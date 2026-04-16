"""Tests for engine/price_sheet.py"""
from pathlib import Path

import pytest

from engine.price_sheet import PartPrice, PriceSheet, load_price_sheet


@pytest.fixture
def price_xlsx(fixtures_dir) -> Path:
    return fixtures_dir / "parts_sheet_small.xlsx"


def test_load_price_sheet_primary_table(price_xlsx):
    sheet = load_price_sheet(price_xlsx, sheet_name="Pricing")
    assert isinstance(sheet, PriceSheet)
    assert sheet.get(".243 #2 - 30.5\"").total == pytest.approx(45.76)
    assert sheet.get("7' Cables").total == pytest.approx(8.47)
    assert sheet.get("Universal Keypad").total == pytest.approx(47.96)


def test_load_price_sheet_supplemental_numeric_name(price_xlsx):
    sheet = load_price_sheet(price_xlsx, sheet_name="Pricing")
    # Row with numeric part name "98022" should be stringified and included
    assert "98022" in sheet.part_names
    assert sheet.get("98022").total == pytest.approx(671.06)
    assert sheet.get("98022").source == "supplemental"


def test_load_price_sheet_supplemental_sparse_row(price_xlsx):
    sheet = load_price_sheet(price_xlsx, sheet_name="Pricing")
    assert sheet.get("2220L-7").total == pytest.approx(316.36)


def test_get_unknown_part_raises(price_xlsx):
    sheet = load_price_sheet(price_xlsx, sheet_name="Pricing")
    with pytest.raises(KeyError):
        sheet.get("Nonexistent Part")


def test_fuzzy_match_substring(price_xlsx):
    sheet = load_price_sheet(price_xlsx, sheet_name="Pricing")
    matches = sheet.search("cable")
    assert len(matches) == 1
    assert matches[0].name == "7' Cables"


def test_fuzzy_match_prefix_bias(price_xlsx):
    sheet = load_price_sheet(price_xlsx, sheet_name="Pricing")
    matches = sheet.search(".243")
    assert len(matches) == 1
    assert matches[0].name == ".243 #2 - 30.5\""


def test_hash_is_deterministic(price_xlsx):
    h1 = PriceSheet.hash_file(price_xlsx)
    h2 = PriceSheet.hash_file(price_xlsx)
    assert h1 == h2
    assert len(h1) == 64  # sha256 hex
