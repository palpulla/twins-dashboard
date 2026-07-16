<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

final class Twins_Builder_Refusal extends RuntimeException {}

$GLOBALS['twins_builder_blog_id'] = 1;

function twins_overhaul_refuse_route(string $reason): void {
    throw new Twins_Builder_Refusal($reason);
}

function twins_staging_overhaul_refuse_boot($reason): void {
    throw new Twins_Builder_Refusal((string) $reason);
}

function get_current_blog_id(): int {
    return (int) $GLOBALS['twins_builder_blog_id'];
}

function twins_overhaul_resolve_context(array $context): array {
    unset($context);
    $regions = array(
        1 => array('key' => 'main', 'phone' => '(833) 833-2010', 'tel' => '+18338332010', 'base' => '/'),
        3 => array('key' => 'ky', 'phone' => '(833) 833-2010', 'tel' => '+18338332010', 'base' => '/ky/'),
        4 => array('key' => 'wi', 'phone' => '(608) 420-2377', 'tel' => '+16084202377', 'base' => '/wi/'),
        5 => array('key' => 'il', 'phone' => '(815) 800-2025', 'tel' => '+18158002025', 'base' => '/il/'),
    );
    $blogId = get_current_blog_id();
    if (!isset($regions[$blogId])) twins_staging_overhaul_refuse_boot('unknown builder blog');
    return $regions[$blogId] + array('contact' => $regions[$blogId]['base'] . 'contact-us/');
}

function esc_html($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_url($value): string { return (string) $value; }
function wp_json_encode($value, $flags = 0, $depth = 512) { return json_encode($value, (int) $flags, (int) $depth); }

function builder_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

if ($argc !== 3 || !is_dir($argv[1]) || !is_dir($argv[2])) {
    fwrite(STDERR, "STAGING_OVERHAUL_BUILDER_PACKAGE_MISSING\n");
    exit(2);
}

require $argv[1] . '/templates/builder.php';
$builderScript = @file_get_contents($argv[2] . '/assets/js/twins-builder.js');
builder_assert(is_string($builderScript) && $builderScript !== '', 'portable builder script missing');
$flatBuilderScript = preg_replace('/\s+/', ' ', $builderScript);
builder_assert(is_string($flatBuilderScript), 'portable builder script normalization failed');
builder_assert(strpos($flatBuilderScript, "const BUILDER_PRODUCT_ORDER = Object.freeze([ '330', '320', '30', '29', '240', '26', '170', '340', '12', '16', '290', '370', '250', '380', '11', '27', '291', '8', '10', '25', '9', '13', '23', ]);") !== false, 'fixed builder order missing');
builder_assert(strpos($builderScript, 'BUILDER_LOCAL_IMAGE') !== false, 'local builder image boundary missing');
builder_assert(strpos($builderScript, 'data-builder-enhanced') !== false, 'builder enhancement marker missing');
builder_assert(strpos($builderScript, 'Manufacturer reference only.') !== false, 'builder manufacturer truth missing');
builder_assert(!preg_match('/\b(?:fetch|XMLHttpRequest|WebSocket|EventSource|sendBeacon|requestSubmit|localStorage|sessionStorage|indexedDB)\b|\.submit\s*\(/', $builderScript), 'builder script has prohibited authority');
builder_assert(!preg_match('/ZIP_ROUTES|initZip|initMenu|initPreservedForms|initReveal|TwinsOverhaulPreview/', $builderScript), 'builder script contains unrelated legacy behavior');

$catalog = twins_overhaul_builder_catalog();
builder_assert(($catalog['schemaVersion'] ?? null) === 1, 'catalog schema mismatch');
builder_assert(count($catalog['products'] ?? array()) === 23, 'catalog product count mismatch');

$phones = array(1 => '(833) 833-2010', 3 => '(833) 833-2010', 4 => '(608) 420-2377', 5 => '(815) 800-2025');
foreach ($phones as $blogId => $phone) {
    $GLOBALS['twins_builder_blog_id'] = $blogId;
    $markup = twins_overhaul_render_builder(array('phone' => '(000) 000-0000', 'base' => '/spoof/'));
    builder_assert(preg_match_all('/<h1\b/i', $markup) === 1, 'builder must have one H1 for blog ' . $blogId);
    builder_assert(strpos($markup, 'class="twins-brand-page twins-overhaul-main twins-builder-page"') !== false, 'shared brand page shell missing');
    builder_assert(strpos($markup, 'Frozen Clopay builder') !== false, 'frozen builder eyebrow missing');
    builder_assert(strpos($markup, 'fixed local 23-product catalog') !== false, 'bounded builder lead missing');
    builder_assert(strpos($markup, 'twins-builder__notice') === false, 'large debug-style builder notice survived');
    builder_assert(strpos($markup, 'Manufacturer reference only.') !== false, 'manufacturer truth missing');
    builder_assert(strpos($markup, 'This private staging preview does not submit or store lead information.') !== false, 'compact staging preview note missing');
    builder_assert(strpos($markup, $phone) !== false, 'fixed phone missing for blog ' . $blogId);
    builder_assert(strpos($markup, '(000) 000-0000') === false && strpos($markup, '/spoof/') === false, 'spoofed context trusted');
    foreach (array('Collection','Design','Color','Windows','Glass','Hardware','Summary','Contact Preview') as $stage) {
        builder_assert(strpos($markup, $stage) !== false, 'builder stage missing: ' . $stage);
    }
    builder_assert((bool) preg_match('~<script[^>]*type="application/json"[^>]*>([^<]+)</script>~', $markup, $match), 'embedded catalog missing');
    $embedded = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    builder_assert(($embedded['schemaVersion'] ?? null) === 1 && count($embedded['products'] ?? array()) === 23, 'embedded catalog invalid');
    builder_assert(!preg_match('/<form\b|type=["\'](?:submit|image)["\']|\baction\s*=|\bformaction\s*=/i', $markup), 'builder has submission authority');
    builder_assert(stripos($markup, 'clopaydoor.com') === false && stripos($markup, 'http://') === false && stripos($markup, 'https://') === false, 'builder leaks a remote URL');
}

$GLOBALS['twins_builder_blog_id'] = 99;
$refused = false;
try {
    twins_overhaul_render_builder(array());
} catch (Twins_Builder_Refusal $exception) {
    $refused = true;
}
builder_assert($refused, 'unknown blog did not fail closed');

echo "STAGING_OVERHAUL_BUILDER_HARNESS_OK\n";
