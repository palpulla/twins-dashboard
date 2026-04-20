"""CLI: stage approved content as draft posts in GHL Social Planner.

Reads approved/*.md files, matches each one to the right GHL social account,
and creates a scheduled DRAFT post via the LeadConnector v2 API. Daniel still
clicks Publish in GHL — auto-publish is a later flag.

Requires .env:
  GHL_API_TOKEN      Private Integration token (pit-...)
  GHL_LOCATION_ID    Sub-account location id
  GHL_API_BASE       https://services.leadconnectorhq.com (default)

Usage:
  # Dry-run: show what would be posted, no API calls that write
  .venv/bin/python scripts/publish_to_ghl.py --dry-run

  # Stage last 7 days of approved GBP posts as drafts
  .venv/bin/python scripts/publish_to_ghl.py --since 2026-04-20 --format gbp_post

  # Schedule one per day starting tomorrow
  .venv/bin/python scripts/publish_to_ghl.py --schedule daily
"""
from __future__ import annotations

import argparse
import datetime as dt
import os
import re
import sys
from pathlib import Path
from typing import Optional

import requests
from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))
load_dotenv(ROOT / ".env", override=True)


DEFAULT_APPROVED = ROOT / "approved"
DEFAULT_API_BASE = "https://services.leadconnectorhq.com"
GHL_VERSION = "2021-07-28"

# Maps content-engine format → GHL social platform type(s).
FORMAT_TO_PLATFORMS = {
    "gbp_post": ["google"],
    "caption": ["facebook", "instagram", "linkedin"],
    "video_script": ["instagram", "tiktok", "youtube"],  # requires mp4 upload first — skipped today
    "faq": [],          # blog destination TBD
    "blog_snippet": [], # blog destination TBD
}

FILENAME_RE = re.compile(
    r"(?P<date>\d{4}-\d{2}-\d{2})_(?P<cluster>.+)_(?P<format>gbp_post|caption|video_script|faq|blog_snippet)\.md$"
)


def parse_filename(path: Path) -> Optional[dict]:
    m = FILENAME_RE.match(path.name)
    if not m:
        return None
    return {
        "date": m.group("date"),
        "cluster": m.group("cluster"),
        "format": m.group("format"),
        "path": path,
    }


def read_body(path: Path) -> str:
    """Strip any leaked meta-preamble the model may have included."""
    raw = path.read_text().strip()
    # Drop common leak pattern: "Using the X skill..." preamble before "---"
    if raw.lower().startswith("using the"):
        parts = raw.split("\n---\n", 1)
        if len(parts) == 2:
            raw = parts[1].strip()
    return raw


def ghl_get(session: requests.Session, base: str, path: str, params: dict | None = None) -> dict:
    url = f"{base}{path}"
    r = session.get(url, params=params or {}, timeout=30)
    r.raise_for_status()
    return r.json()


def ghl_post(session: requests.Session, base: str, path: str, body: dict) -> dict:
    url = f"{base}{path}"
    r = session.post(url, json=body, timeout=30)
    if not r.ok:
        raise RuntimeError(f"GHL {r.status_code}: {r.text}")
    return r.json()


def make_session(token: str) -> requests.Session:
    s = requests.Session()
    s.headers.update({
        "Authorization": f"Bearer {token}",
        "Version": GHL_VERSION,
        "Accept": "application/json",
        "Content-Type": "application/json",
    })
    return s


def list_accounts(session: requests.Session, base: str, location_id: str) -> list[dict]:
    """Return [{id, type, name}, ...] for each connected social account."""
    data = ghl_get(session, base, f"/social-media-posting/{location_id}/accounts")
    # LeadConnector responses vary — normalize both known shapes.
    accounts = data.get("accounts") or data.get("results") or data.get("data") or []
    out = []
    for acc in accounts:
        out.append({
            "id": acc.get("id") or acc.get("_id"),
            "type": (acc.get("type") or acc.get("platform") or "").lower(),
            "name": acc.get("name") or acc.get("displayName", ""),
        })
    return out


def schedule_draft(
    session: requests.Session,
    base: str,
    location_id: str,
    account_ids: list[str],
    body_text: str,
    when: dt.datetime,
) -> dict:
    payload = {
        "accountIds": account_ids,
        "summary": body_text,
        "type": "post",
        "scheduleDate": when.strftime("%Y-%m-%dT%H:%M:%S.000Z"),
        "status": "draft",
    }
    return ghl_post(session, base, f"/social-media-posting/{location_id}/posts", payload)


