# GHL Customer Messaging Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send Twins' relationship messages (estimate follow-up, booking confirmation, reminder, thank-you, and the review request) reliably through a single GHL number, triggered off HCP ticket state, with a self-healing sweep so it fires 100% of the time and never double-texts.

**Architecture:** A frequent, idempotent Supabase sweep (`messaging-bridge`, pg_cron every 15 min) reads the `jobs` table that `hcp-webhook` already keeps fresh, computes which stage messages are *due* for each ticket (pure `stage-resolver`), and for any not yet in `message_send_log` it upserts the customer into GHL by phone and adds a stage **tag**. GHL workflows (configured by Daniel/Aman, tag-triggered) own the copy and do the actual send from the one number. The send-log makes every send exactly-once and provable; a silent SQL health view surfaces coverage. Pre-booking lead messages and HCP's native on-my-way + invoice texts are out of scope (handled in GHL natively and by HCP respectively).

**Tech Stack:** Supabase (Postgres + pg_cron + Edge Functions on Deno), GHL v1 REST API (`https://rest.gohighlevel.com/v1`), TypeScript, Deno test. Repo: `twins-dash` (palpulla/twins-dash), project `jwrpjuqaynownxaoeayi`.

**Scope note:** This plan is Phase 1 (the fact-free, highest-value stages). Retention (membership / annual tune-up / win-back) and the held booking-rate matcher fix are explicitly deferred to separate plans (see end). The design spec is `docs/superpowers/specs/2026-06-23-ghl-messaging-pipeline-design.md`.

**Prerequisite (not code):** Confirm the existing GHL API key (Supabase secret used by `sync-ghl-contacts`) can create contacts and add tags via the v1 API, and that the GHL location is the Dunzo "Twins Garage Doors" location (`iRUlbIBg7PzSfLrPiR2j`). If the key is read-only, get a key with contact write scope before Task 4.

---

## File structure

**Create:**
- `supabase/functions/_shared/messaging/stages.ts` — stage constants + types (single source of truth, imported everywhere).
- `supabase/functions/_shared/messaging/stage-resolver.ts` — pure: a ticket row + `now` → due stages.
- `supabase/functions/_shared/messaging/__tests__/stage-resolver.test.ts` — resolver tests.
- `supabase/functions/_shared/ghl/__tests__/ghl-write.test.ts` — tests for the new GHL write helpers.
- `supabase/functions/messaging-bridge/index.ts` — the sweep orchestrator (cron entrypoint).
- `supabase/migrations/20260623120000_message_send_log.sql` — send-log table + health view.
- `supabase/migrations/20260623120100_messaging_bridge_cron.sql` — pg_cron every 15 min.

**Modify:**
- `supabase/functions/_shared/ghl/ghl-client.ts` — add `upsertContactByPhone()` and `addContactTags()`.

**Configuration (GHL UI, not code) — Task 8:** one tag-triggered workflow per stage, with the exact copy below.

---

## Task 1: Stage constants and types

**Files:**
- Create: `supabase/functions/_shared/messaging/stages.ts`

- [ ] **Step 1: Write the module**

```typescript
// supabase/functions/_shared/messaging/stages.ts
//
// Single source of truth for messaging stages. The GHL tag for a stage is
// `msg:<stage>` and the GHL workflow that sends it triggers on that tag.

export const STAGES = {
  EST_FOLLOWUP_1: "est_followup_1",
  EST_FOLLOWUP_2: "est_followup_2",
  JOB_CONFIRM: "job_confirm",
  JOB_REMINDER: "job_reminder",
  JOB_THANKYOU: "job_thankyou",
  JOB_REVIEW: "job_review",
} as const;

export type Stage = (typeof STAGES)[keyof typeof STAGES];

export type Channel = "sms" | "email";

export interface DueStage {
  stage: Stage;
  channel: Channel;
}

/** The GHL contact tag that triggers the send workflow for a stage. */
export function stageTag(stage: Stage): string {
  return `msg:${stage}`;
}
```

