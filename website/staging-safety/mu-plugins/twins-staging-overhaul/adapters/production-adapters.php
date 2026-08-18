<?php
declare(strict_types=1);

/**
 * Production adapters for the branded experience.
 *
 * brand-runtime.php requires this file whenever WP_ENVIRONMENT_TYPE is
 * 'production' and immediately constructs all three classes, so its absence
 * is a fatal on the first request. It must therefore define every class the
 * runtime names, even where the production behaviour is deliberately minimal.
 *
 * Design rule: these adapters never invent a form and never move a lead. The
 * staging adapters are inert on purpose; the production ones defer to what is
 * already live rather than reimplementing it. Where a decision has not been
 * made, the adapter degrades to a link rather than guessing a destination,
 * because a silently misrouted lead is worse than an obvious one.
 */

use Twins\BrandExperience\ApplicationAdapter;
use Twins\BrandExperience\BookingAdapter;
use Twins\BrandExperience\QuoteAdapter;

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Production context guard.
 *
 * Mirrors twins_brand_staging_assert_context but requires the production
 * environment and refuses 'il', which is preview-only in markets.php.
 *
 * @param array $context Renderer context.
 * @return void
 */
function twins_brand_production_assert_context(array $context): void
{
    $environment = $context['environment'] ?? null;
    $market = $context['market'] ?? null;
    $key = $context['key'] ?? null;
    if (!is_string($key) || !in_array($key, ['main', 'wi', 'ky'], true)) {
        throw new DomainException('Unknown production adapter context key.');
    }
    if ($environment !== 'production' || !is_string($market) || $market !== $key) {
        throw new DomainException('Production adapter context is incomplete.');
    }
}

/**
 * Owner-confirmed Housecall Pro booking destination.
 *
 * Overridable with a TWINS_BOOKING_URL constant so the destination can change
 * without a code deploy. Must be https and on book.housecallpro.com.
 *
 * @return string
 */
function twins_brand_production_booking_url(): string
{
    $url = defined('TWINS_BOOKING_URL') && is_string(TWINS_BOOKING_URL) && TWINS_BOOKING_URL !== ''
        ? TWINS_BOOKING_URL
        : 'https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true';

    $parts = wp_parse_url($url);
    if (
        !is_array($parts)
        || ($parts['scheme'] ?? '') !== 'https'
        || ($parts['host'] ?? '') !== 'book.housecallpro.com'
    ) {
        throw new DomainException('Production booking URL is not the approved Housecall Pro origin.');
    }

    return $url;
}

final class ProductionBookingAdapter implements BookingAdapter
{
    public function action(array $context): array
    {
        twins_brand_production_assert_context($context);
        return ['mode' => 'link', 'href' => twins_brand_production_booking_url()];
    }

    public function assertReady(): void
    {
        twins_brand_production_booking_url();
    }
}

final class ProductionQuoteAdapter implements QuoteAdapter
{
    private StagingRouteAdapter $routes;

    public function __construct()
    {
        // brand-runtime.php builds the Experience with StagingRouteAdapter in
        // both environments; only these three adapters branch. Reuse it so the
        // quote destination cannot drift from the site's own route table.
        $this->routes = new StagingRouteAdapter();
    }

    private function href(array $context): string
    {
        $market = $context['market'] ?? null;
        if (!is_string($market) || !in_array($market, ['main', 'wi', 'ky'], true)) {
            throw new DomainException('Production quote market is unavailable.');
        }
        return $this->routes->route('contact', $market);
    }

    public function action(array $context): array
    {
        twins_brand_production_assert_context($context);
        return ['mode' => 'link', 'href' => $this->href($context)];
    }

    /**
     * The contact page's lead capture is NOT reimplemented here.
     *
     * Production currently runs different lead capture per market: /contact-us/
     * and /ky/contact-us/ share Gravity Forms form 1, while /wi/contact-us/
     * runs a LeadConnector (GHL) embed instead. Rendering one of those inline
     * would silently reroute the other market's leads, so until that is
     * reconciled this returns a link to the market's own contact page, where
     * the existing, working capture already lives.
     *
     * When the routing decision is made, set TWINS_QUOTE_FORM_SHORTCODES to a
     * market => shortcode map and this will render inline instead.
     *
     * @param array $context Renderer context.
     * @return string
     */
    public function renderExperience(array $context): string
    {
        twins_brand_production_assert_context($context);
        $market = (string) $context['market'];

        $map = defined('TWINS_QUOTE_FORM_SHORTCODES') && is_array(TWINS_QUOTE_FORM_SHORTCODES)
            ? TWINS_QUOTE_FORM_SHORTCODES
            : [];

        if (isset($map[$market]) && is_string($map[$market]) && $map[$market] !== '') {
            return do_shortcode($map[$market]);
        }

        $href = $this->href($context);

        return '<div class="twins-brand-quote-handoff">'
            . '<a class="twins-brand-cta twins-brand-cta--quote" href="' . esc_url($href) . '">Request a Quote</a>'
            . '</div>';
    }

    public function assertReady(): void
    {
        // Fail here rather than at render time if the route table has drifted.
        foreach (['main', 'wi', 'ky'] as $market) {
            $href = $this->routes->route('contact', $market);
            if (!is_string($href) || $href === '') {
                throw new DomainException('Production quote route is unavailable.');
            }
        }
    }
}

final class ProductionApplicationAdapter implements ApplicationAdapter
{
    /**
     * The careers funnel is already live and must not be replaced.
     *
     * Page 7341 went live 2026-07-11 carrying a self-contained shadow-DOM
     * application widget wired to WPCode snippet 7356, which records the
     * application, emails it, and pushes a tagged contact plus a Recruiting
     * opportunity into GHL. Reimplementing any of that here would replace a
     * working, verified funnel with something weaker, so this adapter renders
     * nothing and lets the page's own widget stand.
     *
     * @param array $context Renderer context.
     * @return string
     */
    public function renderExperience(array $context): string
    {
        twins_brand_production_assert_context($context);
        return '';
    }

    public function clientContract(array $context): array
    {
        twins_brand_production_assert_context($context);
        return ['mode' => 'page'];
    }

    public function assertReady(): void
    {
        // Nothing to prove: this adapter owns no capture of its own.
    }
}
