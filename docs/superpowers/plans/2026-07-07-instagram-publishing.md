# Twins Instagram Publishing: Draft + Preflight + Approval Queue (Plan 1 of 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generate the next three Instagram post drafts on the two-week slot rotation, validate each against the safeguard checklist, and write passing drafts (plus an internal source record) to a `pending/instagram/` review folder, holding failures with a clear reason.

**Architecture:** Extend the existing `twins-content-engine` Python package. Add typed Instagram config, a pure-function slot planner, a visual resolver implementing the proof fallback hierarchy, a preflight validator that reuses `engine/rules.py` primitives, a draft assembler that serializes to frontmatter markdown, and one orchestration script. Publishing to Instagram (Composio) and the monthly performance loop are separate follow-on plans and are out of scope here.

**Tech Stack:** Python 3.11, dataclasses, PyYAML, python-frontmatter, pytest + pytest-mock. All new code lives in `twins-content-engine/` (its own git repo).

**Scope boundary:** This plan does NOT call the Anthropic API, generate images, or call Composio. Captions are passed in as strings (the orchestration script sources them from the existing generator; unit tests use fixtures). Visual resolution returns a *plan* (real asset path or an AI-graphic spec), not pixels.

---

## File structure

- Create `twins-content-engine/config/instagram.yaml` — Instagram-specific config (offers, banned terms, formats, monthly mix, AI cap, cycle anchor).
- Modify `twins-content-engine/engine/config.py` — add `InstagramConfig` dataclass + `load_instagram_config()`.
- Create `twins-content-engine/engine/ig_planner.py` — slot rotation logic (`SlotSpec`, `plan_slot`).
- Create `twins-content-engine/engine/ig_visuals.py` — proof fallback hierarchy (`VisualPlan`, `resolve_visual`).
- Create `twins-content-engine/engine/ig_draft.py` — `SourceRecord`, `Draft`, `draft_to_markdown`.
- Create `twins-content-engine/engine/ig_rules.py` — `check_instagram_caption` returning `engine.rules.RuleReport`.
- Create `twins-content-engine/scripts/generate_ig_week.py` — orchestration.
- Create tests: `tests/test_ig_planner.py`, `tests/test_ig_visuals.py`, `tests/test_ig_draft.py`, `tests/test_ig_rules.py`.

All commands below run from `twins-content-engine/` with the venv active:
```bash
cd twins-content-engine && source .venv/bin/activate
```

---

### Task 1: Instagram config

**Files:**
- Create: `twins-content-engine/config/instagram.yaml`
- Modify: `twins-content-engine/engine/config.py`
- Test: `twins-content-engine/tests/test_ig_config.py`

- [ ] **Step 1: Write `config/instagram.yaml`**

```yaml
# Cycle anchor: a Monday that defines "Week 1" of the 2-week rotation.
cycle_anchor: "2026-07-06"
approved_offers:
  - "$0 service call"
  - "$49 tune-up"
  - "GoodLeap financing"
banned_corporate_terms:
  - specialist
  - journey
  - transformation
  - solutions
  - experience
banned_hashtags:
  - "#viral"
  - "#fyp"
  - "#explorepage"
approved_cta:
  - "Call or book through the link in our profile"
  - "Send us a photo of the spring and opener label"
  - "Tell us what the door is doing and what city you are in"
  - "Request a new-door estimate"
  - "Book the $49 tune-up"
  - "Ask about GoodLeap financing"
  - "Save this post before winter"
  - "Send this to someone whose garage door is stuck"
max_ai_fraction: 0.34
format_targets: [reel, carousel, static]
monthly_mix:
  real_job: 4
  educational: 3
  offer: 2
  pricing_comparison: 1
  company_local: 1
  recruiting: 1
```

- [ ] **Step 2: Write the failing test**

```python
# tests/test_ig_config.py
from pathlib import Path
from engine.config import load_instagram_config

CONFIG_DIR = Path(__file__).resolve().parents[1] / "config"


def test_load_instagram_config():
    cfg = load_instagram_config(CONFIG_DIR / "instagram.yaml")
    assert cfg.approved_offers == ["$0 service call", "$49 tune-up", "GoodLeap financing"]
    assert "specialist" in cfg.banned_corporate_terms
    assert cfg.max_ai_fraction == 0.34
    assert cfg.cycle_anchor == "2026-07-06"
    assert cfg.monthly_mix["real_job"] == 4
```

