# Twins Marketing OS — Phase 1a Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up `twins-marketing-os` — a branded standalone app on its own Supabase project — showing spend, revenue, ROAS, CAC and channel attribution that reconciles to the dollar against TwinsDash.

**Architecture:** Next.js App Router on Vercel, backed by a new Supabase project in `us-east-1`. A nightly job PULLS a narrow revenue+spend slice from `twins-dash-prod` through one read-only RPC; the new project owns its own copy of the channel-canonicalisation logic so marketing definitions never again require editing TwinsDash. TwinsDash gains no knowledge of any of this.

**Tech Stack:** Next.js (App Router), TypeScript, Tailwind, shadcn/ui, Framer Motion, Supabase (Postgres + Auth + Edge Functions), Vitest.

---

## Why 1a, and what is deliberately NOT here

Phase 1 as specced included a Campaigns surface. **It is not in this plan**, for two reasons established on 2026-07-25:

1. **Campaign-level ROAS is not computable** — there is no `gclid`/`fbclid`/`wbraid`/`gbraid` anywhere in `twins-dash-prod`, including inside every jsonb column, and zero phone calls are campaign-attributed. See the spec's campaign-attribution section.
2. **The cheap Google-only path is unverified.** Whether Google is actually matching the `offline-conversions-weekly` uploads has not been checked. That check needs `ADS_AUDIT_SECRET`, which cannot be read back — the Supabase Management API returns SHA-256 digests for secret values, not plaintext (verified: all 86 secrets return 64-char lowercase hex, including `SUPABASE_URL`).

**Also deferred: hosting the `twins-google-ads` attribution ledger.** That ledger (`google_ads_ops/attribution/`) is the right long-term home for click-level attribution, but it writes to `gads_lead_events`, `gads_ledger_duplicates`, `gads_export_claims` and `gads_export_attempts` — and **no DDL for those tables exists in any repo**; the schema is implicit in its INSERT statements. Its `gads_` prefix also hints it was designed for a shared database rather than a dedicated one. Reconstructing that schema is its own piece of work with its own correctness risk, and it does not block the money dashboard.

Phase 1a therefore delivers **channel-level truth**, which is solid today, on a foundation the campaign layer can land on later without rework.

## Owner-gated prerequisites — nothing in this plan can start until these exist

These are account-level and billable, so they are deliberately not automated:

| # | Action | Why it cannot be scripted here |
| --- | --- | --- |
| P1 | Create GitHub repo `twins-marketing-os` (private) | Account action |
| P2 | Create Supabase project `twins-marketing-os` in **us-east-1** | Billable; region cannot be changed later |
| P3 | Create Vercel project linked to the repo | Account action |
| P4 | In `twins-dash-prod`, create a dedicated Postgres role/key for the mirror to read with | Production credential |

Record P2's project ref and P4's key; every task below needs them.

## File Structure

New repo `twins-marketing-os`:

```
app/
  (auth)/login/page.tsx          — Supabase Auth, invite-only
  (dash)/layout.tsx              — nav rail, phase-badged future sections
  (dash)/page.tsx                — Overview
  (dash)/channels/page.tsx       — per-channel table
  (dash)/health/page.tsx         — attribution confidence, freshness
lib/
  supabase/client.ts             — browser client
  supabase/server.ts             — server client
  format.ts                      — money / percent / ratio formatting
  metrics.ts                     — PURE: roas(), cac(), confidence bands
components/
  kpi-tile.tsx  channel-table.tsx  freshness-badge.tsx  confidence-strip.tsx
supabase/
  migrations/                    — schema, RLS, rollup SQL
  functions/mirror-pull/         — nightly pull from twins-dash-prod
```

Each file has one responsibility. `metrics.ts` is pure and has no Supabase import, so the arithmetic that everything else trusts is unit-testable in isolation — that is where the ROAS/CAC/undefined-vs-infinite rules live.

---

### Task 1: Scaffold the app and prove auth works

**Files:** repo root, `app/(auth)/login/page.tsx`, `lib/supabase/*`

- [ ] **Step 1: Scaffold**

