<?php
declare(strict_types=1);

return [
    'main' => [
        'label' => 'Twins Garage Doors',
        'phoneDisplay' => '(833) 833-2010',
        'phoneHref' => 'tel:+18338332010',
        'routePrefix' => '/',
        'address' => '2921 Landmark Pl, Ste 206, Madison, WI 53713',
        'stagingEnabled' => true,
        'productionEnabled' => true,
        'preview' => false,
        'retired' => false,
    ],
    'wi' => [
        'label' => 'Wisconsin',
        'phoneDisplay' => '(608) 420-2377',
        'phoneHref' => 'tel:+16084202377',
        'metroLines' => [
            ['label' => 'Madison', 'phoneDisplay' => '(608) 420-2377', 'phoneHref' => 'tel:+16084202377'],
            ['label' => 'Milwaukee', 'phoneDisplay' => '(414) 800-9271', 'phoneHref' => 'tel:+14148009271'],
        ],
        'routePrefix' => '/wi/',
        'address' => '2921 Landmark Pl, Ste 206, Madison, WI 53713',
        'stagingEnabled' => true,
        'productionEnabled' => true,
        'preview' => false,
        'retired' => false,
    ],
    // RETIRED August 2026 (Brand Toolkit v1.0: "Kentucky / Lexington -- no longer
    // a market"). The key, phone and address stay as archive so the fixed
    // four-market boundary in MarketRegistry.php:12, Experience.php and the
    // production adapters keeps holding, and so the archived blog-3 subsite stays
    // renderable for admin and for the Kentucky isolation harnesses. Front-end
    // /ky/ requests are 301'd by twins_overhaul_redirect_retired_ky_market() (r33)
    // at template_redirect priority 0, so no /ky/ page composition ever happens on
    // a real request. 'retired' => true keeps Kentucky out of every visitor-facing
    // market list via MarketRegistry::selectable(), and productionEnabled => false
    // means production can never resolve it at all.
    'ky' => [
        'label' => 'Kentucky',
        'phoneDisplay' => '(859) 440-2227',
        'phoneHref' => 'tel:+18594402227',
        'routePrefix' => '/ky/',
        'address' => '3651 Aarons Run Rd, Mt Sterling, KY 40353',
        'stagingEnabled' => true,
        'productionEnabled' => false,
        'preview' => false,
        'retired' => true,
    ],
    'il-preview' => [
        'label' => 'Illinois',
        'phoneDisplay' => '(815) 800-2025',
        'phoneHref' => 'tel:+18158002025',
        'routePrefix' => '/il/',
        'address' => '5758 Elaine Dr, Rockford, IL 61108',
        'stagingEnabled' => true,
        'productionEnabled' => false,
        'preview' => true,
        'retired' => false,
    ],
];
