# Door Builder Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (\`- [ ]\`) syntax for tracking.

**Goal:** Build and verify a dependency-free, repository-only Twins Garage Doors specification builder that uses honest high-quality reference imagery and produces inactive WordPress candidate artifacts.

**Architecture:** A universal pure-JavaScript core normalizes frozen Clopay fixtures, assigns explicit preview evidence, validates deep links and builds lead payloads. A browser adapter and funnel module consume that core. A deterministic Node build generates the WPCode body, local harness, funnel artifact and SHA-256 manifest while page contracts remain blocked pending fresh exports.

**Tech Stack:** Node.js 24 built-ins (node:test, fs, path, crypto), classic browser JavaScript, CSS, PHP/WPCode template text, JSON fixtures, Python 3.9 stdlib HTTP server.

## Global Constraints

- The builder remains embedded on twinsgaragedoors.com.
- The builder costs $0 and uses no paid visualizer service.
- The builder does not redirect visitors to EZDoor or another off-site designer.
- The launch promise is: build a door specification with honest, high-quality reference imagery.
- The builder must not promise photo upload, live compositing, or an exact visual match.
- MAIN and WI keep the same builder behavior and lead payload contract. KY remains outside this remediation.
- The existing 23-collection catalog and ?product=<safe-id> deep links remain supported.
- Historical preservation files are immutable inputs and must remain byte-identical.
- Page contracts stay blocked-pending-fresh-export.
- The local harness must never call the real lead endpoint.
- No WordPress, WP Cloud, SiteGround, grant, lock, freeze or control-plane mutation is authorized.
- Use only Node and Python standard-library capabilities; add no package.json, lockfile or third-party dependency.

---

## File responsibility map

