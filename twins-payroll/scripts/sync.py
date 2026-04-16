#!/usr/bin/env python3
"""Fetch jobs + details from HCP and archive the raw responses. No DB writes."""
from __future__ import annotations

import argparse
import os
import sys
from datetime import date, datetime, timedelta
from pathlib import Path

from dotenv import load_dotenv
from rich.console import Console

PROJECT_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(PROJECT_ROOT))

from engine.config_loader import load_hcp_config
from engine.hcp_client import HCPClient
from engine.hcp_sync import fetch_week_jobs


console = Console()


def main() -> int:
    parser = argparse.ArgumentParser(description="Sync jobs from HCP; cache only, no DB")
    parser.add_argument("--week", required=True, help="Monday of week (YYYY-MM-DD)")
    args = parser.parse_args()

    load_dotenv(PROJECT_ROOT / ".env")
    api_key = os.environ.get("HCP_API_KEY", "")
    base_url = os.environ.get("HCP_BASE_URL", "https://api.housecallpro.com")
    if not api_key:
        console.print("[red]HCP_API_KEY not set[/red]")
        return 2

    hcp_cfg = load_hcp_config(PROJECT_ROOT / "config/hcp.yaml")
    week_start = date.fromisoformat(args.week)
    week_end = week_start + timedelta(days=6)
    cache_dir = PROJECT_ROOT / "data" / "hcp_cache" / f"sync_{week_start.isoformat()}_{datetime.utcnow().strftime('%Y%m%dT%H%M%S')}"

    client = HCPClient(api_key=api_key, base_url=base_url, cache_dir=cache_dir,
                       max_retries=int(hcp_cfg.rate_limit.get("max_retries", 5)),
                       backoff_base=float(hcp_cfg.rate_limit.get("backoff_base", 1.0)))
    try:
        jobs = fetch_week_jobs(client, hcp_cfg, week_start=week_start, week_end=week_end)
    finally:
        client.close()
    console.print(f"[green]Fetched {len(jobs)} jobs; cached under {cache_dir}[/green]")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
