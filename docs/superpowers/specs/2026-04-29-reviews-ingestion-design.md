# Reviews Ingestion: Google Business Profile + GHL Redirect

**Status:** Draft, auto-approving per Daniel's "do both" + "whatever you recommend with as much automatic as possible"
**Author:** Claude (with Daniel)
**Date:** 2026-04-29

## Problem

Daniel's original Wins-tab ask included "most reviews in a week/month/quarter." Reviews data does not exist anywhere in the system today (verified via repo-wide grep across migrations, src/, supabase/functions/). To deliver that record, Twins needs a reviews ingestion pipeline.

Constraints from the brainstorm:

- **Source:** Google Business Profile (GBP) — public Twins-Garage-Doors listing on Google Maps.
- **Review request channels:** GHL (Go High Level) automated text/email after job completion (dominant channel) + QR code cards handed to customers in person (long tail).
- **Attribution:** Daniel wants per-tech attribution where possible. Google reviews don't include tech identity natively, so attribution is a heuristic + GHL-redirect hybrid.
- **Manual workload:** as low as possible. Daniel will do one-time setup; ongoing should be zero.

## Goal

Build the reviews ingestion in three layers, each running independently:

1. **Layer A — Company-level reviews count.** Nightly poll GBP API → store every review → render "Twins's most reviews in a quarter" as a new section on the Wins tab.
2. **Layer B — Best-effort tech attribution.** When a review arrives, fuzzy-match to a recently-completed job by customer name + date window. Hits get `tech_id` populated.
3. **Layer C — GHL-redirect for forward-going attribution.** New Edge Function `/review-redirect?tech=<id>&job=<id>` logs the click → 302-redirects to the GBP review URL. Daniel updates the GHL automated text/email template once to use this URL. Future reviews via GHL are perfectly attributed.

The overall attribution rate should reach ~85%+ once GHL is updated, falling back to Layer B's ~30-50% for QR-card and organic reviews.

## Non-Goals

- **No HCP review-request integration.** Twins doesn't use HCP's "send review request" feature.
- **No manual admin tagging UI.** Daniel doesn't want to babysit a queue.
- **No review response automation.** This spec only ingests reviews; response composition is out of scope.
- **No notification system on review arrival.** Reviews show up on the Wins tab; if Daniel wants alerts later, that's a separate spec.
- **No backfill of historical reviews into per-tech attribution.** Layer A counts every review (including pre-deploy ones if the GBP API exposes history). Layer B can attempt fuzzy match on historical reviews but accuracy will be lower.
- **No multi-location support.** Twins has one GBP listing.

## Architecture

### Data model

New table `public.reviews`:

```sql
CREATE TABLE public.reviews (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  -- GBP review identity
  gbp_review_id   text NOT NULL UNIQUE,           -- GBP's reviewId (stable across pulls)
  -- Review content
  rating          smallint NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment         text,
  reviewer_name   text,
  reviewer_photo  text,
  review_at       timestamptz NOT NULL,           -- GBP's createTime
  -- Twins response (if any)
  reply_comment   text,
  reply_at        timestamptz,
  -- Attribution
  tech_id         integer REFERENCES public.payroll_techs(id),
  attribution_source text NOT NULL DEFAULT 'unattributed'
    CHECK (attribution_source IN ('ghl_redirect', 'fuzzy_match', 'manual', 'unattributed')),
  attributed_at   timestamptz,
  matched_job_id  integer REFERENCES public.payroll_jobs(id),  -- if attribution worked
  -- Bookkeeping
  raw_payload     jsonb NOT NULL,                  -- the full GBP review object
  first_seen_at   timestamptz NOT NULL DEFAULT now(),
  updated_at      timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX reviews_review_at_idx ON public.reviews(review_at DESC);
CREATE INDEX reviews_tech_review_at_idx ON public.reviews(tech_id, review_at DESC) WHERE tech_id IS NOT NULL;
```

RLS: admin-readable. Tech-readable for `tech_id = current_technician_id()` (a tech sees only their own attributed reviews). Service role writes via Edge Functions.

New table `public.review_redirect_clicks`:

```sql
CREATE TABLE public.review_redirect_clicks (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tech_id     integer REFERENCES public.payroll_techs(id),
  job_id      integer REFERENCES public.payroll_jobs(id),
  customer_email text,
  customer_phone text,
  user_agent  text,
  ip_hash     text,                                 -- not raw IP; SHA-256 for dedup-only
  clicked_at  timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX review_redirect_clicks_tech_clicked_at_idx
  ON public.review_redirect_clicks(tech_id, clicked_at DESC);
```

