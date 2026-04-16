"""SQLite layer for the content engine."""
from __future__ import annotations

import sqlite3
from contextlib import contextmanager
from pathlib import Path
from typing import Any, Iterator, Optional

_SCHEMA_PATH = Path(__file__).parent / "schema.sql"

_VALID_STATUSES = {"pending", "approved", "rejected", "published"}


@contextmanager
def get_conn(db_path: Path) -> Iterator[sqlite3.Connection]:
    """Transactional SQLite connection with foreign keys enabled."""
    conn = sqlite3.connect(str(db_path))
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    conn.execute("PRAGMA journal_mode = WAL")
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def init_db(db_path: Path) -> None:
    """Create tables if they don't exist."""
    db_path.parent.mkdir(parents=True, exist_ok=True)
    schema = _SCHEMA_PATH.read_text()
    with get_conn(db_path) as c:
        c.executescript(schema)


def insert_cluster(
    db_path: Path,
    *,
    name: str,
    pillar: str,
    service_type: Optional[str],
    funnel_stage: Optional[str],
    priority_score: int,
    notes: Optional[str],
) -> int:
    with get_conn(db_path) as c:
        cur = c.execute(
            "INSERT INTO clusters "
            "(name, pillar, service_type, funnel_stage, priority_score, notes) "
            "VALUES (?, ?, ?, ?, ?, ?)",
            (name, pillar, service_type, funnel_stage, priority_score, notes),
        )
        return int(cur.lastrowid)


def insert_query(
    db_path: Path,
    *,
    cluster_id: int,
    query_text: str,
    phrasing_type: str,
    geo_modifier: Optional[str],
    source: str,
    priority_score: int,
    notes: Optional[str],
) -> int:
    with get_conn(db_path) as c:
        cur = c.execute(
            "INSERT INTO queries "
            "(cluster_id, query_text, phrasing_type, geo_modifier, source, priority_score, notes) "
            "VALUES (?, ?, ?, ?, ?, ?, ?)",
            (cluster_id, query_text, phrasing_type, geo_modifier, source, priority_score, notes),
        )
        return int(cur.lastrowid)


def insert_generated_content(
    db_path: Path,
    *,
    cluster_id: int,
    source_query_id: int,
    format: str,
    content_path: str,
    brief_path: Optional[str],
    status: str,
    model_used: str,
    notes: Optional[str],
) -> int:
    with get_conn(db_path) as c:
        cur = c.execute(
            "INSERT INTO generated_content "
            "(cluster_id, source_query_id, format, content_path, brief_path, status, model_used, notes) "
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            (cluster_id, source_query_id, format, content_path, brief_path, status, model_used, notes),
        )
        return int(cur.lastrowid)


def get_cluster_by_name(db_path: Path, name: str) -> Optional[sqlite3.Row]:
    with get_conn(db_path) as c:
        cur = c.execute("SELECT * FROM clusters WHERE name = ?", (name,))
        return cur.fetchone()


def get_queries_for_cluster(db_path: Path, cluster_id: int) -> list[sqlite3.Row]:
    with get_conn(db_path) as c:
        return list(c.execute(
            "SELECT * FROM queries WHERE cluster_id = ? ORDER BY priority_score DESC, id",
            (cluster_id,),
        ).fetchall())


def list_clusters(db_path: Path) -> list[sqlite3.Row]:
    with get_conn(db_path) as c:
        return list(c.execute("SELECT * FROM clusters ORDER BY name").fetchall())


def list_pending_content(db_path: Path) -> list[sqlite3.Row]:
    with get_conn(db_path) as c:
        return list(c.execute(
            "SELECT * FROM generated_content WHERE status = 'pending' ORDER BY generated_at"
        ).fetchall())


def update_content_status(db_path: Path, content_id: int, new_status: str) -> None:
    if new_status not in _VALID_STATUSES:
        raise ValueError(
            f"Invalid status {new_status!r}. Must be one of {sorted(_VALID_STATUSES)}."
        )
    with get_conn(db_path) as c:
        if new_status == "approved":
            c.execute(
                "UPDATE generated_content SET status = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?",
                (new_status, content_id),
            )
        else:
            c.execute(
                "UPDATE generated_content SET status = ? WHERE id = ?",
                (new_status, content_id),
            )


def get_cluster_last_used(db_path: Path, cluster_id: int) -> Optional[str]:
    """Return ISO timestamp of most recent content generation, or None."""
    with get_conn(db_path) as c:
        cur = c.execute(
            "SELECT MAX(generated_at) AS ts FROM generated_content WHERE cluster_id = ?",
            (cluster_id,),
        )
        row = cur.fetchone()
        return row["ts"] if row else None
