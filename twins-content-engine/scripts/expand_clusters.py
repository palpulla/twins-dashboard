"""CLI: expand placeholder clusters into real clusters + variants via Claude."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.claude_client import ClaudeClient
from engine.cluster import expand_seeds_to_clusters
from engine.config import load_service_area


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_SERVICE_AREA = ROOT / "config" / "service_area.yaml"


def main() -> None:
    ap = argparse.ArgumentParser(description="Expand seeded clusters via Claude.")
    ap.add_argument("--all", action="store_true", help="Process all placeholder clusters")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--service-area", type=Path, default=DEFAULT_SERVICE_AREA)
    ap.add_argument("--model", type=str, default="claude-sonnet-4-6")
    args = ap.parse_args()

    if not args.all:
        ap.error("--all is required in v1 (partial expansion is a future feature)")

    towns = load_service_area(args.service_area).towns
    client = ClaudeClient(model=args.model)
    expand_seeds_to_clusters(db_path=args.db, client=client, towns=towns)
    print(f"Expansion complete against {args.db}")


if __name__ == "__main__":
    main()
