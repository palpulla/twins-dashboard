<?php
/**
 * Fixed, bounded route classification for the private staging preview.
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Stop route handling when request identity is not fully provable.
 *
 * @param string $reason Fixed refusal reason.
 * @return void
 */
function twins_overhaul_refuse_route(string $reason): void {
    twins_staging_overhaul_refuse_boot('Route classification failed: ' . $reason);
}

/**
 * Prove a path is a bounded, root-relative path rather than a URL or payload.
 *
 * @param string $path Request path.
 * @return void
 */
function twins_overhaul_assert_safe_path(string $path): void {
    if ($path === '' || strlen($path) > 240) {
        twins_overhaul_refuse_route('path length is outside the fixed boundary.');
    }
    if (substr($path, 0, 1) !== '/' || substr($path, 0, 2) === '//') {
        twins_overhaul_refuse_route('path must be root-relative.');
    }
    if (strpos($path, '//') !== false || strpos($path, '?') !== false || strpos($path, '#') !== false) {
        twins_overhaul_refuse_route('path contains a separator, query, or fragment.');
    }
    if (strpos($path, '\\') !== false || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)) {
        twins_overhaul_refuse_route('path contains a dot segment.');
    }
    if (preg_match('~%(?:2f|5c)~i', $path)) {
        twins_overhaul_refuse_route('path contains an encoded slash (%2f or %5c).');
    }
    if (preg_match('/[\x00-\x20\x7f]/', $path)) {
        twins_overhaul_refuse_route('path contains control or whitespace bytes.');
    }
}

/**
 * Confirm that a route belongs to the fixed current multisite region.
 *
 * @param int    $blogId Fixed blog ID.
 * @param string $path Safe path.
 * @return bool
 */
function twins_overhaul_path_belongs_to_blog(int $blogId, string $path): bool {
    if ($blogId === 1) {
        return !preg_match('~^/(?:wi|ky|il)(?:/|$)~', $path);
    }

    $prefixes = array(3 => 'ky', 4 => 'wi', 5 => 'il');
    return isset($prefixes[$blogId]) && preg_match('~^/' . $prefixes[$blogId] . '(?:/|$)~', $path) === 1;
}

/**
 * Match one exact terminal page slug within the fixed current region.
 *
 * @param int    $blogId Fixed blog ID.
 * @param string $path Safe path.
 * @param string $slug Fixed allowlisted slug.
 * @return bool
 */
function twins_overhaul_matches_terminal_slug(int $blogId, string $path, string $slug): bool {
    $prefixes = array(1 => '', 3 => 'ky/', 4 => 'wi/', 5 => 'il/');
    if (!isset($prefixes[$blogId])) {
        return false;
    }

    $expected = '/' . $prefixes[$blogId] . $slug;
    return rtrim($path, '/') === $expected;
}

/**
 * Return the only supported route-family outcomes.
 *
 * @param string $classification Candidate outcome.
 * @return bool
 */
function twins_overhaul_is_known_classification(string $classification): bool {
    return in_array($classification, array(
        'campaign-preserve',
        'catalog-preserve',
        'legal-preserve',
        'home-brand',
        'team-brand',
        'careers-brand',
        'reviews-brand',
        'contact-brand',
        'service',
        'location',
        'trust',
        'article',
        'blog-index',
        'cost-madison',
        'cost-milwaukee',
        'builder',
    ), true);
}

/**
 * Classify one already-resolved WordPress request with fixed allowlists only.
 *
 * @param int    $blogId Current multisite blog ID.
 * @param string $path Current root-relative request path.
 * @param string $postType Queried WordPress post type.
 * @param int    $postId Queried WordPress post ID.
 * @return string
 */
