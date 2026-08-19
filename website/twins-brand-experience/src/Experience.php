<?php
declare(strict_types=1);

namespace Twins\BrandExperience;

final class Experience
{
    private const CONTEXTUAL_ROUTE_LABELS = [
        'il-preview' => [
            'spring-repair' => 'Garage Door Repair',
            'opener-repair' => 'Garage Door Openers',
        ],
    ];

    private AssetResolver $assets;
    private RouteAdapter $routes;
    private ReviewsProvider $reviews;
    private QuoteAdapter $quote;
    private BookingAdapter $booking;
    private ApplicationAdapter $applications;
    private MarketRegistry $markets;
    private ?PageContentRegistry $pageContent = null;
    private ?array $locationContentRecords = null;
    private string $root;

    public function __construct(
        AssetResolver $assets,
        RouteAdapter $routes,
        ReviewsProvider $reviews,
        QuoteAdapter $quote,
        BookingAdapter $booking,
        ApplicationAdapter $applications,
        MarketRegistry $markets,
        string $root
    ) {
        $this->assets = $assets;
        $this->routes = $routes;
        $this->reviews = $reviews;
        $this->quote = $quote;
        $this->booking = $booking;
        $this->applications = $applications;
        $this->markets = $markets;
        $this->root = rtrim($root, '/');
    }

    private function render(string $template, array $context, ?array $catalogView = null): string
    {
        $bufferLevel = ob_get_level();
        ob_start();
        try {
            $context = $this->routes->normalizeContext($context);
            if (!isset($context['environment'], $context['market']) || !is_string($context['environment']) || !is_string($context['market'])) {
                throw new \DomainException('Normalized render context is incomplete.');
            }
            if (!in_array($context['environment'], ['staging', 'production'], true)) {
                throw new \DomainException('Normalized render environment is invalid.');
            }
            $marketKey = $context['market'];
            $environment = $context['environment'];
            $market = $this->markets->resolve($marketKey, $environment);
            $context['metroAddress'] = $this->metroAddressForContext($context, $market);
            $experience = $this;
            if (in_array($template, ['../components/header', '../components/footer', 'home', 'service', 'editorial', 'location-index'], true)) {
                [$phone, $phoneHref] = $this->contactContext($context, $market);
            }
            if ($template === 'service') {
                $path = $context['path'] ?? null;
                $title = $context['title'] ?? null;
                if (!is_string($path) || !is_string($title)) {
                    throw new \DomainException('Service render context is incomplete.');
                }
                $pageContent = $this->pageContent()->resolve($path, $title);
            } elseif ($template === 'editorial') {
                $content = $context['editorialContent'] ?? null;
                $kind = $context['editorialKind'] ?? null;
                if (!is_string($content) || !is_string($kind) || !in_array($kind, ['location', 'trust', 'article'], true)) {
                    throw new \DomainException('Editorial render context is incomplete.');
                }
                $articleHero = '';
                if (isset($context['articleHero'])) {
                    if (!is_string($context['articleHero'])) {
                        throw new \DomainException('Article hero context is invalid.');
                    }
                    if ($context['articleHero'] !== '') {
                        $articleHero = $this->rootRelativePath($context['articleHero'], 'Article hero path is invalid.');
                    }
                }
                $locationContent = null;
                if ($kind === 'location') {
                    $path = $context['path'] ?? null;
                    if (!is_string($path)) {
                        throw new \DomainException('Location render path is invalid.');
                    }
                    $locationContent = $this->locationContent($path);
                }
            } elseif ($template === 'blog-index') {
                $blogIndex = $this->blogIndexView($context);
            }
            $quote = $this->quote->action($context);
            if ($template === 'catalog') {
                if (
                    !is_array($catalogView)
                    || !isset($catalogView['mode'], $catalogView['builderPath'])
                    || !is_string($catalogView['mode'])
                    || !in_array($catalogView['mode'], ['overview', 'product'], true)
                    || !is_string($catalogView['builderPath'])
                ) {
                    throw new \DomainException('Catalog render view is incomplete.');
                }
                $catalogView['builderPath'] = $this->rootRelativePath(
                    $catalogView['builderPath'],
                    'Catalog builder path is invalid.'
                );
                if ($catalogView['mode'] === 'overview') {
                    if (
                        !isset($catalogView['productOrder'], $catalogView['collections'], $catalogView['featured'])
                        || !is_array($catalogView['productOrder'])
                        || !is_array($catalogView['collections'])
                        || !is_array($catalogView['featured'])
                    ) {
                        throw new \DomainException('Catalog overview view is incomplete.');
                    }
                    foreach (array_merge($catalogView['collections'], $catalogView['featured']) as $record) {
                        if (
                            !is_array($record)
                            || !isset($record['path'], $record['product'])
                            || !is_string($record['path'])
                            || !is_array($record['product'])
                        ) {
                            throw new \DomainException('Catalog overview record is incomplete.');
                        }
                        $this->rootRelativePath($record['path'], 'Catalog record path is invalid.');
                    }
                } elseif (!isset($catalogView['product']) || !is_array($catalogView['product'])) {
                    throw new \DomainException('Catalog product view is incomplete.');
                }

                $quoteParts = parse_url((string) ($quote['href'] ?? ''));
                $quotePath = is_array($quoteParts) ? (string) ($quoteParts['path'] ?? '') : '';
                if (
                    !is_array($quoteParts)
                    || isset($quoteParts['user'])
                    || isset($quoteParts['pass'])
                    || isset($quoteParts['query'])
                    || isset($quoteParts['fragment'])
                ) {
                    throw new \DomainException('Catalog quote action is invalid.');
                }
                $quotePath = $this->rootRelativePath($quotePath, 'Catalog quote path is invalid.');
            } elseif ($catalogView !== null) {
                throw new \DomainException('Catalog view was supplied to a different template.');
            }
            $booking = in_array($template, ['../components/header', '../components/footer', 'home', 'contact'], true)
                ? $this->validatedBookingAction($context, $environment)
                : null;
            require $this->root . '/templates/' . $template . '.php';
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            while (ob_get_level() > $bufferLevel) ob_end_clean();
            throw $error;
        }
    }

