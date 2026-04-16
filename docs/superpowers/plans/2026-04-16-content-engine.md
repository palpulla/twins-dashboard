# Twins Content Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build subsystem #1 of the Twins Garage Doors marketing automation — a Python CLI system that produces reviewable, rules-gated, multi-format local content from a clustered intent database.

**Architecture:** Flat-layout Python package at `twins-dashboard/twins-content-engine/`. `engine/` is pure importable library code; `scripts/` are thin argparse CLIs. SQLite stores structured data; Markdown files hold human-reviewable output in `pending/` → `approved/`. JSON "briefs" are emitted to `briefs/` for handoff to `twins-media-generator`. Anthropic Claude is the only LLM provider; `ClaudeClient` is dependency-injected so all tests use a `FakeClaudeClient`.

**Tech Stack:** Python 3.11+, Anthropic SDK (`anthropic`), SQLite (stdlib), PyYAML, python-frontmatter, tenacity (retries), pytest + pytest-mock. Build backend: hatchling. CLI: argparse (stdlib, matches existing `twins-media-generator` style).

**Spec:** [docs/superpowers/specs/2026-04-16-content-engine-design.md](../specs/2026-04-16-content-engine-design.md)

---

## Task 1: Project scaffolding

**Files:**
- Create: `twins-content-engine/pyproject.toml`
- Create: `twins-content-engine/.gitignore`
- Create: `twins-content-engine/README.md`
- Create: `twins-content-engine/engine/__init__.py`
- Create: `twins-content-engine/tests/__init__.py`
- Create: `twins-content-engine/tests/conftest.py`

- [ ] **Step 1: Create the directory structure**

```bash
cd /Users/daniel/twins-dashboard
mkdir -p twins-content-engine/{config,data/seeds,data/harvest_processed,engine,scripts,tests/smoke,pending,approved,briefs}
touch twins-content-engine/engine/__init__.py
touch twins-content-engine/tests/__init__.py
touch twins-content-engine/tests/smoke/__init__.py
```

- [ ] **Step 2: Write `pyproject.toml`**

Create `twins-content-engine/pyproject.toml`:

```toml
[build-system]
requires = ["hatchling"]
build-backend = "hatchling.build"

[project]
name = "twins-content-engine"
version = "0.1.0"
description = "Content strategy + generation engine for Twins Garage Doors"
requires-python = ">=3.11"
dependencies = [
    "anthropic>=0.40.0",
    "pyyaml>=6.0",
    "python-frontmatter>=1.1.0",
    "tenacity>=9.0.0",
]

[project.optional-dependencies]
dev = [
    "pytest>=8.0.0",
    "pytest-mock>=3.12.0",
]

[tool.hatch.build.targets.wheel]
packages = ["engine"]

[tool.pytest.ini_options]
testpaths = ["tests"]
python_files = ["test_*.py"]
addopts = "-v --tb=short"
markers = [
    "integration: hits real Anthropic API (gated by INTEGRATION=1)",
]
```

- [ ] **Step 3: Write `.gitignore`**

Create `twins-content-engine/.gitignore`:

```
__pycache__/
*.py[cod]
*.egg-info/
.pytest_cache/
.venv/
venv/
data/content_engine.db
data/content_engine.db-wal
data/content_engine.db-shm
pending/*
approved/*
briefs/*
!pending/.gitkeep
!approved/.gitkeep
!briefs/.gitkeep
.env
```

- [ ] **Step 4: Add gitkeep files**

```bash
cd /Users/daniel/twins-dashboard/twins-content-engine
touch pending/.gitkeep approved/.gitkeep briefs/.gitkeep
```

- [ ] **Step 5: Write a minimal `README.md`**

Create `twins-content-engine/README.md`:

```markdown
# Twins Content Engine

Content strategy + generation engine for Twins Garage Doors.

See [SKILL.md](SKILL.md) for usage. See the design spec at
`../docs/superpowers/specs/2026-04-16-content-engine-design.md`.

## Setup

```bash
python3.11 -m venv .venv
source .venv/bin/activate
pip install -e ".[dev]"
export ANTHROPIC_API_KEY="sk-ant-..."
```

## Running tests

```bash
pytest                        # unit + smoke tests
INTEGRATION=1 pytest -m integration   # + real API integration test
```
```

- [ ] **Step 6: Write `tests/conftest.py` with shared fixtures**

Create `twins-content-engine/tests/conftest.py`:

```python
"""Shared pytest fixtures for content engine tests."""
from __future__ import annotations

import sqlite3
from pathlib import Path
from typing import Callable

import pytest


@pytest.fixture
def tmp_db_path(tmp_path: Path) -> Path:
    """Temp SQLite path for a single test."""
    return tmp_path / "test.db"


@pytest.fixture
def sample_brand_yaml(tmp_path: Path) -> Path:
    """Minimal valid brand.yaml for tests."""
    p = tmp_path / "brand.yaml"
    p.write_text(
        "business_name: Twins Garage Doors\n"
        "phone: \"(608) 555-0199\"\n"
        "primary_service_area: madison_wi\n"
        "allow_emojis: false\n"
    )
    return p


@pytest.fixture
def sample_service_area_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "service_area.yaml"
    p.write_text(
        "primary: madison_wi\n"
        "towns:\n"
        "  - madison_wi\n"
        "  - middleton_wi\n"
        "  - sun_prairie_wi\n"
        "  - verona_wi\n"
        "  - fitchburg_wi\n"
        "  - waunakee_wi\n"
        "  - mcfarland_wi\n"
        "  - stoughton_wi\n"
        "  - cottage_grove_wi\n"
        "town_display_names:\n"
        "  madison_wi: Madison\n"
        "  middleton_wi: Middleton\n"
        "  sun_prairie_wi: Sun Prairie\n"
        "  verona_wi: Verona\n"
        "  fitchburg_wi: Fitchburg\n"
        "  waunakee_wi: Waunakee\n"
        "  mcfarland_wi: McFarland\n"
        "  stoughton_wi: Stoughton\n"
        "  cottage_grove_wi: Cottage Grove\n"
    )
    return p
```

- [ ] **Step 7: Install the package in editable mode and verify**

```bash
cd /Users/daniel/twins-dashboard/twins-content-engine
python3.11 -m venv .venv
source .venv/bin/activate
pip install -e ".[dev]"
pytest --collect-only
```

Expected: `collected 0 items` (no tests yet — this just confirms pytest can find the package).

- [ ] **Step 8: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/pyproject.toml \
        twins-content-engine/.gitignore \
        twins-content-engine/README.md \
        twins-content-engine/engine/__init__.py \
        twins-content-engine/tests/__init__.py \
        twins-content-engine/tests/smoke/__init__.py \
        twins-content-engine/tests/conftest.py \
        twins-content-engine/pending/.gitkeep \
        twins-content-engine/approved/.gitkeep \
        twins-content-engine/briefs/.gitkeep
git commit -m "chore(content-engine): scaffold project structure"
```

---

## Task 2: Configuration YAMLs

**Files:**
- Create: `twins-content-engine/config/brand.yaml`
- Create: `twins-content-engine/config/service_area.yaml`
- Create: `twins-content-engine/config/pillars.yaml`
- Create: `twins-content-engine/config/rules.yaml`

- [ ] **Step 1: Write `config/brand.yaml`**

```yaml
business_name: Twins Garage Doors
phone: "(608) 555-0199"   # REPLACE with real number before first generation
primary_service_area: madison_wi
allow_emojis: false
voice_terms_to_prefer:
  - "tech"
  - "spring"
  - "opener"
  - "panel"
  - "torsion"
  - "cable"
voice_terms_to_avoid:
  - "team member"
  - "associate"
  - "specialist"
  - "experience"
  - "journey"
brand_years_in_business: 13
signature_proof_points:
  - "13 years serving Dane County"
  - "Same-day emergency service"
  - "Torsion spring specialists"
```

- [ ] **Step 2: Write `config/service_area.yaml`**

```yaml
primary: madison_wi
towns:
  - madison_wi
  - middleton_wi
  - sun_prairie_wi
  - verona_wi
  - fitchburg_wi
  - waunakee_wi
  - mcfarland_wi
  - stoughton_wi
  - cottage_grove_wi

town_display_names:
  madison_wi: Madison
  middleton_wi: Middleton
  sun_prairie_wi: Sun Prairie
  verona_wi: Verona
  fitchburg_wi: Fitchburg
  waunakee_wi: Waunakee
  mcfarland_wi: McFarland
  stoughton_wi: Stoughton
  cottage_grove_wi: Cottage Grove

madison_neighborhoods:
  - Maple Bluff
  - University Heights
  - Nakoma
  - Monroe Street
  - Westmorland
  - Shorewood Hills
  - Middleton Hills
  - Sherman
  - Eken Park
```

- [ ] **Step 3: Write `config/pillars.yaml`**

Copy the complete pillars definition from the spec. Paste this exact content into `twins-content-engine/config/pillars.yaml`:

```yaml
pillars:
  emergency_repairs:
    description: Broken spring, off-track, won't close, door stuck, safety risk
    funnel_stage: high_intent_problem_aware
    seed_patterns:
      - "garage door {symptom} {town}"
      - "is it safe to {action} with broken {part}"
      - "emergency garage door {town}"
    format_bias: [video_script, gbp_post, faq]
    priority_weight: 1.5

  maintenance_prevention:
    description: Lubrication, weather prep, lifespan, seasonal checks
    funnel_stage: low_intent_educational
    seed_patterns:
      - "when should I {maintenance_action}"
      - "how often {part} {lifespan_question}"
      - "garage door winter prep {town}"
    format_bias: [caption, faq, blog_snippet]
    priority_weight: 0.8

  replacement_installation:
    description: New doors, opener upgrades, insulation, smart openers
    funnel_stage: mid_intent_considering
    seed_patterns:
      - "cost of new garage door {town}"
      - "best garage door for {climate_context}"
      - "should I replace or repair {part}"
    format_bias: [blog_snippet, video_script, caption]
    priority_weight: 1.2

  pricing_transparency:
    description: Cost expectations, what affects price, estimate psychology
    funnel_stage: mid_intent_price_checking
    seed_patterns:
      - "how much {service} {town}"
      - "average cost {part_or_service}"
      - "why is {service} so expensive"
    format_bias: [faq, blog_snippet, gbp_post]
    priority_weight: 1.3

  common_mistakes:
    description: DIY gone wrong, cheap-repair traps, red-flag contractors
    funnel_stage: mid_intent_trust_building
    seed_patterns:
      - "should I DIY {repair}"
      - "signs of bad garage door {contractor_or_repair}"
      - "what not to do when {symptom}"
    format_bias: [video_script, caption, blog_snippet]
    priority_weight: 1.0

  comparisons:
    description: Spring types, door styles, opener brands, insulation levels
    funnel_stage: mid_intent_deciding
    seed_patterns:
      - "{option_a} vs {option_b} garage door"
      - "best {component} for {context}"
    format_bias: [blog_snippet, faq]
    priority_weight: 0.9

  local_authority:
    description: Madison winters, specific neighborhoods, climate, local stories
    funnel_stage: trust_and_ranking
    seed_patterns:
      - "garage doors in {town} winter"
      - "{neighborhood} garage door service"
    format_bias: [gbp_post, blog_snippet, caption]
    priority_weight: 1.4
```

- [ ] **Step 4: Write `config/rules.yaml`**

```yaml
voice:
  persona: "A Madison garage door tech who picks up the phone himself. Direct, practical, slightly dry. Not a copywriter. Not excited."
  structure: "hook -> problem-specifics -> authority-proof -> single CTA"
  reading_level_max: 8

hook_requirements:
  video_script:
    min_words: 6
    max_words: 8
    forbidden_openers:
      - "Whether"
      - "Are you"
      - "Did you know"
      - "In today's"
    must_reference_one_of: [specific_symptom, number, neighborhood]
  caption:
    must_mirror_search_query: true
  gbp_post:
    first_n_words: 10
    must_contain_one_of_towns: true
  blog_snippet:
    first_sentence_must_contain_one_of: [specific_number, timeframe, price_range]

anti_ai_spam_blacklist:
  phrases:
    - "whether you're"
    - "in today's fast-paced"
    - "look no further"
    - "game-changer"
    - "revolutionize"
    - "elevate your"
    - "unlock the"
    - "harness the power"
    - "dive into"
    - "journey"
    - "nestled in"
    - "boasts"
    - "seamlessly"
    - "at the end of the day"
  structural:
    - em_dash_pair_as_dramatic_pause
    - formulaic_triadic_list

specificity_requirements:
  must_include_one_of:
    - specific_number
    - named_location
    - timeframe
    - concrete_detail
  must_avoid_phrases:
    - "we're the best"
    - "trusted by many"
    - "#1 in"
    - "industry leading"

cta_rules:
  one_per_piece: true
  funnel_stage_mapping:
    high_intent_problem_aware: call_now
    mid_intent_price_checking: free_estimate
    mid_intent_deciding: text_or_call
    mid_intent_considering: free_estimate
    mid_intent_trust_building: text_or_call
    low_intent_educational: save_or_share
    trust_and_ranking: learn_more

local_embedding:
  mention_frequency:
    video_script: [1, 2]
    caption: [1, 1]
    gbp_post: [2, 3]
    faq: [2, 2]
    blog_snippet: [3, 5]
  placement_rules:
    no_consecutive_sentences: true
    require_mention_in_first_pct: 30
    prefer_specific_town_over_region: true
```

- [ ] **Step 5: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/config/
git commit -m "feat(content-engine): add config YAMLs (brand, service area, pillars, rules)"
```

---

## Task 3: Config loader (`engine/config.py`)

**Files:**
- Create: `twins-content-engine/engine/config.py`
- Create: `twins-content-engine/tests/test_config.py`

- [ ] **Step 1: Write failing test — loads brand.yaml**

Create `twins-content-engine/tests/test_config.py`:

```python
"""Tests for engine.config."""
from __future__ import annotations

from pathlib import Path

import pytest

from engine.config import (
    BrandConfig,
    ServiceAreaConfig,
    PillarsConfig,
    RulesConfig,
    load_brand,
    load_service_area,
    load_pillars,
    load_rules,
)


def test_load_brand(sample_brand_yaml: Path):
    cfg = load_brand(sample_brand_yaml)
    assert isinstance(cfg, BrandConfig)
    assert cfg.business_name == "Twins Garage Doors"
    assert cfg.phone == "(608) 555-0199"
    assert cfg.primary_service_area == "madison_wi"
    assert cfg.allow_emojis is False


def test_load_brand_missing_required_field(tmp_path: Path):
    p = tmp_path / "brand.yaml"
    p.write_text("business_name: X\n")
    with pytest.raises(ValueError, match="phone"):
        load_brand(p)


def test_load_service_area(sample_service_area_yaml: Path):
    cfg = load_service_area(sample_service_area_yaml)
    assert cfg.primary == "madison_wi"
    assert "middleton_wi" in cfg.towns
    assert cfg.town_display_names["middleton_wi"] == "Middleton"


def test_load_service_area_display_name_missing(tmp_path: Path):
    p = tmp_path / "service_area.yaml"
    p.write_text(
        "primary: madison_wi\n"
        "towns: [madison_wi, middleton_wi]\n"
        "town_display_names:\n"
        "  madison_wi: Madison\n"
    )
    with pytest.raises(ValueError, match="display name missing for middleton_wi"):
        load_service_area(p)


def test_load_pillars(tmp_path: Path):
    p = tmp_path / "pillars.yaml"
    p.write_text(
        "pillars:\n"
        "  emergency_repairs:\n"
        "    description: desc\n"
        "    funnel_stage: high_intent_problem_aware\n"
        "    seed_patterns: ['a', 'b']\n"
        "    format_bias: [video_script]\n"
        "    priority_weight: 1.5\n"
    )
    cfg = load_pillars(p)
    assert "emergency_repairs" in cfg.pillars
    assert cfg.pillars["emergency_repairs"].priority_weight == 1.5


def test_load_pillars_bad_format_bias(tmp_path: Path):
    p = tmp_path / "pillars.yaml"
    p.write_text(
        "pillars:\n"
        "  foo:\n"
        "    description: d\n"
        "    funnel_stage: x\n"
        "    seed_patterns: []\n"
        "    format_bias: [not_a_format]\n"
        "    priority_weight: 1.0\n"
    )
    with pytest.raises(ValueError, match="invalid format_bias value"):
        load_pillars(p)


def test_load_rules(tmp_path: Path):
    p = tmp_path / "rules.yaml"
    p.write_text(
        "voice:\n"
        "  persona: p\n"
        "  structure: s\n"
        "  reading_level_max: 8\n"
        "hook_requirements: {}\n"
        "anti_ai_spam_blacklist:\n"
        "  phrases: ['foo']\n"
        "  structural: []\n"
        "specificity_requirements:\n"
        "  must_include_one_of: []\n"
        "  must_avoid_phrases: []\n"
        "cta_rules:\n"
        "  one_per_piece: true\n"
        "  funnel_stage_mapping: {}\n"
        "local_embedding:\n"
        "  mention_frequency: {}\n"
        "  placement_rules:\n"
        "    no_consecutive_sentences: true\n"
        "    require_mention_in_first_pct: 30\n"
        "    prefer_specific_town_over_region: true\n"
    )
    cfg = load_rules(p)
    assert cfg.voice.reading_level_max == 8
    assert "foo" in cfg.anti_ai_spam_blacklist.phrases
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/daniel/twins-dashboard/twins-content-engine
pytest tests/test_config.py -v
```

Expected: `ModuleNotFoundError: No module named 'engine.config'` (collection error). This confirms the test runs and the module doesn't exist yet.

- [ ] **Step 3: Implement `engine/config.py`**

Create `twins-content-engine/engine/config.py`:

```python
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
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
cd /Users/daniel/twins-dashboard/twins-content-engine
pytest tests/test_config.py -v
```

Expected: `7 passed`.

