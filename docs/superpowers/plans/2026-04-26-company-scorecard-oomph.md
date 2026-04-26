# Company Scorecard "Oomph" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Visually refresh `twins-dash/src/pages/Index.tsx` (the Company Scorecard at twinsdash.com) with the approved Scoreboard direction: navy gradient hero panel + delta pill, KPI tiles with gauge bars + goal captions, tier badges on the technician breakdown, and a warm cream page background. The Twins logo asset and all KPI math are preserved.

**Architecture:** Pure UI layer changes. One new component (`HeroScoreboard`), additive props on `MetricCard`, a new column in `TechnicianBreakdown`, a single CSS token swap. `getPreviousPeriod` is lifted from `use-technician-data.ts` to a shared module so `Index.tsx` can compute the delta via a second `useDashboardData(priorRange)` call. Zero data-layer changes.

**Tech Stack:** React 18 + TypeScript + Vite 5 + shadcn/ui + Tailwind + TanStack Query + Recharts. Test runner: Vitest.

**Spec:** [`docs/superpowers/specs/2026-04-26-company-scorecard-oomph-design.md`](../specs/2026-04-26-company-scorecard-oomph-design.md)

**Branch:** `feat/scorecard-oomph` off `main`. Never commit to `main` directly.

**Visual reference:** `twins-dash/.superpowers/brainstorm/74778-1777233066/content/scoreboard-full.html` (third phone — "Subtle warm cream"). Open in the visual companion server before implementing if anything is unclear.

---

## File Structure

### New files (in `twins-dash/`)

- `src/components/dashboard/HeroScoreboard.tsx` — navy gradient hero panel with delta pill + sparkline
- `src/lib/dashboard/period-helpers.ts` — `getPreviousPeriod(dateRange)` lifted from `use-technician-data.ts` for shared use

### Modified files (in `twins-dash/`)

- `src/pages/Index.tsx` — swap inline hero block for `<HeroScoreboard>`, thread `goalPct`/`goalCaption` into existing `MetricCard` invocations, compute delta via second `useDashboardData(priorRange)` call
- `src/components/dashboard/MetricCard.tsx` — add optional `goalPct`/`goalCaption`/`goalTone` props with gauge bar render block
- `src/components/dashboard/TechnicianBreakdown.tsx` — add a Tier column using `TierBadge` + `useTierThresholds` + `computeTier`
- `src/hooks/use-technician-data.ts` — replace inline `getPreviousPeriod` with import from new shared module
- `src/index.css` — change `--background` token from `220 20% 98%` to `48 75% 97%` (warm cream `#fefcf3`)

### Untouched

- `src/lib/kpi-calculations.ts` (sacred per CLAUDE.md)
- All hooks (`useDashboardData`, `useCompanyGoals`, `useTierThresholds`)
- The Twins logo assets (`public/twins-logo.webp`, `public/twins-logo.png`)
- Other dashboard components (`SemiGauge`, `ComparisonSection`, `RevenueTrendChart`, `DateRangePicker`, `ExportButtons`, `GaugeChart`, `ConversionFunnel`)

---

## Task 0: Worktree + branch

**Files:** none

- [ ] **Step 1: From the inner repo, verify clean main**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git status
git checkout main && git pull origin main --ff-only
```

Expected: clean working tree, main at latest origin.

- [ ] **Step 2: Create worktree on new branch**

```bash
git worktree add -b feat/scorecard-oomph .worktrees/scorecard-oomph main
cd .worktrees/scorecard-oomph
npm install --silent
```

Expected: worktree created, deps installed (fuse.js included from PR #25 era).

- [ ] **Step 3: Sanity verify**

```bash
npx tsc --noEmit
npm run build 2>&1 | tail -3
```

Both should be clean before any code changes.

---

## Task 1: Lift `getPreviousPeriod` to a shared module

**Files:**
- Create: `src/lib/dashboard/period-helpers.ts`
- Modify: `src/hooks/use-technician-data.ts` (lines 290-332 of original file)

The existing `getPreviousPeriod` lives inside `use-technician-data.ts`. We lift it verbatim so `Index.tsx` can reuse it without depending on the technician hook.

- [ ] **Step 1: Create `src/lib/dashboard/period-helpers.ts`**

```ts
// src/lib/dashboard/period-helpers.ts
import type { DateRange } from "react-day-picker";

function isSameLocalDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

/**
 * Returns the prior period for the given dateRange.
 * - "This year" YTD compares to last year YTD (calendar equivalent)
 * - All other ranges shift backwards by their own duration
 * Returns null when dateRange is missing endpoints.
 *
 * Lifted from use-technician-data.ts so Index.tsx can compute hero deltas
 * without coupling the company scorecard to the per-tech hook.
 */
