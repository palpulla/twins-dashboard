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

$beforeFunctions = get_defined_functions()['user'];
require $argv[1];
$afterFunctions = get_defined_functions()['user'];

if (array_values(array_diff($afterFunctions, $beforeFunctions)) !== []) {
    fwrite(STDERR, "portable bootstrap created global functions\n");
    exit(1);
}
if (function_exists('add_action') || function_exists('add_filter')) {
    fwrite(STDERR, "portable bootstrap created WordPress hooks\n");
    exit(1);
}

final class PortableHarnessAssetResolver implements Twins\BrandExperience\AssetResolver
{
    public function url(string $assetKey): string { return '/assets/' . $assetKey; }
}

final class PortableHarnessRouteAdapter implements Twins\BrandExperience\RouteAdapter
{
    private array $normalized;
    private bool $throwOnNormalize;
    public function __construct(array $normalized, bool $throwOnNormalize = false)
    {
        $this->normalized = $normalized;
        $this->throwOnNormalize = $throwOnNormalize;
    }
    public function normalizeContext(array $requestContext): array
    {
        if ($this->throwOnNormalize) {
            echo 'LEAKED-NORMALIZER-BYTES';
            ob_start();
            echo 'LEAKED-NESTED-NORMALIZER-BYTES';
            throw new RuntimeException('fixture normalizer failure');
        }
        return $this->normalized;
    }
    public function route(string $routeKey, string $marketKey): string { return '/routes/' . $routeKey . '/' . $marketKey; }
}

final class PortableHarnessReviewsProvider implements Twins\BrandExperience\ReviewsProvider
{
    private bool $renderable;
    public function __construct(bool $renderable = false) { $this->renderable = $renderable; }
    public function collection(): array
    {
        if (!$this->renderable) return ['status' => 'fixture'];
        return [
            'status' => 'verified',
            'allowExternalSourceAction' => false,
            'records' => [[
                'stableId' => 'fixture-review',
                'rating' => 5,
                'text' => 'The fixture review verifies the portable reviews template without external access.',
                'author' => 'Fixture Customer',
                'publishedDate' => '2026-07-15',
            ]],
        ];
    }
}

final class PortableHarnessQuoteAdapter implements Twins\BrandExperience\QuoteAdapter
{
    public int $actionCalls = 0;
    private bool $throwOnAction;
    public function __construct(bool $throwOnAction = false) { $this->throwOnAction = $throwOnAction; }
    public function action(array $context): array
    {
        $this->actionCalls++;
        if ($this->throwOnAction) {
            echo 'LEAKED-ADAPTER-BYTES';
            ob_start();
            echo 'LEAKED-NESTED-ADAPTER-BYTES';
            throw new RuntimeException('fixture adapter failure');
        }
        return ['mode' => 'fixture', 'href' => '/quote/main/'];
    }
    public function renderExperience(array $context): string { return '<div class="quote-fixture"></div>'; }
    public function assertReady(): void {}
}

final class PortableHarnessBookingAdapter implements Twins\BrandExperience\BookingAdapter
{
    public int $actionCalls = 0;
    private bool $environmentModes;
    public function __construct(bool $environmentModes = false) { $this->environmentModes = $environmentModes; }
    public function action(array $context): array
    {
        $this->actionCalls++;
        if ($this->environmentModes) {
            return ($context['environment'] ?? null) === 'production'
                ? ['mode' => 'external', 'href' => 'https://example.invalid/book/', 'target' => '_blank', 'rel' => 'noopener noreferrer']
                : ['mode' => 'dialog', 'experienceHtml' => '<div>fixture booking dialog</div>'];
        }
        return ['mode' => 'fixture'];
    }
    public function assertReady(): void {}
}

final class PortableHarnessApplicationAdapter implements Twins\BrandExperience\ApplicationAdapter
{
    public function clientContract(array $context): array { return ['mode' => 'fixture']; }
    public function renderExperience(array $context): string { return '<div class="application-fixture"></div>'; }
    public function assertReady(): void {}
}

$initialBufferLevel = ob_get_level();
$fixtureRoot = null;
$fixtureFiles = [];
$failure = null;

