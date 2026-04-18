-- 3B Holdings Dashboard — Seed Data
--
-- Placeholder data at similar scale/shape to the real portfolio (4 properties, 12 units).
-- Replace with real values after initial deployment.
-- Apply AFTER schema.sql.

BEGIN;

-- Clean slate (order matters: children first)
DELETE FROM public.deadlines;
DELETE FROM public.documents;
DELETE FROM public.expenses;
DELETE FROM public.mortgage_payments;
DELETE FROM public.mortgages;
DELETE FROM public.repairs;
DELETE FROM public.units;
DELETE FROM public.properties;
DELETE FROM public.vendors;

-- ---------------------------------------------------------------------------
-- Vendors
-- ---------------------------------------------------------------------------
INSERT INTO public.vendors (id, name, category, phone, email, notes) VALUES
  ('11111111-0000-0000-0000-000000000001', 'ProPlumb Services', 'plumber',     '555-0101', 'dispatch@proplumb.example', 'Reliable for weekend calls'),
  ('11111111-0000-0000-0000-000000000002', 'BoltElectric',      'electrician', '555-0102', 'hello@bolt.example',        NULL),
  ('11111111-0000-0000-0000-000000000003', 'Cool Air HVAC',     'hvac',        '555-0103', NULL,                        'Service contract renews in Sept'),
  ('11111111-0000-0000-0000-000000000004', 'Green Lawn Care',   'landscaping', '555-0104', NULL,                        'Bi-weekly April–October'),
  ('11111111-0000-0000-0000-000000000005', 'HandyHank',         'handyman',    '555-0105', 'hank@example.com',          'General repairs');

-- ---------------------------------------------------------------------------
-- Properties
-- ---------------------------------------------------------------------------
INSERT INTO public.properties (id, address, city, state, zip, purchase_date, purchase_price, current_estimated_value, value_updated_at, notes) VALUES
  ('22222222-0000-0000-0000-000000000001', '123 Main St',  'Springfield', 'MO', '65801', '2019-06-15', 185000, 245000, now(), 'Triplex, original portfolio'),
  ('22222222-0000-0000-0000-000000000002', '456 Oak Ave',  'Springfield', 'MO', '65802', '2020-11-02', 220000, 310000, now(), 'Fourplex'),
  ('22222222-0000-0000-0000-000000000003', '789 Pine Rd',  'Branson',     'MO', '65616', '2022-03-20', 165000, 195000, now(), 'Duplex'),
  ('22222222-0000-0000-0000-000000000004', '321 Cedar Ln', 'Springfield', 'MO', '65803', '2023-08-11', 240000, 265000, now(), 'Triplex');

-- ---------------------------------------------------------------------------
-- Units (12 total)
-- ---------------------------------------------------------------------------
INSERT INTO public.units (property_id, label, bedrooms, bathrooms, sqft) VALUES
  -- 123 Main (triplex, 3 units)
  ('22222222-0000-0000-0000-000000000001', 'Unit A', 2, 1.0,  850),
  ('22222222-0000-0000-0000-000000000001', 'Unit B', 2, 1.0,  850),
  ('22222222-0000-0000-0000-000000000001', 'Unit C', 1, 1.0,  650),
  -- 456 Oak (fourplex, 4 units)
  ('22222222-0000-0000-0000-000000000002', 'Unit 1', 2, 1.5,  900),
  ('22222222-0000-0000-0000-000000000002', 'Unit 2', 2, 1.5,  900),
  ('22222222-0000-0000-0000-000000000002', 'Unit 3', 2, 1.5,  900),
  ('22222222-0000-0000-0000-000000000002', 'Unit 4', 2, 1.5,  900),
  -- 789 Pine (duplex, 2 units)
  ('22222222-0000-0000-0000-000000000003', 'Upper',  3, 2.0, 1100),
  ('22222222-0000-0000-0000-000000000003', 'Lower',  2, 1.0,  900),
  -- 321 Cedar (triplex, 3 units)
  ('22222222-0000-0000-0000-000000000004', 'Unit 1', 2, 1.0,  800),
  ('22222222-0000-0000-0000-000000000004', 'Unit 2', 2, 1.0,  800),
  ('22222222-0000-0000-0000-000000000004', 'Unit 3', 1, 1.0,  600);

-- ---------------------------------------------------------------------------
-- Mortgages + a few payments each
-- ---------------------------------------------------------------------------
INSERT INTO public.mortgages (id, property_id, lender, original_principal, interest_rate, term_months, start_date, monthly_payment, escrow_included, status) VALUES
  ('33333333-0000-0000-0000-000000000001', '22222222-0000-0000-0000-000000000001', 'First Community Bank',  148000, 0.0425, 360, '2019-07-01',  728.10, true, 'active'),
  ('33333333-0000-0000-0000-000000000002', '22222222-0000-0000-0000-000000000002', 'Midwest Mortgage Co',    176000, 0.0325, 360, '2020-12-01',  765.83, true, 'active'),
  ('33333333-0000-0000-0000-000000000003', '22222222-0000-0000-0000-000000000003', 'First Community Bank',  132000, 0.0550, 360, '2022-04-01',  749.45, true, 'active'),
  ('33333333-0000-0000-0000-000000000004', '22222222-0000-0000-0000-000000000004', 'Heartland Credit Union', 192000, 0.0650, 360, '2023-09-01', 1213.68, true, 'active');

