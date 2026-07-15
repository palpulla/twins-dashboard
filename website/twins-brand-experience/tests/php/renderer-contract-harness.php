<?php
declare(strict_types=1);

if (!isset($argv[1])) {
    fwrite(STDERR, "portable bootstrap path is required\n");
    exit(1);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require $argv[1];

final class RendererHarnessAssetResolver implements Twins\BrandExperience\AssetResolver
{
    private const ASSETS = [
        'logo' => '/brand/twins-logo.png',
        'crew-fleet-original' => '/team/twins-crew-fleet.jpeg',
        'crew-fleet-768w' => '/team/twins-crew-fleet-768w.webp',
        'crew-fleet-1280w' => '/team/twins-crew-fleet-1280w.webp',
        'crew-fleet-1920w' => '/team/twins-crew-fleet-1920w.webp',
        'tal-portrait-original' => '/team/tal-joseph.jpeg',
        'tal-portrait-480w' => '/team/tal-joseph-480w.webp',
        'tal-portrait-768w' => '/team/tal-joseph-768w.webp',
        'tal-portrait-1066w' => '/team/tal-joseph-1066w.webp',
        'technician-original' => '/team/twins-technician-at-work.png',
        'technician-480w' => '/team/twins-technician-at-work-480w.webp',
        'technician-768w' => '/team/twins-technician-at-work-768w.webp',
        'technician-924w' => '/team/twins-technician-at-work-924w.webp',
        'door-builder-before-after' => '/door-builder/twins-before-after-install.webp',
    ];

    public function url(string $assetKey): string
    {
        if (!isset(self::ASSETS[$assetKey])) throw new DomainException('Unknown renderer asset key.');
        return self::ASSETS[$assetKey];
    }
}

final class RendererHarnessRouteAdapter implements Twins\BrandExperience\RouteAdapter
{
    private const ROUTES = [
        'home' => '/',
        'services' => '/garage-door-services/',
        'installation' => '/garage-door-installation/',
        'spring-repair' => '/garage-door-spring-repair/',
        'opener-repair' => '/garage-door-opener-repair/',
        'emergency-service' => '/emergency-garage-services/',
        'garage-doors' => '/clopay-garage-doors/',
        'classic-collection' => '/clopay-classic-collection/',
        'modern-steel' => '/clopay-modern-steel/',
        'gallery-steel' => '/clopay-gallery-steel/',
        'door-builder' => '/door-builder/',
        'wi' => '/wi/',
        'ky' => '/ky/',
        'il-preview' => '/il/',
        'reviews' => '/reviews/',
        'cost-guide' => '/wi/garage-door-cost-in-madison-wi/',
        'financing' => '/financing/',
        'offers' => '/coupons-offers/',
        'faqs' => '/faqs/',
        'blog' => '/blog/',
        'about' => '/about-us/',
        'team' => '/our-team/',
        'careers' => '/careers/',
        'contact' => '/contact-us/',
    ];

    public function normalizeContext(array $requestContext): array { return $requestContext; }

    public function route(string $routeKey, string $marketKey): string
    {
        if (!isset(self::ROUTES[$routeKey])) throw new DomainException('Unknown renderer route key.');
        return self::ROUTES[$routeKey];
    }
}

final class RendererHarnessReviewsProvider implements Twins\BrandExperience\ReviewsProvider
{
    private array $collection;
    public int $calls = 0;

    public function __construct(array $collection) { $this->collection = $collection; }

    public function collection(): array
    {
        $this->calls++;
        return $this->collection;
    }
}

final class RendererHarnessQuoteAdapter implements Twins\BrandExperience\QuoteAdapter
{
    public function action(array $context): array { return ['href' => '/request-a-quote/']; }
    public function renderExperience(array $context): string { return '<div id="quote-fixture"></div>'; }
    public function assertReady(): void {}
}

final class RendererHarnessBookingAdapter implements Twins\BrandExperience\BookingAdapter
{
    private string $mode;
    public const EXTERNAL_URL = 'https://booking.example.invalid/schedule';

    public function __construct(string $mode) { $this->mode = $mode; }

    public function action(array $context): array
    {
        if ($this->mode === 'dialog') {
            return ['mode' => 'dialog', 'experienceHtml' => '<div id="booking-dialog-fixture">Private booking fixture</div>'];
        }
        if ($this->mode === 'external') return ['mode' => 'external', 'href' => self::EXTERNAL_URL];
        throw new DomainException('Unknown renderer booking mode.');
    }

    public function assertReady(): void {}
}

final class RendererHarnessApplicationAdapter implements Twins\BrandExperience\ApplicationAdapter
{
    public function clientContract(array $context): array { return ['mode' => 'fixture']; }
    public function renderExperience(array $context): string { return '<div id="application-fixture"></div>'; }
    public function assertReady(): void {}
}

$expect = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$root = dirname($argv[1]);
$markets = new Twins\BrandExperience\MarketRegistry(require $root . '/config/markets.php');

$makeExperience = static function (array $collection, string $bookingMode) use ($markets, $root): array {
    $reviews = new RendererHarnessReviewsProvider($collection);
    $experience = new Twins\BrandExperience\Experience(
        new RendererHarnessAssetResolver(),
        new RendererHarnessRouteAdapter(),
        $reviews,
        new RendererHarnessQuoteAdapter(),
        new RendererHarnessBookingAdapter($bookingMode),
        new RendererHarnessApplicationAdapter(),
        $markets,
        $root
    );
    return [$experience, $reviews];
};

$renderComponent = static function (Twins\BrandExperience\Experience $experience, string $file, array $scope): string {
    $level = ob_get_level();
    ob_start();
    try {
        extract($scope, EXTR_SKIP);
        require $file;
        return (string) ob_get_clean();
    } catch (Throwable $error) {
        while (ob_get_level() > $level) ob_end_clean();
        throw $error;
    }
};

$records = [
    [
        'stableId' => 'review-alpha<&"',
        'author' => 'Ava O’Neil & Sons',
        'rating' => 4,
        'publishedDate' => '2026-06-30',
        'text' => "Fast, careful & clear.\nWould call again — absolutely!",
        'sourceRecordUrl' => 'https://records.example.invalid/review-alpha',
    ],
    [
        'stableId' => 'review-beta',
        'author' => 'M. Rivera, Jr.',
        'rating' => 5,
        'publishedDate' => '2026-07-02',
        'text' => '“Excellent” work; clean, prompt, and friendly.',
        'sourceRecordUrl' => 'https://records.example.invalid/review-beta',
    ],
    [
        'stableId' => 'review-gamma',
        'author' => 'Jordan Lee',
        'rating' => 5,
        'publishedDate' => '2026-06-21',
        'text' => 'On time, upfront, and careful with our home.',
        'sourceRecordUrl' => 'https://records.example.invalid/review-gamma',
    ],
    [
        'stableId' => 'review-delta',
        'author' => 'Priya K.',
        'rating' => 5,
        'publishedDate' => '2026-06-18',
        'text' => 'The door is quiet again — thank you!',
        'sourceRecordUrl' => 'https://records.example.invalid/review-delta',
    ],
    [
        'stableId' => 'review-epsilon',
        'author' => 'Sam & Taylor',
        'rating' => 5,
        'publishedDate' => '2026-06-10',
        'text' => 'Straight answers; excellent follow-through.',
        'sourceRecordUrl' => 'https://records.example.invalid/review-epsilon',
    ],
];
$businessReviewsUrl = 'https://reviews.example.invalid/twins';
foreach ($records as $index => $record) {
    $records[$index]['recordSha256'] = Twins\BrandExperience\ReviewCodec::recordSha256($record);
}
$verifiedCollection = Twins\BrandExperience\ReviewCodec::verifyCollection([
    'schemaVersion' => 1,
    'sourceUrl' => 'https://twinsgaragedoors.com/wi/reviews/',
    'businessReviewsUrl' => $businessReviewsUrl,
    'multisitePath' => '/wi/',
    'pageId' => 2186,
    'collectionId' => 2178,
    'providerVersion' => 'renderer-fixture-1',
    'capturedAt' => '2026-07-15T00:00:00Z',
    'sourceResponseSha256' => str_repeat('a', 64),
    'recordCount' => count($records),
    'records' => $records,
], new DateTimeImmutable('2026-07-15T00:00:00Z'));
$verifiedCollection['allowExternalSourceAction'] = true;

[$stagingExperience] = $makeExperience($verifiedCollection, 'dialog');
$header = $stagingExperience->renderHeader(['environment' => 'staging', 'market' => 'main']);
$expect(substr_count($header, 'aria-label="Primary navigation"') === 1, 'header must contain one primary navigation');
$expect(strpos($header, 'Request a Quote') !== false, 'header is missing exact quote CTA');
$expect(strpos($header, 'Book Online') !== false, 'header is missing booking CTA');
$expect(strpos($header, 'Our Team') !== false, 'header is missing team route');
$expect(strpos($header, 'Get an Estimate') === false, 'header contains prohibited legacy CTA');
$expect(strpos($header, 'https://twinsgaragedoors.com') === false, 'header hard-coded the production host');
$expect(strpos($header, 'book.housecallpro.com') === false, 'staging header exposed a live booking host');
$expect(substr_count($header, 'data-twins-booking-open') === 2, 'dialog mode must render two button triggers');
$expect(substr_count($header, '<button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Online</button>') === 2, 'dialog mode rendered a non-button booking action');
$expect(substr_count($header, 'id="booking-dialog-fixture"') === 1, 'dialog experience must render exactly once');
$expect(strpos($header, 'Illinois preview') !== false, 'staging header omitted Illinois preview');

[$productionExperience] = $makeExperience($verifiedCollection, 'external');
$productionHeader = $productionExperience->renderHeader(['environment' => 'production', 'market' => 'main']);
$expect(substr_count($productionHeader, RendererHarnessBookingAdapter::EXTERNAL_URL) === 2, 'external booking URL must render in both approved header actions');
$expect(substr_count($productionHeader, 'data-twins-booking-open') === 0, 'external booking mode rendered dialog triggers');
$expect(strpos($productionHeader, 'booking-dialog-fixture') === false, 'external booking mode rendered dialog HTML');
$expect(strpos($productionHeader, 'Illinois preview') === false, 'production header exposed Illinois preview');

$footer = $stagingExperience->renderFooter(['environment' => 'staging', 'market' => 'main']);
$expect(strpos($footer, 'aria-label="Quick actions"') !== false, 'footer is missing mobile quick actions');
$expect(substr_count($footer, 'Request a Quote') >= 2, 'footer must repeat the exact quote CTA');
$expect(strpos($footer, 'Call Twins') !== false, 'footer is missing Call Twins');
$expect(strpos($footer, 'https://twinsgaragedoors.com') === false, 'footer hard-coded the production host');
$expect(strpos($footer, 'danielj140.sg-host.com') === false, 'footer hard-coded the staging host');
foreach (['/garage-door-services/', '/clopay-garage-doors/', '/wi/', '/ky/', '/il/', '/about-us/', '/our-team/', '/careers/', '/contact-us/'] as $route) {
    $expect(strpos($footer, $route) !== false, 'footer omitted internal route ' . $route);
}

$crewPicture = $renderComponent($stagingExperience, $root . '/components/picture.php', [
    'logicalKey' => 'crew-fleet',
    'sizes' => '(max-width: 900px) 100vw, 50vw',
    'class' => 'crew-image "wide"',
    'loading' => 'lazy',
]);
$expect(strpos($crewPicture, '/team/twins-crew-fleet.jpeg') !== false, 'picture omitted fixed original');
$expect(strpos($crewPicture, '/team/twins-crew-fleet-768w.webp 768w') !== false, 'picture omitted 768w source');
$expect(strpos($crewPicture, '/team/twins-crew-fleet-1920w.webp 1920w') !== false, 'picture omitted 1920w source');
$expect(strpos($crewPicture, 'class="crew-image &quot;wide&quot;"') !== false, 'picture did not escape caller class');
$expect(strpos($crewPicture, 'The Twins Garage Doors crew with their branded service fleet') !== false, 'picture omitted fixed alt text');
$doorPicture = $renderComponent($stagingExperience, $root . '/components/picture.php', [
    'logicalKey' => 'door-builder-before-after',
    'sizes' => '100vw',
    'class' => 'door-preview',
    'loading' => 'eager',
]);
$expect(strpos($doorPicture, '<source') === false, 'single-source door picture emitted an empty responsive source');
$expect(strpos($doorPicture, 'width="1080" height="930"') !== false, 'door picture dimensions drifted');
$closedPicture = false;
try {
    $renderComponent($stagingExperience, $root . '/components/picture.php', [
        'logicalKey' => '/tmp/caller-selected.png',
        'sizes' => '100vw',
        'class' => 'bad',
        'loading' => 'lazy',
    ]);
} catch (DomainException $expected) {
    $closedPicture = true;
}
$expect($closedPicture, 'picture renderer accepted a caller-selected path');

[$stagingReviewExperience, $stagingReviews] = $makeExperience($verifiedCollection, 'dialog');
$stagingSlider = $renderComponent($stagingReviewExperience, $root . '/components/review-slider.php', [
    'environment' => 'staging',
    'marketKey' => 'main',
]);
$expect($stagingReviews->calls === 1, 'staging slider did not request the collection exactly once');
$expect(substr_count($stagingSlider, 'id="twins-brand-reviews-title"') === 1, 'review title target is not unique');
$expect(substr_count($stagingSlider, 'aria-labelledby="twins-brand-reviews-title"') === 1, 'review section is not labelled exactly once');
$expect(strpos($stagingSlider, 'Google reviews from real Twins customers') !== false, 'visible Google attribution is missing');
$expect(strpos($stagingSlider, 'href="/reviews/"') !== false, 'staging slider omitted internal Reviews route');
$expect(strpos($stagingSlider, $businessReviewsUrl) === false, 'staging slider exposed the external business URL');
foreach ($records as $record) {
    $expect(strpos($stagingSlider, $record['sourceRecordUrl']) === false, 'slider exposed a per-record source URL');
    $expect(strpos($stagingSlider, 'aria-label="' . $record['rating'] . ' out of 5 stars"') !== false, 'slider rating label drifted');
}
preg_match_all('/<article\b[^>]*>(.*?)<\/article>/s', $stagingSlider, $cards);
$expect(count($cards[1]) === count($records), 'slider rendered the wrong record count');
foreach ($records as $index => $record) {
    $card = $cards[1][$index];
    $expect(preg_match('/<blockquote>(.*?)<\/blockquote>/s', $card, $quoteMatch) === 1, 'review text markup missing');
    $encodedText = preg_replace('/<br\s*\/?>/i', '', $quoteMatch[1]);
    $expect(html_entity_decode($encodedText, ENT_QUOTES | ENT_HTML5, 'UTF-8') === $record['text'], 'review text bytes changed');
    $expect(preg_match('/<strong>(.*?)<\/strong>/s', $card, $authorMatch) === 1, 'review author markup missing');
    $expect(html_entity_decode($authorMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') === $record['author'], 'review author bytes changed');
    $expect(preg_match('/<time datetime="([^"]+)">(.*?)<\/time>/s', $card, $dateMatch) === 1, 'review date markup missing');
    $expect(html_entity_decode($dateMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') === $record['publishedDate'], 'review datetime bytes changed');
    $expect(html_entity_decode($dateMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') === $record['publishedDate'], 'visible review date bytes changed');
}

[$productionReviewExperience, $productionReviews] = $makeExperience($verifiedCollection, 'external');
$productionSlider = $renderComponent($productionReviewExperience, $root . '/components/review-slider.php', [
    'environment' => 'production',
    'marketKey' => 'main',
]);
$expect($productionReviews->calls === 1, 'production slider did not request the collection exactly once');
$expect(substr_count($productionSlider, $businessReviewsUrl) === 1, 'production external review URL must appear exactly once');
$expect(substr_count($productionSlider, 'rel="noopener noreferrer"') === 1, 'production external review action lacks the fixed rel');
foreach ($records as $record) {
    $expect(strpos($productionSlider, $record['sourceRecordUrl']) === false, 'production slider exposed a per-record source URL');
}

foreach (['false' => false, 'absent' => null, 'string' => 'true'] as $scenario => $permission) {
    $collection = $verifiedCollection;
    if ($scenario === 'absent') unset($collection['allowExternalSourceAction']);
    else $collection['allowExternalSourceAction'] = $permission;
    [$variantExperience, $variantReviews] = $makeExperience($collection, 'external');
    $variant = $renderComponent($variantExperience, $root . '/components/review-slider.php', [
        'environment' => 'production',
        'marketKey' => 'main',
    ]);
    $expect($variantReviews->calls === 1, $scenario . ' permission did not request the collection once');
    $expect(strpos($variant, $businessReviewsUrl) === false, $scenario . ' permission exposed the external business URL');
    $expect(strpos($variant, 'href="/reviews/"') !== false, $scenario . ' permission omitted the internal Reviews route');
}

[$unavailableExperience, $unavailableReviews] = $makeExperience(['status' => 'unavailable', 'reason' => 'private error 90210'], 'dialog');
$unavailable = $renderComponent($unavailableExperience, $root . '/components/review-slider.php', [
    'environment' => 'staging',
    'marketKey' => 'main',
]);
$expect($unavailableReviews->calls === 1, 'unavailable slider did not request the collection exactly once');
$expect(strpos($unavailable, 'Reviews are temporarily unavailable.') !== false, 'unavailable slider omitted its generic notice');
$expect(strpos($unavailable, 'private error 90210') === false, 'unavailable slider leaked provider detail');
$expect(strpos($unavailable, 'data-review-count') === false, 'unavailable slider emitted a numbered collection');

echo 'renderer-contracts-ok';
