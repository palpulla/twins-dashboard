<?php
/**
 * Front-end hooks and page-family composition for the private staging preview.
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Resolve the current path without accepting a route from a hook caller.
 *
 * @return string
 */
function twins_overhaul_current_request_path(): string {
    $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $path = wp_parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        twins_overhaul_refuse_route('current request path is unavailable.');
    }
    twins_overhaul_assert_safe_path($path);
    return $path;
}

/**
 * Protect both queried and currently rendered theme/library documents.
 *
 * @param int    $postId WordPress post ID.
 * @param string $postType WordPress post type.
 * @return bool
 */
function twins_overhaul_is_protected_post_identity(int $postId, string $postType): bool {
    if (in_array($postType, array('elementor_library', 'attachment', 'product'), true)) {
        return true;
    }
    if (in_array($postId, array(36, 305, 466, 1409, 2163, 2179, 7333, 7336, 7344), true)) {
        return true;
    }
    return $postId >= 1498 && $postId <= 1516;
}

/**
 * Allow shared chrome only on a safe singular document or posts index.
 *
 * @return bool
 */
function twins_overhaul_is_allowed_chrome_request(): bool {
    $regions = twins_overhaul_regions();
    if (!isset($regions[(int) get_current_blog_id()])) {
        twins_overhaul_refuse_route('current blog ID is outside the fixed preview map.');
    }
    if (is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
        return false;
    }
    if ((defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }
    if (is_feed() || is_preview() || is_archive() || is_search() || is_404()) {
        return false;
    }

    $isSingular = is_singular();
    $isPostsIndex = function_exists('is_home') && is_home();
    if (!$isSingular && !$isPostsIndex) {
        return false;
    }
    if ($isSingular && function_exists('is_attachment') && is_attachment()) {
        return false;
    }

    $postId = (int) get_queried_object_id();
    $postType = (string) get_post_type($postId);
    if (twins_overhaul_is_protected_post_identity($postId, $postType)) {
        return false;
    }

    $renderedPost = isset($GLOBALS['post']) && is_object($GLOBALS['post']) ? $GLOBALS['post'] : null;
    if ($isSingular && $renderedPost !== null) {
        $renderedPostId = isset($renderedPost->ID) ? (int) $renderedPost->ID : 0;
        $renderedPostType = isset($renderedPost->post_type)
            ? (string) $renderedPost->post_type
            : (string) get_post_type($renderedPostId);
        if (twins_overhaul_is_protected_post_identity($renderedPostId, $renderedPostType)) {
            return false;
        }
    }

    return true;
}

/**
 * Require the exact queried singular document before replacing a body.
 *
 * @return bool
 */
function twins_overhaul_is_allowed_singular_request(): bool {
    if (!twins_overhaul_is_allowed_chrome_request() || !is_singular()) {
        return false;
    }

    $postId = (int) get_queried_object_id();
    $renderedPost = isset($GLOBALS['post']) && is_object($GLOBALS['post']) ? $GLOBALS['post'] : null;
    if ($renderedPost === null) {
        return false;
    }

    $renderedPostId = isset($renderedPost->ID) ? (int) $renderedPost->ID : 0;
    if ($renderedPostId !== $postId) {
        return false;
    }

    return true;
}

/**
 * Resolve the current route classification from WordPress identity only.
 *
 * @return string
 */
function twins_overhaul_current_classification(): string {
    $postId = (int) get_queried_object_id();
    $postType = (string) get_post_type($postId);
    return twins_overhaul_classify_request(
        (int) get_current_blog_id(),
        twins_overhaul_current_request_path(),
        $postType,
        $postId
    );
}

/**
 * Build a fixed renderer context from the current query.
 *
 * @param string $classification Proven route classification.
 * @return array
 */
function twins_overhaul_current_context(string $classification): array {
    $postId = (int) get_queried_object_id();
    $path = twins_overhaul_current_request_path();
    $context = twins_overhaul_resolve_context(array('path' => $path));
    $context['path'] = $path;
    $context['postId'] = $postId;
    $context['postType'] = (string) get_post_type($postId);
    $context['title'] = (string) get_the_title($postId);
    $context['classification'] = $classification;
    return $context;
}

/**
 * Return the fixed visible name for a region.
 *
 * @param string $key Fixed region key.
 * @return string
 */
function twins_overhaul_region_name(string $key): string {
    $names = array(
        'main' => 'Twins Garage Doors',
        'wi' => 'Wisconsin',
        'ky' => 'Kentucky',
        'il' => 'Rockford-area Illinois',
    );
    return isset($names[$key]) ? $names[$key] : 'Twins Garage Doors';
}

/**
 * Demote legacy H1 tags for rebuilt family content.
 *
 * Completed campaign, Careers, Team, catalog, article, and legal bodies never
 * call this helper.
 *
 * @param string $content Existing rendered WordPress content.
 * @return string
 */
function twins_overhaul_normalize_original_headings(string $content): string {
    $normalized = preg_replace_callback(
        '~<(\s*/?\s*)h1(?=[\s>])~i',
        static function (array $match): string {
            return '<' . $match[1] . 'h2';
        },
        $content
    );
    if (!is_string($normalized)) {
        twins_overhaul_refuse_route('legacy heading normalization failed.');
    }
    return $normalized;
}

/**
 * Count original H1 start tags without mutating an exact-preserve body.
 *
 * @param string $content Exact rendered WordPress content.
 * @return int
 */
function twins_overhaul_count_original_h1(string $content): int {
    $count = preg_match_all('~<\s*h1(?=[\s>])~i', $content);
    if (!is_int($count)) {
        twins_overhaul_refuse_route('legacy heading count failed.');
    }
    return $count;
}

/**
 * Detect request-capable primitives that must never remain as family text.
 *
 * @param string $content Prepared family content.
 * @return bool
 */
function twins_overhaul_contains_active_request_primitive(string $content): bool {
    $callNames = array(
        'fet' . 'ch',
        'send' . 'Beacon',
        'u' . 'rl',
    );
    foreach ($callNames as $callName) {
        if (preg_match('~' . preg_quote($callName, '~') . '\s*\(~i', $content)) {
            return true;
        }
    }

    $requestObject = 'XMLHttp' . 'Request';
    return stripos($content, $requestObject) !== false;
}

/**
 * Reduce a rebuilt family body to inert, attribute-free structural content.
 *
 * Factual text, headings, lists, emphasis, quotations, code, and simple tables
 * remain. Active containers are removed; links/media/controls lose their tags;
 * every surviving allowlisted tag is canonical and has no attributes.
 *
 * @param string $content Existing rendered WordPress content.
 * @return string
 */
function twins_overhaul_prepare_family_content(string $content): string {
    $hadFormControls = preg_match(
        '~<\s*/?\s*(?:form|input|button|select|textarea|option|fieldset|datalist|output)\b~i',
        $content
    ) === 1;

    $prepared = $content;
    for ($pass = 0; $pass < 8; $pass++) {
        $next = preg_replace(
            '~<\s*(script|style|iframe|object|template|noscript)\b[^>]*>.*?<\s*/\s*\\1\s*>~is',
            '',
            $prepared
        );
        if (!is_string($next)) {
            twins_overhaul_refuse_route('active legacy block removal failed.');
        }
        if ($next === $prepared) {
            break;
        }
        $prepared = $next;
    }

    if (preg_match('~<\s*(?:script|style|iframe|object|template|noscript)\b~i', $prepared)) {
        twins_overhaul_refuse_route('active legacy block remained after bounded removal.');
    }

    $prepared = twins_overhaul_normalize_original_headings($prepared);
    $allowedTags = array(
        'p' => array(),
        'br' => array(),
        'h2' => array(),
        'h3' => array(),
        'h4' => array(),
        'h5' => array(),
        'h6' => array(),
        'ul' => array(),
        'ol' => array(),
        'li' => array(),
        'strong' => array(),
        'em' => array(),
        'b' => array(),
        'i' => array(),
        'blockquote' => array(),
        'cite' => array(),
        'code' => array(),
        'pre' => array(),
        'hr' => array(),
        'dl' => array(),
        'dt' => array(),
        'dd' => array(),
        'table' => array(),
        'caption' => array(),
        'thead' => array(),
        'tbody' => array(),
        'tfoot' => array(),
        'tr' => array(),
        'th' => array(),
        'td' => array(),
        'figure' => array(),
        'figcaption' => array(),
    );
    $prepared = wp_kses($prepared, $allowedTags);

    $tagNames = implode('|', array_keys($allowedTags));
    $prepared = preg_replace_callback(
        '~<\s*(/?)\s*(' . $tagNames . ')\b[^>]*>~i',
        static function (array $match): string {
            return '<' . $match[1] . strtolower($match[2]) . '>';
        },
        $prepared
    );
    if (!is_string($prepared)) {
        twins_overhaul_refuse_route('structural tag canonicalization failed.');
    }

    if ($hadFormControls) {
        $prepared .= '<p>This private staging preview does not submit or store lead information.</p>';
    }

    if (preg_match('~<(?:form|input|button|select|textarea|script|style|iframe|frame|object|embed|template|a|img)\b~i', $prepared)) {
        twins_overhaul_refuse_route('active element survived family-content preparation.');
    }
    if (preg_match('~<[a-z][^>]*\s+[a-z_:][-a-z0-9_:.]*\s*=~i', $prepared)) {
        twins_overhaul_refuse_route('an attribute survived family-content preparation.');
    }
    if (twins_overhaul_contains_active_request_primitive($prepared)) {
        twins_overhaul_refuse_route('an active request primitive survived family-content preparation.');
    }

    return $prepared;
}

/**
 * Render a truthful, shared structure around an existing page body.
 *
 * The existing factual body is reduced to inert structural content, then
 * inserted once beneath the rebuilt document H1.
 *
 * @param string $family Fixed family key.
 * @param array  $context Proven request context.
 * @param string $content Original rendered WordPress content.
 * @return string
 */
function twins_overhaul_render_page_family(string $family, array $context, string $content): string {
    $families = array(
        'home' => array(
            'eyebrow' => 'Start here',
            'answer' => 'Choose the part of the garage door project you want to understand first.',
        ),
        'service' => array(
            'eyebrow' => 'Service guide',
            'answer' => 'Use the published service details below to understand the subject and prepare your questions.',
        ),
        'location' => array(
            'eyebrow' => 'Service-area guide',
            'answer' => 'Review the published location details below, then use the fixed regional contact path for project-specific questions.',
        ),
        'trust' => array(
            'eyebrow' => 'About Twins',
            'answer' => 'Review the published information below and use the direct regional contact details when you need clarification.',
        ),
    );
    if (!isset($families[$family])) {
        return twins_overhaul_render_article_template($context, $content);
    }

    $content = twins_overhaul_prepare_family_content($content);

    $title = isset($context['title']) && $context['title'] !== '' ? (string) $context['title'] : 'Twins Garage Doors';
    $regionName = twins_overhaul_region_name((string) ($context['key'] ?? 'main'));
    $phone = (string) ($context['phone'] ?? '');
    $tel = (string) ($context['tel'] ?? '');
    $contact = (string) ($context['contact'] ?? '/contact-us/');
    $serviceAreaPaths = array(
        'main' => '/locations/',
        'wi' => '/wi/service-area/',
        'ky' => '/ky/location/lexington/',
        'il' => '/il/locations/',
    );
    $serviceArea = $serviceAreaPaths[$context['key'] ?? 'main'];
    $copy = $families[$family];

    $markup = '<div id="twins-overhaul-main" class="twins-overhaul-main twins-family twins-family--' . esc_attr($family) . '" tabindex="-1">';
    $markup .= '<section class="twins-overhaul-section twins-family-hero"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($copy['eyebrow']) . '</p>';
    $markup .= '<h1 class="twins-overhaul-title">' . esc_html($title) . '</h1>';
    $markup .= '<p class="twins-family-hero__summary">' . esc_html($copy['answer']) . '</p>';
    $markup .= '<a class="twins-overhaul-button twins-overhaul-button--primary" href="' . esc_url($contact) . '">Review contact options</a>';
    $markup .= '</div></section>';

    $markup .= '<section class="twins-overhaul-section twins-family-answer"><div class="twins-overhaul-shell twins-family-answer__grid">';
    $markup .= '<div><p class="twins-overhaul-eyebrow">Direct answer</p><h2>Start with the details already published for this page.</h2></div>';
    $markup .= '<p>' . esc_html($copy['answer']) . ' The original page body remains available in this preview without changing its factual content.</p>';
    $markup .= '</div></section>';

    $markup .= '<section class="twins-overhaul-section twins-family-cards"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">A useful way through</p><h2>Turn the page into a clear next-step list.</h2>';
    $markup .= '<div class="twins-overhaul-grid">';
    $markup .= '<article class="twins-overhaul-card"><h3>Understand the subject</h3><p>Read the existing page details and note which part matches your door, opener, or project.</p></article>';
    $markup .= '<article class="twins-overhaul-card"><h3>Keep the relevant details</h3><p>Record the door style, visible condition, or question that matters to your situation.</p></article>';
    $markup .= '<article class="twins-overhaul-card"><h3>Prepare a conversation</h3><p>Use the regional contact route when you are ready to discuss details specific to your property.</p></article>';
    $markup .= '</div></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-family-original"><div class="twins-overhaul-shell">';
    $markup .= '<div class="twins-overhaul-original-content" data-twins-original-content>' . $content . '</div>';
    $markup .= '</div></section>';

    $markup .= '<section class="twins-overhaul-section twins-family-process"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">Simple process</p><h2>Review, note, and confirm.</h2>';
    $markup .= '<ol class="twins-family-process__steps">';
    $markup .= '<li><strong>Review this page.</strong><span>Use the preserved information above as your starting point.</span></li>';
    $markup .= '<li><strong>Note your questions.</strong><span>Keep the details that need a project-specific answer.</span></li>';
    $markup .= '<li><strong>Use the regional contact path.</strong><span>Call the fixed regional number or open the contact page when you choose.</span></li>';
    $markup .= '</ol></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-family-service-area"><div class="twins-overhaul-shell twins-family-service-area__grid">';
    $markup .= '<div><p class="twins-overhaul-eyebrow">Regional route</p><h2>' . esc_html($regionName) . ' contact details</h2>';
    $markup .= '<p>This preview uses the fixed regional phone and service-area paths for this page. It adds no new street address or service claim.</p></div>';
    $markup .= '<div class="twins-overhaul-card"><a class="twins-family-service-area__phone" href="tel:' . esc_attr($tel) . '">' . esc_html($phone) . '</a>';
    $markup .= '<a href="' . esc_url($serviceArea) . '">Review the service-area pages</a></div>';
    $markup .= '</div></section>';

    $markup .= '<section class="twins-overhaul-section twins-family-faq"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">Preview questions</p><h2>What this staging page does—and does not do.</h2>';
    $markup .= '<div class="twins-family-faq__list">';
    $markup .= '<details><summary>Does this preview rewrite the published page details?</summary><p>No. The original rendered page body appears above without changing its factual content.</p></details>';
    $markup .= '<details><summary>Can this private preview send a service request?</summary><p>This private staging preview does not submit or store lead information.</p></details>';
    $markup .= '<details><summary>Where can I discuss project-specific details?</summary><p>Use the fixed regional phone or contact page shown on this preview when you choose.</p></details>';
    $markup .= '</div></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-family-closing"><div class="twins-overhaul-shell twins-family-closing__panel">';
    $markup .= '<div><p class="twins-overhaul-eyebrow">Keep moving</p><h2>Bring your page notes to the next conversation.</h2></div>';
    $markup .= '<a class="twins-overhaul-button twins-overhaul-button--closing" href="' . esc_url($contact) . '">Review contact options</a>';
    $markup .= '</div></section></div>';

    return $markup;
}

/**
 * Place an already-completed body inside only the shared skip-target frame.
 *
 * @param string $content Original rendered content.
 * @return string
 */
function twins_overhaul_wrap_preserved_content(string $content): string {
    return '<div id="twins-overhaul-main" class="twins-overhaul-main twins-overhaul-preserved" tabindex="-1"><div class="twins-overhaul-original-content" data-twins-original-content>' . $content . '</div></div>';
}

/**
 * Filter body classes only for a proven chrome-eligible singular request.
 *
 * @param array $classes Existing body classes.
 * @return array
 */
function twins_overhaul_filter_body_classes(array $classes): array {
    if (!twins_overhaul_is_allowed_chrome_request()) {
        return $classes;
    }
    $classification = twins_overhaul_current_classification();
    if ($classification === 'campaign-preserve') {
        $classes[] = 'twins-overhaul-campaign';
        return array_values(array_unique($classes));
    }
    if (!twins_overhaul_uses_brand_chrome($classification)) {
        return $classes;
    }

    $context = twins_overhaul_current_context($classification);
    $classes[] = 'twins-overhaul-preview';
    if (is_singular()) {
        $classes[] = 'twins-overhaul-singular';
    }
    $classes[] = 'twins-overhaul-region-' . $context['key'];
    $routeClass = function_exists('sanitize_html_class')
        ? sanitize_html_class($classification)
        : (twins_overhaul_is_known_classification($classification) ? $classification : '');
    if ($routeClass === '') {
        twins_overhaul_refuse_route('brand route body class is outside the fixed classification map.');
    }
    $classes[] = 'twins-brand-experience';
    $classes[] = 'twins-brand-route-' . $routeClass;
    return array_values(array_unique($classes));
}

/**
 * Keep the recovered family assets only for campaign and the three temporary
 * cost/builder migration exceptions.
 *
 * @param string $classification Fixed classifier outcome.
 * @return bool
 */
function twins_overhaul_uses_legacy_family_assets(string $classification): bool {
    return $classification === 'campaign-preserve'
        || in_array(
            $classification,
            array('cost-madison', 'cost-milwaukee', 'builder'),
            true
        );
}

/**
 * Remove only the fixed legacy assets that attempt avoidable remote requests.
 *
 * The shared preview supplies its own local fonts and does not render a live
 * Google map. Dequeueing (without deregistering) preserves WordPress dependency
 * state while preventing those unused front-end transfers.
 *
 * @return void
 */
function twins_overhaul_dequeue_remote_assets(): void {
    foreach (array(
        'astra-google-fonts',
        'elementor-gf-local-montserrat',
        'elementor-gf-local-prompt',
        'wp-emoji-styles',
    ) as $handle) {
        wp_dequeue_style($handle);
    }

    foreach (array(
        'uael-google-maps',
        'uael-google-maps-api',
        'uael-google-maps-cluster',
    ) as $handle) {
        wp_dequeue_script($handle);
    }

    remove_action('wp_head', 'print_emoji_detection_script', 7);
}

/**
 * Remove only the two fixed Google Fonts resource-hint hosts on eligible pages.
 *
 * @param array  $urls Existing WordPress resource hints.
 * @param string $relationType Resource-hint relationship.
 * @return array
 */
function twins_overhaul_filter_remote_resource_hints(array $urls, string $relationType): array {
    unset($relationType);
    if (!twins_overhaul_is_allowed_chrome_request()) {
        return $urls;
    }

    $blockedHosts = array('fonts.googleapis.com', 'fonts.gstatic.com');
    $filtered = array();
    foreach ($urls as $url) {
        $href = is_array($url) && isset($url['href']) ? (string) $url['href'] : (string) $url;
        $host = wp_parse_url($href, PHP_URL_HOST);
        if (is_string($host) && in_array(strtolower($host), $blockedHosts, true)) {
            continue;
        }
        $filtered[] = $url;
    }
    return $filtered;
}

/**
 * Suppress fixed remote style handles even when a plugin enqueues them late.
 *
 * @param string $html Rendered stylesheet tag.
 * @param string $handle Registered WordPress handle.
 * @param string $href Stylesheet source.
 * @param string $media Stylesheet media value.
 * @return string
 */
function twins_overhaul_filter_isolated_style_tag(string $html, string $handle, string $href, string $media): string {
    unset($href, $media);
    if (!twins_overhaul_is_allowed_chrome_request()) {
        return $html;
    }
    if (in_array($handle, array(
        'astra-google-fonts',
        'elementor-gf-local-montserrat',
        'elementor-gf-local-prompt',
        'wp-emoji-styles',
    ), true)) {
        return '';
    }
    if ($handle === 'twins-staging-twx-v2') {
        $classification = twins_overhaul_current_classification();
        return twins_overhaul_uses_legacy_family_assets($classification) ? $html : '';
    }
    return $html;
}

/**
 * Suppress fixed remote script handles even when a plugin enqueues them late.
 *
 * @param string $tag Rendered script tag.
 * @param string $handle Registered WordPress handle.
 * @param string $src Script source.
 * @return string
 */
function twins_overhaul_filter_isolated_script_tag(string $tag, string $handle, string $src): string {
    unset($src);
    if (!twins_overhaul_is_allowed_chrome_request()) {
        return $tag;
    }
    return in_array($handle, array(
        'uael-google-maps',
        'uael-google-maps-api',
        'uael-google-maps-cluster',
    ), true) ? '' : $tag;
}

/**
 * Remove one fixed broken responsive candidate from the Kentucky legacy logo.
 *
 * The filter authenticates both the fixed Kentucky blog and attachment before
 * changing only its generated srcset. All saved attachment metadata and every
 * other rendered image remain untouched.
 *
 * @param array $attributes Generated image attributes.
 * @param mixed $attachment WordPress attachment object.
 * @param mixed $size Requested image size; never authoritative.
 * @return array
 */
function twins_overhaul_filter_legacy_image_attributes(array $attributes, $attachment, $size): array {
    unset($size);
    if (!twins_overhaul_is_allowed_chrome_request()) {
        return $attributes;
    }
    $attachmentId = is_object($attachment) && isset($attachment->ID)
        ? (int) $attachment->ID
        : 0;
    if (get_current_blog_id() !== 3 || $attachmentId !== 34) {
        return $attributes;
    }
    if (!isset($attributes['srcset']) || !is_string($attributes['srcset'])) {
        return $attributes;
    }

    $brokenCandidate = ', https://danielj140.sg-host.com/ky/wp-content/uploads/sites/3/2022/10/elementor/thumbs/cropped-fav2-py5bi9b5cqal2j4zxrp9139yuajk2yedppx0nusyo8.png 500w';
    $attributes['srcset'] = str_replace(
        $brokenCandidate,
        '',
        $attributes['srcset']
    );
    if (strpos($attributes['srcset'], 'cropped-fav2-py5bi9b5cqal2j4zxrp9139yuajk2yedppx0nusyo8.png') !== false) {
        twins_overhaul_refuse_route('broken Kentucky attachment candidate survived isolation.');
    }
    return $attributes;
}

/**
 * Return the exact Elementor document currently being rendered.
 *
 * Elementor applies its document-content filter before restoring the prior
 * document, so this identity distinguishes the queried singular document from
 * header, footer, popup, loop, and nested template documents. The boundary is
 * zero-input and cannot be replaced by an earlier plugin.
 *
 * @return int Positive document ID, or -1 when identity cannot be proven.
 */
function twins_overhaul_current_elementor_document_id(): int {
    if (!class_exists('Elementor\\Plugin')
        || !isset(\Elementor\Plugin::$instance)
        || !is_object(\Elementor\Plugin::$instance)) {
        return -1;
    }
    $plugin = \Elementor\Plugin::$instance;
    if (!isset($plugin->documents)
        || !is_object($plugin->documents)
        || !method_exists($plugin->documents, 'get_current')) {
        return -1;
    }
    $document = $plugin->documents->get_current();
    if (!is_object($document) || !method_exists($document, 'get_main_id')) {
        return -1;
    }
    $documentId = (int) $document->get_main_id();
    return $documentId > 0 ? $documentId : -1;
}

/**
 * Remove the fixed external map iframe emitted by hidden legacy Elementor
 * chrome and suppress only the duplicate legacy article-title widget.
 *
 * Saved WordPress and Elementor bytes remain unchanged. The exact
 * theme-post-content widget is exempt from title removal so the preserved
 * article body remains authoritative.
 *
 * @param string $content Rendered Elementor widget markup.
 * @param mixed  $widget Elementor widget instance.
 * @return string
 */
function twins_overhaul_filter_legacy_elementor_widget(string $content, $widget): string {
    if ($content === '' || !twins_overhaul_is_allowed_chrome_request()) {
        return $content;
    }

    $brokenKentuckyCandidate = ', https://danielj140.sg-host.com/ky/wp-content/uploads/sites/3/2022/10/elementor/thumbs/cropped-fav2-py5bi9b5cqal2j4zxrp9139yuajk2yedppx0nusyo8.png 500w';
    $filtered = str_replace($brokenKentuckyCandidate, '', $content);
    if (strpos($filtered, 'cropped-fav2-py5bi9b5cqal2j4zxrp9139yuajk2yedppx0nusyo8.png') !== false) {
        twins_overhaul_refuse_route('broken Kentucky legacy image candidate survived isolation.');
    }

    $filtered = preg_replace(
        '~<iframe\b(?=[^>]*\bsrc\s*=\s*(["\'])https://www\.google\.com/maps/embed(?:\?[^"\']*)?\1)[^>]*>.*?</iframe\s*>~is',
        '',
        $filtered
    );
    if (!is_string($filtered)) {
        twins_overhaul_refuse_route('legacy external map isolation failed.');
    }
    if (preg_match(
        '~<iframe\b[^>]*\bsrc\s*=\s*(["\'])https://www\.google\.com/maps/embed(?:\?[^"\']*)?\1~i',
        $filtered
    )) {
        twins_overhaul_refuse_route('legacy external map iframe survived isolation.');
    }

    $widgetName = is_object($widget) && method_exists($widget, 'get_name')
        ? (string) $widget->get_name()
        : '';
    $widgetId = is_object($widget) && method_exists($widget, 'get_id')
        ? (string) $widget->get_id()
        : '';
    if ($widgetName === 'search-form') {
        $filtered = twins_overhaul_make_preserved_forms_inert($filtered);
        if (preg_match('~</?form\b~i', $filtered)) {
            twins_overhaul_refuse_route('Elementor search form survived isolation.');
        }
        return $filtered;
    }
    if ($widgetName === 'theme-post-content') {
        if (!twins_overhaul_is_allowed_singular_request()) {
            return $filtered;
        }
        $classification = twins_overhaul_current_classification();
        $provenance = twins_overhaul_render_provenance();
        if ($provenance === 'elementor-fallback') {
            return '';
        }
        if ($provenance === 'elementor-document') {
            twins_overhaul_assert_rendered_classified_content($filtered, $classification);
            return $filtered;
        }
        if ($provenance === 'main-filter') {
            if (preg_match('~</?form\b~i', $filtered)) {
                twins_overhaul_refuse_route('proven main-filter output retained form markup.');
            }
            if (
                $classification !== 'campaign-preserve'
                && substr_count($filtered, 'id="twins-overhaul-main"') !== 1
            ) {
                twins_overhaul_refuse_route('proven main-filter output lost its fixed wrapper.');
            }
            return $filtered;
        }
        $rendered = twins_overhaul_render_classified_content(
            $classification,
            twins_overhaul_current_context($classification),
            $filtered
        );
        twins_overhaul_render_provenance('elementor-fallback');
        return $rendered;
    }
    if (
        twins_overhaul_current_classification() !== 'article'
        || $widgetName !== 'heading'
        || $widgetId !== 'f96b26a'
    ) {
        return $filtered;
    }

    $title = trim(html_entity_decode(
        strip_tags((string) get_the_title((int) get_queried_object_id())),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    ));
    $filtered = preg_replace_callback(
        '~<h1\b[^>]*>(.*?)</h1\s*>~is',
        static function (array $match) use ($title): string {
            $candidate = trim(html_entity_decode(
                strip_tags((string) $match[1]),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ));
            return hash_equals($title, $candidate) ? '' : (string) $match[0];
        },
        $filtered
    );
    if (!is_string($filtered)) {
        twins_overhaul_refuse_route('legacy article-title isolation failed.');
    }
    return $filtered;
}

/**
 * Admit the one verified legacy Elementor Theme Builder document that renders
 * the Lexington location body instead of the queried location document.
 *
 * This exception is staging-only and bound to the exact current blog, path,
 * queried post type and immutable queried/template IDs observed on the cloned
 * site. No caller input can select a different document.
 *
 * @param int $documentId Current Elementor document ID.
 * @return bool
 */
function twins_overhaul_is_fixed_lexington_theme_document(int $documentId): bool {
    return get_current_blog_id() === 3
        && get_queried_object_id() === 2415
        && get_post_type(2415) === 'location'
        && twins_overhaul_current_request_path() === '/ky/location/lexington/'
        && $documentId === 2427;
}

/**
 * Replace a direct Elementor singular document after Elementor has rendered
 * its elements but before it removes the remaining WordPress content filters.
 *
 * The current document whose immutable ID equals the queried post ID is
 * eligible. The one fixed Lexington Theme Builder identity above is also
 * eligible; all other theme-builder and nested documents remain byte-identical.
 *
 * @param string $content Rendered Elementor document markup.
 * @return string
 */
function twins_overhaul_filter_elementor_document_content(string $content): string {
    if ($content === '' || !twins_overhaul_is_allowed_singular_request()) {
        return $content;
    }

    $documentId = twins_overhaul_current_elementor_document_id();
    if ($documentId < 1) {
        twins_overhaul_refuse_route('Elementor document identity is unavailable.');
    }
    if (
        $documentId !== (int) get_queried_object_id()
        && !twins_overhaul_is_fixed_lexington_theme_document($documentId)
    ) {
        return $content;
    }

    $classification = twins_overhaul_current_classification();
    if ($classification !== 'campaign-preserve' && !twins_overhaul_should_render_chrome($classification)) {
        return $content;
    }

    $provenance = twins_overhaul_render_provenance();
    if ($provenance !== '') {
        twins_overhaul_assert_rendered_classified_content($content, $classification);
        return $content;
    }

    $rendered = twins_overhaul_render_classified_content(
        $classification,
        twins_overhaul_current_context($classification),
        $content
    );
    twins_overhaul_render_provenance('elementor-document');
    return $rendered;
}

/**
 * Make theme and archive search forms non-submitting on eligible staging pages.
 *
 * @param string $form Rendered search-form markup.
 * @param mixed  $args WordPress search-form arguments; never authoritative.
 * @return string
 */
function twins_overhaul_filter_search_form(string $form, $args = array()): string {
    unset($args);
    if (!twins_overhaul_is_allowed_chrome_request()) {
        return $form;
    }
    return twins_overhaul_make_preserved_forms_inert($form);
}

/**
 * Remove only fixed Google Fonts link elements from completed campaign output.
 *
 * The stored campaign body remains untouched. Its visible content and inline
 * presentation remain intact while the local preview stylesheet supplies the
 * packaged fonts without a third-party browser request.
 *
 * @param string $content Exact stored campaign body.
 * @return string
 */
function twins_overhaul_remove_campaign_remote_font_links(string $content): string {
    $filtered = preg_replace_callback(
        '~<link\b[^>]*>~i',
        static function (array $match): string {
            if (!preg_match('~\bhref\s*=\s*(["\'])(.*?)\1~is', $match[0], $hrefMatch)) {
                return $match[0];
            }
            $href = html_entity_decode((string) $hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $host = wp_parse_url($href, PHP_URL_HOST);
            if (is_string($host) && in_array(strtolower($host), array('fonts.googleapis.com', 'fonts.gstatic.com'), true)) {
                return '';
            }
            return $match[0];
        },
        $content
    );
    if (!is_string($filtered)) {
        twins_overhaul_refuse_route('campaign remote-font isolation failed.');
    }
    return $filtered;
}

/**
 * Replace rendered preserved form elements with non-submitting semantic groups.
 *
 * Saved WordPress bodies remain byte-identical. This fixed output-only boundary
 * removes browser submission authority before the footer JavaScript can load,
 * including form markup held inside the completed Careers script template.
 *
 * @param string $content Completed preserved body.
 * @return string
 */
function twins_overhaul_make_preserved_forms_inert(string $content): string {
    $inert = preg_replace_callback(
        '~<form\b[^>]*>~i',
        static function (array $match): string {
            $tag = (string) $match[0];
            foreach (array('\\"', "\\'", '"', "'") as $quote) {
                $tag = preg_replace(
                    '~\s+(?:ac' . 'tion|method|enctype|target)\s*=\s*'
                        . preg_quote($quote, '~') . '[^>]*?' . preg_quote($quote, '~') . '~i',
                    '',
                    $tag
                );
                if (!is_string($tag)) {
                    twins_overhaul_refuse_route('preserved quoted form attributes could not be isolated.');
                }
            }
            $tag = preg_replace(
                '~\s+(?:ac' . 'tion|method|enctype|target)\s*=\s*[^\s>]+~i',
                '',
                $tag
            );
            if (!is_string($tag)) {
                twins_overhaul_refuse_route('preserved unquoted form attributes could not be isolated.');
            }
            $roleCount = preg_match_all('~\srole\s*=~i', $tag);
            if (!is_int($roleCount) || $roleCount > 1) {
                twins_overhaul_refuse_route('preserved form roles are ambiguous.');
            }
            $replacement = $roleCount === 1 ? '<div' : '<div role=form';
            $tag = preg_replace('~^<form\b~i', $replacement, $tag, 1);
            if (!is_string($tag) || substr($tag, -1) !== '>') {
                twins_overhaul_refuse_route('preserved form element could not be isolated.');
            }
            return substr($tag, 0, -1) . ' data-twins-staging-form-inert=true>';
        },
        $content
    );
    if (!is_string($inert)) {
        twins_overhaul_refuse_route('preserved forms could not be isolated.');
    }
    $inert = preg_replace('~</form\s*>~i', '</div>', $inert);
    if (!is_string($inert)) {
        twins_overhaul_refuse_route('preserved form closures could not be isolated.');
    }
    $inert = preg_replace_callback(
        '~\btype\s*=\s*([^\s>]+)~i',
        static function (array $match): string {
            $activeTypes = array(
                'submit',
                'image',
                '"submit"',
                '"image"',
                "'submit'",
                "'image'",
                '\\"submit\\"',
                '\\"image\\"',
                "\\'submit\\'",
                "\\'image\\'",
            );
            return in_array(strtolower((string) $match[1]), $activeTypes, true)
                ? 'type=button'
                : (string) $match[0];
        },
        $inert
    );
    if (!is_string($inert)) {
        twins_overhaul_refuse_route('preserved form controls could not be isolated.');
    }
    return $inert;
}

/**
 * Return the eligible-page CSP with all browser submission channels disabled.
 *
 * @return string
 */
function twins_overhaul_inert_csp_policy(): string {
    if (!function_exists('twins_staging_safety_csp_policy')) {
        twins_overhaul_refuse_route('staging safety CSP policy is unavailable.');
    }
    $policy = (string) twins_staging_safety_csp_policy();
    if (
        substr_count($policy, "connect-src 'self'") !== 1
        || substr_count($policy, "form-action 'self'") !== 1
    ) {
        twins_overhaul_refuse_route('staging safety CSP policy changed unexpectedly.');
    }
    $policy = str_replace(
        array("connect-src 'self'", "form-action 'self'"),
        array("connect-src 'none'", "form-action 'none'"),
        $policy
    );
    if (
        substr_count($policy, "connect-src 'none'") !== 1
        || substr_count($policy, "form-action 'none'") !== 1
    ) {
        twins_overhaul_refuse_route('inert staging CSP could not be proven.');
    }
    return $policy;
}

/**
 * Replace the safety header only on a proven eligible front-end document.
 *
 * @return void
 */
function twins_overhaul_send_inert_response_boundary(): void {
    if (!twins_overhaul_is_allowed_chrome_request()) {
        return;
    }
    header('Content-Security-Policy: ' . twins_overhaul_inert_csp_policy(), true);
}

/**
 * Register after every MU plugin has loaded so the fixed hardening header and
 * asset isolation both run after the general staging safety callbacks.
 *
 * @return void
 */
function twins_overhaul_register_inert_response_boundary(): void {
    add_action('send_headers', 'twins_overhaul_send_inert_response_boundary', PHP_INT_MAX, 0);
    add_action('wp_enqueue_scripts', 'twins_overhaul_enqueue_assets', PHP_INT_MAX, 0);
}

/**
 * Mark packaged fonts as available before the preserved Careers body executes.
 *
 * @return void
 */
function twins_overhaul_output_local_font_sentinel(): void {
    static $rendered = false;
    if ($rendered || !twins_overhaul_is_allowed_chrome_request()) {
        return;
    }
    $rendered = true;
    echo '<meta id="twx-mont" name="twins-staging-local-fonts" content="enabled">';
}

/**
 * Return a bounded content-derived version for one fixed portable brand asset.
 *
 * @param string $relativePath Fixed portable asset path.
 * @return string
 */
function twins_overhaul_brand_asset_version(string $relativePath): string {
    $allowed = array('assets/css/twins-brand.css', 'assets/js/twins-brand.js');
    if (!in_array($relativePath, $allowed, true)) {
        twins_overhaul_refuse_route('brand asset path is outside the fixed allowlist.');
    }
    $root = dirname(__DIR__) . '/twins-brand-experience/';
    if (PHP_SAPI === 'cli' && !is_dir($root)) {
        $root = dirname(__DIR__, 3) . '/twins-brand-experience/';
    }
    $path = $root . $relativePath;
    $stat = @lstat($path);
    if (!is_array($stat) || is_link($path) || !is_file($path)) {
        twins_overhaul_refuse_route('brand asset is not a bounded regular file.');
    }
    $size = @filesize($path);
    if (!is_int($size) || $size < 1 || $size > 2097152) {
        twins_overhaul_refuse_route('brand asset size is outside the fixed boundary.');
    }
    return substr(hash_file('sha256', $path), 0, 16);
}

/**
 * Enqueue the isolated campaign assets or portable brand assets on approved
 * requests, retaining only the fixed temporary cost/builder support pair.
 *
 * @return void
 */
function twins_overhaul_enqueue_assets(): void {
    if (!twins_overhaul_is_allowed_chrome_request()) {
        return;
    }
    twins_overhaul_dequeue_remote_assets();

    $classification = twins_overhaul_current_classification();
    $usesLegacyFamilyAssets = twins_overhaul_uses_legacy_family_assets($classification);
    if ($usesLegacyFamilyAssets) {
        wp_enqueue_style('twins-staging-overhaul', twins_overhaul_asset_url('stylesheet'), array(), 'a3fb61ed0da87e83');
        wp_enqueue_script('twins-staging-overhaul', twins_overhaul_asset_url('script'), array(), '549faf277bbadc3d', true);
    }

    if (!twins_overhaul_uses_brand_chrome($classification)) {
        return;
    }

    if (!$usesLegacyFamilyAssets) {
        wp_dequeue_style('twins-staging-twx-v2');
    }

    $runtime = twins_overhaul_brand_runtime();
    $handles = $runtime->assetHandles();
    if (($handles['style'] ?? null) !== 'twins-brand-experience' || ($handles['script'] ?? null) !== 'twins-brand-experience') {
        twins_overhaul_refuse_route('portable brand asset handles changed unexpectedly.');
    }
    $base = rtrim(content_url('mu-plugins/twins-brand-experience'), '/');
    $styleDependencies = $usesLegacyFamilyAssets ? array('twins-staging-overhaul') : array();
    wp_enqueue_style(
        'twins-brand-experience',
        $base . '/assets/css/twins-brand.css',
        $styleDependencies,
        twins_overhaul_brand_asset_version('assets/css/twins-brand.css')
    );
    wp_enqueue_script(
        'twins-brand-experience',
        $base . '/assets/js/twins-brand.js',
        array(),
        twins_overhaul_brand_asset_version('assets/js/twins-brand.js'),
        true
    );
}

/**
 * Print the shared header once for a proven chrome-eligible request.
 *
 * @return void
 */
function twins_overhaul_output_header(): void {
    static $rendered = false;
    if ($rendered || !twins_overhaul_is_allowed_chrome_request()) {
        return;
    }
    $classification = twins_overhaul_current_classification();
    if (!twins_overhaul_should_render_chrome($classification)) {
        return;
    }

    $rendered = true;
    echo twins_overhaul_render_header(twins_overhaul_current_context($classification));
}

/**
 * Track which fixed renderer produced the current request body.
 *
 * The marker is request-local code state, not caller content. Only the two
 * fixed producers can set it, preventing a legacy body from spoofing the main
 * wrapper to bypass the Elementor fallback.
 *
 * @param string $mark Empty to read, or one fixed producer marker to set.
 * @return string
 */
function twins_overhaul_render_provenance(string $mark = ''): string {
    static $provenance = '';
    if ($mark === '') {
        return $provenance;
    }
    if (!in_array($mark, array('main-filter', 'elementor-fallback', 'elementor-document'), true)) {
        twins_overhaul_refuse_route('render provenance marker is invalid.');
    }
    if ($provenance !== '' && $provenance !== $mark) {
        twins_overhaul_refuse_route('render provenance changed within one request.');
    }
    $provenance = $mark;
    return $provenance;
}

/**
 * Render one already-classified singular body through the fixed staging family.
 *
 * This is shared by WordPress's ordinary main-loop filter and the exact
 * Elementor theme-post-content fallback. Every legacy body that remains
 * visible is made non-submitting before it enters a preserved or editorial
 * frame; generated families retain their stricter family-specific sanitizer.
 *
 * @param string $classification Fixed route classification.
 * @param array  $context Proven current request context.
 * @param string $content Original rendered WordPress or Elementor content.
 * @return string
 */
function twins_overhaul_render_classified_content(string $classification, array $context, string $content): string {
    if (!twins_overhaul_is_known_classification($classification)) {
        twins_overhaul_refuse_route('classified content type is outside the fixed renderer map.');
    }

    $brandRenderers = array(
        'home-brand' => 'renderHome',
        'team-brand' => 'renderTeam',
        'careers-brand' => 'renderCareers',
        'reviews-brand' => 'renderReviews',
        'contact-brand' => 'renderContact',
    );

    if (isset($brandRenderers[$classification])) {
        $runtime = twins_overhaul_brand_runtime();
        $rendered = $runtime->{$brandRenderers[$classification]}($context);
    } elseif ($classification === 'campaign-preserve') {
        $rendered = twins_overhaul_make_preserved_forms_inert(
            twins_overhaul_remove_campaign_remote_font_links($content)
        );
    } elseif ($classification === 'catalog-preserve') {
        $rendered = twins_overhaul_wrap_preserved_content(
            twins_overhaul_make_preserved_forms_inert($content)
        );
    } elseif ($classification === 'legal-preserve' || $classification === 'article') {
        $rendered = twins_overhaul_render_article_template(
            $context,
            twins_overhaul_make_preserved_forms_inert($content)
        );
    } elseif ($classification === 'service') {
        $rendered = twins_overhaul_render_service_template($context, $content);
    } elseif ($classification === 'location') {
        $rendered = twins_overhaul_render_location_template($context, $content);
    } elseif ($classification === 'trust') {
        $rendered = twins_overhaul_render_trust_template($context, $content);
    } elseif ($classification === 'cost-madison' && function_exists('twins_overhaul_render_cost_page')) {
        $rendered = twins_overhaul_render_cost_page('madison', $context);
    } elseif ($classification === 'cost-milwaukee' && function_exists('twins_overhaul_render_cost_page')) {
        $rendered = twins_overhaul_render_cost_page('milwaukee', $context);
    } elseif ($classification === 'builder' && function_exists('twins_overhaul_render_builder')) {
        $rendered = twins_overhaul_render_builder($context);
    } else {
        twins_overhaul_refuse_route('classified content has no fixed renderer.');
    }

    if (!is_string($rendered) || preg_match('~</?form\b~i', $rendered)) {
        twins_overhaul_refuse_route('classified staging output retained form markup.');
    }
    return $rendered;
}

/**
 * Revalidate output already produced by one authenticated request-local path.
 *
 * @param string $content Previously rendered classified output.
 * @param string $classification Fixed route classification.
 * @return void
 */
function twins_overhaul_assert_rendered_classified_content(string $content, string $classification): void {
    if (preg_match('~</?form\b~i', $content)) {
        twins_overhaul_refuse_route('authenticated classified output retained form markup.');
    }
    if (
        $classification !== 'campaign-preserve'
        && substr_count($content, 'id="twins-overhaul-main"') !== 1
    ) {
        twins_overhaul_refuse_route('authenticated classified output lost its fixed wrapper.');
    }
}

/**
 * Replace only the first eligible main-loop body.
 *
 * @param string $content Original rendered WordPress content.
 * @return string
 */
function twins_overhaul_replace_main_content(string $content): string {
    static $rendered = false;
    if (
        $rendered
        || !twins_overhaul_is_allowed_singular_request()
        || !is_main_query()
        || !in_the_loop()
    ) {
        return $content;
    }

    $classification = twins_overhaul_current_classification();
    if ($classification !== 'campaign-preserve' && !twins_overhaul_should_render_chrome($classification)) {
        return $content;
    }

    if (twins_overhaul_render_provenance() !== '') {
        twins_overhaul_assert_rendered_classified_content($content, $classification);
        $rendered = true;
        return $content;
    }

    $context = twins_overhaul_current_context($classification);
    $output = twins_overhaul_render_classified_content($classification, $context, $content);
    twins_overhaul_render_provenance('main-filter');
    $rendered = true;
    return $output;
}

/**
 * Print the shared footer once for a proven chrome-eligible request.
 *
 * @return void
 */
function twins_overhaul_output_footer(): void {
    static $rendered = false;
    if ($rendered || !twins_overhaul_is_allowed_chrome_request()) {
        return;
    }
    $classification = twins_overhaul_current_classification();
    if (!twins_overhaul_should_render_chrome($classification)) {
        return;
    }

    $rendered = true;
    echo twins_overhaul_render_footer(twins_overhaul_current_context($classification));
}

/**
 * Register the complete front-end preview hook set after every root gate passed.
 *
 * @return void
 */
function twins_overhaul_register_frontend_hooks(): void {
    add_action('muplugins_loaded', 'twins_overhaul_register_inert_response_boundary', PHP_INT_MAX, 0);
    add_filter('body_class', 'twins_overhaul_filter_body_classes', 20, 1);
    add_filter('wp_resource_hints', 'twins_overhaul_filter_remote_resource_hints', PHP_INT_MAX, 2);
    add_filter('style_loader_tag', 'twins_overhaul_filter_isolated_style_tag', PHP_INT_MAX, 4);
    add_filter('script_loader_tag', 'twins_overhaul_filter_isolated_script_tag', PHP_INT_MAX, 3);
    add_filter('wp_get_attachment_image_attributes', 'twins_overhaul_filter_legacy_image_attributes', PHP_INT_MAX, 3);
    add_filter('elementor/widget/render_content', 'twins_overhaul_filter_legacy_elementor_widget', PHP_INT_MAX, 2);
    add_filter('elementor/frontend/the_content', 'twins_overhaul_filter_elementor_document_content', PHP_INT_MAX, 1);
    add_filter('get_search_form', 'twins_overhaul_filter_search_form', PHP_INT_MAX, 2);
    add_action('wp_head', 'twins_overhaul_output_local_font_sentinel', 1, 0);
    add_action('wp_body_open', 'twins_overhaul_output_header', 5, 0);
    add_filter('the_content', 'twins_overhaul_replace_main_content', PHP_INT_MAX, 1);
    add_action('wp_footer', 'twins_overhaul_output_footer', 5, 0);
}

twins_overhaul_register_frontend_hooks();