    public function renderHeader(array $context): string { return $this->render('../components/header', $context); }
    public function renderFooter(array $context): string { return $this->render('../components/footer', $context); }
    public function renderHome(array $context): string { return $this->render('home', $context); }
    public function renderTeam(array $context): string { return $this->render('team', $context); }
    public function renderCareers(array $context): string { return $this->render('careers', $context); }
    public function renderContact(array $context): string { return $this->render('contact', $context); }
    public function renderReviews(array $context): string { return $this->render('reviews', $context); }
    public function renderService(array $context): string { return $this->render('service', $context); }
    public function renderLocationIndex(array $context): string { return $this->render('location-index', $context); }
    public function renderCatalog(array $context, array $catalogView): string
    {
        return $this->render('catalog', $context, $catalogView);
    }

    private function validatedBookingAction(array $context, string $environment): array
    {
        $booking = $this->booking->action($context);
        $bookingMode = $booking['mode'] ?? null;

        if ($environment === 'staging') {
            if ($bookingMode !== 'dialog') {
                throw new \DomainException('Booking action mode does not match staging.');
            }
            if (!isset($booking['experienceHtml']) || !is_string($booking['experienceHtml'])) {
                throw new \DomainException('Booking dialog is unavailable.');
            }
            return $booking;
        }

        if ($environment === 'production') {
            if ($bookingMode !== 'external') {
                throw new \DomainException('Booking action mode does not match production.');
            }
            if (
                !isset($booking['href'])
                || !is_string($booking['href'])
                || $booking['href'] === ''
                || ($booking['target'] ?? null) !== '_blank'
                || ($booking['rel'] ?? null) !== 'noopener noreferrer'
            ) {
                throw new \DomainException('External booking action is unavailable.');
            }
            return $booking;
        }

        throw new \DomainException('Booking environment is invalid.');
    }

    public function renderBlogIndex(array $context, array $blogView): string
    {
        return $this->render('blog-index', array_replace($context, ['blogIndex' => $blogView]));
    }

