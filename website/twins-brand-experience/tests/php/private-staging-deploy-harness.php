<?php
declare(strict_types=1);

define('TWINS_PRIVATE_STAGING_DEPLOY_TESTING', true);
require_once $argv[1];

$scenario = $argv[2] ?? '';
$fixture = TwinsPrivateStagingDeployHarness::fixture();
try {
    $result = TwinsPrivateStagingDeployHarness::run($scenario, $fixture);
    if (!is_array($result) || ($result['writeAuthority'] ?? null) !== false || ($result['productionWriteAuthority'] ?? null) !== false) {
        throw new RuntimeException('Unsafe result envelope.');
    }
    echo $scenario . '-ok';
} finally {
    TwinsPrivateStagingDeployHarness::cleanup($fixture);
}
