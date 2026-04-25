# Tech Portal Redesign Design Spec

**Date:** 2026-04-25
**Status:** Design approved, ready for implementation plan
**Scope:** Replace the current `/tech/*` experience in `twins-dash` with a focused 3-tab portal (Home · Goals & Coaching · Recognition) anchored to the navy-and-yellow visual language of [Index.tsx](../../../twins-dash/src/pages/Index.tsx). Adds an admin-tunable tier ladder, a weekly AI coaching nudge (Claude Haiku), personal-only streaks and personal records, and a `/admin/scorecard-tiers` page. Replaces today's TechHome and TechnicianView pages. The Paystubs surface and PastPaystubs component are deleted because payroll lives in an external provider; the dashboard's job is to calculate the current week's commission and help techs improve.

## Context

Today the tech-facing surface is split awkwardly across two pages:

- [twins-dash/src/pages/tech/TechHome.tsx](../../../twins-dash/src/pages/tech/TechHome.tsx) — a 101-line "single screen" with hero CTA, estimated paystub, last paystub, and supervisor team override. Reached at `/tech` via TechShell.
- [twins-dash/src/pages/TechnicianView.tsx](../../../twins-dash/src/pages/TechnicianView.tsx) — a 421-line page that mixes admin pre-pick, mobile tech view, and admin post-pick scorecard with raw inline CSS (`.ts-scope`). Reached at `/tech/:id`.

Plus four already-retired pages still in the tree (TechAppointments, TechEstimates, TechProfile, TechJobs) and a TechShell whose nav has only one item ("Home").

The split causes three real problems:

1. **Two parallel "tech home" experiences** that show overlapping information in different visual languages. Daniel rejected the current scorecard rewrite once already (see [feedback_scorecard_leads_from_index.md](../../../.claude/projects/-Users-daniel-twins-dashboard/memory/feedback_scorecard_leads_from_index.md)) for using payroll-tab table-heavy patterns instead of the main dashboard's MetricCard rhythm.
2. **No coaching layer.** Techs see what their numbers are versus the company average but get no guidance on what to do about it. Daniel's brief: "show every important detail and how to get it better."
3. **No motivational layer.** No streaks, no records, no celebration of improvement. The portal is purely diagnostic.

The redesign consolidates and rebuilds around three goals: **calculate this week's commission accurately** (Home), **help techs improve** (Goals & Coaching), and **celebrate their progress** (Recognition). All three are valuable to both the tech (clarity, growth, recognition) and the company (cleaner payroll data, better KPIs, retention).

## Goals

- One focused 3-tab portal at `/tech/*`, mobile-first, consistent with desktop. Tabs: **Home · Goals · Wins**.
- Visual language anchored to [Index.tsx](../../../twins-dash/src/pages/Index.tsx) (navy + yellow, MetricCard pattern, rounded-2xl cards, generous whitespace, no dense tables). The raw `.ts-scope` CSS layer is replaced with Tailwind/shadcn matching the main dashboard.
- Bronze / Silver / Gold / Elite tier ladder per KPI. Thresholds set by admin in a new `/admin/scorecard-tiers` page; per-tech overrides supported.
- Weekly AI coaching nudge per active tech, generated Mondays via cron, written by Claude Haiku 4.5, costing roughly $0.20/month total.
- Personal-only Recognition: streaks, personal records, tier-up moments, year ribbon. No leaderboards, no rank, no negative comparisons.
- Reuse all existing KPI math, hooks, and commission/parts entry flows untouched. The `kpi-calculations.ts` byte-for-byte rule still applies.
- Same shell serves admin "View as tech" mode using the existing impersonation pattern (`/tech?as=<id>`).

## Non-goals

