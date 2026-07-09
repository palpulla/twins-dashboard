# xAI Voice Agent (After-Hours + Missed Calls) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An xAI-hosted voice agent answers Twins' after-hours and missed calls, captures leads, and pushes each capture into GHL (contact + note + `ai-captured-needs-confirm` tag) for Ivory's morning confirm loop.

**Architecture:** GHL call forwarding (after-hours schedule + 20s ring timeout) sends calls to an agent built in the xAI Voice Agent Builder. The agent's `submit_lead` webhook tool POSTs to a new token-gated Supabase edge function `voice-agent-capture` on jwrpj, which audits to a `voice_agent_captures` table and writes the contact/note/tag via the existing GHL v2 private-integration token. No HCP writes, no SMS, no email.

**Tech Stack:** Supabase edge functions (Deno), Postgres (jwrpj), GHL v2 API (`services.leadconnectorhq.com`), xAI Voice Agent Builder (hosted, no-code).

**Spec:** `docs/superpowers/specs/2026-07-09-xai-voice-agent-design.md` (outer repo)

**Repos:** Edge-fn code + playbook docs go in the **twins-dash repo** (`/Users/daniel/twins-dashboard/twins-dash`) on branch `voice-agent-capture`. This plan + the spec live in the outer repo. Commit early and often — the checkout is shared (see memory: shared-worktree branch-clobber).

**Load-bearing constraints (from spec + memory):**
- Capture-and-confirm only. The agent NEVER books; HCP stays source of truth; NO HCP writes (the shelved call-intake pipeline stays shelved).
- No fabricated operational data: the agent never invents prices/fees/terms; missing facts route to "a technician will confirm."
- No SMS/email/push to anyone. `voice_agent_captures` is silent observability.
- Customer-facing copy contains no em-dashes.
- Never paste secrets in chat; set via CLI. The xAI API key lives only in the xAI console.
- Postgres UNIQUE+NULL pitfall: we only ever `ON CONFLICT (call_id)` with non-null call_ids (invalid payloads are plain INSERTs), so a plain UNIQUE is safe here.

---

### Task 1: Phase 0 verifications (gates the whole approach)

No code. These checks happen in the Dunzo GHL account (`app.godunzo.com`, location `iRUlbIBg7PzSfLrPiR2j`) with Daniel. Record every answer in `twins-dash/docs/voice-agent/phase0-findings.md`.

- [ ] **Step 1: Identify the live inbound number.** GHL Settings → Phone Numbers: list all numbers. Cross-check with Daniel which number customers actually call (the phone map lists five; business phone on file is 833-833-2010, main website line is (608) 888-8785). Confirm by opening 2-3 recent inbound call conversations and checking the dialed number.

