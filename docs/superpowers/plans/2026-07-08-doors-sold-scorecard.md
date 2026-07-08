# Doors Sold (pending install) Scorecard — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the FOM a deterministic "Doors Sold" counter on the Rev & Rise tab — garage doors sold (deposit taken) but not yet installed — as a standalone daily card plus a per-tech line, with the sale value shown separately from (never folded into) the Revenue KPI.

**Architecture:** Two phases. **Phase 1 (backend data):** reliably ingest the child install job the moment a door is sold, so a `Door Install` / `Door + Opener Install` job exists in `jobs` with its correct `job_type` even while unscheduled — closing the null-scheduled gap that currently makes freshly-sold doors invisible. **Phase 2 (frontend):** a self-contained `use-doors-sold` hook counts non-completed install-type jobs in the call window, attributes them per tech via the existing Charles co-tech rule, and renders the card + per-tech line. Revenue KPI math is untouched.

**Tech Stack:** Vite + React + TypeScript, React Query, Supabase (Postgres + Deno edge functions), Vitest. HCP REST API (`Authorization: Token <HOUSECALL_PRO_API_KEY>`).

**Spec:** `docs/superpowers/specs/2026-07-08-doors-sold-scorecard-design.md`

**Working directory:** `/Users/daniel/twins-dashboard/twins-dash` (the Vite app / `palpulla/twins-dash`). All paths below are relative to it unless prefixed with `supabase/`.

---

## Background the executor must know

- **Door = human-set `job_type`.** The only trustworthy door signal is `job_type ∈ INSTALL_JOB_TYPES` = `["Door Install", "Door + Opener Install"]` (`src/lib/constants.ts`). Never infer door-ness from price or free text — this is a hard project rule.
- **Why the backend work exists.** When a door is sold in HCP the estimate option is approved and a child job is created, usually `work_status = "needs scheduling"` (no scheduled date). Two gaps make that child job invisible to us:
  1. `sync-hcp-jobs` fetches HCP `/jobs` filtered by `scheduled_start_min/max`, so an unscheduled job is never fetched.
  2. `hcp-webhook`'s `handleEstimateSold` has `data.copied_to_job_id` (the child job id) but only **back-links an existing row**; if the child row was never ingested, the link is a no-op and the job is lost.
  Confirmed against prod (`jwrpjuqaynownxaoeayi`): the child jobs for the two real example doors (estimates 2735 = $6,182 and 2730‑2 = $3,775, both sold Mon Jul 6 2026) have **no row at all**.
- **The fix.** In `handleEstimateSold`, when `copied_to_job_id` is present, fetch that job by id and upsert it (typed, status `pending`). Then pending doors are present and correctly typed going forward. A backfill handles the current in-flight doors.
- **Charles co-tech rule (LOAD-BEARING).** Attribute a job to a tech via `assigned_employees`, not the raw `tech_id`: a tech owns the job if their `hcp_employee_id` is in `assigned_employees`, EXCEPT Charles (`CHARLES_HCP_ID`) only owns a job when he is the sole assignee. Implemented today as `jobBelongsToTech` inside `src/hooks/use-rev-rise-data.ts`.
- **Money formatting:** full dollars, rounded, thousands separator, no `$Xk` (e.g. `$6,182`).
- **Tests:** Vitest. Run a single file with `npx vitest run <path>`. Existing patterns: `src/lib/rev-rise/__tests__/wins-rules.test.ts`, `src/hooks/__tests__/use-rev-rise-data.test.ts`, `src/pages/__tests__/RevRiseDashboard.test.tsx`.

---

## File map

**Phase 1 — backend (in `twins-dash/supabase/functions/`)**
- Create: `_shared/hcp/fetch-job.ts` — `fetchHcpJobById()` + `mapHcpJobToRow()` (single source of truth for job→row shape, mirrors `sync-hcp-jobs`).
- Create: `_shared/hcp/__tests__/map-hcp-job.test.ts` — unit tests for `mapHcpJobToRow`.
- Modify: `hcp-webhook/index.ts` — `handleEstimateSold` upserts the child job when missing; `handleJobUpdated`/`handleJobScheduled` refresh `job_type` + `status`.
- Create: `reconcile-pending-installs/index.ts` — one-shot/cron backfill of in-flight sold-door child jobs.

**Phase 2 — frontend (in `twins-dash/src/`)**
- Create: `lib/rev-rise/attribution.ts` — extract & export `getAssignedHcpIds`, `jobBelongsToTech`.
- Create: `lib/rev-rise/__tests__/attribution.test.ts`.
- Modify: `hooks/use-rev-rise-data.ts` — import the two helpers from `attribution.ts` (delete the local copies).
- Create: `hooks/use-doors-sold.ts` — the doors hook (rows + totals + per-day + per-tech map).
- Create: `hooks/__tests__/use-doors-sold.test.ts`.
- Create: `components/rev-rise/DoorsSoldCard.tsx`.
- Create: `components/rev-rise/__tests__/DoorsSoldCard.test.tsx`.
- Modify: `pages/RevRiseDashboard.tsx` — render `DoorsSoldCard`, pass a doors-by-tech map to `PerTechCards`.
- Modify: `components/rev-rise/PerTechCards.tsx` — add the `Doors sold: N ($X)` line.

---

# PHASE 2 FIRST? No — build Phase 1, then Phase 2.

Phase 2's hook reads rows Phase 1 produces. But Phase 2 is testable in isolation with fixtures, so if you prefer, Phase 2 can be built in parallel against the documented row shape. Recommended order below is Phase 1 → Phase 2. Phase 1 is a natural review checkpoint.

---

## Task 1: `mapHcpJobToRow` shared helper (pure, tested)

Extract the job→row mapping (currently inline in `sync-hcp-jobs`) into a shared, unit-tested pure function so the webhook and backfill produce identical rows.

**Files:**
- Create: `supabase/functions/_shared/hcp/fetch-job.ts`
- Test: `supabase/functions/_shared/hcp/__tests__/map-hcp-job.test.ts`

- [ ] **Step 1: Write the failing test**

