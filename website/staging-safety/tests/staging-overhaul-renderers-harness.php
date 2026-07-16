<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_ENVIRONMENT_TYPE', 'staging');
define('TWINS_STAGING_SAFETY', true);
define('DISABLE_WP_CRON', true);
define('REST_REQUEST', false);

final class Twins_Overhaul_Renderer_Refusal extends RuntimeException
{
    public int $response;

    public function __construct(string $message, int $response)
    {
        parent::__construct($message);
        $this->response = $response;
    }
}

final class Twins_Overhaul_Renderer_Widget
{
    private string $name;
    private string $id;

    public function __construct(string $name, string $id = '')
    {
        $this->name = $name;
        $this->id = $id;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_id(): string
    {
        return $this->id;
    }
}

final class Twins_Overhaul_Renderer_Elementor_Document
{
    private int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function get_main_id(): int
    {
        return $this->id;
    }
}

final class Twins_Overhaul_Renderer_Elementor_Documents
{
    public function get_current(): object
    {
        return new Twins_Overhaul_Renderer_Elementor_Document(
            (int) $GLOBALS['twins_overhaul_renderer_state']['elementorDocumentId']
        );
    }
}

final class Twins_Overhaul_Renderer_Elementor_Plugin
{
    public static $instance;
}

class_alias('Twins_Overhaul_Renderer_Elementor_Plugin', 'Elementor\\Plugin');

$GLOBALS['twins_overhaul_renderer_hooks'] = [];
$GLOBALS['twins_overhaul_renderer_assets'] = [];
$GLOBALS['twins_overhaul_renderer_style_queue'] = [];
$GLOBALS['twins_overhaul_renderer_dequeued_styles'] = [];
$GLOBALS['twins_overhaul_renderer_dequeued_scripts'] = [];
$GLOBALS['twins_overhaul_renderer_removed_actions'] = [];
$GLOBALS['twins_overhaul_renderer_state'] = [
    'blogId' => 1,
    'path' => '/',
    'postType' => 'page',
    'postId' => 1,
    'renderedPostType' => 'page',
    'renderedPostId' => 1,
    'title' => 'Twins Garage Doors',
    'admin' => false,
    'ajax' => false,
    'feed' => false,
    'preview' => false,
    'archive' => false,
    'search' => false,
    '404' => false,
    'singular' => true,
    'home' => false,
    'mainQuery' => true,
    'loop' => true,
    'elementorDocumentId' => 0,
];
Twins_Overhaul_Renderer_Elementor_Plugin::$instance = (object) [
    'documents' => new Twins_Overhaul_Renderer_Elementor_Documents(),
];

function wp_die($message, $title = '', $arguments = []): void
{
    unset($title);
    $response = is_array($arguments)
        ? (int) ($arguments['response'] ?? $arguments['status'] ?? 0)
        : (int) $arguments;
    throw new Twins_Overhaul_Renderer_Refusal((string) $message, $response);
}

function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1): bool
{
    $GLOBALS['twins_overhaul_renderer_hooks'][] = ['action', $hook, $callback, $priority, $acceptedArgs];
    return true;
}

function add_filter($hook, $callback, $priority = 10, $acceptedArgs = 1): bool
{
    $GLOBALS['twins_overhaul_renderer_hooks'][] = ['filter', $hook, $callback, $priority, $acceptedArgs];
    return true;
}

function remove_action($hook, $callback, $priority = 10): bool
{
    $GLOBALS['twins_overhaul_renderer_removed_actions'][] = ['action', $hook, $callback, $priority];
    return true;
}

