# Twins Garage Doors — xAI Voice Agent for After-Hours + Missed Calls — Design

**Status:** Approved design, ready for plan
**Author:** Claude (with Daniel)
**Date:** 2026-07-09
**Supersedes:** the Voice AI section (Section 2) of `2026-06-02-ghl-ai-agent-design.md`. That spec's philosophy, guardrails, and morning-confirm loop carry over unchanged; the engine changes from GHL-native Voice AI to the xAI Voice Agent. Conversation AI (web chat + SMS) from the June spec is untouched and remains a possible later phase off the same knowledge-base content.

## Problem

Twins has one live CSR (Ivory). After-hours calls ring out and daytime missed calls go to voicemail; hot callers (broken spring, stuck door) hang up and call a competitor. The June 2026 plan was GHL-native Voice AI, but it stalled and Daniel now holds an xAI Voice Agent API key and wants that engine answering instead.

## Goal

An xAI-hosted voice agent answers two classes of calls on the Twins line:

1. **After-hours** (outside CSR hours).
2. **Missed calls during hours** (ring-timeout when Ivory doesn't pick up).

The agent answers FAQs from a knowledge base of real facts, captures lead details, and pushes each capture into GHL tagged for Ivory's morning confirm. Capture-and-confirm only: the agent never books. HCP remains the source of truth for jobs.

## Decisions (settled 2026-07-09)

| Decision | Choice | Why |
|---|---|---|
| Engine | xAI Voice Agent Builder (hosted, no-code beta) | xAI hosts the fragile realtime-audio layer; KB, guardrails, webhook tools, observability, and a phone number included |
| Call coverage | After-hours + no-answer overflow | Humans first, AI as safety net; lowest risk to live daytime leads |
| Routing | GHL call forwarding to the agent's number | No SIP surgery on the GHL number; daytime behavior unchanged |
| Lead handoff | GHL contact + note + tag for Ivory | Same human checkpoint the June spec locked in; no SMS/email to anyone |
| Custom code | One Supabase edge function (capture webhook) | Everything else is configuration |
| Booking authority | Capture + human confirms | No double-booking; HCP stays source of truth |

## Architecture

```
Caller → Twins GHL number (rings Ivory as today)
           │ after-hours schedule OR no-answer timeout
           ▼
        GHL call forwarding → xAI agent's phone number
           ▼
        xAI Voice Agent (Builder: KB + guardrails + webhook tool)
           │ on completed capture
           ▼
        Supabase edge fn `voice-agent-capture` (jwrpj, token-gated)
           ▼
        GHL v2 API: find-or-create contact → note (summary + fields
        + VERIFY list) → tag `ai-captured-needs-confirm`
           ▼
        Ivory's morning smart list → she confirms + books in HCP
```

## Section 1 — Call routing (GHL side)

- The Twins inbound number keeps its current daytime behavior: rings Ivory.
- Two forwarding triggers, both to the xAI agent's phone number:
  - **Schedule:** outside business hours.
  - **Ring timeout:** during hours, when unanswered after 20 seconds (about 4 rings; adjustable at config time if Ivory needs longer).
- **Phase 0 verifications (gate the whole approach):**
  1. The Dunzo GHL number supports both scheduled and no-answer forwarding to an external number.
  2. Which number(s) actually carry inbound customer calls today (the phone map lists five; confirm the live one(s) before touching routing).
  3. Caller ID passes through the forwarding leg so the agent and the capture webhook see the caller's real number, not the GHL trunk's.
  4. Per-minute costs pinned: xAI voice pricing + the GHL forwarding leg (an outbound-minutes charge).

If verification 1 fails, the fallback is xAI's direct-SIP option for existing numbers, evaluated as a spec amendment; nothing else in this design changes.

## Section 2 — xAI agent configuration (Builder)

One agent, configured in the xAI Voice Agent Builder console. The API key is used there (and never pasted in chat).

- **Persona:** friendly, local, plain-spoken; short sentences; no corporate jargon. Adapted for speech from the June spec's bot instructions.
- **Greeting:** "Thanks for calling Twins Garage Doors. Our team is helping other customers right now, but I can take down what you need and have someone confirm your appointment first thing. What's going on with your garage door?"
- **Capture flow, one question at a time:**
  1. Name
  2. Service address, and residential or commercial
  3. What's wrong / what they need
  4. Best callback number (confirm against caller ID)
  5. Preferred day or time window
- **Hard rules (carried verbatim from the June spec):**
  1. Never invent a price, fee, financing term, or membership detail. Missing fact → "A technician will confirm exact pricing once we know what's going on with your door."
  2. Never promise a specific appointment time or same-day service. Collect the preferred window; a team member confirms the actual time.
  3. Unknown question → don't guess; capture info and promise follow-up.
  4. Emergencies (stuck-open door, car trapped, safety issue): tell them to call the main line directly, and still capture details.
- **Knowledge base:** built from Daniel's real facts only (see Section 5). Blank facts are omitted, never guessed.
- **Webhook tool:** `submit_lead` — fires at the end of a successful capture (and on partial captures where the caller hung up after giving at least a callback number). Payload in Section 3.
- **Portability:** the finished agent instructions + KB content are exported and committed to this repo as a master copy Twins owns.

## Section 3 — Capture webhook (the only custom code)

New edge function `voice-agent-capture` on jwrpj, following the repo's standard hardened pattern:

- **Auth:** `?t=<VOICE_AGENT_CAPTURE_TOKEN>` gate (same scheme as other token-gated fns); rejects without it.
- **Payload (strict-validated JSON):** `call_id`, `caller_phone`, `name`, `address`, `property_type` (residential/commercial), `problem`, `callback_phone`, `preferred_window`, `emergency` (bool), `uncertain_fields[]`, `summary`. Any field the agent didn't clearly hear arrives null, never guessed.
- **Dedupe:** by `call_id` (webhook retries must not double-write).
- **Audit:** insert every accepted payload into a new `voice_agent_captures` table. Silent observability only; no alerts, no emails, no pushes.
- **GHL writes** (v2 API, `services.leadconnectorhq.com`, existing private-integration token, location `iRUlbIBg7PzSfLrPiR2j`):
  1. Find-or-create contact by phone.
  2. Add one note: structured capture + summary + every `uncertain_fields` entry rendered as a "VERIFY ON CALLBACK" line.
  3. Apply tag `ai-captured-needs-confirm`.
- **Scope check (Phase 0):** confirm the existing PIT's scopes cover contact create/update, notes, and tags; add scopes in GHL settings if not.
- **No HCP writes. No SMS. No email.** The shelved call-intake pipeline stays shelved.
- **Failure mode:** if the fn errors, the caller experience is unaffected and the transcript still exists in xAI's dashboard as the fallback record. Failed payloads land in `voice_agent_captures` with an error status for silent review.

## Section 4 — Ivory's surface

A saved GHL smart list filtered on tag `ai-captured-needs-confirm`. Each morning Ivory calls each capture, confirms the real slot, books it in HCP, and clears the tag. Identical to the June spec's morning-confirm loop.

## Section 5 — Facts intake (Daniel supplies; agent can't rely on them until filled)

- [ ] Business hours (defines "after hours")
- [ ] Service area (Madison WI + radius/counties)
- [ ] Services offered
- [ ] Brands serviced / installed
- [ ] Service-call / diagnostic fee policy (exact $ or "waived with repair")
- [ ] Financing / warranty basics, if offered
- [ ] Anything the agent must never say

Until a value is provided, the agent's instructions force the safe fallback ("a technician will confirm that").

## Guardrails (load-bearing)

- **No fabricated operational data.** Real values from Section 5 only, or the agent defers to a human. Enforced in the agent instructions, not just by convention.
- **No outbound pinging.** Captures and summaries are reviewed inside GHL and the xAI dashboard; nothing texts or emails anyone automatically.
- **Customer-facing copy uses no em-dashes.**
- **HCP is the source of truth for jobs.** The agent never writes a booking, and this build never writes to HCP at all.
- **Secrets:** the xAI API key lives in the xAI console; edge-fn secrets (`VOICE_AGENT_CAPTURE_TOKEN`, existing GHL PIT) are set via CLI, never pasted in chat.

## Who executes

- **Claude:** authors agent instructions, greeting, KB body, webhook payload contract; builds and deploys the edge function, table, and token; writes the GHL click-through playbook (forwarding rules, tag, smart list).
- **Daniel:** supplies Section 5 facts; configures the xAI Builder console (or screen-shares it); approves go-live.
- **Daniel or Aman:** clicks through the GHL forwarding + smart-list setup per the playbook.

## Success criteria

1. A test call to the Twins number after hours reaches the xAI agent, which answers a known FAQ correctly.
2. A test service request produces: all five fields captured, a GHL contact with the note and `ai-captured-needs-confirm` tag, and a row in `voice_agent_captures`.
3. On a pricing question with no KB answer, the agent defers to a human instead of inventing a number.
4. A daytime unanswered call forwards to the agent after the ring timeout; an answered call never does.
5. Caller ID of the real caller appears in the capture.
6. Ivory has a single saved smart list of AI-captured leads.
7. Twins holds an exported master copy of the agent instructions + KB.

## Rollout

Phase 0 verifications → facts intake → agent built in Builder → edge fn deployed → GHL forwarding configured → test calls (success criteria above) → **1-week supervised window** (Daniel/Ivory skim xAI transcripts + GHL captures each morning; tune KB and instructions from real calls) → trust it.

## Amendment 2026-07-09 (Daniel, explicit): HCP drafts + email notifications

Supersedes the "no HCP writes" and "no outbound pinging" items below for THIS pipeline only. The shelved call-intake poller stays shelved; its proven modules are reused inside the voice-agent webhook.

1. **HCP draft creation.** When a capture is a real service request (problem present AND street + city + zip present), the webhook creates an UNSCHEDULED HCP draft: dedupe by phone first (mirror + live-HCP backstop; existing customers never duplicated), then customer → address → unscheduled job → note. Resumable: per-step HCP ids persist on the capture row so retries never double-create. Message-only or address-less captures stay GHL-note-only. The agent still never books; Ivory confirms and schedules the draft.
2. **Email notification on every capture** to daniel@twinsgaragedoors.com and ivory@twinsgaragedoors.com via the repo's existing Resend pipeline: first/last name, email, phone, address, issue description, preferred window, emergency flag, uncertainty list, the agent's conversation recap (xAI exposes no post-call transcript API, so the agent submits a detailed recap; the email links to the full transcript in the xAI console), plus GHL contact and HCP draft links. Ivory's live surface remains the Dunzo desktop app (tag + smart list); email is the record.
3. **New capture fields:** caller email (optional, spelled back) and address split into street/city/state/zip (the agent structures what it heard; state defaults to WI and is flagged VERIFY when defaulted).
4. Email/HCP failures never fail the webhook response; they are recorded on the audit row for silent review. GHL write failures still return 500 (xAI retries).

## Amendment 2 (2026-07-09, Daniel, explicit): availability windows + recording emails

1. **Availability tool.** New token-gated edge fn `voice-agent-availability` on jwrpj reads the synced `jobs` schedule (HCP mirror, timezone America/Chicago) and returns the next open arrival windows. Business rules (Daniel 2026-07-09): Mon-Fri windows 8-10am, 9am-12pm, 11am-2pm, 2-5pm; Saturday windows 8-10am, 10am-12pm, 12-2pm offered ONLY on the guaranteed second Saturday of each month (other Saturdays are TBD and never offered). Capacity: a window is full at 2 scheduled jobs (2 techs, one job each per window); a day is full at 8. The overflow tech is Ivory's manual call, never auto-offered. The agent's `check_availability` tool offers 2-3 open windows; the caller's pick goes into preferred_window; the agent still never books and still says a team member confirms; drafts stay unscheduled. Conservative counting: a job whose scheduled start falls inside two overlapping windows counts against both.
2. **Recording follow-up email.** Production calls flow through GHL forwarding, so GHL records the full call. A cron (every 5 min) scans captures with status 'created' missing a recording email, finds the call recording on the capture's GHL contact conversation (recordings lag the call by minutes; 2h retry budget then give up with a recorded error), and sends a second email to the same two recipients with the audio attached (skip attachment above ~20MB, link instead). Direct calls to the agent number bypass GHL and only have the xAI-console copy (30-day retention, playable + downloadable there).

## Out of scope

- Direct calendar booking or any GHL → HCP sync.
- HCP writes beyond the Amendment above (call-intake pipeline stays shelved).
- Daytime first-ring call handling (agent is overflow only).
- Website chat + SMS (Conversation AI); a later phase can reuse the same KB content.
- Custom transcript pipelines into twins-dash.
