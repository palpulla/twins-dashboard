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

function bootFixture() {
  const listeners = {};
  const mount = {
    innerHTML: '',
    getAttribute(name) {
      if (name === 'data-region') return 'main';
      if (name === 'data-endpoint') return '/lead';
      return null;
    },
    addEventListener(type, handler) {
      listeners[type] = handler;
    },
    contains() {
      return true;
    }
  };
  const documentRef = {
    querySelectorAll(selector) {
      return selector === '#twxdb' ? [mount] : [];
    }
  };
  return { documentRef, mount, listeners };
}

function interactiveBootFixture(fetchImpl, search = '?product=12') {
  const listeners = {};
  let currentHtml = '';
  let currentForm = null;

  function makeLeadForm() {
    const fields = {
      name: { value: 'Test Lead' },
      phone: { value: '608-555-0100' },
      email: { value: 'test@example.com' },
      zip: { value: '53703' },
      notes: { value: '' },
      website: { value: '' }
    };
    const button = { disabled: false, textContent: 'Get my free quote', isConnected: true };
    const error = { hidden: true, textContent: '', innerHTML: '', isConnected: true };
    const form = {
      isConnected: true,
      button,
      error,
      classList: { contains: (name) => name === 'twxdb-form' },
      querySelector(selector) {
        if (selector === 'button[type="submit"]') return button;
        if (selector === '.twxdb-err') return error;
        const match = /^\[name="([^"]+)"\]$/.exec(selector);
        return match ? fields[match[1]] || null : null;
      }
    };
    return form;
  }

  const mount = {
    get innerHTML() { return currentHtml; },
    set innerHTML(value) {
      if (currentForm) {
        currentForm.isConnected = false;
        currentForm.button.isConnected = false;
        currentForm.error.isConnected = false;
      }
      currentHtml = value;
      currentForm = /class="twxdb-form"/.test(value) ? makeLeadForm() : null;
    },
    get form() { return currentForm; },
    getAttribute(name) {
      if (name === 'data-region') return 'main';
      if (name === 'data-endpoint') return '/lead';
      return null;
    },
    addEventListener(type, handler) {
      listeners[type] = handler;
    },
    contains(element) {
      return element.isConnected !== false;
    },
    scrollIntoView() {}
  };
  const documentRef = {
    querySelectorAll(selector) {
      return selector === '#twxdb' ? [mount] : [];
    }
  };
  const windowRef = {
    TwinsDoorBuilderCore: core,
    TwinsDoorBuilderTransport: transport,
    TwinsDoorBuilderFunnel: funnel,
    fetch: fetchImpl,
    sessionStorage: memoryStorage(),
    location: { search }
  };
  return { documentRef, windowRef, mount, listeners };
}

function actionElement(action, index) {
  return {
    isConnected: true,
    closest() { return this; },
    getAttribute(name) {
      if (name === 'data-act') return action;
      if (name === 'data-i' && index !== undefined) return String(index);
      return null;
    }
  };
}

test('boot renders phone-only inert fallback when runtime dependencies are missing', () => {
  const fixture = bootFixture();
  const windowWithoutFetch = {
    TwinsDoorBuilderCore: core,
    TwinsDoorBuilderTransport: transport,
    TwinsDoorBuilderFunnel: funnel
  };
  assert.equal(app.boot(fixture.documentRef, windowWithoutFetch), false);
  assert.match(fixture.mount.innerHTML, /tel:\+18338332010/);
  assert.doesNotMatch(fixture.mount.innerHTML, /<form\b|<input\b|<textarea\b/i);
  assert.equal(Object.hasOwn(fixture.listeners, 'submit'), false);
});

test('failed dependency boot does not block a later valid boot attempt', () => {
  const fixture = bootFixture();
  assert.equal(app.boot(fixture.documentRef, {}), false);
  const windowRef = {
    TwinsDoorBuilderCore: core,
    TwinsDoorBuilderTransport: transport,
    TwinsDoorBuilderFunnel: funnel,
    fetch: async () => jsonResponse(true, []),
    sessionStorage: memoryStorage(),
    location: { search: '' }
  };
  assert.equal(app.boot(fixture.documentRef, windowRef), true);
  assert.equal(Object.hasOwn(fixture.listeners, 'submit'), true);
});

test('boot falls back from a throwing sessionStorage getter without poisoning the mount', async () => {
  const fixture = bootFixture();
  const windowRef = {
    TwinsDoorBuilderCore: core,
    TwinsDoorBuilderTransport: transport,
    TwinsDoorBuilderFunnel: funnel,
    fetch: async () => jsonResponse(true, []),
    location: { search: '' }
  };
  Object.defineProperty(windowRef, 'sessionStorage', {
    get() { throw new Error('storage denied'); }
  });

  assert.equal(app.boot(fixture.documentRef, windowRef), true);
  assert.equal(Object.hasOwn(fixture.listeners, 'submit'), true);
  await new Promise((resolve) => setImmediate(resolve));
  assert.match(fixture.mount.innerHTML, /Get your free door quote/);
});

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

function submissionController(postResponses) {
  let posts = 0;
  const controller = makeController(async (url, options = {}) => {
    if (options.method === 'POST') {
      const responseFactory = postResponses[Math.min(posts, postResponses.length - 1)];
      posts += 1;
      return responseFactory();
    }
    if (url === 'https://fixture/list') return jsonResponse(true, list);
    const id = new URL(url).searchParams.get('id');
    return jsonResponse(true, details[id]);
  });
  return { controller, posts: () => posts };
}

const leadValues = {
  name: 'Test',
  phone: '608-555-0100',
  email: 'test@example.com',
  zip: '53703',
  website: ''
};