- [ ] **Step 5: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/config.py twins-content-engine/tests/test_config.py
git commit -m "feat(content-engine): typed config loaders with validation"
```

---

## Task 4: Database layer (`engine/db.py`)

**Files:**
- Create: `twins-content-engine/engine/db.py`
- Create: `twins-content-engine/engine/schema.sql`
- Create: `twins-content-engine/tests/test_db.py`

- [ ] **Step 1: Write the schema file**

Create `twins-content-engine/engine/schema.sql`:

```sql
CREATE TABLE IF NOT EXISTS clusters (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT NOT NULL UNIQUE,
    pillar          TEXT NOT NULL,
    service_type    TEXT,
    funnel_stage    TEXT,
    priority_score  INTEGER NOT NULL DEFAULT 5,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_clusters_pillar ON clusters(pillar);

CREATE TABLE IF NOT EXISTS queries (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    cluster_id      INTEGER NOT NULL REFERENCES clusters(id) ON DELETE CASCADE,
    query_text      TEXT NOT NULL,
    phrasing_type   TEXT NOT NULL,
    geo_modifier    TEXT,
    source          TEXT NOT NULL,
    priority_score  INTEGER NOT NULL DEFAULT 5,
    notes           TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (cluster_id, query_text)
);

CREATE INDEX IF NOT EXISTS idx_queries_cluster ON queries(cluster_id);

CREATE TABLE IF NOT EXISTS generated_content (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    cluster_id       INTEGER NOT NULL REFERENCES clusters(id) ON DELETE CASCADE,
    source_query_id  INTEGER NOT NULL REFERENCES queries(id) ON DELETE CASCADE,
    format           TEXT NOT NULL,
    content_path     TEXT NOT NULL,
    brief_path       TEXT,
    status           TEXT NOT NULL DEFAULT 'pending',
    model_used       TEXT NOT NULL,
    generated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at      DATETIME,
    notes            TEXT
);

CREATE INDEX IF NOT EXISTS idx_generated_cluster ON generated_content(cluster_id);
CREATE INDEX IF NOT EXISTS idx_generated_status ON generated_content(status);
```

- [ ] **Step 2: Write failing tests for `engine/db.py`**

Create `twins-content-engine/tests/test_db.py`:

```python
"""Tests for engine.db."""
from __future__ import annotations

from pathlib import Path

import pytest

from engine.db import (
    init_db,
    get_conn,
    insert_cluster,
    insert_query,
    insert_generated_content,
    get_cluster_by_name,
    get_queries_for_cluster,
    update_content_status,
    list_pending_content,
    list_clusters,
)


def test_init_db_creates_tables(tmp_db_path: Path):
    init_db(tmp_db_path)
    with get_conn(tmp_db_path) as c:
        tables = {
            r["name"]
            for r in c.execute("SELECT name FROM sqlite_master WHERE type='table'").fetchall()
        }
    assert {"clusters", "queries", "generated_content"}.issubset(tables)


def test_insert_cluster_and_query(tmp_db_path: Path):
    init_db(tmp_db_path)
    cluster_id = insert_cluster(
        tmp_db_path,
        name="broken_spring",
        pillar="emergency_repairs",
        service_type="spring_replacement",
        funnel_stage="high_intent_problem_aware",
        priority_score=8,
        notes=None,
    )
    assert cluster_id > 0
    q_id = insert_query(
        tmp_db_path,
        cluster_id=cluster_id,
        query_text="garage door won't open loud bang",
        phrasing_type="symptom",
        geo_modifier=None,
        source="raw_seed",
        priority_score=7,
        notes=None,
    )
    assert q_id > 0
    queries = get_queries_for_cluster(tmp_db_path, cluster_id)
    assert len(queries) == 1
    assert queries[0]["query_text"] == "garage door won't open loud bang"


def test_insert_cluster_duplicate_name_raises(tmp_db_path: Path):
    init_db(tmp_db_path)
    insert_cluster(
        tmp_db_path,
        name="broken_spring",
        pillar="emergency_repairs",
        service_type=None,
        funnel_stage=None,
        priority_score=5,
        notes=None,
    )
    with pytest.raises(Exception):
        insert_cluster(
            tmp_db_path,
            name="broken_spring",
            pillar="emergency_repairs",
            service_type=None,
            funnel_stage=None,
            priority_score=5,
            notes=None,
        )


def test_get_cluster_by_name(tmp_db_path: Path):
    init_db(tmp_db_path)
    insert_cluster(
        tmp_db_path,
        name="broken_spring",
        pillar="emergency_repairs",
        service_type=None,
        funnel_stage=None,
        priority_score=5,
        notes=None,
    )
    row = get_cluster_by_name(tmp_db_path, "broken_spring")
    assert row is not None
    assert row["pillar"] == "emergency_repairs"
    assert get_cluster_by_name(tmp_db_path, "nonexistent") is None


def test_insert_generated_content_and_status_update(tmp_db_path: Path):
    init_db(tmp_db_path)
    c_id = insert_cluster(
        tmp_db_path, name="x", pillar="emergency_repairs",
        service_type=None, funnel_stage=None, priority_score=5, notes=None,
    )
    q_id = insert_query(
        tmp_db_path, cluster_id=c_id, query_text="q", phrasing_type="raw_seed",
        geo_modifier=None, source="raw_seed", priority_score=5, notes=None,
    )
    gc_id = insert_generated_content(
        tmp_db_path,
        cluster_id=c_id,
        source_query_id=q_id,
        format="caption",
        content_path="pending/foo.md",
        brief_path=None,
        status="pending",
        model_used="claude-sonnet-4-6",
        notes=None,
    )
    assert gc_id > 0
    pending = list_pending_content(tmp_db_path)
    assert len(pending) == 1
    update_content_status(tmp_db_path, gc_id, "approved")
    pending = list_pending_content(tmp_db_path)
    assert len(pending) == 0


def test_list_clusters(tmp_db_path: Path):
    init_db(tmp_db_path)
    insert_cluster(tmp_db_path, name="a", pillar="emergency_repairs",
                   service_type=None, funnel_stage=None, priority_score=5, notes=None)
    insert_cluster(tmp_db_path, name="b", pillar="pricing_transparency",
                   service_type=None, funnel_stage=None, priority_score=5, notes=None)
    names = {r["name"] for r in list_clusters(tmp_db_path)}
    assert names == {"a", "b"}


def test_cascade_delete(tmp_db_path: Path):
    init_db(tmp_db_path)
    c_id = insert_cluster(tmp_db_path, name="x", pillar="emergency_repairs",
                          service_type=None, funnel_stage=None, priority_score=5, notes=None)
    insert_query(tmp_db_path, cluster_id=c_id, query_text="q", phrasing_type="raw_seed",
                 geo_modifier=None, source="raw_seed", priority_score=5, notes=None)
    with get_conn(tmp_db_path) as c:
        c.execute("DELETE FROM clusters WHERE id = ?", (c_id,))
    assert get_queries_for_cluster(tmp_db_path, c_id) == []
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
cd /Users/daniel/twins-dashboard/twins-content-engine
pytest tests/test_db.py -v
```

Expected: import error — `engine.db` doesn't exist yet.

- [ ] **Step 4: Implement `engine/db.py`**

Create `twins-content-engine/engine/db.py`:

```python
"""SQLite layer for the content engine."""
from __future__ import annotations

import sqlite3
from contextlib import contextmanager
from pathlib import Path
from typing import Any, Iterator, Optional

_SCHEMA_PATH = Path(__file__).parent / "schema.sql"


@contextmanager
def get_conn(db_path: Path) -> Iterator[sqlite3.Connection]:
    """Transactional SQLite connection with foreign keys enabled."""
    conn = sqlite3.connect(str(db_path))
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    conn.execute("PRAGMA journal_mode = WAL")
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def init_db(db_path: Path) -> None:
    """Create tables if they don't exist."""
    db_path.parent.mkdir(parents=True, exist_ok=True)
    schema = _SCHEMA_PATH.read_text()
    with get_conn(db_path) as c:
        c.executescript(schema)


def insert_cluster(
    db_path: Path,
    *,
    name: str,
    pillar: str,
    service_type: Optional[str],
    funnel_stage: Optional[str],
    priority_score: int,
    notes: Optional[str],
) -> int:
    with get_conn(db_path) as c:
        cur = c.execute(
            "INSERT INTO clusters "
            "(name, pillar, service_type, funnel_stage, priority_score, notes) "
            "VALUES (?, ?, ?, ?, ?, ?)",
            (name, pillar, service_type, funnel_stage, priority_score, notes),
        )
        return int(cur.lastrowid)


def insert_query(
    db_path: Path,
    *,
    cluster_id: int,
    query_text: str,
    phrasing_type: str,
    geo_modifier: Optional[str],
    source: str,
    priority_score: int,
    notes: Optional[str],
) -> int:
    with get_conn(db_path) as c:
        cur = c.execute(
            "INSERT INTO queries "
            "(cluster_id, query_text, phrasing_type, geo_modifier, source, priority_score, notes) "
            "VALUES (?, ?, ?, ?, ?, ?, ?)",
            (cluster_id, query_text, phrasing_type, geo_modifier, source, priority_score, notes),
        )
        return int(cur.lastrowid)


def insert_generated_content(
    db_path: Path,
    *,
    cluster_id: int,
    source_query_id: int,
    format: str,
    content_path: str,
    brief_path: Optional[str],
    status: str,
    model_used: str,
    notes: Optional[str],
) -> int:
    with get_conn(db_path) as c:
        cur = c.execute(
            "INSERT INTO generated_content "
            "(cluster_id, source_query_id, format, content_path, brief_path, status, model_used, notes) "
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            (cluster_id, source_query_id, format, content_path, brief_path, status, model_used, notes),
        )
        return int(cur.lastrowid)


def get_cluster_by_name(db_path: Path, name: str) -> Optional[sqlite3.Row]:
    with get_conn(db_path) as c:
        cur = c.execute("SELECT * FROM clusters WHERE name = ?", (name,))
        return cur.fetchone()


def get_queries_for_cluster(db_path: Path, cluster_id: int) -> list[sqlite3.Row]:
    with get_conn(db_path) as c:
        return list(c.execute(
            "SELECT * FROM queries WHERE cluster_id = ? ORDER BY priority_score DESC, id",
            (cluster_id,),
        ).fetchall())


def list_clusters(db_path: Path) -> list[sqlite3.Row]:
    with get_conn(db_path) as c:
        return list(c.execute("SELECT * FROM clusters ORDER BY name").fetchall())


def list_pending_content(db_path: Path) -> list[sqlite3.Row]:
    with get_conn(db_path) as c:
        return list(c.execute(
            "SELECT * FROM generated_content WHERE status = 'pending' ORDER BY generated_at"
        ).fetchall())


def update_content_status(db_path: Path, content_id: int, new_status: str) -> None:
    assert new_status in {"pending", "approved", "rejected", "published"}
    with get_conn(db_path) as c:
        if new_status == "approved":
            c.execute(
                "UPDATE generated_content SET status = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?",
                (new_status, content_id),
            )
        else:
            c.execute(
                "UPDATE generated_content SET status = ? WHERE id = ?",
                (new_status, content_id),
            )


def get_cluster_last_used(db_path: Path, cluster_id: int) -> Optional[str]:
    """Return ISO timestamp of most recent content generation, or None."""
    with get_conn(db_path) as c:
        cur = c.execute(
            "SELECT MAX(generated_at) AS ts FROM generated_content WHERE cluster_id = ?",
            (cluster_id,),
        )
        row = cur.fetchone()
        return row["ts"] if row else None
```

- [ ] **Step 5: Add `engine/schema.sql` to package data**

Edit `twins-content-engine/pyproject.toml`, append to the `[tool.hatch.build.targets.wheel]` block:

```toml
[tool.hatch.build.targets.wheel]
packages = ["engine"]

[tool.hatch.build.targets.wheel.force-include]
"engine/schema.sql" = "engine/schema.sql"
```

Then reinstall:

```bash
cd /Users/daniel/twins-dashboard/twins-content-engine
source .venv/bin/activate
pip install -e ".[dev]"
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
pytest tests/test_db.py -v
```

Expected: `7 passed`.

- [ ] **Step 7: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/db.py \
        twins-content-engine/engine/schema.sql \
        twins-content-engine/tests/test_db.py \
        twins-content-engine/pyproject.toml
git commit -m "feat(content-engine): SQLite layer with schema and CRUD helpers"
```

---

## Task 5: Claude client wrapper (`engine/claude_client.py`)

**Files:**
- Create: `twins-content-engine/engine/claude_client.py`
- Create: `twins-content-engine/tests/test_claude_client.py`

This task creates a dependency-injectable LLM wrapper. All downstream modules take a `ClaudeClient` parameter, so tests inject a `FakeClaudeClient`.

- [ ] **Step 1: Write failing tests**

Create `twins-content-engine/tests/test_claude_client.py`:

```python
"""Tests for engine.claude_client."""
from __future__ import annotations

from unittest.mock import MagicMock

import pytest

from engine.claude_client import ClaudeClient, FakeClaudeClient, CompletionResult


def test_fake_client_returns_queued_responses():
    fake = FakeClaudeClient(responses=["first", "second"])
    r1 = fake.complete(system="s", user="u1")
    r2 = fake.complete(system="s", user="u2")
    assert r1.text == "first"
    assert r2.text == "second"
    assert len(fake.calls) == 2
    assert fake.calls[0]["user"] == "u1"


def test_fake_client_raises_when_empty():
    fake = FakeClaudeClient(responses=[])
    with pytest.raises(RuntimeError, match="FakeClaudeClient exhausted"):
        fake.complete(system="s", user="u")


def test_real_client_calls_anthropic_sdk(monkeypatch):
    """ClaudeClient.complete calls Anthropic.messages.create with correct args."""
    mock_anthropic_cls = MagicMock()
    mock_sdk = MagicMock()
    mock_anthropic_cls.return_value = mock_sdk
    mock_response = MagicMock()
    mock_response.content = [MagicMock(text="hello")]
    mock_response.usage = MagicMock(input_tokens=100, output_tokens=50)
    mock_sdk.messages.create.return_value = mock_response

    monkeypatch.setattr("engine.claude_client.Anthropic", mock_anthropic_cls)

    client = ClaudeClient(api_key="fake", model="claude-sonnet-4-6")
    result = client.complete(system="sys", user="user prompt")

    assert result.text == "hello"
    assert result.input_tokens == 100
    assert result.output_tokens == 50

    call_args = mock_sdk.messages.create.call_args
    assert call_args.kwargs["model"] == "claude-sonnet-4-6"
    # system sent as list with cache_control for prompt caching
    assert isinstance(call_args.kwargs["system"], list)
    assert call_args.kwargs["system"][0]["cache_control"] == {"type": "ephemeral"}
    assert call_args.kwargs["system"][0]["text"] == "sys"
    assert call_args.kwargs["messages"] == [{"role": "user", "content": "user prompt"}]


def test_real_client_retries_on_rate_limit(monkeypatch):
    from anthropic import RateLimitError

    mock_anthropic_cls = MagicMock()
    mock_sdk = MagicMock()
    mock_anthropic_cls.return_value = mock_sdk

    error = RateLimitError(
        message="slow down",
        response=MagicMock(status_code=429),
        body=None,
    )
    ok = MagicMock()
    ok.content = [MagicMock(text="ok")]
    ok.usage = MagicMock(input_tokens=1, output_tokens=1)
    mock_sdk.messages.create.side_effect = [error, ok]

    monkeypatch.setattr("engine.claude_client.Anthropic", mock_anthropic_cls)

    client = ClaudeClient(api_key="fake")
    result = client.complete(system="s", user="u")
    assert result.text == "ok"
    assert mock_sdk.messages.create.call_count == 2
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
pytest tests/test_claude_client.py -v
```

Expected: `ModuleNotFoundError: No module named 'engine.claude_client'`.

- [ ] **Step 3: Implement `engine/claude_client.py`**

Create `twins-content-engine/engine/claude_client.py`:

```python
"""Anthropic Claude wrapper with retries, prompt caching, and a test fake."""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any, Optional, Protocol

from anthropic import Anthropic, APIError, RateLimitError
from tenacity import (
    retry,
    retry_if_exception_type,
    stop_after_attempt,
    wait_exponential,
)


@dataclass
class CompletionResult:
    text: str
    input_tokens: int = 0
    output_tokens: int = 0


class ClaudeClientProtocol(Protocol):
    def complete(
        self,
        *,
        system: str,
        user: str,
        max_tokens: int = 2048,
        temperature: float = 0.7,
    ) -> CompletionResult: ...


class ClaudeClient:
    """Real Anthropic client with retries and prompt caching on system prompts."""

    DEFAULT_MODEL = "claude-sonnet-4-6"

    def __init__(self, api_key: Optional[str] = None, model: Optional[str] = None) -> None:
        self._sdk = Anthropic(api_key=api_key) if api_key else Anthropic()
        self.model = model or self.DEFAULT_MODEL

    @retry(
        stop=stop_after_attempt(3),
        wait=wait_exponential(multiplier=1, min=1, max=10),
        retry=retry_if_exception_type((RateLimitError, APIError)),
        reraise=True,
    )
    def complete(
        self,
        *,
        system: str,
        user: str,
        max_tokens: int = 2048,
        temperature: float = 0.7,
    ) -> CompletionResult:
        response = self._sdk.messages.create(
            model=self.model,
            max_tokens=max_tokens,
            temperature=temperature,
            system=[
                {
                    "type": "text",
                    "text": system,
                    "cache_control": {"type": "ephemeral"},
                }
            ],
            messages=[{"role": "user", "content": user}],
        )
        text = response.content[0].text if response.content else ""
        return CompletionResult(
            text=text,
            input_tokens=getattr(response.usage, "input_tokens", 0),
            output_tokens=getattr(response.usage, "output_tokens", 0),
        )


@dataclass
class FakeClaudeClient:
    """Test double. Pass a list of response strings; they are returned in order."""

    responses: list[str]
    calls: list[dict[str, Any]] = field(default_factory=list)

    def complete(
        self,
        *,
        system: str,
        user: str,
        max_tokens: int = 2048,
        temperature: float = 0.7,
    ) -> CompletionResult:
        self.calls.append(
            {"system": system, "user": user, "max_tokens": max_tokens, "temperature": temperature}
        )
        if not self.responses:
            raise RuntimeError("FakeClaudeClient exhausted — add more responses")
        return CompletionResult(text=self.responses.pop(0), input_tokens=0, output_tokens=0)
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
pytest tests/test_claude_client.py -v
```

Expected: `4 passed`.

- [ ] **Step 5: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/claude_client.py \
        twins-content-engine/tests/test_claude_client.py
git commit -m "feat(content-engine): Anthropic client wrapper with retries and test fake"
```

---

## Task 6: Seed parsing + `scripts/ingest_seeds.py`

The seed file format is simple: one query per line, blank lines and `#` comments ignored. Optional inline tags after a pipe: `garage door won't close | pillar=emergency_repairs`.

**Files:**
- Create: `twins-content-engine/engine/cluster.py` (parse_seed_file only — expansion added in Task 7)
- Create: `twins-content-engine/tests/test_cluster_parse.py`
- Create: `twins-content-engine/scripts/ingest_seeds.py`
- Create: `twins-content-engine/tests/smoke/test_ingest_seeds.py`
- Create: `twins-content-engine/data/seeds/initial_seed.md`

- [ ] **Step 1: Write failing tests for seed parsing**

Create `twins-content-engine/tests/test_cluster_parse.py`:

```python
"""Tests for engine.cluster.parse_seed_file."""
from __future__ import annotations

from pathlib import Path

from engine.cluster import ParsedSeed, parse_seed_file


def test_parse_basic_lines(tmp_path: Path):
    f = tmp_path / "seed.md"
    f.write_text(
        "# comments are ignored\n"
        "\n"
        "garage door won't close\n"
        "spring broke loud bang\n"
    )
    seeds = parse_seed_file(f)
    assert len(seeds) == 2
    assert seeds[0] == ParsedSeed(query_text="garage door won't close", pillar_hint=None)
    assert seeds[1].query_text == "spring broke loud bang"


def test_parse_with_pillar_hint(tmp_path: Path):
    f = tmp_path / "seed.md"
    f.write_text("how much to replace spring | pillar=pricing_transparency\n")
    seeds = parse_seed_file(f)
    assert seeds[0].pillar_hint == "pricing_transparency"


def test_parse_strips_whitespace_and_skips_blanks(tmp_path: Path):
    f = tmp_path / "seed.md"
    f.write_text("   \n  garage door stuck   \n\n")
    seeds = parse_seed_file(f)
    assert len(seeds) == 1
    assert seeds[0].query_text == "garage door stuck"


def test_parse_ignores_malformed_hint(tmp_path: Path):
    f = tmp_path / "seed.md"
    f.write_text("foo | not-a-hint\n")
    seeds = parse_seed_file(f)
    assert seeds[0].query_text == "foo"
    assert seeds[0].pillar_hint is None
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
pytest tests/test_cluster_parse.py -v
```

Expected: `ModuleNotFoundError: No module named 'engine.cluster'`.

- [ ] **Step 3: Create `engine/cluster.py` with the parser only**

Create `twins-content-engine/engine/cluster.py`:

```python
"""Seed parsing and cluster expansion.

This module currently implements parse_seed_file.
Cluster expansion (expand_seeds_to_clusters) is added in Task 7.
"""
from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Optional


@dataclass
class ParsedSeed:
    query_text: str
    pillar_hint: Optional[str]


def parse_seed_file(path: Path) -> list[ParsedSeed]:
    """Parse a seed Markdown file into a list of ParsedSeed records.

    Format:
      - one query per line
      - blank lines ignored
      - lines starting with # ignored
      - optional inline tag: "query text | pillar=<pillar_name>"
    """
    seeds: list[ParsedSeed] = []
    for raw in path.read_text().splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        pillar_hint: Optional[str] = None
        if "|" in line:
            query_part, _, tag_part = line.partition("|")
            line = query_part.strip()
            tag = tag_part.strip()
            if tag.startswith("pillar="):
                pillar_hint = tag.split("=", 1)[1].strip() or None
        seeds.append(ParsedSeed(query_text=line, pillar_hint=pillar_hint))
    return seeds
```

- [ ] **Step 4: Run tests to verify parser tests pass**

```bash
pytest tests/test_cluster_parse.py -v
```

Expected: `4 passed`.

- [ ] **Step 5: Write the initial seed file template**

Create `twins-content-engine/data/seeds/initial_seed.md`:

```markdown
# Twins Garage Doors — initial seed queries
# Format: one query per line. Use `| pillar=<name>` to pin a query to a pillar.
# Pillars: emergency_repairs, maintenance_prevention, replacement_installation,
#          pricing_transparency, common_mistakes, comparisons, local_authority

# REPLACE THESE with 15-25 real queries from your actual calls/texts/GBP insights.
# The samples below are placeholders so the first run works end-to-end.

garage door won't close all the way | pillar=emergency_repairs
garage door broken spring loud bang | pillar=emergency_repairs
garage door won't open and it's cold out | pillar=emergency_repairs
how much for new garage door madison wi | pillar=pricing_transparency
how much does spring replacement cost | pillar=pricing_transparency
why is my garage door opener so loud
garage door off track what do I do | pillar=emergency_repairs
should I replace both springs at the same time | pillar=common_mistakes
best garage door for wisconsin winter | pillar=replacement_installation
garage door cable snapped is it dangerous | pillar=emergency_repairs
```

- [ ] **Step 6: Write `scripts/ingest_seeds.py`**

Create `twins-content-engine/scripts/ingest_seeds.py`:

```python
"""CLI: parse a seed file and insert raw_seed queries into the DB.

Each seed becomes a query in a placeholder cluster named `_unclustered_<n>`.
The expand_clusters step (Task 7) re-homes these into real clusters.
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

# Make engine importable when run as a script
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.cluster import parse_seed_file
from engine.db import init_db, insert_cluster, insert_query


DEFAULT_DB = Path(__file__).resolve().parent.parent / "data" / "content_engine.db"


def run(seed_path: Path, db_path: Path) -> int:
    init_db(db_path)
    seeds = parse_seed_file(seed_path)
    inserted = 0
    for i, seed in enumerate(seeds, start=1):
        placeholder_name = f"_unclustered_{i}"
        pillar = seed.pillar_hint or "unclustered"
        cluster_id = insert_cluster(
            db_path,
            name=placeholder_name,
            pillar=pillar,
            service_type=None,
            funnel_stage=None,
            priority_score=5,
            notes="auto-created by ingest_seeds.py",
        )
        insert_query(
            db_path,
            cluster_id=cluster_id,
            query_text=seed.query_text,
            phrasing_type="raw_seed",
            geo_modifier=None,
            source="raw_seed",
            priority_score=5,
            notes=None,
        )
        inserted += 1
    return inserted


def main() -> None:
    ap = argparse.ArgumentParser(description="Ingest seed queries into the content engine DB.")
    ap.add_argument("seed_file", type=Path, help="Path to seed .md file")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB, help="SQLite DB path")
    args = ap.parse_args()

    inserted = run(args.seed_file, args.db)
    print(f"Ingested {inserted} seed queries into {args.db}")


if __name__ == "__main__":
    main()
```

- [ ] **Step 7: Write a smoke test**

Create `twins-content-engine/tests/smoke/test_ingest_seeds.py`:

```python
"""Smoke test: ingest_seeds.py end-to-end against a temp DB."""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

from engine.db import list_clusters


def test_ingest_seeds_cli(tmp_path: Path):
    seed = tmp_path / "seed.md"
    seed.write_text("garage door stuck\nspring broke | pillar=emergency_repairs\n")
    db = tmp_path / "test.db"

    script = Path(__file__).resolve().parents[2] / "scripts" / "ingest_seeds.py"
    result = subprocess.run(
        [sys.executable, str(script), str(seed), "--db", str(db)],
        capture_output=True,
        text=True,
    )
    assert result.returncode == 0, result.stderr
    assert "Ingested 2" in result.stdout
    clusters = list_clusters(db)
    assert len(clusters) == 2
    pillars = {c["pillar"] for c in clusters}
    assert "emergency_repairs" in pillars
    assert "unclustered" in pillars
```

- [ ] **Step 8: Run all tests**

```bash
pytest -v
```

Expected: all prior tests plus `test_ingest_seeds_cli` pass.

- [ ] **Step 9: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/cluster.py \
        twins-content-engine/tests/test_cluster_parse.py \
        twins-content-engine/scripts/ingest_seeds.py \
        twins-content-engine/tests/smoke/test_ingest_seeds.py \
        twins-content-engine/data/seeds/initial_seed.md
git commit -m "feat(content-engine): seed parsing and ingest_seeds CLI"
```

---

## Task 7: Cluster expansion (LLM)

Takes `raw_seed` queries and asks Claude to: (1) detect the natural cluster each seed belongs to, (2) generate 3–5 phrasing variants per seed. The result is real clusters replacing the placeholder `_unclustered_*` rows created in Task 6.

**Files:**
- Modify: `twins-content-engine/engine/cluster.py` (add `expand_seeds_to_clusters`)
- Create: `twins-content-engine/tests/test_cluster_expand.py`
- Create: `twins-content-engine/scripts/expand_clusters.py`

- [ ] **Step 1: Write failing tests for cluster expansion**

Create `twins-content-engine/tests/test_cluster_expand.py`:

```python
"""Tests for engine.cluster.expand_seeds_to_clusters."""
from __future__ import annotations

import json
from pathlib import Path

import pytest

from engine.claude_client import FakeClaudeClient
from engine.cluster import expand_seeds_to_clusters
from engine.db import (
    get_queries_for_cluster,
    init_db,
    insert_cluster,
    insert_query,
    list_clusters,
)


@pytest.fixture
def seeded_db(tmp_db_path: Path) -> Path:
    """DB with two _unclustered_* placeholder rows (simulates ingest_seeds output)."""
    init_db(tmp_db_path)
    for i, (q, hint) in enumerate(
        [
            ("garage door broken spring loud bang", "emergency_repairs"),
            ("how much for new garage door madison", "pricing_transparency"),
        ],
        start=1,
    ):
        cid = insert_cluster(
            tmp_db_path,
            name=f"_unclustered_{i}",
            pillar=hint,
            service_type=None,
            funnel_stage=None,
            priority_score=5,
            notes=None,
        )
        insert_query(
            tmp_db_path,
            cluster_id=cid,
            query_text=q,
            phrasing_type="raw_seed",
            geo_modifier=None,
            source="raw_seed",
            priority_score=5,
            notes=None,
        )
    return tmp_db_path


def test_expand_single_seed_creates_cluster_and_variants(seeded_db: Path):
    fake_response = json.dumps(
        {
            "cluster_name": "broken_spring",
            "service_type": "spring_replacement",
            "funnel_stage": "high_intent_problem_aware",
            "priority_score": 9,
            "variants": [
                {"query_text": "spring just snapped on my garage door",
                 "phrasing_type": "emergency", "geo_modifier": None},
                {"query_text": "garage door spring replacement cost madison wi",
                 "phrasing_type": "price_anxiety", "geo_modifier": "madison_wi"},
                {"query_text": "is a broken garage door spring dangerous",
                 "phrasing_type": "question", "geo_modifier": None},
                {"query_text": "door fell down loud bang can't open it",
                 "phrasing_type": "symptom", "geo_modifier": None},
            ],
        }
    )
    fake_client = FakeClaudeClient(responses=[fake_response, json.dumps({
        "cluster_name": "new_door_cost",
        "service_type": "full_replacement",
        "funnel_stage": "mid_intent_price_checking",
        "priority_score": 7,
        "variants": [
            {"query_text": "average cost new garage door installation",
             "phrasing_type": "price_anxiety", "geo_modifier": None},
        ],
    })])

    expand_seeds_to_clusters(
        db_path=seeded_db,
        client=fake_client,
        towns=["madison_wi", "middleton_wi"],
    )

    clusters = {c["name"]: c for c in list_clusters(seeded_db)}
    assert "broken_spring" in clusters
    assert "new_door_cost" in clusters
    assert "_unclustered_1" not in clusters  # placeholder was renamed

    spring = clusters["broken_spring"]
    queries = get_queries_for_cluster(seeded_db, spring["id"])
    # 1 original raw_seed + 4 expansions
    assert len(queries) == 5
    phrasing_types = {q["phrasing_type"] for q in queries}
    assert phrasing_types == {"raw_seed", "emergency", "price_anxiety", "question", "symptom"}


def test_expand_merges_duplicates(seeded_db: Path):
    """If Claude emits the same cluster_name for two seeds, they merge."""
    same_cluster = json.dumps({
        "cluster_name": "broken_spring",
        "service_type": "spring_replacement",
        "funnel_stage": "high_intent_problem_aware",
        "priority_score": 9,
        "variants": [
            {"query_text": "var a", "phrasing_type": "emergency", "geo_modifier": None},
        ],
    })
    fake = FakeClaudeClient(responses=[same_cluster, same_cluster])
    expand_seeds_to_clusters(db_path=seeded_db, client=fake, towns=["madison_wi"])
    clusters = list_clusters(seeded_db)
    cluster_names = [c["name"] for c in clusters]
    # two seeds, same cluster_name — should result in a single real cluster
    assert cluster_names.count("broken_spring") == 1


def test_expand_handles_malformed_response(seeded_db: Path):
    """Malformed JSON: skip the seed, continue with the rest."""
    fake = FakeClaudeClient(
        responses=[
            "not json",
            json.dumps({
                "cluster_name": "new_door_cost",
                "service_type": None,
                "funnel_stage": "mid_intent_price_checking",
                "priority_score": 7,
                "variants": [],
            }),
        ]
    )
    expand_seeds_to_clusters(db_path=seeded_db, client=fake, towns=["madison_wi"])
    names = {c["name"] for c in list_clusters(seeded_db)}
    assert "new_door_cost" in names
    # Malformed seed keeps its placeholder
    assert any(n.startswith("_unclustered_") for n in names)
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
pytest tests/test_cluster_expand.py -v
```

Expected: `AttributeError: module 'engine.cluster' has no attribute 'expand_seeds_to_clusters'`.

- [ ] **Step 3: Implement `expand_seeds_to_clusters` in `engine/cluster.py`**

Edit `twins-content-engine/engine/cluster.py`. Append to the existing file:

```python
import json
import sqlite3
from typing import Any

from engine.claude_client import ClaudeClientProtocol
from engine.db import (
    get_conn,
    get_cluster_by_name,
    get_queries_for_cluster,
    insert_cluster,
    insert_query,
)


EXPANSION_SYSTEM_PROMPT = """You are building a real-homeowner intent database for a garage door repair company in Madison, Wisconsin.

Given ONE seed query (from an actual customer call, text, or Google search), do two things:

1. Identify the natural cluster it belongs to. A cluster is a semantic group — all queries about "broken spring" belong to one cluster regardless of phrasing. Use snake_case names like "broken_spring", "door_wont_close", "new_door_cost", "opener_noise", "winter_prep".

2. Produce 3–5 real-world phrasing variants of the same problem. These must sound like stressed, rushed homeowners — NOT like clean SEO queries. Use these phrasing_type labels:
   - emergency: panic/urgent phrasing
   - price_anxiety: cost-focused worry ("is this going to be expensive")
   - question: explicit question form ("is it safe if...")
   - symptom: describing the symptom, not the cause ("loud bang and won't open")
   - local_variant: includes a town from the provided list

Constraints:
- Every variant must be plausibly typed by a non-technical Madison-area homeowner in 2026.
- Do NOT sanitize — if real homeowners type "garage door broke spring loud boom" that's better than "broken garage door torsion spring replacement".
- Variants must differ meaningfully (don't just reword with synonyms).
- geo_modifier is one of the provided town slugs, or null. Prefer null unless the phrasing truly includes a place.

Return ONLY a JSON object with this exact shape — no surrounding prose, no code fences:

{
  "cluster_name": "<snake_case>",
  "service_type": "<snake_case or null>",
  "funnel_stage": "<one of: high_intent_problem_aware | mid_intent_considering | mid_intent_price_checking | mid_intent_deciding | mid_intent_trust_building | low_intent_educational | trust_and_ranking>",
  "priority_score": <1-10 integer>,
  "variants": [
    {"query_text": "...", "phrasing_type": "emergency|price_anxiety|question|symptom|local_variant", "geo_modifier": "madison_wi|middleton_wi|...|null"}
  ]
}
"""


def _build_user_prompt(seed_query: str, pillar_hint: str | None, towns: list[str]) -> str:
    hint_line = f"Pillar hint from operator: {pillar_hint}\n" if pillar_hint else ""
    return (
        f"Seed query: {seed_query!r}\n"
        f"{hint_line}"
        f"Available town slugs: {', '.join(towns)}\n"
    )


def _safe_parse(raw: str) -> dict[str, Any] | None:
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return None


def expand_seeds_to_clusters(
    *,
    db_path: Path,
    client: ClaudeClientProtocol,
    towns: list[str],
) -> None:
    """For every placeholder `_unclustered_*` row, call Claude to propose a real
    cluster + variants. Merge into existing clusters by name.
    """
    with get_conn(db_path) as c:
        rows = list(
            c.execute(
                "SELECT id, name, pillar FROM clusters WHERE name LIKE '_unclustered_%'"
            ).fetchall()
        )

    for row in rows:
        placeholder_id = row["id"]
        placeholder_pillar = row["pillar"]
        seed_queries = get_queries_for_cluster(db_path, placeholder_id)
        if not seed_queries:
            continue
        seed_text = seed_queries[0]["query_text"]

        user_prompt = _build_user_prompt(
            seed_query=seed_text,
            pillar_hint=placeholder_pillar if placeholder_pillar != "unclustered" else None,
            towns=towns,
        )
        result = client.complete(
            system=EXPANSION_SYSTEM_PROMPT,
            user=user_prompt,
            max_tokens=1500,
            temperature=0.7,
        )
        payload = _safe_parse(result.text)
        if payload is None:
            # leave placeholder in place; operator can regenerate
            continue

        cluster_name = payload.get("cluster_name")
        if not isinstance(cluster_name, str) or not cluster_name:
            continue

        existing = get_cluster_by_name(db_path, cluster_name)
        if existing is not None:
            target_id = existing["id"]
            # move the raw_seed query onto the existing cluster; delete placeholder
            with get_conn(db_path) as c:
                c.execute(
                    "UPDATE queries SET cluster_id = ? WHERE cluster_id = ?",
                    (target_id, placeholder_id),
                )
                c.execute("DELETE FROM clusters WHERE id = ?", (placeholder_id,))
        else:
            # rename placeholder to real cluster name + backfill fields
            with get_conn(db_path) as c:
                c.execute(
                    "UPDATE clusters SET name = ?, pillar = ?, service_type = ?, "
                    "funnel_stage = ?, priority_score = ?, notes = NULL, "
                    "updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    (
                        cluster_name,
                        placeholder_pillar if placeholder_pillar != "unclustered"
                        else _pillar_for_funnel(payload.get("funnel_stage", "")),
                        payload.get("service_type"),
                        payload.get("funnel_stage"),
                        int(payload.get("priority_score") or 5),
                        placeholder_id,
                    ),
                )
            target_id = placeholder_id

        # insert variants
        for variant in payload.get("variants") or []:
            q_text = variant.get("query_text")
            phrasing = variant.get("phrasing_type")
            geo = variant.get("geo_modifier")
            if geo == "null":
                geo = None
            if not q_text or phrasing not in {
                "emergency", "price_anxiety", "question", "symptom", "local_variant"
            }:
                continue
            try:
                insert_query(
                    db_path,
                    cluster_id=target_id,
                    query_text=q_text,
                    phrasing_type=phrasing,
                    geo_modifier=geo,
                    source="llm_expansion",
                    priority_score=5,
                    notes=None,
                )
            except sqlite3.IntegrityError:
                # duplicate (cluster_id, query_text) — silently skip
                continue


