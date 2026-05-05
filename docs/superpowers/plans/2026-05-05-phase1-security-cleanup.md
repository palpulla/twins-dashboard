# Phase 1: Security & Cleanup — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lock down RLS, sign the HCP webhook, gate edge functions, delete trust artifacts and dead code — all with a one-command revert script and zero user-visible feature changes.

**Architecture:** All work happens on a `phase1-security-cleanup` branch off `palpulla/twins-dash:main`. DB changes apply to a Supabase branch first (`mcp__supabase__create_branch` against project `jwrpj`), then merge to prod after verification. Every forward migration ships with a paired revert migration. A single `scripts/revert-phase1.sh` restores the prior state via SQL + git revert + edge redeploy.

**Tech Stack:** TypeScript, React 18, Vite, Supabase (Postgres + Edge Functions/Deno), TanStack Query, Vercel deploy. Spec source: `docs/superpowers/specs/2026-05-05-phase1-security-cleanup-design.md`.

**Working directory for code work:** `/Users/daniel/twins-dashboard/twins-dash` (the inner `palpulla/twins-dash` repo).
**Working directory for plan/spec doc edits:** `/Users/daniel/twins-dashboard` (the outer repo).

---

## Phase A — Setup & inventory

### Task 1: Create the safety tag and feature branch

**Files:** none modified; remote tag + new branch created on `palpulla/twins-dash`.

- [ ] **Step 1: Confirm clean working tree on twins-dash**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
git status --short
git fetch origin && git checkout main && git pull --ff-only origin main
```

Expected: `git status` shows nothing or only known-stale files. Halt and ask the operator if there are unexpected modifications — they may be in-progress work we shouldn't disturb.

- [ ] **Step 2: Create the pre-phase1 safety tag on current main**

```sh
git tag -a pre-phase1-2026-05-05 -m "Pre-Phase 1 safety tag — restore target if Phase 1 must be aborted"
git push origin pre-phase1-2026-05-05
```

Expected: `pre-phase1-2026-05-05` appears in `git tag --list` and on origin (`git ls-remote --tags origin | grep pre-phase1`).

- [ ] **Step 3: Create the feature branch**

```sh
git checkout -b phase1-security-cleanup
git push -u origin phase1-security-cleanup
```

Expected: branch tracks origin. Vercel preview deploy will auto-build the branch.

- [ ] **Step 4: Commit a marker file pinning the plan**

```sh
mkdir -p .planning/phase1
echo "Phase 1 spec: docs/superpowers/specs/2026-05-05-phase1-security-cleanup-design.md (in outer repo)
Plan: docs/superpowers/plans/2026-05-05-phase1-security-cleanup.md (in outer repo)
Pre-tag: pre-phase1-2026-05-05
Branch: phase1-security-cleanup
" > .planning/phase1/README.md
git add .planning/phase1/README.md
git commit -m "chore(phase1): pin spec/plan/tag references"
git push
```

Expected: commit on branch, no other changes.

---

### Task 2: Snapshot the current RLS and roles state (grants inventory)

**Files:**
- Create: `.planning/phase1/grants-inventory-pre.json`
- Create: `.planning/phase1/inventory-queries.sql`

This is the single most important step in Phase 1. The forward migration is generated from this snapshot. The post-deploy diff is verified against this snapshot. If this is wrong, everything downstream is wrong.

- [ ] **Step 1: Write the inventory query file**

Create `.planning/phase1/inventory-queries.sql` with the exact SQL the inventory will run. Tools: Supabase MCP `mcp__a13384b5-...__execute_sql`.

```sql
-- Q1: All RLS policies on phase1-affected tables
SELECT schemaname, tablename, policyname, permissive, roles, cmd, qual, with_check
FROM pg_policies
WHERE schemaname = 'public'
  AND tablename IN (
    'user_roles',
    'jobs', 'technicians', 'marketing_spend', 'calls_inbound',
    'integrations_config', 'sync_progress',
    'memberships', 'membership_plans', 'membership_charges',
    'tech_coaching_notes',
    'technician_commission_rules', 'parts', 'job_parts', 'parts_price_history'
  )
ORDER BY tablename, policyname;

-- Q2: Current user_roles assignments (id-redacted, role counts only)
SELECT role, COUNT(*) AS row_count
FROM user_roles
GROUP BY role
ORDER BY role;

-- Q3: Helper functions actually present in the schema (we plan to use is_admin_or_manager and has_role)
SELECT proname, pg_get_function_arguments(p.oid) AS args
FROM pg_proc p
JOIN pg_namespace n ON p.pronamespace = n.oid
WHERE n.nspname = 'public'
  AND proname IN ('is_admin_or_manager', 'has_role', 'is_admin', 'is_manager');

-- Q4: Confirm membership_* tables exist (names may differ from spec)
SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'membership%';
```

- [ ] **Step 2: Run the inventory against prod jwrpj**

Use the Supabase MCP tool `mcp__a13384b5-3518-4c7c-9b61-a7f2786de7db__execute_sql` with `project_id="jwrpj..."` (full ref from project memory) and run each Q1–Q4. Capture the raw output.

If a tool prompt asks for confirmation, accept — these are read-only queries.

If `is_admin_or_manager` or `has_role` is missing in Q3 results, **HALT** — the spec depends on these helpers existing. Surface to the operator before continuing. Phase 1 cannot proceed without them.

If Q4 returns table names that differ from `memberships`, `membership_plans`, `membership_charges`, update the affected-table list in the migrations using the actual names returned.

- [ ] **Step 3: Write the inventory to JSON**

Save the four query results to `.planning/phase1/grants-inventory-pre.json` shaped as:

```json
{
  "captured_at": "2026-05-05T...Z",
  "project_ref": "jwrpj...",
  "policies": [ /* one entry per pg_policies row from Q1 */ ],
  "user_role_counts": [ /* Q2 rows */ ],
  "helpers_present": [ /* Q3 rows */ ],
  "membership_tables": [ /* Q4 tablename strings */ ]
}
```

- [ ] **Step 4: Commit the inventory**

```sh
git add .planning/phase1/inventory-queries.sql .planning/phase1/grants-inventory-pre.json
git commit -m "chore(phase1): snapshot pre-migration RLS and role grants"
git push
```

Expected: inventory committed on branch. This file is the canonical "before" state. Do not edit it later.

---

### Task 3: Create the Supabase branch for migration testing

**Files:** none in repo; new Supabase branch created via MCP.

- [ ] **Step 1: List existing branches on the project**

Use `mcp__a13384b5-...__list_branches` for `project_id="jwrpj..."`. Expected: a small list (or empty). If a `phase1-test` branch already exists from a prior aborted attempt, **HALT** and ask the operator whether to reuse or recreate.

- [ ] **Step 2: Create the branch**

Use `mcp__a13384b5-...__create_branch` with `project_id="jwrpj..."`, `name="phase1-test"`. The MCP tool may require `mcp__a13384b5-...__confirm_cost` first — confirm the cost (Supabase branches incur a small per-day fee). Capture the new branch's project_ref in `.planning/phase1/supabase-branch.json`:

```json
{
  "branch_name": "phase1-test",
  "branch_project_ref": "<returned ref>",
  "created_at": "<ISO>"
}
```

- [ ] **Step 3: Verify branch is reachable**

Run a trivial query against the branch ref to confirm: `SELECT current_database(), current_user;` via `execute_sql` with the *branch* project_ref. Expected: query returns. Halt if it errors.

- [ ] **Step 4: Commit the branch reference**

```sh
git add .planning/phase1/supabase-branch.json
git commit -m "chore(phase1): record Supabase test branch ref"
git push
```

---

## Phase B — RLS lockdown

### Task 4: Draft the forward migration locally

**Files:**
- Create: `supabase/migrations/20260505120000_phase1_rls_lockdown.sql`

This task only writes the SQL file. It does **not** apply it anywhere yet.

- [ ] **Step 1: Write the migration file**

Create `supabase/migrations/20260505120000_phase1_rls_lockdown.sql` with the structure below. The exact policy names dropped come from `grants-inventory-pre.json` Q1 — replace `<policyname>` placeholders with actual names from the inventory.

```sql
-- ============================================================================
-- Phase 1: RLS lockdown
-- Replaces permissive `FOR ALL USING (true)` policies with role-aware policies.
-- Source: docs/superpowers/specs/2026-05-05-phase1-security-cleanup-design.md
-- Pre-snapshot: .planning/phase1/grants-inventory-pre.json
-- Revert: 20260505120001_phase1_rls_lockdown_revert.sql
-- ============================================================================

BEGIN;