- website/door-builder-remediation/src/core.js: pure sanitization, normalization, deep-link, preview-policy and payload behavior.
- website/door-builder-remediation/src/app.js: browser rendering, state transitions, API loading and inline quote submission.
- website/door-builder-remediation/src/funnel-submit.js: page-7073 lead-gate submission and success-only redirect.
- website/door-builder-remediation/src/transport.js: injected timeout-aware JSON transport and session cache.
- website/door-builder-remediation/src/styles.css: scoped builder presentation and explicit no-upscale rules.
- website/door-builder-remediation/src/wpcode-wrapper.php.tmpl: thin inactive WPCode wrapper template.
- website/door-builder-remediation/assets/reference-manifest.json: curated reference-photo ordering and provenance.
- website/door-builder-remediation/page-contracts/*.json: non-mutating future page requirements.
- website/door-builder-remediation/scripts/build.mjs: deterministic generation and --check comparison.
- website/door-builder-remediation/tests/*.test.cjs: dependency-free unit and artifact contract tests.
- website/door-builder-remediation/dist/*: deterministic candidate artifacts generated from source.
- website/door-builder-remediation/README.md: commands, artifact meaning, rollback boundary and deployment gates.

---

### Task 1: Establish the pure core contracts

**Files:**
- Create: website/door-builder-remediation/tests/core.test.cjs
- Create: website/door-builder-remediation/src/core.js
- Read-only fixture: docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot/product-12.json

**Interfaces:**
- Produces: TwinsDoorBuilderCore universal module.
- Produces: sanitizeDisplay(value), plainText(value), validateImageUrl(value), normalizeCatalog(raw), normalizeProduct(raw), parseDeepLink(search, allowedIds), buildLeadPayload(input, state, region).
- Consumes: Clopay detail JSON using ProductDesigns, Colors, TopSections, SpecialityGlassOptions and ProductImageGallery.

- [ ] **Step 1: Write the failing core tests**

Create website/door-builder-remediation/tests/core.test.cjs with:

~~~js
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

test('sanitizes display labels and strips all lead-payload markup', () => {
  assert.equal(typeof core.sanitizeDisplay, 'function');
  assert.equal(
    core.sanitizeDisplay('<img src=x onerror=bad>Gallery<sup class=x>®</sup><br class=x>Steel'),
    'Gallery<sup>®</sup><br>Steel'
  );
  assert.equal(core.plainText('Gallery<sup>®</sup>&nbsp;Steel'), 'Gallery® Steel');
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
~~~

- [ ] **Step 2: Run the tests and verify RED**

Run:

~~~bash
node --test website/door-builder-remediation/tests/core.test.cjs
~~~

Expected: seven assertion failures. The first failure reports that normalizeCatalog is undefined. No fixture-read or syntax error is acceptable.

- [ ] **Step 3: Implement the minimal universal core**

Create website/door-builder-remediation/src/core.js with this module shape and the complete functions exercised above:

~~~js
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.TwinsDoorBuilderCore = api;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  function stringValue(value) {
    return value == null ? '' : String(value);
  }

  function decodeEntities(value) {
    return stringValue(value)
      .replace(/&nbsp;/gi, ' ')
      .replace(/&amp;/gi, '&')
      .replace(/&quot;/gi, '"')
      .replace(/&#39;|&apos;/gi, "'")
      .replace(/&reg;/gi, '®')
      .replace(/&trade;/gi, '™');
  }

  function sanitizeDisplay(value) {
    return stringValue(value)
      .replace(/<(?!\/?(?:sup|br)\b)[^>]*>/gi, '')
      .replace(/<sup\b[^>]*>/gi, '<sup>')
      .replace(/<br\b[^>]*>/gi, '<br>');
  }

  function plainText(value) {
    return decodeEntities(sanitizeDisplay(value).replace(/<[^>]*>/g, ''))
      .replace(/\s+/g, ' ')
      .trim();
  }

  function validateImageUrl(value) {
    try {
      var url = new URL(stringValue(value));
      if (url.protocol !== 'https:' || url.hostname !== 'www.clopaydoor.com') {
        return null;
      }
      return url.href;
    } catch (_error) {
      return null;
    }
  }

  function optionList(value, imageKey, evidence) {
    return (Array.isArray(value) ? value : []).map(function (item) {
      return {
        titleHtml: sanitizeDisplay(item && item.Title),
        title: plainText(item && item.Title),
        group: plainText(item && item.GroupName),
        image: validateImageUrl(item && item[imageKey]),
        evidence: evidence
      };
    });
  }

  function normalizeProduct(raw) {
    raw = raw && typeof raw === 'object' ? raw : {};
    var gallery = Array.isArray(raw.ProductImageGallery) ? raw.ProductImageGallery : [];
    return {
      id: plainText(raw.ProductId),
      titleHtml: sanitizeDisplay(raw.Title),
      title: plainText(raw.Title),
      showcaseImage: validateImageUrl(raw.ShowcaseImage),
      designs: optionList(raw.ProductDesigns, 'ProductImage', 'panel-style'),
      colors: optionList(raw.Colors, 'ProductImage', 'swatch-only'),
      windows: optionList(raw.TopSections, 'ThumbnailImage', 'swatch-only'),
      glass: optionList(raw.SpecialityGlassOptions, 'Image', 'swatch-only'),
      referencePhotos: gallery.filter(function (item) {
        return !item || item.IsImage !== false;
      }).map(function (item) {
        return {
          title: plainText(item && item.Title),
          image: validateImageUrl(item && item.ImageUrl),
          evidence: 'reference-photo'
        };
      }).filter(function (item) {
        return Boolean(item.image);
      })
    };
  }

  function normalizeCatalog(raw) {
    return (Array.isArray(raw) ? raw : []).map(function (item) {
      return {
        id: plainText(item && item.ProductId),
        titleHtml: sanitizeDisplay(item && item.Title),
        title: plainText(item && item.Title),
        shortDescriptionHtml: sanitizeDisplay(item && item.ShortDescription),
        shortDescription: plainText(item && item.ShortDescription),
        showcaseImage: validateImageUrl(item && item.ShowcaseImage)
      };
    }).filter(function (item) {
      return Boolean(item.id && item.title);
    });
  }

  function parseDeepLink(search, allowedIds) {
    var value = new URLSearchParams(stringValue(search)).get('product');
    if (!value || !/^[1-9][0-9]*$/.test(value)) {
      return null;
    }
    return allowedIds && allowedIds.has(value) ? value : null;
  }

  function addPayloadChoice(payload, key, choice) {
    var value = plainText(choice && (choice.titleHtml || choice.title));
    if (value) {
      payload[key] = value;
    }
  }

  function buildLeadPayload(input, state, region) {
    input = input || {};
    state = state || {};
    var payload = {
      name: plainText(input.name),
      phone: plainText(input.phone),
      email: plainText(input.email),
      zip: plainText(input.zip),
      region: ['main', 'wi', 'ky'].indexOf(region) >= 0 ? region : 'main',
      website: plainText(input.website)
    };
    if (plainText(input.notes)) {
      payload.notes = plainText(input.notes);
    }
    addPayloadChoice(payload, 'collection', state.product);
    addPayloadChoice(payload, 'design', state.design);
    addPayloadChoice(payload, 'color', state.color);
    addPayloadChoice(payload, 'windows', state.window);
    addPayloadChoice(payload, 'glass', state.glass);
    return payload;
  }

  return {
    sanitizeDisplay: sanitizeDisplay,
    plainText: plainText,
    validateImageUrl: validateImageUrl,
    normalizeCatalog: normalizeCatalog,
    normalizeProduct: normalizeProduct,
    parseDeepLink: parseDeepLink,
    buildLeadPayload: buildLeadPayload
  };
}));
~~~

- [ ] **Step 4: Run the core tests and verify GREEN**

Run:

~~~bash
node --test website/door-builder-remediation/tests/core.test.cjs
~~~

Expected: 7 tests pass, 0 fail.

- [ ] **Step 5: Commit the core slice**

~~~bash
git add website/door-builder-remediation/src/core.js website/door-builder-remediation/tests/core.test.cjs
git commit -m "feat(web): add tested door-builder core contracts"
~~~

---

### Task 2: Add evidence-gated preview selection

**Files:**
- Create: website/door-builder-remediation/assets/reference-manifest.json
- Modify: website/door-builder-remediation/tests/core.test.cjs
- Modify: website/door-builder-remediation/src/core.js

**Interfaces:**
- Consumes: normalizeProduct(raw).
- Produces: selectPreview(product, state, manifest).
- Produces preview: { hero, panel, samples } where every image has evidence, label and allowUpscale:false.

- [ ] **Step 1: Add failing preview-policy tests**

Append these tests to core.test.cjs:

~~~js
test('uses a lifestyle image as labeled reference and panel art as intrinsic secondary evidence', () => {
  assert.equal(typeof core.selectPreview, 'function');
  const product = core.normalizeProduct(gallerySteel);
  const preview = core.selectPreview(product, { design: product.designs[0] }, { products: {} });
  assert.equal(preview.hero.evidence, 'reference-photo');
  assert.equal(preview.hero.allowUpscale, false);
  assert.match(preview.hero.label, /Inspiration photo/);
  assert.equal(preview.panel.evidence, 'panel-style');
  assert.equal(preview.panel.allowUpscale, false);
  assert.match(preview.panel.label, /original resolution/);
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
  assert.deepEqual(
    core.availableSteps(full, { window: full.windows[0] }),
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
~~~

- [ ] **Step 2: Run only the new tests and verify RED**

Run:

~~~bash
node --test --test-name-pattern="lifestyle|unproven|manifest|six-step" website/door-builder-remediation/tests/core.test.cjs
~~~

Expected: 4 failures: two because selectPreview is undefined, one because the manifest does not exist, and one because availableSteps is undefined.

- [ ] **Step 3: Create the initial curated reference manifest**

Create reference-manifest.json with:

~~~json
{
  "schemaVersion": 1,
  "allowedHost": "www.clopaydoor.com",
  "products": {
    "12": {
      "title": "Gallery Steel",
      "referencePhotos": [
        {
          "url": "https://www.clopaydoor.com/images/default-source/product-images/garage-doors/gallerysteel/normal/gallery-steel-ug-long-rec14-822.webp?sfvrsn=3eb6b5f1_6",
          "evidence": "reference-photo",
          "label": "Gallery Steel inspiration photo",
          "source": "clopay-public-product-api",
          "sourceField": "ProductImageGallery.ImageUrl",
          "rightsStatement": "Manufacturer-hosted reference image; deployment remains subject to owner review."
        },
        {
          "url": "https://www.clopaydoor.com/images/default-source/product-images/garage-doors/gallerysteel/normal/gallery-lp-ug-rectgrilles-01.webp?sfvrsn=e1e3dabb_6",
          "evidence": "reference-photo",
          "label": "Gallery Steel installed-door reference",
          "source": "clopay-public-product-api",
          "sourceField": "ProductImageGallery.ImageUrl",
          "rightsStatement": "Manufacturer-hosted reference image; deployment remains subject to owner review."
        }
      ]
    }
  }
}
~~~

- [ ] **Step 4: Implement selectPreview and export it**

Add this function before the return block in core.js:

~~~js
function selectPreview(product, state, manifest) {
  product = product || {};
  state = state || {};
  manifest = manifest && typeof manifest === 'object' ? manifest : { products: {} };
  var entry = manifest.products && manifest.products[String(product.id)];
  var curated = entry && Array.isArray(entry.referencePhotos) ? entry.referencePhotos : [];
  var curatedPhoto = curated.map(function (item) {
    if (!item || item.evidence !== 'reference-photo') {
      return null;
    }
    var image = validateImageUrl(item.url);
    return image ? {
      image: image,
      evidence: 'reference-photo',
      label: 'Inspiration photo — your selected options are listed separately and Twins will confirm the final appearance.',
      allowUpscale: false
    } : null;
  }).filter(Boolean)[0];
  var galleryPhoto = product.referencePhotos && product.referencePhotos[0];
  var hero = curatedPhoto || (galleryPhoto ? {
    image: galleryPhoto.image,
    evidence: 'reference-photo',
    label: 'Inspiration photo — your selected options are listed separately and Twins will confirm the final appearance.',
    allowUpscale: false
  } : null);
  if (!hero && product.showcaseImage) {
    hero = {
      image: product.showcaseImage,
      evidence: 'reference-photo',
      label: 'Inspiration photo — your selected options are listed separately and Twins will confirm the final appearance.',
      allowUpscale: false
    };
  }
  var panel = state.design && state.design.image ? {
    image: state.design.image,
    evidence: 'panel-style',
    label: 'Panel-style reference shown at its original resolution.',
    allowUpscale: false
  } : null;
  return {
    hero: hero,
    panel: panel,
    samples: [state.color, state.window, state.glass].filter(Boolean).map(function (item) {
      return {
        title: plainText(item.titleHtml || item.title),
        image: item.image || null,
        evidence: 'swatch-only',
        allowUpscale: false
      };
    })
  };
}

function availableSteps(product, state) {
  product = product || {};
  state = state || {};
  var steps = ['collection'];
  if (product.designs && product.designs.length) steps.push('design');
  if (product.colors && product.colors.length) steps.push('color');
  if (product.windows && product.windows.length) steps.push('windows');
  if (state.window && !state.window.none && product.glass && product.glass.length) {
    steps.push('glass');
  }
  steps.push('summary');
  return steps;
}

function nextStep(current, product, state) {
  var canonical = ['collection', 'design', 'color', 'windows', 'glass', 'summary'];
  var available = new Set(availableSteps(product, state));
  var index = canonical.indexOf(current);
  for (var position = index + 1; position < canonical.length; position += 1) {
    if (available.has(canonical[position])) return canonical[position];
  }
  return 'summary';
}
~~~

Add selectPreview: selectPreview, availableSteps: availableSteps and nextStep: nextStep to the exported object.

- [ ] **Step 5: Run the complete core tests and verify GREEN**

~~~bash
node --test website/door-builder-remediation/tests/core.test.cjs
~~~

Expected: 11 tests pass, 0 fail.

- [ ] **Step 6: Commit the preview-policy slice**

~~~bash
git add website/door-builder-remediation/assets/reference-manifest.json website/door-builder-remediation/src/core.js website/door-builder-remediation/tests/core.test.cjs
git commit -m "feat(web): add honest door-preview evidence policy"
~~~

---

### Task 3: Make funnel redirect success provable

**Files:**
- Create: website/door-builder-remediation/tests/funnel-submit.test.cjs
- Create: website/door-builder-remediation/src/funnel-submit.js

**Interfaces:**
- Produces: TwinsDoorBuilderFunnel universal module.
- Produces: submitLead({fetchImpl, endpoint, payload, redirect}).
- Produces: collectValues(form) supporting both semantic names and the current twx-n/twx-p/twx-e/twx-z/twx-w IDs.
- Produces: bindFunnel(form, options) for the page-7073 candidate script.

- [ ] **Step 1: Write the failing submission matrix**

Create funnel-submit.test.cjs:

~~~js
const test = require('node:test');
const assert = require('node:assert/strict');

let funnel = {};
try {
  funnel = require('../src/funnel-submit.js');
} catch (_error) {
  funnel = {};
}

function response(ok, body) {
  return {
    ok,
    json: async () => {
      if (body instanceof Error) throw body;
      return body;
    }
  };
}

async function run(fetchImpl) {
  assert.equal(typeof funnel.submitLead, 'function');
  let redirects = 0;
  const result = await funnel.submitLead({
    fetchImpl,
    endpoint: '/lead',
    payload: { name: 'Test' },
    redirect: () => { redirects += 1; }
  });
  return { result, redirects };
}

test('redirects once only for 2xx plus ok true', async () => {
  const output = await run(async () => response(true, { ok: true }));
  assert.equal(output.result.ok, true);
  assert.equal(output.redirects, 1);
});

test('does not redirect for non-2xx', async () => {
  const output = await run(async () => response(false, { ok: true }));
  assert.equal(output.result.ok, false);
  assert.equal(output.result.reason, 'http');
  assert.equal(output.redirects, 0);
});

test('does not redirect for malformed JSON', async () => {
  const output = await run(async () => response(true, new Error('bad json')));
  assert.equal(output.result.reason, 'json');
  assert.equal(output.redirects, 0);
});

test('does not redirect for ok false', async () => {
  const output = await run(async () => response(true, { ok: false }));
  assert.equal(output.result.reason, 'body');
  assert.equal(output.redirects, 0);
});

test('does not redirect for a network failure', async () => {
  const output = await run(async () => { throw new Error('offline'); });
  assert.equal(output.result.reason, 'network');
  assert.equal(output.redirects, 0);
});

test('collects the current lead-gate IDs into the unchanged payload fields', () => {
  assert.equal(typeof funnel.collectValues, 'function');
  const values = {
    '#twx-n': { value: ' Daniel ' },
    '#twx-p': { value: ' 608-555-0100 ' },
    '#twx-e': { value: ' owner@example.com ' },
    '#twx-z': { value: ' 53703 ' },
    '#twx-w': { value: '' }
  };
  const form = {
    querySelector(selector) {
      const id = selector.split(',').pop().trim();
      return values[id] || null;
    },
    getAttribute(name) {
      return name === 'data-region' ? 'main' : null;
    }
  };
  assert.deepEqual(funnel.collectValues(form), {
    name: 'Daniel',
    phone: '608-555-0100',
    email: 'owner@example.com',
    zip: '53703',
    region: 'main',
    website: ''
  });
});

function boundForm() {
  let submitHandler;
  const button = { disabled: false };
  const error = { hidden: true, textContent: '' };
  const fields = {
    '#twx-n': { value: 'Test Lead' },
    '#twx-p': { value: '608-555-0100' },
    '#twx-e': { value: 'test@example.com' },
    '#twx-z': { value: '53703' },
    '#twx-w': { value: '' }
  };
  const form = {
    addEventListener(type, handler) {
      if (type === 'submit') submitHandler = handler;
    },
    querySelector(selector) {
      if (selector === 'button[type="submit"]') return button;
      if (selector === '[data-door-builder-error]') return error;
      const id = selector.split(',').pop().trim();
      return fields[id] || null;
    },
    getAttribute(name) {
      return name === 'data-region' ? 'main' : null;
    }
  };
  return {
    form,
    button,
    error,
    submit: () => submitHandler({ preventDefault() {} })
  };
}

test('bound funnel redirects and keeps its error hidden after confirmed success', async () => {
  assert.equal(typeof funnel.bindFunnel, 'function');
  const fixture = boundForm();
  const redirects = [];
  funnel.bindFunnel(fixture.form, {
    fetchImpl: async () => response(true, { ok: true }),
    endpoint: '/lead',
    successUrl: '/door-builder/',
    redirectImpl: (url) => redirects.push(url),
    errorMessage: 'Call Twins.'
  });
  await fixture.submit();
  assert.deepEqual(redirects, ['/door-builder/']);
  assert.equal(fixture.error.hidden, true);
});

test('bound funnel re-enables the button and shows fallback on failure', async () => {
  assert.equal(typeof funnel.bindFunnel, 'function');
  const fixture = boundForm();
  const redirects = [];
  funnel.bindFunnel(fixture.form, {
    fetchImpl: async () => response(false, { ok: true }),
    endpoint: '/lead',
    successUrl: '/door-builder/',
    redirectImpl: (url) => redirects.push(url),
    errorMessage: 'Call Twins at (833) 833-2010.'
  });
  await fixture.submit();
  assert.deepEqual(redirects, []);
  assert.equal(fixture.button.disabled, false);
  assert.equal(fixture.error.hidden, false);
  assert.equal(fixture.error.textContent, 'Call Twins at (833) 833-2010.');
});
~~~

- [ ] **Step 2: Run the tests and verify RED**

~~~bash
node --test website/door-builder-remediation/tests/funnel-submit.test.cjs
~~~

Expected: 8 failures because submitLead, collectValues and bindFunnel are undefined.

- [ ] **Step 3: Implement the success-only submission module**

Create funnel-submit.js:

~~~js
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.TwinsDoorBuilderFunnel = api;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  async function submitLead(options) {
    try {
      var response = await options.fetchImpl(options.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(options.payload)
      });
      if (!response || !response.ok) {
        return { ok: false, reason: 'http' };
      }
      var body;
      try {
        body = await response.json();
      } catch (_error) {
        return { ok: false, reason: 'json' };
      }
      if (!body || body.ok !== true) {
        return { ok: false, reason: 'body' };
      }
      if (typeof options.redirect === 'function') {
        options.redirect();
      }
      return { ok: true };
    } catch (_error) {
      return { ok: false, reason: 'network' };
    }
  }

  function collectValues(form) {
    function value(name, id) {
      var element = form.querySelector('[name="' + name + '"],#' + id);
      return element ? String(element.value || '').trim() : '';
    }
    return {
      name: value('name', 'twx-n'),
      phone: value('phone', 'twx-p'),
      email: value('email', 'twx-e'),
      zip: value('zip', 'twx-z'),
      region: form.getAttribute('data-region') || 'main',
      website: value('website', 'twx-w')
    };
  }

  function bindFunnel(form, options) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      var button = form.querySelector('button[type="submit"]');
      var error = form.querySelector('[data-door-builder-error]');
      var values = collectValues(form);
      button.disabled = true;
      error.hidden = true;
      var result = await submitLead({
        fetchImpl: options.fetchImpl || fetch,
        endpoint: options.endpoint,
        payload: values,
        redirect: function () {
          var redirectImpl = options.redirectImpl || function (url) { location.assign(url); };
          redirectImpl(options.successUrl);
        }
      });
      if (!result.ok) {
        button.disabled = false;
        error.hidden = false;
        error.textContent = options.errorMessage;
      }
    });
  }

  return {
    submitLead: submitLead,
    collectValues: collectValues,
    bindFunnel: bindFunnel
  };
}));
~~~

- [ ] **Step 4: Run the funnel tests and verify GREEN**

~~~bash
node --test website/door-builder-remediation/tests/funnel-submit.test.cjs
~~~

Expected: 8 tests pass, 0 fail.

- [ ] **Step 5: Commit the funnel slice**

~~~bash
git add website/door-builder-remediation/src/funnel-submit.js website/door-builder-remediation/tests/funnel-submit.test.cjs
git commit -m "fix(web): redirect door leads only after confirmed success"
~~~

---

### Task 4: Extract and remediate the browser app

**Files:**
- Create: website/door-builder-remediation/tests/transport.test.cjs
- Create: website/door-builder-remediation/tests/app.test.cjs
- Create: website/door-builder-remediation/tests/build-contract.test.cjs
- Create: website/door-builder-remediation/src/transport.js
- Create: website/door-builder-remediation/src/app.js
- Create: website/door-builder-remediation/src/styles.css
- Read-only baseline: docs/superpowers/backups/2026-07-09-phase4-catalog/snippet-7127-after-deeplink-expected.php

**Interfaces:**
- Consumes: window.TwinsDoorBuilderCore.
- Consumes: window.TwinsDoorBuilderTransport.
- Consumes: window.TwinsDoorBuilderFunnel.
- Consumes normalized product state and assets/reference-manifest.json.
- Produces one browser mount, six-step builder, honest preview, fallback form and inline result state.

- [ ] **Step 1: Write failing transport tests**

Create transport.test.cjs:

~~~js
const test = require('node:test');
const assert = require('node:assert/strict');

let transport = {};
try {
  transport = require('../src/transport.js');
} catch (_error) {
  transport = {};
}

function response(ok, body) {
  return {
    ok,
    status: ok ? 200 : 503,
    json: async () => {
      if (body instanceof Error) throw body;
      return body;
    }
  };
}

test('fetchJson returns parsed JSON only for a successful response', async () => {
  assert.equal(typeof transport.fetchJson, 'function');
  const value = await transport.fetchJson(
    async () => response(true, { ok: true }),
    '/fixture',
    10000
  );
  assert.deepEqual(value, { ok: true });
});

test('fetchJson rejects non-2xx and malformed JSON', async () => {
  assert.equal(typeof transport.fetchJson, 'function');
  await assert.rejects(
    () => transport.fetchJson(async () => response(false, { ok: false }), '/fixture', 10000),
    /http 503/
  );
  await assert.rejects(
    () => transport.fetchJson(async () => response(true, new Error('bad json')), '/fixture', 10000),
    /bad json/
  );
});

test('fetchJson enforces the injected 10-second abort boundary', async () => {
  assert.equal(typeof transport.fetchJson, 'function');
  class FakeAbortController {
    constructor() { this.signal = { aborted: false }; }
    abort() { this.signal.aborted = true; }
  }
  await assert.rejects(
    () => transport.fetchJson(
      async (_url, options) => {
        assert.equal(options.signal.aborted, true);
        throw new Error('aborted');
      },
      '/fixture',
      10000,
      FakeAbortController,
      (callback, delay) => {
        assert.equal(delay, 10000);
        callback();
        return 1;
      },
      () => {}
    ),
    /aborted/
  );
});

test('session cache round-trips JSON and fails closed on invalid data', () => {
  assert.equal(typeof transport.createSessionCache, 'function');
  const values = new Map();
  const storage = {
    getItem: (key) => values.has(key) ? values.get(key) : null,
    setItem: (key, value) => values.set(key, value)
  };
  const cache = transport.createSessionCache(storage);
  cache.set('product:12', { id: '12' });
  assert.deepEqual(cache.get('product:12'), { id: '12' });
  values.set('broken', '{');
  assert.equal(cache.get('broken'), null);
});
~~~

- [ ] **Step 2: Run transport tests and verify RED**

~~~bash
node --test website/door-builder-remediation/tests/transport.test.cjs
~~~

Expected: 4 failures because fetchJson and createSessionCache are undefined.

- [ ] **Step 3: Implement transport.js**

~~~js
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.TwinsDoorBuilderTransport = api;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  function fetchJson(
    fetchImpl,
    url,
    timeoutMs,
    AbortControllerImpl,
    setTimeoutImpl,
    clearTimeoutImpl
  ) {
    AbortControllerImpl = AbortControllerImpl || AbortController;
    setTimeoutImpl = setTimeoutImpl || setTimeout;
    clearTimeoutImpl = clearTimeoutImpl || clearTimeout;
    var controller = new AbortControllerImpl();
    var timer = setTimeoutImpl(function () { controller.abort(); }, timeoutMs || 10000);
    return Promise.resolve()
      .then(function () { return fetchImpl(url, { signal: controller.signal }); })
      .then(function (response) {
        if (!response.ok) throw new Error('http ' + response.status);
        return response.json();
      })
      .finally(function () { clearTimeoutImpl(timer); });
  }

  function createSessionCache(storage) {
    return {
      get: function (key) {
        try {
          var value = storage.getItem(key);
          return value ? JSON.parse(value) : null;
        } catch (_error) {
          return null;
        }
      },
      set: function (key, value) {
        try {
          storage.setItem(key, JSON.stringify(value));
          return true;
        } catch (_error) {
          return false;
        }
      }
    };
  }

  return { fetchJson: fetchJson, createSessionCache: createSessionCache };
}));
~~~

- [ ] **Step 4: Run transport tests and verify GREEN**

~~~bash
node --test website/door-builder-remediation/tests/transport.test.cjs
~~~

Expected: 4 tests pass, 0 fail.

- [ ] **Step 5: Write failing controller and source-contract tests**

Create app.test.cjs:

~~~js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const core = require('../src/core.js');
const transport = require('../src/transport.js');
const funnel = require('../src/funnel-submit.js');
let app = {};
try {
  app = require('../src/app.js');
} catch (_error) {
  app = {};
}

const fixtures = path.resolve(
  __dirname,
  '../../../docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot'
);
const list = JSON.parse(fs.readFileSync(path.join(fixtures, 'products-list.json'), 'utf8'));
const details = {};
for (const name of fs.readdirSync(fixtures).filter((file) => /^product-[0-9]+\.json$/.test(file))) {
  const detail = JSON.parse(fs.readFileSync(path.join(fixtures, name), 'utf8'));
  details[String(detail.ProductId)] = detail;
}

function jsonResponse(ok, body, status = 200) {
  return { ok, status, json: async () => body };
}

function memoryStorage() {
  const values = new Map();
  return {
    getItem: (key) => values.has(key) ? values.get(key) : null,
    setItem: (key, value) => values.set(key, value)
  };
}

function makeController(fetchImpl) {
  assert.equal(typeof app.createController, 'function');
  return app.createController({
    core,
    transport,
    funnel,
    fetchImpl,
    storage: memoryStorage(),
    listUrl: 'https://fixture/list',
    detailUrl: 'https://fixture/detail?id=',
    endpoint: '/lead',
    region: 'main'
  });
}

test('controller boots a valid product deep link through normalized fixtures', async () => {
  const calls = [];
  const controller = makeController(async (url) => {
    calls.push(url);
    if (url === 'https://fixture/list') return jsonResponse(true, list);
    const id = new URL(url).searchParams.get('id');
    return jsonResponse(true, details[id]);
  });
  const state = await controller.load('?product=12');
  assert.equal(state.products.length, 23);
  assert.equal(state.product.id, '12');
  assert.equal(state.step, 'design');
  assert.equal(calls.length, 2);
  controller.skipToQuote();
  assert.equal(controller.state.step, 'summary');
  controller.back();
  assert.equal(controller.state.step, 'design');
});

test('controller ignores an unlisted deep link without loading detail', async () => {
  const calls = [];
  const controller = makeController(async (url) => {
    calls.push(url);
    return jsonResponse(true, list);
  });
  const state = await controller.load('?product=999');
  assert.equal(state.product, null);
  assert.equal(state.step, 'collection');
  assert.deepEqual(calls, ['https://fixture/list']);
});

test('controller fails closed when the catalog cannot load', async () => {
  const controller = makeController(async () => jsonResponse(false, {}, 503));
  const state = await controller.load('');
  assert.equal(state.step, 'fallback');
});

test('controller fails closed for an empty normalized catalog', async () => {
  const controller = makeController(async () => jsonResponse(true, []));
  const state = await controller.load('');
  assert.equal(state.step, 'fallback');
});

test('controller fails closed for malformed or mismatched product detail', async () => {
  for (const badDetail of [{}, { ProductId: '170', Title: 'Wrong product' }]) {
    const controller = makeController(async (url) => {
      if (url === 'https://fixture/list') return jsonResponse(true, list);
      return jsonResponse(true, badDetail);
    });
    const state = await controller.load('?product=12');
    assert.equal(state.step, 'fallback');
    assert.equal(state.product, null);
  }
});

test('controller clears stale selections after a failed product switch', async () => {
  let posted;
  const controller = makeController(async (url, options = {}) => {
    if (options.method === 'POST') {
      posted = JSON.parse(options.body);
      return jsonResponse(true, { ok: false });
    }
    if (url === 'https://fixture/list') return jsonResponse(true, list);
    const id = new URL(url).searchParams.get('id');
    if (id === '12') return jsonResponse(true, details[id]);
    return jsonResponse(false, {}, 503);
  });
  await controller.load('?product=12');
  controller.choose('design', 0);
  const failed = await controller.selectProduct('170');
  assert.equal(failed.step, 'fallback');
  assert.equal(failed.product, null);
  await controller.submit({ name: 'Test', phone: '608-555-0100', website: '' });
  for (const key of ['collection', 'design', 'color', 'windows', 'glass']) {
    assert.equal(Object.hasOwn(posted, key), false, key);
  }
});

test('controller posts the core payload through the tested funnel module', async () => {
  let posted;
  const controller = makeController(async (url, options = {}) => {
    if (options.method === 'POST') {
      posted = JSON.parse(options.body);
      return jsonResponse(true, { ok: true });
    }
    if (url === 'https://fixture/list') return jsonResponse(true, list);
    const id = new URL(url).searchParams.get('id');
    return jsonResponse(true, details[id]);
  });
  await controller.load('?product=12');
  controller.choose('design', 0);
  controller.choose('color', 0);
  controller.choose('window', 0);
  controller.choose('glass', 0);
  const result = await controller.submit({
    name: 'Test',
    phone: '608-555-0100',
    email: 'test@example.com',
    zip: '53703',
    website: ''
  });
  assert.equal(result.ok, true);
  assert.equal(controller.state.step, 'thanks');
  assert.equal(posted.collection, 'Gallery® Steel');
  assert.equal(posted.design, 'Short Panel');
  assert.equal(posted.region, 'main');
});

test('single image renderer omits null normalized image URLs', () => {
  const product = core.normalizeProduct({
    ProductId: '1',
    Title: 'Synthetic',
    ProductDesigns: [{ Title: 'Unsafe', ProductImage: 'javascript:alert(1)' }]
  });
  assert.equal(product.designs[0].image, null);
  assert.equal(typeof app.imageHTML, 'function');
  assert.equal(app.imageHTML(product.designs[0].image, 'Unsafe', '', 'lazy'), '');
  const appSource = fs.readFileSync(path.resolve(__dirname, '..', 'src', 'app.js'), 'utf8');
  assert.equal((appSource.match(/<img\b/g) || []).length, 1);
});
~~~

Create build-contract.test.cjs:

~~~js
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

function read(relative) {
  const file = path.resolve(__dirname, '..', relative);
  return fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '';
}

test('browser app declares the honest reference labels and core dependency', () => {
  const app = read('src/app.js');
  const core = read('src/core.js');
  assert.ok(app.trim().length > 0, 'app source missing');
  assert.match(app, /TwinsDoorBuilderCore/);
  assert.match(app, /TwinsDoorBuilderTransport/);
  assert.match(app, /TwinsDoorBuilderFunnel/);
  assert.match(core, /Inspiration photo/);
  assert.match(core, /Panel-style reference shown at its original resolution/);
  assert.match(app, /core\.selectPreview/);
  assert.match(app, /function imageHTML/);
  assert.match(app, /if \(!image\) return ''/);
});

test('builder imagery uses the explicit no-upscale CSS contract', () => {
  const css = read('src/styles.css');
  assert.ok(css.trim().length > 0, 'CSS source missing');
  assert.match(css, /\.twxdb img\{[^}]*width:auto[^}]*height:auto[^}]*max-width:100%/);
  assert.doesNotMatch(css, /\.twxdb[^{}]*img\{[^}]*width:100%/);
});

test('candidate source contains no prohibited visualization promise', () => {
  const source = (read('src/app.js') + '\n' + read('src/styles.css')).toLowerCase();
  assert.ok(source.trim().length > 0, 'candidate source missing');
  for (const phrase of [
    'upload your home',
    'try it on your house',
    'every option live',
    'exact render',
    "here's your door",
    'your design is on its way',
    "you'll see your exact door"
  ]) {
    assert.equal(source.includes(phrase), false, phrase);
  }
});

test('builder delegates payload and submission to tested modules', () => {
  const app = read('src/app.js');
  assert.ok(app.trim().length > 0, 'app source missing');
  assert.match(app, /core\.buildLeadPayload/);
  assert.match(app, /funnel\.submitLead/);
});
~~~

- [ ] **Step 6: Run the source contracts and verify RED**

~~~bash
node --test website/door-builder-remediation/tests/app.test.cjs website/door-builder-remediation/tests/build-contract.test.cjs
~~~

Expected: 12 failures: eight app/API failures and four missing-source contract failures. No forbidden-copy test may pass on an empty file.

- [ ] **Step 7: Create app.js from the authoritative preserved builder**

Use the IIFE in snippet-7127-after-deeplink-expected.php as the behavioral baseline so collection ordering, six-step navigation, ?product= deep links, session caching, skip logic and regional form behavior stay intact. Do not copy its PHP wrapper or CSS.

Make these exact structural changes while creating app.js:

1. Wrap app.js as a universal module that exports createController under Node, assigns window.TwinsDoorBuilderApp in browsers, and boots exactly once:

~~~js
(function (root, factory) {
  var api = factory(root);
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.TwinsDoorBuilderApp = api;
    var start = function () { api.boot(root.document, root); };
    if (root.document.readyState === 'loading') {
      root.document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
      start();
    }
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function (root) {
  'use strict';
  return { createController: createController, boot: boot, imageHTML: imageHTML };
}));
~~~

boot(documentRef, windowRef) finds exactly one #twxdb mount, binds core/transport/funnel from windowRef, creates one controller, and adapts the preserved renderer/events to controller.state and controller methods. If the mount is absent it returns false without side effects. If dependencies are absent it renders the fallback copy and returns false.
2. Implement createController(dependencies) with state {step:'collection',products:[],product:null,design:null,color:null,window:null,glass:null,skipped:{},history:[]} and these public methods:
   - load(search): cached 10-second catalog fetch, normalization, safe deep-link parse and optional detail load; returns state; failures set fallback.
   - selectProduct(id): cached 10-second detail fetch, normalize, reset choices and move through core.nextStep.
   - choose(kind,index): choose from designs/colors/windows/glass and update state.
   - back(): restore the previous step from controller-owned history.
   - skipToQuote(): push the current step and move to summary.
   - submit(formValues): core.buildLeadPayload followed by funnel.submitLead; returns the funnel result.

Use this exact controller implementation inside the universal wrapper:

~~~js
function createController(dependencies) {
  var core = dependencies.core;
  var transport = dependencies.transport;
  var funnel = dependencies.funnel;
  var cache = transport.createSessionCache(dependencies.storage);
  var state = {
    step: 'collection',
    products: [],
    product: null,
    design: null,
    color: null,
    window: null,
    glass: null,
    skipped: {},
    history: []
  };

  function loadCached(key, url) {
    var cached = cache.get(key);
    if (cached) return Promise.resolve(cached);
    return transport.fetchJson(dependencies.fetchImpl, url, 10000).then(function (value) {
      cache.set(key, value);
      return value;
    });
  }

  function clearProduct() {
    state.product = null;
    state.design = null;
    state.color = null;
    state.window = null;
    state.glass = null;
    state.skipped = {};
    state.history.length = 0;
  }

  async function selectProduct(id) {
    clearProduct();
    var listed = state.products.some(function (item) { return item.id === id; });
    if (!listed) {
      state.step = 'fallback';
      return state;
    }
    try {
      var raw = await loadCached('twxdb:p:' + id, dependencies.detailUrl + encodeURIComponent(id));
      var product = core.normalizeProduct(raw);
      if (!product.id || product.id !== id || !product.title) {
        throw new Error('invalid product detail');
      }
      state.product = product;
      state.history.push('collection');
      state.step = core.nextStep('collection', state.product, state);
    } catch (_error) {
      state.step = 'fallback';
    }
    return state;
  }

  async function load(search) {
    try {
      state.products = [];
      clearProduct();
      var rawList = await loadCached('twxdb:list', dependencies.listUrl);
      state.products = core.normalizeCatalog(rawList);
      if (!state.products.length) throw new Error('empty catalog');
      var allowed = new Set(state.products.map(function (item) { return item.id; }));
      var deepLink = core.parseDeepLink(search, allowed);
      state.step = 'collection';
      if (deepLink) await selectProduct(deepLink);
    } catch (_error) {
      state.step = 'fallback';
    }
    return state;
  }

  function choose(kind, index) {
    if (!state.product) return state;
    var sourceName = {
      design: 'designs',
      color: 'colors',
      window: 'windows',
      glass: 'glass'
    }[kind];
    if (!sourceName) return state;
    if (kind === 'window' && index === -1) {
      state.window = {
        titleHtml: 'No windows (solid)',
        title: 'No windows (solid)',
        image: null,
        evidence: 'swatch-only',
        none: true
      };
    } else {
      state[kind] = state.product[sourceName][index] || null;
    }
    if (kind === 'window') state.glass = null;
    var canonicalName = kind === 'window' ? 'windows' : kind;
    state.history.push(state.step);
    state.step = core.nextStep(canonicalName, state.product, state);
    return state;
  }

  function back() {
    if (state.history.length) state.step = state.history.pop();
    return state;
  }

  function skipToQuote() {
    if (state.step !== 'summary') state.history.push(state.step);
    state.step = 'summary';
    return state;
  }

  function submit(formValues) {
    var payload = core.buildLeadPayload(formValues, state, dependencies.region);
    return funnel.submitLead({
      fetchImpl: dependencies.fetchImpl,
      endpoint: dependencies.endpoint,
      payload: payload
    }).then(function (result) {
      if (result.ok) state.step = 'thanks';
      return result;
    });
  }

  return {
    state: state,
    load: load,
    selectProduct: selectProduct,
    choose: choose,
    back: back,
    skipToQuote: skipToQuote,
    submit: submit
  };
}
~~~
3. At browser boot, bind var core = window.TwinsDoorBuilderCore, var transport = window.TwinsDoorBuilderTransport and var funnel = window.TwinsDoorBuilderFunnel; fail closed into the fallback form when any is absent. Create one controller and make DOM handlers call its public methods rather than owning a second state machine.
4. Normalize the collection response with core.normalizeCatalog and build the allowed-ID Set from normalized product.id values.
5. Normalize fetched detail JSON with core.normalizeProduct.
6. Use core.parseDeepLink for ?product=.
7. Convert every preserved raw-property access using this fixed mapping:

| Preserved property | Remediation property |
| --- | --- |
| ProductId | id |
| Title | titleHtml for display; title for text |
| ShortDescription | shortDescriptionHtml for display |
| ShowcaseImage | showcaseImage |
| ProductDesigns | designs |
| Colors | colors |
| TopSections | windows |
| SpecialityGlassOptions | glass |
| ProductImage | image |
| ThumbnailImage | image |
| Image | image |

8. Preserve all 23 collection cards and deep-link boot through the single controller:

~~~js
function pickProductById(productId) {
  renderLoading('Loading door details…');
  return controller.selectProduct(productId).then(function () {
    render(true);
  });
}

controller.load(location.search).then(function () {
  render();
});
~~~

Collection-card click handlers call pickProductById(controller.state.products[index].id). Selection handlers call controller.choose(kind,index), Back calls controller.back(), and Skip to quote calls controller.skipToQuote(); every handler then calls render(true). The renderer reads controller.state only. Do not retain loadList, loadProduct, state.history, go, back, nextAfter or any second mutable state outside createController.

9. Replace the old heroHTML function with a single conditional image helper. Every collection, design, color, window, glass, preview and selected-pick renderer must call imageHTML; no renderer may emit an img element for an invalid or null normalized URL:

~~~js
function imageHTML(image, alt, className, loading) {
  if (!image) return '';
  return '<img src="' + esc(image) + '" alt="' + esc(alt || '') + '"'
    + (className ? ' class="' + esc(className) + '"' : '')
    + (loading ? ' loading="' + esc(loading) + '"' : '')
    + ' data-no-lazy="1">';
}

function previewHTML() {
  if (!state.product || state.step === 'collection') return '';
  var preview = core.selectPreview(state.product, state, manifest);
  var hero = preview.hero ? '<figure class="twxdb-preview twxdb-preview--hero">'
    + imageHTML(preview.hero.image, state.product.title, '', 'eager')
    + '<figcaption>' + esc(preview.hero.label) + '</figcaption></figure>' : '';
  var panel = preview.panel ? '<figure class="twxdb-preview twxdb-preview--panel">'
    + imageHTML(preview.panel.image, state.design && state.design.title)
    + '<figcaption>Panel-style reference shown at its original resolution.</figcaption></figure>' : '';
  return '<div class="twxdb-visuals">' + hero + panel + '</div>' + picksHTML();
}
~~~

10. Remove the preserved cacheGet/cacheSet/fetchJSON, loadList and loadProduct helpers. The controller's loadCached function above is the only cache/transport path; boot injects sessionStorage, fetch, LIST_URL and DETAIL_URL into that one controller.

11. Drive forward navigation through core.availableSteps and core.nextStep. Keep the history-backed Back behavior, but do not maintain a second independent step-availability implementation in app.js.

12. After every builder image loads, cap its inline maxWidth to naturalWidth pixels:

~~~js
root.addEventListener('load', function (event) {
  if (event.target.matches && event.target.matches('.twxdb img')) {
    event.target.style.maxWidth = event.target.naturalWidth + 'px';
  }
}, true);
~~~

13. Render selected color, window and glass images only as labeled swatch-only samples.
14. The form handler collects values and calls the controller's one tested submit path. Do not rebuild the payload, call funnel.submitLead directly, or mutate controller.state from the DOM adapter:

~~~js
function showLeadError() {
  btn.disabled = false;
  btn.textContent = BTN_LABEL;
  err.hidden = false;
  err.innerHTML = 'Something went wrong sending your specification. Call us at <a href="tel:'
    + PHONE.tel + '">' + PHONE.disp + '</a> and we\'ll take it from there.';
}
var formValues = {
  name: g('name'),
  phone: g('phone'),
  email: g('email'),
  zip: g('zip'),
  notes: g('notes'),
  website: g('website')
};
controller.submit(formValues).then(function (result) {
  if (!result.ok) {
    showLeadError();
    return;
  }
  render(true);
});
~~~

15. Use these exact truthful strings:

- Summary heading: Review your specification and get your free quote
- Summary intro: We will use these selections to prepare your free quote. Manufacturer images are references; Twins confirms the exact appearance before ordering.
- Sample caption: Manufacturer sample — final appearance is confirmed before ordering.
- Thank-you: Thanks — your specification was sent to our team. We will call you shortly to confirm options and prepare your quote.

Delete the preserved phrases Here's your door, your design is on its way and you'll see your exact door.

16. Keep the inline thank-you; never redirect from the builder itself.
17. Load the curated manifest from a generated global named window.TwinsDoorBuilderReferenceManifest, defaulting to { products: {} }.

- [ ] **Step 8: Create scoped styles.css**

Extract the existing .twxdb-prefixed styling, then replace the old stretching hero rules with these exact preview rules:

~~~css
.twxdb-visuals{display:grid;gap:18px;margin:0 0 20px;align-items:start}
.twxdb-preview{margin:0;background:#F2F5F7;border-radius:12px;padding:14px;text-align:center}
.twxdb img{display:block;width:auto;height:auto;max-width:100%}
.twxdb-preview img{margin:0 auto;border-radius:8px;background:#fff}
.twxdb-preview figcaption{font-size:12.5px;line-height:1.5;color:var(--tw-navy);opacity:.75;margin-top:8px}
.twxdb-preview--panel img{image-rendering:auto}
.twxdb-card img{margin:0 auto;max-height:220px;object-fit:contain}
.twxdb-chip img{margin:0 auto;max-width:46px;max-height:46px;object-fit:contain}
.twxdb-pick img{max-width:26px;max-height:26px;object-fit:contain}
@media(min-width:700px){.twxdb-visuals{grid-template-columns:minmax(0,2fr) minmax(180px,1fr)}}
~~~

Retain the existing mobile-first grid, minimum 44-pixel controls, form, dots, fallback and thank-you styles. Remove every builder-image width:100% rule and every fixed width that can exceed naturalWidth. The load handler enforces the final runtime cap for hero, panel, cards, chips, picks and all future .twxdb images.

- [ ] **Step 9: Run transport, source-contract and core tests**

~~~bash
node --test website/door-builder-remediation/tests/core.test.cjs website/door-builder-remediation/tests/transport.test.cjs website/door-builder-remediation/tests/app.test.cjs website/door-builder-remediation/tests/build-contract.test.cjs
~~~

Expected: 27 tests pass, 0 fail.

- [ ] **Step 10: Commit the browser-app slice**

~~~bash
git add website/door-builder-remediation/src/transport.js website/door-builder-remediation/src/app.js website/door-builder-remediation/src/styles.css website/door-builder-remediation/tests/transport.test.cjs website/door-builder-remediation/tests/app.test.cjs website/door-builder-remediation/tests/build-contract.test.cjs
git commit -m "feat(web): add honest high-resolution door-builder app"
~~~

---

### Task 5: Encode page changes as blocked contracts

**Files:**
- Create: website/door-builder-remediation/page-contracts/page-6065.json
- Create: website/door-builder-remediation/page-contracts/page-7073.json
- Create: website/door-builder-remediation/page-contracts/page-7129.json
- Create: website/door-builder-remediation/tests/page-contracts.test.cjs

**Interfaces:**
- Produces JSON contracts only; no Elementor import or mutation payload.
- Every contract exposes schemaVersion, site, pageId and mutationStatus.

- [ ] **Step 1: Write failing page-contract tests**

Create page-contracts.test.cjs:

~~~js
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
~~~

- [ ] **Step 2: Run the page-contract tests and verify RED**

~~~bash
node --test website/door-builder-remediation/tests/page-contracts.test.cjs
~~~

Expected: 4 failures because the contracts do not exist.

- [ ] **Step 3: Create page-6065.json**

~~~json
{
  "schemaVersion": 1,
  "site": "MAIN",
  "pageId": 6065,
  "mutationStatus": "blocked-pending-fresh-export",
  "requiredCounts": {
    "designYourDoorSection": 1,
    "builderShortcode": 1,
    "visibleHeading": 1
  },
  "requiredHeading": "Design Your Gallery Steel Door",
  "productDeepLink": "?product=12",
  "removalCandidate": {
    "elementorSectionId": "e26273a",
    "requiresExpectedOldExport": true
  },
  "preservedBuilderCandidate": {
    "elementorSectionId": "78da141",
    "presumeReplaceable": false
  }
}
~~~

- [ ] **Step 4: Create page-7073.json**

~~~json
{
  "schemaVersion": 1,
  "site": "MAIN",
  "pageId": 7073,
  "mutationStatus": "blocked-pending-fresh-export",
  "heroCopy": "Choose your collection and options, then send the specification to Twins for a free quote.",
  "supportingCopy": "Manufacturer photos and samples help you compare choices. Twins will confirm the exact appearance before ordering.",
  "successRedirect": "/door-builder/",
  "requiredErrorSelector": "[data-door-builder-error]",
  "requiredCounts": {
    "formSubmissionHandler": 1
  },
  "removeInlineOnsubmit": true,
  "removeLegacyGlobal": "twxDbSubmit",
  "redirectRequires": {
    "httpOk": true,
    "bodyOkTrue": true
  },
  "forbiddenClaims": [
    "upload your home",
    "try it on your house",
    "every option live",
    "exact render"
  ]
}
~~~

- [ ] **Step 5: Create page-7129.json**

~~~json
{
  "schemaVersion": 1,
  "site": "MAIN",
  "pageId": 7129,
  "mutationStatus": "blocked-pending-fresh-export",
  "h1": "Design Your New Garage Door",
  "requiredCounts": {
    "h1": 1,
    "builderShortcode": 1,
    "separateLeadGate": 0
  },
  "seoTitle": "Build Your Garage Door Specification | Twins Garage Doors",
  "metaDescription": "Compare Clopay collections, panel styles, colors, windows and glass, then send your garage door specification to Twins for a free quote."
}
~~~

- [ ] **Step 6: Run page-contract tests and verify GREEN**

~~~bash
node --test website/door-builder-remediation/tests/page-contracts.test.cjs
~~~

Expected: 4 tests pass, 0 fail.

- [ ] **Step 7: Commit the page-contract slice**

~~~bash
git add website/door-builder-remediation/page-contracts website/door-builder-remediation/tests/page-contracts.test.cjs
git commit -m "docs(web): define blocked door-builder page contracts"
~~~

---

### Task 6: Generate deterministic candidate artifacts

**Files:**
- Create: website/door-builder-remediation/src/wpcode-wrapper.php.tmpl
- Create: website/door-builder-remediation/scripts/build.mjs
- Modify: website/door-builder-remediation/tests/build-contract.test.cjs
- Generate: website/door-builder-remediation/dist/twins-door-builder-wpcode.php
- Generate: website/door-builder-remediation/dist/design-your-door-funnel.js
- Generate: website/door-builder-remediation/dist/local-harness.html
- Generate: website/door-builder-remediation/dist/verification-image.svg
- Generate: website/door-builder-remediation/dist/artifact-manifest.json

**Interfaces:**
- Consumes: core.js, app.js, funnel-submit.js, styles.css, reference-manifest.json and frozen product fixtures.
- Produces deterministic files and artifact-manifest SHA-256 entries.
- Produces build.mjs --check exit 0 only when committed dist matches generation.

- [ ] **Step 1: Extend build-contract tests before the generator exists**

Append:

~~~js
const childProcess = require('node:child_process');

test('generated WPCode is one inactive candidate wrapper', () => {
  const php = read('dist/twins-door-builder-wpcode.php');
  assert.equal((php.match(/add_shortcode\(\s*['"]twins_door_builder['"]/g) || []).length, 1);
  assert.equal((php.match(/id="twxdb"/g) || []).length, 1);
  assert.match(php, /CANDIDATE ONLY/);
});

test('local harness contains fixtures but no real lead endpoint', () => {
  const harness = read('dist/local-harness.html');
  assert.match(harness, /TwinsDoorBuilderFixtures/);
  assert.match(harness, /twxdbfail/);
  assert.doesNotMatch(harness, /twinsgaragedoors\.com\/wp-json\/twins\/v1\/door-builder/);
});

test('committed dist matches deterministic generation', () => {
  const script = path.resolve(__dirname, '..', 'scripts', 'build.mjs');
  const result = childProcess.spawnSync(process.execPath, [script, '--check'], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stdout + result.stderr);
});

test('generated funnel candidate boots and configures the current lead form', () => {
  const candidate = read('dist/design-your-door-funnel.js');
  assert.match(candidate, /querySelector\(\"\.twx-db\"\)/);
  assert.match(candidate, /TwinsDoorBuilderFunnel\.bindFunnel/);
  assert.match(candidate, /twinsgaragedoors\.com\/wp-json\/twins\/v1\/door-builder/);
  assert.match(candidate, /successUrl:\"\/door-builder\/\"/);
  assert.match(candidate, /\(833\) 833-2010/);
});
~~~

- [ ] **Step 2: Run only generated-artifact tests and verify RED**

~~~bash
node --test --test-name-pattern="generated WPCode|local harness|deterministic generation|generated funnel" website/door-builder-remediation/tests/build-contract.test.cjs
~~~

Expected: 4 failures because the template, script and dist do not exist.

- [ ] **Step 3: Create wpcode-wrapper.php.tmpl**

Use exactly one shortcode registration and these replacement tokens:

~~~php
/**
 * CANDIDATE ONLY — repository-generated door builder.
 * Live use requires fresh exports, verified staging, owner-signed grant,
 * CHATGPT_PROFILE_1 lease attestation and exact rollback evidence.
 */
