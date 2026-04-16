"""Brief builder — full implementation in Task 11. Stub so generator.py imports."""
from __future__ import annotations

from pathlib import Path
from typing import Any, Optional


def write_brief_for(
    *,
    brief_dir: Path,
    cluster_name: str,
    source_query_id: int,
    content_format: str,
    body: str,
    town_display: str,
    ctx: dict[str, Any],
) -> Optional[Path]:
    brief_dir.mkdir(parents=True, exist_ok=True)
    # Minimal stub — Task 11 replaces with full brief construction.
    path = brief_dir / f"{cluster_name}_{content_format}.json"
    path.write_text("{}")
    return path
