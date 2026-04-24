# Tech Scorecard Overhaul — Design Spec

**Date:** 2026-04-24
**Scope:** `twins-dash/` repo, page currently at `/tech/:id` (`TechnicianView.tsx`).
**Goal:** Replace today's admin-only tech scorecard with a unified, role-aware scorecard used by techs (primary, mobile-first) and admin/supervisors (read-only drill-down with approval controls). Visual language anchored to `Index.tsx` so the dashboard feels consistent end to end.

## Why

Two problems today:

1. The existing `TechnicianView.tsx` uses a different visual language than the main dashboard and includes charts (Commission by Week, Commission by Ticket Size) that add noise without informing action.
2. There is no tech-facing commission tracker. The weekly payroll flow (`/payroll/run`) is admin-only, so techs cannot enter parts on their own jobs. Every missed part turns into a Daniel-has-to-chase-it task.

This overhaul gives techs the self-service surface to enter parts during the week, locks the week on a single "Finish Week" action, and routes any post-lock changes through a supervisor approval queue. Parts costs stay hidden from techs at all times.

## Non-goals (out of scope for v1)

- Photo upload on custom parts.
- Push or SMS notifications to supervisors when a request lands.
- Bulk modification requests (one request = one job).
- Trend or time-series charts on the scorecard. Trends live on the main dashboard.
- Replacing the admin payroll `/payroll/run` flow. That keeps existing behavior; this spec only adds the tech-facing entry and the approval queue that feeds into it.
- Any change to commission math, rules, or the KPI calculation functions already in `kpi-calculations.ts`.

## Audience and routing

- `/tech/:id` serves both techs and admin/supervisors, role-aware.
- Tech hitting `/tech` (no id) redirects to `/tech/<their-technician-id>`.
- Tech hitting `/tech/<someone-else>` redirects to `/tech/<their-technician-id>`.
- Admin or supervisor hitting any `/tech/:id` sees the scorecard in read-only mode with an "Open Tech Requests" button if that tech has anything open.
- The existing `/tech/*` self-service sub-app (`TechHome`, `TechJobs`, `TechJobDetail`, `TechAppointments`, `TechEstimates`, `TechProfile`) stays in place. Where its purpose overlaps the new scorecard (KPI-like tiles on `TechHome`, per-job parts entry on `TechJobDetail`), the sub-app routes surface through the new scorecard rather than being rewritten: the scorecard becomes the hub and the sub-app pages are reachable as drill-ins.

## Visual language (follows `Index.tsx`)

- Page header: `text-2xl font-bold text-primary` h1 with a muted-foreground subtitle. Right side holds `DateRangePicker` and a "Sync jobs" button when viewed by the tech themselves. Stacks on mobile.
- Content organized in cards with the `rounded-2xl border bg-card` pattern used on `Index.tsx`. No nested sidebars, no dense tables on mobile.
- Section rhythm: every major block (KPIs, Tracker, Paystubs) is a single card with a clear title and optional subtitle.
- Metric tiles reuse `MetricCard` from `@/components/dashboard/MetricCard`. Each tile shows icon, value, label, InfoTip, and a "vs company avg" pill.
- Mobile first. Everything legible at 375px wide. Multi-column grids collapse to 2-up then 1-up. Tables convert to stacked cards under `md:`.
- Typography and spacing tokens match `Index.tsx` so nothing feels bolted on.

## Page sections (top to bottom)

### 1. Header and sync bar

- H1: "Your scorecard" when viewed by the tech, "{Tech name} scorecard" when viewed by admin.
- Subtitle: short sentence with the selected date range and "viewing as {role}".
- DateRangePicker (default: YTD, same default as `Index.tsx`).
- "Sync jobs" button (tech view only). Pulls fresh jobs from the HCP webhook backlog via the existing `auto-sync-jobs` edge function.

### 2. Persistent disclaimer banner

Amber `Alert` card, always visible, not dismissable for the session:

> These numbers are estimates and may shift. Final amounts are confirmed when admin runs payroll. Parts you have not entered will skew the math.

Same wording in tech view and admin view.

### 3. KPI Scorecard (8 tiles)