function esc_html($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_url($url): string
{
    return (string) $url;
}

function wp_kses($html, $allowedHtml): string
{
    $allowedTags = array_keys((array) $allowedHtml);
    $tagList = $allowedTags === [] ? '' : '<' . implode('><', $allowedTags) . '>';
    $stripped = strip_tags((string) $html, $tagList);
    return (string) preg_replace_callback(
        '~<\s*(/?)\s*([a-z0-9]+)\b[^>]*>~i',
        static function (array $match) use ($allowedTags): string {
            $tag = strtolower($match[2]);
            return in_array($tag, $allowedTags, true) ? '<' . $match[1] . $tag . '>' : '';
        },
        $stripped
    );
}

function wp_parse_url($url, $component = -1)
{
    return parse_url((string) $url, (int) $component);
}

function network_home_url($path = ''): string
{
    return 'https://stage.example.test/' . ltrim((string) $path, '/');
}

function content_url($path = ''): string
{
    return 'https://stage.example.test/wp-content/' . ltrim((string) $path, '/');
}

function twins_staging_safety_csp_policy(): string
{
    return "default-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'self';";
}

function get_current_blog_id(): int
{
    return (int) $GLOBALS['twins_overhaul_renderer_state']['blogId'];
}

function get_queried_object_id(): int
{
    return (int) $GLOBALS['twins_overhaul_renderer_state']['postId'];
}

function get_post_type($post = null): string
{
    unset($post);
    return (string) $GLOBALS['twins_overhaul_renderer_state']['postType'];
}

function get_the_title($post = 0): string
{
    unset($post);
    return (string) $GLOBALS['twins_overhaul_renderer_state']['title'];
}

function is_admin(): bool
{
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['admin'];
}

function wp_doing_ajax(): bool
{
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['ajax'];
}

function is_feed(): bool
{
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['feed'];
}

function is_preview(): bool
{
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['preview'];
}

function is_archive(): bool
{
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['archive'];
}

function is_search(): bool
{
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['search'];
}

function is_404(): bool
{
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['404'];
}

function is_singular($postTypes = ''): bool
{
    unset($postTypes);
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['singular'];
}

function is_home(): bool
{
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['home'];
}

function is_main_query(): bool
{
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['mainQuery'];
}

function in_the_loop(): bool
{
    return (bool) $GLOBALS['twins_overhaul_renderer_state']['loop'];
}

function is_attachment(): bool
{
    return $GLOBALS['twins_overhaul_renderer_state']['postType'] === 'attachment';
}

function wp_enqueue_style($handle, $src = '', $deps = [], $version = false, $media = 'all'): bool
{
    $GLOBALS['twins_overhaul_renderer_assets'][] = ['style', $handle, $src, $deps, $version, $media];
    $GLOBALS['twins_overhaul_renderer_style_queue'][(string) $handle] = true;
    return true;
}

function wp_enqueue_script($handle, $src = '', $deps = [], $version = false, $inFooter = false): bool
{
    $GLOBALS['twins_overhaul_renderer_assets'][] = ['script', $handle, $src, $deps, $version, $inFooter];
    return true;
}

function wp_dequeue_style($handle): void
{
    $GLOBALS['twins_overhaul_renderer_dequeued_styles'][] = (string) $handle;
    unset($GLOBALS['twins_overhaul_renderer_style_queue'][(string) $handle]);
}

function wp_dequeue_script($handle): void
{
    $GLOBALS['twins_overhaul_renderer_dequeued_scripts'][] = (string) $handle;
}

function twins_overhaul_renderer_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function twins_staging_safety_enqueue_visual_preview_styles(): void
{
    wp_enqueue_style('twins-staging-twx-v2', '/wp-content/mu-plugins/twins-staging-assets/twx-v2-kit.css', [], '2026.07.13.3');
}

function twins_overhaul_renderer_hooks(string $kind, string $name): array
{
    return array_values(array_filter(
        $GLOBALS['twins_overhaul_renderer_hooks'],
        static fn(array $hook): bool => $hook[0] === $kind && $hook[1] === $name
    ));
}

function twins_overhaul_renderer_hook(string $kind, string $name): array
{
    $matches = twins_overhaul_renderer_hooks($kind, $name);
    twins_overhaul_renderer_assert(count($matches) === 1, 'hook count mismatch: ' . $kind . ':' . $name);
    return $matches[0];
}

function twins_overhaul_renderer_set(array $changes): void
{
    $GLOBALS['twins_overhaul_renderer_state'] = array_replace($GLOBALS['twins_overhaul_renderer_state'], $changes);
    $_SERVER['REQUEST_URI'] = (string) $GLOBALS['twins_overhaul_renderer_state']['path'];
    $GLOBALS['post'] = (object) [
        'ID' => (int) $GLOBALS['twins_overhaul_renderer_state']['renderedPostId'],
        'post_type' => (string) $GLOBALS['twins_overhaul_renderer_state']['renderedPostType'],
    ];
}

if ($argc !== 3 || !is_file($argv[1])) {
    fwrite(STDERR, "STAGING_OVERHAUL_PACKAGE_MISSING\n");
    exit(2);
}

$scenario = $argv[2];
if (!in_array($scenario, ['routes', 'asset-versions', 'hooks', 'blog-index', 'campaign', 'family-once', 'path-contact-context', 'service-brand-chrome', 'catalog-brand-chrome', 'home-brand', 'team-brand', 'careers-brand', 'reviews-brand', 'contact-brand', 'elementor-theme-content', 'elementor-document-content', 'legacy-location-document', 'ineligible', 'article', 'unknown-blog'], true)) {
    fwrite(STDERR, "UNKNOWN_RENDERER_SCENARIO\n");
    exit(2);
}

twins_overhaul_renderer_set([]);
require $argv[1];

if ($scenario === 'routes') {
    $cases = [
        [1, '/madison-garage-door-repair-lp/', 'page', 7092, 'campaign-preserve'],
        [1, '/madison-tune-up-lp/', 'page', 7093, 'campaign-preserve'],
        [1, '/careers/', 'page', 7341, 'careers-brand'],
        [1, '/our-team/', 'page', 6955, 'team-brand'],
        [1, '/reviews/', 'page', 2186, 'reviews-brand'],
        [4, '/wi/reviews/', 'page', 2186, 'reviews-brand'],
        [1, '/contact-us/', 'page', 2030, 'contact-brand'],
        [3, '/ky/contact-us/', 'page', 2030, 'contact-brand'],
        [1, '/clopay-garage-doors/', 'page', 7141, 'catalog-preserve'],
        [1, '/clopay-classic-collection/', 'page', 6034, 'catalog-preserve'],
        [1, '/privacy-policy/', 'page', 2009, 'legal-preserve'],
        [4, '/wi/privacy-policy/', 'page', 0, 'legal-preserve'],
        [4, '/wi/thank-you-g-ppc-lp/', 'page', 0, 'legal-preserve'],
        [4, '/wi/garage-door-cost-in-madison-wi/', 'page', 6807, 'cost-madison'],
        [4, '/wi/garage-door-cost-in-milwaukee-wi/', 'page', 6808, 'cost-milwaukee'],
        [1, '/door-builder/', 'page', 7129, 'builder'],
        [4, '/wi/door-builder/', 'page', 6766, 'builder'],
        [3, '/ky/design-your-door/', 'page', 0, 'builder'],
        [5, '/il/door-builder/', 'page', 0, 'builder'],
        [1, '/', 'page', 1, 'home-brand'],
        [3, '/ky/', 'page', 1, 'home-brand'],
        [4, '/wi/', 'page', 1, 'home-brand'],
        [5, '/il/', 'page', 1, 'home-brand'],
        [5, '/il/location/rockford/', 'location', 0, 'location'],
        [1, '/location/madison/', 'page', 0, 'location'],
        [4, '/wi/service-area/', 'page', 0, 'location'],
        [4, '/wi/garage-door-repair-in-milwaukee-wi/', 'page', 0, 'location'],
        [3, '/ky/about-us/', 'page', 0, 'trust'],
        [4, '/wi/garage-door-spring-repair/', 'page', 0, 'service'],
        [5, '/il/garage-door-installation/', 'page', 0, 'service'],
        [1, '/garage-door-repair/', 'page', 0, 'service'],
        [3, '/ky/garage-door-repair/', 'page', 0, 'service'],
        [4, '/wi/garage-door-repair/', 'page', 0, 'service'],
        [5, '/il/garage-door-repair/', 'page', 0, 'service'],
        [1, '/published-story/', 'post', 0, 'article'],
        [1, '/unknown-page/', 'page', 0, 'article'],
        [1, '/about-us-extra/', 'page', 0, 'article'],
        [1, '/garage-door-spring-repair-guide/', 'page', 0, 'article'],
        [4, '/ky/design-your-door/', 'page', 0, 'article'],
        [4, '/wi/anything-wi/', 'page', 0, 'article'],
    ];
    foreach ($cases as [$blogId, $path, $postType, $postId, $expected]) {
        $actual = twins_overhaul_classify_request($blogId, $path, $postType, $postId);
        twins_overhaul_renderer_assert($actual === $expected, $path . ' expected ' . $expected . ', got ' . $actual);
    }

    $unsafePaths = [
        'wi/garage-door-services/',
        '/wi//garage-door-services/',
        '/wi/../garage-door-services/',
        '/wi/./garage-door-services/',
        '/wi/garage-door-services/?route=other',
        '/wi/garage-door-services/#other',
        '/wi%2fgarage-door-services/',
        '/' . str_repeat('a', 300) . '/',
    ];
    foreach ($unsafePaths as $unsafePath) {
        $refusal = null;
        try {
            twins_overhaul_classify_request(4, $unsafePath, 'page', 0);
        } catch (Twins_Overhaul_Renderer_Refusal $exception) {
            $refusal = $exception;
        }
        twins_overhaul_renderer_assert($refusal instanceof Twins_Overhaul_Renderer_Refusal, 'unsafe path did not fail closed: ' . $unsafePath);
        twins_overhaul_renderer_assert($refusal->response === 503, 'unsafe path refusal did not use 503');
    }

    $crossRegionLocation = null;
    try {
        twins_overhaul_classify_request(4, '/ky/location/lexington/', 'location', 0);
    } catch (Twins_Overhaul_Renderer_Refusal $exception) {
        $crossRegionLocation = $exception;
    }
    twins_overhaul_renderer_assert($crossRegionLocation instanceof Twins_Overhaul_Renderer_Refusal, 'cross-region location post type did not fail closed');
    twins_overhaul_renderer_assert($crossRegionLocation->response === 503, 'cross-region location refusal did not use 503');

    $unknownBlog = null;
    try {
        twins_overhaul_classify_request(2, '/', 'page', 0);
    } catch (Twins_Overhaul_Renderer_Refusal $exception) {
        $unknownBlog = $exception;
    }
    twins_overhaul_renderer_assert($unknownBlog instanceof Twins_Overhaul_Renderer_Refusal, 'unknown blog did not fail closed');
    twins_overhaul_renderer_assert($unknownBlog->response === 503, 'unknown blog refusal did not use 503');
    twins_overhaul_renderer_assert(twins_overhaul_should_render_chrome('campaign-preserve') === false, 'campaign chrome was enabled');
    twins_overhaul_renderer_assert(twins_overhaul_should_render_chrome('article') === true, 'article chrome was disabled');
    twins_overhaul_renderer_assert(twins_overhaul_should_render_chrome('unknown') === false, 'unknown classification enabled chrome');

    twins_overhaul_renderer_set([
        'blogId' => 4,
        'path' => '/wi/garage-door-cost-in-madison-wi/',
        'postType' => 'page',
        'postId' => 6807,
        'renderedPostType' => 'page',
        'renderedPostId' => 6807,
        'title' => 'Garage Door Cost in Madison, WI',
    ]);
    twins_overhaul_enqueue_assets();
    twins_overhaul_renderer_assert(count($GLOBALS['twins_overhaul_renderer_assets']) === 4, 'cost route did not retain exactly two legacy support assets plus two portable assets');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_assets'][0][1] === 'twins-staging-overhaul', 'cost support stylesheet handle changed');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_assets'][1][1] === 'twins-staging-overhaul', 'cost support script handle changed');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_assets'][2][1] === 'twins-brand-experience', 'cost portable stylesheet handle changed');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_assets'][3][1] === 'twins-brand-experience', 'cost portable script handle changed');
    twins_overhaul_renderer_assert(!in_array('twins-staging-twx-v2', $GLOBALS['twins_overhaul_renderer_dequeued_styles'], true), 'cost route lost its temporary visual-kit support');
    twins_overhaul_renderer_assert(
        twins_overhaul_filter_isolated_style_tag('<link id="twins-staging-twx-v2-css">', 'twins-staging-twx-v2', '/local.css', 'all') === '<link id="twins-staging-twx-v2-css">',
        'cost route late-filtered its temporary visual-kit support'
    );
}

if ($scenario === 'asset-versions') {
    $brandRoot = dirname($argv[1], 3) . '/twins-brand-experience/';
    $assets = [
        'assets/css/twins-brand.css',
        'assets/js/twins-brand.js',
    ];
    foreach ($assets as $relativePath) {
        $bytes = @file_get_contents($brandRoot . $relativePath);
        twins_overhaul_renderer_assert(is_string($bytes) && $bytes !== '', 'portable asset bytes are unavailable: ' . $relativePath);
        $expected = substr(hash('sha256', $bytes), 0, 16);
        twins_overhaul_renderer_assert(
            twins_overhaul_brand_asset_version($relativePath) === $expected,
            'portable asset version is not an independently derived SHA-256 prefix: ' . $relativePath
        );
    }

    foreach ([
        '',
        '/assets/css/twins-brand.css',
        'assets/css/../css/twins-brand.css',
        'assets/css/twins-brand.css?changed=1',
        'assets\\js\\twins-brand.js',
        'assets/js/not-brand.js',
    ] as $unsafeAssetPath) {
        $refusal = null;
        try {
            twins_overhaul_brand_asset_version($unsafeAssetPath);
        } catch (Twins_Overhaul_Renderer_Refusal $exception) {
            $refusal = $exception;
        }
        twins_overhaul_renderer_assert(
            $refusal instanceof Twins_Overhaul_Renderer_Refusal,
            'unsafe portable asset path did not fail closed: ' . $unsafeAssetPath
        );
        twins_overhaul_renderer_assert($refusal->response === 503, 'unsafe portable asset path refusal did not use 503');
    }
}

