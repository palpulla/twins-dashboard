<?php
declare(strict_types=1);

use Twins\BrandExperience\ApplicationAdapter;
use Twins\BrandExperience\AssetResolver;
use Twins\BrandExperience\BookingAdapter;
use Twins\BrandExperience\QuoteAdapter;
use Twins\BrandExperience\ReviewCodec;
use Twins\BrandExperience\ReviewsProvider;
use Twins\BrandExperience\RouteAdapter;

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

final class StagingAssetResolver implements AssetResolver
{
    private string $base;

    private const MAP = [
        'logo' => 'assets/images/brand/twins-logo.png',
        'twin-left' => 'assets/images/brand/twin-left.png',
        'twin-right' => 'assets/images/brand/twin-right.png',
        'truck-original' => 'assets/images/brand/twins-service-truck-cutout.png',
        'truck-webp' => 'assets/images/brand/twins-service-truck-cutout.webp',
        'crew-fleet-original' => 'assets/images/team/twins-crew-fleet.jpeg',
        'crew-fleet-768w' => 'assets/images/team/twins-crew-fleet-768w.webp',
        'crew-fleet-1280w' => 'assets/images/team/twins-crew-fleet-1280w.webp',
        'crew-fleet-1920w' => 'assets/images/team/twins-crew-fleet-1920w.webp',
        'tal-portrait-original' => 'assets/images/team/tal-joseph.jpeg',
        'tal-portrait-480w' => 'assets/images/team/tal-joseph-480w.webp',
        'tal-portrait-768w' => 'assets/images/team/tal-joseph-768w.webp',
        'tal-portrait-1066w' => 'assets/images/team/tal-joseph-1066w.webp',
        'technician-original' => 'assets/images/team/twins-technician-at-work.png',
        'technician-480w' => 'assets/images/team/twins-technician-at-work-480w.webp',
        'technician-768w' => 'assets/images/team/twins-technician-at-work-768w.webp',
        'technician-924w' => 'assets/images/team/twins-technician-at-work-924w.webp',
        'daniel-portrait-original' => 'assets/images/team/daniel-joseph.jpeg',
        'daniel-portrait-480w' => 'assets/images/team/daniel-joseph-480w.webp',
        'daniel-portrait-768w' => 'assets/images/team/daniel-joseph-768w.webp',
        'daniel-portrait-1066w' => 'assets/images/team/daniel-joseph-1066w.webp',
        'charles-portrait-original' => 'assets/images/team/charles-rue.jpeg',
        'charles-portrait-480w' => 'assets/images/team/charles-rue-480w.webp',
        'charles-portrait-768w' => 'assets/images/team/charles-rue-768w.webp',
        'charles-portrait-1066w' => 'assets/images/team/charles-rue-1066w.webp',
        'maurice-portrait-original' => 'assets/images/team/maurice-williams.jpeg',
        'maurice-portrait-480w' => 'assets/images/team/maurice-williams-480w.webp',
        'maurice-portrait-768w' => 'assets/images/team/maurice-williams-768w.webp',
        'maurice-portrait-1066w' => 'assets/images/team/maurice-williams-1066w.webp',
        'nicholas-portrait-original' => 'assets/images/team/nicholas-roccaforte.jpeg',
        'nicholas-portrait-480w' => 'assets/images/team/nicholas-roccaforte-480w.webp',
        'nicholas-portrait-768w' => 'assets/images/team/nicholas-roccaforte-768w.webp',
        'nicholas-portrait-1066w' => 'assets/images/team/nicholas-roccaforte-1066w.webp',
        'door-builder-before-after' => 'assets/images/door-builder/twins-before-after-install.webp',
    ];

