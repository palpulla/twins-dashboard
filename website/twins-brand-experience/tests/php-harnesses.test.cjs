const assert = require('node:assert/strict');
const cp = require('node:child_process');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const php = cp.spawnSync('php', ['-v'], { encoding: 'utf8' });
const hasPhp = php.status === 0;
if (process.env.CI && !hasPhp) throw new Error('PHP is mandatory in CI');

function phpTest(name, harness, args = [], expectedOutput = '') {
  test(name, { skip: !hasPhp && 'PHP CLI unavailable locally' }, () => {
    const result = cp.spawnSync('php', [path.join(root, 'tests/php', harness), ...args], { encoding: 'utf8' });
    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
    if (expectedOutput !== '') assert.equal(result.stdout, expectedOutput);
  });
}

phpTest('portable core boots without WordPress', 'portable-core-harness.php', [path.join(root, 'bootstrap.php')]);
phpTest('shared component renderer contracts', 'renderer-contract-harness.php', [path.join(root, 'bootstrap.php')], 'renderer-contracts-ok');

const reviewScenarios = [
  'valid',
  'bad-record-hash',
  'bad-source-hash',
  'bad-record-count',
  'bad-business-url',
  'bad-source-record-url',
  'impossible-date',
  'stale',
  'short',
  'relative-date',
];
for (const scenario of reviewScenarios) {
  phpTest(
    `review codec ${scenario}`,
    'review-codec-harness.php',
    [
      path.join(root, 'bootstrap.php'),
      path.join(root, 'tests/fixtures/reviews', `${scenario}.json`),
      scenario,
      '2026-07-15T00:00:00Z',
    ],
    scenario === 'valid' ? 'review-codec-ok' : 'review-codec-rejected',
  );
}

const deploymentScenarios = [
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
for (const scenario of deploymentScenarios) {
  phpTest(
    `private staging deploy ${scenario}`,
    'private-staging-deploy-harness.php',
    [path.join(root, 'tools/private-staging-deploy.php'), scenario],
    `${scenario}-ok`,
  );
}
