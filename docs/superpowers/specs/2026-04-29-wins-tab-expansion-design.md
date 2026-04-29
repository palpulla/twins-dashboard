# Wins Tab Expansion: All-Time + Per-Period Personal Records

**Status:** Draft, awaiting Daniel's approval
**Author:** Claude (with Daniel)
**Date:** 2026-04-29

## Problem

The `/tech/wins` page (`src/pages/tech/Recognition.tsx`) currently shows three personal records, all weekly: best revenue week, best total-jobs week, best avg-opportunity week. Plus streaks (above-avg / tier-held / no-callbacks / rev-floor) and tier-up moments.

Daniel wants this expanded to capture meaningful "wins not just repeat stuff from scorecard" — single-job records (highest install ticket, highest repair ticket), per-period bests for every scorecard KPI (week + month + quarter), longest streaks, lowest callbacks, most reviews. So a tech can open the Wins tab and see their definitive bests at a glance.

## Goal

Expand the personal-records system to track:

1. **Single-job records** — highest install ticket, highest repair ticket (ever, across all jobs).
2. **Per-period records for revenue / total_jobs / avg_opportunity** — week (already exists), month (new), quarter (new).
3. **New per-period records** — best closing %, best memberships %, lowest callbacks % — each week + month + quarter.
4. **Wins tab UI** — show all records grouped by period (single-job → week → month → quarter), highlight the freshest ones.

Reviews-based stats (most reviews in week/month/quarter) are deferred — see "Out of Scope" below.

## Non-Goals

- **Reviews-based stats are deferred to a separate spec.** Reviews data is not currently ingested anywhere in the system. Adding "most reviews in a quarter" requires a separate ingestion pipeline (HCP review webhook, Google Business Profile API, or manual entry) before any UI can render it. A follow-up spec for reviews ingestion will land first; the Wins tab gains those records once that data arrives.
- **No comparative ("you vs. company best") view.** Daniel's ask was "my own only." The Leaderboard is the company-comparison surface. Wins stays personal.
- **No edit/admin UI for records.** The records are derived fields, recomputed nightly. No manual override.
- **No additional streak types.** The 4 existing streaks (above-avg / tier-held / no-callbacks / rev-floor) cover the "longest streak" ask conceptually; this spec doesn't add more streak kinds. If specific new streaks come up later, they're additive and small.

## Architecture

### Data model

The existing `tech_personal_records` table is the right home, with two extensions:

```sql
-- existing schema (20260425170100_tech_personal_records.sql):
-- CREATE TABLE public.tech_personal_records (
--   id, tech_id, kpi_key, period text CHECK (period IN ('week','month','year')),
--   value, achieved_at, is_fresh, updated_at
--   UNIQUE (tech_id, kpi_key, period)
-- )
```

#### Schema migration

1. **Widen the `period` CHECK constraint** to include `'quarter'` and `'single_job'`. The unique key on `(tech_id, kpi_key, period)` already enforces "one record per kpi+period" — so e.g. `('week', 'revenue')` and `('month', 'revenue')` and `('quarter', 'revenue')` are three distinct rows for the same tech.

2. **Add new `kpi_key` rows to `scorecard_tier_thresholds`** for the new keys (the FK requires them to exist). Even though we don't have tier thresholds for these new keys, a row with NULL or zero thresholds is acceptable — the FK is purely existence-checking. New keys:
   - `highest_install_ticket` (period: single_job; higher is better)
   - `highest_repair_ticket` (period: single_job; higher is better)
   - `closing_pct` (periods: week/month/quarter; higher is better) — already exists in scorecard, may need only a row added if not present
   - `memberships_pct` (same)
   - `callbacks_pct` (periods: week/month/quarter; **lower is better** — direction-aware)

   Verify each of these exists in `scorecard_tier_thresholds` before adding; only add rows that don't.

#### Compute function extension

Extend `public.compute_streaks_and_prs()` (defined in `20260425170200_compute_streaks_and_prs.sql`) to compute the new records. The existing function already loops `FOR v_tech IN SELECT * FROM public.payroll_techs ...` and writes records. We add new SQL blocks inside the existing loop for each new (kpi_key, period) tuple.

The function runs nightly at 07:00 UTC via the existing `pg_cron` job (`20260425170300_pg_cron_streaks_prs.sql`). After deploying the new logic, run it once manually so records appear immediately rather than waiting for the next night.

