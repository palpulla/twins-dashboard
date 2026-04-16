"""Local embedding helpers used by the generator to inject local context into prompts."""
from __future__ import annotations

from typing import Optional

from engine.config import BrandConfig, ServiceAreaConfig


def pick_town_for_piece(
    *,
    query_geo_modifier: Optional[str],
    cluster_seed: int,
    service_area: ServiceAreaConfig,
) -> str:
    """Return the town slug to emphasize in generated content.

    Rules:
      1. If the source query has a geo_modifier, use that.
      2. Otherwise, deterministically rotate through the town list by cluster id,
         biased toward the primary service area every ~3rd pick.
    """
    if query_geo_modifier and query_geo_modifier in service_area.towns:
        return query_geo_modifier
    towns = service_area.towns
    if cluster_seed % 3 == 0:
        return service_area.primary
    return towns[cluster_seed % len(towns)]


def pick_neighborhood(
    *,
    cluster_seed: int,
    service_area: ServiceAreaConfig,
) -> Optional[str]:
    """Pick a Madison neighborhood deterministically for authority/local_authority posts."""
    if not service_area.madison_neighborhoods:
        return None
    idx = cluster_seed % len(service_area.madison_neighborhoods)
    return service_area.madison_neighborhoods[idx]


def format_phone_for_content(brand: BrandConfig) -> str:
    """The generator may need to stamp the phone verbatim. Source of truth is brand.yaml."""
    return brand.phone


def target_mention_count(content_format: str, freq_map: dict[str, list[int]]) -> tuple[int, int]:
    """Return (lo, hi) expected town-mention count for a given format, or (0, 0)."""
    val = freq_map.get(content_format)
    if not val or len(val) != 2:
        return (0, 0)
    return (int(val[0]), int(val[1]))
