"""Shared pytest fixtures for twins-payroll tests."""
from pathlib import Path

import pytest


@pytest.fixture
def fixtures_dir() -> Path:
    """Return the path to the shared fixtures directory."""
    return Path(__file__).parent / "fixtures"


@pytest.fixture
def tmp_db(tmp_path) -> Path:
    """Return a temp path for a SQLite DB in a test."""
    return tmp_path / "test_payroll.db"