    public function __construct(string $base, string $networkHome)
    {
        foreach ([$base, $networkHome] as $url) {
            $parts = parse_url($url);
            if (
                !is_array($parts)
                || ($parts['scheme'] ?? '') !== 'https'
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['query'])
                || isset($parts['fragment'])
            ) {
                throw new DomainException('Invalid staging asset origin.');
            }
        }
        $baseParts = parse_url($base);
        $homeParts = parse_url($networkHome);
        $baseOrigin = strtolower((string) $baseParts['host']) . ':' . ($baseParts['port'] ?? 443);
        $homeOrigin = strtolower((string) $homeParts['host']) . ':' . ($homeParts['port'] ?? 443);
        if ($baseOrigin !== $homeOrigin) {
            throw new DomainException('Cross-origin staging assets are forbidden.');
        }
        $this->base = rtrim($base, '/');
    }

    public function url(string $assetKey): string
    {
        if (!isset(self::MAP[$assetKey])) throw new DomainException('Unknown asset key.');
        return $this->base . '/' . self::MAP[$assetKey];
    }
}

final class StagingRouteAdapter implements RouteAdapter
{
    private string $base;

    private const ROUTES = [
        'main' => [
            'home' => '/', 'services' => '/garage-door-services/', 'installation' => '/garage-door-installation/',
            'repair' => '/garage-door-repair/',
            'spring-repair' => '/garage-door-spring-repair/', 'opener-repair' => '/garage-door-opener-repair/',
            'emergency-service' => '/emergency-garage-services/', 'garage-doors' => '/clopay-garage-doors/',
            'classic-collection' => '/clopay-classic-collection/', 'modern-steel' => '/clopay-modern-steel/',
            'gallery-steel' => '/clopay-gallery-steel/', 'door-builder' => '/door-builder/',
            'wi' => '/wi/', 'ky' => '/ky/', 'il-preview' => '/il/', 'reviews' => '/reviews/',
            'cost-guide' => '/wi/garage-door-cost-in-madison-wi/', 'financing' => '/financing/',
            'offers' => '/coupons-offers/', 'faqs' => '/faqs/', 'blog' => '/blog/', 'about' => '/about-us/',
            'team' => '/our-team/', 'careers' => '/careers/', 'contact' => '/contact-us/',
            'cable-repair' => '/garage-door-cable-repair/', 'weatherstripping' => '/garage-weatherstripping-repair/',
            'maintenance-plans' => '/maintenance-plans/', 'property-management' => '/property-management-services/',
            'openers' => '/garage-door-openers/', 'service-area' => '/locations/',
        ],
        'wi' => [
            'home' => '/wi/', 'services' => '/wi/garage-door-services/', 'installation' => '/wi/garage-door-installation/',
            'repair' => '/wi/garage-door-repair/',
            'spring-repair' => '/wi/garage-door-spring-repair/', 'opener-repair' => '/wi/garage-door-opener-repair/',
            'emergency-service' => '/wi/emergency-garage-services/', 'garage-doors' => '/clopay-garage-doors/',
            'classic-collection' => '/clopay-classic-collection/', 'modern-steel' => '/clopay-modern-steel/',
            'gallery-steel' => '/clopay-gallery-steel/', 'door-builder' => '/wi/door-builder/',
            'wi' => '/wi/', 'ky' => '/ky/', 'il-preview' => '/il/', 'reviews' => '/wi/reviews/',
            'cost-guide' => '/wi/garage-door-cost-in-madison-wi/', 'financing' => '/wi/financing/',
            'offers' => '/wi/coupons-offers/', 'faqs' => '/wi/faqs/', 'blog' => '/wi/blog/', 'about' => '/wi/about-us/',
            'team' => '/our-team/', 'careers' => '/careers/', 'contact' => '/wi/contact-us/',
            'cable-repair' => '/wi/garage-door-cable-repair/', 'weatherstripping' => '/wi/garage-weatherstripping-repair/',
            'property-management' => '/wi/property-management-services/', 'openers' => '/wi/garage-door-openers/',
            'service-area' => '/wi/service-area/',
            'city-madison' => '/wi/location/madison/', 'city-milwaukee' => '/wi/garage-door-repair-in-milwaukee-wi/',
            'city-belleville' => '/wi/location/belleville/', 'city-cottage-grove' => '/wi/location/cottage-grove/',
            'city-cross-plains' => '/wi/location/cross-plains/', 'city-deerfield' => '/wi/location/deerfield/',
            'city-deforest' => '/wi/location/deforest/', 'city-edgerton' => '/wi/location/edgerton/',
            'city-evansville' => '/wi/location/evansville/', 'city-fitchburg' => '/wi/location/fitchburg/',
            'city-fort-atkinson' => '/wi/location/fort-atkinson/', 'city-janesville' => '/wi/location/janesville/',
            'city-marshall' => '/wi/location/marshall/', 'city-mcfarland' => '/wi/location/mcfarland/',
            'city-middleton' => '/wi/location/middleton/', 'city-milton' => '/wi/location/milton/',
            'city-monona' => '/wi/location/monona/', 'city-oregon' => '/wi/location/oregon/',
            'city-prairie-du-sac' => '/wi/location/prairie-du-sac/', 'city-sun-prairie' => '/wi/location/sun-prairie/',
            'city-verona' => '/wi/location/verona/',
        ],
        'ky' => [
            'home' => '/ky/', 'services' => '/ky/garage-door-services/', 'installation' => '/ky/garage-door-installation/',
            'repair' => '/ky/garage-door-repair/',
            'spring-repair' => '/ky/garage-door-spring-repair/', 'opener-repair' => '/ky/garage-door-opener-repair/',
            'emergency-service' => '/ky/emergency-garage-services/', 'garage-doors' => '/clopay-garage-doors/',
            'classic-collection' => '/clopay-classic-collection/', 'modern-steel' => '/clopay-modern-steel/',
            'gallery-steel' => '/clopay-gallery-steel/', 'door-builder' => '/ky/design-your-door/',
            'wi' => '/wi/', 'ky' => '/ky/', 'il-preview' => '/il/', 'reviews' => '/ky/reviews/',
            'cost-guide' => '/wi/garage-door-cost-in-madison-wi/', 'financing' => '/ky/financing/',
            'offers' => '/ky/coupons-offers/', 'faqs' => '/ky/faqs/', 'blog' => '/ky/blog/', 'about' => '/ky/about-us/',
            'team' => '/our-team/', 'careers' => '/careers/', 'contact' => '/ky/contact-us/',
            'cable-repair' => '/ky/garage-door-cable-repair/', 'maintenance-plans' => '/ky/maintenance-plans/',
            'openers' => '/ky/garage-door-openers/', 'city-lexington' => '/ky/location/lexington/',
        ],
        'il-preview' => [
            'home' => '/il/', 'services' => '/il/garage-door-services/', 'installation' => '/il/garage-door-installation/',
            'repair' => '/il/garage-door-repair/',
            'spring-repair' => '/il/garage-door-repair/', 'opener-repair' => '/il/garage-door-openers/',
            'emergency-service' => '/il/emergency-garage-services/', 'garage-doors' => '/clopay-garage-doors/',
            'classic-collection' => '/clopay-classic-collection/', 'modern-steel' => '/clopay-modern-steel/',
            'gallery-steel' => '/clopay-gallery-steel/', 'door-builder' => '/il/door-builder/',
            'wi' => '/wi/', 'ky' => '/ky/', 'il-preview' => '/il/', 'reviews' => '/reviews/',
            'cost-guide' => '/wi/garage-door-cost-in-madison-wi/', 'financing' => '/financing/',
            'offers' => '/coupons-offers/', 'faqs' => '/faqs/', 'blog' => '/blog/', 'about' => '/about-us/',
            'team' => '/our-team/', 'careers' => '/careers/', 'contact' => '/il/contact-us/',
            'openers' => '/il/garage-door-openers/', 'service-area' => '/il/locations/',
            'city-rockford' => '/il/location/rockford/', 'city-loves-park' => '/il/location/loves-park/',
            'city-machesney-park' => '/il/location/machesney-park/', 'city-belvidere' => '/il/location/belvidere/',
            'city-roscoe' => '/il/location/roscoe/', 'city-rockton' => '/il/location/rockton/',
            'city-cherry-valley' => '/il/location/cherry-valley/', 'city-poplar-grove' => '/il/location/poplar-grove/',
            'city-south-beloit' => '/il/location/south-beloit/', 'city-winnebago' => '/il/location/winnebago/',
            'city-byron' => '/il/location/byron/', 'city-caledonia' => '/il/location/caledonia/',
        ],
    ];

