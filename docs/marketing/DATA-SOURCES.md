# Marketing Brief — Data Sources

All queries run read-only against the live Supabase project `jwrpjuqaynownxaoeayi` (twins-dash-prod). Each metric mirrors canonical dashboard logic; the brief reads existing math, never reinvents it. If a query returns no rows, the brief says "no data" — numbers are never invented.

## 1. Spend by channel

**Table:** `marketing_spend(platform, date, spend_amount, clicks, leads_generated, calls_generated, conversations_generated)`

> **Result types are split (added 2026-08-05).** `leads_generated` counts **form leads only**. It always did, which is why call-objective Meta campaigns silently read as 0 and every brief before this date understated Meta. Two columns now carry the rest:
>
> | Column | Meta `action_type` | What it is |
> |---|---|---|
> | `leads_generated` | `lead` | Form submissions |
> | `calls_generated` | `click_to_call_native_call_placed` | **Tap-to-call events.** Not connected calls, not booked jobs. |
> | `conversations_generated` | `onsite_conversion.messaging_conversation_started_7d` | Messaging conversations started |
>
> `NULL` means the platform does not report that result type. A real reported zero is stored as `0`. Keep the distinction: Google Ads and LSA rows are NULL for calls and conversations, not zero.
>
> **Never sum the three columns into one "leads" figure.** Meta reports the same underlying event under multiple action types. The Legit5 `$49 Tune-Up` campaign reports 30 form leads *and* 30 messaging conversations for Jul 5 to Aug 3, which is almost certainly the same 30 people. Report each result type on its own line with its own cost per result.
>
> **Always call `calls_generated` "tap-to-calls", never "leads."** The connect rate is unknown until `calls_inbound` is populated, so cost per booked job cannot be derived from it. Quoting $14.92 per *lead* rather than per *tap* overstates the channel.
**Canonical logic:** `twins-dash/src/hooks/use-marketing-source-roi.ts` (`fetchAdSpend` + `PLATFORM_TO_CANONICAL`)

Platform values are mixed-era; map to canonical sources exactly as the hook does:

| Raw platform | Canonical |
|---|---|
| `Google Ads`, `google_ads` | Google Ads |
| `Google LSA`, `google_lsa` | Google LSA |
| `Meta Ads`, `Facebook Ads`, `Facebook`, `meta_ads` | Facebook |

```sql
SELECT CASE
    WHEN platform IN ('Google Ads','google_ads') THEN 'Google Ads'
    WHEN platform IN ('Google LSA','google_lsa') THEN 'Google LSA'
    WHEN platform IN ('Meta Ads','Facebook Ads','Facebook','meta_ads') THEN 'Facebook'
    ELSE platform END AS source,
  SUM(spend_amount)::numeric(12,2) AS spend,
  SUM(clicks) AS clicks,
  SUM(leads_generated) AS leads,
  MAX(date) AS last_data_day
FROM marketing_spend
WHERE date BETWEEN :from AND :to
GROUP BY 1 ORDER BY spend DESC;
```

**Known gap (found 2026-07-03):** newest `marketing_spend` row is 2026-06-16 for both `google_ads` and `google_lsa`; the spend syncs have been stale ~2.5 weeks. `meta_ads` only ever has 2026-02-01..06. Always report `last_data_day` in the brief so stale syncs are visible, and treat spend windows past the last data day as incomplete.

## 2. Jobs + earned revenue by source

**Table:** `jobs` (`lead_source` text, `revenue_amount`, `hcp_data` jsonb, `job_type`, `completed_at`)
**Canonical rules:**
- Exclude estimates: `job_type <> 'Estimate'` (mirrors `use-dashboard-data.ts:43`).
- Earned revenue only: `(hcp_data->>'outstanding_balance')::numeric = 0` (or null-safe equivalent). Sold-but-unpaid is a separate bucket, never added to earned.
- Jobs with blank `lead_source` are reported as **Unattributed** — never classified by guesswork. (The dashboard hook additionally rescues LSA/Meta attributions; the brief reports the simple split plus a pointer to the ROI page for rescued attribution. Do not re-implement the rescue in SQL.)

