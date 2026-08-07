# KPI trends: every KPI over time, companywide and per technician

**Date:** 2026-08-07
**Repo:** `twins-dash` (palpulla/twins-dash)
**Status:** Approved design, ready for implementation planning

## Problem

The dashboard answers "what is our conversion rate?" for a picked date range. It cannot answer "is our conversion rate getting better or worse?" The only trend surfaces today are:

- `RevenueTrendChart` on the Company Scorecard: revenue only, day/week/month.
- `TechTrendChart` in the tech portal: one tech, weekly only, four metrics.

`TechTrendChart` is worse than missing, because it **forks the KPI math**. Its data hook `src/hooks/admin/useTechWeeklyKpi.ts` sums `revenue_amount` directly instead of calling the canonical library, and it hardcodes `closing_pct` to `null` with the comment "needs opportunity dedup logic". A tech looking at their trend sees a different definition of revenue than the tile directly above it, and no closing-rate trend at all.

Daniel's ask: trends for every KPI we track, companywide and individual, over a picked timeframe, bucketed by day / week / month.

## Core insight

Every KPI in `src/lib/kpi-calculations.ts` is already a pure function of a job set:

```ts
calculateTotalConversionRate(jobs) -> number
calculateAvgSoldRepair(jobs)       -> { average, count, revenue }
```

A trend is therefore not new math. It is **the same function run once per time bucket**. Any design that writes new SQL or new aggregation for trends recreates the `useTechWeeklyKpi` bug at larger scale. This is non-negotiable: trends must be a re-slicing of the canonical library, never a reimplementation of it.

This also satisfies the standing rule that KPI math is immutable. Not one line of `kpi-calculations.ts` changes in this work.

## Architecture

Three new modules under `src/lib/trends/`, one new hook, one new component, plus edits to wire them in.

### 1. `src/lib/trends/kpi-registry.ts`

One declarative entry per trendable KPI. This is the single source of truth for what can be trended and how.

```ts
export type TrendUnit = 'usd' | 'pct' | 'count' | 'ratio';
export type DateBasis = 'completed_at' | 'scheduled_at' | 'spend_date' | 'call_date';
export type SourceKey = 'jobs' | 'calls' | 'marketing';

export interface TrendInputs {
  jobs: Job[];
  calls: Call[];
  marketingSpend: MarketingSpend[];
}

export interface TrendKpiDef {
  key: string;                 // stable id, matches KPI_INFO keys where one exists
  label: string;
  unit: TrendUnit;
  dateBasis: DateBasis;        // which timestamp puts a row in a bucket
  sources: SourceKey[];        // which tables the series needs
  needsHcpData: boolean;       // whether the fat hcp_data blob must be selected
  aggregate: 'sum' | 'rate';   // sum = bucket totals add to the period total
                               // rate = an average/percentage, never summed
  compute: (rows: TrendInputs) => number | null;   // null = no denominator
  denominator?: (rows: TrendInputs) => number;     // powers the tooltip's "n of m"
  goalKey?: string;            // company_goals key for the goal overlay
  perTech: boolean;
  higherIsBetter: boolean;
}
```

Example entries, showing that `compute` only ever delegates:

```ts
{ key: 'conversion', label: 'Conversion rate', unit: 'pct',
  dateBasis: 'scheduled_at', sources: ['jobs'], needsHcpData: true,
  aggregate: 'rate',
  compute: r => { const s = calculateConversionRate(r.jobs);
                  return s.total === 0 ? null : s.rate; },
  denominator: r => calculateConversionRate(r.jobs).total,
  goalKey: 'conversion_rate', perTech: true, higherIsBetter: true }

{ key: 'revenue', label: 'Revenue', unit: 'usd',
  dateBasis: 'completed_at', sources: ['jobs'], needsHcpData: false,
  aggregate: 'sum',
  compute: r => calculateTotalRevenue(r.jobs),
  goalKey: 'revenue_annual', perTech: true, higherIsBetter: true }
```

Three fields carry most of the risk and deserve care in review:

**`dateBasis`.** Getting this wrong silently shifts a whole series. `useDashboardData` already runs two separate queries for exactly this reason: revenue by `completed_at`, opportunity and conversion metrics by `scheduled_at`. The registry preserves that split per KPI rather than picking one date for everything. Estimates are a special case: `useDashboardData` includes an estimate row when **either** `hcp_data.created_at` **or** `scheduled_at` falls in range. The bucketer mirrors that with a resolver, `bucketDateFor(job, basis)`, that falls back to `hcp_data.created_at` for estimate rows with no `scheduled_at`, so a trend bucket contains the same rows the tile's range would have contained.

**`aggregate`.** A `rate` KPI with an empty bucket must render as a **gap**, never as zero. A quiet holiday week plotted as 0% conversion looks like a business collapse. `compute` returning `null` is the signal.

**`higherIsBetter`.** Callback rate and cancellation rate are inverted. The delta chip must go green when they fall.

### 2. `src/lib/trends/build-series.ts`

The engine. Pure, no React, unit-testable.

```ts
buildSeries(kpi: TrendKpiDef, rows: TrendInputs, opts: {
  granularity: 'day' | 'week' | 'month' | 'quarter';
  window: { from: Date; to: Date };
  techId?: string;
}): TrendPoint[]
```

Steps:

1. **Attribute to a tech** when `techId` is set, by reusing the existing credit logic rather than re-deriving it. `shouldCreditTechnician(job, techHcpId)` in `src/hooks/use-technician-data.ts` implements the Charles Solo Rule (a ticket where Charles is co-assigned credits to the *other* tech, for all purposes). That function and the `TECH_HCP_BY_UUID` map move to `src/lib/trends/attribution.ts`, and `use-technician-data.ts` imports them from there. Behavior is unchanged; the point is that exactly one implementation exists, so the trend and the tile can never disagree about whose ticket it is.

   Note the asymmetric fetch this implies: crediting a junior tech correctly needs Charles's co-assigned tickets in the row set, which `use-technician-data.ts` handles today with a supplementary query (`fetchAllJobsForTech`, line ~229). The trend fetcher pulls all techs' rows for the window anyway, so the supplementary query is unnecessary here; `shouldCreditTechnician` filters the full set.

2. **Bucket** every row by `bucketDateFor(row, kpi.dateBasis)` into a dense series of buckets spanning the window, so empty buckets exist and are visible as gaps rather than being silently dropped.

3. **Compute** `kpi.compute(bucketSubset)` per bucket, plus `kpi.denominator` for the tooltip.

4. **Emit** `{ bucketStart: string, label: string, value: number | null, n: number }`.

Sample size is surfaced, never enforced. Points keep their real value regardless of how small `n` is, and the tooltip reads "37 of 50 opportunities sold" so the reader can judge for themselves. Nothing is hidden, greyed, or filtered out on the basis of `n`.

### 3. `src/lib/trends/granularity.ts`

Bucket helpers plus the auto-default: window ≤ 45 days → day, ≤ 400 days → week, otherwise month. Quarter is never auto-selected and is only ever reached by manual choice. Manual override persists via `useLocalStorageString`, the same pattern `RevenueTrendChart` already uses.

**Weeks are Monday-aligned** (`weekStartsOn: 1`), matching `RevenueTrendChart` and `TechTrendChart`. They are deliberately **not** the Friday-to-Thursday payroll week. Payroll weeks are Fri–Thu and that is load-bearing for payroll only; a KPI trend is not a pay period. This is written down here so a future session does not "fix" it.

### 4. `src/hooks/use-trend-data.ts`

One React Query hook feeding the engine.

```ts
useTrendData({ window, sources, needsHcpData })
```

- Query key `['trend-rows', from, to, sources, needsHcpData]`, so switching KPIs inside the same window with the same data needs is a cache hit and costs zero network.
- Column selection is narrow by default and only adds `hcp_data` when the registry says the KPI needs it. `hcp_data` is the fat JSONB blob and is genuinely required for options per ticket, callback tags, appointment/opportunity dedup by customer id, and membership detection, so it cannot be dropped wholesale.
- Batched at 1000 rows with the same 10k ceiling `useDashboardData` uses.

