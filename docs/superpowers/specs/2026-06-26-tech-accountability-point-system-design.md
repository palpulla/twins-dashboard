# Tech Accountability Point System — Design

**Date:** 2026-06-26
**Owner:** Daniel (CEO, approves), Charles (FOM, drives in field)
**Status:** Approved design, pending implementation plan
**Source of truth for the point/escalation model:** `Twins_Garage_Doors_Field_Sales_Implementation_Plan.pdf` (Sections 10, 9, 12). The ladder, thresholds, and categories come **only** from that document. Do not invent point values or thresholds.

## Purpose

The `daily-supervisor-digest` report (built, currently disabled) already scans completed HCP jobs for ticket mistakes and emails an FOM/CEO digest. This design ties that report to the PDF's accountability model: count mistakes as points per tech, surface the PDF escalation ladder as a suggestion, let the FOM log the conversations and disciplinary actions he takes, and show it all as an aggregate on the dashboard.

Nothing here disciplines anyone automatically. The system **counts, suggests, and records**. Suspensions and termination remain human decisions with Daniel's sign-off, exactly as the PDF assigns ownership.

## The PDF model being implemented

From Section 10 (Mistake Tracking and Accountability):

**Four mistake categories:**
1. `sales_process` — no 3 options, did not present highest-to-lowest, skipped membership, did not call before presenting when required.
2. `hcp_process` — did not dispatch/start/finish, missing photos, missing forms, wrong materials, no payment status.
3. `operational` — returned to shop for parts that should have been loaded, ignored job notes, missed special-order items.
4. `customer_experience` — no gratitude, no time check, did not ask decision-maker question, no review ask.

**Escalation ladder (count-based, per the PDF):**
| Trigger | PDF action | Owner |
|---|---|---|
| 1-2 mistakes/day | Track only | Charles |
| 3+ mistakes/day | Same-day conversation | Charles |
| Repeated same issue | Written warning | Charles, Daniel aware |
| Continued issue | 1-day suspension | Daniel approval |
| Continued after suspension | 3-day suspension | Daniel approval |
| Still unresolved | Termination | Daniel |

Nathan's "2 mistakes/day before a manager conversation" is the starting line.

**Out of scope (this iteration):** Recall rate (Section 9) is not folded into the point count. Can be added later.

## Decisions locked in brainstorming

- **What counts:** Auto-detected HCP misses (already built) **plus** Charles manually logging the other 3 categories from job checkout.
- **Scoring:** 1 point per mistake, flat. Thresholds taken verbatim from the PDF.
- **Notification:** Daily digest email to Charles + a dashboard flag. No real-time pings.
- **Ladder behavior:** System suggests the level and flags it; Charles records the action actually taken; suspensions/termination always human-approved.
- **Time windows:** today, rolling 7-day, and the dashboard's selected period. "Repeated same issue" = same category 2+ times in 7 days (and history-aware escalation beyond that — see §3).
- **Placement:** New "Tech Accountability" tab on the existing `/admin/notifications` page.

## Architecture

### 1. Point ledger — extend `supervisor_alerts`

Reuse the existing alerts table as the single point ledger rather than build a parallel store. New/changed columns:

- `category text` — one of `sales_process | hcp_process | operational | customer_experience`. Backfill: existing `missing_buttons` and `missing_notes` rows → `hcp_process`.
- `source text` — `auto` (found by the report) or `manual` (logged by Charles). Backfill existing rows → `auto`.
- `points int not null default 1` — always 1 today; explicit so the math is visible and future-proof.
- `job_id` made **nullable** — manual mistakes need not tie to an HCP job.
- `occurred_on date` — the day the mistake counts against (for auto rows, derived from job completion; for manual rows, chosen by Charles, defaults today). Drives the per-day count.

Existing columns kept: `attributed_tech_id` (auto rows use the Charles co-tech rule; manual rows set it directly), `details`, `digest_date`, `resolved_at`, `resolved_by`, `created_at`. Every row stays individually resolvable/editable.

**Charles co-tech attribution rule is load-bearing and unchanged:** Charles alone on a ticket = his; Charles + any other tech = the other tech's.

### 2. Manual mistake entry (Charles, mobile-first)

A "Log mistake" action available from the FOM tab (and a quick entry point usable on a phone): pick tech → pick category → optional link to a job → one-line note. Writes a `source='manual'` row with `points=1`, `occurred_on` defaulting to today. This is the only path by which the 3 non-detectable categories ever get counted.

### 3. Scoring + escalation engine