    public function renderEditorial(array $context, string $content, string $kind): string
    {
        if (!in_array($kind, ['location', 'trust', 'article'], true)) {
            throw new \DomainException('Editorial kind is outside the fixed boundary.');
        }
        if (
            preg_match('~<(?:form|input|button|select|textarea|script|style|iframe|frame|object|embed|template|a|img)\b~i', $content)
            || preg_match('~<[a-z][^>]*\s+[a-z_:][-a-z0-9_:.]*\s*=~i', $content)
            || preg_match('~\b(?:fetch|XMLHttpRequest|sendBeacon)\s*\(|\burl\s*\(~i', $content)
        ) {
            throw new \DomainException('Editorial content is not inert.');
        }
        return $this->render('editorial', array_replace($context, [
            'editorialContent' => $content,
            'editorialKind' => $kind,
        ]));
    }

    public function contextualRouteLabel(string $routeKey, string $marketKey, string $defaultLabel): string
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/D', $routeKey) !== 1) {
            throw new \DomainException('Contextual route label key is invalid.');
        }
        if (!in_array($marketKey, ['main', 'wi', 'ky', 'il-preview'], true)) {
            throw new \DomainException('Contextual route label market is invalid.');
        }
        if (
            $defaultLabel === ''
            || $defaultLabel !== trim($defaultLabel)
            || strlen($defaultLabel) > 100
            || preg_match('/[<>\x00-\x1f\x7f]/', $defaultLabel)
        ) {
            throw new \DomainException('Contextual route label fallback is invalid.');
        }
        return self::CONTEXTUAL_ROUTE_LABELS[$marketKey][$routeKey] ?? $defaultLabel;
    }

    private function contactContext(array $context, array $market): array
    {
        $hasContextPhone = isset($context['phone']) || isset($context['phoneDisplay']);
        $hasContextHref = isset($context['phoneHref']) || isset($context['tel']);
        if ($hasContextPhone !== $hasContextHref) {
            throw new \DomainException('Normalized contact context is incomplete.');
        }

        $phone = $hasContextPhone
            ? ($context['phone'] ?? $context['phoneDisplay'])
            : ($market['phoneDisplay'] ?? null);
        $phoneHref = $hasContextHref
            ? ($context['phoneHref'] ?? ('tel:' . $context['tel']))
            : ($market['phoneHref'] ?? null);
        if (
            !is_string($phone)
            || !is_string($phoneHref)
            || preg_match('/^\(\d{3}\) \d{3}-\d{4}$/D', $phone) !== 1
            || preg_match('/^tel:\+1\d{10}$/D', $phoneHref) !== 1
        ) {
            throw new \DomainException('Normalized contact context is invalid.');
        }
        $displayDigits = preg_replace('/\D+/', '', $phone);
        $hrefDigits = substr($phoneHref, 5);
        if (!is_string($displayDigits) || $hrefDigits !== '1' . $displayDigits) {
            throw new \DomainException('Normalized contact context does not match.');
        }
        return [$phone, $phoneHref];
    }

    private function blogIndexView(array $context): array
    {
        $view = $context['blogIndex'] ?? null;
        if (
            !is_array($view)
            || !isset($view['posts'], $view['page'], $view['totalPages'], $view['basePath'])
            || !is_array($view['posts'])
            || !is_int($view['page'])
            || !is_int($view['totalPages'])
            || !is_string($view['basePath'])
            || $view['page'] < 1
            || $view['totalPages'] < 1
            || $view['page'] > $view['totalPages']
            || count($view['posts']) > 60
        ) {
            throw new \DomainException('Blog index render context is incomplete.');
        }
        $view['basePath'] = $this->rootRelativePath($view['basePath'], 'Blog index base path is invalid.');
        if (substr($view['basePath'], -1) !== '/') {
            throw new \DomainException('Blog index base path is invalid.');
        }
        $posts = [];
        foreach ($view['posts'] as $post) {
            if (
                !is_array($post)
                || !isset($post['path'], $post['title'], $post['excerpt'], $post['date'], $post['thumbnail'])
                || !is_string($post['path'])
                || !is_string($post['title'])
                || !is_string($post['excerpt'])
                || !is_string($post['date'])
                || !is_string($post['thumbnail'])
                || trim($post['title']) === ''
            ) {
                throw new \DomainException('Blog index record is incomplete.');
            }
            $post['path'] = $this->rootRelativePath($post['path'], 'Blog index record path is invalid.');
            if ($post['thumbnail'] !== '') {
                $post['thumbnail'] = $this->rootRelativePath($post['thumbnail'], 'Blog index thumbnail path is invalid.');
            }
            $posts[] = [
                'path' => $post['path'],
                'title' => trim($post['title']),
                'excerpt' => trim($post['excerpt']),
                'date' => trim($post['date']),
                'thumbnail' => $post['thumbnail'],
            ];
        }
        $view['posts'] = $posts;
        return $view;
    }

    private function rootRelativePath(string $path, string $message): string
    {
        if (
            $path === ''
            || strlen($path) > 512
            || $path[0] !== '/'
            || strpos($path, '//') !== false
            || strpos($path, '%') !== false
            || strpos($path, '\\') !== false
            || strpos($path, '?') !== false
            || strpos($path, '#') !== false
            || preg_match('~(?:^|/)\.\.?(?:/|$)|[\x00-\x20\x7f]~', $path)
        ) {
            throw new \DomainException($message);
        }
        return $path;
    }

    private function pageContent(): PageContentRegistry
    {
        if ($this->pageContent instanceof PageContentRegistry) {
            return $this->pageContent;
        }
        $config = $this->root . '/config/page-content.php';
        if (!is_file($config) || is_link($config)) {
            throw new \DomainException('Fixed page-content config is unavailable.');
        }
        $records = require $config;
        if (!is_array($records)) {
            throw new \DomainException('Fixed page-content config is invalid.');
        }
        $this->pageContent = new PageContentRegistry($records);
        return $this->pageContent;
    }

    private function locationContent(string $path): ?array
    {
        $slug = null;
        if ($path === '/wi/garage-door-repair-in-milwaukee-wi/') {
            $slug = 'milwaukee';
        } elseif (preg_match('~^/(?:wi|il|ky)/location/([a-z][a-z0-9-]{0,39})/$~D', $path, $match) === 1) {
            $slug = $match[1];
        } elseif (preg_match('~^/location/([a-z][a-z0-9-]{0,39})/$~D', $path, $match) === 1) {
            $slug = $match[1];
        }
        if ($slug === null) {
            return null;
        }

        if ($this->locationContentRecords === null) {
            $config = $this->root . '/config/location-content.php';
            if (!is_file($config) || is_link($config)) {
                throw new \DomainException('Location content config is unavailable.');
            }
            $records = require $config;
            if (!is_array($records)) {
                throw new \DomainException('Location content config is invalid.');
            }
            $this->locationContentRecords = $records;
        }

        if (!isset($this->locationContentRecords[$slug])) {
            return null;
        }
        $record = $this->locationContentRecords[$slug];
        if (
            !is_array($record)
            || !isset($record['label'], $record['metro'], $record['intro'], $record['localNotes'], $record['faq'])
            || !is_string($record['label'])
            || $record['label'] === ''
            || !is_string($record['metro'])
            || !in_array($record['metro'], ['madison', 'milwaukee', 'rockford'], true)
            || !array_key_exists('completedJobs', $record)
            || !(is_int($record['completedJobs'] ?? null) || ($record['completedJobs'] ?? null) === null)
            || !is_string($record['intro'])
            || !(is_string($record['localNotes']) || is_array($record['localNotes']))
            || !is_array($record['faq'])
        ) {
            throw new \DomainException('Location content record is invalid.');
        }
        if (is_string($record['localNotes'])) {
            $record['localNotes'] = $record['localNotes'] === '' ? [] : [$record['localNotes']];
        }
        foreach ($record['localNotes'] as $note) {
            if (!is_string($note) || trim($note) === '') {
                throw new \DomainException('Location note record is invalid.');
            }
        }
        foreach ($record['faq'] as $faq) {
            if (
                !is_array($faq)
                || !isset($faq['q'], $faq['a'])
                || !is_string($faq['q'])
                || $faq['q'] === ''
                || !is_string($faq['a'])
                || $faq['a'] === ''
            ) {
                throw new \DomainException('Location FAQ record is invalid.');
            }
        }
        $hasLat = array_key_exists('lat', $record);
        $hasLng = array_key_exists('lng', $record);
        if ($hasLat !== $hasLng) {
            throw new \DomainException('Location coordinates are incomplete.');
        }
        if ($hasLat) {
            $lat = $record['lat'];
            $lng = $record['lng'];
            if (
                !(is_int($lat) || is_float($lat))
                || !(is_int($lng) || is_float($lng))
                || $lat < -90 || $lat > 90
                || $lng < -180 || $lng > 180
            ) {
                throw new \DomainException('Location coordinates are invalid.');
            }
        }
        if (array_key_exists('mapEmbedUrl', $record)) {
            $mapEmbedUrl = $record['mapEmbedUrl'];
            if (
                !is_string($mapEmbedUrl)
                || strlen($mapEmbedUrl) > 512
                || strpos($mapEmbedUrl, 'https://www.google.com/maps') !== 0
                || preg_match('~^https://www\.google\.com/maps[A-Za-z0-9/?&=+%.,_:!*\'()-]*$~D', $mapEmbedUrl) !== 1
            ) {
                throw new \DomainException('Location map embed is outside the fixed allowlist.');
            }
        }
        if (array_key_exists('neighbors', $record)) {
            if (!is_array($record['neighbors']) || count($record['neighbors']) !== 6) {
                throw new \DomainException('Location neighbor list is invalid.');
            }
            $neighborLinks = [];
            foreach ($record['neighbors'] as $neighborSlug) {
                if (
                    !is_string($neighborSlug)
                    || preg_match('/^[a-z][a-z0-9-]{0,39}$/D', $neighborSlug) !== 1
                    || $neighborSlug === $slug
                    || in_array($neighborSlug, array_column($neighborLinks, 'slug'), true)
                ) {
                    throw new \DomainException('Location neighbor slug is invalid.');
                }
                $neighborRecord = $this->locationContentRecords[$neighborSlug] ?? null;
                if (
                    !is_array($neighborRecord)
                    || !isset($neighborRecord['label'])
                    || !is_string($neighborRecord['label'])
                    || $neighborRecord['label'] === ''
                ) {
                    throw new \DomainException('Location neighbor is not registered.');
                }
                // City routes are registered under the market that serves the
                // neighbor, so a Rockford page links its Wisconsin neighbors
                // through the Wisconsin route map.
                $neighborLinks[] = [
                    'slug' => $neighborSlug,
                    'label' => $neighborRecord['label'],
                    'market' => ($neighborRecord['metro'] ?? '') === 'rockford' ? 'il-preview' : 'wi',
                ];
            }
            $record['neighborLinks'] = $neighborLinks;
        }
        return $record;
    }

    private function metroAddressForContext(array $context, array $market): string
    {
        if (isset($context['metroAddress']) && is_string($context['metroAddress']) && trim($context['metroAddress']) !== '') {
            return trim($context['metroAddress']);
        }

        $metroAddresses = [
            'madison' => '2921 Landmark Pl, Ste 206, Madison, WI 53713',
            'milwaukee' => '11220 W Burleigh St Ste 100, Wauwatosa, WI 53222',
            'rockford' => '5758 Elaine Dr, Rockford, IL 61108',
        ];
        $path = $context['path'] ?? null;
        if (is_string($path)) {
            $location = $this->locationContent($path);
            $metro = is_array($location) ? ($location['metro'] ?? null) : null;
            if (is_string($metro) && isset($metroAddresses[$metro])) {
                return $metroAddresses[$metro];
            }
        }

        if (($context['market'] ?? null) === 'il-preview') {
            return $metroAddresses['rockford'];
        }

        return isset($market['address']) && is_string($market['address']) ? trim($market['address']) : '';
    }

    public function assetHandles(): array
    {
        return ['style' => 'twins-brand-experience', 'script' => 'twins-brand-experience'];
    }

    public function asset(string $key): string { return $this->assets->url($key); }
    public function route(string $key, string $market): string { return $this->routes->route($key, $market); }
    public function reviewCollection(): array { return $this->reviews->collection(); }
    public function quoteAdapter(): QuoteAdapter { return $this->quote; }
    public function bookingAdapter(): BookingAdapter { return $this->booking; }
    public function applicationAdapter(): ApplicationAdapter { return $this->applications; }
    public function markets(): MarketRegistry { return $this->markets; }
}
