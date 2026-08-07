# KPI Trends Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let any KPI on the Twins dashboard be viewed as a trend over a picked timeframe, bucketed by day/week/month/quarter, companywide or for one technician, without creating a second definition of any KPI.

**Architecture:** A declarative registry names each trendable KPI and delegates its math to the existing canonical functions in `src/lib/kpi-calculations.ts`. A pure `buildSeries` engine partitions rows into time buckets and runs that canonical function once per bucket. The result renders in a new Trend tab inside the KPI drill-down sheet that tile clicks already open. No KPI math is written, changed, or moved.

**Tech Stack:** React 18 + TypeScript + Vite, TanStack Query v5, Recharts 2.15, date-fns 4, Vitest 4, Supabase JS.

**Spec:** `docs/superpowers/specs/2026-08-07-kpi-trends-design.md`

---

## Context an engineer new to this codebase needs

**Repo location.** The dashboard is its own git repo at `~/twins-dashboard/twins-dash` (remote `palpulla/twins-dash`). The outer `~/twins-dashboard` directory is a *different* repo holding only specs and plans. All code work happens inside `twins-dash`. Branch off `main`.

**Run tests with** `npx vitest --run <path>`. The bare `npm test` runs vitest in watch mode and will hang an agent. Existing test conventions live in `src/lib/__tests__/kpi-calculations.test.ts`.

**Two styling systems share one brand palette.** The Company Scorecard (`src/pages/Index.tsx`) uses plain CSS classes from `src/redesign.css` (`.kpi`, `.rd-card`, `.chip`). The tech portal uses shadcn/Tailwind. Brand colors are navy `#0E2148` and gold `#F7B801`. Charts across the app use those two constants directly, `CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0"`, axis ticks `{ fontSize: 11, fill: "#64748B" }` with `tickLine={false} axisLine={false}`, and a white tooltip with `1px solid #E2E8F0` and `borderRadius: 8`. Copy that convention exactly; do not invent new chart colors.

**Three rules that are load-bearing and will cause real damage if broken:**

1. **Never edit `src/lib/kpi-calculations.ts` in this work.** Every KPI number on the dashboard comes from it. This plan only ever *calls* it.
2. **The Charles Solo Rule.** Charles Rue is the field supervisor and rides along with junior techs. When Charles is on a ticket with another tech, 100% of the credit goes to the *other* tech, for every purpose. Task 2 extracts the existing implementation; do not rewrite it.
3. **Rate KPIs with an empty bucket render as a gap, never zero.** A quiet week plotted as 0% conversion reads as a business collapse.

**Money formatting.** Tiles and tooltips show full dollar amounts (`$5,243`, no cents). Chart *axis ticks* are the one permitted exception and may abbreviate to `$40k`.

**Weeks start Monday** (`weekStartsOn: 1`) in all KPI charts. The Friday-to-Thursday week used elsewhere in this codebase is a payroll concept and must not leak into KPI trends.

---

## File structure

**New, all under `src/lib/trends/` (pure, no React, fully unit-tested):**

| File | Responsibility |
|---|---|
| `attribution.ts` | Charles Solo Rule + tech-UUID-to-HCP-id map. Moved from `use-technician-data.ts` so exactly one implementation exists. |
| `granularity.ts` | Bucket boundary math, labels, auto-granularity choice, goal scaling. |
| `bucket-date.ts` | Resolves which timestamp puts a row in a bucket, per KPI date basis. |
| `kpi-registry.ts` | One declarative entry per trendable KPI. Delegates all math. |
| `build-series.ts` | The engine: rows + KPI + window → array of points. |

**New React:**

| File | Responsibility |
|---|---|
| `src/hooks/use-trend-data.ts` | Fetches the row window from Supabase, cached by window and data needs. |
| `src/components/dashboard/KpiTrendChart.tsx` | Renders one primary series plus up to three overlays. |
| `src/components/dashboard/TrendTab.tsx` | Controls (range, granularity, compare picker) wrapping the chart. |

**Modified:**

| File | Change |
|---|---|
| `src/hooks/use-technician-data.ts` | Import attribution helpers instead of defining them. |
| `src/components/dashboard/DrilldownSheet.tsx` | Becomes tabbed: Trend + Jobs. |
| `src/pages/Index.tsx` | ~15 KPI tiles become clickable. |
| `src/components/tech/KpiDrillSheet.tsx` | Gains a Trend tab. |
| `src/components/tech/TechTrendChart.tsx` | Rebuilt on `buildSeries`. |

**Deleted:** `src/hooks/admin/useTechWeeklyKpi.ts` — it forks the revenue math and hardcodes per-tech closing % to `null`.

---

## Task 1: Measure the payload before building on an assumption

The whole design assumes a 12-month window of job rows can be fetched into the browser. That is unverified. Do this first; a bad answer changes Task 7, the data hook.

**Files:**
- Create: `scripts/measure-trend-payload.ts` (throwaway, deleted in step 4)

- [ ] **Step 1: Write the measurement script**

```ts
// scripts/measure-trend-payload.ts
// Throwaway. Measures the row count and JSON size of a 12-month job window
// with and without the fat hcp_data blob, to size the trend fetcher.
import { createClient } from '@supabase/supabase-js';

const url = process.env.VITE_SUPABASE_URL!;
const key = process.env.VITE_SUPABASE_PUBLISHABLE_KEY!;
const supabase = createClient(url, key);

const NARROW =
  'id,job_id,tech_id,revenue_amount,job_type,status,is_opportunity,is_callback,' +
  'sold_threshold,lead_source,membership_attached,scheduled_at,started_at,' +
  'completed_at,invoice_paid_at,estimate_status,source_estimate_id';

async function measure(label: string, columns: string) {
  const from = new Date();
  from.setFullYear(from.getFullYear() - 1);
  let offset = 0;
  let rows = 0;
  let bytes = 0;
  const t0 = Date.now();
  for (;;) {
    const { data, error } = await supabase
      .from('jobs')
      .select(columns)
      .gte('scheduled_at', from.toISOString())
      .range(offset, offset + 999);
    if (error) throw error;
    if (!data?.length) break;
    rows += data.length;
    bytes += Buffer.byteLength(JSON.stringify(data));
    if (data.length < 1000) break;
    offset += 1000;
  }
  console.log(
    `${label}: ${rows} rows, ${(bytes / 1_048_576).toFixed(2)} MB, ${Date.now() - t0} ms`,
  );
}

await measure('narrow            ', NARROW);
await measure('narrow + hcp_data ', `${NARROW},hcp_data`);
```

- [ ] **Step 2: Run it**

Run: `npx tsx scripts/measure-trend-payload.ts`
Expected: two lines of output, for example `narrow: 4812 rows, 1.10 MB, 900 ms`.

- [ ] **Step 3: Record the numbers and decide**

Write the two lines into the spec under "Risks" as a Measured block, replacing the "unverified" wording.

Decision rule:
- Both variants under ~8 MB and ~4 s: proceed with Task 7 exactly as written.
- The `hcp_data` variant is over that: **stop and report to the user before continuing.** The fallback is a Postgres view projecting only the `hcp_data` fields the canonical functions read (`assigned_employees`, `tags`, `options` length, `customer.id`, `created_at`), which keeps the math client-side. Do not respond by moving KPI aggregation into SQL; that recreates the bug this plan exists to delete.

- [ ] **Step 4: Delete the script and commit the finding**

```bash
rm scripts/measure-trend-payload.ts
git add docs/superpowers/specs/2026-08-07-kpi-trends-design.md
git commit -m "docs(spec): record measured trend payload size for a 12-month window"
```

---

## Task 2: Extract the Charles Solo Rule into a shared module

Today `shouldCreditTechnician` lives inside `src/hooks/use-technician-data.ts` (lines 18-100). The trend engine needs the same rule. Copying it would guarantee future drift, so move it and import it back.

**Files:**
- Create: `src/lib/trends/attribution.ts`
- Create: `src/lib/trends/__tests__/attribution.test.ts`
- Modify: `src/hooks/use-technician-data.ts:18-100`

- [ ] **Step 1: Write the failing test**

```ts
// src/lib/trends/__tests__/attribution.test.ts
import { describe, it, expect } from 'vitest';
import {
  CHARLES_HCP_ID,
  CHARLES_TECH_ID,
  MAURICE_HCP_ID,
  MAURICE_TECH_ID,
  TECH_HCP_BY_UUID,
  getAssignedHcpIds,
  shouldCreditTechnician,
  creditedJobsFor,
} from '../attribution';

const job = (ids: string[]) => ({ hcp_data: { assigned_employees: ids.map((id) => ({ id })) } });

describe('attribution', () => {
  it('reads assigned HCP ids, tolerating a missing or malformed blob', () => {
    expect(getAssignedHcpIds(job(['a', 'b']))).toEqual(['a', 'b']);
    expect(getAssignedHcpIds({ hcp_data: null })).toEqual([]);
    expect(getAssignedHcpIds({ hcp_data: { assigned_employees: 'nope' } })).toEqual([]);
  });

  it('credits a shared Charles ticket to the other tech, not Charles', () => {
    const shared = job([CHARLES_HCP_ID, MAURICE_HCP_ID]);
    expect(shouldCreditTechnician(shared, CHARLES_HCP_ID)).toBe(false);
    expect(shouldCreditTechnician(shared, MAURICE_HCP_ID)).toBe(true);
  });

  it('credits Charles when he is the sole assigned tech', () => {
    expect(shouldCreditTechnician(job([CHARLES_HCP_ID]), CHARLES_HCP_ID)).toBe(true);
  });

  it('leaves non-Charles tickets to the caller-side tech_id filter', () => {
    expect(shouldCreditTechnician(job([MAURICE_HCP_ID]), MAURICE_HCP_ID)).toBe(true);
    expect(shouldCreditTechnician({ hcp_data: null }, MAURICE_HCP_ID)).toBe(true);
  });

  it('maps tech UUIDs to HCP ids', () => {
    expect(TECH_HCP_BY_UUID[CHARLES_TECH_ID]).toBe(CHARLES_HCP_ID);
    expect(TECH_HCP_BY_UUID[MAURICE_TECH_ID]).toBe(MAURICE_HCP_ID);
  });

  it('creditedJobsFor filters a mixed set for one tech', () => {
    const jobs = [job([CHARLES_HCP_ID, MAURICE_HCP_ID]), job([CHARLES_HCP_ID])];
    expect(creditedJobsFor(jobs, CHARLES_TECH_ID)).toHaveLength(1);
    expect(creditedJobsFor(jobs, MAURICE_TECH_ID)).toHaveLength(1);
  });

  it('creditedJobsFor returns every row unchanged for an unmapped tech UUID', () => {
    const jobs = [job([CHARLES_HCP_ID, MAURICE_HCP_ID])];
    expect(creditedJobsFor(jobs, 'not-a-known-uuid')).toHaveLength(1);
  });

  it('keeps a job identified only by tech_id, with no assigned_employees', () => {
    // ~5% of real rows carry no assigned_employees array; one per year also
    // has a tech_id. Matching on assignees alone would silently drop it.
    const jobs = [{ tech_id: MAURICE_TECH_ID, hcp_data: null }];
    expect(creditedJobsFor(jobs, MAURICE_TECH_ID)).toHaveLength(1);
    expect(creditedJobsFor(jobs, CHARLES_TECH_ID)).toHaveLength(0);
  });

  it('still drops a Charles solo ticket from another tech set', () => {
    const jobs = [{ tech_id: CHARLES_TECH_ID, hcp_data: { assigned_employees: [{ id: CHARLES_HCP_ID }] } }];
    expect(creditedJobsFor(jobs, MAURICE_TECH_ID)).toHaveLength(0);
    expect(creditedJobsFor(jobs, CHARLES_TECH_ID)).toHaveLength(1);
  });

  it('credits the junior on a ticket where Charles is the primary tech_id', () => {
    // The symmetric case use-technician-data.ts handles with a second query.
    const jobs = [{
      tech_id: CHARLES_TECH_ID,
      hcp_data: { assigned_employees: [{ id: CHARLES_HCP_ID }, { id: MAURICE_HCP_ID }] },
    }];
    expect(creditedJobsFor(jobs, MAURICE_TECH_ID)).toHaveLength(1);
    expect(creditedJobsFor(jobs, CHARLES_TECH_ID)).toHaveLength(0);
  });

  it('credits a two-junior ticket to the primary tech only, never both', () => {
    // Ungated assignee matching would return this job for BOTH techs, so
    // summing per-tech trends would exceed the company total.
    const jobs = [{
      tech_id: MAURICE_TECH_ID,
      hcp_data: { assigned_employees: [{ id: MAURICE_HCP_ID }, { id: NICHOLAS_HCP_ID }] },
    }];
    expect(creditedJobsFor(jobs, MAURICE_TECH_ID)).toHaveLength(1);
    expect(creditedJobsFor(jobs, NICHOLAS_TECH_ID)).toHaveLength(0);
  });

  it('derives its roster from the canonical technicians list', () => {
    expect(Object.keys(TECH_HCP_BY_UUID)).toHaveLength(TECHNICIANS.length);
    for (const t of TECHNICIANS) expect(TECH_HCP_BY_UUID[t.id]).toBe(t.hcpId);
  });
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `npx vitest --run src/lib/trends/__tests__/attribution.test.ts`
Expected: FAIL, cannot resolve `../attribution`.

- [ ] **Step 3: Create the module**

```ts
// src/lib/trends/attribution.ts
/**
 * Charles Solo Rule (Twins-specific, LOAD-BEARING).
 *
 * Charles is the field supervisor and rides along with juniors on bigger
 * tickets. Daniel's directive: when Charles is on a ticket WITH another
 * tech, credit goes 100% to the OTHER tech for ALL purposes — revenue,
 * jobs, KPIs, scorecard, commission, leaderboard. He only earns credit
 * when he is the SOLE assigned tech.
 *
 * Moved here verbatim from use-technician-data.ts so the tile and the
 * trend can never disagree about whose ticket a job is.
 */