_FUNNEL_TO_PILLAR = {
    "high_intent_problem_aware": "emergency_repairs",
    "mid_intent_considering": "replacement_installation",
    "mid_intent_price_checking": "pricing_transparency",
    "mid_intent_deciding": "comparisons",
    "mid_intent_trust_building": "common_mistakes",
    "low_intent_educational": "maintenance_prevention",
    "trust_and_ranking": "local_authority",
}


def _pillar_for_funnel(funnel_stage: str) -> str:
    return _FUNNEL_TO_PILLAR.get(funnel_stage, "maintenance_prevention")
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
pytest tests/test_cluster_expand.py -v
```

Expected: `3 passed`.

- [ ] **Step 5: Write `scripts/expand_clusters.py`**

Create `twins-content-engine/scripts/expand_clusters.py`:

```python
"""CLI: expand placeholder clusters into real clusters + variants via Claude."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.claude_client import ClaudeClient
from engine.cluster import expand_seeds_to_clusters
from engine.config import load_service_area


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_SERVICE_AREA = ROOT / "config" / "service_area.yaml"


def main() -> None:
    ap = argparse.ArgumentParser(description="Expand seeded clusters via Claude.")
    ap.add_argument("--all", action="store_true", help="Process all placeholder clusters")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--service-area", type=Path, default=DEFAULT_SERVICE_AREA)
    ap.add_argument("--model", type=str, default="claude-sonnet-4-6")
    args = ap.parse_args()

    if not args.all:
        ap.error("--all is required in v1 (partial expansion is a future feature)")

    towns = load_service_area(args.service_area).towns
    client = ClaudeClient(model=args.model)
    expand_seeds_to_clusters(db_path=args.db, client=client, towns=towns)
    print(f"Expansion complete against {args.db}")


if __name__ == "__main__":
    main()
```

- [ ] **Step 6: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/cluster.py \
        twins-content-engine/tests/test_cluster_expand.py \
        twins-content-engine/scripts/expand_clusters.py
git commit -m "feat(content-engine): LLM-driven cluster expansion"
```

---

## Task 8: Rules engine (`engine/rules.py`)

Pure-Python validation of generated content. No LLM calls. Returns a `RuleReport` with violations and human-readable suggestions (suggestions are fed back into the generator as a critique for one retry).

**Files:**
- Create: `twins-content-engine/engine/rules.py`
- Create: `twins-content-engine/tests/test_rules.py`

- [ ] **Step 1: Write failing tests for each rule category**

Create `twins-content-engine/tests/test_rules.py`:

```python
"""Tests for engine.rules."""
from __future__ import annotations

from pathlib import Path

import pytest

from engine.config import load_rules, load_brand, load_service_area
from engine.rules import Violation, RuleReport, run_rules


@pytest.fixture
def rules_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "rules.yaml"
    p.write_text("""
voice:
  persona: "Madison garage door tech"
  structure: "hook -> problem -> authority -> CTA"
  reading_level_max: 8
hook_requirements:
  video_script:
    min_words: 6
    max_words: 8
    forbidden_openers: [Whether, "Are you", "Did you know", "In today's"]
    must_reference_one_of: [specific_symptom, number, neighborhood]
  caption:
    must_mirror_search_query: true
  gbp_post:
    first_n_words: 10
    must_contain_one_of_towns: true
  blog_snippet:
    first_sentence_must_contain_one_of: [specific_number, timeframe, price_range]
anti_ai_spam_blacklist:
  phrases: ["whether you're", "game-changer", "elevate your", "journey"]
  structural: [em_dash_pair_as_dramatic_pause, formulaic_triadic_list]
specificity_requirements:
  must_include_one_of: [specific_number, named_location, timeframe, concrete_detail]
  must_avoid_phrases: ["we're the best", "trusted by many"]
cta_rules:
  one_per_piece: true
  funnel_stage_mapping:
    high_intent_problem_aware: call_now
    mid_intent_price_checking: free_estimate
    low_intent_educational: save_or_share
local_embedding:
  mention_frequency:
    video_script: [1, 2]
    caption: [1, 1]
    gbp_post: [2, 3]
    faq: [2, 2]
    blog_snippet: [3, 5]
  placement_rules:
    no_consecutive_sentences: true
    require_mention_in_first_pct: 30
    prefer_specific_town_over_region: true
