<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

function twins_brand_staging_quote_preview(): string
{
    return '<div class="twins-brand-preview-form twins-brand-callback-form" role="form" aria-labelledby="twins-brand-quote-form-title" data-preview-kind="quote">'
        . '<span class="twins-brand-kicker">Fast response</span>'
        . '<h3 id="twins-brand-quote-form-title">Request a call back</h3>'
        . '<p>Tell us what is going on and we will call you right back.</p>'
        . '<label>Name <input type="text" autocomplete="name"></label>'
        . '<label>Phone <input type="tel" autocomplete="tel"></label>'
        . '<label>What do you need? <select>'
        . '<option>Broken spring</option>'
        . '<option>Door will not open or close</option>'
        . '<option>Opener problem</option>'
        . '<option>Door off track</option>'
        . '<option>New garage door</option>'
        . '<option>Something else</option>'
        . '</select></label>'
        . '<button type="button" data-preview-finalize>Get My Call Back</button>'
        . '<p class="twins-brand-callback-consent">By submitting, you agree Twins Garage Doors may call or text this number about your request. Msg and data rates may apply. Reply STOP to opt out.</p>'
        . '<p role="status" hidden data-preview-status>This private preview cannot send a call back request.</p>'
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
