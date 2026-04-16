"""Review workflow: pending -> approved file moves + DB status updates."""
from __future__ import annotations

import shutil
from dataclasses import dataclass, field
from pathlib import Path

from engine.db import (
    get_conn,
    list_pending_content,
    update_content_status,
)


@dataclass
class ApprovalResult:
    approved_count: int = 0
    approved_ids: list[int] = field(default_factory=list)
    skipped_rejected: int = 0


def approve_all_pending(
    *,
    db_path: Path,
    pending_dir: Path,
    approved_dir: Path,
) -> ApprovalResult:
    """Promote all `pending` rows (skip rows whose files are marked _REJECTED_)."""
    approved_dir.mkdir(parents=True, exist_ok=True)
    result = ApprovalResult()
    for row in list_pending_content(db_path):
        content_path = Path(row["content_path"])
        if not content_path.exists():
            continue
        if content_path.name.startswith("_REJECTED_"):
            result.skipped_rejected += 1
            continue
        new_path = approved_dir / content_path.name
        shutil.move(str(content_path), str(new_path))
        _update_content_path(db_path, int(row["id"]), str(new_path))
        update_content_status(db_path, int(row["id"]), "approved")
        result.approved_count += 1
        result.approved_ids.append(int(row["id"]))
    return result


def approve_by_id(
    *,
    db_path: Path,
    content_id: int,
    pending_dir: Path,
    approved_dir: Path,
) -> None:
    approved_dir.mkdir(parents=True, exist_ok=True)
    with get_conn(db_path) as c:
        row = c.execute(
            "SELECT * FROM generated_content WHERE id = ?", (content_id,)
        ).fetchone()
    if row is None:
        raise ValueError(f"No generated_content with id={content_id}")
    content_path = Path(row["content_path"])
    new_path = approved_dir / content_path.name
    shutil.move(str(content_path), str(new_path))
    _update_content_path(db_path, content_id, str(new_path))
    update_content_status(db_path, content_id, "approved")


def _update_content_path(db_path: Path, content_id: int, new_path: str) -> None:
    with get_conn(db_path) as c:
        c.execute(
            "UPDATE generated_content SET content_path = ? WHERE id = ?",
            (new_path, content_id),
        )
