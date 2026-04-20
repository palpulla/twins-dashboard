"""Multi-format content generator orchestrator."""
from __future__ import annotations

import datetime as dt
import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Optional

from engine.brief import write_brief_for
from engine.claude_client import ClaudeClientProtocol
from engine.config import PillarsConfig
from engine.db import (
    get_cluster_by_name,
    get_queries_for_cluster,
    insert_generated_content,
)
from engine.local_embedding import pick_neighborhood, pick_town_for_piece
from engine.prompts import build_generation_prompt, build_regeneration_prompt
from engine.rules import run_rules


FORMATS = ["video_script", "caption", "gbp_post", "faq", "blog_snippet"]
FORMATS_NEEDING_BRIEF = {"video_script", "gbp_post"}


# When the Claude Code session has other skills loaded (copywriting, ad-creative,
# etc.), the model sometimes prefixes its output with a meta-explanation like
# "Using the X skill's approach, here's the rewrite —\n\n---\n\n<actual content>".
# Strip those deterministically before writing to disk so the rules gate and
# downstream publishers see the real first sentence.
_LEAK_PREAMBLE_RE = re.compile(
    r"^\s*(?:using the|following the|applying the|based on the)\b.{0,400}?\n---\n+",
    re.IGNORECASE | re.DOTALL,
)


def strip_leaked_preamble(body: str) -> str:
    """Drop model meta-commentary before the first '---' if it matches known patterns."""
    cleaned = _LEAK_PREAMBLE_RE.sub("", body, count=1)
    return cleaned.lstrip()


@dataclass
class GeneratedPiece:
    format: str
    content_path: str
    brief_path: Optional[str]
    db_id: int
    status: str


@dataclass
class GenerationResult:
    cluster_name: str
    accepted: list[GeneratedPiece] = field(default_factory=list)
    rejected: list[GeneratedPiece] = field(default_factory=list)


def _slugify(s: str) -> str:
    return re.sub(r"[^a-z0-9]+", "_", s.lower()).strip("_")[:40]


def _pick_representative_query(queries: list[Any]) -> Any:
    """Prefer symptom phrasing for high-intent clusters; fall back to highest priority."""
    by_priority = sorted(queries, key=lambda q: (-q["priority_score"], q["id"]))
    for q in by_priority:
        if q["phrasing_type"] == "symptom":
            return q
    return by_priority[0]


def _write_content_file(
    output_dir: Path, cluster_name: str, content_format: str, body: str, rejected: bool
) -> Path:
    output_dir.mkdir(parents=True, exist_ok=True)
    today = dt.date.today().isoformat()
    prefix = "_REJECTED_" if rejected else ""
    filename = f"{prefix}{today}_{_slugify(cluster_name)}_{content_format}.md"
    path = output_dir / filename
    path.write_text(strip_leaked_preamble(body))
    return path


def _build_rejected_body(body: str, violations_text: str, content_format: str, cluster: str) -> str:
    return (
        f"<!-- REJECTED by rules gate -->\n"
        f"<!-- cluster: {cluster} | format: {content_format} -->\n"
        f"<!-- violations:\n{violations_text}\n-->\n\n"
        f"{body}\n"
    )


