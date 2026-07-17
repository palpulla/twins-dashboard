const assert = require('node:assert/strict');
const cp = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const nodeTool = path.join(root, 'tools/deploy-private-staging.mjs');
const phpTool = path.join(root, 'tools/private-staging-deploy.php');
const phpHarness = path.join(root, 'tests/php/private-staging-deploy-harness.php');
const phpHarnessRegistry = path.join(root, 'tests/php-harnesses.test.cjs');
const consumedReleaseRoot = '/home/customer/staging-safety/staging-unification-20260716';
const consumedCorrectiveRoot = '/home/customer/staging-safety/staging-corrective-fad4d35a-20260716';
const consumedHeaderGuardRoot = '/home/customer/staging-safety/staging-header-guard-20260717';
const releaseRoot = '/home/customer/staging-safety/staging-remediation-r3-20260717';

test('deployment CLI accepts only four fixed operations and no caller-selected target fields', () => {
  for (const invalid of ['--host=x', '--port=22', '--root=/tmp/x', '--manifest=x', '--expected-old=x', '--retry=2', '--deploy=x']) {
    const result = cp.spawnSync(process.execPath, [nodeTool, invalid], { encoding: 'utf8' });
    assert.equal(result.status, 2, `${invalid}: ${result.stdout}\n${result.stderr}`);
    const output = JSON.parse(result.stdout);
    assert.equal(output.status, 'INVALID_OPERATION');
    assert.equal(output.writeAuthority, false);
    assert.equal(output.productionWriteAuthority, false);
  }
});
test('deployment source fixes application identity, paths, safe transport, and one attempt', () => {
  const nodeSource = fs.readFileSync(nodeTool, 'utf8');
  const phpSource = fs.readFileSync(phpTool, 'utf8');
  const combined = `${nodeSource}\n${phpSource}`;
  assert.match(combined, /https:\/\/danielj140\.sg-host\.com\//);
  assert.match(combined, /\/home\/customer\/www\/danielj140\.sg-host\.com\/public_html/);
  assert.match(combined, /WP_ENVIRONMENT_TYPE/);
  assert.match(nodeSource, /TWINS_STAGE_SSH_TARGET/);
  assert.match(nodeSource, /TWINS_STAGE_SSH_KEY/);
  assert.match(nodeSource, /TWINS_STAGE_SSH_HOSTKEY_SHA256/);
  assert.match(nodeSource, /const SSH_PORT = '18765';/);
  assert.match(nodeSource, /ssh-keyscan', \['-p', SSH_PORT/);
  assert.match(nodeSource, /const sshOptions = \[\s*'-p', SSH_PORT,/);
  assert.match(nodeSource, /const scpOptions = \[\s*'-P', SSH_PORT,/);
  assert.doesNotMatch(nodeSource, /TWINS_STAGE_SSH_PORT/);
  assert.match(nodeSource, /shell:\s*false/);
  assert.equal(nodeSource.includes(releaseRoot), true);
  assert.equal(phpSource.includes(releaseRoot), true);
  assert.equal(nodeSource.includes(consumedReleaseRoot), false);
  assert.equal(phpSource.includes(consumedReleaseRoot), false);
  assert.equal(nodeSource.includes(consumedCorrectiveRoot), false);
  assert.equal(phpSource.includes(consumedCorrectiveRoot), false);
  assert.equal(nodeSource.includes(consumedHeaderGuardRoot), false);
  assert.equal(phpSource.includes(consumedHeaderGuardRoot), false);
  assert.doesNotMatch(combined, /brand-wide-20260715/);
  assert.match(nodeSource, /const stateParent = path\.join\(root, 'dist\/\.staging-deploy'\);/);
  assert.match(nodeSource, /path\.join\(stateParent, 'staging-remediation-r3-20260717'\)/);
  assert.match(nodeSource, /TRANSACTION_STATE_ALREADY_EXISTS/);
  assert.match(nodeSource, /assertLocalStateRoot/);
  assert.match(nodeSource, /readRegularText\(transportState/);
  assert.match(nodeSource, /HOST_KEY_STATE_DRIFT/);
  assert.match(nodeSource, /writeFileSync\(knownHosts,[\s\S]{0,160}flag:\s*'wx'/);
  assert.match(nodeSource, /const remoteRootGuard/);
  assert.match(nodeSource, /const remoteCommand = op => `\$\{remoteRootGuard\} && php/);
  assert.equal((nodeSource.match(/assertRemoteRoot\(\);/g) || []).length >= 3, true);
  assert.equal(nodeSource.includes("test -d '${TRANSACTION_PARENT}'"), true);
  assert.equal(nodeSource.includes("test ! -L '${TRANSACTION_PARENT}'"), true);
  assert.equal(nodeSource.includes("test ! -e '${TRANSACTION_ROOT}'"), true);
  assert.equal(nodeSource.includes("test ! -L '${TRANSACTION_ROOT}'"), true);
  assert.equal(nodeSource.includes("mkdir '${TRANSACTION_ROOT}'"), true);
  assert.equal(nodeSource.includes("mkdir -p '${TRANSACTION_ROOT}'"), false);
  assert.match(nodeSource, /DEPLOY_ATTEMPT_ALREADY_RECORDED/);
  assert.match(nodeSource, /flag:\s*'wx'/);
  assert.match(nodeSource, /REMOTE_RESULT_INVALID/);
  assert.match(nodeSource, /validateRemoteReport/);
  assert.match(nodeSource, /manifestSha256/);
  assert.match(nodeSource, /deployPackageSha256/);
  assert.match(nodeSource, /prerequisiteSetSha256/);
  assert.match(nodeSource, /trim\(\$?\w*stderr\)\s*!==\s*''|stderr\.trim\(\)\s*!==\s*''/);
  assert.match(phpSource, /DEPLOY_ATTEMPT_ALREADY_RECORDED/);
  assert.match(phpSource, /fopen\([^,]+,\s*'x'\)/);
  assert.doesNotMatch(combined, /twinsgaragedoors\.com|wp-config|ALTER\s+TABLE|UPDATE\s+wp_/i);
  assert.doesNotMatch(combined, /for\s*\([^)]*(?:retry|attempt)|while\s*\([^)]*(?:retry|attempt)/i);
});

test('subsequent release captures existing fixed targets and remains exact-CAS, closed, and rollback-safe', () => {
  const phpSource = fs.readFileSync(phpTool, 'utf8');
  const harnessSource = fs.readFileSync(phpHarness, 'utf8');
  const registrySource = fs.readFileSync(phpHarnessRegistry, 'utf8');
  assert.doesNotMatch(phpSource, /UNEXPECTED_EXISTING_CORE/);
  assert.match(phpSource, /\$names !== \['twins-brand-experience', 'twins-staging-overhaul'\]/);
  assert.match(phpSource, /CANDIDATE_EMPTY/);
  assert.match(phpSource, /CANDIDATE_NOT_CLOSED/);
  assert.match(phpSource, /EXPECTED_OLD_CONFLICT/);
  assert.equal(
    (phpSource.match(/\$this->assertExpectedOldCurrent\(\$snapshot\);/g) || []).length >= 2,
    true,
    'deploy must repeat the expected-old CAS immediately before mutation',
  );
  assert.match(phpSource, /\$mutationStarted\s*=\s*false/);
  assert.match(phpSource, /\$activationProgress/);
  assert.match(phpSource, /'unprocessed'/);
  assert.match(phpSource, /'backed-up'/);
  assert.match(phpSource, /'activated'/);
  assert.match(phpSource, /assertActivationEnvelope/);
  assert.match(phpSource, /after-backup/);
  assert.match(phpSource, /call_user_func\(\$this->activationProbe\);[\s\S]{0,120}\$this->assertCurrentMatchesCandidate\(\$manifest\);/);
  assert.match(phpSource, /\$this->assertCurrentMatchesCandidate\(\$manifest\);[\s\S]{0,160}\$this->restoreSnapshot\(\$snapshot\);/);
  assert.match(phpSource, /ROLLBACK_CONFLICT/);
  assert.match(phpSource, /ROLLBACK_ARCHIVE_DRIFT/);
  assert.match(phpSource, /ROLLBACK_VERIFY_FAILED/);
  assert.match(phpSource, /verifyCandidatePhp\(\)/);
  assert.match(phpSource, /twins-brand-experience\/bootstrap\.php/);
  assert.match(phpSource, /expected old core/);
  for (const scenario of [
    'existing-core-capture',
    'existing-core-install',
    'expected-old-drift',
    'late-expected-old-drift',
    'second-deploy-conflict',
    'rollback-existing-core',
    'rollback-drift',
    'incoming-copy-failure',
    'incoming-copy-drift',
    'partial-activation-failure',
    'activation-deletion-drift',
    'activation-success-drift',
    'activation-failure-drift',
    'empty-candidate',
    'candidate-not-closed',
    'target-set-invalid',
  ]) {
    assert.equal(harnessSource.includes(scenario) || phpSource.includes(scenario), true, `${scenario} harness branch missing`);
    assert.equal(registrySource.includes(`'${scenario}'`), true, `${scenario} is not registered`);
  }
});

test('remote dry-run lints the fixed deploy tooling and executes every fixed PHP harness scenario', () => {
  const phpSource = fs.readFileSync(phpTool, 'utf8');
  const harnessSource = fs.readFileSync(phpHarness, 'utf8');
  const registrySource = fs.readFileSync(phpHarnessRegistry, 'utf8');
  const scenarioBlock = phpSource.match(/private const HOST_VERIFICATION_SCENARIOS = \[([\s\S]*?)\];/);
  const harnessBlock = harnessSource.match(/\$allowed = \[([\s\S]*?)\];/);
  assert.notEqual(scenarioBlock, null);
  assert.notEqual(harnessBlock, null);
  assert.match(phpSource, /\$verificationRoot\s*=\s*\$this->transaction\s*\.\s*'\/verification';/);
  assert.match(phpSource, /\$brandRoot\s*=\s*\$verificationRoot\s*\.\s*'\/twins-brand-experience';/);
  assert.match(phpSource, /\$stagingRoot\s*=\s*\$verificationRoot\s*\.\s*'\/staging-safety';/);
  assert.match(phpSource, /\$tool\s*=\s*\$brandRoot\s*\.\s*'\/tools\/private-staging-deploy\.php';/);
  assert.match(phpSource, /\$harness\s*=\s*\$brandRoot\s*\.\s*'\/tests\/php\/private-staging-deploy-harness\.php';/);
  assert.match(phpSource, /\$operation === '--dry-run'[\s\S]{0,240}\$this->verifyHostTooling\(\)/);
  assert.match(phpSource, /foreach\s*\(\$this->listTree\(\$verificationRoot\)/);
  assert.match(phpSource, /PHP_BINARY,\s*'-l',\s*\$verificationRoot\s*\.\s*'\/'\s*\.\s*\$relative/);
  assert.match(phpSource, /function runPhpExact\(/);
  assert.match(phpSource, /trim\(\$stdout\)\s*!==\s*\$expected/);
  assert.match(phpSource, /function runPhpJson\(/);
  assert.match(phpSource, /function verifyBootstrapReport\(/);
  assert.match(phpSource, /function verifyWordPressReport\(/);
  assert.match(phpSource, /self::MAX_FILE_SIZE\s*\+\s*1/);
  assert.match(phpSource, /trim\(\$stderr\)\s*!==\s*''/);
  for (const scenario of [
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
  ]) {
    assert.equal(scenarioBlock[1].includes(`'${scenario}'`), true, `${scenario} is missing from remote dry-run`);
    assert.equal(harnessBlock[1].includes(`'${scenario}'`), true, `${scenario} is missing from remote harness`);
    assert.equal(registrySource.includes(`'${scenario}'`), true, `${scenario} is missing from local registry`);
  }
  for (const fixedHarness of [
    'portable-core-harness.php',
    'renderer-contract-harness.php',
    'review-codec-harness.php',
    'staging-overhaul-foundation-harness.php',
    'staging-overhaul-harness.php',
    'staging-overhaul-builder-harness.php',
    'staging-overhaul-cost-harness.php',
    'staging-overhaul-bootstrap-harness.php',
    'staging-overhaul-renderers-harness.php',
    'staging-overhaul-brand-asset-harness.php',
    'staging-brand-adapters-harness.php',
    'staging-legacy-image-srcset-harness.php',
    'staging-il-provision-harness.php',
    'staging-chrome-transition-harness.php',
    'wordpress-harness.php',
  ]) {
    assert.equal(phpSource.includes(fixedHarness), true, `${fixedHarness} is absent from remote dry-run`);
  }
  for (const fixedTool of ['staging-il-provision.php', 'staging-chrome-transition.php']) {
    assert.equal(phpSource.includes(fixedTool), true, `${fixedTool} is absent from remote dry-run`);
  }
  for (const scenario of [
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
    'missingEnvironment',
    'wrongEnvironment',
    'missingSafetyFlag',
    'falseSafetyFlag',
    'missingCronDisable',
    'falseCronDisable',
    'routes',
    'asset-versions',
    'hooks',
    'blog-index',
    'campaign',
    'family-once',
    'path-contact-context',
    'service-brand-chrome',
    'catalog-brand-chrome',
    'home-brand',
    'team-brand',
    'careers-brand',
    'reviews-brand',
    'contact-brand',
    'ineligible',
    'article',
    'unknown-blog',
    'elementor-theme-content',
    'elementor-document-content',
    'legacy-location-document',
  ]) {
    assert.equal(phpSource.includes(`'${scenario}'`), true, `${scenario} is absent from remote dry-run matrix`);
  }
});

test('missing transport secrets fails closed without revealing values', () => {
  const env = { ...process.env };
  delete env.TWINS_STAGE_SSH_TARGET;
  delete env.TWINS_STAGE_SSH_KEY;
  delete env.TWINS_STAGE_SSH_HOSTKEY_SHA256;
  const result = cp.spawnSync(process.execPath, [nodeTool, '--dry-run'], { encoding: 'utf8', env });
  assert.equal(result.status, 1, result.stderr);
  const output = JSON.parse(result.stdout);
  assert.equal(output.status, 'TRANSPORT_CONFIGURATION_REQUIRED');
  assert.equal(output.writeAuthority, false);
  assert.equal(output.productionWriteAuthority, false);
  assert.equal(result.stdout.includes('TWINS_STAGE_SSH_TARGET='), false);
});
