<?php
declare(strict_types=1);

if ($argc !== 3 || !is_file($argv[1]) || !is_dir($argv[2])) {
    fwrite(STDERR, "STAGING_BRAND_ADAPTER_PATHS_MISSING\n");
    exit(2);
}

define('ABSPATH', __DIR__ . '/');
define('WP_ENVIRONMENT_TYPE', 'staging');
define('TWINS_STAGING_SAFETY', true);
define('DISABLE_WP_CRON', true);
define('WPMU_PLUGIN_DIR', dirname($argv[1]));

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$GLOBALS['twins_brand_adapter_hooks'] = [];
$GLOBALS['twins_brand_side_effects'] = [
    'shortcode' => 0,
    'remoteGet' => 0,
    'remotePost' => 0,
    'transient' => 0,
    'database' => 0,
];
$GLOBALS['twins_brand_network_home'] = 'https://stage.example.test/';

function wp_die($message, $title = '', $arguments = []): void
{
    unset($title, $arguments);
    throw new RuntimeException((string) $message);
}

function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1): bool
{
    $GLOBALS['twins_brand_adapter_hooks'][] = ['action', $hook, $callback, $priority, $acceptedArgs];
    return true;
}

function add_filter($hook, $callback, $priority = 10, $acceptedArgs = 1): bool
{
    $GLOBALS['twins_brand_adapter_hooks'][] = ['filter', $hook, $callback, $priority, $acceptedArgs];
    return true;
}

function content_url($path = ''): string
{
    return 'https://stage.example.test/wp-content/' . ltrim((string) $path, '/');
}

function network_home_url($path = ''): string
{
    return rtrim((string) $GLOBALS['twins_brand_network_home'], '/') . '/' . ltrim((string) $path, '/');
}

function do_shortcode($content): string
{
    $GLOBALS['twins_brand_side_effects']['shortcode']++;
    throw new RuntimeException('shortcode authority was invoked: ' . (string) $content);
}

function wp_remote_get($url, $arguments = [])
{
    unset($url, $arguments);
    $GLOBALS['twins_brand_side_effects']['remoteGet']++;
    throw new RuntimeException('remote GET authority was invoked');
}

function wp_remote_post($url, $arguments = [])
{
    unset($url, $arguments);
    $GLOBALS['twins_brand_side_effects']['remotePost']++;
    throw new RuntimeException('remote POST authority was invoked');
}

function get_transient($key)
{
    unset($key);
    $GLOBALS['twins_brand_side_effects']['transient']++;
    throw new RuntimeException('transient authority was invoked');
}

final class Twins_Brand_Throwing_Database
{
    public function __call(string $name, array $arguments)
    {
        unset($name, $arguments);
        $GLOBALS['twins_brand_side_effects']['database']++;
        throw new RuntimeException('database authority was invoked');
    }
}

$GLOBALS['wpdb'] = new Twins_Brand_Throwing_Database();

function twins_brand_harness_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function twins_brand_harness_throws(callable $operation, string $message): void
{
    $error = null;
    try {
        $operation();
    } catch (Throwable $caught) {
        $error = $caught;
    }
    twins_brand_harness_assert($error instanceof Throwable, $message);
}

require $argv[1];

$runtime = twins_overhaul_brand_runtime();
$assetBase = 'https://stage.example.test/wp-content/mu-plugins/twins-brand-experience';
$asset = new StagingAssetResolver($assetBase, 'https://stage.example.test/');
twins_brand_harness_assert(
    $asset->url('logo') === $assetBase . '/assets/images/brand/twins-logo.png',
    'valid same-origin asset did not resolve'
);
twins_brand_harness_throws(static fn(): string => $asset->url('caller/path.png'), 'unknown asset key was accepted');

foreach ([
    ['http://stage.example.test/core', 'https://stage.example.test/'],
    ['https://user@stage.example.test/core', 'https://stage.example.test/'],
    ['https://stage.example.test/core?x=1', 'https://stage.example.test/'],
    ['https://stage.example.test/core#x', 'https://stage.example.test/'],
    ['https://assets.example.test/core', 'https://stage.example.test/'],
    ['https://stage.example.test:444/core', 'https://stage.example.test/'],
] as [$base, $home]) {
    twins_brand_harness_throws(
        static fn(): StagingAssetResolver => new StagingAssetResolver($base, $home),
        'invalid staging asset origin was accepted: ' . $base
    );
}

