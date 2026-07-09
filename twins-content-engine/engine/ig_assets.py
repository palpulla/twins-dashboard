"""Scan the Instagram job-photo inbox into RealAssets."""
from __future__ import annotations

import re
from dataclasses import dataclass
from pathlib import Path

from engine.ig_visuals import RealAsset

# Images only — the IG program is photo-first (reels are Plan 3); videos in
# the inbox are ignored so they can never reach the image-post publish path.
_IMAGE_EXTS = {".jpg", ".jpeg", ".png"}
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


def _normalize(text: str) -> str:
    return re.sub(r"[\s-]", "", text).lower()


def _parse_city(slug: str | None, known_cities: list[str] | None = None) -> str | None:
    if not slug:
        return None
    if known_cities:
        target = _normalize(slug)
        for city in known_cities:
            if _normalize(city) == target:
                return city
        # A known-cities whitelist was provided and the token isn't in it:
        # treat it as a description, NOT a location. A caption must never
        # name an invented city (e.g. completed_frontdoor.jpg -> "Frontdoor").
        return None
    return " ".join(w.capitalize() for w in slug.split("-"))


def scan_inbox(inbox: Path, known_cities: list[str] | None = None) -> list[InboxAsset]:
    if not inbox.exists():
        return []
    results: list[InboxAsset] = []
    for f in sorted(inbox.iterdir(), key=lambda p: p.stat().st_mtime, reverse=True):
        if f.suffix.lower() not in _IMAGE_EXTS:
            continue
        stem_parts = f.stem.lower().split("_")
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
        city = _parse_city(rest[0], known_cities) if rest else None
        results.append(InboxAsset(asset=RealAsset(kind=kind, path=str(f)), city=city))
    return results
