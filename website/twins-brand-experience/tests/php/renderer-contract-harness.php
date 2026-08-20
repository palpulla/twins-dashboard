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
        'daniel-portrait-original' => '/team/daniel-joseph.jpeg',
        'daniel-portrait-480w' => '/team/daniel-joseph-480w.webp',
        'daniel-portrait-768w' => '/team/daniel-joseph-768w.webp',
        'daniel-portrait-1066w' => '/team/daniel-joseph-1066w.webp',
        'charles-portrait-original' => '/team/charles-rue.jpeg',
        'charles-portrait-480w' => '/team/charles-rue-480w.webp',
        'charles-portrait-768w' => '/team/charles-rue-768w.webp',
        'charles-portrait-1066w' => '/team/charles-rue-1066w.webp',
        'maurice-portrait-original' => '/team/maurice-williams.jpeg',
        'maurice-portrait-480w' => '/team/maurice-williams-480w.webp',
        'maurice-portrait-768w' => '/team/maurice-williams-768w.webp',
        'maurice-portrait-1066w' => '/team/maurice-williams-1066w.webp',
        'nicholas-portrait-original' => '/team/nicholas-roccaforte.jpeg',
        'nicholas-portrait-480w' => '/team/nicholas-roccaforte-480w.webp',
        'nicholas-portrait-768w' => '/team/nicholas-roccaforte-768w.webp',
        'nicholas-portrait-1066w' => '/team/nicholas-roccaforte-1066w.webp',
        'door-builder-before-after' => '/door-builder/twins-before-after-install.webp',
        'opener-6690l' => '/openers/liftmaster-6690l.png',
        'opener-98022' => '/openers/liftmaster-98022.png',
        'truck-webp-320' => '/brand/twins-service-truck-cutout-320w.webp',
        'truck-webp-880' => '/brand/twins-service-truck-cutout-880w.webp',
    ];

    public function url(string $assetKey): string
    {
        if (!isset(self::ASSETS[$assetKey])) throw new DomainException('Unknown renderer asset key: ' . $assetKey);
        return self::ASSETS[$assetKey];
    }
}

final class RendererHarnessRouteAdapter implements Twins\BrandExperience\RouteAdapter
{
    private const KY_ROUTE_OVERRIDES = [
        'services' => '/ky/garage-door-services/',
        'repair' => '/ky/garage-door-repair/',
        'installation' => '/ky/garage-door-installation/',
        'opener-repair' => '/ky/garage-door-opener-repair/',
    ];

    private const ROUTES = [
        'home' => '/',
        'services' => '/garage-door-services/',
        'repair' => '/garage-door-repair/',
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
        'cable-repair' => '/garage-door-cable-repair/',
        'weatherstripping' => '/garage-weatherstripping-repair/',
        'maintenance-plans' => '/maintenance-plans/',
        'protection-plans' => '/protection-plans/',
        'property-management' => '/property-management-services/',
        'openers' => '/garage-door-openers/',
        'service-area' => '/locations/',
        'city-madison' => '/wi/location/madison/',
        'city-milwaukee' => '/wi/garage-door-repair-in-milwaukee-wi/',
        'city-wauwatosa' => '/wi/location/wauwatosa/',
        'city-waukesha' => '/wi/location/waukesha/',
        'city-brookfield' => '/wi/location/brookfield/',
        'city-new-berlin' => '/wi/location/new-berlin/',
        'city-greenfield' => '/wi/location/greenfield/',
        'city-oak-creek' => '/wi/location/oak-creek/',
        'city-belleville' => '/wi/location/belleville/',
        'city-cottage-grove' => '/wi/location/cottage-grove/',
        'city-cross-plains' => '/wi/location/cross-plains/',
        'city-deerfield' => '/wi/location/deerfield/',
        'city-deforest' => '/wi/location/deforest/',
        'city-edgerton' => '/wi/location/edgerton/',
        'city-evansville' => '/wi/location/evansville/',
        'city-fitchburg' => '/wi/location/fitchburg/',
        'city-fort-atkinson' => '/wi/location/fort-atkinson/',
        'city-janesville' => '/wi/location/janesville/',
        'city-marshall' => '/wi/location/marshall/',
        'city-mcfarland' => '/wi/location/mcfarland/',
        'city-middleton' => '/wi/location/middleton/',
        'city-milton' => '/wi/location/milton/',
        'city-monona' => '/wi/location/monona/',
        'city-oregon' => '/wi/location/oregon/',
        'city-prairie-du-sac' => '/wi/location/prairie-du-sac/',
        'city-sun-prairie' => '/wi/location/sun-prairie/',
        'city-verona' => '/wi/location/verona/',
        'city-baraboo' => '/wi/location/baraboo/',
        'city-barneveld' => '/wi/location/barneveld/',
        'city-beloit' => '/wi/location/beloit/',
        'city-brooklyn' => '/wi/location/brooklyn/',
        'city-cambridge' => '/wi/location/cambridge/',
        'city-columbus' => '/wi/location/columbus/',
        'city-fall-river' => '/wi/location/fall-river/',
        'city-lodi' => '/wi/location/lodi/',
        'city-monroe' => '/wi/location/monroe/',
        'city-mount-horeb' => '/wi/location/mount-horeb/',
        'city-new-glarus' => '/wi/location/new-glarus/',
        'city-pardeeville' => '/wi/location/pardeeville/',
        'city-portage' => '/wi/location/portage/',
        'city-reedsburg' => '/wi/location/reedsburg/',
        'city-rio' => '/wi/location/rio/',
        'city-sauk-city' => '/wi/location/sauk-city/',
        'city-stoughton' => '/wi/location/stoughton/',
        'city-watertown' => '/wi/location/watertown/',
        'city-waunakee' => '/wi/location/waunakee/',
        'city-windsor' => '/wi/location/windsor/',
        'city-lexington' => '/ky/location/lexington/',
        'city-rockford' => '/il/location/rockford/',
        'city-loves-park' => '/il/location/loves-park/',
        'city-machesney-park' => '/il/location/machesney-park/',
        'city-belvidere' => '/il/location/belvidere/',
        'city-roscoe' => '/il/location/roscoe/',
        'city-rockton' => '/il/location/rockton/',
        'city-cherry-valley' => '/il/location/cherry-valley/',
        'city-poplar-grove' => '/il/location/poplar-grove/',
        'city-south-beloit' => '/il/location/south-beloit/',
        'city-winnebago' => '/il/location/winnebago/',
        'city-byron' => '/il/location/byron/',
        'city-caledonia' => '/il/location/caledonia/',
    ];

