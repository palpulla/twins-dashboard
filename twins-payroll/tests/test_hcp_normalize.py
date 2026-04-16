"""Tests for engine/hcp_normalize.py — HCP JSON -> normalized Job."""
import json
from datetime import date

import pytest

from engine.config_loader import HCPConfig
from engine.hcp_normalize import normalize_job


@pytest.fixture
def hcp_cfg():
    return HCPConfig(
        endpoints={},
        filters={},
        pagination={},
        notes_fields=["notes", "service_notes", "internal_notes"],
        rate_limit={},
    )


@pytest.fixture
def job_detail(fixtures_dir):
    return json.loads((fixtures_dir / "hcp_job_detail.json").read_text())


@pytest.fixture
def job_with_discount(fixtures_dir):
    return json.loads((fixtures_dir / "hcp_job_detail_tip_and_discount.json").read_text())


def test_normalize_basic_fields(job_detail, hcp_cfg):
    job = normalize_job(job_detail, hcp_cfg)
    assert job.hcp_id == "job_14404"
    assert job.hcp_job_number == "14404"
    assert job.job_date == date(2026, 4, 14)
    assert job.customer_display == "Sarah Johnson"
    assert job.description == "Labor"
    assert job.amount == pytest.approx(1687.00)
    assert job.tip == pytest.approx(0.0)


def test_normalize_techs_preserved_in_order(job_detail, hcp_cfg):
    job = normalize_job(job_detail, hcp_cfg)
    assert job.raw_techs == ["Charles Rue", "Maurice"]
    assert job.assigned_employees[0].hcp_id == "emp_charles"
    assert job.assigned_employees[0].display_name == "Charles Rue"


def test_normalize_notes_concatenates_configured_fields(job_detail, hcp_cfg):
    job = normalize_job(job_detail, hcp_cfg)
    assert "Replaced 2x .243 #2 30.5" in job.notes_text
    assert "10 long-stem rollers" in job.notes_text


def test_normalize_line_items_rendered_as_text(job_detail, hcp_cfg):
    job = normalize_job(job_detail, hcp_cfg)
    assert "SERVICES" in job.line_items_text
    assert "MATERIALS" in job.line_items_text
    assert "UltraLife Double Spring" in job.line_items_text
    assert "$869.00" in job.line_items_text


def test_normalize_job_with_discount(job_with_discount, hcp_cfg):
    job = normalize_job(job_with_discount, hcp_cfg)
    assert job.amount == pytest.approx(3248.00)
    assert job.tip == pytest.approx(25.00)
    assert job.discount == pytest.approx(175.00)
    assert job.cc_fee == pytest.approx(112.48)
    assert job.raw_techs == ["Nicholas Roccaforte"]


def test_normalize_handles_null_notes(job_with_discount, hcp_cfg):
    job = normalize_job(job_with_discount, hcp_cfg)
    assert job.notes_text.strip() == "Used 2220L-7 opener"
