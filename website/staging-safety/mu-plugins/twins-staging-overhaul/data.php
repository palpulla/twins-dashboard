<?php
/**
 * Fixed regional, navigation, and Illinois manifests for the private preview.
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Return the immutable multisite-to-region map.
 *
 * @return array<int,array>
 */
function twins_overhaul_regions(): array {
    $madison = array('street' => '2921 Landmark Pl #206', 'locality' => 'Madison', 'region' => 'WI', 'postalCode' => '53713');
    $rockford = array('street' => '5758 Elaine Dr Ste 110', 'locality' => 'Rockford', 'region' => 'IL', 'postalCode' => '61108');
    $mtSterling = array('street' => '3651 Aarons Run Rd', 'locality' => 'Mt Sterling', 'region' => 'KY', 'postalCode' => '40353');
    return array(
        1 => array('key' => 'main', 'phone' => '(833) 833-2010', 'tel' => '+18338332010', 'base' => '/', 'address' => $madison),
        3 => array('key' => 'ky', 'phone' => '(859) 440-2227', 'tel' => '+18594402227', 'base' => '/ky/', 'address' => $mtSterling),
        4 => array('key' => 'wi', 'phone' => '(608) 420-2377', 'tel' => '+16084202377', 'base' => '/wi/', 'address' => $madison),
        5 => array('key' => 'il', 'phone' => '(815) 800-2025', 'tel' => '+18158002025', 'base' => '/il/', 'address' => $rockford),
    );
}

/**
 * Metro layer for the state -> metro -> city structure.
 *
 * A market (state) can hold more than one metro, and each metro carries its own
 * real NAP. Wisconsin has two: Madison and Milwaukee. A city page shows the phone
 * and address of the metro that serves it, not a single market-wide address.
 *
 * Addresses and phones confirmed by the owner 2026-07-20. 'cities' lists the
 * satellite city slugs served from that metro; the hub city itself is the key.
 * Empty 'cities' means the satellite list is not yet confirmed for that metro.
 *
 * @return array<string,array<string,mixed>> Fixed metro definitions.
 */
function twins_overhaul_metros(): array {
    $madison = array('street' => '2921 Landmark Pl #206', 'locality' => 'Madison', 'region' => 'WI', 'postalCode' => '53713');
    $wauwatosa = array('street' => '11220 W Burleigh St Ste 100', 'locality' => 'Wauwatosa', 'region' => 'WI', 'postalCode' => '53222');
    $rockford = array('street' => '5758 Elaine Dr Ste 110', 'locality' => 'Rockford', 'region' => 'IL', 'postalCode' => '61108');
    $mtSterling = array('street' => '3651 Aarons Run Rd', 'locality' => 'Mt Sterling', 'region' => 'KY', 'postalCode' => '40353');

    return array(
        'madison' => array(
            'label' => 'Madison',
            'market' => 'wi',
            'phone' => '(608) 420-2377',
            'tel' => '+16084202377',
            'address' => $madison,
            'cities' => array(
                'verona', 'fitchburg', 'sun-prairie', 'middleton', 'deforest', 'oregon',
                'waunakee', 'janesville', 'mcfarland', 'monona', 'cottage-grove',
                'pardeeville', 'baraboo', 'deerfield', 'stoughton', 'portage',
                'belleville', 'reedsburg', 'mount-horeb', 'watertown', 'edgerton',
                'evansville', 'monroe', 'cambridge', 'rio', 'cross-plains', 'beloit',
                'brooklyn', 'marshall', 'columbus', 'fall-river', 'lodi', 'milton',
                'new-glarus', 'barneveld', 'windsor', 'sauk-city', 'fort-atkinson',
                'prairie-du-sac',
            ),
        ),
        'milwaukee' => array(
            'label' => 'Milwaukee',
            'market' => 'wi',
            'phone' => '(414) 800-9271',
            'tel' => '+14148009271',
            'address' => $wauwatosa,
            'cities' => array(
                'wauwatosa', 'waukesha', 'brookfield', 'new-berlin', 'greenfield', 'oak-creek',
            ),
        ),
        'rockford' => array(
            'label' => 'Rockford',
            'market' => 'il',
            'phone' => '(815) 800-2025',
            'tel' => '+18158002025',
            'address' => $rockford,
            'cities' => array(
                'loves-park', 'machesney-park', 'belvidere', 'roscoe', 'rockton',
                'cherry-valley', 'poplar-grove', 'south-beloit', 'winnebago',
                'byron', 'caledonia',
            ),
        ),
        'lexington' => array(
            'label' => 'Lexington',
            'market' => 'ky',
            'phone' => '(859) 440-2227',
            'tel' => '+18594402227',
            'address' => $mtSterling,
            'cities' => array(),
        ),
    );
}

