"""JSON brief builder — one-way handoff contract to twins-media-generator."""
from __future__ import annotations

import datetime as dt
import json
import re
import uuid
from pathlib import Path
from typing import Any, Optional


# Map content-engine cluster names to twins-media-generator template names.
# Clusters not in this map fall back to DEFAULT_TEMPLATE.
CLUSTER_TO_TEMPLATE: dict[str, tuple[str, dict[str, str]]] = {
    "broken_spring": ("emergency_repair", {"issue_type": "broken_spring"}),
    "off_track": ("emergency_repair", {"issue_type": "off_track"}),
    "wont_open": ("emergency_repair", {"issue_type": "wont_open"}),
    "damaged_panel": ("emergency_repair", {"issue_type": "damaged_panel"}),
    "new_door_cost": ("product_hero", {}),
    "curb_appeal": ("curb_appeal", {}),
    "winter_prep": ("seasonal_promo", {"season": "winter"}),
    "opener_noise": ("hardware_closeup", {"product": "opener"}),
}
DEFAULT_TEMPLATE = ("product_hero", {})


FORMAT_ASPECT = {
    "video_script": ("9:16", 20),
    "gbp_post": ("4:5", None),
}


def _detect_cluster_template(cluster_name: str) -> tuple[str, dict[str, str]]:
    return CLUSTER_TO_TEMPLATE.get(cluster_name, DEFAULT_TEMPLATE)


def _extract_video_fields(body: str) -> dict[str, Any]:
    """Pull HOOK line, shot list, and voiceover from a video_script body."""
    hook_match = re.search(r"HOOK\s*\([^)]*\)\s*:\s*([^\n]+)", body)
    hook = hook_match.group(1).strip() if hook_match else ""
    on_screen = [hook] if hook else []

    shot_section_match = re.search(r"SHOT LIST:\s*\n(.+)$", body, re.DOTALL)
    shot_list: list[str] = []
    if shot_section_match:
        for line in shot_section_match.group(1).splitlines():
            stripped = line.strip()
            if stripped.startswith("-"):
                shot_list.append(stripped.lstrip("- ").strip())

    voiceover = re.sub(r"SHOT LIST:.*$", "", body, flags=re.DOTALL).strip()

    return {
        "on_screen_text": on_screen,
        "voiceover_script": voiceover,
        "shot_list": shot_list,
    }


def write_brief_for(
    *,
    brief_dir: Path,
    cluster_name: str,
    source_query_id: int,
    content_format: str,
    body: str,
    town_display: str,
    ctx: dict[str, Any],
) -> Optional[Path]:
    """Write a JSON brief for the given generated piece.

    Only video_script and gbp_post formats produce briefs (captions/faq/blog don't
    need visuals from the media generator).
    """
    if content_format not in FORMAT_ASPECT:
        return None

    brief_dir.mkdir(parents=True, exist_ok=True)
    brand = ctx["brand"]
    template_name, template_options = _detect_cluster_template(cluster_name)
    aspect, duration = FORMAT_ASPECT[content_format]

    brief_id = (
        f"{dt.date.today().isoformat()}_{cluster_name}_{content_format}_{uuid.uuid4().hex[:8]}"
    )

    payload: dict[str, Any] = {
        "brief_id": brief_id,
        "source_cluster": cluster_name,
        "source_query_id": source_query_id,
        "purpose": content_format,
        "platform_targets": _platform_targets_for(content_format),
        "aspect_ratio": aspect,
        "preferred_template": template_name,
        "template_options": template_options,
        "fallback_prompt": _fallback_prompt_from_body(body, content_format, town_display),
        "brand_enforcement": {
            "phone_number": brand.phone,
            "brand_colors": "enforce",
            "location_context": f"{town_display}, WI",
        },
    }
    if duration is not None:
        payload["duration_sec"] = duration

    if content_format == "video_script":
        payload.update(_extract_video_fields(body))

    path = brief_dir / f"{brief_id}.json"
    path.write_text(json.dumps(payload, indent=2))
    return path


def _platform_targets_for(content_format: str) -> list[str]:
    if content_format == "video_script":
        return ["instagram_reels", "tiktok", "youtube_shorts"]
    if content_format == "gbp_post":
        return ["google_business_profile"]
    return []


def _fallback_prompt_from_body(body: str, content_format: str, town_display: str) -> str:
    snippet = " ".join(body.split()[:50])
    return f"[{content_format} | {town_display}] {snippet}"
