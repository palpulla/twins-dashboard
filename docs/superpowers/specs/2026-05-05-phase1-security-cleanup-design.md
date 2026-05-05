# Phase 1 — Security & Cleanup

**Date:** 2026-05-05
**Source audit:** `twins-dash/DASHBOARD_FULL_AUDIT.md`
**Repo:** `twins-dash` (live at twinsdash.com, Vercel-deployed)
**Position in rollout:** First of five phase specs. See "Full rollout context" below.

---

## 1. Goal

Eliminate the security and credibility risks identified in the dashboard audit without changing any user-visible feature. After this phase ships:

- No authenticated user can self-promote to admin or read commission rules from the client.
- The HCP webhook only accepts payloads signed by HousecallPro.
- Edge functions enforce auth via a single shared middleware.
- Three trust artifacts (the hardcoded `↓ 15% improved` chip, the 940-line 3B Holdings CSS paste, the triple-refetch storm) are deleted.
- Dead code and redundant dependencies are gone.
- The whole change is fully reversible via a single `revert-phase1.sh` script.

This is intentionally a zero-feature rollout. No new pages, no new metrics, no UX changes. The goal is a clean baseline before any feature work in later phases.

## 2. Out of scope (deferred to later specs)

These are real audit findings but **explicitly not in this spec**:

- Rev & Rise daily-mode extension, KPI tile drilldowns, Watchlist panel, Behaviors row, Open Estimates page, jobs-needing-review queue → **Spec 2 (Operator Surfaces)**
- `<PageShell>` / `<EmptyState>` / `<ErrorState>` primitives, single navy hex, sortable tables, mobile fixes, real favicon, Tier→Score rename → **Spec 3 (UI System)**
- Single `kpi-calculations.ts` source of truth, Postgres KPI views, `select` column lists, strict TS, per-route ErrorBoundary, zod schemas → **Spec 4 (KPI Consolidation + Perf + Types)**
- `/diligence` page, cohort retention, customer concentration, commercial vs residential, TTM toggle, closed-period locking → **Spec 5 (Buyer-Ready)**
- LSA OAuth credential rotation → handled outside the dashboard repo (separate backlog).
- Decommissioning the stale `wxip…` Supabase project → noted, separate operational task.

## 3. Full rollout context

This spec is the first of five sequential phase specs. Each phase runs its own brainstorm → spec → plan → execute loop with the same safety pattern (Section 5). The five-phase order is locked and deliberate: security first (this spec), then operator surfaces, then UI system, then engineering cleanup, then buyer-readiness. Each phase ships behind atomic git commits and is independently revertable. Future specs may be revised based on what we learn shipping this one — but the order of phases is set.

## 4. Constraints (from project memory)

- **All changes must be reversible.** Hard requirement. Drives the rollback-migration design in Section 6.
- **KPIs immutable.** No KPI math changes in this phase. (KPI consolidation is Spec 4.)
- **Access stays as it is today.** No human currently using the dashboard loses any access they have today. Drives the grant-inventory step in Section 6.1.
- **Don't ask, just do it.** Decisions inside this spec are mine to make; user reviews the spec before plan-writing begins.
- **Live site must keep working.** Branch + Vercel preview + Supabase branch testing required before main merges.

## 5. Safety pattern (option B + rollback migrations)

Every phase ships through the same pattern:

1. **Branch** off main: `phase1-security-cleanup`. All work in this branch.
2. **Tag main** before any change: `pre-phase1-2026-05-05`. Used as the hard-reset target if we ever need to bypass the script.
3. **Vercel preview deploy** is automatic on the branch push. Used to smoke-test code-only changes (CSS paste, CPA chip, dead code).
4. **Supabase branch** (`mcp__supabase__create_branch`) for migration work. All RLS / webhook / edge-function changes apply to the branch first.
5. **Grant inventory** before policy rewrite — query `user_roles` and every related table on the live `jwrpj` project, snapshot to `.planning/phase1/grants-inventory-pre.json`. Same query post-deploy → `grants-inventory-post.json`. Diff must be empty for human users (Section 6.1.4).
6. **Rollback migration** committed alongside every forward migration. Naming: `<timestamp>_phase1_<topic>.sql` and `<timestamp>_phase1_<topic>_revert.sql`.
7. **`revert-phase1.sh` script** committed in the same branch, runs the full restoration in one command (Section 7).
8. **Post-deploy observation:** 24 hours after merge to main, re-run the grant inventory diff and a smoke test of the live dashboard for each named user role. If anything drifted, run revert.

