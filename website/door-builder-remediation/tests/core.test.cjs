const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

let core = {};
try {
  core = require('../src/core.js');
} catch (_error) {
  core = {};
}

const fixturePath = path.resolve(
  __dirname,
  '../../../docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot/product-12.json'
);
const gallerySteel = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
const classicSteel = JSON.parse(fs.readFileSync(
  path.join(path.dirname(fixturePath), 'product-13.json'),
  'utf8'
));
const catalogPath = path.resolve(
  __dirname,
  '../../../docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot/products-list.json'
);
const catalogFixture = JSON.parse(fs.readFileSync(catalogPath, 'utf8'));
const fixtureDirectory = path.dirname(fixturePath);
const detailFixtures = fs.readdirSync(fixtureDirectory)
  .filter((name) => /^product-[0-9]+\.json$/.test(name))
  .sort()
  .map((name) => JSON.parse(fs.readFileSync(path.join(fixtureDirectory, name), 'utf8')));

test('normalizes all 23 frozen catalog records', () => {
  assert.equal(typeof core.normalizeCatalog, 'function');
  const catalog = core.normalizeCatalog(catalogFixture);
  assert.equal(catalog.length, 23);
  assert.equal(catalog.some((product) => product.id === '12'), true);
  assert.equal(catalog.every((product) => product.title && product.id), true);
});

test('has one valid detail fixture for every catalog product', () => {
  assert.equal(typeof core.normalizeCatalog, 'function');
  assert.equal(typeof core.normalizeProduct, 'function');
  const catalogIds = core.normalizeCatalog(catalogFixture).map((item) => item.id).sort();
  const detailProducts = detailFixtures.map(core.normalizeProduct);
  const detailIds = detailProducts.map((item) => item.id).sort();
  assert.deepEqual(detailIds, catalogIds);
  assert.equal(detailProducts.length, 23);
  assert.equal(detailProducts.every((item) => item.id && item.title), true);
});

test('normalizes the frozen Gallery Steel option counts', () => {
  assert.equal(typeof core.normalizeProduct, 'function');
  const product = core.normalizeProduct(gallerySteel);
  assert.equal(product.id, '12');
  assert.equal(product.designs.length, 2);
  assert.equal(product.colors.length, 19);
  assert.equal(product.windows.length, 20);
  assert.equal(product.glass.length, 6);
  assert.equal(product.referencePhotos.length, 9);
});

test('preserves word boundaries in the frozen Classic Steel design names', () => {
  const product = core.normalizeProduct(classicSteel);
  assert.deepEqual(product.designs.map((design) => design.title), [
    'Elegant Short 3-layer doors',
    'Elegant Long 3-layer doors',
    'Traditional Short Panel 2 and 1-layer doors',
    'Traditional Long Panel 2 and 1-layer doors'
  ]);
});

test('sanitizes display labels and strips all lead-payload markup', () => {
  assert.equal(typeof core.sanitizeDisplay, 'function');
  assert.equal(
    core.sanitizeDisplay('<img src=x onerror=bad>Gallery<sup class=x>®</sup><br class=x>Steel'),
    'Gallery<sup>®</sup><br>Steel'
  );
  assert.equal(core.plainText('Gallery<sup>®</sup>&nbsp;Steel'), 'Gallery® Steel');
});

test('removes malicious closing br tags from display labels', () => {
  assert.equal(
    core.sanitizeDisplay('First</br onmouseover=bad>Second'),
    'FirstSecond'
  );
});

test('normalizes malicious closing sup tags in display labels', () => {
  assert.equal(
    core.sanitizeDisplay('Gallery<sup class=x>®</sup onmouseover=bad> Steel'),
    'Gallery<sup>®</sup> Steel'
  );
});

test('allows only HTTPS images on the Clopay public host', () => {
  assert.equal(typeof core.validateImageUrl, 'function');
  assert.match(
    core.validateImageUrl('https://www.clopaydoor.com/images/door.webp'),
    /^https:\/\/www\.clopaydoor\.com\//
  );
  assert.equal(core.validateImageUrl('http://www.clopaydoor.com/images/door.webp'), null);
  assert.equal(core.validateImageUrl('https://ezdoor.clopay.com/private.webp'), null);
  assert.equal(core.validateImageUrl('javascript:alert(1)'), null);
});

test('accepts only listed positive-decimal product deep links', () => {
  assert.equal(typeof core.parseDeepLink, 'function');
  const allowed = new Set(['12', '170']);
  assert.equal(core.parseDeepLink('?product=12', allowed), '12');
  assert.equal(core.parseDeepLink('?product=-1', allowed), null);
  assert.equal(core.parseDeepLink('?product=12x', allowed), null);
  assert.equal(core.parseDeepLink('?product=999', allowed), null);
  assert.equal(core.parseDeepLink('', allowed), null);
});

test('builds the unchanged plain-text lead payload contract', () => {
  assert.equal(typeof core.buildLeadPayload, 'function');
  const payload = core.buildLeadPayload(
    {
      name: ' Daniel ',
      phone: ' 608-555-0100 ',
      email: ' owner@example.com ',
      zip: ' 53703 ',
      notes: ' Oak <b>look</b> ',
      website: ''
    },
    {
      product: { titleHtml: 'Gallery<sup>®</sup> Steel' },
      design: { titleHtml: 'Long <b>Panel</b>' },
      color: { titleHtml: 'Standard White' },
      window: { titleHtml: 'ARCH3 Plain' },
      glass: { titleHtml: 'Seeded' }
    },
    'wi'
  );
  assert.deepEqual(payload, {
    name: 'Daniel',
    phone: '608-555-0100',
    email: 'owner@example.com',
    zip: '53703',
    region: 'wi',
    website: '',
    notes: 'Oak look',
    collection: 'Gallery® Steel',
    design: 'Long Panel',
    color: 'Standard White',
    windows: 'ARCH3 Plain',
    glass: 'Seeded'
  });
});
