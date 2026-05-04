# Rev & Rise Tab — Design Spec

**Date:** 2026-05-04
**Repo:** `twins-dash`
**Status:** Approved for planning

## Goal

Replace the underused `/team` (Supervisor) page with a `/rev-rise` page purpose-built for the Mon/Wed/Fri Rev & Rise calls. Keep the one supervisor metric Daniel actually uses (avg appts/day) by surfacing it on the main Dashboard he already checks daily.

## Scope

Two coordinated changes in a single PR:

1. **Remove Supervisor page**, build new **Rev & Rise tab** at `/rev-rise`.
2. **Surface avg appts/day on the main Dashboard** (Index.tsx) — per-tech card row plus a company-level KPI tile.

Out of scope for v1: holiday/skip-call calendar overrides, manual notes per call, scheduled AI commentary, multi-supervisor/multi-team support.

## Approach

Reuse the existing `useSupervisorData` hook compute layer (rename and rescope), build new presentation. The hook's `jobsPerDay` math is already validated and correct for the appts/day metric. Net new code is the call-window logic, the page shell, the day-ahead query, and the AI commentary trigger.

## Routing & nav

- **New route:** `/rev-rise` → `RevRiseDashboard.tsx`. Admin-only via existing `requiredPermission` pattern (admin bypass works as today).
- **Removed:** `/team` route, `SupervisorDashboard.tsx` page, `use-supervisor-data.ts` hook (renamed, see below).
- **Redirect:** `/team` → `<Navigate to="/rev-rise" replace />` so any bookmarks land correctly.
- **Nav swap:** "Supervisor" item in `AppShellWithNav.tsx` becomes "Rev & Rise"; icon changes from `Users` to `TrendingUp`.
- **Dormant DB state:** the `field_supervisor` role and `view_team` permission stay in the schema, unreferenced by code (per the reversibility rule). They can be removed in a later cleanup once we confirm nothing else uses them.

## Call window logic

A pure function in `src/lib/rev-rise/call-window.ts`:

```ts
type CallDay = "monday" | "wednesday" | "friday";

interface CallWindow {
  callDay: CallDay;
  callDate: Date;     // the date the call runs/ran (00:00 local)
  rangeStart: Date;   // first day the call covers (00:00 local)
  rangeEnd: Date;     // last day the call covers (23:59:59.999 local)
  isLive: boolean;    // true when today is callDate
  label: string;      // e.g. "Monday call · covers Fri-Sun (May 1-3)"
}

function resolveCallWindow(today: Date, offset = 0): CallWindow;
```

**Coverage map:**
- Monday call → previous Friday + Saturday + Sunday (3 days)
- Wednesday call → Monday + Tuesday (2 days)
- Friday call → Wednesday + Thursday (2 days)

**Default behavior** (offset = 0):
- If today is Mon/Wed/Fri → returns today's call (with `isLive: true`).
- Else → returns the most recent call's window (Tue/Thu/Sat/Sun → prior call day).

**Navigation:** prev/next arrows in the page header bump the offset value held in component state. `offset = -1` returns one call back, `offset = +1` returns the next upcoming call.

**Edge cases:**
- Holidays / skipped calls: not modelled in v1. The page still renders the window even if the call didn't happen.
- DST transitions: local-time day boundaries; the helper uses `Date` arithmetic in local timezone (America/Chicago — Madison, WI), matching the rest of the dashboard's date logic.
- Year boundaries: a Friday-Jan-2 call covers Dec 31 + Jan 1.

## Data flow

```
RevRiseDashboard.tsx
  ├── useState<offset>                        // prev/next arrow state
  ├── callWindow = resolveCallWindow(now, offset)
  │
  ├── useRevRiseData(callWindow)              // renamed from useSupervisorData
  │     scopes the jobs query to rangeStart..rangeEnd
  │     returns: { teamTotals, technicianKPIs[], alerts[] }
  │
  ├── useDayAheadJobs(callDate)               // new hook
  │     queries jobs WHERE scheduled_at IN [callDate+1d, callDate+1d EOD]
  │     groups by tech_id; returns { techId, count, estRevenue }[]
  │
  ├── useRevRiseInsights(callWindow)          // new hook for AI panel
  │     React Query, enabled: false by default
  │     refetch() called when "Generate AI commentary" clicked
  │     calls the existing AI suggestions edge function with a
  │     rev-rise-specific prompt variant
  │
  └── handleSyncNow()                         // same handler as Index.tsx
        triggers sync-jobs edge function, then invalidates rev-rise keys
```