```sql
SELECT COALESCE(NULLIF(TRIM(lead_source), ''), 'Unattributed') AS source,
  COUNT(*) AS completed_jobs,
  SUM(revenue_amount) FILTER (
    WHERE COALESCE((hcp_data->>'outstanding_balance')::numeric, 0) = 0
  )::numeric(12,2) AS earned_revenue,
  SUM(revenue_amount) FILTER (
    WHERE COALESCE((hcp_data->>'outstanding_balance')::numeric, 0) > 0
  )::numeric(12,2) AS sold_unpaid
FROM jobs
WHERE job_type <> 'Estimate'
  AND completed_at >= :from AND completed_at < :to_exclusive
GROUP BY 1 ORDER BY earned_revenue DESC NULLS LAST;
```

## 3. Review velocity

**Live source (2026-07-05):** `places_reviews_interim` — the ~5 most-recent Google reviews via Places API (New), refreshed daily by the `sync-places-reviews` edge function (cron `places-reviews-daily`, 7:35 AM). Columns: `author_name`, `rating`, `review_text`, `published_at`, `est_tech_slug`/`est_technician_id` (correlated to review-card clicks, display-only estimate). Plus `review_card_clicks` (`clicked_at`, `tech`) for the click leading-indicator, and the `google-review-stats` edge function for the live aggregate rating + total count.

Because Places returns only the recent ~5, `new_reviews` from this table is reliable only at low review volume (currently accurate for Twins). For the true running total use `google-review-stats`.

```sql
SELECT
  (SELECT COUNT(*) FROM places_reviews_interim WHERE published_at >= :from AND published_at < :to_exclusive) AS new_reviews_recent,
  (SELECT ROUND(AVG(rating),2) FROM places_reviews_interim) AS avg_rating_recent5,
  (SELECT COUNT(*) FROM review_card_clicks WHERE clicked_at >= :from AND clicked_at < :to_exclusive) AS card_clicks;
```

The `reviews` table (GBP v4 full archive) stays empty until the legacy GBP API access request is approved — low priority; the Places feed covers the brief's needs.

## 4. GHL lead summary

**Table:** `ghl_contacts`; view `v_ghl_booking_rate_accurate`.
**Canonical logic:** `twins-dash/src/hooks/use-ghl-summary.ts`. Attribution from GHL into jobs is a known gap (ROI polish backlog) — report GHL contact counts as a leading indicator only, never join them to revenue.

```sql
SELECT COUNT(*) AS new_contacts FROM ghl_contacts
WHERE contact_created_at >= :from AND contact_created_at < :to_exclusive;
```
(Column verified 2026-07-03: `contact_created_at`; sync live, 1,222 total contacts.)

## 5. Content output

Not in the DB. Count last week's approved drafts:
```bash
ls /Users/daniel/twins-dashboard/twins-content-engine/approved/ | wc -l
```
plus a note of what shipped via DUNZO Social Planner.

## Window conventions

- **Weekly brief:** payroll week Fri–Thu for job/revenue numbers (`weekStartsOn: 5`, dates parsed as local midnight); calendar Mon–Sun acceptable for spend since ad platforms report calendar days. State the window on every table.
- **Monthly/baseline:** trailing 30 days + MTD.

## Known gaps (carried as backlog, not blockers)

1. ~~Spend syncs stale since 2026-06-16~~ **FIXED 2026-07-03** (Google Ads API v20 sunset; bumped to v21 in PR #327, backfilled through 7/3). Both functions now accept POST `{"daysBack": N}` (1–90, cron-secret-gated) for future backfills.
2. Meta spend sync dead since 2026-02-06 — access token expired 2026-05-03; blocked on Daniel re-auth. Graph API v20.0 default should be bumped at the same time.
3. GHL → job attribution not wired (ROI polish backlog) — 186 contacts phone-matched to jobs but 0 carry attribution_source.
4. Funnel "booked" semantics + GA4 sync (ROI polish backlog).
5. ~~`reviews` table empty / no review data~~ **RESOLVED 2026-07-05** via Places API (New): `sync-places-reviews` now populates `places_reviews_interim` daily with recent Google reviews (rating + text + author). The legacy GBP `reviews` full-archive table stays empty pending the gated GBP API access request (low priority).
