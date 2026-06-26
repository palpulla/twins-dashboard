# Technician Accountability Program — Point System Design

**Date:** 2026-06-26
**Program name:** Twins Garage Doors Technician Accountability Program
**Owner:** Daniel (CEO, approves discipline), Charles (FOM/service manager, grades daily and drives in field)
**Status:** Approved design, pending implementation plan
**Source of truth for the point model:** Charles's Technician Accountability Program (the 1/2/3-point violation lists, the 2/4/6/8 Level ladder, the daily checklist, and point decay). This **supersedes** the PDF's Section 10 ladder and the earlier "1 point per mistake" draft. The Field Sales Implementation Plan PDF remains background context only.

## Purpose

The `daily-supervisor-digest` report (built, currently disabled) already scans completed HCP jobs for ticket misses and emails an FOM/CEO digest. This design turns that into Charles's full accountability program: weighted points per violation, a cumulative balance with a 30-day decay, a Level ladder that suggests disciplinary action, a daily per-tech checklist Charles grades, a place to log the talks and discipline actually taken, and an FOM aggregate on the dashboard.

The system **counts, suggests, and records**. It never auto-disciplines. Suspensions and termination review remain human decisions with Daniel's sign-off.

## The point model

### Weighted violations

Each violation carries 1, 2, or 3 points. Catalog (editable config — values below are the launch defaults):

**1 point — Minor**
- Failed to present 3 repair options *(checklist)*
- Failed to document parts used *(checklist)*
- Missing before photos *(checklist)*
- Missing after photos *(checklist)*
- Did not collect required customer signature *(checklist)*
- Did not send invoice *(checklist; auto-detectable)*
- Truck not restocked at end of day / returned to get parts for a job *(checklist)*
- Dirty work area after job
- Failed to inform customer of arrival *(auto-detectable: OMW)*
- Not wearing company uniform
- Failed to upkeep company vehicle
- Missing Rev & Rise call
- Did not explain safety-sensor alignment, causing a return call

**2 points — Serious**
- Customer complaint caused by technician negligence
- Did not perform required safety inspection *(checklist)*
- Failed to recommend obvious safety issues
- Left job incomplete without approval
- Incorrect diagnosis resulting in a return visit *(callback — see below)*
- Failed to call customer when running late
- Missing required tools
- Repeated 1-point violation after a coaching discussion
- Missed company L10 or sales-training meeting

**3 points — Major**
- Unsafe work practices
- Incomplete required arrival-inspection fields (photos uploaded, parts entered correctly, job notes completed)
- Dishonesty on a quote
- Damaging customer property due to negligence
- Failure to collect payment *(checklist; auto-detectable)*
- Unapproved absence
- Disrespectful behavior toward a customer
- Disrespectful behavior toward peers

### Cumulative balance + decay (locked in brainstorming)

There is **one running balance per tech**, not a weekly reset. Charles's "start every week with 0" is implemented as a **weekly points-earned scorecard** (Fri-Thu, matching the payroll week) layered over the persistent balance — the weekly number is a coaching lens, the cumulative balance is the disciplinary record.

- **Decay:** every 30 consecutive days a tech goes without earning a point, the balance drops by 1 (then the 30-day clock restarts). Implemented as a dated negative entry in the ledger so it is auditable and reversible.
- **Reward:** a calendar month with zero points flags the tech as **reward-eligible** (gift card / preferred scheduling / recognition). The system flags eligibility; Daniel decides the reward. No automated payout.

### Level ladder (suggested action)

Computed from the current balance; advisory only.

| Balance | Level | Suggested action |
|---|---|---|
| 2 | Level 1 | Coaching discussion + documented improvement plan + note in employee file |
| 4 | Level 2 | Written warning + 1-day suspension (Daniel approval) |
| 6 | Level 3 | Final written warning + 3-day suspension (Daniel approval) |
| 8 | Level 4 | Termination review (Daniel) |

3-point ("Major") violations may warrant immediate action regardless of balance; the system flags them but the call is human.

### Callbacks

