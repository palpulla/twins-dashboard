# /Users/daniel/twins-dashboard/twins-payroll/tests/test_sync_rules.py
from pathlib import Path
import yaml
from twins_payroll.sync_rules import build_rule_rows, Tech

def test_build_rule_rows_from_techs_yaml_matches_spec(tmp_path: Path):
    techs_yaml = tmp_path / "techs.yaml"
    techs_yaml.write_text("""
supervisor: Charles Rue
techs:
  - name: Charles Rue
    hcp_employee_id: pro_abc
    commission_pct: 0.20
    bonus_tier: step_tiers_charles
    override_on_others_pct: 0.02
  - name: Maurice Williams
    hcp_employee_id: pro_def
    commission_pct: 0.20
  - name: Nicholas Roccaforte
    hcp_employee_id: pro_ghi
    commission_pct: 0.18
""")
    rows = build_rule_rows(techs_yaml, effective_date="2026-04-22", bonus_tiers={
        "step_tiers_charles": {"band_width": 1000, "band_start": 5000, "bonus_start": 50, "bonus_step": 25},
    })
    charles = next(r for r in rows if r["hcp_employee_id"] == "pro_abc")
    assert charles["base_pct"] == 0.20
    assert charles["supervisor_override_pct"] == 0.02
    assert charles["tier_config"] == {"band_width": 1000, "band_start": 5000, "bonus_start": 50, "bonus_step": 25}
    maurice = next(r for r in rows if r["hcp_employee_id"] == "pro_def")
    assert maurice["base_pct"] == 0.20
    assert maurice["supervisor_override_pct"] == 0
    assert maurice["tier_config"] is None
