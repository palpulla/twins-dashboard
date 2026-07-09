"""Publish due, approved Instagram drafts via Composio. Dry-run by default.

Fail-safe direction — mark-then-post: each publishable draft is stamped with
``publish_attempted_at`` and moved OUT of ``approved/`` into ``published/``
BEFORE the Composio call, so a crash mid-post can never leave a publishable
copy behind that a rerun would double-post. On confirmed success the file is
rewritten with ``published_at``; on failure (exception or an unsuccessful
Composio response) it is rewritten with ``publish_error`` and counted as
failed — it is never moved back to ``approved/`` and never counted toward the
month totals. A hard crash mid-post leaves a file in ``published/`` with
``publish_attempted_at`` but neither ``published_at`` nor ``publish_error``;
every run detects attempted-but-unconfirmed files and prints a loud warning
that they need a manual check on Instagram before re-approving. We accept a
potentially lost post (human re-approves after checking IG) over a double post.

Due-ness is by DATE only: a draft dated today or earlier is due regardless of
time of day. ``post_time_local`` in config is informational until an external
scheduler invokes this script at the posting time.

Publishing is a two-step Composio flow (Instagram's Graph API requires it):
first create a media container (``INSTAGRAM_POST_IG_USER_MEDIA``) from a
*public* ``image_url``, then publish that container
(``INSTAGRAM_POST_IG_USER_MEDIA_PUBLISH``) using the creation id the first
call returns. Instagram will not fetch a local file path, so a draft can only
be published once its frontmatter carries a public ``image_url`` — the local
``visual.asset_path`` proof asset is still required to exist (when
``visual.kind == "real"``) but is no longer what gets posted. Until an
image-hosting step exists to populate ``image_url``, ``publish_tools.verified``
stays ``false`` and drafts without a public URL are skipped as unsafe.
"""
from __future__ import annotations

import argparse
import datetime as dt
from pathlib import Path

import frontmatter
import yaml

from engine.composio_client import run_tool
from engine.config import InstagramConfig, load_instagram_config
from engine.ig_rules import check_instagram_draft
from engine.ig_state import load_month_state, save_month_state

ROOT = Path(__file__).resolve().parent.parent


def _posted_caption(post) -> str:
    """Caption exactly as it will be posted: body + frontmatter hashtags."""
    caption = post.content.strip()
    tags = [t for t in (post.get("hashtags") or []) if t]
    if tags:
        caption = f"{caption}\n\n{' '.join(tags)}"
    return caption


def _extract_creation_id(resp: dict) -> str | None:
    """Liberally read a creation/container id out of a Composio response.

    Be liberal reading (accept "id", "creation_id", or a nested data.id),
    strict validating (return None — a hard failure upstream — if nothing
    usable is found).
    """
    data = resp.get("data") if isinstance(resp, dict) else None
    if isinstance(data, dict):
        for key in ("id", "creation_id"):
            val = data.get(key)
            if val:
                return str(val)
        nested = data.get("data")
        if isinstance(nested, dict):
            val = nested.get("id")
            if val:
                return str(val)
    return None


def _publish_via_composio(cfg: InstagramConfig, caption: str, image_url: str) -> None:
    """Two-step Composio publish: create the media container, then publish it.

    Raises RuntimeError on any failure (unsuccessful response or a container
    response with no extractable id) so the caller's existing try/except
    records it as ``publish_error``, per the mark-then-post safety contract.
    """
    pt = cfg.publish_tools
    create_resp = run_tool(pt["media_create"], {
        "ig_user_id": pt["ig_user_id"],
        "image_url": image_url,
        "caption": caption,
    })
    if not isinstance(create_resp, dict) or create_resp.get("successful") is not True:
        raise RuntimeError(f"composio media container creation not successful: {create_resp!r}")
    creation_id = _extract_creation_id(create_resp)
    if not creation_id:
        raise RuntimeError(f"composio media container response missing an id: {create_resp!r}")
    publish_resp = run_tool(pt["media_publish"], {
        "ig_user_id": pt["ig_user_id"],
        "creation_id": creation_id,
    })
    if not isinstance(publish_resp, dict) or publish_resp.get("successful") is not True:
        raise RuntimeError(f"composio media publish not successful: {publish_resp!r}")


def _warn_unconfirmed_attempts(published_dir: Path) -> None:
    """Flag files whose publish was attempted but never confirmed successful."""
    if not published_dir.exists():
        return
    for f in sorted(published_dir.glob("*.md")):
        try:
            post = frontmatter.loads(f.read_text())
        except Exception as e:
            print(f"WARNING: {f.name}: unparseable — needs manual inspection ({e})")
            continue
        if post.get("publish_attempted_at") and not post.get("published_at"):
            print(
                f"WARNING: {f.name} has publish_attempted_at but no published_at — "
                "needs manual check on Instagram before re-approving."
            )


