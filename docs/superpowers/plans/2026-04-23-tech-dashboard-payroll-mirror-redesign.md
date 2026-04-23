# Tech Dashboard Payroll-Mirror Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the tech self-service view's three pages (TechHome, TechJobs, TechJobDetail) and associated components to visually mirror the admin `/payroll/*` tab while preserving the already-shipped data layer, RLS, and Edge Functions.

**Architecture:** One new Postgres function (`my_paystub`) returns a richer per-timeframe breakdown (primary + override + tip + next-draft-job). Two new hooks wrap it. Three new presentational components (`HeroActionCard`, `PaystubCard`, `PayrollWeekSection`) adopt the admin Payroll Home visual primitives (page header, yellow-accent hero card, `MiniStat` grid, Card-wrapped lists). Three pages are rewritten; three superseded components are deleted.

**Tech Stack:** React 18 + TS + Vite + shadcn/ui + TanStack Query + Supabase. Mobile-first Tailwind breakpoints.

**Cloned working dir:** `/Users/daniel/twins-dashboard/twins-dash/` (sibling of this plan's repo).
**Feature branch:** `feature/tech-dashboard-phase-1-infra`.
**Live Supabase project:** `jwrpjuqaynownxaoeayi` (CLI-linked; migrations apply via `npx supabase db push`).
**Reference to mirror:** `src/pages/payroll/Home.tsx` — copy its primitives verbatim.

---

## Cross-cutting rules every task must follow

1. **Use `git -C /Users/daniel/twins-dashboard/twins-dash` for every git command.** A parallel Claude session on branch `feature/payroll-draft-sync` silently switches the shell's CWD branch via bare `git checkout` commands. Absolute-path `git -C` is resistant to this but not immune — every task starts with an explicit checkout + verification.
2. **At the start of every task, run this verification block:**
   ```bash
   git -C /Users/daniel/twins-dashboard/twins-dash checkout feature/tech-dashboard-phase-1-infra 2>&1
   git -C /Users/daniel/twins-dashboard/twins-dash branch --show-current
   # MUST print: feature/tech-dashboard-phase-1-infra
   ```
   If output doesn't match, STOP and report BLOCKED.
3. **Never push to origin during implementation.** The branch has an upstream already (`origin/feature/tech-dashboard-phase-1-infra`). Only the final push task pushes.
4. **Never stage `supabase/migrations/20260423180100_payroll_merge_rpc.sql`** — it's a stray from the parallel session, not part of this plan.
5. **Price columns must never reach tech sessions.** Views already enforce this. Do NOT add a component or hook that reads `unit_price` / `total` / `total_cost` on tech paths.

---

## File Structure

### New files

- `supabase/migrations/<ts>_my_paystub_fn.sql` — the `my_paystub(p_since, p_until)` function
- `src/hooks/tech/useMyPaystub.ts` — hook wrapper for the RPC
- `src/hooks/tech/useLastFinalizedPaystub.ts` — fetches last finalized run + calls `my_paystub` for its range
- `src/lib/tech-dashboard/hero-action-state.ts` — pure helper `computeHeroActionState()` that returns the hero variant
- `src/lib/tech-dashboard/__tests__/hero-action-state.test.ts`
- `src/components/tech/HeroActionCard.tsx` — 3-variant presentational card
- `src/components/tech/PaystubCard.tsx` — labeled breakdown card (this-week / last-week / admin-provided variants)
- `src/components/tech/PayrollWeekSection.tsx` — wraps a list of JobRows in a Card with week header

### Rewritten files

- `src/pages/tech/TechHome.tsx`
- `src/pages/tech/TechJobs.tsx`
- `src/pages/tech/TechJobDetail.tsx`

### Lightly modified files

- `src/components/tech/JobRow.tsx` — revenue adopts `MiniStat`-style typography (one small change)

### Deleted files

- `src/components/tech/ScorecardHero.tsx`
- `src/components/tech/SecondaryMetrics.tsx`
- `src/components/tech/SyncStatusBar.tsx`

### Untouched (already correct)

- All hooks except the net-new ones
- All Edge Functions
- All other migrations through `20260423180355_add_job_part_rpc.sql`
- RLS policies, views, triggers
- `TechShell`, `RequireTechnician`, `TimeframePicker`, `StatusFilter`, `JobStatusBadge`, `PartsPickerModal`, `SubmitConfirmModal`, `RequestPartAddModal`, `TeamOverrideCard`
- `TechProfile`, `TechAppointments`, `TechEstimates`
- Utilities: `smart-guard`, `timeframe`, `format`, `part-category`

---

## Task 1: `my_paystub` Postgres function

**Files:**
- Create: `supabase/migrations/<ts>_my_paystub_fn.sql` (use `npx supabase migration new my_paystub_fn`; timestamp must be later than `20260423180355`; if migrator generates an earlier one, rename to `20260423181000_my_paystub_fn.sql`)

- [ ] **Step 1: Run the task verification block**

```bash
git -C /Users/daniel/twins-dashboard/twins-dash checkout feature/tech-dashboard-phase-1-infra 2>&1
git -C /Users/daniel/twins-dashboard/twins-dash branch --show-current
git -C /Users/daniel/twins-dashboard/twins-dash log --oneline -1
```

Expected: on `feature/tech-dashboard-phase-1-infra` at commit `c9338c3 feat(tech-dashboard): Charles Team Override card`.

- [ ] **Step 2: Create the migration file**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx supabase migration new my_paystub_fn
```

Note the generated filename. If its 14-digit timestamp is ≤ `20260423180355`, rename it to `20260423181000_my_paystub_fn.sql`.

- [ ] **Step 3: Write the SQL**

Paste this into the migration file:

```sql
-- Tech self-service: richer paystub breakdown for a given timeframe.
-- Returns one row with revenue / primary / override / tip / total / counts /
-- next_draft_job_id. All queries scope to the caller via current_technician_name().

CREATE OR REPLACE FUNCTION public.my_paystub(
  p_since timestamptz,
  p_until timestamptz
)
RETURNS TABLE (
  revenue              numeric,
  primary_commission   numeric,
  override_commission  numeric,
  tip_total            numeric,
  commission_total     numeric,
  commission_pct       numeric,
  job_count            bigint,
  draft_count          bigint,
  submitted_count      bigint,
  locked_count         bigint,
  next_draft_job_id    int
)
LANGUAGE sql
STABLE
SECURITY INVOKER
AS $$
  WITH me AS (
    SELECT id, name, commission_pct
    FROM public.payroll_techs
    WHERE auth_user_id = auth.uid()
    LIMIT 1
  ),
  my_jobs AS (
    SELECT j.*
    FROM public.payroll_jobs j
    WHERE j.owner_tech = (SELECT name FROM me)
      AND j.deleted_at IS NULL
      AND j.job_date::timestamptz >= p_since
      AND j.job_date::timestamptz <  p_until
  ),
  my_comms AS (
    SELECT c.*
    FROM public.payroll_commissions c
    JOIN my_jobs mj ON mj.id = c.job_id
    WHERE c.tech_name = (SELECT name FROM me)
  ),
  agg AS (
    SELECT
      COALESCE(SUM(mj.amount), 0)::numeric                                       AS revenue,
      COUNT(mj.id)::bigint                                                       AS job_count,
      COUNT(mj.id) FILTER (WHERE mj.submission_status = 'draft')::bigint         AS draft_count,
      COUNT(mj.id) FILTER (WHERE mj.submission_status = 'submitted')::bigint     AS submitted_count,
      COUNT(mj.id) FILTER (WHERE mj.submission_status = 'locked')::bigint        AS locked_count
    FROM my_jobs mj
  ),
  comm AS (
    SELECT
      COALESCE(SUM(commission_amt) FILTER (WHERE kind='primary'),  0)::numeric  AS primary_commission,
      COALESCE(SUM(commission_amt) FILTER (WHERE kind='override'), 0)::numeric  AS override_commission,
      COALESCE(SUM(tip_amt),                                       0)::numeric  AS tip_total,
      COALESCE(SUM(total),                                         0)::numeric  AS commission_total
    FROM my_comms
  ),
  next_draft AS (
    SELECT id
    FROM my_jobs
    WHERE submission_status = 'draft'
    ORDER BY job_date ASC, id ASC
    LIMIT 1
  )
  SELECT
    agg.revenue,
    comm.primary_commission,
    comm.override_commission,
    comm.tip_total,
    comm.commission_total,
    COALESCE((SELECT commission_pct FROM me), 0)::numeric,
    agg.job_count,
    agg.draft_count,
    agg.submitted_count,
    agg.locked_count,
    (SELECT id FROM next_draft)
  FROM agg, comm;
$$;

GRANT EXECUTE ON FUNCTION public.my_paystub(timestamptz, timestamptz)
  TO authenticated, service_role;
```

- [ ] **Step 4: Apply the migration**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx supabase db push --dry-run
# expect: exactly one new pending migration (your new file)
npx supabase db push
# expect: Finished supabase db push.
```

If `db push` wants to apply anything other than your new file, STOP — something is out of sync.

- [ ] **Step 5: Smoke-test the function**

```bash
# Zero-data case (call with a UUID that has no linked tech): function returns a row with zeros
npx supabase db query "SELECT * FROM public.my_paystub(now() - interval '30 days', now());" --linked --output json
# Expected under service-role: one row, all zeros + commission_pct=0 + next_draft_job_id=null.
```

Since service-role doesn't have `auth.uid()`, `current_technician_name()` returns NULL → no jobs match → all-zero row. That's the correct no-data behavior. A real tech session would see their data.

- [ ] **Step 6: Regenerate supabase types**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx supabase gen types typescript --linked 2>/dev/null > src/integrations/supabase/types.ts
grep -q "my_paystub:" src/integrations/supabase/types.ts && echo "my_paystub in types" || echo "MISSING"
```

Must print "my_paystub in types". If missing, stop — regen failed.

- [ ] **Step 7: Commit**

```bash
git -C /Users/daniel/twins-dashboard/twins-dash add \
  supabase/migrations/*my_paystub_fn.sql \
  src/integrations/supabase/types.ts

git -C /Users/daniel/twins-dashboard/twins-dash status --short
# Verify only the two intended files staged. Stray 20260423180100_payroll_merge_rpc.sql must stay unstaged.

git -C /Users/daniel/twins-dashboard/twins-dash commit -m "feat(tech-dashboard): my_paystub function for richer breakdown"
```

---

## Task 2: Hooks + hero-action-state helper + unit test

**Files:**
- Create: `src/lib/tech-dashboard/hero-action-state.ts`
- Create: `src/lib/tech-dashboard/__tests__/hero-action-state.test.ts`
- Create: `src/hooks/tech/useMyPaystub.ts`
- Create: `src/hooks/tech/useLastFinalizedPaystub.ts`

- [ ] **Step 1: Run task verification block (see Cross-cutting rules)**

- [ ] **Step 2: Write failing unit test**

Create `src/lib/tech-dashboard/__tests__/hero-action-state.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { computeHeroActionState } from '../hero-action-state';

describe('computeHeroActionState', () => {
  it("returns 'no_jobs' when job_count is 0", () => {
    expect(computeHeroActionState({ job_count: 0, draft_count: 0, next_draft_job_id: null })).toBe('no_jobs');
  });

  it("returns 'enter_parts' when there's a draft job with a next id", () => {
    expect(computeHeroActionState({ job_count: 3, draft_count: 2, next_draft_job_id: 17 })).toBe('enter_parts');
  });

  it("returns 'caught_up' when jobs exist but none are draft", () => {
    expect(computeHeroActionState({ job_count: 3, draft_count: 0, next_draft_job_id: null })).toBe('caught_up');
  });

  it("returns 'caught_up' when draft_count is 0 even if next_draft_job_id is stale null", () => {
    expect(computeHeroActionState({ job_count: 5, draft_count: 0, next_draft_job_id: null })).toBe('caught_up');
  });
});
```

- [ ] **Step 3: Run the test — expect FAIL**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx vitest run src/lib/tech-dashboard/__tests__/hero-action-state.test.ts 2>&1 | tail -15
```

Expected: import fails (module not found).

- [ ] **Step 4: Create the helper**

Create `src/lib/tech-dashboard/hero-action-state.ts`:

```typescript
export type HeroActionState = 'enter_parts' | 'caught_up' | 'no_jobs';

export interface HeroActionInput {
  job_count: number;
  draft_count: number;
  next_draft_job_id: number | null;
}

/**
 * Decide which variant the Home hero action card should render.
 * - no_jobs:     tech has no jobs in the current timeframe
 * - enter_parts: tech has draft jobs waiting for parts entry
 * - caught_up:   tech has jobs, all are submitted/locked
 */
export function computeHeroActionState(input: HeroActionInput): HeroActionState {
  if (input.job_count === 0) return 'no_jobs';
  if (input.draft_count > 0 && input.next_draft_job_id != null) return 'enter_parts';
  return 'caught_up';
}
```

- [ ] **Step 5: Re-run tests — expect PASS**

```bash
npx vitest run src/lib/tech-dashboard/__tests__/hero-action-state.test.ts 2>&1 | tail -10
```

Expected: 4 passed.

- [ ] **Step 6: Create `useMyPaystub.ts`**

Create `src/hooks/tech/useMyPaystub.ts`:

```typescript
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { Timeframe, timeframeRange } from '@/lib/tech-dashboard/timeframe';
import { Database } from '@/integrations/supabase/types';

export type MyPaystub = Database['public']['Functions']['my_paystub']['Returns'][number];

const ZEROS: MyPaystub = {
  revenue: 0,
  primary_commission: 0,
  override_commission: 0,
  tip_total: 0,
  commission_total: 0,
  commission_pct: 0,
  job_count: 0,
  draft_count: 0,
  submitted_count: 0,
  locked_count: 0,
  next_draft_job_id: null,
} as MyPaystub;

export function useMyPaystub(tf: Timeframe) {
  const { since, until } = timeframeRange(tf);
  return useQuery({
    queryKey: ['my_paystub', since.toISOString(), until.toISOString()],
    queryFn: async (): Promise<MyPaystub> => {
      const { data, error } = await supabase.rpc('my_paystub', {
        p_since: since.toISOString(),
        p_until: until.toISOString(),
      });
      if (error) throw error;
      return (data?.[0] ?? ZEROS) as MyPaystub;
    },
    staleTime: 30_000,
    refetchInterval: 30_000,
    refetchOnWindowFocus: false,
  });
}
```

- [ ] **Step 7: Create `useLastFinalizedPaystub.ts`**

Create `src/hooks/tech/useLastFinalizedPaystub.ts`:

```typescript
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import type { MyPaystub } from './useMyPaystub';

export interface LastFinalizedPaystub {
  weekStart: string;  // YYYY-MM-DD
  weekEnd: string;
  lockedAt: string | null;
  paystub: MyPaystub;
}

/**
 * Returns the caller's most recent finalized payroll week + its paystub.
 * null when the tech has no finalized runs yet.
 */
export function useLastFinalizedPaystub() {
  return useQuery({
    queryKey: ['last_finalized_paystub'],
    queryFn: async (): Promise<LastFinalizedPaystub | null> => {
      // 1. Find the most recent final run that the caller has jobs on.
      //    RLS on payroll_runs already scopes by "EXISTS jobs for caller".
      const { data: run, error } = await supabase
        .from('payroll_runs')
        .select('id, week_start, week_end, locked_at, status')
        .eq('status', 'final')
        .order('week_start', { ascending: false })
        .limit(1)
        .maybeSingle();

      if (error) throw error;
      if (!run) return null;

      // 2. Call my_paystub bounded to that week. Add one day to week_end so the
      //    [since, until) half-open interval covers jobs scheduled on week_end.
      const since = new Date(run.week_start + 'T00:00:00').toISOString();
      const u = new Date(run.week_end + 'T00:00:00');
      u.setDate(u.getDate() + 1);
      const until = u.toISOString();

      const { data: paystubRows, error: psErr } = await supabase.rpc('my_paystub', {
        p_since: since, p_until: until,
      });
      if (psErr) throw psErr;
      const paystub = (paystubRows?.[0] ?? null) as MyPaystub | null;
      if (!paystub) return null;

      return {
        weekStart: run.week_start,
        weekEnd: run.week_end,
        lockedAt: (run as any).locked_at ?? null,
        paystub,
      };
    },
    staleTime: 5 * 60_000,  // locked data changes rarely
    refetchOnWindowFocus: false,
  });
}
```

- [ ] **Step 8: Verify build**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npm run build 2>&1 | tail -10
```

Expected: success.

- [ ] **Step 9: Commit**

```bash
git -C /Users/daniel/twins-dashboard/twins-dash add \
  src/lib/tech-dashboard/hero-action-state.ts \
  src/lib/tech-dashboard/__tests__/hero-action-state.test.ts \
  src/hooks/tech/useMyPaystub.ts \
  src/hooks/tech/useLastFinalizedPaystub.ts

git -C /Users/daniel/twins-dashboard/twins-dash status --short

git -C /Users/daniel/twins-dashboard/twins-dash commit -m "feat(tech-dashboard): useMyPaystub + useLastFinalizedPaystub + hero state helper"
```

---

## Task 3: Presentational components (HeroActionCard + PaystubCard + PayrollWeekSection)

**Files:**
- Create: `src/components/tech/HeroActionCard.tsx`
- Create: `src/components/tech/PaystubCard.tsx`
- Create: `src/components/tech/PayrollWeekSection.tsx`

- [ ] **Step 1: Run task verification block**

- [ ] **Step 2: Create `HeroActionCard.tsx`**

```tsx
import { Link } from 'react-router-dom';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle2, Wrench, RefreshCw } from 'lucide-react';
import type { HeroActionState } from '@/lib/tech-dashboard/hero-action-state';

interface Props {
  state: HeroActionState;
  draftCount: number;
  totalCount: number;
  nextDraftJobId: number | null;
  onForceRefresh: () => void;
  isRefreshing: boolean;
}

export function HeroActionCard({ state, draftCount, totalCount, nextDraftJobId, onForceRefresh, isRefreshing }: Props) {
  if (state === 'enter_parts' && nextDraftJobId != null) {
    const jobWord = draftCount === 1 ? 'job' : 'jobs';
    return (
      <Card className="border-accent/40">
        <CardContent className="p-6">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <h2 className="text-xl font-semibold">Enter parts for {draftCount} {jobWord}</h2>
              <p className="text-sm text-muted-foreground">
                Still need parts entered this week. Tap to start with the next one.
              </p>
            </div>
            <Button asChild className="bg-accent text-accent-foreground hover:bg-accent/90">
              <Link to={`/tech/jobs/${nextDraftJobId}`}>
                <Wrench className="mr-2 h-4 w-4" /> Enter Parts
              </Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (state === 'caught_up') {
    return (
      <Card className="border-primary/30 bg-primary/5">
        <CardContent className="p-6">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div className="flex items-start gap-3">
              <CheckCircle2 className="h-6 w-6 shrink-0 text-primary" />
              <div>
                <h2 className="text-xl font-semibold">You're caught up</h2>
                <p className="text-sm text-muted-foreground">
                  All {totalCount} of {totalCount} jobs submitted this week.
                </p>
              </div>
            </div>
            <Button variant="outline" asChild>
              <Link to="/tech/jobs?status=submitted">View this week's jobs</Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardContent className="p-6">
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 className="text-xl font-semibold">No jobs this week yet</h2>
            <p className="text-sm text-muted-foreground">
              Pull the latest from HouseCall Pro if you expect to see jobs.
            </p>
          </div>
          <Button variant="outline" onClick={onForceRefresh} disabled={isRefreshing}>
            <RefreshCw className={`mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
            {isRefreshing ? 'Refreshing…' : 'Refresh from HCP'}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 3: Create `PaystubCard.tsx`**

```tsx
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { MyPaystub } from '@/hooks/tech/useMyPaystub';
import { formatCurrency } from '@/lib/tech-dashboard/format';

interface Props {
  title: string;
  caption: string;
  paystub: MyPaystub | undefined;
  isSupervisor: boolean;
  disclaimer?: string;
  footnote?: string;  // e.g. "Locked on Apr 21."
}

function pct(v: number | null | undefined): string {
  if (v == null) return '—';
  return `${(Number(v) * 100).toFixed(0)}%`;
}

function Row({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className={`flex items-center justify-between ${strong ? 'pt-2 border-t' : ''}`}>
      <span className={strong ? 'font-semibold' : 'text-muted-foreground'}>{label}</span>
      <span className={`tabular-nums ${strong ? 'font-semibold text-lg' : 'font-medium'}`}>{value}</span>
    </div>
  );
}

export function PaystubCard({ title, caption, paystub, isSupervisor, disclaimer, footnote }: Props) {
  const p = paystub ?? ({
    revenue: 0, primary_commission: 0, override_commission: 0,
    tip_total: 0, commission_total: 0, commission_pct: 0,
  } as MyPaystub);

  const showOverride = isSupervisor || Number(p.override_commission ?? 0) > 0;

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
        <CardTitle className="text-base">{title}</CardTitle>
        <span className="text-xs text-muted-foreground">{caption}</span>
      </CardHeader>
      <CardContent className="space-y-2 text-sm">
        <Row label="Revenue worked"    value={formatCurrency(Number(p.revenue ?? 0))} />
        <Row label="Commission rate"   value={pct(p.commission_pct)} />
        <Row label="Commission earned" value={formatCurrency(Number(p.primary_commission ?? 0))} />
        {showOverride && (
          <Row label="Team override" value={formatCurrency(Number(p.override_commission ?? 0))} />
        )}
        <Row label="Tip" value={formatCurrency(Number(p.tip_total ?? 0))} />
        <Row label="Estimated total" value={formatCurrency(Number(p.commission_total ?? 0))} strong />
        {footnote && <p className="pt-2 text-xs text-muted-foreground">{footnote}</p>}
        {disclaimer && <p className="pt-1 text-xs text-muted-foreground italic">{disclaimer}</p>}
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 4: Create `PayrollWeekSection.tsx`**

```tsx
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { JobRow } from './JobRow';
import type { MyJob } from '@/hooks/tech/useMyJobs';

interface Props {
  weekStart: string;    // 'YYYY-MM-DD' or 'Unscheduled'
  weekEnd?: string;
  runStatus?: string | null;  // 'in_progress' | 'final' | 'superseded' | null
  jobs: MyJob[];
}

function formatRange(weekStart: string, weekEnd?: string): string {
  if (weekStart === 'Unscheduled') return 'Unscheduled';
  const fmt = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  return weekEnd ? `${fmt(weekStart)}–${fmt(weekEnd)}` : fmt(weekStart);
}

function statusCaption(runStatus: string | null | undefined): string {
  if (runStatus === 'final') return 'finalized';
  if (runStatus === 'in_progress') return 'in progress';
  if (runStatus === 'superseded') return 'superseded';
  return '';
}

export function PayrollWeekSection({ weekStart, weekEnd, runStatus, jobs }: Props) {
  const caption = statusCaption(runStatus);
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
        <CardTitle className="text-base">Week of {formatRange(weekStart, weekEnd)}</CardTitle>
        {caption && <span className="text-xs uppercase text-muted-foreground">{caption}</span>}
      </CardHeader>
      <CardContent className="space-y-2 pt-0">
        {jobs.map(j => <JobRow key={j.id as number} job={j} />)}
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 5: Verify build**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npm run build 2>&1 | tail -10
```

Components are unused at this point — they're ready for Tasks 4–6 to consume. Unused exports don't fail the build.

- [ ] **Step 6: Commit**

```bash
git -C /Users/daniel/twins-dashboard/twins-dash add \
  src/components/tech/HeroActionCard.tsx \
  src/components/tech/PaystubCard.tsx \
  src/components/tech/PayrollWeekSection.tsx

git -C /Users/daniel/twins-dashboard/twins-dash status --short

git -C /Users/daniel/twins-dashboard/twins-dash commit -m "feat(tech-dashboard): HeroActionCard + PaystubCard + PayrollWeekSection"
```

---

## Task 4: Rewrite TechHome + delete superseded components

**Files:**
- Rewrite: `src/pages/tech/TechHome.tsx`
- Delete: `src/components/tech/ScorecardHero.tsx`
- Delete: `src/components/tech/SecondaryMetrics.tsx`
- Delete: `src/components/tech/SyncStatusBar.tsx`

- [ ] **Step 1: Task verification block**

- [ ] **Step 2: Verify deleted files aren't referenced elsewhere**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
grep -rn "ScorecardHero\|SecondaryMetrics\|SyncStatusBar" src/ 2>&1 | grep -v "components/tech/ScorecardHero\|components/tech/SecondaryMetrics\|components/tech/SyncStatusBar"
```

Expected: only `src/pages/tech/TechHome.tsx` shows up (the current importer). If any other file imports them, STOP and report BLOCKED — the spec assumed no outside consumers.

- [ ] **Step 3: Rewrite `TechHome.tsx`**

Overwrite `src/pages/tech/TechHome.tsx` with:

```tsx
import { useState } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import { TimeframePicker } from '@/components/tech/TimeframePicker';
import { HeroActionCard } from '@/components/tech/HeroActionCard';
import { PaystubCard } from '@/components/tech/PaystubCard';
import { TeamOverrideCard } from '@/components/tech/TeamOverrideCard';
import { useMyPaystub } from '@/hooks/tech/useMyPaystub';
import { useLastFinalizedPaystub } from '@/hooks/tech/useLastFinalizedPaystub';
import { useForceRefresh } from '@/hooks/tech/useForceRefresh';
import { computeHeroActionState } from '@/lib/tech-dashboard/hero-action-state';
import type { Timeframe } from '@/lib/tech-dashboard/timeframe';
import { toast } from '@/components/ui/sonner';

const DISCLAIMER = 'Estimate. Final amounts confirmed when admin runs payroll. Pricebook-only; custom parts require admin review. Take with a grain of salt.';

function formatLockedOn(lockedAt: string | null): string {
  if (!lockedAt) return 'Finalized.';
  try {
    return 'Locked on ' + new Date(lockedAt).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + '.';
  } catch { return 'Finalized.'; }
}

function formatWeekRange(weekStart: string, weekEnd: string): string {
  const fmt = (d: string) => new Date(d + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  return `${fmt(weekStart)}–${fmt(weekEnd)}`;
}

export default function TechHome() {
  const [tf, setTf] = useState<Timeframe>('week');
  const { technicianName, isFieldSupervisor } = useAuth() as any;
  const { data: paystub, isLoading } = useMyPaystub(tf);
  const { data: last } = useLastFinalizedPaystub();
  const forceRefresh = useForceRefresh();

  const heroState = paystub
    ? computeHeroActionState({
        job_count: Number(paystub.job_count ?? 0),
        draft_count: Number(paystub.draft_count ?? 0),
        next_draft_job_id: (paystub.next_draft_job_id as number | null) ?? null,
      })
    : 'no_jobs';

  const handleRefresh = async () => {
    try {
      const res = await forceRefresh.mutateAsync(14);
      toast.success(`Synced ${res.jobs_synced} jobs`);
    } catch (e: any) {
      toast.error(e?.message ?? 'Sync failed');
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-primary">
          Hey, {technicianName ?? 'there'}
        </h1>
        <p className="text-sm text-muted-foreground">
          Your jobs, parts, and weekly earnings.
        </p>
      </div>

      <TimeframePicker value={tf} onChange={setTf} />

      <HeroActionCard
        state={heroState}
        draftCount={Number(paystub?.draft_count ?? 0)}
        totalCount={Number(paystub?.job_count ?? 0)}
        nextDraftJobId={(paystub?.next_draft_job_id as number | null) ?? null}
        onForceRefresh={handleRefresh}
        isRefreshing={forceRefresh.isPending}
      />

      <PaystubCard
        title="Your estimated paystub"
        caption="This week · provisional"
        paystub={paystub}
        isSupervisor={!!isFieldSupervisor}
        disclaimer={DISCLAIMER}
      />

      {last && (
        <PaystubCard
          title="Your last paystub"
          caption={`${formatWeekRange(last.weekStart, last.weekEnd)} · finalized`}
          paystub={last.paystub}
          isSupervisor={!!isFieldSupervisor}
          footnote={formatLockedOn(last.lockedAt)}
        />
      )}

      {isFieldSupervisor && <TeamOverrideCard />}
    </div>
  );
}
```

- [ ] **Step 4: Delete the superseded components**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
rm src/components/tech/ScorecardHero.tsx
rm src/components/tech/SecondaryMetrics.tsx
rm src/components/tech/SyncStatusBar.tsx
```

- [ ] **Step 5: Verify build**

```bash
npm run build 2>&1 | tail -15
```

Expected: success. If `SyncStatusBar` or the others are still referenced anywhere, that reference must be cleaned up — the grep in Step 2 should have caught it.

- [ ] **Step 6: Commit**

```bash
git -C /Users/daniel/twins-dashboard/twins-dash add src/pages/tech/TechHome.tsx
git -C /Users/daniel/twins-dashboard/twins-dash rm src/components/tech/ScorecardHero.tsx src/components/tech/SecondaryMetrics.tsx src/components/tech/SyncStatusBar.tsx

git -C /Users/daniel/twins-dashboard/twins-dash status --short

git -C /Users/daniel/twins-dashboard/twins-dash commit -m "feat(tech-dashboard): rewrite TechHome in payroll-mirror style

- Header + subtitle matching admin PayrollHome.
- HeroActionCard flips between enter-parts / caught-up / no-jobs.
- PaystubCard for this-week (provisional) and last-week (finalized).
- TeamOverrideCard for field_supervisor.
- Removes ScorecardHero, SecondaryMetrics, SyncStatusBar (superseded)."
```

---

## Task 5: Rewrite TechJobs + JobRow typography tweak

**Files:**
- Rewrite: `src/pages/tech/TechJobs.tsx`
- Modify: `src/components/tech/JobRow.tsx`

- [ ] **Step 1: Task verification block**

- [ ] **Step 2: Modify `JobRow.tsx` revenue typography**

The existing `JobRow` renders revenue as `<span className="text-sm font-semibold tabular-nums">`. Change that one className to `<span className="text-xl font-bold tabular-nums">` — matches `MiniStat` from admin PayrollHome. Leave every other line alone.

Example diff (exact line numbers depend on the file; locate the `{formatCurrency(Number(job.amount ?? 0))}` span):

```tsx
// Before:
<span className="text-sm font-semibold tabular-nums">{formatCurrency(Number(job.amount ?? 0))}</span>

// After:
<span className="text-xl font-bold tabular-nums">{formatCurrency(Number(job.amount ?? 0))}</span>
```

- [ ] **Step 3: Rewrite `TechJobs.tsx`**

Overwrite with:

```tsx
import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { TimeframePicker } from '@/components/tech/TimeframePicker';
import { StatusFilter } from '@/components/tech/StatusFilter';
import { PayrollWeekSection } from '@/components/tech/PayrollWeekSection';
import { Card, CardContent } from '@/components/ui/card';
import { useMyJobs, type MyJob } from '@/hooks/tech/useMyJobs';
import type { Timeframe } from '@/lib/tech-dashboard/timeframe';

type Status = 'draft' | 'submitted' | 'locked';

function parseStatus(s: string | null): Status | undefined {
  if (s === 'draft' || s === 'submitted' || s === 'locked') return s;
  return undefined;
}

export default function TechJobs() {
  const [tf, setTf] = useState<Timeframe>('week');
  const [sp, setSp] = useSearchParams();
  const status = parseStatus(sp.get('status'));

  const setStatus = (v: Status | undefined) => {
    const next = new URLSearchParams(sp);
    if (v) next.set('status', v); else next.delete('status');
    setSp(next, { replace: true });
  };

  const { data: jobs = [], isLoading } = useMyJobs(tf, status);

  // Group by week_start. Each group also carries the run_status (first row wins; all rows in a group share it).
  const groups = useMemo(() => {
    const map = new Map<string, { weekStart: string; weekEnd?: string; runStatus?: string | null; jobs: MyJob[] }>();
    for (const j of jobs) {
      const key = (j.week_start as string | null) ?? 'Unscheduled';
      const existing = map.get(key);
      if (existing) {
        existing.jobs.push(j);
      } else {
        map.set(key, {
          weekStart: key,
          weekEnd: (j.week_end as string | null) ?? undefined,
          runStatus: (j.run_status as string | null) ?? null,
          jobs: [j],
        });
      }
    }
    return Array.from(map.values()).sort((a, b) => b.weekStart.localeCompare(a.weekStart));
  }, [jobs]);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-primary">Your jobs</h1>
        <p className="text-sm text-muted-foreground">
          Your work across HouseCall Pro, grouped by payroll week.
        </p>
      </div>

      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <TimeframePicker value={tf} onChange={setTf} />
        <StatusFilter value={status} onChange={setStatus} />
      </div>

      {isLoading && <div className="text-sm text-muted-foreground">Loading…</div>}

      {!isLoading && jobs.length === 0 && (
        <Card>
          <CardContent className="p-6 text-center text-sm text-muted-foreground">
            No jobs in this period.
            <div className="mt-1">Hit Refresh on the Home tab to sync from HouseCall Pro.</div>
          </CardContent>
        </Card>
      )}

      <div className="space-y-4">
        {groups.map(g => (
          <PayrollWeekSection
            key={g.weekStart}
            weekStart={g.weekStart}
            weekEnd={g.weekEnd}
            runStatus={g.runStatus}
            jobs={g.jobs}
          />
        ))}
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Verify build**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npm run build 2>&1 | tail -10
```

Expected: success.

- [ ] **Step 5: Commit**

```bash
git -C /Users/daniel/twins-dashboard/twins-dash add \
  src/pages/tech/TechJobs.tsx \
  src/components/tech/JobRow.tsx

git -C /Users/daniel/twins-dashboard/twins-dash status --short

git -C /Users/daniel/twins-dashboard/twins-dash commit -m "feat(tech-dashboard): rewrite TechJobs with per-week Card sections

- Page header + subtitle matching admin PayrollHome.
- Groups jobs into Card sections per payroll week with run-status caption.
- Status filter persists via URL search param (?status=submitted).
- JobRow revenue adopts MiniStat-style typography (text-xl font-bold)."
```

---

## Task 6: Rewrite TechJobDetail

**Files:**
- Rewrite: `src/pages/tech/TechJobDetail.tsx`

- [ ] **Step 1: Task verification block**

- [ ] **Step 2: Rewrite `TechJobDetail.tsx`**

Overwrite the existing file with:

```tsx
import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, ExternalLink, Plus, Trash2, AlertTriangle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/sonner';
import { JobStatusBadge } from '@/components/tech/JobStatusBadge';
import { PartsPickerModal } from '@/components/tech/PartsPickerModal';
import { SubmitConfirmModal } from '@/components/tech/SubmitConfirmModal';
import { RequestPartAddModal } from '@/components/tech/RequestPartAddModal';
import { useMyJobDetail } from '@/hooks/tech/useMyJobDetail';
import { useRemoveJobPart } from '@/hooks/tech/useRemoveJobPart';
import { useSubmitJobParts } from '@/hooks/tech/useSubmitJobParts';
import { detectMissingParts } from '@/lib/tech-dashboard/smart-guard';
import { formatCurrency, formatJobDate } from '@/lib/tech-dashboard/format';

function MiniStat({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[11px] uppercase text-muted-foreground">{label}</div>
      <div className="text-xl font-bold tabular-nums">{value}</div>
    </div>
  );
}

export default function TechJobDetail() {
  const { jobId: jobIdParam } = useParams();
  const jobId = jobIdParam ? Number(jobIdParam) : null;
  const { data, isLoading, error } = useMyJobDetail(jobId);

  const [pickerOpen, setPickerOpen] = useState(false);
  const [requestOpen, setRequestOpen] = useState(false);
  const [submitOpen, setSubmitOpen] = useState(false);
  const [noPartsUsed, setNoPartsUsed] = useState(false);

  const removeMut = useRemoveJobPart(jobId);
  const submitMut = useSubmitJobParts(jobId);

  if (!jobId) return <div className="p-4 text-sm text-destructive">Invalid job id.</div>;
  if (isLoading) return <div className="text-sm text-muted-foreground">Loading…</div>;
  if (error) return <div className="text-sm text-destructive">Failed to load job: {String(error)}</div>;
  if (!data?.job) return <div className="text-sm text-muted-foreground">Job not found.</div>;

  const { job, parts } = data;
  const status = (job.submission_status ?? 'draft') as 'draft' | 'submitted' | 'locked';
  const isDraft = status === 'draft';
  const isWeekLocked = !!job.is_week_locked;
  const canEdit = isDraft && !isWeekLocked;

  const jobAny = job as any;
  const notesText: string | null = jobAny.notes_text ?? null;
  const lineItemsText: string | null = jobAny.line_items_text ?? null;
  const showHcpSource = !!(notesText && notesText.trim()) || !!(lineItemsText && lineItemsText.trim());

  const missing = canEdit
    ? detectMissingParts({
        notes: notesText,
        line_items: lineItemsText,
        entered_part_names: parts.map(p => p.part_name ?? ''),
      })
    : [];

  const handleRemove = async (partId: number) => {
    try {
      await removeMut.mutateAsync(partId);
      toast.success('Part removed');
    } catch (e: any) {
      toast.error(e?.message ?? 'Failed to remove part');
    }
  };

  const handleConfirmSubmit = async () => {
    try {
      await submitMut.mutateAsync({ no_parts_used: parts.length === 0 && noPartsUsed });
      toast.success('Submitted. Thanks!');
      setSubmitOpen(false);
    } catch (e: any) {
      toast.error(e?.message ?? 'Submit failed');
    }
  };

  return (
    <div className="space-y-6">
      <Link to="/tech/jobs" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="h-4 w-4" /> Back to jobs
      </Link>

      {/* Job card (hero) */}
      <Card>
        <CardContent className="space-y-4 p-6">
          <div className="flex items-center justify-between gap-2">
            <h2 className="text-xl font-semibold truncate">{job.customer_display ?? 'Customer'}</h2>
            <JobStatusBadge status={status} />
          </div>
          <div className="text-sm text-muted-foreground">
            {formatJobDate(job.job_date ?? '')} · Job #{job.hcp_job_number ?? '—'}
          </div>
          {job.commission_admin_adjusted && (
            <div className="rounded-md border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">
              Admin reviewed &amp; adjusted — numbers may differ from what you last entered.
            </div>
          )}
          <div className="grid gap-4 md:grid-cols-3">
            <MiniStat label="Revenue" value={formatCurrency(Number(job.amount ?? 0))} />
            <MiniStat label="Work type" value={job.description ?? '—'} />
            <MiniStat label="Parts entered" value={String(parts.length)} />
          </div>
          <div>
            <Button variant="outline" size="sm" asChild>
              <a
                href={`https://pro.housecallpro.com/app/jobs/${job.hcp_id}`}
                target="_blank"
                rel="noopener noreferrer"
              >
                Open in HouseCall Pro <ExternalLink className="ml-1 h-3 w-3" />
              </a>
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* HCP notes card */}
      {showHcpSource && (
        <Card>
          <CardHeader className="pb-2"><CardTitle className="text-base">From HouseCall Pro</CardTitle></CardHeader>
          <CardContent className="space-y-3 text-sm">
            {lineItemsText && lineItemsText.trim() && (
              <div>
                <div className="text-[11px] uppercase text-muted-foreground mb-1">Line items</div>
                <pre className="whitespace-pre-wrap text-xs leading-5 text-foreground/90">{lineItemsText}</pre>
              </div>
            )}
            {notesText && notesText.trim() && (
              <div>
                <div className="text-[11px] uppercase text-muted-foreground mb-1">Notes</div>
                <pre className="whitespace-pre-wrap text-xs leading-5 text-foreground/90">{notesText}</pre>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Parts card */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
          <CardTitle className="text-base">Parts used</CardTitle>
          {canEdit && (
            <Button size="sm" variant="outline" onClick={() => setPickerOpen(true)}>
              <Plus className="mr-1 h-4 w-4" /> Add Part
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-3">
          {parts.length === 0 ? (
            <div className="text-sm text-muted-foreground">No parts entered yet.</div>
          ) : (
            <ul className="space-y-2">
              {parts.map(p => {
                const isTechEntered = p.entered_by === 'tech';
                return (
                  <li key={p.id as number} className="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                    <div>
                      <div>{p.part_name}</div>
                      <div className="text-xs text-muted-foreground">Qty {p.quantity}</div>
                    </div>
                    {canEdit && isTechEntered && (
                      <Button
                        size="icon"
                        variant="ghost"
                        onClick={() => handleRemove(p.id as number)}
                        disabled={removeMut.isPending}
                        aria-label="Remove"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                  </li>
                );
              })}
            </ul>
          )}

          {missing.length > 0 && (
            <div className="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
              <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
              <div>
                This job's HouseCall Pro notes mention {missing.join(', ').toLowerCase()} but you haven't entered any.
                Double-check before submitting.
              </div>
            </div>
          )}

          {canEdit && (
            <div className="pt-2">
              <Button
                className="w-full bg-accent text-accent-foreground hover:bg-accent/90"
                onClick={() => setSubmitOpen(true)}
              >
                Submit parts for this job
              </Button>
            </div>
          )}

          {status === 'submitted' && (
            <div className="pt-2 text-sm text-muted-foreground">
              Submitted.{' '}
              <button
                className="text-primary underline underline-offset-2"
                onClick={() => toast('Contact Daniel to request a correction.')}
              >
                Request a correction
              </button>
            </div>
          )}

          {status === 'locked' && (
            <div className="pt-2 text-sm text-muted-foreground">Locked · this is final.</div>
          )}
        </CardContent>
      </Card>

      <PartsPickerModal
        jobId={jobId}
        open={pickerOpen}
        onOpenChange={setPickerOpen}
        onRequestMissingPart={() => setRequestOpen(true)}
      />

      <RequestPartAddModal jobId={jobId} open={requestOpen} onOpenChange={setRequestOpen} />

      <SubmitConfirmModal
        open={submitOpen}
        onOpenChange={setSubmitOpen}
        parts={parts.map(p => ({ part_name: p.part_name ?? '', quantity: Number(p.quantity ?? 0) }))}
        missingCategories={missing}
        noPartsUsed={noPartsUsed}
        setNoPartsUsed={setNoPartsUsed}
        onConfirm={handleConfirmSubmit}
        isPending={submitMut.isPending}
      />
    </div>
  );
}
```

- [ ] **Step 3: Verify build**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npm run build 2>&1 | tail -10
```

Expected: success.

- [ ] **Step 4: Run all existing tests**

```bash
npx vitest run src/lib/tech-dashboard/__tests__/ 2>&1 | tail -15
```

Expected: all tests pass (`timeframe`: 7, `smart-guard`: 5, `hero-action-state`: 4 = 16 total).

- [ ] **Step 5: Commit**

```bash
git -C /Users/daniel/twins-dashboard/twins-dash add src/pages/tech/TechJobDetail.tsx

git -C /Users/daniel/twins-dashboard/twins-dash status --short

git -C /Users/daniel/twins-dashboard/twins-dash commit -m "feat(tech-dashboard): rewrite TechJobDetail with 3-card layout

- Back link to jobs list.
- Job card (hero): customer name + status badge, date/job-number, MiniStat grid
  for Revenue/Work type/Parts entered, admin-adjusted banner, Open-in-HCP.
- 'From HouseCall Pro' card (conditional): line items + notes, same source
  admin sees in the Run wizard. Feeds the smart-guard check.
- Parts card: list with per-row Remove (tech-entered + draft only), smart-guard
  warning banner, yellow-accent 'Submit parts for this job' CTA.
- Never shows per-job commission (aggregate-only rule preserved)."
```

---

## Task 7: Push the branch

**Files:** none (push-only)

- [ ] **Step 1: Task verification block**

- [ ] **Step 2: Verify local state before push**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git -C /Users/daniel/twins-dashboard/twins-dash log origin/feature/tech-dashboard-phase-1-infra..feature/tech-dashboard-phase-1-infra --oneline
```

Expected: 6 new commits not yet on origin (Task 1 through Task 6).

```bash
git -C /Users/daniel/twins-dashboard/twins-dash status --short
```

Expected: empty output (clean working tree, except the always-stray `supabase/migrations/20260423180100_payroll_merge_rpc.sql` may or may not appear — that's fine).

- [ ] **Step 3: Push**

```bash
git -C /Users/daniel/twins-dashboard/twins-dash push origin feature/tech-dashboard-phase-1-infra
```

Expected: fast-forward push succeeds. No conflicts (branch only has additive commits).

- [ ] **Step 4: Report Vercel deploy URL for Daniel**

After push, Vercel auto-builds from the updated branch. The draft PR updates in place. Report back: "Push complete. When Vercel's PR-preview build finishes (~60s), load it and preview as Maurice. Or merge to main if ready."

No additional commit or file change in this task.

---

## Self-Review

**Spec coverage:**
- §4 Visual reference patterns → used throughout components (Task 3) and pages (Tasks 4–6).
- §5 TechHome layout (header, hero card 3-variant, paystub card, last paystub card, Charles override) → Task 4.
- §6 TechJobs layout (header, filter row, per-week Card sections) → Task 5.
- §7 TechJobDetail layout (back link, 3 cards, no per-job commission) → Task 6.
- §8.1 `my_paystub` function → Task 1.
- §8.2 `useMyPaystub` + `useLastFinalizedPaystub` → Task 2.
- §8.3 `HeroActionCard` + `PaystubCard` + `PayrollWeekSection` → Task 3.
- §9 Salvage / Replace / Delete / Add / Modify lists → distributed across Tasks 1–6.
- §10 Testing: `hero-action-state` unit test (Task 2); `my_paystub` smoke test via db query (Task 1); existing `timeframe`/`smart-guard` tests preserved (verified in Task 6 Step 4).
- §11 Rollout: branch already `feature/tech-dashboard-phase-1-infra`, commits focused per task, push at end (Task 7), Daniel previews as Maurice (already linked). Covered.

**Placeholder scan:** No TBD / TODO / "implement later" / vague-error-handling placeholders in the plan body. Every step shows the exact code or command.

**Type consistency:**
- `MyPaystub` type is defined once in `useMyPaystub.ts` and re-exported to `useLastFinalizedPaystub.ts` consistently.
- `MyJob` comes from `useMyJobs.ts` (already exists on branch); `TechJobs` and `PayrollWeekSection` import it consistently.
- `HeroActionState` enum values `'enter_parts' | 'caught_up' | 'no_jobs'` are used in the helper, test, and component consistently.
- `PaystubCard` prop names (`title`, `caption`, `paystub`, `isSupervisor`, `disclaimer`, `footnote`) match call sites in TechHome.
- `HeroActionCard` prop names (`state`, `draftCount`, `totalCount`, `nextDraftJobId`, `onForceRefresh`, `isRefreshing`) match the call site in TechHome.
- Edge-function call in `useForceRefresh`: payload already `{ since }`, matches what the existing `sync-my-hcp-jobs` expects.

Plan is complete and internally consistent.
