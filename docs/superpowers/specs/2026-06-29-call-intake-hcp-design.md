# Call-Intake to HCP Pipeline — Design Spec

**Date:** 2026-06-29
**Owner:** Daniel (Twins Garage Doors)
**Status:** Approved design, ready for implementation plan

## Problem

When Daniel takes a call while driving or otherwise away from Housecall Pro (HCP),
the lead details (name, contact, address, the problem) are only captured on the GHL
(Dunzo) call recording and risk being lost. The CSR (Ivory) and Daniel normally enter
callers into HCP live, so any automated capture must not create duplicate tickets when
a human already handled the call.

Goal: automatically transcribe new-lead call recordings from GHL, extract the caller's
details, and create a draft (unscheduled) HCP job for a human to confirm and schedule,
without ever inventing data and without clobbering live human entry.

## Scope decisions (locked)

- **Autonomy: hybrid.** Auto-create the HCP customer + an unscheduled draft job so nothing
  is lost; a human (Ivory) verifies and schedules. No auto-booking, no auto-scheduling.
- **Which calls:** new inbound calls from numbers that are NOT already an HCP customer.
  The unknown-vs-known check happens at processing time (after a grace delay), against
  live HCP data, because the CSR may have just added the customer.
- **Review surface:** Ivory works the lead inside HCP. The draft unscheduled job carries
  the transcript, recording link, and extracted fields (with uncertainty flags) in its notes.
- **Grace window:** wait 10 minutes after a call before processing, so live human entry
  lands in HCP first. Configurable.
- **Dedupe on match:** if the caller's phone matches an existing HCP customer at processing
  time, do NOT create a duplicate. (No notification is sent in this case; see Notifications.)
- **Missing/ambiguous fields:** never invent a value. Fill only what was clearly stated,
  flag everything missing or uncertain in the job notes, and still create the draft. If
  extraction is near-empty, still create a minimal draft so the recording/lead is not lost.
- **Notifications: SMS to Daniel only.** No email to anyone. The SMS fires only on weekends
  (all day) and weekdays from 5:00pm ET onward. Outside that window the pipeline still runs
  silently and the HCP draft is still created.
- **Reversible:** branch + PR workflow; nothing destructive; config-driven toggles.

## Out of scope

- Auto-scheduling / auto-booking onto any calendar.
- Setting HCP Job Type (not writable via the public API).
- Notifying Ivory by email or SMS (she works leads from the HCP draft directly).
- Processing calls from existing HCP customers (skipped after dedupe).
- A twins-dash review tab (HCP is the review surface; can be added later if volume grows).

## Architecture

A Supabase edge-function pipeline on the live **jwrpj** project
(`jwrpjuqaynownxaoeayi`, twins-dash-prod), matching every other Twins automation
(CSR EOD, estimate tracker): edge functions + pg_cron + existing HCP key + Twilio/GHL SMS.

```
GHL inbound call (recorded)
   -> GHL Workflow: "Call Details / Incoming" trigger + Custom Webhook action
       -> [edge fn] call-intake-webhook         (insert pending row, idempotent on messageId)
   ... 10-minute grace window ...
   -> [pg_cron every 5 min] -> [edge fn] call-intake-process (due rows):
        1. fetch recording from GHL (by messageId)
        2. Deepgram Nova-3 transcription (diarized)
        3. Claude extraction -> strict JSON (+ per-field "stated?" flags)
        4. dedupe: caller phone vs jwrpj HCP customer mirror
        5a. match found  -> mark done, no ticket, no SMS
        5b. no match     -> HCP: create customer -> address -> unscheduled job -> note
                          -> if within SMS window: send SMS to Daniel
        6. update call_intake row (status, ids, error)
```

### 1. Trigger & capture (GHL)

- A GHL Workflow (configured in the Dunzo sub-account UI by Daniel/Aman; content supplied
  by Claude) uses a **Call Details** trigger with Call Direction = Incoming, plus a minimum
  call-duration filter (skip sub-~20s junk / no-content voicemails).
- A **Custom Webhook** action POSTs to `call-intake-webhook` with at least: GHL `messageId`,
  `conversationId`, `locationId`, `contactId`, caller phone number, call timestamp, and the
  recording URL if present in the trigger payload.
- We do NOT rely on GHL's native transcript (accuracy on spelled emails/addresses is
  undocumented and weak). The recording is the source of truth.
