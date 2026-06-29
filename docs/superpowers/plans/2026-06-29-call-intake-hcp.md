# Call-Intake to HCP Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically transcribe new-lead phone-call recordings from GHL, extract the caller's details without fabricating anything, and create a draft (unscheduled) Housecall Pro job for a human to confirm and schedule, deduping against live HCP so it never clobbers human entry.

**Architecture:** A Supabase edge-function pipeline on the jwrpj project. A GHL workflow POSTs each inbound call to a webhook edge function, which queues a row with a 10-minute grace delay. A pg_cron poller (every 5 min) invokes a processor edge function that fetches the recording, transcribes via Deepgram, extracts fields via Claude, dedupes the caller's phone against the synced HCP customer mirror, and on no-match creates an HCP customer → address → unscheduled job → note. An ET-gated SMS to Daniel fires via a GHL "Send SMS" workflow. All state lives in a `call_intake` table (silent audit, no health-alert pings).

**Tech Stack:** Supabase (Postgres, edge functions in Deno/TypeScript, pg_cron, pg_net), Deepgram Nova-3 STT, Anthropic Claude (extraction), Housecall Pro public API, GoHighLevel (LeadConnector) workflows, Deno standard test runner.

**Spec:** `docs/superpowers/specs/2026-06-29-call-intake-hcp-design.md`

**Repo:** All code in `/Users/daniel/twins-dashboard/twins-dash` (palpulla/twins-dash). Work in a branch, open a PR; do not commit pipeline code to `main` directly.

---

## File Structure

**New edge functions:**
- `supabase/functions/call-intake-webhook/index.ts` — receives the GHL call webhook, inserts a `pending` row (idempotent on GHL message id). Public, `?t=<token>` gated, `verify_jwt=false`.
- `supabase/functions/call-intake-process/index.ts` — cron-invoked processor; orchestrates due rows through transcribe → extract → dedupe → HCP write → SMS.

**New shared modules (`supabase/functions/_shared/call-intake/`):**
- `phone.ts` — phone normalization (to E.164-ish digits) + comparison.
- `sms-window.ts` — the ET time-gate (weekends OR weekday ≥17:00 America/New_York).
- `extract-schema.ts` — the extracted-fields TypeScript type + a runtime validator/normalizer for Claude's JSON.
- `deepgram.ts` — Deepgram Nova-3 transcription client.
- `extract.ts` — Claude extraction client (calls Anthropic Messages API, returns validated `Extracted`).
- `hcp-create.ts` — HCP create-customer → create-address → create-unscheduled-job → add-note (extends existing `_shared/hcp`).
- `dedupe.ts` — caller-phone lookup against the HCP customer mirror.
- `sms.ts` — `sendSmsViaGhl()` — POSTs to the configured GHL inbound-webhook to trigger a Send-SMS workflow.
- `notes.ts` — builds the HCP job-note body (transcript + recording link + extracted fields + VERIFY list).

**New migrations (`supabase/migrations/`):**
- `<ts>_call_intake_tables.sql` — `call_intake` table + `call_intake_config` singleton.
- `<ts>_call_intake_cron.sql` — `*/5 * * * *` poller invoking `call-intake-process`.

**New docs:**
- `docs/call-intake/ghl-workflow-setup.md` — the no-code GHL workflow steps (call webhook out + SMS webhook in) for Daniel/Aman.

**Config TOML:**
- `supabase/config.toml` — add `verify_jwt = false` entries for both new functions (mirror `ghl-webhook-1`).

---

## Phase 0: Verification & Prerequisites (do FIRST, gate everything)

These are live/manual checks. None should be skipped; each unblocks later phases. Record findings in the PR description.

### Task 0.1: Confirm GHL records the relevant calls

- [ ] **Step 1: Verify call recording is ON** for the GHL/LC number(s) Twins uses for inbound, in the Dunzo sub-account (Settings → Phone System → ensure recording enabled).
- [ ] **Step 2: Place a test inbound call** to the Twins number, let it forward to Daniel's cell, answer, talk ~30s, hang up. In GHL → the contact's Conversations, confirm a CALL entry appears WITH a playable recording.
- [ ] **Step 3: Inspect the webhook payload.** Temporarily build the GHL workflow from Task 10.1 pointing at a request-capture URL (e.g. webhook.site) and confirm the POST body contains: a call/message id, the caller phone, and a recording URL (field name and whether the URL is directly fetchable without auth). Note the exact field names.

Expected: recording exists for cell-forwarded answered calls, and the webhook carries a fetchable recording URL. If the URL is auth-gated, Phase 4 must fetch via the v2 API instead (see Task 4.2 fallback).

### Task 0.2: Confirm HCP write access and request shapes

- [ ] **Step 1:** Confirm the `HOUSECALL_PRO_API_KEY` secret on jwrpj belongs to a MAX-plan account with write scope (the existing dispatch client writes, so this is likely already true — confirm by reading `_shared/hcp/client.ts` usage).
- [ ] **Step 2: One live test write** using that key (a throwaway curl from your machine, not committed): create a test customer, add an address, create an unscheduled job, add a note, then delete/cancel the test job in HCP. Confirm:
  - `POST /customers` minimal required fields (name only vs name+something).
  - address is `POST /customers/{id}/addresses` (separate call).
  - `POST /jobs` with `customer_id` + `address_id` and NO `schedule` yields an unscheduled job (note the resulting `work_status`).
  - whether `schedule` must be nested vs flat (only relevant later; we omit it).
  - `POST /jobs/{id}/notes` body shape (`note` vs `content`, and the visibility flag).
- [ ] **Step 3:** Record the confirmed field names; Phase 5 code uses them.

### Task 0.3: Deepgram accuracy spot-check

- [ ] **Step 1:** Create a Deepgram account, get an API key, set `DEEPGRAM_API_KEY` as a jwrpj function secret (`supabase secrets set`).
- [ ] **Step 2:** Download 3–5 real Twins call recordings (from GHL) that include a spelled-out email and a street address.
- [ ] **Step 3:** Transcribe each with Nova-3 (`model=nova-3`, `smart_format=true`, `numerals=true`, `diarize=true`) via a throwaway curl. Eyeball accuracy on emails, street numbers, phone digits.
- [ ] **Step 4:** Decide: Deepgram good enough, or switch to the AssemblyAI fallback (Slam-1). Record the decision. The "never invent / flag uncertain" logic (Phase 4) is the safety net regardless.

