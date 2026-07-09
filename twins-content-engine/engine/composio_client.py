"""Thin subprocess wrapper around the authenticated Composio CLI."""
from __future__ import annotations

import json
import shutil
import subprocess
from pathlib import Path

_COMPOSIO = shutil.which("composio") or str(Path.home() / ".composio" / "composio")


def run_tool(slug: str, payload: dict) -> dict:
    proc = subprocess.run(
        [_COMPOSIO, "execute", slug, "-d", json.dumps(payload)],
        capture_output=True, text=True, timeout=120,
    )
    if proc.returncode != 0:
        raise RuntimeError(f"composio execute {slug} failed: {proc.stderr[:500]}")
    return json.loads(proc.stdout)
