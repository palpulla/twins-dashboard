-- supabase/migrations/00007_pg_cron_digest.sql
--
-- IMPORTANT: After deploying to production, run once on the live DB:
--   ALTER DATABASE postgres SET app.supabase_functions_url = 'https://<project>.supabase.co/functions/v1';
--   ALTER DATABASE postgres SET app.supabase_service_role_key = '<service-role-key>';
-- These GUCs are read by the cron-triggered http_post call below.

-- Enable pg_cron + pg_net (required for cron→edge-function HTTP call).
-- Wrapped in a guard so local environments without pg_cron extension don't break the migration chain.
DO $outer$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_available_extensions WHERE name = 'pg_cron')
     AND EXISTS (SELECT 1 FROM pg_available_extensions WHERE name = 'pg_net') THEN
    CREATE EXTENSION IF NOT EXISTS pg_cron;
    CREATE EXTENSION IF NOT EXISTS pg_net;

    -- Schedule the daily digest. Default: 0 23 * * * (23:00 UTC ≈ 18:00 CDT in DST).
    BEGIN
      PERFORM cron.schedule(
        'daily-supervisor-digest',
        (SELECT digest_cron_expression FROM public.app_settings WHERE id = 1),
        $cron$
        SELECT net.http_post(
          url := current_setting('app.supabase_functions_url', true) || '/daily-supervisor-digest',
          headers := jsonb_build_object(
            'Content-Type', 'application/json',
            'Authorization', 'Bearer ' || current_setting('app.supabase_service_role_key', true)
          )
        ) AS request_id;
        $cron$
      );
    EXCEPTION WHEN duplicate_object THEN
      -- job already exists; ignore
      NULL;
    WHEN OTHERS THEN
      RAISE NOTICE 'cron.schedule call failed: %', SQLERRM;
    END;
  ELSE
    RAISE NOTICE 'pg_cron/pg_net not available; daily digest must be triggered via external scheduler';
  END IF;
END
$outer$;

-- A SECURITY DEFINER helper for the Next.js API to use.
-- Created unconditionally so the function exists in production once pg_cron is available.
-- The body guards against pg_cron being absent so the function is callable but a no-op locally.
CREATE OR REPLACE FUNCTION public.reschedule_digest(new_cron_expression TEXT)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, cron
AS $$
DECLARE
  job_id BIGINT;
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_cron') THEN
    RAISE NOTICE 'pg_cron not installed; updating app_settings only';
    UPDATE public.app_settings SET digest_cron_expression = new_cron_expression, updated_at = now() WHERE id = 1;
    RETURN;
  END IF;

  SELECT jobid INTO job_id FROM cron.job WHERE jobname = 'daily-supervisor-digest';
  IF job_id IS NOT NULL THEN
    PERFORM cron.alter_job(job_id, schedule := new_cron_expression);
    UPDATE public.app_settings SET digest_cron_expression = new_cron_expression, updated_at = now() WHERE id = 1;
  END IF;
END;
$$;

REVOKE ALL ON FUNCTION public.reschedule_digest(TEXT) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.reschedule_digest(TEXT) TO authenticated;