export function getPreviousPeriod(
  dateRange?: DateRange,
): { from?: string; to?: string; label: string } | null {
  if (!dateRange?.from || !dateRange?.to) return null;
  const from = dateRange.from;
  const to = dateRange.to;
  const now = new Date();

  // YTD → last year YTD
  const thisYearStart = new Date(now.getFullYear(), 0, 1);
  if (isSameLocalDay(from, thisYearStart) && isSameLocalDay(to, now)) {
    const lastYear = now.getFullYear() - 1;
    const prevFrom = new Date(lastYear, 0, 1, 0, 0, 0, 0);
    const prevTo = new Date(lastYear, now.getMonth(), now.getDate(), 23, 59, 59, 999);
    return {
      from: prevFrom.toISOString(),
      to: prevTo.toISOString(),
      label: `${lastYear} YTD`,
    };
  }

  // Default: shift back by duration
  const durationMs = to.getTime() - from.getTime();
  const prevTo = new Date(from.getTime() - 1);
  prevTo.setHours(23, 59, 59, 999);
  const prevFrom = new Date(prevTo.getTime() - durationMs);
  prevFrom.setHours(0, 0, 0, 0);

  return {
    from: prevFrom.toISOString(),
    to: prevTo.toISOString(),
    label: `${prevFrom.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${prevTo.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`,
  };
}

/**
 * Convenience: convert the ISO from/to strings back into a DateRange so callers
 * can pass straight into useDashboardData(priorRange).
 */
export function priorPeriodAsDateRange(
  dateRange?: DateRange,
): DateRange | undefined {
  const p = getPreviousPeriod(dateRange);
  if (!p?.from || !p?.to) return undefined;
  return { from: new Date(p.from), to: new Date(p.to) };
}
```

- [ ] **Step 2: Update `use-technician-data.ts` to import the helper**

Find the inline `function getPreviousPeriod(...)` block (search for `function getPreviousPeriod` — single match). Delete the function body AND the helper `isSameLocalDay`. At the top of the file, add:

```ts
import { getPreviousPeriod } from "@/lib/dashboard/period-helpers";
```

The single existing call site (`const previousPeriod = getPreviousPeriod(dateRange);`) keeps the same signature, so no other code changes.

- [ ] **Step 3: Write a unit test for the lift**

Create `src/lib/dashboard/__tests__/period-helpers.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { getPreviousPeriod } from "../period-helpers";

describe("getPreviousPeriod", () => {
  it("returns null for missing endpoints", () => {
    expect(getPreviousPeriod(undefined)).toBeNull();
    expect(getPreviousPeriod({ from: undefined, to: undefined })).toBeNull();
  });

  it("compares YTD to last year YTD", () => {
    const now = new Date();
    const yearStart = new Date(now.getFullYear(), 0, 1);
    const result = getPreviousPeriod({ from: yearStart, to: now });
    expect(result).not.toBeNull();
    expect(result!.label).toBe(`${now.getFullYear() - 1} YTD`);
  });

  it("shifts an arbitrary 7-day range back by 7 days", () => {
    const from = new Date(2026, 3, 14); // Apr 14
    const to = new Date(2026, 3, 20);   // Apr 20
    const result = getPreviousPeriod({ from, to });
    expect(result).not.toBeNull();
    // Prior 7 days: Apr 7 (00:00) – Apr 13 (23:59)
    expect(new Date(result!.from!).toISOString().slice(0, 10)).toBe("2026-04-07");
    expect(new Date(result!.to!).toISOString().slice(0, 10)).toBe("2026-04-13");
  });
});
```

- [ ] **Step 4: Run the test**

```bash
npx vitest run src/lib/dashboard/__tests__/period-helpers.test.ts
```

Expected: 3 passing.

- [ ] **Step 5: Verify TS still compiles**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add src/lib/dashboard/period-helpers.ts src/lib/dashboard/__tests__/period-helpers.test.ts src/hooks/use-technician-data.ts
git commit -m "refactor(dashboard): lift getPreviousPeriod to shared period-helpers module"
```

---

## Task 2: Warm cream page background

**Files:**
- Modify: `src/index.css`

The page background token controls Tailwind's `bg-background` utility. Single line change.

- [ ] **Step 1: Find the current --background token**

```bash
grep -n "^\s*--background:" src/index.css
```

Expected: one or two lines (light + dark mode).

- [ ] **Step 2: Update the light-mode token only**

In `src/index.css`, change:

```css
--background: 220 20% 98%;
```

To:

```css
--background: 48 75% 97%;        /* warm cream #fefcf3 — was 220 20% 98% (cool near-white) */
```

