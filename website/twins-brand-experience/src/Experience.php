<?php
declare(strict_types=1);

namespace Twins\BrandExperience;

final class Experience
{
    private AssetResolver $assets;
    private RouteAdapter $routes;
    private ReviewsProvider $reviews;
    private QuoteAdapter $quote;
    private BookingAdapter $booking;
    private ApplicationAdapter $applications;
    private MarketRegistry $markets;
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

    private function render(string $template, array $context): string
    {
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
        $experience = $this;
        $bufferLevel = ob_get_level();
        ob_start();
        try {
            $quote = $this->quote->action($context);
            $booking = $template === '../components/header' ? $this->booking->action($context) : null;
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
