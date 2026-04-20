# Twins Dashboard — Payroll Merge, Redesign & Vercel Migration Design Spec

**Date:** 2026-04-18
**Status:** Combined 3-phase spec
**Scope:** Apply the Claude Design redesign handoff to `twins-dash`, merge the standalone `payroll-perfect` Lovable app into `twins-dash` with admin + per-user RBAC, then migrate hosting from Lovable to Vercel.

## Context

Twins Garage Doors has two working dashboard apps today:

1. **`twins-dash`** at `twinsdash.com` — the main internal dashboard on Lovable + Supabase (ref `zjjdrkbgprctsxvagcqb`). Pages: Index, Supervisor, Technician, Leaderboard, AdminPanel, CommissionReports, PartsManagement, Marketing ROI, What-If, etc. Real RBAC already in place (`user_roles` table with `role` + `permissions` JSONB).
2. **`payroll-perfect`** — a separate Lovable app on Lovable-Cloud-managed Supabase (ref `tqrrecxwdvuqbxujhslo`). Weekly payroll wizard that pulls jobs from Housecall Pro, walks the operator through parts + tip entry, applies commission rules (20/20/18 + Charles bonus + 2% override), and emits Excel. Six pages: Home, Run, Parts, Techs, History, HistoryDetail.

The operator (a single admin) wants **one** dashboard, not two. Additionally, a Claude Design handoff bundle (`Twins Garage Doors (2).zip`) has just been produced containing a navy/yellow visual refresh, a persistent `AppShell`, a 3-part prompt covering redesign + performance + deploy, and Vercel configuration (`vercel.json`, `DEPLOY.md`). The redesign + merge + hosting move should land together so the business ends up with one deploy on Vercel, consistent design, and $30–50/month savings from retiring the Lovable subscription.

## Goals

- Apply the Claude Design redesign handoff to `twins-dash`: new navy/yellow tokens, persistent `AppShell`, page-header cleanup, performance optimization (React Query, code splitting, bundle chunking, memoization).
- Copy all of `payroll-perfect`'s functionality into `twins-dash` as new `/payroll/*` routes styled with the new AppShell.
- Migrate data (techs, bonus tiers, parts, runs, jobs, commissions) from the Lovable-Cloud Supabase into the Twins-Dash Supabase.
- Gate every payroll route on `role = 'admin' OR permissions.payroll_access = true`. Admins get access automatically; non-admins get it via a per-user toggle in the existing user management page.
- Deploy the merged app to Vercel (using the already-prepared `vercel.json`), keep Supabase unchanged, cut over the `twinsgaragedoors.com` DNS, and decommission both the Lovable subscription and the `payroll-perfect` Supabase project.

## Non-goals

- No changes to the existing `twins-dash` business logic. The handoff explicitly says hooks, `lib/`, and `integrations/supabase/` are off-limits for the visual pass.
- No new RBAC roles. All access decisions ride on `role = 'admin'` + `permissions.payroll_access`.
- No refactor of the payroll-perfect logic (commission math, ownership rule, Excel writer) — it's proven and transplants directly.
- No migration of historical data outside the 7 payroll tables. Past payroll runs in payroll-perfect get migrated by row; everything else in the old Supabase project is discarded.
- No mobile-native apps. Responsive web only, per the handoff.

## Phase 0 — Apply the Claude Design redesign handoff

Implements the CLAUDE_CODE_PROMPT.md from the handoff bundle verbatim, on branch `refactor/navy-yellow-redesign`.

### 0.1 Handoff bundle placement

Unzip `/Users/daniel/Downloads/Twins Garage Doors (2).zip` into the `twins-dash` repo root. The bundle already knows where each file goes:

```
twins-dash/
├── Dashboard Redesign Preview.html     ← visual spec, stays at repo root for reference
├── vercel.json                         ← Vercel deploy config
├── DEPLOY.md                           ← Vercel + DNS steps
├── .env.example                        ← env-var template
├── src/index.css                       ← REPLACES existing (navy/yellow tokens)
├── tailwind.config.ts                  ← REPLACES existing (matching token config)
└── src/components/AppShell.tsx         ← NEW (sidebar + topbar + mobile drawer)
```

The property-management `components/` folder elsewhere in the zip is from a different project and is NOT copied.

### 0.2 Visual redesign (Part 1 of the handoff prompt)

Scope is strictly visual layer. Hooks, `lib/`, and `integrations/supabase/` are off-limits.

