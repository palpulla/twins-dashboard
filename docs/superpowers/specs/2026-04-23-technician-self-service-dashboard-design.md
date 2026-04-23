# Technician Self-Service Dashboard — Design Spec

**Date:** 2026-04-23
**Product:** Twins Dashboard (palpulla/twins-dash, live at twinsdash.com)
**Audience builder:** Daniel (owner-operator)
**Status:** Approved for planning

## 1. Context

The admin Payroll tab already exists inside the Twins Dashboard. Daniel runs weekly payroll there: HCP sync, per-job parts entry, commission calc, paystub export. The three current technicians (Maurice, Nicholas, Charles) have no access to the dashboard today; commission is a black box to them until pay day.

This spec adds a **per-technician self-service view**. Each tech logs in and sees only their own appointments, estimates, and jobs. On completed jobs they enter parts used from the existing pricebook, which drives a provisional commission total for Today / Week / Month / Custom timeframes. Techs never see part prices or per-job commission amounts, which keeps pricing confidential while still giving them a running picture of their earnings.

Charles is also a field supervisor and receives a 2% override on Maurice's and Nicholas's post-parts subtotal. His view includes a Team Override card that reflects sibling-tech submission progress without leaking their parts.

## 2. Goals & non-goals

**Goals (v1):**
- Each technician logs in and sees only their own HCP records (appointments, estimates, completed jobs).
- Techs enter parts on completed jobs from the pricebook (no custom parts, no free-text pricing).
- Techs see their provisional commission aggregated by Today / Week / Month / Custom, but never per-job and never part prices.
- Admin retains final authority on weekly payroll and can edit tech entries; techs see a soft "admin reviewed & adjusted" flag when this happens.
- Charles gets a dedicated Team Override card with submission status for his direct reports.
- Real-time updates via existing HCP webhook plus a Force Refresh escape hatch.

**Non-goals (v1, explicitly deferred):**
- Push notifications / SMS to techs (admin nudges are email only in v1).
- Native mobile app wrapping via Capacitor (web responsive is sufficient).
- Tech-to-tech visibility (no leaderboards, no cross-tech scoreboards).
- Bulk parts entry across multiple jobs.
- Mid-week partial payroll exports; payroll is a weekly run.
- Tech ability to add custom parts or override pricebook prices.

## 3. Key decisions (from brainstorm)

| Decision | Choice |
|---|---|
| Trust / authority | Admin has final say; tech entries feed a shared pool; admin locks the week after payroll run. |
| Parts authority | Pricebook only. Custom parts require admin via Part Request queue. |
| Who gets the view | All three techs. Charles gets an extra Team Override card. |
| Tech feed contents | Three tabs: Appointments (upcoming), Estimates (pending), Jobs (completed, commission-eligible). |
| Sync mechanism | HCP webhook → Supabase realtime subscription (primary). Force Refresh button for gaps. |
| Parts entry UX | Autocomplete search with category chips. "Request admin add this part" for missing parts. |
| Scorecard content | Commission + performance (revenue, avg ticket, repair/install split, goal progress). |
| Locking | Admin can reopen locked weeks; tech sees banner. Tech never self-edits after submit. |
| Price-leak defense | No per-job commission ever. Aggregate-only. Submit-then-reveal per job with confirmation gate. Smart guard against missed parts. |
| Admin edit visibility | Soft flag ("Admin reviewed & adjusted") rather than itemized diff. |

## 4. Architecture approach

**Approach 3 — pre-computed `commissions` table + RLS on raw tables + tech-only views.**

The `commissions` table already exists in the payroll schema (per `sync_rules.py`). We layer on:
- Column-level `REVOKE` on price columns for the `technician` role.
- Postgres views (`v_my_*`) as the only data path tech dashboards use.
- Edge Functions for state-changing actions (submit parts, request part add, force refresh).
- Supabase realtime subscriptions on tech-scoped views for live updates.

Tech dashboards never query raw tables or compute commission client-side. Price data never crosses the wire to a tech session.

