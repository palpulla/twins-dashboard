# Point System — Dedicated Tab, Live Graphs, FOM Rule Editing

**Date:** 2026-07-01
**Repo:** palpulla/twins-dash (Vite app, twinsdash.com)
**Status:** Design approved, pending spec review

## Problem

The Technician Accountability point system is fully live, but it is fragmented and under-surfaced:

- The per-tech points **table** lives buried as a sub-tab under `/admin/notifications` ("Tech Accountability").
- The **graphs and trends** (team trend, per-tech trend lines, ranked totals, severity pie) exist only inside the exported PDF. There is no way to *see* trends live in the dashboard.
- The **rules catalog** (point values per violation) is editable only by admin (Daniel). Charles (FOM / `field_supervisor`) cannot change point values.

Daniel wants: a **dedicated tab** for the point system that keeps track of point accumulations and shows graphs, trends, and data live; and the FOM able to modify **both** the rules catalog and each tech's points from that tab.

## Goal

Consolidate the entire point system into one dedicated, top-level page with live visualizations, and extend edit access to the FOM — without touching the underlying scoring engine, ledger, decay, digest, or ladder math.

This is a **surfacing + access** change, not an engine rewrite.

## Non-Goals

- No change to the scoring engine, `accountability_points` ledger, decay job, digest email, or Level-ladder math.
- No change to how per-tech points are proposed/committed (the review-gated add/remove flow stays exactly as-is).
- No new discipline automation. The ladder remains advisory; suspensions/termination still require Daniel.
- No change to Charles's co-tech attribution, Fri-Thu payroll weeks, revenue recognition, or any KPI math.

## Design

### 1. Navigation & page shell

- New top-level sidebar item **"Point System"** in `AppShellWithNav.tsx`, placed directly under **Leaderboard**, icon a target/gauge.
- Visibility gate: `isAdmin || isFieldSupervisor` — identical to the current Tech Accountability sub-tab.
- Route: `/admin/point-system` (with the `navSuffix` view-as pattern the other admin routes use).
- The existing **Notifications → "Tech Accountability" sub-tab is removed.** Notifications returns to pure triage. Every piece of content moves to the new page; nothing is lost or duplicated.
- Visual language follows the **main Dashboard** (per the scorecard-anchoring rule, not the payroll mirror): cards, navy `#0E2148` / yellow `#F7B801`, Inter, full dollar amounts (no `$Xk`), mobile-fits-screen.

### 2. Point System page layout

A single page `src/pages/admin/PointSystem.tsx` with light internal tabs so it does not become one long busy scroll:

**Tab A — Overview (default).** A date-range picker with presets (This week Fri–Thu, This month, Last 30 days, Custom) drives all of:

- **KPI strip** — total points this period; count of techs at/over each Level (L1–L4); most-improved (largest 30-day decay credit); highest current balance.
- **Team trend line** — points accrued over time, auto-bucketed day / Fri–Thu week / month by range length.
- **Per-tech trend lines** — each tech's cumulative balance over the range. If the roster exceeds ~8, render top-N by points and note the truncation (mirrors the known PDF busy-chart caveat).
- **Ranked totals bar** — points per tech, descending.
- **Severity pie** — minor / serious / major split.
- **Per-tech table** — the existing `AccountabilityTable` (balance, this-week Fri–Thu total, Level) underneath.
- **Export** — the existing `AccountabilityExport` (PDF + Excel) stays, top-right.

All charts read the **committed** ledger only (voided entries excluded), same predicate the export uses today.

**Tab B — Daily Review.** The existing `DailyReviewCard` review-gated flow, unchanged: Charles picks a tech-day, adds/removes mistakes, commits. Points post only on commit.

**Tab C — Rules.** The point-values catalog editor (see §3).

### 3. FOM rule editing

Give `field_supervisor` write access to the rules catalog:

- **RLS:** add an update policy on `violation_types` for `field_supervisor` (currently admin-only write). Read policy already covers both roles.
- **UI:** unhide the "Edit point values" editor (`PointValuesEditor`) for `field_supervisor` (currently gated `isAdmin`).
- **Audit safeguard (new):** because point values drive the discipline ladder, every rule change is logged.
  - Add `updated_by uuid` + `updated_at timestamptz` columns to `violation_types`, stamped on every update.
  - Add a `violation_type_history` table: `(id, violation_type_id, field_changed, old_value, new_value, changed_by, changed_at)`. The editor writes a history row per changed field on save. Read access: admin + field_supervisor. This gives a reversible trail without blocking Charles.

Note: this lets Charles directly recalibrate `inform_arrival` (the OMW auto-point that currently dominates the ledger and can push techs toward L3 on a habit gap) — the previously-open calibration item is now self-serve.

### 4. Reuse map (what already exists)

- `useAccountabilityReportData` — already aggregates trend/ranking/severity data for the PDF; drives the live charts.
- `pdf-report.ts` / `report.ts` — chart-data logic extracted/shared into live Recharts components (new `AccountabilityCharts.tsx`).
- `AccountabilityTable`, `DailyReviewCard`, `PointValuesEditor`, `AccountabilityExport` — moved onto the new page as-is.
- `useAccountability`, `useTechDayReview`, `useCommitDayReview` — unchanged.
- Recharts is already a dependency (used by the PDF via off-screen render); live charts use it directly on-screen.

### 5. New / changed files

**New**
- `src/pages/admin/PointSystem.tsx` — the dedicated page + internal tabs.
- `src/components/accountability/AccountabilityCharts.tsx` — live Recharts (team trend, per-tech trends, ranked bar, severity pie) + KPI strip.
- Migration: RLS update policy on `violation_types` for `field_supervisor`; `updated_by`/`updated_at` columns; `violation_type_history` table + RLS.

**Changed**
- `src/components/AppShellWithNav.tsx` — new nav item; remove no nav item (Notifications stays).
- `src/App.tsx` — new route `/admin/point-system`.
- `src/pages/admin/Notifications.tsx` — remove the "Tech Accountability" sub-tab; Notifications is triage-only.
- `src/components/accountability/PointValuesEditor.tsx` — allow `field_supervisor`; write history rows on save.
- `src/components/accountability/TechAccountabilityTab.tsx` — retired/absorbed into `PointSystem.tsx` (or repointed).

### 6. Data flow

1. FOM/admin opens `/admin/point-system`.
2. Overview tab: `useAccountabilityReportData(range)` returns aggregated committed-ledger series → Recharts components + `AccountabilityTable`.
3. Daily Review tab: unchanged prefill → review → commit path writes to `accountability_points` (review-gated).
4. Rules tab: `PointValuesEditor` reads/writes `violation_types`; each save also inserts `violation_type_history` rows and stamps `updated_by/updated_at`. RLS permits admin + field_supervisor.

### 7. Access control summary

| Capability | admin | field_supervisor | other |
|---|---|---|---|
| View Point System page | ✅ | ✅ | ❌ |
| Adjust per-tech points (review) | ✅ | ✅ | ❌ |
| Edit rules catalog / point values | ✅ | ✅ (new) | ❌ |
| View rule-change history | ✅ | ✅ | ❌ |

### 8. Testing

- Engine/report aggregation: existing tests in `src/lib/accountability/__tests__` stay green; add a test that live chart-data selectors match the PDF's series for a fixed fixture.
- RLS: verify `field_supervisor` can update `violation_types` and insert `violation_type_history`; verify non-privileged roles still cannot.
- `tsc` + build clean; existing deno render tests green.
- UI smoke: nav item shows for both roles, hidden otherwise; Notifications no longer shows the sub-tab; charts render with a non-empty range.

### 9. Rollout

- Migration applied to jwrpj via Supabase MCP `apply_migration` (CLI `db push` blocked by migration-history desync; manually record the version row after apply).
- Ship behind the existing role gate; auto-deploys from `main` via Vercel.
- Reversible: nav item + page are additive; rule-edit access is one RLS policy + one UI gate that can be reverted; the engine is untouched.
