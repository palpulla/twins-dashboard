<?php
/**
 * Plugin Name: Twins Staging Safety
 * Description: Fail-closed side-effect and indexing controls for an isolated Twins WordPress staging clone.
 * Version: 1.1.3
 * Author: Twins Garage Doors
 * Network: true
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Stop WordPress before this package can register any staging-only hooks.
 *
 * @param string $reason Human-readable configuration failure.
 * @return void
 */
function twins_staging_safety_refuse_boot($reason) {
    wp_die(
        'Twins staging safety refused to boot: ' . $reason,
        'Staging safety configuration error',
        array('response' => 503)
    );
    exit;
}

if (!defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'staging') {
    twins_staging_safety_refuse_boot('WP_ENVIRONMENT_TYPE must be staging.');
}

if (!defined('TWINS_STAGING_SAFETY') || TWINS_STAGING_SAFETY !== true) {
    twins_staging_safety_refuse_boot('TWINS_STAGING_SAFETY must be the boolean true.');
}

if (!defined('DISABLE_WP_CRON') || DISABLE_WP_CRON !== true) {
    twins_staging_safety_refuse_boot('DISABLE_WP_CRON must be the boolean true.');
}

/**
 * Tell wp_mail() that a message was handled without invoking a mail transport.
 *
 * @param null|bool $return Short-circuit value supplied by WordPress.
 * @param array     $attributes Mail attributes supplied by WordPress.
 * @return bool
 */
function twins_staging_safety_block_mail($return, $attributes) {
    unset($return, $attributes);
    return true;
}

/**
 * Block every outbound WordPress HTTP request.
 *
 * @param mixed  $preempt Existing preempted response.
 * @param array  $arguments WordPress request arguments.
 * @param string $url Destination URL.
 * @return mixed|WP_Error
 */
function twins_staging_safety_filter_http($preempt, $arguments, $url) {
    unset($preempt, $arguments, $url);
    return new WP_Error(
        'twins_staging_http_blocked',
        'Outbound HTTP is blocked by Twins staging safety.'
    );
}

/**
 * Return the exact browser-side quarantine policy.
 *
 * Same-origin resources remain available for visual QA. Clopay is allowed only
 * for images. data: is allowed only for images.
 *
 * @return string
 */
function twins_staging_safety_csp_policy() {
    return implode('; ', array(
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: https://www.clopaydoor.com",
        "font-src 'self'",
        "connect-src 'self'",
        "media-src 'self'",
        "frame-src 'self'",
        "child-src 'self'",
        "worker-src 'self'",
        "manifest-src 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "navigate-to 'self'"
    )) . ';';
}

/**
 * Emit crawler directives on the HTTP response.
 *
 * @return void
 */
function twins_staging_safety_send_headers() {
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    header('Content-Security-Policy: ' . twins_staging_safety_csp_policy(), true);
}

/**
 * Add all crawler exclusions to the WordPress robots meta tag.
 *
 * @param array $robots Existing directives.
 * @return array
 */
function twins_staging_safety_robots_meta($robots) {
    $robots['noindex'] = true;
    $robots['nofollow'] = true;
    $robots['noarchive'] = true;
    return $robots;
}

/**
 * Deny every crawler path in the virtual robots.txt response.
 *
 * @param string $output Existing robots.txt output.
 * @param bool   $public Whether WordPress considers the site public.
 * @return string
 */
function twins_staging_safety_robots_txt($output, $public) {
    unset($output, $public);
    return "User-agent: *\nDisallow: /\n";
}

/**
 * Render the persistent admin warning.
 *
 * @return void
 */
function twins_staging_safety_admin_banner() {
    echo '<div class="notice notice-error" role="alert"><p><strong>STAGING</strong> — Email, cron, and all WordPress outbound HTTP are disabled.</p></div>';
}

/**
 * Render one fixed staging warning even when the theme omits wp_body_open().
 *
 * @return void
 */
function twins_staging_safety_frontend_banner() {
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    echo '<div id="twins-staging-banner" role="alert" style="position:fixed;left:0;right:0;top:0;z-index:2147483647;padding:8px 16px;background:#b91c1c;color:#fff;text-align:center;font:700 14px/1.3 sans-serif;letter-spacing:.08em;pointer-events:none">STAGING — NOT PRODUCTION</div>';
}

/**
 * Resolve known legacy links copied from production to verified staging paths.
 *
 * Every destination is a root-relative path on the current staging site. The
 * Madison prefix rule covers the former Wisconsin site path, while exceptional
 * and generated paths remain explicit so unrelated 404s are never hidden.
 *
 * @param string $path Request path without a query string.
 * @return string|null Root-relative destination or null when unmapped.
 */
