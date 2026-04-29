# Auto-Detect Visibility: Scorecard + Run Payroll Triggers

**Status:** Draft, awaiting Daniel's approval
**Author:** Claude (with Daniel)
**Date:** 2026-04-29

## Problem

The tech-parts auto-detect feature (PR #40, deployed 2026-04-29) inserts auto-confidence parts into `payroll_job_parts` and recomputes commission — but **only when someone opens an individual job's detail page** (`/tech/jobs/<payroll_id>`), via the lazy `useApplyPartSuggestions(jobId)` hook in `TechJobDetail.tsx`.

Daniel's mental model is different. He opens a scorecard at `/tech?as=<tech-uuid>` (admin impersonation of, e.g., Charles Rue) and expects to see parts and commission already filled in across every visible job. Today, the scorecard shows `PARTS = $0`, `COMMISSION = $0.00`, `STATUS = UNSYNCED` for every row, because:

1. **Most rows are UNSYNCED** — meaning the HCP job isn't imported into `payroll_jobs` yet. Auto-detect can't run on jobs that don't exist in the payroll system.
2. **Even imported draft jobs show $0 parts** — because nobody has clicked into them, so detection never fired.

The feature works end-to-end at the job-detail level. The visibility gap is purely about **when detection fires**: the scorecard is the natural inspection surface but the trigger is one level deeper.

## Goal

Make detection fire at two natural points so the scorecard reflects auto-detected parts without the admin (or tech) having to drill into every job:

1. **Run Payroll import** — when `merge_payroll_jobs` imports HCP jobs into `payroll_jobs`, fire detection on every new/changed draft job. Forward-path coverage.
2. **Scorecard load** — when `CommissionTracker` mounts (or its date range changes), scan visible draft jobs with `parts_cost === 0` and fire detection on them. Catch-up for historical jobs imported before this feature shipped, plus drift if HCP notes change after import.

## Non-Goals

- **No new Edge Function.** The existing `apply-part-suggestions` Edge Function is reused as-is.
- **No server-side bulk endpoint.** N concurrent invocations from the client is fine for the volumes Twins handles (typical: 5–35 jobs per scorecard, 30–80 per weekly Run Payroll). YAGNI until we measure a problem.
- **No re-detection on HCP data changes.** If HCP notes/line items are edited after the import, the scorecard auto-fire (option A) catches it the next time someone loads the scorecard. Webhook-triggered re-detection is a separate spec.
- **No SQL/RLS changes.** Pure frontend wiring on top of the already-deployed Edge Function and migrations.

## Architecture

### Trigger B — Run Payroll import auto-fires

In `src/pages/payroll/Run.tsx`, after the existing `merge_payroll_jobs` RPC resolves with its delta object `{ new, changed, removed, unchanged }`, fire `apply-part-suggestions` once per draft job in `delta.new` + `delta.changed`. Wait for all to settle. Surface progress in the existing sync UI ("Syncing… → Detecting parts on N jobs… → Done").

The `merge_payroll_jobs` RPC returns the run_id + per-bucket counts but not the specific job IDs that were inserted/updated. The Run page already has the matching local job records keyed by `hcp_id`. After the merge, we can re-read `payroll_jobs` for that `run_id` filtered to `submission_status='draft'`, get the IDs, fire detection.

**Why not move detection inside the SQL RPC?** Calling Edge Functions from PL/pgSQL is awkward (HTTP from inside the DB, requires extensions or extra plumbing). Doing it client-side after the merge resolves is simpler and equally correct.

### Trigger A — Scorecard auto-fires

In `src/components/technician/scorecard/CommissionTracker.tsx`, add a `useEffect` keyed on the visible jobs list. When the list changes (initial load, date-range change, refetch), scan for jobs that satisfy ALL of:

- `payroll_id !== null` (in payroll system — auto-detect needs a `payroll_jobs` row)
- `submission_status === 'draft'` (cannot auto-edit submitted/locked jobs)
- `parts_cost === 0` (no parts entered yet — detection might find some)

For each match, fire `apply-part-suggestions(job_id=payroll_id)`. After all settle, invalidate the scorecard query keys so the table re-renders with new parts/commission values.

A module-level dedup map (`Map<jobId, timestamp>`) suppresses re-firing the same job within a 30s window. This stops a date-range toggle from re-firing on the same job repeatedly.

### Shared bulk hook

Both triggers call the same code path. Factor into a single hook:

```ts
// src/hooks/tech/useApplyPartSuggestionsBulk.ts
export function useApplyPartSuggestionsBulk() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (jobIds: number[]) => {
      // Dedup-window check + Promise.allSettled on Edge Function calls
      // Returns { fired: number, skipped: number, errors: Error[] }
    },
    onSettled: () => {
      // Invalidate keys so the UI refreshes
      qc.invalidateQueries({ queryKey: ['my_jobs'] });
      qc.invalidateQueries({ queryKey: ['my_scorecard'] });
      qc.invalidateQueries({ queryKey: ['admin_tech_jobs'] });
      qc.invalidateQueries({ queryKey: ['apply_part_suggestions'] });
    },
  });
}
```

The hook is the single point that knows the Edge Function name, the dedup logic, and the query keys to invalidate. Both call sites just hand it a list of job IDs.

### Dedup window

A 30-second module-level `Map<number, number>` (jobId → last-fired-at-ms). Before firing, check `Date.now() - lastFiredAt < 30000` and skip if so. The Edge Function is already idempotent at the database level, so this is purely a perf/network optimization — preventing N redundant HTTP requests when the user toggles between date ranges.

### Performance bounds

- Typical scorecard view: ~5–20 draft jobs with `parts_cost === 0`. ~5 concurrent Edge Function calls, each ~500ms–1s. Total background time: ~1–2s.
- Worst observed scorecard: ~50 draft jobs (large historical date range). 50 concurrent calls. Supabase Edge Functions handle this fine; if any throttle, `Promise.allSettled` surfaces the errors and we log + retry on next mount.
- Run Payroll import: typically ~30–80 jobs per week. Same pattern, just bigger N. Existing sync UI already shows a progress state, so the user sees "Detecting parts on 42 jobs…" instead of a silent freeze.

If real measurements show this is slow, we add a server-side bulk endpoint later. Don't pre-optimize.

## Components Summary

| File | Action |
|---|---|
| `src/hooks/tech/useApplyPartSuggestionsBulk.ts` | Create — bulk wrapper hook with dedup + invalidation |
| `src/components/technician/scorecard/CommissionTracker.tsx` | Modify — add effect that fires bulk detection on visible draft-with-zero-parts jobs |
| `src/pages/payroll/Run.tsx` | Modify — after `merge_payroll_jobs` resolves, fire bulk detection on the run's draft jobs; surface progress in existing sync UI |

## Testing

### Unit tests

- `useApplyPartSuggestionsBulk` — given an array of jobIds, calls `supabase.functions.invoke('apply-part-suggestions')` once per ID, returns aggregated counts, invalidates the right query keys.
- Dedup window — second call within 30s for the same jobId is skipped.
- Empty input array — no-ops, no Edge Function calls.
- Edge Function error on one job doesn't fail the whole batch (`Promise.allSettled` semantics).

### Manual smoke (live)

1. Open `/tech?as=<charles-uuid>` on twinsdash.com (Vercel deploy of merged main + this PR).
2. Watch the network tab: should see 1 Edge Function call per draft job with `parts_cost = 0` in the visible range.
3. Wait ~2s; the table re-renders with non-zero PARTS and COMMISSION values for jobs whose HCP notes/line-items mentioned recognizable parts.
4. Open `/payroll/run`, sync the current week. After "Syncing…" finishes, the indicator transitions to "Detecting parts on N jobs…" then to "Done." Open the scorecard for the same tech: parts already populated.
5. Soft-delete a part on a job detail (`/tech/jobs/<id>`). Reload the scorecard. The Edge Function does NOT re-add the part (existing `removed_by_tech` mechanism, unchanged here).

## Observability

The existing `tech_part_match_log` table already records every Edge Function invocation with `applied_count`, `suggested_count`, `unmatched_count`, `raw_inputs_hash`. After this ships, that table will see a much higher invocation rate — useful for measuring how many jobs are getting auto-detection without manual intervention. No new logging needed.

## Reversibility

If anything goes wrong:

- Disable trigger A: gate the `useEffect` in `CommissionTracker.tsx` behind a feature flag check, or comment out the effect entirely. Existing scorecard behavior returns immediately (rows just don't auto-populate).
- Disable trigger B: remove the post-merge call in `Run.tsx`. Existing sync flow is unchanged.
- The `useApplyPartSuggestionsBulk` hook stays in place but unused — no risk.

The Edge Function itself, the SQL function, and the `payroll_job_parts` rows it has inserted are all untouched by reverting this PR.

## Open Questions

None. Pure frontend wiring on already-shipped infrastructure.

## Follow-Up Specs

- **Wins tab expansion** — best-week-avg / highest-repair-ticket / highest-install-ticket / weekly-monthly-quarterly conversion records / most-reviews-in-period / etc. Separate scope, separate spec, queued after this ships.
- **Webhook re-detection** — when HCP notifies us of changed line items / notes on an existing job, fire detection automatically (not just on next scorecard load). Spec only if drift in practice is meaningful.
- **Admin impersonation write-parity audit** — already in the queue from the parts-access brainstorm. Should be done before or alongside this so admin can confirm/remove suggested parts on a tech's scorecard cleanly.
