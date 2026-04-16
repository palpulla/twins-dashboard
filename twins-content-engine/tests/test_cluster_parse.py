"""Tests for engine.cluster.parse_seed_file."""
from __future__ import annotations

from pathlib import Path

from engine.cluster import ParsedSeed, parse_seed_file


def test_parse_basic_lines(tmp_path: Path):
    f = tmp_path / "seed.md"
    f.write_text(
        "# comments are ignored\n"
        "\n"
        "garage door won't close\n"
        "spring broke loud bang\n"
    )
    seeds = parse_seed_file(f)
    assert len(seeds) == 2
    assert seeds[0] == ParsedSeed(query_text="garage door won't close", pillar_hint=None)
    assert seeds[1].query_text == "spring broke loud bang"


def test_parse_with_pillar_hint(tmp_path: Path):
    f = tmp_path / "seed.md"
    f.write_text("how much to replace spring | pillar=pricing_transparency\n")
    seeds = parse_seed_file(f)
    assert seeds[0].pillar_hint == "pricing_transparency"


def test_parse_strips_whitespace_and_skips_blanks(tmp_path: Path):
    f = tmp_path / "seed.md"
    f.write_text("   \n  garage door stuck   \n\n")
    seeds = parse_seed_file(f)
    assert len(seeds) == 1
    assert seeds[0].query_text == "garage door stuck"


def test_parse_ignores_malformed_hint(tmp_path: Path):
    f = tmp_path / "seed.md"
    f.write_text("foo | not-a-hint\n")
    seeds = parse_seed_file(f)
    assert seeds[0].query_text == "foo"
    assert seeds[0].pillar_hint is None
