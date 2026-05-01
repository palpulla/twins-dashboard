# Marketing Source ROI — Revamp Design

**Status:** Draft, ready for plan
**Author:** Claude (with Daniel)
**Date:** 2026-05-01
**Repo:** `twins-dash` (palpulla/twins-dash)
**Live page:** `/marketing-roi` (`twins-dash/src/pages/MarketingSourceROI.tsx`)

## Problem

The current Marketing Source ROI page has the right data underneath but the wrong shape. It opens with a single hero number (revenue) and a flat strip of seven equal-weight metric cards, then a two-tab bar chart, then a long sortable table. For an owner-operator opening the page on a phone, three things are missing:

1. **No fast-glance answer.** The page does not say in one second whether marketing is working, behind, or on track.
2. **Channels are not first-class.** Google Ads, Meta Ads, GHL calls, and organic traffic each carry different decisions (raise spend, kill ad set, follow up faster, write a blog post), but they share the same generic table row treatment.
3. **Live data is not visible.** GHL is dropping every inbound call into `public.calls_inbound` and every opportunity into `public.jobs`, but neither table surfaces here. The page looks static when the underlying data is real-time.

Two more APIs that Daniel has access to (GA4, Google LSA) are also unwired. Yelp, Thumbtack, and GBP can come later.

## Goal

Revamp `MarketingSourceROI.tsx` into a five-section page that scales from a 3-second glance to a 3-minute audit, on mobile and desktop. Phase 1 ships with the data already in Supabase. Phases 2–4 wire new APIs as drop-in channel cards.

## Non-goals

- **No KPI math changes.** Hero, channel cards, and the existing table must all reconcile to the same per-source aggregation. The numbers visible today must be exactly the numbers visible after this revamp on the same date range.
- **No new authentication or role logic.** The page audience is owner-operator (Daniel) only. Tech and supervisor cuts of marketing data are out of scope.
- **No GBP / reviews work.** That has its own spec (`2026-04-29-reviews-ingestion-design.md`) and surfaces on the Wins tab, not here.
- **No call-tracking purchase or replacement.** GHL is the source of truth for inbound calls; we surface what is already coming in via webhook.
- **No campaign-level editing.** Daniel adjusts campaigns inside Google / Meta consoles. The dashboard is read-only.

## Architecture

### Page layout

Five sections, top to bottom:

1. **Filter bar** — segmented period toggle (Today / WTD / MTD / QTD / YTD / Custom), job-type select, technician select, export menu. State persists in URL query string so bookmarks and shared links survive a reload.
2. **Stoplight hero** — three navy-yellow tiles using the existing `HeroScoreboard` visual language:
   - **ROI tile** — revenue / spend, vs. ROI goal, delta vs. prior period
   - **Pacing tile** — revenue vs. monthly goal, day-of-period, required-daily-rate to hit goal
   - **Live tile** — today's call count, booked count, median speed-to-lead, last-call timestamp
3. **Channel scorecards grid** — one card per channel. Two columns on desktop, one on mobile. Top performers carry gold/silver/bronze ribbons (TOP ROI / RISING / VOLUME LEAD). One unwired-channel placeholder card ("Connect Google LSA") ships in Phase 1 to signal where the next channel lands; Yelp/Thumbtack placeholders are added in Phase 4 when those channels are imminent. Phase 1 grid: Google Ads, Meta Ads, GHL, Organic, GA4-placeholder, LSA-placeholder = 6 cards.
4. **Funnel + Live feed** — two-column panel:
   - **Lead funnel** — Calls → Leads → Booked → Completed → Paid, stacked by canonical source, with conversion percentage per step. Drop-off alerts call out any step where a channel performs >20% below the average.
   - **Live feed** — last 20 inbound calls/forms from `calls_inbound`, with source pill, time, and a green tint on rows that became booked jobs.
5. **All-sources table** — the existing `MarketingSourceTable`, kept as-is for the long tail. Renamed section heading and lightly restyled to match the new card aesthetic.

### Data sources & phasing

