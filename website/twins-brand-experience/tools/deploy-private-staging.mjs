import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const APPLICATION_IDENTITY = 'https://danielj140.sg-host.com/';
const WEB_ROOT = '/home/customer/www/danielj140.sg-host.com/public_html';
const TRANSACTION_PARENT = '/home/customer/staging-safety';
const TRANSACTION_ROOT = '/home/customer/staging-safety/staging-remediation-r9-20260717';
const SSH_PORT = '18765';
const allowed = new Set(['--dry-run', '--capture-expected-old', '--deploy', '--rollback']);
const operation = process.argv[2] || '';
const baseResult = { writeAuthority: false, productionWriteAuthority: false, applicationIdentity: APPLICATION_IDENTITY };

function finish(status, exitCode = 0, extra = {}) {
  process.stdout.write(`${JSON.stringify({ status, ...baseResult, ...extra })}\n`);
  process.exit(exitCode);
}

if (process.argv.length !== 3 || !allowed.has(operation)) finish('INVALID_OPERATION', 2);

const target = process.env.TWINS_STAGE_SSH_TARGET;
const key = process.env.TWINS_STAGE_SSH_KEY;
const fingerprint = process.env.TWINS_STAGE_SSH_HOSTKEY_SHA256;
if (!target || !key || !fingerprint) finish('TRANSPORT_CONFIGURATION_REQUIRED', 1);
if (!/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+$/.test(target) || !path.isAbsolute(key) || !/^SHA256:[A-Za-z0-9+/=]{20,}$/.test(fingerprint)) {
  finish('TRANSPORT_CONFIGURATION_INVALID', 1);
}
let keyStat;
try { keyStat = fs.lstatSync(key); } catch { finish('TRANSPORT_KEY_UNAVAILABLE', 1); }
if (!keyStat.isFile() || keyStat.isSymbolicLink()) finish('TRANSPORT_KEY_INVALID', 1);

const stateParent = path.join(root, 'dist/.staging-deploy');
const stateRoot = path.join(stateParent, 'staging-remediation-r9-20260717');
const knownHosts = path.join(stateRoot, 'known_hosts');
const transportState = path.join(stateRoot, 'transport.json');
const deployAttempt = path.join(stateRoot, 'deploy-attempt.json');
const targetHash = crypto.createHash('sha256').update(target).digest('hex');
const host = target.slice(target.indexOf('@') + 1);

function entryExists(file) {
  try {
    fs.lstatSync(file);
    return true;
  } catch (error) {
    if (error && error.code === 'ENOENT') return false;
    finish('TRANSACTION_STATE_INSPECTION_FAILED', 1);
  }
}

function assertRealDirectory(file, failure) {
  let stat;
  try { stat = fs.lstatSync(file); } catch { finish(failure, 1); }
  if (!stat.isDirectory() || stat.isSymbolicLink()) finish(failure, 1);
}

function run(command, args, options = {}) {
  const result = spawnSync(command, args, {
    encoding: 'utf8',
    shell: false,
    timeout: options.timeout || 120000,
    input: options.input,
    maxBuffer: 1024 * 1024,
  });
  const stdout = typeof result.stdout === 'string' ? result.stdout : '';
  const stderr = typeof result.stderr === 'string' ? result.stderr : '';
  const keyscanCommentsOnly = options.stderrPolicy === 'keyscan-comments' &&
    stderr.trim().split(/\r?\n/).every(line => line.startsWith('# '));
  if (result.error || result.signal || result.status !== 0 ||
      (stderr.trim() !== '' && !keyscanCommentsOnly)) {
    finish(options.failure || 'TRANSPORT_OPERATION_FAILED', 1);
  }
  return stdout;
}

function readRegularText(file, failure) {
  let stat;
  try { stat = fs.lstatSync(file); } catch { finish(failure, 1); }
  if (!stat.isFile() || stat.isSymbolicLink() || stat.size < 1 || stat.size > 1024 * 1024) finish(failure, 1);
  try { return fs.readFileSync(file, 'utf8'); } catch { finish(failure, 1); }
}