add_shortcode( 'twins_door_builder', function () {
  $region = strpos( home_url(), '/wi' ) !== false ? 'wi' : 'main';
  ob_start(); ?>
<div id="twxdb" class="twxdb" data-region="<?php echo esc_attr( $region ); ?>" data-endpoint="https://twinsgaragedoors.com/wp-json/twins/v1/door-builder"></div>
<style id="twxdb-css">/*__TWXDB_CSS__*/</style>
<script>/*__TWXDB_MANIFEST__*/</script>
<script>/*__TWXDB_CORE__*/</script>
<script>/*__TWXDB_TRANSPORT__*/</script>
<script>/*__TWXDB_FUNNEL__*/</script>
<script>/*__TWXDB_APP__*/</script>
<?php return ob_get_clean();
} );
~~~

- [ ] **Step 4: Implement scripts/build.mjs**

The script must:

1. Resolve the package root from import.meta.url.
2. Read source files, frozen products-list.json and every product-<decimal-id>.json fixture; fail unless the 23 catalog IDs exactly equal the 23 detail IDs.
3. Replace each template token exactly once; fail if a token remains.
4. Build local-harness.html with an in-memory fetch shim:
   - list URL returns products-list;
   - every listed product detail URL returns its matching frozen product-<id>.json body;
   - POST calls append parsed bodies to window.__twxdbPosts;
   - leadFail=1 returns HTTP 500;
   - unlisted product IDs and all other network calls reject.