- [ ] **Step 3: Run test to verify it fails**

Run: `pytest tests/test_ig_config.py -v`
Expected: FAIL with `ImportError: cannot import name 'load_instagram_config'`

- [ ] **Step 4: Add to `engine/config.py`**

Append after the existing dataclasses:

```python
@dataclass
class InstagramConfig:
    cycle_anchor: str
    approved_offers: list[str]
    banned_corporate_terms: list[str]
    banned_hashtags: list[str]
    approved_cta: list[str]
    max_ai_fraction: float
    format_targets: list[str]
    monthly_mix: dict[str, int]


def load_instagram_config(path: "Path") -> InstagramConfig:
    with open(path) as f:
        raw = yaml.safe_load(f)
    return InstagramConfig(
        cycle_anchor=raw["cycle_anchor"],
        approved_offers=list(raw["approved_offers"]),
        banned_corporate_terms=list(raw["banned_corporate_terms"]),
        banned_hashtags=list(raw["banned_hashtags"]),
        approved_cta=list(raw["approved_cta"]),
        max_ai_fraction=float(raw["max_ai_fraction"]),
        format_targets=list(raw["format_targets"]),
        monthly_mix=dict(raw["monthly_mix"]),
    )
```

- [ ] **Step 5: Run test to verify it passes**

Run: `pytest tests/test_ig_config.py -v`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add config/instagram.yaml engine/config.py tests/test_ig_config.py
git commit -m "feat(ig): Instagram config loader"
```

---

### Task 2: Slot planner

**Files:**
- Create: `twins-content-engine/engine/ig_planner.py`
- Test: `twins-content-engine/tests/test_ig_planner.py`

The rotation: only Mon/Wed/Fri produce posts. Weeks alternate from `cycle_anchor` (an anchor Monday = Week 1). Even week-offsets are Week 1, odd are Week 2. Slot map: Mon=proof, Wed=value, Fri Week1=offer, Fri Week2=flex. Format target rotates reel/carousel/static across the three weekly posts (Mon=carousel, Wed=reel, Fri=static) as a *target*.

- [ ] **Step 1: Write the failing test**

```python
# tests/test_ig_planner.py
import datetime as dt
import pytest
from engine.ig_planner import plan_slot, SlotSpec

ANCHOR = "2026-07-06"  # a Monday


def test_week1_monday_is_proof_carousel():
    spec = plan_slot(dt.date(2026, 7, 6), ANCHOR, hiring=False)
    assert spec.slot == "proof"
    assert spec.week == 1
    assert spec.format_target == "carousel"


def test_week1_friday_is_offer():
    spec = plan_slot(dt.date(2026, 7, 10), ANCHOR, hiring=False)
    assert spec.slot == "offer"
    assert spec.week == 1


def test_week2_friday_is_flex_and_recruiting_gated_by_hiring():
    not_hiring = plan_slot(dt.date(2026, 7, 17), ANCHOR, hiring=False)
    hiring = plan_slot(dt.date(2026, 7, 17), ANCHOR, hiring=True)
    assert not_hiring.slot == "flex"
    assert not_hiring.week == 2
    assert not_hiring.allow_recruiting is False
    assert hiring.allow_recruiting is True


def test_non_posting_day_raises():
    with pytest.raises(ValueError):
        plan_slot(dt.date(2026, 7, 7), ANCHOR, hiring=False)  # Tuesday
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pytest tests/test_ig_planner.py -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'engine.ig_planner'`

- [ ] **Step 3: Write `engine/ig_planner.py`**

```python
"""Two-week Instagram slot rotation (Mon/Wed/Fri)."""
from __future__ import annotations

import datetime as dt
from dataclasses import dataclass

_WEEKDAY_SLOT = {0: "proof", 2: "value", 4: "friday"}   # Mon, Wed, Fri
_WEEKDAY_FORMAT = {0: "carousel", 2: "reel", 4: "static"}


