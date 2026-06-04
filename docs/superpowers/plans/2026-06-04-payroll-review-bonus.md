# Payroll 5-Star Review Bonus Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the payroll operator enter a per-tech 5-star review count on the final payroll screen; each review pays the tech $10, shows on their paystub PDF/Excel, and is excluded from Charles's 2% supervisor override.

**Architecture:** The review bonus is a per-run, per-tech value persisted in a new `payroll_run_review_counts` table and applied only at the summary/aggregation level (`aggregate()`), never inside a job's commission `basis`. Because the Charles override is computed per-job on `basis` in `computeCommissions`, it never sees the review bonus — the exclusion is automatic. Dollars are derived as `count × $10` from a single constant.

**Tech Stack:** Vite + React + TypeScript, Vitest, Supabase (Postgres + RLS), ExcelJS, jsPDF.

**Working directory:** `/Users/daniel/twins-dashboard/twins-dash-payroll-work` (branch `feature/payroll-draft-sync`). All paths below are relative to this directory unless noted.

**Spec:** `../docs/superpowers/specs/2026-06-04-payroll-review-bonus-design.md` (outer repo).

---

## File Structure

- **Create** `supabase/migrations/20260604120000_payroll_run_review_counts.sql` — new table + RLS policy.
- **Modify** `src/lib/payroll/commission.ts` — export `REVIEW_BONUS_PER_5_STAR` constant.
- **Modify** `src/lib/payroll/aggregation.ts` — `aggregate()` gains `reviewCounts` param; `AggregateSummaryRow` gains `review_count`/`review_bonus`; bonus folded into pre-makeup/pre-tip/final.
- **Modify** `src/lib/payroll/excelExport.ts` — `SummaryRow`, `TechPaystub`, `reportFromFlat`, `buildTechPaystub`, `addPaystubSheet`, `addSummarySheet` carry review fields + render a review line/column.
- **Modify** `src/lib/payroll/pdfExport.ts` — `reportFromFlat`, `renderPaystubPage`, payroll summary table, and per-tech paystub include-condition carry/render review fields.
- **Modify** `src/pages/payroll/Run.tsx` — Step 4 input column, load/upsert counts, pass `reviewCounts` to `aggregate()`.
- **Modify** `src/pages/payroll/HistoryDetail.tsx` — load counts, pass to `aggregate()` read-only.
- **Tests** `src/lib/payroll/__tests__/aggregation.test.ts`, `src/lib/payroll/__tests__/excelExport.test.ts`.

Run tests with: `npx vitest run <path>` (there is no `test` npm script; invoke vitest directly).

---

## Task 1: Database migration — `payroll_run_review_counts`

**Files:**
- Create: `supabase/migrations/20260604120000_payroll_run_review_counts.sql`

- [ ] **Step 1: Write the migration**

Create `supabase/migrations/20260604120000_payroll_run_review_counts.sql`:

```sql
-- Per-run, per-tech 5-star review counts. Each review pays the tech $10,
-- applied at the payroll summary level (never inside a job commission basis),
-- so it is automatically excluded from Charles's 2% supervisor override.
--
-- Reversibility: DROP TABLE public.payroll_run_review_counts;

CREATE TABLE IF NOT EXISTS public.payroll_run_review_counts (
  id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  run_id       INT  NOT NULL REFERENCES public.payroll_runs(id) ON DELETE CASCADE,
  tech_name    TEXT NOT NULL,
  review_count INT  NOT NULL DEFAULT 0 CHECK (review_count >= 0),
  UNIQUE (run_id, tech_name)
);

CREATE INDEX IF NOT EXISTS idx_payroll_run_review_counts_run
  ON public.payroll_run_review_counts(run_id);

ALTER TABLE public.payroll_run_review_counts ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS payroll_run_review_counts_all ON public.payroll_run_review_counts;
CREATE POLICY payroll_run_review_counts_all
  ON public.payroll_run_review_counts
  FOR ALL TO authenticated
  USING (public.has_payroll_access(auth.uid()))
  WITH CHECK (public.has_payroll_access(auth.uid()));

GRANT SELECT, INSERT, UPDATE, DELETE
  ON public.payroll_run_review_counts TO authenticated;
```