If a dark-mode `--background` exists (e.g. inside a `.dark { ... }` block), leave it untouched.

- [ ] **Step 3: Verify visually**

```bash
npm run dev -- --port 5173
```

Open `http://localhost:5173/` (Company Scorecard). The page bg should now be a barely-warm cream — not pure white, not yellow, not overpowering. White cards still pop on top.

- [ ] **Step 4: Commit**

```bash
git add src/index.css
git commit -m "feat(scorecard): warm cream page background (#fefcf3)"
```

---

## Task 3: MetricCard gauge bar + goal caption

**Files:**
- Modify: `src/components/dashboard/MetricCard.tsx`

Add three optional props. When `goalPct` is provided, render a thin gauge bar + caption below the existing value/footer block. Existing callers continue to work unchanged.

- [ ] **Step 1: Extend the props interface**

In `src/components/dashboard/MetricCard.tsx`, update the `MetricCardProps` interface:

```ts
interface MetricCardProps {
  title: string;
  value: string | number;
  subtitle?: string;
  trend?: "up" | "down" | "neutral";
  trendValue?: string;
  variant?: "default" | "success" | "warning" | "danger";
  icon?: React.ReactNode;
  className?: string;
  tooltip?: string;
  highlighted?: boolean;
  previousValue?: string;
  percentChange?: number;
  previousLabel?: string;
  footer?: React.ReactNode;
  /** 0..100 fill percent for the goal gauge bar. Hides the gauge when null/undefined. */
  goalPct?: number | null;
  /** Caption rendered below the gauge, e.g. "73% to 30% goal". */
  goalCaption?: string | null;
  /** Tone of the gauge fill. 'good' = emerald (at/over goal), 'progress' = yellow→amber, 'neutral' = muted. Defaults to 'progress'. */
  goalTone?: "good" | "progress" | "neutral";
}
```

- [ ] **Step 2: Destructure the new props**

Update the function signature:

```ts
export function MetricCard({
  title,
  value,
  subtitle,
  trend,
  trendValue,
  variant = "default",
  icon,
  className,
  tooltip,
  highlighted = false,
  previousValue,
  percentChange,
  previousLabel,
  footer,
  goalPct,
  goalCaption,
  goalTone = "progress",
}: MetricCardProps) {
```

- [ ] **Step 3: Add the gauge render block**

Inside the `<div className="flex-1">` block, AFTER the existing `previousValue !== undefined` block and BEFORE the `footer` line, insert:

```tsx
{goalPct !== null && goalPct !== undefined && (
  <div className="mt-3">
    <div className="h-1 bg-muted rounded-full overflow-hidden">
      <div
        className="h-full rounded-full"
        style={{
          width: `${Math.min(100, Math.max(0, goalPct))}%`,
          background:
            goalTone === "good"
              ? "linear-gradient(90deg, #34d399, #6ee7b7)"     // emerald
              : goalTone === "neutral"
              ? "#cbd5e1"                                       // slate-300
              : "linear-gradient(90deg, #f7b801, #fde047)",    // Twins yellow → amber
        }}
      />
    </div>
    {goalCaption && (
      <p className="text-[10px] text-muted-foreground mt-1.5">{goalCaption}</p>
    )}
  </div>
)}
```

Inline hex colors (not theme tokens) so the gauge renders correctly even when `--accent` resolution is finicky — same lesson we learned with `TierBadge` and `YearRibbon`.

- [ ] **Step 4: Add a small render test**

Create `src/components/dashboard/__tests__/MetricCard.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { MetricCard } from "../MetricCard";

describe("MetricCard", () => {
  it("renders title and value", () => {
    render(<MetricCard title="Revenue" value="$305,904" />);
    expect(screen.getByText("Revenue")).toBeInTheDocument();
    expect(screen.getByText("$305,904")).toBeInTheDocument();
  });

  it("does not render gauge when goalPct is null/undefined", () => {
    const { container } = render(<MetricCard title="Revenue" value="$305,904" />);
    expect(container.querySelector("[style*='linear-gradient']")).toBeNull();
  });

  it("renders gauge + caption when goalPct provided", () => {
    render(
      <MetricCard
        title="Membership"
        value="22%"
        goalPct={73}
        goalCaption="73% to 30% goal"
      />
    );
    expect(screen.getByText("73% to 30% goal")).toBeInTheDocument();
    const { container } = render(
      <MetricCard
        title="Membership"
        value="22%"
        goalPct={73}
        goalCaption="73% to 30% goal"
      />
    );
    expect(container.querySelector("[style*='gradient']")).not.toBeNull();
  });

  it("uses emerald tone when goalTone='good'", () => {
    const { container } = render(
      <MetricCard
        title="Callbacks"
        value="2.1%"
        goalPct={80}
        goalCaption="under 3% target"
        goalTone="good"
      />
    );
    const fill = container.querySelector("[style*='34d399']");
    expect(fill).not.toBeNull();
  });
});
```

