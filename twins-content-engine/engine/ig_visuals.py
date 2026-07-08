"""Visual resolution with the proof fallback hierarchy and AI cap."""
from __future__ import annotations

from dataclasses import dataclass
from typing import Optional

# Ordered proof fallback: real kinds by priority, then educational AI.
_PROOF_ORDER = ["completed_job", "before_after", "verified_review", "truck", "tools", "parts", "wip"]
_LEVEL = {"completed_job": 1, "before_after": 2, "verified_review": 3,
          "truck": 4, "tools": 4, "parts": 4, "wip": 4}


@dataclass(frozen=True)
class RealAsset:
    kind: str      # completed_job | before_after | verified_review | truck | tools | parts | wip
    path: str


@dataclass(frozen=True)
class VisualPlan:
    kind: str                    # "real" | "ai"
    asset_path: Optional[str]
    ai_spec: Optional[str]       # e.g. "educational_graphic" when kind == "ai"
    fallback_level: int          # 1..5
    needs_approval: bool = False


def _would_exceed_cap(ai_used: int, total_posts: int, max_ai_fraction: float) -> bool:
    projected = (ai_used + 1) / (total_posts + 1)
    return projected > max_ai_fraction


def resolve_visual(slot: str, real_assets: list[RealAsset], ai_used: int,
                   total_posts: int, max_ai_fraction: float) -> VisualPlan:
    for kind in _PROOF_ORDER:
        match = next((a for a in real_assets if a.kind == kind), None)
        if match:
            return VisualPlan(kind="real", asset_path=match.path,
                              ai_spec=None, fallback_level=_LEVEL[kind])
    # No real asset: educational AI graphic only (never a fabricated job).
    needs_approval = _would_exceed_cap(ai_used, total_posts, max_ai_fraction)
    return VisualPlan(kind="ai", asset_path=None, ai_spec="educational_graphic",
                      fallback_level=5, needs_approval=needs_approval)
