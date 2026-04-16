"""Pure commission math. No I/O, no config loading, no DB."""
from __future__ import annotations

from engine.models import CommissionRow, Job, StepTierConfig, TechsConfig


def compute_step_bonus(basis: float, tier: StepTierConfig) -> float:
    if basis < tier.band_start:
        return 0.0
    bands_above_start = int((basis - tier.band_start) // tier.band_width)
    return float(tier.bonus_start + bands_above_start * tier.bonus_step)


def compute_commissions(
    job: Job,
    techs: TechsConfig,
    bonus_tiers: dict[str, StepTierConfig],
) -> list[CommissionRow]:
    if job.owner_tech is None:
        raise ValueError(f"Job {job.hcp_job_number} has no owner_tech")
    if job.skip_reason:
        return []

    parts_cost = round(sum(p.total for p in job.parts), 2)
    basis = max(0.0, round(job.amount - job.tip - parts_cost, 2))

    owner = techs.get(job.owner_tech)
    primary_comm = round(basis * owner.commission_pct, 2)
    bonus = 0.0
    if owner.bonus_tier:
        bonus = compute_step_bonus(basis, bonus_tiers[owner.bonus_tier])

    rows: list[CommissionRow] = [
        CommissionRow(
            tech_name=owner.name,
            kind="primary",
            basis=basis,
            commission_pct=owner.commission_pct,
            commission_amt=primary_comm,
            bonus_amt=bonus,
            override_amt=0.0,
            tip_amt=round(job.tip, 2),
        )
    ]

    supervisor = techs.supervisor
    if owner.name != supervisor and supervisor in job.raw_techs:
        sup = techs.get(supervisor)
        override_amt = round(basis * sup.override_on_others_pct, 2)
        rows.append(CommissionRow(
            tech_name=supervisor,
            kind="override",
            basis=basis,
            commission_pct=sup.override_on_others_pct,
            commission_amt=0.0,
            bonus_amt=0.0,
            override_amt=override_amt,
            tip_amt=0.0,
        ))

    return rows
