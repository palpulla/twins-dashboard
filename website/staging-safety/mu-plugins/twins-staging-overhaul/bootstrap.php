<?php
/**
 * Load the fixed preview data and semantic chrome after the root MU plugin has
 * proven every staging gate.
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/cost-data.php';
require_once __DIR__ . '/brand-runtime.php';
require_once __DIR__ . '/components.php';
require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/templates/service.php';
require_once __DIR__ . '/templates/location.php';
require_once __DIR__ . '/templates/home.php';
require_once __DIR__ . '/templates/trust.php';
require_once __DIR__ . '/templates/article.php';
require_once __DIR__ . '/templates/cost.php';
require_once __DIR__ . '/templates/builder.php';
require_once __DIR__ . '/renderers.php';

/**
 * Resolve an approved same-origin preview asset.
 *
 * Unknown names deliberately return an empty URL. This function never turns a
 * caller value into a path.
 *
 * @param string $name Fixed logical asset name.
 * @return string
 */
function twins_overhaul_asset_url(string $name): string {
    $assets = array(
        'stylesheet' => '/wp-content/mu-plugins/twins-staging-assets/twins-overhaul.css',
        'script' => '/wp-content/mu-plugins/twins-staging-assets/twins-overhaul.js',
        'logo' => '/wp-content/mu-plugins/twins-staging-assets/twins-logo.png',
        'twin-left' => '/wp-content/mu-plugins/twins-staging-assets/twin-left.png',
        'twin-right' => '/wp-content/mu-plugins/twins-staging-assets/twin-right.png',
        'truck-png' => '/wp-content/mu-plugins/twins-staging-assets/twins-service-truck-cutout.png',
        'truck-webp' => '/wp-content/mu-plugins/twins-staging-assets/twins-service-truck-cutout.webp',
    );

    return isset($assets[$name]) ? $assets[$name] : '';
}
