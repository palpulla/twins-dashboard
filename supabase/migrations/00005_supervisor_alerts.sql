-- Supervisor alerts: schema for end-of-day digest pipeline
-- Adds work-tracking fields to jobs, plus job_technicians junction,
-- app_settings (single-row config), and supervisor_alerts audit table.

-- 1. Add fields the digest needs on the existing jobs table
ALTER TABLE public.jobs
  ADD COLUMN IF NOT EXISTS work_notes TEXT,
  ADD COLUMN IF NOT EXISTS started_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS invoiced_at TIMESTAMPTZ;

-- 2. Junction for HCP multi-tech assignments (used for Charles co-tech attribution)
CREATE TABLE IF NOT EXISTS public.job_technicians (
  job_id UUID NOT NULL REFERENCES public.jobs(id) ON DELETE CASCADE,
  technician_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  assigned_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  PRIMARY KEY (job_id, technician_id)
);
CREATE INDEX IF NOT EXISTS idx_job_technicians_tech ON public.job_technicians (technician_id);

-- 3. Single-row settings for the digest pipeline
CREATE TABLE IF NOT EXISTS public.app_settings (
  id INTEGER PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  digest_time TIME NOT NULL DEFAULT '18:00',
  digest_timezone TEXT NOT NULL DEFAULT 'America/Chicago',
  digest_cron_expression TEXT NOT NULL DEFAULT '0 23 * * *',
  digest_recipient_email TEXT NOT NULL,
  notes_threshold_dollars INTEGER NOT NULL DEFAULT 185,
  pay_grace_hours INTEGER NOT NULL DEFAULT 48,
  enabled_button_checks TEXT[] NOT NULL DEFAULT ARRAY['SCHEDULE','OMW','START','FINISH','INVOICE','PAY'],
  co_tech_default_user_id UUID REFERENCES public.users(id),
  last_digest_sent_at TIMESTAMPTZ,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 4. Audit/state table for individual alert rows
CREATE TABLE IF NOT EXISTS public.supervisor_alerts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  digest_date DATE NOT NULL,
  job_id UUID NOT NULL REFERENCES public.jobs(id) ON DELETE CASCADE,
  alert_type TEXT NOT NULL CHECK (alert_type IN ('missing_buttons', 'missing_notes')),
  details JSONB NOT NULL,
  attributed_tech_id UUID REFERENCES public.users(id),
  resolved_at TIMESTAMPTZ,
  resolved_by UUID REFERENCES public.users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT supervisor_alerts_unique_per_day UNIQUE NULLS NOT DISTINCT (job_id, alert_type, digest_date)
);

CREATE INDEX IF NOT EXISTS idx_supervisor_alerts_unresolved
  ON public.supervisor_alerts (digest_date DESC) WHERE resolved_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_supervisor_alerts_job
  ON public.supervisor_alerts (job_id);

-- 5. RLS — admins/owners can read+write everything; everyone else blocked
ALTER TABLE public.app_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.supervisor_alerts ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.job_technicians ENABLE ROW LEVEL SECURITY;

CREATE POLICY "owners_manage_app_settings" ON public.app_settings
  FOR ALL TO authenticated
  USING (EXISTS (SELECT 1 FROM public.users WHERE auth_id = auth.uid() AND role IN ('owner','manager')))
  WITH CHECK (EXISTS (SELECT 1 FROM public.users WHERE auth_id = auth.uid() AND role IN ('owner','manager')));

CREATE POLICY "owners_manage_supervisor_alerts" ON public.supervisor_alerts
  FOR ALL TO authenticated
  USING (EXISTS (SELECT 1 FROM public.users WHERE auth_id = auth.uid() AND role IN ('owner','manager')))
  WITH CHECK (EXISTS (SELECT 1 FROM public.users WHERE auth_id = auth.uid() AND role IN ('owner','manager')));

CREATE POLICY "read_job_technicians" ON public.job_technicians
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "service_role_writes_job_technicians" ON public.job_technicians
  FOR ALL TO service_role USING (true) WITH CHECK (true);
