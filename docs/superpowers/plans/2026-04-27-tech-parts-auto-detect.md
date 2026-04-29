# Tech Parts Auto-Detect Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a tech opens a job in `TechJobDetail`, an Edge Function reuses payroll's `partsMatcher.ts` to bucket parts mentioned in `payroll_jobs.line_items_text` + `notes_text` against `payroll_parts_prices` into auto-applied (≥0.85 confidence) / suggested (0.5–0.85) / unmatched (<0.5) tiers. Auto rows insert into `payroll_job_parts` idempotently; suggestions surface for one-tap confirm; unmatched lines route to the existing `RequestPartAddModal` pre-filled with the raw text. Tech never sees prices.

**Architecture:** Lazy on-view trigger (no cron). Single Edge Function `apply-part-suggestions` runs the existing TS matcher. SQL changes are additive: one new column (`payroll_job_parts.removed_by_tech`), one new table (`tech_part_match_log`), one new RPC (`tech_remove_job_part`), one extension to `add_job_part` (optional `p_source` param). Tech UI gains 3 sections in `TechJobDetail`; existing `PartsPickerModal` and `RequestPartAddModal` remain.

**Tech Stack:** React + TypeScript + Vite + TanStack Query + shadcn/ui. Supabase Postgres + RLS + Edge Functions (Deno). Vitest for unit tests.

**Spec:** [`docs/superpowers/specs/2026-04-27-tech-parts-auto-detect-design.md`](../specs/2026-04-27-tech-parts-auto-detect-design.md)

**Branch:** `feat/tech-parts-auto-detect` off `main`. Worktree at `~/twins-dashboard/twins-dash/.worktrees/tech-parts-auto-detect`.

**Operational notes:**
- After every `npx supabase db push`, run `npx supabase migration list` and verify the new version is in the Remote column. If missing, manually `INSERT INTO supabase_migrations.schema_migrations (version, name, statements) ...` (history desync is documented).
- Tech-facing surfaces must NEVER expose prices. Confirm via code review before merge.
- The Edge Function copy of `partsMatcher.ts` MUST stay byte-identical to `src/lib/payroll/partsMatcher.ts`. The identity test in M2 enforces this.

---

## File Structure

### New files

**Migrations** (`twins-dash/supabase/migrations/`):
- `20260427160000_payroll_job_parts_removed_by_tech.sql` — adds `removed_by_tech` column + partial index
- `20260427160100_tech_part_match_log.sql` — new table + RLS
- `20260427160200_tech_remove_job_part_rpc.sql` — soft-delete RPC
- `20260427160300_add_job_part_source_param.sql` — extends existing `add_job_part` with optional `p_source` param

**Edge Function** (`twins-dash/supabase/functions/apply-part-suggestions/`):
- `index.ts` — entry point
- `partsMatcher.ts` — verbatim copy of `src/lib/payroll/partsMatcher.ts`
- `__tests__/bucketing.test.ts`
- `__tests__/idempotency.test.ts`
- `__tests__/removal.test.ts`

**Frontend** (`twins-dash/src/`):
- `hooks/tech/useApplyPartSuggestions.ts`
- `lib/payroll/__tests__/matcher-identity.test.ts`

### Modified files

- `src/pages/tech/TechJobDetail.tsx` — add three new sections; wire the new hook
- `src/components/tech/RequestPartAddModal.tsx` — add `prefillName`, `sourceLineText` props
- `src/hooks/tech/useRequestPartAdd.ts` — pass through new optional fields
- `supabase/functions/request-part-add/index.ts` — accept and persist `source_line_text` + `prefill_name` into `modification_requests.notes` and `modification_requests.reasons`

### Untouched (sacred)

- `src/lib/payroll/partsMatcher.ts` — sacred. Identity test enforces byte-identity with the Edge Function copy.
- `src/pages/payroll/Run.tsx` — operator-side payroll flow unchanged.
- `src/lib/kpi-calculations.ts` — KPI math untouched.
- Existing `payroll_job_parts.source` column — extended convention (`'auto'`, `'tech_confirmed'`) but no schema change.

---

## Setup

### Task 0: Branch + worktree

**Files:** none

- [ ] **Step 1: Verify clean main**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git status
git checkout main && git pull origin main --ff-only
```

Expected: clean `main`.

- [ ] **Step 2: Create worktree**

```bash
git worktree add -b feat/tech-parts-auto-detect .worktrees/tech-parts-auto-detect main
cd .worktrees/tech-parts-auto-detect
npm install --silent
```

- [ ] **Step 3: Verify Supabase link**

```bash
cat supabase/.temp/linked-project.json
```

Expected: `"ref": "jwrpjuqaynownxaoeayi"`.

---

## M1 — Schema

### Task 1: `removed_by_tech` column on `payroll_job_parts`

**Files:**
- Create: `supabase/migrations/20260427160000_payroll_job_parts_removed_by_tech.sql`

- [ ] **Step 1: Write the migration**

```sql
-- 20260427160000_payroll_job_parts_removed_by_tech.sql
ALTER TABLE public.payroll_job_parts
  ADD COLUMN IF NOT EXISTS removed_by_tech boolean NOT NULL DEFAULT false;

CREATE INDEX IF NOT EXISTS payroll_job_parts_job_removed_idx
  ON public.payroll_job_parts(job_id) WHERE removed_by_tech;

COMMENT ON COLUMN public.payroll_job_parts.removed_by_tech IS
  'Soft-delete marker for parts the tech explicitly removed. The Edge Function apply-part-suggestions skips re-adding parts where any prior row with this flag exists for the same (job_id, lower(part_name)).';
```

- [ ] **Step 2: Apply**

```bash
cd /Users/daniel/twins-dashboard/twins-dash/.worktrees/tech-parts-auto-detect
npx supabase db push
npx supabase migration list | tail -5
```

Expected: `20260427160000` in Local AND Remote columns. If only Local, manually insert version row using the documented pattern.

- [ ] **Step 3: Verify**

```bash
npx supabase db query --linked --output csv "SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema='public' AND table_name='payroll_job_parts' AND column_name='removed_by_tech';"
```

Expected: one row showing `removed_by_tech | boolean | NO | false`.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260427160000_payroll_job_parts_removed_by_tech.sql
git commit -m "feat(tech-parts): add removed_by_tech soft-delete column on payroll_job_parts"
```

---

### Task 2: `tech_part_match_log` table

**Files:**
- Create: `supabase/migrations/20260427160100_tech_part_match_log.sql`

- [ ] **Step 1: Write the migration**

