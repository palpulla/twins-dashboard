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
    cost_assert(substr_count($markup, 'Planning ranges from our own completed jobs, not quotes. Every project gets its own flat number, in person, before any work starts.') >= 2, $market . ' short disclaimer placement is incomplete');

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

// Every published range must match docs/marketing/website-rebuild/data/
// price-ranges.json (p20/p80, 24-month window ending 2026-08-18) or one of
// the two approved offers ($0 service call with repair, $49 tune-up).
foreach (array('$575 to $1,225', '$325 to $625', '$100 to $300', '$775 to $1,400', '$2,625 to $3,525', '$3,425 to $4,400', '$49') as $range) {
    cost_assert(strpos($madison, $range) !== false, 'Madison missing ' . $range);
    cost_assert(strpos($milwaukee, $range) !== false, 'Milwaukee missing ' . $range);
}
foreach (array('$400 to $1,050', '$900 to $1,450', '$3,000 to $4,100', '$4,400 to $7,250', '$780 to $1,660') as $staleRange) {
    cost_assert(strpos($madison, $staleRange) === false, 'Madison carries the retired range ' . $staleRange);
    cost_assert(strpos($milwaukee, $staleRange) === false, 'Milwaukee carries the retired range ' . $staleRange);
}

// The "based on N completed jobs" framing must trace to price-ranges.json.
// The counts are company-wide, so Milwaukee may show them only with the
// explicit service-area framing and never as Madison-area or Milwaukee facts.
foreach (array('Based on 432 completed jobs', 'Based on 50 completed jobs', 'Based on 23 completed jobs', 'Based on 93 completed jobs', 'Based on 15 completed jobs', 'Based on 47 completed jobs') as $sample) {
    cost_assert(strpos($madison, $sample) !== false, 'Madison missing sample ' . $sample);
    cost_assert(strpos($milwaukee, $sample) !== false, 'Milwaukee missing sample ' . $sample);
}
foreach (array('516 completed jobs', '378 jobs', '55 jobs', '48 jobs', '35 jobs') as $staleSample) {
    cost_assert(strpos($madison, $staleSample) === false, 'Madison carries the retired sample ' . $staleSample);
    cost_assert(strpos($milwaukee, $staleSample) === false, 'Milwaukee carries the retired sample ' . $staleSample);
}
foreach (array(
    'The short answer',
    'What should you expect to pay?',
    'based on 62 completed installations in the Madison area over the last two years',
    'The Madison price table',
    'Three honest footnotes',
    'Fifteen jobs is enough to say "typically," not enough to promise.',
    'Every Twins project is priced individually',
    'Nothing moves the number as far as the width of your opening.',
    'Repair when',
    'The door itself is sound',
    'A steel door in good shape routinely outlives two or three sets of springs',
    'Replace when',
    'The door is the problem',
    'Rust is eating the bottom sections',
    'A Madison garage door handles seasonal temperature swings, moisture, road salt, and daily use.',
    'Monthly payments on new doors',
    'Approval and terms are provided by GoodLeap, the financing partner.',
    'Book online or call',
    'Tell us repair or new door. Same-day slots exist for repairs.',
    'One flat number, before anything starts',
    'The guarantee backs it',
    '"Done Right, or We Make It Right." If something about our work is not right, we come back and fix it. No arguing, no fine print.',
    'When a sample is thin, we say so.'
) as $canonicalContent) {
    cost_assert(strpos($madison, esc_html($canonicalContent)) !== false, 'Madison canonical content is missing: ' . $canonicalContent);
}
cost_assert(strpos($madison, 'class="twins-overhaul-button twins-cost-secondary-button"') !== false, 'Madison secondary CTA treatment is missing');
cost_assert(substr_count($madison, 'Pricing data generated August 18, 2026 · Completed Twins Garage Doors jobs from August 2024 through August 2026.') === 1, 'Madison consolidated source line count is not one');
cost_assert(substr_count($milwaukee, 'Pricing data generated August 18, 2026 · Completed Twins Garage Doors jobs from August 2024 through August 2026.') === 1, 'Milwaukee consolidated source line count is not one');
cost_assert(strpos($milwaukee, 'completed Milwaukee jobs') === false, 'Milwaukee invents local completed-job evidence');
cost_assert(strpos($milwaukee, 'Milwaukee-area installations') === false, 'Milwaukee invents local installation evidence');
cost_assert(strpos($milwaukee, 'in the Madison area over the last two years') === false, 'Milwaukee inherited the Madison-area evidence claim');
cost_assert(strpos($milwaukee, 'Madison-area installations') === false, 'Milwaukee inherited the Madison-area install claim');
cost_assert(strpos($milwaukee, 'Based on completed local jobs') === false, 'Milwaukee inherits an unsupported city-local evidence promise');
cost_assert(substr_count($milwaukee, esc_html('across our Wisconsin service area')) >= 2, 'Milwaukee is missing the honest company-wide framing');
cost_assert(strpos($madison, '(608) 420-2377') !== false && strpos($madison, '2921 Landmark Pl, Ste 206') !== false, 'Madison identity is incomplete');
cost_assert(strpos($milwaukee, '(414) 800-9271') !== false && strpos($milwaukee, '11220 W Burleigh St Ste 100') !== false, 'Milwaukee identity is incomplete');
foreach (array($madison, $milwaukee) as $styleMarkup) {
    cost_assert(strpos($styleMarkup, "\u{2014}") === false && strpos($styleMarkup, "\u{2013}") === false, 'cost page carries an em- or en-dash');
    cost_assert(stripos($styleMarkup, '24/7') === false && stripos($styleMarkup, 'lifetime') === false, 'cost page carries banned copy');
}

$refused = false;
try {
    twins_overhaul_cost_data('caller-market');
} catch (Twins_Overhaul_Cost_Refusal $exception) {
    $refused = true;
}
cost_assert($refused, 'unknown cost market did not fail closed');

echo "STAGING_OVERHAUL_COST_HARNESS_OK\n";
