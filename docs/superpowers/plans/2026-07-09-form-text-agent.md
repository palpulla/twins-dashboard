# Forms v2 + AI Text Agent + Widget Recolor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Multi-step lead-form wizard with 50/50 A/B testing on 3 pages, an instant AI follow-up (lead picks SMS or on-page chat, Grok brain with the voice agent's playbook), and the Dunzo chat widget recolored to the old Legit5 orange.

**Architecture:** One new edge function `text-agent` on jwrpj (Grok tool-loop; tools call the existing `voice-agent-availability` and `voice-agent-capture` fns over HTTP). Thread state in a new `ai_text_threads` table; A/B metrics in `lp_form_events`. WP side is two site-wide WPCode "forms engine" JS snippets (main + /wi) that upgrade existing plain-HTML forms into the wizard + chooser + chat panel. Spec: `docs/superpowers/specs/2026-07-09-form-text-agent-design.md`.

**Tech Stack:** Supabase edge functions (Deno + deno test), xAI Chat Completions API, GHL v2 API (PIT), WordPress via WPCode/REST (Chrome MCP), GHL builder UI.

**Repos/paths:** Code lives in the INNER repo `~/twins-dashboard/twins-dash` (palpulla/twins-dash). `lp-lead-intake` is NOT on main — it lives on branch `claude/legit5-separation` (deployed live). Docs/changelog live in the outer repo `~/twins-dashboard`.

**Env vars already present on jwrpj:** `GHL_PIT`, `GHL_LOCATION_ID`, `VOICE_AGENT_CAPTURE_TOKEN`, `RESEND_API_KEY`, `GHL_API_KEY_1` (v1, used by lp-lead-intake). **New:** `XAI_API_KEY` (Daniel pastes in dashboard), `XAI_MODEL` (default in code: `grok-4-fast`), `TEXT_AGENT_TOKEN`, `TEXT_AGENT_ENABLED`.

---

### Task 0: Branch setup

**Files:** none (git only)

- [ ] **Step 1: Create worktree + branch from main, merge the deployed lp-lead-intake branch**

```bash
cd ~/twins-dashboard/twins-dash
git fetch origin main claude/legit5-separation
git worktree add .worktrees/text-agent -b text-agent origin/main
cd .worktrees/text-agent
git merge --no-edit origin/claude/legit5-separation
```

Expected: merge commits cleanly (lp-lead-intake only adds files). If conflicts: stop and report.

- [ ] **Step 2: Verify baseline tests pass**

```bash
cd ~/twins-dashboard/twins-dash/.worktrees/text-agent
deno test -A supabase/functions/_shared/voice-agent/
```

Expected: all existing voice-agent tests PASS.

---

### Task 1: Migration — `ai_text_threads` + `lp_form_events`

**Files:**
- Create: `supabase/migrations/20260710010000_text_agent.sql`

- [ ] **Step 1: Write the migration**

```sql
-- Text agent conversation state + form A/B metrics (spec 2026-07-09-form-text-agent-design.md)
create table if not exists public.ai_text_threads (
  id uuid primary key default gen_random_uuid(),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  channel text not null check (channel in ('sms','chat')),
  lp_lead_id uuid references public.lp_leads(id),
  ghl_contact_id text,
  phone text,
  chooser_token text,
  chat_token text,
  status text not null default 'active' check (status in ('active','muted','done')),
  muted_reason text,
  message_count int not null default 0,
  captured boolean not null default false,
  transcript jsonb not null default '[]'::jsonb,
  sent_ghl_msg_ids jsonb not null default '[]'::jsonb,
  last_ghl_msg_id text
);
create index if not exists ai_text_threads_phone_idx on public.ai_text_threads (phone) where status = 'active';
create unique index if not exists ai_text_threads_chooser_idx on public.ai_text_threads (chooser_token, channel) where chooser_token is not null;
alter table public.ai_text_threads enable row level security; -- service-role only

create table if not exists public.lp_form_events (
  id bigint generated always as identity primary key,
  created_at timestamptz not null default now(),
  page text not null,
  variant text not null check (variant in ('A','B')),
  event text not null check (event in ('view','start','submit')),
  session_id text
);
create index if not exists lp_form_events_page_idx on public.lp_form_events (page, variant, event);
alter table public.lp_form_events enable row level security; -- inserts only via service role (edge fn)
```

- [ ] **Step 2: Apply via Management API and record migration version** (project jwrpj; migration history is manually maintained — see memory `reference_twins_dash_migration_history`)

```bash
TOKEN=$(cat ~/.supabase/access-token)
SQL=$(cat supabase/migrations/20260710010000_text_agent.sql | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))')
curl -s -X POST "https://api.supabase.com/v1/projects/jwrpjuqaynownxaoeayi/database/query" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d "{\"query\": $SQL}"
curl -s -X POST "https://api.supabase.com/v1/projects/jwrpjuqaynownxaoeayi/database/query" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"query": "insert into supabase_migrations.schema_migrations (version) values ('"'"'20260710010000'"'"') on conflict do nothing"}'
```

Expected: first call returns `[]`, second returns `[]`.

- [ ] **Step 3: Verify tables exist**

```bash
curl -s -X POST "https://api.supabase.com/v1/projects/jwrpjuqaynownxaoeayi/database/query" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"query": "select relname from pg_class where relname in ('"'"'ai_text_threads'"'"','"'"'lp_form_events'"'"')"}'
```

Expected: both names returned.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260710010000_text_agent.sql
git commit -m "feat(text-agent): ai_text_threads + lp_form_events tables"
```

---

### Task 2: lp-lead-intake — A/B beacon route + new fields

**Files:**
- Modify: `supabase/functions/lp-lead-intake/index.ts`
- Create: `supabase/functions/_shared/text-agent/intake-extras.ts`
- Test: `supabase/functions/_shared/text-agent/intake-extras_test.ts`

- [ ] **Step 1: Write failing tests for the extras parser**

```ts
// supabase/functions/_shared/text-agent/intake-extras_test.ts
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { parseFormEvent, extractIntakeExtras } from "./intake-extras.ts";

Deno.test("parseFormEvent accepts valid beacon", () => {
  const r = parseFormEvent({ event: "view", page: "/wi/contact-us/", variant: "B", session_id: "s1" });
  assertEquals(r, { ok: true, row: { event: "view", page: "/wi/contact-us/", variant: "B", session_id: "s1" } });
});

Deno.test("parseFormEvent rejects bad variant/event", () => {
  assertEquals(parseFormEvent({ event: "view", page: "/x/", variant: "C" }).ok, false);
  assertEquals(parseFormEvent({ event: "click", page: "/x/", variant: "A" }).ok, false);
  assertEquals(parseFormEvent({ event: "view", variant: "A" }).ok, false); // page required
});

Deno.test("extractIntakeExtras builds utm additions", () => {
  const extras = extractIntakeExtras({
    service: "Broken spring", zip: "53713",
    form_variant: "B", chooser_token: "11111111-2222-3333-4444-555555555555", consent: "true",
  });
  assertEquals(extras.utmExtra, {
    form_variant: "B",
    chooser_token: "11111111-2222-3333-4444-555555555555",
    consent: "true",
    service: "Broken spring",
    zip: "53713",
  });
  assertEquals(extras.messagePrefix, "Service: Broken spring | ZIP: 53713");
});

Deno.test("extractIntakeExtras drops junk tokens", () => {
  const extras = extractIntakeExtras({ chooser_token: "<script>", form_variant: "Z" });
  assertEquals(extras.utmExtra, {});
  assertEquals(extras.messagePrefix, null);
});
```

- [ ] **Step 2: Run to verify failure**

```bash
deno test -A supabase/functions/_shared/text-agent/intake-extras_test.ts
```

Expected: FAIL (module not found).

- [ ] **Step 3: Implement**

```ts
// supabase/functions/_shared/text-agent/intake-extras.ts
// Small parsers shared by lp-lead-intake for the forms-v2 additions:
// A/B beacon rows and the extra fields the wizard sends.
const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export type FormEventRow = { event: string; page: string; variant: string; session_id: string | null };
export type FormEventResult = { ok: true; row: FormEventRow } | { ok: false };

export function parseFormEvent(body: Record<string, unknown>): FormEventResult {
  const event = String(body.event ?? "");
  const page = String(body.page ?? "").slice(0, 200);
  const variant = String(body.variant ?? "");
  if (!["view", "start", "submit"].includes(event)) return { ok: false };
  if (!["A", "B"].includes(variant)) return { ok: false };
  if (!page) return { ok: false };
  const session = String(body.session_id ?? "").slice(0, 64) || null;
  return { ok: true, row: { event, page, variant, session_id: session } };
}

