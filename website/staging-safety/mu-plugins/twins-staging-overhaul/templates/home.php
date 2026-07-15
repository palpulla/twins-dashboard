<?php
/** Dedicated regional homepage renderer for the private staging preview. */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Render the approved Twins homepage language with fixed regional routes.
 *
 * @param array  $context Proven request context.
 * @param string $content Original rendered WordPress content.
 * @return string
 */
function twins_overhaul_render_home_template(array $context, string $content): string {
    $regions = array(
        'main' => array(
            'name' => 'Twins Garage Doors',
            'headline' => 'Garage door repair and installation,',
            'lead' => 'Start with repair, opener help, a new garage door, or the right regional contact for your project.',
            'phone' => '(833) 833-2010',
            'tel' => '+18338332010',
            'services' => '/garage-door-services/',
            'repair' => '/garage-door-repair/',
            'installation' => '/garage-door-installation/',
            'openers' => '/garage-door-opener-repair/',
            'builder' => '/door-builder/',
            'areas' => '/locations/',
            'contact' => '/contact-us/',
        ),
        'wi' => array(
            'name' => 'Twins Garage Doors Wisconsin',
            'headline' => 'Wisconsin garage door repair and installation,',
            'lead' => 'Explore garage door repair, opener help, installation, and planning resources for Wisconsin properties.',
            'phone' => '(608) 420-2377',
            'tel' => '+16084202377',
            'services' => '/wi/garage-door-services/',
            'repair' => '/wi/garage-door-repair/',
            'installation' => '/wi/garage-door-installation/',
            'openers' => '/wi/garage-door-opener-repair/',
            'builder' => '/wi/door-builder/',
            'areas' => '/wi/service-area/',
            'contact' => '/wi/contact-us/',
        ),
        'ky' => array(
            'name' => 'Twins Garage Doors Kentucky',
            'headline' => 'Kentucky garage door repair and installation,',
            'lead' => 'Explore garage door repair, opener help, installation, and planning resources for Kentucky properties.',
            'phone' => '(833) 833-2010',
            'tel' => '+18338332010',
            'services' => '/ky/garage-door-services/',
            'repair' => '/ky/garage-door-services/',
            'installation' => '/ky/garage-door-installation/',
            'openers' => '/ky/garage-door-opener-repair/',
            'builder' => '/ky/design-your-door/',
            'areas' => '/ky/location/lexington/',
            'contact' => '/ky/contact-us/',
        ),
        'il' => array(
            'name' => 'Twins Garage Doors Illinois',
            'headline' => 'Rockford-area garage door service,',
            'lead' => 'Explore garage door repair, opener help, installation, and planning resources for Rockford-area properties.',
            'phone' => '(815) 800-2025',
            'tel' => '+18158002025',
            'services' => '/il/garage-door-services/',
            'repair' => '/il/garage-door-repair/',
            'installation' => '/il/garage-door-installation/',
            'openers' => '/il/garage-door-openers/',
            'builder' => '/il/door-builder/',
            'areas' => '/il/locations/',
            'contact' => '/il/contact-us/',
        ),
    );

    $regionKey = (string) ($context['key'] ?? '');
    if (!isset($regions[$regionKey])) {
        twins_overhaul_refuse_route('homepage region is outside the fixed map.');
    }
    $region = $regions[$regionKey];

    $publishedContent = twins_overhaul_prepare_family_content($content);
    $twinLeft = twins_overhaul_asset_url('twin-left');
    $twinRight = twins_overhaul_asset_url('twin-right');
    $truckWebp = twins_overhaul_asset_url('truck-webp');
    $truckPng = twins_overhaul_asset_url('truck-png');

    $markup = '<div id="twins-overhaul-main" class="twins-overhaul-main twins-home" tabindex="-1">';

    $markup .= '<section class="twins-overhaul-section twins-home-hero"><div class="twins-overhaul-shell twins-home-hero__grid">';
    $markup .= '<div class="twins-home-hero__copy">';
    $markup .= '<p class="twins-home-hero__sticker">Garage doors, done right</p>';
    $markup .= '<h1 class="twins-home-hero__title">' . esc_html($region['headline']) . ' <span>done with Twins.</span></h1>';
    $markup .= '<div class="twins-home-hero__mobile-mascots" aria-hidden="true">';
    $markup .= '<img src="' . esc_url($twinLeft) . '" width="196" height="534" alt="">';
    $markup .= '<img src="' . esc_url($twinRight) . '" width="297" height="538" alt="">';
    $markup .= '</div>';
    $markup .= '<p class="twins-home-hero__lead">' . esc_html($region['lead']) . '</p>';
    $markup .= '<div class="twins-home-hero__actions">';
    $markup .= '<a class="twins-overhaul-button twins-overhaul-button--primary" href="tel:' . esc_attr($region['tel']) . '">Call Twins <span>' . esc_html($region['phone']) . '</span></a>';
    $markup .= '<a class="twins-overhaul-button twins-overhaul-button--secondary twins-home-hero__secondary" href="' . esc_url($region['services']) . '">Explore services</a>';
    $markup .= '</div>';
    $markup .= '<ul class="twins-home-hero__proof" aria-label="Ways Twins can help"><li>Garage door repair</li><li>New garage doors</li><li>Opener help</li></ul>';
    $markup .= '</div>';
    $markup .= '<div class="twins-home-hero__art" aria-label="The Twins Garage Doors team">';
    $markup .= '<div class="twins-home-hero__mascots">';
    $markup .= '<img class="twins-home-hero__mascot twins-home-hero__mascot--left" src="' . esc_url($twinLeft) . '" width="196" height="534" alt="" aria-hidden="true">';
    $markup .= '<img class="twins-home-hero__mascot twins-home-hero__mascot--right" src="' . esc_url($twinRight) . '" width="297" height="538" alt="" aria-hidden="true">';
    $markup .= '</div></div></div></section>';

    $markup .= '<section class="twins-home-benefits" aria-label="Homepage guide"><div class="twins-overhaul-shell">';
    $markup .= '<ul class="twins-home-benefits__list">';
    $markup .= '<li><strong>Repair and opener paths</strong><span>Find the service page that matches the issue you are researching.</span></li>';
    $markup .= '<li><strong>Door styles and planning</strong><span>Explore new-door collections and the private door-planning preview.</span></li>';
    $markup .= '<li><strong>Regional contact routes</strong><span>Use the fixed phone and location links for ' . esc_html($region['name']) . '.</span></li>';
    $markup .= '</ul></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-home-services"><div class="twins-overhaul-shell">';
    $markup .= '<header class="twins-home-services__header"><div><p class="twins-overhaul-eyebrow">What can we help with?</p><h2>Start with the part of your garage door project that matters now.</h2></div>';
    $markup .= '<a href="' . esc_url($region['services']) . '">View all services</a></header>';
    $markup .= '<div class="twins-home-services__grid">';
    $markup .= '<a class="twins-home-services__card twins-home-service-card" href="' . esc_url($region['repair']) . '"><span class="twins-home-services__icon" aria-hidden="true">01</span><h3>Garage Door Repair</h3><p>Review repair information for doors, hardware, springs, and related concerns.</p><span>Explore repair</span></a>';
    $markup .= '<a class="twins-home-services__card twins-home-service-card" href="' . esc_url($region['installation']) . '"><span class="twins-home-services__icon" aria-hidden="true">02</span><h3>Garage Door Installation</h3><p>Learn about the installation path when a replacement or new door is the project.</p><span>Explore installation</span></a>';
    $markup .= '<a class="twins-home-services__card twins-home-service-card" href="' . esc_url($region['openers']) . '"><span class="twins-home-services__icon" aria-hidden="true">03</span><h3>Garage Door Openers</h3><p>Find the regional page for opener repair, replacement, and product questions.</p><span>Explore openers</span></a>';
    $markup .= '<a class="twins-home-services__card twins-home-service-card" href="' . esc_url($region['builder']) . '"><span class="twins-home-services__icon" aria-hidden="true">04</span><h3>Design Your Door</h3><p>Use the private visual planner to compare door styles before a conversation.</p><span>Open the builder</span></a>';
    $markup .= '</div></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-home-published twins-home-original"><div class="twins-overhaul-shell">';
    $markup .= '<details class="twins-home-published__disclosure"><summary>More about Twins Garage Doors</summary>';
    $markup .= '<div class="twins-overhaul-original-content twins-home-published__content" data-twins-original-content>' . $publishedContent . '</div>';
    $markup .= '</details></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-home-fleet"><div class="twins-overhaul-shell twins-home-fleet__grid">';
    $markup .= '<div class="twins-home-fleet__copy"><p class="twins-overhaul-eyebrow">The real Twins fleet</p><h2>Look for the yellow-and-navy Twins truck.</h2>';
    $markup .= '<p>The truck shown here is part of the Twins Garage Doors service fleet. It brings the same recognizable Twins identity from the road onto this private preview.</p>';
    $markup .= '<a class="twins-overhaul-button twins-overhaul-button--secondary" href="' . esc_url($region['contact']) . '">Review contact options</a></div>';
    $markup .= '<div class="twins-home-fleet__media"><picture><source srcset="' . esc_url($truckWebp) . '" type="image/webp">';
    $markup .= '<img src="' . esc_url($truckPng) . '" width="1398" height="821" alt="Yellow-and-navy Twins Garage Doors service truck"></picture></div>';
    $markup .= '</div></section>';

    $markup .= '<section class="twins-overhaul-section twins-home-builder"><div class="twins-overhaul-shell twins-home-builder__grid">';
    $markup .= '<div class="twins-home-builder__copy"><p class="twins-overhaul-eyebrow">Plan a new garage door</p><h2>Compare a direction before discussing the details.</h2>';
    $markup .= '<p>The private door-builder preview helps organize style choices. It does not submit or store project information.</p>';
    $markup .= '<div class="twins-home-builder__actions"><a class="twins-overhaul-button twins-overhaul-button--primary" href="' . esc_url($region['builder']) . '">Open the door builder</a>';
    $markup .= '<a href="/clopay-garage-doors/">Browse door collections</a></div></div>';
    $markup .= '<div class="twins-home-builder__preview" aria-hidden="true"><div class="twins-home-builder__door"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></div>';
    $markup .= '</div></section>';

    $markup .= '<section class="twins-overhaul-section twins-home-service-area"><div class="twins-overhaul-shell twins-home-service-area__grid">';
    $markup .= '<div class="twins-home-service-area__copy"><p class="twins-overhaul-eyebrow">Your regional starting point</p><h2>' . esc_html($region['name']) . '</h2>';
    $markup .= '<p>Review the fixed service-area route for this regional site, or use the fixed contact path when you are ready to continue.</p></div>';
    $markup .= '<div class="twins-home-service-area__links">';
    $markup .= '<a href="' . esc_url($region['areas']) . '"><strong>Service areas</strong><span>Browse regional location pages</span></a>';
    $markup .= '<a href="' . esc_url($region['contact']) . '"><strong>Contact Twins</strong><span>Review the regional contact page</span></a>';
    $markup .= '<a href="/careers/"><strong>Careers</strong><span>See the rebuilt employment page</span></a>';
    $markup .= '<a href="tel:' . esc_attr($region['tel']) . '"><strong>' . esc_html($region['phone']) . '</strong><span>Call the fixed regional number</span></a>';
    $markup .= '</div></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-home-closing"><div class="twins-overhaul-shell twins-home-closing__panel">';
    $markup .= '<div><p class="twins-overhaul-eyebrow">Ready for the next step?</p><h2>Choose a service, explore a door, or talk with Twins.</h2>';
    $markup .= '<p>This private staging preview cannot submit a service request. Its regional links and phone number give you a clear route when you choose to continue.</p>';
    $markup .= '<div class="twins-home-hero__actions"><a class="twins-overhaul-button twins-overhaul-button--primary" href="tel:' . esc_attr($region['tel']) . '">Call ' . esc_html($region['phone']) . '</a>';
    $markup .= '<a class="twins-overhaul-button twins-overhaul-button--secondary twins-home-hero__secondary" href="' . esc_url($region['services']) . '">Explore services</a></div></div>';
    $markup .= '<div class="twins-home-closing__art" aria-hidden="true"><img src="' . esc_url($twinLeft) . '" width="196" height="534" alt=""><img src="' . esc_url($twinRight) . '" width="297" height="538" alt=""></div>';
    $markup .= '</div></section></div>';

    return $markup;
}
