#!/usr/bin/env python3
"""Print a past run's summary (and optionally per-job detail) to the terminal."""
from __future__ import annotations

import argparse
import sys
from datetime import date, datetime
from pathlib import Path

from rich.console import Console
from rich.table import Table

PROJECT_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(PROJECT_ROOT))

from engine.config_loader import load_rules
from engine.db import connect, get_run_by_week
from engine.report_builder import build_weekly_report


console = Console()


def main() -> int:
    parser = argparse.ArgumentParser(description="Show past run summary")
    parser.add_argument("--week", required=True, help="Monday of week (YYYY-MM-DD)")
    parser.add_argument("--jobs", action="store_true", help="Also show per-job detail")
    args = parser.parse_args()

    week_start = date.fromisoformat(args.week)
    rules = load_rules(PROJECT_ROOT / "config/rules.yaml")
    db_path = PROJECT_ROOT / "data" / "payroll.db"
    conn = connect(db_path)
    row = get_run_by_week(conn, week_start)
    if row is None:
        console.print(f"[red]No run for week {week_start.isoformat()}[/red]")
        return 1
    report = build_weekly_report(
        conn,
        run_id=row["id"],
        run_timestamp=datetime.utcnow().isoformat(),
        price_sheet_sha8=(row["price_sheet_sha256"] or "")[:8],
        ticket_url_template=rules.ticket_url_template,
    )

    t = Table(title=f"Week of {week_start.isoformat()}")
    for col in ["Tech", "Jobs", "Gross", "Tips", "Parts", "Basis",
                "Comm", "Bonus", "Override", "Final"]:
        t.add_column(col)
    for r in report.summary:
        t.add_row(r.tech, str(r.jobs),
                  f"${r.gross_revenue:,.2f}", f"${r.tips:,.2f}",
                  f"${r.parts_cost:,.2f}", f"${r.basis:,.2f}",
                  f"${r.commission:,.2f}", f"${r.bonuses:,.2f}",
                  f"${r.overrides:,.2f}", f"${r.final_pay:,.2f}")
    console.print(t)

    if args.jobs:
        jt = Table(title="Jobs")
        for col in ["Job #", "Date", "Customer", "Owner", "Amount", "Tip",
                    "Parts", "Basis", "Primary", "Bonus", "Override"]:
            jt.add_column(col)
        for j in report.jobs:
            jt.add_row(
                j.job_number, j.job_date.isoformat(), j.customer, j.owner,
                f"${j.amount:,.2f}", f"${j.tip:,.2f}",
                f"${j.parts_cost:,.2f}", f"${j.basis:,.2f}",
                f"${j.primary_comm:,.2f}", f"${j.charles_bonus:,.2f}",
                f"${j.charles_override:,.2f}",
            )
        console.print(jt)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