- No paystubs view. Daniel runs payroll through an external provider; the dashboard does not need to show finalized historical paystubs. A single inline "Last finalized week landed at $X" line on Home is the only retrospective surface.
- No leaderboards or competitive ranking. Recognition is personal-progress only.
- No new KPIs. Stays at the current 8: revenue, total jobs, avg opportunity, avg repair, avg install, closing %, callback %, membership %.
- No changes to commission math, ServiceTitan-style KPI definitions, parts entry flow, low-stock email alerts, payroll run wizard, or HCP webhook ingestion.
- No notifications/inbox tab. Tech-facing alerts (mod request answers, callback flags, low-priced parts) continue to surface as toasts and in admin-side queues; not a tab in this scope.
- No schedule/appointments tab. Techs use HCP for daily appointment view.
- No mobile push notifications, native app shell changes, or Capacitor updates.
- No AI generation on demand from the tech UI. AI runs weekly on cron only; manual regeneration is admin-side at `/admin/scorecard-tiers`.

## Information architecture

Three tabs surfaced via TechShell bottom-nav (mobile) and left sidebar (desktop). The short label is what shows on the nav; the long name is the in-page header:

| Nav label | Page header | Route | Purpose |
|---|---|---|---|
| **Home** | Home | `/tech` | Calculate this week's commission · see all 8 KPI tiles · enter parts on draft jobs |
| **Goals** | Goals & Coaching | `/tech/goals` | AI weekly nudge · tier ladder rollup · what changed this week · tip library |
| **Wins** | Recognition | `/tech/wins` | Active streaks · personal records · tier-up moments · year ribbon |

Admin "View as tech" (`/tech?as=<id>`) renders the same three tabs identically, with a small "Exit preview" affordance. Admin scorecard with tech picker continues at `/tech/:id` (renders the Home tab content for any picked tech, plus the existing Tech Requests sidebar).

## Tab 1: Home

Order top-to-bottom on mobile, two-column on desktop where it helps:

1. **Hero estimate card.** Navy gradient with yellow radial accent. Displays:
   - Greeting and tech first name
   - Week range (e.g. "This week · Apr 21 – Apr 27")
   - Estimated commission, large, with cents at smaller weight
   - Three mini stats: Jobs done, Drafts left (orange when > 0), Avg ticket
   - Footer: "Submit by Sunday 11:59 PM"
2. **Yellow CTA card** (only when drafts exist). "2 jobs need parts entered. Estimate could be off by ~$240 until these are entered." Tapping routes to the first draft job's parts entry screen.
3. **Action row.** Two buttons: outlined "Sync HCP" (calls existing `useForceRefresh`) and primary "Submit week" (existing submit flow).
4. **Performance section.** Header "PERFORMANCE" with a date range picker pill (default: Last 30 days; options: Last 30, Last 90, This month, YTD). Below: 8 KPI tiles in a 2-column grid (mobile) or 4-column grid (desktop). Each tile shows the KPI name, tier badge (Bronze/Silver/Gold/Elite), value, comparison pill vs company average (+/- pts or %), thin progress bar to the next tier, and a one-line "X to Gold" caption. Tapping a tile opens a drill-in sheet (see below).
5. **This week's jobs.** A list of the current week's completed jobs. Each row: HCP job number, customer name, status pill (Draft / Submitted / Locked), commission amount (or "— pending" for drafts), and a meta line with job type, address, day, and status detail. Tapping a row routes to `/tech/jobs/:id` for parts entry.
6. **Sanity line.** A grey pill at the bottom: "📌 Last finalized week: Apr 14 – Apr 20 landed at $1,640.00. You can sanity-check your math against this."

### KPI drill-in sheet

Bottom sheet on mobile, inline expansion on desktop. Triggered by tapping a KPI tile. Contents:

- KPI name and current value with tier badge
- Versus company average (large delta number)
- Stat rows: Your number (last 30d), Company average, Drivers (e.g. "Memberships sold: 5 of 34 jobs"), Best week of 2026
- Tier ladder: 4 chips for Bronze/Silver/Gold/Elite with the tech's current position outlined
- Sparkline: last 8 weeks of this KPI with a dashed company-average line
- AI nudge tip slot: pulls the relevant bullet from this week's `tech_ai_nudges.bullets` if the AI nudge focused on this KPI; otherwise pulls the matching tip-library card

The drill-in sheet is the same component used in the Goals tab tier ladder rows, just with different framing.

## Tab 2: Goals & Coaching

