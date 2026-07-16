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
        'twin-left' => '/brand/twin-left.png',
        'twin-right' => '/brand/twin-right.png',
        'truck-webp' => '/brand/twins-service-truck-cutout.webp',
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
    public function action(array $context): array { return ['href' => '/contact-us/']; }
    public function renderExperience(array $context): string
    {
        return '<div id="quote-fixture" role="form"><label>Full name <input type="text" autocomplete="name"></label><button type="button">Review quote on staging</button></div>';
    }
    public function assertReady(): void {}
}

final class RendererHarnessBookingAdapter implements Twins\BrandExperience\BookingAdapter
{
    private array $action;
    public int $calls = 0;
    public const EXTERNAL_URL = 'https://booking.example.invalid/schedule';

    public function __construct(array $action) { $this->action = $action; }

    public function action(array $context): array
    {
        $this->calls++;
        return $this->action;
    }

    public function assertReady(): void {}
}

final class RendererHarnessApplicationAdapter implements Twins\BrandExperience\ApplicationAdapter
{
    public function clientContract(array $context): array { return ['mode' => 'fixture']; }
    public function renderExperience(array $context): string
    {
        if (($context['environment'] ?? null) === 'staging') {
            return '<div id="application-fixture" role="form"><label>Full name <input type="text" autocomplete="name"></label><button type="button">Review application on staging</button></div>';
        }
        if (($context['environment'] ?? null) === 'production') {
            return '<div id="production-application-fixture">Production application fixture</div>';
        }
        throw new DomainException('Unknown application fixture environment.');
    }
    public function assertReady(): void {}
}

$expect = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$root = dirname($argv[1]);
$markets = new Twins\BrandExperience\MarketRegistry(require $root . '/config/markets.php');

