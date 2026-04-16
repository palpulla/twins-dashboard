"""Tests for engine/config_loader.py"""
from pathlib import Path

import pytest

from engine.config_loader import (
    HCPConfig,
    RulesConfig,
    load_bonus_tiers,
    load_hcp_config,
    load_rules,
    load_techs,
)
from engine.models import StepTierConfig, TechsConfig


def _write(path: Path, content: str) -> Path:
    path.write_text(content)
    return path


def test_load_techs_parses_roster(tmp_path):
    cfg = _write(tmp_path / "techs.yaml", """
supervisor: Charles Rue
techs:
  - name: Charles Rue
    commission_pct: 0.20
    bonus_tier: step_tiers_charles
    override_on_others_pct: 0.02
  - name: Maurice
    commission_pct: 0.20
  - name: Nicholas Roccaforte
    commission_pct: 0.18
""")
    techs = load_techs(cfg)
    assert isinstance(techs, TechsConfig)
    assert techs.supervisor == "Charles Rue"
    assert techs.get("Maurice").commission_pct == pytest.approx(0.20)
    assert techs.get("Nicholas Roccaforte").commission_pct == pytest.approx(0.18)
    assert techs.get("Charles Rue").bonus_tier == "step_tiers_charles"
    assert techs.get("Charles Rue").override_on_others_pct == pytest.approx(0.02)


def test_load_techs_missing_supervisor_raises(tmp_path):
    cfg = _write(tmp_path / "techs.yaml", """
techs:
  - name: Maurice
    commission_pct: 0.20
""")
    with pytest.raises(ValueError, match="supervisor"):
        load_techs(cfg)


def test_load_techs_supervisor_not_in_roster_raises(tmp_path):
    cfg = _write(tmp_path / "techs.yaml", """
supervisor: Ghost
techs:
  - name: Maurice
    commission_pct: 0.20
""")
    with pytest.raises(ValueError, match="supervisor.*not in roster"):
        load_techs(cfg)


def test_load_bonus_tiers(tmp_path):
    cfg = _write(tmp_path / "bonus_tiers.yaml", """
step_tiers_charles:
  band_width: 100
  band_start: 400
  bonus_start: 20
  bonus_step: 10
""")
    tiers = load_bonus_tiers(cfg)
    assert isinstance(tiers["step_tiers_charles"], StepTierConfig)
    assert tiers["step_tiers_charles"].band_start == 400


def test_load_hcp_config(tmp_path):
    cfg = _write(tmp_path / "hcp.yaml", """
endpoints:
  jobs_list: /jobs
  job_detail: /jobs/{id}
  employees: /employees
filters:
  work_status: completed
pagination:
  page_size: 100
  cursor_param: cursor
notes_fields:
  - notes
  - service_notes
rate_limit:
  max_retries: 5
  backoff_base: 1.0
""")
    hcp = load_hcp_config(cfg)
    assert isinstance(hcp, HCPConfig)
    assert hcp.endpoints["jobs_list"] == "/jobs"
    assert hcp.filters["work_status"] == "completed"
    assert hcp.pagination["page_size"] == 100
    assert hcp.notes_fields == ["notes", "service_notes"]
    assert hcp.rate_limit["max_retries"] == 5


def test_load_rules(tmp_path):
    cfg = _write(tmp_path / "rules.yaml", """
price_sheet:
  path: ../prices/parts.xlsx
  sheet: Pricing
week:
  start_day: monday
ticket_url_template: https://pro.housecallpro.com/app/jobs/{hcp_id}
""")
    rules = load_rules(cfg)
    assert isinstance(rules, RulesConfig)
    assert rules.price_sheet_path == "../prices/parts.xlsx"
    assert rules.price_sheet_sheet == "Pricing"
    assert rules.week_start_day == "monday"
    assert rules.ticket_url_template == "https://pro.housecallpro.com/app/jobs/{hcp_id}"
