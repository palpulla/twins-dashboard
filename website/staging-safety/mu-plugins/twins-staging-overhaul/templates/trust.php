<?php
/** About, reviews, FAQ, financing, offers, and contact family renderer. */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

function twins_overhaul_render_trust_template(array $context, string $content): string {
    return twins_overhaul_render_page_family('trust', $context, $content);
}