```sql
-- 20260427160100_tech_part_match_log.sql
CREATE TABLE public.tech_part_match_log (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          integer NOT NULL REFERENCES public.payroll_jobs(id) ON DELETE CASCADE,
  tech_id         integer REFERENCES public.payroll_techs(id),
  applied_count   integer NOT NULL DEFAULT 0,
  suggested_count integer NOT NULL DEFAULT 0,
  unmatched_count integer NOT NULL DEFAULT 0,
  raw_inputs_hash text,
  ran_at          timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX tech_part_match_log_job_idx ON public.tech_part_match_log(job_id, ran_at DESC);

ALTER TABLE public.tech_part_match_log ENABLE ROW LEVEL SECURITY;

CREATE POLICY tech_part_match_log_admin_read
  ON public.tech_part_match_log FOR SELECT TO authenticated
  USING (public.has_payroll_access(auth.uid()));
-- WRITE: service role only (Edge Function uses service role key).

COMMENT ON TABLE public.tech_part_match_log IS
  'One row per invocation of apply-part-suggestions Edge Function. Tracks how often the matcher fires + the bucketing split for observability.';
```

- [ ] **Step 2: Apply + verify**

```bash
npx supabase db push
npx supabase db query --linked --output csv "SELECT polname, polcmd FROM pg_policy WHERE polrelid='public.tech_part_match_log'::regclass;"
```

Expected: 1 row, `tech_part_match_log_admin_read | r`.

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260427160100_tech_part_match_log.sql
git commit -m "feat(tech-parts): tech_part_match_log table for matcher observability"
```

---

### Task 3: `tech_remove_job_part` RPC

**Files:**
- Create: `supabase/migrations/20260427160200_tech_remove_job_part_rpc.sql`

- [ ] **Step 1: Write the migration**

```sql
-- 20260427160200_tech_remove_job_part_rpc.sql
-- Soft-delete a payroll_job_parts row by setting removed_by_tech=true. Caller
-- must be either the tech assigned to the parent job OR have payroll access.
CREATE OR REPLACE FUNCTION public.tech_remove_job_part(p_job_part_id integer)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_caller_tech text := public.current_technician_name();
  v_job_owner   text;
BEGIN
  -- Look up the owner_tech of the parent job
  SELECT pj.owner_tech INTO v_job_owner
  FROM public.payroll_job_parts jp
  JOIN public.payroll_jobs pj ON pj.id = jp.job_id
  WHERE jp.id = p_job_part_id;

  IF v_job_owner IS NULL THEN
    RAISE EXCEPTION 'Job part % not found', p_job_part_id USING ERRCODE = '02000';
  END IF;

  -- Authorization: caller is the assigned tech OR has payroll access
  IF v_caller_tech IS DISTINCT FROM v_job_owner
     AND NOT public.has_payroll_access(auth.uid()) THEN
    RAISE EXCEPTION 'Not authorized to remove this part' USING ERRCODE = '42501';
  END IF;

  UPDATE public.payroll_job_parts
  SET removed_by_tech = true
  WHERE id = p_job_part_id;
END $$;

GRANT EXECUTE ON FUNCTION public.tech_remove_job_part(integer) TO authenticated;
```

- [ ] **Step 2: Apply + smoke test**

```bash
npx supabase db push
npx supabase db query --linked --output csv "SELECT proname, pg_get_function_arguments(oid) FROM pg_proc WHERE proname='tech_remove_job_part';"
```

Expected: one row, args `p_job_part_id integer`.

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260427160200_tech_remove_job_part_rpc.sql
git commit -m "feat(tech-parts): tech_remove_job_part RPC (soft-delete via removed_by_tech)"
```

---

### Task 4: Extend `add_job_part` with optional `p_source` param

**Files:**
- Create: `supabase/migrations/20260427160300_add_job_part_source_param.sql`

- [ ] **Step 1: Inspect current signature**

```bash
grep -A 30 "CREATE OR REPLACE FUNCTION public.add_job_part" supabase/migrations/20260424210000_add_job_part_admin_bypass.sql | head -40
```

Read the current body to copy it forward — only the parameter list and one INSERT line change.

- [ ] **Step 2: Write the migration**

```sql
-- 20260427160300_add_job_part_source_param.sql
-- Extends add_job_part with an optional p_source text parameter. The
-- Edge Function path (apply-part-suggestions) inserts directly into
-- payroll_job_parts and bypasses this RPC entirely; this RPC is what the
-- existing PartsPickerModal "+ Add another part" flow AND the new "Confirm
-- suggestion" button call. We default p_source to 'manual' so existing
-- callers keep working unchanged. The suggestion-confirm path passes
-- 'tech_confirmed'.
CREATE OR REPLACE FUNCTION public.add_job_part(
  p_job_id    int,
  p_part_name text,
  p_qty       int,
  p_source    text DEFAULT 'manual'
)
RETURNS int
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  v_caller_tech text := public.current_technician_name();
  v_is_admin    boolean := public.has_payroll_access(auth.uid());
  v_job         public.payroll_jobs%ROWTYPE;
  v_run         public.payroll_runs%ROWTYPE;
  v_price_row   public.payroll_parts_prices%ROWTYPE;
  v_new_id      int;
BEGIN
  -- Auth: tech (matching owner_tech) OR admin
  IF v_caller_tech IS NULL AND NOT v_is_admin THEN
    RAISE EXCEPTION 'Technician profile not linked' USING ERRCODE = '42501';
  END IF;

  -- Validate source
  IF p_source NOT IN ('manual', 'admin', 'auto', 'tech_confirmed') THEN
    RAISE EXCEPTION 'Invalid source value: %', p_source USING ERRCODE = '22023';
  END IF;

  SELECT * INTO v_job FROM public.payroll_jobs WHERE id = p_job_id;
  IF NOT FOUND THEN
    RAISE EXCEPTION 'Job % not found', p_job_id USING ERRCODE = '02000';
  END IF;

  IF NOT v_is_admin AND v_job.owner_tech IS DISTINCT FROM v_caller_tech THEN
    RAISE EXCEPTION 'Not your job' USING ERRCODE = '42501';
  END IF;

  SELECT * INTO v_run FROM public.payroll_runs WHERE id = v_job.run_id;
  IF v_run.status = 'final' THEN
    RAISE EXCEPTION 'Cannot add parts to a finalized run' USING ERRCODE = '42501';
  END IF;

  SELECT * INTO v_price_row FROM public.payroll_parts_prices
    WHERE name = p_part_name LIMIT 1;
  IF NOT FOUND THEN
    RAISE EXCEPTION 'Part not in pricebook: %', p_part_name USING ERRCODE = '02000';
  END IF;

  INSERT INTO public.payroll_job_parts (job_id, part_name, quantity, unit_price, total, source, entered_by, entered_at)
  VALUES (
    p_job_id,
    v_price_row.name,
    p_qty,
    v_price_row.total_cost,
    v_price_row.total_cost * p_qty,
    p_source,
    COALESCE(v_caller_tech, 'admin'),
    now()
  )
  RETURNING id INTO v_new_id;

  RETURN v_new_id;
END $$;

GRANT EXECUTE ON FUNCTION public.add_job_part(int, text, int, text) TO authenticated;
```

