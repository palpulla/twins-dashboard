"""Generate one week of Instagram drafts (Mon/Wed/Fri) with preflight gating."""
from __future__ import annotations

import argparse
import datetime as dt
from pathlib import Path
from typing import Callable

import frontmatter

from engine.config import InstagramConfig, load_instagram_config
from engine.ig_planner import SlotSpec, plan_slot
from engine.ig_visuals import RealAsset, resolve_visual
from engine.ig_rules import check_instagram_caption
from engine.ig_draft import Draft, SourceRecord, draft_to_markdown

CaptionFn = Callable[[SlotSpec], str]
AssetsFn = Callable[[SlotSpec], list[RealAsset]]

ROOT = Path(__file__).resolve().parent.parent


def generate_week(monday: dt.date, cfg: InstagramConfig, out_dir: Path, hiring: bool,
                  caption_fn: CaptionFn, assets_fn: AssetsFn,
                  month_ai_used: int, month_total_posts: int) -> dict:
    if monday.weekday() != 0:
        raise ValueError(f"{monday} is not a Monday; generate_week expects the week's Monday")
    out_dir.mkdir(parents=True, exist_ok=True)
    held_dir = out_dir / "held"
    held_dir.mkdir(parents=True, exist_ok=True)

    written = {"passed": [], "held": []}
    ai_used = month_ai_used
    total = month_total_posts

    for offset in (0, 2, 4):  # Mon, Wed, Fri
        day = monday + dt.timedelta(days=offset)
        slot = plan_slot(day, cfg.cycle_anchor, hiring=hiring)
        caption = caption_fn(slot)
        visual = resolve_visual(slot.slot, assets_fn(slot), ai_used, total, cfg.max_ai_fraction)
        if visual.kind == "ai":
            ai_used += 1
        total += 1

        source = SourceRecord(
            asset_source="real_photo" if visual.kind == "real" else "ai_graphic",
            job_folder=None, review_verified=None, confirmed_city=None,
            offer_used=None,
            needs_approval=(["AI fraction cap exceeded"] if visual.needs_approval else []),
        )
        draft = Draft(slot=slot, caption=caption, cta="", city=None,
                      hashtags=[], visual=visual, source=source)

        report = check_instagram_caption(caption, cfg)
        md = draft_to_markdown(draft)
        fname = f"{slot.date}_{slot.slot}.md"
        if report.passed:
            (held_dir / fname).unlink(missing_ok=True)
            (out_dir / fname).write_text(md)
            written["passed"].append(str(out_dir / fname))
        else:
            post = frontmatter.loads(md)
            post["_held_reason"] = [v.message for v in report.violations]
            (out_dir / fname).unlink(missing_ok=True)
            (held_dir / fname).write_text(frontmatter.dumps(post))
            written["held"].append(str(held_dir / fname))

    written["ai_used"] = ai_used
    written["total"] = total
    return written


def _real_caption_fn(slot: SlotSpec) -> str:
    # Wire the existing content-engine generator here in a follow-up; placeholder
    # captions are never published (preflight + human approval gate downstream).
    raise NotImplementedError("Wire engine.generator in the publishing plan (Plan 2).")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--monday", required=True, help="ISO date of the week's Monday")
    parser.add_argument("--out", default="pending/instagram")
    parser.add_argument("--hiring", action="store_true")
    args = parser.parse_args()
    cfg = load_instagram_config(ROOT / "config" / "instagram.yaml")
    result = generate_week(
        monday=dt.date.fromisoformat(args.monday), cfg=cfg, out_dir=Path(args.out),
        hiring=args.hiring, caption_fn=_real_caption_fn, assets_fn=lambda s: [],
        month_ai_used=0, month_total_posts=0,
    )
    print(f"passed: {len(result['passed'])}  held: {len(result['held'])}")


if __name__ == "__main__":
    main()
