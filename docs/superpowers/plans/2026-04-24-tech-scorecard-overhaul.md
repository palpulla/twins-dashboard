# Tech Scorecard Overhaul Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `TechnicianView.tsx` with a mobile-first, role-aware unified scorecard (KPI block + commission tracker + week summary + paystubs), add `/admin/tech-requests` queue that unifies existing `part_requests` with a new `modification_requests` table, wire sidebar and routing. Visual language matches `Index.tsx`.

**Architecture:** Build on existing tech self-service infrastructure (`v_my_jobs`, `v_my_job_parts`, `my_scorecard`/`my_paystub` RPCs, `payroll_jobs.submission_status`, `part_requests`, `submit-job-parts` and `request-part-add` edge functions). Add one new table (`modification_requests`) and three new edge functions (`finish-tech-week`, `submit-modification-request`, `resolve-modification-request`). Reuse `useTechnicianData` for KPIs (reads `jobs` table via existing RLS). Admin queue page reads a union view of the two request tables.

**Tech Stack:** React + Vite + TypeScript, Supabase (Postgres + edge functions), TanStack Query, shadcn/ui, Vitest + Testing Library, react-day-picker.

**Spec:** `docs/superpowers/specs/2026-04-24-tech-scorecard-overhaul-design.md`

**Working directory:** `twins-dash/` (all file paths below are relative to repo root unless prefixed `twins-dash/`).

---

## File Structure

### New files

```
twins-dash/supabase/migrations/
  2026042500001_modification_requests.sql
  2026042500002_finish_tech_week_fn.sql
twins-dash/supabase/functions/
  finish-tech-week/index.ts
  submit-modification-request/index.ts
  resolve-modification-request/index.ts

twins-dash/src/lib/scorecard/
  kpi-comparison.ts
  __tests__/kpi-comparison.test.ts

twins-dash/src/components/technician/scorecard/
  DisclaimerBanner.tsx
  ComparisonPill.tsx
  TechScorecardKPIs.tsx
  CommissionTracker.tsx
  WeekSummary.tsx
  PastPaystubs.tsx
  ModificationRequestDialog.tsx

twins-dash/src/hooks/tech/
  useFinishTechWeek.ts
  useSubmitModificationRequest.ts
  useMyModificationRequests.ts
  usePastPaystubs.ts

twins-dash/src/pages/admin/
  TechRequests.tsx
twins-dash/src/components/admin/
  RequestCard.tsx
twins-dash/src/hooks/admin/
  useAdminTechRequests.ts
  useResolvePartRequest.ts
  useResolveModificationRequest.ts
```

### Modified files

```
twins-dash/src/pages/TechnicianView.tsx    — full rewrite as unified scorecard
twins-dash/src/App.tsx                      — add /admin/tech-requests route; auto-redirect /tech → /tech/:my_id
twins-dash/src/components/layout/AppSidebar.tsx  — add "Tech Requests" nav entry with count badge
twins-dash/src/lib/kpi-info.ts             — add any missing KPI_INFO entries used by the scorecard
```

### Retired / left-in-place

- `twins-dash/src/components/technician/CommissionSection.tsx` — no longer imported; leave file but remove usages.
- `twins-dash/src/pages/tech/TechHome.tsx`, `TechJobs.tsx`, `TechJobDetail.tsx`, etc. — remain as drill-in routes reachable from the new scorecard. No changes this plan.

---

## Task 1: `modification_requests` table and RLS

**Files:**
- Create: `twins-dash/supabase/migrations/2026042500001_modification_requests.sql`

- [ ] **Step 1.1: Write the migration**

Create `twins-dash/supabase/migrations/2026042500001_modification_requests.sql` with:

```sql
-- Tech-to-admin queue for post-lock modification requests.
-- Companion to part_requests (which handles new custom-part additions).

CREATE TABLE IF NOT EXISTS public.modification_requests (
  id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  requested_by      uuid NOT NULL REFERENCES auth.users(id),
  technician_id     int  NOT NULL REFERENCES public.payroll_techs(id) ON DELETE CASCADE,
  job_id            int  NOT NULL REFERENCES public.payroll_jobs(id)  ON DELETE CASCADE,
  run_id            int      REFERENCES public.payroll_runs(id),
  reasons           text[] NOT NULL,
  notes             text NOT NULL,
  status            text NOT NULL DEFAULT 'pending'
    CHECK (status IN ('pending','resolved','rejected')),
  resolved_by       uuid REFERENCES auth.users(id),
  resolved_at       timestamptz,
  resolution_notes  text,
  created_at        timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_modification_requests_status
  ON public.modification_requests(status, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_modification_requests_technician
  ON public.modification_requests(technician_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_modification_requests_job
  ON public.modification_requests(job_id);

ALTER TABLE public.modification_requests ENABLE ROW LEVEL SECURITY;

-- Tech: insert own rows only, for own locked jobs
DROP POLICY IF EXISTS "tech insert own modification_requests" ON public.modification_requests;
CREATE POLICY "tech insert own modification_requests" ON public.modification_requests
  FOR INSERT TO authenticated
  WITH CHECK (
    requested_by = auth.uid()
    AND technician_id = public.current_technician_id()
    AND EXISTS (
      SELECT 1
      FROM public.payroll_jobs j
      LEFT JOIN public.payroll_runs r ON r.id = j.run_id
      WHERE j.id = modification_requests.job_id
        AND j.owner_tech = public.current_technician_name()
        AND j.submission_status IN ('submitted','locked')
    )
  );

-- Tech: select own rows
DROP POLICY IF EXISTS "tech select own modification_requests" ON public.modification_requests;
CREATE POLICY "tech select own modification_requests" ON public.modification_requests
  FOR SELECT TO authenticated
  USING (
    technician_id = public.current_technician_id()
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
    OR public.has_payroll_access(auth.uid())
  );

-- Admin / field_supervisor: update (resolve / reject)
DROP POLICY IF EXISTS "admin update modification_requests" ON public.modification_requests;
CREATE POLICY "admin update modification_requests" ON public.modification_requests
  FOR UPDATE TO authenticated
  USING (
    public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
    OR public.has_payroll_access(auth.uid())
  )
  WITH CHECK (
    public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
    OR public.has_payroll_access(auth.uid())
  );

GRANT SELECT, INSERT ON public.modification_requests TO authenticated;
GRANT UPDATE ON public.modification_requests TO authenticated;
```

- [ ] **Step 1.2: Apply migration locally**

Run: `cd twins-dash && npx supabase db push --include-all --linked` (or equivalent in your dev setup).
Expected: migration applies without error, new table appears in `\dt public.*`.

If local Supabase is not running, use the MCP-linked remote dev branch:
```
mcp__a13384b5-...__apply_migration name=modification_requests query=<contents of file>
```

- [ ] **Step 1.3: Smoke test RLS manually**

```sql
-- As a tech (set auth.uid via test setup or supabase.auth.admin):
INSERT INTO modification_requests (requested_by, technician_id, job_id, reasons, notes)
VALUES (<tech auth uid>, <tech id>, <their submitted job id>, ARRAY['forgot_parts'], 'forgot 1x 25SSWDE');
-- Expected: succeeds

SELECT * FROM modification_requests WHERE technician_id = <other tech id>;
-- Expected: 0 rows
```

Note: if a local DB is not set up for tests, rely on the edge-function integration test in Task 8 rather than manual SQL here.

- [ ] **Step 1.4: Regenerate Supabase types**

Run: `cd twins-dash && npx supabase gen types typescript --linked > src/integrations/supabase/types.ts`
Expected: `modification_requests` appears under `Database['public']['Tables']`.

- [ ] **Step 1.5: Commit**

```bash
git add twins-dash/supabase/migrations/2026042500001_modification_requests.sql twins-dash/src/integrations/supabase/types.ts
git commit -m "feat(tech-dash): modification_requests table + RLS"
```

---

## Task 2: KPI comparison utility

Pure function that returns the pill state (better | worse | neutral) given a tech value and the company average.

**Files:**
- Create: `twins-dash/src/lib/scorecard/kpi-comparison.ts`
- Create: `twins-dash/src/lib/scorecard/__tests__/kpi-comparison.test.ts`

- [ ] **Step 2.1: Write the failing test**

Create `twins-dash/src/lib/scorecard/__tests__/kpi-comparison.test.ts`:

```ts
import { describe, it, expect } from 'vitest';
import { comparisonPillState, KpiKind } from '../kpi-comparison';

describe('comparisonPillState', () => {
  it('returns neutral when company avg is null', () => {
    expect(comparisonPillState(100, null, 'dollar')).toBe('neutral');
  });

  it('percent KPI: neutral within 2 points', () => {
    expect(comparisonPillState(73, 72, 'percent')).toBe('neutral');
    expect(comparisonPillState(73, 70, 'percent')).toBe('better');
    expect(comparisonPillState(65, 73, 'percent')).toBe('worse');
  });

  it('dollar KPI: neutral within 5% of avg', () => {
    expect(comparisonPillState(1020, 1000, 'dollar')).toBe('neutral');
    expect(comparisonPillState(1100, 1000, 'dollar')).toBe('better');
    expect(comparisonPillState(900, 1000, 'dollar')).toBe('worse');
  });

  it('count KPI: neutral within 5% of avg', () => {
    expect(comparisonPillState(51, 50, 'count')).toBe('neutral');
    expect(comparisonPillState(60, 50, 'count')).toBe('better');
    expect(comparisonPillState(40, 50, 'count')).toBe('worse');
  });

  it('inverts direction for lower-is-better KPIs', () => {
    // callback rate percent, invert=true
    expect(comparisonPillState(3, 8, 'percent', { lowerIsBetter: true })).toBe('better');
    expect(comparisonPillState(12, 8, 'percent', { lowerIsBetter: true })).toBe('worse');
  });

  it('handles zero avg without dividing by zero', () => {
    expect(comparisonPillState(50, 0, 'dollar')).toBe('better');
    expect(comparisonPillState(0, 0, 'dollar')).toBe('neutral');
  });
});
```