- **Confirmed (GHL docs):** Call Details / Call Status triggers, Custom Webhook action,
  `InboundMessage` webhook with `messageType: "CALL"` and recording URL in `attachments`,
  recording endpoint `GET /conversations/messages/{messageId}/locations/{locationId}/recording`
  (returns audio bytes), Private Integration token auth, scope `conversations/message.readonly`.
- **Prerequisite to verify before build:** inbound calls route through the GHL/LC number with
  call recording ENABLED, including when forwarded to Daniel's cell while driving. If a call
  Daniel answers on his cell is not routed/recorded through GHL, there is no recording to pull.

### 2. Grace window & queue

- `call-intake-webhook` only inserts a `call_intake` row (status `pending`,
  `process_after = now() + grace_minutes`). Idempotent on GHL `messageId` so replayed
  webhooks never double-insert.
- A **pg_cron** job runs every 5 minutes and invokes `call-intake-process`, which selects
  rows where `status = 'pending' AND process_after <= now()` and processes each.
- Default `grace_minutes = 10` (config-editable).

### 3. Transcription (Deepgram)

- `call-intake-process` fetches the recording from GHL (via the recording endpoint by
  `messageId`, using the Private Integration token), then submits it to **Deepgram Nova-3**
  prerecorded with: `diarize=true`, `smart_format=true`, `numerals=true`, and keyterm
  prompting for recurring Twins terms (brand/opener names, common local street names).
- Cost ~1 cent per call. Deepgram can pull a remote URL or take uploaded bytes; choose based
  on whether the GHL recording URL is publicly fetchable by Deepgram (if auth-gated, fetch
  bytes from GHL then upload to Deepgram).
- **Accuracy caveat (research-backed):** there is no reliable public benchmark for any vendor
  on spelled-out emails / digit strings / street numbers over 8kHz phone audio. This is why
  the "flag uncertain, never invent" rule (section 4) is the core safety mechanism. The plan
  must include a spot-check of Deepgram output against a handful of real Twins recordings
  before trusting it in production.

### 4. Extraction (Claude)