5. Build artifact-manifest.json with sorted SHA-256 records for the three non-manifest artifacts.
6. In normal mode write files.
7. In --check mode compare bytes and exit nonzero with the differing path; write nothing.

Use this fixed helper structure:

~~~js
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..');
const repo = path.resolve(root, '../..');
const dist = path.join(root, 'dist');
const check = process.argv.includes('--check');

function read(relative) {
  return fs.readFileSync(path.join(root, relative), 'utf8');
}

function replaceOnce(source, token, value) {
  if (source.split(token).length !== 2) throw new Error('token count: ' + token);
  return source.replace(token, value);
}

function sha256(value) {
  return crypto.createHash('sha256').update(value).digest('hex');
}

function stableJson(value) {
  return JSON.stringify(value, null, 2) + '\n';
}

function writeOrCheck(relative, value) {
  const target = path.join(dist, relative);
  if (check) {
    const current = fs.existsSync(target) ? fs.readFileSync(target, 'utf8') : '';
    if (current !== value) {
      console.error('generated artifact differs: ' + relative);
      process.exitCode = 1;
    }
    return;
  }
  fs.mkdirSync(dist, { recursive: true });
  fs.writeFileSync(target, value, 'utf8');
}

const css = read('src/styles.css');
const core = read('src/core.js');
const transport = read('src/transport.js');
const app = read('src/app.js');
const funnel = read('src/funnel-submit.js');
const referenceManifest = JSON.parse(read('assets/reference-manifest.json'));
const manifestScript = 'window.TwinsDoorBuilderReferenceManifest = '
  + JSON.stringify(referenceManifest) + ';';