- [ ] **Step 2: Verify forwarding capabilities on that number** (target = Daniel's cell for this test, NOT xAI yet):
  - Ring-timeout forward: set unanswered-call forwarding with a ~20s timeout, call the number, don't answer, confirm it forwards.
  - After-hours forward: confirm GHL supports schedule-based routing on this number (business-hours setting or workflow-based routing). Test one after-hours-simulated call.
  - If BOTH work → Approach A confirmed. If either fails → STOP, report back; fallback is xAI direct-SIP on the existing number (spec amendment required).

- [ ] **Step 3: Verify caller-ID passthrough.** During the Step 2 test call, check whether Daniel's cell shows the original caller's number or the GHL trunk number. Record which. (If the trunk number shows, the agent must rely on asking for the callback number — the design already collects it verbally, so this is informational, not blocking.)

- [ ] **Step 4: Pin costs.** Record in the findings doc: (a) xAI voice per-minute price from https://x.ai pricing / Voice Agent Builder console; (b) GHL/LC Phone forwarding-leg per-minute rate from the Dunzo billing page. Estimate monthly cost at ~5 after-hours calls/day × 4 min.

- [ ] **Step 5: Verify PIT scopes.** GHL Settings → Private Integrations → `call-intake-pipeline`: confirm scopes include `contacts.readonly` + `contacts.write` (notes and tags ride on contacts.write). If scopes must be added, note whether GHL rotates the token when scopes change — if it does, the new value must be re-set as the `GHL_PIT` secret on jwrpj at deploy time (Task 7).

- [ ] **Step 6: Commit findings**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git checkout -b voice-agent-capture
git add docs/voice-agent/phase0-findings.md
git commit -m "docs(voice-agent): phase 0 findings - forwarding, caller id, costs, PIT scopes"
```

---

### Task 2: `voice_agent_captures` table (migration)

**Files:**
- Create: `supabase/migrations/20260709120000_voice_agent_captures.sql`

- [ ] **Step 1: Write the migration**

```sql
-- Audit table for xAI voice-agent capture webhooks. Silent observability only:
-- no alerts, no emails. Service-role access only (RLS on, no policies).
create table if not exists public.voice_agent_captures (
  id bigint generated always as identity primary key,
  call_id text unique,          -- xAI call id; null only for invalid payloads (never upserted on null)
  caller_phone text,            -- normalized 10-digit, best of callback_phone/caller_phone
  payload jsonb not null,       -- raw validated capture (or raw body for invalid rows)
  ghl_contact_id text,
  status text not null default 'received',  -- received | created | duplicate | error | invalid
  error text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

alter table public.voice_agent_captures enable row level security;
```

- [ ] **Step 2: Apply to jwrpj** via the Supabase MCP `apply_migration` tool (project jwrpj…), name `voice_agent_captures`. Then verify the migration is recorded: `select version from supabase_migrations.schema_migrations order by version desc limit 3;` via `execute_sql` — if the new version row is missing, INSERT it manually (known desync gotcha, see memory reference_twins_dash_migration_history).

- [ ] **Step 3: Verify table exists**

Run via MCP `execute_sql`: `select count(*) from public.voice_agent_captures;`
Expected: `0`

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260709120000_voice_agent_captures.sql
git commit -m "feat(voice-agent): voice_agent_captures audit table"
```

---

### Task 3: Payload validation module (TDD)

**Files:**
- Create: `supabase/functions/_shared/voice-agent/payload.ts`
- Test: `supabase/functions/_shared/voice-agent/payload_test.ts`

- [ ] **Step 1: Write the failing tests**

```ts
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { parseCapture } from "./payload.ts";

Deno.test("rejects non-object and missing call_id", () => {
  assertEquals(parseCapture("nope").ok, false);
  assertEquals(parseCapture({ name: "Jane" }).ok, false);
});

Deno.test("rejects capture with no phone at all", () => {
  const r = parseCapture({ call_id: "c1", name: "Jane" });
  assertEquals(r.ok, false);
});

Deno.test("parses a full capture, trims strings, coerces property_type", () => {
  const r = parseCapture({
    call_id: " c1 ", caller_phone: "+16085551234", callback_phone: "",
    name: "  Jane Doe ", address: "123 Main St, Madison WI", property_type: "Residential",
    problem: "broken spring", preferred_window: "tomorrow morning",
    emergency: "yes", uncertain_fields: ["address", 42, "  "], summary: "spring call",
  });
  if (!r.ok) throw new Error(r.error);
  assertEquals(r.capture.call_id, "c1");
  assertEquals(r.capture.name, "Jane Doe");
  assertEquals(r.capture.callback_phone, null);       // empty string -> null
  assertEquals(r.capture.property_type, "residential");
  assertEquals(r.capture.emergency, false);            // only boolean true counts
  assertEquals(r.capture.uncertain_fields, ["address"]);
});

Deno.test("unknown property_type becomes null", () => {
  const r = parseCapture({ call_id: "c2", caller_phone: "6085551234", property_type: "farm" });
  if (!r.ok) throw new Error(r.error);
  assertEquals(r.capture.property_type, null);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/daniel/twins-dashboard/twins-dash && deno test supabase/functions/_shared/voice-agent/payload_test.ts`
Expected: FAIL (module not found)

- [ ] **Step 3: Write the implementation**

```ts
// Validates the xAI voice agent's submit_lead webhook payload.
// Fields the agent didn't clearly hear arrive null, never guessed.
export interface VoiceCapture {
  call_id: string;
  caller_phone: string | null;
  callback_phone: string | null;
  name: string | null;
  address: string | null;
  property_type: "residential" | "commercial" | null;
  problem: string | null;
  preferred_window: string | null;
  emergency: boolean;
  uncertain_fields: string[];
  summary: string | null;
}

export type ParseResult =
  | { ok: true; capture: VoiceCapture }
  | { ok: false; error: string };

function str(v: unknown): string | null {
  if (typeof v !== "string") return null;
  const t = v.trim();
  return t.length ? t : null;
}

export function parseCapture(input: unknown): ParseResult {
  if (typeof input !== "object" || input === null || Array.isArray(input)) {
    return { ok: false, error: "payload must be a JSON object" };
  }
  const o = input as Record<string, unknown>;
  const call_id = str(o.call_id);
  if (!call_id) return { ok: false, error: "call_id is required" };
  const caller_phone = str(o.caller_phone);
  const callback_phone = str(o.callback_phone);
  if (!caller_phone && !callback_phone) {
    return { ok: false, error: "capture needs callback_phone or caller_phone" };
  }
  const pt = str(o.property_type)?.toLowerCase() ?? null;
  return {
    ok: true,
    capture: {
      call_id,
      caller_phone,
      callback_phone,
      name: str(o.name),
      address: str(o.address),
      property_type: pt === "residential" || pt === "commercial" ? pt : null,
      problem: str(o.problem),
      preferred_window: str(o.preferred_window),
      emergency: o.emergency === true,
      uncertain_fields: Array.isArray(o.uncertain_fields)
        ? o.uncertain_fields.filter((f): f is string => typeof f === "string" && f.trim().length > 0)
        : [],
      summary: str(o.summary),
    },
  };
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `deno test supabase/functions/_shared/voice-agent/payload_test.ts`
Expected: 4 passed

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/voice-agent/payload.ts supabase/functions/_shared/voice-agent/payload_test.ts
git commit -m "feat(voice-agent): submit_lead payload validation"
```

---

### Task 4: Note builder module (TDD)

**Files:**
- Create: `supabase/functions/_shared/voice-agent/note.ts`
- Test: `supabase/functions/_shared/voice-agent/note_test.ts`

- [ ] **Step 1: Write the failing tests**

```ts
import { assertStringIncludes, assert } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { buildCaptureNote } from "./note.ts";
import type { VoiceCapture } from "./payload.ts";

const base: VoiceCapture = {
  call_id: "call_abc", caller_phone: "6085551234", callback_phone: "6085559999",
  name: "Jane Doe", address: "123 Main St, Madison WI", property_type: "residential",
  problem: "door stuck halfway", preferred_window: "tomorrow morning",
  emergency: false, uncertain_fields: [], summary: "Spring likely broken.",
};

Deno.test("note includes all captured fields and the call id", () => {
  const n = buildCaptureNote(base);
  assertStringIncludes(n, "Jane Doe");
  assertStringIncludes(n, "123 Main St");
  assertStringIncludes(n, "residential");
  assertStringIncludes(n, "door stuck halfway");
  assertStringIncludes(n, "6085559999");
  assertStringIncludes(n, "tomorrow morning");
  assertStringIncludes(n, "call_abc");
  assert(!n.includes("VERIFY ON CALLBACK"));
});

Deno.test("uncertain fields render as VERIFY ON CALLBACK lines", () => {
  const n = buildCaptureNote({ ...base, uncertain_fields: ["address", "name spelling"] });
  assertStringIncludes(n, "VERIFY ON CALLBACK");
  assertStringIncludes(n, "- address");
  assertStringIncludes(n, "- name spelling");
});

Deno.test("emergency flag is loud, missing fields say not given", () => {
  const n = buildCaptureNote({ ...base, emergency: true, name: null, address: null });
  assertStringIncludes(n, "EMERGENCY");
  assertStringIncludes(n, "Name: (not given)");
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `deno test supabase/functions/_shared/voice-agent/note_test.ts`
Expected: FAIL (module not found)

- [ ] **Step 3: Write the implementation**

```ts
import type { VoiceCapture } from "./payload.ts";

/** GHL contact note for Ivory's morning confirm. Internal copy; plain text. */
export function buildCaptureNote(c: VoiceCapture): string {
  const lines: string[] = ["AI VOICE AGENT CAPTURE (after-hours / missed call)"];
  if (c.emergency) lines.push("!! EMERGENCY DESCRIBED ON CALL - CALL BACK FIRST !!");
  lines.push(`Name: ${c.name ?? "(not given)"}`);
  lines.push(
    `Address: ${c.address ?? "(not given)"}${c.property_type ? ` (${c.property_type})` : ""}`,
  );
  lines.push(`Problem: ${c.problem ?? "(not given)"}`);
  lines.push(`Callback: ${c.callback_phone ?? c.caller_phone ?? "(not given)"}`);
  lines.push(
    `Preferred window: ${c.preferred_window ?? "(not given)"} (NOT confirmed. Twins confirms the actual time.)`,
  );
  if (c.uncertain_fields.length) {
    lines.push("", "VERIFY ON CALLBACK (agent heard these uncertainly):");
    for (const f of c.uncertain_fields) lines.push(`- ${f}`);
  }
  if (c.summary) lines.push("", `Call summary: ${c.summary}`);
  lines.push("", `xAI call id: ${c.call_id}`);
  return lines.join("\n");
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `deno test supabase/functions/_shared/voice-agent/note_test.ts`
Expected: 3 passed

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/voice-agent/note.ts supabase/functions/_shared/voice-agent/note_test.ts
git commit -m "feat(voice-agent): capture note builder with VERIFY ON CALLBACK lines"
```

---

### Task 5: GHL v2 helpers (contact / note / tags)

**Files:**
- Create: `supabase/functions/_shared/voice-agent/ghl.ts`

Thin fetch wrappers; no unit tests (network calls) — verified live in Task 7's smoke test. Mirrors the verified client in `_shared/call-intake/ghl.ts` (same base URL, same `Version: 2021-04-15` header that passed live contact create/search on 2026-06-29).

- [ ] **Step 1: Write the module**

```ts
// GHL (LeadConnector) v2 helpers for the voice-agent capture webhook.
// Auth: Private Integration token. Contact search/create pattern verified live
// 2026-06-29 in _shared/call-intake/ghl.ts; notes + tags verified in Task 7 smoke test.
const GHL_BASE = "https://services.leadconnectorhq.com";
const GHL_VERSION = "2021-04-15";

function headers(pit: string): Record<string, string> {
  return {
    Authorization: `Bearer ${pit}`,
    Version: GHL_VERSION,
    Accept: "application/json",
    "Content-Type": "application/json",
  };
}

/**
 * Find a contact by phone (duplicate search) or create one.
 * Never invents a name: a phone-only contact is created when name is null.
 */
export async function findOrCreateContact(args: {
  pit: string; locationId: string; phone: string; name?: string | null;
}): Promise<string | null> {
  const su = new URL(`${GHL_BASE}/contacts/search/duplicate`);
  su.searchParams.set("locationId", args.locationId);
  su.searchParams.set("number", args.phone);
  const sr = await fetch(su, { headers: headers(args.pit) });
  if (sr.ok) {
    const d = await sr.json();
    if (d?.contact?.id) return d.contact.id;
  }
  const body: Record<string, unknown> = { locationId: args.locationId, phone: args.phone };
  if (args.name) body.firstName = args.name;
  const cr = await fetch(`${GHL_BASE}/contacts/`, {
    method: "POST", headers: headers(args.pit), body: JSON.stringify(body),
  });
  if (!cr.ok) return null;
  return (await cr.json())?.contact?.id ?? null;
}

export async function addContactNote(args: {
  pit: string; contactId: string; body: string;
}): Promise<{ ok: boolean; status: number }> {
  const r = await fetch(`${GHL_BASE}/contacts/${args.contactId}/notes`, {
    method: "POST", headers: headers(args.pit), body: JSON.stringify({ body: args.body }),
  });
  return { ok: r.ok, status: r.status };
}

export async function addContactTags(args: {
  pit: string; contactId: string; tags: string[];
}): Promise<{ ok: boolean; status: number }> {
  const r = await fetch(`${GHL_BASE}/contacts/${args.contactId}/tags`, {
    method: "POST", headers: headers(args.pit), body: JSON.stringify({ tags: args.tags }),
  });
  return { ok: r.ok, status: r.status };
}
```

Fallback note for the executor: if the notes or tags call returns 4xx in the Task 7 smoke test with an API-version error, retry with `Version: "2021-07-28"` (the contacts-API version in newer GHL docs) and keep whichever works.

- [ ] **Step 2: Typecheck**

Run: `deno check supabase/functions/_shared/voice-agent/ghl.ts`
Expected: no errors

- [ ] **Step 3: Commit**

```bash
git add supabase/functions/_shared/voice-agent/ghl.ts
git commit -m "feat(voice-agent): ghl v2 contact/note/tag helpers"
```

---

### Task 6: `voice-agent-capture` edge function

**Files:**
- Create: `supabase/functions/voice-agent-capture/index.ts`

- [ ] **Step 1: Write the function**

```ts
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.45.0";
import { parseCapture } from "../_shared/voice-agent/payload.ts";
import { buildCaptureNote } from "../_shared/voice-agent/note.ts";
import { findOrCreateContact, addContactNote, addContactTags } from "../_shared/voice-agent/ghl.ts";
import { normalizePhone } from "../_shared/call-intake/phone.ts";

const CORS = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, content-type",
};
const TAG = "ai-captured-needs-confirm";

function json(b: unknown, s = 200) {
  return new Response(JSON.stringify(b), {
    status: s,
    headers: { ...CORS, "Content-Type": "application/json" },
  });
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: CORS });
  if (req.method !== "POST") return json({ error: "method not allowed" }, 405);

  // Auth gate: ?t=<VOICE_AGENT_CAPTURE_TOKEN>, same scheme as other gated fns.
  const url = new URL(req.url);
  const gate = Deno.env.get("VOICE_AGENT_CAPTURE_TOKEN");
  if (!gate || url.searchParams.get("t") !== gate) {
    return new Response("forbidden", { status: 403, headers: CORS });
  }

  const sb = createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
    { auth: { persistSession: false, autoRefreshToken: false } },
  );

  let raw: unknown;
  try {
    raw = await req.json();
  } catch {
    return json({ error: "invalid json" }, 400);
  }

  const parsed = parseCapture(raw);
  if (!parsed.ok) {
    // Silent review trail for malformed webhooks; call_id may be absent (plain INSERT, never upserted).
    await sb.from("voice_agent_captures").insert({
      payload: raw, status: "invalid", error: parsed.error,
    });
    return json({ error: parsed.error }, 400);
  }
  const cap = parsed.capture;

  // Idempotency: one row per xAI call id. A retry of a finished capture no-ops.
  const { data: existing } = await sb
    .from("voice_agent_captures")
    .select("id, status")
    .eq("call_id", cap.call_id)
    .maybeSingle();
  if (existing?.status === "created") return json({ ok: true, duplicate: true });

  const phone = normalizePhone(cap.callback_phone) ?? normalizePhone(cap.caller_phone);
  const { data: saved, error: upErr } = await sb
    .from("voice_agent_captures")
    .upsert(
      {
        call_id: cap.call_id,
        caller_phone: phone,
        payload: cap,
        status: "received",
        error: null,
        updated_at: new Date().toISOString(),
      },
      { onConflict: "call_id" },
    )
    .select("id")
    .single();
  if (upErr || !saved) return json({ error: upErr?.message ?? "db error" }, 500);

  try {
    const PIT = Deno.env.get("GHL_PIT") ?? "";
    const LOCATION = Deno.env.get("GHL_LOCATION_ID") ?? "";
    if (!PIT || !LOCATION) throw new Error("GHL_PIT / GHL_LOCATION_ID not configured");
    if (!phone) throw new Error("no valid 10-digit phone in capture");

    const contactId = await findOrCreateContact({
      pit: PIT, locationId: LOCATION, phone, name: cap.name,
    });
    if (!contactId) throw new Error("GHL contact find-or-create failed");

    const note = await addContactNote({ pit: PIT, contactId, body: buildCaptureNote(cap) });
    if (!note.ok) throw new Error(`GHL note create failed (${note.status})`);

    const tag = await addContactTags({ pit: PIT, contactId, tags: [TAG] });
    if (!tag.ok) throw new Error(`GHL tag apply failed (${tag.status})`);

    await sb.from("voice_agent_captures").update({
      status: "created", ghl_contact_id: contactId, error: null,
      updated_at: new Date().toISOString(),
    }).eq("id", saved.id);
    return json({ ok: true });
  } catch (e) {
    // Caller experience is unaffected; xAI keeps the transcript as the fallback record.
    await sb.from("voice_agent_captures").update({
      status: "error", error: String(e), updated_at: new Date().toISOString(),
    }).eq("id", saved.id);
    return json({ error: String(e) }, 500);
  }
});
```

- [ ] **Step 2: Typecheck + run all voice-agent tests**

Run: `deno check supabase/functions/voice-agent-capture/index.ts && deno test supabase/functions/_shared/voice-agent/`
Expected: check clean, 7 tests passed

- [ ] **Step 3: Commit**

```bash
git add supabase/functions/voice-agent-capture/index.ts
git commit -m "feat(voice-agent): capture webhook edge fn (gate, dedupe, GHL contact+note+tag)"
```

---

### Task 7: Deploy + live smoke test

Prereq: a Supabase access token for the CLI (Daniel logs in with `npx supabase login`, or sets `SUPABASE_ACCESS_TOKEN`; a fresh token may be needed — the last one was revoked).

- [ ] **Step 1: Generate + set the gate token (never echo it into chat/logs)**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
TOKEN="va_$(openssl rand -hex 16)"
npx supabase secrets set VOICE_AGENT_CAPTURE_TOKEN="$TOKEN"
printf '%s' "$TOKEN" > ~/.twins-voice-agent-token && chmod 600 ~/.twins-voice-agent-token
```

