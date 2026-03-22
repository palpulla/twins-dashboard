-- Row Level Security Policies
-- Technicians see own data, managers see team, owners see all

-- Enable RLS on all tables
ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.jobs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.invoices ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.customers ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.estimates ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.leads ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.commission_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.commission_tiers ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.call_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.marketing_spend ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.reviews ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.kpi_definitions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.raw_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.audit_log ENABLE ROW LEVEL SECURITY;

-- Helper function: get current user's role
CREATE OR REPLACE FUNCTION get_user_role()
RETURNS TEXT AS $$
  SELECT role FROM public.users WHERE auth_id = auth.uid();
$$ LANGUAGE sql SECURITY DEFINER STABLE;

-- Helper function: get current user's internal ID
CREATE OR REPLACE FUNCTION get_user_id()
RETURNS UUID AS $$
  SELECT id FROM public.users WHERE auth_id = auth.uid();
$$ LANGUAGE sql SECURITY DEFINER STABLE;

-- Helper function: check if user manages a technician
CREATE OR REPLACE FUNCTION manages_user(target_user_id UUID)
RETURNS BOOLEAN AS $$
  SELECT EXISTS (
    SELECT 1 FROM public.users
    WHERE id = target_user_id AND manager_id = get_user_id()
  );
$$ LANGUAGE sql SECURITY DEFINER STABLE;

-- Users: everyone can see basic user info, only owners can modify
CREATE POLICY users_select ON public.users FOR SELECT USING (true);
CREATE POLICY users_insert ON public.users FOR INSERT WITH CHECK (get_user_role() = 'owner');
CREATE POLICY users_update ON public.users FOR UPDATE USING (get_user_role() = 'owner');
CREATE POLICY users_delete ON public.users FOR DELETE USING (get_user_role() = 'owner');

-- Jobs: technicians see own, managers see team, owners see all
CREATE POLICY jobs_select ON public.jobs FOR SELECT USING (
  get_user_role() = 'owner'
  OR technician_id = get_user_id()
  OR manages_user(technician_id)
);

-- Invoices: same pattern as jobs
CREATE POLICY invoices_select ON public.invoices FOR SELECT USING (
  get_user_role() = 'owner'
  OR EXISTS (SELECT 1 FROM public.jobs WHERE jobs.id = invoices.job_id AND (jobs.technician_id = get_user_id() OR manages_user(jobs.technician_id)))
);

-- Commission Records: tech sees own, manager sees team, owner sees all
CREATE POLICY commission_select ON public.commission_records FOR SELECT USING (
  get_user_role() = 'owner'
  OR technician_id = get_user_id()
  OR manages_user(technician_id)
);

-- Commission Tiers: tech sees own, owner can modify
CREATE POLICY commission_tiers_select ON public.commission_tiers FOR SELECT USING (
  get_user_role() = 'owner'
  OR user_id = get_user_id()
  OR manages_user(user_id)
);
CREATE POLICY commission_tiers_modify ON public.commission_tiers FOR ALL USING (get_user_role() = 'owner');

-- Call Records: CSR sees own, manager/owner sees all
CREATE POLICY call_records_select ON public.call_records FOR SELECT USING (
  get_user_role() IN ('owner', 'manager')
  OR csr_id = get_user_id()
);

-- Reviews: tech sees own, manager/owner sees all
CREATE POLICY reviews_select ON public.reviews FOR SELECT USING (
  get_user_role() IN ('owner', 'manager')
  OR technician_id = get_user_id()
);

-- Customers: accessible to all authenticated users
CREATE POLICY customers_select ON public.customers FOR SELECT USING (true);

-- Marketing Spend: manager/owner only
CREATE POLICY marketing_select ON public.marketing_spend FOR SELECT USING (
  get_user_role() IN ('owner', 'manager')
);

-- KPI Definitions: all can read, owner can modify
CREATE POLICY kpi_definitions_select ON public.kpi_definitions FOR SELECT USING (true);
CREATE POLICY kpi_definitions_modify ON public.kpi_definitions FOR ALL USING (get_user_role() = 'owner');

-- Raw Events: owner only
CREATE POLICY raw_events_select ON public.raw_events FOR SELECT USING (get_user_role() = 'owner');

-- Audit Log: owner only
CREATE POLICY audit_log_select ON public.audit_log FOR SELECT USING (get_user_role() = 'owner');

-- Leads: manager/owner
CREATE POLICY leads_select ON public.leads FOR SELECT USING (
  get_user_role() IN ('owner', 'manager')
);

-- Estimates: tech sees own, manager/owner sees all
CREATE POLICY estimates_select ON public.estimates FOR SELECT USING (
  get_user_role() IN ('owner', 'manager')
  OR technician_id = get_user_id()
);

-- Service role bypasses RLS for webhook processing
-- (Supabase service role key already bypasses RLS by default)
