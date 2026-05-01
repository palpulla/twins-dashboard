# Tech Dashboard — Payroll-Tab Mirror Redesign

**Date:** 2026-04-23
**Project:** Twins Dashboard (palpulla/twins-dash)
**Supersedes (visually, not functionally):** portions of `2026-04-23-technician-self-service-dashboard-design.md`
**Status:** Approved for planning

## 1. Context

The first pass of the tech self-service view (branch `feature/tech-dashboard-phase-1-infra`, 19 commits) built correct data plumbing (schema, RLS, views, Edge Functions, commission recompute, Team Override) but produced a visually inconsistent UI that Daniel rejected on preview: "this sucks, absolutely not useful."

Two concrete problems surfaced:

1. **Commission structure wasn't visible.** The scorecard showed only a scalar commission total, not the primary / override / tip breakdown that makes the number interpretable.
2. **Visual language felt decoupled from `/payroll/*`.** The admin Payroll tab (which Daniel built and likes) uses a specific rhythm — page header, yellow-accent hero action card, `MiniStat` grid, quick-link cards — and the tech view ignored it.

The data layer is correct and stays. This redesign replaces only the three tech pages (`TechHome`, `TechJobs`, `TechJobDetail`) and a pair of components (`ScorecardHero`, `SecondaryMetrics`) to visually mirror `src/pages/payroll/Home.tsx`.

## 2. Goals

- Tech view feels like an extension of the admin Payroll tab, not a separate app.
- Commission structure is immediately visible as a labeled paystub breakdown (revenue, rate, commission, override, tip, total).
- Landing page surfaces a single primary action: enter parts for the first unsubmitted job.
- Prior-week paystub is shown alongside this-week's provisional for comparison.
- Charles's Team Override card becomes a visible component on his Home, not an optional add-on.
- Per-job commission is still never shown (aggregate-only rule stays).

## 3. Non-goals

- No changes to DB schema except one new function (`my_paystub`).
- No changes to RLS, Edge Functions, commission math, or the pricebook lookup path.
- No changes to `TechShell`, `RequireTechnician`, modal components (`PartsPickerModal`, `SubmitConfirmModal`, `RequestPartAddModal`), utilities, hooks not listed as "Add" below.
- No new routes. Existing `/tech`, `/tech/jobs`, `/tech/jobs/:id`, `/tech/appointments`, `/tech/estimates`, `/tech/profile` stay.
- No Charles-specific flow differences beyond the Paystub card's override line and the existing `TeamOverrideCard`.

## 4. Visual reference — admin PayrollHome patterns

Primitives to copy verbatim:

| Element | Pattern |
|---|---|
| Page header | `<h1 className="text-2xl font-bold text-primary">` + `<p className="text-sm text-muted-foreground">` subtitle |
| Hero action card | `<Card className="border-accent/40">` + inner flex row: left-side title/description, right-side yellow CTA `<Button className="bg-accent text-accent-foreground hover:bg-accent/90">` |
| Stats grid | `<Card>` with `<CardHeader><CardTitle className="text-base">` then `<CardContent className="grid gap-4 md:grid-cols-N">` filled with `MiniStat` |
| `MiniStat` component | `<div className="text-[11px] uppercase text-muted-foreground">{label}</div>` + `<div className="text-xl font-bold tabular-nums">{value}</div>` |
| Row card | `<Card className="transition hover:border-primary/40">` with compact inner padding |

These patterns appear in `src/pages/payroll/Home.tsx:46-122`. The new tech pages adopt them 1:1.

## 5. TechHome layout

Stacked Cards, top to bottom:

### 5.1 Page header

```
Hey, {technicianName}
Your jobs, parts, and weekly earnings.
```

Same classes as admin's "Payroll" h1 + subtitle.

### 5.2 Hero action card

Two states, flipped on whether the tech has unsubmitted draft jobs this week.

**When draft jobs exist:**
- Yellow-accent border.
- Left: "Enter parts for {N} job{s}" heading + subtitle "Still need parts entered this week. Tap to start with the next one."
- Right: yellow CTA button `Enter Parts` → navigates to `/tech/jobs/{next_draft_job_id}`.

**When caught up:**
- Softer styling (green tint, `border-primary/30` or similar).
- Left: "You're caught up" heading + subtitle "All {N} of {N} jobs submitted this week."
- Right: outline button `View this week's jobs` → `/tech/jobs?status=submitted`.

**When no jobs synced yet:**
- Muted styling (default border).
- Left: "No jobs this week yet" + subtitle "Pull the latest from HouseCall Pro if you expect to see jobs."
- Right: `Refresh from HCP` button that fires `useForceRefresh`.

Single card, three variants; the Home page decides which variant based on `my_paystub` counts and `next_draft_job_id`.

### 5.3 "Your estimated paystub · this week (provisional)" card