@dataclass
class SlotSpec:
    date: str
    week: int              # 1 or 2 within the cycle
    slot: str              # proof | value | offer | flex
    format_target: str     # reel | carousel | static
    allow_recruiting: bool


def _cycle_week(target: dt.date, anchor: dt.date) -> int:
    days = (target - anchor).days
    week_index = (days // 7) % 2
    return 1 if week_index == 0 else 2


def plan_slot(target: dt.date, cycle_anchor: str, hiring: bool) -> SlotSpec:
    anchor = dt.date.fromisoformat(cycle_anchor)
    weekday = target.weekday()
    if weekday not in _WEEKDAY_SLOT:
        raise ValueError(f"{target} is not a posting day (Mon/Wed/Fri only)")
    week = _cycle_week(target, anchor)
    base = _WEEKDAY_SLOT[weekday]
    if base == "friday":
        slot = "offer" if week == 1 else "flex"
    else:
        slot = base
    return SlotSpec(
        date=target.isoformat(),
        week=week,
        slot=slot,
        format_target=_WEEKDAY_FORMAT[weekday],
        allow_recruiting=(slot == "flex" and hiring),
    )
```

- [ ] **Step 4: Run test to verify it passes**

Run: `pytest tests/test_ig_planner.py -v`
Expected: PASS (4 passed)

- [ ] **Step 5: Commit**

```bash
git add engine/ig_planner.py tests/test_ig_planner.py
git commit -m "feat(ig): two-week slot planner"
```

---

### Task 3: Visual resolver (proof fallback hierarchy + AI cap)

**Files:**
- Create: `twins-content-engine/engine/ig_visuals.py`
- Test: `twins-content-engine/tests/test_ig_visuals.py`

The resolver is pure: the caller passes the ordered real assets it found and the running AI usage this month. Fallback order for a proof slot: 1 completed job, 2 before-and-after, 3 verified review, 4 truck/tools/parts/WIP, 5 educational AI graphic. A proof slot NEVER produces a fake AI job; if it must fall to AI it is explicitly an educational graphic. The AI cap holds AI posts at `<= max_ai_fraction` of the month; exceeding it flags the draft for approval rather than silently publishing.

- [ ] **Step 1: Write the failing test**

```python
# tests/test_ig_visuals.py
from engine.ig_visuals import resolve_visual, VisualPlan, RealAsset


def test_prefers_completed_job_when_available():
    assets = [
        RealAsset(kind="completed_job", path="/jobs/verona/done.jpg"),
        RealAsset(kind="truck", path="/jobs/verona/truck.jpg"),
    ]
    plan = resolve_visual("proof", assets, ai_used=0, total_posts=0, max_ai_fraction=0.34)
    assert plan.kind == "real"
    assert plan.asset_path == "/jobs/verona/done.jpg"
    assert plan.fallback_level == 1


def test_proof_falls_to_educational_ai_not_fake_job():
    plan = resolve_visual("proof", [], ai_used=0, total_posts=0, max_ai_fraction=0.34)
    assert plan.kind == "ai"
    assert plan.ai_spec == "educational_graphic"
    assert plan.fallback_level == 5
    assert "fake" not in plan.ai_spec


def test_ai_cap_flags_when_exceeded():
    # 3 of 9 posts already AI, adding another crosses 0.34
    plan = resolve_visual("value", [], ai_used=3, total_posts=9, max_ai_fraction=0.34)
    assert plan.kind == "ai"
    assert plan.needs_approval is True
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pytest tests/test_ig_visuals.py -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'engine.ig_visuals'`

- [ ] **Step 3: Write `engine/ig_visuals.py`**

```python
"""Visual resolution with the proof fallback hierarchy and AI cap."""
from __future__ import annotations

from dataclasses import dataclass
from typing import Optional

# Ordered proof fallback: real kinds by priority, then educational AI.
_PROOF_ORDER = ["completed_job", "before_after", "verified_review", "truck", "tools", "parts", "wip"]
_LEVEL = {"completed_job": 1, "before_after": 2, "verified_review": 3,
          "truck": 4, "tools": 4, "parts": 4, "wip": 4}


@dataclass
class RealAsset:
    kind: str      # completed_job | before_after | verified_review | truck | tools | parts | wip
    path: str


@dataclass
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `pytest tests/test_ig_visuals.py -v`
Expected: PASS (3 passed)

- [ ] **Step 5: Commit**

```bash
git add engine/ig_visuals.py tests/test_ig_visuals.py
git commit -m "feat(ig): visual resolver with proof fallback + AI cap"
```

---

### Task 4: Preflight validator

**Files:**
- Create: `twins-content-engine/engine/ig_rules.py`
- Test: `twins-content-engine/tests/test_ig_rules.py`

Reuse `engine.rules.RuleReport` and `Violation`. Rules: emojis, banned corporate terms, Kentucky/Lexington, banned hashtags, and dollar-offer strings not in the approved list. Each violation is severity `error` (holds the draft). A dollar amount that is not an approved offer becomes an error naming the string, so the human confirms wording. Note: same-day service is a PERMITTED claim (owner-confirmed) and is NOT blocked.

- [ ] **Step 1: Write the failing test**

```python
# tests/test_ig_rules.py
from engine.config import load_instagram_config
from engine.ig_rules import check_instagram_caption
from pathlib import Path

CFG = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")


def _ids(report):
    return {v.rule_id for v in report.violations}


def test_clean_caption_passes():
    text = "Broken spring repair in Verona. Send us a photo of the spring and opener label."
    report = check_instagram_caption(text, CFG)
    assert report.passed, report.violations


def test_same_day_is_allowed():
    report = check_instagram_caption("We offer same-day garage door service.", CFG)
    assert report.passed, report.violations


def test_emoji_is_blocked():
    report = check_instagram_caption("New opener installed \U0001F600", CFG)
    assert "ig:emoji" in _ids(report)


def test_kentucky_is_blocked():
    report = check_instagram_caption("Serving Madison and Lexington KY.", CFG)
    assert "ig:out_of_state" in _ids(report)


def test_corporate_term_is_blocked():
    report = check_instagram_caption("Our specialists deliver solutions.", CFG)
    assert "ig:corporate_term" in _ids(report)


def test_unapproved_dollar_offer_is_blocked():
    report = check_instagram_caption("Get $25 off any repair today.", CFG)
    assert "ig:unapproved_offer" in _ids(report)


def test_approved_offer_passes():
    report = check_instagram_caption("Book the $49 tune-up before winter.", CFG)
    assert "ig:unapproved_offer" not in _ids(report)
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pytest tests/test_ig_rules.py -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'engine.ig_rules'`

- [ ] **Step 3: Write `engine/ig_rules.py`**

```python
"""Instagram-specific preflight validation. Reuses engine.rules primitives."""
from __future__ import annotations

import re

from engine.config import InstagramConfig
from engine.rules import RuleReport, Violation

_EMOJI_RE = re.compile(
    "[" "\U0001F300-\U0001FAFF" "\U00002600-\U000027BF" "\U0001F1E6-\U0001F1FF" "]",
    flags=re.UNICODE,
)
_OUT_OF_STATE_RE = re.compile(r"\b(kentucky|lexington|ky)\b", re.IGNORECASE)
_DOLLAR_RE = re.compile(r"\$\d[\d,]*")
# Note: same-day service is a permitted, owner-confirmed claim, so it is NOT blocked.


def check_instagram_caption(text: str, cfg: InstagramConfig) -> RuleReport:
    violations: list[Violation] = []

    if _EMOJI_RE.search(text):
        violations.append(Violation("ig:emoji", "error", "Emojis are not allowed."))

    if _OUT_OF_STATE_RE.search(text):
        violations.append(Violation("ig:out_of_state", "error", "Kentucky/Lexington reference not allowed."))

    lowered = text.lower()
    for term in cfg.banned_corporate_terms:
        if re.search(rf"\b{re.escape(term)}\b", lowered):
            violations.append(Violation("ig:corporate_term", "error", f"Corporate term not allowed: {term!r}"))

    for tag in cfg.banned_hashtags:
        if tag.lower() in lowered:
            violations.append(Violation("ig:banned_hashtag", "error", f"Banned hashtag: {tag}"))

    # Dollar strings must belong to an approved offer.
    approved_dollar = " | ".join(cfg.approved_offers).lower()
    for m in _DOLLAR_RE.finditer(text):
        dollar = m.group(0)
        if dollar.lower() not in approved_dollar:
            violations.append(Violation("ig:unapproved_offer", "error",
                                        f"Dollar amount {dollar} is not an approved offer."))

    return RuleReport(passed=len(violations) == 0, violations=violations)
```

- [ ] **Step 4: Run test to verify it passes**

Run: `pytest tests/test_ig_rules.py -v`
Expected: PASS (7 passed)

- [ ] **Step 5: Commit**

```bash
git add engine/ig_rules.py tests/test_ig_rules.py
git commit -m "feat(ig): preflight validator for captions"
```

---

### Task 5: Draft assembler + source record

**Files:**
- Create: `twins-content-engine/engine/ig_draft.py`
- Test: `twins-content-engine/tests/test_ig_draft.py`

- [ ] **Step 1: Write the failing test**

```python
# tests/test_ig_draft.py
import frontmatter
from engine.ig_draft import Draft, SourceRecord, draft_to_markdown
from engine.ig_planner import SlotSpec
from engine.ig_visuals import VisualPlan


def _draft():
    slot = SlotSpec(date="2026-07-06", week=1, slot="proof",
                    format_target="carousel", allow_recruiting=False)
    visual = VisualPlan(kind="real", asset_path="/jobs/verona/done.jpg",
                        ai_spec=None, fallback_level=1)
    source = SourceRecord(asset_source="real_photo", job_folder="/jobs/verona",
                          review_verified=None, confirmed_city="Verona",
                          offer_used=None, needs_approval=["confirm city spelling"])
    return Draft(slot=slot, caption="Broken spring repair in Verona.",
                 cta="Send us a photo of the spring and opener label.",
                 city="Verona", hashtags=["#VeronaWI", "#GarageDoorRepair"],
                 visual=visual, source=source)


def test_draft_serializes_with_source_record():
    md = draft_to_markdown(_draft())
    post = frontmatter.loads(md)
    assert post["slot"] == "proof"
    assert post["city"] == "Verona"
    assert post["source"]["asset_source"] == "real_photo"
    assert post["source"]["needs_approval"] == ["confirm city spelling"]
    assert "Broken spring repair in Verona." in post.content
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pytest tests/test_ig_draft.py -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'engine.ig_draft'`

- [ ] **Step 3: Write `engine/ig_draft.py`**

```python
"""Instagram draft object and markdown serialization."""
from __future__ import annotations

from dataclasses import dataclass, field, asdict
from typing import Optional

import frontmatter

from engine.ig_planner import SlotSpec
from engine.ig_visuals import VisualPlan


@dataclass
class SourceRecord:
    asset_source: str                       # real_photo | real_video | verified_review | ai_graphic
    job_folder: Optional[str]
    review_verified: Optional[bool]
    confirmed_city: Optional[str]
    offer_used: Optional[str]
    needs_approval: list[str] = field(default_factory=list)


@dataclass
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `pytest tests/test_ig_draft.py -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add engine/ig_draft.py tests/test_ig_draft.py
git commit -m "feat(ig): draft object + markdown serialization with source record"
```

---

### Task 6: Orchestration script

**Files:**
- Create: `twins-content-engine/scripts/generate_ig_week.py`
- Test: `twins-content-engine/tests/test_ig_generate_week.py`

The script builds drafts for a given week's Mon/Wed/Fri, runs preflight, writes passing drafts to `pending/instagram/` and held drafts to `pending/instagram/held/` with a `_held_reason` field. Captions are provided by a `caption_fn(slot) -> str` injected for testability (the CLI entry point wires the real generator). Real assets are discovered by a `assets_fn(slot) -> list[RealAsset]` (CLI scans a job folder; tests inject).

- [ ] **Step 1: Write the failing test**

```python
# tests/test_ig_generate_week.py
import datetime as dt
from pathlib import Path
from engine.config import load_instagram_config
from engine.ig_visuals import RealAsset
from scripts.generate_ig_week import generate_week

CFG = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")


def test_generates_three_drafts_split_pass_and_held(tmp_path):
    def caption_fn(slot):
        if slot.slot == "offer":
            return "Get $25 off any repair."  # will be held: $25 not an approved offer
        return f"Recent repair in Verona. {slot.slot}."

    def assets_fn(slot):
        return [RealAsset(kind="completed_job", path="/jobs/verona/done.jpg")]

    written = generate_week(
        monday=dt.date(2026, 7, 6), cfg=CFG, out_dir=tmp_path,
        hiring=False, caption_fn=caption_fn, assets_fn=assets_fn,
        month_ai_used=0, month_total_posts=0,
    )
    passed = list((tmp_path).glob("*.md"))
    held = list((tmp_path / "held").glob("*.md"))
    assert len(passed) == 2      # proof + value
    assert len(held) == 1        # offer caption held: $25 not an approved offer
    assert written["held"][0].endswith(".md")
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pytest tests/test_ig_generate_week.py -v`
Expected: FAIL with `ModuleNotFoundError: No module named 'scripts.generate_ig_week'`

- [ ] **Step 3: Write `scripts/generate_ig_week.py`**

```python
"""Generate one week of Instagram drafts (Mon/Wed/Fri) with preflight gating."""
from __future__ import annotations

import argparse
import datetime as dt
from pathlib import Path
from typing import Callable

import frontmatter

from engine.config import InstagramConfig, load_instagram_config, load_service_area  # noqa: F401
from engine.ig_planner import SlotSpec, plan_slot
from engine.ig_visuals import RealAsset, resolve_visual
from engine.ig_rules import check_instagram_caption
from engine.ig_draft import Draft, SourceRecord, draft_to_markdown

CaptionFn = Callable[[SlotSpec], str]
AssetsFn = Callable[[SlotSpec], list[RealAsset]]


def generate_week(monday: dt.date, cfg: InstagramConfig, out_dir: Path, hiring: bool,
                  caption_fn: CaptionFn, assets_fn: AssetsFn,
                  month_ai_used: int, month_total_posts: int) -> dict:
    out_dir.mkdir(parents=True, exist_ok=True)
    held_dir = out_dir / "held"
    held_dir.mkdir(parents=True, exist_ok=True)

    written = {"passed": [], "held": []}
    ai_used = month_ai_used
    total = month_total_posts

    for offset in (0, 2, 4):  # Mon, Wed, Fri
        day = monday + dt.timedelta(days=offset)
        slot = plan_slot(day, cfg.cycle_anchor, hiring=hiring)
        caption = caption_fn(slot)
        visual = resolve_visual(slot.slot, assets_fn(slot), ai_used, total, cfg.max_ai_fraction)
        if visual.kind == "ai":
            ai_used += 1
        total += 1

        source = SourceRecord(
            asset_source="real_photo" if visual.kind == "real" else "ai_graphic",
            job_folder=None, review_verified=None, confirmed_city=None,
            offer_used=None,
            needs_approval=(["AI fraction cap exceeded"] if visual.needs_approval else []),
        )
        draft = Draft(slot=slot, caption=caption, cta="", city=None,
                      hashtags=[], visual=visual, source=source)

        report = check_instagram_caption(caption, cfg)
        md = draft_to_markdown(draft)
        fname = f"{slot.date}_{slot.slot}.md"
        if report.passed:
            (out_dir / fname).write_text(md)
            written["passed"].append(str(out_dir / fname))
        else:
            post = frontmatter.loads(md)
            post["_held_reason"] = [v.message for v in report.violations]
            (held_dir / fname).write_text(frontmatter.dumps(post))
            written["held"].append(str(held_dir / fname))

    return written


def _real_caption_fn(slot: SlotSpec) -> str:
    # Wire the existing content-engine generator here in a follow-up; placeholder
    # captions are never published (preflight + human approval gate downstream).
    raise NotImplementedError("Wire engine.generator in the publishing plan (Plan 2).")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--monday", required=True, help="ISO date of the week's Monday")
    parser.add_argument("--out", default="pending/instagram")
    parser.add_argument("--hiring", action="store_true")
    args = parser.parse_args()
    cfg = load_instagram_config(Path("config/instagram.yaml"))
    result = generate_week(
        monday=dt.date.fromisoformat(args.monday), cfg=cfg, out_dir=Path(args.out),
        hiring=args.hiring, caption_fn=_real_caption_fn, assets_fn=lambda s: [],
        month_ai_used=0, month_total_posts=0,
    )
    print(f"passed: {len(result['passed'])}  held: {len(result['held'])}")


if __name__ == "__main__":
    main()
```

- [ ] **Step 4: Run test to verify it passes**

Run: `pytest tests/test_ig_generate_week.py -v`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `pytest -q`
Expected: all tests pass (existing + new).

- [ ] **Step 6: Commit**

```bash
git add scripts/generate_ig_week.py tests/test_ig_generate_week.py
git commit -m "feat(ig): weekly draft orchestration with preflight gating"
```

---

## Self-review notes

- **Spec coverage:** posting structure + rotation (Task 2), monthly-mix config (Task 1, consumed by Plan 2 scheduler), proof fallback hierarchy + AI cap (Task 3), safeguard checklist (Task 4), draft source record (Task 5), approval queue via `pending/instagram/` + `held/` (Task 6). Publishing workflow steps 4 (Composio publish) and the monthly performance loop are explicitly deferred to Plans 2 and 3.
- **Deferred to Plan 2 (publishing):** wiring `engine.generator` for real captions, scanning a real job drop-folder into `RealAsset`s, generating AI graphics via the media generator, and Composio scheduled publishing on Central time. `_real_caption_fn` and the CLI `assets_fn` are stubbed here on purpose.
- **AI-cap persistence (Plan 2 prerequisite):** `generate_week` threads `ai_used`/`total` correctly when passed in, but `main()` hardcodes both to 0, so each weekly CLI run restarts the "monthly" AI fraction. Harmless in Plan 1 (the CLI caption stub raises before any run completes) but Plan 2 MUST persist month-window counts (e.g. a small `data/ig_month_state.json` keyed by YYYY-MM) and feed them in, or the ≤1/3 AI cap is per-week in name only.
- **Forward-looking config, unused in Plan 1:** `approved_cta` and `format_targets` (like `monthly_mix`) are loaded by `InstagramConfig` but not yet consumed — `Draft.cta` is assembled empty and the planner uses its own weekday→format map. CTA whitelist enforcement and format targeting are Plan 2 work; do not assume they are enforced today.
- **Deferred to Plan 3 (analytics):** the monthly performance loop (Composio insights + jwrpj attribution).
- **Open items from the spec** (public phone number, booking link, hiring status, offer fine print) must be resolved before Plan 2 publishes anything live. Same-day service is owner-confirmed as permitted, so it is NOT blocked and `brand.yaml` needs no change.
- **Preflight coverage gap (Plan 2 prerequisite — do NOT over-trust "passed"):** the Task 4 validator machine-enforces 5 rules only — emoji, out-of-state (KY/Lexington literal), corporate terms, banned hashtags, and unapproved dollar *amounts*. The spec's safeguard checklist additionally names rules that are NOT yet machine-enforced and today rely on the mandatory human approval gate: (a) **city/area whitelist** — a non-WI city that is not "Kentucky/Lexington" (e.g. "Rockford IL") currently PASSES preflight; (b) **private-info patterns** — street addresses, house numbers, license plates are not detected; (c) **commercial-content** detection; (d) **offer-phrase** matching — the dollar check matches by numeric value, so an unapproved offer reusing an approved number ("$49 off", "$0 down") passes; (e) **invented job/customer/review details** (inherently human-judgment, covered by the missing-facts rule + source record). Before Plan 2 automates publishing, add machine checks for (a) using the service-area town list and (b) private-info regexes, and tighten (d) toward offer-phrase matching. "Preflight passed" in Plan 1 means "5 rules passed," not "the full checklist passed."
- **Source-record fidelity (Plan 2):** `generate_ig_week` currently collapses every real asset kind to `asset_source="real_photo"` and does not propagate the real kind/path into `job_folder`/`review_verified`. Plan 2's approval UX should carry the true asset kind (real photo vs real video vs verified review) into the source record.
```