$makeExperience = static function (array $collection, array $bookingAction) use ($markets, $root): array {
    $reviews = new RendererHarnessReviewsProvider($collection);
    $booking = new RendererHarnessBookingAdapter($bookingAction);
    $experience = new Twins\BrandExperience\Experience(
        new RendererHarnessAssetResolver(),
        new RendererHarnessRouteAdapter(),
        $reviews,
        new RendererHarnessQuoteAdapter(),
        $booking,
        new RendererHarnessApplicationAdapter(),
        $markets,
        $root
    );
    return [$experience, $reviews, $booking];
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

$longReviewText = 'From the first phone call through the final walkthrough, the Twins team communicated clearly, arrived when promised, protected the surrounding space, explained every repair choice without pressure, completed the work carefully, tested the door several times, cleaned up every tool and scrap, and left us with a quiet, reliable garage door and complete confidence in the result.';
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
        'text' => $longReviewText,
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
    [
        'stableId' => 'review-zeta',
        'author' => 'Alex N.',
        'rating' => 5,
        'publishedDate' => '2026-06-08',
        'text' => 'Prepared, courteous, and easy to work with.',
        'sourceRecordUrl' => 'https://records.example.invalid/review-zeta',
    ],
    [
        'stableId' => 'review-eta',
        'author' => 'Taylor W.',
        'rating' => 5,
        'publishedDate' => '2026-06-05',
        'text' => 'The repair was explained clearly and completed carefully.',
        'sourceRecordUrl' => 'https://records.example.invalid/review-eta',
    ],
    [
        'stableId' => 'review-theta',
        'author' => 'Chris B.',
        'rating' => 5,
        'publishedDate' => '2026-06-03',
        'text' => 'Friendly service and a garage door that works smoothly again.',
        'sourceRecordUrl' => 'https://records.example.invalid/review-theta',
    ],
    [
        'stableId' => 'review-iota',
        'author' => 'Drew H.',
        'rating' => 4,
        'publishedDate' => '2026-05-30',
        'text' => 'Good communication, prompt arrival, and dependable work.',
        'sourceRecordUrl' => 'https://records.example.invalid/review-iota',
    ],
    [
        'stableId' => 'review-kappa',
        'author' => 'Jamie P.',
        'rating' => 5,
        'publishedDate' => '2026-05-25',
        'text' => 'Everything was neat, efficient, and professionally handled.',
        'sourceRecordUrl' => 'https://records.example.invalid/review-kappa',
    ],
    [
        'stableId' => 'review-lambda',
        'author' => 'Robin C.',
        'rating' => 5,
        'publishedDate' => '2026-05-20',
        'text' => 'A smooth experience from scheduling through the completed repair.',
        'sourceRecordUrl' => 'https://records.example.invalid/review-lambda',
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
$dialogBookingAction = [
    'mode' => 'dialog',
    'experienceHtml' => '<div id="booking-dialog-fixture" class="twins-brand-booking-dialog" data-twins-booking-dialog hidden><div role="dialog" aria-modal="true" aria-labelledby="twins-brand-booking-title"><button type="button" data-booking-close aria-label="Close booking preview">Close</button><h2 id="twins-brand-booking-title">Book with Twins</h2><p>Choose a convenient time after this experience moves to production.</p><button type="button" data-booking-finalize>Continue on staging</button><p role="status" hidden data-booking-status>Booking is intentionally disabled on this private staging copy.</p></div></div>',
];
$externalBookingAction = [
    'mode' => 'external',
    'href' => RendererHarnessBookingAdapter::EXTERNAL_URL,
    'target' => '_blank',
    'rel' => 'noopener noreferrer',
];

[$stagingExperience] = $makeExperience($verifiedCollection, $dialogBookingAction);
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

[$productionExperience] = $makeExperience($verifiedCollection, $externalBookingAction);
$productionHeader = $productionExperience->renderHeader(['environment' => 'production', 'market' => 'main']);
$expect(substr_count($productionHeader, RendererHarnessBookingAdapter::EXTERNAL_URL) === 2, 'external booking URL must render in both approved header actions');
$expect(substr_count($productionHeader, 'target="_blank" rel="noopener noreferrer"') === 2, 'external booking actions must emit exact safe target and rel values');
$expect(substr_count($productionHeader, 'data-twins-booking-open') === 0, 'external booking mode rendered dialog triggers');
$expect(strpos($productionHeader, 'booking-dialog-fixture') === false, 'external booking mode rendered dialog HTML');
$expect(strpos($productionHeader, 'Illinois preview') === false, 'production header exposed Illinois preview');

$invalidBookingCases = [
    'staging-missing-mode' => ['environment' => 'staging', 'action' => []],
    'staging-malformed-mode' => ['environment' => 'staging', 'action' => ['mode' => ['dialog']]],
    'staging-missing-dialog-html' => ['environment' => 'staging', 'action' => ['mode' => 'dialog']],
    'staging-malformed-dialog-html' => ['environment' => 'staging', 'action' => ['mode' => 'dialog', 'experienceHtml' => []]],
    'staging-external-mode-mismatch' => ['environment' => 'staging', 'action' => $externalBookingAction],
    'production-missing-mode' => ['environment' => 'production', 'action' => []],
    'production-dialog-mode-mismatch' => ['environment' => 'production', 'action' => $dialogBookingAction],
    'production-missing-external-href' => ['environment' => 'production', 'action' => ['mode' => 'external', 'target' => '_blank', 'rel' => 'noopener noreferrer']],
    'production-malformed-external-href' => ['environment' => 'production', 'action' => ['mode' => 'external', 'href' => [], 'target' => '_blank', 'rel' => 'noopener noreferrer']],
    'production-missing-external-target' => ['environment' => 'production', 'action' => ['mode' => 'external', 'href' => RendererHarnessBookingAdapter::EXTERNAL_URL, 'rel' => 'noopener noreferrer']],
    'production-wrong-external-target' => ['environment' => 'production', 'action' => ['mode' => 'external', 'href' => RendererHarnessBookingAdapter::EXTERNAL_URL, 'target' => '_self', 'rel' => 'noopener noreferrer']],
    'production-missing-external-rel' => ['environment' => 'production', 'action' => ['mode' => 'external', 'href' => RendererHarnessBookingAdapter::EXTERNAL_URL, 'target' => '_blank']],
    'production-wrong-external-rel' => ['environment' => 'production', 'action' => ['mode' => 'external', 'href' => RendererHarnessBookingAdapter::EXTERNAL_URL, 'target' => '_blank', 'rel' => 'opener']],
];
foreach ($invalidBookingCases as $scenario => $fixture) {
    [$invalidExperience, , $invalidBooking] = $makeExperience($verifiedCollection, $fixture['action']);
    $rejected = false;
    try {
        $invalidExperience->renderHeader(['environment' => $fixture['environment'], 'market' => 'main']);
    } catch (DomainException $expected) {
        $rejected = true;
    }
    $expect($invalidBooking->calls === 1, $scenario . ' did not reach the header component through the fake adapter');
    $expect($rejected, $scenario . ' was not rejected by the header component');
}

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

[$stagingReviewExperience, $stagingReviews] = $makeExperience($verifiedCollection, $dialogBookingAction);
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
}
$featuredRecords = array_slice($records, 0, 9);
foreach ($featuredRecords as $record) {
    $expect(strpos($stagingSlider, 'aria-label="' . $record['rating'] . ' out of 5 stars"') !== false, 'slider rating label drifted');
}
preg_match_all('/<article\b[^>]*>(.*?)<\/article>/s', $stagingSlider, $cards);
$expect(count($cards[1]) === 9, 'featured slider did not stay within nine records');
$expect(strpos($stagingSlider, $records[9]['author']) === false, 'featured slider rendered a record beyond its nine-record cap');
$expect(strpos($stagingSlider, '<details') === false, 'featured slider exposed a long-review disclosure');
foreach ($featuredRecords as $index => $record) {
    $card = $cards[1][$index];
    if ($record['stableId'] === 'review-beta') {
        $words = preg_split('/\s+/u', trim($record['text']));
        $expect($words !== false, 'long review words were unavailable');
        $expectedExcerpt = implode(' ', array_slice($words, 0, 42)) . '…';
        $expect(preg_match('/<p class="twins-brand-review-excerpt">(.*?)<\/p>/s', $card, $quoteMatch) === 1, 'featured long review excerpt is missing');
        $expect(html_entity_decode($quoteMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') === $expectedExcerpt, 'featured long review excerpt changed');
        $expect(strpos($card, htmlspecialchars($record['text'], ENT_QUOTES, 'UTF-8')) === false, 'featured long review exposed complete disclosure text');
    } else {
        $expect(preg_match('/<blockquote>(.*?)<\/blockquote>/s', $card, $quoteMatch) === 1, 'review text markup missing');
        $encodedText = preg_replace('/<br\s*\/?>/i', '', $quoteMatch[1]);
        $expect(html_entity_decode($encodedText, ENT_QUOTES | ENT_HTML5, 'UTF-8') === $record['text'], 'review text bytes changed');
    }
    $expect(preg_match('/<strong>(.*?)<\/strong>/s', $card, $authorMatch) === 1, 'review author markup missing');
    $expect(html_entity_decode($authorMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') === $record['author'], 'review author bytes changed');
    $expect(preg_match('/<time datetime="([^"]+)">(.*?)<\/time>/s', $card, $dateMatch) === 1, 'review date markup missing');
    $expect(html_entity_decode($dateMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') === $record['publishedDate'], 'review datetime bytes changed');
    $expect(html_entity_decode($dateMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') === $record['publishedDate'], 'visible review date bytes changed');
}

[$reviewsListExperience, $reviewsListProvider] = $makeExperience($verifiedCollection, $dialogBookingAction);
$reviewsList = $renderComponent($reviewsListExperience, $root . '/components/review-slider.php', [
    'environment' => 'staging',
    'marketKey' => 'main',
    'context' => ['classification' => 'reviews-brand'],
]);
$expect($reviewsListProvider->calls === 1, 'reviews list did not request the collection exactly once');
preg_match_all('/<article\b[^>]*>(.*?)<\/article>/s', $reviewsList, $reviewListCards);
$expect(count($reviewListCards[1]) === count($records), 'reviews list did not render the full verified collection');
$expect(strpos($reviewsList, 'data-twins-review-slider') === false, 'reviews list emitted featured autoplay markup');
$expect(strpos($reviewsList, '<details class="twins-brand-review-details">') !== false, 'reviews list omitted its long-review disclosure');
$expect(strpos($reviewsList, htmlspecialchars($longReviewText, ENT_QUOTES, 'UTF-8')) !== false, 'reviews list changed the complete long quote');

[$productionReviewExperience, $productionReviews] = $makeExperience($verifiedCollection, $externalBookingAction);
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
    [$variantExperience, $variantReviews] = $makeExperience($collection, $externalBookingAction);
    $variant = $renderComponent($variantExperience, $root . '/components/review-slider.php', [
        'environment' => 'production',
        'marketKey' => 'main',
    ]);
    $expect($variantReviews->calls === 1, $scenario . ' permission did not request the collection once');
    $expect(strpos($variant, $businessReviewsUrl) === false, $scenario . ' permission exposed the external business URL');
    $expect(strpos($variant, 'href="/reviews/"') !== false, $scenario . ' permission omitted the internal Reviews route');
}

[$unavailableExperience, $unavailableReviews] = $makeExperience(['status' => 'unavailable', 'reason' => 'private error 90210'], $dialogBookingAction);
$unavailable = $renderComponent($unavailableExperience, $root . '/components/review-slider.php', [
    'environment' => 'staging',
    'marketKey' => 'main',
]);
$expect($unavailableReviews->calls === 1, 'unavailable slider did not request the collection exactly once');
$expect(strpos($unavailable, 'Reviews are temporarily unavailable.') !== false, 'unavailable slider omitted its generic notice');
$expect(strpos($unavailable, 'private error 90210') === false, 'unavailable slider leaked provider detail');
$expect(strpos($unavailable, 'data-review-count') === false, 'unavailable slider emitted a numbered collection');

$bodyMethods = ['renderHome', 'renderTeam', 'renderCareers', 'renderContact', 'renderReviews'];
$stagingBodies = [];
foreach ($bodyMethods as $method) {
    $body = $stagingExperience->{$method}(['environment' => 'staging', 'market' => 'main']);
    $stagingBodies[$method] = $body;
    $expect(substr_count($body, 'id="twins-overhaul-main"') === 1, $method . ' must own exactly one main landmark');
    $expect(strpos($body, 'Request a Quote') !== false, $method . ' is missing exact quote copy');
    $expect(strpos($body, 'Get an Estimate') === false, $method . ' contains prohibited legacy quote copy');
}
$expect(substr_count($header, 'id="twins-overhaul-main"') === 0, 'header must not own the body main landmark');
$expect(substr_count($footer, 'id="twins-overhaul-main"') === 0, 'footer must not own the body main landmark');

$stagingDocuments = [];
foreach ($stagingBodies as $method => $body) {
    $document = $header . $body . $footer;
    $stagingDocuments[$method] = $document;
    $expect(substr_count($document, '<header class="twins-brand-header"') === 1, $method . ' composition duplicated the shared header');
    $expect(substr_count($document, '<footer class="twins-brand-footer"') === 1, $method . ' composition duplicated the shared footer');
    $expect(substr_count($document, 'id="twins-overhaul-main"') === 1, $method . ' composition duplicated the main landmark');
    $expect(substr_count($document, 'aria-label="Primary navigation"') === 1, $method . ' composition duplicated primary navigation');
}

$inertPatterns = [
    '/<form\b/i' => 'form element',
    '/type\s*=\s*["\'](?:submit|image)["\']/i' => 'submitting control',
    '/\sname\s*=/i' => 'named field',
    '/\sform\s*=/i' => 'form owner',
    '/formaction\s*=/i' => 'form action',
    '/https?:\/\//i' => 'external URL',
    '/fetch\s*\(/i' => 'fetch primitive',
    '/XMLHttpRequest/i' => 'XHR primitive',
    '/sendBeacon\s*\(/i' => 'beacon primitive',
];
$assertInertComposition = static function (string $document, string $scope) use ($inertPatterns, $expect): void {
    foreach ($inertPatterns as $pattern => $label) {
        $expect(preg_match($pattern, $document) === 0, $scope . ' exposed a staging ' . $label);
    }
};
foreach ($stagingDocuments as $method => $document) {
    $assertInertComposition($document, $method . ' composition');
}

$unsafeBookingFragments = [
    'form-element' => '<form></form>',
    'submit-control' => '<input type="submit">',
    'image-control' => '<input type="image">',
    'named-field' => '<input name="unsafe">',
    'form-owner' => '<input form="unsafe">',
    'form-action' => '<button type="button" formaction="/unsafe">Unsafe</button>',
    'external-url' => '<a href="https://unsafe.example">Unsafe</a>',
    'fetch' => '<script>fetch("/unsafe")</script>',
    'xhr' => '<script>new XMLHttpRequest()</script>',
    'beacon' => '<script>navigator.sendBeacon("/unsafe")</script>',
];
foreach ($unsafeBookingFragments as $scenario => $unsafeFragment) {
    [$unsafeBookingExperience] = $makeExperience($verifiedCollection, [
        'mode' => 'dialog',
        'experienceHtml' => '<div id="unsafe-booking-fixture">' . $unsafeFragment . '</div>',
    ]);
    $unsafeHeader = $unsafeBookingExperience->renderHeader(['environment' => 'staging', 'market' => 'main']);
    $unsafeDocument = $unsafeHeader . $stagingBodies['renderHome'] . $footer;
    $unsafeRejected = false;
    try {
        $assertInertComposition($unsafeDocument, 'unsafe booking ' . $scenario);
    } catch (RuntimeException $expectedFailure) {
        $unsafeRejected = true;
    }
    $expect($unsafeRejected, 'unsafe booking composition was not rejected: ' . $scenario);
}

$home = $stagingBodies['renderHome'];
$homeMarkers = ['brand-hero', 'trust-ribbon', 'service-pathways', 'review-slider', 'team-story', 'door-builder', 'market-selector', 'careers', 'final-cta'];
$homeCursor = -1;
foreach ($homeMarkers as $marker) {
    $next = strpos($home, $marker);
    $expect($next !== false && $next > $homeCursor, 'home section missing or out of order: ' . $marker);
    $homeCursor = $next;
}
$expect(strpos($home, 'Illinois preview') !== false, 'staging home omitted Illinois preview');
$productionHome = $productionExperience->renderHome(['environment' => 'production', 'market' => 'main']);
$expect(strpos($productionHome, 'Illinois preview') === false, 'production home exposed Illinois preview');
$expect(strpos($productionHome, 'Private staging preview') === false, 'production home exposed staging-only preview copy');
$productionCareers = $productionExperience->renderCareers(['environment' => 'production', 'market' => 'main']);
$expect(preg_match('/(?:staging|preview)/i', $productionCareers) === 0, 'production Careers exposed staging preview copy');
$expect(preg_match('/>\s*Apply\s*</', $productionCareers) === 1, 'production Careers omitted its non-preview navigation label');
$expect(preg_match('/>\s*Start your application\s*</', $productionCareers) === 1, 'production Careers omitted its non-preview hero action');
$expect(strpos($productionCareers, 'Give us the essentials') !== false, 'production Careers omitted its non-preview first step');
$expect(strpos($stagingBodies['renderCareers'], 'id="application-fixture"') !== false, 'careers did not delegate to the application adapter');
$expect(strpos($stagingBodies['renderCareers'], 'Application preview') !== false, 'staging Careers omitted its explicit preview navigation label');
$expect(strpos($stagingBodies['renderCareers'], 'Preview the application') !== false, 'staging Careers omitted its explicit preview hero action');
$expect(strpos($stagingBodies['renderCareers'], 'This private staging page') !== false, 'staging Careers omitted its inert-preview explanation');
$expect(strpos($stagingBodies['renderCareers'], 'Preview the essentials') !== false, 'staging Careers omitted its preview first step');
$expect(strpos($stagingBodies['renderContact'], 'id="quote-fixture"') !== false, 'contact did not delegate to the quote adapter');
foreach ($records as $record) {
    $encodedAuthor = htmlspecialchars($record['author'], ENT_QUOTES, 'UTF-8');
    $expect(strpos($stagingBodies['renderReviews'], $encodedAuthor) !== false, 'reviews page omitted verified record ' . $record['stableId']);
}
preg_match_all('/<article\b[^>]*>(.*?)<\/article>/s', $stagingBodies['renderReviews'], $renderedReviewCards);
$expect(count($renderedReviewCards[1]) === count($records), 'renderReviews did not retain the full verified collection');
$expect(strpos($stagingBodies['renderReviews'], htmlspecialchars($longReviewText, ENT_QUOTES, 'UTF-8')) !== false, 'renderReviews changed the complete long quote');

echo 'renderer-contracts-ok';
