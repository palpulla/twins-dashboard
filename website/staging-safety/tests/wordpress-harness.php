<?php

// Runtime coverage for environments that provide PHP. Local runs may skip this
// harness when PHP is unavailable; CI has a separate mandatory-PHP assertion.

if ($argc !== 2) {
    fwrite(STDERR, "usage: wordpress-harness.php <plugin>\n");
    exit(2);
}

$plugin = $argv[1];

function run_boot_scenario($plugin, $scenario) {
$script = <<<'PHP'
<?php
class WPDieException extends RuntimeException {}
class WP_Error {
    public $code;
    public function __construct($code) { $this->code = $code; }
}
$scenario = getenv('TWINS_TEST_SCENARIO');
define('ABSPATH', __DIR__ . '/');
if ($scenario !== 'missingEnvironment') define('WP_ENVIRONMENT_TYPE', $scenario === 'wrongEnvironment' ? 'production' : 'staging');
if (!in_array($scenario, array('missingSafetyFlag', 'missingEnvironment', 'wrongEnvironment'), true)) define('TWINS_STAGING_SAFETY', $scenario === 'falseSafetyFlag' ? false : true);
if ($scenario !== 'missingCronDisable') define('DISABLE_WP_CRON', true);
$GLOBALS['hooks'] = array();
$GLOBALS['shortcodes'] = array();
$GLOBALS['deleted_options'] = array();
$GLOBALS['deleted_network_options'] = array();
$GLOBALS['options'] = array();
$GLOBALS['site_options'] = array();
if ($scenario === 'preexistingOrdinary') $GLOBALS['options']['elementor_connect_site_key'] = 'secret';
if ($scenario === 'preexistingNetwork') $GLOBALS['site_options']['elementor_connect_site_key'] = 'secret';
if ($scenario === 'preexistingBrainstrom') $GLOBALS['options']['brainstrom_products'] = array('product' => array('accessToken' => 'secret'));
function add_filter() { $GLOBALS['hooks'][] = func_get_args(); }
function add_action() { $GLOBALS['hooks'][] = func_get_args(); }
function add_shortcode($tag, $callback) { $GLOBALS['shortcodes'][$tag] = $callback; }
function remove_shortcode($tag) { unset($GLOBALS['shortcodes'][$tag]); }
function delete_option($option) { $GLOBALS['deleted_options'][] = $option; unset($GLOBALS['options'][$option]); return true; }
function delete_network_option($network_id, $option) { $GLOBALS['deleted_network_options'][] = array($network_id, $option); unset($GLOBALS['site_options'][$option]); return true; }
function get_option($option, $default = false) { return array_key_exists($option, $GLOBALS['options']) ? $GLOBALS['options'][$option] : $default; }
function get_site_option($option, $default = false) { return array_key_exists($option, $GLOBALS['site_options']) ? $GLOBALS['site_options'][$option] : $default; }
function is_multisite() { return true; }
function wp_die($message, $title = '', $args = array()) { throw new WPDieException($message, (int) ($args['response'] ?? 0)); }
try {
    require getenv('TWINS_TEST_PLUGIN');
    echo json_encode(array('status' => 'booted', 'hooks' => count($GLOBALS['hooks'])));
} catch (WPDieException $error) {
    echo json_encode(array('status' => 'refused', 'response' => $error->getCode(), 'hooks' => count($GLOBALS['hooks'])));
}
PHP;

    $temporary = tempnam(sys_get_temp_dir(), 'twins-staging-');
    file_put_contents($temporary, $script);
    $command = sprintf(
        'TWINS_TEST_SCENARIO=%s TWINS_TEST_PLUGIN=%s %s %s',
        escapeshellarg($scenario),
        escapeshellarg($plugin),
        escapeshellarg(PHP_BINARY),
        escapeshellarg($temporary)
    );
    exec($command, $output, $status);
    unlink($temporary);
    if ($status !== 0) {
        throw new RuntimeException(implode("\n", $output));
    }
    return json_decode(implode("\n", $output), true);
}