- [ ] **Step 5: Run test**

```bash
npx vitest run src/components/dashboard/__tests__/MetricCard.test.tsx
```

Expected: 4 passing.

- [ ] **Step 6: Commit**

```bash
git add src/components/dashboard/MetricCard.tsx src/components/dashboard/__tests__/MetricCard.test.tsx
git commit -m "feat(scorecard): MetricCard gauge bar + goal caption (additive props)"
```

---

## Task 4: HeroScoreboard component

**Files:**
- Create: `src/components/dashboard/HeroScoreboard.tsx`
- Test: `src/components/dashboard/__tests__/HeroScoreboard.test.tsx`

Standalone component. All visual styling via inline hex (not theme tokens) for color stability.

- [ ] **Step 1: Implement the component**

Create `src/components/dashboard/HeroScoreboard.tsx`:

```tsx
type Tone = "up" | "down" | "flat";

export type HeroScoreboardProps = {
  /** Uppercase yellow label, e.g. "Total Sales · YTD". */
  label: string;
  /** Pre-formatted value, e.g. "$305,904". */
  valueFormatted: string;
  /** Small white-faded line under the value, e.g. "212 paid jobs". */
  subValue?: string;
  /** Single line of context above the divider, e.g. "309 opportunities · 68% closing · 2.1% callbacks". */
  contextLine?: string;
  /** Delta pill in the top-right; null hides it. */
  delta?: { label: string; tone: Tone } | null;
  /** Last N data points; null/empty hides the sparkline. */
  sparklineValues?: number[] | null;
};

const DELTA_BG: Record<Tone, string> = {
  up:   "rgba(4, 120, 87, 0.85)",   // emerald-700 @ 85%
  down: "rgba(185, 28, 28, 0.85)",  // red-700 @ 85%
  flat: "rgba(100, 116, 139, 0.85)", // slate-500 @ 85%
};

export function HeroScoreboard({
  label,
  valueFormatted,
  subValue,
  contextLine,
  delta,
  sparklineValues,
}: HeroScoreboardProps) {
  // Build sparkline polyline points if we have at least 2 values
  const sparkPath = (() => {
    const vs = (sparklineValues ?? []).filter((v) => Number.isFinite(v));
    if (vs.length < 2) return null;
    const max = Math.max(...vs);
    const min = Math.min(...vs);
    const span = max - min || 1;
    const stepX = 200 / (vs.length - 1);
    return vs
      .map((v, i) => `${i * stepX},${24 - ((v - min) / span) * 22 - 1}`)
      .join(" ");
  })();

  return (
    <div
      className="relative overflow-hidden rounded-2xl p-5 mb-4"
      style={{
        background: "linear-gradient(135deg, #0f1d4d 0%, #1e3a8a 100%)",
        color: "#ffffff",
        boxShadow: "0 6px 18px rgba(15, 29, 77, 0.12)",
      }}
    >
      {/* Yellow radial glow accent */}
      <div
        className="absolute pointer-events-none"
        style={{
          right: "-30px",
          top: "-40px",
          width: "160px",
          height: "160px",
          background: "radial-gradient(circle, rgba(247, 184, 1, 0.45), transparent 70%)",
        }}
      />

      {/* Top row: label + delta pill */}
      <div className="relative flex items-start justify-between gap-3">
        <div
          className="text-[11px] font-extrabold uppercase tracking-wider"
          style={{ color: "#f7b801" }}
        >
          {label}
        </div>
        {delta && (
          <span
            className="text-[10px] font-extrabold px-2 py-0.5 rounded-full whitespace-nowrap"
            style={{ background: DELTA_BG[delta.tone], color: "#ffffff" }}
          >
            {delta.label}
          </span>
        )}
      </div>

      {/* Big number */}
      <div
        className="relative font-extrabold tracking-tight leading-none mt-2 text-4xl md:text-5xl"
        style={{ color: "#ffffff" }}
      >
        {valueFormatted}
      </div>

      {/* Sub-value */}
      {subValue && (
        <div
          className="relative text-xs mt-1.5"
          style={{ color: "rgba(255, 255, 255, 0.7)" }}
        >
          {subValue}
        </div>
      )}

      {/* Sparkline */}
      {sparkPath && (
        <div className="relative mt-3 h-6">
          <svg viewBox="0 0 200 24" preserveAspectRatio="none" className="w-full h-full">
            <polyline points={sparkPath} fill="none" stroke="#f7b801" strokeWidth="2" />
          </svg>
        </div>
      )}

      {/* Context line + divider */}
      {contextLine && (
        <div
          className="relative text-[10.5px] mt-3 pt-2.5"
          style={{
            color: "rgba(255, 255, 255, 0.55)",
            borderTop: "1px solid rgba(255, 255, 255, 0.1)",
          }}
        >
          {contextLine}
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Add a render test**

Create `src/components/dashboard/__tests__/HeroScoreboard.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { HeroScoreboard } from "../HeroScoreboard";

