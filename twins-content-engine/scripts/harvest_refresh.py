"""CLI: fold new queries from harvest_inbox.md into the DB; archive the inbox."""
from __future__ import annotations

import argparse
import datetime as dt
import shutil
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.claude_client import ClaudeClient
from engine.harvest import fold_into_db, parse_harvest_inbox


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_INBOX = ROOT / "data" / "harvest_inbox.md"
DEFAULT_ARCHIVE = ROOT / "data" / "harvest_processed"


def main() -> None:
    ap = argparse.ArgumentParser(description="Fold harvest_inbox entries into the DB.")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--inbox", type=Path, default=DEFAULT_INBOX)
    ap.add_argument("--archive", type=Path, default=DEFAULT_ARCHIVE)
    ap.add_argument("--model", type=str, default="claude-sonnet-4-6")
    args = ap.parse_args()

    if not args.inbox.exists():
        print(f"No inbox at {args.inbox}; nothing to refresh.")
        return

    entries = parse_harvest_inbox(args.inbox)
    if not entries:
        print("Inbox is empty (only comments/blank lines). Nothing to do.")
        return

    client = ClaudeClient(model=args.model)
    fold_into_db(db_path=args.db, entries=entries, client=client)

    args.archive.mkdir(parents=True, exist_ok=True)
    stamp = dt.datetime.utcnow().strftime("%Y%m%d_%H%M%S")
    archived = args.archive / f"inbox_{stamp}.md"
    shutil.move(str(args.inbox), str(archived))
    args.inbox.write_text(
        "# harvest inbox — drop new real-world queries here, one per line.\n"
        "# Optional: add `| source=call` / `source=text` / `source=search_console` / `source=review`.\n"
    )
    print(f"Folded {len(entries)} entries; archived inbox to {archived}")


if __name__ == "__main__":
    main()
