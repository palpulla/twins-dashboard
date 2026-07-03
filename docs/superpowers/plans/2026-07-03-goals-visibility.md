# Goals Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make company and personal revenue goals impossible to miss on the two pages the team already lands on (`/tech` Home and the company dashboard), per spec `docs/superpowers/specs/2026-07-03-goals-visibility-design.md`.

**Architecture:** Display-layer only. One new SECURITY DEFINER RPC (`get_company_goal_progress`) lets any authenticated user (incl. technicians, whose RLS blocks the `jobs` table) read exactly three aggregate numbers. A new shared animated `PaceBar` replaces the two heroes' inline bars; a new `CompanyGoalRibbon` renders under the tech `YearRibbon`; both heroes gain a this-month line; the Index hero is un-gated from its localStorage toggle. Zero KPI math changes — all revenue values come from existing hooks or an RPC that mirrors the existing client revenue predicate exactly.

**Tech Stack:** Vite + React 18 + TS (strict for new/changed files — CI ratchet), TanStack Query, Supabase (prod ref `jwrpjuqaynownxaoeayi`), date-fns, vitest + @testing-library/react.

**Repo:** `/Users/daniel/twins-dashboard/twins-dash` (palpulla/twins-dash). Work in an isolated worktree — concurrent sessions clobber the main checkout.

**What already exists (do NOT rebuild):**
- `YearRibbon` (tech Home) already renders the personal annual goal block: YTD vs goal, pace pill, elapsed marker, "Should be today / Vs pace / Need per week" grid.
- `AnnualRevenueHero` (Index) already renders YTD vs goal, pace pill, elapsed marker, "on pace for $X by Dec 31".
- `src/lib/pacing.ts` — `pacingStatus`, `PILL_BG`, `PILL_LABEL`. Reuse; do not duplicate.
- Deltas this plan ships: month lines, company ribbon + RPC + hook, always-on Index hero, animated bar with milestone ticks, tests.

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `src/lib/goal-pace.ts` | Create | Pure month/weekly-need math shared by all three surfaces |
| `src/lib/__tests__/goal-pace.test.ts` | Create | Unit tests for the above |
| `src/components/dashboard/PaceBar.tsx` | Create | Animated progress bar + elapsed marker + 25/50/75 ticks, onDark/onLight variants |
| `supabase/migrations/20260703090000_company_goal_progress_rpc.sql` | Create | `get_company_goal_progress()` RPC |
| `src/hooks/use-company-goal-progress.ts` | Create | React Query hook for the RPC (`enabled: !!session`) |
| `src/lib/demo/demoTechData.ts` | Modify | Add `demoCompanyGoalProgress` |
| `src/components/tech/CompanyGoalRibbon.tsx` | Create | Slim company progress card for tech Home |
| `src/components/tech/__tests__/CompanyGoalRibbon.test.tsx` | Create | Render tests (goal present / absent) |
| `src/components/tech/YearRibbon.tsx` | Modify | Month line + PaceBar swap in goal block |
| `src/pages/tech/Home.tsx` | Modify | MTD scorecard query, `mtdRevenue` prop, mount `CompanyGoalRibbon` |
| `src/components/dashboard/AnnualRevenueHero.tsx` | Modify | Month line + PaceBar swap |
| `src/components/dashboard/__tests__/AnnualRevenueHero.test.tsx` | Modify | Cover month line; keep existing 4 tests green |
| `src/pages/Index.tsx` | Modify | Un-gate hero (remove `showGoalCompare`), feed `mtdRevenue` |

---

### Task 1: Worktree setup

**Files:** none (git only)

- [ ] **Step 1: Create isolated worktree + branch**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git fetch origin main && git worktree add .worktrees/goals-visibility -b feat/goals-visibility origin/main
cd .worktrees/goals-visibility && npm ci
```

Expected: worktree at `.worktrees/goals-visibility`, branch `feat/goals-visibility`, deps installed. All later tasks run inside this worktree directory.

---

### Task 2: `goal-pace` pure math module (TDD)

**Files:**
- Create: `src/lib/goal-pace.ts`
- Test: `src/lib/__tests__/goal-pace.test.ts`

- [ ] **Step 1: Write the failing tests**

```ts
// src/lib/__tests__/goal-pace.test.ts
import { describe, it, expect } from 'vitest';
import { monthlyTargetFromAnnual, neededPerWeekToGoal } from '../goal-pace';

describe('monthlyTargetFromAnnual', () => {
  it('divides the annual goal by 12', () => {
    expect(monthlyTargetFromAnnual(2400000)).toBe(200000);
  });
  it('returns 0 for null/zero/negative goals', () => {
    expect(monthlyTargetFromAnnual(null)).toBe(0);
    expect(monthlyTargetFromAnnual(0)).toBe(0);
    expect(monthlyTargetFromAnnual(-5)).toBe(0);
  });
});