$route = new StagingRouteAdapter();
twins_brand_harness_assert($route->normalizeContext(['key' => 'main']) === ['key' => 'main', 'environment' => 'staging', 'market' => 'main'], 'main context normalization changed');
twins_brand_harness_assert($route->normalizeContext(['key' => 'il']) === ['key' => 'il', 'environment' => 'staging', 'market' => 'il-preview'], 'Illinois preview normalization changed');
twins_brand_harness_assert($route->route('home', 'main') === 'https://stage.example.test/', 'main home route changed');
twins_brand_harness_assert($route->route('home', 'wi') === 'https://stage.example.test/wi/', 'Wisconsin home route changed');
twins_brand_harness_assert($route->route('contact', 'ky') === 'https://stage.example.test/ky/contact-us/', 'Kentucky contact route changed');
twins_brand_harness_throws(static fn(): array => $route->normalizeContext([]), 'missing context key was accepted');
twins_brand_harness_throws(static fn(): array => $route->normalizeContext(['key' => 'unknown']), 'unknown context key was accepted');
twins_brand_harness_throws(static fn(): array => $route->normalizeContext(['key' => 'main', 'environment' => 'production']), 'fake production context was accepted');
twins_brand_harness_throws(static fn(): string => $route->route('unknown', 'main'), 'unknown route key was accepted');
twins_brand_harness_throws(static fn(): string => $route->route('home', 'unknown'), 'unknown route market was accepted');

foreach ([
    'http://stage.example.test/',
    'https://user@stage.example.test/',
    'https://stage.example.test/?x=1',
    'https://stage.example.test/#x',
] as $invalidHome) {
    $GLOBALS['twins_brand_network_home'] = $invalidHome;
    twins_brand_harness_throws(static fn(): StagingRouteAdapter => new StagingRouteAdapter(), 'invalid network home was accepted: ' . $invalidHome);
}
$GLOBALS['twins_brand_network_home'] = 'https://stage.example.test/';
$originBoundQuote = new StagingQuoteAdapter();
$GLOBALS['twins_brand_network_home'] = 'https://other-stage.example.test/';
twins_brand_harness_throws(
    static fn(): array => $originBoundQuote->action(['key' => 'main', 'environment' => 'staging', 'market' => 'main']),
    'quote adapter followed a changed staging origin'
);
$GLOBALS['twins_brand_network_home'] = 'https://stage.example.test/';

final class Twins_Brand_Incomplete_Route_Adapter implements Twins\BrandExperience\RouteAdapter
{
    private array $normalized;
    public function __construct(array $normalized) { $this->normalized = $normalized; }
    public function normalizeContext(array $requestContext): array { unset($requestContext); return $this->normalized; }
    public function route(string $routeKey, string $marketKey): string { unset($routeKey, $marketKey); return '/never-reached/'; }
}

$corePath = rtrim($argv[2], '/');
foreach ([['environment' => 'staging'], ['market' => 'main']] as $incomplete) {
    $level = ob_get_level();
    $incompleteRuntime = new Twins\BrandExperience\Experience(
        $asset,
        new Twins_Brand_Incomplete_Route_Adapter($incomplete),
        new CapturedReviewsProvider($corePath . '/data/reviews/google-business-reviews-collection-2178.json'),
        new StagingQuoteAdapter(),
        new StagingBookingAdapter(),
        new StagingApplicationAdapter(),
        new Twins\BrandExperience\MarketRegistry(require $corePath . '/config/markets.php'),
        $corePath
    );
    twins_brand_harness_throws(static fn(): string => $incompleteRuntime->renderHome(['key' => 'main']), 'incomplete normalized context reached a template');
    twins_brand_harness_assert(ob_get_level() === $level, 'incomplete normalized context leaked an output buffer');
}

