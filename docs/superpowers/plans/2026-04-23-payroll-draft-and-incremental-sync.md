# Payroll Draft & Incremental Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make in-progress payroll runs resumable "drafts" that merge new/changed HCP jobs on re-sync instead of wiping operator review work.

**Architecture:** Add new columns to `payroll_runs` (current_step, review_idx, last_sync_at) and `payroll_jobs` (first_synced_at, last_synced_at, removed_from_hcp, amount_changed_from, is_reviewed). Replace the destructive `delete + insert` in `syncHCP` with a Postgres RPC `merge_payroll_jobs` that atomically merges by `(run_id, hcp_id)` while preserving operator edits on reviewed jobs. Add a resume flow to `/payroll/run` and a Drafts section to `/payroll`.

**Tech Stack:** React + TypeScript + Vite, Supabase (Postgres + RLS + RPC), Vitest (jsdom), Tailwind + shadcn/ui, date-fns. Migrations applied via the Supabase MCP `apply_migration` tool against project ref `zjjdrkbgprctsxvagcqb` (the live twins-dash project).

**Source spec:** [docs/superpowers/specs/2026-04-23-payroll-draft-and-incremental-sync-design.md](../specs/2026-04-23-payroll-draft-and-incremental-sync-design.md)

---

## File Structure

**Create:**
- `twins-dash/supabase/migrations/20260423180000_payroll_draft_columns.sql` — column additions + unique-index swap
- `twins-dash/supabase/migrations/20260423180100_payroll_merge_rpc.sql` — `merge_payroll_jobs` RPC
- `twins-dash/src/lib/payroll/syncMerge.ts` — pure helpers: `computeDeltaCounts`, `computeJobBadges`, `buildRpcPayload`
- `twins-dash/src/lib/payroll/__tests__/syncMerge.test.ts` — Vitest unit tests for pure helpers
- `twins-dash/src/components/payroll/ResumeDraftCard.tsx` — shown on `/payroll/run` when a draft exists
- `twins-dash/src/components/payroll/DraftsSection.tsx` — shown on `/payroll` home above history
- `twins-dash/src/components/payroll/SyncDeltaBadges.tsx` — NEW / Δ / Removed inline badges

**Modify:**
- `twins-dash/src/pages/payroll/Run.tsx` — resume hydration, RPC call, persisted `current_step`/`review_idx`, amount-changed banner, `is_reviewed` flip, removed-jobs warning, Save & exit button
- `twins-dash/src/pages/payroll/Home.tsx` — render `DraftsSection`

**Untouched (but referenced):**
- `twins-dash/src/lib/payroll/ownership.ts` — `resolveOwner` stays
- `twins-dash/src/lib/payroll/commission.ts` — `computeCommissions` stays
- `twins-dash/src/lib/payroll/tipExtraction.ts` — `stripGratuityFromLineItems` stays
- `twins-dash/supabase/functions/sync-hcp-week` — edge function unchanged

---

## Task 1: DB migration — new columns and unique-index swap

**Files:**
- Create: `twins-dash/supabase/migrations/20260423180000_payroll_draft_columns.sql`

**Context:** The existing `payroll_runs` table has a composite `UNIQUE (week_start, status)` constraint that blocks any second row for the same `(week_start, status)` pair. That's too restrictive — two `superseded` runs for the same week are perfectly valid history. We drop it and replace with a partial unique index that only blocks duplicate `in_progress` drafts.

- [ ] **Step 1: Create the migration file**

Write `twins-dash/supabase/migrations/20260423180000_payroll_draft_columns.sql` with:

```sql
-- Payroll draft/resume columns and unique-index swap.

-- New columns on payroll_runs for resume state
ALTER TABLE public.payroll_runs
  ADD COLUMN IF NOT EXISTS current_step SMALLINT NOT NULL DEFAULT 1 CHECK (current_step BETWEEN 1 AND 4),
  ADD COLUMN IF NOT EXISTS review_idx   INTEGER  NOT NULL DEFAULT 0 CHECK (review_idx >= 0),
  ADD COLUMN IF NOT EXISTS last_sync_at TIMESTAMPTZ;

-- Replace composite UNIQUE(week_start, status) with a partial unique index
-- that only forbids two concurrent in_progress drafts for the same week.
ALTER TABLE public.payroll_runs
  DROP CONSTRAINT IF EXISTS payroll_runs_week_start_status_key;

CREATE UNIQUE INDEX IF NOT EXISTS payroll_runs_one_draft_per_week
  ON public.payroll_runs(week_start)
  WHERE status = 'in_progress';

-- New columns on payroll_jobs for sync-delta tracking
ALTER TABLE public.payroll_jobs
  ADD COLUMN IF NOT EXISTS first_synced_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  ADD COLUMN IF NOT EXISTS last_synced_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  ADD COLUMN IF NOT EXISTS removed_from_hcp    BOOLEAN     NOT NULL DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS amount_changed_from NUMERIC(10,2),
  ADD COLUMN IF NOT EXISTS is_reviewed         BOOLEAN     NOT NULL DEFAULT FALSE;

-- Back-fill: any job that already exists predates this migration, so treat
-- it as "first seen now" for delta calculations. This is cosmetic — any
-- existing run that the operator resumes will immediately see all jobs as
-- NEW on first post-migration sync, which is fine (nothing was "reviewed"
-- under the new definition yet either, so the merge logic will refresh
-- informational fields as case 2).
UPDATE public.payroll_jobs
  SET first_synced_at = NOW(), last_synced_at = NOW()
  WHERE first_synced_at IS NULL OR last_synced_at IS NULL;

-- Index to speed up the "what's new since last sync" badge query
CREATE INDEX IF NOT EXISTS idx_payroll_jobs_last_synced_at
  ON public.payroll_jobs(run_id, last_synced_at);
```

- [ ] **Step 2: Apply the migration to the live Supabase project**

Use the Supabase MCP tool `mcp__a13384b5-3518-4c7c-9b61-a7f2786de7db__apply_migration` with:
- `project_id`: `zjjdrkbgprctsxvagcqb`
- `name`: `payroll_draft_columns`
- `query`: the full SQL body from Step 1

Expected: migration succeeds, no error. If the `DROP CONSTRAINT IF EXISTS payroll_runs_week_start_status_key` name doesn't match the actual constraint name generated by Postgres, query `pg_constraint` first to find the real name:

```sql
SELECT conname FROM pg_constraint
WHERE conrelid = 'public.payroll_runs'::regclass AND contype = 'u';
```

Update the DROP line in the migration file to match and re-apply.

- [ ] **Step 3: Verify the schema**

Run via `mcp__...__execute_sql`:

```sql
SELECT column_name, data_type, column_default, is_nullable
FROM information_schema.columns
WHERE table_schema='public' AND table_name IN ('payroll_runs','payroll_jobs')
  AND column_name IN ('current_step','review_idx','last_sync_at',
    'first_synced_at','last_synced_at','removed_from_hcp',
    'amount_changed_from','is_reviewed')
ORDER BY table_name, column_name;
```

Expected: 8 rows returned with the types and defaults specified above.

Then verify the partial unique index exists:

```sql
SELECT indexdef FROM pg_indexes
WHERE schemaname='public' AND indexname='payroll_runs_one_draft_per_week';
```

Expected: one row whose `indexdef` includes `WHERE (status = 'in_progress'::text)`.

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/supabase/migrations/20260423180000_payroll_draft_columns.sql
git commit -m "$(cat <<'EOF'
feat(payroll): add draft/sync-delta columns + partial unique index

- payroll_runs: current_step, review_idx, last_sync_at
- payroll_jobs: first_synced_at, last_synced_at, removed_from_hcp,
  amount_changed_from, is_reviewed
- Swap UNIQUE(week_start, status) for a partial unique index on
  (week_start) WHERE status='in_progress' to allow multiple superseded
  runs for the same week.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: `merge_payroll_jobs` RPC

**Files:**
- Create: `twins-dash/supabase/migrations/20260423180100_payroll_merge_rpc.sql`

**Context:** Replaces `syncHCP`'s `delete + insert` block (Run.tsx:201) with an atomic server-side merge. The function takes the run ID, the sync timestamp, and the incoming normalized jobs (JSONB array). It performs five cases per spec section "Sync merge logic": new, existing-unreviewed, existing-reviewed, missing-from-sync (flagged), reappearing (unflagged). Returns a delta count JSON so the client can toast.

- [ ] **Step 1: Create the migration file**

Write `twins-dash/supabase/migrations/20260423180100_payroll_merge_rpc.sql`:

```sql
-- Merge incoming HCP jobs into a payroll run, preserving operator edits
-- on reviewed jobs. Atomic (function body runs in a single transaction).
--
-- p_jobs shape (per element):
--   {
--     "hcp_id": text, "hcp_job_number": text, "job_date": date,
--     "customer_display": text|null, "description": text|null,
--     "line_items_text": text|null, "notes_text": text|null,
--     "amount": numeric, "subtotal": numeric, "discount": numeric,
--     "tip": numeric, "raw_techs": text,   -- comma-joined
--     "owner_tech": text|null, "skip_reason": text|null
--   }
--
-- Returns: { "new": int, "changed": int, "removed": int, "unchanged": int }

CREATE OR REPLACE FUNCTION public.merge_payroll_jobs(
  p_run_id    INT,
  p_sync_ts   TIMESTAMPTZ,
  p_jobs      JSONB
)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path = public
AS $$
DECLARE
  v_new       INT := 0;
  v_changed   INT := 0;
  v_removed   INT := 0;
  v_unchanged INT := 0;
  v_incoming_ids TEXT[];
  v_job       JSONB;
  v_existing  RECORD;
  v_new_amount NUMERIC;
BEGIN
  -- Collect incoming hcp_ids for the "removed" pass
  SELECT COALESCE(array_agg(j->>'hcp_id'), ARRAY[]::TEXT[])
    INTO v_incoming_ids
  FROM jsonb_array_elements(p_jobs) j;

  -- Per-job upsert
  FOR v_job IN SELECT * FROM jsonb_array_elements(p_jobs) LOOP
    SELECT id, amount, is_reviewed, amount_changed_from, removed_from_hcp
      INTO v_existing
      FROM public.payroll_jobs
      WHERE run_id = p_run_id AND hcp_id = v_job->>'hcp_id';

    IF v_existing.id IS NULL THEN
      -- Case 1: New job
      INSERT INTO public.payroll_jobs (
        run_id, hcp_id, hcp_job_number, job_date,
        customer_display, description, line_items_text, notes_text,
        amount, subtotal, discount, tip,
        raw_techs, owner_tech, skip_reason,
        first_synced_at, last_synced_at, is_reviewed, removed_from_hcp
      ) VALUES (
        p_run_id,
        v_job->>'hcp_id',
        v_job->>'hcp_job_number',
        (v_job->>'job_date')::DATE,
        v_job->>'customer_display',
        v_job->>'description',
        v_job->>'line_items_text',
        v_job->>'notes_text',
        (v_job->>'amount')::NUMERIC,
        COALESCE((v_job->>'subtotal')::NUMERIC, 0),
        COALESCE((v_job->>'discount')::NUMERIC, 0),
        COALESCE((v_job->>'tip')::NUMERIC, 0),
        v_job->>'raw_techs',
        v_job->>'owner_tech',
        v_job->>'skip_reason',
        p_sync_ts, p_sync_ts, FALSE, FALSE
      );
      v_new := v_new + 1;

    ELSIF NOT v_existing.is_reviewed THEN
      -- Case 2: Existing, not reviewed → full refresh
      UPDATE public.payroll_jobs SET
        customer_display  = v_job->>'customer_display',
        description       = v_job->>'description',
        line_items_text   = v_job->>'line_items_text',
        notes_text        = v_job->>'notes_text',
        amount            = (v_job->>'amount')::NUMERIC,
        subtotal          = COALESCE((v_job->>'subtotal')::NUMERIC, 0),
        discount          = COALESCE((v_job->>'discount')::NUMERIC, 0),
        tip               = COALESCE((v_job->>'tip')::NUMERIC, 0),
        raw_techs         = v_job->>'raw_techs',
        owner_tech        = v_job->>'owner_tech',
        last_synced_at    = p_sync_ts,
        removed_from_hcp  = FALSE
      WHERE id = v_existing.id;
      v_unchanged := v_unchanged + 1;

    ELSE
      -- Case 3: Existing, reviewed → informational refresh only, flag amount change
      v_new_amount := (v_job->>'amount')::NUMERIC;
      UPDATE public.payroll_jobs SET
        customer_display     = v_job->>'customer_display',
        description          = v_job->>'description',
        line_items_text      = v_job->>'line_items_text',
        notes_text           = v_job->>'notes_text',
        raw_techs            = v_job->>'raw_techs',
        amount               = v_new_amount,
        amount_changed_from  = CASE
          WHEN v_new_amount <> v_existing.amount
            THEN COALESCE(v_existing.amount_changed_from, v_existing.amount)
          ELSE v_existing.amount_changed_from
        END,
        last_synced_at       = p_sync_ts,
        removed_from_hcp     = FALSE
      WHERE id = v_existing.id;
      IF v_new_amount <> v_existing.amount THEN
        v_changed := v_changed + 1;
      ELSE
        v_unchanged := v_unchanged + 1;
      END IF;
    END IF;
  END LOOP;

  -- Case 4: flag jobs in the run that did NOT appear in this sync
  UPDATE public.payroll_jobs
     SET removed_from_hcp = TRUE
   WHERE run_id = p_run_id
     AND hcp_id <> ALL(v_incoming_ids)
     AND removed_from_hcp = FALSE;
  GET DIAGNOSTICS v_removed = ROW_COUNT;

  -- Stamp the run's last_sync_at so the UI can branch on "first sync vs re-sync"
  UPDATE public.payroll_runs
     SET last_sync_at = p_sync_ts
   WHERE id = p_run_id;

  RETURN jsonb_build_object(
    'new',       v_new,
    'changed',   v_changed,
    'removed',   v_removed,
    'unchanged', v_unchanged
  );
END;
$$;

-- Grant execute to authenticated role (RLS on payroll_jobs still applies
-- because the function is SECURITY INVOKER).
GRANT EXECUTE ON FUNCTION public.merge_payroll_jobs(INT, TIMESTAMPTZ, JSONB) TO authenticated;
```

- [ ] **Step 2: Apply via the Supabase MCP**

Use `mcp__...__apply_migration` with:
- `project_id`: `zjjdrkbgprctsxvagcqb`
- `name`: `payroll_merge_rpc`
- `query`: the SQL body above

Expected: success.

- [ ] **Step 3: Smoke-test the RPC against a disposable run**

Via `mcp__...__execute_sql`, create a throwaway run + job, invoke the RPC, verify delta counts, then clean up:

```sql
-- Setup
WITH new_run AS (
  INSERT INTO public.payroll_runs (week_start, week_end, status)
  VALUES ('2099-01-05', '2099-01-11', 'in_progress')
  RETURNING id
)
SELECT id FROM new_run;  -- remember RUN_ID

-- Seed one existing reviewed job at amount=100
INSERT INTO public.payroll_jobs (
  run_id, hcp_id, hcp_job_number, job_date, amount, subtotal,
  raw_techs, owner_tech, is_reviewed
) VALUES (
  :RUN_ID, 'TEST-OLD', 'OLD-1', '2099-01-06', 100, 100,
  'Maurice Williams', 'Maurice Williams', TRUE
);

-- Call the RPC with one update (amount change on reviewed job) + one new job
SELECT public.merge_payroll_jobs(
  :RUN_ID,
  NOW(),
  '[
    {"hcp_id":"TEST-OLD","hcp_job_number":"OLD-1","job_date":"2099-01-06",
     "customer_display":"C","description":"D","line_items_text":"L","notes_text":"N",
     "amount":120,"subtotal":120,"discount":0,"tip":0,"raw_techs":"Maurice Williams",
     "owner_tech":"Maurice Williams","skip_reason":null},
    {"hcp_id":"TEST-NEW","hcp_job_number":"NEW-1","job_date":"2099-01-07",
     "customer_display":"C2","description":"D2","line_items_text":"L2","notes_text":"N2",
     "amount":50,"subtotal":50,"discount":0,"tip":0,"raw_techs":"Nicholas Roccaforte",
     "owner_tech":"Nicholas Roccaforte","skip_reason":null}
  ]'::jsonb
);

-- Verify: OLD job has amount=120, amount_changed_from=100, still reviewed; NEW job exists
SELECT hcp_id, amount, amount_changed_from, is_reviewed, removed_from_hcp
  FROM public.payroll_jobs WHERE run_id = :RUN_ID ORDER BY hcp_id;

-- Second call with only TEST-NEW → TEST-OLD should get removed_from_hcp=TRUE
SELECT public.merge_payroll_jobs(
  :RUN_ID, NOW(),
  '[
    {"hcp_id":"TEST-NEW","hcp_job_number":"NEW-1","job_date":"2099-01-07",
     "customer_display":"C2","description":"D2","line_items_text":"L2","notes_text":"N2",
     "amount":50,"subtotal":50,"discount":0,"tip":0,"raw_techs":"Nicholas Roccaforte",
     "owner_tech":"Nicholas Roccaforte","skip_reason":null}
  ]'::jsonb
);

SELECT hcp_id, removed_from_hcp FROM public.payroll_jobs WHERE run_id = :RUN_ID;
-- Expected: TEST-OLD.removed_from_hcp = TRUE, TEST-NEW.removed_from_hcp = FALSE

-- Cleanup
DELETE FROM public.payroll_runs WHERE id = :RUN_ID;
```

Expected output sequence:
- First RPC: `{"new":1,"changed":1,"removed":0,"unchanged":0}`
- First SELECT: TEST-OLD has amount=120, amount_changed_from=100, is_reviewed=true; TEST-NEW exists with is_reviewed=false
- Second RPC: `{"new":0,"changed":0,"removed":1,"unchanged":1}`
- Second SELECT: TEST-OLD.removed_from_hcp=true

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/supabase/migrations/20260423180100_payroll_merge_rpc.sql
git commit -m "$(cat <<'EOF'
feat(payroll): merge_payroll_jobs RPC for non-destructive re-sync

Replaces the delete+insert pattern with an atomic per-job upsert that
preserves owner_tech, tip, and skip_reason on reviewed jobs, flags
amount changes with amount_changed_from, and marks jobs missing from
the sync as removed_from_hcp=true without deleting them.

Returns a delta summary {new, changed, removed, unchanged} so the
client can surface what changed.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Pure helpers + unit tests

**Files:**
- Create: `twins-dash/src/lib/payroll/syncMerge.ts`
- Create: `twins-dash/src/lib/payroll/__tests__/syncMerge.test.ts`