function assertLocalStateRoot() {
  assertRealDirectory(path.join(root, 'dist'), 'TRANSACTION_STATE_ROOT_INVALID');
  assertRealDirectory(stateParent, 'TRANSACTION_STATE_ROOT_INVALID');
  assertRealDirectory(stateRoot, 'TRANSACTION_STATE_ROOT_INVALID');
  readRegularText(knownHosts, 'TRANSPORT_STATE_INVALID');
  readRegularText(transportState, 'TRANSPORT_STATE_INVALID');
}

if (operation === '--dry-run') {
  if (entryExists(stateRoot)) finish('TRANSACTION_STATE_ALREADY_EXISTS', 1);
  assertRealDirectory(path.join(root, 'dist'), 'TRANSACTION_STATE_ROOT_INVALID');
  if (entryExists(stateParent)) {
    assertRealDirectory(stateParent, 'TRANSACTION_STATE_ROOT_INVALID');
  } else {
    try { fs.mkdirSync(stateParent, { mode: 0o700 }); } catch { finish('TRANSACTION_STATE_ROOT_INVALID', 1); }
    assertRealDirectory(stateParent, 'TRANSACTION_STATE_ROOT_INVALID');
  }
} else {
  assertLocalStateRoot();
}

function packageIdentity() {
  let metadata;
  const metadataBytes = readRegularText(
    path.join(root, 'dist/staging-runtime/package-metadata.json'),
    'PACKAGE_IDENTITY_INVALID',
  );
  const manifestPath = path.join(root, 'dist/staging-runtime/staging-runtime.json');
  const manifestBytes = readRegularText(manifestPath, 'PACKAGE_IDENTITY_INVALID');
  try { metadata = JSON.parse(metadataBytes); } catch { finish('PACKAGE_IDENTITY_INVALID', 1); }
  const hashes = ['deployPackageSha256', 'prerequisiteSetSha256', 'hostVerificationSha256'];
  if (!metadata || Array.isArray(metadata) || metadata.schemaVersion !== 1 ||
      metadata.productionWriteAuthority !== false ||
      !hashes.every(name => typeof metadata[name] === 'string' && /^[a-f0-9]{64}$/.test(metadata[name]))) {
    finish('PACKAGE_IDENTITY_INVALID', 1);
  }
  return {
    manifestSha256: crypto.createHash('sha256').update(manifestBytes).digest('hex'),
    deployPackageSha256: metadata.deployPackageSha256,
    prerequisiteSetSha256: metadata.prerequisiteSetSha256,
    hostVerificationSha256: metadata.hostVerificationSha256,
  };
}

function validateRemoteReport(stdout, expectedStatus, expectedOperation, identity) {
  let report;
  try { report = JSON.parse(stdout.trim()); } catch { finish('REMOTE_RESULT_INVALID', 1); }
  const expected = {
    status: expectedStatus,
    operation: expectedOperation,
    applicationIdentity: APPLICATION_IDENTITY,
    environment: 'staging',
    manifestSha256: identity.manifestSha256,
    deployPackageSha256: identity.deployPackageSha256,
    prerequisiteSetSha256: identity.prerequisiteSetSha256,
    writeAuthority: false,
    productionWriteAuthority: false,
  };
  if (!report || Array.isArray(report) ||
      JSON.stringify(Object.keys(report).sort()) !== JSON.stringify(Object.keys(expected).sort()) ||
      Object.entries(expected).some(([name, value]) => report[name] !== value)) {
    finish('REMOTE_RESULT_INVALID', 1);
  }
  return report;
}

