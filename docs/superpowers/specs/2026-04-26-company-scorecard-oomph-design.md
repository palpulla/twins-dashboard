# Company Scorecard "Oomph" Redesign Spec

**Date:** 2026-04-26
**Status:** Design approved, ready for implementation plan
**Scope:** Visual refresh of the Company Scorecard page (`twins-dash/src/pages/Index.tsx`) and the dashboard components it composes. Anchored to the approved "Scoreboard" direction with a warm cream page background. Same data, same KPIs, same hooks. The Twins logo, navy + yellow brand palette, and existing component contracts are preserved.

## Context

The Company Scorecard at `twinsdash.com/` is the canonical visual surface for the dashboard product. Daniel reviewed the live page on 2026-04-26 and described it as "bland". A brainstorm in the visual companion explored three directions (Scoreboard / Morning briefing / Goals & pacing) plus three subtle background tints. Daniel approved:

- **Direction:** A — Scoreboard (confident command-center: navy hero panel with yellow accents, KPI gauges).
- **Background:** Warm cream `#fefcf3` (subtle yellow tint that complements the brand without overpowering).
- **Logo:** keep the existing `/twins-logo.webp` + `/twins-logo.png` assets exactly as today.

## Goals

- Add visual "oomph": stronger hierarchy, better use of brand colors, more confident hero treatment.
- Show goal context on every KPI tile (gauge bar + "X% to goal" caption) using the existing `useCompanyGoals` hook.
- Add a delta pill on the hero comparing the current period to the prior equivalent period (e.g. "↑ 12% vs prior YTD").
- Adjust the page background from near-white `hsl(220 20% 98%)` to warm cream `#fefcf3` (HSL: `48 75% 97%`).
- Apply tier badges (Bronze / Silver / Gold / Elite) to the technician breakdown rows so the company scorecard mirrors the per-tech scorecards.
- Keep all data calculations, hooks (`useDashboardData`, `useCompanyGoals`, KPI calc functions in `kpi-calculations.ts`), Sync Now action, and Export buttons working unchanged.

## Non-goals

- No changes to KPI math (`src/lib/kpi-calculations.ts` is sacred per CLAUDE.md).
- No new data sources, RPCs, or migrations.
- No changes to the routing, AppShell, or other pages (Tech portal `/tech/*`, Payroll, Admin, etc.). The tech-portal navy + yellow tokens it already uses are unchanged.
- No animations beyond what shadcn defaults give us (no count-up, no tween libraries). YAGNI.
- No new chart library. We continue using the existing `RevenueTrendChart`, `ComparisonSection`, and the `recharts` baseline.
- No removal of the Sync Now button, ExportButtons, or InfoTip surfaces.

## Visual language

| Element | Today | After |
|---|---|---|
| Page background | `--background: 220 20% 98%` (cool near-white) | `48 75% 97%` (warm cream `#fefcf3`) |
| Card background | `#ffffff` | unchanged `#ffffff` (cards still pop on the cream bg) |
| Hero metric | white card with `chip chip-yellow` "HERO METRIC" pill, navy number, simple sparkline below | navy gradient panel (`#0f1d4d → #1e3a8a`), yellow `--accent` label and sparkline, white extrabold number, green delta pill, radial yellow glow accent in top-right |
| KPI tiles | white card with label, big number, optional "target $X" green pill | white card with label, big number, **gauge bar** (yellow→amber gradient when below goal, emerald when at/over goal), "X% to {goal}" caption underneath |
| Technician breakdown | existing table | same table + a **TierBadge** per row in the rightmost column, matching the tech portal's badge styles |
| Date filter strip + Sync button | unchanged | unchanged |
| Logo | `/twins-logo.webp` + `.png` in header | unchanged — same asset, same position |

The Twins logo asset stays exactly as it is today (the yellow shield "TWINS / GARAGE DOORS" image). No CSS recreation, no SVG redraw.

## Components

### New: `HeroScoreboard` (`src/components/dashboard/HeroScoreboard.tsx`)

Replaces the existing inline hero block in `Index.tsx` (lines ~352–360 and the surrounding sparkline JSX).

```ts
type Props = {
  label: string;                 // e.g. "Total Sales · YTD"
  valueFormatted: string;        // pre-formatted by caller, e.g. "$305,904"
  subValue: string;              // e.g. "212 paid jobs"
  contextLine: string;           // e.g. "309 opportunities · 68% closing · 2.1% callbacks"
  delta: { label: string; tone: 'up' | 'down' | 'flat' } | null;  // e.g. { label: '↑ 12% vs prior YTD', tone: 'up' }
  sparklineValues: number[];     // last N data points
};
```

Rendering:

- Container: `relative overflow-hidden rounded-2xl p-5` with inline gradient background `linear-gradient(135deg, #0f1d4d 0%, #1e3a8a 100%)` and `color: #ffffff`. Explicit hex (not theme tokens) — same pattern we used for `YearRibbon` and `TierBadge` after the `text-accent` resolution issue.
- Top-right radial yellow glow: `position:absolute; right:-30px; top:-40px; width:160px; height:160px; background:radial-gradient(circle, rgba(247,184,1,0.45), transparent 70%)`.
- Top row: yellow uppercase label (`#f7b801`) on the left, delta pill on the right (green `rgba(4,120,87,.85)` for up, red `rgba(185,28,28,.85)` for down, slate `rgba(100,116,139,.85)` for flat).
- Middle: extrabold white value (`text-4xl` mobile, `text-5xl` md+).
- Below value: white/70 subValue text.
- Sparkline: 24px tall SVG, yellow stroke (`#f7b801`), `preserveAspectRatio="none"`.
- Bottom: small white/55 context line above a 1px white/10 divider.

