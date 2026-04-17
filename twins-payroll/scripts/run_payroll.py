#!/usr/bin/env python3
"""Main weekly payroll command.

Usage:
  python scripts/run_payroll.py --week 2026-04-13
  python scripts/run_payroll.py --week today
  python scripts/run_payroll.py --week last
"""
from __future__ import annotations

import argparse
import os
import sys
from datetime import date, datetime, timedelta
from pathlib import Path

from dotenv import load_dotenv
from rich.console import Console
from rich.table import Table

PROJECT_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(PROJECT_ROOT))

from engine.commission import compute_commissions
from engine.config_loader import load_bonus_tiers, load_hcp_config, load_rules, load_techs
from engine.db import (
    connect,
    create_run,
    get_run_by_week,
    init_schema,
    insert_commission,
    insert_job,
    insert_job_part,
    set_run_status,
    update_job_skip_reason,
)
from engine.hcp_client import HCPClient
from engine.hcp_sync import fetch_week_jobs
from engine.models import Job
from engine.ownership import AmbiguousOwnerError, UnknownTechError, resolve_owner
from engine.parts_prompt import collect_parts_for_job
from engine.price_sheet import PriceSheet, load_price_sheet
from engine.prompt_io import PromptToolkitIO
from engine.report_builder import build_weekly_report
from engine.excel_writer import write_weekly_report


console = Console()


def _parse_week(value: str) -> date:
    if value == "today":
        d = date.today()
    elif value == "last":
        d = date.today() - timedelta(days=7)
    else:
        d = date.fromisoformat(value)
    return d - timedelta(days=d.weekday())


def _resolve_all_owners(jobs: list[Job], techs, io) -> None:
    for j in jobs:
        try:
            j.owner_tech = resolve_owner(j.raw_techs, techs)
        except AmbiguousOwnerError as e:
            console.print(f"[yellow]Job {j.hcp_job_number}: ambiguous owner ({e})[/yellow]")
            console.print(f"  listed techs: {j.raw_techs}")
            pick = io.read("  pick owner > ").strip()
            if pick not in techs.names:
                raise SystemExit(f"unknown pick {pick!r}")
            j.owner_tech = pick
        except UnknownTechError as e:
            raise SystemExit(f"Job {j.hcp_job_number}: {e}")