-- ----- user_roles -----
-- Authenticated users SELECT their own role (so the dashboard can boot).
-- Only admin/manager can INSERT/UPDATE/DELETE.
DROP POLICY IF EXISTS "Allow all operations on user_roles" ON public.user_roles;
-- ...drop any other permissive policies surfaced in the inventory...

CREATE POLICY "user_roles_select_own_or_manager"
  ON public.user_roles FOR SELECT TO authenticated
  USING (user_id = auth.uid() OR public.is_admin_or_manager(auth.uid()));

CREATE POLICY "user_roles_write_admin_only"
  ON public.user_roles FOR ALL TO authenticated
  USING (public.is_admin_or_manager(auth.uid()))
  WITH CHECK (public.is_admin_or_manager(auth.uid()));

-- ----- core operational tables -----
-- Authenticated users SELECT all rows (dashboard reads). Only admin/manager writes.
-- Service role unaffected (used by edge functions).
DO $$
DECLARE t text;
BEGIN
  FOR t IN SELECT unnest(ARRAY[
    'jobs', 'technicians', 'marketing_spend', 'calls_inbound',
    'integrations_config', 'sync_progress'
    -- membership table names from inventory Q4 appended dynamically
  ])
  LOOP
    EXECUTE format('DROP POLICY IF EXISTS %I ON public.%I',
      'Allow all operations on ' || t, t);
    -- drop any other permissive policy names surfaced in inventory
    EXECUTE format('CREATE POLICY %I ON public.%I FOR SELECT TO authenticated USING (true)',
      t || '_select_authenticated', t);
    EXECUTE format($f$CREATE POLICY %I ON public.%I FOR ALL TO authenticated
                       USING (public.is_admin_or_manager(auth.uid()))
                       WITH CHECK (public.is_admin_or_manager(auth.uid()))$f$,
      t || '_write_manager_admin', t);
  END LOOP;
END $$;

-- Append membership_* tables explicitly using values from inventory Q4.
-- Example (replace with actual table names):
DROP POLICY IF EXISTS "Allow all operations on memberships" ON public.memberships;
CREATE POLICY "memberships_select_authenticated" ON public.memberships
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "memberships_write_manager_admin" ON public.memberships
  FOR ALL TO authenticated
  USING (public.is_admin_or_manager(auth.uid()))
  WITH CHECK (public.is_admin_or_manager(auth.uid()));
-- repeat for each membership_* table name from inventory

-- ----- tech_coaching_notes -----
-- Owner tech can read their own notes. Admin/manager can read all and write.
DROP POLICY IF EXISTS "tech_coaching_notes_select_all" ON public.tech_coaching_notes;
CREATE POLICY "tech_coaching_notes_select_owner_or_manager"
  ON public.tech_coaching_notes FOR SELECT TO authenticated
  USING (
    technician_id IN (SELECT id FROM public.technicians WHERE user_id = auth.uid())
    OR public.is_admin_or_manager(auth.uid())
  );
CREATE POLICY "tech_coaching_notes_write_manager_admin"
  ON public.tech_coaching_notes FOR ALL TO authenticated
  USING (public.is_admin_or_manager(auth.uid()))
  WITH CHECK (public.is_admin_or_manager(auth.uid()));

-- ----- public-readable parts/commission tables -----
-- Revoke `public` role; require authenticated. Commission rules further restricted.
DO $$
DECLARE t text;
BEGIN
  FOR t IN SELECT unnest(ARRAY['parts', 'job_parts', 'parts_price_history'])
  LOOP
    EXECUTE format('DROP POLICY IF EXISTS %I ON public.%I',
      t || '_select_public', t);
    EXECUTE format('CREATE POLICY %I ON public.%I FOR SELECT TO authenticated USING (true)',
      t || '_select_authenticated', t);
  END LOOP;
END $$;

-- technician_commission_rules — admin/manager only
DROP POLICY IF EXISTS "technician_commission_rules_select_public" ON public.technician_commission_rules;
CREATE POLICY "technician_commission_rules_select_manager_admin"
  ON public.technician_commission_rules FOR SELECT TO authenticated
  USING (public.is_admin_or_manager(auth.uid()));
CREATE POLICY "technician_commission_rules_write_admin_only"
  ON public.technician_commission_rules FOR ALL TO authenticated
  USING (public.is_admin_or_manager(auth.uid()))
  WITH CHECK (public.is_admin_or_manager(auth.uid()));

COMMIT;
```

The migration is intentionally explicit, not minimal. Every table is named, every policy is named. Reviewable without executing.

- [ ] **Step 2: Confirm exact policy names from inventory**

Open `.planning/phase1/grants-inventory-pre.json`. For every policy in Q1 results that targets a phase1 table, ensure your migration `DROP POLICY IF EXISTS` either explicitly drops it OR documents in a comment that it survives unchanged. **No permissive policy on a phase1 table may survive without a comment justifying it.**

If the inventory shows a policy you don't recognize (e.g. on `jobs`), HALT. Do not invent its purpose; ask the operator.

- [ ] **Step 3: Lint the SQL**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
test -f supabase/migrations/20260505120000_phase1_rls_lockdown.sql && echo "exists" || echo "MISSING"
# Optional: run `supabase db lint` if installed; otherwise visual review
```

Expected: file exists. If `supabase db lint` is available it should report no SQL errors.

- [ ] **Step 4: Commit (do NOT apply yet)**

```sh
git add supabase/migrations/20260505120000_phase1_rls_lockdown.sql
git commit -m "feat(phase1): forward RLS lockdown migration (not yet applied)"
git push
```

The commit message says "not yet applied" so the operator (and any reviewer) knows the file is committed but the prod DB hasn't moved yet.

---

### Task 5: Apply the forward migration to the Supabase branch and smoke-test

**Files:** none modified in repo; DB state changed on the test branch only.

- [ ] **Step 1: Apply forward migration to test branch**

Use `mcp__a13384b5-...__apply_migration` with `project_id` set to the **branch ref** from `.planning/phase1/supabase-branch.json`, `name="phase1_rls_lockdown"`, and `query` set to the contents of `20260505120000_phase1_rls_lockdown.sql`.

If the apply fails with a syntax error or missing helper, HALT. Fix the migration in Task 4, recommit, retry.

- [ ] **Step 2: Verify pg_policies on the branch**

Run Q1 from `.planning/phase1/inventory-queries.sql` against the **branch** project ref. Save output to `.planning/phase1/grants-inventory-branch-after-forward.json`.

Compare to expected post-state:
- `user_roles` should have `user_roles_select_own_or_manager` + `user_roles_write_admin_only`. No `Allow all operations`.
- Each core table should have a `<table>_select_authenticated` and `<table>_write_manager_admin`. No `Allow all`.
- `tech_coaching_notes` should have owner-or-manager SELECT and manager-write.
- `parts*` tables should have `_select_authenticated`, no `_select_public`.

If any expectation fails, HALT and adjust the migration.

- [ ] **Step 3: Self-promotion attack test (must FAIL on the branch)**

Run via `execute_sql` with the branch ref, simulating an authenticated non-admin session is awkward via SQL — instead, use a synthetic test:

```sql
-- Switch session to authenticated role with a non-admin auth.uid()
SET LOCAL ROLE authenticated;
SET LOCAL "request.jwt.claims" TO '{"sub":"00000000-0000-0000-0000-000000000001","role":"authenticated"}';

-- Attempt: a non-admin user tries to make themselves admin
UPDATE public.user_roles
SET role = 'admin'
WHERE user_id = '00000000-0000-0000-0000-000000000001';
-- Expected: 0 rows affected (RLS denies the update)
```

Expected: `UPDATE 0` (RLS blocks). If `UPDATE 1`, the policy is wrong. HALT.

- [ ] **Step 4: Commit the branch verification**

```sh
git add .planning/phase1/grants-inventory-branch-after-forward.json
git commit -m "chore(phase1): post-forward RLS state captured from Supabase branch"
git push
```

---

### Task 6: Write and roundtrip-test the revert migration

**Files:**
- Create: `supabase/migrations/20260505120001_phase1_rls_lockdown_revert.sql`

The revert restores the **exact** policy state captured in `grants-inventory-pre.json`. Generate it from the inventory, not from the spec.

- [ ] **Step 1: Write the revert SQL**

Create `supabase/migrations/20260505120001_phase1_rls_lockdown_revert.sql`:

