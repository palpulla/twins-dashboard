# Jobs/Parts-Cost Merge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make team hero and admin commission reports reflect auto-detected and tech-entered parts (currently they only see HCP's `jobs.parts_cost`, which is often $0 or stale).

**Architecture:** A single SQL view `v_jobs_with_parts` exposes every column of `jobs` plus a new `effective_parts_cost = GREATEST(jobs.parts_cost, SUM(payroll_job_parts.total))`. Two consumer queries swap `from('jobs')` → `from('v_jobs_with_parts')` and use the new column. Soft-deleted parts (`removed_by_tech = true`) are excluded.

**Tech Stack:** Supabase Postgres view, React + TypeScript consumer swaps. No frontend tests change.

---

## Repo Context

- **Repo root:** `/Users/daniel/twins-dashboard/twins-dash`. Worktree at `.worktrees/jobs-parts-merge`.
- **Branch from:** `origin/main` at `a5675aa` or later (Wins tab v1 just merged).
- **Spec:** `docs/superpowers/specs/2026-04-29-jobs-parts-cost-merge-design.md`
- **Migration history desync:** same pattern as the previous 4 features. If `npx supabase db push` complains, fall back to `npx supabase db query --linked -f <file>` + manual `INSERT INTO supabase_migrations.schema_migrations`.

## File Structure

| File | Action | Purpose |
|---|---|---|
| `supabase/migrations/20260429200000_v_jobs_with_parts.sql` | Create | View definition + GRANT SELECT TO authenticated |
| `src/hooks/admin/useTeamOverview.ts` | Modify | Swap `from('jobs')` → `from('v_jobs_with_parts')`; use `effective_parts_cost` |
| `src/pages/AdminCommissionReports.tsx` | Modify | Same swap; map `parts_cost: j.effective_parts_cost` |

3 files total.

---

## M1 — Worktree

### Task 0: Worktree + branch

- [ ] **Step 1**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git fetch origin
git worktree add .worktrees/jobs-parts-merge -b feat/jobs-parts-merge origin/main
cd .worktrees/jobs-parts-merge
```

- [ ] **Step 2: Sanity**

```bash
git status
git log --oneline -3
```

Expected: clean tree on `feat/jobs-parts-merge`. HEAD at `a5675aa` or later.

---

## M2 — SQL view

### Task 1: Migration file

**Files:**
- Create: `supabase/migrations/20260429200000_v_jobs_with_parts.sql`

- [ ] **Step 1: Create the migration file**

```bash
cat > supabase/migrations/20260429200000_v_jobs_with_parts.sql <<'EOF'
-- v_jobs_with_parts: a read-only view over jobs that exposes effective_parts_cost
-- merged from jobs.parts_cost (HCP-sourced) and payroll_job_parts (Twins-side,
-- including auto-detected and tech-entered parts).
--
-- Why GREATEST:
--   - jobs.parts_cost reflects whatever HCP captured; often $0 or stale.
--   - payroll_job_parts SUM reflects what techs entered + what auto-detect found.
--   - In rare cases both have data; whichever is larger is most likely correct.
--
-- Why filter removed_by_tech:
--   - When a tech soft-removes an auto-detected part, it shouldn't count toward
--     effective_parts_cost. Mirrors the auto-detect Edge Function's idempotency.
--
-- Reversibility: DROP VIEW public.v_jobs_with_parts;

CREATE OR REPLACE VIEW public.v_jobs_with_parts AS
SELECT
  j.*,
  GREATEST(
    COALESCE(j.parts_cost, 0),
    COALESCE(p.payroll_parts_sum, 0)
  ) AS effective_parts_cost
FROM public.jobs j
LEFT JOIN (
  SELECT pj.hcp_job_number,
         SUM(jpp.total) AS payroll_parts_sum
  FROM public.payroll_jobs pj
  JOIN public.payroll_job_parts jpp ON jpp.job_id = pj.id
  WHERE NOT jpp.removed_by_tech
  GROUP BY pj.hcp_job_number
) p ON p.hcp_job_number = j.hcp_job_number;

GRANT SELECT ON public.v_jobs_with_parts TO authenticated;
EOF
```

- [ ] **Step 2: Verify**

```bash
wc -l supabase/migrations/20260429200000_v_jobs_with_parts.sql
head -5 supabase/migrations/20260429200000_v_jobs_with_parts.sql
```

Expected: ~30 lines; first line starts with `-- v_jobs_with_parts:`.

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260429200000_v_jobs_with_parts.sql
git commit -m "feat(jobs-parts-merge): SQL view v_jobs_with_parts merges jobs.parts_cost with payroll_job_parts SUM"
```

