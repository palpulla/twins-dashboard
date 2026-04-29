# Tech Parts Auto-Detect Design Spec

**Date:** 2026-04-27
**Status:** Design approved, ready for implementation plan
**Scope:** When a tech opens a job in `TechJobDetail`, auto-bucket parts mentioned in HCP `line_items_text` + tech `notes_text` against the `payroll_parts_prices` sheet into three tiers (auto-applied / suggested / unmatched). High-confidence matches are inserted into `payroll_job_parts` automatically. Medium-confidence matches surface as one-tap suggestions. Unmatched lines route to the existing `RequestPartAddModal`. Reuses the existing `partsMatcher.ts` (operator-side) verbatim — no logic drift between admin and tech surfaces.

## Context

Today's tech parts entry flow:

- Tech opens a job at `/tech/jobs/:id` → `TechJobDetail.tsx`
- They search the price sheet via `PartsPickerModal` (`usePricebook` queries `payroll_parts_prices`)
- They tap a part + qty → `add_job_part` RPC inserts into `payroll_job_parts` with the auto-priced cost (price hidden from tech)
- For parts not on the sheet → existing `RequestPartAddModal` files a request that lands in admin's Tech Requests queue

Operator (payroll) parts entry flow:

- Operator runs `/payroll/run` → Step 3 walks per-job
- `partsMatcher.ts` (`extractPartMentions([notes_text, line_items_text], parts)`) parses both inputs and produces fuzzy-matched suggestions with confidence scores
- Operator confirms each suggestion → inserts into `payroll_job_parts`
- Header comment in `partsMatcher.ts` is explicit: "Suggestions are reviewed by the operator in Step 3 — never applied automatically."

Daniel's request: bring the same auto-detect to the tech surface, but tier the confidence so high-confidence matches auto-apply (faster than payroll's all-confirm pattern), medium ones suggest, low/no-match ones route to the existing admin-add request flow. Match from BOTH line_items_text AND notes_text. Custom parts not on the sheet → admin queue.

## Goals

- Reduce tech parts-entry effort to near-zero for the common case (jobs whose HCP invoice lists parts that already exist on the price sheet).
- Surface uncertain matches without auto-committing them, so the tech can reject obvious mismatches.
- Route every unmatched line item to the admin add-part queue with the raw text + source job, so admin can grow the price sheet over time.
- Reuse `partsMatcher.ts` exactly — no parallel matcher implementation, no logic drift.
- Tech never sees prices (existing privacy rule preserved).

## Non-goals

- Changing what data the tech sees about commissions / part prices. Prices stay hidden.
- Backfilling auto-suggestions for already-finalized jobs.
- Cron-based suggestion pre-loading. Lazy-on-view is sufficient.
- Building a tokenizer/scorer in PL/pgSQL. The Edge Function path keeps logic in one place.
- Per-user permissions overrides (Daniel raised this alongside; tracked as a separate spec to follow this one).
- Changing operator-side payroll Run.tsx behavior. The operator continues to confirm each suggestion explicitly.

## Architecture

When `TechJobDetail` mounts (or an explicit "rescan parts" button fires), the client calls a new Edge Function `apply-part-suggestions`. The function:

1. Loads the job (`payroll_jobs` row) for `line_items_text`, `notes_text`, `id`, `submission_status`.
2. Loads the price sheet (`payroll_parts_prices`) — id, name, total_cost.
3. Loads `removed_by_tech` markers for this job (a new column on `payroll_job_parts`) so we don't re-add parts the tech explicitly removed.
4. Runs `extractPartMentions([notes_text, line_items_text], parts)` from `partsMatcher.ts`.
5. Buckets each `PartMatch`:
   - **Auto-apply** — confidence ≥ 0.85 OR exact-match (after `tokenize(line) === tokenize(part.name)`).
   - **Suggested** — 0.5 ≤ confidence < 0.85.
   - **Unmatched** — anything below 0.5, plus any line text the matcher couldn't extract (returned as raw text).
6. For auto-apply: insert into `payroll_job_parts` ONLY if there is no existing row for this `(job_id, lower(name))` AND no `removed_by_tech=true` row exists for the same name. Insert uses the price-sheet's `total_cost × qty`.
7. Returns `{ applied[], suggested[], unmatched[] }` to the client. Suggested + unmatched are transient — recomputed on every invocation.

The client renders three sections in `TechJobDetail` (see Section 3 below). Existing `PartsPickerModal` and `RequestPartAddModal` stay unchanged in behavior; the unmatched section is a router that pre-fills the request modal with the raw line text + source job.