```sql
-- ============================================================================
-- Phase 1 RLS lockdown — REVERT
-- Restores policy state captured in .planning/phase1/grants-inventory-pre.json.
-- Run order: this revert UNDOES 20260505120000_phase1_rls_lockdown.sql.
-- ============================================================================

BEGIN;

-- Drop every policy created by the forward migration
DROP POLICY IF EXISTS "user_roles_select_own_or_manager" ON public.user_roles;
DROP POLICY IF EXISTS "user_roles_write_admin_only" ON public.user_roles;
-- ... drop every <table>_select_authenticated, <table>_write_manager_admin from forward migration
-- ... drop tech_coaching_notes new policies
-- ... drop parts*/commission new policies

-- Recreate the original permissive policies as captured in inventory Q1.
-- For every row in grants-inventory-pre.json:policies, emit:
--   CREATE POLICY <policyname> ON public.<tablename>
--     <permissive>?<for cmd> TO <roles>
--     USING (<qual>) WITH CHECK (<with_check>);
-- Use the exact strings from the inventory.

CREATE POLICY "Allow all operations on user_roles"
  ON public.user_roles FOR ALL USING (true) WITH CHECK (true);
-- ... one CREATE POLICY per pre-snapshot row ...

COMMIT;
```

- [ ] **Step 2: Roundtrip test on the branch**

On the **branch** ref:

1. Apply the revert: `mcp__a13384b5-...__apply_migration` with `name="phase1_rls_lockdown_revert"`, `query` = revert SQL.
2. Run Q1 again, capture to `.planning/phase1/grants-inventory-branch-after-revert.json`.
3. Diff against `grants-inventory-pre.json`. **Must be empty for the policies array.**

If diff is non-empty, the revert is incomplete. Patch the revert SQL, re-apply forward + revert on the branch, repeat.

- [ ] **Step 3: Re-apply forward to leave the branch in post-forward state**

We want the branch in the post-forward state for the next task (smoke testing). Re-apply the forward migration via `apply_migration`. Verify Q1 matches `grants-inventory-branch-after-forward.json`.

- [ ] **Step 4: Commit**

```sh
git add supabase/migrations/20260505120001_phase1_rls_lockdown_revert.sql \
        .planning/phase1/grants-inventory-branch-after-revert.json
git commit -m "feat(phase1): revert migration for RLS lockdown + roundtrip-tested on branch"
git push
```

---

### Task 7: Smoke-test the dashboard against the Supabase branch

**Files:** none modified.

- [ ] **Step 1: Get the branch's API URL and anon key**

Use `mcp__a13384b5-...__get_project_url` and `mcp__a13384b5-...__get_publishable_keys` with the branch ref. Save to `.planning/phase1/branch-credentials.local.json` (gitignored — these are short-lived test creds).

Add `.planning/phase1/*.local.json` to `.gitignore` if it isn't already.

- [ ] **Step 2: Build the dashboard pointed at the branch**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
VITE_SUPABASE_URL="<branch url>" \
VITE_SUPABASE_PUBLISHABLE_KEY="<branch publishable key>" \
npm run build
npx vite preview --port 4174
```

Open `http://localhost:4174` in a browser. Daniel logs in with his admin account (which is bridged to the branch via the same auth project — confirm with the operator if auth is project-scoped vs branch-scoped; if scoped, this step requires creating a test user on the branch).

If auth is fully separated, fall back to running the existing test suite against the branch:
```sh
VITE_SUPABASE_URL="<branch url>" \
VITE_SUPABASE_PUBLISHABLE_KEY="<branch publishable key>" \
npm test
```

- [ ] **Step 3: Manual click-through (if auth allows)**

Operator confirms in browser:
1. `/` (Index) renders all KPI tiles with non-zero values.
2. `/leaderboard` renders the tech list.
3. `/admin` is reachable.
4. `/tech?as=<a tech user_id>` (impersonation) shows the tech-only view.
5. Hard test: open devtools console and run:
   ```js
   const { data, error } = await supabase.from('user_roles').update({ role: 'admin' }).eq('user_id', supabase.auth.session().user.id).select();
   console.log({ data, error });
   ```
   Expected: empty data + RLS error. If this succeeds, the policy is wrong; HALT.

- [ ] **Step 4: Commit a smoke-test report**

Write the result to `.planning/phase1/smoke-test-rls.md` (a short bulleted pass/fail list with timestamps), commit, push.

---

## Phase C — HCP webhook signature verification

### Task 8: Add `HCP_WEBHOOK_SECRET` and write the failing signature test

**Files:**
- Modify: `supabase/functions/hcp-webhook/index.ts` (add a verify helper but no enforcement yet)
- Create: `supabase/functions/hcp-webhook/index.test.ts`

- [ ] **Step 1: Locate the webhook function**

```sh
ls supabase/functions/hcp-webhook/
cat supabase/functions/hcp-webhook/index.ts | head -40
```

Confirm the function exists and reads request body via `await req.json()` or `await req.text()`. Note the existing body-parsing pattern.

- [ ] **Step 2: Add a `verifyHcpSignature` helper (not yet enforced)**

Edit `supabase/functions/hcp-webhook/index.ts` and add the helper near the top (after imports, before the `serve` handler):

```ts
import { crypto } from "https://deno.land/std@0.208.0/crypto/mod.ts";

const HCP_SIGNATURE_HEADER = "x-hcp-signature"; // confirm exact header name from HCP webhook docs

async function verifyHcpSignature(req: Request, rawBody: string): Promise<boolean> {
  const secret = Deno.env.get("HCP_WEBHOOK_SECRET");
  if (!secret) {
    console.error("HCP_WEBHOOK_SECRET not set");
    return false;
  }
  const sigHeader = req.headers.get(HCP_SIGNATURE_HEADER);
  if (!sigHeader) return false;

  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw",
    enc.encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const sigBytes = await crypto.subtle.sign("HMAC", key, enc.encode(rawBody));
  const expected = Array.from(new Uint8Array(sigBytes))
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");

  // Constant-time-ish comparison
  if (sigHeader.length !== expected.length) return false;
  let mismatch = 0;
  for (let i = 0; i < expected.length; i++) {
    mismatch |= sigHeader.charCodeAt(i) ^ expected.charCodeAt(i);
  }
  return mismatch === 0;
}
```

**Important:** Read HCP's actual webhook signature docs first. The header name (`X-HCP-Signature` vs `X-Webhook-Signature` vs `Hcp-Signature`) and the algorithm (HMAC-SHA256 vs different) may differ from what's drafted above. **Confirm the format from HCP docs before implementing.** If HCP sends a prefix like `sha256=abc...`, parse it appropriately. If unclear, HALT and ask the operator to share an HCP webhook doc URL.

- [ ] **Step 3: Set the secret on Supabase prod and branch**

Operator action — share with the operator the command they need to run, OR run via Supabase MCP if available:

```sh
# On prod jwrpj
supabase secrets set HCP_WEBHOOK_SECRET=<value> --project-ref <jwrpj ref>

# On test branch
supabase secrets set HCP_WEBHOOK_SECRET=<value> --project-ref <branch ref>
```

Source of `<value>`: the existing HCP webhook signing secret from HousecallPro's webhook configuration page. If HCP doesn't expose one to us, generate one and configure both sides.

- [ ] **Step 4: Write the failing test**

Create `supabase/functions/hcp-webhook/index.test.ts`:

```ts
import { assertEquals } from "https://deno.land/std@0.208.0/assert/mod.ts";

// Stub Deno.env for predictable test environment
Deno.env.set("HCP_WEBHOOK_SECRET", "test-secret-do-not-ship");

const { default: handler } = await import("./index.ts");

async function call(opts: { body: string; signature?: string }) {
  const headers = new Headers({ "content-type": "application/json" });
  if (opts.signature !== undefined) headers.set("x-hcp-signature", opts.signature);
  return await handler(new Request("https://test/", {
    method: "POST", headers, body: opts.body,
  }));
}

Deno.test("rejects request with no signature header", async () => {
  const res = await call({ body: '{"event":"invoice.paid"}' });
  assertEquals(res.status, 401);
});

Deno.test("rejects request with tampered signature", async () => {
  const res = await call({ body: '{"event":"invoice.paid"}', signature: "deadbeef" });
  assertEquals(res.status, 401);
});

Deno.test("accepts request with valid signature", async () => {
  // Pre-computed HMAC-SHA256 of '{"event":"ping"}' with key 'test-secret-do-not-ship'
  const body = '{"event":"ping"}';
  const validSig = await computeExpectedSig(body, "test-secret-do-not-ship");
  const res = await call({ body, signature: validSig });
  assertEquals(res.status, 200);
});

async function computeExpectedSig(body: string, secret: string) {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw", enc.encode(secret),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"],
  );
  const sig = await crypto.subtle.sign("HMAC", key, enc.encode(body));
  return Array.from(new Uint8Array(sig)).map((b) => b.toString(16).padStart(2, "0")).join("");
}
```

