# Marketing-Skills Playbook

How to pair the `coreyhaines31/marketingskills` plugin (50+ skills) with this content engine.

## Install (one-time)

From any terminal (the `/plugin` slash command only works in the Claude Code CLI app, not in web/IDE):

```bash
npx -y skills add coreyhaines31/marketingskills --yes --global
```

This clones the repo, installs all 35 skills under `~/.agents/skills/`, and symlinks them into `~/.claude/skills/` so Claude Code picks them up in every session. Installed 2026-04-20.

Useful follow-ups:
```bash
npx skills list                              # what's installed
npx skills update                            # pull newer versions
npx skills remove                            # uninstall some/all
```

## The 10 skills that matter here

The content engine handles structure (intent clusters, rules gate, format fan-out). These skills sharpen the *words* around it.

| When | Skill | What to say |
|---|---|---|
| Before `expand_clusters.py` | `ai-seo` | "Review `data/seeds/initial_seed.md` for AI-search coverage — which cluster phrasings are we missing for ChatGPT / Perplexity / Gemini answers?" |
| Before `expand_clusters.py` (bigger push) | `programmatic-seo` | "Design a programmatic-SEO page template for each service + Madison-area town combo — which fields stay static, which vary?" |
| Before `generate_week.py` | `marketing-psychology` | "Critique the hooks in `config/rules.yaml` — are they pattern-interrupts or generic?" |
| After `generate_week.py` | `copy-editing` | "Tighten `pending/2026-04-20_broken_spring_blog_snippet.md` — cut fluff, strengthen CTA, keep Madison-local voice." |
| Drafting new ad-hoc content | `copywriting` | "Write a GBP post for `after-hours emergency` cluster — 120 words, hook → educate → authority → CTA." |
| Short-form video review | `social-content` | "Review the Reels script — is the first 3 seconds a scroll-stopper? Is there a clear visual direction per beat?" |
| Blog deployment to twinsdash.com | `site-architecture` | "Where on twinsdash.com should `pending/*_blog_snippet.md` posts live — `/blog/`, `/madison-garage-door-repair/`, or split by cluster?" |
| Blog deployment to twinsdash.com | `schema-markup` | "Generate LocalBusiness + FAQPage JSON-LD for the broken-spring cluster blog post." |
| Quarterly planning | `content-strategy` | "Audit `config/pillars.yaml` weights against what actually called in last quarter — which pillars are over/underweighted?" |
| New service-area launches | `competitor-alternatives` | "Find 5 local Madison-area competitors and the search phrases homeowners use to compare them — feed into `data/harvest_inbox.md`." |

## Usage patterns

**Weekly cadence** — no change to CLI commands. Insert skill invocations as review steps:

```
python scripts/harvest_refresh.py
# → invoke /ai-seo to spot-check new phrasing coverage
python scripts/generate_week.py --count 7
# → invoke /copy-editing on anything in pending/ that feels weak
python scripts/approve.py --all-passing
python scripts/emit_briefs.py --since yesterday
```

**Ad hoc** — when rewriting one cluster:

```
python scripts/generate_cluster.py --name broken_spring
# → /copy-editing on each of the 5 format files
python scripts/approve.py --ids ...
```

## Skills intentionally not mapped

The plugin ships 40+ skills outside the SEO content workflow: `page-cro`, `signup-flow-cro`, `onboarding-cro`, `paywall-upgrade-cro`, `cold-email`, `email-sequence`, `paid-ads`, `ad-creative`, `churn-prevention`, `free-tool-strategy`, `referral-program`, `launch-strategy`, `pricing-strategy`, `revops`, `sales-enablement`, `analytics-tracking`, `ab-test-setup`, `customer-research`, `community-marketing`, `aso-audit`, `lead-magnets`, and more.

They stay installed and are available if you ask for them — e.g. `pricing-strategy` if you're rethinking emergency-call pricing, `paid-ads` if you ever buy Google LSA. They're just not part of the content-engine loop.

## Invocation shortcuts

- `Skill tool → <skill-name>` works silently inside a conversation.
- `/<skill-name>` works as a slash command.
- Plain-English requests work too: "help me tighten this Reels hook" auto-triggers `copywriting` or `social-content`.

## When a skill conflicts with the rules gate

The content engine's `config/rules.yaml` is the source of truth. If a marketingskills output violates it (buzzwords, missing Madison-WI mention, weak hook), the rules gate rejects. Don't weaken the rules to accommodate a skill's output — fix the prompt to the skill instead.
