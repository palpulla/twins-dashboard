# Twins Instagram Publishing: Captions, Assets, Approval + Composio Publish (Plan 2 of 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the Plan 1 pipeline real end-to-end: month-state persistence for the AI cap, the remaining machine safeguards (out-of-area, private info, CTA whitelist, offer phrases), Claude-generated captions, a job-photo drop folder, an approval CLI, and a Composio publisher that is dry-run by default and only ever publishes human-approved drafts.

**Architecture:** Continue extending `twins-content-engine`. New pure modules (`ig_state`, `ig_captions`, `ig_assets`) + additions to `ig_rules` and `config`, plus two CLIs (`approve_ig.py`, `publish_ig.py`) and a real `main()` in `generate_ig_week.py`. Composio is reached through a thin subprocess wrapper around the `composio` CLI (already authenticated on this machine; Instagram connected account alias `twinsgd`, account `twins.garage.doors`). Tool slugs live in config and are verified by a discovery step at execution time — never guessed in code.

**Tech Stack:** Python 3.11, dataclasses, PyYAML, python-frontmatter, pytest + pytest-mock. Anthropic API via the existing `engine/claude_client.py`. Composio CLI at `~/.composio/composio`.

**Safety invariants (unchanged from spec):** nothing publishes without a human-approved draft file in `approved/instagram/`; `publish_ig.py` defaults to dry-run and requires `--live`; preflight re-runs at publish time (defense in depth); WI-only; approved offers only; same-day allowed.

---

## File structure

- Modify `twins-content-engine/config/instagram.yaml` — add topics-per-slot, hashtag map, banned place terms, commercial terms, approved offer phrases, publish tool slugs, post time.
- Modify `twins-content-engine/engine/config.py` — extend `InstagramConfig`.
- Create `twins-content-engine/engine/ig_state.py` — month-window AI-cap persistence.
- Modify `twins-content-engine/engine/ig_rules.py` — 4 new machine checks.
- Create `twins-content-engine/engine/ig_captions.py` — topic picker + caption generation via claude_client.
- Create `twins-content-engine/engine/ig_assets.py` — drop-folder scanner → `RealAsset`s.
- Create `twins-content-engine/scripts/approve_ig.py` — list/approve/reject pending drafts.
- Create `twins-content-engine/engine/composio_client.py` — subprocess wrapper for `composio execute`.
- Create `twins-content-engine/scripts/publish_ig.py` — publish due approved drafts (dry-run default).
- Modify `twins-content-engine/scripts/generate_ig_week.py` — real `main()` wiring.
- Tests: `tests/test_ig_state.py`, `tests/test_ig_rules_plan2.py`, `tests/test_ig_captions.py`, `tests/test_ig_assets.py`, `tests/test_ig_approve.py`, `tests/test_ig_publish.py`.

All commands run from `twins-content-engine/` with the venv active:
```bash
cd twins-content-engine && source .venv/bin/activate
```

---

### Task 1: Config additions

**Files:**
- Modify: `twins-content-engine/config/instagram.yaml`
- Modify: `twins-content-engine/engine/config.py` (extend `InstagramConfig` + loader)
- Modify: `twins-content-engine/tests/test_ig_config.py`

- [ ] **Step 1: Append to `config/instagram.yaml`**

```yaml
post_time_local: "12:00"          # America/Chicago
timezone: "America/Chicago"
# Composio tool slugs — VERIFIED at execution time via `composio search`, then
# corrected here if they differ. Publisher refuses to run --live if unverified.
publish_tools:
  verified: false
  image_post: "INSTAGRAM_CREATE_POST"
banned_place_terms:               # non-WI markets that must never appear
  - kentucky
  - lexington
  - illinois
  - chicago
  - rockford
  - minnesota
  - iowa
commercial_terms:                 # commercial (non-residential) content markers
  - commercial garage door
  - loading dock
  - roll-up door
  - warehouse door
  - overhead coiling
approved_offer_phrases:           # exact allowed phrasings around approved dollar amounts
  - "$0 service call"
  - "$49 tune-up"
  - "$49 tune up"
hashtags_base: ["#GarageDoorRepair", "#MadisonWI", "#DaneCountyWI"]
city_hashtags:
  Madison: "#MadisonWI"
  Middleton: "#MiddletonWI"
  Verona: "#VeronaWI"
  Fitchburg: "#FitchburgWI"
  "Sun Prairie": "#SunPrairieWI"
  Waunakee: "#WaunakeeWI"
  DeForest: "#DeForestWI"
  "Cottage Grove": "#CottageGroveWI"
  McFarland: "#McFarlandWI"
  Monona: "#MononaWI"
  Oregon: "#OregonWI"
  Stoughton: "#StoughtonWI"
  "Mount Horeb": "#MountHorebWI"
  "Cross Plains": "#CrossPlainsWI"
slot_topics:
  proof:
    - "Broken spring repair"
    - "New garage door installation"
    - "Off-track door repair"
    - "Opener replacement"
  value:
    - "Door opens a few inches and stops - what it usually means"
    - "A loud bang from the garage - what just broke"
    - "The door suddenly feels heavy - why that is unsafe"
    - "Opener runs but the door does not move"
    - "The door reverses for no clear reason"
    - "Repair versus replacement - how to decide"
    - "Should both springs be replaced at once"
    - "When should an opener be replaced"
    - "What the $49 tune-up includes"
    - "What affects the cost of a new garage door"
  offer:
    - "$0 service call"
    - "$49 tune-up"
    - "GoodLeap financing"
  flex:
    - "Recent garage door jobs across Madison, Middleton, Verona, and Fitchburg"
    - "What a normal day looks like for a Twins tech"
    - "The trucks and tools we bring to every job"
slot_cta:                          # one main CTA per slot type (from approved_cta)
  proof: "Tell us what the door is doing and what city you are in"
  value: "Book the $49 tune-up"
  offer: "Call or book through the link in our profile"
  flex: "Call or book through the link in our profile"
```