- [ ] **Step 2: Commit**

```bash
git add supabase/functions/_shared/messaging/stages.ts
git commit -m "feat(messaging): add stage constants and tag helper"
```

---

## Task 2: Pure stage resolver

The resolver decides which stage messages a single ticket is *due* for at a given `now`. It is pure (no I/O) so it is fully testable. Idempotency (send-once) is handled later by the send-log, not here. Estimate-conversion suppression is handled by the bridge (Task 5), not here.

**Field facts (verified in `hcp_data`):** estimates have `estimate_number` and no `invoice_number`; jobs have `invoice_number`. `work_status` values include `scheduled`, `in progress`, `complete unrated`, `complete rated`, `needs scheduling`. Schedule start at `schedule.scheduled_start` (ISO). Completion at `work_timestamps.completed_at` (ISO).

**Files:**
- Create: `supabase/functions/_shared/messaging/stage-resolver.ts`
- Test: `supabase/functions/_shared/messaging/__tests__/stage-resolver.test.ts`

- [ ] **Step 1: Write the failing tests**

```typescript
// supabase/functions/_shared/messaging/__tests__/stage-resolver.test.ts
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { resolveDueStages, type Ticket } from "../stage-resolver.ts";
import { STAGES } from "../stages.ts";

const NOW = new Date("2026-06-23T18:00:00Z");
const iso = (d: string) => d;

function job(over: Record<string, unknown>): Ticket {
  return { id: "j1", hcp_data: { invoice_number: "1001", ...over } };
}
function estimate(over: Record<string, unknown>): Ticket {
  return { id: "e1", hcp_data: { estimate_number: "E55", ...over } };
}
const stages = (t: Ticket) => resolveDueStages(t, NOW).map((d) => d.stage);

Deno.test("future scheduled job is due for confirmation", () => {
  const t = job({ work_status: "scheduled", schedule: { scheduled_start: iso("2026-06-25T15:00:00Z") } });
  assertEquals(stages(t).includes(STAGES.JOB_CONFIRM), true);
});

Deno.test("job scheduled later today is due for a reminder", () => {
  const t = job({ work_status: "scheduled", schedule: { scheduled_start: iso("2026-06-23T22:00:00Z") } });
  assertEquals(stages(t).includes(STAGES.JOB_REMINDER), true);
});

Deno.test("completed job >2h ago and unrated is due for thank-you and review", () => {
  const t = job({ work_status: "complete unrated", work_timestamps: { completed_at: iso("2026-06-23T14:00:00Z") } });
  const s = stages(t);
  assertEquals(s.includes(STAGES.JOB_THANKYOU), true);
  assertEquals(s.includes(STAGES.JOB_REVIEW), true);
});

Deno.test("already-rated job is NOT due for a review", () => {
  const t = job({ work_status: "complete rated", work_timestamps: { completed_at: iso("2026-06-23T14:00:00Z") } });
  assertEquals(stages(t).includes(STAGES.JOB_REVIEW), false);
});

Deno.test("estimate is NEVER due for a review (guardrail)", () => {
  // HCP marks estimates 'complete unrated' too; must not trigger a review.
  const t = estimate({ work_status: "complete unrated", work_timestamps: { completed_at: iso("2026-06-23T14:00:00Z") } });
  assertEquals(stages(t).includes(STAGES.JOB_REVIEW), false);
});

Deno.test("estimate 1 day old is due for follow-up 1", () => {
  const t = estimate({ work_status: "scheduled", created_at: iso("2026-06-22T12:00:00Z") });
  assertEquals(stages(t).includes(STAGES.EST_FOLLOWUP_1), true);
});

Deno.test("a completed job from 30 days ago is not re-triggered (window guard)", () => {
  const t = job({ work_status: "complete unrated", work_timestamps: { completed_at: iso("2026-05-01T14:00:00Z") } });
  assertEquals(stages(t).length, 0);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `deno test supabase/functions/_shared/messaging/__tests__/stage-resolver.test.ts`
Expected: FAIL with "Module not found ../stage-resolver.ts".

- [ ] **Step 3: Implement the resolver**

```typescript
// supabase/functions/_shared/messaging/stage-resolver.ts
import { STAGES, type DueStage } from "./stages.ts";

