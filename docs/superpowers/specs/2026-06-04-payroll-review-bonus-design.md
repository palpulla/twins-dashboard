# Payroll 5-Star Review Bonus — Design

Date: 2026-06-04
Repo: `twins-dash` (working copy `twins-dash-payroll-work`, branch `feature/payroll-draft-sync`)
Status: Approved, ready for implementation plan

## Goal

Add a per-tech 5-star review bonus to the weekly payroll run:

- Each individual tech gets a **5-star review count** entered manually on the final payroll screen before submitting, the same way parts are categorized to a job.
- Each 5-star review is worth **$10 extra** to that tech.
- The bonus is reflected on the **paystub PDF** (and Excel) sent to techs.
- The bonus is **excluded from Charles's 2% supervisor override** when another tech earns it.

## Non-Goals (explicitly out of scope)

- The Google Business Profile (GBP) reviews ingestion pipeline (`reviews` table, `sync-gbp-reviews`, OAuth). That work is dormant pending OAuth and Daniel chose to "deal with this after." This feature does **not** read from or depend on the `reviews` table.
- Automatic attribution of reviews to techs (fuzzy match, GHL redirect). Attribution here is 100% manual operator entry.
- Editing review counts after a run is finalized.

## Key Architectural Fact (why Charles's 2% is safe for free)

The Charles override is computed **per job, on the job's `basis`** in `computeCommissions` (`src/lib/payroll/commission.ts`) and stored as `kind = 'override'` rows in `payroll_commissions`. The review bonus is a **per-tech, per-week amount applied only at the summary/aggregation level** — it is never part of any job's `basis` and never generates a commission row. Therefore the supervisor override mathematically cannot see it. No special-casing required; a regression test will assert it.

Corollary: Charles can earn his own review bonus (admin enters a count for him, he gets $10 each). Only *other* techs' review bonuses are excluded from his override, which the architecture already guarantees.

## Data Model

New table:

```sql
CREATE TABLE public.payroll_run_review_counts (
  id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  run_id       INT  NOT NULL REFERENCES public.payroll_runs(id) ON DELETE CASCADE,
  tech_name    TEXT NOT NULL,
  review_count INT  NOT NULL DEFAULT 0 CHECK (review_count >= 0),
  UNIQUE (run_id, tech_name)
);
```

- `tech_name` matches how `payroll_commissions` already keys techs (text name, not `tech_id`).
- RLS: enable, and attach the same `FOR ALL TO authenticated USING/WITH CHECK has_payroll_access(auth.uid())` policy that every other payroll table uses (see `20260418100001_payroll_rbac.sql`).
- Reversibility: migration is a single `CREATE TABLE` + one policy; rollback is `DROP TABLE`.

We store the **count** only. Dollars are derived (`count × rate`) so a future rate change recomputes historical paystubs consistently and there is one source of truth.

Rate constant (single source of truth), in `src/lib/payroll/commission.ts` (or a shared payroll constants module):

```ts
export const REVIEW_BONUS_PER_5_STAR = 10; // USD per 5-star review
```

## Persistence Path

Mirror manual parts entry: direct table writes from the client via the typed-cast supabase client.

- Read: `supabase.from("payroll_run_review_counts").select("*").eq("run_id", runId)`.
- Write: `supabase.from("payroll_run_review_counts").upsert({ run_id, tech_name, review_count }, { onConflict: "run_id,tech_name" })` on input change.
- No finalize RPC change. The Step 4 summary and the History summary are both recomputed live from the tables via `aggregate()`, so persisting the count is sufficient for it to survive finalize and reprint identically.

## Aggregation Math (`src/lib/payroll/aggregation.ts`)

`aggregate()` gains a new optional parameter:

```ts
reviewCounts: Record<string, number> = {}
```

`AggregateSummaryRow` gains two fields:

```ts
review_count: number;  // raw count entered
review_bonus: number;  // review_count * REVIEW_BONUS_PER_5_STAR
```