if ($scenario === 'hooks') {
    $muPluginsLoadedHook = twins_overhaul_renderer_hook('action', 'muplugins_loaded');
    twins_overhaul_renderer_assert(twins_overhaul_renderer_hooks('action', 'wp_enqueue_scripts') === [], 'overhaul enqueue hook registered before all MU plugins loaded');
    add_action('wp_enqueue_scripts', 'twins_staging_safety_enqueue_visual_preview_styles', PHP_INT_MAX, 0);
    $safetyEnqueueHooks = twins_overhaul_renderer_hooks('action', 'wp_enqueue_scripts');
    twins_overhaul_renderer_assert(count($safetyEnqueueHooks) === 1 && $safetyEnqueueHooks[0][2] === 'twins_staging_safety_enqueue_visual_preview_styles', 'safety visual-kit hook order setup failed');
    ($muPluginsLoadedHook[2])();
    $enqueueHooks = twins_overhaul_renderer_hooks('action', 'wp_enqueue_scripts');
    twins_overhaul_renderer_assert(
        array_column($enqueueHooks, 2) === ['twins_staging_safety_enqueue_visual_preview_styles', 'twins_overhaul_enqueue_assets'],
        'overhaul enqueue hook was not registered after the safety visual-kit hook'
    );
    $enqueueHook = $enqueueHooks[1];
    $bodyHook = twins_overhaul_renderer_hook('filter', 'body_class');
    $resourceHintsHook = twins_overhaul_renderer_hook('filter', 'wp_resource_hints');
    $styleTagHook = twins_overhaul_renderer_hook('filter', 'style_loader_tag');
    $scriptTagHook = twins_overhaul_renderer_hook('filter', 'script_loader_tag');
    $elementorWidgetHook = twins_overhaul_renderer_hook('filter', 'elementor/widget/render_content');
    $elementorDocumentHook = twins_overhaul_renderer_hook('filter', 'elementor/frontend/the_content');
    $imageAttributesHook = twins_overhaul_renderer_hook('filter', 'wp_get_attachment_image_attributes');
    $searchFormHook = twins_overhaul_renderer_hook('filter', 'get_search_form');
    $fontSentinelHook = twins_overhaul_renderer_hook('action', 'wp_head');
    $headerHook = twins_overhaul_renderer_hook('action', 'wp_body_open');
    $contentHook = twins_overhaul_renderer_hook('filter', 'the_content');
    $footerHook = twins_overhaul_renderer_hook('action', 'wp_footer');
    twins_overhaul_renderer_assert($muPluginsLoadedHook[2] === 'twins_overhaul_register_inert_response_boundary', 'inert response-boundary registration callback mismatch');
    twins_overhaul_renderer_assert($muPluginsLoadedHook[3] === PHP_INT_MAX && $muPluginsLoadedHook[4] === 0, 'inert response-boundary registration priority mismatch');
    $sendHeadersHook = twins_overhaul_renderer_hook('action', 'send_headers');
    twins_overhaul_renderer_assert($sendHeadersHook[2] === 'twins_overhaul_send_inert_response_boundary', 'inert response header callback mismatch');
    twins_overhaul_renderer_assert($sendHeadersHook[3] === PHP_INT_MAX && $sendHeadersHook[4] === 0, 'inert response header priority mismatch');
    $inertPolicy = twins_overhaul_inert_csp_policy();
    twins_overhaul_renderer_assert(strpos($inertPolicy, "connect-src 'none'") !== false, 'inert CSP retained connection authority');
    twins_overhaul_renderer_assert(strpos($inertPolicy, "form-action 'none'") !== false, 'inert CSP retained form authority');
    twins_overhaul_renderer_assert(strpos($inertPolicy, "connect-src 'self'") === false && strpos($inertPolicy, "form-action 'self'") === false, 'inert CSP retained the prior same-origin directives');
    twins_overhaul_renderer_assert($bodyHook[2] === 'twins_overhaul_filter_body_classes', 'body-class callback mismatch');
    twins_overhaul_renderer_assert($enqueueHook[2] === 'twins_overhaul_enqueue_assets', 'enqueue callback mismatch');
    twins_overhaul_renderer_assert($enqueueHook[3] === PHP_INT_MAX && $enqueueHook[4] === 0, 'enqueue callback priority mismatch');
    twins_overhaul_renderer_assert($resourceHintsHook[2] === 'twins_overhaul_filter_remote_resource_hints', 'resource-hints callback mismatch');
    twins_overhaul_renderer_assert($resourceHintsHook[3] === PHP_INT_MAX && $resourceHintsHook[4] === 2, 'resource-hints callback priority mismatch');
    twins_overhaul_renderer_assert($fontSentinelHook[2] === 'twins_overhaul_output_local_font_sentinel', 'local-font sentinel callback mismatch');
    twins_overhaul_renderer_assert($styleTagHook[2] === 'twins_overhaul_filter_isolated_style_tag', 'style-tag callback mismatch');
    twins_overhaul_renderer_assert($scriptTagHook[2] === 'twins_overhaul_filter_isolated_script_tag', 'script-tag callback mismatch');
    twins_overhaul_renderer_assert($elementorWidgetHook[2] === 'twins_overhaul_filter_legacy_elementor_widget', 'Elementor widget isolation callback mismatch');
    twins_overhaul_renderer_assert($elementorWidgetHook[3] === PHP_INT_MAX && $elementorWidgetHook[4] === 2, 'Elementor widget isolation priority mismatch');
    twins_overhaul_renderer_assert($elementorDocumentHook[2] === 'twins_overhaul_filter_elementor_document_content', 'Elementor document isolation callback mismatch');
    twins_overhaul_renderer_assert($elementorDocumentHook[3] === PHP_INT_MAX && $elementorDocumentHook[4] === 1, 'Elementor document isolation priority mismatch');
    twins_overhaul_renderer_assert($imageAttributesHook[2] === 'twins_overhaul_filter_legacy_image_attributes', 'legacy image-attributes isolation callback mismatch');
    twins_overhaul_renderer_assert($imageAttributesHook[3] === PHP_INT_MAX && $imageAttributesHook[4] === 3, 'legacy image-attributes isolation priority mismatch');
    twins_overhaul_renderer_assert($searchFormHook[2] === 'twins_overhaul_filter_search_form', 'search-form isolation callback mismatch');
    twins_overhaul_renderer_assert($searchFormHook[3] === PHP_INT_MAX && $searchFormHook[4] === 2, 'search-form isolation priority mismatch');
    twins_overhaul_renderer_assert($headerHook[2] === 'twins_overhaul_output_header', 'header callback mismatch');
    twins_overhaul_renderer_assert($contentHook[2] === 'twins_overhaul_replace_main_content', 'content callback mismatch');
    twins_overhaul_renderer_assert($contentHook[3] === PHP_INT_MAX && $contentHook[4] === 1, 'content callback priority mismatch');
    twins_overhaul_renderer_assert($footerHook[2] === 'twins_overhaul_output_footer', 'footer callback mismatch');

    foreach ($enqueueHooks as $registeredEnqueueHook) {
        ($registeredEnqueueHook[2])();
    }
    twins_overhaul_renderer_assert(!isset($GLOBALS['twins_overhaul_renderer_style_queue']['twins-staging-twx-v2']), 'late safety visual kit remained queued after overhaul isolation');
    twins_overhaul_renderer_assert(isset($GLOBALS['twins_overhaul_renderer_style_queue']['twins-brand-experience']), 'portable stylesheet was not queued after late isolation');
    $GLOBALS['twins_overhaul_renderer_assets'] = [];
    $GLOBALS['twins_overhaul_renderer_style_queue'] = [];
    $GLOBALS['twins_overhaul_renderer_dequeued_styles'] = [];
    $GLOBALS['twins_overhaul_renderer_dequeued_scripts'] = [];
    $GLOBALS['twins_overhaul_renderer_removed_actions'] = [];

    $classes = ($bodyHook[2])(['existing-class']);
    twins_overhaul_renderer_assert(in_array('twins-overhaul-preview', $classes, true), 'eligible request lacks preview body class');
    twins_overhaul_renderer_assert(in_array('twins-overhaul-singular', $classes, true), 'eligible singular request lacks singular-only body class');
    twins_overhaul_renderer_assert(in_array('twins-brand-experience', $classes, true), 'branded request lacks portable body class');
    twins_overhaul_renderer_assert(in_array('twins-brand-route-home-brand', $classes, true), 'branded request lacks its fixed route body class');

    twins_overhaul_enqueue_assets();
    twins_overhaul_renderer_assert(count($GLOBALS['twins_overhaul_renderer_assets']) === 2, 'ordinary brand request did not enqueue exactly two portable assets');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_assets'][0][1] === 'twins-brand-experience', 'portable style handle changed');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_assets'][1][1] === 'twins-brand-experience', 'portable script handle changed');
    twins_overhaul_renderer_assert(strpos((string) $GLOBALS['twins_overhaul_renderer_assets'][0][2], 'https://stage.example.test/wp-content/mu-plugins/twins-brand-experience/assets/css/twins-brand.css') === 0, 'portable style is not fixed same-origin');
    twins_overhaul_renderer_assert(strpos((string) $GLOBALS['twins_overhaul_renderer_assets'][1][2], 'https://stage.example.test/wp-content/mu-plugins/twins-brand-experience/assets/js/twins-brand.js') === 0, 'portable script is not fixed same-origin');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_assets'][0][4] === twins_overhaul_brand_asset_version('assets/css/twins-brand.css'), 'portable style version is not content-derived');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_assets'][1][4] === twins_overhaul_brand_asset_version('assets/js/twins-brand.js'), 'portable script version is not content-derived');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_dequeued_styles'] === [
        'astra-google-fonts',
        'elementor-gf-local-montserrat',
        'elementor-gf-local-prompt',
        'wp-emoji-styles',
        'twins-staging-twx-v2',
    ], 'eligible request did not dequeue the exact fixed remote styles');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_dequeued_scripts'] === [
        'uael-google-maps',
        'uael-google-maps-api',
        'uael-google-maps-cluster',
    ], 'eligible request did not dequeue the exact fixed remote scripts');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_removed_actions'] === [
        ['action', 'wp_head', 'print_emoji_detection_script', 7],
    ], 'eligible request did not remove only the fixed emoji worker loader');

    $resourceHints = [
        'https://fonts.googleapis.com',
        ['href' => '//fonts.gstatic.com', 'crossorigin' => true],
        'https://danielj140.sg-host.com',
        ['href' => 'https://example.test/resource', 'crossorigin' => false],
        'https://fonts.googleapis.com.evil.test',
    ];
    $filteredHints = ($resourceHintsHook[2])($resourceHints, 'preconnect');
    twins_overhaul_renderer_assert($filteredHints === [
        'https://danielj140.sg-host.com',
        ['href' => 'https://example.test/resource', 'crossorigin' => false],
        'https://fonts.googleapis.com.evil.test',
    ], 'resource-hint filter removed anything beyond the two fixed Google font hosts or changed order');
    twins_overhaul_renderer_assert(($styleTagHook[2])('<link id="elementor-gf-local-montserrat-css">', 'elementor-gf-local-montserrat', '/local.css', 'all') === '', 'late Elementor font style was printed');
    twins_overhaul_renderer_assert(($styleTagHook[2])('<link id="twins-staging-twx-v2-css">', 'twins-staging-twx-v2', '/local.css', 'all') === '', 'late recovered global visual kit was printed');
    twins_overhaul_renderer_assert(($styleTagHook[2])('<link id="unrelated-css">', 'unrelated', '/local.css', 'all') === '<link id="unrelated-css">', 'unrelated local style was removed');
    twins_overhaul_renderer_assert(($scriptTagHook[2])('<script id="uael-google-maps-api-js"></script>', 'uael-google-maps-api', '/local.js') === '', 'late Google Maps API script was printed');
    twins_overhaul_renderer_assert(($scriptTagHook[2])('<script id="unrelated-js"></script>', 'unrelated', '/local.js') === '<script id="unrelated-js"></script>', 'unrelated local script was removed');

    $mapWidget = '<div>before<iframe src="https://www.google.com/maps/embed?pb=fixed" loading="lazy"></iframe>after</div>';
    $isolatedMapWidget = ($elementorWidgetHook[2])($mapWidget, new Twins_Overhaul_Renderer_Widget('shortcode'));
    twins_overhaul_renderer_assert(strpos($isolatedMapWidget, 'before') !== false && strpos($isolatedMapWidget, 'after') !== false, 'map isolation changed unrelated widget bytes');
    twins_overhaul_renderer_assert(stripos($isolatedMapWidget, '<iframe') === false && strpos($isolatedMapWidget, 'google.com/maps') === false, 'rendered external map iframe survived isolation');
    $sameOriginWidget = '<iframe src="/local-map-preview/"></iframe>';
    twins_overhaul_renderer_assert(($elementorWidgetHook[2])($sameOriginWidget, new Twins_Overhaul_Renderer_Widget('shortcode')) === $sameOriginWidget, 'same-origin widget iframe was changed');

    $kentuckyLegacyImage = '<img src="https://danielj140.sg-host.com/ky/wp-content/uploads/sites/3/2022/10/cropped-fav2.png" srcset="https://danielj140.sg-host.com/ky/wp-content/uploads/sites/3/2022/10/cropped-fav2.png 512w, https://danielj140.sg-host.com/ky/wp-content/uploads/sites/3/2022/10/cropped-fav2-300x300.png 300w, https://danielj140.sg-host.com/ky/wp-content/uploads/sites/3/2022/10/elementor/thumbs/cropped-fav2-py5bi9b5cqal2j4zxrp9139yuajk2yedppx0nusyo8.png 500w">';
    $isolatedKentuckyImage = ($elementorWidgetHook[2])($kentuckyLegacyImage, new Twins_Overhaul_Renderer_Widget('image'));
    twins_overhaul_renderer_assert(strpos($isolatedKentuckyImage, 'elementor/thumbs/cropped-fav2-py5bi9b5cqal2j4zxrp9139yuajk2yedppx0nusyo8.png') === false, 'broken Kentucky legacy image candidate survived isolation');
    twins_overhaul_renderer_assert(strpos($isolatedKentuckyImage, 'cropped-fav2.png 512w') !== false && strpos($isolatedKentuckyImage, 'cropped-fav2-300x300.png 300w') !== false, 'valid Kentucky legacy image candidates were changed');
    $kentuckyAttributes = [
        'src' => 'https://danielj140.sg-host.com/ky/wp-content/uploads/sites/3/2022/10/cropped-fav2.png',
        'srcset' => 'https://danielj140.sg-host.com/ky/wp-content/uploads/sites/3/2022/10/cropped-fav2.png 512w, https://danielj140.sg-host.com/ky/wp-content/uploads/sites/3/2022/10/cropped-fav2-300x300.png 300w, https://danielj140.sg-host.com/ky/wp-content/uploads/sites/3/2022/10/elementor/thumbs/cropped-fav2-py5bi9b5cqal2j4zxrp9139yuajk2yedppx0nusyo8.png 500w',
        'alt' => 'twins garage doors logo',
    ];
    twins_overhaul_renderer_set(['blogId' => 3]);
    $isolatedKentuckyAttributes = ($imageAttributesHook[2])($kentuckyAttributes, (object) ['ID' => 34], 'full');
    twins_overhaul_renderer_assert(strpos($isolatedKentuckyAttributes['srcset'], 'elementor/thumbs/cropped-fav2-py5bi9b5cqal2j4zxrp9139yuajk2yedppx0nusyo8.png') === false, 'broken Kentucky attachment candidate survived image-attributes isolation');
    twins_overhaul_renderer_assert($isolatedKentuckyAttributes['src'] === $kentuckyAttributes['src'] && $isolatedKentuckyAttributes['alt'] === $kentuckyAttributes['alt'], 'Kentucky image-attributes isolation changed unrelated attributes');
    twins_overhaul_renderer_set(['blogId' => 1]);
    twins_overhaul_renderer_assert(($imageAttributesHook[2])($kentuckyAttributes, (object) ['ID' => 34], 'full') === $kentuckyAttributes, 'non-Kentucky image attributes were changed');

    $elementorSearch = '<search role="search"><form class="elementor-search-form" action="https://danielj140.sg-host.com" method="get"><input type="search"><button type="submit">Search</button></form></search>';
    $isolatedElementorSearch = ($elementorWidgetHook[2])($elementorSearch, new Twins_Overhaul_Renderer_Widget('search-form'));
    twins_overhaul_renderer_assert(stripos($isolatedElementorSearch, '<form') === false && stripos($isolatedElementorSearch, '</form>') === false, 'Elementor search form survived isolation');
    twins_overhaul_renderer_assert(stripos($isolatedElementorSearch, 'action=') === false && stripos($isolatedElementorSearch, 'method=') === false && stripos($isolatedElementorSearch, 'type="submit"') === false, 'Elementor search submission authority survived isolation');
    twins_overhaul_renderer_assert(strpos($isolatedElementorSearch, 'data-twins-staging-form-inert=true') !== false, 'Elementor search form lacks the fixed inert marker');

    $searchForm = '<form role="search" method="get" action="https://danielj140.sg-host.com"><input type="search"><button type="submit">Search</button></form>';
    $isolatedSearch = ($searchFormHook[2])($searchForm, []);
    twins_overhaul_renderer_assert(stripos($isolatedSearch, '<form') === false && stripos($isolatedSearch, '</form>') === false, 'search form element survived isolation');
    twins_overhaul_renderer_assert(stripos($isolatedSearch, 'action=') === false && stripos($isolatedSearch, 'method=') === false && stripos($isolatedSearch, 'type="submit"') === false, 'search form submission authority survived isolation');
    twins_overhaul_renderer_assert(strpos($isolatedSearch, 'data-twins-staging-form-inert=true') !== false, 'search form lacks the fixed inert marker');
    twins_overhaul_renderer_assert(substr_count($isolatedSearch, 'role=') === 1 && strpos($isolatedSearch, 'role="search"') !== false, 'existing search landmark role was duplicated or replaced');
    $rolelessSearch = ($searchFormHook[2])('<form><input type="search"></form>', []);
    twins_overhaul_renderer_assert(substr_count($rolelessSearch, 'role=') === 1 && strpos($rolelessSearch, 'role=form') !== false, 'roleless form did not receive exactly one inert form role');
    $ambiguousSearchRole = null;
    try {
        ($searchFormHook[2])('<form role="search" role="form"><input type="search"></form>', []);
    } catch (Twins_Overhaul_Renderer_Refusal $exception) {
        $ambiguousSearchRole = $exception;
    }
    twins_overhaul_renderer_assert($ambiguousSearchRole instanceof Twins_Overhaul_Renderer_Refusal, 'duplicate source form roles did not fail closed');

    twins_overhaul_renderer_set([
        'path' => '/5-signs-that-its-time-to-replace-your-garage-door/',
        'postType' => 'post',
        'postId' => 4202,
        'renderedPostType' => 'post',
        'renderedPostId' => 4202,
        'title' => '5 Signs That it’s Time to Replace Your Garage Door',
    ]);
    $legacyTitle = '<div><h1 class="elementor-heading-title">5 Signs That it’s Time to Replace Your Garage Door</h1></div>';
    twins_overhaul_renderer_assert(stripos(($elementorWidgetHook[2])($legacyTitle, new Twins_Overhaul_Renderer_Widget('heading', 'f96b26a')), '<h1') === false, 'legacy article title H1 survived isolation');
    twins_overhaul_renderer_assert(($elementorWidgetHook[2])($legacyTitle, new Twins_Overhaul_Renderer_Widget('heading', 'body-heading-1')) === $legacyTitle, 'nested article body heading was changed by legacy-title isolation');
    $themePostFallback = ($elementorWidgetHook[2])($legacyTitle, new Twins_Overhaul_Renderer_Widget('theme-post-content', 'e031a6d'));
    twins_overhaul_renderer_assert(substr_count($themePostFallback, 'id="twins-overhaul-main"') === 1, 'exact article body widget did not receive the fixed fallback frame');
    twins_overhaul_renderer_assert(substr_count($themePostFallback, $legacyTitle) === 1, 'exact article body widget content changed during fallback rendering');

    twins_overhaul_renderer_set([
        'path' => '/',
        'postType' => 'page',
        'postId' => 1,
        'renderedPostType' => 'page',
        'renderedPostId' => 1,
        'title' => 'Twins Garage Doors',
    ]);

    ob_start();
    ($fontSentinelHook[2])();
    ($fontSentinelHook[2])();
    $fontSentinel = (string) ob_get_clean();
    twins_overhaul_renderer_assert(substr_count($fontSentinel, 'id="twx-mont"') === 1, 'local-font sentinel once guard failed');

    ob_start();
    ($headerHook[2])();
    ($headerHook[2])();
    $header = (string) ob_get_clean();
    twins_overhaul_renderer_assert(substr_count($header, '<header class="twins-brand-header"') === 1, 'portable header once guard failed');
    twins_overhaul_renderer_assert(substr_count($header, 'aria-label="Primary navigation"') === 1, 'portable header primary navigation count changed');

    ob_start();
    ($footerHook[2])();
    ($footerHook[2])();
    $footer = (string) ob_get_clean();
    twins_overhaul_renderer_assert(substr_count($footer, '<footer class="twins-brand-footer"') === 1, 'portable footer once guard failed');
}

