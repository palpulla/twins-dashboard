# Revenue Recognition Fix + Clickable Revenue Breakdown — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop counting sold-but-unpaid jobs as earned revenue on the main dashboard, and make the Total Sales card open a slide-over panel that breaks down every job in the picked timeframe (earned vs balance-due).

**Architecture:** Introduce one shared pure module `src/lib/revenue/recognition.ts` that defines the recognition rule (a completed job's revenue is earned only when `outstanding_balance == 0`). Every revenue consumer (dashboard hook, prior-period hook, drill-down hook) imports it so the definition can never drift. Wire the existing-but-orphaned `DrilldownSheet` to the Total Sales `HeroScoreboard` via an `onClick`.

**Tech Stack:** Vite + React + TypeScript, @tanstack/react-query, Supabase JS client, vitest + @testing-library/react. Repo: `twins-dash`. DB: Supabase `jwrpjuqaynownxaoeayi`.

**Key data facts (verified against live DB):**
- `outstanding_balance` lives inside `jobs.hcp_data` JSON (not a column) and is stored in **cents**; `revenue_amount` is in **dollars**.
- Exclusion is binary and per-job: if `outstanding_balance > 0`, the whole job's `revenue_amount` is excluded from earned revenue (partial payments still exclude the full job).
- Filtering only needs `> 0` vs `== 0`; only the *displayed* owed total needs `/100` — but we surface owed as the sum of excluded jobs' `revenue_amount` (dollars), which is exactly the amount removed from the headline.

**Branch:** Create `claude/revenue-recognition-drilldown` off `origin/main` before Task 1.

---

### Task 0: Branch setup

**Files:** none (git only)

- [ ] **Step 1: Create the feature branch off origin/main**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git fetch origin
git checkout -b claude/revenue-recognition-drilldown origin/main
```

- [ ] **Step 2: Confirm clean base**

Run: `git status --short && git log --oneline -1`
Expected: working tree clean (ignore untracked `.claude/worktrees/`), HEAD at the latest `origin/main` commit.

---

### Task 1: Shared revenue-recognition helper (pure, TDD)

**Files:**
- Create: `src/lib/revenue/recognition.ts`
- Test: `src/lib/revenue/__tests__/recognition.test.ts`

- [ ] **Step 1: Write the failing test**

```ts
// src/lib/revenue/__tests__/recognition.test.ts
import { describe, it, expect } from 'vitest';
import { outstandingBalanceCents, isEarned, splitRevenue } from '../recognition';

const job = (revenue_amount: number | null, outstanding?: number | string) => ({
  revenue_amount,
  hcp_data: outstanding === undefined ? {} : { outstanding_balance: outstanding },
});

describe('outstandingBalanceCents', () => {
  it('reads the numeric value from hcp_data', () => {
    expect(outstandingBalanceCents(job(100, 1581400))).toBe(1581400);
  });
  it('coerces a string value', () => {
    expect(outstandingBalanceCents(job(100, '4900'))).toBe(4900);
  });
  it('returns 0 when missing', () => {
    expect(outstandingBalanceCents(job(100))).toBe(0);
  });
  it('returns 0 when hcp_data is null', () => {
    expect(outstandingBalanceCents({ revenue_amount: 100, hcp_data: null })).toBe(0);
  });
});

describe('isEarned', () => {
  it('is true when nothing is outstanding', () => {
    expect(isEarned(job(100, 0))).toBe(true);
    expect(isEarned(job(100))).toBe(true);
  });
  it('is false when any balance is outstanding (even partial)', () => {
    expect(isEarned(job(100, 5000))).toBe(false);
    expect(isEarned(job(15814, 1581400))).toBe(false);
  });
});

describe('splitRevenue', () => {
  it('splits earned from owed and sums revenue_amount in dollars', () => {
    const jobs = [
      job(12884, 0),       // earned
      job(15814, 1581400), // owed (full balance)
      job(49, 4900),       // owed
      job(0, 0),           // earned but zero revenue
    ];
    const r = splitRevenue(jobs);
    expect(r.earnedJobs).toHaveLength(2);
    expect(r.owedJobs).toHaveLength(2);
    expect(r.earnedRevenue).toBe(12884);
    expect(r.owedRevenue).toBe(15863);
  });
  it('treats null revenue_amount as 0', () => {
    const r = splitRevenue([job(null, 0)]);
    expect(r.earnedRevenue).toBe(0);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run src/lib/revenue/__tests__/recognition.test.ts`
Expected: FAIL — cannot resolve `../recognition`.

- [ ] **Step 3: Write minimal implementation**

```ts
// src/lib/revenue/recognition.ts

/**
 * Revenue recognition rule for Twins Garage Doors.
 *
 * A completed job's revenue is EARNED only when the customer owes nothing
 * (`outstanding_balance == 0`). Sold-but-unpaid work (HCP outstanding > 0)
 * sits in its own bucket and must never inflate earned revenue.
 *
 * `outstanding_balance` lives inside the `hcp_data` JSON blob and is stored
 * in CENTS; `revenue_amount` is in DOLLARS. The earned check only needs the
 * zero-vs-nonzero test, so units do not matter here.
 */

export interface RevenueJobLike {
  revenue_amount: number | null;
  hcp_data: unknown;
}

/** Outstanding balance in cents from hcp_data. 0 when missing or unparseable. */
export function outstandingBalanceCents(job: { hcp_data: unknown }): number {
  const hcp = job.hcp_data as { outstanding_balance?: number | string } | null;
  const raw = hcp?.outstanding_balance;
  const n = typeof raw === 'string' ? Number(raw) : raw ?? 0;
  return Number.isFinite(n) ? (n as number) : 0;
}

/** True when the job's revenue counts as earned (nothing outstanding). */
export function isEarned(job: { hcp_data: unknown }): boolean {
  return outstandingBalanceCents(job) === 0;
}

export interface RevenueSplit<T> {
  earnedJobs: T[];
  owedJobs: T[];
  /** Sum of revenue_amount (dollars) for earned jobs. */
  earnedRevenue: number;
  /** Sum of revenue_amount (dollars) for owed jobs — i.e. amount excluded from earned. */
  owedRevenue: number;
}

export function splitRevenue<T extends RevenueJobLike>(jobs: T[]): RevenueSplit<T> {
  const earnedJobs: T[] = [];
  const owedJobs: T[] = [];
  for (const j of jobs) {
    if (isEarned(j)) earnedJobs.push(j);
    else owedJobs.push(j);
  }
  const sum = (xs: T[]) => xs.reduce((s, j) => s + (j.revenue_amount || 0), 0);
  return {
    earnedJobs,
    owedJobs,
    earnedRevenue: sum(earnedJobs),
    owedRevenue: sum(owedJobs),
  };
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx vitest run src/lib/revenue/__tests__/recognition.test.ts`
Expected: PASS (all cases green).

- [ ] **Step 5: Commit**

```bash
git add src/lib/revenue/recognition.ts src/lib/revenue/__tests__/recognition.test.ts
git commit -m "feat(revenue): shared recognition rule — earned only when outstanding_balance==0"
```

---

### Task 2: Apply recognition rule to the dashboard revenue hook

**Files:**
- Modify: `src/hooks/use-dashboard-data.ts:110-149` (revenue query) and the return object at `:201-209`

This corrects `totalRevenue`, `paidJobs`, and — because `useYtdRevenue` and the prior-period `priorDashboard` both reuse this hook — the YTD hero and prior-period number too. It also exposes `owedRevenue` + `owedJobs` for the UI.

- [ ] **Step 1: Import the helper**

At the top of `src/hooks/use-dashboard-data.ts`, after the existing imports (after line 5), add:

```ts
import { splitRevenue } from "@/lib/revenue/recognition";
```

- [ ] **Step 2: Apply the split in the revenue queryFn**

Replace the body of the revenue `queryFn` return (currently lines 145-147):

```ts
      const totalRevenue = allRevenueJobs.reduce((sum, j) => sum + (j.revenue_amount || 0), 0);

      return { jobs: allRevenueJobs, totalRevenue };
```

with:

```ts
      // Recognition rule: revenue is earned only when nothing is outstanding.
      // Sold-but-unpaid jobs go in the owed bucket and never inflate revenue.
      const { earnedJobs, owedJobs, earnedRevenue, owedRevenue } = splitRevenue(allRevenueJobs);

      return {
        jobs: earnedJobs,
        totalRevenue: earnedRevenue,
        owedJobs,
        owedRevenue,
      };
```

- [ ] **Step 3: Expose owed values from the hook return**

In the hook's return object (currently lines 201-209), the existing lines are:

```ts
  return {
    jobs: jobs || [],
    paidJobs: revenueData?.jobs || [],
    totalRevenue: revenueData?.totalRevenue || 0,
    technicians: technicians || [],
    marketingSpend: marketingSpend || [],
    calls: calls || [],
```

Add two lines immediately after the `totalRevenue` line:

```ts
    totalRevenue: revenueData?.totalRevenue || 0,
    owedRevenue: revenueData?.owedRevenue || 0,
    owedJobs: revenueData?.owedJobs || [],
```

- [ ] **Step 4: Typecheck + run the full hook test suite**

Run: `npx tsc --noEmit && npx vitest run src/hooks`
Expected: no TS errors; existing hook tests pass (note: `use-ytd-revenue.test.tsx` and `use-period-comparison.test.ts` may assert revenue values — if any now fail because the mock data includes outstanding balances, fix the test expectations to the earned numbers; if the mocks have no outstanding_balance, they stay green).

- [ ] **Step 5: Commit**

```bash
git add src/hooks/use-dashboard-data.ts
git commit -m "fix(revenue): exclude sold-but-unpaid jobs from dashboard totalRevenue"
```

---

### Task 3: Apply recognition rule to the prior-period comparison hook

**Files:**
- Modify: `src/hooks/use-period-comparison.ts:50-63`

- [ ] **Step 1: Import the helper**

After the existing imports at the top of `src/hooks/use-period-comparison.ts` (after line 3), add:

```ts
import { isEarned } from '@/lib/revenue/recognition';
```

- [ ] **Step 2: Select hcp_data and filter to earned jobs**

The current query + paid-jobs filter (lines 50-62) reads:

```ts
      const { data, error } = await supabase
        .from('jobs')
        .select('revenue_amount, status')
        .eq('is_opportunity', true)
        .neq('job_type', 'Estimate')
        .not('completed_at', 'is', null)
        .gte('completed_at', fromIso)
        .lte('completed_at', toIso);
      if (error) throw error;
      const paidJobs = (data ?? []).filter((j: any) => j.status === 'paid' || j.status === 'completed');
      const totalRevenue = paidJobs.reduce((s: number, j: any) => s + Number(j.revenue_amount ?? 0), 0);
```

Replace the `.select('revenue_amount, status')` line with:

```ts
        .select('revenue_amount, status, hcp_data')
```

and replace the `paidJobs` filter line with:

```ts
      const paidJobs = (data ?? []).filter((j: any) => (j.status === 'paid' || j.status === 'completed') && isEarned(j));
```

- [ ] **Step 3: Typecheck + run the period-comparison test**

Run: `npx tsc --noEmit && npx vitest run src/hooks/__tests__/use-period-comparison.test.ts`
Expected: PASS. If the test's mocked jobs include an `outstanding_balance` and now compute a different total, update the expected values to the earned numbers and note it in the commit.

- [ ] **Step 4: Commit**

```bash
git add src/hooks/use-period-comparison.ts
git commit -m "fix(revenue): prior-period delta uses earned-revenue rule for like-for-like compare"
```

---

### Task 4: Make the drill-down hook match the headline + carry paid/owed per row

**Files:**
- Modify: `src/hooks/use-drilldown-jobs.ts` (the `DrilldownJobRow` interface, the `revenue` case, and the row mapping)

The revenue drill-down must list EVERY completed revenue job (earned + owed) so the panel reconciles to both the headline (earned) and the owed caption. Each row carries `is_earned` and `outstanding_cents`.

- [ ] **Step 1: Import the helper**

After the existing imports at the top of `src/hooks/use-drilldown-jobs.ts` (after line 3), add:

```ts
import { isEarned, outstandingBalanceCents } from "@/lib/revenue/recognition";
```

- [ ] **Step 2: Add two fields to the row interface**

In `DrilldownJobRow`, add after the `revenue_amount: number;` line:

```ts
  revenue_amount: number;
  is_earned: boolean;
  outstanding_cents: number;
```

- [ ] **Step 3: Fix the revenue predicate**

The current `revenue` case (inside the `switch (f.kpi)`):

```ts
        case "revenue":
          q = q.not("completed_at", "is", null).eq("is_opportunity", true);
          break;
```

Replace with (match the headline definition: completed + has revenue; do NOT drop owed jobs — we flag them instead):

```ts
        case "revenue":
          q = q.not("completed_at", "is", null).gt("revenue_amount", 0);
          break;
```

- [ ] **Step 4: Populate the new fields in the row mapping**

In the `.map((r: any): DrilldownJobRow => ({ ... }))`, add after the `revenue_amount: Number(r.revenue_amount ?? 0),` line:

```ts
        revenue_amount: Number(r.revenue_amount ?? 0),
        is_earned: isEarned(r),
        outstanding_cents: outstandingBalanceCents(r),
```

(The query already selects `hcp_data`, so `isEarned(r)` / `outstandingBalanceCents(r)` work directly on each row.)

- [ ] **Step 5: Typecheck**

Run: `npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add src/hooks/use-drilldown-jobs.ts
git commit -m "feat(revenue): drill-down revenue rows match headline + flag paid vs balance-due"
```

---

### Task 5: Show the earned/owed split + per-row badge in DrilldownSheet

**Files:**
- Modify: `src/components/dashboard/DrilldownSheet.tsx`

- [ ] **Step 1: Add an owed-aware subtotal header**

Inside `DrilldownSheet`, after `const rows = useMemo(() => data ?? [], [data]);` (line 42), add a memo that only applies to the revenue KPI:

```ts
  const totals = useMemo(() => {
    if (p.kpi !== 'revenue') return null;
    let earned = 0;
    let owed = 0;
    let owedCount = 0;
    for (const r of rows) {
      if (r.is_earned) earned += r.revenue_amount;
      else { owed += r.revenue_amount; owedCount += 1; }
    }
    return { earned, owed, owedCount };
  }, [rows, p.kpi]);
```

- [ ] **Step 2: Render the split under the header**

Immediately after the closing `</SheetHeader>` tag (after line 68), add:

```tsx
        {totals && (
          <div className="px-4 pb-2 flex flex-wrap gap-x-6 gap-y-1 text-sm">
            <span className="font-semibold">
              Earned: <span className="tabular-nums">{fmtMoney(totals.earned)}</span>
            </span>
            {totals.owed > 0 && (
              <span className="text-amber-600 font-medium">
                Awaiting payment: <span className="tabular-nums">{fmtMoney(totals.owed)}</span> ({totals.owedCount} not counted)
              </span>
            )}
          </div>
        )}
```

- [ ] **Step 3: Add a balance-due badge to each owed row**

The current row markup renders the amount span (line 97):

```tsx
                  <span className="tabular-nums font-semibold">{fmtMoney(r.revenue_amount)}</span>
                  <span className="text-xs text-muted-foreground">{r.estimate_status ?? '—'}</span>
```

Replace those two spans with (show a "balance due" pill instead of estimate status when the job is unpaid):

```tsx
                  <span className={`tabular-nums font-semibold ${r.is_earned ? '' : 'text-amber-600'}`}>{fmtMoney(r.revenue_amount)}</span>
                  {r.is_earned ? (
                    <span className="text-xs text-muted-foreground">{r.estimate_status ?? '—'}</span>
                  ) : (
                    <span className="text-[10px] font-semibold text-amber-700 bg-amber-100 rounded-full px-2 py-0.5 whitespace-nowrap">
                      balance due {fmtMoney(r.outstanding_cents / 100)}
                    </span>
                  )}
```

- [ ] **Step 4: Typecheck**

Run: `npx tsc --noEmit`
Expected: no errors (`r.is_earned` / `r.outstanding_cents` exist from Task 4).

- [ ] **Step 5: Commit**

```bash
git add src/components/dashboard/DrilldownSheet.tsx
git commit -m "feat(revenue): DrilldownSheet shows earned vs awaiting-payment split + balance-due badges"
```

---

### Task 6: Make the Total Sales card clickable

**Files:**
- Modify: `src/components/dashboard/HeroScoreboard.tsx` (add optional `onClick`)
- Modify: `src/pages/Index.tsx` (sheet state, wire onClick, owed caption, consistent count + sparkline)

- [ ] **Step 1: Add an optional onClick to HeroScoreboard**

In `HeroScoreboardProps` (after the `sparklineValues` field, line 15), add:

```ts
  sparklineValues?: number[] | null;
  /** When provided, the whole card becomes a button that opens the revenue breakdown. */
  onClick?: () => void;
```

Destructure it in the component signature (add `onClick,` to the params object, after `sparklineValues,`).

Then on the outer card `<div>` (the one with `className="relative overflow-hidden rounded-2xl p-5 mb-4"`, line 46), make it interactive when `onClick` is set by adding these props to that div:

```tsx
      onClick={onClick}
      role={onClick ? 'button' : undefined}
      tabIndex={onClick ? 0 : undefined}
      onKeyDown={onClick ? (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClick(); } } : undefined}
```

and append `${onClick ? ' cursor-pointer transition-transform hover:scale-[1.01]' : ''}` to that div's className string.

- [ ] **Step 2: Add drill-down sheet state + import in Index**

In `src/pages/Index.tsx`, ensure `useState` is imported (it is used elsewhere; if not, add it to the React import). Add the import for the sheet near the other dashboard component imports (after line 5):

```ts
import { DrilldownSheet } from "@/components/dashboard/DrilldownSheet";
```

Add the helper import near the top imports as well:

```ts
import { isEarned } from "@/lib/revenue/recognition";
```

Inside the `Index` component, alongside the other `useState` declarations near the top of the component body, add:

```ts
  const [revenueDrilldownOpen, setRevenueDrilldownOpen] = useState(false);
```

- [ ] **Step 3: Destructure owed values from the hook**

The current destructure (line 164):

```ts
  const { jobs, paidJobs, totalRevenue, calls, marketingSpend, isLoading, error: dataError, refetch } = useDashboardData(dateRange);
```

Replace with:

```ts
  const { jobs, paidJobs, totalRevenue, owedRevenue, owedJobs, calls, marketingSpend, isLoading, error: dataError, refetch } = useDashboardData(dateRange);
```

- [ ] **Step 4: Keep the "paid jobs" count + sparkline consistent with earned revenue**

The count memo (line 281) currently:

```ts
    completedRevenueJobsCount: jobs.filter(j => j.completed_at && j.status === 'completed' && (j.revenue_amount || 0) > 0).length,
```

Replace with (exclude owed jobs so the count matches the earned headline):

```ts
    completedRevenueJobsCount: jobs.filter(j => j.completed_at && j.status === 'completed' && (j.revenue_amount || 0) > 0 && isEarned(j)).length,
```

In the sparkline loop, after the existing `if (rev <= 0) continue;` line (line 292), add:

```ts
      if (rev <= 0) continue;
      if (!isEarned(j)) continue;
```

- [ ] **Step 5: Wire the onClick and render the sheet + owed caption**

In the Total Sales block (lines 580-587), add `onClick` to the `HeroScoreboard`:

```tsx
              <HeroScoreboard
                label={`Total Sales · ${dateRangeLabel}`}
                valueFormatted={fmtCurrency(totalRevenue)}
                subValue={`${completedRevenueJobsCount} paid jobs`}
                contextLine={heroContext}
                delta={heroDelta}
                sparklineValues={heroSparkline}
                onClick={() => setRevenueDrilldownOpen(true)}
              />
```

Immediately after the `</HeroScoreboard>`'s wrapping logic, before the `{showPeriodCompare && revDelta !== null ? (` block (line 588), add the owed caption:

```tsx
              {owedRevenue > 0 && (
                <div className="text-[11px] mt-1.5 text-amber-600 font-medium">
                  + {fmtCurrency(owedRevenue)} sold, awaiting payment ({owedJobs.length} {owedJobs.length === 1 ? 'job' : 'jobs'}) — not counted in revenue
                </div>
              )}
```

Then render the sheet once near the end of the page's JSX (just before the final closing tag of the page container — anywhere inside the returned tree is fine; place it right after the KPI grid's closing `</div>` at a top level). Add:

```tsx
      <DrilldownSheet
        open={revenueDrilldownOpen}
        onOpenChange={setRevenueDrilldownOpen}
        kpi="revenue"
        dateRange={dateRange}
      />
```

- [ ] **Step 6: Typecheck + run the dashboard component/page tests**

Run: `npx tsc --noEmit && npx vitest run`
Expected: no TS errors; full suite passes (fix any revenue-value assertions that legitimately changed to earned numbers).

- [ ] **Step 7: Commit**

```bash
git add src/components/dashboard/HeroScoreboard.tsx src/pages/Index.tsx
git commit -m "feat(revenue): clickable Total Sales card opens breakdown + awaiting-payment caption"
```

---

### Task 7: Verify in the running app

**Files:** none (manual verification via preview tools)

- [ ] **Step 1: Start the dev server**

Use the preview tooling to start the Vite dev server for `twins-dash` and load the dashboard (authenticated as an admin/owner so the main dashboard renders, not the tech view).

- [ ] **Step 2: Set the range to the affected week and check the headline**

Set the date range to 2026-05-22 → 2026-05-28. Confirm Total Sales reads ≈ **$12,884** (not $28,747), and that an amber caption reads "+ $15,863 sold, awaiting payment (2 jobs) — not counted in revenue".

- [ ] **Step 3: Open the breakdown**

Click the Total Sales card. Confirm the slide-over panel opens, lists the jobs for that range, shows an "Earned: $12,884" subtotal plus "Awaiting payment: $15,863 (2 not counted)", and that the John Sturges row shows a "balance due $15,814" amber badge.

- [ ] **Step 4: Capture proof**

Take a screenshot of the dashboard with the caption visible and one of the open panel. Check the browser console for errors (expect none).

- [ ] **Step 5: Final full check**

Run: `npx tsc --noEmit && npx vitest run`
Expected: clean. Then report the before/after numbers to Daniel.

---

## Self-review notes (for the executor)

- **Out of scope — do not touch:** tech scorecards, payroll/commission math, rev-rise dashboard, and `use-comparison-data.ts` (its "revenue" is opportunity-pipeline revenue, a different metric — leaving it alone is intentional).
- **Expected side effect:** the YTD revenue hero (via `useYtdRevenue` → `useDashboardData`) and the prior-period number also drop by any currently-unpaid amount. This is correct and keeps every card on one definition.
- **Known minor:** the drill-down hook keys its date filter on `dateRange.from.toISOString()` (UTC) while the dashboard builds date-only strings; boundary jobs at the very edge of a day could differ slightly between headline and panel. Acceptable for v1; do not fix here.
- **Staleness caveat:** `outstanding_balance` can lag a payment until the next `sync-hcp-jobs`. We trust the synced value; not a blocker.
