"""Tests for engine.brief.write_brief_for."""
from __future__ import annotations

import json
from pathlib import Path

import pytest

from engine.brief import write_brief_for
from engine.config import load_brand, load_service_area


def _ctx(sample_brand_yaml, sample_service_area_yaml) -> dict:
    return {
        "brand": load_brand(sample_brand_yaml),
        "service_area": load_service_area(sample_service_area_yaml),
    }


def test_video_script_brief_has_expected_fields(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    video_body = (
        "HOOK (0:00-0:02): Spring snapped loud bang\n\n"
        "PROBLEM (0:02-0:08): Door stuck. Cable dangling. 200 lbs of tension.\n\n"
        "AUTHORITY (0:08-0:15): 13 years in Madison. Thousands of springs.\n\n"
        "CTA (0:15-0:20): Call (608) 555-0199.\n\n"
        "SHOT LIST:\n"
        "- 0:00-0:02: close-up of snapped spring\n"
        "- 0:02-0:08: tech pointing at cable\n"
        "- 0:08-0:15: truck with Twins branding\n"
        "- 0:15-0:20: phone number card\n"
    )
    path = write_brief_for(
        brief_dir=tmp_path / "briefs",
        cluster_name="broken_spring",
        source_query_id=42,
        content_format="video_script",
        body=video_body,
        town_display="Madison",
        ctx=_ctx(sample_brand_yaml, sample_service_area_yaml),
    )
    assert path is not None
    data = json.loads(path.read_text())
    assert data["source_cluster"] == "broken_spring"
    assert data["source_query_id"] == 42
    assert data["purpose"] == "video_script"
    assert data["preferred_template"] == "emergency_repair"
    assert data["template_options"]["issue_type"] == "broken_spring"
    assert data["aspect_ratio"] == "9:16"
    assert data["duration_sec"] == 20
    assert data["brand_enforcement"]["phone_number"] == "(608) 555-0199"
    assert "Spring snapped loud bang" in data["on_screen_text"][0]
    assert len(data["shot_list"]) == 4


def test_gbp_brief_has_landscape_aspect(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    body = "In Madison, your garage door spring snapped? We fix same-day for $250-400. Call (608) 555-0199."
    path = write_brief_for(
        brief_dir=tmp_path / "briefs",
        cluster_name="broken_spring",
        source_query_id=1,
        content_format="gbp_post",
        body=body,
        town_display="Madison",
        ctx=_ctx(sample_brand_yaml, sample_service_area_yaml),
    )
    data = json.loads(path.read_text())
    assert data["aspect_ratio"] == "4:5"
    assert data["purpose"] == "gbp_post"


def test_unknown_cluster_falls_back_to_product_hero(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    body = "HOOK (0:00-0:02): test\n\nSHOT LIST:\n- 0:00-0:02: x"
    path = write_brief_for(
        brief_dir=tmp_path / "briefs",
        cluster_name="some_unknown_cluster",
        source_query_id=1,
        content_format="video_script",
        body=body,
        town_display="Madison",
        ctx=_ctx(sample_brand_yaml, sample_service_area_yaml),
    )
    data = json.loads(path.read_text())
    assert data["preferred_template"] == "product_hero"


def test_brief_filename_is_unique(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml)
    body = "HOOK (0:00-0:02): test\n\nSHOT LIST:\n- 0:00-0:02: x"
    path1 = write_brief_for(
        brief_dir=tmp_path / "briefs",
        cluster_name="broken_spring",
        source_query_id=1,
        content_format="video_script",
        body=body,
        town_display="Madison",
        ctx=ctx,
    )
    path2 = write_brief_for(
        brief_dir=tmp_path / "briefs",
        cluster_name="broken_spring",
        source_query_id=1,
        content_format="video_script",
        body=body,
        town_display="Madison",
        ctx=ctx,
    )
    assert path1 != path2
