"""
sync_rules.py — mirror twins-payroll commission config into Supabase
so the dashboard can read the same rules.

Usage:
    python sync_rules.py --effective-date 2026-04-22

Environment:
    SUPABASE_URL                 the project REST URL
    SUPABASE_SERVICE_ROLE_KEY    service role key (write access)
    TWINS_PAYROLL_ROOT           path to the twins-payroll project root
                                 (defaults to this file's parent dir)
"""
from __future__ import annotations
import argparse
import os
import sys
from dataclasses import dataclass
from datetime import date
from pathlib import Path
from typing import Optional

import yaml
import requests


@dataclass
class Tech:
    name: str
    hcp_employee_id: str
    commission_pct: float
    override_on_others_pct: float
    bonus_tier_name: Optional[str]


def _parse_techs(techs_yaml_path: Path) -> list[Tech]:
    data = yaml.safe_load(techs_yaml_path.read_text())
    supervisor = data.get("supervisor")
    out: list[Tech] = []
    for t in data["techs"]:
        is_supervisor = t["name"] == supervisor
        out.append(Tech(
            name=t["name"],
            hcp_employee_id=t["hcp_employee_id"],
            commission_pct=float(t["commission_pct"]),
            override_on_others_pct=float(t.get("override_on_others_pct", 0)) if is_supervisor else 0.0,
            bonus_tier_name=t.get("bonus_tier"),
        ))
    return out


def build_rule_rows(
    techs_yaml_path: Path,
    effective_date: str,
    bonus_tiers: dict,
) -> list[dict]:
    """Pure function: read techs.yaml + a bonus_tiers map, emit rule rows."""
    techs = _parse_techs(techs_yaml_path)
    rows = []
    for t in techs:
        tier = bonus_tiers.get(t.bonus_tier_name) if t.bonus_tier_name else None
        rows.append({
            "hcp_employee_id": t.hcp_employee_id,
            "name": t.name,
            "effective_date": effective_date,
            "base_pct": t.commission_pct,
            "supervisor_override_pct": t.override_on_others_pct,
            "tier_config": tier,
        })
    return rows


def upsert_to_supabase(rows: list[dict], url: str, service_role_key: str) -> None:
    """Look up technician IDs by hcp_employee_id, then call the admin RPC for each row."""
    headers = {
        "apikey": service_role_key,
        "Authorization": f"Bearer {service_role_key}",
        "Content-Type": "application/json",
    }
    for r in rows:
        # Resolve tech_id from technicians table by hcp_employee_id
        resp = requests.get(
            f"{url}/rest/v1/technicians",
            params={"hcp_employee_id": f"eq.{r['hcp_employee_id']}", "select": "id,name"},
            headers=headers,
        )
        resp.raise_for_status()
        found = resp.json()
        if not found:
            print(f"[skip] no technician row for hcp_employee_id={r['hcp_employee_id']} ({r['name']})", file=sys.stderr)
            continue
        tech_id = found[0]["id"]

        # Upsert via REST (service role bypasses RLS; no need to call the admin RPC as service role)
        upsert = requests.post(
            f"{url}/rest/v1/commission_rules",
            headers={**headers, "Prefer": "resolution=merge-duplicates,return=representation"},
            json={
                "tech_id": tech_id,
                "effective_date": r["effective_date"],
                "base_pct": r["base_pct"],
                "supervisor_override_pct": r["supervisor_override_pct"],
                "tier_config": r["tier_config"],
            },
        )
        upsert.raise_for_status()
        print(f"[ok] {r['name']}: base {r['base_pct']}, override {r['supervisor_override_pct']}, effective {r['effective_date']}")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--effective-date", default=date.today().isoformat())
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    root = Path(os.environ.get("TWINS_PAYROLL_ROOT", Path(__file__).parent.parent))
    techs_yaml = root / "config" / "techs.yaml"

    # Bonus tiers are hardcoded in the Python engine — export the mapping explicitly here.
    bonus_tiers = {
        "step_tiers_charles": {"band_width": 1000, "band_start": 5000, "bonus_start": 50, "bonus_step": 25},
    }

    rows = build_rule_rows(techs_yaml, args.effective_date, bonus_tiers)

    if args.dry_run:
        for r in rows:
            print(r)
        return 0

    url = os.environ["SUPABASE_URL"]
    key = os.environ["SUPABASE_SERVICE_ROLE_KEY"]
    upsert_to_supabase(rows, url, key)
    return 0


if __name__ == "__main__":
    sys.exit(main())
