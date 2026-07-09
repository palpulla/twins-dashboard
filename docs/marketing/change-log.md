# Marketing change log

Standing rule (CAP doc §8): no conversion action, pixel rule, or bid-strategy change ships without an entry here.

## 2026-07-08 — Claude, Clopay Product API v2 augment on product pages (Daniel-approved)

Spec: `docs/superpowers/specs/2026-07-08-clopay-product-api-pages-design.md`. Adds live, auto-updating Clopay content (official colors, photo-gallery iframe, brochures/docs, and a "Where To Buy" CTA) to the existing rich, ranking product pages. Decision: **augment, not replace** — all unique review content, H1s, and FAQs are preserved (wholesale replacement would have risked rankings). Rendered **server-side** (SEO-safe).

| # | Change | Detail | Revert |
|---|---|---|---|
| C1 | WPCode snippet "Twins x Clopay Product API (fetch+cache+shortcode)" — **main site ID 7050**, **/ky site ID 6369** | PHP, Auto Insert / Run Everywhere, Active. Registers `[clopay_product id="{id}" mode="specs|full"]`. Fetches `clopaydoor.com/api/v2/GetProductDetails/GetProductData` (public, no key, CORS `*`), 24h transient cache + durable last-good fallback + daily `twins_clopay_refresh` cron (warms 170/12/13). Prints swatch CSS once via `wp_head`. Code auto-prepends `<?php` in WPCode (snippet body omits its own tag). | Deactivate the snippet on each site → shortcode goes inert (pages keep all original content) |
| C2 | Added a "specs" section before the FAQ on 4 Elementor pages | Shortcode widget inserted at section index 7 via Elementor JS API (`document/elements/create`). main **Modern Steel** (page 6090, id 170), main **Gallery Steel** (6065, id 12), main **Classic** (6034, id 13), **/ky Classic** (6198, id 13). Verified server-side render: colors (24/19/14), gallery iframe, docs, CTA; H1 + "What We Like/Quick Verdict" + FAQ all preserved; main phone (833) 833-2010 intact (snippet 6753 did not clobber). WP Rocket auto-cleared on save (canonical URLs already serve it). | In each page's Elementor editor, delete the "Official Clopay Specs, Colors & Gallery" shortcode section |

| C3 | Dealer prop ID **100841** added to both snippets (2026-07-08) | Prepended `if ( ! defined('TWINS_CLOPAY_PROPID') ) { define('TWINS_CLOPAY_PROPID','100841'); }` to snippet 7050 (main) + 6369 (/ky). Where-To-Buy CTA now `clopaydoor.com/where-to-buy?propId=100841` → only Twins shows in Clopay's locator. Verified live on all 4 pages after WP Rocket clear-and-preload (both sites). Snippet edits do NOT auto-clear WP Rocket page cache — must clear manually. | Remove the define line from each snippet |

**Pending / not yet done:**
- **Kentucky product pages** (/ky Modern Steel id 170, /ky Gallery Steel id 12): Daniel approved building these, localized to Lexington KY (859 phone) matching the existing /ky Classic (page 6198, title "Clopay Classic™ Garage Lexington, KY"). Clone rich structure from main Modern Steel (6090) / Gallery (6065), re-localize Madison/Wisconsin→Lexington/Kentucky + phone→859, keep Clopay `mode="specs"` section (ids 170/12), set Rank Math title/meta. NOT yet built.
- **/wi Clopay product pages: intentionally SKIPPED** — main site already targets Wisconsin/Madison; /wi versions would cannibalize. Daniel chose KY-only.
- **EZDoor "Design Your Door" lead funnel** (workstream 1, spec `2026-07-08-clopay-door-builder-landing-design.md`) — NOT built yet; awaiting go-ahead + dealer-branded EZDoor links.

## 2026-07-06 — Claude, Monday-brief data fix (Daniel-approved)

`marketing_spend` KPI-data cleanup. Full backup taken first: `marketing_spend_backup_googleads_20260706` (1,680 rows, all `Google Ads`/`google_ads` label rows). Fully reversible by re-inserting from that table.

| # | Change | Detail | Revert |
|---|---|---|---|
| S1 | Removed duplicate legacy `Google Ads` rows for 2026-03-05..2026-07-03 | 253 rows / $11,877.82 deleted. Root cause: two platform labels (`Google Ads` legacy + `google_ads` new v21/v23 sync) both wrote the same spend after the PR #327 sync switch, double-counting Google Ads in every brief. Verified `google_ads` (API-sourced, authoritative) covers every overlap date to the cent (112/113 exact, 1 date off $0.05); zero LSA-pollution rows in this range. Corrected brief-week Google Ads = **$964.27** (was $1,928.59) | `INSERT INTO marketing_spend SELECT * FROM marketing_spend_backup_googleads_20260706 WHERE platform='Google Ads' AND date BETWEEN '2026-03-05' AND '2026-07-03'` |

