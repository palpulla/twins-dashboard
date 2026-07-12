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
  assert.equal(contract.requiredHeading, 'Design Your Gallery Steel Door');
  assert.equal(contract.productDeepLink, '?product=12');
  assert.deepEqual(contract.removalCandidate, {
    elementorSectionId: 'e26273a',
    requiresExpectedOldExport: true
  });
  assert.deepEqual(contract.preservedBuilderCandidate, {
    elementorSectionId: '78da141',
    presumeReplaceable: false
  });
});

test('7073 uses approved truthful copy and success-only redirect', () => {
  const contract = load(7073);
  assert.equal(
    contract.heroCopy,
    'Choose your collection and options, then send the specification to Twins for a free quote.'
  );
  assert.equal(
    contract.supportingCopy,
    'Manufacturer photos and samples help you compare choices. Twins will confirm the exact appearance before ordering.'
  );
  assert.equal(contract.successRedirect, '/door-builder/');
  assert.equal(contract.requiredErrorSelector, '[data-door-builder-error]');
  assert.deepEqual(contract.requiredCounts, {
    formSubmissionHandler: 1
  });
  assert.equal(contract.removeInlineOnsubmit, true);
  assert.equal(contract.removeLegacyGlobal, 'twxDbSubmit');
  assert.deepEqual(contract.redirectRequires, {
    httpOk: true,
    bodyOkTrue: true
  });
  assert.deepEqual(contract.forbiddenClaims, [
    'upload your home',
    'try it on your house',
    'every option live',
    'exact render'
  ]);
});

test('7129 has one H1, one mount and unique metadata', () => {
  const contract = load(7129);
  assert.equal(contract.h1, 'Design Your New Garage Door');
  assert.deepEqual(contract.requiredCounts, {
    h1: 1,
    builderShortcode: 1,
    separateLeadGate: 0
  });
  assert.equal(contract.seoTitle, 'Build Your Garage Door Specification | Twins Garage Doors');
  assert.equal(
    contract.metaDescription,
    'Compare Clopay collections, panel styles, colors, windows and glass, then send your garage door specification to Twins for a free quote.'
  );
});
