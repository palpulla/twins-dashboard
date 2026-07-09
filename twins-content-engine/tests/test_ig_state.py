# tests/test_ig_state.py
import datetime as dt
from engine.ig_state import load_month_state, save_month_state


def test_state_roundtrip_and_month_rollover(tmp_path):
    p = tmp_path / "ig_month_state.json"
    s = load_month_state(p, dt.date(2026, 7, 6))
    assert s == {"month": "2026-07", "ai_used": 0, "total": 0}
    save_month_state(p, {"month": "2026-07", "ai_used": 2, "total": 5})
    s2 = load_month_state(p, dt.date(2026, 7, 20))
    assert s2["ai_used"] == 2 and s2["total"] == 5
    # new month resets
    s3 = load_month_state(p, dt.date(2026, 8, 3))
    assert s3 == {"month": "2026-08", "ai_used": 0, "total": 0}
