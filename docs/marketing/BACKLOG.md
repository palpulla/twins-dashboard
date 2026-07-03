# Marketing Backlog (ranked)

**Updated:** 2026-07-03. Re-ranked in Monday briefs / monthly reviews. Items above the line get a spec → plan → build cycle; nothing paid launches without Daniel-approved test cap + kill criterion.

| # | Initiative | Bucket | Status | Next action | Needs Daniel |
|---|---|---|---|---|---|
| 1 | Fix `marketing_spend` sync (google_ads + google_lsa stale since 2026-06-16; meta dead since 2026-02-06) | Measurement | **APPROVED 2026-07-03** — in progress | Diagnose edge functions (`google-ads-sync`, `sync-google-lsa`, `meta-ads-sync`) | No (read/fix infra, reversible) |
| 2 | Fix `reviews` ingest (table empty since creation; review velocity unmeasurable) | Measurement | **APPROVED 2026-07-03** — in progress | Diagnose Places/GBP review ingest; check `places_reviews_interim` | Maybe (API key — Places key had referer restriction issue) |
| 3 | Attack the "Unknown" lead source (35% of earned revenue, $41,910/30d) | Measurement | **APPROVED 2026-07-03 (spec only)** | Options doc: CSR intake script + HCP source hygiene + GHL match; NO heuristic classifiers | Yes — process change touches CSR workflow |
| 4 | Finish $49 tune-up avatar ad | Capture | On hold — not approved in 2026-07-03 brief | Await Daniel green light | Yes — creative sign-off before it runs |
| 5 | Launch GHL messaging Phase 1 (confirm/reminder/thank-you/review/estimate-followup) | Base | Spec approved v3.1 | Build HCP→Supabase→GHL bridge | Yes — copy sign-off before any send |
| 6 | ROI attribution gaps (GHL attribution, booked semantics, GA4) | Measurement | Polish backlog exists | Spec the GHL attribution piece first (unblocks #3) | No (spec only) |
| 7 | Open estimates CSR tracker | Base | Spec + plan exist | Schedule build | No (internal tool) |
| 8 | Google Ads pilot (separate from LSA, measurable) | Capture | New idea | Proposal w/ test cap after #1 and #3 land | Yes — budget |
| 9 | Meta ads pilot w/ media-generator creative | Capture | New idea | Proposal w/ test cap; FB shows organic signal ($4,894/30d on $0) | Yes — budget |

**Ranking logic:** measurement first (1–3) because the budget rule is ROI-driven — we can't scale LSA, judge Google Ads, or fund Meta while spend tracking is stale, reviews are invisible, and a third of revenue is unattributed. Revenue-adjacent builds already specced (4–5) run in parallel as they're approved.