```bash
npx create-next-app@latest twins-marketing-os --typescript --tailwind --app --eslint --src-dir=false --import-alias "@/*"
cd twins-marketing-os
npm i @supabase/supabase-js @supabase/ssr framer-motion
npx shadcn@latest init -d
npm i -D vitest @vitejs/plugin-react jsdom @testing-library/react
```

- [ ] **Step 2: Add the vitest config**

Create `vitest.config.ts`:

```ts
import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  test: { environment: "jsdom", include: ["**/*.{test,spec}.{ts,tsx}"] },
});
```

Add to `package.json` scripts: `"test": "vitest run"`, `"typecheck": "tsc --noEmit"`.

- [ ] **Step 3: Environment**

Create `.env.local` (and add it to `.gitignore` — verify it is there):

```
NEXT_PUBLIC_SUPABASE_URL=https://<P2_REF>.supabase.co
NEXT_PUBLIC_SUPABASE_ANON_KEY=<publishable key>
```

- [ ] **Step 4: Verify it boots and auth redirects**

```bash
npm run dev
```

Visit `/` unauthenticated. Expected: redirect to `/login`. Sign in with an invited address; expected: reach `/`.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: scaffold twins-marketing-os with Supabase auth"
```

---

### Task 2: The metrics module — pure arithmetic, TDD

This is the task that decides whether every number in the product is trustworthy. It has no I/O.

**Files:** Create `lib/metrics.ts`, Test `lib/metrics.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `lib/metrics.test.ts`:

```ts
import { describe, expect, it } from "vitest";
import { cac, confidence, roas } from "./metrics";

describe("roas", () => {
  it("is revenue divided by spend", () => {
    expect(roas(1000, 250)).toBe(4);
  });

  it("is UNDEFINED when there is no spend, never Infinity", () => {
    // Organic and existing-customer revenue has no spend. Rendering these as
    // Infinity trains the reader to ignore the column.
    expect(roas(1000, 0)).toBeNull();
    expect(roas(0, 0)).toBeNull();
  });

  it("is 0 when spend produced no revenue", () => {
    expect(roas(0, 500)).toBe(0);
  });

  it("rejects negative inputs rather than returning a plausible number", () => {
    expect(() => roas(-1, 100)).toThrow();
    expect(() => roas(100, -1)).toThrow();
  });
});

describe("cac", () => {
  it("is spend divided by jobs", () => {
    expect(cac(1000, 8)).toBe(125);
  });

  it("is UNDEFINED with no jobs, never Infinity", () => {
    expect(cac(1000, 0)).toBeNull();
  });

  it("is 0 when there was no spend", () => {
    expect(cac(0, 8)).toBe(0);
  });
});

describe("confidence", () => {
  // Mirrors the 8-job floor the spend-recommendation rules already use.
  it("is low below the 8-job floor", () => {
    expect(confidence(7)).toBe("low");
    expect(confidence(0)).toBe("low");
  });

  it("is normal at or above the floor", () => {
    expect(confidence(8)).toBe("normal");
    expect(confidence(400)).toBe("normal");
  });
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
npx vitest run lib/metrics.test.ts
```

Expected: FAIL — cannot find module `./metrics`.

- [ ] **Step 3: Implement**

Create `lib/metrics.ts`:

```ts
// Pure measurement arithmetic. No I/O, no Supabase import — this is the module
// every displayed number depends on, so it must be testable in isolation.
//
// The central rule: a ratio with a zero denominator is UNDEFINED (null), never
// Infinity. Organic and existing-customer revenue legitimately has no spend, and
// an infinity symbol in a ROAS column teaches the reader to skip the column.

/** Jobs below this count do not support a confident ratio. Matches the floor the
 *  spend-recommendation rules in TwinsDash already apply before acting. */
export const MIN_JOBS_FOR_CONFIDENCE = 8;

function assertNonNegative(...values: number[]): void {
  for (const v of values) {
    if (!Number.isFinite(v) || v < 0) {
      throw new RangeError(`expected a non-negative finite number, got ${v}`);
    }
  }
}

/** Return on ad spend. `null` when there was no spend at all. */
export function roas(revenue: number, spend: number): number | null {
  assertNonNegative(revenue, spend);
  return spend === 0 ? null : revenue / spend;
}

/** Cost to acquire one completed job. `null` when there were no jobs. */
export function cac(spend: number, jobs: number): number | null {
  assertNonNegative(spend, jobs);
  return jobs === 0 ? null : spend / jobs;
}

export function confidence(jobs: number): "low" | "normal" {
  assertNonNegative(jobs);
  return jobs < MIN_JOBS_FOR_CONFIDENCE ? "low" : "normal";
}
```

