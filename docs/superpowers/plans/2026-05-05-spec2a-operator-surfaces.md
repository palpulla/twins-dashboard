# Spec 2A: Operator Surfaces (Daily-mode core) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a daily-mode toggle to Rev & Rise, ship a jobs-needing-review queue, wire KPI tile drilldowns across the dashboard, and connect the existing `public.reviews` table to per-tech rating chips.

**Architecture:** Two new SQL views (`v_yesterday_recap`, `v_jobs_needing_review`) + four hooks + one reusable `DrilldownSheet` component + four new Rev & Rise daily-mode blocks. Toggle defaults to current call-window behavior so the surface ships additively. Same safety net as Phase 1: pre-tag, paired revert SQL, one-command revert script.

**Tech Stack:** TypeScript, React 18, Vite, Supabase (Postgres + Edge Functions/Deno), TanStack Query, `@radix-ui/react-dialog`, `@tanstack/react-virtual`, Vercel deploy. Source spec: `docs/superpowers/specs/2026-05-05-spec2a-operator-surfaces-design.md`.

**Working directory for code work:** `/Users/daniel/twins-dashboard/twins-dash`.
**Working directory for plan/spec doc edits:** `/Users/daniel/twins-dashboard`.

---

## File structure (created vs modified)

**Created:**
- `.planning/spec2a/README.md`
- `.planning/spec2a/DEFERRED.md`
- `.planning/spec2a/views-pre-snapshot.json`
- `.planning/spec2a/verification-report.md`
- `supabase/migrations/20260505140000_spec2a_views.sql`
- `supabase/migrations/20260505140001_spec2a_views_revert.sql`
- `src/hooks/use-jobs-needing-review.ts`
- `src/hooks/use-drilldown-jobs.ts`
- `src/hooks/use-yesterday-recap.ts`
- `src/components/dashboard/DrilldownSheet.tsx`
- `src/components/rev-rise/ModeToggle.tsx`
- `src/components/rev-rise/YesterdayRecap.tsx`
- `src/components/rev-rise/DispatchQueue.tsx`
- `src/components/rev-rise/JobsNeedingReview.tsx`
- `src/components/rev-rise/MissedMoneyPanel.tsx`
- `scripts/revert-spec2a.sh`

**Modified:**
- `src/hooks/use-rev-rise-data.ts` (add `mode` param, wire reviews/ratings from `public.reviews`)
- `src/pages/RevRiseDashboard.tsx` (read `?mode` URL param, render daily blocks when `mode=daily`)
- `src/pages/Index.tsx` (drilldown wiring on KPI tiles)
- `src/components/dashboard/TechnicianBreakdown.tsx` (drilldown wiring + rating chip)
- `src/components/rev-rise/PerTechCards.tsx` (rating chip)

---

## Group A — Setup

### Task 1: Pre-flight tag and feature branch

**Files:** none modified; tag + branch on `palpulla/twins-dash`.

- [ ] **Step 1: Confirm clean working tree on twins-dash main**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
git status --short
git fetch origin && git checkout main && git pull --ff-only origin main
git log --oneline -1
```

Expected: `git status` clean. Last commit on main = the Phase 1 merge `e075184` or later. Halt and ask the operator if main is in an unexpected state.

- [ ] **Step 2: Create the safety tag**

```sh
git tag -a pre-spec2a-2026-05-05 -m "Pre-Spec-2A safety tag — restore target if Spec 2A must be aborted"
git push origin pre-spec2a-2026-05-05
```

Expected: `git ls-remote --tags origin | grep pre-spec2a` returns the tag.

- [ ] **Step 3: Create the feature branch**

```sh
git checkout -b spec2a-operator-surfaces
git push -u origin spec2a-operator-surfaces
```

Expected: branch tracks origin. Vercel preview will auto-build.

- [ ] **Step 4: Commit a marker file**

Create `.planning/spec2a/README.md` with the same shape as `.planning/phase1/README.md`:

```sh
mkdir -p .planning/spec2a
```

Write to `.planning/spec2a/README.md`:

```markdown
# Spec 2A — Operator Surfaces (Daily-mode core)

| Item | Reference |
|---|---|
| Spec | `docs/superpowers/specs/2026-05-05-spec2a-operator-surfaces-design.md` (in outer repo) |
| Plan | `docs/superpowers/plans/2026-05-05-spec2a-operator-surfaces.md` (in outer repo) |
| Pre-merge safety tag | `pre-spec2a-2026-05-05` (on `palpulla/twins-dash`) |
| Feature branch | `spec2a-operator-surfaces` (on `palpulla/twins-dash`) |
| Live Supabase project | `jwrpjuqaynownxaoeayi` |

## Revert procedure

When Spec 2A is merged, the script `scripts/revert-spec2a.sh` will:
1. Apply the revert SQL (drops the new views).
2. `git revert -m 1` the merge commit and push to main.
3. Verify the views are gone.

Hard-reset alternative if the script itself fails:
```sh
git reset --hard pre-spec2a-2026-05-05
git push --force-with-lease origin main
# Then drop views manually via SQL editor.
```
```

Then commit:

```sh
git add .planning/spec2a/README.md
git commit -m "chore(spec2a): pin spec/plan/tag references"
git push
```

---

### Task 2: Capture pre-snapshot of view state on prod

**Files:**
- Create: `.planning/spec2a/views-pre-snapshot.json`

- [ ] **Step 1: Confirm neither view exists on prod yet**

Use the Supabase MCP `execute_sql` against `project_id="jwrpjuqaynownxaoeayi"`:

```sql
SELECT table_name
FROM information_schema.views
WHERE table_schema = 'public'
  AND table_name IN ('v_yesterday_recap', 'v_jobs_needing_review');
```

Expected: empty result. If any row returns, halt — a prior aborted attempt left state behind. Drop the view manually before continuing.

- [ ] **Step 2: Confirm the underlying tables we depend on exist**

Run:

```sql
SELECT table_name FROM information_schema.tables
WHERE table_schema = 'public' AND table_name IN ('jobs', 'reviews', 'technicians', 'user_roles');
```

Expected: 4 rows. If `reviews` is missing, halt — the spec assumes it exists (it was created in `20260429220000_reviews_phase1.sql`).

- [ ] **Step 3: Snapshot the schemas of the dependency tables**

```sql
SELECT table_name, column_name, data_type
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name IN ('jobs', 'reviews')
  AND column_name IN (
    'id', 'job_id', 'tech_id', 'is_callback', 'is_opportunity', 'completed_at',
    'scheduled_at', 'revenue_amount', 'membership_attached', 'estimate_status',
    'invoice_paid_at', 'hcp_data', 'matched_job_id', 'rating', 'review_at'
  )
ORDER BY table_name, column_name;
```

Save the result to `.planning/spec2a/views-pre-snapshot.json` shaped as:

```json
{
  "captured_at": "<ISO>",
  "project_ref": "jwrpjuqaynownxaoeayi",
  "views_already_present": [],
  "dependency_columns": [
    { "table_name": "jobs", "column_name": "tech_id", "data_type": "uuid" }
    /* one entry per row */
  ]
}
```

- [ ] **Step 4: Commit**

```sh
git add .planning/spec2a/views-pre-snapshot.json
git commit -m "chore(spec2a): pre-snapshot view + dependency-column state on prod"
git push
```

---

## Group B — DB views

### Task 3: Write the forward migration

**Files:**
- Create: `supabase/migrations/20260505140000_spec2a_views.sql`

- [ ] **Step 1: Author the migration file**

Write `supabase/migrations/20260505140000_spec2a_views.sql`:

```sql
-- ============================================================================
-- Spec 2A: Operator Surfaces — DB views
-- v_yesterday_recap        : Per-tech and company-total revenue/jobs/callbacks/cancellations
--                            for the last calendar day.
-- v_jobs_needing_review    : Rule-flagged jobs needing operator attention.
--                            Rules: callback within 30d, attributed review <= 3 within 30d,
--                            invoice refunded.
--
-- Source spec: docs/superpowers/specs/2026-05-05-spec2a-operator-surfaces-design.md
-- Revert: 20260505140001_spec2a_views_revert.sql
-- ============================================================================

BEGIN;