describe("HeroScoreboard", () => {
  it("renders label, value, subValue, and contextLine", () => {
    render(
      <HeroScoreboard
        label="Total Sales · YTD"
        valueFormatted="$305,904"
        subValue="212 paid jobs"
        contextLine="309 opportunities · 68% closing"
      />
    );
    expect(screen.getByText("Total Sales · YTD")).toBeInTheDocument();
    expect(screen.getByText("$305,904")).toBeInTheDocument();
    expect(screen.getByText("212 paid jobs")).toBeInTheDocument();
    expect(screen.getByText("309 opportunities · 68% closing")).toBeInTheDocument();
  });

  it("renders delta pill with the right label", () => {
    render(
      <HeroScoreboard
        label="Total Sales"
        valueFormatted="$305,904"
        delta={{ label: "↑ 12% vs prior YTD", tone: "up" }}
      />
    );
    expect(screen.getByText("↑ 12% vs prior YTD")).toBeInTheDocument();
  });

  it("hides delta pill when null", () => {
    render(<HeroScoreboard label="Total Sales" valueFormatted="$0" delta={null} />);
    // No "↑" or "↓" pills present
    expect(screen.queryByText(/↑|↓/)).toBeNull();
  });

  it("hides sparkline when fewer than 2 data points", () => {
    const { container } = render(
      <HeroScoreboard label="Total Sales" valueFormatted="$0" sparklineValues={[100]} />
    );
    expect(container.querySelector("polyline")).toBeNull();
  });

  it("renders sparkline polyline when 2+ data points", () => {
    const { container } = render(
      <HeroScoreboard
        label="Total Sales"
        valueFormatted="$305,904"
        sparklineValues={[10, 20, 15, 25, 30]}
      />
    );
    expect(container.querySelector("polyline")).not.toBeNull();
  });
});
```

- [ ] **Step 3: Run tests**

```bash
npx vitest run src/components/dashboard/__tests__/HeroScoreboard.test.tsx
```

Expected: 5 passing.

- [ ] **Step 4: Commit**

```bash
git add src/components/dashboard/HeroScoreboard.tsx src/components/dashboard/__tests__/HeroScoreboard.test.tsx
git commit -m "feat(scorecard): HeroScoreboard component (navy panel + delta pill + yellow sparkline)"
```

---

## Task 5: Wire HeroScoreboard + delta into Index.tsx

**Files:**
- Modify: `src/pages/Index.tsx`

Replace the existing inline hero block (lines around 350-400 — the "TOTAL SALES" card with the `chip-yellow` "HERO METRIC" label and the inline sparkline) with `<HeroScoreboard>`. Add a second `useDashboardData` call for the prior period. Keep all surrounding sections unchanged.

- [ ] **Step 1: Add imports**

At the top of `src/pages/Index.tsx`, add:

```ts
import { HeroScoreboard } from "@/components/dashboard/HeroScoreboard";
import { priorPeriodAsDateRange } from "@/lib/dashboard/period-helpers";
```

- [ ] **Step 2: Add prior-period data hook**

Inside the `Index` component body, AFTER the existing `useDashboardData(dateRange)` line, add:

```ts
const priorRange = useMemo(() => priorPeriodAsDateRange(dateRange), [dateRange]);
const { totalRevenue: priorRevenue } = useDashboardData(priorRange);
```

(`useMemo` here so the React Query key stays stable — same lesson from PR #22.)

- [ ] **Step 3: Compute the delta**

Below the prior-period hook, add:

```ts
const heroDelta = useMemo(() => {
  if (!priorRevenue || priorRevenue <= 0) return null;
  const pct = ((totalRevenue - priorRevenue) / priorRevenue) * 100;
  if (Math.abs(pct) < 0.5) return { label: "≈ flat vs prior period", tone: "flat" as const };
  const sign = pct > 0 ? "↑" : "↓";
  const tone = pct > 0 ? ("up" as const) : ("down" as const);
  return { label: `${sign} ${Math.abs(pct).toFixed(0)}% vs prior period`, tone };
}, [totalRevenue, priorRevenue]);
```

(`totalRevenue` is already in scope from the existing `useDashboardData(dateRange)` call.)

- [ ] **Step 4: Build sparkline data**

The existing hero block already has a sparkline somewhere — search `Index.tsx` for `polyline` or `sparkline` to find the data source. If the data lives inline, extract it into:

```ts
const heroSparkline = useMemo<number[]>(() => {
  // Daily revenue buckets for the current dateRange. Re-uses paidJobs from useDashboardData.
  if (!paidJobs || paidJobs.length === 0) return [];
  const byDay = new Map<string, number>();
  for (const j of paidJobs) {
    const day = (j.invoice_paid_at ?? j.completed_at ?? "").slice(0, 10);
    if (!day) continue;
    byDay.set(day, (byDay.get(day) ?? 0) + Number(j.revenue_amount ?? 0));
  }
  return [...byDay.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([, v]) => v);
}, [paidJobs]);
```

If the existing inline sparkline already used a similar transformation, prefer reusing that one over duplicating logic.

- [ ] **Step 5: Replace the inline hero block with `<HeroScoreboard>`**

Find the hero block in `Index.tsx` (search for `HERO METRIC` or `chip-yellow`). Replace the entire `<Card>` (including the title row, value, sparkline, and any wrapping div) with:

```tsx
<HeroScoreboard
  label={`Total Sales · ${dateRangeLabel}`}
  valueFormatted={fmtCurrency(totalRevenue)}
  subValue={`${paidJobs?.length ?? 0} paid jobs`}
  contextLine={`${jobs?.length ?? 0} opportunities · ${closingPctText} closing · ${callbackPctText} callbacks`}
  delta={heroDelta}
  sparklineValues={heroSparkline}
