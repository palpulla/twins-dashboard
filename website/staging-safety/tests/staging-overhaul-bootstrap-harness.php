<?php

declare(strict_types=1);

final class Twins_Overhaul_Bootstrap_Refusal extends RuntimeException
{
    public int $response;

    public function __construct(string $message, int $response)
    {
        parent::__construct($message);
        $this->response = $response;
    }
}

$GLOBALS['twins_overhaul_bootstrap_hooks'] = [];

function wp_die($message, $title = '', $arguments = []): void
{
    unset($title);
    $response = is_array($arguments)
        ? (int) ($arguments['response'] ?? $arguments['status'] ?? 0)
        : (int) $arguments;
    throw new Twins_Overhaul_Bootstrap_Refusal((string) $message, $response);
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1): bool
{
    $GLOBALS['twins_overhaul_bootstrap_hooks'][] = ['action', $hook, $callback, $priority, $accepted_args];
    return true;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): bool
{
    $GLOBALS['twins_overhaul_bootstrap_hooks'][] = ['filter', $hook, $callback, $priority, $accepted_args];
    return true;
}

function twins_overhaul_bootstrap_harness_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if ($argc !== 3 || !is_file($argv[1])) {
    fwrite(STDERR, "STAGING_OVERHAUL_PACKAGE_MISSING\n");
    exit(2);
}

$plugin_path = realpath($argv[1]);
$scenario = $argv[2];
$scenarios = [
    'missingEnvironment',
    'wrongEnvironment',
    'missingSafetyFlag',
    'falseSafetyFlag',
    'missingCronDisable',
    'falseCronDisable',
];
if (!in_array($scenario, $scenarios, true)) {
    fwrite(STDERR, "UNKNOWN_BOOTSTRAP_SCENARIO\n");
    exit(2);
}

define('ABSPATH', __DIR__ . '/');

if ($scenario !== 'missingEnvironment') {
    define('WP_ENVIRONMENT_TYPE', $scenario === 'wrongEnvironment' ? 'production' : 'staging');
}
if ($scenario !== 'missingSafetyFlag') {
    define('TWINS_STAGING_SAFETY', $scenario !== 'falseSafetyFlag');
}
if ($scenario !== 'missingCronDisable') {
    define('DISABLE_WP_CRON', $scenario !== 'falseCronDisable');
}

$included_before = array_map('realpath', get_included_files());
$refusal = null;
try {
    require $plugin_path;
} catch (Twins_Overhaul_Bootstrap_Refusal $exception) {
    $refusal = $exception;
}

$included_after = array_map('realpath', get_included_files());
$new_includes = array_values(array_filter(
    array_diff($included_after, $included_before),
    static function ($file) use ($plugin_path): bool {
        return $file !== $plugin_path;
    }
));

twins_overhaul_bootstrap_harness_assert($refusal instanceof Twins_Overhaul_Bootstrap_Refusal, 'invalid configuration did not refuse bootstrap');
twins_overhaul_bootstrap_harness_assert($refusal->response === 503, 'bootstrap refusal did not use HTTP 503');
twins_overhaul_bootstrap_harness_assert($new_includes === [], 'implementation loaded before all staging gates passed');
twins_overhaul_bootstrap_harness_assert($GLOBALS['twins_overhaul_bootstrap_hooks'] === [], 'hook registered before all staging gates passed');

echo json_encode([
    'scenario' => $scenario,
    'status' => 'refused',
    'response' => $refusal->response,
    'implementationLoads' => count($new_includes),
    'hookRegistrations' => count($GLOBALS['twins_overhaul_bootstrap_hooks']),
], JSON_UNESCAPED_SLASHES) . "\n";
