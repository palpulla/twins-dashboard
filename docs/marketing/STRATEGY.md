# Twins Garage Doors — Marketing Strategy (living document)

**Updated:** 2026-07-03 (seeded from [baselines/2026-07-03-baseline.md](baselines/2026-07-03-baseline.md))
**Rhythm:** revised whenever a Monday brief or monthly review changes a ranking. History lives in git.

## Positioning

Family-owned, twin-brothers garage door company in Madison, WI. Real local technicians (published bios: Charles Rue — Field Operations Manager; Maurice Williams and Nicholas Roccaforte — Senior Techs). Honest, fast service without the national-franchise upsell machine.

- **Contact used in all marketing:** (608) 888-8785, twinsgaragedoors.com. Never the 833 van number in local ads (known van/local mismatch).
- **Brand:** yellow + navy. No fabricated people, prices, or claims. Never promise same-day.

## Confirmed offers

| Offer | Use |
|---|---|
| $0 service call | Repair capture campaigns |
| $49 tune-up | Maintenance funnel entry + avatar ad |
| GoodLeap financing | Replacement/consideration segment |

## Segments → buckets

| Segment | Buyer moment | Bucket | Primary channels |
|---|---|---|---|
| Emergency repair | Door broken now, high intent | Capture | LSA, Google Ads, GBP |
| Planned replacement | Weeks of consideration | Capture + Base | Google, financing offer, estimate follow-up |
| Maintenance | No urgency, needs a nudge | Base | $49 tune-up funnel, GHL messaging, existing customers |

## Channel ranking (by proven ROI, 2026-07-03 baseline)

1. **Google LSA — proven winner.** $4,265 spend (14 tracked days) vs $14,912 earned LSA-tagged revenue (30 days). Scale candidate once spend tracking is fixed.
2. **Free/owned (Referral, Existing Customer, Door Sticker, Online Booking, Reserve with Google)** — $35,399 earned, zero marginal cost. Protect and systematize (review flow, referral asks, stickers on every job).
3. **Google (mixed organic/GBP/paid) — big but unmeasurable.** $24,694 earned + $1,913 Google Ads spend, not separable today. Attribution fix required before any budget decision.
4. **Facebook — unproven, cheap signal.** $4,894 earned FB-tagged in 30 days on $0 spend. Pilot candidate with a test cap, using media-generator creative.
5. **Unknown — 35% of earned revenue ($41,910/30d).** Not a channel; a measurement failure to fix at the CSR intake + HCP level.

**Compound channel — AI search (AEO/GEO):** getting recommended by ChatGPT/Perplexity/Gemini/AI Overviews for Madison garage-door queries. Baseline 2026-07-07: site is AI-crawlable + has LocalBusiness schema, but Twins is absent on cost/how-to queries and out-detailed by competitors who publish specifics. Program spec: [ai-search-reddit-program](../superpowers/specs/2026-07-07-ai-search-reddit-program-design.md). Paired with a Reddit credibility program (monitor + human-posted, disclosed, never automated).

## Budget rule

ROI-driven, no fixed cap. Every new paid initiative launches with (a) a test cap in dollars, (b) a kill/scale criterion, (c) Daniel's explicit approval. Losers die at the cap; winners scale in increments Daniel approves in the Monday brief.

## Authority matrix

| Action | Autonomy |
|---|---|
| Organic content (social, blog, GBP) | Auto-publish on schedule, brand rules enforced |
| Pause/adjust live ad campaigns | Propose in brief, Daniel approves |
| Launch new campaign / channel / offer | Propose with mini-plan, Daniel approves |
| Customer-facing messaging (GHL SMS/email copy) | Propose, Daniel approves |
| Spend money anywhere | Never without explicit approval |

Standing guardrails: reversible changes only; real data only (never fabricate); full dollar amounts; no Lovable references; KPI math immutable — briefs read canonical calculations.

## Cadence

- **Weekly:** Monday brief in chat (generator: `.claude/skills/marketing-brief/`, data map: [DATA-SOURCES.md](DATA-SOURCES.md)). Contents: what ran, spend, jobs + earned revenue by source, reviews/content, 2–3 proposed moves for approval. No email/SMS/push — chat only.
- **Monthly:** deeper pass — re-rank channels, revise this document, re-rank [BACKLOG.md](BACKLOG.md).
- **Mechanism (verified 2026-07-03):** local scheduled task `monday-marketing-brief` (stored at `~/.claude/scheduled-tasks/monday-marketing-brief/SKILL.md`), fires Mondays ~8:12 AM machine-local while the Claude desktop app is open — if the app is closed it runs on next launch. First-run tip: click "Run now" once in the Scheduled sidebar to pre-approve the Supabase read tools so future runs don't pause on permission prompts. Fallback any time: type `/marketing-brief`.