-- ----- v_yesterday_recap -----
-- Yesterday in America/Chicago (CST/CDT). Twins is Madison WI.
-- Returns one row per tech_id (NULL for unassigned) plus one company-total row.
CREATE OR REPLACE VIEW public.v_yesterday_recap AS
WITH yesterday AS (
  SELECT (current_date - INTERVAL '1 day' AT TIME ZONE 'America/Chicago')::date AS the_date,
         (current_date - INTERVAL '1 day') AT TIME ZONE 'America/Chicago' AS day_start,
         (current_date) AT TIME ZONE 'America/Chicago' AS day_end
),
ranged AS (
  SELECT j.*
  FROM public.jobs j, yesterday y
  WHERE j.completed_at IS NOT NULL
    AND j.completed_at >= y.day_start
    AND j.completed_at < y.day_end
)
-- Per-tech rows
SELECT
  r.tech_id,
  COUNT(*) FILTER (WHERE r.is_opportunity)                             AS jobs,
  COALESCE(SUM(r.revenue_amount) FILTER (WHERE r.is_opportunity), 0)   AS revenue,
  COUNT(*) FILTER (WHERE r.is_callback)                                AS callbacks,
  COUNT(*) FILTER (WHERE r.status = 'canceled')                        AS cancellations,
  false AS is_company_total
FROM ranged r
GROUP BY r.tech_id
UNION ALL
-- Company-total row (tech_id NULL, is_company_total = true)
SELECT
  NULL::uuid AS tech_id,
  COUNT(*) FILTER (WHERE r.is_opportunity)                             AS jobs,
  COALESCE(SUM(r.revenue_amount) FILTER (WHERE r.is_opportunity), 0)   AS revenue,
  COUNT(*) FILTER (WHERE r.is_callback)                                AS callbacks,
  COUNT(*) FILTER (WHERE r.status = 'canceled')                        AS cancellations,
  true AS is_company_total
FROM ranged r;

GRANT SELECT ON public.v_yesterday_recap TO authenticated, service_role;

-- ----- v_jobs_needing_review -----
-- A job is flagged if ANY of these hold:
--   1. is_callback = true AND completed_at within last 30 days
--   2. there exists an attributed review with rating <= 3 in last 30 days for this job/tech
--   3. hcp_data->>'refunded_amount' > 0 (any time)
--
-- One row per job per reason (a job can appear up to 3 times if it hits multiple rules).
-- The frontend dedupes / merges in the hook layer.
CREATE OR REPLACE VIEW public.v_jobs_needing_review AS
WITH window_def AS (
  SELECT (now() - INTERVAL '30 days') AS thirty_days_ago
)

-- Reason 1: callback within 30 days
SELECT
  j.id                                               AS job_db_id,
  j.job_id                                           AS hcp_job_id,
  j.tech_id,
  j.scheduled_at,
  j.completed_at,
  j.revenue_amount,
  COALESCE(j.hcp_data->'customer'->>'first_name', '') || ' ' ||
    COALESCE(j.hcp_data->'customer'->>'last_name', '')      AS customer_name,
  'callback_30d'::text                               AS reason,
  'Callback flagged in HCP within last 30 days'      AS reason_detail,
  GREATEST(0, EXTRACT(DAY FROM now() - j.completed_at)::int) AS age_days
FROM public.jobs j, window_def w
WHERE j.is_callback = true
  AND j.completed_at IS NOT NULL
  AND j.completed_at >= w.thirty_days_ago

UNION ALL

-- Reason 2: low rating attributed to this tech in last 30 days, joined to the job
SELECT
  j.id                                               AS job_db_id,
  j.job_id                                           AS hcp_job_id,
  j.tech_id,
  j.scheduled_at,
  j.completed_at,
  j.revenue_amount,
  COALESCE(j.hcp_data->'customer'->>'first_name', '') || ' ' ||
    COALESCE(j.hcp_data->'customer'->>'last_name', '')      AS customer_name,
  'low_rating'::text                                 AS reason,
  'Customer review ' || r.rating || '/5: ' ||
    COALESCE(SUBSTRING(r.comment FROM 1 FOR 80), '(no comment)') AS reason_detail,
  GREATEST(0, EXTRACT(DAY FROM now() - r.review_at)::int)        AS age_days
FROM public.reviews r
JOIN public.jobs j ON r.matched_job_id::text = j.job_id  /* matched_job_id is integer in payroll_jobs in some schemas; cast for safety */
JOIN window_def w ON true
WHERE r.rating <= 3
  AND r.review_at >= w.thirty_days_ago

UNION ALL

-- Reason 3: invoice refunded
SELECT
  j.id                                               AS job_db_id,
  j.job_id                                           AS hcp_job_id,
  j.tech_id,
  j.scheduled_at,
  j.completed_at,
  j.revenue_amount,
  COALESCE(j.hcp_data->'customer'->>'first_name', '') || ' ' ||
    COALESCE(j.hcp_data->'customer'->>'last_name', '')      AS customer_name,
  'refunded'::text                                   AS reason,
  'Invoice refunded: $' ||
    COALESCE((j.hcp_data->>'refunded_amount')::numeric / 100, 0)::text AS reason_detail,
  GREATEST(0, EXTRACT(DAY FROM now() - j.completed_at)::int) AS age_days
FROM public.jobs j
WHERE (j.hcp_data->>'refunded_amount')::numeric > 0
  AND j.completed_at IS NOT NULL;

GRANT SELECT ON public.v_jobs_needing_review TO authenticated, service_role;

COMMIT;
```

- [ ] **Step 2: Sanity-check the SQL**

```sh
test -f supabase/migrations/20260505140000_spec2a_views.sql && wc -l supabase/migrations/20260505140000_spec2a_views.sql
```

Expected: file exists, ~110 lines.

- [ ] **Step 3: Commit (do NOT apply yet)**

```sh
git add supabase/migrations/20260505140000_spec2a_views.sql
git commit -m "feat(spec2a): forward migration — v_yesterday_recap + v_jobs_needing_review (NOT yet applied)"
git push
```

---

### Task 4: Transaction-rollback dry-run on prod

**Files:** none modified.

- [ ] **Step 1: Apply forward migration inside a transaction, verify, ROLLBACK**

Use Supabase MCP `execute_sql` with `project_id="jwrpjuqaynownxaoeayi"`:

```sql
BEGIN;

CREATE OR REPLACE VIEW public.v_yesterday_recap AS
WITH yesterday AS (
  SELECT (current_date - INTERVAL '1 day' AT TIME ZONE 'America/Chicago')::date AS the_date,
         (current_date - INTERVAL '1 day') AT TIME ZONE 'America/Chicago' AS day_start,
         (current_date) AT TIME ZONE 'America/Chicago' AS day_end
),
ranged AS (
  SELECT j.* FROM public.jobs j, yesterday y
  WHERE j.completed_at IS NOT NULL
    AND j.completed_at >= y.day_start AND j.completed_at < y.day_end
)
SELECT
  r.tech_id,
  COUNT(*) FILTER (WHERE r.is_opportunity)                             AS jobs,
  COALESCE(SUM(r.revenue_amount) FILTER (WHERE r.is_opportunity), 0)   AS revenue,
  COUNT(*) FILTER (WHERE r.is_callback)                                AS callbacks,
  COUNT(*) FILTER (WHERE r.status = 'canceled')                        AS cancellations,
  false AS is_company_total
FROM ranged r GROUP BY r.tech_id
UNION ALL
SELECT NULL::uuid, COUNT(*) FILTER (WHERE r.is_opportunity),
  COALESCE(SUM(r.revenue_amount) FILTER (WHERE r.is_opportunity), 0),
  COUNT(*) FILTER (WHERE r.is_callback),
  COUNT(*) FILTER (WHERE r.status = 'canceled'),
  true
FROM ranged r;

CREATE OR REPLACE VIEW public.v_jobs_needing_review AS
/* paste the full v_jobs_needing_review SQL from Task 3 here */
;

-- verify both views exist and SELECT count works
SELECT 'recap_count' AS check_name, COUNT(*)::text AS value FROM public.v_yesterday_recap
UNION ALL
SELECT 'needing_review_count', COUNT(*)::text FROM public.v_jobs_needing_review;

ROLLBACK;
```

If both checks return numeric counts, the views are syntactically correct and the joins resolve. If any error, halt and fix the migration.

If rollback executes cleanly, prod is unchanged.

- [ ] **Step 2: Verify rollback was clean**

```sql
SELECT table_name FROM information_schema.views
WHERE table_schema = 'public' AND table_name IN ('v_yesterday_recap','v_jobs_needing_review');
```

Expected: empty.

- [ ] **Step 3: Note the dry-run result in the planning dir**

Append to `.planning/spec2a/README.md`:

```sh
cat >> .planning/spec2a/README.md <<EOF

## Dry-run results

