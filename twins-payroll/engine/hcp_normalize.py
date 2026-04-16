"""Normalize HCP API job JSON into the tool's Job dataclass.

Field names here are the *design's* expected names. The first real API call
during implementation will confirm them; any mismatches are fixed by updating
this module and its tests, not downstream code.
"""
from __future__ import annotations

from datetime import date, datetime
from typing import Any

from engine.config_loader import HCPConfig
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


def _render_line_items(items: list[dict[str, Any]]) -> str:
    services = [i for i in items if (i.get("kind") or "").lower() == "service"]
    materials = [i for i in items if (i.get("kind") or "").lower() == "material"]
    others = [i for i in items if i not in services and i not in materials]
    out_lines: list[str] = []
    if services:
        out_lines.append("SERVICES")
        for s in services:
            out_lines.append(_fmt_line(s))
    if materials:
        out_lines.append("MATERIALS")
        for m in materials:
            out_lines.append(_fmt_line(m))
    for o in others:
        out_lines.append(_fmt_line(o))
    return "\n".join(out_lines)


def _fmt_line(item: dict[str, Any]) -> str:
    name = item.get("name") or item.get("description") or "(unnamed)"
    unit = float(item.get("unit_price") or 0)
    qty = item.get("quantity", 1) or 1
    total = unit * qty
    prefix = f"  {name}"
    if qty and qty != 1:
        prefix = f"  {qty}x {name}"
    return f"{prefix} - ${total:,.2f}"


def _f(x: Any) -> float:
    if x is None or x == "":
        return 0.0
    return float(x)


def normalize_job(raw: dict[str, Any], hcp_cfg: HCPConfig) -> Job:
    employees = [
        EmployeeRef(hcp_id=str(e.get("id", "")), display_name=_display_name(e))
        for e in (raw.get("assigned_employees") or [])
    ]
    raw_techs = [e.display_name for e in employees]

    notes_parts: list[str] = []
    for field in hcp_cfg.notes_fields:
        val = raw.get(field)
        if val:
            notes_parts.append(str(val).strip())
    notes_text = "\n\n".join(notes_parts)

    return Job(
        hcp_id=str(raw.get("id", "")),
        hcp_job_number=str(raw.get("invoice_number") or raw.get("id") or ""),
        job_date=_parse_date(raw.get("scheduled_start")),
        customer_display=_customer_display(raw.get("customer")),
        description=(raw.get("description") or "").strip(),
        line_items_text=_render_line_items(raw.get("line_items") or []),
        notes_text=notes_text,
        amount=_f(raw.get("total_amount")),
        tip=_f(raw.get("tip")),
        subtotal=_f(raw.get("subtotal")),
        labor=_f(raw.get("labor_total")),
        materials_charged=_f(raw.get("materials_total")),
        cc_fee=_f(raw.get("cc_fee")),
        discount=_f(raw.get("discount")),
        assigned_employees=employees,
        raw_techs=raw_techs,
    )