## 5. Data model

### 5.1 Table changes

| Table | Change |
|---|---|
| `technicians` | Add `auth_user_id uuid` (nullable). Admin sets this when linking an invited auth user to the HCP tech row. Unique constraint. |
| `runs` | Add `locked_at timestamptz` and `reopened_at timestamptz`. A week is tech-editable when `locked_at IS NULL` OR `reopened_at > locked_at`. |
| `job_parts` | Add `entered_by enum('tech','admin')`, `entered_at timestamptz DEFAULT now()`, `admin_adjusted bool DEFAULT false`. |
| `commissions` | Add `admin_adjusted bool DEFAULT false`. Recomputed by trigger when related `job_parts` or `jobs.revenue` change. |
| `parts_pricebook` | No column changes. `REVOKE SELECT (unit_price) ON parts_pricebook FROM technician_role`. |
| `jobs` | Add `owner_technician_id` FK if not already present (resolved from webhook `hcp_pro_id`). Add `deleted_at timestamptz` for soft delete. |

### 5.2 New table — `part_requests`

```sql
CREATE TABLE part_requests (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  requested_by uuid NOT NULL REFERENCES auth.users(id),
  technician_id uuid NOT NULL REFERENCES technicians(id),
  job_id uuid NOT NULL REFERENCES jobs(id),
  part_name_text text NOT NULL,
  notes text,
  status enum('pending','added','rejected') DEFAULT 'pending',
  resolved_by uuid REFERENCES auth.users(id),
  resolved_at timestamptz,
  resolved_pricebook_id uuid REFERENCES parts_pricebook(id),
  rejection_reason text,
  created_at timestamptz DEFAULT now()
);
```

### 5.3 Views (tech read path)

- **`v_my_jobs`** — `jobs` WHERE `owner_technician_id = current_technician_id()` AND `deleted_at IS NULL`. Columns: id, hcp_id, date, customer_first_name + last_initial, address, revenue, status, tip, week_start, locked flag, admin_adjusted flag. No parts, no commission, no subtotal.
- **`v_my_job_parts`** — `job_parts JOIN parts_pricebook` WHERE owning job is current tech's. Columns: id, job_id, part_name, category, quantity, entered_by, admin_adjusted. **No `unit_price`, no `total`.**
- **`v_my_commissions`** — `commissions` WHERE `technician_id = current_technician_id()`. Columns: week_start, total_commission, total_tip, admin_adjusted. For Charles, a separate view `v_team_override` returns aggregate primary commission by sibling tech plus their submission progress (submitted_count, total_count) — no `job_parts` join.
- **`v_my_scorecard`** — parameterized by timeframe. Computes revenue, job_count, avg_ticket, repair_vs_install split, goal progress. Reads from `v_my_jobs` plus the existing per-tech goals source used by the admin KPI dashboard (confirm during planning; if no goals table exists yet, the implementation plan adds `technician_goals` with per-tech, per-period targets editable by admin).

All views use `SECURITY INVOKER` so RLS still applies; views are a convenience layer, not a security bypass.

A helper function `current_technician_id()` returns the `technicians.id` WHERE `auth_user_id = auth.uid()`, or NULL.

### 5.4 RLS policies

- `technicians`: tech SELECT WHERE `id = current_technician_id()`. Admin: full.
- `jobs`, `job_parts`, `commissions`: tech SELECT WHERE `technician_id = current_technician_id()` (or on `job_parts` via join to jobs). Admin: full.
- `job_parts`: tech INSERT/UPDATE/DELETE allowed WHERE:
  - Job belongs to them, AND
  - Job status is Draft, AND
  - The run for that week has `locked_at IS NULL` or `reopened_at > locked_at`.
- `part_requests`: tech INSERT with `requested_by = auth.uid()`; tech SELECT own rows. Admin: full.
- Column-level: `REVOKE SELECT (unit_price, total) ON job_parts FROM technician_role`. `REVOKE SELECT (unit_price) ON parts_pricebook FROM technician_role`.