- Forward migration dry-run on prod: PASSED ($(date -u +%FT%TZ))
- Both views compile and return counts.
- Rollback clean — no view persists in prod.
EOF
git add .planning/spec2a/README.md
git commit -m "chore(spec2a): forward-migration dry-run passed on prod (rolled back)"
git push
```

---

### Task 5: Write the revert migration + roundtrip dry-run

**Files:**
- Create: `supabase/migrations/20260505140001_spec2a_views_revert.sql`

- [ ] **Step 1: Author the revert SQL**

Write `supabase/migrations/20260505140001_spec2a_views_revert.sql`:

```sql
-- ============================================================================
-- Spec 2A — REVERT
-- Drops the views created in 20260505140000_spec2a_views.sql.
-- Pre-Spec-2A state had no such views (confirmed in
-- .planning/spec2a/views-pre-snapshot.json).
-- ============================================================================

BEGIN;
DROP VIEW IF EXISTS public.v_jobs_needing_review;
DROP VIEW IF EXISTS public.v_yesterday_recap;
COMMIT;
```

- [ ] **Step 2: Roundtrip dry-run on prod**

Use Supabase MCP `execute_sql`:

```sql
BEGIN;

-- forward (from Task 3)
CREATE OR REPLACE VIEW public.v_yesterday_recap AS /* full SQL from Task 3 */;
CREATE OR REPLACE VIEW public.v_jobs_needing_review AS /* full SQL from Task 3 */;

-- revert (from Step 1 above)
DROP VIEW IF EXISTS public.v_jobs_needing_review;
DROP VIEW IF EXISTS public.v_yesterday_recap;

-- Verify zero views remain
SELECT 'views_after_revert' AS check_name, COUNT(*)::text AS value
FROM information_schema.views
WHERE table_schema = 'public' AND table_name IN ('v_yesterday_recap','v_jobs_needing_review');

