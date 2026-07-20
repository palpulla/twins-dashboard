# Production release runbook

The go-live sequence for the site overhaul. Every step is reversible until
step 6; owner authorization is required to begin step 4 onward.

## 0. Preconditions (all must be true)

> Work these through `owner-gate-checklist.md` first — it is the owner-facing
> decision tracker (business calls, access, verified backup, authorizations) that
> culminates in the go-decision. The list below is the same gates in runbook form.


- [ ] Owner sign-off on the staged site (danielj140.sg-host.com) page by page.
- [ ] Clopay licensing answered (builder matrix either shipped or explicitly deferred).
- [ ] Hormann decision executed (redesign or redirect).
- [ ] IL forwarding proven or IL confirmed dark at launch.
- [ ] BlogVault (or equivalent) full-site backup verified restorable; fresh
      DB dump saved off-host (pattern: `/home/customer/staging-safety/before-*.sql.gz`).
- [ ] Production WP admin access + SiteGround access confirmed.

## 1. Build the production package

Follow `production-build-spec.md` — it has the exact file manifest, the two
security-contract changes this requires (an owner-reviewed un-seal of
`build-packages.mjs`, and an environment gate on the overhaul's form scan so the
production callback form is not refused), the brand-runtime adapter env branch,
and the build sequence. Summary:

- The pipeline marks the production package DEFERRED by design
  (`build-packages.mjs:187`); the build tool also structurally refuses
  production-cutover sources (line 89). Both need a reviewed change.
- Ship the brand core + overhaul MINUS `twins-staging-safety.php` and the
  staging loader `twins-staging-overhaul.php` (neither is deployed), PLUS
  `production-adapters.php` (wired into `brand-runtime.php` under
  `WP_ENVIRONMENT_TYPE === 'production'`), `production-callback.js`
  (production-only asset; staging JS contract bans fetch), and
  `production-overhaul-loader.php` → `twins-overhaul.php` (production needs its
  own loader; the staging one refuses non-staging boot — see
  `blocker-a-build-unseal.md`).
- Booking = HCP external; Quote = live callback form -> `lp-lead-intake`;
  Careers = external `/careers/#apply`. See `production-adapters.php`.
- Assembling the package (`build:packages`) is not deploying — deploy is step 4.

## 2. Content/SEO preflight (on production, before switching themes/routes)

- [ ] Execute `redirect-plan.md` findings 3 and 4 in Rank Math.
- [ ] Apply the blog prune (`blog-prune-list.md`): unpublish the pruned posts and
      301 the once-published ones → `/blog/`. Required, not optional —
      `redirect_guess` is ON (redirect-plan standing caveat), so a pruned URL
      without a 301 gets guessed to a wrong slug.
- [ ] Create the 3 missing service-page WP pages so they render instead of
      breaking: `/garage-door-repair/`, `/garage-door-tune-up/`,
      `/protection-plans/`. Finding 1 was a missing-page permalink guess, NOT a
      WPCode shim (see redirect-plan.md correction) — creating the pages is the
      fix; no shim retirement needed.
- [ ] Remove OTTO / Search Atlas per `otto-removal-runbook.md` (delete the
      plugin, revoke Search Atlas, clean residue) so its client-side overlay
      does not override the new site's titles/meta/schema/redirects.
- [ ] Confirm titles/meta: brand routes render their OWN managed
      `<title>`/meta/OG (Wave 1 — the overhaul's `pre_get_document_title` filter +
      `wp_head` emitter) and exactly one H1. OTTO removal (above) is what lets
      those win; verify on the first deployed page that the overhaul title shows,
      not an OTTO or stale Rank Math one.
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
- [ ] Callback form end-to-end (this path is production-only — it has never run
      on staging, so verify it deliberately). On `/contact-us/`, send exactly ONE
      test lead, labeled TEST, and confirm all of:
  > Fix already in place (PR #4, `52d83bc9`): the enqueue-handle, `form.name`,
  > `response.ok`, and load-timing bugs the code review found are fixed on the
  > branch, so these checks are *expected* to pass — this step confirms the
  > never-on-staging path actually works live. Still unchecked on purpose: it can
  > only be verified after the production deploy.
  - [ ] `production-callback.js` is actually loaded on the page (view source / no
        404 for it, and the submit handler is attached — the enqueue guards on the
        `twins-brand-experience` script handle, which must be present on this route).
  - [ ] Submitting fires a `POST` to `lp-lead-intake` in the Network tab and the
        page does NOT navigate (a page reload / URL gaining `?name=...` means the
        handler didn't bind and the form did a native submit).
  - [ ] The payload carries the real `name` and `phone` values (not empty), and
        the "Got it, we will call you back" message appears ONLY after a `200`
        (force/observe a non-2xx once if you can — it must show the error message,
        not false success).
  - [ ] The lead arrives in the dashboard/GHL and the phone gets the notification.
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
