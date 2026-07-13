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

test('keeps an accepted lead successful when navigation throws', async () => {
  let requests = 0;
  const result = await funnel.submitLead({
    fetchImpl: async () => {
      requests += 1;
      return response(true, { ok: true });
    },
    endpoint: '/lead',
    payload: { name: 'Accepted' },
    redirect: () => { throw new Error('navigation unavailable'); }
  });
  assert.equal(requests, 1);
  assert.deepEqual(result, {
    ok: true,
    navigationOk: false,
    reason: 'redirect'
  });
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

test('bound funnel accepts only one submission through confirmed success', async () => {
  const fixture = boundForm();
  const redirects = [];
  let requests = 0;
  let resolveResponse;
  const pendingResponse = new Promise((resolve) => {
    resolveResponse = resolve;
  });
  funnel.bindFunnel(fixture.form, {
    fetchImpl: async () => {
      requests += 1;
      return pendingResponse;
    },
    endpoint: '/lead',
    successUrl: '/door-builder/',
    redirectImpl: (url) => redirects.push(url),
    errorMessage: 'Call Twins.'
  });

  const first = fixture.submit();
  const concurrent = fixture.submit();
  const requestsWhilePending = requests;
  resolveResponse(response(true, { ok: true }));
  await Promise.all([first, concurrent]);
  await fixture.submit();

  assert.equal(requestsWhilePending, 1);
  assert.equal(requests, 1);
  assert.deepEqual(redirects, ['/door-builder/']);
});

test('bound funnel remains accepted and locked when redirect throws', async () => {
  const fixture = boundForm();
  let requests = 0;
  funnel.bindFunnel(fixture.form, {
    fetchImpl: async () => {
      requests += 1;
      return response(true, { ok: true });
    },
    endpoint: '/lead',
    successUrl: '/door-builder/',
    redirectImpl: () => { throw new Error('navigation unavailable'); },
    errorMessage: 'Call Twins.'
  });

  await fixture.submit();
  await fixture.submit();

  assert.equal(requests, 1);
  assert.equal(fixture.button.disabled, true);
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

test('bound funnel allows retry after a failed submission', async () => {
  const fixture = boundForm();
  const redirects = [];
  let requests = 0;
  funnel.bindFunnel(fixture.form, {
    fetchImpl: async () => {
      requests += 1;
      return requests === 1
        ? response(false, { ok: true })
        : response(true, { ok: true });
    },
    endpoint: '/lead',
    successUrl: '/door-builder/',
    redirectImpl: (url) => redirects.push(url),
    errorMessage: 'Call Twins at (833) 833-2010.'
  });

  await fixture.submit();
  assert.equal(fixture.button.disabled, false);
  assert.equal(fixture.error.hidden, false);

  await fixture.submit();
  assert.equal(requests, 2);
  assert.deepEqual(redirects, ['/door-builder/']);
  assert.equal(fixture.error.hidden, true);
});