if ($scenario === 'blog-index') {
    twins_overhaul_renderer_set([
        'blogId' => 1,
        'path' => '/blog/',
        'postType' => 'post',
        'postId' => 0,
        'renderedPostType' => 'post',
        'renderedPostId' => 901,
        'title' => 'Garage Door Resources',
        'singular' => false,
        'home' => true,
    ]);
    $classes = twins_overhaul_filter_body_classes(['blog']);
    twins_overhaul_renderer_assert(in_array('twins-overhaul-preview', $classes, true), 'posts index lacks preview body class');
    twins_overhaul_renderer_assert(!in_array('twins-overhaul-singular', $classes, true), 'posts index received the singular-only title-suppression class');
    twins_overhaul_renderer_assert(in_array('twins-brand-experience', $classes, true), 'posts index lacks portable brand body class');
    twins_overhaul_renderer_assert(in_array('twins-brand-route-article', $classes, true), 'posts index lacks its fixed article route body class');
    twins_overhaul_enqueue_assets();
    twins_overhaul_renderer_assert(count($GLOBALS['twins_overhaul_renderer_assets']) === 2, 'posts index did not enqueue exactly two portable assets');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_assets'][0][1] === 'twins-brand-experience', 'posts index portable style handle changed');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_assets'][1][1] === 'twins-brand-experience', 'posts index portable script handle changed');
    twins_overhaul_renderer_assert(in_array('twins-staging-twx-v2', $GLOBALS['twins_overhaul_renderer_dequeued_styles'], true), 'posts index retained the recovered global visual kit');
    ob_start();
    twins_overhaul_output_header();
    twins_overhaul_output_header();
    $header = (string) ob_get_clean();
    twins_overhaul_renderer_assert(substr_count($header, '<header class="twins-brand-header"') === 1, 'posts index portable header once guard failed');
    twins_overhaul_renderer_assert(strpos($header, 'twins-overhaul-header') === false, 'posts index retained the legacy public header');
    ob_start();
    twins_overhaul_output_footer();
    twins_overhaul_output_footer();
    $footer = (string) ob_get_clean();
    twins_overhaul_renderer_assert(substr_count($footer, '<footer class="twins-brand-footer"') === 1, 'posts index portable footer once guard failed');
    $postBody = '<article data-index-post="exact">BLOG-INDEX-POST-BYTES</article>';
    twins_overhaul_renderer_assert(twins_overhaul_replace_main_content($postBody) === $postBody, 'posts index body was replaced');
}

