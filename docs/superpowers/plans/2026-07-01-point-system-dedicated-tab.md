# Point System Dedicated Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate the Technician Accountability point system into one dedicated top-level `Point System` page with live graphs/trends/data, and give the FOM (field_supervisor) write access to the point-values catalog with an audit trail.

**Architecture:** New route `/admin/point-system` renders a two-tab page (Overview + Rules). Overview lifts the trend/ranking/severity charts out of the PDF-only path into live on-screen Recharts driven by the existing `useAccountabilityReportData` hook, plus a KPI strip and the existing per-tech table (whose rows already carry the "Review day" action). Rules exposes the existing point-values editor, now writable by field_supervisor, with every change logged by a database trigger into `violation_type_history`. The Notifications page loses its embedded "Tech Accountability" sub-tab. The scoring engine, ledger, decay, digest, and ladder math are untouched.

**Tech Stack:** Vite + React + TypeScript, TanStack Query, Recharts, shadcn/ui, Supabase (jwrpj), vitest. Repo: `/Users/daniel/twins-dashboard/twins-dash`.

---

## Pre-flight

- [ ] **Create an isolated branch/worktree** (shared checkouts clobber each other — commit early).

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git checkout -b feat/point-system-tab
git status   # expect clean tree on the new branch
```

---

## Task 1: Database migration — FOM edit access + audit trail for `violation_types`

**Files:**
- Create: `supabase/migrations/20260701120000_point_system_fom_edit_audit.sql`

Existing table (for reference, from `20260626120000_accountability_violation_types.sql`): `violation_types(code PK, label, points, severity, is_checklist_item, auto_detectable, active, sort_order, created_at)` with RLS read-for-all-authenticated and manage-for-admin using `public.has_role(auth.uid(), 'admin'::public.app_role)`. The `field_supervisor` value already exists on `public.app_role`.

- [ ] **Step 1: Write the migration**

Create `supabase/migrations/20260701120000_point_system_fom_edit_audit.sql`:

```sql
-- Point System: let the FOM (field_supervisor) edit point values, and log
-- every change to violation_types. The scoring engine is unaffected; only
-- who may write the catalog changes, plus a new audit trail.

-- 1. Audit columns on the catalog.
ALTER TABLE public.violation_types
  ADD COLUMN IF NOT EXISTS updated_at timestamptz,
  ADD COLUMN IF NOT EXISTS updated_by uuid;

