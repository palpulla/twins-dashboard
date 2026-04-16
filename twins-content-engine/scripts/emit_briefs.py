"""CLI: list JSON briefs produced recently for media-generator handoff."""
from __future__ import annotations

import argparse
import datetime as dt
import shutil
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.db import get_conn


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_BRIEFS = ROOT / "briefs"


def main() -> None:
    ap = argparse.ArgumentParser(description="List/export briefs for twins-media-generator.")
    ap.add_argument("--since", type=str, default="yesterday",
                    help="'yesterday', 'today', ISO date (YYYY-MM-DD), or 'all'")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--briefs", type=Path, default=DEFAULT_BRIEFS)
    ap.add_argument("--copy-to", type=Path, default=None,
                    help="If set, copy matching briefs into this directory")
    args = ap.parse_args()

    cutoff = _resolve_cutoff(args.since)
    with get_conn(args.db) as c:
        rows = list(c.execute(
            "SELECT * FROM generated_content "
            "WHERE brief_path IS NOT NULL AND status IN ('pending','approved') "
            "AND generated_at >= ? "
            "ORDER BY generated_at",
            (cutoff.isoformat(sep=" "),),
        ).fetchall())

    print(f"Found {len(rows)} brief(s) since {cutoff.isoformat()}:")
    for row in rows:
        path = Path(row["brief_path"])
        exists = "exists" if path.exists() else "missing"
        print(f"  [{row['id']}] {row['format']:12s} {path}  ({exists})")
        if args.copy_to and path.exists():
            args.copy_to.mkdir(parents=True, exist_ok=True)
            shutil.copy2(str(path), str(args.copy_to / path.name))
    if args.copy_to:
        print(f"Copied to {args.copy_to}")


def _resolve_cutoff(since: str) -> dt.datetime:
    now = dt.datetime.utcnow()
    if since == "all":
        return dt.datetime(2000, 1, 1)
    if since == "today":
        return dt.datetime.combine(now.date(), dt.time.min)
    if since == "yesterday":
        return dt.datetime.combine(now.date() - dt.timedelta(days=1), dt.time.min)
    return dt.datetime.fromisoformat(since)


if __name__ == "__main__":
    main()