if ($scenario === 'campaign') {
    twins_overhaul_renderer_set([
        'blogId' => 1,
        'path' => '/madison-garage-door-repair-lp/',
        'postType' => 'page',
        'postId' => 7092,
        'renderedPostType' => 'page',
        'renderedPostId' => 7092,
        'title' => 'Madison Garage Door Repair',
    ]);
    $bodyHook = twins_overhaul_renderer_hook('filter', 'body_class');
    $original = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Lilita+One" rel="stylesheet"><section data-campaign="exact">CAMPAIGN-BYTES</section><form action="/campaign-lead" method="post"><input type="email"><button type="submit">Send</button></form>';
    twins_overhaul_renderer_assert(twins_overhaul_is_allowed_singular_request(), 'campaign scenario is not a genuinely eligible singular body');
    twins_overhaul_renderer_assert(twins_overhaul_current_classification() === 'campaign-preserve', 'campaign scenario did not prove campaign classification');
    twins_overhaul_renderer_assert(($bodyHook[2])(['campaign']) === ['campaign', 'twins-overhaul-campaign'], 'campaign did not receive only its fixed inert-form marker class');
    twins_overhaul_enqueue_assets();
    twins_overhaul_renderer_assert(count($GLOBALS['twins_overhaul_renderer_assets']) === 2, 'campaign did not enqueue exactly the two local safety assets');
    foreach ($GLOBALS['twins_overhaul_renderer_assets'] as $asset) {
        twins_overhaul_renderer_assert((bool) preg_match('~^/(?!/)~', (string) $asset[2]), 'campaign safety asset is not same-origin root-relative');
    }
    twins_overhaul_renderer_assert(count($GLOBALS['twins_overhaul_renderer_dequeued_styles']) === 4, 'campaign did not remove fixed remote and emoji styles');
    twins_overhaul_renderer_assert(count($GLOBALS['twins_overhaul_renderer_dequeued_scripts']) === 3, 'campaign did not remove fixed remote scripts');
    ob_start();
    twins_overhaul_output_header();
    twins_overhaul_output_footer();
    twins_overhaul_renderer_assert(ob_get_clean() === '', 'campaign received preview chrome');
    $campaign = twins_overhaul_replace_main_content($original);
    twins_overhaul_renderer_assert(strpos($campaign, '<section data-campaign="exact">CAMPAIGN-BYTES</section>') !== false, 'campaign visual body changed');
    twins_overhaul_renderer_assert(strpos($campaign, 'fonts.googleapis.com') === false && strpos($campaign, 'fonts.gstatic.com') === false, 'campaign retained a remote font link');
    twins_overhaul_renderer_assert(stripos($campaign, '<form') === false && stripos($campaign, '</form>') === false, 'campaign retained a form element');
    twins_overhaul_renderer_assert(stripos($campaign, 'type="submit"') === false && stripos($campaign, 'action=') === false && stripos($campaign, 'method=') === false, 'campaign retained submission authority');
    twins_overhaul_renderer_assert(strpos($campaign, 'data-twins-staging-form-inert=true') !== false, 'campaign lacks the context-neutral server-side inert marker');
}

if (in_array($scenario, ['service-brand-chrome', 'catalog-brand-chrome'], true)) {
    $fixtures = [
        'service-brand-chrome' => [4, '/wi/garage-door-spring-repair/', 801, 'Garage Door Spring Repair', 'service'],
        'catalog-brand-chrome' => [1, '/clopay-garage-doors/', 7141, 'Clopay Garage Doors', 'catalog-preserve'],
    ];
    [$blogId, $path, $postId, $title, $classification] = $fixtures[$scenario];
    twins_overhaul_renderer_set([
        'blogId' => $blogId,
        'path' => $path,
        'postType' => 'page',
        'postId' => $postId,
        'renderedPostType' => 'page',
        'renderedPostId' => $postId,
        'title' => $title,
    ]);
    twins_overhaul_renderer_assert(twins_overhaul_current_classification() === $classification, $scenario . ' classification mismatch');
    $classes = twins_overhaul_filter_body_classes(['existing']);
    twins_overhaul_renderer_assert(in_array('twins-brand-experience', $classes, true), 'portable brand body class missing');
    twins_overhaul_renderer_assert(in_array('twins-brand-route-' . $classification, $classes, true), 'portable brand route body class missing');
    $context = twins_overhaul_current_context($classification);
    $header = twins_overhaul_render_header($context);
    $footer = twins_overhaul_render_footer($context);
    twins_overhaul_renderer_assert(strpos($header, 'class="twins-brand-header"') !== false, 'portable brand header missing');
    twins_overhaul_renderer_assert(strpos($header, 'twins-overhaul-header') === false, 'legacy public header survived');
    twins_overhaul_renderer_assert(strpos($footer, 'class="twins-brand-footer"') !== false, 'portable brand footer missing');
}