- [ ] **Step 4: Run to verify it passes**

```bash
npx vitest run lib/metrics.test.ts
```

Expected: PASS, 10 tests.

- [ ] **Step 5: Commit**

```bash
git add lib/metrics.ts lib/metrics.test.ts
git commit -m "feat(metrics): pure ROAS/CAC/confidence with undefined-not-infinite semantics"
```

---

### Task 3: Schema and RLS on the new project

**Files:** Create `supabase/migrations/0001_mirror_schema.sql`

- [ ] **Step 1: Write the migration**

```sql
-- Mirrored facts pulled nightly from twins-dash-prod. Read-only copies:
-- nothing in this project writes back to the source of truth.

create table public.jobs_slice (
  job_id            text primary key,
  customer_id       text,
  completed_at      date not null,
  revenue_amount    numeric(12,2) not null default 0,
  canonical_channel text not null,
  raw_lead_source   text,
  business_unit     text,
  market            text,
  service_zip       text,
  mirrored_at       timestamptz not null default now()
);
create index on public.jobs_slice (completed_at);
create index on public.jobs_slice (canonical_channel);
create index on public.jobs_slice (market);

create table public.spend (
  id              bigserial primary key,
  spend_date      date not null,
  platform        text not null,
  -- NULLS NOT DISTINCT (PG15+, and this project is PG17) is load-bearing.
  -- Postgres treats NULLs as distinct in a unique constraint by default, so a
  -- single row with a null campaign would bypass the upsert and duplicate on
  -- every nightly run, silently inflating spend. Upstream is clean today —
  -- verified 2026-07-25: 0 null campaign_name across all 1,799 marketing_spend
  -- rows — but the mirror must not depend on that staying true.
  campaign_name   text,
  spend_amount    numeric(12,2) not null default 0,
  clicks          integer,
  leads_generated integer,
  mirrored_at     timestamptz not null default now(),
  unique nulls not distinct (spend_date, platform, campaign_name)
);
create index on public.spend (spend_date);

-- A stray writer upstream must never silently move a ROAS number. Only these
-- three platform labels are recognised; anything else is rejected at the door.
-- (twins-dash-prod carries a dormant second Google Ads writer that emits
-- 'Google Ads' where the nightly cron emits 'google_ads'.)
alter table public.spend
  add constraint spend_platform_allowlist
  check (platform in ('google_ads', 'google_lsa', 'meta_ads'));

create table public.mirror_runs (
  id           bigserial primary key,
  started_at   timestamptz not null default now(),
  finished_at  timestamptz,
  window_start date not null,
  window_end   date not null,
  jobs_upserted  integer,
  spend_upserted integer,
  status       text not null default 'running',
  error        text
);

alter table public.jobs_slice  enable row level security;
alter table public.spend       enable row level security;
alter table public.mirror_runs enable row level security;

-- Single shared admin role: every authenticated user reads everything.
-- Writes are service-role only (the mirror), so no write policy is granted.
create policy "authenticated read" on public.jobs_slice
  for select to authenticated using (true);
create policy "authenticated read" on public.spend
  for select to authenticated using (true);
create policy "authenticated read" on public.mirror_runs
  for select to authenticated using (true);
```

- [ ] **Step 2: Test the migration on a preview branch**

This is the capability the fresh project exists to give us — use it.

```bash
npx supabase branches create phase1a-schema --project-ref <P2_REF>
npx supabase db push --project-ref <preview ref>
```

Expected: applies without error. If it fails, fix and re-run **before** touching the main project.

- [ ] **Step 3: Verify the allow-list constraint actually bites**