function twins_staging_safety_legacy_redirect_path($path) {
    if (!is_string($path) || $path === '' || strpos($path, '/') !== 0) {
        return null;
    }

    $redirects = array(
        '/madison/hello-world/' => '/wi/garage-door-services/',
        '/emergency-services/' => '/wi/emergency-garage-services/',
        '/ky/location/lexington/garage-door-installation/' => '/ky/garage-door-installation/',
        '/wi/location/wi/' => '/wi/location/madison/',
        '/wi/maintenance-plans/' => '/wi/protection-plans/',
        '/ky/author/' => '/ky/blog/',
        '/wi/author/' => '/wi/blog/',
        '/ky/category/madison/page/2/' => '/ky/category/madison/',
        '/wi/category/broken-cable/page/3/' => '/wi/category/broken-cable/',
        '/wi/category/construction/page/3/' => '/wi/category/construction/',
        '/wi/category/garage-door-installation/page/2/' => '/wi/category/garage-door-installation/',
        '/wi/category/garage-door-installation/page/3/' => '/wi/category/garage-door-installation/',
        '/wi/category/replace-a-broken-cable/page/3/' => '/wi/category/replace-a-broken-cable/',
        '/wi/category/torsion-spring-conversion/page/3/' => '/wi/category/torsion-spring-conversion/',
        '/wi/category/torsion-spring/page/3/' => '/wi/category/torsion-spring/'
    );

    if (isset($redirects[$path])) {
        return $redirects[$path];
    }

    if (strpos($path, '/madison/') === 0) {
        $suffix = substr($path, strlen('/madison/'));
        if ($suffix !== '' && strpos($suffix, '..') === false && strpos($suffix, '//') === false) {
            return '/wi/' . $suffix;
        }
    }

    return null;
}

/**
 * Redirect a verified legacy staging path without preserving query parameters.
 *
 * @return void
 */
function twins_staging_safety_redirect_legacy_request() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = wp_parse_url($request_uri, PHP_URL_PATH);
    $destination = twins_staging_safety_legacy_redirect_path($path);

    if ($destination === null) {
        return;
    }

    wp_safe_redirect(network_home_url($destination), 302, 'Twins Staging Safety');
    exit;
}

/**
 * Replace the production review integration with a local staging-only notice.
 *
 * @param array|string $attributes Shortcode attributes.
 * @param string|null  $content Nested shortcode content.
 * @return string
 */
function twins_staging_safety_review_placeholder($attributes = array(), $content = null) {
    unset($attributes, $content);

    return '<div class="twins-staging-review-placeholder" role="note" style="padding:24px;border:1px solid #d1d5db;border-radius:12px;background:#f8fafc;color:#334155;text-align:center"><strong>Reviews are intentionally disabled on this private staging copy.</strong><br><span style="font-size:.9em">Production review data is not loaded or contacted here.</span></div>';
}

/**
 * Replace production catalog and door-builder integrations with a local notice.
 *
 * @param array|string $attributes Shortcode attributes.
 * @param string|null  $content Nested shortcode content.
 * @param string       $tag Shortcode tag.
 * @return string
 */
function twins_staging_safety_disabled_integration_placeholder($attributes = array(), $content = null, $tag = '') {
    unset($attributes, $content, $tag);

    return '<div class="twins-staging-integration-placeholder" role="note" style="padding:24px;border:1px solid #d1d5db;border-radius:12px;background:#f8fafc;color:#334155;text-align:center"><strong>Interactive product and door-builder integrations are intentionally disabled on this private staging copy.</strong><br><span style="font-size:.9em">No catalog service, booking system, form submission, or production integration is contacted here.</span></div>';
}

/**
 * Reassert staging placeholders after plugins and the theme register shortcodes.
 *
 * @return void
 */
function twins_staging_safety_register_placeholders() {
    remove_shortcode('brb_collection');
    add_shortcode('brb_collection', 'twins_staging_safety_review_placeholder');

    remove_shortcode('clopay_product');
    add_shortcode('clopay_product', 'twins_staging_safety_disabled_integration_placeholder');
    remove_shortcode('clopay_collection_grid');
    add_shortcode('clopay_collection_grid', 'twins_staging_safety_disabled_integration_placeholder');
    remove_shortcode('twins_door_builder');
    add_shortcode('twins_door_builder', 'twins_staging_safety_disabled_integration_placeholder');
}

