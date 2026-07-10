# HANDOFF: xAI Voice Agent (Ashley) — pick up here

Start-of-session context for continuing the Twins voice agent work. Full history lives in auto-memory (`project_xai_voice_agent.md` — read it first, it has every gotcha) plus the spec (`docs/superpowers/specs/2026-07-09-xai-voice-agent-design.md` + 2 amendments) and the master copy of the agent brain (`twins-dash/docs/voice-agent/xai-agent-config.md`).

## What is LIVE right now (2026-07-10, afternoon — FORWARDING IS LIVE FLEET-WIDE)

- Agent "Twins Garage Doors Scheduler" (persona: **Ashley**) in Daniel's xAI console, answering (608) 924-5743. Instructions **v9** published (Ashley persona, residential-sectional-only scope, GoodLeap+Wisetack 0% financing line, 911/DIY-danger/privacy/no-spam-leads rules, Spanish on, submit_lead fires MID-CALL at step 8, never end_call before submit_lead).
- Edge fns on jwrpj, all `verify_jwt=false`, gated by VOICE_AGENT_CAPTURE_TOKEN (Bearer or ?t=; local copy `~/.twins-voice-agent-token`): `voice-agent-capture`, `voice-agent-availability`, `voice-agent-recording-email` (cron every 5 min, active). Health-checked 2026-07-10: all three 403 without token (alive+gated), cron active, no edge desync.
- **GHL forwarding LIVE on ALL numbers (2026-07-10):** 17 numbers now have External Phone Number = 608-924-5743 with **15s incoming timeout**, Team Member ring intact (Daniel Joseph + Twins Garage Phone Line), Voicemail backup. Includes Main Number 608-888-8785. Two intentional exclusions: **859-440-2227 KY** (Daniel excluded 859 area code) and **608-420-3460 Charles Business Card** (forwards to Charles's cell 779-256-1288; wiring the agent would cut Charles out — confirm with Daniel someday).
- **Ivory's smart list created:** Contacts > "AI captured - needs confirm" (filter Tag = ai-captured-needs-confirm). 2 test-residue contacts in it (TEST GrokCheck, Tal Joseph).
- Everything merged to main (PR #349); playbook updated in `twins-dash/docs/voice-agent/ghl-forwarding-setup.md`.

## Immediate next steps

1. **Supervised week starts NOW** (checklist in ghl-forwarding-setup.md): daily skim of xAI Conversations tab, `voice_agent_captures` on jwrpj for error statuses, morning smart list vs emails.
2. Any missed call on ANY Twins number now reaches Ashley after 15s of human ring. Rollback per number = set External Phone Number back to 916-712-3699 (one field).
3. Watch preferred_window null-arrivals; tighten submit_lead tool description if it repeats.
4. Clean test-residue contacts from the smart list once real captures flow (TEST GrokCheck).

## Loaded-gun gotchas (memory has more)

- xAI console: instruction drafts are LOST on any reload; a Publish click can silently no-op. Type → Publish immediately → verify the "Changes published" toast EVERY time. Sync every published change back to xai-agent-config.md.
- Never put pipeline-critical tool calls after end_call in the instructions (the v7/v8 regression).
- Debugging "no email": check `voice_agent_captures` on jwrpj first (no row = submit_lead never fired; check xAI Conversations tab + edge logs for 403s), then email_sent_at/email_error, then Resend dashboard, then Gmail `in:anywhere subject:"New lead"`.
- Secrets: never in chat/transcript; stage on clipboard via `pbcopy < ~/.twins-voice-agent-token`, Daniel pastes into console Token fields himself.
- **GHL phone-config UI (2026-07-10):** the Edit Configuration modal frequently opens STALE (empty Friendly Name = data never hydrated). Clicking the Call Forwarding tab on a stale modal DISMISSES it, and saving a half-hydrated modal could wipe the team-ring config. Rule: after Edit Configuration, wait until Friendly Name is populated before touching anything; if stale, close (X) and reopen — 1-2 retries always hydrates. The post-save closing animation renders a ghost modal with unchecked/default fields; it's cosmetic, but it eats clicks aimed at the list behind it — wait ~2s after each save toast.
- GHL "Incoming Call Timeout" per-number is separate from Phone System > Voice > Voicemail "Incoming Call Timeout" (location-level slider, still 20s — left alone deliberately).

## Open threads (non-blocking)

- KY job cluster (~70 Mount Sterling jobs) sits in the jobs mirror; excluded from service area; KY number also excluded from agent forwarding; ask Daniel about it someday.
- Charles Business Card number: wire to agent or leave ringing Charles? Ask Daniel.
- preferred_window occasionally arrives null despite a chosen window; watch during supervised week.
- Optional later: direct-to-cell transfer tool for business-hours emergencies; explicit zip list from Daniel if he wants to override the history-derived footprint.