/**
 * Resolve the metro that serves a city slug.
 *
 * @param string $citySlug Fixed lowercase city slug.
 * @return array{key:string,metro:array<string,mixed>}|null Null when unknown.
 */
function twins_overhaul_metro_for_city(string $citySlug, string $marketKey = '') {
    foreach (twins_overhaul_metros() as $key => $metro) {
        // A city slug alone is ambiguous across states. Caledonia and Beloit
        // exist in both Wisconsin and Illinois, so a metro only matches when
        // its market matches the market the page is actually served from.
        if ($marketKey !== '' && $metro['market'] !== $marketKey) {
            continue;
        }
        if ($citySlug === $key || in_array($citySlug, $metro['cities'], true)) {
            return array('key' => $key, 'metro' => $metro);
        }
    }
    return null;
}

function twins_overhaul_market_from_path(string $path): string {
    $segments = array_values(array_filter(explode('/', $path), 'strlen'));
    if (count($segments) === 0) {
        return '';
    }
    $first = strtolower((string) $segments[0]);
    return in_array($first, array('wi', 'il', 'ky'), true) ? $first : '';
}

function twins_overhaul_metro_address_line(array $address): string {
    return $address['street'] . ', ' . $address['locality'] . ', '
        . $address['region'] . ' ' . $address['postalCode'];
}

function twins_overhaul_city_slug_from_path(string $path): string {
    $segments = array_values(array_filter(explode('/', $path), 'strlen'));
    if (count($segments) === 0) {
        return '';
    }
    $slug = strtolower((string) end($segments));
    return preg_match('~^[a-z0-9-]+$~', $slug) === 1 ? $slug : '';
}

function twins_overhaul_apply_metro_context(array $context): array {
    $path = isset($context['path']) && is_string($context['path']) ? $context['path'] : '';
    $slug = twins_overhaul_city_slug_from_path($path);
    if ($slug === '') {
        return $context;
    }
    // The market comes from the served path, so a city page can never inherit a
    // metro from another state even when the two states share a city name.
    $marketKey = twins_overhaul_market_from_path($path);
    if ($marketKey === '') {
        return $context;
    }
    $found = twins_overhaul_metro_for_city($slug, $marketKey);
    if ($found === null) {
        return $context;
    }
    $metro = $found['metro'];
    // Contact context requires the display and href pair to be set together or
    // not at all, so both are assigned in the same branch.
    $context['phone'] = $metro['phone'];
    $context['tel'] = $metro['tel'];
    $context['metroAddress'] = twins_overhaul_metro_address_line($metro['address']);
    $context['metroKey'] = $found['key'];
    return $context;
}

/**
 * Create one fixed navigation item.
 *
 * @param string $label Visible label.
 * @param string $path Root-relative destination.
 * @return array{label:string,path:string}
 */
function twins_overhaul_navigation_item(string $label, string $path): array {
    return array('label' => $label, 'path' => $path);
}

/**
 * Return the approved regional navigation with exactly five groups.
 *
 * @param string $region Fixed region key.
 * @return array<string,array>
 */
