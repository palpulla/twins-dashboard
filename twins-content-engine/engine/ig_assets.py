"""Scan the Instagram job-photo inbox into RealAssets."""
from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

from engine.ig_visuals import RealAsset

_IMAGE_EXTS = {".jpg", ".jpeg", ".png", ".mp4", ".mov"}
_KIND_MAP = {
    "completed": "completed_job",
    "before_after": "before_after",
    "review": "verified_review",
    "truck": "truck",
    "tools": "tools",
    "parts": "parts",
    "wip": "wip",
}


@dataclass(frozen=True)
class InboxAsset:
    asset: RealAsset
    city: str | None


def _parse_city(slug: str | None) -> str | None:
    if not slug:
        return None
    return " ".join(w.capitalize() for w in slug.split("-"))


def scan_inbox(inbox: Path) -> list[InboxAsset]:
    if not inbox.exists():
        return []
    results: list[InboxAsset] = []
    for f in sorted(inbox.iterdir(), key=lambda p: p.stat().st_mtime, reverse=True):
        if f.suffix.lower() not in _IMAGE_EXTS:
            continue
        stem_parts = f.stem.split("_")
        kind = None
        rest: list[str] = []
        if len(stem_parts) >= 2 and f"{stem_parts[0]}_{stem_parts[1]}" in _KIND_MAP:
            kind = _KIND_MAP[f"{stem_parts[0]}_{stem_parts[1]}"]
            rest = stem_parts[2:]
        elif stem_parts[0] in _KIND_MAP:
            kind = _KIND_MAP[stem_parts[0]]
            rest = stem_parts[1:]
        if kind is None:
            continue
        city = _parse_city(rest[0]) if rest else None
        results.append(InboxAsset(asset=RealAsset(kind=kind, path=str(f)), city=city))
    return results
