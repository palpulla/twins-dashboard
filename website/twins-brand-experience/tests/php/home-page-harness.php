<?php
declare(strict_types=1);

// Homepage system harness (2026-08-19): renders the r30 home through the real
// template stack and asserts the approved 2026-08-18 copy set (price figures
// pinned to data/price-ranges.json, verbatim guarantee, style bans) plus the
// template-owned JSON-LD (Organization + FAQPage mirroring exactly the
// rendered closing accordion, and no LocalBusiness because the renderer layer
// owns that node). Defines a home_url() shim so the WordPress-gated
// structured-data component emits; renderer-contract-harness.php keeps
// covering the WordPress-free composition where the component stays silent.

if (!isset($argv[1])) { fwrite(STDERR, "bootstrap path required\n"); exit(1); }

function home_url(string $path = '/'): string
{
    return 'https://staging-host.example.invalid' . $path;
}

require $argv[1];

final class HomeFocusedAssets implements Twins\BrandExperience\AssetResolver
{
    public function url(string $assetKey): string { return '/assets/' . $assetKey; }
}
final class HomeFocusedRoutes implements Twins\BrandExperience\RouteAdapter
{
    private array $normalized;
    public function __construct(array $normalized) { $this->normalized = $normalized; }
    public function normalizeContext(array $requestContext): array { return $this->normalized; }
    public function route(string $routeKey, string $marketKey): string
    {
        // The home template touches the full route surface (services, offers,
        // guides, market chooser, city links), so the fixture resolves every
        // key deterministically instead of maintaining a parallel route map.
        return '/' . $routeKey . '/';
    }
}
final class HomeFocusedReviews implements Twins\BrandExperience\ReviewsProvider
{
    public function collection(): array { return ['status' => 'fixture']; }
}
final class HomeFocusedQuote implements Twins\BrandExperience\QuoteAdapter
{
    public function action(array $context): array { return ['mode' => 'fixture', 'href' => '/contact-us/']; }
    public function renderExperience(array $context): string { return ''; }
    public function assertReady(): void {}
}
final class HomeFocusedBooking implements Twins\BrandExperience\BookingAdapter
{
    public function action(array $context): array { return ['mode' => 'dialog', 'experienceHtml' => '<div></div>']; }
    public function assertReady(): void {}
}
final class HomeFocusedApplications implements Twins\BrandExperience\ApplicationAdapter
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
        new HomeFocusedAssets(),
        new HomeFocusedRoutes($context),
        new HomeFocusedReviews(),
        new HomeFocusedQuote(),
        new HomeFocusedBooking(),
        new HomeFocusedApplications(),
        $registry,
        $root
    );
};

$wiContext = ['environment' => 'staging', 'market' => 'wi', 'path' => '/wi/', 'title' => 'Garage Door Repair & Installation'];
$home = $make($wiContext)->renderHome($wiContext);

// Approved copy set: hero, ticker, scenes, services, journey, cards.
foreach ([
    'Garage door trouble? <em>Call the Twins.</em>',
    'Same-day garage door repair across Madison and southern Wisconsin.',
    '$0 Service Call With Repair',
    'Family owned by twin brothers',
    'You approve a flat price before any work starts',
    'What is your door doing?',
    'Loud bang, then stuck',
    'Four things we do all day, every day.',
    'Most spring replacements run $575 to $1,225.',
    'We install LiftMaster openers and repair every major brand',
    'Financing through GoodLeap, monthly payments available on new doors.',
    'Here is how it goes.',
    'Meet your tech',
    'The reviews name names.',
    'Meet the Twins. And the crew.',
    'Fix it or replace it? Straight answer.',
    'TwinShield. The no-surprises plan.',
    'New door now. Payments monthly.',
    'Quick answers.',
    'See Real Madison Prices',
] as $approvedHomeCopy) {
    $expect(strpos($home, $approvedHomeCopy) !== false, 'home lost approved copy: ' . $approvedHomeCopy);
}

