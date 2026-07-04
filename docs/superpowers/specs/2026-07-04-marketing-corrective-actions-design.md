# Marketing Corrective Actions — Autonomous Execution Design

**Date:** 2026-07-04
**Source:** "Marketing Corrective Action Plan - July 2026.docx" (Desktop, prepared 2026-07-04, covers May–June 2026)
**Approved by:** Daniel, 2026-07-04 ("Run all phases")

## Goal

Execute the corrective action plan's findings autonomously, without manual work from Daniel and without waiting on Legit5, using access that already exists:

- **Google Ads API** — live credentials on jwrpj (`sync-google-ads` secrets, customer 6863455097, GAQL v21, fixed in PR #327). Reads are proven; mutations use the same OAuth scope.
- **Meta Ads MCP** — live account access (used for the July 4 account pull that produced the source doc).
- **twinsgaragedoors.com** — WordPress on SiteGround via browser + WPCode (precedent: review-redirect snippet 7016, Elementor team-page edits).
- **HCP data in jwrpj** — jobs, customers, revenue for lead-quality matching.
- **Gmail MCP** — drafts only.

## Phase 0 — Audit (read-only)

Deploy a temporary, cron-secret-gated GAQL audit function (or run one-off queries) against the Google Ads account:

1. **Conversion-action inventory** (doc §1.2): every conversion action with source, category, primary/secondary, count method, attribution, and May/June counts segmented by campaign. Settles the 7 → 34 → 19 swing and whether Installation's 5 May conversions were real.
2. **PMax segmentation** (doc §2.3): conversions by conversion action, view-through share, brand-term traffic. Verdict on the implausible $19.93–$40.37 CPA.
3. **Search-term reports** for Repair + Installation Search, 2026-05-01..06-30, sorted by cost; geographic (user-location) report; keyword list with match types; bid strategies; change history (June) relevant to the install LP tag.
4. **Install LP test lead**: submit a test through `/wi/garage-door-installation-lp-ppc/` via browser; observe GTM/pixel/conversion network calls. Marked clearly as a test lead so the office ignores it.
5. **Meta lead quality**: retrieve May–June instant-form leads; phone/email-match against HCP jobs in jwrpj; report qualified/booked rate for the 53 leads.
6. **Meta account attribution setting** and custom-conversion May/June counts.

Deliverable: a findings report to Daniel. Findings gate Phase 1 specifics (e.g., tCPA only if conversion data is clean).

## Phase 1 — Fixes (reversible, logged)

7. **Google Ads hygiene** via API: negative-keyword list from doc §2.1 (refined by the search-term pull); geo to Presence-only + explicit rest-of-WI exclusion; account-level brand exclusions ("twins garage doors" + variants); Installation Search budget cap ~$15/day; demote non-lead conversion actions to Secondary per doc §1.3 policy (primary = qualified calls, lead forms, bookings, qualified messages). Every mutation recorded in a change log committed to the repo.
8. **Meta pixel repair**: retire/repoint the 'Lead' pixel rule (currently fires on a Google Ads thank-you URL); separate `/thank-you` vs `/ei-thank-you` custom-conversion rules to exact-path; via API where possible, Events Manager in browser otherwise.
9. **Website sprint** (WPCode + Elementor + Rank Math): remove `user-scalable=0`; sticky mobile call bar ([Call Now] + [Book Online]) on service/location pages; unify (608) 888-8785 vs (833) 833-2010 mobile-menu split; review badge (4.9★ · 687 reviews) + $49 tuneup offer in homepage hero; title/meta rewrites per doc §4.2 table; "this goes wrong easily — call" CTA blocks on top 5 DIY posts; noindex/rewrite the 'ippt' page; LocalBusiness + Service + FAQ schema; surface financing on install/repair pages. All copy uses real, confirmed facts only (608 number, real review count read live at edit time).

## Phase 2 — Plumbing (build)

10. **Offline-conversions pipeline**: weekly job matching booked HCP jobs back to leads (phone/email), uploading qualified/booked events to Google Ads and Meta. Extends existing jwrpj edge-function patterns.
11. **Full-funnel report**: extend `/marketing-brief` to doc §6 format (Spend → Leads → Qualified → Booked → Revenue per channel), with the §6 KPI definitions table; clicks/CPC demoted to appendix.
12. **Meta creative drafts**: generate review-proof carousel, repair-emergency reel, financing creative (twins-media-generator); stage as PAUSED campaigns with budgets configured. Launch requires Daniel's explicit go (new spend).

## Needs Daniel (one-time)

- Meta spend-sync re-auth (token expired 2026-05-03) — also prerequisite for CAPI restoration.
- Send the drafted Legit5 email (trimmed to items Claude cannot pull: Legit5's own report denominators, call-data ownership of the 833 number, rank-tracker export internals).
- CallRail decision — deferred; not a dependency for the above.

## Out of scope

GBP posting/reviews (OAuth blocked), Legit5's internal reporting, launching any new paid campaign without approval, CAPI server-side rebuild (blocked on Meta re-auth).

## Constraints

- No fabricated data anywhere; absence of data is reported as a finding (mirrors the source doc's own standard).
- Ad-account mutations limited to those listed; anything else goes back to Daniel first.
- KPI math on the dashboard is untouched.
- All work committed to git; ad-account changes logged in `docs/marketing/change-log.md`.
