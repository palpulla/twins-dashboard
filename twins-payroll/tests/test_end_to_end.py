"""End-to-end test: mocked HCP + scripted IO -> DB + Excel output."""
from __future__ import annotations
import json
from datetime import date, datetime
from pathlib import Path

import openpyxl
import pytest
import respx

from engine.commission import compute_commissions
from engine.config_loader import HCPConfig
from engine.db import connect, create_run, init_schema, insert_commission, insert_job, insert_job_part, set_run_status
from engine.excel_writer import write_weekly_report
from engine.hcp_client import HCPClient
from engine.hcp_sync import fetch_week_jobs
from engine.models import StepTierConfig, TechConfig, TechsConfig
from engine.ownership import resolve_owner
from engine.parts_prompt import ScriptedIO, collect_parts_for_job
from engine.price_sheet import PriceSheet, load_price_sheet
from engine.report_builder import build_weekly_report


@respx.mock
def test_full_pipeline(tmp_path, fixtures_dir):
    # --- HCP mocks ---
    jobs_list = json.loads((fixtures_dir / "hcp_jobs_list.json").read_text())
    job_a = json.loads((fixtures_dir / "hcp_job_detail.json").read_text())
    job_b = json.loads((fixtures_dir / "hcp_job_detail_tip_and_discount.json").read_text())
    respx.get("https://api.housecallpro.com/jobs").respond(200, json=jobs_list)
    respx.get("https://api.housecallpro.com/jobs/job_14404").respond(200, json=job_a)
    respx.get("https://api.housecallpro.com/jobs/job_14411").respond(200, json=job_b)

    # --- Config ---
    techs = TechsConfig(
        supervisor="Charles Rue",
        techs=[
            TechConfig(name="Charles Rue", commission_pct=0.20,
                       bonus_tier="step_tiers_charles", override_on_others_pct=0.02),
            TechConfig(name="Maurice", commission_pct=0.20),
            TechConfig(name="Nicholas Roccaforte", commission_pct=0.18),
        ],
    )
    bonus_tiers = {"step_tiers_charles": StepTierConfig(100, 400, 20, 10)}
    hcp_cfg = HCPConfig(
        endpoints={"jobs_list": "/jobs", "job_detail": "/jobs/{id}"},
        filters={"work_status": "completed"},
        pagination={"page_size": 100, "cursor_param": "cursor"},
        notes_fields=["notes", "service_notes", "internal_notes"],
        rate_limit={},
    )

    # --- Price sheet ---
    price_sheet = load_price_sheet(fixtures_dir / "parts_sheet_small.xlsx")

    # --- Sync jobs ---
    cache_dir = tmp_path / "cache"
    client = HCPClient(api_key="k", base_url="https://api.housecallpro.com",
                       cache_dir=cache_dir, max_retries=1, backoff_base=0.0)
    jobs = fetch_week_jobs(client, hcp_cfg,
                            week_start=date(2026, 4, 13),
                            week_end=date(2026, 4, 19))
    client.close()
    assert len(jobs) == 2

    # --- Resolve owners ---
    for j in jobs:
        j.owner_tech = resolve_owner(j.raw_techs, techs)
    owners = sorted(j.owner_tech for j in jobs)
    assert owners == ["Maurice", "Nicholas Roccaforte"]

    # --- DB init + run ---
    db_path = tmp_path / "payroll.db"
    conn = connect(db_path)
    init_schema(conn)
    run_id = create_run(conn,
                        week_start=date(2026, 4, 13),
                        week_end=date(2026, 4, 19),
                        hcp_cache_dir=str(cache_dir),
                        price_sheet_path="p",
                        price_sheet_sha256="h" * 64)

    # --- Scripted parts entry ---
    scripts = {
        "job_14404": ScriptedIO(inputs=[".243 #2 - 30.5\"", "2", ""]),
        "job_14411": ScriptedIO(inputs=["Universal Keypad", "1", ""]),
    }

    for j in sorted(jobs, key=lambda x: (x.job_date, x.hcp_job_number)):
        job_db_id = insert_job(
            conn, run_id=run_id, hcp_id=j.hcp_id, hcp_job_number=j.hcp_job_number,
            job_date=j.job_date, amount=j.amount, customer_display=j.customer_display,
            description=j.description, line_items_text=j.line_items_text,
            notes_text=j.notes_text, tip=j.tip, subtotal=j.subtotal, labor=j.labor,
            materials_charged=j.materials_charged, cc_fee=j.cc_fee, discount=j.discount,
            raw_techs=", ".join(j.raw_techs), owner_tech=j.owner_tech,
        )
        parts, skip = collect_parts_for_job(j, price_sheet, io=scripts[j.hcp_id])
        assert skip is None
        for p in parts:
            insert_job_part(conn, job_id=job_db_id, part_name=p.part_name,
                            quantity=p.quantity, unit_price=p.unit_price, source=p.source)
        j.parts = parts
        for row in compute_commissions(j, techs, bonus_tiers):
            insert_commission(
                conn, job_id=job_db_id,
                tech_name=row.tech_name, kind=row.kind, basis=row.basis,
                commission_pct=row.commission_pct, commission_amt=row.commission_amt,
                bonus_amt=row.bonus_amt, override_amt=row.override_amt, tip_amt=row.tip_amt,
            )
    set_run_status(conn, run_id, "final")

    # --- Build + write Excel ---
    report = build_weekly_report(
        conn, run_id=run_id,
        run_timestamp=datetime.utcnow().isoformat(),
        price_sheet_sha8="deadbeef",
        ticket_url_template="https://pro.housecallpro.com/app/jobs/{hcp_id}",
    )
    out_path = tmp_path / "payroll.xlsx"
    write_weekly_report(out_path, report)
    assert out_path.exists()

    # --- Assertions on the numbers ---
    # Job 14404: Maurice primary. basis = 1687 - 0 - (2 * 45.76) = 1595.48
    # Maurice pay = 1595.48 * 0.20 = 319.10
    # Charles override = 1595.48 * 0.02 = 31.91
    # Job 14411: Nicholas primary. basis = 3248 - 25 - 47.96 = 3175.04
    # Nicholas pay = 3175.04 * 0.18 = 571.51
    # Tip 25 pass-through to Nicholas
    by_tech = {r.tech: r for r in report.summary}
    assert by_tech["Maurice"].final_pay == pytest.approx(319.10)
    assert by_tech["Nicholas Roccaforte"].final_pay == pytest.approx(round(3175.04 * 0.18, 2) + 25.0)
    assert by_tech["Charles Rue"].final_pay == pytest.approx(31.91)

    # Sanity-check Excel was written with 3 sheets
    wb = openpyxl.load_workbook(out_path)
    assert wb.sheetnames == ["Summary", "Jobs", "Parts"]