- [ ] **Step 2.2: Run test to verify it fails**

Run: `cd twins-dash && npx vitest run src/lib/scorecard/__tests__/kpi-comparison.test.ts`
Expected: fails — module not found.

- [ ] **Step 2.3: Write implementation**

Create `twins-dash/src/lib/scorecard/kpi-comparison.ts`:

```ts
export type KpiKind = 'percent' | 'dollar' | 'count';
export type PillState = 'better' | 'worse' | 'neutral';

export interface ComparisonOptions {
  lowerIsBetter?: boolean;
}

export function comparisonPillState(
  value: number,
  companyAvg: number | null,
  kind: KpiKind,
  opts: ComparisonOptions = {},
): PillState {
  if (companyAvg == null) return 'neutral';

  const diff = value - companyAvg;

  // within-tolerance check
  if (kind === 'percent') {
    if (Math.abs(diff) <= 2) return 'neutral';
  } else {
    // dollar and count: 5% tolerance
    if (companyAvg === 0) {
      if (value === 0) return 'neutral';
    } else {
      if (Math.abs(diff) / Math.abs(companyAvg) <= 0.05) return 'neutral';
    }
  }

  const rawBetter = diff > 0;
  const isBetter = opts.lowerIsBetter ? !rawBetter : rawBetter;
  return isBetter ? 'better' : 'worse';
}
```

- [ ] **Step 2.4: Run tests to verify pass**

Run: `cd twins-dash && npx vitest run src/lib/scorecard/__tests__/kpi-comparison.test.ts`
Expected: all tests pass.

- [ ] **Step 2.5: Commit**

```bash
git add twins-dash/src/lib/scorecard/
git commit -m "feat(scorecard): kpi-comparison utility with tests"
```

---

## Task 3: Disclaimer banner component

Reusable amber banner shown atop the scorecard. One-liner component.

**Files:**
- Create: `twins-dash/src/components/technician/scorecard/DisclaimerBanner.tsx`

- [ ] **Step 3.1: Implement component**

Create `twins-dash/src/components/technician/scorecard/DisclaimerBanner.tsx`:

```tsx
import { AlertTriangle } from 'lucide-react';

export function DisclaimerBanner() {
  return (
    <div
      role="note"
      className="flex items-start gap-3 rounded-2xl border border-amber-300/60 bg-amber-50 p-3 text-sm text-amber-900 sm:p-4"
    >
      <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
      <p className="leading-snug">
        These numbers are estimates and may shift. Final amounts are confirmed
        when admin runs payroll. Parts you have not entered will skew the math.
      </p>
    </div>
  );
}
```

- [ ] **Step 3.2: Commit**

```bash
git add twins-dash/src/components/technician/scorecard/DisclaimerBanner.tsx
git commit -m "feat(scorecard): disclaimer banner component"
```

---

## Task 4: ComparisonPill and TechScorecardKPIs

**Files:**
- Create: `twins-dash/src/components/technician/scorecard/ComparisonPill.tsx`
- Create: `twins-dash/src/components/technician/scorecard/TechScorecardKPIs.tsx`
- Modify: `twins-dash/src/lib/kpi-info.ts` (append any missing entries)

- [ ] **Step 4.1: Audit `kpi-info.ts`**

Open `twins-dash/src/lib/kpi-info.ts`. Verify entries exist for these keys:
`avgOpportunity`, `avgRepair`, `avgInstall`, `closingPercentage`, `callbackRate`, `membershipConversion`, `revenue`, `jobCount`.

If any missing, append using the existing pattern. Use exactly this copy (matches spec):

```ts
// Append to KPI_INFO object if missing; reference keys exactly as used below.
avgOpportunity: {
  label: 'Avg Opportunity',
  description: 'Average ticket on jobs where you had a chance to sell. Measures how well you close when given a chance.',
},
avgRepair: {
  label: 'Avg Repair',
  description: 'Typical size of your service calls.',
},
avgInstall: {
  label: 'Avg Install',
  description: 'Typical size of your install jobs.',
},
closingPercentage: {
  label: 'Closing %',
  description: 'How often you turn an opportunity into a sale.',
},
callbackRate: {
  label: 'Callback Rate',
  description: 'Percent of your jobs that needed a return visit. Lower is better.',
},
membershipConversion: {
  label: 'Membership Conversion %',
  description: 'How often you add a membership to a service call.',
},
revenue: {
  label: 'Revenue',
  description: 'Total revenue from your completed jobs in this period.',
},
jobCount: {
  label: 'Jobs',
  description: 'Number of jobs you completed in this period.',
},
```

- [ ] **Step 4.2: Implement ComparisonPill**

Create `twins-dash/src/components/technician/scorecard/ComparisonPill.tsx`:

```tsx
import { ArrowDown, ArrowUp, Minus } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { PillState } from '@/lib/scorecard/kpi-comparison';

interface Props {
  state: PillState;
  /** Short label like "vs company" */
  label?: string;
  /** Computed delta text to show (e.g. "+$120", "-3pp", "=") */
  deltaText?: string;
}

export function ComparisonPill({ state, label = 'vs company', deltaText }: Props) {
  const styles: Record<PillState, string> = {
    better: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    worse: 'bg-rose-100 text-rose-800 border-rose-200',
    neutral: 'bg-muted text-muted-foreground border-border',
  };
  const Icon = state === 'better' ? ArrowUp : state === 'worse' ? ArrowDown : Minus;
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium',
        styles[state],
      )}
    >
      <Icon className="h-3 w-3" />
      {deltaText ?? label}
    </span>
  );
}
```

- [ ] **Step 4.3: Implement TechScorecardKPIs**

Create `twins-dash/src/components/technician/scorecard/TechScorecardKPIs.tsx`:

```tsx
import {
  DollarSign,
  Briefcase,
  Target,
  Wrench,
  Home,
  Percent,
  RotateCcw,
  Award,
} from 'lucide-react';
import { MetricCard } from '@/components/dashboard/MetricCard';
import { comparisonPillState, type KpiKind } from '@/lib/scorecard/kpi-comparison';
import { ComparisonPill } from './ComparisonPill';
import { KPI_INFO } from '@/lib/kpi-info';

export interface TechScorecardValues {
  revenue: number;
  jobCount: number;
  avgOpportunity: number;
  avgRepair: number;
  avgInstall: number;
  closingPercentage: number;
  callbackRate: number;
  membershipConversion: number;
}

export interface CompanyAverages {
  revenue: number | null;
  jobCount: number | null;
  avgOpportunity: number | null;
  avgRepair: number | null;
  avgInstall: number | null;
  closingPercentage: number | null;
  callbackRate: number | null;
  membershipConversion: number | null;
}

const fmtCurrency = (n: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n || 0);
const fmtPercent = (n: number) => `${(n || 0).toFixed(1)}%`;
const fmtInt = (n: number) => `${Math.round(n || 0)}`;

interface TileSpec {
  key: keyof TechScorecardValues;
  kind: KpiKind;
  lowerIsBetter?: boolean;
  icon: typeof DollarSign;
  format: (n: number) => string;
}

const TILES: TileSpec[] = [
  { key: 'revenue',              kind: 'dollar',  icon: DollarSign, format: fmtCurrency },
  { key: 'jobCount',             kind: 'count',   icon: Briefcase,  format: fmtInt },
  { key: 'avgOpportunity',       kind: 'dollar',  icon: Target,     format: fmtCurrency },
  { key: 'avgRepair',            kind: 'dollar',  icon: Wrench,     format: fmtCurrency },
  { key: 'avgInstall',           kind: 'dollar',  icon: Home,       format: fmtCurrency },
  { key: 'closingPercentage',    kind: 'percent', icon: Percent,    format: fmtPercent },
  { key: 'callbackRate',         kind: 'percent', icon: RotateCcw,  format: fmtPercent, lowerIsBetter: true },
  { key: 'membershipConversion', kind: 'percent', icon: Award,      format: fmtPercent },
];

interface Props {
  values: TechScorecardValues;
  companyAverages: CompanyAverages;
}

export function TechScorecardKPIs({ values, companyAverages }: Props) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
      {TILES.map((t) => {
        const value = values[t.key];
        const avg = companyAverages[t.key];
        const info = KPI_INFO[t.key];
        const state = comparisonPillState(value, avg, t.kind, { lowerIsBetter: t.lowerIsBetter });
        return (
          <MetricCard
            key={t.key}
            title={info?.label ?? t.key}
            value={t.format(value)}
            icon={<t.icon className="h-4 w-4" />}
            infoText={info?.description}
            footer={<ComparisonPill state={state} />}
          />
        );
      })}
    </div>
  );
}
```

Note: this uses the existing `MetricCard` component. Open `twins-dash/src/components/dashboard/MetricCard.tsx` in advance to confirm props include `title`, `value`, `icon`, `infoText`, and a slot for `footer`. If the prop names differ, adapt the call above to match. If there is no `footer` slot, pass the pill as a React child or extend MetricCard in a small follow-up commit inside this task.

- [ ] **Step 4.4: Verify MetricCard prop compatibility**