let php = read('src/wpcode-wrapper.php.tmpl');
php = replaceOnce(php, '/*__TWXDB_CSS__*/', css);
php = replaceOnce(php, '/*__TWXDB_MANIFEST__*/', manifestScript);
php = replaceOnce(php, '/*__TWXDB_CORE__*/', core);
php = replaceOnce(php, '/*__TWXDB_TRANSPORT__*/', transport);
php = replaceOnce(php, '/*__TWXDB_FUNNEL__*/', funnel);
php = replaceOnce(php, '/*__TWXDB_APP__*/', app);
if (/\/\*__TWXDB_[A-Z_]+__\*\//.test(php)) {
  throw new Error('unreplaced WPCode token');
}

const fixtureRoot = path.join(
  repo,
  'docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot'
);
const listFixture = JSON.parse(fs.readFileSync(path.join(fixtureRoot, 'products-list.json'), 'utf8'));
const detailFiles = fs.readdirSync(fixtureRoot)
  .filter(function (name) { return /^product-[0-9]+\.json$/.test(name); })
  .sort(function (left, right) {
    return Number(left.match(/[0-9]+/)[0]) - Number(right.match(/[0-9]+/)[0]);
  });
const details = {};
detailFiles.forEach(function (name) {
  const detail = JSON.parse(fs.readFileSync(path.join(fixtureRoot, name), 'utf8'));
  details[String(detail.ProductId)] = detail;
});
const listIds = listFixture.map(function (item) { return String(item.ProductId); }).sort();
const detailIds = Object.keys(details).sort();
if (JSON.stringify(listIds) !== JSON.stringify(detailIds)) {
  throw new Error('catalog/detail fixture ID mismatch');
}
const fixturesScript = 'window.TwinsDoorBuilderFixtures = '
  + JSON.stringify({ list: listFixture, details: details }) + ';';

