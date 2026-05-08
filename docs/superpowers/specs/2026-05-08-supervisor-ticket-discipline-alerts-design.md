# Supervisor Ticket-Discipline Alerts — Design

**Date:** 2026-05-08
**Status:** Approved (pending spec review)
**Owner:** Daniel

## Problem

Technicians sometimes close out HCP tickets without completing the full workflow (Schedule / OMW / Start / Finish / Invoice / Pay) or without writing job notes on small-dollar visits. The supervisor currently has no systematic way to catch these gaps. They have to spot-check tickets manually in HCP, which is error-prone and slow.

We want an end-of-day digest that surfaces every ticket from today with workflow or documentation gaps, plus an in-app Notifications tab with a global bell badge for at-a-glance triage.

## Scope

In scope:
- A nightly scheduled job that scans completed tickets and writes alert rows to the database
- An email digest sent at a configurable time (default 18:00 CDT)
- An in-app `/admin/notifications` page with open issues, past digests, and editable settings
- A bell icon + count badge in the global top bar that links to the page
- Schema additions to ingest fields we don't currently capture: job-level work notes, `started_at`, `invoiced_at`
- Charles co-tech attribution applied to alert ownership

Out of scope:
- SMS / push / Slack channels (email + in-app only for v1)
- Per-tech alerts to the technicians themselves (only the supervisor recipient is notified)
- Auto-resolving alerts when a tech retroactively clicks the missing button (manual "mark resolved" only for v1; auto-resolve can come later)
- Alerting on tickets older than 14 days (digest only covers recent activity)

## Alert Rules

### Rule 1 — Missing button clicks

**Scope:** Every job whose `completed_at` falls in the digest's reporting window.

**Reporting window:** Tickets that finished any time between the previous digest's send time and the current digest's send time. (For the initial 18:00 CDT digest this is approximately the 24 hours ending at 18:00.)

**Per-button checks:**

| Button   | Source field                                  | Skip condition                                               |
|----------|-----------------------------------------------|--------------------------------------------------------------|
| SCHEDULE | `jobs.scheduled_at IS NULL`                   | Always checked                                               |
| OMW      | No `on_my_way` event in job status history    | Always checked                                               |
| START    | `jobs.started_at IS NULL`                     | Always checked                                               |
| FINISH   | `jobs.completed_at IS NULL`                   | Always checked (no-op for in-scope jobs)                     |
| INVOICE  | No invoice row linked to job                  | Skip if `total_amount = 0`                                   |
| PAY      | Invoice exists but `invoices.paid_at IS NULL` | Skip if `total_amount = 0` AND skip if < 48h since FINISH    |

The $0 skip for INVOICE/PAY is intentional: warranty visits have nothing to charge, but SCHEDULE/OMW/START stay checked so dispatch discipline is still enforced on those tickets.

**PAY grace period:** PAY is only flagged on the digest that runs at least 48 hours after the FINISH timestamp. So a job finished at 16:00 Monday will not flag PAY on Monday's or Tuesday's 18:00 digest, but will flag on Wednesday's if still unpaid. Grace period is configurable in settings.

**Per-button enable toggle:** `app_settings.enabled_button_checks` is an array (`['SCHEDULE','OMW','START','FINISH','INVOICE','PAY']` by default). Disabling a button in settings excludes it from all checks.

### Rule 2 — Missing notes on sub-labor charge

**Scope:** Every job whose `completed_at` falls in the digest's reporting window.

**Trigger:** `total_amount < 185` (configurable threshold — `app_settings.notes_threshold_dollars`). Includes `total_amount = 0` (warranty visits).

**Check:** `work_notes IS NULL OR trim(work_notes) = ''` → alert.

**"Notes" definition:** The job-level work notes field on HCP — the tech's free-text summary of what they did on this visit. Line item descriptions and customer-facing job descriptions are NOT counted. Only the dedicated work-notes field.

### Rule overlap

A single ticket can fire both rules (e.g., a $99 service call missing both notes and the OMW button gets one row in the digest with both pills). Alerts are stored as separate rows in `supervisor_alerts` keyed by `(job_id, alert_type)`, but rendered as one ticket card per job in the digest and UI.

## Architecture

### Trigger

`pg_cron` schedules a Supabase edge function `daily-supervisor-digest`. The cron expression is read from `app_settings.digest_cron_expression` (derived from `app_settings.digest_time` whenever the user updates the setting via the UI). Updating settings writes both the time field and re-applies the cron via `cron.alter_job()`.