/>
```

Where:
- `dateRangeLabel` is the existing label (search Index.tsx — "YTD" / "30D" / etc. should already be derivable from `dateRange`)
- `fmtCurrency` is the existing import from `@/lib/export-utils`
- `closingPctText` = `fmtPercent(calculateClosingPercentage(jobs))` (the existing call site)
- `callbackPctText` = `fmtPercent(calculateCallbackRate(jobs))` (the existing call site)

If any of those vars need to be created/renamed to feed the HeroScoreboard cleanly, do so in this commit.

- [ ] **Step 6: Verify TS + visual**

```bash
npx tsc --noEmit
npm run dev -- --port 5173
```

Open `http://localhost:5173/`. The hero should now be the navy gradient panel with yellow label + extrabold white number + delta pill + yellow sparkline + context line.

- [ ] **Step 7: Commit**

```bash
git add src/pages/Index.tsx
git commit -m "feat(scorecard): wire HeroScoreboard with delta + sparkline on Index"
```

---

## Task 6: Thread `goalPct` + `goalCaption` into existing MetricCards

**Files:**
- Modify: `src/pages/Index.tsx`

Each `<MetricCard>` invocation in `Index.tsx` should compute its `goalPct` and `goalCaption` from the existing `useCompanyGoals().getGoal(kpi)` value vs the live KPI value.

- [ ] **Step 1: Add a helper at the top of `Index.tsx`**

Inside the file (above the `Index` component), add:

```ts
type GoalDir = "higher" | "lower";

function computeGoalPct(value: number, goal: number, dir: GoalDir = "higher"): number | null {
  if (!goal || goal <= 0) return null;
  if (dir === "higher") return Math.min(100, Math.max(0, (value / goal) * 100));
  // lower-better (e.g. callbacks): we're "100%" when value is at-or-below goal
  if (value <= goal) return 100;
  // tail off as value gets worse — show how close to goal we are
  return Math.max(0, Math.min(100, (goal / value) * 100));
}

function computeGoalCaption(value: number, goal: number, unit: "usd" | "pct" | "count", dir: GoalDir = "higher"): string {
  if (!goal || goal <= 0) return "";
  const fmt = (n: number) =>
    unit === "usd"
      ? n >= 1000
        ? `$${(n / 1000).toFixed(1)}k`
        : `$${n.toFixed(0)}`
      : unit === "pct"
      ? `${n.toFixed(0)}%`
      : String(Math.round(n));
  if (dir === "lower") {
    if (value <= goal) return `under ${fmt(goal)} target ✓`;
    return `${fmt(value - goal)} over ${fmt(goal)} target`;
  }
  const pct = (value / goal) * 100;
  return `${pct.toFixed(0)}% to ${fmt(goal)} goal`;
}

function goalToneFor(value: number, goal: number, dir: GoalDir = "higher"): "good" | "progress" {
  if (!goal || goal <= 0) return "progress";
  if (dir === "higher") return value >= goal ? "good" : "progress";
  return value <= goal ? "good" : "progress";
}
```

- [ ] **Step 2: Apply to each MetricCard**