Run: `grep -nE "interface.*Props|title|value|icon|footer|infoText" twins-dash/src/components/dashboard/MetricCard.tsx | head -20`
Expected: see existing prop names. If `footer` slot is missing, add one:

```tsx
// In MetricCard props interface
footer?: React.ReactNode;
// In MetricCard JSX, render after value:
{footer ? <div className="mt-2">{footer}</div> : null}
```

- [ ] **Step 4.5: Commit**

```bash
git add twins-dash/src/components/technician/scorecard/ComparisonPill.tsx \
        twins-dash/src/components/technician/scorecard/TechScorecardKPIs.tsx \
        twins-dash/src/lib/kpi-info.ts \
        twins-dash/src/components/dashboard/MetricCard.tsx
git commit -m "feat(scorecard): KPI grid with vs-company comparison pills"
```

---

## Task 5: Past paystubs hook + component

Last 4 finalized weeks, read from `payroll_commissions` via the existing `v_my_commissions` view grouped by run.

**Files:**
- Create: `twins-dash/src/hooks/tech/usePastPaystubs.ts`
- Create: `twins-dash/src/components/technician/scorecard/PastPaystubs.tsx`

- [ ] **Step 5.1: Implement hook**

Create `twins-dash/src/hooks/tech/usePastPaystubs.ts`:

```ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export interface PastPaystub {
  run_id: number;
  week_start: string;
  week_end: string;
  locked_at: string | null;
  job_count: number;
  total_commission: number;
}

export function usePastPaystubs(limit = 4) {
  return useQuery({
    queryKey: ['my_past_paystubs', limit],
    queryFn: async (): Promise<PastPaystub[]> => {
      // v_my_commissions already exposes week_start, week_end, locked_at, total
      const { data, error } = await supabase
        .from('v_my_commissions')
        .select('run_id, week_start, week_end, locked_at, total')
        .not('locked_at', 'is', null)
        .order('week_start', { ascending: false });
      if (error) throw error;

      // Group by run_id client-side
      const byRun = new Map<number, PastPaystub>();
      for (const row of (data ?? []) as any[]) {
        const id = row.run_id as number;
        const existing = byRun.get(id);
        if (existing) {
          existing.total_commission += Number(row.total ?? 0);
          existing.job_count += 1;
        } else {
          byRun.set(id, {
            run_id: id,
            week_start: row.week_start,
            week_end: row.week_end,
            locked_at: row.locked_at,
            job_count: 1,
            total_commission: Number(row.total ?? 0),
          });
        }
      }
      return Array.from(byRun.values()).slice(0, limit);
    },
    staleTime: 60_000,
  });
}
```

- [ ] **Step 5.2: Implement component**

Create `twins-dash/src/components/technician/scorecard/PastPaystubs.tsx`:

```tsx
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePastPaystubs } from '@/hooks/tech/usePastPaystubs';

const fmtUSD = (n: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n || 0);
const fmtDate = (s: string) =>
  new Date(s + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

export function PastPaystubs() {
  const { data, isLoading } = usePastPaystubs(4);

  return (
    <Card className="rounded-2xl">
      <CardHeader>
        <CardTitle className="text-base font-semibold">Past paystubs</CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <p className="text-sm text-muted-foreground">Loading…</p>
        ) : !data || data.length === 0 ? (
          <p className="text-sm text-muted-foreground">No finalized weeks yet.</p>
        ) : (
          <ul className="divide-y">
            {data.map((p) => (
              <li key={p.run_id} className="flex items-center justify-between py-3">
                <div>
                  <div className="text-sm font-medium">
                    {fmtDate(p.week_start)} – {fmtDate(p.week_end)}
                  </div>
                  <div className="text-xs text-muted-foreground">{p.job_count} jobs</div>
                </div>
                <div className="text-right text-sm font-semibold tabular-nums">
                  {fmtUSD(p.total_commission)}
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 5.3: Commit**

```bash
git add twins-dash/src/hooks/tech/usePastPaystubs.ts \
        twins-dash/src/components/technician/scorecard/PastPaystubs.tsx
git commit -m "feat(scorecard): past paystubs (last 4 finalized weeks)"
```

---

## Task 6: `finish-tech-week` edge function and hook

Transitions every `draft` job in a run to `submitted` for the current tech in one transaction. Blocks if any `part_requests` are still `pending` on those jobs.

**Files:**
- Create: `twins-dash/supabase/functions/finish-tech-week/index.ts`
- Create: `twins-dash/src/hooks/tech/useFinishTechWeek.ts`

- [ ] **Step 6.1: Write edge function**

Create `twins-dash/supabase/functions/finish-tech-week/index.ts`:

```ts
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response('ok', {
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
        'Access-Control-Allow-Methods': 'POST, OPTIONS',
      },
    });
  }

  const authHeader = req.headers.get('Authorization') ?? '';
  const token = authHeader.replace('Bearer ', '');

  const supabase = createClient(
    Deno.env.get('SUPABASE_URL')!,
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,
    { global: { headers: { Authorization: `Bearer ${token}` } } },
  );

  const { data: userData, error: userErr } = await supabase.auth.getUser(token);
  if (userErr || !userData?.user) {
    return new Response(JSON.stringify({ error: 'unauthorized' }), { status: 401 });
  }

  const { run_id } = await req.json().catch(() => ({}));
  if (!run_id || typeof run_id !== 'number') {
    return new Response(JSON.stringify({ error: 'run_id required' }), { status: 400 });
  }

  // Resolve tech identity
  const { data: techRow } = await supabase
    .from('payroll_techs')
    .select('id,name')
    .eq('auth_user_id', userData.user.id)
    .single();
  if (!techRow) {
    return new Response(JSON.stringify({ error: 'not a technician' }), { status: 403 });
  }

  // Block if any pending part_requests on this tech's jobs in this run
  const { data: pending, error: prErr } = await supabase
    .from('part_requests')
    .select('id, job_id, payroll_jobs!inner(run_id, owner_tech)')
    .eq('status', 'pending')
    .eq('payroll_jobs.run_id', run_id)
    .eq('payroll_jobs.owner_tech', techRow.name);
  if (prErr) {
    return new Response(JSON.stringify({ error: prErr.message }), { status: 500 });
  }
  if (pending && pending.length > 0) {
    return new Response(
      JSON.stringify({ error: 'pending_parts', pending_count: pending.length }),
      { status: 409 },
    );
  }

  // Transition all draft → submitted for this tech in this run
  const { data: updated, error: upErr } = await supabase
    .from('payroll_jobs')
    .update({ submission_status: 'submitted' })
    .eq('run_id', run_id)
    .eq('owner_tech', techRow.name)
    .eq('submission_status', 'draft')
    .is('deleted_at', null)
    .select('id');
  if (upErr) {
    return new Response(JSON.stringify({ error: upErr.message }), { status: 500 });
  }

  return new Response(
    JSON.stringify({ ok: true, submitted_count: updated?.length ?? 0 }),
    {
      status: 200,
      headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
    },
  );
});
```

- [ ] **Step 6.2: Deploy edge function**

Run: `cd twins-dash && npx supabase functions deploy finish-tech-week --no-verify-jwt=false`
Expected: deploys. If using MCP: `mcp__a13384b5-...__deploy_edge_function name=finish-tech-week files=[{path: 'index.ts', content: <above>}]`

- [ ] **Step 6.3: Write hook**

Create `twins-dash/src/hooks/tech/useFinishTechWeek.ts`:

```ts
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export function useFinishTechWeek() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (run_id: number) => {
      const { data, error } = await supabase.functions.invoke('finish-tech-week', {
        body: { run_id },
      });
      if (error) {
        // Surface pending-parts blocker with a stable code
        const ctx = (error as any).context;
        if (ctx?.pending_count) {
          const e = new Error(`pending_parts:${ctx.pending_count}`);
          (e as any).code = 'pending_parts';
          throw e;
        }
        throw error;
      }
      return data as { ok: boolean; submitted_count: number };
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my_jobs'] });
      qc.invalidateQueries({ queryKey: ['my_scorecard'] });
      qc.invalidateQueries({ queryKey: ['my_paystub'] });
    },
  });
}
```

- [ ] **Step 6.4: Commit**

```bash
git add twins-dash/supabase/functions/finish-tech-week/ twins-dash/src/hooks/tech/useFinishTechWeek.ts
git commit -m "feat(tech-dash): finish-tech-week edge function + hook"
```

---

## Task 7: CommissionTracker component

Weekly tracker card. Tech-interactive mode (view/edit own data) and admin-readonly mode (no mutations, deep-link to `/payroll/run`). Uses existing hooks (`useMyJobs`, `useMyJobDetail`, etc.) for the tech view; for the admin view, queries `payroll_jobs` directly filtered by tech name.

**Files:**
- Create: `twins-dash/src/components/technician/scorecard/CommissionTracker.tsx`

- [ ] **Step 7.1: Inspect reusable components**

Open these files to confirm prop shapes. You will call them from CommissionTracker:

```
twins-dash/src/components/tech/JobRow.tsx
twins-dash/src/components/tech/JobStatusBadge.tsx
twins-dash/src/components/tech/PartsPickerModal.tsx
twins-dash/src/components/tech/RequestPartAddModal.tsx
twins-dash/src/components/tech/SubmitConfirmModal.tsx
twins-dash/src/hooks/tech/useMyJobs.ts
twins-dash/src/hooks/tech/useMyJobDetail.ts
twins-dash/src/hooks/tech/useSubmitJobParts.ts
twins-dash/src/hooks/tech/useRequestPartAdd.ts
```

- [ ] **Step 7.2: Implement tracker (tech-interactive + admin read-only)**

Create `twins-dash/src/components/technician/scorecard/CommissionTracker.tsx`:

```tsx
import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ChevronLeft, ChevronRight, ExternalLink, Loader2 } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { JobStatusBadge } from '@/components/tech/JobStatusBadge';
import { SubmitConfirmModal } from '@/components/tech/SubmitConfirmModal';
import { useMyJobs } from '@/hooks/tech/useMyJobs';
import { useFinishTechWeek } from '@/hooks/tech/useFinishTechWeek';
import { toast } from '@/components/ui/sonner';

