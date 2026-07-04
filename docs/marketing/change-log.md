# Marketing change log

Standing rule (CAP doc §8): no conversion action, pixel rule, or bid-strategy change ships without an entry here.

## 2026-07-04 — Claude, per approved corrective-action plan (spec 2026-07-04)

### Google Ads (customer 7171993484, via ads-audit edge fn)

| # | Change | Detail | Revert |
|---|---|---|---|
| G1 | Created shared negative-keyword set "CAP negatives 2026-07" | `sharedSets/12144677471`; 42 PHRASE negatives (diy, how to, manual(ly), parts, kit, lowes, home depot, menards, harbor freight, amazon, youtube, video, instructions, panel only, jobs, hiring, salary, training, rental, apartment, weight, lubricant, wd-40/wd40, cost, for sale, supplier(s), watertown, janesville, hayward, beloit, milwaukee, green bay, appleton, oshkosh, eau claire, la crosse, wausau, kenosha, racine) + 4 EXACT brand negatives (twins garage doors variants) | Remove set or detach (G2) |
| G2 | Attached the set to both Search campaigns | `campaignSharedSets/23209603182~12144677471` (Repair), `23209631247~12144677471` (Installation) | Remove the two campaignSharedSets |
| G3 | Installation Search daily budget $38 → $15 | `campaignBudgets/15103082338` amountMicros 38000000 → 15000000. Rationale: LP has no form + dead CTAs; last conversion May 22 | Set amountMicros back to 38000000 |
| G4 | Demoted "Click to call" conversion action to Secondary | `conversionActions/7635727417` primaryForGoal true → false. A tap on a number is not a lead (CAP §1.3 policy) | Set primaryForGoal back to true |
| G5 | Created conversion action "Booked Job (HCP)" — SECONDARY | `conversionActions/7672808531`, UPLOAD_CLICKS / CONVERTED_LEAD / ONE_PER_CLICK / 90d click lookback, `primaryForGoal: false`, `includeInConversionsMetric: false` (observation only — does not touch the cleaned conversion column or bidding). Target of the weekly booked-job offline upload (CAP Task 11) | Remove the conversion action (or set status REMOVED) |
| G6 | Standing weekly offline upload: `offline-conversions-weekly` edge fn | Fridays 10:07 UTC via pg_cron; uploads the closed Fri–Thu payroll week's booked (non-Estimate) HCP jobs as hashed-identifier enhanced conversions for leads into G5 via Data Manager API. Dedupe: transactionId = job uuid. Per-run history in `offline_conversion_uploads` table (silent, no alerts). Design + status: `docs/marketing/audits/2026-07-04-cap/offline-conversions-design.md`. **Currently failing with a recorded scope error** — needs one-time OAuth re-consent adding the `datamanager` scope to GOOGLE_ADS_REFRESH_TOKEN (steps in the design note); self-heals after that | `SELECT cron.unschedule('offline-conversions-weekly')` + remove G5 |

Deliberately NOT changed: tCPA (deferred until conversion data is clean ~2 weeks); competitor-name negatives (borderline: ~$340 spend, 2 conversions ≈ $170/conv — Daniel/Legit5 call); PMax brand exclusion (needs brand lists, no public API path — Legit5 to do in UI); geo (already Presence-only).

### Meta (act_388398022876424)

