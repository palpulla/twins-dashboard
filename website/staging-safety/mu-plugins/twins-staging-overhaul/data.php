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
    return array(
        1 => array('key' => 'main', 'phone' => '(833) 833-2010', 'tel' => '+18338332010', 'base' => '/'),
        3 => array('key' => 'ky', 'phone' => '(859) 440-2227', 'tel' => '+18594402227', 'base' => '/ky/'),
        4 => array('key' => 'wi', 'phone' => '(608) 420-2377', 'tel' => '+16084202377', 'base' => '/wi/'),
        5 => array('key' => 'il', 'phone' => '(815) 800-2025', 'tel' => '+18158002025', 'base' => '/il/'),
    );
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
        'address' => null,
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
