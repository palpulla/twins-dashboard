# Accountability Reports (PDF + Excel) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Export technician accountability data for any period as a branded PDF (charts incl. per-tech trend lines + summary/detail tables) and a raw-data Excel workbook.

**Architecture:** A pure, tested aggregation layer (`report.ts`) turns the period's committed ledger + actions + techs + catalog into one `AccountabilityReport`. A data hook fetches the rows. Two builders read the report: `excel-report.ts` (ExcelJS) and `pdf-report.ts` (jsPDF + jspdf-autotable, with charts captured from off-screen Recharts via html2canvas). A UI component wires a date-range picker + presets + the two download buttons.

**Tech Stack:** TypeScript, React, React Query, Vitest; jsPDF + jspdf-autotable, ExcelJS + file-saver, Recharts, html2canvas, date-fns.

**Base:** Branch off `origin/main` (Plans 1-3 + review surface + catalog editor all merged). Frontend deploys via Vercel on merge; no DB changes (read-only feature).

**Reuse/patterns:** mirror `src/lib/payroll/{pdfExport,excelExport}.ts` for jsPDF/ExcelJS/`saveAs` conventions. Engine helpers in `src/lib/accountability/engine.ts`: `computeBalance`, `computeLevel`, `daysSinceLastPoint`, `parseDateLocal`. Brand: navy `#0E2148`, yellow `#F7B801`. Logo `https://twinsdash.com/twins-logo.png`. Supabase client `@/integrations/supabase/client`; `useAuth()` `session`; `enabled: !!session`. No em-dashes in any copy.

---

## File Structure
- `src/lib/accountability/report.ts` — pure aggregation + bucketing → `AccountabilityReport`. (create)
- `src/lib/accountability/__tests__/report.test.ts` — tests. (create)
- `src/hooks/useAccountabilityReportData.ts` — fetch period rows, run `buildAccountabilityReport`. (create)
- `src/lib/accountability/excel-report.ts` — `downloadAccountabilityExcel(report)`. (create)
- `src/lib/accountability/pdf-report.ts` — `buildAccountabilityPdf(report, chartImages)`. (create)
- `src/components/accountability/AccountabilityExport.tsx` — picker + presets + off-screen charts + capture + download buttons. (create)
- `src/components/accountability/TechAccountabilityTab.tsx` — mount `<AccountabilityExport/>`. (modify)

---

## Task 1: Aggregation layer `report.ts` (pure, TDD)

**Files:** Create `src/lib/accountability/report.ts`, test `src/lib/accountability/__tests__/report.test.ts`

