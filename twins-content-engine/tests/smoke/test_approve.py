"""Smoke test: approve.py end-to-end."""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

from engine.db import (
    init_db,
    insert_cluster,
    insert_generated_content,
    insert_query,
    list_pending_content,
)


def test_approve_all_passing_cli(tmp_path: Path):
    db = tmp_path / "t.db"
    pending = tmp_path / "pending"
    approved = tmp_path / "approved"
    pending.mkdir()
    init_db(db)
    cid = insert_cluster(db, name="c", pillar="emergency_repairs",
                        service_type=None, funnel_stage=None,
                        priority_score=5, notes=None)
    qid = insert_query(db, cluster_id=cid, query_text="q", phrasing_type="raw_seed",
                       geo_modifier=None, source="raw_seed",
                       priority_score=5, notes=None)
    f = pending / "a.md"
    f.write_text("body")
    insert_generated_content(
        db, cluster_id=cid, source_query_id=qid, format="caption",
        content_path=str(f), brief_path=None, status="pending",
        model_used="x", notes=None,
    )

    script = Path(__file__).resolve().parents[2] / "scripts" / "approve.py"
    result = subprocess.run(
        [sys.executable, str(script), "--all-passing",
         "--db", str(db), "--pending", str(pending), "--approved", str(approved)],
        capture_output=True, text=True,
    )
    assert result.returncode == 0, result.stderr
    assert "Approved 1" in result.stdout
    assert (approved / "a.md").exists()
    assert list_pending_content(db) == []