type Mode = 'tech' | 'admin';

interface Props {
  mode: Mode;
  /** Admin view requires explicit tech name/id; tech view resolves via auth. */
  technicianName?: string;
}

export function CommissionTracker({ mode, technicianName }: Props) {
  const navigate = useNavigate();
  // Tech view: pulls own jobs; admin view would pull by technicianName.
  // For v1, admin view in this card reads a slimmed hook that filters
  // v_my_jobs-equivalent for admin (see follow-up: if useMyJobs is
  // tech-only, add a parallel useTechJobsAdmin(technicianName) hook in
  // this same task with the same return shape).
  const { data: jobs, isLoading } = useMyJobs();

  const [offset, setOffset] = useState(0); // 0 = current week, -1 prev, etc.
  const [confirmOpen, setConfirmOpen] = useState(false);

  const finishWeek = useFinishTechWeek();

  // Group by run_id; pick the run offset.
  const runs = useMemo(() => {
    if (!jobs) return [];
    const byRun = new Map<number, { run_id: number; week_start: string; week_end: string; is_week_locked: boolean; jobs: any[] }>();
    for (const j of jobs) {
      const id = (j as any).run_id;
      if (!id) continue;
      if (!byRun.has(id)) {
        byRun.set(id, {
          run_id: id,
          week_start: (j as any).week_start,
          week_end: (j as any).week_end,
          is_week_locked: !!(j as any).is_week_locked,
          jobs: [],
        });
      }
      byRun.get(id)!.jobs.push(j);
    }
    return Array.from(byRun.values()).sort((a, b) =>
      a.week_start < b.week_start ? 1 : -1,
    );
  }, [jobs]);

  const run = runs[Math.min(Math.max(-offset, 0), runs.length - 1)];

  if (isLoading) {
    return (
      <Card className="rounded-2xl">
        <CardContent className="py-10 text-center text-sm text-muted-foreground">
          <Loader2 className="mx-auto mb-2 h-4 w-4 animate-spin" />
          Loading jobs…
        </CardContent>
      </Card>
    );
  }
  if (!run) {
    return (
      <Card className="rounded-2xl">
        <CardContent className="py-10 text-center text-sm text-muted-foreground">
          No jobs yet.
        </CardContent>
      </Card>
    );
  }

  const draftCount = run.jobs.filter((j: any) => j.submission_status === 'draft').length;
  const readyCount = run.jobs.length - draftCount;
  const allReady = draftCount === 0;

  const onFinish = async () => {
    try {
      const res = await finishWeek.mutateAsync(run.run_id);
      toast.success(`Submitted ${res.submitted_count} jobs for this week.`);
      setConfirmOpen(false);
    } catch (e: any) {
      if (e?.code === 'pending_parts') {
        toast.error('Cannot finish: custom parts are still awaiting price.');
      } else {
        toast.error(e?.message ?? 'Failed to submit week');
      }
    }
  };

  return (
    <Card className="rounded-2xl">
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle className="text-base font-semibold">Commission Tracker</CardTitle>
          <p className="text-xs text-muted-foreground">
            Week of {run.week_start} to {run.week_end}
          </p>
        </div>
        <div className="flex items-center gap-1">
          <Button size="icon" variant="ghost" onClick={() => setOffset(offset - 1)} aria-label="previous week">
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <Button size="icon" variant="ghost" disabled={offset === 0} onClick={() => setOffset(offset + 1)} aria-label="next week">
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="text-sm text-muted-foreground">
          {readyCount} of {run.jobs.length} jobs ready
        </div>

        <ul className="divide-y">
          {run.jobs.map((j: any) => (
            <li
              key={j.id}
              className="flex items-center justify-between gap-3 py-3 text-sm cursor-pointer hover:bg-muted/40 rounded-md px-2 -mx-2"
              onClick={() => {
                if (mode === 'tech') {
                  navigate(`/tech/jobs/${j.id}`);
                }
              }}
            >
              <div className="min-w-0">
                <div className="font-medium">#{j.hcp_job_number ?? j.id}</div>
                <div className="truncate text-xs text-muted-foreground">
                  {j.customer_display ?? ''}
                </div>
              </div>
              <JobStatusBadge status={j.submission_status ?? 'draft'} />
            </li>
          ))}
        </ul>

        {mode === 'admin' ? (
          <Button
            variant="outline"
            className="w-full"
            onClick={() => navigate(`/payroll/run?run_id=${run.run_id}`)}
          >
            <ExternalLink className="mr-2 h-4 w-4" />
            Open in payroll view
          </Button>
        ) : run.is_week_locked ? (
          <Alert>
            <AlertDescription>
              This week is locked. Use Request Modification on a job below to propose changes.
            </AlertDescription>
          </Alert>
        ) : (
          <Button
            className="w-full sticky bottom-4"
            disabled={!allReady || finishWeek.isPending}
            onClick={() => setConfirmOpen(true)}
          >
            {finishWeek.isPending ? 'Submitting…' : allReady ? 'Finish Week' : `${draftCount} jobs still draft`}
          </Button>
        )}

        <SubmitConfirmModal
          open={confirmOpen}
          onOpenChange={setConfirmOpen}
          title="Finish this week?"
          body="Finishing this week submits your entries for admin payroll. To change anything after this point, submit a modification request."
          confirmLabel="Finish Week"
          onConfirm={onFinish}
          loading={finishWeek.isPending}
        />
      </CardContent>
    </Card>
  );
}
```

Notes for the implementing engineer:
- `SubmitConfirmModal` may have different prop names than used above. Open it and adapt.
- `useMyJobs` returns the tech's jobs from `v_my_jobs`. For admin-mode, a small follow-up hook `useTechJobsAdmin(technicianName)` can be added in this task that selects from `payroll_jobs` filtered by owner_tech, returning the same shape. Keep it in the same component file if trivial, or a new file under `src/hooks/admin/`.

- [ ] **Step 7.3: Verify SubmitConfirmModal props**

Run: `grep -nE "interface.*Props|export" twins-dash/src/components/tech/SubmitConfirmModal.tsx | head`
Expected: confirm props. Adapt the call in step 7.2 if names differ.

- [ ] **Step 7.4: Commit**

```bash
git add twins-dash/src/components/technician/scorecard/CommissionTracker.tsx
git commit -m "feat(scorecard): role-aware commission tracker"
```

---

## Task 8: Modification request: migration-adjacent edge function + hook + dialog

**Files:**
- Create: `twins-dash/supabase/functions/submit-modification-request/index.ts`
- Create: `twins-dash/src/hooks/tech/useSubmitModificationRequest.ts`
- Create: `twins-dash/src/hooks/tech/useMyModificationRequests.ts`
- Create: `twins-dash/src/components/technician/scorecard/ModificationRequestDialog.tsx`

- [ ] **Step 8.1: Write submit edge function**

Create `twins-dash/supabase/functions/submit-modification-request/index.ts`:

```ts
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response('ok', {
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
        'Access-Control-Allow-Methods': 'POST, OPTIONS',
      },
    });
  }

  const authHeader = req.headers.get('Authorization') ?? '';
  const token = authHeader.replace('Bearer ', '');
  const supabase = createClient(
    Deno.env.get('SUPABASE_URL')!,
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,
    { global: { headers: { Authorization: `Bearer ${token}` } } },
  );

  const { data: userData, error: userErr } = await supabase.auth.getUser(token);
  if (userErr || !userData?.user) {
    return new Response(JSON.stringify({ error: 'unauthorized' }), { status: 401 });
  }

  const body = await req.json().catch(() => ({}));
  const { job_id, reasons, notes } = body as { job_id?: number; reasons?: string[]; notes?: string };
  if (!job_id || !Array.isArray(reasons) || reasons.length === 0 || !notes) {
    return new Response(JSON.stringify({ error: 'job_id, reasons[], notes required' }), { status: 400 });
  }

  const { data: techRow } = await supabase
    .from('payroll_techs')
    .select('id,name')
    .eq('auth_user_id', userData.user.id)
    .single();
  if (!techRow) {
    return new Response(JSON.stringify({ error: 'not a technician' }), { status: 403 });
  }

  // Verify ownership + submitted/locked state
  const { data: job } = await supabase
    .from('payroll_jobs')
    .select('id, owner_tech, run_id, submission_status')
    .eq('id', job_id)
    .single();
  if (!job || job.owner_tech !== techRow.name) {
    return new Response(JSON.stringify({ error: 'not owner' }), { status: 403 });
  }
  if (!['submitted', 'locked'].includes((job as any).submission_status)) {
    return new Response(JSON.stringify({ error: 'job not submitted' }), { status: 409 });
  }

  // Reject if an open modification request already exists for this job
  const { data: existing } = await supabase
    .from('modification_requests')
    .select('id')
    .eq('job_id', job_id)
    .eq('status', 'pending')
    .maybeSingle();
  if (existing) {
    return new Response(JSON.stringify({ error: 'open_request_exists' }), { status: 409 });
  }

  const { data: inserted, error: insErr } = await supabase
    .from('modification_requests')
    .insert({
      requested_by: userData.user.id,
      technician_id: techRow.id,
      job_id,
      run_id: (job as any).run_id ?? null,
      reasons,
      notes,
    })
    .select('id')
    .single();
  if (insErr) {
    return new Response(JSON.stringify({ error: insErr.message }), { status: 500 });
  }

  return new Response(JSON.stringify({ request_id: inserted!.id }), {
    status: 200,
    headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
  });
});
```

- [ ] **Step 8.2: Deploy edge function**

Run: `cd twins-dash && npx supabase functions deploy submit-modification-request`

- [ ] **Step 8.3: Write hooks**

Create `twins-dash/src/hooks/tech/useSubmitModificationRequest.ts`:

```ts
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export function useSubmitModificationRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: { job_id: number; reasons: string[]; notes: string }) => {
      const { data, error } = await supabase.functions.invoke('submit-modification-request', {
        body: args,
      });
      if (error) throw error;
      return data as { request_id: string };
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my_modification_requests'] });
    },
  });
}
```

Create `twins-dash/src/hooks/tech/useMyModificationRequests.ts`:

```ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export function useMyModificationRequests() {
  return useQuery({
    queryKey: ['my_modification_requests'],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('modification_requests')
        .select('id, job_id, status, reasons, notes, resolved_at, resolution_notes, created_at')
        .order('created_at', { ascending: false });
      if (error) throw error;
      return data ?? [];
    },
    staleTime: 30_000,
  });
}
```

- [ ] **Step 8.4: Write the dialog component**

Create `twins-dash/src/components/technician/scorecard/ModificationRequestDialog.tsx`:

```tsx
import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/sonner';
import { useSubmitModificationRequest } from '@/hooks/tech/useSubmitModificationRequest';