### 5.5 Job submission state machine

```
Draft ──(Confirm parts modal)──> Submitted ──(Admin locks week)──> Locked
                                     │
                                     └──(Admin reopens week)──> Draft (for Draft jobs only)
                                                             OR stays Submitted
```

State stored on `jobs.submission_status enum('draft','submitted','locked')`. Transitions via `submit-job-parts` Edge Function only; clients cannot update directly.

## 6. Realtime sync flow

### 6.1 Existing pipes (no changes)
- HCP webhook → Supabase edge → writes to `jobs`, `line_items`. Continues to power admin KPIs.

### 6.2 New wiring
1. Webhook enrichment: after write, match `hcp_pro_id` against `technicians.hcp_pro_id` and set `jobs.owner_technician_id`. Unmatched → admin "unassigned" bucket.
2. Tech dashboard subscribes to Supabase realtime channel on `v_my_jobs`. New rows push live; shadcn toast: "New job synced from HCP."
3. Force Refresh button calls `sync-my-hcp-jobs(technician_id, since)` Edge Function. Pulls HCP API directly for that tech, upserts `jobs`. Rate-limited server-side to once per 30 seconds per user.
4. Staleness indicator: header shows "Last synced: X min ago" based on max `updated_at`. > 15 min stale → warning banner with Refresh CTA.

### 6.3 New Edge Functions

| Function | Purpose |
|---|---|
| `sync-my-hcp-jobs(since)` | Force refresh for the calling tech. Pulls HCP, upserts jobs, returns count. Rate-limited. |
| `submit-job-parts(job_id)` | Atomic Draft → Submitted transition. Validates job ownership, week not locked, at least one part entered (or explicit "no parts used" flag). Recomputes `commissions` row. |
| `request-part-add(job_id, part_name, notes)` | Creates `part_requests` row. Emails admin. |
| `admin-lock-week(week_start)` | Admin-only. Sets `runs.locked_at`. Flips affected jobs to `locked`. Notifies tech dashboards via realtime. |
| `admin-reopen-week(week_start, reason)` | Admin-only. Clears locked, sets `reopened_at`, writes audit log. |

All functions validate the caller's role and return structured error codes for client handling.

## 7. Technician UX

### 7.1 Navigation
Left sidebar on desktop, bottom tab bar on mobile:
- Home (scorecard)
- Appointments
- Estimates
- Jobs
- Profile

Mobile breakpoint: 375px wide minimum, no horizontal scroll anywhere.

### 7.2 Home / Scorecard

Timeframe segmented control at top: **Today · Week · Month · Custom**.

**Hero cards (2-col desktop, stacked mobile):**
1. **Commission** — big $ number for timeframe. Sub-line: "X of Y jobs submitted · Z pending parts." Disclaimer beneath: *"Estimate. Final amounts confirmed when admin runs payroll. Pricebook-only; custom parts require admin review. Take with a grain of salt."*
2. **Revenue** — total + goal progress bar.

**Secondary row:** Job count + goal, Avg ticket, Repair/Install split donut.

**Charles-only Team Override card:**
- Big override $ number (provisional).
- Sub-line: "Maurice: 8 of 10 submitted · Nicholas: 6 of 7 submitted."
- Text: *"Sub-commission depends on Maurice and Nicholas submitting their parts. Number updates as they submit."*

### 7.3 Jobs tab

List of completed jobs, newest first, grouped by week. Each row:
- Date · customer first name + last initial
- Revenue $
- Status badge: **Draft** (yellow) / **Submitted** (green) / **Locked** (grey)
- Parts count: "3 parts entered" or "No parts entered"
- Soft badge "Admin reviewed & adjusted" if `commissions.admin_adjusted`

Filter bar: timeframe + status.

### 7.4 Job Detail