if ($scenario === 'family-once') {
    twins_overhaul_renderer_set([
        'blogId' => 4,
        'path' => '/wi/garage-door-spring-repair/',
        'postType' => 'page',
        'postId' => 801,
        'renderedPostType' => 'page',
        'renderedPostId' => 801,
        'title' => 'Garage Door Spring Repair',
    ]);
    $original = '<DIV data-original="service"><H1 id="legacy-one">Embedded heading one</H1><h1 CLASS="legacy">Embedded heading two</h1>'
        . '<P onclick="fetch(\'/lead\')" STYLE="background:url(https://bad.example/pixel)">SAFE FACTUAL COPY <STRONG data-x="1">stays strong</STRONG></P>'
        . '<FORM ACTION="/wp-admin/admin-post.php" METHOD="POST"><P>SAFE FORM FACT</P><INPUT TYPE="image" SRC="/pixel"><BUTTON>Implicit submit label</BUTTON><BUTTON TYPE="submit" FORMACTION="https://bad.example/lead">Submit</BUTTON></FORM>'
        . '<FoRm AcTiOn=/local MeThOd=POST><p>SAFE MALFORMED FORM FACT</p>'
        . '<A HREF="/same-origin-write" ONCLICK="sendBeacon(\'/lead\')">SAFE LINK LABEL</A><IMG SRC="https://bad.example/image.jpg" ONERROR="alert(1)">'
        . '<IFRAME SRC="/same-origin-frame">frame fallback</IFRAME><OBJECT DATA="https://bad.example/object">object fallback</OBJECT><EMBED SRC="/embed">'
        . '<SCRIPT SRC="https://bad.example/x.js">fetch(\'/lead\')</SCRIPT><STYLE>.x{background:url(/pixel)}</STYLE><TEMPLATE><a href="/write">template</a></TEMPLATE>';
    $prepared = twins_overhaul_prepare_family_content($original);
    twins_overhaul_renderer_assert(strpos($prepared, 'SAFE FACTUAL COPY') !== false, 'family preparation lost safe factual copy');
    twins_overhaul_renderer_assert(strpos($prepared, 'SAFE FORM FACT') !== false, 'family preparation lost safe form text');
    twins_overhaul_renderer_assert(strpos($prepared, 'SAFE LINK LABEL') !== false, 'family preparation lost safe link text');
    twins_overhaul_renderer_assert(strpos($prepared, '<strong>stays strong</strong>') !== false, 'family preparation lost safe emphasis');
    twins_overhaul_renderer_assert(strpos($prepared, 'This private staging preview does not submit or store lead information.') !== false, 'family preparation omitted the inert replacement notice');
    twins_overhaul_renderer_assert(!preg_match('/<(?:form|input|button|select|textarea|script|style|iframe|frame|object|embed|template|a|img)\b/i', $prepared), 'family preparation retained an active element');
    twins_overhaul_renderer_assert(!preg_match('/\b(?:action|method|formaction|href|src|style|on[a-z]+)\s*=/i', $prepared), 'family preparation retained an authority attribute');
    twins_overhaul_renderer_assert(!preg_match('/\b(?:fetch|XMLHttpRequest|sendBeacon)\s*\(|\burl\s*\(/i', $prepared), 'family preparation retained an active request primitive');
    twins_overhaul_renderer_assert(!preg_match('/<[a-z][^>]*\s+[a-z_:][-a-z0-9_:.]*\s*=/i', $prepared), 'family preparation retained an attribute');
    twins_overhaul_renderer_assert(!preg_match('~bad\.example|same-origin-(?:write|frame)|wp-admin/admin-post|/pixel|/embed~i', $prepared), 'family preparation retained an external or same-origin authority destination');
    foreach ([
        '<ScRiPt SrC=//bad.example/unclosed>MALFORMED SCRIPT TEXT',
        '<STYLE>.x{color:red}',
        '<IFRAME SRC=/frame>UNFINISHED FRAME',
        '<OBJECT DATA=/object>UNFINISHED OBJECT',
        '<TEMPLATE><p>UNFINISHED TEMPLATE',
        '<p>plain fetch(\'/lead\') text</p>',
        '<p>plain XMLHttpRequest text</p>',
        '<p>plain sendBeacon(\'/lead\') text</p>',
        '<p>plain url(/pixel) text</p>',
    ] as $unsafeBody) {
        $refusal = null;
        try {
            twins_overhaul_prepare_family_content($unsafeBody);
        } catch (Twins_Overhaul_Renderer_Refusal $exception) {
            $refusal = $exception;
        }
        twins_overhaul_renderer_assert($refusal instanceof Twins_Overhaul_Renderer_Refusal, 'unsafe malformed family body did not fail closed: ' . $unsafeBody);
        twins_overhaul_renderer_assert($refusal->response === 503, 'unsafe malformed family-body refusal did not use 503');
    }
    $rendered = twins_overhaul_replace_main_content($original);
    twins_overhaul_renderer_assert(substr_count($rendered, '<h1') === 1, 'service H1 count changed');
    twins_overhaul_renderer_assert(substr_count($rendered, '<details') >= 4, 'service FAQ count is insufficient');
    twins_overhaul_renderer_assert(stripos($rendered, 'dangerous tension') !== false, 'spring safety answer missing');
    twins_overhaul_renderer_assert(stripos($rendered, 'trained professionals') !== false, 'spring professional-handling answer missing');
    twins_overhaul_renderer_assert(stripos($rendered, 'replace it yourself') === false, 'unsafe DIY spring copy survived');
    twins_overhaul_renderer_assert(stripos($rendered, 'DIY spring') === false, 'unsafe DIY spring phrase survived');
    twins_overhaul_renderer_assert(stripos($rendered, '#1') === false, 'unsupported ranking claim survived');
    twins_overhaul_renderer_assert(strpos($rendered, 'data-twins-original-content') === false, 'raw service body survived');
    twins_overhaul_renderer_assert(strpos($rendered, 'Embedded heading one') === false, 'legacy service heading survived');
    twins_overhaul_renderer_assert(strpos($rendered, 'SAFE FACTUAL COPY') === false, 'legacy service body survived');
    twins_overhaul_renderer_assert(strpos($rendered, '(608) 420-2377') !== false, 'service page lost the normalized regional phone');
    twins_overhaul_renderer_assert(strpos($rendered, '/contact-us/') !== false, 'service page lost the quote adapter action');
    twins_overhaul_renderer_assert(strpos($rendered, '/wi/garage-door-opener-repair/') !== false, 'service page bypassed market-aware internal links');
    twins_overhaul_renderer_assert(strpos($rendered, 'id="twins-overhaul-main"') !== false, 'service family lacks skip target');
    twins_overhaul_renderer_assert(!preg_match('/\b(?:five[- ]star|same[- ]day|licensed|guaranteed|24\/7)\b/i', $rendered), 'service family invented an unsupported claim');
    twins_overhaul_renderer_assert(!preg_match('/<form\b|type=["\']submit["\']|\baction=/i', $rendered), 'service family contains an active form');
}

if ($scenario === 'path-contact-context') {
    twins_overhaul_renderer_set([
        'blogId' => 4,
        'path' => '/wi/garage-door-repair-in-milwaukee-wi/',
        'postType' => 'page',
        'postId' => 805,
        'renderedPostType' => 'page',
        'renderedPostId' => 805,
        'title' => 'Garage Door Repair in Milwaukee',
    ]);
    twins_overhaul_renderer_assert(twins_overhaul_current_classification() === 'location', 'Milwaukee context route is not a location');
    $milwaukeeContext = twins_overhaul_current_context('location');
    $milwaukeeHeader = twins_overhaul_render_header($milwaukeeContext);
    $milwaukeeBody = twins_overhaul_render_classified_content(
        'location',
        $milwaukeeContext,
        '<p>MILWAUKEE VERIFIED CONTENT</p>'
    );
    $milwaukeeFooter = twins_overhaul_render_footer($milwaukeeContext);
    $milwaukee = $milwaukeeHeader . $milwaukeeBody . $milwaukeeFooter;
    twins_overhaul_renderer_assert(
        preg_match('~<details class="twins-brand-market-menu">(.*?)</details>~s', $milwaukeeHeader, $milwaukeeMarketMenu) === 1,
        'Milwaukee header lost the market selector'
    );
    twins_overhaul_renderer_assert(strpos($milwaukeeMarketMenu[0], '(608) 420-2377') !== false, 'Milwaukee market selector lost the approved Wisconsin phone');
    twins_overhaul_renderer_assert(strpos($milwaukeeMarketMenu[0], '(815) 800-2025') !== false, 'Milwaukee market selector lost the approved Illinois phone');
    $milwaukeeWithoutMarketMenu = str_replace($milwaukeeMarketMenu[0], '', $milwaukee);
    twins_overhaul_renderer_assert(substr_count($milwaukeeWithoutMarketMenu, '(414) 800-9271') === 3, 'Milwaukee composition does not use one display phone in header, body, and footer');
    twins_overhaul_renderer_assert(substr_count($milwaukeeWithoutMarketMenu, 'tel:+14148009271') === 5, 'Milwaukee composition does not use one phone href across all call actions');
    twins_overhaul_renderer_assert(strpos($milwaukeeWithoutMarketMenu, '(608) 420-2377') === false, 'Milwaukee composition exposes a contradictory broad Wisconsin phone');
    twins_overhaul_renderer_assert(strpos($milwaukeeWithoutMarketMenu, 'tel:+16084202377') === false, 'Milwaukee composition exposes a contradictory broad Wisconsin phone href');

    twins_overhaul_renderer_set([
        'blogId' => 4,
        'path' => '/wi/garage-door-spring-repair/',
        'postType' => 'page',
        'postId' => 806,
        'renderedPostType' => 'page',
        'renderedPostId' => 806,
        'title' => 'Garage Door Spring Repair',
    ]);
    twins_overhaul_renderer_assert(twins_overhaul_current_classification() === 'service', 'Wisconsin context route is not a service');
    $wisconsinContext = twins_overhaul_current_context('service');
    $wisconsinHeader = twins_overhaul_render_header($wisconsinContext);
    $wisconsinBody = twins_overhaul_render_classified_content(
        'service',
        $wisconsinContext,
        '<p>IGNORED WISCONSIN SERVICE BODY</p>'
    );
    $wisconsinFooter = twins_overhaul_render_footer($wisconsinContext);
    $wisconsin = $wisconsinHeader . $wisconsinBody . $wisconsinFooter;
    twins_overhaul_renderer_assert(
        preg_match('~<details class="twins-brand-market-menu">(.*?)</details>~s', $wisconsinHeader, $wisconsinMarketMenu) === 1,
        'generic Wisconsin header lost the market selector'
    );
    $wisconsinWithoutMarketMenu = str_replace($wisconsinMarketMenu[0], '', $wisconsin);
    twins_overhaul_renderer_assert(substr_count($wisconsinWithoutMarketMenu, '(608) 420-2377') === 3, 'generic Wisconsin composition display phone changed');
    twins_overhaul_renderer_assert(substr_count($wisconsinWithoutMarketMenu, 'tel:+16084202377') === 6, 'generic Wisconsin composition phone href changed');
    twins_overhaul_renderer_assert(strpos($wisconsinWithoutMarketMenu, '(414) 800-9271') === false, 'Milwaukee phone leaked into generic Wisconsin composition');
    twins_overhaul_renderer_assert(strpos($wisconsinWithoutMarketMenu, '(815) 800-2025') === false, 'Illinois phone leaked into generic Wisconsin composition');

    twins_overhaul_renderer_set([
        'blogId' => 5,
        'path' => '/il/emergency-garage-services/',
        'postType' => 'page',
        'postId' => 807,
        'renderedPostType' => 'page',
        'renderedPostId' => 807,
        'title' => 'Emergency Garage Door Service',
    ]);
    twins_overhaul_renderer_assert(twins_overhaul_current_classification() === 'service', 'Illinois context route is not a service');
    $illinoisContext = twins_overhaul_current_context('service');
    $illinoisHeader = twins_overhaul_render_header($illinoisContext);
    $illinoisBody = twins_overhaul_render_classified_content(
        'service',
        $illinoisContext,
        '<p>IGNORED ILLINOIS SERVICE BODY</p>'
    );
    $illinoisFooter = twins_overhaul_render_footer($illinoisContext);
    $illinois = $illinoisHeader . $illinoisBody . $illinoisFooter;
    twins_overhaul_renderer_assert(
        preg_match('~<details class="twins-brand-market-menu">(.*?)</details>~s', $illinoisHeader, $illinoisMarketMenu) === 1,
        'generic Illinois header lost the market selector'
    );
    $illinoisWithoutMarketMenu = str_replace($illinoisMarketMenu[0], '', $illinois);
    twins_overhaul_renderer_assert(substr_count($illinoisWithoutMarketMenu, '(815) 800-2025') === 3, 'generic Illinois composition display phone changed');
    twins_overhaul_renderer_assert(substr_count($illinoisWithoutMarketMenu, 'tel:+18158002025') === 6, 'generic Illinois composition phone href changed');
    twins_overhaul_renderer_assert(strpos($illinoisWithoutMarketMenu, '(608) 420-2377') === false, 'Wisconsin phone leaked into Illinois composition');
    twins_overhaul_renderer_assert(strpos($illinoisWithoutMarketMenu, '(414) 800-9271') === false, 'Milwaukee phone leaked into Illinois composition');
}

