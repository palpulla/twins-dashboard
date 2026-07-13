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

for (const family of [
  { property: 'ProductDesigns', output: 'designs', imageKey: 'ProductImage' },
  { property: 'Colors', output: 'colors', imageKey: 'ProductImage' },
  { property: 'TopSections', output: 'windows', imageKey: 'ThumbnailImage' },
  { property: 'SpecialityGlassOptions', output: 'glass', imageKey: 'Image' }
]) {
  test('filters malformed and titleless ' + family.output + ' options', () => {
    const raw = { ProductId: '999', Title: 'Synthetic' };
    raw[family.property] = [
      null,
      {},
      { Title: '' },
      { Title: ' <br><sup></sup> ' },
      { Title: 'Usable option', [family.imageKey]: 'https://www.clopaydoor.com/images/usable.webp' }
    ];
    const product = core.normalizeProduct(raw);
    assert.deepEqual(product[family.output].map((item) => item.title), ['Usable option']);
  });
}

test('preserves valid solid and non-solid window navigation after malformed options are removed', () => {
  const product = core.normalizeProduct({
    ProductId: '999',
    Title: 'Synthetic',
    ProductDesigns: [null, { Title: 'Valid design' }],
    Colors: [{ Title: '' }, { Title: 'Valid color' }],
    TopSections: [
      null,
      { Title: '<br>' },
      { Title: 'Short Solid', GroupName: 'Solid' },
      { Title: 'ARCH3 Plain', GroupName: 'Decorative' }
    ],
    SpecialityGlassOptions: [{}, { Title: 'Seeded' }]
  });
  const solid = product.windows.find((item) => item.title === 'Short Solid');
  const nonSolid = product.windows.find((item) => item.title === 'ARCH3 Plain');
  assert.deepEqual(core.availableSteps(product, { window: solid }), [
    'collection', 'design', 'color', 'windows', 'summary'
  ]);
  assert.deepEqual(core.availableSteps(product, { window: nonSolid }), [
    'collection', 'design', 'color', 'windows', 'glass', 'summary'
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

test('uses a lifestyle image as labeled reference and panel art as intrinsic secondary evidence', () => {
  assert.equal(typeof core.selectPreview, 'function');
  const product = core.normalizeProduct(gallerySteel);
  const preview = core.selectPreview(product, {
    design: product.designs[0],
    color: product.colors[0],
    window: product.windows[0],
    glass: product.glass[0]
  }, { products: {} });
  assert.equal(preview.hero.evidence, 'reference-photo');
  assert.equal(preview.hero.allowUpscale, false);
  assert.match(preview.hero.label, /Inspiration photo/);
  assert.equal(preview.panel.evidence, 'panel-style');
  assert.equal(preview.panel.allowUpscale, false);
  assert.match(preview.panel.label, /original resolution/);
  assert.equal(preview.samples.length, 3);
  assert.equal(preview.samples.every((sample) => sample.evidence === 'swatch-only'), true);
  assert.equal(preview.samples.every((sample) => sample.allowUpscale === false), true);
  assert.equal(preview.samples.every((sample) => (
    sample.label === 'Manufacturer sample — final appearance is confirmed before ordering.'
  )), true);
});

test('never upgrades an unproven manifest record to an exact render', () => {
  assert.equal(typeof core.selectPreview, 'function');
  const product = core.normalizeProduct(gallerySteel);
  const manifest = {
    products: {
      '12': {
        referencePhotos: [{
          url: product.referencePhotos[1].image,
          evidence: 'exact-render',
          label: 'unsupported claim'
        }]
      }
    }
  };
  const preview = core.selectPreview(product, {}, manifest);
  assert.equal(preview.hero.evidence, 'reference-photo');
  assert.notEqual(preview.hero.label, 'unsupported claim');
});

test('initial reference manifest contains no exact-render record', () => {
  const manifestPath = path.resolve(__dirname, '..', 'assets', 'reference-manifest.json');
  const manifest = fs.existsSync(manifestPath)
    ? JSON.parse(fs.readFileSync(manifestPath, 'utf8'))
    : {};
  assert.equal(manifest.schemaVersion, 1);
  assert.equal(JSON.stringify(manifest).includes('exact-render'), false);
});

test('calculates six-step availability and skips missing option families', () => {
  assert.equal(typeof core.availableSteps, 'function');
  assert.equal(typeof core.nextStep, 'function');
  const byId = new Map(detailFixtures.map((raw) => {
    const product = core.normalizeProduct(raw);
    return [product.id, product];
  }));
  const full = byId.get('12');
  const solidWindow = full.windows.find((window) => window.title === 'Short Solid');
  assert.deepEqual(
    core.availableSteps(full, { window: solidWindow }),
    ['collection', 'design', 'color', 'windows', 'summary']
  );
  const realWindow = full.windows.find((window) => window.title === 'ARCH3 Plain');
  assert.deepEqual(
    core.availableSteps(full, { window: realWindow }),
    ['collection', 'design', 'color', 'windows', 'glass', 'summary']
  );
  assert.deepEqual(
    core.availableSteps(full, { window: { title: 'No windows (solid)', none: true } }),
    ['collection', 'design', 'color', 'windows', 'summary']
  );
  const noWindows = byId.get('16');
  assert.deepEqual(
    core.availableSteps(noWindows, {}),
    ['collection', 'design', 'color', 'summary']
  );
  const empty = byId.get('8');
  assert.deepEqual(core.availableSteps(empty, {}), ['collection', 'summary']);
  assert.equal(core.nextStep('collection', empty, {}), 'summary');
  assert.equal(core.nextStep('design', noWindows, {}), 'color');
});

test('annotates every frozen solid top section as a no-window choice', () => {
  const products = detailFixtures.map(core.normalizeProduct);
  const isFrozenSolid = (window) => /\bsolid\b/i.test(`${window.group} ${window.title}`);
  const affected = products
    .filter((product) => product.windows.some(isFrozenSolid))
    .sort((left, right) => Number(left.id) - Number(right.id));
  assert.deepEqual(
    affected.map((product) => product.id),
    ['9', '10', '11', '12', '13', '23', '27', '29', '30', '250', '330', '380']
  );
  const solidWindows = affected.flatMap((product) => product.windows.filter(isFrozenSolid));
  assert.equal(solidWindows.length, 51);
  assert.equal(solidWindows.every((window) => window.none === true), true);
  const realWindows = products.flatMap((product) => product.windows.filter((window) => (
    !isFrozenSolid(window)
  )));
  assert.equal(realWindows.every((window) => window.none === false), true);
});

test('uses actual frozen solid choices to suppress glass availability', () => {
  const isFrozenSolid = (window) => /\bsolid\b/i.test(`${window.group} ${window.title}`);
  const affected = detailFixtures
    .map(core.normalizeProduct)
    .filter((product) => product.glass.length && product.windows.some(isFrozenSolid))
    .sort((left, right) => Number(left.id) - Number(right.id));
  assert.deepEqual(
    affected.map((product) => product.id),
    ['9', '10', '11', '12', '13', '29', '30', '250', '330', '380']
  );
  affected.forEach((product) => {
    product.windows.filter(isFrozenSolid).forEach((window) => {
      assert.equal(core.availableSteps(product, { window }).includes('glass'), false);
    });
    const realWindow = product.windows.find((window) => !isFrozenSolid(window));
    assert.ok(realWindow);
    assert.equal(core.availableSteps(product, { window: realWindow }).includes('glass'), true);
  });
});