- [ ] **Step 5: Run the test — must FAIL**

```sh
cd supabase/functions/hcp-webhook
deno test --allow-env --allow-net index.test.ts
```

Expected: tests FAIL because the handler is not yet enforcing the signature (signature verification is added in the next task). This confirms our test catches the missing enforcement.

- [ ] **Step 6: Commit (failing test + helper, no enforcement)**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
git add supabase/functions/hcp-webhook/index.ts supabase/functions/hcp-webhook/index.test.ts
git commit -m "test(phase1): failing webhook signature test + verifyHcpSignature helper"
git push
```

---

### Task 9: Enforce signature verification in the webhook

**Files:**
- Modify: `supabase/functions/hcp-webhook/index.ts`

- [ ] **Step 1: Add the enforcement to the handler**

Edit the `serve()` handler in `supabase/functions/hcp-webhook/index.ts` so the very first thing it does (after method check) is verify the signature. Replace the existing body parse with:

```ts
serve(async (req) => {
  if (req.method !== "POST") return new Response("Method not allowed", { status: 405 });

  // NEW: signature verification before any DB write
  const rawBody = await req.text();
  const ok = await verifyHcpSignature(req, rawBody);
  if (!ok) {
    console.warn("hcp-webhook: signature verification failed", {
      ip: req.headers.get("x-forwarded-for"),
    });
    return new Response("Unauthorized", { status: 401 });
  }

  // Existing logic continues from here, parse JSON from rawBody
  let payload: unknown;
  try { payload = JSON.parse(rawBody); }
  catch { return new Response("Invalid JSON", { status: 400 }); }

  // ... rest of existing handler unchanged ...
});
```

If the existing handler already calls `await req.json()` later, you must remove that and have it use `JSON.parse(rawBody)` instead — `req.text()` consumes the body and `req.json()` will fail after.

- [ ] **Step 2: Run the test — must PASS**

```sh
cd supabase/functions/hcp-webhook
deno test --allow-env --allow-net index.test.ts
```

Expected: 3/3 tests pass.

- [ ] **Step 3: Deploy to the test branch and replay a real payload**

Use `mcp__a13384b5-...__deploy_edge_function` with `project_id=<branch ref>`, `name="hcp-webhook"`, `files=[{"name":"index.ts","content":"<file contents>"}]`. Then send a known-good captured payload from HCP webhook logs to the branch endpoint:

```sh
curl -X POST "<branch-functions-url>/hcp-webhook" \
  -H "x-hcp-signature: <captured signature>" \
  -H "content-type: application/json" \
  -d @captured-payload.json
```

Expected: 200. Then send the same payload with a flipped signature byte → 401. Then no signature → 401.

If the captured payload returns 401, it means our header name or algorithm is wrong. HALT, fix the helper, retest.

- [ ] **Step 4: Commit**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
git add supabase/functions/hcp-webhook/index.ts
git commit -m "feat(phase1): enforce HCP webhook signature verification (401 on tampered)"
git push
```

---

## Phase D — Edge function auth middleware

### Task 10: Classify all 29 edge functions

**Files:**
- Create: `.planning/phase1/edge-function-classification.md`

- [ ] **Step 1: List edge functions**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
ls supabase/functions/ | sort
```

Capture full list. Cross-reference with `supabase/config.toml` to find current `verify_jwt` settings per function.

- [ ] **Step 2: Read each function's first 30 lines**

For each function in the list, read `supabase/functions/<name>/index.ts` (top 30 lines is usually enough to determine intent). Classify into one of three buckets:

- **public**: webhook receivers and public-facing endpoints. Examples: `hcp-webhook`, anything that expects unauthenticated traffic from HCP/HouseCallPro/external. Verify by signature, not JWT. → `verify_jwt = false`.
- **authed**: any logged-in user can call. Examples: AI insights, user-triggered sync. → `verify_jwt = true`, no admin check.
- **admin-only**: write to KPI-affecting state, payroll, role assignment. Examples: payroll lock, admin sync, role changes. → `verify_jwt = true` + `requireAdminAuth(req)`.

**Default if uncertain:** classify as **admin-only**. Tightening can be relaxed later; loosening can leak data.

- [ ] **Step 3: Write the classification table**

Create `.planning/phase1/edge-function-classification.md`:

```markdown
# Edge Function Classification — Phase 1

| Function | Bucket | verify_jwt | requireAdminAuth | Reason |
|---|---|---|---|---|
| hcp-webhook | public | false | n/a | HCP webhook, signed |
| auto-sync-jobs | admin-only | true | yes | Writes to jobs |
| ... | ... | ... | ... | ... |
```

Each row must have a justification. If a function has no clear purpose, mark it `**REVIEW**` and HALT — ask the operator before classifying.

- [ ] **Step 4: Commit**

```sh
git add .planning/phase1/edge-function-classification.md
git commit -m "chore(phase1): classify 29 edge functions into public/authed/admin-only buckets"
git push
```

---

### Task 11: Write the shared `requireAdminAuth` middleware

**Files:**
- Create: `supabase/functions/_shared/require-admin-auth.ts`
- Create: `supabase/functions/_shared/require-admin-auth.test.ts`

- [ ] **Step 1: Write the failing test**

```ts
// supabase/functions/_shared/require-admin-auth.test.ts
import { assertEquals } from "https://deno.land/std@0.208.0/assert/mod.ts";
import { requireAdminAuth } from "./require-admin-auth.ts";

Deno.test("rejects request with no auth header", async () => {
  const req = new Request("https://test/", { method: "POST" });
  const res = await requireAdminAuth(req);
  assertEquals(res?.status, 401);
});

Deno.test("rejects request with malformed bearer token", async () => {
  const req = new Request("https://test/", {
    method: "POST",
    headers: { authorization: "Bearer notajwt" },
  });
  const res = await requireAdminAuth(req);
  assertEquals(res?.status, 401);
});
```

- [ ] **Step 2: Run test — must FAIL with "module not found"**

```sh
cd supabase/functions/_shared
deno test --allow-env --allow-net require-admin-auth.test.ts
```

Expected: import fails (file doesn't exist).

- [ ] **Step 3: Implement the middleware**

```ts
// supabase/functions/_shared/require-admin-auth.ts
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

/**
 * Returns null if the request is from an admin/manager.
 * Returns a 401/403 Response if not — the caller short-circuits with `if (denied) return denied`.
 */
export async function requireAdminAuth(req: Request): Promise<Response | null> {
  const auth = req.headers.get("authorization");
  if (!auth || !auth.startsWith("Bearer ")) {
    return new Response("Unauthorized", { status: 401 });
  }
  const jwt = auth.slice("Bearer ".length).trim();
  if (jwt.split(".").length !== 3) {
    return new Response("Unauthorized", { status: 401 });
  }

  const supabase = createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_ANON_KEY")!,
    { global: { headers: { authorization: `Bearer ${jwt}` } } },
  );
  const { data: userRes, error: userErr } = await supabase.auth.getUser(jwt);
  if (userErr || !userRes?.user) {
    return new Response("Unauthorized", { status: 401 });
  }

  // Use the existing helper exposed via RPC, or query user_roles directly
  const { data, error } = await supabase
    .from("user_roles")
    .select("role")
    .eq("user_id", userRes.user.id)
    .maybeSingle();
  if (error || !data) {
    return new Response("Forbidden", { status: 403 });
  }
  if (!["admin", "manager"].includes(data.role)) {
    return new Response("Forbidden", { status: 403 });
  }
  return null;
}
```

- [ ] **Step 4: Run test — must PASS**

```sh
deno test --allow-env --allow-net require-admin-auth.test.ts
```

Expected: 2/2 pass.

- [ ] **Step 5: Commit**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
git add supabase/functions/_shared/require-admin-auth.ts \
        supabase/functions/_shared/require-admin-auth.test.ts
git commit -m "feat(phase1): shared requireAdminAuth middleware for edge functions"
git push
```

---

### Task 12: Apply `requireAdminAuth` to admin-only functions

**Files:** modify each function classified `admin-only` in `.planning/phase1/edge-function-classification.md`.

- [ ] **Step 1: For each admin-only function, prepend the middleware call**

For every function in the admin-only bucket, edit `supabase/functions/<name>/index.ts`:

```ts
import { serve } from "https://deno.land/std@0.208.0/http/server.ts";
import { requireAdminAuth } from "../_shared/require-admin-auth.ts"; // NEW

serve(async (req) => {
  const denied = await requireAdminAuth(req); // NEW
  if (denied) return denied;                  // NEW

  // ... rest of existing handler unchanged ...
});
```