/**
 * Short-circuit protected shortcodes even if another callback replaced ours.
 *
 * @param false|string $output Existing short-circuit output.
 * @param string       $tag Shortcode tag.
 * @param array        $attributes Parsed shortcode attributes.
 * @param array        $match Shortcode regular-expression match.
 * @return false|string
 */
function twins_staging_safety_prevent_production_shortcode($output, $tag, $attributes, $match) {
    unset($match);

    if ($tag === 'brb_collection') {
        return twins_staging_safety_review_placeholder($attributes, null);
    }

    if (in_array($tag, array('clopay_product', 'clopay_collection_grid', 'twins_door_builder'), true)) {
        return twins_staging_safety_disabled_integration_placeholder($attributes, null, $tag);
    }

    return $output;
}

/**
 * Return options that may contain production credentials or connection state.
 *
 * @return array
 */
function twins_staging_safety_quarantined_option_names() {
    return array(
        '_elementor_pro_api_requests_lock',
        '_elementor_pro_license_data',
        '_elementor_pro_license_data_fallback',
        '_elementor_pro_license_v2_data',
        '_elementor_pro_license_v2_data_fallback',
        '_temporary_login_site_token',
        'ai1wm_secret_key',
        'appsero_c6aa184e76ef48e61c74d4a212a611e3_manage_license',
        'brb_active',
        'brb_auth_code',
        'brb_google_api_key',
        'brb_google_places_api',
        'brb_license',
        'brb_license_expired',
        'brb_license_status',
        'brb_latest_version',
        'brb_last_error',
        'brb_notice_msg',
        'brb_renewal_date',
        'brb_renewal_status',
        'brb_yelp_api_key',
        'clickcease_api_key',
        'clickcease_bot_zapping_authenticated',
        'clickcease_client_id',
        'clickcease_domain_key',
        'clickcease_secret_key',
        'duplicator_pro_license_key',
        'elementor_allow_tracking',
        'elementor_connect_connect',
        'elementor_connect_library',
        'elementor_connect_product-feedback',
        'elementor_connect_site_key',
        'elementor_pro_license_key',
        'gf_last_telemetry_run',
        'gf_telemetry_data',
        'gform_version_info',
        'image_optimizer_access_token',
        'image_optimizer_client_id',
        'image_optimizer_client_secret',
        'image_optimizer_connect_data',
        'image_optimizer_refresh_token',
        'image_optimizer_token_id',
        'jetpack_active_plan',
        'jetpack_connection_active_plugins',
        'jetpack_licenses',
        'jetpack_options',
        'jetpack_persistent_blog_id',
        'jetpack_private_options',
        'jetpack_unique_connection',
        'jetpack_unique_registrations',
        'lead_connector_plugin_options',
        'metasync_telemetry_jwt_secret',
        'nitropack-webhookToken',
        'postmark_settings',
        'rank_math_analytics_all_services',
        'rank_math_analytics_permissions',
        'rank_math_google_analytic_profile',
        'rank_math_google_oauth_tokens',
        'rg_gforms_dataCollection',
        'rg_gforms_key',
        'siteground_data_token',
        'wordpress_api_key',
        'wpcode_usage_tracking_config',
        'wpil_2_license_data',
        'wpil_2_license_key',
        'wpil_ai_access_token'
    );
}

/**
 * Decide whether persisted quarantine state is absent or exactly empty.
 *
 * @param mixed $value Option value.
 * @return bool
 */
function twins_staging_safety_value_is_empty($value) {
    return $value === null
        || $value === false
        || $value === ''
        || $value === 0
        || $value === 0.0
        || $value === '0'
        || $value === array();
}

/**
 * Identify a registry field whose value can authenticate or license software.
 *
 * @param mixed $key Registry field name.
 * @return bool
 */
function twins_staging_safety_sensitive_registry_key($key) {
    if (!is_string($key)) {
        return false;
    }

    $normalized = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key);
    return preg_match('/(?:^|[^a-z0-9])(license|licence|purchase|token|key)(?:$|[^a-z0-9])/i', $normalized) === 1;
}

/**
 * Recursively reject nonempty credential fields in the Astra product registry.
 *
 * @param mixed $value Registry value or nested value.
 * @param int   $depth Current traversal depth.
 * @return bool
 */
function twins_staging_safety_registry_contains_sensitive_value($value, $depth = 0) {
    if ($depth > 20) {
        return true;
    }

    if (is_object($value)) {
        $value = get_object_vars($value);
    }
    if (!is_array($value)) {
        return false;
    }

    foreach ($value as $key => $nested_value) {
        if (twins_staging_safety_sensitive_registry_key($key)
            && !twins_staging_safety_value_is_empty($nested_value)) {
            return true;
        }
        if ((is_array($nested_value) || is_object($nested_value))
            && twins_staging_safety_registry_contains_sensitive_value($nested_value, $depth + 1)) {
            return true;
        }
    }

    return false;
}

