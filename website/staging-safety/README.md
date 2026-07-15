# Twins WordPress staging safety

This package is a fail-closed must-use WordPress plugin for an isolated Twins
staging clone. It suppresses email, WordPress cron, indexing, and outbound HTTP
side effects while staging is reviewed. It is not an authentication control and
does not make a public staging hostname private; hosting-level access control is
still required.

The MU plugin is not sufficient by itself. It cannot intercept code that bypasses
WordPress hooks through direct curl or sockets, PHP mail, vendor SDKs, persistent
action queues, or code that runs from early WordPress drop-ins. A default-deny
hosting/server egress policy is mandatory even when every plugin test passes.

## Current private staging overhaul

**Status:** `READY_FOR_VISUAL_APPROVAL`

**Branch:** `codex/staging-site-safety`

**Production write authority:** `false`

The complete overhaul is currently live for private visual review at
`https://danielj140.sg-host.com/`. SiteGround HTTP Basic Authentication must
remain enabled; credentials are managed outside Git and are never stored here.

The staged package includes the Madison-inspired homepage, revised careers and
team experiences, Wisconsin cost pages, state/location surfaces for WI/KY and
private-staging IL, and a local non-submitting door builder. The shared header
uses a larger crossing logo, high-contrast phone treatment, prominent phone and
estimate actions, mobile Twin characters, a modern action dock, and motion that
respects `prefers-reduced-motion`.

Previously completed live QA recorded 161/161 browser route/viewport visits with
zero violations and a complete 687-page/1,335-asset crawl with zero failures.
The exact 819-file live source was recovered into this branch after the cleared
disposable worktree. The recovered suite has zero failures, all staging-host PHP
harnesses pass, both task-created SSH keys are removed, and the verified tree is
committed and pushed. The private clone is `READY_FOR_VISUAL_APPROVAL`.

Primary review routes:

- `/`, `/careers/`, `/our-team/`
- `/madison-garage-door-repair-lp/`, `/madison-tune-up-lp/`
- `/wi/garage-door-cost-in-madison-wi/`
- `/wi/garage-door-cost-in-milwaukee-wi/`
- `/door-builder/`, `/wi/door-builder/`, `/ky/design-your-door/`
- `/il/door-builder/`, `/il/`, `/ky/location/lexington/`

Canonical source-recovery archive:

- `/home/customer/staging-safety/final-live-source-recovery-20260715.tar.gz`
- SHA-256:
  `3dc6bc90217307da4ecc445857b9bc3e15336192cf7f143df1d7e842b11cfb97`

Pre-overhaul staging database rollback:

- `/home/customer/staging-safety/before-full-overhaul-20260714.sql.gz`
- SHA-256:
  `836dd8850730d4772956e041877cebd23d791700800a0a94588ebf1a9e12f374`

This package and every route above are staging-only. They do not authorize a
production deployment, production integration, DNS change, or real form/message
test. See
`docs/handoff/2026-07-14-private-staging-overhaul.md` for the current evidence
and remaining closure gates.

## Installation order

Install and verify this package **before restoring the production database** or
opening the clone in a browser. If the staging filesystem is itself restored,
install the MU plugin immediately after that file restore and before the database
restore or the first WordPress request.

1. Provision an isolated staging destination. Do not point production DNS at it.
2. Add these exact values to staging `wp-config.php` before WordPress loads:

   ```php
   define('WP_ENVIRONMENT_TYPE', 'staging');
   define('TWINS_STAGING_SAFETY', true);
   define('DISABLE_WP_CRON', true);
   ```

3. At the hosting/server layer, apply the default-deny outbound egress policy
   before restoring data. Permit only DNS to the controlled resolver; permit no
   application-layer outbound destination. Browser image loading does not
   require server-side Clopay access. Do not rely on PHP or WordPress filters as
   the egress boundary.
