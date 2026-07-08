import datetime as dt
import pytest
from engine.ig_planner import plan_slot, SlotSpec

ANCHOR = "2026-07-06"  # a Monday


def test_week1_monday_is_proof_carousel():
    spec = plan_slot(dt.date(2026, 7, 6), ANCHOR, hiring=False)
    assert spec.slot == "proof"
    assert spec.week == 1
    assert spec.format_target == "carousel"


def test_week1_friday_is_offer():
    spec = plan_slot(dt.date(2026, 7, 10), ANCHOR, hiring=False)
    assert spec.slot == "offer"
    assert spec.week == 1


def test_week2_friday_is_flex_and_recruiting_gated_by_hiring():
    not_hiring = plan_slot(dt.date(2026, 7, 17), ANCHOR, hiring=False)
    hiring = plan_slot(dt.date(2026, 7, 17), ANCHOR, hiring=True)
    assert not_hiring.slot == "flex"
    assert not_hiring.week == 2
    assert not_hiring.allow_recruiting is False
    assert hiring.allow_recruiting is True


def test_non_posting_day_raises():
    with pytest.raises(ValueError):
        plan_slot(dt.date(2026, 7, 7), ANCHOR, hiring=False)  # Tuesday


def test_week2_monday_is_proof():
    # 2026-07-13 is the Monday of week 2 (7 days after the anchor)
    spec = plan_slot(dt.date(2026, 7, 13), ANCHOR, hiring=False)
    assert spec.week == 2
    assert spec.slot == "proof"
    assert spec.format_target == "carousel"