**Context:** The UI needs to compute delta counts and per-job badges from the already-loaded `DBJob` rows (no extra DB call). Keep this logic pure and testable. The RPC returns a delta summary after sync, but re-computing client-side from `last_synced_at = run.last_sync_at` lets the badges persist across page reloads (the RPC's return value is ephemeral).

- [ ] **Step 1: Write the failing test first (TDD)**

Create `twins-dash/src/lib/payroll/__tests__/syncMerge.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import {
  computeJobBadges,
  computeDeltaCounts,
  buildRpcPayload,
} from "../syncMerge";

const SYNC_A = "2026-04-22T12:00:00.000Z";
const SYNC_B = "2026-04-23T09:30:00.000Z";

describe("computeJobBadges", () => {
  it("flags NEW when first==last==runLastSyncAt", () => {
    const b = computeJobBadges(
      { first_synced_at: SYNC_B, last_synced_at: SYNC_B, amount_changed_from: null, removed_from_hcp: false },
      SYNC_B,
    );
    expect(b).toEqual({ isNew: true, hasAmountChange: false, isRemoved: false });
  });

  it("does not flag NEW for a pre-existing job that was re-synced", () => {
    const b = computeJobBadges(
      { first_synced_at: SYNC_A, last_synced_at: SYNC_B, amount_changed_from: null, removed_from_hcp: false },
      SYNC_B,
    );
    expect(b.isNew).toBe(false);
  });

  it("flags amount change when amount_changed_from is non-null", () => {
    const b = computeJobBadges(
      { first_synced_at: SYNC_A, last_synced_at: SYNC_B, amount_changed_from: 100, removed_from_hcp: false },
      SYNC_B,
    );
    expect(b.hasAmountChange).toBe(true);
  });

  it("flags removed when removed_from_hcp is true", () => {
    const b = computeJobBadges(
      { first_synced_at: SYNC_A, last_synced_at: SYNC_A, amount_changed_from: null, removed_from_hcp: true },
      SYNC_B,
    );
    expect(b.isRemoved).toBe(true);
  });

  it("returns all false when runLastSyncAt is null (first-time, no sync yet)", () => {
    const b = computeJobBadges(
      { first_synced_at: SYNC_A, last_synced_at: SYNC_A, amount_changed_from: null, removed_from_hcp: false },
      null,
    );
    expect(b).toEqual({ isNew: false, hasAmountChange: false, isRemoved: false });
  });

  it("accepts amount_changed_from as a string (Postgres NUMERIC → string in supabase-js)", () => {
    const b = computeJobBadges(
      { first_synced_at: SYNC_A, last_synced_at: SYNC_B, amount_changed_from: "99.50", removed_from_hcp: false },
      SYNC_B,
    );
    expect(b.hasAmountChange).toBe(true);
  });
});

describe("computeDeltaCounts", () => {
  it("counts NEW, changed, and removed across a mixed list", () => {
    const jobs = [
      { first_synced_at: SYNC_B, last_synced_at: SYNC_B, amount_changed_from: null, removed_from_hcp: false },  // new
      { first_synced_at: SYNC_B, last_synced_at: SYNC_B, amount_changed_from: null, removed_from_hcp: false },  // new
      { first_synced_at: SYNC_A, last_synced_at: SYNC_B, amount_changed_from: 100, removed_from_hcp: false },   // changed
      { first_synced_at: SYNC_A, last_synced_at: SYNC_A, amount_changed_from: null, removed_from_hcp: true },   // removed
      { first_synced_at: SYNC_A, last_synced_at: SYNC_B, amount_changed_from: null, removed_from_hcp: false },  // unchanged
    ];
    expect(computeDeltaCounts(jobs, SYNC_B)).toEqual({
      newCount: 2, changedCount: 1, removedCount: 1,
    });
  });

  it("a single job can be counted in multiple buckets", () => {
    // A job synced fresh this round AND flagged removed. Defensive: count each bucket independently.
    const jobs = [
      { first_synced_at: SYNC_B, last_synced_at: SYNC_B, amount_changed_from: 50, removed_from_hcp: true },
    ];
    const d = computeDeltaCounts(jobs, SYNC_B);
    expect(d.newCount + d.changedCount + d.removedCount).toBe(3);
  });

  it("returns zeros for empty list", () => {
    expect(computeDeltaCounts([], SYNC_B)).toEqual({
      newCount: 0, changedCount: 0, removedCount: 0,
    });
  });
});

describe("buildRpcPayload", () => {
  it("packs a normalized job into the RPC shape", () => {
    const payload = buildRpcPayload({
      normalized: {
        hcp_id: "abc123",
        hcp_job_number: "J-1001",
        job_date: "2026-04-20T13:45:00Z",
        customer_display: "Acme",
        description: "Install springs",
        line_items_text: "SERVICES\n  1x Labor - $200",
        notes_text: ".243 #2 30.5\" springs",
        amount: 400,
        subtotal: 400,
        discount: 0,
        tip: 20,
        raw_techs: ["Maurice Williams", "Charles Rue"],
      },
      fallbackJobDate: "2026-04-19",
      ownerTech: "Maurice Williams",
      strippedLineItems: "SERVICES\n  1x Labor - $200",
      tip: 20,
    });
    expect(payload).toEqual({
      hcp_id: "abc123",
      hcp_job_number: "J-1001",
      job_date: "2026-04-20",
      customer_display: "Acme",
      description: "Install springs",
      line_items_text: "SERVICES\n  1x Labor - $200",
      notes_text: ".243 #2 30.5\" springs",
      amount: 400,
      subtotal: 400,
      discount: 0,
      tip: 20,
      raw_techs: "Maurice Williams, Charles Rue",
      owner_tech: "Maurice Williams",
      skip_reason: null,
    });
  });

  it("falls back to fallbackJobDate when normalized.job_date is null", () => {
    const payload = buildRpcPayload({
      normalized: { hcp_id: "x", hcp_job_number: "Y", job_date: null, raw_techs: [] },
      fallbackJobDate: "2026-04-19",
      ownerTech: null,
      strippedLineItems: "",
      tip: 0,
    });
    expect(payload.job_date).toBe("2026-04-19");
  });

  it("sets skip_reason='zero_revenue' when amount is 0", () => {
    const payload = buildRpcPayload({
      normalized: { hcp_id: "x", hcp_job_number: "Y", job_date: "2026-04-19T00:00:00Z", amount: 0, raw_techs: [] },
      fallbackJobDate: "2026-04-19",
      ownerTech: null,
      strippedLineItems: "",
      tip: 0,
    });
    expect(payload.skip_reason).toBe("zero_revenue");
  });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx vitest run src/lib/payroll/__tests__/syncMerge.test.ts
```

Expected: FAIL with module-not-found — `../syncMerge` doesn't exist yet.

- [ ] **Step 3: Write the implementation**

Create `twins-dash/src/lib/payroll/syncMerge.ts`:

```ts
// Pure helpers for sync-delta UI. No DB access — operates on DBJob rows
// already loaded by Run.tsx.

export type DBJobForMerge = {
  first_synced_at: string | null;
  last_synced_at: string | null;
  amount_changed_from: number | string | null;
  removed_from_hcp: boolean;
};

export type JobBadges = {
  isNew: boolean;
  hasAmountChange: boolean;
  isRemoved: boolean;
};

export type DeltaCounts = {
  newCount: number;
  changedCount: number;
  removedCount: number;
};

/**
 * A job is "new in the latest sync" if its first_synced_at equals its
 * last_synced_at AND equals the run's last_sync_at. Equality is string
 * compare because Postgres TIMESTAMPTZ returns ISO strings; the RPC
 * stamps both fields with the same value on insert.
 */
export function computeJobBadges(
  job: DBJobForMerge,
  runLastSyncAt: string | null,
): JobBadges {
  const isNew =
    !!runLastSyncAt &&
    job.first_synced_at === runLastSyncAt &&
    job.last_synced_at === runLastSyncAt;
  const hasAmountChange =
    job.amount_changed_from !== null && job.amount_changed_from !== undefined;
  const isRemoved = job.removed_from_hcp === true;
  return { isNew, hasAmountChange, isRemoved };
}

export function computeDeltaCounts(
  jobs: DBJobForMerge[],
  runLastSyncAt: string | null,
): DeltaCounts {
  let newCount = 0;
  let changedCount = 0;
  let removedCount = 0;
  for (const j of jobs) {
    const b = computeJobBadges(j, runLastSyncAt);
    if (b.isNew) newCount++;
    if (b.hasAmountChange) changedCount++;
    if (b.isRemoved) removedCount++;
  }
  return { newCount, changedCount, removedCount };
}

/**
 * Shape of one element in the p_jobs JSONB array the RPC expects.
 * Must match the PL/pgSQL in merge_payroll_jobs.
 */
export type RpcJobInput = {
  hcp_id: string;
  hcp_job_number: string;
  job_date: string;
  customer_display: string | null;
  description: string | null;
  line_items_text: string | null;
  notes_text: string | null;
  amount: number;
  subtotal: number;
  discount: number;
  tip: number;
  raw_techs: string; // comma-joined
  owner_tech: string | null;
  skip_reason: string | null;
};

/**
 * Build the RPC payload from a normalized edge-function job row. Keeps
 * the shape contract in one place so Run.tsx doesn't hand-roll it.
 */
export function buildRpcPayload(args: {
  normalized: {
    hcp_id: string;
    hcp_job_number: string;
    job_date: string | null;
    customer_display?: string | null;
    description?: string | null;
    line_items_text?: string | null;
    notes_text?: string | null;
    amount?: number | null;
    subtotal?: number | null;
    discount?: number | null;
    tip?: number | null;
    raw_techs?: string[] | null;
  };
  fallbackJobDate: string; // ISO "YYYY-MM-DD"
  ownerTech: string | null;
  strippedLineItems: string;
  tip: number;
}): RpcJobInput {
  const n = args.normalized;
  const amount = Number(n.amount ?? 0);
  return {
    hcp_id: n.hcp_id,
    hcp_job_number: n.hcp_job_number,
    job_date: n.job_date ? n.job_date.slice(0, 10) : args.fallbackJobDate,
    customer_display: n.customer_display ?? null,
    description: n.description ?? null,
    line_items_text: args.strippedLineItems,
    notes_text: n.notes_text ?? null,
    amount,
    subtotal: Number(n.subtotal ?? 0),
    discount: Number(n.discount ?? 0),
    tip: args.tip,
    raw_techs: (n.raw_techs ?? []).join(", "),
    owner_tech: args.ownerTech,
    skip_reason: amount === 0 ? "zero_revenue" : null,
  };
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx vitest run src/lib/payroll/__tests__/syncMerge.test.ts
```

Expected: all 12 test cases PASS. If any FAIL, that's a bug in the Step-3 implementation — fix it before moving on.

- [ ] **Step 5: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/src/lib/payroll/syncMerge.ts twins-dash/src/lib/payroll/__tests__/syncMerge.test.ts
git commit -m "$(cat <<'EOF'
feat(payroll): pure helpers for sync-delta badges + RPC payload

computeJobBadges / computeDeltaCounts drive the NEW, Δ, Removed
indicators in Step 2 and Step 3 titles. buildRpcPayload centralizes
the contract between the client and merge_payroll_jobs so Run.tsx
doesn't hand-roll JSON shapes.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Replace `syncHCP` with RPC call

**Files:**
- Modify: `twins-dash/src/pages/payroll/Run.tsx` — `syncHCP` function (currently lines 156-219), `DBJob` type (lines 34-40), and the initial jobs-load in `useEffect` (currently not explicit — jobs load happens after sync)

**Context:** Replace the `delete().eq("run_id", runId)` + `insert(rows)` pattern with a single `supabase.rpc('merge_payroll_jobs', ...)`. After the RPC, re-read the jobs. Surface the delta summary as a toast. Also extend `DBJob` to include the new columns so TypeScript stops complaining when we read them in later tasks.

- [ ] **Step 1: Extend the `DBJob` type in Run.tsx**

In `twins-dash/src/pages/payroll/Run.tsx`, find (currently lines 34-40):

```ts
type DBJob = {
  id: number; hcp_id: string; hcp_job_number: string; job_date: string;
  customer_display: string | null; description: string | null;
  line_items_text: string | null; notes_text: string | null;
  amount: number; tip: number; subtotal: number; discount: number;
  raw_techs: string | null; owner_tech: string | null; skip_reason: string | null;
};
```

Replace with:

```ts
type DBJob = {
  id: number; hcp_id: string; hcp_job_number: string; job_date: string;
  customer_display: string | null; description: string | null;
  line_items_text: string | null; notes_text: string | null;
  amount: number; tip: number; subtotal: number; discount: number;
  raw_techs: string | null; owner_tech: string | null; skip_reason: string | null;
  first_synced_at: string | null; last_synced_at: string | null;
  removed_from_hcp: boolean; amount_changed_from: number | string | null;
  is_reviewed: boolean;
};
```

- [ ] **Step 2: Add a `DBRun` type and state for it**

Still in `Run.tsx`, after the `DBBonusTier` type (currently line 46), add:

```ts
type DBRun = {
  id: number;
  week_start: string; week_end: string;
  status: "in_progress" | "final" | "superseded";
  current_step: number; review_idx: number;
  last_sync_at: string | null;
};
```

In the `Run` component state block (currently around line 96-109), add:

```ts
const [runLastSyncAt, setRunLastSyncAt] = useState<string | null>(null);
```

(Place it next to `const [runId, setRunId] = useState<number | null>(null);`.)

- [ ] **Step 3: Import the new helpers**

At the top of `Run.tsx`, next to the existing imports from `@/lib/payroll/*`, add:

```ts
import { buildRpcPayload, computeDeltaCounts } from "@/lib/payroll/syncMerge";
```

- [ ] **Step 4: Rewrite `syncHCP` to call the RPC**

Replace the entire `syncHCP` function (currently Run.tsx:156-219) with:

```ts
const syncHCP = async () => {
  if (!runId) return;
  setSyncing(true); setSyncError(null);
  try {
    const { data, error } = await supabase.functions.invoke("sync-hcp-week", {
      body: { week_start: format(weekStart, "yyyy-MM-dd"), week_end: format(weekEnd, "yyyy-MM-dd") },
    });
    if (error) throw new Error(error.message);
    const fnError = (data as { error?: string })?.error;
    if (fnError) throw new Error(fnError);
    const allNormalized = (data as { jobs: any[] }).jobs ?? [];
    // Filter out $0 jobs client-side (safety net in case the edge function
    // hasn't been redeployed with the amount > 0 filter). See spec
    // 2026-04-18-payroll-merge-and-vercel-migration for the HCP schema quirks
    // and Run.tsx history: zero-dollar completed jobs are estimates / declined
    // repairs / freebies and should never hit payroll review.
    const normalized = allNormalized.filter((j) => Number(j.amount ?? 0) > 0);

    const rpcJobs = normalized.map((j) => {
      const ownerTech = resolveOwner(j.raw_techs ?? [], techsForCalc);
      const stripped = stripGratuityFromLineItems(j.line_items_text ?? "");
      const edgeTip = Number(j.tip ?? 0);
      const tip = edgeTip > 0 ? edgeTip : stripped.tip;
      return buildRpcPayload({
        normalized: j,
        fallbackJobDate: format(weekStart, "yyyy-MM-dd"),
        ownerTech,
        strippedLineItems: stripped.text,
        tip,
      });
    });

    const syncTs = new Date().toISOString();
    const { data: delta, error: rpcErr } = await supabase.rpc("merge_payroll_jobs", {
      p_run_id: runId,
      p_sync_ts: syncTs,
      p_jobs: rpcJobs,
    });
    if (rpcErr) throw new Error(rpcErr.message);
    setRunLastSyncAt(syncTs);

    const { data: jobsData } = await supabase
      .from("payroll_jobs").select("*").eq("run_id", runId).order("job_date");
    setJobs((jobsData as DBJob[]) ?? []);

    const ids = (jobsData ?? []).map((j: DBJob) => j.id);
    if (ids.length) {
      const { data: jp } = await supabase.from("payroll_job_parts").select("*").in("job_id", ids);
      setJobParts((jp as DBJobPart[]) ?? []);
    } else setJobParts([]);

    const d = (delta ?? {}) as { new?: number; changed?: number; removed?: number; unchanged?: number };
    const parts = [
      d.new ? `${d.new} new` : "",
      d.changed ? `${d.changed} amount-changed` : "",
      d.removed ? `${d.removed} removed from HCP` : "",
    ].filter(Boolean);
    toast({
      title: parts.length ? `Synced: ${parts.join(" · ")}` : `Synced (no changes)`,
      description: `${(jobsData ?? []).length} jobs in this run`,
    });
  } catch (e) {
    setSyncError((e as Error).message);
  } finally {
    setSyncing(false);
  }
};
```

Key changes from the old code:
- Drops the `delete().eq("run_id", runId)` + `insert(rows)` pattern entirely.
- Uses `supabase.rpc("merge_payroll_jobs", ...)` with the `buildRpcPayload`-shaped array.
- Records the sync timestamp into `runLastSyncAt` state (used for badges in later tasks).
- Toast now reports the delta summary instead of a raw count.

- [ ] **Step 5: Remove now-unused imports**

The helpers `resolveOwner` and `stripGratuityFromLineItems` are still used inside `syncHCP`, so leave them. The helper `stripGratuityFromLineItems` function defined at Run.tsx:61-92 is no longer used anywhere else — but it's already imported from a utility (or inlined — check current file). If the current file defines it inline (lines 61-92 per the current snapshot), **leave it in place for now**; it's invoked by the new `syncHCP` body via the inline `stripGratuityFromLineItems(...)` call. Task 5 doesn't need to relocate it.

(If `stripGratuityFromLineItems` is actually imported from `@/lib/payroll/tipExtraction`, nothing changes. The `grep`-based verification is: `grep -n stripGratuityFromLineItems twins-dash/src/pages/payroll/Run.tsx` — if it shows `import {...}` at the top, it's imported; if it shows `function stripGratuityFromLineItems(...)` mid-file, it's local.)

- [ ] **Step 6: Typecheck**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx tsc --noEmit
```

Expected: clean exit (0 errors). If errors reference `DBJob` missing a property, double-check Step 1. If errors reference `supabase.rpc` overload, that's expected because the Payroll tables were cast through `any` (`const supabase = supabaseTyped as any;` at Run.tsx:26) — `rpc` on an `any` resolves to `any`, so no error should appear.

- [ ] **Step 7: Run the dev server and verify the sync flow**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npm run dev
```

Open http://localhost:8080/payroll/run (or whatever port vite picks). Pick the current week → click "Sync from HCP" → verify the toast shows the delta summary instead of `"Synced N jobs from HCP"`. Verify the jobs table renders as before.

Stop the dev server (Ctrl-C) when done.

- [ ] **Step 8: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/src/pages/payroll/Run.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): syncHCP calls merge_payroll_jobs RPC instead of wiping