    public function __construct()
    {
        $home = network_home_url('/');
        $parts = parse_url($home);
        if (
            !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || !in_array((string) ($parts['path'] ?? '/'), ['', '/'], true)
        ) {
            throw new DomainException('Invalid staging route origin.');
        }
        $this->base = rtrim($home, '/');
    }

    public function normalizeContext(array $requestContext): array
    {
        if (array_key_exists('environment', $requestContext) && $requestContext['environment'] !== 'staging') {
            throw new DomainException('Caller environment does not match staging.');
        }
        $key = $requestContext['key'] ?? null;
        if (!is_string($key) || !in_array($key, ['main', 'wi', 'ky', 'il'], true)) {
            throw new DomainException('Unknown staging context key.');
        }
        $requestContext['key'] = $key;
        $requestContext['environment'] = 'staging';
        $requestContext['market'] = $key === 'il' ? 'il-preview' : $key;
        return $requestContext;
    }

    public function route(string $routeKey, string $marketKey): string
    {
        if (!isset(self::ROUTES[$marketKey])) throw new DomainException('Unknown staging route market.');
        if (!isset(self::ROUTES[$marketKey][$routeKey])) throw new DomainException('Unknown staging route key.');
        return $this->base . self::ROUTES[$marketKey][$routeKey];
    }
}

