"""Tests for engine.review."""
from __future__ import annotations

from pathlib import Path

from engine.db import (
    init_db,
    insert_cluster,
    insert_generated_content,
    insert_query,
    list_pending_content,
)
from engine.review import ApprovalResult, approve_all_pending, approve_by_id


def _setup_pending_content(db: Path, pending_dir: Path, count: int = 2) -> list[int]:
    init_db(db)
    cid = insert_cluster(
        db, name="c", pillar="emergency_repairs",
        service_type=None, funnel_stage=None, priority_score=5, notes=None,
    )
    qid = insert_query(
        db, cluster_id=cid, query_text="q", phrasing_type="raw_seed",
        geo_modifier=None, source="raw_seed", priority_score=5, notes=None,
    )
    ids = []
    pending_dir.mkdir(parents=True, exist_ok=True)
    for i in range(count):
        p = pending_dir / f"file_{i}.md"
        p.write_text(f"body {i}")
        gid = insert_generated_content(
            db, cluster_id=cid, source_query_id=qid,
            format="caption", content_path=str(p), brief_path=None,
            status="pending", model_used="x", notes=None,
        )
        ids.append(gid)
    return ids


def test_approve_all_moves_files_and_updates_status(tmp_path):
    db = tmp_path / "t.db"
    pending = tmp_path / "pending"
    approved = tmp_path / "approved"
    ids = _setup_pending_content(db, pending, count=2)
    result = approve_all_pending(db_path=db, pending_dir=pending, approved_dir=approved)
    assert isinstance(result, ApprovalResult)
    assert result.approved_count == 2
    assert not any(pending.iterdir())
    approved_files = list(approved.iterdir())
    assert len(approved_files) == 2
    assert list_pending_content(db) == []


def test_approve_all_skips_rejected_files(tmp_path):
    db = tmp_path / "t.db"
    pending = tmp_path / "pending"
    approved = tmp_path / "approved"
    ids = _setup_pending_content(db, pending, count=1)
    (pending / "_REJECTED_bad.md").write_text("no good")
    result = approve_all_pending(db_path=db, pending_dir=pending, approved_dir=approved)
    assert result.approved_count == 1
    assert (pending / "_REJECTED_bad.md").exists()


def test_approve_by_id(tmp_path):
    db = tmp_path / "t.db"
    pending = tmp_path / "pending"
    approved = tmp_path / "approved"
    ids = _setup_pending_content(db, pending, count=2)
    approve_by_id(db_path=db, content_id=ids[0], pending_dir=pending, approved_dir=approved)
    remaining = list_pending_content(db)
    assert len(remaining) == 1
    assert remaining[0]["id"] == ids[1]