if ($scenario === 'home-brand') {
    twins_overhaul_renderer_set([
        'blogId' => 1,
        'path' => '/',
        'postType' => 'page',
        'postId' => 1,
        'renderedPostType' => 'page',
        'renderedPostId' => 1,
        'title' => 'Garage Door Repair & Installation',
    ]);
    $body = twins_overhaul_replace_main_content('<section>OBSOLETE-HOME-BYTES</section>');
    $context = twins_overhaul_current_context('home-brand');
    $output = twins_overhaul_render_header($context) . $body . twins_overhaul_render_footer($context);
    twins_overhaul_renderer_assert(substr_count($output, 'aria-label="Primary navigation"') === 1, 'home lacks exactly one primary navigation');
    twins_overhaul_renderer_assert(strpos($output, 'Request a Quote') !== false, 'home lacks exact quote CTA');
    twins_overhaul_renderer_assert(strpos($output, 'Book Online') !== false, 'home lacks booking control');
    twins_overhaul_renderer_assert(strpos($output, 'Our Team') !== false, 'home lacks Our Team journey');
    twins_overhaul_renderer_assert(strpos($output, 'Get an Estimate') === false, 'obsolete CTA survived');
    twins_overhaul_renderer_assert(substr_count($body, 'id="twins-overhaul-main"') === 1, 'home lacks one portable main landmark');
    twins_overhaul_renderer_assert(strpos($body, 'OBSOLETE-HOME-BYTES') === false, 'obsolete homepage body survived replacement');
    twins_overhaul_renderer_assert(stripos($output, '<form') === false, 'home retained form authority');
}

if (in_array($scenario, ['team-brand', 'careers-brand', 'reviews-brand', 'contact-brand'], true)) {
    $fixtures = [
        'team-brand' => ['/our-team/', 6955, 'Our Team', 'twins-brand-team-page'],
        'careers-brand' => ['/careers/', 7341, 'Careers', 'twins-brand-careers-page'],
        'reviews-brand' => ['/reviews/', 2186, 'Reviews', 'twins-brand-reviews-page'],
        'contact-brand' => ['/contact-us/', 2030, 'Contact', 'twins-brand-contact-page'],
    ];
    [$path, $postId, $title, $marker] = $fixtures[$scenario];
    twins_overhaul_renderer_set([
        'blogId' => 1,
        'path' => $path,
        'postType' => 'page',
        'postId' => $postId,
        'renderedPostType' => 'page',
        'renderedPostId' => $postId,
        'title' => $title,
    ]);
    twins_overhaul_renderer_assert(twins_overhaul_current_classification() === $scenario, $scenario . ' classification mismatch');
    $body = twins_overhaul_replace_main_content('<section>LEGACY-BRAND-BODY</section>');
    twins_overhaul_renderer_assert(substr_count($body, 'id="twins-overhaul-main"') === 1, $scenario . ' lacks one portable main landmark');
    twins_overhaul_renderer_assert(strpos($body, $marker) !== false, $scenario . ' portable body marker is missing');
    twins_overhaul_renderer_assert(strpos($body, 'LEGACY-BRAND-BODY') === false, $scenario . ' retained its legacy body');
    twins_overhaul_renderer_assert(stripos($body, '<form') === false, $scenario . ' retained form authority');
}

if ($scenario === 'elementor-theme-content') {
    twins_overhaul_renderer_set([
        'blogId' => 3,
        'path' => '/ky/location/lexington/',
        'postType' => 'location',
        'postId' => 2415,
        'renderedPostType' => 'location',
        'renderedPostId' => 2415,
        'title' => 'Lexington',
    ]);
    $elementorWidgetHook = twins_overhaul_renderer_hook('filter', 'elementor/widget/render_content');
    twins_overhaul_renderer_assert(twins_overhaul_is_allowed_chrome_request(), 'Elementor location scenario is not chrome-eligible');
    twins_overhaul_renderer_assert(twins_overhaul_is_allowed_singular_request(), 'Elementor location scenario is not singular-content-eligible');
    twins_overhaul_renderer_assert(twins_overhaul_current_classification() === 'location', 'Elementor location scenario did not prove location classification');
    $legacyLocation = '<div id="twins-overhaul-main"><section data-elementor-original="location"><p>LEGACY LOCATION BODY</p><form action="/spoofed-lead"><button type="submit">Send</button></form></section></div>';
    $location = ($elementorWidgetHook[2])(
        $legacyLocation,
        new Twins_Overhaul_Renderer_Widget('theme-post-content', 'location-body')
    );
    twins_overhaul_renderer_assert(substr_count($location, 'id="twins-overhaul-main"') === 1, 'Elementor location fallback did not render one fixed main wrapper');
    twins_overhaul_renderer_assert(
        strpos($location, 'twins-brand-editorial-page') !== false,
        'Elementor location fallback did not render the portable editorial family: ' . substr($location, 0, 320)
    );
    twins_overhaul_renderer_assert(substr_count($location, 'LEGACY LOCATION BODY') === 1, 'Elementor location fallback did not retain safe legacy facts exactly once');
    twins_overhaul_renderer_assert(stripos($location, '<form') === false && stripos($location, 'spoofed-lead') === false, 'spoofed legacy main marker bypassed Elementor form isolation');
    twins_overhaul_renderer_assert(
        ($elementorWidgetHook[2])($legacyLocation, new Twins_Overhaul_Renderer_Widget('theme-post-content', 'location-body-duplicate')) === '',
        'Elementor theme-post-content fallback rendered more than once'
    );

    twins_overhaul_renderer_set([
        'blogId' => 1,
        'path' => '/legacy-form-page/',
        'postType' => 'page',
        'postId' => 889,
        'renderedPostType' => 'page',
        'renderedPostId' => 889,
        'title' => 'Legacy Form Page',
    ]);
    $legacyForm = '<article><h1>Legacy Form Page</h1><form action="/lead" method="post"><input type="email"><button type="submit">Send</button></form></article>';
    $article = twins_overhaul_render_classified_content(
        twins_overhaul_current_classification(),
        twins_overhaul_current_context('article'),
        $legacyForm
    );
    twins_overhaul_renderer_assert(stripos($article, '<form') === false && stripos($article, '</form>') === false, 'general article fallback retained form markup');
    twins_overhaul_renderer_assert(stripos($article, 'action=') === false && stripos($article, 'method=') === false && stripos($article, 'type="submit"') === false, 'general article fallback retained submission authority');
    twins_overhaul_renderer_assert(strpos($article, 'This private staging preview does not submit or store lead information.') !== false, 'general article fallback lacks the inert-form notice');
    twins_overhaul_renderer_assert(strpos($article, 'twins-brand-editorial-page') !== false, 'general article fallback lacks the portable editorial shell');
}

