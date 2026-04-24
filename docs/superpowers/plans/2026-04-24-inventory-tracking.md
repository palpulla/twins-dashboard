# Parts Inventory Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Track live `on_hand` stock per part in `payroll_parts_prices`, atomically deduct on payroll Finalize, resync via CSV upload, and surface low-stock alerts on Parts Library + Payroll Home.

**Architecture:** Four new columns on `payroll_parts_prices` (`on_hand`, `min_stock`, `last_count_at`, `track_inventory`). Two new Postgres RPCs — `finalize_payroll_run` (atomic status-flip + stock decrement) and `upload_inventory_counts` (bulk on_hand/min_stock reset). Pending stock is derived client-side from in-progress drafts, never stored. Client-side CSV parser (Vitest-tested) preceding the upload RPC call.

**Tech Stack:** Supabase (Postgres 15 + RLS), TypeScript, React + Vite, React Query, shadcn/ui + Tailwind, Vitest. Migrations applied via `npx supabase db push` against project ref `jwrpjuqaynownxaoeayi`. Work in worktree `/Users/daniel/twins-dashboard/twins-dash-payroll-work` on branch `feature/payroll-draft-sync`.

**Source spec:** [docs/superpowers/specs/2026-04-24-inventory-tracking-design.md](../specs/2026-04-24-inventory-tracking-design.md)

---

## File Structure

**Create:**
- `supabase/migrations/20260424120000_inventory_columns.sql` — column additions
- `supabase/migrations/20260424120100_finalize_payroll_run_rpc.sql` — atomic finalize + stock decrement
- `supabase/migrations/20260424120200_upload_inventory_counts_rpc.sql` — bulk CSV upload
- `src/lib/payroll/inventoryCsv.ts` — pure parser
- `src/lib/payroll/__tests__/inventoryCsv.test.ts` — 8 Vitest cases
- `src/components/payroll/InventoryUploadPreview.tsx` — CSV preview modal
- `src/components/payroll/LowStockCard.tsx` — Home page alert card
- `src/hooks/usePendingStock.ts` — React Query hook that computes pending per part

**Modify:**
- `src/pages/payroll/Parts.tsx` — add On hand / Min / Pending / Effective / Tracked columns + Upload counts button
- `src/pages/payroll/Run.tsx` — swap the client-side `.update({status:'final'})` for the `finalize_payroll_run` RPC
- `src/pages/payroll/Home.tsx` — render `<LowStockCard />` between hero and Drafts section

**Untouched:**
- Existing `payroll_jobs`, `payroll_job_parts`, `payroll_commissions`, `payroll_techs`, `payroll_bonus_tiers` tables.
- Step 2 / Step 3 of the Run wizard (stock only moves on Finalize per the spec).

---

## Task 1: DB migration — `payroll_parts_prices` inventory columns

**Files:**
- Create: `supabase/migrations/20260424120000_inventory_columns.sql`

**Context:** Adds the four inventory columns. `is_one_time` already exists on the table from the original schema; we do not touch it, but our RPC logic later skips parts with `is_one_time = true`.

- [ ] **Step 1: Create the migration file**

Write `supabase/migrations/20260424120000_inventory_columns.sql`:

```sql
-- Parts-inventory columns. on_hand is the live authoritative stock,
-- decremented atomically on payroll Finalize and reset via CSV upload.
-- Pending (consumed by in-progress drafts) is derived at read time and
-- not stored. min_stock=0 means "no low-stock alert".

ALTER TABLE public.payroll_parts_prices
  ADD COLUMN IF NOT EXISTS on_hand         NUMERIC(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS min_stock       NUMERIC(10,2) NOT NULL DEFAULT 0 CHECK (min_stock >= 0),
  ADD COLUMN IF NOT EXISTS last_count_at   TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS track_inventory BOOLEAN       NOT NULL DEFAULT TRUE;

-- Auto-opt-out services / one-time items from inventory tracking by
-- default; the operator can still flip track_inventory back on.
UPDATE public.payroll_parts_prices
  SET track_inventory = FALSE
  WHERE is_one_time = TRUE;

-- Speed up the "current low-stock" dashboard query.
CREATE INDEX IF NOT EXISTS idx_payroll_parts_prices_low_stock
  ON public.payroll_parts_prices(min_stock, on_hand)
  WHERE track_inventory = TRUE AND min_stock > 0;
```

- [ ] **Step 2: Apply to the live DB**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx supabase db push
```

Expected: the three prior payroll migrations show `Already pushed`; `20260424120000_inventory_columns.sql` shows `Applied`. If the CLI flags remote drift (new migrations from other sessions), repair with `supabase migration repair --status reverted <id>` per the drift list, then re-run `db push`.

- [ ] **Step 3: Verify the schema**

Via the Supabase MCP `mcp__a13384b5-3518-4c7c-9b61-a7f2786de7db__execute_sql` with project_id `jwrpjuqaynownxaoeayi`:

```sql
SELECT column_name, data_type, column_default, is_nullable
FROM information_schema.columns
WHERE table_schema='public' AND table_name='payroll_parts_prices'
  AND column_name IN ('on_hand','min_stock','last_count_at','track_inventory')