1. `src/App.tsx` — import `AppShell`; wrap authenticated routes (everything behind `ProtectedRoute`) so the sidebar persists. `Auth` and `NotFound` stay outside the shell.
2. In every page component (`Index.tsx`, `SupervisorDashboard.tsx`, `TechnicianView.tsx`, `Leaderboard.tsx`, `AdminPanel.tsx`, `AdminCommissionReports.tsx`, `AdminPartsManagement.tsx`, `MembershipPerformance.tsx`, `MarketingSourceROI.tsx`, `WhatIfScenario.tsx`) delete the page-level `<header>` block. Keep the `<h1>`, subtitle, and filter bar.
3. Swap primary CTA styling globally: buttons with `className="bg-primary ..."` that represent primary actions (New, Create, Submit, Save, Sync) become `className="bg-accent text-accent-foreground hover:bg-accent/90"`. Secondary buttons stay navy outline. The `text-yellow-500` trophy icons in `Leaderboard.tsx` are semantic (gold, not brand) — leave them.
4. Match spacing, typography, KPI card treatments, gauges, and tables to `Dashboard Redesign Preview.html`. Tabular numerals (`font-variant-numeric: tabular-nums`) on every numeric value. KPI label: 11px uppercase muted. KPI value: 24px 800-weight navy. Hero value: 32px.
5. Mobile responsiveness is a hard requirement (techs view this on phones):
   - KPI grid: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-6`. Hero card: `sm:col-span-2`.
   - Gauge row: `grid-cols-1 sm:grid-cols-3`.
   - Filter bar: `flex flex-wrap gap-2`; date range `w-full sm:w-auto`.
   - Tables: wrap every `<table>` in `<div className="overflow-x-auto -mx-4 md:mx-0">`.
   - Charts: `min-h-[280px]` on mobile.
   - Test at 375px (iPhone SE), 768px (iPad), 1280px (laptop). No horizontal page scroll except inside table wrappers.

Commit: `Part 1: apply navy/yellow redesign + AppShell + mobile responsive`.

### 0.3 Performance optimization (Part 2 of the handoff prompt)

Performance is the operator's #1 priority — current twins-dash is slow.

1. **React Query.** Install `@tanstack/react-query`. Wrap every Supabase hook (`use-dashboard-data`, `use-supervisor-data`, `use-technician-data`, `use-leaderboard-data`, `use-membership-data`, `use-marketing-source-roi`, `use-servicetitan-kpi`, `use-comparison-data`, `use-google-ads-data`, `use-company-goals`) in `useQuery`:
   - `staleTime: 60_000`
   - Query keys include date range + filters
   - `refetchOnWindowFocus: false`
   - Remove `setInterval` polling; replace with `refetchInterval: 30_000` ONLY on the dashboard, `refetchIntervalInBackground: false`.
2. **Code splitting.** Convert every page import in `App.tsx` to `React.lazy()` with `<Suspense>` fallback. Admin pages, Marketing ROI, What-If, Membership load on demand.
3. **Bundle chunks.** In `vite.config.ts`, add `build.rollupOptions.output.manualChunks` splitting `recharts`, `@radix-ui/*`, `date-fns`, `@supabase/supabase-js`, `xlsx` and `jspdf` (when present) into separate chunks. Main bundle target: under 200KB gzipped.
4. **Images.** Export `src/assets/twins-logo.png` as WebP; use `<picture>` with both sources in `AppShell.tsx`. Preload the WebP via `<link rel="preload">` in `index.html`.
5. **Memoization.** Every `calculate*` call in `Index.tsx` (and similar) wrapped in `useMemo` keyed on `jobs` + filters. `validateOpportunityDeduplication(jobs)` runs only in `import.meta.env.DEV`.
6. **Virtualize tables.** Technician breakdown + leaderboard tables with >20 rows → `@tanstack/react-virtual`.
7. **Memo charts.** Wrap each recharts component in `React.memo`; pass primitive-stable props.
8. **Supabase audit.** Every `.select('*')` becomes an explicit column list. Add `.limit()` where the UI shows top-N only.
9. **Fonts.** Remove Geist from `src/index.css` `@import` if present (Inter only). Append `&subset=latin` to Google Fonts URL.
10. Run `npm run build`; report bundle sizes per chunk. Anything > 250KB gzipped gets investigated.

Commit: `Part 2: performance optimization`.

### 0.4 Deploy config (Part 3 of the handoff prompt)

1. `vercel.json` and `.env.example` already at repo root from the zip.
2. `package.json` already has `"build": "vite build"` emitting to `dist/`.
3. `.npmrc` with `legacy-peer-deps=true` already committed (3861b52) so Vercel installs succeed — the existing `date-fns@4` vs `react-day-picker@8` peer conflict otherwise breaks the build.

Commit: `Part 3: deploy config`.

### 0.5 Merge Phase 0 to main

Merge `refactor/navy-yellow-redesign` → `main`. Lovable picks up the push and redeploys to `twinsdash.com`. Visually confirm the redesign in production. Phase 0 complete.

## Phase 1 — Payroll merge + RBAC

Implemented on branch `feat/payroll-merge` off the freshly redesigned `main`.

### 1.1 File structure added to twins-dash

```
twins-dash/
├── src/
│   ├── pages/payroll/
│   │   ├── Home.tsx              ← adapted from payroll-perfect
│   │   ├── Run.tsx
│   │   ├── Parts.tsx
│   │   ├── Techs.tsx
│   │   ├── History.tsx
│   │   └── HistoryDetail.tsx
│   ├── components/payroll/
│   │   ├── PayrollGuard.tsx      ← NEW: admin OR permissions.payroll_access
│   │   ├── PayrollSidebarLink.tsx ← NEW: rendered by AppShell conditionally
│   │   └── WizardStep.tsx         ← shared wizard step components
│   └── lib/payroll/
│       ├── commission.ts          ← pure math (carried over)
│       ├── ownership.ts           ← Charles rule (carried over)
│       ├── excelExport.ts         ← xlsx writer
│       └── tipExtraction.ts       ← gratuity line-item parser
├── supabase/
│   ├── functions/sync-hcp-week/
│   │   └── index.ts              ← copied from payroll-perfect
│   └── migrations/
│       └── 20260418000000_payroll_schema.sql   ← 7 tables + has_payroll_access()
└── scripts/
    └── migrate-payroll.ts        ← one-time data migration script
```

`src/pages/payroll/Setup.tsx` from payroll-perfect is NOT copied — twins-dash already has auth.

### 1.2 Database schema (migration `20260418000000_payroll_schema.sql`)

Seven new tables with the exact shapes from the prior spec (2026-04-17-lovable-dashboard-design.md):

- `techs` — roster + rates
- `bonus_tiers` — step-tier config
- `parts_prices` — cost lookup
- `runs` — weekly payroll runs
- `jobs` — normalized HCP jobs per run
- `job_parts` — parts entered per job
- `commissions` — commission ledger rows

All tables have RLS enabled. Plus one shared function:

```sql
CREATE OR REPLACE FUNCTION public.has_payroll_access(uid uuid)
RETURNS boolean
LANGUAGE sql
SECURITY DEFINER
STABLE
AS $$
  SELECT EXISTS (
    SELECT 1 FROM user_roles
    WHERE user_id = uid
      AND (role = 'admin' OR (permissions->>'payroll_access')::boolean = true)
  );
$$;
```

Every RLS policy on the 7 tables reads:

```sql
CREATE POLICY "payroll access" ON <table>
  FOR ALL TO authenticated
  USING (public.has_payroll_access(auth.uid()))
  WITH CHECK (public.has_payroll_access(auth.uid()));
```

Seed data inserted after table creation:
- `bonus_tiers`: 1 row (`step_tiers_charles`, 100/400/20/10)
- `techs`: 3 rows (Charles Rue, Maurice Williams, Nicholas Roccaforte) with the correct HCP employee IDs

### 1.3 RBAC wiring

**`<PayrollGuard>`** component in `src/components/payroll/PayrollGuard.tsx`:
- Reads `isAdmin` and `userRole.permissions` from the existing `AuthContext`.
- Computes `hasAccess = isAdmin || userRole?.permissions?.payroll_access === true`.
- If no access: redirect to `/` with a destructive toast `"You don't have access to Payroll."`.
- If access: render `{children}`.

**Sidebar link** in `AppShell.tsx`: a new "Payroll" section (under a divider) rendered only when `hasAccess`. Items: Home, Run Payroll, Parts Library, Techs & Rules, History.

**User management page** gains a new column per user: "Payroll access" checkbox. For admins it's always-on and disabled. For non-admins it toggles `user_roles.permissions.payroll_access` via an RPC:

```sql
CREATE OR REPLACE FUNCTION public.set_payroll_access(target_user_id uuid, allowed boolean)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin') THEN
    RAISE EXCEPTION 'Only admins can change payroll access';
  END IF;
  UPDATE user_roles
  SET permissions = coalesce(permissions, '{}'::jsonb) || jsonb_build_object('payroll_access', allowed),
      updated_at = now()
  WHERE user_id = target_user_id;
END;
$$;
```

(`public.has_role(uid, text)` already exists in twins-dash — we reuse it.)

### 1.4 Edge function migration

`supabase/functions/sync-hcp-week/index.ts` copied verbatim from payroll-perfect (post the tip/notes fixes that were pushed there as commits `4c5a29d`, `540918f`, `9267f6d`, `9716b19`). Deployed via the Supabase CLI to the Twins-Dash Supabase project using the stored PAT, with `--no-verify-jwt` matching its current configuration.

`HCP_API_KEY` secret is added once to twins-dash Supabase. This is the only step that requires you to paste a value into the Supabase dashboard — the Management API doesn't expose secret writes.

### 1.5 Auth adaptation inside payroll pages

Every `supabase.functions.invoke(...)` and `supabase.from(...)` call in the copied payroll pages already uses the default `supabase` client. In twins-dash, that client resolves to `src/integrations/supabase/client.ts` which is wired to the Twins-Dash project. No code change needed beyond import paths — the copies just need their import of `supabase` updated to point at twins-dash's client.

The copied pages drop their own user/setup flow (the payroll-perfect `/setup` and `/login` routes). Auth is handled at the twins-dash router level via `ProtectedRoute` + `PayrollGuard`.

### 1.6 Design adaptation

Because both apps share the shadcn/ui component library and the same HSL token names, the visual adjustments are minimal:

- Remove any literal colors from payroll code. The cream `#FFF8DC` used for the "tech notes from HCP" callout becomes a new semantic token `--notes-highlight: 48 100% 90%` defined once in `src/index.css`, referenced as `bg-[hsl(var(--notes-highlight))]` or added to `tailwind.config.ts` as `colors.notes-highlight`.
- Remove payroll's standalone page headers; the pages sit inside twins-dash's `AppShell` and show their title through the shell's topbar/breadcrumb.
- Reuse twins-dash's shared `PageHeader`, `MetricCard`, `DataTable`, and toast components. Any payroll-specific component (e.g. the HCP Line Items collapsible, the Parts autocomplete) moves to `src/components/payroll/`.

### 1.7 Data migration script (`scripts/migrate-payroll.ts`)

One-time idempotent script that copies data from `payroll-perfect` Supabase to `twins-dash` Supabase.

```typescript
// Read from: VITE_SUPABASE_URL + VITE_SUPABASE_PUBLISHABLE_KEY
//   (payroll-perfect values; already in the cloned repo's .env)
// Write to:  TWINS_DASH_SUPABASE_URL + TWINS_DASH_SUPABASE_SERVICE_ROLE_KEY
//   (from .secrets/twins-dash-supabase.env)
```

Migration order (FK-safe):
1. `bonus_tiers` → upsert by `name`
2. `techs` → upsert by `name`
3. `parts_prices` → upsert by `name`
4. `runs` → upsert by `week_start`
5. `jobs` → upsert by `(run_id, hcp_id)`
6. `job_parts` → delete-then-insert per job (no natural key)
7. `commissions` → delete-then-insert per job

The script logs a summary: `"Migrated 3 techs, 1 bonus tier, 83 parts, 1 runs, 14 jobs, 28 job_parts, 16 commissions"`.

Idempotent: re-running copies any new rows without duplicates. Run by the implementer once Phase 1 code is complete and schema is deployed.

### 1.8 Merge Phase 1 to main

1. Apply schema migration to Twins-Dash Supabase via `supabase db push` using the PAT.
2. Deploy `sync-hcp-week` edge function via `supabase functions deploy` using the PAT.
3. Paste `HCP_API_KEY` into Twins-Dash Supabase → Edge Functions → Secrets. (One manual step.)
4. Run `scripts/migrate-payroll.ts` locally to copy the data.
5. Merge `feat/payroll-merge` → `main`. Lovable redeploys.
6. Smoke-test from `twinsdash.com` as admin: Home → Run Payroll → Sync from HCP → pick any week with data → walk through a job → download Excel. Numbers should match the already-verified payroll-perfect app exactly.
7. Toggle `payroll_access = true` for one non-admin user, confirm they see the Payroll link and can access the pages; untoggle, confirm they no longer do.

## Phase 2 — Vercel migration

Uses the `vercel.json`, `DEPLOY.md`, and `.env.example` that came in the handoff bundle (placed in the repo during Phase 0).

### 2.1 Vercel project creation

User (one-time, ~5 minutes):
- Sign up at https://vercel.com with GitHub
- Create a new project, import `palpulla/twins-dash`
- Vercel auto-detects Vite; no framework preset adjustment needed
- `.npmrc` with `legacy-peer-deps=true` already committed — install succeeds

### 2.2 Environment variables on Vercel

Copy from `.env.example` into Vercel → Project Settings → Environment Variables. All three env vars are the same VITE_* values already in the Lovable deploy:

- `VITE_SUPABASE_URL` = `https://zjjdrkbgprctsxvagcqb.supabase.co`
- `VITE_SUPABASE_PUBLISHABLE_KEY` = (existing anon key from twins-dash)
- `VITE_SUPABASE_PROJECT_ID` = `zjjdrkbgprctsxvagcqb`

Scope: all (Production + Preview + Development).

### 2.3 Custom domain cutover

1. In Vercel → Project → Domains, add `twinsdash.com` and `www.twinsdash.com`.
2. Update DNS at the registrar (follow `DEPLOY.md` from the handoff bundle — typically an A record to `76.76.21.21` or CNAME to `cname.vercel-dns.com`).
3. Wait for DNS propagation (~5 min–24h depending on TTL).
4. Vercel auto-issues SSL.

### 2.4 Decommission Lovable + payroll-perfect

Once Vercel shows the site live at `twinsdash.com`:
- Cancel the Lovable subscription (save $30–50/mo per the handoff README).
- Archive the `palpulla/payroll-perfect` GitHub repo (read-only).
- Pause the Lovable-Cloud Supabase project (`tqrrecxwdvuqbxujhslo`).
- Keep the local clone of payroll-perfect indefinitely as a safety net.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Payroll migration loses data | Script is idempotent; run on a dry-run first (read-only `--dry-run` flag); compare row counts before and after; keep payroll-perfect untouched for a week. |
| Edge function `HCP_API_KEY` paste forgotten | Phase 1 step 3 is explicit; the app shows a clear "HCP_API_KEY not set" banner if missing. |
| Vercel build fails on peer deps | Already fixed by `.npmrc` (commit 3861b52). |
| DNS cutover breaks access during propagation | Keep Lovable active until Vercel is verified live; DNS TTL lowered 24h before cutover. |
| RBAC misconfiguration locks admin out | The `has_payroll_access` function is added via migration that also seeds the admin's own `payroll_access` to true as a safety net. |
| Redesign breaks visual regressions | The redesign prompt explicitly scopes itself to visual layer only; hooks/lib/integrations off-limits. Smoke test each page before merging Phase 0. |
| Performance optimization breaks hooks | React Query migration keeps the same return contract; each hook still exports the same shape. Tests/smoke per-hook. |

## Testing strategy

Unit tests exist already for payroll-perfect's commission math; those get copied into `twins-dash/src/lib/payroll/__tests__/` and run via the existing vitest config. New tests:

- `PayrollGuard` component: renders children when admin; renders children when `payroll_access=true`; redirects when neither.
- `set_payroll_access` RPC: admin can call; non-admin cannot (errors with "Only admins can change payroll access").
- `migrate-payroll.ts`: dry-run mode produces the expected row-count summary against a seed fixture.

End-to-end (manual):
- Run the Phase 1 smoke test (1.8 step 6) against a real HCP week.
- Toggle `payroll_access` on/off for a test user; confirm visibility + access changes.
- Compare Phase 1 Excel output against payroll-perfect's Excel output for the same week — must be byte-identical on summary values.

## Deliverables

1. A combined implementation plan (written via `writing-plans` skill) covering all three phases as sequenced tasks with TDD steps, commit points, and verification commands.
2. Executed implementation via subagent-driven development.
3. Post-deploy: `docs/PAYROLL_RUNBOOK.md` covering the weekly flow, troubleshooting, and re-migration instructions if ever needed.

## Open questions

None — all prior open questions (HCP field names, commission math, tip extraction, week boundaries) were pinned during the payroll-perfect real-run work and carry over unchanged.
