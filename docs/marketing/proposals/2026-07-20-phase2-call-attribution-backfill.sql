-- Phase 2 call attribution — populate job_attribution_calls from calls_inbound + jobs.
-- Runs once calls_inbound has data (after GHL number pools are deployed + the
-- GHL -> calls_inbound sync runs). Table created by migration create_job_attribution_calls.
--
-- Deterministic only: exact last-10 phone match; the call precedes the job (within
-- 90 days); one channel per job (the closest call at/before it). HCP lead_source is
-- never touched. Reversible: TRUNCATE public.job_attribution_calls;
--
-- The channel CASE below is a best-effort normalization; source_raw is preserved so
-- the final canonical mapping reconciles with ghlSourceToCanonical (production parity),
-- same as the 2026-07-06 GHL build. Do NOT keyword-guess business rules beyond mapping
-- GHL's explicit source names.

WITH c AS (
  SELECT ci.id AS call_id, ci.created_at AS call_at, ci.source,
         right(regexp_replace(coalesce(ci.phone_number,''),'\D','','g'),10) AS p10
  FROM calls_inbound ci
  WHERE coalesce(btrim(ci.source),'') <> ''
    AND length(regexp_replace(coalesce(ci.phone_number,''),'\D','','g')) >= 10
),
j AS (
  SELECT jb.id AS job_id, jb.created_at AS job_at,
         right(regexp_replace(coalesce(
           jb.hcp_data->'customer'->>'mobile_number',
           jb.hcp_data->'customer'->>'home_number',
           jb.hcp_data->'customer'->>'work_number',''),'\D','','g'),10) AS p10
  FROM jobs jb
  WHERE jb.job_type <> 'Estimate'
),
best AS (
  SELECT j.job_id, c.call_id, c.source, c.p10,
         count(*) OVER (PARTITION BY j.job_id) AS candidate_count,
         row_number() OVER (PARTITION BY j.job_id ORDER BY c.call_at DESC) AS rn
  FROM j JOIN c ON c.p10 = j.p10
  WHERE c.call_at <= j.job_at + interval '2 days'
    AND c.call_at >= j.job_at - interval '90 days'
)
INSERT INTO public.job_attribution_calls
  (job_id, channel, matched_phone, call_id, source_raw, match_method, candidate_count, mapped_at)
SELECT b.job_id,
  CASE
    WHEN b.source ILIKE '%lsa%' OR b.source ILIKE '%local service%'     THEN 'Google LSA'
    WHEN b.source ILIKE '%google ads%' OR b.source ILIKE '%google_ads%' THEN 'Google Ads'
    WHEN b.source ILIKE '%facebook%' OR b.source ILIKE '%meta%'         THEN 'Facebook'
    WHEN b.source ILIKE '%gbp%' OR b.source ILIKE '%business profile%'  THEN 'Google (GBP)'
    WHEN b.source ILIKE '%website%' OR b.source ILIKE '%number pool%'   THEN 'Website'
    WHEN b.source ILIKE 'google%'                                       THEN 'Google'
    ELSE b.source
  END AS channel,
  b.p10, b.call_id, b.source, 'phone_last10_exact', b.candidate_count, now()
FROM best b
WHERE b.rn = 1
ON CONFLICT (job_id) DO UPDATE
  SET channel = EXCLUDED.channel, matched_phone = EXCLUDED.matched_phone,
      call_id = EXCLUDED.call_id, source_raw = EXCLUDED.source_raw,
      match_method = EXCLUDED.match_method, candidate_count = EXCLUDED.candidate_count,
      mapped_at = now();

-- Before/after check to report in the Monday brief (run after the insert):
--   SELECT count(*) AS newly_attributed,
--          sum(j.revenue_amount) FILTER (WHERE coalesce((j.hcp_data->>'outstanding_balance')::numeric,0)=0) AS earned_recovered
--   FROM job_attribution_calls a JOIN jobs j ON j.id=a.job_id
--   WHERE (j.lead_source IS NULL OR btrim(j.lead_source)='' OR j.lead_source='Unknown');
