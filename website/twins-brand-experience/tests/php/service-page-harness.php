<?php
declare(strict_types=1);

// Service-page system harness (2026-08-18): renders the 13 fixed records on
// every market through the real templates and asserts the template
// capabilities (answer facts, warning signs, cost section, gated safety
// module, service-tagged review quotes, related services, five-question FAQ,
// template-owned JSON-LD, verbatim guarantee). Runs standalone so the
// service system stays verifiable while renderer-contract-harness.php still
// carries pre-r30 chrome pins awaiting reconciliation.

if (!isset($argv[1])) { fwrite(STDERR, "bootstrap path required\n"); exit(1); }
require $argv[1];

final class FocusedAssets implements Twins\BrandExperience\AssetResolver
{
    public function url(string $assetKey): string { return '/assets/' . $assetKey; }
}
final class FocusedRoutes implements Twins\BrandExperience\RouteAdapter
{
    private const ROUTES = [
        'services' => '/garage-door-services/', 'repair' => '/garage-door-repair/',
        'installation' => '/garage-door-installation/', 'spring-repair' => '/garage-door-spring-repair/',
        'opener-repair' => '/garage-door-opener-repair/', 'emergency-service' => '/emergency-garage-services/',
        'garage-doors' => '/clopay-garage-doors/', 'door-builder' => '/door-builder/',
        'contact' => '/contact-us/', 'openers' => '/garage-door-openers/',
        'maintenance-plans' => '/maintenance-plans/', 'reviews' => '/reviews/',
    ];
    private array $normalized;
    public function __construct(array $normalized) { $this->normalized = $normalized; }
    public function normalizeContext(array $requestContext): array { return $this->normalized; }
    public function route(string $routeKey, string $marketKey): string
    {
        if (!isset(self::ROUTES[$routeKey])) throw new DomainException('Unknown focused route key: ' . $routeKey);
        return self::ROUTES[$routeKey];
    }
}
final class FocusedReviews implements Twins\BrandExperience\ReviewsProvider
{
    public function collection(): array { return ['status' => 'fixture']; }
}
final class FocusedQuote implements Twins\BrandExperience\QuoteAdapter
{
    public function action(array $context): array { return ['mode' => 'fixture', 'href' => '/contact-us/']; }
    public function renderExperience(array $context): string { return ''; }
    public function assertReady(): void {}
}
final class FocusedBooking implements Twins\BrandExperience\BookingAdapter
{
    public function action(array $context): array { return ['mode' => 'dialog', 'experienceHtml' => '<div></div>']; }
    public function assertReady(): void {}
}
final class FocusedApplications implements Twins\BrandExperience\ApplicationAdapter
{
    public function clientContract(array $context): array { return ['mode' => 'fixture']; }
    public function renderExperience(array $context): string { return ''; }
    public function assertReady(): void {}
}

$root = dirname($argv[1]);
$registry = new Twins\BrandExperience\MarketRegistry(require $root . '/config/markets.php');
$expect = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$make = static function (array $context) use ($registry, $root): Twins\BrandExperience\Experience {
    return new Twins\BrandExperience\Experience(
        new FocusedAssets(),
        new FocusedRoutes($context),
        new FocusedReviews(),
        new FocusedQuote(),
        new FocusedBooking(),
        new FocusedApplications(),
        $registry,
        $root
    );
};

$springContext = ['environment' => 'staging', 'market' => 'wi', 'path' => '/wi/garage-door-spring-repair/', 'title' => 'Garage Door Spring Repair'];
$springService = $make($springContext)->renderService($springContext);
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

$weatherContext = ['environment' => 'staging', 'market' => 'main', 'path' => '/garage-weatherstripping-repair/', 'title' => 'Weatherstripping Repair'];
$weatherService = $make($weatherContext)->renderService($weatherContext);
$expect(strpos($weatherService, 'twins-brand-service-safety') === false, 'the safety module leaked onto a non-tension page');
$expect(strpos($weatherService, 'twins-brand-membership-card') === false, 'membership cards leaked onto a service page');
$expect(strpos($weatherService, '"minPrice"') === false, 'weatherstripping schema invented a price range');
$expect(strpos($weatherService, 'least expensive repairs') !== false, 'weatherstripping page lost its approved cost framing');

$membershipContext = ['environment' => 'staging', 'market' => 'main', 'path' => '/protection-plans/', 'title' => 'TwinShield Protection Plans'];
$membershipService = $make($membershipContext)->renderService($membershipContext);
$expect(substr_count($membershipService, 'twins-brand-membership-card') >= 3, 'membership page must render three plan cards');
$expect(strpos($membershipService, 'twins-brand-membership-card--featured') !== false, 'membership page lost the recommended tier');
$expect(strpos($membershipService, '$12.99/mo or $149/yr') !== false, 'membership card lost the Core price line');
$expect(strpos($membershipService, 'Ask about Core') !== false, 'membership card lost the tier CTA');
$expect(strpos($membershipService, 'twins-brand-service-safety') === false, 'the safety module leaked onto the membership page');

// Every record renders on every market it is routed to.
foreach (['main', 'wi', 'ky', 'il-preview'] as $marketKey) {
    foreach ([
        '/garage-door-repair/', '/garage-door-installation/', '/garage-door-spring-repair/',
        '/garage-door-opener-repair/', '/emergency-garage-services/', '/garage-door-services/',
        '/garage-door-cable-repair/', '/garage-door-openers/', '/garage-weatherstripping-repair/',
        '/garage-door-tune-up/', '/maintenance-plans/', '/property-management-services/', '/protection-plans/',
    ] as $servicePath) {
        $context = ['environment' => 'staging', 'market' => $marketKey, 'path' => $servicePath, 'title' => 'Fixture'];
        $rendered = $make($context)->renderService($context);
        $expect(substr_count($rendered, '<h1') === 1, $marketKey . $servicePath . ' h1 count');
        $expect(substr_count($rendered, '<details') === 5, $marketKey . $servicePath . ' FAQ count');
        $expect(substr_count($rendered, 'twins-location-review-card') === 3, $marketKey . $servicePath . ' review quotes');
        $expect(strpos($rendered, 'Done Right, or We Make It Right.') !== false, $marketKey . $servicePath . ' guarantee');
        $expect(strpos($rendered, 'type="application/ld+json"') !== false, $marketKey . $servicePath . ' schema');
        $expect(strpos($rendered, '—') === false, $marketKey . $servicePath . ' em-dash leaked');
        $expect(stripos($rendered, '24/7') === false, $marketKey . $servicePath . ' 24/7 leaked');
        $expect(stripos($rendered, 'lifetime') === false, $marketKey . $servicePath . ' lifetime claim leaked');
    }
}

echo "service-page-ok\n";
