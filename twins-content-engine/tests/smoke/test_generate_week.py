"""Smoke test: generate_week.py --help."""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path


def test_generate_week_with_fake_client(tmp_path: Path, monkeypatch):
    script = Path(__file__).resolve().parents[2] / "scripts" / "generate_week.py"
    result = subprocess.run(
        [sys.executable, str(script), "--help"],
        capture_output=True, text=True,
    )
    assert result.returncode == 0
    assert "--count" in result.stdout
    assert "--model" in result.stdout
