# Production release runbook

The go-live sequence for the site overhaul. Every step is reversible until
step 6; owner authorization is required to begin step 4 onward.

## 0. Preconditions (all must be true)

- [ ] Owner sign-off on the staged site (danielj140.sg-host.com) page by page.
- [ ] Clopay licensing answered (builder matrix either shipped or explicitly deferred).
- [ ] Hormann decision executed (redesign or redirect).
- [ ] IL forwarding proven or IL confirmed dark at launch.
- [ ] BlogVault (or equivalent) full-site backup verified restorable; fresh
      DB dump saved off-host (pattern: `/home/customer/staging-safety/before-*.sql.gz`).
- [ ] Production WP admin access + SiteGround access confirmed.

## 1. Build the production package

- The pipeline currently marks the production package DEFERRED by design.
  Extend `tools/build-packages.mjs` with the production manifest: same brand
  core + overhaul files, MINUS `twins-staging-safety.php` behaviors (the
  safety plugin refuses to boot outside staging on its own; do not ship it),
  PLUS `website/production-cutover/production-adapters.php` wired into
  `brand-runtime.php` under `environment === 'production'`.
- Booking = HCP external; Quote = live callback form -> `lp-lead-intake`;
  Careers = production flow. See `production-adapters.php`.
- Add the callback submission JS to the production asset build (staging
  contracts ban fetch in the shared JS; keep it a production-only file).

## 2. Content/SEO preflight (on production, before switching themes/routes)

- [ ] Execute `redirect-plan.md` findings 3 and 4 in Rank Math.
- [ ] Create the 3 missing service-page WP pages so they render instead of
      breaking: `/garage-door-repair/`, `/garage-door-tune-up/`,
      `/protection-plans/`. Finding 1 was a missing-page permalink guess, NOT a
      WPCode shim (see redirect-plan.md correction) — creating the pages is the
      fix; no shim retirement needed.
- [ ] Remove OTTO / Search Atlas per `otto-removal-runbook.md` (delete the
      plugin, revoke Search Atlas, clean residue) so its client-side overlay
      does not override the new site's titles/meta/schema/redirects.
- [ ] Confirm titles/meta strategy: brand routes render one H1; Rank Math
      continues to own `<title>`/meta (verify on the first deployed page).
- [ ] robots.txt unchanged (AI crawlers already allowlisted); staging host
      keeps its 401/noindex.
- [ ] Snapshot current GSC coverage + top-100 queries for before/after.

## 3. Rehearsal on staging (final)

- [ ] Rerun the authenticated crawl matrix on staging (all green).
- [ ] `npm run test:all` locally (browser suite included).
- [ ] Verify review-summary numbers current (refresh from the live listing).

## 4. Release window

1. Enable maintenance notice window (optional; deploys are file-atomic).
2. Deploy the production package via the sealed pipeline (same
   dry-run -> capture -> release discipline, production transaction id).
3. Flush SiteGround dynamic cache (`site-tools-client domain-all update
   flush_cache=1` for twinsgaragedoors.com).

## 5. Immediate verification (within 15 minutes)

- [ ] Home, /wi/, /ky/, one service page, one WI city page, /our-team/,
      /reviews/, /door-builder/, /contact-us/: one header, correct phones,
      no staging banner, no "Private staging preview" strings anywhere.
- [ ] Book Online opens HCP in a new tab.
- [ ] Callback form submits; lead arrives in the dashboard/GHL; phone gets
      the notification. Send exactly ONE test lead, labeled TEST.
- [ ] Careers application reaches the hiring pipeline (one TEST entry).
- [ ] Schema validates (Rich Results test) on home, a service page, a city
      page, reviews.
- [ ] Redirect spot-checks: every source in redirect-plan hits its target in
      one hop; `/garage-door-repair/` serves the service page with HTTP 200.
- [ ] No console errors; Lighthouse mobile pass on home + one service page.

## 6. Post-launch (first week)

- [ ] GSC: submit updated sitemap, watch coverage/404 reports daily.
- [ ] Watch lead volume vs the prior week (LP + calls); alert if callbacks
      drop below normal call-economics baselines.
- [ ] Reviews live-counter integration (Places key without referer lock).
- [ ] Remove the staging deploy SSH key from SiteGround if no follow-up
      staging work is planned.

## Rollback

File-level: the sealed pipeline captures expected-old state per transaction;
`npm run deploy:staging:rollback` equivalent for the production transaction
restores prior bytes. Full-site: BlogVault restore point from step 0. Either
path first, then flush cache, then re-verify step 5 list.