```sql
insert into public.spend (spend_date, platform, spend_amount)
values ('2026-07-25', 'Google Ads', 10);
```

Expected: `ERROR: new row ... violates check constraint "spend_platform_allowlist"`.

Then verify the null-campaign dedupe actually holds, since it is the subtler of the two guards:

```sql
insert into public.spend (spend_date, platform, campaign_name, spend_amount)
values ('2026-07-25', 'google_lsa', null, 10);
insert into public.spend (spend_date, platform, campaign_name, spend_amount)
values ('2026-07-25', 'google_lsa', null, 20);
```

Expected: the **second** insert fails with a unique violation. If it succeeds, `nulls not distinct` did not take effect and the mirror will duplicate null-campaign rows on every run — stop and fix before going further.

- [ ] **Step 4: Apply to the project and commit**

```bash
npx supabase db push --project-ref <P2_REF>
git add supabase/migrations/0001_mirror_schema.sql
git commit -m "feat(db): mirrored jobs_slice + spend with platform allow-list and RLS"
```

---

### Task 4: The read-only export RPC on `twins-dash-prod`

The mirror pulls; TwinsDash gains no knowledge of marketing and no new write path.

**Files:** a migration in `twins-dash` (inner repo)

**Signatures below were read from the live schema on 2026-07-25, not assumed:**

| Object | Real signature |
| --- | --- |
| `_canonical_channel` | `(p_source text) → text` — **one argument** |
| `market_of` | `(p_business_unit text, p_zip text) → text` |
| `_marketing_job_channels` | `(p_days integer) → TABLE(id uuid, job_id text, revenue_amount numeric, completed_at timestamptz, job_type text, lead_source text, channel text, market text)` |
| `get_channel_rollup` | `(p_days integer, p_market text) → TABLE(channel text, spend numeric, completed_jobs bigint, revenue numeric)` |
| `jobs` columns | `id uuid`, `job_id text`, `hcp_customer_id text`, `business_unit`, `service_zip`, `revenue_amount`, `completed_at timestamptz`, `lead_source`, `job_type` — there is **no** `hcp_job_id` |

- [ ] **Step 1: Write the function**

Build it on `_marketing_job_channels`, **not** on `_canonical_channel` directly. That function already performs the full governed channel resolution — including the existing-customer fallback that moved 24 jobs and $38,845 out of the Unknown bucket — and it returns `market` already resolved. Reusing it means the mirror's channels are identical to TwinsDash's *by construction*, which is what makes the reconcile-to-the-cent test in Task 6 a near-certainty rather than a hope.

```sql
-- Read-only export for the twins-marketing-os mirror. SECURITY DEFINER so the
-- dedicated mirror role needs no direct table grants; returns a narrow, fixed
-- column set and nothing else.
--
-- Delegates channel and market resolution to _marketing_job_channels so the
-- mirror can never drift from TwinsDash's own definition of a channel.
create or replace function public.export_marketing_slice(p_days integer)
returns table (
  job_id text, customer_id text, completed_at date, revenue_amount numeric,
  canonical_channel text, raw_lead_source text, business_unit text,
  market text, service_zip text
)
language sql
stable
security definer
set search_path = public
as $$
  select c.job_id,
         j.hcp_customer_id,
         c.completed_at::date,
         coalesce(c.revenue_amount, 0),
         c.channel,
         c.lead_source,
         j.business_unit,
         c.market,
         j.service_zip
  from _marketing_job_channels(p_days) c
  join jobs j on j.id = c.id;
$$;

revoke all on function public.export_marketing_slice(integer) from public, anon, authenticated;
grant execute on function public.export_marketing_slice(integer) to marketing_mirror;
```

- [ ] **Step 2: Verify it returns the expected shape and row count**

```sql
select count(*), min(completed_at), max(completed_at),
       count(*) filter (where canonical_channel is null) as null_channels
from public.export_marketing_slice(365);
```

Expected: roughly 1,100 rows for a year (about 300 per 90 days), and **`null_channels = 0`**. A non-zero count means `_marketing_job_channels` is emitting rows the mirror will reject in Task 5 — investigate before continuing rather than loosening the mirror.

