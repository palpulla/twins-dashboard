from engine.ig_visuals import resolve_visual, VisualPlan, RealAsset


def test_prefers_completed_job_when_available():
    assets = [
        RealAsset(kind="completed_job", path="/jobs/verona/done.jpg"),
        RealAsset(kind="truck", path="/jobs/verona/truck.jpg"),
    ]
    plan = resolve_visual("proof", assets, ai_used=0, total_posts=0, max_ai_fraction=0.34)
    assert plan.kind == "real"
    assert plan.asset_path == "/jobs/verona/done.jpg"
    assert plan.fallback_level == 1


def test_proof_falls_to_educational_ai_not_fake_job():
    plan = resolve_visual("proof", [], ai_used=0, total_posts=0, max_ai_fraction=0.34)
    assert plan.kind == "ai"
    assert plan.ai_spec == "educational_graphic"
    assert plan.fallback_level == 5
    assert "fake" not in plan.ai_spec


def test_ai_cap_flags_when_exceeded():
    # 3 of 9 posts already AI, adding another crosses 0.34
    plan = resolve_visual("value", [], ai_used=3, total_posts=9, max_ai_fraction=0.34)
    assert plan.kind == "ai"
    assert plan.needs_approval is True