## 6. Work items

Numbered in the order they are committed. Each item is one atomic git commit and is independently revertable in reverse order if a single item fails.

### 6.1 RLS lockdown (the high-risk piece)

**Files:** new migration `supabase/migrations/<ts>_phase1_rls_lockdown.sql` + revert pair.

**Tables touched:**
- `user_roles` — current policy `Allow all operations FOR ALL USING (true)` → admin-only-write via `is_admin_or_manager(auth.uid())`. SELECT remains broad enough for the dashboard to read its own role at boot.
- `jobs`, `technicians`, `marketing_spend`, `calls_inbound`, `memberships*`, `integrations_config`, `sync_progress` — current `FOR ALL USING (true)` → role-aware policies. Authed users SELECT, only admin/manager INSERT/UPDATE/DELETE. Service role unaffected (used by edge functions).
- `tech_coaching_notes` — current `FOR SELECT USING (true)` → owner-tech + admin/manager only.
- `technician_commission_rules`, `parts`, `job_parts`, `parts_price_history` — current `FOR SELECT TO public USING (true)` → revoke `public`, grant authenticated. Commission rules further restricted to admin/manager.

**Why these specific helpers:** `is_admin_or_manager(auth.uid())` and `has_role(...)` already exist in the migrations history (per audit §8). We reuse them rather than introducing new ones.

**Sub-steps (each its own commit):**

1. **6.1.1 Inventory.** Query and snapshot all current grants. Source of truth for both the new policies (we generate them from the actual current state, not guesses) and the post-deploy diff. Output: `.planning/phase1/grants-inventory-pre.json` committed to the branch (no PII — just role names + counts + table names).
2. **6.1.2 Forward migration.** Generated from the inventory. Drops every permissive policy, recreates restrictive policies. Includes explicit `GRANT` statements for `authenticated` and `service_role`.
3. **6.1.3 Revert migration.** Restores the previous (permissive) policy state exactly as captured in the inventory. Tested by applying forward + revert on the Supabase branch and confirming pg_policies match the original snapshot.
4. **6.1.4 Smoke test on Supabase branch.** Daniel logs into the branch URL as: (a) himself (admin), (b) an impersonated tech account, (c) the impersonated manager account. Each must see the same data they see today. Pass = merge. Fail = adjust policies, retest.
5. **6.1.5 Apply to prod.** Merge branch + apply migration on `jwrpj` project. Re-run inventory → `grants-inventory-post.json`. Diff against pre-snapshot. Empty diff = ship.

**Risk:** medium. Mitigated by inventory + branch test + rollback migration.

### 6.2 HCP webhook signature verification

**File:** `supabase/functions/hcp-webhook/index.ts`

**Change:** Before any DB write, verify the HCP signature header against the request body using the shared secret. Reject 401 on mismatch. Secret stored in Supabase secrets (`HCP_WEBHOOK_SECRET`).

**Verification:** Send a known-signed payload (replay one captured from the live HCP webhook log) → 200. Send the same payload with a tampered signature → 401. Send the same payload with no signature → 401.

**Risk:** low — additive guard. If HCP signature handling is wrong, real webhooks fail and `jobs` stops getting updated; the rollback is `git revert` on this single function. Sync delay is detectable on the dashboard sync chip, so we'll know within an hour.

**Revert:** `git revert <commit>` + redeploy the function from the prior version. No DB state to undo.

### 6.3 Edge function auth middleware

**Files:** new `supabase/functions/_shared/require-admin-auth.ts`, plus per-function changes across `supabase/functions/*` (29 functions per audit §8).