const funnelBoot = [
  '(function(){',
  'function start(){',
  'var form=document.querySelector(".twx-db");',
  'if(!form)return;',
  'var error=form.querySelector("[data-door-builder-error]");',
  'if(!error){error=document.createElement("p");error.hidden=true;error.setAttribute("data-door-builder-error","");form.appendChild(error);}',
  'TwinsDoorBuilderFunnel.bindFunnel(form,{',
  'fetchImpl:fetch,',
  'endpoint:"https://twinsgaragedoors.com/wp-json/twins/v1/door-builder",',
  'successUrl:"/door-builder/",',
  'errorMessage:"Something went wrong. Call Twins at (833) 833-2010."',
  '});',
  '}',
  'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",start);}else{start();}',
  '}());'
].join('\n');
const funnelCandidate = funnel.trimEnd() + '\n' + funnelBoot + '\n';

const harness = [
  '<!doctype html>',
  '<html lang="en"><head>',
  '<meta charset="utf-8">',
  '<meta name="viewport" content="width=device-width,initial-scale=1">',
  '<title>Twins door builder repository harness</title>',
  '<style>:root{--tw-navy:#022751;--tw-yellow:#FBBD04}body{font-family:Arial,sans-serif;margin:0;padding:24px}</style>',
  '</head><body>',
  '<div id="twxdb" class="twxdb" data-region="main" data-endpoint="/__harness__/lead"></div>',
  '<style id="twxdb-css">' + css + '</style>',
  '<script>' + fixturesScript + '</script>',
  '<script>',
  '(function(){',
  'window.__twxdbPosts=[];',
  'window.fetch=function(url,options){',
  'var href=String(url);',
  'if(options&&options.method==="POST"){',
  'var payload=JSON.parse(options.body||"{}");',
  'window.__twxdbPosts.push(payload);',
  'var fail=new URLSearchParams(location.search).get("leadFail")==="1";',
  'return Promise.resolve(new Response(JSON.stringify({ok:!fail}),{status:fail?500:200,headers:{"Content-Type":"application/json"}}));',
  '}',
  'if(href.indexOf("GetProductsList/GetProducts")>=0){',
  'var catalogFail=new URLSearchParams(location.search).get("twxdbfail")==="1";',
  'if(catalogFail)return Promise.resolve(new Response(JSON.stringify({error:"forced catalog failure"}),{status:503,headers:{"Content-Type":"application/json"}}));',
  'return Promise.resolve(new Response(JSON.stringify(window.TwinsDoorBuilderFixtures.list),{status:200,headers:{"Content-Type":"application/json"}}));',
  '}',
  'var match=href.match(/productId=([0-9]+)/);',
  'if(match&&window.TwinsDoorBuilderFixtures.details[match[1]]){',
  'return Promise.resolve(new Response(JSON.stringify(window.TwinsDoorBuilderFixtures.details[match[1]]),{status:200,headers:{"Content-Type":"application/json"}}));',
  '}',
  'return Promise.reject(new Error("harness blocked network"));',
  '};',
  '}());',
  '</script>',
  '<script>' + manifestScript + '</script>',
  '<script>' + core + '</script>',
  '<script>' + transport + '</script>',
  '<script>' + funnel + '</script>',
  '<script>' + app + '</script>',
  '</body></html>'
].join('\n') + '\n';

