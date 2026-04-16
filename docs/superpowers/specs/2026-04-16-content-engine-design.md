# Twins Content Engine — Design Spec

**Date:** 2026-04-16
**Status:** Draft for implementation planning
**Scope:** Subsystem #1 of 7 in the Twins Garage Doors marketing automation decomposition

## Context

Twins Garage Doors is a local garage door repair and installation business in Madison, WI. The broader goal is an end-to-end automated content engine that drives inbound calls via local SEO, AI search visibility, and social distribution.

That end-to-end vision is too large for a single implementation. It has been decomposed into seven subsystems:

1. **Strategy + Content Generator** ← **this spec**
2. Repurposing system
3. Google Business Profile / Local SEO publishing
4. AI Search Optimization (GEO/AEO) publishing
5. Social distribution (Meta, TikTok, YouTube)
6. Performance + feedback loop
7. Review generation workflow

This spec covers **only subsystem #1**: the engine that produces reviewable, ready-to-distribute content. Distribution, scheduling, publishing, and analytics are explicitly deferred to later subsystems.

A related, already-built system — `twins-media-generator` (at `twins-dashboard/twins-media-generator/`) — handles image and video generation via Nano Banana 2, Veo 3.1, Remotion, and Gemma 4. The content engine produces JSON "briefs" that the media generator consumes. The two systems are loosely coupled via a file-based contract.

## Goals

- Produce local, high-signal content that sounds like a Madison garage door tech, not a copywriter
- Generate five distinct output formats per source query: video script, caption, GBP post, FAQ, blog snippet
- Organize all work around **clusters of real homeowner search queries**, not pillars in the abstract
- Enforce an anti-AI-spam rules gate so generated content has specific hooks, local embedding, and single-purpose CTAs
- Provide a manual review step before anything is considered publishable
- Emit structured JSON briefs for the media generator to produce matching visuals
- Keep the system operable from the CLI by a technical operator (following the `twins-media-generator` pattern)

## Non-goals (v1)

- Posting, scheduling, or publishing to any platform
- Direct invocation of `twins-media-generator` (briefs only — manual handoff)
- Analytics / KPI tracking / feedback loops
- Multi-LLM provider abstraction
- Web UI or dashboard
- A/B testing of content variants
- Auto-publishing to blog or CMS

## Architecture

```
twins-dashboard/twins-content-engine/
├── SKILL.md                        # discovery + usage (matches twins-media-generator)
├── config/
│   ├── brand.yaml                  # name, phone, voice signals, brand terms
│   ├── service_area.yaml           # Madison + surrounding towns (configurable)
│   ├── pillars.yaml                # 7 pillars with targeting + query patterns
│   └── rules.yaml                  # tone rules, anti-AI-spam blacklist, hook specs
├── data/
│   ├── content_engine.db           # SQLite (clusters, queries, generated_content)
│   ├── harvest_inbox.md            # drop new queries here anytime
│   ├── harvest_processed/          # archived after weekly refresh
│   └── seeds/initial_seed.md       # user-provided 15–25 seed queries
├── engine/                         # library code (importable, unit-tested)
│   ├── db.py                       # SQLite layer
│   ├── claude_client.py            # LLM wrapper: retries, prompt caching, cost logs
│   ├── cluster.py                  # cluster detection + expansion
│   ├── generator.py                # multi-format generation orchestrator
│   ├── rules.py                    # enforcement: hook, length, CTA, anti-AI checks
│   ├── local_embedding.py          # Madison-aware phrasing injection
│   ├── brief.py                    # JSON brief builder for media generator
│   └── review.py                   # pending→approved workflow
├── scripts/                        # thin CLIs calling engine/
│   ├── ingest_seeds.py
│   ├── expand_clusters.py
│   ├── harvest_refresh.py
│   ├── generate_week.py
│   ├── generate_cluster.py
│   ├── regenerate.py
│   ├── approve.py
│   └── emit_briefs.py
├── pending/                        # Markdown files awaiting review
├── approved/                       # reviewed + ready
└── briefs/                         # JSON handoffs for twins-media-generator
```

### Component boundaries

