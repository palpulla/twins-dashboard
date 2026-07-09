"""CLI: approve or reject pending Instagram drafts (the human gate)."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

import frontmatter

ROOT = Path(__file__).resolve().parent.parent
DEFAULT_PENDING = ROOT / "pending" / "instagram"
DEFAULT_APPROVED = ROOT / "approved" / "instagram"
DEFAULT_REJECTED = ROOT / "rejected" / "instagram"


def approve_draft(path: Path, approved_dir: Path) -> Path:
    """Stamp a pending draft as approved and move it into approved_dir."""
    out = approved_dir / path.name
    if out.exists():
        raise FileExistsError(
            f"Already approved: {path.name} — delete it from {approved_dir} "
            f"first if you mean to replace it."
        )
    post = frontmatter.loads(path.read_text())
    post["approved"] = True
    approved_dir.mkdir(parents=True, exist_ok=True)
    out.write_text(frontmatter.dumps(post))
    path.unlink()
    return out


def reject_draft(path: Path, rejected_dir: Path, reason: str) -> Path:
    """Stamp a pending draft with a rejection reason and move it into rejected_dir."""
    out = rejected_dir / path.name
    if out.exists():
        raise FileExistsError(
            f"Already rejected: {path.name} — delete it from {rejected_dir} "
            f"first if you mean to replace it."
        )
    post = frontmatter.loads(path.read_text())
    post["rejected_reason"] = reason
    rejected_dir.mkdir(parents=True, exist_ok=True)
    out.write_text(frontmatter.dumps(post))
    path.unlink()
    return out


def _list(pending_dir: Path) -> None:
    files = sorted(pending_dir.glob("*.md")) if pending_dir.exists() else []
    if not files:
        print("No pending drafts.")
        return
    for f in files:
        try:
            post = frontmatter.loads(f.read_text())
        except Exception:
            print(f"{f.name}  [unparseable frontmatter]")
            continue
        flags = ", ".join(post.get("source", {}).get("needs_approval", [])) or "-"
        first_line = post.content.strip().splitlines()[0] if post.content.strip() else ""
        print(f"{f.name}  [{post.get('slot')}]  visual={post.get('visual', {}).get('kind')}  flags={flags}")
        print(f"    {first_line}")


def main() -> None:
    ap = argparse.ArgumentParser(description="Approve or reject pending Instagram drafts.")
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("list", help="Show pending drafts awaiting approval")
    a = sub.add_parser("approve", help="Move a pending draft to approved/")
    a.add_argument("name", help="Draft filename in pending/instagram/")
    r = sub.add_parser("reject", help="Move a pending draft to rejected/ with a reason")
    r.add_argument("name", help="Draft filename in pending/instagram/")
    r.add_argument("reason", help="Why this draft was rejected")
    args = ap.parse_args()

    if args.cmd == "list":
        _list(DEFAULT_PENDING)
        return

    if args.name != Path(args.name).name:
        print("Draft name must be a bare filename.")
        sys.exit(1)

    try:
        if args.cmd == "approve":
            out = approve_draft(DEFAULT_PENDING / args.name, DEFAULT_APPROVED)
            print(f"Approved: {out}")
        else:
            out = reject_draft(DEFAULT_PENDING / args.name, DEFAULT_REJECTED, args.reason)
            print(f"Rejected: {out}")
    except FileNotFoundError:
        print(f"Not found in pending/instagram/: {args.name} — run 'list' to see pending drafts")
        sys.exit(1)
    except FileExistsError as e:
        print(e)
        sys.exit(1)


if __name__ == "__main__":
    main()