```ts
// supabase/functions/_shared/hcp/__tests__/map-hcp-job.test.ts
import { describe, it, expect } from "vitest";
import { mapHcpJobToRow } from "../fetch-job.ts";

const doorJob = {
  id: "job_abc",
  work_status: "needs scheduling",
  job_fields: { job_type: { name: "Door Install" } },
  total_amount: 377500, // cents
  schedule: { scheduled_start: null },
  work_timestamps: {},
  original_estimate_uuids: ["est_cfc76"],
  assigned_employees: [{ id: "pro_x" }],
  lead_source: "WI Google LSA",
};

describe("mapHcpJobToRow", () => {
  it("maps an unscheduled door job to a pending install row", () => {
    const row = mapHcpJobToRow(doorJob, "tech_uuid_1");
    expect(row.job_id).toBe("job_abc");
    expect(row.job_type).toBe("Door Install");
    expect(row.status).toBe("pending");        // 'needs scheduling' -> pending
    expect(row.scheduled_at).toBeNull();
    expect(row.revenue_amount).toBe(3775);     // cents -> dollars
    expect(row.source_estimate_id).toBe("est_cfc76");
    expect(row.tech_id).toBe("tech_uuid_1");
    expect(row.hcp_data).toEqual(doorJob);
  });

  it("falls back to 'Service' when no job_type present", () => {
    const row = mapHcpJobToRow({ id: "job_z", work_status: "scheduled" }, null);
    expect(row.job_type).toBe("Service");
    expect(row.status).toBe("scheduled");
    expect(row.tech_id).toBeNull();
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd twins-dash && npx vitest run supabase/functions/_shared/hcp/__tests__/map-hcp-job.test.ts`
Expected: FAIL — `Cannot find module '../fetch-job.ts'`.

- [ ] **Step 3: Implement `fetch-job.ts`**

Mirror the exact mapping already used in `supabase/functions/sync-hcp-jobs/index.ts` (lines ~280–330) and its `mapWorkStatus` (lines ~373–392). Keep `mapHcpJobToRow` pure; keep the network call in a separate function.

```ts
// supabase/functions/_shared/hcp/fetch-job.ts
export function mapWorkStatus(status: string): string {
  const statusMap: Record<string, string> = {
    "needs scheduling": "pending",
    "scheduled": "scheduled",
    "on my way": "in_progress",
    "working": "in_progress",
    "in progress": "in_progress",
    "completed": "completed",
    "complete unrated": "completed",
    "complete rated": "completed",
    "finished": "completed",
    "canceled": "canceled",
    "user canceled": "canceled",
    "pro canceled": "canceled",
  };
  return statusMap[(status ?? "").toLowerCase()] || "pending";
}

export function extractJobType(job: any): string {
  return (
    job?.job_fields?.job_type?.name ??
    job?.job_type?.name ??
    (typeof job?.job_type === "string" ? job.job_type : null) ??
    job?.type ??
    "Service"
  );
}

/** Contract/earned amount in dollars (cents -> dollars). Tip stripping is
 *  only meaningful at completion; for pending jobs total_amount is the
 *  contract value, which is exactly what we want to show. */
export function extractRevenueDollars(job: any): number {
  const cents = Number(job?.total_amount ?? 0);
  return Math.max(0, cents / 100);
}

export interface HcpJobRow {
  job_id: string;
  job_type: string;
  status: string;
  scheduled_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  revenue_amount: number;
  is_opportunity: boolean;
  source_estimate_id: string | null;
  lead_source: string;
  tech_id: string | null;
  hcp_data: any;
}

export function mapHcpJobToRow(job: any, techId: string | null): HcpJobRow {
  const sourceEstimateId =
    job?.original_estimate_id ||
    (job?.original_estimate_uuids?.length > 0 ? job.original_estimate_uuids[0] : null);
  return {
    job_id: job.id,
    job_type: extractJobType(job),
    status: mapWorkStatus(job.work_status),
    scheduled_at: job?.schedule?.scheduled_start || null,
    started_at: job?.work_timestamps?.started_at || null,
    completed_at: job?.work_timestamps?.completed_at || null,
    revenue_amount: extractRevenueDollars(job),
    is_opportunity: false,
    source_estimate_id: sourceEstimateId,
    lead_source: job?.lead_source || job?.customer?.lead_source || "Unknown",
    tech_id: techId,
    hcp_data: job,
  };
}

/** Fetch a single job from HCP by id. Uses the proven auth pattern from
 *  sync-hcp-jobs (`Authorization: Token <key>`). */
export async function fetchHcpJobById(jobId: string, apiKey: string): Promise<any | null> {
  const res = await fetch(`https://api.housecallpro.com/jobs/${encodeURIComponent(jobId)}`, {
    method: "GET",
    headers: { Authorization: `Token ${apiKey}`, Accept: "application/json" },
  });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`HCP GET /jobs/${jobId} returned ${res.status}`);
  return await res.json();
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd twins-dash && npx vitest run supabase/functions/_shared/hcp/__tests__/map-hcp-job.test.ts`
Expected: PASS (2 tests).

- [ ] **Step 5: Verify the HCP by-id response shape (spike, no code change)**

The repo already fetches individual jobs (`api.housecallpro.com/jobs/job_...`). Confirm `GET /jobs/{id}` returns the same object shape used above (`job_fields.job_type.name`, `total_amount`, `work_status`, `schedule.scheduled_start`, `original_estimate_uuids`, `assigned_employees`). If a field differs, adjust `fetch-job.ts` and re-run Step 4.
Run: `cd twins-dash && grep -rn "housecallpro.com/jobs/" supabase/functions/*/index.ts`
Expected: at least one existing by-id fetch to model against.

- [ ] **Step 6: Commit**

```bash
git add supabase/functions/_shared/hcp/fetch-job.ts supabase/functions/_shared/hcp/__tests__/map-hcp-job.test.ts
git commit -m "feat(hcp): shared mapHcpJobToRow + fetchHcpJobById helper"
```

---

> **IMPORTANT — test placement (applies to Tasks 2 & 3):** Vitest is configured
> to EXCLUDE edge-function tests that import Deno/esm.sh modules, and its `include`
> globs only pick up `src/**`, `supabase/functions/_shared/**/__tests__/**`, and
> `supabase/functions/sync-gbp-reviews/__tests__/**`. `hcp-webhook/index.ts` imports
> `https://esm.sh/...` and uses the `Deno` global, so a vitest test that imports from
> it fails at collection AND would not be discovered anyway. Therefore all testable
> logic for Tasks 2 & 3 lives under `supabase/functions/_shared/hcp/` (pure, no Deno
> imports, inside the include glob). The `hcp-webhook/index.ts` change is thin wiring,
> verified by the reviewer reading code + the live check in Task 4/10.

## Task 2: On sale, ingest the child door job if it is missing

Add a tested `ingestChildJobIfMissing` helper in `_shared/hcp/`, then call it from `handleEstimateSold` so a freshly-sold door's child job is fetched and upserted (not just back-linked). This is the going-forward capture.

**Files:**
- Create: `supabase/functions/_shared/hcp/ingest.ts`
- Test: `supabase/functions/_shared/hcp/__tests__/ingest.test.ts`
- Modify: `supabase/functions/hcp-webhook/index.ts` (`handleEstimateSold`, ~line 954)

- [ ] **Step 1: Write the failing test**

Inject a fake supabase + fake fetcher so no network is hit.

```ts
// supabase/functions/_shared/hcp/__tests__/ingest.test.ts
import { describe, it, expect, vi } from "vitest";
import { ingestChildJobIfMissing } from "../ingest.ts";

function fakeSupabase(existingRowForJob: string | null) {
  const calls: any[] = [];
  return {
    calls,
    from() {
      return {
        _table: "jobs",
        select() { return this; },
        eq() { return this; },
        is() { return this; },
        maybeSingle: async () => ({ data: existingRowForJob ? { job_id: existingRowForJob } : null }),
        update(patch: any) { calls.push(["update", patch]); return { eq: () => ({ is: async () => ({ error: null }) }) }; },
        upsert: async (row: any) => { calls.push(["upsert", row]); return { error: null }; },
      };
    },
  } as any;
}

describe("ingestChildJobIfMissing", () => {
  it("fetches + upserts the child job when no row exists", async () => {
    const supabase = fakeSupabase(null);
    const fetchJob = vi.fn(async () => ({
      id: "job_new", work_status: "needs scheduling",
      job_fields: { job_type: { name: "Door + Opener Install" } },
      total_amount: 618200, original_estimate_uuids: ["est_9b5f"],
      assigned_employees: [{ id: "pro_5df7" }],
    }));
    await ingestChildJobIfMissing(supabase, "job_new", "APIKEY", fetchJob);
    expect(fetchJob).toHaveBeenCalledWith("job_new", "APIKEY");
    const upsert = supabase.calls.find((c: any) => c[0] === "upsert");
    expect(upsert[1].job_type).toBe("Door + Opener Install");
    expect(upsert[1].status).toBe("pending");
    expect(upsert[1].revenue_amount).toBe(6182);
  });

  it("does nothing when the child job row already exists", async () => {
    const supabase = fakeSupabase("job_exists");
    const fetchJob = vi.fn();
    await ingestChildJobIfMissing(supabase, "job_exists", "APIKEY", fetchJob);
    expect(fetchJob).not.toHaveBeenCalled();
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd twins-dash && npx vitest run supabase/functions/_shared/hcp/__tests__/ingest.test.ts`
Expected: FAIL — cannot find `../ingest.ts`.

- [ ] **Step 3: Implement `ingest.ts`**

```ts
// supabase/functions/_shared/hcp/ingest.ts
import { fetchHcpJobById, mapHcpJobToRow } from "./fetch-job.ts";

/** If the child job created from a sold estimate is not yet in our DB
 *  (common for "needs scheduling" installs the periodic sync never fetches),
 *  fetch it from HCP and upsert a typed row so pending doors are visible.
 *  `fetchJob` is injectable for tests; defaults to the real HCP fetch. */