    public function normalizeContext(array $requestContext): array { return $requestContext; }

    public function route(string $routeKey, string $marketKey): string
    {
        if ($marketKey === 'ky' && $routeKey === 'service-area') {
            throw new DomainException('Kentucky does not expose a service-area route.');
        }
        if ($marketKey === 'ky' && isset(self::KY_ROUTE_OVERRIDES[$routeKey])) {
            return self::KY_ROUTE_OVERRIDES[$routeKey];
        }
        if (!isset(self::ROUTES[$routeKey])) throw new DomainException('Unknown renderer route key: ' . $routeKey);
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
$expect(substr_count($header, 'Book Garage Door Service') >= 1, 'header is missing the booking CTA');
$expect(strpos($header, 'Our Team') !== false, 'header is missing team route');
$expect(strpos($header, 'Garage Door Repair') !== false, 'header is missing repair route');
$expect(strpos($header, 'Wisconsin Garage Door Cost Guide') !== false, 'header is missing the qualified cost-guide label');
$expect(strpos($header, 'Get an Estimate') === false, 'header contains prohibited legacy CTA');
$expect(strpos($header, 'https://twinsgaragedoors.com') === false, 'header hard-coded the production host');
$expect(strpos($header, 'book.housecallpro.com') === false, 'staging header exposed a live booking host');
$expect(substr_count($header, 'data-twins-booking-open') === 3, 'dialog mode must render three button triggers');
$expect(substr_count($header, '<button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Garage Door Service</button>') === 1, 'dialog mode rendered a non-button booking action');
$expect(substr_count($header, 'id="booking-dialog-fixture"') === 1, 'dialog experience must render exactly once');
$expect(strpos($header, 'Illinois') !== false, 'staging header omitted Illinois');
$expect(strpos($header, 'Private staging preview') !== false, 'staging header omitted the Illinois preview marker');

$locationHeader = $stagingExperience->renderHeader([
    'environment' => 'staging',
    'market' => 'wi',
    'path' => '/wi/location/madison/',
    'classification' => 'location',
]);
// Owner call 2026-07-23: location pages keep the familiar full chrome
// (48196a4c); only the quote CTA label changes. The compact
// twins-brand-header--location variant from 8ca0f4ec was walked back.
$expect(strpos($locationHeader, 'class="twins-brand-header"') !== false, 'location header did not use the familiar header chrome');
$expect(strpos($locationHeader, 'twins-brand-header--location') === false, 'location header selected the retired compact variant');
$expect(strpos($locationHeader, 'twins-brand-market') !== false || strpos($locationHeader, 'Choose your service area') !== false, 'location header lost the market chooser');
$expect(strpos($locationHeader, 'Book Garage Door Service') !== false, 'location header lost the booking CTA');
$expect(substr_count($locationHeader, 'id="booking-dialog-fixture"') === 1, 'location header must render the dialog experience exactly once');
foreach ([
    'Garage Door Repair' => '/garage-door-repair/',
    'Garage Door Installation' => '/garage-door-installation/',
    'Garage Door Openers' => '/garage-door-openers/',
] as $label => $route) {
    $expect(substr_count($locationHeader, $label) === 2, 'location header did not render ' . $label . ' in both desktop and drawer navigation');
    $expect(substr_count($locationHeader, 'href="' . $route . '"') === 2, 'location header did not use the normalized ' . $label . ' route');
}
$expect(substr_count($locationHeader, 'Get a Free Quote') === 0, 'the quote action moved into the page body; header must not render it');
$expect(strpos($locationHeader, 'Request a Quote') === false, 'location header retained the non-location quote label');
$expect(substr_count($locationHeader, 'tel:+16084202377') === 2, 'location header must render the normalized market phone in desktop and mobile actions');

[$productionExperience] = $makeExperience($verifiedCollection, $externalBookingAction);
$productionHeader = $productionExperience->renderHeader(['environment' => 'production', 'market' => 'main']);
$expect(substr_count($productionHeader, RendererHarnessBookingAdapter::EXTERNAL_URL) === 3, 'external booking URL must render in all approved header actions');
$expect(substr_count($productionHeader, 'target="_blank" rel="noopener noreferrer"') === 3, 'external booking actions must emit exact safe target and rel values');
$expect(substr_count($productionHeader, 'data-twins-booking-open') === 0, 'external booking mode rendered dialog triggers');
$expect(strpos($productionHeader, 'booking-dialog-fixture') === false, 'external booking mode rendered dialog HTML');
$expect(strpos($productionHeader, 'Illinois') === false, 'production header exposed Illinois');
$expect(strpos($productionHeader, 'Private staging preview') === false, 'production header exposed the staging preview marker');

$productionLocationHeader = $productionExperience->renderHeader([
    'environment' => 'production',
    'market' => 'wi',
    'path' => '/wi/location/madison/',
    'classification' => 'location',
]);
// Familiar chrome on production location pages too: external booking renders
// exactly like the main production header (owner call 2026-07-23).
$expect(substr_count($productionLocationHeader, RendererHarnessBookingAdapter::EXTERNAL_URL) === 3, 'production location header must render the external booking URL in all approved actions');
$expect(strpos($productionLocationHeader, 'Book Garage Door Service') !== false, 'production location header lost the booking CTA');
$expect(strpos($productionLocationHeader, 'twins-brand-header--location') === false, 'production location header selected the retired compact variant');
$expect(substr_count($productionLocationHeader, 'Get a Free Quote') === 0, 'the quote action moved into the page body; production location header must not render it');

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
$expect(substr_count($footer, 'Request a Quote') >= 1, 'footer must carry the exact quote CTA');
$expect(strpos($footer, 'Call Now') !== false, 'footer is missing the call action');
$expect(strpos($footer, 'https://twinsgaragedoors.com') === false, 'footer hard-coded the production host');
$expect(strpos($footer, 'danielj140.sg-host.com') === false, 'footer hard-coded the staging host');
// Kentucky retired (Brand Toolkit v1.0). The footer Service Area column enumerates
// MarketRegistry::selectable(), so /ky/ is gone and /il/ takes its place. The
// stronger pin is the negative one below: a retired market must not be advertised
// anywhere in the shared chrome, which is a better guarantee than "KY is listed".
foreach (['/garage-door-services/', '/garage-door-repair/', '/garage-door-installation/', '/garage-door-spring-repair/', '/wi/', '/il/'] as $route) {
    $expect(strpos($footer, $route) !== false, 'footer omitted internal route ' . $route);
}
$expect(strpos($footer, '/ky/') === false, 'footer advertised the retired Kentucky market');
$expect(stripos($footer, 'Kentucky') === false, 'footer named the retired Kentucky market');
$rockfordFooter = $stagingExperience->renderFooter([
    'environment' => 'staging',
    'market' => 'il-preview',
    'path' => '/il/location/rockford/',
    'classification' => 'location',
]);
$expect(
    strpos($rockfordFooter, '5758 Elaine Dr, Rockford, IL 61108') !== false,
    'Rockford footer omitted the local branch address'
);
$expect(strpos($rockfordFooter, 'Call Now') !== false, 'location footer is missing Call Now');
$expect(substr_count($rockfordFooter, 'Get a Free Quote') >= 1, 'location footer must repeat Get a Free Quote');
$rockfordFooterGroups = [];
preg_match_all('~<div class="twins-brand-footer-group">([\s\S]*?)</div>~', $rockfordFooter, $rockfordFooterGroups);
$expect(count($rockfordFooterGroups[1] ?? []) === 3, 'Rockford footer must render the three compact il-preview groups');
// Group 1 is Service Area: Wisconsin + Illinois. It was 3 while retired Kentucky
// was still enumerated by MarketRegistry::all().
foreach ([4, 2, 4] as $index => $expectedLinkCount) {
    $expect(
        substr_count($rockfordFooterGroups[1][$index] ?? '', '<a ') === $expectedLinkCount,
        'Rockford footer group ' . $index . ' did not match the il-preview link count'
    );
}
$expect(stripos($rockfordFooter, 'Kentucky') === false, 'Rockford footer named the retired Kentucky market');
$expect(strpos($rockfordFooter, '>Spring Repair</a>') === false, 'Rockford footer exposed an unsupported service link');
$expect(strpos($rockfordFooter, '>Wisconsin Garage Door Cost Guide</a>') === false, 'Rockford footer exposed the Wisconsin-only cost guide');

$lexingtonFooter = $stagingExperience->renderFooter([
    'environment' => 'staging',
    'market' => 'ky',
    'path' => '/ky/location/lexington/',
    'classification' => 'location',
]);
$expect(strpos($lexingtonFooter, 'Call Now') !== false, 'Lexington location footer is missing Call Now');
$expect(substr_count($lexingtonFooter, 'Get a Free Quote') >= 1, 'Lexington location footer must repeat Get a Free Quote');
$expect(strpos($lexingtonFooter, 'Call Twins') === false, 'Lexington location footer retained the non-location call label');

$lexingtonLocation = $stagingExperience->renderEditorial([
    'environment' => 'staging',
    'market' => 'ky',
    'path' => '/ky/location/lexington/',
    'title' => 'Lexington',
    'classification' => 'location',
], '<p>LEGACY LEXINGTON LOCATION BODY</p>', 'location');
$lexingtonServiceLinks = [];
preg_match_all('~<a class="twins-location-service-link" href="([^"]+)">~', $lexingtonLocation, $lexingtonServiceLinks);
// r29 location body ships three market-local service cards; the fourth
// all-services card and its fallback copy were removed in the rebuild.
$expect(
    ($lexingtonServiceLinks[1] ?? []) === [
        '/ky/garage-door-repair/',
        '/ky/garage-door-opener-repair/',
        '/ky/garage-door-installation/',
    ],
    'Lexington pathway did not retain Kentucky service destinations'
);
$expect(strpos($lexingtonLocation, 'href="/wi/') === false, 'Lexington body emitted a Wisconsin destination');
$expect(strpos($lexingtonLocation, 'The right service for the door you have.') !== false, 'Lexington omitted the location services section');
$expect(strpos($lexingtonLocation, 'href="/ky/maintenance-plans/"') === false, 'Lexington linked the unsupported Kentucky maintenance route');
$expect(strpos($lexingtonLocation, 'twins-location-nearby-grid') === false, 'Lexington invented nearby Kentucky towns');
$expect(strpos($lexingtonLocation, 'LEGACY LEXINGTON LOCATION BODY') === false, 'Lexington rendered the preserved legacy body');

$illinoisService = $stagingExperience->renderService([
    'environment' => 'staging',
    'market' => 'il-preview',
    'path' => '/garage-door-repair/',
    'title' => 'Garage Door Repair',
]);
$expect(strpos($illinoisService, '>Garage Door Spring Repair</a>') === false, 'Illinois service links retained the misleading Spring Repair label');
$expect(strpos($illinoisService, '>Garage Door Openers</a>') !== false, 'Illinois service links omitted the Garage Door Openers destination label');

// 2026-08-18 service-page system: answer facts, warning signs, real-price
// cost section, gated safety module, service-tagged review quotes, related
// services, five-question FAQ, and template-owned JSON-LD.
$springService = $stagingExperience->renderService([
    'environment' => 'staging',
    'market' => 'wi',
    'path' => '/wi/garage-door-spring-repair/',
    'title' => 'Garage Door Spring Repair',
]);
foreach ([
    'twins-brand-service-facts',
    'twins-brand-service-signs',
    'twins-brand-service-cost',
    'twins-brand-service-safety-section',
    'twins-brand-service-reviews',
    'twins-brand-service-related',
] as $serviceSection) {
    $expect(strpos($springService, $serviceSection) !== false, 'spring service page omitted ' . $serviceSection);
}
$expect(substr_count($springService, 'Done Right, or We Make It Right.') === 2, 'spring page must carry the verbatim guarantee in the answer block and the process card');
$expect(strpos($springService, 'Typical cost') !== false, 'spring page omitted the cost fact chip');
$expect(strpos($springService, 'When to call') !== false, 'spring page omitted the when-to-call fact chip');
$expect(strpos($springService, '$575') !== false && strpos($springService, '$1,225') !== false, 'spring page lost the approved price range');
$expect(strpos($springService, 'dangerous tension') !== false && strpos($springService, 'trained professionals') !== false, 'spring page lost the mandatory safety framing');
$expect(substr_count($springService, '<details') === 5, 'spring page must render exactly five FAQ items');
$expect(substr_count($springService, 'twins-location-review-card') === 3, 'spring page must render three verbatim review quotes');
$expect(strpos($springService, 'Verbatim Google reviews from real Twins customers') !== false, 'spring review quotes lost their attribution');
$expect(strpos($springService, 'fixed same day') !== false, 'spring page did not pick from the springs-tagged quote pool');
$expect(strpos($springService, 'type="application/ld+json"') !== false, 'spring page omitted its JSON-LD script');
$expect(strpos($springService, '"@type":"Service"') !== false, 'spring schema omitted the Service node');
$expect(strpos($springService, '"@type":"BreadcrumbList"') !== false, 'spring schema omitted the BreadcrumbList node');
$expect(strpos($springService, '"@type":"FAQPage"') !== false, 'spring schema omitted the FAQPage node');
$expect(strpos($springService, '"minPrice":575') !== false && strpos($springService, '"maxPrice":1225') !== false, 'spring schema offers lost the approved range');
$expect(strpos($springService, 'Can I replace garage door springs myself?') !== false, 'spring schema and accordion lost an approved FAQ');
$expect(substr_count($springService, 'Can I replace garage door springs myself?') === 2, 'spring FAQPage must mirror the rendered accordion');
$expect(strpos($springService, 'href="/wi/maintenance-plans/"') === false, 'wi maintenance link must use the shared main route');
$expect(strpos($springService, 'href="/maintenance-plans/"') !== false, 'spring page omitted the maintenance related link');

$weatherService = $stagingExperience->renderService([
    'environment' => 'staging',
    'market' => 'main',
    'path' => '/garage-weatherstripping-repair/',
    'title' => 'Weatherstripping Repair',
]);
$expect(strpos($weatherService, 'twins-brand-service-safety') === false, 'the safety module leaked onto a non-tension page');
$expect(strpos($weatherService, 'twins-brand-membership-card') === false, 'membership cards leaked onto a service page');
$expect(strpos($weatherService, '"minPrice"') === false, 'weatherstripping schema invented a price range');
$expect(strpos($weatherService, 'least expensive repairs') !== false, 'weatherstripping page lost its approved cost framing');

$membershipService = $stagingExperience->renderService([
    'environment' => 'staging',
    'market' => 'main',
    'path' => '/protection-plans/',
    'title' => 'TwinShield Protection Plans',
]);
$expect(substr_count($membershipService, 'twins-brand-membership-card') >= 3, 'membership page must render three plan cards');
$expect(strpos($membershipService, 'twins-brand-membership-card--featured') !== false, 'membership page lost the recommended tier');
$expect(strpos($membershipService, '$12.99/mo or $149/yr') !== false, 'membership card lost the Core price line');
$expect(strpos($membershipService, 'Ask about Core') !== false, 'membership card lost the tier CTA');
$expect(strpos($membershipService, 'twins-brand-service-safety') === false, 'the safety module leaked onto the membership page');

$madisonLocation = $stagingExperience->renderEditorial([
    'environment' => 'staging',
    'market' => 'main',
    'path' => '/wi/location/madison/',
    'title' => 'Madison',
], '<p>LEGACY MADISON LOCATION BODY</p>', 'location');
$expect(
    strpos($madisonLocation, 'This is our home turf. Our shop sits just off the Beltline on Madison') !== false,
    'Madison location intro did not replace the shared default'
);
$expect(
    strpos($madisonLocation, htmlspecialchars('Madison\'s housing makes for interesting garage door work.', ENT_QUOTES, 'UTF-8')) !== false,
    'Madison location notes were not rendered'
);
$expect(
    strpos($madisonLocation, 'How much does it cost to repair a garage door in Madison?') !== false
        && strpos($madisonLocation, 'Can I replace garage door springs myself?') !== false,
    'Madison location FAQs were not rendered'
);
$expect(strpos($madisonLocation, 'LEGACY MADISON LOCATION BODY') === false, 'Madison location rendered the preserved legacy body');

$milwaukeeLocation = $stagingExperience->renderEditorial([
    'environment' => 'staging',
    'market' => 'main',
    'path' => '/wi/garage-door-repair-in-milwaukee-wi/',
    'title' => 'Garage Door Repair in Milwaukee',
], '<p>LEGACY MILWAUKEE LOCATION BODY</p>', 'location');
$expect(
    strpos($milwaukeeLocation, 'Our Milwaukee office sits on Burleigh Street in Wauwatosa, just west of Highway 100') !== false,
    'Milwaukee special route did not resolve its location content'
);
$expect(strpos($milwaukeeLocation, 'LEGACY MILWAUKEE LOCATION BODY') === false, 'Milwaukee location rendered the preserved legacy body');

$locationRecords = require $root . '/config/location-content.php';
$prohibitedLocationPhrases = [
    'recently opened',
    'newly opened',
    'new to this market',
    'new market',
    'recent addition',
    'recent expansion',
    'still building',
    'earn the local record',
];
foreach ($locationRecords as $slug => $record) {
    $path = $slug === 'milwaukee'
        ? '/wi/garage-door-repair-in-milwaukee-wi/'
        : '/' . ($record['metro'] === 'rockford' ? 'il' : 'wi') . '/location/' . $slug . '/';
    $renderedLocation = $stagingExperience->renderEditorial([
        'environment' => 'staging',
        'market' => 'main',
        'path' => $path,
        'title' => $record['label'],
    ], '<p>LEGACY LOCATION BODY FOR ' . $slug . '</p>', 'location');
    $recordNotes = is_array($record['localNotes']) ? $record['localNotes'] : [$record['localNotes']];
    foreach (array_merge([$record['intro']], $recordNotes) as $expectedCopy) {
        $expect(
            strpos($renderedLocation, htmlspecialchars($expectedCopy, ENT_QUOTES, 'UTF-8')) !== false,
            $slug . ' did not render its city copy'
        );
    }
    foreach ($record['faq'] as $faq) {
        $expect(
            strpos($renderedLocation, htmlspecialchars($faq['q'], ENT_QUOTES, 'UTF-8')) !== false
                && strpos($renderedLocation, htmlspecialchars($faq['a'], ENT_QUOTES, 'UTF-8')) !== false,
            $slug . ' did not render all of its FAQs'
        );
    }
    if (isset($record['mapEmbedUrl'])) {
        $expect(
            substr_count($renderedLocation, 'src="' . htmlspecialchars($record['mapEmbedUrl'], ENT_QUOTES, 'UTF-8') . '"') === 1,
            $slug . ' did not render its live map embed exactly once'
        );
        $expect(
            strpos($renderedLocation, 'Open the') === false,
            $slug . ' retained the placeholder map link'
        );
    }
    if (isset($record['neighbors'])) {
        $expect(
            substr_count($renderedLocation, '<nav class="twins-brand-location-links" aria-label="Nearby areas we serve">') === 1,
            $slug . ' did not render its neighbor links block'
        );
        foreach ($record['neighbors'] as $neighborSlug) {
            $neighborHrefNeedle = $neighborSlug === 'milwaukee'
                ? '/wi/garage-door-repair-in-milwaukee-wi/"'
                : '/location/' . $neighborSlug . '/"';
            $expect(
                strpos($renderedLocation, $neighborHrefNeedle) !== false,
                $slug . ' did not link neighbor ' . $neighborSlug
            );
        }
    }
    $expect(
        substr_count($renderedLocation, 'class="twins-location-review-card"') === 3,
        $slug . ' did not render three verbatim review quotes'
    );
    $expect(
        strpos($renderedLocation, 'aria-label="Helpful garage door guides"') !== false
            && strpos($renderedLocation, '/is-it-cheaper-to-repair-or-replace-a-garage-door/') !== false
            && strpos($renderedLocation, '/belt-drive-vs-chain-drive-openers/') !== false,
        $slug . ' did not render the helpful-resources block'
    );
    $expectedCostGuide = $record['metro'] === 'milwaukee'
        ? '/wi/garage-door-cost-in-milwaukee-wi/'
        : '/wi/garage-door-cost-in-madison-wi/';
    $expect(
        strpos($renderedLocation, 'href="' . $expectedCostGuide . '"') !== false,
        $slug . ' did not link its metro cost guide'
    );
    if (isset($record['lat'], $record['lng'])) {
        $expect(
            substr_count($renderedLocation, '<script type="application/ld+json">') === 1,
            $slug . ' did not emit its structured data exactly once'
        );
        $schemaMatch = [];
        $expect(
            preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $renderedLocation, $schemaMatch) === 1,
            $slug . ' structured data is unreadable'
        );
        $schemaGraph = json_decode($schemaMatch[1], true);
        $expect(
            is_array($schemaGraph) && isset($schemaGraph['@graph']) && count($schemaGraph['@graph']) === 3,
            $slug . ' structured data graph drifted'
        );
        [$schemaBusiness, $schemaBreadcrumb, $schemaFaqPage] = $schemaGraph['@graph'];
        $expect($schemaBusiness['@type'] === 'HomeAndConstructionBusiness', $slug . ' schema business type drifted');
        $expect($schemaBusiness['geo']['latitude'] === $record['lat'] && $schemaBusiness['geo']['longitude'] === $record['lng'], $slug . ' schema coordinates drifted');
        $expect($schemaBusiness['areaServed']['name'] === $record['label'], $slug . ' schema areaServed drifted');
        $expect($schemaBreadcrumb['@type'] === 'BreadcrumbList' && count($schemaBreadcrumb['itemListElement']) === 4, $slug . ' schema breadcrumb drifted');
        $expect($schemaFaqPage['@type'] === 'FAQPage' && count($schemaFaqPage['mainEntity']) === count($record['faq']), $slug . ' schema FAQPage does not mirror the rendered FAQs');
        foreach ($schemaFaqPage['mainEntity'] as $faqIndex => $schemaQuestion) {
            $expect(
                $schemaQuestion['name'] === $record['faq'][$faqIndex]['q']
                    && $schemaQuestion['acceptedAnswer']['text'] === $record['faq'][$faqIndex]['a'],
                $slug . ' schema FAQPage question ' . $faqIndex . ' drifted'
            );
        }
    }
    foreach (['twins-location-hero', 'twins-location-trust', 'twins-location-services', 'twins-location-local-proof', 'twins-location-final-cta'] as $className) {
        $expect(strpos($renderedLocation, $className) !== false, $slug . ' omitted ' . $className);
    }
    foreach (['twins-location-system', 'twins-location-process', 'twins-location-branch', 'twins-location-nearby', 'twins-location-faq'] as $retiredClass) {
        $expect(strpos($renderedLocation, $retiredClass) === false, $slug . ' retained ' . $retiredClass);
    }
    $expect(substr_count($renderedLocation, 'class="twins-location-service-card') === 3, $slug . ' did not render three service choices');
    $expect(strpos($renderedLocation, 'Preventive maintenance') === false, $slug . ' retained the fourth service module');
    $expect(substr_count($renderedLocation, '/door-builder/twins-before-after-install.webp') === 1, $slug . ' did not render the owned local-proof photo exactly once');
    foreach (['services', 'final-left', 'final-right'] as $placement) {
        $expect(
            substr_count($renderedLocation, 'twins-location-twin--' . $placement) === 1,
            $slug . ' did not render exactly one ' . $placement . ' Twin'
        );
    }
    $expect(strpos($renderedLocation, 'twins-location-twin--hero') === false, $slug . ' retained the rejected hero Twin');
    $expect(strpos($renderedLocation, 'twins-location-twin--guidance') === false, $slug . ' retained the removed guidance Twin');
    $expect(substr_count($renderedLocation, '/brand/twin-left.png') === 1, $slug . ' did not render one left Twin');
    $expect(substr_count($renderedLocation, '/brand/twin-right.png') === 2, $slug . ' did not render two right Twins');
    $expect(substr_count($renderedLocation, 'alt="" aria-hidden="true"') === 3, $slug . ' Twin accessibility markup drifted');
    $recordText = strtolower($record['intro'] . ' ' . implode(' ', (array) $record['localNotes']));
    foreach ($record['faq'] as $faq) {
        $recordText .= ' ' . strtolower($faq['q'] . ' ' . $faq['a']);
    }
    foreach ($prohibitedLocationPhrases as $phrase) {
        $expect(strpos($recordText, $phrase) === false, $slug . ' contains prohibited positioning: ' . $phrase);
    }
    $expect(strpos($renderedLocation, 'LEGACY LOCATION BODY FOR ' . $slug) === false, $slug . ' rendered its legacy body');
}

$rockfordLocation = $stagingExperience->renderEditorial([
    'environment' => 'staging',
    'market' => 'main',
    'path' => '/il/location/rockford/',
    'title' => 'Rockford',
], '<p>LEGACY ROCKFORD LOCATION BODY</p>', 'location');
foreach (['twins-location-hero', 'twins-location-trust', 'twins-location-services', 'twins-location-local-proof', 'twins-location-final-cta'] as $className) {
    $expect(strpos($rockfordLocation, $className) !== false, 'Rockford omitted ' . $className);
}
foreach (['twins-location-system', 'twins-location-process', 'twins-location-branch', 'twins-location-nearby', 'twins-location-faq'] as $retiredClass) {
    $expect(strpos($rockfordLocation, $retiredClass) === false, 'Rockford retained ' . $retiredClass);
}
$expect(substr_count($rockfordLocation, 'Explore Garage Door Repair</a>') === 1, 'Rockford duplicated the repair destination');
$expect(strpos($rockfordLocation, '5758 Elaine Dr, Rockford, IL 61108') !== false, 'Rockford omitted its branch address');
foreach (['recently opened', 'new to this market', 'earn the local record'] as $prohibitedCopy) {
    $expect(stripos($rockfordLocation, $prohibitedCopy) === false, 'Rockford rendered prohibited copy: ' . $prohibitedCopy);
}
$expect(strpos($rockfordLocation, 'LEGACY ROCKFORD LOCATION BODY') === false, 'Rockford location rendered the preserved legacy body');
foreach (['spring', 'keypad', 'door'] as $artKind) {
    $expect(
        strpos($rockfordLocation, 'twins-brand-door-art twins-brand-door-art--' . $artKind . ' twins-location-service-art') !== false,
        'Rockford omitted service art: ' . $artKind
    );
}
$expect(substr_count($rockfordLocation, 'class="twins-location-service-card') === 3, 'Rockford must render three service choices');
$expect(strpos($rockfordLocation, 'Preventive maintenance') === false, 'Rockford retained the fourth service module');
$expect(substr_count($rockfordLocation, '/door-builder/twins-before-after-install.webp') === 1, 'Rockford must render the owned local-proof photo exactly once');

$trustEditorial = $stagingExperience->renderEditorial([
    'environment' => 'staging',
    'market' => 'main',
    'path' => '/about-us/',
    'title' => 'About Twins Garage Doors',
], '<p>Trusted local garage door service.</p>', 'trust');
$expect(strpos($trustEditorial, 'twins-location-twin') === false, 'trust editorial rendered location Twin markup');

$articleEditorial = $stagingExperience->renderEditorial([
    'environment' => 'staging',
    'market' => 'main',
    'path' => '/blog/garage-door-guide/',
    'title' => 'Garage Door Guide',
], '<p>Garage door care guidance.</p>', 'article');
$expect(strpos($articleEditorial, 'twins-location-twin') === false, 'article editorial rendered location Twin markup');

$servicesTwin = $renderComponent($stagingExperience, $root . '/components/twin-character.php', [
    'character' => 'right',
    'placement' => 'services',
]);
$expect(strpos($servicesTwin, 'src="/brand/twin-right.png"') !== false, 'services Twin omitted the fixed right asset');
$expect(strpos($servicesTwin, 'class="twins-location-twin twins-location-twin--services twins-location-twin--right"') !== false, 'services Twin classes drifted');
$expect(strpos($servicesTwin, 'alt="" aria-hidden="true"') !== false, 'services Twin is not decorative');
$expect(strpos($servicesTwin, 'loading="lazy" decoding="async"') !== false, 'services Twin loading behavior drifted');

$invalidTwinCharacter = $renderComponent($stagingExperience, $root . '/components/twin-character.php', [
    'character' => 'center',
    'placement' => 'system',
]);
$expect($invalidTwinCharacter === '', 'Twin renderer accepted an unsupported character');

$invalidTwinPlacement = $renderComponent($stagingExperience, $root . '/components/twin-character.php', [
    'character' => 'left',
    'placement' => 'system',
]);
$expect($invalidTwinPlacement === '', 'Twin renderer accepted a retired placement');

foreach ([
    ['right', 'services', '/brand/twin-right.png'],
    ['left', 'final-left', '/brand/twin-left.png'],
    ['right', 'final-right', '/brand/twin-right.png'],
] as [$character, $placement, $asset]) {
    $twin = $renderComponent($stagingExperience, $root . '/components/twin-character.php', compact('character', 'placement'));
    $expect(strpos($twin, 'src="' . $asset . '"') !== false, 'Twin renderer rejected approved ' . $character . ' + ' . $placement . ' pair');
}
foreach ([
    ['left', 'services'],
    ['left', 'final-right'],
    ['right', 'final-left'],
    ['left', 'hero'],
    ['right', 'guidance'],
] as [$character, $placement]) {
    $twin = $renderComponent($stagingExperience, $root . '/components/twin-character.php', compact('character', 'placement'));
    $expect($twin === '', 'Twin renderer accepted unsupported ' . $character . ' + ' . $placement . ' pair');
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
// r30 tightened the featured slider to six records and a 30-word excerpt
// rendered as a quoted blockquote (list mode keeps 42 words).
$featuredRecords = array_slice($records, 0, 6);
foreach ($featuredRecords as $record) {
    $expect(strpos($stagingSlider, 'aria-label="' . $record['rating'] . ' out of 5 stars"') !== false, 'slider rating label drifted');
}
preg_match_all('/<article\b[^>]*>(.*?)<\/article>/s', $stagingSlider, $cards);
$expect(count($cards[1]) === 6, 'featured slider did not stay within six records');
$expect(strpos($stagingSlider, $records[6]['author']) === false, 'featured slider rendered a record beyond its six-record cap');
$expect(strpos($stagingSlider, '<details') === false, 'featured slider exposed a long-review disclosure');
foreach ($featuredRecords as $index => $record) {
    $card = $cards[1][$index];
    if ($record['stableId'] === 'review-beta') {
        $words = preg_split('/\s+/u', trim($record['text']));
        $expect($words !== false, 'long review words were unavailable');
        $expectedExcerpt = implode(' ', array_slice($words, 0, 30)) . '…';
        $expect(preg_match('/<blockquote class="twins-brand-review-excerpt">(.*?)<\/blockquote>/s', $card, $quoteMatch) === 1, 'featured long review excerpt is missing');
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
    $expect($method === 'renderHome' ? strpos($body, 'Book Online') !== false : strpos($body, 'Request a Quote') !== false, $method . ' is missing its exact conversion copy');
    $expect(strpos($body, 'Get an Estimate') === false, $method . ' contains prohibited legacy quote copy');
}
$catalogProduct = [
    'id' => 'fixture',
    'title' => 'Fixture Steel',
    'showcase' => ['src' => '/clopay/fixture.webp', 'width' => 1190, 'height' => 692, 'alt' => 'Fixture steel reference'],
    'designs' => [],
    'colors' => [],
    'windows' => [],
    'glass' => [],
    'hardware' => [],
    'gallery' => [],
];
$catalogBody = $stagingExperience->renderCatalog(
    ['environment' => 'staging', 'market' => 'main'],
    ['mode' => 'product', 'product' => $catalogProduct, 'builderPath' => '/door-builder/']
);
$stagingBodies['renderCatalog'] = $catalogBody;
$expect(substr_count($catalogBody, 'id="twins-overhaul-main"') === 1, 'renderCatalog must own exactly one main landmark');
$expect(substr_count($catalogBody, '<h1') === 1, 'renderCatalog must own exactly one H1');
$expect(strpos($catalogBody, 'Request a Quote') !== false, 'renderCatalog is missing exact quote copy');
$expect(strpos($catalogBody, 'https://') === false, 'renderCatalog exposed a remote URL');
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
$homeMarkers = ['twins-brand-hero', 'twins-brand-company-story', 'twins-brand-service-showcase', 'twins-brand-review-proof', 'twins-brand-service-journey', 'twins-brand-why-scene', 'twins-brand-door-scene', 'twins-brand-membership-scene', 'twins-brand-closing-scene'];
$homeCursor = -1;
foreach ($homeMarkers as $marker) {
    $next = strpos($home, $marker);
    $expect($next !== false && $next > $homeCursor, 'home section missing or out of order: ' . $marker);
    $homeCursor = $next;
}
$expect(strpos($home, 'Illinois') !== false, 'staging home omitted Illinois');
// r30 home no longer carries the inline preview marker; the header market menu owns it.
$productionHome = $productionExperience->renderHome(['environment' => 'production', 'market' => 'main']);
$expect(strpos($productionHome, 'Illinois') === false, 'production home exposed Illinois');
$expect(strpos($productionHome, 'Private staging preview') === false, 'production home exposed the staging preview marker');
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