- [ ] **Step 1: Write the failing test**
```typescript
// src/lib/accountability/__tests__/report.test.ts
import { describe, it, expect } from "vitest";
import {
  bucketGranularity, enumerateBuckets, bucketKeyForDate, buildAccountabilityReport,
  type ReportInput,
} from "../report";
import type { LedgerEntry } from "../types";

function entry(p: Partial<LedgerEntry>): LedgerEntry {
  return { id: Math.random().toString(36).slice(2), technician_id: "t1", points: 1,
    reason_type: "violation", violation_code: "present_3_options", severity: "minor",
    source: "checklist", occurred_on: "2026-06-01", job_id: null, note: null, voided_at: null, ...p };
}

describe("bucketGranularity", () => {
  it("<=28 days -> day", () => expect(bucketGranularity("2026-06-01","2026-06-20")).toBe("day"));
  it("<=183 days -> week", () => expect(bucketGranularity("2026-01-01","2026-04-01")).toBe("week"));
  it(">183 days -> month", () => expect(bucketGranularity("2026-01-01","2026-12-31")).toBe("month"));
});

describe("bucketKeyForDate", () => {
  it("day = the date", () => expect(bucketKeyForDate("2026-06-10","day")).toBe("2026-06-10"));
  it("week = the Friday on/before (weekStartsOn 5)", () => {
    // 2026-06-10 is a Wednesday; its Fri-Thu week starts Fri 2026-06-05
    expect(bucketKeyForDate("2026-06-10","week")).toBe("2026-06-05");
  });
  it("month = YYYY-MM", () => expect(bucketKeyForDate("2026-06-10","month")).toBe("2026-06"));
});

describe("buildAccountabilityReport", () => {
  const techs = [{ id: "t1", name: "Maurice" }, { id: "t2", name: "Nick" }];
  const catalog = [
    { code: "present_3_options", label: "Presented 3 options", points: 1, severity: "minor" as const },
    { code: "failure_collect_payment", label: "Failure to collect payment", points: 3, severity: "major" as const },
  ];
  const input: ReportInput = {
    startIso: "2026-06-01", endIso: "2026-06-20",
    entries: [
      entry({ technician_id: "t1", occurred_on: "2026-06-02", points: 1, severity: "minor" }),
      entry({ technician_id: "t1", occurred_on: "2026-06-05", points: 3, severity: "major", violation_code: "failure_collect_payment" }),
      entry({ technician_id: "t2", occurred_on: "2026-06-03", points: 1, severity: "minor" }),
      entry({ technician_id: "t1", occurred_on: "2026-06-02", points: 1, voided_at: "2026-06-03T00:00:00Z" }), // voided, excluded
      entry({ technician_id: "t1", occurred_on: "2026-05-20", points: 2, severity: "serious" }), // before range: counts for balance, not period
    ],
    actions: [{ technician_id: "t1", action_type: "coaching_discussion", occurred_on: "2026-06-06", notes: "talk" }],
    technicians: techs, catalog,
  };

  it("computes per-tech period points (voided + out-of-range excluded from period)", () => {
    const r = buildAccountabilityReport(input);
    const m = r.perTech.find((p) => p.technician_id === "t1")!;
    expect(m.periodPoints).toBe(4);            // 1 + 3 (voided + May excluded)
    expect(m.severity).toEqual({ minor: 1, serious: 0, major: 1 });
    expect(m.balanceAsOfEnd).toBe(6);          // 1+3 + the May 2 (non-voided, <= end)
  });
  it("team series sums equal sum of per-tech series", () => {
    const r = buildAccountabilityReport(input);
    const teamTotal = r.teamSeries.reduce((n, b) => n + b.points, 0);
    const perTechTotal = r.perTech.reduce((n, p) => n + p.series.reduce((m, b) => m + b.points, 0), 0);
    expect(teamTotal).toBe(perTechTotal);
  });
  it("violations list excludes voided and out-of-range, includes label", () => {
    const r = buildAccountabilityReport(input);
    expect(r.violations).toHaveLength(3);
    expect(r.violations.some((v) => v.label === "Failure to collect payment" && v.points === 3)).toBe(true);
  });
  it("empty period -> empty series/perTech-zero, valid shape", () => {
    const r = buildAccountabilityReport({ ...input, entries: [], actions: [] });
    expect(r.violations).toEqual([]);
    expect(r.perTech.every((p) => p.periodPoints === 0)).toBe(true);
  });
});
```

- [ ] **Step 2: Run → fails** (`npm test -- src/lib/accountability/__tests__/report.test.ts`).