def publish_due(approved_dir: Path, published_dir: Path, state_path: Path,
                cfg: InstagramConfig, today: dt.date, live: bool) -> dict:
    if live and not cfg.publish_tools.get("verified"):
        raise RuntimeError(
            "publish_tools.verified is false — run the slug-discovery step and set "
            "verified: true in config/instagram.yaml before --live."
        )
    result = {"due": 0, "published": 0, "skipped_unsafe": 0,
              "failed": 0, "skipped_invalid": 0}
    _warn_unconfirmed_attempts(published_dir)
    state = load_month_state(state_path, today) if live else None
    files = sorted(approved_dir.glob("*.md")) if approved_dir.exists() else []
    for f in files:
        try:
            post = frontmatter.loads(f.read_text())
            if post.get("published_at"):
                continue
            if post.get("publish_attempted_at"):
                print(
                    f"WARNING: {f.name} in approved/ already has publish_attempted_at — "
                    "needs manual check on Instagram before re-approving; skipping."
                )
                continue
            if dt.date.fromisoformat(str(post["date"])) > today:
                continue
        except (ValueError, KeyError, yaml.YAMLError) as e:
            result["skipped_invalid"] += 1
            print(f"INVALID (skipped): {f.name}: {e}")
            continue
        result["due"] += 1
        # Validate the caption AS POSTED (body + hashtags) so the banned-hashtag
        # rule protects the publish path too.
        caption = _posted_caption(post)
        report = check_instagram_draft(caption, str(post.get("cta", "")), cfg)
        if not report.passed:
            result["skipped_unsafe"] += 1
            print(f"UNSAFE (skipped): {f.name}: {[v.message for v in report.violations]}")
            continue
        image_url = post.get("image_url")
        if not (isinstance(image_url, str) and image_url.startswith("http")):
            result["skipped_unsafe"] += 1
            reason = "no public image_url (hosting step pending)"
            if live:
                print(f"UNSAFE (skipped): {f.name}: {reason}")
            else:
                print(f"DRY-RUN would skip (missing image_url): {f.name}: {reason}")
            continue
        asset_path = post.get("visual", {}).get("asset_path")
        if post.get("visual", {}).get("kind") == "real" and (
            not asset_path or not Path(asset_path).exists()
        ):
            result["skipped_unsafe"] += 1
            if live:
                print(f"UNSAFE (skipped): {f.name}: missing image (not on disk): {asset_path!r}")
            else:
                print(f"DRY-RUN would skip (missing image): {f.name}: {asset_path!r}")
            continue
        if not live:
            print(f"DRY-RUN would publish: {f.name} ({post.get('slot')})")
            continue
        # Mark-then-post: stamp + move BEFORE calling Composio (see module docstring).
        post["publish_attempted_at"] = dt.datetime.now().isoformat(timespec="seconds")
        published_dir.mkdir(parents=True, exist_ok=True)
        dest = published_dir / f.name
        dest.write_text(frontmatter.dumps(post))
        f.unlink()
        error = None
        try:
            # Caption body already ends with the CTA line (appended by
            # ig_captions.generate_caption) — do NOT concatenate post["cta"] again.
            _publish_via_composio(cfg, caption, image_url)
        except Exception as e:
            error = str(e)
        if error:
            post["publish_error"] = error
            dest.write_text(frontmatter.dumps(post))
            result["failed"] += 1
            print(f"FAILED: {f.name}: {error} — needs manual check on Instagram before re-approving.")
            continue
        post["published_at"] = dt.datetime.now().isoformat(timespec="seconds")
        dest.write_text(frontmatter.dumps(post))
        state["total"] += 1
        if post.get("visual", {}).get("kind") == "ai":
            state["ai_used"] += 1
        save_month_state(state_path, state)
        result["published"] += 1
    return result


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--live", action="store_true", help="actually publish (default: dry-run)")
    args = ap.parse_args()
    print("note: due-ness is by DATE only — time of day comes from the scheduler "
          "(post_time_local is informational until scheduling lands).")
    cfg = load_instagram_config(ROOT / "config" / "instagram.yaml")
    res = publish_due(ROOT / "approved" / "instagram", ROOT / "published" / "instagram",
                      ROOT / "data" / "ig_month_state.json", cfg,
                      today=dt.date.today(), live=args.live)
    print(res)


if __name__ == "__main__":
    main()
