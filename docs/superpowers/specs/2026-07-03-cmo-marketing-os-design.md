# Twins Marketing OS — CMO Operating Model

**Date:** 2026-07-03
**Status:** Approved direction (Option A: engine first, strategy light)
**Owner:** Daniel (approval authority) / Claude (CMO execution)

## Problem

Daniel asked Claude to act as CMO for Twins Garage Doors: own marketing strategy and implementation. The scope is too broad for a single build. Twins already has substantial marketing infrastructure (content engine, media generator, ROI page, review-card tracking, GHL messaging pipeline, LSA); the missing piece is not another tool but a consistent decision loop that looks at real numbers weekly and acts on them.

## Decisions captured during brainstorm

- **90-day goal:** balanced portfolio — demand capture, compounding assets, and existing-base monetization all matter ("all").
- **Budget:** flexible / ROI-driven. No fixed cap; spend scales with what the ROI page proves profitable. New spend starts with conservative test caps.
- **Authority:** organic content (social, blog/SEO, GBP posts) publishes without per-item review. Ad-spend changes, new campaign launches, and customer-facing messaging changes always go to Daniel as proposals first.
- **Cadence:** weekly Monday brief in chat. No email/SMS/push pings (standing preference).

## Design

### 1. Role and authority matrix

| Action | Autonomy |
|---|---|
| Organic content (social, blog, GBP) | Auto-publish on schedule, brand rules enforced |
| Pause/adjust live ad campaigns | Propose in brief, Daniel approves |
| Launch new campaign / channel / offer | Propose with mini-plan, Daniel approves |
| Customer-facing messaging (GHL SMS/email copy) | Propose, Daniel approves |
| Spend money anywhere | Never without explicit approval |

Standing guardrails (from established preferences): all changes reversible; never fabricate operational data (prices, phone numbers, names — ask or ship empty); real Twins brand/contact info only; full dollar amounts ($5,243, never $5k); no Lovable references; no automated alert pings — the brief lives in chat.

### 2. Strategy one-pager (`docs/marketing/STRATEGY.md`)

A living document, corrected by ROI data, containing:
- **Positioning:** family-owned twin-brothers brand, Madison WI, real techs (Charles, Maurice, Nicholas bios exist), fast honest service.
- **Confirmed offers:** $0 service call, $49 tune-up, GoodLeap financing. Never promise same-day.
- **Segments:** emergency repair (high intent, capture channels), planned replacement (consideration, financing + estimates follow-up), maintenance (tune-up funnel, base).
- **Channel ranking by proven ROI**, updated from the weekly brief, not from theory.

### 3. Portfolio structure — three buckets

- **Capture (buy jobs now):** LSA (live, wired into ROI page), Google Ads (pilot candidate), Meta Ads (MCP access available), GBP.
- **Compound (assets that keep producing):** content engine weekly output, AI-search visibility, review-card flow (live), referrals.
- **Base (earn more per customer):** GHL messaging Phase 1 (approved spec), open-estimates follow-up tracker (spec exists), $49 tune-up funnel.

Budget rule: every new paid initiative gets a proposed test cap and a kill/scale criterion before launch. Winners scale; losers die at the cap.

### 4. Operating rhythm

**Weekly Monday brief (in chat):**
1. What ran last week (content published, campaigns live, sends).
2. Spend by channel.
3. Booked jobs + revenue by source (jwrpj ROI data; earned-revenue rules apply — outstanding_balance == 0).
4. Review velocity and content output.
5. 2–3 proposed moves with cost and expected effect; Daniel approves/rejects in one message.

Delivery mechanism: scheduled Monday run that prepares the brief. If scheduled/headless runs can't reach the data (MCP auth limits), fallback is a repo skill/command Daniel or a local cron triggers. Week-1 brief doubles as the baseline audit (mini-audit folded in, per Option A).

**Monthly:** deeper pass — strategy one-pager revision, channel ranking update, backlog re-rank.

### 5. Initiative pipeline (`docs/marketing/BACKLOG.md`)

Ranked backlog; every initiative above the line gets the normal spec → plan → build cycle. Seed entries (already in flight or specced):
1. Finish $49 tune-up avatar ad (build in progress).
2. Launch GHL customer messaging Phase 1 (approved v3.1 spec).
3. ROI-page attribution gaps (GHL attribution, funnel booked semantics, GA4 sync — existing polish backlog).
4. Google Ads pilot (new; needs proposal + test cap).
5. Open estimates CSR tracker (spec + plan exist, not built).
6. Meta ads pilot using media-generator creative (new; needs proposal + test cap).

### 6. Data and measurement

Source of truth is the Marketing ROI page / jwrpj tables. Known gaps carried as backlog items, not blockers. KPI math is immutable; the brief reads existing calculations, never reinvents them. No new heuristic classifiers for attribution — if source data lacks a structured tag, the brief shows "unattributed" rather than guessing.

## Alternatives considered

- **Full strategy first** (multi-session comprehensive plan before execution): rejected — delays action weeks; first month of real data would rewrite the guesses.
- **Audit first** (deep channel audit before strategy): rejected as a separate phase — the week-1 baseline brief performs the useful audit inside the operating rhythm.

## Implementation scope (first cycle)

1. Commit this spec.
2. Write `docs/marketing/STRATEGY.md` seeded with real baseline data pulled from jwrpj.
3. Write `docs/marketing/BACKLOG.md` with the seed ranking above.
4. Produce the Week-1 baseline brief (the mini-audit) and deliver it in chat.
5. Stand up the Monday cadence (scheduled run; fallback command if headless data access fails).

Out of scope for this cycle: launching any new paid campaign, changing any customer-facing message, building dashboard UI. Those arrive as proposals through the brief.

## Success criteria

- Daniel gets a Monday brief with real numbers and concrete proposals, every week, without asking for it.
- Every marketing dollar traceable to a decision recorded in a brief.
- Backlog items flow through spec → plan → build with no orphaned half-projects.
