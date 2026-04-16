"""Typed loaders for config YAML files."""
from __future__ import annotations

from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import yaml


VALID_FORMATS = {"video_script", "caption", "gbp_post", "faq", "blog_snippet"}


@dataclass
class BrandConfig:
    business_name: str
    phone: str
    primary_service_area: str
    allow_emojis: bool = False
    voice_terms_to_prefer: list[str] = field(default_factory=list)
    voice_terms_to_avoid: list[str] = field(default_factory=list)
    brand_years_in_business: int = 0
    signature_proof_points: list[str] = field(default_factory=list)


@dataclass
class ServiceAreaConfig:
    primary: str
    towns: list[str]
    town_display_names: dict[str, str]
    madison_neighborhoods: list[str] = field(default_factory=list)


@dataclass
class Pillar:
    description: str
    funnel_stage: str
    seed_patterns: list[str]
    format_bias: list[str]
    priority_weight: float


@dataclass
class PillarsConfig:
    pillars: dict[str, Pillar]


@dataclass
class VoiceRules:
    persona: str
    structure: str
    reading_level_max: int


@dataclass
class BlacklistRules:
    phrases: list[str]
    structural: list[str]


@dataclass
class SpecificityRules:
    must_include_one_of: list[str]
    must_avoid_phrases: list[str]


@dataclass
class CTARules:
    one_per_piece: bool
    funnel_stage_mapping: dict[str, str]


@dataclass
class PlacementRules:
    no_consecutive_sentences: bool
    require_mention_in_first_pct: int
    prefer_specific_town_over_region: bool


@dataclass
class LocalEmbeddingRules:
    mention_frequency: dict[str, list[int]]
    placement_rules: PlacementRules


@dataclass
class RulesConfig:
    voice: VoiceRules
    hook_requirements: dict[str, Any]
    anti_ai_spam_blacklist: BlacklistRules
    specificity_requirements: SpecificityRules
    cta_rules: CTARules
    local_embedding: LocalEmbeddingRules


def _read_yaml(path: Path) -> dict[str, Any]:
    return yaml.safe_load(path.read_text())


def load_brand(path: Path) -> BrandConfig:
    data = _read_yaml(path)
    required = ["business_name", "phone", "primary_service_area"]
    for key in required:
        if key not in data:
            raise ValueError(f"brand.yaml missing required field: {key}")
    return BrandConfig(
        business_name=data["business_name"],
        phone=data["phone"],
        primary_service_area=data["primary_service_area"],
        allow_emojis=data.get("allow_emojis", False),
        voice_terms_to_prefer=data.get("voice_terms_to_prefer", []),
        voice_terms_to_avoid=data.get("voice_terms_to_avoid", []),
        brand_years_in_business=data.get("brand_years_in_business", 0),
        signature_proof_points=data.get("signature_proof_points", []),
    )


def load_service_area(path: Path) -> ServiceAreaConfig:
    data = _read_yaml(path)
    for key in ("primary", "towns"):
        if key not in data:
            raise ValueError(f"service_area.yaml missing required field: {key}")
    display_names = data.get("town_display_names", {})
    for town in data["towns"]:
        if town not in display_names:
            raise ValueError(f"service_area.yaml: display name missing for {town}")
    return ServiceAreaConfig(
        primary=data["primary"],
        towns=data["towns"],
        town_display_names=display_names,
        madison_neighborhoods=data.get("madison_neighborhoods", []),
    )


def load_pillars(path: Path) -> PillarsConfig:
    data = _read_yaml(path)
    if "pillars" not in data:
        raise ValueError("pillars.yaml missing required key: pillars")
    pillars: dict[str, Pillar] = {}
    for name, p in data["pillars"].items():
        for fmt in p["format_bias"]:
            if fmt not in VALID_FORMATS:
                raise ValueError(
                    f"pillars.yaml: invalid format_bias value {fmt!r} under {name}"
                )
        pillars[name] = Pillar(
            description=p["description"],
            funnel_stage=p["funnel_stage"],
            seed_patterns=p["seed_patterns"],
            format_bias=p["format_bias"],
            priority_weight=float(p["priority_weight"]),
        )
    return PillarsConfig(pillars=pillars)


def load_rules(path: Path) -> RulesConfig:
    data = _read_yaml(path)
    v = data["voice"]
    bl = data["anti_ai_spam_blacklist"]
    sp = data["specificity_requirements"]
    ct = data["cta_rules"]
    le = data["local_embedding"]
    pr = le["placement_rules"]
    return RulesConfig(
        voice=VoiceRules(
            persona=v["persona"],
            structure=v["structure"],
            reading_level_max=int(v["reading_level_max"]),
        ),
        hook_requirements=data.get("hook_requirements", {}),
        anti_ai_spam_blacklist=BlacklistRules(
            phrases=bl.get("phrases", []),
            structural=bl.get("structural", []),
        ),
        specificity_requirements=SpecificityRules(
            must_include_one_of=sp.get("must_include_one_of", []),
            must_avoid_phrases=sp.get("must_avoid_phrases", []),
        ),
        cta_rules=CTARules(
            one_per_piece=bool(ct["one_per_piece"]),
            funnel_stage_mapping=ct.get("funnel_stage_mapping", {}),
        ),
        local_embedding=LocalEmbeddingRules(
            mention_frequency={k: list(v) for k, v in le.get("mention_frequency", {}).items()},
            placement_rules=PlacementRules(
                no_consecutive_sentences=bool(pr["no_consecutive_sentences"]),
                require_mention_in_first_pct=int(pr["require_mention_in_first_pct"]),
                prefer_specific_town_over_region=bool(pr["prefer_specific_town_over_region"]),
            ),
        ),
    )
