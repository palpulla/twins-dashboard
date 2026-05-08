-- Parts catalog: the parts library used for pricing line items on jobs.
-- Distinct table name (parts_catalog, not "parts") to avoid colliding with
-- legacy `parts` tables in other Twins Supabase projects.

CREATE TABLE IF NOT EXISTS public.parts_catalog (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  sku          TEXT,
  name         TEXT NOT NULL,
  category     TEXT NOT NULL DEFAULT 'Uncategorized',
  price        NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (price >= 0),
  description  TEXT,
  is_active    BOOLEAN NOT NULL DEFAULT true,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- SKU is optional but unique when present.
CREATE UNIQUE INDEX IF NOT EXISTS uq_parts_catalog_sku
  ON public.parts_catalog(sku)
  WHERE sku IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_parts_catalog_category
  ON public.parts_catalog(category);

CREATE INDEX IF NOT EXISTS idx_parts_catalog_active
  ON public.parts_catalog(is_active)
  WHERE is_active = true;

-- updated_at trigger (function defined in 00001_initial_schema.sql)
DROP TRIGGER IF EXISTS set_updated_at ON public.parts_catalog;
CREATE TRIGGER set_updated_at
  BEFORE UPDATE ON public.parts_catalog
  FOR EACH ROW
  EXECUTE FUNCTION update_updated_at();

-- RLS: any authenticated user can read; only owners can modify.
ALTER TABLE public.parts_catalog ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS parts_catalog_select ON public.parts_catalog;
CREATE POLICY parts_catalog_select
  ON public.parts_catalog
  FOR SELECT
  USING (true);

DROP POLICY IF EXISTS parts_catalog_modify ON public.parts_catalog;
CREATE POLICY parts_catalog_modify
  ON public.parts_catalog
  FOR ALL
  USING (get_user_role() = 'owner')
  WITH CHECK (get_user_role() = 'owner');
