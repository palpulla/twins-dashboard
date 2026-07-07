# Marketing Backlog (ranked)

**Updated:** 2026-07-03. Re-ranked in Monday briefs / monthly reviews. Items above the line get a spec → plan → build cycle; nothing paid launches without Daniel-approved test cap + kill criterion.

| # | Initiative | Bucket | Status | Next action | Needs Daniel |
|---|---|---|---|---|---|
| 1a | ~~Google spend syncs stale~~ | Measurement | **FIXED 2026-07-03** (PR #327) | Root cause: Google Ads API v20 sunset. Bumped to v21, backfilled 6/17–7/3 ($6,915 recovered). Monitor next nightly run | No |
| 1b | ~~Meta spend sync dead~~ | Measurement | **FIXED 2026-07-05** | New token set in Supabase; sync returned 200, Facebook spend current through 2026-07-04. Graph API v20.0 still valid | No |
| 2 | ~~Fix `reviews` ingest~~ | Measurement | **FIXED 2026-07-05** (PR #329) | Places API (New) route: `sync-places-reviews` reads `GOOGLE_PLACES_API_KEY`, pulls recent Google reviews into `places_reviews_interim` daily (cron 7:35 AM). Verified: 5 reviews live. Full GBP archive parked behind gated access-request (low pri) | No |
| 3 | Attack the "Unknown" lead source (35% of earned revenue, $41,910/30d) | Measurement | **A+B EXECUTED 2026-07-03** | HCP source list cleaned 44→20 (done); Ivory intake script ready for Daniel to forward; Unknown% now tracked weekly in Monday brief. C (GHL rescue) deferred until #6 | Only forwarding the note to Ivory |
| 4 | Finish $49 tune-up avatar ad | Capture | On hold — not approved in 2026-07-03 brief | Await Daniel green light | Yes — creative sign-off before it runs |
| 5 | Launch GHL messaging Phase 1 (confirm/reminder/thank-you/review/estimate-followup) | Base | Spec approved v3.1 | Build HCP→Supabase→GHL bridge | Yes — copy sign-off before any send |
| 6 | ROI attribution gaps (GHL attribution, booked semantics, GA4) | Measurement | Polish backlog exists | Spec the GHL attribution piece first (unblocks #3) | No (spec only) |
| 7 | Open estimates CSR tracker | Base | Spec + plan exist | Schedule build | No (internal tool) |
| 8 | Google Ads pilot (separate from LSA, measurable) | Capture | New idea | Proposal w/ test cap after #1 and #3 land | Yes — budget |
| 9 | Meta ads pilot w/ media-generator creative | Capture | New idea | Proposal w/ test cap; FB shows organic signal ($4,894/30d on $0) | Yes — budget |
| 10 | AI search (AEO/GEO) program | Compound | **Spec written 2026-07-07** | [spec](../superpowers/specs/2026-07-07-ai-search-reddit-program-design.md). Phase 1: llms.txt + Service/FAQ schema + 5 extractable money pages + monthly AI-visibility scoreboard. Baseline: crawlable + LocalBusiness schema, but absent on cost/how-to queries, out-detailed by competitors | Publish to WP needs go |
| 11 | Reddit credibility program | Compound | **Spec written 2026-07-07** | Monitor r/madisonwi + r/HomeImprovement, Claude drafts disclosed helpful replies, HUMAN posts from real account. Never auto-post. Surface in Monday brief | Human posts each reply |

**Ranking logic:** measurement first (1–3) because the budget rule is ROI-driven. As of 2026-07-05 all three measurement gauges are fixed (Google spend, Meta spend, Google reviews) and lead-source hygiene is done — so the ROI picture is now trustworthy and the paid-pilot items (8, 9) are unblocked to propose with test caps in an upcoming Monday brief. Revenue-adjacent builds already specced (4–5) run in parallel as they're approved.
