<?php
/**
 * Semantic shared chrome for the private overhaul preview.
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Resolve a renderer context exclusively through the fixed region map.
 *
 * @param array $context Internal request context.
 * @return array
 */
function twins_overhaul_resolve_context(array $context): array {
    $regions = twins_overhaul_regions();
    $blog_id = (int) get_current_blog_id();
    if (!isset($regions[$blog_id])) {
        twins_staging_overhaul_refuse_boot('Current blog ID is not in the fixed preview region map.');
    }
    $region = $regions[$blog_id];

    $path = isset($context['path']) ? (string) $context['path'] : $region['base'];
    if ($path === '' || substr($path, 0, 1) !== '/' || strpos($path, '//') !== false || strpos($path, '..') !== false) {
        $path = $region['base'];
    }

    if ($region['key'] === 'wi' && preg_match('~(?:^|/)[^/]*milwaukee[^/]*(?:/|$)~i', $path)) {
        $region['phone'] = '(414) 800-9271';
        $region['tel'] = '+14148009271';
    }

    $region['contact'] = $region['base'] . 'contact-us/';
    return $region;
}

/**
 * Render a fixed list of navigation links.
 *
 * @param array $items Fixed navigation items.
 * @return string
 */
function twins_overhaul_render_link_list(array $items): string {
    $markup = '<ul class="twins-overhaul-nav__links">';
    foreach ($items as $item) {
        $markup .= '<li><a href="' . esc_url($item['path']) . '">' . esc_html($item['label']) . '</a></li>';
    }
    return $markup . '</ul>';
}

/**
 * Render the shared preview header.
 *
 * @param array $context Internal request context.
 * @return string
 */
function twins_overhaul_render_header(array $context): string {
    $classification = $context['classification'] ?? null;
    if (is_string($classification) && in_array($classification, array('home-brand', 'team-brand', 'careers-brand', 'reviews-brand', 'contact-brand'), true)) {
        return twins_overhaul_brand_runtime()->renderHeader($context);
    }

    $region = twins_overhaul_resolve_context($context);
    $logo = twins_overhaul_asset_url('logo');
    $skipTarget = function_exists('is_home') && is_home() ? '#content' : '#twins-overhaul-main';
    $isHome = isset($context['classification']) && (string) $context['classification'] === 'home';

    if ($isHome) {
        $markup = '<header class="twins-overhaul-header twins-overhaul-header--home" data-twins-overhaul-region="' . esc_attr($region['key']) . '">';
        $markup .= '<a class="twins-overhaul-skip-link" href="' . esc_attr($skipTarget) . '">Skip to main content</a>';
        $markup .= '<div class="twins-overhaul-header__inner">';
        $markup .= '<a class="twins-overhaul-logo" href="' . esc_url($region['base']) . '" aria-label="Twins Garage Doors home">';
        $markup .= '<img src="' . esc_url($logo) . '" alt="Twins Garage Doors" width="178" height="82">';
        $markup .= '</a>';
        $markup .= '<div class="twins-overhaul-header__actions twins-overhaul-header__actions--home">';
        $markup .= '<a class="twins-overhaul-header__phone twins-overhaul-header__phone--home" href="tel:' . esc_attr($region['tel']) . '"><span aria-hidden="true">&#9742;</span><span class="screen-reader-text">Call Twins Garage Doors at </span>' . esc_html($region['phone']) . '</a>';
        $markup .= '<a class="twins-overhaul-button twins-overhaul-header__estimate--home" href="' . esc_url($region['contact']) . '">Get an Estimate</a>';
        $markup .= '</div></div></header>';
        return $markup;
    }

    $navigation = twins_overhaul_navigation($region['key']);

    $markup = '<header class="twins-overhaul-header" data-twins-overhaul-region="' . esc_attr($region['key']) . '">';
    $markup .= '<a class="twins-overhaul-skip-link" href="' . esc_attr($skipTarget) . '">Skip to main content</a>';
    $markup .= '<div class="twins-overhaul-utility" aria-label="Service promise"><span>Local garage door help with clear next steps.</span></div>';
    $markup .= '<div class="twins-overhaul-header__inner">';
    $markup .= '<a class="twins-overhaul-logo" href="' . esc_url($region['base']) . '" aria-label="Twins Garage Doors home">';
    $markup .= '<img src="' . esc_url($logo) . '" alt="Twins Garage Doors" width="178" height="82">';
    $markup .= '</a>';
    $markup .= '<button class="twins-overhaul-menu-trigger" type="button" aria-controls="twins-overhaul-primary-nav" aria-expanded="false"><span aria-hidden="true">Menu</span><span class="screen-reader-text">Open primary navigation</span></button>';
    $markup .= '<nav class="twins-overhaul-nav" id="twins-overhaul-primary-nav" aria-label="Primary">';
    $markup .= '<button class="twins-overhaul-menu-close" type="button" data-twins-overhaul-menu-close>Close menu</button>';
    $markup .= '<ul class="twins-overhaul-nav__groups">';

    foreach ($navigation as $group => $items) {
        $markup .= '<li class="twins-overhaul-nav__group"><details><summary>' . esc_html($group) . '</summary>';
        $markup .= twins_overhaul_render_link_list($items);
        $markup .= '</details></li>';
    }

    $markup .= '</ul></nav>';
    $markup .= '<button class="twins-overhaul-menu-overlay" type="button" data-twins-overhaul-menu-close aria-label="Close menu"></button>';
    $markup .= '<div class="twins-overhaul-header__actions">';
    $markup .= '<a class="twins-overhaul-header__phone" href="tel:' . esc_attr($region['tel']) . '"><span class="screen-reader-text">Call Twins Garage Doors at </span>' . esc_html($region['phone']) . '</a>';
    $markup .= '<a class="twins-overhaul-button twins-overhaul-button--quote" href="' . esc_url($region['contact']) . '">Request Exact Quote</a>';
    $markup .= '</div></div></header>';

    return $markup;
}