final class CapturedReviewsProvider implements ReviewsProvider
{
    private const MAX_BYTES = 2097152;
    private const SNAPSHOT_KEYS = ['dev', 'ino', 'mode', 'uid', 'gid', 'size', 'mtime', 'ctime'];
    private string $path;

    public function __construct(string $path)
    {
        if ($path === '' || strpos($path, "\0") !== false) throw new DomainException('Invalid captured review path.');
        $this->path = $path;
    }

    private function snapshot(): ?array
    {
        clearstatcache(true, $this->path);
        $stat = @lstat($this->path);
        if (!is_array($stat)) return null;
        if (((int) $stat['mode'] & 0170000) !== 0100000) return null;
        if ((int) $stat['size'] < 1 || (int) $stat['size'] > self::MAX_BYTES) return null;
        $snapshot = [];
        foreach (self::SNAPSHOT_KEYS as $key) {
            if (!array_key_exists($key, $stat)) return null;
            $snapshot[$key] = (int) $stat[$key];
        }
        return $snapshot;
    }

    private static function sameSnapshot(array $expected, array $actual): bool
    {
        foreach (self::SNAPSHOT_KEYS as $key) {
            if (!array_key_exists($key, $actual) || (int) $actual[$key] !== $expected[$key]) return false;
        }
        return true;
    }

    public function collection(): array
    {
        try {
            $before = $this->snapshot();
            if ($before === null) return ['status' => 'unavailable'];
            $handle = @fopen($this->path, 'rb');
            if (!is_resource($handle)) return ['status' => 'unavailable'];
            try {
                $opened = @fstat($handle);
                if (!is_array($opened) || !self::sameSnapshot($before, $opened)) return ['status' => 'unavailable'];
                $bytes = '';
                while (strlen($bytes) < $before['size']) {
                    $chunk = fread($handle, min(8192, $before['size'] - strlen($bytes)));
                    if (!is_string($chunk) || $chunk === '') return ['status' => 'unavailable'];
                    $bytes .= $chunk;
                }
                if (fread($handle, 1) !== '') return ['status' => 'unavailable'];
                $readSnapshot = @fstat($handle);
                if (!is_array($readSnapshot) || !self::sameSnapshot($before, $readSnapshot)) return ['status' => 'unavailable'];
            } finally {
                fclose($handle);
            }
            $after = $this->snapshot();
            if ($after === null || !self::sameSnapshot($before, $after)) return ['status' => 'unavailable'];
            $decoded = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) return ['status' => 'unavailable'];
            $verified = ReviewCodec::verifyCollection($decoded, new DateTimeImmutable('now', new DateTimeZone('UTC')));
            unset($verified['allowExternalSourceAction']);
            return $verified;
        } catch (Throwable $error) {
            return ['status' => 'unavailable'];
        }
    }
}

