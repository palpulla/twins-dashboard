"""Core dataclasses for twins-payroll.

These types are the lingua franca between engine modules. They have no I/O
and no dependencies on config or DB.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from datetime import date
from typing import Literal, Optional


@dataclass
class EmployeeRef:
    """A tech as referenced on a job."""
    hcp_id: str
    display_name: str


@dataclass
class JobPart:
    """A single line of component parts entered for a job."""
    part_name: str
    quantity: int
    unit_price: float
    source: Literal["manual"] = "manual"

    @property
    def total(self) -> float:
        return round(self.quantity * self.unit_price, 2)


@dataclass
class Job:
    """A normalized job pulled from the HCP API for a single run."""
    hcp_id: str
    hcp_job_number: str
    job_date: date
    customer_display: str
    description: str
    line_items_text: str
    notes_text: str
    amount: float
    tip: float
    subtotal: float
    labor: float
    materials_charged: float
    cc_fee: float
    discount: float
    assigned_employees: list[EmployeeRef]
    raw_techs: list[str]
    owner_tech: Optional[str] = None
    skip_reason: Optional[str] = None
    parts: list[JobPart] = field(default_factory=list)


@dataclass
class StepTierConfig:
    """Step-tiered bonus configuration (applied per-job)."""
    band_width: int
    band_start: int
    bonus_start: int
    bonus_step: int


@dataclass
class TechConfig:
    """A single tech's payroll configuration."""
    name: str
    commission_pct: float
    bonus_tier: Optional[str] = None
    override_on_others_pct: float = 0.0
    hcp_employee_id: Optional[str] = None


@dataclass
class TechsConfig:
    """Full tech roster + supervisor designation."""
    supervisor: str
    techs: list[TechConfig]

    def get(self, name: str) -> TechConfig:
        for t in self.techs:
            if t.name == name:
                return t
        raise KeyError(f"Tech not in roster: {name!r}")

    @property
    def names(self) -> list[str]:
        return [t.name for t in self.techs]


@dataclass
class CommissionRow:
    """One ledger row — a tech's earnings contribution from a single job."""
    tech_name: str
    kind: Literal["primary", "override"]
    basis: float
    commission_pct: float
    commission_amt: float
    bonus_amt: float
    override_amt: float
    tip_amt: float

    @property
    def total(self) -> float:
        return round(self.commission_amt + self.bonus_amt + self.override_amt + self.tip_amt, 2)