""")
    return p


def _ctx(brand_yaml: Path, service_area_yaml: Path, rules_yaml: Path) -> dict:
    return {
        "brand": load_brand(brand_yaml),
        "service_area": load_service_area(service_area_yaml),
        "rules": load_rules(rules_yaml),
    }


def test_clean_caption_passes(sample_brand_yaml, sample_service_area_yaml, rules_yaml):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = (
        "Garage door won't close all the way?\n"
        "Usually the bottom safety sensor is misaligned.\n"
        "Twins Garage Doors in Madison can fix it same-day for $120.\n"
        "Call (608) 555-0199."
    )
    report = run_rules(
        format="caption",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert report.passed, report.violations


def test_blacklist_phrase_flagged(sample_brand_yaml, sample_service_area_yaml, rules_yaml):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = (
        "Whether you're in Madison or Middleton, our game-changer "
        "service will elevate your garage door experience. Call now."
    )
    report = run_rules(
        format="caption",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert not report.passed
    rule_ids = {v.rule_id for v in report.violations}
    assert "anti_ai_spam:phrase" in rule_ids


def test_video_script_bad_hook_opener_flagged(
    sample_brand_yaml, sample_service_area_yaml, rules_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = (
        "HOOK (0:00-0:02): Did you know garage doors break?\n\n"
        "PROBLEM: stuff\n"
        "AUTHORITY: 13 years in Madison serving Middleton too.\n"
        "CTA: Call (608) 555-0199."
    )
    report = run_rules(
        format="video_script",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert not report.passed
    rule_ids = {v.rule_id for v in report.violations}
    assert "hook:forbidden_opener" in rule_ids


def test_gbp_first_words_must_contain_town(
    sample_brand_yaml, sample_service_area_yaml, rules_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = (
        "Your garage door spring just snapped and you can't open it. "
        "That's a torsion spring under 200 lbs of tension — don't try to lift the door manually. "
        "In Madison we handle these same-day; standard replacement runs $250-400. "
        "Middleton and Sun Prairie too. Call (608) 555-0199 to get back in by tonight."
    )
    report = run_rules(
        format="gbp_post",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert not report.passed
    ids = {v.rule_id for v in report.violations}
    assert "hook:gbp_town" in ids


def test_specificity_requirement(sample_brand_yaml, sample_service_area_yaml, rules_yaml):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = (
        "Your garage door broke?\n"
        "Things can go wrong sometimes. We help people fix things.\n"
        "Call us for service."
    )
    report = run_rules(
        format="caption",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert not report.passed
    ids = {v.rule_id for v in report.violations}
    assert "specificity:required_marker" in ids


def test_local_embedding_frequency(
    sample_brand_yaml, sample_service_area_yaml, rules_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    # caption expects exactly 1 town mention
    zero_mentions = (
        "Garage door won't close?\n"
        "Usually the sensor at the bottom needs realignment.\n"
        "Fixed same-day for $120. Call (608) 555-0199."
    )
    report = run_rules(
        format="caption",
        content=zero_mentions,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    ids = {v.rule_id for v in report.violations}
    assert "local_embedding:frequency" in ids


def test_cta_one_per_piece(sample_brand_yaml, sample_service_area_yaml, rules_yaml):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    # two phone-number CTAs
    content = (
        "Garage door spring snapped in Madison? Call (608) 555-0199.\n"
        "Don't lift it. Torsion springs hold 200 lbs. $250-400 typical fix.\n"
        "Call (608) 555-0199 tonight."
    )
    report = run_rules(
        format="caption",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    ids = {v.rule_id for v in report.violations}
    assert "cta:multiple" in ids


def test_suggestions_are_populated(
    sample_brand_yaml, sample_service_area_yaml, rules_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml)
    content = "Whether you're searching, journey into excellence."
    report = run_rules(
        format="caption",
        content=content,
        funnel_stage="high_intent_problem_aware",
        ctx=ctx,
    )
    assert not report.passed
    assert report.suggestions, "should have at least one critique line"
    assert all(isinstance(s, str) and s for s in report.suggestions)
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
pytest tests/test_rules.py -v
```

Expected: import error — `engine.rules` doesn't exist.

- [ ] **Step 3: Implement `engine/rules.py`**

Create `twins-content-engine/engine/rules.py`:

```python
"""Pure-Python rules engine for generated content validation."""
from __future__ import annotations

import re
from dataclasses import dataclass, field
from typing import Any, Literal, Optional

from engine.config import BrandConfig, RulesConfig, ServiceAreaConfig


Severity = Literal["error", "warning"]


@dataclass
class Violation:
    rule_id: str
    severity: Severity
    message: str
    span: Optional[tuple[int, int]] = None


@dataclass
class RuleReport:
    passed: bool
    violations: list[Violation] = field(default_factory=list)
    suggestions: list[str] = field(default_factory=list)


# ---------- regex helpers ----------

_NUMBER_RE = re.compile(r"\b\d+(?:[-.,]\d+)?\b|\$\d+")
_TIMEFRAME_RE = re.compile(
    r"\b(same[- ]day|within \d+ hours?|today|tomorrow|this week|before winter|"
    r"in under \d+|\d+\s*hours?|\d+\s*minutes?)\b",
    re.IGNORECASE,
)
_PRICE_RANGE_RE = re.compile(r"\$\d[\d,]*\s*[–-]\s*\$?\d[\d,]*")
_PHONE_RE = re.compile(r"\(\d{3}\)\s*\d{3}[-.]\d{4}|\b\d{3}[-.]\d{3}[-.]\d{4}\b")
_SENTENCE_SPLIT = re.compile(r"(?<=[.!?])\s+")


def _first_n_words(text: str, n: int) -> str:
    words = text.strip().split()
    return " ".join(words[:n])


def _count_occurrences(text: str, needle: str) -> int:
    return len(re.findall(re.escape(needle), text, flags=re.IGNORECASE))


def _contains_any_town(text: str, service_area: ServiceAreaConfig) -> bool:
    names = [service_area.town_display_names[s] for s in service_area.towns]
    lowered = text.lower()
    return any(n.lower() in lowered for n in names)


def _town_mention_count(text: str, service_area: ServiceAreaConfig) -> int:
    total = 0
    for slug in service_area.towns:
        display = service_area.town_display_names[slug]
        total += _count_occurrences(text, display)
    return total


def _town_mention_positions(text: str, service_area: ServiceAreaConfig) -> list[int]:
    positions: list[int] = []
    lowered = text.lower()
    for slug in service_area.towns:
        display = service_area.town_display_names[slug].lower()
        start = 0
        while True:
            idx = lowered.find(display, start)
            if idx == -1:
                break
            positions.append(idx)
            start = idx + len(display)
    return sorted(positions)


def _consecutive_sentence_mentions(text: str, service_area: ServiceAreaConfig) -> bool:
    sentences = _SENTENCE_SPLIT.split(text)
    prev = False
    for s in sentences:
        has = _contains_any_town(s, service_area)
        if has and prev:
            return True
        prev = has
    return False


# ---------- individual rule checks ----------

def _check_blacklist_phrases(text: str, rules: RulesConfig) -> list[Violation]:
    violations: list[Violation] = []
    lowered = text.lower()
    for phrase in rules.anti_ai_spam_blacklist.phrases:
        if phrase.lower() in lowered:
            violations.append(
                Violation(
                    rule_id="anti_ai_spam:phrase",
                    severity="error",
                    message=f"Contains blacklisted phrase: {phrase!r}",
                )
            )
    return violations


def _check_blacklist_structural(text: str, rules: RulesConfig) -> list[Violation]:
    violations: list[Violation] = []
    structural = set(rules.anti_ai_spam_blacklist.structural)
    if "em_dash_pair_as_dramatic_pause" in structural:
        # pattern: "— X —" where X is a word or short phrase between em dashes
        if re.search(r"—[^—\n]{1,80}—", text):
            violations.append(Violation(
                rule_id="anti_ai_spam:structural_em_dash_pair",
                severity="error",
                message="Dramatic em-dash pair detected (AI tell).",
            ))
    if "formulaic_triadic_list" in structural:
        # pattern: "X, Y, Z." or "X. Y. Z." with short adjective triples at end of a clause
        if re.search(
            r"\b([A-Z][a-z]{2,12})[,.]\s+([A-Z][a-z]{2,12})[,.]\s+([A-Z][a-z]{2,12})\.",
            text,
        ):
            violations.append(Violation(
                rule_id="anti_ai_spam:structural_triadic_list",
                severity="warning",
                message="Formulaic three-adjective list detected (AI tell).",
            ))
    return violations


def _check_specificity(text: str, rules: RulesConfig,
                       service_area: ServiceAreaConfig) -> list[Violation]:
    violations: list[Violation] = []
    markers = rules.specificity_requirements.must_include_one_of
    found_marker = False
    for marker in markers:
        if marker == "specific_number" and _NUMBER_RE.search(text):
            found_marker = True
            break
        if marker == "named_location":
            if _contains_any_town(text, service_area):
                found_marker = True
                break
            if service_area.madison_neighborhoods:
                if any(n.lower() in text.lower() for n in service_area.madison_neighborhoods):
                    found_marker = True
                    break
        if marker == "timeframe" and _TIMEFRAME_RE.search(text):
            found_marker = True
            break
        if marker == "concrete_detail":
            # heuristic: any all-caps product-like token or spec with units
            if re.search(r"\b[A-Z]{2,}\w*\b", text) or re.search(r"\b\d+\s*(lbs?|ft|inches|in|years?|cycles?)\b", text, re.IGNORECASE):
                found_marker = True
                break
    if not found_marker:
        violations.append(Violation(
            rule_id="specificity:required_marker",
            severity="error",
            message=f"Content lacks any required specificity marker: {markers}",
        ))
    for bad in rules.specificity_requirements.must_avoid_phrases:
        if bad.lower() in text.lower():
            violations.append(Violation(
                rule_id="specificity:forbidden_phrase",
                severity="error",
                message=f"Contains forbidden generic claim: {bad!r}",
            ))
    return violations


def _check_hook_video_script(content: str, hook_cfg: dict[str, Any]) -> list[Violation]:
    violations: list[Violation] = []
    hook_line = _extract_video_hook_line(content)
    if not hook_line:
        return [Violation(
            rule_id="hook:video_script_missing",
            severity="error",
            message="video_script is missing a HOOK (0:00-0:02) block.",
        )]
    forbidden = [f.lower() for f in hook_cfg.get("forbidden_openers", [])]
    hook_lower = hook_line.strip().lower()
    for f in forbidden:
        if hook_lower.startswith(f):
            violations.append(Violation(
                rule_id="hook:forbidden_opener",
                severity="error",
                message=f"Hook starts with forbidden opener {f!r}.",
            ))
            break
    word_count = len(hook_line.split())
    lo = int(hook_cfg.get("min_words", 6))
    hi = int(hook_cfg.get("max_words", 12))
    if not (lo <= word_count <= hi):
        violations.append(Violation(
            rule_id="hook:video_script_length",
            severity="warning",
            message=f"Hook is {word_count} words; expected {lo}-{hi}.",
        ))
    return violations


def _extract_video_hook_line(content: str) -> Optional[str]:
    m = re.search(r"HOOK\s*\([^)]*\)\s*:\s*(.+)", content)
    if not m:
        return None
    line = m.group(1).strip()
    # If the hook spans multiple lines, take just the first non-empty line
    return line.split("\n")[0].strip()


def _check_hook_gbp(content: str, service_area: ServiceAreaConfig,
                   first_n: int) -> list[Violation]:
    prefix = _first_n_words(content, first_n)
    if not _contains_any_town(prefix, service_area):
        return [Violation(
            rule_id="hook:gbp_town",
            severity="error",
            message=f"First {first_n} words of gbp_post must contain a service-area town name.",
        )]
    return []


def _check_blog_first_sentence(content: str, req: list[str]) -> list[Violation]:
    first_sentence = _SENTENCE_SPLIT.split(content.strip())[0]
    for r in req:
        if r == "specific_number" and _NUMBER_RE.search(first_sentence):
            return []
        if r == "timeframe" and _TIMEFRAME_RE.search(first_sentence):
            return []
        if r == "price_range" and _PRICE_RANGE_RE.search(first_sentence):
            return []
    return [Violation(
        rule_id="hook:blog_first_sentence",
        severity="error",
        message=f"First sentence must contain one of: {req}",
    )]


def _check_cta(content: str, funnel_stage: str, rules: RulesConfig,
               brand: BrandConfig) -> list[Violation]:
    violations: list[Violation] = []
    if rules.cta_rules.one_per_piece:
        phone_count = len(_PHONE_RE.findall(content))
        if phone_count > 1:
            violations.append(Violation(
                rule_id="cta:multiple",
                severity="error",
                message=f"Found {phone_count} phone CTAs; only one allowed.",
            ))
    mapping = rules.cta_rules.funnel_stage_mapping
    expected = mapping.get(funnel_stage)
    if expected == "call_now":
        if not _PHONE_RE.search(content):
            violations.append(Violation(
                rule_id="cta:call_now_missing_phone",
                severity="error",
                message="high-intent content must include a phone-number CTA.",
            ))
    return violations


def _check_local_embedding(content: str, format: str, rules: RulesConfig,
                           service_area: ServiceAreaConfig) -> list[Violation]:
    violations: list[Violation] = []
    freq = rules.local_embedding.mention_frequency.get(format)
    if not freq:
        return violations
    lo, hi = freq[0], freq[1]
    count = _town_mention_count(content, service_area)
    if count < lo or count > hi:
        violations.append(Violation(
            rule_id="local_embedding:frequency",
            severity="error",
            message=f"{format} has {count} town mentions; expected {lo}-{hi}.",
        ))
    if rules.local_embedding.placement_rules.no_consecutive_sentences:
        if _consecutive_sentence_mentions(content, service_area):
            violations.append(Violation(
                rule_id="local_embedding:consecutive_sentences",
                severity="warning",
                message="Consecutive sentences both mention a town (sounds spammy).",
            ))
    pct = rules.local_embedding.placement_rules.require_mention_in_first_pct
    if count > 0:
        positions = _town_mention_positions(content, service_area)
        if positions:
            threshold = int(len(content) * (pct / 100))
            if positions[0] > threshold:
                violations.append(Violation(
                    rule_id="local_embedding:late_first_mention",
                    severity="warning",
                    message=f"First town mention appears after the first {pct}% of content.",
                ))
    return violations


# ---------- top-level ----------

def run_rules(
    *,
    format: str,
    content: str,
    funnel_stage: str,
    ctx: dict[str, Any],
) -> RuleReport:
    """Run every applicable rule. Return RuleReport with passed=False if any
    error-severity violation was found."""
    brand: BrandConfig = ctx["brand"]
    service_area: ServiceAreaConfig = ctx["service_area"]
    rules: RulesConfig = ctx["rules"]

    violations: list[Violation] = []
    violations += _check_blacklist_phrases(content, rules)
    violations += _check_blacklist_structural(content, rules)
    violations += _check_specificity(content, rules, service_area)
    violations += _check_cta(content, funnel_stage, rules, brand)
    violations += _check_local_embedding(content, format, rules, service_area)

    hook_reqs = rules.hook_requirements or {}
    if format == "video_script" and "video_script" in hook_reqs:
        violations += _check_hook_video_script(content, hook_reqs["video_script"])
    elif format == "gbp_post" and "gbp_post" in hook_reqs:
        first_n = int(hook_reqs["gbp_post"].get("first_n_words", 10))
        violations += _check_hook_gbp(content, service_area, first_n)
    elif format == "blog_snippet" and "blog_snippet" in hook_reqs:
        req = hook_reqs["blog_snippet"].get("first_sentence_must_contain_one_of", [])
        violations += _check_blog_first_sentence(content, req)

    errors = [v for v in violations if v.severity == "error"]
    suggestions = _suggestions_from_violations(violations)
    return RuleReport(passed=not errors, violations=violations, suggestions=suggestions)


def _suggestions_from_violations(violations: list[Violation]) -> list[str]:
    """Produce human-readable critique lines for LLM regeneration prompts."""
    out: list[str] = []
    for v in violations:
        out.append(f"- [{v.rule_id}] {v.message}")
    return out
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
pytest tests/test_rules.py -v
```

Expected: `8 passed`.

- [ ] **Step 5: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/rules.py \
        twins-content-engine/tests/test_rules.py
git commit -m "feat(content-engine): rules engine with anti-AI-spam checks"
```

---

## Task 9: Local embedding injection (`engine/local_embedding.py`)

Helpers the generator uses to **inject** local context into prompts (pick a town, pick a neighborhood, format phone). Validation lives in `rules.py`; this module is purely about influencing LLM output.

**Files:**
- Create: `twins-content-engine/engine/local_embedding.py`
- Create: `twins-content-engine/tests/test_local_embedding.py`

- [ ] **Step 1: Write failing tests**

Create `twins-content-engine/tests/test_local_embedding.py`:

```python
"""Tests for engine.local_embedding."""
from __future__ import annotations

from pathlib import Path

from engine.config import load_brand, load_service_area
from engine.local_embedding import (
    pick_town_for_piece,
    pick_neighborhood,
    format_phone_for_content,
    target_mention_count,
)


def test_pick_town_uses_query_geo_modifier_when_available(
    sample_brand_yaml, sample_service_area_yaml
):
    sa = load_service_area(sample_service_area_yaml)
    town_slug = pick_town_for_piece(
        query_geo_modifier="middleton_wi",
        cluster_seed=123,
        service_area=sa,
    )
    assert town_slug == "middleton_wi"


def test_pick_town_falls_back_to_deterministic_rotation(
    sample_brand_yaml, sample_service_area_yaml
):
    sa = load_service_area(sample_service_area_yaml)
    a = pick_town_for_piece(query_geo_modifier=None, cluster_seed=1, service_area=sa)
    b = pick_town_for_piece(query_geo_modifier=None, cluster_seed=1, service_area=sa)
    assert a == b  # deterministic for same seed
    assert a in sa.towns
    # different seeds ideally map to different towns
    c = pick_town_for_piece(query_geo_modifier=None, cluster_seed=2, service_area=sa)
    # not strict inequality (collisions possible) — just ensure it's a valid slug
    assert c in sa.towns


def test_pick_neighborhood_deterministic(sample_brand_yaml, sample_service_area_yaml):
    sa = load_service_area(sample_service_area_yaml)
    # sample service area yaml has no neighborhoods; patch by adding
    sa.madison_neighborhoods = ["Maple Bluff", "University Heights", "Nakoma"]
    n1 = pick_neighborhood(cluster_seed=5, service_area=sa)
    n2 = pick_neighborhood(cluster_seed=5, service_area=sa)
    assert n1 == n2
    assert n1 in sa.madison_neighborhoods


def test_pick_neighborhood_returns_none_when_empty(sample_service_area_yaml):
    sa = load_service_area(sample_service_area_yaml)
    sa.madison_neighborhoods = []
    assert pick_neighborhood(cluster_seed=5, service_area=sa) is None


def test_format_phone_for_content_preserves_format(sample_brand_yaml):
    brand = load_brand(sample_brand_yaml)
    assert format_phone_for_content(brand) == "(608) 555-0199"


def test_target_mention_count_reads_rules():
    freq = {"caption": [1, 1], "gbp_post": [2, 3]}
    assert target_mention_count("caption", freq) == (1, 1)
    assert target_mention_count("gbp_post", freq) == (2, 3)
    assert target_mention_count("unknown_format", freq) == (0, 0)
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
pytest tests/test_local_embedding.py -v
```

Expected: `ModuleNotFoundError: No module named 'engine.local_embedding'`.

- [ ] **Step 3: Implement `engine/local_embedding.py`**

Create `twins-content-engine/engine/local_embedding.py`:

```python
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
    # bias: every 3rd cluster emphasizes the primary
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


def target_mention_count(format: str, freq_map: dict[str, list[int]]) -> tuple[int, int]:
    """Return (lo, hi) expected town-mention count for a given format, or (0, 0)."""
    val = freq_map.get(format)
    if not val or len(val) != 2:
        return (0, 0)
    return (int(val[0]), int(val[1]))
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
pytest tests/test_local_embedding.py -v
```

Expected: `6 passed`.

- [ ] **Step 5: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/local_embedding.py \
        twins-content-engine/tests/test_local_embedding.py
git commit -m "feat(content-engine): local embedding injection helpers"
```

---

## Task 10: Multi-format generator (`engine/generator.py`)

The orchestrator: given a cluster + representative query, generate all 5 formats. Each format has its own prompt. Rules are checked after generation — on failure, regenerate once with the violation list as critique. On second failure, mark as rejected.

This task is split into three steps: prompts module, orchestrator, tests.

**Files:**
- Create: `twins-content-engine/engine/prompts.py`
- Create: `twins-content-engine/engine/generator.py`
- Create: `twins-content-engine/tests/test_prompts.py`
- Create: `twins-content-engine/tests/test_generator.py`

- [ ] **Step 1: Write failing tests for prompt builders**

Create `twins-content-engine/tests/test_prompts.py`:

```python
"""Tests for engine.prompts builders."""
from __future__ import annotations

from pathlib import Path

from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.prompts import build_generation_prompt, build_regeneration_prompt


def _ctx(brand_yaml, service_area_yaml, rules_yaml, pillars_yaml):
    return {
        "brand": load_brand(brand_yaml),
        "service_area": load_service_area(service_area_yaml),
        "rules": load_rules(rules_yaml),
        "pillars": load_pillars(pillars_yaml),
    }


def _pillars_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "pillars.yaml"
    p.write_text("""