if ($scenario === 'elementor-document-content') {
    twins_overhaul_renderer_set([
        'blogId' => 3,
        'path' => '/ky/location/lexington/',
        'postType' => 'location',
        'postId' => 2415,
        'renderedPostType' => 'location',
        'renderedPostId' => 2415,
        'title' => 'Lexington',
        'elementorDocumentId' => 7333,
    ]);
    $elementorDocumentHook = twins_overhaul_renderer_hook('filter', 'elementor/frontend/the_content');
    $templateContent = '<section data-elementor-template="header">LEGACY HEADER TEMPLATE</section>';
    twins_overhaul_renderer_assert(
        ($elementorDocumentHook[2])($templateContent) === $templateContent,
        'a non-queried Elementor template was changed'
    );

    twins_overhaul_renderer_set(['elementorDocumentId' => 2415]);
    $directLocation = '<div data-elementor-type="location"><p>DIRECT ELEMENTOR LOCATION FACT</p><form action="/direct-lead" method="post"><button type="submit">Send</button></form></div>';
    $renderedLocation = ($elementorDocumentHook[2])($directLocation);
    twins_overhaul_renderer_assert(substr_count($renderedLocation, 'id="twins-overhaul-main"') === 1, 'direct Elementor document did not render one fixed main wrapper');
    twins_overhaul_renderer_assert(strpos($renderedLocation, 'twins-brand-editorial-page') !== false, 'direct Elementor document did not render the portable editorial family');
    twins_overhaul_renderer_assert(substr_count($renderedLocation, 'DIRECT ELEMENTOR LOCATION FACT') === 1, 'direct Elementor document did not retain safe legacy facts exactly once');
    twins_overhaul_renderer_assert(stripos($renderedLocation, '<form') === false && stripos($renderedLocation, 'direct-lead') === false, 'direct Elementor document retained form or action authority');
    twins_overhaul_renderer_assert(
        twins_overhaul_replace_main_content($renderedLocation) === $renderedLocation,
        'outer WordPress content filtering rendered the direct Elementor document twice'
    );
    $elementorWidgetHook = twins_overhaul_renderer_hook('filter', 'elementor/widget/render_content');
    twins_overhaul_renderer_assert(
        ($elementorWidgetHook[2])(
            $renderedLocation,
            new Twins_Overhaul_Renderer_Widget('theme-post-content', 'direct-location-wrapper')
        ) === $renderedLocation,
        'outer Elementor theme-post-content filtering rendered the authenticated direct document twice'
    );
}

if ($scenario === 'legacy-location-document') {
    twins_overhaul_renderer_set([
        'blogId' => 3,
        'path' => '/ky/about-us/',
        'postType' => 'location',
        'postId' => 2415,
        'renderedPostType' => 'location',
        'renderedPostId' => 2415,
        'title' => 'Lexington',
        'elementorDocumentId' => 2427,
    ]);
    $elementorDocumentHook = twins_overhaul_renderer_hook('filter', 'elementor/frontend/the_content');
    $legacyTemplate = '<div data-elementor-id="2427"><h1>LEGACY LEXINGTON FACT</h1><form action="/legacy-lead" method="post"><button type="submit">Send</button></form></div>';
    twins_overhaul_renderer_assert(
        ($elementorDocumentHook[2])($legacyTemplate) === $legacyTemplate,
        'the fixed legacy template was accepted outside its exact Lexington path'
    );

    twins_overhaul_renderer_set(['path' => '/ky/location/lexington/']);
    $renderedLocation = ($elementorDocumentHook[2])($legacyTemplate);
    twins_overhaul_renderer_assert(substr_count($renderedLocation, 'id="twins-overhaul-main"') === 1, 'fixed Lexington template did not render one overhaul root');
    twins_overhaul_renderer_assert(strpos($renderedLocation, 'twins-brand-editorial-page') !== false, 'fixed Lexington template did not render the portable editorial family');
    twins_overhaul_renderer_assert(substr_count($renderedLocation, 'LEGACY LEXINGTON FACT') === 1, 'fixed Lexington template did not retain its inert factual text exactly once');
    twins_overhaul_renderer_assert(stripos($renderedLocation, '<form') === false && stripos($renderedLocation, 'legacy-lead') === false, 'fixed Lexington template retained form or action authority');
}

if ($scenario === 'ineligible') {
    twins_overhaul_renderer_set([
        'blogId' => 4,
        'path' => '/wi/garage-door-openers/',
        'postType' => 'page',
        'postId' => 802,
        'title' => 'Garage Door Openers',
    ]);
    $original = '<p>INELIGIBLE-BYTES</p>';
    twins_overhaul_renderer_set(['admin' => true]);
    twins_overhaul_enqueue_assets();
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_dequeued_styles'] === [], 'ineligible request dequeued styles');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_dequeued_scripts'] === [], 'ineligible request dequeued scripts');
    twins_overhaul_renderer_assert($GLOBALS['twins_overhaul_renderer_removed_actions'] === [], 'ineligible request removed a front-end action');
    foreach ([
        ['admin' => true],
        ['ajax' => true],
        ['feed' => true],
        ['preview' => true],
        ['archive' => true],
        ['search' => true],
        ['404' => true],
        ['singular' => false],
        ['mainQuery' => false],
        ['loop' => false],
        ['postType' => 'elementor_library'],
        ['postType' => 'attachment'],
        ['postType' => 'product'],
        ['renderedPostId' => 7336, 'renderedPostType' => 'elementor_library'],
        ['renderedPostId' => 899, 'renderedPostType' => 'page'],
    ] as $change) {
        twins_overhaul_renderer_set([
            'admin' => false,
            'ajax' => false,
            'feed' => false,
            'preview' => false,
            'archive' => false,
            'search' => false,
            '404' => false,
            'singular' => true,
            'home' => false,
            'mainQuery' => true,
            'loop' => true,
            'postType' => 'page',
            'renderedPostId' => 802,
            'renderedPostType' => 'page',
        ]);
        twins_overhaul_renderer_set($change);
        twins_overhaul_renderer_assert(twins_overhaul_replace_main_content($original) === $original, 'ineligible request changed content: ' . json_encode($change));
    }
    twins_overhaul_renderer_set(['postType' => 'page', 'renderedPostId' => 802, 'renderedPostType' => 'page', 'mainQuery' => true, 'loop' => true]);
    twins_overhaul_renderer_assert(twins_overhaul_replace_main_content($original) !== $original, 'ineligible calls consumed the once guard');
}

if ($scenario === 'article') {
    twins_overhaul_renderer_set([
        'blogId' => 1,
        'path' => '/published-story/',
        'postType' => 'post',
        'postId' => 803,
        'renderedPostType' => 'post',
        'renderedPostId' => 803,
        'title' => 'Published Garage Door Story',
    ]);
    $original = '<article data-original="article" class="exact"><h1 id="article-heading" data-source="published">Embedded article heading</h1><p style="color:navy">PUBLISHED-ARTICLE-BYTES</p></article>';
    $rendered = twins_overhaul_replace_main_content($original);
    twins_overhaul_renderer_assert(preg_match_all('/<h1\b/i', $rendered) === 1, 'article frame does not render exactly one H1');
    twins_overhaul_renderer_assert(strpos($rendered, $original) === false, 'article retained its raw wrapper and authority attributes');
    twins_overhaul_renderer_assert(substr_count($rendered, '<h2>Embedded article heading</h2>') === 1, 'article heading was not demoted exactly once');
    twins_overhaul_renderer_assert(substr_count($rendered, 'PUBLISHED-ARTICLE-BYTES') === 1, 'article lost or duplicated its inert published facts');
    twins_overhaul_renderer_assert(strpos($rendered, 'data-original=') === false && strpos($rendered, 'data-source=') === false && strpos($rendered, 'style=') === false, 'article retained a legacy authority attribute');
    twins_overhaul_renderer_assert(strpos($rendered, 'twins-brand-editorial-page') !== false, 'article lacks the portable editorial frame');
    twins_overhaul_renderer_assert(strpos($rendered, '(833) 833-2010') !== false, 'article lost the normalized regional phone');
    twins_overhaul_renderer_assert(strpos($rendered, '/contact-us/') !== false, 'article lost the quote adapter action');
    $legalOriginal = '<div data-original="legal"><H1 class="legal-title" DATA-KEEP="yes">LEGAL TITLE</H1><p>LEGAL-BYTES</p></div>';
    $legalContext = ['title' => 'Privacy Policy', 'classification' => 'legal-preserve'];
    $legal = twins_overhaul_render_article_template($legalContext, $legalOriginal);
    twins_overhaul_renderer_assert(substr_count($legal, $legalOriginal) === 1, 'legal body is not byte-identical exactly once');
    twins_overhaul_renderer_assert(preg_match_all('/<h1\b/i', $legal) === 1, 'legal frame added a duplicate H1');
    $legalWithoutHeading = '<div data-original="legal-none"><p>LEGAL WITHOUT HEADING</p></div>';
    $legalWithFrameHeading = twins_overhaul_render_article_template($legalContext, $legalWithoutHeading);
    twins_overhaul_renderer_assert(substr_count($legalWithFrameHeading, $legalWithoutHeading) === 1, 'heading-free legal body changed');
    twins_overhaul_renderer_assert(preg_match_all('/<h1\b/i', $legalWithFrameHeading) === 1, 'heading-free legal frame did not add one H1');
}

if ($scenario === 'unknown-blog') {
    twins_overhaul_renderer_set(['blogId' => 2, 'path' => '/', 'postType' => 'page', 'postId' => 804]);
    $refusal = null;
    try {
        twins_overhaul_filter_body_classes(['existing']);
    } catch (Twins_Overhaul_Renderer_Refusal $exception) {
        $refusal = $exception;
    }
    twins_overhaul_renderer_assert($refusal instanceof Twins_Overhaul_Renderer_Refusal, 'unknown mapped blog silently kept legacy chrome');
    twins_overhaul_renderer_assert($refusal->response === 503, 'unknown mapped blog refusal did not use 503');
}

echo 'STAGING_OVERHAUL_RENDERERS_HARNESS_OK:' . $scenario . "\n";