| # | Change | Detail | Revert |
|---|---|---|---|
| M1 | Deactivated pixel 'Lead' event rule | Rule 3986528874925127 fired on the Google Ads thank-you URL (/wi/thank-you-g-ppc-lp/) — cross-channel contamination (CAP §1.1). Set INACTIVE (not deleted; UI-created rules hard-delete). Real per-form Lead rules ship with the form fix | Set status ACTIVE |
| M2 | Staged challenger campaign: Calls Reel (PAUSED, 2026-07-04) | Campaign `120255240287140399` "Twins – Challenger – Calls Reel (Madison) – CAP 2026-07", OUTCOME_LEADS. Ad set `120255240291070399` $12/day, QUALITY_CALL, PHONE_CALL destination, Madison +25mi ages 30-65 (cloned from the proven Reel–Calls ad set). Ad `120255240330090399` = Remotion emergency reel (video `1012245481211656`), CALL_NOW tel:+16088888785. Created via Marketing API from Daniel's own Ads Manager session (Meta MCP not connected in this session); PAUSED at campaign, ad set, and ad level | Delete campaign 120255240287140399 (cascades to ad set/ad) |
| M3 | Staged challenger campaign: Review Carousel (PAUSED, 2026-07-04) | Campaign `120255240287320399`, OUTCOME_TRAFFIC. Ad set `120255240292460399` $8/day, LINK_CLICKS, same geo. Ad `120255240333670399` = 7-card carousel of real GBP review quotes (verbatim; reviewer towns only where stated in the review — Deerfield) → twinsgaragedoors.com/wi + UTMs | Delete campaign 120255240287320399 |
| M4 | Staged challenger campaign: Install Financing (PAUSED, 2026-07-04) | Campaign `120255240287560399`, OUTCOME_TRAFFIC. Ad set `120255240292960399` $8/day, LINK_CLICKS, same geo. Ad `120255240336000399` = real Twins before/after install photo (CompanyCam, from /wi site) + GoodLeap band → twinsgaragedoors.com/wi/financing/ + UTMs | Delete campaign 120255240287560399 |
| M5 | Created public Supabase Storage bucket `ad-assets` on jwrpj (2026-07-04) | Hosts the challenger ad media (meta-challenger-2026-07/, ~3.5 MB) so Meta could fetch by URL; source assets + copy + review provenance committed to docs/marketing/creative/2026-07-04-meta-challenger/ | Delete bucket once ads are approved/cached (or leave) |

CAP Task 13 context: combined staged budget $28/day (within the $25–30 spec) vs the fatigued Legit5 $49 Tune-Up dynamic ad carrying ~94% of spend (CPL $68.10 → $86.28 May → June). Confirmed offers only ($0 service call, GoodLeap financing); same-day copy restricted to the repair-emergency angle already live on the site; review quotes never invented (see creative folder's reviews-corpus.txt). NOTHING activates without Daniel's explicit go. The account's 2 pre-existing unpublished Ads Manager drafts (not Claude's) were left untouched — API-created objects bypass the draft queue.

Deferred: custom-conversion rule separation — Meta CC rules are immutable (archive+recreate only); recreating now is pointless because the broken forms produce no thank-you URLs. Lands with the form rebuild. CAPI restoration remains blocked on Meta re-auth (token expired 2026-05-03).

### Website (twinsgaragedoors.com main + /wi, via WPCode/Rank Math)

| # | Change | Detail | Revert |
|---|---|---|---|
| W1 | Sticky mobile call bar + viewport zoom fix | WPCode snippet 7044 (main) + 6753 (/wi), site-wide footer. Call (608) 888-8785 + Book Online (HCP booking URL). Also rewrites the viewport meta to re-enable pinch zoom | Deactivate the snippets |
| W2 | Phone unification | Snippet 6753 addendum rewrites tel:8338332010 links/text to (608) 888-8785 on /wi (the 833 lives in an Elementor header template; ownership still unknown per CAP §2.4) | Remove addendum |
| W3 | DIY-post CTA blocks | Snippet 7045 (main): "This job goes wrong easily" callout + call/book buttons on 5 DIY posts (genie manual, manually-lift, clopay low-headroom kit, low-headroom systems, clopay wood install) | Deactivate snippet |
| W4 | LocalBusiness schema | Snippet 7045 (main) + 6754 (/wi): name/phone/Madison-WI/24-7 hours. No aggregateRating (self-serving markup risk), no street address (not verified — add when confirmed) | Deactivate snippets |
| W5 | Title/meta rewrites (Rank Math) | /wi/garage-door-repair-in-madison-wi/ → "24/7 Garage Door Repair Madison, WI \| 4.9★ on Google \| Twins"; /wi/emergency-garage-door-repair-madison-wi/ → same-day angle; ippt post retitled to garage-scoped phrasing | Restore old titles (in git history of this file's commit context) |

Notes: the orphan /wi/garage-door-installation-lp-ppc/ (California 916 pool numbers, no form) is NOINDEXED — no organic exposure; flagged to Legit5 to kill or rebuild. Hero review-badge and financing surfacing are DEFERRED follow-ups (Elementor content edits). The /go/* paid LPs are Legit5's GHL funnels (foreign infrastructure) — Twins-side snippets cannot reach them; their broken form (401 to foreign GHL location) is the top Legit5 action item.