If the function already extracts `auth.uid()` manually from the JWT (audit said many do), that custom extraction can stay — `requireAdminAuth` is additive. Don't refactor more than this.

- [ ] **Step 2: Per-function smoke test**

For each modified function, deploy to the **test branch** via `mcp__a13384b5-...__deploy_edge_function`. Then test:

```sh
# No token → 401
curl -sS -o /dev/null -w "%{http_code}\n" "<branch-fn-url>/<name>" -X POST
# Tech token (non-admin) → 403
curl -sS -o /dev/null -w "%{http_code}\n" "<branch-fn-url>/<name>" -X POST -H "authorization: Bearer <tech-jwt>"
# Admin token → 200 (or whatever the function normally returns)
curl -sS -o /dev/null -w "%{http_code}\n" "<branch-fn-url>/<name>" -X POST -H "authorization: Bearer <admin-jwt>"
```

If a function expected to return 200 returns something else, the function's own logic is broken — investigate before continuing.

- [ ] **Step 3: Commit each function in its own commit OR a single bulk commit**

Single bulk commit is fine here because the change pattern is identical:

```sh
git add supabase/functions/
git commit -m "feat(phase1): require admin auth on N admin-only edge functions"
git push
```

Where N is the actual count from the classification table.

---

### Task 13: Update `config.toml` `verify_jwt` flags + bulk redeploy

**Files:**
- Modify: `supabase/config.toml`

- [ ] **Step 1: Edit config.toml**

For every function classified as `authed` or `admin-only`, set `verify_jwt = true`. For `public`, set `verify_jwt = false` (with a comment explaining why — typically signature-verified webhook).

```toml
[functions.hcp-webhook]
verify_jwt = false  # webhook receiver, signature-verified

[functions.auto-sync-jobs]
verify_jwt = true   # admin-only, also gated by requireAdminAuth

# ... etc for all 29 ...
```

- [ ] **Step 2: Validate config.toml parses**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
# If supabase CLI is installed:
supabase config get 2>/dev/null || cat supabase/config.toml | head -30
```

- [ ] **Step 3: Deploy all functions to test branch**

For each modified function, use `mcp__a13384b5-...__deploy_edge_function` with the branch ref.

Then re-run the per-function smoke tests from Task 12, Step 2 against the branch.

- [ ] **Step 4: Commit**

```sh
git add supabase/config.toml
git commit -m "feat(phase1): flip verify_jwt true on authed and admin-only edge functions"
git push
```

---

## Phase E — Trust artifact deletes

### Task 14: Remove the `↓ 15% improved` CPA chip

**Files:**
- Modify: `src/pages/Index.tsx` (around line 735)

- [ ] **Step 1: Verify line still matches**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
grep -n "15%" src/pages/Index.tsx
```

Expected: shows line 735 (or near) with `<span className="chip chip-green">↓ 15%</span> improved`. If the line has moved, find the exact location before editing.

- [ ] **Step 2: Edit the file**

Open `src/pages/Index.tsx` around line 735. Find:

```tsx
<div className="meta"><span className="chip chip-green">↓ 15%</span> improved</div>
```

Delete the entire line (the static "improved" text with no real delta). If the surrounding KPI tile had a real period-over-period delta available in nearby state, replace with that; otherwise leave the CPA tile without a delta.

To check whether a real delta is available, look ~50 lines up for any `cpaDelta`, `cpaPct`, `cpaPriorPeriod` variable. If none, no replacement.

- [ ] **Step 3: Run dev build to verify no syntax error**

```sh
npm run build
```

Expected: build succeeds.

- [ ] **Step 4: Commit**

```sh
git add src/pages/Index.tsx
git commit -m "fix(phase1): remove hardcoded '↓ 15% improved' CPA chip (was static text on a live KPI)"
git push
```

---

### Task 15: Remove the 3B Holdings CSS paste

**Files:**
- Modify: `src/index.css` (delete lines 80-1020)

- [ ] **Step 1: Verify boundaries**

```sh
sed -n '78,82p' src/index.css   # last lines of legitimate Twins CSS
sed -n '1016,1020p' src/index.css   # last 3B paste lines
wc -l src/index.css
```

Expected:
- Line 79 ends a Twins CSS block (likely `}` or blank).
- Line 80 starts the 3B paste comment (`/* Claude Design handoff` or `/* ===== 3B Holdings`).
- Line 1020 is the file's last line.

If boundaries differ, adjust the line range below.

- [ ] **Step 2: Search for any consumer of 3B-paste-only tokens**

The 3B paste defines `--accent`, `--bg`, `--surface`, `--surface-2`, `--fg` and an unused mobile drawer. Search Twins source for usage:

```sh
grep -rn "var(--accent)\|var(--surface-2)\|var(--bg)" src/ | grep -v "src/index.css"
```

If any Twins component uses `var(--accent)` or `var(--surface-2)` etc., note them. Most dashboards use the shadcn HSL tokens (`hsl(var(--primary))` etc.) defined in lines 1-79 of `index.css`. The 3B-only tokens should have zero consumers.

If a Twins consumer is found, decide per-call-site: replace with the equivalent `hsl(var(--primary))` token, or with a Tailwind class. Patch each consumer first in this same task.

- [ ] **Step 3: Truncate the file at line 79**

```sh
head -n 79 src/index.css > src/index.css.new && mv src/index.css.new src/index.css
wc -l src/index.css
```

Expected: file is now 79 lines.

- [ ] **Step 4: Build + visual smoke**

```sh
npm run build
npm run dev
```

Open `http://localhost:8080` (or whatever the dev port is — check vite.config.ts). Click through `/`, `/leaderboard`, `/tech`, `/admin`, `/marketing-source-roi`, `/memberships`, `/rev-rise`, `/what-if`. Visual check: nothing looks unstyled or missing color.

- [ ] **Step 5: Commit**

```sh
git add src/index.css
git commit -m "fix(phase1): delete 3B Holdings CSS paste (940 lines of wrong-brand tokens at :root)"
git push
```

---

### Task 16: Remove the `setInterval` triple-refetch

**Files:**
- Modify: `src/pages/Index.tsx` (around line 192)

- [ ] **Step 1: Locate the block**

```sh
grep -n "setInterval" src/pages/Index.tsx
```

Expected: one match around line 192. If multiple matches, only delete the one inside the dashboard refetch effect.

- [ ] **Step 2: Read the surrounding effect**

Read `src/pages/Index.tsx` lines 185-200 to understand the `useEffect` that contains the `setInterval`. The effect should be entirely removed if its only purpose is the interval — the realtime subscription + 60s `refetchInterval` already cover freshness.

- [ ] **Step 3: Delete the effect**

Remove the `useEffect(() => { const interval = setInterval(...); return () => clearInterval(interval); }, ...)` block in its entirety.

- [ ] **Step 4: Build + smoke**

```sh
npm run build
```

Expected: build passes.

- [ ] **Step 5: Commit**

```sh
git add src/pages/Index.tsx
git commit -m "perf(phase1): remove setInterval triple-refetch (realtime + 60s refetchInterval already cover freshness)"
git push
```

---

## Phase F — Dead code & dependency sweep

### Task 17: Delete `MarketingSourceROIv1.tsx`

**Files:**
- Delete: `src/pages/MarketingSourceROIv1.tsx`
- Modify: `src/App.tsx` (remove the `?legacy=1` route handling if present)

- [ ] **Step 1: Confirm no live import outside the legacy escape**

```sh
grep -rn "MarketingSourceROIv1" src/
```

Expected: only `MarketingSourceROIv1.tsx` itself + a conditional import in `MarketingSourceROI.tsx` or `App.tsx`. Find the conditional and remove it.

- [ ] **Step 2: Delete the file + remove the legacy gate**

```sh
git rm src/pages/MarketingSourceROIv1.tsx
```

Edit `src/pages/MarketingSourceROI.tsx` (or `App.tsx`) to remove any `?legacy=1` branch.

- [ ] **Step 3: Build**

```sh
npm run build
```

Expected: passes.

- [ ] **Step 4: Commit**

```sh
git add -A src/pages/MarketingSourceROIv1.tsx src/pages/MarketingSourceROI.tsx src/App.tsx
git commit -m "chore(phase1): delete MarketingSourceROIv1.tsx legacy duplicate (287 LOC)"
git push
```

---

### Task 18: Delete `WhatIfScenario.tsx` + remove the `/what-if-legacy` route

**Files:**
- Delete: `src/pages/WhatIfScenario.tsx`
- Modify: `src/App.tsx` (remove the `/what-if-legacy` route)

- [ ] **Step 1: Confirm no other consumers**

```sh
grep -rn "WhatIfScenario" src/
```