The review bonus is treated as **earned pay, like a bonus (not a tip)**:

- `review_count = reviewCounts[name] ?? 0`
- `review_bonus = review_count * REVIEW_BONUS_PER_5_STAR`
- `preMakeup = commission + bonuses + overrides + tips + review_bonus`  (so it counts toward the weekly-minimum top-up)
- `makeup = weekly_minimum > 0 ? max(0, weekly_minimum - preMakeup) : 0`
- `final = preMakeup + makeup`
- Pre-tip display (Commission + Bonuses + Overrides + Makeup) also adds `review_bonus`.

Override rows are untouched by this function path; `review_bonus` is purely additive at the per-tech summary level.

## Step 4 UI (`src/pages/payroll/Run.tsx`, `Step4Summary`)

- New column header **"5★ Reviews"**.
- Per-tech cell: a small number `Input` bound to `review_count`, with a compact derived label beside/below it, e.g. `× $10 = $20`.
- On change: optimistic local state update (so Final Pay recomputes live) + `upsert` to `payroll_run_review_counts`.
- Pass a `reviewCounts` map into `aggregate()` alongside the existing args.
- Totals row sums the review count and review bonus.
- The per-tech `.pdf` button `disabled` condition changes from `s.jobs === 0 && s.makeup === 0` to also allow when `s.review_bonus > 0` (a tech with reviews but no jobs can still get a paystub).
- Editing allowed only while run `status = 'in_progress'`. In History the column is read-only display.

The column placement and styling follow the existing summary table (keep it compact; the table is already wide — one count column + inline derived dollar, not two columns).

## Paystub PDF + Excel (`src/lib/payroll/pdfExport.ts`, `src/lib/payroll/excelExport.ts`)

- `SummaryRow` (in `excelExport.ts`) gains `review_count` and `review_bonus`.
- `reportFromFlat` threads the new fields through.
- `TechPaystub` gains `review_count` + `review_bonus`; `buildTechPaystub` reads them from the matching summary row.
- Paystub PDF: render a clear summary line — **"5-Star Reviews   2 × $10 = $20"** — included in the final-pay total, placed alongside the existing mgmt/makeup lines.
- Excel paystub sheet (`addPaystubSheet`): add the same line.
- When `review_count` is 0, omit the line (no clutter), consistent with how zero-value lines are handled.

## History (`src/pages/payroll/HistoryDetail.tsx`)

- Load `payroll_run_review_counts` for the run and pass the map into `aggregate()`.
- Display is read-only (finalized runs are immutable here).
- Re-downloaded paystubs reflect the stored counts identically.

## Testing

- `aggregation` unit test: given review counts, `review_bonus` = count × 10, included in `final` and `preMakeup`; weekly-minimum makeup shrinks accordingly.
- `commission` regression test: review counts do not change any `override_amt` (Charles's 2%) — overrides depend only on job basis.
- Paystub builder test: `buildTechPaystub` surfaces `review_count`/`review_bonus` and the PDF/Excel line renders only when count > 0.
- Edge: tech with a review but zero jobs still produces a paystub and a positive final pay.

## Files Touched

- `supabase/migrations/<ts>_payroll_run_review_counts.sql` (new)
- `src/lib/payroll/commission.ts` (rate constant)
- `src/lib/payroll/aggregation.ts` (math + new param/fields)
- `src/lib/payroll/excelExport.ts` (SummaryRow, reportFromFlat, buildTechPaystub, TechPaystub, addPaystubSheet)
- `src/lib/payroll/pdfExport.ts` (paystub review line)
- `src/pages/payroll/Run.tsx` (Step 4 input + persistence)
- `src/pages/payroll/HistoryDetail.tsx` (load + pass counts, read-only)
- Tests alongside the above.

## Reversibility

- DB: `DROP TABLE payroll_run_review_counts`.
- Code: all changes are additive (new optional param defaults to `{}`, new fields default to 0). KPI math for existing commission/override/bonus is unchanged.
