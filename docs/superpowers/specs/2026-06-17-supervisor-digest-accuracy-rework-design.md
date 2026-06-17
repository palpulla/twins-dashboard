# Supervisor ticket-discipline digest — accuracy rework

**Date:** 2026-06-17
**Repo:** `twins-dash` (Vite app + Supabase edge functions), live DB `jwrpjuqaynownxaoeayi`
**Function:** `supabase/functions/daily-supervisor-digest`
**Status:** Design approved, pending implementation plan

## Problem

There is a daily "ticket review" report that flags tickets where a field tech skipped a
step (no invoice, no notes on a low-dollar ticket, missing button presses). The report
"is not done well" — it produces **inaccurate / false flags**, and it must reliably reach
the **Field Operations Manager (FOM)**.

## What we found (diagnosis)

- The report is the `daily-supervisor-digest` edge function. It runs hourly via the
  `supervisor-digest-hourly` pg_cron job and self-gates to 6:00 PM America/Chicago.
- It is currently **disabled** (`supervisor_digest_config.enabled = false`). The last real
  email sent on **2026-05-12**.
- The two biggest false-flag bugs were fixed in code on **2026-05-13** — one day *after*
  the last send — and have **never run in production** because the digest was disabled:
  - #183: read OMW / tech assignments / notes from `hcp_data` instead of the dedicated
    columns the daily `sync-hcp-jobs` cron overwrites (was flagging nearly every ticket).
  - #185: "fix Maurice-on-every-job" attribution bug.
- The deployed function (version 19) matches the repo and contains both fixes.
- **One real accuracy bug remains in code:** the candidate query does not exclude
  `job_type = 'Estimate'`. HCP marks estimates "completed", so they enter the report as if
  they were performed jobs. In a 21-day sample, 18 of ~99 candidate tickets were estimates
  (all $0), and they accounted for the bulk of the bogus "missing notes" flags.
- Live data sample (21 days, `is_opportunity = true`, evaluated the way the code actually
  evaluates — i.e. with the `hcp_data` fallbacks):
  - True missing OMW: 4. True missing START: 5. (Button checks are healthy.)
  - "Missing notes" would fire on 53 tickets: 18 estimates, 45 $0 tickets, and **8 real
    paid tickets ≤ $185 with no notes** — the 8 are the genuine signal.

## Recipient / FOM facts

- The Field Operations Manager is **Charles** (`charlesrue@icloud.com`,
  `user_roles.role = manager`, `user_roles.title = 'Field Operations Manager'`,
  `is_active = true`).
- Charles is already on the digest recipient list (`daniel@twinsgaragedoors.com,
  charlesrue@icloud.com`), but the list is a hardcoded string in
  `supervisor_digest_config.digest_recipient_email`, not tied to the FOM role.
- Charles is **also** the `co_tech_default_tech_id` used by the Charles co-tech attribution
  rule. Shared tickets are attributed away from Charles to the other tech, and Charles
  receives the report as the crew's manager. This is intentional and accepted.

## Decisions (from the user)

- **Notes rule:** flag **any ticket at or below $185** with no work notes. Keep the $185
  threshold. $0 tickets are intentionally included.
- **Recipients:** send to whoever holds the **Field Operations Manager** title (resolved
  dynamically) **plus Daniel**. Must survive a personnel change without a code edit.
- **Email scope:** accuracy first, with light polish to wording/layout. No full redesign.

## Design

### 1. Accuracy (core fix)

- **Exclude estimates** from the candidate query: add `job_type <> 'Estimate'`
  (case-insensitive) alongside the existing `is_opportunity = true` and `completed_at`
  window filters in `fetchCandidateJobs` (and in the `assembleEmailTickets` extra-jobs
  pull, so a carried-over estimate alert cannot reappear).
- **Notes rule boundary:** change `evaluateMissingNotes` from "flag when
  `total_amount < notes_threshold_dollars`" to "flag when
  `total_amount <= notes_threshold_dollars`" (at-or-below). $0 stays included. Keep
  `notes_threshold_dollars` (185) as the editable config value.
- **Button checks unchanged:** keep SCHEDULE/OMW/START/FINISH/INVOICE/PAY with the existing
  `hcp_data` fallbacks. INVOICE/PAY stay gated to tickets with `total_amount > 0`.
- **First-send backlog guard:** `last_digest_sent_at` is stuck at 2026-05-12, so the
  `recentFinisherWindow` would span a month on first run and dump a huge backlog. When the
  digest is enabled, reset `last_digest_sent_at` (to null or ~24h ago) so the first email
  covers a normal window.

### 2. Delivery (dynamic FOM + Daniel)

- Add a `SECURITY DEFINER` RPC `public.supervisor_digest_recipients()` returning a deduped,
  lower-cased list of recipient emails:
  - every active user whose `user_roles.title = 'Field Operations Manager'`, joined to
    `auth.users.email`;
  - plus Daniel (`daniel@twinsgaragedoors.com`);
  - plus any non-empty addresses still present in
    `supervisor_digest_config.digest_recipient_email` (so the existing Email-recipients
    matrix UI stays additive and nothing silently drops).
- The edge function calls this RPC at send time instead of splitting the raw config string.
  The per-recipient send loop (one clean `to:` per address) is unchanged.
- **Enable** the digest (`enabled: true`) only after a validated dry-run.

### 3. Informative (light polish, layout kept)

- Surface the **responsible (attributed) tech** prominently on each ticket card rather than
  in the secondary gray line.
- Clarify the notes pill to read **"No notes"** with the existing dollar amount beside it,
  so the reason for the flag is self-evident.
- Keep the HCP deep-link, co-tech badge, severity sort, and summary banner as-is.

## Testing & rollout

- Unit tests:
  - estimate exclusion (an `Estimate` job with missing buttons/notes produces no alert);
  - notes boundary (`$185 → flag`, `$186 → no flag`, `$0 → flag`);
  - keep the existing `vendored-sync` test green (the rules file is vendored into the edge
    function and must stay byte-faithful to `src/lib/alerts/*`).
- **Dry-run** via the `x-dry-run: true` header against live data to preview the exact email
  before any real send.
- Ship on a branch → PR. Fully reversible: enabling/disabling is a config toggle; the RPC
  and code changes revert with the branch.

## Out of scope

- Full email redesign.
- Changing the set of button checks.
- Changing attribution rules (Charles co-tech rule stays).
- Revenue-recognition changes to the candidate set beyond excluding estimates.

## Open risks

- The notes rule now intentionally flags $0 warranty/callback/membership tickets with no
  notes. This is the user's explicit choice; volume will be higher than the "8 real
  signals" figure. If it proves noisy in practice, revisit the $0 handling later.