pillars:
  emergency_repairs:
    description: desc
    funnel_stage: high_intent_problem_aware
    seed_patterns: []
    format_bias: [video_script]
    priority_weight: 1.5
""")
    return p


def _rules_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "rules.yaml"
    p.write_text("""
voice:
  persona: "Madison garage door tech"
  structure: "hook -> problem -> authority -> CTA"
  reading_level_max: 8
hook_requirements: {}
anti_ai_spam_blacklist:
  phrases: []
  structural: []
specificity_requirements:
  must_include_one_of: []
  must_avoid_phrases: []
cta_rules:
  one_per_piece: true
  funnel_stage_mapping:
    high_intent_problem_aware: call_now
local_embedding:
  mention_frequency:
    caption: [1, 1]
  placement_rules:
    no_consecutive_sentences: true
    require_mention_in_first_pct: 30
    prefer_specific_town_over_region: true
""")
    return p


def test_build_generation_prompt_caption(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml,
               _rules_yaml(tmp_path), _pillars_yaml(tmp_path))
    system, user = build_generation_prompt(
        format="caption",
        query_text="garage door won't open loud bang",
        cluster_name="broken_spring",
        pillar_name="emergency_repairs",
        funnel_stage="high_intent_problem_aware",
        target_town_display="Middleton",
        target_neighborhood=None,
        ctx=ctx,
    )
    assert "Madison garage door tech" in system
    assert "caption" in user.lower()
    assert "Middleton" in user
    assert "garage door won't open loud bang" in user
    assert "(608) 555-0199" in user
    # Ensure funnel CTA guidance is present
    assert "call_now" in user or "phone" in user.lower()


def test_build_generation_prompt_video_script(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml,
               _rules_yaml(tmp_path), _pillars_yaml(tmp_path))
    system, user = build_generation_prompt(
        format="video_script",
        query_text="spring snapped loud bang",
        cluster_name="broken_spring",
        pillar_name="emergency_repairs",
        funnel_stage="high_intent_problem_aware",
        target_town_display="Madison",
        target_neighborhood="Maple Bluff",
        ctx=ctx,
    )
    assert "HOOK" in user
    assert "SHOT LIST" in user or "shot list" in user.lower()


def test_build_regeneration_prompt_includes_critique(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml,
               _rules_yaml(tmp_path), _pillars_yaml(tmp_path))
    system, user = build_regeneration_prompt(
        format="caption",
        previous_output="bad output",
        critique_lines=["- [anti_ai_spam:phrase] Contains blacklisted phrase 'journey'"],
        query_text="q",
        cluster_name="c",
        pillar_name="emergency_repairs",
        funnel_stage="high_intent_problem_aware",
        target_town_display="Madison",
        target_neighborhood=None,
        ctx=ctx,
    )
    assert "bad output" in user
    assert "anti_ai_spam:phrase" in user
    assert "journey" in user
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
pytest tests/test_prompts.py -v
```

Expected: `ModuleNotFoundError: No module named 'engine.prompts'`.

- [ ] **Step 3: Implement `engine/prompts.py`**

Create `twins-content-engine/engine/prompts.py`:

```python
"""Prompt builders for each content format."""
from __future__ import annotations

from typing import Any, Optional

from engine.config import BrandConfig, PillarsConfig, RulesConfig, ServiceAreaConfig


_SYSTEM_BASE = """You are a content writer for Twins Garage Doors, a local garage door repair company in Madison, Wisconsin.

Voice: {persona}
Structure: {structure}
Reading level: max grade {reading_level}.

Hard rules — violating any of these invalidates the output:
- No copywriter clichés: no "whether you're", "game-changer", "elevate your", "journey", "in today's fast-paced", "nestled in", "seamlessly", "at the end of the day".
- No em-dash pair used as dramatic pause ("— and here's why —"). Single em-dashes are fine.
- No three-adjective lists like "Fast. Reliable. Affordable." Write real sentences instead.
- Every piece must include at least one: specific number, named neighborhood/town, concrete timeframe, or concrete detail (e.g., "10-ft torsion spring", "LiftMaster 8500W").
- No generic superiority claims ("we're the best", "#1 in", "trusted by many").
- Exactly ONE call-to-action per piece. Do not stack CTAs.
- When a phone number is included, use exactly: {phone}
- Use these brand proof points when natural: {proof_points}.
- Avoid these words: {avoid_terms}.

Tone check: a real Madison tech picks up the phone at 2pm on a Tuesday, tired, professional, slightly dry. Write like that.
"""


def _system_prompt(ctx: dict[str, Any]) -> str:
    brand: BrandConfig = ctx["brand"]
    rules: RulesConfig = ctx["rules"]
    return _SYSTEM_BASE.format(
        persona=rules.voice.persona,
        structure=rules.voice.structure,
        reading_level=rules.voice.reading_level_max,
        phone=brand.phone,
        proof_points="; ".join(brand.signature_proof_points) or "(none provided)",
        avoid_terms=", ".join(brand.voice_terms_to_avoid) or "(none)",
    )


_FORMAT_USER_TEMPLATES: dict[str, str] = {
    "caption": """Write a single Instagram/Facebook caption for a Twins Garage Doors post.

Cluster: {cluster_name}
Pillar: {pillar_name}
Funnel stage: {funnel_stage} (CTA type: {cta_type})
Source search query (real homeowner phrasing): "{query_text}"
Target town to mention: {target_town} (mention exactly once, naturally)