Replaces the delete+insert with an atomic server-side merge that
preserves operator edits on already-reviewed jobs. Extends DBJob
with the new columns. Toasts a delta summary after each sync.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Step 2 badge column + re-sync button label

**Files:**
- Create: `twins-dash/src/components/payroll/SyncDeltaBadges.tsx`
- Modify: `twins-dash/src/pages/payroll/Run.tsx` — `Step2Sync` component (currently lines 430-489)

**Context:** The jobs table in Step 2 shows Date / Invoice / Customer / Amount / Listed Techs. We add a leading "Status" column rendering small badges per job (NEW, Δ, Removed). The sync button text changes based on whether the run has ever been synced.

- [ ] **Step 1: Create `SyncDeltaBadges.tsx`**

Write `twins-dash/src/components/payroll/SyncDeltaBadges.tsx`:

```tsx
import { Badge } from "@/components/ui/badge";
import { computeJobBadges, type DBJobForMerge } from "@/lib/payroll/syncMerge";

export function SyncDeltaBadges({
  job,
  runLastSyncAt,
}: {
  job: DBJobForMerge;
  runLastSyncAt: string | null;
}) {
  const b = computeJobBadges(job, runLastSyncAt);
  if (!b.isNew && !b.hasAmountChange && !b.isRemoved) return null;
  return (
    <div className="flex flex-wrap gap-1">
      {b.isNew && (
        <Badge className="bg-success text-success-foreground hover:bg-success text-[10px] px-1.5 py-0">
          NEW
        </Badge>
      )}
      {b.hasAmountChange && (
        <Badge
          className="bg-warning text-warning-foreground hover:bg-warning text-[10px] px-1.5 py-0"
          title="Amount changed in HCP since last review"
        >
          Δ
        </Badge>
      )}
      {b.isRemoved && (
        <Badge variant="secondary" className="text-[10px] px-1.5 py-0">
          Removed
        </Badge>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Wire the delta strip and badges into `Step2Sync`**

In `twins-dash/src/pages/payroll/Run.tsx`, change the `Step2Sync` signature and body.

Find the signature (currently line 430):

```tsx
function Step2Sync({
  syncing, syncError, jobs, onSync, onBack, onContinue,
}: { syncing: boolean; syncError: string | null; jobs: DBJob[]; onSync: () => void; onBack: () => void; onContinue: () => void }) {
```

Replace with:

```tsx
function Step2Sync({
  syncing, syncError, jobs, runLastSyncAt, onSync, onBack, onContinue,
}: {
  syncing: boolean; syncError: string | null; jobs: DBJob[];
  runLastSyncAt: string | null;
  onSync: () => void; onBack: () => void; onContinue: () => void;
}) {
  const delta = computeDeltaCounts(jobs, runLastSyncAt);
  const deltaBits = [
    delta.newCount ? `${delta.newCount} new` : "",
    delta.changedCount ? `${delta.changedCount} amount-changed` : "",
    delta.removedCount ? `${delta.removedCount} removed from HCP` : "",
  ].filter(Boolean);
```

Add this import at the top of the file (near the existing `syncMerge` import):

```ts
import { SyncDeltaBadges } from "@/components/payroll/SyncDeltaBadges";
```

Then in the `Step2Sync` JSX, change the sync button:

```tsx
<Button onClick={onSync} disabled={syncing}>
  {syncing ? <><Loader2 className="h-4 w-4 mr-2 animate-spin" /> Syncing…</> : (
    <><RefreshCcw className="h-4 w-4 mr-2" /> {runLastSyncAt ? "Re-sync from HCP" : "Sync from HCP"}</>
  )}
</Button>
```

Above the jobs table in `Step2Sync` (right before `<div className="border rounded-md max-h-80 overflow-auto">`), add:

```tsx
{deltaBits.length > 0 && (
  <div className="text-xs text-muted-foreground">
    {deltaBits.join(" · ")}
  </div>
)}
```

Change the TableHeader row to prepend a Status column:

```tsx
<TableHeader>
  <TableRow>
    <TableHead>Status</TableHead>
    <TableHead>Date</TableHead>
    <TableHead>Invoice #</TableHead>
    <TableHead>Customer</TableHead>
    <TableHead>Amount</TableHead>
    <TableHead>Listed Techs</TableHead>
  </TableRow>
</TableHeader>
```

Change each TableRow body:

```tsx
<TableRow key={j.id}>
  <TableCell><SyncDeltaBadges job={j} runLastSyncAt={runLastSyncAt} /></TableCell>
  <TableCell>{j.job_date}</TableCell>
  <TableCell>{j.hcp_job_number}</TableCell>
  <TableCell>{j.customer_display}</TableCell>
  <TableCell>{fmtUSD(Number(j.amount))}</TableCell>
  <TableCell className="text-xs">{j.raw_techs}</TableCell>
</TableRow>
```

- [ ] **Step 3: Pass `runLastSyncAt` from `Run` to `Step2Sync`**

In `Run.tsx`, find the `{step === 2 && (` block (currently line 318) and update the prop list:

```tsx
{step === 2 && (
  <Step2Sync
    syncing={syncing} syncError={syncError} jobs={jobs}
    runLastSyncAt={runLastSyncAt}
    onSync={syncHCP} onBack={() => setStep(1)} onContinue={goToReview}
  />
)}
```

- [ ] **Step 4: Import `computeDeltaCounts` at the top**

It was imported in Task 4 already. Verify the import at the top of `Run.tsx`:

```ts
import { buildRpcPayload, computeDeltaCounts } from "@/lib/payroll/syncMerge";
```

If missing, add it.

- [ ] **Step 5: Typecheck + preview**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx tsc --noEmit
npm run dev
```

Open `/payroll/run`, click through to Step 2, sync, verify:
- Button says "Sync from HCP" before sync, "Re-sync from HCP" after.
- Jobs table has a leading Status column with green **NEW** badges on all jobs (first sync).
- Re-sync without any HCP change → no badges.

Stop dev server.

- [ ] **Step 6: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/src/components/payroll/SyncDeltaBadges.tsx twins-dash/src/pages/payroll/Run.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): Step 2 sync-delta badges + re-sync button label

Adds a Status column to the Step 2 jobs table showing NEW / Δ /
Removed badges based on first_synced_at, amount_changed_from, and
removed_from_hcp. Sync button relabels to "Re-sync from HCP" after
the first sync.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Resume flow — `ResumeDraftCard` + query-param hydration

**Files:**
- Create: `twins-dash/src/components/payroll/ResumeDraftCard.tsx`
- Modify: `twins-dash/src/pages/payroll/Run.tsx` — initial-load effect, add draft-detection branch, hydrate from `?run_id=`

**Context:** On mount, `Run.tsx` must check for an existing `in_progress` run. If `?run_id=` is present in the URL, skip the prompt and hydrate directly. If there's exactly one in-progress run, show the Resume card. Start-fresh supersedes the old run and continues to Step 1.

- [ ] **Step 1: Create `ResumeDraftCard.tsx`**

Write `twins-dash/src/components/payroll/ResumeDraftCard.tsx`:

```tsx
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { format, formatDistanceToNow } from "date-fns";

export function ResumeDraftCard({
  weekStart,
  weekEnd,
  lastSyncAt,
  jobCount,
  onResume,
  onStartFresh,
}: {
  weekStart: string; weekEnd: string;
  lastSyncAt: string | null;
  jobCount: number;
  onResume: () => void;
  onStartFresh: () => void;
}) {
  const rel = lastSyncAt
    ? `last synced ${formatDistanceToNow(new Date(lastSyncAt), { addSuffix: true })}`
    : "never synced";
  const range = `${format(new Date(weekStart), "MMM d")} – ${format(new Date(weekEnd), "MMM d, yyyy")}`;
  return (
    <Card className="border-accent/50 bg-accent/5">
      <CardContent className="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <div className="font-medium">Draft in progress for week of {range}</div>
          <div className="text-sm text-muted-foreground">
            {rel} · {jobCount} job{jobCount === 1 ? "" : "s"} in draft
          </div>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={onStartFresh}>Start fresh</Button>
          <Button onClick={onResume}>Resume</Button>
        </div>
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 2: Add state and load hook in `Run.tsx`**

At the top of `Run.tsx`, add to the existing imports from `react-router-dom`:

```ts
import { useNavigate, useSearchParams } from "react-router-dom";
```

Add these imports next to the other component imports:

```ts
import { ResumeDraftCard } from "@/components/payroll/ResumeDraftCard";
```

Inside the `Run` component (around the existing `const nav = useNavigate();` line), add:

```ts
const [params] = useSearchParams();
const queryRunId = params.get("run_id");
const [loading, setLoading] = useState(true);
const [draftCandidate, setDraftCandidate] = useState<null | {
  id: number; week_start: string; week_end: string; last_sync_at: string | null;
  current_step: number; review_idx: number; jobCount: number;
}>(null);
```

- [ ] **Step 3: Add a helper `hydrateRun(runId: number)` inside the component**

Place it above `startRun`:

```ts
const hydrateRun = async (id: number) => {
  const { data: run, error } = await supabase
    .from("payroll_runs")
    .select("id, week_start, week_end, status, current_step, review_idx, last_sync_at")
    .eq("id", id)
    .maybeSingle();
  if (error || !run || run.status !== "in_progress") {
    toast({ title: "Draft not found", variant: "destructive" });
    setLoading(false);
    return;
  }
  setRunId(run.id);
  setWeekStart(new Date(`${run.week_start}T00:00:00`));
  setRunLastSyncAt(run.last_sync_at ?? null);
  setStep(Math.min(4, Math.max(1, Number(run.current_step))) as Step);
  setReviewIdx(Math.max(0, Number(run.review_idx)));
  const { data: jobsData } = await supabase
    .from("payroll_jobs").select("*").eq("run_id", run.id).order("job_date");
  setJobs((jobsData as DBJob[]) ?? []);
  const ids = (jobsData ?? []).map((j: DBJob) => j.id);
  if (ids.length) {
    const { data: jp } = await supabase.from("payroll_job_parts").select("*").in("job_id", ids);
    setJobParts((jp as DBJobPart[]) ?? []);
  } else setJobParts([]);
  // If resumed directly at step 4, mark the summary ready so the existing
  // Step4Summary useEffect loads the previously-computed commissions from
  // payroll_commissions. If the operator wants fresh numbers (e.g. new
  // jobs were added via re-sync after step 4 was first reached), they
  // click Back → Continue, which runs the usual goToSummary recompute.
  if (Number(run.current_step) === 4) setSummaryReady(true);
  setLoading(false);
};
```

- [ ] **Step 4: Rewrite the initial-load effect**

Currently Run.tsx has a single `useEffect` at line 114 that loads `parts/techs/tiers`. Extend it to also detect drafts and auto-hydrate from query param.

Replace the `useEffect(() => { (async () => { ... })(); }, []);` block (currently lines 114-125) with:

```ts
useEffect(() => {
  (async () => {
    const [{ data: p }, { data: t }, { data: bt }] = await Promise.all([
      supabase.from("payroll_parts_prices").select("*").order("name"),
      supabase.from("payroll_techs").select("*"),
      supabase.from("payroll_bonus_tiers").select("*"),
    ]);
    setParts((p as DBPart[]) ?? []);
    setTechs((t as DBTech[]) ?? []);
    setTiers((bt as DBBonusTier[]) ?? []);

    if (queryRunId) {
      await hydrateRun(Number(queryRunId));
      return;
    }

    const { data: drafts } = await supabase
      .from("payroll_runs")
      .select("id, week_start, week_end, last_sync_at, current_step, review_idx")
      .eq("status", "in_progress")
      .order("week_start", { ascending: false })
      .limit(1);

    const draft = (drafts as any[])?.[0];
    if (draft) {
      const { count } = await supabase
        .from("payroll_jobs").select("id", { count: "exact", head: true }).eq("run_id", draft.id);
      setDraftCandidate({ ...draft, jobCount: count ?? 0 });
    }
    setLoading(false);
  })();
  // eslint-disable-next-line react-hooks/exhaustive-deps
}, []);
```

- [ ] **Step 5: Render the Resume card when a draft candidate exists**

At the top of the `return (...)` block in `Run` (before `<StepIndicator />`), add:

```tsx
{loading && <div className="text-sm text-muted-foreground">Loading…</div>}

{!loading && draftCandidate && !runId && (
  <ResumeDraftCard
    weekStart={draftCandidate.week_start}
    weekEnd={draftCandidate.week_end}
    lastSyncAt={draftCandidate.last_sync_at}
    jobCount={draftCandidate.jobCount}
    onResume={() => hydrateRun(draftCandidate.id)}
    onStartFresh={async () => {
      await supabase.from("payroll_runs")
        .update({ status: "superseded" }).eq("id", draftCandidate.id);
      setDraftCandidate(null);
    }}
  />
)}
```

- [ ] **Step 6: Suppress Step 1 rendering while loading or draft-prompting**

Currently `{step === 1 && <Step1Pick ... />}`. Change it so Step 1 is only rendered after the load completes and we're not showing the resume card:

```tsx
{!loading && !draftCandidate && step === 1 && (
  <Step1Pick week={weekStart} setWeek={setWeekStart} weekEnd={weekEnd} onContinue={startRun} />
)}
```

Leave step 2/3/4 checks unchanged — they depend on `step > 1` which implies `runId !== null`.

- [ ] **Step 7: Typecheck + smoke test**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx tsc --noEmit
npm run dev
```

Manual test:
1. Start a fresh run at `/payroll/run`: pick week, Continue, Sync, review 2 jobs.
2. Navigate away to `/payroll` (via the nav).
3. Navigate back to `/payroll/run` — expect the **ResumeDraftCard** to appear with "Draft in progress for week of …" and a Resume button.
4. Click Resume → verify you land in Step 2 (since `current_step` was last set to 2; no current_step persist yet, so it'll be 1 — that's OK for this task, Task 7 adds persistence).
5. Refresh with `/payroll/run?run_id=<id>` in the URL — expect auto-hydrate, no card.

Stop dev server.

- [ ] **Step 8: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/src/components/payroll/ResumeDraftCard.tsx twins-dash/src/pages/payroll/Run.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): resume flow — detect draft on mount, hydrate from url

On /payroll/run mount, detect any in_progress run and show a
ResumeDraftCard with Resume and Start fresh buttons. Accepts
?run_id=<id> for direct hydration from the Drafts section (Task 8).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Persist `current_step` and `review_idx` (debounced)

**Files:**
- Modify: `twins-dash/src/pages/payroll/Run.tsx` — add two debounced effects

**Context:** When the operator leaves and returns, they land at the step + job index they were on. These writes are not critical (worst case they lose their exact position), so debounce 500ms and swallow errors.

- [ ] **Step 1: Add a tiny debounced-effect helper in Run.tsx**

Inside `Run.tsx`, above the `Run` component, add:

```ts
function useDebouncedEffect(fn: () => void, deps: unknown[], delayMs: number) {
  useEffect(() => {
    const t = setTimeout(fn, delayMs);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);
}
```

- [ ] **Step 2: Add the two persist effects inside `Run`**

Add near the other `useEffect` blocks (after the initial-load effect from Task 6):

```ts
useDebouncedEffect(() => {
  if (!runId) return;
  supabase.from("payroll_runs").update({ current_step: step }).eq("id", runId)
    .then(({ error }: { error: unknown }) => {
      if (error) console.warn("persist current_step failed", error);
    });
}, [runId, step], 500);

useDebouncedEffect(() => {
  if (!runId || step !== 3) return;
  supabase.from("payroll_runs").update({ review_idx: reviewIdx }).eq("id", runId)
    .then(({ error }: { error: unknown }) => {
      if (error) console.warn("persist review_idx failed", error);
    });
}, [runId, step, reviewIdx], 500);
```

- [ ] **Step 3: Test resume at exact spot**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npm run dev
```

1. `/payroll/run` → fresh run → pick week → Continue (Step 2) → Sync → Continue (Step 3). Arrow-right a few times to job 4.
2. Wait 1 second. Reload the browser.
3. Expect the Resume card → Resume → lands on Step 3, job 4.

Stop dev server.

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/src/pages/payroll/Run.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): persist current_step and review_idx (debounced)

Writes are non-critical (operator loses position at worst); 500ms
debounce avoids hammering Supabase on every arrow-key press.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: Drafts section on the Payroll home page

**Files:**
- Create: `twins-dash/src/components/payroll/DraftsSection.tsx`
- Modify: `twins-dash/src/pages/payroll/Home.tsx` — render the DraftsSection above the "Last completed run" block

**Context:** Show any `in_progress` runs as "Drafts" above the existing history card. Each draft has a Resume button that navigates to `/payroll/run?run_id=<id>`.

- [ ] **Step 1: Create `DraftsSection.tsx`**

Write `twins-dash/src/components/payroll/DraftsSection.tsx`:

```tsx
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { format, formatDistanceToNow } from "date-fns";
import { supabase as supabaseTyped } from "@/integrations/supabase/client";

const supabase = supabaseTyped as any;

type DraftRow = {
  id: number; week_start: string; week_end: string;
  last_sync_at: string | null; current_step: number;
  job_count: number;
};

export function DraftsSection() {
  const { data: drafts, isLoading } = useQuery({
    queryKey: ["payroll", "drafts"],
    queryFn: async (): Promise<DraftRow[]> => {
      const { data: runs, error } = await supabase
        .from("payroll_runs")
        .select("id, week_start, week_end, last_sync_at, current_step")
        .eq("status", "in_progress")
        .order("week_start", { ascending: false });
      if (error) throw error;
      if (!runs || runs.length === 0) return [];

      // Fetch job counts per draft in one query
      const ids = runs.map((r: any) => r.id);
      const { data: jobs } = await supabase
        .from("payroll_jobs")
        .select("run_id")
        .in("run_id", ids);
      const counts = new Map<number, number>();
      for (const j of (jobs ?? []) as { run_id: number }[]) {
        counts.set(j.run_id, (counts.get(j.run_id) ?? 0) + 1);
      }
      return runs.map((r: any) => ({ ...r, job_count: counts.get(r.id) ?? 0 }));
    },
    staleTime: 30_000,
  });

  if (isLoading || !drafts || drafts.length === 0) return null;

  return (
    <Card className="border-accent/50">
      <CardHeader>
        <CardTitle className="text-base">Drafts in progress</CardTitle>
      </CardHeader>
      <CardContent className="space-y-2">
        {drafts.map((d) => {
          const range = `${format(new Date(d.week_start), "MMM d")} – ${format(new Date(d.week_end), "MMM d, yyyy")}`;
          const rel = d.last_sync_at
            ? `last synced ${formatDistanceToNow(new Date(d.last_sync_at), { addSuffix: true })}`
            : "never synced";
          return (
            <div key={d.id} className="flex items-center justify-between gap-3 rounded-md border p-3">
              <div>
                <div className="font-medium">{range}</div>
                <div className="text-xs text-muted-foreground">
                  {rel} · {d.job_count} job{d.job_count === 1 ? "" : "s"} · on step {d.current_step}
                </div>
              </div>
              <Button asChild size="sm">
                <Link to={`/payroll/run?run_id=${d.id}`}>Resume</Link>
              </Button>
            </div>
          );
        })}
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 2: Render it on Home.tsx**

In `twins-dash/src/pages/payroll/Home.tsx`, add the import:

```ts
import { DraftsSection } from "@/components/payroll/DraftsSection";
```

In the JSX, insert `<DraftsSection />` between the "Run this week's payroll" hero card and the `{lastRun && ...}` block. It will self-hide when there are no drafts.

Specifically, find:

```tsx
      {lastRun && (
        <Card>
```

And insert immediately before it:

```tsx
      <DraftsSection />

```

- [ ] **Step 3: Smoke test**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npm run dev
```

1. Open `/payroll` — if there's a draft from Task 6/7 testing, expect a "Drafts in progress" card with a Resume button.
2. Click Resume → should go to `/payroll/run?run_id=<id>` and hydrate.
3. Finalize that run (or manually `UPDATE payroll_runs SET status='superseded' WHERE id=...`). Refresh `/payroll` — draft card disappears.

Stop dev server.

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/src/components/payroll/DraftsSection.tsx twins-dash/src/pages/payroll/Home.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): Drafts section on payroll home page

Lists any in_progress runs with week range, last-sync time, job
count, and a Resume button linking to /payroll/run?run_id=<id>.
Self-hides when there are no drafts.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: Step 3 — amount-changed banner, `is_reviewed` flip, title counts

**Files:**
- Modify: `twins-dash/src/pages/payroll/Run.tsx` — `Step3Review` component (currently ~line 491) and `Run` parent (pass `runLastSyncAt`, handle `amount_changed_from` clearing)

**Context:** Three additions to Step 3:
1. Title shows `Job N of M (X new, Y changed)` when counts > 0.
2. When the current job has `amount_changed_from !== null`, render a yellow banner above the Field grid.
3. When the current job's `id` changes, flip `is_reviewed = true` for that job and clear `amount_changed_from` for the *previous* job (one-shot heads-up).

- [ ] **Step 1: Add the Step 3 effect — is_reviewed flip + amount_changed_from clear**

At the top of `Run.tsx`, add `useRef` to the React import:

```ts
import { useEffect, useMemo, useRef, useState } from "react";
```

Inside `Run` component, find the `useEffect` that binds arrow keys (currently around line 225). Add a new effect after it:

```ts
const prevJobIdRef = useRef<number | null>(null);
useEffect(() => {
  const cur = step === 3 ? jobs[reviewIdx] : null;
  const prevId = prevJobIdRef.current;
  const curId = cur?.id ?? null;

  // Previous job changed (arrow navigation) OR we left Step 3 entirely.
  // Clear amount_changed_from on the previous job as a one-shot heads-up.
  if (prevId && prevId !== curId) {
    const prev = jobs.find((j) => j.id === prevId);
    if (prev && prev.amount_changed_from !== null && prev.amount_changed_from !== undefined) {
      supabase.from("payroll_jobs").update({ amount_changed_from: null }).eq("id", prev.id)
        .then(({ error }: { error: unknown }) => {
          if (error) console.warn("clear amount_changed_from failed", error);
        });
      setJobs((js) => js.map((j) => j.id === prev.id ? { ...j, amount_changed_from: null } : j));
    }
  }

  // Flip is_reviewed=true on the newly-opened job if not already set
  if (cur && !cur.is_reviewed) {
    supabase.from("payroll_jobs").update({ is_reviewed: true }).eq("id", cur.id)
      .then(({ error }: { error: unknown }) => {
        if (error) console.warn("mark reviewed failed", error);
      });
    setJobs((js) => js.map((j) => j.id === cur.id ? { ...j, is_reviewed: true } : j));
  }

  prevJobIdRef.current = curId;
}, [step, reviewIdx, jobs]);
```

This single effect covers both navigation-between-jobs and leaving Step 3 (via Back or Continue), because in both cases `curId` becomes either a new job id or `null`.

- [ ] **Step 2: Add the banner + title counts in `Step3Review`**

In the `Step3Review` component signature (currently line 491), add `runLastSyncAt` and `allJobs` props (allJobs is needed for delta counts — the component only has the current job):

Find:

```tsx
function Step3Review({
  job, idx, total, parts, jobParts, techs,
  onPrev, onNext, onUpdate, onAddPart, onAddOneTimePart, onRemovePart, onBack, onContinue, onAddNewPart,
}: {
  job: DBJob; idx: number; total: number;
  parts: DBPart[]; jobParts: DBJobPart[]; techs: Tech[];
  onPrev: () => void; onNext: () => void;
  onUpdate: (p: Partial<DBJob>) => void;
  onAddPart: (p: DBPart, qty: number) => void;
  onAddOneTimePart: (name: string, cost: number, qty: number) => void;
  onRemovePart: (id: number) => void;
  onBack: () => void; onContinue: () => void;
  onAddNewPart: (name: string, cost: number) => Promise<DBPart | null>;
}) {
```

Add `allJobs` and `runLastSyncAt`:

```tsx
function Step3Review({
  job, idx, total, parts, jobParts, techs, allJobs, runLastSyncAt,
  onPrev, onNext, onUpdate, onAddPart, onAddOneTimePart, onRemovePart, onBack, onContinue, onAddNewPart,
}: {
  job: DBJob; idx: number; total: number;
  parts: DBPart[]; jobParts: DBJobPart[]; techs: Tech[];
  allJobs: DBJob[]; runLastSyncAt: string | null;
  onPrev: () => void; onNext: () => void;
  onUpdate: (p: Partial<DBJob>) => void;
  onAddPart: (p: DBPart, qty: number) => void;
  onAddOneTimePart: (name: string, cost: number, qty: number) => void;
  onRemovePart: (id: number) => void;
  onBack: () => void; onContinue: () => void;
  onAddNewPart: (name: string, cost: number) => Promise<DBPart | null>;
}) {
  const delta = computeDeltaCounts(allJobs, runLastSyncAt);
  const hasDelta = delta.newCount + delta.changedCount > 0;
  const titleExtra = hasDelta
    ? ` (${delta.newCount ? `${delta.newCount} new` : ""}${delta.newCount && delta.changedCount ? ", " : ""}${delta.changedCount ? `${delta.changedCount} changed` : ""})`
    : "";
  const changedFrom = job.amount_changed_from !== null && job.amount_changed_from !== undefined
    ? Number(job.amount_changed_from)
    : null;
```

Find the CardTitle (currently around line 528):

```tsx
<CardTitle>Job {reviewedCount} of {total}</CardTitle>
```

Replace with:

```tsx
<CardTitle>Job {reviewedCount} of {total}{titleExtra}</CardTitle>
```

Inside the CardContent, immediately after the grid of Field rows (currently around line 552, right before the `{/* Tech notes from HCP */}` block), insert:

```tsx
{changedFrom !== null && (
  <div className="rounded-md border border-warning bg-warning/10 p-3 text-sm">
    <div className="font-medium">Amount changed in HCP</div>
    <div className="text-muted-foreground">
      ${changedFrom.toFixed(2)} → ${Number(job.amount).toFixed(2)}. Commissions will recalculate on Finalize.
    </div>
  </div>
)}
```

- [ ] **Step 3: Pass the new props from `Run` to `Step3Review`**

Find the `{step === 3 && currentJob && (` block in `Run`'s return (currently line 325). Add `allJobs` and `runLastSyncAt` props:

```tsx
{step === 3 && currentJob && (
  <Step3Review
    job={currentJob} idx={reviewIdx} total={jobs.length} parts={parts}
    jobParts={partsForCurrent} techs={techsForCalc}
    allJobs={jobs} runLastSyncAt={runLastSyncAt}
    onPrev={() => setReviewIdx((i) => Math.max(0, i - 1))}
    onNext={() => setReviewIdx((i) => Math.min(jobs.length - 1, i + 1))}
    onUpdate={(p) => updateJob(currentJob.id, p)}
    onAddPart={(part, qty) => addPartToJob(currentJob.id, part, qty)}
    onAddOneTimePart={(name, cost, qty) => addOneTimePart(currentJob.id, name, cost, qty)}
    onRemovePart={removeJobPart}
    onBack={() => setStep(2)}
    onContinue={goToSummary}
    onAddNewPart={async (name, cost) => {
      const { data, error } = await supabase.from("payroll_parts_prices").insert({ name, total_cost: cost }).select().single();
      if (error) { toast({ title: "Add failed", description: error.message, variant: "destructive" }); return null; }
      setParts((ps) => [...ps, data as DBPart]);
      return data as DBPart;
    }}
  />
)}
```

- [ ] **Step 4: Smoke test**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx tsc --noEmit
npm run dev
```

Manual:
1. Resume a draft that has jobs.
2. Land on Step 3 — verify title reads "Job 1 of N" with no extra (no sync has happened this session).
3. Arrow-right to a job. Verify `is_reviewed=true` in DB for that job (via MCP `execute_sql`: `SELECT id, is_reviewed FROM payroll_jobs WHERE run_id=<id> ORDER BY id;`).
4. To test the banner: manually set `amount_changed_from=100` on one of the jobs via MCP `execute_sql`. Reload, Resume, navigate to that job — expect a yellow banner "Amount changed in HCP: $100.00 → $<current>. Commissions will recalculate on Finalize."
5. Arrow to the next job, then back — banner is gone (cleared on navigation away).

Stop dev server.

- [ ] **Step 5: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/src/pages/payroll/Run.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): Step 3 amount-change banner + is_reviewed flip

Opening a job in Step 3 marks it is_reviewed=true so future re-syncs
preserve the operator's edits. If the amount changed in HCP since
the last review, a yellow banner surfaces the old → new amount;
navigating away clears the flag (one-shot heads-up).

Card title now shows "(X new, Y changed)" when the latest sync
added or changed jobs.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 10: Step 4 — warning strip for removed jobs

**Files:**
- Modify: `twins-dash/src/pages/payroll/Run.tsx` — `Step4Summary` component (currently line 744) and its prop pass

**Context:** If any job in the current run has `removed_from_hcp=true`, show a warning above the summary table with a button to jump back to Step 3 on that job.

- [ ] **Step 1: Pass `jobs` and a jump-callback to Step4Summary**

Step4Summary already receives `jobs`. Add a callback to jump to a specific reviewIdx:

In `Run` return, find:

```tsx
{step === 4 && (
  <Step4Summary
    runId={runId!} weekStart={weekStart} computing={computing} ready={summaryReady}
    jobs={jobs} jobParts={jobParts} techs={techs}
    onBack={() => setStep(3)}
```

Change to:

```tsx
{step === 4 && (
  <Step4Summary
    runId={runId!} weekStart={weekStart} computing={computing} ready={summaryReady}
    jobs={jobs} jobParts={jobParts} techs={techs}
    onJumpToRemoved={(jobId) => {
      const i = jobs.findIndex((j) => j.id === jobId);
      if (i >= 0) { setReviewIdx(i); setStep(3); }
    }}
    onBack={() => setStep(3)}
```

- [ ] **Step 2: Render the warning strip inside Step4Summary**

Find the `Step4Summary` signature (currently line 744) and add `onJumpToRemoved`:

```tsx
function Step4Summary({
  runId, weekStart, computing, ready, jobs, jobParts, techs, onJumpToRemoved,
  onBack, onFinalize,
}: {
  runId: number; weekStart: Date;
  computing: boolean; ready: boolean;
  jobs: DBJob[]; jobParts: DBJobPart[]; techs: DBTech[];
  onJumpToRemoved: (jobId: number) => void;
  onBack: () => void; onFinalize: () => void;
}) {
  const removed = jobs.filter((j) => j.removed_from_hcp);
  // ... rest unchanged
```

Inside the CardContent, immediately after the `{computing && …}` line, insert:

```tsx
{removed.length > 0 && (
  <div className="rounded-md border border-warning bg-warning/10 p-3 text-sm flex items-start gap-2">
    <AlertCircle className="h-4 w-4 mt-0.5 shrink-0 text-warning-foreground" />
    <div className="flex-1">
      <div className="font-medium">
        {removed.length} job{removed.length === 1 ? "" : "s"} removed from HCP after sync
      </div>
      <div className="opacity-80">
        These jobs were in an earlier sync but HCP no longer returns them.
        Their parts and edits are preserved.
      </div>
    </div>
    <Button size="sm" variant="outline" onClick={() => onJumpToRemoved(removed[0].id)}>
      Review
    </Button>
  </div>
)}
```

Make sure `AlertCircle` is imported at the top of the file; it should already be (used elsewhere in this file).

- [ ] **Step 3: Smoke test**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx tsc --noEmit
npm run dev
```

Manual:
1. Manually mark a job `removed_from_hcp=true` via MCP `execute_sql`.
2. Resume that draft. Advance to Step 4. Expect the warning strip to appear.
3. Click Review → expect to land on Step 3 with that job as the current one.

Stop dev server.

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/src/pages/payroll/Run.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): Step 4 warning for jobs removed from HCP

When a draft contains jobs that HCP no longer returns (flagged
removed_from_hcp=true), surface a warning above the summary with
a Review button that jumps back to Step 3 on the first removed job.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 11: "Save & exit" button on all steps

**Files:**
- Modify: `twins-dash/src/pages/payroll/Run.tsx` — Step1Pick, Step2Sync, Step3Review, Step4Summary footer rows

**Context:** Cosmetic closure — every step's footer gets a "Save & exit" button that just navigates to `/payroll`. Data is already saved per-edit; this button signals "I'm stepping away." Not shown on Step 1 *before* the run is created (there's nothing to save).

- [ ] **Step 1: Add the button to Step2Sync**

Find Step2Sync's footer (currently around line 482-486):

```tsx
<div className="flex justify-between pt-2">
  <Button variant="outline" onClick={onBack}>Back</Button>
  <Button onClick={onContinue}>Continue</Button>
</div>
```

Change to:

```tsx
<div className="flex items-center justify-between pt-2">
  <Button variant="outline" onClick={onBack}>Back</Button>
  <div className="flex gap-2">
    <Button variant="outline" onClick={onSaveAndExit}>Save &amp; exit</Button>
    <Button onClick={onContinue}>Continue</Button>
  </div>
</div>
```

Add `onSaveAndExit` to the Step2Sync props signature:

```tsx
function Step2Sync({
  syncing, syncError, jobs, runLastSyncAt, onSync, onBack, onContinue, onSaveAndExit,
}: {
  syncing: boolean; syncError: string | null; jobs: DBJob[];
  runLastSyncAt: string | null;
  onSync: () => void; onBack: () => void; onContinue: () => void; onSaveAndExit: () => void;
}) {
```

- [ ] **Step 2: Add the button to Step3Review**

Find Step3Review's footer (currently around line 653-656):

```tsx
<div className="flex justify-between pt-3 border-t">
  <Button variant="outline" onClick={onBack}>Back</Button>
  <Button onClick={onContinue}>Continue to summary</Button>
</div>
```

Change to:

```tsx
<div className="flex items-center justify-between pt-3 border-t">
  <Button variant="outline" onClick={onBack}>Back</Button>
  <div className="flex gap-2">
    <Button variant="outline" onClick={onSaveAndExit}>Save &amp; exit</Button>
    <Button onClick={onContinue}>Continue to summary</Button>
  </div>
</div>
```

Add `onSaveAndExit` to the Step3Review props signature (alongside other handlers):

```tsx
  onBack: () => void; onContinue: () => void; onSaveAndExit: () => void;
```

- [ ] **Step 3: Add the button to Step4Summary**

Find Step4Summary's footer (currently around line 825-833):

```tsx
<div className="flex justify-between pt-2">
  <Button variant="outline" onClick={onBack}>Back</Button>
  <div className="flex gap-2">
    <Button variant="outline" onClick={() => downloadPayrollWorkbook(weekStart, summary, jobsRows, partsRows)}>
      <Download className="h-4 w-4 mr-2" /> Download Excel
    </Button>
    <Button onClick={onFinalize}>Finalize</Button>
  </div>
</div>
```

Change to:

```tsx
<div className="flex items-center justify-between pt-2">
  <Button variant="outline" onClick={onBack}>Back</Button>
  <div className="flex gap-2">
    <Button variant="outline" onClick={onSaveAndExit}>Save &amp; exit</Button>
    <Button variant="outline" onClick={() => downloadPayrollWorkbook(weekStart, summary, jobsRows, partsRows)}>
      <Download className="h-4 w-4 mr-2" /> Download Excel
    </Button>
    <Button onClick={onFinalize}>Finalize</Button>
  </div>
</div>
```

Add `onSaveAndExit` to Step4Summary props:

```tsx
  onJumpToRemoved: (jobId: number) => void;
  onBack: () => void; onFinalize: () => void; onSaveAndExit: () => void;
```

- [ ] **Step 4: Pass `onSaveAndExit` from Run**

In `Run`'s return, define it once above the step blocks:

```ts
const saveAndExit = () => nav("/payroll");
```

Place this right next to the other handlers like `startRun`, `syncHCP`, etc. (near line 155).

Then pass `onSaveAndExit={saveAndExit}` to each of `<Step2Sync />`, `<Step3Review />`, `<Step4Summary />`.

Step1Pick does NOT receive it — no draft exists yet before `startRun`, so there's nothing to save.

- [ ] **Step 5: Smoke test**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx tsc --noEmit
npm run dev
```

Manual:
1. Open a draft, get to Step 3.
2. Click "Save & exit" → expect redirect to `/payroll`.
3. Verify the draft is listed in the Drafts section with the same step and review_idx.
4. Resume → land back where you were.

Stop dev server.

- [ ] **Step 6: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add twins-dash/src/pages/payroll/Run.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): Save & exit button on Steps 2, 3, 4

Cosmetic closure — navigates to /payroll. Data is already saved
per-edit. Step 1 does not get the button because no draft exists
until Continue is clicked.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 12: End-to-end manual test + final verification

**Files:** none (testing only)

**Context:** Walk the full happy path the operator will run. This catches any regression the unit tests can't — mostly integration issues between the RPC, the UI, and the Supabase client's handling of NUMERIC-as-string.

- [ ] **Step 1: Start dev server**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npm run dev
```

- [ ] **Step 2: Confirm a fresh run works end-to-end**

1. Navigate to `/payroll` — no Drafts section should appear (or if there are leftover drafts from earlier testing, supersede them via `UPDATE payroll_runs SET status='superseded' WHERE status='in_progress'` first).
2. Click "Run Payroll" → `/payroll/run`. Should land on Step 1 (no resume card).
3. Pick a week (defaults fine) → Continue.
4. Click "Sync from HCP". Verify toast says "Synced: N new" and jobs have green NEW badges.
5. Continue to Step 3. Verify title shows "(N new)" where N matches the sync.
6. Review 2 jobs (add parts, pick owner). Hit "Save & exit".
7. On `/payroll`, confirm Drafts section shows your run with step=3 and job count.
8. Click Resume → back on Step 3 at job 3 (next job, since you reviewed 1 and 2).
9. Hit Back → Step 2. Click "Re-sync from HCP" (button should now say "Re-sync" not "Sync"). Expect toast to show the same counts as last time OR "(no changes)" if HCP hasn't changed.
10. Verify the previously-reviewed jobs retain their parts + owner.

- [ ] **Step 3: Confirm amount-changed flow (using MCP to simulate an HCP change)**

For a specific reviewed job, simulate an HCP amount change by manipulating the DB directly. First pick one `hcp_id` from your draft, then via MCP `execute_sql`:

```sql
-- This simulates what happens when HCP returns a different amount on re-sync.
-- We manually set amount_changed_from to mock what the RPC would set.
UPDATE public.payroll_jobs
   SET amount = amount * 1.1,
       amount_changed_from = amount
 WHERE run_id = <DRAFT_RUN_ID> AND hcp_id = '<HCP_ID>';
```

Resume the draft, navigate Step 3 to that job. Verify the yellow "Amount changed in HCP" banner appears. Arrow-right, then back — banner should be gone (cleared by the navigation-away effect).

- [ ] **Step 4: Confirm removed-jobs flow**

Flag one job as removed via MCP:

```sql
UPDATE public.payroll_jobs
   SET removed_from_hcp = TRUE
 WHERE run_id = <DRAFT_RUN_ID> LIMIT 1;
```

Step 2 table: verify the "Removed" badge appears on that row.
Step 4: verify the warning strip appears with "1 job removed from HCP…" and the Review button jumps to Step 3 on that job.

- [ ] **Step 5: Finalize & cleanup**

Click Finalize on Step 4. Confirm:
- Draft disappears from the Drafts section on `/payroll`.
- History includes the run.
- You can still open the run's History detail.

Stop dev server. No commit (pure testing).

---

## Out-of-scope (not in this plan)

The following are documented in the spec under "Out-of-scope follow-ups" and are NOT built here:

- Per-sync event log (`payroll_sync_events`) for forensic history.
- Live co-editing indicators if two admins open the same draft.
- Automatic background re-sync on a schedule.
- Reminders/notifications about unfinalized drafts at week-end.
