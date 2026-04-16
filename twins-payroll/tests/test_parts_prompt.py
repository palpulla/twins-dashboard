"""Tests for engine/parts_prompt.py with a scripted reader."""
from datetime import date

import pytest

from engine.models import EmployeeRef, Job, JobPart
from engine.parts_prompt import PartEntry, ScriptedIO, collect_parts_for_job
from engine.price_sheet import load_price_sheet


@pytest.fixture
def price_sheet(fixtures_dir):
    return load_price_sheet(fixtures_dir / "parts_sheet_small.xlsx")


def _mk_job():
    return Job(
        hcp_id="h1", hcp_job_number="14404", job_date=date(2026, 4, 14),
        customer_display="Sarah J.", description="Labor",
        line_items_text="SERVICES: Labor", notes_text="2x .243 #2 30.5\" springs, 2 drums",
        amount=1687.0, tip=0.0, subtotal=0.0, labor=0.0, materials_charged=0.0,
        cc_fee=0.0, discount=0.0,
        assigned_employees=[EmployeeRef("e1", "Maurice")], raw_techs=["Maurice"],
        owner_tech="Maurice",
    )


def test_user_enters_two_parts_then_blank_line_ends(price_sheet):
    io = ScriptedIO(inputs=[
        ".243 #2 - 30.5\"",
        "2",
        "Drum",
        "2",
        "",
    ])
    parts, skip = collect_parts_for_job(_mk_job(), price_sheet, io=io)
    assert skip is None
    assert len(parts) == 2
    assert parts[0].part_name == ".243 #2 - 30.5\""
    assert parts[0].quantity == 2
    assert parts[1].part_name == "Drum"


def test_unknown_part_rejected_then_retry(price_sheet):
    io = ScriptedIO(inputs=[
        "nonsense_part",
        "Drum",
        "1",
        "",
    ])
    parts, skip = collect_parts_for_job(_mk_job(), price_sheet, io=io)
    assert len(parts) == 1
    assert parts[0].part_name == "Drum"
    assert any("no match" in line.lower() or "not found" in line.lower() for line in io.outputs)


def test_skip_job(price_sheet):
    io = ScriptedIO(inputs=["s"])
    parts, skip = collect_parts_for_job(_mk_job(), price_sheet, io=io)
    assert skip == "user_skip"
    assert parts == []


def test_quit_returns_sentinel(price_sheet):
    io = ScriptedIO(inputs=["q"])
    parts, skip = collect_parts_for_job(_mk_job(), price_sheet, io=io)
    assert skip == "user_quit"
    assert parts == []


def test_quantity_defaults_to_one_on_blank(price_sheet):
    io = ScriptedIO(inputs=["Drum", "", ""])
    parts, _ = collect_parts_for_job(_mk_job(), price_sheet, io=io)
    assert parts[0].quantity == 1


def test_ambiguous_substring_lists_matches_and_reprompts(price_sheet):
    io = ScriptedIO(inputs=["7' Cables", "2", ""])
    parts, _ = collect_parts_for_job(_mk_job(), price_sheet, io=io)
    assert parts[0].part_name == "7' Cables"
    assert parts[0].unit_price == pytest.approx(8.47)
