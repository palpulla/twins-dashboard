-- supabase/migrations/00008_past_digests_summary.sql
CREATE OR REPLACE FUNCTION public.past_digests_summary(days INT DEFAULT 14)
RETURNS TABLE (
  digest_date DATE,
  ticket_count INT,
  issue_count INT,
  resolved_count INT,
  ignored_count INT
)
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT
    digest_date,
    COUNT(DISTINCT job_id)::INT AS ticket_count,
    COUNT(*)::INT AS issue_count,
    COUNT(*) FILTER (WHERE resolved_at IS NOT NULL)::INT AS resolved_count,
    0::INT AS ignored_count
  FROM public.supervisor_alerts
  WHERE digest_date >= CURRENT_DATE - days
  GROUP BY digest_date
  ORDER BY digest_date DESC;
$$;

GRANT EXECUTE ON FUNCTION public.past_digests_summary(INT) TO authenticated;
