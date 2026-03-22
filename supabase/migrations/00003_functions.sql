-- KPI Calculation Functions
-- These run server-side in PostgreSQL for performance

-- Calculate average ticket for a technician in a date range
CREATE OR REPLACE FUNCTION calc_avg_ticket(
  p_technician_id UUID DEFAULT NULL,
  p_from TIMESTAMPTZ DEFAULT '-infinity',
  p_to TIMESTAMPTZ DEFAULT 'infinity'
)
RETURNS NUMERIC AS $$
  SELECT COALESCE(AVG(revenue), 0)
  FROM public.jobs
  WHERE status = 'completed'
    AND revenue > 0
    AND job_type NOT IN ('Warranty Call')
    AND (p_technician_id IS NULL OR technician_id = p_technician_id)
    AND created_at >= p_from
    AND created_at <= p_to;
$$ LANGUAGE sql STABLE;

-- Calculate conversion rate
CREATE OR REPLACE FUNCTION calc_conversion_rate(
  p_technician_id UUID DEFAULT NULL,
  p_from TIMESTAMPTZ DEFAULT '-infinity',
  p_to TIMESTAMPTZ DEFAULT 'infinity'
)
RETURNS NUMERIC AS $$
  SELECT CASE
    WHEN COUNT(*) = 0 THEN 0
    ELSE (COUNT(*) FILTER (WHERE status = 'completed' AND revenue > 0)::NUMERIC / COUNT(*)::NUMERIC) * 100
  END
  FROM public.jobs
  WHERE (p_technician_id IS NULL OR technician_id = p_technician_id)
    AND created_at >= p_from
    AND created_at <= p_to;
$$ LANGUAGE sql STABLE;

-- Calculate total commission for a technician
CREATE OR REPLACE FUNCTION calc_total_commission(
  p_technician_id UUID,
  p_from TIMESTAMPTZ DEFAULT '-infinity',
  p_to TIMESTAMPTZ DEFAULT 'infinity'
)
RETURNS NUMERIC AS $$
  SELECT COALESCE(SUM(commission_amount), 0)
  FROM public.commission_records
  WHERE technician_id = p_technician_id
    AND created_at >= p_from
    AND created_at <= p_to;
$$ LANGUAGE sql STABLE;

-- Calculate manager total override + bonus
CREATE OR REPLACE FUNCTION calc_manager_earnings(
  p_manager_id UUID,
  p_from TIMESTAMPTZ DEFAULT '-infinity',
  p_to TIMESTAMPTZ DEFAULT 'infinity'
)
RETURNS TABLE(total_override NUMERIC, total_bonus NUMERIC) AS $$
  SELECT
    COALESCE(SUM(manager_override), 0),
    COALESCE(SUM(manager_bonus), 0)
  FROM public.commission_records
  WHERE manager_id = p_manager_id
    AND created_at >= p_from
    AND created_at <= p_to;
$$ LANGUAGE sql STABLE;

-- Get technician scorecard data
CREATE OR REPLACE FUNCTION get_tech_scorecard(
  p_technician_id UUID,
  p_from TIMESTAMPTZ,
  p_to TIMESTAMPTZ
)
RETURNS TABLE(
  avg_ticket NUMERIC,
  avg_opportunity NUMERIC,
  conversion_rate NUMERIC,
  avg_repair_ticket NUMERIC,
  avg_install_ticket NUMERIC,
  new_doors_installed BIGINT,
  total_opportunities BIGINT,
  five_star_reviews BIGINT,
  protection_plan_sales BIGINT,
  total_commission NUMERIC,
  callback_rate NUMERIC
) AS $$
  WITH job_data AS (
    SELECT * FROM public.jobs
    WHERE technician_id = p_technician_id
      AND created_at >= p_from AND created_at <= p_to
  ),
  completed AS (
    SELECT * FROM job_data WHERE status = 'completed'
  ),
  revenue_jobs AS (
    SELECT * FROM completed WHERE revenue > 0 AND job_type NOT IN ('Warranty Call')
  )
  SELECT
    COALESCE(AVG(revenue) FILTER (WHERE id IN (SELECT id FROM revenue_jobs)), 0),
    COALESCE(AVG(revenue) FILTER (WHERE id IN (SELECT id FROM completed)), 0),
    CASE WHEN COUNT(*) = 0 THEN 0
      ELSE (COUNT(*) FILTER (WHERE status = 'completed' AND revenue > 0)::NUMERIC / COUNT(*)::NUMERIC) * 100
    END,
    COALESCE(AVG(revenue) FILTER (WHERE id IN (SELECT id FROM revenue_jobs) AND job_type IN ('Repair', 'Service Call', 'Opener + Repair')), 0),
    COALESCE(AVG(revenue) FILTER (WHERE id IN (SELECT id FROM revenue_jobs) AND job_type IN ('Door Install', 'Opener Install', 'Door + Opener Install')), 0),
    COUNT(*) FILTER (WHERE status = 'completed' AND job_type IN ('Door Install', 'Door + Opener Install')),
    COUNT(*),
    (SELECT COUNT(*) FROM public.reviews WHERE technician_id = p_technician_id AND rating = 5 AND created_at >= p_from AND created_at <= p_to),
    COUNT(*) FILTER (WHERE status = 'completed' AND protection_plan_sold = true),
    (SELECT COALESCE(SUM(commission_amount), 0) FROM public.commission_records WHERE technician_id = p_technician_id AND created_at >= p_from AND created_at <= p_to),
    CASE WHEN COUNT(*) FILTER (WHERE status = 'completed') = 0 THEN 0
      ELSE (COUNT(*) FILTER (WHERE status = 'completed' AND job_type = 'Warranty Call')::NUMERIC / COUNT(*) FILTER (WHERE status = 'completed')::NUMERIC) * 100
    END
  FROM job_data;
$$ LANGUAGE sql STABLE;
