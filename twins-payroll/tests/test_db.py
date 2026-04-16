"""Tests for engine/db.py"""
from datetime import date

import pytest

from engine.db import connect, create_run, get_run_by_week, init_schema, insert_job


def test_init_schema_creates_tables(tmp_db):
    conn = connect(tmp_db)
    init_schema(conn)
    rows = conn.execute(
        "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"
    ).fetchall()
    names = {r["name"] for r in rows}
    assert {"runs", "jobs", "job_parts", "commissions"}.issubset(names)


def test_create_run_roundtrip(tmp_db):
    conn = connect(tmp_db)
    init_schema(conn)
    run_id = create_run(
        conn,
        week_start=date(2026, 4, 13),
        week_end=date(2026, 4, 19),
        hcp_cache_dir="data/hcp_cache/2026-04-13_abc",
        price_sheet_path="../prices/parts.xlsx",
        price_sheet_sha256="deadbeef" * 8,
    )
    assert run_id > 0
    row = get_run_by_week(conn, date(2026, 4, 13))
    assert row is not None
    assert row["status"] == "in_progress"
    assert row["price_sheet_path"] == "../prices/parts.xlsx"


def test_insert_job_unique_constraint(tmp_db):
    conn = connect(tmp_db)
    init_schema(conn)
    run_id = create_run(
        conn,
        week_start=date(2026, 4, 13),
        week_end=date(2026, 4, 19),
        hcp_cache_dir="data/hcp_cache/r1",
        price_sheet_path="p",
        price_sheet_sha256="h",
    )
    insert_job(conn, run_id=run_id, hcp_id="job_1", hcp_job_number="14404",
               job_date=date(2026, 4, 14), amount=100.0)
    with pytest.raises(Exception):
        insert_job(conn, run_id=run_id, hcp_id="job_1", hcp_job_number="14404-dup",
                   job_date=date(2026, 4, 14), amount=100.0)


def test_update_job_skip_reason(tmp_db):
    from engine.db import update_job_skip_reason
    conn = connect(tmp_db)
    init_schema(conn)
    run_id = create_run(
        conn,
        week_start=date(2026, 4, 13),
        week_end=date(2026, 4, 19),
        hcp_cache_dir="data/hcp_cache/r1",
        price_sheet_path="p",
        price_sheet_sha256="h",
    )
    jid = insert_job(conn, run_id=run_id, hcp_id="job_1", hcp_job_number="14404",
                     job_date=date(2026, 4, 14), amount=100.0)
    update_job_skip_reason(conn, job_id=jid, skip_reason="user_skip")
    row = conn.execute("SELECT skip_reason FROM jobs WHERE id = ?", (jid,)).fetchone()
    assert row["skip_reason"] == "user_skip"