## Data model

### New column on `payroll_job_parts` (one new column, one existing column repurposed)

`payroll_job_parts` ALREADY has a `source text` column (values seen in prod: `'manual'` and `'admin'`). We **reuse** it by adding two new conventional values:
- `'auto'` — inserted by the Edge Function from a high-confidence match.
- `'tech_confirmed'` — inserted via the existing `add_job_part` RPC after the tech tapped Confirm on a suggestion. (Requires updating `add_job_part` to accept an optional source param defaulting to `'manual'`.)

Existing rows with `source = 'manual'` or `'admin'` are untouched.

The single new column we DO add is for the soft-delete marker:

```sql
ALTER TABLE public.payroll_job_parts
  ADD COLUMN IF NOT EXISTS removed_by_tech boolean NOT NULL DEFAULT false;

CREATE INDEX IF NOT EXISTS payroll_job_parts_job_removed_idx
  ON public.payroll_job_parts(job_id) WHERE removed_by_tech;
```

When the tech taps the × on an auto-entered row, instead of a hard delete, we mark `removed_by_tech = true` and the matcher's name de-dup excludes it on next view. Existing operator-side hard-delete (`Run.tsx` — `delete().eq("id", jpId)`) is unchanged; admins can still purge rows entirely.

### New table `tech_part_match_log`

```sql
CREATE TABLE public.tech_part_match_log (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          integer NOT NULL REFERENCES public.payroll_jobs(id) ON DELETE CASCADE,
  tech_id         integer REFERENCES public.payroll_techs(id),
  applied_count   integer NOT NULL DEFAULT 0,
  suggested_count integer NOT NULL DEFAULT 0,
  unmatched_count integer NOT NULL DEFAULT 0,
  raw_inputs_hash text,
  ran_at          timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX tech_part_match_log_job_idx ON public.tech_part_match_log(job_id, ran_at DESC);

ALTER TABLE public.tech_part_match_log ENABLE ROW LEVEL SECURITY;

CREATE POLICY tech_part_match_log_admin_read
  ON public.tech_part_match_log FOR SELECT TO authenticated
  USING (public.has_payroll_access(auth.uid()));
-- WRITE: service role only.
```

`raw_inputs_hash` is a SHA-256 of `notes_text || line_items_text`. Lets us detect when source data changed between matcher runs without storing PII.

### Reuse existing tables

- `payroll_parts_prices` — unchanged, source of truth for prices and matchable names.
- `payroll_job_parts` — gains the two columns above; existing columns and constraints unchanged.
- `modification_requests` (the table that backs `RequestPartAddModal` requests) — already has `job_id`, `notes`, `reasons`, `requested_by`, `technician_id`. **No schema change needed.** The submit payload from the modal will write the raw line text into `notes` and the prefilled part name into `reasons` (jsonb). Admin Tech Requests queue at `/admin/tech-requests` already reads from this table.

## Edge Function: `apply-part-suggestions`

Path: `supabase/functions/apply-part-suggestions/index.ts`. Runs on tech-view trigger only — no cron.

**Input:**

```ts
{ job_id: number }
```

**Auth:** verify_jwt enabled. The caller must be either the tech assigned to the job (`payroll_jobs.owner_tech` matches their `payroll_techs.name` via `current_technician_id()`) OR have `has_payroll_access(auth.uid()) = true` (admin/manager bypass for "view as tech").

**Steps:**

1. Pull the job row: `select id, owner_tech, line_items_text, notes_text, submission_status from payroll_jobs where id = :job_id`. If `submission_status = 'locked'`, return early with empty arrays — finalized jobs don't get re-suggested.
2. Authorization check (above).
3. Pull price sheet: `select id, name, total_cost from payroll_parts_prices`.
4. Pull existing parts: `select lower(name) as norm_name, removed_by_tech from payroll_job_parts where job_id = :job_id`.
5. Run `extractPartMentions([notes_text, line_items_text], partLibrary)` — this is the existing helper, copied into the Edge Function via `import { extractPartMentions } from "./partsMatcher.ts"`. The `partsMatcher.ts` file is symlinked or copied from `src/lib/payroll/partsMatcher.ts` so logic drift is impossible.
6. Bucket matches by confidence; collect any line texts that produced no `PartMatch` as `unmatched`.
7. For each auto-apply candidate: skip if `lower(part.name)` already exists OR has `removed_by_tech=true` for this job.
8. Insert remaining auto-apply rows into `payroll_job_parts` with `source='auto'`, `unit_price = part.total_cost`, `total = part.total_cost × qty`, `entered_by = 'auto'`, `entered_at = now()`. Pre-filtered by step 7's existence check; no unique index needed. All inserts inside a single transaction so a partial failure rolls back.
9. Insert one row into `tech_part_match_log` with the counts.
10. Return JSON `{ applied: [...], suggested: [...], unmatched: [...] }`.