| Phase | Channels surfaced | New wiring required |
|---|---|---|
| **1 (this spec)** | Google Ads, Meta Ads, GHL Calls, Organic (via existing `lead_source` on `jobs`) | None — refactor only. All data is already in Supabase. |
| **2 (separate spec)** | GA4 Organic / Direct | New `sync-ga4` edge function + `ga4_daily_metrics` table + pg_cron |
| **3 (separate spec)** | Google LSA | New `sync-google-lsa` edge function + `lsa_leads` table + pg_cron |
| **4 (later)** | Yelp, Thumbtack | One edge function per platform, each drops in as a new channel card |

**Phase 1 ships standalone.** Phases 2–4 each get their own design doc and PR. The channel grid is built so each new channel adds a card without disturbing siblings.

### New components

| File | Action |
|---|---|
| `src/pages/MarketingSourceROI.tsx` | Modify — becomes a thin composition of the new components |
| `src/pages/MarketingSourceROIv1.tsx` | Create — verbatim copy of the current page, gated behind `?legacy=1` for the first 30 days post-deploy, then deleted |
| `src/components/marketing-roi/MarketingStoplightHero.tsx` | Create — three-tile navy-yellow hero |
| `src/components/marketing-roi/ChannelScorecardCard.tsx` | Create — channel card (logo, KPI row, ROI bar, sparkline, ribbon, footer) |
| `src/components/marketing-roi/ChannelScorecardConnect.tsx` | Create — "Connect Google LSA / Yelp / Thumbtack" placeholder card |
| `src/components/marketing-roi/LeadFunnelPanel.tsx` | Create — funnel viz + drop-off alerts |
| `src/components/marketing-roi/LiveLeadFeed.tsx` | Create — feed of recent `calls_inbound` rows |
| `src/components/marketing-roi/MarketingSourceTable.tsx` | Modify — small style refresh only; logic unchanged |
| `src/hooks/use-marketing-source-roi.ts` | Modify — extend to return per-channel sparkline series and ribbon ranking |
| `src/hooks/use-stoplight-metrics.ts` | Create — composes goal data + revenue + spend + live counts |
| `src/hooks/use-lead-funnel.ts` | Create — computes funnel steps stacked by source |
| `src/hooks/use-live-lead-feed.ts` | Create — reads `calls_inbound` for the live feed |
| `src/lib/marketing-roi/ribbon-ranking.ts` | Create — pure function: given channel metrics, returns gold/silver/bronze assignments and the criterion (TOP ROI / RISING / VOLUME LEAD) |
| `src/lib/marketing-roi/drop-off-alerts.ts` | Create — pure function: given funnel data, returns alert rows |
| `src/lib/marketing-roi/url-state.ts` | Create — encodes/decodes filter state to URL query |

13 files. Each one is small and focused.

### Data flow (Phase 1)

**ROI tile**

- Revenue numerator: `sum(jobs.revenue)` over the selected period, jobs where `status in ('completed','paid')`. Reuses the existing aggregation in `useMarketingSourceROI`.
- Spend denominator: `sum(marketing_spend.spend_amount)` over the selected period. Reuses existing query.
- Goal: `getGoal('roi_target')` from `useCompanyGoals`, default 3.0× when unset. New goal key.
- Delta: same calc against the prior period (using existing `priorPeriodAsDateRange` helper).
- Tone: green if >= goal, amber if 0.8×–1.0× of goal, red below 0.8×.

**Pacing tile**

- Numerator: same revenue as ROI tile.
- Goal: `getGoal('revenue')` × period-length-fraction. For MTD, that is `monthly_goal × (days_elapsed / days_in_month)`.
- Required daily rate: `(goal − revenue_so_far) / days_remaining`.
- Running daily rate: `revenue_so_far / days_elapsed`.
- Tone: green if running >= required, amber if within 10% short, red if more than 10% short.

**Live tile**

- Calls today: `count(calls_inbound where date = current_date)`.
- Booked today: `count(calls_inbound where date = current_date and is_booked = true)`.
- Median speed-to-lead: extracted from `calls_inbound` if a `responded_at` column exists; otherwise omit and we add the column in a follow-up. **Open question** flagged below.
- Last call timestamp: `max(calls_inbound.created_at)` for today.
- Tone: green when calls > 0 in last 2 hours, amber when 2–4 hours, red when no inbound for >4 hours during business hours.

**Channel cards**

