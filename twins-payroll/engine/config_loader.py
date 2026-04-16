"""YAML config loaders for twins-payroll.

Validates structure and types, emits typed config objects. No I/O beyond
reading the provided file paths.
"""
from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Any

import yaml

from engine.models import StepTierConfig, TechConfig, TechsConfig


def _read_yaml(path: Path) -> dict[str, Any]:
    with open(path) as f:
        data = yaml.safe_load(f)
    if data is None:
        raise ValueError(f"Empty YAML file: {path}")
    if not isinstance(data, dict):
        raise ValueError(f"Top-level YAML must be a mapping in {path}")
    return data


def load_techs(path: Path) -> TechsConfig:
    data = _read_yaml(path)
    supervisor = data.get("supervisor")
    if not supervisor:
        raise ValueError(f"Missing 'supervisor' key in {path}")
    techs_raw = data.get("techs") or []
    techs = [
        TechConfig(
            name=t["name"],
            commission_pct=float(t["commission_pct"]),
            bonus_tier=t.get("bonus_tier"),
            override_on_others_pct=float(t.get("override_on_others_pct", 0.0)),
            hcp_employee_id=t.get("hcp_employee_id"),
        )
        for t in techs_raw
    ]
    names = {t.name for t in techs}
    if supervisor not in names:
        raise ValueError(f"supervisor {supervisor!r} not in roster {sorted(names)}")
    return TechsConfig(supervisor=supervisor, techs=techs)


def load_bonus_tiers(path: Path) -> dict[str, StepTierConfig]:
    data = _read_yaml(path)
    return {
        key: StepTierConfig(
            band_width=int(val["band_width"]),
            band_start=int(val["band_start"]),
            bonus_start=int(val["bonus_start"]),
            bonus_step=int(val["bonus_step"]),
        )
        for key, val in data.items()
    }


@dataclass
class HCPConfig:
    endpoints: dict[str, str]
    filters: dict[str, Any]
    pagination: dict[str, Any]
    notes_fields: list[str]
    rate_limit: dict[str, Any]


def load_hcp_config(path: Path) -> HCPConfig:
    data = _read_yaml(path)
    return HCPConfig(
        endpoints=data.get("endpoints", {}),
        filters=data.get("filters", {}),
        pagination=data.get("pagination", {}),
        notes_fields=list(data.get("notes_fields", [])),
        rate_limit=data.get("rate_limit", {}),
    )


@dataclass
class RulesConfig:
    price_sheet_path: str
    price_sheet_sheet: str
    week_start_day: str
    ticket_url_template: str


def load_rules(path: Path) -> RulesConfig:
    data = _read_yaml(path)
    ps = data.get("price_sheet", {})
    return RulesConfig(
        price_sheet_path=ps.get("path", "../prices/parts.xlsx"),
        price_sheet_sheet=ps.get("sheet", "Pricing"),
        week_start_day=(data.get("week", {}) or {}).get("start_day", "monday"),
        ticket_url_template=data.get("ticket_url_template", ""),
    )
