"""CLI: promote pending content to approved/."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.review import approve_all_pending, approve_by_id


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_PENDING = ROOT / "pending"
DEFAULT_APPROVED = ROOT / "approved"


def main() -> None:
    ap = argparse.ArgumentParser(description="Approve generated content pieces.")
    group = ap.add_mutually_exclusive_group(required=True)
    group.add_argument("--all-passing", action="store_true",
                       help="Promote all non-rejected pending pieces")
    group.add_argument("--id", type=int, help="Promote a single piece by DB id")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--pending", type=Path, default=DEFAULT_PENDING)
    ap.add_argument("--approved", type=Path, default=DEFAULT_APPROVED)
    args = ap.parse_args()

    if args.all_passing:
        result = approve_all_pending(
            db_path=args.db, pending_dir=args.pending, approved_dir=args.approved
        )
        print(f"Approved {result.approved_count} piece(s). "
              f"Skipped {result.skipped_rejected} rejected piece(s).")
    else:
        approve_by_id(
            db_path=args.db,
            content_id=args.id,
            pending_dir=args.pending,
            approved_dir=args.approved,
        )
        print(f"Approved piece id={args.id}.")


if __name__ == "__main__":
    main()
