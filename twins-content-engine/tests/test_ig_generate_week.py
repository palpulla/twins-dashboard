import datetime as dt
from pathlib import Path
from engine.config import load_instagram_config
from engine.ig_visuals import RealAsset
from scripts.generate_ig_week import generate_week

CFG = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")


def test_generates_three_drafts_split_pass_and_held(tmp_path):
    def caption_fn(slot):
        if slot.slot == "offer":
            return "Get $25 off any repair."  # will be held: $25 not an approved offer
        return f"Recent repair in Verona. {slot.slot}."

    def assets_fn(slot):
        return [RealAsset(kind="completed_job", path="/jobs/verona/done.jpg")]

    written = generate_week(
        monday=dt.date(2026, 7, 6), cfg=CFG, out_dir=tmp_path,
        hiring=False, caption_fn=caption_fn, assets_fn=assets_fn,
        month_ai_used=0, month_total_posts=0,
    )
    passed = list((tmp_path).glob("*.md"))
    held = list((tmp_path / "held").glob("*.md"))
    assert len(passed) == 2      # proof + value
    assert len(held) == 1        # offer caption held: $25 not an approved offer
    assert written["held"][0].endswith(".md")

    import frontmatter
    held_post = frontmatter.loads(Path(written["held"][0]).read_text())
    assert any("$25" in reason for reason in held_post["_held_reason"])


def test_ai_cap_flags_needs_approval_on_passed_drafts(tmp_path):
    import frontmatter
    def caption_fn(slot):
        return f"Recent repair in Verona. {slot.slot}."  # clean, passes preflight
    def assets_fn(slot):
        return []  # no real asset -> AI educational graphic
    written = generate_week(
        monday=dt.date(2026, 7, 6), cfg=CFG, out_dir=tmp_path,
        hiring=False, caption_fn=caption_fn, assets_fn=assets_fn,
        month_ai_used=3, month_total_posts=9,  # already over the 0.34 cap
    )
    passed = sorted(tmp_path.glob("*.md"))
    assert len(passed) == 3
    assert written["ai_used"] == 6 and written["total"] == 12
    first = frontmatter.loads(passed[0].read_text())
    assert first["source"]["needs_approval"] == ["AI fraction cap exceeded"]


def test_non_monday_monday_raises(tmp_path):
    import pytest
    with pytest.raises(ValueError):
        generate_week(monday=dt.date(2026, 7, 7), cfg=CFG, out_dir=tmp_path,
                      hiring=False, caption_fn=lambda s: "Recent repair in Verona.",
                      assets_fn=lambda s: [], month_ai_used=0, month_total_posts=0)