/**
 * Keep a safe Astra registry update and reject a secret-bearing update.
 *
 * @param mixed $value New registry.
 * @param mixed $old_value Existing registry.
 * @return mixed
 */
function twins_staging_safety_filter_brainstrom_registry_update($value, $old_value) {
    if (twins_staging_safety_registry_contains_sensitive_value($value)) {
        return $old_value;
    }
    return $value;
}

/**
 * Keep a safe new Astra registry and empty a secret-bearing addition.
 *
 * @param mixed $value New registry.
 * @return mixed
 */
function twins_staging_safety_filter_new_brainstrom_registry($value) {
    if (twins_staging_safety_registry_contains_sensitive_value($value)) {
        return array();
    }
    return $value;
}

/**
 * Abort an ordinary option addition before WordPress writes unsafe state.
 *
 * WordPress exposes a pre-insert action, not a value filter, for add_option().
 * Throwing the fail-closed 503 here prevents the database insert and stops any
 * later add-option callbacks from observing a restored credential.
 *
 * @param string $option Option name.
 * @param mixed  $value Proposed option value.
 * @return void
 */
function twins_staging_safety_reject_unsafe_option_add($option, $value) {
    if (in_array($option, twins_staging_safety_quarantined_option_names(), true)
        && !twins_staging_safety_value_is_empty($value)) {
        twins_staging_safety_refuse_boot('A quarantined ordinary option addition was rejected.');
    }

    if ($option === 'brainstrom_products'
        && twins_staging_safety_registry_contains_sensitive_value($value)) {
        twins_staging_safety_refuse_boot('A credential-bearing Astra registry addition was rejected.');
    }
}

/**
 * Replace a new quarantined network option with an empty value before insert.
 *
 * @param mixed  $value Proposed network option value.
 * @param string $option Option name.
 * @param int    $network_id Network ID.
 * @return false
 */
function twins_staging_safety_filter_new_quarantined_network_option($value, $option, $network_id) {
    unset($value, $option, $network_id);
    return false;
}

/**
 * Refuse a request if restored credentials or connection state remain.
 *
 * Each multisite request checks its current blog and the current network.
 *
 * @return void
 */
function twins_staging_safety_assert_quarantine_empty() {
    foreach (twins_staging_safety_quarantined_option_names() as $option_name) {
        if (!twins_staging_safety_value_is_empty(get_option($option_name, null))) {
            twins_staging_safety_refuse_boot('A quarantined ordinary option is nonempty.');
        }
        if (is_multisite() && !twins_staging_safety_value_is_empty(get_site_option($option_name, null))) {
            twins_staging_safety_refuse_boot('A quarantined network option is nonempty.');
        }
    }

    $ordinary_registry = get_option('brainstrom_products', null);
    if (twins_staging_safety_registry_contains_sensitive_value($ordinary_registry)) {
        twins_staging_safety_refuse_boot('The ordinary Astra product registry contains credential state.');
    }
    if (is_multisite()) {
        $network_registry = get_site_option('brainstrom_products', null);
        if (twins_staging_safety_registry_contains_sensitive_value($network_registry)) {
            twins_staging_safety_refuse_boot('The network Astra product registry contains credential state.');
        }
    }
}

/**
 * Keep a quarantined option at its current empty or absent value.
 *
 * @param mixed  $value New option value.
 * @param mixed  $old_value Existing option value.
 * @param string $option Option name.
 * @return mixed
 */
function twins_staging_safety_keep_quarantined_option_empty($value, $old_value, $option, $network_id = null) {
    unset($value, $option, $network_id);
    return $old_value;
}

/**
 * Remove a quarantined option if a plugin adds it instead of updating it.
 *
 * @param string $option Added option name.
 * @param mixed  $value Added option value.
 * @return void
 */
function twins_staging_safety_delete_quarantined_option_after_add($option, $value) {
    unset($value);
    if (in_array($option, twins_staging_safety_quarantined_option_names(), true)) {
        delete_option($option);
    }
}

/**
 * Remove a quarantined network option immediately after it is added.
 *
 * @param string $option Added network option name.
 * @param mixed  $value Added option value.
 * @param int    $network_id Network ID.
 * @return void
 */
function twins_staging_safety_delete_quarantined_site_option_after_add($option, $value, $network_id) {
    unset($value);
    if (in_array($option, twins_staging_safety_quarantined_option_names(), true)) {
        delete_network_option($network_id, $option);
    }
}

