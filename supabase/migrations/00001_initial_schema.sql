-- Twins Garage Doors KPI Dashboard - Initial Schema
-- All monetary values stored as numeric(12,2) for dollar amounts

-- Users table (extends Supabase Auth)
CREATE TABLE IF NOT EXISTS public.users (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  auth_id UUID UNIQUE REFERENCES auth.users(id) ON DELETE CASCADE,
  email TEXT NOT NULL UNIQUE,
  full_name TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('technician', 'csr', 'manager', 'owner')),
  avatar_url TEXT,
  manager_id UUID REFERENCES public.users(id),
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Commission tiers (historical tracking)
CREATE TABLE IF NOT EXISTS public.commission_tiers (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  tier_level INTEGER NOT NULL CHECK (tier_level IN (1, 2, 3)),
  rate NUMERIC(5,4) NOT NULL CHECK (rate >= 0 AND rate <= 1),
  effective_date DATE NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_commission_tiers_user ON public.commission_tiers(user_id, effective_date DESC);

-- Customers
CREATE TABLE IF NOT EXISTS public.customers (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  hcp_id TEXT UNIQUE NOT NULL,
  name TEXT NOT NULL,
  email TEXT,
  phone TEXT,
  address TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_customers_hcp ON public.customers(hcp_id);

-- Jobs
CREATE TABLE IF NOT EXISTS public.jobs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  hcp_id TEXT UNIQUE NOT NULL,
  customer_id UUID REFERENCES public.customers(id),
  technician_id UUID REFERENCES public.users(id),
  job_type TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'created',
  scheduled_at TIMESTAMPTZ,
  completed_at TIMESTAMPTZ,
  revenue NUMERIC(12,2) NOT NULL DEFAULT 0,
  parts_cost NUMERIC(12,2) NOT NULL DEFAULT 0,
  parts_cost_override NUMERIC(12,2),
  protection_plan_sold BOOLEAN NOT NULL DEFAULT false,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_jobs_hcp ON public.jobs(hcp_id);
CREATE INDEX idx_jobs_technician ON public.jobs(technician_id, created_at DESC);
CREATE INDEX idx_jobs_status ON public.jobs(status);
CREATE INDEX idx_jobs_completed ON public.jobs(completed_at DESC) WHERE completed_at IS NOT NULL;

-- Invoices
CREATE TABLE IF NOT EXISTS public.invoices (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  hcp_id TEXT UNIQUE NOT NULL,
  job_id UUID REFERENCES public.jobs(id),
  customer_id UUID REFERENCES public.customers(id),
  amount NUMERIC(12,2) NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'created',
  paid_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_invoices_hcp ON public.invoices(hcp_id);
CREATE INDEX idx_invoices_job ON public.invoices(job_id);

-- Estimates
CREATE TABLE IF NOT EXISTS public.estimates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  hcp_id TEXT UNIQUE NOT NULL,
  customer_id UUID REFERENCES public.customers(id),
  technician_id UUID REFERENCES public.users(id),
  status TEXT NOT NULL DEFAULT 'created',
  amount NUMERIC(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Leads
CREATE TABLE IF NOT EXISTS public.leads (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  source TEXT NOT NULL,
  channel TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'new',
  customer_name TEXT,
  customer_phone TEXT,
  customer_email TEXT,
  converted_at TIMESTAMPTZ,
  job_id UUID REFERENCES public.jobs(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_leads_channel ON public.leads(channel, created_at DESC);

-- Commission Records
CREATE TABLE IF NOT EXISTS public.commission_records (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id UUID NOT NULL REFERENCES public.jobs(id),
  technician_id UUID NOT NULL REFERENCES public.users(id),
  gross_revenue NUMERIC(12,2) NOT NULL,
  parts_cost NUMERIC(12,2) NOT NULL,
  net_revenue NUMERIC(12,2) NOT NULL,
  tier_rate NUMERIC(5,4) NOT NULL,
  commission_amount NUMERIC(12,2) NOT NULL,
  manager_id UUID REFERENCES public.users(id),
  manager_override NUMERIC(12,2) NOT NULL DEFAULT 0,
  manager_bonus NUMERIC(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_commission_records_tech ON public.commission_records(technician_id, created_at DESC);
CREATE INDEX idx_commission_records_job ON public.commission_records(job_id);

-- Call Records
CREATE TABLE IF NOT EXISTS public.call_records (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  caller_name TEXT,
  caller_phone TEXT,
  source TEXT NOT NULL,
  channel TEXT NOT NULL,
  duration_seconds INTEGER NOT NULL DEFAULT 0,
  outcome TEXT NOT NULL DEFAULT 'unknown',
  notes TEXT,
  csr_id UUID REFERENCES public.users(id),
  ghl_agency TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_call_records_csr ON public.call_records(csr_id, created_at DESC);
CREATE INDEX idx_call_records_source ON public.call_records(source);

-- Marketing Spend
CREATE TABLE IF NOT EXISTS public.marketing_spend (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  channel TEXT NOT NULL,
  campaign TEXT,
  spend NUMERIC(12,2) NOT NULL DEFAULT 0,
  impressions INTEGER NOT NULL DEFAULT 0,
  clicks INTEGER NOT NULL DEFAULT 0,
  conversions INTEGER NOT NULL DEFAULT 0,
  date DATE NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_marketing_spend_channel ON public.marketing_spend(channel, date DESC);

-- Reviews
CREATE TABLE IF NOT EXISTS public.reviews (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  google_review_id TEXT UNIQUE,
  reviewer_name TEXT NOT NULL,
  rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
  review_text TEXT,
  technician_id UUID REFERENCES public.users(id),
  review_date TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_reviews_tech ON public.reviews(technician_id, review_date DESC);
CREATE INDEX idx_reviews_rating ON public.reviews(rating);

-- KPI Definitions (dynamic configuration)
CREATE TABLE IF NOT EXISTS public.kpi_definitions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL UNIQUE,
  description TEXT,
  formula TEXT NOT NULL,
  data_source TEXT NOT NULL,
  target NUMERIC(12,2) NOT NULL DEFAULT 0,
  display_format TEXT NOT NULL DEFAULT 'count' CHECK (display_format IN ('currency', 'percentage', 'count')),
  is_active BOOLEAN NOT NULL DEFAULT true,
  inverted_status BOOLEAN NOT NULL DEFAULT false,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Raw Events (webhook storage)
CREATE TABLE IF NOT EXISTS public.raw_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  event_type TEXT NOT NULL,
  source TEXT NOT NULL,
  payload JSONB NOT NULL,
  received_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  processed_at TIMESTAMPTZ,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'processed', 'failed')),
  error_message TEXT
);

CREATE INDEX idx_raw_events_type ON public.raw_events(event_type, received_at DESC);
CREATE INDEX idx_raw_events_status ON public.raw_events(status) WHERE status != 'processed';

-- Audit Log
CREATE TABLE IF NOT EXISTS public.audit_log (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  table_name TEXT NOT NULL,
  record_id TEXT NOT NULL,
  action TEXT NOT NULL CHECK (action IN ('INSERT', 'UPDATE', 'DELETE')),
  old_data JSONB,
  new_data JSONB,
  user_id UUID REFERENCES public.users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_audit_log_table ON public.audit_log(table_name, created_at DESC);

-- Updated_at trigger function
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply updated_at triggers
CREATE TRIGGER set_updated_at BEFORE UPDATE ON public.users FOR EACH ROW EXECUTE FUNCTION update_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON public.customers FOR EACH ROW EXECUTE FUNCTION update_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON public.jobs FOR EACH ROW EXECUTE FUNCTION update_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON public.invoices FOR EACH ROW EXECUTE FUNCTION update_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON public.estimates FOR EACH ROW EXECUTE FUNCTION update_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON public.leads FOR EACH ROW EXECUTE FUNCTION update_updated_at();
CREATE TRIGGER set_updated_at BEFORE UPDATE ON public.kpi_definitions FOR EACH ROW EXECUTE FUNCTION update_updated_at();