**Measure before building on this.** A 12-month window is roughly 4,000 to 5,000 job rows, which fits inside the existing cap, but the payload with `hcp_data` included is unverified. Task 1 of the implementation plan is to measure real row count and transfer size for a 12-month window with and without `hcp_data`, and to confirm the narrow select is sufficient. If the fat window is too slow, the fallback is a server-side **row projection** (a view exposing the handful of `hcp_data` fields the canonical functions actually read: tags, options count, customer id, assigned employees) rather than server-side KPI aggregation. Projecting rows keeps the math client-side and canonical; aggregating server-side would recreate the fork.

### 5. `src/components/dashboard/KpiTrendChart.tsx`

Recharts, following the house convention already used across the app: `NAVY = "#0E2148"` / `GOLD = "#F7B801"`, `CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0"`, axis ticks `{ fontSize: 11, fill: "#64748B" }` with `tickLine`/`axisLine` off, tooltip on white with `1px solid #E2E8F0` and `borderRadius: 8`.

Chart type follows `aggregate`: bars for `sum` KPIs (matching `RevenueTrendChart` today), lines for `rate` KPIs, because a rate is a level and not a quantity.

Currency axis ticks may abbreviate to `$40k`. Every tooltip, tile, and label shows the full amount (`$5,243`, no cents). Chart ticks are the standing exception to the no-abbreviation rule; nothing else is.

## The compare picker

Up to three overlays on one chart. Only **goal** is on by default.

| Overlay | Behavior |
|---|---|
| Goal | `company_goals` value via `useCompanyGoals`, scaled to the bucket (annual ÷ 52 for weekly, ÷ 12 for monthly). Shown only when the registry entry has a `goalKey`. |
| Same period last year | Window offset by 364 days, so weekday alignment holds. Runs the same `buildSeries` on last year's rows. |
| Previous period | The equal-length window immediately preceding. |
| Company average | On an individual's chart, the companywide series for the same KPI. |
| Another technician | Any tech, or all techs at once as thin series. |
| A second KPI | Rendered on a right-hand axis, e.g. avg ticket against closing %. |
| Marketing spend | Right-hand axis, context for lead-driven KPIs. |

Two rules keep this from becoming unreadable: a hard cap of three overlays, and a second Y axis appearing only when the overlay's unit differs from the primary's. Primary series is solid navy; overlays are dashed and colour-ranked gold, slate, then muted navy.

## Placement and interaction

No new nav item and no new icon on any tile. Clicking anywhere on a KPI tile opens the existing drill-down sheet, now tabbed:

- **Trend** tab, first and default, available for every KPI in the registry.
- **Jobs** tab, second, rendered only for the four KPIs `useDrilldownJobs` supports today (`revenue`, `closing`, `callback`, `membership`). Hidden entirely for the rest.

This preserves the current revenue-tile behavior at the cost of one extra click, and adds zero visual clutter to a scorecard Daniel has already said should not get busier.

The trend window is **independent of the page's date filter**. A 30-day filter on the scorecard with a 12-month trend in the sheet is the normal case, not an edge case.

Surfaces wired up:

- **Company Scorecard** (`src/pages/Index.tsx`): the hand-written `<div className="kpi">` tiles get a click handler, `cursor-pointer`, `role="button"`, `tabIndex`, and Enter/Space key handling. The existing revenue tile's `onClick` changes from "open job drill-down" to "open sheet on Trend tab".
- **Tech portal** (`src/pages/tech/Home.tsx`): `KpiTile` is already a `<button>` with an `onClick` opening `KpiDrillSheet`. That sheet gains the same Trend tab, keeping its existing tier-ladder and improvement-tip content as a third tab.
- **`TechTrendChart`** is rebuilt on `buildSeries` and `useTechWeeklyKpi` is deleted. This is what makes per-tech closing % work for the first time, and it is the main reason to build a shared engine rather than a second chart.

## KPI catalog

**Jobs-sourced, companywide and per technician** (canonical function in parentheses):