const outputs = {
  'design-your-door-funnel.js': funnelCandidate,
  'local-harness.html': harness,
  'twins-door-builder-wpcode.php': php.trimEnd() + '\n'
};

Object.keys(outputs).sort().forEach(function (name) {
  writeOrCheck(name, outputs[name]);
});

const records = Object.keys(outputs).sort().map(function (name) {
  return { path: name, sha256: sha256(outputs[name]) };
});
writeOrCheck('artifact-manifest.json', stableJson({
  schemaVersion: 1,
  artifacts: records
}));

if (!process.exitCode) {
  console.log(check ? 'generated artifacts match' : 'generated five artifacts');
}
~~~

- [ ] **Step 5: Generate dist**

~~~bash
node website/door-builder-remediation/scripts/build.mjs
~~~

Expected after the final-review amendment: five files written under dist and no network access.

- [ ] **Step 6: Run artifact and deterministic checks**

~~~bash
node --test website/door-builder-remediation/tests/build-contract.test.cjs
node website/door-builder-remediation/scripts/build.mjs --check
~~~

Expected: 8 build-contract tests pass; --check exits 0 with no differing path.

- [ ] **Step 7: Commit the generation slice**

~~~bash
git add website/door-builder-remediation/src/wpcode-wrapper.php.tmpl website/door-builder-remediation/scripts/build.mjs website/door-builder-remediation/tests/build-contract.test.cjs website/door-builder-remediation/dist
git commit -m "build(web): generate deterministic door-builder candidates"
~~~