- `engine/` is a pure library. No CLI code, no `argparse`, no `print` for user output. Unit-testable in isolation.
- `scripts/` are thin `argparse` wrappers that call `engine/` and print user-facing output. Smoke-tested only.
- `config/` YAMLs are the tuning surface. No code changes needed to adjust pillars, swap tone rules, or change the service area.
- Content lives in Markdown (reviewable, diffable, git-friendly). Metadata and state live in SQLite.
- Handoff to `twins-media-generator` is a one-way JSON file contract. Neither side imports the other.

## Data model

### SQLite tables

```sql
clusters
  id                INTEGER PK
  name              TEXT UNIQUE      -- e.g. "broken_spring"
  pillar            TEXT             -- FK-by-key into pillars.yaml
  service_type      TEXT             -- e.g. "spring_replacement"
  funnel_stage      TEXT             -- one of the funnel stages in rules.yaml
  priority_score    INTEGER          -- 1–10
  notes             TEXT
  created_at, updated_at

queries
  id                INTEGER PK
  cluster_id        INTEGER FK clusters.id
  query_text        TEXT
  phrasing_type     TEXT             -- emergency | price_anxiety | question | symptom | local_variant | raw_seed
  geo_modifier      TEXT             -- madison_wi | middleton_wi | ... | null
  source            TEXT             -- call | text | search_console | review | harvest_inbox | llm_expansion
  priority_score    INTEGER          -- 1–10
  notes             TEXT
  created_at, updated_at

generated_content
  id                INTEGER PK
  cluster_id        INTEGER FK clusters.id
  source_query_id   INTEGER FK queries.id
  format            TEXT             -- video_script | caption | gbp_post | faq | blog_snippet
  content_path      TEXT             -- relative path to .md file
  brief_path        TEXT             -- nullable, path to .json
  status            TEXT             -- pending | approved | rejected | published
  model_used        TEXT
  generated_at      DATETIME
  approved_at       DATETIME
  notes             TEXT
```

### Why pillars live in YAML (not DB)

Pillars are human-tuned, rarely change, and benefit from PR review and git diff. Cluster records reference pillar by key (e.g., `emergency_repairs`). The DB layer validates the key against `pillars.yaml` on load.

### Why clusters matter for generation

Every generator call picks one representative query from one cluster — never cross-cluster. The weekly rotation tracks recency per cluster and samples proportional to `pillar.priority_weight × inverse_recency`. This is the mechanism that prevents the "every post sounds the same" failure mode.

## Content pillars

Defined in `config/pillars.yaml`:

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

## Rules engine + anti-AI-spam layer

Lives in `engine/rules.py`, driven by `config/rules.yaml`. Runs after generation as a gate. Failing pieces are auto-regenerated once with the violation list fed back as critique; a second failure lands the piece in `pending/_REJECTED_<file>.md` with violations listed at the top.

```yaml
voice:
  persona: "A Madison garage door tech who picks up the phone himself.
            Direct, practical, slightly dry. Not a copywriter. Not excited."
  structure: "hook → problem-specifics → authority-proof → single CTA"
  reading_level_max: 8

hook_requirements:
  video_script:
    - first 6–8 words must reference a specific symptom, number, or neighborhood
    - may not begin with "Whether", "Are you", "Did you know", "In today's"
  caption:
    - first line must mirror a search query (symptom-phrased, first-person OK)
  gbp_post:
    - first 10 words must contain "Madison" or a named surrounding town
  blog_snippet:
    - first sentence must contain a specific number, timeframe, or price range

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
    - em_dash_pair_as_dramatic_pause   # "— and here's why —"
    - formulaic_triadic_list           # "Fast. Reliable. Affordable." / "Fast, reliable, affordable." — short adjective triples regardless of separator

specificity_requirements:
  must_include_one_of:
    - specific_number           # "13 years", "$180–240", "8 springs this week"
    - named_location            # "Maple Bluff", "University Heights", "Middleton"
    - timeframe                 # "same-day", "within 2 hours", "before winter"
    - concrete_detail           # "10-ft torsion spring", "LiftMaster 8500W"
  must_avoid:
    - generic_claims            # "We're the best", "trusted by many"
    - unverifiable_superlatives # "#1 in the Midwest"

cta_rules:
  one_per_piece: true
  funnel_stage_mapping:
    high_intent_problem_aware: call_now
    mid_intent_price_checking: free_estimate
    mid_intent_deciding:       text_or_call
    low_intent_educational:    save_or_share
    trust_and_ranking:         learn_more
  phone_number_source: brand.yaml

local_embedding:
  mention_frequency:
    video_script: 1_to_2
    caption:      1
    gbp_post:     2_to_3
    faq:          2
    blog_snippet: 3_to_5
  natural_placement_rules:
    - never in consecutive sentences
    - prefer neighborhood/town name over generic "Madison" when possible
    - at least one mention in first 30% of piece
```

