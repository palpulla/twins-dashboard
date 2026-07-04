# Task 11 design note — weekly booked-job → Google Ads offline upload

Date: 2026-07-04. Prereqs verified against customer 7171993484 via `ads-audit`:
`acceptedCustomerDataTerms: true`, `enhancedConversionsForLeadsEnabled: true`, currency USD.

## What ships (v1)

1. **One new conversion action** `Booked Job (HCP)` — `UPLOAD_CLICKS`, category
   `CONVERTED_LEAD`, counting `ONE_PER_CLICK`, **`primaryForGoal: false`**
   (observation only — the conversion column was just cleaned per change-log G4;
   nothing here feeds bidding). Default value $0 (a booked job has no earned
   revenue yet; we do not fabricate values). 90-day click lookback.
2. **New edge function `offline-conversions-weekly`** on jwrpj. Weekly pg_cron,
   Friday 10:07 UTC (~5am CT), after the payroll week closes Thursday night.

## Mechanics

- **Window:** previous complete payroll week, Fri 00:00 → Thu 24:00 in
  America/Chicago (`weekStartsOn: 5`, same as payroll). Manual body param
  `{ "weeksBack": N }` (1–12) replays older weeks for backfill.
- **Selection:** `jobs` rows with `created_at` in the window, `job_type <> 'Estimate'`
  (estimates are opportunities, not booked jobs — established rule), and at least
  one contact identifier in `hcp_data->customer` (email or any phone).
- **Channel:** every selected job's `lead_source` is normalized with the same
  alias table as `src/lib/lead-source-normalization.ts` (ported constant, no new
  heuristics) and the per-channel split is recorded in the run log. **All booked
  jobs are uploaded, not just ones HCP labels "Google Ads".** Rationale: HCP
  `lead_source` is exactly the attribution we know is unreliable (CAP §1);
  enhanced conversions for leads works by Google matching hashed identifiers to
  its *own* click records — a job that never saw an ad click simply doesn't
  count. Match decisions stay deterministic on Google's side; we never label.
  Filtering to `lead_source = "Google Ads"` would have uploaded 0 jobs in the
  last 60 days (no raw value normalizes to Google Ads) and defeated the point.
- **Identity hashing (Google spec):** email → trim, lowercase, strip dots in the
  local part for gmail/googlemail, SHA-256. Phone → digits only, `+1` E.164,
  SHA-256. Sent as `userIdentifiers` with `userIdentifierSource: FIRST_PARTY`.
  Raw PII never leaves the function; nothing lands in the repo or logs.
- **Upload:** Data Manager API `POST datamanager.googleapis.com/v1/events:ingest`
  with a `GOOGLE_ADS` destination pointing at the conversion action. No GCLIDs —
  identifier-only events are the documented enhanced-conversions-for-leads path.
  (First attempt used Google Ads API `uploadClickConversions`; the account is
  rejected with `CUSTOMER_NOT_ALLOWLISTED_FOR_THIS_FEATURE` on v23 **and** v22 —
  Google closed that service to new integrations in 2026 and routes everyone to
  Data Manager. No developer token needed on the new path.)
- **Idempotency:** `transactionId = jobs.id` (uuid). Google Ads dedupes events
  with the same transactionId within a conversion action, so re-runs and
  backfill overlaps can't double-count.
- **Timestamps:** `conversionDateTime = jobs.created_at` rendered with its real
  America/Chicago UTC offset (`yyyy-MM-dd HH:mm:ss-05:00`).
- **Gate:** same `is_valid_cron_call(p_secret)` RPC used by the other cron
  functions, header `x-cron-secret`.
- **Run log:** one row per run in new table `offline_conversion_uploads`
  (window, selected, uploaded, accepted, duplicates, error payload, channel
  split). Silent observability — no emails/alerts, per standing rule. The
  standing behavior + conversion-action creation are logged in
  `docs/marketing/change-log.md`; per-run history lives in this table.

## Status 2026-07-04 (evening) — LIVE, first upload accepted

The OAuth unblock below was completed 2026-07-04: Data Manager API enabled on
GCP project `twins-dashboard-marketing`, refresh token re-minted with both
scopes (Daniel approved the consent), jwrpj secret rotated. One code fix was
required after all — the GOOGLE_ADS destination rejects events missing
`event_source`, so the function now sends `eventSource: "WEB"` per the docs
(twins-dash commit `ef41cd7` on `feat/ads-audit-fn`, deployed). Verified:
probe shows both scopes; validateOnly clean; real `{"weeksBack":1}` run
accepted 26/26 for window 2026-06-26 → 2026-07-03 (run-log row `status: ok`);
`sync-google-ads` still works. The weekly cron needs no further attention.

## Status 2026-07-04 (morning, superseded) — built + scheduled, first accepted upload blocked on OAuth scope

Everything above is deployed and verified except the final Google handoff. The
Data Manager API requires the `https://www.googleapis.com/auth/datamanager`
OAuth scope; the shared `GOOGLE_ADS_REFRESH_TOKEN` was minted with `adwords`
only, so the live run fails with `ACCESS_TOKEN_SCOPE_INSUFFICIENT` (empirically
confirmed; recorded in the run log). Unblock (one-time, ~5 min, needs Daniel's
Google login):

1. In Google Cloud console, enable **Data Manager API** on the project that
   owns OAuth client `76441850488-0sv7rle1jljrs5cucl123idmaaiidqhl.apps.googleusercontent.com`.
2. Re-run the OAuth consent for that client requesting BOTH scopes
   (`adwords` + `datamanager`, `access_type=offline&prompt=consent`) and set the
   new refresh token: `supabase secrets set GOOGLE_ADS_REFRESH_TOKEN=...` on
   jwrpj. Both scopes on one token keeps sync-google-ads/ads-audit working.
3. Verify: POST the function with `{"probe": true}` (should list both scopes),
   then `{"validateOnly": true}`, then a real run with
   `{"weeksBack": N}` sized to cover any weeks missed while blocked.

No code changes needed — the function self-heals once the secret carries the
scope. The weekly cron stays enabled meanwhile; failed runs are visible in
`offline_conversion_uploads`.

## Deliberately out of scope

- Meta offline events (blocked on Meta re-auth).
- Making the action PRIMARY or wiring tCPA to it — Daniel/Legit5 decision after
  ~2 weeks of observation data.
- Call-only leads with no email/phone in HCP (nothing to hash).