- [ ] **Step 3: Verify the grant is tight**

Confirm `anon` and `authenticated` cannot execute it:

```sql
select has_function_privilege('anon', 'public.export_marketing_slice(integer)', 'execute'),
       has_function_privilege('authenticated', 'public.export_marketing_slice(integer)', 'execute');
```

Expected: `false, false`.

- [ ] **Step 4: Commit** to the `twins-dash` repo (inner repo — confirm with `git rev-parse --show-toplevel`).

---

### Task 5: The mirror edge function

**Files:** Create `supabase/functions/mirror-pull/index.ts` and `pure.ts`, Test `pure.test.ts`

Split deliberately: `pure.ts` holds the windowing and upsert-shaping logic with no I/O, so it is testable; `index.ts` does the I/O.

- [ ] **Step 1: Write the failing test for the pure part**

Create `supabase/functions/mirror-pull/pure.test.ts`:

```ts
import { assertEquals, assertThrows } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { refreshWindow, toJobRow } from "./pure.ts";

Deno.test("refresh window is a trailing 365 days, inclusive of today", () => {
  const w = refreshWindow(new Date("2026-07-25T00:00:00Z"));
  assertEquals(w.end, "2026-07-25");
  assertEquals(w.start, "2025-07-25");
});

Deno.test("a job row maps straight through with revenue defaulted, never null", () => {
  const row = toJobRow({
    job_id: "j1", customer_id: "c1", completed_at: "2026-07-01",
    revenue_amount: null, canonical_channel: "lsa", raw_lead_source: "Google LSA",
    business_unit: "WI", market: "madison", service_zip: "53713",
  });
  assertEquals(row.revenue_amount, 0);
  assertEquals(row.canonical_channel, "lsa");
});

Deno.test("a job with no channel is rejected, not silently bucketed", () => {
  // Silently defaulting to 'unknown' would inflate the unknown bucket with rows
  // that actually failed to map — a data bug disguised as a measurement result.
  assertThrows(() => toJobRow({
    job_id: "j2", customer_id: null, completed_at: "2026-07-01",
    revenue_amount: 100, canonical_channel: null, raw_lead_source: null,
    business_unit: null, market: null, service_zip: null,
  }));
});
```

- [ ] **Step 2: Run and verify failure**

```bash
deno test --allow-env --no-check supabase/functions/mirror-pull/pure.test.ts
```

- [ ] **Step 3: Implement `pure.ts`**

```ts
// Pure helpers for the nightly mirror. No I/O so the window arithmetic and row
// shaping are testable without a database.

export interface SliceRow {
  job_id: string;
  customer_id: string | null;
  completed_at: string;
  revenue_amount: number | null;
  canonical_channel: string | null;
  raw_lead_source: string | null;
  business_unit: string | null;
  market: string | null;
  service_zip: string | null;
}

/** Trailing 365 days. Wide on purpose: 27% of jobs are written to more than 90
 *  days after completion, and `jobs` is not audited, so a genuine revenue
 *  restatement cannot be distinguished from a bulk re-sync. Re-pulling a year is
 *  ~1,100 rows — the volume is irrelevant, so there is nothing to gain by
 *  narrowing it and a silent desync to lose. */
export function refreshWindow(now: Date): { start: string; end: string } {
  const end = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
  const start = new Date(end);
  start.setUTCFullYear(start.getUTCFullYear() - 1);
  const iso = (d: Date) => d.toISOString().slice(0, 10);
  return { start: iso(start), end: iso(end) };
}

export function toJobRow(r: SliceRow) {
  if (!r.canonical_channel) {
    throw new Error(`job ${r.job_id} has no canonical_channel — refusing to mirror`);
  }
  return {
    job_id: r.job_id,
    customer_id: r.customer_id,
    completed_at: r.completed_at,
    revenue_amount: r.revenue_amount ?? 0,
    canonical_channel: r.canonical_channel,
    raw_lead_source: r.raw_lead_source,
    business_unit: r.business_unit,
    market: r.market,
    service_zip: r.service_zip,
  };
}
```

- [ ] **Step 4: Run to verify it passes**