4. Disable and inspect every drop-in before the first WordPress request:
   `advanced-cache.php`, `object-cache.php`, `db.php`, and `sunrise.php`. Keep
   `WP_CACHE` disabled. A reviewed drop-in may be restored later only if it cannot
   send mail, make network calls, or execute a production queue.
5. Disable every hosting-panel or operating-system task that calls `wp-cron.php`.
6. Copy `mu-plugins/twins-staging-safety.php` to
   `wp-content/mu-plugins/twins-staging-safety.php`. Create the `mu-plugins`
   directory if necessary.
7. Confirm that the MU plugin is present and that all three constants have the
   exact values above. A missing or different value deliberately returns HTTP
   503 before any staging hooks are registered.
8. Restore the database while web access remains disabled. Sanitize or deactivate
   all client-side GTM, GHL, chat, and analytics snippets before the first
   frontend load. Inspect the restored database, Elementor content, theme files,
   and snippet managers. With offline database tooling, also disable or drain
   Action Scheduler and every other persistent action queue; loading an admin
   queue screen is already a WordPress request.
9. Complete the staging URL replacement and inspect for direct curl, socket,
   vendor-SDK, PHP mail, and action-queue transports. Only then authenticate to
   WordPress and make the first controlled request.

Do not install this package or its staging-only constants on production.
Production must not be changed by this runbook.

## Enforced behavior

- `wp_mail()` is short-circuited as handled without invoking a mail transport.
- Every WordPress server-side HTTP request is blocked with a `WP_Error`, including
  same-origin, Clopay, read-only, and authenticated requests. There is no
  WordPress HTTP allowlist.
- A default quarantine Content Security Policy permits same-origin resources and
  Clopay images, but `connect-src` is same-origin only. It blocks external scripts,
  frames, form targets, connections, and navigation where browser CSP support
  permits. `data:` is allowed only for images.
- Responses include `X-Robots-Tag: noindex, nofollow, noarchive`; WordPress meta
  robots and virtual `robots.txt` also deny indexing. These are crawler hints,
  not access control.
- A red `STAGING` warning appears in WordPress admin and on the frontend.
- Claude's approved `twx v2` visual kit is restored from a same-origin static
  stylesheet while WPCode remains inactive. This preserves the completed page
  presentation without enabling its scripts, endpoints, or integrations.
- Wisconsin pages reproduce the completed Madison/Milwaukee phone presentation
  with a browser-local text and `tel:` rewrite only; no booking, tracking, or
  messaging connection is enabled.
- Known broken legacy links copied from production redirect only to explicit,
  verified paths on the same staging hostname. These staging-only redirects do
  not change production content or conceal unrelated missing pages.
- The production Business Reviews shortcode renders a fixed local notice instead
  of loading review data or contacting a third-party review service. The plugin
  reasserts every protected shortcode after plugin and theme registration, and a
  `pre_do_shortcode_tag` fail-safe prevents later callback replacement.
- Clopay product/catalog and interactive door-builder shortcodes render a fixed
  local notice instead of scheduling work, writing remote caches, submitting a
  lead, or contacting a production integration.
- `DISABLE_WP_CRON` is mandatory, existing cron events are hidden from the
  runtime, and new or recurring events are rejected. Hosting-level cron must
  remain disabled too.
- Pre-existing nonempty quarantined ordinary or network options cause an HTTP
  503 refusal before request hooks are registered. Updates and additions are
  guarded on both ordinary-option and multisite network-option hooks. Ordinary
  unsafe additions are rejected by WordPress's real pre-insert `add_option`
  action; network additions are emptied by `pre_add_site_option_*` filters.
- `brainstrom_products` remains available as Astra's runtime product registry,
  but nested nonempty license, purchase, token, or key fields are rejected on
  additions and updates and cause a fail-closed refusal when already persisted.