ORDER BY column_name;
```

Expected: four rows, all `NOT NULL` except `last_count_at`. Defaults: `on_hand=0`, `min_stock=0`, `track_inventory=true`.

```sql
SELECT indexdef FROM pg_indexes
WHERE schemaname='public' AND indexname='idx_payroll_parts_prices_low_stock';
```

Expected: one row whose `indexdef` includes `WHERE ((track_inventory = true) AND (min_stock > (0)::numeric))`.

If the MCP token lacks access (it does on the payroll project — Task 1 of the prior plan documented this), skip the MCP verify and trust the successful `db push` from Step 2.

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add supabase/migrations/20260424120000_inventory_columns.sql
git commit -m "$(cat <<'EOF'
feat(payroll): add inventory columns to payroll_parts_prices

- on_hand NUMERIC(10,2) default 0 — live stock count
- min_stock NUMERIC(10,2) default 0 — reorder threshold (0 = no alert)
- last_count_at TIMESTAMPTZ — when on_hand was last set
- track_inventory BOOLEAN default true — opt-out for services / one-time items
- Back-fills track_inventory=false for is_one_time rows
- Partial index on (min_stock, on_hand) for the low-stock query

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: `finalize_payroll_run` RPC

**Files:**
- Create: `supabase/migrations/20260424120100_finalize_payroll_run_rpc.sql`

**Context:** Atomically flips a draft run to `final` and decrements `on_hand` for every tracked part it consumed. Replaces the client-side `.update({status:'final'})` call currently in [Run.tsx:536](../../../twins-dash/src/pages/payroll/Run.tsx#L536). Task 7 wires the client call.

- [ ] **Step 1: Create the migration file**

Write `supabase/migrations/20260424120100_finalize_payroll_run_rpc.sql`:

```sql
-- Atomically finalize an in-progress payroll run and decrement part
-- stock for every tracked part it consumed. Returns a summary JSON
-- so the client can toast "{stock_updates} parts deducted from stock".

CREATE OR REPLACE FUNCTION public.finalize_payroll_run(p_run_id INT)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path = public
AS $$
DECLARE
  v_status TEXT;
  v_updated INT := 0;
  v_skipped INT := 0;
  v_row     RECORD;
BEGIN
  -- Lock the run row and verify it's a live draft
  SELECT status INTO v_status
    FROM public.payroll_runs
    WHERE id = p_run_id
    FOR UPDATE;
  IF v_status IS NULL THEN
    RAISE EXCEPTION 'Run % not found', p_run_id;
  END IF;
  IF v_status <> 'in_progress' THEN
    RAISE EXCEPTION 'Run % is % (must be in_progress)', p_run_id, v_status;
  END IF;

  UPDATE public.payroll_runs SET status = 'final' WHERE id = p_run_id;

  -- Decrement on_hand for each part the run consumed, matching by
  -- case-insensitive trimmed name against the parts library.
  FOR v_row IN
    SELECT LOWER(TRIM(jp.part_name)) AS key, SUM(jp.quantity) AS qty
      FROM public.payroll_job_parts jp
      JOIN public.payroll_jobs j ON jp.job_id = j.id
     WHERE j.run_id = p_run_id
     GROUP BY LOWER(TRIM(jp.part_name))
  LOOP
    WITH updated AS (
      UPDATE public.payroll_parts_prices
         SET on_hand = on_hand - v_row.qty
       WHERE LOWER(TRIM(name)) = v_row.key
         AND track_inventory = TRUE
         AND is_one_time = FALSE
       RETURNING id
    )
    SELECT COUNT(*) INTO v_updated FROM updated;
    IF v_updated > 0 THEN
      v_updated := v_updated;  -- accumulate in outer var below
    END IF;
  END LOOP;

  -- Re-count from scratch to return accurate totals (above loop's local
  -- v_updated gets overwritten each iteration; we need per-iteration sum)
  SELECT
    COUNT(*) FILTER (WHERE pp.id IS NOT NULL),
    COUNT(*) FILTER (WHERE pp.id IS NULL)
  INTO v_updated, v_skipped
  FROM (
    SELECT LOWER(TRIM(jp.part_name)) AS key
      FROM public.payroll_job_parts jp
      JOIN public.payroll_jobs j ON jp.job_id = j.id
     WHERE j.run_id = p_run_id
     GROUP BY LOWER(TRIM(jp.part_name))
  ) agg
  LEFT JOIN public.payroll_parts_prices pp
    ON LOWER(TRIM(pp.name)) = agg.key
   AND pp.track_inventory = TRUE
   AND pp.is_one_time = FALSE;

  RETURN jsonb_build_object(
    'status',          'final',
    'stock_updates',   v_updated,
    'skipped_unmatched', v_skipped
  );
END;
$$;

GRANT EXECUTE ON FUNCTION public.finalize_payroll_run(INT) TO authenticated;
```

- [ ] **Step 2: Apply via supabase db push**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx supabase db push
```

Expected: new migration applied.

- [ ] **Step 3: Smoke-test against disposable data**