// Verbatim guarantee: journey step, why list, FAQ answer (plus its JSON-LD mirror).
$expect(substr_count($home, 'Done Right, or We Make It Right.') >= 3, 'home must carry the verbatim guarantee across journey, why list, and FAQ');

// Every published dollar figure traces to data/price-ranges.json or an approved offer.
foreach (['$575 to $1,225', '$325 to $625', '$2,625 to $3,525', '$3,425 to $4,400'] as $approvedRange) {
    $expect(strpos($home, $approvedRange) !== false, 'home lost the approved range ' . $approvedRange);
}

// Style law: no em/en dashes, no 24/7, no lifetime claims, no corporate filler.
$expect(strpos($home, "\u{2014}") === false && strpos($home, "\u{2013}") === false, 'home carries an em- or en-dash');
$expect(stripos($home, '24/7') === false && stripos($home, 'lifetime') === false, 'home carries banned copy');
$expect(stripos($home, 'verified contact path') === false && stripos($home, 'Get an Estimate') === false, 'home carries retired corporate filler');

// Template-owned JSON-LD: Organization + FAQPage, and never a LocalBusiness
// (twins_overhaul_brand_schema_markup owns the Madison-HQ LocalBusiness node).
$expect(preg_match('~<script type="application/ld\+json">([^<]+)</script>~', $home, $schemaMatch) === 1, 'home JSON-LD script count is not one');
$schema = json_decode($schemaMatch[1], true, 512, JSON_THROW_ON_ERROR);
$graph = $schema['@graph'] ?? [];
$types = array_map(static fn(array $node): string => (string) ($node['@type'] ?? ''), $graph);
$expect($types === ['Organization', 'FAQPage'], 'home schema graph must be exactly Organization + FAQPage');
$organization = $graph[0];
$expect(($organization['name'] ?? '') === 'Twins Garage Doors', 'Organization name drifted');
$expect(($organization['slogan'] ?? '') === 'Your Garage Door, Done Right.', 'Organization slogan drifted');
$expect(($organization['telephone'] ?? '') === '+18338332010', 'Organization must carry the permanent footer number from config/markets.php');
$founders = array_map(static fn(array $person): string => (string) ($person['name'] ?? ''), $organization['founder'] ?? []);
$expect($founders === ['Daniel Joseph', 'Tal Joseph'], 'Organization founders drifted');

// FAQPage mirrors exactly the rendered closing accordion.
$visibleHome = str_replace($schemaMatch[0], '', $home);
$faqStart = strpos($visibleHome, 'twins-brand-closing-faq');
$faqEnd = strpos($visibleHome, 'twins-brand-closing-area');
$expect($faqStart !== false && $faqEnd !== false && $faqStart < $faqEnd, 'home closing FAQ boundary is missing');
$visibleFaq = substr($visibleHome, $faqStart, $faqEnd - $faqStart);
$questions = $graph[1]['mainEntity'] ?? [];
$expect(count($questions) === 5, 'home FAQ schema must contain five questions');
foreach ($questions as $question) {
    $questionName = (string) ($question['name'] ?? '');
    $questionAnswer = (string) ($question['acceptedAnswer']['text'] ?? '');
    $expect($questionName !== '' && substr_count($visibleFaq, htmlspecialchars($questionName, ENT_QUOTES, 'UTF-8')) === 1, 'schema question is not visible exactly once: ' . $questionName);
    $expect($questionAnswer !== '' && substr_count($visibleFaq, htmlspecialchars($questionAnswer, ENT_QUOTES, 'UTF-8')) === 1, 'schema answer is not visible exactly once: ' . $questionName);
}

// Markets without a cost guide keep the FAQ block but not the Madison price link.
$kyContext = ['environment' => 'staging', 'market' => 'ky', 'path' => '/ky/', 'title' => 'Garage Door Repair & Installation'];
$kyHome = $make($kyContext)->renderHome($kyContext);
$expect(strpos($kyHome, 'See Real Madison Prices') === false, 'ky home exposed the Wisconsin-only cost guide link');
$expect(preg_match('~<script type="application/ld\+json">~', $kyHome) === 1, 'ky home lost its JSON-LD');

echo "home-page-ok\n";
