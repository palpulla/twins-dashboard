"""Aggregate DB rows for a run into a WeeklyReport ready for excel_writer."""
from __future__ import annotations

import sqlite3
from collections import defaultdict
from datetime import date

from engine.db import (
    fetch_commissions_for_run,
    fetch_jobs_for_run,
    fetch_parts_for_job,
)
from engine.excel_writer import (
    JobRow,
    PartRow,
    WeeklyReport,
    WeeklyReportMeta,
    WeeklySummaryRow,
)


def _parse_date(s: str) -> date:
    return date.fromisoformat(s)


def build_weekly_report(
    conn: sqlite3.Connection,
    *,
    run_id: int,
    run_timestamp: str,
    price_sheet_sha8: str,
    ticket_url_template: str,
) -> WeeklyReport:
    run_row = conn.execute("SELECT * FROM runs WHERE id = ?", (run_id,)).fetchone()
    if run_row is None:
        raise ValueError(f"run {run_id} not found")

    meta = WeeklyReportMeta(
        week_start=_parse_date(run_row["week_start"]),
        week_end=_parse_date(run_row["week_end"]),
        run_timestamp=run_timestamp,
        hcp_cache_dir=run_row["hcp_cache_dir"],
        hcp_response_count=_count_cache(conn, run_row["hcp_cache_dir"]),
        price_sheet_sha8=price_sheet_sha8,
        ticket_url_template=ticket_url_template,
    )

    jobs = fetch_jobs_for_run(conn, run_id)
    comms = fetch_commissions_for_run(conn, run_id)

    primary_by_tech: dict[str, dict] = defaultdict(lambda: {
        "jobs": 0, "gross": 0.0, "tips": 0.0, "parts_cost": 0.0,
        "basis": 0.0, "commission": 0.0, "bonus": 0.0, "tip_amt": 0.0,
    })
    override_by_tech: dict[str, float] = defaultdict(float)
    override_tip_by_tech: dict[str, float] = defaultdict(float)

    comm_by_job: dict[int, list[sqlite3.Row]] = defaultdict(list)
    for c in comms:
        comm_by_job[c["job_id"]].append(c)

    for j in jobs:
        if j["skip_reason"]:
            continue
        parts_cost = sum(p["total"] for p in fetch_parts_for_job(conn, j["id"]))
        for c in comm_by_job[j["id"]]:
            if c["kind"] == "primary":
                agg = primary_by_tech[c["tech_name"]]
                agg["jobs"] += 1
                agg["gross"] += j["amount"]
                agg["tips"] += j["tip"]
                agg["parts_cost"] += parts_cost
                agg["basis"] += c["basis"]
                agg["commission"] += c["commission_amt"]
                agg["bonus"] += c["bonus_amt"]
                agg["tip_amt"] += c["tip_amt"]
            else:
                override_by_tech[c["tech_name"]] += c["override_amt"]
                override_tip_by_tech[c["tech_name"]] += c["tip_amt"]

    all_techs = set(primary_by_tech.keys()) | set(override_by_tech.keys())
    summary: list[WeeklySummaryRow] = []
    for tech in sorted(all_techs):
        a = primary_by_tech.get(tech, {
            "jobs": 0, "gross": 0.0, "tips": 0.0, "parts_cost": 0.0,
            "basis": 0.0, "commission": 0.0, "bonus": 0.0, "tip_amt": 0.0,
        })
        overrides = override_by_tech.get(tech, 0.0)
        final_pay = a["commission"] + a["bonus"] + overrides + a["tip_amt"]
        summary.append(WeeklySummaryRow(
            tech=tech,
            jobs=a["jobs"],
            gross_revenue=round(a["gross"], 2),
            tips=round(a["tips"], 2),
            parts_cost=round(a["parts_cost"], 2),
            basis=round(a["basis"], 2),
            commission=round(a["commission"], 2),
            bonuses=round(a["bonus"], 2),
            overrides=round(overrides, 2),
            final_pay=round(final_pay, 2),
        ))

    job_rows: list[JobRow] = []
    for j in jobs:
        parts = fetch_parts_for_job(conn, j["id"])
        parts_cost = round(sum(p["total"] for p in parts), 2)
        primary = next((c for c in comm_by_job[j["id"]] if c["kind"] == "primary"), None)
        override = next((c for c in comm_by_job[j["id"]] if c["kind"] == "override"), None)
        notes = j["notes"] or ""
        if j["skip_reason"] == "zero_revenue":
            notes = (notes + " | zero revenue — skipped").strip(" |")
        job_rows.append(JobRow(
            job_number=j["hcp_job_number"],
            hcp_id=j["hcp_id"],
            job_date=_parse_date(j["job_date"]),
            customer=j["customer_display"] or "",
            amount=j["amount"],
            tip=j["tip"],
            parts_cost=parts_cost,
            basis=primary["basis"] if primary else 0.0,
            listed_techs=j["raw_techs"] or "",
            owner=j["owner_tech"] or "",
            primary_comm=primary["commission_amt"] if primary else 0.0,
            charles_bonus=(primary["bonus_amt"] if primary and primary["tech_name"] == "Charles Rue" else 0.0),
            charles_override=override["override_amt"] if override else 0.0,
            notes=notes,
        ))

    part_rows: list[PartRow] = []
    for j in jobs:
        for p in fetch_parts_for_job(conn, j["id"]):
            part_rows.append(PartRow(
                job_number=j["hcp_job_number"],
                part_name=p["part_name"],
                quantity=p["quantity"],
                unit_price=p["unit_price"],
                total=p["total"],
                source=p["source"],
            ))

    return WeeklyReport(meta=meta, summary=summary, jobs=job_rows, parts=part_rows)


def _count_cache(conn, cache_dir: str) -> int:
    from pathlib import Path
    p = Path(cache_dir)
    if not p.is_absolute():
        return 0
    if not p.exists():
        return 0
    return sum(1 for _ in p.glob("*.json"))
