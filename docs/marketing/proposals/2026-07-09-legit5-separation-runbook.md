# Legit5 Separation Runbook (ready to fire)

2026-07-09. Companion to the keep/replace/renegotiate memo. This is the one-click plan: everything is sequenced so that when Daniel says "send the notice," nothing breaks. Nothing here has been sent to Legit5.

## The money clock

Payment was made today, July 9. The contract requires 30 days notice. If notice lands today or tomorrow, cancellation is effective around August 8 and today's payment covers the entire notice period, so this month is the last $2,400. Every day of delay past the billing date risks one more month, because notice given July 15 means effective August 14, and the August 9 billing would likely fire first. Check the contract for whether they prorate; assume they do not.

**Target: complete Phase 0 within 48 to 72 hours, send notice by July 11 to 12.** The rest of the work happens inside the paid notice window, while they are still contractually obligated.

## Discovered dependencies (verified 2026-07-09)

1. **Hosting is theirs.** twinsgaragedoors.com resolves to WordPress.com/Automattic infrastructure (A records 192.0.79.136 and .171, Automattic CDN headers, MalCare firewall) — Legit5's agency hosting, matching their sitemanager@wpmanager.io admin and BlogVault/MalCare stack. DNS zone is at SiteGround (ns1/ns2.siteground.net); domain is registered at Squarespace Domains, expires June 2027. The site must be migrated to Daniel-controlled hosting and the A records repointed. Note: it is a WordPress multisite (main + /wi subsite), which constrains the migration tooling.
2. **Site chat is theirs.** The chat bubble is the GHL LeadConnector WordPress plugin, wired to Legit5's GHL. The plugin settings page is not accessible to the Tal Joseph admin account (network-level lockout), so the swap is: deactivate their plugin (or reconnect it) and install the chat widget from Twins' own GHL location (Dunzo, iRUlbIBg7PzSfLrPiR2j). Confirm the current widget ID during execution.
3. **Paid landing pages are theirs.** /go/* and offer.twinsgaragedoors.com are GHL funnels in Legit5's location (ATDh3QGRFcbWAxmrvh2G), reverse-proxied; offer subdomain CNAMEs to GHL's ludicrous.cloud. These are the live Google Ads final URLs. Their form path is the one that was returning 401.
4. **Tracking numbers are theirs.** (608) 447-5351 pool on /go/ pages, (608) 933-4223 on the offer page.
5. **Blog author account is theirs.** sitemanager@wpmanager.io, plus whatever wpmanager.io tooling and the BlogVault/MalCare subscriptions ride on their accounts.

Twins-owned and safe: Google Ads account 7171993484 (they only have manager access via collins@legit5.com), Meta ad account 388398022876424 and campaigns and pixel, the Facebook page and its lead forms, the WordPress content itself, LSA, the domain, and all dashboard infrastructure.

## Phase 0 — de-risk before notice (48 to 72 hours)

| # | Item | Owner | Status |
|---|---|---|---|
| 0.1 | Full multisite backup exported OFF their hosting (files + DB for main and /wi), stored locally + cloud | Claude via WP admin | PARTIAL 2026-07-09: content WXR exports for main (27.7MB) + /wi (56.2MB) in ~/twins-site-backups/2026-07-09/; full-file backup staged as task chip (needs ~$99 A1WM multisite extension, Daniel to approve) |
| 0.2 | Verify Daniel's SiteGround login works (DNS zone control) and Squarespace Domains login (registrar) | Daniel, 10 min | pending |
| 0.3 | Confirm target hosting (existing SiteGround plan if alive, else new account) | Daniel + Claude | pending |
| 0.4 | lp-lead-intake edge function live: LP forms post to Twins GHL location, logged in DB | Claude | DONE 2026-07-09 (PR #346, e2e-verified) |
| 0.5 | Replacement LPs built on Twins WP (repair + $49 tune-up), forms wired to 0.4, main (608) 888-8785 number, end-to-end form test | Claude | DONE 2026-07-09, PUBLISHED noindex: /madison-garage-door-repair-lp/ + /madison-tune-up-lp/ (Daniel approved design w/ both twins) |
| 0.6 | Export from Legit5 GHL location whatever is reachable (contacts, form submissions); export Meta lead-form CSVs from Ads Manager | Claude + Daniel | pending |
| 0.7 | Repoint Google Ads final URLs + Meta ad destinations to the new LPs (change-logged, reversible) | Claude, needs Daniel approval | pending |
| 0.8 | Swap chat widget to Dunzo GHL location; test a chat message arrives in Dunzo | Claude | DONE 2026-07-09 (change-log L1–L5): Dunzo widget 66b654c1e70da57b4d7e70ba branded navy/yellow, WPCode snippets 7152 (main) + 6773 (/wi), LC plugin deactivated, test chat verified in Dunzo Conversations. GOTCHA: a phantom copy of the LC plugin still executes server-side on main despite deactivation (hosting-level; unreachable from wp-admin) — its widget id was DB-swapped to Dunzo's, so main double-loads the same widget until the hosting migration (2.1) kills it |
| 0.9 | Website forms off Legit5 (found while drafting notice) | Claude | DONE 2026-07-09 (change-log L6–L9): main /contact-us/ gform_1 now POSTs to lp-lead-intake (snippet 7165), Legit5's robot.zapier.com notification disabled; /wi/contact-us/ Legit5 GHL iframe ("Website Form", their location via link.leadrbrd.com) replaced with a native form posting to lp-lead-intake; both E2E-verified into lp_leads (synced → Dunzo contact). Sitewide sweep: no other Legit5 embeds. /go/* untouched |

Phase 0 leaves Legit5 with nothing load-bearing except hosting, which is covered by the paid notice window.

**NOTICE SENT 2026-07-09** (Daniel emailed Tanner, cc support@legit5.com). Service through ~Aug 8, covered by the Jul 9 payment. NEW FINDING while drafting: main-site "Website Forms" leads also route through Legit5's GHL (notifications from leads@legit5.com), so the swap task covers forms, not just chat.

## Phase 1 — fire (one click)

| # | Item |
|---|---|
| 1.1 | Daniel sends the cancellation notice (email drafted and waiting in Gmail drafts once Phase 0 completes; nothing sends without Daniel hitting send) |
| 1.2 | Same day: screenshot/export any remaining reports or assets from their dashboards |

## Phase 2 — during the 30-day notice window

| # | Item |
|---|---|
| 2.1 | Migrate WordPress multisite to Daniel's hosting; stage on temp URL; verify /wi, Elementor, WPCode snippets, forms, review-redirect snippet 7016, door-builder endpoint |
| 2.2 | Flip A records at SiteGround to new hosting; keep TTL low; verify with cache-buster; watch HCP webhooks and WPCode-dependent flows |
| 2.3 | Replace MalCare/BlogVault (theirs) with Daniel-owned backup + firewall (SiteGround Security or equivalent) |
| 2.4 | Kill or repoint offer.twinsgaragedoors.com DNS once no ads reference it |
| 2.5 | Confirm no automations live only in their GHL location (review sends, missed-call textback); rebuild needed ones in Dunzo |
| 2.6 | Decide fate of their LG5 Google campaign and their Meta Tune-Up campaign (pause at effective date, in-house challengers take the budget) |

## Phase 3 — at effective date (about August 8)

| # | Item |
|---|---|
| 3.1 | Remove collins@legit5.com manager link from Google Ads 7171993484 |
| 3.2 | Remove Legit5 from Meta Business Manager roles on act 388398022876424 and the Facebook page |
| 3.3 | Delete/demote sitemanager@wpmanager.io WP admin; deactivate LeadConnector plugin if not already; remove their MalCare/BlogVault connections |
| 3.4 | Rotate WP admin passwords and any shared credentials |
| 3.5 | Final sweep: crawl site for remaining legit5/GHL-foreign references; verify all 5 phone numbers map to Twins-owned lines |

## Takeover operating plan (replaces the $2,400 retainer)

- **Google Ads:** managed via the ads-audit function with every change logged in docs/marketing/change-log.md. Weekly optimization pass (search terms, negatives, budgets, bids); offline booked-job conversions finish wiring after the OAuth re-consent, then tCPA. Spend changes always get Daniel's approval first.
- **Meta:** in-house campaigns (goat call, reel, Messenger) continue under the daily briefing skill with the $65/call alert ceiling. New creative through the established pipeline (real photos, brand rules, Daniel approves before spend).
- **SEO:** content engine publishes Madison-intent posts (target 4/month) with focus keywords, internal links, and LocalBusiness schema; Rank Math instant indexing on publish. Quarterly content audit replaces the zero-view agency posts.
- **Reporting:** Monday marketing brief (already live) covers spend, cost per call/lead, booked revenue by channel, and a monthly what-changed log. More reporting than Legit5 ever sent.
- **KPI guardrails:** $65/call and $45/lead ceilings (15 percent cost of sale at current funnel economics); anything above runs two weeks max before kill-or-fix.

## Open items needing Daniel (10 minutes total)

1. Confirm SiteGround and Squarespace Domains logins work (0.2).
2. Name the target hosting (0.3): reuse SiteGround if the plan is active.
3. Approve the ad-destination repoint when LPs are ready (0.7).
4. Say the word on notice day; the email will be sitting in drafts.
