"""Tests for engine/report_builder.py"""
from datetime import date

from engine.db import connect, create_run, init_schema, insert_commission, insert_job, insert_job_part
from engine.report_builder import build_weekly_report


def _seed(conn):
    rid = create_run(
        conn,
        week_start=date(2026, 4, 13),
        week_end=date(2026, 4, 19),
        hcp_cache_dir="data/hcp_cache/r1",
        price_sheet_path="prices.xlsx",
        price_sheet_sha256="a" * 64,
    )

    j1 = insert_job(
        conn, run_id=rid, hcp_id="job_14404", hcp_job_number="14404",
        job_date=date(2026, 4, 14), amount=1687.00, tip=0.0,
        raw_techs="Charles Rue, Maurice", owner_tech="Maurice",
    )
    insert_job_part(conn, job_id=j1, part_name=".243 #2", quantity=2, unit_price=45.76)
    insert_commission(conn, job_id=j1, tech_name="Maurice", kind="primary",
                      basis=1595.48, commission_pct=0.20, commission_amt=319.10,
                      bonus_amt=0.0, override_amt=0.0, tip_amt=0.0)
    insert_commission(conn, job_id=j1, tech_name="Charles Rue", kind="override",
                      basis=1595.48, commission_pct=0.02, commission_amt=0.0,
                      bonus_amt=0.0, override_amt=31.91, tip_amt=0.0)

    j2 = insert_job(
        conn, run_id=rid, hcp_id="job_14371", hcp_job_number="14371",
        job_date=date(2026, 4, 13), amount=625.00, tip=0.0,
        raw_techs="Charles Rue", owner_tech="Charles Rue",
    )
    insert_job_part(conn, job_id=j2, part_name="Drum", quantity=2, unit_price=6.86)
    insert_commission(conn, job_id=j2, tech_name="Charles Rue", kind="primary",
                      basis=611.28, commission_pct=0.20, commission_amt=122.26,
                      bonus_amt=40.0, override_amt=0.0, tip_amt=0.0)
    return rid


def test_build_weekly_report_aggregates_per_tech(tmp_db):
    conn = connect(tmp_db)
    init_schema(conn)
    rid = _seed(conn)

    report = build_weekly_report(
        conn,
        run_id=rid,
        run_timestamp="2026-04-20T10:30:00",
        price_sheet_sha8="deadbeef",
        ticket_url_template="https://pro.housecallpro.com/app/jobs/{hcp_id}",
    )

    tech_names = [r.tech for r in report.summary]
    assert "Charles Rue" in tech_names
    assert "Maurice" in tech_names

    charles = next(r for r in report.summary if r.tech == "Charles Rue")
    assert charles.jobs == 1
    assert charles.gross_revenue == 625.00
    assert charles.commission == 122.26
    assert charles.bonuses == 40.00
    assert charles.overrides == 31.91
    assert charles.final_pay == 122.26 + 40.00 + 31.91 + 0.0

    maurice = next(r for r in report.summary if r.tech == "Maurice")
    assert maurice.jobs == 1
    assert maurice.commission == 319.10
    assert maurice.overrides == 0.0
    assert maurice.final_pay == 319.10

    assert len(report.jobs) == 2
    assert len(report.parts) == 2