function twins_overhaul_navigation(string $region): array {
    $region = in_array($region, array('main', 'wi', 'ky', 'il'), true) ? $region : 'main';

    $services = array(
        'main' => array(
            twins_overhaul_navigation_item('All Services', '/garage-door-services/'),
            twins_overhaul_navigation_item('Garage Door Installation', '/garage-door-installation/'),
            twins_overhaul_navigation_item('Spring Repair', '/garage-door-spring-repair/'),
            twins_overhaul_navigation_item('Opener Repair', '/garage-door-opener-repair/'),
            twins_overhaul_navigation_item('Emergency Service', '/emergency-garage-services/'),
        ),
        'wi' => array(
            twins_overhaul_navigation_item('All Wisconsin Services', '/wi/garage-door-services/'),
            twins_overhaul_navigation_item('Garage Door Installation', '/wi/garage-door-installation/'),
            twins_overhaul_navigation_item('Spring Repair', '/wi/garage-door-spring-repair/'),
            twins_overhaul_navigation_item('Opener Repair', '/wi/garage-door-opener-repair/'),
            twins_overhaul_navigation_item('Emergency Service', '/wi/emergency-garage-services/'),
        ),
        'ky' => array(
            twins_overhaul_navigation_item('All Kentucky Services', '/ky/garage-door-services/'),
            twins_overhaul_navigation_item('Garage Door Installation', '/ky/garage-door-installation/'),
            twins_overhaul_navigation_item('Spring Repair', '/ky/garage-door-spring-repair/'),
            twins_overhaul_navigation_item('Opener Repair', '/ky/garage-door-opener-repair/'),
            twins_overhaul_navigation_item('Emergency Service', '/ky/emergency-garage-services/'),
        ),
        'il' => array(
            twins_overhaul_navigation_item('All Illinois Services', '/il/garage-door-services/'),
            twins_overhaul_navigation_item('Garage Door Repair', '/il/garage-door-repair/'),
            twins_overhaul_navigation_item('Garage Door Installation', '/il/garage-door-installation/'),
            twins_overhaul_navigation_item('Garage Door Openers', '/il/garage-door-openers/'),
            twins_overhaul_navigation_item('Emergency Service', '/il/emergency-garage-services/'),
        ),
    );

    $garage_doors = array(
        'main' => array(
            twins_overhaul_navigation_item('Garage Door Collections', '/clopay-garage-doors/'),
            twins_overhaul_navigation_item('Classic Collection', '/clopay-classic-collection/'),
            twins_overhaul_navigation_item('Modern Steel', '/clopay-modern-steel/'),
            twins_overhaul_navigation_item('Gallery Steel', '/clopay-gallery-steel/'),
            twins_overhaul_navigation_item('Design Your Door', '/door-builder/'),
        ),
        'wi' => array(
            twins_overhaul_navigation_item('Garage Door Collections', '/clopay-garage-doors/'),
            twins_overhaul_navigation_item('Design Your Door', '/wi/door-builder/'),
            twins_overhaul_navigation_item('Garage Door Openers', '/wi/garage-door-openers/'),
        ),
        'ky' => array(
            twins_overhaul_navigation_item('Garage Door Collections', '/clopay-garage-doors/'),
            twins_overhaul_navigation_item('Design Your Door', '/ky/design-your-door/'),
            twins_overhaul_navigation_item('Garage Door Openers', '/ky/garage-door-openers/'),
        ),
        'il' => array(
            twins_overhaul_navigation_item('Garage Door Collections', '/clopay-garage-doors/'),
            twins_overhaul_navigation_item('Design Your Door', '/il/door-builder/'),
            twins_overhaul_navigation_item('Garage Door Openers', '/il/garage-door-openers/'),
        ),
    );

    $service_areas = array(
        'main' => array(
            twins_overhaul_navigation_item('All Service Areas', '/locations/'),
            twins_overhaul_navigation_item('Wisconsin', '/wi/'),
            twins_overhaul_navigation_item('Illinois', '/il/'),
            twins_overhaul_navigation_item('Kentucky', '/ky/'),
        ),
        'wi' => array(
            twins_overhaul_navigation_item('Wisconsin Service Areas', '/wi/service-area/'),
            twins_overhaul_navigation_item('Madison', '/wi/location/madison/'),
            twins_overhaul_navigation_item('Milwaukee', '/wi/garage-door-repair-in-milwaukee-wi/'),
            twins_overhaul_navigation_item('Janesville', '/wi/location/janesville/'),
            twins_overhaul_navigation_item('Middleton', '/wi/location/middleton/'),
            twins_overhaul_navigation_item('Sun Prairie', '/wi/location/sun-prairie/'),
            twins_overhaul_navigation_item('Verona', '/wi/location/verona/'),
        ),
        'ky' => array(
            twins_overhaul_navigation_item('Lexington', '/ky/location/lexington/'),
        ),
        'il' => array(
            twins_overhaul_navigation_item('Illinois Service Areas', '/il/locations/'),
            twins_overhaul_navigation_item('Rockford', '/il/location/rockford/'),
            twins_overhaul_navigation_item('Loves Park', '/il/location/loves-park/'),
            twins_overhaul_navigation_item('Machesney Park', '/il/location/machesney-park/'),
            twins_overhaul_navigation_item('Belvidere', '/il/location/belvidere/'),
            twins_overhaul_navigation_item('Roscoe', '/il/location/roscoe/'),
            twins_overhaul_navigation_item('Rockton', '/il/location/rockton/'),
            twins_overhaul_navigation_item('Cherry Valley', '/il/location/cherry-valley/'),
            twins_overhaul_navigation_item('Poplar Grove', '/il/location/poplar-grove/'),
            twins_overhaul_navigation_item('South Beloit', '/il/location/south-beloit/'),
            twins_overhaul_navigation_item('Winnebago', '/il/location/winnebago/'),
            twins_overhaul_navigation_item('Byron', '/il/location/byron/'),
            twins_overhaul_navigation_item('Caledonia', '/il/location/caledonia/'),
        ),
    );

    $resources = array(
        'main' => array(
            twins_overhaul_navigation_item('Wisconsin Garage Door Cost Guide', '/wi/garage-door-cost-in-madison-wi/'),
            twins_overhaul_navigation_item('Financing', '/financing/'),
            twins_overhaul_navigation_item('Offers', '/coupons-offers/'),
            twins_overhaul_navigation_item('Frequently Asked Questions', '/faqs/'),
            twins_overhaul_navigation_item('Blog', '/blog/'),
        ),
        'wi' => array(
            twins_overhaul_navigation_item('Garage Door Cost Guide', '/wi/garage-door-cost-in-madison-wi/'),
            twins_overhaul_navigation_item('Financing', '/wi/financing/'),
            twins_overhaul_navigation_item('Offers', '/wi/coupons-offers/'),
            twins_overhaul_navigation_item('Frequently Asked Questions', '/wi/faqs/'),
            twins_overhaul_navigation_item('Blog', '/wi/blog/'),
        ),
        'ky' => array(
            twins_overhaul_navigation_item('Wisconsin Garage Door Cost Guide', '/wi/garage-door-cost-in-madison-wi/'),
            twins_overhaul_navigation_item('Financing', '/ky/financing/'),
            twins_overhaul_navigation_item('Offers', '/ky/coupons-offers/'),
            twins_overhaul_navigation_item('Frequently Asked Questions', '/ky/faqs/'),
            twins_overhaul_navigation_item('Blog', '/ky/blog/'),
        ),
        'il' => array(
            twins_overhaul_navigation_item('Wisconsin Garage Door Cost Guide', '/wi/garage-door-cost-in-madison-wi/'),
            twins_overhaul_navigation_item('Financing', '/financing/'),
            twins_overhaul_navigation_item('Offers', '/coupons-offers/'),
            twins_overhaul_navigation_item('Frequently Asked Questions', '/faqs/'),
            twins_overhaul_navigation_item('Blog', '/blog/'),
        ),
    );
    $about = array(
        'main' => array(
            twins_overhaul_navigation_item('About Twins', '/about-us/'),
            twins_overhaul_navigation_item('Our Team', '/our-team/'),
            twins_overhaul_navigation_item('Careers', '/careers/'),
            twins_overhaul_navigation_item('Reviews', '/reviews/'),
            twins_overhaul_navigation_item('Contact', '/contact-us/'),
        ),
        'wi' => array(
            twins_overhaul_navigation_item('About Twins', '/wi/about-us/'),
            twins_overhaul_navigation_item('Our Team', '/our-team/'),
            twins_overhaul_navigation_item('Careers', '/careers/'),
            twins_overhaul_navigation_item('Reviews', '/wi/reviews/'),
            twins_overhaul_navigation_item('Contact', '/wi/contact-us/'),
        ),
        'ky' => array(
            twins_overhaul_navigation_item('About Twins', '/ky/about-us/'),
            twins_overhaul_navigation_item('Our Team', '/our-team/'),
            twins_overhaul_navigation_item('Careers', '/careers/'),
            twins_overhaul_navigation_item('Reviews', '/ky/reviews/'),
            twins_overhaul_navigation_item('Contact', '/ky/contact-us/'),
        ),
        'il' => array(
            twins_overhaul_navigation_item('About Twins', '/about-us/'),
            twins_overhaul_navigation_item('Our Team', '/our-team/'),
            twins_overhaul_navigation_item('Careers', '/careers/'),
            twins_overhaul_navigation_item('Reviews', '/reviews/'),
            twins_overhaul_navigation_item('Contact', '/il/contact-us/'),
        ),
    );

    return array(
        'Services' => $services[$region],
        'Garage Doors' => $garage_doors[$region],
        'Service Areas' => $service_areas[$region],
        'Resources' => $resources[$region],
        'About' => $about[$region],
    );
}

