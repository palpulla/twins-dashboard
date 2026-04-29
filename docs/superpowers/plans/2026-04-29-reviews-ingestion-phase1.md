# Reviews Ingestion Phase 1 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans.

**Goal:** Land schema + nightly poller + frontend Twins Records section. Edge Function ships dormant; activates once Daniel sets 3 GBP env vars (one-time OAuth flow).

**Architecture:** New `reviews` table stores GBP reviews. New `company_records` table stores company-level Wins (since they're not per-tech). New Edge Function `sync-gbp-reviews` polls GBP nightly via pg_cron, upserts into `reviews`, then computes company records. Frontend Wins page gains a "Twins Records" section that renders from `company_records`. Edge Function is dormant when env vars missing — logs and exits 200, no error.

**Tech Stack:** Supabase Postgres + pg_cron + Edge Functions (Deno), React + TanStack Query, Vitest.

---

## Repo Context

- **Repo root:** `/Users/daniel/twins-dashboard/twins-dash`
- **Worktree:** `.worktrees/reviews-ingestion-phase1`
- **Branch from:** `origin/main` at `90f4ab6` or later (Wins v2 just merged)
- **Spec:** `docs/superpowers/specs/2026-04-29-reviews-ingestion-design.md`

## Phase 1 scope (this plan)

- Layer A only — company-level reviews count.
- Edge Function with graceful "no credentials" fallback.
- Frontend Twins Records section on `/tech/wins`.

## Phase 2 (separate plan, after Daniel's OAuth setup)

- Layer B (fuzzy-match per-tech attribution)
- Layer C (review-redirect Edge Function + GHL template guidance)
- Per-tech "most reviews" records on the existing per-tech Wins sections

## File Structure

| File | Action |
|---|---|
| `supabase/migrations/20260429220000_reviews_phase1.sql` | Create — `reviews` table + `company_records` table + RLS + new kpi_keys + extend `compute_streaks_and_prs` for company review counts + pg_cron schedule |
| `supabase/functions/sync-gbp-reviews/index.ts` | Create — nightly GBP API poll + upsert to `reviews`. Returns 200 OK with `{skipped: 'no credentials'}` when env vars missing. |
| `supabase/functions/sync-gbp-reviews/__tests__/sync.test.ts` | Create — 3 unit tests (skipped-on-missing-env, parses GBP response, upserts dedup by gbp_review_id) |
| `src/hooks/wins/useCompanyRecords.ts` | Create — React Query hook returning `CompanyRecord[]` for the new section |
| `src/lib/wins/recordFormatters.ts` | Modify — add `most_reviews` and `avg_review_rating` to LABEL_MAP/VALUE_FMT |
| `src/lib/wins/__tests__/recordFormatters.test.ts` | Modify — 2 new tests |
| `src/pages/tech/Recognition.tsx` | Modify — add "Twins Records" section above the per-tech sections |

7 files. Larger than recent plans but each piece is independent.

---

## M1 — Worktree

### Task 0

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git fetch origin
git worktree add .worktrees/reviews-ingestion-phase1 -b feat/reviews-phase1 origin/main
cd .worktrees/reviews-ingestion-phase1
```

---

## M2 — Schema migration

### Task 1: Migration file

**Files:**
- Create: `supabase/migrations/20260429220000_reviews_phase1.sql`

- [ ] **Step 1: Create the migration**

```bash
cat > supabase/migrations/20260429220000_reviews_phase1.sql <<'EOF'
-- Reviews Ingestion Phase 1 — schema + company-level Wins records
--
-- Layer A only. Layer B (fuzzy attribution) and Layer C (GHL-redirect) ship
-- in Phase 2 after Daniel does the GBP OAuth setup.
--
-- Reversibility: drop the 2 tables + the new kpi_keys + restore the prior
-- compute_streaks_and_prs() body from 20260429210000_wins_v2_rate_kpis.sql.

-- ===== reviews table =====
CREATE TABLE IF NOT EXISTS public.reviews (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  gbp_review_id   text NOT NULL UNIQUE,
  rating          smallint NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment         text,
  reviewer_name   text,
  reviewer_photo  text,
  review_at       timestamptz NOT NULL,
  reply_comment   text,
  reply_at        timestamptz,
  -- Phase 2 attribution columns; nullable for Phase 1
  tech_id         integer REFERENCES public.payroll_techs(id),
  attribution_source text NOT NULL DEFAULT 'unattributed'
    CHECK (attribution_source IN ('ghl_redirect', 'fuzzy_match', 'manual', 'unattributed')),
  attributed_at   timestamptz,
  matched_job_id  integer REFERENCES public.payroll_jobs(id),
  -- Bookkeeping
  raw_payload     jsonb NOT NULL,
  first_seen_at   timestamptz NOT NULL DEFAULT now(),
  updated_at      timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS reviews_review_at_idx
  ON public.reviews(review_at DESC);
CREATE INDEX IF NOT EXISTS reviews_tech_review_at_idx
  ON public.reviews(tech_id, review_at DESC) WHERE tech_id IS NOT NULL;

ALTER TABLE public.reviews ENABLE ROW LEVEL SECURITY;

-- Admin + payroll-access can read all reviews
DROP POLICY IF EXISTS reviews_select_payroll ON public.reviews;
CREATE POLICY reviews_select_payroll ON public.reviews
  FOR SELECT TO authenticated
  USING (public.has_payroll_access(auth.uid()));

-- Tech can read reviews attributed to them
DROP POLICY IF EXISTS reviews_select_own ON public.reviews;
CREATE POLICY reviews_select_own ON public.reviews
  FOR SELECT TO authenticated
  USING (tech_id IS NOT NULL AND tech_id = public.current_technician_id());

-- Service role writes
GRANT SELECT ON public.reviews TO authenticated;

-- ===== company_records table =====
-- Mirrors tech_personal_records but without tech_id (records that aren't per-tech).
CREATE TABLE IF NOT EXISTS public.company_records (
  id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  kpi_key      text NOT NULL,
  period       text NOT NULL CHECK (period IN ('week','month','quarter','year','all_time')),
  value        numeric NOT NULL,
  achieved_at  date NOT NULL,
  is_fresh     boolean NOT NULL DEFAULT false,
  updated_at   timestamptz NOT NULL DEFAULT now(),
  UNIQUE (kpi_key, period)
);

ALTER TABLE public.company_records ENABLE ROW LEVEL SECURITY;

-- All authenticated users can read company records (they're not sensitive)
DROP POLICY IF EXISTS company_records_select_all ON public.company_records;
CREATE POLICY company_records_select_all ON public.company_records
  FOR SELECT TO authenticated
  USING (auth.uid() IS NOT NULL);

GRANT SELECT ON public.company_records TO authenticated;

-- ===== new kpi_keys =====
INSERT INTO public.scorecard_tier_thresholds
  (kpi_key, bronze, silver, gold, elite, direction, unit, display_name) VALUES
  ('most_reviews',      3, 6, 10, 15, 'higher', 'count', 'Most reviews'),
  ('avg_review_rating', 4.0, 4.5, 4.7, 4.9, 'higher', 'count', 'Avg review rating')
ON CONFLICT (kpi_key) DO NOTHING;

-- ===== extend compute_streaks_and_prs to write company records =====
-- We add 8 new compute blocks AT THE END of the existing function body:
--   most_reviews × week / month / quarter / all_time
--   avg_review_rating × week / month / quarter / all_time
--
-- All write to company_records (single row per kpi_key × period). No tech loop.

CREATE OR REPLACE FUNCTION public._upsert_company_record(
  p_kpi_key text,
  p_period  text,
  p_value   numeric,
  p_achieved_at date
) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE
  v_fresh_days integer;
BEGIN
  IF p_value IS NULL OR p_achieved_at IS NULL THEN RETURN; END IF;

  v_fresh_days := CASE p_period
    WHEN 'week'      THEN 7
    WHEN 'month'     THEN 14
    WHEN 'quarter'   THEN 30
    WHEN 'year'      THEN 60
    WHEN 'all_time'  THEN 90
    ELSE 7
  END;

  INSERT INTO public.company_records
    (kpi_key, period, value, achieved_at, is_fresh, updated_at)
  VALUES (
    p_kpi_key, p_period, p_value, p_achieved_at,
    (p_achieved_at >= (now() - (v_fresh_days * interval '1 day'))::date),
    now()
  )
  ON CONFLICT (kpi_key, period) DO UPDATE SET
    value       = EXCLUDED.value,
    achieved_at = EXCLUDED.achieved_at,
    is_fresh    = EXCLUDED.is_fresh,
    updated_at  = now();
END;
$$;

GRANT EXECUTE ON FUNCTION public._upsert_company_record(text, text, numeric, date) TO service_role;

-- New function: compute_company_review_records()
-- Called by the same nightly cron after compute_streaks_and_prs().
CREATE OR REPLACE FUNCTION public.compute_company_review_records()
RETURNS void
LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE
  v_best_w      RECORD;
  v_best_m      RECORD;
  v_best_q      RECORD;
  v_best_y      RECORD;
  v_avg_w       RECORD;
  v_avg_m       RECORD;
  v_avg_q       RECORD;
  v_avg_y       RECORD;
BEGIN
  -- Most reviews in a week (last 25 weeks)
  SELECT date_trunc('week', review_at)::date AS week_start, COUNT(*)::numeric AS n
  INTO v_best_w
  FROM public.reviews
  WHERE review_at >= (now() - interval '25 weeks')
  GROUP BY date_trunc('week', review_at)
  HAVING COUNT(*) > 0
  ORDER BY n DESC LIMIT 1;
  PERFORM public._upsert_company_record('most_reviews', 'week', v_best_w.n, v_best_w.week_start);

  -- Most reviews in a month (last 12 months)
  SELECT date_trunc('month', review_at)::date AS month_start, COUNT(*)::numeric AS n
  INTO v_best_m
  FROM public.reviews
  WHERE review_at >= (now() - interval '12 months')
  GROUP BY date_trunc('month', review_at)
  HAVING COUNT(*) > 0
  ORDER BY n DESC LIMIT 1;
  PERFORM public._upsert_company_record('most_reviews', 'month', v_best_m.n, v_best_m.month_start);

  -- Most reviews in a quarter (last 24 months)
  SELECT date_trunc('quarter', review_at)::date AS quarter_start, COUNT(*)::numeric AS n
  INTO v_best_q
  FROM public.reviews
  WHERE review_at >= (now() - interval '24 months')
  GROUP BY date_trunc('quarter', review_at)
  HAVING COUNT(*) > 0
  ORDER BY n DESC LIMIT 1;
  PERFORM public._upsert_company_record('most_reviews', 'quarter', v_best_q.n, v_best_q.quarter_start);

  -- Most reviews ever (all_time)
  SELECT date_trunc('year', review_at)::date AS year_start, COUNT(*)::numeric AS n
  INTO v_best_y
  FROM public.reviews
  GROUP BY date_trunc('year', review_at)
  HAVING COUNT(*) > 0
  ORDER BY n DESC LIMIT 1;
  PERFORM public._upsert_company_record('most_reviews', 'year', v_best_y.n, v_best_y.year_start);

  -- Best avg rating in a quarter (min 5 reviews)
  SELECT date_trunc('quarter', review_at)::date AS quarter_start,
         AVG(rating)::numeric AS avg_r
  INTO v_avg_q
  FROM public.reviews
  WHERE review_at >= (now() - interval '24 months')
  GROUP BY date_trunc('quarter', review_at)
  HAVING COUNT(*) >= 5
  ORDER BY avg_r DESC LIMIT 1;
  PERFORM public._upsert_company_record('avg_review_rating', 'quarter', v_avg_q.avg_r, v_avg_q.quarter_start);
END $$;

GRANT EXECUTE ON FUNCTION public.compute_company_review_records() TO service_role;

-- ===== pg_cron: schedule the GBP poll + post-poll compute =====
-- The Edge Function URL pattern matches the existing email-notifications cron.
-- The function self-skips when env vars are missing, so it's safe to schedule
-- now even before Daniel sets the GBP env vars.

CREATE EXTENSION IF NOT EXISTS pg_cron;

DO $$
BEGIN
  PERFORM cron.unschedule(jobid)
  FROM cron.job
  WHERE jobname = 'sync-gbp-reviews';
END $$;

SELECT cron.schedule(
  'sync-gbp-reviews',
  '15 7 * * *',  -- 07:15 UTC nightly (15 min after the streaks-and-prs cron at 07:00)
  $cron$
  SELECT net.http_post(
    url := current_setting('app.settings.supabase_url', true) || '/functions/v1/sync-gbp-reviews',
    headers := jsonb_build_object(
      'Content-Type', 'application/json',
      'Authorization', 'Bearer ' || (SELECT current_setting('app.email_cron_secret', true))
    ),
    body := jsonb_build_object('source', 'pg_cron')
  );
  -- After the sync (which inserts/updates reviews rows), recompute company records.
  -- The function is fast and idempotent.
  PERFORM public.compute_company_review_records();
  $cron$
);

-- ===== One-shot run of compute_company_review_records (will produce zero rows
--       in Phase 1 because the reviews table is empty until OAuth is set up) =====
SELECT public.compute_company_review_records();
EOF
```

- [ ] **Step 2: Verify**

```bash
wc -l supabase/migrations/20260429220000_reviews_phase1.sql
grep -c "CREATE TABLE\|CREATE OR REPLACE FUNCTION\|cron.schedule" supabase/migrations/20260429220000_reviews_phase1.sql
```

Expected: ~200 lines; grep returns 5 (2 tables + 2 functions + 1 cron).

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260429220000_reviews_phase1.sql
git commit -m "feat(reviews-phase1): schema (reviews + company_records) + compute_company_review_records + pg_cron"
```

---

### Task 2: Apply migration to prod

- [ ] **Step 1: Try push**

```bash
npx supabase db push --linked
```

If clean, skip Step 2.

- [ ] **Step 2: Fallback**

```bash
npx supabase db query --linked -f supabase/migrations/20260429220000_reviews_phase1.sql
npx supabase db query --linked "INSERT INTO supabase_migrations.schema_migrations (version, name) VALUES ('20260429220000', 'reviews_phase1') ON CONFLICT DO NOTHING;"
```

- [ ] **Step 3: Verify tables exist**

```bash
npx supabase db query --linked "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('reviews', 'company_records') ORDER BY table_name;"
```

Expected: 2 rows.

- [ ] **Step 4: Verify cron job scheduled**

```bash
npx supabase db query --linked "SELECT jobname, schedule, active FROM cron.job WHERE jobname = 'sync-gbp-reviews';"
```

Expected: 1 row with `active = true` and schedule `'15 7 * * *'`.

- [ ] **Step 5: Verify company_records has zero rows (expected, no reviews yet)**

```bash
npx supabase db query --linked "SELECT COUNT(*) FROM public.company_records;"
```

Expected: 0. The `compute_company_review_records()` ran but found nothing because `reviews` is empty.

---

## M3 — Edge Function

### Task 3: sync-gbp-reviews Edge Function

**Files:**
- Create: `supabase/functions/sync-gbp-reviews/index.ts`
- Create: `supabase/functions/sync-gbp-reviews/__tests__/sync.test.ts`

- [ ] **Step 1: Write failing tests**

```bash
mkdir -p supabase/functions/sync-gbp-reviews/__tests__
cat > supabase/functions/sync-gbp-reviews/__tests__/sync.test.ts <<'EOF'
import { describe, it, expect, vi } from "vitest";
import { performSync, type SyncDeps } from "../sync";

describe("sync-gbp-reviews performSync", () => {
  it("returns 'skipped: no credentials' when GBP_REFRESH_TOKEN is unset", async () => {
    const deps: SyncDeps = {
      env: { GBP_REFRESH_TOKEN: undefined, GBP_ACCOUNT_ID: 'a', GBP_LOCATION_ID: 'l' },
      fetchAccessToken: async () => { throw new Error('should not be called'); },
      fetchReviewsPage: async () => { throw new Error('should not be called'); },
      upsertReviews: async () => { throw new Error('should not be called'); },
    };
    const r = await performSync(deps);
    expect(r.skipped).toBe('no credentials');
    expect(r.synced).toBe(0);
  });

  it("upserts reviews from a single GBP page", async () => {
    let upsertedCount = 0;
    const deps: SyncDeps = {
      env: { GBP_REFRESH_TOKEN: 'rt', GBP_ACCOUNT_ID: 'a', GBP_LOCATION_ID: 'l' },
      fetchAccessToken: async () => 'access-token',
      fetchReviewsPage: async () => ({
        reviews: [
          { reviewId: 'r1', rating: 'FIVE', comment: 'great', reviewer: { displayName: 'Mary K' }, createTime: '2026-04-15T12:00:00Z' },
          { reviewId: 'r2', rating: 'FOUR', comment: 'good',  reviewer: { displayName: 'John D' }, createTime: '2026-04-16T12:00:00Z' },
        ],
        nextPageToken: undefined,
      }),
      upsertReviews: async (rows) => { upsertedCount += rows.length; },
    };
    const r = await performSync(deps);
    expect(r.skipped).toBeFalsy();
    expect(r.synced).toBe(2);
    expect(upsertedCount).toBe(2);
  });

  it("paginates when nextPageToken is returned", async () => {
    let pageCount = 0;
    let upsertedCount = 0;
    const deps: SyncDeps = {
      env: { GBP_REFRESH_TOKEN: 'rt', GBP_ACCOUNT_ID: 'a', GBP_LOCATION_ID: 'l' },
      fetchAccessToken: async () => 'access-token',
      fetchReviewsPage: async () => {
        pageCount++;
        if (pageCount === 1) {
          return {
            reviews: [{ reviewId: 'r1', rating: 'FIVE', comment: '', reviewer: { displayName: 'A' }, createTime: '2026-04-15T12:00:00Z' }],
            nextPageToken: 'page2',
          };
        }
        return {
          reviews: [{ reviewId: 'r2', rating: 'FOUR', comment: '', reviewer: { displayName: 'B' }, createTime: '2026-04-16T12:00:00Z' }],
          nextPageToken: undefined,
        };
      },
      upsertReviews: async (rows) => { upsertedCount += rows.length; },
    };
    const r = await performSync(deps);
    expect(r.synced).toBe(2);
    expect(pageCount).toBe(2);
    expect(upsertedCount).toBe(2);
  });
});
EOF
```

- [ ] **Step 2: Write the Edge Function (and a separable `sync.ts` for unit testing)**

```bash
cat > supabase/functions/sync-gbp-reviews/sync.ts <<'EOF'
// supabase/functions/sync-gbp-reviews/sync.ts
//
// Pure logic separated from Deno.serve handler so unit tests (Vitest) can
// import without dragging in Deno HTTP server / Supabase client deps.
//
// Phase 1: Layer A only. Inserts reviews into public.reviews keyed by gbp_review_id.
// No attribution (Phase 2 adds Layers B + C).

export type GBPReview = {
  reviewId: string;
  rating: 'ONE' | 'TWO' | 'THREE' | 'FOUR' | 'FIVE' | string;
  comment?: string;
  reviewer?: { displayName?: string; profilePhotoUrl?: string };
  createTime: string;
  reviewReply?: { comment: string; updateTime: string };
};

export type GBPPage = {
  reviews?: GBPReview[];
  nextPageToken?: string;
};

export type ReviewRow = {
  gbp_review_id: string;
  rating: number;
  comment: string | null;
  reviewer_name: string | null;
  reviewer_photo: string | null;
  review_at: string; // ISO
  reply_comment: string | null;
  reply_at: string | null;
  raw_payload: GBPReview;
};

export type SyncDeps = {
  env: { GBP_REFRESH_TOKEN: string | undefined; GBP_ACCOUNT_ID: string; GBP_LOCATION_ID: string };
  fetchAccessToken: () => Promise<string>;
  fetchReviewsPage: (accessToken: string, pageToken: string | undefined) => Promise<GBPPage>;
  upsertReviews: (rows: ReviewRow[]) => Promise<void>;
};

export type SyncResult = {
  synced: number;
  skipped?: string;
  errors?: string[];
};

const RATING_TO_INT: Record<string, number> = {
  ONE: 1, TWO: 2, THREE: 3, FOUR: 4, FIVE: 5,
};

function gbpToRow(r: GBPReview): ReviewRow {
  return {
    gbp_review_id: r.reviewId,
    rating: RATING_TO_INT[r.rating] ?? Number(r.rating) ?? 0,
    comment: r.comment ?? null,
    reviewer_name: r.reviewer?.displayName ?? null,
    reviewer_photo: r.reviewer?.profilePhotoUrl ?? null,
    review_at: r.createTime,
    reply_comment: r.reviewReply?.comment ?? null,
    reply_at: r.reviewReply?.updateTime ?? null,
    raw_payload: r,
  };
}

export async function performSync(deps: SyncDeps): Promise<SyncResult> {
  if (!deps.env.GBP_REFRESH_TOKEN) {
    return { synced: 0, skipped: 'no credentials' };
  }

  const accessToken = await deps.fetchAccessToken();

  let pageToken: string | undefined = undefined;
  let total = 0;
  const errors: string[] = [];

  while (true) {
    const page = await deps.fetchReviewsPage(accessToken, pageToken);
    const reviews = page.reviews ?? [];
    if (reviews.length > 0) {
      try {
        await deps.upsertReviews(reviews.map(gbpToRow));
        total += reviews.length;
      } catch (e) {
        errors.push(e instanceof Error ? e.message : String(e));
      }
    }
    if (!page.nextPageToken) break;
    pageToken = page.nextPageToken;
  }

  return { synced: total, errors: errors.length > 0 ? errors : undefined };
}
EOF
```

```bash
cat > supabase/functions/sync-gbp-reviews/index.ts <<'EOF'
// supabase/functions/sync-gbp-reviews/index.ts
//
// Nightly poller. Pulls reviews from Google Business Profile API and upserts
// to public.reviews. Self-skips when GBP env vars are missing — safe to deploy
// dormant before OAuth is set up.
//
// Required env vars (set after Daniel does GBP OAuth):
//   GBP_REFRESH_TOKEN     — OAuth refresh token with business.manage scope
//   GBP_ACCOUNT_ID        — accounts/{accountId} (digits)
//   GBP_LOCATION_ID       — locations/{locationId} (digits)
//   GOOGLE_ADS_CLIENT_ID  — reused from existing Google project
//   GOOGLE_ADS_CLIENT_SECRET — reused from existing Google project

import { serve } from "https://deno.land/std@0.224.0/http/server.ts";
import { createClient } from "jsr:@supabase/supabase-js@^2";
import { performSync, type GBPPage, type ReviewRow } from "./sync.ts";

async function fetchAccessToken(refreshToken: string, clientId: string, clientSecret: string): Promise<string> {
  const r = await fetch('https://oauth2.googleapis.com/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      client_id: clientId,
      client_secret: clientSecret,
      refresh_token: refreshToken,
      grant_type: 'refresh_token',
    }),
  });
  if (!r.ok) throw new Error(`OAuth token refresh failed: ${r.status} ${await r.text()}`);
  const data = await r.json();
  return data.access_token;
}

async function fetchReviewsPage(
  accountId: string,
  locationId: string,
  accessToken: string,
  pageToken: string | undefined,
): Promise<GBPPage> {
  const url = new URL(`https://mybusiness.googleapis.com/v4/accounts/${accountId}/locations/${locationId}/reviews`);
  if (pageToken) url.searchParams.set('pageToken', pageToken);
  url.searchParams.set('pageSize', '50');
  const r = await fetch(url, { headers: { Authorization: `Bearer ${accessToken}` } });
  if (!r.ok) throw new Error(`GBP fetch failed: ${r.status} ${await r.text()}`);
  return await r.json();
}

serve(async (_req) => {
  const env = {
    GBP_REFRESH_TOKEN: Deno.env.get('GBP_REFRESH_TOKEN'),
    GBP_ACCOUNT_ID: Deno.env.get('GBP_ACCOUNT_ID') ?? '',
    GBP_LOCATION_ID: Deno.env.get('GBP_LOCATION_ID') ?? '',
  };
  const clientId = Deno.env.get('GOOGLE_ADS_CLIENT_ID') ?? '';
  const clientSecret = Deno.env.get('GOOGLE_ADS_CLIENT_SECRET') ?? '';
  const supaUrl = Deno.env.get('SUPABASE_URL')!;
  const serviceKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!;

  const svc = createClient(supaUrl, serviceKey);

  const result = await performSync({
    env,
    fetchAccessToken: () => fetchAccessToken(env.GBP_REFRESH_TOKEN!, clientId, clientSecret),
    fetchReviewsPage: (token, pageToken) => fetchReviewsPage(env.GBP_ACCOUNT_ID, env.GBP_LOCATION_ID, token, pageToken),
    upsertReviews: async (rows: ReviewRow[]) => {
      const { error } = await svc.from('reviews').upsert(rows, { onConflict: 'gbp_review_id' });
      if (error) throw error;
    },
  });

  return new Response(JSON.stringify(result), {
    headers: { 'Content-Type': 'application/json' },
    status: 200,
  });
});
EOF
```

- [ ] **Step 3: Run tests — verify pass**

```bash
npx vitest run supabase/functions/sync-gbp-reviews/__tests__/sync.test.ts
```

Expected: 3 passing.

- [ ] **Step 4: Deploy Edge Function**

```bash
npx supabase functions deploy sync-gbp-reviews --project-ref jwrpjuqaynownxaoeayi
```

Expected: deploy success message.

- [ ] **Step 5: Smoke-test the deployed function (with no credentials)**

```bash
ANON=$(grep VITE_SUPABASE_PUBLISHABLE_KEY .env | cut -d= -f2 | tr -d '"')
curl -s -X POST https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/sync-gbp-reviews \
  -H "Authorization: Bearer $ANON" -H "Content-Type: application/json" -d '{"source":"smoke-test"}'
```

Expected: `{"synced":0,"skipped":"no credentials"}`. Confirms the dormant-mode path works.

- [ ] **Step 6: Commit**

```bash
git add supabase/functions/sync-gbp-reviews/
git commit -m "feat(reviews-phase1): sync-gbp-reviews Edge Function (dormant on missing creds, 3/3 tests passing)"
```

---

## M4 — Frontend

### Task 4: useCompanyRecords hook + recordFormatters + Recognition.tsx section

**Files:**
- Create: `src/hooks/wins/useCompanyRecords.ts`
- Modify: `src/lib/wins/recordFormatters.ts`
- Modify: `src/lib/wins/__tests__/recordFormatters.test.ts`
- Modify: `src/pages/tech/Recognition.tsx`

- [ ] **Step 1: Hook**

```bash
mkdir -p src/hooks/wins
cat > src/hooks/wins/useCompanyRecords.ts <<'EOF'
// src/hooks/wins/useCompanyRecords.ts
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";

export type CompanyRecord = {
  id: string;
  kpi_key: string;
  period: 'week' | 'month' | 'quarter' | 'year' | 'all_time';
  value: number;
  achieved_at: string;
  is_fresh: boolean;
};

export function useCompanyRecords() {
  return useQuery({
    queryKey: ['company_records'],
    queryFn: async (): Promise<CompanyRecord[]> => {
      // company_records is added by the Phase 1 migration; cast to any until
      // typegen catches up.
      const { data, error } = await (supabase as any)
        .from('company_records')
        .select('id, kpi_key, period, value, achieved_at, is_fresh')
        .order('achieved_at', { ascending: false });
      if (error) throw error;
      return (data ?? []).map((r: any) => ({
        id: r.id,
        kpi_key: r.kpi_key,
        period: r.period,
        value: Number(r.value),
        achieved_at: r.achieved_at,
        is_fresh: Boolean(r.is_fresh),
      }));
    },
    staleTime: 60_000,
  });
}
EOF
```

- [ ] **Step 2: Add formatter tests**

In `src/lib/wins/__tests__/recordFormatters.test.ts`, add inside `describe("formatRecord", ...)`:

```ts
  it("formats most_reviews/quarter as count with quarter context", () => {
    const r = formatRecord({
      kpi_key: "most_reviews",
      period: "quarter",
      value: 12,
      achieved_at: "2026-01-01",
    });
    expect(r.label).toBe("Most reviews");
    expect(r.value).toBe("12");
    expect(r.context).toMatch(/Q1 2026/);
  });

  it("formats avg_review_rating with 1 decimal", () => {
    const r = formatRecord({
      kpi_key: "avg_review_rating",
      period: "quarter",
      value: 4.7,
      achieved_at: "2026-01-01",
    });
    expect(r.label).toBe("Highest avg rating");
    expect(r.value).toMatch(/4\.7/);
    expect(r.context).toMatch(/Q1 2026/);
  });
```

- [ ] **Step 3: Run tests — verify failures**

```bash
npx vitest run src/lib/wins/__tests__/recordFormatters.test.ts
```

Expected: 2 new failures.

- [ ] **Step 4: Add formatters**

In `src/lib/wins/recordFormatters.ts`, after the existing `fmtPct` add:

```ts
const fmtRating = (n: number) => `${n.toFixed(1)} ★`;
```

Add to `LABEL_MAP`:
```ts
  most_reviews:      { week: 'Most reviews', month: 'Most reviews', quarter: 'Most reviews', year: 'Most reviews', all_time: 'Most reviews ever' },
  avg_review_rating: { week: 'Highest avg rating', month: 'Highest avg rating', quarter: 'Highest avg rating', year: 'Highest avg rating', all_time: 'Highest avg rating' },
```

Add to `VALUE_FMT`:
```ts
  most_reviews:      fmtCount,
  avg_review_rating: fmtRating,
```

The `'all_time'` period needs a context formatter. In `formatContext`, add a case:
```ts
    case "all_time":
      return "all time";
```

Also expand the `RecordPeriod` type:
```ts
export type RecordPeriod = "single_job" | "week" | "month" | "quarter" | "year" | "all_time";
```

And add to `SECTION_DEFS`:
```ts
  all_time: { title: "All-Time Bests", order: 5 },
```

- [ ] **Step 5: Run tests**

```bash
npx vitest run src/lib/wins/__tests__/recordFormatters.test.ts
```

Expected: 12 passing (10 existing + 2 new).

- [ ] **Step 6: Recognition.tsx — add Twins Records section**

In `src/pages/tech/Recognition.tsx`, add the `useCompanyRecords` import:
```tsx
import { useCompanyRecords } from '@/hooks/wins/useCompanyRecords';
```

Inside the component body, fetch the data:
```tsx
const { data: companyRecords = [] } = useCompanyRecords();
```

Add a new section near the top of the JSX (above the streaks/per-tech sections), conditionally rendered when there are any company records:

```tsx
{companyRecords.length > 0 && (
  <section className="space-y-3">
    <h2 className="text-base font-extrabold uppercase tracking-wider text-primary">
      🏢 Twins Records
    </h2>
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      {companyRecords
        .slice()
        .sort((a, b) => a.kpi_key.localeCompare(b.kpi_key) || a.period.localeCompare(b.period))
        .map((r) => {
          const f = formatRecord({
            kpi_key: r.kpi_key,
            period: r.period,
            value: r.value,
            achieved_at: r.achieved_at,
          });
          return (
            <PersonalRecordTile
              key={r.id}
              label={f.label}
              value={f.value}
              context={f.context}
              isFresh={r.is_fresh}
            />
          );
        })}
    </div>
  </section>
)}
```

Place this BEFORE the existing streaks/per-tech sections.

- [ ] **Step 7: TS compile**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 8: Commit**

```bash
git add src/hooks/wins/useCompanyRecords.ts src/lib/wins/recordFormatters.ts src/lib/wins/__tests__/recordFormatters.test.ts src/pages/tech/Recognition.tsx
git commit -m "feat(reviews-phase1): Twins Records section + useCompanyRecords + recordFormatters for most_reviews/avg_review_rating"
```

---

## M5 — Ship

### Task 5

```bash
npx vitest run 2>&1 | tail -10
npx tsc --noEmit
npm run build 2>&1 | tail -3
git push -u origin feat/reviews-phase1
```

Then PR via GitHub API:

```bash
TOKEN=$(printf "protocol=https\nhost=github.com\n" | git credential-osxkeychain get | grep '^password=' | cut -d= -f2)
curl -s -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.github+json" \
  -d "$(cat <<'EOF'
{
  "title": "feat(reviews-phase1): GBP review ingestion + Twins Records section (dormant pending OAuth)",
  "head": "feat/reviews-phase1",
  "base": "main",
  "body": "Implements `docs/superpowers/specs/2026-04-29-reviews-ingestion-design.md` Phase 1.\n\n## What's working today\n- New `reviews` + `company_records` tables with RLS\n- `sync-gbp-reviews` Edge Function deployed (currently dormant — returns `{skipped: 'no credentials'}` without error)\n- pg_cron schedule fires nightly at 07:15 UTC; safe to run dormant\n- `compute_company_review_records()` function ready (currently produces zero rows since `reviews` is empty)\n- Frontend: new 'Twins Records' section on `/tech/wins` (hidden until at least one record exists)\n- 12/12 formatter tests passing including 2 new (most_reviews, avg_review_rating)\n\n## What Daniel does (one-time, ~10 min)\nAfter this PR merges, do the GBP OAuth setup to activate the sync:\n\n1. **Open Google Cloud Console** → existing Twins project (the one with Google Ads).\n2. **APIs & Services → Library** → search 'Business Profile API' → enable.\n3. **APIs & Services → OAuth consent screen** → add scope `https://www.googleapis.com/auth/business.manage`.\n4. **Run an OAuth flow** to get a refresh token — easiest path: use Google's OAuth Playground (https://developers.google.com/oauthplayground/), enter your client ID + secret, authorize the new scope, get the refresh token.\n5. **Find your GBP IDs** — call `GET https://mybusiness.googleapis.com/v4/accounts` with the new access token. The response gives `accounts/{accountId}`. Then call `GET https://mybusiness.googleapis.com/v4/accounts/{accountId}/locations` to get `locations/{locationId}`.\n6. **Set 3 Supabase env vars** (Supabase dashboard → Functions → Settings):\n   - `GBP_REFRESH_TOKEN`\n   - `GBP_ACCOUNT_ID` (just the digits, e.g. `12345678901234567890`)\n   - `GBP_LOCATION_ID` (just the digits)\n7. **Trigger first sync manually** (or wait for next 07:15 UTC cron):\n```bash\ncurl -X POST https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/sync-gbp-reviews -H 'Authorization: Bearer <ANON_KEY>'\n```\n   Expected: `{synced: <N>}` where N = total reviews in your GBP listing.\n\nAfter that, the Twins Records section on `/tech/wins` populates within seconds (just refresh the page).\n\n## Phase 2 (future PR)\n- Layer B: fuzzy attribute reviews to techs by customer-name match\n- Layer C: review-redirect Edge Function + GHL template guidance for forward-going attribution\n- Per-tech 'most reviews' records on existing per-tech Wins sections\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"
}
EOF
)" | python3 -c "import sys,json; d=json.load(sys.stdin); print('PR URL:', d.get('html_url','ERR')); print('Number:', d.get('number','ERR'))"
```

---

## Self-Review

**Spec coverage (Phase 1):**
- ✅ `reviews` + `company_records` tables with RLS — Task 1
- ✅ Phase 2 attribution columns nullable on `reviews` (forward compat) — Task 1
- ✅ `compute_company_review_records()` SQL function — Task 1
- ✅ pg_cron schedule, dormant-safe — Task 1
- ✅ `sync-gbp-reviews` Edge Function with separable `sync.ts` for unit testing — Task 3
- ✅ Graceful no-credentials fallback — Task 3 (test #1 verifies)
- ✅ `useCompanyRecords` hook + Twins Records section — Task 4
- ✅ `recordFormatters` extended for new kpi_keys + `all_time` period — Task 4
- ✅ All-time SECTION_DEFS entry — Task 4

**No placeholders.** All SQL, TS, test code is verbatim.

**Type consistency:** `kpi_key` strings (`most_reviews`, `avg_review_rating`) consistent across SQL + LABEL_MAP + VALUE_FMT + tests. `period` value `'all_time'` added to RecordPeriod type AND to SQL CHECK constraint.
