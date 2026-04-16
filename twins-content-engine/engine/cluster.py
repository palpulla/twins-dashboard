"""Seed parsing and cluster expansion.

This module implements:
- parse_seed_file: parse a seed Markdown file into ParsedSeed records.
- expand_seeds_to_clusters: LLM-driven expansion of _unclustered_* placeholder rows
  into real clusters with 3–5 phrasing variants each.
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


import json
import sqlite3
from typing import Any

from engine.claude_client import ClaudeClientProtocol
from engine.db import (
    get_conn,
    get_cluster_by_name,
    get_queries_for_cluster,
    insert_query,
)


EXPANSION_SYSTEM_PROMPT = """You are building a real-homeowner intent database for a garage door repair company in Madison, Wisconsin.

Given ONE seed query (from an actual customer call, text, or Google search), do two things:

1. Identify the natural cluster it belongs to. A cluster is a semantic group — all queries about "broken spring" belong to one cluster regardless of phrasing. Use snake_case names like "broken_spring", "door_wont_close", "new_door_cost", "opener_noise", "winter_prep".

2. Produce 3–5 real-world phrasing variants of the same problem. These must sound like stressed, rushed homeowners — NOT like clean SEO queries. Use these phrasing_type labels:
   - emergency: panic/urgent phrasing
   - price_anxiety: cost-focused worry ("is this going to be expensive")
   - question: explicit question form ("is it safe if...")
   - symptom: describing the symptom, not the cause ("loud bang and won't open")
   - local_variant: includes a town from the provided list

