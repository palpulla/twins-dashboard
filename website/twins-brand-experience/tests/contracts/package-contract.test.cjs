const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const website = path.resolve(root, '..');

function verifyEntry(entry, destinationRequired) {
  assert.match(entry.role, /^(deploy|verify-prerequisite|verify)$/);
  assert.match(entry.source, /^(twins-brand-experience|staging-safety)\//);
  assert.doesNotMatch(entry.source, /(^|\/)\.\.?(\/|$)/);
  assert.equal(Number.isSafeInteger(entry.size) && entry.size >= 0, true);
  assert.match(entry.sha256, /^[a-f0-9]{64}$/);
  if (destinationRequired) {
    assert.equal(typeof entry.destination, 'string');
    assert.doesNotMatch(entry.destination, /(^|\/)\.\.?(\/|$)/);
  } else {
    assert.equal(Object.hasOwn(entry, 'destination'), false);
  }
  const bytes = fs.readFileSync(path.resolve(website, entry.source));
  assert.equal(bytes.length, entry.size);
  assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), entry.sha256);
}

test('staging manifest is closed, hash-pinned, and has no production payload', () => {
  const manifest = JSON.parse(fs.readFileSync(path.join(root, 'manifests/staging-runtime.json'), 'utf8'));
  assert.equal(manifest.schemaVersion, 1);
  assert.equal(manifest.applicationIdentity, 'https://danielj140.sg-host.com/');
  assert.equal(manifest.environment, 'staging');
  assert.equal(manifest.productionWriteAuthority, false);
  assert.equal(fs.existsSync(path.join(root, 'manifests/production-runtime.json')), false,
    'staging-only release must not create or weaken a production manifest');
  manifest.files.forEach(entry => verifyEntry(entry, true));
  assert.deepEqual(manifest.files.map(entry => entry.destination),
    [...manifest.files.map(entry => entry.destination)].sort((a, b) => Buffer.from(a).compare(Buffer.from(b))));

  const deploy = new Set(manifest.files.filter(file => file.role === 'deploy').map(file => file.destination));
  const prerequisites = new Set(manifest.files.filter(file => file.role === 'verify-prerequisite').map(file => file.destination));
  assert.equal(deploy.has('twins-brand-experience/bootstrap.php'), true);
  assert.equal(deploy.has('twins-brand-experience/assets/css/twins-brand-families.css'), true);
  assert.equal(deploy.has('twins-brand-experience/assets/js/twins-builder.js'), true);
  assert.equal(deploy.has('twins-brand-experience/assets/images/door-builder/twins-before-after-install.webp'), true);
  assert.equal(deploy.has('twins-brand-experience/templates/catalog.php'), true);
  assert.equal(deploy.has('twins-staging-overhaul/brand-runtime.php'), true);
  assert.equal(deploy.has('twins-staging-overhaul/adapters/BrandStagingAdapters.php'), true);
  assert.equal(deploy.has('twins-staging-overhaul/adapters/BrandStagingPreviews.php'), true);
  assert.equal(deploy.has('twins-staging-overhaul.php'), false);
  assert.equal(prerequisites.has('twins-staging-overhaul.php'), true);
  assert.equal(prerequisites.has('twins-staging-safety.php'), true);
  assert.equal([...prerequisites].some(file => file.startsWith('twins-staging-assets/')), true);
  assert.equal([...prerequisites].some(file => file.endsWith('.md')), false,
    'documentation-only files are not live runtime prerequisites');
  assert.equal(manifest.files.some(file => /(^|\/)production(\/|$)|\/tests\//.test(file.source)), false);
  assert.equal(manifest.files.some(file => /twins-before-after-install-source\.png$/.test(file.source)), false);
});
test('host verification manifest is separate, closed, and non-deployable', () => {
  const manifest = JSON.parse(fs.readFileSync(path.join(root, 'manifests/host-verification.json'), 'utf8'));
  assert.equal(manifest.schemaVersion, 1);
  assert.equal(manifest.productionWriteAuthority, false);
  assert.equal(manifest.remoteDirectory, '/home/customer/staging-safety/staging-remediation-r18-20260717/verification/');
  assert.equal(manifest.files.some(file => file.source.endsWith('private-staging-deploy-harness.php')), true);
  assert.equal(manifest.files.some(file => file.source.endsWith('private-staging-deploy.php')), true);
  for (const required of [
    'staging-safety/tools/staging-il-provision.php',
    'staging-safety/tools/staging-chrome-transition.php',
    'staging-safety/mu-plugins/twins-staging-assets/clopay-products.json',
    'staging-safety/tests/staging-il-provision-harness.php',
    'staging-safety/tests/staging-chrome-transition-harness.php',
    'staging-safety/tests/wordpress-harness.php',
    'twins-brand-experience/assets/css/twins-brand.css',
    'twins-brand-experience/assets/css/twins-brand-families.css',
    'twins-brand-experience/assets/js/twins-brand.js',
    'twins-brand-experience/assets/js/twins-builder.js',
    'twins-brand-experience/tests/php/portable-core-harness.php',
    'twins-brand-experience/tests/php/renderer-contract-harness.php',
    'twins-brand-experience/tests/php/review-codec-harness.php',
  ]) {
    assert.equal(manifest.files.some(file => file.source === required), true, `${required} is missing from host verification`);
  }
  manifest.files.forEach(entry => verifyEntry(entry, false));
});
