"""Two-week Instagram slot rotation (Mon/Wed/Fri)."""
from __future__ import annotations

import datetime as dt
from dataclasses import dataclass

_WEEKDAY_SLOT = {0: "proof", 2: "value", 4: "friday"}   # Mon, Wed, Fri
_WEEKDAY_FORMAT = {0: "carousel", 2: "reel", 4: "static"}


@dataclass(frozen=True)
class SlotSpec:
    date: str
    week: int              # 1 or 2 within the cycle
    slot: str              # proof | value | offer | flex
    format_target: str     # reel | carousel | static
    allow_recruiting: bool


def _cycle_week(target: dt.date, anchor: dt.date) -> int:
    days = (target - anchor).days
    week_index = (days // 7) % 2
    return 1 if week_index == 0 else 2


def plan_slot(target: dt.date, cycle_anchor: str, hiring: bool) -> SlotSpec:
    anchor = dt.date.fromisoformat(cycle_anchor)
    weekday = target.weekday()
    if weekday not in _WEEKDAY_SLOT:
        raise ValueError(f"{target} is not a posting day (Mon/Wed/Fri only)")
    week = _cycle_week(target, anchor)
    base = _WEEKDAY_SLOT[weekday]
    if base == "friday":
        slot = "offer" if week == 1 else "flex"
    else:
        slot = base
    return SlotSpec(
        date=target.isoformat(),
        week=week,
        slot=slot,
        format_target=_WEEKDAY_FORMAT[weekday],
        allow_recruiting=(slot == "flex" and hiring),
    )