Grid: 4 columns on desktop, 2 columns on tablet, 1 column on mobile portrait, 2 columns on mobile landscape. Ordered so the most-read KPIs (Revenue, Avg Opp, Closing %) show first on mobile.

| # | Tile | Source calc | InfoTip copy |
|---|------|-------------|--------------|
| 1 | Revenue (period) | sum of tech's paid jobs in date range | "Total revenue from your completed jobs in this period." |
| 2 | Jobs (period) | count of tech's jobs in date range | "Number of jobs you completed in this period." |
| 3 | Avg Opportunity | `calculateOpportunityJobAverage` scoped to tech | "Average ticket on jobs where you had a chance to sell. Measures how well you close when given a chance." |
| 4 | Avg Repair | `calculateAvgRepairTicket` scoped to tech | "Typical size of your service calls." |
| 5 | Avg Install | `calculateAvgInstallTicket` scoped to tech | "Typical size of your install jobs." |
| 6 | Closing % | `calculateClosingPercentage` scoped to tech | "How often you turn an opportunity into a sale." |
| 7 | Callback Rate | `calculateCallbackRate` scoped to tech | "Percent of your jobs that needed a return visit. Lower is better." |
| 8 | Membership Conversion % | `calculateMembershipConversionRate` scoped to tech | "How often you add a membership to a service call." |

Each tile shows a "vs company avg" pill with:
- green up-arrow if better than company average,
- red down-arrow if worse,
- neutral gray dash if within the "near average" tolerance.

Tolerance rules (to keep the pill from flickering on small variance):
- For percent KPIs (Closing %, Callback Rate, Membership Conversion %): neutral if within 2 percentage points.
- For dollar KPIs (Revenue, Avg Opp, Avg Repair, Avg Install): neutral if within 5% of company average.
- For count KPIs (Jobs): neutral if within 5% of company average.

For KPIs where lower is better (Callback Rate), the arrow logic inverts.

Comparison source: reuse `useServiceTitanKPI(dateRange)` already present on the page.

### 4. Commission Tracker

The centerpiece. Drives the weekly loop.

#### Default state

- Shows the current payroll week (Friday through Thursday, same boundary as `Run.tsx`).
- A prominent "Week of {Fri date} to {Thu date}" header with week navigation arrows (tech can view prior weeks read-only, cannot edit finalized weeks).
- Jobs list for the selected week. Each job row shows: job number, customer, job date, ticket amount, and a parts status badge.
- Parts status values: `Not entered`, `Entered`, `Pending price`, `Ready`.

Desktop: table. Mobile: stacked cards with the same fields.

#### Per-job parts entry

Tap/click a job to open a drawer (mobile: full-screen sheet, desktop: right-side `Sheet`).

- Part picker: search the pricebook by name. No cost column is rendered anywhere in this sheet. Tech sees only part name and quantity input.
- Added parts display as chips with name and quantity. Tap a chip to edit quantity or remove.
- "Add custom part" button: opens a small form with `name`, `quantity`, `notes` text field. Submits as a `pending_price` entry. Shown inline with a "Pending price" tag so the tech knows admin needs to fill price before the week can lock.
- "Mark job ready" button. Transitions job state from `Entered` to `Ready`. Tech can re-open and re-edit while the week is still unlocked.
- "Skip this job" button with a required reason text (examples: warranty-only, no parts used, estimate declined). Skipped jobs count as `Ready` for the week-lock check.

#### During the week

- No commission total shown anywhere on the page. No effective rate. No net subtotal. The tech's screen is about parts-status and job-readiness only.
- A progress line at the top of the tracker card: "{X} of {Y} jobs ready." Mobile-friendly single line.

#### "Finish Week" action

- Button sits inside the tracker card, primary style, disabled until:
  - all jobs in the week have state `Ready` or explicitly `Skipped`, and
  - there are zero `pending_price` custom parts on any job in the week.
- If any custom parts are still pending, the button shows "Waiting on pricing for {N} parts" and is disabled.
- Clicking "Finish Week" opens a confirmation dialog that spells out the finality: "Finishing this week locks your entries. To change anything after this point, submit a modification request and wait for supervisor approval." Confirm or cancel.
- On confirm: server writes a `tech_week_locks` row with the commission snapshot. Tracker re-renders as the Week Summary (next section).

