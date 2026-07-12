# Phase 5 Workstream A — /wi Madison + Milwaukee build-out (design)

Date: 2026-07-10 · Status: awaiting Daniel's review · Prior context: `2026-07-09-twins-web-program-handoff.md`

Phase 5 was decomposed into four workstreams; Daniel chose **A: /wi build-out + Milwaukee overhaul** for this session. B (chrome re-skin), C (goodgolly shelf items), D (LP fixes) are deferred to later sessions.

## Goal

Make /wi genuinely serve "Madison + Milwaukee + surrounding" (today Milwaukee has ONE thin page vs Madison's 36+), give /wi a product-forward installation hub, and make both hubs the pages AI engines cite for local garage-door queries — while every visitor lands on a clean conversion path.

## Locked decisions (Daniel, 2026-07-10 — do not re-ask)

| Decision | Value |
|---|---|
| Product strategy | **Showcase, don't clone.** No /wi copies of the 23 Clopay collection pages. The 23-card grid embeds in the hubs; cards deep-link the /wi door builder. |
| /wi Madison/general phone | **(608) 420-2377** (GHL tracking number → forwards to real line 888-8785). Delete the blanket 888-8785→925-2038 swap script. Post-launch forward test required. |
| Milwaukee phone | **(414) 800-9271** (previously confirmed) |
| Madison NAP | **2921 Landmark Pl, Ste 206, Madison, WI 53713.** Fix the conflicting `620 N Carroll St` schema entry everywhere it appears. |
| Milwaukee NAP | 11220 W Burleigh St Ste 100, Wauwatosa, WI 53222 (previously confirmed) |
| Cost-FAQ data | **Computed from real HCP job data on jwrpj** (per-service price ranges). Daniel approves the computed ranges BEFORE publish. Never fabricated. |
| Reviews | **Real reviews band on both hubs** via the existing Business Reviews Bundle plugin (RichPlugins, already live: `/wi/reviews/` renders 100+ real Google reviews server-side). Embed the plugin shortcode inside a twx v2 band wrapper. Never sample text; reviews are business-wide (one Google profile), not per-city. QA: ensure we don't double-emit review/AggregateRating schema (plugin may already emit it). |
| WI license number | Still not provided. Footers ship without the line (no placeholder). |

## Discovery facts grounding this design (verified live 2026-07-10)

- /wi: 85 pages + 21 location posts. 47 service-intent, 15 Madison-area, **0 collection/product**, Milwaukee = 1 page (`garage-door-repair-in-milwaukee-wi`, id 6460) + 1 location post.
- Live /wi shows (608) 925-2038 to every visitor via an **unconditional** inline swap script (382 chars, script index 17 on the homepage): no utm/gclid/referrer/cookie logic, so there is NO channel attribution to preserve. Raw HTML/schema still carries 888-8785 ×29.
- No third-party call-tracking JS (CallRail etc.) loads on /wi.
- /wi JSON-LD publishes TWO conflicting Madison addresses (620 N Carroll St 53703 vs 2921 Landmark Pl 53713) — a live NAP-consistency bug.
- /wi already has an organic `garage-door-installation` page → overhaul it (keep URL equity), do not create a new slug.

## What ships

### 1. Milwaukee hub — overhaul `/wi/garage-door-repair-in-milwaukee-wi/` (page 6460)

Elevated from repair-only to full metro hub (repair + install + openers). Section stack (all twx v2 kit, calibrated twins sizing):

1. Textured hero — Milwaukee framing, (414) 800-9271, Book + Call dual CTA
2. Answer-first intro: self-contained 40–60 word paragraph naming Twins + services + Milwaukee/Wauwatosa (the AI-extractable passage)
3. Yellow trust ribbon
4. Services cards: Repair · Installation · Openers (query-shaped H2s)
5. 23-collection Clopay grid (showcase) → cards deep-link `/wi/door-builder/?product={id}`
6. What-to-expect 01-03 steps
7. Service-area band: Milwaukee, Wauwatosa + surrounding
8. Real reviews band — Business Reviews Bundle shortcode in a twx v2 wrapper
9. FAQ band (visible Q&A, 5–8 questions incl. cost ranges) + FAQPage schema
10. Book + Call closer
11. Full footer: Wauwatosa NAP, (414) 800-9271, no license line yet

### 2. Madison install hub — overhaul `/wi/garage-door-installation/`

Product-forward "New Garage Doors & Installation" hub:

1. Textured hero — (608) 420-2377, Book + Call dual CTA
2. Answer-first intro (Madison + surrounding)
3. Trust ribbon
4. 23-collection grid as centerpiece → builder deep-links
5. Door-builder CTA band
6. Financing band: GoodLeap + $0 service call (confirmed offers only)
7. What-to-expect 01-03 steps
8. Real reviews band — Business Reviews Bundle shortcode in a twx v2 wrapper
9. FAQ band (incl. "how much does a new garage door cost in Madison") + FAQPage schema
10. Closer
11. Full footer: Madison NAP (2921 Landmark Pl Ste 206), 420-2377

### 3. Supporting cleanup (all reversible, change-logged)

- **Grid port:** add `[clopay_collection_grid]` server-rendered grid to a /wi snippet (source: main snippet 7050 + Phase-4 Clopay snapshot). Cards link the /wi builder; secondary "view full collection" link to main's `/clopay-*/`.
- **Phone standardization:** delete the swap script from the /wi snippet that carries it; replace 888-8785/925-2038 across /wi templates+schema with 420-2377 (Madison/general) and 414 800-9271 (Milwaukee page). Snippet backups before edit.
- **Schema fix:** standardize every /wi LocalBusiness address on 2921 Landmark Pl Ste 206; remove 620 N Carroll St.
- **Freshness:** visible "Last updated: <date>" on both hubs.

### 4. Cost-FAQ computation (pre-build step)

Query jwrpj (`payroll_jobs` + line items) for completed real jobs by service category (spring replacement, opener install/replacement, new door install, service call/repair), split Madison vs Milwaukee where sample allows. Produce honest ranges (e.g. p25–p75, rounded). **Daniel approves the exact published ranges before any page goes live.** Full dollar amounts, no "$Xk".

## AI-SEO principles applied (from ai-seo skill)

- Lead with direct answers; key passages 40–60 words; H2s phrased as user queries
- Visible FAQ + FAQPage schema only where Q&A is on-page (goodgolly anti-pattern: invisible FAQ schema)
- Real statistics only (computed from Twins' own job data — original data, +37-40% citation odds); no fabricated stats/reviews/prices
- Single consistent NAP + phone per metro in text, tel: links, and JSON-LD
- Freshness date visible
- AI-crawlability already confirmed by 2026-07-07 baseline (no robots.txt work needed)

## Build approach

Proven Phase-4 pipeline per page: REST draft → Rank Math meta (nonce dance) → Elementor builder script → save/poll → deterministic JSON-LD widget re-add → Astra `site-post-title` meta LAST → publish → `qa-gate.py`. Builder + content pack in `docs/superpowers/backups/2026-07-09-phase4-catalog/`. One subagent batch (~180k tokens) for both pages; orchestrator commits (live-edit subagents never commit). All environment gotchas per handoff §Environment (WPCode toast verify, sha256 before/after, ≥6s request throttle, logged-in fetches only while BlogVault block stands, foreground fetch loops).

## Verification

- QA gate on both pages (logged-in; anonymous re-run blocked until Daniel whitelists the redacted machine IP at BlogVault)
- **420-2377 → 888-8785 forward test** (Daniel or a real dial) before workstream is called done
- Schema validation: exactly one address + one phone per page, FAQPage parses
- Mobile fit at 390px via same-origin iframe; final proof screenshots in main session
- Grep the rendered pages: zero 925-2038, zero 620 N Carroll, zero em-dashes in customer copy

## Out of scope (this session)

Chrome re-skin (workstream B) · zip finder / reviews page / standalone cost-FAQ pages (C) · LP bug fixes (D) · Milwaukee suburb/programmatic pages · /il, /ky · menu restructuring beyond repointing existing Milwaukee items if needed.

## Open items

1. WI license number (Daniel) — add to footers when provided
2. BlogVault IP whitelist (Daniel) — unlocks anonymous QA
3. Confirm 420-2377 forwarding is active in GHL before publish (verify, don't assume)

## Revert paths

Both pages: pre-overhaul Elementor JSON exported to `docs/superpowers/backups/2026-07-10-phase5-wi/` before editing; restore = re-import + republish. Snippets: before-files in same dir; restore = setValue(backup) + save. Schema/meta: recorded in change-log rows.