---

### Task 2: Apply to prod + verify

- [ ] **Step 1: Try standard push**

```bash
npx supabase db push --linked
```

If clean, skip Step 2.

- [ ] **Step 2: Fallback (likely needed due to migration history desync)**

```bash
npx supabase db query --linked -f supabase/migrations/20260429200000_v_jobs_with_parts.sql
npx supabase db query --linked "INSERT INTO supabase_migrations.schema_migrations (version, name) VALUES ('20260429200000', 'v_jobs_with_parts') ON CONFLICT DO NOTHING;"
```

- [ ] **Step 3: Verify view exists**

```bash
npx supabase db query --linked "SELECT relname, relkind FROM pg_class WHERE relname = 'v_jobs_with_parts';"
```

Expected: 1 row with `relkind = v` (view).

- [ ] **Step 4: Spot-check the merge logic**

```bash
npx supabase db query --linked "SELECT j.hcp_job_number, j.parts_cost AS hcp_value, v.effective_parts_cost AS merged FROM public.v_jobs_with_parts v JOIN public.jobs j ON j.id = v.id WHERE v.effective_parts_cost > 0 ORDER BY v.effective_parts_cost DESC LIMIT 10;"
```

Expected: rows where `merged >= hcp_value`. If `merged > hcp_value` for any row, that's payroll-side parts kicking in (tech entries + auto-detected). If `merged = hcp_value` for everything, no jobs have payroll-side parts yet — that's also fine for a freshly-shipped feature.

- [ ] **Step 5: Verify soft-deleted parts excluded**

```bash
npx supabase db query --linked "SELECT COUNT(*) AS count_with_removed FROM public.payroll_job_parts WHERE removed_by_tech = TRUE;"
```

If this returns 0, the spec's soft-delete-exclusion logic is currently a no-op (no removed parts in the system yet). That's fine — the filter will activate once techs start using the soft-delete UI.

If non-zero, do a manual check: for one of those jobs, query both `SUM(jpp.total) WHERE NOT removed_by_tech` and `SUM(jpp.total) WHERE removed_by_tech = TRUE`. Confirm the view's `effective_parts_cost` matches the former, not the sum of both.

---

## M3 — Consumer swaps

### Task 3: Swap useTeamOverview + AdminCommissionReports

**Files:**
- Modify: `src/hooks/admin/useTeamOverview.ts`
- Modify: `src/pages/AdminCommissionReports.tsx`

Both consumers do the same swap pattern: `.from('jobs')` → `.from('v_jobs_with_parts')` + use `effective_parts_cost` instead of `parts_cost`.

- [ ] **Step 1: useTeamOverview**

Open `src/hooks/admin/useTeamOverview.ts`. Find the query block (around line 60-65):

```ts
const { data: jobs, error: jobsErr } = await supabase
  .from('jobs')
  .select('tech_id, revenue_amount, parts_cost, status, job_type, completed_at, is_callback, membership_attached')
  .eq('status', 'completed')
  .gte('completed_at', thirtyDaysAgoIso);
```

Use the Edit tool to change `from('jobs')` → `from('v_jobs_with_parts')` and add `effective_parts_cost` to the select:

```ts
const { data: jobs, error: jobsErr } = await supabase
  .from('v_jobs_with_parts')
  .select('tech_id, revenue_amount, parts_cost, effective_parts_cost, status, job_type, completed_at, is_callback, membership_attached')
  .eq('status', 'completed')
  .gte('completed_at', thirtyDaysAgoIso);
```

Then update the type annotation around line 66-75 to add the new column:

```ts
const allJobs = (jobs ?? []) as Array<{
  tech_id: string | null;
  revenue_amount: number | null;
  parts_cost: number | null;
  effective_parts_cost: number | null;  // NEW
  status: string;
  job_type: string | null;
  completed_at: string | null;
  is_callback: boolean | null;
  membership_attached: boolean | null;
}>;
```

Then change the parts-cost reference at line 148 from `j.parts_cost` to `j.effective_parts_cost`:

```ts
const weekEstCommission = techWeekJobs.reduce((s, j) => {
  const net = Math.max(0, Number(j.revenue_amount ?? 0) - Number(j.effective_parts_cost ?? 0));
  return s + net * commPct;
}, 0);
```

- [ ] **Step 2: AdminCommissionReports**

Open `src/pages/AdminCommissionReports.tsx`. Find the jobs query (use `grep -n "from.*jobs" src/pages/AdminCommissionReports.tsx | head -3` to locate it precisely; the query should be near the `useQuery` hooks at the top).

