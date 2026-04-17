"""Tests for engine/hcp_sync.py using respx to mock HCP."""
from __future__ import annotations

import json
from datetime import date

import httpx
import pytest
import respx

from engine.config_loader import HCPConfig
from engine.hcp_client import HCPClient
from engine.hcp_sync import fetch_week_jobs


@pytest.fixture
def hcp_cfg():
    return HCPConfig(
        endpoints={
            "jobs_list": "/jobs",
            "job_detail": "/jobs/{id}",
            "line_items": "/jobs/{id}/line_items",
            "invoices": "/jobs/{id}/invoices",
        },
        filters={"work_status": "complete unrated"},
        pagination={"page_size": 10},
        rate_limit={},
    )


@respx.mock
def test_fetch_week_jobs_filters_and_enriches(tmp_path, fixtures_dir, hcp_cfg):
    job_a = json.loads((fixtures_dir / "hcp_job_detail.json").read_text())
    job_b = json.loads((fixtures_dir / "hcp_job_detail_tip_and_discount.json").read_text())

    # Jobs list returns both, plus one scheduled (excluded) and one canceled (excluded)
    jobs_list = {
        "page": 1, "page_size": 10, "total_pages": 1, "total_items": 4,
        "jobs": [
            {"id": "job_14404", "work_status": "complete unrated"},
            {"id": "job_14411", "work_status": "complete unrated"},
            {"id": "job_other", "work_status": "scheduled"},
            {"id": "job_canceled", "work_status": "user canceled"},
        ],
    }
    respx.get("https://api.housecallpro.com/jobs").respond(200, json=jobs_list)
    respx.get("https://api.housecallpro.com/jobs/job_14404").respond(200, json=job_a)
    respx.get("https://api.housecallpro.com/jobs/job_14411").respond(200, json=job_b)
    respx.get("https://api.housecallpro.com/jobs/job_14404/line_items").respond(
        200, json={"object": "list", "data": [
            {"name": "Labor", "unit_price": 18500, "quantity": 1, "kind": "labor", "amount": 18500}
        ]})
    respx.get("https://api.housecallpro.com/jobs/job_14411/line_items").respond(
        200, json={"object": "list", "data": []})
    respx.get("https://api.housecallpro.com/jobs/job_14404/invoices").respond(
        200, json={"invoices": [{"amount": 168700, "subtotal": 168700, "discounts": []}]})
    respx.get("https://api.housecallpro.com/jobs/job_14411/invoices").respond(
        200, json={"invoices": [{"amount": 322300, "subtotal": 339800,
                                  "discounts": [{"amount": -17500, "name": "Discount"}]}]})

    client = HCPClient(api_key="test", base_url="https://api.housecallpro.com",
                       cache_dir=tmp_path, max_retries=1, backoff_base=0.0)
    jobs = fetch_week_jobs(client, hcp_cfg,
                            week_start=date(2026, 4, 13), week_end=date(2026, 4, 19))
    client.close()

    # Only 2 eligible jobs (scheduled + canceled excluded)
    assert len(jobs) == 2
    by_id = {j.hcp_id: j for j in jobs}
    assert by_id["job_14404"].amount == pytest.approx(1687.00)
    assert "Labor" in by_id["job_14404"].line_items_text
    assert by_id["job_14411"].discount == pytest.approx(175.00)


@respx.mock
def test_fetch_week_jobs_paginates_by_page_number(tmp_path, fixtures_dir, hcp_cfg):
    """Multiple pages are walked; all pages' jobs get fetched."""
    job_a = json.loads((fixtures_dir / "hcp_job_detail.json").read_text())
    job_b = json.loads((fixtures_dir / "hcp_job_detail_tip_and_discount.json").read_text())

    responses = iter([
        httpx.Response(200, json={"page": 1, "page_size": 10, "total_pages": 2, "total_items": 2,
                                   "jobs": [{"id": "job_14404", "work_status": "complete unrated"}]}),
        httpx.Response(200, json={"page": 2, "page_size": 10, "total_pages": 2, "total_items": 2,
                                   "jobs": [{"id": "job_14411", "work_status": "complete unrated"}]}),
    ])
    respx.get("https://api.housecallpro.com/jobs").mock(side_effect=lambda r: next(responses))
    respx.get("https://api.housecallpro.com/jobs/job_14404").respond(200, json=job_a)
    respx.get("https://api.housecallpro.com/jobs/job_14411").respond(200, json=job_b)
    respx.get("https://api.housecallpro.com/jobs/job_14404/line_items").respond(200, json={"data": []})
    respx.get("https://api.housecallpro.com/jobs/job_14411/line_items").respond(200, json={"data": []})
    respx.get("https://api.housecallpro.com/jobs/job_14404/invoices").respond(200, json={"invoices": []})
    respx.get("https://api.housecallpro.com/jobs/job_14411/invoices").respond(200, json={"invoices": []})

    client = HCPClient(api_key="test", base_url="https://api.housecallpro.com",
                       cache_dir=tmp_path, max_retries=1, backoff_base=0.0)
    jobs = fetch_week_jobs(client, hcp_cfg,
                            week_start=date(2026, 4, 13), week_end=date(2026, 4, 19))
    client.close()
    ids = {j.hcp_id for j in jobs}
    assert ids == {"job_14404", "job_14411"}