#### Week Summary (post-lock, tech view)

- Replaces the job-entry table with a read-only summary:
  - Headline: "You earned ${total} this week." That is the only dollar value at the card level.
  - Per-job rows: job number, customer, and the tech's commission dollars for that job. No cost. No effective rate. No net subtotal.
  - Each row has a "Request modification" button.
- A "View prior weeks" link opens the paystubs list.

#### Admin view of tracker

- Same layout. All data is read-only.
- If the tech has pending tech requests tied to this week, an inline banner: "{N} open requests for this tech. Open queue."
- An extra button "Open in payroll view" that deep-links to the corresponding week in `/payroll/run`. This is the bridge between this page and the existing admin payroll flow.

### 5. Modification request (per-job, post-lock)

Opens a dialog from any locked job row.

- Form fields:
  - Reason checkboxes (multi-select): `Forgot parts`, `Wrong part`, `Wrong quantity`, `Wrong job tag`, `Customer dispute`, `Other`.
  - Details: required text area. Placeholder "Describe exactly what needs to change. For example: forgot 1x 25SSWDE on Job 12345."
  - Submit button.
- On submit: creates a `tech_requests` row with `type = modification`, status `open`. Tech sees a confirmation toast and the job row gets a "Request pending" badge.
- Tech cannot submit a second request on the same job while one is open.

### 6. Past paystubs list

- Shows the last 4 finalized weeks under the tracker.
- Each row: week range, total commission, job count, status (`Finalized` or `Locked, awaiting payroll`). Tap to drill in read-only.
- Data source: reuse the existing `useLastFinalizedPaystub` pattern extended to return multiple weeks, or a new `useMyPaystubs` hook. Implementation detail deferred to the plan.

## `/admin/tech-requests` queue page

New admin page. Linked from the sidebar Admin section as "Tech Requests" with a count badge showing open requests.

### Layout

- Header: "Tech Requests" with count of open items.
- Filter row: status (`Open`, `Resolved`, `All`), technician, type (`Price needed`, `Modification`).
- List of request cards, newest first.

### Request types rendered inline

**Price needed:**
- Tech name, job number, custom part name, quantity, tech notes.
- Inline input: "Unit price" + "Mark priced" button.
- Saving sets the part to `priced`, and if that was the last pending custom part on a week-ready job and tech already hit Finish, the week-lock proceeds automatically. (Edge case: if tech hit Finish while custom parts were still pending, the tracker blocks until priced. Prefer blocking Finish at submission time to keep semantics simple.)

**Modification:**
- Tech name, job number, week range, reason checkboxes rendered as chips, tech notes in full.
- "Open in payroll editor" button: deep-links to the job in `/payroll/run` for that week. Admin applies the change there, then returns and clicks "Mark resolved" with a short resolution note.
- Resolution note is required. Stored on the request and visible to the tech.

### After resolve

Tech gets a subtle notification on the scorecard: a dismissable green banner "Your modification request for Job {N} was applied. See details." Links to the resolution note.

## Data model additions

All tables in the twins-dash Supabase project (project id: `jwrpj*`, per `reference_external_systems.md`).

### `tech_part_entries`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, pk | |
| `technician_id` | uuid, fk to `technicians.id` | |
| `job_id` | bigint, fk to jobs table | |
| `week_start` | date | Friday anchor |
| `part_id` | bigint, fk to `parts`, nullable | null for custom parts |
| `custom_part_name` | text, nullable | required when `part_id` is null |
| `quantity` | numeric | |
| `unit_price` | numeric, nullable | admin fills for custom parts |
| `status` | text enum | `entered`, `pending_price`, `priced`, `applied` |
| `notes` | text, nullable | tech or admin notes |
| `created_at` | timestamptz | |
| `finalized_at` | timestamptz, nullable | set when week locks |

### `tech_week_locks`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, pk | |
| `technician_id` | uuid | |
| `week_start` | date | Friday anchor |
| `week_end` | date | Thursday |
| `locked_at` | timestamptz | |
| `locked_by` | uuid | user id of locker (tech or admin) |
| `total_commission` | numeric | snapshot at lock time |
| `commission_by_job` | jsonb | array of `{job_id, amount}` |
| `notes` | text, nullable | admin override note if lock was forced |