(`GHL_PIT` and `GHL_LOCATION_ID` already exist as secrets from call-intake. If Task 1 Step 5 rotated the PIT, re-set it now the same way.)

- [ ] **Step 2: Deploy (JWT verification off — xAI sends no Supabase JWT; the `?t=` gate is the auth)**

```bash
npx supabase functions deploy voice-agent-capture --no-verify-jwt
```

- [ ] **Step 3: Verify the gate.** Get the project URL via Supabase MCP `get_project_url` (project jwrpj…), then:

```bash
BASE="<project-url>/functions/v1/voice-agent-capture"
curl -s -o /dev/null -w "%{http_code}\n" -X POST "$BASE" -H "Content-Type: application/json" -d '{}'
```

Expected: `403`

- [ ] **Step 4: Verify invalid-payload handling**

```bash
T=$(cat ~/.twins-voice-agent-token)
curl -s -X POST "$BASE?t=$T" -H "Content-Type: application/json" -d '{"name":"no call id"}'
```

Expected: `{"error":"call_id is required"}` and (via MCP `execute_sql`) one `status='invalid'` row in `voice_agent_captures`.

- [ ] **Step 5: Happy-path smoke test with clearly-fake test data**

```bash
curl -s -X POST "$BASE?t=$T" -H "Content-Type: application/json" -d '{
  "call_id": "smoketest-001",
  "caller_phone": "6085550147",
  "callback_phone": "6085550147",
  "name": "ZZ-TEST VoiceAgent",
  "address": "1 Test St, Madison WI 53703",
  "property_type": "residential",
  "problem": "smoke test - door will not open",
  "preferred_window": "any weekday morning",
  "emergency": false,
  "uncertain_fields": ["address"],
  "summary": "Smoke test capture. Delete me."
}'
```

