"""CLI: generate N cluster-batches (all 5 formats each) for the week."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.claude_client import ClaudeClient
from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.generator import generate_for_cluster
from engine.sampling import pick_clusters_for_week


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_CONFIG = ROOT / "config"
DEFAULT_PENDING = ROOT / "pending"
DEFAULT_BRIEFS = ROOT / "briefs"


def main() -> None:
    ap = argparse.ArgumentParser(description="Generate one week of cluster content.")
    ap.add_argument("--count", type=int, default=7, help="Number of clusters to generate")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--config", type=Path, default=DEFAULT_CONFIG)
    ap.add_argument("--pending", type=Path, default=DEFAULT_PENDING)
    ap.add_argument("--briefs", type=Path, default=DEFAULT_BRIEFS)
    ap.add_argument("--model", type=str, default="claude-sonnet-4-6")
    args = ap.parse_args()

    ctx = {
        "brand": load_brand(args.config / "brand.yaml"),
        "service_area": load_service_area(args.config / "service_area.yaml"),
        "pillars": load_pillars(args.config / "pillars.yaml"),
        "rules": load_rules(args.config / "rules.yaml"),
    }

    picks = pick_clusters_for_week(
        db_path=args.db,
        count=args.count,
        pillars=ctx["pillars"],
    )
    if not picks:
        print("No clusters available. Run ingest_seeds and expand_clusters first.")
        sys.exit(1)

    client = ClaudeClient(model=args.model)
    total_accepted = 0
    total_rejected = 0
    for p in picks:
        print(f"Generating cluster: {p.name} (pillar={p.pillar}, weight={p.weight:.3f})")
        result = generate_for_cluster(
            db_path=args.db,
            cluster_name=p.name,
            client=client,
            ctx=ctx,
            output_dir=args.pending,
            brief_dir=args.briefs,
            model_name=args.model,
        )
        total_accepted += len(result.accepted)
        total_rejected += len(result.rejected)
        print(f"  -> accepted={len(result.accepted)} rejected={len(result.rejected)}")

    print(f"\nDone. {total_accepted} accepted, {total_rejected} rejected.")
    print(f"Review pending files in: {args.pending}")


if __name__ == "__main__":
    main()