- [ ] **Step 3: Implement `report.ts`**
```typescript
// src/lib/accountability/report.ts
import { startOfWeek, eachDayOfInterval, eachWeekOfInterval, eachMonthOfInterval, format, differenceInCalendarDays } from "date-fns";
import { computeBalance, computeLevel, daysSinceLastPoint, parseDateLocal } from "./engine";
import type { LedgerEntry, Severity, Level } from "./types";

export type Granularity = "day" | "week" | "month";

export interface ReportCatalogRow { code: string; label: string; points: number; severity: Severity; }
export interface ReportActionRow { technician_id: string; action_type: string; occurred_on: string; notes: string | null; }

export interface ReportInput {
  startIso: string; endIso: string;
  entries: LedgerEntry[];                 // all NON-voided rows with occurred_on <= endIso (for balance)
  actions: ReportActionRow[];
  technicians: { id: string; name: string }[];
  catalog: ReportCatalogRow[];
}

export interface AccountabilityReport {
  period: { startIso: string; endIso: string; granularity: Granularity };
  buckets: string[];
  teamSeries: { bucket: string; points: number }[];
  perTech: {
    technician_id: string; name: string;
    periodPoints: number; balanceAsOfEnd: number; level: Level;
    severity: { minor: number; serious: number; major: number };
    daysClean: number | null;
    series: { bucket: string; points: number }[];
  }[];
  violations: { occurred_on: string; technician_name: string; code: string | null; label: string; points: number; severity: Severity | null; source: string; job_id: string | null; note: string | null }[];
  actions: { occurred_on: string; technician_name: string; action_type: string; notes: string | null }[];
}

export function bucketGranularity(startIso: string, endIso: string): Granularity {
  const days = differenceInCalendarDays(parseDateLocal(endIso), parseDateLocal(startIso));
  if (days <= 28) return "day";
  if (days <= 183) return "week";
  return "month";
}

export function bucketKeyForDate(dateIso: string, g: Granularity): string {
  const d = parseDateLocal(dateIso);
  if (g === "day") return format(d, "yyyy-MM-dd");
  if (g === "month") return format(d, "yyyy-MM");
  return format(startOfWeek(d, { weekStartsOn: 5 }), "yyyy-MM-dd"); // Fri-Thu
}

export function enumerateBuckets(startIso: string, endIso: string, g: Granularity): string[] {
  const start = parseDateLocal(startIso); const end = parseDateLocal(endIso);
  if (g === "day") return eachDayOfInterval({ start, end }).map((d) => format(d, "yyyy-MM-dd"));
  if (g === "month") return eachMonthOfInterval({ start, end }).map((d) => format(d, "yyyy-MM"));
  return eachWeekOfInterval({ start, end }, { weekStartsOn: 5 }).map((d) => format(d, "yyyy-MM-dd"));
}

function inRange(occurredOn: string, startIso: string, endIso: string): boolean {
  return occurredOn >= startIso && occurredOn <= endIso; // ISO date strings compare lexically
}

export function buildAccountabilityReport(input: ReportInput): AccountabilityReport {
  const { startIso, endIso, entries, actions, technicians, catalog } = input;
  const g = bucketGranularity(startIso, endIso);
  const buckets = enumerateBuckets(startIso, endIso, g);
  const labelByCode = new Map(catalog.map((c) => [c.code, c.label]));
  const nameById = new Map(technicians.map((t) => [t.id, t.name]));

  const active = entries.filter((e) => !e.voided_at);
  const periodViolations = active.filter((e) => e.reason_type === "violation" && e.points > 0 && inRange(e.occurred_on, startIso, endIso));

  // team series
  const teamMap = new Map<string, number>(buckets.map((b) => [b, 0]));
  for (const e of periodViolations) {
    const k = bucketKeyForDate(e.occurred_on, g);
    if (teamMap.has(k)) teamMap.set(k, teamMap.get(k)! + e.points);
  }
  const teamSeries = buckets.map((b) => ({ bucket: b, points: teamMap.get(b) ?? 0 }));

  const perTech = technicians.map((t) => {
    const techEntries = active.filter((e) => e.technician_id === t.id);
    const techPeriod = periodViolations.filter((e) => e.technician_id === t.id);
    const sevMap = new Map<string, number>(buckets.map((b) => [b, 0]));
    const severity = { minor: 0, serious: 0, major: 0 };
    for (const e of techPeriod) {
      const k = bucketKeyForDate(e.occurred_on, g);
      if (sevMap.has(k)) sevMap.set(k, sevMap.get(k)! + e.points);
      if (e.severity === "minor") severity.minor++;
      else if (e.severity === "serious") severity.serious++;
      else if (e.severity === "major") severity.major++;
    }
    const balanceAsOfEnd = computeBalance(techEntries); // entries already <= end and non-voided
    return {
      technician_id: t.id, name: t.name,
      periodPoints: techPeriod.reduce((n, e) => n + e.points, 0),
      balanceAsOfEnd, level: computeLevel(balanceAsOfEnd),
      severity, daysClean: daysSinceLastPoint(techEntries, parseDateLocal(endIso)),
      series: buckets.map((b) => ({ bucket: b, points: sevMap.get(b) ?? 0 })),
    };
  });

  const violations = periodViolations
    .slice().sort((a, b) => a.occurred_on.localeCompare(b.occurred_on))
    .map((e) => ({ occurred_on: e.occurred_on, technician_name: nameById.get(e.technician_id) ?? "Unknown",
      code: e.violation_code, label: e.violation_code ? (labelByCode.get(e.violation_code) ?? e.violation_code) : "(adjustment)",
      points: e.points, severity: e.severity, source: e.source, job_id: e.job_id, note: e.note }));

  const actionsOut = actions.filter((a) => inRange(a.occurred_on, startIso, endIso))
    .map((a) => ({ occurred_on: a.occurred_on, technician_name: nameById.get(a.technician_id) ?? "Unknown", action_type: a.action_type, notes: a.notes }));

  return { period: { startIso, endIso, granularity: g }, buckets, teamSeries, perTech, violations, actions: actionsOut };
}
```
> Note: `caller passes entries already filtered to non-voided AND occurred_on <= endIso` (the hook does this). The function also defensively filters `!voided_at`.

