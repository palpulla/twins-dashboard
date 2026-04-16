"""Tests for engine.prompts builders."""
from __future__ import annotations

from pathlib import Path

from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.prompts import build_generation_prompt, build_regeneration_prompt


def _ctx(brand_yaml, service_area_yaml, rules_yaml, pillars_yaml):
    return {
        "brand": load_brand(brand_yaml),
        "service_area": load_service_area(service_area_yaml),
        "rules": load_rules(rules_yaml),
        "pillars": load_pillars(pillars_yaml),
    }


def _pillars_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "pillars.yaml"
    p.write_text("""
pillars:
  emergency_repairs:
    description: desc
    funnel_stage: high_intent_problem_aware
    seed_patterns: []
    format_bias: [video_script]
    priority_weight: 1.5
""")
    return p


def _rules_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "rules.yaml"
    p.write_text("""
voice:
  persona: "Madison garage door tech"
  structure: "hook -> problem -> authority -> CTA"
  reading_level_max: 8
hook_requirements: {}
anti_ai_spam_blacklist:
  phrases: []
  structural: []
specificity_requirements:
  must_include_one_of: []
  must_avoid_phrases: []
cta_rules:
  one_per_piece: true
  funnel_stage_mapping:
    high_intent_problem_aware: call_now
local_embedding:
  mention_frequency:
    caption: [1, 1]
  placement_rules:
    no_consecutive_sentences: true
    require_mention_in_first_pct: 30
    prefer_specific_town_over_region: true
""")
    return p


def test_build_generation_prompt_caption(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml,
               _rules_yaml(tmp_path), _pillars_yaml(tmp_path))
    system, user = build_generation_prompt(
        content_format="caption",
        query_text="garage door won't open loud bang",
        cluster_name="broken_spring",
        pillar_name="emergency_repairs",
        funnel_stage="high_intent_problem_aware",
        target_town_display="Middleton",
        target_neighborhood=None,
        ctx=ctx,
    )
    assert "Madison garage door tech" in system
    assert "caption" in user.lower()
    assert "Middleton" in user
    assert "garage door won't open loud bang" in user
    assert "(608) 555-0199" in user
    assert "call_now" in user or "phone" in user.lower()


def test_build_generation_prompt_video_script(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml,
               _rules_yaml(tmp_path), _pillars_yaml(tmp_path))
    system, user = build_generation_prompt(
        content_format="video_script",
        query_text="spring snapped loud bang",
        cluster_name="broken_spring",
        pillar_name="emergency_repairs",
        funnel_stage="high_intent_problem_aware",
        target_town_display="Madison",
        target_neighborhood="Maple Bluff",
        ctx=ctx,
    )
    assert "HOOK" in user
    assert "SHOT LIST" in user or "shot list" in user.lower()


def test_build_regeneration_prompt_includes_critique(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml,
               _rules_yaml(tmp_path), _pillars_yaml(tmp_path))
    system, user = build_regeneration_prompt(
        content_format="caption",
        previous_output="bad output",
        critique_lines=["- [anti_ai_spam:phrase] Contains blacklisted phrase 'journey'"],
        query_text="q",
        cluster_name="c",
        pillar_name="emergency_repairs",
        funnel_stage="high_intent_problem_aware",
        target_town_display="Madison",
        target_neighborhood=None,
        ctx=ctx,
    )
    assert "bad output" in user
    assert "anti_ai_spam:phrase" in user
    assert "journey" in user
