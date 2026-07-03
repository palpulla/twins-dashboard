# Marketing Backlog (ranked)

**Updated:** 2026-07-03. Re-ranked in Monday briefs / monthly reviews. Items above the line get a spec → plan → build cycle; nothing paid launches without Daniel-approved test cap + kill criterion.

| # | Initiative | Bucket | Status | Next action | Needs Daniel |
|---|---|---|---|---|---|
| 1a | ~~Google spend syncs stale~~ | Measurement | **FIXED 2026-07-03** (PR #327) | Root cause: Google Ads API v20 sunset. Bumped to v21, backfilled 6/17–7/3 ($6,915 recovered). Monitor next nightly run | No |
| 1b | Meta spend sync dead since 2026-02-06 | Measurement | **BLOCKED on Daniel** | Meta access token expired 2026-05-03 (OAuthException 190). Need fresh long-lived token for meta-ads-sync. Also bump Graph API v20.0 while at it | Yes — Meta re-auth |
| 2 | Fix `reviews` ingest (table empty; velocity unmeasurable) | Measurement | **BLOCKED on Daniel** | Root cause: sync-gbp-reviews exits "no credentials" — GBP_REFRESH_TOKEN/ACCOUNT_ID/LOCATION_ID never set. Need GBP OAuth (business.manage). Places fallback also empty (key referer-restricted) | Yes — GBP OAuth grant |
| 3 | Attack the "Unknown" lead source (35% of earned revenue, $41,910/30d) | Measurement | **Options doc delivered 2026-07-03** | [proposals/2026-07-03-unknown-lead-source-options.md](proposals/2026-07-03-unknown-lead-source-options.md) — awaiting Daniel's pick (rec: A+B now, C later) | Yes — approve A/B |
| 4 | Finish $49 tune-up avatar ad | Capture | On hold — not approved in 2026-07-03 brief | Await Daniel green light | Yes — creative sign-off before it runs |
| 5 | Launch GHL messaging Phase 1 (confirm/reminder/thank-you/review/estimate-followup) | Base | Spec approved v3.1 | Build HCP→Supabase→GHL bridge | Yes — copy sign-off before any send |
| 6 | ROI attribution gaps (GHL attribution, booked semantics, GA4) | Measurement | Polish backlog exists | Spec the GHL attribution piece first (unblocks #3) | No (spec only) |
| 7 | Open estimates CSR tracker | Base | Spec + plan exist | Schedule build | No (internal tool) |
| 8 | Google Ads pilot (separate from LSA, measurable) | Capture | New idea | Proposal w/ test cap after #1 and #3 land | Yes — budget |
| 9 | Meta ads pilot w/ media-generator creative | Capture | New idea | Proposal w/ test cap; FB shows organic signal ($4,894/30d on $0) | Yes — budget |

**Ranking logic:** measurement first (1–3) because the budget rule is ROI-driven — we can't scale LSA, judge Google Ads, or fund Meta while spend tracking is stale, reviews are invisible, and a third of revenue is unattributed. Revenue-adjacent builds already specced (4–5) run in parallel as they're approved.
