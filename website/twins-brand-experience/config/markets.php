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
    // METRO LINES. 'key' joins a line to the metro its pages are filed under
    // (config/location-content.php 'metro') and to the metro tree the header
    // menu and the service-area index already navigate by
    // (components/nav-data.php $twinsNavMetroTree). It is what lets a
    // Milwaukee-metro page resolve the Milwaukee line instead of the
    // market-wide Madison one, and what lets /contact-us/ offer three teams
    // when only two markets exist. Every visitor-facing Twins number in the
    // portable runtime is written here and nowhere else.
    //
    // The three literals below are kept between 'phoneHref' and 'routePrefix'
    // with no comment in between on purpose: tests/contracts/portable-core.test.cjs
    // byte-matches this file against the staging registry with a regex that
    // walks that exact span.
    'wi' => [
        'label' => 'Wisconsin',
        'phoneDisplay' => '(608) 420-2377',
        'phoneHref' => 'tel:+16084202377',
        'metroLines' => [
            ['key' => 'madison', 'label' => 'Madison', 'phoneDisplay' => '(608) 420-2377', 'phoneHref' => 'tel:+16084202377'],
            ['key' => 'milwaukee', 'label' => 'Milwaukee', 'phoneDisplay' => '(414) 800-9271', 'phoneHref' => 'tel:+14148009271'],
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
    // The key is historical. 'il-preview' is baked into four fail-closed boundary
    // literals (MarketRegistry.php:12, Experience.php, the production adapters)
    // and into the portable<->staging byte-match test, which deliberately maps
    // portable 'il-preview' <-> staging 'il'. It is never rendered: the route
    // prefix is /il/ and the visitor-facing string is 'label' => 'Illinois'.
    // Renaming it is a separate change with a four-file fail-closed blast radius
    // and zero visitor benefit.
    //
    // Illinois stopped being a preview on 2026-08-18: real NAP, real phone, a
    // Rockford city page and 11 provisioned ring towns.
    'il-preview' => [
        'label' => 'Illinois',
        'phoneDisplay' => '(815) 800-2025',
        'phoneHref' => 'tel:+18158002025',
        'metroLines' => [
            ['key' => 'rockford', 'label' => 'Rockford', 'phoneDisplay' => '(815) 800-2025', 'phoneHref' => 'tel:+18158002025'],
        ],
        'routePrefix' => '/il/',
        'address' => '5758 Elaine Dr, Rockford, IL 61108',
        'stagingEnabled' => true,
        'productionEnabled' => true,
        'preview' => false,
        'retired' => false,
    ],
];
