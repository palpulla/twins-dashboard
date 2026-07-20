<?php
/**
 * Plugin Name: Twins Overhaul (Production)
 * Description: Presentation layer for the live Twins site. Production counterpart
 *              of twins-staging-overhaul.php - CUTOVER ONLY, not loaded on staging.
 * Version: 0.1.0
 * Author: Twins Garage Doors
 * Network: true
 *
 * Why a separate loader (see production-build-spec.md / blocker-a-build-unseal.md):
 * the staging loader (twins-staging-overhaul.php) fails closed unless
 * WP_ENVIRONMENT_TYPE === 'staging' AND the staging-only constants
 * TWINS_STAGING_SAFETY / DISABLE_WP_CRON are set, and it is a verify-prerequisite
 * (never deployed by the pipeline). Production runs normal WordPress (cron on, no
 * staging safety plugin), so it needs its own loader that boots the SAME overhaul
 * bootstrap under the production environment and additionally loads the callback
 * submission script (the shared twins-overhaul.js carries no fetch by contract).
 *
 * Install path on the production host: wp-content/mu-plugins/twins-overhaul.php,
 * alongside the deployed twins-staging-overhaul/ package directory. The staging
 * safety plugin (twins-staging-safety.php) is NOT present on production.
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Stop WordPress before any implementation can load.
 *
 * @param string $reason Human-readable configuration failure.
 * @return void
 */
function twins_overhaul_production_refuse_boot($reason) {
    wp_die(
        'Twins overhaul refused to boot: ' . $reason,
        'Twins overhaul configuration error',
        array('response' => 503)
    );
    exit;
}

// Fail closed anywhere that is not explicitly the production environment, so this
// loader can never accidentally run on staging or an unconfigured host.
if (!defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'production') {
    twins_overhaul_production_refuse_boot('WP_ENVIRONMENT_TYPE must be production.');
}

// The staging safety plugin must not be present on production; if its flag is set
// the host is misconfigured (a staging plugin leaked into production).
if (defined('TWINS_STAGING_SAFETY') && TWINS_STAGING_SAFETY === true) {
    twins_overhaul_production_refuse_boot('TWINS_STAGING_SAFETY must not be set on production.');
}

require_once __DIR__ . '/twins-staging-overhaul/bootstrap.php';

/**
 * Load the callback submission handler on top of the portable brand script.
 *
 * The shared brand script (handle `twins-brand-experience`) contains no network
 * calls (staging JS contract); production adds this small script, which POSTs the
 * callback form to the approved lead-intake edge function. The brand routes that
 * render the callback form (e.g. contact-brand) enqueue `twins-brand-experience`,
 * NOT `twins-staging-overhaul` (that handle is enqueued only on campaign-preserve
 * routes — see twins_overhaul_enqueue_assets). So guard on and depend on the
 * brand handle, or the callback never loads and the form falls back to a native
 * submit. Deployed inside the overhaul package dir (a deploy namespace) so the
 * sealed build ships it without touching the verify-prerequisite asset directory.
 */
function twins_overhaul_production_enqueue_callback() {
    if (!wp_script_is('twins-brand-experience', 'enqueued')) {
        return;
    }
    wp_enqueue_script(
        'twins-overhaul-callback',
        '/wp-content/mu-plugins/twins-staging-overhaul/production-callback.js',
        array('twins-brand-experience'),
        '0.1.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'twins_overhaul_production_enqueue_callback', PHP_INT_MAX, 0);