- [ ] **Step 2: Extend the test** — append to `tests/test_ig_config.py`:

```python
def test_plan2_config_fields():
    cfg = load_instagram_config(CONFIG_DIR / "instagram.yaml")
    assert cfg.timezone == "America/Chicago"
    assert cfg.publish_tools["verified"] is False
    assert "illinois" in cfg.banned_place_terms
    assert "commercial garage door" in cfg.commercial_terms
    assert "$49 tune-up" in cfg.approved_offer_phrases
    assert cfg.city_hashtags["Verona"] == "#VeronaWI"
    assert "proof" in cfg.slot_topics and len(cfg.slot_topics["value"]) >= 5
    assert cfg.slot_cta["offer"] == "Call or book through the link in our profile"
```

- [ ] **Step 3: Run to verify it fails** — `pytest tests/test_ig_config.py -v` → FAIL (unknown attribute).

- [ ] **Step 4: Extend `InstagramConfig`** in `engine/config.py` — add fields (all with the same list/dict copying style as the existing loader):

```python
    post_time_local: str = "12:00"
    timezone: str = "America/Chicago"
    publish_tools: dict[str, Any] = field(default_factory=dict)
    banned_place_terms: list[str] = field(default_factory=list)
    commercial_terms: list[str] = field(default_factory=list)
    approved_offer_phrases: list[str] = field(default_factory=list)
    hashtags_base: list[str] = field(default_factory=list)
    city_hashtags: dict[str, str] = field(default_factory=dict)
    slot_topics: dict[str, list[str]] = field(default_factory=dict)
    slot_cta: dict[str, str] = field(default_factory=dict)
```

and populate them in `load_instagram_config` with `raw.get(...)` defaults so Plan 1 tests stay green.

- [ ] **Step 5: Run** `pytest tests/test_ig_config.py -v` → PASS; `pytest -q` → no regressions.

- [ ] **Step 6: Commit** — `git add config/instagram.yaml engine/config.py tests/test_ig_config.py && git commit -m "feat(ig): plan-2 config (topics, hashtags, safeguards, publish tools)"`

---

### Task 2: Month-state persistence (AI cap)

**Files:**
- Create: `twins-content-engine/engine/ig_state.py`
- Test: `twins-content-engine/tests/test_ig_state.py`

- [ ] **Step 1: Failing test**

```python
# tests/test_ig_state.py
import datetime as dt
from engine.ig_state import load_month_state, save_month_state


def test_state_roundtrip_and_month_rollover(tmp_path):
    p = tmp_path / "ig_month_state.json"
    s = load_month_state(p, dt.date(2026, 7, 6))
    assert s == {"month": "2026-07", "ai_used": 0, "total": 0}
    save_month_state(p, {"month": "2026-07", "ai_used": 2, "total": 5})
    s2 = load_month_state(p, dt.date(2026, 7, 20))
    assert s2["ai_used"] == 2 and s2["total"] == 5
    # new month resets
    s3 = load_month_state(p, dt.date(2026, 8, 3))
    assert s3 == {"month": "2026-08", "ai_used": 0, "total": 0}
```

- [ ] **Step 2: Verify FAIL** — `pytest tests/test_ig_state.py -v` → ModuleNotFoundError.

- [ ] **Step 3: Implement `engine/ig_state.py`**

```python
"""Month-window persistence for the Instagram AI-fraction cap."""
from __future__ import annotations

import datetime as dt
import json
from pathlib import Path


def _month_key(day: dt.date) -> str:
    return day.strftime("%Y-%m")


def load_month_state(path: Path, today: dt.date) -> dict:
    key = _month_key(today)
    if path.exists():
        state = json.loads(path.read_text())
        if state.get("month") == key:
            return state
    return {"month": key, "ai_used": 0, "total": 0}


def save_month_state(path: Path, state: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(state, indent=2))
```

- [ ] **Step 4: Verify PASS**, then **Step 5: Commit** — `git add engine/ig_state.py tests/test_ig_state.py && git commit -m "feat(ig): month-state persistence for AI cap"`