Unique constraint on (`technician_id`, `week_start`).

### `tech_requests`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, pk | |
| `technician_id` | uuid | |
| `job_id` | bigint, nullable | null ok for generic tech-level requests; current v1 always has job_id |
| `week_start` | date | for grouping |
| `type` | text enum | `price`, `modification` |
| `reasons` | text[] | for modification, empty for price |
| `notes` | text | tech-provided detail |
| `status` | text enum | `open`, `resolved`, `rejected` |
| `resolved_by` | uuid, nullable | admin who resolved |
| `resolved_at` | timestamptz, nullable | |
| `resolution_notes` | text, nullable | |
| `unit_price` | numeric, nullable | for price-type resolutions |
| `created_at` | timestamptz | |

### RLS

- `tech_part_entries`: tech can select/insert/update/delete own rows where week is not locked. Admin/supervisor full access.
- `tech_week_locks`: tech can insert own lock, can select own rows. Admin/supervisor full access (admin can re-lock after applying a modification).
- `tech_requests`: tech can insert and select own rows. Admin/supervisor full access and are the only role that can resolve.

## Permissions

- Existing `view_technician` permission continues to gate admin-style access.
- New permissions (add to role model):
  - `manage_tech_requests`: required to resolve requests on `/admin/tech-requests`. Granted to admin and field supervisor roles.
- Tech role requires no new permission beyond what already lets them load their own data.

## Integration points

- Commission math: reuse `computeCommissions` from `src/lib/payroll/commission.ts`. At week-lock, server calls this with the tech's parts to produce the snapshot. No new math.
- Parts library: reuse `parts` table used by `/payroll/parts` and `/payroll/run`. Tech sees name-only projection, achieved through a view or through the select list in the UI (no RLS relaxation on parts).
- HCP sync: "Sync jobs" button reuses `auto-sync-jobs` edge function already called from `Index.tsx`.
- Admin payroll flow: `/admin/tech-requests` deep-links into `/payroll/run` for modification fulfillment. `/payroll/run` itself is not modified.

## Mobile considerations (primary surface)

- Every interactive element at least 44px tall.
- Sheets and drawers use full-screen on `sm:` breakpoint. No side drawers on phones.
- Sticky "Finish Week" button at the bottom of the tracker on mobile so the tech does not have to scroll past the job list to act.
- No table horizontal scroll on mobile. All table rows collapse to stacked cards.
- Amber disclaimer uses a compact two-line variant on `sm:` to save vertical space.
- DateRangePicker opens as a full-screen modal on `sm:`.

## Error handling

- Sync job failures show a toast and leave the existing job list untouched.
- Part save failures leave the draft in local state with a red "Retry" chip on the failed row.
- Lock week failures (e.g., network): tracker stays in ready-to-lock state, toast with "Could not finish week, try again." No partial state written server-side (wrap in a single RPC call).
- Request submission failures: form keeps its contents, toast with retry prompt.

## Testing plan (outline; detailed steps land in the implementation plan)

- Unit: `tech_part_entries` RLS, week-lock RPC transactionality, commission snapshot shape.
- Component: KPI tiles render correct values for a fixture set of jobs; "vs company avg" pill colors correctly.
- Flow: tech enters parts, marks ready, hits Finish Week, sees Week Summary. Repeat with a custom part in pending state and verify Finish is blocked.
- Flow: tech submits a modification request, admin resolves via `/admin/tech-requests`, tech sees confirmation banner.
- Mobile visual: 375px, 390px, 414px widths. Scorecard and tracker both readable, sticky action works.

## Appendix A — Claude Design prompt

Use this prompt in Claude Design to produce the visual polish pass. It assumes Claude Design already has the `Index.tsx` dashboard as its reference because that design came from there.

