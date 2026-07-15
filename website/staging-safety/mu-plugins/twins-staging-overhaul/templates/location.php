<?php
/** Location and service-area family renderer. */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

function twins_overhaul_render_location_template(array $context, string $content): string {
    return twins_overhaul_render_page_family('location', $context, $content);
}
