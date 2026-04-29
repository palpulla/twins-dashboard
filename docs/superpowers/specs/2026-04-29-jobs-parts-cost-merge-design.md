# Merge `jobs.parts_cost` with `payroll_job_parts` Across Surfaces

**Status:** Draft, awaiting Daniel's approval (auto-approving per "whatever is best")
**Author:** Claude (with Daniel)
**Date:** 2026-04-29

## Problem

The `jobs` table holds an HCP-sourced `parts_cost` value, populated by the HCP webhook + sync edge functions when an HCP job is created or updated. That value is often $0 or stale because techs don't always enter parts data in HCP itself — they enter it in Twins Dash via the Parts Picker / auto-detect.

The Twins-side parts data lives in a different table: `payroll_job_parts` (rows linked to `payroll_jobs` rows). The auto-detect feature shipped earlier today writes here. The Parts Picker writes here. The new soft-delete writes here.

**Result:** the parts cost reflected on different pages of the dashboard depends on which data source that page reads:

- **Scorecard / `useAdminTechJobs`** correctly does the merge: `payroll_job_parts SUM` first, fall back to `jobs.parts_cost`. Shows the right number.
- **Team Hero / `useTeamOverview` / Supervisor Dashboard** reads `jobs.parts_cost` only. **Misses every auto-detected and tech-entered part.** Underreports parts and overreports commission for any tech using the Twins-side parts flow.
- **Admin Commission Reports / `AdminCommissionReports.tsx`** same problem.
- **Leaderboard** doesn't read parts_cost (revenue-only ranking) — unaffected.

This was flagged in the auto-detect-visibility PR's final review. The auto-detect feature works end-to-end, but the leaderboard / commission reports keep showing the pre-auto-detect numbers because they read the wrong column.

## Goal

Make every consumer that reads `parts_cost` see the same value: `MAX(payroll_job_parts SUM, jobs.parts_cost)` — i.e., whichever source has more parts data wins. (Most often the payroll-side wins because techs enter parts there; sometimes HCP has more if a tech entered parts in HCP and Twins skipped Twins-side entry.)

Single source of truth. Achieved server-side so future consumers automatically benefit.

## Non-Goals

- **No HCP webhook changes.** The webhook keeps writing to `jobs.parts_cost` from HCP data — that's still the right behavior for the HCP-side number.
- **No DB triggers** that propagate `payroll_job_parts` writes back into `jobs.parts_cost`. The two columns are conceptually different (HCP vs. Twins-managed). Keeping them separate avoids write-path coupling and makes it possible to detect drift between HCP and Twins.
- **No commission-recalc backfill** for previously-completed payroll runs. The view fixes display going forward; historical commission paid is whatever it was. If a tech is owed back-commission for a prior week because the merge wasn't in effect yet, that's a manual decision Daniel makes case-by-case.
- **No removal of `jobs.parts_cost` column.** Other things may reference it; minimal-touch is the principle.

## Architecture

### A SQL view that does the merge

```sql
CREATE OR REPLACE VIEW public.v_jobs_with_parts AS
SELECT
  j.*,
  -- effective_parts_cost: max of HCP's value and Twins-side aggregate.
  -- payroll_job_parts join requires the job to be in a payroll_runs row;
  -- jobs not yet imported into payroll fall back to j.parts_cost.
  GREATEST(
    COALESCE(j.parts_cost, 0),
    COALESCE(p.payroll_parts_sum, 0)
  ) AS effective_parts_cost
FROM public.jobs j
LEFT JOIN (
  SELECT pj.hcp_job_number,
         SUM(jpp.total) AS payroll_parts_sum
  FROM public.payroll_jobs pj
  JOIN public.payroll_job_parts jpp ON jpp.job_id = pj.id
  WHERE NOT jpp.removed_by_tech    -- soft-deleted parts don't count
  GROUP BY pj.hcp_job_number
) p ON p.hcp_job_number = j.hcp_job_number;
```

**`GREATEST` instead of `COALESCE`:** if both sources have data (rare but possible), use the larger. This handles the edge case where HCP has $300 and Twins has $250 — show $300 (which is presumably correct since HCP captures actual cost; the Twins side may have missed a part). The opposite case (Twins has more) is the common one auto-detect creates.

**Soft-delete filter:** `WHERE NOT jpp.removed_by_tech` — when a tech soft-removes an auto-detected part, it stops counting in the SUM. Mirrors the auto-detect Edge Function's idempotency check.