**Change:** Single `requireAdminAuth(req)` helper. Each admin-scoped function calls it as the first line. `verify_jwt = true` flipped on for functions that should require auth at the gateway layer.

**Sequencing:** I'll classify the 29 functions in three buckets:
- **Public** (e.g. webhook receivers): `verify_jwt = false`, signature verification.
- **Authed** (e.g. user-triggered AI insight): `verify_jwt = true`, no admin check.
- **Admin-only** (e.g. payroll lock, role assignment): `verify_jwt = true` + `requireAdminAuth`.

The inventory lives in the spec deliverable as a table.

**Risk:** medium — touches 29 functions. Mitigated by classifying first, applying per bucket, redeploying via `supabase functions deploy --bulk`. Each function is independently revertable.

**Revert:** `git revert <commit>` + bulk redeploy from the prior commit's source.

### 6.4 Trust artifact deletes

Three small commits, each independently revertable via `git revert`:

1. **6.4.1** Delete the `↓ 15% improved` chip at `src/pages/Index.tsx:735`. Replace with the real period-over-period delta if available, otherwise nothing. This is one commit.
2. **6.4.2** Delete `src/index.css:80-1021` (the 3B Holdings paste). Verify no Twins component depends on the OKLCH `--accent` value, the unused sidebar drawer CSS, or the body font-size override. If any consumer is found, replace with an equivalent token in `tailwind.config.ts` first.
3. **6.4.3** Delete the `setInterval(refetch, 10000)` block at `src/pages/Index.tsx:191-194`. Realtime subscription + 60s `refetchInterval` already cover freshness.

**Risk:** low — pure deletions. Vercel preview deploy + visual smoke check on each.

### 6.5 Dead code and dependency sweep

**Deletions:**
- `src/pages/MarketingSourceROIv1.tsx` (legacy duplicate, 287 LOC).
- `src/pages/WhatIfScenario.tsx` + `/what-if-legacy` route in `App.tsx`.
- `src/pages/payroll/Parts.tsx` (not imported in `App.tsx`).
- `html2canvas` dep (zero references in `src/` or `supabase/`).
- One of `xlsx` / `exceljs` — keep `exceljs` (xlsx@0.18 has known CVEs). Audit which call-sites use which library, migrate the xlsx ones to exceljs, remove `xlsx` from package.json.
- Capacitor mobile deps **only if** there's no active iOS/Android build pipeline. Confirm with Daniel before this commit (it's the one open question — see Section 9).

**Additions:**
- `.worktrees/` added to `eslint.config.js` `ignorePatterns`. Drops the lint `any`-warning count from 2874 to 252.

**Risk:** low. Vercel preview catches any accidental remaining import.

### 6.6 Env naming harmonization