**Verified correct, NOT changed:** LSA week 6/26–7/2 = $2,144.15 is right — matches Google's own LSA report to the cent for July MTD ($704.99); the week just straddles late June (LSA spent ~$1,566 6/26–6/30, then collapsed to ~$705 all July). Single `google_lsa` label, no duplication.

**Deferred to a separate spec (Phase 2, not touched):** (a) Feb 1–Mar 4 legacy `Google Ads` still double-counts (internal duplicate repair-campaign rows, e.g. `Garage Door Repair | Search` + `GDML | TGD | Repair` = same spend twice); (b) **$34,947 of LSA spend misfiled as `LocalServicesCampaign` rows inside the `Google Ads` platform** across 417 dates through 2026-03-04 — on 276 of those dates it's the only record of that LSA spend, so it must be relabeled to `google_lsa` (reconciling against existing LSA rows on the 141 overlap dates), not deleted. Mostly 2025 history; corrupts historical Google-Ads-vs-LSA split. Needs its own careful cleanup + hook hardening.

## 2026-07-04 — Claude, per approved corrective-action plan (spec 2026-07-04)

### Google Ads (customer 7171993484, via ads-audit edge fn)

| # | Change | Detail | Revert |
|---|---|---|---|
| G1 | Created shared negative-keyword set "CAP negatives 2026-07" | `sharedSets/12144677471`; 42 PHRASE negatives (diy, how to, manual(ly), parts, kit, lowes, home depot, menards, harbor freight, amazon, youtube, video, instructions, panel only, jobs, hiring, salary, training, rental, apartment, weight, lubricant, wd-40/wd40, cost, for sale, supplier(s), watertown, janesville, hayward, beloit, milwaukee, green bay, appleton, oshkosh, eau claire, la crosse, wausau, kenosha, racine) + 4 EXACT brand negatives (twins garage doors variants) | Remove set or detach (G2) |
| G2 | Attached the set to both Search campaigns | `campaignSharedSets/23209603182~12144677471` (Repair), `23209631247~12144677471` (Installation) | Remove the two campaignSharedSets |
| G3 | Installation Search daily budget $38 → $15 | `campaignBudgets/15103082338` amountMicros 38000000 → 15000000. Rationale: LP has no form + dead CTAs; last conversion May 22 | Set amountMicros back to 38000000 |
| G4 | Demoted "Click to call" conversion action to Secondary | `conversionActions/7635727417` primaryForGoal true → false. A tap on a number is not a lead (CAP §1.3 policy) | Set primaryForGoal back to true |
| G5 | Created conversion action "Booked Job (HCP)" — SECONDARY | `conversionActions/7672808531`, UPLOAD_CLICKS / CONVERTED_LEAD / ONE_PER_CLICK / 90d click lookback, `primaryForGoal: false`, `includeInConversionsMetric: false` (observation only — does not touch the cleaned conversion column or bidding). Target of the weekly booked-job offline upload (CAP Task 11) | Remove the conversion action (or set status REMOVED) |
| G6 | Standing weekly offline upload: `offline-conversions-weekly` edge fn | Fridays 10:07 UTC via pg_cron; uploads the closed Fri–Thu payroll week's booked (non-Estimate) HCP jobs as hashed-identifier enhanced conversions for leads into G5 via Data Manager API. Dedupe: transactionId = job uuid. Per-run history in `offline_conversion_uploads` table (silent, no alerts). Design + status: `docs/marketing/audits/2026-07-04-cap/offline-conversions-design.md`. **LIVE since 2026-07-04** (unblocked by G7) | `SELECT cron.unschedule('offline-conversions-weekly')` + remove G5 |
| G7 | Unblocked G6: OAuth re-consent + first successful upload (2026-07-04) | Enabled Data Manager API on GCP project `twins-dashboard-marketing`; re-minted GOOGLE_ADS_REFRESH_TOKEN with `adwords`+`datamanager` scopes (Daniel approved consent); fixed required `eventSource: "WEB"` on events (twins-dash `ef41cd7`). First real run: window 2026-06-26→2026-07-03, 26/26 accepted, requestId `77e4ee00-d454-4638-872f-e1a259046006`. `sync-google-ads` re-verified OK on the new token (316 rows) | Rotate the token back (old one is invalidated — re-consent instead); conversions themselves age out per Google retention |
| G8 | Removed 8 negatives from set 12144677471 (Daniel, 2026-07-04) | Per Daniel: removed all 4 EXACT brand negatives (`twins garage doors`, `twins garage door`, `twin garage doors`, `twins garage doors madison`) — he wants paid to still show on brand searches — and 4 in-area/near cities he serves (`milwaukee`, `beloit`, `janesville`, `watertown`). Set now 38 (was 46). Remaining city negatives: appleton, oshkosh, eau claire, la crosse, wausau, kenosha, racine, green bay, hayward | Re-add via `sharedCriteria:mutate` create if ever wanted back |

