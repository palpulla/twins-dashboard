<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_ENVIRONMENT_TYPE', 'staging');
define('TWINS_STAGING_SAFETY', true);
define('DISABLE_WP_CRON', true);

final class Twins_Overhaul_Foundation_Refusal extends RuntimeException
{
    public int $response;

    public function __construct(string $message, int $response)
    {
        parent::__construct($message);
        $this->response = $response;
    }
}

$GLOBALS['twins_overhaul_foundation_blog_id'] = 4;

function wp_die($message, $title = '', $arguments = []): void
{
    unset($title);
    $response = is_array($arguments)
        ? (int) ($arguments['response'] ?? $arguments['status'] ?? 0)
        : (int) $arguments;
    throw new Twins_Overhaul_Foundation_Refusal((string) $message, $response);
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool
{
    unset($hook, $callback, $priority, $accepted_args);
    return true;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): bool
{
    unset($hook, $callback, $priority, $accepted_args);
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

function get_current_blog_id(): int
{
    return (int) $GLOBALS['twins_overhaul_foundation_blog_id'];
}

function twins_overhaul_foundation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "STAGING_OVERHAUL_PACKAGE_MISSING\n");
    exit(2);
}

require $argv[1];

$main_navigation = twins_overhaul_navigation('main');
$wi_navigation = twins_overhaul_navigation('wi');
$ky_navigation = twins_overhaul_navigation('ky');
$il_navigation = twins_overhaul_navigation('il');
twins_overhaul_foundation_assert($main_navigation['Garage Doors'][0]['path'] === '/clopay-garage-doors/', 'main Collections route is not confirmed');
twins_overhaul_foundation_assert($wi_navigation['Service Areas'][0]['path'] === '/wi/service-area/', 'Wisconsin service-area hub is not confirmed');
twins_overhaul_foundation_assert($ky_navigation['Garage Doors'][1]['path'] === '/ky/design-your-door/', 'Kentucky builder route is not confirmed');

$il_paths = array();
foreach (array_merge($il_navigation['Resources'], $il_navigation['About']) as $item) {
    $il_paths[] = $item['path'];
}
foreach (array('/il/financing/', '/il/coupons-offers/', '/il/faqs/', '/il/blog/', '/il/about-us/', '/il/reviews/') as $unplanned_path) {
    twins_overhaul_foundation_assert(!in_array($unplanned_path, $il_paths, true), 'unplanned Illinois route remains: ' . $unplanned_path);
}
twins_overhaul_foundation_assert($il_navigation['Resources'][0]['label'] === 'Wisconsin Garage Door Cost Guide', 'Illinois cost resource is not truthfully labeled');
twins_overhaul_foundation_assert($il_navigation['Resources'][0]['path'] === '/wi/garage-door-cost-in-madison-wi/', 'Illinois cost resource does not use the real cost page');

$spoofed_context = array(
    'region' => 'main',
    'blogId' => 1,
    'phone' => '(000) 000-0000',
    'tel' => '+10000000000',
    'base' => '/',
    'path' => '/wi/location/madison/',
);
$wisconsin_header = twins_overhaul_render_header($spoofed_context);
twins_overhaul_foundation_assert(strpos($wisconsin_header, 'data-twins-overhaul-region="wi"') !== false, 'spoofed region replaced blog 4 identity');
twins_overhaul_foundation_assert(strpos($wisconsin_header, '(608) 420-2377') !== false, 'spoofed phone replaced fixed Wisconsin phone');
twins_overhaul_foundation_assert(strpos($wisconsin_header, 'href="/wi/"') !== false, 'spoofed base replaced fixed Wisconsin base');
twins_overhaul_foundation_assert(strpos($wisconsin_header, '(833) 833-2010') === false, 'main phone leaked into Wisconsin header');

$GLOBALS['twins_overhaul_foundation_blog_id'] = 1;
$main_header = twins_overhaul_render_header(array('region' => 'il', 'blogId' => 5, 'path' => '/'));
twins_overhaul_foundation_assert(strpos($main_header, 'data-twins-overhaul-region="main"') !== false, 'spoofed Illinois identity replaced blog 1');
twins_overhaul_foundation_assert(strpos($main_header, '(833) 833-2010') !== false, 'main header lost fixed phone');

$GLOBALS['twins_overhaul_foundation_blog_id'] = 4;
$milwaukee_header = twins_overhaul_render_header(array('region' => 'il', 'path' => '/wi/garage-door-cost-in-milwaukee-wi/'));
twins_overhaul_foundation_assert(strpos($milwaukee_header, '(414) 800-9271') !== false, 'Milwaukee path did not apply fixed display override');
twins_overhaul_foundation_assert(strpos($milwaukee_header, '+14148009271') !== false, 'Milwaukee path did not apply fixed tel override');
twins_overhaul_foundation_assert(strpos($milwaukee_header, 'data-twins-overhaul-region="wi"') !== false, 'Milwaukee path changed fixed blog identity');

$footer = twins_overhaul_render_footer(array('path' => '/wi/location/madison/'));
twins_overhaul_foundation_assert(strpos($footer, 'href="/terms-conditions/"') !== false, 'confirmed terms route is missing');
twins_overhaul_foundation_assert(strpos($footer, 'href="/ada-standards/"') !== false, 'confirmed ADA route is missing');
twins_overhaul_foundation_assert(strpos($footer, '/terms-and-conditions/') === false, 'dead terms route remains');
twins_overhaul_foundation_assert(strpos($footer, '/ada-accessibility/') === false, 'dead ADA route remains');

$GLOBALS['twins_overhaul_foundation_blog_id'] = 99;
$refusal = null;
try {
    twins_overhaul_render_header(array('region' => 'main', 'blogId' => 1, 'path' => '/'));
} catch (Twins_Overhaul_Foundation_Refusal $exception) {
    $refusal = $exception;
}
twins_overhaul_foundation_assert($refusal instanceof Twins_Overhaul_Foundation_Refusal, 'unknown blog did not fail closed');
twins_overhaul_foundation_assert($refusal->response === 503, 'unknown blog refusal did not use HTTP 503');

echo "STAGING_OVERHAUL_FOUNDATION_HARNESS_OK\n";