/**
 * Hide every persisted cron event from the staging runtime.
 *
 * @return array
 */
function twins_staging_safety_empty_cron() {
    return array('version' => 2);
}

/**
 * Prevent new and recurring cron events from being scheduled.
 *
 * @param mixed  $pre Existing short-circuit value.
 * @param object $event Proposed cron event.
 * @param bool   $wp_error Whether the caller requested a WP_Error.
 * @return false|WP_Error
 */
function twins_staging_safety_block_cron_schedule($pre, $event, $wp_error = false) {
    unset($pre, $event);
    if ($wp_error) {
        return new WP_Error(
            'twins_staging_cron_disabled',
            'WordPress cron is disabled by Twins staging safety.'
        );
    }
    return false;
}

twins_staging_safety_assert_quarantine_empty();

add_filter('pre_wp_mail', 'twins_staging_safety_block_mail', PHP_INT_MAX, 2);
add_filter('pre_http_request', 'twins_staging_safety_filter_http', PHP_INT_MAX, 3);

add_action('muplugins_loaded', 'twins_staging_safety_send_headers', PHP_INT_MAX);
add_action('send_headers', 'twins_staging_safety_send_headers', PHP_INT_MAX);
add_action('admin_init', 'twins_staging_safety_send_headers', PHP_INT_MAX);
add_action('login_init', 'twins_staging_safety_send_headers', PHP_INT_MAX);
add_filter('wp_robots', 'twins_staging_safety_robots_meta', PHP_INT_MAX);
add_filter('robots_txt', 'twins_staging_safety_robots_txt', PHP_INT_MAX, 2);

add_action('admin_notices', 'twins_staging_safety_admin_banner', PHP_INT_MAX);
add_action('network_admin_notices', 'twins_staging_safety_admin_banner', PHP_INT_MAX);
add_action('wp_body_open', 'twins_staging_safety_frontend_banner', PHP_INT_MIN);
add_action('wp_footer', 'twins_staging_safety_frontend_banner', PHP_INT_MAX);
add_action('template_redirect', 'twins_staging_safety_redirect_legacy_request', PHP_INT_MIN);
twins_staging_safety_register_placeholders();
add_action('plugins_loaded', 'twins_staging_safety_register_placeholders', PHP_INT_MAX);
add_action('after_setup_theme', 'twins_staging_safety_register_placeholders', PHP_INT_MAX);
add_action('init', 'twins_staging_safety_register_placeholders', PHP_INT_MAX);
add_action('wp_loaded', 'twins_staging_safety_register_placeholders', PHP_INT_MAX);
add_filter('pre_do_shortcode_tag', 'twins_staging_safety_prevent_production_shortcode', PHP_INT_MAX, 4);

foreach (twins_staging_safety_quarantined_option_names() as $option_name) {
    add_filter('pre_update_option_' . $option_name, 'twins_staging_safety_keep_quarantined_option_empty', PHP_INT_MAX, 3);
    add_filter('pre_add_site_option_' . $option_name, 'twins_staging_safety_filter_new_quarantined_network_option', PHP_INT_MIN, 3);
    add_filter('pre_update_site_option_' . $option_name, 'twins_staging_safety_keep_quarantined_option_empty', PHP_INT_MAX, 4);
}
unset($option_name);
add_action('add_option', 'twins_staging_safety_reject_unsafe_option_add', PHP_INT_MIN, 2);
add_action('added_option', 'twins_staging_safety_delete_quarantined_option_after_add', PHP_INT_MAX, 2);
add_action('add_site_option', 'twins_staging_safety_delete_quarantined_site_option_after_add', PHP_INT_MAX, 3);

add_filter('pre_update_option_brainstrom_products', 'twins_staging_safety_filter_brainstrom_registry_update', PHP_INT_MAX, 3);
add_filter('pre_add_site_option_brainstrom_products', 'twins_staging_safety_filter_new_brainstrom_registry', PHP_INT_MAX, 3);
add_filter('pre_update_site_option_brainstrom_products', 'twins_staging_safety_filter_brainstrom_registry_update', PHP_INT_MAX, 4);

add_filter('pre_option_cron', 'twins_staging_safety_empty_cron', PHP_INT_MAX);
add_filter('pre_schedule_event', 'twins_staging_safety_block_cron_schedule', PHP_INT_MAX, 3);
add_filter('pre_schedule_single_event', 'twins_staging_safety_block_cron_schedule', PHP_INT_MAX, 3);
add_filter('pre_reschedule_event', 'twins_staging_safety_block_cron_schedule', PHP_INT_MAX, 3);
