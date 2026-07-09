"""Month-window persistence for the Instagram AI-fraction cap."""
from __future__ import annotations

import datetime as dt
import json
from pathlib import Path


def _month_key(day: dt.date) -> str:
    return day.strftime("%Y-%m")


def load_month_state(path: Path, today: dt.date) -> dict:
    key = _month_key(today)
    if path.exists():
        state = json.loads(path.read_text())
        if state.get("month") == key:
            return state
    return {"month": key, "ai_used": 0, "total": 0}


def save_month_state(path: Path, state: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(state, indent=2))