### Refactor: `MetricCard` (`src/components/dashboard/MetricCard.tsx`)

Existing component already handles label + value + optional `target` chip. Add two optional props:

- `goalPct?: number | null` — 0..100 fill percent for the gauge bar
- `goalCaption?: string | null` — e.g. "73% to 30% goal"
- `goalTone?: 'good' | 'progress' | 'neutral'` — drives gauge color

When `goalPct` is provided, render a 4px-tall gauge bar below the value:
- `progress` tone → `linear-gradient(90deg, #f7b801, #fde047)` (Twins yellow → amber)
- `good` tone → `linear-gradient(90deg, #34d399, #6ee7b7)` (emerald)
- `neutral` tone → `bg-muted`

Below the gauge: `text-[10px] text-muted-foreground` caption (the `goalCaption` string).

The existing `target` chip behavior is preserved when `goalPct` is omitted, so no caller breaks.

### Refactor: `TechnicianBreakdown` (`src/components/dashboard/TechnicianBreakdown.tsx`)

Add a "Tier" column at the right of the table (or as a row badge on mobile cards). The tier is computed from each tech's `revenue` for the current dateRange via `computeTier(revenue, 'revenue', thresholds, [])` — using `revenue` (not closing %, not avg ticket) as the single tier-source KPI for the company-scorecard breakdown. Per-KPI tier badges already exist in the tech portal; this surface keeps it simple with one ranking dimension.

Reuse the `TierBadge` component from `src/components/tech/TierBadge.tsx` — already styled with explicit hex colors so it renders correctly on cream bg. Reuse `useTierThresholds` to fetch thresholds.

### Modified: `Index.tsx`

- Replace the existing hero block with `<HeroScoreboard ... />`. Keep the surrounding `<div>` grid structure.
- For each `MetricCard` invocation, compute `goalPct` and `goalCaption` from the existing `useCompanyGoals().getGoal(kpi)` value vs the live KPI value, then pass to `MetricCard`.
- No other structural changes — the page sections (date picker, comparison section, revenue trend chart, technician breakdown) stay in their current order and structure.

### Modified: `src/index.css` (theme tokens)

Update the page background token only:

```css
:root {
  --background: 48 75% 97%;        /* warm cream — was 220 20% 98% */
}
```

Cards (`--card: 0 0% 100%`) stay pure white so they POP on the cream bg. All other tokens unchanged.

If the dark-mode block exists, leave it untouched — the cream bg only applies to light mode.

## Data flow

No new data sources. The hero scoreboard's `delta` is computed in `Index.tsx`:
- Take current-period total revenue from `useDashboardData(jobs)` (already in scope).
- Compute prior-period range using `getPreviousPeriod(dateRange)` lifted from `use-technician-data.ts` to a new shared module `src/lib/dashboard/period-helpers.ts`.
- Run a **second** `useDashboardData(priorRange)` call. React Query keys differ by date range so this dedupes cleanly. (Avoids re-implementing KPI math against a filtered `jobs` array.)
- Compute `delta = (current - prior) / prior * 100`.

If prior period has zero revenue, the delta pill is hidden (don't show "↑ ∞%" or divide-by-zero).

## File structure

### New files

- `src/components/dashboard/HeroScoreboard.tsx`
- `src/lib/dashboard/period-helpers.ts` — extracts `getPreviousPeriod(dateRange)` to a shared location (no behavior change; just lifted out of `use-technician-data.ts` so `Index.tsx` can use it without coupling).

### Modified files

- `src/pages/Index.tsx` — swap inline hero for `HeroScoreboard`; thread `goalPct` + `goalCaption` into each `MetricCard`.
- `src/components/dashboard/MetricCard.tsx` — add gauge bar + goal caption support.
- `src/components/dashboard/TechnicianBreakdown.tsx` — add tier column with `TierBadge`.
- `src/index.css` — update `--background` token.

### Untouched

- `src/lib/kpi-calculations.ts` — sacred.
- `src/hooks/use-dashboard-data.ts`, `use-technician-data.ts`, `use-company-goals.ts` — reused as-is.
- `src/components/dashboard/ComparisonSection.tsx`, `RevenueTrendChart.tsx`, `SemiGauge.tsx`, `DateRangePicker.tsx`, `ExportButtons.tsx` — visually consistent with the new look already, no changes needed.
- `public/twins-logo.webp`, `public/twins-logo.png` — keep as-is, used unchanged in the existing header.
- The Tech portal pages (`src/pages/tech/*`) and Admin pages — visually consistent with the new look already, no changes needed.

## Out of scope (future polish)

- Count-up animation on the hero number (premium feel; not requested).
- Sparkline tooltips / hover for exact values (would need data-mapping refactor).
- Per-tile drill-in (the tech portal has this; the company scorecard intentionally stays "at a glance").
- A "morning briefing" yellow banner with a 1-line read of the data (B option from the brainstorm; can revisit if the Scoreboard alone doesn't feel narrative enough).
- Goal-pacing on the hero ("on pace for $920k by year end") — option C from the brainstorm; can add later as a single subline if Daniel sets goals seriously and wants the projection.

## Visual reference

The approved mockup lives at `twins-dash/.superpowers/brainstorm/74778-1777233066/content/scoreboard-full.html` (the third phone in the row, "Subtle warm cream"). Open it in the visual companion server before implementing if you need to recheck spacing, font sizes, or color values.

## Risk & rollback

- All changes are additive UI props or token swaps. Reversion is a single revert commit.
- The `--background` token change is applied in `src/index.css`; if the cream tint clashes with any non-Index page in unexpected ways, revert that one line.
- No DB or Edge Function changes. No migrations.
