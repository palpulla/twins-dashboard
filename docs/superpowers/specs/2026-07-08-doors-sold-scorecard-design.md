# Doors Sold (pending install) — Rev & Rise scorecard

Date: 2026-07-08
Status: Approved design, ready for implementation plan
Owner: Daniel (FOM-facing)

## Problem

The FOM reads the Rev & Rise tab during the daily huddle. Daniel wants a
**sold-door counter** he can read off: how many garage doors the team has
**sold but not yet installed**, per recent day, with the total dollar value of
those sales. Example he gave: "yesterday 1 sale, the day before 1 sale, plus the
total value of the sale" — where that value must **not** be added to revenue,
because the door is sold (deposit taken, ~50% down) but not yet installed or
paid in full.

Concrete real examples (sold Mon Jul 6, not installed as of Jul 8):
- Estimate **2735** — approved option, $6,182
- Estimate **2730-2** — approved option, $3,775

## Data reality (why the definition is what it is)

Investigated against the live prod DB (`jwrpjuqaynownxaoeayi`):

1. **A "door" is only trustworthy via the human-set `job_type`.** The canonical
   install types are `Door Install` and `Door + Opener Install`
   (`INSTALL_JOB_TYPES` in `src/lib/constants.ts`) — the same list payroll and
   KPIs use.

2. **Approval status alone is NOT "a door."** In HCP an estimate has multiple
   **options**; the customer approves one (`approval_status = "approved"` /
   `"pro approved"`, option `status = "created job from estimate"`). But the set
   of approved-not-completed options in the Jul 6 window is dominated by
   **non-doors**: $49 tune-ups, $98/$185 service items, etc., mixed in with the
   two real doors. Counting approved options as "doors" would over-count badly
   and mislabel service work as doors — a violation of the project rule *no
   heuristic classifiers for business rules* (no structured tag → exclude or
   ask; never guess from value or free text).

3. **The child door-job is missing from our DB before install.** When a door is
   sold it becomes an HCP job that is usually `work_status = "needs scheduling"`
   (no scheduled date). `sync-hcp-jobs` queries HCP by `scheduled_start_min/max`,
   so unscheduled created-from-estimate jobs never land in our `jobs` table.
   Confirmed: the child jobs for estimates 2730 and 2735 are absent. This is the
   same null-scheduled gap already documented for payroll.

4. **The estimate option carries the clean sale facts** we already sync richly
   in `jobs.hcp_data->'options'`: `total_amount` (cents), `approval_status`,
   `status`, `updated_at` (approval moment), and the estimate's
   `assigned_employees` (tech).

Conclusion: anchor on the **sold estimate option** (rich, already-synced) and
**enrich it with its child job's `job_type`** so we count only true doors.

## Definition — "Sold door (pending install)"

An estimate option qualifies as a sold, not-yet-installed door when **all** hold:

- The parent row is an estimate with `estimate_status = 'sold'`.
- The option's `approval_status` ∈ {`approved`, `pro approved`}.
- The option's child job `job_type` ∈ {`Door Install`, `Door + Opener Install`}
  (the deterministic door signal — see Enrichment).
- The work is **not yet completed or removed**: option `status` ∉
  {`complete rated`, `complete unrated`, `deleted`} and the estimate
  `work_status` ≠ `pro canceled`.

Derived fields per qualifying door:
- **Sale value** = option `total_amount` / 100 (contract value, e.g. $6,182).
- **Sold date** = option `updated_at` (approval moment), converted to
  `America/Chicago` for day bucketing (parse DB timestamps as Central, per the
  Fri–Thu payroll-week convention already used in the app).
- **Customer** = estimate `hcp_data->'customer'` first/last name.
- **Tech** = estimate `assigned_employees`, applying the **Charles co-tech rule**
  (LOAD-BEARING): Charles alone → Charles; Charles + any other tech → the other
  tech. Reuse the existing attribution helper, do not re-implement.

### Enrichment: resolving the child job's `job_type`

The one piece we do not already store is each approved option's child-job
`job_type`. The implementation plan will resolve this deterministically (no
guessing from value/text). Candidate mechanisms, to be chosen in planning:

- Extend job ingestion to capture created-from-estimate jobs that are
  unscheduled / `needs scheduling` (close the null-scheduled gap), then join
  option → job and read `job_type`; **or**
- A targeted resolver that, for each approved option, fetches its child job from
  HCP and records the `job_type`.

Lean on existing machinery (`backfill-estimate-job-links`, `sync-missing-jobs`,
`reconcile-hcp-data`). Store the resolved door facts so the frontend reads them
directly (a view or a small derived table — decided in the plan). Options whose
child-job type is unresolved are **excluded** (never assumed to be doors).

## Scope / window

The standalone card follows the **tab's current call window** (`dateRange` from
`resolveCallWindow`), same as the rest of Rev & Rise. Doors bucket by **sold
date** (approval moment, Central) within that window.

## UI

Both placements (per Daniel):

### 1. Standalone "Doors Sold" card (weekly view, `mode !== 'daily'`)

Placed near the top of the non-daily Rev & Rise view (after `CompanyKpiStrip`,
before/near `WinsBlock`). Styled to match existing `rd-card` sections
(cf. `RecentSoldJobs`). Contents:

- Header: **Doors Sold** + summary `N sold · $TOTAL` for the window.
- Sub-label (honesty): *"Sold, pending install — deposits taken, not counted in
  revenue."*
- Per-day mini-breakdown, e.g. `Mon 1 · Tue 1` (only days with ≥1 door, within
  window).
- Short list of the doors: customer · tech · sale value · day. Link each to the
  HCP estimate where practical.
- Empty state: *"No doors sold in this window yet."*

Dollar formatting: full dollars, rounded, thousands separator, no `$Xk`
(e.g. `$6,182`) — per project currency rule.

### 2. Per-tech line on `PerTechCards`

Add one metric row to each technician card: `Doors sold: N ($X)`, where N and $
are that tech's qualifying doors in the window (Charles rule applied). Omit or
show `0` consistently when a tech has none (match the card's existing style).

## Revenue handling

Brand-new, **separate, untouched**. The existing Rev & Rise Revenue KPI math is
not modified in any way. Door sale value is displayed beside revenue, never
folded into it. KPIs remain immutable.

## Components / data flow

- **New hook** `use-doors-sold.ts` — queries the resolved door facts for a
  `DateRange`, returns per-door rows + window totals + per-day + per-tech
  aggregates. Gate fetch on session (`enabled: !!session`) and use stable memoized
  range keys (avoid unstable `new Date()` query keys), per existing React Query
  conventions in this codebase.
- **New component** `DoorsSoldCard.tsx` (in `src/components/rev-rise/`).
- **Edit** `PerTechCards.tsx` — add the doors line (fed from the same hook or a
  per-tech slice passed down).
- **Edit** `RevRiseDashboard.tsx` — render `DoorsSoldCard` in the weekly branch.
- **Pipeline**: enrichment/resolver for child-job `job_type` (edge function
  and/or backfill) + persisted door facts (view or table).

## Testing

- Unit: the qualify predicate (approved + door type + not-completed/canceled),
  cents→dollars, Central-time day bucketing, Charles co-tech attribution.
- Fixtures anchored on the real shapes of estimates 2730 (multi-option, 2730‑2
  approved) and 2735 (single option) so 2730‑2 and 2735 count and the sibling
  non-approved/non-door options do not.
- Verify non-door approved options ($49 tune-up, $98 service) are **excluded**.
- Verify completed/canceled doors drop out of the pending count.

## Out of scope (v1)

- Detecting doors from estimate line-item text or value thresholds (forbidden).
- Any change to revenue recognition or existing KPIs.
- Daily-mode view (`mode === 'daily'`) additions — weekly view only for v1.
- Historical trend/charting of doors sold.

## Non-negotiables carried from project rules

- No heuristic classifiers for the door decision — only the structured
  `job_type` tag counts; unresolved → excluded.
- Charles co-tech attribution applied everywhere doors are attributed.
- Full dollar amounts, no `$Xk`.
- Revenue KPI math untouched (immutable KPIs).
- Never disable/regress HCP webhooks or existing sync behavior.