---

### Task 3: Remaining machine safeguards

**Files:**
- Modify: `twins-content-engine/engine/ig_rules.py`
- Create: `twins-content-engine/tests/test_ig_rules_plan2.py`

New rules (all severity error), closing the Plan 1 coverage gap: `ig:out_of_area` (banned place terms, word-boundary), `ig:private_info` (street-address pattern), `ig:commercial` (commercial terms), `ig:unapproved_offer_phrase` (an approved dollar VALUE used in a non-approved phrasing, e.g. "$49 off"), and a draft-level `check_instagram_draft(caption, cta, cfg)` that additionally enforces the CTA whitelist (`ig:cta`).

- [ ] **Step 1: Failing tests**

```python
# tests/test_ig_rules_plan2.py
from pathlib import Path
from engine.config import load_instagram_config
from engine.ig_rules import check_instagram_caption, check_instagram_draft

CFG = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")


def _ids(report):
    return {v.rule_id for v in report.violations}


def test_out_of_area_city_blocked():
    assert "ig:out_of_area" in _ids(check_instagram_caption("Now serving Rockford and Chicago.", CFG))


def test_wisconsin_cities_pass():
    r = check_instagram_caption("Broken spring repair in Sun Prairie today.", CFG)
    assert r.passed, r.violations


def test_street_address_blocked():
    assert "ig:private_info" in _ids(check_instagram_caption("Job done at 4128 Maple Ave for a happy customer.", CFG))


def test_commercial_content_blocked():
    assert "ig:commercial" in _ids(check_instagram_caption("We repair commercial garage door systems and loading dock equipment.", CFG))


def test_offer_value_in_wrong_phrase_blocked():
    # $49 is an approved VALUE but "$49 off" is not an approved PHRASE
    assert "ig:unapproved_offer_phrase" in _ids(check_instagram_caption("This week only: $49 off any repair.", CFG))


def test_offer_value_in_approved_phrase_passes():
    r = check_instagram_caption("Book the $49 tune-up before winter.", CFG)
    assert r.passed, r.violations


def test_draft_check_enforces_cta_whitelist():
    bad = check_instagram_draft("Broken spring repair in Verona.", "DM us on WhatsApp!!", CFG)
    good = check_instagram_draft("Broken spring repair in Verona.", CFG.slot_cta["proof"], CFG)
    assert "ig:cta" in _ids(bad)
    assert good.passed, good.violations
```

- [ ] **Step 2: Verify FAIL** — `pytest tests/test_ig_rules_plan2.py -v` → ImportError on `check_instagram_draft`.

- [ ] **Step 3: Implement in `engine/ig_rules.py`** — add after the existing checks inside `check_instagram_caption` (reusing `lowered`):

```python
    for place in cfg.banned_place_terms:
        if re.search(rf"\b{re.escape(place)}\b", lowered):
            violations.append(Violation("ig:out_of_area", "error",
                                        f"Out-of-area reference not allowed: {place!r}"))

    if _ADDRESS_RE.search(text):
        violations.append(Violation("ig:private_info", "error",
                                    "Looks like a street address (customer privacy)."))

    for term in cfg.commercial_terms:
        if term.lower() in lowered:
            violations.append(Violation("ig:commercial", "error",
                                        f"Commercial content not allowed: {term!r}"))
```

module-level pattern (broad street-suffix match; approval gate is the backstop for false positives):

```python
_ADDRESS_RE = re.compile(
    r"\b\d{1,5}\s+(?:[A-Z][a-z]+\s){0,2}"
    r"(?:St|Street|Ave|Avenue|Rd|Road|Dr|Drive|Ln|Lane|Blvd|Ct|Court|Way|Trail|Tr)\b\.?",
)
```

offer-phrase tightening — replace the tail of the dollar loop so approved VALUES also require an approved PHRASE around them:

```python
    for token, value in _dollar_values(text):
        if value not in approved_values:
            violations.append(Violation("ig:unapproved_offer", "error",
                                        f"Dollar amount {token} is not an approved offer."))
            continue
        idx = lowered.find(token.lower())
        window = lowered[max(0, idx - 20): idx + len(token) + 25]
        if not any(p.lower() in window for p in cfg.approved_offer_phrases if token.lower() in p.lower()):
            violations.append(Violation("ig:unapproved_offer_phrase", "error",
                                        f"{token} used outside an approved offer phrasing."))
```

and the draft-level wrapper:

```python
def check_instagram_draft(caption: str, cta: str, cfg: InstagramConfig) -> RuleReport:
    report = check_instagram_caption(caption, cfg)
    if cta not in cfg.approved_cta:
        report.violations.append(Violation("ig:cta", "error", f"CTA not in approved list: {cta!r}"))
    return RuleReport(passed=len(report.violations) == 0, violations=report.violations)
```

- [ ] **Step 4: Verify PASS** — `pytest tests/test_ig_rules_plan2.py tests/test_ig_rules.py -v` (all Plan 1 rule tests must stay green), then `pytest -q`.

