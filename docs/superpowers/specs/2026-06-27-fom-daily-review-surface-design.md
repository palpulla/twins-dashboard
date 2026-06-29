# Tech Accountability — FOM Daily Review Surface (review-gated) Design

**Date:** 2026-06-27
**Owner:** Daniel (CEO), Charles (FOM, the reviewer)
**Status:** Approved design, pending implementation plan
**Builds on:** [[project_tech_accountability_program]] (Plans 1-3, shipped). This spec changes the commit model from "auto-writes points" to "system proposes, FOM disposes," before the program goes live.

## Purpose

Give the FOM one place to review every accountability item per technician per day: the items the system can calculate from HCP come pre-filled, the manual items are entered by hand, and **nothing counts as points until Charles reviews and saves**. This replaces the current split where the digest auto-writes points on one path and a separate daily checklist defaults everything to "yes."

## Decisions locked in brainstorming

- **Commit model:** review-gated. Auto-detection pre-fills the review; points post only when Charles saves a tech's day. He is the final authority and can override any auto item.
- **Review unit:** one card per technician per day. Per-ticket items listed under that day (one row per ticket worked); a day-level section for truck/uniform/conduct items.
- **Daily email:** becomes a "needs review" nudge — leads with how many tech-days await review, links into the dashboard, and shows current committed standings. It no longer writes points.
- **Un-reviewed days:** stay pending and **never auto-commit** (zero points until saved), but are **flagged prominently to Charles** (email count + dashboard badge) so the backlog is never hidden.

## Architecture

### The shift
- **Before:** digest detects misses → writes `accountability_points` immediately (Plan 3 auto-ledger + the 3-options auto-commit). Charles voids false ones later. Separate manual checklist.
- **After:** digest/dashboard detect misses → those become **pre-fill proposals** on the review surface. Charles reviews a tech-day, overrides as needed, fills manual items, **Saves** → committed `accountability_points` are written for that tech-day. No detection path writes points on its own.

### Review state lives in `tech_daily_review`
Reuse and extend the existing table (one row per `(technician_id, review_date)`):
- `results jsonb` holds the full review: `{ tickets: { <job_id>: { <code>: 'yes'|'no'|'na' } }, day: { <code>: 'yes'|'no'|'na' } }`.
- Add `status text` (`'pending' | 'committed'`) — `pending` until Charles saves. (`reviewed_at`/`reviewed_by` already exist and stamp the save.)
- On Save: void the prior `source IN ('checklist','auto')` `accountability_points` for that tech+date (the void-then-write idempotency we already use), then insert one violation entry per item resolved to "no"/flagged, with points/severity from the catalog. Mark `status='committed'`.

### Item taxonomy (catalog-driven)
Add a `scope text` column to `violation_types`: `'ticket' | 'day' | 'adhoc'`. Combined with the existing `auto_detectable` flag, this drives the surface:
- **Per-ticket, auto pre-filled** (`scope='ticket'`, `auto_detectable=true`): `present_3_options`, `send_invoice`, `failure_collect_payment`, `inform_arrival`.
- **Per-ticket, manual** (`scope='ticket'`, `auto_detectable=false`): `document_parts`, `before_photos`, `after_photos`, `safety_inspection`, `customer_signature`.
- **Day-level, manual** (`scope='day'`): `truck_restocked`, `company_uniform`, `vehicle_upkeep`, `rev_rise_call`, `missed_meeting`, `unapproved_absence`, `dirty_work_area`.
- **Ad-hoc** (`scope='adhoc'`): the conduct/judgment violations (`customer_complaint_negligence`, `recommend_safety_issues`, `left_job_incomplete`, `incorrect_diagnosis_return`, `no_late_call`, `missing_tools`, `repeat_after_coaching`, `unsafe_practices`, `incomplete_arrival_inspection`, `quote_dishonesty`, `property_damage`, `disrespect_customer`, `disrespect_peers`, `sensor_alignment_return`) — added via "Add violation," not a daily checkbox.

### Auto pre-fill computation (reuse existing detectors)
When Charles opens a tech-day, the dashboard loads that day's tickets and computes the auto items from HCP using the detectors we already have in `src/lib`:
- `present_3_options` — estimate options count < 3 (the `three-options` logic / `needsThreeOptionsFlag`, plus the job→estimate link).
- `send_invoice` / `failure_collect_payment` / `inform_arrival` — from the `evaluateMissingButtons` signals (INVOICE / PAY / OMW).
Each auto item is shown checked (compliant) or flagged (violation) with an "auto" tag. Charles's override always wins; the saved value is what counts.

## Components

- **Migration:** `violation_types.scope` column + backfill; `tech_daily_review.status` column + backfill `'committed'` for any existing rows.
- **`src/lib/accountability/prefill.ts`** — pure functions that, given a ticket's HCP-derived signals, return the auto pre-fill for each auto item. Reuses `needsThreeOptionsFlag` and the alert detectors. Unit-tested.
- **`useTechDayReview(techId, date)` hook** — loads the tech's tickets for the day + any saved `tech_daily_review` row, merges saved values over computed pre-fill, returns the review model.
- **`DailyReviewCard` component** — the per-tech-per-day card: per-ticket rows (auto + manual items), day-level section, Add-violation, Save. Mobile-first (Charles is in the field).
- **`useCommitDayReview` mutation** — on Save: upsert `tech_daily_review` (status committed), void prior checklist/auto points for that tech+date, insert the resolved violations. Invalidate the accountability queries.
- **Pending surface:** a query/count of tech-days in range with `status != 'committed'` (or no row); a dashboard badge + a row indicator; and the digest email's "N tech-days awaiting review" line.
- **Digest changes:** remove the auto-point emission (the Plan 3 missing-button → ledger block and the 3-options auto-commit). Keep the detection for the email's operational ticket list. Rework the email to lead with the pending-review nudge + committed standings.

## Data flow
1. Daily: digest emails Charles "N tech-days awaiting review" + committed standings. No points written.
2. Charles opens a tech-day on the dashboard → sees tickets with auto items pre-filled + manual blanks.
3. He overrides/fills, adds any ad-hoc violations, Saves.
4. Save voids that tech-day's prior checklist/auto points and writes the committed ones; marks the day reviewed.
5. The per-tech table, balances, Levels, and decay all read the committed ledger as before.

## Error handling / safety
- Nothing auto-disciplines; nothing counts until Charles saves. Pure human-in-the-loop.
- Un-reviewed days never commit; they stay visibly pending.
- Re-saving a day is idempotent (void-then-write for that tech+date).
- KPI math untouched; points remain editable/voidable; decay unchanged.
- Digest stays disabled until go-live; manual send still previews.

## Testing
- `prefill.ts` unit tests: each auto item computes the right pre-fill from signals (3 options, invoice, payment, arrival); manual items return no pre-fill.
- Commit tests: saving with N "no" items writes exactly N catalog-priced points for that tech+date; re-saving replaces (no double count); overriding an auto item changes what's written.
- Pending logic: a tech-day with no committed review counts as pending and contributes 0 points.
- Email render: the "N awaiting review" line and committed standings render; no points written on send.

## Open items for the plan
- Confirm the exact day-level item set with Charles (the spec's `scope='day'` list is the starting point; editable in the catalog).
- Decide whether `inform_arrival` (OMW) stays an auto item or is dropped, given it dominated early testing (it's now override-able regardless, so lower stakes).
- Verify the dashboard can fetch per-ticket HCP signals efficiently for a tech-day (jobs + linked estimates) without N+1.