### Rule report contract

```python
RuleReport(
    passed: bool,
    violations: list[Violation],   # each has rule_id, severity, span, message
    suggestions: list[str],        # human-readable critique for regeneration prompt
)
```

## Output formats

Each generation run, for one selected query in a cluster, produces all five formats. Each is a Markdown file with strict structure validated in `engine/generator.py` before write.

### `video_script.md`

```
---
cluster: broken_spring
source_query: "garage door won't open and it made a loud bang"
duration_sec: 20
aspect_ratio: 9:16
---
HOOK (0:00–0:02):
[on-screen text + voiceover — 6–8 words, symptom-specific]

PROBLEM (0:02–0:08):
[3–4 lines, what's actually happening, no fluff]

AUTHORITY (0:08–0:15):
[specific detail — e.g., "Torsion springs are rated for 10,000 cycles.
After 13 years in Madison, I've replaced hundreds."]

CTA (0:15–0:20):
[one action, phone number on screen]

SHOT LIST:
- 0:00–0:02: close-up of snapped spring
- 0:02–0:08: tech pointing at broken cable
- 0:08–0:15: truck with Twins branding
- 0:15–0:20: phone number card
```

### `caption.md`

```
---
cluster: broken_spring
platforms: [instagram, facebook]
char_count: 180–250
---
[Line 1: mirrors search phrasing — "Your garage door won't open and made a loud bang?"]
[2–3 short lines of practical info, no emojis unless brand.yaml allows]
[CTA line with phone]

#GarageDoorRepair #MadisonWI #{neighborhood_or_town}
```

Max 5 hashtags. First 3 local.

### `gbp_post.md`

```
---
cluster: broken_spring
char_count: 900–1400
cta_button: CALL           # CALL | LEARN_MORE | BOOK
---
[Opens with "In Madison" or named town — required by rules]
[2 paragraphs, 6–10 sentences total, 2–3 local mentions]
[One clear CTA tied to funnel stage]
```

### `faq.md`

Schema.org-friendly structured Q&A, up to 3 Q&A pairs per cluster. This is the AI-search-optimization payload — concise factual answers with natural entity repetition:

```
---
cluster: broken_spring
structured_data: true
---
## Q: What does it mean when my garage door makes a loud bang and stops working?
**A:** It usually means a torsion spring snapped. Twins Garage Doors in Madison WI
handles this same-day — the spring is under ~200 lbs of tension, so don't try to
open the door manually. Expect $250–400 for a standard replacement pair.

## Q: Can I open my garage door if the spring is broken?
**A:** [concise factual answer with entity mention]

## Q: How much does spring replacement cost in Madison?
**A:** [concise factual answer]
```

### `blog_snippet.md`

250–400 words. Targets the cluster's highest-priority query. Structure: H1 (question-form) → 1-paragraph direct answer (AI-search-friendly) → 3 short sections → final CTA paragraph. Internal linking placeholder tags `[[link:service/spring-repair]]` so a later publishing subsystem can resolve them.

## Handoff contract to `twins-media-generator`

Every generated `video_script` and `gbp_post` emits a companion `brief.json` consumed by the media generator's existing `--prompt` / `--template` machinery:

```json
{
  "brief_id": "2026-04-16_broken_spring_video_001",
  "source_cluster": "broken_spring",
  "source_query_id": 42,
  "purpose": "video_script",
  "platform_targets": ["instagram_reels", "tiktok", "youtube_shorts"],
  "aspect_ratio": "9:16",
  "duration_sec": 20,
  "preferred_template": "emergency_repair",
  "template_options": {
    "issue_type": "broken_spring",
    "time": "day"
  },
  "fallback_prompt": "[verbatim prompt if template unavailable]",
  "on_screen_text": ["Your spring just snapped?", "Don't try to lift it.", "Call Twins."],
  "voiceover_script": "...",
  "shot_list": [...],
  "brand_enforcement": {
    "phone_number": "[resolved from brand.yaml]",
    "brand_colors": "enforce",
    "location_context": "Madison, WI"
  }
}
```

