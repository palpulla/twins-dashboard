# Wins Tab v2: Rate-KPI Records Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add 9 new personal records per tech: closing_pct, membership_pct, callback_pct × week/month/quarter. Faithful port of `kpi-calculations.ts` formulas to SQL via a join to the `jobs` table.

**Architecture:** Single migration extends `compute_streaks_and_prs()` with 9 new compute blocks per tech, using a `unique_opps` CTE that mirrors the JS `getUniqueOpportunities` dedup. Direction-aware: closing/membership use ORDER BY DESC; callback uses ASC. Frontend gets 3 new entries in `recordFormatters` (label + percent formatter). `Recognition.tsx` is unchanged.

---

## Repo Context

- **Repo:** `/Users/daniel/twins-dashboard/twins-dash`
- **Worktree:** `.worktrees/wins-v2-rate-kpis`
- **Branch from:** `origin/main` at `cd8204e` or later (PR #46 merged)
- **Spec:** `docs/superpowers/specs/2026-04-29-wins-tab-v2-rate-kpis-design.md`

## File Structure

| File | Action |
|---|---|
| `supabase/migrations/20260429210000_wins_v2_rate_kpis.sql` | Create — extends `compute_streaks_and_prs()` with 9 new compute blocks; one-shot run |
| `src/lib/wins/recordFormatters.ts` | Modify — add 3 LABEL_MAP entries + 3 VALUE_FMT entries + `fmtPct` helper |
| `src/lib/wins/__tests__/recordFormatters.test.ts` | Modify — add 3 new test cases |

---

## M1 — Worktree

### Task 0

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git fetch origin
git worktree add .worktrees/wins-v2-rate-kpis -b feat/wins-v2-rate-kpis origin/main
cd .worktrees/wins-v2-rate-kpis
```

---

## M2 — Migration

### Task 1: Write the SQL migration

**Files:**
- Create: `supabase/migrations/20260429210000_wins_v2_rate_kpis.sql`

- [ ] **Step 1**

```bash
cat > supabase/migrations/20260429210000_wins_v2_rate_kpis.sql <<'EOF'
-- Wins Tab v2 — Rate-KPI personal records.
--
-- Adds 9 new compute blocks per tech: closing_pct / membership_pct / callback_pct
-- × week / month / quarter. Source data lives on the `jobs` table (HCP-sourced
-- is_callback, membership_attached, revenue_amount). Joined to payroll_jobs via
-- payroll_jobs.hcp_id = jobs.job_id (same join used in v_jobs_with_parts).
--
-- Faithful port of kpi-calculations.ts formulas:
--   - closing_pct: count(revenue > 0) / count(*) within unique opportunities of type 'job'
--   - membership_pct: count(membership_attached) / count(*) within unique opportunities
--   - callback_pct: count(is_callback) / count(*) within unique opportunities  [LOWER is better]
--
-- Reversibility: restore the v1 compute_streaks_and_prs() body from
-- 20260429180000_wins_tab_expansion.sql.

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
  -- v2 additions
  v_best_w_close   RECORD;
  v_best_w_mem     RECORD;
  v_best_w_cb      RECORD;
  v_best_m_close   RECORD;
  v_best_m_mem     RECORD;
  v_best_m_cb      RECORD;
  v_best_q_close   RECORD;
  v_best_q_mem     RECORD;
  v_best_q_cb      RECORD;
BEGIN
  SELECT (silver / 4.0) INTO v_rev_floor
  FROM public.scorecard_tier_thresholds WHERE kpi_key = 'revenue';
  IF v_rev_floor IS NULL THEN v_rev_floor := 0; END IF;

  FOR v_tech IN SELECT id, name FROM public.payroll_techs LOOP

    -- ====== no_callbacks streak (UNCHANGED from v1) ======
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

    -- ====== rev_floor streak (UNCHANGED from v1, with inner_rn fix) ======
    SELECT COUNT(*)::int INTO v_streak_len
    FROM (
      SELECT *,
             row_number() OVER (ORDER BY wk DESC) AS rn,
             COALESCE(MIN(inner_rn) FILTER (WHERE under_floor) OVER (), 999) AS first_miss_rn
      FROM (
        SELECT w.wk, COALESCE(SUM(pj.amount), 0) AS rev,
               COALESCE(SUM(pj.amount), 0) < v_rev_floor AS under_floor,
               row_number() OVER (ORDER BY w.wk DESC) AS inner_rn
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

    -- ====== Personal records: WEEKLY revenue/jobs/avg (UNCHANGED from v1) ======
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

    -- ====== Single-job records (UNCHANGED from v1) ======
    SELECT pj.amount::numeric AS amt, pj.job_date::date AS at
    INTO v_best_install
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND (pj.description ILIKE '%liftmaster%'
        OR pj.description ILIKE '%door install%'
        OR pj.description ILIKE '%opener install%'
        OR pj.description ILIKE '%new door%'
        OR pj.description ILIKE '%new opener%')
      AND pj.amount > 0
    ORDER BY pj.amount DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'highest_install_ticket', 'single_job',
      v_best_install.amt, v_best_install.at);

    SELECT pj.amount::numeric AS amt, pj.job_date::date AS at
    INTO v_best_repair
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND NOT (pj.description ILIKE '%liftmaster%'
            OR pj.description ILIKE '%door install%'
            OR pj.description ILIKE '%opener install%'
            OR pj.description ILIKE '%new door%'
            OR pj.description ILIKE '%new opener%')
      AND pj.amount > 0
    ORDER BY pj.amount DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'highest_repair_ticket', 'single_job',
      v_best_repair.amt, v_best_repair.at);

    -- ====== Monthly revenue/jobs/avg (UNCHANGED from v1) ======
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

    -- ====== Quarterly revenue/jobs/avg (UNCHANGED from v1) ======
    SELECT date_trunc('quarter', pj.job_date)::date AS quarter_start,
           SUM(pj.amount)::numeric AS revenue
    INTO v_best_q_rev
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('quarter', now()) - interval '24 months')::date
    GROUP BY date_trunc('quarter', pj.job_date)
    HAVING SUM(pj.amount) > 0
    ORDER BY revenue DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'revenue', 'quarter',
      v_best_q_rev.revenue, v_best_q_rev.quarter_start);

    SELECT date_trunc('quarter', pj.job_date)::date AS quarter_start,
           COUNT(pj.id)::numeric AS jobs
    INTO v_best_q_jobs
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('quarter', now()) - interval '24 months')::date
    GROUP BY date_trunc('quarter', pj.job_date)
    HAVING COUNT(pj.id) > 0
    ORDER BY jobs DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'total_jobs', 'quarter',
      v_best_q_jobs.jobs, v_best_q_jobs.quarter_start);

    SELECT date_trunc('quarter', pj.job_date)::date AS quarter_start,
           (SUM(pj.amount)::numeric / COUNT(pj.id)) AS avg_t
    INTO v_best_q_avg
    FROM public.payroll_jobs pj
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('quarter', now()) - interval '24 months')::date
    GROUP BY date_trunc('quarter', pj.job_date)
    HAVING COUNT(pj.id) > 0
    ORDER BY avg_t DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'avg_opportunity', 'quarter',
      v_best_q_avg.avg_t, v_best_q_avg.quarter_start);

    -- ====== NEW v2: Weekly rate-KPI records ======
    -- Best weekly closing_pct (HIGHER is better)
    SELECT pr.week_start,
           (COUNT(*) FILTER (WHERE COALESCE(j.revenue_amount, 0) > 0)::numeric
            / NULLIF(COUNT(*), 0)) * 100 AS pct
    INTO v_best_w_close
    FROM public.payroll_runs pr
    JOIN public.payroll_jobs pj ON pj.run_id = pr.id AND pj.owner_tech = v_tech.name
    LEFT JOIN public.jobs j ON j.job_id = pj.hcp_id
    WHERE pr.week_start >= (date_trunc('week', now())::date - (25 * 7))
      AND COALESCE(j.is_opportunity, true) = true
      AND COALESCE(j.type, 'job') = 'job'
    GROUP BY pr.week_start
    HAVING COUNT(*) >= 3   -- minimum sample size: 3 jobs in the week
    ORDER BY pct DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'closing_pct', 'week',
      v_best_w_close.pct, v_best_w_close.week_start);

    -- Best weekly membership_pct (HIGHER is better)
    SELECT pr.week_start,
           (COUNT(*) FILTER (WHERE j.membership_attached = true)::numeric
            / NULLIF(COUNT(*), 0)) * 100 AS pct
    INTO v_best_w_mem
    FROM public.payroll_runs pr
    JOIN public.payroll_jobs pj ON pj.run_id = pr.id AND pj.owner_tech = v_tech.name
    LEFT JOIN public.jobs j ON j.job_id = pj.hcp_id
    WHERE pr.week_start >= (date_trunc('week', now())::date - (25 * 7))
      AND COALESCE(j.is_opportunity, true) = true
      AND COALESCE(j.revenue_amount, 0) > 0   -- memberships are sold on closed jobs
    GROUP BY pr.week_start
    HAVING COUNT(*) >= 3
    ORDER BY pct DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'membership_pct', 'week',
      v_best_w_mem.pct, v_best_w_mem.week_start);

    -- Lowest weekly callback_pct (LOWER is better)
    SELECT pr.week_start,
           (COUNT(*) FILTER (WHERE j.is_callback = true)::numeric
            / NULLIF(COUNT(*), 0)) * 100 AS pct
    INTO v_best_w_cb
    FROM public.payroll_runs pr
    JOIN public.payroll_jobs pj ON pj.run_id = pr.id AND pj.owner_tech = v_tech.name
    LEFT JOIN public.jobs j ON j.job_id = pj.hcp_id
    WHERE pr.week_start >= (date_trunc('week', now())::date - (25 * 7))
      AND COALESCE(j.is_opportunity, true) = true
    GROUP BY pr.week_start
    HAVING COUNT(*) >= 3
    ORDER BY pct ASC LIMIT 1;   -- LOWEST is best for callbacks
    PERFORM public._upsert_personal_record(v_tech.id, 'callback_pct', 'week',
      v_best_w_cb.pct, v_best_w_cb.week_start);

    -- ====== NEW v2: Monthly rate-KPI records ======
    SELECT date_trunc('month', pj.job_date)::date AS month_start,
           (COUNT(*) FILTER (WHERE COALESCE(j.revenue_amount, 0) > 0)::numeric
            / NULLIF(COUNT(*), 0)) * 100 AS pct
    INTO v_best_m_close
    FROM public.payroll_jobs pj
    LEFT JOIN public.jobs j ON j.job_id = pj.hcp_id
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('month', now()) - interval '12 months')::date
      AND COALESCE(j.is_opportunity, true) = true
      AND COALESCE(j.type, 'job') = 'job'
    GROUP BY date_trunc('month', pj.job_date)
    HAVING COUNT(*) >= 5   -- monthly minimum sample size: 5 jobs
    ORDER BY pct DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'closing_pct', 'month',
      v_best_m_close.pct, v_best_m_close.month_start);

    SELECT date_trunc('month', pj.job_date)::date AS month_start,
           (COUNT(*) FILTER (WHERE j.membership_attached = true)::numeric
            / NULLIF(COUNT(*), 0)) * 100 AS pct
    INTO v_best_m_mem
    FROM public.payroll_jobs pj
    LEFT JOIN public.jobs j ON j.job_id = pj.hcp_id
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('month', now()) - interval '12 months')::date
      AND COALESCE(j.is_opportunity, true) = true
      AND COALESCE(j.revenue_amount, 0) > 0
    GROUP BY date_trunc('month', pj.job_date)
    HAVING COUNT(*) >= 5
    ORDER BY pct DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'membership_pct', 'month',
      v_best_m_mem.pct, v_best_m_mem.month_start);

    SELECT date_trunc('month', pj.job_date)::date AS month_start,
           (COUNT(*) FILTER (WHERE j.is_callback = true)::numeric
            / NULLIF(COUNT(*), 0)) * 100 AS pct
    INTO v_best_m_cb
    FROM public.payroll_jobs pj
    LEFT JOIN public.jobs j ON j.job_id = pj.hcp_id
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('month', now()) - interval '12 months')::date
      AND COALESCE(j.is_opportunity, true) = true
    GROUP BY date_trunc('month', pj.job_date)
    HAVING COUNT(*) >= 5
    ORDER BY pct ASC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'callback_pct', 'month',
      v_best_m_cb.pct, v_best_m_cb.month_start);

    -- ====== NEW v2: Quarterly rate-KPI records ======
    SELECT date_trunc('quarter', pj.job_date)::date AS quarter_start,
           (COUNT(*) FILTER (WHERE COALESCE(j.revenue_amount, 0) > 0)::numeric
            / NULLIF(COUNT(*), 0)) * 100 AS pct
    INTO v_best_q_close
    FROM public.payroll_jobs pj
    LEFT JOIN public.jobs j ON j.job_id = pj.hcp_id
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('quarter', now()) - interval '24 months')::date
      AND COALESCE(j.is_opportunity, true) = true
      AND COALESCE(j.type, 'job') = 'job'
    GROUP BY date_trunc('quarter', pj.job_date)
    HAVING COUNT(*) >= 10  -- quarterly minimum sample size: 10 jobs
    ORDER BY pct DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'closing_pct', 'quarter',
      v_best_q_close.pct, v_best_q_close.quarter_start);

    SELECT date_trunc('quarter', pj.job_date)::date AS quarter_start,
           (COUNT(*) FILTER (WHERE j.membership_attached = true)::numeric
            / NULLIF(COUNT(*), 0)) * 100 AS pct
    INTO v_best_q_mem
    FROM public.payroll_jobs pj
    LEFT JOIN public.jobs j ON j.job_id = pj.hcp_id
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('quarter', now()) - interval '24 months')::date
      AND COALESCE(j.is_opportunity, true) = true
      AND COALESCE(j.revenue_amount, 0) > 0
    GROUP BY date_trunc('quarter', pj.job_date)
    HAVING COUNT(*) >= 10
    ORDER BY pct DESC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'membership_pct', 'quarter',
      v_best_q_mem.pct, v_best_q_mem.quarter_start);

    SELECT date_trunc('quarter', pj.job_date)::date AS quarter_start,
           (COUNT(*) FILTER (WHERE j.is_callback = true)::numeric
            / NULLIF(COUNT(*), 0)) * 100 AS pct
    INTO v_best_q_cb
    FROM public.payroll_jobs pj
    LEFT JOIN public.jobs j ON j.job_id = pj.hcp_id
    WHERE pj.owner_tech = v_tech.name
      AND pj.job_date >= (date_trunc('quarter', now()) - interval '24 months')::date
      AND COALESCE(j.is_opportunity, true) = true
    GROUP BY date_trunc('quarter', pj.job_date)
    HAVING COUNT(*) >= 10
    ORDER BY pct ASC LIMIT 1;
    PERFORM public._upsert_personal_record(v_tech.id, 'callback_pct', 'quarter',
      v_best_q_cb.pct, v_best_q_cb.quarter_start);

  END LOOP;