-- 2. History table: one row per changed field per update.
CREATE TABLE IF NOT EXISTS public.violation_type_history (
  id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  code          text NOT NULL REFERENCES public.violation_types(code) ON DELETE CASCADE,
  field_changed text NOT NULL,
  old_value     text,
  new_value     text,
  changed_by    uuid,
  changed_at    timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS violation_type_history_code_idx
  ON public.violation_type_history (code, changed_at DESC);

-- 3. Trigger: stamp updated_at/updated_by and log points/active changes.
CREATE OR REPLACE FUNCTION public.log_violation_type_change()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  NEW.updated_at := now();
  NEW.updated_by := auth.uid();

  IF NEW.points IS DISTINCT FROM OLD.points THEN
    INSERT INTO public.violation_type_history (code, field_changed, old_value, new_value, changed_by)
    VALUES (NEW.code, 'points', OLD.points::text, NEW.points::text, auth.uid());
  END IF;

  IF NEW.active IS DISTINCT FROM OLD.active THEN
    INSERT INTO public.violation_type_history (code, field_changed, old_value, new_value, changed_by)
    VALUES (NEW.code, 'active', OLD.active::text, NEW.active::text, auth.uid());
  END IF;

  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_log_violation_type_change ON public.violation_types;
CREATE TRIGGER trg_log_violation_type_change
  BEFORE UPDATE ON public.violation_types
  FOR EACH ROW EXECUTE FUNCTION public.log_violation_type_change();

-- 4. RLS: field_supervisor may now UPDATE the catalog (admin policy stays).
DROP POLICY IF EXISTS "field_supervisor update violation_types" ON public.violation_types;
CREATE POLICY "field_supervisor update violation_types" ON public.violation_types
  FOR UPDATE TO authenticated
  USING (public.has_role(auth.uid(), 'field_supervisor'::public.app_role))
  WITH CHECK (public.has_role(auth.uid(), 'field_supervisor'::public.app_role));

-- 5. History RLS: admin + field_supervisor may read; writes only via trigger.
ALTER TABLE public.violation_type_history ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "read violation_type_history" ON public.violation_type_history;
CREATE POLICY "read violation_type_history" ON public.violation_type_history
  FOR SELECT TO authenticated
  USING (
    public.has_role(auth.uid(), 'admin'::public.app_role)
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  );

GRANT SELECT ON public.violation_type_history TO authenticated;
```

- [ ] **Step 2: Apply the migration to jwrpj**

CLI `db push` is blocked by this repo's migration-history desync. Apply via the Supabase MCP `apply_migration` tool with `name: "point_system_fom_edit_audit"` and the SQL body above (project ref jwrpj).

- [ ] **Step 3: Record the migration version (history desync workaround)**

Via Supabase MCP `execute_sql`:

```sql
INSERT INTO supabase_migrations.schema_migrations (version, name)
VALUES ('20260701120000', 'point_system_fom_edit_audit')
ON CONFLICT (version) DO NOTHING;
```

- [ ] **Step 4: Verify the policy, trigger, and history wiring**

Via Supabase MCP `execute_sql`, confirm the objects exist:

```sql
SELECT policyname FROM pg_policies
  WHERE tablename = 'violation_types' AND policyname = 'field_supervisor update violation_types';
SELECT tgname FROM pg_trigger WHERE tgname = 'trg_log_violation_type_change';
SELECT to_regclass('public.violation_type_history') AS history_table;
```
Expected: one policy row, one trigger row, `history_table` = `violation_type_history` (not null).

- [ ] **Step 5: Smoke-test the trigger writes history**

Via `execute_sql`, flip a value and back, then confirm two history rows were logged (changed_by will be null under the service role — that is expected; real edits carry `auth.uid()`):

```sql
UPDATE public.violation_types SET points = points WHERE code = 'dirty_work_area';  -- no-op, no history
UPDATE public.violation_types SET active = NOT active WHERE code = 'dirty_work_area';
UPDATE public.violation_types SET active = NOT active WHERE code = 'dirty_work_area';
SELECT field_changed, old_value, new_value FROM public.violation_type_history
  WHERE code = 'dirty_work_area' ORDER BY changed_at DESC LIMIT 2;
```
Expected: two rows, `field_changed = 'active'`. Then clean up: `DELETE FROM public.violation_type_history WHERE code = 'dirty_work_area';`

- [ ] **Step 6: Commit**

```bash
git add supabase/migrations/20260701120000_point_system_fom_edit_audit.sql
git commit -m "feat(point-system): FOM catalog write access + violation_type_history audit"
```

---

## Task 2: Overview KPI helper (pure logic, TDD)

Keep the KPI math out of the component so it is unit-tested. Uses only fields the report already exposes (`teamSeries`, `perTech[].balanceAsOfEnd`, `perTech[].daysClean`, `perTech[].name`).

**Files:**
- Create: `src/lib/accountability/overview.ts`
- Test: `src/lib/accountability/__tests__/overview.test.ts`

- [ ] **Step 1: Write the failing test**

Create `src/lib/accountability/__tests__/overview.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { deriveOverviewKpis } from "../overview";
import type { AccountabilityReport } from "../report";

function makeReport(): AccountabilityReport {
  return {
    period: { startIso: "2026-06-01", endIso: "2026-06-30", granularity: "week" },
    buckets: ["2026-06-01", "2026-06-08"],
    teamSeries: [
      { bucket: "2026-06-01", points: 5 },
      { bucket: "2026-06-08", points: 3 },
    ],
    perTech: [
      {
        technician_id: "a", name: "Nicholas", periodPoints: 6, balanceAsOfEnd: 8,
        level: 4 as never, severity: { minor: 1, serious: 1, major: 1 }, daysClean: 0,
        series: [],
      },
      {
        technician_id: "b", name: "Maurice", periodPoints: 2, balanceAsOfEnd: 3,
        level: 1 as never, severity: { minor: 2, serious: 0, major: 0 }, daysClean: 12,
        series: [],
      },
      {
        technician_id: "c", name: "Ivy", periodPoints: 0, balanceAsOfEnd: 1,
        level: 0 as never, severity: { minor: 0, serious: 0, major: 0 }, daysClean: 30,
        series: [],
      },
    ],
    violations: [],
    actions: [],
  };
}

describe("deriveOverviewKpis", () => {
  it("sums team points across buckets", () => {
    expect(deriveOverviewKpis(makeReport()).totalPoints).toBe(8);
  });

  it("counts techs at or above the first level threshold (balance >= 2)", () => {
    const k = deriveOverviewKpis(makeReport());
    expect(k.techsAtLevel).toBe(2);
    expect(k.totalTechs).toBe(3);
  });

  it("reports the highest current balance", () => {
    expect(deriveOverviewKpis(makeReport()).highestBalance).toEqual({ name: "Nicholas", balance: 8 });
  });

  it("reports the longest clean streak", () => {
    expect(deriveOverviewKpis(makeReport()).longestCleanStreak).toEqual({ name: "Ivy", days: 30 });
  });

  it("returns null highlights when there are no technicians", () => {
    const empty = { ...makeReport(), perTech: [], teamSeries: [] };
    const k = deriveOverviewKpis(empty);
    expect(k.totalPoints).toBe(0);
    expect(k.highestBalance).toBeNull();
    expect(k.longestCleanStreak).toBeNull();
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run src/lib/accountability/__tests__/overview.test.ts`
Expected: FAIL — cannot find module `../overview` / `deriveOverviewKpis is not a function`.

- [ ] **Step 3: Write the implementation**

Create `src/lib/accountability/overview.ts`:

```ts
// src/lib/accountability/overview.ts
//
// Pure derivations for the Point System Overview KPI strip. Uses only fields
// already present on AccountabilityReport so it stays in lockstep with the
// exported PDF/Excel. "At a level" mirrors the ladder's first rung (balance 2).

import type { AccountabilityReport } from "./report";

const FIRST_LEVEL_THRESHOLD = 2;

export interface OverviewKpis {
  totalPoints: number;
  techsAtLevel: number;
  totalTechs: number;
  highestBalance: { name: string; balance: number } | null;
  longestCleanStreak: { name: string; days: number } | null;
}

export function deriveOverviewKpis(report: AccountabilityReport): OverviewKpis {
  const totalPoints = report.teamSeries.reduce((n, b) => n + b.points, 0);
  const totalTechs = report.perTech.length;
  const techsAtLevel = report.perTech.filter((t) => t.balanceAsOfEnd >= FIRST_LEVEL_THRESHOLD).length;

  let highestBalance: OverviewKpis["highestBalance"] = null;
  for (const t of report.perTech) {
    if (!highestBalance || t.balanceAsOfEnd > highestBalance.balance) {
      highestBalance = { name: t.name, balance: t.balanceAsOfEnd };
    }
  }

  let longestCleanStreak: OverviewKpis["longestCleanStreak"] = null;
  for (const t of report.perTech) {
    if (t.daysClean == null) continue;
    if (!longestCleanStreak || t.daysClean > longestCleanStreak.days) {
      longestCleanStreak = { name: t.name, days: t.daysClean };
    }
  }

  return { totalPoints, techsAtLevel, totalTechs, highestBalance, longestCleanStreak };
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run src/lib/accountability/__tests__/overview.test.ts`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/lib/accountability/overview.ts src/lib/accountability/__tests__/overview.test.ts
git commit -m "feat(point-system): overview KPI derivation with tests"
```

---

## Task 3: Live charts component (`AccountabilityCharts`)

On-screen versions of the four report visuals, driven by the same `useAccountabilityReportData(startIso, endIso)` React Query key the PDF export uses (so both share one fetch). Controlled by range props owned by the parent.

**Files:**
- Create: `src/components/accountability/AccountabilityCharts.tsx`

- [ ] **Step 1: Implement the component**

Create `src/components/accountability/AccountabilityCharts.tsx`:

```tsx
// src/components/accountability/AccountabilityCharts.tsx
//
// Live on-screen Point System visuals: KPI strip, team trend, points-by-tech,
// and severity split. Reads the same report as the PDF export (shared query
// key) so numbers always agree with the exported document.

import { useMemo } from "react";
import {
  ResponsiveContainer,
  LineChart, Line,
  BarChart, Bar,
  PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip,
} from "recharts";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useAccountabilityReportData } from "@/hooks/useAccountabilityReportData";
import { deriveOverviewKpis } from "@/lib/accountability/overview";

const NAVY = "#0E2148";
const SEVERITY_COLORS = { Minor: "#EF9F27", Serious: "#D85A30", Major: "#A32D2D" } as const;

interface Props {
  startIso: string;
  endIso: string;
}

function KpiCard({ label, value, sub }: { label: string; value: string; sub?: string }) {
  return (
    <div className="rounded-lg bg-muted/40 p-4">
      <div className="text-sm text-muted-foreground">{label}</div>
      <div className="mt-1 text-2xl font-medium">
        {value}
        {sub ? <span className="ml-1 text-sm font-normal text-muted-foreground">{sub}</span> : null}
      </div>
    </div>
  );
}

export function AccountabilityCharts({ startIso, endIso }: Props) {
  const { data: report, isLoading } = useAccountabilityReportData(startIso, endIso);

  const kpis = useMemo(() => (report ? deriveOverviewKpis(report) : null), [report]);

  const totalsRows = useMemo(() => {
    if (!report) return [];
    return report.perTech
      .slice()
      .sort((a, b) => b.balanceAsOfEnd - a.balanceAsOfEnd)
      .map((t) => ({ name: t.name, balance: t.balanceAsOfEnd }));
  }, [report]);

  const severityRows = useMemo(() => {
    if (!report) return [];
    const totals = report.perTech.reduce(
      (acc, t) => {
        acc.minor += t.severity.minor;
        acc.serious += t.severity.serious;
        acc.major += t.severity.major;
        return acc;
      },
      { minor: 0, serious: 0, major: 0 },
    );
    return [
      { name: "Minor", value: totals.minor },
      { name: "Serious", value: totals.serious },
      { name: "Major", value: totals.major },
    ].filter((r) => r.value > 0);
  }, [report]);

  if (isLoading || !report || !kpis) {
    return <div className="py-12 text-center text-sm text-muted-foreground">Loading…</div>;
  }

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <KpiCard label="Total points" value={String(kpis.totalPoints)} />
        <KpiCard label="Techs at a level" value={String(kpis.techsAtLevel)} sub={`of ${kpis.totalTechs}`} />
        <KpiCard
          label="Highest balance"
          value={kpis.highestBalance ? kpis.highestBalance.name : "—"}
          sub={kpis.highestBalance ? String(kpis.highestBalance.balance) : undefined}
        />
        <KpiCard
          label="Longest clean streak"
          value={kpis.longestCleanStreak ? kpis.longestCleanStreak.name : "—"}
          sub={kpis.longestCleanStreak ? `${kpis.longestCleanStreak.days}d` : undefined}
        />
      </div>

      <Card>
        <CardHeader><CardTitle className="text-base">Team points over time</CardTitle></CardHeader>
        <CardContent>
          <ResponsiveContainer width="100%" height={220}>
            <LineChart data={report.teamSeries} margin={{ top: 8, right: 16, bottom: 8, left: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" />
              <XAxis dataKey="bucket" tick={{ fontSize: 11, fill: "#64748B" }} />
              <YAxis tick={{ fontSize: 11, fill: "#64748B" }} allowDecimals={false} />
              <Tooltip />
              <Line type="monotone" dataKey="points" stroke={NAVY} strokeWidth={2} dot={{ r: 3 }} />
            </LineChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">Points by tech</CardTitle></CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={totalsRows} layout="vertical" margin={{ top: 8, right: 16, bottom: 8, left: 8 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" horizontal={false} />
                <XAxis type="number" tick={{ fontSize: 11, fill: "#64748B" }} allowDecimals={false} />
                <YAxis type="category" dataKey="name" width={90} tick={{ fontSize: 11, fill: "#64748B" }} />
                <Tooltip />
                <Bar dataKey="balance" fill={NAVY} radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">By severity</CardTitle></CardHeader>
          <CardContent>
            {severityRows.length === 0 ? (
              <div className="py-16 text-center text-sm text-muted-foreground">No points in range</div>
            ) : (
              <ResponsiveContainer width="100%" height={220}>
                <PieChart>
                  <Pie data={severityRows} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={45} outerRadius={80} label>
                    {severityRows.map((s) => (
                      <Cell key={s.name} fill={SEVERITY_COLORS[s.name as keyof typeof SEVERITY_COLORS]} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Type-check**

Run: `npx tsc --noEmit`
Expected: no errors from `AccountabilityCharts.tsx`.

- [ ] **Step 3: Commit**

```bash
git add src/components/accountability/AccountabilityCharts.tsx
git commit -m "feat(point-system): live on-screen accountability charts"
```

---

## Task 4: Make `AccountabilityExport` accept a controlled range

So the Overview page shows ONE date picker and both the live charts and the export buttons follow it. Backwards compatible: when no range props are passed, the component keeps its own picker (existing behaviour).

**Files:**
- Modify: `src/components/accountability/AccountabilityExport.tsx`

- [ ] **Step 1: Add optional controlled props**

In `AccountabilityExport.tsx`, replace the component signature and the range state setup. Change:

```tsx
export function AccountabilityExport() {
  const today = useToday();
  const [range, setRange] = useState<DateRange>({ from: startOfMonth(today), to: today });
```

to:

```tsx
interface AccountabilityExportProps {
  startIso?: string;
  endIso?: string;
}

export function AccountabilityExport({ startIso: startIsoProp, endIso: endIsoProp }: AccountabilityExportProps = {}) {
  const today = useToday();
  const controlled = startIsoProp != null && endIsoProp != null;
  const [range, setRange] = useState<DateRange>({ from: startOfMonth(today), to: today });
```

- [ ] **Step 2: Derive the effective range from props when controlled**

Replace:

```tsx
  const startIso = range.from ? format(range.from, "yyyy-MM-dd") : format(today, "yyyy-MM-dd");
  const endIso = range.to ? format(range.to, "yyyy-MM-dd") : startIso;
```

with:

```tsx
  const localStart = range.from ? format(range.from, "yyyy-MM-dd") : format(today, "yyyy-MM-dd");
  const localEnd = range.to ? format(range.to, "yyyy-MM-dd") : localStart;
  const startIso = controlled ? startIsoProp! : localStart;
  const endIso = controlled ? endIsoProp! : localEnd;
```

- [ ] **Step 3: Hide the internal picker/presets when controlled**

In the returned JSX, wrap the picker + preset buttons block (the `<DateRangePicker .../>` and the `<div className="flex flex-wrap gap-1">…</div>` preset group) so it only renders when not controlled. Change the opening of that row:

```tsx
      <div className="flex flex-wrap items-center gap-2">
        <DateRangePicker dateRange={range} setDateRange={(r) => r && setRange(r)} />
        <div className="flex flex-wrap gap-1">
```

to:

```tsx
      <div className="flex flex-wrap items-center gap-2">
        {!controlled && (
          <>
            <DateRangePicker dateRange={range} setDateRange={(r) => r && setRange(r)} />
            <div className="flex flex-wrap gap-1">
```

and close the fragment right after the closing `</div>` of that preset group (before `<div className="flex gap-2 ml-auto">`):

```tsx
            </div>
          </>
        )}
        <div className="flex gap-2 ml-auto">
```

- [ ] **Step 4: Type-check and test the existing export still builds**

Run: `npx tsc --noEmit`
Expected: no errors. The default call site (none exist yet outside the retired tab) and the new controlled call site both type-check.

- [ ] **Step 5: Commit**

```bash
git add src/components/accountability/AccountabilityExport.tsx
git commit -m "refactor(point-system): AccountabilityExport accepts controlled date range"
```

---

## Task 5: Violation-type history hook + "last edit" line

Surface the newest catalog change under the editor as the audit cue.

**Files:**
- Create: `src/hooks/useViolationTypeHistory.ts`
- Modify: `src/components/accountability/PointValuesEditor.tsx`

- [ ] **Step 1: Create the hook**

Create `src/hooks/useViolationTypeHistory.ts`:

```ts
// src/hooks/useViolationTypeHistory.ts
//
// Reads the newest violation_types catalog changes for the audit cue on the
// Rules tab. RLS restricts reads to admin + field_supervisor.

import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/contexts/AuthContext";

export interface ViolationTypeHistoryRow {
  code: string;
  field_changed: string;
  old_value: string | null;
  new_value: string | null;
  changed_at: string;
}

export function useViolationTypeHistory(limit = 5) {
  const { session } = useAuth();
  return useQuery<ViolationTypeHistoryRow[]>({
    queryKey: ["violation-type-history", limit],
    enabled: !!session,
    staleTime: 30_000,
    queryFn: async () => {
      const { data, error } = await supabase
        .from("violation_type_history" as never)
        .select("code, field_changed, old_value, new_value, changed_at")
        .order("changed_at", { ascending: false })
        .limit(limit);
      if (error) throw error;
      return (data ?? []) as unknown as ViolationTypeHistoryRow[];
    },
  });
}
```

- [ ] **Step 2: Show the newest change in the editor footer**

In `PointValuesEditor.tsx`, add the import near the other hooks:

```tsx
import { useViolationTypeHistory } from "@/hooks/useViolationTypeHistory";
```

Inside the component, after `const save = useSaveCatalog();`, add:

```tsx
  const { data: history = [] } = useViolationTypeHistory(1);
  const lastEdit = history[0];
```

Then, in the footer `<div>` (the one with `className="shrink-0 border-t px-4 py-3 …"`), add a left-aligned audit line before the Cancel/Save buttons. Change the footer opening to:

```tsx
        <div className="shrink-0 border-t px-4 py-3 sm:px-6 flex items-center justify-between gap-2">
          <p className="text-xs text-muted-foreground truncate">
            {lastEdit
              ? `Last edit: ${lastEdit.code} ${lastEdit.field_changed} ${lastEdit.old_value ?? "—"} → ${lastEdit.new_value ?? "—"}`
              : "No edits logged yet."}
          </p>
          <div className="flex gap-2 shrink-0">
```

and add the matching closing `</div>` after the Save `</Button>` (wrap the two buttons). The existing buttons stay unchanged inside that new inner `div`.

- [ ] **Step 3: Invalidate history after a save**

In `src/hooks/useEditableCatalog.ts`, in `useSaveCatalog`'s `onSuccess`, add an invalidation so the audit line refreshes:

```tsx
      queryClient.invalidateQueries({ queryKey: ["violation-catalog"] });
      queryClient.invalidateQueries({ queryKey: ["violation-catalog-all"] });
      queryClient.invalidateQueries({ queryKey: ["tech-accountability"] });
      queryClient.invalidateQueries({ queryKey: ["violation-type-history"] });
```

- [ ] **Step 4: Type-check**

Run: `npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add src/hooks/useViolationTypeHistory.ts src/components/accountability/PointValuesEditor.tsx src/hooks/useEditableCatalog.ts
git commit -m "feat(point-system): audit cue for point-value edits"
```

---

## Task 6: The Point System page (Overview + Rules tabs)

**Files:**
- Create: `src/pages/admin/PointSystem.tsx`

The Rules tab shows the "Edit point values" button for admin AND field_supervisor (the old tab gated it to admin only). Overview owns the single date range + presets and passes it to the charts and the (controlled) export.

- [ ] **Step 1: Implement the page**

Create `src/pages/admin/PointSystem.tsx`:

```tsx
// src/pages/admin/PointSystem.tsx
//
// Dedicated Point System page. Overview = live KPIs/charts + per-tech table
// (rows carry the "Review day" action). Rules = point-values editor, now
// writable by the FOM, with an audit trail. Gated to admin + field_supervisor.

import { useState } from "react";
import { format, startOfMonth, startOfWeek, subDays } from "date-fns";
import type { DateRange } from "react-day-picker";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { DateRangePicker } from "@/components/dashboard/DateRangePicker";
import { useToday } from "@/hooks/use-today";
import { useEffectiveAuth } from "@/contexts/EffectiveAuthContext";
import { AccountabilityCharts } from "@/components/accountability/AccountabilityCharts";
import { AccountabilityExport } from "@/components/accountability/AccountabilityExport";
import { AccountabilityTable } from "@/components/accountability/AccountabilityTable";
import { PointValuesEditor } from "@/components/accountability/PointValuesEditor";

export default function PointSystemPage() {
  const { isAdmin, isFieldSupervisor } = useEffectiveAuth();
  const today = useToday();
  const [range, setRange] = useState<DateRange>({ from: startOfMonth(today), to: today });
  const [editorOpen, setEditorOpen] = useState(false);

  if (!isAdmin && !isFieldSupervisor) return null;

  const startIso = range.from ? format(range.from, "yyyy-MM-dd") : format(today, "yyyy-MM-dd");
  const endIso = range.to ? format(range.to, "yyyy-MM-dd") : startIso;
  const setPreset = (from: Date, to: Date) => setRange({ from, to });

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <header>
        <h1 className="text-2xl font-bold">Point system</h1>
        <p className="text-sm text-muted-foreground">
          Cumulative accountability points per technician. Balance drives the Level ladder
          (L1 at 2 pts, L2 at 4, L3 at 6, L4 at 8); the ladder suggests action only.
        </p>
      </header>

      <Tabs defaultValue="overview" className="w-full">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="rules">Rules</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="mt-4 space-y-6">
          <div className="flex flex-wrap items-center gap-2">
            <DateRangePicker dateRange={range} setDateRange={(r) => r && setRange(r)} />
            <div className="flex flex-wrap gap-1">
              <Button variant="outline" size="sm" onClick={() => setPreset(startOfWeek(today, { weekStartsOn: 5 }), today)}>This week</Button>
              <Button variant="outline" size="sm" onClick={() => setPreset(startOfMonth(today), today)}>This month</Button>
              <Button variant="outline" size="sm" onClick={() => setPreset(subDays(today, 30), today)}>Last 30</Button>
            </div>
          </div>

          <AccountabilityCharts startIso={startIso} endIso={endIso} />
          <AccountabilityExport startIso={startIso} endIso={endIso} />
          <AccountabilityTable />
        </TabsContent>

        <TabsContent value="rules" className="mt-4 space-y-4">
          <div className="flex items-start justify-between gap-2">
            <p className="text-sm text-muted-foreground">
              Point values for each violation. Editing changes scoring going forward; posted
              points are unchanged. Every edit is logged.
            </p>
            <Button variant="secondary" size="sm" className="shrink-0" onClick={() => setEditorOpen(true)}>
              Edit point values
            </Button>
          </div>
          <PointValuesEditor open={editorOpen} onOpenChange={setEditorOpen} />
        </TabsContent>
      </Tabs>
    </div>
  );
}
```

- [ ] **Step 2: Type-check**

Run: `npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/pages/admin/PointSystem.tsx
git commit -m "feat(point-system): dedicated Point System page (Overview + Rules)"
```

---

## Task 7: Route + navigation, and retire the Notifications sub-tab

**Files:**
- Modify: `src/App.tsx`
- Modify: `src/components/AppShellWithNav.tsx`
- Modify: `src/pages/admin/Notifications.tsx`
- Delete: `src/components/accountability/TechAccountabilityTab.tsx`

- [ ] **Step 1: Add the lazy import in `App.tsx`**

Next to `const NotificationsPage = lazy(() => import("./pages/admin/Notifications"));` add:

```tsx
const PointSystemPage = lazy(() => import("./pages/admin/PointSystem"));
```

- [ ] **Step 2: Add the route in `App.tsx`**

After the `/admin/notifications` `<Route …/>` line, add (same `field_supervisor` gate the sub-tab used):

```tsx
            <Route path="/admin/point-system" element={<ProtectedRoute requiredRole="field_supervisor"><AppShellWithNav><Suspense fallback={<PageSpinner />}><PointSystemPage /></Suspense></AppShellWithNav></ProtectedRoute>} />
```

- [ ] **Step 3: Add the nav item in `AppShellWithNav.tsx`**

Add `Gauge` to the lucide-react import block (alongside `Trophy`, `Bell`, etc.):

```tsx
  Gauge,
```

Then add the nav entry immediately after the Leaderboard line (line ~62), so it sits under Leaderboard and is gated to admin + field_supervisor:

```tsx
    { to: `/admin/point-system${navSuffix}`, label: "Point System", icon: <Gauge className="h-4 w-4" />, show: isAdmin || isFieldSupervisor },
```

Confirm `isFieldSupervisor` is destructured from `useEffectiveAuth()` at the top of the nav component; it already provides `isAdmin` and `isFieldSupervisor` (used by the Notifications entry on line ~66). If not already destructured, add it.

- [ ] **Step 4: Remove the accountability sub-tab from `Notifications.tsx`**

Remove the import:

```tsx
import { TechAccountabilityTab } from "@/components/accountability/TechAccountabilityTab";
```

Remove the `<TabsTrigger value="accountability">Tech Accountability</TabsTrigger>` line and the entire `<TabsContent value="accountability" …>…</TabsContent>` block. The `<TabsList>` then holds only the Triage trigger; simplify by removing the `Tabs` wrapper is optional — leaving a single-tab `Tabs` is harmless, but cleaner is to drop `Tabs`/`TabsList`/`TabsTrigger`/`TabsContent` and render the Triage cards directly. Minimal-change version: keep the `Tabs` with just the `triage` trigger and content.

Apply the minimal-change version — change the `<TabsList>` block to:

```tsx
        <TabsList>
          <TabsTrigger value="triage">Triage</TabsTrigger>
        </TabsList>
```

and delete the accountability `TabsContent` block entirely.

- [ ] **Step 5: Delete the now-unused sub-tab component**

```bash
git rm src/components/accountability/TechAccountabilityTab.tsx
```

Confirm nothing else imports it:

```bash
grep -rn "TechAccountabilityTab" src/ ; echo "exit: $?"
```
Expected: no matches (grep exit 1).

- [ ] **Step 6: Type-check and build**

Run: `npx tsc --noEmit && npx vite build`
Expected: type-check clean; build succeeds.

- [ ] **Step 7: Commit**

```bash
git add src/App.tsx src/components/AppShellWithNav.tsx src/pages/admin/Notifications.tsx
git commit -m "feat(point-system): route + nav item; retire Notifications accountability sub-tab"
```

---

## Task 8: Full verification

- [ ] **Step 1: Run the full unit suite**

Run: `npx vitest run`
Expected: all tests pass, including the new `overview.test.ts` and the existing accountability suite (`report.test.ts`, `engine.test.ts`, etc.).

- [ ] **Step 2: Type-check + lint + build**

Run: `npx tsc --noEmit && npx vite build`
Expected: clean.

- [ ] **Step 3: Manual smoke (dev server) — Overview**

Start the dev server via the preview tooling, sign in as admin, open `/admin/point-system`.
Expected: KPI strip renders; team trend, points-by-tech, and severity charts render; changing the date range / presets updates all charts and the table; Export PDF/Excel buttons produce files.

- [ ] **Step 4: Manual smoke — Rules (FOM path)**

As a `field_supervisor` account (e.g. Charles), open `/admin/point-system` → Rules → "Edit point values", lower `inform_arrival` to a new value, Save.
Expected: save succeeds (no RLS error); reopening shows the new value; the footer shows the "Last edit" line reflecting the change.

- [ ] **Step 5: Manual smoke — Notifications**

Open `/admin/notifications`.
Expected: only the Triage content shows; no "Tech Accountability" tab.

- [ ] **Step 6: Manual smoke — daily review unchanged**

On `/admin/point-system` → Overview → the per-tech table, use a row's "Review day" action.
Expected: the review dialog opens and commits exactly as before (unchanged flow).

---

## Self-review notes (spec coverage)

- Spec §1 nav + retire sub-tab → Task 7.
- Spec §2 Overview layout (KPI strip, team trend, points-by-tech, severity, table, export) → Tasks 2, 3, 4, 6. Daily Review: kept as the per-row "Review day" action in the table (Task 6 Overview), not a separate tab, because that is the existing review surface (a dialog keyed to tech + date). This is a deliberate simplification from the three-tab mockup; flag to Daniel at handoff.
- Spec §3 FOM rule editing + audit → Tasks 1, 5, 6 (button unlocked for field_supervisor in the Rules tab).
- Spec §4 engine untouched → no engine/ledger/decay/digest files are modified.
- Spec §7 access control → route gate + page gate + RLS all use admin ∨ field_supervisor.
- Spec §8 testing → Task 2 unit test; Task 8 build + manual smokes.
- Spec §9 rollout → migration via MCP apply_migration + manual version record (Task 1).
