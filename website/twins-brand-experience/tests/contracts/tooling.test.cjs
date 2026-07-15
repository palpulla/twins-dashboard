const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');

test('brand tooling is pinned and side-effecting commands are explicit', () => {
  const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
  assert.equal(pkg.private, true);
  assert.equal(pkg.engines.node, '>=20');
  assert.equal(pkg.devDependencies['@playwright/test'], '1.61.1');
  assert.equal(pkg.devDependencies.sharp, '0.34.5');
  assert.equal(pkg.scripts['test:contracts'], 'node --test tests/contracts/*.test.cjs');
  assert.equal(pkg.scripts['check:assets'], 'node tools/build-owned-images.mjs --check');
  assert.equal(pkg.scripts['check:repo'], 'node tools/check-repository.mjs');
  assert.equal(pkg.scripts['install:browser'], 'playwright install chromium');
  assert.equal(pkg.scripts['test:browser'], 'playwright test');
  assert.equal(pkg.scripts['test:crawl'], 'node tools/crawl-staging.mjs');
});