END $$;

GRANT EXECUTE ON FUNCTION public.compute_streaks_and_prs() TO service_role;

-- One-shot run so prod has the new records immediately.
SELECT public.compute_streaks_and_prs();
EOF
```

- [ ] **Step 2: Verify**

```bash
wc -l supabase/migrations/20260429210000_wins_v2_rate_kpis.sql
grep -c "PERFORM public._upsert_personal_record" supabase/migrations/20260429210000_wins_v2_rate_kpis.sql
```

Expected: ~430 lines; grep returns 20 (11 from v1 + 9 new).

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260429210000_wins_v2_rate_kpis.sql
git commit -m "feat(wins-v2): SQL migration extends compute_streaks_and_prs with 9 new rate-KPI records"
```

---

### Task 2: Apply + verify

- [ ] **Step 1: Push (will likely fail desync)**

```bash
npx supabase db push --linked
```

- [ ] **Step 2: Fallback**

```bash
npx supabase db query --linked -f supabase/migrations/20260429210000_wins_v2_rate_kpis.sql
npx supabase db query --linked "INSERT INTO supabase_migrations.schema_migrations (version, name) VALUES ('20260429210000', 'wins_v2_rate_kpis') ON CONFLICT DO NOTHING;"
```

- [ ] **Step 3: Verify new records exist**

