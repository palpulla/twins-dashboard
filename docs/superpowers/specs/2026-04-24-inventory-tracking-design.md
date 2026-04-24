# Parts Inventory Tracking Design Spec

**Date:** 2026-04-24
**Status:** Design approved, ready for implementation plan
**Scope:** Track physical stock of parts in the Twins Garage Doors parts library. Stock decrements atomically when a payroll run is finalized. Operator periodically uploads a CSV of counted inventory to resync when reality drifts. Low-stock alerts surface on Parts Library and Payroll Home.

## Context

The payroll system already captures which parts get used on each job ([payroll_job_parts](../../../twins-dash/supabase/migrations/20260418100000_payroll_schema.sql#L69)). The parts library at `/payroll/parts` ([Parts.tsx](../../../twins-dash/src/pages/payroll/Parts.tsx)) lists every part with name + unit cost, but has no stock field. Today the operator guesses when to reorder Titan rollers by memory.

This spec adds stock tracking on top of that foundation. Finalize-time deduction + periodic count upload is the simplest model that matches how a 3-tech shop actually operates: trucks get restocked from the shop, counts drift, and the operator runs a physical count every few weeks to re-baseline the system.

## Goals

- Record `on_hand` per part. Decrement atomically when a payroll run is finalized.
- Accept a CSV upload of physical counts to reset `on_hand` per part and mark `last_count_at`.
- Surface a **pending** value (derived) showing how much stock is tentatively reserved by in-progress drafts.
- Alert when `on_hand − pending < min_stock` (per-part threshold, zero = no alert).
- No ledger table; live `on_hand` is authoritative.

## Non-goals

- No email / push notifications for low stock. (Out-of-scope v1 followup.)
- No reorder-quantity suggestions.
- No purchase / receiving log.
- No per-truck / per-tech stock splits.
- No auto-creation of parts from unmatched CSV rows — those surface as a warning list; the operator adds them manually to the Parts Library first, then re-uploads.
- No "un-finalize a run" — already non-reversible in the existing flow; stock decrement inherits that property.

## Data model

Add four columns to `public.payroll_parts_prices`:

| Column | Type | Default | Purpose |
|---|---|---|---|
| `on_hand` | `NUMERIC(10,2) NOT NULL` | `0` | Live stock count. Decremented on Finalize. Reset by CSV upload. |
| `min_stock` | `NUMERIC(10,2) NOT NULL` | `0` | Reorder threshold. `0` means no alert. |
| `last_count_at` | `TIMESTAMPTZ` | NULL | When `on_hand` was last set by a CSV upload or manual edit. Purely informational. |
| `track_inventory` | `BOOLEAN NOT NULL` | `TRUE` | Opt-out for services / non-physical items. Also auto-skipped when `is_one_time = true`. |

**Pending** (stock consumed by in-progress drafts) is NOT stored. It's derived at read time via:

```sql
SELECT jp.part_name, SUM(jp.quantity) AS pending
FROM public.payroll_job_parts jp
JOIN public.payroll_jobs j ON jp.job_id = j.id
JOIN public.payroll_runs r ON j.run_id = r.id
WHERE r.status = 'in_progress'
GROUP BY jp.part_name;
```

**Effective** = `on_hand − pending`.

### RLS

The `"payroll access"` policy on `payroll_parts_prices` already covers all DML for authenticated users with payroll access. No change.

## Backend

### RPC 1 — `finalize_payroll_run(p_run_id INT) RETURNS JSONB`

Replaces the client-side `supabase.from("payroll_runs").update({ status: "final" }).eq("id", runId)` call with an atomic server-side transaction.

Steps (single transaction):

1. SELECT the run FOR UPDATE; raise if it doesn't exist or its `status != 'in_progress'`.
2. UPDATE `payroll_runs` SET `status = 'final'`.
3. For each row in `payroll_job_parts` joined to `payroll_jobs` where `run_id = p_run_id`: look up the matching part in `payroll_parts_prices` by `LOWER(TRIM(name))`. If found AND `track_inventory = true` AND `is_one_time = false`: UPDATE `on_hand = on_hand − quantity`. Accumulate a count of updated parts rows.
4. Return `{ status: 'final', stock_updates: <int>, skipped_unmatched: <int> }`.

`SECURITY INVOKER` + `SET search_path = public`. RLS on the three tables already restricts callers to payroll-access users.

### RPC 2 — `upload_inventory_counts(p_rows JSONB) RETURNS JSONB`

Input: JSONB array of objects with shape:

```json
[{ "part_name": "Titan rollers", "on_hand": 42, "min_stock": 10 }, …]
```

`min_stock` is optional. `part_name` matched case-insensitively after trimming whitespace.

Steps:

1. For each incoming row: SELECT the part by `LOWER(TRIM(name)) = LOWER(TRIM(<p_row>.part_name))`.
2. If found: UPDATE `on_hand = <p_row>.on_hand`, `min_stock = COALESCE(<p_row>.min_stock, min_stock)`, `last_count_at = NOW()`. Push onto `updated` list: `{ id, name, old_on_hand, new_on_hand }`.
3. If not found: push `part_name` onto the `unmatched` list.
4. Return `{ matched: <int>, updated: [...], unmatched: [...] }`.

Entire call runs in one transaction; a single bad row (e.g., non-numeric `on_hand` coerced to NaN by the client) should never land a partial state. Client-side validation rejects bad rows before the RPC.

### Client-side CSV parser

Implemented in `twins-dash/src/lib/payroll/inventoryCsv.ts`. Pure TypeScript, Vitest-tested.

- Accepts CSV with 2 columns (`part_name,on_hand`) or 3 columns (`part_name,on_hand,min_stock`).
- First-row detection: if the first row's second cell is NOT a number, treat it as a header and skip it; header names are case-insensitive and ignored beyond the count.
- Trims every cell. Empty part_name → skipped silently.
- Non-numeric `on_hand` or `min_stock` → returns a `parseErrors` array; caller aborts the upload and toasts the bad rows.
- Returns `{ rows: [{ part_name, on_hand, min_stock? }], parseErrors: [{ lineNo, reason }] }`.

## UI

### `/payroll/parts` — Parts Library

Add columns (after `Total Cost`):

| On hand | Min | Pending | Effective | Tracked? |
|---|---|---|---|---|
| 42.00 | 10 | 5 *(gray, small)* | 37 *(red if < min)* | ☑ |

- **On hand** + **Min** are inline-editable number inputs. Enter or blur commits via `UPDATE payroll_parts_prices SET …`.
- **Pending** shown only when > 0, gray + small.
- **Effective** red text when `effective < min_stock && min_stock > 0`.
- **Tracked?** checkbox binds to `track_inventory`. When off, row is greyed; no alerting.

New **Upload counts** button in the card header, right-aligned. Click → native `<input type=file accept=".csv">` → on change, read the file with `FileReader`, parse with `inventoryCsv.parse()`.

On parse success, render a preview modal:

- Top: *"N rows will be updated, M unmatched."*
- Main: table of matched rows with old → new `on_hand` (+ optional min_stock delta).
- Collapsible: unmatched rows. Explanation: *"These part names don't exist in the library. Add them to the Parts Library first, then re-upload."*
- Footer: **Cancel** | **Apply** buttons. Apply fires the RPC and toasts `{matched, unmatched}`.

### `/payroll` — Payroll Home low-stock card

Between the "Run this week's payroll" hero and the Drafts section, render (only when at least one tracked part has `effective < min_stock && min_stock > 0`):

```
┌─────────────────────────────────────────────────┐
│ ⚠ Low stock — 3 parts below reorder threshold   │
│   Titan rollers · 7' LiftMaster opener · cables │
│                                      [Review →] │
└─────────────────────────────────────────────────┘
```

Link target: `/payroll/parts?filter=low-stock` (Parts Library reads the query param and filters).

### Run Step 4 — Finalize

Change the Finalize button's handler from:

```ts
const { error } = await supabase.from("payroll_runs").update({ status: "final" }).eq("id", runId);
```

to:

```ts
const { data, error } = await supabase.rpc("finalize_payroll_run", { p_run_id: runId });
```

Toast message updated: on success, show `"Run finalized · {stock_updates} parts deducted from stock"`. If `skipped_unmatched > 0`, include `· {N} parts not tracked` in the description.

No other changes to Step 3 or Step 2. Stock only moves on Finalize.

## Error handling / edge cases

- **Stock goes negative:** allowed. Happens when the count is stale or a tech pulled from an untracked stash. Row still flagged low-stock.
- **Two concurrent Finalize clicks:** the RPC's `SELECT … FOR UPDATE` + status check raises on the second; UI toasts the error.
- **Upload row with `on_hand = 0`:** valid; sets stock to zero. Triggers low-stock alert if `min_stock > 0`.
- **Upload row referencing a part marked `track_inventory = false`:** match succeeds but UPDATE still writes `on_hand` + `last_count_at`. No alerting triggered. The operator later toggles `track_inventory` back on if they want it flagged.
- **`is_one_time` parts:** never decremented on Finalize. Shown with a dash in the On hand column (not a zero).
- **Unmatched CSV name differs only by casing / whitespace:** matches (RPC lowercases + trims both sides). Differs by typo → unmatched; show in the warning list.
- **Deleting a non-final run** (from the delete-unran feature): no stock side effect, since stock only moved on finalize.

## Testing

- **Vitest unit tests** for `inventoryCsv.parse()`: header detection, two- vs. three-column input, trimming, quoted cells, parse errors for non-numeric values, empty lines skipped.
- **Postgres smoke test** for `finalize_payroll_run`: create a draft run with two parts and one untracked part, call the RPC, assert `on_hand` decremented for tracked parts and untouched for the untracked one, and that the run's status is `final`.
- **Postgres smoke test** for `upload_inventory_counts`: mix of matched + unmatched + with-min_stock + without-min_stock rows; assert return shape and row state.
- **Manual E2E:** upload a CSV of real parts, finalize a test run, observe stock decrement, manually set one part's `on_hand` below `min_stock`, reload `/payroll` and confirm the low-stock card appears.

No UI-level automated tests (no RTL harness in this repo for payroll pages).

## Out-of-scope followups

- Email / push / SMS low-stock notifications.
- Reorder-quantity suggestions (weeks-of-supply model).
- Purchase / receiving flow (logging `+20 Titan rollers received` when a delivery arrives).
- Per-tech / per-truck stock splits.
- Auto-creation of parts from unmatched CSV rows.
- Historical "parts used per week" chart (data is already there — just need a view).
- Undo a finalize (would need to reverse the stock decrement; currently finalize is one-way).
