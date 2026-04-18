-- 3B Holdings Dashboard — Supabase Schema
-- Apply to a fresh Supabase project (via SQL Editor in the dashboard, or psql locally).
-- Safe to re-run: every DDL uses IF NOT EXISTS / DROP IF EXISTS.

-- ============================================================================
-- 1. Access tables
-- ============================================================================

-- Partners: the set of users allowed into the app.
-- Row id mirrors auth.users.id; membership is enforced via partners, not auth.
CREATE TABLE IF NOT EXISTS public.partners (
  id uuid PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  name text NOT NULL,
  email text NOT NULL UNIQUE,
  role text NOT NULL DEFAULT 'admin' CHECK (role IN ('admin')),
  created_at timestamptz NOT NULL DEFAULT now()
);

-- Activity log: append-only audit trail.
-- Every CRUD table has a trigger that writes one row per change.
CREATE TABLE IF NOT EXISTS public.activity_log (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  actor_id uuid REFERENCES public.partners(id) ON DELETE SET NULL,
  action text NOT NULL CHECK (action IN ('create', 'update', 'delete')),
  entity_type text NOT NULL,
  entity_id uuid NOT NULL,
  property_id uuid,
  diff jsonb NOT NULL DEFAULT '{}'::jsonb,
  "timestamp" timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS activity_log_entity_idx ON public.activity_log (entity_type, entity_id);
CREATE INDEX IF NOT EXISTS activity_log_property_idx ON public.activity_log (property_id) WHERE property_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS activity_log_timestamp_idx ON public.activity_log ("timestamp" DESC);

-- ============================================================================
-- 2. Core: properties + units
-- ============================================================================

CREATE TABLE IF NOT EXISTS public.properties (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  address text NOT NULL,
  city text NOT NULL,
  state text NOT NULL,
  zip text NOT NULL,
  purchase_date date,
  purchase_price numeric(12, 2),
  current_estimated_value numeric(12, 2),
  value_updated_at timestamptz,
  notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.units (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  property_id uuid NOT NULL REFERENCES public.properties(id) ON DELETE CASCADE,
  label text NOT NULL,
  bedrooms int,
  bathrooms numeric(3, 1),
  sqft int,
  notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS units_property_idx ON public.units (property_id);

-- ============================================================================
-- 3. Mortgages
-- ============================================================================

CREATE TABLE IF NOT EXISTS public.mortgages (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  property_id uuid NOT NULL REFERENCES public.properties(id) ON DELETE CASCADE,
  lender text NOT NULL,
  original_principal numeric(12, 2) NOT NULL CHECK (original_principal > 0),
  interest_rate numeric(6, 4) NOT NULL CHECK (interest_rate >= 0 AND interest_rate < 1),
  term_months int NOT NULL CHECK (term_months > 0),
  start_date date NOT NULL,
  monthly_payment numeric(12, 2) NOT NULL CHECK (monthly_payment > 0),
  escrow_included bool NOT NULL DEFAULT false,
  status text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'paid_off', 'refinanced')),
  notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS mortgages_property_idx ON public.mortgages (property_id);

CREATE TABLE IF NOT EXISTS public.mortgage_payments (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  mortgage_id uuid NOT NULL REFERENCES public.mortgages(id) ON DELETE CASCADE,
  payment_date date NOT NULL,
  amount numeric(12, 2) NOT NULL CHECK (amount >= 0),
  principal_portion numeric(12, 2) NOT NULL DEFAULT 0 CHECK (principal_portion >= 0),
  interest_portion numeric(12, 2) NOT NULL DEFAULT 0 CHECK (interest_portion >= 0),
  escrow_portion numeric(12, 2) NOT NULL DEFAULT 0 CHECK (escrow_portion >= 0),
  extra_principal numeric(12, 2) NOT NULL DEFAULT 0 CHECK (extra_principal >= 0),
  source text NOT NULL DEFAULT 'manual' CHECK (source IN ('manual', 'recurring')),
  notes text,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS mortgage_payments_mortgage_idx ON public.mortgage_payments (mortgage_id, payment_date DESC);

-- View: current principal balance per mortgage.
-- Clients query this instead of recomputing.
CREATE OR REPLACE VIEW public.mortgage_balances AS
SELECT
  m.id AS mortgage_id,
  m.original_principal,
  m.original_principal
    - COALESCE((SELECT SUM(principal_portion + extra_principal) FROM public.mortgage_payments WHERE mortgage_id = m.id), 0)
    AS current_balance
FROM public.mortgages m;

-- ============================================================================
-- 4. Operations: vendors, repairs, expenses
-- ============================================================================

CREATE TABLE IF NOT EXISTS public.vendors (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  category text NOT NULL CHECK (category IN ('plumber', 'electrician', 'hvac', 'handyman', 'landscaping', 'other')),
  phone text,
  email text,
  notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.repairs (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  property_id uuid NOT NULL REFERENCES public.properties(id) ON DELETE CASCADE,
  unit_id uuid REFERENCES public.units(id) ON DELETE SET NULL,
  title text NOT NULL,
  description text,
  status text NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'done', 'cancelled')),
  opened_date date NOT NULL DEFAULT CURRENT_DATE,
  completed_date date,
  vendor_id uuid REFERENCES public.vendors(id) ON DELETE SET NULL,
  assigned_to uuid REFERENCES public.partners(id) ON DELETE SET NULL,
  cost numeric(12, 2),
  invoice_document_id uuid, -- FK added below after documents table is defined
  notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS repairs_property_status_idx ON public.repairs (property_id, status);
CREATE INDEX IF NOT EXISTS repairs_status_idx ON public.repairs (status) WHERE status IN ('open', 'in_progress');

CREATE TABLE IF NOT EXISTS public.expenses (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  property_id uuid NOT NULL REFERENCES public.properties(id) ON DELETE CASCADE,
  expense_date date NOT NULL,
  amount numeric(12, 2) NOT NULL CHECK (amount >= 0),
  category text NOT NULL CHECK (category IN ('mortgage', 'insurance', 'tax', 'utilities', 'repairs', 'hoa', 'other')),
  description text,
  vendor_id uuid REFERENCES public.vendors(id) ON DELETE SET NULL,
  receipt_document_id uuid, -- FK added below after documents table
  source_repair_id uuid REFERENCES public.repairs(id) ON DELETE SET NULL,
  notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS expenses_property_date_idx ON public.expenses (property_id, expense_date DESC);
CREATE INDEX IF NOT EXISTS expenses_category_idx ON public.expenses (category);

-- Trigger: when a repair transitions to status='done' with a cost + completed_date,
-- auto-create an expenses row. Guarded so updates to an already-done repair
-- don't create duplicate expenses.
CREATE OR REPLACE FUNCTION public.repair_to_expense() RETURNS trigger AS $$
BEGIN
  IF NEW.status = 'done' AND NEW.cost IS NOT NULL AND NEW.completed_date IS NOT NULL
     AND (TG_OP = 'INSERT' OR OLD.status <> 'done') THEN
    INSERT INTO public.expenses (property_id, expense_date, amount, category, description, vendor_id, source_repair_id)
    VALUES (NEW.property_id, NEW.completed_date, NEW.cost, 'repairs',
            COALESCE('Repair: ' || NEW.title, 'Repair'),
            NEW.vendor_id, NEW.id);
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS repair_to_expense_trigger ON public.repairs;
CREATE TRIGGER repair_to_expense_trigger
  AFTER INSERT OR UPDATE ON public.repairs
  FOR EACH ROW EXECUTE FUNCTION public.repair_to_expense();

-- ============================================================================
-- 5. Documents + storage
-- ============================================================================

CREATE TABLE IF NOT EXISTS public.documents (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  property_id uuid NOT NULL REFERENCES public.properties(id) ON DELETE CASCADE,
  title text NOT NULL,
  category text NOT NULL CHECK (category IN ('purchase', 'tax', 'insurance', 'mortgage_statement', 'inspection', 'receipt', 'other')),
  storage_path text NOT NULL UNIQUE,
  file_size bigint NOT NULL CHECK (file_size >= 0),
  mime_type text NOT NULL,
  uploaded_by uuid REFERENCES public.partners(id) ON DELETE SET NULL,
  uploaded_at timestamptz NOT NULL DEFAULT now(),
  notes text
);

CREATE INDEX IF NOT EXISTS documents_property_idx ON public.documents (property_id);
CREATE INDEX IF NOT EXISTS documents_category_idx ON public.documents (category);

-- Back-references from repairs and expenses to documents
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'repairs_invoice_document_fk') THEN
    ALTER TABLE public.repairs
      ADD CONSTRAINT repairs_invoice_document_fk
      FOREIGN KEY (invoice_document_id) REFERENCES public.documents(id) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'expenses_receipt_document_fk') THEN
    ALTER TABLE public.expenses
      ADD CONSTRAINT expenses_receipt_document_fk
      FOREIGN KEY (receipt_document_id) REFERENCES public.documents(id) ON DELETE SET NULL;
  END IF;
END $$;

-- Storage bucket for property documents (private; access gated by RLS on storage.objects below)
INSERT INTO storage.buckets (id, name, public)
VALUES ('property-documents', 'property-documents', false)
ON CONFLICT (id) DO NOTHING;

-- ============================================================================
-- 6. Deadlines
-- ============================================================================

CREATE TABLE IF NOT EXISTS public.deadlines (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  property_id uuid NOT NULL REFERENCES public.properties(id) ON DELETE CASCADE,
  title text NOT NULL,
  due_date date NOT NULL,
  recurring text NOT NULL DEFAULT 'none' CHECK (recurring IN ('none', 'monthly', 'annually')),
  completed bool NOT NULL DEFAULT false,
  notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS deadlines_property_idx ON public.deadlines (property_id, due_date);
CREATE INDEX IF NOT EXISTS deadlines_upcoming_idx ON public.deadlines (due_date) WHERE completed = false;

-- Trigger: when a recurring deadline is marked complete, auto-create the next occurrence.
-- The completed row is preserved as history.
CREATE OR REPLACE FUNCTION public.deadline_auto_advance() RETURNS trigger AS $$
DECLARE
  next_due date;
BEGIN
  IF NEW.completed = true AND (TG_OP = 'INSERT' OR OLD.completed = false)
     AND NEW.recurring <> 'none' THEN
    next_due := CASE NEW.recurring
      WHEN 'monthly' THEN (NEW.due_date + INTERVAL '1 month')::date
      WHEN 'annually' THEN (NEW.due_date + INTERVAL '1 year')::date
    END;
    INSERT INTO public.deadlines (property_id, title, due_date, recurring, completed, notes)
    VALUES (NEW.property_id, NEW.title, next_due, NEW.recurring, false, NEW.notes);
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS deadline_auto_advance_trigger ON public.deadlines;
CREATE TRIGGER deadline_auto_advance_trigger
  AFTER INSERT OR UPDATE ON public.deadlines
  FOR EACH ROW EXECUTE FUNCTION public.deadline_auto_advance();

-- ============================================================================
-- 7. Activity log triggers (one generic function attached to every CRUD table)
-- ============================================================================

CREATE OR REPLACE FUNCTION public.log_activity() RETURNS trigger AS $$
DECLARE
  v_entity_id uuid;
  v_property_id uuid;
  v_diff jsonb;
  v_action text;
BEGIN
  IF TG_OP = 'DELETE' THEN
    v_entity_id := (OLD.id)::uuid;
    v_diff := to_jsonb(OLD);
    v_action := 'delete';
  ELSIF TG_OP = 'INSERT' THEN
    v_entity_id := (NEW.id)::uuid;
    v_diff := to_jsonb(NEW);
    v_action := 'create';
  ELSE
    v_entity_id := (NEW.id)::uuid;
    v_diff := jsonb_build_object('before', to_jsonb(OLD), 'after', to_jsonb(NEW));
    v_action := 'update';
  END IF;

  -- Resolve property_id based on which table fired the trigger
  IF TG_TABLE_NAME = 'properties' THEN
    v_property_id := v_entity_id;
  ELSIF TG_TABLE_NAME IN ('units', 'mortgages', 'repairs', 'expenses', 'documents', 'deadlines') THEN
    v_property_id := COALESCE(
      (CASE WHEN TG_OP = 'DELETE' THEN (to_jsonb(OLD)->>'property_id')::uuid
            ELSE (to_jsonb(NEW)->>'property_id')::uuid END),
      NULL
    );
  ELSIF TG_TABLE_NAME = 'mortgage_payments' THEN
    v_property_id := (
      SELECT property_id FROM public.mortgages WHERE id =
        COALESCE(
          (CASE WHEN TG_OP = 'DELETE' THEN (to_jsonb(OLD)->>'mortgage_id')::uuid
                ELSE (to_jsonb(NEW)->>'mortgage_id')::uuid END),
          NULL
        )
    );
  ELSE
    v_property_id := NULL;
  END IF;

  INSERT INTO public.activity_log (actor_id, action, entity_type, entity_id, property_id, diff)
  VALUES (auth.uid(), v_action, TG_TABLE_NAME, v_entity_id, v_property_id, v_diff);

  RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Attach the activity trigger to every CRUD table
DO $$
DECLARE
  tbl text;
BEGIN
  FOREACH tbl IN ARRAY ARRAY['properties', 'units', 'mortgages', 'mortgage_payments',
                              'vendors', 'repairs', 'expenses', 'documents', 'deadlines']
  LOOP
    EXECUTE format('DROP TRIGGER IF EXISTS log_activity_trigger ON public.%I', tbl);
    EXECUTE format('CREATE TRIGGER log_activity_trigger
      AFTER INSERT OR UPDATE OR DELETE ON public.%I
      FOR EACH ROW EXECUTE FUNCTION public.log_activity()', tbl);
  END LOOP;
END $$;

-- ============================================================================
-- 8. Row-level security
-- ============================================================================

-- Helper: is the caller a partner?
CREATE OR REPLACE FUNCTION public.is_partner() RETURNS boolean AS $$
  SELECT EXISTS (SELECT 1 FROM public.partners WHERE id = auth.uid());
$$ LANGUAGE sql STABLE SECURITY DEFINER;

-- Enable RLS on every table
DO $$
DECLARE tbl text;
BEGIN
  FOREACH tbl IN ARRAY ARRAY['partners', 'properties', 'units', 'mortgages', 'mortgage_payments',
                              'vendors', 'repairs', 'expenses', 'documents', 'deadlines', 'activity_log']
  LOOP
    EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', tbl);
  END LOOP;
END $$;

-- Partners: any partner can see all partner rows (it's the team roster)
DROP POLICY IF EXISTS partners_all ON public.partners;
CREATE POLICY partners_all ON public.partners FOR ALL
  USING (public.is_partner()) WITH CHECK (public.is_partner());

-- All other CRUD tables: full access if you're a partner
DO $$
DECLARE tbl text; pol text;
BEGIN
  FOREACH tbl IN ARRAY ARRAY['properties', 'units', 'mortgages', 'mortgage_payments',
                              'vendors', 'repairs', 'expenses', 'documents', 'deadlines']
  LOOP
    pol := tbl || '_all';
    EXECUTE format('DROP POLICY IF EXISTS %I ON public.%I', pol, tbl);
    EXECUTE format('CREATE POLICY %I ON public.%I FOR ALL USING (public.is_partner()) WITH CHECK (public.is_partner())', pol, tbl);
  END LOOP;
END $$;

-- activity_log: partners can SELECT. INSERT happens via SECURITY DEFINER trigger which bypasses RLS.
DROP POLICY IF EXISTS activity_log_select ON public.activity_log;
CREATE POLICY activity_log_select ON public.activity_log FOR SELECT
  USING (public.is_partner());

-- Storage bucket policies: any partner can read/write property-documents
DROP POLICY IF EXISTS "partners read property-documents" ON storage.objects;
CREATE POLICY "partners read property-documents" ON storage.objects FOR SELECT
  USING (bucket_id = 'property-documents' AND public.is_partner());

DROP POLICY IF EXISTS "partners write property-documents" ON storage.objects;
CREATE POLICY "partners write property-documents" ON storage.objects FOR INSERT
  WITH CHECK (bucket_id = 'property-documents' AND public.is_partner());

DROP POLICY IF EXISTS "partners delete property-documents" ON storage.objects;
CREATE POLICY "partners delete property-documents" ON storage.objects FOR DELETE
  USING (bucket_id = 'property-documents' AND public.is_partner());