`partsMatcher.ts` shared between client and Edge Function: the existing file at `src/lib/payroll/partsMatcher.ts` has zero browser-only deps and runs in Deno as-is. The Edge Function imports it via a `.ts` import statement using a copy (Supabase Functions can't import from `src/`). To prevent drift: a `vitest` test asserts the byte-identity of `supabase/functions/apply-part-suggestions/partsMatcher.ts` and `src/lib/payroll/partsMatcher.ts` — fails the build if they diverge.

## Client integration

### Hook: `useApplyPartSuggestions(job_id)`

Path: `src/hooks/tech/useApplyPartSuggestions.ts`.

```ts
export function useApplyPartSuggestions(jobId: number | null) {
  return useQuery({
    enabled: !!jobId,
    queryKey: ['apply_part_suggestions', jobId],
    queryFn: async () => {
      const { data, error } = await supabase.functions.invoke(
        'apply-part-suggestions',
        { body: { job_id: jobId } }
      );
      if (error) throw error;
      return data as {
        applied:   { part_id: number; name: string; qty: number; confidence: number; source_line: string }[];
        suggested: { part_id: number; name: string; qty: number; confidence: number; source_line: string }[];
        unmatched: { raw: string; qty: number }[];
      };
    },
    staleTime: 0, // re-run on every navigation to the job
  });
}
```

### `TechJobDetail.tsx` updates

Replace the existing empty state with three new sections, in this order:

1. **Auto-entered** — read existing `payroll_job_parts` rows for this job, render as a list with a × button. The button calls a new RPC `tech_remove_job_part(p_job_part_id integer)` which sets `removed_by_tech = true` (NOT a DELETE). Re-fetches.
2. **Suggested** — read from `useApplyPartSuggestions().data.suggested`. Each row has a Confirm button that calls existing `add_job_part(p_job_id, p_part_name, p_qty)` then refetches both queries.
3. **Couldn't match** — read from `useApplyPartSuggestions().data.unmatched`. Each row has a "Request admin add" button that opens `RequestPartAddModal` pre-filled with the raw line text and source job id.

The existing "+ Add another part" button (which opens `PartsPickerModal`) stays at the bottom of the page — for parts the tech remembers using that didn't surface in either the line items or notes.

### `RequestPartAddModal` updates

The modal currently takes a free-text part name + qty. Add two optional props: `prefillName` and `sourceJobId`. When the unmatched section opens it, pass both. The submit payload writes them to `tech_requests` (or the existing equivalent table) so admin sees "from Karen Mitchell's job · line: 'Custom-cut spring 31"' " in the queue. After submission, the unmatched row gets a "✓ requested" pill so the tech doesn't re-request.

## Idempotency rules (codified)

- **Auto-apply insert** is gated by: no `payroll_job_parts` row with `lower(part_name) = lower(suggested_name)` for this `job_id`, AND no row with `removed_by_tech = true` for the same normalized name.
- **Suggestion list** is transient. Even if the tech reloads the page 10 times, no rows are written for suggestions. The Confirm button is the only path that materializes a suggestion into `payroll_job_parts`.
- **Unmatched list** is transient. Tapping "Request admin add" inserts a `tech_requests` row exactly once (the modal's existing submit guard handles double-tap). The "✓ requested" pill is read from the existing `tech_requests` table by `(source_job_id, source_line_text)`.
- **Removal**: tech taps × → `removed_by_tech = true`. Subsequent matcher runs see this and skip re-adding. If the tech later wants the part back, they use `PartsPickerModal` to add it manually — that path resets `removed_by_tech = false` (or inserts a new row, depending on how `add_job_part` handles existing names).
- **Locked jobs**: when `submission_status = 'locked'`, the Edge Function returns empty arrays. Locked jobs don't re-suggest.

## Testing

### Unit tests in `supabase/functions/apply-part-suggestions/__tests__/`

- `bucketing.test.ts` — feed synthetic `PartMatch[]` arrays at confidence 0.95 / 0.7 / 0.4 / no-match → assert correct bucket assignment.
- `idempotency.test.ts` — invoke twice against an in-memory Postgres mock or a fixture DB; assert `payroll_job_parts` row count is the same after both calls.
- `removal.test.ts` — auto-apply, mark `removed_by_tech=true`, invoke again → assert no re-add.

### Integration tests in `src/lib/payroll/__tests__/partsMatcher.test.ts` (extend existing)

- Real-fixture tests using actual `line_items_text` blobs from a handful of sample `payroll_jobs` rows. Run the matcher against the current `payroll_parts_prices` snapshot. Lock the bucketing output. Re-running on `main` must match.

### Identity test

- `src/lib/payroll/__tests__/matcher-identity.test.ts` — assert `supabase/functions/apply-part-suggestions/partsMatcher.ts` is byte-identical to `src/lib/payroll/partsMatcher.ts`. Fails the build on drift.

## Observability

- `tech_part_match_log` writes one row per Edge Function invocation. Admin queries: "How often is the matcher firing? What's the suggested-vs-auto split? Are unmatched counts trending up (price sheet stale)?"
- The existing admin Tech Requests queue at `/admin/tech-requests` shows requested-add entries with the new `source_job_id` + `source_line_text` context.
- No new dashboard or chart for this in v1. The log + the queue are sufficient.

## File structure

### New files

- `supabase/functions/apply-part-suggestions/index.ts`
- `supabase/functions/apply-part-suggestions/partsMatcher.ts` (verbatim copy of `src/lib/payroll/partsMatcher.ts`)
- `supabase/functions/apply-part-suggestions/__tests__/bucketing.test.ts`
- `supabase/functions/apply-part-suggestions/__tests__/idempotency.test.ts`
- `supabase/functions/apply-part-suggestions/__tests__/removal.test.ts`
- `supabase/migrations/<YYYYMMDDhhmmss>_payroll_job_parts_removed_by_tech.sql` — adds the `removed_by_tech` boolean column + partial index. The existing `source` text column is reused (no schema change).
- `supabase/migrations/<YYYYMMDDhhmmss>_tech_part_match_log.sql` — new table + RLS
- `supabase/migrations/<YYYYMMDDhhmmss>_tech_remove_job_part_rpc.sql` — new RPC `tech_remove_job_part(p_job_part_id)` that sets `removed_by_tech = true` (gated by RLS to the tech who owns the job, or admin)
- `supabase/migrations/<YYYYMMDDhhmmss>_add_job_part_source_param.sql` — extends the existing `add_job_part` RPC with an optional `p_source text default 'manual'` param so the suggestion-confirm flow can pass `'tech_confirmed'`
- `src/hooks/tech/useApplyPartSuggestions.ts`
- `src/lib/payroll/__tests__/matcher-identity.test.ts`

### Modified files

- `src/pages/tech/TechJobDetail.tsx` — add three sections; wire the new hook
- `src/components/tech/RequestPartAddModal.tsx` — add `prefillName` + `sourceLineText` + `sourceJobId` props; submit payload writes `sourceLineText` to `modification_requests.notes` and `prefillName` to `modification_requests.reasons.requested_part_name`
- `src/components/tech/PartsPickerModal.tsx` — no functional change; still the fallback. (Internally, the Confirm button on a suggestion also calls `add_job_part` with `p_source = 'tech_confirmed'`.)

### Untouched

- `src/lib/payroll/partsMatcher.ts` — sacred. The identity test enforces this.
- `src/pages/payroll/Run.tsx` — operator-side flow unchanged.
- `kpi-calculations.ts`, all KPI hooks — math untouched.

## Risk + rollback

- All migrations are additive (new columns, new table, new RPC). Reversion is dropping the new columns/table.
- Edge Function deploys are independent — can be paused without breaking the existing tech parts flow (the modal-based picker still works as today).
- If the matcher is too aggressive (false-positive auto-applies), the threshold (0.85) lives in one constant in the Edge Function. Bumping it to 0.9 / 0.95 is a one-line change + redeploy.
- Worst-case rollback: delete the Edge Function, drop the new columns/table, revert the `TechJobDetail.tsx` changes — back to the existing picker-only flow.

## Open questions resolved during brainstorm

- **Match input sources**: BOTH `line_items_text` AND `notes_text`. Confirmed by Daniel.
- **Confidence tiers**: 0.85 / 0.5 thresholds. Confirmed.
- **Custom parts**: route to existing `RequestPartAddModal` with prefill. Confirmed.
- **Trigger**: lazy on tech-view (no cron, no webhook hook). Inherited from payroll's pattern.
- **Tech sees prices**: NO — existing privacy rule preserved.
- **Operator-side flow**: untouched. Operator still confirms every suggestion in `Run.tsx` Step 3.