- [ ] **Step 5: Commit** — `git commit -m "feat(ig): out-of-area, private-info, commercial, offer-phrase + CTA safeguards"` (add the two files).

---

### Task 4: Topic picker + caption generation

**Files:**
- Create: `twins-content-engine/engine/ig_captions.py`
- Test: `twins-content-engine/tests/test_ig_captions.py`

Deterministic topic rotation (ISO-week index modulo list length — no randomness), prompt assembled from brand voice + slot + topic + optional city, generation through the existing `engine.claude_client` (mocked in tests). The generated caption gets the slot CTA appended as its own final line.

- [ ] **Step 1: Failing tests**

```python
# tests/test_ig_captions.py
import datetime as dt
from pathlib import Path
from engine.config import load_instagram_config, load_brand
from engine.ig_captions import pick_topic, build_prompt, generate_caption
from engine.ig_planner import plan_slot

CONFIG = Path(__file__).resolve().parents[1] / "config"
CFG = load_instagram_config(CONFIG / "instagram.yaml")
BRAND = load_brand(CONFIG / "brand.yaml")
ANCHOR = "2026-07-06"


def test_pick_topic_is_deterministic_and_rotates():
    slot = plan_slot(dt.date(2026, 7, 8), ANCHOR, hiring=False)   # value slot
    t1 = pick_topic(slot, CFG)
    t2 = pick_topic(slot, CFG)
    assert t1 == t2 and t1 in CFG.slot_topics["value"]
    later = plan_slot(dt.date(2026, 7, 15), ANCHOR, hiring=False)
    assert pick_topic(later, CFG) != t1   # next week rotates


def test_build_prompt_carries_voice_and_topic():
    slot = plan_slot(dt.date(2026, 7, 6), ANCHOR, hiring=False)
    prompt = build_prompt(slot, "Broken spring repair", "Verona", CFG, BRAND)
    assert "Broken spring repair" in prompt
    assert "Verona" in prompt
    assert "no emojis" in prompt.lower()


def test_generate_caption_appends_cta(mocker):
    slot = plan_slot(dt.date(2026, 7, 6), ANCHOR, hiring=False)
    mocker.patch("engine.ig_captions.complete", return_value="Broken spring repair in Verona. Done the same day.")
    caption = generate_caption(slot, "Broken spring repair", "Verona", CFG, BRAND)
    assert caption.endswith(CFG.slot_cta["proof"])
    assert "Broken spring repair in Verona." in caption
```

- [ ] **Step 2: Verify FAIL**, read `engine/claude_client.py` first to learn its real completion entry point, and adapt the import in `ig_captions.py` (the test patches `engine.ig_captions.complete`, so alias whatever the client exposes as `complete`).

- [ ] **Step 3: Implement `engine/ig_captions.py`**

```python
"""Instagram caption generation: deterministic topic rotation + Claude call."""
from __future__ import annotations

from engine.claude_client import complete   # alias/adapt to the client's real fn
from engine.config import BrandConfig, InstagramConfig
from engine.ig_planner import SlotSpec
import datetime as dt


def pick_topic(slot: SlotSpec, cfg: InstagramConfig) -> str:
    topics = cfg.slot_topics[slot.slot]
    week_index = dt.date.fromisoformat(slot.date).isocalendar().week
    return topics[week_index % len(topics)]


def build_prompt(slot: SlotSpec, topic: str, city: str | None,
                 cfg: InstagramConfig, brand: BrandConfig) -> str:
    place = f" in {city}" if city else ""
    avoid = ", ".join(cfg.banned_corporate_terms)
    return (
        f"Write a short Instagram caption for {brand.business_name}, a residential "
        f"garage door company serving Madison and Dane County, Wisconsin.\n"
        f"Post type: {slot.slot}. Topic: {topic}{place}.\n"
        f"Rules: plain trade language, no emojis, no hashtags, no exclamation streaks, "
        f"avoid these words: {avoid}. 2-4 sentences. If a city is given, name it in the "
        f"first sentence. Do not invent job details, prices, or customer names. "
        f"Same-day service may be mentioned truthfully.\n"
        f"Return ONLY the caption text."
    )


def generate_caption(slot: SlotSpec, topic: str, city: str | None,
                     cfg: InstagramConfig, brand: BrandConfig) -> str:
    body = complete(build_prompt(slot, topic, city, cfg, brand)).strip()
    cta = cfg.slot_cta[slot.slot]
    return f"{body}\n\n{cta}"
```

- [ ] **Step 4: Verify PASS** (`pytest tests/test_ig_captions.py -v`), full suite, **Step 5: Commit** — `git commit -m "feat(ig): deterministic topics + claude caption generation"`.

---

### Task 5: Job-photo drop folder scanner

**Files:**
- Create: `twins-content-engine/engine/ig_assets.py`
- Test: `twins-content-engine/tests/test_ig_assets.py`