- Header: date, customer, address, revenue, "Open in HCP" deep-link.
- Parts used list: part name + qty only. No prices, no totals.
- Add Part button → Parts picker modal.
- Submit Parts button (primary, only when Draft + week unlocked). Disabled until at least one part added OR tech confirms a "No parts used on this job" checkbox.
- Submitted state: replaced with "Request correction" link + "Submitted on [date]" timestamp.
- Locked state: read-only. Soft badge if adjusted.

### 7.5 Parts picker modal

- Search input with autocomplete on pricebook part names (no prices).
- Category chips: Springs · Openers · Cables · Rollers · Sensors · Motors · Hardware · Other.
- Results: part name only. Tap to select → qty stepper → Add.
- Footer link: *"Can't find a part? Request admin add it."* → opens short form (part name + notes), closes picker, job stays Draft.

### 7.6 Submit confirmation modal

- Title: *"Confirm parts for this job?"*
- Body: list of parts entered (name + qty).
- Smart guard warning (if triggered): *"⚠ This job's HCP notes mention 'spring' but no springs entered. Did you use any?"* Keywords checked: spring, opener, cable, roller, motor, sensor.
- Bold: *"After confirming, corrections require admin review."*
- Buttons: **[Keep editing]** · **[Confirm]**

### 7.7 Appointments tab

Chronological list: date/time, customer, address, job type. Tap → "Open in HCP." View-only.

### 7.8 Estimates tab

List of open estimates: date, customer, amount, status. View-only.

### 7.9 Profile tab

Name, email, commission rate, HCP technician ID, sign out. Read-only; admin manages everything.

### 7.10 Empty / error states

- Not linked: *"Your technician profile isn't linked yet. Contact Daniel to finish setup."*
- No jobs in timeframe: *"No jobs yet for this period. Jobs appear automatically when they're created in HCP."*
- Sync stale: banner *"Sync may be delayed. Tap Refresh."*
- Offline: banner *"You're offline. Changes queue and sync when you're back online."*

## 8. Admin integration

Extensions to the existing admin Payroll tab. No existing flows are rewritten.

### 8.1 Techs Activity panel (new sub-tab)

Weekly status table: Tech | Jobs this week | Draft | Submitted | Part requests | Last activity. Nudge button per row → email reminder (uses existing Gmail integration).

### 8.2 Part Requests queue (new sub-tab)

List of pending `part_requests`. Per row:
- **Add to pricebook** modal: part name (prefilled), category, unit price, one-time vs permanent toggle. On save: inserts into `parts_pricebook`, auto-inserts into originating job (qty 1, editable), sets request status='added'.
- **Reject** with reason → status='rejected'. Tech sees rejection reason on Job Detail.

### 8.3 Run Payroll flow — additive changes only

Existing wizard stays. New behaviors:
- Per-job review step pre-populates parts from tech entries. Each row tagged `entered by Maurice` / `entered by admin`. Admin edits flip `job_parts.admin_adjusted`; any row flip on a job sets `commissions.admin_adjusted = true`.
- Pre-flight check: *"3 jobs still in Draft. [Review] [Ignore]."* Admin can submit on tech's behalf.
- Finalize step explicitly says *"Lock week for all techs — techs will be notified."*

### 8.4 Reopen week

On a finalized run: Reopen button → reason modal (saved to audit log) → sets `reopened_at`. Tech dashboards show banner.

### 8.5 Techs management (new sub-tab)

Per row: name, HCP pro_id, rate, bonus tier, effective date, **Linked auth user** dropdown (unclaimed technician-role users). Edit rate/effective date writes a new `commission_rules` row; never mutates history.

### 8.6 Audit log (new sub-tab)

Read-only chronological log: who locked, reopened, edited parts, submitted on behalf, resolved part requests. Supports the "all changes reversible" principle.

## 9. Edge cases

