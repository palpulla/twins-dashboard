-- supabase/migrations/00006_seed_charles_attribution.sql

-- Insert Charles as a technician if he doesn't already exist
INSERT INTO public.users (id, auth_id, email, full_name, role, is_active)
VALUES (
  gen_random_uuid(),
  NULL,
  'charlesrue@icloud.com',
  'Charles Rue',
  'technician',
  true
)
ON CONFLICT (email) DO NOTHING;

-- Single-row app_settings, with Charles as the co-tech attribution exception
INSERT INTO public.app_settings (
  id,
  digest_recipient_email,
  co_tech_default_user_id
)
SELECT
  1,
  'daniel@twinsgaragedoors.com',
  (SELECT id FROM public.users WHERE email = 'charlesrue@icloud.com' LIMIT 1)
ON CONFLICT (id) DO UPDATE
  SET co_tech_default_user_id = EXCLUDED.co_tech_default_user_id
  WHERE public.app_settings.co_tech_default_user_id IS NULL;