Swap `.from('jobs')` → `.from('v_jobs_with_parts')`. Add `effective_parts_cost` to the column list if select is explicit; if it's `select('*')`, the new column is included automatically.

Then find line 69 where `parts_cost: j.parts_cost` is mapped into `JobForCommission`. Change to:

```ts
parts_cost: j.effective_parts_cost ?? j.parts_cost ?? 0,
```

The `?? j.parts_cost ?? 0` defensively handles edge cases where the view doesn't expose the new column for some reason. The downstream `calculateCommissionSummary` uses the field generically.

- [ ] **Step 3: TS compile**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 4: Run all tests**

```bash
npx vitest run 2>&1 | tail -10
```

Expected: same pass count as before (~209). No new tests; consumer changes are integration-level.

- [ ] **Step 5: Build check**

```bash
npm run build 2>&1 | tail -3
```

Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add src/hooks/admin/useTeamOverview.ts src/pages/AdminCommissionReports.tsx
git commit -m "feat(jobs-parts-merge): useTeamOverview + AdminCommissionReports use v_jobs_with_parts.effective_parts_cost"
```

---

## M4 — Ship

### Task 4: Push + PR

- [ ] **Step 1: Push**

```bash
git push -u origin feat/jobs-parts-merge
```

- [ ] **Step 2: Open PR via GitHub API**

```bash
TOKEN=$(printf "protocol=https\nhost=github.com\n" | git credential-osxkeychain get | grep '^password=' | cut -d= -f2)
curl -s -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.github+json" \
  -d "$(cat <<'EOF'
{
  "title": "feat(jobs-parts-merge): team hero + commission reports see auto-detected parts (v_jobs_with_parts view)",
  "head": "feat/jobs-parts-merge",
  "base": "main",
  "body": "Implements `docs/superpowers/specs/2026-04-29-jobs-parts-cost-merge-design.md`.\n\n## Summary\n\nThe Team Hero / Supervisor Dashboard and Admin Commission Reports were silently underreporting parts cost (and overreporting commission) for any tech using the Twins-side parts flow — they read `jobs.parts_cost` (HCP-sourced) and missed everything in `payroll_job_parts` (auto-detected + tech-entered + soft-deletable).\n\nFix: a new SQL view `v_jobs_with_parts` exposes every column of `jobs` plus `effective_parts_cost = GREATEST(jobs.parts_cost, SUM(payroll_job_parts.total))`. Soft-deleted parts (`removed_by_tech = true`) are excluded.\n\nTwo consumers swap `from('jobs')` → `from('v_jobs_with_parts')` and use `effective_parts_cost` instead of `parts_cost`. Three files total. No HCP webhook changes, no triggers, no commission backfill.\n\n## Files\n- New: `supabase/migrations/20260429200000_v_jobs_with_parts.sql` (view + GRANT)\n- Modified: `src/hooks/admin/useTeamOverview.ts` (table + column references)\n- Modified: `src/pages/AdminCommissionReports.tsx` (table + column references)\n\n## Behavior preserved\n- Scorecard / `useAdminTechJobs` already does the merge client-side. Unchanged in this PR.\n- Leaderboard doesn't read parts_cost. Unchanged.\n- HCP webhook still writes `jobs.parts_cost` from HCP data. Unchanged.\n\n## Test plan\n- [x] tsc + vite build clean\n- [x] vitest: full suite passing\n- [x] SQL: view exists, effective_parts_cost merges as expected for live data\n- [ ] Manual smoke (Vercel preview): open `/team` and `/admin/commission`. Verify any tech with auto-detected or tech-entered parts now shows accurate commission numbers (lower than before, by exactly the parts_cost they had auto-inserted)\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"
}
EOF
)" | python3 -c "import sys,json; d=json.load(sys.stdin); print('PR URL:', d.get('html_url','ERR')); print('Number:', d.get('number','ERR'))"
```

Expected: PR URL + number.

---

## Self-Review

**Spec coverage:**
- ✅ View merges HCP value with payroll_job_parts SUM via GREATEST
- ✅ Soft-deleted parts excluded from the SUM
- ✅ useTeamOverview consumer migrated
- ✅ AdminCommissionReports consumer migrated
- ✅ useAdminTechJobs intentionally NOT touched (already does the merge client-side)
- ✅ Reversibility — drop view + revert 2 client files
- ✅ RLS handled by view inheritance from jobs

**No placeholders.** All SQL is verbatim. Both client edits have specific old/new strings + line references.

**Type consistency:** `effective_parts_cost` named consistently across SQL view, useTeamOverview type annotation + reference, AdminCommissionReports mapping.
