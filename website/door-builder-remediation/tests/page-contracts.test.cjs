const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

function load(id) {
  const file = path.resolve(__dirname, '..', 'page-contracts', 'page-' + id + '.json');
  return fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : {};
}

test('all page contracts are MAIN and blocked pending fresh export', () => {
  for (const id of [6065, 7073, 7129]) {
    const contract = load(id);
    assert.equal(contract.schemaVersion, 1);
    assert.equal(contract.site, 'MAIN');
    assert.equal(contract.pageId, id);
    assert.equal(contract.mutationStatus, 'blocked-pending-fresh-export');
  }
});

test('6065 requires one section, mount and visible heading', () => {
  const contract = load(6065);
  assert.deepEqual(contract.requiredCounts, {
    designYourDoorSection: 1,
    builderShortcode: 1,
    visibleHeading: 1
  });
  assert.equal(contract.productDeepLink, '?product=12');
});

test('7073 uses approved truthful copy and success-only redirect', () => {
  const contract = load(7073);
  assert.equal(contract.successRedirect, '/door-builder/');
  assert.match(contract.heroCopy, /specification/);
  assert.equal(contract.forbiddenClaims.includes('upload your home'), true);
  assert.equal(contract.redirectRequires.bodyOkTrue, true);
  assert.equal(contract.requiredErrorSelector, '[data-door-builder-error]');
  assert.equal(contract.requiredCounts.formSubmissionHandler, 1);
  assert.equal(contract.removeInlineOnsubmit, true);
  assert.equal(contract.removeLegacyGlobal, 'twxDbSubmit');
});

test('7129 has one H1, one mount and unique metadata', () => {
  const contract = load(7129);
  assert.equal(contract.h1, 'Design Your New Garage Door');
  assert.equal(contract.requiredCounts.h1, 1);
  assert.equal(contract.requiredCounts.builderShortcode, 1);
  assert.equal(contract.seoTitle, 'Build Your Garage Door Specification | Twins Garage Doors');
});
