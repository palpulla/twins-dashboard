"""Tests for engine/commission.py — commission, bonus, override math."""
from datetime import date

import pytest

from engine.commission import compute_commissions, compute_step_bonus
from engine.models import (
    EmployeeRef,
    Job,
    JobPart,
    StepTierConfig,
    TechConfig,
    TechsConfig,
)


@pytest.fixture
def techs():
    return TechsConfig(
        supervisor="Charles Rue",
        techs=[
            TechConfig(name="Charles Rue", commission_pct=0.20,
                       bonus_tier="step_tiers_charles", override_on_others_pct=0.02),
            TechConfig(name="Maurice", commission_pct=0.20),
            TechConfig(name="Nicholas Roccaforte", commission_pct=0.18),
        ],
    )


@pytest.fixture
def tiers():
    return {"step_tiers_charles": StepTierConfig(
        band_width=100, band_start=400, bonus_start=20, bonus_step=10)}


def _mk_job(*, amount, tip=0.0, raw_techs, owner, parts=()):
    return Job(
        hcp_id="h1", hcp_job_number="14404", job_date=date(2026, 4, 14),
        customer_display="Test", description="", line_items_text="", notes_text="",
        amount=amount, tip=tip, subtotal=amount, labor=0.0, materials_charged=0.0,
        cc_fee=0.0, discount=0.0,
        assigned_employees=[EmployeeRef("", n) for n in raw_techs],
        raw_techs=list(raw_techs), owner_tech=owner,
        parts=[JobPart(name, q, price) for name, q, price in parts],
    )


# --- step bonus -----------------------------------------------------------

@pytest.mark.parametrize("basis,expected", [
    (399.99, 0.0),
    (400.00, 20.0),
    (400.01, 20.0),
    (499.99, 20.0),
    (500.00, 30.0),
    (600.00, 40.0),
    (999.99, 70.0),
    (1000.00, 80.0),
    (5000.00, 480.0),
    (0.0, 0.0),
])
def test_compute_step_bonus(basis, expected, tiers):
    assert compute_step_bonus(basis, tiers["step_tiers_charles"]) == pytest.approx(expected)


# --- primary commission ---------------------------------------------------

def test_solo_charles_primary_plus_bonus(techs, tiers):
    job = _mk_job(amount=625.0, tip=0.0, raw_techs=["Charles Rue"],
                  owner="Charles Rue", parts=[("Drum", 2, 6.86)])
    # parts_cost = 13.72, basis = 625 - 0 - 13.72 = 611.28 -> bonus band $600-699 -> $40
    rows = compute_commissions(job, techs, tiers)
    assert len(rows) == 1
    r = rows[0]
    assert r.tech_name == "Charles Rue"
    assert r.kind == "primary"
    assert r.basis == pytest.approx(611.28)
    assert r.commission_amt == pytest.approx(round(611.28 * 0.20, 2))
    assert r.bonus_amt == pytest.approx(40.0)
    assert r.override_amt == 0.0
    assert r.tip_amt == 0.0


def test_charles_plus_maurice_maurice_primary_charles_override(techs, tiers):
    job = _mk_job(amount=1687.0, tip=0.0, raw_techs=["Charles Rue", "Maurice"],
                  owner="Maurice", parts=[(".243 #2 - 30.5\"", 2, 45.76)])
    # parts_cost = 91.52, basis = 1687 - 0 - 91.52 = 1595.48
    rows = compute_commissions(job, techs, tiers)
    assert len(rows) == 2

    primary = next(r for r in rows if r.kind == "primary")
    assert primary.tech_name == "Maurice"
    assert primary.basis == pytest.approx(1595.48)
    assert primary.commission_amt == pytest.approx(round(1595.48 * 0.20, 2))
    assert primary.bonus_amt == 0.0  # Maurice has no bonus tier

    override = next(r for r in rows if r.kind == "override")
    assert override.tech_name == "Charles Rue"
    assert override.basis == pytest.approx(1595.48)
    assert override.commission_amt == 0.0
    assert override.override_amt == pytest.approx(round(1595.48 * 0.02, 2))
    assert override.tip_amt == 0.0


def test_nicholas_solo_at_18pct(techs, tiers):
    job = _mk_job(amount=212.75, tip=27.75, raw_techs=["Nicholas Roccaforte"],
                  owner="Nicholas Roccaforte", parts=[])
    # basis = 212.75 - 27.75 - 0 = 185.0
    rows = compute_commissions(job, techs, tiers)
    assert len(rows) == 1
    r = rows[0]
    assert r.tech_name == "Nicholas Roccaforte"
    assert r.commission_amt == pytest.approx(round(185.0 * 0.18, 2))
    assert r.tip_amt == pytest.approx(27.75)
    assert r.bonus_amt == 0.0


def test_negative_basis_clamped(techs, tiers):
    # parts cost exceeds revenue - tip
    job = _mk_job(amount=100.0, tip=0.0, raw_techs=["Maurice"], owner="Maurice",
                  parts=[("Drum", 20, 6.86)])  # parts = 137.20
    rows = compute_commissions(job, techs, tiers)
    r = rows[0]
    assert r.basis == 0.0
    assert r.commission_amt == 0.0
    assert r.bonus_amt == 0.0
    assert r.tip_amt == 0.0


def test_tip_passes_to_owner_not_to_override(techs, tiers):
    job = _mk_job(amount=500.0, tip=50.0, raw_techs=["Charles Rue", "Maurice"],
                  owner="Maurice", parts=[])
    # basis = 500 - 50 - 0 = 450
    rows = compute_commissions(job, techs, tiers)
    primary = next(r for r in rows if r.kind == "primary")
    override = next(r for r in rows if r.kind == "override")
    assert primary.tip_amt == pytest.approx(50.0)
    assert override.tip_amt == 0.0
    assert override.override_amt == pytest.approx(9.0)  # 450 * 0.02


def test_charles_solo_no_override_row(techs, tiers):
    job = _mk_job(amount=450.0, tip=0.0, raw_techs=["Charles Rue"],
                  owner="Charles Rue", parts=[])
    rows = compute_commissions(job, techs, tiers)
    assert len(rows) == 1
    assert rows[0].kind == "primary"
