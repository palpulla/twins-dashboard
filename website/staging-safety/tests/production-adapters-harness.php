<?php

declare(strict_types=1);

/**
 * Unit-validates the PRODUCTION adapters (production-cutover/production-adapters.php)
 * in isolation.
 *
 * These implement the production branch of brand-runtime.php and are never loaded
 * on staging (the staging LOADER refuses to boot outside WP_ENVIRONMENT_TYPE=
 * 'staging'), so this focused harness is the only place they can be exercised in
 * the suite. It proves the file loads, the three classes satisfy the brand-core
 * adapter interfaces the Experience constructor requires, assertReady() passes for
 * the approved hosts/destinations, and the quote adapter renders the trusted
 * callback form that the classified-output form gate (Blocker B) must allow on
 * production. It does NOT boot the staging package or bypass any safety gate;
 * production-adapters.php carries no environment authority of its own.
 *
 * argv[1]: the portable brand core directory (twins-brand-experience).
 */

if ($argc !== 2 || !is_dir($argv[1])) {
    fwrite(STDERR, "PRODUCTION_ADAPTERS_CORE_MISSING\n");
    exit(2);
}

define('ABSPATH', __DIR__ . '/');
define('WP_ENVIRONMENT_TYPE', 'production');

function twins_prod_adapters_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'PRODUCTION_ADAPTERS_HARNESS_FAILED: ' . $message . "\n");
        exit(1);
    }
}

$core = rtrim($argv[1], '/');
require $core . '/bootstrap.php';
require dirname(__DIR__, 2) . '/production-cutover/production-adapters.php';

$quote = new ProductionQuoteAdapter();
$booking = new ProductionBookingAdapter();
$applications = new ProductionApplicationAdapter();

// Interface conformance: the Experience constructor accepts exactly these three.
twins_prod_adapters_assert($quote instanceof Twins\BrandExperience\QuoteAdapter, 'quote adapter does not implement QuoteAdapter');
twins_prod_adapters_assert($booking instanceof Twins\BrandExperience\BookingAdapter, 'booking adapter does not implement BookingAdapter');
twins_prod_adapters_assert($applications instanceof Twins\BrandExperience\ApplicationAdapter, 'application adapter does not implement ApplicationAdapter');

// Every adapter must report ready for its approved host/destination.
$quote->assertReady();
$booking->assertReady();
$applications->assertReady();

// The quote adapter renders the trusted callback form the production form gate allows.
$quoteExperience = $quote->renderExperience([]);
twins_prod_adapters_assert(is_string($quoteExperience) && stripos($quoteExperience, '<form') !== false, 'production quote adapter did not render the callback form');
twins_prod_adapters_assert(strpos($quoteExperience, 'data-callback-endpoint="https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/lp-lead-intake"') !== false, 'callback form lost the approved lead-intake endpoint');
twins_prod_adapters_assert(strpos($quoteExperience, 'name="website"') !== false, 'callback form lost its spam honeypot field');
twins_prod_adapters_assert(strpos($quoteExperience, 'Reply STOP to opt out') !== false, 'callback form lost its consent copy');

// Booking is external Housecall Pro; careers is an external anchor with no form.
$bookingAction = $booking->action([]);
twins_prod_adapters_assert(($bookingAction['mode'] ?? '') === 'external', 'production booking is not external');
twins_prod_adapters_assert(strpos($bookingAction['href'] ?? '', 'book.housecallpro.com') !== false, 'production booking lost the Housecall Pro host');
$careers = $applications->renderExperience([]);
twins_prod_adapters_assert(stripos($careers, '<form') === false && strpos($careers, '/careers/#apply') !== false, 'production careers adapter is not the external application anchor');

echo "PRODUCTION_ADAPTERS_HARNESS_OK\n";
