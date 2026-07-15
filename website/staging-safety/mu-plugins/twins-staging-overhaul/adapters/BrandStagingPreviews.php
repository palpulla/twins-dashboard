<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

function twins_brand_staging_quote_preview(): string
{
    return '<div class="twins-brand-preview-form" role="form" aria-labelledby="twins-brand-quote-form-title" data-preview-kind="quote">'
        . '<h3 id="twins-brand-quote-form-title">Tell us what you need</h3>'
        . '<label>Full name <input type="text" autocomplete="name"></label>'
        . '<label>Phone <input type="tel" autocomplete="tel"></label>'
        . '<label>Email <input type="email" autocomplete="email"></label>'
        . '<label>ZIP code <input type="text" inputmode="numeric" autocomplete="postal-code"></label>'
        . '<label>Service needed <select><option>Garage door repair</option><option>Garage door installation</option><option>Garage door opener help</option><option>Something else</option></select></label>'
        . '<label>Message <textarea></textarea></label>'
        . '<button type="button" data-preview-finalize>Review quote on staging</button>'
        . '<p role="status" hidden data-preview-status>This private preview cannot send a quote request.</p>'
        . '</div>';
}

function twins_brand_staging_booking_preview(): string
{
    return '<div class="twins-brand-booking-dialog" data-twins-booking-dialog hidden>'
        . '<div role="dialog" aria-modal="true" aria-labelledby="twins-brand-booking-title">'
        . '<button type="button" data-booking-close aria-label="Close booking preview">Close</button>'
        . '<h2 id="twins-brand-booking-title">Book with Twins</h2>'
        . '<p>Choose a convenient time after this experience moves to production.</p>'
        . '<button type="button" data-booking-finalize>Continue on staging</button>'
        . '<p role="status" hidden data-booking-status>Booking is intentionally disabled on this private staging copy.</p>'
        . '</div></div>';
}

function twins_brand_staging_application_preview(): string
{
    return '<div class="twins-brand-preview-form" role="form" aria-labelledby="twins-brand-careers-form-title" data-preview-kind="application">'
        . '<h3 id="twins-brand-careers-form-title">Application preview</h3>'
        . '<label>Full name <input type="text" autocomplete="name"></label>'
        . '<label>Email <input type="email" autocomplete="email"></label>'
        . '<label>Phone <input type="tel" autocomplete="tel"></label>'
        . '<label>Role of interest <select><option>Service and repair</option><option>Installations</option><option>Sales and estimates</option><option>Customer care and operations</option><option>Something else</option></select></label>'
        . '<label>Tell us about your experience <textarea></textarea></label>'
        . '<button type="button" data-preview-finalize>Review application on staging</button>'
        . '<p role="status" hidden data-preview-status>This private preview cannot send an application.</p>'
        . '</div>';
}