- [ ] **Step 4: Run → all pass.** `npm test -- src/lib/accountability/__tests__/report.test.ts`, then `npm test -- src/lib/accountability` (no regressions), then `npx tsc --noEmit`.
- [ ] **Step 5: Commit** — `git add src/lib/accountability/report.ts src/lib/accountability/__tests__/report.test.ts && git commit -m "feat(accountability): report aggregation + bucketing"`.

---

## Task 2: Data hook `useAccountabilityReportData`

**Files:** Create `src/hooks/useAccountabilityReportData.ts`

- [ ] **Step 1: Implement**
```typescript
// src/hooks/useAccountabilityReportData.ts
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/contexts/AuthContext";
import { buildAccountabilityReport, type AccountabilityReport } from "@/lib/accountability/report";
import type { LedgerEntry } from "@/lib/accountability/types";

export function useAccountabilityReportData(startIso: string, endIso: string) {
  const { session } = useAuth();
  return useQuery<AccountabilityReport>({
    queryKey: ["accountability-report", startIso, endIso],
    enabled: !!session,
    staleTime: 60_000,
    queryFn: async () => {
      const { data: techs } = await supabase.from("technicians").select("id, name").eq("is_active", true).order("name");
      const { data: cat } = await supabase.from("violation_types" as never)
        .select("code, label, points, severity").eq("active", true) as { data: { code: string; label: string; points: number; severity: "minor"|"serious"|"major" }[] | null };
      // non-voided ledger rows with occurred_on <= endIso (covers balance + period)
      const { data: pts } = await supabase.from("accountability_points" as never)
        .select("id, technician_id, points, reason_type, violation_code, severity, source, occurred_on, job_id, note, voided_at")
        .is("voided_at", null).lte("occurred_on", endIso) as { data: LedgerEntry[] | null };
      const { data: acts } = await supabase.from("accountability_actions" as never)
        .select("technician_id, action_type, occurred_on, notes") as { data: { technician_id: string; action_type: string; occurred_on: string; notes: string | null }[] | null };
      return buildAccountabilityReport({
        startIso, endIso,
        entries: (pts ?? []) as LedgerEntry[],
        actions: acts ?? [],
        technicians: (techs ?? []) as { id: string; name: string }[],
        catalog: cat ?? [],
      });
    },
  });
}
```
- [ ] **Step 2:** `npx tsc --noEmit` clean.
- [ ] **Step 3:** Commit — `git add src/hooks/useAccountabilityReportData.ts && git commit -m "feat(accountability): report data hook"`.

---

## Task 3: Excel builder `excel-report.ts`

**Files:** Create `src/lib/accountability/excel-report.ts`. Read `src/lib/payroll/excelExport.ts` first for the ExcelJS + `saveAs` + branded-header conventions.

