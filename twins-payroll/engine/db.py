"""SQLite layer for twins-payroll.

Thin CRUD helpers + schema init. All functions take a sqlite3 Connection so
tests can use in-memory / tmp DBs.
"""
from __future__ import annotations

import sqlite3
from datetime import date
from pathlib import Path
from typing import Any, Optional

SCHEMA_PATH = Path(__file__).parent / "schema.sql"


def connect(db_path: Path) -> sqlite3.Connection:
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


def init_schema(conn: sqlite3.Connection) -> None:
    sql = SCHEMA_PATH.read_text()
    conn.executescript(sql)
    conn.commit()


def create_run(
    conn: sqlite3.Connection,
    *,
    week_start: date,
    week_end: date,
    hcp_cache_dir: str,
    price_sheet_path: str,
    price_sheet_sha256: str,
    notes: Optional[str] = None,
) -> int:
    cur = conn.execute(
        """
        INSERT INTO runs (week_start, week_end, hcp_cache_dir,
                          price_sheet_path, price_sheet_sha256, status, notes)
        VALUES (?, ?, ?, ?, ?, 'in_progress', ?)
        """,
        (week_start.isoformat(), week_end.isoformat(), hcp_cache_dir,
         price_sheet_path, price_sheet_sha256, notes),
    )
    conn.commit()
    return int(cur.lastrowid)


def get_run_by_week(
    conn: sqlite3.Connection, week_start: date
) -> Optional[sqlite3.Row]:
    row = conn.execute(
        """
        SELECT * FROM runs
        WHERE week_start = ? AND status IN ('in_progress', 'final')
        ORDER BY id DESC LIMIT 1
        """,
        (week_start.isoformat(),),
    ).fetchone()
    return row


def set_run_status(conn: sqlite3.Connection, run_id: int, status: str) -> None:
    assert status in ("in_progress", "final", "superseded")
    conn.execute("UPDATE runs SET status = ? WHERE id = ?", (status, run_id))
    conn.commit()


def insert_job(
    conn: sqlite3.Connection,
    *,
    run_id: int,
    hcp_id: str,
    hcp_job_number: str,
    job_date: date,
    amount: float,
    customer_display: str = "",
    description: str = "",
    line_items_text: str = "",
    notes_text: str = "",
    tip: float = 0.0,
    subtotal: float = 0.0,
    labor: float = 0.0,
    materials_charged: float = 0.0,
    cc_fee: float = 0.0,
    discount: float = 0.0,
    raw_techs: str = "",
    owner_tech: Optional[str] = None,
    skip_reason: Optional[str] = None,
    notes: Optional[str] = None,
) -> int:
    cur = conn.execute(
        """
        INSERT INTO jobs (run_id, hcp_id, hcp_job_number, job_date, customer_display,
                          description, line_items_text, notes_text, amount, tip,
                          subtotal, labor, materials_charged, cc_fee, discount,
                          raw_techs, owner_tech, skip_reason, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        """,
        (run_id, hcp_id, hcp_job_number, job_date.isoformat(), customer_display,
         description, line_items_text, notes_text, amount, tip,
         subtotal, labor, materials_charged, cc_fee, discount,
         raw_techs, owner_tech, skip_reason, notes),
    )
    conn.commit()
    return int(cur.lastrowid)


def insert_job_part(
    conn: sqlite3.Connection,
    *,
    job_id: int,
    part_name: str,
    quantity: int,
    unit_price: float,
    source: str = "manual",
) -> int:
    total = round(quantity * unit_price, 2)
    cur = conn.execute(
        """
        INSERT INTO job_parts (job_id, part_name, quantity, unit_price, total, source)
        VALUES (?, ?, ?, ?, ?, ?)
        """,
        (job_id, part_name, quantity, unit_price, total, source),
    )
    conn.commit()
    return int(cur.lastrowid)


def insert_commission(
    conn: sqlite3.Connection,
    *,
    job_id: int,
    tech_name: str,
    kind: str,
    basis: float,
    commission_pct: float,
    commission_amt: float,
    bonus_amt: float,
    override_amt: float,
    tip_amt: float,
) -> int:
    total = round(commission_amt + bonus_amt + override_amt + tip_amt, 2)
    cur = conn.execute(
        """
        INSERT INTO commissions (job_id, tech_name, kind, basis, commission_pct,
                                 commission_amt, bonus_amt, override_amt, tip_amt, total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """,
        (job_id, tech_name, kind, basis, commission_pct,
         commission_amt, bonus_amt, override_amt, tip_amt, total),
    )
    conn.commit()
    return int(cur.lastrowid)


def update_job_skip_reason(
    conn: sqlite3.Connection, *, job_id: int, skip_reason: str
) -> None:
    conn.execute(
        "UPDATE jobs SET skip_reason = ? WHERE id = ?",
        (skip_reason, job_id),
    )
    conn.commit()


def fetch_jobs_for_run(conn: sqlite3.Connection, run_id: int) -> list[sqlite3.Row]:
    return list(conn.execute(
        "SELECT * FROM jobs WHERE run_id = ? ORDER BY job_date, hcp_job_number",
        (run_id,),
    ).fetchall())


def fetch_parts_for_job(conn: sqlite3.Connection, job_id: int) -> list[sqlite3.Row]:
    return list(conn.execute(
        "SELECT * FROM job_parts WHERE job_id = ? ORDER BY id",
        (job_id,),
    ).fetchall())


def fetch_commissions_for_run(
    conn: sqlite3.Connection, run_id: int
) -> list[sqlite3.Row]:
    return list(conn.execute(
        """
        SELECT c.*, j.hcp_job_number, j.job_date
        FROM commissions c JOIN jobs j ON c.job_id = j.id
        WHERE j.run_id = ?
        ORDER BY j.job_date, j.hcp_job_number, c.kind
        """,
        (run_id,),
    ).fetchall())
