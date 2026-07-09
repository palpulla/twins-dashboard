"""Generate one week of Instagram drafts (Mon/Wed/Fri) with preflight gating."""
from __future__ import annotations

import argparse
import datetime as dt
from dataclasses import replace as replace_fields
from pathlib import Path
from typing import Callable

import frontmatter

from engine.config import InstagramConfig, load_brand, load_instagram_config
from engine.ig_assets import InboxAsset, scan_inbox
from engine.ig_captions import generate_caption, pick_topic
from engine.ig_design import render_card
from engine.ig_planner import SlotSpec, plan_slot
from engine.ig_state import load_month_state, save_month_state
from engine.ig_visuals import RealAsset, resolve_visual
from engine.ig_rules import check_instagram_caption, check_instagram_draft
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


def _build_hashtags(cfg: InstagramConfig, city: str | None) -> list[str]:
    extra = cfg.city_hashtags.get(city) if city else None
    candidates = list(cfg.hashtags_base) + ([extra] if extra else [])
    tags: list[str] = []
    for tag in candidates:
        if tag and tag not in tags:
            tags.append(tag)
    return tags[:5]


def _pop_matching_asset(pool: list[InboxAsset], asset_path: str | None) -> InboxAsset | None:
    for i, item in enumerate(pool):
        if item.asset.path == asset_path:
            return pool.pop(i)
    return None


def generate_week_wired(monday: dt.date, cfg: InstagramConfig, out_dir: Path, hiring: bool,
                        inbox: Path, state_path: Path) -> dict:
    if monday.weekday() != 0:
        raise ValueError(f"{monday} is not a Monday; generate_week_wired expects the week's Monday")
    brand = load_brand(ROOT / "config" / "brand.yaml")
    state = load_month_state(state_path, monday)

    out_dir.mkdir(parents=True, exist_ok=True)
    held_dir = out_dir / "held"
    held_dir.mkdir(parents=True, exist_ok=True)

    # Scan the inbox exactly once; the pool is mutated as slots consume assets so
    # the same photo is never attached to two drafts in the same week.
    pool = scan_inbox(inbox, known_cities=list(cfg.city_hashtags.keys()))

    written = {"passed": [], "held": []}
    ai_used = state["ai_used"]
    total = state["total"]

    for offset in (0, 2, 4):  # Mon, Wed, Fri
        day = monday + dt.timedelta(days=offset)
        slot = plan_slot(day, cfg.cycle_anchor, hiring=hiring)

        candidate_assets = [item.asset for item in pool]
        visual = resolve_visual(slot.slot, candidate_assets, ai_used, total, cfg.max_ai_fraction)

        chosen = _pop_matching_asset(pool, visual.asset_path) if visual.kind == "real" else None
        city = chosen.city if chosen else None

        # Every published post must be a branded card, never the raw inbox
        # photo. Render it here; a render failure holds the draft for manual
        # review instead of crashing the whole week's run.
        render_error: str | None = None
        job_folder = str(Path(chosen.asset.path).parent) if chosen is not None else None
        if chosen is not None:
            headline = cfg.design["kind_headline"][chosen.asset.kind]
            kicker = cfg.design["slot_kicker"][slot.slot]
            # Collages must never be cover-cropped or the comparison is lost.
            fit = "contain" if chosen.asset.kind == "before_after" else ""
            try:
                card_path = render_card(Path(chosen.asset.path), headline, kicker, cfg, ROOT,
                                        fit=fit)
                visual = replace_fields(visual, asset_path=str(card_path),
                                        original_photo=chosen.asset.path)
            except RuntimeError as e:
                render_error = f"branded card render failed: {e}"

        if visual.kind == "ai":
            ai_used += 1
        total += 1

        topic = pick_topic(slot, cfg)
        caption = generate_caption(slot, topic, city, cfg, brand)
        cta = cfg.slot_cta[slot.slot]
        hashtags = _build_hashtags(cfg, city)

        if chosen is not None and chosen.asset.kind == "verified_review":
            asset_source = "verified_review"
        elif visual.kind == "real":
            asset_source = "real_photo"
        else:
            asset_source = "ai_graphic"

        source = SourceRecord(
            asset_source=asset_source,
            job_folder=job_folder,
            review_verified=True if asset_source == "verified_review" else None,
            confirmed_city=city,
            offer_used=topic if slot.slot == "offer" else None,
            needs_approval=(["AI fraction cap exceeded"] if visual.needs_approval else []),
        )
        draft = Draft(slot=slot, caption=caption, cta=cta, city=city,
                      hashtags=hashtags, visual=visual, source=source)

        report = check_instagram_draft(caption, cta, cfg)
        md = draft_to_markdown(draft)
        fname = f"{slot.date}_{slot.slot}.md"
        if report.passed and render_error is None:
            (held_dir / fname).unlink(missing_ok=True)
            (out_dir / fname).write_text(md)
            written["passed"].append(str(out_dir / fname))
        else:
            post = frontmatter.loads(md)
            reasons = [v.message for v in report.violations]
            if render_error:
                reasons.append(render_error)
            post["_held_reason"] = reasons
            (out_dir / fname).unlink(missing_ok=True)
            (held_dir / fname).write_text(frontmatter.dumps(post))
            written["held"].append(str(held_dir / fname))

    state["ai_used"] = ai_used
    state["total"] = total
    save_month_state(state_path, state)

    written["ai_used"] = ai_used
    written["total"] = total
    return written


def _next_monday(today: dt.date) -> dt.date:
    offset = (7 - today.weekday()) % 7
    return today + dt.timedelta(days=offset)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--monday", default=None,
                        help="ISO date of the week's Monday (default: next Monday from today)")
    parser.add_argument("--hiring", action="store_true")
    parser.add_argument("--out", default=str(ROOT / "pending" / "instagram"))
    parser.add_argument("--inbox", default=str(ROOT / "assets" / "instagram" / "inbox"))
    args = parser.parse_args()
    cfg = load_instagram_config(ROOT / "config" / "instagram.yaml")
    monday = dt.date.fromisoformat(args.monday) if args.monday else _next_monday(dt.date.today())
    result = generate_week_wired(
        monday=monday, cfg=cfg, out_dir=Path(args.out), hiring=args.hiring,
        inbox=Path(args.inbox), state_path=ROOT / "data" / "ig_month_state.json",
    )
    print(f"passed: {len(result['passed'])}  held: {len(result['held'])}")


if __name__ == "__main__":
    main()
