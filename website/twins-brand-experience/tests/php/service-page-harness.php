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

// /protection-plans/ was retired into /maintenance-plans/ on 2026-08-27. The
// registry refuses the path outright (it left BESPOKE_PATHS and
// FALLBACK_TITLES together, so there is no generic stub either), which is what
// makes the host-side redirect the only way that URL can resolve.
foreach (['/protection-plans/', '/wi/protection-plans/', '/il/protection-plans/'] as $retiredPath) {
    $retiredContext = ['environment' => 'staging', 'market' => 'main', 'path' => $retiredPath, 'title' => 'TwinShield Protection Plans'];
    $refused = false;
    try { $make($retiredContext)->renderService($retiredContext); } catch (DomainException $expected) { $refused = true; }
    $expect($refused, 'the retired plan route still rendered: ' . $retiredPath);
}

// The TwinShield Protection Plan page: the three tiers, both payment options,
// the comparison table on the house scroll rail, the equipment credit worked
// examples, the limitations, and the tier offers in the page schema. Figures
// are asserted literally so a drifted rate fails here, not live.
$twinshieldContext = ['environment' => 'staging', 'market' => 'main', 'path' => '/maintenance-plans/', 'title' => 'TwinShield Protection Plan'];
$twinshieldPage = $make($twinshieldContext)->renderService($twinshieldContext);
$expect(substr_count($twinshieldPage, 'twins-brand-twinshield-card"') + substr_count($twinshieldPage, 'twins-brand-twinshield-card twins-brand-twinshield-card--lead') === 3, 'the TwinShield page must render exactly three tier cards');
$expect(substr_count($twinshieldPage, 'twins-brand-twinshield-card--lead') === 1, 'exactly one tier card is the inverted lead card');
$expect(strpos($twinshieldPage, 'BEST VALUE') === false, 'the tagline is copy, not a shouted literal');
$expect(strpos($twinshieldPage, 'Best Value') !== false, 'the featured tier lost the Housecall Pro tagline');
foreach ([
    '$12.99 a month for 12 months, $155.88 total, or $149 paid once.',
    '$18.99 a month for 12 months, $227.88 total, or $199 paid once.',
    '$24.99 a month for 12 months, $299.88 total, or $279 paid once.',
    '12 successful monthly payments earn $38.97. One $149 annual payment earns $37.25.',
    '12 successful monthly payments earn $79.76. One $199 annual payment earns $69.65.',
    '12 successful monthly payments earn $149.94. One $279 annual payment earns $139.50.',
    '25% of what you pay, up to $150.',
    '35% of what you pay, up to $300.',
    '50% of what you pay, up to $500.',
    'Monthly billing is a payment option for the full 12-month term, not a cancel-anytime membership.',
    'TwinShield is a maintenance-and-savings membership, not insurance or a repair/replacement warranty.',
    'Unused visits do not roll over.',
    'Discounts do not combine unless Twins approves otherwise in writing.',
    'Plan applies to one residential service address and one garage-door system.',
    'The Twins Garage Doors office must verify the exact balance before it is promised or applied.',
] as $twinshieldFigure) {
    $expect(strpos($twinshieldPage, htmlspecialchars($twinshieldFigure, ENT_QUOTES, 'UTF-8')) !== false, 'the TwinShield page lost: ' . $twinshieldFigure);
}
// The enrollment discount every tier shares is stated ONCE on the page, in the
// cue strip above the row, so the first bullet of each card is the figure that
// separates the tiers. The three schema offers still publish the full benefit
// list, so the rendered count is one plus three.
$expect(substr_count($twinshieldPage, '10% off the qualifying repair on the job where this new membership is purchased.') === 4, 'the shared enrollment discount is said once above the row and once in each tier offer');
$expect(substr_count($twinshieldPage, 'twins-brand-twinshield-shared') === 1, 'the shared enrollment discount lost its cue strip');
$expect(strpos($twinshieldPage, 'twins-brand-twinshield-card__benefits"><li>5% off future qualifying repairs') !== false, 'the Core card must open on its own discount, not on the shared one');
// The worked example rides with the rate on the card: the ceiling alone reads
// as a promise of the ceiling.
foreach (['38.97', '79.76', '149.94'] as $twinshieldEarned) {
    $expect(substr_count($twinshieldPage, '$' . $twinshieldEarned) === 1, 'the worked example belongs on the card exactly once: $' . $twinshieldEarned);
}
$expect(substr_count($twinshieldPage, 'twins-brand-twinshield-card__credit-example') === 3, 'every tier card states what its credit earns over a full term');
$expect(strpos($twinshieldPage, 'twins-brand-twinshield-credit-grid') === false, 'the credit callout row duplicated the cards and the table and is gone');
$expect(strpos($twinshieldPage, 'twins-l10-callout__title') === false, 'the credit callout row duplicated the cards and the table and is gone');
$expect(strpos($twinshieldPage, 'twins-l10-badge') === false, 'the term pill repeated the terms line directly above it');
$expect(strpos($twinshieldPage, 'role="region" aria-labelledby="twins-brand-twinshield-compare-title" tabindex="0"') !== false, 'the comparison table lost the house scroll rail');
$expect(substr_count($twinshieldPage, 'twins-brand-twinshield-table__lead') === 6, 'the featured column is marked in the header and all five rows');
$expect(strpos($twinshieldPage, 'Ask about Priority') !== false, 'the TwinShield cards lost their tier CTA');
$expect(strpos($twinshieldPage, '"hasOfferCatalog"') !== false, 'the TwinShield tiers are missing from the page schema');
$expect(substr_count($twinshieldPage, '"@type":"UnitPriceSpecification"') === 6, 'every tier publishes both payment options in schema');
$expect(strpos($twinshieldPage, '"price":12.99') !== false && strpos($twinshieldPage, '"price":149') !== false, 'the Core offer lost a price');
$expect(strpos($twinshieldPage, 'twins-brand-membership-card') === false, 'the thin membership block must not double up on the TwinShield page');
$expect(strpos($twinshieldPage, '$0.') === false, 'the TwinShield page abbreviated an amount');

// Every record renders on every market it is routed to.
foreach (['main', 'wi', 'ky', 'il-preview'] as $marketKey) {
    foreach ([
        '/garage-door-repair/', '/garage-door-installation/', '/garage-door-spring-repair/',
        '/garage-door-opener-repair/', '/emergency-garage-services/', '/garage-door-services/',
        '/garage-door-cable-repair/', '/garage-door-openers/', '/garage-weatherstripping-repair/',
        '/garage-door-tune-up/', '/maintenance-plans/', '/property-management-services/',
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
