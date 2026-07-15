<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_ENVIRONMENT_TYPE', 'staging');
define('TWINS_STAGING_SAFETY', true);
define('DISABLE_WP_CRON', true);

function wp_die($message, $title = '', $arguments = []): void
{
    unset($title, $arguments);
    throw new RuntimeException((string) $message);
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

function remove_action($hook, $callback, $priority = 10): bool
{
    unset($hook, $callback, $priority);
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

function esc_url_raw($url): string
{
    return (string) $url;
}

function esc_html__($text, $domain = null): string
{
    unset($domain);
    return esc_html($text);
}

function __($text, $domain = null): string
{
    unset($domain);
    return (string) $text;
}

function home_url($path = ''): string
{
    return 'https://staging.example.test' . '/' . ltrim((string) $path, '/');
}

function network_home_url($path = ''): string
{
    return home_url($path);
}

function site_url($path = ''): string
{
    return home_url($path);
}

function plugins_url($path = '', $plugin = ''): string
{
    unset($plugin);
    return '/wp-content/mu-plugins/' . ltrim((string) $path, '/');
}

function wp_parse_url($url, $component = -1)
{
    return parse_url((string) $url, (int) $component);
}

function wp_json_encode($value, $flags = 0, $depth = 512)
{
    return json_encode($value, (int) $flags, (int) $depth);
}

function wp_kses_post($html): string
{
    return (string) $html;
}

function sanitize_key($key): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)) ?? '';
}

function get_current_blog_id(): int
{
    return 4;
}

function twins_overhaul_harness_assert(bool $condition, string $message): void
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

assert(twins_overhaul_classify_request(4, '/wi/garage-door-cost-in-madison-wi/', 'page', 6807) === 'cost-madison');
assert(twins_overhaul_classify_request(1, '/madison-garage-door-repair-lp/', 'page', 7092) === 'campaign-preserve');
assert(twins_overhaul_classify_request(1, '/careers/', 'page', 7341) === 'careers-brand');
assert(twins_overhaul_classify_request(5, '/il/location/rockford/', 'location', 0) === 'location');
assert(twins_overhaul_should_render_chrome('campaign-preserve') === false);
assert(twins_overhaul_should_render_chrome('cost-madison') === true);
assert(count(twins_overhaul_illinois_manifest()['cities']) === 12);

$interface_arity = [
    'twins_overhaul_regions' => 0,
    'twins_overhaul_navigation' => 1,
    'twins_overhaul_illinois_manifest' => 0,
    'twins_overhaul_classify_request' => 4,
    'twins_overhaul_should_render_chrome' => 1,
    'twins_overhaul_render_header' => 1,
    'twins_overhaul_render_footer' => 1,
    'twins_overhaul_render_cost_page' => 2,
    'twins_overhaul_render_builder' => 1,
];
foreach ($interface_arity as $function_name => $arity) {
    twins_overhaul_harness_assert(
        (new ReflectionFunction($function_name))->getNumberOfParameters() === $arity,
        $function_name . ' accepts caller-selected configuration'
    );
}

$expected_regions = [
    1 => ['main', '(833) 833-2010'],
    3 => ['ky', '(833) 833-2010'],
    4 => ['wi', '(608) 420-2377'],
    5 => ['il', '(815) 800-2025'],
];
$actual_regions = twins_overhaul_regions();
foreach ($expected_regions as $blog_id => [$region_key, $phone]) {
    twins_overhaul_harness_assert(isset($actual_regions[$blog_id]), 'fixed region is missing: ' . $blog_id);
    twins_overhaul_harness_assert(($actual_regions[$blog_id]['key'] ?? null) === $region_key, 'region key mismatch: ' . $blog_id);
    twins_overhaul_harness_assert(($actual_regions[$blog_id]['phone'] ?? null) === $phone, 'region phone mismatch: ' . $blog_id);
}
twins_overhaul_harness_assert(array_keys($actual_regions) === [1, 3, 4, 5], 'caller-selectable or unknown region is present');

$nav_groups = ['Services', 'Garage Doors', 'Service Areas', 'Resources', 'About'];
$navigation = twins_overhaul_navigation('wi');
twins_overhaul_harness_assert(array_keys($navigation) === $nav_groups, 'navigation is not the fixed five-group manifest');

$illinois_cities = ['rockford','loves-park','machesney-park','belvidere','roscoe','rockton','cherry-valley','poplar-grove','south-beloit','winnebago','byron','caledonia'];
twins_overhaul_harness_assert(twins_overhaul_illinois_manifest()['cities'] === $illinois_cities, 'Illinois city manifest is not fixed');