This table powers Layer C's attribution: when a new GBP review arrives, look back N days for a click matching the same customer or in the same time window, and use the tech_id from the click.

### Edge Functions

**1. `review-redirect` (new, public).** Receives `GET /review-redirect?tech=<id>&job=<id>`. Logs a click row, sets a tech-attribution cookie (so the customer's eventual review is correlated even if multiple jobs in their history). 302-redirects to `https://g.page/r/<TWINS_GBP_REVIEW_CODE>`. Exposed at the public URL `https://twinsdash.com/review-redirect`.

**2. `sync-gbp-reviews` (new, service-role, scheduled).** Nightly pg_cron job:
- Calls Google Business Profile API: `GET /v4/accounts/{account}/locations/{location}/reviews` paginated.
- Upserts each review into `public.reviews` keyed by `gbp_review_id`.
- For each NEW review (first_seen_at = now()), runs the attribution pipeline:
  1. **Layer C (GHL-redirect):** look at `review_redirect_clicks` for clicks within the last 14 days where customer_email or customer_phone matches anything findable in the GBP review payload (often just the reviewer name and time). If a match is found, attribute via `attribution_source = 'ghl_redirect'`.
  2. **Layer B (fuzzy match):** if no Layer-C hit, fuzzy-match the GBP `reviewer_name` against `payroll_jobs.customer_display` for jobs completed in the last 30 days where that tech was the owner. Use Postgres `levenshtein` or `pg_trgm` similarity. Threshold: similarity > 0.5 AND single best match (no ties).
  3. **No match → `attribution_source = 'unattributed'`.**
- Logs the result (count synced / count attributed / count unattributed) to `tech_part_match_log`-style observability table or just stdout (Edge Function logs).

### Configuration

OAuth setup (Daniel does once):
1. Open the existing Google Cloud project (already used for Google Ads — `GOOGLE_ADS_CLIENT_ID` / `GOOGLE_ADS_CLIENT_SECRET` env vars confirm it exists).
2. Enable the **Business Profile API** (formerly Google My Business API).
3. Add the OAuth scope `https://www.googleapis.com/auth/business.manage` to the consent screen.
4. Run an OAuth flow once to get a refresh token for the GBP API.
5. Find the GBP location ID for the Twins listing (`accounts/{accountId}/locations/{locationId}`).
6. Set Supabase env vars: `GBP_REFRESH_TOKEN`, `GBP_ACCOUNT_ID`, `GBP_LOCATION_ID`. (`GOOGLE_ADS_CLIENT_ID` / `GOOGLE_ADS_CLIENT_SECRET` are reused.)

Daniel also does once:
- Update the GHL automated text/email template to use `https://twinsdash.com/review-redirect?tech={{contact.assigned_user.id}}&job={{contact.custom_field.job_id}}` (or whatever GHL's actual variable syntax is — confirm with GHL support if unclear). The exact variable names may differ; Daniel knows GHL.

### Frontend

**`Recognition.tsx`** — add a new "Twins Records" section above the existing per-tech sections. This section is the same for every tech (it's company-level). Renders one tile per period:

- "Most reviews in a week" — value = max count, context = "week of <date>"
- "Most reviews in a month" — value = max count, context = "<Month YYYY>"
- "Most reviews in a quarter" — value = max count, context = "Q<n> <YYYY>"
- "Highest avg rating in a quarter" (bonus) — value = decimal rating, context = "Q<n> <YYYY>"

Plus the per-tech sections gain new tiles for tech-attributed reviews:

- "Most reviews in a week" / "month" / "quarter" — tech_id-scoped count

### Compute integration

Extend `compute_streaks_and_prs()` once more to compute review counts per period from the `reviews` table:

```sql
-- For each tech, count tech-attributed reviews per period.
-- For company-level (tech_id = NULL placeholder or separate aggregation), count all.
```

Concretely: 4 new compute blocks per tech (3 periods × 1 metric) + 4 company-level blocks stored under a sentinel tech_id (or a new `company_records` table — TBD).

Actually simpler: store company-level review records in a separate `public.company_records` table with the same shape as `tech_personal_records` minus `tech_id`. Cleaner separation. The frontend reads both and renders accordingly.

### `kpi_keys` additions to `scorecard_tier_thresholds`

- `most_reviews` (`direction = 'higher'`, `unit = 'count'`) — for "most reviews in a period" records
- `avg_review_rating` (`direction = 'higher'`, `unit = 'count'` with display nuance) — for the bonus rating record

Bronze/silver/gold/elite values are placeholders (records, not tiers).

## Components Summary

| File | Action |
|---|---|
| `supabase/migrations/<new>.sql` | Create — `reviews`, `review_redirect_clicks`, `company_records` tables; new kpi_keys; extend `compute_streaks_and_prs()` with review-counting blocks |
| `supabase/functions/review-redirect/index.ts` | Create — public GET endpoint, logs click + 302-redirects |
| `supabase/functions/sync-gbp-reviews/index.ts` | Create — nightly service-role poll + attribution pipeline |
| `supabase/migrations/<another-new>.sql` | Create — pg_cron schedule for sync-gbp-reviews (mirrors the existing pattern) |
| `src/pages/tech/Recognition.tsx` | Modify — add "Twins Records" section + tech-attributed review tiles |
| `src/lib/wins/recordFormatters.ts` | Modify — add `most_reviews`, `avg_review_rating` to LABEL_MAP/VALUE_FMT |
| `src/hooks/wins/useCompanyRecords.ts` | Create — fetches `company_records` rows for the Twins-Records section |

7 files. Bigger spec than recent ones, but each piece is independent.

## Reversibility

- Drop the 3 tables (`reviews`, `review_redirect_clicks`, `company_records`)
- Delete the 2 Edge Functions
- Revert frontend changes
- The pg_cron schedule entry can be removed via `cron.unschedule(...)` (mirrors existing email-cron pattern)

## Testing

### Unit tests

- `review-redirect` Edge Function — 3 tests: valid click logs row + redirects, missing tech/job params still redirects (graceful), invalid params return 400.
- `sync-gbp-reviews` attribution logic — 5 tests: GHL-redirect match, fuzzy name match, no match, deduplication on re-sync, malformed GBP payload.
- `recordFormatters.ts` — add 2 tests for new kpi_keys.

### Manual smoke (live, post-OAuth-setup)

1. Trigger sync-gbp-reviews manually: `curl -X POST .../sync-gbp-reviews`. Verify reviews appear in `public.reviews`.
2. Click the GHL-redirect URL with `?tech=1&job=999`. Verify a row in `review_redirect_clicks` and a 302 to Google.
3. Wait for a real customer to leave a review through the new GHL link. Verify it gets `attribution_source = 'ghl_redirect'` on the next sync.
4. Open `/tech/wins`. Verify the new "Twins Records" section appears with real review counts.

## Phasing

This is bigger than v2-rate-KPIs. To make it shippable in pieces:

- **Phase 1 (this spec, ship as one PR):** schema + sync function + frontend Twins-Records section. Layer A only — company-level counts, no per-tech attribution. Reviews appear on the Wins tab as company records within hours of the first sync.
- **Phase 2 (follow-up PR):** Layers B + C — review-redirect Edge Function, fuzzy-match attribution, GHL template update guidance. Per-tech review records light up on the Wins tab.

Phase 1 alone delivers 60% of the value for ~40% of the work. Phase 2 fills in attribution. We ship Phase 1 first; Phase 2 is a follow-up spec once Phase 1 is live and Daniel has done the GBP OAuth setup.

## What Daniel does (one-time)

After Phase 1 spec ships:
1. **Google Cloud project setup** (~10 min): enable Business Profile API, add scope, run OAuth flow once to get refresh token, find location ID. I can't do this — requires logging into Daniel's Google account.
2. **Set 3 Supabase env vars**: `GBP_REFRESH_TOKEN`, `GBP_ACCOUNT_ID`, `GBP_LOCATION_ID`. Done via Supabase dashboard or CLI.

After Phase 2 spec ships:
3. **Update GHL template** (~5 min): change the bare Google review URL in the automated text/email to the new redirect URL.

After both, ongoing maintenance is zero.

## Open Questions

- **Attribution token lifetime in `review_redirect_clicks`.** A customer might click the GHL link the day they get the message but only leave a review a week later. The sync function looks back N days; what's N? Default to **14 days** (covers the long tail without too many false positives). Adjustable per real-world data.
- **Company-level vs per-tech storage.** Currently spec says `company_records` is a separate table. Alternative: use `tech_personal_records` with `tech_id = -1` or NULL. Separate table is cleaner, no FK constraint workarounds.
- **API quotas.** Google Business Profile API has tight per-day quotas. For a single GBP listing with maybe 10-30 reviews/month, polling once/night is well under quota. If quotas become a problem, switch to weekly.

## Phase 1 = this initial implementation. Phase 2 spec follows after Daniel's GBP OAuth setup is done.