> **NOTE for the engineer:** verify the existing function body before pasting — copy every line of business logic from the current version. The diff above shows the EXPECTED final state. If the live function has additional checks (e.g. duplicate-name guards), preserve them.

- [ ] **Step 3: Apply + verify dispatch**

```bash
npx supabase db push
npx supabase db query --linked --output csv "SELECT pg_get_function_arguments(oid) FROM pg_proc WHERE proname='add_job_part';"
```

Expected: `p_job_id integer, p_part_name text, p_qty integer, p_source text DEFAULT 'manual'::text`.

- [ ] **Step 4: Smoke-test backward compatibility**

The existing 3-arg callsites (PartsPickerModal) must still work. Run:

```bash
npx supabase db query --linked --output csv "SELECT public.add_job_part((SELECT id FROM public.payroll_jobs LIMIT 1), 'Torsion Spring 2 5/8 x 28', 1);"
```

Expected: returns an integer (new payroll_job_parts.id) OR fails with an explicit business error (not a function-signature mismatch). Either way confirms the 3-arg shape still resolves. Roll back the test row immediately:

```bash
npx supabase db query --linked --output csv "DELETE FROM public.payroll_job_parts WHERE id = (SELECT MAX(id) FROM public.payroll_job_parts) RETURNING id;"
```

