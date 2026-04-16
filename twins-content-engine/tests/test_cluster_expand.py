"""Tests for engine.cluster.expand_seeds_to_clusters."""
from __future__ import annotations

import json
from pathlib import Path

import pytest

from engine.claude_client import FakeClaudeClient
from engine.cluster import expand_seeds_to_clusters
from engine.db import (
    get_queries_for_cluster,
    init_db,
    insert_cluster,
    insert_query,
    list_clusters,
)


@pytest.fixture
def seeded_db(tmp_db_path: Path) -> Path:
    """DB with two _unclustered_* placeholder rows (simulates ingest_seeds output)."""
    init_db(tmp_db_path)
    for i, (q, hint) in enumerate(
        [
            ("garage door broken spring loud bang", "emergency_repairs"),
            ("how much for new garage door madison", "pricing_transparency"),
        ],
        start=1,
    ):
        cid = insert_cluster(
            tmp_db_path,
            name=f"_unclustered_{i}",
            pillar=hint,
            service_type=None,
            funnel_stage=None,
            priority_score=5,
            notes=None,
        )
        insert_query(
            tmp_db_path,
            cluster_id=cid,
            query_text=q,
            phrasing_type="raw_seed",
            geo_modifier=None,
            source="raw_seed",
            priority_score=5,
            notes=None,
        )
    return tmp_db_path


def test_expand_single_seed_creates_cluster_and_variants(seeded_db: Path):
    fake_response = json.dumps(
        {
            "cluster_name": "broken_spring",
            "service_type": "spring_replacement",
            "funnel_stage": "high_intent_problem_aware",
            "priority_score": 9,
            "variants": [
                {"query_text": "spring just snapped on my garage door",
                 "phrasing_type": "emergency", "geo_modifier": None},
                {"query_text": "garage door spring replacement cost madison wi",
                 "phrasing_type": "price_anxiety", "geo_modifier": "madison_wi"},
                {"query_text": "is a broken garage door spring dangerous",
                 "phrasing_type": "question", "geo_modifier": None},
                {"query_text": "door fell down loud bang can't open it",
                 "phrasing_type": "symptom", "geo_modifier": None},
            ],
        }
    )
    fake_client = FakeClaudeClient(responses=[fake_response, json.dumps({
        "cluster_name": "new_door_cost",
        "service_type": "full_replacement",
        "funnel_stage": "mid_intent_price_checking",
        "priority_score": 7,
        "variants": [
            {"query_text": "average cost new garage door installation",
             "phrasing_type": "price_anxiety", "geo_modifier": None},
        ],
    })])

    expand_seeds_to_clusters(
        db_path=seeded_db,
        client=fake_client,
        towns=["madison_wi", "middleton_wi"],
    )

    clusters = {c["name"]: c for c in list_clusters(seeded_db)}
    assert "broken_spring" in clusters
    assert "new_door_cost" in clusters
    assert "_unclustered_1" not in clusters  # placeholder was renamed

    spring = clusters["broken_spring"]
    queries = get_queries_for_cluster(seeded_db, spring["id"])
    # 1 original raw_seed + 4 expansions
    assert len(queries) == 5
    phrasing_types = {q["phrasing_type"] for q in queries}
    assert phrasing_types == {"raw_seed", "emergency", "price_anxiety", "question", "symptom"}


def test_expand_merges_duplicates(seeded_db: Path):
    """If Claude emits the same cluster_name for two seeds, they merge."""
    same_cluster = json.dumps({
        "cluster_name": "broken_spring",
        "service_type": "spring_replacement",
        "funnel_stage": "high_intent_problem_aware",
        "priority_score": 9,
        "variants": [
            {"query_text": "var a", "phrasing_type": "emergency", "geo_modifier": None},
        ],
    })
    fake = FakeClaudeClient(responses=[same_cluster, same_cluster])
    expand_seeds_to_clusters(db_path=seeded_db, client=fake, towns=["madison_wi"])
    clusters = list_clusters(seeded_db)
    cluster_names = [c["name"] for c in clusters]
    # two seeds, same cluster_name — should result in a single real cluster
    assert cluster_names.count("broken_spring") == 1

    # Both original raw_seed queries must have ended up on the merged cluster
    merged = next(c for c in clusters if c["name"] == "broken_spring")
    queries = get_queries_for_cluster(seeded_db, merged["id"])
    raw_seed_queries = [q for q in queries if q["phrasing_type"] == "raw_seed"]
    assert len(raw_seed_queries) == 2


def test_expand_handles_malformed_response(seeded_db: Path):
    """Malformed JSON: skip the seed, continue with the rest."""
    fake = FakeClaudeClient(
        responses=[
            "not json",
            json.dumps({
                "cluster_name": "new_door_cost",
                "service_type": None,
                "funnel_stage": "mid_intent_price_checking",
                "priority_score": 7,
                "variants": [],
            }),
        ]
    )
    expand_seeds_to_clusters(db_path=seeded_db, client=fake, towns=["madison_wi"])
    names = {c["name"] for c in list_clusters(seeded_db)}
    assert "new_door_cost" in names
    # Malformed seed keeps its placeholder
    assert any(n.startswith("_unclustered_") for n in names)
