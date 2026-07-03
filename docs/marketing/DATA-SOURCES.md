# Marketing Brief — Data Sources

All queries run read-only against the live Supabase project `jwrpjuqaynownxaoeayi` (twins-dash-prod). Each metric mirrors canonical dashboard logic; the brief reads existing math, never reinvents it. If a query returns no rows, the brief says "no data" — numbers are never invented.

## 1. Spend by channel

**Table:** `marketing_spend(platform, date, spend_amount, clicks, leads_generated)`
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

**Tables:** `reviews` (`review_at`, `rating`, `tech_id`), `review_card_clicks` (`clicked_at`, `tech`, utm columns). Views `v_reviews_by_tech`, `v_review_clicks_by_tech` exist for per-tech cuts.

```sql
SELECT
  (SELECT COUNT(*) FROM reviews WHERE review_at >= :from AND review_at < :to_exclusive) AS new_reviews,
  (SELECT ROUND(AVG(rating),2) FROM reviews WHERE review_at >= :from AND review_at < :to_exclusive) AS avg_rating,
  (SELECT COUNT(*) FROM review_card_clicks WHERE clicked_at >= :from AND clicked_at < :to_exclusive) AS card_clicks;
```

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

1. Spend syncs stale since 2026-06-16 (google_ads, google_lsa) — needs edge-function investigation.
2. Meta spend sync dead since 2026-02-06.
3. GHL → job attribution not wired (ROI polish backlog).
4. Funnel "booked" semantics + GA4 sync (ROI polish backlog).
5. `reviews` table is EMPTY (0 rows, verified 2026-07-03) — the Google-review ingest never populated it, so review velocity can only report card clicks until fixed. `places_reviews_interim` exists and may hold partial data.
