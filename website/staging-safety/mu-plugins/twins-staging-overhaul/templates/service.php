<?php
/** Service-page family renderer. */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

function twins_overhaul_render_service_template(array $context, string $content): string {
    return twins_overhaul_render_page_family('service', $context, $content);
}
