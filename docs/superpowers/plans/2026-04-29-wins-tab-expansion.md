# Wins Tab Expansion (v1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expand the Wins tab from 3 weekly personal records to ~11 records (2 single-job + 3 weekly + 3 monthly + 3 quarterly), grouped into 4 sections in the UI. Rate-KPI records (closing %, memberships %, callbacks %) and reviews-based records are explicitly deferred to v2 specs.

**Architecture:** A single SQL migration (1) widens the `tech_personal_records.period` CHECK constraint to include `'quarter'` and `'single_job'`, (2) inserts 2 new `kpi_keys` (`highest_install_ticket`, `highest_repair_ticket`) into `scorecard_tier_thresholds` (FK requirement), (3) extends `compute_streaks_and_prs()` to write 8 new record types, and (4) runs the function once manually so prod has data immediately. The frontend (`Recognition.tsx`) groups returned records by period into 4 sections; rendering is via a new `recordFormatters.ts` mapping kpi_key → label + value-formatter.

**Tech Stack:** Supabase Postgres (PL/pgSQL function + pg_cron schedule already exists), React + TypeScript, Vitest + Testing Library.

---

## Repo Context

- **Repo root:** `/Users/daniel/twins-dashboard/twins-dash`. Worktrees inside `.worktrees/<feature-name>`.
- **Branch from:** `origin/main` (currently `232bdc2` after the auto-detect-visibility merge).
- **Spec:** `docs/superpowers/specs/2026-04-29-wins-tab-expansion-design.md`
- **Existing data plumbing to know about:**
  - `tech_personal_records` table — columns `(tech_id, kpi_key, period, value, achieved_at, is_fresh, updated_at)`. CHECK on `period IN ('week','month','year')`. UNIQUE on `(tech_id, kpi_key, period)`.
  - `scorecard_tier_thresholds` — primary key `kpi_key`. The FK on `tech_personal_records.kpi_key` requires the row to exist before any record can be inserted.
  - `compute_streaks_and_prs()` — PL/pgSQL function in `20260425170200_compute_streaks_and_prs.sql`. Loops `FOR v_tech IN SELECT * FROM payroll_techs`, writes records inside the loop. Currently writes 3 weekly PRs (`revenue`, `total_jobs`, `avg_opportunity`).
  - pg_cron schedule — `20260425170300_pg_cron_streaks_prs.sql` runs the compute function nightly at 07:00 UTC.
  - Frontend hook — `useMyPersonalRecords()` returns the flat list. Display tile — `PersonalRecordTile` (no changes needed).
  - Page — `src/pages/tech/Recognition.tsx` renders the tab content.
- **Install/repair classifier:** authoritative source is `src/lib/constants.ts:4`:
  ```ts
  export const INSTALL_JOB_TYPES: string[] = ["Door Install", "Door + Opener Install"];
  ```
  Anything else is a repair. The SQL migration must use this exact list against `payroll_jobs.description`.
- **Migration history desync:** `npx supabase db push --linked` may complain about prior versions. Fall back to `npx supabase db query --linked -f <file>` plus a manual `INSERT INTO supabase_migrations.schema_migrations` row, the same pattern used in the previous 3 features this session.

## File Structure

| File | Action | Purpose |
|---|---|---|
| `supabase/migrations/<new>.sql` | Create | CHECK widening + 2 new kpi_key rows + extended `compute_streaks_and_prs()` body + manual one-shot run statement |
| `src/lib/wins/recordFormatters.ts` | Create | Map `kpi_key` → `{ label, formatValue, formatContext }` per (kpi_key, period) tuple. Pure utility, no React. |
| `src/lib/wins/__tests__/recordFormatters.test.ts` | Create | Unit tests for the formatter map (every kpi_key × period covered) |
| `src/pages/tech/Recognition.tsx` | Modify | Group records by period (single_job / week / month / quarter), render 4 sections via a new helper component. Use `recordFormatters` for label/value/context |

That's 4 files: 1 migration, 1 formatter module, 1 unit-test file, 1 page modification. Self-contained.

---

## M1 — Worktree + branch

### Task 0: Worktree + branch

**Files:** none