- Card header: `<CardTitle>` = "Your estimated paystub". Sub-caption on the right: "This week · provisional".
- Card body: structured rows (label on left, value on right, right-aligned tabular-nums):
  - Revenue worked
  - Commission rate (from `payroll_techs.commission_pct`, displayed as `XX%`)
  - Commission earned
  - Team override (Charles only; hidden otherwise)
  - Tip
  - Divider
  - Estimated total (bold)
- Footer disclaimer copy (verbatim, same as prior spec): "Estimate. Final amounts confirmed when admin runs payroll. Pricebook-only; custom parts require admin review. Take with a grain of salt."

### 5.4 "Your last paystub · {week_range} (finalized)" card

Same structure as 5.3 but:
- Card header: "Your last paystub" + right caption "Apr 14–20 · finalized".
- Values come from the most recent `payroll_runs.status='final'` that includes the tech.
- No "provisional" disclaimer — instead, a short note: "Locked on {locked_at}." (or "Finalized" when `locked_at` isn't set because the run was marked final before the lock column existed).
- If no finalized run yet exists for this tech, hide the card entirely.

### 5.5 Team Override card (Charles only)

Reuse the existing `TeamOverrideCard` component exactly as built. Rendered only when `isFieldSupervisor` is true.

## 6. TechJobs layout

### 6.1 Page header

```
Your jobs
Your work across HouseCall Pro, grouped by payroll week.
```

### 6.2 Filter row

Horizontal row: `TimeframePicker` (Today / Week / Month / Custom) on the left, `StatusFilter` chips (All / Draft / Submitted / Locked) on the right. Both exist today; no rebuild.

### 6.3 Per-week Card sections

Each week is a standalone Card with:
- `CardHeader`: "Week of {Apr 14}" + right-side caption showing run status ("in progress" or "finalized Apr 21").
- `CardContent`: list of `JobRow` rows with subtle divider between them.

`JobRow` visual tweaks:
- Revenue amount uses the `MiniStat`-style typography (`text-xl font-bold tabular-nums`) rather than inline text.
- Status badge + "Adjusted" badge sit on the right edge, consistent with admin row treatment.

### 6.4 Empty state

One full-width Card, muted content: "No jobs in this period. Hit Refresh on Home to sync from HouseCall Pro."

## 7. TechJobDetail layout

Three stacked Cards, preceded by a back link.

### 7.1 Back link

`← Back to jobs` at top; simple text button.

### 7.2 Job card (hero)

- Top row: `<h2>` customer display name + `JobStatusBadge`.
- Sub-line: "Date · Job #####".
- Detail grid (using `MiniStat` pattern): `Revenue` | `Work type` (from `description`) | optional empty cells to fill 3-col on md+.
- Bottom row: "Open in HouseCall Pro" outline button (existing deep link).
- Conditional banner above the grid when `commission_admin_adjusted`: blue-tinted "Admin reviewed & adjusted — numbers may differ from what you last entered."

### 7.3 "From HouseCall Pro" notes card

- Renders only when `notes_text` or `line_items_text` from `payroll_jobs` is non-empty.
- `CardHeader`: "From HouseCall Pro".
- `CardContent`: if `line_items_text` present, render it under a small "Line items" label; if `notes_text` present, render under "Notes". Both in monospace or muted regular text, preserving line breaks.

This is the same view admin sees during the Run wizard's per-job step — giving the tech the same context. It's also the source text the smart-guard scans.

### 7.4 Parts card

- `CardHeader`: "Parts used" on the left, `Add Part` button (outline) on the right (draft + unlocked only).
- `CardContent`:
  - List of parts: `part_name` on the left, `Qty N` + remove icon on the right (remove icon only for `entered_by='tech'` in draft + unlocked).
  - If no parts: muted line "No parts entered yet."
  - Smart-guard warning banner inline when applicable: amber-tinted "This job's HCP notes mention {categories} but you haven't entered any. Did you use any?"
- `CardFooter`:
  - **Draft + unlocked:** primary yellow CTA `Submit parts for this job` (opens `SubmitConfirmModal`).
  - **Submitted:** muted line "Submitted on {submitted_at}." + small link "Request a correction" (toasts "Contact Daniel" for now; not wiring correction flow in this redesign).
  - **Locked:** muted line "Locked · this is final." Read-only.

### 7.5 What's deliberately missing

No per-job commission display anywhere. Tech can see revenue, parts count, and status, but never "this job earned me $X." Protects the aggregate-only rule against price inference.

## 8. Data additions

### 8.1 `my_paystub(p_since timestamptz, p_until timestamptz)` Postgres function

Returns one row:
- `revenue numeric` — sum of `payroll_jobs.amount` for caller in range
- `primary_commission numeric` — sum of `payroll_commissions.commission_amt` where `kind='primary'`
- `override_commission numeric` — sum where `kind='override'`
- `tip_total numeric` — sum of `tip_amt`
- `commission_total numeric` — primary + override + tip
- `commission_pct numeric` — the tech's current `payroll_techs.commission_pct` (scalar)
- `job_count bigint`
- `draft_count bigint`
- `submitted_count bigint`
- `locked_count bigint`
- `next_draft_job_id int | null` — earliest `payroll_jobs.id` in range with `submission_status='draft'`, or NULL

SECURITY INVOKER so RLS applies. The function replaces the current `my_scorecard` usage on Home; `my_scorecard` can stay for other consumers or be deprecated later.

### 8.2 Hooks

- `useMyPaystub(timeframe)` — calls `my_paystub`, returns typed result.
- `useLastFinalizedPaystub()` — queries `payroll_runs` for the most recent `status='final'` run where `EXISTS(jobs WHERE owner_tech = current_technician_name())`, then calls `my_paystub` bounded to that run's `week_start` / `week_end + 1 day`. Returns `{ weekStart, weekEnd, lockedAt, paystub }` or `null`.

### 8.3 New components

- `HeroActionCard.tsx` — one component, three states (see 5.2). Takes `{ draftCount, totalCount, nextDraftJobId, isForceRefreshing, onForceRefresh }`.
- `PaystubCard.tsx` — labeled breakdown. Takes `{ title, caption, paystub, isSupervisor, disclaimer? }`. Used by both this-week and last-week cards.
- `PayrollWeekSection.tsx` — wraps a set of JobRows in a Card with a week header. Takes `{ weekStart, weekEnd, runStatus, jobs }`.

## 9. Salvage list (explicit)

**Keep unchanged:**
- Every hook in `src/hooks/tech/` except the net-new `useMyPaystub` / `useLastFinalizedPaystub`.
- Every Edge Function (`submit-job-parts`, `sync-my-hcp-jobs`, `request-part-add`).
- Every migration through `20260423180355_add_job_part_rpc.sql`.
- RLS policies, views, triggers.
- `TechShell`, `RequireTechnician`, `TimeframePicker`, `StatusFilter`, `JobStatusBadge`, `PartsPickerModal`, `SubmitConfirmModal`, `RequestPartAddModal`, `TeamOverrideCard`.
- `TechProfile`, `TechAppointments`, `TechEstimates`.
- Utilities: `smart-guard`, `timeframe`, `format`, `part-category`.

**Replace:**
- `src/pages/tech/TechHome.tsx`
- `src/pages/tech/TechJobs.tsx`
- `src/pages/tech/TechJobDetail.tsx`

**Delete:**
- `src/components/tech/ScorecardHero.tsx`
- `src/components/tech/SecondaryMetrics.tsx`
- `src/components/tech/SyncStatusBar.tsx` (the Refresh action moves into the "No jobs" variant of the hero card)

**Add:**
- `src/components/tech/HeroActionCard.tsx`
- `src/components/tech/PaystubCard.tsx`
- `src/components/tech/PayrollWeekSection.tsx`
- `src/hooks/tech/useMyPaystub.ts`
- `src/hooks/tech/useLastFinalizedPaystub.ts`
- `supabase/migrations/<ts>_my_paystub_fn.sql`

**Modify lightly:**
- `src/components/tech/JobRow.tsx` — revenue uses `MiniStat`-style typography, otherwise unchanged.

## 10. Testing

- Existing unit tests (`timeframe`, `smart-guard`) remain green — no code-path changes.
- One new unit-testable boundary: `computeHeroActionState(paystub)` → returns `'enter_parts' | 'caught_up' | 'no_jobs'`. Pure function, 3-4 tests covering the transitions.
- `my_paystub` function: smoke test via Supabase CLI `db query` after migration — verify it returns the expected shape on a no-data range (all zeros) and a known-data range (Maurice's recent week, values match `payroll_commissions` aggregation).
- Visual QA: Daniel previews as Maurice (already linked) after deploy. Charles variant QA deferred until Charles is invited.

## 11. Rollout

1. Single branch off current `feature/tech-dashboard-phase-1-infra`: add the migration + new hooks + new components + rewritten pages. Delete the superseded components.
2. Commit as a focused set (migration → hooks → components → pages → cleanup).
3. Push. The existing draft PR updates in place; Vercel rebuilds.
4. Daniel previews as Maurice. If approved, he merges and invites all three techs.
5. If rejected, we iterate on this branch before merge — no prod impact until merge.

Reversibility holds: every commit is individually revertable, the new migration only adds a function (droppable), and the deleted component files can be restored from git history.

## 12. Out of scope (deferred to future phases)

- Charles "Request correction" flow (Submit-on-behalf-of-tech by admin) — TODO in Phase 3 along with admin lock/reopen functions.
- Appointments / Estimates tabs (no HCP sync tables exist yet).
- Admin Payroll sub-tabs (Activity / Part Requests queue / Techs mgmt UI / Audit log) — separately scoped in the original spec's Phase 1.5.
- End-to-end RLS tests with real tech sessions — gated on Maurice being invited.