Expected: `{"ok":true}`. Then verify all of:
1. `voice_agent_captures` has a `status='created'` row with a `ghl_contact_id` (MCP `execute_sql`).
2. In GHL: contact "ZZ-TEST VoiceAgent" exists with the note (including a `VERIFY ON CALLBACK` line for address) and the `ai-captured-needs-confirm` tag.
3. Re-POST the same payload → `{"ok":true,"duplicate":true}` and still exactly one row for `smoketest-001`.

If the note or tag call failed with a version-related 4xx, apply the Task 5 fallback (`Version: 2021-07-28`), redeploy, re-test.

- [ ] **Step 6: Clean up test data.** Delete the "ZZ-TEST VoiceAgent" contact in the GHL UI (Contacts → search "ZZ-TEST" → select → Delete), and delete the smoke-test rows: `delete from voice_agent_captures where call_id = 'smoketest-001' or status = 'invalid';`

- [ ] **Step 7: Commit any fixes**

```bash
git add -A && git commit -m "fix(voice-agent): smoke-test fixes" || echo "nothing to fix"
```

---

### Task 8: xAI Voice Agent Builder configuration (playbook + content)

**Files:**
- Create: `docs/voice-agent/xai-agent-config.md` (twins-dash repo — the exported master copy Twins owns)

