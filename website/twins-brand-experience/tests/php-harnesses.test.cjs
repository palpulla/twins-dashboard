const assert = require('node:assert/strict');
const cp = require('node:child_process');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const php = cp.spawnSync('php', ['-v'], { encoding: 'utf8' });
const hasPhp = php.status === 0;
if (process.env.CI && !hasPhp) throw new Error('PHP is mandatory in CI');

function phpTest(name, harness, args = []) {
  test(name, { skip: !hasPhp && 'PHP CLI unavailable locally' }, () => {
    const result = cp.spawnSync('php', [path.join(root, 'tests/php', harness), ...args], { encoding: 'utf8' });
    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
  });
}

phpTest('portable core boots without WordPress', 'portable-core-harness.php', [path.join(root, 'bootstrap.php')]);