Via `mcp__...__execute_sql` against project `jwrpjuqaynownxaoeayi` (if MCP access works; otherwise skip and rely on Task 7's UI integration test):

```sql
-- Setup: one tracked part at on_hand=100, one untracked part at on_hand=100
INSERT INTO public.payroll_parts_prices (name, total_cost, on_hand, track_inventory)
VALUES ('TEST Tracked', 10, 100, TRUE), ('TEST Untracked', 10, 100, FALSE)
ON CONFLICT (name) DO UPDATE SET on_hand = 100, track_inventory = EXCLUDED.track_inventory;

-- Disposable run + job + parts
WITH new_run AS (
  INSERT INTO public.payroll_runs (week_start, week_end, status)
  VALUES ('2099-02-01', '2099-02-07', 'in_progress')
  RETURNING id
), new_job AS (
  INSERT INTO public.payroll_jobs (run_id, hcp_id, hcp_job_number, job_date, amount)
  SELECT id, 'FIN-TEST-1', 'FT-1', '2099-02-02', 100 FROM new_run
  RETURNING id, run_id
)
INSERT INTO public.payroll_job_parts (job_id, part_name, quantity, unit_price, total)
SELECT id, 'TEST Tracked',   5, 10, 50 FROM new_job
UNION ALL
SELECT id, 'TEST Untracked', 3, 10, 30 FROM new_job;

-- Find the run id we just created
SELECT id FROM public.payroll_runs WHERE week_start = '2099-02-01';

-- Call the RPC with that id → expect stock_updates=1, skipped_unmatched=0
SELECT public.finalize_payroll_run(<RUN_ID>);

-- Verify:
--   TEST Tracked:   on_hand = 95 (100 - 5)
--   TEST Untracked: on_hand = 100 (unchanged — track_inventory=false)
SELECT name, on_hand FROM public.payroll_parts_prices
 WHERE name IN ('TEST Tracked', 'TEST Untracked');

-- Cleanup
DELETE FROM public.payroll_runs WHERE week_start = '2099-02-01';
DELETE FROM public.payroll_parts_prices WHERE name IN ('TEST Tracked', 'TEST Untracked');
```

Expected: `stock_updates=1`, `skipped_unmatched=0`, TEST Tracked on_hand=95, TEST Untracked on_hand=100.

If MCP is blocked, the RPC gets validated end-to-end during Task 7's manual test step.

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add supabase/migrations/20260424120100_finalize_payroll_run_rpc.sql
git commit -m "$(cat <<'EOF'
feat(payroll): finalize_payroll_run RPC — atomic finalize + stock decrement

- Flips status in_progress → final inside a single transaction
- Decrements on_hand for every tracked, non-one-time part the run
  consumed; matches case-insensitive + trimmed on part_name
- Returns { status, stock_updates, skipped_unmatched } for the toast
- SECURITY INVOKER + SET search_path so existing payroll RLS applies

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: `upload_inventory_counts` RPC

**Files:**
- Create: `supabase/migrations/20260424120200_upload_inventory_counts_rpc.sql`

**Context:** Takes a JSONB array of `{ part_name, on_hand, min_stock? }` rows. Updates matched parts atomically; reports matched/unmatched to the client.

- [ ] **Step 1: Create the migration file**

Write `supabase/migrations/20260424120200_upload_inventory_counts_rpc.sql`:

```sql
-- Bulk-apply a CSV upload of physical inventory counts. Match is
-- case-insensitive + trimmed against payroll_parts_prices.name.
-- Unmatched rows are returned to the client so the operator can add
-- them to the library and re-upload. Whole call is one transaction;
-- malformed input from the client should be caught before this RPC.

CREATE OR REPLACE FUNCTION public.upload_inventory_counts(p_rows JSONB)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path = public
AS $$
DECLARE
  v_row        JSONB;
  v_updated    JSONB := '[]'::JSONB;
  v_unmatched  JSONB := '[]'::JSONB;
  v_matched    INT := 0;
  v_existing   RECORD;
BEGIN
  FOR v_row IN SELECT * FROM jsonb_array_elements(p_rows) LOOP
    SELECT id, name, on_hand
      INTO v_existing
      FROM public.payroll_parts_prices
      WHERE LOWER(TRIM(name)) = LOWER(TRIM(v_row->>'part_name'))
      LIMIT 1;

    IF v_existing.id IS NULL THEN
      v_unmatched := v_unmatched || jsonb_build_array(v_row->>'part_name');
      CONTINUE;
    END IF;

    UPDATE public.payroll_parts_prices SET
      on_hand = (v_row->>'on_hand')::NUMERIC,
      min_stock = CASE
        WHEN v_row ? 'min_stock' AND (v_row->>'min_stock') IS NOT NULL
          THEN (v_row->>'min_stock')::NUMERIC
        ELSE min_stock
      END,
      last_count_at = NOW()
    WHERE id = v_existing.id;

    v_updated := v_updated || jsonb_build_array(jsonb_build_object(
      'id', v_existing.id,
      'name', v_existing.name,
      'old_on_hand', v_existing.on_hand,
      'new_on_hand', (v_row->>'on_hand')::NUMERIC
    ));
    v_matched := v_matched + 1;
  END LOOP;

  RETURN jsonb_build_object(
    'matched', v_matched,
    'updated', v_updated,
    'unmatched', v_unmatched
  );
END;
$$;

GRANT EXECUTE ON FUNCTION public.upload_inventory_counts(JSONB) TO authenticated;
```

- [ ] **Step 2: Apply + smoke-test**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx supabase db push
```

Optional smoke-test via MCP `execute_sql` (skip if blocked):

```sql
INSERT INTO public.payroll_parts_prices (name, total_cost, on_hand)
VALUES ('TEST Upload Target', 5, 10) ON CONFLICT (name) DO UPDATE SET on_hand = 10;

SELECT public.upload_inventory_counts('[
  { "part_name": "test upload target", "on_hand": 42, "min_stock": 5 },
  { "part_name": "nonexistent part", "on_hand": 99 }
]'::jsonb);
-- Expected: { "matched": 1, "updated": [{...on_hand: 42}], "unmatched": ["nonexistent part"] }

SELECT name, on_hand, min_stock, last_count_at IS NOT NULL AS has_last_count
FROM public.payroll_parts_prices WHERE name = 'TEST Upload Target';
-- Expected: on_hand=42, min_stock=5, has_last_count=true

DELETE FROM public.payroll_parts_prices WHERE name = 'TEST Upload Target';
```

- [ ] **Step 3: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add supabase/migrations/20260424120200_upload_inventory_counts_rpc.sql
git commit -m "$(cat <<'EOF'
feat(payroll): upload_inventory_counts RPC for bulk CSV reset

Takes a JSONB array of { part_name, on_hand, min_stock? } rows.
Match is case-insensitive + whitespace-trimmed. Unmatched rows come
back in the response so the UI can warn the operator. Stamps
last_count_at on every matched row. One transaction.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Pure CSV parser + Vitest

**Files:**
- Create: `src/lib/payroll/inventoryCsv.ts`
- Create: `src/lib/payroll/__tests__/inventoryCsv.test.ts`

**Context:** Parses the operator's CSV before it hits the RPC. Rejects non-numeric values. Two- or three-column input. Optional header row auto-detected.

- [ ] **Step 1: Write the failing test**

Create `src/lib/payroll/__tests__/inventoryCsv.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { parseInventoryCsv } from "../inventoryCsv";

describe("parseInventoryCsv", () => {
  it("parses 2-column CSV without header", () => {
    const { rows, parseErrors } = parseInventoryCsv("Titan rollers,42\n7' LiftMaster 8365 opener,3");
    expect(parseErrors).toEqual([]);
    expect(rows).toEqual([
      { part_name: "Titan rollers", on_hand: 42 },
      { part_name: "7' LiftMaster 8365 opener", on_hand: 3 },
    ]);
  });

  it("parses 3-column CSV with min_stock", () => {
    const { rows, parseErrors } = parseInventoryCsv("Titan rollers,42,10\nCables,18,5");
    expect(parseErrors).toEqual([]);
    expect(rows).toEqual([
      { part_name: "Titan rollers", on_hand: 42, min_stock: 10 },
      { part_name: "Cables", on_hand: 18, min_stock: 5 },
    ]);
  });

  it("auto-detects and skips header row when 2nd cell is non-numeric", () => {
    const { rows } = parseInventoryCsv("Part,On hand\nTitan rollers,42");
    expect(rows).toEqual([{ part_name: "Titan rollers", on_hand: 42 }]);
  });

  it("trims whitespace from cells", () => {
    const { rows } = parseInventoryCsv("  Titan rollers ,  42  ");
    expect(rows).toEqual([{ part_name: "Titan rollers", on_hand: 42 }]);
  });

  it("skips empty lines silently", () => {
    const { rows } = parseInventoryCsv("Titan rollers,42\n\n\nCables,18");
    expect(rows.length).toBe(2);
  });

  it("flags non-numeric on_hand as a parse error, other rows still parsed", () => {
    const { rows, parseErrors } = parseInventoryCsv("Titan rollers,42\nBroken row,abc\nCables,18");
    expect(rows).toEqual([
      { part_name: "Titan rollers", on_hand: 42 },
      { part_name: "Cables", on_hand: 18 },
    ]);
    expect(parseErrors).toHaveLength(1);
    expect(parseErrors[0]).toMatchObject({ lineNo: 2, reason: expect.stringContaining("on_hand") });
  });

  it("flags non-numeric min_stock as a parse error", () => {
    const { rows, parseErrors } = parseInventoryCsv("Titan rollers,42,foo");
    expect(rows).toEqual([]);
    expect(parseErrors[0].reason).toContain("min_stock");
  });

  it("handles quoted cells that contain commas", () => {
    const { rows } = parseInventoryCsv('"opener, 7 foot",3\nCables,18');
    expect(rows[0]).toEqual({ part_name: "opener, 7 foot", on_hand: 3 });
  });
});
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx vitest run src/lib/payroll/__tests__/inventoryCsv.test.ts
```

Expected: FAIL with module-not-found (`../inventoryCsv` doesn't exist yet).

- [ ] **Step 3: Write the implementation**

Create `src/lib/payroll/inventoryCsv.ts`:

```ts
// Pure CSV parser for the inventory-count upload. Two or three columns:
//   part_name, on_hand [, min_stock]
// Header row is optional — auto-detected by checking if the second cell
// of the first row is a number.

export type InventoryCsvRow = {
  part_name: string;
  on_hand: number;
  min_stock?: number;
};

export type InventoryCsvParseError = {
  lineNo: number;     // 1-indexed, skipping the header if detected
  reason: string;
};

export type InventoryCsvResult = {
  rows: InventoryCsvRow[];
  parseErrors: InventoryCsvParseError[];
};

// Minimal CSV tokenizer that handles quoted fields with embedded commas.
function tokenizeLine(line: string): string[] {
  const out: string[] = [];
  let cur = "";
  let inQuotes = false;
  for (let i = 0; i < line.length; i++) {
    const c = line[i];
    if (inQuotes) {
      if (c === '"' && line[i + 1] === '"') { cur += '"'; i++; }
      else if (c === '"') { inQuotes = false; }
      else cur += c;
    } else {
      if (c === '"' && cur.length === 0) inQuotes = true;
      else if (c === ",") { out.push(cur); cur = ""; }
      else cur += c;
    }
  }
  out.push(cur);
  return out.map((s) => s.trim());
}

export function parseInventoryCsv(text: string): InventoryCsvResult {
  const rawLines = text.split(/\r?\n/).map((l) => l.replace(/\s+$/, ""));
  const nonEmpty = rawLines.filter((l) => l.trim().length > 0);
  if (nonEmpty.length === 0) return { rows: [], parseErrors: [] };

  // Header detection: if the second cell of the first row doesn't parse
  // as a finite number, treat the first row as a header and skip it.
  const firstCells = tokenizeLine(nonEmpty[0]);
  const firstLooksLikeHeader =
    firstCells.length >= 2 &&
    !Number.isFinite(Number(firstCells[1].replace(/[,$]/g, "")));

  const dataLines = firstLooksLikeHeader ? nonEmpty.slice(1) : nonEmpty;

  const rows: InventoryCsvRow[] = [];
  const parseErrors: InventoryCsvParseError[] = [];

  dataLines.forEach((line, idx) => {
    const lineNo = idx + 1; // 1-indexed within data section
    const cells = tokenizeLine(line);
    if (cells.length < 2) {
      parseErrors.push({ lineNo, reason: "row has fewer than 2 columns" });
      return;
    }
    const part_name = cells[0];
    if (!part_name) {
      parseErrors.push({ lineNo, reason: "part_name is empty" });
      return;
    }
    const rawOnHand = cells[1].replace(/[,$]/g, "");
    const on_hand = Number(rawOnHand);
    if (!Number.isFinite(on_hand)) {
      parseErrors.push({ lineNo, reason: `on_hand is not a number: ${JSON.stringify(cells[1])}` });
      return;
    }
    let min_stock: number | undefined;
    if (cells.length >= 3 && cells[2].length > 0) {
      const rawMin = cells[2].replace(/[,$]/g, "");
      const parsedMin = Number(rawMin);
      if (!Number.isFinite(parsedMin)) {
        parseErrors.push({ lineNo, reason: `min_stock is not a number: ${JSON.stringify(cells[2])}` });
        return;
      }
      min_stock = parsedMin;
    }
    const row: InventoryCsvRow = min_stock !== undefined
      ? { part_name, on_hand, min_stock }
      : { part_name, on_hand };
    rows.push(row);
  });

  return { rows, parseErrors };
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx vitest run src/lib/payroll/__tests__/inventoryCsv.test.ts
```

Expected: all 8 tests PASS.

- [ ] **Step 5: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add src/lib/payroll/inventoryCsv.ts src/lib/payroll/__tests__/inventoryCsv.test.ts
git commit -m "$(cat <<'EOF'
feat(payroll): pure CSV parser for inventory-count uploads

Handles 2- or 3-column input (part_name, on_hand [, min_stock]),
auto-detects optional header rows, tolerates quoted cells with
embedded commas, and returns both valid rows and a per-line
parseErrors array so the UI can surface bad rows before the RPC.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Pending-stock hook

**Files:**
- Create: `src/hooks/usePendingStock.ts`

**Context:** Computes the "pending" column per part name — sum of `quantity` in `payroll_job_parts` joined to `payroll_jobs`/`payroll_runs` where the run is in_progress. Returns a `Map<string, number>` keyed by lowercased-trimmed part name.

- [ ] **Step 1: Create the hook**

Write `src/hooks/usePendingStock.ts`:

```ts
import { useQuery } from "@tanstack/react-query";
import { supabase as supabaseTyped } from "@/integrations/supabase/client";

const supabase = supabaseTyped as any;

export type PendingStock = Map<string, number>;

function keyFor(partName: string): string {
  return partName.trim().toLowerCase();
}

/**
 * Returns a Map keyed by lowercase-trimmed part_name. Consumers look up
 * pending for a part via pending.get(keyFor(part.name)) ?? 0.
 */
export function usePendingStock() {
  return useQuery<PendingStock>({
    queryKey: ["payroll", "pending-stock"],
    queryFn: async () => {
      // Pull all job_parts for jobs whose run is in_progress. Supabase
      // doesn't do cross-table aggregation over filtered joins in the
      // PostgREST client without a view, so fetch the raw rows and
      // aggregate client-side. The dataset is small (drafts only).
      const { data: runs, error: rErr } = await supabase
        .from("payroll_runs")
        .select("id")
        .eq("status", "in_progress");
      if (rErr) throw rErr;
      const runIds = (runs ?? []).map((r: { id: number }) => r.id);
      if (runIds.length === 0) return new Map();

      const { data: jobs, error: jErr } = await supabase
        .from("payroll_jobs")
        .select("id")
        .in("run_id", runIds);
      if (jErr) throw jErr;
      const jobIds = (jobs ?? []).map((j: { id: number }) => j.id);
      if (jobIds.length === 0) return new Map();

      const { data: parts, error: pErr } = await supabase
        .from("payroll_job_parts")
        .select("part_name, quantity")
        .in("job_id", jobIds);
      if (pErr) throw pErr;

      const out: PendingStock = new Map();
      for (const p of (parts ?? []) as { part_name: string; quantity: number | string }[]) {
        const k = keyFor(p.part_name);
        out.set(k, (out.get(k) ?? 0) + Number(p.quantity));
      }
      return out;
    },
    staleTime: 30_000,
  });
}

export { keyFor as pendingKeyFor };
```

- [ ] **Step 2: Typecheck**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 3: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add src/hooks/usePendingStock.ts
git commit -m "$(cat <<'EOF'
feat(payroll): usePendingStock hook for derived pending-stock column

Pulls job_parts for all in_progress runs and aggregates quantity by
lowercase-trimmed part_name. Parts Library + Home use this to show
"pending" (stock tentatively reserved by drafts) without storing it.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: Inventory columns + Upload flow on Parts Library

**Files:**
- Create: `src/components/payroll/InventoryUploadPreview.tsx`
- Modify: `src/pages/payroll/Parts.tsx`

**Context:** Adds the On hand / Min / Pending / Effective / Tracked columns and a "Upload counts" button that opens a preview modal. Inline-edit for `on_hand`, `min_stock`, `track_inventory`.

- [ ] **Step 1: Create the upload preview modal**

Write `src/components/payroll/InventoryUploadPreview.tsx`:

```tsx
import { Button } from "@/components/ui/button";
import {
  Dialog, DialogContent, DialogDescription, DialogFooter,
  DialogHeader, DialogTitle,
} from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { InventoryCsvRow, InventoryCsvParseError } from "@/lib/payroll/inventoryCsv";

export type UploadPreviewProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  rows: InventoryCsvRow[];
  parseErrors: InventoryCsvParseError[];
  onApply: () => void;
  applying: boolean;
};

export function InventoryUploadPreview({
  open, onOpenChange, rows, parseErrors, onApply, applying,
}: UploadPreviewProps) {
  const hasErrors = parseErrors.length > 0;
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>Inventory upload preview</DialogTitle>
          <DialogDescription>
            {hasErrors
              ? `${parseErrors.length} row${parseErrors.length === 1 ? "" : "s"} have parse errors — fix and re-upload.`
              : `${rows.length} row${rows.length === 1 ? "" : "s"} will be applied. Unmatched part names will be listed after Apply.`}
          </DialogDescription>
        </DialogHeader>

        {hasErrors && (
          <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm">
            <div className="font-medium text-destructive mb-2">Parse errors</div>
            <ul className="space-y-1">
              {parseErrors.map((e, i) => (
                <li key={i}><code>line {e.lineNo}</code>: {e.reason}</li>
              ))}
            </ul>
          </div>
        )}

        {!hasErrors && (
          <div className="max-h-80 overflow-y-auto border rounded-md">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Part name</TableHead>
                  <TableHead className="text-right">On hand</TableHead>
                  <TableHead className="text-right">Min stock</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((r, i) => (
                  <TableRow key={i}>
                    <TableCell>{r.part_name}</TableCell>
                    <TableCell className="text-right tabular-nums">{r.on_hand}</TableCell>
                    <TableCell className="text-right tabular-nums">
                      {r.min_stock ?? <span className="text-muted-foreground">—</span>}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={applying}>
            Cancel
          </Button>
          <Button onClick={onApply} disabled={applying || hasErrors || rows.length === 0}>
            {applying ? "Applying…" : "Apply"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

- [ ] **Step 2: Extend the Parts type + query in Parts.tsx**

In `src/pages/payroll/Parts.tsx`, extend the `Part` type and the query's column list:

Replace:

```ts
type Part = {
  id: number;
  name: string;
  total_cost: number;
  notes: string | null;
  is_one_time: boolean;
  updated_at: string;
};
```

with:

```ts
type Part = {
  id: number;
  name: string;
  total_cost: number;
  notes: string | null;
  is_one_time: boolean;
  updated_at: string;
  on_hand: number;
  min_stock: number;
  track_inventory: boolean;
  last_count_at: string | null;
};
```

Update the query select in the same file from:

```ts
.select("id, name, total_cost, notes, is_one_time, updated_at")
```

to:

```ts
.select("id, name, total_cost, notes, is_one_time, updated_at, on_hand, min_stock, track_inventory, last_count_at")
```

- [ ] **Step 3: Add Upload flow state + parser + RPC**

Still in `src/pages/payroll/Parts.tsx`, add imports near the top:

```ts
import { parseInventoryCsv, type InventoryCsvRow, type InventoryCsvParseError } from "@/lib/payroll/inventoryCsv";
import { InventoryUploadPreview } from "@/components/payroll/InventoryUploadPreview";
import { usePendingStock, pendingKeyFor } from "@/hooks/usePendingStock";
import { toast } from "@/hooks/use-toast";
```

Inside the `PayrollParts` component, add state + handlers. Place next to the existing `[search, setSearch]` line:

```ts
const [uploadOpen, setUploadOpen] = useState(false);
const [uploadRows, setUploadRows] = useState<InventoryCsvRow[]>([]);
const [uploadErrors, setUploadErrors] = useState<InventoryCsvParseError[]>([]);
const [applying, setApplying] = useState(false);
const { data: pending } = usePendingStock();
```

Add an `onInventoryCsvFile` handler inside the component, next to `handleImportFile`:

```ts
async function onInventoryCsvFile(file: File) {
  const text = await file.text();
  const { rows, parseErrors } = parseInventoryCsv(text);
  setUploadRows(rows);
  setUploadErrors(parseErrors);
  setUploadOpen(true);
}

async function applyInventoryUpload() {
  setApplying(true);
  try {
    const { data, error } = await supabase.rpc("upload_inventory_counts", { p_rows: uploadRows });
    if (error) throw error;
    const res = data as { matched: number; unmatched: string[] };
    qc.invalidateQueries({ queryKey: ["payroll", "parts"] });
    qc.invalidateQueries({ queryKey: ["payroll", "pending-stock"] });
    const unm = res.unmatched?.length ?? 0;
    toast({
      title: `Uploaded: ${res.matched} updated${unm ? `, ${unm} unmatched` : ""}`,
      description: unm
        ? `Unmatched names: ${res.unmatched.slice(0, 3).join(", ")}${unm > 3 ? ` + ${unm - 3} more` : ""}. Add them to the library and re-upload if needed.`
        : undefined,
    });
    setUploadOpen(false);
  } catch (e) {
    toast({ title: "Upload failed", description: (e as Error).message, variant: "destructive" });
  } finally {
    setApplying(false);
  }
}
```

- [ ] **Step 4: Add Upload counts button + hidden file input + preview modal mount**

In the JSX's top-right button cluster (next to "Import from Excel" and "Add Part"), insert:

```tsx
<Button variant="outline" onClick={() => document.getElementById("inventory-csv-file")?.click()}>
  <Upload className="mr-2 h-4 w-4" /> Upload counts
</Button>
<input
  id="inventory-csv-file"
  type="file"
  accept=".csv,text/csv"
  className="hidden"
  onChange={(e) => {
    const file = e.target.files?.[0];
    if (file) void onInventoryCsvFile(file);
    e.target.value = "";
  }}
/>
```

And at the bottom of the component's return, before the final `</div>`:

```tsx
<InventoryUploadPreview
  open={uploadOpen}
  onOpenChange={setUploadOpen}
  rows={uploadRows}
  parseErrors={uploadErrors}
  onApply={() => void applyInventoryUpload()}
  applying={applying}
/>
```

- [ ] **Step 5: Add inventory columns to the table**

In the `<TableHeader>` inside `Parts.tsx`, replace:

```tsx
<TableRow>
  <TableHead>Part</TableHead>
  <TableHead>Cost</TableHead>
  <TableHead>One-time</TableHead>
  <TableHead>Updated</TableHead>
  <TableHead></TableHead>
</TableRow>
```

with:

```tsx
<TableRow>
  <TableHead>Part</TableHead>
  <TableHead>Cost</TableHead>
  <TableHead className="text-right">On hand</TableHead>
  <TableHead className="text-right">Min</TableHead>
  <TableHead className="text-right">Pending</TableHead>
  <TableHead className="text-right">Effective</TableHead>
  <TableHead>Tracked?</TableHead>
  <TableHead>One-time</TableHead>
  <TableHead>Updated</TableHead>
  <TableHead></TableHead>
</TableRow>
```

In the `<TableBody>` map over `filtered`, replace the existing `<TableRow>` body with:

```tsx
{filtered.map((p) => {
  const pendingQty = Number(pending?.get(pendingKeyFor(p.name)) ?? 0);
  const effective = Number(p.on_hand) - pendingQty;
  const lowStock = p.track_inventory && Number(p.min_stock) > 0 && effective < Number(p.min_stock);
  const trackableRow = !p.is_one_time && p.track_inventory;
  return (
    <TableRow key={p.id}>
      <TableCell className="font-medium">{p.name}</TableCell>
      <TableCell className="tabular-nums">${Number(p.total_cost).toFixed(2)}</TableCell>
      <TableCell className="tabular-nums text-right">
        {trackableRow ? (
          <input
            type="number"
            step="1"
            defaultValue={Number(p.on_hand)}
            className="w-20 text-right bg-transparent border border-transparent hover:border-input rounded px-1"
            onBlur={(e) => {
              const v = Number(e.target.value);
              if (Number.isFinite(v) && v !== Number(p.on_hand)) {
                updateMutation.mutate({ id: p.id, on_hand: v, last_count_at: new Date().toISOString() } as any);
              }
            }}
          />
        ) : <span className="text-muted-foreground">—</span>}
      </TableCell>
      <TableCell className="tabular-nums text-right">
        {trackableRow ? (
          <input
            type="number"
            step="1"
            defaultValue={Number(p.min_stock)}
            className="w-16 text-right bg-transparent border border-transparent hover:border-input rounded px-1"
            onBlur={(e) => {
              const v = Number(e.target.value);
              if (Number.isFinite(v) && v !== Number(p.min_stock)) {
                updateMutation.mutate({ id: p.id, min_stock: v } as any);
              }
            }}
          />
        ) : <span className="text-muted-foreground">—</span>}
      </TableCell>
      <TableCell className={`tabular-nums text-right text-xs ${pendingQty > 0 ? "text-muted-foreground" : "text-transparent"}`}>
        {pendingQty > 0 ? pendingQty : "·"}
      </TableCell>
      <TableCell className={`tabular-nums text-right font-medium ${lowStock ? "text-destructive" : ""}`}>
        {trackableRow ? effective : <span className="text-muted-foreground">—</span>}
      </TableCell>
      <TableCell>
        <input
          type="checkbox"
          checked={p.track_inventory}
          disabled={p.is_one_time}
          onChange={(e) => updateMutation.mutate({ id: p.id, track_inventory: e.target.checked } as any)}
          className="h-4 w-4"
        />
      </TableCell>
      <TableCell>{p.is_one_time ? "Yes" : "No"}</TableCell>
      <TableCell className="text-muted-foreground text-xs">
        {new Date(p.updated_at).toLocaleDateString()}
      </TableCell>
      <TableCell>
        <Button variant="ghost" size="icon" onClick={() => {
          const newCost = Number(prompt(`New cost for ${p.name} ($)?`, p.total_cost.toString()) ?? "");
          if (newCost > 0) updateMutation.mutate({ id: p.id, total_cost: newCost });
        }} aria-label="Edit">
          <Pencil className="h-4 w-4" />
        </Button>
        <Button variant="ghost" size="icon" onClick={() => {
          if (confirm(`Delete "${p.name}"?`)) deleteMutation.mutate(p.id);
        }} aria-label="Delete">
          <Trash2 className="h-4 w-4" />
        </Button>
      </TableCell>
    </TableRow>
  );
})}
```

- [ ] **Step 6: Typecheck + run tests**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx tsc --noEmit
npx vitest run
```

Expected: tsc clean, vitest passes including the 8 new inventoryCsv tests.

- [ ] **Step 7: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add src/components/payroll/InventoryUploadPreview.tsx src/pages/payroll/Parts.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): Parts Library inventory columns + CSV upload flow

- New columns on /payroll/parts: On hand / Min / Pending / Effective
  / Tracked? Inline-editable on_hand and min_stock (number inputs,
  commit on blur). Tracked? checkbox flips track_inventory; disabled
  for is_one_time rows.
- "Upload counts" button opens a hidden file input that parses the
  CSV client-side (inventoryCsv.parseInventoryCsv) and launches an
  InventoryUploadPreview modal showing the rows that will apply.
  Apply calls upload_inventory_counts RPC; toast names matched /
  unmatched counts so the operator knows what to add to the library.
- Pending column uses usePendingStock (derived from in_progress
  drafts) — not stored.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Wire Finalize to RPC

**Files:**
- Modify: `src/pages/payroll/Run.tsx:535-539`

**Context:** Swap the client-side `.update({status:'final'})` for the RPC. Toast the stock-update count.

- [ ] **Step 1: Replace the onFinalize body**

In `src/pages/payroll/Run.tsx`, find (currently around line 535):

```tsx
          onFinalize={async () => {
            const { error } = await supabase.from("payroll_runs").update({ status: "final" }).eq("id", runId!);
            if (error) { toast({ title: "Finalize failed", description: error.message, variant: "destructive" }); return; }
            toast({ title: "Run finalized" });
            nav(`/payroll/history/${runId}`);
          }}
```

Replace with:

```tsx
          onFinalize={async () => {
            const { data, error } = await supabase.rpc("finalize_payroll_run", { p_run_id: runId! });
            if (error) { toast({ title: "Finalize failed", description: error.message, variant: "destructive" }); return; }
            const res = (data ?? {}) as { stock_updates?: number; skipped_unmatched?: number };
            const bits: string[] = [];
            if ((res.stock_updates ?? 0) > 0) bits.push(`${res.stock_updates} parts deducted from stock`);
            if ((res.skipped_unmatched ?? 0) > 0) bits.push(`${res.skipped_unmatched} parts not tracked`);
            toast({ title: "Run finalized", description: bits.length ? bits.join(" · ") : undefined });
            nav(`/payroll/history/${runId}`);
          }}
```

- [ ] **Step 2: Typecheck**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 3: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add src/pages/payroll/Run.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): Finalize uses finalize_payroll_run RPC

Swaps the client-side .update({status:'final'}) for the atomic
server RPC so the status flip and stock decrement happen in one
transaction. Toast surfaces the stock_updates count so the operator
sees "12 parts deducted from stock" on finalize.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: Low-stock card on Payroll Home

**Files:**
- Create: `src/components/payroll/LowStockCard.tsx`
- Modify: `src/pages/payroll/Home.tsx`

**Context:** Renders a warning card when any tracked part's effective stock (on_hand − pending) is below its `min_stock`. Self-hides when none.

- [ ] **Step 1: Create LowStockCard.tsx**

Write `src/components/payroll/LowStockCard.tsx`:

```tsx
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { AlertTriangle } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { supabase as supabaseTyped } from "@/integrations/supabase/client";
import { usePendingStock, pendingKeyFor } from "@/hooks/usePendingStock";

const supabase = supabaseTyped as any;

type TrackedPart = { id: number; name: string; on_hand: number; min_stock: number };

export function LowStockCard() {
  const { data: pending } = usePendingStock();
  const { data: parts = [] } = useQuery<TrackedPart[]>({
    queryKey: ["payroll", "tracked-parts-with-min"],
    queryFn: async () => {
      const { data, error } = await supabase
        .from("payroll_parts_prices")
        .select("id, name, on_hand, min_stock")
        .eq("track_inventory", true)
        .eq("is_one_time", false)
        .gt("min_stock", 0);
      if (error) throw error;
      return (data ?? []) as TrackedPart[];
    },
    staleTime: 30_000,
  });

  const low = parts.filter((p) => {
    const pendingQty = Number(pending?.get(pendingKeyFor(p.name)) ?? 0);
    const effective = Number(p.on_hand) - pendingQty;
    return effective < Number(p.min_stock);
  });
  if (low.length === 0) return null;

  const previewNames = low.slice(0, 3).map((p) => p.name).join(" · ");
  const extra = low.length > 3 ? ` · +${low.length - 3} more` : "";

  return (
    <Card className="border-warning/60 bg-warning/5">
      <CardContent className="p-4 flex items-start gap-3">
        <AlertTriangle className="h-5 w-5 mt-0.5 text-warning-foreground shrink-0" />
        <div className="flex-1">
          <div className="font-medium">
            Low stock — {low.length} part{low.length === 1 ? "" : "s"} below reorder threshold
          </div>
          <div className="text-sm text-muted-foreground">{previewNames}{extra}</div>
        </div>
        <Button asChild size="sm" variant="outline">
          <Link to="/payroll/parts">Review</Link>
        </Button>
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 2: Render on Home.tsx**

In `src/pages/payroll/Home.tsx`, add the import near the other component imports:

```ts
import { LowStockCard } from "@/components/payroll/LowStockCard";
```

In the JSX return, insert `<LowStockCard />` immediately BEFORE `<DraftsSection />`:

```tsx
<LowStockCard />
<DraftsSection />
```

- [ ] **Step 3: Typecheck + quick manual verify with preview**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx tsc --noEmit
```

Use the Preview tools to stand up the dev server and fetch `/payroll` — confirm the page loads and no runtime errors appear in console. (LowStockCard self-hides when no low-stock items, so a clean DB won't show anything; that's expected.)

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add src/components/payroll/LowStockCard.tsx src/pages/payroll/Home.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): low-stock alert card on Payroll Home

Queries tracked, non-one-time parts with min_stock > 0 and filters
to rows whose on_hand - pending < min_stock. Self-hides when none.
Shows a warning card with up to 3 part names inline and a Review
button that links to the Parts Library.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: End-to-end manual test

**Files:** none (testing only)

**Context:** Walk the full loop once to confirm everything ties together in the browser. Requires operator auth on the live dashboard; subagents should hand off to Daniel for this step if they can't log in.

- [ ] **Step 1: Start dev server**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
# preview_start server named "Twins Dashboard (Vite)" via the preview MCP
# (or `npm run dev` if running outside the agent harness)
```

- [ ] **Step 2: Seed a known-good fixture via MCP execute_sql**

```sql
UPDATE public.payroll_parts_prices
   SET on_hand = 100, min_stock = 10, track_inventory = TRUE, last_count_at = NOW()
 WHERE name = 'Titan rollers';
```

Pick any real part from the library. Record its `on_hand` before testing.

- [ ] **Step 3: Confirm Parts Library columns**

Navigate to `/payroll/parts`. Verify:
- On hand shows 100 for Titan rollers
- Min shows 10
- Pending is empty (no drafts consumed rollers)
- Effective shows 100
- Tracked? checkbox is checked

Edit the on_hand inline to 5 → tab out → expect the cell to commit (refetch runs, shows 5). Effective now shows 5, which is < 10 → cell renders red.

- [ ] **Step 4: Confirm Home low-stock card**

Navigate to `/payroll`. Verify the yellow "Low stock — 1 part below reorder threshold" card appears with "Titan rollers" listed. Click Review → lands on Parts Library.

Edit on_hand back to 100 on the Titan rollers row. Reload Home → card disappears.

- [ ] **Step 5: Confirm CSV upload**

Create a test CSV locally named `test-counts.csv` with content:

```
Titan rollers,50,10
Made-up part name,99
```

On `/payroll/parts`, click **Upload counts** → select the file → preview shows 2 rows. Click Apply.

Expected toast: "Uploaded: 1 updated, 1 unmatched". Titan rollers row now shows on_hand=50. A "Made-up part name" entry is NOT created.

- [ ] **Step 6: Confirm Finalize decrement**

Create a small test payroll:
1. Run Payroll → pick a week with HCP data → Sync → Review one job → add "2x Titan rollers" to the job.
2. Step 4 → click Finalize.
3. Expected toast: "Run finalized · 1 parts deducted from stock" (exact wording may vary).
4. Back on `/payroll/parts`, Titan rollers should show on_hand=48 (50 − 2).

- [ ] **Step 7: Cleanup**

```sql
-- Restore Titan rollers to a realistic baseline
UPDATE public.payroll_parts_prices SET on_hand = <your real count>, min_stock = 0
 WHERE name = 'Titan rollers';
-- Delete the test run via the history Trash icon or via SQL.
```

No code change; no commit.

---

## Out-of-scope (not in this plan)

The following are noted in the spec and NOT built here:

- Email / push / SMS low-stock notifications.
- Reorder-quantity suggestions.
- Purchase / receiving log.
- Per-tech / per-truck stock splits.
- Auto-creation of parts from unmatched CSV rows.
- Historical "parts used per week" chart.
- Undo a finalize (would need to reverse stock decrement).