export interface Ticket {
  id: string;
  hcp_data: Record<string, unknown>;
  created_at?: string;
}

const HOUR = 3_600_000;
const DAY = 86_400_000;

function ts(v: unknown): number | null {
  if (typeof v !== "string" || !v) return null;
  const t = new Date(v).getTime();
  return Number.isNaN(t) ? null : t;
}
function get(o: Record<string, unknown>, path: string[]): unknown {
  let cur: unknown = o;
  for (const k of path) {
    if (!cur || typeof cur !== "object") return undefined;
    cur = (cur as Record<string, unknown>)[k];
  }
  return cur;
}

export function resolveDueStages(ticket: Ticket, now: Date): DueStage[] {
  const d = ticket.hcp_data ?? {};
  const isJob = typeof d["invoice_number"] === "string" && d["invoice_number"] !== "";
  const isEstimate = !isJob && typeof d["estimate_number"] === "string" && d["estimate_number"] !== "";
  const nowMs = now.getTime();
  const status = String(d["work_status"] ?? "");
  const out: DueStage[] = [];

  if (isJob) {
    const start = ts(get(d, ["schedule", "scheduled_start"]));
    const completed = ts(get(d, ["work_timestamps", "completed_at"]));

    if (status === "scheduled" && start && start > nowMs) {
      out.push({ stage: STAGES.JOB_CONFIRM, channel: "sms" });
      // Reminder when the appointment is later today (within next 12h).
      if (start - nowMs <= 12 * HOUR) out.push({ stage: STAGES.JOB_REMINDER, channel: "sms" });
    }
    if (completed && nowMs - completed >= 2 * HOUR && nowMs - completed <= 14 * DAY) {
      out.push({ stage: STAGES.JOB_THANKYOU, channel: "sms" });
      if (status === "complete unrated") out.push({ stage: STAGES.JOB_REVIEW, channel: "sms" });
    }
  } else if (isEstimate) {
    // Review is NEVER emitted for estimates (HCP also marks them "complete unrated").
    const createdMs = ts(ticket.created_at) ?? ts(d["created_at"]);
    if (createdMs) {
      const ageDays = (nowMs - createdMs) / DAY;
      if (ageDays >= 1 && ageDays < 3) out.push({ stage: STAGES.EST_FOLLOWUP_1, channel: "sms" });
      if (ageDays >= 3 && ageDays < 8) out.push({ stage: STAGES.EST_FOLLOWUP_2, channel: "sms" });
    }
  }
  return out;
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `deno test supabase/functions/_shared/messaging/__tests__/stage-resolver.test.ts`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/messaging/stage-resolver.ts supabase/functions/_shared/messaging/__tests__/stage-resolver.test.ts
git commit -m "feat(messaging): pure stage resolver with review-jobs-only guardrail"
```

---

## Task 3: Send-log table + health view (migration)

**Files:**
- Create: `supabase/migrations/20260623120000_message_send_log.sql`

- [ ] **Step 1: Write the migration**

```sql
-- message_send_log: exactly-once record of every stage message we triggered.
-- (ticket_id, stage) is unique so a re-run of the sweep can never double-send.
create table if not exists public.message_send_log (
  id uuid primary key default gen_random_uuid(),
  ticket_id text not null,            -- HCP job/estimate id (hcp_data->>'id')
  stage text not null,                -- one of the messaging stages
  ghl_contact_id text,                -- GHL contact the tag was applied to
  status text not null default 'sent',-- 'sent' | 'failed'
  detail text,                        -- error text when status='failed'
  created_at timestamptz not null default now(),
  unique (ticket_id, stage)
);

create index if not exists message_send_log_created_idx on public.message_send_log (created_at desc);

-- Silent health surface: per-stage counts over the last 7 days + failures.
create or replace view public.v_messaging_health as
select
  stage,
  count(*) filter (where status = 'sent')   as sent_7d,
  count(*) filter (where status = 'failed') as failed_7d,
  max(created_at)                            as last_sent_at
from public.message_send_log
where created_at > now() - interval '7 days'
group by stage;

-- Owner-operator only; reuse the project's admin RLS posture.
alter table public.message_send_log enable row level security;
```

- [ ] **Step 2: Apply the migration**

Apply via the project's migration workflow (per `reference_twins_dash_migration_history`: after applying, insert the version row into `schema_migrations` if the CLI history is desynced).
Expected: `message_send_log` table and `v_messaging_health` view exist.

- [ ] **Step 3: Verify**

Run (Supabase SQL): `select * from public.v_messaging_health;`
Expected: 0 rows (empty), no error.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260623120000_message_send_log.sql
git commit -m "feat(messaging): send-log table + silent health view"
```

---

## Task 4: GHL write helpers (upsert contact, add tags)

**Files:**
- Modify: `supabase/functions/_shared/ghl/ghl-client.ts`
- Test: `supabase/functions/_shared/ghl/__tests__/ghl-write.test.ts`

- [ ] **Step 1: Write the failing tests (fetch mocked)**

```typescript
// supabase/functions/_shared/ghl/__tests__/ghl-write.test.ts
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { addContactTags, upsertContactByPhone } from "../ghl-client.ts";

const cfg = { apiKey: "k", locationId: "loc1" };

Deno.test("upsertContactByPhone posts to /contacts and returns the id", async () => {
  const calls: { url: string; body: unknown }[] = [];
  const orig = globalThis.fetch;
  globalThis.fetch = ((url: string, init?: RequestInit) => {
    calls.push({ url: String(url), body: JSON.parse(String(init?.body ?? "{}")) });
    return Promise.resolve(new Response(JSON.stringify({ contact: { id: "c123" } }), { status: 200 }));
  }) as typeof fetch;
  try {
    const id = await upsertContactByPhone(cfg, { phone: "6085551234", firstName: "Sam" });
    assertEquals(id, "c123");
    assertEquals(calls[0].url.endsWith("/contacts/"), true);
  } finally {
    globalThis.fetch = orig;
  }
});

Deno.test("addContactTags posts the tags array", async () => {
  const calls: { url: string; body: { tags?: string[] } }[] = [];
  const orig = globalThis.fetch;
  globalThis.fetch = ((url: string, init?: RequestInit) => {
    calls.push({ url: String(url), body: JSON.parse(String(init?.body ?? "{}")) });
    return Promise.resolve(new Response(JSON.stringify({ tags: ["msg:job_review"] }), { status: 200 }));
  }) as typeof fetch;
  try {
    await addContactTags(cfg, "c123", ["msg:job_review"]);
    assertEquals(calls[0].url.includes("/contacts/c123/tags"), true);
    assertEquals(calls[0].body.tags, ["msg:job_review"]);
  } finally {
    globalThis.fetch = orig;
  }
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `deno test supabase/functions/_shared/ghl/__tests__/ghl-write.test.ts`
Expected: FAIL with "upsertContactByPhone is not exported".

- [ ] **Step 3: Add the helpers to `ghl-client.ts`**

Append to `supabase/functions/_shared/ghl/ghl-client.ts` (reuses `BASE_URL` and `GhlAccountConfig` already defined in that file):

```typescript
export interface UpsertContactInput {
  phone: string;            // normalized 10-digit
  firstName?: string | null;
  lastName?: string | null;
  email?: string | null;
}

/** Create/update a GHL contact by phone within the location. Returns contact id. */
export async function upsertContactByPhone(
  config: GhlAccountConfig,
  input: UpsertContactInput,
): Promise<string> {
  const resp = await fetch(`${BASE_URL}/contacts/`, {
    method: "POST",
    headers: { authorization: `Bearer ${config.apiKey}`, "content-type": "application/json" },
    body: JSON.stringify({
      locationId: config.locationId,
      phone: input.phone,
      firstName: input.firstName ?? undefined,
      lastName: input.lastName ?? undefined,
      email: input.email ?? undefined,
    }),
  });
  if (!resp.ok) {
    throw new Error(`GHL upsert contact failed: ${resp.status} ${(await resp.text()).slice(0, 200)}`);
  }
  const data = await resp.json() as { contact?: { id?: string } };
  const id = data.contact?.id;
  if (!id) throw new Error("GHL upsert contact returned no id");
  return id;
}

/** Add tags to a GHL contact (idempotent on GHL's side). */
export async function addContactTags(
  config: GhlAccountConfig,
  contactId: string,
  tags: string[],
): Promise<void> {
  const resp = await fetch(`${BASE_URL}/contacts/${contactId}/tags/`, {
    method: "POST",
    headers: { authorization: `Bearer ${config.apiKey}`, "content-type": "application/json" },
    body: JSON.stringify({ tags }),
  });
  if (!resp.ok) {
    throw new Error(`GHL add tags failed: ${resp.status} ${(await resp.text()).slice(0, 200)}`);
  }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `deno test supabase/functions/_shared/ghl/__tests__/ghl-write.test.ts`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/ghl/ghl-client.ts supabase/functions/_shared/ghl/__tests__/ghl-write.test.ts
git commit -m "feat(ghl): add contact upsert + tag write helpers"
```

---

## Task 5: messaging-bridge sweep function

The cron entrypoint. Pulls candidate tickets from `jobs`, resolves due stages, suppresses estimate stages for converted estimates, skips anything already in `message_send_log`, upserts the contact + adds the stage tag, and records the result.

**Files:**
- Create: `supabase/functions/messaging-bridge/index.ts`

- [ ] **Step 1: Write the function**

```typescript
// supabase/functions/messaging-bridge/index.ts
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.39.3";
import { resolveDueStages } from "../_shared/messaging/stage-resolver.ts";
import { stageTag } from "../_shared/messaging/stages.ts";
import { addContactTags, upsertContactByPhone } from "../_shared/ghl/ghl-client.ts";
import { extractPhones } from "../_shared/matching/phone-matcher.ts";

const LOCATION_ID = Deno.env.get("GHL_LOCATION_ID") ?? "iRUlbIBg7PzSfLrPiR2j"; // Dunzo "Twins Garage Doors"

Deno.serve(async () => {
  const sb = createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
  );
  const ghl = { apiKey: Deno.env.get("GHL_API_KEY")!, locationId: LOCATION_ID };
  const now = new Date();

  // Candidate window: tickets created/updated in the last 60 days. Far enough
  // to catch all live stages; bounded so the sweep stays fast.
  const since = new Date(now.getTime() - 60 * 86_400_000).toISOString();
  const { data: tickets, error } = await sb
    .from("jobs")
    .select("id, hcp_data, created_at")
    .gte("created_at", since);
  if (error) return Response.json({ ok: false, error: error.message }, { status: 500 });

  // Estimates that already converted to a job: suppress their follow-ups.
  const { data: convRows } = await sb
    .from("jobs")
    .select("hcp_data")
    .not("hcp_data->>original_estimate_id", "is", null);
  const convertedEstimateIds = new Set(
    (convRows ?? []).map((r) => String((r.hcp_data as Record<string, unknown>)["original_estimate_id"])),
  );

  let triggered = 0, failed = 0, skipped = 0;
  for (const t of tickets ?? []) {
    const hcp = (t.hcp_data ?? {}) as Record<string, unknown>;
    const hcpId = String(hcp["id"] ?? t.id);
    let due = resolveDueStages({ id: hcpId, hcp_data: hcp, created_at: t.created_at }, now);

    const isEstimate = !hcp["invoice_number"] && hcp["estimate_number"];
    if (isEstimate && convertedEstimateIds.has(hcpId)) {
      due = due.filter((d) => !d.stage.startsWith("est_"));
    }
    if (due.length === 0) continue;

    // Skip stages already logged for this ticket.
    const { data: logged } = await sb
      .from("message_send_log")
      .select("stage")
      .eq("ticket_id", hcpId);
    const done = new Set((logged ?? []).map((r) => r.stage));
    const pending = due.filter((d) => !done.has(d.stage));
    if (pending.length === 0) { skipped++; continue; }

    // Resolve a phone + name from the HCP customer.
    const phones = extractPhones(hcp);
    const phone = phones[0];
    const customer = (hcp["customer"] ?? {}) as Record<string, unknown>;
    if (!phone) {
      for (const d of pending) {
        await sb.from("message_send_log").insert({
          ticket_id: hcpId, stage: d.stage, status: "failed", detail: "no phone on HCP customer",
        }).select().maybeSingle();
      }
      failed += pending.length;
      continue;
    }

    try {
      const contactId = await upsertContactByPhone(ghl, {
        phone,
        firstName: (customer["first_name"] as string) ?? null,
        lastName: (customer["last_name"] as string) ?? null,
        email: (customer["email"] as string) ?? null,
      });
      for (const d of pending) {
        // Insert the log row FIRST (unique constraint = our lock); if it
        // conflicts another sweep already handled it.
        const { error: insErr } = await sb.from("message_send_log").insert({
          ticket_id: hcpId, stage: d.stage, ghl_contact_id: contactId, status: "sent",
        });
        if (insErr) { skipped++; continue; }       // unique violation = already done
        await addContactTags(ghl, contactId, [stageTag(d.stage)]);
        triggered++;
      }
    } catch (e) {
      failed++;
      // Leave it unlogged so the next sweep retries (self-healing).
      console.error(`messaging-bridge ticket ${hcpId}:`, e instanceof Error ? e.message : e);
    }
  }

  return Response.json({ ok: true, triggered, failed, skipped, scanned: tickets?.length ?? 0 });
});
```

- [ ] **Step 2: Deploy the function (no schedule yet)**

Deploy `messaging-bridge` with the project's function-deploy workflow. Do NOT schedule it yet (Task 6 schedules it after a dry verification).
Expected: function ACTIVE.

- [ ] **Step 3: Dry-run verification against live data, with the send DISABLED**

Before the first real send, confirm the resolver picks sane tickets without tagging anyone. Temporarily set env `MESSAGING_DRY_RUN=1` and add a guard at the top of the tag loop (`if (Deno.env.get("MESSAGING_DRY_RUN")==="1") { triggered++; continue; }`) OR invoke once and inspect the returned counts before any workflow exists in GHL (Task 8 not done yet = tags are harmless no-ops).
Run: invoke the function once; read the JSON response.
Expected: `scanned` > 0, `triggered` is a believable number (tens, not thousands), `failed` low. Spot-check 3 `message_send_log` rows against the HCP tickets.

- [ ] **Step 4: Commit**

```bash
git add supabase/functions/messaging-bridge/index.ts
git commit -m "feat(messaging): idempotent sweep that tags GHL contacts per due stage"
```

---

## Task 6: Schedule the sweep (pg_cron, every 15 min)

**Files:**
- Create: `supabase/migrations/20260623120100_messaging_bridge_cron.sql`

- [ ] **Step 1: Write the migration** (mirrors `20260502101200_ghl_sync_cron.sql`)

```sql
DO $$ BEGIN
  PERFORM cron.unschedule(jobid) FROM cron.job WHERE jobname = 'messaging-bridge-sweep';
END $$;

SELECT cron.schedule(
  'messaging-bridge-sweep',
  '*/15 * * * *',
  $cron$
    SELECT net.http_post(
      url := 'https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/messaging-bridge',
      headers := jsonb_build_object(
        'Content-Type', 'application/json',
        'Authorization', 'Bearer ' || (SELECT decrypted_secret FROM vault.decrypted_secrets WHERE name = 'email_cron_secret')
      ),
      body := jsonb_build_object('source', 'pg_cron')
    );
  $cron$
);
```

- [ ] **Step 2: Apply the migration** (only AFTER Task 8 GHL workflows exist, so tags actually send). Update `schema_migrations` if history is desynced.

- [ ] **Step 3: Verify the schedule**

Run (Supabase SQL): `select jobname, schedule from cron.job where jobname='messaging-bridge-sweep';`
Expected: one row, `*/15 * * * *`.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260623120100_messaging_bridge_cron.sql
git commit -m "feat(messaging): schedule reconciliation sweep every 15 min"
```

---

## Task 7: Silent health pill (dashboard)

A read-only glance that the pipeline is flowing. No alerts (per standing rule). Follows the existing dashboard hook/component patterns.

**Files:**
- Create: `src/hooks/use-messaging-health.ts`
- Create: `src/components/marketing-roi/MessagingHealthPill.tsx`
- Modify: the marketing-roi page to render the pill (place near `GhlAttributionPanel`).

- [ ] **Step 1: Write the hook**

```typescript
// src/hooks/use-messaging-health.ts
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useSession } from "@/contexts/AuthContext"; // match the project's session hook

export interface MessagingHealthRow { stage: string; sent_7d: number; failed_7d: number; last_sent_at: string | null; }

export function useMessagingHealth() {
  const { session } = useSession();
  return useQuery({
    queryKey: ["messaging-health"],
    enabled: !!session, // gate on session (RLS race guard)
    refetchInterval: 5 * 60_000,
    queryFn: async (): Promise<MessagingHealthRow[]> => {
      const { data, error } = await supabase.from("v_messaging_health").select("*");
      if (error) throw error;
      return data ?? [];
    },
  });
}
```

- [ ] **Step 2: Write the component**

```tsx
// src/components/marketing-roi/MessagingHealthPill.tsx
import { useMessagingHealth } from "@/hooks/use-messaging-health";

export function MessagingHealthPill() {
  const { data, isLoading } = useMessagingHealth();
  if (isLoading || !data) return null;
  const sent = data.reduce((n, r) => n + r.sent_7d, 0);
  const failed = data.reduce((n, r) => n + r.failed_7d, 0);
  const ok = failed === 0;
  return (
    <div className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm ${ok ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700"}`}>
      <span className={`h-2 w-2 rounded-full ${ok ? "bg-emerald-500" : "bg-amber-500"}`} />
      Messaging: {sent} sent / 7d{failed > 0 ? `, ${failed} failed` : ""}
    </div>
  );
}
```

- [ ] **Step 3: Render it on the marketing-roi page**

In `src/pages/MarketingSourceROI.tsx`, import `MessagingHealthPill` and render it in the header row near the GHL attribution panel.

- [ ] **Step 4: Verify in the browser**

Run the dev server and load `/marketing-roi`. Confirm the pill renders (green "0 sent / 7d" before go-live is fine) and no console errors.

- [ ] **Step 5: Commit**

```bash
git add src/hooks/use-messaging-health.ts src/components/marketing-roi/MessagingHealthPill.tsx src/pages/MarketingSourceROI.tsx
git commit -m "feat(messaging): silent health pill on marketing-roi"
```

---

## Task 8: GHL workflow configuration (UI playbook, Daniel/Aman)

For each stage, create one GHL workflow in the Dunzo "Twins Garage Doors" location. **Trigger:** "Contact Tag" added = the stage tag. **Action:** send SMS (or email) from the single Twins number using the copy below. After sending, **remove the trigger tag** (so a future job can re-enter cleanly; the send-log still prevents re-tagging within a ticket). No em-dashes. `{{contact.first_name}}` is GHL's merge field.

- [ ] **`msg:est_followup_1` → SMS**
  > Hi {{contact.first_name}}, it's Twins Garage Doors. Did you get a chance to look over your estimate? Happy to walk through the options. We can also help you spread the cost with financing: https://www.goodleap.dev/twinsgaragedoorsllc/8fcb0f0d-2f74-4026-bb3c-6e93a3d18e3d
- [ ] **`msg:est_followup_2` → SMS**
  > Hi {{contact.first_name}}, just checking in on your garage door estimate. If you'd like to lock it in we can get you on the schedule. Call (608) 888-8785.
- [ ] **`msg:job_confirm` → SMS**
  > You're booked with Twins Garage Doors, {{contact.first_name}}! Your tech will text when they're on the way. Questions? (608) 888-8785.
- [ ] **`msg:job_reminder` → SMS**
  > Reminder from Twins Garage Doors: we're scheduled to see you today. Reply C to confirm, or call (608) 888-8785 to reschedule.
- [ ] **`msg:job_thankyou` → SMS**
  > All done, {{contact.first_name}}! Thanks for trusting Twins Garage Doors. If anything feels off, call us right away at (608) 888-8785, we stand behind our work.
- [ ] **`msg:job_review` → SMS**
  > {{contact.first_name}}, it was a pleasure helping with your garage door today! A quick review really helps our small local team. Would you leave one here? https://g.page/r/CYMu-jkURnx7EAI/review Thank you, the Twins crew.
- [ ] **Verify** each workflow with a GHL test contact: add the tag, confirm the message previews/sends correctly and the tag is removed after.

---

## Task 9: Supervised go-live

- [ ] **Step 1:** Confirm Task 8 workflows are published in GHL.
- [ ] **Step 2:** Apply the Task 6 cron migration (starts the 15-min sweep).
- [ ] **Step 3:** For the first 48 hours, check `v_messaging_health` and the GHL conversation log each morning. Confirm: review requests only on job tickets (never estimates), no double-sends, phone-less tickets logged as `failed` (not silently dropped).
- [ ] **Step 4:** If anything looks off, `cron.unschedule('messaging-bridge-sweep')` halts all sending instantly (reversible kill switch). Tags already sent are logged; nothing is lost.

---

## Self-review notes (coverage)

- Spec "reliability: event + reconciliation + idempotent log + silent health" → Tasks 5/6 (sweep+cron), 3 (send-log unique), 7 (health). The sweep is the reconciliation; it is also the primary driver, which is strictly more reliable than event-coupling.
- Spec "GHL one number, copy in GHL" → Task 8 (tag-triggered workflows).
- Spec "review jobs-only, estimates excluded" → Task 2 test "estimate is NEVER due for a review" + resolver branch.
- Spec "contact bridging for all jobs by phone" → Task 5 `upsertContactByPhone` + `extractPhones`.
- Spec "no fabricated facts / real links / no em-dashes" → Task 8 copy uses only known facts + the two fixed links.
- Spec "no pinging Daniel" → Task 7 is a passive pill; no notifications.
- Spec "reversible kill switch" → Task 9 Step 4.

## Deferred (separate plans, NOT in this plan)

- **Retention stages** (membership / annual tune-up / win-back): need TwinShield plan facts + timing; add stages to `stages.ts`/resolver and matching workflows once facts are supplied.
- **Booking-rate matcher fix** (the held workstream): diagnose the ~May 24 stall, stop dropping repeat customers, backfill. KPI change, ships with a diff + approval.
- **Literal one-number invoice** (Option B, declined): fetch the HCP invoice URL into GHL. Only if Daniel later reverses the decision.
