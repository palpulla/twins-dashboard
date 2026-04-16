"""Harvest inbox parsing + folding into the cluster DB."""
from __future__ import annotations

import json
import sqlite3
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Optional

from engine.claude_client import ClaudeClientProtocol
from engine.db import (
    get_cluster_by_name,
    insert_cluster,
    insert_query,
    list_clusters,
)


@dataclass
class HarvestEntry:
    query_text: str
    source: str


_VALID_SOURCES = {"harvest_inbox", "call", "text", "search_console", "review"}


HARVEST_SYSTEM_PROMPT = """You are categorizing a single raw customer query into a cluster for Twins Garage Doors (Madison, WI).

Given:
- a raw query string (often typo-ridden, rushed, or symptom-based)
- a list of EXISTING cluster names

Decide: does the query belong to one of the existing clusters, or does it warrant a NEW cluster?

Return ONLY JSON, no prose. Either:
  {"cluster_name": "<existing_name>"}
or
  {"cluster_name": "<new_snake_case_name>", "pillar": "<pillar>", "funnel_stage": "<funnel_stage>"}

Pillar must be one of: emergency_repairs, maintenance_prevention, replacement_installation,
pricing_transparency, common_mistakes, comparisons, local_authority.
Funnel stage must be one of: high_intent_problem_aware, mid_intent_considering,
mid_intent_price_checking, mid_intent_deciding, mid_intent_trust_building,
low_intent_educational, trust_and_ranking.
"""


def parse_harvest_inbox(path: Path) -> list[HarvestEntry]:
    entries: list[HarvestEntry] = []
    for raw in path.read_text().splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        source = "harvest_inbox"
        if "|" in line:
            query_part, _, tag_part = line.partition("|")
            line = query_part.strip()
            tag = tag_part.strip()
            if tag.startswith("source="):
                proposed = tag.split("=", 1)[1].strip()
                if proposed in _VALID_SOURCES:
                    source = proposed
        entries.append(HarvestEntry(query_text=line, source=source))
    return entries


def fold_into_db(
    *,
    db_path: Path,
    entries: list[HarvestEntry],
    client: ClaudeClientProtocol,
) -> None:
    for entry in entries:
        existing_names = [c["name"] for c in list_clusters(db_path)]
        user = (
            f"Raw query: {entry.query_text!r}\n"
            f"Existing clusters: {existing_names}\n"
        )
        result = client.complete(
            system=HARVEST_SYSTEM_PROMPT,
            user=user,
            max_tokens=300,
            temperature=0.2,
        )
        payload = _safe_parse(result.text)
        if not payload:
            continue
        cluster_name = payload.get("cluster_name")
        if not isinstance(cluster_name, str) or not cluster_name:
            continue

        existing = get_cluster_by_name(db_path, cluster_name)
        if existing is None:
            cluster_id = insert_cluster(
                db_path,
                name=cluster_name,
                pillar=payload.get("pillar") or "maintenance_prevention",
                service_type=None,
                funnel_stage=payload.get("funnel_stage"),
                priority_score=5,
                notes="auto-created from harvest",
            )
        else:
            cluster_id = int(existing["id"])

        phrasing = "symptom"
        try:
            insert_query(
                db_path,
                cluster_id=cluster_id,
                query_text=entry.query_text,
                phrasing_type=phrasing,
                geo_modifier=None,
                source=entry.source,
                priority_score=5,
                notes=None,
            )
        except sqlite3.IntegrityError:
            continue


def _safe_parse(raw: str) -> Optional[dict[str, Any]]:
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return None
