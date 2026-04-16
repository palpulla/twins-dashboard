"""Tests for engine.local_embedding."""
from __future__ import annotations

from pathlib import Path

from engine.config import load_brand, load_service_area
from engine.local_embedding import (
    pick_town_for_piece,
    pick_neighborhood,
    format_phone_for_content,
    target_mention_count,
)


def test_pick_town_uses_query_geo_modifier_when_available(
    sample_brand_yaml, sample_service_area_yaml
):
    sa = load_service_area(sample_service_area_yaml)
    town_slug = pick_town_for_piece(
        query_geo_modifier="middleton_wi",
        cluster_seed=123,
        service_area=sa,
    )
    assert town_slug == "middleton_wi"


def test_pick_town_falls_back_to_deterministic_rotation(
    sample_brand_yaml, sample_service_area_yaml
):
    sa = load_service_area(sample_service_area_yaml)
    a = pick_town_for_piece(query_geo_modifier=None, cluster_seed=1, service_area=sa)
    b = pick_town_for_piece(query_geo_modifier=None, cluster_seed=1, service_area=sa)
    assert a == b
    assert a in sa.towns
    c = pick_town_for_piece(query_geo_modifier=None, cluster_seed=2, service_area=sa)
    assert c in sa.towns


def test_pick_neighborhood_deterministic(sample_brand_yaml, sample_service_area_yaml):
    sa = load_service_area(sample_service_area_yaml)
    sa.madison_neighborhoods = ["Maple Bluff", "University Heights", "Nakoma"]
    n1 = pick_neighborhood(cluster_seed=5, service_area=sa)
    n2 = pick_neighborhood(cluster_seed=5, service_area=sa)
    assert n1 == n2
    assert n1 in sa.madison_neighborhoods


def test_pick_neighborhood_returns_none_when_empty(sample_service_area_yaml):
    sa = load_service_area(sample_service_area_yaml)
    sa.madison_neighborhoods = []
    assert pick_neighborhood(cluster_seed=5, service_area=sa) is None


def test_format_phone_for_content_preserves_format(sample_brand_yaml):
    brand = load_brand(sample_brand_yaml)
    assert format_phone_for_content(brand) == "(608) 555-0199"


def test_target_mention_count_reads_rules():
    freq = {"caption": [1, 1], "gbp_post": [2, 3]}
    assert target_mention_count("caption", freq) == (1, 1)
    assert target_mention_count("gbp_post", freq) == (2, 3)
    assert target_mention_count("unknown_format", freq) == (0, 0)
