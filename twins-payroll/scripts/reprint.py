#!/usr/bin/env python3
"""Regenerate the weekly Excel output for a past run from the DB."""
from __future__ import annotations

import argparse
import sys
from datetime import date, datetime
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(PROJECT_ROOT))

from engine.config_loader import load_rules
from engine.db import connect, get_run_by_week
from engine.excel_writer import write_weekly_report
from engine.report_builder import build_weekly_report


def main() -> int:
    parser = argparse.ArgumentParser(description="Reprint weekly Excel from DB")
    parser.add_argument("--week", required=True, help="Monday of week (YYYY-MM-DD)")
    args = parser.parse_args()

    week_start = date.fromisoformat(args.week)
    rules = load_rules(PROJECT_ROOT / "config/rules.yaml")
    db_path = PROJECT_ROOT / "data" / "payroll.db"
    conn = connect(db_path)
    row = get_run_by_week(conn, week_start)
    if row is None:
        print(f"No run found for week {week_start.isoformat()}")
        return 1

    report = build_weekly_report(
        conn,
        run_id=row["id"],
        run_timestamp=datetime.utcnow().isoformat(),
        price_sheet_sha8=row["price_sheet_sha256"][:8] if row["price_sheet_sha256"] else "????????",
        ticket_url_template=rules.ticket_url_template,
    )
    out = PROJECT_ROOT / "output" / f"payroll_week_of_{week_start.isoformat()}.xlsx"
    write_weekly_report(out, report)
    print(f"Wrote {out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
