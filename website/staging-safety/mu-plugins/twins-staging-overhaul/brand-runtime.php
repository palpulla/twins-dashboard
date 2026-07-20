<?php
declare(strict_types=1);

use Twins\BrandExperience\Experience;
use Twins\BrandExperience\MarketRegistry;

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

function twins_overhaul_brand_runtime(): Experience
{
    static $runtime = null;
    if ($runtime instanceof Experience) return $runtime;

    $candidates = [dirname(__DIR__) . '/twins-brand-experience'];
    if (PHP_SAPI === 'cli') $candidates[] = dirname(__DIR__, 3) . '/twins-brand-experience';
    $valid = array_values(array_filter($candidates, static function (string $candidate): bool {
        return is_dir($candidate)
            && is_file($candidate . '/bootstrap.php')
            && !is_link($candidate)
            && !is_link($candidate . '/bootstrap.php');
    }));
    if (count($valid) !== 1) {
        throw new RuntimeException('Portable brand core resolution is unavailable or ambiguous.');
    }

    $core = $valid[0];
    require_once $core . '/bootstrap.php';
    require_once __DIR__ . '/adapters/BrandStagingAdapters.php';
    require_once __DIR__ . '/adapters/BrandStagingPreviews.php';

    // Quote, Booking, and Careers are the only environment-specific adapters.
    // The portable infrastructure above (asset resolver, route adapter, reviews
    // provider) is shared across environments. On production the sealed build
    // installs production-adapters.php next to this file, and this branch swaps
    // in the live callback form, Housecall Pro booking, and careers flow. The
    // staging package never ships that file, so the production branch cannot run
    // there. The seam is the same one the renderer form scan and map embed use,
    // so the environments cannot drift. See production-build-spec.md.
    if (twins_overhaul_environment_is_production()) {
        require_once __DIR__ . '/adapters/production-adapters.php';
        $quote = new ProductionQuoteAdapter();
        $booking = new ProductionBookingAdapter();
        $applications = new ProductionApplicationAdapter();
    } else {
        $quote = new StagingQuoteAdapter();
        $booking = new StagingBookingAdapter();
        $applications = new StagingApplicationAdapter();
    }
    $quote->assertReady();
    $booking->assertReady();
    $applications->assertReady();

    $markets = new MarketRegistry(require $core . '/config/markets.php');
    $reviewPath = $core . '/data/reviews/google-business-reviews-collection-2178.json';
    if (PHP_SAPI !== 'cli') {
        if (!defined('WPMU_PLUGIN_DIR')) throw new RuntimeException('Fixed MU-plugin directory is unavailable.');
        $fixedReviewPath = rtrim(WPMU_PLUGIN_DIR, '/') . '/twins-brand-experience/data/reviews/google-business-reviews-collection-2178.json';
        if ($reviewPath !== $fixedReviewPath) throw new RuntimeException('Captured review path is outside the fixed MU-plugin directory.');
        $reviewPath = $fixedReviewPath;
    }
    $runtime = new Experience(
        new StagingAssetResolver(content_url('mu-plugins/twins-brand-experience'), network_home_url('/')),
        new StagingRouteAdapter(),
        new CapturedReviewsProvider($reviewPath),
        $quote,
        $booking,
        $applications,
        $markets,
        $core
    );
    return $runtime;
}