- [ ] **Step 1: Create worktree from main**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git fetch origin
git worktree add .worktrees/wins-tab-expansion -b feat/wins-tab-expansion origin/main
cd .worktrees/wins-tab-expansion
```

Expected: a new directory `.worktrees/wins-tab-expansion` on `feat/wins-tab-expansion`. HEAD at `232bdc2` (auto-detect-visibility merge) or later.

- [ ] **Step 2: Sanity check**

```bash
git status
git log --oneline -3
ls supabase/migrations/20260425170200_compute_streaks_and_prs.sql
ls src/pages/tech/Recognition.tsx
ls src/components/tech/PersonalRecordTile.tsx
```

Expected: clean tree, all four files present (the 3 dependencies + the recent commit).

---

## M2 — Database migration

### Task 1: Write the migration

**Files:**
- Create: `supabase/migrations/20260429180000_wins_tab_expansion.sql`

The migration has 4 sections: CHECK widening, threshold seed, compute-function extension, one-shot run.

- [ ] **Step 1: Create the migration file**

```bash
cat > supabase/migrations/20260429180000_wins_tab_expansion.sql <<'EOF'
-- Wins Tab v1 expansion. Adds:
--   1. period values 'quarter' and 'single_job' to tech_personal_records
--   2. kpi_keys highest_install_ticket and highest_repair_ticket to
--      scorecard_tier_thresholds (FK requirement; thresholds are placeholders
--      since these are records, not tier-based KPIs)
--   3. compute_streaks_and_prs() extended to write 8 new record types per tech:
--        - 2 single-job: highest_install_ticket, highest_repair_ticket
--        - 3 monthly:    revenue, total_jobs, avg_opportunity (best month)
--        - 3 quarterly:  revenue, total_jobs, avg_opportunity (best quarter)
--   4. one-shot SELECT compute_streaks_and_prs() so records exist immediately
--
-- Reversibility: drop the new constraint + function body changes; thresholds
-- can be left in place (harmless without records pointing at them).

-- 1. CHECK widening on tech_personal_records.period
ALTER TABLE public.tech_personal_records
  DROP CONSTRAINT IF EXISTS tech_personal_records_period_check;
ALTER TABLE public.tech_personal_records
  ADD CONSTRAINT tech_personal_records_period_check
  CHECK (period IN ('week','month','quarter','year','single_job'));

-- 2. New kpi_keys for single-job records. Thresholds are placeholders that
-- will never be compared against (these are records, not rate-tier KPIs).
INSERT INTO public.scorecard_tier_thresholds
  (kpi_key, bronze, silver, gold, elite, direction, unit, display_name) VALUES
  ('highest_install_ticket', 1000, 2500, 4000, 6000, 'higher', 'usd', 'Highest install ticket'),
  ('highest_repair_ticket',   500, 1000, 1500, 2500, 'higher', 'usd', 'Highest repair ticket')
ON CONFLICT (kpi_key) DO NOTHING;

-- 3a. Helper: upsert a single PR. Skips NULL values. Sets is_fresh based on
-- a per-period freshness window (single_job=30d, week=7d, month=14d, quarter=30d).
CREATE OR REPLACE FUNCTION public._upsert_personal_record(
  p_tech_id integer,
  p_kpi_key text,
  p_period  text,
  p_value   numeric,
  p_achieved_at date
) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE
  v_fresh_days integer;
BEGIN
  IF p_value IS NULL OR p_achieved_at IS NULL THEN RETURN; END IF;

  v_fresh_days := CASE p_period
    WHEN 'single_job' THEN 30
    WHEN 'week'       THEN 7
    WHEN 'month'      THEN 14
    WHEN 'quarter'    THEN 30
    ELSE 7
  END;

  INSERT INTO public.tech_personal_records
    (tech_id, kpi_key, period, value, achieved_at, is_fresh, updated_at)
  VALUES (
    p_tech_id, p_kpi_key, p_period, p_value, p_achieved_at,
    (p_achieved_at >= (now() - (v_fresh_days * interval '1 day'))::date),
    now()
  )
  ON CONFLICT (tech_id, kpi_key, period) DO UPDATE SET
    value       = EXCLUDED.value,
    achieved_at = EXCLUDED.achieved_at,
    is_fresh    = EXCLUDED.is_fresh,
    updated_at  = now();
END;
$$;

-- 3b. Extended compute function. Replaces the existing body. The existing
-- streak + 3 weekly-PR logic is preserved verbatim; new blocks are appended
-- at the end of the per-tech loop, before END LOOP.
CREATE OR REPLACE FUNCTION public.compute_streaks_and_prs()
RETURNS void
LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
  v_tech         RECORD;
  v_rev_floor    numeric;
  v_since        date;
  v_streak_len   integer;
  v_best_rev     RECORD;
  v_best_jobs    RECORD;
  v_best_avg     RECORD;
  v_best_install RECORD;
  v_best_repair  RECORD;
  v_best_m_rev   RECORD;
  v_best_m_jobs  RECORD;
  v_best_m_avg   RECORD;
  v_best_q_rev   RECORD;
  v_best_q_jobs  RECORD;
  v_best_q_avg   RECORD;