def main() -> int:
    parser = argparse.ArgumentParser(description="Run weekly payroll")
    parser.add_argument("--week", required=True,
                        help="Monday of target week (YYYY-MM-DD, 'today', or 'last')")
    parser.add_argument("--fresh", action="store_true",
                        help="Discard any in-progress run for this week")
    parser.add_argument("--offline", metavar="RUN_ID",
                        help="Replay from an existing HCP cache (not implemented in v0)")
    parser.add_argument("--dry-run", action="store_true",
                        help="Run without writing DB or Excel output")
    args = parser.parse_args()

    load_dotenv(PROJECT_ROOT / ".env")
    api_key = os.environ.get("HCP_API_KEY", "")
    base_url = os.environ.get("HCP_BASE_URL", "https://api.housecallpro.com")
    if not api_key:
        console.print("[red]HCP_API_KEY not set. See .env.example.[/red]")
        return 2

    techs_cfg = load_techs(PROJECT_ROOT / "config/techs.yaml")
    bonus_tiers = load_bonus_tiers(PROJECT_ROOT / "config/bonus_tiers.yaml")
    hcp_cfg = load_hcp_config(PROJECT_ROOT / "config/hcp.yaml")
    rules = load_rules(PROJECT_ROOT / "config/rules.yaml")

    price_sheet_path = (PROJECT_ROOT / rules.price_sheet_path).resolve()
    if not price_sheet_path.exists():
        console.print(f"[red]Price sheet not found: {price_sheet_path}[/red]")
        return 2
    price_sheet = load_price_sheet(price_sheet_path, sheet_name=rules.price_sheet_sheet)
    price_sheet_sha256 = PriceSheet.hash_file(price_sheet_path)

    week_start = _parse_week(args.week)
    week_end = week_start + timedelta(days=6)
    console.print(f"[bold]Week: {week_start.isoformat()} to {week_end.isoformat()}[/bold]")

    db_path = PROJECT_ROOT / "data" / "payroll.db"
    conn = connect(db_path)
    init_schema(conn)

    existing = get_run_by_week(conn, week_start)
    if existing and existing["status"] == "in_progress":
        if args.fresh:
            set_run_status(conn, existing["id"], "superseded")
            console.print("[yellow]Discarded existing in-progress run[/yellow]")
        else:
            console.print(f"[yellow]Resuming run {existing['id']} (in_progress).[/yellow]")
            set_run_status(conn, existing["id"], "superseded")

    run_cache_dir = PROJECT_ROOT / "data" / "hcp_cache" / f"{week_start.isoformat()}_{datetime.utcnow().strftime('%Y%m%dT%H%M%S')}"
    run_id = create_run(
        conn,
        week_start=week_start,
        week_end=week_end,
        hcp_cache_dir=str(run_cache_dir),
        price_sheet_path=str(price_sheet_path),
        price_sheet_sha256=price_sheet_sha256,
    )

    client = HCPClient(
        api_key=api_key,
        base_url=base_url,
        cache_dir=run_cache_dir,
        max_retries=int(hcp_cfg.rate_limit.get("max_retries", 5)),
        backoff_base=float(hcp_cfg.rate_limit.get("backoff_base", 1.0)),
    )
    try:
        console.print("Fetching jobs from HCP...")
        jobs = fetch_week_jobs(client, hcp_cfg, week_start=week_start, week_end=week_end)
    finally:
        client.close()

    if not jobs:
        console.print("[yellow]No jobs returned for this week.[/yellow]")
        set_run_status(conn, run_id, "final")
        return 0

    io = PromptToolkitIO(price_sheet)
    _resolve_all_owners(jobs, techs_cfg, io)

    console.print(f"[bold]{len(jobs)} jobs to review[/bold]")

    for j in sorted(jobs, key=lambda x: (x.job_date, x.hcp_job_number)):
        if j.amount == 0:
            j.skip_reason = "zero_revenue"

        if j.skip_reason != "zero_revenue":
            # Tip is not in the HCP API; ask operator manually.
            tip_raw = io.read(f"  tip for job #{j.hcp_job_number} [0] > ").strip()
            if tip_raw:
                try:
                    j.tip = float(tip_raw)
                except ValueError:
                    console.print(f"[yellow]Invalid tip {tip_raw!r}, using 0[/yellow]")
                    j.tip = 0.0

        job_db_id = insert_job(
            conn,
            run_id=run_id,
            hcp_id=j.hcp_id,
            hcp_job_number=j.hcp_job_number,
            job_date=j.job_date,
            amount=j.amount,
            customer_display=j.customer_display,
            description=j.description,
            line_items_text=j.line_items_text,
            notes_text=j.notes_text,
            tip=j.tip,
            subtotal=j.subtotal,
            labor=j.labor,
            materials_charged=j.materials_charged,
            cc_fee=j.cc_fee,
            discount=j.discount,
            raw_techs=", ".join(j.raw_techs),
            owner_tech=j.owner_tech,
            skip_reason=j.skip_reason,
        )

        if j.skip_reason == "zero_revenue":
            console.print(f"[dim]skipped job {j.hcp_job_number} (zero revenue)[/dim]")
            continue

        parts, user_skip = collect_parts_for_job(j, price_sheet, io=io)
        if user_skip == "user_quit":
            console.print("[yellow]Quitting and saving progress.[/yellow]")
            return 1
        if user_skip == "user_skip":
            j.skip_reason = "user_skip"
            update_job_skip_reason(conn, job_id=job_db_id, skip_reason="user_skip")
            continue

        for p in parts:
            insert_job_part(
                conn, job_id=job_db_id,
                part_name=p.part_name, quantity=p.quantity, unit_price=p.unit_price,
                source=p.source,
            )
        j.parts = parts

        for row in compute_commissions(j, techs_cfg, bonus_tiers):
            insert_commission(
                conn, job_id=job_db_id,
                tech_name=row.tech_name, kind=row.kind, basis=row.basis,
                commission_pct=row.commission_pct, commission_amt=row.commission_amt,
                bonus_amt=row.bonus_amt, override_amt=row.override_amt, tip_amt=row.tip_amt,
            )

    set_run_status(conn, run_id, "final")

    if not args.dry_run:
        report = build_weekly_report(
            conn,
            run_id=run_id,
            run_timestamp=datetime.utcnow().isoformat(),
            price_sheet_sha8=price_sheet_sha256[:8],
            ticket_url_template=rules.ticket_url_template,
        )
        out_path = PROJECT_ROOT / "output" / f"payroll_week_of_{week_start.isoformat()}.xlsx"
        write_weekly_report(out_path, report)
        console.print(f"[green]Wrote {out_path}[/green]")

        table = Table(title="Weekly Summary", show_lines=False)
        for col in ["Tech", "Jobs", "Gross", "Tips", "Parts", "Basis",
                    "Comm", "Bonus", "Override", "Final"]:
            table.add_column(col, justify="right" if col != "Tech" else "left")
        for r in report.summary:
            table.add_row(
                r.tech, str(r.jobs),
                f"${r.gross_revenue:,.2f}", f"${r.tips:,.2f}",
                f"${r.parts_cost:,.2f}", f"${r.basis:,.2f}",
                f"${r.commission:,.2f}", f"${r.bonuses:,.2f}",
                f"${r.overrides:,.2f}", f"${r.final_pay:,.2f}",
            )
        console.print(table)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
