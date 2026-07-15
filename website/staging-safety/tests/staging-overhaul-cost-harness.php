<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

final class Twins_Overhaul_Cost_Refusal extends RuntimeException {}

function twins_staging_overhaul_refuse_boot($reason): void {
    throw new Twins_Overhaul_Cost_Refusal((string) $reason);
}

function twins_overhaul_refuse_route(string $reason): void {
    throw new Twins_Overhaul_Cost_Refusal($reason);
}

function esc_html($text): string {
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr($text): string {
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_url($url): string {
    return (string) $url;
}

function wp_json_encode($value, $flags = 0, $depth = 512) {
    return json_encode($value, (int) $flags, (int) $depth);
}

function twins_overhaul_asset_url(string $name): string {
    $assets = array(
        'truck-png' => '/wp-content/mu-plugins/twins-staging-assets/twins-service-truck-cutout.png',
        'truck-webp' => '/wp-content/mu-plugins/twins-staging-assets/twins-service-truck-cutout.webp',
    );
    return $assets[$name] ?? '';
}

function cost_assert(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if ($argc !== 2 || !is_dir($argv[1])) {
    fwrite(STDERR, "STAGING_OVERHAUL_COST_PACKAGE_MISSING\n");
    exit(2);
}

require $argv[1] . '/cost-data.php';
require $argv[1] . '/templates/cost.php';

$spoofed = array(
    'phone' => '(000) 000-0000',
    'tel' => '+10000000000',
    'path' => '/caller-selected/',
    'title' => 'Caller title',
);
$madison = twins_overhaul_render_cost_page('madison', $spoofed);
$milwaukee = twins_overhaul_render_cost_page('milwaukee', $spoofed);

foreach (array('madison' => $madison, 'milwaukee' => $milwaukee) as $market => $markup) {
    cost_assert(preg_match_all('/<h1\b/i', $markup) === 1, $market . ' must render exactly one H1');
    cost_assert(strpos($markup, '(000) 000-0000') === false, $market . ' trusted spoofed phone');
    cost_assert(strpos($markup, '/caller-selected/') === false, $market . ' trusted spoofed path');
    cost_assert(strpos($markup, 'twinsgaragedoors.com') === false, $market . ' leaked production URL');
    cost_assert(!preg_match('/<form\b|type=["\'](?:submit|image)["\']|\baction\s*=|\bformaction\s*=/i', $markup), $market . ' has active form authority');
    cost_assert(substr_count($markup, 'Historical planning ranges only. Every project is evaluated and priced individually.') >= 2, $market . ' short disclaimer placement is incomplete');

    $order = array('twins-cost-hero', 'twins-cost-promise', 'twins-cost-answer', 'twins-cost-pricing', 'twins-cost-factors', 'twins-cost-decision', 'twins-cost-climate', 'twins-cost-financing', 'twins-cost-process', 'twins-cost-faq', 'twins-cost-service-area');
    $previous = -1;
    foreach ($order as $class) {
        $position = strpos($markup, $class);
        cost_assert($position !== false && $position > $previous, $market . ' section order failed at ' . $class);
        $previous = $position;
    }
    cost_assert(strpos($markup, 'data-twins-overhaul-zip') < strpos($markup, 'twins-overhaul-fleet-proof'), $market . ' truck precedes ZIP control');

    cost_assert((bool) preg_match('~<script type="application/ld\+json">([^<]+)</script>~', $markup, $schemaMatch), $market . ' schema is missing');
    $schema = json_decode($schemaMatch[1], true, 512, JSON_THROW_ON_ERROR);
    $visibleMarkup = preg_replace('~<script type="application/ld\+json">[^<]+</script>~', '', $markup);
    cost_assert(is_string($visibleMarkup), $market . ' visible-schema split failed');
    $faqStart = strpos($visibleMarkup, 'twins-cost-faq');
    $faqEnd = strpos($visibleMarkup, 'twins-cost-service-area');
    cost_assert($faqStart !== false && $faqEnd !== false && $faqStart < $faqEnd, $market . ' visible FAQ boundary is missing');
    $visibleFaq = substr($visibleMarkup, $faqStart, $faqEnd - $faqStart);
    $graph = $schema['@graph'] ?? array();
    $faqNodes = array_values(array_filter($graph, static fn(array $node): bool => ($node['@type'] ?? '') === 'FAQPage'));
    cost_assert(count($faqNodes) === 1, $market . ' FAQPage count is not one');
    $questions = $faqNodes[0]['mainEntity'] ?? array();
    cost_assert(count($questions) === 5, $market . ' FAQ schema must contain five questions');
    foreach ($questions as $question) {
        $visibleQuestion = (string) ($question['name'] ?? '');
        $visibleAnswer = (string) ($question['acceptedAnswer']['text'] ?? '');
        cost_assert($visibleQuestion !== '' && substr_count($visibleFaq, esc_html($visibleQuestion)) === 1, $market . ' schema question is not visible exactly once in the FAQ');
        cost_assert($visibleAnswer !== '' && substr_count($visibleFaq, esc_html($visibleAnswer)) === 1, $market . ' schema answer is not visible exactly once in the FAQ');
    }
}

foreach (array('$400 to $1,050', '$900 to $1,450', '$3,000 to $4,100', '$4,400 to $7,250', '$49') as $range) {
    cost_assert(strpos($madison, $range) !== false, 'Madison missing ' . $range);
    cost_assert(strpos($milwaukee, $range) !== false, 'Milwaukee missing ' . $range);
}

foreach (array('516 completed jobs', '378 jobs', '55 jobs', '48 jobs', '35 jobs') as $sample) {
    cost_assert(strpos($madison, $sample) !== false, 'Madison missing sample ' . $sample);
    cost_assert(strpos($milwaukee, $sample) === false, 'Milwaukee contains unsupported sample ' . $sample);
}
foreach (array(
    'The short answer',
    'What should you expect to pay?',
    "We use completed local jobs to publish useful planning ranges while pricing every customer's project individually.",
    'Garage door repair and installation prices in Madison',
    'These are planning ranges, not instant quotes. Door condition, parts, size, and product selection determine the exact price.',
    'Every Twins project is priced individually',
    'Insulated steel, wood-look composite, and full-view glass have different product costs.',
    'Repair may fit when',
    'The door itself is still sound',
    'The problem is limited to one component',
    'Compare replacement when',
    'Problems affect the whole system',
    'Several panels or major components are damaged',
    'A Madison garage door needs to handle seasonal temperature changes, moisture, and daily use. These factors are worth discussing when you compare products.',
    'Correctly fitted bottom, side, and top seals help manage drafts, moisture, and debris.',
    'Plan the project around your home and budget',
    'Approval and terms are provided by the financing partner.',
    'Tell us what is happening',
    'Call or book online and share the problem, door type, and timing.',
    'Get an on-site diagnosis',
    'A technician inspects the system and identifies the required work.',
    'Approve the exact price',
    'You see the price and available options before repair or installation begins.',
    'Original local data, clearly explained and reviewed for freshness.'
) as $canonicalContent) {
    cost_assert(strpos($madison, esc_html($canonicalContent)) !== false, 'Madison canonical content is missing: ' . $canonicalContent);
}
cost_assert(strpos($madison, 'class="twins-overhaul-button twins-cost-secondary-button"') !== false, 'Madison secondary CTA treatment is missing');
cost_assert(substr_count($madison, 'Pricing data reviewed July 10, 2026 · Based on completed Twins Garage Doors jobs from July 2025 through July 2026.') === 1, 'Madison consolidated source line count is not one');
cost_assert(strpos($milwaukee, 'completed Milwaukee jobs') === false, 'Milwaukee invents local completed-job evidence');
cost_assert(strpos($milwaukee, 'Based on completed local jobs') === false, 'Milwaukee inherits an unsupported city-local evidence promise');
cost_assert(strpos($milwaukee, 'Historical planning ranges') !== false, 'Milwaukee safe historical label is missing');
cost_assert(strpos($milwaukee, 'Typical local ranges') === false, 'Milwaukee inherits the Madison-only local pricing label');
cost_assert(strpos($madison, '(608) 420-2377') !== false && strpos($madison, '2921 Landmark Pl, Ste 206') !== false, 'Madison identity is incomplete');
cost_assert(strpos($milwaukee, '(414) 800-9271') !== false && strpos($milwaukee, '11220 W Burleigh St Ste 100') !== false, 'Milwaukee identity is incomplete');

$refused = false;
try {
    twins_overhaul_cost_data('caller-market');
} catch (Twins_Overhaul_Cost_Refusal $exception) {
    $refused = true;
}
cost_assert($refused, 'unknown cost market did not fail closed');

echo "STAGING_OVERHAUL_COST_HARNESS_OK\n";