A callback is identified by the HCP "Callback" tag (already on jobs as `is_callback`). The tag does **not** establish fault, so Charles classifies each attributed callback as **recall** (tech could have prevented → scores as the matching violation: "incorrect diagnosis resulting in a return visit" = 2 pts, or "did not explain safety-sensor alignment" = 1 pt), **warranty** (outside tech control → 0 points), or **training_gap** (Twins never trained the standard → 0 points, fix-and-train first). No heuristic auto-classification of fault. Callback rate per tech is shown as an informational metric (reusing `calculateCallbackRate`); the points ladder, not a separate recall-rate ladder, is the disciplinary mechanism.

### Daily checklist (primary input)

Charles grades every tech daily (the "Supervisor Review" at end of day). The 9 checklist items and their point values:

| Checklist item | Points if "No" | Auto-prefill? |
|---|---|---|
| Presented 3 options | 1 | No |
| Parts listed correctly | 1 | Best-effort (line items) |
| Before photos uploaded | 1 | Best-effort (HCP attachments) |
| After photos uploaded | 1 | Best-effort (HCP attachments) |
| Safety inspection completed | 2 | No |
| Invoice complete | 1 | Yes (INVOICE / invoice_number) |
| Customer signature obtained | 1 | Best-effort (HCP signature field) |
| Payment collected | 3 | Yes (PAY / outstanding_balance == 0) |
| Truck restocked | 1 | No (per-tech EOD, not per-job) |

Items HCP reliably exposes are pre-filled; Charles confirms or overrides the rest. Every "No" creates a violation ledger entry at the listed points. The plan verifies which HCP fields are actually available before relying on a prefill.

## Architecture

### 1. `violation_types` — catalog config
Reference table: `code`, `label`, `points` (1/2/3), `severity` (`minor|serious|major`), `is_checklist_item` (bool), `auto_detectable` (bool), `active`. Seeded from the catalog above; editable by admin so Daniel/Charles can tune point values without a deploy.