function twins_overhaul_classify_request(int $blogId, string $path, string $postType, int $postId): string {
    $regions = twins_overhaul_regions();
    if (!isset($regions[$blogId])) {
        twins_overhaul_refuse_route('blog ID is outside the fixed region map.');
    }
    twins_overhaul_assert_safe_path($path);

    $postType = strtolower($postType);

    if ($blogId === 1) {
        if (in_array($postId, array(7092, 7093), true)) {
            return 'campaign-preserve';
        }
        if ($postId === 7341) {
            return 'careers-brand';
        }
        if ($postId === 6955) {
            return 'team-brand';
        }
        if (in_array($postId, array(
            7141,
            6034, 6065, 6090, 7137, 7172, 7179, 7186, 7196, 7203, 7210, 7218, 7225,
            7232, 7239, 7246, 7253, 7260, 7267, 7274, 7281, 7288, 7295, 7302,
        ), true)) {
            return 'catalog-preserve';
        }
        if (in_array($postId, array(2009, 4240, 2061, 2077, 2092), true)) {
            return 'legal-preserve';
        }
    }

    $legalTerminalPattern = '~^/(?:wi/|ky/|il/)?(?:privacy-policy|terms-conditions|ada-standards|thank-you)/?$~D';
    if (
        twins_overhaul_path_belongs_to_blog($blogId, $path)
        && (
            preg_match($legalTerminalPattern, $path)
            || ($blogId === 4 && rtrim($path, '/') === '/wi/thank-you-g-ppc-lp')
        )
    ) {
        return 'legal-preserve';
    }

    if ($blogId === 4 && rtrim($path, '/') === '/wi/garage-door-cost-in-madison-wi') {
        return 'cost-madison';
    }
    if ($blogId === 4 && rtrim($path, '/') === '/wi/garage-door-cost-in-milwaukee-wi') {
        return 'cost-milwaukee';
    }

    $builders = array(
        1 => array('/door-builder', '/design-your-door'),
        3 => array('/ky/design-your-door'),
        4 => array('/wi/door-builder'),
        5 => array('/il/door-builder'),
    );
    if (in_array(rtrim($path, '/'), $builders[$blogId], true)) {
        return 'builder';
    }

    $homes = array(1 => '', 3 => '/ky', 4 => '/wi', 5 => '/il');
    if (rtrim($path, '/') === $homes[$blogId]) {
        return 'home-brand';
    }

    if ($blogId === 1 && twins_overhaul_matches_terminal_slug($blogId, $path, 'our-team')) {
        return 'team-brand';
    }
    if ($blogId === 1 && twins_overhaul_matches_terminal_slug($blogId, $path, 'careers')) {
        return 'careers-brand';
    }

    if (twins_overhaul_matches_terminal_slug($blogId, $path, 'reviews')) {
        return 'reviews-brand';
    }
    if (twins_overhaul_matches_terminal_slug($blogId, $path, 'contact-us')) {
        return 'contact-brand';
    }

    if ($postType === 'location') {
        if (!twins_overhaul_path_belongs_to_blog($blogId, $path)) {
            twins_overhaul_refuse_route('location post type does not belong to the fixed current blog.');
        }
        return 'location';
    }

    if (twins_overhaul_path_belongs_to_blog($blogId, $path)) {
        if (preg_match('~^/(?:wi|ky|il)?/?location/[a-z0-9]+(?:-[a-z0-9]+){0,9}/?$~D', $path)) {
            return 'location';
        }

        $locationHubs = array(
            1 => '/locations',
            4 => '/wi/service-area',
            5 => '/il/locations',
        );
        if (isset($locationHubs[$blogId]) && rtrim($path, '/') === $locationHubs[$blogId]) {
            return 'location';
        }

        $cityServicePattern = '~^/(wi|ky|il)/(?=[a-z0-9-]{1,120}/?$)(?=[a-z0-9-]*garage-door)[a-z0-9]+(?:-[a-z0-9]+){2,18}-\\1/?$~D';
        if ($blogId !== 1 && preg_match($cityServicePattern, $path)) {
            return 'location';
        }
    }

    $trustSlugs = array('about-us', 'faqs', 'financing', 'coupons-offers');
    foreach ($trustSlugs as $slug) {
        if (twins_overhaul_matches_terminal_slug($blogId, $path, $slug)) {
            return 'trust';
        }
    }

    $serviceSlugs = array(
        'garage-door-services',
        'garage-door-repair',
        'garage-door-installation',
        'garage-door-cable-repair',
        'garage-door-opener-repair',
        'garage-door-openers',
        'garage-door-spring-repair',
        'garage-weatherstripping-repair',
        'garage-door-tune-up',
        'emergency-garage-services',
        'property-management-services',
        'maintenance-plans',
        'protection-plans',
    );
    foreach ($serviceSlugs as $slug) {
        if (twins_overhaul_matches_terminal_slug($blogId, $path, $slug)) {
            return 'service';
        }
    }

    if ($postType === 'post') {
        return 'article';
    }

    return 'article';
}

/**
 * Determine whether the final shared chrome belongs on a classified route.
 *
 * @param string $classification Fixed classifier outcome.
 * @return bool
 */
function twins_overhaul_should_render_chrome(string $classification): bool {
    return twins_overhaul_is_known_classification($classification) && $classification !== 'campaign-preserve';
}
