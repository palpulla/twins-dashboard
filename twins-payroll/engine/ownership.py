"""Resolve the owner tech for a job given its raw tech list + config.

The Charles rule:
- Only Charles listed -> Charles owns.
- Charles + exactly one other -> the other owns (Charles gets a 2% override upstream).
- Charles + 2+ others -> ambiguous; caller must prompt operator.
- No Charles, single other tech -> that tech owns.
- No Charles, 2+ others -> ambiguous.
- Empty list -> ambiguous.
"""
from __future__ import annotations

from engine.models import TechsConfig


class AmbiguousOwnerError(Exception):
    """The rule can't pick a single owner; caller must prompt the operator."""


class UnknownTechError(Exception):
    """A tech name in raw_techs is not in the configured roster."""


def resolve_owner(raw_techs: list[str], techs: TechsConfig) -> str:
    cleaned = [t.strip() for t in raw_techs if t and t.strip()]
    known = set(techs.names)

    unknown = [t for t in cleaned if t not in known]
    if unknown:
        raise UnknownTechError(f"Unknown techs: {unknown}. Known: {sorted(known)}")

    if not cleaned:
        raise AmbiguousOwnerError("Job has no assigned techs")

    supervisor = techs.supervisor
    if len(cleaned) == 1:
        return cleaned[0]

    non_sup = [t for t in cleaned if t != supervisor]
    if supervisor in cleaned:
        if len(non_sup) == 1:
            return non_sup[0]
        if len(non_sup) == 0:
            # Multiple entries all equal to Charles — treat as solo
            return supervisor
        raise AmbiguousOwnerError(
            f"Supervisor co-listed with multiple techs: {non_sup}"
        )
    # No supervisor in list, multiple techs
    raise AmbiguousOwnerError(f"Multiple non-supervisor techs, no supervisor: {non_sup}")