**Wins block** is computed in-component from the hook's data — no AI, no extra query. Rules:

- Top revenue tech for the window (only if revenue > $0).
- Highest close % among techs with ≥3 opportunities (filters out small-N noise).
- Biggest avg-ticket day (computed from job-day buckets within the window).
- Open-estimate flag callout: any tech with ≥3 open estimates totaling >$2.5k gets named.

If the window has zero jobs (e.g. holiday week, or sync hasn't run), the wins block shows: "Nothing to celebrate yet — sync HCP if you expect data here."

**Cache:** React Query `staleTime: 30_000` matches existing dashboard cadence. Each `(callDate, offset)` pair gets a unique cache key, so prev/next nav reuses cached windows.

**Sync button:** identical pattern to Index.tsx's `handleSyncNow`. Calls `sync-jobs` edge function, then `queryClient.invalidateQueries({ queryKey: ['rev-rise', ...] })`.

## Page layout

Mobile-first single column; 2-column on `md+`. Visual language mirrors Index.tsx (per Daniel's "scorecard follows Index.tsx" rule).

```
┌─────────────────────────────────────────────────────────────┐
│ [<] Monday call · covers Fri-Sun (May 1-3)  [>]             │
│       [● Live · running now]   [Sync HCP]   [✨ AI insight] │
├─────────────────────────────────────────────────────────────┤
│ Revenue   Jobs   Close %   Avg ticket   Avg appts/day       │
│  $24.1k    47     42.3%      $1.2k         3.4              │
├─────────────────────────────────────────────────────────────┤
│ 🏆 Wins                                                      │
│  • Maurice closed 78% on 9 opps                             │
│  • Saturday hit $9.4k — highest day this call               │
│  • Charles + Nicholas each landed an install >$3k           │
├─────────────────────────────────────────────────────────────┤
│ Per-tech (cards)                                             │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐             │
│  │ Charles    │  │ Maurice    │  │ Nicholas   │             │
│  │ Supervisor │  │ Tech       │  │ Tech       │             │
│  │ $7.1k · 11j│  │ $8.2k · 14j│  │ $4.9k · 9j │             │
│  │ 64% · $1.8k│  │ 78% · $1.4k│  │ 55% · $1.1k│             │
│  │ 2.8/day    │  │ 3.5/day    │  │ 4.5/day    │             │
│  │  2 open    │  │ ⚠ 4 open   │  │  1 open    │             │
│  │  ($1.8k)   │  │   ($6.2k)  │  │  ($800)    │             │
│  └────────────┘  └────────────┘  └────────────┘             │
├─────────────────────────────────────────────────────────────┤
│ 📅 Day Ahead — Tuesday May 5                                 │
│  Charles  3 jobs · est $2.1k                                │
│  Maurice  4 jobs · est $2.8k                                │
│  Nicholas 5 jobs · est $3.0k                                │
├─────────────────────────────────────────────────────────────┤
│ ✨ AI commentary  [click button to generate]                 │
└─────────────────────────────────────────────────────────────┘
```

**Module rules:**
- The "⚠ open estimates" warning badge appears only when count ≥3 OR value ≥$2.5k. Otherwise the line shows the plain count.
- Per-tech cards are clickable links to `/tech?as=<tech_id>` for during-call drill-downs.
- "Day Ahead" only renders when `offset === 0` (it's only meaningful for the most-recent call window).
- Empty states: each section has a one-line empty message. No bare zeros.

**Roster source-of-truth:** the per-tech card list reads from the same `TECHNICIANS` constant that powers `TechnicianBreakdown.tsx`. No hardcoded tech names in `RevRiseDashboard.tsx`. Adding a tech to the constant flows them through automatically.

## Avg appts/day on Index.tsx

Two additions to the main Dashboard, both reusing already-loaded jobs (no new queries):

**1. Per-tech card row** (in `TechnicianBreakdown.tsx`):

Add a 5th metric row to each `TechCard`:

```
Appts/day    3.4
```

Computed as `techJobs.length / workingDays` for the dashboard's selected date range. Counts every appointment on the tech's schedule (estimates, $0, canceled, in-progress, completed) — same denominator semantics as the supervisor hook's `jobsPerDay`.

**2. Company-level KPI tile** in the existing tile strip on Index.tsx:

```
Avg appts/day
    3.4
```

Computed as `totalScheduledJobs / workingDays` across all techs in range.

**Working-day count:** Mon-Fri count between range start/end. If a shared business-day helper exists in the dashboard's date utilities, reuse it; otherwise inline a simple Mon-Fri counter for v1 (no holiday calendar — the existing `jobsPerDay` doesn't model that either, so we stay consistent).

## File changes summary

```
NEW    src/pages/RevRiseDashboard.tsx
NEW    src/components/rev-rise/CallWindowHeader.tsx
NEW    src/components/rev-rise/CompanyKpiStrip.tsx
NEW    src/components/rev-rise/WinsBlock.tsx
NEW    src/components/rev-rise/PerTechCards.tsx
NEW    src/components/rev-rise/DayAhead.tsx
NEW    src/components/rev-rise/AiCommentaryPanel.tsx
NEW    src/lib/rev-rise/call-window.ts
NEW    src/lib/rev-rise/wins-rules.ts
NEW    src/lib/rev-rise/__tests__/call-window.test.ts
NEW    src/lib/rev-rise/__tests__/wins-rules.test.ts
NEW    src/hooks/use-rev-rise-data.ts          (renamed from use-supervisor-data.ts)
NEW    src/hooks/use-day-ahead-jobs.ts
NEW    src/hooks/use-rev-rise-insights.ts
NEW    src/pages/__tests__/RevRiseDashboard.test.tsx

EDIT   src/App.tsx                              (route swap + redirect)
EDIT   src/components/AppShellWithNav.tsx       (nav label + icon)
EDIT   src/pages/Index.tsx                      (new Avg appts/day KPI tile)
EDIT   src/components/dashboard/TechnicianBreakdown.tsx  (Appts/day row)

DELETE src/pages/SupervisorDashboard.tsx
DELETE src/hooks/use-supervisor-data.ts         (replaced by rename)
```

## Testing

**Pure-function tests** (`call-window.test.ts`):
- Each weekday Mon-Sun, offsets -2/-1/0/+1/+2.
- DST spring-forward and fall-back boundaries.
- Year-end rollover (Dec 31 Friday call covers Dec 29-30).
- Label formatting matches the spec strings.

**Wins rules tests** (`wins-rules.test.ts`):
- Top revenue tech selection with ties and zero-revenue cases.
- Highest close % filters out techs with <3 opportunities.
- Biggest avg-ticket day picks correctly across the window.
- Open-estimate callout fires at correct thresholds.

**Hook tests:**
- `use-rev-rise-data` mirrors the existing `use-supervisor-data` test pattern. Critically: paste-compare the `jobsPerDay` output against the old hook's expected fixtures to prove the rename didn't move the math (KPI immutability).
- `use-day-ahead-jobs`: tomorrow-bound query, grouping by tech, empty state.

**Component tests** (`RevRiseDashboard.test.tsx`):
- Renders with mock data per call day.
- Empty-state copy when window has no jobs.
- Prev/next arrows update window correctly.
- "Live · running now" badge shows only on call days at offset = 0.
- Day Ahead hidden when offset ≠ 0.
- AI panel collapsed by default; refetch fires on button click.

**Manual QA:**
- Open in mobile Safari at 375px width before merge: verify cards stack, KPI strip wraps cleanly, no horizontal scroll.
- Sanity-check the appts/day numbers on Index.tsx match what the old supervisor page showed for the same date range.

## Migration sequence (atomic commits)

1. Add `resolveCallWindow` + tests.
2. Rename `use-supervisor-data` → `use-rev-rise-data` (no logic change; verifies math is untouched via the immutability test).
3. Add `useDayAheadJobs` hook + tests.
4. Add `useRevRiseInsights` hook for the AI panel.
5. Build `RevRiseDashboard.tsx` + sub-components + tests.
6. Wire route `/rev-rise`, redirect `/team` → `/rev-rise`, swap nav label/icon.
7. Add Appts/day row to `TechCard` and the new company KPI tile to Index.tsx.
8. Delete `SupervisorDashboard.tsx`.
9. Manual mobile QA pass.

Each step is its own commit. Any single revert restores prior behavior. The deleted supervisor page survives in git history. The `field_supervisor` role and `view_team` permission stay in the DB.

## Open follow-ups (not v1)

- Holiday / skip-call calendar (override which dates the rolling window covers).
- Manual notes field per call (option B in brainstorming — punt for now).
- Scheduled AI commentary (precompute on call mornings).
- Removing the dormant `field_supervisor` role + `view_team` permission once confirmed unused.
