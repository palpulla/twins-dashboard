"""CLI: regenerate a single generated_content piece with an operator critique."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.claude_client import ClaudeClient
from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.db import get_conn, update_content_status
from engine.local_embedding import pick_neighborhood, pick_town_for_piece
from engine.prompts import build_regeneration_prompt
from engine.rules import run_rules


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_CONFIG = ROOT / "config"


def main() -> None:
    ap = argparse.ArgumentParser(description="Regenerate one piece with an operator critique.")
    ap.add_argument("--id", type=int, required=True, help="generated_content.id")
    ap.add_argument("--critique", type=str, required=True,
                    help="Free-text critique fed into the LLM")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--config", type=Path, default=DEFAULT_CONFIG)
    ap.add_argument("--model", type=str, default="claude-sonnet-4-6")
    args = ap.parse_args()

    ctx = {
        "brand": load_brand(args.config / "brand.yaml"),
        "service_area": load_service_area(args.config / "service_area.yaml"),
        "pillars": load_pillars(args.config / "pillars.yaml"),
        "rules": load_rules(args.config / "rules.yaml"),
    }

    with get_conn(args.db) as c:
        row = c.execute(
            "SELECT gc.*, cl.name AS cluster_name, cl.pillar, cl.funnel_stage, "
            "       q.query_text, q.geo_modifier "
            "FROM generated_content gc "
            "JOIN clusters cl ON cl.id = gc.cluster_id "
            "JOIN queries q ON q.id = gc.source_query_id "
            "WHERE gc.id = ?",
            (args.id,),
        ).fetchone()
    if row is None:
        ap.error(f"No generated_content with id={args.id}")

    prior_body = Path(row["content_path"]).read_text()
    service_area = ctx["service_area"]
    town_slug = pick_town_for_piece(
        query_geo_modifier=row["geo_modifier"],
        cluster_seed=int(row["cluster_id"]),
        service_area=service_area,
    )
    town_display = service_area.town_display_names[town_slug]
    neighborhood = pick_neighborhood(cluster_seed=int(row["cluster_id"]),
                                     service_area=service_area)

    system, user = build_regeneration_prompt(
        content_format=row["format"],
        previous_output=prior_body,
        critique_lines=[f"- [operator] {args.critique}"],
        query_text=row["query_text"],
        cluster_name=row["cluster_name"],
        pillar_name=row["pillar"],
        funnel_stage=row["funnel_stage"] or "low_intent_educational",
        target_town_display=town_display,
        target_neighborhood=neighborhood,
        ctx=ctx,
    )

    client = ClaudeClient(model=args.model)
    result = client.complete(system=system, user=user, max_tokens=2048, temperature=0.7)
    report = run_rules(
        content_format=row["format"], content=result.text,
        funnel_stage=row["funnel_stage"] or "low_intent_educational", ctx=ctx,
    )

    new_body = result.text
    if not report.passed:
        print("Regenerated output still fails rules:")
        for v in report.violations:
            print(f"  - {v.rule_id}: {v.message}")
        print("Writing as _REJECTED_; correct manually or rerun.")

    target = Path(row["content_path"])
    if not report.passed and not target.name.startswith("_REJECTED_"):
        target = target.with_name("_REJECTED_" + target.name)
    target.write_text(new_body)
    with get_conn(args.db) as c:
        c.execute(
            "UPDATE generated_content SET content_path = ?, model_used = ? WHERE id = ?",
            (str(target), args.model, args.id),
        )
    update_content_status(
        args.db, args.id,
        "rejected" if not report.passed else "pending",
    )
    print(f"Regenerated piece {args.id} -> {target}")


if __name__ == "__main__":
    main()
