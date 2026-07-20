<?php
declare(strict_types=1);

/**
 * Production adapters for the Twins brand experience - CUTOVER ONLY.
 *
 * Not loaded on staging. These implement the `production` environment branch
 * of the brand chrome contracts. Install alongside the brand runtime on the
 * production host and swap them into the Experience constructor in place of
 * the Staging* adapters.
 */

use Twins\BrandExperience\ApplicationAdapter;
use Twins\BrandExperience\BookingAdapter;
use Twins\BrandExperience\QuoteAdapter;

final class ProductionBookingAdapter implements BookingAdapter
{
    /** Owner decision 2026-07-17: Housecall Pro online booking. */
    private const HCP_BOOKING_URL =
        'https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true';

    public function action(array $context): array
    {
        return [
            'mode' => 'external',
            'href' => self::HCP_BOOKING_URL,
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ];
    }

    public function assertReady(): void
    {
        if (parse_url(self::HCP_BOOKING_URL, PHP_URL_HOST) !== 'book.housecallpro.com') {
            throw new DomainException('Production booking host is not Housecall Pro.');
        }
    }
}

final class ProductionQuoteAdapter implements QuoteAdapter
{
    /** The same edge function the Madison LP posts to today. */
    private const LEAD_INTAKE_URL =
        'https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/lp-lead-intake';

    public function action(array $context): array
    {
        return ['mode' => 'section', 'href' => '/contact-us/#callback'];
    }

    /**
     * Owner decision 2026-07-17: LP-style callback form.
     * Renders a live form; submission handled by a small inline script that
     * POSTs the LP payload contract as JSON (name, phone, service, page,
     * form_variant "site-callback", consent, website honeypot empty).
     */
    public function renderExperience(array $context): string
    {
        $endpoint = htmlspecialchars(self::LEAD_INTAKE_URL, ENT_QUOTES, 'UTF-8');
        return '<div id="callback" class="twins-brand-preview-form twins-brand-callback-form" data-callback-endpoint="' . $endpoint . '">'
            . '<span class="twins-brand-kicker">Fast response</span>'
            . '<h3>Request a call back</h3>'
            . '<p>Tell us what is going on and we will call you right back.</p>'
            . '<form class="twins-brand-callback" novalidate>'
            . '<label>Name <input type="text" name="name" autocomplete="name" required></label>'
            . '<label>Phone <input type="tel" name="phone" autocomplete="tel" required></label>'
            . '<label>What do you need? <select name="service">'
            . '<option>Broken spring</option>'
            . '<option>Door will not open or close</option>'
            . '<option>Opener problem</option>'
            . '<option>Door off track</option>'
            . '<option>New garage door</option>'
            . '<option>Something else</option>'
            . '</select></label>'
            . '<input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">'
            . '<button type="submit">Get My Call Back</button>'
            . '<p class="twins-brand-callback-consent">By submitting, you agree Twins Garage Doors may call or text this number about your request. Msg and data rates may apply. Reply STOP to opt out.</p>'
            . '<p role="status" hidden data-callback-status></p>'
            . '</form>'
            . '</div>';
    }

    public function assertReady(): void
    {
        if (parse_url(self::LEAD_INTAKE_URL, PHP_URL_HOST) !== 'jwrpjuqaynownxaoeayi.supabase.co') {
            throw new DomainException('Production lead intake host is not the approved edge function.');
        }
    }
}

final class ProductionApplicationAdapter implements ApplicationAdapter
{
    public function clientContract(array $context): array
    {
        return ['mode' => 'external'];
    }

    /**
     * Careers applications route to the production careers flow (page 2322 /
     * GHL employment funnel, location iRUlbIBg7PzSfLrPiR2j). Final embed to
     * be confirmed during cutover engineering.
     */
    public function renderExperience(array $context): string
    {
        return '<a class="twins-brand-cta twins-brand-cta--quote" href="/careers/#apply">Start your application</a>';
    }

    public function assertReady(): void
    {
        if (strpos($this->renderExperience([]), '/careers/#apply') === false) {
            throw new DomainException('Production application flow lost its careers destination.');
        }
    }
}

/*
 * Companion JS: production-callback.js (in this directory). It binds the submit
 * handler and POSTs the payload above to data-callback-endpoint. Use that file
 * rather than re-deriving the handler here — it reads controls via form.elements
 * (form.name would collide with the form's name IDL attribute), treats a non-ok
 * fetch response as a failure, and waits for the DOM before binding.
 */
