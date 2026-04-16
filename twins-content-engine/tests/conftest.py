"""Shared pytest fixtures for content engine tests."""
from __future__ import annotations

import sqlite3
from pathlib import Path
from typing import Callable

import pytest


@pytest.fixture
def tmp_db_path(tmp_path: Path) -> Path:
    """Temp SQLite path for a single test."""
    return tmp_path / "test.db"


@pytest.fixture
def sample_brand_yaml(tmp_path: Path) -> Path:
    """Minimal valid brand.yaml for tests."""
    p = tmp_path / "brand.yaml"
    p.write_text(
        "business_name: Twins Garage Doors\n"
        "phone: \"(608) 555-0199\"\n"
        "primary_service_area: madison_wi\n"
        "allow_emojis: false\n"
    )
    return p


@pytest.fixture
def sample_service_area_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "service_area.yaml"
    p.write_text(
        "primary: madison_wi\n"
        "towns:\n"
        "  - madison_wi\n"
        "  - middleton_wi\n"
        "  - sun_prairie_wi\n"
        "  - verona_wi\n"
        "  - fitchburg_wi\n"
        "  - waunakee_wi\n"
        "  - mcfarland_wi\n"
        "  - stoughton_wi\n"
        "  - cottage_grove_wi\n"
        "town_display_names:\n"
        "  madison_wi: Madison\n"
        "  middleton_wi: Middleton\n"
        "  sun_prairie_wi: Sun Prairie\n"
        "  verona_wi: Verona\n"
        "  fitchburg_wi: Fitchburg\n"
        "  waunakee_wi: Waunakee\n"
        "  mcfarland_wi: McFarland\n"
        "  stoughton_wi: Stoughton\n"
        "  cottage_grove_wi: Cottage Grove\n"
    )
    return p
