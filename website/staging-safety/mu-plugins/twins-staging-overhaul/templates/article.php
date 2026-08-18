<?php
/** Article and legal editorial-frame renderer. */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

function twins_overhaul_render_article_template(array $context, string $content): string {
    $title = isset($context['title']) && $context['title'] !== '' ? (string) $context['title'] : 'Twins Garage Doors';
    $label = (($context['classification'] ?? '') === 'legal-preserve') ? 'Published information' : 'Garage door resource';
    $bodyHasH1 = twins_overhaul_count_original_h1($content) > 0;

    $markup = '<div id="twins-overhaul-main" class="twins-overhaul-main twins-overhaul-editorial" tabindex="-1">';
    $markup .= '<header class="twins-overhaul-section twins-overhaul-editorial__header"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($label) . '</p>';
    $markup .= $bodyHasH1
        ? '<p class="twins-overhaul-title">' . esc_html($title) . '</p>'
        : '<h1 class="twins-overhaul-title">' . esc_html($title) . '</h1>';
    $markup .= '</div></header>';
    $markup .= '<div class="twins-overhaul-section twins-overhaul-editorial__body"><div class="twins-overhaul-shell">';
    // Some preserved production pages (terms) carry more than one h1 of their
    // own. Keep the first as the page h1 and demote the rest to h2 so every
    // route has exactly one h1 (WCAG/SEO). Heading level is presentation;
    // the preserved copy itself is not edited.
    $h1Seen = false;
    $content = preg_replace_callback(
        '~<(/?)h1\b~i',
        static function (array $m) use (&$h1Seen) {
            if ($m[1] === '') {
                if (!$h1Seen) {
                    $h1Seen = true;
                    return $m[0];
                }
                return '<h2';
            }
            static $closedFirst = false;
            if (!$closedFirst) {
                $closedFirst = true;
                return $m[0];
            }
            return '</h2';
        },
        $content
    );
    $markup .= '<article class="twins-overhaul-original-content" data-twins-original-content>' . $content . '</article>';
    $markup .= '</div></div></div>';
    return $markup;
}