In hosts-file staging, a browser can map the production hostname to staging while
server-side DNS still resolves that hostname to production. Every WordPress
server-side HTTP request, including same-origin HTTP, is therefore blocked to
prevent a staging request from reaching production. Browser navigation is
unaffected because this plugin
filters only WordPress's server-side HTTP API; normal page loads, browser form
requests, and browser REST requests still reach the hosts-file destination.
Exercise forms and integrations with synthetic data only. These safety gates are
not permission to send real leads.

## Verification before restore

This is a multisite clone. Repeat every gate independently for the main site,
`/wi`, and `/ky`; one passing site cannot authorize another. On each site, verify
its URL mapping, every form action, every WPCode integration, cron and queue
state, mail suppression, and network denial before visual QA.

For each staging site, confirm all of the following:

- the response is not HTTP 503;
- `X-Robots-Tag` contains `noindex, nofollow, noarchive`;
- the red frontend `STAGING — NOT PRODUCTION` banner is visible;
- the MU Plugins screen lists **Twins Staging Safety**;
- an attempted test email reports handled but no message arrives;
- an arbitrary external WordPress HTTP request returns
  `twins_staging_http_blocked`;
- a server-side WordPress HTTP request to the site's own hostname also returns
  `twins_staging_http_blocked`;
- no hosting-level cron task targets the clone.

If any check fails, stop. Take the clone offline and do not restore production
data until the gate is corrected.

## Current private staging chrome

As of 2026-07-13, the main private staging site at
`danielj140.sg-host.com` is in the verified `GLOBAL` chrome state. This is a
staging-only preview; it is not a production publication and grants no
production write authority.

- Header document `7336` and footer document `7344` have the fixed
  `include/general` condition.
- Saved menu document `7333` remains the header's modal-menu dependency.
- Original header `36`, saved menu `305`, and footer `1409` remain published but
  have no conditions, preserving an exact rollback path.
- Contact footer `2179` retains only its existing contact-page condition.
- Wisconsin and Kentucky multisite surfaces retain their original chrome.
- The staging visual kit applies `overflow-x: clip` to the root and body solely
  to contain legacy horizontal spill in the private preview. This rule is not a
  production change. It also confines the long cloned mobile menu to its modal
  scroll area and locks the background page only while that modal is open.

The condition transition changes no Elementor content, titles, statuses,
template types, links, menu bindings, or document hashes. Before promotion, the
following mode-600 backups were captured outside the web root:

- `/home/customer/staging-safety/chrome-before-20260713.json`
  (`sha256:5ab877462d07e1401ebf86512b47253104e5644874a7825957d89a9b2ebe65c6`)
- `/home/customer/staging-safety/before-global-chrome-20260713.sql.gz`
  (`sha256:c25fbf9ce2dc5eebd4d014aae09eaf6a059b524e1e34f9fa39913e2b8b88c8d8`)

The rollback transition is fixed and fail-closed. Verify it first with
`TWINS_STAGING_CHROME_DRY_RUN=1`; a valid dry run reports the actual state as
`GLOBAL`, projects `ORIGINAL`, reports `stagingMutation:false`, and lists only
documents `36`, `7336`, `1409`, and `7344` as projected changes. To execute the
rollback from the staging WordPress directory only when explicitly required:

```sh
TWINS_STAGING_CHROME_MODE=rollback wp eval-file /home/customer/staging-safety/staging-chrome-transition.php
```

Do not run this command on production. After a real rollback, flush Elementor
CSS and the private staging cache, then repeat browser and whole-site checks.

## Rollback

Rollback means destroying or sanitizing the staging clone; it never means
copying these files or constants to production.

1. Remove public access to the staging clone at the hosting layer.
2. Delete the restored database and uploaded production files, or destroy the
   entire staging instance.
3. After the restored data is no longer accessible, remove the MU plugin from
   the staging filesystem.
4. Remove the three staging-only constants only if the staging instance is being
   decommissioned. Never boot a restored clone after removing the safety gate.

If staging must be retained, leave the plugin and constants installed and restore
the clone from a known snapshot instead of weakening any gate.
