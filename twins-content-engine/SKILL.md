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
