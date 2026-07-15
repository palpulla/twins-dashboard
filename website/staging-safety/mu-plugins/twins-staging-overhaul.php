<?php
/**
 * Plugin Name: Twins Staging Overhaul Preview
 * Description: Fail-closed presentation layer for the private Twins staging clone.
 * Version: 0.1.0
 * Author: Twins Garage Doors
 * Network: true
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Stop WordPress before any preview implementation can load.
 *
 * @param string $reason Human-readable configuration failure.
 * @return void
 */
function twins_staging_overhaul_refuse_boot($reason) {
    wp_die(
        'Twins staging overhaul refused to boot: ' . $reason,
        'Staging overhaul configuration error',
        array('response' => 503)
    );
    exit;
}

if (!defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'staging') {
    twins_staging_overhaul_refuse_boot('WP_ENVIRONMENT_TYPE must be staging.');
}

if (!defined('TWINS_STAGING_SAFETY') || TWINS_STAGING_SAFETY !== true) {
    twins_staging_overhaul_refuse_boot('TWINS_STAGING_SAFETY must be the boolean true.');
}

if (!defined('DISABLE_WP_CRON') || DISABLE_WP_CRON !== true) {
    twins_staging_overhaul_refuse_boot('DISABLE_WP_CRON must be the boolean true.');
}

require_once __DIR__ . '/twins-staging-overhaul/bootstrap.php';