BEGIN
  -- Weekly revenue floor: 1/4 of the silver-tier monthly revenue threshold.
  SELECT (silver / 4.0) INTO v_rev_floor
  FROM public.scorecard_tier_thresholds WHERE kpi_key = 'revenue';
  IF v_rev_floor IS NULL THEN v_rev_floor := 0; END IF;

  FOR v_tech IN SELECT id, name FROM public.payroll_techs LOOP

    -- ====== no_callbacks streak (UNCHANGED from previous version) ======
    SELECT COUNT(*)::int INTO v_streak_len
    FROM (
      SELECT w.wk
      FROM (
        SELECT generate_series(
          date_trunc('week', now())::date - (3 * 7),
          date_trunc('week', now())::date,
          interval '7 days'
        )::date AS wk
      ) w
      WHERE EXISTS (
        SELECT 1 FROM public.payroll_jobs pj
        JOIN public.payroll_runs pr ON pr.id = pj.run_id
        WHERE pj.owner_tech = v_tech.name
          AND pr.week_start = w.wk
      )
    ) eligible;

    IF v_streak_len > 0 THEN
      v_since := date_trunc('week', now())::date - ((v_streak_len - 1) * 7);
    ELSE
      v_since := date_trunc('week', now())::date;
    END IF;

    INSERT INTO public.tech_streaks
      (tech_id, kind, kpi_key, count, unit, since_period, active, updated_at)
    VALUES
      (v_tech.id, 'no_callbacks', NULL, v_streak_len, 'week', v_since, (v_streak_len > 0), now())
    ON CONFLICT (tech_id, kind, kpi_key) DO UPDATE SET
      count        = EXCLUDED.count,
      since_period = EXCLUDED.since_period,
      active       = EXCLUDED.active,
      updated_at   = now();

    -- ====== rev_floor streak (UNCHANGED) ======
    SELECT COUNT(*)::int INTO v_streak_len
    FROM (
      SELECT *,
             row_number() OVER (ORDER BY wk DESC) AS rn,
             COALESCE(MIN(rn) FILTER (WHERE under_floor) OVER (), 999) AS first_miss_rn
      FROM (
        SELECT w.wk, COALESCE(SUM(pj.amount), 0) AS rev,
               COALESCE(SUM(pj.amount), 0) < v_rev_floor AS under_floor,
               row_number() OVER (ORDER BY w.wk DESC) AS rn
        FROM (
          SELECT generate_series(
            date_trunc('week', now())::date - (25 * 7),
            date_trunc('week', now())::date,
            interval '7 days'
          )::date AS wk
        ) w
        LEFT JOIN public.payroll_runs pr ON pr.week_start = w.wk
        LEFT JOIN public.payroll_jobs pj ON pj.run_id = pr.id
                                        AND pj.owner_tech = v_tech.name
        GROUP BY w.wk
      ) ranked
    ) windowed
    WHERE rn < first_miss_rn;

    IF v_streak_len > 0 THEN
      v_since := date_trunc('week', now())::date - ((v_streak_len - 1) * 7);
    ELSE
      v_since := date_trunc('week', now())::date;
    END IF;

    INSERT INTO public.tech_streaks
      (tech_id, kind, kpi_key, count, unit, since_period, active, updated_at)
    VALUES
      (v_tech.id, 'rev_floor', 'revenue', v_streak_len, 'week', v_since, (v_streak_len > 0), now())
    ON CONFLICT (tech_id, kind, kpi_key) DO UPDATE SET
      count        = EXCLUDED.count,
      since_period = EXCLUDED.since_period,
      active       = EXCLUDED.active,
      updated_at   = now();

    -- ====== Personal records: WEEKLY (UNCHANGED, kept verbatim) ======
    -- best weekly revenue
    SELECT pr.week_start, COALESCE(SUM(pj.amount), 0)::numeric AS revenue
    INTO v_best_rev
    FROM public.payroll_runs pr
    LEFT JOIN public.payroll_jobs pj ON pj.run_id = pr.id
                                    AND pj.owner_tech = v_tech.name
    WHERE pr.week_start >= (date_trunc('week', now())::date - (25 * 7))
    GROUP BY pr.week_start
    HAVING COALESCE(SUM(pj.amount), 0) > 0
    ORDER BY revenue DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'revenue', 'week',
      v_best_rev.revenue, v_best_rev.week_start);

    -- best weekly total_jobs
    SELECT pr.week_start, COUNT(pj.id)::numeric AS jobs
    INTO v_best_jobs
    FROM public.payroll_runs pr
    LEFT JOIN public.payroll_jobs pj ON pj.run_id = pr.id
                                    AND pj.owner_tech = v_tech.name
    WHERE pr.week_start >= (date_trunc('week', now())::date - (25 * 7))
    GROUP BY pr.week_start
    HAVING COUNT(pj.id) > 0
    ORDER BY jobs DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'total_jobs', 'week',
      v_best_jobs.jobs, v_best_jobs.week_start);

    -- best weekly avg_opportunity
    SELECT pr.week_start, (SUM(pj.amount)::numeric / COUNT(pj.id)) AS avg_t
    INTO v_best_avg
    FROM public.payroll_runs pr
    LEFT JOIN public.payroll_jobs pj ON pj.run_id = pr.id
                                    AND pj.owner_tech = v_tech.name
    WHERE pr.week_start >= (date_trunc('week', now())::date - (25 * 7))
    GROUP BY pr.week_start
    HAVING COUNT(pj.id) > 0
    ORDER BY avg_t DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'avg_opportunity', 'week',
      v_best_avg.avg_t, v_best_avg.week_start);

    -- ====== NEW: Single-job records ======
    -- highest install ticket (Door Install + Door + Opener Install only)
    SELECT pj.amount::numeric AS amt, pj.job_date::date AS at
    INTO v_best_install
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND pj.description IN ('Door Install', 'Door + Opener Install')
      AND pj.amount > 0
    ORDER BY pj.amount DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'highest_install_ticket', 'single_job',
      v_best_install.amt, v_best_install.at);

    -- highest repair ticket (everything NOT in the install list)
    SELECT pj.amount::numeric AS amt, pj.job_date::date AS at
    INTO v_best_repair
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND (pj.description IS NULL
           OR pj.description NOT IN ('Door Install', 'Door + Opener Install'))
      AND pj.amount > 0
    ORDER BY pj.amount DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'highest_repair_ticket', 'single_job',
      v_best_repair.amt, v_best_repair.at);

    -- ====== NEW: Monthly records (12-month lookback) ======
    -- best monthly revenue
    SELECT date_trunc('month', pj.job_date)::date AS month_start,
           SUM(pj.amount)::numeric AS revenue
    INTO v_best_m_rev
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('month', now()) - interval '12 months')::date
    GROUP BY date_trunc('month', pj.job_date)
    HAVING SUM(pj.amount) > 0
    ORDER BY revenue DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'revenue', 'month',
      v_best_m_rev.revenue, v_best_m_rev.month_start);

    -- best monthly total_jobs
    SELECT date_trunc('month', pj.job_date)::date AS month_start,
           COUNT(pj.id)::numeric AS jobs
    INTO v_best_m_jobs
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('month', now()) - interval '12 months')::date
    GROUP BY date_trunc('month', pj.job_date)
    HAVING COUNT(pj.id) > 0
    ORDER BY jobs DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'total_jobs', 'month',
      v_best_m_jobs.jobs, v_best_m_jobs.month_start);

    -- best monthly avg_opportunity
    SELECT date_trunc('month', pj.job_date)::date AS month_start,
           (SUM(pj.amount)::numeric / COUNT(pj.id)) AS avg_t
    INTO v_best_m_avg
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('month', now()) - interval '12 months')::date
    GROUP BY date_trunc('month', pj.job_date)
    HAVING COUNT(pj.id) > 0
    ORDER BY avg_t DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'avg_opportunity', 'month',
      v_best_m_avg.avg_t, v_best_m_avg.month_start);

    -- ====== NEW: Quarterly records (8-quarter lookback) ======
    -- best quarterly revenue
    SELECT date_trunc('quarter', pj.job_date)::date AS quarter_start,
           SUM(pj.amount)::numeric AS revenue
    INTO v_best_q_rev
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('quarter', now()) - interval '8 months' * 3)::date
    GROUP BY date_trunc('quarter', pj.job_date)
    HAVING SUM(pj.amount) > 0
    ORDER BY revenue DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'revenue', 'quarter',
      v_best_q_rev.revenue, v_best_q_rev.quarter_start);

    -- best quarterly total_jobs
    SELECT date_trunc('quarter', pj.job_date)::date AS quarter_start,
           COUNT(pj.id)::numeric AS jobs
    INTO v_best_q_jobs
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('quarter', now()) - interval '8 months' * 3)::date
    GROUP BY date_trunc('quarter', pj.job_date)
    HAVING COUNT(pj.id) > 0
    ORDER BY jobs DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'total_jobs', 'quarter',
      v_best_q_jobs.jobs, v_best_q_jobs.quarter_start);

    -- best quarterly avg_opportunity
    SELECT date_trunc('quarter', pj.job_date)::date AS quarter_start,
           (SUM(pj.amount)::numeric / COUNT(pj.id)) AS avg_t
    INTO v_best_q_avg
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('quarter', now()) - interval '8 months' * 3)::date
    GROUP BY date_trunc('quarter', pj.job_date)
    HAVING COUNT(pj.id) > 0
    ORDER BY avg_t DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'avg_opportunity', 'quarter',
      v_best_q_avg.avg_t, v_best_q_avg.quarter_start);

  END LOOP;
