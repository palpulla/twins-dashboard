# FB/IG text agent — Facebook Messenger + Instagram DMs and lead-ad follow-up

Date: 2026-07-13. Approved by Daniel in session (scope: both DM auto-replies AND lead-ad SMS follow-up).
Builds on the LIVE text-agent ([[project_text_agent]], PR #353 merged 2026-07-10). After today's Facebook reconnect (Twins Garage Doors Page + Instagram now linked to the Dunzo GHL location `iRUlbIBg7PzSfLrPiR2j` via Daniel's own FB account), FB/IG DMs land in the same Dunzo GHL Conversations inbox as SMS.

## Goal

Let the xAI (Grok) agent auto-respond to Facebook Messenger and Instagram DMs in-thread, and follow up on FB/IG lead-ad leads by SMS — reusing the existing text-agent brain, safety, and GHL send/poll plumbing, keeping every conversation in the Dunzo Conversations inbox.

## Context: what already exists (do not rebuild)

- **`text-agent` edge fn** on jwrpj (`/start`, `/chat`, `/ghl-webhook`, `/poll`, plus token-gated read-only `debug-*` routes). Grok tool-loop reuses `voice-agent-availability` (real arrival windows) and `voice-agent-capture` (GHL contact/note/tag + HCP draft + emails to daniel@ + ivory@).
- **`/poll` route** runs every minute (pg_cron `text-agent-poll-1min`, jobid 92, token from vault `text_agent_token`). It is **thread-driven**: it iterates existing `ai_text_threads` where `channel='sms' AND status='active'` (48h window, ≤20), and for each calls `findConversationId` → `fetchRecentMessages` → `pickNewInbound` (currently filters `messageType==='TYPE_SMS'`) → `handleInbound`.
- **`handleInbound(db, inb)`** is the shared inbound pipeline: claim-CAS dedupe on `last_ghl_msg_id`, STOP handling, **fail-closed human-takeover** (any non-automated outbound in the thread on/after thread start mutes the AI; automated GHL notification sends are ignored — see `detectHumanTakeover`), caps (30/thread, 10/hr via `canReply`), Grok reply, send.
- **`_shared/text-agent/`**: `thread.ts` (caps/dedupe/takeover + `pickNewInbound`), `ghl-sms.ts` (`sendSms`, `fetchRecentMessages` carrying id/direction/body/dateAdded/source/messageType, `findConversationId`), `grok.ts`, `instructions.ts`, `intake-extras.ts`. 34 deno tests.
- **`ai_text_threads`** columns: id, created_at, updated_at, channel (`'sms'|'chat'`), status (`'active'|'muted'|'done'`), muted_reason, message_count, transcript jsonb, sent_ghl_msg_ids, last_ghl_msg_id, lp_lead_id, ghl_contact_id, phone, chooser_token, chat_token, captured.
- **Kill switch** `TEXT_AGENT_ENABLED=false` gates ALL sends server-side.

## Scope

**In:** (1) FB Messenger + Instagram DM auto-replies in-thread; (2) cold-inbound brain mode (DMs arrive with no website-form context); (3) one-time opening SMS follow-up to new FB/IG lead-ad leads.

**Out:** direct Meta Graph API integration (GHL already receives the DMs); GHL "Customer Replied" workflow (that builder is an un-automatable cross-origin iframe — see [[project_text_agent]] gotcha; polling is the chosen transport); changing the Facebook/Instagram connection; touching the Legit5 Meta ad; the not-yet-deployed `feat/text-agent-autobook` branch (build independently of it).

## Architecture — extend the poll (chosen)

Rejected alternatives: **GHL workflow webhook** (un-automatable iframe, same reason SMS uses polling); **direct Meta Messenger Platform app** (large new surface — Meta app review, webhook verification, token rotation — duplicating what GHL already provides and pulling DMs out of the unified inbox). Extending the poll reuses the proven pipeline and keeps humans in the same Dunzo inbox with working takeover.

The FB/IG DM flow differs from SMS in one structural way: **SMS threads are created proactively** (chooser "Text me" → `/start` sends opener → thread row exists before any poll). **FB/IG DMs are inbound-first** — the customer messages the Page/IG and no `ai_text_threads` row exists yet. So the poll needs a **discovery** step in addition to the existing thread-iteration step.

### Component 1 — FB/IG conversation discovery (new)

Add to `/poll` (or a sibling `/poll-social` on the same cron; single route preferred for one cron hit):
- Query GHL `GET /conversations/search?locationId=…&limit=100` sorted by last message date desc; keep conversations whose `type` is `TYPE_FACEBOOK` or `TYPE_INSTAGRAM` AND `lastMessageDirection` is inbound AND `lastMessageDate` within a recency window (e.g. 24h, so we never try to answer a stale thread the 24h window has closed).
- For each such conversation, look up an `ai_text_threads` row by `ghl_contact_id` + channel (`facebook`/`instagram`). If none exists and the conversation has a fresh inbound: **create the thread** (channel set, status active, ghl_contact_id, phone if available, transcript `[]`, message_count 0, last_ghl_msg_id null) then run the SAME `handleInbound` pipeline against it (which claims the inbound msg id, checks takeover, caps, Grok, sends the reply via the FB/IG send below).
- If a thread already exists, it is picked up by the existing thread-iteration loop (extended to include `facebook`/`instagram` channels), so no double-processing. Claim-CAS on `last_ghl_msg_id` guards any overlap.

### Component 2 — FB/IG send (new)

Add `sendChannelMessage({pit, contactId, message, channel})` in `ghl-sms.ts` (or generalize `sendSms`) that POSTs `POST /conversations/messages` with `type: 'FB'` (Messenger) or `type: 'IG'` (Instagram). **Codex must verify the exact `type` strings and required body fields against the live GHL v2 API** (confirmed set includes `SMS`, `Email`, `WhatsApp`, `FB`, `IG`, `Live_Chat`, `Custom`; verify FB/IG accept `contactId` + `message`, and capture the returned message id for `sent_ghl_msg_ids`). On failure (e.g., Meta 24h window closed), degrade like SMS: log, no retry storm, thread stays put.

### Component 3 — cold-inbound brain mode (new instructions variant)

`instructions.ts` gains a cold-inbound variant used when a thread has no `lp_lead_id` (FB/IG DM). Same voice-agent v8 playbook adapted for a fresh contact: brief greeting, understand the garage-door problem, collect name → address/ZIP → offer real windows (`check_availability`) → confirm details → `submit_lead` mid-thread (so the lead lands in GHL + HCP draft + emails, same as every other channel). No firm price ever; free on-site estimate is THE pricing answer; admits being an AI if asked. Keep replies to 1–2 short sentences (DM etiquette). The existing warm mode (with lp_lead context) is unchanged.

### Component 4 — lead-ad SMS follow-up (new)

A `/poll-leads` step (same cron): find new GHL contacts created from FB/IG lead-ad forms that (a) have a phone, (b) have no existing `ai_text_threads` row, (c) are within a recency window. **Codex must confirm the detection signal** by inspecting a real FB/IG lead-ad contact — likely `attributionSource`/`source` (e.g. contains "facebook"/"instagram"/"lead ad") and/or a lead tag GHL applies; prefer a structured source field over free-text guessing. For each: create a `channel='sms'` thread and send ONE opening SMS via the existing SMS path, opener referencing their request + STOP opt-out, then the normal poll handles replies. Meta blocks DMing lead-ad leads (no open 24h window), so SMS is the only compliant proactive channel. If a lead form has no phone, skip (no channel to reach them proactively).

## Data model

- `ai_text_threads.channel` accepts `facebook` and `instagram` in addition to `sms`/`chat`. If the column is a CHECK/enum, migrate to add the values (migration file, applied to jwrpj, version recorded — note the schema_migrations desync gotcha [[reference_twins_dash_migration_history]]).
- No new tables. FB/IG threads reuse `ai_text_threads`; `lp_lead_id` is null for cold DMs.

## 24-hour messaging window + policy

- DM replies always occur within Meta's 24h window (the customer just messaged). The discovery window (≤24h recency) ensures the agent never tries to answer a thread whose window has closed.
- The agent NEVER proactively DMs a cold lead. Lead-ad follow-up is SMS only.
- If a DM send fails because the window closed mid-flight, degrade gracefully (log; the human sees the thread in Dunzo).

## Safety (all reused, no new model)

- `TEXT_AGENT_ENABLED=false` stops every channel. Add per-channel enable flags (`TEXT_AGENT_FB_ENABLED`, `TEXT_AGENT_IG_ENABLED`, `TEXT_AGENT_LEADSMS_ENABLED`, default the new ones OFF until supervised go-live) so channels can be turned on independently.
- Human-takeover mute, caps, claim-CAS dedupe, STOP handling: reused unchanged via `handleInbound`.
- The `debug-social` diagnostic route added this session should be removed or kept token-gated read-only per cleanup.

## Testing / rollout

1. Unit: deno tests for FB/IG `pickNewInbound` typing, discovery selection (type + direction + recency), cold-inbound instruction selection, lead-ad detection, per-channel gating. Keep the suite green (currently 34).
2. Supervised go-live (Daniel): turn on `TEXT_AGENT_FB_ENABLED` first, send a test DM to the Twins Page from another account → agent replies in-thread within ~1 min; verify human-takeover (Ivory replies → AI mutes) and that it captures a lead (`submit_lead` → GHL/HCP/emails). Then IG DMs. Then lead-ad SMS with a test lead. Daniel says go before each channel serves real traffic.
3. Verify via `debug-social` that FB/IG conversations are being read, and via Conversations inbox that replies post as the agent.

## Build / execution

- Branch off **main** (which has the merged text-agent, PR #353) as `feat/text-agent-fbig`. Keep independent of `feat/text-agent-autobook`.
- Implementation delegated to the **Codex MCP tool** (model per Daniel's "sol 5.6" directive — confirm exact model at handoff). Orchestrator (Claude) reviews Codex output, runs tests, and does the live verification before any channel is enabled for real traffic.

## Open items for Codex to verify against live GHL/Meta (do not assume)

1. Exact GHL `POST /conversations/messages` `type` strings + required fields for Messenger vs Instagram, and the returned message-id shape.
2. `conversations/search` filtering: whether `type` filter works server-side (this session found it ignored via the `type` query param — may need client-side filtering of the full page) and the exact `type` values (`TYPE_FACEBOOK`/`TYPE_INSTAGRAM`) + `lastMessageDirection` values.
3. The FB/IG lead-ad contact detection signal (source/attribution/tag) from a real lead-ad contact.
4. Whether `pickNewInbound`/`fetchRecentMessages` need any FB/IG-specific message-type handling (e.g., story replies, reactions) to avoid answering non-text events.
