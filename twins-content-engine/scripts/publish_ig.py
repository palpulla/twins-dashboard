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
"""
from __future__ import annotations

import argparse
import datetime as dt
from pathlib import Path

import frontmatter

from engine.composio_client import run_tool
from engine.config import InstagramConfig, load_instagram_config
from engine.ig_rules import check_instagram_draft
from engine.ig_state import load_month_state, save_month_state

ROOT = Path(__file__).resolve().parent.parent


def _warn_unconfirmed_attempts(published_dir: Path) -> None:
    """Flag files whose publish was attempted but never confirmed successful."""
    if not published_dir.exists():
        return
    for f in sorted(published_dir.glob("*.md")):
        post = frontmatter.loads(f.read_text())
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
        except (ValueError, KeyError) as e:
            result["skipped_invalid"] += 1
            print(f"INVALID (skipped): {f.name}: {e}")
            continue
        result["due"] += 1
        caption = post.content.strip()
        report = check_instagram_draft(caption, str(post.get("cta", "")), cfg)
        if not report.passed:
            result["skipped_unsafe"] += 1
            print(f"UNSAFE (skipped): {f.name}: {[v.message for v in report.violations]}")
            continue
        if not live:
            print(f"DRY-RUN would publish: {f.name} ({post.get('slot')})")
            continue
        asset_path = post.get("visual", {}).get("asset_path")
        if not asset_path or not Path(asset_path).exists():
            result["skipped_unsafe"] += 1
            print(f"UNSAFE (skipped): {f.name}: image missing or not on disk: {asset_path!r}")
            continue
        # Mark-then-post: stamp + move BEFORE calling Composio (see module docstring).
        post["publish_attempted_at"] = dt.datetime.now().isoformat(timespec="seconds")
        published_dir.mkdir(parents=True, exist_ok=True)
        dest = published_dir / f.name
        dest.write_text(frontmatter.dumps(post))
        f.unlink()
        error = None
        try:
            resp = run_tool(cfg.publish_tools["image_post"], {
                "caption": caption,
                "image_path": asset_path,
            })
            if not isinstance(resp, dict) or resp.get("successful") is not True:
                error = f"composio response not successful: {resp!r}"
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