const REASON_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'forgot_parts',     label: 'Forgot parts' },
  { value: 'wrong_part',       label: 'Wrong part' },
  { value: 'wrong_quantity',   label: 'Wrong quantity' },
  { value: 'wrong_job_tag',    label: 'Wrong job tag' },
  { value: 'customer_dispute', label: 'Customer dispute' },
  { value: 'other',            label: 'Other' },
];

interface Props {
  open: boolean;
  onOpenChange: (v: boolean) => void;
  jobId: number;
  jobLabel: string;
}

export function ModificationRequestDialog({ open, onOpenChange, jobId, jobLabel }: Props) {
  const [reasons, setReasons] = useState<string[]>([]);
  const [notes, setNotes] = useState('');
  const submit = useSubmitModificationRequest();

  const toggle = (v: string) =>
    setReasons((r) => (r.includes(v) ? r.filter((x) => x !== v) : [...r, v]));

  const canSubmit = reasons.length > 0 && notes.trim().length > 0 && !submit.isPending;

  const handleSubmit = async () => {
    try {
      await submit.mutateAsync({ job_id: jobId, reasons, notes: notes.trim() });
      toast.success('Request submitted. Supervisor will review.');
      setReasons([]);
      setNotes('');
      onOpenChange(false);
    } catch (e: any) {
      const msg = e?.context?.error === 'open_request_exists'
        ? 'A request for this job is already open.'
        : e?.message ?? 'Failed to submit request';
      toast.error(msg);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Request Modification — Job {jobLabel}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div>
            <Label className="mb-2 block text-sm font-medium">What needs to change?</Label>
            <div className="grid grid-cols-2 gap-2">
              {REASON_OPTIONS.map((r) => (
                <label key={r.value} className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={reasons.includes(r.value)}
                    onCheckedChange={() => toggle(r.value)}
                  />
                  {r.label}
                </label>
              ))}
            </div>
          </div>
          <div>
            <Label htmlFor="mod-notes" className="mb-2 block text-sm font-medium">
              Details (required)
            </Label>
            <Textarea
              id="mod-notes"
              rows={4}
              placeholder="Describe exactly what needs to change. For example: forgot 1x 25SSWDE on Job 12345."
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
            />
          </div>
        </div>
        <DialogFooter>
          <Button variant="ghost" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button onClick={handleSubmit} disabled={!canSubmit}>
            {submit.isPending ? 'Submitting…' : 'Submit Request'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
```

- [ ] **Step 8.5: Commit**

```bash
git add twins-dash/supabase/functions/submit-modification-request/ \
        twins-dash/src/hooks/tech/useSubmitModificationRequest.ts \
        twins-dash/src/hooks/tech/useMyModificationRequests.ts \
        twins-dash/src/components/technician/scorecard/ModificationRequestDialog.tsx
git commit -m "feat(tech-dash): modification request submit flow"
```

---

## Task 9: WeekSummary component (post-finish read-only)

Shows commission total and per-job commission rows. Only entry point for "Request Modification" on submitted/locked jobs.

**Files:**
- Create: `twins-dash/src/components/technician/scorecard/WeekSummary.tsx`

- [ ] **Step 9.1: Implement**

Create `twins-dash/src/components/technician/scorecard/WeekSummary.tsx`:

```tsx
import { useMemo, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { supabase } from '@/integrations/supabase/client';
import { useQuery } from '@tanstack/react-query';
import { ModificationRequestDialog } from './ModificationRequestDialog';
import { useMyModificationRequests } from '@/hooks/tech/useMyModificationRequests';

const fmtUSD = (n: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n || 0);

function useWeekCommissions(runId: number | null) {
  return useQuery({
    queryKey: ['v_my_commissions', runId],
    enabled: !!runId,
    queryFn: async () => {
      const { data, error } = await supabase
        .from('v_my_commissions')
        .select('id, job_id, total, run_id')
        .eq('run_id', runId!);
      if (error) throw error;
      return data ?? [];
    },
  });
}

interface Props {
  runId: number;
  jobs: Array<{ id: number; hcp_job_number: string | null; customer_display: string | null }>;
}

export function WeekSummary({ runId, jobs }: Props) {
  const { data: commissions } = useWeekCommissions(runId);
  const { data: modReqs } = useMyModificationRequests();
  const [modJob, setModJob] = useState<{ id: number; label: string } | null>(null);

  const total = useMemo(
    () => (commissions ?? []).reduce((s, r: any) => s + Number(r.total ?? 0), 0),
    [commissions],
  );
  const byJob = useMemo(() => {
    const m = new Map<number, number>();
    for (const r of commissions ?? []) {
      m.set((r as any).job_id, (m.get((r as any).job_id) ?? 0) + Number((r as any).total ?? 0));
    }
    return m;
  }, [commissions]);

  const openReqJobIds = new Set(
    (modReqs ?? []).filter((r: any) => r.status === 'pending').map((r: any) => r.job_id),
  );

  return (
    <Card className="rounded-2xl">
      <CardHeader>
        <CardTitle className="text-base font-semibold">Week Summary</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="rounded-xl bg-primary/5 p-4 text-center">
          <div className="text-xs uppercase text-muted-foreground">You earned this week</div>
          <div className="mt-1 text-3xl font-bold text-primary tabular-nums">{fmtUSD(total)}</div>
        </div>
        <ul className="divide-y">
          {jobs.map((j) => {
            const amt = byJob.get(j.id) ?? 0;
            const hasOpen = openReqJobIds.has(j.id);
            return (
              <li key={j.id} className="flex items-center justify-between gap-3 py-3 text-sm">
                <div className="min-w-0">
                  <div className="font-medium">#{j.hcp_job_number ?? j.id}</div>
                  <div className="truncate text-xs text-muted-foreground">
                    {j.customer_display ?? ''}
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="text-sm font-semibold tabular-nums">{fmtUSD(amt)}</div>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={hasOpen}
                    onClick={() =>
                      setModJob({ id: j.id, label: String(j.hcp_job_number ?? j.id) })
                    }
                  >
                    {hasOpen ? 'Pending' : 'Request Mod'}
                  </Button>
                </div>
              </li>
            );
          })}
        </ul>
      </CardContent>

      {modJob && (
        <ModificationRequestDialog
          open={!!modJob}
          onOpenChange={(v) => { if (!v) setModJob(null); }}
          jobId={modJob.id}
          jobLabel={modJob.label}
        />
      )}
    </Card>
  );
}
```

- [ ] **Step 9.2: Commit**

```bash
git add twins-dash/src/components/technician/scorecard/WeekSummary.tsx
git commit -m "feat(scorecard): week summary with per-job commission + mod request button"
```

---

## Task 10: Rewrite `TechnicianView.tsx` as the unified scorecard

Replaces the current page's chart blocks with the new layout: header, disclaimer, KPI grid, commission tracker (or week summary), past paystubs.

**Files:**
- Modify: `twins-dash/src/pages/TechnicianView.tsx`

- [ ] **Step 10.1: Read the current file**

Run: open `twins-dash/src/pages/TechnicianView.tsx`. Note which data the page currently resolves (from `useTechnicianData(id, dateRange)`): `technician`, `jobs`, `kpi`, `companyKPI` via `useServiceTitanKPI(dateRange)`. Keep those sources; replace the rendered layout.

- [ ] **Step 10.2: Implement the new page**

Replace the contents of `twins-dash/src/pages/TechnicianView.tsx` with:

```tsx
import { useMemo, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { DateRangePicker } from '@/components/dashboard/DateRangePicker';
import { DateRange } from 'react-day-picker';
import { startOfYear } from 'date-fns';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

import { useTechnicianData } from '@/hooks/use-technician-data';
import { useServiceTitanKPI } from '@/hooks/use-servicetitan-kpi';
import { useAuth } from '@/contexts/AuthContext';

import { DisclaimerBanner } from '@/components/technician/scorecard/DisclaimerBanner';
import { TechScorecardKPIs } from '@/components/technician/scorecard/TechScorecardKPIs';
import { CommissionTracker } from '@/components/technician/scorecard/CommissionTracker';
import { PastPaystubs } from '@/components/technician/scorecard/PastPaystubs';

// UUID validation
const isValidUUID = (s: string) =>
  /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(s);

export default function TechnicianView() {
  const { id } = useParams();
  const { userId, technicianId: ownTechnicianId, technicianName, isAdmin, isFieldSupervisor } =
    useAuth() as any; // rely on existing context shape

  const [dateRange, setDateRange] = useState<DateRange | undefined>({
    from: startOfYear(new Date()),
    to: new Date(),
  });

  const isInvalidId = !id || id === ':id' || id === 'undefined' || !isValidUUID(id);
  const effectiveId = isInvalidId ? (ownTechnicianId ?? '') : id!;
  const viewingOwn = effectiveId && effectiveId === ownTechnicianId;

  const { technician, jobs, kpi, isLoading } = useTechnicianData(effectiveId, dateRange);
  const { data: companyKPI } = useServiceTitanKPI(dateRange);

  const values = useMemo(() => ({
    revenue:              Number(kpi?.totalRevenue ?? 0),
    jobCount:             Number(kpi?.jobCount ?? 0),
    avgOpportunity:       Number(kpi?.opportunityAverage ?? 0),
    avgRepair:            Number(kpi?.avgRepair ?? 0),
    avgInstall:           Number(kpi?.avgInstall ?? 0),
    closingPercentage:    Number(kpi?.closingPercentage ?? 0),
    callbackRate:         Number(kpi?.callbackRate ?? 0),
    membershipConversion: Number(kpi?.membershipConversion ?? 0),
  }), [kpi]);

  const companyAverages = useMemo(() => ({
    revenue:              companyKPI?.totalRevenue ?? null,
    jobCount:             companyKPI?.jobCount ?? null,
    avgOpportunity:       companyKPI?.opportunityAverage ?? null,
    avgRepair:            companyKPI?.avgRepair ?? null,
    avgInstall:           companyKPI?.avgInstall ?? null,
    closingPercentage:    companyKPI?.closingPercentage ?? null,
    callbackRate:         companyKPI?.callbackRate ?? null,
    membershipConversion: companyKPI?.membershipConversion ?? null,
  }), [companyKPI]);

  if (isLoading && !technician) {
    return <div className="p-6 text-sm text-muted-foreground">Loading…</div>;
  }

  return (
    <div className="space-y-5 p-3 sm:space-y-6 sm:p-6">
      {/* Header */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-primary">
            {viewingOwn ? 'Your scorecard' : `${technician?.name ?? 'Tech'} scorecard`}
          </h1>
          <p className="text-sm text-muted-foreground">
            {viewingOwn
              ? 'Your jobs, KPIs, and commission tracker.'
              : `Viewing as ${isAdmin ? 'admin' : isFieldSupervisor ? 'field supervisor' : 'viewer'}.`}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <DateRangePicker value={dateRange} onChange={setDateRange} />
        </div>
      </div>

      <DisclaimerBanner />

      {/* KPIs */}
      <TechScorecardKPIs values={values} companyAverages={companyAverages} />

      {/* Commission tracker or admin-readonly variant */}
      <CommissionTracker
        mode={viewingOwn ? 'tech' : 'admin'}
        technicianName={technician?.name ?? undefined}
      />

      {/* Past paystubs: only for tech viewing own. Admin uses /payroll/history. */}
      {viewingOwn && <PastPaystubs />}

      {/* Admin bonus link */}
      {!viewingOwn && (isAdmin || isFieldSupervisor) && (
        <Card className="rounded-2xl">
          <CardContent className="flex items-center justify-between p-4">
            <div className="text-sm">Open tech request queue</div>
            <Button asChild variant="outline" size="sm">
              <Link to="/admin/tech-requests">
                <ExternalLink className="mr-1 h-4 w-4" /> Tech Requests
              </Link>
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
```

Notes:
- `useTechnicianData` returns a `kpi` object. Open that hook (`src/hooks/use-technician-data.ts`) to confirm the exact property names. If a field is named differently (for example `avgRepairTicket` instead of `avgRepair`), update the mapping in `values`. Same for `companyKPI` from `useServiceTitanKPI`. Do not invent field names — align with the source.
- `useAuth` context: confirm the exact property names for `userId`, `technicianId`, `technicianName`, `isAdmin`, `isFieldSupervisor`. If `technicianId` is not exposed, add a selector to the auth context that maps `auth.uid()` via the existing `current_technician_id()` RPC.

- [ ] **Step 10.3: Type-check and build**

Run: `cd twins-dash && npx tsc --noEmit`
Expected: no type errors in `TechnicianView.tsx`. If any field names are wrong, fix in this task and rerun.

- [ ] **Step 10.4: Commit**

```bash
git add twins-dash/src/pages/TechnicianView.tsx
git commit -m "feat(scorecard): unify TechnicianView as role-aware scorecard"
```

---

## Task 11: Conditional swap — tracker vs. week summary

If the current week already has all jobs `submitted` (or if `is_week_locked`), TechnicianView should render `WeekSummary` under the tracker. Simpler: the tracker always shows the job list, and a WeekSummary card appears below when the current run is in submitted/locked state.

**Files:**
- Modify: `twins-dash/src/pages/TechnicianView.tsx`
- Modify: `twins-dash/src/components/technician/scorecard/CommissionTracker.tsx` (expose the current run for the parent)

- [ ] **Step 11.1: Expose current-run from tracker**

Refactor `CommissionTracker` to accept an optional `onRunChange(run)` callback called with the displayed run object (`{run_id, is_week_locked, status: 'draft'|'submitted'|'locked', jobs}`). The parent can then render `<WeekSummary>` when `status !== 'draft'`.

Add after the `run` selection inside CommissionTracker:

```tsx
useEffect(() => {
  if (run && onRunChange) {
    const anyDraft = run.jobs.some((j: any) => j.submission_status === 'draft');
    const allLocked = run.jobs.every((j: any) => j.submission_status === 'locked');
    const status = allLocked ? 'locked' : anyDraft ? 'draft' : 'submitted';
    onRunChange({ ...run, status });
  }
}, [run, onRunChange]);
```

Don't forget to import `useEffect` and to add the prop to the `Props` interface.

- [ ] **Step 11.2: Render WeekSummary in TechnicianView when not draft**

In `TechnicianView.tsx`:

```tsx
const [currentRun, setCurrentRun] = useState<any>(null);

// ...
<CommissionTracker
  mode={viewingOwn ? 'tech' : 'admin'}
  technicianName={technician?.name ?? undefined}
  onRunChange={setCurrentRun}
/>

{currentRun && currentRun.status !== 'draft' && (
  <WeekSummary
    runId={currentRun.run_id}
    jobs={currentRun.jobs}
  />
)}
```

Add the `WeekSummary` import.

- [ ] **Step 11.3: Commit**

```bash
git add twins-dash/src/components/technician/scorecard/CommissionTracker.tsx \
        twins-dash/src/pages/TechnicianView.tsx
git commit -m "feat(scorecard): show week summary alongside tracker post-submit"
```

---

## Task 12: `/admin/tech-requests` — admin queue page + hooks + resolve functions

Unified list of `part_requests` and `modification_requests`. Admin can resolve both.

**Files:**
- Create: `twins-dash/src/pages/admin/TechRequests.tsx`
- Create: `twins-dash/src/components/admin/RequestCard.tsx`
- Create: `twins-dash/src/hooks/admin/useAdminTechRequests.ts`
- Create: `twins-dash/src/hooks/admin/useResolvePartRequest.ts`
- Create: `twins-dash/src/hooks/admin/useResolveModificationRequest.ts`
- Create: `twins-dash/supabase/functions/resolve-modification-request/index.ts`

- [ ] **Step 12.1: Write unified query hook**

Create `twins-dash/src/hooks/admin/useAdminTechRequests.ts`:

```ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export type RequestRow =
  | {
      kind: 'price';
      id: string;
      status: 'pending' | 'added' | 'rejected';
      part_name_text: string;
      notes: string | null;
      job_id: number;
      technician_id: number;
      technician_name: string;
      created_at: string;
    }
  | {
      kind: 'modification';
      id: string;
      status: 'pending' | 'resolved' | 'rejected';
      reasons: string[];
      notes: string;
      job_id: number;
      run_id: number | null;
      technician_id: number;
      technician_name: string;
      created_at: string;
      resolution_notes: string | null;
    };

export interface Filters {
  status?: 'open' | 'resolved' | 'all';
  technicianId?: number | null;
  type?: 'price' | 'modification' | 'all';
}

export function useAdminTechRequests(filters: Filters = {}) {
  const statusSet =
    filters.status === 'open' ? ['pending']
    : filters.status === 'resolved' ? ['added', 'rejected', 'resolved']
    : null;

  return useQuery({
    queryKey: ['admin_tech_requests', filters],
    queryFn: async (): Promise<RequestRow[]> => {
      const out: RequestRow[] = [];

      if (filters.type !== 'modification') {
        let q = supabase
          .from('part_requests')
          .select('id, status, part_name_text, notes, job_id, technician_id, created_at, payroll_techs!inner(name)')
          .order('created_at', { ascending: false });
        if (statusSet) q = q.in('status', statusSet);
        if (filters.technicianId) q = q.eq('technician_id', filters.technicianId);
        const { data, error } = await q;
        if (error) throw error;
        for (const r of (data ?? []) as any[]) {
          out.push({
            kind: 'price',
            id: r.id,
            status: r.status,
            part_name_text: r.part_name_text,
            notes: r.notes,
            job_id: r.job_id,
            technician_id: r.technician_id,
            technician_name: r.payroll_techs?.name ?? '',
            created_at: r.created_at,
          });
        }
      }

      if (filters.type !== 'price') {
        let q = supabase
          .from('modification_requests')
          .select('id, status, reasons, notes, job_id, run_id, technician_id, created_at, resolution_notes, payroll_techs!inner(name)')
          .order('created_at', { ascending: false });
        if (statusSet) q = q.in('status', statusSet);
        if (filters.technicianId) q = q.eq('technician_id', filters.technicianId);
        const { data, error } = await q;
        if (error) throw error;
        for (const r of (data ?? []) as any[]) {
          out.push({
            kind: 'modification',
            id: r.id,
            status: r.status,
            reasons: r.reasons ?? [],
            notes: r.notes,
            job_id: r.job_id,
            run_id: r.run_id,
            technician_id: r.technician_id,
            technician_name: r.payroll_techs?.name ?? '',
            created_at: r.created_at,
            resolution_notes: r.resolution_notes,
          });
        }
      }

      return out.sort((a, b) => (a.created_at < b.created_at ? 1 : -1));
    },
    staleTime: 15_000,
  });
}
```

- [ ] **Step 12.2: Write resolve hooks**

Create `twins-dash/src/hooks/admin/useResolvePartRequest.ts`:

```ts
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

// Resolve by creating a parts_prices row and pointing the request at it,
// OR by rejecting with a reason. Kept minimal: admin fills unit price and
// part name, we insert into payroll_parts_prices, then update the request.
export function useResolvePartRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args:
      | { request_id: string; action: 'reject'; rejection_reason: string }
      | { request_id: string; action: 'price'; part_name: string; unit_price: number }) => {
      if (args.action === 'reject') {
        const { error } = await supabase
          .from('part_requests')
          .update({
            status: 'rejected',
            rejection_reason: args.rejection_reason,
            resolved_at: new Date().toISOString(),
          })
          .eq('id', args.request_id);
        if (error) throw error;
      } else {
        // Insert into payroll_parts_prices
        const { data: price, error: priceErr } = await supabase
          .from('payroll_parts_prices')
          .insert({ part_name: args.part_name, unit_price: args.unit_price })
          .select('id')
          .single();
        if (priceErr) throw priceErr;
        const { error: updErr } = await supabase
          .from('part_requests')
          .update({
            status: 'added',
            resolved_parts_prices_id: price!.id,
            resolved_at: new Date().toISOString(),
          })
          .eq('id', args.request_id);
        if (updErr) throw updErr;
      }
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin_tech_requests'] }),
  });
}
```

Note: the exact column name `part_name` on `payroll_parts_prices` must match what's in the schema. If it's `name`, adjust. Grep it: `grep -n "payroll_parts_prices" twins-dash/supabase/migrations/*.sql | head`.

Create `twins-dash/src/hooks/admin/useResolveModificationRequest.ts`:

```ts
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export function useResolveModificationRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: { request_id: string; resolution_notes: string; reject?: boolean }) => {
      const { error } = await supabase
        .from('modification_requests')
        .update({
          status: args.reject ? 'rejected' : 'resolved',
          resolution_notes: args.resolution_notes,
          resolved_at: new Date().toISOString(),
        })
        .eq('id', args.request_id);
      if (error) throw error;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admin_tech_requests'] }),
  });
}
```

- [ ] **Step 12.3: Write RequestCard**

Create `twins-dash/src/components/admin/RequestCard.tsx`:

```tsx
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { ExternalLink } from 'lucide-react';
import { toast } from '@/components/ui/sonner';
import type { RequestRow } from '@/hooks/admin/useAdminTechRequests';
import { useResolvePartRequest } from '@/hooks/admin/useResolvePartRequest';
import { useResolveModificationRequest } from '@/hooks/admin/useResolveModificationRequest';

interface Props {
  req: RequestRow;
}

function fmtDate(s: string) {
  return new Date(s).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

export function RequestCard({ req }: Props) {
  const [price, setPrice] = useState<string>('');
  const [partName, setPartName] = useState<string>('');
  const [notes, setNotes] = useState<string>('');
  const resolvePart = useResolvePartRequest();
  const resolveMod = useResolveModificationRequest();

  return (
    <Card className="rounded-2xl">
      <CardHeader className="flex flex-row items-start justify-between gap-2 pb-2">
        <div>
          <div className="text-sm font-semibold">
            {req.technician_name} — Job {req.job_id}
          </div>
          <div className="text-xs text-muted-foreground">{fmtDate(req.created_at)}</div>
        </div>
        <Badge variant={req.kind === 'price' ? 'default' : 'secondary'}>
          {req.kind === 'price' ? 'Price needed' : 'Modification'}
        </Badge>
      </CardHeader>
      <CardContent className="space-y-3">
        {req.kind === 'price' ? (
          <>
            <div className="text-sm">
              <div className="font-medium">{req.part_name_text}</div>
              {req.notes ? (
                <div className="text-xs text-muted-foreground">{req.notes}</div>
              ) : null}
            </div>
            {req.status === 'pending' && (
              <div className="grid gap-2 sm:grid-cols-[1fr_140px_auto]">
                <Input
                  placeholder="Pricebook part name"
                  value={partName}
                  onChange={(e) => setPartName(e.target.value)}
                />
                <Input
                  placeholder="Unit price"
                  type="number"
                  step="0.01"
                  value={price}
                  onChange={(e) => setPrice(e.target.value)}
                />
                <Button
                  disabled={!price || !partName || resolvePart.isPending}
                  onClick={async () => {
                    try {
                      await resolvePart.mutateAsync({
                        request_id: req.id,
                        action: 'price',
                        part_name: partName,
                        unit_price: Number(price),
                      });
                      toast.success('Part priced and added.');
                    } catch (e: any) {
                      toast.error(e?.message ?? 'Failed to save');
                    }
                  }}
                >
                  Mark priced
                </Button>
              </div>
            )}
          </>
        ) : (
          <>
            <div className="flex flex-wrap gap-1">
              {req.reasons.map((r) => (
                <Badge key={r} variant="outline" className="text-[11px]">
                  {r.replace(/_/g, ' ')}
                </Badge>
              ))}
            </div>
            <div className="text-sm whitespace-pre-wrap">{req.notes}</div>
            {req.status === 'pending' ? (
              <div className="space-y-2">
                <Button asChild variant="outline" size="sm">
                  <Link to={`/payroll/run?run_id=${req.run_id ?? ''}`}>
                    <ExternalLink className="mr-1 h-4 w-4" />
                    Open in payroll editor
                  </Link>
                </Button>
                <Textarea
                  placeholder="Resolution note (required)"
                  rows={2}
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                />
                <div className="flex gap-2">
                  <Button
                    disabled={!notes.trim() || resolveMod.isPending}
                    onClick={async () => {
                      try {
                        await resolveMod.mutateAsync({
                          request_id: req.id,
                          resolution_notes: notes.trim(),
                        });
                        toast.success('Resolved.');
                      } catch (e: any) {
                        toast.error(e?.message ?? 'Failed to resolve');
                      }
                    }}
                  >
                    Mark resolved
                  </Button>
                  <Button
                    variant="destructive"
                    disabled={!notes.trim() || resolveMod.isPending}
                    onClick={async () => {
                      try {
                        await resolveMod.mutateAsync({
                          request_id: req.id,
                          resolution_notes: notes.trim(),
                          reject: true,
                        });
                        toast.success('Rejected.');
                      } catch (e: any) {
                        toast.error(e?.message ?? 'Failed');
                      }
                    }}
                  >
                    Reject
                  </Button>
                </div>
              </div>
            ) : (
              <div className="text-xs text-muted-foreground">
                Status: {req.status}
                {req.resolution_notes ? ` — ${req.resolution_notes}` : ''}
              </div>
            )}
          </>
        )}
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 12.4: Write the admin page**

Create `twins-dash/src/pages/admin/TechRequests.tsx`:

```tsx
import { useState } from 'react';
import { Card } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAdminTechRequests, type Filters } from '@/hooks/admin/useAdminTechRequests';
import { RequestCard } from '@/components/admin/RequestCard';

export default function TechRequestsPage() {
  const [filters, setFilters] = useState<Filters>({ status: 'open', type: 'all' });
  const { data, isLoading } = useAdminTechRequests(filters);

  return (
    <div className="space-y-5 p-3 sm:space-y-6 sm:p-6">
      <div>
        <h1 className="text-2xl font-bold text-primary">Tech Requests</h1>
        <p className="text-sm text-muted-foreground">
          Part price fills and modification requests from technicians.
        </p>
      </div>

      <Card className="flex flex-col gap-3 rounded-2xl p-3 sm:flex-row sm:items-center">
        <Select
          value={filters.status}
          onValueChange={(v: any) => setFilters((f) => ({ ...f, status: v }))}
        >
          <SelectTrigger className="w-full sm:w-40">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="open">Open</SelectItem>
            <SelectItem value="resolved">Resolved</SelectItem>
            <SelectItem value="all">All</SelectItem>
          </SelectContent>
        </Select>
        <Select
          value={filters.type ?? 'all'}
          onValueChange={(v: any) => setFilters((f) => ({ ...f, type: v }))}
        >
          <SelectTrigger className="w-full sm:w-40">
            <SelectValue placeholder="Type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All types</SelectItem>
            <SelectItem value="price">Price needed</SelectItem>
            <SelectItem value="modification">Modification</SelectItem>
          </SelectContent>
        </Select>
      </Card>

      {isLoading ? (
        <div className="text-sm text-muted-foreground">Loading…</div>
      ) : !data || data.length === 0 ? (
        <div className="text-sm text-muted-foreground">No matching requests.</div>
      ) : (
        <div className="space-y-3">
          {data.map((r) => (
            <RequestCard key={`${r.kind}-${r.id}`} req={r} />
          ))}
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 12.5: Commit**

```bash
git add twins-dash/src/pages/admin/TechRequests.tsx \
        twins-dash/src/components/admin/RequestCard.tsx \
        twins-dash/src/hooks/admin/
git commit -m "feat(admin): tech-requests queue page (price + modification)"
```

---

## Task 13: Routing — `/admin/tech-requests` and `/tech` → `/tech/:my_id` redirect

**Files:**
- Modify: `twins-dash/src/App.tsx`

- [ ] **Step 13.1: Add admin route**

In `twins-dash/src/App.tsx`, add near the other admin routes:

```tsx
<Route
  path="/admin/tech-requests"
  element={
    <ProtectedRoute requiredRole="admin">
      <AppShellWithNav>
        <Suspense fallback={<PageSpinner />}>
          <TechRequestsPage />
        </Suspense>
      </AppShellWithNav>
    </ProtectedRoute>
  }
/>
```

And import it at the top:

```tsx
const TechRequestsPage = lazy(() => import('./pages/admin/TechRequests'));
```

If `requiredRole="admin"` does not already grant field supervisors, change to the existing permission that matches both roles, or add a new `requiredPermission="manage_tech_requests"` with a fallback to role check. Follow whatever pattern is used for other admin pages.

- [ ] **Step 13.2: Add `/tech` root redirect for technicians**

Add a small component that resolves the caller's technician id from auth and redirects. In `twins-dash/src/App.tsx`, above the existing `<Route path="/tech/:id" ...>`:

```tsx
<Route
  path="/tech-scorecard-redirect"
  element={<TechScorecardRedirect />}
/>
```

Create that component inline or in a new file. Simplest: append to `App.tsx` before the `App` component:

```tsx
function TechScorecardRedirect() {
  const { technicianId } = useAuth() as any;
  if (!technicianId) return <Navigate to="/" replace />;
  return <Navigate to={`/tech/${technicianId}`} replace />;
}
```

Don't override the existing `/tech` route that already serves `TechHome` for the self-service app — leave that alone. Instead, add a new link in the tech sidebar ("My scorecard") that points to `/tech-scorecard-redirect` (see Task 14).

- [ ] **Step 13.3: Type-check**

Run: `cd twins-dash && npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 13.4: Commit**

```bash
git add twins-dash/src/App.tsx
git commit -m "feat(routing): /admin/tech-requests + /tech-scorecard-redirect"
```

---

## Task 14: Sidebar — add "Tech Requests" nav with count badge

**Files:**
- Modify: `twins-dash/src/components/layout/AppSidebar.tsx` (or whichever file holds the sidebar; confirm by grep)

- [ ] **Step 14.1: Find the sidebar file**

Run: `grep -lrn "Admin\s*</" twins-dash/src/components/layout/ 2>/dev/null; grep -lrn "nav" twins-dash/src/components/layout/ | head`
Expected: identifies the sidebar file. Open it.

- [ ] **Step 14.2: Add count hook**

Create `twins-dash/src/hooks/admin/useOpenTechRequestsCount.ts`:

```ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export function useOpenTechRequestsCount() {
  return useQuery({
    queryKey: ['open_tech_requests_count'],
    queryFn: async () => {
      const [{ count: partCount }, { count: modCount }] = await Promise.all([
        supabase.from('part_requests').select('*', { count: 'exact', head: true }).eq('status', 'pending'),
        supabase.from('modification_requests').select('*', { count: 'exact', head: true }).eq('status', 'pending'),
      ]);
      return (partCount ?? 0) + (modCount ?? 0);
    },
    staleTime: 30_000,
    refetchInterval: 60_000,
  });
}
```

- [ ] **Step 14.3: Add sidebar item**

In the sidebar file, under the existing Admin group, add:

```tsx
import { useOpenTechRequestsCount } from '@/hooks/admin/useOpenTechRequestsCount';

// Inside render, where other admin nav items are listed:
{(isAdmin || isFieldSupervisor) && (
  <NavLink to="/admin/tech-requests" className="...">
    Tech Requests
    <TechRequestsBadge />
  </NavLink>
)}

function TechRequestsBadge() {
  const { data } = useOpenTechRequestsCount();
  if (!data) return null;
  return (
    <span className="ml-auto rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
      {data}
    </span>
  );
}
```

Adapt `NavLink` / styling to the existing sidebar's pattern.

- [ ] **Step 14.4: Commit**

```bash
git add twins-dash/src/hooks/admin/useOpenTechRequestsCount.ts \
        twins-dash/src/components/layout/AppSidebar.tsx
git commit -m "feat(sidebar): tech requests nav with open count badge"
```

---

## Task 15: Integration pass + manual QA checklist

**Files:**
- Modify: whichever files surface during QA.

- [ ] **Step 15.1: Run typecheck and build**

Run:
```
cd twins-dash && npx tsc --noEmit
cd twins-dash && npm run build
```
Expected: clean. Fix any type or build errors encountered.

- [ ] **Step 15.2: Run all tests**

Run: `cd twins-dash && npx vitest run`
Expected: all green. Notably: `kpi-comparison.test.ts` passes.

- [ ] **Step 15.3: Manual QA on 375px viewport**

Start dev server: `cd twins-dash && npm run dev`. Open in a browser emulator at 375px.

Verify as each role:
1. **Tech logged in → `/tech-scorecard-redirect`** → lands on `/tech/<own-id>`. Sees Your scorecard heading, KPI grid readable, tracker shows current week's jobs, Finish Week disabled when jobs are draft, disclaimer banner visible.
2. **Tech → click a job in tracker** → navigates to `/tech/jobs/:jobId` (existing page). Enters parts via PartsPickerModal (no cost). Adds a custom part via RequestPartAddModal. Returns to scorecard.
3. **Tech → all jobs submitted → Finish Week** → confirmation modal → submit. Week Summary card appears with total commission and per-job rows. Request Modification opens dialog, submits, toast confirms.
4. **Admin logged in → `/tech/<other-tech-id>`** → sees {Name} scorecard heading, KPIs, tracker in admin read-only mode with "Open in payroll view" button.
5. **Admin → `/admin/tech-requests`** → sees list. Price request: fills part name + price, clicks Mark priced. Modification request: opens Open in payroll editor, returns, fills resolution note, Mark resolved.
6. **Sidebar badge** reflects open-request count.

- [ ] **Step 15.4: Screenshot evidence**

Take 3 mobile screenshots: (a) tech scorecard current week, (b) tech scorecard post-finish with Week Summary, (c) admin tech-requests page with one price and one modification request. Attach in PR body.

- [ ] **Step 15.5: Commit any fixes**

```bash
git add -A
git commit -m "chore(tech-dash): QA fixes from integration pass"
```

- [ ] **Step 15.6: Final push**

```bash
git push -u origin <branch-name>
```

Then open a PR referencing the spec at `docs/superpowers/specs/2026-04-24-tech-scorecard-overhaul-design.md`.

---

## Self-Review (performed on this plan)

**Spec coverage check:**
- Header + DateRangePicker + sync button → Task 10 header block (sync button carry-over from existing page).
- Disclaimer banner → Task 3 + rendered in Task 10.
- 8 KPIs with InfoTip + vs-company-avg pills → Tasks 2, 4, 10.
- Commission tracker (jobs list, per-job drill, Finish Week, week summary swap) → Tasks 6, 7, 9, 11.
- Modification request (reason checkboxes + details + queue flow) → Task 8 + Task 12.
- `/admin/tech-requests` unified queue → Task 12.
- Sidebar count badge → Task 14.
- Routing + auto-redirect → Task 13.
- Mobile-first + build verify → Task 15.
- New `modification_requests` table + RLS → Task 1.
- Existing schema reuse (`payroll_job_parts`, `submission_status`, `part_requests`, `v_my_*`) → no new tasks; leveraged throughout.

**Placeholder scan:** no "TBD", "TODO", or "similar to Task N" placeholders. All code blocks are complete.

**Type consistency:** `RequestRow` has `created_at` used everywhere. `CompanyAverages` fields match `TechScorecardValues`. `PillState` type used in `ComparisonPill`. Hook invalidation keys (`my_jobs`, `my_scorecard`, `my_paystub`, `my_modification_requests`, `admin_tech_requests`) are consistent across tasks.

**Known field-name risks (flagged for implementer):**
- `useTechnicianData` `kpi` property names (Task 10, Step 10.2) — verify and adapt.
- `useServiceTitanKPI` returned shape (Task 10) — verify.
- `useAuth` property names (`technicianId`, `isAdmin`, `isFieldSupervisor`) — verify.
- `MetricCard` `footer` prop — add if missing (Task 4, Step 4.4).
- `SubmitConfirmModal` props (Task 7, Step 7.3) — adapt to existing shape.
- `payroll_parts_prices` column name `part_name` vs `name` (Task 12) — grep and adapt.