For each `<MetricCard>` in `Index.tsx`, add the three goal props. Example for the Opportunity Job Average card:

```tsx
<MetricCard
  title="Opp Job Avg"
  value={fmtCurrency(opportunityJobAvg)}
  // ... existing props ...
  goalPct={computeGoalPct(opportunityJobAvg, getGoal("opportunity_job_avg"), "higher")}
  goalCaption={computeGoalCaption(opportunityJobAvg, getGoal("opportunity_job_avg"), "usd", "higher")}
  goalTone={goalToneFor(opportunityJobAvg, getGoal("opportunity_job_avg"), "higher")}
/>
```

Apply the same pattern to:

| Title | Goal key (per `useCompanyGoals`) | Unit | Direction |
|---|---|---|---|
| Opp Job Avg | `opportunity_job_avg` | usd | higher |
| Repair Avg | `repair_avg` | usd | higher |
| Install Avg | `install_avg` | usd | higher |
| Closing % | `closing_pct` | pct | higher |
| Membership % | `membership_pct` | pct | higher |
| Callback % | `callback_pct` | pct | **lower** |

(Goal-key strings: confirm by reading `src/hooks/use-company-goals.ts` — use the actual keys exposed by `getGoal`. If a goal isn't defined for a KPI, `getGoal` returns null/undefined and `computeGoalPct` returns null → the gauge bar simply doesn't render.)

- [ ] **Step 3: Verify TS + visual**

```bash
npx tsc --noEmit
npm run dev -- --port 5173
```

Open `/`. Each KPI tile should show a gauge bar + caption (yellow gradient, or emerald when at/over goal). KPIs without a defined goal show the existing card unchanged.

- [ ] **Step 4: Commit**

```bash
git add src/pages/Index.tsx
git commit -m "feat(scorecard): goal gauges on every KPI tile (yellow when below, emerald when at/over)"
```

---

## Task 7: Tier badges in TechnicianBreakdown

**Files:**
- Modify: `src/components/dashboard/TechnicianBreakdown.tsx`

Add a "Tier" column at the right of the existing table. Tier is computed from each tech's revenue using the existing `computeTier` helper + `useTierThresholds` hook (both shipped with the tech-portal redesign).

- [ ] **Step 1: Add imports**

At the top of `src/components/dashboard/TechnicianBreakdown.tsx`:

```ts
import { useTierThresholds } from "@/hooks/tech/useTierThresholds";
import { computeTier } from "@/lib/tech-portal/compute-tier";
import { TierBadge } from "@/components/tech/TierBadge";
```

- [ ] **Step 2: Pull thresholds in the component**

Inside the `TechnicianBreakdown` component body (search for `function TechnicianBreakdown` or `export function TechnicianBreakdown`), add early in the function:

```ts
const { data: thresholds = [] } = useTierThresholds();
```

- [ ] **Step 3: Add the Tier column header**

In the `<TableHeader>` block, add a new `<TableHead>` at the rightmost position:

```tsx
<TableHead className="text-right whitespace-nowrap">Tier</TableHead>
```

(Match the existing `<TableHead>` formatting style — likely uppercase / muted-foreground.)

- [ ] **Step 4: Render the tier badge in each row**

In the `<TableBody>`'s row map, add a final `<TableCell>` after the last existing one:

```tsx
<TableCell className="text-right">
  <TierBadge tier={computeTier(techRow.revenue, "revenue", thresholds, [])} />
</TableCell>
```