function verifyHostKey() {
  const scanned = run('ssh-keyscan', ['-p', SSH_PORT, '-T', '8', host], {
    failure: 'HOST_KEY_SCAN_FAILED',
    timeout: 15000,
    stderrPolicy: 'keyscan-comments',
  });
  const lines = scanned.split(/\r?\n/).filter(line => line && !line.startsWith('#'));
  const matching = [];
  for (const line of lines) {
    const detail = run('ssh-keygen', ['-lf', '-', '-E', 'sha256'], { input: `${line}\n`, failure: 'HOST_KEY_INSPECTION_FAILED' });
    if (detail.split(/\s+/).includes(fingerprint)) matching.push(line);
  }
  if (matching.length !== 1) finish('HOST_KEY_FINGERPRINT_MISMATCH', 1);
  const knownHostBytes = `${matching[0]}\n`;
  if (operation === '--dry-run') {
    try { fs.mkdirSync(stateRoot, { mode: 0o700 }); } catch { finish('TRANSACTION_STATE_ROOT_INVALID', 1); }
    assertRealDirectory(stateRoot, 'TRANSACTION_STATE_ROOT_INVALID');
    try { fs.writeFileSync(knownHosts, knownHostBytes, { mode: 0o600, flag: 'wx' }); } catch { finish('HOST_KEY_STATE_WRITE_FAILED', 1); }
  } else {
    assertLocalStateRoot();
    if (readRegularText(knownHosts, 'TRANSPORT_STATE_INVALID') !== knownHostBytes) finish('HOST_KEY_STATE_DRIFT', 1);
  }
}

run(process.execPath, [path.join(root, 'tools/build-packages.mjs'), '--check'], {
  failure: 'PACKAGE_CHECK_FAILED',
  timeout: 180000,
});
const identity = packageIdentity();
verifyHostKey();
if (operation === '--dry-run') {
  if (entryExists(transportState)) finish('TRANSPORT_STATE_ALREADY_EXISTS', 1);
} else {
  let state;
  try { state = JSON.parse(readRegularText(transportState, 'TRANSPORT_DRY_RUN_REQUIRED')); } catch { finish('TRANSPORT_DRY_RUN_REQUIRED', 1); }
  if (state.targetSha256 !== targetHash || state.hostKeySha256 !== fingerprint || state.sshPort !== SSH_PORT ||
      state.manifestSha256 !== identity.manifestSha256 ||
      state.deployPackageSha256 !== identity.deployPackageSha256 ||
      state.prerequisiteSetSha256 !== identity.prerequisiteSetSha256 ||
      state.hostVerificationSha256 !== identity.hostVerificationSha256) {
    finish('TRANSPORT_IDENTITY_DRIFT', 1);
  }
}

if (operation === '--deploy') {
  assertLocalStateRoot();
  try {
    fs.writeFileSync(deployAttempt, `${JSON.stringify({
      schemaVersion: 1,
      transactionRoot: TRANSACTION_ROOT,
      targetSha256: targetHash,
      hostKeySha256: fingerprint,
      ...identity,
      writeAuthority: false,
      productionWriteAuthority: false,
    }, null, 2)}\n`, { mode: 0o600, flag: 'wx' });
  } catch (error) {
    finish(error && error.code === 'EEXIST' ? 'DEPLOY_ATTEMPT_ALREADY_RECORDED' : 'DEPLOY_ATTEMPT_RECORD_FAILED', 1);
  }
}

const transportOptions = [
  '-i', key,
  '-o', 'BatchMode=yes',
  '-o', 'IdentitiesOnly=yes',
  '-o', 'StrictHostKeyChecking=yes',
  '-o', `UserKnownHostsFile=${knownHosts}`,
  '-o', 'ConnectTimeout=15',
];
const sshOptions = [
  '-p', SSH_PORT,
  ...transportOptions,
];
const scpOptions = [
  '-P', SSH_PORT,
  ...transportOptions,
];
const remoteRootGuard = `test -d '${TRANSACTION_PARENT}' && test ! -L '${TRANSACTION_PARENT}' && test -d '${TRANSACTION_ROOT}' && test ! -L '${TRANSACTION_ROOT}'`;
const assertRemoteRoot = () => run('ssh', [...sshOptions, target, remoteRootGuard], { failure: 'REMOTE_TRANSACTION_ROOT_INVALID' });
const remoteScript = `${TRANSACTION_ROOT}/verification/twins-brand-experience/tools/private-staging-deploy.php`;
const remoteCommand = op => `${remoteRootGuard} && php '${remoteScript}' '${op}'`;

