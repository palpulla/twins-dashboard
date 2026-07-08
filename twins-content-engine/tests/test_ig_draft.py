import frontmatter
from engine.ig_draft import Draft, SourceRecord, draft_to_markdown
from engine.ig_planner import SlotSpec
from engine.ig_visuals import VisualPlan


def _draft():
    slot = SlotSpec(date="2026-07-06", week=1, slot="proof",
                    format_target="carousel", allow_recruiting=False)
    visual = VisualPlan(kind="real", asset_path="/jobs/verona/done.jpg",
                        ai_spec=None, fallback_level=1)
    source = SourceRecord(asset_source="real_photo", job_folder="/jobs/verona",
                          review_verified=None, confirmed_city="Verona",
                          offer_used=None, needs_approval=["confirm city spelling"])
    return Draft(slot=slot, caption="Broken spring repair in Verona.",
                 cta="Send us a photo of the spring and opener label.",
                 city="Verona", hashtags=["#VeronaWI", "#GarageDoorRepair"],
                 visual=visual, source=source)


def test_draft_serializes_with_source_record():
    md = draft_to_markdown(_draft())
    post = frontmatter.loads(md)
    assert post["slot"] == "proof"
    assert post["city"] == "Verona"
    assert post["source"]["asset_source"] == "real_photo"
    assert post["source"]["needs_approval"] == ["confirm city spelling"]
    assert "Broken spring repair in Verona." in post.content
