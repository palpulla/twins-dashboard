<?php
/**
 * Branded posts-index document served through the fixed template boundary.
 *
 * This file is selected only by twins_overhaul_filter_branded_template()
 * for a proven main posts-index request. It is intentionally not loaded by
 * bootstrap.php because WordPress includes it as a full page template.
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

get_header();

if (twins_overhaul_current_classification() !== 'blog-index') {
    twins_overhaul_refuse_route('blog index template received a non-index request.');
}
echo twins_overhaul_render_classified_content(
    'blog-index',
    twins_overhaul_current_context('blog-index'),
    ''
);

get_footer();