Deliberately NOT changed: tCPA (deferred until conversion data is clean ~2 weeks); competitor-name negatives (borderline: ~$340 spend, 2 conversions ≈ $170/conv — Daniel/Legit5 call); PMax brand exclusion (needs brand lists, no public API path — Legit5 to do in UI); geo (already Presence-only).

### Meta (act_388398022876424)

| # | Change | Detail | Revert |
|---|---|---|---|
| M1 | Deactivated pixel 'Lead' event rule | Rule 3986528874925127 fired on the Google Ads thank-you URL (/wi/thank-you-g-ppc-lp/) — cross-channel contamination (CAP §1.1). Set INACTIVE (not deleted; UI-created rules hard-delete). Real per-form Lead rules ship with the form fix | Set status ACTIVE |
| M2 | Staged challenger campaign: Calls Reel (PAUSED, 2026-07-04) | Campaign `120255240287140399` "Twins – Challenger – Calls Reel (Madison) – CAP 2026-07", OUTCOME_LEADS. Ad set `120255240291070399` $12/day, QUALITY_CALL, PHONE_CALL destination, Madison +25mi ages 30-65 (cloned from the proven Reel–Calls ad set). Ad `120255240330090399` = Remotion emergency reel (video `1012245481211656`), CALL_NOW tel:+16088888785. Created via Marketing API from Daniel's own Ads Manager session (Meta MCP not connected in this session); PAUSED at campaign, ad set, and ad level | Delete campaign 120255240287140399 (cascades to ad set/ad) |
| M3 | Staged challenger campaign: Review Carousel (PAUSED, 2026-07-04) | Campaign `120255240287320399`, OUTCOME_TRAFFIC. Ad set `120255240292460399` $8/day, LINK_CLICKS, same geo. Ad `120255240333670399` = 7-card carousel of real GBP review quotes (verbatim; reviewer towns only where stated in the review — Deerfield) → twinsgaragedoors.com/wi + UTMs | Delete campaign 120255240287320399 |
| M4 | Staged challenger campaign: Install Financing (PAUSED, 2026-07-04) | Campaign `120255240287560399`, OUTCOME_TRAFFIC. Ad set `120255240292960399` $8/day, LINK_CLICKS, same geo. Ad `120255240336000399` = real Twins before/after install photo (CompanyCam, from /wi site) + GoodLeap band → twinsgaragedoors.com/wi/financing/ + UTMs | Delete campaign 120255240287560399 |
| M5 | Created public Supabase Storage bucket `ad-assets` on jwrpj (2026-07-04) | Hosts the challenger ad media (meta-challenger-2026-07/, ~3.5 MB) so Meta could fetch by URL; source assets + copy + review provenance committed to docs/marketing/creative/2026-07-04-meta-challenger/ | Delete bucket once ads are approved/cached (or leave) |

CAP Task 13 context: combined staged budget $28/day (within the $25–30 spec) vs the fatigued Legit5 $49 Tune-Up dynamic ad carrying ~94% of spend (CPL $68.10 → $86.28 May → June). Confirmed offers only ($0 service call, GoodLeap financing); same-day copy restricted to the repair-emergency angle already live on the site; review quotes never invented (see creative folder's reviews-corpus.txt). The account's 2 pre-existing unpublished Ads Manager drafts (not Claude's) were left untouched — API-created objects bypass the draft queue.

| M6 | LAUNCHED M2–M4 with Daniel's explicit go (2026-07-04) | Daniel approved "Yes, launch all 3" after previews, with the standing condition that the Legit5 campaign is never touched (verified untouched: `120249121812270399` ACTIVE $66/day, zero edits). All 9 objects (3 campaigns/3 ad sets/3 ads) set ACTIVE; ads entered Meta review (IN_PROCESS) | Set the 3 campaign statuses back to PAUSED |

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
| M7 | Observed 2026-07-05: the three challenger campaigns (M2–M4) are back to PAUSED at campaign level | Not changed by this session. Ad-level objects remain ACTIVE/approved (effective_status CAMPAIGN_PAUSED, no disapprovals), so flipping the 3 campaign toggles relaunches instantly. $0 spent today on challengers; Legit5 `120249121812270399` untouched and delivering normally ($66/day). Awaiting Daniel's direction before any status change | n/a (observation only) |
| M8 | Daniel's call 2026-07-05: keep the three challenger campaigns PAUSED | No status changes made. Relaunch when he says so = flip the 3 campaign toggles (ads already approved): `120255240287140399`, `120255240287320399`, `120255240287560399` | n/a |