---

### Task 7: Document, verify and hand off the repository package

**Files:**
- Create: website/door-builder-remediation/README.md
- Verify: all files from Tasks 1–6
- Do not modify: any docs/superpowers/backups historical artifact

**Interfaces:**
- Produces the operator-facing repository handoff.
- Does not produce authority or a deployment instruction that bypasses the private control plane.

- [ ] **Step 1: Write README.md**

The README must include:

- purpose and honest-reference promise;
- directory map;
- exact test/build/local-preview commands;
- artifact table explaining source versus generated candidate files;
- explicit statement that endpoint 7072 is unchanged;
- explicit statement that pages 6065/7073/7129 remain blocked pending fresh exports;
- exact future baseline list: 7127, 6765, 7072, 7050, 6755, 6065, 7073 and 7129;
- rollback rule: no deployment without byte-identical before exports and expected-old evidence;
- control-plane gates: verified staging, freeze clearance, owner grant, CHATGPT_PROFILE_1 lease and one attempt with no automatic retry;
- out-of-scope phone-leak, native-menu, global-chrome and KY work.

- [ ] **Step 2: Run the complete automated suite**

~~~bash
node --test website/door-builder-remediation/tests/*.test.cjs
~~~

Expected after the final-review amendment: 81 tests pass, 0 fail, 0 skipped.

- [ ] **Step 3: Verify deterministic output**

~~~bash
node website/door-builder-remediation/scripts/build.mjs --check
git diff --check
~~~

Expected: both exit 0.

- [ ] **Step 4: Verify historical preservation remains untouched**

~~~bash
git diff origin/main -- docs/superpowers/backups
~~~

Expected: no output.

- [ ] **Step 5: Run local browser verification**

Start:

~~~bash
python3 -m http.server 8123 --bind 127.0.0.1 --directory website/door-builder-remediation/dist
~~~

Use the browser-testing workflow against:

- http://localhost:8123/local-harness.html?product=12
- http://localhost:8123/local-harness.html?product=8
- http://localhost:8123/local-harness.html?product=16
- http://localhost:8123/local-harness.html?product=291
- http://localhost:8123/local-harness.html?product=12&leadFail=1
- http://localhost:8123/local-harness.html?twxdbfail=1

Verify at desktop and 390-pixel width:

- Gallery Steel deep link opens;
- product 8 skips directly from collection to summary because every option family is empty;
- product 16 skips windows and glass;
- product 291 skips color while retaining its available design, windows and glass steps;
- deterministic same-origin verification images load with `naturalWidth > 0`, while the reference hero remains labeled as inspiration;
- panel reference remains at intrinsic width;
- every .twxdb img satisfies rendered width <= naturalWidth + 1 pixel at desktop and 390-pixel width;
- no builder render contains img[src=""], an img without src, or the literal src="null" when a fixture option has no valid image;
- every displayed builder image uses the local verification fixture and preserves its original HTTPS `www.clopaydoor.com` URL in `data-source-url`;
- color/window/glass selections change samples and summary, not the reference-photo claim;
- full path, no-window path and skip-to-quote path work;
- leadFail=1 keeps the form and shows fallback;
- twxdbfail=1 renders catalog-failure fallback;
- window.__twxdbPosts records only harness-local payloads;
- no request reaches twinsgaragedoors.com.

Also create 23 same-origin hidden iframes, one for each key in window.TwinsDoorBuilderFixtures.details, each loading local-harness.html?product=<id>. Wait for every frame to leave its loading state and assert that none renders the catalog-failure fallback. Remove the iframes after the assertion. This is a harness-only browser smoke and still makes no network request.

Stop the HTTP server after verification.

- [ ] **Step 6: Run final repository checks**

~~~bash
git status --short
git diff --check
node --test website/door-builder-remediation/tests/*.test.cjs
node website/door-builder-remediation/scripts/build.mjs --check
~~~

Expected: README.md is the only intentional uncommitted file before its commit; all checks pass.

- [ ] **Step 7: Commit the handoff**

~~~bash
git add website/door-builder-remediation/README.md
git commit -m "docs(web): hand off tested door-builder remediation"
~~~

- [ ] **Step 8: Verify the final branch**

~~~bash
git status --short --branch
git log --oneline origin/main..HEAD
node --test website/door-builder-remediation/tests/*.test.cjs
node website/door-builder-remediation/scripts/build.mjs --check
~~~

Expected:

- clean status on codex/door-builder-remediation;
- design commit, implementation-plan commit, and seven implementation commits;
- complete test suite passes with zero failures;
- deterministic check exits 0;
- no WordPress or control-plane mutation occurred.

---

## Implementation review gates

After every task:

1. Verify the task-specific tests failed for the expected missing behavior before implementation.
2. Verify the same tests pass after minimal implementation.
3. Inspect git diff --check.
4. Confirm no historical backup changed.
5. Commit only the files listed for that task.

Before push or pull request:

1. Re-read docs/superpowers/specs/2026-07-12-door-builder-remediation-design.md.
2. Map every acceptance criterion to a passing test, generated artifact or README gate.
3. Run the full automated suite and deterministic check fresh.
4. Review the complete origin/main..HEAD diff for secrets, stale nonces, live endpoint use in the harness and accidental generated timestamps.
5. Request an independent code review.

## Explicit non-goals for this plan

- Do not fetch or export current WordPress objects.
- Do not alter page 6065, 7073 or 7129.
- Do not change snippets 7127, 6765, 7072, 7050 or 6755.
- Do not create a grant, acquire a lease, clear the production freeze or access WP Cloud.
- Do not submit any real lead.
- Do not fix WI/KY phone leakage, menus, global chrome or KY builder behavior.
