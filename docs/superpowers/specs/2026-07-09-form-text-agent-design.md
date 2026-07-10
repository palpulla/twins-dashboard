# Website forms v2 + AI text agent + chat widget recolor — design

Date: 2026-07-09. Approved by Daniel in session (options: A+C chooser, scope C, wizard B with A/B test, xAI Grok API).
Builds on the Legit5 separation work shipped earlier today (change-log L1–L9): all four website forms already post to `lp-lead-intake` on jwrpj, and the Dunzo chat widget (66b654c1e70da57b4d7e70ba) is embedded on both sites via WPCode snippets 7152/6773.

## Goal

1. Higher-converting lead forms (multi-step wizard, A/B tested against the current single-step).
2. Instant AI follow-up after any form submit: the lead chooses **Text me** (SMS conversation) or **Chat now** (on-page chat). Same brain as the xAI voice agent: capture-and-confirm, real availability windows, never books, Ivory confirms.
3. Chat widget recolored to the old Legit5 look (orange bubble, gold header, mascot avatar).

## Scope

- Forms: `/wi/contact-us/` native form, `/madison-garage-door-repair-lp/`, `/madison-tune-up-lp/` (custom HTML forms) get wizard + A/B + chooser. Main `/contact-us/` (Gravity Form 1) keeps its fields; gains the SMS-consent line and the post-submit chooser on its thank-you flow.
- Out of scope: `/go/*` funnels (Legit5's, untouched), the voice agent itself (untouched), HCP booking (never), any Google/Meta ads changes.

## Non-negotiable guardrails (inherited from voice agent v8 + house rules)

- Capture-and-confirm only. The agent offers real arrival windows but NEVER books; every job is confirmed by Ivory/Daniel. No HCP writes from the text agent beyond what `voice-agent-capture` already does (unscheduled draft).
- No payment info, ever. Admits being an AI if asked. No fabricated prices/facts: free on-site estimate + exact price before work starts is THE pricing answer.
- Lead safety first: form capture into `lp_leads` + GHL happens before any AI involvement. AI failure can never lose a lead (fallback: one static "got it, we'll call you shortly" SMS).
- Human takeover: any human outbound in the Dunzo thread mutes the AI for that thread. Tag `ai-text-muted` also mutes manually.
- submit_lead fires mid-conversation once details are confirmed, never gated behind conversation end (v8 lesson).
- Supervised go-live: AI texting starts only after Daniel watches test threads and says go.

## Part 1 — Form wizard + A/B test

**Variant A (control):** current single-step form, unchanged except the consent line.

**Variant B (wizard),** same navy/yellow sticker styling:
- Step 1 — "What's going on with your door?" tap-chips: Broken spring / Door won't open / Opener problem / New door quote / Something else.
- Step 2 — name, phone, ZIP, optional details textarea (pre-seeded from chip, e.g. "Broken spring —"), email (optional), consent line, submit. Progress indicator, back link.
- Chip choice + ZIP ride along in the payload (`message` prefix + new optional `service` and `zip` fields accepted by `lp-lead-intake`; unknown fields are already tolerated, stored in message/utm).

**Assignment:** client-side 50/50, persisted in localStorage (`twins_form_variant`), so a returning visitor sees the same variant. Variant stamped into `lp_leads.utm` jsonb (`{"form_variant":"B"}`).

**Measurement:** new `lp_form_events` table on jwrpj: `id, created_at, page, variant, event ('view'|'start'|'submit'), session_id`. The form JS sends a beacon on render, first interaction, and submit. Conversion per variant = submits/views; no third-party tool. RLS: insert-only via `lp-lead-intake` (new `/event` route or separate tiny fn), no public reads.

**Consent copy (all four forms, both variants):** "By submitting, you agree Twins Garage Doors may call or text this number about your request. Msg/data rates may apply. Reply STOP to opt out." Stored with the lead (`utm.consent: true` + timestamp).

## Part 2 — Post-submit chooser + text agent

**Chooser UI:** replaces the static success message on the 3 custom forms; on the main Gravity Form it renders on the thank-you page. Handoff mechanism for the GF path: snippet 7165 generates a `chooser_token` (uuid), includes it in the lp-lead-intake payload (`utm.chooser_token`) and sets it as a 10-minute cookie; a WPCode snippet on /thank-you/ reads the cookie and passes the token to `text-agent`, which resolves the lead by token from `lp_leads`. Copy: "Got it. Want an answer right now?" → buttons **Text me** / **Chat now**, plus "or call (608) 888-8785". Choosing nothing is fine; the normal pipeline already ran.

**New edge function `text-agent` (jwrpj), token-gated like the voice fns (Bearer or ?t=):**
- Engine: xAI Chat Completions API (fast Grok model, model id in config), tools: `check_availability` (calls existing `voice-agent-availability`), `submit_lead` (POSTs to existing `voice-agent-capture` with source `text-agent`; same emails to daniel@+ivory@, GHL contact/note/tag, HCP-draft rules, dedupe by thread id).
- Instructions: voice v8 playbook adapted for text: 1–2 short sentences, no spell-backs (text is visible), no filler lines, same collect order (issue → address/ZIP → windows → callback details → confirm once → submit_lead mid-thread), out-of-area script, "other callers" message-taking path, never end before submit_lead when a lead is in play.
- State: `ai_text_threads` table: `id, created_at, updated_at, channel ('sms'|'chat'), lp_lead_id, ghl_contact_id, phone, status ('active'|'muted'|'done'), muted_reason, message_count, transcript jsonb, last_ghl_msg_id`. Hard caps: 30 AI messages/thread, 10/hour; dedupe inbound by GHL message id; idempotent opening send.

**SMS leg:**
- Opening text sent from the main number (608) 888-8785 via GHL API (one-number strategy; thread fully visible in Dunzo Conversations). Example: "Hi {first}, Twins Garage Doors here — got your note about the broken spring. Want me to check the next open arrival windows?"
- Inbound replies: GHL Workflow ("Customer Replied", channel SMS) → webhook action → `text-agent` → Grok → outbound via GHL API. The workflow is the one piece living in GHL; it's dumb on purpose (fire webhook, nothing else). STOP/unsubscribe is handled by GHL natively; the fn also treats it as mute.
- Takeover: webhook also fires on outbound messages; any outbound in the thread NOT authored by the agent (compare against our own sent ids) → status 'muted'. Tag `ai-text-muted` on the contact does the same.
- After-hours: works 24/7 (lead initiated the contact); confirm-call ETA wording reuses the availability fn's server-computed `confirm_eta` (never lets the model guess the clock).

**Chat leg:**
- Minimal self-contained panel (same styling family as the forms) that swaps in after "Chat now". Talks straight to `text-agent` (`channel:'chat'`, session id in localStorage). Simple request/response (no streaming needed for 1–2 sentence replies).
- On submit_lead or window close, transcript is written to the GHL contact as a note. If the visitor abandons chat, the lead is already captured anyway.

**Failure modes:** Grok error/timeout → SMS: one static fallback text; chat: "call us" card with the phone number. GHL send failure → logged in thread row, no retry storm (max 2 retries). Nothing blocks `lp-lead-intake`'s existing behavior.

**Secrets/config:** `XAI_API_KEY` added by Daniel directly in Supabase edge secrets (never pasted in chat). GHL PIT already present. New fn ships with `verify_jwt=false` + token gate, matching the voice fns, config.toml entry included (bulk-redeploy protection).

**Costs (order of magnitude):** Grok fast model ≈ $0.001–0.01 per reply; GHL SMS ≈ $0.0079/segment. Negligible at current lead volume.

## Part 3 — Chat widget recolor (Legit5 look)

Dunzo widget 66b654c1e70da57b4d7e70ba, measured live from Legit5's actual widget (loaded old widget id 69f8fbd6 on a scratch page):
- Chat bubble: **#E9900F** (orange), white icon.
- Header: gradient **270deg #FBBD04 → #C88A00**; if the builder only accepts a solid, use **#E2A402** (gradient midpoint).
- Send button: #E9900F. Visitor message bubble: #E9900F. Backgrounds stay white/light.
- Avatar: re-enable "Allow avatar image", set to the mascot head (`twins-mascot-avatar.jpg`, already in Dunzo Media Storage — Legit5 used the same mascot-head treatment).
- Copy unchanged. This supersedes today's navy branding (change-log L1) at Daniel's request; change-log gets a new entry.

## Testing / rollout

1. Widget recolor: visual check + one live chat message → Dunzo (same test as today).
2. Forms: e2e submit per page per variant → `lp_leads` row + variant stamp + `lp_form_events` rows.
3. Chat leg: full scripted conversation on a staging page → windows offered match `voice-agent-availability`, capture lands (GHL note/tags/emails), transcript note written.
4. SMS leg (supervised): test submit with Daniel's cell → opening text, several turns, human-takeover test (Ivory replies, AI mutes), STOP test. Daniel says go before it's on for real leads.
5. A/B readout: after ~2 weeks, conversion per variant from `lp_form_events` (added to Monday marketing brief manually at first).

## Rollback

- Forms: WPCode/page revisions restore the single-step form; chooser is additive.
- Text agent: disable the GHL webhook + set fn env `TEXT_AGENT_ENABLED=false` (chooser then shows call CTA only).
- Widget: recolor back to navy values recorded in change-log L1.