### Task 0.4: Confirm the SMS send path

- [ ] **Step 1:** In the Dunzo GHL sub-account, build a workflow with an **Inbound Webhook** trigger and a **Send SMS** action that texts a fixed number (Daniel's cell) using `{{inboundWebhookRequest.message}}` as the body. Save and copy the inbound webhook URL.
- [ ] **Step 2:** `curl -X POST <inbound-webhook-url> -H 'content-type: application/json' -d '{"phone":"<daniel-cell>","message":"Twins call-intake test"}'` and confirm Daniel receives the SMS.
- [ ] **Step 3:** Store the URL as the `GHL_SMS_WEBHOOK_URL` jwrpj secret. (If this path fails, fall back to a standalone Twilio account + number; Phase 7 `sms.ts` interface stays the same, only the impl changes.)

### Task 0.5: Set remaining secrets

- [ ] **Step 1:** Ensure these jwrpj function secrets exist (set any missing via `supabase secrets set --project-ref jwrpjuqaynownxaoeayi`): `DEEPGRAM_API_KEY`, `ANTHROPIC_API_KEY`, `GHL_SMS_WEBHOOK_URL`, plus existing `HOUSECALL_PRO_API_KEY`, `SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`.
- [ ] **Step 2:** Pick the webhook gate token (a random string) for `call-intake-webhook`; you'll embed it in the function and the GHL workflow URL.

---

## Phase 1: Data model & config

### Task 1.1: Create the `call_intake` and `call_intake_config` tables

**Files:**
- Create: `supabase/migrations/<ts>_call_intake_tables.sql` (use a current UTC timestamp prefix, e.g. `20260629180000_call_intake_tables.sql`)

- [ ] **Step 1: Write the migration**

```sql
-- call_intake: one row per inbound call captured from GHL (silent audit trail)
CREATE TABLE IF NOT EXISTS public.call_intake (
  id                   uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  ghl_message_id       text NOT NULL UNIQUE,         -- idempotency key
  ghl_conversation_id  text,
  ghl_contact_id       text,
  ghl_location_id      text,
  caller_phone         text,                          -- normalized digits, authoritative
  call_at              timestamptz,
  recording_url        text,
  process_after        timestamptz NOT NULL,          -- now() + grace; cron gate
  status               text NOT NULL DEFAULT 'pending'
                         CHECK (status IN ('pending','processing','matched','created','skipped','error')),
  attempts             int NOT NULL DEFAULT 0,
  transcript           text,
  extracted            jsonb,
  dedupe_match         text,                          -- matched HCP customer id, if any
  hcp_customer_id      text,
  hcp_address_id       text,
  hcp_job_id           text,
  sms_sent_at          timestamptz,
  error                text,
  created_at           timestamptz NOT NULL DEFAULT now(),
  updated_at           timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS call_intake_due_idx
  ON public.call_intake (process_after)
  WHERE status = 'pending';

-- Singleton config row (mirrors csr_config pattern)
CREATE TABLE IF NOT EXISTS public.call_intake_config (
  id                int PRIMARY KEY DEFAULT 1,
  enabled           boolean NOT NULL DEFAULT true,
  grace_minutes     int NOT NULL DEFAULT 10,
  min_call_seconds  int NOT NULL DEFAULT 20,
  sms_recipient     text NOT NULL DEFAULT '',          -- Daniel's cell, set after deploy
  sms_timezone      text NOT NULL DEFAULT 'America/New_York',
  sms_weekday_hour  int NOT NULL DEFAULT 17,            -- >= 5pm ET on weekdays
  updated_at        timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT call_intake_config_singleton CHECK (id = 1)
);
INSERT INTO public.call_intake_config (id) VALUES (1) ON CONFLICT (id) DO NOTHING;

-- RLS: service-role only (no client access); table is internal observability.
ALTER TABLE public.call_intake ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.call_intake_config ENABLE ROW LEVEL SECURITY;
```

- [ ] **Step 2: Apply the migration** (via Supabase MCP `apply_migration` or CLI) to jwrpj.

Run: `supabase db push --project-ref jwrpjuqaynownxaoeayi` (or MCP `apply_migration`).
Expected: both tables created; `call_intake_config` has one row.

- [ ] **Step 3: Record the migration version** in `schema_migrations` if the history-desync quirk applies (per the repo's migration-history note: manually INSERT the version row after applying if the tracker doesn't pick it up).

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/<ts>_call_intake_tables.sql
git commit -m "feat(call-intake): call_intake + call_intake_config tables"
```

---

## Phase 2: Pure-logic modules (TDD)

These have no external dependencies and are unit-tested with `deno test`.

### Task 2.1: Phone normalization

**Files:**
- Create: `supabase/functions/_shared/call-intake/phone.ts`
- Test: `supabase/functions/_shared/call-intake/phone_test.ts`

- [ ] **Step 1: Write the failing test**

```typescript
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { normalizePhone, samePhone } from "./phone.ts";

Deno.test("normalizePhone strips formatting to 10-digit", () => {
  assertEquals(normalizePhone("(608) 888-8785"), "6088888785");
  assertEquals(normalizePhone("+1 608-888-8785"), "6088888785");
  assertEquals(normalizePhone("16088888785"), "6088888785");
});

Deno.test("normalizePhone returns null for junk", () => {
  assertEquals(normalizePhone(""), null);
  assertEquals(normalizePhone("123"), null);
  assertEquals(normalizePhone(null), null);
});

Deno.test("samePhone compares normalized", () => {
  assertEquals(samePhone("(608) 888-8785", "6088888785"), true);
  assertEquals(samePhone("608-111-2222", "6088888785"), false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `deno test supabase/functions/_shared/call-intake/phone_test.ts`
Expected: FAIL (module not found / functions undefined).

- [ ] **Step 3: Write minimal implementation**

```typescript
/** Normalize a US phone string to 10 digits, or null if not a valid 10-digit number. */
export function normalizePhone(input: string | null | undefined): string | null {
  if (!input) return null;
  let d = input.replace(/\D/g, "");
  if (d.length === 11 && d.startsWith("1")) d = d.slice(1);
  return d.length === 10 ? d : null;
}

export function samePhone(a: string | null | undefined, b: string | null | undefined): boolean {
  const na = normalizePhone(a);
  const nb = normalizePhone(b);
  return na !== null && na === nb;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `deno test supabase/functions/_shared/call-intake/phone_test.ts`
Expected: PASS (all 3 tests).

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/call-intake/phone.ts supabase/functions/_shared/call-intake/phone_test.ts
git commit -m "feat(call-intake): phone normalization util + tests"
```

### Task 2.2: SMS ET time-gate

**Files:**
- Create: `supabase/functions/_shared/call-intake/sms-window.ts`
- Test: `supabase/functions/_shared/call-intake/sms-window_test.ts`

- [ ] **Step 1: Write the failing test**

```typescript
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { isWithinSmsWindow } from "./sms-window.ts";

const cfg = { sms_timezone: "America/New_York", sms_weekday_hour: 17 };

Deno.test("weekend any hour is allowed", () => {
  // 2026-06-27 is a Saturday; 10:00 UTC
  assertEquals(isWithinSmsWindow(new Date("2026-06-27T10:00:00Z"), cfg), true);
});

Deno.test("weekday before 5pm ET is blocked", () => {
  // 2026-06-29 Mon 18:00 UTC = 14:00 ET -> blocked
  assertEquals(isWithinSmsWindow(new Date("2026-06-29T18:00:00Z"), cfg), false);
});

Deno.test("weekday at/after 5pm ET is allowed", () => {
  // 2026-06-29 Mon 21:30 UTC = 17:30 ET -> allowed
  assertEquals(isWithinSmsWindow(new Date("2026-06-29T21:30:00Z"), cfg), true);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `deno test supabase/functions/_shared/call-intake/sms-window_test.ts`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

```typescript
export interface SmsWindowCfg { sms_timezone: string; sms_weekday_hour: number; }

/** Returns the weekday (0=Sun..6=Sat) and hour (0-23) of `at` in the given IANA tz. */
function partsInTz(at: Date, tz: string): { dow: number; hour: number } {
  const fmt = new Intl.DateTimeFormat("en-US", {
    timeZone: tz, weekday: "short", hour: "2-digit", hour12: false,
  });
  const parts = fmt.formatToParts(at);
  const wd = parts.find((p) => p.type === "weekday")?.value ?? "Sun";
  const hourStr = parts.find((p) => p.type === "hour")?.value ?? "00";
  const map: Record<string, number> = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
  let hour = parseInt(hourStr, 10);
  if (hour === 24) hour = 0; // some runtimes emit "24" for midnight
  return { dow: map[wd] ?? 0, hour };
}

/** SMS allowed if weekend (Sat/Sun) OR weekday hour >= sms_weekday_hour, evaluated in tz. */
export function isWithinSmsWindow(at: Date, cfg: SmsWindowCfg): boolean {
  const { dow, hour } = partsInTz(at, cfg.sms_timezone);
  const isWeekend = dow === 0 || dow === 6;
  return isWeekend || hour >= cfg.sms_weekday_hour;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `deno test supabase/functions/_shared/call-intake/sms-window_test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/call-intake/sms-window.ts supabase/functions/_shared/call-intake/sms-window_test.ts
git commit -m "feat(call-intake): ET SMS time-gate + tests"
```

### Task 2.3: Extraction schema + validator

**Files:**
- Create: `supabase/functions/_shared/call-intake/extract-schema.ts`
- Test: `supabase/functions/_shared/call-intake/extract-schema_test.ts`

- [ ] **Step 1: Write the failing test**

```typescript
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { parseExtracted, EMPTY_EXTRACTED } from "./extract-schema.ts";

Deno.test("parseExtracted fills missing fields with null + stated=false", () => {
  const r = parseExtracted(JSON.stringify({
    first_name: { value: "Jane", stated: true },
    issue_description: { value: "Broken spring", stated: true },
  }));
  assertEquals(r.first_name, { value: "Jane", stated: true, note: null });
  assertEquals(r.email, { value: null, stated: false, note: null });
  assertEquals(r.issue_description.value, "Broken spring");
});

Deno.test("parseExtracted on garbage returns EMPTY_EXTRACTED", () => {
  assertEquals(parseExtracted("not json"), EMPTY_EXTRACTED());
  assertEquals(parseExtracted("{}").last_name, { value: null, stated: false, note: null });
});

Deno.test("parseExtracted coerces non-string values to null", () => {
  const r = parseExtracted(JSON.stringify({ first_name: { value: 123, stated: true } }));
  assertEquals(r.first_name.value, null);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `deno test supabase/functions/_shared/call-intake/extract-schema_test.ts`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

```typescript
export interface Field { value: string | null; stated: boolean; note: string | null; }
export interface Extracted {
  first_name: Field; last_name: Field; email: Field;
  street: Field; street_line_2: Field; city: Field; state: Field; zip: Field;
  phone: Field; issue_description: Field;
}

const KEYS: (keyof Extracted)[] = [
  "first_name","last_name","email","street","street_line_2","city","state","zip","phone","issue_description",
];

const emptyField = (): Field => ({ value: null, stated: false, note: null });

export function EMPTY_EXTRACTED(): Extracted {
  const o = {} as Extracted;
  for (const k of KEYS) o[k] = emptyField();
  return o;
}

function coerceField(raw: unknown): Field {
  if (!raw || typeof raw !== "object") return emptyField();
  const r = raw as Record<string, unknown>;
  const value = typeof r.value === "string" && r.value.trim() !== "" ? r.value.trim() : null;
  const stated = r.stated === true && value !== null;
  const note = typeof r.note === "string" && r.note.trim() !== "" ? r.note.trim() : null;
  return { value, stated, note };
}

/** Parse Claude's JSON into a complete Extracted, tolerating missing keys / bad JSON. */
export function parseExtracted(jsonText: string): Extracted {
  let obj: Record<string, unknown> = {};
  try {
    const parsed = JSON.parse(jsonText);
    if (parsed && typeof parsed === "object") obj = parsed as Record<string, unknown>;
    else return EMPTY_EXTRACTED();
  } catch {
    return EMPTY_EXTRACTED();
  }
  const out = {} as Extracted;
  for (const k of KEYS) out[k] = coerceField(obj[k]);
  return out;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `deno test supabase/functions/_shared/call-intake/extract-schema_test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/call-intake/extract-schema.ts supabase/functions/_shared/call-intake/extract-schema_test.ts
git commit -m "feat(call-intake): extracted-fields schema + tolerant parser + tests"
```

### Task 2.4: HCP job-note builder

**Files:**
- Create: `supabase/functions/_shared/call-intake/notes.ts`
- Test: `supabase/functions/_shared/call-intake/notes_test.ts`

- [ ] **Step 1: Write the failing test**

```typescript
import { assert, assertStringIncludes } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { buildJobNote } from "./notes.ts";
import { EMPTY_EXTRACTED } from "./extract-schema.ts";

Deno.test("note lists stated fields and flags unstated under VERIFY", () => {
  const ex = EMPTY_EXTRACTED();
  ex.first_name = { value: "Jane", stated: true, note: null };
  ex.email = { value: null, stated: false, note: "not given" };
  const note = buildJobNote({
    extracted: ex, transcript: "hello world", recordingUrl: "https://rec/1.wav",
  });
  assertStringIncludes(note, "Jane");
  assertStringIncludes(note, "VERIFY ON CALLBACK");
  assertStringIncludes(note, "email");
  assertStringIncludes(note, "https://rec/1.wav");
  assertStringIncludes(note, "hello world");
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `deno test supabase/functions/_shared/call-intake/notes_test.ts`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

```typescript
import { Extracted } from "./extract-schema.ts";

const LABELS: Record<keyof Extracted, string> = {
  first_name: "First name", last_name: "Last name", email: "Email",
  street: "Street", street_line_2: "Street line 2", city: "City",
  state: "State", zip: "Zip", phone: "Phone", issue_description: "Issue",
};

export function buildJobNote(args: {
  extracted: Extracted; transcript: string; recordingUrl: string | null;
}): string {
  const ex = args.extracted;
  const keys = Object.keys(LABELS) as (keyof Extracted)[];

  const captured: string[] = [];
  const verify: string[] = [];
  for (const k of keys) {
    const f = ex[k];
    if (f.stated && f.value) {
      captured.push(`- ${LABELS[k]}: ${f.value}`);
    } else {
      const why = f.note ? ` (${f.note})` : "";
      verify.push(`- ${LABELS[k]}${why}`);
    }
  }

  const parts = [
    "AUTO-CAPTURED FROM CALL — confirm details before scheduling.",
    "",
    "Captured:",
    captured.length ? captured.join("\n") : "- (nothing captured with confidence)",
  ];
  if (verify.length) {
    parts.push("", "⚠️ VERIFY ON CALLBACK (missing/uncertain):", verify.join("\n"));
  }
  if (args.recordingUrl) parts.push("", `Recording: ${args.recordingUrl}`);
  parts.push("", "Transcript:", args.transcript || "(none)");
  return parts.join("\n");
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `deno test supabase/functions/_shared/call-intake/notes_test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/call-intake/notes.ts supabase/functions/_shared/call-intake/notes_test.ts
git commit -m "feat(call-intake): HCP job-note builder + tests"
```

---

## Phase 3: Webhook receiver

### Task 3.1: `call-intake-webhook` edge function

**Files:**
- Create: `supabase/functions/call-intake-webhook/index.ts`
- Modify: `supabase/config.toml` (add `[functions.call-intake-webhook] verify_jwt = false`)

- [ ] **Step 1: Write the function** (mirror `csr-eod-response` boilerplate for CORS + `?t=` gate + service-role client)

```typescript
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.45.0";
import { normalizePhone } from "../_shared/call-intake/phone.ts";

const CORS = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, content-type",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
};
const WEBHOOK_TOKEN = Deno.env.get("CALL_INTAKE_WEBHOOK_TOKEN") ?? "";

function json(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), { status, headers: { ...CORS, "Content-Type": "application/json" } });
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: CORS });
  if (req.method !== "POST") return new Response("method not allowed", { status: 405, headers: CORS });
  const url = new URL(req.url);
  if (!WEBHOOK_TOKEN || url.searchParams.get("t") !== WEBHOOK_TOKEN) {
    return new Response("forbidden", { status: 403, headers: CORS });
  }

  try {
    const sb = createClient(Deno.env.get("SUPABASE_URL")!, Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!, {
      auth: { persistSession: false, autoRefreshToken: false },
    });

    const p = await req.json().catch(() => null);
    if (!p) return json({ error: "bad json" }, 400);

    // Field names per Task 0.1 findings; these are the expected defaults.
    const messageId   = String(p.messageId ?? p.message_id ?? p.id ?? "").trim();
    const callDuration = Number(p.callDuration ?? p.call_duration ?? 0);
    const direction   = String(p.direction ?? "").toLowerCase();
    if (!messageId) return json({ error: "missing messageId" }, 400);

    const { data: cfg } = await sb.from("call_intake_config").select("*").eq("id", 1).single();
    if (!cfg?.enabled) return json({ ok: true, gated: "disabled" });

    // Filter: inbound only, min duration (skip junk/voicemail-only)
    if (direction && direction !== "inbound") return json({ ok: true, skipped: "not-inbound" });
    if (callDuration && callDuration < (cfg.min_call_seconds ?? 20)) {
      return json({ ok: true, skipped: "too-short" });
    }

    const row = {
      ghl_message_id: messageId,
      ghl_conversation_id: p.conversationId ?? p.conversation_id ?? null,
      ghl_contact_id: p.contactId ?? p.contact_id ?? null,
      ghl_location_id: p.locationId ?? p.location_id ?? null,
      caller_phone: normalizePhone(p.from ?? p.phone ?? p.caller ?? null),
      call_at: p.dateAdded ?? p.timestamp ?? new Date().toISOString(),
      recording_url: p.recordingUrl ?? (Array.isArray(p.attachments) ? p.attachments[0] : null) ?? null,
      process_after: new Date(Date.now() + (cfg.grace_minutes ?? 10) * 60_000).toISOString(),
      status: "pending",
    };

    // Idempotent on ghl_message_id (table has UNIQUE).
    const { error } = await sb.from("call_intake").upsert(row, { onConflict: "ghl_message_id", ignoreDuplicates: true });
    if (error) return json({ error: error.message }, 500);

    return json({ ok: true, queued: messageId });
  } catch (e) {
    return json({ error: String(e) }, 500);
  }
});
```

- [ ] **Step 2: Add config.toml entry**

```toml
[functions.call-intake-webhook]
verify_jwt = false
```

- [ ] **Step 3: Set the token secret**

Run: `supabase secrets set CALL_INTAKE_WEBHOOK_TOKEN=<random> --project-ref jwrpjuqaynownxaoeayi`

- [ ] **Step 4: Deploy and smoke-test**

Run: `supabase functions deploy call-intake-webhook --project-ref jwrpjuqaynownxaoeayi`
Then: `curl -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/call-intake-webhook?t=<token>" -H 'content-type: application/json' -d '{"messageId":"test-1","direction":"inbound","callDuration":45,"from":"6085551234","recordingUrl":"https://example.com/r.wav"}'`
Expected: `{"ok":true,"queued":"test-1"}`. Re-run the same curl → still ok, but no duplicate row (verify `select count(*) from call_intake where ghl_message_id='test-1'` returns 1).

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/call-intake-webhook/index.ts supabase/config.toml
git commit -m "feat(call-intake): webhook receiver (idempotent queue + grace delay)"
```

---

## Phase 4: Transcription & extraction clients

### Task 4.1: Deepgram client

**Files:**
- Create: `supabase/functions/_shared/call-intake/deepgram.ts`

- [ ] **Step 1: Write the implementation** (no unit test — it's a thin network wrapper; covered by the live test in Task 0.3 and end-to-end in Phase 10)

```typescript
const DG_URL = "https://api.deepgram.com/v1/listen";

export interface TranscribeResult { transcript: string; raw: unknown; }

/** Transcribe a recording by URL using Deepgram Nova-3. Throws on non-2xx. */
export async function transcribeUrl(args: {
  apiKey: string; recordingUrl: string; keyterms?: string[];
}): Promise<TranscribeResult> {
  const params = new URLSearchParams({
    model: "nova-3", smart_format: "true", numerals: "true", diarize: "true", punctuate: "true",
  });
  for (const k of args.keyterms ?? []) params.append("keyterm", k);

  const res = await fetch(`${DG_URL}?${params.toString()}`, {
    method: "POST",
    headers: { Authorization: `Token ${args.apiKey}`, "Content-Type": "application/json" },
    body: JSON.stringify({ url: args.recordingUrl }),
  });
  if (!res.ok) throw new Error(`Deepgram ${res.status}: ${await res.text()}`);
  const data = await res.json();
  const transcript: string =
    data?.results?.channels?.[0]?.alternatives?.[0]?.transcript ?? "";
  return { transcript, raw: data };
}
```

- [ ] **Step 2: Commit**

```bash
git add supabase/functions/_shared/call-intake/deepgram.ts
git commit -m "feat(call-intake): Deepgram Nova-3 transcription client"
```

### Task 4.2: Recording fetch fallback (v2 API) — only if Task 0.1 found the URL is auth-gated

**Files:**
- Modify: `supabase/functions/_shared/call-intake/deepgram.ts` (add an upload-bytes path)

- [ ] **Step 1:** If the GHL recording URL is NOT publicly fetchable by Deepgram, add a function to fetch the audio bytes from GHL's v2 endpoint (`GET https://services.leadconnectorhq.com/conversations/messages/{messageId}/locations/{locationId}/recording` with `Authorization: Bearer <GHL_PIT>` and `Version: 2021-07-28`), then POST the bytes to Deepgram with `Content-Type: audio/wav`. Add `GHL_PIT` secret. Otherwise skip this task.

```typescript
export async function transcribeBytes(args: {
  apiKey: string; audio: ArrayBuffer; contentType: string; keyterms?: string[];
}): Promise<TranscribeResult> {
  const params = new URLSearchParams({
    model: "nova-3", smart_format: "true", numerals: "true", diarize: "true", punctuate: "true",
  });
  for (const k of args.keyterms ?? []) params.append("keyterm", k);
  const res = await fetch(`${DG_URL}?${params.toString()}`, {
    method: "POST",
    headers: { Authorization: `Token ${args.apiKey}`, "Content-Type": args.contentType },
    body: args.audio,
  });
  if (!res.ok) throw new Error(`Deepgram ${res.status}: ${await res.text()}`);
  const data = await res.json();
  return { transcript: data?.results?.channels?.[0]?.alternatives?.[0]?.transcript ?? "", raw: data };
}
```

- [ ] **Step 2: Commit** (if implemented)

```bash
git add supabase/functions/_shared/call-intake/deepgram.ts
git commit -m "feat(call-intake): v2 recording-bytes transcription fallback"
```

### Task 4.3: Claude extraction client

**Files:**
- Create: `supabase/functions/_shared/call-intake/extract.ts`

- [ ] **Step 1: Write the implementation** (returns a validated `Extracted` via `parseExtracted`)

```typescript
import { Extracted, parseExtracted, EMPTY_EXTRACTED } from "./extract-schema.ts";

const ANTHROPIC_URL = "https://api.anthropic.com/v1/messages";
const MODEL = "claude-sonnet-4-6";

const SYSTEM = `You extract caller contact details from a transcribed phone call for a garage-door company.
Return ONLY a JSON object. For each field return {"value": <string|null>, "stated": <bool>, "note": <string|null>}.
RULES:
- Use ONLY information the caller clearly stated. NEVER infer, guess, autocomplete, or normalize beyond what was said.
- If a field was not clearly stated, set value=null, stated=false. Optionally add a short note (e.g. "spelled unclearly").
- Do not invent zip codes, area codes, email domains, or street types.
Fields: first_name, last_name, email, street, street_line_2, city, state, zip, phone, issue_description.`;

export async function extractFromTranscript(args: {
  apiKey: string; transcript: string;
}): Promise<Extracted> {
  if (!args.transcript.trim()) return EMPTY_EXTRACTED();
  const res = await fetch(ANTHROPIC_URL, {
    method: "POST",
    headers: {
      "x-api-key": args.apiKey,
      "anthropic-version": "2023-06-01",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      model: MODEL,
      max_tokens: 1024,
      system: SYSTEM,
      messages: [{ role: "user", content: `Transcript:\n${args.transcript}\n\nReturn the JSON now.` }],
    }),
  });
  if (!res.ok) throw new Error(`Anthropic ${res.status}: ${await res.text()}`);
  const data = await res.json();
  const text: string = data?.content?.[0]?.text ?? "";
  // Strip code fences if present, then parse tolerantly.
  const cleaned = text.replace(/^```(?:json)?/i, "").replace(/```$/, "").trim();
  return parseExtracted(cleaned);
}
```

- [ ] **Step 2: Commit**

```bash
git add supabase/functions/_shared/call-intake/extract.ts
git commit -m "feat(call-intake): Claude transcript extraction (strict, no-fabrication)"
```

---

## Phase 5: HCP create module

### Task 5.1: `hcp-create.ts` (customer → address → unscheduled job → note)

**Files:**
- Create: `supabase/functions/_shared/call-intake/hcp-create.ts`

Use the field names confirmed in Task 0.2. The code below uses the documented defaults; adjust if Task 0.2 found differences.

- [ ] **Step 1: Write the implementation**

```typescript
const HCP_BASE = "https://api.housecallpro.com";

async function hcpPost(apiKey: string, path: string, body: unknown): Promise<any> {
  const res = await fetch(`${HCP_BASE}${path}`, {
    method: "POST",
    headers: { Authorization: `Token ${apiKey}`, "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new Error(`HCP POST ${path} ${res.status}: ${await res.text()}`);
  return await res.json();
}

import { Extracted } from "./extract-schema.ts";

export interface HcpCreateResult { customerId: string; addressId: string | null; jobId: string; }

export async function createDraftFromExtracted(args: {
  apiKey: string; extracted: Extracted; callerPhone: string | null; noteBody: string;
}): Promise<HcpCreateResult> {
  const ex = args.extracted;

  // 1. Customer (name required; fall back so we never send empty names)
  const customer = await hcpPost(args.apiKey, "/customers", {
    first_name: ex.first_name.value ?? "Unknown",
    last_name: ex.last_name.value ?? "Caller",
    email: ex.email.value ?? undefined,
    mobile_number: ex.phone.value ?? args.callerPhone ?? undefined,
    lead_source: "Phone call (auto-captured)",
    tags: ["AI-captured", "from call", "needs confirm"],
  });
  const customerId = String(customer.id ?? customer.customer?.id);

  // 2. Address (only if we have at least street + city + state + zip)
  let addressId: string | null = null;
  if (ex.street.value && ex.city.value && ex.state.value && ex.zip.value) {
    const addr = await hcpPost(args.apiKey, `/customers/${customerId}/addresses`, {
      street: ex.street.value,
      street_line_2: ex.street_line_2.value ?? undefined,
      city: ex.city.value,
      state: ex.state.value,
      zip: ex.zip.value,
    });
    addressId = String(addr.id ?? addr.address?.id);
  }

  // 3. Unscheduled job (NO schedule object => needs scheduling)
  const job = await hcpPost(args.apiKey, "/jobs", {
    customer_id: customerId,
    ...(addressId ? { address_id: addressId } : {}),
    description: ex.issue_description.value ?? "Auto-captured call lead — see notes",
    tags: ["AI-captured", "from call", "needs confirm"],
  });
  const jobId = String(job.id ?? job.job?.id);

  // 4. Note (transcript + recording + extracted + VERIFY list)
  await hcpPost(args.apiKey, `/jobs/${jobId}/notes`, { note: args.noteBody });

  return { customerId, addressId, jobId };
}
```

- [ ] **Step 2: Commit**

```bash
git add supabase/functions/_shared/call-intake/hcp-create.ts
git commit -m "feat(call-intake): HCP draft creation (customer/address/unscheduled job/note)"
```

---

## Phase 6: Dedupe

### Task 6.1: Phone dedupe against the HCP customer mirror

**Files:**
- Create: `supabase/functions/_shared/call-intake/dedupe.ts`

The mirror lives in the synced HCP data. Confirm the exact table/column in Task 0.2 / by inspecting `sync-hcp-jobs`; the code below assumes customer phones are queryable. Adjust the table name if needed.

- [ ] **Step 1: Write the implementation**

```typescript
import { SupabaseClient } from "https://esm.sh/@supabase/supabase-js@2.45.0";
import { normalizePhone } from "./phone.ts";

/**
 * Returns a matched HCP customer id if the caller phone already exists in the synced
 * HCP customer mirror, else null. Implementation note: the mirror stores phone digits in
 * hcp_data; we compare normalized 10-digit numbers. Adjust the source table/columns to
 * match the actual sync schema confirmed during Task 0.2.
 */
export async function findExistingCustomer(
  sb: SupabaseClient, callerPhone: string | null,
): Promise<string | null> {
  const phone = normalizePhone(callerPhone);
  if (!phone) return null;

  // Query the customers mirror. Replace 'hcp_customers' / column names with the real ones.
  const { data, error } = await sb
    .from("hcp_customers")
    .select("hcp_customer_id, mobile_number, home_number, work_number")
    .or(
      `mobile_number.ilike.%${phone}%,home_number.ilike.%${phone}%,work_number.ilike.%${phone}%`,
    )
    .limit(5);
  if (error || !data) return null;

  for (const c of data) {
    if ([c.mobile_number, c.home_number, c.work_number].some((n) => normalizePhone(n) === phone)) {
      return String(c.hcp_customer_id);
    }
  }
  return null;
}
```

- [ ] **Step 2: Verify the real mirror schema.** Inspect `sync-hcp-jobs/index.ts` (and the DB) to find where customer phone numbers are stored. Update the table/column names above to match before deploying. Run a quick `select` against jwrpj to confirm a known customer's phone matches.

- [ ] **Step 3: Commit**

```bash
git add supabase/functions/_shared/call-intake/dedupe.ts
git commit -m "feat(call-intake): caller-phone dedupe vs HCP customer mirror"
```

---

## Phase 7: SMS

### Task 7.1: `sms.ts` — send via GHL workflow webhook

**Files:**
- Create: `supabase/functions/_shared/call-intake/sms.ts`

- [ ] **Step 1: Write the implementation**

```typescript
/** Fire-and-confirm SMS to Daniel by POSTing to the GHL inbound-webhook that runs a Send-SMS workflow. */
export async function sendSmsViaGhl(args: {
  webhookUrl: string; phone: string; message: string;
}): Promise<{ ok: boolean; status: number }> {
  const res = await fetch(args.webhookUrl, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ phone: args.phone, message: args.message }),
  });
  return { ok: res.ok, status: res.status };
}
```

- [ ] **Step 2: Commit**

```bash
git add supabase/functions/_shared/call-intake/sms.ts
git commit -m "feat(call-intake): SMS-to-Daniel via GHL Send-SMS workflow webhook"
```

---

## Phase 8: Processor

### Task 8.1: `call-intake-process` edge function

**Files:**
- Create: `supabase/functions/call-intake-process/index.ts`
- Modify: `supabase/config.toml` (add `[functions.call-intake-process] verify_jwt = false`)

- [ ] **Step 1: Write the function**

```typescript
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.45.0";
import { transcribeUrl } from "../_shared/call-intake/deepgram.ts";
import { extractFromTranscript } from "../_shared/call-intake/extract.ts";
import { findExistingCustomer } from "../_shared/call-intake/dedupe.ts";
import { createDraftFromExtracted } from "../_shared/call-intake/hcp-create.ts";
import { buildJobNote } from "../_shared/call-intake/notes.ts";
import { isWithinSmsWindow } from "../_shared/call-intake/sms-window.ts";
import { sendSmsViaGhl } from "../_shared/call-intake/sms.ts";

const CORS = { "Access-Control-Allow-Origin": "*", "Access-Control-Allow-Headers": "authorization, content-type" };
const MAX_ATTEMPTS = 4;
const KEYTERMS = ["Twins Garage Doors", "LiftMaster", "Chamberlain", "garage door", "opener", "spring"];

function json(b: unknown, s = 200) {
  return new Response(JSON.stringify(b), { status: s, headers: { ...CORS, "Content-Type": "application/json" } });
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: CORS });
  const sb = createClient(Deno.env.get("SUPABASE_URL")!, Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!, {
    auth: { persistSession: false, autoRefreshToken: false },
  });

  const { data: cfg } = await sb.from("call_intake_config").select("*").eq("id", 1).single();
  if (!cfg?.enabled) return json({ ok: true, gated: "disabled" });

  const nowIso = new Date().toISOString();
  const { data: due, error } = await sb
    .from("call_intake").select("*")
    .eq("status", "pending").lte("process_after", nowIso)
    .order("process_after", { ascending: true }).limit(10);
  if (error) return json({ error: error.message }, 500);
  if (!due || due.length === 0) return json({ ok: true, processed: 0 });

  const DG = Deno.env.get("DEEPGRAM_API_KEY")!;
  const ANTHROPIC = Deno.env.get("ANTHROPIC_API_KEY")!;
  const HCP = Deno.env.get("HOUSECALL_PRO_API_KEY")!;
  const SMS_URL = Deno.env.get("GHL_SMS_WEBHOOK_URL") ?? "";

  let processed = 0;
  for (const row of due) {
    // Claim the row (optimistic): flip to processing, bump attempts.
    const { data: claimed } = await sb.from("call_intake")
      .update({ status: "processing", attempts: row.attempts + 1, updated_at: nowIso })
      .eq("id", row.id).eq("status", "pending").select("id").single();
    if (!claimed) continue; // another invocation took it

    try {
      if (!row.recording_url) throw new Error("no recording_url");

      const { transcript } = await transcribeUrl({ apiKey: DG, recordingUrl: row.recording_url, keyterms: KEYTERMS });
      const extracted = await extractFromTranscript({ apiKey: ANTHROPIC, transcript });

      const matchId = await findExistingCustomer(sb, row.caller_phone);
      if (matchId) {
        await sb.from("call_intake").update({
          status: "matched", transcript, extracted, dedupe_match: matchId, error: null,
          updated_at: new Date().toISOString(),
        }).eq("id", row.id);
        processed++;
        continue; // no ticket, no SMS
      }

      const noteBody = buildJobNote({ extracted, transcript, recordingUrl: row.recording_url });
      const created = await createDraftFromExtracted({
        apiKey: HCP, extracted, callerPhone: row.caller_phone, noteBody,
      });

      // SMS (gated). Never let an SMS failure fail the row.
      let smsSentAt: string | null = null;
      if (SMS_URL && cfg.sms_recipient && isWithinSmsWindow(new Date(), cfg)) {
        const jobLink = `https://pro.housecallpro.com/app/jobs/${created.jobId}`;
        const name = [extracted.first_name.value, extracted.last_name.value].filter(Boolean).join(" ") || "Unknown caller";
        const msg = `📞 Lead captured: ${name} (${row.caller_phone ?? "no #"}) — draft in HCP: ${jobLink}`;
        try {
          const r = await sendSmsViaGhl({ webhookUrl: SMS_URL, phone: cfg.sms_recipient, message: msg });
          if (r.ok) smsSentAt = new Date().toISOString();
        } catch (_) { /* swallow; recorded as null */ }
      }

      await sb.from("call_intake").update({
        status: "created", transcript, extracted,
        hcp_customer_id: created.customerId, hcp_address_id: created.addressId, hcp_job_id: created.jobId,
        sms_sent_at: smsSentAt, error: null, updated_at: new Date().toISOString(),
      }).eq("id", row.id);
      processed++;
    } catch (e) {
      // Re-queue for retry unless we've exhausted attempts.
      const exhausted = row.attempts + 1 >= MAX_ATTEMPTS;
      await sb.from("call_intake").update({
        status: exhausted ? "error" : "pending",
        process_after: exhausted ? row.process_after : new Date(Date.now() + 5 * 60_000).toISOString(),
        error: String(e), updated_at: new Date().toISOString(),
      }).eq("id", row.id);
    }
  }

  return json({ ok: true, processed });
});
```

- [ ] **Step 2: Add config.toml entry**

```toml
[functions.call-intake-process]
verify_jwt = false
```

- [ ] **Step 3: Deploy**

Run: `supabase functions deploy call-intake-process --project-ref jwrpjuqaynownxaoeayi`
Expected: deploy success.

- [ ] **Step 4: Manual invoke against the seeded test row** (from Task 3.1; give it a real recording URL first, or expect a clean `error` status)

Run: `curl -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/call-intake-process"`
Expected: `{"ok":true,"processed":N}`. Inspect the `call_intake` row's `status`, `transcript`, `extracted`.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/call-intake-process/index.ts supabase/config.toml
git commit -m "feat(call-intake): processor (transcribe/extract/dedupe/HCP/SMS) + retry"
```

---

## Phase 9: Cron

### Task 9.1: 5-minute poller

**Files:**
- Create: `supabase/migrations/<ts>_call_intake_cron.sql`

- [ ] **Step 1: Write the migration** (no DST gating needed — the ET SMS gate lives in the function; the poller just runs every 5 min)

```sql
SELECT cron.schedule(
  'call-intake-process-5min',
  '*/5 * * * *',
  $$
  SELECT net.http_post(
    url := 'https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/call-intake-process',
    headers := jsonb_build_object('Content-Type', 'application/json'),
    body := jsonb_build_object('source', 'pg_cron'),
    timeout_milliseconds := 120000
  );
  $$
);
```

- [ ] **Step 2: Apply** (MCP `apply_migration` or `supabase db push`).
Expected: `select jobname from cron.job where jobname = 'call-intake-process-5min'` returns the row.

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/<ts>_call_intake_cron.sql
git commit -m "feat(call-intake): pg_cron 5-min processor poller"
```

---

## Phase 10: GHL workflow + end-to-end verification

### Task 10.1: GHL inbound-call webhook workflow (no-code)

**Files:**
- Create: `docs/call-intake/ghl-workflow-setup.md`

- [ ] **Step 1: Write the setup doc** with exact GHL steps:
  - Workflow trigger: **Call Details**, Call Direction = Incoming, (optional) Duration filter.
  - Action: **Custom Webhook** → POST to `https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/call-intake-webhook?t=<token>`, JSON body mapping the GHL variables found in Task 0.1: `messageId`, `conversationId`, `contactId`, `locationId`, `from` (caller phone), `callDuration`, `direction`, `recordingUrl`, `dateAdded`.
  - Note timing: if the recording URL isn't populated at call-end, add a short Wait step before the webhook, or rely on the processor's retry to pick up the recording once ready.
- [ ] **Step 2:** Daniel/Aman build the workflow in the Dunzo sub-account per the doc.
- [ ] **Step 3: Commit the doc.**

```bash
git add docs/call-intake/ghl-workflow-setup.md
git commit -m "docs(call-intake): GHL inbound-call webhook workflow setup"
```

### Task 10.2: End-to-end live test

- [ ] **Step 1:** Set `call_intake_config.sms_recipient` to Daniel's cell; confirm `enabled=true`, `grace_minutes=10`.
- [ ] **Step 2: New-lead path.** From a phone NOT in HCP, call the Twins number, leave a full lead (name, spelled email, address, issue). Wait ~15 min. Confirm: a `call_intake` row went `pending → created`; an HCP unscheduled draft job exists with the note (transcript + recording + VERIFY list); and (if within the ET window) Daniel got the SMS.
- [ ] **Step 3: Dedupe path.** Repeat from a number that IS an existing HCP customer. Confirm the row ends `matched`, NO new HCP job, NO SMS.
- [ ] **Step 4: Grace path.** Call from a new number, then within 10 min manually create that customer in HCP (simulating live CSR entry). Confirm the processor dedupes to `matched` and creates no duplicate.
- [ ] **Step 5: SMS-gate path.** Trigger a capture during a weekday before 5pm ET; confirm the draft is created but NO SMS is sent.
- [ ] **Step 6: Record results in the PR; flip `enabled` on for good.**

### Task 10.3: Open the PR

- [ ] **Step 1:** Push the branch and open a PR to `palpulla/twins-dash` summarizing the pipeline, the Phase 0 findings, and the live-test results. (Use the GitHub API per the repo's no-gh-CLI convention.)

---

## Self-Review (completed)

- **Spec coverage:** trigger/capture (P3, T10.1), grace+queue (T1.1, T3.1, T9.1), transcription (T4.1/4.2), extraction no-fabrication (T2.3, T4.3), dedupe (T6.1, T10.2 grace path), HCP unscheduled draft + note (T5.1, T2.4), SMS-only ET-gated (T2.2, T7.1, T8.1), data model/idempotency (T1.1, T3.1), config/secrets (T0.5, T1.1), failure handling/retry (T8.1), out-of-scope no-auto-schedule (no schedule object in T5.1). All spec sections map to tasks.
- **Placeholder scan:** no TBD/TODO; the only deliberately deferred decisions are the live-confirmed field names (Task 0.1/0.2) and the mirror table name (Task 6.1 Step 2), each with an explicit confirm-and-adjust step and working default code.
- **Type consistency:** `Extracted`/`Field` defined once in `extract-schema.ts`, consumed identically in `extract.ts`, `notes.ts`, `hcp-create.ts`, `call-intake-process`. `normalizePhone` signature consistent across `phone.ts`, `dedupe.ts`, webhook. `isWithinSmsWindow(date, cfg)` consistent between test, module, and processor.
```
