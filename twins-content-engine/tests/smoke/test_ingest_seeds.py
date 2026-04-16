"""Smoke test: ingest_seeds.py end-to-end against a temp DB."""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

from engine.db import list_clusters


def test_ingest_seeds_cli(tmp_path: Path):
    seed = tmp_path / "seed.md"
    seed.write_text("garage door stuck\nspring broke | pillar=emergency_repairs\n")
    db = tmp_path / "test.db"

    script = Path(__file__).resolve().parents[2] / "scripts" / "ingest_seeds.py"
    result = subprocess.run(
        [sys.executable, str(script), str(seed), "--db", str(db)],
        capture_output=True,
        text=True,
    )
    assert result.returncode == 0, result.stderr
    assert "Ingested 2" in result.stdout
    clusters = list_clusters(db)
    assert len(clusters) == 2
    pillars = {c["pillar"] for c in clusters}
    assert "emergency_repairs" in pillars
    assert "unclustered" in pillars