```bash
deno test --allow-env --no-check supabase/functions/mirror-pull/pure.test.ts
```

Expected: `ok | 3 passed | 0 failed`.

- [ ] **Step 5: Implement `index.ts`**

The handler must: open a `mirror_runs` row; call `export_marketing_slice` on `twins-dash-prod` with the window start; map every row through `toJobRow` **before writing anything**; upsert `jobs_slice` on `job_id` and `spend` on `(spend_date, platform, campaign_name)`; then close the run row with counts. If any row fails to map, write nothing and record `status='failed'` with the error — a partial mirror that looks complete is worse than a stale one that admits it.

- [ ] **Step 6: Run it and verify reconciliation to the dollar**

Against `twins-dash-prod`:

```sql
select canonical_channel, count(*) jobs, sum(revenue_amount) revenue
from public.export_marketing_slice(current_date - 90) group by 1 order by 1;
```

Against the new project:

```sql
select canonical_channel, count(*) jobs, sum(revenue_amount) revenue
from public.jobs_slice where completed_at >= current_date - 90 group by 1 order by 1;
```

Expected: **identical row-for-row and to the cent.** Any difference stops the task.

- [ ] **Step 7: Verify idempotency**

Run the mirror twice. Expected: identical counts, no duplicate `jobs_slice` rows.

- [ ] **Step 8: Commit**

---

### Task 6: The rollup and the three pages

- [ ] **Step 1: Write the rollup SQL** as `supabase/migrations/0002_rollup.sql`: a `SECURITY INVOKER` function `get_channel_rollup(p_days integer, p_market text)` returning `TABLE(channel text, spend numeric, completed_jobs bigint, revenue numeric)` — matching TwinsDash's existing signature exactly, so the two are directly comparable in Step 2.

  Keep ratio arithmetic OUT of SQL. Return only these raw numerators and denominators and let `lib/metrics.ts` compute ROAS and CAC, so there is exactly one implementation of the undefined-vs-infinite rule.

  **Unknown share is derived, not returned** — TwinsDash's version does not emit it either. Compute it in the client as `revenue where channel = 'unknown'` ÷ `total revenue`, from the same rows.

- [ ] **Step 2: Reconcile the rollup against TwinsDash**

Run `get_channel_rollup(90, null)` on both projects. Expected: identical to the cent. **This is the acceptance test for the whole phase.**

- [ ] **Step 3: Build Overview** — KPI tiles (spend, revenue, blended ROAS, CAC, jobs), period selector 7/30/90, market filter, and the confidence strip showing attribution confidence and mirror freshness.

- [ ] **Step 4: Build Channels** — the per-channel table with ROAS chips, `n/a` for undefined ratios, and a low-confidence marker below the 8-job floor.

- [ ] **Step 5: Build Data health** — attribution confidence, campaign coverage (0%, explicitly labelled not-yet-measurable), and mirror freshness: amber past 24h, red past 48h, with an explicit "as of" timestamp.

- [ ] **Step 6: Verify the stale path honestly**

Set the newest `mirror_runs.finished_at` back 50 hours. Expected: the badge goes red and the "as of" timestamp shows the real age. The dashboard must never blank, and must never present stale numbers as current.

- [ ] **Step 7: Full verification**

```bash
npm run typecheck && npm run test && npm run build
```

- [ ] **Step 8: Commit and deploy to Vercel**

---

## Done when

- Logging in requires an invite; an uninvited address cannot reach any page
- `get_channel_rollup(90, null)` reconciles **to the cent** against `twins-dash-prod`
- Running the mirror twice changes nothing
- A job with no canonical channel fails the run rather than being bucketed as unknown
- `insert into spend` with platform `'Google Ads'` is rejected by the constraint
- Zero-spend channels render `n/a`, never `∞`
- A 50-hour-old mirror renders red with a real timestamp
- `typecheck`, `test` and `build` all pass

## Follow-on, not in this plan

Campaigns surface and the attribution ledger — both blocked, see the top of this document. The ledger additionally needs its DDL reconstructed from `google_ads_ops/attribution/ledger.py`'s INSERT statements, which is its own piece of work.
