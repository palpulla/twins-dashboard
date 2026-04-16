"""Tests for engine.db."""
from __future__ import annotations

from pathlib import Path

import pytest

from engine.db import (
    init_db,
    get_conn,
    insert_cluster,
    insert_query,
    insert_generated_content,
    get_cluster_by_name,
    get_queries_for_cluster,
    update_content_status,
    list_pending_content,
    list_clusters,
)


def test_init_db_creates_tables(tmp_db_path: Path):
    init_db(tmp_db_path)
    with get_conn(tmp_db_path) as c:
        tables = {
            r["name"]
            for r in c.execute("SELECT name FROM sqlite_master WHERE type='table'").fetchall()
        }
    assert {"clusters", "queries", "generated_content"}.issubset(tables)


def test_insert_cluster_and_query(tmp_db_path: Path):
    init_db(tmp_db_path)
    cluster_id = insert_cluster(
        tmp_db_path,
        name="broken_spring",
        pillar="emergency_repairs",
        service_type="spring_replacement",
        funnel_stage="high_intent_problem_aware",
        priority_score=8,
        notes=None,
    )
    assert cluster_id > 0
    q_id = insert_query(
        tmp_db_path,
        cluster_id=cluster_id,
        query_text="garage door won't open loud bang",
        phrasing_type="symptom",
        geo_modifier=None,
        source="raw_seed",
        priority_score=7,
        notes=None,
    )
    assert q_id > 0
    queries = get_queries_for_cluster(tmp_db_path, cluster_id)
    assert len(queries) == 1
    assert queries[0]["query_text"] == "garage door won't open loud bang"


def test_insert_cluster_duplicate_name_raises(tmp_db_path: Path):
    init_db(tmp_db_path)
    insert_cluster(
        tmp_db_path,
        name="broken_spring",
        pillar="emergency_repairs",
        service_type=None,
        funnel_stage=None,
        priority_score=5,
        notes=None,
    )
    with pytest.raises(Exception):
        insert_cluster(
            tmp_db_path,
            name="broken_spring",
            pillar="emergency_repairs",
            service_type=None,
            funnel_stage=None,
            priority_score=5,
            notes=None,
        )


def test_get_cluster_by_name(tmp_db_path: Path):
    init_db(tmp_db_path)
    insert_cluster(
        tmp_db_path,
        name="broken_spring",
        pillar="emergency_repairs",
        service_type=None,
        funnel_stage=None,
        priority_score=5,
        notes=None,
    )
    row = get_cluster_by_name(tmp_db_path, "broken_spring")
    assert row is not None
    assert row["pillar"] == "emergency_repairs"
    assert get_cluster_by_name(tmp_db_path, "nonexistent") is None


def test_insert_generated_content_and_status_update(tmp_db_path: Path):
    init_db(tmp_db_path)
    c_id = insert_cluster(
        tmp_db_path, name="x", pillar="emergency_repairs",
        service_type=None, funnel_stage=None, priority_score=5, notes=None,
    )
    q_id = insert_query(
        tmp_db_path, cluster_id=c_id, query_text="q", phrasing_type="raw_seed",
        geo_modifier=None, source="raw_seed", priority_score=5, notes=None,
    )
    gc_id = insert_generated_content(
        tmp_db_path,
        cluster_id=c_id,
        source_query_id=q_id,
        format="caption",
        content_path="pending/foo.md",
        brief_path=None,
        status="pending",
        model_used="claude-sonnet-4-6",
        notes=None,
    )
    assert gc_id > 0
    pending = list_pending_content(tmp_db_path)
    assert len(pending) == 1
    update_content_status(tmp_db_path, gc_id, "approved")
    pending = list_pending_content(tmp_db_path)
    assert len(pending) == 0


def test_list_clusters(tmp_db_path: Path):
    init_db(tmp_db_path)
    insert_cluster(tmp_db_path, name="a", pillar="emergency_repairs",
                   service_type=None, funnel_stage=None, priority_score=5, notes=None)
    insert_cluster(tmp_db_path, name="b", pillar="pricing_transparency",
                   service_type=None, funnel_stage=None, priority_score=5, notes=None)
    names = {r["name"] for r in list_clusters(tmp_db_path)}
    assert names == {"a", "b"}


def test_cascade_delete(tmp_db_path: Path):
    init_db(tmp_db_path)
    c_id = insert_cluster(tmp_db_path, name="x", pillar="emergency_repairs",
                          service_type=None, funnel_stage=None, priority_score=5, notes=None)
    insert_query(tmp_db_path, cluster_id=c_id, query_text="q", phrasing_type="raw_seed",
                 geo_modifier=None, source="raw_seed", priority_score=5, notes=None)
    with get_conn(tmp_db_path) as c:
        c.execute("DELETE FROM clusters WHERE id = ?", (c_id,))
    assert get_queries_for_cluster(tmp_db_path, c_id) == []