- [ ] **Step 1: Implement** `export async function downloadAccountabilityExcel(report: AccountabilityReport): Promise<void>`:
  - `const wb = new ExcelJS.Workbook();`
  - **Summary** sheet: header row (Tech, Period points, Minor, Serious, Major, Balance, Level, Days clean) with navy fill `FF0E2148` + white bold font; one row per `report.perTech` (Level as `L${level}`, daysClean as `-` when null).
  - **Violations** sheet: header (Date, Tech, Code, Label, Points, Severity, Source, Ticket #, Note); one row per `report.violations`.
  - **Actions** sheet: (Date, Tech, Action, Notes) from `report.actions`.
  - **Daily trend** sheet: first column "Bucket" = `report.buckets`; then one column per tech (name) with that tech's `series[i].points` aligned by bucket index.
  - Auto-size-ish: set sensible `column.width`.
  - `const buf = await wb.xlsx.writeBuffer(); saveAs(new Blob([buf]), \`twins-accountability_${report.period.startIso}_${report.period.endIso}.xlsx\`);` (`import { saveAs } from "file-saver";`).
- [ ] **Step 2:** `npx tsc --noEmit` clean; `npm run build` succeeds.
- [ ] **Step 3:** Commit — `git add src/lib/accountability/excel-report.ts && git commit -m "feat(accountability): excel report builder"`.

---

## Task 4: PDF builder `pdf-report.ts`

**Files:** Create `src/lib/accountability/pdf-report.ts`. Read `src/lib/payroll/pdfExport.ts` first for jsPDF + autoTable + branded header conventions.

- [ ] **Step 1: Implement**
```typescript
import { jsPDF } from "jspdf";
import autoTable from "jspdf-autotable";
import type { AccountabilityReport } from "./report";

export interface ChartImages { teamTrend: string; perTechTrend: string; perTechTotals: string; severity: string; } // PNG data URLs

const NAVY = "#0E2148"; const YELLOW = "#F7B801";

export function buildAccountabilityPdf(report: AccountabilityReport, charts: ChartImages): jsPDF {
  const doc = new jsPDF({ unit: "pt", format: "letter" });
  const W = doc.internal.pageSize.getWidth();
  // Header band
  doc.setFillColor(NAVY); doc.rect(0, 0, W, 64, "F");
  doc.setTextColor("#FFFFFF"); doc.setFontSize(16); doc.setFont("helvetica", "bold");
  doc.text("Technician Accountability Report", 40, 32);
  doc.setFontSize(10); doc.setFont("helvetica", "normal");
  doc.text(`${report.period.startIso} to ${report.period.endIso}`, 40, 48);
  doc.setFillColor(YELLOW); doc.rect(40, 56, 60, 3, "F");
  let y = 86;
  // Charts (each ~ half/full width). addImage(dataURL,"PNG",x,y,w,h)
  const cw = W - 80;
  for (const [title, img, h] of [
    ["Team points over time", charts.teamTrend, 150],
    ["Per-technician trend", charts.perTechTrend, 160],
    ["Period totals by technician", charts.perTechTotals, 150],
    ["Severity breakdown", charts.severity, 140],
  ] as [string, string, number][]) {
    if (y + h + 24 > doc.internal.pageSize.getHeight()) { doc.addPage(); y = 40; }
    doc.setTextColor(NAVY); doc.setFontSize(12); doc.setFont("helvetica", "bold"); doc.text(title, 40, y); y += 10;
    if (img) { doc.addImage(img, "PNG", 40, y, cw, h); }
    y += h + 20;
  }
  // Standings table
  autoTable(doc, { startY: y > doc.internal.pageSize.getHeight() - 120 ? undefined : y,
    head: [["Tech", "Period pts", "Balance", "Level", "Days clean"]],
    body: report.perTech.slice().sort((a,b)=>b.balanceAsOfEnd-a.balanceAsOfEnd)
      .map((p)=>[p.name, String(p.periodPoints), String(p.balanceAsOfEnd), `L${p.level}`, p.daysClean==null?"-":String(p.daysClean)]),
    headStyles: { fillColor: NAVY }, styles: { fontSize: 9 } });
  // Violations detail
  autoTable(doc, { head: [["Date","Tech","Item","Pts","Severity","Ticket"]],
    body: report.violations.map((v)=>[v.occurred_on, v.technician_name, v.label, String(v.points), v.severity ?? "", v.job_id ?? ""]),
    headStyles: { fillColor: NAVY }, styles: { fontSize: 8 } });
  // Footer note
  doc.setFontSize(8); doc.setTextColor("#9CA3AF");
  doc.text("Twins Garage Doors internal report. Generated automatically.", 40, doc.internal.pageSize.getHeight() - 24);
  return doc;
}
```
> Empty period: if `report.violations.length === 0`, after the header print "No accountability activity in this period." and still render the (zeroed) charts + empty tables.

- [ ] **Step 2:** `npx tsc --noEmit` clean; `npm run build` succeeds.
- [ ] **Step 3:** Commit — `git add src/lib/accountability/pdf-report.ts && git commit -m "feat(accountability): pdf report builder"`.

---

## Task 5: UI `AccountabilityExport.tsx` (picker + charts + capture + downloads)

**Files:** Create `src/components/accountability/AccountabilityExport.tsx`. Read `src/components/dashboard/DateRangePicker.tsx` for the picker props, and an existing Recharts usage (e.g. a component under `src/components/dashboard/`) for chart conventions.

- [ ] **Step 1: Implement**
  - State: `range: DateRange` (default: this month via `startOfMonth(useToday())`..`useToday()`); derive `startIso`/`endIso` via `format(d,"yyyy-MM-dd")`.
  - `const { data: report } = useAccountabilityReportData(startIso, endIso);`
  - **Presets:** small buttons (This month, Last 30, Last 90) that set `range`.
  - **Off-screen charts:** a `<div ref={chartsRef} style={{ position:"absolute", left:-9999, top:0, width:680 }}>` containing four Recharts charts sized ~640x150, driven by `report`:
    1. `LineChart` of `report.teamSeries` (x=bucket, y=points).
    2. `LineChart` with one `<Line>` per `report.perTech` (data = buckets joined; build a row per bucket with each tech's points as keys).
    3. `BarChart` of per-tech `periodPoints` (sorted desc).
    4. Stacked `BarChart` (or `PieChart`) of severity totals across techs (sum minor/serious/major).
    Each chart wrapped in a fixed-size div with an id so html2canvas can target it.
  - **Download PDF:** `import html2canvas from "html2canvas";` On click: for each of the four chart nodes, `const canvas = await html2canvas(node, { scale: 2, backgroundColor: "#ffffff" }); const url = canvas.toDataURL("image/png");` build `ChartImages`, then `const doc = buildAccountabilityPdf(report, images); doc.save(\`twins-accountability_${startIso}_${endIso}.pdf\`);`. Guard on `!report`. Show a spinner while capturing (`isExporting` state).
  - **Download Excel:** `await downloadAccountabilityExcel(report)` (guard `!report`).
  - Buttons disabled while `!report || isExporting`.
- [ ] **Step 2:** `npx tsc --noEmit` clean; `npm run build` succeeds.
- [ ] **Step 3:** Commit — `git add src/components/accountability/AccountabilityExport.tsx && git commit -m "feat(accountability): export UI (PDF + Excel with charts)"`.

---

## Task 6: Mount + verify

**Files:** Modify `src/components/accountability/TechAccountabilityTab.tsx`

- [ ] **Step 1:** Import and render `<AccountabilityExport/>` near the top of the tab (below the heading, above the table). Visible to admin + field_supervisor (the tab is already gated to those roles, so no extra gating needed).
- [ ] **Step 2:** `npx tsc --noEmit` clean; `npm test -- src/lib/accountability` green; `npm run build` succeeds.
- [ ] **Step 3:** Controller verification: preview is auth-gated, so verify via build/types/tests; the controller (or a manual check post-merge) downloads a PDF + Excel for a known range and confirms charts + tables render and the files open.
- [ ] **Step 4:** Commit — `git add src/components/accountability/TechAccountabilityTab.tsx && git commit -m "feat(accountability): mount export on the tab"`.

---

## Self-Review (planning)
- **Spec coverage:** aggregation + bucketing (T1), data fetch (T2), Excel raw-data sheets (T3), branded PDF with the four charts incl. per-tech trend + standings + detail (T4), date-range picker + presets + capture + downloads (T5), mounted + gated (T6). Read-only (no DB writes). Empty-period handled (T1 shape, T4 note). Committed-only (hook filters `voided_at IS NULL`; period excludes out-of-range).
- **Placeholders:** none — `report.ts` is full code+tests; builders/UI give complete key code + the exact files to mirror. The per-tech-trend "row per bucket with tech keys" shaping is described explicitly in T5.
- **Type consistency:** `AccountabilityReport`, `ReportInput`, `Granularity`, `ChartImages`, and the engine helpers (`computeBalance`/`computeLevel`/`daysSinceLastPoint`/`parseDateLocal`) are consistent across tasks.

## Open items
- Per-tech trend readability with a large roster: if techs exceed ~8, switch chart 2 to top-N by period points (rest grouped) or small-multiples — current small roster is fine; revisit then.
- html2canvas scale (start 2x); if PDFs are heavy, drop to 1.5x.
