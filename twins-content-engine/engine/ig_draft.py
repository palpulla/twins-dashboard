"""Instagram draft object and markdown serialization."""
from __future__ import annotations

from dataclasses import dataclass, field, asdict
from typing import Optional

import frontmatter

from engine.ig_planner import SlotSpec
from engine.ig_visuals import VisualPlan


@dataclass(frozen=True)
class SourceRecord:
    asset_source: str                       # real_photo | real_video | verified_review | ai_graphic
    job_folder: Optional[str]
    review_verified: Optional[bool]
    confirmed_city: Optional[str]
    offer_used: Optional[str]
    needs_approval: list[str] = field(default_factory=list)


@dataclass(frozen=True)
class Draft:
    slot: SlotSpec
    caption: str
    cta: str
    city: Optional[str]
    hashtags: list[str]
    visual: VisualPlan
    source: SourceRecord


def draft_to_markdown(draft: Draft) -> str:
    post = frontmatter.Post(draft.caption)
    post["date"] = draft.slot.date
    post["week"] = draft.slot.week
    post["slot"] = draft.slot.slot
    post["format_target"] = draft.slot.format_target
    post["city"] = draft.city
    post["cta"] = draft.cta
    post["hashtags"] = draft.hashtags
    post["visual"] = asdict(draft.visual)
    post["source"] = asdict(draft.source)
    return frontmatter.dumps(post)