#### Direction-aware "best"

For most kpis, "best" = highest value. For `callbacks_pct`, "best" = lowest. Inside the compute function, use `ORDER BY value DESC` for higher-is-better and `ORDER BY value ASC` for lower-is-better. The table itself stores raw values without direction metadata; the compute logic decides what's "best" before writing.

The frontend doesn't need to know about direction — the value stored is already the best, regardless of direction.

#### Single-job records

For `highest_install_ticket` and `highest_repair_ticket`, the source is `payroll_jobs` joined to job-type classification. The convention used elsewhere in the codebase (e.g., the KPI calculation in `src/lib/kpi/`) classifies jobs by `description` or `job_type`:

- Install: `description ILIKE '%install%'` (or whatever the existing convention is — check `src/lib/kpi/types.ts` or similar).
- Repair: complement / repair-specific patterns.

Use the SAME classification logic as the scorecard so "highest install ticket" matches the install-counting elsewhere. If the rule is non-trivial, factor it into a SQL helper view rather than duplicating across the matcher. **Implementation must read the actual classifier from the codebase and not invent a new one** — otherwise records will diverge from the scorecard's own counts.

The single-job record uses `period = 'single_job'`; `achieved_at = pj.job_date`; `value = pj.amount`.

#### Periodic-rate records (closing_pct / memberships_pct / callbacks_pct)

These are KPI rates already computed elsewhere in the codebase, typically as `kpi_count / kpi_denominator * 100` over a date range. The compute function needs equivalent SQL.

Look at how the existing `useMyScorecardWithTiers` hook (or whichever drives the scorecard tile values) computes these for a date range. Reuse that logic in the SQL function — don't fork it. If the existing logic lives in a SQL view (`v_my_scorecard_*` or similar), the compute function can `SELECT FROM` that view. If it's only computed client-side, factor a SQL view as part of this spec.

For weekly: aggregate per `payroll_runs.week_start`. For monthly: aggregate per `date_trunc('month', job_date)`. For quarterly: aggregate per `date_trunc('quarter', job_date)`.

Lookback: spec says 25 weeks for the existing record computations. For monthly: 12 months. For quarterly: 8 quarters. These windows are large enough to capture "personal best" history without scanning the full table.

### Frontend

#### Recognition.tsx — group records by period

