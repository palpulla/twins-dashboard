"""Orchestrate fetching a week's jobs + details from HCP."""
from __future__ import annotations

from datetime import date, datetime, time

from engine.config_loader import HCPConfig
from engine.hcp_client import HCPClient
from engine.hcp_normalize import normalize_job
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
    """Fetch the list of completed jobs for a week, then fetch each job's detail, then normalize."""
    list_path = hcp_cfg.endpoints.get("jobs_list", "/jobs")
    detail_tmpl = hcp_cfg.endpoints.get("job_detail", "/jobs/{id}")
    work_status = hcp_cfg.filters.get("work_status", "completed")
    cursor_param = hcp_cfg.pagination.get("cursor_param", "cursor")
    page_size = hcp_cfg.pagination.get("page_size", 100)

    list_params = {
        "scheduled_start_min": _iso_day_start(week_start),
        "scheduled_start_max": _iso_day_end(week_end),
        "work_status": work_status,
        "per_page": page_size,
    }

    items = list(client.paginate(
        list_path,
        cursor_param=cursor_param,
        next_path=["next_cursor"],
        items_path=["data"],
        params=list_params,
    ))

    jobs: list[Job] = []
    for item in items:
        jid = item.get("id")
        if not jid:
            continue
        detail = client.get(detail_tmpl.replace("{id}", str(jid)))
        jobs.append(normalize_job(detail, hcp_cfg))
    return jobs