> Design a mobile-first tech scorecard page for Twins Garage Doors that follows the exact visual language of the existing main dashboard (the one with SemiGauge, MetricCard, ComparisonSection, RevenueTrendChart, DateRangePicker).
>
> Page name: Your scorecard. Subtitle shows the selected date range and role.
>
> Sections in order:
> 1. Header with title, subtitle, DateRangePicker on the right, and a small Sync jobs button. Everything stacks on mobile.
> 2. An amber disclaimer banner that reads: These numbers are estimates and may shift. Final amounts are confirmed when admin runs payroll. Parts you have not entered will skew the math.
> 3. A KPI grid with 8 tiles: Revenue, Jobs, Avg Opportunity, Avg Repair, Avg Install, Closing %, Callback Rate, Membership Conversion %. Each tile has icon, value, label, a small information icon, and a pill that compares the tech's value to the company average (green up, red down, gray dash within 2 points). Grid is 4 columns on desktop, 2 on tablet, 1 on small mobile, 2 on landscape mobile.
> 4. A Commission Tracker card with a "Week of {Fri} to {Thu}" header and left/right arrows. Inside: a one-line progress indicator "{X} of {Y} jobs ready" and a list of jobs. Each job row shows number, customer, date, ticket amount, and a status badge (Not entered, Entered, Pending price, Ready). On mobile the rows collapse to stacked cards. Large primary Finish Week button at the bottom, sticky on mobile.
> 5. After Finish Week, the tracker card replaces its content with a Week Summary: a single big headline "You earned ${amount} this week." Below it, a read-only list of jobs each showing job number, customer, and the tech's commission dollars only. Each row has a Request modification button.
> 6. A Past paystubs list showing the last 4 finalized weeks, each row tap-able.
>
> Drawers and dialogs:
> - Per-job parts entry drawer: search field for pricebook parts, chips for added parts with quantity inputs, an Add custom part button that opens a small form with name, quantity, notes. Absolutely no price, cost, subtotal, or commission shown inside this drawer.
> - Modification request dialog: checkbox group of reasons (Forgot parts, Wrong part, Wrong quantity, Wrong job tag, Customer dispute, Other) and a required details text area.
>
> Constraints:
> - Mobile first. All controls at least 44px tall. No horizontal table scroll.
> - Never render any dollar value related to parts cost or commission during the week. Commission numbers are only visible in Week Summary and paystubs.
> - Match Index.tsx tokens: rounded-2xl cards, muted-foreground subtitles, primary color for headers, generous whitespace.
> - No charts. No bar graphs. No line graphs on this page.
>
> Also design the supervisor page at /admin/tech-requests: a filter bar (status, technician, type), then a list of request cards. Price-needed cards have an inline Unit price input and Mark priced button. Modification cards show reasons as chips and have an Open in payroll editor button plus a Mark resolved button with a required resolution note.

## Appendix B — File impact (for the plan)

Rough set of files the implementation plan will need to touch. Not exhaustive.

- `src/pages/TechnicianView.tsx` — rewrite as the unified scorecard.
- `src/pages/tech/TechHome.tsx`, `TechJobDetail.tsx`, `TechJobs.tsx` — decide redirect or absorb. Exact decision deferred to plan.
- New: `src/pages/admin/TechRequests.tsx`.
- New components: `TechScorecardKPIs`, `CommissionTracker`, `WeekSummary`, `PartsEntryDrawer`, `ModificationRequestDialog`, `TechRequestCard`.
- Hooks: extend or add `useMyPaystub`, `useTechWeekTracker`, `useTechRequests`.
- Supabase: migrations for `tech_part_entries`, `tech_week_locks`, `tech_requests`, plus RLS policies.
- Edge function (optional): `finish-tech-week` to atomically lock, snapshot, and return the summary.
- Routing: update `src/App.tsx` with auto-redirect rules for `/tech` and `/tech/:id`.
- Sidebar: add "Tech Requests" under Admin with count badge.

## Open questions for the implementation plan

- Exact payroll week boundary function to import (`lastPayrollWeekStart` in `Run.tsx` is private; promote to a shared util).
- Whether to absorb the `/tech/*` sub-app into the new scorecard or keep it as drill-in routes. Leaning keep-as-drill-in to minimize churn.
- Unit test coverage strategy for RLS policies (pgTAP vs Vitest with service role).
- Whether admin can force-lock a tech's week on the tech's behalf if the tech is on vacation (not in v1, flag for future).