- [ ] **Step 1: Write the playbook doc** containing, verbatim and complete:

**(a) Setup steps:** log into the xAI console (console.x.ai) with the account holding Daniel's API key → Voice Agent Builder → create agent "Twins Garage Doors After-Hours" → note the agent's phone number (needed for Task 9 forwarding).

**(b) Greeting (exact text):**

> "Thanks for calling Twins Garage Doors. Our team is helping other customers right now, but I can take down what you need and have someone confirm your appointment first thing. What's going on with your garage door?"

**(c) Agent instructions (exact text, no em-dashes anywhere in spoken copy):**

```
You are the after-hours and overflow phone assistant for Twins Garage Doors,
a local garage door company in Madison, Wisconsin. You answer questions and
collect what the team needs to schedule service. You never book appointments
yourself. A Twins team member confirms every appointment.

VOICE: Friendly, local, plain spoken. Short sentences. No corporate jargon.
Never pushy.

WHAT YOU KNOW: Only what is in your knowledge base. Our phone is
(608) 888-8785. If a fact is not in your knowledge base, you do not know it.

HARD RULES:
1. Never invent a price, fee, financing term, or membership detail. If asked
   about cost and your knowledge base has no exact answer, say: "A technician
   will confirm exact pricing once we know what's going on with your door.
   I can take your details so we can get you scheduled."
2. Never promise a specific appointment time or same day service. Collect
   their preferred window. A team member confirms the actual time.
3. If you don't know something, do not guess. Take their details and say
   someone will follow up.
4. If the caller describes a stuck open door, a car trapped inside, or any
   safety issue, tell them: "Please call our main line at (608) 888-8785
   right away." Still collect their details first if they are willing.

WHEN SOMEONE NEEDS SERVICE, collect one question at a time:
1. Their name.
2. The service address, and whether it is a home or a business.
3. What is wrong or what they need.
4. The best callback number. Read it back to confirm.
5. Their preferred day or time window.

Then say: "Got it. Someone from Twins will confirm your appointment first
thing. You can also reach us any time at (608) 888-8785."

After the call, submit the lead with your submit_lead tool. Put any field you
are not fully sure you heard correctly into uncertain_fields. Never fill a
field you did not clearly hear; leave it null.
```

