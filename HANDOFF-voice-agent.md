# HANDOFF: xAI Voice Agent (Ashley) — pick up here

Start-of-session context for continuing the Twins voice agent work. Full history lives in auto-memory (`project_xai_voice_agent.md` — read it first, it has every gotcha) plus the spec (`docs/superpowers/specs/2026-07-09-xai-voice-agent-design.md` + 2 amendments) and the master copy of the agent brain (`twins-dash/docs/voice-agent/xai-agent-config.md`).

## What is LIVE right now (2026-07-10)

- Agent "Twins Garage Doors Scheduler" (persona: **Ashley**) in Daniel's xAI console, answering (608) 924-5743. Instructions **v9** published (Ashley persona, residential-sectional-only scope, GoodLeap+Wisetack 0% financing line, 911/DIY-danger/privacy/no-spam-leads rules, Spanish on, submit_lead fires MID-CALL at step 8, never end_call before submit_lead).
- Edge fns on jwrpj, all `verify_jwt=false`, gated by VOICE_AGENT_CAPTURE_TOKEN (Bearer or ?t=; local copy `~/.twins-voice-agent-token`): `voice-agent-capture` (GHL contact+note+tag, HCP unscheduled draft when problem+street+city+zip, email to daniel@+ivory@), `voice-agent-availability` (real windows from jobs mirror, span-overlap counting, per-day tech capacity, service-area by zip incl. Milwaukee, confirm_eta), `voice-agent-recording-email` (cron every 5 min, armed, attaches GHL call WAV ~5-10 min post-call — only for GHL-forwarded calls).
- GHL: TEST number **608-889-3255** ("Thank You Card") forwards to the agent (team ring first, external hop = agent, voicemail backup). Verified live: chain works, out-of-area zip handling works. Everything merged to main (PR #349).

## Immediate next steps

1. Daniel does ONE clean test call to 608-889-3255 as a Madison customer (zip 53703-ish, problem, callback number, pick a window). Expect: mid-call email to daniel@+ivory@, GHL contact+tag, HCP draft (only with full address), recording email ~5-10 min later. Call from a phone OTHER than his cell (his cell is in the ring group — calling from it skips the human stage instantly, which confused a prior test).
2. If clean → flip **Main Number (608-888-8785)**: Settings > Phone System > Edit Configuration > Call Forwarding > External Phone Number: 916-712-3699 → 608-924-5743 (keep team ring, 20s timeout, Voicemail backup). Rollback = revert that one field. Daniel's cell keeps ringing via the Team Member stage (his profile has the 916 number; confirmed there is a phone-device icon on his chip).
3. Create Ivory's smart list: Contacts > filter tag `ai-captured-needs-confirm` > save as "AI captured - needs confirm".
4. Later: apply same forwarding to the other tracking numbers; start the 1-week supervised window (checklist in `twins-dash/docs/voice-agent/ghl-forwarding-setup.md`).

## Loaded-gun gotchas (memory has more)

- xAI console: instruction drafts are LOST on any reload; a Publish click can silently no-op. Type → Publish immediately → verify the "Changes published" toast EVERY time. Sync every published change back to xai-agent-config.md.
- Never put pipeline-critical tool calls after end_call in the instructions (the v7/v8 regression).
- Debugging "no email": check `voice_agent_captures` on jwrpj first (no row = submit_lead never fired; check xAI Conversations tab + edge logs for 403s), then email_sent_at/email_error, then Resend dashboard, then Gmail `in:anywhere subject:"New lead"`.
- Secrets: never in chat/transcript; stage on clipboard via `pbcopy < ~/.twins-voice-agent-token`, Daniel pastes into console Token fields himself.
- Daniel keeps training Ashley by test-calling and reporting; instruction changes publish independently of all plumbing.

## Open threads (non-blocking)

- KY job cluster (~70 Mount Sterling jobs) sits in the jobs mirror; excluded from service area; ask Daniel about it someday.
- preferred_window occasionally arrives null despite a chosen window; watch during supervised week, tighten tool description if it repeats.
- Optional later: direct-to-cell transfer tool for business-hours emergencies; explicit zip list from Daniel if he wants to override the history-derived footprint.
