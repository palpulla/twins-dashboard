---
date: 2026-04-20
topic: Marketing-skills plugin + content-engine SEO playbook
status: approved
---

# Marketing-Skills Plugin Integration for Twins Content Engine

## Goal

Install the `coreyhaines31/marketingskills` Claude Code plugin (50+ marketing skills) and document which ones pair with which steps of the existing `twins-content-engine` workflow, so future sessions can invoke them by name at the right moment.

## Non-goals

- No changes to `twins-content-engine` Python code.
- No wrapping of plugin skills in CLI scripts — they are conversational tools, invoked via the `Skill` tool or `/<skill-name>`.
- No adoption of the 40+ skills irrelevant to local-SEO content (paid ads, churn, pricing, RevOps, etc.). They remain installed but unmapped.

## Architecture

Two artifacts:

1. **Plugin install** — one-time user action in Claude Code:
   ```
   /plugin marketplace add coreyhaines31/marketingskills
   /plugin install marketing-skills
   ```
   Claude Code cannot run these from a tool call; the user runs them and I verify via `ls ~/.claude/plugins/`.

2. **Playbook doc** — single markdown file at [`twins-content-engine/docs/marketing-skills-playbook.md`](../../twins-content-engine/docs/marketing-skills-playbook.md), mapping the ~10 relevant skills to the content-engine pipeline stages.

## Skill → pipeline mapping

The content engine has these stages (from [`SKILL.md`](../../twins-content-engine/SKILL.md)):

| Stage | Script | Relevant marketingskills |
|---|---|---|
| Seed / cluster expansion | `expand_clusters.py` | `ai-seo`, `programmatic-seo` |
| Weekly draft generation | `generate_week.py` | `copywriting`, `marketing-psychology` |
| Review pass on `pending/` | (manual) | `copy-editing` |
| Short-form video scripts | (format inside generate) | `social-content` |
| Blog snippets → twinsdash.com | (format inside generate) | `site-architecture`, `schema-markup` |
| Weekly / quarterly planning | (human) | `content-strategy` |

Intent database already encodes the strategy (clusters, funnel stage, geo, priority). The new skills are used **around** the pipeline — sharpening inputs before generation and critiquing outputs before approval — not inside it.

## Usage patterns

- **Before a weekly run:** invoke `content-strategy` to review pillar weights in `config/pillars.yaml`.
- **Before `expand_clusters.py`:** invoke `ai-seo` on the seed file to check coverage of AI-answer-engine phrasing (ChatGPT / Perplexity / Claude / Gemini).
- **After `generate_week.py`:** invoke `copy-editing` on files in `pending/` that survived the rules gate but feel weak.
- **When planning blog deployment on twinsdash.com:** invoke `site-architecture` for URL / IA decisions, `schema-markup` for LocalBusiness + FAQPage JSON-LD.

## Risks / tradeoffs

- **Skill overload:** 50+ new skills in the list can dilute signal. Mitigation: the playbook names the ~10 that matter; the rest stay installed but are only surfaced if explicitly asked.
- **Conflict with existing `twins-content-engine` skill:** the project already has a local skill of that name. No conflict — different namespace (local SKILL.md vs plugin skill), different trigger phrases.
- **Anthropic-only constraint:** these are conversational skills, not model-switching tools; the constraint from [`project_content_engine.md`](~/.claude/projects/-Users-daniel-twins-dashboard/memory/project_content_engine.md) is unaffected.

## Deliverables

1. This spec (committed).
2. The playbook at `twins-content-engine/docs/marketing-skills-playbook.md` (committed).
3. Install instructions given inline to the user.