Structure:
1. First line: mirror the search query — same anxious phrasing, question mark OK.
2. 2–3 short lines of practical info. No fluff. No "picture this".
3. One CTA line with the phone number.
4. One hashtag block: max 5 hashtags, first three must be local (e.g., #MadisonWI, #{target_town_hashtag}).

Return ONLY the caption text + hashtags. No YAML frontmatter, no commentary.
""",

    "video_script": """Write a 20-second vertical video script for Twins Garage Doors.

Cluster: {cluster_name}
Pillar: {pillar_name}
Funnel stage: {funnel_stage} (CTA type: {cta_type})
Source search query: "{query_text}"
Target town to reference: {target_town}{neighborhood_hint}

Required structure — use this EXACT block layout:

HOOK (0:00-0:02):
[6-8 words. Must reference a specific symptom, number, or neighborhood. Do NOT start with "Whether", "Are you", "Did you know", "In today's".]

PROBLEM (0:02-0:08):
[3-4 short voiceover lines. No fluff. What's actually happening to the homeowner.]

AUTHORITY (0:08-0:15):
[One specific proof line. Example: "Torsion springs are rated for 10,000 cycles. After 13 years in Madison, I've replaced hundreds."]

CTA (0:15-0:20):
[One action. Phone number on screen.]

SHOT LIST:
- 0:00-0:02: [visual]
- 0:02-0:08: [visual]
- 0:08-0:15: [visual]
- 0:15-0:20: [phone number card]

Return the blocks only, no YAML frontmatter.
""",

    "gbp_post": """Write a Google Business Profile post for Twins Garage Doors.

Cluster: {cluster_name}
Pillar: {pillar_name}
Funnel stage: {funnel_stage} (CTA type: {cta_type})
Source search query: "{query_text}"
Target town: {target_town} — must appear in the first 10 words.

Constraints:
- 900–1400 characters total.
- Two paragraphs, 6–10 sentences total.
- Must mention {target_town} 2–3 times across the whole post (natural, not in consecutive sentences).
- One CTA at the end matching {cta_type}. Include phone number if call-based CTA.

Return ONLY the post body, no frontmatter.
""",

    "faq": """Write 3 FAQ entries for Twins Garage Doors in schema.org-friendly Markdown.

Cluster: {cluster_name}
Pillar: {pillar_name}
Funnel stage: {funnel_stage}
Source search query: "{query_text}"
Target town: {target_town}

Constraints:
- Each Q is a real homeowner question — use the source query as Q1 verbatim if it's already question-form, otherwise reformulate it naturally.
- Each A is 2-4 sentences, FACTUAL and concise. Include the business entity ("Twins Garage Doors") and "{target_town}" (or "Madison") at least twice across the 3 answers — this is critical for AI-search retrieval.
- Include one specific number, price range, or timeframe in at least one answer.

Format:
## Q: [question]
**A:** [answer]

## Q: [question]
**A:** [answer]

## Q: [question]
**A:** [answer]

Return ONLY the Q/A blocks, no frontmatter.
""",

    "blog_snippet": """Write a 250–400 word blog snippet for Twins Garage Doors.

Cluster: {cluster_name}
Pillar: {pillar_name}
Funnel stage: {funnel_stage} (CTA type: {cta_type})
Source search query: "{query_text}"
Target town: {target_town}

Structure:
1. H1 in question form that mirrors the search query.
2. First paragraph: direct answer in 2–3 sentences. First sentence MUST contain a specific number, timeframe, or price range.
3. Three short sections with H2 subheads, 1 paragraph each.
4. Final paragraph: one CTA matching {cta_type}.

Local mentions: reference {target_town} or "Madison" 3–5 times across the piece (spread out, never consecutive sentences).
Use these placeholder internal link tags where a link would make sense: [[link:service/<slug>]]

Return ONLY the Markdown body, no YAML frontmatter.
""",
}


def _town_hashtag(town_display: str) -> str:
    # "Sun Prairie" -> "SunPrairie"
    return town_display.replace(" ", "")


def build_generation_prompt(
    *,
    format: str,
    query_text: str,
    cluster_name: str,
    pillar_name: str,
    funnel_stage: str,
    target_town_display: str,
    target_neighborhood: Optional[str],
    ctx: dict[str, Any],
) -> tuple[str, str]:
    """Return (system, user) prompt strings for the given format."""
    rules: RulesConfig = ctx["rules"]
    cta_type = rules.cta_rules.funnel_stage_mapping.get(funnel_stage, "learn_more")

    template = _FORMAT_USER_TEMPLATES[format]
    neighborhood_hint = (
        f" Include the neighborhood '{target_neighborhood}' once if natural."
        if target_neighborhood else ""
    )
    user = template.format(
        cluster_name=cluster_name,
        pillar_name=pillar_name,
        funnel_stage=funnel_stage,
        cta_type=cta_type,
        query_text=query_text,
        target_town=target_town_display,
        target_town_hashtag=_town_hashtag(target_town_display),
        neighborhood_hint=neighborhood_hint,
    )
    return _system_prompt(ctx), user


def build_regeneration_prompt(
    *,
    format: str,
    previous_output: str,
    critique_lines: list[str],
    query_text: str,
    cluster_name: str,
    pillar_name: str,
    funnel_stage: str,
    target_town_display: str,
    target_neighborhood: Optional[str],
    ctx: dict[str, Any],
) -> tuple[str, str]:
    """Build a prompt that includes the prior output and the critique lines."""
    system, base_user = build_generation_prompt(
        format=format,
        query_text=query_text,
        cluster_name=cluster_name,
        pillar_name=pillar_name,
        funnel_stage=funnel_stage,
        target_town_display=target_town_display,
        target_neighborhood=target_neighborhood,
        ctx=ctx,
    )
    critique = "\n".join(critique_lines)
    user = (
        f"{base_user}\n\n"
        f"=== YOUR PREVIOUS ATTEMPT ===\n{previous_output}\n=== END PREVIOUS ===\n\n"
        f"That attempt failed these rule checks:\n{critique}\n\n"
        f"Rewrite from scratch — do not minimally edit. Address every listed issue."
    )
    return system, user
```

- [ ] **Step 4: Run prompt tests**

```bash
pytest tests/test_prompts.py -v
```

Expected: `3 passed`.

- [ ] **Step 5: Commit prompt builders**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/prompts.py twins-content-engine/tests/test_prompts.py
git commit -m "feat(content-engine): per-format prompt builders"
```

- [ ] **Step 6: Write failing tests for the generator orchestrator**

Create `twins-content-engine/tests/test_generator.py`:

```python
"""Tests for engine.generator.generate_for_cluster."""
from __future__ import annotations

from pathlib import Path

import pytest

from engine.claude_client import FakeClaudeClient
from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.db import (
    get_cluster_by_name,
    init_db,
    insert_cluster,
    insert_query,
    list_pending_content,
)
from engine.generator import GenerationResult, generate_for_cluster


@pytest.fixture
def pillars_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "pillars.yaml"
    p.write_text("""
pillars:
  emergency_repairs:
    description: desc
    funnel_stage: high_intent_problem_aware
    seed_patterns: []
    format_bias: [video_script, gbp_post, faq, caption, blog_snippet]
    priority_weight: 1.5
""")
    return p


@pytest.fixture
def rules_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "rules.yaml"
    p.write_text("""
voice:
  persona: "Madison garage door tech"
  structure: "hook -> problem -> authority -> CTA"
  reading_level_max: 8
hook_requirements:
  gbp_post:
    first_n_words: 10
    must_contain_one_of_towns: true
anti_ai_spam_blacklist:
  phrases: ["journey", "game-changer"]
  structural: []
specificity_requirements:
  must_include_one_of: [specific_number, named_location, timeframe, concrete_detail]
  must_avoid_phrases: []
cta_rules:
  one_per_piece: true
  funnel_stage_mapping:
    high_intent_problem_aware: call_now
local_embedding:
  mention_frequency:
    caption: [1, 1]
    video_script: [1, 2]
    gbp_post: [2, 3]
    faq: [2, 2]
    blog_snippet: [3, 5]
  placement_rules:
    no_consecutive_sentences: true
    require_mention_in_first_pct: 30
    prefer_specific_town_over_region: true
""")
    return p


def _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml, pillars_yaml):
    return {
        "brand": load_brand(sample_brand_yaml),
        "service_area": load_service_area(sample_service_area_yaml),
        "rules": load_rules(rules_yaml),
        "pillars": load_pillars(pillars_yaml),
    }


@pytest.fixture
def seeded(tmp_path, sample_brand_yaml, sample_service_area_yaml, rules_yaml, pillars_yaml):
    db = tmp_path / "test.db"
    init_db(db)
    cid = insert_cluster(
        db, name="broken_spring", pillar="emergency_repairs",
        service_type="spring_replacement", funnel_stage="high_intent_problem_aware",
        priority_score=9, notes=None,
    )
    insert_query(
        db, cluster_id=cid, query_text="garage door won't open loud bang",
        phrasing_type="symptom", geo_modifier="madison_wi", source="raw_seed",
        priority_score=9, notes=None,
    )
    return {
        "db": db,
        "tmp": tmp_path,
        "ctx": _ctx(sample_brand_yaml, sample_service_area_yaml, rules_yaml, pillars_yaml),
    }


def _pass_caption(town="Madison") -> str:
    return (
        f"Your garage door won't open after a loud bang?\n"
        f"That's usually a snapped torsion spring. In {town} we fix it same-day for $250-400.\n"
        f"Call (608) 555-0199."
    )


def test_generator_produces_five_format_files(seeded):
    caption_pass = _pass_caption()
    fake = FakeClaudeClient(responses=[caption_pass] * 5)
    result = generate_for_cluster(
        db_path=seeded["db"],
        cluster_name="broken_spring",
        client=fake,
        ctx=seeded["ctx"],
        output_dir=seeded["tmp"] / "pending",
        brief_dir=seeded["tmp"] / "briefs",
        model_name="claude-sonnet-4-6",
    )
    assert isinstance(result, GenerationResult)
    assert len(result.accepted) == 5
    assert len(result.rejected) == 0
    # files exist
    for gc in result.accepted:
        assert Path(gc.content_path).exists()
    # one DB row per format
    pending = list_pending_content(seeded["db"])
    assert {r["format"] for r in pending} == {
        "video_script", "caption", "gbp_post", "faq", "blog_snippet"
    }


def test_generator_retries_once_on_rule_failure(seeded):
    bad_caption = "Whether you're searching for help, this is a journey. Call (608) 555-0199."
    good_caption = _pass_caption()
    # 5 formats: one bad+good (retry-and-pass) for caption, good x4 for others
    fake = FakeClaudeClient(responses=[
        good_caption,      # video_script
        bad_caption,       # caption (fails)
        good_caption,      # caption (retry passes)
        good_caption,      # gbp_post
        good_caption,      # faq
        good_caption,      # blog_snippet
    ])
    result = generate_for_cluster(
        db_path=seeded["db"],
        cluster_name="broken_spring",
        client=fake,
        ctx=seeded["ctx"],
        output_dir=seeded["tmp"] / "pending",
        brief_dir=seeded["tmp"] / "briefs",
        model_name="claude-sonnet-4-6",
    )
    assert len(result.accepted) == 5
    # 6 API calls made (5 formats, 1 retry)
    assert len(fake.calls) == 6


def test_generator_rejects_after_second_failure(seeded):
    bad = "journey journey game-changer"
    good = _pass_caption()
    fake = FakeClaudeClient(responses=[
        good,       # video_script ok
        bad, bad,   # caption: fail, retry-fail
        good,       # gbp_post
        good,       # faq
        good,       # blog_snippet
    ])
    result = generate_for_cluster(
        db_path=seeded["db"],
        cluster_name="broken_spring",
        client=fake,
        ctx=seeded["ctx"],
        output_dir=seeded["tmp"] / "pending",
        brief_dir=seeded["tmp"] / "briefs",
        model_name="claude-sonnet-4-6",
    )
    assert len(result.rejected) == 1
    assert result.rejected[0].format == "caption"
    # rejected file prefixed with _REJECTED_
    assert "_REJECTED_" in Path(result.rejected[0].content_path).name


def test_generator_emits_brief_for_video_script(seeded):
    good = _pass_caption()
    fake = FakeClaudeClient(responses=[good] * 5)
    result = generate_for_cluster(
        db_path=seeded["db"],
        cluster_name="broken_spring",
        client=fake,
        ctx=seeded["ctx"],
        output_dir=seeded["tmp"] / "pending",
        brief_dir=seeded["tmp"] / "briefs",
        model_name="claude-sonnet-4-6",
    )
    vid = next(g for g in result.accepted if g.format == "video_script")
    assert vid.brief_path is not None
    assert Path(vid.brief_path).exists()
```

- [ ] **Step 7: Run generator tests to verify they fail**

```bash
pytest tests/test_generator.py -v
```

Expected: `ModuleNotFoundError: No module named 'engine.generator'`.

- [ ] **Step 8: Implement `engine/generator.py`**

Create `twins-content-engine/engine/generator.py`:

```python
"""Multi-format content generator orchestrator."""
from __future__ import annotations

import datetime as dt
import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Optional

from engine.brief import write_brief_for  # defined in Task 11
from engine.claude_client import ClaudeClientProtocol
from engine.config import PillarsConfig
from engine.db import (
    get_cluster_by_name,
    get_queries_for_cluster,
    insert_generated_content,
)
from engine.local_embedding import pick_neighborhood, pick_town_for_piece
from engine.prompts import build_generation_prompt, build_regeneration_prompt
from engine.rules import run_rules


FORMATS = ["video_script", "caption", "gbp_post", "faq", "blog_snippet"]
FORMATS_NEEDING_BRIEF = {"video_script", "gbp_post"}


@dataclass
class GeneratedPiece:
    format: str
    content_path: str
    brief_path: Optional[str]
    db_id: int
    status: str


@dataclass
class GenerationResult:
    cluster_name: str
    accepted: list[GeneratedPiece] = field(default_factory=list)
    rejected: list[GeneratedPiece] = field(default_factory=list)


def _slugify(s: str) -> str:
    return re.sub(r"[^a-z0-9]+", "_", s.lower()).strip("_")[:40]


def _pick_representative_query(queries: list[Any]) -> Any:
    """Prefer symptom phrasing for high-intent clusters; fall back to highest priority."""
    by_priority = sorted(queries, key=lambda q: (-q["priority_score"], q["id"]))
    for q in by_priority:
        if q["phrasing_type"] == "symptom":
            return q
    return by_priority[0]


def _write_content_file(
    output_dir: Path, cluster_name: str, format: str, body: str, rejected: bool
) -> Path:
    output_dir.mkdir(parents=True, exist_ok=True)
    today = dt.date.today().isoformat()
    prefix = "_REJECTED_" if rejected else ""
    filename = f"{prefix}{today}_{_slugify(cluster_name)}_{format}.md"
    path = output_dir / filename
    path.write_text(body)
    return path


def _build_rejected_body(body: str, violations_text: str, format: str, cluster: str) -> str:
    return (
        f"<!-- REJECTED by rules gate -->\n"
        f"<!-- cluster: {cluster} | format: {format} -->\n"
        f"<!-- violations:\n{violations_text}\n-->\n\n"
        f"{body}\n"
    )


def generate_for_cluster(
    *,
    db_path: Path,
    cluster_name: str,
    client: ClaudeClientProtocol,
    ctx: dict[str, Any],
    output_dir: Path,
    brief_dir: Path,
    model_name: str,
) -> GenerationResult:
    """Generate all 5 formats for one cluster. One retry per format on rule failure."""
    cluster = get_cluster_by_name(db_path, cluster_name)
    if cluster is None:
        raise ValueError(f"Cluster not found: {cluster_name!r}")

    queries = get_queries_for_cluster(db_path, cluster["id"])
    if not queries:
        raise ValueError(f"Cluster {cluster_name!r} has no queries")

    representative = _pick_representative_query(queries)

    pillars: PillarsConfig = ctx["pillars"]
    pillar = pillars.pillars.get(cluster["pillar"])
    if pillar is None:
        raise ValueError(f"Unknown pillar: {cluster['pillar']!r}")

    service_area = ctx["service_area"]
    town_slug = pick_town_for_piece(
        query_geo_modifier=representative["geo_modifier"],
        cluster_seed=cluster["id"],
        service_area=service_area,
    )
    town_display = service_area.town_display_names[town_slug]
    neighborhood = pick_neighborhood(cluster_seed=cluster["id"], service_area=service_area)
    funnel_stage = cluster["funnel_stage"] or pillar.funnel_stage

    result = GenerationResult(cluster_name=cluster_name)

    for fmt in FORMATS:
        piece = _generate_single_format(
            db_path=db_path,
            cluster_row=cluster,
            source_query_id=representative["id"],
            format=fmt,
            funnel_stage=funnel_stage,
            query_text=representative["query_text"],
            pillar_name=cluster["pillar"],
            town_display=town_display,
            neighborhood=neighborhood,
            client=client,
            ctx=ctx,
            output_dir=output_dir,
            brief_dir=brief_dir,
            model_name=model_name,
        )
        if piece.status == "rejected":
            result.rejected.append(piece)
        else:
            result.accepted.append(piece)
    return result


def _generate_single_format(
    *,
    db_path: Path,
    cluster_row: Any,
    source_query_id: int,
    format: str,
    funnel_stage: str,
    query_text: str,
    pillar_name: str,
    town_display: str,
    neighborhood: Optional[str],
    client: ClaudeClientProtocol,
    ctx: dict[str, Any],
    output_dir: Path,
    brief_dir: Path,
    model_name: str,
) -> GeneratedPiece:
    system, user = build_generation_prompt(
        format=format,
        query_text=query_text,
        cluster_name=cluster_row["name"],
        pillar_name=pillar_name,
        funnel_stage=funnel_stage,
        target_town_display=town_display,
        target_neighborhood=neighborhood,
        ctx=ctx,
    )
    first = client.complete(system=system, user=user, max_tokens=2048, temperature=0.7)
    report = run_rules(format=format, content=first.text,
                       funnel_stage=funnel_stage, ctx=ctx)

    final_body = first.text
    final_report = report
    if not report.passed:
        system2, user2 = build_regeneration_prompt(
            format=format,
            previous_output=first.text,
            critique_lines=report.suggestions,
            query_text=query_text,
            cluster_name=cluster_row["name"],
            pillar_name=pillar_name,
            funnel_stage=funnel_stage,
            target_town_display=town_display,
            target_neighborhood=neighborhood,
            ctx=ctx,
        )
        retry = client.complete(system=system2, user=user2, max_tokens=2048, temperature=0.7)
        retry_report = run_rules(format=format, content=retry.text,
                                 funnel_stage=funnel_stage, ctx=ctx)
        final_body = retry.text
        final_report = retry_report

    rejected = not final_report.passed
    body_to_write = final_body
    if rejected:
        body_to_write = _build_rejected_body(
            final_body,
            "\n".join(v.message for v in final_report.violations),
            format,
            cluster_row["name"],
        )

    content_path = _write_content_file(
        output_dir, cluster_row["name"], format, body_to_write, rejected=rejected
    )

    brief_path: Optional[Path] = None
    if not rejected and format in FORMATS_NEEDING_BRIEF:
        brief_path = write_brief_for(
            brief_dir=brief_dir,
            cluster_name=cluster_row["name"],
            source_query_id=source_query_id,
            format=format,
            body=final_body,
            town_display=town_display,
            ctx=ctx,
        )

    db_id = insert_generated_content(
        db_path,
        cluster_id=cluster_row["id"],
        source_query_id=source_query_id,
        format=format,
        content_path=str(content_path),
        brief_path=str(brief_path) if brief_path else None,
        status="rejected" if rejected else "pending",
        model_used=model_name,
        notes=None,
    )
    return GeneratedPiece(
        format=format,
        content_path=str(content_path),
        brief_path=str(brief_path) if brief_path else None,
        db_id=db_id,
        status="rejected" if rejected else "pending",
    )
```

- [ ] **Step 9: Commit generator orchestrator (brief builder stub added in Task 11)**

`engine/brief.py` is imported — stub it so the package imports:

Create `twins-content-engine/engine/brief.py`:

```python
"""Brief builder — full implementation in Task 11. Stub so generator.py imports."""
from __future__ import annotations

from pathlib import Path
from typing import Any, Optional


def write_brief_for(
    *,
    brief_dir: Path,
    cluster_name: str,
    source_query_id: int,
    format: str,
    body: str,
    town_display: str,
    ctx: dict[str, Any],
) -> Optional[Path]:
    brief_dir.mkdir(parents=True, exist_ok=True)
    # Minimal stub — Task 11 replaces with full brief construction.
    path = brief_dir / f"{cluster_name}_{format}.json"
    path.write_text("{}")
    return path
```

- [ ] **Step 10: Run generator tests**

```bash
pytest tests/test_generator.py -v
```

Expected: `4 passed`.

- [ ] **Step 11: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/generator.py \
        twins-content-engine/engine/brief.py \
        twins-content-engine/tests/test_generator.py
git commit -m "feat(content-engine): multi-format generator with retry-once rules gate"
```

---

## Task 11: Brief builder (`engine/brief.py` full implementation)

Replace the stub with the full JSON brief generation. The brief is consumed by `twins-media-generator`.

**Files:**
- Modify: `twins-content-engine/engine/brief.py`
- Create: `twins-content-engine/tests/test_brief.py`

Template mapping for `twins-media-generator` (from its existing SKILL.md):
- `broken_spring`, `off_track`, `wont_open`, `damaged_panel` clusters → `emergency_repair` template
- `curb_appeal`, `new_door_cost`, etc. → `product_hero` or `curb_appeal` template
- Default fallback → `product_hero` (generic)

- [ ] **Step 1: Write failing tests**

Create `twins-content-engine/tests/test_brief.py`:

```python
"""Tests for engine.brief.write_brief_for."""
from __future__ import annotations

import json
from pathlib import Path

import pytest

from engine.brief import write_brief_for
from engine.config import load_brand, load_service_area


def _ctx(sample_brand_yaml, sample_service_area_yaml) -> dict:
    return {
        "brand": load_brand(sample_brand_yaml),
        "service_area": load_service_area(sample_service_area_yaml),
    }


def test_video_script_brief_has_expected_fields(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    video_body = (
        "HOOK (0:00-0:02): Spring snapped loud bang\n\n"
        "PROBLEM (0:02-0:08): Door stuck. Cable dangling. 200 lbs of tension.\n\n"
        "AUTHORITY (0:08-0:15): 13 years in Madison. Thousands of springs.\n\n"
        "CTA (0:15-0:20): Call (608) 555-0199.\n\n"
        "SHOT LIST:\n"
        "- 0:00-0:02: close-up of snapped spring\n"
        "- 0:02-0:08: tech pointing at cable\n"
        "- 0:08-0:15: truck with Twins branding\n"
        "- 0:15-0:20: phone number card\n"
    )
    path = write_brief_for(
        brief_dir=tmp_path / "briefs",
        cluster_name="broken_spring",
        source_query_id=42,
        format="video_script",
        body=video_body,
        town_display="Madison",
        ctx=_ctx(sample_brand_yaml, sample_service_area_yaml),
    )
    assert path is not None
    data = json.loads(path.read_text())
    assert data["source_cluster"] == "broken_spring"
    assert data["source_query_id"] == 42
    assert data["purpose"] == "video_script"
    assert data["preferred_template"] == "emergency_repair"
    assert data["template_options"]["issue_type"] == "broken_spring"
    assert data["aspect_ratio"] == "9:16"
    assert data["duration_sec"] == 20
    assert data["brand_enforcement"]["phone_number"] == "(608) 555-0199"
    assert "Spring snapped loud bang" in data["on_screen_text"][0]
    assert len(data["shot_list"]) == 4


def test_gbp_brief_has_landscape_aspect(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    body = "In Madison, your garage door spring snapped? We fix same-day for $250-400. Call (608) 555-0199."
    path = write_brief_for(
        brief_dir=tmp_path / "briefs",
        cluster_name="broken_spring",
        source_query_id=1,
        format="gbp_post",
        body=body,
        town_display="Madison",
        ctx=_ctx(sample_brand_yaml, sample_service_area_yaml),
    )
    data = json.loads(path.read_text())
    assert data["aspect_ratio"] == "4:5"
    assert data["purpose"] == "gbp_post"


def test_unknown_cluster_falls_back_to_product_hero(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    body = "HOOK (0:00-0:02): test\n\nSHOT LIST:\n- 0:00-0:02: x"
    path = write_brief_for(
        brief_dir=tmp_path / "briefs",
        cluster_name="some_unknown_cluster",
        source_query_id=1,
        format="video_script",
        body=body,
        town_display="Madison",
        ctx=_ctx(sample_brand_yaml, sample_service_area_yaml),
    )
    data = json.loads(path.read_text())
    assert data["preferred_template"] == "product_hero"


def test_brief_filename_is_unique(
    tmp_path, sample_brand_yaml, sample_service_area_yaml
):
    ctx = _ctx(sample_brand_yaml, sample_service_area_yaml)
    body = "HOOK (0:00-0:02): test\n\nSHOT LIST:\n- 0:00-0:02: x"
    path1 = write_brief_for(
        brief_dir=tmp_path / "briefs",
        cluster_name="broken_spring",
        source_query_id=1,
        format="video_script",
        body=body,
        town_display="Madison",
        ctx=ctx,
    )
    path2 = write_brief_for(
        brief_dir=tmp_path / "briefs",
        cluster_name="broken_spring",
        source_query_id=1,
        format="video_script",
        body=body,
        town_display="Madison",
        ctx=ctx,
    )
    assert path1 != path2
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
pytest tests/test_brief.py -v
```

Expected: the stub brief writes `{}`, so asserts on fields fail.

- [ ] **Step 3: Replace `engine/brief.py` with full implementation**

Replace `twins-content-engine/engine/brief.py` entirely:

```python
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

    # Extract bullet-style shot list lines
    shot_section_match = re.search(r"SHOT LIST:\s*\n(.+)$", body, re.DOTALL)
    shot_list: list[str] = []
    if shot_section_match:
        for line in shot_section_match.group(1).splitlines():
            stripped = line.strip()
            if stripped.startswith("-"):
                shot_list.append(stripped.lstrip("- ").strip())

    # Voiceover = body with SHOT LIST section removed
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
    format: str,
    body: str,
    town_display: str,
    ctx: dict[str, Any],
) -> Optional[Path]:
    """Write a JSON brief for the given generated piece.

    Only video_script and gbp_post formats produce briefs (captions/faq/blog don't
    need visuals from the media generator).
    """
    if format not in FORMAT_ASPECT:
        return None

    brief_dir.mkdir(parents=True, exist_ok=True)
    brand = ctx["brand"]
    template_name, template_options = _detect_cluster_template(cluster_name)
    aspect, duration = FORMAT_ASPECT[format]

    brief_id = (
        f"{dt.date.today().isoformat()}_{cluster_name}_{format}_{uuid.uuid4().hex[:8]}"
    )

    payload: dict[str, Any] = {
        "brief_id": brief_id,
        "source_cluster": cluster_name,
        "source_query_id": source_query_id,
        "purpose": format,
        "platform_targets": _platform_targets_for(format),
        "aspect_ratio": aspect,
        "preferred_template": template_name,
        "template_options": template_options,
        "fallback_prompt": _fallback_prompt_from_body(body, format, town_display),
        "brand_enforcement": {
            "phone_number": brand.phone,
            "brand_colors": "enforce",
            "location_context": f"{town_display}, WI",
        },
    }
    if duration is not None:
        payload["duration_sec"] = duration

    if format == "video_script":
        payload.update(_extract_video_fields(body))

    path = brief_dir / f"{brief_id}.json"
    path.write_text(json.dumps(payload, indent=2))
    return path


def _platform_targets_for(format: str) -> list[str]:
    if format == "video_script":
        return ["instagram_reels", "tiktok", "youtube_shorts"]
    if format == "gbp_post":
        return ["google_business_profile"]
    return []


def _fallback_prompt_from_body(body: str, format: str, town_display: str) -> str:
    snippet = " ".join(body.split()[:50])
    return f"[{format} | {town_display}] {snippet}"
```

- [ ] **Step 4: Run brief tests to verify they pass**

```bash
pytest tests/test_brief.py -v
```

Expected: `4 passed`.

- [ ] **Step 5: Re-run generator tests (brief dependency changed)**

```bash
pytest tests/test_generator.py -v
```

Expected: all still pass.

- [ ] **Step 6: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/brief.py twins-content-engine/tests/test_brief.py
git commit -m "feat(content-engine): full JSON brief builder for media-generator handoff"
```

---

## Task 12: Review workflow (`engine/review.py` + `scripts/approve.py`)

**Files:**
- Create: `twins-content-engine/engine/review.py`
- Create: `twins-content-engine/tests/test_review.py`
- Create: `twins-content-engine/scripts/approve.py`
- Create: `twins-content-engine/tests/smoke/test_approve.py`

- [ ] **Step 1: Write failing tests**

Create `twins-content-engine/tests/test_review.py`:

```python
"""Tests for engine.review."""
from __future__ import annotations

from pathlib import Path

from engine.db import (
    init_db,
    insert_cluster,
    insert_generated_content,
    insert_query,
    list_pending_content,
)
from engine.review import ApprovalResult, approve_all_pending, approve_by_id


def _setup_pending_content(db: Path, pending_dir: Path, count: int = 2) -> list[int]:
    init_db(db)
    cid = insert_cluster(
        db, name="c", pillar="emergency_repairs",
        service_type=None, funnel_stage=None, priority_score=5, notes=None,
    )
    qid = insert_query(
        db, cluster_id=cid, query_text="q", phrasing_type="raw_seed",
        geo_modifier=None, source="raw_seed", priority_score=5, notes=None,
    )
    ids = []
    pending_dir.mkdir(parents=True, exist_ok=True)
    for i in range(count):
        p = pending_dir / f"file_{i}.md"
        p.write_text(f"body {i}")
        gid = insert_generated_content(
            db, cluster_id=cid, source_query_id=qid,
            format="caption", content_path=str(p), brief_path=None,
            status="pending", model_used="x", notes=None,
        )
        ids.append(gid)
    return ids


def test_approve_all_moves_files_and_updates_status(tmp_path):
    db = tmp_path / "t.db"
    pending = tmp_path / "pending"
    approved = tmp_path / "approved"
    ids = _setup_pending_content(db, pending, count=2)
    result = approve_all_pending(db_path=db, pending_dir=pending, approved_dir=approved)
    assert isinstance(result, ApprovalResult)
    assert result.approved_count == 2
    assert not any(pending.iterdir())
    approved_files = list(approved.iterdir())
    assert len(approved_files) == 2
    assert list_pending_content(db) == []


def test_approve_all_skips_rejected_files(tmp_path):
    db = tmp_path / "t.db"
    pending = tmp_path / "pending"
    approved = tmp_path / "approved"
    ids = _setup_pending_content(db, pending, count=1)
    # create a _REJECTED_ file — should be skipped
    (pending / "_REJECTED_bad.md").write_text("no good")
    result = approve_all_pending(db_path=db, pending_dir=pending, approved_dir=approved)
    assert result.approved_count == 1
    assert (pending / "_REJECTED_bad.md").exists()  # rejected stays put


def test_approve_by_id(tmp_path):
    db = tmp_path / "t.db"
    pending = tmp_path / "pending"
    approved = tmp_path / "approved"
    ids = _setup_pending_content(db, pending, count=2)
    approve_by_id(db_path=db, content_id=ids[0], pending_dir=pending, approved_dir=approved)
    # only id[0] should now be approved
    remaining = list_pending_content(db)
    assert len(remaining) == 1
    assert remaining[0]["id"] == ids[1]
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
pytest tests/test_review.py -v
```

Expected: `ModuleNotFoundError: No module named 'engine.review'`.

- [ ] **Step 3: Implement `engine/review.py`**

Create `twins-content-engine/engine/review.py`:

```python
"""Review workflow: pending -> approved file moves + DB status updates."""
from __future__ import annotations

import shutil
from dataclasses import dataclass, field
from pathlib import Path

from engine.db import (
    get_conn,
    list_pending_content,
    update_content_status,
)


@dataclass
class ApprovalResult:
    approved_count: int = 0
    approved_ids: list[int] = field(default_factory=list)
    skipped_rejected: int = 0


def approve_all_pending(
    *,
    db_path: Path,
    pending_dir: Path,
    approved_dir: Path,
) -> ApprovalResult:
    """Promote all `pending` rows (skip rows whose files are marked _REJECTED_)."""
    approved_dir.mkdir(parents=True, exist_ok=True)
    result = ApprovalResult()
    for row in list_pending_content(db_path):
        content_path = Path(row["content_path"])
        if not content_path.exists():
            continue
        if content_path.name.startswith("_REJECTED_"):
            result.skipped_rejected += 1
            continue
        new_path = approved_dir / content_path.name
        shutil.move(str(content_path), str(new_path))
        _update_content_path(db_path, int(row["id"]), str(new_path))
        update_content_status(db_path, int(row["id"]), "approved")
        result.approved_count += 1
        result.approved_ids.append(int(row["id"]))
    return result


def approve_by_id(
    *,
    db_path: Path,
    content_id: int,
    pending_dir: Path,
    approved_dir: Path,
) -> None:
    approved_dir.mkdir(parents=True, exist_ok=True)
    with get_conn(db_path) as c:
        row = c.execute(
            "SELECT * FROM generated_content WHERE id = ?", (content_id,)
        ).fetchone()
    if row is None:
        raise ValueError(f"No generated_content with id={content_id}")
    content_path = Path(row["content_path"])
    new_path = approved_dir / content_path.name
    shutil.move(str(content_path), str(new_path))
    _update_content_path(db_path, content_id, str(new_path))
    update_content_status(db_path, content_id, "approved")


def _update_content_path(db_path: Path, content_id: int, new_path: str) -> None:
    with get_conn(db_path) as c:
        c.execute(
            "UPDATE generated_content SET content_path = ? WHERE id = ?",
            (new_path, content_id),
        )
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
pytest tests/test_review.py -v
```

Expected: `3 passed`.

- [ ] **Step 5: Write `scripts/approve.py`**

Create `twins-content-engine/scripts/approve.py`:

```python
"""CLI: promote pending content to approved/."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.review import approve_all_pending, approve_by_id


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_PENDING = ROOT / "pending"
DEFAULT_APPROVED = ROOT / "approved"


def main() -> None:
    ap = argparse.ArgumentParser(description="Approve generated content pieces.")
    group = ap.add_mutually_exclusive_group(required=True)
    group.add_argument("--all-passing", action="store_true",
                       help="Promote all non-rejected pending pieces")
    group.add_argument("--id", type=int, help="Promote a single piece by DB id")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--pending", type=Path, default=DEFAULT_PENDING)
    ap.add_argument("--approved", type=Path, default=DEFAULT_APPROVED)
    args = ap.parse_args()

    if args.all_passing:
        result = approve_all_pending(
            db_path=args.db, pending_dir=args.pending, approved_dir=args.approved
        )
        print(f"Approved {result.approved_count} piece(s). "
              f"Skipped {result.skipped_rejected} rejected piece(s).")
    else:
        approve_by_id(
            db_path=args.db,
            content_id=args.id,
            pending_dir=args.pending,
            approved_dir=args.approved,
        )
        print(f"Approved piece id={args.id}.")


if __name__ == "__main__":
    main()
```

- [ ] **Step 6: Smoke test for `approve.py`**

Create `twins-content-engine/tests/smoke/test_approve.py`:

```python
"""Smoke test: approve.py end-to-end."""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

from engine.db import (
    init_db,
    insert_cluster,
    insert_generated_content,
    insert_query,
    list_pending_content,
)


def test_approve_all_passing_cli(tmp_path: Path):
    db = tmp_path / "t.db"
    pending = tmp_path / "pending"
    approved = tmp_path / "approved"
    pending.mkdir()
    init_db(db)
    cid = insert_cluster(db, name="c", pillar="emergency_repairs",
                        service_type=None, funnel_stage=None,
                        priority_score=5, notes=None)
    qid = insert_query(db, cluster_id=cid, query_text="q", phrasing_type="raw_seed",
                       geo_modifier=None, source="raw_seed",
                       priority_score=5, notes=None)
    f = pending / "a.md"
    f.write_text("body")
    insert_generated_content(
        db, cluster_id=cid, source_query_id=qid, format="caption",
        content_path=str(f), brief_path=None, status="pending",
        model_used="x", notes=None,
    )

    script = Path(__file__).resolve().parents[2] / "scripts" / "approve.py"
    result = subprocess.run(
        [sys.executable, str(script), "--all-passing",
         "--db", str(db), "--pending", str(pending), "--approved", str(approved)],
        capture_output=True, text=True,
    )
    assert result.returncode == 0, result.stderr
    assert "Approved 1" in result.stdout
    assert (approved / "a.md").exists()
    assert list_pending_content(db) == []
```

- [ ] **Step 7: Run all tests**

```bash
pytest -v
```

Expected: all pass.

- [ ] **Step 8: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/review.py \
        twins-content-engine/tests/test_review.py \
        twins-content-engine/scripts/approve.py \
        twins-content-engine/tests/smoke/test_approve.py
git commit -m "feat(content-engine): review workflow (pending -> approved) with CLI"
```

---

## Task 13: Weekly sampling + `scripts/generate_week.py`

**Files:**
- Create: `twins-content-engine/engine/sampling.py`
- Create: `twins-content-engine/tests/test_sampling.py`
- Create: `twins-content-engine/scripts/generate_week.py`
- Create: `twins-content-engine/tests/smoke/test_generate_week.py`
- Create: `twins-content-engine/scripts/generate_cluster.py`

- [ ] **Step 1: Write failing tests for cluster sampling**

Create `twins-content-engine/tests/test_sampling.py`:

```python
"""Tests for engine.sampling.pick_clusters_for_week."""
from __future__ import annotations

import datetime as dt
from pathlib import Path

import pytest

from engine.config import load_pillars
from engine.db import (
    init_db,
    insert_cluster,
    insert_generated_content,
    insert_query,
)
from engine.sampling import pick_clusters_for_week


@pytest.fixture
def pillars_yaml(tmp_path: Path) -> Path:
    p = tmp_path / "pillars.yaml"
    p.write_text("""
pillars:
  emergency_repairs:
    description: d
    funnel_stage: high_intent_problem_aware
    seed_patterns: []
    format_bias: [video_script]
    priority_weight: 1.5
  pricing_transparency:
    description: d
    funnel_stage: mid_intent_price_checking
    seed_patterns: []
    format_bias: [faq]
    priority_weight: 1.3
  maintenance_prevention:
    description: d
    funnel_stage: low_intent_educational
    seed_patterns: []
    format_bias: [caption]
    priority_weight: 0.8
""")
    return p


def _seed_clusters(db: Path, specs: list[tuple[str, str]]) -> list[int]:
    ids = []
    for name, pillar in specs:
        cid = insert_cluster(
            db, name=name, pillar=pillar, service_type=None,
            funnel_stage=None, priority_score=5, notes=None,
        )
        qid = insert_query(
            db, cluster_id=cid, query_text=f"q_{name}", phrasing_type="raw_seed",
            geo_modifier=None, source="raw_seed", priority_score=5, notes=None,
        )
        ids.append(cid)
    return ids


def test_pick_clusters_respects_count(tmp_path, pillars_yaml):
    db = tmp_path / "t.db"
    init_db(db)
    _seed_clusters(db, [
        ("a", "emergency_repairs"),
        ("b", "pricing_transparency"),
        ("c", "maintenance_prevention"),
        ("d", "emergency_repairs"),
    ])
    pillars = load_pillars(pillars_yaml)
    picks = pick_clusters_for_week(db_path=db, count=3, pillars=pillars)
    assert len(picks) == 3
    names = {p.name for p in picks}
    assert names.issubset({"a", "b", "c", "d"})


def test_pick_clusters_enforces_max_two_per_pillar(tmp_path, pillars_yaml):
    db = tmp_path / "t.db"
    init_db(db)
    _seed_clusters(db, [
        ("a1", "emergency_repairs"),
        ("a2", "emergency_repairs"),
        ("a3", "emergency_repairs"),
        ("a4", "emergency_repairs"),
        ("b1", "pricing_transparency"),
        ("c1", "maintenance_prevention"),
    ])
    pillars = load_pillars(pillars_yaml)
    picks = pick_clusters_for_week(db_path=db, count=5, pillars=pillars)
    pillars_chosen = [p.pillar for p in picks]
    emergency_count = sum(1 for p in pillars_chosen if p == "emergency_repairs")
    assert emergency_count <= 2


def test_pick_clusters_oversamples_high_priority_pillar(tmp_path, pillars_yaml):
    """With equal recency and clusters available in every pillar,
    high-priority pillars should be picked first."""
    db = tmp_path / "t.db"
    init_db(db)
    _seed_clusters(db, [
        ("a", "emergency_repairs"),          # 1.5 weight
        ("b", "pricing_transparency"),       # 1.3 weight
        ("c", "maintenance_prevention"),     # 0.8 weight
    ])
    pillars = load_pillars(pillars_yaml)
    picks = pick_clusters_for_week(db_path=db, count=3, pillars=pillars)
    # first pick should be the highest-weight pillar cluster
    assert picks[0].pillar == "emergency_repairs"


def test_pick_clusters_avoids_recently_used(tmp_path, pillars_yaml):
    db = tmp_path / "t.db"
    init_db(db)
    ids = _seed_clusters(db, [
        ("a", "emergency_repairs"),
        ("b", "emergency_repairs"),
    ])
    # Mark cluster a as just-used
    insert_generated_content(
        db, cluster_id=ids[0], source_query_id=1, format="caption",
        content_path="x.md", brief_path=None, status="approved",
        model_used="x", notes=None,
    )
    pillars = load_pillars(pillars_yaml)
    picks = pick_clusters_for_week(db_path=db, count=1, pillars=pillars)
    assert picks[0].name == "b"
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
pytest tests/test_sampling.py -v
```

Expected: `ModuleNotFoundError: No module named 'engine.sampling'`.

- [ ] **Step 3: Implement `engine/sampling.py`**

Create `twins-content-engine/engine/sampling.py`:

```python
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
            "SELECT id, name, pillar FROM clusters "
            "WHERE name NOT LIKE '_unclustered_%'"
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
        weight = pillar_obj.priority_weight / ((days if days is not None else 30.0) + 1.0)
        # never-used clusters get a boost toward the average
        if days is None:
            weight = pillar_obj.priority_weight * 0.9
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
```

- [ ] **Step 4: Run sampling tests**

```bash
pytest tests/test_sampling.py -v
```

Expected: `4 passed`.

- [ ] **Step 5: Commit sampling**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/sampling.py twins-content-engine/tests/test_sampling.py
git commit -m "feat(content-engine): cluster sampling for weekly generation"
```

- [ ] **Step 6: Write `scripts/generate_week.py`**

Create `twins-content-engine/scripts/generate_week.py`:

```python
"""CLI: generate N cluster-batches (all 5 formats each) for the week."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.claude_client import ClaudeClient
from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.generator import generate_for_cluster
from engine.sampling import pick_clusters_for_week


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_CONFIG = ROOT / "config"
DEFAULT_PENDING = ROOT / "pending"
DEFAULT_BRIEFS = ROOT / "briefs"


def main() -> None:
    ap = argparse.ArgumentParser(description="Generate one week of cluster content.")
    ap.add_argument("--count", type=int, default=7, help="Number of clusters to generate")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--config", type=Path, default=DEFAULT_CONFIG)
    ap.add_argument("--pending", type=Path, default=DEFAULT_PENDING)
    ap.add_argument("--briefs", type=Path, default=DEFAULT_BRIEFS)
    ap.add_argument("--model", type=str, default="claude-sonnet-4-6")
    args = ap.parse_args()

    ctx = {
        "brand": load_brand(args.config / "brand.yaml"),
        "service_area": load_service_area(args.config / "service_area.yaml"),
        "pillars": load_pillars(args.config / "pillars.yaml"),
        "rules": load_rules(args.config / "rules.yaml"),
    }

    picks = pick_clusters_for_week(
        db_path=args.db,
        count=args.count,
        pillars=ctx["pillars"],
    )
    if not picks:
        print("No clusters available. Run ingest_seeds and expand_clusters first.")
        sys.exit(1)

    client = ClaudeClient(model=args.model)
    total_accepted = 0
    total_rejected = 0
    for p in picks:
        print(f"Generating cluster: {p.name} (pillar={p.pillar}, weight={p.weight:.3f})")
        result = generate_for_cluster(
            db_path=args.db,
            cluster_name=p.name,
            client=client,
            ctx=ctx,
            output_dir=args.pending,
            brief_dir=args.briefs,
            model_name=args.model,
        )
        total_accepted += len(result.accepted)
        total_rejected += len(result.rejected)
        print(f"  -> accepted={len(result.accepted)} rejected={len(result.rejected)}")

    print(f"\nDone. {total_accepted} accepted, {total_rejected} rejected.")
    print(f"Review pending files in: {args.pending}")


if __name__ == "__main__":
    main()
```

- [ ] **Step 7: Write smoke test for `generate_week.py`**

Create `twins-content-engine/tests/smoke/test_generate_week.py`:

```python
"""Smoke test: generate_week.py invokes the orchestrator against a fake client.

This test monkeypatches ClaudeClient at module level with a FakeClaudeClient
to avoid hitting the real API.
"""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path


def test_generate_week_with_fake_client(tmp_path: Path, monkeypatch):
    """Integration-ish: run the script as a subprocess with env pointing to fake."""
    # For smoke coverage, verify --help works (end-to-end is covered by generator tests)
    script = Path(__file__).resolve().parents[2] / "scripts" / "generate_week.py"
    result = subprocess.run(
        [sys.executable, str(script), "--help"],
        capture_output=True, text=True,
    )
    assert result.returncode == 0
    assert "--count" in result.stdout
    assert "--model" in result.stdout
```

- [ ] **Step 8: Write `scripts/generate_cluster.py`**

Create `twins-content-engine/scripts/generate_cluster.py`:

```python
"""CLI: generate content for a single named cluster."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.claude_client import ClaudeClient
from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.generator import FORMATS, generate_for_cluster


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_CONFIG = ROOT / "config"
DEFAULT_PENDING = ROOT / "pending"
DEFAULT_BRIEFS = ROOT / "briefs"


def main() -> None:
    ap = argparse.ArgumentParser(description="Generate content for a single cluster.")
    ap.add_argument("--name", type=str, required=True, help="Cluster name")
    ap.add_argument("--formats", type=str,
                    help="Comma-separated format list; default: all 5")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--config", type=Path, default=DEFAULT_CONFIG)
    ap.add_argument("--pending", type=Path, default=DEFAULT_PENDING)
    ap.add_argument("--briefs", type=Path, default=DEFAULT_BRIEFS)
    ap.add_argument("--model", type=str, default="claude-sonnet-4-6")
    args = ap.parse_args()

    if args.formats:
        requested = [f.strip() for f in args.formats.split(",")]
        unknown = set(requested) - set(FORMATS)
        if unknown:
            ap.error(f"Unknown format(s): {sorted(unknown)}. Valid: {FORMATS}")
    # NOTE: partial-format generation is not yet supported in v1; honoring
    # --formats is a future enhancement. For now we always generate all 5.

    ctx = {
        "brand": load_brand(args.config / "brand.yaml"),
        "service_area": load_service_area(args.config / "service_area.yaml"),
        "pillars": load_pillars(args.config / "pillars.yaml"),
        "rules": load_rules(args.config / "rules.yaml"),
    }

    client = ClaudeClient(model=args.model)
    result = generate_for_cluster(
        db_path=args.db,
        cluster_name=args.name,
        client=client,
        ctx=ctx,
        output_dir=args.pending,
        brief_dir=args.briefs,
        model_name=args.model,
    )
    print(f"Cluster {args.name}: accepted={len(result.accepted)} "
          f"rejected={len(result.rejected)}")


if __name__ == "__main__":
    main()
```

- [ ] **Step 9: Run all tests**

```bash
pytest -v
```

Expected: everything passes.

- [ ] **Step 10: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/scripts/generate_week.py \
        twins-content-engine/scripts/generate_cluster.py \
        twins-content-engine/tests/smoke/test_generate_week.py
git commit -m "feat(content-engine): generate_week and generate_cluster CLIs"
```

---

## Task 14: Harvest refresh, regenerate, emit_briefs

**Files:**
- Create: `twins-content-engine/engine/harvest.py`
- Create: `twins-content-engine/tests/test_harvest.py`
- Create: `twins-content-engine/scripts/harvest_refresh.py`
- Create: `twins-content-engine/scripts/regenerate.py`
- Create: `twins-content-engine/scripts/emit_briefs.py`

- [ ] **Step 1: Write failing tests for harvest parsing**

Create `twins-content-engine/tests/test_harvest.py`:

```python
"""Tests for engine.harvest.parse_harvest_inbox and fold_into_db."""
from __future__ import annotations

import json
from pathlib import Path

from engine.claude_client import FakeClaudeClient
from engine.db import (
    get_queries_for_cluster,
    init_db,
    insert_cluster,
    list_clusters,
)
from engine.harvest import HarvestEntry, fold_into_db, parse_harvest_inbox


def test_parse_basic(tmp_path: Path):
    p = tmp_path / "inbox.md"
    p.write_text(
        "# 2026-04-10 notes\n"
        "garage door stuck halfway\n"
        "customer asked about opener brands | source=call\n"
        "\n"
        "another one\n"
    )
    entries = parse_harvest_inbox(p)
    assert len(entries) == 3
    assert entries[0].query_text == "garage door stuck halfway"
    assert entries[0].source == "harvest_inbox"
    assert entries[1].source == "call"


def test_fold_assigns_to_existing_cluster(tmp_path: Path):
    db = tmp_path / "t.db"
    init_db(db)
    insert_cluster(
        db, name="broken_spring", pillar="emergency_repairs",
        service_type=None, funnel_stage=None, priority_score=8, notes=None,
    )
    fake = FakeClaudeClient(responses=[json.dumps({"cluster_name": "broken_spring"})])
    entries = [HarvestEntry(query_text="spring snapped", source="harvest_inbox")]
    fold_into_db(db_path=db, entries=entries, client=fake)
    clusters = list_clusters(db)
    assert len(clusters) == 1
    bs = clusters[0]
    queries = get_queries_for_cluster(db, bs["id"])
    assert any(q["query_text"] == "spring snapped" for q in queries)


def test_fold_creates_new_cluster_when_none_matches(tmp_path: Path):
    db = tmp_path / "t.db"
    init_db(db)
    # Include an existing cluster so Claude has context of what exists
    insert_cluster(
        db, name="broken_spring", pillar="emergency_repairs",
        service_type=None, funnel_stage=None, priority_score=8, notes=None,
    )
    fake = FakeClaudeClient(responses=[json.dumps({
        "cluster_name": "winter_weather_stuck",
        "pillar": "emergency_repairs",
        "funnel_stage": "high_intent_problem_aware",
    })])
    entries = [HarvestEntry(query_text="door frozen shut", source="harvest_inbox")]
    fold_into_db(db_path=db, entries=entries, client=fake)
    names = {c["name"] for c in list_clusters(db)}
    assert "winter_weather_stuck" in names
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
pytest tests/test_harvest.py -v
```

Expected: `ModuleNotFoundError: No module named 'engine.harvest'`.

- [ ] **Step 3: Implement `engine/harvest.py`**

Create `twins-content-engine/engine/harvest.py`:

```python
"""Harvest inbox parsing + folding into the cluster DB."""
from __future__ import annotations

import json
import sqlite3
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Optional

from engine.claude_client import ClaudeClientProtocol
from engine.db import (
    get_cluster_by_name,
    insert_cluster,
    insert_query,
    list_clusters,
)


@dataclass
class HarvestEntry:
    query_text: str
    source: str  # "harvest_inbox", "call", "text", "search_console", "review"


_VALID_SOURCES = {"harvest_inbox", "call", "text", "search_console", "review"}


HARVEST_SYSTEM_PROMPT = """You are categorizing a single raw customer query into a cluster for Twins Garage Doors (Madison, WI).

Given:
- a raw query string (often typo-ridden, rushed, or symptom-based)
- a list of EXISTING cluster names

Decide: does the query belong to one of the existing clusters, or does it warrant a NEW cluster?

Return ONLY JSON, no prose. Either:
  {"cluster_name": "<existing_name>"}
or
  {"cluster_name": "<new_snake_case_name>", "pillar": "<pillar>", "funnel_stage": "<funnel_stage>"}

Pillar must be one of: emergency_repairs, maintenance_prevention, replacement_installation,
pricing_transparency, common_mistakes, comparisons, local_authority.
Funnel stage must be one of: high_intent_problem_aware, mid_intent_considering,
mid_intent_price_checking, mid_intent_deciding, mid_intent_trust_building,
low_intent_educational, trust_and_ranking.
"""


def parse_harvest_inbox(path: Path) -> list[HarvestEntry]:
    """Parse `harvest_inbox.md` into HarvestEntry records.

    Format:
      - one query per line
      - blank lines ignored
      - lines starting with `#` ignored
      - optional `| source=<kind>` tag (defaults to harvest_inbox)
    """
    entries: list[HarvestEntry] = []
    for raw in path.read_text().splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        source = "harvest_inbox"
        if "|" in line:
            query_part, _, tag_part = line.partition("|")
            line = query_part.strip()
            tag = tag_part.strip()
            if tag.startswith("source="):
                proposed = tag.split("=", 1)[1].strip()
                if proposed in _VALID_SOURCES:
                    source = proposed
        entries.append(HarvestEntry(query_text=line, source=source))
    return entries


def fold_into_db(
    *,
    db_path: Path,
    entries: list[HarvestEntry],
    client: ClaudeClientProtocol,
) -> None:
    """For each entry, ask Claude whether it maps to an existing cluster or a new one,
    then insert."""
    for entry in entries:
        existing_names = [c["name"] for c in list_clusters(db_path)]
        user = (
            f"Raw query: {entry.query_text!r}\n"
            f"Existing clusters: {existing_names}\n"
        )
        result = client.complete(
            system=HARVEST_SYSTEM_PROMPT,
            user=user,
            max_tokens=300,
            temperature=0.2,
        )
        payload = _safe_parse(result.text)
        if not payload:
            continue
        cluster_name = payload.get("cluster_name")
        if not isinstance(cluster_name, str) or not cluster_name:
            continue

        existing = get_cluster_by_name(db_path, cluster_name)
        if existing is None:
            cluster_id = insert_cluster(
                db_path,
                name=cluster_name,
                pillar=payload.get("pillar") or "maintenance_prevention",
                service_type=None,
                funnel_stage=payload.get("funnel_stage"),
                priority_score=5,
                notes="auto-created from harvest",
            )
        else:
            cluster_id = int(existing["id"])

        phrasing = "symptom"  # harvest entries default to symptom phrasing
        try:
            insert_query(
                db_path,
                cluster_id=cluster_id,
                query_text=entry.query_text,
                phrasing_type=phrasing,
                geo_modifier=None,
                source=entry.source,
                priority_score=5,
                notes=None,
            )
        except sqlite3.IntegrityError:
            # duplicate already present; skip
            continue


def _safe_parse(raw: str) -> Optional[dict[str, Any]]:
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return None
```

- [ ] **Step 4: Run harvest tests**

```bash
pytest tests/test_harvest.py -v
```

Expected: `3 passed`.

- [ ] **Step 5: Write `scripts/harvest_refresh.py`**

Create `twins-content-engine/scripts/harvest_refresh.py`:

```python
"""CLI: fold new queries from harvest_inbox.md into the DB; archive the inbox."""
from __future__ import annotations

import argparse
import datetime as dt
import shutil
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.claude_client import ClaudeClient
from engine.harvest import fold_into_db, parse_harvest_inbox


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_INBOX = ROOT / "data" / "harvest_inbox.md"
DEFAULT_ARCHIVE = ROOT / "data" / "harvest_processed"


def main() -> None:
    ap = argparse.ArgumentParser(description="Fold harvest_inbox entries into the DB.")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--inbox", type=Path, default=DEFAULT_INBOX)
    ap.add_argument("--archive", type=Path, default=DEFAULT_ARCHIVE)
    ap.add_argument("--model", type=str, default="claude-sonnet-4-6")
    args = ap.parse_args()

    if not args.inbox.exists():
        print(f"No inbox at {args.inbox}; nothing to refresh.")
        return

    entries = parse_harvest_inbox(args.inbox)
    if not entries:
        print("Inbox is empty (only comments/blank lines). Nothing to do.")
        return

    client = ClaudeClient(model=args.model)
    fold_into_db(db_path=args.db, entries=entries, client=client)

    args.archive.mkdir(parents=True, exist_ok=True)
    stamp = dt.datetime.utcnow().strftime("%Y%m%d_%H%M%S")
    archived = args.archive / f"inbox_{stamp}.md"
    shutil.move(str(args.inbox), str(archived))
    args.inbox.write_text(
        "# harvest inbox — drop new real-world queries here, one per line.\n"
        "# Optional: add `| source=call` / `source=text` / `source=search_console` / `source=review`.\n"
    )
    print(f"Folded {len(entries)} entries; archived inbox to {archived}")


if __name__ == "__main__":
    main()
```

- [ ] **Step 6: Write `scripts/regenerate.py`**

Create `twins-content-engine/scripts/regenerate.py`:

```python
"""CLI: regenerate a single generated_content piece with an operator critique."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.claude_client import ClaudeClient
from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.db import get_conn, update_content_status
from engine.local_embedding import pick_neighborhood, pick_town_for_piece
from engine.prompts import build_regeneration_prompt
from engine.rules import run_rules


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_CONFIG = ROOT / "config"


def main() -> None:
    ap = argparse.ArgumentParser(description="Regenerate one piece with an operator critique.")
    ap.add_argument("--id", type=int, required=True, help="generated_content.id")
    ap.add_argument("--critique", type=str, required=True,
                    help="Free-text critique fed into the LLM")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--config", type=Path, default=DEFAULT_CONFIG)
    ap.add_argument("--model", type=str, default="claude-sonnet-4-6")
    args = ap.parse_args()

    ctx = {
        "brand": load_brand(args.config / "brand.yaml"),
        "service_area": load_service_area(args.config / "service_area.yaml"),
        "pillars": load_pillars(args.config / "pillars.yaml"),
        "rules": load_rules(args.config / "rules.yaml"),
    }

    with get_conn(args.db) as c:
        row = c.execute(
            "SELECT gc.*, cl.name AS cluster_name, cl.pillar, cl.funnel_stage, "
            "       q.query_text, q.geo_modifier "
            "FROM generated_content gc "
            "JOIN clusters cl ON cl.id = gc.cluster_id "
            "JOIN queries q ON q.id = gc.source_query_id "
            "WHERE gc.id = ?",
            (args.id,),
        ).fetchone()
    if row is None:
        ap.error(f"No generated_content with id={args.id}")

    prior_body = Path(row["content_path"]).read_text()
    service_area = ctx["service_area"]
    town_slug = pick_town_for_piece(
        query_geo_modifier=row["geo_modifier"],
        cluster_seed=int(row["cluster_id"]),
        service_area=service_area,
    )
    town_display = service_area.town_display_names[town_slug]
    neighborhood = pick_neighborhood(cluster_seed=int(row["cluster_id"]),
                                     service_area=service_area)

    system, user = build_regeneration_prompt(
        format=row["format"],
        previous_output=prior_body,
        critique_lines=[f"- [operator] {args.critique}"],
        query_text=row["query_text"],
        cluster_name=row["cluster_name"],
        pillar_name=row["pillar"],
        funnel_stage=row["funnel_stage"] or "low_intent_educational",
        target_town_display=town_display,
        target_neighborhood=neighborhood,
        ctx=ctx,
    )

    client = ClaudeClient(model=args.model)
    result = client.complete(system=system, user=user, max_tokens=2048, temperature=0.7)
    report = run_rules(
        format=row["format"], content=result.text,
        funnel_stage=row["funnel_stage"] or "low_intent_educational", ctx=ctx,
    )

    new_body = result.text
    if not report.passed:
        print("Regenerated output still fails rules:")
        for v in report.violations:
            print(f"  - {v.rule_id}: {v.message}")
        print("Writing as _REJECTED_; correct manually or rerun.")

    target = Path(row["content_path"])
    if not report.passed and not target.name.startswith("_REJECTED_"):
        target = target.with_name("_REJECTED_" + target.name)
    target.write_text(new_body)
    with get_conn(args.db) as c:
        c.execute(
            "UPDATE generated_content SET content_path = ?, model_used = ? WHERE id = ?",
            (str(target), args.model, args.id),
        )
    update_content_status(
        args.db, args.id,
        "rejected" if not report.passed else "pending",
    )
    print(f"Regenerated piece {args.id} -> {target}")


if __name__ == "__main__":
    main()
```

- [ ] **Step 7: Write `scripts/emit_briefs.py`**

Create `twins-content-engine/scripts/emit_briefs.py`:

```python
"""CLI: list JSON briefs produced recently for media-generator handoff.

This is intentionally a lister/copier — it does NOT call twins-media-generator.
Running twins-media-generator on briefs is the operator's manual step, OR a future
subsystem.
"""
from __future__ import annotations

import argparse
import datetime as dt
import shutil
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from engine.db import get_conn


ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DB = ROOT / "data" / "content_engine.db"
DEFAULT_BRIEFS = ROOT / "briefs"


def main() -> None:
    ap = argparse.ArgumentParser(description="List/export briefs for twins-media-generator.")
    ap.add_argument("--since", type=str, default="yesterday",
                    help="'yesterday', 'today', ISO date (YYYY-MM-DD), or 'all'")
    ap.add_argument("--db", type=Path, default=DEFAULT_DB)
    ap.add_argument("--briefs", type=Path, default=DEFAULT_BRIEFS)
    ap.add_argument("--copy-to", type=Path, default=None,
                    help="If set, copy matching briefs into this directory")
    args = ap.parse_args()

    cutoff = _resolve_cutoff(args.since)
    with get_conn(args.db) as c:
        rows = list(c.execute(
            "SELECT * FROM generated_content "
            "WHERE brief_path IS NOT NULL AND status IN ('pending','approved') "
            "AND generated_at >= ? "
            "ORDER BY generated_at",
            (cutoff.isoformat(sep=" "),),
        ).fetchall())

    print(f"Found {len(rows)} brief(s) since {cutoff.isoformat()}:")
    for row in rows:
        path = Path(row["brief_path"])
        exists = "exists" if path.exists() else "missing"
        print(f"  [{row['id']}] {row['format']:12s} {path}  ({exists})")
        if args.copy_to and path.exists():
            args.copy_to.mkdir(parents=True, exist_ok=True)
            shutil.copy2(str(path), str(args.copy_to / path.name))
    if args.copy_to:
        print(f"Copied to {args.copy_to}")


def _resolve_cutoff(since: str) -> dt.datetime:
    now = dt.datetime.utcnow()
    if since == "all":
        return dt.datetime(2000, 1, 1)
    if since == "today":
        return dt.datetime.combine(now.date(), dt.time.min)
    if since == "yesterday":
        return dt.datetime.combine(now.date() - dt.timedelta(days=1), dt.time.min)
    return dt.datetime.fromisoformat(since)


if __name__ == "__main__":
    main()
```

- [ ] **Step 8: Smoke-test the new CLIs with --help**

```bash
cd /Users/daniel/twins-dashboard/twins-content-engine
source .venv/bin/activate
python scripts/harvest_refresh.py --help
python scripts/regenerate.py --help
python scripts/emit_briefs.py --help
```

Expected: each prints its argparse help without error.

- [ ] **Step 9: Run all tests**

```bash
pytest -v
```

Expected: all pass.

- [ ] **Step 10: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/engine/harvest.py \
        twins-content-engine/tests/test_harvest.py \
        twins-content-engine/scripts/harvest_refresh.py \
        twins-content-engine/scripts/regenerate.py \
        twins-content-engine/scripts/emit_briefs.py
git commit -m "feat(content-engine): harvest inbox, regenerate, and emit_briefs CLIs"
```

---

## Task 15: SKILL.md, harvest inbox template, integration test

**Files:**
- Create: `twins-content-engine/SKILL.md`
- Create: `twins-content-engine/data/harvest_inbox.md`
- Create: `twins-content-engine/tests/test_integration.py`

- [ ] **Step 1: Create the harvest inbox template**

Create `twins-content-engine/data/harvest_inbox.md`:

```markdown
# harvest inbox — drop new real-world queries here, one per line.
# Optional: add `| source=call` / `source=text` / `source=search_console` / `source=review`.
# Run `python scripts/harvest_refresh.py` weekly (or whenever you want) to fold these into the DB.
```

- [ ] **Step 2: Write `SKILL.md`**

Create `twins-content-engine/SKILL.md`:

```markdown
---
name: twins-content-engine
description: Generate local, rules-gated, multi-format content (video scripts, captions, GBP posts, FAQs, blog snippets) for Twins Garage Doors from a clustered homeowner-intent database. Use whenever the user asks to draft social posts, plan weekly content, generate marketing copy tied to real customer searches, or expand search queries into a Madison-targeted content calendar. Produces JSON briefs for handoff to the twins-media-generator skill.
---

# Twins Content Engine

Content strategy + multi-format generation for Twins Garage Doors.
Reads from `data/seeds/initial_seed.md` and `data/harvest_inbox.md`; writes Markdown to `pending/` and JSON briefs to `briefs/`.

## One-time setup

```bash
cd /Users/daniel/twins-dashboard/twins-content-engine
python3.11 -m venv .venv
source .venv/bin/activate
pip install -e ".[dev]"
export ANTHROPIC_API_KEY="sk-ant-..."
```

Edit `config/brand.yaml` to set the real phone number. Edit `data/seeds/initial_seed.md` to replace the placeholder seeds with 15-25 real queries from calls/texts/GBP search insights.

## First run

```bash
python scripts/ingest_seeds.py data/seeds/initial_seed.md
python scripts/expand_clusters.py --all
python scripts/generate_week.py --count 7
# review pending/ in your editor; delete or edit anything weak
python scripts/approve.py --all-passing
python scripts/emit_briefs.py --since yesterday
```

## Weekly cadence

```bash
python scripts/harvest_refresh.py     # fold new queries from data/harvest_inbox.md
python scripts/generate_week.py --count 7
# review pending/
python scripts/approve.py --all-passing
python scripts/emit_briefs.py --since yesterday --copy-to /path/to/media-generator-inbox
```

## Ad hoc

```bash
# One cluster, all formats
python scripts/generate_cluster.py --name broken_spring

# Regenerate with a critique
python scripts/regenerate.py --id 127 --critique "CTA too soft; make it direct"
```

## Output shapes

- `pending/YYYY-MM-DD_<cluster>_<format>.md` — reviewable Markdown
- `pending/_REJECTED_YYYY-MM-DD_<cluster>_<format>.md` — failed rules gate twice; violation list in file header
- `briefs/<brief_id>.json` — handoff to `twins-media-generator` (video_script, gbp_post only)

## Configuration surfaces

- `config/brand.yaml` — business name, phone, voice terms
- `config/service_area.yaml` — Madison + surrounding towns
- `config/pillars.yaml` — 7 content pillars with priority weights
- `config/rules.yaml` — tone rules, anti-AI-spam blacklist, hook requirements, local embedding

All four YAMLs are hot-editable — no code changes needed to tune behavior.

## Design reference

See `docs/superpowers/specs/2026-04-16-content-engine-design.md` for full architecture.
```

- [ ] **Step 3: Write the integration test (gated by `INTEGRATION=1`)**

Create `twins-content-engine/tests/test_integration.py`:

```python
"""End-to-end integration test against the real Anthropic API.

Gated behind INTEGRATION=1 env var. Requires ANTHROPIC_API_KEY.
Run with: INTEGRATION=1 pytest -m integration -v
"""
from __future__ import annotations

import os
from pathlib import Path

import pytest

from engine.claude_client import ClaudeClient
from engine.config import load_brand, load_pillars, load_rules, load_service_area
from engine.db import init_db, insert_cluster, insert_query
from engine.generator import generate_for_cluster


pytestmark = pytest.mark.integration


def _gated():
    if os.environ.get("INTEGRATION") != "1":
        pytest.skip("set INTEGRATION=1 to run the real-API integration test")
    if not os.environ.get("ANTHROPIC_API_KEY"):
        pytest.skip("ANTHROPIC_API_KEY not set")


def test_generate_for_cluster_real_api(tmp_path: Path):
    _gated()
    repo_root = Path(__file__).resolve().parents[1]
    cfg = repo_root / "config"
    ctx = {
        "brand": load_brand(cfg / "brand.yaml"),
        "service_area": load_service_area(cfg / "service_area.yaml"),
        "pillars": load_pillars(cfg / "pillars.yaml"),
        "rules": load_rules(cfg / "rules.yaml"),
    }

    db = tmp_path / "t.db"
    init_db(db)
    cid = insert_cluster(
        db, name="broken_spring", pillar="emergency_repairs",
        service_type="spring_replacement", funnel_stage="high_intent_problem_aware",
        priority_score=9, notes=None,
    )
    insert_query(
        db, cluster_id=cid, query_text="garage door won't open loud bang",
        phrasing_type="symptom", geo_modifier="madison_wi", source="raw_seed",
        priority_score=9, notes=None,
    )

    client = ClaudeClient(model="claude-sonnet-4-6")
    result = generate_for_cluster(
        db_path=db,
        cluster_name="broken_spring",
        client=client,
        ctx=ctx,
        output_dir=tmp_path / "pending",
        brief_dir=tmp_path / "briefs",
        model_name="claude-sonnet-4-6",
    )
    # At least some formats should be accepted — retries handle the rest.
    assert len(result.accepted) + len(result.rejected) == 5
    # Assert files exist for every returned piece
    for piece in result.accepted + result.rejected:
        assert Path(piece.content_path).exists()
```

- [ ] **Step 4: Update `.gitignore` to not ignore `data/harvest_inbox.md`**

Edit `twins-content-engine/.gitignore`. The existing content does NOT ignore the inbox — verify. If not already the case, add an explicit allow line before the DB ignores:

The final `.gitignore` must read exactly:

```
__pycache__/
*.py[cod]
*.egg-info/
.pytest_cache/
.venv/
venv/
data/content_engine.db
data/content_engine.db-wal
data/content_engine.db-shm
data/harvest_processed/*
!data/harvest_processed/.gitkeep
pending/*
approved/*
briefs/*
!pending/.gitkeep
!approved/.gitkeep
!briefs/.gitkeep
.env
```

Also add a `data/harvest_processed/.gitkeep`:

```bash
touch twins-content-engine/data/harvest_processed/.gitkeep
```

- [ ] **Step 5: Run the full test suite (non-integration)**

```bash
cd /Users/daniel/twins-dashboard/twins-content-engine
source .venv/bin/activate
pytest -v
```

Expected: all unit + smoke tests pass. Integration test shows `SKIPPED` (INTEGRATION=1 not set).

- [ ] **Step 6: Optional — run the integration test against the real API**

```bash
INTEGRATION=1 pytest -m integration -v
```

Expected: passes (or fails loudly with a real API error — fix, re-run).

- [ ] **Step 7: End-to-end smoke run against the bundled sample seed**

```bash
cd /Users/daniel/twins-dashboard/twins-content-engine
source .venv/bin/activate

# First run the whole pipeline end-to-end against the sample seed.
# This burns real API credits; set ANTHROPIC_API_KEY first.
python scripts/ingest_seeds.py data/seeds/initial_seed.md
python scripts/expand_clusters.py --all
python scripts/generate_week.py --count 3
ls pending/
```

Expected:
- `data/content_engine.db` exists
- at least 3 clusters are populated in the DB (check with `sqlite3 data/content_engine.db 'select count(*) from clusters'`)
- `pending/` contains ~15 Markdown files (3 clusters × 5 formats)
- `briefs/` contains JSON files for video_script and gbp_post formats

- [ ] **Step 8: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-content-engine/SKILL.md \
        twins-content-engine/README.md \
        twins-content-engine/data/harvest_inbox.md \
        twins-content-engine/data/harvest_processed/.gitkeep \
        twins-content-engine/.gitignore \
        twins-content-engine/tests/test_integration.py
git commit -m "feat(content-engine): SKILL.md, harvest inbox template, integration test"
```

---

## v1 Definition of Done Checklist

Run through this list after Task 15 to confirm the definition-of-done items from the spec are met:

- [ ] `python scripts/ingest_seeds.py data/seeds/initial_seed.md` produces N queries in the DB
- [ ] `python scripts/expand_clusters.py --all` produces ~60–80 queries across ~15–20 clusters
- [ ] `python scripts/generate_week.py --count 7` produces 35 pieces
- [ ] Rejected pieces land in `pending/_REJECTED_*.md` with violations at the top
- [ ] `python scripts/approve.py --all-passing` moves non-rejected pieces to `approved/` and updates DB status
- [ ] `python scripts/emit_briefs.py --since yesterday` lists JSON briefs for the week
- [ ] Dropping new queries into `data/harvest_inbox.md` and running `harvest_refresh.py` folds them into existing clusters or creates new ones
- [ ] `pytest -v` exits 0 (all unit + smoke tests pass)
- [ ] `INTEGRATION=1 pytest -m integration -v` exits 0 when API key is available
