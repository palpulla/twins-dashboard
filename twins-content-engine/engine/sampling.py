"""Cluster sampling for weekly content generation.

Algorithm:
  weight(cluster) = pillar.priority_weight * (1 / (days_since_last_use + 1))

Clusters are picked highest-weight first. Soft constraint: at most 2 clusters
per pillar per batch.
"""
from __future__ import annotations

import datetime as dt
from dataclasses import dataclass
from pathlib import Path
from typing import Optional

from engine.config import PillarsConfig
from engine.db import get_conn, get_cluster_last_used


@dataclass
class ClusterPick:
    id: int
    name: str
    pillar: str
    weight: float
    days_since_last_use: Optional[float]


MAX_PER_PILLAR_DEFAULT = 2


def pick_clusters_for_week(
    *,
    db_path: Path,
    count: int,
    pillars: PillarsConfig,
    max_per_pillar: int = MAX_PER_PILLAR_DEFAULT,
    now: Optional[dt.datetime] = None,
) -> list[ClusterPick]:
    """Return up to `count` cluster picks, sorted by descending weight."""
    now = now or dt.datetime.utcnow()
    with get_conn(db_path) as c:
        rows = list(c.execute(
            r"SELECT id, name, pillar FROM clusters "
            r"WHERE name NOT LIKE '\_unclustered\_%' ESCAPE '\'"
        ).fetchall())

    candidates: list[ClusterPick] = []
    for row in rows:
        pillar_obj = pillars.pillars.get(row["pillar"])
        if pillar_obj is None:
            continue
        last_used_str = get_cluster_last_used(db_path, int(row["id"]))
        if last_used_str:
            last_used = dt.datetime.fromisoformat(last_used_str.replace(" ", "T"))
            days = max((now - last_used).total_seconds() / 86400.0, 0.0)
        else:
            days = None
        if days is None:
            # Never used — treat as 30 days old for weight calculation,
            # then boost to ensure preference over recently-used clusters.
            weight = pillar_obj.priority_weight * (30.0 + 1.0)
        else:
            # More days since last use → higher weight (prefer stale clusters).
            weight = pillar_obj.priority_weight * (days + 1.0)
        candidates.append(ClusterPick(
            id=int(row["id"]),
            name=row["name"],
            pillar=row["pillar"],
            weight=weight,
            days_since_last_use=days,
        ))

    candidates.sort(key=lambda c: c.weight, reverse=True)

    picked: list[ClusterPick] = []
    per_pillar_count: dict[str, int] = {}
    for cand in candidates:
        if len(picked) >= count:
            break
        if per_pillar_count.get(cand.pillar, 0) >= max_per_pillar:
            continue
        picked.append(cand)
        per_pillar_count[cand.pillar] = per_pillar_count.get(cand.pillar, 0) + 1
    return picked
