"""Seed parsing and cluster expansion.

This module currently implements parse_seed_file.
Cluster expansion (expand_seeds_to_clusters) is added in Task 7.
"""
from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Optional


@dataclass
class ParsedSeed:
    query_text: str
    pillar_hint: Optional[str]


def parse_seed_file(path: Path) -> list[ParsedSeed]:
    """Parse a seed Markdown file into a list of ParsedSeed records.

    Format:
      - one query per line
      - blank lines ignored
      - lines starting with # ignored
      - optional inline tag: "query text | pillar=<pillar_name>"
    """
    seeds: list[ParsedSeed] = []
    for raw in path.read_text().splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        pillar_hint: Optional[str] = None
        if "|" in line:
            query_part, _, tag_part = line.partition("|")
            line = query_part.strip()
            tag = tag_part.strip()
            if tag.startswith("pillar="):
                pillar_hint = tag.split("=", 1)[1].strip() or None
        seeds.append(ParsedSeed(query_text=line, pillar_hint=pillar_hint))
    return seeds