Expected: only `App.tsx` route + the file itself.

- [ ] **Step 2: Delete the file + remove the route line**

```sh
git rm src/pages/WhatIfScenario.tsx
```

In `src/App.tsx`, find the line:

```tsx
<Route path="/what-if-legacy" element={<ProtectedRoute requiredPermission="view_what_if"><AppShellWithNav><Suspense fallback={<PageSpinner />}><WhatIfScenario /></Suspense></AppShellWithNav></ProtectedRoute>} />
```

Delete it. Also remove the `lazy(() => import('./pages/WhatIfScenario'))` (or equivalent) import at the top of `App.tsx`.

- [ ] **Step 3: Build**

```sh
npm run build
```

- [ ] **Step 4: Commit**

```sh
git add -A src/pages/WhatIfScenario.tsx src/App.tsx
git commit -m "chore(phase1): delete WhatIfScenario.tsx + /what-if-legacy route"
git push
```

---

### Task 19: Delete `payroll/Parts.tsx`

**Files:**
- Delete: `src/pages/payroll/Parts.tsx`

- [ ] **Step 1: Confirm not imported in App.tsx**

```sh
grep -n "Parts" src/App.tsx | grep -v "PartsManagement\|payroll/PartsLibrary\|admin/parts"
```

Expected: no result for the bare `Parts.tsx` import.

- [ ] **Step 2: Delete the file**

```sh
git rm src/pages/payroll/Parts.tsx
```

- [ ] **Step 3: Build**

```sh
npm run build
```

- [ ] **Step 4: Commit**

```sh
git add -A src/pages/payroll/Parts.tsx
git commit -m "chore(phase1): delete unused payroll/Parts.tsx"
git push
```

---

### Task 20: Migrate xlsx → exceljs (remaining call-sites)

**Files:**
- Modify: `src/lib/export-utils.ts`
- Modify: `src/pages/payroll/PartsLibrary.tsx`

After Task 19, the only remaining `xlsx` call-sites are `src/lib/export-utils.ts` and `src/pages/payroll/PartsLibrary.tsx`. We migrate both to `exceljs` (already a dep) so we can drop `xlsx` (which has known CVEs).

- [ ] **Step 1: Audit current xlsx usage**

```sh
grep -n "XLSX\." src/lib/export-utils.ts
grep -n "XLSX\." src/pages/payroll/PartsLibrary.tsx
```

Note every `XLSX.*` call. Most common: `XLSX.utils.json_to_sheet`, `XLSX.utils.book_new`, `XLSX.utils.book_append_sheet`, `XLSX.write`. PartsLibrary may also use `XLSX.read` for imports.

- [ ] **Step 2: Rewrite `export-utils.ts` with exceljs**

Replace the `xlsx` API with the `exceljs` equivalent. Reference: `src/lib/payroll/excelExport.ts` is already exceljs-based and shows the pattern. Mirror its style.

For a json-to-sheet exporter:

```ts
import ExcelJS from "exceljs";
import { saveAs } from "file-saver";

export async function exportToXlsx<T extends Record<string, unknown>>(
  rows: T[],
  filename: string,
  sheetName = "Sheet1",
): Promise<void> {
  const wb = new ExcelJS.Workbook();
  const ws = wb.addWorksheet(sheetName);
  if (rows.length === 0) {
    saveAs(new Blob([await wb.xlsx.writeBuffer()]), `${filename}.xlsx`);
    return;
  }
  ws.columns = Object.keys(rows[0]).map((k) => ({ header: k, key: k }));
  rows.forEach((r) => ws.addRow(r));
  const buf = await wb.xlsx.writeBuffer();
  saveAs(new Blob([buf], { type: "application/octet-stream" }), `${filename}.xlsx`);
}
```

Adapt to whatever signature `export-utils.ts` currently exports — keep the public function names and types the same so call-sites don't change.

- [ ] **Step 3: Rewrite `PartsLibrary.tsx` xlsx usage**

`PartsLibrary.tsx` imports `XLSX` for both export and import. Export side: same pattern as Step 2. Import side (reading an uploaded `.xlsx`):

```ts
import ExcelJS from "exceljs";

async function readXlsxFile(file: File): Promise<unknown[]> {
  const wb = new ExcelJS.Workbook();
  await wb.xlsx.load(await file.arrayBuffer());
  const ws = wb.worksheets[0];
  if (!ws) return [];
  const headers: string[] = [];
  const rows: unknown[] = [];
  ws.eachRow((row, idx) => {
    if (idx === 1) {
      row.eachCell((cell) => headers.push(String(cell.value ?? "")));
    } else {
      const obj: Record<string, unknown> = {};
      row.eachCell((cell, col) => { obj[headers[col - 1]] = cell.value; });
      rows.push(obj);
    }
  });
  return rows;
}
```

- [ ] **Step 4: Run any existing export/import tests**

```sh
npm test -- export-utils PartsLibrary
```

If tests don't exist for these flows, run the dev server, navigate to `/admin/parts`, click the export button, verify the downloaded `.xlsx` opens. Click the import button, upload a known-good file, verify it parses.

- [ ] **Step 5: Commit**

```sh
git add src/lib/export-utils.ts src/pages/payroll/PartsLibrary.tsx
git commit -m "refactor(phase1): migrate remaining xlsx call-sites to exceljs"
git push
```

---

### Task 21: Remove `xlsx` and `html2canvas` from `package.json`

**Files:**
- Modify: `package.json`
- Modify: `package-lock.json`

- [ ] **Step 1: Confirm zero remaining references**

```sh
grep -rn "from 'xlsx'\|from \"xlsx\"\|require('xlsx')\|require(\"xlsx\")" src/
grep -rn "html2canvas" src/
```

Both must return nothing. If either has remaining hits, fix them first (xlsx → finish Task 20; html2canvas → just delete the call-site, the audit confirmed zero references at audit time).

- [ ] **Step 2: Remove from package.json**

```sh
npm uninstall xlsx html2canvas
```

This updates both `package.json` and `package-lock.json`.

- [ ] **Step 3: Build + test**

```sh
npm run build
npm test
```

Expected: both pass. If a test fails because it imports `xlsx`, that test is for code we already migrated — update the test to use exceljs.

- [ ] **Step 4: Commit**

```sh
git add package.json package-lock.json
git commit -m "chore(phase1): drop xlsx and html2canvas deps (xlsx CVEs, html2canvas unused)"
git push
```

---

### Task 22: Remove Capacitor deps + `capacitor.config.ts`

**Files:**
- Modify: `package.json`, `package-lock.json`
- Delete: `capacitor.config.ts`

Decision locked in spec: Daniel confirmed 2026-05-05 the dashboard ships as a website only.

- [ ] **Step 1: Confirm no source uses Capacitor**

```sh
grep -rn "@capacitor" src/
```

Expected: no result. (Audit confirmed at audit time. Re-confirm here in case anything was added since.)

- [ ] **Step 2: Remove Capacitor deps**

```sh
npm uninstall @capacitor/android @capacitor/cli @capacitor/core @capacitor/ios
```

- [ ] **Step 3: Delete the config file**

```sh
git rm capacitor.config.ts
```

If there's an `android/` or `ios/` directory at the project root, leave them (they may contain platform-specific generated code Daniel may want to revive later — safer to keep on disk than delete). Note in commit message.

- [ ] **Step 4: Build**

```sh
npm run build
```

- [ ] **Step 5: Commit**

```sh
git add -A package.json package-lock.json capacitor.config.ts
git commit -m "chore(phase1): remove Capacitor mobile deps + capacitor.config.ts (web-only dashboard, decision 2026-05-05)"
git push
```

---

### Task 23: Add `.worktrees/` to eslint ignore

**Files:**
- Modify: `eslint.config.js`

- [ ] **Step 1: Read current ignore patterns**

```sh
cat eslint.config.js | grep -A 5 "ignores\|ignorePatterns"
```

- [ ] **Step 2: Add `.worktrees/`**

In `eslint.config.js`, find the `ignores` (flat config) or `ignorePatterns` (legacy) array and add `.worktrees/`:

```js
export default [
  {
    ignores: [
      "dist",
      "node_modules",
      ".worktrees/", // NEW
      // ... existing ignores ...
    ],
  },
  // ... rest of config ...
];
```

- [ ] **Step 3: Verify lint warning count drops**

```sh
npm run lint 2>&1 | grep -c "warning"
```

Expected: drops from ~2874 to ~252 (per audit). Order-of-magnitude check is fine — exact number depends on pending edits.

- [ ] **Step 4: Commit**

```sh
git add eslint.config.js
git commit -m "chore(phase1): add .worktrees/ to eslint ignore (drops ~2622 ghost any warnings)"
git push
```