END $$;

GRANT EXECUTE ON FUNCTION public.compute_streaks_and_prs() TO service_role;
GRANT EXECUTE ON FUNCTION public._upsert_personal_record(integer, text, text, numeric, date) TO service_role;

-- 4. One-shot run so prod has data immediately (don't wait for the next 07:00 UTC cron).
SELECT public.compute_streaks_and_prs();
EOF
```

- [ ] **Step 2: Verify the file**

```bash
wc -l supabase/migrations/20260429180000_wins_tab_expansion.sql
head -5 supabase/migrations/20260429180000_wins_tab_expansion.sql
grep -c "PERFORM public._upsert_personal_record" supabase/migrations/20260429180000_wins_tab_expansion.sql
```

Expected: ~280 lines; first line starts with `-- Wins Tab v1 expansion`; grep returns `11` (3 weekly + 2 single_job + 3 monthly + 3 quarterly).

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260429180000_wins_tab_expansion.sql
git commit -m "feat(wins-tab): SQL migration — period CHECK widening + 2 new kpi_keys + extended compute_streaks_and_prs"
```

---

### Task 2: Apply migration to prod + verify

**Files:** none (executes against live DB)

- [ ] **Step 1: Try standard push**

```bash
npx supabase db push --linked
```

If clean, skip Step 2. Otherwise fall through.

- [ ] **Step 2: Fallback for migration history desync**

```bash
npx supabase db query --linked -f supabase/migrations/20260429180000_wins_tab_expansion.sql
npx supabase db query --linked "INSERT INTO supabase_migrations.schema_migrations (version, name) VALUES ('20260429180000', 'wins_tab_expansion') ON CONFLICT DO NOTHING;"
```

The migration ends with a `SELECT public.compute_streaks_and_prs();` so applying via `db query -f` automatically populates records as part of the apply step.

- [ ] **Step 3: Verify CHECK widening**

```bash
npx supabase db query --linked "SELECT conname, pg_get_constraintdef(oid) FROM pg_constraint WHERE conrelid = 'public.tech_personal_records'::regclass AND conname = 'tech_personal_records_period_check';"
```

Expected: the constraint def includes `'quarter'` and `'single_job'`.

- [ ] **Step 4: Verify new kpi_keys**

```bash
npx supabase db query --linked "SELECT kpi_key, display_name FROM public.scorecard_tier_thresholds WHERE kpi_key IN ('highest_install_ticket', 'highest_repair_ticket') ORDER BY kpi_key;"
```

Expected: 2 rows.

- [ ] **Step 5: Verify records populated**

```bash
npx supabase db query --linked "SELECT period, kpi_key, COUNT(*) AS rows FROM public.tech_personal_records GROUP BY period, kpi_key ORDER BY period, kpi_key;"
```

Expected: rows for `single_job × 2 kpis`, `week × 3 kpis`, `month × 3 kpis`, `quarter × 3 kpis`. Counts match the number of techs that have any history of the relevant job type / period.

- [ ] **Step 6: Spot-check one tech's records**

```bash
npx supabase db query --linked "SELECT pt.name, tpr.kpi_key, tpr.period, tpr.value, tpr.achieved_at, tpr.is_fresh FROM public.tech_personal_records tpr JOIN public.payroll_techs pt ON pt.id = tpr.tech_id ORDER BY pt.name, tpr.period, tpr.kpi_key LIMIT 30;"
```

Expected: realistic values (install tickets in low-thousands, repair tickets in low-hundreds-to-low-thousands, monthly revenue in 5-figure range, etc.). If any value looks suspiciously off (e.g. install ticket = $20), that's a sign the install/repair classifier doesn't match prod data — STOP and investigate `payroll_jobs.description` actual values.

---

## M3 — Frontend formatter module

### Task 3: recordFormatters.ts + tests

**Files:**
- Create: `src/lib/wins/recordFormatters.ts`
- Create: `src/lib/wins/__tests__/recordFormatters.test.ts`

TDD: tests first.

- [ ] **Step 1: Write the failing tests**

```bash
mkdir -p src/lib/wins/__tests__
cat > src/lib/wins/__tests__/recordFormatters.test.ts <<'EOF'
import { describe, it, expect } from "vitest";
import { formatRecord, getSectionForPeriod, type RecordPeriod } from "../recordFormatters";

describe("formatRecord", () => {
  it("formats highest_install_ticket (single_job) as USD with date context", () => {
    const r = formatRecord({
      kpi_key: "highest_install_ticket",
      period: "single_job",
      value: 5400,
      achieved_at: "2026-03-12",
    });
    expect(r.label).toBe("Highest install ticket");
    expect(r.value).toBe("$5,400");
    expect(r.context).toMatch(/Mar 12, 2026/);
  });

  it("formats highest_repair_ticket (single_job)", () => {
    const r = formatRecord({
      kpi_key: "highest_repair_ticket",
      period: "single_job",
      value: 2180,
      achieved_at: "2026-02-04",
    });
    expect(r.label).toBe("Highest repair ticket");
    expect(r.value).toBe("$2,180");
    expect(r.context).toMatch(/Feb 4, 2026/);
  });

  it("formats revenue/week with 'week of <date>' context", () => {
    const r = formatRecord({
      kpi_key: "revenue",
      period: "week",
      value: 34500,
      achieved_at: "2026-04-14",
    });
    expect(r.label).toBe("Best revenue");
    expect(r.value).toBe("$34,500");
    expect(r.context).toMatch(/week of Apr 14/i);
  });

  it("formats total_jobs/month as count with 'Month YYYY' context", () => {
    const r = formatRecord({
      kpi_key: "total_jobs",
      period: "month",
      value: 47,
      achieved_at: "2026-03-01",
    });
    expect(r.label).toBe("Most jobs");
    expect(r.value).toBe("47");
    expect(r.context).toMatch(/March 2026/i);
  });

  it("formats avg_opportunity/quarter as USD with 'Q1 2026' context", () => {
    const r = formatRecord({
      kpi_key: "avg_opportunity",
      period: "quarter",
      value: 1480,
      achieved_at: "2026-01-01",
    });
    expect(r.label).toBe("Best avg ticket");
    expect(r.value).toBe("$1,480");
    expect(r.context).toMatch(/Q1 2026/);
  });

  it("returns a fallback label for unknown kpi_key (defensive)", () => {
    const r = formatRecord({
      kpi_key: "unknown_kpi",
      period: "week",
      value: 100,
      achieved_at: "2026-01-01",
    });
    expect(r.label).toBe("unknown_kpi");
    expect(r.value).toBe("100"); // fallback to raw value
  });
});

describe("getSectionForPeriod", () => {
  it("returns the right section header for each period", () => {
    expect(getSectionForPeriod("single_job" as RecordPeriod)).toMatchObject({
      title: "Single-Job Bests",
      order: 0,
    });
    expect(getSectionForPeriod("week" as RecordPeriod)).toMatchObject({
      title: "Best Week Ever",
      order: 1,
    });
    expect(getSectionForPeriod("month" as RecordPeriod)).toMatchObject({
      title: "Best Month Ever",
      order: 2,
    });
    expect(getSectionForPeriod("quarter" as RecordPeriod)).toMatchObject({
      title: "Best Quarter Ever",
      order: 3,
    });
  });
});
EOF
```

- [ ] **Step 2: Run tests — verify failure**

```bash
npx vitest run src/lib/wins/__tests__/recordFormatters.test.ts
```

Expected: failures with `Cannot find module '../recordFormatters'`.

- [ ] **Step 3: Write the formatter module**

```bash
cat > src/lib/wins/recordFormatters.ts <<'EOF'
// src/lib/wins/recordFormatters.ts
//
// Maps personal-record (kpi_key, period) tuples to display strings:
//   - label: section-relative noun ("Best revenue", "Most jobs")
//   - value: formatted display ("$34,500", "47", "78%")
//   - context: when/scope ("Mar 12, 2026", "week of Apr 14", "Q1 2026")
//
// Pure utility — no React, no data fetching. Recognition.tsx imports the
// formatter and feeds the result into PersonalRecordTile.

import { format, parseISO } from "date-fns";

export type RecordPeriod = "single_job" | "week" | "month" | "quarter" | "year";

export type RecordInput = {
  kpi_key: string;
  period: RecordPeriod | string;
  value: number;
  achieved_at: string; // ISO date string (YYYY-MM-DD)
};

export type FormattedRecord = {
  label: string;
  value: string;
  context: string;
};

const fmtUsd = (n: number) =>
  new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: 0 }).format(n);
const fmtCount = (n: number) => String(Math.round(n));

// Map of kpi_key → label per period. Some kpi_keys re-use the same label
// across periods; some don't.
const LABEL_MAP: Record<string, Record<string, string>> = {
  highest_install_ticket: { single_job: "Highest install ticket" },
  highest_repair_ticket: { single_job: "Highest repair ticket" },
  revenue: { week: "Best revenue", month: "Best revenue", quarter: "Best revenue" },
  total_jobs: { week: "Most jobs", month: "Most jobs", quarter: "Most jobs" },
  avg_opportunity: { week: "Best avg ticket", month: "Best avg ticket", quarter: "Best avg ticket" },
};

// kpi_key → value formatter
const VALUE_FMT: Record<string, (n: number) => string> = {
  highest_install_ticket: fmtUsd,
  highest_repair_ticket: fmtUsd,
  revenue: fmtUsd,
  avg_opportunity: fmtUsd,
  total_jobs: fmtCount,
};

function formatContext(period: string, achievedAt: string): string {
  // achievedAt is YYYY-MM-DD; parse without timezone shift
  const d = parseISO(achievedAt);
  switch (period) {
    case "single_job":
      return format(d, "MMM d, yyyy");
    case "week":
      return `week of ${format(d, "MMM d")}`;
    case "month":
      return format(d, "MMMM yyyy");
    case "quarter": {
      const q = Math.floor(d.getMonth() / 3) + 1;
      return `Q${q} ${d.getFullYear()}`;
    }
    case "year":
      return String(d.getFullYear());
    default:
      return achievedAt;
  }
}

export function formatRecord(r: RecordInput): FormattedRecord {
  const labelByPeriod = LABEL_MAP[r.kpi_key];
  const label = labelByPeriod?.[r.period] ?? r.kpi_key;
  const valueFmt = VALUE_FMT[r.kpi_key];
  const value = valueFmt ? valueFmt(r.value) : String(r.value);
  const context = formatContext(r.period, r.achieved_at);
  return { label, value, context };
}

export type SectionDef = { title: string; order: number };
const SECTION_DEFS: Record<string, SectionDef> = {
  single_job: { title: "Single-Job Bests", order: 0 },
  week: { title: "Best Week Ever", order: 1 },
  month: { title: "Best Month Ever", order: 2 },
  quarter: { title: "Best Quarter Ever", order: 3 },
  year: { title: "Best Year Ever", order: 4 },
};

export function getSectionForPeriod(period: RecordPeriod): SectionDef {
  return SECTION_DEFS[period] ?? { title: period, order: 99 };
}
EOF
```

- [ ] **Step 4: Run tests — verify pass**

```bash
npx vitest run src/lib/wins/__tests__/recordFormatters.test.ts
```

Expected: 6 passing.

- [ ] **Step 5: TS compile**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add src/lib/wins/recordFormatters.ts src/lib/wins/__tests__/recordFormatters.test.ts
git commit -m "feat(wins-tab): recordFormatters utility (label + value + context per kpi_key × period) + 6 unit tests"
```

---

## M4 — Recognition.tsx UI grouping

### Task 4: Group records by period in Recognition.tsx

**Files:**
- Modify: `src/pages/tech/Recognition.tsx`

The current page uses `useMyPersonalRecords()` and renders all records in a single section. After this task, records are grouped into 4 sections (Single-Job / Week / Month / Quarter) using the new `recordFormatters` utility.

- [ ] **Step 1: Read the current Recognition.tsx structure**

```bash
grep -n "useMyPersonalRecords\|PersonalRecordTile\|RecognitionEmptyState\|records\.\|prs\." src/pages/tech/Recognition.tsx | head -20
```

Identify where the existing records are rendered. The plan assumes there's a section that maps over `records` and renders `<PersonalRecordTile>` for each. The new structure groups them first.

- [ ] **Step 2: Add the imports**

In `src/pages/tech/Recognition.tsx`, add the import after the existing `useMyPersonalRecords` import:

```tsx
import { formatRecord, getSectionForPeriod, type RecordPeriod } from '@/lib/wins/recordFormatters';
```

- [ ] **Step 3: Replace the records-render block with grouped sections**

Find the existing block that maps records into tiles (in the JSX return). It looks roughly like:

```tsx
{records.map((r) => (
  <PersonalRecordTile
    key={r.id}
    label={...}
    value={...}
    context={...}
    isFresh={r.is_fresh}
  />
))}
```

Replace it with a grouped-by-period rendering. Just before the JSX, build the grouped data structure:

```tsx
  // Group personal records by period, ordered single_job → week → month → quarter
  const recordsByPeriod = useMemo(() => {
    const groups = new Map<string, typeof records>();
    for (const r of records ?? []) {
      const list = groups.get(r.period) ?? [];
      list.push(r);
      groups.set(r.period, list);
    }
    // Stable order within each section: by kpi_key alphabetical
    for (const list of groups.values()) {
      list.sort((a, b) => a.kpi_key.localeCompare(b.kpi_key));
    }
    return Array.from(groups.entries())
      .map(([period, list]) => ({ period, list, def: getSectionForPeriod(period as RecordPeriod) }))
      .sort((a, b) => a.def.order - b.def.order);
  }, [records]);
```

(`useMemo` is already imported at the top of Recognition.tsx — verify with `grep -n "useMemo" src/pages/tech/Recognition.tsx`. If missing, add it to the existing `react` import.)

Then in the JSX, replace the old single-section render with the grouped render:

```tsx
      {recordsByPeriod.length === 0 ? (
        <RecognitionEmptyState />
      ) : (
        <div className="space-y-6">
          {recordsByPeriod.map(({ period, list, def }) => (
            <section key={period}>
              <h3 className="text-sm font-extrabold uppercase tracking-wider text-primary mb-3">
                {def.title}
              </h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                {list.map((r) => {
                  const f = formatRecord({
                    kpi_key: r.kpi_key,
                    period: r.period,
                    value: r.value,
                    achieved_at: r.achieved_at,
                  });
                  return (
                    <PersonalRecordTile
                      key={r.id}
                      label={f.label}
                      value={f.value}
                      context={f.context}
                      isFresh={r.is_fresh}
                    />
                  );
                })}
              </div>
            </section>
          ))}
        </div>
      )}
```

The variable `records` here refers to the result of `useMyPersonalRecords()`. Match whatever variable name is used in the current file (it may be `data` or `prs` or similar — adjust accordingly).

DO NOT touch the streaks / tier-up / year-ribbon sections of the page. They stay untouched.

- [ ] **Step 4: TS compile + visual check**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 5: Commit**

```bash
git add src/pages/tech/Recognition.tsx
git commit -m "feat(wins-tab): Recognition.tsx groups personal records by period (4 sections)"
```

---

## M5 — Final verification + PR

### Task 5: Full verification + push + PR

**Files:** none

- [ ] **Step 1: Run full test suite**

```bash
npx vitest run 2>&1 | tail -10
```

Expected: 198 + 6 (new formatter tests) = 204 passing. 8 pre-existing Deno-import failures unrelated.

- [ ] **Step 2: Final tsc**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 3: Final build**

```bash
npm run build 2>&1 | tail -5
```

Expected: `✓ built in <N>s`.

- [ ] **Step 4: Push the branch**

```bash
git push -u origin feat/wins-tab-expansion
```

- [ ] **Step 5: Open PR**

```bash
TOKEN=$(printf "protocol=https\nhost=github.com\n" | git credential-osxkeychain get | grep '^password=' | cut -d= -f2)
curl -s -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.github+json" \
  -d "$(cat <<'EOF'
{
  "title": "feat(wins-tab): expand personal records to single-job + monthly + quarterly bests (v1)",
  "head": "feat/wins-tab-expansion",
  "base": "main",
  "body": "Implements `docs/superpowers/specs/2026-04-29-wins-tab-expansion-design.md` (v1 scope).\n\n## Summary\n\nWins tab grows from 3 weekly personal records to ~11 grouped into 4 sections.\n\n- **Single-Job Bests** (new): highest install ticket, highest repair ticket\n- **Best Week Ever** (existing weekly): revenue, total jobs, avg ticket\n- **Best Month Ever** (new): revenue, total jobs, avg ticket\n- **Best Quarter Ever** (new): revenue, total jobs, avg ticket\n\n## What's NOT in this PR (deferred to v2 follow-up specs)\n\n- **Closing % / memberships % / callbacks % records.** Per the existing `compute_streaks_and_prs()` migration's own comments, these formulas aren't yet exposed at the DB layer. v2 spec (TBD) will add a SQL view that computes these per period — same work that the scorecard tile rendering does client-side, lifted to a server-side aggregate.\n- **Reviews-based records** (most reviews per period). No review data exists in the system today; needs its own ingestion-pipeline spec first.\n\n## Schema changes (1 migration, applied to prod)\n- `20260429180000_wins_tab_expansion.sql`:\n  1. Widens `tech_personal_records.period` CHECK to include `'quarter'` and `'single_job'`\n  2. Inserts `highest_install_ticket` and `highest_repair_ticket` into `scorecard_tier_thresholds` (FK requirement)\n  3. Adds helper `_upsert_personal_record(...)` to keep the compute body readable\n  4. Replaces `compute_streaks_and_prs()` body — preserves all existing streak + 3 weekly-PR logic verbatim, appends 8 new compute blocks (2 single-job + 3 monthly + 3 quarterly)\n  5. One-shot `SELECT compute_streaks_and_prs()` so prod has data immediately\n\n## Frontend\n- New: `src/lib/wins/recordFormatters.ts` — pure utility mapping `(kpi_key, period)` to `{label, value, context}` strings\n- New: 6 unit tests covering single_job / week / month / quarter formatting + section ordering\n- Modified: `src/pages/tech/Recognition.tsx` — replaces flat record render with 4 ordered sections, uses `recordFormatters` for display\n\nReuses existing `PersonalRecordTile` component unchanged.\n\n## Test plan\n- [x] Unit tests: 6 new formatter tests passing\n- [x] tsc + vite build clean\n- [x] vitest run: 204/204 passing\n- [x] SQL: CHECK widening verified, new kpi_keys present, records populated for all techs with relevant history\n- [ ] Manual smoke (Vercel preview): open `/tech/wins?as=<tech-uuid>` as admin, verify 4 sections render with realistic numbers; verify single-job records show actual job dates that match the tech's job history\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"
}
EOF
)" | python3 -c "import sys,json; d=json.load(sys.stdin); print('PR URL:', d.get('html_url','ERR')); print('Number:', d.get('number','ERR'))"
```

Expected: PR URL + number.

---

## Self-Review

**Spec coverage (v1 scope):**

- ✅ Single-job records (highest install + highest repair) → Task 1 step 1 (compute blocks)
- ✅ Monthly records for revenue/total_jobs/avg_opportunity → Task 1 step 1 (compute blocks)
- ✅ Quarterly records for same → Task 1 step 1 (compute blocks)
- ✅ Wins tab UI grouped by period → Task 4 step 3
- ✅ Install/repair classifier matches `INSTALL_JOB_TYPES` constant → Task 1 step 1 (`description IN ('Door Install', 'Door + Opener Install')`)
- ✅ One-shot run on apply so prod has data immediately → Task 1 step 1 final SELECT
- ✅ pg_cron continues nightly without changes → not touched (existing `20260425170300` schedule still calls the same function)
- ✅ Reversibility — drop function body changes; thresholds harmless if left → covered by migration comment + DROP CONSTRAINT IF EXISTS pattern
- ✅ Frontend tests for the formatter (6 cases including unknown-kpi fallback) → Task 3 step 1
- ✅ Existing `useMyPersonalRecords` hook + `PersonalRecordTile` component untouched → covered by File Structure

**No placeholders.** All SQL, all test code, all React code is verbatim. The one place a runtime check matters is the variable name used in Recognition.tsx for the records array (Task 4 step 3 calls this out explicitly — implementer reads the file first, matches the local variable name).

**Type consistency:** `RecordPeriod`, `RecordInput`, `FormattedRecord`, `SectionDef` defined once in `recordFormatters.ts`, consumed in tests + Recognition.tsx. SQL `kpi_key` strings (`highest_install_ticket`, `highest_repair_ticket`, `revenue`, `total_jobs`, `avg_opportunity`) match the LABEL_MAP keys exactly.
