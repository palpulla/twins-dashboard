"""Tests for engine/hcp_sync.py using respx to mock HCP."""
from __future__ import annotations

import json
from datetime import date
from pathlib import Path

import pytest
import respx

from engine.config_loader import HCPConfig
from engine.hcp_client import HCPClient
from engine.hcp_sync import fetch_week_jobs


@pytest.fixture
def hcp_cfg():
    return HCPConfig(
        endpoints={"jobs_list": "/jobs", "job_detail": "/jobs/{id}"},
        filters={"work_status": "completed"},
        pagination={"page_size": 100, "cursor_param": "cursor"},
        notes_fields=["notes", "service_notes", "internal_notes"],
        rate_limit={},
    )


@respx.mock
def test_fetch_week_jobs_pulls_list_then_detail(tmp_path, fixtures_dir, hcp_cfg):
    jobs_list = json.loads((fixtures_dir / "hcp_jobs_list.json").read_text())
    job_a = json.loads((fixtures_dir / "hcp_job_detail.json").read_text())
    job_b = json.loads((fixtures_dir / "hcp_job_detail_tip_and_discount.json").read_text())

    respx.get("https://api.housecallpro.com/jobs").respond(200, json=jobs_list)
    respx.get("https://api.housecallpro.com/jobs/job_14404").respond(200, json=job_a)
    respx.get("https://api.housecallpro.com/jobs/job_14411").respond(200, json=job_b)

    client = HCPClient(
        api_key="test", base_url="https://api.housecallpro.com",
        cache_dir=tmp_path, max_retries=1, backoff_base=0.0,
    )

    jobs = fetch_week_jobs(
        client,
        hcp_cfg,
        week_start=date(2026, 4, 13),
        week_end=date(2026, 4, 19),
    )
    assert len(jobs) == 2
    ids = {j.hcp_id for j in jobs}
    assert ids == {"job_14404", "job_14411"}
    j404 = next(j for j in jobs if j.hcp_id == "job_14404")
    assert "Replaced 2x .243" in j404.notes_text


@respx.mock
def test_fetch_week_jobs_paginates(tmp_path, fixtures_dir, hcp_cfg):
    """Jobs across multiple pages are all fetched, not just page 1."""
    import json as _json
    import httpx as _httpx

    job_a = _json.loads((fixtures_dir / "hcp_job_detail.json").read_text())
    job_b = _json.loads((fixtures_dir / "hcp_job_detail_tip_and_discount.json").read_text())

    # Page 1 has job_14404 with a cursor to page 2; page 2 has job_14411.
    page1 = {"data": [{"id": "job_14404", "invoice_number": "14404"}], "next_cursor": "page2"}
    page2 = {"data": [{"id": "job_14411", "invoice_number": "14411"}], "next_cursor": None}

    list_responses = iter([
        _httpx.Response(200, json=page1),
        _httpx.Response(200, json=page2),
    ])
    respx.get("https://api.housecallpro.com/jobs").mock(side_effect=lambda req: next(list_responses))
    respx.get("https://api.housecallpro.com/jobs/job_14404").respond(200, json=job_a)
    respx.get("https://api.housecallpro.com/jobs/job_14411").respond(200, json=job_b)

    client = HCPClient(
        api_key="test", base_url="https://api.housecallpro.com",
        cache_dir=tmp_path, max_retries=1, backoff_base=0.0,
    )
    jobs = fetch_week_jobs(client, hcp_cfg,
                           week_start=date(2026, 4, 13),
                           week_end=date(2026, 4, 19))
    client.close()
    ids = {j.hcp_id for j in jobs}
    assert ids == {"job_14404", "job_14411"}
