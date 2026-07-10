# AI text-agent — SMS go-live (supervised test, ~5 min)

Everything is built, deployed, and wired — including inbound replies. There is **no GHL workflow to configure**: the agent polls the Dunzo conversation for replies every minute (pg_cron job `text-agent-poll-1min` → `text-agent/poll`, token from vault). The GHL automation UI turned out to be un-automatable (cross-origin iframe), and polling is one less moving part anyway.

What that means: the full SMS loop is live-capable right now. The one remaining step is the spec's supervised test — you watch a real thread before trusting it with real leads.

## The supervised test (do this once)

1. On your phone (or a private window), open `twinsgaragedoors.com/madison-tune-up-lp/` and submit the form with your own name + cell. Pick **Text me now**.
2. Within seconds you get an opening text from (608) 888-8785 referencing your issue.
3. Reply a few times (replies are picked up within ~1 minute, the poll cadence). Confirm the agent:
   - offers real arrival windows (from the same availability source as the voice agent),
   - never quotes a firm price (free on-site estimate is THE pricing answer),
   - never claims to book — it captures and says Ivory/the office confirms,
   - submits the lead mid-conversation once details are confirmed (you and ivory@ get the capture email; HCP draft appears).
4. **Human-takeover test:** from Dunzo Conversations, send any manual text in that thread. The AI must go silent in that thread from then on.
5. **STOP test** (optional, from a second number or after unmuting): text `STOP`. GHL unsubscribes natively; the agent also mutes the thread.
6. Clean up: delete the test contact/thread in Dunzo if you like; tell me and I'll clear the DB rows.

If anything misbehaves, flip the kill switch and tell me what you saw.

## Kill switches (fastest first)

- `TEXT_AGENT_ENABLED=false` in Supabase → Edge Functions → Secrets: stops ALL sends (SMS opener, replies, chat) immediately. Chooser then shows the call CTA only.
- Pause just the reply loop: `select cron.unschedule('text-agent-poll-1min');` on jwrpj (I can run this on request).
- Per-thread: any human reply in Dunzo mutes that thread automatically; tag `ai-text-muted` does the same.

## After the test passes

- Say "go" and I'll merge [PR #353](https://github.com/palpulla/twins-dash/pull/353) to main.
- A/B readout (any time, now measurable end to end):
  `select variant, count(*) filter (where event='view') as views, count(*) filter (where event='submit') as submits from lp_form_events group by variant;`
- Optional cleanup: TEST GHL contacts dodLmd4NTcMXdrbmYXZT, 1JzDDK0629JH7YnGSnIS, VpQxsJJHkrJlagJSES6m.