### 2. `accountability_points` — the ledger (single source of points)
Append-only, signed entries. Columns: `id`, `technician_id`, `points` (signed int; violations +, decay −, manual adjustment ±), `reason_type` (`violation|decay|adjustment`), `violation_code` (nullable FK to `violation_types`), `severity`, `source` (`auto|checklist|manual`), `occurred_on date`, `job_id` (nullable — links to a job when relevant), `note`, `created_by`, `voided_at`/`voided_by` (soft-delete for reversibility), `created_at`. **Current balance = sum(points) over non-voided entries.** Charles co-tech attribution rule applies to auto/job-linked entries (Charles alone = his; Charles + other = the other tech's).

### 3. `tech_daily_review` — checklist audit
Per tech per day: the 9 item results (yes/no), `reviewed_by`, `reviewed_at`, `note`. Records that a tech was graded (even a clean day) for audit, and each "No" spawns a ledger violation entry. One row per tech per day.

### 4. `accountability_actions` — talks + discipline log
`technician_id`, `action_type` (`coaching_discussion | improvement_plan | written_warning | suspension_1day | final_written_warning | suspension_3day | termination_review | recognition`), `occurred_on`, `notes` (the talk summary — Daniel's "capture talks"), `level_at_time`, `balance_at_time`, `created_by`, optional `related_entry_ids`, `created_at`. This is the record of what was actually done.

### 5. Engine — `v_tech_accountability` view / RPC
Single source of the numbers for both the email and the dashboard. Per active tech: current balance, current Level, points earned this Fri-Thu week, points by severity and by category, days since last point, decay-eligibility (days into the current 30-day clean window), reward-eligibility (clean calendar month), callback rate, and count of callbacks awaiting classification.

### 6. Decay cron
A daily scheduled function: for each tech at >0 balance whose last non-decay point is ≥30 days old, insert a `reason_type='decay'`, `points=-1` entry dated today and restart the clock. Auditable; reversible by voiding the decay entry.

### 7. `daily-supervisor-digest` edge function (refactor)
Its existing auto checks now emit ledger entries via the catalog (invoice not sent → 1 pt; payment not collected → 3 pt; etc.) instead of bespoke alert rows. Adds an **Accountability section** to the email, grouped by tech: weekly points, current balance + Level, anyone who crossed a Level since the last digest, and callbacks awaiting classification. Stays gated/disabled until Daniel flips it on; manual invocation still previews.

### 8. FOM aggregate — "Tech Accountability" tab on `/admin/notifications`
Per-tech table, worst-first:

| Tech | Balance | Level | This week (Fri-Thu) | By severity | Callback rate | Days clean | Last action |

- Severity column: pills for minor/serious/major counts.
- Level cell color-banded (L1-L4); reward-eligible techs get a positive badge.
- Callback-rate cell shows a "N to classify" chip when callbacks await Charles.
- Row expands to: the daily checklist for a chosen day (gradeable inline), every ledger entry (violations, decay, adjustments, each voidable), callbacks awaiting classification, and the full action log.
- Row actions: **Grade daily checklist**, **Add violation**, **Classify callback**, **Log action** (talk/discipline/recognition), **Void entry** (correction).
- Period selector reuses the dashboard's; the daily checklist is mobile-first for field use.

### 9. Access control
Charles already logs into the dashboard. The new tab gates to admin **plus** Charles's existing supervisor/FOM role/permission; the plan confirms the exact flag in the access-control model (roles + RLS + permissions JSON). RLS: admin + FOM read; FOM may insert checklist results, violations, classifications, and actions; only soft-void (never hard-delete).

## Data flow

1. Auto checks (digest cron) emit `source='auto'` ledger entries via the catalog.
2. Charles grades the daily checklist (`source='checklist'`) and logs off-checklist violations (`source='manual'`); each "No"/violation is a ledger entry.
3. Charles classifies callbacks; tech-fault ones become ledger entries.
4. The decay cron writes negative entries on 30-day-clean techs.
5. `v_tech_accountability` rolls the ledger into balance, Level, weekly points, callback rate, decay/reward eligibility.
6. The digest email flags Level crossings; the FOM tab shows the full table and is where Charles grades, logs, and records discipline.

## Error handling and safety

- Nothing auto-disciplines; the Level ladder is advisory and suspensions/termination need Daniel.
- Point math is additive and isolated — it does not touch KPI calculations (KPIs remain immutable).
- Every ledger entry and action is soft-voidable/editable; decay and adjustments are explicit dated entries — the whole record is auditable and reversible.
- Catalog point values are config, changeable without a deploy.
- Digest stays disabled until go-live; manual invocation previews.
- Dollar amounts render in full ($5,243), never $Xk.

## Testing

- Engine tests: balance from signed entries; Level thresholds at 2/4/6/8; weekly (Fri-Thu) points-earned vs cumulative balance; decay fires at exactly 30 clean days and restarts; reward-eligibility on a clean calendar month; only tech-fault callbacks score; Charles co-tech attribution preserved.
- Catalog mapping test: each checklist "No" produces the correct point value.
- Email render test extended for the Accountability section (snapshot).
- `vendored-sync` test still passes (edge-function rule copies stay byte-faithful).
- RLS: FOM can grade/log/void; non-FOM cannot; nothing hard-deletes.

## Open items for the implementation plan

- Confirm Charles's existing role and the exact permission flag to gate the FOM tab.
- Verify which HCP fields actually back the checklist prefills (photos/attachments, signature, line items) before relying on them; anything unverified defaults to Charles's manual mark.
- Confirm the launch point values, especially Payment collected = 3 and the arrival-inspection-fields = 3 grouping (Charles's lists imply these; catalog is editable so this is tunable, not blocking).
- Confirm how `supervisor_alerts` and its existing `/admin/notifications` "open issues" view are reconciled with the ledger (subsume vs keep as an operational view over auto entries).
- Confirm the live `accountability_points`/`supervisor_alerts` schema and any UNIQUE/NULLS-NOT-DISTINCT conflict targets before writing the migration; INSERT the migration-history row after apply (known desync on jwrpj).