export function extractIntakeExtras(body: Record<string, string>) {
  const utmExtra: Record<string, string> = {};
  const variant = body["form_variant"];
  if (variant === "A" || variant === "B") utmExtra.form_variant = variant;
  const token = body["chooser_token"];
  if (token && UUID_RE.test(token)) utmExtra.chooser_token = token;
  if ((body["consent"] ?? "") === "true") utmExtra.consent = "true";
  const service = (body["service"] ?? "").trim().slice(0, 60);
  const zip = (body["zip"] ?? "").trim();
  if (service) utmExtra.service = service;
  if (/^\d{5}$/.test(zip)) utmExtra.zip = zip;
  const parts = [];
  if (utmExtra.service) parts.push(`Service: ${utmExtra.service}`);
  if (utmExtra.zip) parts.push(`ZIP: ${utmExtra.zip}`);
  return { utmExtra, messagePrefix: parts.length ? parts.join(" | ") : null };
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
deno test -A supabase/functions/_shared/text-agent/intake-extras_test.ts
```

- [ ] **Step 5: Wire into lp-lead-intake**

In `supabase/functions/lp-lead-intake/index.ts`:

(a) add import at top:
```ts
import { parseFormEvent, extractIntakeExtras } from "../_shared/text-agent/intake-extras.ts";
```

(b) right after `body` is parsed successfully (after the `catch` returning "unreadable body"), add the beacon route — beacons carry `event`, real submissions never do:
```ts
  // Forms-v2 A/B beacon: {event, page, variant, session_id}. Fire-and-forget.
  if (body["event"]) {
    const ev = parseFormEvent(body);
    if (ev.ok) {
      const sb = createClient(
        Deno.env.get("SUPABASE_URL")!,
        Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
      );
      const { error } = await sb.from("lp_form_events").insert(ev.row);
      if (error) console.error("lp_form_events insert:", error.message);
    }
    return Response.json({ ok: true }, { headers: cors });
  }
```

(c) after the `utm` loop (`for (const k of UTM_KEYS) {...}`), add:
```ts
  const extras = extractIntakeExtras(body);
  Object.assign(utm, extras.utmExtra);
```

(d) in the `.insert({...})` call, change the `message` line to:
```ts
    message: extras.messagePrefix ? `${extras.messagePrefix}${message ? "\n" + message : ""}` : message,
```

- [ ] **Step 6: Deploy + smoke test**

```bash
supabase functions deploy lp-lead-intake --project-ref jwrpjuqaynownxaoeayi --no-verify-jwt
curl -s -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/lp-lead-intake" \
  -H 'content-type: application/json' -H 'origin: https://twinsgaragedoors.com' \
  -d '{"event":"view","page":"/plan-smoke/","variant":"A","session_id":"plan-smoke"}'
curl -s -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/lp-lead-intake" \
  -H 'content-type: application/json' -H 'origin: https://twinsgaragedoors.com' \
  -d '{"dryRun":"true","name":"Plan Smoke","phone":"6085551212","form_variant":"B","service":"Broken spring","zip":"53713"}'
```

Expected: both return `{"ok":true...}`; then verify the beacon row:
```bash
TOKEN=$(cat ~/.supabase/access-token)
curl -s -X POST "https://api.supabase.com/v1/projects/jwrpjuqaynownxaoeayi/database/query" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"query": "select page, variant, event from lp_form_events where session_id = '"'"'plan-smoke'"'"'"}'
```
Expected: 1 row `/plan-smoke/ A view`. Delete it after:
```bash
curl -s -X POST "https://api.supabase.com/v1/projects/jwrpjuqaynownxaoeayi/database/query" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"query": "delete from lp_form_events where session_id = '"'"'plan-smoke'"'"'"}'
```

- [ ] **Step 7: Commit**

```bash
git add supabase/functions/_shared/text-agent/ supabase/functions/lp-lead-intake/index.ts
git commit -m "feat(text-agent): A/B beacon route + wizard fields in lp-lead-intake"
```

---

### Task 3: `_shared/text-agent/thread.ts` — thread state machine

**Files:**
- Create: `supabase/functions/_shared/text-agent/thread.ts`
- Test: `supabase/functions/_shared/text-agent/thread_test.ts`

Pure logic, no I/O: caps, mute decisions, transcript append, dedupe.

- [ ] **Step 1: Failing tests**

```ts
// supabase/functions/_shared/text-agent/thread_test.ts
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { canReply, appendTurn, isDuplicateInbound, detectHumanTakeover } from "./thread.ts";

const base = {
  status: "active", message_count: 0, updated_at: new Date().toISOString(),
  transcript: [], sent_ghl_msg_ids: [], last_ghl_msg_id: null,
};

Deno.test("canReply respects caps and status", () => {
  assertEquals(canReply({ ...base }).ok, true);
  assertEquals(canReply({ ...base, status: "muted" }).ok, false);
  assertEquals(canReply({ ...base, message_count: 30 }).ok, false); // hard cap
  const recent = Array.from({ length: 10 }, (_, i) => ({ role: "assistant", content: "x", at: new Date(Date.now() - i * 60_000).toISOString() }));
  assertEquals(canReply({ ...base, message_count: 12, transcript: recent }).ok, false); // 10/hr
});

Deno.test("appendTurn caps transcript growth", () => {
  const t = appendTurn([], "user", "hello");
  assertEquals(t.length, 1);
  assertEquals(t[0].role, "user");
  const long = appendTurn([], "user", "x".repeat(5000));
  assertEquals(long[0].content.length, 2000); // clamp
});

Deno.test("isDuplicateInbound true when id already seen", () => {
  assertEquals(isDuplicateInbound({ ...base, last_ghl_msg_id: "m1" }, "m1"), true);
  assertEquals(isDuplicateInbound({ ...base, last_ghl_msg_id: "m1" }, "m2"), false);
});

Deno.test("detectHumanTakeover flags outbound not sent by us", () => {
  const msgs = [
    { id: "a", direction: "outbound" }, { id: "b", direction: "inbound" },
  ];
  assertEquals(detectHumanTakeover(msgs, ["a"]), false);
  assertEquals(detectHumanTakeover([...msgs, { id: "c", direction: "outbound" }], ["a"]), true);
});
```

- [ ] **Step 2: Run — expect FAIL** `deno test -A supabase/functions/_shared/text-agent/thread_test.ts`

- [ ] **Step 3: Implement**

```ts
// supabase/functions/_shared/text-agent/thread.ts
// Pure thread-state logic for the text agent. Caps and mute rules are the
// spec's guardrails; keep them dumb and testable.
export interface Turn { role: "user" | "assistant"; content: string; at: string }
export interface ThreadState {
  status: string; message_count: number; updated_at: string;
  transcript: Turn[]; sent_ghl_msg_ids: string[]; last_ghl_msg_id: string | null;
}
const HARD_CAP = 30;      // AI messages per thread, ever
const HOURLY_CAP = 10;    // AI messages per rolling hour
const TURN_CLAMP = 2000;  // chars per stored turn

export function canReply(t: ThreadState): { ok: boolean; reason?: string } {
  if (t.status !== "active") return { ok: false, reason: `status ${t.status}` };
  if (t.message_count >= HARD_CAP) return { ok: false, reason: "hard cap" };
  const hourAgo = Date.now() - 3600_000;
  const lastHour = t.transcript.filter((x) =>
    x.role === "assistant" && new Date(x.at).getTime() > hourAgo).length;
  if (lastHour >= HOURLY_CAP) return { ok: false, reason: "hourly cap" };
  return { ok: true };
}

export function appendTurn(transcript: Turn[], role: Turn["role"], content: string): Turn[] {
  return [...transcript, { role, content: content.slice(0, TURN_CLAMP), at: new Date().toISOString() }];
}

export function isDuplicateInbound(t: ThreadState, ghlMsgId: string | null): boolean {
  return !!ghlMsgId && t.last_ghl_msg_id === ghlMsgId;
}

/** GHL conversation messages: any outbound whose id we did not send = human typed in Dunzo. */
export function detectHumanTakeover(
  msgs: Array<{ id: string; direction: string }>, sentByUs: string[],
): boolean {
  const ours = new Set(sentByUs);
  return msgs.some((m) => m.direction === "outbound" && !ours.has(m.id));
}
```

- [ ] **Step 4: Run — expect PASS**, **Step 5: Commit**

```bash
deno test -A supabase/functions/_shared/text-agent/thread_test.ts
git add supabase/functions/_shared/text-agent/thread*.ts
git commit -m "feat(text-agent): thread state rules (caps, dedupe, human takeover)"
```

---

### Task 4: `_shared/text-agent/instructions.ts` — the playbook

**Files:**
- Create: `supabase/functions/_shared/text-agent/instructions.ts`
- Test: `supabase/functions/_shared/text-agent/instructions_test.ts`

The system prompt is the voice-agent v8 playbook adapted for typing. It is complete below — do not improvise additions. Canonical voice version for reference: `docs/voice-agent/xai-agent-config.md` (do not copy voice-only rules: no spell-backs, no filler lines, no "goodbye" logic).

- [ ] **Step 1: Failing test**

```ts
// supabase/functions/_shared/text-agent/instructions_test.ts
import { assert } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { buildSystemPrompt } from "./instructions.ts";