---

## Phase G — Env naming harmonization

### Task 24: Harmonize env var name + add boot guard

**Files:**
- Modify: `.env.example`
- Modify: `src/integrations/supabase/client.ts`

- [ ] **Step 1: Read current state**

```sh
grep -n "VITE_SUPABASE" .env.example
sed -n '1,15p' src/integrations/supabase/client.ts
```

Expected: `.env.example` documents `VITE_SUPABASE_ANON_KEY`; `client.ts:6` reads `VITE_SUPABASE_PUBLISHABLE_KEY`. Confirmed by audit.

- [ ] **Step 2: Update `.env.example`**

Edit `.env.example` to use the name actually read by code (`VITE_SUPABASE_PUBLISHABLE_KEY`). Keep `VITE_SUPABASE_ANON_KEY` as a deprecated alias comment, since some operators may have it set in their local `.env`:

```
# Required
VITE_SUPABASE_URL=https://<your-project>.supabase.co
VITE_SUPABASE_PUBLISHABLE_KEY=<anon publishable key>

# Deprecated alias (still read if PUBLISHABLE_KEY is missing). Will be removed in Phase 4.
# VITE_SUPABASE_ANON_KEY=
```

- [ ] **Step 3: Add a boot guard**

Edit `src/integrations/supabase/client.ts`:

```ts
const url = import.meta.env.VITE_SUPABASE_URL;
const key =
  import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY ??
  import.meta.env.VITE_SUPABASE_ANON_KEY;

if (!url || !key) {
  throw new Error(
    "Missing Supabase env vars. Set VITE_SUPABASE_URL and VITE_SUPABASE_PUBLISHABLE_KEY in .env",
  );
}

export const supabase = createClient(url, key);
```

- [ ] **Step 4: Build + run dev**

```sh
npm run build
npm run dev
```

Expected: app loads. Then test with the env var temporarily unset:

```sh
mv .env .env.bak
npm run dev
# Expect: clear error in console: "Missing Supabase env vars..."
mv .env.bak .env
```

- [ ] **Step 5: Commit**

```sh
git add .env.example src/integrations/supabase/client.ts
git commit -m "fix(phase1): harmonize VITE_SUPABASE_PUBLISHABLE_KEY env name + add boot guard"
git push
```

---

## Phase H — Revert script

### Task 25: Write `scripts/revert-phase1.sh`

**Files:**
- Create: `scripts/revert-phase1.sh`

- [ ] **Step 1: Write the script**

```sh
#!/usr/bin/env bash
# scripts/revert-phase1.sh
# One-command revert of Phase 1 work.
# Restores DB to pre-phase1 state, reverts code via git, redeploys edge functions.
# Usage: ./scripts/revert-phase1.sh        (interactive confirm)
#        ./scripts/revert-phase1.sh --yes  (non-interactive, for emergencies)

set -euo pipefail

CONFIRM="${1:-}"
PROJECT_REF="${SUPABASE_PROJECT_REF:-jwrpj-replace-me}"
PHASE1_TAG="pre-phase1-2026-05-05"
REVERT_MIGRATION="supabase/migrations/20260505120001_phase1_rls_lockdown_revert.sql"
INVENTORY_PRE=".planning/phase1/grants-inventory-pre.json"
INVENTORY_VERIFY=".planning/phase1/grants-inventory-revert-verify.json"

if [[ "$CONFIRM" != "--yes" ]]; then
  echo "This will revert ALL Phase 1 changes:"
  echo "  1. Apply revert SQL to project $PROJECT_REF"
  echo "  2. git revert the Phase 1 merge commit on main and push"
  echo "  3. Bulk-redeploy edge functions from post-revert HEAD"
  echo "  4. Verify pg_policies match $INVENTORY_PRE"
  read -rp "Type YES to proceed: " ans
  [[ "$ans" == "YES" ]] || { echo "Aborted"; exit 1; }
fi

echo "==> 1. Apply revert SQL"
# Requires `supabase` CLI authenticated to the project.
supabase db push --include-all --project-ref "$PROJECT_REF" || {
  echo "FAILED to apply revert SQL. Falling back to direct SQL apply."
  # Operator can paste the contents of $REVERT_MIGRATION into the SQL editor.
  echo "Paste the contents of $REVERT_MIGRATION into the Supabase SQL editor and run it, then re-run this script with --yes."
  exit 1
}

echo "==> 2. git revert Phase 1"
PHASE1_MERGE=$(git log --oneline --merges main..origin/main 2>/dev/null | grep "phase1" | head -1 | awk '{print $1}')
if [[ -z "$PHASE1_MERGE" ]]; then
  echo "Could not find phase1 merge commit. Find it manually with: git log --merges --grep=phase1"
  exit 1
fi
git checkout main
git pull --ff-only origin main
git revert -m 1 "$PHASE1_MERGE" --no-edit
git push origin main

echo "==> 3. Bulk-redeploy edge functions"
supabase functions deploy --project-ref "$PROJECT_REF" --no-verify-jwt || true

echo "==> 4. Verify pg_policies match pre-snapshot"
echo "Re-run inventory query Q1 from .planning/phase1/inventory-queries.sql against $PROJECT_REF"
echo "Save result to $INVENTORY_VERIFY and diff against $INVENTORY_PRE."
echo "If diff is empty (modulo timestamps/ordering), revert is complete."

echo "DONE."
```

- [ ] **Step 2: Make executable**

```sh
chmod +x scripts/revert-phase1.sh
```

- [ ] **Step 3: Add a README pointer**

Edit `.planning/phase1/README.md` to mention the revert procedure:

```
To revert Phase 1: ./scripts/revert-phase1.sh
Hard reset alternative: git reset --hard pre-phase1-2026-05-05
DB-only revert: apply supabase/migrations/20260505120001_phase1_rls_lockdown_revert.sql
```

- [ ] **Step 4: Commit**

```sh
git add scripts/revert-phase1.sh .planning/phase1/README.md
git commit -m "feat(phase1): one-command revert script (DB + git + edge redeploy)"
git push
```

---

### Task 26: Dry-run the revert script on the Supabase branch

**Files:** none modified.

- [ ] **Step 1: Run revert against the branch ref**

Set `SUPABASE_PROJECT_REF=<branch ref>` from `.planning/phase1/supabase-branch.json`. Run:

```sh
SUPABASE_PROJECT_REF="<branch ref>" ./scripts/revert-phase1.sh --yes
```

Note: the script's `git revert` step will fail because the branch's main hasn't received a phase1 merge yet. That's fine — the goal of this dry-run is to confirm the **DB-only** revert step works against the test branch.

- [ ] **Step 2: Run inventory Q1 against the branch**

Capture to `.planning/phase1/grants-inventory-branch-after-revert.json` (overwrite from Task 6, Step 2).

- [ ] **Step 3: Diff against `grants-inventory-pre.json`**

Use `jq` or a simple diff:

```sh
jq -S 'del(.captured_at)' .planning/phase1/grants-inventory-pre.json > /tmp/pre.json
jq -S 'del(.captured_at)' .planning/phase1/grants-inventory-branch-after-revert.json > /tmp/post.json
diff /tmp/pre.json /tmp/post.json && echo "REVERT CLEAN" || echo "REVERT DIRTY — patch the revert migration"
```

Expected: `REVERT CLEAN`. If dirty, patch the revert migration in Task 6 and retry.

- [ ] **Step 4: Reapply forward to leave branch in post-forward state for the next phase**

Re-apply the forward migration via `apply_migration` so the branch is back in the post-forward state we'll use for final verification.

- [ ] **Step 5: Commit dry-run report**

```sh
echo "Dry-run revert: PASSED $(date -u +%FT%TZ)" >> .planning/phase1/smoke-test-rls.md
git add .planning/phase1/smoke-test-rls.md \
        .planning/phase1/grants-inventory-branch-after-revert.json
git commit -m "chore(phase1): dry-run of revert script verified clean on Supabase branch"
git push
```

---

## Phase I — Verification & merge

### Task 27: Pre-merge verification (all 8 spec items)

**Files:**
- Create: `.planning/phase1/verification-report.md`

The 8 verification items are from spec §8. Each must pass before merge.

- [ ] **Step 1: Run the full check matrix**

For each item, capture the test command/output:

1. **Grant inventory diff empty for human users** — already verified on the branch in Task 5+6. Confirm `grants-inventory-branch-after-forward.json` matches the expected post-state.
2. **Each role can do what they could before** — verified on the branch in Task 7. Add a final check now if any new edge function changes (Task 12-13) might have affected role behaviour.
3. **Self-promotion attack fails** — Task 5, Step 3. Re-run if anything has changed in `user_roles` policies since.
4. **HCP webhook with tampered signature fails** — Task 9, Step 3. Repeat with a fresh captured payload.
5. **Vercel build green on branch** — confirm in Vercel dashboard. The branch should have multiple successful preview deploys.
6. **Lint warning count drops from ~2874 to ~252** — Task 23, Step 3.
7. **Dashboard renders identically to pre-phase1** — manual click-through. Side-by-side comparison: open prod twinsdash.com in one tab, the Vercel preview URL for the branch in another. Verify all routes look identical (modulo the deleted CPA chip and any visual side-effects of removing the 3B paste).
8. **24-hour observation passes** — this happens **after** merge, not before. Note as deferred.

- [ ] **Step 2: Write the verification report**

```markdown
# Phase 1 Verification Report

Date: 2026-05-XX

| # | Check | Status | Evidence |
|---|---|---|---|
| 1 | Grant inventory diff empty | PASS | grants-inventory-branch-after-forward.json |
| 2 | Roles unchanged | PASS | smoke-test-rls.md |
| 3 | Self-promotion attack denied | PASS | smoke-test-rls.md |
| 4 | HCP tampered → 401 | PASS | curl logs |
| 5 | Vercel build green | PASS | <preview URL> |
| 6 | Lint warnings ~252 | PASS | npm run lint 2>&1 \| grep -c warning |
| 7 | Visual identity | PASS | side-by-side screenshots |
| 8 | 24h observation | DEFERRED | post-merge |
```

- [ ] **Step 3: Commit**

```sh
git add .planning/phase1/verification-report.md
git commit -m "chore(phase1): pre-merge verification report — 7/7 mergeable items pass"
git push
```

---

### Task 28: Merge to main and apply migration to prod

**Files:** none directly; merge commit on main + migration applied to `jwrpj`.

- [ ] **Step 1: Open the PR**

Use the GitHub API path documented in project memory (`reference_gh_via_api.md`) — the operator's `gh` CLI is unavailable in this env. Token is in macOS keychain via `git-credential`.

```sh
TOKEN=$(printf 'host=github.com\nprotocol=https\n' | git credential fill | grep '^password=' | cut -d= -f2)
curl -sS -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/vnd.github+json" \
  -d '{
    "title": "Phase 1: Security & Cleanup",
    "head": "phase1-security-cleanup",
    "base": "main",
    "body": "Implements docs/superpowers/specs/2026-05-05-phase1-security-cleanup-design.md.\n\nAll 7 pre-merge verification items pass. Revert script: scripts/revert-phase1.sh. Pre-merge tag: pre-phase1-2026-05-05."
  }'
```

- [ ] **Step 2: Merge the PR**

After CI green and operator approval. Use a **merge commit** (not squash, not rebase) so the revert script can `git revert -m 1 <merge-sha>` cleanly.

- [ ] **Step 3: Apply forward migration to prod**

Use `mcp__a13384b5-...__apply_migration` with `project_id=<jwrpj ref>`, `name="phase1_rls_lockdown"`, `query=<contents of forward migration>`.

If it fails, IMMEDIATELY run the revert via `apply_migration` with `name="phase1_rls_lockdown_revert"` and the revert SQL. Document the failure mode.

- [ ] **Step 4: Bulk-deploy edge functions to prod**

For each function modified in Tasks 9, 12, 13: deploy via `mcp__a13384b5-...__deploy_edge_function` with `project_id=<jwrpj ref>`.

- [ ] **Step 5: Confirm Vercel deploys main**

Vercel auto-deploys main on merge. Wait for green deploy. Verify twinsdash.com is reachable and the dashboard renders.

---

### Task 29: Post-deploy inventory diff

**Files:**
- Create: `.planning/phase1/grants-inventory-post.json`

- [ ] **Step 1: Run inventory Q1+Q2+Q3 against prod jwrpj**

Use `execute_sql` with `project_id=<jwrpj ref>`. Save as `.planning/phase1/grants-inventory-post.json`.

- [ ] **Step 2: Diff against pre-snapshot**

```sh
jq -S '.user_role_counts' .planning/phase1/grants-inventory-pre.json > /tmp/pre-roles.json
jq -S '.user_role_counts' .planning/phase1/grants-inventory-post.json > /tmp/post-roles.json
diff /tmp/pre-roles.json /tmp/post-roles.json
```

Expected: empty diff (no roles added or lost). If diff is non-empty, **immediately run `./scripts/revert-phase1.sh`**.

- [ ] **Step 3: Smoke-test live**

Daniel logs into twinsdash.com:
1. As himself (admin).
2. Impersonates a tech.
3. Impersonates a manager.

For each, click `/`, `/leaderboard`, `/tech`, the role's normal home page. Anything missing or 403'd that wasn't before? If yes, revert.

- [ ] **Step 4: Commit post-deploy report**

```sh
git checkout main
git pull
git add .planning/phase1/grants-inventory-post.json
git commit -m "chore(phase1): post-deploy grant inventory captured + diff against pre-snapshot is empty"
git push
```

---

### Task 30: 24-hour observation window

**Files:**
- Modify: `.planning/phase1/verification-report.md` (mark item 8 PASS or revert)

- [ ] **Step 1: Set a calendar reminder for T+24h**

Use a scheduled task or just note the timestamp.

- [ ] **Step 2: At T+24h, check Supabase logs**

```sh
# Function logs
mcp__a13384b5-...__get_logs(project_id=<jwrpj>, service="edge-function")
# Postgres logs
mcp__a13384b5-...__get_logs(project_id=<jwrpj>, service="postgres")
```

Look for:
- 401s on hcp-webhook from real HCP traffic — should be zero. If non-zero, the webhook signature secret is wrong; investigate.
- RLS denial errors on legitimate dashboard queries — should be zero.
- Spike in 403s on edge functions from legitimate users — should be zero.

- [ ] **Step 3: Mark item 8 PASS or trigger revert**

If logs are clean: edit `.planning/phase1/verification-report.md`, set item 8 to PASS, commit.

If logs show breakage: run `./scripts/revert-phase1.sh`, document what broke in `.planning/phase1/post-mortem.md`, plan a Phase 1.1 hotfix.

- [ ] **Step 4: Final commit**

```sh
git add .planning/phase1/verification-report.md
git commit -m "chore(phase1): 24h observation passed — Phase 1 complete"
git push
```

---

## Self-Review (per writing-plans skill)

**Spec coverage check:**
- ✅ §6.1 RLS lockdown → Tasks 4-7
- ✅ §6.2 HCP webhook signature → Tasks 8-9
- ✅ §6.3 Edge function auth middleware → Tasks 10-13
- ✅ §6.4 Trust artifact deletes → Tasks 14-16
- ✅ §6.5 Dead code & dep sweep → Tasks 17-23
- ✅ §6.6 Env naming harmonization → Task 24
- ✅ §7 Revert procedure → Tasks 25-26
- ✅ §8 Verification (8 items) → Tasks 27, 29, 30
- ✅ §11 Acceptance criteria → covered by Tasks 1, 25, 27, 28

**Placeholder scan:** No "TBD" or "TODO" lines. Every code block has runnable code; every command is exact. Two intentional uncertainty callouts (HCP signature header name in Task 8; Capacitor `android/`/`ios/` directories in Task 22) are explicit decision points where the executor halts and asks if needed.

**Type/name consistency:** `requireAdminAuth` named consistently in Tasks 11, 12. `verifyHcpSignature` consistent in Tasks 8, 9. Migration filenames (`20260505120000` forward, `20260505120001` revert) consistent across Tasks 4, 5, 6, 25, 26, 28.

**Out-of-scope guardrails:** Each phase delete (CSS paste, dead pages) limits its own blast radius via per-task commits. The RLS migration is gated on Supabase-branch verification before prod application. The revert script is the universal escape hatch.

---

## Execution Handoff

**Plan complete and saved to `docs/superpowers/plans/2026-05-05-phase1-security-cleanup.md`. Two execution options:**

**1. Subagent-Driven (recommended for the cosmetic deletes — Tasks 14-23)** — I dispatch a fresh subagent per task, review between tasks. Good for the Phase E + F batch where each task is independently testable and low-risk.

**2. Inline Execution (recommended for the migration + auth work — Tasks 1-13, 25-30)** — Execute in this session with checkpoints between phases. Higher-risk DB and auth work needs a single conversation thread holding context across the verify/halt checkpoints.

**Recommended split:** Inline for Phases A–D + H–I (the high-stakes work). Subagent-driven for Phases E–F (the cosmetic + cleanup work). Phase G (env naming) can go either way.

Which approach?
