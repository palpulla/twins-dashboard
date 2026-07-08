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
    assert plan.asset_path is None
    assert "fake" not in plan.ai_spec


def test_ai_cap_flags_when_exceeded():
    # 3 of 9 posts already AI, adding another crosses 0.34
    plan = resolve_visual("value", [], ai_used=3, total_posts=9, max_ai_fraction=0.34)
    assert plan.kind == "ai"
    assert plan.needs_approval is True


def test_before_after_is_level_2():
    plan = resolve_visual("proof", [RealAsset(kind="before_after", path="/a.jpg")],
                          ai_used=0, total_posts=0, max_ai_fraction=0.34)
    assert plan.kind == "real"
    assert plan.fallback_level == 2


def test_verified_review_is_level_3():
    plan = resolve_visual("proof", [RealAsset(kind="verified_review", path="/r.jpg")],
                          ai_used=0, total_posts=0, max_ai_fraction=0.34)
    assert plan.fallback_level == 3


def test_truck_is_level_4():
    plan = resolve_visual("proof", [RealAsset(kind="truck", path="/t.jpg")],
                          ai_used=0, total_posts=0, max_ai_fraction=0.34)
    assert plan.fallback_level == 4


def test_priority_wins_over_list_order():
    assets = [RealAsset(kind="truck", path="/t.jpg"),
              RealAsset(kind="completed_job", path="/done.jpg")]
    plan = resolve_visual("proof", assets, ai_used=0, total_posts=0, max_ai_fraction=0.34)
    assert plan.asset_path == "/done.jpg"
    assert plan.fallback_level == 1


def test_ai_under_cap_does_not_need_approval():
    plan = resolve_visual("value", [], ai_used=1, total_posts=9, max_ai_fraction=0.34)
    assert plan.kind == "ai"
    assert plan.needs_approval is False