Convention: Daniel (or techs, via shared album export) drop files into `assets/instagram/inbox/` named `<kind>_<city>_<anything>.<ext>` where kind ∈ completed/before_after/review/truck/tools/parts/wip and city uses dashes for spaces (`sun-prairie`). Scanner returns `RealAsset`s (kind mapped to resolver kinds, `completed`→`completed_job`, `review`→`verified_review`) plus the parsed city, newest file first.

- [ ] **Step 1: Failing tests**

```python
# tests/test_ig_assets.py
from engine.ig_assets import scan_inbox


def test_scan_maps_kinds_and_cities(tmp_path):
    (tmp_path / "completed_verona_spring.jpg").write_bytes(b"x")
    (tmp_path / "before_after_sun-prairie_door.png").write_bytes(b"x")
    (tmp_path / "truck_madison.jpg").write_bytes(b"x")
    (tmp_path / "notes.txt").write_bytes(b"x")          # ignored: not an image
    (tmp_path / "random.jpg").write_bytes(b"x")         # ignored: no known kind prefix
    found = scan_inbox(tmp_path)
    kinds = {(a.asset.kind, a.city) for a in found}
    assert ("completed_job", "Verona") in kinds
    assert ("before_after", "Sun Prairie") in kinds
    assert ("truck", "Madison") in kinds
    assert len(found) == 3


def test_empty_inbox_is_fine(tmp_path):
    assert scan_inbox(tmp_path) == []
```

- [ ] **Step 2: Verify FAIL.**

- [ ] **Step 3: Implement `engine/ig_assets.py`**

```python
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
```

- [ ] **Step 4: Verify PASS**, full suite, **Step 5: Commit** — `git commit -m "feat(ig): job-photo inbox scanner"`.

---

### Task 6: Approval CLI

**Files:**
- Create: `twins-content-engine/scripts/approve_ig.py`
- Test: `twins-content-engine/tests/test_ig_approve.py`

Mirrors the existing `scripts/approve.py` pattern: `list` shows pending drafts (slot, date, caption first line, visual kind, needs_approval flags); `approve <name>` moves the file to `approved/instagram/`; `reject <name> "reason"` moves to `rejected/instagram/` and records the reason in frontmatter. Pure-function core (`approve_draft`, `reject_draft`) + argparse `main`.

- [ ] **Step 1: Failing tests**

```python
# tests/test_ig_approve.py
import frontmatter
from scripts.approve_ig import approve_draft, reject_draft


def _mk(pending, name="2026-07-06_proof.md"):
    p = pending / name
    p.write_text("---\nslot: proof\ndate: '2026-07-06'\n---\nCaption body.")
    return p


def test_approve_moves_to_approved(tmp_path):
    pending = tmp_path / "pending"; pending.mkdir()
    approved = tmp_path / "approved"
    f = _mk(pending)
    out = approve_draft(f, approved)
    assert not f.exists() and out.exists()
    assert frontmatter.loads(out.read_text())["approved"] is True


def test_reject_records_reason(tmp_path):
    pending = tmp_path / "pending"; pending.mkdir()
    rejected = tmp_path / "rejected"
    f = _mk(pending)
    out = reject_draft(f, rejected, "wrong city")
    assert not f.exists() and out.exists()
    post = frontmatter.loads(out.read_text())
    assert post["rejected_reason"] == "wrong city"
```

- [ ] **Step 2: Verify FAIL.**

- [ ] **Step 3: Implement `scripts/approve_ig.py`**

```python
"""Approve or reject pending Instagram drafts (human gate)."""
from __future__ import annotations

import argparse
from pathlib import Path

import frontmatter

ROOT = Path(__file__).resolve().parent.parent
PENDING = ROOT / "pending" / "instagram"
APPROVED = ROOT / "approved" / "instagram"
REJECTED = ROOT / "rejected" / "instagram"


def approve_draft(path: Path, approved_dir: Path) -> Path:
    post = frontmatter.loads(path.read_text())
    post["approved"] = True
    approved_dir.mkdir(parents=True, exist_ok=True)
    out = approved_dir / path.name
    out.write_text(frontmatter.dumps(post))
    path.unlink()
    return out


def reject_draft(path: Path, rejected_dir: Path, reason: str) -> Path:
    post = frontmatter.loads(path.read_text())
    post["rejected_reason"] = reason
    rejected_dir.mkdir(parents=True, exist_ok=True)
    out = rejected_dir / path.name
    out.write_text(frontmatter.dumps(post))
    path.unlink()
    return out


def _list(pending_dir: Path) -> None:
    files = sorted(pending_dir.glob("*.md")) if pending_dir.exists() else []
    if not files:
        print("No pending drafts.")
        return
    for f in files:
        post = frontmatter.loads(f.read_text())
        flags = ", ".join(post.get("source", {}).get("needs_approval", [])) or "-"
        first_line = post.content.strip().splitlines()[0] if post.content.strip() else ""
        print(f"{f.name}  [{post.get('slot')}]  visual={post.get('visual', {}).get('kind')}  flags={flags}")
        print(f"    {first_line}")


def main() -> None:
    ap = argparse.ArgumentParser()
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("list")
    a = sub.add_parser("approve"); a.add_argument("name")
    r = sub.add_parser("reject"); r.add_argument("name"); r.add_argument("reason")
    args = ap.parse_args()
    if args.cmd == "list":
        _list(PENDING)
    elif args.cmd == "approve":
        print(approve_draft(PENDING / args.name, APPROVED))
    else:
        print(reject_draft(PENDING / args.name, REJECTED, args.reason))


if __name__ == "__main__":
    main()
```