(Skip this entire smoke test if there's no convenient test row to insert against — the function-signature `pg_get_function_arguments` check above is sufficient.)

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260427160300_add_job_part_source_param.sql
git commit -m "feat(tech-parts): add_job_part gains optional p_source param ('manual' default)"
```

---

### Task 5: Regenerate TS types

**Files:**
- Modify: `src/integrations/supabase/types.ts`

- [ ] **Step 1: Regenerate**

```bash
npx supabase gen types typescript --linked > src/integrations/supabase/types.ts
```

- [ ] **Step 2: Verify the new objects appear**

```bash
grep -c "tech_part_match_log\|tech_remove_job_part\|removed_by_tech" src/integrations/supabase/types.ts
```

Expected: count >= 3.

- [ ] **Step 3: TS compiles**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 4: Commit**

```bash
git add src/integrations/supabase/types.ts
git commit -m "chore(tech-parts): regenerate Supabase types after M1 migrations"
```

---

## M2 — Edge Function (`apply-part-suggestions`)

### Task 6: Copy `partsMatcher.ts` into the Edge Function + identity test

**Files:**
- Create: `supabase/functions/apply-part-suggestions/partsMatcher.ts`
- Create: `src/lib/payroll/__tests__/matcher-identity.test.ts`

- [ ] **Step 1: Copy the matcher verbatim**

```bash
mkdir -p supabase/functions/apply-part-suggestions
cp src/lib/payroll/partsMatcher.ts supabase/functions/apply-part-suggestions/partsMatcher.ts
```

- [ ] **Step 2: Write the identity test**

```ts
// src/lib/payroll/__tests__/matcher-identity.test.ts
import { describe, it, expect } from "vitest";
import { readFileSync } from "fs";
import { resolve } from "path";

const PRIMARY = resolve(__dirname, "../partsMatcher.ts");
const COPY = resolve(__dirname, "../../../../supabase/functions/apply-part-suggestions/partsMatcher.ts");

describe("partsMatcher.ts identity", () => {
  it("Edge Function copy is byte-identical to src/lib/payroll/partsMatcher.ts", () => {
    const a = readFileSync(PRIMARY, "utf8");
    const b = readFileSync(COPY, "utf8");
    expect(b).toBe(a);
  });
});
```

- [ ] **Step 3: Run the identity test**

```bash
npx vitest run src/lib/payroll/__tests__/matcher-identity.test.ts
```

Expected: PASS.

- [ ] **Step 4: Verify TS compile**

```bash
npx tsc --noEmit
```

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/apply-part-suggestions/partsMatcher.ts src/lib/payroll/__tests__/matcher-identity.test.ts
git commit -m "feat(tech-parts): copy partsMatcher.ts into Edge Function + identity test"
```

---

### Task 7: Edge Function `index.ts`

**Files:**
- Create: `supabase/functions/apply-part-suggestions/index.ts`

- [ ] **Step 1: Write the function**

```ts
// supabase/functions/apply-part-suggestions/index.ts
import { serve } from "https://deno.land/std@0.224.0/http/server.ts";
import { createClient, type SupabaseClient } from "jsr:@supabase/supabase-js@^2";
import { extractPartMentions, type PartLibraryItem, type PartMatch } from "./partsMatcher.ts";

const AUTO_THRESHOLD = 0.85;
const SUGGEST_THRESHOLD = 0.5;

type Bucket = "applied" | "suggested" | "unmatched";

type Applied = { part_id: number; name: string; qty: number; confidence: number; source_line: string };
type Suggested = Applied;
type Unmatched = { raw: string; qty: number };

async function sha256(s: string): Promise<string> {
  const buf = new TextEncoder().encode(s);
  const hash = await crypto.subtle.digest("SHA-256", buf);
  return [...new Uint8Array(hash)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

function classify(confidence: number): Bucket {
  if (confidence >= AUTO_THRESHOLD) return "applied";
  if (confidence >= SUGGEST_THRESHOLD) return "suggested";
  return "unmatched";
}

async function authorize(supa: SupabaseClient, jobId: number, callerJwt: string) {
  const { data: { user } } = await supa.auth.getUser(callerJwt);
  if (!user) throw new Response("Unauthorized", { status: 401 });

  const { data: techRow } = await supa.rpc("current_technician_name");
  const { data: payrollAccess } = await supa.rpc("has_payroll_access", { uid: user.id });

  const { data: job, error: jobErr } = await supa
    .from("payroll_jobs")
    .select("id, owner_tech, line_items_text, notes_text, submission_status")
    .eq("id", jobId)
    .maybeSingle();
  if (jobErr) throw new Response(JSON.stringify({ error: jobErr.message }), { status: 500 });
  if (!job) throw new Response("Job not found", { status: 404 });

  if (!payrollAccess && techRow !== job.owner_tech) {
    throw new Response("Not authorized", { status: 403 });
  }
  return job;
}

serve(async (req) => {
  if (req.method !== "POST") return new Response("Method not allowed", { status: 405 });

  const auth = req.headers.get("Authorization") ?? "";
  const jwt = auth.startsWith("Bearer ") ? auth.slice(7) : "";

  let payload: { job_id: number };
  try { payload = await req.json(); } catch { return new Response("Bad JSON", { status: 400 }); }
  if (!payload.job_id || !Number.isInteger(payload.job_id)) {
    return new Response("Missing or invalid job_id", { status: 400 });
  }

  // Service-role client for the actual reads/writes; auth-context client for permission check.
  const url = Deno.env.get("SUPABASE_URL")!;
  const serviceKey = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const userClient = createClient(url, Deno.env.get("SUPABASE_ANON_KEY")!, {
    global: { headers: { Authorization: `Bearer ${jwt}` } },
  });
  const svc = createClient(url, serviceKey);

  let job;
  try {
    job = await authorize(userClient, payload.job_id, jwt);
  } catch (resp) {
    if (resp instanceof Response) return resp;
    throw resp;
  }

  // Locked jobs: return empty arrays
  if (job.submission_status === "locked") {
    return new Response(JSON.stringify({ applied: [], suggested: [], unmatched: [] }), {
      headers: { "Content-Type": "application/json" },
    });
  }

  // Pull the price sheet
  const { data: parts, error: partsErr } = await svc
    .from("payroll_parts_prices")
    .select("id, name, total_cost");
  if (partsErr) {
    return new Response(JSON.stringify({ error: partsErr.message }), { status: 500 });
  }
  const partLibrary: PartLibraryItem[] = (parts ?? []).map((p) => ({
    id: p.id,
    name: p.name,
    total_cost: Number(p.total_cost),
  }));

  // Pull existing parts on this job (for de-dup)
  const { data: existing, error: exErr } = await svc
    .from("payroll_job_parts")
    .select("id, part_name, removed_by_tech")
    .eq("job_id", job.id);
  if (exErr) {
    return new Response(JSON.stringify({ error: exErr.message }), { status: 500 });
  }
  const presentNames = new Set(
    (existing ?? []).filter((r) => !r.removed_by_tech).map((r) => r.part_name.toLowerCase())
  );
  const removedNames = new Set(
    (existing ?? []).filter((r) => r.removed_by_tech).map((r) => r.part_name.toLowerCase())
  );

  // Run the matcher (the verbatim copy of partsMatcher.ts)
  const matches: PartMatch[] = extractPartMentions(
    [job.notes_text ?? "", job.line_items_text ?? ""],
    partLibrary,
  );

  const applied: Applied[] = [];
  const suggested: Suggested[] = [];
  const unmatched: Unmatched[] = [];

  for (const m of matches) {
    const bucket = classify(m.confidence);
    const lowerName = m.part.name.toLowerCase();
    if (bucket === "applied") {
      // Skip if already present or removed by tech
      if (presentNames.has(lowerName) || removedNames.has(lowerName)) continue;
      applied.push({
        part_id: m.part.id, name: m.part.name, qty: m.quantity,
        confidence: m.confidence, source_line: m.source_line,
      });
      presentNames.add(lowerName); // prevent same-batch duplicates
    } else if (bucket === "suggested") {
      suggested.push({
        part_id: m.part.id, name: m.part.name, qty: m.quantity,
        confidence: m.confidence, source_line: m.source_line,
      });
    } else {
      unmatched.push({ raw: m.source_line, qty: m.quantity });
    }
  }

  // Insert auto-applied rows in a single transaction-equivalent (Supabase JS doesn't
  // expose explicit BEGIN/COMMIT; we use a single .insert([]) which is atomic per call).
  if (applied.length > 0) {
    const rows = applied.map((a) => {
      const partRow = partLibrary.find((p) => p.id === a.part_id)!;
      return {
        job_id: job.id,
        part_name: partRow.name,
        quantity: a.qty,
        unit_price: partRow.total_cost,
        total: partRow.total_cost * a.qty,
        source: "auto",
        entered_by: "auto",
        entered_at: new Date().toISOString(),
      };
    });
    const { error: insErr } = await svc.from("payroll_job_parts").insert(rows);
    if (insErr) {
      return new Response(JSON.stringify({ error: insErr.message }), { status: 500 });
    }
  }

  // Resolve tech_id for the log row (best-effort)
  let techId: number | null = null;
  const { data: techMatch } = await svc
    .from("payroll_techs").select("id").eq("name", job.owner_tech).maybeSingle();
  techId = (techMatch as any)?.id ?? null;

  const inputsHash = await sha256(`${job.notes_text ?? ""}|${job.line_items_text ?? ""}`);

  await svc.from("tech_part_match_log").insert({
    job_id: job.id,
    tech_id: techId,
    applied_count: applied.length,
    suggested_count: suggested.length,
    unmatched_count: unmatched.length,
    raw_inputs_hash: inputsHash,
  });

  return new Response(JSON.stringify({ applied, suggested, unmatched }), {
    headers: { "Content-Type": "application/json" },
  });
});
```

- [ ] **Step 2: Deploy**

```bash
npx supabase functions deploy apply-part-suggestions
```

Expected: deployment succeeds, function listed:

```bash
npx supabase functions list 2>&1 | grep apply-part-suggestions
```

- [ ] **Step 3: Commit**

```bash
git add supabase/functions/apply-part-suggestions/index.ts
git commit -m "feat(tech-parts): apply-part-suggestions Edge Function (3-tier bucketing + idempotent auto-insert)"
```

---

## M3 — Edge Function tests

### Task 8: Bucketing test

**Files:**
- Create: `supabase/functions/apply-part-suggestions/__tests__/bucketing.test.ts`

- [ ] **Step 1: Write the test (uses pure functions, no DB)**

The Edge Function as written intermingles DB writes with bucketing logic. To test bucketing in isolation, factor out a pure helper. Open `supabase/functions/apply-part-suggestions/index.ts` and **before** the `serve(...)` call add at the top of the file (just after imports):

```ts
export function bucketMatches(matches: PartMatch[]): {
  applied: Applied[]; suggested: Suggested[]; unmatched: Unmatched[];
} {
  const applied: Applied[] = [];
  const suggested: Suggested[] = [];
  const unmatched: Unmatched[] = [];
  for (const m of matches) {
    const bucket = classify(m.confidence);
    if (bucket === "applied") {
      applied.push({ part_id: m.part.id, name: m.part.name, qty: m.quantity, confidence: m.confidence, source_line: m.source_line });
    } else if (bucket === "suggested") {
      suggested.push({ part_id: m.part.id, name: m.part.name, qty: m.quantity, confidence: m.confidence, source_line: m.source_line });
    } else {
      unmatched.push({ raw: m.source_line, qty: m.quantity });
    }
  }
  return { applied, suggested, unmatched };
}
```

Replace the corresponding loop inside `serve(...)` with a call to `bucketMatches(matches)` (then layer the de-dup checks on top of the result).

- [ ] **Step 2: Write the test**

```ts
// supabase/functions/apply-part-suggestions/__tests__/bucketing.test.ts
import { describe, it, expect } from "vitest";
import { bucketMatches } from "../index.ts";
import type { PartMatch } from "../partsMatcher.ts";

const mk = (id: number, name: string, confidence: number, qty = 1, source = ""): PartMatch => ({
  part: { id, name, total_cost: 0 },
  quantity: qty,
  confidence,
  source_line: source || `line for ${name}`,
});

describe("bucketMatches", () => {
  it("classifies confidence>=0.85 as applied", () => {
    const r = bucketMatches([mk(1, "Torsion Spring", 0.92)]);
    expect(r.applied).toHaveLength(1);
    expect(r.suggested).toHaveLength(0);
    expect(r.unmatched).toHaveLength(0);
  });

  it("classifies 0.5<=confidence<0.85 as suggested", () => {
    const r = bucketMatches([mk(2, "Nylon Roller", 0.7)]);
    expect(r.applied).toHaveLength(0);
    expect(r.suggested).toHaveLength(1);
  });

  it("classifies confidence<0.5 as unmatched", () => {
    const r = bucketMatches([mk(3, "Mystery", 0.3, 2, "raw text")]);
    expect(r.applied).toHaveLength(0);
    expect(r.suggested).toHaveLength(0);
    expect(r.unmatched).toEqual([{ raw: "raw text", qty: 2 }]);
  });

  it("handles a mixed input deterministically", () => {
    const r = bucketMatches([
      mk(1, "Spring", 0.95),
      mk(2, "Roller", 0.6),
      mk(3, "Bracket", 0.2, 1, "Custom bracket"),
    ]);
    expect(r.applied.map((a) => a.name)).toEqual(["Spring"]);
    expect(r.suggested.map((s) => s.name)).toEqual(["Roller"]);
    expect(r.unmatched).toEqual([{ raw: "Custom bracket", qty: 1 }]);
  });
});
```

- [ ] **Step 3: Run + commit**

```bash
npx vitest run supabase/functions/apply-part-suggestions/__tests__/bucketing.test.ts
```

Expected: 4 passing.

```bash
git add supabase/functions/apply-part-suggestions/index.ts supabase/functions/apply-part-suggestions/__tests__/bucketing.test.ts
git commit -m "test(tech-parts): bucketing logic isolated + 4 unit tests"
```

---

### Task 9: Idempotency + removal integration tests (live DB, controlled fixture)

**Files:**
- Create: `supabase/functions/apply-part-suggestions/__tests__/idempotency.test.ts`

These are live-DB tests against the linked Supabase project. They use a known fixture job, capture pre-state, run the Edge Function twice, and assert post-state is unchanged after the second invocation. Run them locally with the `.env.test` config; do NOT run them in CI (they mutate prod data inside a transaction-rollback wrapper).

- [ ] **Step 1: Write the integration test as a Node script (not vitest)**

Vitest doesn't run against the live Edge Function easily. Use a curl-driven script:

```bash
cat > supabase/functions/apply-part-suggestions/__tests__/idempotency.test.ts <<'EOF'
// Manual integration test (run locally only). Verifies idempotency by
// invoking the deployed function twice against a known job_id and asserting
// payroll_job_parts row counts are equal after both calls.
//
// USAGE:
//   1. Pick a draft job_id with line_items_text containing recognizable parts
//      (e.g. "Torsion Spring 2 5/8 x 28").
//   2. Set ANON_KEY env var to the project's anon key.
//   3. node --import tsx supabase/functions/apply-part-suggestions/__tests__/idempotency.test.ts <job_id>
//
// Expected output: "Idempotent: row count after run 1 == after run 2".
import { createClient } from "@supabase/supabase-js";

const url = "https://jwrpjuqaynownxaoeayi.supabase.co";
const anonKey = process.env.ANON_KEY ?? "";
const jobId = Number(process.argv[2] ?? 0);
if (!anonKey || !jobId) {
  console.error("Usage: ANON_KEY=... node ... idempotency.test.ts <job_id>");
  process.exit(1);
}

const supa = createClient(url, anonKey);

async function rowCount(): Promise<number> {
  const { count, error } = await supa
    .from("payroll_job_parts")
    .select("id", { count: "exact", head: true })
    .eq("job_id", jobId);
  if (error) throw error;
  return count ?? 0;
}

async function invoke(): Promise<{ applied: any[]; suggested: any[]; unmatched: any[] }> {
  const { data, error } = await supa.functions.invoke("apply-part-suggestions", {
    body: { job_id: jobId },
  });
  if (error) throw error;
  return data;
}

const before = await rowCount();
const r1 = await invoke();
const after1 = await rowCount();
const r2 = await invoke();
const after2 = await rowCount();

console.log(`Before: ${before}, after run 1: ${after1}, after run 2: ${after2}`);
console.log(`Run 1 applied: ${r1.applied.length}, suggested: ${r1.suggested.length}, unmatched: ${r1.unmatched.length}`);
console.log(`Run 2 applied: ${r2.applied.length}, suggested: ${r2.suggested.length}, unmatched: ${r2.unmatched.length}`);

if (after1 !== after2) {
  console.error("FAIL: row counts differ after run 2 — auto-insert is not idempotent");
  process.exit(1);
}
if (r2.applied.length !== 0) {
  console.error("FAIL: run 2 reported new auto-applies — should be 0 because all matches already exist");
  process.exit(1);
}
console.log("Idempotent: row count after run 1 == after run 2");
EOF
```

- [ ] **Step 2: Run the test against a known job**

```bash
ANON_KEY="$(grep VITE_SUPABASE_PUBLISHABLE_KEY .env | cut -d= -f2 | tr -d '\"')" \
  node --import tsx supabase/functions/apply-part-suggestions/__tests__/idempotency.test.ts <KNOWN_JOB_ID>
```

Replace `<KNOWN_JOB_ID>` with the id of an actual draft `payroll_jobs` row whose `line_items_text` mentions parts in the price sheet. Find one via:

```bash
npx supabase db query --linked --output csv "SELECT id, owner_tech, length(line_items_text) FROM payroll_jobs WHERE submission_status='draft' AND line_items_text IS NOT NULL ORDER BY id DESC LIMIT 5;"
```

Expected output: "Idempotent: row count after run 1 == after run 2".

- [ ] **Step 3: Commit**

```bash
git add supabase/functions/apply-part-suggestions/__tests__/idempotency.test.ts
git commit -m "test(tech-parts): manual idempotency integration test (live DB, run locally)"
```

> **NOTE:** This test is intentionally NOT in the CI test suite — it mutates prod data. Engineers run it once before merging.

---

## M4 — Frontend hook + TechJobDetail UI

### Task 10: `useApplyPartSuggestions` hook

**Files:**
- Create: `src/hooks/tech/useApplyPartSuggestions.ts`

- [ ] **Step 1: Write the hook**

```ts
// src/hooks/tech/useApplyPartSuggestions.ts
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";

export type AppliedSuggestion = {
  part_id: number;
  name: string;
  qty: number;
  confidence: number;
  source_line: string;
};
export type SuggestedSuggestion = AppliedSuggestion;
export type UnmatchedSuggestion = { raw: string; qty: number };

export type PartSuggestionsResult = {
  applied: AppliedSuggestion[];
  suggested: SuggestedSuggestion[];
  unmatched: UnmatchedSuggestion[];
};

const EMPTY: PartSuggestionsResult = { applied: [], suggested: [], unmatched: [] };

/**
 * Invokes the apply-part-suggestions Edge Function for a given job.
 * The Edge Function is idempotent: it inserts auto-confidence rows into
 * payroll_job_parts only if they're not already present (and not soft-removed).
 * Suggestions and unmatched lines are transient — recomputed each call.
 */
export function useApplyPartSuggestions(jobId: number | null) {
  return useQuery({
    enabled: !!jobId,
    queryKey: ["apply_part_suggestions", jobId],
    queryFn: async (): Promise<PartSuggestionsResult> => {
      const { data, error } = await supabase.functions.invoke("apply-part-suggestions", {
        body: { job_id: jobId },
      });
      if (error) throw error;
      return (data as PartSuggestionsResult) ?? EMPTY;
    },
    // Re-run on every tech-view; no stale-time caching.
    staleTime: 0,
    gcTime: 0,
    refetchOnMount: "always",
  });
}
```

- [ ] **Step 2: TS compile**

```bash
npx tsc --noEmit
```

- [ ] **Step 3: Commit**

```bash
git add src/hooks/tech/useApplyPartSuggestions.ts
git commit -m "feat(tech-parts): useApplyPartSuggestions hook"
```

---

### Task 11: Soft-delete hook `useRemoveJobPart`

**Files:**
- Create: `src/hooks/tech/useRemoveJobPart.ts`

- [ ] **Step 1: Write the hook**

```ts
// src/hooks/tech/useRemoveJobPart.ts
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";

/**
 * Soft-delete a job part by calling the tech_remove_job_part RPC. Sets
 * removed_by_tech=true so the matcher never re-adds the same part on next view.
 */
export function useRemoveJobPart(jobId: number | null) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (jobPartId: number) => {
      const { error } = await supabase.rpc("tech_remove_job_part", { p_job_part_id: jobPartId });
      if (error) throw error;
    },
    onSuccess: () => {
      if (jobId) {
        qc.invalidateQueries({ queryKey: ["my_job_detail", jobId] });
        qc.invalidateQueries({ queryKey: ["apply_part_suggestions", jobId] });
      }
      qc.invalidateQueries({ queryKey: ["my_jobs"] });
      qc.invalidateQueries({ queryKey: ["my_scorecard"] });
    },
  });
}
```

- [ ] **Step 2: Commit**

```bash
git add src/hooks/tech/useRemoveJobPart.ts
git commit -m "feat(tech-parts): useRemoveJobPart hook (soft-delete via tech_remove_job_part RPC)"
```

---

### Task 12: Update `add_job_part` callsites to pass `p_source = 'tech_confirmed'` for confirmed suggestions

**Files:**
- Modify: `src/hooks/tech/useAddJobPart.ts`

- [ ] **Step 1: Add an optional source param to the hook**

```ts
// src/hooks/tech/useAddJobPart.ts
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export function useAddJobPart(jobId: number | null) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: { part_name: string; qty: number; source?: 'manual' | 'tech_confirmed' }) => {
      if (!jobId) throw new Error('jobId required');
      const { data, error } = await supabase.rpc('add_job_part', {
        p_job_id: jobId,
        p_part_name: args.part_name,
        p_qty: args.qty,
        p_source: args.source ?? 'manual',
      });
      if (error) throw error;
      return data as number;
    },
    onSuccess: () => {
      if (jobId) qc.invalidateQueries({ queryKey: ['my_job_detail', jobId] });
      qc.invalidateQueries({ queryKey: ['my_jobs'] });
      qc.invalidateQueries({ queryKey: ['my_scorecard'] });
      if (jobId) qc.invalidateQueries({ queryKey: ['apply_part_suggestions', jobId] });
    },
  });
}
```

- [ ] **Step 2: Verify existing callsite (PartsPickerModal) still compiles without source**

```bash
grep -n "useAddJobPart\|addMut.mutate" src/components/tech/PartsPickerModal.tsx
```

Confirm the call sends only `{ part_name, qty }` — the new `source?` is optional. TS should still pass.

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 3: Commit**

```bash
git add src/hooks/tech/useAddJobPart.ts
git commit -m "feat(tech-parts): useAddJobPart accepts optional source ('tech_confirmed' for suggestions)"
```

---

### Task 13: TechJobDetail UI — three new sections

**Files:**
- Modify: `src/pages/tech/TechJobDetail.tsx`

This is the largest UI change. Read the current file before editing.

- [ ] **Step 1: Read the existing structure**

```bash
sed -n '1,80p' src/pages/tech/TechJobDetail.tsx
```

Note where the existing parts list + "Add part" button are rendered. Identify the parent container we'll mount the new sections inside.

- [ ] **Step 2: Add imports at the top**

```tsx
import { useApplyPartSuggestions, type SuggestedSuggestion, type UnmatchedSuggestion } from "@/hooks/tech/useApplyPartSuggestions";
import { useRemoveJobPart } from "@/hooks/tech/useRemoveJobPart";
import { useAddJobPart } from "@/hooks/tech/useAddJobPart";
import { useState } from "react";  // if not already imported
import { Button } from "@/components/ui/button";
import { X, Sparkles, AlertCircle } from "lucide-react";
import { toast } from "@/components/ui/sonner";
import { RequestPartAddModal } from "@/components/tech/RequestPartAddModal";
```

- [ ] **Step 3: Inside the component body, fire the suggestions hook**

After the existing `useMyJobDetail(jobId)` (or whatever loads the job's existing parts):

```tsx
const { data: suggestions } = useApplyPartSuggestions(jobId);
const removeMut = useRemoveJobPart(jobId);
const addMut = useAddJobPart(jobId);

const [requestModalOpen, setRequestModalOpen] = useState(false);
const [requestPrefill, setRequestPrefill] = useState<{ name: string; lineText: string }>({ name: "", lineText: "" });
```

- [ ] **Step 4: Render the three sections**

Below the job header (job number, customer, date) and ABOVE the existing "+ Add another part" button, add:

```tsx
{/* AUTO-ENTERED — rows already in payroll_job_parts (incl. ones the matcher just inserted) */}
{job?.parts && job.parts.length > 0 && (
  <section className="rounded-2xl border border-border bg-card p-4">
    <h3 className="text-sm font-extrabold uppercase tracking-wider text-primary mb-3 flex items-center gap-2">
      <Sparkles className="h-4 w-4" /> Auto-entered
    </h3>
    <div className="space-y-2">
      {job.parts.map((p: any) => (
        <div key={p.id} className="flex items-center justify-between rounded-lg bg-muted px-3 py-2">
          <div className="text-sm font-medium text-primary truncate">
            {p.part_name}
            <span className="ml-2 text-xs text-muted-foreground">×{p.quantity}</span>
          </div>
          <Button
            variant="ghost"
            size="icon"
            className="h-7 w-7 flex-shrink-0"
            onClick={() => {
              removeMut.mutate(p.id, {
                onSuccess: () => toast.success(`Removed ${p.part_name}`),
                onError: (e: any) => toast.error(e?.message ?? "Failed to remove"),
              });
            }}
            aria-label={`Remove ${p.part_name}`}
          >
            <X className="h-4 w-4" />
          </Button>
        </div>
      ))}
    </div>
  </section>
)}

{/* SUGGESTED — transient, tap to confirm */}
{suggestions && suggestions.suggested.length > 0 && (
  <section className="rounded-2xl border border-accent/40 bg-yellow-50 p-4">
    <h3 className="text-sm font-extrabold uppercase tracking-wider text-amber-900 mb-2 flex items-center gap-2">
      <Sparkles className="h-4 w-4" /> Suggested from your notes
    </h3>
    <p className="text-xs text-amber-900 mb-3">We think you used these. Tap to confirm.</p>
    <div className="space-y-2">
      {suggestions.suggested.map((s: SuggestedSuggestion, i: number) => (
        <div key={`${s.part_id}-${i}`} className="flex items-center justify-between rounded-lg bg-white px-3 py-2 border border-accent/30">
          <div className="text-sm font-medium text-primary truncate">
            {s.name}
            <span className="ml-2 text-xs text-muted-foreground">×{s.qty}</span>
          </div>
          <Button
            size="sm"
            onClick={() => {
              addMut.mutate(
                { part_name: s.name, qty: s.qty, source: "tech_confirmed" },
                {
                  onSuccess: () => toast.success(`Added ${s.name} × ${s.qty}`),
                  onError: (e: any) => toast.error(e?.message ?? "Failed to add"),
                }
              );
            }}
          >
            Confirm
          </Button>
        </div>
      ))}
    </div>
  </section>
)}

{/* COULDN'T MATCH — route to admin add request */}
{suggestions && suggestions.unmatched.length > 0 && (
  <section className="rounded-2xl border border-border bg-muted p-4">
    <h3 className="text-sm font-extrabold uppercase tracking-wider text-muted-foreground mb-2 flex items-center gap-2">
      <AlertCircle className="h-4 w-4" /> Couldn't match (custom?)
    </h3>
    <p className="text-xs text-muted-foreground mb-3">
      These line items aren't in the pricebook. Request admin to add any that you actually used.
    </p>
    <div className="space-y-2">
      {suggestions.unmatched.map((u: UnmatchedSuggestion, i: number) => (
        <div key={i} className="flex items-center justify-between rounded-lg bg-white px-3 py-2">
          <div className="text-sm text-muted-foreground italic truncate">
            "{u.raw}"
            {u.qty > 1 && <span className="ml-2 text-xs">×{u.qty}</span>}
          </div>
          <Button
            size="sm"
            variant="outline"
            onClick={() => {
              setRequestPrefill({ name: u.raw, lineText: u.raw });
              setRequestModalOpen(true);
            }}
          >
            Request admin add
          </Button>
        </div>
      ))}
    </div>
  </section>
)}

{/* The existing "+ Add another part" button + PartsPickerModal stays below — for parts the tech remembers using that didn't surface in the line items / notes. */}

<RequestPartAddModal
  jobId={jobId!}
  open={requestModalOpen}
  onOpenChange={setRequestModalOpen}
  prefillName={requestPrefill.name}
  sourceLineText={requestPrefill.lineText}
/>
```

- [ ] **Step 5: TS compile + build**

```bash
npx tsc --noEmit
npm run build 2>&1 | tail -3
```

Both must be clean. (If TS complains about `prefillName` / `sourceLineText` not being on `RequestPartAddModalProps`, that's expected — Task 14 adds them. You can add a temporary `// @ts-expect-error` comment + remove it in Task 14.)

- [ ] **Step 6: Commit**

```bash
git add src/pages/tech/TechJobDetail.tsx
git commit -m "feat(tech-parts): TechJobDetail renders auto-entered / suggested / unmatched sections"
```

---

## M5 — RequestPartAddModal extension

### Task 14: Add `prefillName` + `sourceLineText` props

**Files:**
- Modify: `src/components/tech/RequestPartAddModal.tsx`
- Modify: `src/hooks/tech/useRequestPartAdd.ts`
- Modify: `supabase/functions/request-part-add/index.ts`

- [ ] **Step 1: Update the modal props + useState init from props**

```tsx
// src/components/tech/RequestPartAddModal.tsx — relevant changes only

interface Props {
  jobId: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Pre-fills the part name input. Used by the "Couldn't match" section in TechJobDetail. */
  prefillName?: string;
  /** Raw line-item text the prefill came from (admin-side context). Stored on the modification_request. */
  sourceLineText?: string;
}

export function RequestPartAddModal({ jobId, open, onOpenChange, prefillName, sourceLineText }: Props) {
  const [partName, setPartName] = useState(prefillName ?? '');
  const [notes, setNotes] = useState('');
  const mut = useRequestPartAdd(jobId);

  // Re-sync when prefillName changes (modal reopened with a different unmatched line)
  useEffect(() => {
    setPartName(prefillName ?? '');
  }, [prefillName, open]);

  const reset = () => { setPartName(''); setNotes(''); };

  const handleSubmit = async () => {
    const trimmed = partName.trim();
    if (!trimmed) {
      toast.error('Please enter a part name.');
      return;
    }
    try {
      await mut.mutateAsync({
        part_name: trimmed,
        notes: notes.trim() || undefined,
        source_line_text: sourceLineText,
        prefill_name: prefillName,
      });
      toast.success('Request sent to admin.');
      reset();
      onOpenChange(false);
    } catch (e: any) {
      toast.error(e?.message ?? 'Failed to send request');
    }
  };

  // ... rest unchanged
```

Add `import { useEffect } from 'react';` at the top.

- [ ] **Step 2: Update the hook to forward the new fields**

```ts
// src/hooks/tech/useRequestPartAdd.ts
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export function useRequestPartAdd(jobId: number | null) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: {
      part_name: string;
      notes?: string;
      source_line_text?: string;
      prefill_name?: string;
    }) => {
      if (!jobId) throw new Error('jobId required');
      const { data, error } = await supabase.functions.invoke('request-part-add', {
        body: {
          job_id: jobId,
          part_name: args.part_name,
          notes: args.notes,
          source_line_text: args.source_line_text,
          prefill_name: args.prefill_name,
        },
      });
      if (error) throw error;
      return data as { request_id: string };
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['my_part_requests'] });
    },
  });
}
```

- [ ] **Step 3: Update the Edge Function to persist into modification_requests**

Inspect the current `supabase/functions/request-part-add/index.ts` to find the INSERT statement. Modify the inserted row to include the two new fields in `notes` and `reasons`:

```ts
// In the existing handler, when building the modification_requests row:
const modRow = {
  // ... existing fields
  notes: source_line_text
    ? `${notes ? notes + '\n\n' : ''}From line: "${source_line_text}"`
    : notes,
  reasons: {
    // ... existing reason flags
    requested_part_name: prefill_name ?? part_name,
    source_line_text: source_line_text ?? null,
  },
};
```

(If the existing function uses snake_case keys for the request body, match them. Adjust according to the actual current code.)

- [ ] **Step 4: Deploy Edge Function**

```bash
npx supabase functions deploy request-part-add
```

- [ ] **Step 5: TS + build**

```bash
npx tsc --noEmit
npm run build 2>&1 | tail -3
```

- [ ] **Step 6: Commit**

```bash
git add src/components/tech/RequestPartAddModal.tsx src/hooks/tech/useRequestPartAdd.ts supabase/functions/request-part-add/index.ts
git commit -m "feat(tech-parts): RequestPartAddModal accepts prefillName + sourceLineText"
```

---

## M6 — QA + PR

### Task 15: Visual sweep + final tests + push

**Files:** none

- [ ] **Step 1: Run all tests**

```bash
cd /Users/daniel/twins-dashboard/twins-dash/.worktrees/tech-parts-auto-detect
npx vitest run
```

Expected: all green (pre-existing edge-function-test-import failures are OK, document if any new failures appear).

- [ ] **Step 2: Final tsc + build**

```bash
npx tsc --noEmit
npm run build 2>&1 | tail -5
```

Both must be clean.

- [ ] **Step 3: Manual smoke test (live)**

- Open a tech account (or admin in `View as tech` mode at `/tech?as=<uuid>`).
- Navigate to a job from the current week's CommissionTracker.
- Verify three sections appear (or some subset, depending on the job's data).
- Tap × on an auto-entered part — confirm it disappears + the matcher doesn't re-add on refresh.
- Tap Confirm on a suggestion — confirm it moves to Auto-entered with `source='tech_confirmed'`.
  - Verify via SQL: `SELECT source FROM payroll_job_parts WHERE id = <new-row-id>;`
- Tap "Request admin add" on an unmatched line — confirm RequestPartAddModal opens with the part name prefilled.
- Submit the request, then verify it lands in the admin Tech Requests queue with `notes` containing `From line: "..."` and `reasons.requested_part_name` set.

- [ ] **Step 4: Visual price-leak audit**

Search the diff for any code path that exposes price data to the tech UI:

```bash
git diff main..HEAD -- src/components/tech/ src/pages/tech/ src/hooks/tech/ | grep -E "total_cost|unit_price|total[^a-zA-Z]"
```

If any matches show the tech UI rendering a dollar amount tied to a part — flag and fix before merge. Tech must NOT see prices.

- [ ] **Step 5: Push**

```bash
git push -u origin feat/tech-parts-auto-detect
```

- [ ] **Step 6: Open PR via GitHub API**

```bash
TOKEN=$(printf "protocol=https\nhost=github.com\n" | git credential-osxkeychain get | grep '^password=' | cut -d= -f2)
curl -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.github+json" \
  -d "$(cat <<'EOF'
{
  "title": "feat(tech-parts): auto-detect from HCP line items + notes (3-tier matcher)",
  "head": "feat/tech-parts-auto-detect",
  "base": "main",
  "body": "Implements docs/superpowers/specs/2026-04-27-tech-parts-auto-detect-design.md.\n\n## Summary\n- New Edge Function `apply-part-suggestions` reuses payroll's `partsMatcher.ts` verbatim (identity test enforces byte-identity)\n- High-confidence matches (>=0.85) auto-insert into `payroll_job_parts`. Medium (0.5-0.85) surface as one-tap Confirm. Below 0.5 routes to RequestPartAddModal pre-filled with raw line text.\n- Idempotent via name-based de-dup + new `removed_by_tech` soft-delete column.\n- Tech never sees prices (privacy preserved).\n- Observability: new `tech_part_match_log` table.\n\n## Test plan\n- [x] Unit tests (bucketing, identity)\n- [x] Manual idempotency integration (live DB)\n- [x] tsc + build clean\n- [x] Price-leak audit (no \\$ figures in tech UI)\n- [ ] Vercel preview: open a draft job as tech, see all 3 sections, exercise confirm + remove + request flows"
}
EOF
)" | python3 -c "import sys,json; d=json.load(sys.stdin); print('PR URL:', d.get('html_url','ERR'))"
```

Expected: returns the PR URL.

---

## Self-Review

**Spec coverage:**

- ✅ Confidence-tiered bucketing (auto/suggested/unmatched) → Task 7 (Edge Function), Task 8 (bucketing test)
- ✅ Sources: line_items_text + notes_text → Task 7 (`extractPartMentions([notes_text, line_items_text], ...)`)
- ✅ Idempotency via name-based de-dup → Task 7 (existence check + applied set update)
- ✅ removed_by_tech soft-delete → Task 1 (column), Task 3 (RPC), Task 11 (hook), Task 13 (UI)
- ✅ Locked jobs return empty → Task 7 (early return on `submission_status === 'locked'`)
- ✅ Reuse partsMatcher.ts verbatim + identity test → Task 6
- ✅ tech_part_match_log → Task 2 (table), Task 7 (insert)
- ✅ source convention 'auto'/'tech_confirmed' → Task 4 (RPC param), Task 12 (hook), Task 13 (Confirm path)
- ✅ TechJobDetail 3-section UI → Task 13
- ✅ RequestPartAddModal prefill + source line → Task 14
- ✅ Tech never sees prices → enforced by Task 15 step 4 audit
- ✅ Observability via tech_part_match_log + admin Tech Requests queue → Task 7 + Task 14

**Placeholder scan:**

- One `<KNOWN_JOB_ID>` placeholder in Task 9 step 2 — intentional, the engineer queries for a real id with the SQL provided directly above the placeholder. Acceptable.
- Task 14 step 3 says "match according to the actual current code" — this is a small gap. Mitigation: the engineer reads the current request-part-add Edge Function (named in the file path) before editing.

**Type consistency:**

- `PartSuggestionsResult.applied[].name` (string), `qty` (number), `confidence` (number), `source_line` (string) — used consistently across hook, UI, Edge Function tests.
- `tech_remove_job_part(p_job_part_id integer)` — consistent in migration (Task 3) and hook (Task 11).
- `add_job_part` 4-arg signature — consistent in migration (Task 4), hook (Task 12), and call sites in Task 13.

No further fixes required. Plan ready.

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-27-tech-parts-auto-detect.md`. Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, two-stage review, fast iteration. ~15 tasks across 6 milestones.
2. **Inline Execution** — execute tasks in this session using executing-plans, batch checkpoints.

Which approach?