test('controller reuses one in-flight submission promise and sends one POST', async () => {
  let resolvePost;
  const pending = new Promise((resolve) => { resolvePost = resolve; });
  const fixture = submissionController([() => pending]);
  await fixture.controller.load('?product=12');
  fixture.controller.skipToQuote();

  const first = fixture.controller.submit(leadValues);
  const concurrent = fixture.controller.submit(leadValues);
  assert.strictEqual(concurrent, first);
  assert.equal(fixture.posts(), 1);

  resolvePost(jsonResponse(true, { ok: true }));
  const result = await first;
  assert.equal(result.ok, true);
  assert.equal(fixture.posts(), 1);
});

test('controller freezes navigation while a submission is pending', async () => {
  let resolvePost;
  const pending = new Promise((resolve) => { resolvePost = resolve; });
  const fixture = submissionController([() => pending]);
  await fixture.controller.load('?product=12');
  fixture.controller.skipToQuote();
  const request = fixture.controller.submit(leadValues);

  fixture.controller.back();
  fixture.controller.choose('design', 0);
  fixture.controller.skipToQuote();
  await fixture.controller.selectProduct('13');
  assert.equal(fixture.controller.state.step, 'summary');
  assert.equal(fixture.controller.state.product.id, '12');
  assert.equal(fixture.controller.state.design, null);

  resolvePost(jsonResponse(true, { ok: true }));
  await request;
});

test('pending DOM navigation keeps the visible lead form mounted through failure feedback', async () => {
  let posts = 0;
  let resolvePost;
  const pendingPost = new Promise((resolve) => { resolvePost = resolve; });
  const fixture = interactiveBootFixture(async (url, options = {}) => {
    if (options.method === 'POST') {
      posts += 1;
      return pendingPost;
    }
    if (url.includes('GetProductsList')) return jsonResponse(true, list);
    const id = new URL(url).searchParams.get('productId');
    return jsonResponse(true, details[id]);
  });

  assert.equal(app.boot(fixture.documentRef, fixture.windowRef), true);
  await new Promise((resolve) => setImmediate(resolve));
  fixture.listeners.click({ target: actionElement('skip') });
  const form = fixture.mount.form;
  const button = form.button;
  const error = form.error;

  fixture.listeners.submit({ target: form, preventDefault() {} });
  assert.equal(button.disabled, true);
  fixture.listeners.click({ target: actionElement('back') });
  const visibleFormWhilePending = fixture.mount.form;

  resolvePost(jsonResponse(false, { ok: false }, 503));
  await new Promise((resolve) => setImmediate(resolve));

  assert.equal(posts, 1);
  assert.strictEqual(visibleFormWhilePending, form);
  assert.strictEqual(fixture.mount.form, form);
  assert.equal(form.isConnected, true);
  assert.equal(button.isConnected, true);
  assert.equal(error.isConnected, true);
  assert.equal(button.disabled, false);
  assert.equal(error.hidden, false);
  assert.match(error.innerHTML, /tel:\+18338332010/);
  assert.match(error.innerHTML, /\(833\) 833-2010/);
});

test('controller clears its submission guard after confirmed failure and permits retry', async () => {
  const fixture = submissionController([
    () => jsonResponse(false, { ok: false }, 503),
    () => jsonResponse(true, { ok: true })
  ]);
  await fixture.controller.load('?product=12');
  fixture.controller.skipToQuote();

  const failed = await fixture.controller.submit(leadValues);
  assert.equal(failed.ok, false);
  assert.equal(fixture.controller.state.submissionStatus, 'idle');
  const accepted = await fixture.controller.submit(leadValues);
  assert.equal(accepted.ok, true);
  assert.equal(fixture.posts(), 2);
});

test('controller permanently prevents repost and navigation after accepted success', async () => {
  const fixture = submissionController([() => jsonResponse(true, { ok: true })]);
  await fixture.controller.load('?product=12');
  fixture.controller.skipToQuote();
  const accepted = await fixture.controller.submit(leadValues);
  assert.equal(accepted.ok, true);
  assert.equal(fixture.controller.state.step, 'thanks');

  const repeated = await fixture.controller.submit(leadValues);
  fixture.controller.back();
  fixture.controller.skipToQuote();
  fixture.controller.choose('design', 0);
  await fixture.controller.selectProduct('13');

  assert.equal(repeated.ok, true);
  assert.equal(fixture.posts(), 1);
  assert.equal(fixture.controller.state.step, 'thanks');
  assert.equal(fixture.controller.state.product.id, '12');
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

test('harness image rendering uses the local fixture and preserves original source provenance', () => {
  const original = 'https://www.clopaydoor.com/images/reference.webp';
  globalThis.TwinsDoorBuilderVerificationImage = {
    path: './verification-image.svg',
    provenance: 'repository-generated deterministic local verification fixture'
  };
  try {
    const html = app.imageHTML(original, 'Reference', '', 'eager');
    assert.match(html, /src="\.\/verification-image\.svg"/);
    assert.match(html, /data-source-url="https:\/\/www\.clopaydoor\.com\/images\/reference\.webp"/);
    assert.match(html, /data-verification-provenance="repository-generated deterministic local verification fixture"/);
    assert.doesNotMatch(html, /src="https:\/\/www\.clopaydoor\.com/);
  } finally {
    delete globalThis.TwinsDoorBuilderVerificationImage;
  }
});

test('production image rendering keeps the approved Clopay source untouched', () => {
  const original = 'https://www.clopaydoor.com/images/reference.webp';
  const html = app.imageHTML(original, 'Reference', '', 'eager');
  assert.match(html, /src="https:\/\/www\.clopaydoor\.com\/images\/reference\.webp"/);
  assert.doesNotMatch(html, /verification-image|data-source-url|data-verification-provenance/);
});