Currently `useMyPersonalRecords()` returns a flat list of `PersonalRecord[]`. The render lays out tiles in one section. After this spec, there will be ~17–20 records per tech (3 single-job + 9 weekly + 9 monthly + 9 quarterly, modulo what's actually computed).

Group by `period` and render four sections:

```
🏆 Single-Job Bests
  - Highest install ticket: $5,400 (Mar 12, 2026)
  - Highest repair ticket: $2,180 (Feb 4, 2026)

📅 Best Week Ever
  - Revenue: $34,500 (week of Apr 14)
  - Jobs: 28 (week of Mar 3)
  - Avg ticket: $1,420 (week of Apr 7)
  - Closing %: 78% (week of Mar 17)
  - Memberships %: 42% (week of Mar 17)
  - Callbacks %: 0% (week of Apr 7)  ← lower is better

🗓️ Best Month Ever
  ... same kpis ...

📆 Best Quarter Ever
  ... same kpis ...
```

The `is_fresh` flag (set when the record was achieved within the last 7 days) keeps the existing "🆕" highlight on `PersonalRecordTile`.

#### Empty / partial data

If a tech has zero history for some kpi (e.g. brand-new hire, no quarterly data yet), simply don't render that record. The `RecognitionEmptyState` component already handles "no records at all" — keep using it when the entire returned list is empty.

#### Display formatting

`PersonalRecordTile` already exists (`src/components/tech/PersonalRecordTile.tsx`), takes `label`, `value`, `context`, `isFresh`. Reuse it. Format:

- `value`: `$5,400` for currency kpis, `28 jobs` for counts, `78%` for percentages, `0%` for callbacks (with the visual cue that lower-is-better).
- `context`: e.g. `"Mar 12, 2026"` for single-job, `"week of Apr 14"` for weekly, `"April 2026"` for monthly, `"Q1 2026"` for quarterly.

The label-to-format mapping lives in `Recognition.tsx` so the tile component stays generic.

## Components Summary

| File | Action | Purpose |
|---|---|---|
| `supabase/migrations/<new>.sql` | Create | Widen `period` CHECK; insert any missing `scorecard_tier_thresholds` rows; extend `compute_streaks_and_prs()`; run it once manually |
| `src/pages/tech/Recognition.tsx` | Modify | Group records by period, add 4 section headers, fan out tiles per period |
| `src/lib/wins/recordFormatters.ts` | Create | label + value-format mapping per kpi_key (currency / count / percent / direction) |

That's it. The hook (`useMyPersonalRecords`) and the tile (`PersonalRecordTile`) don't change.

## Reversibility

- **Migration is reversible** — drop the new compute-function blocks (or revert to the old `compute_streaks_and_prs()` body); restore the original CHECK constraint after deleting any new-period rows.
- **`scorecard_tier_thresholds` rows added by the migration** can be left in place if reverted — they're harmless without the compute function feeding them.
- **Frontend revert** — simply revert the Recognition.tsx changes; the hook keeps working with the original 3-record output (the new records will be in the DB but unused — until the table is cleaned up).

KPI math elsewhere is untouched.

## Testing

### SQL tests (manual via Supabase Studio)

After applying the migration and running `SELECT public.compute_streaks_and_prs();`:

```sql
-- Should show records for all kpi_keys × periods for each tech with history.
SELECT tech_id, kpi_key, period, value, achieved_at, is_fresh
FROM public.tech_personal_records
ORDER BY tech_id, kpi_key, period;
```

Spot-check expected rows:
- Each tech should have 3 single-job rows (if they have install + repair history).
- 9 weekly rows (revenue, total_jobs, avg_opportunity, closing_pct, memberships_pct, callbacks_pct, plus the 3 existing ones).
- Same for monthly and quarterly.
- A brand-new hire (no history) should have zero rows; that's expected.
- Verify direction: `callbacks_pct` records should be the LOWEST observed value, not highest.

### Frontend tests

- `Recognition.tsx` — render with mocked `useMyPersonalRecords` returning a mixed set; assert section headers appear in the right order; assert tiles get the right label + format per kpi_key.
- `recordFormatters.ts` — pure unit tests on the label/format mapping (covers each kpi_key × format).

### Manual smoke (live)

1. Apply migration to prod.
2. `SELECT public.compute_streaks_and_prs();` to populate immediately.
3. Open `/tech/wins` (or `/tech/wins?as=<tech-uuid>` as admin).
4. Verify 4 sections render with real records for the impersonated tech.
5. Verify `is_fresh` highlight on any record achieved within the last 7 days.

## Out of Scope (deferred)

### Reviews ingestion + reviews-based records

Daniel's ask included "most reviews in a week/month/quarter." The system today does not capture reviews data anywhere — verified by repo-wide grep across migrations, src/, supabase/functions/. Adding the records requires:

1. **Decide the ingestion source.** Options:
   - HCP webhook for review events (if HCP captures customer reviews on completed jobs)
   - Google Business Profile API polling (Twins's GBP listing reviews)
   - Manual entry by admin on a new admin page
2. **Schema for reviews.** A `reviews` table linked to `payroll_jobs` (or `jobs`) by job_id + tech_id, with rating, review text, source, created_at.
3. **Compute integration.** Once data exists, the same `compute_streaks_and_prs()` function pattern adds `most_reviews_pct` (or whatever the chosen metric is) per period.

A separate brainstorm + spec for reviews ingestion lands before this gets unblocked. Once it does, adding the records to Wins is a small follow-up to the compute function — no UI changes needed beyond a new section header.

## Open Questions

None. Spec is concrete enough to write the implementation plan.

## Follow-Up Specs

- **Reviews data ingestion** — see "Out of Scope" above. Required before "most reviews in period" records can be added.
- **Backfill `jobs.parts_cost`** — flagged from the auto-detect-visibility final review. The leaderboard, team hero, and admin commission reports still show $0 parts because they read `jobs.parts_cost` (HCP table) instead of `payroll_job_parts`. Separate concern from Wins; tracking here as a known follow-up.
- **Streak expansion** — additional streak kinds if specific user behaviors emerge (e.g. "consecutive weeks with X memberships %", "consecutive months without callbacks", etc.). Additive, small, deferred.
