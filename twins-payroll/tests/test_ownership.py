"""Tests for engine/ownership.py — the Charles rule."""
import pytest

from engine.models import TechConfig, TechsConfig
from engine.ownership import (
    AmbiguousOwnerError,
    UnknownTechError,
    resolve_owner,
)


@pytest.fixture
def techs():
    return TechsConfig(
        supervisor="Charles Rue",
        techs=[
            TechConfig(name="Charles Rue", commission_pct=0.20,
                       bonus_tier="step_tiers_charles", override_on_others_pct=0.02),
            TechConfig(name="Maurice", commission_pct=0.20),
            TechConfig(name="Nicholas Roccaforte", commission_pct=0.18),
        ],
    )


def test_solo_charles_owns(techs):
    assert resolve_owner(["Charles Rue"], techs) == "Charles Rue"


def test_charles_plus_one_other_other_owns(techs):
    assert resolve_owner(["Charles Rue", "Maurice"], techs) == "Maurice"
    assert resolve_owner(["Nicholas Roccaforte", "Charles Rue"], techs) == "Nicholas Roccaforte"


def test_solo_non_charles_owns(techs):
    assert resolve_owner(["Maurice"], techs) == "Maurice"


def test_charles_plus_two_others_ambiguous(techs):
    with pytest.raises(AmbiguousOwnerError):
        resolve_owner(["Charles Rue", "Maurice", "Nicholas Roccaforte"], techs)


def test_two_non_charles_techs_ambiguous(techs):
    with pytest.raises(AmbiguousOwnerError):
        resolve_owner(["Maurice", "Nicholas Roccaforte"], techs)


def test_empty_techs_raises(techs):
    with pytest.raises(AmbiguousOwnerError):
        resolve_owner([], techs)


def test_unknown_tech_raises(techs):
    with pytest.raises(UnknownTechError):
        resolve_owner(["Charles Rue", "Ghost"], techs)


def test_whitespace_trimmed(techs):
    assert resolve_owner(["  Charles Rue  ", "Maurice"], techs) == "Maurice"
