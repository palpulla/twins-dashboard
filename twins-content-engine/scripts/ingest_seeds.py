"""CLI: parse a seed file and insert raw_seed queries into the DB.

Each seed becomes a query in a placeholder cluster named `_unclustered_<n>`.
The expand_clusters step (Task 7) re-homes these into real clusters.
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

# Make engine importable when run as a script
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.cluster import parse_seed_file
from engine.db import init_db, insert_cluster, insert_query


DEFAULT_DB = Path(__file__).resolve().parent.parent / "data" / "content_engine.db"


def run(seed_path: Path, db_path: Path) -> int:
    init_db(db_path)
    seeds = parse_seed_file(seed_path)
    inserted = 0
    for i, seed in enumerate(seeds, start=1):
        placeholder_name = f"_unclustered_{i}"
        pillar = seed.pillar_hint or "unclustered"
        cluster_id = insert_cluster(
            db_path,
            name=placeholder_name,
            pillar=pillar,
            service_type=None,
            funnel_stage=None,
            priority_score=5,
            notes="auto-created by ingest_seeds.py",
        )
        insert_query(
            db_path,
            cluster_id=cluster_id,
            query_text=seed.query_text,
            phrasing_type="raw_seed",
            geo_modifier=None,
            source="raw_seed",
            priority_score=5,
            notes=None,
        )
        inserted += 1
    return inserted


def main() -> None:
    ap = argparse.ArgumentParser(description="Ingest seed queries into the content engine DB.")
    ap.add_argument("seed_file", type=Path, help="Path to seed .md file")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB, help="SQLite DB path")
    args = ap.parse_args()

    inserted = run(args.seed_file, args.db)
    print(f"Ingested {inserted} seed queries into {args.db}")


if __name__ == "__main__":
    main()