- [ ] **Step 4: Verify PASS**, full suite, **Step 5: Commit** — `git commit -m "feat(ig): approval CLI (human gate)"`.

---

### Task 7: Composio wrapper + publisher

**Files:**
- Create: `twins-content-engine/engine/composio_client.py`
- Create: `twins-content-engine/scripts/publish_ig.py`
- Test: `twins-content-engine/tests/test_ig_publish.py`

Publisher contract: collect `approved/instagram/*.md` with `date <= today` and no `published_at`; re-run `check_instagram_draft` (defense in depth — a hand-edited approved file must still pass); **dry-run by default** printing exactly what would be sent; `--live` requires `cfg.publish_tools["verified"] is True` or it refuses; on success stamp `published_at` + move to `published/instagram/`; update month state via `ig_state`. The Composio call goes through `run_tool(slug, payload)` in `composio_client.py` (subprocess to the `composio` CLI), which tests mock.

- [ ] **Step 1: Failing tests**

```python
# tests/test_ig_publish.py
import datetime as dt
import json
import frontmatter
import pytest
from pathlib import Path
from engine.config import load_instagram_config
from scripts.publish_ig import publish_due

CFG = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")


def _approved(dirp, name, date, caption="Broken spring repair in Verona.\n\nCall or book through the link in our profile"):
    dirp.mkdir(parents=True, exist_ok=True)
    p = dirp / name
    post = frontmatter.Post(caption)
    post["date"] = date; post["slot"] = "proof"; post["approved"] = True
    post["cta"] = "Call or book through the link in our profile"
    post["visual"] = {"kind": "real", "asset_path": "/tmp/x.jpg", "ai_spec": None, "fallback_level": 1, "needs_approval": False}
    p.write_text(frontmatter.dumps(post))
    return p


def test_dry_run_publishes_nothing_and_lists_due(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         CFG, today=dt.date(2026, 7, 6), live=False)
    assert result["due"] == 1 and result["published"] == 0
    run_tool.assert_not_called()


def test_live_refuses_unverified_tools(tmp_path, mocker):
    mocker.patch("scripts.publish_ig.run_tool")
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    with pytest.raises(RuntimeError, match="verified"):
        publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                    CFG, today=dt.date(2026, 7, 6), live=True)


def test_live_publishes_and_stamps(tmp_path, mocker):
    run_tool = mocker.patch("scripts.publish_ig.run_tool", return_value={"successful": True})
    cfg = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")
    cfg.publish_tools["verified"] = True
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         cfg, today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 1
    run_tool.assert_called_once()
    published = list((tmp_path / "published").glob("*.md"))
    assert len(published) == 1
    assert frontmatter.loads(published[0].read_text()).get("published_at")
    state = json.loads((tmp_path / "state.json").read_text())
    assert state["total"] == 1


def test_unsafe_edited_draft_is_skipped(tmp_path, mocker):
    mocker.patch("scripts.publish_ig.run_tool")
    cfg = load_instagram_config(Path(__file__).resolve().parents[1] / "config" / "instagram.yaml")
    cfg.publish_tools["verified"] = True
    approved = tmp_path / "approved"
    _approved(approved, "2026-07-06_proof.md", "2026-07-06",
              caption="Get $25 off in Rockford!\n\nCall or book through the link in our profile")
    result = publish_due(approved, tmp_path / "published", tmp_path / "state.json",
                         cfg, today=dt.date(2026, 7, 6), live=True)
    assert result["published"] == 0 and result["skipped_unsafe"] == 1
```

- [ ] **Step 2: Verify FAIL.**

- [ ] **Step 3: Implement `engine/composio_client.py`**

```python
"""Thin subprocess wrapper around the authenticated Composio CLI."""
from __future__ import annotations

import json
import shutil
import subprocess
from pathlib import Path

_COMPOSIO = shutil.which("composio") or str(Path.home() / ".composio" / "composio")


def run_tool(slug: str, payload: dict) -> dict:
    proc = subprocess.run(
        [_COMPOSIO, "execute", slug, "-d", json.dumps(payload)],
        capture_output=True, text=True, timeout=120,
    )
    if proc.returncode != 0:
        raise RuntimeError(f"composio execute {slug} failed: {proc.stderr[:500]}")
    return json.loads(proc.stdout)
```

- [ ] **Step 4: Implement `scripts/publish_ig.py`**

