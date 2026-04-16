#!/usr/bin/env python3
"""Sanity-check YAMLs + price sheet. Zero side effects."""
from __future__ import annotations

import os
import sys
from pathlib import Path

from dotenv import load_dotenv
from rich.console import Console

PROJECT_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(PROJECT_ROOT))

from engine.config_loader import load_bonus_tiers, load_hcp_config, load_rules, load_techs
from engine.price_sheet import load_price_sheet


console = Console()


def main() -> int:
    load_dotenv(PROJECT_ROOT / ".env")
    ok = True

    console.print("[bold]Validating configs...[/bold]")
    try:
        techs = load_techs(PROJECT_ROOT / "config/techs.yaml")
        console.print(f"  techs.yaml ✓ — {len(techs.techs)} techs; supervisor={techs.supervisor}")
    except Exception as e:
        console.print(f"  [red]techs.yaml ✗ — {e}[/red]")
        ok = False

    try:
        tiers = load_bonus_tiers(PROJECT_ROOT / "config/bonus_tiers.yaml")
        console.print(f"  bonus_tiers.yaml ✓ — {list(tiers.keys())}")
    except Exception as e:
        console.print(f"  [red]bonus_tiers.yaml ✗ — {e}[/red]")
        ok = False

    try:
        hcp = load_hcp_config(PROJECT_ROOT / "config/hcp.yaml")
        console.print(f"  hcp.yaml ✓ — endpoints={list(hcp.endpoints.keys())}")
    except Exception as e:
        console.print(f"  [red]hcp.yaml ✗ — {e}[/red]")
        ok = False

    try:
        rules = load_rules(PROJECT_ROOT / "config/rules.yaml")
        console.print(f"  rules.yaml ✓")
    except Exception as e:
        console.print(f"  [red]rules.yaml ✗ — {e}[/red]")
        ok = False
        rules = None

    if rules:
        price_sheet_path = (PROJECT_ROOT / rules.price_sheet_path).resolve()
        if not price_sheet_path.exists():
            console.print(f"  [yellow]price sheet not found at {price_sheet_path} (operator fills in later)[/yellow]")
        else:
            try:
                sheet = load_price_sheet(price_sheet_path, sheet_name=rules.price_sheet_sheet)
                console.print(f"  price sheet ✓ — {len(sheet.part_names)} parts loaded")
            except Exception as e:
                console.print(f"  [red]price sheet parse error: {e}[/red]")
                ok = False

    api_key = os.environ.get("HCP_API_KEY", "")
    if not api_key:
        console.print("  [yellow]HCP_API_KEY not set (.env missing or empty)[/yellow]")
    else:
        console.print(f"  HCP_API_KEY ✓ ({len(api_key)} chars)")

    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