/**
 * Render the shared preview footer and two-control mobile action bar.
 *
 * @param array $context Internal request context.
 * @return string
 */
function twins_overhaul_render_footer(array $context): string {
    $classification = $context['classification'] ?? null;
    if (is_string($classification) && in_array($classification, array('home-brand', 'team-brand', 'careers-brand', 'reviews-brand', 'contact-brand'), true)) {
        return twins_overhaul_brand_runtime()->renderFooter($context);
    }

    $region = twins_overhaul_resolve_context($context);
    $navigation = twins_overhaul_navigation($region['key']);
    $region_names = array(
        'main' => 'Twins Garage Doors',
        'wi' => 'Wisconsin',
        'ky' => 'Kentucky',
        'il' => 'Rockford-area Illinois',
    );
    $region_name = $region_names[$region['key']];

    $markup = '<footer class="twins-overhaul-footer">';
    $markup .= '<section class="twins-overhaul-footer__closing" aria-labelledby="twins-overhaul-footer-cta">';
    $markup .= '<h2 id="twins-overhaul-footer-cta">Ready for a clearer garage door plan?</h2>';
    $markup .= '<p>Tell the Twins team what is happening and review the next step without sending information from this private preview.</p>';
    $markup .= '<a class="twins-overhaul-button twins-overhaul-button--closing" href="' . esc_url($region['contact']) . '">Plan Your Service</a>';
    $markup .= '</section>';
    $markup .= '<div class="twins-overhaul-footer__grid">';
    $markup .= '<section class="twins-overhaul-footer__brand" aria-label="Twins Garage Doors"><img src="' . esc_url(twins_overhaul_asset_url('logo')) . '" alt="Twins Garage Doors" width="178" height="82"><p>Friendly, direct garage door guidance for homeowners and property teams.</p></section>';
    $markup .= '<section aria-labelledby="twins-overhaul-footer-services"><h2 id="twins-overhaul-footer-services">Services</h2>' . twins_overhaul_render_link_list($navigation['Services']) . '</section>';
    $markup .= '<section aria-labelledby="twins-overhaul-footer-resources"><h2 id="twins-overhaul-footer-resources">Resources</h2>' . twins_overhaul_render_link_list($navigation['Resources']) . '</section>';
    $markup .= '<section class="twins-overhaul-footer__contact" aria-labelledby="twins-overhaul-footer-contact"><h2 id="twins-overhaul-footer-contact">' . esc_html($region_name) . ' contact</h2>';
    $markup .= '<a href="tel:' . esc_attr($region['tel']) . '">' . esc_html($region['phone']) . '</a>';
    $markup .= '<a href="' . esc_url($region['contact']) . '">Contact Twins Garage Doors</a></section>';
    $markup .= '</div>';
    $markup .= '<div class="twins-overhaul-footer__legal"><span>&copy; Twins Garage Doors</span><a href="/privacy-policy/">Privacy</a><a href="/terms-conditions/">Terms</a><a href="/ada-standards/">Accessibility</a></div>';
    $markup .= '<nav class="twins-overhaul-mobile-actions" aria-label="Quick actions">';
    $markup .= '<a href="tel:' . esc_attr($region['tel']) . '">Call Now</a>';
    $markup .= '<a href="' . esc_url($region['contact']) . '">Get an Estimate</a>';
    $markup .= '</nav></footer>';

    return $markup;
}