if (operation === '--dry-run') {
  run('ssh', [...sshOptions, target, `test -d '${TRANSACTION_PARENT}' && test ! -L '${TRANSACTION_PARENT}' && test ! -e '${TRANSACTION_ROOT}' && test ! -L '${TRANSACTION_ROOT}' && mkdir '${TRANSACTION_ROOT}' && chmod 700 '${TRANSACTION_ROOT}'`], { failure: 'REMOTE_TRANSACTION_ALREADY_EXISTS_OR_PREFLIGHT_FAILED' });
  assertRemoteRoot();
  run('scp', [...scpOptions, '-r', path.join(root, 'dist/host-verification'), `${target}:${TRANSACTION_ROOT}/verification.incoming`], { failure: 'VERIFICATION_UPLOAD_FAILED' });
  assertRemoteRoot();
  const stdout = run('ssh', [...sshOptions, target, `${remoteRootGuard} && test -d '${TRANSACTION_ROOT}/verification.incoming' && test ! -L '${TRANSACTION_ROOT}/verification.incoming' && rm -rf '${TRANSACTION_ROOT}/verification' && mv '${TRANSACTION_ROOT}/verification.incoming' '${TRANSACTION_ROOT}/verification' && ${remoteCommand(operation)}`], { failure: 'REMOTE_DRY_RUN_FAILED' });
  const report = validateRemoteReport(stdout, 'PRIVATE_STAGING_DRY_RUN_PASSED', operation, identity);
  fs.writeFileSync(transportState, `${JSON.stringify({
    targetSha256: targetHash,
    hostKeySha256: fingerprint,
    sshPort: SSH_PORT,
    ...identity,
  }, null, 2)}\n`, { mode: 0o600, flag: 'wx' });
  finish('PRIVATE_STAGING_DRY_RUN_PASSED', 0, report);
}

if (operation === '--deploy') {
  assertRemoteRoot();
  run('ssh', [...sshOptions, target, `${remoteRootGuard} && rm -rf '${TRANSACTION_ROOT}/candidate.incoming' '${TRANSACTION_ROOT}/verification.incoming'`], { failure: 'REMOTE_UPLOAD_PREP_FAILED' });
  assertRemoteRoot();
  run('scp', [...scpOptions, '-r', path.join(root, 'dist/staging-runtime'), `${target}:${TRANSACTION_ROOT}/candidate.incoming`], { failure: 'CANDIDATE_UPLOAD_FAILED' });
  assertRemoteRoot();
  run('scp', [...scpOptions, '-r', path.join(root, 'dist/host-verification'), `${target}:${TRANSACTION_ROOT}/verification.incoming`], { failure: 'VERIFICATION_UPLOAD_FAILED' });
  const stdout = run('ssh', [...sshOptions, target, `${remoteRootGuard} && test -d '${TRANSACTION_ROOT}/candidate.incoming' && test ! -L '${TRANSACTION_ROOT}/candidate.incoming' && test -d '${TRANSACTION_ROOT}/verification.incoming' && test ! -L '${TRANSACTION_ROOT}/verification.incoming' && rm -rf '${TRANSACTION_ROOT}/candidate' '${TRANSACTION_ROOT}/verification' && mv '${TRANSACTION_ROOT}/candidate.incoming' '${TRANSACTION_ROOT}/candidate' && mv '${TRANSACTION_ROOT}/verification.incoming' '${TRANSACTION_ROOT}/verification' && ${remoteCommand(operation)}`], { failure: 'REMOTE_DEPLOY_FAILED' });
  const report = validateRemoteReport(stdout, 'PRIVATE_STAGING_DEPLOYED', operation, identity);
  finish('PRIVATE_STAGING_DEPLOYED', 0, report);
}

assertRemoteRoot();
const stdout = run('ssh', [...sshOptions, target, remoteCommand(operation)], {
  failure: operation === '--rollback' ? 'REMOTE_ROLLBACK_FAILED' : 'EXPECTED_OLD_CAPTURE_FAILED',
});
const finalStatus = operation === '--rollback' ? 'PRIVATE_STAGING_ROLLED_BACK' : 'EXPECTED_OLD_CAPTURED';
const report = validateRemoteReport(stdout, finalStatus, operation, identity);
finish(finalStatus, 0, report);
