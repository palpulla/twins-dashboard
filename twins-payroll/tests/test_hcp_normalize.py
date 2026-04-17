"""Tests for engine/hcp_normalize.py — HCP JSON -> normalized Job."""
from __future__ import annotations

import json
from datetime import date

import pytest

from engine.hcp_normalize import apply_invoice, apply_line_items, normalize_job


@pytest.fixture
def job_detail(fixtures_dir):
    return json.loads((fixtures_dir / "hcp_job_detail.json").read_text())


@pytest.fixture
def job_with_discount(fixtures_dir):
    return json.loads((fixtures_dir / "hcp_job_detail_tip_and_discount.json").read_text())


def test_normalize_basic_fields(job_detail):
    job = normalize_job(job_detail)
    assert job.hcp_id == "job_14404"
    assert job.hcp_job_number == "14404"
    assert job.job_date == date(2026, 4, 14)
    assert job.customer_display == "Sarah Johnson"
    assert job.description == "Labor"
    assert job.amount == pytest.approx(1687.00)  # cents -> dollars
    assert job.tip == 0.0  # not in API


def test_normalize_techs_preserved_in_order(job_detail):
    job = normalize_job(job_detail)
    assert job.raw_techs == ["Charles Rue", "Maurice Williams"]


def test_normalize_notes_concatenates_content(job_detail):
    job = normalize_job(job_detail)
    assert "Replaced 2x .243" in job.notes_text
    assert "10 long-stem rollers" in job.notes_text


def test_normalize_amount_is_cents_converted(job_with_discount):
    job = normalize_job(job_with_discount)
    assert job.amount == pytest.approx(3223.00)
    assert job.subtotal == pytest.approx(3398.00)


def test_apply_line_items_builds_line_items_text(job_detail):
    job = normalize_job(job_detail)
    li_resp = {
        "data": [
            {"name": "Labor", "unit_price": 18500, "quantity": 1, "kind": "labor", "amount": 18500},
            {"name": "UltraLife Double Spring Replacement", "unit_price": 86900, "quantity": 1, "kind": "materials", "amount": 86900},
        ]
    }
    apply_line_items(job, li_resp)
    assert "SERVICES" in job.line_items_text
    assert "MATERIALS" in job.line_items_text
    assert "UltraLife Double Spring" in job.line_items_text
    assert "$185.00" in job.line_items_text
    assert "$869.00" in job.line_items_text


def test_apply_invoice_extracts_discount(job_detail):
    job = normalize_job(job_detail)
    inv_resp = {"invoices": [{"amount": 148700, "subtotal": 168700,
                               "discounts": [{"amount": -20000, "name": "Discount"}]}]}
    apply_invoice(job, inv_resp)
    assert job.discount == pytest.approx(200.00)