// Derived from the canonical roster in src/lib/technicians.ts, never
// retyped. That file's header promises every consumer picks up a new tech
// automatically; forking the ids here would silently break per-tech trends
// on the next hire, in the very module that exists to prevent drift.
import { TECHNICIANS, CHARLES_HCP_ID, TECH_BY_DB_ID } from '@/lib/technicians';

export { CHARLES_HCP_ID };

const techIdForHcpId = (hcpId: string) => TECHNICIANS.find((t) => t.hcpId === hcpId)?.id ?? '';
export const CHARLES_TECH_ID = techIdForHcpId(CHARLES_HCP_ID);

/** DB tech UUID -> HCP employee id, derived from the canonical roster. */
export const TECH_HCP_BY_UUID: Record<string, string> = Object.fromEntries(TECH_BY_DB_ID);

/** Structural minimum: a tech id and an hcp_data blob. */
export interface AttributableJob {
  tech_id?: string | null;
  hcp_data?: unknown;
}

export function getAssignedHcpIds(job: AttributableJob): string[] {
  const employees = (job.hcp_data as { assigned_employees?: Array<{ id?: string }> } | null)
    ?.assigned_employees;
  if (!Array.isArray(employees)) return [];
  return employees.map((e) => e?.id ?? '').filter(Boolean);
}

/**
 * True iff `techHcpId` is the credited tech for `job` per the rule.
 *
 * Callers that query per-tech also need a SYMMETRIC pull (a tech's view
 * must include tickets where Charles is the primary tech_id but the
 * queried tech is co-assigned). The trend fetcher sidesteps that by
 * pulling every tech's rows for the window and filtering here.
 */
export function shouldCreditTechnician(job: AttributableJob, techHcpId: string): boolean {
  const hcpIds = getAssignedHcpIds(job);
  const charlesOnTicket = hcpIds.includes(CHARLES_HCP_ID);
  const sharedTicket = charlesOnTicket && hcpIds.length > 1;

  if (sharedTicket) {
    if (techHcpId === CHARLES_HCP_ID) return false;
    return hcpIds.includes(techHcpId);
  }
  return true;
}

/**
 * Filter a full row set to the tickets credited to one tech UUID.
 * An unmapped UUID returns the set unchanged, matching the existing
 * `techHcpId ? filter : allJobs` behavior in use-technician-data.ts.
 *
 * IMPORTANT: `shouldCreditTechnician` alone is NOT enough here. It assumes
 * the caller already narrowed the set to one tech with a SQL `tech_id`
 * filter, which is why its non-shared branch returns true unconditionally.
 * Over the trend engine's all-techs row set that would credit every
 * non-shared ticket to whoever you asked about. So ownership is checked
 * first, both ways, and only then is the Charles rule applied.
 */
export function creditedJobsFor<T extends AttributableJob>(jobs: T[], techUuid: string): T[] {
  const techHcpId = TECH_HCP_BY_UUID[techUuid] ?? '';
  if (!techHcpId) return jobs;
  return jobs.filter((j) => {
    // tech_id alone is sometimes the only signal: ~5% of rows carry no
    // assigned_employees at all. The assignees branch covers the symmetric
    // case where Charles is the primary tech_id but this tech is
    // co-assigned — and it MUST stay gated to Charles-primary rows.
    // Ungated, any co-assignment reads as ownership, so a ticket shared by
    // two juniors is credited to both and per-tech totals exceed the
    // company total. use-technician-data.ts gates its symmetric pull the
    // same way, citing a prior inflation incident.
    const isTheirs =
      j.tech_id === techUuid
      || (j.tech_id === CHARLES_TECH_ID && getAssignedHcpIds(j).includes(techHcpId));
    return isTheirs && shouldCreditTechnician(j, techHcpId);
  });
}
```

- [ ] **Step 4: Run the test**

Run: `npx vitest --run src/lib/trends/__tests__/attribution.test.ts`
Expected: PASS, 7 tests.

- [ ] **Step 5: Point the existing hook at the shared module**

In `src/hooks/use-technician-data.ts`, delete the six `const *_HCP_ID` / `*_TECH_ID` declarations, the `TECH_HCP_BY_UUID` object, the `AssignedEmployee` interface, `getAssignedHcpIds`, and `shouldCreditTechnician` (lines 18-100, keeping the `JobRow` interface and its `export type { JobRow }`). Add at the top of the import block:

```ts
import {
  CHARLES_HCP_ID,
  CHARLES_TECH_ID,
  TECH_HCP_BY_UUID,
  shouldCreditTechnician,
} from '@/lib/trends/attribution';
```

Leave every call site unchanged. `CHARLES_HCP_ID` and `CHARLES_TECH_ID` are still referenced by `fetchAllJobsForTech` around lines 225-231.

- [ ] **Step 6: Verify nothing moved numerically**

Run: `npx vitest --run src/lib && npx tsc --noEmit`
Expected: all existing tests PASS, no type errors. This refactor must be behavior-neutral.

- [ ] **Step 7: Commit**

```bash
git add src/lib/trends/attribution.ts src/lib/trends/__tests__/attribution.test.ts src/hooks/use-technician-data.ts
git commit -m "refactor(trends): extract the Charles Solo Rule into a shared attribution module"
```

---

## Task 3: Bucket math and granularity

**Files:**
- Create: `src/lib/trends/granularity.ts`
- Create: `src/lib/trends/__tests__/granularity.test.ts`

- [ ] **Step 1: Write the failing test**

```ts
// src/lib/trends/__tests__/granularity.test.ts
import { describe, it, expect } from 'vitest';
import {
  autoGranularity,
  bucketKey,
  bucketLabel,
  bucketStart,
  eachBucket,
  periodGoal,
} from '../granularity';