$context = $actual_regions[4] + [
    'blogId' => 4,
    'region' => 'wi',
    'key' => 'wi',
    'phone' => '(608) 420-2377',
    'tel' => '+16084202377',
    'base' => '/wi/',
    'path' => '/wi/garage-door-cost-in-madison-wi/',
    'title' => 'Garage Door Cost in Madison, WI',
];

$header = twins_overhaul_render_header($context);
$footer = twins_overhaul_render_footer($context);
$cost = twins_overhaul_render_cost_page('madison', $context);
$builder = twins_overhaul_render_builder($context);

foreach (['header' => $header, 'footer' => $footer, 'cost' => $cost, 'builder' => $builder] as $name => $markup) {
    twins_overhaul_harness_assert(is_string($markup) && $markup !== '', $name . ' renderer returned no markup');
}

$cost_document = $header . $cost . $footer;
$builder_document = $header . $builder . $footer;
twins_overhaul_harness_assert(preg_match_all('/<h1\b/i', $cost_document) === 1, 'cost document must render exactly one H1');
twins_overhaul_harness_assert(preg_match_all('/<h1\b/i', $builder_document) === 1, 'builder document must render exactly one H1');
twins_overhaul_harness_assert(preg_match_all('/<nav\b[^>]*aria-label=["\']Primary["\']/i', $header) === 1, 'header must render one primary navigation');
$nav_start = strpos($header, '<nav class="twins-overhaul-nav"');
$close_start = strpos($header, 'twins-overhaul-menu-close');
$groups_start = strpos($header, '<ul class="twins-overhaul-nav__groups">');
twins_overhaul_harness_assert($nav_start !== false && $close_start !== false && $groups_start !== false, 'header must render the drawer close control and navigation groups');
twins_overhaul_harness_assert($nav_start < $close_start && $close_start < $groups_start, 'drawer close control must be inside the nav before its groups');
twins_overhaul_harness_assert((bool) preg_match('/<button\b[^>]*class=["\'][^"\']*twins-overhaul-menu-close[^"\']*["\'][^>]*>/i', $header), 'drawer close control class is missing');
twins_overhaul_harness_assert((bool) preg_match('/<button\b[^>]*\btype=["\']button["\'][^>]*>/i', $header), 'drawer close control must use explicit type button');
twins_overhaul_harness_assert((bool) preg_match('/<button\b[^>]*\bdata-twins-overhaul-menu-close(?:=["\']["\'])?[^>]*>\s*Close (?:menu|navigation)\s*<\/button>/i', $header), 'drawer close control must be wired and visibly labelled');
$nav_summary_count = preg_match_all('/<summary>([^<]*)<\/summary>/', $header, $nav_summary_matches);
twins_overhaul_harness_assert($nav_summary_count === count($nav_groups), 'header must render exactly the fixed navigation summaries');
twins_overhaul_harness_assert($nav_summary_matches[1] === array_map('esc_html', $nav_groups), 'header navigation summaries must match the fixed five groups in order');

$all_markup = $cost_document . $builder_document;
twins_overhaul_harness_assert(stripos($all_markup, 'twinsgaragedoors.com') === false, 'production URL leaked into rendered markup');
twins_overhaul_harness_assert(!preg_match('/<form\b[^>]*\baction\s*=/i', $all_markup), 'rendered form has an action');
twins_overhaul_harness_assert(!preg_match('/<(?:button|input)\b[^>]*\btype\s*=\s*(?:["\'](?:submit|image)["\']|(?:submit|image)\b)/i', $all_markup), 'rendered markup has an active submit control');
twins_overhaul_harness_assert(!preg_match('/<button\b(?![^>]*\btype\s*=\s*(?:["\']button["\']|button)(?:\s|>))[^>]*>/i', $all_markup), 'rendered markup has an implicit submit button');
twins_overhaul_harness_assert(!preg_match('/<script\b[^>]*\bsrc\s*=\s*["\'](?:https?:)?\/\//i', $all_markup), 'rendered markup loads an external script');
twins_overhaul_harness_assert(!preg_match('/\b(?:fetch|XMLHttpRequest|sendBeacon)\s*\(/i', $all_markup), 'rendered markup contains an external connection primitive');

$staging_notice = 'This private staging preview does not submit or store lead information.';
twins_overhaul_harness_assert(strpos($cost, $staging_notice) !== false, 'cost renderer is missing the fixed staging-only notice');
twins_overhaul_harness_assert(strpos($builder, $staging_notice) !== false, 'builder renderer is missing the fixed staging-only notice');

echo "STAGING_OVERHAUL_HARNESS_OK\n";