Constraints:
- Every variant must be plausibly typed by a non-technical Madison-area homeowner in 2026.
- Do NOT sanitize — if real homeowners type "garage door broke spring loud boom" that's better than "broken garage door torsion spring replacement".
- Variants must differ meaningfully (don't just reword with synonyms).
- geo_modifier is one of the provided town slugs, or null. Prefer null unless the phrasing truly includes a place.

Return ONLY a JSON object with this exact shape — no surrounding prose, no code fences:

{
  "cluster_name": "<snake_case>",
  "service_type": "<snake_case or null>",
  "funnel_stage": "<one of: high_intent_problem_aware | mid_intent_considering | mid_intent_price_checking | mid_intent_deciding | mid_intent_trust_building | low_intent_educational | trust_and_ranking>",
  "priority_score": <1-10 integer>,
  "variants": [
    {"query_text": "...", "phrasing_type": "emergency|price_anxiety|question|symptom|local_variant", "geo_modifier": "madison_wi|middleton_wi|...|null"}
  ]
}
"""


def _build_user_prompt(seed_query: str, pillar_hint: str | None, towns: list[str]) -> str:
    hint_line = f"Pillar hint from operator: {pillar_hint}\n" if pillar_hint else ""
    return (
        f"Seed query: {seed_query!r}\n"
        f"{hint_line}"
        f"Available town slugs: {', '.join(towns)}\n"
    )


def _safe_parse(raw: str) -> dict[str, Any] | None:
    # Strip common LLM-wrapping patterns (markdown fences) before parsing.
    stripped = raw.strip()
    if stripped.startswith("```"):
        stripped = stripped.removeprefix("```json").removeprefix("```").strip()
        if stripped.endswith("```"):
            stripped = stripped.removesuffix("```").strip()
    try:
        return json.loads(stripped)
    except json.JSONDecodeError as exc:
        print(
            f"[warn] _safe_parse: JSON decode failed ({exc}); "
            f"raw prefix: {raw[:200]!r}",
            flush=True,
        )
        return None


def expand_seeds_to_clusters(
    *,
    db_path: Path,
    client: ClaudeClientProtocol,
    towns: list[str],
) -> None:
    """For every placeholder `_unclustered_*` row, call Claude to propose a real
    cluster + variants. Merge into existing clusters by name.
    """
    with get_conn(db_path) as c:
        rows = list(
            c.execute(
                r"SELECT id, name, pillar FROM clusters "
                r"WHERE name LIKE '\_unclustered\_%' ESCAPE '\'"
            ).fetchall()
        )

    total = len(rows)
    if total == 0:
        print("[info] no placeholder clusters to expand.", flush=True)
        return

    for idx, row in enumerate(rows, start=1):
        placeholder_id = row["id"]
        placeholder_pillar = row["pillar"]
        seed_queries = get_queries_for_cluster(db_path, placeholder_id)
        if not seed_queries:
            continue
        seed_text = seed_queries[0]["query_text"]

        print(
            f"[{idx}/{total}] expanding seed id={placeholder_id} "
            f"({seed_text[:40]!r})...",
            flush=True,
        )

        user_prompt = _build_user_prompt(
            seed_query=seed_text,
            pillar_hint=placeholder_pillar if placeholder_pillar != "unclustered" else None,
            towns=towns,
        )
        try:
            result = client.complete(
                system=EXPANSION_SYSTEM_PROMPT,
                user=user_prompt,
                max_tokens=1500,
                temperature=0.7,
            )
        except Exception as exc:  # noqa: BLE001 — log and continue so other seeds still expand
            print(
                f"[warn] seed id={placeholder_id} "
                f"({seed_text[:40]!r}): API error — {exc}",
                flush=True,
            )
            continue
        payload = _safe_parse(result.text)
        if payload is None:
            # leave placeholder in place; operator can regenerate
            continue

        cluster_name = payload.get("cluster_name")
        if not isinstance(cluster_name, str) or not cluster_name:
            continue

        existing = get_cluster_by_name(db_path, cluster_name)
        if existing is not None:
            target_id = existing["id"]
            # move the raw_seed query onto the existing cluster; delete placeholder
            with get_conn(db_path) as c:
                c.execute(
                    "UPDATE queries SET cluster_id = ? WHERE cluster_id = ?",
                    (target_id, placeholder_id),
                )
                c.execute("DELETE FROM clusters WHERE id = ?", (placeholder_id,))
        else:
            # rename placeholder to real cluster name + backfill fields
            with get_conn(db_path) as c:
                c.execute(
                    "UPDATE clusters SET name = ?, pillar = ?, service_type = ?, "
                    "funnel_stage = ?, priority_score = ?, notes = NULL, "
                    "updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    (
                        cluster_name,
                        placeholder_pillar if placeholder_pillar != "unclustered"
                        else _pillar_for_funnel(payload.get("funnel_stage", "")),
                        payload.get("service_type"),
                        payload.get("funnel_stage"),
                        int(payload.get("priority_score") or 5),
                        placeholder_id,
                    ),
                )
            target_id = placeholder_id

        # insert variants
        for variant in payload.get("variants") or []:
            q_text = variant.get("query_text")
            phrasing = variant.get("phrasing_type")
            geo = variant.get("geo_modifier")
            if geo == "null":
                geo = None
            if not q_text or phrasing not in {
                "emergency", "price_anxiety", "question", "symptom", "local_variant"
            }:
                continue
            try:
                insert_query(
                    db_path,
                    cluster_id=target_id,
                    query_text=q_text,
                    phrasing_type=phrasing,
                    geo_modifier=geo,
                    source="llm_expansion",
                    priority_score=5,
                    notes=None,
                )
            except sqlite3.IntegrityError:
                # duplicate (cluster_id, query_text) — silently skip
                continue


_FUNNEL_TO_PILLAR = {
    "high_intent_problem_aware": "emergency_repairs",
    "mid_intent_considering": "replacement_installation",
    "mid_intent_price_checking": "pricing_transparency",
    "mid_intent_deciding": "comparisons",
    "mid_intent_trust_building": "common_mistakes",
    "low_intent_educational": "maintenance_prevention",
    "trust_and_ranking": "local_authority",
}


def _pillar_for_funnel(funnel_stage: str) -> str:
    return _FUNNEL_TO_PILLAR.get(funnel_stage, "maintenance_prevention")
