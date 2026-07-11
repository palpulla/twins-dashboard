# Text agent: auto-booking + lead-source notifications + conversation behavior

**Date:** 2026-07-11
**Status:** Approved design (Daniel), ready for implementation via Codex.
**Owner surfaces:** Grok text agent (SMS + website chat) and the shared voice-agent capture/notification pipeline.

## Context

The Grok text agent went live 2026-07-10 (PR #353). A real test lead exposed a chain of problems:

1. The admin's first text notification had **no context** — no indication of where the lead came from.
2. The agent's replies are **curt** (its instructions cap it at "1-2 short sentences").
3. On scheduling it said **"a human will reach out to confirm"**, prompting Daniel to jump into the thread.
4. It then **re-asked for information the customer had already submitted on the form** → customer annoyed.
5. It **let the lead go without collecting everything** (no full address / phone).
6. The admin **email arrived with the issue but no address, no phone** (fields rendered `(not given)`).

Root causes found in code:

- `_shared/voice-agent/payload.ts` (`VoiceCapture`) has **no `source`/`channel` field**.
- `_shared/voice-agent/email.ts` (`buildLeadEmail`) has Phone + Address rows but they render `(not given)` when null; **no source anywhere**; transcript pointer is hardcoded to the old xAI voice console (stale for text + the new GHL voice agent).
- `_shared/text-agent/instructions.ts`: line "1-2 short sentences" (curt), "tell them when the confirming call comes" (human-confirm language), "You never book … Ivory or Daniel confirms every job by phone."
- `_shared/voice-agent/hcp.ts`: `POST /jobs` **deliberately omits the `schedule` object** to create unscheduled drafts. HCP supports scheduled jobs; sending a `schedule` object books them.

## Scope

In scope: the **Grok text agent** (SMS + chat) and the **shared capture/notification pipeline** (`voice-agent-capture` fn and its `_shared/voice-agent/*` modules) that both the text agent and the (old) voice agent call.

Out of scope for this effort (but must inherit the same rules later): the **new GHL-native voice "Ashley" agent** built 2026-07-10. Her brain/capture wiring is a separate follow-up; this spec's persona, source rules, and booking rules are the reference she will adopt.

## Decisions (approved)

- **Option 2 — the agent books the job for real.** When it has all required info and the customer picks a window `check_availability` shows open, it creates a **scheduled HCP job**. This **reverses the prior load-bearing rule** "agent never writes booked jobs / a human confirms every job" ([[feedback_no_fabricated_operational_data]] / the capture-and-confirm design). Daniel opted in knowingly.
- **Notify admin as usual** — every booked/captured lead still fires the email **and** a text to admin, now with source + full info + booking status.
- **Source labels** use the format `"{Marketing source} via {contact method}"` (e.g., "Facebook Ad via lead form", "Google Ads via call"), derived from **structured signal only, never AI-guessed** ([[feedback_no_heuristic_classifiers_for_business_rules]]).

## Requirements

### R1. Lead source on the capture

Add `source: string | null` to `VoiceCapture` (payload.ts). It is populated **server-side from structured lead context**, not by the AI model:

- **Text/website leads:** derive from the form's `utm` jsonb + channel already present in the text-agent lead context.
- **Voice/call leads (existing xAI path + future GHL Ashley):** derive from the **tracking number's Friendly Name** (the number the customer dialed).
- No signal → `"Unknown source via {method}"`. Never invent.

Deterministic mapping (implement as a pure, unit-tested function `deriveSource({...}) => string`):

Marketing source (first match wins):
| Signal | Marketing source |
|---|---|
| `utm_source` contains `facebook`/`fb`/`meta` (or FB lead form) | Facebook Ad |
| `utm_source` contains `google` + `utm_medium` cpc/paid | Google Ads |
| tracking number = Google Ads Call Extension (608-336-4300) | Google Ads |
| tracking number = GBP WI (608-447-5366) | Google Business Profile |
| tracking number = Facebook Ad WI (608-688-9109) | Facebook Ad |
| tracking number = Yard Sign WI (608-597-3193) | Yard sign |
| tracking number = Door Hanger (608-413-3447 / 608-413-4425) | Door hanger |
| tracking number = Biz Card / Maurice Business Card (608-602-8802 / 608-583-9835) | Business card |
| tracking number = Door Sticker WI (608-447-5781) | Door sticker |
| tracking number = Thank You Card (608-889-3255) | Thank you card |
| tracking number = Postcard/Toll Free (833-307-0631 / 833-833-2010) | Postcard |
| organic / no utm / direct | Website (organic) |

Contact method: `lead form` (website form submit), `call` (voice), `text` (SMS), `chat` (on-page chat). Label = `"{source} via {method}"`.

Store the number→source map in one place (reuse/extend `reference_twins_web_properties_phone_map` knowledge; keep it in a small config module, not scattered). Confirm labels with Daniel already done; treat as the source of truth.

### R2. Merge form-known fields into the capture (fix "(not given)")

Known lead fields (name, phone, email, zip, service/problem) that arrive from the **form/lead context** must be merged into the capture **server-side** before email/HCP/note/text — independent of whether the AI echoed them into `submit_lead`. The AI's collected/corrected values take precedence when present; otherwise fall back to the form-known values. Result: no field is blank just because the model didn't repeat it.

### R3. HCP auto-book with safe fallback

Extend `createHcpDraft` (rename/extend to support a scheduled path, e.g. `createHcpJob({ schedule })`):

- When the customer selected a window that `check_availability` returned as open **and** required fields are present → POST `/jobs` **with a `schedule` object** (start/end derived from the chosen window; America/Chicago; match HCP's expected shape — verify against HCP API and existing timestamp handling).
- **On any failure or detected conflict** → fall back to the current unscheduled draft, and the customer is told a person will confirm the exact time. Record `booked: true|false` on the capture row and in notifications.
- Never over-promise: only tell the customer "you're booked for [window]" once the scheduled job actually succeeds.
- Keep resumability / in-flight claim semantics already in the fn.

### R4. Admin notifications (email + text), with context + consistency

- **Email** (`buildLeadEmail`): subject and a prominent intro line lead with **`Lead source: {label}`** and **booking status** (`Booked: Tue 2–5pm` or `Draft — needs scheduling`). Keep all existing rows; they now carry the merged full info from R2. Replace the stale xAI transcript pointer with the correct per-channel pointer (Dunzo conversation for text; correct console for voice).
- **Admin text alert:** send a concise SMS to the configured **admin number(s)** on every capture: source + name + issue + phone + booked window (or draft). Recipient number(s) in config/settings (default: Daniel's cell; optionally Ivory) — not hardcoded in logic. Gated by the existing enable flag; must fail closed (never block the capture).
- **Consistency:** one canonical capture object (post-R2 merge) drives email, the GHL note (`buildCaptureNote`), and the admin text — same fields everywhere.

### R5. Conversation behavior (text-agent instructions)

Rewrite `_shared/text-agent/instructions.ts` so the agent:

- Replies **warm and natural** — remove the "1-2 short sentences" cap; concise but human, no corporate filler, no emoji storms.
- **Never re-asks what the form already provided** (name, phone, zip, service). Treat form fields as known context.
- **Collects everything before it lets go** — pushes for full street address + phone + issue; does not `submit_lead` a half-lead. If the customer refuses a field after a reasonable ask, note it and proceed.
- **Available window → confirms directly** and continues ("great, you're set for [window]"). The "a human will reach out" line appears **only** on a real scheduling conflict or a booking failure — never on the happy path.
- Reflects the new booking authority (it can book), while keeping honest-about-being-AI and safety guardrails.

Carry the same persona/rules into the future GHL Ashley (noted, not built here).

## Failure handling & safety

- Capture-first: any AI/tool/HCP/email/SMS failure must degrade gracefully; the lead is never lost, and the customer is never told something false. Booking failure → draft + "office will confirm the time."
- Kill switch: existing `TEXT_AGENT_ENABLED` (all sends) and the poll cron remain the off switches. Admin-SMS respects the same gating.
- No secrets in chat; tokens/keys stay in vault/env.

## Testing

Deno unit tests for each changed module:
- `deriveSource` mapping (utm variants, each tracking number, unknown).
- Capture payload with `source`; merge-form-fields precedence.
- `buildLeadEmail` / note / admin-text: source + booking status + no `(not given)` when form fields present.
- HCP scheduled-job body shape + fallback-to-draft on error.
- Instruction snapshot/contract test if applicable.

## Delivery

- Codex builds on a **branch with tests green**; does **not** deploy to live jwrpj.
- Review, then a **supervised go-live**: prove on one live text (from a non-owner phone) before it's on for real leads — booked HCP job verified, admin email+text with correct source, no re-asking, warm tone.

## Open items to resolve during build

- Exact HCP `schedule` object shape and timezone handling (verify against HCP API + existing code; test carefully — real jobs land in the live HCP).
- Admin-SMS recipient list + whether Ivory is included (default Daniel's cell; confirm at go-live).
- Whether the existing Dunzo "New Text Message!" workflow SMS should be suppressed to avoid double-notifying (evaluate; the deliberate admin alert supersedes it).