/**
 * Return the fixed private Illinois staging structure.
 *
 * @return array
 */
function twins_overhaul_illinois_manifest(): array {
    return array(
        'key' => 'il',
        'base' => '/il/',
        'phone' => '(815) 800-2025',
        'tel' => '+18158002025',
        'address' => array('street' => '5758 Elaine Dr Ste 110', 'locality' => 'Rockford', 'region' => 'IL', 'postalCode' => '61108'),
        'public' => 0,
        'core' => array(
            array('slug' => '', 'title' => 'Garage Door Service in Rockford, Illinois'),
            array('slug' => 'garage-door-services', 'title' => 'Garage Door Services'),
            array('slug' => 'garage-door-repair', 'title' => 'Garage Door Repair'),
            array('slug' => 'garage-door-installation', 'title' => 'Garage Door Installation'),
            array('slug' => 'garage-door-openers', 'title' => 'Garage Door Openers'),
            array('slug' => 'emergency-garage-services', 'title' => 'Emergency Garage Door Service'),
            array('slug' => 'locations', 'title' => 'Illinois Service Areas'),
            array('slug' => 'contact-us', 'title' => 'Contact Twins Garage Doors'),
            array('slug' => 'door-builder', 'title' => 'Design Your Garage Door'),
        ),
        'cities' => array(
            'rockford',
            'loves-park',
            'machesney-park',
            'belvidere',
            'roscoe',
            'rockton',
            'cherry-valley',
            'poplar-grove',
            'south-beloit',
            'winnebago',
            'byron',
            'caledonia',
        ),
    );
}