ROLLBACK;
```

Expected: `views_after_revert = 0`. If the count is non-zero, the revert is incomplete.

- [ ] **Step 3: Commit the revert migration**

```sh
git add supabase/migrations/20260505140001_spec2a_views_revert.sql
git commit -m "feat(spec2a): revert migration for SQL views — roundtrip dry-run passed"
git push
```

---

### Task 6: Apply forward migration to prod

**Files:** none modified.

- [ ] **Step 1: Apply via MCP**

Use `mcp__a13384b5-3518-4c7c-9b61-a7f2786de7db__apply_migration`:
- `project_id`: `jwrpjuqaynownxaoeayi`
- `name`: `spec2a_views`
- `query`: full contents of `supabase/migrations/20260505140000_spec2a_views.sql`

- [ ] **Step 2: Verify both views exist and return data**

```sql
SELECT 'recap_rows' AS check_name, COUNT(*)::text AS value FROM public.v_yesterday_recap
UNION ALL
SELECT 'needing_review_rows', COUNT(*)::text FROM public.v_jobs_needing_review
UNION ALL
SELECT 'recap_grants', string_agg(DISTINCT privilege_type, ',') FROM information_schema.role_table_grants
WHERE table_schema='public' AND table_name='v_yesterday_recap' AND grantee IN ('authenticated','service_role')
UNION ALL
SELECT 'review_grants', string_agg(DISTINCT privilege_type, ',') FROM information_schema.role_table_grants
WHERE table_schema='public' AND table_name='v_jobs_needing_review' AND grantee IN ('authenticated','service_role');
```

Expected: `recap_rows` and `needing_review_rows` return integer counts (could be 0 if there's no recent data — fine). Grants strings include `SELECT`.

If any query errors with `relation does not exist`, the apply silently failed; investigate.

---

## Group C — Hooks

### Task 7: Extend `use-rev-rise-data.ts` — add `mode` param + wire reviews

**Files:**
- Modify: `src/hooks/use-rev-rise-data.ts` (currently 314 lines)

- [ ] **Step 1: Add `mode` parameter + dateRange override**

At the top of the hook function in `src/hooks/use-rev-rise-data.ts`, change the signature from:

```ts
export function useRevRiseData(techId?: string, dateRange?: DateRange) {
```

to:

```ts
export type RevRiseMode = 'call-window' | 'daily';

export function useRevRiseData(
  techId?: string,
  dateRange?: DateRange,
  mode: RevRiseMode = 'call-window',
) {
```

Then immediately after the existing `dateRange` setup but before any DB queries, add:

```ts
const effectiveRange: DateRange = mode === 'daily'
  ? (() => {
      const now = new Date();
      const yest = new Date(now);
      yest.setDate(yest.getDate() - 1);
      yest.setHours(0, 0, 0, 0);
      const todayStart = new Date(now);
      todayStart.setHours(0, 0, 0, 0);
      return { from: yest, to: todayStart };
    })()
  : (dateRange ?? { from: undefined, to: undefined });
```

Replace every downstream reference to `dateRange` with `effectiveRange`.

- [ ] **Step 2: Wire reviews/ratings query (replaces stubs at lines 143-144)**

Inside the same hook, find the per-tech KPI computation (around line 100-150 in the current file). After computing other per-tech aggregates and before the `return` block that produces a `TechKPIRow`, add:

```ts
// Reviews: count + avg rating attributed to this tech in `effectiveRange`.
// `public.reviews` is GBP reviews; `tech_id` is set when attribution succeeded
// (ghl_redirect, fuzzy_match, or manual). Unattributed reviews don't count.
const { data: reviewRows } = await supabase
  .from('reviews')
  .select('rating, review_at, tech_id')
  .eq('tech_id', tech.id)
  .gte('review_at', effectiveRange.from?.toISOString() ?? new Date(0).toISOString())
  .lte('review_at', effectiveRange.to?.toISOString() ?? new Date().toISOString());

const reviewCount = reviewRows?.length ?? 0;
const avgRating = reviewCount === 0
  ? 0
  : (reviewRows!.reduce((s, r) => s + (r.rating ?? 0), 0) / reviewCount);
```

Then replace the stubbed lines 143-144 (`reviews: 0, // TODO: wire in review data` and `avgRating: 0,`) with:

```ts
reviews: reviewCount,
avgRating: avgRating,
```

Keep the field order in `TechKPIRow` unchanged.

- [ ] **Step 3: Update the queryKey to include `mode`**

In the `useQuery` call inside this hook, change the queryKey from something like:

```ts
queryKey: ['rev-rise-data', techId, dateRange]
```

to:

```ts
queryKey: ['rev-rise-data', techId, effectiveRange, mode]
```

This ensures TanStack Query distinguishes call-window vs daily caches.

- [ ] **Step 4: Update existing call sites in `RevRiseDashboard.tsx`**

In `src/pages/RevRiseDashboard.tsx`, find every `useRevRiseData(undefined, dateRange)` call and leave the third argument off — `mode` defaults to `call-window` (current behavior unchanged). The Task 9-12 work on RevRiseDashboard.tsx will pass `mode` explicitly.

- [ ] **Step 5: Type-check + build**

```sh
cd /Users/daniel/twins-dashboard/twins-dash
npm run build 2>&1 | tail -5
```

Expected: build passes.

- [ ] **Step 6: Commit**

```sh
git add src/hooks/use-rev-rise-data.ts
git commit -m "feat(spec2a): add mode param + wire reviews/ratings from public.reviews into use-rev-rise-data (replaces TODO stubs)"
git push
```

---

### Task 8: Create `use-yesterday-recap.ts`

**Files:**
- Create: `src/hooks/use-yesterday-recap.ts`

- [ ] **Step 1: Write the hook**

Write `src/hooks/use-yesterday-recap.ts`:

```ts
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";

export interface YesterdayRecapRow {
  tech_id: string | null;
  jobs: number;
  revenue: number;
  callbacks: number;
  cancellations: number;
  is_company_total: boolean;
}

export interface YesterdayRecap {
  perTech: YesterdayRecapRow[];
  companyTotal: YesterdayRecapRow | null;
}

export function useYesterdayRecap() {
  return useQuery<YesterdayRecap>({
    queryKey: ['yesterday-recap'],
    refetchInterval: 60_000,
    queryFn: async () => {
      const { data, error } = await supabase
        .from('v_yesterday_recap')
        .select('tech_id, jobs, revenue, callbacks, cancellations, is_company_total');
      if (error) throw error;

      const rows = (data ?? []) as YesterdayRecapRow[];
      const companyTotal = rows.find((r) => r.is_company_total) ?? null;
      const perTech = rows.filter((r) => !r.is_company_total);
      return { perTech, companyTotal };
    },
  });
}
```

- [ ] **Step 2: Type-check**

```sh
npm run build 2>&1 | tail -5
```

Expected: build passes (the new hook isn't referenced yet but type-checks against the view's return shape).

- [ ] **Step 3: Commit**

```sh
git add src/hooks/use-yesterday-recap.ts
git commit -m "feat(spec2a): use-yesterday-recap hook reading v_yesterday_recap"
git push
```

---

### Task 9: Create `use-jobs-needing-review.ts`

**Files:**
- Create: `src/hooks/use-jobs-needing-review.ts`

- [ ] **Step 1: Write the hook**

Write `src/hooks/use-jobs-needing-review.ts`:

```ts
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";

export type JobReviewReason = 'callback_30d' | 'low_rating' | 'refunded';

export interface JobNeedingReview {
  job_db_id: string;
  hcp_job_id: string;
  tech_id: string | null;
  scheduled_at: string | null;
  completed_at: string | null;
  revenue_amount: number;
  customer_name: string;
  reason: JobReviewReason;
  reason_detail: string;
  age_days: number;
}

export function useJobsNeedingReview(opts?: { techId?: string }) {
  return useQuery<JobNeedingReview[]>({
    queryKey: ['jobs-needing-review', opts?.techId ?? 'all'],
    refetchInterval: 60_000,
    queryFn: async () => {
      let q = supabase
        .from('v_jobs_needing_review')
        .select('*')
        .order('age_days', { ascending: true })
        .limit(200);
      if (opts?.techId) q = q.eq('tech_id', opts.techId);
      const { data, error } = await q;
      if (error) throw error;
      return (data ?? []) as JobNeedingReview[];
    },
  });
}
```

- [ ] **Step 2: Build**

```sh
npm run build 2>&1 | tail -5
```

Expected: passes.

- [ ] **Step 3: Commit**

```sh
git add src/hooks/use-jobs-needing-review.ts
git commit -m "feat(spec2a): use-jobs-needing-review hook reading v_jobs_needing_review"
git push
```

---

### Task 10: Create `use-drilldown-jobs.ts`

**Files:**
- Create: `src/hooks/use-drilldown-jobs.ts`

- [ ] **Step 1: Write the hook**

Write `src/hooks/use-drilldown-jobs.ts`:

```ts
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { DateRange } from "react-day-picker";

export type DrilldownKpi = 'revenue' | 'closing' | 'callback' | 'membership';

export interface DrilldownFilter {
  kpi: DrilldownKpi;
  techId?: string;
  dateRange?: DateRange;
  /** Skip the query entirely — used to keep the hook always-mounted but disabled */
  enabled?: boolean;
}

export interface DrilldownJobRow {
  job_db_id: string;
  job_id: string;
  tech_id: string | null;
  scheduled_at: string | null;
  completed_at: string | null;
  revenue_amount: number;
  is_opportunity: boolean;
  is_callback: boolean;
  estimate_status: string | null;
  membership_attached: boolean;
  hcp_data: any;
  customer_name: string;
}

export function useDrilldownJobs(f: DrilldownFilter) {
  return useQuery<DrilldownJobRow[]>({
    enabled: f.enabled !== false,
    queryKey: ['drilldown-jobs', f.kpi, f.techId ?? 'all', f.dateRange ?? 'all-time'],
    queryFn: async () => {
      let q = supabase
        .from('jobs')
        .select(
          'id, job_id, tech_id, scheduled_at, completed_at, revenue_amount, ' +
          'is_opportunity, is_callback, estimate_status, membership_attached, hcp_data',
        )
        .limit(500);

      if (f.techId) q = q.eq('tech_id', f.techId);
      if (f.dateRange?.from) q = q.gte('completed_at', f.dateRange.from.toISOString());
      if (f.dateRange?.to)   q = q.lte('completed_at', f.dateRange.to.toISOString());

      switch (f.kpi) {
        case 'revenue':
          q = q.not('completed_at', 'is', null).eq('is_opportunity', true);
          break;
        case 'closing':
          q = q.eq('is_opportunity', true);
          break;
        case 'callback':
          q = q.eq('is_callback', true);
          break;
        case 'membership':
          q = q.eq('membership_attached', true);
          break;
      }

      const { data, error } = await q.order('completed_at', { ascending: false, nullsFirst: false });
      if (error) throw error;

      return (data ?? []).map((r: any): DrilldownJobRow => ({
        job_db_id: r.id,
        job_id: r.job_id,
        tech_id: r.tech_id,
        scheduled_at: r.scheduled_at,
        completed_at: r.completed_at,
        revenue_amount: Number(r.revenue_amount ?? 0),
        is_opportunity: !!r.is_opportunity,
        is_callback: !!r.is_callback,
        estimate_status: r.estimate_status,
        membership_attached: !!r.membership_attached,
        hcp_data: r.hcp_data,
        customer_name: [
          r.hcp_data?.customer?.first_name,
          r.hcp_data?.customer?.last_name,
        ].filter(Boolean).join(' ').trim() || '(no customer)',
      }));
    },
  });
}
```

- [ ] **Step 2: Build**

```sh
npm run build 2>&1 | tail -5
```

Expected: passes.

- [ ] **Step 3: Commit**

```sh
git add src/hooks/use-drilldown-jobs.ts
git commit -m "feat(spec2a): use-drilldown-jobs hook — generic KPI→jobs mapping for revenue/closing/callback/membership"
git push
```

---

## Group D — DrilldownSheet component

### Task 11: Create `DrilldownSheet.tsx`

**Files:**
- Create: `src/components/dashboard/DrilldownSheet.tsx`

- [ ] **Step 1: Write the component**

Write `src/components/dashboard/DrilldownSheet.tsx`:

```tsx
import { useMemo, useRef } from 'react';
import { useVirtualizer } from '@tanstack/react-virtual';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useDrilldownJobs, type DrilldownKpi } from '@/hooks/use-drilldown-jobs';
import type { DateRange } from 'react-day-picker';

const HCP_JOB_URL = (jobId: string) =>
  `https://pro.housecallpro.com/app/customers/jobs/${encodeURIComponent(jobId)}`;

const KPI_LABELS: Record<DrilldownKpi, string> = {
  revenue: 'Revenue',
  closing: 'Opportunities (sold + unsold)',
  callback: 'Callbacks',
  membership: 'Memberships sold',
};

const fmtMoney = (n: number) =>
  n >= 1000 ? `$${(n / 1000).toFixed(1)}k` : `$${Math.round(n)}`;

const fmtDate = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

export interface DrilldownSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  kpi: DrilldownKpi;
  techId?: string;
  techName?: string;
  dateRange?: DateRange;
}

export function DrilldownSheet(p: DrilldownSheetProps) {
  const { data, isLoading, error } = useDrilldownJobs({
    enabled: p.open,
    kpi: p.kpi,
    techId: p.techId,
    dateRange: p.dateRange,
  });

  const rows = useMemo(() => data ?? [], [data]);
  const parentRef = useRef<HTMLDivElement>(null);
  const v = useVirtualizer({
    count: rows.length,
    getScrollElement: () => parentRef.current,
    estimateSize: () => 56,
    overscan: 8,
  });

  const subtitle = [
    p.techName,
    p.dateRange?.from ? fmtDate(p.dateRange.from.toISOString()) : null,
    p.dateRange?.to ? `→ ${fmtDate(p.dateRange.to.toISOString())}` : null,
  ].filter(Boolean).join(' · ');

  return (
    <Sheet open={p.open} onOpenChange={p.onOpenChange}>
      <SheetContent side="bottom" className="rounded-t-2xl max-h-[85vh] overflow-hidden flex flex-col">
        <SheetHeader>
          <SheetTitle className="flex items-baseline gap-2">
            <span>{KPI_LABELS[p.kpi]}</span>
            {subtitle && <span className="text-sm font-normal text-muted-foreground">{subtitle}</span>}
            <span className="ml-auto text-sm font-normal text-muted-foreground">
              {isLoading ? 'loading…' : `${rows.length} jobs`}
            </span>
          </SheetTitle>
        </SheetHeader>

        {error && (
          <div className="p-4 bg-destructive/10 text-destructive text-sm rounded">
            Failed to load: {String(error)}
          </div>
        )}

        {!isLoading && !error && rows.length === 0 && (
          <div className="p-8 text-center text-sm text-muted-foreground">
            No jobs match this filter.
          </div>
        )}

        <div ref={parentRef} className="flex-1 overflow-y-auto">
          <div style={{ height: v.getTotalSize(), position: 'relative' }}>
            {v.getVirtualItems().map((vi) => {
              const r = rows[vi.index];
              return (
                <a
                  key={r.job_db_id}
                  href={HCP_JOB_URL(r.job_id)}
                  target="_blank"
                  rel="noreferrer noopener"
                  style={{ position: 'absolute', top: 0, left: 0, right: 0, transform: `translateY(${vi.start}px)`, height: vi.size }}
                  className="grid grid-cols-[1fr_auto_auto_auto] gap-3 items-center px-4 py-2 hover:bg-accent border-b text-sm"
                >
                  <span className="truncate font-medium">{r.customer_name}</span>
                  <span className="text-muted-foreground tabular-nums">{fmtDate(r.completed_at ?? r.scheduled_at)}</span>
                  <span className="tabular-nums font-semibold">{fmtMoney(r.revenue_amount)}</span>
                  <span className="text-xs text-muted-foreground">{r.estimate_status ?? '—'}</span>
                </a>
              );
            })}
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
```

- [ ] **Step 2: Build**

```sh
npm run build 2>&1 | tail -5
```

Expected: passes.

- [ ] **Step 3: Commit**

```sh
git add src/components/dashboard/DrilldownSheet.tsx
git commit -m "feat(spec2a): DrilldownSheet — virtualized job list, click row opens HCP, reuses Twins Sheet primitive"
git push
```

---

## Group E — Rev & Rise daily-mode

### Task 12: Create `ModeToggle.tsx`

**Files:**
- Create: `src/components/rev-rise/ModeToggle.tsx`

- [ ] **Step 1: Write the toggle**

Write `src/components/rev-rise/ModeToggle.tsx`:

```tsx
import { useSearchParams } from 'react-router-dom';
import { Button } from '@/components/ui/button';

export type RevRiseMode = 'call-window' | 'daily';

export function useRevRiseMode(): [RevRiseMode, (m: RevRiseMode) => void] {
  const [params, setParams] = useSearchParams();
  const raw = params.get('mode');
  const mode: RevRiseMode = raw === 'daily' ? 'daily' : 'call-window';

  const set = (m: RevRiseMode) => {
    const p = new URLSearchParams(params);
    if (m === 'call-window') p.delete('mode');
    else p.set('mode', m);
    setParams(p, { replace: true });
  };

  return [mode, set];
}

export function ModeToggle({ value, onChange }: { value: RevRiseMode; onChange: (m: RevRiseMode) => void }) {
  return (
    <div className="inline-flex rounded-lg border bg-background p-0.5" role="tablist" aria-label="Rev & Rise mode">
      <Button
        role="tab"
        aria-selected={value === 'call-window'}
        size="sm"
        variant={value === 'call-window' ? 'default' : 'ghost'}
        onClick={() => onChange('call-window')}
      >
        Call window
      </Button>
      <Button
        role="tab"
        aria-selected={value === 'daily'}
        size="sm"
        variant={value === 'daily' ? 'default' : 'ghost'}
        onClick={() => onChange('daily')}
      >
        Daily
      </Button>
    </div>
  );
}
```

- [ ] **Step 2: Build**

```sh
npm run build 2>&1 | tail -5
```

Expected: passes.

- [ ] **Step 3: Commit**

```sh
git add src/components/rev-rise/ModeToggle.tsx
git commit -m "feat(spec2a): ModeToggle component + useRevRiseMode hook (URL-param-driven)"
git push
```

---

### Task 13: Create `YesterdayRecap.tsx`

**Files:**
- Create: `src/components/rev-rise/YesterdayRecap.tsx`

- [ ] **Step 1: Write the component**

Write `src/components/rev-rise/YesterdayRecap.tsx`:

```tsx
import { useYesterdayRecap } from '@/hooks/use-yesterday-recap';

const fmtMoney = (n: number) =>
  n >= 1000 ? `$${(n / 1000).toFixed(1)}k` : `$${Math.round(n)}`;

export function YesterdayRecap() {
  const { data, isLoading, error } = useYesterdayRecap();
  const t = data?.companyTotal;

  return (
    <section className="rounded-2xl border bg-card p-4">
      <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3">
        Yesterday
      </h3>
      {isLoading && <div className="text-sm text-muted-foreground">Loading…</div>}
      {error && <div className="text-sm text-destructive">Failed to load yesterday's recap</div>}
      {!isLoading && !error && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <Stat label="Revenue" value={fmtMoney(t?.revenue ?? 0)} />
          <Stat label="Jobs"        value={String(t?.jobs ?? 0)} />
          <Stat label="Callbacks"   value={String(t?.callbacks ?? 0)} tone={(t?.callbacks ?? 0) > 0 ? 'warn' : 'neutral'} />
          <Stat label="Cancellations" value={String(t?.cancellations ?? 0)} tone={(t?.cancellations ?? 0) > 0 ? 'warn' : 'neutral'} />
        </div>
      )}
    </section>
  );
}

function Stat({ label, value, tone = 'neutral' }: { label: string; value: string; tone?: 'neutral' | 'warn' }) {
  return (
    <div>
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className={`text-2xl font-bold tabular-nums ${tone === 'warn' ? 'text-amber-600' : ''}`}>{value}</div>
    </div>
  );
}
```

- [ ] **Step 2: Build + commit**

```sh
npm run build 2>&1 | tail -5
git add src/components/rev-rise/YesterdayRecap.tsx
git commit -m "feat(spec2a): YesterdayRecap — single-tile yesterday revenue/jobs/callbacks/cancellations"
git push
```

---

### Task 14: Create `DispatchQueue.tsx`

**Files:**
- Create: `src/components/rev-rise/DispatchQueue.tsx`

- [ ] **Step 1: Look at the existing DayAhead component for the rendering pattern**

```sh
cat src/components/rev-rise/DayAhead.tsx | head -60
```

Note the per-tech rendering pattern. We'll reuse the same shape but for **today** instead of tomorrow.

- [ ] **Step 2: Write the component**

Write `src/components/rev-rise/DispatchQueue.tsx`:

```tsx
import { useDayAheadJobs } from '@/hooks/use-day-ahead-jobs';

/**
 * Today's dispatch queue — same shape as DayAhead but for today's date.
 * Shows scheduled jobs grouped by tech, plus an unassigned bucket.
 */
export function DispatchQueue() {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const { data } = useDayAheadJobs(today);

  const groups = data?.byTech ?? [];
  const unassigned = data?.unassigned ?? [];

  return (
    <section className="rounded-2xl border bg-card p-4">
      <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3">
        Today's dispatch
      </h3>

      {groups.length === 0 && unassigned.length === 0 && (
        <div className="text-sm text-muted-foreground">No jobs scheduled for today.</div>
      )}

      <ul className="space-y-2">
        {groups.map((g) => (
          <li key={g.techId} className="flex items-center justify-between gap-3 text-sm">
            <span className="truncate font-medium">{g.techName}</span>
            <span className="text-muted-foreground tabular-nums">{g.jobs.length} jobs</span>
          </li>
        ))}
        {unassigned.length > 0 && (
          <li className="flex items-center justify-between gap-3 text-sm border-t pt-2">
            <span className="font-medium text-amber-600">Unassigned</span>
            <span className="tabular-nums">{unassigned.length} jobs</span>
          </li>
        )}
      </ul>
    </section>
  );
}
```

NOTE: this assumes `useDayAheadJobs` already returns `{ byTech, unassigned }` shape. If its actual return shape differs, adapt this component accordingly during execution — and STOP if any unexpected shape change is needed; report and ask.

- [ ] **Step 3: Confirm `useDayAheadJobs` shape**

```sh
sed -n '1,80p' src/hooks/use-day-ahead-jobs.ts
```

If the shape doesn't include `byTech`/`unassigned`, write a small wrapper helper at the top of `DispatchQueue.tsx` that derives them from whatever shape it returns. Halt and surface to the operator if the data isn't there at all.

- [ ] **Step 4: Build + commit**

```sh
npm run build 2>&1 | tail -5
git add src/components/rev-rise/DispatchQueue.tsx
git commit -m "feat(spec2a): DispatchQueue — today's scheduled jobs grouped by tech + unassigned bucket"
git push
```

---

### Task 15: Create `JobsNeedingReview.tsx`

**Files:**
- Create: `src/components/rev-rise/JobsNeedingReview.tsx`

- [ ] **Step 1: Write the component**

Write `src/components/rev-rise/JobsNeedingReview.tsx`:

```tsx
import { useJobsNeedingReview, type JobReviewReason } from '@/hooks/use-jobs-needing-review';

const REASON_LABEL: Record<JobReviewReason, string> = {
  callback_30d: 'Callback',
  low_rating:   'Low rating',
  refunded:     'Refunded',
};

const REASON_TONE: Record<JobReviewReason, string> = {
  callback_30d: 'bg-amber-100 text-amber-900',
  low_rating:   'bg-red-100 text-red-900',
  refunded:     'bg-purple-100 text-purple-900',
};

const HCP_JOB_URL = (jobId: string) =>
  `https://pro.housecallpro.com/app/customers/jobs/${encodeURIComponent(jobId)}`;

const fmtMoney = (n: number) =>
  n >= 1000 ? `$${(n / 1000).toFixed(1)}k` : `$${Math.round(n)}`;

export function JobsNeedingReview() {
  const { data, isLoading, error } = useJobsNeedingReview();

  return (
    <section className="rounded-2xl border bg-card p-4">
      <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3">
        Jobs needing your eyes
        {data && <span className="ml-2 text-xs font-normal">({data.length})</span>}
      </h3>
      {isLoading && <div className="text-sm text-muted-foreground">Loading…</div>}
      {error && <div className="text-sm text-destructive">Failed to load</div>}
      {!isLoading && !error && (data?.length ?? 0) === 0 && (
        <div className="text-sm text-muted-foreground">Nothing flagged. </div>
      )}
      <ul className="divide-y">
        {(data ?? []).map((j) => (
          <li key={`${j.job_db_id}-${j.reason}`} className="py-2 flex items-center gap-3 text-sm">
            <span className={`text-xs px-2 py-0.5 rounded ${REASON_TONE[j.reason]}`}>
              {REASON_LABEL[j.reason]}
            </span>
            <a
              href={HCP_JOB_URL(j.hcp_job_id)}
              target="_blank"
              rel="noreferrer noopener"
              className="flex-1 truncate hover:underline font-medium"
              title={j.reason_detail}
            >
              {j.customer_name}
            </a>
            <span className="text-xs text-muted-foreground">{j.age_days}d ago</span>
            <span className="tabular-nums font-semibold">{fmtMoney(j.revenue_amount)}</span>
          </li>
        ))}
      </ul>
    </section>
  );
}
```

- [ ] **Step 2: Build + commit**

```sh
npm run build 2>&1 | tail -5
git add src/components/rev-rise/JobsNeedingReview.tsx
git commit -m "feat(spec2a): JobsNeedingReview — rule-flagged queue (callback/low-rating/refund) with click-to-HCP"
git push
```

---

### Task 16: Create `MissedMoneyPanel.tsx` (placeholder for Spec 2B)

**Files:**
- Create: `src/components/rev-rise/MissedMoneyPanel.tsx`

- [ ] **Step 1: Write the placeholder component**

Write `src/components/rev-rise/MissedMoneyPanel.tsx`:

```tsx
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

const fmtMoney = (n: number) =>
  n >= 1000 ? `$${(n / 1000).toFixed(1)}k` : `$${Math.round(n)}`;

function useOpenEstimatesPlaceholder() {
  return useQuery({
    queryKey: ['missed-money-placeholder'],
    refetchInterval: 60_000,
    queryFn: async () => {
      const cutoff = new Date(Date.now() - 48 * 60 * 60 * 1000).toISOString();
      const { data, error } = await supabase
        .from('jobs')
        .select('revenue_amount, created_at, estimate_status')
        .eq('estimate_status', 'open')
        .lte('created_at', cutoff);
      if (error) throw error;
      const rows = data ?? [];
      const total = rows.reduce((s, r: any) => s + Number(r.revenue_amount ?? 0), 0);
      return { count: rows.length, total };
    },
  });
}

export function MissedMoneyPanel() {
  const { data } = useOpenEstimatesPlaceholder();
  const count = data?.count ?? 0;
  const total = data?.total ?? 0;

  return (
    <section className="rounded-2xl border bg-card p-4">
      <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide mb-3">
        Missed money (open estimates &gt; 48h)
      </h3>
      <div className="flex items-baseline gap-4">
        <div>
          <div className="text-xs text-muted-foreground">Count</div>
          <div className="text-2xl font-bold tabular-nums">{count}</div>
        </div>
        <div>
          <div className="text-xs text-muted-foreground">Total $</div>
          <div className="text-2xl font-bold tabular-nums">{fmtMoney(total)}</div>
        </div>
        <button
          disabled
          className="ml-auto text-xs px-3 py-1.5 rounded border text-muted-foreground cursor-not-allowed"
          title="Full follow-up surface ships in Spec 2B"
        >
          See all (Spec 2B)
        </button>
      </div>
    </section>
  );
}
```

- [ ] **Step 2: Build + commit**

```sh
npm run build 2>&1 | tail -5
git add src/components/rev-rise/MissedMoneyPanel.tsx
git commit -m "feat(spec2a): MissedMoneyPanel placeholder — count + total of open estimates >48h, full surface deferred to 2B"
git push
```

---

### Task 17: Wire ModeToggle + daily blocks into `RevRiseDashboard.tsx`

**Files:**
- Modify: `src/pages/RevRiseDashboard.tsx`

- [ ] **Step 1: Read current structure**

```sh
sed -n '1,60p' src/pages/RevRiseDashboard.tsx
```

Note the imports and the JSX structure (CallWindowHeader, CompanyKpiStrip, WinsBlock, PerTechCards, DayAhead, AiCommentaryPanel).

- [ ] **Step 2: Replace the body with mode-aware rendering**

In `src/pages/RevRiseDashboard.tsx`, change the imports to add:

```tsx
import { useRevRiseMode, ModeToggle } from '@/components/rev-rise/ModeToggle';
import { YesterdayRecap } from '@/components/rev-rise/YesterdayRecap';
import { DispatchQueue } from '@/components/rev-rise/DispatchQueue';
import { JobsNeedingReview } from '@/components/rev-rise/JobsNeedingReview';
import { MissedMoneyPanel } from '@/components/rev-rise/MissedMoneyPanel';
```

Add at the top of the `RevRiseDashboard` function body, near the existing `useState`/`useMemo` calls:

```tsx
const [mode, setMode] = useRevRiseMode();
```

Pass `mode` into `useRevRiseData`:

```tsx
const { data, refetch } = useRevRiseData(undefined, dateRange, mode);
```

In the JSX, immediately after `<CallWindowHeader … />` and before `<CompanyKpiStrip … />`, insert:

```tsx
<div className="flex justify-end mb-3">
  <ModeToggle value={mode} onChange={setMode} />
</div>

{mode === 'daily' ? (
  <div className="space-y-4">
    <YesterdayRecap />
    <DispatchQueue />
    <JobsNeedingReview />
    <MissedMoneyPanel />
  </div>
) : (
  <>
    {/* original call-window body — keep all existing children unchanged */}
    <CompanyKpiStrip ... />
    <WinsBlock ... />
    <PerTechCards ... />
    <DayAhead ... />
    <AiCommentaryPanel ... />
  </>
)}
```

(Use the actual prop values from the existing JSX — don't change them.)

The result: when `mode === 'daily'`, the page renders the 4 new daily blocks; otherwise it renders the existing call-window body byte-for-byte.

- [ ] **Step 3: Build**

```sh
npm run build 2>&1 | tail -5
```

Expected: passes.

- [ ] **Step 4: Commit**

```sh
git add src/pages/RevRiseDashboard.tsx
git commit -m "feat(spec2a): wire ModeToggle + daily blocks into RevRiseDashboard (default mode unchanged)"
git push
```

---

## Group F — Reviews/ratings UI surface

### Task 18: Add rating chip to `PerTechCards.tsx`

**Files:**
- Modify: `src/components/rev-rise/PerTechCards.tsx`

- [ ] **Step 1: Read current props**

```sh
sed -n '1,30p' src/components/rev-rise/PerTechCards.tsx
```

Note the per-tech card body — that's where the chip goes. Each card has access to a `TechKPIRow` (per the type defined in `use-rev-rise-data.ts`). The hook already populates `reviews` and `avgRating` after Task 7.

- [ ] **Step 2: Add a chip in the per-tech card**

In each per-tech card render block, find the row that shows the open-estimates flag (or wherever per-tech metadata chips live). Add immediately after it:

```tsx
{tech.avgRating > 0 && tech.reviews > 0 && (
  <span
    className="ml-2 inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded bg-yellow-50 border border-yellow-200 text-yellow-900"
    title={`${tech.reviews} reviews in window`}
  >
    ★ {tech.avgRating.toFixed(1)} ({tech.reviews})
  </span>
)}
```

The chip only renders when at least one review exists in the window — avoids "0.0 (0)" noise.

- [ ] **Step 3: Build + commit**

```sh
npm run build 2>&1 | tail -5
git add src/components/rev-rise/PerTechCards.tsx
git commit -m "feat(spec2a): per-tech rating chip on PerTechCards (renders only when reviews > 0)"
git push
```

---

### Task 19: Add rating chip to `TechnicianBreakdown.tsx`

**Files:**
- Modify: `src/components/dashboard/TechnicianBreakdown.tsx`

- [ ] **Step 1: Locate the per-tech card render block**

```sh
grep -n "techName\|TechCard\|<Card" src/components/dashboard/TechnicianBreakdown.tsx | head -10
```

Find the per-tech card render (the per-row JSX that shows tech name, revenue, jobs, etc.).

- [ ] **Step 2: Add the chip**

Inside the per-tech card render, after the tech-name header, add:

```tsx
{tech.avgRating > 0 && tech.reviews > 0 && (
  <span
    className="ml-2 inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded bg-yellow-50 border border-yellow-200 text-yellow-900 align-middle"
    title={`${tech.reviews} reviews in window`}
  >
    ★ {tech.avgRating.toFixed(1)} ({tech.reviews})
  </span>
)}
```

This depends on `tech` having `reviews` and `avgRating` — it does, because Task 7 wired them into the same `TechKPIRow` shape, and `TechnicianBreakdown` consumes that shape via the dashboard hook chain.

If `TechnicianBreakdown` consumes from a *different* hook (e.g. `use-leaderboard-data.ts`) that doesn't expose `reviews/avgRating`, halt — the rating chip there is deferred to a follow-up task. Surface to the operator.

- [ ] **Step 3: Build + commit**

```sh
npm run build 2>&1 | tail -5
git add src/components/dashboard/TechnicianBreakdown.tsx
git commit -m "feat(spec2a): per-tech rating chip on TechnicianBreakdown"
git push
```

---

## Group G — Drilldown wiring

### Task 20: Wire DrilldownSheet into `Index.tsx` KPI tiles

**Files:**
- Modify: `src/pages/Index.tsx`

- [ ] **Step 1: Locate the 4 main KPI tiles**

```sh
grep -n "Revenue\|Closing\|Callback\|Membership" src/pages/Index.tsx | head -20
```

Identify the JSX blocks for the 4 main KPI tiles (revenue, closing %, callback %, membership %).

- [ ] **Step 2: Add drilldown state + sheet rendering**

At the top of the `Index` component body:

```tsx
import { DrilldownSheet } from '@/components/dashboard/DrilldownSheet';
import type { DrilldownKpi } from '@/hooks/use-drilldown-jobs';
// ...
const [drillKpi, setDrillKpi] = useState<DrilldownKpi | null>(null);
```

Make each of the 4 KPI tiles clickable. Wrap each tile's outer element in a button-like container:

```tsx
<button
  type="button"
  onClick={() => setDrillKpi('revenue')}
  className="text-left w-full hover:opacity-90 transition-opacity"
>
  {/* existing tile content unchanged */}
</button>
```

Repeat for `'closing'`, `'callback'`, `'membership'` on the appropriate tiles.

At the bottom of the `Index` JSX, before the closing tag, render the sheet:

```tsx
<DrilldownSheet
  open={drillKpi !== null}
  onOpenChange={(o) => !o && setDrillKpi(null)}
  kpi={drillKpi ?? 'revenue'}
  dateRange={dateRange}
/>
```

- [ ] **Step 3: Build + commit**

```sh
npm run build 2>&1 | tail -5
git add src/pages/Index.tsx
git commit -m "feat(spec2a): wire DrilldownSheet to 4 main KPI tiles on Index"
git push
```

---

### Task 21: Wire DrilldownSheet into `TechnicianBreakdown.tsx`

**Files:**
- Modify: `src/components/dashboard/TechnicianBreakdown.tsx`

- [ ] **Step 1: Add per-tech click → sheet**

Add to the imports:

```tsx
import { DrilldownSheet } from '@/components/dashboard/DrilldownSheet';
import type { DrilldownKpi } from '@/hooks/use-drilldown-jobs';
```

Add state:

```tsx
const [drill, setDrill] = useState<{ kpi: DrilldownKpi; techId: string; techName: string } | null>(null);
```

For each per-tech metric inside the per-tech card (revenue, closing, callback, membership), wrap the metric value in a button:

```tsx
<button
  type="button"
  onClick={() => setDrill({ kpi: 'revenue', techId: tech.techId, techName: tech.techName })}
  className="text-left hover:opacity-90"
>
  {/* existing metric value */}
</button>
```

Render the sheet once at the bottom of the component:

```tsx
{drill && (
  <DrilldownSheet
    open={true}
    onOpenChange={(o) => !o && setDrill(null)}
    kpi={drill.kpi}
    techId={drill.techId}
    techName={drill.techName}
    dateRange={dateRange}
  />
)}
```

- [ ] **Step 2: Build + commit**

```sh
npm run build 2>&1 | tail -5
git add src/components/dashboard/TechnicianBreakdown.tsx
git commit -m "feat(spec2a): per-tech metric drilldown on TechnicianBreakdown"
git push
```

---

### Task 22: Wire DrilldownSheet into `PerTechCards.tsx`

**Files:**
- Modify: `src/components/rev-rise/PerTechCards.tsx`

- [ ] **Step 1: Same pattern as Task 21**

Apply the identical pattern (state + per-metric button + sheet render) to `PerTechCards.tsx`.

- [ ] **Step 2: Build + commit**

```sh
npm run build 2>&1 | tail -5
git add src/components/rev-rise/PerTechCards.tsx
git commit -m "feat(spec2a): per-tech metric drilldown on PerTechCards (Rev & Rise)"
git push
```

---

## Group H — Revert script

### Task 23: Write `scripts/revert-spec2a.sh`

**Files:**
- Create: `scripts/revert-spec2a.sh`

- [ ] **Step 1: Write the script**

Write `scripts/revert-spec2a.sh`:

```sh
#!/usr/bin/env bash
# scripts/revert-spec2a.sh
# One-command revert of Spec 2A.
# Drops the new SQL views, git-reverts the merge commit, redeploys nothing
# (no edge function changes in 2A).
#
# Usage:
#   ./scripts/revert-spec2a.sh           # interactive confirm
#   ./scripts/revert-spec2a.sh --yes     # non-interactive
set -euo pipefail

CONFIRM="${1:-}"
PROJECT_REF="${SUPABASE_PROJECT_REF:-jwrpjuqaynownxaoeayi}"
PHASE_TAG="pre-spec2a-2026-05-05"
REVERT_MIGRATION="supabase/migrations/20260505140001_spec2a_views_revert.sql"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

if [[ "$CONFIRM" != "--yes" ]]; then
  cat <<EOF
This will REVERT all Spec 2A changes:
  1. Drop v_yesterday_recap and v_jobs_needing_review on $PROJECT_REF
  2. git revert the Spec 2A merge commit on main and push
  3. Verify both views are gone

Hard-reset alternative if this script itself fails:
  git reset --hard $PHASE_TAG
  git push --force-with-lease origin main
  (then DROP VIEW IF EXISTS public.v_yesterday_recap, public.v_jobs_needing_review via SQL editor)

EOF
  read -rp "Type YES to proceed: " ans
  [[ "$ans" == "YES" ]] || { echo "Aborted"; exit 1; }
fi

echo
echo "==> 1/3 Drop views"
if ! npx supabase db push --include-all --project-ref "$PROJECT_REF"; then
  echo "WARN: 'supabase db push' failed. Manual fallback:"
  echo "  Open https://supabase.com/dashboard/project/$PROJECT_REF/sql/new"
  echo "  Paste the contents of $REVERT_MIGRATION and run it."
  read -rp "Did you run it manually? (yes/no) " applied
  [[ "$applied" == "yes" ]] || { echo "Aborted — DB not reverted."; exit 1; }
fi

echo
echo "==> 2/3 git revert Spec 2A"
git checkout main
git fetch origin main
git pull --ff-only origin main
PHASE_MERGE="$(git log --oneline --merges main --grep='Spec 2A' -1 --format='%H' || true)"
[[ -n "$PHASE_MERGE" ]] || PHASE_MERGE="$(git log --oneline --merges main --grep='spec2a' -1 --format='%H' || true)"
if [[ -z "$PHASE_MERGE" ]]; then
  echo "ERROR: could not find Spec 2A merge commit. Find it manually."
  exit 1
fi
echo "Reverting merge commit: $PHASE_MERGE"
git revert -m 1 "$PHASE_MERGE" --no-edit
git push origin main

echo
echo "==> 3/3 Verify views are gone"
echo "Run this query against $PROJECT_REF to verify (count must be 0):"
cat <<'SQL'
SELECT COUNT(*) FROM information_schema.views
WHERE table_schema = 'public' AND table_name IN ('v_yesterday_recap','v_jobs_needing_review');
SQL

echo "DONE."
```

- [ ] **Step 2: Make executable + commit**

```sh
chmod +x scripts/revert-spec2a.sh
git add scripts/revert-spec2a.sh
git commit -m "feat(spec2a): one-command revert script (drops views + git-reverts merge)"
git push
```

---

## Group I — Verification & merge

### Task 24: Local verification

**Files:** none modified.

- [ ] **Step 1: Build**

```sh
npm run build 2>&1 | tail -10
```

Expected: passes; no new warnings beyond what Phase 1 left.

- [ ] **Step 2: Lint**

```sh
npm run lint 2>&1 | grep -c warning
```

Expected: ≤496 (Phase 1 floor). If higher, investigate.

- [ ] **Step 3: Tests**

```sh
npm test 2>&1 | tail -20
```

Expected: passes (no new test failures).

- [ ] **Step 4: Commit a verification placeholder**

```sh
mkdir -p .planning/spec2a
cat > .planning/spec2a/verification-report.md <<EOF
# Spec 2A — Verification Report

Date: $(date -u +%FT%TZ)

| # | Check | Status | Evidence |
|---|---|---|---|
| 1 | Build green on branch | PENDING | npm run build → 0 errors |
| 2 | Lint warnings ≤ Phase 1 floor (~496) | PENDING | npm run lint |
| 3 | Tests pass | PENDING | npm test |
| 4 | Toggle switches body | PENDING | manual |
| 5 | Yesterday recap renders | PENDING | manual |
| 6 | Jobs-needing-review queue populates | PENDING | manual |
| 7 | Drilldown sheets open + match counts | PENDING | manual |
| 8 | Rating chips appear for techs with reviews | PENDING | manual |

Update each row to PASS / FAIL with timestamps as the verification proceeds.
EOF
git add .planning/spec2a/verification-report.md
git commit -m "chore(spec2a): verification report skeleton"
git push
```

---

### Task 25: Vercel preview smoke test

**Files:** none modified.

- [ ] **Step 1: Open the preview URL**

After the latest push, the Vercel preview for `spec2a-operator-surfaces` is live. The URL is in the GitHub PR or Vercel dashboard.

- [ ] **Step 2: Click-through checklist (in the preview)**

For each, mark PASS or FAIL in `.planning/spec2a/verification-report.md`:

1. Open `/` (Index). Click each of the 4 main KPI tiles (revenue, closing, callback, membership). Each opens the DrilldownSheet with a list of jobs whose count visually matches the tile's denominator.
2. Open `/leaderboard` and `/tech` (admin view). Click any per-tech metric. Drilldown opens with that tech filtered.
3. Open `/rev-rise`. The page renders as before (call-window mode is the default). Toggle to "Daily" — body swaps to YesterdayRecap + DispatchQueue + JobsNeedingReview + MissedMoneyPanel.
4. Toggle back to "Call window" — body returns to original.
5. URL updates to `?mode=daily` when toggled.
6. If any tech has attributed reviews, the rating chip appears on `PerTechCards` and `TechnicianBreakdown`.

- [ ] **Step 3: Update verification report**

Edit `.planning/spec2a/verification-report.md` rows 4–8 to PASS or FAIL. If anything fails, halt — don't merge.

```sh
git add .planning/spec2a/verification-report.md
git commit -m "chore(spec2a): preview smoke test results"
git push
```

---

### Task 26: Open PR

**Files:** none modified.

- [ ] **Step 1: Open via GitHub API**

```sh
TOKEN=$(printf 'host=github.com\nprotocol=https\n' | git credential fill 2>/dev/null | grep '^password=' | cut -d= -f2)
curl -sS -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/vnd.github+json" \
  -d "$(cat <<'EOF'
{
  "title": "Spec 2A: Operator Surfaces (daily-mode toggle, jobs-needing-review, KPI drilldowns, reviews wiring)",
  "head": "spec2a-operator-surfaces",
  "base": "main",
  "body": "Implements `docs/superpowers/specs/2026-05-05-spec2a-operator-surfaces-design.md`.\n\nNo KPI math changes. Daily mode is opt-in (default = call-window unchanged). Drilldowns are additive (KPI tiles previously didn't respond to clicks). Reviews/ratings replace the `// TODO` stubs in `use-rev-rise-data.ts:143-144`.\n\nRevert: `./scripts/revert-spec2a.sh`. Pre-merge tag: `pre-spec2a-2026-05-05`.\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"
}
EOF
)" | grep -E '"(html_url|number)"' | head -2
```

Expected: PR URL printed.

---

### Task 27: Merge with merge-commit, then post-deploy verification

**Files:** none modified.

- [ ] **Step 1: Merge via GitHub API (merge-commit, NOT squash)**

```sh
PR=<PR number from Task 26>
TOKEN=$(printf 'host=github.com\nprotocol=https\n' | git credential fill 2>/dev/null | grep '^password=' | cut -d= -f2)
curl -sS -X PUT "https://api.github.com/repos/palpulla/twins-dash/pulls/${PR}/merge" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/vnd.github+json" \
  -d '{"merge_method": "merge", "commit_title": "Merge Spec 2A: Operator Surfaces (daily-mode core)"}'
```

Expected: `"merged": true`. Vercel auto-deploys main.

- [ ] **Step 2: Post-deploy smoke (twinsdash.com)**

Repeat the click-through from Task 25 against twinsdash.com (the live site, not the preview). All 8 verification rows must be PASS.

- [ ] **Step 3: Mark verification report complete**

```sh
git checkout main
git pull --ff-only
# Edit .planning/spec2a/verification-report.md to mark all 8 rows PASS with timestamps.
git add .planning/spec2a/verification-report.md
git commit -m "chore(spec2a): post-deploy verification — all 8 items PASS"
git push
```

---

## Self-Review

**Spec coverage:**
- ✅ §2 locked decisions: daily-mode toggle (Tasks 12, 17), drilldown sheet (Tasks 11, 20–22), jobs-review rules (Tasks 3, 9, 15), reviews wiring (Tasks 7, 18, 19), revert script (Task 23).
- ✅ §3 out of scope: Watchlist + Behaviors + Open Estimates explicitly NOT in this plan; `MissedMoneyPanel` placeholder (Task 16) explicitly notes the deferral.
- ✅ §6 work items: every group A–H has at least one task.
- ✅ §8 verification plan: all 8 items covered in Tasks 24–27.

**Placeholder scan:** No "TBD"/"TODO"/"fill in details". Two intentional inline halt-points (Task 14 step 3 if `useDayAheadJobs` shape differs, Task 19 step 2 if `TechnicianBreakdown` consumes from a different hook) — these are explicit decision points where the executor stops and asks.

**Type consistency:**
- `RevRiseMode` defined in Task 7 (hook) and re-exported from Task 12 (component); both use `'call-window' | 'daily'`. Consistent.
- `DrilldownKpi` defined in Task 10 (`use-drilldown-jobs`); imported by Tasks 11, 20, 21, 22. Consistent.
- `JobReviewReason` defined in Task 9 (`use-jobs-needing-review`); imported by Task 15. Consistent.
- `TechKPIRow` (existing) gains accurate `reviews` and `avgRating` in Task 7; consumed by Tasks 18 and 19.

---

## Execution Handoff

**Plan complete and saved to `docs/superpowers/plans/2026-05-05-spec2a-operator-surfaces.md`. Two execution options:**

**1. Subagent-Driven (recommended for the new-component group: Tasks 8–16, 23)** — fresh subagent per task, fast iteration, less risk of conflict between independent component creations.

**2. Inline Execution (recommended for the high-stakes group: Tasks 1–6, 7, 17, 20–22, 24–27)** — DB migration work and existing-file modifications need continuous context across halt-and-ask checkpoints.

**Recommended split:** inline for Groups A, B, C, E (the wiring), G (drilldown wiring across 3 existing files), I (verify + merge); subagent-driven for the new-component group (D, F components, H revert script).

Which approach?
