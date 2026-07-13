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