- Claude (Anthropic, matching Daniel's stack) reads the diarized transcript and returns strict
  JSON:
  - `first_name`, `last_name`, `email`, `address` { `street`, `street_line_2`, `city`,
    `state`, `zip` }, `phone`, `issue_description`
  - For each field, a boolean `stated` (was it clearly said by the caller, verbatim) and an
    optional short `note` (e.g. "spelled letters unclear").
- The prompt forbids inferring, guessing, or normalizing beyond what was clearly stated.
  Anything not clearly present returns `null` with `stated = false`. This encodes the
  no-fabrication / no-heuristic-classifier rules.
- The caller phone from GHL caller-ID is authoritative for the `phone` field and for dedupe
  (more reliable than a spoken number).

### 5. Dedupe

- Match the caller's phone number (normalized to digits) against the **jwrpj HCP customer
  mirror** (`hcp_data` customer phone fields), which is kept near-live by the HCP webhooks.
- Within the 10-minute grace window, a customer the CSR just entered will have synced via the
  HCP webhook, so the mirror is the reliable dedupe source. Optionally back-stop with a direct
  HCP customer search if the mirror is suspected stale.
- **Match found** -> set the row `status = 'matched'`, create no ticket, send no SMS.
- **No match** -> proceed to HCP write.

### 6. HCP write (no-match path only)

Sequence of public-API calls (base `https://api.housecallpro.com`, `Authorization: Token …`):

1. `POST /customers` — `first_name`, `last_name`, plus `email` / `mobile_number` when stated.
2. `POST /customers/{id}/addresses` — `street`, `city`, `state`, `zip` (only if a usable
   address was captured; otherwise skip and flag in notes).
3. `POST /jobs` — `customer_id` (required), `address_id` (when available), `description` =
   issue summary, `tags` e.g. `["AI-captured", "from call", "needs confirm"]`, **no schedule
   object** (yields an unscheduled / needs-scheduling job), no employee assignment, no job_type.
4. `POST /jobs/{job_id}/notes` — `note` containing: the full transcript, the recording link,
   the extracted fields, and an explicit "VERIFY ON CALLBACK" list of any field with
   `stated = false` or a flagged note.

- **Confidence (research):** customer create, separate address POST, job create with optional
  schedule (unscheduled supported), and job notes are all high-confidence from official docs +
  a working community OpenAPI mirror + an open-source MCP client. Medium-confidence items to
  confirm with ONE live test call before wiring production: exact required-vs-optional customer
  fields, flat-vs-nested `schedule` shape, and whether `address_id` is strictly required.
- Verify the existing edge-function HCP key has customer/job WRITE scope (memory notes edge
  functions use `Token` + `HOUSECALL_PRO_API_KEY`; reads are confirmed, writes must be checked).

### 7. Notification (SMS to Daniel)

- After a successful no-match HCP draft creation, if the current time is within the SMS window,
  send ONE short SMS to Daniel's cell via the existing GHL/Twilio number:
  e.g. `📞 Lead captured (608-555-1234) — draft in HCP: <job link>`.
- **SMS window:** weekends (all day) OR weekday hour >= 17:00 **America/New_York (ET)**.
  Evaluate the gate in ET regardless of server timezone.
- This is a text to Daniel, not to a client, so the "one number to clients" rule is untouched.
- No email is sent to anyone. Ivory works the lead from the HCP draft.
- Matched-customer cases and within-business-hours captures produce no SMS (silent; HCP draft
  is the only surface).

### 8. Data model & idempotency

`call_intake` table (jwrpj), keyed/unique on GHL `messageId`:

| column | purpose |
|---|---|
| `id` | pk |
| `ghl_message_id` | unique, idempotency key |
| `ghl_conversation_id`, `ghl_contact_id`, `ghl_location_id` | GHL refs |
| `caller_phone` | normalized digits, authoritative |
| `call_at` | call timestamp |
| `process_after` | now()+grace; cron gate |
| `status` | `pending` / `processing` / `matched` / `created` / `skipped` / `error` |
| `recording_url` | source recording |
| `transcript` | Deepgram output |
| `extracted` | jsonb (fields + stated flags) |
| `dedupe_match` | matched HCP customer id, if any |
| `hcp_customer_id`, `hcp_address_id`, `hcp_job_id` | created refs |
| `sms_sent_at` | nullable |
| `error` | last error for the silent audit trail |
| timestamps | created/updated |

The table is the silent observability surface (no health-alert emails/pings, per Daniel's rule).

### 9. Config & secrets

- A `call_intake_config` row (id=1): `enabled` toggle, `grace_minutes`, `min_call_seconds`,
  `sms_recipient`, `sms_window` definition, and Deepgram/extraction tunables.
- Secrets on jwrpj: GHL Private Integration token + `locationId`, `DEEPGRAM_API_KEY`,
  `ANTHROPIC_API_KEY`, HCP write key, Twilio/GHL SMS credentials (or reuse the existing GHL
  send-SMS path). Verify which already exist; add the rest.
- Edge functions follow the existing pattern: `verify_jwt=false`, gated by a `?t=<token>`
  query param.

### 10. Failure handling

- Recording not yet ready at processing time -> retry with backoff (re-queue, bump
  `process_after`), give up after N attempts and set `status='error'` (visible in the table).
- Deepgram / Claude / HCP errors -> capture in `error`, leave row for retry; never partially
  create (create customer+address+job+note as a unit; on a mid-sequence failure, record what
  was created so a retry can resume rather than duplicate).
- HCP 429 -> backoff (no published rate limit; assume modest).

## Vendor decisions

- **Transcription:** Deepgram Nova-3 (`diarize` + `smart_format` + `numerals` + keyterm).
  Reasons: pulls by URL, cheapest accurate tier (~1c/call), best documented telephony/keyterm
  feature set. Runner-up AssemblyAI (Slam-1 / Universal-3) if Deepgram underperforms on the
  real-call spot-check.
- **Extraction:** Claude (Anthropic), strict-JSON with per-field stated flags.
- **Notification:** SMS via existing GHL/Twilio number, ET-gated.
- **Host:** Supabase edge functions + pg_cron on jwrpj.

## Open verification items (do in the plan, before production)

1. Confirm GHL records inbound calls (incl. cell-forwarded) for the relevant number(s).
2. One live HCP test call: confirm customer/address/job(unscheduled)/note shapes and the
   write scope of the edge-function HCP key.
3. Deepgram accuracy spot-check on real Twins recordings for emails/addresses/digits.
4. Confirm the SMS send path (existing GHL workflow SMS vs direct Twilio creds).
```