The content engine never calls the media generator. It writes the JSON and exits. The `emit_briefs.py` script (or a future scheduler) picks up `briefs/*.json` and invokes `twins-media-generator/scripts/generate_video.py` with the right flags.

## Workflow

### One-time setup

```bash
python scripts/ingest_seeds.py data/seeds/initial_seed.md
python scripts/expand_clusters.py --all
```

### Weekly cadence (~15 min of operator time)

```bash
python scripts/harvest_refresh.py
python scripts/generate_week.py --count 7
# review pending/ folder in editor
python scripts/approve.py --all-passing
python scripts/emit_briefs.py --since yesterday
```

Produces 7 clusters × 5 formats = 35 reviewable pieces per week.

### Ad hoc

```bash
python scripts/generate_cluster.py --name broken_spring --formats video_script,gbp_post
python scripts/regenerate.py --id 127 --critique "CTA too soft, make it direct"
```

### Cluster sampling algorithm

`generate_week.py` samples clusters using weighted inverse-recency:

```
weight(cluster) = pillar.priority_weight × (1 / (days_since_last_use + 1))
```

Clusters are drawn without replacement until `--count` is satisfied. Pillar balance is enforced as a soft constraint: no more than 2 clusters from the same pillar per weekly batch unless `--pillar-lock` is set.

## Testing strategy

- **`engine/` unit tests** cover:
  - DB CRUD and migration integrity
  - Rules engine: each rule checked independently, plus full report flow
  - Cluster expansion with mocked Claude responses
  - Multi-format generator with mocked Claude + rule-pass + rule-fail paths
  - Brief builder: JSON schema validation
- **`scripts/` smoke tests** run each CLI with `--help` and a minimal happy-path invocation against a temp DB + fixture seeds.
- **Claude calls are mocked** in unit tests. A single integration test hits the real API end-to-end with one cluster generation, gated behind `INTEGRATION=1` env.

## Error handling

- All Claude calls wrapped with retries (3 attempts, exponential backoff) + cost logging
- Rule-gate failures are expected; they trigger one regeneration retry before the piece is flagged
- Malformed seed or harvest entries produce per-line warnings and skip, not hard failures
- SQLite is opened with WAL; all writes are transactional
- Missing or malformed config YAMLs fail fast on import with a clear error message

## Open questions / deferred decisions

The following are intentionally not addressed in this spec. They are noted here so later subsystem specs can reference them:

- **Posting automation**: Subsystem #5 will consume `approved/` and distribute to platforms via Meta Graph, TikTok API, YouTube Data API, Google Business Profile API.
- **SEO publishing**: Subsystem #4 will consume `approved/blog_snippet/*` and `approved/faq/*`, resolve internal links, and publish to a CMS (likely WordPress).
- **Analytics**: Subsystem #6 will close the loop — pull call/engagement/ranking data, correlate with `generated_content.id`, and feed priority adjustments back into clusters and queries.
- **Review generation outreach**: Subsystem #7 (SMS/email review requests) is fully independent of this engine.
- **Multi-LLM provider support**: Deferred. `claude_client.py` is the single integration point; an abstraction layer can be added when a real need emerges.

## v1 Definition of Done

The operator can:

1. Seed ~20 queries in `data/seeds/initial_seed.md`
2. Run `ingest_seeds.py` and `expand_clusters.py` to produce ~60–80 clustered queries in SQLite
3. Run `generate_week.py --count 7` to produce 35 pieces (7 clusters × 5 formats) that pass the rules gate
4. Review pieces in `pending/`, make edits in place, and run `approve.py` to promote them
5. Run `emit_briefs.py` to get JSON briefs in `briefs/` ready to hand off to `twins-media-generator`
6. Drop new queries into `harvest_inbox.md` at any time; `harvest_refresh.py` folds them in on next run

All `engine/` modules have unit test coverage. All `scripts/` have smoke tests.