export async function ingestChildJobIfMissing(
  supabase: any,
  childJobId: string,
  apiKey: string,
  fetchJob: (id: string, key: string) => Promise<any | null> = fetchHcpJobById,
) {
  if (!childJobId || !apiKey) return;
  const { data: existing } = await supabase
    .from("jobs").select("job_id").eq("job_id", childJobId).maybeSingle();
  if (existing) return;
  const job = await fetchJob(childJobId, apiKey);
  if (!job) return;
  // tech_id resolution is best-effort; leave null and let syncJobTechnicians /
  // later job.updated fill it. Attribution in the UI uses assigned_employees.
  const row = mapHcpJobToRow(job, null);
  const { error } = await supabase.from("jobs").upsert(row, { onConflict: "job_id" });
  if (error) console.error(`ingestChildJobIfMissing upsert failed for ${childJobId}:`, error);
  else console.log(`✅ Ingested pending child job ${childJobId} (${row.job_type})`);
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd twins-dash && npx vitest run supabase/functions/_shared/hcp/__tests__/ingest.test.ts`
Expected: PASS (2 tests).

- [ ] **Step 5: Wire it into the webhook (thin, untested-by-vitest)**

Add the import at the top of `hcp-webhook/index.ts`:
```ts
import { ingestChildJobIfMissing } from "../_shared/hcp/ingest.ts";
```
In `handleEstimateSold`, inside the existing `if (convertedJobId) { ... }` block, after the back-link update, add:
```ts
    const apiKey = Deno.env.get("HOUSECALL_PRO_API_KEY") ?? "";
    await ingestChildJobIfMissing(supabase, convertedJobId, apiKey);
```
Do NOT add a vitest test that imports `hcp-webhook/index.ts` (it imports Deno/esm.sh and is excluded from vitest). Verify the wiring by `deno check` if available, otherwise by reviewer code inspection.

- [ ] **Step 6: Commit**

```bash
git add supabase/functions/_shared/hcp/ingest.ts supabase/functions/_shared/hcp/__tests__/ingest.test.ts supabase/functions/hcp-webhook/index.ts
git commit -m "feat(hcp-webhook): ingest pending child door job on sale when missing"
```

---

## Task 3: Keep `job_type`/`status` current on job updates

`handleJobUpdated` currently does NOT refresh `job_type` or `status`, so a door typed after creation stays mislabeled `Service` while pending. Add a tiny tested pure helper and spread it into the update patch (and the scheduled patch). HCP payload is source of truth, matching how `handleJobCompleted` already re-extracts type.

**Files:**
- Modify: `supabase/functions/_shared/hcp/fetch-job.ts` (add `jobTypeStatusPatch`)
- Test: `supabase/functions/_shared/hcp/__tests__/map-hcp-job.test.ts` (append a case)
- Modify: `supabase/functions/hcp-webhook/index.ts` (`handleJobUpdated` ~663; `handleJobScheduled` ~771)

- [ ] **Step 1: Write the failing test (append to the existing map test file)**

```ts
// append to supabase/functions/_shared/hcp/__tests__/map-hcp-job.test.ts
import { jobTypeStatusPatch } from "../fetch-job.ts";

describe("jobTypeStatusPatch", () => {
  it("returns refreshed job_type and mapped status from the payload", () => {
    const patch = jobTypeStatusPatch({
      work_status: "scheduled",
      job_fields: { job_type: { name: "Door Install" } },
    });
    expect(patch).toEqual({ job_type: "Door Install", status: "scheduled" });
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd twins-dash && npx vitest run supabase/functions/_shared/hcp/__tests__/map-hcp-job.test.ts`
Expected: FAIL — `jobTypeStatusPatch` not exported.

- [ ] **Step 3: Implement the helper in `fetch-job.ts`**

```ts
/** The two fields job.updated / job.scheduled must refresh but currently
 *  don't. Reuses the already-tested extractors so a door typed after
 *  creation stays correctly typed while still pending. */
export function jobTypeStatusPatch(data: any): { job_type: string; status: string } {
  return { job_type: extractJobType(data), status: mapWorkStatus(data.work_status) };
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd twins-dash && npx vitest run supabase/functions/_shared/hcp/__tests__/map-hcp-job.test.ts`
Expected: PASS (all cases).

- [ ] **Step 5: Wire into the webhook (thin, untested-by-vitest)**

Add import to `hcp-webhook/index.ts`: `import { jobTypeStatusPatch } from "../_shared/hcp/fetch-job.ts";`
In `handleJobUpdated`, add the two fields to the existing `updatePatch` object literal (spread at the top so completion-time values still win elsewhere):
```ts
  const updatePatch: Record<string, unknown> = {
    ...jobTypeStatusPatch(data),   // NEW — keep type + status current pre-completion
    revenue_amount: extractRevenue(data),
    // ... rest of the existing patch unchanged ...
  };
```
In `handleJobScheduled`, if it does not already set `job_type` and `status`, add `...jobTypeStatusPatch(data)` to its update object the same way. Do NOT add a vitest test importing `hcp-webhook/index.ts`.

- [ ] **Step 6: Commit**

```bash
git add supabase/functions/_shared/hcp/fetch-job.ts supabase/functions/_shared/hcp/__tests__/map-hcp-job.test.ts supabase/functions/hcp-webhook/index.ts
git commit -m "fix(hcp-webhook): refresh job_type + status on job.updated"
```

---

## Task 4: Backfill in-flight sold-door child jobs

New edge function to recover the child jobs of doors already sold before Tasks 2–3 shipped (e.g. estimates 2730, 2735). For each recent sold estimate, resolve its converted job and upsert it.

**Files:**
- Create: `supabase/functions/reconcile-pending-installs/index.ts`

- [ ] **Step 1: Implement the function**

Strategy, in priority order per sold estimate (last `daysBack`, default 21):
1. If the estimate's stored `hcp_data.copied_to_job_id` exists → `ingestChildJobIfMissing`.
2. Else fetch the customer's jobs from HCP and match the child by estimate id. Model the auth/fetch on `sync-hcp-jobs`. **Verify the endpoint** (`GET /jobs?customer_id=<id>` vs `GET /customers/<id>/jobs`) against HCP during Step 2; use whichever returns the customer's jobs. Match a job where `original_estimate_uuids` includes the estimate's HCP id (strip the `est_`/`csr_` prefix), then upsert via `mapHcpJobToRow`.

```ts
// supabase/functions/reconcile-pending-installs/index.ts
import { createClient } from "https://esm.sh/@supabase/supabase-js@2.39.3";
import { corsHeaders, requireAdminAuth } from "../_shared/auth.ts";
import { fetchHcpJobById, mapHcpJobToRow } from "../_shared/hcp/fetch-job.ts";

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response(null, { headers: corsHeaders });
  await requireAdminAuth(req); // verify_jwt = false; service-role allowed, like siblings
  const body = await req.json().catch(() => ({}));
  const daysBack = typeof body.daysBack === "number" && body.daysBack > 0 ? Math.min(body.daysBack, 120) : 21;

  const apiKey = Deno.env.get("HOUSECALL_PRO_API_KEY") ?? "";
  const supabase = createClient(
    Deno.env.get("SUPABASE_URL") ?? "",
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? "",
  );

  const sinceIso = new Date(Date.now() - daysBack * 86400_000).toISOString();
  const { data: soldEstimates, error } = await supabase
    .from("jobs")
    .select("job_id, hcp_data")
    .eq("job_type", "Estimate")
    .eq("estimate_status", "sold")
    .gte("created_at", sinceIso);
  if (error) {
    return new Response(JSON.stringify({ error: error.message }), {
      status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }

  let ingested = 0, alreadyPresent = 0, unresolved = 0;
  for (const est of soldEstimates ?? []) {
    const childId: string | null = (est.hcp_data as any)?.copied_to_job_id ?? null;
    if (!childId) { unresolved++; continue; } // customer-jobs fallback added after endpoint verified
    const { data: existing } = await supabase.from("jobs").select("job_id").eq("job_id", childId).maybeSingle();
    if (existing) { alreadyPresent++; continue; }
    const job = await fetchHcpJobById(childId, apiKey);
    if (!job) { unresolved++; continue; }
    const { error: upErr } = await supabase.from("jobs").upsert(mapHcpJobToRow(job, null), { onConflict: "job_id" });
    if (upErr) { unresolved++; continue; }
    ingested++;
  }

  return new Response(JSON.stringify({ success: true, scanned: soldEstimates?.length ?? 0, ingested, alreadyPresent, unresolved }), {
    status: 200, headers: { ...corsHeaders, "Content-Type": "application/json" },
  });
});
```

- [ ] **Step 2: Deploy + dry-check, then run**

```bash
cd twins-dash
npx supabase functions deploy reconcile-pending-installs --project-ref jwrpjuqaynownxaoeayi
```
Then invoke it (service-role) and inspect the JSON counts. Confirm via SQL that `Door Install` / `Door + Opener Install` rows with `status <> 'completed'` now exist for the recent window:

```sql
select job_id, job_type, status, created_at, revenue_amount
from jobs
where job_type in ('Door Install','Door + Opener Install')
  and status not in ('completed','canceled')
  and created_at > now() - interval '21 days'
order by created_at desc;
```
Expected: the in-flight doors appear. If `unresolved` is high because estimates lack `copied_to_job_id`, implement the customer-jobs fallback (Step 1, option 2) using the endpoint verified here, then re-run.

- [ ] **Step 3: Commit**

```bash
git add supabase/functions/reconcile-pending-installs/index.ts
git commit -m "feat(hcp): reconcile-pending-installs backfill for sold-not-installed doors"
```

- [ ] **Step 4 (optional): schedule it** as a low-frequency safety net (e.g. hourly) via the project's existing cron mechanism, so any conversion the webhook missed is still recovered. Mirror an existing scheduled function's config. Do not email/alert on results (silent observability rule).

---

# PHASE 2 — Frontend

## Task 5: Extract the Charles-rule attribution helpers

Move `getAssignedHcpIds` + `jobBelongsToTech` out of `use-rev-rise-data.ts` into a shared module so the doors hook reuses the exact same rule.

**Files:**
- Create: `src/lib/rev-rise/attribution.ts`
- Test: `src/lib/rev-rise/__tests__/attribution.test.ts`
- Modify: `src/hooks/use-rev-rise-data.ts`

- [ ] **Step 1: Write the failing test**

```ts
// src/lib/rev-rise/__tests__/attribution.test.ts
import { describe, it, expect } from "vitest";
import { jobBelongsToTech } from "../attribution";
import { CHARLES_HCP_ID } from "@/lib/technicians";

const job = (ids: string[]) => ({ hcp_data: { assigned_employees: ids.map((id) => ({ id })) } }) as any;

describe("jobBelongsToTech", () => {
  it("attributes a solo job to the assigned tech", () => {
    expect(jobBelongsToTech(job(["pro_maurice"]), "pro_maurice")).toBe(true);
  });
  it("gives a Charles+other job to the OTHER tech, not Charles", () => {
    const j = job([CHARLES_HCP_ID, "pro_maurice"]);
    expect(jobBelongsToTech(j, "pro_maurice")).toBe(true);
    expect(jobBelongsToTech(j, CHARLES_HCP_ID)).toBe(false);
  });
  it("gives a Charles-solo job to Charles", () => {
    expect(jobBelongsToTech(job([CHARLES_HCP_ID]), CHARLES_HCP_ID)).toBe(true);
  });
  it("returns false for a null tech id", () => {
    expect(jobBelongsToTech(job(["pro_x"]), null)).toBe(false);
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd twins-dash && npx vitest run src/lib/rev-rise/__tests__/attribution.test.ts`
Expected: FAIL — cannot find `../attribution`.

- [ ] **Step 3: Create `attribution.ts` (copy the exact existing logic)**

```ts
// src/lib/rev-rise/attribution.ts
import { Tables } from "@/integrations/supabase/types";
import { CHARLES_HCP_ID } from "@/lib/technicians";

type Job = Tables<"jobs">;

/** HCP employee ids on a job's assigned_employees array. */
export function getAssignedHcpIds(job: Job): string[] {
  const employees = (job as any).hcp_data?.assigned_employees ?? [];
  if (!Array.isArray(employees)) return [];
  return employees
    .map((e: any) => e?.id)
    .filter((id: unknown): id is string => typeof id === "string" && id.length > 0);
}

/** Charles solo rule: a tech owns a job if assigned, except Charles owns it
 *  only when he is the sole assignee. */
export function jobBelongsToTech(job: Job, techHcpId: string | null): boolean {
  if (!techHcpId) return false;
  const assignedIds = getAssignedHcpIds(job);
  if (!assignedIds.includes(techHcpId)) return false;
  if (techHcpId === CHARLES_HCP_ID && assignedIds.length > 1) return false;
  return true;
}
```

- [ ] **Step 4: Rewire `use-rev-rise-data.ts`**

Delete the local `getAssignedHcpIds` and `jobBelongsToTech` definitions (lines ~19–40) and the now-unused `CHARLES_HCP_ID` import if it becomes unused; add:

```ts
import { jobBelongsToTech } from "@/lib/rev-rise/attribution";
```

- [ ] **Step 5: Run tests to verify green (new + existing rev-rise)**

Run: `cd twins-dash && npx vitest run src/lib/rev-rise/__tests__/attribution.test.ts src/hooks/__tests__/use-rev-rise-data.test.ts`
Expected: PASS for both (no behavior change in the data hook).

- [ ] **Step 6: Commit**

```bash
git add src/lib/rev-rise/attribution.ts src/lib/rev-rise/__tests__/attribution.test.ts src/hooks/use-rev-rise-data.ts
git commit -m "refactor(rev-rise): extract Charles-rule attribution to shared module"
```

---

## Task 6: `use-doors-sold` hook

Counts non-completed install-type jobs in the window, attributes per tech via the Charles rule, and returns rows + totals + per-day + per-tech map.

**Files:**
- Create: `src/hooks/use-doors-sold.ts`
- Test: `src/hooks/__tests__/use-doors-sold.test.ts`

Design notes:
- **Query:** `jobs` where `job_type in ('Door Install','Door + Opener Install')`, `status not in ('completed','canceled')`, `created_at` within `[from,to]`. Select `job_id, job_type, revenue_amount, created_at, tech_id, source_estimate_id, hcp_data`.
- **Sold day:** `created_at` bucketed to `America/Chicago`.
- **Value:** `revenue_amount` (contract total).
- **Attribution:** fetch active technicians (`id, name, hcp_employee_id`); for each door pick the tech where `jobBelongsToTech(job, hcp_employee_id)` is true; display name = that tech, else the first `assigned_employees[].first_name/last_name` from `hcp_data`, else `"Unassigned"`.
- **Gate:** `enabled: !!session && !!from && !!to` (React Query auth-race rule). Memoize range keys (pass ISO strings, not `Date` objects, into `queryKey`).
- Keep pure aggregation in an exported `aggregateDoors(rows, techs, tz)` so it is unit-testable without React Query.

- [ ] **Step 1: Write the failing test (pure aggregator)**

```ts
// src/hooks/__tests__/use-doors-sold.test.ts
import { describe, it, expect } from "vitest";
import { aggregateDoors, type DoorJobRow } from "../use-doors-sold";
import { CHARLES_HCP_ID } from "@/lib/technicians";

const techs = [
  { id: "u_charles", name: "Charles Rue", hcp_employee_id: CHARLES_HCP_ID },
  { id: "u_maurice", name: "Maurice Williams", hcp_employee_id: "pro_maurice" },
];

const door = (over: Partial<DoorJobRow> & { assigned: string[] }): DoorJobRow => ({
  job_id: over.job_id ?? "j1",
  job_type: over.job_type ?? "Door Install",
  revenue_amount: over.revenue_amount ?? 3775,
  created_at: over.created_at ?? "2026-07-06T18:10:00Z", // Mon Jul 6 (Central)
  hcp_data: { assigned_employees: over.assigned.map((id) => ({ id })), customer: { first_name: "A", last_name: "B" } },
} as DoorJobRow);

describe("aggregateDoors", () => {
  it("totals count and value across the window", () => {
    const res = aggregateDoors([
      door({ job_id: "j1", revenue_amount: 3775, assigned: ["pro_maurice"] }),
      door({ job_id: "j2", revenue_amount: 6182, created_at: "2026-07-07T14:00:00Z", assigned: ["pro_maurice"] }),
    ], techs, "America/Chicago");
    expect(res.count).toBe(2);
    expect(res.totalValue).toBe(9957);
  });

  it("buckets per day in Central time", () => {
    const res = aggregateDoors([
      door({ job_id: "j1", assigned: ["pro_maurice"] }),
      door({ job_id: "j2", created_at: "2026-07-07T14:00:00Z", assigned: ["pro_maurice"] }),
    ], techs, "America/Chicago");
    expect(res.perDay.map((d) => d.count)).toEqual([1, 1]);
  });

  it("attributes a Charles+Maurice door to Maurice", () => {
    const res = aggregateDoors([door({ assigned: [CHARLES_HCP_ID, "pro_maurice"] })], techs, "America/Chicago");
    expect(res.perTech.get("u_maurice")).toEqual({ count: 1, value: 3775 });
    expect(res.perTech.get("u_charles")).toBeUndefined();
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd twins-dash && npx vitest run src/hooks/__tests__/use-doors-sold.test.ts`
Expected: FAIL — cannot find `../use-doors-sold`.

- [ ] **Step 3: Implement the hook + aggregator**

```ts
// src/hooks/use-doors-sold.ts
import { useQuery } from "@tanstack/react-query";
import { useMemo } from "react";
import type { DateRange } from "react-day-picker";
import { supabase } from "@/integrations/supabase/client";
import { INSTALL_JOB_TYPES } from "@/lib/constants";
import { jobBelongsToTech } from "@/lib/rev-rise/attribution";
import type { Tables } from "@/integrations/supabase/types";

const TZ = "America/Chicago";

export interface DoorJobRow {
  job_id: string;
  job_type: string;
  revenue_amount: number;
  created_at: string;
  hcp_data: any;
}
interface TechLite { id: string; name: string; hcp_employee_id: string | null }

export interface DoorItem {
  job_id: string; customerName: string; techName: string;
  value: number; soldDayLabel: string; soldDayKey: string;
}
export interface DoorsSold {
  count: number; totalValue: number;
  perDay: { key: string; label: string; count: number; value: number }[];
  perTech: Map<string, { count: number; value: number }>;
  items: DoorItem[];
}

function dayKey(iso: string): string {
  // YYYY-MM-DD in Central
  return new Intl.DateTimeFormat("en-CA", { timeZone: TZ, year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date(iso));
}
function dayLabel(iso: string): string {
  return new Intl.DateTimeFormat("en-US", { timeZone: TZ, weekday: "short", month: "short", day: "numeric" }).format(new Date(iso));
}
function customerName(hcp: any): string {
  return [hcp?.customer?.first_name, hcp?.customer?.last_name].filter(Boolean).join(" ").trim() || "(no customer)";
}
function fallbackTechName(hcp: any): string {
  const e = (hcp?.assigned_employees ?? [])[0];
  const n = [e?.first_name, e?.last_name].filter(Boolean).join(" ").trim();
  return n || "Unassigned";
}

export function aggregateDoors(rows: DoorJobRow[], techs: TechLite[], _tz = TZ): DoorsSold {
  const perTech = new Map<string, { count: number; value: number }>();
  const perDayMap = new Map<string, { label: string; count: number; value: number }>();
  const items: DoorItem[] = [];
  let count = 0, totalValue = 0;

  for (const r of rows) {
    const value = Math.round(r.revenue_amount || 0);
    count += 1; totalValue += value;

    const owner = techs.find((t) => jobBelongsToTech(r as unknown as Tables<"jobs">, t.hcp_employee_id));
    const techName = owner?.name ?? fallbackTechName(r.hcp_data);
    if (owner) {
      const cur = perTech.get(owner.id) ?? { count: 0, value: 0 };
      perTech.set(owner.id, { count: cur.count + 1, value: cur.value + value });
    }

    const key = dayKey(r.created_at);
    const label = dayLabel(r.created_at);
    const d = perDayMap.get(key) ?? { label, count: 0, value: 0 };
    perDayMap.set(key, { label, count: d.count + 1, value: d.value + value });

    items.push({ job_id: r.job_id, customerName: customerName(r.hcp_data), techName, value, soldDayLabel: label, soldDayKey: key });
  }

  const perDay = Array.from(perDayMap.entries())
    .sort((a, b) => a[0].localeCompare(b[0]))
    .map(([key, v]) => ({ key, label: v.label, count: v.count, value: v.value }));
  items.sort((a, b) => (a.soldDayKey < b.soldDayKey ? 1 : a.soldDayKey > b.soldDayKey ? -1 : b.value - a.value));

  return { count, totalValue, perDay, perTech, items };
}

export function useDoorsSold(dateRange: DateRange | undefined) {
  const fromIso = dateRange?.from
    ? `${dateRange.from.getFullYear()}-${String(dateRange.from.getMonth() + 1).padStart(2, "0")}-${String(dateRange.from.getDate()).padStart(2, "0")}T00:00:00Z`
    : null;
  const toIso = dateRange?.to
    ? `${dateRange.to.getFullYear()}-${String(dateRange.to.getMonth() + 1).padStart(2, "0")}-${String(dateRange.to.getDate()).padStart(2, "0")}T23:59:59Z`
    : null;

  const query = useQuery({
    queryKey: ["doors-sold", fromIso, toIso],
    enabled: !!fromIso && !!toIso,
    staleTime: 30_000,
    refetchInterval: 60_000,
    queryFn: async (): Promise<{ rows: DoorJobRow[]; techs: TechLite[] }> => {
      const { data: { session } } = await supabase.auth.getSession();
      if (!session) return { rows: [], techs: [] };

      const { data: rows, error } = await supabase
        .from("jobs")
        .select("job_id, job_type, revenue_amount, created_at, hcp_data")
        .in("job_type", INSTALL_JOB_TYPES)
        .not("status", "in", "(completed,canceled)")
        .gte("created_at", fromIso!)
        .lte("created_at", toIso!)
        .order("created_at", { ascending: false });
      if (error) throw error;

      const { data: techs } = await supabase
        .from("technicians").select("id, name, hcp_employee_id").eq("is_active", true);
      return { rows: (rows ?? []) as DoorJobRow[], techs: (techs ?? []) as TechLite[] };
    },
  });

  const data = useMemo(
    () => aggregateDoors(query.data?.rows ?? [], query.data?.techs ?? []),
    [query.data],
  );
  return { ...query, doors: data };
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd twins-dash && npx vitest run src/hooks/__tests__/use-doors-sold.test.ts`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/hooks/use-doors-sold.ts src/hooks/__tests__/use-doors-sold.test.ts
git commit -m "feat(rev-rise): use-doors-sold hook (pending install doors)"
```

---

## Task 7: `DoorsSoldCard` component

**Files:**
- Create: `src/components/rev-rise/DoorsSoldCard.tsx`
- Test: `src/components/rev-rise/__tests__/DoorsSoldCard.test.tsx`

- [ ] **Step 1: Write the failing test**

```tsx
// src/components/rev-rise/__tests__/DoorsSoldCard.test.tsx
import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { DoorsSoldCard } from "../DoorsSoldCard";
import type { DoorsSold } from "@/hooks/use-doors-sold";

const doors: DoorsSold = {
  count: 2, totalValue: 9957,
  perDay: [
    { key: "2026-07-06", label: "Mon Jul 6", count: 1, value: 3775 },
    { key: "2026-07-07", label: "Tue Jul 7", count: 1, value: 6182 },
  ],
  perTech: new Map(),
  items: [
    { job_id: "j2", customerName: "Brandon Bastasic", techName: "Maurice Williams", value: 6182, soldDayLabel: "Tue Jul 7", soldDayKey: "2026-07-07" },
    { job_id: "j1", customerName: "A B", techName: "Maurice Williams", value: 3775, soldDayLabel: "Mon Jul 6", soldDayKey: "2026-07-06" },
  ],
};

describe("DoorsSoldCard", () => {
  it("shows count, total value, and the not-in-revenue note", () => {
    render(<DoorsSoldCard doors={doors} isLoading={false} />);
    expect(screen.getByText(/2 sold/i)).toBeInTheDocument();
    expect(screen.getByText(/\$9,957/)).toBeInTheDocument();
    expect(screen.getByText(/not counted in revenue/i)).toBeInTheDocument();
    expect(screen.getByText(/Brandon Bastasic/)).toBeInTheDocument();
  });

  it("renders an empty state", () => {
    render(<DoorsSoldCard doors={{ ...doors, count: 0, totalValue: 0, items: [], perDay: [] }} isLoading={false} />);
    expect(screen.getByText(/no doors sold in this window/i)).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd twins-dash && npx vitest run src/components/rev-rise/__tests__/DoorsSoldCard.test.tsx`
Expected: FAIL — cannot find `../DoorsSoldCard`.

- [ ] **Step 3: Implement (match `RecentSoldJobs` styling: `rd-card`, navy headers)**

```tsx
// src/components/rev-rise/DoorsSoldCard.tsx
import type { DoorsSold } from "@/hooks/use-doors-sold";

const fmtUsd = (n: number) => `$${Math.round(n).toLocaleString("en-US")}`;

export function DoorsSoldCard({ doors, isLoading }: { doors: DoorsSold; isLoading: boolean }) {
  return (
    <section className="rd-card" style={{ padding: 16, marginTop: 16 }}>
      <header style={{ marginBottom: 10 }}>
        <h3 style={{ margin: 0, fontSize: 13, fontWeight: 800, color: "var(--rd-navy)", textTransform: "uppercase", letterSpacing: ".06em" }}>
          Doors Sold
          {!isLoading && (
            <span style={{ marginLeft: 8, fontSize: 12, fontWeight: 500, color: "var(--rd-muted)", textTransform: "none", letterSpacing: 0 }}>
              {doors.count} sold · {fmtUsd(doors.totalValue)}
            </span>
          )}
        </h3>
        <p style={{ margin: "4px 0 0", fontSize: 12, color: "var(--rd-muted)" }}>
          Sold, pending install — deposits taken, not counted in revenue.
        </p>
        {doors.perDay.length > 0 && (
          <p style={{ margin: "6px 0 0", fontSize: 12, color: "var(--rd-navy)", fontWeight: 600 }}>
            {doors.perDay.map((d) => `${d.label} ${d.count}`).join(" · ")}
          </p>
        )}
      </header>

      {isLoading && <div style={{ fontSize: 12, color: "var(--rd-muted)" }}>Loading doors…</div>}
      {!isLoading && doors.items.length === 0 && (
        <div style={{ fontSize: 12, color: "var(--rd-muted)" }}>No doors sold in this window yet.</div>
      )}

      {doors.items.length > 0 && (
        <ul style={{ listStyle: "none", margin: 0, padding: 0, display: "grid", gap: 6 }}>
          {doors.items.map((it) => (
            <li key={it.job_id} style={{ display: "grid", gridTemplateColumns: "1fr auto", alignItems: "baseline", gap: 12, padding: "8px 10px", borderRadius: 8, border: "1px solid var(--rd-line)", background: "#fff" }}>
              <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600, color: "var(--rd-navy)", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{it.customerName}</div>
                <div style={{ fontSize: 11, color: "var(--rd-muted)", marginTop: 2 }}>{it.techName} · {it.soldDayLabel}</div>
              </div>
              <div style={{ fontSize: 14, fontWeight: 800, color: "var(--rd-navy)", fontVariantNumeric: "tabular-nums", whiteSpace: "nowrap" }}>{fmtUsd(it.value)}</div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd twins-dash && npx vitest run src/components/rev-rise/__tests__/DoorsSoldCard.test.tsx`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/components/rev-rise/DoorsSoldCard.tsx src/components/rev-rise/__tests__/DoorsSoldCard.test.tsx
git commit -m "feat(rev-rise): DoorsSoldCard component"
```

---

## Task 8: Render the card + wire per-tech doors in `RevRiseDashboard`

**Files:**
- Modify: `src/pages/RevRiseDashboard.tsx`

- [ ] **Step 1: Add the hook + render the card (weekly branch only)**

Add import: `import { useDoorsSold } from "@/hooks/use-doors-sold";` and `import { DoorsSoldCard } from "@/components/rev-rise/DoorsSoldCard";`

In the component body (near the other hooks):
```tsx
  const { doors, isLoading: doorsLoading } = useDoorsSold(dateRange);
```

In the `mode !== 'daily'` branch, render the card right after `<CompanyKpiStrip .../>`:
```tsx
          <DoorsSoldCard doors={doors} isLoading={doorsLoading} />
```

- [ ] **Step 2: Pass the per-tech map to `PerTechCards`**

Change the existing render to:
```tsx
          <PerTechCards techs={data?.technicianKPIs ?? []} doorsByTech={doors.perTech} />
```

- [ ] **Step 3: Verify the existing page test still passes**

Run: `cd twins-dash && npx vitest run src/pages/__tests__/RevRiseDashboard.test.tsx`
Expected: PASS. If the test renders without a QueryClient/session and now fails on the new hook, wrap or mock `useDoorsSold` the same way the test already handles `useRevRiseData` (follow the existing mock pattern in that file).

- [ ] **Step 4: Commit**

```bash
git add src/pages/RevRiseDashboard.tsx
git commit -m "feat(rev-rise): render DoorsSoldCard + pass per-tech doors"
```

---

## Task 9: Per-tech `Doors sold` line on `PerTechCards`

**Files:**
- Modify: `src/components/rev-rise/PerTechCards.tsx`
- Test: `src/components/rev-rise/__tests__/PerTechCards.doors.test.tsx` (create)

- [ ] **Step 1: Write the failing test**

```tsx
// src/components/rev-rise/__tests__/PerTechCards.doors.test.tsx
import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { PerTechCards } from "../PerTechCards";
import type { TechKPIRow } from "@/hooks/use-rev-rise-data";

const tech = (over: Partial<TechKPIRow>): TechKPIRow => ({
  techId: "u_maurice", techName: "Maurice Williams", isSupervisor: false,
  revenue: 0, revenuePerJob: 0, closeRate: 0, avgTicket: 0, jobsPerDay: 0,
  avgOpportunity: 0, avgInstall: 0, avgRepair: 0, openEstimates: 0, openEstimateValue: 0,
  reviews: 0, avgRating: 0, completedJobs: 0, opportunities: 0, ...over,
});

describe("PerTechCards doors line", () => {
  it("shows Doors sold count + value for the tech", () => {
    render(
      <MemoryRouter>
        <PerTechCards techs={[tech({})]} doorsByTech={new Map([["u_maurice", { count: 2, value: 9957 }]])} />
      </MemoryRouter>
    );
    expect(screen.getByText(/Doors sold/i)).toBeInTheDocument();
    expect(screen.getByText(/2 \(\$9,957\)/)).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd twins-dash && npx vitest run src/components/rev-rise/__tests__/PerTechCards.doors.test.tsx`
Expected: FAIL — `doorsByTech` prop not supported / text missing.

- [ ] **Step 3: Implement**

Update the `Props` and add the metric row. New prop is optional so existing callers/tests are unaffected.

```tsx
interface Props {
  techs: TechKPIRow[];
  doorsByTech?: Map<string, { count: number; value: number }>;
}
```

In `PerTechCards({ techs, doorsByTech })`, inside the `.map((t) => { ... })` body, compute:
```tsx
        const doors = doorsByTech?.get(t.techId);
```
And add this metric row inside `.tcard-body`, after the Open est. row:
```tsx
              {doors && doors.count > 0 && (
                <div className="metric">
                  <span className="m-label">Doors sold</span>
                  <span className="m-val">{doors.count} ({fmtUsd(doors.value)})</span>
                </div>
              )}
```
(`fmtUsd` already exists in this file.)

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd twins-dash && npx vitest run src/components/rev-rise/__tests__/PerTechCards.doors.test.tsx`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/components/rev-rise/PerTechCards.tsx src/components/rev-rise/__tests__/PerTechCards.doors.test.tsx
git commit -m "feat(rev-rise): per-tech Doors sold line on tech cards"
```

---

## Task 10: Full suite + live verification

- [ ] **Step 1: Run the full test suite**

Run: `cd twins-dash && npx vitest run`
Expected: PASS (all, including pre-existing).

- [ ] **Step 2: Verify in the running app (preview)**

Start the dev server (preview tooling), open the Rev & Rise tab in weekly mode, and confirm:
- The **Doors Sold** card shows the in-flight doors (after Task 4's backfill), with per-day breakdown and the "not counted in revenue" note.
- Each door attributes to the right tech (Charles+other → the other tech).
- The Revenue KPI value is unchanged from before (compare against a pre-change screenshot).
Capture a screenshot for Daniel.

- [ ] **Step 3: Verify revenue KPI is untouched (data check)**

Confirm `use-rev-rise-data` output did not change: the existing `use-rev-rise-data.test.ts` still passes and the CompanyKpiStrip revenue equals the sum of completed, revenue-bearing, non-estimate jobs (unchanged formula). Doors are non-completed, so they cannot enter that sum.

---

## Self-review against the spec

- **Definition (approved installs, not completed, true door type):** Tasks 1–4 ensure typed pending door jobs exist; Task 6 filters `INSTALL_JOB_TYPES` + `status not in (completed,canceled)`. ✔
- **No heuristics:** door-ness is the human-set `job_type` only; unresolved child jobs are simply absent, never guessed. ✔
- **Value = contract total, shown separately:** `revenue_amount` on the pending job; Revenue KPI formula (`use-rev-rise-data`) untouched — doors are non-completed so excluded by construction. ✔
- **Sold date / current call window:** Task 6 filters `created_at` within the tab's `dateRange`, buckets per day in Central. ✔
- **Both placements:** standalone card (Tasks 7–8) + per-tech line (Task 9). ✔
- **Charles co-tech rule:** shared `jobBelongsToTech` (Task 5) used for per-tech attribution (Task 6). ✔
- **Money format, no `$Xk`:** `fmtUsd` rounds to full dollars with separators. ✔
- **Reversibility:** additive files + additive columns of behavior; no KPI math changed; each task committed atomically. ✔

## Known risks / verify-live items

- **HCP `GET /jobs/{id}` and customer-jobs endpoint shapes** — verified in Task 1 Step 5 and Task 4 Step 2 against the live API; adjust field paths if HCP differs.
- **`copied_to_job_id` coverage** — going-forward capture (Task 2) depends on the sold/copy_to_job webhook delivering it; the reconcile function (Task 4) + hourly schedule are the safety net for anything missed. If many historical estimates lack it, implement the Task 4 customer-jobs fallback.
- **Estimate visits vs installs:** only `Door Install` / `Door + Opener Install` count; the estimate row itself (`job_type = 'Estimate'`) is never counted.
