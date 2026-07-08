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
    assert post.content == "Broken spring repair in Verona."


def test_draft_serializes_with_none_optionals():
    slot = SlotSpec(date="2026-07-08", week=1, slot="value",
                    format_target="reel", allow_recruiting=False)
    visual = VisualPlan(kind="ai", asset_path=None, ai_spec="educational_graphic",
                        fallback_level=5, needs_approval=False)
    source = SourceRecord(asset_source="ai_graphic", job_folder=None,
                          review_verified=None, confirmed_city=None,
                          offer_used=None, needs_approval=[])
    draft = Draft(slot=slot, caption="How a broken spring behaves.",
                  cta="", city=None, hashtags=[], visual=visual, source=source)
    post = frontmatter.loads(draft_to_markdown(draft))
    assert post["city"] is None
    assert post["source"]["job_folder"] is None
    assert post["hashtags"] == []
    assert post.content == draft.caption