function twins_brand_staging_assert_context(array $context): void
{
    $environment = $context['environment'] ?? null;
    $market = $context['market'] ?? null;
    $key = $context['key'] ?? null;
    if (!is_string($key) || !in_array($key, ['main', 'wi', 'ky', 'il'], true)) {
        throw new DomainException('Unknown staging adapter context key.');
    }
    $expectedMarket = $key === 'il' ? 'il-preview' : $key;
    if ($environment !== 'staging' || !is_string($market) || $market !== $expectedMarket) {
        throw new DomainException('Staging adapter context is incomplete.');
    }
}

function twins_brand_staging_assert_inert_markup(string $markup, string $requiredMarker): void
{
    if ($markup === '' || strpos($markup, $requiredMarker) === false) throw new DomainException('Staging preview marker is missing.');
    if (preg_match('~</?form\b|\b(?:name|form|formaction|action|method)\s*=|type\s*=\s*["\'](?:submit|image)["\']~i', $markup)) {
        throw new DomainException('Staging preview contains submission authority.');
    }
    if (preg_match('~https?:|//|\b(?:fetch|XMLHttpRequest|sendBeacon|requestSubmit)\b~i', $markup)) {
        throw new DomainException('Staging preview contains remote authority.');
    }
}

final class StagingQuoteAdapter implements QuoteAdapter
{
    private string $href;

    public function __construct()
    {
        $route = new StagingRouteAdapter();
        $this->href = $route->route('contact', 'main');
    }

    private function href(): string
    {
        return $this->href;
    }

    public function action(array $context): array
    {
        twins_brand_staging_assert_context($context);
        $this->assertReady();
        return ['mode' => 'preview', 'href' => $this->href()];
    }

    public function renderExperience(array $context): string
    {
        twins_brand_staging_assert_context($context);
        $markup = twins_brand_staging_quote_preview();
        twins_brand_staging_assert_inert_markup($markup, 'data-preview-kind="quote"');
        return $markup;
    }

    public function assertReady(): void
    {
        $href = $this->href();
        if ($href !== rtrim(network_home_url('/'), '/') . '/contact-us/') throw new DomainException('Staging quote route is not fixed.');
        twins_brand_staging_assert_inert_markup(twins_brand_staging_quote_preview(), 'data-preview-kind="quote"');
    }
}

final class StagingBookingAdapter implements BookingAdapter
{
    public function action(array $context): array
    {
        twins_brand_staging_assert_context($context);
        $this->assertReady();
        return ['mode' => 'dialog', 'experienceHtml' => twins_brand_staging_booking_preview()];
    }

    public function assertReady(): void
    {
        twins_brand_staging_assert_inert_markup(twins_brand_staging_booking_preview(), 'data-twins-booking-dialog');
    }
}

final class StagingApplicationAdapter implements ApplicationAdapter
{
    public function clientContract(array $context): array
    {
        twins_brand_staging_assert_context($context);
        $this->assertReady();
        return ['mode' => 'preview'];
    }

    public function renderExperience(array $context): string
    {
        twins_brand_staging_assert_context($context);
        $markup = twins_brand_staging_application_preview();
        twins_brand_staging_assert_inert_markup($markup, 'data-preview-kind="application"');
        return $markup;
    }

    public function assertReady(): void
    {
        twins_brand_staging_assert_inert_markup(twins_brand_staging_application_preview(), 'data-preview-kind="application"');
    }
}