```python
"""Publish due, approved Instagram drafts via Composio. Dry-run by default."""
from __future__ import annotations

import argparse
import datetime as dt
from pathlib import Path

import frontmatter

from engine.composio_client import run_tool
from engine.config import InstagramConfig, load_instagram_config
from engine.ig_rules import check_instagram_draft
from engine.ig_state import load_month_state, save_month_state

ROOT = Path(__file__).resolve().parent.parent


def publish_due(approved_dir: Path, published_dir: Path, state_path: Path,
                cfg: InstagramConfig, today: dt.date, live: bool) -> dict:
    if live and not cfg.publish_tools.get("verified"):
        raise RuntimeError(
            "publish_tools.verified is false — run the slug-discovery step and set "
            "verified: true in config/instagram.yaml before --live."
        )
    result = {"due": 0, "published": 0, "skipped_unsafe": 0}
    state = load_month_state(state_path, today)
    files = sorted(approved_dir.glob("*.md")) if approved_dir.exists() else []
    for f in files:
        post = frontmatter.loads(f.read_text())
        if post.get("published_at") or dt.date.fromisoformat(str(post["date"])) > today:
            continue
        result["due"] += 1
        report = check_instagram_draft(post.content.strip(), str(post.get("cta", "")), cfg)
        if not report.passed:
            result["skipped_unsafe"] += 1
            print(f"UNSAFE (skipped): {f.name}: {[v.message for v in report.violations]}")
            continue
        if not live:
            print(f"DRY-RUN would publish: {f.name} ({post.get('slot')})")
            continue
        run_tool(cfg.publish_tools["image_post"], {
            "caption": post.content.strip(),
            "image_path": post.get("visual", {}).get("asset_path"),
        })
        post["published_at"] = dt.datetime.now().isoformat(timespec="seconds")
        published_dir.mkdir(parents=True, exist_ok=True)
        (published_dir / f.name).write_text(frontmatter.dumps(post))
        f.unlink()
        state["total"] += 1
        if post.get("visual", {}).get("kind") == "ai":
            state["ai_used"] += 1
        result["published"] += 1
    save_month_state(state_path, state)
    return result


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--live", action="store_true", help="actually publish (default: dry-run)")
    args = ap.parse_args()
    cfg = load_instagram_config(ROOT / "config" / "instagram.yaml")
    res = publish_due(ROOT / "approved" / "instagram", ROOT / "published" / "instagram",
                      ROOT / "data" / "ig_month_state.json", cfg,
                      today=dt.date.today(), live=args.live)
    print(res)


if __name__ == "__main__":
    main()
```

- [ ] **Step 5: Verify PASS** (`pytest tests/test_ig_publish.py -v`, then full suite), **Step 6: Commit** — `git commit -m "feat(ig): composio publisher with dry-run default + safety re-check"` (3 files).

---

### Task 8: Wire generate_ig_week main() for real

**Files:**
- Modify: `twins-content-engine/scripts/generate_ig_week.py`
- Modify: `twins-content-engine/tests/test_ig_generate_week.py`

Replace `_real_caption_fn` and the stub `assets_fn` with: `ig_captions.generate_caption` (topic via `pick_topic`, city from the newest matching inbox asset), `ig_assets.scan_inbox(ROOT / "assets" / "instagram" / "inbox")`, month state from `ig_state`, CTA + hashtags + city populated onto the Draft (hashtags = `hashtags_base` + city hashtag, capped at 5), and `SourceRecord` upgraded to carry the real asset path as `job_folder` and asset kind fidelity (`asset_source` = `verified_review` / `real_photo` / `ai_graphic`). The draft-level gate switches from `check_instagram_caption` to `check_instagram_draft`.

- [ ] **Step 1: Failing test** — add to `tests/test_ig_generate_week.py`:

```python
def test_wired_generation_populates_cta_city_hashtags(tmp_path, mocker):
    mocker.patch("scripts.generate_ig_week.generate_caption",
                 return_value="Broken spring repair in Verona.\n\nTell us what the door is doing and what city you are in")
    inbox = tmp_path / "inbox"
    inbox.mkdir()
    (inbox / "completed_verona_done.jpg").write_bytes(b"x")
    written = generate_week_wired(
        monday=dt.date(2026, 7, 6), cfg=CFG, out_dir=tmp_path / "pending",
        hiring=False, inbox=inbox, state_path=tmp_path / "state.json",
    )
    assert len(written["passed"]) == 3
    post = frontmatter.loads(Path(written["passed"][0]).read_text())
    assert post["cta"] in CFG.approved_cta
    assert post["city"] == "Verona"
    assert any(h == "#VeronaWI" for h in post["hashtags"])
    assert post["source"]["job_folder"] and post["source"]["asset_source"] == "real_photo"
```

(import `generate_week_wired`, `frontmatter`, `Path` at top of the test file; existing tests for the injected-fn `generate_week` stay untouched.)

- [ ] **Step 2: Verify FAIL.**

