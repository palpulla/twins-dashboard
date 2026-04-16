"""Tests for engine.config."""
from __future__ import annotations

from pathlib import Path

import pytest

from engine.config import (
    BrandConfig,
    ServiceAreaConfig,
    PillarsConfig,
    RulesConfig,
    load_brand,
    load_service_area,
    load_pillars,
    load_rules,
)


def test_load_brand(sample_brand_yaml: Path):
    cfg = load_brand(sample_brand_yaml)
    assert isinstance(cfg, BrandConfig)
    assert cfg.business_name == "Twins Garage Doors"
    assert cfg.phone == "(608) 555-0199"
    assert cfg.primary_service_area == "madison_wi"
    assert cfg.allow_emojis is False


def test_load_brand_missing_required_field(tmp_path: Path):
    p = tmp_path / "brand.yaml"
    p.write_text("business_name: X\n")
    with pytest.raises(ValueError, match="phone"):
        load_brand(p)


def test_load_service_area(sample_service_area_yaml: Path):
    cfg = load_service_area(sample_service_area_yaml)
    assert cfg.primary == "madison_wi"
    assert "middleton_wi" in cfg.towns
    assert cfg.town_display_names["middleton_wi"] == "Middleton"


def test_load_service_area_display_name_missing(tmp_path: Path):
    p = tmp_path / "service_area.yaml"
    p.write_text(
        "primary: madison_wi\n"
        "towns: [madison_wi, middleton_wi]\n"
        "town_display_names:\n"
        "  madison_wi: Madison\n"
    )
    with pytest.raises(ValueError, match="display name missing for middleton_wi"):
        load_service_area(p)


def test_load_pillars(tmp_path: Path):
    p = tmp_path / "pillars.yaml"
    p.write_text(
        "pillars:\n"
        "  emergency_repairs:\n"
        "    description: desc\n"
        "    funnel_stage: high_intent_problem_aware\n"
        "    seed_patterns: ['a', 'b']\n"
        "    format_bias: [video_script]\n"
        "    priority_weight: 1.5\n"
    )
    cfg = load_pillars(p)
    assert "emergency_repairs" in cfg.pillars
    assert cfg.pillars["emergency_repairs"].priority_weight == 1.5


def test_load_pillars_bad_format_bias(tmp_path: Path):
    p = tmp_path / "pillars.yaml"
    p.write_text(
        "pillars:\n"
        "  foo:\n"
        "    description: d\n"
        "    funnel_stage: x\n"
        "    seed_patterns: []\n"
        "    format_bias: [not_a_format]\n"
        "    priority_weight: 1.0\n"
    )
    with pytest.raises(ValueError, match="invalid format_bias value"):
        load_pillars(p)


def test_load_rules(tmp_path: Path):
    p = tmp_path / "rules.yaml"
    p.write_text(
        "voice:\n"
        "  persona: p\n"
        "  structure: s\n"
        "  reading_level_max: 8\n"
        "hook_requirements: {}\n"
        "anti_ai_spam_blacklist:\n"
        "  phrases: ['foo']\n"
        "  structural: []\n"
        "specificity_requirements:\n"
        "  must_include_one_of: []\n"
        "  must_avoid_phrases: []\n"
        "cta_rules:\n"
        "  one_per_piece: true\n"
        "  funnel_stage_mapping: {}\n"
        "local_embedding:\n"
        "  mention_frequency: {}\n"
        "  placement_rules:\n"
        "    no_consecutive_sentences: true\n"
        "    require_mention_in_first_pct: 30\n"
        "    prefer_specific_town_over_region: true\n"
    )
    cfg = load_rules(p)
    assert cfg.voice.reading_level_max == 8
    assert "foo" in cfg.anti_ai_spam_blacklist.phrases