A SQL view (e.g. `v_tech_accountability`) and/or RPC computes, per active tech:
- points today, rolling 7-day, and over the dashboard-selected period;
- breakdown by the 4 categories;
- a **suggested level** derived from the PDF ladder and the logged-action history:
  - 0-2 today → *Track only*
  - 3+ today → *Same-day conversation*
  - same category 2+ in 7 days → *Written-warning candidate*
  - the same issue recurs after a `conversation` was already logged → escalate one rung (warning);
  - recurs after a `written_warning` logged → *1-day suspension candidate (Daniel approval)*;
  - recurs after a suspension → *3-day suspension*, then *termination candidate*.

The suggested level is advisory only. It is computed from counts + the `accountability_actions` history so it reflects where a tech actually sits on the ladder, not just raw daily counts.

### 4. Talks + disciplinary log — new `accountability_actions` table

Columns: `id`, `technician_id`, `action_type` (`coaching_note | conversation | written_warning | suspension_1day | suspension_3day | termination`), `occurred_on date`, `notes text` (the talk summary — Daniel's "capture talks"), `created_by`, `suggested_level_at_time text`, `created_at`. Optionally `related_alert_ids` to link the talk to the specific mistakes that triggered it.

This is the record of what Charles/Daniel actually did — the "summary of what disciplinary actions were done."

### 5. Notifications

- **Daily digest email** (the existing one to Charles, +Daniel): add an **Accountability section** grouped by tech — today's points, who hit 3+ (conversation needed), repeated-issue flags, and current suggested level. Rides the existing send path; the digest stays gated/disabled until go-live.
- **Dashboard flag:** badge on the notifications bell/tab when any tech is at "conversation needed" or above.

No real-time pings, consistent with "build accountability as silent observability, don't ping me."

### 6. FOM aggregate — "Tech Accountability" tab on `/admin/notifications`

Per-tech table, worst-first:

| Tech | Today | 7-day | Period | By category | Suggested level | Last talk | Open actions |

- Category column shows pills per the 4 categories with counts.
- Row expands to: the individual mistakes (auto + manual, each with date/job/note) and the full talk/discipline log (dates + notes).
- Row actions: **Log talk**, **Log discipline**, **Log mistake**.
- Period selector reuses the dashboard's existing selector.

### 7. Access control

`/admin/notifications` is admin-gated today. The new tab is gated to admin **plus a supervisor/FOM permission**, and Charles's account is wired to that permission. The implementation plan must verify Charles's current login/role and the access-control model (roles + RLS + permissions JSON) before exposing the tab.

## Components and boundaries

- **Migration** — `supervisor_alerts` column additions + backfill; new `accountability_actions` table; RLS for both (admin + FOM read; FOM insert for manual mistakes and actions). Manual INSERT into `schema_migrations` after apply (migration-history desync is known on this DB).
- **`daily-supervisor-digest` edge function** — set `category`/`source`/`occurred_on` on the rows it already writes; add the Accountability section to the rendered email.
- **`v_tech_accountability` view / RPC** — the counting + suggested-level engine. Single source of the numbers for both the email and the dashboard.
- **Dashboard: Tech Accountability tab** — table + row expansion + the three log actions. New React Query hooks gated on session (anon-keyed first fetch returns [] under RLS).
- **Manual-entry form** — mobile-first, minimal.

Each unit communicates through the view/RPC and the two tables; the dashboard never recomputes the ladder itself.

## Data flow

1. `daily-supervisor-digest` (cron, gated) writes `source='auto'` rows into `supervisor_alerts` with category/occurred_on.
2. Charles logs `source='manual'` rows and `accountability_actions` from the field.
3. `v_tech_accountability` aggregates both into per-tech counts + suggested level.
4. The digest email and the FOM tab both read that aggregate. The email flags conversation-needed techs; the tab shows the full table and lets Charles record talks/discipline.

## Error handling and safety

- Nothing auto-disciplines. The ladder is advisory; suspensions/termination require Daniel.
- Point math is purely additive and does not touch KPI calculations (KPIs remain immutable).
- Every mistake row and action is editable/resolvable; the feature is reversible.
- Digest remains disabled (`enabled=false`) until Daniel flips it on; manual invocations still work for preview.
- Dollar amounts render in full ($5,243), never $Xk.

## Testing

- View/RPC unit tests: per-day, 7-day, period counts; suggested-level transitions including history-aware escalation; Charles co-tech attribution preserved.
- Email render test extended for the Accountability section (snapshot).
- `vendored-sync` test still passes (rules duplicated into the edge function stay byte-faithful).
- Manual-entry happy path + RLS (FOM can insert, non-FOM cannot).

## Open items for the implementation plan

- Verify Charles's login and the exact permission flag to gate the FOM tab.
- Confirm `supervisor_alerts` current columns/constraints against the live `jwrpj` schema before writing the migration (and the UNIQUE/NULLS-NOT-DISTINCT conflict target after `job_id` goes nullable).
- Decide the mobile entry point for "Log mistake" (within the tab vs. a lightweight standalone surface).