**(d) Knowledge base template** with the Section 5 facts checklist (hours, service area, services, brands, service-call fee policy, financing/warranty, never-say list) as fill-in slots marked `DANIEL SUPPLIES`, plus the already-confirmed facts (brand, Madison WI, phone (608) 888-8785, twinsgaragedoors.com).

**(e) Webhook tool definition** named `submit_lead`:
- URL: `<project-url>/functions/v1/voice-agent-capture?t=<VOICE_AGENT_CAPTURE_TOKEN>` (paste the token from `~/.twins-voice-agent-token` directly into the Builder console; never commit it — the doc holds the URL shape with `<token>` placeholder only)
- Method: POST, JSON body with this exact schema:

```json
{
  "type": "object",
  "required": ["call_id"],
  "properties": {
    "call_id": { "type": "string", "description": "The unique id of this call" },
    "caller_phone": { "type": ["string", "null"] },
    "callback_phone": { "type": ["string", "null"], "description": "Number the caller confirmed" },
    "name": { "type": ["string", "null"] },
    "address": { "type": ["string", "null"] },
    "property_type": { "type": ["string", "null"], "enum": ["residential", "commercial", null] },
    "problem": { "type": ["string", "null"] },
    "preferred_window": { "type": ["string", "null"] },
    "emergency": { "type": "boolean" },
    "uncertain_fields": { "type": "array", "items": { "type": "string" } },
    "summary": { "type": ["string", "null"] }
  }
}
```