describe('neededPerWeekToGoal', () => {
  it('spreads the remaining gap over remaining weeks', () => {
    // goal 1,000,000, ytd 400,000, mid-year day 183 of 365 → 182 days left = 26 weeks
    // gap 600,000 / 182 days * 7 ≈ 23,077
    const v = neededPerWeekToGoal(1000000, 400000, 183, 365);
    expect(Math.round(v)).toBe(23077);
  });
  it('returns 0 when goal already met', () => {
    expect(neededPerWeekToGoal(1000000, 1200000, 183, 365)).toBe(0);
  });
  it('does not divide by zero on Dec 31 (day 365 of 365)', () => {
    const v = neededPerWeekToGoal(1000000, 400000, 365, 365);
    expect(Number.isFinite(v)).toBe(true);
    expect(v).toBeGreaterThan(0); // remaining gap over a floor of 1 day
  });
  it('handles Jan 1 (day 1) and leap years (366 days)', () => {
    expect(Number.isFinite(neededPerWeekToGoal(1200000, 0, 1, 366))).toBe(true);
  });
  it('returns 0 when goal is null or <= 0', () => {
    expect(neededPerWeekToGoal(null, 400000, 183, 365)).toBe(0);
    expect(neededPerWeekToGoal(0, 400000, 183, 365)).toBe(0);
  });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx vitest run src/lib/__tests__/goal-pace.test.ts`
Expected: FAIL — module `../goal-pace` not found.

- [ ] **Step 3: Write the implementation**

```ts
// src/lib/goal-pace.ts
// Pure display-layer goal math shared by AnnualRevenueHero (Index),
// YearRibbon (tech Home) and CompanyGoalRibbon. NOT part of KPI
// calculations — nothing here feeds payroll or scorecard numbers.

/** This month's slice of an annual goal. Simple 1/12; seasonal weighting deliberately deferred (see spec). */
export function monthlyTargetFromAnnual(annualGoal: number | null): number {
  if (annualGoal == null || annualGoal <= 0) return 0;
  return annualGoal / 12;
}

/**
 * Revenue per week needed for the rest of the year to land exactly on goal.
 * (goal − ytd) / daysRemaining × 7, with daysRemaining floored at 1 so the
 * last day of the year never divides by zero. Returns 0 once the goal is met.
 * Matches the inline math already shown in YearRibbon's "Need / week" cell.
 */
export function neededPerWeekToGoal(
  annualGoal: number | null,
  ytdRevenue: number,
  dayOfYear: number,
  daysInYear: number,
): number {
  if (annualGoal == null || annualGoal <= 0 || daysInYear <= 0) return 0;
  const remainingToGoal = Math.max(0, annualGoal - ytdRevenue);
  if (remainingToGoal === 0) return 0;
  const remainingDays = Math.max(1, daysInYear - dayOfYear);
  return (remainingToGoal / remainingDays) * 7;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npx vitest run src/lib/__tests__/goal-pace.test.ts`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add src/lib/goal-pace.ts src/lib/__tests__/goal-pace.test.ts
git commit -m "feat(goals): goal-pace helpers — monthly target + needed-per-week"
```

---

### Task 3: `PaceBar` shared animated bar

**Files:**
- Create: `src/components/dashboard/PaceBar.tsx`

No dedicated test file — it's covered through the AnnualRevenueHero/CompanyGoalRibbon render tests (they assert on content, and the bar is presentational). Keep it dependency-free and pure.

- [ ] **Step 1: Write the component**

```tsx
// src/components/dashboard/PaceBar.tsx
import { useEffect, useState } from 'react';

type Props = {
  /** Percent of goal reached, 0–100+ (display clamps to 100). */
  pctToGoal: number;
  /** Percent of period elapsed, 0–100. Renders the "where you should be" marker. Omit to hide. */
  pctElapsed?: number;
  /** Tailwind height class for the track. */
  heightClass?: string;
  /** Milestone ticks at 25/50/75%. */
  showTicks?: boolean;
  /** onDark = navy hero cards (white track); onLight = standard cards. */
  variant?: 'onDark' | 'onLight';
};

const TRACK = {
  onDark: 'rgba(255,255,255,0.12)',
  onLight: 'rgba(15,29,77,0.08)',
};
const TICK = {
  onDark: 'rgba(255,255,255,0.25)',
  onLight: 'rgba(15,29,77,0.15)',
};
const MARKER = {
  onDark: '#ffffff',
  onLight: 'rgba(15,29,77,0.45)',
};
const FILL_PROGRESS = 'linear-gradient(to right, #f7b801, #fcd34d)';
const FILL_DONE = 'linear-gradient(to right, #059669, #34d399)';

/**
 * Shared goal-progress bar: gold gradient fill (emerald once >=100%),
 * 25/50/75 milestone ticks, elapsed-time marker, and a one-shot mount
 * animation (width transitions from 0 to value; later data refetches
 * transition from the previous width, not from 0).
 */
export function PaceBar({ pctToGoal, pctElapsed, heightClass = 'h-2.5', showTicks = true, variant = 'onDark' }: Props) {
  const target = Math.min(100, Math.max(0, pctToGoal));
  const [width, setWidth] = useState(0);
  useEffect(() => {
    // Deferred one frame so the browser paints width:0 first, making the
    // CSS transition animate on mount.
    const id = requestAnimationFrame(() => setWidth(target));
    return () => cancelAnimationFrame(id);
  }, [target]);
  const done = pctToGoal >= 100;

  return (
    <div className={`relative ${heightClass} rounded-full overflow-visible`} style={{ background: TRACK[variant] }}>
      {showTicks && [25, 50, 75].map((t) => (
        <div key={t} className="absolute top-0 h-full w-px" style={{ left: `${t}%`, background: TICK[variant] }} />
      ))}
      <div
        className="absolute top-0 left-0 h-full rounded-full"
        style={{
          width: `${width}%`,
          background: done ? FILL_DONE : FILL_PROGRESS,
          transition: 'width 700ms cubic-bezier(0.22, 1, 0.36, 1)',
        }}
      />
      {pctElapsed != null && (
        <div
          className="absolute -top-1 w-0.5 h-4"
          style={{ left: `${Math.min(100, Math.max(0, pctElapsed))}%`, background: MARKER[variant] }}
        />
      )}
    </div>
  );
}
```

- [ ] **Step 2: Type-check**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: no new errors (pre-existing project errors unrelated to this file are tolerated by the ratchet; this file itself must be clean).

- [ ] **Step 3: Commit**

```bash
git add src/components/dashboard/PaceBar.tsx
git commit -m "feat(goals): shared PaceBar — animated fill, milestone ticks, elapsed marker"
```

---

### Task 4: `get_company_goal_progress()` RPC (migration + prod apply)

**Files:**
- Create: `supabase/migrations/20260703090000_company_goal_progress_rpc.sql`

Techs cannot SELECT the `jobs` table (RLS). This SECURITY DEFINER function returns exactly three aggregates and nothing else. The revenue predicate MUST mirror `src/hooks/use-dashboard-data.ts`'s revenue query byte-for-byte in meaning: `completed_at IS NOT NULL AND revenue_amount > 0`, summing `revenue_amount` (gross; paid or not — matches the dashboard hero). Year/month boundaries computed in America/Chicago (business local time, consistent with payroll's local-midnight convention).

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260703090000_company_goal_progress_rpc.sql
-- Company goal progress readable by ALL authenticated roles (incl. technician,
-- whose RLS blocks public.jobs). Returns exactly three aggregate numbers used
-- by CompanyGoalRibbon (/tech) and the Index hero month line.
--
-- Revenue definition mirrors use-dashboard-data.ts's revenue query
-- (completed_at IS NOT NULL AND revenue_amount > 0, SUM(revenue_amount)) so
-- the ribbon can never disagree with the dashboard's Total Sales YTD.
-- Boundaries are America/Chicago local (Madison WI), matching how the
-- client's startOfYear(today) behaves for dashboard users.

CREATE OR REPLACE FUNCTION public.get_company_goal_progress()
RETURNS TABLE (ytd_revenue numeric, mtd_revenue numeric, annual_goal numeric)
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  WITH bounds AS (
    SELECT
      (date_trunc('year',  now() AT TIME ZONE 'America/Chicago') AT TIME ZONE 'America/Chicago') AS year_start,
      (date_trunc('month', now() AT TIME ZONE 'America/Chicago') AT TIME ZONE 'America/Chicago') AS month_start
  )
  SELECT
    COALESCE(SUM(j.revenue_amount), 0)::numeric AS ytd_revenue,
    COALESCE(SUM(j.revenue_amount) FILTER (WHERE j.completed_at >= b.month_start), 0)::numeric AS mtd_revenue,
    (SELECT cg.target_value FROM public.company_goals cg WHERE cg.metric_key = 'revenue_annual')::numeric AS annual_goal
  FROM bounds b
  LEFT JOIN public.jobs j
    ON j.completed_at IS NOT NULL
   AND j.revenue_amount > 0
   AND j.completed_at >= b.year_start
   AND j.completed_at <= now()
  GROUP BY b.year_start, b.month_start;
$$;

REVOKE ALL ON FUNCTION public.get_company_goal_progress() FROM PUBLIC;
REVOKE ALL ON FUNCTION public.get_company_goal_progress() FROM anon;
GRANT EXECUTE ON FUNCTION public.get_company_goal_progress() TO authenticated;
GRANT EXECUTE ON FUNCTION public.get_company_goal_progress() TO service_role;
```

- [ ] **Step 2: Apply to prod via Supabase MCP**

Use MCP tool `apply_migration` on project `jwrpjuqaynownxaoeayi` with name `company_goal_progress_rpc` and the SQL above.

- [ ] **Step 3: Verify migration recorded + function works**

Via MCP `execute_sql` on the same project:

```sql
SELECT version FROM supabase_migrations.schema_migrations ORDER BY version DESC LIMIT 3;
```
Expected: newest row is the applied migration (known repo quirk: history desyncs when applied outside `db push` — if the version row is missing, INSERT it manually to keep `supabase db` tooling consistent).

```sql
SELECT * FROM public.get_company_goal_progress();
```
Expected: one row, three numeric columns; `ytd_revenue` should visually match the dashboard hero's YTD number; `annual_goal` matches Admin → Goals annual revenue target.

- [ ] **Step 4: Verify anon is blocked**

```bash
curl -s -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/rest/v1/rpc/get_company_goal_progress" \
  -H "apikey: $(grep VITE_SUPABASE_ANON_KEY .env* -rh | head -1 | cut -d= -f2)" \
  -H "Content-Type: application/json" -d '{}'
```
Expected: `"permission denied for function get_company_goal_progress"` (or 401/403) — NOT a data row.

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260703090000_company_goal_progress_rpc.sql
git commit -m "feat(goals): get_company_goal_progress RPC — 3 aggregates for all authed roles"
```

---

### Task 5: Hook + demo data

**Files:**
- Create: `src/hooks/use-company-goal-progress.ts`
- Modify: `src/lib/demo/demoTechData.ts` (append export)

- [ ] **Step 1: Add demo constant**

Append to `src/lib/demo/demoTechData.ts`:

```ts
// Company-wide goal progress shown in CompanyGoalRibbon / Index hero month
// line when demo mode is on. Deliberately "on pace"-ish mid-year numbers.
export const demoCompanyGoalProgress = {
  ytd_revenue: 1264000,
  mtd_revenue: 31200,
  annual_goal: 2400000,
};
```

- [ ] **Step 2: Write the hook**

```ts
// src/hooks/use-company-goal-progress.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/contexts/AuthContext';
import { useDemoQuery } from '@/contexts/DemoModeContext';
import { demoCompanyGoalProgress } from '@/lib/demo/demoTechData';

export interface CompanyGoalProgress {
  ytd_revenue: number;
  mtd_revenue: number;
  annual_goal: number | null;
}

type RpcRow = { ytd_revenue: number | string | null; mtd_revenue: number | string | null; annual_goal: number | string | null };

/**
 * Company YTD/MTD revenue + annual goal via get_company_goal_progress().
 * Works for every authenticated role (techs can't read jobs directly).
 * Gated on session per the standing auth-race rule; the RPC name is cast
 * because supabase types.ts isn't regenerated for this function.
 */
export function useCompanyGoalProgress() {
  const { session } = useAuth();
  const real = useQuery({
    queryKey: ['company-goal-progress'],
    enabled: !!session,
    staleTime: 60_000,
    refetchInterval: 300_000,
    queryFn: async (): Promise<CompanyGoalProgress | null> => {
      const { data, error } = await supabase.rpc('get_company_goal_progress' as never);
      if (error) throw error;
      const rows = data as unknown as RpcRow[] | RpcRow | null;
      const row = Array.isArray(rows) ? rows[0] : rows;
      if (!row) return null;
      return {
        ytd_revenue: Number(row.ytd_revenue ?? 0),
        mtd_revenue: Number(row.mtd_revenue ?? 0),
        annual_goal: row.annual_goal == null ? null : Number(row.annual_goal),
      };
    },
  });
  return useDemoQuery(real, demoCompanyGoalProgress as CompanyGoalProgress);
}
```

- [ ] **Step 3: Type-check**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: the new file is strict-clean.

- [ ] **Step 4: Commit**

```bash
git add src/hooks/use-company-goal-progress.ts src/lib/demo/demoTechData.ts
git commit -m "feat(goals): useCompanyGoalProgress hook + demo data"
```

---

### Task 6: `CompanyGoalRibbon` (tech Home) — TDD

**Files:**
- Create: `src/components/tech/CompanyGoalRibbon.tsx`
- Test: `src/components/tech/__tests__/CompanyGoalRibbon.test.tsx`

- [ ] **Step 1: Write the failing tests**

```tsx
// src/components/tech/__tests__/CompanyGoalRibbon.test.tsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import React from 'react';

const mockUseCompanyGoalProgress = vi.fn();
vi.mock('@/hooks/use-company-goal-progress', () => ({
  useCompanyGoalProgress: () => mockUseCompanyGoalProgress(),
}));

import { CompanyGoalRibbon } from '../CompanyGoalRibbon';

describe('CompanyGoalRibbon', () => {
  it('renders company YTD vs goal, month line, and a pace pill', () => {
    mockUseCompanyGoalProgress.mockReturnValue({
      data: { ytd_revenue: 1264000, mtd_revenue: 31200, annual_goal: 2400000 },
    });
    // day 183/365 ≈ 50.1% elapsed; 52.7% of goal → on pace
    render(<CompanyGoalRibbon dayOfYear={183} daysInYear={365} />);
    expect(screen.getByText(/\$1,264,000/)).toBeInTheDocument();
    expect(screen.getByText(/\$2,400,000/)).toBeInTheDocument();
    // month line: $31,200 of $200,000 (annual/12)
    expect(screen.getByText(/\$31,200/)).toBeInTheDocument();
    expect(screen.getByText(/\$200,000/)).toBeInTheDocument();
    expect(screen.getByText(/On pace/i)).toBeInTheDocument();
  });

  it('shows needed-per-week when behind pace', () => {
    mockUseCompanyGoalProgress.mockReturnValue({
      data: { ytd_revenue: 600000, mtd_revenue: 10000, annual_goal: 2400000 },
    });
    // 25% of goal vs ~50% elapsed → behind; needs (1.8M/182)*7 ≈ $69,231/wk
    render(<CompanyGoalRibbon dayOfYear={183} daysInYear={365} />);
    expect(screen.getByText(/Behind/i)).toBeInTheDocument();
    expect(screen.getByText(/\/wk/)).toBeInTheDocument();
  });

  it('renders nothing when there is no annual goal', () => {
    mockUseCompanyGoalProgress.mockReturnValue({
      data: { ytd_revenue: 1264000, mtd_revenue: 31200, annual_goal: null },
    });
    const { container } = render(<CompanyGoalRibbon dayOfYear={183} daysInYear={365} />);
    expect(container.firstChild).toBeNull();
  });

  it('renders nothing while data is unavailable (loading/error)', () => {
    mockUseCompanyGoalProgress.mockReturnValue({ data: undefined });
    const { container } = render(<CompanyGoalRibbon dayOfYear={183} daysInYear={365} />);
    expect(container.firstChild).toBeNull();
  });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx vitest run src/components/tech/__tests__/CompanyGoalRibbon.test.tsx`
Expected: FAIL — component module not found.

- [ ] **Step 3: Write the component**

```tsx
// src/components/tech/CompanyGoalRibbon.tsx
import { format } from 'date-fns';
import { pacingStatus, PILL_BG, PILL_LABEL } from '@/lib/pacing';
import { PaceBar } from '@/components/dashboard/PaceBar';
import { useCompanyGoalProgress } from '@/hooks/use-company-goal-progress';
import { monthlyTargetFromAnnual, neededPerWeekToGoal } from '@/lib/goal-pace';

const fmtUsd = (n: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(Math.max(0, n || 0));

type Props = {
  dayOfYear: number;
  daysInYear: number;
};

/**
 * Slim "how's the company doing" ribbon under the tech's personal YearRibbon.
 * Deliberately quieter than the hero above it: one line of text, a thin bar,
 * a pace pill, and the this-month line. Hides itself entirely when the RPC
 * fails or no annual goal is configured (no error card, no layout jump).
 */
export function CompanyGoalRibbon({ dayOfYear, daysInYear }: Props) {
  const { data } = useCompanyGoalProgress();
  if (!data || data.annual_goal == null || data.annual_goal <= 0 || daysInYear <= 0) return null;

  const goal = data.annual_goal;
  const year = new Date().getFullYear();
  const elapsed = dayOfYear / daysInYear;
  const pace = pacingStatus(data.ytd_revenue, goal, elapsed, 'higher');
  const pct = (data.ytd_revenue / goal) * 100;
  const monthTarget = monthlyTargetFromAnnual(goal);
  const needWk = neededPerWeekToGoal(goal, data.ytd_revenue, dayOfYear, daysInYear);
  const isTrailing = pace.status === 'behind' || pace.status === 'close';

  return (
    <div className="rounded-2xl border bg-card text-card-foreground p-3.5">
      <div className="flex items-center justify-between gap-2 flex-wrap">
        <div className="min-w-0 text-sm truncate">
          <span className="text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground">
            🏢 Company {year} goal
          </span>{' '}
          <b className="tabular-nums">{fmtUsd(data.ytd_revenue)}</b>
          <span className="text-muted-foreground"> of {fmtUsd(goal)} · {pct.toFixed(0)}%</span>
        </div>
        <span
          className="text-[11px] font-extrabold px-2.5 py-0.5 rounded-full whitespace-nowrap"
          style={{ background: PILL_BG[pace.status], color: '#fff' }}
        >
          {PILL_LABEL[pace.status]}
        </span>
      </div>
      <div className="mt-2">
        <PaceBar pctToGoal={pct} pctElapsed={elapsed * 100} heightClass="h-1.5" variant="onLight" />
      </div>
      <div className="flex justify-between gap-2 mt-1.5 text-[11px] text-muted-foreground">
        <span className="truncate tabular-nums">
          {format(new Date(), 'MMMM')}: {fmtUsd(data.mtd_revenue)} of {fmtUsd(monthTarget)}
        </span>
        {isTrailing && needWk > 0 && (
          <span className="whitespace-nowrap tabular-nums">need {fmtUsd(needWk)}/wk to hit goal</span>
        )}
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `npx vitest run src/components/tech/__tests__/CompanyGoalRibbon.test.tsx`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/components/tech/CompanyGoalRibbon.tsx src/components/tech/__tests__/CompanyGoalRibbon.test.tsx
git commit -m "feat(goals): CompanyGoalRibbon — company progress on tech Home"
```

---

### Task 7: YearRibbon month line + mount on tech Home

**Files:**
- Modify: `src/components/tech/YearRibbon.tsx`
- Modify: `src/pages/tech/Home.tsx`

- [ ] **Step 1: Add `mtdRevenue` prop + month line + PaceBar to YearRibbon**

In `src/components/tech/YearRibbon.tsx`:

(a) Add to the `Props` type (after `daysInYear?: number;`):

```ts
  /** Month-to-date revenue for the "July: $X of $Y" line. Omit to hide the line. */
  mtdRevenue?: number | null;
```

(b) Add imports: extend the existing date-fns import to include nothing new (`format` already imported), and add:

```ts
import { PaceBar } from '@/components/dashboard/PaceBar';
import { monthlyTargetFromAnnual } from '@/lib/goal-pace';
```

(c) Replace the inline progress bar block (the `div.relative.h-2.5` with its two children — fill div and elapsed marker div, currently lines ~143-149):

```tsx
            <div className="mt-3">
              <PaceBar pctToGoal={ytdPct} pctElapsed={elapsedPct} variant="onDark" />
            </div>
```

(d) Directly after the `flex justify-between mt-1.5` row (the "% to goal · % elapsed / on pace for" line, currently ends ~line 153), add the month line:

```tsx
            {p.mtdRevenue != null && (
              <div className="text-[11px] mt-1 tabular-nums" style={{ color: 'rgba(255,255,255,0.85)' }}>
                {format(new Date(), 'MMMM')}: <b>{fmtUsd(p.mtdRevenue)}</b>
                <span style={{ color: 'rgba(255,255,255,0.6)' }}> of {fmtUsd(monthlyTargetFromAnnual(goalDollars))}</span>
              </div>
            )}
```

- [ ] **Step 2: Wire MTD data + CompanyGoalRibbon in Home.tsx**

In `src/pages/tech/Home.tsx`:

(a) Extend the date-fns import (`startOfYear, endOfYear, isAfter, subDays` line) with `startOfMonth`.

(b) Add import: `import { CompanyGoalRibbon } from '@/components/tech/CompanyGoalRibbon';`

(c) After the `ytdScorecard` block (`const ytdAvgTicket = ...`, ~line 192), add:

```ts
  // Month-to-date revenue for the YearRibbon's "July: $X of $Y" line.
  // Own memoized range (same pattern as ytdRange) so the query key is stable.
  const mtdRange = useMemo<DateRange>(() => {
    const today = new Date();
    return { from: startOfMonth(today), to: today };
  }, []);
  const { scorecard: mtdScorecard } = useMyScorecardWithTiers(effectiveUuid, mtdRange);
  const mtdRevenue = Number(mtdScorecard?.revenue?.value ?? 0);
```

(d) Pass the prop on the `<YearRibbon ... />` call (after `daysInYear={daysInYear}`):

```tsx
        mtdRevenue={mtdRevenue}
```

(e) Directly after the closing `/>` of `<YearRibbon ...>`, mount the ribbon:

```tsx
      <CompanyGoalRibbon dayOfYear={dayOfYear} daysInYear={daysInYear} />
```

- [ ] **Step 3: Run the tech test suites + type-check**

Run: `npx vitest run src/components/tech src/pages 2>/dev/null || npx vitest run src/components/tech`
Expected: PASS (existing YearRibbon-consuming tests stay green).
Run: `npx tsc --noEmit -p tsconfig.json` — changed files strict-clean.

- [ ] **Step 4: Commit**

```bash
git add src/components/tech/YearRibbon.tsx src/pages/tech/Home.tsx
git commit -m "feat(goals): month line on YearRibbon + company ribbon on tech Home"
```

---

### Task 8: Index hero — always on, month line, PaceBar

**Files:**
- Modify: `src/components/dashboard/AnnualRevenueHero.tsx`
- Modify: `src/pages/Index.tsx`
- Modify: `src/components/dashboard/__tests__/AnnualRevenueHero.test.tsx`

- [ ] **Step 1: Extend AnnualRevenueHero**

In `src/components/dashboard/AnnualRevenueHero.tsx`:

(a) Add imports:

```ts
import { format } from 'date-fns';
import { PaceBar } from './PaceBar';
import { monthlyTargetFromAnnual } from '@/lib/goal-pace';
```

(b) Add to `Props` (after `daysInYear: number;`):

```ts
  /** Company month-to-date revenue for the "July: $X of $Y" line. Omit/null hides the line. */
  mtdRevenue?: number | null;
```

(c) Destructure it in the signature: `..., daysInYear, mtdRevenue, buildSnapshot }`.

(d) Replace the inline bar block (the `div.relative.h-2.5 ... mt-3` with fill + marker children, currently lines ~87-90):

```tsx
        <div className="mt-3">
          <PaceBar pctToGoal={ytdPct} pctElapsed={elapsedPct} variant="onDark" />
        </div>
```

(e) In the bottom `flex justify-between mt-1.5` row, add the month line as a right-hand span (after the existing `% to goal · % elapsed` span):

```tsx
          {mtdRevenue != null && (
            <span className="tabular-nums">
              {format(new Date(), 'MMMM')}: {fmtUsd(mtdRevenue)} of {fmtUsd(monthlyTargetFromAnnual(goalAnnual))}
            </span>
          )}
```

- [ ] **Step 2: Un-gate the hero in Index.tsx + feed month data**

In `src/pages/Index.tsx`:

(a) Add import: `import { useCompanyGoalProgress } from '@/hooks/use-company-goal-progress';`

(b) Near the other hooks (~line 226, after `useYtdRevenue()`), add:

```ts
  // Company MTD revenue for the hero's month line. Same RPC the tech portal
  // ribbon uses; YTD continues to come from useYtdRevenue so the hero always
  // matches the Total Sales card below it.
  const companyProgress = useCompanyGoalProgress();
```

(c) Delete the `showGoalCompare` state line (~148):

```ts
  const [showGoalCompare, setShowGoalCompare] = useLocalStorageBoolean('twins.dashboard.showGoalCompare', true);
```

(d) Find the Switch that renders `aria-label="Show goal comparison"` (~line 543) and remove that Switch and its label wrapper (keep the adjacent `showPeriodCompare` Switch intact — verify by reading the surrounding JSX before deleting).

(e) Replace the gated hero render:

```tsx
          {showGoalCompare && (
            <AnnualRevenueHero
              ytdRevenue={ytdRevenueNum}
              goalAnnual={annualGoal}
              dayOfYear={dayOfYear}
              daysInYear={daysInYear}
              buildSnapshot={buildSnapshot}
            />
          )}
```

with the always-on version:

```tsx
          <AnnualRevenueHero
            ytdRevenue={ytdRevenueNum}
            goalAnnual={annualGoal}
            dayOfYear={dayOfYear}
            daysInYear={daysInYear}
            mtdRevenue={companyProgress.data?.mtd_revenue ?? null}
            buildSnapshot={buildSnapshot}
          />
```

(f) Search Index.tsx for any other `showGoalCompare` references (`grep -n showGoalCompare src/pages/Index.tsx`) and remove leftovers. If `useLocalStorageBoolean` is now unused for this key only, leave the other usages alone.

- [ ] **Step 3: Extend the hero tests**

In `src/components/dashboard/__tests__/AnnualRevenueHero.test.tsx`, add:

```tsx
  it('renders the month line when mtdRevenue is provided', () => {
    render(
      <AnnualRevenueHero ytdRevenue={333357} goalAnnual={1200000} dayOfYear={122} daysInYear={365} mtdRevenue={31200} />,
      { wrapper: wrapper() },
    );
    // month target = 1,200,000 / 12 = 100,000
    expect(screen.getByText(/\$31,200 of \$100,000/)).toBeInTheDocument();
  });

  it('hides the month line when mtdRevenue is null', () => {
    render(
      <AnnualRevenueHero ytdRevenue={333357} goalAnnual={1200000} dayOfYear={122} daysInYear={365} mtdRevenue={null} />,
      { wrapper: wrapper() },
    );
    expect(screen.queryByText(/of \$100,000/)).not.toBeInTheDocument();
  });
```

- [ ] **Step 4: Run tests + type-check + build**

Run: `npx vitest run src/components/dashboard/__tests__/AnnualRevenueHero.test.tsx`
Expected: PASS (6 tests — 4 existing + 2 new).
Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add src/components/dashboard/AnnualRevenueHero.tsx src/pages/Index.tsx src/components/dashboard/__tests__/AnnualRevenueHero.test.tsx
git commit -m "feat(goals): always-on annual hero with month line on company dashboard"
```

---

### Task 9: Full verification + visual check

**Files:** none (verification)

- [ ] **Step 1: Full test suite + ratchet**

```bash
npx vitest run
node scripts/strict-ratchet.mjs origin/main
npm run build
```
Expected: all vitest suites pass (~930 tests), ratchet clean on every changed file, build succeeds.

- [ ] **Step 2: Visual verification in preview**

Start the dev server from THIS worktree (verify `fetch('/src/App.tsx')` serves the worktree copy — known two-checkouts gotcha on port 8080). Check with preview tools:
- `/tech` (as admin via `?as=<tech-uuid>`): YearRibbon shows month line; CompanyGoalRibbon renders under it with bar + pill + month line; bars animate on load.
- `/` : hero visible with no toggle; month line present; ticks at 25/50/75 visible.
- Mobile (375px): ribbon wraps without horizontal scroll; pill stays on-screen.
- Screenshot both pages for the PR/user.

- [ ] **Step 3: Commit any polish fixes from the visual pass**

```bash
git add -A src && git commit -m "polish(goals): visual pass fixes" # only if needed
```

---

### Task 10: PR, merge, live verify

**Files:** none (release)

- [ ] **Step 1: Push + open PR via GitHub API** (no gh CLI — extract token from osxkeychain)

```bash
git push -u origin feat/goals-visibility
TOKEN=$(printf 'protocol=https\nhost=github.com\n' | git credential-osxkeychain get | grep password | cut -d= -f2)
curl -s -X POST https://api.github.com/repos/palpulla/twins-dash/pulls \
  -H "Authorization: token $TOKEN" \
  -d '{"title":"feat: goals visibility — month lines, company ribbon on /tech, always-on annual hero","head":"feat/goals-visibility","base":"main","body":"Implements docs/superpowers/specs/2026-07-03-goals-visibility-design.md. Display-layer only; new get_company_goal_progress RPC (3 aggregates, authenticated-only). No KPI math changes.\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"}'
```

- [ ] **Step 2: Wait for CI green (build-and-ratchet + deno-tests), then merge via API**

- [ ] **Step 3: Live verify on twinsdash.com**

- Company dashboard: hero always visible with month line.
- `/tech` as a real tech (or `?as=`): personal month line + company ribbon live.
- Confirm RPC anon rejection again on prod (Task 4 Step 4 curl).

- [ ] **Step 4: Clean up worktree**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git worktree remove .worktrees/goals-visibility
```

---

## Self-Review Notes

- **Spec coverage:** tech hero upgrade (Task 7 — month line; pace chip + need/week already existed in YearRibbon), company ribbon (Tasks 4-6), always-on Index hero + month line (Task 8), UI polish (Task 3 PaceBar: animation, ticks, gradients; Task 9 mobile/visual pass), goal-pace module + tests (Task 2), RPC safety (Task 4: SECURITY DEFINER, pinned search_path, anon revoked, 3 fields only), `enabled: !!session` (Task 5), zero KPI-math changes (all revenue from existing hooks/RPC mirror; full suite must stay green in Task 9).
- **Deviation from spec, intentional:** spec's "PaceChip" component is unnecessary — `pacing.ts` PILL_* already renders the chip on both existing surfaces; building a wrapper would duplicate. The "need $X/wk" copy lives in CompanyGoalRibbon + YearRibbon's existing grid rather than inside the pill.
- **Types:** `PaceBar` props consistent across Tasks 3/6/7/8; `mtdRevenue?: number | null` on both heroes; hook returns `CompanyGoalProgress | null`.
