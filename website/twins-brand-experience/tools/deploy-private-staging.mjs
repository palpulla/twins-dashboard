import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const APPLICATION_IDENTITY = 'https://danielj140.sg-host.com/';
const WEB_ROOT = '/home/customer/www/danielj140.sg-host.com/public_html';
const TRANSACTION_ROOT = '/home/customer/staging-safety/brand-wide-20260715';
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

const stateRoot = path.join(root, 'dist/.staging-deploy');
const knownHosts = path.join(stateRoot, 'known_hosts');
const transportState = path.join(stateRoot, 'transport.json');
const targetHash = crypto.createHash('sha256').update(target).digest('hex');
const host = target.slice(target.indexOf('@') + 1);

function run(command, args, options = {}) {
  const result = spawnSync(command, args, {
    encoding: 'utf8',
    shell: false,
    timeout: options.timeout || 120000,
    input: options.input,
    maxBuffer: 1024 * 1024,
  });
  if (result.status !== 0) finish(options.failure || 'TRANSPORT_OPERATION_FAILED', 1);
  return result.stdout || '';
}

function verifyHostKey() {
  const scanned = run('ssh-keyscan', ['-T', '8', host], { failure: 'HOST_KEY_SCAN_FAILED', timeout: 15000 });
  const lines = scanned.split(/\r?\n/).filter(line => line && !line.startsWith('#'));
  const matching = [];
  for (const line of lines) {
    const detail = run('ssh-keygen', ['-lf', '-', '-E', 'sha256'], { input: `${line}\n`, failure: 'HOST_KEY_INSPECTION_FAILED' });
    if (detail.split(/\s+/).includes(fingerprint)) matching.push(line);
  }
  if (matching.length !== 1) finish('HOST_KEY_FINGERPRINT_MISMATCH', 1);
  fs.mkdirSync(stateRoot, { recursive: true, mode: 0o700 });
  fs.writeFileSync(knownHosts, `${matching[0]}\n`, { mode: 0o600 });
}

verifyHostKey();
if (operation === '--dry-run') {
  if (fs.existsSync(transportState)) finish('TRANSPORT_STATE_ALREADY_EXISTS', 1);
} else {
  let state;
  try { state = JSON.parse(fs.readFileSync(transportState, 'utf8')); } catch { finish('TRANSPORT_DRY_RUN_REQUIRED', 1); }
  if (state.targetSha256 !== targetHash || state.hostKeySha256 !== fingerprint) finish('TRANSPORT_IDENTITY_DRIFT', 1);
}

run(process.execPath, [path.join(root, 'tools/build-packages.mjs'), '--check'], { failure: 'PACKAGE_CHECK_FAILED', timeout: 180000 });

const sshOptions = [
  '-i', key,
  '-o', 'BatchMode=yes',
  '-o', 'IdentitiesOnly=yes',
  '-o', 'StrictHostKeyChecking=yes',
  '-o', `UserKnownHostsFile=${knownHosts}`,
  '-o', 'ConnectTimeout=15',
];
const remoteScript = `${TRANSACTION_ROOT}/verification/twins-brand-experience/tools/private-staging-deploy.php`;
const remoteCommand = op => `php '${remoteScript}' '${op}'`;

if (operation === '--dry-run') {
  run('ssh', [...sshOptions, target, `mkdir -p '${TRANSACTION_ROOT}' && chmod 700 '${TRANSACTION_ROOT}' && rm -rf '${TRANSACTION_ROOT}/verification.incoming'`], { failure: 'REMOTE_PREFLIGHT_FAILED' });
  run('scp', [...sshOptions, '-r', path.join(root, 'dist/host-verification'), `${target}:${TRANSACTION_ROOT}/verification.incoming`], { failure: 'VERIFICATION_UPLOAD_FAILED' });
  run('ssh', [...sshOptions, target, `rm -rf '${TRANSACTION_ROOT}/verification' && mv '${TRANSACTION_ROOT}/verification.incoming' '${TRANSACTION_ROOT}/verification' && ${remoteCommand(operation)}`], { failure: 'REMOTE_DRY_RUN_FAILED' });
  fs.writeFileSync(transportState, `${JSON.stringify({ targetSha256: targetHash, hostKeySha256: fingerprint }, null, 2)}\n`, { mode: 0o600 });
  finish('PRIVATE_STAGING_DRY_RUN_PASSED');
}

if (operation === '--deploy') {
  run('ssh', [...sshOptions, target, `rm -rf '${TRANSACTION_ROOT}/candidate.incoming' '${TRANSACTION_ROOT}/verification.incoming'`], { failure: 'REMOTE_UPLOAD_PREP_FAILED' });
  run('scp', [...sshOptions, '-r', path.join(root, 'dist/staging-runtime'), `${target}:${TRANSACTION_ROOT}/candidate.incoming`], { failure: 'CANDIDATE_UPLOAD_FAILED' });
  run('scp', [...sshOptions, '-r', path.join(root, 'dist/host-verification'), `${target}:${TRANSACTION_ROOT}/verification.incoming`], { failure: 'VERIFICATION_UPLOAD_FAILED' });
  run('ssh', [...sshOptions, target, `rm -rf '${TRANSACTION_ROOT}/candidate' '${TRANSACTION_ROOT}/verification' && mv '${TRANSACTION_ROOT}/candidate.incoming' '${TRANSACTION_ROOT}/candidate' && mv '${TRANSACTION_ROOT}/verification.incoming' '${TRANSACTION_ROOT}/verification' && ${remoteCommand(operation)}`], { failure: 'REMOTE_DEPLOY_FAILED' });
  finish('PRIVATE_STAGING_DEPLOYED');
}

run('ssh', [...sshOptions, target, remoteCommand(operation)], {
  failure: operation === '--rollback' ? 'REMOTE_ROLLBACK_FAILED' : 'EXPECTED_OLD_CAPTURE_FAILED',
});
finish(operation === '--rollback' ? 'PRIVATE_STAGING_ROLLED_BACK' : 'EXPECTED_OLD_CAPTURED');
