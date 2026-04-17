"""Orchestrate fetching a week's jobs + details + line items + invoices from HCP."""
from __future__ import annotations

from datetime import date, datetime, time

from engine.config_loader import HCPConfig
from engine.hcp_client import HCPClient, HCPError
from engine.hcp_normalize import apply_invoice, apply_line_items, normalize_job
from engine.models import Job


def _iso_day_start(d: date) -> str:
    return datetime.combine(d, time.min).isoformat()


def _iso_day_end(d: date) -> str:
    return datetime.combine(d, time.max).isoformat()


def fetch_week_jobs(
    client: HCPClient,
    hcp_cfg: HCPConfig,
    *,
    week_start: date,
    week_end: date,
) -> list[Job]:
    """Fetch all payroll-eligible jobs for the given week (page-based pagination)."""
    list_path = hcp_cfg.endpoints.get("jobs_list", "/jobs")
    detail_tmpl = hcp_cfg.endpoints.get("job_detail", "/jobs/{id}")
    line_items_tmpl = hcp_cfg.endpoints.get("line_items", "/jobs/{id}/line_items")
    invoices_tmpl = hcp_cfg.endpoints.get("invoices", "/jobs/{id}/invoices")
    work_status = hcp_cfg.filters.get("work_status", "complete unrated")

    base_params = {
        "scheduled_start_min": _iso_day_start(week_start),
        "scheduled_start_max": _iso_day_end(week_end),
    }

    all_items: list[dict] = []
    page = 1
    while True:
        resp = client.get(list_path, params={**base_params, "page": page})
        page_jobs = resp.get("jobs") or resp.get("data") or []
        all_items.extend(page_jobs)
        total_pages = resp.get("total_pages", 1)
        if page >= total_pages or not page_jobs:
            break
        page += 1

    # Filter to payroll-eligible status
    eligible = [j for j in all_items if j.get("work_status") == work_status]

    jobs: list[Job] = []
    for item in eligible:
        jid = item.get("id")
        if not jid:
            continue
        detail = client.get(detail_tmpl.replace("{id}", str(jid)))
        job = normalize_job(detail)

        # Optional: line items
        try:
            li = client.get(line_items_tmpl.replace("{id}", str(jid)))
            apply_line_items(job, li)
        except HCPError:
            pass  # some jobs may not have line items yet

        # Optional: invoices (for discount)
        try:
            inv = client.get(invoices_tmpl.replace("{id}", str(jid)))
            apply_invoice(job, inv)
        except HCPError:
            pass

        jobs.append(job)
    return jobs