- Source aggregation: existing `useMarketingSourceROI` already produces `MarketingSourceMetrics[]` per canonical source. We extend it with a daily time-series for the sparkline (revenue and spend per day, last 14 days within the selected window).
- Ribbon ranking: `ribbon-ranking.ts` takes the array, ranks by ROI (gold = highest ROI with adSpend > $500), Rising (silver = highest period-over-period revenue growth), Volume Lead (bronze = highest lead/call count). At most one ribbon per card; no card gets two.
- KPI row per card differs by channel:
  - Google Ads / Meta Ads / LSA: Spend / Leads / Revenue
  - GHL: Calls / Booked / Revenue
  - Organic / GA4: Sessions / Form fills / Revenue (Sessions and Form fills are placeholders showing "—" until Phase 2)
- ROI bar: width = clamp(ROI / goal, 0, 1) × 100%. Color: good >= goal, ok 0.6×–1.0× of goal, poor below 0.6×.
- Sparkline: yellow line = revenue, dim navy line = spend.
- Footer: cost-per-lead and close rate (or speed-to-lead for GHL, top page for GA4).

**Funnel**

```
Calls         = count(calls_inbound, period, group by canonical_source)
Leads         = count(unique opportunities from useMarketingSourceROI)
Booked        = leads where scheduled_at is not null
Completed     = leads where status in ('completed','paid')
Paid          = leads where revenue > 0
```

Each step is rendered as a stacked bar segmented by source. Conversion percentage shown beneath each step.

**Drop-off alerts**

For each (channel, step), compute the channel's conversion ratio at that step. If it is more than 20 percentage points below the all-channel average for that step **and** the channel has at least 10 leads in the period, surface a red alert row: `"<Channel> <step> is <X>% (vs <Y>% avg). <one-line action hint>."`

**Live feed**

Reads the latest 20 rows from `calls_inbound` ordered by `date desc, created_at desc`. Joins to `jobs` on `phone_number` to detect booked status. Each row shows: time, masked phone or form-icon, short job-type label (parsed from `notes` or job opportunity title where available), source pill. Booked rows tinted green.

### Filter state via URL

Filter values are encoded in the query string so a reload preserves state and a copied URL shares the same view:

```
?period=mtd&from=2026-05-01&to=2026-05-31&jobType=all&tech=all
```

The `period` token controls the segmented control. When `period=custom`, `from` and `to` are honored. Otherwise `from`/`to` are derived from `period`. Default state has no query string and resolves to MTD.

### Visual language

- Hero tiles use the existing `HeroScoreboard` gradient (`linear-gradient(135deg, #0f1d4d 0%, #1e3a8a 100%)`) and yellow glow accent. Same family, three instances side by side.
- Channel cards use the existing card aesthetic from `MetricCard` and the payroll/scorecard pages: white background, `rounded-2xl`, soft shadow, navy text, yellow accents.
- Ribbon colors: gold `linear-gradient(90deg,#e8a900,#f7b801)`, silver `linear-gradient(90deg,#8a93a8,#b6bfd2)`, bronze `linear-gradient(90deg,#a35a23,#d18248)`.
- Funnel uses a navy-to-gold ramp matching the brand: navy gradients for top steps, yellow for the bottom (paid) step to anchor the eye on revenue.

### Mobile

- Hero tiles stack vertically (1 column).
- Channel cards stack vertically (1 column).
- Funnel keeps its horizontal shape but compresses bar widths; numbers stay readable down to ~360 px.
- Live feed and source table scroll independently; no horizontal page scroll.

### Goals (Supabase `company_goals` table)

Reuses the existing `useCompanyGoals` hook. Two new goal keys are added (no schema migration; `company_goals` is a key/value table):

- `roi_target` — default 3.0 (number, ratio)
- `marketing_live_calls_per_day` — default 8 (number, count) — used for the Live tile's "calls today" tone

Daniel can edit these in the existing admin `company_goals` UI; if not yet wired there, the defaults above kick in.

## Reversibility

- Single feature branch off `main`. One PR, reviewable.
- Old page lives at `src/pages/MarketingSourceROIv1.tsx` and is reachable via `?legacy=1` for the first 30 days post-deploy. After 30 days of no rollbacks, it is deleted in a follow-up PR.
- No destructive migrations. Phase 1 has zero schema changes — all data sources already exist.
- No KPI math changes. The new hero ROI value, summed channel-card revenue, and the totals row of the existing source table must all reconcile to the same number on the same date range. An assertion test enforces this.
- Goals additions are key/value rows; deleting them reverts to the hard-coded defaults.