try {
    $expect = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    $registry = new Twins\BrandExperience\MarketRegistry(require dirname($argv[1]) . '/config/markets.php');
    $staging = array_keys($registry->all('staging'));
    $production = array_keys($registry->all('production'));
    $expect($staging === ['main', 'wi', 'ky', 'il-preview'], 'unexpected staging markets: ' . json_encode($staging));
    $expect($production === ['main', 'wi', 'ky'], 'production exposed Illinois: ' . json_encode($production));
    $expect($registry->resolve('wi', 'staging')['key'] === 'wi', 'resolved market key is missing');

    $closed = false;
    try { $registry->resolve('il-preview', 'production'); } catch (DomainException $expected) { $closed = true; }
    $expect($closed, 'production Illinois did not fail closed');
    $closed = false;
    try { $registry->all('preview'); } catch (DomainException $expected) { $closed = true; }
    $expect($closed, 'unknown environment did not fail closed');
    $extraMarkets = require dirname($argv[1]) . '/config/markets.php';
    $extraMarkets['unauthorized'] = ['stagingEnabled' => true, 'productionEnabled' => true];
    $closed = false;
    try { new Twins\BrandExperience\MarketRegistry($extraMarkets); } catch (InvalidArgumentException $expected) { $closed = true; }
    $expect($closed, 'registry accepted an unauthorized market name');

    $pageRecords = require dirname($argv[1]) . '/config/page-content.php';
    $pageRegistry = new Twins\BrandExperience\PageContentRegistry($pageRecords);
    $bespoke = $pageRegistry->resolve('/wi/garage-door-spring-repair/', '<script>ignored bespoke title</script>');
    $expect($bespoke['h1'] === 'Garage Door Spring Repair', 'prefixed bespoke resolution used the mutable title');
    $fallback = $pageRegistry->resolve('/wi/garage-door-cable-repair/', '<script>hostile mutable title</script>');
    $expect($fallback['h1'] === 'Garage Door Cable Repair', 'generic fallback did not use the fixed slug title');
    $closed = false;
    try { $pageRegistry->resolve('/wi/not-a-service/', 'Ignored'); } catch (DomainException $expected) { $closed = true; }
    $expect($closed, 'unknown page-content route did not fail closed');
    $malformedPageRecords = $pageRecords;
    unset($malformedPageRecords['/garage-door-repair/']['safety']);
    $closed = false;
    try { new Twins\BrandExperience\PageContentRegistry($malformedPageRecords); } catch (InvalidArgumentException $expected) { $closed = true; }
    $expect($closed, 'page-content registry accepted a malformed fixed record');

    $publicMethods = array_values(array_map(
        static fn(ReflectionMethod $method): string => $method->getName(),
        array_filter(
            (new ReflectionClass(Twins\BrandExperience\Experience::class))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn(ReflectionMethod $method): bool => !$method->isConstructor()
        )
    ));
    sort($publicMethods);
    $expectedMethods = ['applicationAdapter', 'asset', 'assetHandles', 'bookingAdapter', 'contextualRouteLabel', 'markets', 'quoteAdapter', 'renderBlogIndex', 'renderCareers', 'renderCatalog', 'renderContact', 'renderEditorial', 'renderFooter', 'renderHeader', 'renderHome', 'renderReviews', 'renderService', 'renderTeam', 'reviewCollection', 'route'];
    sort($expectedMethods);
    $expect($publicMethods === $expectedMethods, 'portable Experience surface drift: ' . json_encode($publicMethods));

    $interfaceMethods = [
        Twins\BrandExperience\AssetResolver::class => ['url'],
        Twins\BrandExperience\RouteAdapter::class => ['normalizeContext', 'route'],
        Twins\BrandExperience\ReviewsProvider::class => ['collection'],
        Twins\BrandExperience\QuoteAdapter::class => ['action', 'assertReady', 'renderExperience'],
        Twins\BrandExperience\BookingAdapter::class => ['action', 'assertReady'],
        Twins\BrandExperience\ApplicationAdapter::class => ['assertReady', 'clientContract', 'renderExperience'],
    ];
    foreach ($interfaceMethods as $interface => $expected) {
        $actual = array_map(static fn(ReflectionMethod $method): string => $method->getName(), (new ReflectionClass($interface))->getMethods());
        sort($actual);
        sort($expected);
        $expect($actual === $expected, $interface . ' surface drift: ' . json_encode($actual));
    }

    $makeExperience = static function (
        array $normalized,
        string $root,
        ?PortableHarnessQuoteAdapter $quote = null,
        ?PortableHarnessBookingAdapter $booking = null,
        ?PortableHarnessRouteAdapter $routes = null,
        ?PortableHarnessReviewsProvider $reviews = null
    ) use ($registry): array {
        $assets = new PortableHarnessAssetResolver();
        $routes = $routes ?? new PortableHarnessRouteAdapter($normalized);
        $reviews = $reviews ?? new PortableHarnessReviewsProvider();
        $quote = $quote ?? new PortableHarnessQuoteAdapter();
        $booking = $booking ?? new PortableHarnessBookingAdapter();
        $applications = new PortableHarnessApplicationAdapter();
        return [
            new Twins\BrandExperience\Experience($assets, $routes, $reviews, $quote, $booking, $applications, $registry, $root),
            $assets, $routes, $reviews, $quote, $booking, $applications,
        ];
    };

    $missingRoot = sys_get_temp_dir() . '/twins-portable-missing-' . bin2hex(random_bytes(8));
    foreach (['staging', 'production'] as $targetEnvironment) {
        $invalidContexts = [
            'missing-environment' => ['market' => 'main', 'normalizationTarget' => $targetEnvironment],
            'missing-market' => ['environment' => $targetEnvironment],
            'unknown-environment' => ['environment' => 'unknown-' . $targetEnvironment, 'market' => 'main'],
            'unknown-market' => ['environment' => $targetEnvironment, 'market' => 'unknown-market'],
        ];
        foreach ($invalidContexts as $scenario => $normalized) {
            [$experience, , , , $quote, $booking] = $makeExperience($normalized, $missingRoot);
            $beforeLevel = ob_get_level();
            $threw = false;
            try { $experience->renderHome([]); } catch (DomainException $expected) { $threw = true; }
            $expect($threw, $scenario . '/' . $targetEnvironment . ' did not fail closed');
            $expect(ob_get_level() === $beforeLevel, $scenario . '/' . $targetEnvironment . ' changed output-buffer level');
            $expect($quote->actionCalls === 0 && $booking->actionCalls === 0, $scenario . '/' . $targetEnvironment . ' reached adapters or templates');
        }
    }

    $fixtureRoot = sys_get_temp_dir() . '/twins-portable-core-' . bin2hex(random_bytes(8));
    $expect(mkdir($fixtureRoot . '/templates', 0700, true), 'could not create template fixture directory');
    $expect(mkdir($fixtureRoot . '/components', 0700, true), 'could not create component fixture directory');

    $requiredScope = <<<'PHP'
<?php
if (!isset($experience, $marketKey, $environment, $market, $quote)) throw new RuntimeException('missing validated render scope');
if (!$experience instanceof Twins\BrandExperience\Experience) throw new RuntimeException('invalid experience scope');
if ($marketKey !== 'main' || $environment !== 'staging') throw new RuntimeException('invalid normalized scope');
if (!isset($market['key']) || $market['key'] !== 'main') throw new RuntimeException('invalid market scope');
if (!isset($quote['mode']) || $quote['mode'] !== 'fixture') throw new RuntimeException('invalid quote scope');
if ($booking !== null) throw new RuntimeException('booking escaped header scope');
?>
<main id="twins-overhaul-main" class="twins-brand-page"></main>
PHP;
    foreach (['home', 'team', 'careers', 'contact', 'reviews'] as $template) {
        $path = $fixtureRoot . '/templates/' . $template . '.php';
        $expect(file_put_contents($path, $requiredScope) === strlen($requiredScope), 'could not write ' . $template . ' fixture');
        $fixtureFiles[] = $path;
    }
    $catalogScope = <<<'PHP'
<?php
if (!isset($experience, $marketKey, $environment, $market, $quote, $quotePath, $catalogView)) throw new RuntimeException('missing catalog render scope');
if ($catalogView['mode'] !== 'product' || $catalogView['builderPath'] !== '/door-builder/') throw new RuntimeException('invalid catalog view scope');
if ($quotePath !== '/quote/main/') throw new RuntimeException('invalid catalog quote scope');
?>
<main id="twins-overhaul-main" class="twins-brand-catalog-page"></main>
PHP;
    $catalogPath = $fixtureRoot . '/templates/catalog.php';
    $expect(file_put_contents($catalogPath, $catalogScope) === strlen($catalogScope), 'could not write catalog fixture');
    $fixtureFiles[] = $catalogPath;
    $footerScope = str_replace(
        '<main id="twins-overhaul-main" class="twins-brand-page"></main>',
        '<footer class="twins-brand-footer"></footer>',
        $requiredScope
    );
    $footerPath = $fixtureRoot . '/components/footer.php';
    $expect(file_put_contents($footerPath, $footerScope) === strlen($footerScope), 'could not write footer fixture');
    $fixtureFiles[] = $footerPath;
    $headerScope = str_replace(
        ["if (\$booking !== null) throw new RuntimeException('booking escaped header scope');", '<main id="twins-overhaul-main" class="twins-brand-page"></main>'],
        ["if (!isset(\$booking['mode']) || \$booking['mode'] !== 'fixture') throw new RuntimeException('missing booking scope');", '<header class="twins-brand-header"></header>'],
        $requiredScope
    );
    $headerPath = $fixtureRoot . '/components/header.php';
    $expect(file_put_contents($headerPath, $headerScope) === strlen($headerScope), 'could not write header fixture');
    $fixtureFiles[] = $headerPath;

    [$experience, $assets, $routes, $reviews, $quote, $booking, $applications] = $makeExperience(
        ['environment' => 'staging', 'market' => 'main'],
        $fixtureRoot
    );
    $renderMethods = ['renderHeader', 'renderFooter', 'renderHome', 'renderTeam', 'renderCareers', 'renderContact', 'renderReviews'];
    foreach ($renderMethods as $method) {
        $output = $experience->{$method}([]);
        $expect(strpos($output, 'twins-brand-') !== false, $method . ' did not render its semantic fixture');
    }
    $catalogOutput = $experience->renderCatalog([], [
        'mode' => 'product',
        'product' => ['id' => 'fixture'],
        'builderPath' => '/door-builder/',
    ]);
    $expect(strpos($catalogOutput, 'twins-brand-catalog-page') !== false, 'renderCatalog did not render its bounded fixture');
    $expect($quote->actionCalls === 8, 'quote action did not run for every public render surface');
    $expect($booking->actionCalls === 1, 'booking action was not header-only');
    $expect($experience->assetHandles() === ['style' => 'twins-brand-experience', 'script' => 'twins-brand-experience'], 'asset handles drifted');
    $expect($experience->asset('logo') === '/assets/logo', 'asset facade drifted');
    $expect($experience->route('home', 'main') === '/routes/home/main', 'route facade drifted');
    $expect($experience->reviewCollection() === ['status' => 'fixture'], 'review facade drifted');
    $expect($experience->quoteAdapter() === $quote, 'quote adapter facade drifted');
    $expect($experience->bookingAdapter() === $booking, 'booking adapter facade drifted');
    $expect($experience->applicationAdapter() === $applications, 'application adapter facade drifted');
    $expect($experience->markets() === $registry, 'market registry facade drifted');

    $homePath = $fixtureRoot . '/templates/home.php';
    $throwingTemplate = '<?php echo "LEAKED-TEMPLATE-BYTES"; throw new RuntimeException("fixture template failure");';
    $expect(file_put_contents($homePath, $throwingTemplate) === strlen($throwingTemplate), 'could not write throwing fixture');
    $captureBase = ob_get_level();
    ob_start();
    $scenarioLevel = ob_get_level();
    $threw = false;
    try { $experience->renderHome([]); } catch (RuntimeException $expected) {
        $expect($expected->getMessage() === 'fixture template failure', 'unexpected template exception');
        $threw = true;
    }
    $expect($threw, 'throwing template did not throw');
    $expect(ob_get_level() === $scenarioLevel, 'throwing template changed output-buffer level');
    $cleanTemplate = '<?php echo "clean-fixture";';
    $expect(file_put_contents($homePath, $cleanTemplate) === strlen($cleanTemplate), 'could not write clean fixture');
    $cleanOutput = $experience->renderHome([]);
    $strayOutput = (string) ob_get_clean();
    $expect(ob_get_level() === $captureBase, 'template scenario leaked an output buffer');
    $expect($cleanOutput === 'clean-fixture' && $strayOutput === '', 'template scenario leaked bytes');

    $throwingQuote = new PortableHarnessQuoteAdapter(true);
    [$throwingExperience] = $makeExperience(['environment' => 'staging', 'market' => 'main'], $fixtureRoot, $throwingQuote);
    $captureBase = ob_get_level();
    ob_start();
    $scenarioLevel = ob_get_level();
    $threw = false;
    try { $throwingExperience->renderHome([]); } catch (RuntimeException $expected) {
        $expect($expected->getMessage() === 'fixture adapter failure', 'unexpected adapter exception');
        $threw = true;
    }
    $expect($threw, 'throwing adapter did not throw');
    $expect(ob_get_level() === $scenarioLevel, 'throwing adapter changed output-buffer level');
    [$cleanExperience] = $makeExperience(['environment' => 'staging', 'market' => 'main'], $fixtureRoot);
    $cleanOutput = $cleanExperience->renderHome([]);
    $strayOutput = (string) ob_get_clean();
    $expect(ob_get_level() === $captureBase, 'adapter scenario leaked an output buffer');
    $expect($cleanOutput === 'clean-fixture' && $strayOutput === '', 'adapter scenario leaked bytes');

    $throwingRoutes = new PortableHarnessRouteAdapter(['environment' => 'staging', 'market' => 'main'], true);
    [$throwingExperience] = $makeExperience(
        ['environment' => 'staging', 'market' => 'main'],
        $fixtureRoot,
        null,
        null,
        $throwingRoutes
    );
    $captureBase = ob_get_level();
    ob_start();
    $scenarioLevel = ob_get_level();
    $threw = false;
    try { $throwingExperience->renderHome([]); } catch (RuntimeException $expected) {
        $expect($expected->getMessage() === 'fixture normalizer failure', 'unexpected normalizer exception');
        $threw = true;
    }
    $expect($threw, 'throwing route normalizer did not throw');
    $expect(ob_get_level() === $scenarioLevel, 'throwing route normalizer changed output-buffer level');
    [$cleanExperience] = $makeExperience(['environment' => 'staging', 'market' => 'main'], $fixtureRoot);
    $cleanOutput = $cleanExperience->renderHome([]);
    $strayOutput = (string) ob_get_clean();
    $expect(ob_get_level() === $captureBase, 'normalizer scenario leaked an output buffer');
    $expect($cleanOutput === 'clean-fixture' && $strayOutput === '', 'normalizer scenario leaked bytes');

    foreach (['staging', 'production'] as $environment) {
        $environmentBooking = new PortableHarnessBookingAdapter(true);
        $renderableReviews = new PortableHarnessReviewsProvider(true);
        [$actualExperience, , , , $actualQuote, $actualBooking] = $makeExperience(
            ['environment' => $environment, 'market' => 'main'],
            dirname($argv[1]),
            null,
            $environmentBooking,
            null,
            $renderableReviews
        );
        foreach ($renderMethods as $method) $actualExperience->{$method}([]);
        $expect($actualQuote->actionCalls === 7 && $actualBooking->actionCalls === 1, 'regular templates did not render in ' . $environment);
    }
} catch (Throwable $error) {
    while (ob_get_level() > $initialBufferLevel) ob_end_clean();
    $failure = $error->getMessage();
} finally {
    foreach (array_reverse($fixtureFiles) as $file) {
        if (is_file($file)) unlink($file);
    }
    if (is_string($fixtureRoot)) {
        if (is_dir($fixtureRoot . '/templates')) rmdir($fixtureRoot . '/templates');
        if (is_dir($fixtureRoot . '/components')) rmdir($fixtureRoot . '/components');
        if (is_dir($fixtureRoot)) rmdir($fixtureRoot);
    }
    restore_error_handler();
}

if (is_string($failure)) {
    fwrite(STDERR, $failure . "\n");
    exit(1);
}

echo "portable-core-ok\n";
