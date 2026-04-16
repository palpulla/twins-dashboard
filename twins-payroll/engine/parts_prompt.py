"""Interactive parts-entry prompt.

The engine module exposes a pure `collect_parts_for_job` that accepts an
IO protocol. Production uses `PromptToolkitIO`; tests use `ScriptedIO`.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import Optional, Protocol

from engine.models import Job, JobPart
from engine.price_sheet import PriceSheet


class IOProtocol(Protocol):
    def write(self, text: str) -> None: ...
    def read(self, prompt: str) -> str: ...


@dataclass
class ScriptedIO:
    """Test double. Feeds queued inputs; captures outputs."""
    inputs: list[str] = field(default_factory=list)
    outputs: list[str] = field(default_factory=list)

    def write(self, text: str) -> None:
        self.outputs.append(text)

    def read(self, prompt: str) -> str:
        if not self.inputs:
            raise RuntimeError(f"ScriptedIO exhausted, prompt was: {prompt!r}")
        return self.inputs.pop(0)


@dataclass
class PartEntry:
    part_name: str
    quantity: int
    unit_price: float


def _format_job_header(job: Job) -> str:
    owner = job.owner_tech or "?"
    listed = ", ".join(job.raw_techs) or "(none)"
    lines = [
        "─" * 60,
        f"Job #{job.hcp_job_number} — {job.job_date.isoformat()} — {job.customer_display}",
        f"Amount: ${job.amount:,.2f}   Tip: ${job.tip:,.2f}   Owner: {owner}",
        f"Listed techs: {listed}",
        "",
        f"HCP Description: {job.description or '(none)'}",
        "",
        "HCP Line Items:",
        *(f"  {line}" for line in (job.line_items_text.splitlines() or ["(none)"])),
        "",
        "Tech notes (from HCP):",
        "  ───",
    ]
    if job.notes_text.strip():
        for ln in job.notes_text.splitlines():
            lines.append(f"  {ln}")
    else:
        lines.append("  (no tech notes on ticket)")
    lines.append("  ───")
    return "\n".join(lines)


def collect_parts_for_job(
    job: Job,
    price_sheet: PriceSheet,
    *,
    io: IOProtocol,
) -> tuple[list[JobPart], Optional[str]]:
    """Walk the operator through one job. Returns (parts, skip_reason)."""
    io.write(_format_job_header(job))
    io.write("\nEnter parts used (blank line to finish, 's' to skip job, 'q' to quit):")
    parts: list[JobPart] = []

    while True:
        raw = io.read("  part > ").strip()
        if raw == "":
            return parts, None
        if raw.lower() == "s":
            return [], "user_skip"
        if raw.lower() == "q":
            return [], "user_quit"

        try:
            pp = price_sheet.get(raw)
        except KeyError:
            suggestions = price_sheet.search(raw, limit=5)
            io.write(f"  no match for {raw!r}.")
            if suggestions:
                io.write("  did you mean:")
                for s in suggestions:
                    io.write(f"    {s.name}   ${s.total:,.2f}")
            continue

        qty_raw = io.read("  qty > ").strip()
        if qty_raw == "":
            qty = 1
        else:
            try:
                qty = int(qty_raw)
                if qty <= 0:
                    raise ValueError
            except ValueError:
                io.write(f"  bad quantity {qty_raw!r}; entry cancelled")
                continue

        parts.append(JobPart(part_name=pp.name, quantity=qty, unit_price=pp.total))
        io.write(f"  added: {pp.name} × {qty} @ ${pp.total:,.2f} = ${qty * pp.total:,.2f}")