- Firing rule: at the end of any call where the caller wanted service and gave at least a callback number (full or partial capture).

**(f) Voice + model settings:** pick a natural female or male US voice in the Builder (Daniel's call at config time); keep max call length ~10 min; enable xAI-side transcripts/observability.

- [ ] **Step 2: Fill the KB facts with Daniel.** Ask Daniel for the Section 5 facts (hours, service area, services, brands, fee policy, financing/warranty, never-say list) and write his answers into the doc. Facts he doesn't supply stay out of the KB entirely (the instructions already force the safe fallback).

- [ ] **Step 3: Configure the agent in the Builder console** per the doc (Daniel drives or screen-shares; the xAI API key never leaves the console). Record the agent's phone number in the doc.

- [ ] **Step 4: Test call directly to the agent's number** (skip GHL entirely): ask an FAQ, then request service, give all five fields. Verify: agent answers from KB, defers on a made-up pricing question, and the capture lands in GHL (contact + note + tag) and `voice_agent_captures`.

- [ ] **Step 5: Commit**

```bash
git add docs/voice-agent/xai-agent-config.md
git commit -m "docs(voice-agent): xai builder playbook, agent instructions, kb master copy"
```

---

### Task 9: GHL forwarding + Ivory's smart list (playbook + click-through)

**Files:**
- Create: `docs/voice-agent/ghl-forwarding-setup.md` (twins-dash repo)

- [ ] **Step 1: Write the click-through doc** using the exact mechanism verified in Task 1 Step 2:
  - Ring-timeout forward on the live inbound number → agent's phone number, 20 seconds (~4 rings; bump if Ivory needs longer).
  - After-hours forward → agent's phone number, schedule = outside the CSR hours Daniel supplied in Task 8 Step 2.
  - Smart list for Ivory: Contacts → filter tag = `ai-captured-needs-confirm` → save as "AI captured - needs confirm". Morning routine: call each, confirm the slot, book in HCP, remove the tag.

- [ ] **Step 2: Daniel or Aman clicks it through** in the Dunzo GHL per the doc.

- [ ] **Step 3: Commit**

```bash
git add docs/voice-agent/ghl-forwarding-setup.md
git commit -m "docs(voice-agent): ghl forwarding + morning smart list playbook"
```

---

### Task 10: End-to-end verification, PR, supervised window

- [ ] **Step 1: Run the spec's success criteria as live tests:**
  1. After-hours (or schedule-simulated) call to the Twins number → reaches the agent → answers a known FAQ correctly.
  2. Service-request call → all five fields captured → GHL contact + note + tag + `voice_agent_captures` row.
  3. Pricing question with no KB answer → agent defers, invents nothing.
  4. Daytime call answered by a human → does NOT forward. Daytime call left ringing 20s → forwards.
  5. Real caller's number appears in the capture (from caller ID or verbal confirm).
  6. Ivory's smart list shows the test captures.
  7. `docs/voice-agent/xai-agent-config.md` holds the full exported agent content.

- [ ] **Step 2: Clean up test captures** (remove tag from test contacts or delete test contacts in GHL UI; delete test rows from `voice_agent_captures`).

- [ ] **Step 3: Push + PR** (via GitHub API per memory reference_gh_via_api, not gh CLI):

```bash
git push -u origin voice-agent-capture
# POST /repos/palpulla/twins-dash/pulls  title: "feat: voice-agent-capture webhook for xAI voice agent"
```

- [ ] **Step 4: Start the 1-week supervised window.** Daniel/Ivory skim xAI transcripts + GHL captures each morning; tune KB + instructions from real calls. No automated reports (memory: no webhook-health alerts). After the week, Daniel calls go/no-go on trusting it.

---

## Task-order note

Tasks 2-7 (all code) can proceed in parallel with Task 1 verifications EXCEPT: do not configure forwarding (Task 9) until Task 1 confirms the mechanism, and do not go live until Task 8 Step 2's facts are in the KB. If Task 1 Step 2 fails, stop after Task 7 (the webhook is engine-agnostic and survives a switch to direct-SIP).