- [ ] **Step 2: Apply the migration to the live `jwrpj` project**

Use the Supabase MCP `apply_migration` tool on the `jwrpj` project with name `payroll_run_review_counts` and the SQL body above. (Do not disable webhooks or touch other objects.) Per the known migration-history desync on this project, after applying, verify the table exists with `list_tables` (or `execute_sql: SELECT to_regclass('public.payroll_run_review_counts');`).

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260604120000_payroll_run_review_counts.sql
git commit -m "feat(payroll): payroll_run_review_counts table for 5-star review bonus"
```

---

## Task 2: Review bonus rate constant + aggregation math

**Files:**
- Modify: `src/lib/payroll/commission.ts` (add constant near top, after imports)
- Modify: `src/lib/payroll/aggregation.ts` (`AggregateSummaryRow`, `aggregate` signature + math)
- Test: `src/lib/payroll/__tests__/aggregation.test.ts`

- [ ] **Step 1: Add the rate constant**

In `src/lib/payroll/commission.ts`, add at the top of the file (after the `export type` block is fine; put it right below the final type so it is exported):

```ts
/** USD paid to a tech per individual 5-star review (manual payroll entry). */
export const REVIEW_BONUS_PER_5_STAR = 10;
```

- [ ] **Step 2: Write the failing aggregation tests**

Append to `src/lib/payroll/__tests__/aggregation.test.ts`:

```ts
import { REVIEW_BONUS_PER_5_STAR } from "../commission";

