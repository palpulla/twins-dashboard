import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const gates = ['legacy-node', 'brand-contracts', 'brand-php', 'owned-assets', 'staging-runtime-package'];
const envelope = {
  writeAuthority: false,
  productionWriteAuthority: false,
  gates,
  deferred: ['production-runtime-package'],
};

if (process.argv.length === 3 && process.argv[2] === '--self-test') {
  process.stdout.write(`${JSON.stringify({ status: 'REPOSITORY_CHECK_SELF_TEST_PASSED', ...envelope })}\n`);
  process.exit(0);
}
if (process.argv.length !== 2) {
  process.stdout.write(`${JSON.stringify({ status: 'INVALID_ARGUMENT', ...envelope })}\n`);
  process.exit(2);
}

const npm = process.platform === 'win32' ? 'npm.cmd' : 'npm';
const legacyRoot = path.resolve(root, '../staging-safety/tests');
const legacyTests = fs.readdirSync(legacyRoot)
  .filter(name => name.endsWith('.test.cjs'))
  .sort()
  .map(name => path.join(legacyRoot, name));
const commands = [
  ['legacy-node', process.execPath, ['--test', ...legacyTests]],
  ['brand-contracts', npm, ['run', 'test:contracts']],
  ['brand-php', npm, ['run', 'test:php']],
  ['owned-assets', npm, ['run', 'check:assets']],
  ['staging-runtime-package', npm, ['run', 'check:packages']],
];

for (const [gate, command, args] of commands) {
  const result = spawnSync(command, args, { cwd: root, encoding: 'utf8', shell: false, timeout: 180000 });
  if (result.status !== 0) {
    const bounded = `${result.stdout || ''}\n${result.stderr || ''}`.slice(-12000);
    process.stdout.write(`${JSON.stringify({ status: 'REPOSITORY_CHECK_FAILED', failedGate: gate, output: bounded, ...envelope })}\n`);
    process.exit(1);
  }
}

process.stdout.write(`${JSON.stringify({ status: 'REPOSITORY_CHECK_PASSED', ...envelope })}\n`);
