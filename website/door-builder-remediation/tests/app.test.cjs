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