Where `techRow.revenue` is the existing per-tech revenue field already computed in the row. (Confirm the field name in the row-map; if it's called something else like `techRevenue` or `total_revenue`, use that.)

- [ ] **Step 5: Verify TS + visual**

```bash
npx tsc --noEmit
npm run dev -- --port 5173
```

Open `/` and scroll to the technician breakdown. Each row should now show a Bronze / Silver / Gold / Elite badge in the rightmost column. Badge colors match the tech portal's per-KPI badges (the same `TierBadge` component).

- [ ] **Step 6: Commit**

```bash
git add src/components/dashboard/TechnicianBreakdown.tsx
git commit -m "feat(scorecard): tier badge column in TechnicianBreakdown (revenue-tiered)"
```

---

## Task 8: Final verification + PR

**Files:** none

- [ ] **Step 1: Full type check + tests + build**

```bash
cd /Users/daniel/twins-dashboard/twins-dash/.worktrees/scorecard-oomph
npx tsc --noEmit
npx vitest run
npm run build 2>&1 | tail -8
```

Expected: tsc clean, all tests green, build succeeds.

- [ ] **Step 2: Visual sweep**

```bash
npm run dev -- --port 5173
```

Open `http://localhost:5173/` (the Company Scorecard) and verify:

- Twins logo at top is the existing yellow shield image, not a CSS reconstruction
- Page background is a barely-warm cream (not pure white, not yellow)
- Date filter strip + Sync button render unchanged
- Hero is a navy gradient panel with yellow uppercase label, extrabold white number, green/red/slate delta pill, yellow sparkline, white-faded context line at the bottom
- Each KPI tile has a gauge bar at the bottom (yellow→amber when below goal, emerald when at/over)
- Each KPI tile has a "X% to {goal}" caption beneath the gauge
- Technician breakdown table has a Tier column at the right with bronze/silver/gold/elite badges
- ComparisonSection, RevenueTrendChart, and ExportButtons all still render

Check the same flow on a 375px viewport (DevTools > iPhone). No horizontal scroll, no clipped tiles.

- [ ] **Step 3: Push branch**

```bash
git push -u origin feat/scorecard-oomph
```

- [ ] **Step 4: Open PR via GitHub API**

Per memory `reference_gh_via_api.md`:

```bash
TOKEN=$(printf "protocol=https\nhost=github.com\n" | git credential-osxkeychain get | grep '^password=' | cut -d= -f2)
curl -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.github+json" \
  -d "$(cat <<'EOF'
{
  "title": "feat(scorecard): oomph redesign — Scoreboard hero + KPI gauges + tier breakdown + cream bg",
  "head": "feat/scorecard-oomph",
  "base": "main",
  "body": "Implements docs/superpowers/specs/2026-04-26-company-scorecard-oomph-design.md per the implementation plan in docs/superpowers/plans/2026-04-26-company-scorecard-oomph.md.\n\n## Summary\n- New HeroScoreboard component (navy gradient panel + delta pill + yellow sparkline + context line)\n- MetricCard gains optional gauge bar + goal caption (additive props; existing callers unchanged)\n- TechnicianBreakdown gains a Tier column (Bronze/Silver/Gold/Elite per tech, revenue-tiered)\n- Page background swaps from cool near-white to warm cream #fefcf3\n- Twins logo asset preserved exactly\n- KPI math (kpi-calculations.ts) untouched\n\n## Test plan\n- [x] tsc clean\n- [x] vitest all passing\n- [x] build clean\n- [ ] Mobile viewport (375px) — no horizontal scroll, no clipped tiles\n- [ ] Hero delta pill renders for YTD vs last YTD comparison\n- [ ] Each KPI tile shows gauge bar + caption (when goal exists)\n- [ ] Tech breakdown shows tier badges\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"
}
EOF
)"
```

- [ ] **Step 5: Return PR URL to user**

---

## Self-Review

**Spec coverage check:**

| Spec section | Covered by |
|---|---|
| Direction A (Scoreboard) | Tasks 4, 5 (HeroScoreboard) |
| Warm cream background `#fefcf3` | Task 2 |
| Twins logo preserved | Untouched in all tasks (verified in Task 8 visual sweep) |
| Hero delta pill | Tasks 4 (component), 5 (wiring) |
| Hero context line | Task 5 step 5 |
| Hero sparkline | Tasks 4 (render), 5 (data) |
| KPI gauge bars + goal captions | Tasks 3 (props), 6 (wiring) |
| Tier badges in TechnicianBreakdown | Task 7 |
| `getPreviousPeriod` lifted to shared module | Task 1 |
| Second `useDashboardData(priorRange)` for delta | Task 5 step 2 |
| Tier source = revenue (not closing %, etc.) | Task 7 step 4 (uses `'revenue'` kpi_key) |
| KPI math untouched | All tasks; explicitly verified by `npx tsc --noEmit` against current `kpi-calculations.ts` |
| `useDashboardData`, `useCompanyGoals`, `useTierThresholds` reused as-is | Tasks 5, 6, 7 |

**Placeholder scan:** No "TBD" / "TODO" / "implement later" / "add appropriate error handling" / "similar to Task N" patterns. Each step has the exact code, exact file path, and exact verification command.

**Type consistency:**
- `HeroScoreboardProps.delta` shape `{ label: string; tone: "up" | "down" | "flat" }` is consistent across Tasks 4 and 5.
- `MetricCardProps` new fields `goalPct` (number | null), `goalCaption` (string | null), `goalTone` ('good' | 'progress' | 'neutral') are consistent across Tasks 3 and 6.
- `Tier` type imported from `@/components/tech/TierBadge` in Task 7 — same import path used in tech portal code.
- `priorPeriodAsDateRange` (Task 1) returns `DateRange | undefined` — same shape `useDashboardData` accepts.

No issues to fix. Plan is ready.

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-26-company-scorecard-oomph.md`. Two execution options:

1. **Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration. Good fit for this plan's 8 tasks.

2. **Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