$scenarios = array(
    'missingEnvironment',
    'wrongEnvironment',
    'missingSafetyFlag',
    'falseSafetyFlag',
    'missingCronDisable',
    'preexistingOrdinary',
    'preexistingNetwork',
    'preexistingBrainstrom',
    'configuredStaging',
);
$boot = array();
foreach ($scenarios as $scenario) {
    $result = run_boot_scenario($plugin, $scenario);
    if ($scenario === 'configuredStaging' && ($result['hooks'] ?? 0) === 0) {
        throw new RuntimeException('Configured staging registered no hooks.');
    }
    if ($scenario !== 'configuredStaging' && (($result['response'] ?? 0) !== 503 || ($result['hooks'] ?? -1) !== 0)) {
        throw new RuntimeException('Refusal was not fail-closed for ' . $scenario . '.');
    }
    $boot[$scenario] = $result['status'];
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
$GLOBALS['deleted_options'] = array();
$GLOBALS['deleted_network_options'] = array();
$GLOBALS['options'] = array();
$GLOBALS['site_options'] = array();
function add_filter() { $GLOBALS['hooks'][] = func_get_args(); }
function add_action() { $GLOBALS['hooks'][] = func_get_args(); }
function add_shortcode($tag, $callback) { $GLOBALS['shortcodes'][$tag] = $callback; }
function remove_shortcode($tag) { unset($GLOBALS['shortcodes'][$tag]); }
function delete_option($option) { $GLOBALS['deleted_options'][] = $option; unset($GLOBALS['options'][$option]); return true; }
function delete_network_option($network_id, $option) { $GLOBALS['deleted_network_options'][] = array($network_id, $option); unset($GLOBALS['site_options'][$option]); return true; }
function get_option($option, $default = false) { return array_key_exists($option, $GLOBALS['options']) ? $GLOBALS['options'][$option] : $default; }
function get_site_option($option, $default = false) { return array_key_exists($option, $GLOBALS['site_options']) ? $GLOBALS['site_options'][$option] : $default; }
function is_multisite() { return true; }
function wp_die() { throw new RuntimeException('unexpected refusal'); }
require $plugin;

function ordinary_option_add_result($option, $value) {
    try {
        twins_staging_safety_reject_unsafe_option_add($option, $value);
        return 'allowed';
    } catch (RuntimeException $error) {
        return 'refused';
    }
}

$review_placeholder = function_exists('twins_staging_safety_review_placeholder')
    ? twins_staging_safety_review_placeholder(array('onload' => 'attacker'), '<script>attacker</script>')
    : '__missing_review_placeholder__';
$integration_placeholder = function_exists('twins_staging_safety_disabled_integration_placeholder')
    ? twins_staging_safety_disabled_integration_placeholder(array('onload' => 'attacker'), '<script>attacker</script>', 'clopay_product')
    : '__missing_integration_placeholder__';
$quarantined_option_update = function_exists('twins_staging_safety_keep_quarantined_option_empty')
    ? twins_staging_safety_keep_quarantined_option_empty('attacker', false, 'elementor_connect_site_key')
    : '__missing_quarantined_option_blocker__';
if (function_exists('twins_staging_safety_delete_quarantined_option_after_add')) {
    twins_staging_safety_delete_quarantined_option_after_add('elementor_connect_site_key', 'attacker');
    twins_staging_safety_delete_quarantined_option_after_add('ordinary_option', 'safe');
}
if (function_exists('twins_staging_safety_delete_quarantined_site_option_after_add')) {
    twins_staging_safety_delete_quarantined_site_option_after_add('elementor_connect_site_key', 'attacker', 7);
    twins_staging_safety_delete_quarantined_site_option_after_add('ordinary_option', 'safe', 7);
}

function rejected_code($arguments, $url) {
    $result = twins_staging_safety_filter_http(false, $arguments, $url);
    return $result instanceof WP_Error ? $result->code : 'not-rejected';
}

$http = array(
    'sameOriginGet' => rejected_code(array('method' => 'GET'), 'https://stage.example.test/wp-json/'),
    'sameOriginPost' => rejected_code(array('method' => 'POST'), 'https://stage.example.test/wp-json/form'),
    'clopayGet' => rejected_code(array('method' => 'GET'), 'https://www.clopaydoor.com/api/v2/GetProductsList/GetProducts?productType=Residential'),
    'clopayHead' => rejected_code(array('method' => 'HEAD'), 'https://www.clopaydoor.com/image.jpg'),
    'arbitraryExternal' => rejected_code(array('method' => 'GET'), 'https://example.com/'),
);

foreach (array('brb_collection', 'clopay_product', 'clopay_collection_grid', 'twins_door_builder') as $tag) {
    $GLOBALS['shortcodes'][$tag] = 'unsafe_late_callback';
}
twins_staging_safety_register_placeholders();
$late_shortcodes = $GLOBALS['shortcodes'];
$shortcode_fail_safe = array(
    'reviews' => twins_staging_safety_prevent_production_shortcode('attacker', 'brb_collection', array(), array()),
    'clopay' => twins_staging_safety_prevent_production_shortcode('attacker', 'clopay_product', array(), array()),
    'ordinary' => twins_staging_safety_prevent_production_shortcode('unchanged', 'gallery', array(), array()),
);

$safe_registry = array('astra-addon' => array('version' => '4.0.0', 'enabled' => true));
$old_registry = array('astra-addon' => array('version' => '3.9.0'));
$brainstrom = array(
    'safeUpdate' => twins_staging_safety_filter_brainstrom_registry_update($safe_registry, $old_registry),
    'safeAdd' => twins_staging_safety_filter_new_brainstrom_registry($safe_registry),
    'licenseUpdate' => twins_staging_safety_filter_brainstrom_registry_update(array('product' => array('licenseKey' => 'secret')), $old_registry),
    'purchaseAdd' => twins_staging_safety_filter_new_brainstrom_registry(array('product' => array('purchase_code' => 'secret'))),
    'tokenAdd' => twins_staging_safety_filter_new_brainstrom_registry(array('product' => array('accessToken' => 'secret'))),
    'keyAdd' => twins_staging_safety_filter_new_brainstrom_registry(array('product' => array('api_key' => 'secret'))),
);
$ordinary_add_guard = array(
    'quarantinedSecret' => ordinary_option_add_result('elementor_connect_site_key', 'secret'),
    'quarantinedEmpty' => ordinary_option_add_result('elementor_connect_site_key', ''),
    'brainstromSecret' => ordinary_option_add_result('brainstrom_products', array('product' => array('accessToken' => 'secret'))),
    'brainstromSafe' => ordinary_option_add_result('brainstrom_products', $safe_registry),
    'ordinary' => ordinary_option_add_result('ordinary_option', 'safe'),
);
$legacy_redirects = array(
    'madisonPage' => twins_staging_safety_legacy_redirect_path('/madison/garage-door-opener-in-madison-wi/'),
    'madisonException' => twins_staging_safety_legacy_redirect_path('/madison/hello-world/'),
    'wiMenu' => twins_staging_safety_legacy_redirect_path('/wi/location/wi/'),
    'kyPagination' => twins_staging_safety_legacy_redirect_path('/ky/category/madison/page/2/'),
    'ordinaryMissing' => twins_staging_safety_legacy_redirect_path('/unmapped-page/'),
    'unsafeRelative' => twins_staging_safety_legacy_redirect_path('madison/no-leading-slash/'),
    'unsafeTraversal' => twins_staging_safety_legacy_redirect_path('/madison/../production/'),
);

echo json_encode(array(
    'boot' => $boot,
    'mailShortCircuit' => twins_staging_safety_block_mail(null, array()),
    'http' => $http,
    'csp' => twins_staging_safety_csp_policy(),
    'reviewPlaceholder' => $review_placeholder,
    'integrationPlaceholder' => $integration_placeholder,
    'quarantinedOptionUpdate' => $quarantined_option_update,
    'quarantinedOptionAdded' => $GLOBALS['deleted_options'],
    'quarantinedNetworkOptionAdded' => $GLOBALS['deleted_network_options'],
    'quarantinedNetworkPreAdd' => twins_staging_safety_filter_new_quarantined_network_option('secret', 'elementor_connect_site_key', 7),
    'ordinaryAddGuard' => $ordinary_add_guard,
    'legacyRedirects' => $legacy_redirects,
    'lateShortcodes' => $late_shortcodes,
    'shortcodeFailSafe' => $shortcode_fail_safe,
    'brainstrom' => $brainstrom,
));
