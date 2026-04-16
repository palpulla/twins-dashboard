"""Tests for engine.rules."""
from __future__ import annotations

from pathlib import Path

import pytest

from engine.config import load_rules, load_brand, load_service_area
from engine.rules import Violation, RuleReport, run_rules


@pytest.fixture
def rules_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "rules.yaml"
    p.write_text("""
voice:
  persona: "Madison garage door tech"
  structure: "hook -> problem -> authority -> CTA"
  reading_level_max: 8
hook_requirements:
  video_script:
    min_words: 6
    max_words: 8
    forbidden_openers: [Whether, "Are you", "Did you know", "In today's"]
    must_reference_one_of: [specific_symptom, number, neighborhood]
  caption:
    must_mirror_search_query: true
  gbp_post:
    first_n_words: 10
    must_contain_one_of_towns: true
  blog_snippet:
    first_sentence_must_contain_one_of: [specific_number, timeframe, price_range]
anti_ai_spam_blacklist:
  phrases: ["whether you're", "game-changer", "elevate your", "journey"]
  structural: [em_dash_pair_as_dramatic_pause, formulaic_triadic_list]
specificity_requirements:
  must_include_one_of: [specific_number, named_location, timeframe, concrete_detail]
  must_avoid_phrases: ["we're the best", "trusted by many"]
cta_rules:
  one_per_piece: true
  funnel_stage_mapping:
    high_intent_problem_aware: call_now
    mid_intent_price_checking: free_estimate
    low_intent_educational: save_or_share
local_embedding:
  mention_frequency:
    video_script: [1, 2]
    caption: [1, 1]
    gbp_post: [2, 3]
    faq: [2, 2]
    blog_snippet: [3, 5]
  placement_rules:
    no_consecutive_sentences: true
    require_mention_in_first_pct: 30
    prefer_specific_town_over_region: true
""")
    return p


def _ctx(brand_yaml: Path, service_area_yaml: Path, rules_yaml: Path) -> dict:
    return {
        "brand": load_brand(brand_yaml),
        "service_area": load_service_area(service_area_yaml),
        "rules": load_rules(rules_yaml),
    }


def test_clean_caption_passes(sample_brand_yaml, sample_service_area_yaml, rules_yaml):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = (
        "Garage door won't close all the way?\n"
        "Usually the bottom safety sensor is misaligned.\n"
        "Twins Garage Doors in Madison can fix it same-day for $120.\n"
        "Call (608) 555-0199."
    )
    report = run_rules(
        format="caption",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert report.passed, report.violations


def test_blacklist_phrase_flagged(sample_brand_yaml, sample_service_area_yaml, rules_yaml):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = (
        "Whether you're in Madison or Middleton, our game-changer "
        "service will elevate your garage door experience. Call now."
    )
    report = run_rules(
        format="caption",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert not report.passed
    rule_ids = {v.rule_id for v in report.violations}
    assert "anti_ai_spam:phrase" in rule_ids


def test_video_script_bad_hook_opener_flagged(
    sample_brand_yaml, sample_service_area_yaml, rules_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = (
        "HOOK (0:00-0:02): Did you know garage doors break?\n\n"
        "PROBLEM: stuff\n"
        "AUTHORITY: 13 years in Madison serving Middleton too.\n"
        "CTA: Call (608) 555-0199."
    )
    report = run_rules(
        format="video_script",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert not report.passed
    rule_ids = {v.rule_id for v in report.violations}
    assert "hook:forbidden_opener" in rule_ids


def test_gbp_first_words_must_contain_town(
    sample_brand_yaml, sample_service_area_yaml, rules_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = (
        "Your garage door spring just snapped and you can't open it. "
        "That's a torsion spring under 200 lbs of tension — don't try to lift the door manually. "
        "In Madison we handle these same-day; standard replacement runs $250-400. "
        "Middleton and Sun Prairie too. Call (608) 555-0199 to get back in by tonight."
    )
    report = run_rules(
        format="gbp_post",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert not report.passed
    ids = {v.rule_id for v in report.violations}
    assert "hook:gbp_town" in ids


def test_specificity_requirement(sample_brand_yaml, sample_service_area_yaml, rules_yaml):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = (
        "Your garage door broke?\n"
        "Things can go wrong sometimes. We help people fix things.\n"
        "Call us for service."
    )
    report = run_rules(
        format="caption",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert not report.passed
    ids = {v.rule_id for v in report.violations}
    assert "specificity:required_marker" in ids


def test_local_embedding_frequency(
    sample_brand_yaml, sample_service_area_yaml, rules_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    # caption expects exactly 1 town mention
    zero_mentions = (
        "Garage door won't close?\n"
        "Usually the sensor at the bottom needs realignment.\n"
        "Fixed same-day for $120. Call (608) 555-0199."
    )
    report = run_rules(
        format="caption",
        content=zero_mentions,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    ids = {v.rule_id for v in report.violations}
    assert "local_embedding:frequency" in ids


def test_cta_one_per_piece(sample_brand_yaml, sample_service_area_yaml, rules_yaml):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    # two phone-number CTAs
    content = (
        "Garage door spring snapped in Madison? Call (608) 555-0199.\n"
        "Don't lift it. Torsion springs hold 200 lbs. $250-400 typical fix.\n"
        "Call (608) 555-0199 tonight."
    )
    report = run_rules(
        format="caption",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    ids = {v.rule_id for v in report.violations}
    assert "cta:multiple" in ids


def test_suggestions_are_populated(
    sample_brand_yaml, sample_service_area_yaml, rules_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = "Whether you're searching, journey into excellence."
    report = run_rules(
        format="caption",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert not report.passed
    assert report.suggestions, "should have at least one critique line"
    assert all(isinstance(s, str) and s for s in report.suggestions)