| KPI | Function | Basis | Aggregate |
|---|---|---|---|
| Revenue | `calculateTotalRevenue` | `completed_at` | sum |
| Opportunity job average | `calculateOpportunityJobAverage` | `scheduled_at` | rate |
| Total job average | `calculateTotalJobAverage` | `scheduled_at` | rate |
| Avg sold repair | `calculateAvgSoldRepair` | `scheduled_at` | rate |
| Avg sold install | `calculateAvgSoldInstall` | `scheduled_at` | rate |
| Conversion rate | `calculateTotalConversionRate` | `scheduled_at` | rate |
| Closing % | `calculateClosingStats` | `scheduled_at` | rate |
| Estimate close % | `calculateEstimateConversionRate` | `scheduled_at` | rate |
| Membership rate | `calculateMembershipConversionRate` | `scheduled_at` | rate |
| Callback rate | `calculateCallbackRate` | `scheduled_at` | rate |
| Cancellation rate | `calculateCancellationRate` | `scheduled_at` | rate |
| Options per ticket | `calculateAvgOptionsPerTicket` | `scheduled_at` | rate |
| Opportunities | `getUniqueOpportunities().length` | `scheduled_at` | sum |
| Appointments | `countAppointments` | `scheduled_at` | sum |
| Completed jobs | row count | `completed_at` | sum |
| Cancellations | `getOpportunitiesIncludingCanceled().canceled` | `scheduled_at` | sum |

The per-tech subset maps cleanly onto the eight `scorecard_tier_thresholds` keys already driving the tech portal grid (`revenue`, `total_jobs`, `avg_opportunity`, `avg_repair`, `avg_install`, `closing_pct`, `callback_pct`, `membership_pct`) plus `options_per_ticket`, so tier badges and trends speak the same language.

Note that `calculateAvgOptionsPerTicketForTech` takes a second argument (all jobs, not just credited ones), so its registry entry receives the unfiltered set alongside the credited one.

**Companywide only**, no tech attribution available:

| KPI | Function | Source |
|---|---|---|
| Call booking rate | `calculateCallBookingRate` | `calls_inbound` |
| Marketing spend | sum of `spend_amount` | `marketing_spend` |
| Marketing leads | sum of `leads_generated` | `marketing_spend` |
| CPA | `calculateCPA` | `marketing_spend` + `jobs` |

Anything with a KPI tile gets a trend. Anything without a canonical function does not get one invented for it. `leads_generated` counts form leads only, so the marketing-leads series reads low for call and messaging campaigns; its tooltip says so rather than pretending otherwise.

## Testing

`buildSeries` and the registry are pure functions, so they get real unit tests in `src/lib/trends/__tests__/`:

- Bucketing: a job on a bucket boundary lands in exactly one bucket; a rate KPI with an empty bucket yields `null`, not `0`.
- Definitional agreement, the test that matters most: for a given window, `buildSeries` over a **single** bucket spanning the whole window must equal the tile's value from the canonical function on the same rows, for every registry entry. This is the regression guard that stops a second `useTechWeeklyKpi` from ever appearing.
- Attribution: a ticket with Charles co-assigned credits to the other tech in the trend, matching `use-technician-data.ts`.
- `dateBasis`: an estimate row with no `scheduled_at` but an in-range `hcp_data.created_at` lands in the bucket the tile would have counted it in.

## Out of scope

Stated plainly rather than half-built:

- Trends split by marketing source or channel.
- Trends split by CSR or dispatcher.
- Trend affordances on Leaderboard rows.
- Google review count and rating trends.
- Server-side aggregation of any kind.

## Risks

- **Payload size on a 12-month window with `hcp_data`.** Unverified. Task 1 measures it; the projection-view fallback is described above and keeps the math client-side either way.
- **Chart busyness.** Daniel rejects cluttered UI. Mitigated by defaulting to goal-only, capping overlays at three, and adding no new tile chrome.
- **`Index.tsx` is 998 lines** and gains ~15 tile click handlers. The handlers are one-liners delegating to a single sheet-state hook, so this adds no branching logic, but the file is already too large and this is not the change that fixes that.