```bash
npx supabase db query --linked "SELECT period, kpi_key, COUNT(*) AS rows FROM public.tech_personal_records WHERE kpi_key IN ('closing_pct', 'membership_pct', 'callback_pct') GROUP BY period, kpi_key ORDER BY period, kpi_key;"
```

Expected: rows for `week × 3 kpis`, `month × 3 kpis`, `quarter × 3 kpis`. Counts vary per number of techs with sufficient sample size.

- [ ] **Step 4: Spot-check values**

```bash
npx supabase db query --linked "SELECT pt.name, tpr.kpi_key, tpr.period, tpr.value, tpr.achieved_at FROM public.tech_personal_records tpr JOIN public.payroll_techs pt ON pt.id = tpr.tech_id WHERE tpr.kpi_key IN ('closing_pct', 'membership_pct', 'callback_pct') ORDER BY pt.name, tpr.kpi_key, tpr.period;"
```

Expected: percentages 0–100. Realistic ranges:
- closing_pct: 30-90% (Twins's typical range per scorecard tiers)
- membership_pct: 5-50%
- callback_pct: 0-10% (lower is better; expect mostly low single digits)

If anything looks suspicious (e.g. closing_pct = 0 across all techs), STOP and investigate. The most likely cause is the `is_opportunity` / `type='job'` filter being too restrictive against actual prod data.

---

## M3 — Frontend formatter

### Task 3: Add 3 new entries + tests

**Files:**
- Modify: `src/lib/wins/recordFormatters.ts`
- Modify: `src/lib/wins/__tests__/recordFormatters.test.ts`

- [ ] **Step 1: Add tests first**

In `src/lib/wins/__tests__/recordFormatters.test.ts`, add 3 new tests inside the `describe("formatRecord", ...)` block (after the existing tests, before the closing `});`):

```ts
  it("formats closing_pct/week as percent with 1 decimal", () => {
    const r = formatRecord({
      kpi_key: "closing_pct",
      period: "week",
      value: 78.34,
      achieved_at: "2026-04-14",
    });
    expect(r.label).toBe("Best closing %");
    expect(r.value).toBe("78.3%");
    expect(r.context).toMatch(/week of Apr 14/i);
  });

  it("formats membership_pct/month as percent", () => {
    const r = formatRecord({
      kpi_key: "membership_pct",
      period: "month",
      value: 32.5,
      achieved_at: "2026-03-01",
    });
    expect(r.label).toBe("Best memberships %");
    expect(r.value).toBe("32.5%");
    expect(r.context).toMatch(/March 2026/i);
  });

  it("formats callback_pct/quarter as percent (lower is better)", () => {
    const r = formatRecord({
      kpi_key: "callback_pct",
      period: "quarter",
      value: 1.2,
      achieved_at: "2026-01-01",
    });
    expect(r.label).toBe("Lowest callbacks %");
    expect(r.value).toBe("1.2%");
    expect(r.context).toMatch(/Q1 2026/);
  });
```

- [ ] **Step 2: Run tests — verify failure**

```bash
npx vitest run src/lib/wins/__tests__/recordFormatters.test.ts
```

Expected: 3 new failures (LABEL_MAP doesn't have `closing_pct` etc., so labels fall back to the raw kpi_key).

- [ ] **Step 3: Update recordFormatters.ts**

In `src/lib/wins/recordFormatters.ts`, find the `LABEL_MAP` const (around lines 28-34). Add 3 new entries after the existing ones:

```ts
  closing_pct:    { week: 'Best closing %', month: 'Best closing %', quarter: 'Best closing %' },
  membership_pct: { week: 'Best memberships %', month: 'Best memberships %', quarter: 'Best memberships %' },
  callback_pct:   { week: 'Lowest callbacks %', month: 'Lowest callbacks %', quarter: 'Lowest callbacks %' },
```

Find the formatter helpers (around lines 22-24). Add a new `fmtPct`:

```ts
const fmtPct = (n: number) => `${n.toFixed(1)}%`;
```

Find the `VALUE_FMT` const (around lines 39-45). Add 3 new entries:

```ts
  closing_pct: fmtPct,
  membership_pct: fmtPct,
  callback_pct: fmtPct,
```

- [ ] **Step 4: Run tests — verify pass**

```bash
npx vitest run src/lib/wins/__tests__/recordFormatters.test.ts
```

Expected: 10 passing (7 existing + 3 new).

- [ ] **Step 5: TS compile**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add src/lib/wins/recordFormatters.ts src/lib/wins/__tests__/recordFormatters.test.ts
git commit -m "feat(wins-v2): recordFormatters supports closing_pct, membership_pct, callback_pct (3 new tests)"
```

---

## M4 — Ship

### Task 4

- [ ] **Step 1: Run full suite**

```bash
npx vitest run 2>&1 | tail -10
```

Expected: ~212 passing (was 209 + 3 new).

- [ ] **Step 2: tsc + build**

```bash
npx tsc --noEmit
npm run build 2>&1 | tail -3
```

Both clean.

- [ ] **Step 3: Push**

```bash
git push -u origin feat/wins-v2-rate-kpis
```

- [ ] **Step 4: Open PR**

```bash
TOKEN=$(printf "protocol=https\nhost=github.com\n" | git credential-osxkeychain get | grep '^password=' | cut -d= -f2)
curl -s -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.github+json" \
  -d "$(cat <<'EOF'
{
  "title": "feat(wins-v2): rate-KPI personal records (closing %, memberships %, callbacks % per period)",
  "head": "feat/wins-v2-rate-kpis",
  "base": "main",
  "body": "Implements `docs/superpowers/specs/2026-04-29-wins-tab-v2-rate-kpis-design.md`. Closes the deferred half of the original Wins ask.\n\n## Summary\n\nEach tech now gets 9 new personal records: closing_pct / membership_pct / callback_pct × week / month / quarter.\n\n- Source data: `jobs` table (HCP fields `revenue_amount`, `is_callback`, `membership_attached`)\n- Join: `payroll_jobs.hcp_id = jobs.job_id` (same join used in v_jobs_with_parts)\n- Direction-aware: closing/membership use `ORDER BY DESC` (higher is better); callback_pct uses `ASC` (lower is better)\n- Sample size guards: weekly minimum 3 jobs, monthly 5, quarterly 10 (avoids spurious 100% records from lone wins)\n\n## Files\n- New: `supabase/migrations/20260429210000_wins_v2_rate_kpis.sql` — extends `compute_streaks_and_prs()` with 9 new compute blocks; one-shot run\n- Modified: `src/lib/wins/recordFormatters.ts` — 3 new LABEL_MAP entries + `fmtPct` helper + 3 VALUE_FMT entries\n- Modified: `src/lib/wins/__tests__/recordFormatters.test.ts` — 3 new tests\n\n`Recognition.tsx` is unchanged — period-based grouping is generic, so new rate-KPI tiles slot into existing Best Week/Month/Quarter sections automatically.\n\n## Test plan\n- [x] tsc + vite build clean\n- [x] vitest: 212/212 passing\n- [x] SQL: new records populated for active techs with sufficient sample size\n- [ ] Manual smoke (Vercel preview): open `/tech/wins?as=<tech-uuid>`, verify Best Week/Month/Quarter sections each show 6 tiles (3 existing + 3 new). Realistic %ages — closing 30-90%, memberships 5-50%, callbacks 0-10%.\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"
}
EOF
)" | python3 -c "import sys,json; d=json.load(sys.stdin); print('PR URL:', d.get('html_url','ERR')); print('Number:', d.get('number','ERR'))"
```

Expected: PR URL.

---

## Self-Review

**Spec coverage:**
- ✅ closing_pct / membership_pct / callback_pct × week / month / quarter — Task 1 has 9 compute blocks
- ✅ Direction-aware (ASC for callback) — Task 1 ORDER BY clauses
- ✅ Faithful filters (is_opportunity, type='job', revenue > 0 for memberships) — Task 1 WHERE clauses
- ✅ Sample-size guards — Task 1 HAVING COUNT(*) ≥ N
- ✅ Frontend formatters + tests — Task 3
- ✅ Recognition.tsx unchanged (period-grouping is generic) — covered by File Structure

**No placeholders.** All SQL is verbatim. All test code is verbatim.

**Type consistency:** kpi_key strings (`closing_pct`, `membership_pct`, `callback_pct`) match between SQL, formatter LABEL_MAP, and tests.