describe('granularity', () => {
  it('starts weeks on Monday, never Sunday or Friday', () => {
    // 2026-08-07 is a Friday. Its week starts Monday 2026-08-03.
    expect(bucketKey(bucketStart(new Date(2026, 7, 7), 'week'), 'week')).toBe('2026-08-03');
    // A Sunday belongs to the week that began the previous Monday.
    expect(bucketKey(bucketStart(new Date(2026, 7, 9), 'week'), 'week')).toBe('2026-08-03');
    // The following Monday opens a new bucket.
    expect(bucketKey(bucketStart(new Date(2026, 7, 10), 'week'), 'week')).toBe('2026-08-10');
  });

  it('collapses a date to the start of its day, month and quarter', () => {
    const d = new Date(2026, 7, 7, 15, 30);
    expect(bucketKey(bucketStart(d, 'day'), 'day')).toBe('2026-08-07');
    expect(bucketKey(bucketStart(d, 'month'), 'month')).toBe('2026-08-01');
    expect(bucketKey(bucketStart(d, 'quarter'), 'quarter')).toBe('2026-07-01');
  });

  it('emits a dense bucket list including empty periods', () => {
    const buckets = eachBucket(new Date(2026, 0, 1), new Date(2026, 2, 31), 'month');
    expect(buckets.map((b) => bucketKey(b, 'month'))).toEqual([
      '2026-01-01',
      '2026-02-01',
      '2026-03-01',
    ]);
  });

  it('picks granularity from the window length and never auto-picks quarter', () => {
    const from = new Date(2026, 0, 1);
    const plus = (days: number) => new Date(2026, 0, 1 + days);
    expect(autoGranularity(from, plus(29))).toBe('day');
    expect(autoGranularity(from, plus(45))).toBe('day');
    expect(autoGranularity(from, plus(46))).toBe('week');
    expect(autoGranularity(from, plus(400))).toBe('week');
    expect(autoGranularity(from, plus(401))).toBe('month');
  });

  it('scales an annual goal to the bucket', () => {
    expect(periodGoal(520000, 'week')).toBe(10000);
    expect(periodGoal(1200000, 'month')).toBe(100000);
    expect(periodGoal(1200000, 'quarter')).toBe(300000);
  });

  it('labels buckets readably', () => {
    expect(bucketLabel(new Date(2026, 7, 3), 'week')).toBe('Aug 3');
    expect(bucketLabel(new Date(2026, 7, 1), 'month')).toBe('Aug 2026');
    expect(bucketLabel(new Date(2026, 6, 1), 'quarter')).toBe('Q3 2026');
  });
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `npx vitest --run src/lib/trends/__tests__/granularity.test.ts`
Expected: FAIL, cannot resolve `../granularity`.

- [ ] **Step 3: Implement**

```ts
// src/lib/trends/granularity.ts
import {
  differenceInCalendarDays,
  eachDayOfInterval,
  eachMonthOfInterval,
  eachQuarterOfInterval,
  eachWeekOfInterval,
  format,
  startOfDay,
  startOfMonth,
  startOfQuarter,
  startOfWeek,
} from 'date-fns';

export type Granularity = 'day' | 'week' | 'month' | 'quarter';
export const GRANULARITIES: readonly Granularity[] = ['day', 'week', 'month', 'quarter'] as const;

/**
 * KPI trend weeks are Monday-aligned, matching RevenueTrendChart and
 * TechTrendChart. They are deliberately NOT the Friday-to-Thursday
 * payroll week: a KPI trend is not a pay period.
 */
const WEEK_OPTS = { weekStartsOn: 1 } as const;

export function bucketStart(date: Date, g: Granularity): Date {
  if (g === 'day') return startOfDay(date);
  if (g === 'week') return startOfWeek(date, WEEK_OPTS);
  if (g === 'month') return startOfMonth(date);
  return startOfQuarter(date);
}

export function bucketKey(date: Date, _g: Granularity): string {
  return format(date, 'yyyy-MM-dd');
}

export function bucketLabel(date: Date, g: Granularity): string {
  if (g === 'day' || g === 'week') return format(date, 'MMM d');
  if (g === 'month') return format(date, 'MMM yyyy');
  return `Q${Math.floor(date.getMonth() / 3) + 1} ${format(date, 'yyyy')}`;
}

/** Dense bucket list spanning [from, to], so empty periods still appear. */
export function eachBucket(from: Date, to: Date, g: Granularity): Date[] {
  const interval = { start: bucketStart(from, g), end: to };
  if (g === 'day') return eachDayOfInterval(interval);
  if (g === 'week') return eachWeekOfInterval(interval, WEEK_OPTS);
  if (g === 'month') return eachMonthOfInterval(interval);
  return eachQuarterOfInterval(interval);
}

/**
 * Default granularity for a window. Quarter is never auto-selected; it is
 * reachable only by explicit user choice.
 */
export function autoGranularity(from: Date, to: Date): Granularity {
  const days = differenceInCalendarDays(to, from);
  if (days <= 45) return 'day';
  if (days <= 400) return 'week';
  return 'month';
}

/** Scale an annual goal down to one bucket. */
export function periodGoal(annualGoal: number, g: Granularity): number {
  if (g === 'day') return annualGoal / 365;
  if (g === 'week') return annualGoal / 52;
  if (g === 'month') return annualGoal / 12;
  return annualGoal / 4;
}
```

- [ ] **Step 4: Run the test**

Run: `npx vitest --run src/lib/trends/__tests__/granularity.test.ts`
Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add src/lib/trends/granularity.ts src/lib/trends/__tests__/granularity.test.ts
git commit -m "feat(trends): add Monday-aligned bucket math and auto-granularity"
```

---

## Task 4: Resolve which date puts a row in a bucket

This is the subtle one. `useDashboardData` runs two different queries for two different date fields, and it includes an estimate row when **either** `hcp_data.created_at` **or** `scheduled_at` is in range. The bucketer must mirror that or trends will silently drop rows the tiles counted.

**Files:**
- Create: `src/lib/trends/bucket-date.ts`
- Create: `src/lib/trends/__tests__/bucket-date.test.ts`

- [ ] **Step 1: Write the failing test**

```ts
// src/lib/trends/__tests__/bucket-date.test.ts
import { describe, it, expect } from 'vitest';
import { bucketDateFor, type DateBasis } from '../bucket-date';

const at = (iso: string) => new Date(iso).getTime();

describe('bucketDateFor', () => {
  it('buckets a completed_at KPI by completion', () => {
    const d = bucketDateFor(
      { job_type: 'Repair', completed_at: '2026-08-07T14:00:00Z', scheduled_at: '2026-08-01T14:00:00Z' },
      'completed_at',
    );
    expect(d?.getTime()).toBe(at('2026-08-07T14:00:00Z'));
  });

  it('returns null when the basis field is empty, so the row is skipped', () => {
    expect(bucketDateFor({ job_type: 'Repair', completed_at: null }, 'completed_at')).toBeNull();
    expect(bucketDateFor({ job_type: 'Repair', scheduled_at: null }, 'scheduled_at')).toBeNull();
  });

  it('buckets a scheduled_at KPI by schedule', () => {
    const d = bucketDateFor({ job_type: 'Repair', scheduled_at: '2026-08-01T14:00:00Z' }, 'scheduled_at');
    expect(d?.getTime()).toBe(at('2026-08-01T14:00:00Z'));
  });

  it('falls back to hcp_data.created_at for an unscheduled ESTIMATE row', () => {
    // Mirrors useDashboardData, which includes an estimate when EITHER
    // hcp_data.created_at OR scheduled_at falls in range.
    const d = bucketDateFor(
      { job_type: 'Estimate', scheduled_at: null, hcp_data: { created_at: '2026-07-20T09:00:00Z' } },
      'scheduled_at',
    );
    expect(d?.getTime()).toBe(at('2026-07-20T09:00:00Z'));
  });

  it('does NOT apply the created_at fallback to non-estimate rows', () => {
    const d = bucketDateFor(
      { job_type: 'Repair', scheduled_at: null, hcp_data: { created_at: '2026-07-20T09:00:00Z' } },
      'scheduled_at',
    );
    expect(d).toBeNull();
  });

  it('prefers a real scheduled_at over the estimate fallback', () => {
    const d = bucketDateFor(
      {
        job_type: 'Estimate',
        scheduled_at: '2026-08-01T14:00:00Z',
        hcp_data: { created_at: '2026-07-20T09:00:00Z' },
      },
      'scheduled_at',
    );
    expect(d?.getTime()).toBe(at('2026-08-01T14:00:00Z'));
  });

  it('buckets non-job sources by their date column', () => {
    const d = bucketDateFor({ date: '2026-08-07' }, 'row_date' as DateBasis);
    expect(d?.getFullYear()).toBe(2026);
  });
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `npx vitest --run src/lib/trends/__tests__/bucket-date.test.ts`
Expected: FAIL, cannot resolve `../bucket-date`.

- [ ] **Step 3: Implement**

```ts
// src/lib/trends/bucket-date.ts

/**
 * Which timestamp decides a row's bucket.
 *  - completed_at: revenue-style KPIs, matching useDashboardData's revenue query
 *  - scheduled_at: opportunity/conversion KPIs, matching useDashboardData's jobs query
 *  - row_date:     calls_inbound and marketing_spend, which carry a plain `date`
 */
export type DateBasis = 'completed_at' | 'scheduled_at' | 'row_date';

export interface DatableRow {
  job_type?: string | null;
  completed_at?: string | null;
  scheduled_at?: string | null;
  hcp_data?: unknown;
  date?: string | null;
}

const parse = (v: string | null | undefined): Date | null => {
  if (!v) return null;
  const d = new Date(v);
  return Number.isNaN(d.getTime()) ? null : d;
};

export function bucketDateFor(row: DatableRow, basis: DateBasis): Date | null {
  if (basis === 'row_date') return parse(row.date);
  if (basis === 'completed_at') return parse(row.completed_at);

  const scheduled = parse(row.scheduled_at);
  if (scheduled) return scheduled;

  // Estimate rows created in the office often never get scheduled.
  // useDashboardData still counts them, keyed on hcp_data.created_at.
  // Applying this fallback to non-estimate rows would pull undispatched
  // work into buckets the tiles never counted it in.
  if (row.job_type !== 'Estimate') return null;
  const created = (row.hcp_data as { created_at?: string } | null)?.created_at;
  return parse(created);
}
```

- [ ] **Step 4: Run the test**

Run: `npx vitest --run src/lib/trends/__tests__/bucket-date.test.ts`
Expected: PASS, 7 tests.

- [ ] **Step 5: Commit**

```bash
git add src/lib/trends/bucket-date.ts src/lib/trends/__tests__/bucket-date.test.ts
git commit -m "feat(trends): resolve bucket dates per KPI basis, mirroring useDashboardData"
```

---

## Task 5: The KPI registry

Every `compute` here delegates to `src/lib/kpi-calculations.ts`. If you find yourself writing arithmetic in this file beyond a `reduce` that reproduces an existing tile expression verbatim, stop: that is a second definition and it is the bug this plan exists to prevent.

**Files:**
- Create: `src/lib/trends/kpi-registry.ts`
- Create: `src/lib/trends/__tests__/kpi-registry.test.ts`

- [ ] **Step 1: Write the failing test**

```ts
// src/lib/trends/__tests__/kpi-registry.test.ts
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { TREND_KPIS, getTrendKpi, trendKpisForTech } from '../kpi-registry';

describe('kpi-registry', () => {
  it('has unique keys', () => {
    const keys = TREND_KPIS.map((k) => k.key);
    expect(new Set(keys).size).toBe(keys.length);
  });

  it('gives every entry a sane shape', () => {
    for (const k of TREND_KPIS) {
      expect(k.label.length, `${k.key} needs a label`).toBeGreaterThan(0);
      expect(['usd', 'pct', 'count', 'ratio']).toContain(k.unit);
      expect(['sum', 'rate']).toContain(k.aggregate);
      expect(['completed_at', 'scheduled_at', 'row_date']).toContain(k.dateBasis);
      expect(k.sources.length, `${k.key} needs a source`).toBeGreaterThan(0);
      expect(typeof k.compute).toBe('function');
    }
  });

  it('marks the inverted KPIs as lower-is-better', () => {
    expect(getTrendKpi('callback_rate')?.higherIsBetter).toBe(false);
    expect(getTrendKpi('cancellation_rate')?.higherIsBetter).toBe(false);
    expect(getTrendKpi('conversion')?.higherIsBetter).toBe(true);
  });

  it('returns null rather than zero for a rate with no denominator', () => {
    const empty = { jobs: [], calls: [], marketingSpend: [], allJobs: [], isTechScoped: false };
    for (const k of TREND_KPIS.filter((k) => k.aggregate === 'rate')) {
      expect(k.compute(empty), `${k.key} must gap, not zero`).toBeNull();
    }
  });

  it('returns zero, not null, for a countable KPI with no rows', () => {
    const empty = { jobs: [], calls: [], marketingSpend: [], allJobs: [], isTechScoped: false };
    for (const k of TREND_KPIS.filter((k) => k.aggregate === 'sum')) {
      expect(k.compute(empty), `${k.key} must be 0`).toBe(0);
    }
  });

  it('excludes non-attributable KPIs from the per-tech list', () => {
    const perTechKeys = trendKpisForTech().map((k) => k.key);
    expect(perTechKeys).toContain('conversion');
    expect(perTechKeys).toContain('revenue');
    expect(perTechKeys).not.toContain('call_booking_rate');
    expect(perTechKeys).not.toContain('marketing_spend');
  });

  // NOTE: the obvious version of this test — "every key in every chain is
  // reachable" — is WRONG and will fail. `closing_rate` is unreachable BY
  // DESIGN; that is the entire reason closing_pct needs a chain. The
  // invariant that matters is that a chain ENDS in a reachable key.
  //
  // Also: scan every migration, not just the 20260406 seed. `revenue_annual`
  // is created later by 20260502190000_tech_revenue_goals.sql, so reading one
  // file would wrongly condemn it.
  //
  // Implemented as three tests, all mutation-checked:
  //   1. ends every goal chain in a key that something can actually create
  //   2. has exactly one known-unreachable goal key, and it is a fallback
  //      (pins the unreachable set to {closing_rate}, preserving typo
  //      protection — a typo'd key would otherwise pass test 1 silently)
  //   3. mirrors the fallback chain the Closing % gauge uses (pins the
  //      ORDER against Index.tsx:750)
  //
  // ADMIN_CREATABLE is {callback_rate, options_per_ticket}, verified against
  // Goals.tsx's KPI_TO_METRIC_KEY *and* the live scorecard_tier_thresholds
  // rows, since that page renders a card only when the kpi_key row exists.
  // See the committed src/lib/trends/__tests__/kpi-registry.test.ts.
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `npx vitest --run src/lib/trends/__tests__/kpi-registry.test.ts`
Expected: FAIL, cannot resolve `../kpi-registry`.

- [ ] **Step 3: Implement**

```ts
// src/lib/trends/kpi-registry.ts
import {
  calculateAvgOptionsPerTicket,
  calculateAvgOptionsPerTicketForTech,
  calculateAvgSoldInstall,
  calculateAvgSoldRepair,
  calculateCallBookingRate,
  calculateCallbackRate,
  calculateCancellationRate,
  calculateClosingStats,
  calculateConversionRate,
  calculateEstimateConversionRate,
  calculateMembershipConversionRate,
  calculateOpportunityJobAverage,
  calculateTotalJobAverage,
  countAppointments,
  getOpportunitiesIncludingCanceled,
  getUniqueOpportunities,
} from '@/lib/kpi-calculations';
import type { DateBasis } from './bucket-date';

export type TrendUnit = 'usd' | 'pct' | 'count' | 'ratio';
export type SourceKey = 'jobs' | 'calls' | 'marketing';

/* eslint-disable @typescript-eslint/no-explicit-any */
export interface TrendInputs {
  /** Rows credited to the selected tech, or all rows companywide. */
  jobs: any[];
  calls: any[];
  marketingSpend: any[];
  /** Unfiltered rows for the same bucket. Only options-per-ticket needs this. */
  allJobs: any[];
  /** True when `jobs` has been narrowed to one technician. */
  isTechScoped: boolean;
}
/* eslint-enable @typescript-eslint/no-explicit-any */

export interface TrendKpiDef {
  key: string;
  label: string;
  unit: TrendUnit;
  dateBasis: DateBasis;
  sources: SourceKey[];
  /** Whether the fat hcp_data blob must be selected for this KPI. */
  needsHcpData: boolean;
  /** sum = bucket values add to the period total. rate = an average or percentage. */
  aggregate: 'sum' | 'rate';
  /** Delegates to the canonical library. Returns null when a rate has no denominator. */
  compute: (rows: TrendInputs) => number | null;
  /** Powers the tooltip's sample size. */
  denominator?: (rows: TrendInputs) => number;
  /** company_goals keys to try IN ORDER, mirroring the tiles' fallback chains. */
  goalKeys?: string[];
  perTech: boolean;
  higherIsBetter: boolean;
}

const sum = (rows: Array<Record<string, unknown>>, field: string) =>
  rows.reduce((s, r) => s + (Number(r[field]) || 0), 0);

export const TREND_KPIS: TrendKpiDef[] = [
  {
    key: 'revenue',
    label: 'Revenue',
    unit: 'usd',
    dateBasis: 'completed_at',
    sources: ['jobs'],
    // MUST be true despite the compute not reading hcp_data: this entry is
    // perTech, and creditedJobsFor reads hcp_data.assigned_employees. Without
    // the blob the Charles Solo Rule degenerates to "always true" and per-tech
    // revenue credit is silently wrong — on the number that feeds commission.
    needsHcpData: true,
    aggregate: 'sum',
    // Mirrors the Total Sales tile, which sums useDashboardData's revenue
    // query: completed_at present AND revenue_amount > 0, with no status or
    // opportunity filter. This is deliberately NOT calculateTotalRevenue(),
    // which sums unique opportunity rows and produces a different number.
    compute: (r) => sum(r.jobs.filter((j) => j.completed_at && (j.revenue_amount || 0) > 0), 'revenue_amount'),
    denominator: (r) => r.jobs.filter((j) => j.completed_at && (j.revenue_amount || 0) > 0).length,
    goalKeys: ['revenue_annual'],
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'opportunity_avg',
    label: 'Opportunity job average',
    unit: 'usd',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    compute: (r) => (getUniqueOpportunities(r.jobs).length === 0 ? null : calculateOpportunityJobAverage(r.jobs)),
    denominator: (r) => getUniqueOpportunities(r.jobs).length,
    goalKeys: ['opportunity_average'],
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'total_job_avg',
    label: 'Total job average',
    unit: 'usd',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    compute: (r) => (r.jobs.length === 0 ? null : calculateTotalJobAverage(r.jobs)),
    denominator: (r) => r.jobs.length,
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'repair_avg',
    label: 'Avg sold repair',
    unit: 'usd',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    compute: (r) => {
      const s = calculateAvgSoldRepair(r.jobs);
      return s.count === 0 ? null : s.average;
    },
    denominator: (r) => calculateAvgSoldRepair(r.jobs).count,
    goalKeys: ['avg_repair_ticket'],
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'install_avg',
    label: 'Avg sold install',
    unit: 'usd',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    compute: (r) => {
      const s = calculateAvgSoldInstall(r.jobs);
      return s.count === 0 ? null : s.average;
    },
    denominator: (r) => calculateAvgSoldInstall(r.jobs).count,
    goalKeys: ['avg_install_ticket'],
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'conversion',
    label: 'Conversion rate',
    unit: 'pct',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    compute: (r) => {
      const s = calculateConversionRate(r.jobs);
      return s.total === 0 ? null : s.rate;
    },
    denominator: (r) => calculateConversionRate(r.jobs).total,
    goalKeys: ['conversion_rate'],
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'closing_pct',
    label: 'Closing %',
    unit: 'pct',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    compute: (r) => {
      const s = calculateClosingStats(r.jobs);
      return s.total === 0 ? null : s.rate;
    },
    denominator: (r) => calculateClosingStats(r.jobs).total,
    goalKeys: ['closing_rate', 'conversion_rate'],
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'estimate_close_pct',
    label: 'Estimate close %',
    unit: 'pct',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    compute: (r) => {
      const s = calculateEstimateConversionRate(r.jobs);
      return s.total === 0 ? null : s.rate;
    },
    denominator: (r) => calculateEstimateConversionRate(r.jobs).total,
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'membership_conv',
    label: 'Membership rate',
    unit: 'pct',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    compute: (r) => (getUniqueOpportunities(r.jobs).length === 0 ? null : calculateMembershipConversionRate(r.jobs)),
    denominator: (r) => getUniqueOpportunities(r.jobs).length,
    goalKeys: ['membership_rate'],
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'callback_rate',
    label: 'Callback rate',
    unit: 'pct',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    compute: (r) => (getUniqueOpportunities(r.jobs).length === 0 ? null : calculateCallbackRate(r.jobs)),
    denominator: (r) => getUniqueOpportunities(r.jobs).length,
    goalKeys: ['callback_rate'],
    perTech: true,
    higherIsBetter: false,
  },
  {
    key: 'cancellation_rate',
    label: 'Cancellation rate',
    unit: 'pct',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    compute: (r) => {
      const b = getOpportunitiesIncludingCanceled(r.jobs);
      return b.total === 0 ? null : calculateCancellationRate(r.jobs);
    },
    denominator: (r) => getOpportunitiesIncludingCanceled(r.jobs).total,
    goalKeys: ['cancellation_rate'],
    perTech: true,
    higherIsBetter: false,
  },
  {
    key: 'options_per_ticket',
    label: 'Options per ticket',
    unit: 'ratio',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'rate',
    // Two canonical variants exist. The per-tech one needs the unfiltered
    // ticket set to find the estimates written on that tech's visits, so
    // the engine hands both sets in and the flag picks the right function.
    compute: (r) => {
      const s = r.isTechScoped
        ? calculateAvgOptionsPerTicketForTech(r.jobs, r.allJobs)
        : calculateAvgOptionsPerTicket(r.jobs);
      return s.tickets === 0 ? null : s.average;
    },
    denominator: (r) =>
      (r.isTechScoped
        ? calculateAvgOptionsPerTicketForTech(r.jobs, r.allJobs)
        : calculateAvgOptionsPerTicket(r.jobs)
      ).tickets,
    goalKeys: ['options_per_ticket'],
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'total_opportunities',
    label: 'Opportunities',
    unit: 'count',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'sum',
    compute: (r) => getUniqueOpportunities(r.jobs).length,
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'appointments',
    label: 'Appointments',
    unit: 'count',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'sum',
    compute: (r) => countAppointments(r.jobs),
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'completed_jobs',
    label: 'Completed jobs',
    unit: 'count',
    dateBasis: 'completed_at',
    sources: ['jobs'],
    // True for the attribution reason above, not because compute needs it.
    needsHcpData: true,
    aggregate: 'sum',
    compute: (r) => r.jobs.filter((j) => j.completed_at).length,
    perTech: true,
    higherIsBetter: true,
  },
  {
    key: 'cancellations',
    label: 'Cancellations',
    unit: 'count',
    dateBasis: 'scheduled_at',
    sources: ['jobs'],
    needsHcpData: true,
    aggregate: 'sum',
    compute: (r) => getOpportunitiesIncludingCanceled(r.jobs).canceled,
    perTech: true,
    higherIsBetter: false,
  },
  {
    key: 'call_booking_rate',
    label: 'Call booking rate',
    unit: 'pct',
    dateBasis: 'row_date',
    sources: ['calls'],
    needsHcpData: false,
    aggregate: 'rate',
    compute: (r) => (r.calls.length === 0 ? null : calculateCallBookingRate(r.calls)),
    denominator: (r) => r.calls.length,
    goalKeys: ['call_booking_rate'],
    perTech: false,
    higherIsBetter: true,
  },
  {
    key: 'marketing_spend',
    label: 'Marketing spend',
    unit: 'usd',
    dateBasis: 'row_date',
    sources: ['marketing'],
    needsHcpData: false,
    aggregate: 'sum',
    compute: (r) => sum(r.marketingSpend, 'spend_amount'),
    perTech: false,
    higherIsBetter: false,
  },
  {
    key: 'marketing_leads',
    label: 'Marketing leads',
    unit: 'count',
    dateBasis: 'row_date',
    sources: ['marketing'],
    needsHcpData: false,
    aggregate: 'sum',
    // leads_generated counts FORM leads only, so call and messaging
    // campaigns read as 0 here. The chart footnote says so.
    compute: (r) => sum(r.marketingSpend, 'leads_generated'),
    perTech: false,
    higherIsBetter: true,
  },
];

export function getTrendKpi(key: string): TrendKpiDef | undefined {
  return TREND_KPIS.find((k) => k.key === key);
}

export function trendKpisForTech(): TrendKpiDef[] {
  return TREND_KPIS.filter((k) => k.perTech);
}
```

- [ ] **Step 4: Run the test**

Run: `npx vitest --run src/lib/trends/__tests__/kpi-registry.test.ts`
Expected: PASS, 7 tests.

The empty-input test only proves an entry gaps when it has NOTHING. The real hazard is a bucket FULL of rows whose canonical denominator is still empty: every `calculate*` returns a bare `0` there, and a plotted `0` on a rate is a claim, not an absence. `total_job_avg` and `call_booking_rate` both have this shape — their gap condition must count the canonical denominator (completed non-estimate jobs; calls flagged `is_lead_opportunity`), not `r.jobs.length` / `r.calls.length`. Add non-empty tests for both. If an entry cannot satisfy a test, work out whether the entry or the test is wrong before changing either.

- [ ] **Step 5: Commit**

```bash
git add src/lib/trends/kpi-registry.ts src/lib/trends/__tests__/kpi-registry.test.ts
git commit -m "feat(trends): declare the KPI registry, delegating all math to the canonical library"
```

---

## Task 6: The series engine

**Files:**
- Create: `src/lib/trends/build-series.ts`
- Create: `src/lib/trends/__tests__/build-series.test.ts`

- [ ] **Step 1: Write the failing test**

The last test here is the most important one in the plan: it is the regression guard that stops a second `useTechWeeklyKpi` from ever appearing.

```ts
// src/lib/trends/__tests__/build-series.test.ts
import { describe, it, expect } from 'vitest';
import { buildSeries } from '../build-series';
import { getTrendKpi, TREND_KPIS, type TrendInputs } from '../kpi-registry';
// Task 2 exports neither MAURICE_HCP_ID nor MAURICE_TECH_ID — it derives
// its roster from src/lib/technicians.ts. Do the same here.
import { CHARLES_HCP_ID, CHARLES_TECH_ID } from '../attribution';
import { TECHNICIANS } from '@/lib/technicians';
const MAURICE_HCP_ID = TECHNICIANS.find((t) => t.name === 'Maurice Williams')!.hcpId;
const MAURICE_TECH_ID = TECHNICIANS.find((t) => t.name === 'Maurice Williams')!.id;

let seq = 0;
// tech_id MUST be a real tech UUID. buildOpportunities drops non-estimate
// rows with no tech_id, so a null default makes getUniqueOpportunities
// return [] for every fixture and the AGREEMENT loop below silently
// compares null to null — a green test that verifies nothing.
const mkJob = (over: Record<string, unknown> = {}) => ({
  id: `id-${++seq}`,
  job_id: `job-${seq}`,
  tech_id: MAURICE_TECH_ID,
  revenue_amount: 500,
  job_type: 'Repair',
  status: 'completed',
  is_opportunity: true,
  is_callback: false,
  sold_threshold: null,
  lead_source: null,
  membership_attached: false,
  scheduled_at: '2026-08-04T14:00:00Z',
  completed_at: '2026-08-04T18:00:00Z',
  invoice_paid_at: null,
  estimate_status: null,
  source_estimate_id: null,
  hcp_data: { customer: { id: `cust-${seq}` }, assigned_employees: [] },
  ...over,
});

const inputs = (jobs: unknown[]): TrendInputs => ({
  jobs: jobs as never[],
  calls: [],
  marketingSpend: [],
  allJobs: jobs as never[],
  isTechScoped: false,
});

const WINDOW = { from: new Date(2026, 6, 1), to: new Date(2026, 7, 31) };

describe('buildSeries', () => {
  it('emits one point per bucket including empty ones', () => {
    const series = buildSeries(getTrendKpi('revenue')!, inputs([mkJob()]), {
      granularity: 'month',
      window: WINDOW,
    });
    expect(series).toHaveLength(2);
    expect(series[0].label).toBe('Jul 2026');
    expect(series[1].label).toBe('Aug 2026');
  });

  it('sums a countable KPI into its bucket and leaves other buckets at zero', () => {
    const series = buildSeries(
      getTrendKpi('revenue')!,
      inputs([mkJob({ revenue_amount: 1000 }), mkJob({ revenue_amount: 250 })]),
      { granularity: 'month', window: WINDOW },
    );
    expect(series[0].value).toBe(0);
    expect(series[1].value).toBe(1250);
  });

  it('gaps a rate KPI in an empty bucket instead of plotting zero', () => {
    const series = buildSeries(getTrendKpi('conversion')!, inputs([mkJob()]), {
      granularity: 'month',
      window: WINDOW,
    });
    expect(series[0].value).toBeNull();
    expect(series[1].value).not.toBeNull();
  });

  it('reports the denominator as the sample size', () => {
    const series = buildSeries(getTrendKpi('conversion')!, inputs([mkJob(), mkJob()]), {
      granularity: 'month',
      window: WINDOW,
    });
    expect(series[1].n).toBe(2);
  });

  it('skips rows whose basis date is missing', () => {
    const series = buildSeries(
      getTrendKpi('revenue')!,
      inputs([mkJob(), mkJob({ completed_at: null })]),
      { granularity: 'month', window: WINDOW },
    );
    expect(series[1].value).toBe(500);
  });

  it('applies the Charles Solo Rule when a tech is selected', () => {
    const shared = mkJob({
      revenue_amount: 900,
      hcp_data: {
        customer: { id: 'c1' },
        assigned_employees: [{ id: CHARLES_HCP_ID }, { id: MAURICE_HCP_ID }],
      },
    });
    const series = buildSeries(getTrendKpi('revenue')!, inputs([shared]), {
      granularity: 'month',
      window: WINDOW,
      techId: MAURICE_TECH_ID,
    });
    expect(series[1].value).toBe(900);
  });

  it('buckets by each KPI own date basis', () => {
    // Scheduled in July, completed in August.
    const straddler = mkJob({
      scheduled_at: '2026-07-28T14:00:00Z',
      completed_at: '2026-08-04T18:00:00Z',
      revenue_amount: 700,
    });
    const byCompletion = buildSeries(getTrendKpi('revenue')!, inputs([straddler]), {
      granularity: 'month',
      window: WINDOW,
    });
    const bySchedule = buildSeries(getTrendKpi('total_opportunities')!, inputs([straddler]), {
      granularity: 'month',
      window: WINDOW,
    });
    expect(byCompletion[1].value).toBe(700); // August, by completion
    expect(bySchedule[0].value).toBe(1); // July, by schedule
  });

  it('AGREEMENT: one bucket spanning the window equals the canonical value', () => {
    // The guard that keeps trends and tiles from ever drifting apart.
    // A single-bucket series must reproduce exactly what the tile shows.
    const jobs = [
      mkJob({ revenue_amount: 1200 }),
      mkJob({ revenue_amount: 0, status: 'canceled', completed_at: null }),
      mkJob({ job_type: 'Estimate', estimate_status: 'open', completed_at: null, revenue_amount: 0 }),
      mkJob({ revenue_amount: 3400, job_type: 'Door Install' }),
      mkJob({ revenue_amount: 800, is_callback: true }),
    ];
    const rows = inputs(jobs);

    for (const kpi of TREND_KPIS.filter((k) => k.sources.length === 1 && k.sources[0] === 'jobs')) {
      const series = buildSeries(kpi, rows, { granularity: 'quarter', window: WINDOW });
      const nonNull = series.filter((p) => p.value !== null);
      const direct = kpi.compute(rows);

      if (direct === null) {
        expect(nonNull, `${kpi.key} should have produced no value`).toHaveLength(0);
        continue;
      }
      // July-September is one quarter bucket, so exactly one point carries the value.
      expect(nonNull, `${kpi.key} should land in one bucket`).toHaveLength(1);
      expect(nonNull[0].value, `${kpi.key} trend must equal the tile`).toBeCloseTo(direct, 6);
    }
  });
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `npx vitest --run src/lib/trends/__tests__/build-series.test.ts`
Expected: FAIL, cannot resolve `../build-series`.

- [ ] **Step 3: Implement**

```ts
// src/lib/trends/build-series.ts
import { creditedJobsFor } from './attribution';
import { bucketDateFor } from './bucket-date';
import { bucketKey, bucketLabel, bucketStart, eachBucket, type Granularity } from './granularity';
import type { TrendInputs, TrendKpiDef } from './kpi-registry';

export interface TrendPoint {
  /** Bucket start as yyyy-MM-dd. Stable id for the x axis. */
  bucketStart: string;
  label: string;
  /** null means no denominator in this bucket: render a gap, never a zero. */
  value: number | null;
  /** Sample size behind the value, surfaced in the tooltip. Never used to filter. */
  n: number;
}

export interface BuildSeriesOptions {
  granularity: Granularity;
  window: { from: Date; to: Date };
  /** Technician UUID. Omit for companywide. */
  techId?: string;
}

/**
 * Run one canonical KPI function once per time bucket.
 *
 * This is the whole engine. It contains no KPI math of its own by design:
 * every number it returns came out of src/lib/kpi-calculations.ts via the
 * registry entry's `compute`.
 */
export function buildSeries(
  kpi: TrendKpiDef,
  rows: TrendInputs,
  opts: BuildSeriesOptions,
): TrendPoint[] {
  const { granularity: g, window, techId } = opts;

  const scopedJobs = techId ? creditedJobsFor(rows.jobs, techId) : rows.jobs;

  const buckets = eachBucket(window.from, window.to, g);
  const keys = buckets.map((b) => bucketKey(b, g));

  const jobsByBucket = new Map<string, unknown[]>();
  const allJobsByBucket = new Map<string, unknown[]>();
  const callsByBucket = new Map<string, unknown[]>();
  const spendByBucket = new Map<string, unknown[]>();
  for (const k of keys) {
    jobsByBucket.set(k, []);
    allJobsByBucket.set(k, []);
    callsByBucket.set(k, []);
    spendByBucket.set(k, []);
  }

  const place = (row: Record<string, unknown>, into: Map<string, unknown[]>) => {
    const d = bucketDateFor(row, kpi.dateBasis);
    if (!d) return;
    const target = into.get(bucketKey(bucketStart(d, g), g));
    if (target) target.push(row);
  };

  for (const job of scopedJobs) place(job, jobsByBucket);
  // allJobs stays unscoped: calculateAvgOptionsPerTicketForTech needs the
  // full ticket set to find the estimates written on a tech's visits.
  for (const job of rows.allJobs) place(job, allJobsByBucket);
  for (const call of rows.calls) place(call, callsByBucket);
  for (const spend of rows.marketingSpend) place(spend, spendByBucket);

  return buckets.map((b, i) => {
    const key = keys[i];
    const subset: TrendInputs = {
      jobs: (jobsByBucket.get(key) ?? []) as never[],
      allJobs: (allJobsByBucket.get(key) ?? []) as never[],
      calls: (callsByBucket.get(key) ?? []) as never[],
      marketingSpend: (spendByBucket.get(key) ?? []) as never[],
      isTechScoped: !!techId,
    };
    return {
      bucketStart: key,
      label: bucketLabel(b, g),
      value: kpi.compute(subset),
      // Fall back to the row count of the KPI's OWN sources, not jobs.
      // marketing_spend and marketing_leads read no job rows at all, so
      // subset.jobs.length would report n = 0 beside a real dollar figure.
      n: kpi.denominator ? kpi.denominator(subset) : defaultSampleSize(kpi, subset),
    };
  });
}
```

- [ ] **Step 4: Run the test**

Run: `npx vitest --run src/lib/trends/__tests__/build-series.test.ts`
Expected: PASS, 8 tests.

If the AGREEMENT test fails for a KPI, the registry entry is wrong, not the test. Fix the entry to match what the tile does.

**Know what this test cannot catch.** It runs `buildSeries` and `kpi.compute` over the *same in-memory row set*, so it proves the bucketing is faithful — not that the row set itself matches what the tile was fed. A fetch that pulls the wrong rows passes this test with the trend still disagreeing with the tile. That failure mode is real: `calculateEstimateConversionRate` counts raw estimate rows rather than going through `getUniqueOpportunities`, and 1,102 of 1,305 estimates in a 12-month window have no `scheduled_at`, so a scheduled-only fetch would shrink its denominator about fivefold while every test here still passed. Task 7 fetches estimates the way `useDashboardData` does for that reason, and Task 13 checks fetch parity against a live tile.

- [ ] **Step 5: Commit**

```bash
git add src/lib/trends/build-series.ts src/lib/trends/__tests__/build-series.test.ts
git commit -m "feat(trends): add the bucketed series engine with a tile-agreement guard"
```

---

## Task 7: The data hook

**Files:**
- Create: `src/hooks/use-trend-data.ts`

No unit test: this is a thin Supabase fetch with no branching logic worth mocking. It is exercised by the manual verification in Task 12.

- [ ] **Step 1: Implement**

```ts
// src/hooks/use-trend-data.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import type { SourceKey, TrendInputs } from '@/lib/trends/kpi-registry';

/** Columns every canonical KPI function reads, minus the fat hcp_data blob. */
const NARROW_COLUMNS = [
  'id', 'job_id', 'tech_id', 'revenue_amount', 'job_type', 'status',
  'is_opportunity', 'is_callback', 'sold_threshold', 'lead_source',
  'membership_attached', 'scheduled_at', 'started_at', 'completed_at',
  'invoice_paid_at', 'estimate_status', 'source_estimate_id',
].join(',');

const BATCH = 1000;
const MAX_ROWS = 10000;

const iso = (d: Date, endOfDay = false) => {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}T${endOfDay ? '23:59:59' : '00:00:00'}Z`;
};

const dateOnly = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

async function fetchAll<T>(
  build: (offset: number) => PromiseLike<{ data: T[] | null; error: unknown }>,
): Promise<T[]> {
  const out: T[] = [];
  let offset = 0;
  for (;;) {
    const { data, error } = await build(offset);
    if (error) throw error;
    if (!data?.length) break;
    out.push(...data);
    if (data.length < BATCH || out.length >= MAX_ROWS) break;
    offset += BATCH;
  }
  return out;
}

export interface UseTrendDataArgs {
  window: { from: Date; to: Date };
  sources: SourceKey[];
  needsHcpData: boolean;
  enabled?: boolean;
}

/**
 * Fetches the raw rows a trend needs for a window.
 *
 * Deliberately NOT keyed on the KPI: switching KPIs inside the same window
 * with the same data needs is a cache hit and costs no network. Jobs are
 * fetched on BOTH date bases (union) so a KPI keyed on completed_at and one
 * keyed on scheduled_at can share a single cached row set. This also fixes
 * the existing RevenueTrendChart bug, where rows fetched by scheduled_at
 * were bucketed by completed_at, silently dropping work completed in range
 * but scheduled before it.
 */
export function useTrendData({ window, sources, needsHcpData, enabled = true }: UseTrendDataArgs) {
  const from = iso(window.from);
  const to = iso(window.to, true);
  const columns = needsHcpData ? `${NARROW_COLUMNS},hcp_data` : NARROW_COLUMNS;
  const estimateColumns = `${NARROW_COLUMNS},hcp_data`;

  return useQuery({
    enabled,
    staleTime: 5 * 60_000,
    queryKey: ['trend-rows', from, to, [...sources].sort().join(','), needsHcpData],
    queryFn: async (): Promise<TrendInputs> => {
      const wantJobs = sources.includes('jobs');
      const wantCalls = sources.includes('calls');
      const wantMarketing = sources.includes('marketing');

      const [scheduled, completed, estimates, calls, marketingSpend] = await Promise.all([
        wantJobs
          ? fetchAll((offset) =>
              supabase.from('jobs').select(columns).neq('job_type', 'Estimate')
                .gte('scheduled_at', from).lte('scheduled_at', to)
                .range(offset, offset + BATCH - 1))
          : Promise.resolve([]),
        wantJobs
          ? fetchAll((offset) =>
              supabase.from('jobs').select(columns).neq('job_type', 'Estimate')
                .gte('completed_at', from).lte('completed_at', to)
                .range(offset, offset + BATCH - 1))
          : Promise.resolve([]),
        // Estimates need their own unfiltered read, exactly as
        // useDashboardData does. MEASURED 2026-08-07: 1,102 of the 1,305
        // estimates in a 12-month window carry NO scheduled_at and no
        // completed_at, so the two reads above would miss ~85% of them.
        // getUniqueOpportunities drops those rows anyway, so opportunity
        // KPIs would not notice — but calculateEstimateConversionRate does
        // NOT go through getUniqueOpportunities. It counts raw estimate
        // rows, so a scheduled-only fetch would shrink its denominator
        // about fivefold and the estimate close % trend would silently
        // disagree with its own tile.
        // Note the column list: the estimate read ALWAYS pulls hcp_data,
        // even when the selected KPI does not need it, because the
        // created_at that decides whether an estimate is in the window
        // lives inside that blob and nowhere else.
        wantJobs
          ? fetchAll((offset) =>
              supabase.from('jobs').select(estimateColumns).eq('job_type', 'Estimate')
                .range(offset, offset + BATCH - 1))
          : Promise.resolve([]),
        wantCalls
          ? fetchAll((offset) =>
              supabase.from('calls_inbound').select('*')
                .gte('date', dateOnly(window.from)).lte('date', dateOnly(window.to))
                .range(offset, offset + BATCH - 1))
          : Promise.resolve([]),
        wantMarketing
          ? fetchAll((offset) =>
              supabase.from('marketing_spend').select('*')
                .gte('date', dateOnly(window.from)).lte('date', dateOnly(window.to))
                .range(offset, offset + BATCH - 1))
          : Promise.resolve([]),
      ]);

      // Keep an estimate when EITHER hcp_data.created_at OR scheduled_at
      // falls in the window — the same client-side rule useDashboardData
      // applies, so the trend's row set matches the tile's row set.
      // Extracted to src/lib/trends/estimate-window.ts and unit-tested —
      // this predicate is where a silent trend/tile divergence hides.
      //
      // CRITICAL: the `to` end is NOT `<= to`. useDashboardData widens it
      // to `to + 24h - 1ms` (see its estimate branch, ~line 81) because
      // every caller hands it a midnight-normalized `to`. Using `<= to`
      // drops every estimate created during business hours on the final
      // day of the window — the newest bucket, the one users look at most.
      const DAY_MS = 24 * 60 * 60 * 1000;
      const start = window.from.getTime();
      const end = window.to.getTime() + DAY_MS - 1;
      const inWindow = (v: string | null | undefined) => {
        if (!v) return false;
        const t = new Date(v).getTime();
        return !Number.isNaN(t) && t >= start && t <= end;
      };
      const keptEstimates = (estimates as Array<Record<string, unknown>>).filter((e) => {
        const created = (e.hcp_data as { created_at?: string } | null)?.created_at;
        return inWindow(created) || inWindow(e.scheduled_at as string | null);
      });

      // Union the reads by id, then drop the CSR duplicate rows that
      // inflate opportunity counts, exactly as useDashboardData does.
      const byId = new Map<string, Record<string, unknown>>();
      for (const row of [...scheduled, ...completed, ...keptEstimates] as Array<Record<string, unknown>>) {
        byId.set(String(row.id), row);
      }
      const jobs = [...byId.values()].filter((j) => !String(j.job_id ?? '').includes('csr_'));

      // buildSeries narrows `jobs` per tech and flips isTechScoped itself.
      return {
        jobs: jobs as never[],
        allJobs: jobs as never[],
        calls: calls as never[],
        marketingSpend: marketingSpend as never[],
        isTechScoped: false,
      };
    },
  });
}
```

- [ ] **Step 2: Typecheck**

Run: `npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/hooks/use-trend-data.ts
git commit -m "feat(trends): fetch trend row windows, cached by window rather than by KPI"
```

---

## Task 8: The chart component

**Files:**
- Create: `src/components/dashboard/KpiTrendChart.tsx`

- [ ] **Step 1: Implement**

```tsx
// src/components/dashboard/KpiTrendChart.tsx
import { memo, useMemo } from 'react';
import {
  Bar, CartesianGrid, ComposedChart, Legend, Line, ReferenceLine,
  ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import type { TrendPoint } from '@/lib/trends/build-series';
import type { TrendKpiDef, TrendUnit } from '@/lib/trends/kpi-registry';

// Mirror --rd-navy and --rd-yellow from src/redesign.css.
const NAVY = '#0E2148';
const GOLD = '#F7B801';
const SLATE = '#94A3B8';
const MUTED = '#64748B';

/** Overlay colors in rank order. Primary is always NAVY. */
const OVERLAY_COLORS = [SLATE, '#7C3AED', '#0F766E'];

export interface TrendOverlay {
  id: string;
  label: string;
  points: TrendPoint[];
  /** When set and different from the primary unit, renders on the right axis. */
  unit?: TrendUnit;
}

export interface KpiTrendChartProps {
  kpi: TrendKpiDef;
  points: TrendPoint[];
  overlays?: TrendOverlay[];
  /** Bucket-scaled goal value, or null when the KPI has no goal. */
  goalValue?: number | null;
  height?: number;
}

/**
 * Axis ticks may abbreviate currency to $40k. Tooltips and labels never do:
 * Daniel wants full dollar amounts everywhere except chart ticks.
 */
function formatTick(v: number, unit: TrendUnit): string {
  if (unit === 'usd') return Math.abs(v) >= 1000 ? `$${Math.round(v / 1000)}k` : `$${Math.round(v)}`;
  if (unit === 'pct') return `${Math.round(v)}%`;
  if (unit === 'ratio') return v.toFixed(1);
  return String(Math.round(v));
}

function formatFull(v: number, unit: TrendUnit): string {
  if (unit === 'usd') {
    return new Intl.NumberFormat('en-US', {
      style: 'currency', currency: 'USD', maximumFractionDigits: 0,
    }).format(v);
  }
  if (unit === 'pct') return `${v.toFixed(1)}%`;
  if (unit === 'ratio') return v.toFixed(2);
  return String(Math.round(v));
}

function KpiTrendChartBase({ kpi, points, overlays = [], goalValue, height = 300 }: KpiTrendChartProps) {
  const data = useMemo(() => {
    return points.map((p, i) => {
      const row: Record<string, unknown> = {
        label: p.label,
        bucketStart: p.bucketStart,
        primary: p.value,
        n: p.n,
      };
      overlays.forEach((o) => {
        row[o.id] = o.points[i]?.value ?? null;
      });
      return row;
    });
  }, [points, overlays]);

  const hasRightAxis = overlays.some((o) => o.unit && o.unit !== kpi.unit);

  if (points.length === 0) {
    return (
      <div className="h-[300px] grid place-items-center text-sm text-muted-foreground">
        No data in this window yet.
      </div>
    );
  }

  return (
    <ResponsiveContainer width="100%" height={height}>
      <ComposedChart data={data} margin={{ top: 8, right: hasRightAxis ? 8 : 4, bottom: 0, left: 0 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
        <XAxis dataKey="label" tick={{ fontSize: 11, fill: MUTED }} tickLine={false} axisLine={false} />
        <YAxis
          yAxisId="left"
          tick={{ fontSize: 11, fill: MUTED }}
          tickLine={false}
          axisLine={false}
          tickFormatter={(v: number) => formatTick(v, kpi.unit)}
        />
        {hasRightAxis && (
          <YAxis
            yAxisId="right"
            orientation="right"
            tick={{ fontSize: 11, fill: MUTED }}
            tickLine={false}
            axisLine={false}
          />
        )}
        <Tooltip
          contentStyle={{ backgroundColor: '#fff', border: '1px solid #E2E8F0', borderRadius: 8 }}
          // Key off dataKey, NOT name. Recharts sets the tooltip item's
          // `name` to `name || dataKey`, and every series here passes an
          // explicit `name`, so `name` is always the DISPLAY LABEL and a
          // comparison against 'primary' can never match — every overlay
          // would silently format with the primary series' unit.
          formatter={(value: number | null, name: string, item: { dataKey?: string }) => {
            if (value === null || value === undefined) return ['no data', name];
            const key = item?.dataKey;
            const unit = key === 'primary' ? kpi.unit : (overlays.find((o) => o.id === key)?.unit ?? kpi.unit);
            return [formatFull(value, unit), name];
          }}
          labelFormatter={(label: string, payload) => {
            const n = payload?.[0]?.payload?.n;
            return typeof n === 'number' ? `${label} · sample of ${n}` : label;
          }}
        />
        {overlays.length > 0 && <Legend wrapperStyle={{ fontSize: 11 }} />}

        {goalValue != null && goalValue > 0 && (
          <ReferenceLine
            yAxisId="left"
            y={goalValue}
            stroke={GOLD}
            strokeWidth={2}
            strokeDasharray="6 4"
            // 'right' clips against margin.right: 8. TechTrendChart only
            // gets away with it by pairing margin.right: 56 with abbreviated
            // dollars, and full dollar amounts are required here.
            label={{ value: `goal ${formatFull(goalValue, kpi.unit)}`, position: 'insideTopRight', fontSize: 11, fill: '#B07E00' }}
          />
        )}

        {kpi.aggregate === 'sum' ? (
          <Bar yAxisId="left" dataKey="primary" name={kpi.label} fill={NAVY} radius={[4, 4, 0, 0]} />
        ) : (
          <Line
            yAxisId="left"
            type="monotone"
            dataKey="primary"
            name={kpi.label}
            stroke={NAVY}
            strokeWidth={2.5}
            dot={false}
            // A rate with no denominator must break the line, not drop to zero.
            connectNulls={false}
          />
        )}

        {overlays.map((o, i) => (
          <Line
            key={o.id}
            yAxisId={o.unit && o.unit !== kpi.unit ? 'right' : 'left'}
            type="monotone"
            dataKey={o.id}
            name={o.label}
            stroke={OVERLAY_COLORS[i % OVERLAY_COLORS.length]}
            strokeWidth={2}
            strokeDasharray="5 4"
            dot={false}
            connectNulls={false}
          />
        ))}
      </ComposedChart>
    </ResponsiveContainer>
  );
}

export const KpiTrendChart = memo(KpiTrendChartBase);
```

- [ ] **Step 2: Typecheck and lint**

Run: `npx tsc --noEmit && npx eslint src/components/dashboard/KpiTrendChart.tsx`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/components/dashboard/KpiTrendChart.tsx
git commit -m "feat(trends): add the KPI trend chart with goal and overlay support"
```

---

## Task 9: The Trend tab, controls and compare picker

**Files:**
- Create: `src/components/dashboard/TrendTab.tsx`

- [ ] **Step 1: Implement**

```tsx
// src/components/dashboard/TrendTab.tsx
import { useMemo, useState } from 'react';
import { subDays, subYears, startOfYear } from 'date-fns';
import { KpiTrendChart, type TrendOverlay } from './KpiTrendChart';
import { useTrendData } from '@/hooks/use-trend-data';
import { useCompanyGoals } from '@/hooks/use-company-goals';
import { buildSeries } from '@/lib/trends/build-series';
import { autoGranularity, GRANULARITIES, periodGoal, type Granularity } from '@/lib/trends/granularity';
import { getTrendKpi } from '@/lib/trends/kpi-registry';
import { useLocalStorageString } from '@/hooks/useLocalStorageString';

type RangeKey = '30d' | '90d' | 'ytd' | '12mo';
const RANGES: readonly RangeKey[] = ['30d', '90d', 'ytd', '12mo'] as const;
const RANGE_LABELS: Record<RangeKey, string> = {
  '30d': '30d', '90d': '90d', ytd: 'YTD', '12mo': '12 mo',
};

type CompareKey = 'goal' | 'last_year' | 'prev_period' | 'company';
const MAX_OVERLAYS = 3;

// `to` MUST be midnight-normalized. useTrendData's query key is
// day-granular while isEstimateInWindow reads the raw Dates and widens
// `to` by a day itself, so a `to` carrying a time of day selects a
// different estimate set than the cache key implies. useToday() also
// gives the referentially-stable value react-query keys require here.
function windowFor(key: RangeKey, to: Date): { from: Date; to: Date } {
  if (key === '30d') return { from: subDays(to, 29), to };
  if (key === '90d') return { from: subDays(to, 89), to };
  if (key === 'ytd') return { from: startOfYear(to), to };
  return { from: subYears(to, 1), to };
}

export interface TrendTabProps {
  kpiKey: string;
  /** Technician UUID for an individual trend. Omit for companywide. */
  techId?: string;
  techName?: string;
}

export function TrendTab({ kpiKey, techId, techName }: TrendTabProps) {
  const kpi = getTrendKpi(kpiKey);
  const [rangeKey, setRangeKey] = useLocalStorageString<RangeKey>(
    'twins.trend.range', '12mo', RANGES,
  );
  const [granOverride, setGranOverride] = useState<Granularity | null>(null);
  const [compare, setCompare] = useState<CompareKey[]>(['goal']);

  const window = useMemo(() => windowFor(rangeKey), [rangeKey]);
  const granularity = granOverride ?? autoGranularity(window.from, window.to);

  const priorWindow = useMemo(() => {
    if (compare.includes('last_year')) {
      return { from: subDays(window.from, 364), to: subDays(window.to, 364) };
    }
    if (compare.includes('prev_period')) {
      // Must END THE DAY BEFORE window.from. Ending ON window.from makes
      // the prior series' last bucket this period's FIRST bucket, plotted
      // as though it were history.
      const spanDays = differenceInCalendarDays(window.to, window.from);
      return { from: subDays(window.from, spanDays + 1), to: subDays(window.from, 1) };
    }
    return null;
  }, [compare, window]);

  const { getGoal } = useCompanyGoals();
  const main = useTrendData({
    window,
    sources: kpi?.sources ?? ['jobs'],
    needsHcpData: kpi?.needsHcpData ?? true,
    enabled: !!kpi,
  });
  const prior = useTrendData({
    window: priorWindow ?? window,
    sources: kpi?.sources ?? ['jobs'],
    needsHcpData: kpi?.needsHcpData ?? true,
    enabled: !!kpi && !!priorWindow,
  });

  const points = useMemo(() => {
    if (!kpi || !main.data) return [];
    return buildSeries(kpi, main.data, { granularity, window, techId });
  }, [kpi, main.data, granularity, window, techId]);

  const overlays = useMemo<TrendOverlay[]>(() => {
    if (!kpi || !main.data) return [];
    const out: TrendOverlay[] = [];

    if (priorWindow && prior.data) {
      const priorPoints = buildSeries(kpi, prior.data, {
        granularity, window: priorWindow, techId,
      });
      out.push({
        id: 'prior',
        label: compare.includes('last_year') ? 'Same weeks last year' : 'Previous period',
        // Align by index so the two windows overlap on the x axis.
        points: points.map((_, i) => priorPoints[i] ?? { bucketStart: '', label: '', value: null, n: 0 }),
        unit: kpi.unit,
      });
    }

    if (techId && compare.includes('company')) {
      out.push({
        id: 'company',
        label: 'Company',
        points: buildSeries(kpi, main.data, { granularity, window }),
        unit: kpi.unit,
      });
    }

    return out.slice(0, MAX_OVERLAYS);
  }, [kpi, main.data, prior.data, priorWindow, compare, granularity, window, techId, points]);

  // NOTE: every hook, including the goalValue useMemo below, MUST sit
  // ABOVE this early return. A useMemo after it is a conditional hook:
  // React throws on any unknown kpiKey and rules-of-hooks fails lint.
  if (!kpi) return <div className="p-6 text-sm text-muted-foreground">No trend available for this metric.</div>;

  // goalKeys is a fallback chain mirroring the tiles: Closing % looks for
  // closing_rate then conversion_rate, because only eight goal keys are
  // seeded and closing_rate is not one of them. Resolving a single key
  // would draw no goal line under a gauge that shows a target of 80.
  //
  // Scaling: a 'sum' KPI accumulates over the bucket, so an annual target
  // must be divided down. A 'rate' KPI is a level and its target applies
  // to every bucket as-is. No goal found means no goal line, never an
  // invented default.
  const goalValue = useMemo(() => {
    if (!compare.includes('goal') || !kpi.goalKeys?.length) return null;
    for (const key of kpi.goalKeys) {
      const raw = getGoal(key);
      if (raw == null) continue;
      return kpi.aggregate === 'sum' ? periodGoal(raw, granularity) : raw;
    }
    return null;
  }, [compare, kpi, getGoal, granularity]);

  const toggle = (key: CompareKey) =>
    setCompare((c) => (c.includes(key) ? c.filter((x) => x !== key) : [...c, key].slice(-MAX_OVERLAYS - 1)));

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-2 px-4 sm:px-6">
        <div className="granularity-tabs" role="tablist" aria-label="Trend range">
          {RANGES.map((r) => (
            <button
              key={r}
              type="button"
              role="tab"
              aria-selected={rangeKey === r}
              className={`granularity-tab ${rangeKey === r ? 'active' : ''}`}
              onClick={() => { setRangeKey(r); setGranOverride(null); }}
            >
              {RANGE_LABELS[r]}
            </button>
          ))}
        </div>
        <div className="granularity-tabs" role="tablist" aria-label="Trend granularity">
          {GRANULARITIES.map((g) => (
            <button
              key={g}
              type="button"
              role="tab"
              aria-selected={granularity === g}
              className={`granularity-tab ${granularity === g ? 'active' : ''}`}
              onClick={() => setGranOverride(g)}
            >
              {g === 'quarter' ? 'Qtr' : g.charAt(0).toUpperCase() + g.slice(1)}
            </button>
          ))}
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2 px-4 sm:px-6 text-xs">
        <span className="text-muted-foreground">Compare:</span>
        {kpi.goalKeys?.length ? (
          <button type="button" className={`chip ${compare.includes('goal') ? 'chip-green' : ''}`} onClick={() => toggle('goal')}>
            Goal
          </button>
        ) : null}
        <button type="button" className={`chip ${compare.includes('last_year') ? 'chip-green' : ''}`} onClick={() => toggle('last_year')}>
          Last year
        </button>
        <button type="button" className={`chip ${compare.includes('prev_period') ? 'chip-green' : ''}`} onClick={() => toggle('prev_period')}>
          Previous period
        </button>
        {techId && (
          <button type="button" className={`chip ${compare.includes('company') ? 'chip-green' : ''}`} onClick={() => toggle('company')}>
            Company
          </button>
        )}
      </div>

      <div className="px-2 sm:px-4">
        {main.isLoading ? (
          <div className="h-[300px] grid place-items-center text-sm text-muted-foreground">Loading trend…</div>
        ) : (
          <KpiTrendChart kpi={kpi} points={points} overlays={overlays} goalValue={goalValue} />
        )}
      </div>

      <p className="px-4 sm:px-6 text-[11px] text-muted-foreground">
        {techName ? `${techName} · ` : 'Company · '}
        {kpi.label} by {granularity}.
        {kpi.key === 'marketing_leads' && ' Counts form leads only, so call and messaging campaigns read as zero.'}
      </p>
    </div>
  );
}
```

- [ ] **Step 2: Confirm the goal-key assumption**

Run: `grep -n "getGoal" src/hooks/use-company-goals.ts`
Expected: a `getGoal(key: string) => number | null | undefined` returned from the hook. If the signature differs, adapt the two `getGoal` call sites above to match; do not change the hook.

- [ ] **Step 3: Typecheck and lint**

Run: `npx tsc --noEmit && npx eslint src/components/dashboard/TrendTab.tsx`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add src/components/dashboard/TrendTab.tsx
git commit -m "feat(trends): add the Trend tab with range, granularity and compare controls"
```

---

## Task 10: The remaining three overlays

Task 9 shipped goal, last year, previous period and company average. The spec promises three more, and Daniel asked for the compare set to be "as extensive as possible": another technician, a second KPI on the right axis, and marketing spend on the right axis. `KpiTrendChart` already renders any overlay with a unit, so this task is picker plumbing only.

**Files:**
- Modify: `src/components/dashboard/TrendTab.tsx`

- [ ] **Step 1: Add the technician overlay**

Add a tech picker to the compare row and build one overlay per selected tech. Add near the other hooks:

```tsx
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

const { data: techs = [] } = useQuery({
  queryKey: ['trend-tech-options'],
  staleTime: 5 * 60_000,
  queryFn: async () => {
    const { data, error } = await supabase
      .from('technicians')
      .select('id,name')
      .eq('is_active', true);
    if (error) throw error;
    return (data ?? []) as Array<{ id: string; name: string }>;
  },
});

const [overlayTechIds, setOverlayTechIds] = useState<string[]>([]);
```

Render the picker after the existing compare chips, hidden when the KPI is not per-tech:

```tsx
{kpi.perTech && techs.map((t) => (
  <button
    key={t.id}
    type="button"
    className={`chip ${overlayTechIds.includes(t.id) ? 'chip-green' : ''}`}
    onClick={() =>
      setOverlayTechIds((ids) =>
        ids.includes(t.id) ? ids.filter((x) => x !== t.id) : [...ids, t.id],
      )
    }
  >
    {t.name}
  </button>
))}
```

Inside the `overlays` memo, before the `.slice`, push one series per selected tech:

```tsx
for (const id of overlayTechIds) {
  if (id === techId) continue;
  out.push({
    id: `tech-${id}`,
    label: techs.find((t) => t.id === id)?.name ?? 'Technician',
    points: buildSeries(kpi, main.data, { granularity, window, techId: id }),
    unit: kpi.unit,
  });
}
```

Add `overlayTechIds` and `techs` to the memo's dependency array.

- [ ] **Step 2: Add the second-KPI and marketing-spend overlays**

Both are the same mechanism: run `buildSeries` with a different registry entry over the same window. A different unit lands the series on the right axis automatically.

```tsx
const [secondKpiKey, setSecondKpiKey] = useState<string | null>(null);

const secondKpi = secondKpiKey ? getTrendKpi(secondKpiKey) : null;
const secondNeedsOtherSources =
  !!secondKpi && secondKpi.sources.some((s) => !(kpi?.sources ?? []).includes(s));

const second = useTrendData({
  window,
  sources: secondKpi?.sources ?? ['jobs'],
  needsHcpData: secondKpi?.needsHcpData ?? true,
  enabled: !!secondKpi && secondNeedsOtherSources,
});
```

In the `overlays` memo, using the second fetch only when the KPI needs sources the primary fetch did not pull:

```tsx
if (secondKpi) {
  const rows = secondNeedsOtherSources ? second.data : main.data;
  if (rows) {
    out.push({
      id: 'second',
      label: secondKpi.label,
      points: buildSeries(secondKpi, rows, { granularity, window, techId }),
      unit: secondKpi.unit,
    });
  }
}
```

Marketing spend needs no special case: it is the registry entry `marketing_spend`, so selecting it as the second KPI is exactly the spend overlay. Render a `<select>` in the compare row listing every registry entry except the primary:

```tsx
<select
  className="chip"
  value={secondKpiKey ?? ''}
  onChange={(e) => setSecondKpiKey(e.target.value || null)}
  aria-label="Overlay a second metric"
>
  <option value="">+ second metric</option>
  {TREND_KPIS.filter((k) => k.key !== kpi.key).map((k) => (
    <option key={k.key} value={k.key}>{k.label}</option>
  ))}
</select>
```

Import `TREND_KPIS` alongside `getTrendKpi`.

- [ ] **Step 3: Enforce the overlay cap**

The existing `.slice(0, MAX_OVERLAYS)` at the end of the memo already caps the rendered set at three. Confirm it still runs after the new pushes, so selecting four techs plus a second KPI cannot produce an unreadable chart.

- [ ] **Step 4: Typecheck and verify**

Run: `npx tsc --noEmit && npx eslint src/components/dashboard/TrendTab.tsx`
Expected: no errors.

In the browser, open a per-tech trend, add two technician overlays plus a second metric with a different unit, and confirm: a right-hand axis appears, the legend names all series, and no more than three overlays draw at once.

- [ ] **Step 5: Commit**

```bash
git add src/components/dashboard/TrendTab.tsx
git commit -m "feat(trends): add technician, second-metric and spend overlays to the compare picker"
```

---

## Task 11: Wire the Company Scorecard tiles

**Files:**
- Modify: `src/components/dashboard/DrilldownSheet.tsx`
- Modify: `src/pages/Index.tsx`

- [ ] **Step 1: Make the drill-down sheet tabbed**

In `src/components/dashboard/DrilldownSheet.tsx`, extend the props and wrap the existing body in tabs. The existing job-list rendering is unchanged; it just moves inside the Jobs tab.

```tsx
// Add to the imports
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { TrendTab } from './TrendTab';
import { getTrendKpi } from '@/lib/trends/kpi-registry';

// Extend the props interface
export interface DrilldownSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** KPI for the Jobs tab. Undefined when this KPI has no job list. */
  kpi?: DrilldownKpi;
  /** Registry key for the Trend tab. */
  trendKpiKey: string;
  techId?: string;
  techName?: string;
  dateRange?: DateRange;
}
```

Inside the component, gate the jobs query on the Jobs tab being available and render:

```tsx
const hasJobsTab = !!p.kpi;
const trendKpi = getTrendKpi(p.trendKpiKey);

return (
  <Sheet open={p.open} onOpenChange={p.onOpenChange}>
    <SheetContent side="right" className="w-full sm:max-w-2xl overflow-y-auto">
      <SheetHeader>
        <SheetTitle>
          {trendKpi?.label ?? 'Metric'}
          {p.techName ? ` · ${p.techName}` : ''}
        </SheetTitle>
      </SheetHeader>

      <Tabs defaultValue="trend" className="mt-4">
        <TabsList>
          <TabsTrigger value="trend">Trend</TabsTrigger>
          {hasJobsTab && <TabsTrigger value="jobs">Jobs</TabsTrigger>}
        </TabsList>

        <TabsContent value="trend">
          <TrendTab kpiKey={p.trendKpiKey} techId={p.techId} techName={p.techName} />
        </TabsContent>

        {hasJobsTab && (
          <TabsContent value="jobs">
            {/* Move the component's existing markup here verbatim: the
                loading/error branches and the `useVirtualizer` scroll
                container with its job rows. Do not rewrite it. Only the
                <SheetHeader> stays above, since the title is now shared. */}
          </TabsContent>
        )}
      </Tabs>
    </SheetContent>
  </Sheet>
);
```

Change the `useDrilldownJobs` call to `enabled: p.open && hasJobsTab` so opening a Trend-only KPI does not fire a job query.

- [ ] **Step 2: Add a single sheet-state hook to Index**

In `src/pages/Index.tsx`, add the `DrilldownKpi` type to the existing drill-down import:

```tsx
import { DrilldownSheet } from '@/components/dashboard/DrilldownSheet';
import type { DrilldownKpi } from '@/hooks/use-drilldown-jobs';
```

Then, near the other `useState` calls, replace the existing `revenueDrilldownOpen` state with:

```tsx
const [trendSheet, setTrendSheet] = useState<{ trendKpiKey: string; kpi?: DrilldownKpi } | null>(null);
const openTrend = useCallback(
  (trendKpiKey: string, kpi?: DrilldownKpi) => setTrendSheet({ trendKpiKey, kpi }),
  [],
);
```

- [ ] **Step 3: Make each tile clickable**

For each `<div className="kpi">` block, add the click affordance. The pattern, applied identically to every tile, differing only in the two arguments:

```tsx
<div
  className="kpi"
  role="button"
  tabIndex={0}
  style={{ cursor: 'pointer' }}
  onClick={() => openTrend('conversion')}
  onKeyDown={(e) => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openTrend('conversion'); }
  }}
>
```

Tile-to-key mapping. **VERIFIED against `Index.tsx` on 2026-08-07** — the earlier draft of this table listed tiles that do not exist and missed two that do. Confirm with:

```bash
grep -oE '<div className="label">[^<{]*' src/pages/Index.tsx | sed 's/<div className="label">//' | sort -u
```

The ten `.kpi` tiles that get a trend:

| Tile label | `trendKpiKey` | `kpi` (Jobs tab) |
|---|---|---|
| Opp. Job Avg ⭐ | `opportunity_avg` | — |
| Avg Sold Repair | `repair_avg` | — |
| Avg Sold Install | `install_avg` | — |
| Conversion | `conversion` | `closing` |
| Estimate Close % | `estimate_close_pct` | — |
| Callback Rate | `callback_rate` | `callback` |
| Cancellations | `cancellations` | — |
| Options / Ticket | `options_per_ticket` | — |
| Total Opportunities | `total_opportunities` | — |
| Completed Jobs | `completed_jobs` | — |

Handled separately, because they are not `.kpi` tiles:

- **Total Sales** is the large revenue card near line 635, with its own markup and an existing `onClick`. Map it to `revenue` / Jobs tab `revenue`.
- **Membership Rate**, **Call Booking Rate** and **Closing %** are `SemiGauge` cards in the gauge row (around line 745), not tiles. Wire the same click handler onto the gauge card wrapper: `membership_conv` (Jobs tab `membership`), `call_booking_rate`, and `closing_pct`.

Deliberately NOT clickable, and no registry entry exists for them:

- **Active Technicians** and **Scheduled** are point-in-time states, not period metrics. A trend of them would be meaningless.
- **Avg appts/day** — see below.

Two mismatches to respect rather than paper over:

- **Avg appts/day** shows a per-day average normalized to a five-day week. The registry's `appointments` entry is the per-bucket count, because a bucket is already a fixed-width period. Related, but not the same number. Wire the tile to `appointments` and leave the chart labeled "Appointments"; do not add a per-day variant and do not adjust either number to match the other.
- **Marketing Leads** has a tile, and `marketing_leads` is in the registry, but the tile reads from `marketing_spend.leads_generated`, which counts FORM leads only. Call and messaging campaigns read as zero. The chart footnote from Task 9 says so; do not try to "fix" the number.

**CPA has no tile.** The company-wide CPA card was deliberately removed — see the comment at `Index.tsx:803` — and CPA now appears as per-channel CAC on `/marketing-roi` with different math. There is no `cpa` registry entry; do not add one.

**Marketing Spend has no tile either.** Its registry entry exists only to be selectable as a right-axis overlay in Task 10.

The existing Total Sales tile already has `onClick={() => setRevenueDrilldownOpen(true)}` at line ~635. Replace it with `onClick={() => openTrend('revenue', 'revenue')}`. Its behavior changes from opening the job list directly to opening the sheet on Trend, with the same job list one tab away.

- [ ] **Step 4: Render the sheet once**

Replace the existing `<DrilldownSheet ... />` at the bottom of `Index.tsx` (line ~988) with:

```tsx
{trendSheet && (
  <DrilldownSheet
    open={!!trendSheet}
    onOpenChange={(o) => !o && setTrendSheet(null)}
    trendKpiKey={trendSheet.trendKpiKey}
    kpi={trendSheet.kpi}
    dateRange={dateRange}
  />
)}
```

- [ ] **Step 5: Verify in the browser**

Start the dev server with the preview tool (never `npm run dev` in Bash), open the Company Scorecard, and click through at least Total Sales, Conversion, Callback Rate and Options/Ticket. For each, confirm: the sheet opens on Trend, the chart draws, switching Day/Week/Month/Qtr redraws, and the Jobs tab appears only for Total Sales, Conversion, Membership and Callback. Check `read_console_messages` for errors.

Then confirm the numbers agree: set the page filter to YTD, note a tile value, open its trend, set the range to YTD and granularity to Qtr, and check the buckets are consistent with that tile. They will not be identical bucket-by-bucket (the tile is one number), but the shape and magnitude must be sane. Any wild mismatch means a registry `dateBasis` is wrong.

- [ ] **Step 6: Commit**

```bash
git add src/components/dashboard/DrilldownSheet.tsx src/pages/Index.tsx
git commit -m "feat(trends): open a Trend tab from every Company Scorecard KPI tile"
```

---

## Task 12: Wire the tech portal and delete the forked hook

This is the task that fixes per-tech closing %, which has rendered as `null` since `TechTrendChart` shipped.

**Files:**
- Modify: `src/components/tech/KpiDrillSheet.tsx`
- Modify: `src/pages/tech/Home.tsx`
- Modify: `src/components/tech/TechTrendChart.tsx`
- Delete: `src/hooks/admin/useTechWeeklyKpi.ts`

- [ ] **Step 1: Map the tech scorecard keys to registry keys**

The tech grid is driven by `scorecard_tier_thresholds.kpi_key`. Add this map to `src/lib/trends/kpi-registry.ts`:

```ts
/** scorecard_tier_thresholds.kpi_key -> TREND_KPIS key. */
export const TECH_KPI_KEY_TO_TREND: Record<string, string> = {
  revenue: 'revenue',
  total_jobs: 'completed_jobs',
  avg_opportunity: 'opportunity_avg',
  avg_repair: 'repair_avg',
  avg_install: 'install_avg',
  closing_pct: 'closing_pct',
  callback_pct: 'callback_rate',
  membership_pct: 'membership_conv',
  options_per_ticket: 'options_per_ticket',
};
```

- [ ] **Step 2: Add a Trend tab to the tech drill sheet**

In `src/components/tech/KpiDrillSheet.tsx`, add two props and wrap the existing content in tabs.

Extend `DrillSheetProps` with:

```ts
  /** Registry key for the Trend tab. */
  trendKpiKey: string;
  /** Technician UUID, so the trend is scoped to this tech. */
  techId?: string;
  techName?: string;
```

Add the imports:

```tsx
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { TrendTab } from '@/components/dashboard/TrendTab';
```

Then wrap the body. The tier-ladder and tip markup already in the file moves inside the second tab unchanged:

```tsx
<Tabs defaultValue="trend" className="mt-4">
  <TabsList>
    <TabsTrigger value="trend">Trend</TabsTrigger>
    <TabsTrigger value="ladder">Tier ladder</TabsTrigger>
  </TabsList>

  <TabsContent value="trend">
    <TrendTab kpiKey={p.trendKpiKey} techId={p.techId} techName={p.techName} />
  </TabsContent>

  <TabsContent value="ladder">
    {/* Move the existing ladder steps, TierBadge and how-to-improve tip
        markup here verbatim. Do not rewrite it. */}
  </TabsContent>
</Tabs>
```

- [ ] **Step 3: Pass the mapped key from Home**

In `src/pages/tech/Home.tsx`, where `KpiDrillSheet` is rendered (around line 641), pass:

```tsx
trendKpiKey={TECH_KPI_KEY_TO_TREND[drillKpi] ?? 'revenue'}
techId={effectiveUuid}
```

Import `TECH_KPI_KEY_TO_TREND` from `@/lib/trends/kpi-registry`.

- [ ] **Step 4: Rebuild TechTrendChart on the engine**

Replace the whole file. The old version imported `useTechWeeklyKpi`, declared `MetricKey` / `MetricDef` / `METRICS`, and carried `ytdWeekCount`, `fmtValue` and its own Recharts `AreaChart`. All of that goes: range, granularity, overlays and formatting now live in `TrendTab`.

```tsx
// src/components/tech/TechTrendChart.tsx
import { useState } from 'react';
import { TrendTab } from '@/components/dashboard/TrendTab';

/**
 * Per-tech KPI trend. Every series now comes from the shared trend engine,
 * which runs the canonical functions in src/lib/kpi-calculations.ts once per
 * bucket. The previous implementation summed revenue_amount itself and
 * hardcoded closing % to null because per-week opportunity dedup was never
 * written; both problems are gone because there is no longer any local math.
 */
const METRICS = [
  { key: 'revenue', label: 'Revenue' },
  { key: 'opportunity_avg', label: 'Avg ticket' },
  { key: 'completed_jobs', label: 'Jobs done' },
  { key: 'closing_pct', label: 'Closing %' },
] as const;

interface Props {
  /** Technician UUID. */
  technicianUuid?: string | null;
  /** Display name for the chart footnote. */
  technicianName?: string | null;
}

export function TechTrendChart({ technicianUuid, technicianName }: Props) {
  const [metric, setMetric] = useState<string>('revenue');

  if (!technicianUuid) return null;

  return (
    <div className="space-y-3">
      <div className="granularity-tabs" role="tablist" aria-label="Trend metric">
        {METRICS.map((m) => (
          <button
            key={m.key}
            type="button"
            role="tab"
            aria-selected={metric === m.key}
            className={`granularity-tab ${metric === m.key ? 'active' : ''}`}
            onClick={() => setMetric(m.key)}
          >
            {m.label}
          </button>
        ))}
      </div>
      <TrendTab kpiKey={metric} techId={technicianUuid} techName={technicianName ?? undefined} />
    </div>
  );
}
```

Closing % is no longer a special case: it flows through `calculateClosingStats` per bucket like every other KPI.

Then update the call site in `src/pages/tech/Home.tsx`. The old props (`weeklyGoalDollars`, `compareMode`, `impersonatedTechName`) no longer exist, because comparison now lives in the Trend tab's own picker:

```tsx
<TechTrendChart technicianUuid={effectiveUuid} technicianName={effectiveTechName} />
```

Run `grep -n "TechTrendChart" src/pages/tech/Home.tsx` first to find the exact call site and the in-scope variable holding the tech's display name.

- [ ] **Step 5: Delete the forked hook**

```bash
git rm src/hooks/admin/useTechWeeklyKpi.ts
```

Run: `grep -rn "useTechWeeklyKpi" src/`
Expected: no matches. If any remain, update those call sites to `TrendTab` before continuing.

- [ ] **Step 6: Verify**

Run: `npx tsc --noEmit && npx vitest --run src/`
Expected: no type errors, all tests pass.

In the browser, open `/tech?as=<a technician UUID>`, click the Closing % tile, and confirm the Trend tab draws a real line rather than an empty chart. Compare a co-assigned Charles ticket: it must appear in the junior tech's trend and not in Charles's.

- [ ] **Step 7: Commit**

```bash
git add -A src/components/tech src/pages/tech/Home.tsx src/lib/trends/kpi-registry.ts
git commit -m "feat(trends): move tech trends onto the shared engine and delete the forked weekly KPI hook"
```

---

## Task 13: Full verification and PR

- [ ] **Step 1: Run everything**

Run: `npx vitest --run src/ && npx tsc --noEmit && npx eslint src/lib/trends src/components/dashboard/KpiTrendChart.tsx src/components/dashboard/TrendTab.tsx`
Expected: all green.

- [ ] **Step 2: Check mobile**

Resize the preview to 375px wide. Open a trend sheet. Confirm the sheet fills the width, the control chips wrap instead of overflowing, and the chart does not push the page into horizontal scroll. "It extends to the sides" is a bug, not a nitpick.

- [ ] **Step 3: Check fetch parity against live tiles**

The unit tests cannot prove the trend fetched the same rows the tile was fed (see the note in Task 6). Verify it by hand, against real data, for the KPI most exposed to it.

Set the Company Scorecard filter to YTD and note the exact value on the **Estimate close %** tile. Open its Trend tab, set the range to YTD and the granularity to Qtr, and read the tooltip sample sizes. The denominators summed across buckets must match the tile's denominator. If the trend's total is dramatically smaller, the estimate read in `use-trend-data.ts` is not mirroring `useDashboardData` and unscheduled estimates are being dropped.

Repeat the same comparison for **Conversion rate** and **Revenue**, which use different date bases and so exercise different reads.

**Also check the null-basis-date gap.** `buildSeries` drops any row whose basis date is null, because it has no bucket to live in. Several canonical functions do not require that date: `calculateTotalJobAverage` counts a completed job with no `scheduled_at`, and `calculateEstimateConversionRate` counts unscheduled estimates. Those rows count in the tile and vanish from the trend. `bucketDateFor` softens this for estimates via the `hcp_data.created_at` fallback, and non-estimate rows deliberately have no fallback (Task 4 explains why), so some divergence here is inherent to plotting against time rather than a bug. The AGREEMENT test cannot see it, because it runs over rows that all have dates. Quantify it once against a live tile: if **Total job average** or **Estimate close %** differs from its tile by more than a rounding margin, count the null-`scheduled_at` rows in the window and confirm the difference is fully explained by them before shipping.

- [ ] **Step 4: Confirm no KPI math moved**

Run: `git diff main --stat -- src/lib/kpi-calculations.ts`
Expected: **empty output.** If this file appears in the diff, revert those changes before opening the PR.

- [ ] **Step 5: Open the PR**

```bash
git push -u origin feat/kpi-trends
```

Then open a PR against `main` titled `feat: KPI trend charts, companywide and per technician`, with a body covering: what shipped, the fact that all math delegates to `kpi-calculations.ts`, the deletion of `useTechWeeklyKpi` and the per-tech closing % fix, the measured payload numbers from Task 1, and the two definitional findings (the Total Sales revenue definition, and the `RevenueTrendChart` scheduled-vs-completed bucketing bug this replaces).

Vercel auto-deploys `main` on merge.

---

## Notes for the reviewer

Three things worth checking closely in review:

1. **No new KPI math.** Every `compute` in the registry should be a call into `kpi-calculations.ts` or a `reduce` that reproduces an existing tile expression verbatim. Anything else is a second definition.
2. **The AGREEMENT test in Task 6** is the guard that keeps trends and tiles from drifting. It should not be weakened to make a KPI pass; the registry entry should be fixed instead.
3. **`Index.tsx` gains ~15 click handlers** and is already 998 lines. The handlers are one-liners delegating to a single hook, so this adds no branching, but the file remains too large and a future task should split it.
