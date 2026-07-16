<?php
declare(strict_types=1);

define('TWINS_PRIVATE_STAGING_DEPLOY_TESTING', true);
require_once $argv[1];

$scenario = $argv[2] ?? '';
$allowed = [
    'dry-run',
    'existing-core-capture',
    'existing-core-install',
    'prerequisite-drift',
    'expected-old-drift',
    'late-expected-old-drift',
    'second-deploy-conflict',
    'empty-candidate',
    'candidate-not-closed',
    'incoming-copy-failure',
    'incoming-copy-drift',
    'partial-activation-failure',
    'activation-deletion-drift',
    'activation-success-drift',
    'target-set-invalid',
    'core-boot-failure',
    'activation-failure',
    'activation-failure-drift',
    'rollback-existing-core',
    'rollback-drift',
    'non-regular-rejected',
];
if (!in_array($scenario, $allowed, true)) {
    throw new InvalidArgumentException('Invalid fixed harness scenario.');
}
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
