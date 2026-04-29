# Wins Tab v2: Rate-KPI Records (Closing % / Memberships % / Callbacks %)

**Status:** Draft, auto-approving per Daniel's "do both" + "whatever you recommend"
**Author:** Claude (with Daniel)
**Date:** 2026-04-29

## Problem

The Wins tab v1 (PR #44) shipped single-job records + week/month/quarter records for revenue / total_jobs / avg_opportunity. The original ask included rate-KPI records (best closing %, best memberships %, lowest callbacks %) per period — those were deferred because the original `compute_streaks_and_prs()` migration noted the formulas weren't yet exposed at the DB layer.

Reality check: the source data IS available. Closing %, callbacks %, memberships % are all derivable from the `jobs` table (HCP-sourced fields: `revenue_amount`, `is_callback`, `membership_attached`). The compute function loops over `payroll_jobs` — a join `payroll_jobs.hcp_id = jobs.job_id` (same join used in the jobs-parts-merge view) gives access to those fields.

So v2 closes the gap: 9 new records per tech (3 rate KPIs × 3 periods).

## Goal

For every active tech, compute and store these "best-ever" records:

- **closing_pct** (higher is better): week / month / quarter
- **membership_pct** (higher is better): week / month / quarter
- **callback_pct** (lower is better): week / month / quarter

Surface them on the existing `/tech/wins` page in the existing Best Week/Month/Quarter sections (no new sections, just more tiles).

## Non-Goals

- **No new period buckets.** Reuses the existing `'week'`, `'month'`, `'quarter'` periods from v1.
- **No new section headers on the Wins UI.** The 3 new tiles per period slot into existing `Recognition.tsx` sections.
- **No `single_job` rate KPIs.** Rate KPIs only make sense over a population, not a single job.
- **No "Twins records" section** (company-level aggregates). That's the Reviews-ingestion spec's territory.
- **No rewrite of `kpi-calculations.ts`** (the client-side formula source). The SQL is a faithful port of the same formulas, but lives independently. If the formulas drift, both must update.

## Architecture

### Where each KPI's source data lives

| KPI | Source field | Source table | Filter |
|---|---|---|---|
| closing_pct | `revenue_amount > 0` | `jobs` | `type = 'job'` (excludes Estimates) |
| membership_pct | `membership_attached = true` | `jobs` | opportunities only (`status = 'completed'` AND `revenue_amount IS NOT NULL`) |
| callback_pct | `is_callback = true` | `jobs` | same opportunities filter |

The `payroll_jobs` table doesn't carry `is_callback` or `membership_attached` — those are HCP-side fields living only on `jobs`. The join `payroll_jobs.hcp_id = jobs.job_id` (UUID slug match) is the bridge. Same join already used in the `v_jobs_with_parts` view shipped in PR #45.

### Faithful-port note

The client-side formulas (`src/lib/kpi-calculations.ts`) use `getUniqueOpportunities(jobs)` to dedupe before counting. Looking at that function: it keys by `job_id` (HCP UUID) and filters `is_opportunity = true`, then keeps one row per opportunity (the latest by `created_at` if multiple).

For the SQL port, we replicate this with:

```sql
WITH unique_opps AS (
  SELECT DISTINCT ON (j.job_id)
    j.job_id, j.tech_id, j.completed_at, j.revenue_amount, j.is_callback,
    j.membership_attached, j.type, j.is_opportunity, j.status, pj.owner_tech, pj.run_id
  FROM public.jobs j
  JOIN public.payroll_jobs pj ON pj.hcp_id = j.job_id
  WHERE j.is_opportunity = true
    AND j.status = 'completed'
  ORDER BY j.job_id, j.created_at DESC
)
SELECT ... FROM unique_opps WHERE ...
```

### Compute function extension

Extend `compute_streaks_and_prs()` (in `20260425170200_compute_streaks_and_prs.sql` + the v1 extension `20260429180000_wins_tab_expansion.sql`) with 9 new compute blocks per tech: one per (rate_kpi × period). Each block follows the same `_upsert_personal_record(...)` helper pattern v1 introduced.

The blocks are essentially:

```sql
-- Best weekly closing_pct (HIGHER is better)
SELECT pr.week_start,
       (COUNT(*) FILTER (WHERE u.revenue_amount > 0)::numeric
        / NULLIF(COUNT(*), 0)) * 100 AS pct
INTO v_best_closing_w
FROM public.payroll_runs pr
JOIN unique_opps u ON u.run_id = pr.id
WHERE u.owner_tech = v_tech.name
  AND u.type = 'job'
  AND pr.week_start >= (date_trunc('week', now())::date - (25 * 7))
GROUP BY pr.week_start
HAVING COUNT(*) > 0
ORDER BY pct DESC LIMIT 1;
PERFORM public._upsert_personal_record(v_tech.id, 'closing_pct', 'week',
  v_best_closing_w.pct, v_best_closing_w.week_start);
```

For `callback_pct`, change `ORDER BY pct DESC` to `ORDER BY pct ASC` so the LOWEST value wins. The stored `value` column doesn't track direction — it's just the value; "best" is direction-aware.

Each rate KPI × period gets its own block. 9 blocks total.

The `unique_opps` CTE is defined ONCE per per-tech loop iteration to avoid recomputing it for every record.

### Direction-aware display

`scorecard_tier_thresholds.direction` already encodes 'higher' vs 'lower' for each kpi_key. The compute function can read it once at the start of the loop:

```sql
SELECT direction INTO v_callback_dir FROM public.scorecard_tier_thresholds WHERE kpi_key = 'callback_pct';
```

But simpler: just hard-code `ORDER BY pct ASC` for callback_pct compute blocks, since direction never changes for these KPIs.

### Frontend (recordFormatters.ts)

Add 3 new entries to `LABEL_MAP` and 1 new value formatter:

```ts
const LABEL_MAP: Record<string, Record<string, string>> = {
  // ... existing ...
  closing_pct:    { week: 'Best closing %', month: 'Best closing %', quarter: 'Best closing %' },
  membership_pct: { week: 'Best memberships %', month: 'Best memberships %', quarter: 'Best memberships %' },
  callback_pct:   { week: 'Lowest callbacks %', month: 'Lowest callbacks %', quarter: 'Lowest callbacks %' },
};

const fmtPct = (n: number) => `${n.toFixed(1)}%`;

const VALUE_FMT: Record<string, (n: number) => string> = {
  // ... existing ...
  closing_pct: fmtPct,
  membership_pct: fmtPct,
  callback_pct: fmtPct,
};
```

Plus 3 new test cases in `recordFormatters.test.ts` (one per new KPI).

`Recognition.tsx` is unchanged — it groups records by period generically, so new rate-KPI rows just appear as new tiles in their period sections automatically.

## Components Summary

| File | Action |
|---|---|
| `supabase/migrations/<new>.sql` | Create — extends `compute_streaks_and_prs()` with the 9 new compute blocks; one-shot run at end |
| `src/lib/wins/recordFormatters.ts` | Modify — 3 new LABEL_MAP entries + 3 new VALUE_FMT entries + new `fmtPct` helper |
| `src/lib/wins/__tests__/recordFormatters.test.ts` | Modify — 3 new test cases |

3 files. No `Recognition.tsx` changes. No new view (the `unique_opps` CTE lives inside the function).

## Reversibility

The migration replaces `compute_streaks_and_prs()` body. Restore the v1 body to revert. Records added by v2 stay in the table but are unreferenced (LABEL_MAP wouldn't have entries for them — they'd render with the kpi_key as label, ugly but not broken).

## Testing

### SQL spot-check (manual)

After applying the migration and `SELECT public.compute_streaks_and_prs();`:

```sql
SELECT pt.name, tpr.kpi_key, tpr.period, tpr.value, tpr.achieved_at, tpr.is_fresh
FROM public.tech_personal_records tpr
JOIN public.payroll_techs pt ON pt.id = tpr.tech_id
WHERE tpr.kpi_key IN ('closing_pct', 'membership_pct', 'callback_pct')
ORDER BY pt.name, tpr.kpi_key, tpr.period;
```

Expect: each active tech has up to 9 new rows. Spot-check sanity: `closing_pct` values should be 0–100, `membership_pct` 0–100, `callback_pct` 0–100. Realistic ranges per Twins's actual scorecard tiers (closing ≈ 50%, memberships ≈ 20%, callbacks ≈ 2%).

### Frontend tests

Run `npx vitest run src/lib/wins/__tests__/recordFormatters.test.ts`. Expect 7 (existing) + 3 (new) = 10 passing.

### Manual smoke (live)

After deploy, open `/tech/wins?as=<charles-uuid>`. The Best Week/Month/Quarter sections should each have 3 new tiles (Best closing %, Best memberships %, Lowest callbacks %) alongside the existing 3 (revenue, jobs, avg ticket).

## Out of Scope (deferred)

- **Reviews-based records** ("most reviews per period") — separate spec lands next.
- **`v_kpi_per_period` view** that the frontend could read directly. The compute-function approach is fine for v2 because reads come through `useMyPersonalRecords` which already exists.
- **Twins-records section** (company-level Wins) — Reviews-ingestion spec adds that section.

## Open Questions

None. The formulas are pinned to the existing `kpi-calculations.ts`. The compute extension follows the v1 pattern verbatim.
