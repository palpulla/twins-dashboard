"""Tests for engine/models.py dataclasses."""
from datetime import date

import pytest

from engine.models import (
    CommissionRow,
    EmployeeRef,
    Job,
    JobPart,
    StepTierConfig,
    TechConfig,
    TechsConfig,
)


def test_employee_ref_basic():
    ref = EmployeeRef(hcp_id="emp_123", display_name="Charles Rue")
    assert ref.hcp_id == "emp_123"
    assert ref.display_name == "Charles Rue"


def test_job_defaults_and_parts_list():
    job = Job(
        hcp_id="job_1",
        hcp_job_number="14404",
        job_date=date(2026, 4, 14),
        customer_display="Sarah J.",
        description="Labor",
        line_items_text="SERVICES: Labor - $185.00",
        notes_text="",
        amount=1687.00,
        tip=0.0,
        subtotal=1687.00,
        labor=185.00,
        materials_charged=1498.00,
        cc_fee=0.0,
        discount=0.0,
        assigned_employees=[EmployeeRef("emp_1", "Maurice")],
        raw_techs=["Maurice"],
    )
    assert job.parts == []
    assert job.owner_tech is None
    assert job.skip_reason is None


def test_job_part_total_computed():
    part = JobPart(part_name=".243 #2 - 30.5\"", quantity=2, unit_price=45.76)
    assert part.total == pytest.approx(91.52)


def test_tech_config_defaults():
    tc = TechConfig(name="Maurice", commission_pct=0.20)
    assert tc.bonus_tier is None
    assert tc.override_on_others_pct == 0.0
    assert tc.hcp_employee_id is None


def test_techs_config_get_returns_config():
    techs = TechsConfig(
        supervisor="Charles Rue",
        techs=[
            TechConfig(name="Charles Rue", commission_pct=0.20, bonus_tier="step_tiers_charles", override_on_others_pct=0.02),
            TechConfig(name="Maurice", commission_pct=0.20),
        ],
    )
    assert techs.get("Maurice").commission_pct == pytest.approx(0.20)
    assert techs.get("Charles Rue").bonus_tier == "step_tiers_charles"


def test_techs_config_get_unknown_raises():
    techs = TechsConfig(supervisor="Charles Rue", techs=[])
    with pytest.raises(KeyError):
        techs.get("Unknown")


def test_step_tier_config_fields():
    tier = StepTierConfig(band_width=100, band_start=400, bonus_start=20, bonus_step=10)
    assert tier.band_width == 100
    assert tier.band_start == 400


def test_commission_row_total_sums():
    row = CommissionRow(
        tech_name="Maurice",
        kind="primary",
        basis=1472.00,
        commission_pct=0.20,
        commission_amt=294.40,
        bonus_amt=0.0,
        override_amt=0.0,
        tip_amt=10.0,
    )
    assert row.total == pytest.approx(304.40)
