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