1. **AI nudge hero.** Yellow gradient card. Header: "🤖 AI nudge · Week of Apr 21 — refreshed Mon 6:00 AM". Headline (one sentence, e.g. "This week, focus on Memberships."), lede (one sentence framing why), then 3 numbered bullets each citing a real number from the tech's data (e.g. "3 of your last 5 callbacks were on openers older than 10 years"). Footer: "Based on N jobs · date range · Powered by Claude Haiku".
2. **Tier ladder rollup.** Header "Tier ladder · all KPIs". One row per KPI: KPI name on the left, a wide progress bar with 4 tier markers in the middle showing the tech's current position with the appropriate gradient fill, the current tier badge on the right, and a caption below the bar (e.g. "$48k of $55k for Elite · $7k to go"). Caps at 6 visible KPIs with "+ 2 more on Home" footer.
3. **What changed this week.** A 2-up grid of small cards. Each: an up/down arrow chip, a sentence ("Closing % +4 pts (from 34%)"). Computed from the same KPI math, comparing the current 7-day window to the prior 7-day window.
4. **Tip library.** A 2-up grid of static tip cards. Each card: KPI label, tip headline, and a one-sentence preview. Tapping opens the full tip content (a markdown body stored in a small `coaching_tips` table or hardcoded JSON; we'll go with hardcoded JSON in this spec to avoid needing yet another admin surface).

### AI nudge mechanics

- Runs as a Supabase pg_cron job every Monday at 11:00 UTC (6:00 AM Madison local; CT is UTC-5 in spring/summer). Job name: `generate_weekly_ai_nudges`.
- For each active tech (`technicians.is_active = true`), the cron job calls the `generate-tech-nudge` Edge Function. If the tech had fewer than 5 jobs in the prior 7-day window, the cron job skips them; the Goals tab shows a static tip-library card instead.
- The Edge Function builds an Anthropic API call:
  - Model: `claude-haiku-4-5-20251001`
  - System block (cached): the schema, output rules, voice ("warm coach, not a manager"), and the explicit constraint "only cite numbers that appear in the user message; do not invent percentages or counts".
  - User block: the tech's first name, week range, their 8 KPIs with values, the company averages for the same window, the tier thresholds (and per-tech overrides), and the lowest-tier KPI with its specific job-level drivers (e.g. "Membership offers: 2 of 14 jobs", "Avg time on site: 38 min", "Callback ages: 3 openers older than 10 years").
  - Output format: JSON with `headline` (one sentence), `lede` (one sentence), `bullets` (array of exactly 3 strings).
- Validates the response (length caps, presence of all fields, no markdown). If invalid, retries once; if still invalid, falls back to a tip-library card and writes a row with `bullets = null`.
- Upserts into `tech_ai_nudges` keyed on `(tech_id, week_start)`. Stores `model`, `cost_usd`, `jobs_in_window`.
- Anthropic API key stored as Edge Function secret `ANTHROPIC_API_KEY`. Never client-side.

Manual regeneration is exposed via a "Regenerate now (force)" button on `/admin/scorecard-tiers`. Admin can also "Pause AI nudges" which sets a feature-flag row in `app_settings` consulted by the cron job.

## Tab 3: Recognition

1. **Year ribbon.** Navy gradient hero. "2026 · Year so far" label, "[Tech]'s year" headline, three stats in a row: YTD revenue (with on-pace projection), YTD jobs (with avg ticket), Memberships sold (with year-over-year delta).
2. **Tier-up celebration.** Yellow card, only when the tech has crossed a tier threshold in the last 30 days. Shows the icon (🎉), the kind ("New tier · 6 days ago"), the headline ("You hit Gold on Avg Ticket"), and a one-sentence frame including the next tier ("Up from Silver three months ago. Keep it up and you'll hit Elite at $1,500."). Pinned for 30 days from the crossing date, then auto-dismissed.
3. **Active streaks.** Header "Active streaks". 2-up grid of streak cards. Each: an emoji icon (🔥 weeks-above-avg, ⭐ tier-held, 📈 revenue-floor, 🎯 zero-callbacks), the count ("4 weeks"), what ("above company avg on Closing %"), and since ("since week of Mar 24").
4. **Personal records.** Header "Personal records". 2-up grid of PR tiles. Each: KPI label, value, and context ("week of Mar 17"). PRs broken in the last 7 days get a yellow background and a "🆕" prefix on the label.
5. **Empty state for brand-new techs.** Shown when the tech has fewer than 4 weeks of history. A 🌱 icon, "Your wins will show up here as you build them", and an explainer line.

## Admin controls

### `/admin/scorecard-tiers` (new page)

A single new page in the admin shell. Three sections:

1. **Tier thresholds table.** One row per KPI (8 rows). Columns: KPI name with unit pill (e.g. "$ / 30d"), Bronze input, Silver input, Gold input, Elite input, Direction badge (↑ Higher better / ↓ Lower better). Inputs use the existing form styles. Save button writes to `scorecard_tier_thresholds`; reset-to-defaults button restores the seed values. View change log link opens a modal listing the audit table entries.
2. **AI nudge controls.** Read-only stats grid: Schedule, Model, Last run (with skip count), Cost this month / YTD. Action buttons: Regenerate now (force), Pause AI nudges, View prompt template.
3. **Per-tech tier override panel.** Not part of `/admin/scorecard-tiers` directly; it opens from the Goals tab when admin is in "View as tech" mode (a small "Override thresholds for this tech" link in the page header). The panel lists all 8 KPIs with toggle switches; an "Active" toggle reveals an inline number input that overrides the company default for that tier. Saves to `tech_tier_overrides`.

All admin actions gated by the existing `has_payroll_access()` RPC pattern.

### Reused admin surfaces (no change)

- `/admin/users` — adds nothing. Tech portal access is already gated by role.
- `/admin/tech-requests` — unchanged. Mod requests and parts price flags continue to land here.
- `TechPicker` and the "View as tech" impersonation flow — unchanged.

## Data model

### New tables (6)

#### `scorecard_tier_thresholds`

| Column | Type | Notes |
|---|---|---|
| `kpi_key` | TEXT PK | One of: `revenue`, `total_jobs`, `avg_opportunity`, `avg_repair`, `avg_install`, `closing_pct`, `callback_pct`, `membership_pct` |
| `bronze` | NUMERIC | Threshold to reach Bronze tier |
| `silver` | NUMERIC | Threshold to reach Silver tier |
| `gold` | NUMERIC | Threshold to reach Gold tier |
| `elite` | NUMERIC | Threshold to reach Elite tier |
| `direction` | TEXT | `higher` or `lower` (callbacks are lower-better) |
| `updated_at` | TIMESTAMPTZ | |
| `updated_by` | UUID | FK to auth.users |

Seeded via migration with the defaults shown in the admin mock (revenue $20k/$35k/$45k/$55k, closing 25/35/45/55, callbacks 5.0/3.5/2.5/1.5, etc.).

#### `tech_tier_overrides`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID PK | |
| `tech_id` | UUID FK | references `technicians(id)` |
| `kpi_key` | TEXT FK | references `scorecard_tier_thresholds(kpi_key)` |
| `bronze` | NUMERIC NULL | NULL = use company default |
| `silver` | NUMERIC NULL | |
| `gold` | NUMERIC NULL | |
| `elite` | NUMERIC NULL | |
| `updated_at` | TIMESTAMPTZ | |
| `updated_by` | UUID | |

UNIQUE on `(tech_id, kpi_key)`.

#### `tech_ai_nudges`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID PK | |
| `tech_id` | UUID FK | |
| `week_start` | DATE | Monday of the week the nudge covers |
| `headline` | TEXT | One sentence |
| `lede` | TEXT | One sentence |
| `bullets` | JSONB | Array of exactly 3 strings; NULL if generation failed |
| `jobs_in_window` | INT | Number of jobs Claude saw |
| `model` | TEXT | e.g. `claude-haiku-4-5-20251001` |
| `cost_usd` | NUMERIC | Recorded for cost tracking |
| `created_at` | TIMESTAMPTZ | |

UNIQUE on `(tech_id, week_start)`.

#### `tech_streaks`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID PK | |
| `tech_id` | UUID FK | |
| `kind` | TEXT | One of: `above_avg`, `tier_held`, `no_callbacks`, `rev_floor` |
| `kpi_key` | TEXT NULL | The KPI this streak is about (NULL for `no_callbacks`/`rev_floor`) |
| `count` | INT | Number of weeks or months |
| `unit` | TEXT | `week` or `month` |
| `since_period` | DATE | First period of the streak |
| `active` | BOOLEAN | False once the streak breaks; row preserved for "longest streak" PR |
| `updated_at` | TIMESTAMPTZ | |

Recomputed nightly. Active rows shown in Recognition tab; inactive rows feed into the "longest streak" PR computation.

#### `tech_personal_records`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID PK | |
| `tech_id` | UUID FK | |
| `kpi_key` | TEXT | |
| `period` | TEXT | `week`, `month`, or `year` |
| `value` | NUMERIC | The record value |
| `achieved_at` | DATE | Period start |
| `is_fresh` | BOOLEAN | True if set in the last 7 days; UI highlights these yellow |
| `updated_at` | TIMESTAMPTZ | |

UNIQUE on `(tech_id, kpi_key, period)`. Recomputed nightly; only the best value per (tech, KPI, period) is kept.

#### `scorecard_tier_threshold_audit`

| Column | Type | Notes |
|---|---|---|
| `id` | UUID PK | |
| `kpi_key` | TEXT | |
| `snapshot` | JSONB | Full prior row |
| `changed_at` | TIMESTAMPTZ | |
| `changed_by` | UUID | |

Trigger on UPDATE of `scorecard_tier_thresholds` writes the prior row here. Admin "View change log" link reads from this.

### RLS policies

| Table | SELECT | WRITE |
|---|---|---|
| `scorecard_tier_thresholds` | All authenticated | Admin only |
| `tech_tier_overrides` | Own row (tech) + all (admin) | Admin only |
| `tech_ai_nudges` | Own row (tech) + all (admin/manager) | Service role only |
| `tech_streaks` | Own row (tech) + all (admin/manager) | Service role only |
| `tech_personal_records` | Own row (tech) + all (admin/manager) | Service role only |
| `scorecard_tier_threshold_audit` | Admin only | Trigger only |

Follows the existing `has_payroll_access()` and per-tech `auth.jwt() ->> 'tech_id'` patterns already used by `useMyScorecard` and `useMyPaystub`.

### Cron jobs

| Schedule | Job | What |
|---|---|---|
| Mon 11:00 UTC | `generate_weekly_ai_nudges` | For each active tech with ≥5 jobs last week, invoke `generate-tech-nudge` Edge Function |
| Daily 07:00 UTC | `compute_streaks_and_prs` | Pure SQL function. Scans last 26 weeks per tech, recomputes `tech_streaks` and `tech_personal_records`. Sets `is_fresh = true` on PRs broken in the last 7 days. |

Both registered via SQL migration following the existing pg_cron pattern (see the low-stock-email cron from 2026-04-22 commits).

### Edge Functions

#### `generate-tech-nudge` (new)

- Trigger: cron job (looped per tech) and admin manual button.
- Input: `{ tech_id: UUID, week_start: DATE }`.
- Calls Anthropic API per the AI nudge mechanics section above.
- Writes one row to `tech_ai_nudges`. Returns `{ ok: true, nudge_id, cost_usd }` or `{ ok: false, reason }`.
- Verify-JWT enabled; admin-only via `has_payroll_access()` check at the function entry. Cron uses service-role key.

#### `force-refresh-hcp` (existing, no change)

Powers the "Sync HCP" button on Home. Already wired via [twins-dash/src/hooks/tech/useForceRefresh.ts](../../../twins-dash/src/hooks/tech/useForceRefresh.ts).

### SQL helpers

- `get_my_tier(kpi TEXT, value NUMERIC)` → returns `bronze | silver | gold | elite | null`. Consults per-tech override first, then company default.
- `get_my_scorecard_with_tiers(date_range JSONB)` → existing scorecard payload plus a `tier` field per KPI and a `gap_to_next` field.
- Read hooks: `useTierThresholds`, `useMyAiNudge`, `useMyStreaks`, `useMyPersonalRecords`, `useTechTierOverrides(tech_id)` — all standard `useQuery` wrappers around supabase calls.

## Frontend refactor

### Keep (no change)

- `src/lib/kpi-calculations.ts` — math is sacred per CLAUDE.md.
- Hooks: `useTechnicianData`, `useServiceTitanKPI`, `useMyScorecard`, `useMyPaystub`, `useLastFinalizedPaystub`, `useForceRefresh`.
- Components: `CommissionTracker`, `JobRow`, `JobStatusBadge`, `PartsPickerModal`, `RequestPartAddModal`, `SubmitConfirmModal`, `ProtectedRoute`, `RequireTechnician`, `TechPicker`, the entire `/tech/jobs/:id` parts-entry flow.
- Admin `/admin/tech-requests` — unchanged.
- `AppShell.tsx`, `AppShellWithNav.tsx`, `NavLink.tsx`, all UI primitives.

### Add (new files)

- Pages: `pages/tech/Home.tsx`, `pages/tech/Goals.tsx`, `pages/tech/Recognition.tsx`, `pages/admin/ScorecardTiers.tsx`.
- Components: `components/tech/HeroEstimate.tsx`, `KpiTile.tsx`, `KpiDrillSheet.tsx`, `AiNudgeCard.tsx`, `TierLadderRow.tsx`, `WhatChangedGrid.tsx`, `TipLibrary.tsx`, `StreakCard.tsx`, `PersonalRecordTile.tsx`, `YearRibbon.tsx`, `TierUpCard.tsx`, `TierBadge.tsx`.
- Components: `components/admin/TierThresholdsTable.tsx`, `TechTierOverridePanel.tsx`, `AiNudgeControls.tsx`.
- Hooks: `useTierThresholds`, `useMyAiNudge`, `useMyStreaks`, `useMyPersonalRecords`, `useTechTierOverrides`, `useTierUpMoments`.
- Edge Function: `supabase/functions/generate-tech-nudge/index.ts`.
- Migrations: `supabase/migrations/<ts>_scorecard_tier_thresholds.sql`, `<ts>_tech_tier_overrides.sql`, `<ts>_tech_ai_nudges.sql`, `<ts>_tech_streaks.sql`, `<ts>_tech_personal_records.sql`, `<ts>_scorecard_tier_threshold_audit.sql`, `<ts>_get_my_tier_function.sql`, `<ts>_compute_streaks_and_prs_function.sql`, `<ts>_pg_cron_ai_nudges.sql`, `<ts>_pg_cron_streaks_prs.sql`.
- Routes: TechShell `NAV_ITEMS` array goes from 1 entry to 3.
- Static data: `src/data/coaching-tips.ts` — the static tip-library content (one tip per KPI per tier gap, hardcoded JSON to avoid an admin surface).

### Remove

- `pages/tech/TechHome.tsx` (consolidated into Home).
- `pages/TechnicianView.tsx` (split into the 3 new tab pages plus the `/tech/:id` admin scorecard which renders the new Home content for any picked tech).
- Already-retired pages: `pages/tech/TechAppointments.tsx`, `TechEstimates.tsx`, `TechProfile.tsx`, `TechJobs.tsx`. Finalize their removal so the tree matches the code comment in TechShell.tsx.
- `components/technician/scorecard/PastPaystubs.tsx`.
- `components/tech/PaystubCard.tsx` (replaced by HeroEstimate).
- The entire `.ts-scope` raw-CSS layer baked into TechnicianView. Replaced with Tailwind/shadcn matching Index.tsx.

The existing scorecard atoms in `components/technician/scorecard/` (TechScorecardKPIs, CommissionTracker, WeekSummary, ComparisonPill, DisclaimerBanner, ModificationRequestDialog, atoms.tsx) are evaluated case-by-case during implementation: anything that fits the new visual language (e.g. ComparisonPill, ModificationRequestDialog) is kept; anything that doesn't (e.g. TechScorecardKPIs which uses the old `.ts-scope` styling) is replaced by the new tile/drill components.

### Routing changes

In `App.tsx` (or wherever the tech routes live):

- `/tech` → `Home.tsx` (was TechHome, then routed via TechShell)
- `/tech/goals` → `Goals.tsx` (new)
- `/tech/wins` → `Recognition.tsx` (new)
- `/tech/jobs/:id` → unchanged, parts entry
- `/tech/:id` → admin-side scorecard (renders Home's content for the picked tech)
- `/tech?as=<id>` → impersonation, lands on `/tech` (Home) for the impersonated tech
- `/admin/scorecard-tiers` → `ScorecardTiers.tsx` (new)

`TechShell.NAV_ITEMS` becomes:

```ts
const NAV_ITEMS = [
  { to: '/tech',       label: 'Home',  icon: Home,   exact: true  },
  { to: '/tech/goals', label: 'Goals', icon: Target, exact: false },
  { to: '/tech/wins',  label: 'Wins',  icon: Award,  exact: false },
];
```

## Visual language anchor

Per [feedback_scorecard_leads_from_index.md](../../../.claude/projects/-Users-daniel-twins-dashboard/memory/feedback_scorecard_leads_from_index.md), every new component anchors to [Index.tsx](../../../twins-dash/src/pages/Index.tsx):

- Navy `text-primary` for headings, yellow `text-accent` for emphasis chips.
- Rounded-2xl cards, `border-border`, generous padding (`p-4`/`p-5`).
- MetricCard pattern: small uppercase label, large value, comparison pill, optional sparkline.
- Mobile-first 2-column tile grids, 4-column on desktop.
- No dense tables in the tech-facing UI. Tables are reserved for admin (the threshold editor).
- Date range picker reuses the existing `DateRangePicker` component.

The payroll-tab `border-accent/40` hero-action-card / MiniStat pattern is **not** used in the tech portal. That pattern is reserved for payroll workflow pages per [feedback_tech_view_mirrors_payroll.md](../../../.claude/projects/-Users-daniel-twins-dashboard/memory/feedback_tech_view_mirrors_payroll.md) (the rule there applies to admin payroll workflow sub-pages, not scorecards).

## Cost and operational footprint

- **AI nudges:** ~$0.012 per tech per week × 4 active techs × 4.3 weeks ≈ **$0.20/month**. YTD with current team: under $3/year.
- **DB storage:** 6 small tables, all scoped per-tech-per-week. Negligible.
- **Edge Function invocations:** ~17/month (4 techs × 4.3 Mondays + occasional manual regen). Within Supabase free tier.
- **Cron overhead:** Two pg_cron jobs. The nightly streak/PR job runs in pure SQL against existing data; expected runtime under 5 seconds for the current team size.

## Open questions

These were considered and resolved during brainstorming, captured here so the implementation plan does not relitigate them:

- **Why no Paystubs tab?** Daniel runs payroll through an external provider. The dashboard's job is current-week commission calculation, not historical pay records.
- **Why personal-only Recognition?** Wholesome by design. Daniel explicitly chose "personal streaks only" over leaderboards to avoid demotivating the bottom of the pack.
- **Why Claude Haiku vs static tips vs hybrid?** Daniel chose AI-generated weekly nudge over the cheaper hybrid option. Cost is rounding error; magic is high.
- **Why tier ladder vs raw "vs company avg"?** Daniel chose admin-tunable tier ladder over the simpler "company avg = goal" pattern. Gives him control over what "good" means without per-tech maintenance.
- **What if a tech has no AI nudge yet (new, vacation, low job count)?** The Goals tab shows a tip-library card in the AI nudge slot. The drill-in sheets fall back to static tips on the relevant KPI.
- **What about the existing Modification Request Dialog?** Kept. Surfaces from the per-job entry screen, not the new tabs. Admin queue at `/admin/tech-requests` continues to receive submissions.
