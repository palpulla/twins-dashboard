"""Normalize HCP API job JSON into the tool's Job dataclass.

Adapted to the real HCP API shape confirmed 2026-04-17:
- total_amount/subtotal are in cents (integer)
- schedule.scheduled_start is nested under 'schedule'
- notes is an array of {id, content} objects
- line_items come from a separate /jobs/{id}/line_items endpoint
- invoices (discounts) come from a separate /jobs/{id}/invoices endpoint
- tip is not in the API; operator enters manually
"""
from __future__ import annotations

from datetime import date, datetime
from typing import Any

from engine.models import EmployeeRef, Job


def _display_name(emp: dict[str, Any]) -> str:
    first = (emp.get("first_name") or "").strip()
    last = (emp.get("last_name") or "").strip()
    full = f"{first} {last}".strip()
    return full or emp.get("name") or emp.get("id") or "Unknown"


def _customer_display(customer: dict[str, Any] | None) -> str:
    if not customer:
        return ""
    first = (customer.get("first_name") or "").strip()
    last = (customer.get("last_name") or "").strip()
    return (f"{first} {last}".strip()
            or customer.get("name")
            or customer.get("company")
            or "")


def _parse_date(iso: str | None) -> date:
    if not iso:
        return date.min
    try:
        return datetime.fromisoformat(iso.replace("Z", "+00:00")).date()
    except ValueError:
        return datetime.strptime(iso[:10], "%Y-%m-%d").date()


def _f(x: Any) -> float:
    if x is None or x == "":
        return 0.0
    return float(x)


def normalize_job(raw: dict[str, Any]) -> Job:
    employees = [
        EmployeeRef(hcp_id=str(e.get("id", "")), display_name=_display_name(e))
        for e in (raw.get("assigned_employees") or [])
    ]
    raw_techs = [e.display_name for e in employees]

    notes_text = "\n".join(
        str(n.get("content", "")).strip()
        for n in (raw.get("notes") or [])
        if n.get("content")
    )

    schedule = raw.get("schedule") or {}
    scheduled_start = schedule.get("scheduled_start")

    return Job(
        hcp_id=str(raw.get("id", "")),
        hcp_job_number=str(raw.get("invoice_number") or raw.get("id") or ""),
        job_date=_parse_date(scheduled_start),
        customer_display=_customer_display(raw.get("customer")),
        description=(raw.get("description") or "").strip(),
        line_items_text="",  # populated later by apply_line_items
        notes_text=notes_text,
        amount=_f(raw.get("total_amount")) / 100.0,
        tip=0.0,  # Not in API; operator enters manually during walkthrough
        subtotal=_f(raw.get("subtotal")) / 100.0,
        labor=0.0,
        materials_charged=0.0,
        cc_fee=0.0,
        discount=0.0,  # populated later by apply_invoice
        assigned_employees=employees,
        raw_techs=raw_techs,
    )


def apply_line_items(job: Job, line_items_resp: dict[str, Any]) -> None:
    """Populate job.line_items_text from a /jobs/{id}/line_items response (in place)."""
    items = (line_items_resp or {}).get("data", []) or []
    services = [i for i in items if (i.get("kind") or "").lower() in ("service", "labor")]
    materials = [i for i in items if (i.get("kind") or "").lower() in ("material", "materials")]
    others = [i for i in items if i not in services and i not in materials]
    lines: list[str] = []
    if services:
        lines.append("SERVICES")
        for s in services:
            lines.append(_fmt_line_item(s))
    if materials:
        lines.append("MATERIALS")
        for m in materials:
            lines.append(_fmt_line_item(m))
    for o in others:
        lines.append(_fmt_line_item(o))
    job.line_items_text = "\n".join(lines)


def _fmt_line_item(item: dict[str, Any]) -> str:
    name = item.get("name") or item.get("description") or "(unnamed)"
    unit_cents = item.get("unit_price") or 0
    qty = item.get("quantity") or 1
    amount_cents = item.get("amount") or (unit_cents * qty)
    dollars = amount_cents / 100.0
    prefix = f"  {name}"
    if qty and qty != 1:
        prefix = f"  {qty}x {name}"
    return f"{prefix} - ${dollars:,.2f}"


def apply_invoice(job: Job, invoices_resp: dict[str, Any]) -> None:
    """Populate job.discount and job.amount from invoice data (cents -> dollars)."""
    invs = (invoices_resp or {}).get("invoices", []) or []
    if not invs:
        return
    # Use the first invoice
    inv = invs[0]
    discounts = inv.get("discounts") or []
    total_disc_cents = sum(abs(d.get("amount", 0)) for d in discounts)
    job.discount = total_disc_cents / 100.0