## Testing

### Unit tests

- `useStoplightMetrics` — 6 tests: green/amber/red ROI tile, green/amber/red Pacing tile, edge case where spend is 0 (no division by zero), edge case where prior period is empty (no delta shown), MTD vs Custom date arithmetic, goal missing (uses default).
- `useLeadFunnel` — 4 tests: full funnel with realistic data, empty period, single-source filter, conversion math accuracy.
- `useLiveLeadFeed` — 3 tests: no calls today (empty state), 5 calls with 2 booked, source pill resolution.
- `ribbon-ranking.ts` — 4 tests: clear winner per category, tie-break, channel below thresholds gets no ribbon, single-channel scenario.
- `drop-off-alerts.ts` — 3 tests: alert fires on >20pp gap with sufficient sample, no alert on small sample, no alert when channel matches average.
- `url-state.ts` — 3 tests: encode/decode round trip for each period, custom range encoding, malformed query falls back to default.

### Component tests (vitest + @testing-library/react)

- `MarketingStoplightHero` — renders three tiles with correct tones; delta pill visible when prior period present.
- `ChannelScorecardCard` — renders all KPI variants (Ads / GHL / Organic); ribbon visible when assigned.
- `ChannelScorecardConnect` — renders placeholder copy and CTA.
- `LeadFunnelPanel` — renders 5 steps with stacked sources and conversion percentages; drop-off alerts render.
- `LiveLeadFeed` — renders 5 rows with correct source pills; booked rows tinted.

### Integration tests

- Full-page render with seed data: hero ROI = sum(channel cards revenue) / sum(channel cards spend), to within 1 cent. This is the math-reconciliation guard.
- Filter interaction: changing the period control updates the URL query and refetches all hooks.
- Legacy gate: visiting `/marketing-roi?legacy=1` renders the old page byte-for-byte.

### Manual smoke (post-merge, on twinsdash.com)

1. Open `/marketing-roi`. Verify the stoplight hero renders within 1 second on Daniel's iPhone.
2. Toggle period through Today / WTD / MTD / Custom. Verify all sections refresh and the URL updates.
3. Compare hero ROI value against `/marketing-roi?legacy=1` on the same date range. They must match to within rounding.
4. Trigger a test inbound on the GHL phone line. Verify it appears in the Live feed within 60 seconds (subject to GHL webhook lag).
5. Resize browser to 375 px wide. Verify nothing horizontally scrolls and all hero/card numbers remain legible.

## Open questions

1. **Speed-to-lead column.** The Live tile shows "median response 4m 12s" in the mockup. `calls_inbound` may not have a `responded_at` column; if not, we either add it (requires migration + GHL webhook update to log when a tech opens the lead) or drop that line from the Live tile in Phase 1. **Plan recommendation:** drop it from Phase 1, file a follow-up to add the column once Daniel confirms which GHL event represents "responded."
2. **Top-page-by-source for GA4 card placeholder.** The mockup shows `top page: /madison-spring-replacement` for the Organic card. Without GA4 wired, we cannot show a real top page. **Plan recommendation:** Phase 1 shows a static "GA4 not connected" link instead; real top-page lights up in Phase 2.
3. **Drop-off alert action hints.** The mockup hard-codes "Likely lower-intent traffic. Consider tightening targeting." for Meta. We can either ship hand-written hints per (channel, step) pair or omit hints in Phase 1. **Plan recommendation:** ship 4 hand-written hints (Meta lead→book, Google lead→book, GHL booked→completed, any channel completed→paid). 4 hints covers the realistic alert surface.

## Phasing summary

| Phase | Scope | Ships when |
|---|---|---|
| 1 | This spec — refactor existing data into new layout | After plan approval |
| 2 | GA4 sync + GA4 card | Separate spec, after Phase 1 stable for ~1 week |
| 3 | Google LSA sync + LSA card | Separate spec |
| 4 | Yelp / Thumbtack / additional directories | One spec per platform, lowest priority |

Phase 1 alone delivers the visual revamp, the stoplight hero, the live feed, the funnel, and the drop-off alerts using only data that is already flowing into Supabase today.