describe("aggregate — 5-star review bonus", () => {
  const techs = ["Charles Rue", "Maurice Williams", "Nicholas Roccaforte"];

  it("adds $10 per review to the tech's final pay", () => {
    const jobs = [baseJob(1, "Maurice Williams", 500)];
    const comms = [baseComm(1, "Maurice Williams", 100)]; // $100 commission
    const { summary } = aggregate(
      jobs as any, [], comms as any, techs, {}, { "Maurice Williams": 2 },
    );
    const maur = summary.find((s) => s.tech === "Maurice Williams")!;
    expect(maur.review_count).toBe(2);
    expect(maur.review_bonus).toBe(2 * REVIEW_BONUS_PER_5_STAR); // 20
    expect(maur.final).toBe(120); // 100 commission + 20 review bonus
  });

  it("defaults to 0 reviews / $0 when none provided", () => {
    const jobs = [baseJob(1, "Maurice Williams", 500)];
    const comms = [baseComm(1, "Maurice Williams", 100)];
    const { summary } = aggregate(jobs as any, [], comms as any, techs, {});
    const maur = summary.find((s) => s.tech === "Maurice Williams")!;
    expect(maur.review_count).toBe(0);
    expect(maur.review_bonus).toBe(0);
    expect(maur.final).toBe(100);
  });

  it("review bonus counts toward the weekly minimum (shrinks makeup)", () => {
    const mins = { "Maurice Williams": 800 };
    const jobs = [baseJob(1, "Maurice Williams", 500)];
    const comms = [baseComm(1, "Maurice Williams", 100)]; // $100
    const { summary } = aggregate(
      jobs as any, [], comms as any, techs, mins, { "Maurice Williams": 5 },
    );
    const maur = summary.find((s) => s.tech === "Maurice Williams")!;
    expect(maur.review_bonus).toBe(50); // 5 × 10
    expect(maur.makeup).toBe(650);      // 800 - (100 + 50)
    expect(maur.final).toBe(800);
  });

  it("does NOT change Charles's override when another tech earns reviews", () => {
    // Maurice owns a job, Charles co-listed → Charles gets a 2% override row.
    const jobs = [baseJob(1, "Maurice Williams", 500)];
    const comms = [
      baseComm(1, "Maurice Williams", 100),
      { job_id: 1, tech_name: "Charles Rue", kind: "override" as const,
        basis: 500, commission_amt: 0, bonus_amt: 0, override_amt: 10, tip_amt: 0, total: 10 },
    ];
    const withReviews = aggregate(
      jobs as any, [], comms as any, techs, {}, { "Maurice Williams": 3 },
    ).summary.find((s) => s.tech === "Charles Rue")!;
    const withoutReviews = aggregate(
      jobs as any, [], comms as any, techs, {},
    ).summary.find((s) => s.tech === "Charles Rue")!;
    expect(withReviews.overrides).toBe(10);
    expect(withReviews.overrides).toBe(withoutReviews.overrides);
    expect(withReviews.review_bonus).toBe(0); // Charles earned no reviews here
  });
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `npx vitest run src/lib/payroll/__tests__/aggregation.test.ts`
Expected: FAIL — `aggregate` takes 5 args / `review_count` undefined.

- [ ] **Step 4: Update `AggregateSummaryRow` and `aggregate()`**

In `src/lib/payroll/aggregation.ts`:

Add two fields to `AggregateSummaryRow` (after `makeup` / before `final`):

```ts
  review_count: number;  // 5-star reviews entered for this tech this run
  review_bonus: number;  // review_count * REVIEW_BONUS_PER_5_STAR
```

Add the import at the top:

```ts
import { REVIEW_BONUS_PER_5_STAR } from "./commission";
```

Change the `aggregate` signature to accept a new trailing param:

```ts
export function aggregate(
  jobs: DBJob[],
  jobParts: DBJobPart[],
  commissions: DBCommission[],
  techNames: string[],
  weeklyMinimums: Record<string, number> = {},
  reviewCounts: Record<string, number> = {},
): { summary: AggregateSummaryRow[]; jobsRows: AggregateJobsRow[]; partsRows: AggregatePartsRow[] } {
```

In the initial `summary` map, initialize the two fields:

```ts
  const summary: AggregateSummaryRow[] = techNames.map((name) => ({
    tech: name, jobs: 0, gross: 0, tips: 0, parts: 0, basis: 0,
    commission: 0, bonuses: 0, overrides: 0,
    weekly_minimum: Number(weeklyMinimums[name] ?? 0),
    review_count: Number(reviewCounts[name] ?? 0),
    review_bonus: Number(reviewCounts[name] ?? 0) * REVIEW_BONUS_PER_5_STAR,
    makeup: 0, final: 0,
  }));
```

Update the final makeup/final pass to include `review_bonus` in pre-makeup pay:

```ts
  summary.forEach((s) => {
    const preMakeup = s.commission + s.bonuses + s.overrides + s.tips + s.review_bonus;
    s.makeup = s.weekly_minimum > 0 ? Math.max(0, s.weekly_minimum - preMakeup) : 0;
    s.final = preMakeup + s.makeup;
  });
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `npx vitest run src/lib/payroll/__tests__/aggregation.test.ts`
Expected: PASS (all new + existing cases).

- [ ] **Step 6: Commit**

```bash
git add src/lib/payroll/commission.ts src/lib/payroll/aggregation.ts src/lib/payroll/__tests__/aggregation.test.ts
git commit -m "feat(payroll): review bonus in aggregation (\$10/review, outside Charles override)"
```

---

## Task 3: Paystub model + Excel rendering

**Files:**
- Modify: `src/lib/payroll/excelExport.ts` (`SummaryRow`, `TechPaystub`, `reportFromFlat`, `buildTechPaystub`, `addPaystubSheet`, `addSummarySheet`)
- Test: `src/lib/payroll/__tests__/excelExport.test.ts`

- [ ] **Step 1: Write the failing paystub-builder test**

Append to `src/lib/payroll/__tests__/excelExport.test.ts` (it already imports from `../excelExport`; add `buildTechPaystub` to the import if not present):

```ts
import { buildTechPaystub, type WeeklyReport } from "../excelExport";

describe("buildTechPaystub — review bonus", () => {
  const baseReport = (reviewCount: number, reviewBonus: number): WeeklyReport => ({
    meta: {
      week_start: new Date("2026-05-29T00:00:00"),
      week_end: new Date("2026-06-04T00:00:00"),
      run_timestamp: "2026-06-04T12:00:00Z",
      ticket_url_template: "https://pro.housecallpro.com/app/jobs/{hcp_id}",
    },
    summary: [{
      tech: "Maurice Williams", jobs: 1, gross_revenue: 500, tips: 0,
      parts_cost: 0, basis: 500, commission: 100, bonuses: 0, overrides: 0,
      final_pay: 100 + reviewBonus, makeup: 0, weekly_minimum: 0,
      review_count: reviewCount, review_bonus: reviewBonus,
    }],
    jobs: [{
      job_number: "J1", hcp_id: "h1", job_date: new Date("2026-05-30T00:00:00"),
      customer: "C", amount: 500, tip: 0, parts_cost: 0, basis: 500,
      listed_techs: "Maurice Williams", owner: "Maurice Williams",
      primary_comm: 100, charles_bonus: 0, charles_override: 0, notes: "",
    }],
    parts: [],
  });

  it("surfaces review_count/review_bonus on the paystub", () => {
    const ps = buildTechPaystub(baseReport(2, 20), "Maurice Williams");
    expect(ps.review_count).toBe(2);
    expect(ps.review_bonus).toBe(20);
    expect(ps.final_pay).toBe(120);
  });

  it("is zero when the tech earned no reviews", () => {
    const ps = buildTechPaystub(baseReport(0, 0), "Maurice Williams");
    expect(ps.review_count).toBe(0);
    expect(ps.review_bonus).toBe(0);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run src/lib/payroll/__tests__/excelExport.test.ts`
Expected: FAIL — `review_count` missing on `SummaryRow`/`TechPaystub`.

- [ ] **Step 3: Extend the types**

In `src/lib/payroll/excelExport.ts`, add to `SummaryRow` (after `makeup?`):

```ts
  review_count?: number;
  review_bonus?: number;
```

Add to `TechPaystub` (after `weekly_minimum`):

```ts
  review_count: number;
  review_bonus: number;
```

- [ ] **Step 4: Map fields in `reportFromFlat` and `buildTechPaystub`**

In `reportFromFlat` (excelExport.ts), widen the `summary` param item type to include the fields and map them. Change the `summary` param type to add `review_count?: number; review_bonus?: number;` to the inline object type, and update the `.map`:

```ts
    summary: summary.map((s) => ({
      tech: s.tech, jobs: s.jobs, gross_revenue: s.gross, tips: s.tips,
      parts_cost: s.parts, basis: s.basis, commission: s.commission,
      bonuses: s.bonuses, overrides: s.overrides, final_pay: s.final,
      makeup: s.makeup ?? 0, weekly_minimum: s.weekly_minimum ?? 0,
      review_count: s.review_count ?? 0, review_bonus: s.review_bonus ?? 0,
    })),
```

(The inline param type for `summary` becomes:
`Array<{ tech: string; jobs: number; gross: number; tips: number; parts: number; basis: number; commission: number; bonuses: number; overrides: number; makeup?: number; weekly_minimum?: number; review_count?: number; review_bonus?: number; final: number }>`)

In `buildTechPaystub`, read the fields from the matching summary row and include them in the returned object. Locate the block that reads `sRow` and returns the paystub; update it:

```ts
  const sRow = report.summary.find((s) => s.tech === techName);
  const mgmt = Number(sRow?.overrides ?? 0);
  const makeup = Number(sRow?.makeup ?? 0);
  const weekly_minimum = Number(sRow?.weekly_minimum ?? 0);
  const final_pay = Number(sRow?.final_pay ?? 0);
  const review_count = Number(sRow?.review_count ?? 0);
  const review_bonus = Number(sRow?.review_bonus ?? 0);
  return { tech: techName, week_start: report.meta.week_start, week_end: report.meta.week_end, lines, mgmt, makeup, weekly_minimum, review_count, review_bonus, final_pay };
```

- [ ] **Step 5: Render the review line in the Excel paystub footer**

In `addPaystubSheet`, after the Makeup row block and before the Total row, add:

```ts
  // 5-star review bonus row (only if non-zero)
  if (ps.review_bonus > 0) {
    paintFooterMgmtRow(ws, rowIdx++, COLS - 1, `5-Star Reviews (${ps.review_count} × $${REVIEW_BONUS_PER_5_STAR})`, COLS, ps.review_bonus);
  }
```

Add the import at the top of excelExport.ts:

```ts
import { REVIEW_BONUS_PER_5_STAR } from "./commission";
```

- [ ] **Step 6: Add a Reviews column to the Excel summary sheet so Final foots**

In `addSummarySheet`, add `"Reviews"` to `headers` immediately before `"Final Pay"`:

```ts
  const headers = ["Tech", "Jobs", "Gross", "Tips", "Parts", "Basis", "Commission", "Bonuses", "Overrides", "Makeup", "Reviews", "Final Pay"];
```

Then, in the per-row value array within `addSummarySheet`, insert the review bonus value in the same position (immediately before the final-pay cell). Locate the row-building loop in `addSummarySheet` and add `{ v: Number(s.review_bonus ?? 0), money: true }` directly before the final-pay cell push, and add the matching total. (Mirror the existing Makeup cell handling exactly; if the sheet uses a totals row, add `sum of review_bonus` before the final-pay total.)

- [ ] **Step 7: Run tests to verify they pass**

Run: `npx vitest run src/lib/payroll/__tests__/excelExport.test.ts`
Expected: PASS.

- [ ] **Step 8: Type-check the file compiles**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: no errors in `excelExport.ts`.

- [ ] **Step 9: Commit**

```bash
git add src/lib/payroll/excelExport.ts src/lib/payroll/__tests__/excelExport.test.ts
git commit -m "feat(payroll): review bonus line on Excel paystub + summary sheet"
```

---

## Task 4: PDF paystub + summary rendering

**Files:**
- Modify: `src/lib/payroll/pdfExport.ts` (`reportFromFlat`, `renderPaystubPage`, payroll summary table head/rows, paystub include-condition)

No new unit test (jsPDF output is binary canvas). Correctness of the data model is covered by Task 3's `buildTechPaystub` test, which the PDF reuses. Verify via type-check + the manual preview in Task 7.

- [ ] **Step 1: Map review fields in pdfExport's `reportFromFlat`**

`src/lib/payroll/pdfExport.ts` has its own `reportFromFlat`. Apply the SAME change as excelExport Step 4: widen the inline `summary` param type to include `review_count?: number; review_bonus?: number;` and add to the `.map`:

```ts
      review_count: s.review_count ?? 0, review_bonus: s.review_bonus ?? 0,
```

(within the existing `summary: summary.map((s) => ({ ... }))` object, alongside `makeup`/`weekly_minimum`).

- [ ] **Step 2: Render the review line in the paystub footer**

In `renderPaystubPage`, the footer builds `footerRows` of `{ label, value, kind }`. Extend the `FooterRow` kind union and push a review row after the makeup row and before the total:

Change the type:

```ts
  type FooterRow = { label: string; value: number; kind: "mgmt" | "makeup" | "review" | "total" };
```

After the `if (ps.makeup > 0) { ... }` block and before the `footerRows.push({ label: "Total", ... })`:

```ts
  if (ps.review_bonus > 0) {
    footerRows.push({
      label: `5-Star Reviews (${ps.review_count} × $${REVIEW_BONUS_PER_5_STAR})`,
      value: ps.review_bonus,
      kind: "review",
    });
  }
```

Add the import at the top of pdfExport.ts:

```ts
import { REVIEW_BONUS_PER_5_STAR } from "./commission";
```

If the footer-row rendering switches on `kind` for styling, render `"review"` with the same style as `"mgmt"`/`"makeup"` (a plain labeled money row, not the bold total).

- [ ] **Step 3: Add a Reviews column to the payroll summary PDF table so Final foots**

In the payroll summary table (the `head` array around line 205), insert `"Reviews"` immediately before `"Final"`:

```ts
  const head = ["Tech", "Jobs", "Gross", "Tips", "Parts", "Basis", "Comm", "Bonus", "Ovr", "Makeup", "Reviews", "Pre-tip", "Final"];
```

In the per-row build (the `body` rows), insert `usd(Number(r.review_bonus ?? 0))` immediately before the pre-tip cell, and in the totals row insert `usd(sum((r) => r.review_bonus ?? 0))` in the same position. Keep "Pre-tip" as `commission + bonuses + overrides + makeup` (review excluded from pre-tip column but present as its own column, so the columns + review + pre-tip foot to Final).

NOTE: confirm the SummaryRow used here carries `review_bonus`; it does after Task 3 Step 3.

- [ ] **Step 4: Include techs with only review bonus in the per-tech paystub loop**

In `downloadPayrollPDF`/the paystub loop (line ~287), change:

```ts
    if (s.jobs === 0 && (s.makeup ?? 0) === 0) continue;
```

to:

```ts
    if (s.jobs === 0 && (s.makeup ?? 0) === 0 && (s.review_bonus ?? 0) === 0) continue;
```

- [ ] **Step 5: Type-check**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: no errors in `pdfExport.ts`.

- [ ] **Step 6: Commit**

```bash
git add src/lib/payroll/pdfExport.ts
git commit -m "feat(payroll): review bonus line on PDF paystub + summary table"
```

---

## Task 5: Step 4 UI — enter review counts on the final screen

**Files:**
- Modify: `src/pages/payroll/Run.tsx` (`Step4Summary`, and the parent that renders it)

- [ ] **Step 1: Load existing review counts for the run**

In the parent `Run` component, near where jobs/parts are loaded for the run, add state and a loader. Add:

```ts
const [reviewCounts, setReviewCounts] = useState<Record<string, number>>({});
```

In the effect that loads run data (where `payroll_job_parts` is fetched by `run_id`/job ids), also fetch counts:

```ts
const { data: rc } = await supabase
  .from("payroll_run_review_counts").select("tech_name, review_count").eq("run_id", run.id);
const map: Record<string, number> = {};
for (const row of rc ?? []) map[row.tech_name] = Number(row.review_count);
setReviewCounts(map);
```

(Use the same `run.id`/`runId` source the surrounding loader already uses. If the loader is split across resume vs fresh-start paths, set `reviewCounts` in whichever path loads `payroll_job_parts`.)

- [ ] **Step 2: Add an upsert handler**

In the `Run` component add:

```ts
const setReviewCount = async (techName: string, count: number) => {
  const safe = Math.max(0, Math.floor(Number.isFinite(count) ? count : 0));
  setReviewCounts((prev) => ({ ...prev, [techName]: safe })); // optimistic
  if (!runId) return;
  const { error } = await supabase
    .from("payroll_run_review_counts")
    .upsert({ run_id: runId, tech_name: techName, review_count: safe }, { onConflict: "run_id,tech_name" });
  if (error) toast({ title: "Couldn't save review count", description: error.message, variant: "destructive" });
};
```

- [ ] **Step 3: Pass props into `Step4Summary`**

Where `<Step4Summary ... />` is rendered (around line 553), add:

```tsx
  reviewCounts={reviewCounts}
  onSetReviewCount={setReviewCount}
```

Add them to `Step4Summary`'s prop type and destructure:

```ts
  reviewCounts: Record<string, number>;
  onSetReviewCount: (techName: string, count: number) => void;
```

- [ ] **Step 4: Pass `reviewCounts` into `aggregate()` inside `Step4Summary`**

Update the `useMemo` that calls `aggregate`:

```ts
  const { summary, jobsRows, partsRows } = useMemo(
    () => {
      const mins: Record<string, number> = {};
      for (const t of techs) mins[t.name] = Number(t.weekly_minimum ?? 0);
      return aggregate(jobs as any, jobParts as any, commissions as any, techs.map((t) => t.name), mins, reviewCounts);
    },
    [jobs, jobParts, commissions, techs, reviewCounts],
  );
```

- [ ] **Step 5: Add the "5★ Reviews" column to the summary table**

In the table header, add a `<TableHead>` titled `5★ Reviews` immediately before the `Final Pay` head:

```tsx
<TableHead title="Manual entry — $10 per 5-star review">5★ Reviews</TableHead>
```

In each tech row, immediately before the `Final Pay` cell, add an editable cell:

```tsx
<TableCell>
  <div className="flex items-center gap-2">
    <Input
      type="number"
      min={0}
      className="h-8 w-16"
      value={s.review_count}
      onChange={(e) => onSetReviewCount(s.tech, parseInt(e.target.value, 10))}
    />
    <span className="text-xs text-muted-foreground whitespace-nowrap">
      {s.review_bonus > 0 ? `= ${fmtUSD(s.review_bonus)}` : `× $10`}
    </span>
  </div>
</TableCell>
```

In the TOTAL row, add a cell before the final-pay total summing review bonus:

```tsx
<TableCell>{fmtUSD(summary.reduce((a, s) => a + s.review_bonus, 0))}</TableCell>
```

- [ ] **Step 6: Enable the per-tech `.pdf` button when only reviews exist**

Change the paystub button `disabled` prop from:

```tsx
disabled={s.jobs === 0 && s.makeup === 0}
```

to:

```tsx
disabled={s.jobs === 0 && s.makeup === 0 && s.review_bonus === 0}
```

- [ ] **Step 7: Verify build + types**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: no errors.
Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 8: Commit**

```bash
git add src/pages/payroll/Run.tsx
git commit -m "feat(payroll): enter per-tech 5-star review counts on final payroll screen"
```

---

## Task 6: History — show review bonus on finalized runs (read-only)

**Files:**
- Modify: `src/pages/payroll/HistoryDetail.tsx`

- [ ] **Step 1: Load review counts for the run**

In `HistoryDetail.tsx`, where jobs/commissions are loaded by `runId`, also load counts. After the existing fetches, add:

```ts
const { data: rc } = await supabase
  .from("payroll_run_review_counts").select("tech_name, review_count").eq("run_id", runId);
const reviewCounts: Record<string, number> = {};
for (const row of rc ?? []) reviewCounts[row.tech_name] = Number(row.review_count);
```

Store it in component state alongside `jobs`/`commissions` (mirror however those are held — `useState` set in the same effect).

- [ ] **Step 2: Pass into `aggregate()`**

Update the `aggregate(...)` call (line ~49) to pass minimums (if any) and the counts. If History currently passes no minimums, pass `{}` then the counts:

```ts
const { summary, jobsRows, partsRows } = aggregate(jobs, jobParts, commissions, techNames, {}, reviewCounts);
```

- [ ] **Step 3: (Optional, read-only) show a Reviews column**

If the History summary table renders per-column cells, add a read-only `Reviews` column before `Final Pay` showing `fmtUSD(s.review_bonus)` so totals foot, mirroring Task 5 Step 5 but with no input. (If History reuses a shared summary component, no change needed beyond Step 2.)

- [ ] **Step 4: Verify build**

Run: `npx tsc --noEmit -p tsconfig.json` then `npm run build`
Expected: both succeed.

- [ ] **Step 5: Commit**

```bash
git add src/pages/payroll/HistoryDetail.tsx
git commit -m "feat(payroll): review bonus reflected in payroll history (read-only)"
```

---

## Task 7: Full verification

- [ ] **Step 1: Run the full payroll test suite**

Run: `npx vitest run src/lib/payroll`
Expected: PASS, including the new aggregation + excelExport cases and all pre-existing tests.

- [ ] **Step 2: Type-check + build + lint**

Run: `npx tsc --noEmit -p tsconfig.json && npm run build && npm run lint`
Expected: all succeed (no new lint errors in touched files).

- [ ] **Step 3: Manual smoke test via preview**

Start the dev server (preview_start). In a `Payroll → Run` flow at Step 4 (or resume a draft):
1. Enter `2` in Maurice's 5★ Reviews field → his Final Pay increases by $20 and the cell shows `= $20.00`.
2. Confirm Charles's Overrides column is unchanged by Maurice's review entry.
3. Download Maurice's per-tech `.pdf` → a "5-Star Reviews (2 × $10)" line appears and Total includes it.
4. Reload the page → the entered count persists (re-read from `payroll_run_review_counts`).
Capture a screenshot of the Step 4 table and the paystub PDF as proof.

- [ ] **Step 4: Final commit (if any preview-driven fixes were needed)**

```bash
git add -A
git commit -m "fix(payroll): review bonus smoke-test adjustments"
```

(Skip if no changes.)

---

## Self-Review Notes (coverage check)

- Spec "manual per-tech entry on final screen" → Task 5.
- "$10 per review, derived from constant" → Task 2 (constant) + aggregation math.
- "reflected on paystub PDF" → Task 4; "Excel" → Task 3.
- "excluded from Charles's 2%" → automatic (summary-level); explicitly asserted by Task 2 Step 2 test #4.
- "persists / survives finalize / shows in history" → Task 1 (table) + Task 6.
- "tech with reviews but no jobs still gets a paystub" → Task 4 Step 4 + Task 5 Step 6.
- Reversibility → Task 1 DROP TABLE; all code changes additive with defaulted params.
