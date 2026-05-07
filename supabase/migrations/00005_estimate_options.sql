-- Options / Ticket KPI: estimate options table + estimates.job_id column
-- See docs/superpowers/specs/2026-05-07-options-per-ticket-kpi-design.md

-- Add job_id to estimates so we can distinguish standalone estimate tickets
-- from estimates attached to a job, and roll options up to the parent job.
ALTER TABLE public.estimates
  ADD COLUMN IF NOT EXISTS job_id UUID REFERENCES public.jobs(id);

CREATE INDEX IF NOT EXISTS idx_estimates_job ON public.estimates(job_id);

-- Estimate Options (Good / Better / Best lines on an HCP estimate)
CREATE TABLE IF NOT EXISTS public.estimate_options (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  hcp_id TEXT UNIQUE NOT NULL,
  estimate_hcp_id TEXT NOT NULL,
  estimate_id UUID REFERENCES public.estimates(id),
  name TEXT,
  amount NUMERIC(12,2) NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'created',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_estimate_options_estimate_hcp
  ON public.estimate_options(estimate_hcp_id);
CREATE INDEX IF NOT EXISTS idx_estimate_options_estimate
  ON public.estimate_options(estimate_id, created_at);

CREATE TRIGGER set_updated_at
  BEFORE UPDATE ON public.estimate_options
  FOR EACH ROW EXECUTE FUNCTION update_updated_at();

ALTER TABLE public.estimate_options ENABLE ROW LEVEL SECURITY;

-- Read policy mirrors estimates_select: managers/owners read all,
-- technicians read options on their own estimates.
CREATE POLICY estimate_options_select ON public.estimate_options FOR SELECT USING (
  get_user_role() IN ('owner', 'manager')
  OR EXISTS (
    SELECT 1 FROM public.estimates e
    WHERE e.id = estimate_options.estimate_id
      AND e.technician_id = get_user_id()
  )
);