### Two consumer migrations

The view exposes the same columns as `jobs` plus the new `effective_parts_cost`. Two consumers need updates:

**1. `src/hooks/admin/useTeamOverview.ts:62`** — change `from('jobs')` to `from('v_jobs_with_parts')`, and change the column reference at line 148 from `j.parts_cost` to `j.effective_parts_cost`. Also update the type at line 69 to include the new column (or use `j.effective_parts_cost ?? j.parts_cost` defensively).

**2. `src/pages/AdminCommissionReports.tsx`** — find the `from('jobs')` query (around lines 40-55), swap to `from('v_jobs_with_parts')`. Change the line 69 mapping `parts_cost: j.parts_cost` to `parts_cost: j.effective_parts_cost`. The downstream `calculateCommissionSummary` already takes `parts_cost` as a generic field name; just feed it the merged value.

**Not changed:**
- `useAdminTechJobs.ts` — already merges client-side. Could simplify by switching to the view too (deferred — works correctly today, so YAGNI).
- `Leaderboard.tsx` — doesn't use parts_cost.
- `Index.tsx` (main dashboard) — doesn't reference jobs.parts_cost directly.

### RLS

The view inherits RLS from the underlying `jobs` table. Anyone who can read `jobs` can read `v_jobs_with_parts`. The `payroll_job_parts` join in the view bypasses RLS because views run as the view owner — but since the view only exposes the SUM, no row-level data leaks. This matches the existing pattern (e.g., scorecard view aggregates payroll data without exposing rows).

If RLS proves wrong (a non-payroll-access user accidentally seeing a parts SUM they shouldn't), gate the JOIN on `has_payroll_access(auth.uid())`. But the SUM alone is not sensitive — it's just dollars.

## Components Summary

| File | Action |
|---|---|
| `supabase/migrations/<new>.sql` | Create — `CREATE OR REPLACE VIEW public.v_jobs_with_parts ...` plus a `GRANT SELECT TO authenticated` |
| `src/hooks/admin/useTeamOverview.ts` | Modify — swap `from('jobs')` → `from('v_jobs_with_parts')`; use `effective_parts_cost` |
| `src/pages/AdminCommissionReports.tsx` | Modify — same swap, same column rename |

That's it. 3 files. No SQL function changes. No Edge Function changes. No frontend tests needed (consumers are integration code; the view is what matters and is tested via SQL spot-check).

## Reversibility

- Drop the view: `DROP VIEW public.v_jobs_with_parts;`
- Revert the 2 client files

The underlying `jobs` and `payroll_job_parts` tables are untouched.

## Testing

### SQL spot-check (manual via Supabase Studio)

After applying:

```sql
-- Verify view exists + grants are right
SELECT relname, relkind FROM pg_class
WHERE relname = 'v_jobs_with_parts';

-- Spot-check: a job that has payroll_job_parts but jobs.parts_cost = 0.
-- effective_parts_cost should reflect the SUM.
SELECT j.hcp_job_number, j.parts_cost, v.effective_parts_cost
FROM public.v_jobs_with_parts v
JOIN public.jobs j ON j.id = v.id
WHERE j.parts_cost = 0
  AND v.effective_parts_cost > 0
LIMIT 5;

-- Spot-check: a soft-deleted part should NOT count.
-- (Manual: pick a job, mark a part removed_by_tech=true, re-query; expect a smaller sum.)
```

### Manual smoke (live)

1. Apply migration to prod.
2. Open `/team` (Supervisor Dashboard / Team Hero). Verify any tech with auto-detected parts now shows accurate `weekEstCommission`.
3. Open `/admin/commission`. Verify per-tech commission summaries reflect the merged parts cost.
4. Open `/tech/<charles-uuid>` scorecard. Verify it still works (it uses `useAdminTechJobs` which already does the merge — should be unchanged).

### Existing tests

Run `npx vitest run` — should still pass (these consumers don't have unit tests; the view is verified via SQL).

## Observability

No new logging needed. If a consumer reports wrong parts numbers post-deploy, debug via the SQL spot-check above.

## Open Questions

None. Single concrete change, well-scoped.

## Follow-Up

Once this lands, the only remaining "$0 parts" inconsistency is in surfaces that read jobs.parts_cost directly bypassing the view. Future code reviews should flag any new `from('jobs')` query that needs parts data — point it at `v_jobs_with_parts` instead. A linter rule could enforce this, but it's overkill for a 2-consumer system.