$malformedPath = tempnam(sys_get_temp_dir(), 'twins-brand-review-');
if (!is_string($malformedPath)) throw new RuntimeException('review fixture allocation failed');
file_put_contents($malformedPath, '{not-json');
twins_brand_harness_assert((new CapturedReviewsProvider($malformedPath))->collection() === ['status' => 'unavailable'], 'malformed review payload was accepted');
$symlinkPath = $malformedPath . '-link';
if (!symlink($malformedPath, $symlinkPath)) throw new RuntimeException('review symlink fixture failed');
twins_brand_harness_assert((new CapturedReviewsProvider($symlinkPath))->collection() === ['status' => 'unavailable'], 'review symlink was accepted');
$oversizePath = $malformedPath . '-oversize';
$oversizeHandle = fopen($oversizePath, 'wb');
if (!is_resource($oversizeHandle) || !ftruncate($oversizeHandle, 2097153)) throw new RuntimeException('oversize review fixture failed');
fclose($oversizeHandle);
twins_brand_harness_assert((new CapturedReviewsProvider($oversizePath))->collection() === ['status' => 'unavailable'], 'oversize review payload was accepted');
unlink($symlinkPath);
unlink($oversizePath);
unlink($malformedPath);

$quote = new StagingQuoteAdapter();
$booking = new StagingBookingAdapter();
$application = new StagingApplicationAdapter();
$quote->assertReady();
$booking->assertReady();
$application->assertReady();
$context = ['key' => 'main', 'environment' => 'staging', 'market' => 'main'];
twins_brand_harness_throws(static fn(): array => $quote->action(['environment' => 'staging', 'market' => 'main']), 'adapter accepted a missing context key');
twins_brand_harness_throws(static fn(): array => $quote->action(['key' => 'unknown', 'environment' => 'staging', 'market' => 'main']), 'adapter accepted an unknown context key');
twins_brand_harness_throws(static fn(): array => $quote->action(['key' => 'main', 'environment' => 'staging', 'market' => 'wi']), 'adapter accepted a caller-selected market');
twins_brand_harness_assert($quote->action($context) === ['mode' => 'preview', 'href' => 'https://stage.example.test/contact-us/'], 'quote action changed');
twins_brand_harness_assert(($booking->action($context)['mode'] ?? null) === 'dialog', 'booking action is not a dialog');
twins_brand_harness_assert(strpos((string) ($booking->action($context)['experienceHtml'] ?? ''), 'data-twins-booking-dialog') !== false, 'booking preview is missing');
twins_brand_harness_assert($application->clientContract($context) === ['mode' => 'preview'], 'application client contract changed');
foreach ([$quote->renderExperience($context), $booking->action($context)['experienceHtml'], $application->renderExperience($context)] as $preview) {
    twins_brand_harness_assert(stripos($preview, '<form') === false, 'preview contains a form');
    twins_brand_harness_assert(!preg_match('~\b(?:name|form|formaction)\s*=~i', $preview), 'preview contains field or form authority');
    twins_brand_harness_assert(!preg_match('~type\s*=\s*["\'](?:submit|image)["\']~i', $preview), 'preview contains a submitting control');
    twins_brand_harness_assert(stripos($preview, 'http://') === false && stripos($preview, 'https://') === false, 'preview contains an external URL');
}

$renderContext = ['key' => 'main'];
$outputs = [
    $runtime->renderHeader($renderContext),
    $runtime->renderFooter($renderContext),
    $runtime->renderHome($renderContext),
    $runtime->renderTeam($renderContext),
    $runtime->renderCareers($renderContext),
    $runtime->renderReviews($renderContext),
    $runtime->renderContact($renderContext),
];
foreach ($outputs as $output) {
    twins_brand_harness_assert(stripos($output, '<form') === false, 'portable staging render contains form authority');
    twins_brand_harness_assert(stripos($output, 'book.housecallpro.com') === false, 'portable staging render contains booking authority');
}
twins_brand_harness_assert(
    $GLOBALS['twins_brand_side_effects'] === ['shortcode' => 0, 'remoteGet' => 0, 'remotePost' => 0, 'transient' => 0, 'database' => 0],
    'staging adapter invoked a side-effect primitive'
);

echo "STAGING_BRAND_ADAPTERS_HARNESS_OK\n";
