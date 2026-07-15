<?php

if ($argc !== 2) {
    fwrite(STDERR, "usage: staging-legacy-image-srcset-harness.php <plugin>\n");
    exit(2);
}

define('ABSPATH', __DIR__ . '/');
define('WP_ENVIRONMENT_TYPE', 'staging');
define('TWINS_STAGING_SAFETY', true);
define('DISABLE_WP_CRON', true);

class WP_Error {
    public $code;
    public function __construct($code) { $this->code = $code; }
}

$GLOBALS['hooks'] = array();
$GLOBALS['shortcodes'] = array();
function add_filter() { $GLOBALS['hooks'][] = func_get_args(); }
function add_action() { $GLOBALS['hooks'][] = func_get_args(); }
function add_shortcode($tag, $callback) { $GLOBALS['shortcodes'][$tag] = $callback; }
function remove_shortcode($tag) { unset($GLOBALS['shortcodes'][$tag]); }
function delete_option() { return true; }
function delete_network_option() { return true; }
function get_option($option, $default = false) { unset($option); return $default; }
function get_site_option($option, $default = false) { unset($option); return $default; }
function is_multisite() { return true; }
function wp_die($message = '', $title = '', $args = array()) {
    unset($message, $title, $args);
    throw new RuntimeException('unexpected staging safety refusal');
}
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }

require $argv[1];

$sources = array(
    150 => array('url' => 'https://stage.example.test/wp-content/uploads/2023/05/liftmaster-84505r-150x150.png', 'descriptor' => 'w', 'value' => 150),
    300 => array('url' => 'https://stage.example.test/wp-content/uploads/2023/05/liftmaster-84505r-300x300.png', 'descriptor' => 'w', 'value' => 300),
    768 => array('url' => 'https://stage.example.test/wp-content/uploads/2022/11/Liftmaster-768x768.jpg', 'descriptor' => 'w', 'value' => 768),
    800 => array('url' => 'https://stage.example.test/wp-content/uploads/2022/11/elementor/thumbs/Liftmaster-pxvfkw06sw4jutr19cwz68jdicnan2uq0t6tgqr0w0.jpg', 'descriptor' => 'w', 'value' => 800),
    801 => array('url' => 'https://stage.example.test/wp-content/uploads/2023/05/elementor/thumbs/liftmaster-84505r-150x150.png', 'descriptor' => 'w', 'value' => 801),
    900 => array('url' => 'https://stage.example.test/wp-content/uploads/2022/11/elementor/thumbs/similar-but-valid.jpg', 'descriptor' => 'w', 'value' => 900),
    'malformed' => 'preserve-unrelated-input',
);

$result = twins_staging_safety_filter_broken_legacy_srcset($sources);
if (isset($result[800]) || isset($result[801])) {
    throw new RuntimeException('proven-missing legacy candidate survived');
}
foreach (array(150, 300, 768, 900, 'malformed') as $key) {
    if (!array_key_exists($key, $result) || $result[$key] !== $sources[$key]) {
        throw new RuntimeException('unrelated srcset candidate changed');
    }
}
if (twins_staging_safety_filter_broken_legacy_srcset('unchanged') !== 'unchanged') {
    throw new RuntimeException('non-array input changed');
}

$registered = false;
foreach ($GLOBALS['hooks'] as $hook) {
    if (($hook[0] ?? null) === 'wp_calculate_image_srcset'
        && ($hook[1] ?? null) === 'twins_staging_safety_filter_broken_legacy_srcset'
        && ($hook[2] ?? null) === PHP_INT_MAX
        && ($hook[3] ?? null) === 1) {
        $registered = true;
    }
}
if (!$registered) {
    throw new RuntimeException('legacy srcset filter hook is missing');
}

echo "STAGING_LEGACY_IMAGE_SRCSET_HARNESS_OK\n";

