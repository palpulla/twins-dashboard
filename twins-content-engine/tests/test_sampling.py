"""Tests for engine.sampling.pick_clusters_for_week."""
from __future__ import annotations

import datetime as dt
from pathlib import Path

import pytest

from engine.config import load_pillars
from engine.db import (
    init_db,
    insert_cluster,
    insert_generated_content,
    insert_query,
)
from engine.sampling import pick_clusters_for_week


@pytest.fixture
def pillars_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "pillars.yaml"
    p.write_text("""
pillars:
  emergency_repairs:
    description: d
    funnel_stage: high_intent_problem_aware
    seed_patterns: []
    format_bias: [video_script]
    priority_weight: 1.5
  pricing_transparency:
    description: d
    funnel_stage: mid_intent_price_checking
    seed_patterns: []
    format_bias: [faq]
    priority_weight: 1.3
  maintenance_prevention:
    description: d
    funnel_stage: low_intent_educational
    seed_patterns: []
    format_bias: [caption]
    priority_weight: 0.8
""")
    return p


def _seed_clusters(db: Path, specs: list[tuple[str, str]]) -> list[int]:
    ids = []
    for name, pillar in specs:
        cid = insert_cluster(
            db, name=name, pillar=pillar, service_type=None,
            funnel_stage=None, priority_score=5, notes=None,
        )
        qid = insert_query(
            db, cluster_id=cid, query_text=f"q_{name}", phrasing_type="raw_seed",
            geo_modifier=None, source="raw_seed", priority_score=5, notes=None,
        )
        ids.append(cid)
    return ids


def test_pick_clusters_respects_count(tmp_path, pillars_yaml):
    db = tmp_path / "t.db"
    init_db(db)
    _seed_clusters(db, [
        ("a", "emergency_repairs"),
        ("b", "pricing_transparency"),
        ("c", "maintenance_prevention"),
        ("d", "emergency_repairs"),
    ])
    pillars = load_pillars(pillars_yaml)
    picks = pick_clusters_for_week(db_path=db, count=3, pillars=pillars)
    assert len(picks) == 3
    names = {p.name for p in picks}
    assert names.issubset({"a", "b", "c", "d"})


def test_pick_clusters_enforces_max_two_per_pillar(tmp_path, pillars_yaml):
    db = tmp_path / "t.db"
    init_db(db)
    _seed_clusters(db, [
        ("a1", "emergency_repairs"),
        ("a2", "emergency_repairs"),
        ("a3", "emergency_repairs"),
        ("a4", "emergency_repairs"),
        ("b1", "pricing_transparency"),
        ("c1", "maintenance_prevention"),
    ])
    pillars = load_pillars(pillars_yaml)
    picks = pick_clusters_for_week(db_path=db, count=5, pillars=pillars)
    pillars_chosen = [p.pillar for p in picks]
    emergency_count = sum(1 for p in pillars_chosen if p == "emergency_repairs")
    assert emergency_count <= 2


def test_pick_clusters_oversamples_high_priority_pillar(tmp_path, pillars_yaml):
    db = tmp_path / "t.db"
    init_db(db)
    _seed_clusters(db, [
        ("a", "emergency_repairs"),
        ("b", "pricing_transparency"),
        ("c", "maintenance_prevention"),
    ])
    pillars = load_pillars(pillars_yaml)
    picks = pick_clusters_for_week(db_path=db, count=3, pillars=pillars)
    assert picks[0].pillar == "emergency_repairs"


def test_pick_clusters_avoids_recently_used(tmp_path, pillars_yaml):
    db = tmp_path / "t.db"
    init_db(db)
    ids = _seed_clusters(db, [
        ("a", "emergency_repairs"),
        ("b", "emergency_repairs"),
    ])
    # Mark cluster a as just-used. Must reference an existing query.
    insert_generated_content(
        db, cluster_id=ids[0], source_query_id=ids[0], format="caption",
        content_path="x.md", brief_path=None, status="approved",
        model_used="x", notes=None,
    )
    pillars = load_pillars(pillars_yaml)
    picks = pick_clusters_for_week(db_path=db, count=1, pillars=pillars)
    assert picks[0].name == "b"