def generate_for_cluster(
    *,
    db_path: Path,
    cluster_name: str,
    client: ClaudeClientProtocol,
    ctx: dict[str, Any],
    output_dir: Path,
    brief_dir: Path,
    model_name: str,
) -> GenerationResult:
    """Generate all 5 formats for one cluster. One retry per format on rule failure."""
    cluster = get_cluster_by_name(db_path, cluster_name)
    if cluster is None:
        raise ValueError(f"Cluster not found: {cluster_name!r}")

    queries = get_queries_for_cluster(db_path, cluster["id"])
    if not queries:
        raise ValueError(f"Cluster {cluster_name!r} has no queries")

    representative = _pick_representative_query(queries)

    pillars: PillarsConfig = ctx["pillars"]
    pillar = pillars.pillars.get(cluster["pillar"])
    if pillar is None:
        raise ValueError(f"Unknown pillar: {cluster['pillar']!r}")

    service_area = ctx["service_area"]
    town_slug = pick_town_for_piece(
        query_geo_modifier=representative["geo_modifier"],
        cluster_seed=cluster["id"],
        service_area=service_area,
    )
    town_display = service_area.town_display_names[town_slug]
    neighborhood = pick_neighborhood(cluster_seed=cluster["id"], service_area=service_area)
    funnel_stage = cluster["funnel_stage"] or pillar.funnel_stage

    result = GenerationResult(cluster_name=cluster_name)

    for fmt in FORMATS:
        piece = _generate_single_format(
            db_path=db_path,
            cluster_row=cluster,
            source_query_id=representative["id"],
            content_format=fmt,
            funnel_stage=funnel_stage,
            query_text=representative["query_text"],
            pillar_name=cluster["pillar"],
            town_display=town_display,
            neighborhood=neighborhood,
            client=client,
            ctx=ctx,
            output_dir=output_dir,
            brief_dir=brief_dir,
            model_name=model_name,
        )
        if piece.status == "rejected":
            result.rejected.append(piece)
        else:
            result.accepted.append(piece)
    return result


def _generate_single_format(
    *,
    db_path: Path,
    cluster_row: Any,
    source_query_id: int,
    content_format: str,
    funnel_stage: str,
    query_text: str,
    pillar_name: str,
    town_display: str,
    neighborhood: Optional[str],
    client: ClaudeClientProtocol,
    ctx: dict[str, Any],
    output_dir: Path,
    brief_dir: Path,
    model_name: str,
) -> GeneratedPiece:
    system, user = build_generation_prompt(
        content_format=content_format,
        query_text=query_text,
        cluster_name=cluster_row["name"],
        pillar_name=pillar_name,
        funnel_stage=funnel_stage,
        target_town_display=town_display,
        target_neighborhood=neighborhood,
        ctx=ctx,
    )
    first = client.complete(system=system, user=user, max_tokens=2048, temperature=0.7)
    report = run_rules(content_format=content_format, content=first.text,
                       funnel_stage=funnel_stage, ctx=ctx)

    final_body = first.text
    final_report = report
    if not report.passed:
        system2, user2 = build_regeneration_prompt(
            content_format=content_format,
            previous_output=first.text,
            critique_lines=report.suggestions,
            query_text=query_text,
            cluster_name=cluster_row["name"],
            pillar_name=pillar_name,
            funnel_stage=funnel_stage,
            target_town_display=town_display,
            target_neighborhood=neighborhood,
            ctx=ctx,
        )
        retry = client.complete(system=system2, user=user2, max_tokens=2048, temperature=0.7)
        retry_report = run_rules(content_format=content_format, content=retry.text,
                                 funnel_stage=funnel_stage, ctx=ctx)
        final_body = retry.text
        final_report = retry_report

    rejected = not final_report.passed
    body_to_write = final_body
    if rejected:
        body_to_write = _build_rejected_body(
            final_body,
            "\n".join(v.message for v in final_report.violations),
            content_format,
            cluster_row["name"],
        )

    content_path = _write_content_file(
        output_dir, cluster_row["name"], content_format, body_to_write, rejected=rejected
    )

    brief_path: Optional[Path] = None
    if not rejected and content_format in FORMATS_NEEDING_BRIEF:
        brief_path = write_brief_for(
            brief_dir=brief_dir,
            cluster_name=cluster_row["name"],
            source_query_id=source_query_id,
            content_format=content_format,
            body=final_body,
            town_display=town_display,
            ctx=ctx,
        )

    db_id = insert_generated_content(
        db_path,
        cluster_id=cluster_row["id"],
        source_query_id=source_query_id,
        format=content_format,  # DB column is named 'format' — this is SQL, not Python param
        content_path=str(content_path),
        brief_path=str(brief_path) if brief_path else None,
        status="rejected" if rejected else "pending",
        model_used=model_name,
        notes=None,
    )
    return GeneratedPiece(
        format=content_format,  # GeneratedPiece data field stays named 'format'
        content_path=str(content_path),
        brief_path=str(brief_path) if brief_path else None,
        db_id=db_id,
        status="rejected" if rejected else "pending",
    )
