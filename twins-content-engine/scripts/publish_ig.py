"""Publish due, approved Instagram drafts via Composio. Dry-run by default."""
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


def publish_due(approved_dir: Path, published_dir: Path, state_path: Path,
                cfg: InstagramConfig, today: dt.date, live: bool) -> dict:
    if live and not cfg.publish_tools.get("verified"):
        raise RuntimeError(
            "publish_tools.verified is false — run the slug-discovery step and set "
            "verified: true in config/instagram.yaml before --live."
        )
    result = {"due": 0, "published": 0, "skipped_unsafe": 0}
    state = load_month_state(state_path, today)
    files = sorted(approved_dir.glob("*.md")) if approved_dir.exists() else []
    for f in files:
        post = frontmatter.loads(f.read_text())
        if post.get("published_at") or dt.date.fromisoformat(str(post["date"])) > today:
            continue
        result["due"] += 1
        report = check_instagram_draft(post.content.strip(), str(post.get("cta", "")), cfg)
        if not report.passed:
            result["skipped_unsafe"] += 1
            print(f"UNSAFE (skipped): {f.name}: {[v.message for v in report.violations]}")
            continue
        if not live:
            print(f"DRY-RUN would publish: {f.name} ({post.get('slot')})")
            continue
        run_tool(cfg.publish_tools["image_post"], {
            "caption": post.content.strip(),
            "image_path": post.get("visual", {}).get("asset_path"),
        })
        post["published_at"] = dt.datetime.now().isoformat(timespec="seconds")
        published_dir.mkdir(parents=True, exist_ok=True)
        (published_dir / f.name).write_text(frontmatter.dumps(post))
        f.unlink()
        state["total"] += 1
        if post.get("visual", {}).get("kind") == "ai":
            state["ai_used"] += 1
        result["published"] += 1
    save_month_state(state_path, state)
    return result


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--live", action="store_true", help="actually publish (default: dry-run)")
    args = ap.parse_args()
    cfg = load_instagram_config(ROOT / "config" / "instagram.yaml")
    res = publish_due(ROOT / "approved" / "instagram", ROOT / "published" / "instagram",
                      ROOT / "data" / "ig_month_state.json", cfg,
                      today=dt.date.today(), live=args.live)
    print(res)


if __name__ == "__main__":
    main()
