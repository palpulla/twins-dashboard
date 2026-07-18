<?php
/**
 * Branded singular-article document served through the fixed template boundary.
 *
 * This file is selected only by twins_overhaul_filter_branded_template()
 * for a proven singular blog post (post_type "post", classification
 * "article"). It replaces the legacy Elementor single-post shell — sidebar,
 * search widget, and share controls — with the branded article layout while
 * the main-loop content filter still produces the classified body. It is
 * intentionally not loaded by bootstrap.php because WordPress includes it as
 * a full page template.
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

get_header();

if (
    twins_overhaul_current_classification() !== 'article'
    || (string) get_post_type((int) get_queried_object_id()) !== 'post'
) {
    twins_overhaul_refuse_route('article template received a non-article request.');
}
if (!have_posts()) {
    twins_overhaul_refuse_route('article template has no queried post.');
}
the_post();
ob_start();
the_content();
$twinsOverhaulArticleBody = (string) ob_get_clean();
if (twins_overhaul_render_provenance() === '') {
    twins_overhaul_refuse_route('article template body was not classified.');
}
echo $twinsOverhaulArticleBody;

get_footer();
