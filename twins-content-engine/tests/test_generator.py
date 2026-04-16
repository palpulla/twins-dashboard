"""Tests for engine.generator.generate_for_cluster."""
from __future__ import annotations

from pathlib import Path

import pytest

from engine.claude_client import FakeClaudeClient
from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.db import (
    get_cluster_by_name,
    init_db,
    insert_cluster,
    insert_query,
    list_pending_content,
)
from engine.generator import GenerationResult, generate_for_cluster


@pytest.fixture
def pillars_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "pillars.yaml"
    p.write_text("""
pillars:
  emergency_repairs:
    description: desc
    funnel_stage: high_intent_problem_aware
    seed_patterns: []
    format_bias: [video_script, gbp_post, faq, caption, blog_snippet]
    priority_weight: 1.5
""")
    return p


@pytest.fixture
def rules_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "rules.yaml"
    p.write_text("""
voice:
  persona: "Madison garage door tech"
  structure: "hook -> problem -> authority -> CTA"
  reading_level_max: 8
hook_requirements: {}
anti_ai_spam_blacklist:
  phrases: ["journey", "game-changer"]
  structural: []
specificity_requirements:
  must_include_one_of: [specific_number, named_location, timeframe, concrete_detail]
  must_avoid_phrases: []
cta_rules:
  one_per_piece: true
  funnel_stage_mapping:
    high_intent_problem_aware: call_now
local_embedding:
  mention_frequency:
    caption: [1, 5]
    video_script: [1, 5]
    gbp_post: [1, 5]
    faq: [1, 5]
    blog_snippet: [1, 5]
  placement_rules:
    no_consecutive_sentences: true
    require_mention_in_first_pct: 30
    prefer_specific_town_over_region: true
""")
    return p


def _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml, pillars_yaml):
    return {
        "brand": load_brand(sample_brand_yaml),
        "service_area": load_service_area(sample_service_area_yaml),
        "rules": load_rules(rules_yaml),
        "pillars": load_pillars(pillars_yaml),
    }


@pytest.fixture
def seeded(tmp_path, sample_brand_yaml, sample_service_area_yaml, rules_yaml, pillars_yaml):
    db = tmp_path / "test.db"
    init_db(db)
    cid = insert_cluster(
        db, name="broken_spring", pillar="emergency_repairs",
        service_type="spring_replacement", funnel_stage="high_intent_problem_aware",
        priority_score=9, notes=None,
    )
    insert_query(
        db, cluster_id=cid, query_text="garage door won't open loud bang",
        phrasing_type="symptom", geo_modifier="madison_wi", source="raw_seed",
        priority_score=9, notes=None,
    )
    return {
        "db": db,
        "tmp": tmp_path,
        "ctx": _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml, pillars_yaml),
    }


def _pass_caption(town="Madison") -> str:
    return (
        f"Your garage door won't open after a loud bang?\n"
        f"That's usually a snapped torsion spring. In {town} we fix it same-day for $250-400.\n"
        f"Call (608) 555-0199."
    )


def test_generator_produces_five_format_files(seeded):
    caption_pass = _pass_caption()
    fake = FakeClaudeClient(responses=[caption_pass] * 5)
    result = generate_for_cluster(
        db_path=seeded["db"],
        cluster_name="broken_spring",
        client=fake,
        ctx=seeded["ctx"],
        output_dir=seeded["tmp"] / "pending",
        brief_dir=seeded["tmp"] / "briefs",
        model_name="claude-sonnet-4-6",
    )
    assert isinstance(result, GenerationResult)
    assert len(result.accepted) == 5
    assert len(result.rejected) == 0
    for gc in result.accepted:
        assert Path(gc.content_path).exists()
    pending = list_pending_content(seeded["db"])
    assert {r["format"] for r in pending} == {
        "video_script", "caption", "gbp_post", "faq", "blog_snippet"
    }


def test_generator_retries_once_on_rule_failure(seeded):
    bad_caption = "Whether you're searching for help, this is a journey. Call (608) 555-0199."
    good_caption = _pass_caption()
    fake = FakeClaudeClient(responses=[
        good_caption,      # video_script
        bad_caption,       # caption (fails)
        good_caption,      # caption (retry passes)
        good_caption,      # gbp_post
        good_caption,      # faq
        good_caption,      # blog_snippet
    ])
    result = generate_for_cluster(
        db_path=seeded["db"],
        cluster_name="broken_spring",
        client=fake,
        ctx=seeded["ctx"],
        output_dir=seeded["tmp"] / "pending",
        brief_dir=seeded["tmp"] / "briefs",
        model_name="claude-sonnet-4-6",
    )
    assert len(result.accepted) == 5
    assert len(fake.calls) == 6


def test_generator_rejects_after_second_failure(seeded):
    bad = "journey journey game-changer"
    good = _pass_caption()
    fake = FakeClaudeClient(responses=[
        good,       # video_script ok
        bad, bad,   # caption: fail, retry-fail
        good,       # gbp_post
        good,       # faq
        good,       # blog_snippet
    ])
    result = generate_for_cluster(
        db_path=seeded["db"],
        cluster_name="broken_spring",
        client=fake,
        ctx=seeded["ctx"],
        output_dir=seeded["tmp"] / "pending",
        brief_dir=seeded["tmp"] / "briefs",
        model_name="claude-sonnet-4-6",
    )
    assert len(result.rejected) == 1
    assert result.rejected[0].format == "caption"
    assert "_REJECTED_" in Path(result.rejected[0].content_path).name


def test_generator_emits_brief_for_video_script(seeded):
    good = _pass_caption()
    fake = FakeClaudeClient(responses=[good] * 5)
    result = generate_for_cluster(
        db_path=seeded["db"],
        cluster_name="broken_spring",
        client=fake,
        ctx=seeded["ctx"],
        output_dir=seeded["tmp"] / "pending",
        brief_dir=seeded["tmp"] / "briefs",
        model_name="claude-sonnet-4-6",
    )
    vid = next(g for g in result.accepted if g.format == "video_script")
    assert vid.brief_path is not None
    assert Path(vid.brief_path).exists()