| Case | Handling |
|---|---|
| Tech auth user not linked | Empty state; no queries run. |
| Webhook misses a job | Force Refresh button. Staleness banner > 15 min. |
| Tech wants edit after submit (week open) | Request correction → admin Activity panel → admin edits. |
| Tech correction after week locked | Same flow; requires admin reopen or next-run adjustment. |
| Admin edits parts after submit | `admin_adjusted = true`; tech sees soft badge; aggregates refresh. |
| Admin reopens locked week | Banner on tech dashboards; Draft jobs editable again; Submitted stays unless admin reverts. |
| HCP revenue changes after submit | Commissions recompute; soft badge; live aggregate refresh. |
| HCP job reassigned to different tech | `job_parts` stay attached to the job; old tech's aggregate drops, new owner's rises. Admin notified; audit logged. |
| HCP job deleted | Soft delete via `deleted_at`; drops from tech view + aggregates. |
| New tech added mid-week | Admin creates row, links auth user; HCP sync backfills their week. |
| Duplicate part row | Allowed; techs may use same part twice. Qty stepper is primary path. |
| Pending part request during payroll | Pre-flight check flags: [Review] [Ignore]. |
| Charles override while siblings in Draft | Team Override card shows provisional + submission progress. |
| Offline field signal | Parts picker state persists in IndexedDB via TanStack Query. Submit queued until online. |
| Tech tries direct table query | RLS blocks; only `v_my_*` views return data, with column filters. |

## 10. Testing priorities

1. **RLS tests (highest priority).** Maurice cannot SELECT Nicholas's jobs; cannot SELECT `unit_price`; cannot UPDATE Locked parts. Charles sees sibling `primary` commissions but not their `job_parts` prices.
2. **Price-leak regression (snapshot).** Every API response under a tech session is scanned for `unit_price`, `total`, per-job commission fields. Any appearance fails the test.
3. **Submission state machine.** Draft → Submitted → Locked transitions; reopen path; `admin_adjusted` propagation.
4. **Smart guard.** HCP notes/line_items trigger "forgot parts" warnings.
5. **Commission recompute.** Admin edits part → `commissions` updates → `admin_adjusted` flips.
6. **Realtime subscription (e2e).** Simulate webhook → new row appears in tech UI within 5 seconds.
7. **Charles Team Override.** Updates live as siblings submit; reflects only `primary` commission, not `job_parts`.
8. **Mobile viewport (Playwright @ 375×667).** No horizontal scroll on any screen.
9. **Force Refresh rate limit.** 30s cooldown enforced server-side.
10. **Part request flow (e2e).** Tech requests → admin adds → request auto-closes → originating job has the part.

## 11. Rollout

Phased rollout to minimize risk:

1. **Phase 1 (infra):** Schema migrations (table adds, views, RLS, column revokes). Edge Functions deployed. Admin gets the new sub-tabs (Techs Activity, Part Requests, Techs management, Audit log).
2. **Phase 2 (Maurice pilot):** Link Maurice's auth user. He uses the tech dashboard for one week in parallel with the existing manual process. Daniel compares his submissions against his own admin entries.
3. **Phase 3 (Nicholas + Charles):** After Maurice pilot passes, roll out to the other two. Charles's Team Override card activated.
4. **Phase 4 (cutover):** Weekly payroll now officially consumes tech-entered parts as the source of truth. Manual Excel side-process retired.

Each phase is a separate merge to main; Lovable auto-deploys; Daniel hits Publish if needed.

## 12. Reversibility

Consistent with Daniel's standing requirement that all changes be reversible and KPI math be immutable:

- Commission math in `kpi-calculations.ts` and the payroll engine is **unchanged**. This spec adds a new read/write surface for techs but never alters calculation logic.
- Every rate change writes a new `commission_rules` row with an effective date; history is preserved.
- Admin edits to `job_parts` modify rows in place but append an entry to the audit log capturing the before/after and the editor. Tech's original entry is preserved in the audit log even when the live row is mutated.
- Week lock/reopen is fully reversible and audit-logged.
- Phase 1–3 can each be backed out by disabling RLS policies for the technician role and the tech dashboard routes; admin flow is unaffected.
