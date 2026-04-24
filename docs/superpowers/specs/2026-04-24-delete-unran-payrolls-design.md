# Delete Unran Payrolls Design Spec

**Date:** 2026-04-24
**Status:** Design approved, ready for implementation plan
**Scope:** Add a per-row delete action to the payroll History page for any `payroll_runs` row that isn't `status = 'final'`. Finalized runs remain locked.

## Context

The payroll History page at [twins-dash/src/pages/payroll/History.tsx](../../../twins-dash/src/pages/payroll/History.tsx) lists every row in `payroll_runs` regardless of status. Over time this accumulates abandoned drafts (`in_progress` runs the operator never finalized) and superseded runs (`superseded` rows left behind when the operator clicked "Start Fresh" on the Resume card). The operator has no way to remove them, so the history list grows monotonically with noise.

Finalized runs (`status = 'final'`) represent real payroll that was paid out — those must stay immutable for audit purposes.

## Goals

- Let the operator delete any `payroll_runs` row whose status is not `final`.
- Cascade the delete to the run's jobs, job parts, and commissions (already handled by existing `ON DELETE CASCADE` FKs — no schema change needed).
- Make final runs visually distinct and non-deletable, even if a client bug tried.
- Require a single confirm click; no multi-step flow.

## Non-goals

- No soft-delete / trash / recovery. Abandoned drafts aren't worth tracking; hard delete is fine.
- No per-user audit log of who deleted what (single-admin app; if we need this later, we add it).
- No bulk select / "delete all superseded" button. If the operator wants to clean multiple rows, they click each one; most weeks will have 0-1 deletable rows.
- No change to the delete-during-sync or delete-during-review flows — only touches the History page.

## Data model

No schema changes. Existing FKs already cascade:

- `payroll_jobs.run_id → payroll_runs.id ON DELETE CASCADE` ([20260418100000_payroll_schema.sql:49](../../../twins-dash/supabase/migrations/20260418100000_payroll_schema.sql#L49))
- `payroll_job_parts.job_id → payroll_jobs.id ON DELETE CASCADE`
- `payroll_commissions.job_id → payroll_jobs.id ON DELETE CASCADE`

So a single `DELETE FROM payroll_runs WHERE id = ?` drops every dependent row in one transaction.

## RLS

The existing `"payroll access"` policy on `payroll_runs` is `FOR ALL TO authenticated USING (has_payroll_access(auth.uid()))`. It already covers DELETE. No migration required.

## Backend — single call pattern

Client issues:

```ts
const { error, count } = await supabase
  .from("payroll_runs")
  .delete({ count: "exact" })
  .eq("id", runId)
  .neq("status", "final");
```

The `.neq("status", "final")` is a belt-and-suspenders safety gate — even a buggy client or race condition cannot delete a finalized run. If `count === 0` after the call succeeds, either the run doesn't exist or the operator tried to delete a finalized run; the UI surfaces that specific case.

## UI

### History page row action

For each row in the history table, append a new rightmost action cell.

- If `status === 'final'`: render a muted `<Lock>` icon with a tooltip *"Finalized — locked"*. Non-clickable.
- If `status !== 'final'`: render a `<Trash2>` icon button (ghost variant, destructive on hover).

### Confirm dialog

Click on the trash icon opens a [shadcn AlertDialog](https://ui.shadcn.com/docs/components/alert-dialog) with:

- **Title:** *"Delete this payroll run?"*
- **Body:** *"Week of Apr 13, 2026 · 12 jobs · draft. This permanently removes the run and its jobs, parts, and commissions. This cannot be undone."* (Substitute the status label: `draft` for `in_progress`, `superseded` for `superseded`.)
- **Cancel** button: closes dialog, no-op.
- **Delete** button (destructive red): runs the delete, closes dialog.

### After-delete behavior

- On success: toast *"Run deleted"*, refetch the history list. (Existing list state already reloads on every render; simplest path is to mutate state locally by filtering out the deleted id.)
- On error: toast the Postgres error message (most likely an RLS or network issue).
- On `count === 0` with no error: toast *"Run could not be deleted (may already be finalized)"*. Refetch to sync UI with reality.

## Testing

Manual test plan:

1. On `/payroll/history`, confirm the action column shows: trash for `in_progress` + `superseded` rows, lock for `final` rows.
2. Click trash on a `superseded` row → confirm dialog → Delete → row disappears from the list, toast fires.
3. Click trash on an `in_progress` draft → confirm dialog → Delete → row disappears, any jobs/parts/commissions for that run are also gone (verify via a spot-check SQL query or by reloading; the FKs should have cascaded).
4. Cancel on the confirm dialog → no change.
5. Manually edit DOM / devtools to force a delete call against a `final` row (or race the status update) → expect toast *"Run could not be deleted"* and the row remains.

No automated tests are added. The logic is too thin to justify a Vitest suite, and there's no existing DB-level test harness in this codebase yet.

## Out-of-scope follow-ups

- Per-user audit log of deletions.
- Bulk-delete UI ("delete all superseded for weeks older than 30 days").
- Undo / recover deleted runs.
- Applying the same pattern to the Drafts section on the Payroll Home page — can be added later if the Home-page drafts section grows cluttered, but for now the History page delete is sufficient since Home already filters out superseded runs.