- [ ] **Step 3: Implement** — in `scripts/generate_ig_week.py`, keep `generate_week` (pure, injected) and add `generate_week_wired(monday, cfg, out_dir, hiring, inbox, state_path)` that:
  1. loads brand config + month state,
  2. builds `assets_fn` returning inbox assets (proof slots consume the newest completed/before_after first; each used asset is not offered twice in the same week),
  3. builds `caption_fn` = `generate_caption(slot, pick_topic(slot, cfg), city_for(slot), cfg, brand)`,
  4. populates `Draft` with cta=`cfg.slot_cta[slot.slot]`, city from the chosen asset (None for AI graphics), hashtags = `(cfg.hashtags_base + [cfg.city_hashtags.get(city)] if city else cfg.hashtags_base)[:5]` with None filtered,
  5. sets `SourceRecord(asset_source=..., job_folder=asset dir or None, review_verified=(kind=="verified_review") or None, confirmed_city=city, offer_used=offer topic when slot=="offer" else None, needs_approval=visual flags)`,
  6. gates with `check_instagram_draft(caption, cta, cfg)`,
  7. saves month state (`ai_used`/`total` updated with the week's counts) at the end,
  8. `main()` calls `generate_week_wired` with `ROOT`-based default paths and prints the pass/held counts.

The exact code follows the existing `generate_week` body — copy its loop shape, swapping the injected fns for the wired ones; keep it under ~80 lines.

- [ ] **Step 4: Verify PASS** — new test + all Plan 1 orchestration tests; `pytest -q` full suite green.

- [ ] **Step 5: Commit** — `git commit -m "feat(ig): wire real captions, inbox assets, state, cta/hashtags into weekly generation"`.

---

### Task 9 (execution-time, no code): Composio slug discovery + live smoke test

**Files:**
- Modify: `twins-content-engine/config/instagram.yaml` (fill real slugs, set `verified: true`)

- [ ] **Step 1:** Run `composio search "publish instagram photo post" --toolkits instagram --limit 10` and identify the real publish tool slug(s) (Instagram Graph flow is usually a two-step container+publish; if so, note both and adapt `publish_ig.run_tool` call into two calls in a follow-up commit with a test).
- [ ] **Step 2:** Update `publish_tools` in `config/instagram.yaml` with the real slug(s); set `verified: true`. Commit: `git commit -m "chore(ig): verified composio publish tool slugs"`.
- [ ] **Step 3:** Dry-run end-to-end: `python scripts/generate_ig_week.py --monday <next-monday>` → `python scripts/approve_ig.py list` → approve one → `python scripts/publish_ig.py` (dry-run) and confirm output. **Do NOT run `--live` without Daniel's explicit go.**

---

## Self-review notes

- Spec coverage: closes Plan 1's disclosed gaps (AI-cap persistence Task 2, machine safeguards Task 3, CTA/format config consumption Tasks 3/8, source-record fidelity Task 8). Publishing workflow steps 1-4 of the spec are now fully implemented; the monthly performance loop remains Plan 3.
- Live publishing is quadruple-gated: human approval file move + publish-time re-validation + dry-run default + `verified` slug flag. Reels/carousel publishing (video, multi-image) is deferred: Plan 2 publishes single-image posts; format_target stays a target and the publisher treats every draft as an image post until Plan 2b extends it. This is intentional YAGNI — confirm real slugs before building multi-step container flows.
- Open go-live items (unchanged): public phone number, booking link, hiring status, offer fine print. None block building; all block `--live`.

## Task 9 execution results (2026-07-09)

- **Real Composio slugs discovered and committed** (26e636c): publishing is the Graph two-step — `INSTAGRAM_POST_IG_USER_MEDIA` (container) then `INSTAGRAM_POST_IG_USER_MEDIA_PUBLISH` (creation_id). `ig_user_id: "me"` works. Publisher adapted with `_publish_via_composio` preserving mark-then-post semantics.
- **BLOCKING go-live gap found: image hosting.** The Graph API only accepts a public HTTPS `image_url` — local inbox paths cannot be posted. The publisher now requires frontmatter `image_url` (else `skipped_unsafe: "no public image_url (hosting step pending)"`), and `publish_tools.verified` stays `false` until a hosting step exists (candidates: Twins WP media library via REST, or a public Supabase storage bucket on jwrpj). This is Plan 2b work.
- **End-to-end dry-run smoke PASSED**: seeded a real door photo as `completed_madison_smoke.jpg` → `generate_ig_week --monday 2026-07-13` produced 3 passing drafts (real photo on proof; AI-cap flags fired on the 2 AI drafts at 2/3 > 0.34) → `approve_ig approve` moved the proof draft → dry-run publish correctly skipped future-dated drafts, previewed "would skip (missing image_url)" without a URL and "would publish" with one. State file persisted and was reset after the smoke; all artifacts cleaned.
- **Content-safety catch from the smoke:** a proof caption fabricated job timing ("wrapped up today... before we packed up"). Fixed at prompt level (bfc054f): proof prompts now forbid claiming when the job happened or inventing a narrative. Machine-enforceable follow-up for Plan 3: an `ig:invented_timing` rule for proof captions (needs a slot-aware `check_instagram_draft`).
