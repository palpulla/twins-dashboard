"""End-to-end integration test against the real Anthropic API.

Gated behind INTEGRATION=1 env var. Requires ANTHROPIC_API_KEY.
Run with: INTEGRATION=1 pytest -m integration -v
"""
from __future__ import annotations

import os
from pathlib import Path

import pytest

from engine.claude_client import ClaudeClient
from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.db import init_db, insert_cluster, insert_query
from engine.generator import generate_for_cluster


pytestmark = pytest.mark.integration


def _gated():
    if os.environ.get("INTEGRATION") != "1":
        pytest.skip("set INTEGRATION=1 to run the real-API integration test")
    if not os.environ.get("ANTHROPIC_API_KEY"):
        pytest.skip("ANTHROPIC_API_KEY not set")


def test_generate_for_cluster_real_api(tmp_path: Path):
    _gated()
    repo_root = Path(__file__).resolve().parents[1]
    cfg = repo_root / "config"
    ctx = {
        "brand": load_brand(cfg / "brand.yaml"),
        "service_area": load_service_area(cfg / "service_area.yaml"),
        "pillars": load_pillars(cfg / "pillars.yaml"),
        "rules": load_rules(cfg / "rules.yaml"),
    }

    db = tmp_path / "t.db"
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

    client = ClaudeClient(model="claude-sonnet-4-6")
    result = generate_for_cluster(
        db_path=db,
        cluster_name="broken_spring",
        client=client,
        ctx=ctx,
        output_dir=tmp_path / "pending",
        brief_dir=tmp_path / "briefs",
        model_name="claude-sonnet-4-6",
    )
    # At least some formats should be accepted — retries handle the rest.
    assert len(result.accepted) + len(result.rejected) == 5
    # Assert files exist for every returned piece
    for piece in result.accepted + result.rejected:
        assert Path(piece.content_path).exists()