**File:** `src/integrations/supabase/client.ts:6` reads `VITE_SUPABASE_PUBLISHABLE_KEY`; `.env.example` documents `VITE_SUPABASE_ANON_KEY`. Pick the publishable name (it's already in prod), fix `.env.example`, add a single boot-time guard in `client.ts` that throws a readable error if either env var is missing.

**Risk:** trivial.

## 7. The revert procedure ("type revert")

Single command:

```sh
./scripts/revert-phase1.sh
```

Script behavior:

1. Confirm with the operator (`y/N` prompt) — non-interactive `--yes` flag for emergencies.
2. **Database first.** Apply the revert migrations in reverse order using `supabase db push` against the `jwrpj` project. Confirm pg_policies match the pre-phase1 inventory (Section 6.1.5).
3. **Code second.** `git checkout main && git revert -m 1 <merge-commit-of-phase1> --no-edit && git push origin main`. Vercel auto-redeploys.
4. **Edge functions.** `supabase functions deploy --project-ref jwrpj --no-verify-jwt` for the affected functions, sourcing from the post-revert main HEAD.
5. **Verify.** Re-run grant inventory → must match `grants-inventory-pre.json`. Print pass/fail.

**If the script itself fails midway:** hard-reset is `git reset --hard pre-phase1-2026-05-05 && git push --force-with-lease origin main` for code, and `supabase db reset --linked` is **not** safe (drops data) — instead, rerun the revert migrations manually via SQL Editor. The script logs each step so you know where it stopped.

## 8. Verification plan

Each work item has an item-level verification listed inline. The spec-level verification is:

1. **Grant inventory diff is empty for every human user.** Pre vs post `user_roles` snapshot identical for non-test rows.
2. **Each role can do what they could before.** Daniel logs in as admin; an impersonated tech logs in; an impersonated manager logs in. All three can see and do what they could on the day before phase 1 shipped. Specifically tested: admin sees `/admin/*`, tech sees `/tech/*` only, both see their own commission row.
3. **Self-promotion attack fails.** From a non-admin authed session in browser devtools: `await supabase.from('user_roles').update({role: 'admin'}).eq('user_id', auth.user.id)` returns an RLS denial. Pre-phase1 this would succeed.
4. **HCP webhook with a tampered signature fails.** Captured production payload + flipped one byte in the signature → 401.
5. **Vercel build is green** on the branch and on main after merge.
6. **Lint warning count** drops from ~2874 to ~252 (`.worktrees/` exclusion is correctly applied).
7. **Dashboard renders** the same Index, Leaderboard, Tech, Admin, Marketing, Memberships, RevRise, WhatIf pages with identical visible output as the pre-phase1 main HEAD.
8. **24-hour observation passes.** No 401s in webhook logs from real HCP traffic; no spike in client errors in Vercel logs; sync chip on RevRise stays green.

Acceptance = all 8 pass. If any fail, revert.

## 9. Open question (one)

**Capacitor mobile build.** Audit flagged Capacitor deps as candidates for removal because no mobile build pipeline appears active. Removing them shrinks `node_modules` and CI time. Question for Daniel: do you intend to ship the iOS/Android wrapper this year? If yes — keep deps, this work moves to Spec 4 (engineering cleanup). If no — remove now in step 6.5.

Default if unanswered: keep them. Removal is reversible later.

## 10. Out-of-scope footguns to be aware of during execution

- **Dual-Supabase risk** (`wxip…` still receiving writes per memory). Phase 1 only touches `jwrpj`. Daniel knows about the wxip cleanup; that's a separate operational task and pre-phase1 grants on wxip are not snapshotted here.
- **AuthContext race-bug** (`Account Pending` flash). Phase 1 doesn't touch `AuthContext.tsx`. The `react-hooks/exhaustive-deps` warning at `AuthContext.tsx:143` is real but its fix could regress the prior race. That's Spec 4.
- **KPI math drift** (`totalRevenue` in 6 files). Tempting to fix while in the codebase but explicitly out of scope. Spec 4.
- **CSP / HSTS headers.** Not audited, not in Phase 1. If the operator wants this, separate task.

## 11. Acceptance criteria

- [ ] All 8 verification items in Section 8 pass.
- [ ] `revert-phase1.sh` is committed, executable, dry-run tested on the Supabase branch.
- [ ] Pre-phase1 git tag `pre-phase1-2026-05-05` exists on origin.
- [ ] `.planning/phase1/grants-inventory-pre.json` and `grants-inventory-post.json` both committed.
- [ ] Branch is merged to main with a single merge commit (revert target).
- [ ] No code changes outside the audit's Phase 1 + Quick Wins items.
- [ ] No KPI math changed.
- [ ] No new feature shipped to users.

## 12. Deliverables of this spec

When implementation is complete:

1. One merged PR on `twins-dash` containing the full Phase 1 work as a single merge commit.
2. `scripts/revert-phase1.sh` in the repo.
3. The two grant-inventory JSON files.
4. The pre-phase1 git tag.
5. Updated `.env.example`.
6. A short PR description matching the verification checklist.

## 13. Next phase

After Phase 1 is shipped and observed for 24 hours, the next brainstorm starts: **Spec 2 — Operator Surfaces.** Scope locked at brainstorm time; expect the Rev & Rise daily-mode extension, KPI tile drilldowns, Watchlist panel, Behaviors row, Open Estimates page, jobs-needing-review queue.