def main() -> None:
    ap = argparse.ArgumentParser(description="Stage approved content as GHL draft posts.")
    ap.add_argument("--approved", type=Path, default=DEFAULT_APPROVED)
    ap.add_argument("--format", type=str, default=None,
                    help="Only publish this format (gbp_post, caption, ...).")
    ap.add_argument("--since", type=str, default=None,
                    help="YYYY-MM-DD: only files dated on or after this.")
    ap.add_argument("--dry-run", action="store_true",
                    help="Do not call GHL — print what would be posted.")
    ap.add_argument("--schedule", choices=["now", "daily"], default="daily",
                    help="'daily' spaces posts one per day starting tomorrow; 'now' schedules all for +10 min.")
    args = ap.parse_args()

    token = os.environ.get("GHL_API_TOKEN")
    location_id = os.environ.get("GHL_LOCATION_ID")
    base = os.environ.get("GHL_API_BASE", DEFAULT_API_BASE)

    if not args.dry_run and (not token or token.startswith("pit-...")):
        print("ERROR: GHL_API_TOKEN not set. Add to .env and retry, or use --dry-run.",
              file=sys.stderr)
        sys.exit(1)
    if not args.dry_run and not location_id:
        print("ERROR: GHL_LOCATION_ID not set.", file=sys.stderr)
        sys.exit(1)

    # Collect + filter approved files
    files = []
    for p in sorted(args.approved.glob("*.md")):
        meta = parse_filename(p)
        if meta is None:
            continue
        if args.format and meta["format"] != args.format:
            continue
        if args.since and meta["date"] < args.since:
            continue
        if not FORMAT_TO_PLATFORMS.get(meta["format"]):
            continue  # blog/faq/video — skipped until destination is wired
        files.append(meta)

    if not files:
        print("No publishable files matched the filters.")
        sys.exit(0)

    # Schedule timestamps: daily starting tomorrow 10am local, or now +10m
    def ts_for(i: int) -> dt.datetime:
        now = dt.datetime.utcnow()
        if args.schedule == "now":
            return now + dt.timedelta(minutes=10 + i)
        base_day = (now + dt.timedelta(days=1)).replace(hour=15, minute=0, second=0, microsecond=0)
        return base_day + dt.timedelta(days=i)

    if args.dry_run:
        print(f"DRY RUN — {len(files)} file(s) would be staged:\n")
        for i, meta in enumerate(files):
            body = read_body(meta["path"])
            platforms = FORMAT_TO_PLATFORMS[meta["format"]]
            when = ts_for(i)
            print(f"[{i+1}/{len(files)}] {meta['path'].name}")
            print(f"    -> platforms: {platforms}")
            print(f"    -> when: {when.isoformat()}Z")
            print(f"    -> body ({len(body)} chars):")
            for line in body.splitlines()[:5]:
                print(f"       {line}")
            if len(body.splitlines()) > 5:
                print(f"       ... +{len(body.splitlines())-5} more lines")
            print()
        return

    # Real run: talk to GHL
    session = make_session(token)
    print(f"Listing GHL social accounts for location {location_id}...")
    accounts = list_accounts(session, base, location_id)
    if not accounts:
        print("ERROR: No social accounts returned. Check token scopes.", file=sys.stderr)
        sys.exit(2)
    print(f"Found {len(accounts)} account(s):")
    for a in accounts:
        print(f"  [{a['type']:10}] {a['name']:30} ({a['id']})")
    print()

    by_type: dict[str, list[str]] = {}
    for a in accounts:
        by_type.setdefault(a["type"], []).append(a["id"])

    staged = 0
    skipped = 0
    for i, meta in enumerate(files):
        body = read_body(meta["path"])
        platforms = FORMAT_TO_PLATFORMS[meta["format"]]
        acct_ids: list[str] = []
        for p in platforms:
            acct_ids.extend(by_type.get(p, []))
        if not acct_ids:
            print(f"[skip] {meta['path'].name}: no connected account for {platforms}")
            skipped += 1
            continue
        when = ts_for(i)
        resp = schedule_draft(session, base, location_id, acct_ids, body, when)
        post_id = resp.get("id") or resp.get("_id", "?")
        print(f"[ok]   {meta['path'].name} -> draft {post_id} @ {when.strftime('%Y-%m-%d %H:%M')}")
        staged += 1

    print(f"\nStaged {staged} draft(s); skipped {skipped}.")
    print(f"Review in GHL → Social Planner → Drafts, then click Publish on each.")


if __name__ == "__main__":
    main()
