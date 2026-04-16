"""Tests for engine.harvest.parse_harvest_inbox and fold_into_db."""
from __future__ import annotations

import json
from pathlib import Path

from engine.claude_client import FakeClaudeClient
from engine.db import (
    get_queries_for_cluster,
    init_db,
    insert_cluster,
    list_clusters,
)
from engine.harvest import HarvestEntry, fold_into_db, parse_harvest_inbox


def test_parse_basic(tmp_path: Path):
    p = tmp_path / "inbox.md"
    p.write_text(
        "# 2026-04-10 notes\n"
        "garage door stuck halfway\n"
        "customer asked about opener brands | source=call\n"
        "\n"
        "another one\n"
    )
    entries = parse_harvest_inbox(p)
    assert len(entries) == 3
    assert entries[0].query_text == "garage door stuck halfway"
    assert entries[0].source == "harvest_inbox"
    assert entries[1].source == "call"


def test_fold_assigns_to_existing_cluster(tmp_path: Path):
    db = tmp_path / "t.db"
    init_db(db)
    insert_cluster(
        db, name="broken_spring", pillar="emergency_repairs",
        service_type=None, funnel_stage=None, priority_score=8, notes=None,
    )
    fake = FakeClaudeClient(responses=[json.dumps({"cluster_name": "broken_spring"})])
    entries = [HarvestEntry(query_text="spring snapped", source="harvest_inbox")]
    fold_into_db(db_path=db, entries=entries, client=fake)
    clusters = list_clusters(db)
    assert len(clusters) == 1
    bs = clusters[0]
    queries = get_queries_for_cluster(db, bs["id"])
    assert any(q["query_text"] == "spring snapped" for q in queries)


def test_fold_creates_new_cluster_when_none_matches(tmp_path: Path):
    db = tmp_path / "t.db"
    init_db(db)
    insert_cluster(
        db, name="broken_spring", pillar="emergency_repairs",
        service_type=None, funnel_stage=None, priority_score=8, notes=None,
    )
    fake = FakeClaudeClient(responses=[json.dumps({
        "cluster_name": "winter_weather_stuck",
        "pillar": "emergency_repairs",
        "funnel_stage": "high_intent_problem_aware",
    })])
    entries = [HarvestEntry(query_text="door frozen shut", source="harvest_inbox")]
    fold_into_db(db_path=db, entries=entries, client=fake)
    names = {c["name"] for c in list_clusters(db)}
    assert "winter_weather_stuck" in names
