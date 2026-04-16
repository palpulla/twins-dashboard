"""CLI: generate content for a single named cluster."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.claude_client import ClaudeClient
from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.generator import FORMATS, generate_for_cluster


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_CONFIG = ROOT / "config"
DEFAULT_PENDING = ROOT / "pending"
DEFAULT_BRIEFS = ROOT / "briefs"


def main() -> None:
    ap = argparse.ArgumentParser(description="Generate content for a single cluster.")
    ap.add_argument("--name", type=str, required=True, help="Cluster name")
    ap.add_argument("--formats", type=str,
                    help="Comma-separated format list; default: all 5")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--config", type=Path, default=DEFAULT_CONFIG)
    ap.add_argument("--pending", type=Path, default=DEFAULT_PENDING)
    ap.add_argument("--briefs", type=Path, default=DEFAULT_BRIEFS)
    ap.add_argument("--model", type=str, default="claude-sonnet-4-6")
    args = ap.parse_args()

    if args.formats:
        requested = [f.strip() for f in args.formats.split(",")]
        unknown = set(requested) - set(FORMATS)
        if unknown:
            ap.error(f"Unknown format(s): {sorted(unknown)}. Valid: {FORMATS}")

    ctx = {
        "brand": load_brand(args.config / "brand.yaml"),
        "service_area": load_service_area(args.config / "service_area.yaml"),
        "pillars": load_pillars(args.config / "pillars.yaml"),
        "rules": load_rules(args.config / "rules.yaml"),
    }

    client = ClaudeClient(model=args.model)
    result = generate_for_cluster(
        db_path=args.db,
        cluster_name=args.name,
        client=client,
        ctx=ctx,
        output_dir=args.pending,
        brief_dir=args.briefs,
        model_name=args.model,
    )
    print(f"Cluster {args.name}: accepted={len(result.accepted)} "
          f"rejected={len(result.rejected)}")


if __name__ == "__main__":
    main()
