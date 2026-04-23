# Payroll Draft & Incremental Sync Design Spec

**Date:** 2026-04-23
**Status:** Design approved, ready for implementation plan
**Scope:** Add draft-and-resume + incremental re-sync to the payroll run wizard in `twins-dash` at `src/pages/payroll/Run.tsx`. No changes to commission math, ownership resolution, or the Excel writer.

## Context

The payroll run wizard is a 4-step flow: pick week → sync HCP → review jobs → summary/finalize. It lives at [twins-dash/src/pages/payroll/Run.tsx](../../../twins-dash/src/pages/payroll/Run.tsx), backed by Supabase tables `payroll_runs`, `payroll_jobs`, `payroll_job_parts`, `payroll_commissions`, `payroll_techs`, `payroll_parts_prices`, `payroll_bonus_tiers`.

Today the wizard has two limitations:

1. **No resume.** `/payroll/run` always starts at Step 1 with a fresh `in_progress` run. A draft from earlier in the week is supersededa without warning. Local React state (`step`, `reviewIdx`) is not persisted; a page reload drops the operator back at Step 1 even if the DB has a half-reviewed draft.
2. **Destructive re-sync.** `syncHCP` does `delete().eq("run_id", runId)` before inserting (Run.tsx:201). Any parts, owner picks, tip edits, or skip decisions from prior review are wiped. This means the operator must complete the weekly run in a single uninterrupted session, or risk losing work.

The operator wants to run payroll incrementally throughout the week as jobs complete in HCP — Monday's jobs on Monday, Tuesday's jobs on Tuesday — without re-reviewing what he's already reviewed.

## Goals

- Treat any `in_progress` run as a **draft**. Re-syncing HCP merges new/changed jobs into the draft instead of wiping it.
- Let the operator leave mid-flow and resume at the exact step + job they left off on.
- Make merge deltas visible: operator sees what's new, what changed, what got removed from HCP since last sync.
- Preserve operator edits (parts, owner_tech, tip, skip_reason, is_reviewed) across re-syncs; refresh purely informational HCP fields.
- Enforce one-draft-per-week by unique index so two concurrent runs can't collide.

## Non-goals

- No change to commission math, ownership resolution, Excel output, or HCP sync edge function logic (`sync-hcp-week`). The edge function keeps returning the same shape; the client does the merge.
- No sync across devices beyond what Supabase already gives (writes are shared; UI live-refresh is not added).
- No notifications/reminders that a draft exists. The operator checks Home or `/payroll/run` manually.
- No history of sync attempts beyond `last_sync_at`. If we need forensic detail later, we add a `payroll_sync_events` table; not now.

## Data model changes

### `payroll_runs` — new columns

| Column | Type | Default | Purpose |
|---|---|---|---|
| `current_step` | SMALLINT | 1 | 1–4, tracks wizard step for resume |
| `review_idx` | INTEGER | 0 | Which job index in Step 3 the operator was on |
| `last_sync_at` | TIMESTAMPTZ | NULL | Most recent HCP sync for this run |

Partial unique index to prevent duplicate drafts:

```sql
CREATE UNIQUE INDEX payroll_runs_one_draft_per_week
  ON payroll_runs(week_start)
  WHERE status = 'in_progress';
```

`status` values remain `in_progress | final | superseded`. "Draft" is a UI label, not a new status.

### `payroll_jobs` — new columns

| Column | Type | Default | Purpose |
|---|---|---|---|
| `first_synced_at` | TIMESTAMPTZ | now() | When this job first appeared in this run |
| `last_synced_at` | TIMESTAMPTZ | now() | Last sync that included this job |
| `removed_from_hcp` | BOOLEAN | false | Sync didn't return this job last time |
| `amount_changed_from` | NUMERIC(10,2) | NULL | Previous amount when HCP amount changed on a reviewed job |
| `is_reviewed` | BOOLEAN | false | Operator opened/edited this job in Step 3 |

`payroll_job_parts`, `payroll_commissions` — unchanged. Sync never touches parts rows; commissions are wiped and recomputed on every Step 3 → Step 4 transition as today.

## Sync merge logic

Replaces the `delete + insert` block in `syncHCP` (Run.tsx:156-219). Implemented as a Postgres RPC (`merge_payroll_jobs`) called from the client so the merge is atomic.

Input: `run_id`, `sync_timestamp`, array of normalized job rows from the edge function (after client-side $0-filter and gratuity-strip, unchanged).

For each incoming job, matched by `(run_id, hcp_id)`:

1. **New job** (no existing row): INSERT with `first_synced_at = sync_timestamp`, `last_synced_at = sync_timestamp`, `is_reviewed = false`, `removed_from_hcp = false`. Compute `owner_tech` via `resolveOwner` client-side before RPC (unchanged), or leave null if ambiguous.
2. **Existing, not reviewed** (`is_reviewed = false`): UPDATE all fields — `customer_display`, `description`, `line_items_text`, `notes_text`, `amount`, `subtotal`, `discount`, `tip`, `raw_techs`, `owner_tech`, `last_synced_at = sync_timestamp`, `removed_from_hcp = false`. Informational refresh, safe because the operator hasn't touched it.
3. **Existing, reviewed** (`is_reviewed = true`): UPDATE only informational fields — `customer_display`, `description`, `line_items_text`, `notes_text`, `raw_techs`, `last_synced_at = sync_timestamp`, `removed_from_hcp = false`. Preserve `owner_tech`, `tip`, `skip_reason`. If incoming `amount` differs from existing `amount`, set `amount_changed_from = COALESCE(amount_changed_from, <old amount>)` and update `amount` to the new value. The `COALESCE` is important for the double-sync case: if an earlier sync already flagged the job as changed and the operator hasn't opened it yet, we preserve the original (pre-change) amount rather than overwriting with the intermediate value. (Commission will be recomputed on the next Step 3 → Step 4 transition.)
4. **Existing row not in this sync's job list**: SET `removed_from_hcp = true`. Do not delete. Parts, commissions, and operator edits stay intact.
5. **Row flagged `removed_from_hcp = true` that now reappears**: clear the flag as part of case 2 or 3.

RPC returns a delta summary: `{ new: INT, changed: INT, removed: INT, unchanged: INT }` so the client can toast.

After the RPC returns successfully, client sets `payroll_runs.last_sync_at = sync_timestamp` in the same transaction (or the RPC does it).

Sync error handling: if the RPC errors, the transaction rolls back and the draft is unchanged. Surface the error message in the existing `syncError` toast.

## Resume flow

**Home page (`/payroll`)** — add a "Drafts" section above the history table. Query `payroll_runs WHERE status = 'in_progress' ORDER BY week_start DESC`. For each draft, render a row with: week range, last-sync timestamp (or "never synced"), job count (via count of `payroll_jobs`), and a "Resume" button that navigates to `/payroll/run?run_id=<id>`. Empty state: section hidden.

**`/payroll/run` entry** — on mount:

- If query param `run_id` is present: hydrate that run. Set `runId`, `weekStart`, `step = current_step`, `reviewIdx = review_idx`. Load its jobs and job_parts. Skip the resume prompt.
- Else: query `payroll_runs WHERE status = 'in_progress'`. If exactly one exists, show a resume card: "Draft in progress for week of {week_start} (last synced {relative time}). **Resume** | **Start fresh**". Resume → hydrate as above. Start-fresh → set existing run to `status = 'superseded'`, then fall through to normal Step 1. If multiple drafts exist (shouldn't, because of the unique index, but defensive), show all in a list and let the operator pick.
- Else: fall through to Step 1 as today.

**Persisting step + reviewIdx** — debounced 500ms:

- Whenever `step` changes: UPDATE `payroll_runs.current_step = {step}` for the current run_id.
- Whenever `reviewIdx` changes in Step 3: UPDATE `payroll_runs.review_idx = {reviewIdx}`.

Failure to persist is a soft error (console.warn, no toast). The user's work is already safe in `payroll_jobs` and `payroll_job_parts`; only the resume position is lost.

## UI changes

**Step 2 (Sync HCP)** — [Run.tsx:430-489](../../../twins-dash/src/pages/payroll/Run.tsx#L430).

- Button label: "Sync from HCP" on first sync (no `last_sync_at`), "Re-sync from HCP" afterward.
- After successful sync, show a delta strip above the jobs table: `+5 new · 2 amount-changed · 1 removed from HCP` (hide segments with count 0).
- Jobs table adds an indicator column (before Date):
  - Green **NEW** badge if `first_synced_at = last_synced_at` (job arrived in the most recent sync).
  - Yellow **Δ** badge if `amount_changed_from IS NOT NULL`.
  - Muted **Removed** badge if `removed_from_hcp = true`.
  - Stack if multiple apply.

**Step 3 (Review)** — [Run.tsx:491-742](../../../twins-dash/src/pages/payroll/Run.tsx#L491).

- Title line: "Job {idx + 1} of {total}" becomes "Job {idx + 1} of {total} ({new_count} new, {changed_count} changed)" when at least one of those counts is > 0; otherwise unchanged.
- When the current job has `amount_changed_from IS NOT NULL`, render a yellow banner at the top of the card: "Amount changed in HCP: ${amount_changed_from} → ${amount}. Commissions will recalculate on Finalize." When the operator navigates to a different job (Next/Prev) or leaves Step 3 (Back), clear that job's `amount_changed_from = NULL` — the banner is a one-shot heads-up.
- Each time the current job changes (new `job.id` in Step 3's Card), flip `is_reviewed = true` for that job. Simple effect keyed on `job.id`.
- "Save & exit" button added to the bottom-right of every step card next to the Continue button. Clicking it navigates to `/payroll`. Data is already saved per-edit; this button is cosmetic closure.

**Step 4 (Summary)** — [Run.tsx:744-837](../../../twins-dash/src/pages/payroll/Run.tsx#L744).

- If any `payroll_jobs.removed_from_hcp = true` exists for this run, show a warning strip above the table: "{N} job(s) removed from HCP after sync. [Review]". Clicking jumps back to Step 3 with `reviewIdx` set to the first removed job.
- Otherwise unchanged.

**Resume card on `/payroll/run`** — new component, rendered before StepIndicator when a draft is detected via the entry-flow logic above.

## Error handling and edge cases

- **Two drafts for the same week.** Prevented by the partial unique index. Start-fresh must mark the existing draft `superseded` before creating a new one (matches current code at Run.tsx:145).
- **Mid-merge failure.** RPC transaction rolls back; draft unchanged. Operator sees the existing `syncError` toast and can retry.
- **HCP changes a job's `hcp_id`.** Would present as an add + a remove in the same sync. Visible in the delta line; operator decides.
- **Operator opens a job, makes no edits, clicks Next.** `is_reviewed = true` is flipped on open, so subsequent re-syncs preserve what's there. That's intentional — "I looked at it, it's mine."
- **Operator hits Resume on a draft whose `current_step = 4` but `status = in_progress`** (didn't click Finalize). Drop them at Step 4 with commissions already computed from the last Step 3 → 4 transition. They can Finalize or go back to Step 3 to re-review.
- **Operator hits Resume on an empty draft** (`current_step = 1` with no jobs). Same as fresh start — land on Step 1 with the saved `week_start`.
- **A job reappears in a sync after being flagged removed.** Case 5 in the merge logic clears the flag. The previously-entered parts and commissions are still intact, so the job seamlessly rejoins the draft.
- **`amount_changed_from` on a job that's then re-opened and re-edited.** Clearing happens on navigation away. If the operator wants to *keep* seeing the flag, the implementation doesn't guarantee that — it's a one-shot "hey look, this changed."

## Testing

**Unit tests** for the merge logic. Since the merge is a Postgres RPC, tests run against a local Supabase or a mock (choice depends on existing twins-dash test infrastructure — check `twins-dash/vitest.config.ts` before committing to an approach). Cases:

1. New job inserts with correct timestamps and default flags.
2. Existing unreviewed job fully refreshed including `owner_tech` and `tip`.
3. Existing reviewed job: `owner_tech`, `tip`, `skip_reason` preserved; informational fields refreshed; `amount_changed_from` set when amount differs; unset when amount unchanged.
4. Job missing from sync → `removed_from_hcp = true`; parts still exist.
5. Job that reappears → `removed_from_hcp = false`.
6. Partial unique index rejects a second `in_progress` run for the same `week_start`.

**Integration test** (single flow): start draft → sync → review 3 of 5 jobs (add parts, pick owner, skip one) → re-sync with 2 new + 1 amount change on a reviewed job + 1 removal → assert reviewed jobs preserved, new jobs added with NEW flag, changed job has `amount_changed_from` set, removed job flagged but still present with its parts.

**Manual test plan** (operator):

1. Start fresh payroll for current week. Sync. Review first 3 jobs. Close the tab.
2. Reopen `/payroll/run`. Confirm the resume card appears and lands you at Step 3, Job 4.
3. Re-sync. Observe delta toast and badges. Confirm reviewed jobs look untouched.
4. In Step 3, confirm "Save & exit" returns to Home with the draft listed in the Drafts section.
5. Finalize. Confirm the draft leaves the Drafts section and appears in History.

## Out-of-scope follow-ups

Noted here, not built now:

- Per-sync event log (`payroll_sync_events`) for forensic history.
- Live co-editing indicators if two admins open the same draft.
- Automatic background re-sync on a schedule (e.g., Monday morning cron).
- Reminders/notifications about unfinalized drafts at week-end.
