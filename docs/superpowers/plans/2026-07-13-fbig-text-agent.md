# FB/IG Text Agent Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: implementation is delegated to the **Codex MCP tool (model "5.6 Sol Ultra")**, orchestrated by Claude. Steps use checkbox (`- [ ]`) syntax. Claude reviews Codex output per task, runs tests, and does live verification before any channel serves real traffic.

**Goal:** Let the Grok text-agent auto-reply to Facebook Messenger + Instagram DMs and follow up on FB/IG lead-ad leads by SMS, reusing the live text-agent's poll/pipeline/safety.

**Architecture:** Extend the existing per-minute `text-agent/poll` with (a) a discovery step that finds new FB/IG DM conversations in GHL and answers them through the same `handleInbound` pipeline, and (b) a lead-ad step that texts new FB/IG lead-ad contacts. Replies send via GHL `conversations/messages` with `type` `FB`/`IG`; DMs run a cold-inbound Grok prompt (no website-form context). All safety (kill switch, per-channel flags, human-takeover, caps, claim-CAS) is reused.

**Tech Stack:** Supabase edge functions (Deno + `deno test`), GHL v2 conversations API, xAI Grok, pg_cron. Spec: `docs/superpowers/specs/2026-07-13-fbig-text-agent-design.md`.

**Repo:** inner repo `~/twins-dashboard/twins-dash` (palpulla/twins-dash). Functions in `supabase/functions/`. `main` has the merged text-agent (PR #353).

---

### Task 0: Branch setup

**Files:** none (git).

- [ ] **Step 1:** From `main`, create the feature branch. Confirm main has the text-agent.
```bash
cd ~/twins-dashboard/twins-dash
git fetch origin main
git worktree add .worktrees/fbig -b feat/text-agent-fbig origin/main
cd .worktrees/fbig
test -f supabase/functions/text-agent/index.ts && grep -q 'route === "poll"' supabase/functions/text-agent/index.ts && echo "OK text-agent present" || echo "STOP: main missing text-agent"
```
Expected: `OK text-agent present`. If not, stop and report (main may not have PR #353).

- [ ] **Step 2:** Baseline tests green.
```bash
deno test -A supabase/functions/_shared/text-agent/
```
Expected: all pass (34 as of 2026-07-10).

---

### Task 1: Migration — allow `facebook`/`instagram` channels

**Files:** Create `supabase/migrations/20260713010000_text_agent_fbig_channels.sql`

- [ ] **Step 1: Write the migration** (drop + re-add the CHECK constraint; the current one is `check (channel in ('sms','chat'))`, an unnamed inline constraint — find its name first).
```sql
-- ai_text_threads.channel currently: check (channel in ('sms','chat'))
-- Widen to include facebook + instagram DM channels.
alter table public.ai_text_threads
  drop constraint if exists ai_text_threads_channel_check;
alter table public.ai_text_threads
  add constraint ai_text_threads_channel_check
  check (channel in ('sms','chat','facebook','instagram'));
```
- [ ] **Step 2:** Codex must confirm the existing constraint's real name via `information_schema` (Postgres auto-names inline CHECKs `<table>_<col>_check`, i.e. `ai_text_threads_channel_check` — verify) and adjust the `drop constraint` name if different.
- [ ] **Step 3:** Apply to jwrpj via the Supabase MCP `apply_migration` (or Management API), then record the version in `supabase_migrations.schema_migrations` (see [[reference_twins_dash_migration_history]] — 20260423* desync gotcha; INSERT the version if the tracker doesn't auto-record).
- [ ] **Step 4: Commit.**
```bash
git add supabase/migrations/20260713010000_text_agent_fbig_channels.sql
git commit -m "feat(text-agent): allow facebook/instagram thread channels"
```

---

### Task 2: `thread.ts` — channel-aware `pickNewInbound`

**Files:** Modify `supabase/functions/_shared/text-agent/thread.ts`; Test `supabase/functions/_shared/text-agent/thread_test.ts`

Currently `pickNewInbound` hardcodes `if (m.messageType && m.messageType !== "TYPE_SMS") continue;`. Generalize to an allowed-types set so FB/IG text messages are picked and non-text events (calls, reactions, story mentions) skipped.

- [ ] **Step 1: Write failing tests** (append to thread_test.ts):
```ts
Deno.test("pickNewInbound: FB channel accepts TYPE_FACEBOOK, skips non-text", () => {
  const msgs = [
    { id: "s1", direction: "inbound", body: "story reaction", messageType: "TYPE_ACTIVITY", dateAdded: "2026-07-13T10:06:00Z" },
    { id: "f1", direction: "inbound", body: "do you fix springs?", messageType: "TYPE_FACEBOOK", dateAdded: "2026-07-13T10:05:00Z" },
  ];
  assertEquals(pickNewInbound(msgs, null, ["TYPE_FACEBOOK"]), { body: "do you fix springs?", msgId: "f1" });
});
Deno.test("pickNewInbound: IG channel accepts TYPE_INSTAGRAM", () => {
  const msgs = [{ id: "i1", direction: "inbound", body: "hi", messageType: "TYPE_INSTAGRAM", dateAdded: "2026-07-13T10:05:00Z" }];
  assertEquals(pickNewInbound(msgs, null, ["TYPE_INSTAGRAM"]), { body: "hi", msgId: "i1" });
});
Deno.test("pickNewInbound: default (no allowed arg) still SMS-only", () => {
  const msgs = [{ id: "m1", direction: "inbound", body: "yes", messageType: "TYPE_SMS", dateAdded: "2026-07-13T10:05:00Z" }];
  assertEquals(pickNewInbound(msgs, null), { body: "yes", msgId: "m1" });
});
```
- [ ] **Step 2:** Run `deno test -A supabase/functions/_shared/text-agent/thread_test.ts` → FAIL (arity/typing).
- [ ] **Step 3: Implement** — change the signature and the type filter:
```ts
export function pickNewInbound(
  msgs: ConvMessage[], lastClaimedId: string | null,
  allowedTypes: string[] = ["TYPE_SMS"],
): { body: string; msgId: string } | null {
  const allow = new Set(allowedTypes);
  const sorted = [...msgs].sort((a, b) => (b.dateAdded ?? "").localeCompare(a.dateAdded ?? ""));
  const fresh: ConvMessage[] = [];
  for (const m of sorted) {
    if (lastClaimedId && m.id === lastClaimedId) break;
    // Only text messages of the requested channel(s); skip calls/voicemails/reactions.
    if (m.messageType && !allow.has(m.messageType)) continue;
    if (m.direction === "inbound" && (m.body ?? "").trim()) fresh.push(m);
    if (fresh.length >= 5) break;
  }
  if (!fresh.length) return null;
  const body = fresh.slice().reverse().map((m) => (m.body ?? "").trim()).join("\n").slice(0, 2000);
  return { body, msgId: fresh[0].id };
}
```
- [ ] **Step 4:** Run tests → PASS (existing SMS tests still green via the default arg).
- [ ] **Step 5: Commit.** `git commit -am "feat(text-agent): channel-aware pickNewInbound"`

> **Codex must verify (live):** exact inbound `messageType` strings for Messenger vs Instagram DMs via the token-gated `debug-conv` route against a real FB/IG conversation (candidates `TYPE_FACEBOOK`/`TYPE_INSTAGRAM`). Update the allowed-types arrays used in Task 7 to the confirmed values.

---

### Task 3: `ghl-sms.ts` — channel send + conversation search + contact fetch

**Files:** Modify `supabase/functions/_shared/text-agent/ghl-sms.ts`; Test `..._test.ts`

- [ ] **Step 1:** Generalize `sendSms` into `sendChannelMessage`, keep `sendSms` as a wrapper (DRY — no caller churn):
```ts
export async function sendChannelMessage(a: { pit: string; contactId: string; message: string; type: "SMS" | "FB" | "IG"; fetchImpl?: typeof fetch }):
  Promise<{ ok: true; msgId: string | null } | { ok: false; status: number }> {
  const f = a.fetchImpl ?? fetch;
  try {
    const r = await f(`${GHL_BASE}/conversations/messages`, {
      method: "POST", headers: headers(a.pit),
      body: JSON.stringify({ type: a.type, contactId: a.contactId, message: a.message }),
      signal: AbortSignal.timeout(FETCH_TIMEOUT_MS),
    });
    if (!r.ok) { console.error(`GHL ${a.type} send`, r.status, (await r.text()).slice(0, 200)); return { ok: false, status: r.status }; }
    const d = await r.json().catch(() => ({})) as Record<string, unknown>;
    return { ok: true, msgId: (d.messageId ?? d.msgId ?? null) as string | null };
  } catch (e) { console.error(`GHL ${a.type} send threw`, e); return { ok: false, status: 0 }; }
}
export async function sendSms(a: { pit: string; contactId: string; message: string; fetchImpl?: typeof fetch }) {
  return sendChannelMessage({ ...a, type: "SMS" });
}
```
- [ ] **Step 2:** Add `searchSocialConversations({pit, locationId})` → returns conversations with `{id, contactId, type, phone, lastMessageDirection, lastMessageDate}` for `TYPE_FACEBOOK`/`TYPE_INSTAGRAM` (client-side filter — the `type` query param was found ignored server-side 2026-07-10). Fetch `GET /conversations/search?locationId=…&limit=100&sortBy=last_message_date&sort=desc`; map + filter by type client-side.
- [ ] **Step 3:** Add `fetchContact({pit, contactId})` → returns `{firstName, lastName, name, email, phone}` from `GET /contacts/{id}` (`.contact`), used for cold-DM context + lead-ad detection. Coerce with the existing `asStr`.
- [ ] **Step 4: Tests** (mock `fetchImpl`): `sendChannelMessage` posts `type:"FB"`/`"IG"` and returns the id; `sendSms` still posts `type:"SMS"`; `searchSocialConversations` keeps only FB/IG types; `fetchContact` parses `.contact`.
- [ ] **Step 5:** Run tests → PASS. **Commit.**

> **Codex must verify (live):** the exact `type` strings GHL accepts for Messenger vs Instagram sends (`"FB"`/`"IG"` per GHL docs — confirm with a single test send to Daniel's own test DM thread during supervised go-live, NOT to a real customer), and the conversation `type` values returned by search.

---

### Task 4: `instructions.ts` — cold-inbound DM prompt

**Files:** Modify `supabase/functions/_shared/text-agent/instructions.ts`; Test `..._test.ts`

Extend `LeadContext.channel` to `"sms" | "chat" | "facebook" | "instagram"` and add a `cold: boolean` (true for FB/IG DMs with no form context). Warm mode unchanged.

- [ ] **Step 1: Failing test:**
```ts
Deno.test("cold-inbound prompt: no form context, asks for name first", () => {
  const p = buildSystemPrompt({ channel: "facebook", cold: true, firstName: null, service: null, zip: null, message: null, page: null });
  assertStringIncludes(p, "Facebook");
  assertStringIncludes(p, "get their name");            // must instruct capturing the name
  assertStringIncludes(p, "submit_lead");
  assert(!p.includes("just submitted our website form"));// cold mode must not claim a form submission
});
Deno.test("warm SMS prompt unchanged references the form", () => {
  const p = buildSystemPrompt({ channel: "sms", cold: false, firstName: "Reg", service: "broken spring", zip: "53703", message: null, page: null });
  assertStringIncludes(p, "website form");
});
```
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3: Implement** — branch on `ctx.cold`. Cold prompt (channel-labelled), same tools/hard-rules as warm, but opens fresh:
```ts
export interface LeadContext {
  channel: "sms" | "chat" | "facebook" | "instagram";
  cold?: boolean;
  firstName: string | null; service: string | null; zip: string | null; message: string | null; page: string | null;
}
// ... inside buildSystemPrompt, when ctx.cold:
const channelName = ctx.channel === "facebook" ? "Facebook Messenger" : ctx.channel === "instagram" ? "Instagram" : ctx.channel;
// return a prompt that: greets briefly on `${channelName}`, states this is a new inbound message with NO prior details,
// JOB order: (1) understand the garage-door problem, (2) get their name, (3) street address + ZIP,
// (4) offer real windows via check_availability, (5) submit_lead mid-thread with the REAL name + email if given
// (never a placeholder; leave unknown fields null). Reuse the exact STYLE + HARD RULES + TOOLS blocks from the warm prompt (DRY: factor the shared blocks into consts).
```
Keep the shared STYLE/HARD-RULES/TOOLS text in module consts so warm + cold don't drift.
- [ ] **Step 4:** Run → PASS. **Commit.**

---

### Task 5: `leadads.ts` — detect FB/IG lead-ad contacts (pure)

**Files:** Create `supabase/functions/_shared/text-agent/leadads.ts`; Test `leadads_test.ts`

- [ ] **Step 1: Failing tests:**
```ts
import { isFbIgLeadAd } from "./leadads.ts";
Deno.test("detects facebook/instagram lead-ad source", () => {
  assertEquals(isFbIgLeadAd({ source: "Facebook Lead Ads", attributionSource: null }), true);
  assertEquals(isFbIgLeadAd({ source: null, attributionSource: "instagram" }), true);
  assertEquals(isFbIgLeadAd({ source: "Website LP", attributionSource: null }), false);
  assertEquals(isFbIgLeadAd({ source: null, attributionSource: null }), false);
});
```
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3: Implement:**
```ts
export function isFbIgLeadAd(c: { source?: string | null; attributionSource?: string | null; tags?: string[] }): boolean {
  const hay = `${c.source ?? ""} ${c.attributionSource ?? ""} ${(c.tags ?? []).join(" ")}`.toLowerCase();
  return /(facebook|instagram|fb lead|ig lead|lead ad)/.test(hay) && !hay.includes("website");
}
```
- [ ] **Step 4:** Run → PASS. **Commit.**

> **Codex must verify (live):** the actual `source`/`attributionSource`/`tags` values on a real FB/IG lead-ad contact (via `debug-contact`), and tighten the regex to the confirmed structured signal rather than free-text guessing.

---

### Task 6: `index.ts` — lead-less context + channel-aware send in `handleInbound`

**Files:** Modify `supabase/functions/text-agent/index.ts`

`handleInbound` currently fetches the lead from `lp_leads` by `t.lp_lead_id` and treats a missing lead as an orphan (mute). FB/IG DM threads have `lp_lead_id = null` by design — that must NOT be an orphan.

- [ ] **Step 1:** In `handleInbound`, when `t.lp_lead_id` is null AND `t.channel` is `facebook`/`instagram`/(sms cold), build a synthetic lead context from the GHL contact instead of the orphan path:
```ts
// after loading thread t, before the lp_leads fetch:
let lead: LeadRow;
if (t.lp_lead_id) {
  const { data } = await db.from("lp_leads").select("id, first_name, phone, message, page, utm, ghl_contact_id").eq("id", t.lp_lead_id).maybeSingle();
  if (!data) { /* existing orphan mute path */ }
  lead = data as LeadRow;
} else {
  const c = await fetchContact({ pit, contactId: inb.contactId });
  lead = { id: "", first_name: c?.firstName ?? null, phone: (t.phone as string | null) ?? c?.phone ?? null,
           message: null, page: t.channel as string, utm: {}, ghl_contact_id: inb.contactId } as LeadRow;
}
```
- [ ] **Step 2:** Make the reply send channel-aware. Replace the SMS-only `sendSms(...)` in `handleInbound` with:
```ts
const sendType = t.channel === "facebook" ? "FB" : t.channel === "instagram" ? "IG" : "SMS";
const sent = await sendChannelMessage({ pit, contactId: inb.contactId, message: reply, type: sendType });
```
- [ ] **Step 3:** `grokReply` / `agentTools` — pass `cold: !t.lp_lead_id` and the thread channel into `buildSystemPrompt`, so cold DMs use the Task-4 prompt. `submit_lead` already sets `name: args.name ?? lead.first_name` and `email: args.email ?? null` — no placeholder (satisfies the spec's real-name/email requirement; the cold prompt is what makes the model supply the real name).
- [ ] **Step 4:** `deno check` the function; run all `_shared` tests. **Commit.**

---

### Task 7: `index.ts` — poll: FB/IG thread iteration + discovery

**Files:** Modify `supabase/functions/text-agent/index.ts`

- [ ] **Step 1:** Extend the existing thread-iteration query in `/poll` from `.eq("channel","sms")` to `.in("channel", ["sms","facebook","instagram"])`. In the per-thread `pickNewInbound` call, pass the channel's allowed types: SMS→`["TYPE_SMS"]`, facebook→`["TYPE_FACEBOOK"]`, instagram→`["TYPE_INSTAGRAM"]` (confirmed strings from Task 2 verify).
- [ ] **Step 2:** Add a **discovery** step in `/poll` (gated by `TEXT_AGENT_FB_ENABLED`/`TEXT_AGENT_IG_ENABLED`): call `searchSocialConversations`, keep conversations with `lastMessageDirection` inbound and `lastMessageDate` within 24h; for each whose `contactId` has no active `ai_text_threads` row on that channel, insert a thread `{channel, status:'active', ghl_contact_id, phone, transcript:[], message_count:0, last_ghl_msg_id:null, lp_lead_id:null}` then run `handleInbound(db, {contactId, phone, body, msgId})` using the freshly `pickNewInbound`-ed message. Wrap per-conversation in try/catch so one failure doesn't abort the sweep; `log`/console the count acted.
- [ ] **Step 3:** Claim-CAS on `last_ghl_msg_id` already prevents the discovery step and the iteration step from double-answering the same inbound in the same tick.
- [ ] **Step 4:** `deno check`; redeploy to a scratch state is NOT needed yet. **Commit.**

> **Codex must verify (live):** that a newly created FB thread + `handleInbound` posts a reply visible in Dunzo Conversations, using Daniel's supervised test DM (Task 9), before enabling for real traffic.

---

### Task 8: `index.ts` — poll: lead-ad SMS follow-up + per-channel flags

**Files:** Modify `supabase/functions/text-agent/index.ts`

- [ ] **Step 1:** Add per-channel env gates read at the top of `/poll`: `TEXT_AGENT_FB_ENABLED`, `TEXT_AGENT_IG_ENABLED`, `TEXT_AGENT_LEADSMS_ENABLED` (each `=== "true"` to enable; **default OFF**). The global `TEXT_AGENT_ENABLED=false` still overrides everything.
- [ ] **Step 2:** Add a **lead-ad** step (gated by `TEXT_AGENT_LEADSMS_ENABLED`): query GHL for recently-created contacts (or scan recent conversations' contacts), filter with `isFbIgLeadAd` + has phone + within a recency window (e.g. 2h) + no existing `ai_text_threads` row. For each: create a `channel:'sms'` thread, send ONE opener via `sendSms` (opener text: acknowledge their request + `Reply STOP to opt out.`), store the sent msg id. The normal SMS iteration then handles replies.
- [ ] **Step 3:** Opener helper (reuse `openerFor` shape but generic, since there is no `utm.service`): e.g. `"Hi{name}, Twins Garage Doors here — thanks for reaching out on Facebook/Instagram. Want me to check our next open arrival windows? Reply STOP to opt out."`
- [ ] **Step 4:** `deno check`; all tests green. **Commit.**

> **Codex must verify (live):** the contact-listing endpoint/params for "recently created contacts" (GHL `GET /contacts/` supports query + `startAfterId`/date filters — confirm) and the exact lead-ad source signal (Task 5 verify).

---

### Task 9: Deploy + supervised go-live

**Files:** none (ops).

- [ ] **Step 1:** Deploy `text-agent` to jwrpj (`--no-verify-jwt`, bundling `_shared`). Set the three new secrets to `false` initially. Confirm `/poll?t=…` still returns `{ok, polled, acted}` and the cron (jobid 92) 200s.
- [ ] **Step 2:** Remove or keep-read-only the `debug-social` route added 2026-07-13 (token-gated read-only OK; note in change-log).
- [ ] **Step 3: Supervised FB:** set `TEXT_AGENT_FB_ENABLED=true`. Daniel DMs the Twins Page from another account → within ~1 min the agent replies in-thread. Verify: human-takeover (Ivory replies in Dunzo → AI mutes), and a full capture (`submit_lead` → GHL contact/HCP draft/emails with the REAL captured name, email null if not given). Then IG (`TEXT_AGENT_IG_ENABLED=true`) with an IG DM. Then lead-SMS (`TEXT_AGENT_LEADSMS_ENABLED=true`) with a test lead-ad contact.
- [ ] **Step 4:** Change-log entry (`~/twins-dashboard/docs/marketing/change-log.md`) with revert steps (disable the three flags / `TEXT_AGENT_ENABLED=false`). Update [[project_text_agent]] memory. Open PR for `feat/text-agent-fbig`; merge after Daniel's go.

---

## Self-Review

**Spec coverage:** DM auto-replies (Tasks 2,3,6,7) ✓; cold-DM brain mode (Task 4,6) ✓; lead-ad SMS follow-up (Task 5,8) ✓; channel data model (Task 1) ✓; FB/IG send (Task 3) ✓; 24h window (Task 7 discovery ≤24h + graceful send-fail Task 3) ✓; safety reuse + per-channel flags (Task 8) ✓; real name/email no placeholder (Task 6 Step 3) ✓; build on main independent of autobook (Task 0) ✓; the 4 live-verify open items are each attached to their task as "Codex must verify" ✓.

**Placeholders:** none — API-dependent values are explicit verify-then-implement steps with the method named, not silent TODOs.

**Type consistency:** `pickNewInbound(msgs, lastClaimedId, allowedTypes?)`, `sendChannelMessage({type})`, `LeadContext.channel|cold`, `isFbIgLeadAd`, `fetchContact`, `searchSocialConversations` used consistently across tasks.
