"""Phase 0 retirement guard on scripts/publish_to_ghl.py.

This script has its OWN write path (`ghl_post` -> `session.post`), independent
of `engine.ghl_social.create_social_post`. Guarding only that function left this
script able to write draft posts to GHL. These tests pin the guard in place.

`--dry-run` must keep working: inspecting what would be staged writes nothing
and stays useful after the retirement.
"""
from __future__ import annotations

import sys

import pytest

from scripts.publish_to_ghl import main


@pytest.fixture(autouse=True)
def _no_network(monkeypatch):
    """Any HTTP call from this module is a test failure, not a network call."""

    def _boom(*args, **kwargs):
        raise AssertionError("publish_to_ghl attempted an HTTP request")

    monkeypatch.setattr("scripts.publish_to_ghl.requests.post", _boom)
    monkeypatch.setattr("scripts.publish_to_ghl.requests.Session.post", _boom)
    monkeypatch.setattr("scripts.publish_to_ghl.requests.Session.get", _boom)


def _run(monkeypatch, argv: list[str]) -> None:
    monkeypatch.setattr(sys, "argv", ["publish_to_ghl.py", *argv])
    main()


def test_write_path_is_retired_by_default(monkeypatch):
    monkeypatch.delenv("GHL_SOCIAL_WRITES_ENABLED", raising=False)
    monkeypatch.setenv("GHL_API_TOKEN", "pit-abc123")
    monkeypatch.setenv("GHL_LOCATION_ID", "loc-xyz")

    with pytest.raises(RuntimeError, match="retired"):
        _run(monkeypatch, [])


def test_retirement_cannot_be_bypassed_by_truthy_values(monkeypatch):
    """Fails closed — only the exact string "true" re-enables writes."""
    monkeypatch.setenv("GHL_API_TOKEN", "pit-abc123")
    monkeypatch.setenv("GHL_LOCATION_ID", "loc-xyz")

    for value in ("1", "yes", "TRUE", "True", "", " true", "false"):
        monkeypatch.setenv("GHL_SOCIAL_WRITES_ENABLED", value)
        with pytest.raises(RuntimeError, match="retired"):
            _run(monkeypatch, [])


def test_guard_fires_before_the_token_check(monkeypatch):
    """Retirement must not be maskable by a missing-token SystemExit.

    The pre-existing token check calls sys.exit(1). If the guard ran after it,
    an operator with no token would see "token not set" and conclude the script
    still works once configured.
    """
    monkeypatch.delenv("GHL_SOCIAL_WRITES_ENABLED", raising=False)
    monkeypatch.delenv("GHL_API_TOKEN", raising=False)
    monkeypatch.delenv("GHL_LOCATION_ID", raising=False)

    with pytest.raises(RuntimeError, match="retired"):
        _run(monkeypatch, [])


def test_dry_run_still_works_and_makes_no_request(monkeypatch, tmp_path, capsys):
    """--dry-run is deliberately still allowed after the retirement."""
    monkeypatch.delenv("GHL_SOCIAL_WRITES_ENABLED", raising=False)
    approved = tmp_path / "approved"
    approved.mkdir()

    # No matching files: main() reports and exits 0 without touching GHL.
    with pytest.raises(SystemExit) as exc:
        _run(monkeypatch, ["--dry-run", "--approved", str(approved)])

    assert exc.value.code == 0
    assert "No publishable files matched" in capsys.readouterr().out