INSERT INTO public.mortgage_payments (mortgage_id, payment_date, amount, principal_portion, interest_portion, escrow_portion) VALUES
  ('33333333-0000-0000-0000-000000000001', '2026-02-01',  728.10, 220.00, 380.00, 128.10),
  ('33333333-0000-0000-0000-000000000001', '2026-03-01',  728.10, 221.00, 379.00, 128.10),
  ('33333333-0000-0000-0000-000000000002', '2026-02-01',  765.83, 290.00, 325.00, 150.83),
  ('33333333-0000-0000-0000-000000000002', '2026-03-01',  765.83, 291.00, 324.00, 150.83),
  ('33333333-0000-0000-0000-000000000003', '2026-02-01',  749.45, 195.00, 430.00, 124.45),
  ('33333333-0000-0000-0000-000000000003', '2026-03-01',  749.45, 196.00, 429.00, 124.45),
  ('33333333-0000-0000-0000-000000000004', '2026-02-01', 1213.68, 210.00, 840.00, 163.68),
  ('33333333-0000-0000-0000-000000000004', '2026-03-01', 1213.68, 211.00, 839.00, 163.68);

-- ---------------------------------------------------------------------------
-- Repairs (mix of open, in-progress, done)
-- ---------------------------------------------------------------------------
INSERT INTO public.repairs (property_id, title, description, status, opened_date, completed_date, vendor_id, cost) VALUES
  ('22222222-0000-0000-0000-000000000001', 'Kitchen sink leak',   'Unit A reported slow leak under sink', 'open',        '2026-04-10', NULL,         '11111111-0000-0000-0000-000000000001', NULL),
  ('22222222-0000-0000-0000-000000000002', 'Bedroom outlet dead', 'Unit 2 — outlet on north wall not working', 'in_progress', '2026-04-08', NULL,         '11111111-0000-0000-0000-000000000002', NULL),
  ('22222222-0000-0000-0000-000000000002', 'Annual HVAC service', 'Clean coils, change filters', 'done',        '2026-03-20', '2026-03-22', '11111111-0000-0000-0000-000000000003', 180.00),
  ('22222222-0000-0000-0000-000000000003', 'Roof leak (Upper)',   'Water spot in upper unit ceiling', 'done',        '2026-02-15', '2026-02-28', '11111111-0000-0000-0000-000000000005', 650.00),
  ('22222222-0000-0000-0000-000000000004', 'Mulch + spring trim', 'Front yard mulch refresh',        'done',        '2026-03-30', '2026-04-02', '11111111-0000-0000-0000-000000000004', 275.00);

-- (The repair_to_expense trigger auto-creates expenses for the 3 'done' repairs above.)

-- ---------------------------------------------------------------------------
-- Other expenses (mortgage, insurance, tax, utilities) for recent months
-- ---------------------------------------------------------------------------
INSERT INTO public.expenses (property_id, expense_date, amount, category, description) VALUES
  ('22222222-0000-0000-0000-000000000001', '2026-03-01',  728.10, 'mortgage',   'March mortgage payment'),
  ('22222222-0000-0000-0000-000000000001', '2026-04-01',  728.10, 'mortgage',   'April mortgage payment'),
  ('22222222-0000-0000-0000-000000000001', '2026-01-15', 1100.00, 'insurance',  'Annual property insurance'),
  ('22222222-0000-0000-0000-000000000002', '2026-03-01',  765.83, 'mortgage',   'March mortgage payment'),
  ('22222222-0000-0000-0000-000000000002', '2026-04-01',  765.83, 'mortgage',   'April mortgage payment'),
  ('22222222-0000-0000-0000-000000000002', '2026-02-10',  420.00, 'utilities',  'Shared water/sewer Q1'),
  ('22222222-0000-0000-0000-000000000003', '2026-03-01',  749.45, 'mortgage',   'March mortgage payment'),
  ('22222222-0000-0000-0000-000000000003', '2026-04-01',  749.45, 'mortgage',   'April mortgage payment'),
  ('22222222-0000-0000-0000-000000000004', '2026-03-01', 1213.68, 'mortgage',   'March mortgage payment'),
  ('22222222-0000-0000-0000-000000000004', '2026-04-01', 1213.68, 'mortgage',   'April mortgage payment'),
  ('22222222-0000-0000-0000-000000000004', '2026-01-28', 2850.00, 'tax',        '2025 property tax');

-- ---------------------------------------------------------------------------
-- Deadlines (upcoming + recurring so auto-advance is demonstrable)
-- ---------------------------------------------------------------------------
INSERT INTO public.deadlines (property_id, title, due_date, recurring, completed) VALUES
  ('22222222-0000-0000-0000-000000000001', 'Insurance renewal', '2027-01-15', 'annually', false),
  ('22222222-0000-0000-0000-000000000001', 'Property tax due',  '2026-12-31', 'annually', false),
  ('22222222-0000-0000-0000-000000000002', 'Insurance renewal', '2026-05-03', 'annually', false),
  ('22222222-0000-0000-0000-000000000002', 'HOA dues',          '2026-05-20', 'monthly',  false),
  ('22222222-0000-0000-0000-000000000003', 'Insurance renewal', '2026-06-10', 'annually', false),
  ('22222222-0000-0000-0000-000000000004', 'Property tax due',  '2026-12-31', 'annually', false),
  ('22222222-0000-0000-0000-000000000004', 'HVAC service',      '2026-09-15', 'annually', false);

COMMIT;