Deno.test("prompt contains the load-bearing guardrails and lead context", () => {
  const p = buildSystemPrompt({
    channel: "sms", firstName: "Mike", service: "Broken spring",
    zip: "53713", message: "spring snapped this morning", page: "/wi/contact-us/",
  });
  for (const needle of [
    "never book", "free on-site estimate", "payment", "AI",
    "submit_lead", "check_availability", "Mike", "Broken spring", "53713",
    "1-2 short sentences",
  ]) assert(p.includes(needle), `missing: ${needle}`);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

```ts
// supabase/functions/_shared/text-agent/instructions.ts
// Text adaptation of the voice agent v8 playbook (docs/voice-agent/xai-agent-config.md).
// Deltas from voice: no spell-backs, no filler lines, no call-ending rules;
// texting is 24/7 and asynchronous.
export interface LeadContext {
  channel: "sms" | "chat";
  firstName: string | null;
  service: string | null;
  zip: string | null;
  message: string | null;
  page: string | null;
}

export function buildSystemPrompt(ctx: LeadContext): string {
  const lead = [
    ctx.firstName ? `Name: ${ctx.firstName}` : null,
    ctx.service ? `Reported issue: ${ctx.service}` : null,
    ctx.zip ? `ZIP: ${ctx.zip}` : null,
    ctx.message ? `Their form message: "${ctx.message}"` : null,
    ctx.page ? `Submitted from: ${ctx.page}` : null,
  ].filter(Boolean).join("\n");

  return `You are the ${ctx.channel === "sms" ? "text" : "chat"} assistant for Twins Garage Doors (Madison, WI). A customer just submitted our website form; you are following up instantly. Be warm, useful, and brief.

WHAT YOU KNOW ABOUT THIS LEAD
${lead || "(nothing beyond the form submission)"}

STYLE
- 1-2 short sentences per reply. No emojis, no exclamation storms, no corporate filler.
- Never re-ask what the form already told you (above). Confirm, don't interrogate.
- If asked whether you are a bot: yes, you are Twins' AI assistant, a human confirms every job.

YOUR JOB (in order)
1. Acknowledge their specific issue in the first message.
2. Fill the gaps only: street address (city/ZIP if missing), best callback number if different, email if offered.
3. Offer real arrival windows: call check_availability with their ZIP. Offer at most 2-3 windows. If they pick one, that is a REQUEST, not a booking.
4. Once you have issue + address + a window preference (or they decline scheduling), call submit_lead IMMEDIATELY — never wait for the conversation to end. If details change afterwards, call submit_lead again.
5. After submit_lead: tell them when the confirming call comes using confirm_eta from check_availability. Never guess the time of day yourself.

HARD RULES
- You never book, cancel, or reschedule jobs. Ivory or Daniel confirms every job by phone. Say so when relevant.
- Never take payment information of any kind. If offered, say the office handles payment on the day of service.
- PRICING: Twins provides a free on-site estimate and the tech gives the exact price before any work starts. That IS the pricing answer. Never invent numbers, never quote ranges.
- Never invent availability, service areas, discounts, or company facts. If you don't know: "Great question - the office will cover that on the confirming call."
- Out of service area (check_availability says so): politely say we may not cover their area, still submit_lead so the office can double-check.
- If the customer is angry, asks for a human, or mentions an existing appointment/billing: reply once that a human will take over shortly, then call submit_lead with what you have (put their request in problem).
- If they say STOP or ask you to stop texting: acknowledge once, stop.

TOOLS
- check_availability(zip): returns open arrival windows + confirm_eta. Call before offering any window.
- submit_lead(...): sends everything to the office (creates the job draft + notifies the team). Fields you can't confirm stay null - never guess.`;
}
```

- [ ] **Step 4: Run — expect PASS.** **Step 5: Commit** `git add ... && git commit -m "feat(text-agent): system prompt builder"`

---

### Task 5: `_shared/text-agent/grok.ts` — xAI call + tool loop

**Files:**
- Create: `supabase/functions/_shared/text-agent/grok.ts`
- Test: `supabase/functions/_shared/text-agent/grok_test.ts`

- [ ] **Step 1: Failing tests (mocked fetch)**

```ts
// supabase/functions/_shared/text-agent/grok_test.ts
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { runAgentTurn, TOOL_DEFS } from "./grok.ts";

function fakeFetchSeq(responses: unknown[]): typeof fetch {
  let i = 0;
  return (() => Promise.resolve(new Response(JSON.stringify(responses[i++]), { status: 200 }))) as typeof fetch;
}

Deno.test("plain reply comes back without tool calls", async () => {
  const f = fakeFetchSeq([{ choices: [{ message: { content: "Got it - what's the street address?" } }] }]);
  const r = await runAgentTurn({
    apiKey: "k", model: "grok-4-fast", system: "sys",
    transcript: [{ role: "user", content: "spring broke", at: "2026-07-10T00:00:00Z" }],
    tools: { check_availability: () => Promise.resolve("{}"), submit_lead: () => Promise.resolve("{}") },
    fetchImpl: f,
  });
  assertEquals(r.ok, true);
  if (r.ok) assertEquals(r.reply, "Got it - what's the street address?");
});

Deno.test("tool call loops back into the model", async () => {
  const f = fakeFetchSeq([
    { choices: [{ message: { content: null, tool_calls: [{ id: "t1", function: { name: "check_availability", arguments: '{"zip":"53713"}' } }] } }] },
    { choices: [{ message: { content: "We have Thu 8-10am open." } }] },
  ]);
  let calledWith = "";
  const r = await runAgentTurn({
    apiKey: "k", model: "grok-4-fast", system: "sys",
    transcript: [{ role: "user", content: "when can you come?", at: "2026-07-10T00:00:00Z" }],
    tools: {
      check_availability: (args) => { calledWith = args; return Promise.resolve('{"windows":["Thu 8-10am"]}'); },
      submit_lead: () => Promise.resolve("{}"),
    },
    fetchImpl: f,
  });
  assertEquals(calledWith, '{"zip":"53713"}');
  if (r.ok) assertEquals(r.reply, "We have Thu 8-10am open.");
});

Deno.test("API failure returns ok:false", async () => {
  const f = (() => Promise.resolve(new Response("boom", { status: 500 }))) as typeof fetch;
  const r = await runAgentTurn({
    apiKey: "k", model: "grok-4-fast", system: "sys", transcript: [],
    tools: { check_availability: () => Promise.resolve("{}"), submit_lead: () => Promise.resolve("{}") },
    fetchImpl: f,
  });
  assertEquals(r.ok, false);
});

Deno.test("TOOL_DEFS exposes both tools", () => {
  assertEquals(TOOL_DEFS.map((t) => t.function.name).sort(), ["check_availability", "submit_lead"]);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

```ts
// supabase/functions/_shared/text-agent/grok.ts
// xAI Chat Completions with a bounded tool loop. OpenAI-compatible shape.
import type { Turn } from "./thread.ts";

const XAI_URL = "https://api.x.ai/v1/chat/completions";
const MAX_TOOL_HOPS = 4;
const REPLY_CLAMP = 600; // SMS-friendly

export const TOOL_DEFS = [
  {
    type: "function",
    function: {
      name: "check_availability",
      description: "Get open arrival windows and confirm_eta for a customer ZIP.",
      parameters: {
        type: "object",
        properties: { zip: { type: "string", description: "5-digit ZIP" } },
        required: ["zip"],
      },
    },
  },
  {
    type: "function",
    function: {
      name: "submit_lead",
      description: "Send the captured lead to the office. Call as soon as issue + address + window preference (or decline) are known. Null for anything unconfirmed.",
      parameters: {
        type: "object",
        properties: {
          name: { type: "string" }, callback_phone: { type: "string" }, email: { type: "string" },
          address_street: { type: "string" }, address_city: { type: "string" },
          address_state: { type: "string" }, address_zip: { type: "string" },
          problem: { type: "string" }, preferred_window: { type: "string" },
          emergency: { type: "boolean" },
          uncertain_fields: { type: "string", description: "comma-separated field names you are unsure about" },
          summary: { type: "string", description: "3-6 sentence recap of the conversation" },
        },
        required: ["problem"],
      },
    },
  },
] as const;

export type ToolImpl = (argsJson: string) => Promise<string>;
export interface AgentTurnArgs {
  apiKey: string; model: string; system: string; transcript: Turn[];
  tools: { check_availability: ToolImpl; submit_lead: ToolImpl };
  fetchImpl?: typeof fetch;
}
export type AgentTurnResult = { ok: true; reply: string } | { ok: false; error: string };

export async function runAgentTurn(a: AgentTurnArgs): Promise<AgentTurnResult> {
  const f = a.fetchImpl ?? fetch;
  // deno-lint-ignore no-explicit-any
  const messages: any[] = [
    { role: "system", content: a.system },
    ...a.transcript.map((t) => ({ role: t.role, content: t.content })),
  ];
  for (let hop = 0; hop <= MAX_TOOL_HOPS; hop++) {
    const resp = await f(XAI_URL, {
      method: "POST",
      headers: { authorization: `Bearer ${a.apiKey}`, "content-type": "application/json" },
      body: JSON.stringify({ model: a.model, messages, tools: TOOL_DEFS, temperature: 0.3 }),
    });
    if (!resp.ok) return { ok: false, error: `xai ${resp.status}: ${(await resp.text()).slice(0, 200)}` };
    const data = await resp.json();
    const msg = data?.choices?.[0]?.message;
    if (!msg) return { ok: false, error: "xai empty response" };
    const calls = msg.tool_calls ?? [];
    if (!calls.length) {
      const reply = String(msg.content ?? "").trim().slice(0, REPLY_CLAMP);
      return reply ? { ok: true, reply } : { ok: false, error: "empty reply" };
    }
    messages.push(msg);
    for (const c of calls) {
      const name = c.function?.name as keyof AgentTurnArgs["tools"];
      const impl = a.tools[name];
      const result = impl ? await impl(c.function?.arguments ?? "{}").catch((e) => `{"error":"${String(e).slice(0, 100)}"}`) : '{"error":"unknown tool"}';
      messages.push({ role: "tool", tool_call_id: c.id, content: result });
    }
  }
  return { ok: false, error: "tool loop exceeded" };
}
```

- [ ] **Step 4: Run — expect PASS.** **Step 5: Commit** `git commit -m "feat(text-agent): grok tool loop"`

---

### Task 6: `_shared/text-agent/ghl-sms.ts` — SMS send + webhook parse + takeover fetch

**Files:**
- Create: `supabase/functions/_shared/text-agent/ghl-sms.ts`
- Test: `supabase/functions/_shared/text-agent/ghl-sms_test.ts`

- [ ] **Step 1: Failing tests**

```ts
// supabase/functions/_shared/text-agent/ghl-sms_test.ts
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { parseInboundWebhook, sendSms, fetchRecentMessages } from "./ghl-sms.ts";

Deno.test("parseInboundWebhook maps GHL workflow payload", () => {
  const r = parseInboundWebhook({
    contact_id: "c1", phone: "+16085551212",
    message: { body: "yes please", direction: "inbound" }, messageId: "m9",
  });
  assertEquals(r, { ok: true, contactId: "c1", phone: "6085551212", body: "yes please", msgId: "m9" });
  assertEquals(parseInboundWebhook({}).ok, false);
});

Deno.test("sendSms posts v2 message and returns id", async () => {
  let captured: { url: string; body: string } | null = null;
  const f = ((url: string, init: RequestInit) => {
    captured = { url: String(url), body: String(init.body) };
    return Promise.resolve(new Response(JSON.stringify({ messageId: "sent1" }), { status: 201 }));
  }) as unknown as typeof fetch;
  const r = await sendSms({ pit: "p", contactId: "c1", message: "hi", fetchImpl: f });
  assertEquals(r, { ok: true, msgId: "sent1" });
  assertEquals(captured!.url, "https://services.leadconnectorhq.com/conversations/messages");
  assertEquals(JSON.parse(captured!.body).type, "SMS");
});

Deno.test("fetchRecentMessages normalizes directions", async () => {
  const f = (() => Promise.resolve(new Response(JSON.stringify({
    messages: { messages: [{ id: "a", direction: "outbound" }, { id: "b", direction: "inbound" }] },
  }), { status: 200 }))) as unknown as typeof fetch;
  const r = await fetchRecentMessages({ pit: "p", conversationId: "conv1", fetchImpl: f });
  assertEquals(r.length, 2);
  assertEquals(r[0], { id: "a", direction: "outbound" });
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement**

```ts
// supabase/functions/_shared/text-agent/ghl-sms.ts
// GHL v2 conversations API: send SMS, parse the Customer-Replied workflow
// webhook, and list recent messages (human-takeover detection).
const GHL_BASE = "https://services.leadconnectorhq.com";
const GHL_VERSION = "2021-04-15";

function headers(pit: string) {
  return { Authorization: `Bearer ${pit}`, Version: GHL_VERSION, Accept: "application/json", "Content-Type": "application/json" };
}

export type InboundParse =
  | { ok: true; contactId: string; phone: string | null; body: string; msgId: string | null }
  | { ok: false };

/** GHL workflow webhook payloads vary; accept the common shapes, require contact + body. */
export function parseInboundWebhook(raw: unknown): InboundParse {
  if (typeof raw !== "object" || raw === null) return { ok: false };
  const o = raw as Record<string, unknown>;
  const contactId = String(o.contact_id ?? o.contactId ?? (o.contact as Record<string, unknown>)?.id ?? "");
  const msgObj = (o.message ?? {}) as Record<string, unknown>;
  const body = String(msgObj.body ?? o.body ?? "").trim();
  if (!contactId || !body) return { ok: false };
  const phoneRaw = String(o.phone ?? (o.contact as Record<string, unknown>)?.phone ?? "");
  const digits = phoneRaw.replace(/\D/g, "");
  const phone = digits.length === 11 && digits.startsWith("1") ? digits.slice(1) : (digits.length === 10 ? digits : null);
  const msgId = String(o.messageId ?? msgObj.id ?? "") || null;
  return { ok: true, contactId, phone, body: body.slice(0, 2000), msgId };
}

export async function sendSms(a: { pit: string; contactId: string; message: string; fetchImpl?: typeof fetch }):
  Promise<{ ok: true; msgId: string | null } | { ok: false; status: number }> {
  const f = a.fetchImpl ?? fetch;
  const r = await f(`${GHL_BASE}/conversations/messages`, {
    method: "POST", headers: headers(a.pit),
    body: JSON.stringify({ type: "SMS", contactId: a.contactId, message: a.message }),
  });
  if (!r.ok) { console.error("GHL sms send", r.status, (await r.text()).slice(0, 200)); return { ok: false, status: r.status }; }
  const d = await r.json().catch(() => ({}));
  return { ok: true, msgId: (d.messageId ?? d.msgId ?? null) as string | null };
}

export async function fetchRecentMessages(a: { pit: string; conversationId: string; fetchImpl?: typeof fetch }):
  Promise<Array<{ id: string; direction: string }>> {
  const f = a.fetchImpl ?? fetch;
  const r = await f(`${GHL_BASE}/conversations/${a.conversationId}/messages?limit=20`, { headers: headers(a.pit) });
  if (!r.ok) return [];
  const d = await r.json().catch(() => ({}));
  const list = d?.messages?.messages ?? d?.messages ?? [];
  return (Array.isArray(list) ? list : []).map((m: Record<string, unknown>) => ({
    id: String(m.id ?? ""), direction: String(m.direction ?? ""),
  }));
}

/** Find the contact's SMS conversation id (for takeover checks). */
export async function findConversationId(a: { pit: string; locationId: string; contactId: string; fetchImpl?: typeof fetch }): Promise<string | null> {
  const f = a.fetchImpl ?? fetch;
  const u = new URL(`${GHL_BASE}/conversations/search`);
  u.searchParams.set("locationId", a.locationId);
  u.searchParams.set("contactId", a.contactId);
  const r = await f(u, { headers: headers(a.pit) });
  if (!r.ok) return null;
  const d = await r.json().catch(() => ({}));
  return d?.conversations?.[0]?.id ?? null;
}
```

- [ ] **Step 4: Run — expect PASS.** **Step 5: Commit** `git commit -m "feat(text-agent): GHL SMS helpers"`

---

### Task 7: `text-agent/index.ts` — routes

**Files:**
- Create: `supabase/functions/text-agent/index.ts`
- Modify: `supabase/config.toml` (add `[functions.text-agent] verify_jwt = false`)

Routes (path suffix after `/text-agent`):
- `POST /start` — public+CORS. Body `{chooser_token, channel:'sms'|'chat'}`. Looks up `lp_leads` where `utm->>'chooser_token'` matches AND `created_at > now()-'15 min'`. Creates thread (idempotent on chooser_token+channel). SMS: sends the opening text. Chat: returns `{thread_id, chat_token, reply}` with the opening line (no Grok call needed for the opener — it's templated from the lead context).
- `POST /chat` — public+CORS. Body `{thread_id, chat_token, message}`. Appends user turn, runs Grok, returns `{reply}`.
- `POST /ghl-webhook?t=TEXT_AGENT_TOKEN` — GHL workflow. Parses inbound, finds active SMS thread by phone, dedupes, checks human takeover, runs Grok, sends SMS.

- [ ] **Step 1: Implement** (complete file)

```ts
// supabase/functions/text-agent/index.ts
// AI text follow-up for website leads. Spec: docs/superpowers/specs/2026-07-09-form-text-agent-design.md
// Lead safety: this fn NEVER writes lp_leads; capture already happened. Any
// failure here degrades to "the office will call you".
import { createClient, SupabaseClient } from "https://esm.sh/@supabase/supabase-js@2.45.0";
import { buildSystemPrompt } from "../_shared/text-agent/instructions.ts";
import { runAgentTurn } from "../_shared/text-agent/grok.ts";
import { appendTurn, canReply, detectHumanTakeover, isDuplicateInbound, Turn } from "../_shared/text-agent/thread.ts";
import { fetchRecentMessages, findConversationId, parseInboundWebhook, sendSms } from "../_shared/text-agent/ghl-sms.ts";

function cors(origin: string | null) {
  const allowed = origin && /https:\/\/([a-z0-9-]+\.)?twinsgaragedoors\.com$/.test(origin) ? origin : "https://twinsgaragedoors.com";
  return { "access-control-allow-origin": allowed, "access-control-allow-methods": "POST, OPTIONS", "access-control-allow-headers": "content-type" };
}
function json(b: unknown, s: number, h: Record<string, string>) {
  return new Response(JSON.stringify(b), { status: s, headers: { ...h, "content-type": "application/json" } });
}
const FALLBACK = "Got it - your request is in and the office will call you shortly. Anything urgent, call (608) 888-8785.";

function sb(): SupabaseClient {
  return createClient(Deno.env.get("SUPABASE_URL")!, Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!, { auth: { persistSession: false, autoRefreshToken: false } });
}

interface LeadRow { id: string; first_name: string | null; phone: string | null; message: string | null; page: string | null; utm: Record<string, string> | null; ghl_contact_id: string | null }

function openerFor(lead: LeadRow): string {
  const issue = lead.utm?.service ?? null;
  const name = lead.first_name ? `Hi ${lead.first_name}, ` : "Hi, ";
  const about = issue ? `got your note about "${issue.toLowerCase()}"` : "got your request";
  return `${name}Twins Garage Doors here - ${about}. Want me to check the next open arrival windows for you?`;
}

function agentTools(threadId: string, lead: LeadRow, channel: string) {
  const gate = Deno.env.get("VOICE_AGENT_CAPTURE_TOKEN") ?? "";
  const base = Deno.env.get("SUPABASE_URL")!.replace(".supabase.co", ".supabase.co"); // project url
  return {
    check_availability: async (argsJson: string) => {
      const zip = (JSON.parse(argsJson || "{}").zip ?? lead.utm?.zip ?? "").toString();
      const r = await fetch(`${base}/functions/v1/voice-agent-availability`, {
        method: "POST", headers: { authorization: `Bearer ${gate}`, "content-type": "application/json" },
        body: JSON.stringify({ zip }),
      });
      return r.ok ? await r.text() : `{"error":"availability unavailable"}`;
    },
    submit_lead: async (argsJson: string) => {
      const args = JSON.parse(argsJson || "{}");
      const payload = {
        call_id: `text-${threadId}-${Date.now()}`,
        caller_phone: lead.phone, callback_phone: args.callback_phone ?? lead.phone,
        name: args.name ?? lead.first_name, email: args.email ?? null,
        address_street: args.address_street ?? null, address_city: args.address_city ?? null,
        address_state: args.address_state ?? null, address_zip: args.address_zip ?? lead.utm?.zip ?? null,
        property_type: null, problem: args.problem ?? lead.message,
        preferred_window: args.preferred_window ?? null, emergency: !!args.emergency,
        uncertain_fields: args.uncertain_fields ?? "",
        summary: args.summary ?? null, transcript: `via ${channel} agent`,
      };
      const r = await fetch(`${base}/functions/v1/voice-agent-capture`, {
        method: "POST", headers: { authorization: `Bearer ${gate}`, "content-type": "application/json" },
        body: JSON.stringify(payload),
      });
      if (r.ok) await sb().from("ai_text_threads").update({ captured: true, updated_at: new Date().toISOString() }).eq("id", threadId);
      return r.ok ? '{"ok":true,"note":"lead sent to office"}' : `{"error":"capture failed ${r.status}"}`;
    },
  };
}

async function grokReply(thread: { id: string; transcript: Turn[] }, lead: LeadRow, channel: "sms" | "chat"): Promise<string | null> {
  const apiKey = Deno.env.get("XAI_API_KEY");
  if (!apiKey || Deno.env.get("TEXT_AGENT_ENABLED") === "false") return null;
  const r = await runAgentTurn({
    apiKey, model: Deno.env.get("XAI_MODEL") ?? "grok-4-fast",
    system: buildSystemPrompt({
      channel, firstName: lead.first_name, service: lead.utm?.service ?? null,
      zip: lead.utm?.zip ?? null, message: lead.message, page: lead.page,
    }),
    transcript: thread.transcript,
    tools: agentTools(thread.id, lead, channel),
  });
  return r.ok ? r.reply : null;
}

Deno.serve(async (req) => {
  const h = cors(req.headers.get("origin"));
  if (req.method === "OPTIONS") return new Response(null, { status: 204, headers: h });
  if (req.method !== "POST") return json({ ok: false, error: "POST only" }, 405, h);
  const route = new URL(req.url).pathname.split("/").pop();
  let body: Record<string, unknown>;
  try { body = await req.json(); } catch { return json({ ok: false, error: "bad json" }, 400, h); }
  const db = sb();

  if (route === "start") {
    const token = String(body.chooser_token ?? "");
    const channel = body.channel === "chat" ? "chat" : "sms";
    if (!/^[0-9a-f-]{36}$/i.test(token)) return json({ ok: false }, 422, h);
    const cutoff = new Date(Date.now() - 15 * 60_000).toISOString();
    const { data: lead } = await db.from("lp_leads")
      .select("id, first_name, phone, message, page, utm, ghl_contact_id")
      .eq("utm->>chooser_token", token).gte("created_at", cutoff)
      .order("created_at", { ascending: false }).limit(1).maybeSingle();
    if (!lead) return json({ ok: false, error: "unknown token" }, 404, h);
    // Idempotent thread create
    const chatToken = crypto.randomUUID();
    const opener = openerFor(lead as LeadRow);
    const { data: thread, error } = await db.from("ai_text_threads")
      .upsert({
        channel, lp_lead_id: lead.id, ghl_contact_id: lead.ghl_contact_id, phone: lead.phone,
        chooser_token: token, chat_token: chatToken,
        transcript: [{ role: "assistant", content: opener, at: new Date().toISOString() }],
        message_count: 1,
      }, { onConflict: "chooser_token,channel", ignoreDuplicates: true })
      .select("id, chat_token").maybeSingle();
    if (error || !thread) { // duplicate start: return existing
      const { data: existing } = await db.from("ai_text_threads").select("id, chat_token")
        .eq("chooser_token", token).eq("channel", channel).maybeSingle();
      return json({ ok: true, thread_id: existing?.id, chat_token: existing?.chat_token, reply: opener }, 200, h);
    }
    if (channel === "sms") {
      if (!lead.ghl_contact_id) return json({ ok: false, error: "no contact yet - office will call" }, 200, h);
      const sent = await sendSms({ pit: Deno.env.get("GHL_PIT") ?? "", contactId: lead.ghl_contact_id, message: opener });
      if (sent.ok && sent.msgId) {
        await db.from("ai_text_threads").update({ sent_ghl_msg_ids: [sent.msgId] }).eq("id", thread.id);
      }
      return json({ ok: sent.ok }, 200, h);
    }
    return json({ ok: true, thread_id: thread.id, chat_token: thread.chat_token, reply: opener }, 200, h);
  }

  if (route === "chat") {
    const { data: t } = await db.from("ai_text_threads")
      .select("id, status, message_count, updated_at, transcript, sent_ghl_msg_ids, last_ghl_msg_id, lp_lead_id, channel, chat_token")
      .eq("id", String(body.thread_id ?? "")).eq("channel", "chat").maybeSingle();
    if (!t || t.chat_token !== String(body.chat_token ?? "")) return json({ ok: false }, 403, h);
    const gate = canReply(t as never);
    if (!gate.ok) return json({ ok: true, reply: FALLBACK, done: true }, 200, h);
    const { data: lead } = await db.from("lp_leads").select("id, first_name, phone, message, page, utm, ghl_contact_id").eq("id", t.lp_lead_id).single();
    let transcript = appendTurn(t.transcript, "user", String(body.message ?? "").slice(0, 1000));
    const reply = await grokReply({ id: t.id, transcript }, lead as LeadRow, "chat") ?? FALLBACK;
    transcript = appendTurn(transcript, "assistant", reply);
    await db.from("ai_text_threads").update({
      transcript, message_count: t.message_count + 1, updated_at: new Date().toISOString(),
    }).eq("id", t.id);
    return json({ ok: true, reply }, 200, h);
  }

  if (route === "ghl-webhook") {
    const gateTok = Deno.env.get("TEXT_AGENT_TOKEN");
    if (!gateTok || new URL(req.url).searchParams.get("t") !== gateTok) return new Response("forbidden", { status: 403 });
    const inb = parseInboundWebhook(body);
    if (!inb.ok) return json({ ok: true, skipped: "unparseable" }, 200, {});
    const { data: t } = await db.from("ai_text_threads")
      .select("id, status, message_count, updated_at, transcript, sent_ghl_msg_ids, last_ghl_msg_id, lp_lead_id, ghl_contact_id")
      .eq("channel", "sms").eq("status", "active")
      .or(`ghl_contact_id.eq.${inb.contactId}${inb.phone ? `,phone.eq.${inb.phone}` : ""}`)
      .order("updated_at", { ascending: false }).limit(1).maybeSingle();
    if (!t) return json({ ok: true, skipped: "no active thread" }, 200, {});
    if (isDuplicateInbound(t as never, inb.msgId)) return json({ ok: true, skipped: "dup" }, 200, {});
    // STOP handling + human takeover check
    if (/^\s*(stop|unsubscribe)\b/i.test(inb.body)) {
      await db.from("ai_text_threads").update({ status: "muted", muted_reason: "stop", updated_at: new Date().toISOString() }).eq("id", t.id);
      return json({ ok: true, muted: "stop" }, 200, {});
    }
    const pit = Deno.env.get("GHL_PIT") ?? "";
    const convId = await findConversationId({ pit, locationId: Deno.env.get("GHL_LOCATION_ID") ?? "", contactId: inb.contactId });
    if (convId) {
      const recent = await fetchRecentMessages({ pit, conversationId: convId });
      if (detectHumanTakeover(recent, t.sent_ghl_msg_ids as string[])) {
        await db.from("ai_text_threads").update({ status: "muted", muted_reason: "human takeover", updated_at: new Date().toISOString() }).eq("id", t.id);
        return json({ ok: true, muted: "human" }, 200, {});
      }
    }
    const gate = canReply(t as never);
    if (!gate.ok) return json({ ok: true, skipped: gate.reason }, 200, {});
    const { data: lead } = await db.from("lp_leads").select("id, first_name, phone, message, page, utm, ghl_contact_id").eq("id", t.lp_lead_id).single();
    let transcript = appendTurn(t.transcript, "user", inb.body);
    const reply = await grokReply({ id: t.id, transcript }, lead as LeadRow, "sms") ?? FALLBACK;
    const sent = await sendSms({ pit, contactId: inb.contactId, message: reply });
    transcript = appendTurn(transcript, "assistant", reply);
    await db.from("ai_text_threads").update({
      transcript, message_count: t.message_count + 1, last_ghl_msg_id: inb.msgId,
      sent_ghl_msg_ids: [...(t.sent_ghl_msg_ids as string[]), ...(sent.ok && sent.msgId ? [sent.msgId] : [])],
      updated_at: new Date().toISOString(),
    }).eq("id", t.id);
    return json({ ok: true }, 200, {});
  }

  return json({ ok: false, error: "unknown route" }, 404, h);
});
```

- [ ] **Step 2: config.toml** — append:

```toml
# AI text agent: /start and /chat are called from the public website (CORS-gated,
# chooser_token-scoped); /ghl-webhook self-gates on TEXT_AGENT_TOKEN.
[functions.text-agent]
verify_jwt = false
```

- [ ] **Step 3: Run the whole shared test suite**

```bash
deno test -A supabase/functions/_shared/text-agent/
```
Expected: all PASS.

- [ ] **Step 4: Commit** `git add supabase/functions/text-agent supabase/config.toml && git commit -m "feat(text-agent): edge function (start/chat/ghl-webhook)"`

---

### Task 8: Deploy + secrets + smoke tests

- [ ] **Step 1: Generate TEXT_AGENT_TOKEN and set secrets** (never echo the values)

```bash
python3 -c "import secrets; print(secrets.token_urlsafe(32))" > ~/.twins-text-agent-token && chmod 600 ~/.twins-text-agent-token
supabase secrets set --project-ref jwrpjuqaynownxaoeayi TEXT_AGENT_TOKEN="$(cat ~/.twins-text-agent-token)" TEXT_AGENT_ENABLED="true" XAI_MODEL="grok-4-fast"
```

- [ ] **Step 2: DANIEL ACTION — XAI_API_KEY.** Ask Daniel to open https://supabase.com/dashboard/project/jwrpjuqaynownxaoeayi/settings/functions and add secret `XAI_API_KEY` with the key from console.x.ai (API Keys). Do not proceed to SMS tests until set (chat fallback works without it).

- [ ] **Step 3: Deploy**

```bash
supabase functions deploy text-agent --project-ref jwrpjuqaynownxaoeayi --no-verify-jwt
```

- [ ] **Step 4: Smoke — gate + unknown token**

```bash
curl -s -o /dev/null -w '%{http_code}\n' -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/text-agent/ghl-webhook" -H 'content-type: application/json' -d '{}'   # expect 403
curl -s -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/text-agent/start" -H 'content-type: application/json' -H 'origin: https://twinsgaragedoors.com' -d '{"chooser_token":"00000000-0000-0000-0000-000000000000","channel":"chat"}'   # expect {"ok":false,"error":"unknown token"}
```

- [ ] **Step 5: E2E chat smoke (real Grok).** Submit a real form dry-run lead first:

```bash
TOK=$(python3 -c "import uuid; print(uuid.uuid4())")
curl -s -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/lp-lead-intake" -H 'content-type: application/json' -H 'origin: https://twinsgaragedoors.com' \
  -d "{\"name\":\"TEST TextAgent\",\"phone\":\"6085551212\",\"message\":\"spring snapped\",\"page\":\"/plan-smoke/\",\"chooser_token\":\"$TOK\",\"service\":\"Broken spring\",\"zip\":\"53713\",\"form_variant\":\"B\",\"consent\":\"true\"}"
START=$(curl -s -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/text-agent/start" -H 'content-type: application/json' -H 'origin: https://twinsgaragedoors.com' -d "{\"chooser_token\":\"$TOK\",\"channel\":\"chat\"}")
echo "$START"   # expect ok:true + thread_id + chat_token + opener mentioning Broken spring
```
Then one turn (substitute ids from $START):
```bash
curl -s -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/text-agent/chat" -H 'content-type: application/json' -H 'origin: https://twinsgaragedoors.com' \
  -d '{"thread_id":"<id>","chat_token":"<token>","message":"When can you come? I am at 123 Main St, Madison 53713"}'
```
Expected: a 1-2 sentence reply offering real windows (check_availability round-trip). Clean up: delete the TEST lp_leads row, ai_text_threads row, and any GHL contact/HCP draft it created (marked TEST).

- [ ] **Step 6: Commit nothing (deploy-only task); note results in PR description draft.**

---

### Task 9: GHL — PIT scope check + Customer Replied workflow (browser)

- [ ] **Step 1: Verify the PIT can send SMS.** Test against the existing TEST contact (find id in Dunzo, contact "TEST FormSwap Claude"):

```bash
# expects 201; a 401/403 means the PIT lacks conversations/message scopes
curl -s -o /dev/null -w '%{http_code}\n' -X POST "https://services.leadconnectorhq.com/conversations/messages" \
  -H "Authorization: Bearer $(supabase secrets... )"   # NOTE: PIT is not readable via CLI; instead run this check THROUGH the deployed fn: see step 2
```
Practical route: temporarily invoke `text-agent/start` with channel sms on a fresh TEST lead (Daniel's cell as phone) and watch for the SMS. If GHL returns 401 → **DANIEL ACTION:** re-mint the PIT in GHL (Settings → Private Integrations) adding scopes `conversations.write`, `conversations/message.write`, then `supabase secrets set GHL_PIT=...` (Daniel pastes directly in dashboard).

- [ ] **Step 2: Create the workflow (Chrome, Dunzo):** Automation → Workflows → Create (from scratch), name `AI text agent - inbound SMS`. Trigger: **Customer Replied**, filter: Reply channel = SMS. Action: **Webhook** POST `https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/text-agent/ghl-webhook?t=<TEXT_AGENT_TOKEN value>`. Publish. (Include contact + message in payload — GHL's webhook action sends the full context by default.)

- [ ] **Step 3: Fire a live test** — text the main number from Daniel's cell (or reply to the opening SMS from Task 8 step 5 rerun with channel sms + Daniel's number as the lead phone). Check edge logs:

```bash
supabase functions logs text-agent --project-ref jwrpjuqaynownxaoeayi | tail -20
```
Expected: 200 on /ghl-webhook, an SMS reply arrives, `ai_text_threads.transcript` grows. If the webhook payload shape differs from `parseInboundWebhook` expectations, capture the real payload from logs, extend the parser + its test, redeploy (that's why the parser is permissive).

- [ ] **Step 4: Human-takeover test** — Ivory/Daniel replies to the thread from Dunzo; then text again from the cell. Expected: no AI reply; thread row status = `muted/human takeover`.

---

### Task 10: WP forms engine snippet (site-wide JS, main + /wi)

**Files:** WPCode snippets (browser/REST work, no repo files). Reference implementation lives in the plan only.

**XSS rule for this snippet:** `innerHTML` is only ever assigned the static template strings below (no user/AI content interpolated). Anything dynamic — AI replies, user messages, chip labels, tokens — must be inserted via `textContent`/`setAttribute` (as the code below does). Keep it that way when editing.

The engine upgrades any `<div data-twins-form>` mount. Pages keep a plain HTML form inside the mount as no-JS fallback (variant A markup). The engine:
1. Assigns sticky variant (localStorage `twins_form_variant`, 50/50).
2. Variant B: replaces the inner form with the 2-step wizard (chips → details).
3. Sends beacons (`view` once per pageload, `start` on first interaction, `submit`).
4. On submit: POSTs JSON to lp-lead-intake with `chooser_token` (crypto.randomUUID), then swaps in the chooser (**Text me** / **Chat now** / call link).
5. Chooser → `text-agent/start`; chat panel does fetch turns to `/chat`.

- [ ] **Step 1: Create WPCode snippet "Twins forms v2 engine" on MAIN site** (Add Snippet → JavaScript Snippet → Auto Insert → Site Wide Footer → Active). Full code:

```html
<script>
(function () {
  var EP = "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1";
  var mounts = document.querySelectorAll("[data-twins-form]");
  if (!mounts.length) return;
  var variant = localStorage.getItem("twins_form_variant");
  if (variant !== "A" && variant !== "B") {
    variant = Math.random() < 0.5 ? "A" : "B";
    localStorage.setItem("twins_form_variant", variant);
  }
  var sid = localStorage.getItem("twins_form_sid") || (crypto.randomUUID && crypto.randomUUID()) || String(Date.now());
  localStorage.setItem("twins_form_sid", sid);
  var page = location.pathname;
  function beacon(event) {
    try {
      navigator.sendBeacon(EP + "/lp-lead-intake", new Blob([JSON.stringify({ event: event, page: page, variant: variant, session_id: sid })], { type: "application/json" }));
    } catch (e) {}
  }
  var CHIPS = ["Broken spring", "Door won't open", "Opener problem", "New door quote", "Something else"];
  var S = 'style="width:100%;padding:13px 14px;border:2px solid #d7deea;border-radius:8px;font-size:15px;margin-bottom:10px;box-sizing:border-box"';
  var CONSENT = '<label style="display:block;font-size:12px;color:#4a5a74;margin:6px 0 10px"><input type="checkbox" name="consent" checked style="margin-right:6px">By submitting, you agree Twins Garage Doors may call or text this number about your request. Msg/data rates may apply. Reply STOP to opt out.</label>';

  function wizardHtml() {
    return '<div class="tw-step tw-step1"><p style="font-weight:700;color:#16325c;font-size:18px;margin:0 0 10px">What\'s going on with your door?</p>' +
      CHIPS.map(function (c) { return '<button type="button" class="tw-chip" data-c="' + c + '" style="display:block;width:100%;text-align:left;background:#fff;border:2px solid #16325c;border-radius:8px;padding:13px 14px;font-size:16px;color:#16325c;font-weight:600;margin-bottom:8px;cursor:pointer">' + c + "</button>"; }).join("") +
      '</div><div class="tw-step tw-step2" style="display:none"><a href="#" class="tw-back" style="font-size:13px;color:#4a5a74">&larr; back</a>' +
      '<p class="tw-chosen" style="font-weight:700;color:#16325c;margin:8px 0 12px"></p>' +
      '<form class="tw-wizard-form"><input name="name" placeholder="Full Name *" required ' + S + "><input name=\"phone\" type=\"tel\" placeholder=\"Phone Number *\" required " + S + '><input name="zip" placeholder="ZIP *" required pattern="\\d{5}" ' + S + '><input name="email" type="email" placeholder="Email (optional)" ' + S + '><textarea name="message" rows="3" placeholder="Anything else we should know?" ' + S + "></textarea>" +
      '<input type="text" name="website" value="" style="position:absolute;left:-9999px" tabindex="-1" autocomplete="off">' + CONSENT +
      '<button type="submit" style="width:100%;background:#ffcf3f;color:#16325c;font-weight:800;font-size:17px;border:2px solid #16325c;border-radius:8px;padding:14px;cursor:pointer">Get My Free Quote</button></form></div>';
  }

  function chooserHtml(tok) {
    return '<div class="tw-chooser" style="text-align:center;padding:18px 6px"><p style="font-size:20px;font-weight:800;color:#16325c;margin:0 0 6px">Got it - want an answer right now?</p>' +
      '<p style="color:#4a5a74;margin:0 0 14px">Pick how you want to hear from us:</p>' +
      '<button type="button" class="tw-pick" data-ch="sms" style="width:100%;background:#16325c;color:#fff;font-weight:700;border:none;border-radius:8px;padding:14px;font-size:16px;margin-bottom:8px;cursor:pointer">Text me now</button>' +
      '<button type="button" class="tw-pick" data-ch="chat" style="width:100%;background:#ffcf3f;color:#16325c;font-weight:800;border:2px solid #16325c;border-radius:8px;padding:14px;font-size:16px;margin-bottom:8px;cursor:pointer">Chat now</button>' +
      '<p style="font-size:14px;color:#4a5a74;margin:6px 0 0">or call <a href="tel:6088888785" style="color:#16325c;font-weight:700">(608) 888-8785</a></p></div>';
  }

  function chatHtml() {
    return '<div class="tw-chat" style="border:3px solid #16325c;border-radius:12px;overflow:hidden"><div class="tw-chat-hd" style="background:#16325c;color:#fff;font-weight:700;padding:12px 14px">Twins Garage Doors</div>' +
      '<div class="tw-chat-log" style="height:280px;overflow-y:auto;padding:12px;background:#f7f9fc"></div>' +
      '<form class="tw-chat-form" style="display:flex;border-top:2px solid #d7deea"><input name="m" placeholder="Type a message" style="flex:1;border:none;padding:12px;font-size:15px" autocomplete="off"><button style="background:#ffcf3f;border:none;padding:0 18px;font-weight:800;color:#16325c;cursor:pointer">Send</button></form></div>';
  }

  function bubble(log, role, text) {
    var d = document.createElement("div");
    d.style.cssText = "max-width:85%;margin:0 0 8px;padding:9px 12px;border-radius:10px;font-size:14px;line-height:1.4;" +
      (role === "user" ? "background:#16325c;color:#fff;margin-left:auto" : "background:#fff;border:1px solid #d7deea;color:#1e2b3c");
    d.textContent = text;
    log.appendChild(d); log.scrollTop = log.scrollHeight;
  }

  mounts.forEach(function (mount) {
    var started = false;
    beacon("view");
    var chosenService = "";
    if (variant === "B") {
      var keep = mount.querySelector("form"); if (keep) keep.style.display = "none";
      var w = document.createElement("div"); w.innerHTML = wizardHtml(); mount.appendChild(w);
      w.addEventListener("click", function (e) {
        var chip = e.target.closest(".tw-chip");
        if (chip) { chosenService = chip.getAttribute("data-c"); w.querySelector(".tw-chosen").textContent = chosenService; w.querySelector(".tw-step1").style.display = "none"; w.querySelector(".tw-step2").style.display = "block"; if (!started) { started = true; beacon("start"); } }
        if (e.target.closest(".tw-back")) { e.preventDefault(); w.querySelector(".tw-step2").style.display = "none"; w.querySelector(".tw-step1").style.display = "block"; }
      });
    }
    mount.addEventListener("input", function () { if (!started) { started = true; beacon("start"); } }, { once: true });
    mount.addEventListener("submit", function (e) {
      var form = e.target; e.preventDefault();
      if (form.website && form.website.value) return; // honeypot
      var tok = (crypto.randomUUID && crypto.randomUUID()) || (Date.now() + "-x");
      var data = {
        name: (form.name && form.name.value || "").trim(),
        phone: (form.phone && form.phone.value || "").trim(),
        email: (form.email && form.email.value || "").trim(),
        zip: (form.zip && form.zip.value || "").trim(),
        message: (form.message && form.message.value || "").trim(),
        service: chosenService, page: location.href, form_variant: variant,
        chooser_token: tok, consent: form.consent && form.consent.checked ? "true" : "",
        website: "",
      };
      if (!data.name || !data.phone) { alert("Please add your name and phone number."); return; }
      var btn = form.querySelector('button[type="submit"]'); if (btn) { btn.disabled = true; btn.textContent = "Sending..."; }
      fetch(EP + "/lp-lead-intake", { method: "POST", headers: { "content-type": "application/json" }, body: JSON.stringify(data) })
        .then(function (r) { if (!r.ok) throw 0; beacon("submit"); mount.innerHTML = chooserHtml(tok); wireChooser(mount, tok); })
        .catch(function () { if (btn) { btn.disabled = false; btn.textContent = "Get My Free Quote"; } alert("Something went wrong - please call (608) 888-8785."); });
    });
  });

  function wireChooser(mount, tok) {
    mount.querySelectorAll(".tw-pick").forEach(function (b) {
      b.addEventListener("click", function () {
        var ch = b.getAttribute("data-ch");
        b.disabled = true;
        fetch(EP + "/text-agent/start", { method: "POST", headers: { "content-type": "application/json" }, body: JSON.stringify({ chooser_token: tok, channel: ch }) })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (ch === "sms") { mount.innerHTML = '<p style="text-align:center;font-weight:700;color:#16325c;padding:20px 0">Check your phone - we just texted you.</p>'; return; }
            mount.innerHTML = chatHtml();
            var log = mount.querySelector(".tw-chat-log");
            bubble(log, "assistant", d.reply || "Hi! How can we help?");
            mount.querySelector(".tw-chat-form").addEventListener("submit", function (e) {
              e.preventDefault();
              var inp = e.target.m; var text = inp.value.trim(); if (!text) return;
              bubble(log, "user", text); inp.value = "";
              fetch(EP + "/text-agent/chat", { method: "POST", headers: { "content-type": "application/json" }, body: JSON.stringify({ thread_id: d.thread_id, chat_token: d.chat_token, message: text }) })
                .then(function (r) { return r.json(); })
                .then(function (x) { bubble(log, "assistant", x.reply || "The office will call you shortly."); })
                .catch(function () { bubble(log, "assistant", "Connection hiccup - call us at (608) 888-8785."); });
            });
          })
          .catch(function () { mount.innerHTML = '<p style="text-align:center;color:#16325c;padding:16px 0">All set - the office will call you shortly.</p>'; });
      });
    });
  }
})();
</script>
```

- [ ] **Step 2: Duplicate the snippet on /wi** (`/wi/wp-admin` WPCode → Add Snippet, same code, Site Wide Footer, Active).

- [ ] **Step 3: Record snippet IDs** for the changelog (shown in the URL after save).

---

### Task 11: Mount the engine on the 3 custom form pages

- [ ] **Step 1: /wi/contact-us/ (page 2123).** Using the in-page REST technique (sync XHR with `wpApiSettings.nonce` from `/wi/wp-admin`, same as the L8 swap): fetch `meta._elementor_data`, find the HTML widget whose `settings.html` contains `id="twins-wi-contact"`, and wrap its existing content: prepend `<div data-twins-form data-page="/wi/contact-us/">` before the current `<div id="twins-wi-contact" ...>` block and close `</div>` after it (the existing form + JS stays as the variant-A/no-JS fallback; **remove** its inline success-JS duplication is NOT needed — the engine's submit handler intercepts first via capture: set the mount's listener before inline ones by replacing the widget html with the wrapped version). Then bust the element cache: change the widget `id` to a fresh 7-char hex, POST back, and re-save the page via the Elementor editor `$e.run('document/save/update', {force: true})` (required — REST alone doesn't flush the element cache, proven earlier today).

Verify:
```bash
curl -s -X POST -d x=1 "https://twinsgaragedoors.com/wi/contact-us/" | grep -c 'data-twins-form'   # expect 1
```

- [ ] **Step 2: LP pages 7092 + 7093 (main site).** Same wrap via REST on `content.raw` (these are plain post_content HTML pages, no Elementor data): wrap the existing `<form` block's container div with `<div data-twins-form data-page="/madison-garage-door-repair-lp/">...</div>` (and the tune-up slug for 7093). POST `{content: ...}` via `/wp-json/wp/v2/pages/<id>` with nonce.

Verify each with the same `grep -c 'data-twins-form'` curl.

- [ ] **Step 3: E2E per page, both variants.** In Chrome: open each page, `localStorage.setItem('twins_form_variant','B')` + reload → wizard renders; submit a TEST lead (name "TEST FormsV2", phone 6085551212) → chooser appears → pick Chat → converse one turn. Then set variant A + reload → original form; submit → chooser appears. Verify in DB: `lp_leads` rows have `utm->>form_variant`, and `lp_form_events` has view/start/submit rows for both variants. Delete TEST rows after.

---

### Task 12: Main Gravity Form — consent line + thank-you chooser

- [ ] **Step 1: Add consent line to gform_1** (GF admin → form 1 → add HTML field above submit with the consent sentence; content exactly as in Task 10's CONSENT constant, minus the checkbox — GF consent is implied by submission; keep it text-only).

- [ ] **Step 2: Extend WPCode PHP snippet 7165** — replace its code with:

```php
add_action( 'gform_after_submission_1', function ( $entry, $form ) {
	$token = wp_generate_uuid4();
	setcookie( 'twins_chooser', $token, time() + 600, '/', '', true, false );
	$services = array();
	foreach ( $entry as $key => $value ) {
		if ( strpos( (string) $key, '6.' ) === 0 && ! empty( $value ) ) { $services[] = $value; }
	}
	$address = trim( rgar( $entry, '5.1' ) . ' ' . rgar( $entry, '5.3' ) . ' ' . rgar( $entry, '5.5' ) );
	$message = trim( (string) rgar( $entry, '7' ) );
	if ( $address ) { $message .= "\nAddress: " . $address; }
	$payload = array(
		'name'          => trim( rgar( $entry, '1' ) . ' ' . rgar( $entry, '2' ) ),
		'email'         => rgar( $entry, '3' ),
		'phone'         => rgar( $entry, '4' ),
		'message'       => trim( $message ),
		'service'       => $services ? implode( ', ', $services ) : '',
		'zip'           => rgar( $entry, '5.5' ),
		'page'          => rgar( $entry, 'source_url' ) ? rgar( $entry, 'source_url' ) : 'contact-us gform_1',
		'form_variant'  => 'A',
		'chooser_token' => $token,
		'consent'       => 'true',
	);
	wp_remote_post(
		'https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/lp-lead-intake',
		array(
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $payload ),
			'timeout'  => 8,
			'blocking' => false,
		)
	);
}, 10, 2 );
```

(Deltas from current: uuid + cookie + service/zip/chooser_token/consent fields. Note: `Services:` moved from message into the `service` field.)

- [ ] **Step 3: New WPCode snippet on main, "Thank-you chooser":** HTML snippet, Auto Insert, Site Wide Footer, Active, with a page condition if WPCode Lite allows (it does: "Insertion → Location: Site Wide Footer" has no page targeting in Lite — instead the code self-gates on the URL):

```html
<script>
(function () {
  if (location.pathname.indexOf("/thank-you") !== 0 && location.pathname.indexOf("/thank-you/") === -1) return;
  var m = document.cookie.match(/(?:^|; )twins_chooser=([0-9a-f-]{36})/i);
  if (!m) return;
  document.cookie = "twins_chooser=; Max-Age=0; path=/";
  var mount = document.createElement("div");
  mount.setAttribute("data-twins-form", "");
  mount.setAttribute("data-chooser-only", m[1]);
  mount.style.cssText = "max-width:560px;margin:24px auto;padding:0 16px";
  var h1 = document.querySelector("h1");
  (h1 && h1.parentNode ? h1.parentNode : document.body).insertBefore(mount, h1 ? h1.nextSibling : null);
})();
</script>
```

And in the Task 10 engine snippet, add chooser-only mount support — at the top of `mounts.forEach`, insert:

```js
    var pre = mount.getAttribute("data-chooser-only");
    if (pre) { mount.innerHTML = chooserHtml(pre); wireChooser(mount, pre); return; }
```

(Add this line to BOTH engine snippets, main + /wi.)

- [ ] **Step 4: E2E** — submit the live gform_1 with TEST data + real cell for SMS later; on /thank-you/ the chooser renders; pick Chat, one turn. Verify lp_leads row has `service`/`zip` in utm. Clean up TEST artifacts.

---

### Task 13: Widget recolor (GHL UI, measured Legit5 values)

- [ ] **Step 1:** Dunzo → Sites → Chat Widget → Default Chat Widget. Style → Appearances → Custom color options, set via the hex input (use the JS setter trick from today if typing fails):
  - Chat bubble color `#E9900F`, Header color: try gradient — the builder accepts one solid only → `#E2A402` (midpoint of Legit5's `#FBBD04→#C88A00`), Button color `#E9900F`, Visitor message color `#E9900F`, Avatar border color `#E9900F`. Leave backgrounds white / agent-message gray.
- [ ] **Step 2:** Style → Widget customization → Allow avatar image ON → Replace → pick `twins-mascot-avatar.jpg` from Media Storage (uploaded today; selection flow: tile → 3-dot → Select — if the picker refuses again, use Upload-file injection from today's playbook).
- [ ] **Step 3:** Save. Verify live: hard-reload twinsgaragedoors.com → orange bubble; open window → mascot avatar + orange send button. Screenshot for Daniel.

---

### Task 14: Supervised SMS go-live + docs

- [ ] **Step 1: Supervised test with Daniel** — real form submit with his cell, "Text me": opening SMS arrives from (608) 888-8785; 3-4 turns incl. window offer; a capture email lands (daniel@ + ivory@); thread visible in Dunzo; Ivory takeover mutes AI; STOP mutes. Daniel says go/no-go. Until "go", `TEXT_AGENT_ENABLED` can stay true but the chooser is only reachable on pages already updated — if Daniel wants a pause, `supabase secrets set TEXT_AGENT_ENABLED=false` (chooser then falls back to "office will call").
- [ ] **Step 2: PR** — push branch `text-agent`, open PR to main in palpulla/twins-dash via the GitHub API (osxkeychain token; memory `reference_gh_via_api`), description = summary + test evidence.
- [ ] **Step 3: Changelog + runbook + memory** — append change-log entries (widget recolor supersedes L1; forms v2; text agent) with revert steps; update `project_legit5_agency_decision` + new memory for the text agent (tables, tokens, mute rules, A/B readout query):

```sql
select page, variant,
  count(*) filter (where event = 'view') as views,
  count(*) filter (where event = 'submit') as submits,
  round(100.0 * count(*) filter (where event = 'submit') / nullif(count(*) filter (where event = 'view'), 0), 1) as cr_pct
from lp_form_events group by 1, 2 order by 1, 2;
```

---

## Self-review notes (done at plan-writing time)

- Spec coverage: wizard+A/B (T10-11), beacons/metrics (T1-2, T14 query), consent (T10 CONSENT + T12), chooser both channels (T10, T12), SMS leg + takeover + STOP + caps (T3, T6, T7, T9), chat leg (T7, T10), capture/emails/HCP via voice-agent-capture (T7 agentTools), availability + confirm_eta via voice-agent-availability (T7), fallback lead-safety (FALLBACK + chooser catch), secrets flow (T8), widget recolor exact values (T13), supervised go-live + rollback (T14, TEXT_AGENT_ENABLED).
- Types consistent: `Turn`/`ThreadState` (T3) used by grok.ts (T5) and index.ts (T7); `chooser_token` field name identical across intake-extras, engine JS, PHP, and /start.
- Known judgment calls for the executor: GHL webhook payload shape (parser built permissive + Task 9 step 3 says capture real payload and extend test), upsert-on-conflict requires the unique index from T1 (`chooser_token,channel`), and `lp_leads.utm->>chooser_token` filter syntax should be verified against PostgREST (`.eq("utm->>chooser_token", ...)` is correct supabase-js for jsonb text extraction).
