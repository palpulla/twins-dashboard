const assert = require('node:assert/strict');
const cp = require('node:child_process');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');

test('staging-only checker declares all deploy gates without granting write authority', () => {
  const result = cp.spawnSync(process.execPath, ['tools/check-repository.mjs', '--self-test'], {
    cwd: root,
    encoding: 'utf8',
  });
  assert.equal(result.status, 0, result.stderr);
  const output = JSON.parse(result.stdout);
  assert.equal(output.status, 'REPOSITORY_CHECK_SELF_TEST_PASSED');
  assert.equal(output.writeAuthority, false);
  assert.equal(output.productionWriteAuthority, false);
  assert.deepEqual(output.gates, ['legacy-node', 'brand-contracts', 'brand-php', 'owned-assets', 'staging-runtime-package']);
  assert.equal(output.deferred.includes('production-runtime-package'), true);
});