### Edge function flow

1. Determine reporting window: from `last_digest_sent_at` to `now()`. If `last_digest_sent_at` is null (first run), use `now() - interval '24 hours'`.
2. Query candidate jobs in two passes:
   - **Pass A (recent finishers)**: jobs with `completed_at` inside the reporting window → evaluated for Rule 1 (excluding PAY) and Rule 2.
   - **Pass B (PAY-grace agers)**: jobs with `completed_at` between `now() - (pay_grace_hours + 24h)` and `now() - pay_grace_hours`, where an invoice exists with `paid_at IS NULL` → evaluated for the PAY check only. This catches tickets that have just aged past the grace window since the previous digest.
3. For each job, evaluate the applicable rules against current settings (button toggles, threshold).
4. Apply Charles co-tech attribution per the rules in the "Charles Co-Tech Attribution" section. "First non-Charles tech" is determined by ascending HCP-assignment timestamp; if timestamps are equal, by ascending tech `id`.
5. Insert/upsert one row per `(job_id, alert_type, digest_date)` into `supervisor_alerts`. Re-runs of the same digest day are idempotent thanks to `UNIQUE NULLS NOT DISTINCT`.
6. Render an HTML email of all currently-unresolved rows in `supervisor_alerts`, grouped by job. (Includes both newly-inserted alerts and prior-day alerts that haven't been marked resolved.)
7. Send email via Resend to `app_settings.digest_recipient_email`. Skip send if there are zero unresolved rows.
8. Update `app_settings.last_digest_sent_at`.

The function is also callable on demand (no cron context) via the "Send test digest now" button in settings — same flow, but uses the previous 24h window as a stand-in.

### Schema additions

One migration file containing:

```sql
-- New columns on existing jobs table
ALTER TABLE public.jobs
  ADD COLUMN work_notes TEXT,
  ADD COLUMN started_at TIMESTAMPTZ,
  ADD COLUMN invoiced_at TIMESTAMPTZ;

-- Single-row settings table
CREATE TABLE public.app_settings (
  id INTEGER PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  digest_time TIME NOT NULL DEFAULT '18:00',
  digest_timezone TEXT NOT NULL DEFAULT 'America/Chicago',
  digest_cron_expression TEXT NOT NULL DEFAULT '0 18 * * *',
  digest_recipient_email TEXT NOT NULL,
  notes_threshold_dollars INTEGER NOT NULL DEFAULT 185,
  pay_grace_hours INTEGER NOT NULL DEFAULT 48,
  enabled_button_checks TEXT[] NOT NULL DEFAULT ARRAY['SCHEDULE','OMW','START','FINISH','INVOICE','PAY'],
  last_digest_sent_at TIMESTAMPTZ,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Audit/state table for individual alert rows
CREATE TABLE public.supervisor_alerts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  digest_date DATE NOT NULL,
  job_id UUID NOT NULL REFERENCES public.jobs(id) ON DELETE CASCADE,
  alert_type TEXT NOT NULL CHECK (alert_type IN ('missing_buttons', 'missing_notes')),
  details JSONB NOT NULL,
  attributed_tech_id UUID REFERENCES public.users(id),
  resolved_at TIMESTAMPTZ,
  resolved_by UUID REFERENCES public.users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE NULLS NOT DISTINCT (job_id, alert_type, digest_date)
);

CREATE INDEX idx_supervisor_alerts_unresolved ON public.supervisor_alerts (digest_date DESC) WHERE resolved_at IS NULL;
CREATE INDEX idx_supervisor_alerts_job ON public.supervisor_alerts (job_id);
```

`UNIQUE NULLS NOT DISTINCT` is required to prevent duplicate alert rows on re-runs (per the documented Postgres UPSERT pitfall).

### Webhook handler updates

The existing `webhook-handler` edge function is extended to populate the three new job columns from HCP webhook payloads:

- `work_notes` → from the HCP job's primary notes field (the tech work-notes field, not customer-facing description)
- `started_at` → set when receiving the HCP `started` event (alongside the existing status update)
- `invoiced_at` → set when an invoice is created/sent for the job

A backfill script populates these columns for tickets already in the database — best-effort from existing payload data, leaving NULL where unavailable.

### Notifications tab UI

Route: `/admin/notifications` (admin-only).

Three stacked sections:

1. **Today's open issues** — table of `supervisor_alerts` rows where `resolved_at IS NULL`, grouped by job. Columns: Job # + summary, Tech (with co-tech badge if applicable), Total, Issues (pills), Action (Mark resolved button). Clicking a row expands to show full ticket detail with a deep link out to HCP at `https://pro.housecallpro.com/app/jobs/{hcp_id}`.

2. **Past digests** — last 14 days of digest dates, each showing ticket count, issue count, and resolved/ignored breakdown. Click into any date to see the snapshot of alerts from that digest.

3. **Settings** (collapsible, expanded by default if no settings have been set):
   - Digest send time (time picker)
   - Recipient email (text input, defaults to admin's auth email)
   - Notes threshold $ (number input)
   - PAY grace hours (number input)
   - Button toggles (6 pills, click to enable/disable)
   - "Send test digest now" button — calls the edge function manually

### Global bell

Top bar component on every authenticated page. Bell SVG with a red badge showing unresolved-alert count (`SELECT COUNT(*) FROM supervisor_alerts WHERE resolved_at IS NULL`). Click → dropdown peek showing the 4 most recent unresolved alerts. "View all notifications →" links to `/admin/notifications`.

Count is fetched on page load and refreshed whenever an alert is marked resolved.

## Data Model — Charles Co-Tech Attribution

The `attributed_tech_id` field on `supervisor_alerts` resolves the ownership question per the load-bearing rule:

- 1 tech assigned, that tech = Charles → `attributed_tech_id = Charles.id`
- 1 tech assigned, that tech ≠ Charles → `attributed_tech_id = that_tech.id`
- 2+ techs assigned, none are Charles → `attributed_tech_id = first_assigned_non_charles_tech.id`
- 2+ techs assigned, Charles is one of them → `attributed_tech_id = first_other_tech.id`

The digest and table render `Maurice (+Charles)` style labels when Charles is a co-tech, so the supervisor sees who else was on the ticket without losing the primary attribution.

## Email Format

HTML email via Resend. Subject: `Daily ticket review — N issues across M tickets`. One sample-quality block per flagged ticket showing job summary, customer first name + last initial, tech (with co-tech), total, finish date, issue pills, and an "Open in HCP" deep link. Yellow summary banner at top showing ticket count, issue count, and total revenue for the day.

If zero alerts are flagged, no email is sent (silent success). The `supervisor_alerts` audit log will show zero new rows for the day, which is its own signal.

## Configuration & Defaults

| Setting                  | Default                                                |
|--------------------------|--------------------------------------------------------|
| Digest time              | 18:00 (America/Chicago)                                |
| Recipient                | The admin's auth email (Daniel)                        |
| Notes threshold          | $185                                                   |
| PAY grace                | 48 hours                                               |
| Enabled buttons          | All 6 (SCHEDULE / OMW / START / FINISH / INVOICE / PAY)|
| Email channel            | Resend (env: `RESEND_API_KEY`)                         |

All settings live in the single `app_settings` row; updating any of them via the UI takes effect on the next digest (or immediately for the bell-badge query).

## Open Items / Risks

- **Resend account**: needs an API key + verified sending domain (`twinsdash.com` or similar). If domain isn't verified, fall back to Resend's onboarding sandbox sender for the first runs.
- **Notes ingestion**: HCP's notes payload format needs verification — there may be multiple note types (job notes, internal notes, line item notes). The webhook handler change must pick the correct field. To be confirmed during implementation by inspecting a real webhook payload.
- **Backfill**: existing rows in `jobs` will have NULL for `work_notes`, `started_at`, `invoiced_at`. The backfill script is best-effort; tickets older than the webhook archive may stay NULL forever, but those are out of the digest window anyway.
- **Walk-in jobs**: if SCHEDULE button is genuinely never clicked for walk-in tickets (because the job is created on-the-fly), the alert may produce noise. If this becomes a problem, the SCHEDULE button toggle in settings is the immediate escape hatch (disable it). A more refined rule (e.g., "skip SCHEDULE if job created same-day as completion") can be added later if needed.

## Success Criteria

- A test digest run on yesterday's tickets produces an email matching the sample mockup, with correct alert pills for each known gap.
- Charles co-tech attribution is verified on at least one historical multi-tech ticket.
- The bell badge count matches the number of unresolved rows in `supervisor_alerts`.
- Marking an alert resolved removes it from the open-issues table and decrements the bell badge without a page refresh.
- Updating the digest time in settings reschedules `pg_cron` correctly (verifiable via `SELECT * FROM cron.job`).
