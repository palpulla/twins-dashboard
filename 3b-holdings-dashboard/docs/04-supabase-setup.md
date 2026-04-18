# Supabase Setup

Two paths: **cloud** (for production / shared use — recommended) or **local** (for dev with Docker).

## Cloud Supabase project (recommended)

### 1. Create the project

1. Go to <https://supabase.com> and sign in.
2. Click **New project**.
3. Name it `3b-holdings-dashboard`. Pick a region close to you.
4. Set a strong database password and save it somewhere safe.
5. Wait ~2 minutes for provisioning.

### 2. Run the schema

1. Open **SQL Editor** in the project dashboard.
2. Click **New query**.
3. Paste the entire contents of `supabase/schema.sql` from this repo.
4. Click **Run**. All statements should succeed.

If anything fails, the SQL editor shows the exact line — usually a syntax tweak needed for your Postgres version. Fix and re-run (the schema is idempotent).

### 3. (Optional) Run the seed

Same steps as above, with `supabase/seed.sql`. This gives Claude Design's generated UI something to render against.

### 4. Verify the storage bucket exists

Open **Storage** tab. You should see a `property-documents` bucket (created by schema.sql). If missing, create it manually: name `property-documents`, set **private**.

### 5. Invite the 3 partners

In the Supabase dashboard: **Authentication → Users → Add user → Send invitation**. Enter each partner's email. They receive a magic link.

After each partner first signs in, you need to insert a matching `partners` row. In SQL Editor:

```sql
INSERT INTO public.partners (id, name, email)
VALUES (
  '<copy the auth.users.id value from Authentication → Users for that email>',
  'Partner Name',
  'partner@example.com'
);
```

(One row per partner, one-time. Phase 2 adds an in-app invite flow.)

### 6. Grab env vars

**Project Settings → API** shows:
- **URL** → paste into `VITE_SUPABASE_URL`
- **anon public key** → paste into `VITE_SUPABASE_ANON_KEY`

Copy `.env.example` to `.env.local` (for local dev) or set these in Vercel / wherever you deploy.

### 7. (Optional) Regenerate TypeScript types from your real schema

Install Supabase CLI once (no Docker needed for the cloud path):

```bash
npm install -g supabase
```

Then, from the project root:

```bash
npx supabase login   # one-time
npx supabase gen types typescript --project-id <your-project-ref> > src/types/database.ts
```

`your-project-ref` is in your Supabase project URL — the string before `.supabase.co`.

Claude Design can then use the typed client for full end-to-end type safety.

---

## Local Supabase (for development only)

Requires Docker Desktop running and Supabase CLI installed.

### 1. Install tooling

```bash
# Homebrew (macOS)
brew install --cask docker
brew install supabase/tap/supabase
brew install libpq && brew link --force libpq   # for psql
```

Start Docker Desktop from Applications.

### 2. Initialize and start

```bash
cd 3b-holdings-dashboard
supabase init
supabase start
```

Output includes URLs and an anon key — copy the anon key into `.env.local`.

### 3. Apply schema + seed

```bash
psql "postgresql://postgres:postgres@localhost:54322/postgres" -f supabase/schema.sql
psql "postgresql://postgres:postgres@localhost:54322/postgres" -f supabase/seed.sql
```

### 4. Regenerate types

```bash
npm run types:gen
```

### 5. Access local Studio

<http://localhost:54323> — full Supabase dashboard locally.

### 6. Stop local stack when done

```bash
supabase stop
```

---

## Troubleshooting

- **`supabase start` fails with "Cannot connect to Docker"** → open Docker Desktop, wait for it to finish starting, re-run.
- **Schema fails mid-way on re-run** → every table uses `IF NOT EXISTS` and every trigger uses `DROP TRIGGER IF EXISTS` first, so re-running should be safe. If you see a constraint conflict, manually drop the conflicting object in Studio and re-run.
- **Anon key changed after `supabase stop/start`** → it's a new local instance; update `.env.local`.
- **Magic link not arriving (cloud)** → check spam. Default Supabase SMTP works but may be slow. Custom SMTP requires Supabase Pro.
- **Supabase Storage upload fails with 403** → make sure the partner is in `public.partners` (`auth.users.id` match). RLS denies uploads from non-partners.
