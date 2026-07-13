const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const childProcess = require('node:child_process');
const crypto = require('node:crypto');
const vm = require('node:vm');
const { pathToFileURL } = require('node:url');

const LIST_URL = 'https://www.clopaydoor.com/api/v2/GetProductsList/GetProducts?productType=Residential';
const DETAIL_URL = 'https://www.clopaydoor.com/api/v2/GetProductDetails/GetProductData?productId=';
const LEAD_PATH = '/__harness__/lead';

function read(relative) {
  const file = path.resolve(__dirname, '..', relative);
  return fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '';
}

function frozenFixtures() {
  const fixtureRoot = path.resolve(
    __dirname,
    '../../..',
    'docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot'
  );
  const list = JSON.parse(fs.readFileSync(path.join(fixtureRoot, 'products-list.json'), 'utf8'));
  const detailFixtures = fs.readdirSync(fixtureRoot)
    .filter((name) => /^product-[0-9]+\.json$/.test(name))
    .map((name) => ({
      name,
      detail: JSON.parse(fs.readFileSync(path.join(fixtureRoot, name), 'utf8'))
    }));
  return { list, detailFixtures };
}

function buildModule() {
  const script = path.resolve(__dirname, '..', 'scripts', 'build.mjs');
  return import(pathToFileURL(script).href);
}

function harnessRuntime(search = '') {
  const harness = read('dist/local-harness.html');
  const scripts = Array.from(harness.matchAll(/<script>([\s\S]*?)<\/script>/g), (match) => match[1]);
  const context = { location: { search }, Response, URLSearchParams };
  context.window = context;
  vm.runInNewContext(scripts.find((script) => script.includes('TwinsDoorBuilderFixtures =')), context);
  vm.runInNewContext(scripts.find((script) => script.includes('window.__twxdbPosts=[]')), context);
  return context;
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
  const stretchingImage = /\.twxdb[^{}]*img\{[^}]*(?<![-\w])width\s*:\s*100%/;
  assert.ok(css.trim().length > 0, 'CSS source missing');
  assert.match(
    css,
    /\.twxdb img\{[^}]*width:auto[^}]*height:auto[^}]*max-width:min\(100%,var\(--twxdb-natural-width,100%\)\)/
  );
  assert.match('.twxdb-card img{width:100%}', stretchingImage);
  assert.doesNotMatch('.twxdb-card img{max-width:100%}', stretchingImage);
  assert.doesNotMatch(css, stretchingImage);
});

test('intrinsic image widths preserve responsive and component CSS caps', () => {
  const app = read('src/app.js');
  const css = read('src/styles.css');
  assert.match(
    app,
    /style\.setProperty\('--twxdb-natural-width', event\.target\.naturalWidth \+ 'px'\)/
  );
  assert.doesNotMatch(app, /style\.maxWidth/);
  assert.match(
    css,
    /\.twxdb-chip img\{[^}]*max-width:min\(46px,100%,var\(--twxdb-natural-width,46px\)\)/
  );
  assert.match(
    css,
    /\.twxdb-chip--wide img\{[^}]*max-width:min\(100%,var\(--twxdb-natural-width,100%\)\)/
  );
  assert.match(
    css,
    /\.twxdb-pick img\{[^}]*max-width:min\(26px,100%,var\(--twxdb-natural-width,26px\)\)/
  );
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

test('deterministic check rejects an extra stale regular dist entry', () => {
  const script = path.resolve(__dirname, '..', 'scripts', 'build.mjs');
  const stale = path.resolve(__dirname, '..', 'dist', 'stale-generated-entry.txt');
  fs.writeFileSync(stale, 'stale\n', 'utf8');
  try {
    const result = childProcess.spawnSync(process.execPath, [script, '--check'], { encoding: 'utf8' });
    assert.notEqual(result.status, 0, result.stdout + result.stderr);
    assert.match(result.stdout + result.stderr, /unexpected generated entry: stale-generated-entry\.txt/);
  } finally {
    fs.unlinkSync(stale);
  }
});

test('dist entry validation requires the exact expected regular-file set', async () => {
  const { validateDistEntries } = await buildModule();
  const regular = (name) => ({ name, isFile: () => true });
  const nonRegular = (name) => ({ name, isFile: () => false });
  assert.deepEqual(validateDistEntries(
    [regular('expected.txt'), nonRegular('stale-directory')],
    ['expected.txt']
  ), ['unexpected generated entry: stale-directory']);
  assert.deepEqual(validateDistEntries(
    [nonRegular('expected.txt')],
    ['expected.txt']
  ), ['generated artifact is not a regular file: expected.txt']);
  assert.deepEqual(validateDistEntries(
    [regular('expected.txt')],
    ['expected.txt']
  ), []);
});

test('generated funnel candidate boots and configures the current lead form', () => {
  const candidate = read('dist/design-your-door-funnel.js');
  assert.match(candidate, /querySelector\(\"\.twx-db\"\)/);
  assert.match(candidate, /TwinsDoorBuilderFunnel\.bindFunnel/);
  assert.match(candidate, /twinsgaragedoors\.com\/wp-json\/twins\/v1\/door-builder/);
  assert.match(candidate, /successUrl:\"\/door-builder\/\"/);
  assert.match(candidate, /\(833\) 833-2010/);
});

test('page 7073 contract drives matching source and generated funnel DOM bindings', async () => {
  const contract = JSON.parse(read('page-contracts/page-7073.json'));
  const source = require('../src/funnel-submit.js');
  assert.deepEqual(source.DOM_BINDINGS, contract.requiredDomBindings);

  async function exerciseBindings(api) {
    const bindings = contract.requiredDomBindings;
    const values = Object.fromEntries(Object.entries(bindings.fieldSelectors).map(([name, selector]) => (
      [selector, { value: name === 'website' ? '' : name }]
    )));
    const button = { disabled: false };
    const error = { hidden: true, textContent: '' };
    const selectors = [];
    const attributes = [];
    let submitHandler;
    const form = {
      addEventListener(type, handler) {
        if (type === 'submit') submitHandler = handler;
      },
      querySelector(selector) {
        selectors.push(selector);
        if (selector === bindings.submitButtonSelector) return button;
        if (selector === bindings.errorSelector) return error;
        return values[selector] || null;
      },
      getAttribute(name) {
        attributes.push(name);
        return name === bindings.regionAttribute ? 'main' : null;
      }
    };
    api.bindFunnel(form, {
      fetchImpl: async () => ({ ok: true, json: async () => ({ ok: true }) }),
      endpoint: '/lead',
      successUrl: '/door-builder/',
      redirectImpl() {},
      errorMessage: 'Call Twins.'
    });
    await submitHandler({ preventDefault() {} });
    assert.deepEqual(new Set(selectors), new Set([
      bindings.submitButtonSelector,
      bindings.errorSelector,
      ...Object.values(bindings.fieldSelectors)
    ]));
    assert.deepEqual(attributes, [bindings.regionAttribute]);
  }

  await exerciseBindings(source);

  const candidate = read('dist/design-your-door-funnel.js');
  const queried = [];
  const context = {
    document: {
      readyState: 'complete',
      querySelector(selector) {
        queried.push(selector);
        return null;
      },
      addEventListener() {},
      createElement() { throw new Error('form is absent'); }
    },
    fetch: async () => { throw new Error('not called'); },
    location: { assign() {} }
  };
  context.globalThis = context;
  vm.runInNewContext(candidate, context);
  assert.equal(queried[0], contract.requiredDomBindings.formSelector);
  assert.equal(
    JSON.stringify(context.TwinsDoorBuilderFunnel.DOM_BINDINGS),
    JSON.stringify(contract.requiredDomBindings)
  );
  await exerciseBindings(context.TwinsDoorBuilderFunnel);
});

test('fixture validator requires exactly 23 catalog entries', async () => {
  const { validateCatalogFixtures } = await buildModule();
  const fixtures = frozenFixtures();
  assert.throws(
    () => validateCatalogFixtures(fixtures.list.slice(0, 22), fixtures.detailFixtures),
    /exactly 23 catalog entries/
  );
});

test('fixture validator rejects 24 detail files with a duplicate body ID', async () => {
  const { validateCatalogFixtures } = await buildModule();
  const fixtures = frozenFixtures();
  const duplicate = structuredClone(fixtures.detailFixtures[0]);
  duplicate.name = 'product-999.json';
  assert.throws(
    () => validateCatalogFixtures(fixtures.list, fixtures.detailFixtures.concat(duplicate)),
    /exactly 23 detail files/
  );
});

test('fixture validator requires 23 unique detail body IDs', async () => {
  const { validateCatalogFixtures } = await buildModule();
  const fixtures = frozenFixtures();
  const details = structuredClone(fixtures.detailFixtures);
  details[22].detail.ProductId = details[0].detail.ProductId;
  assert.throws(
    () => validateCatalogFixtures(fixtures.list, details),
    /exactly 23 unique detail body IDs/
  );
});

test('fixture validator rejects filename and body ProductId mismatch', async () => {
  const { validateCatalogFixtures } = await buildModule();
  const fixtures = frozenFixtures();
  const details = structuredClone(fixtures.detailFixtures);
  details[0].detail.ProductId = '999';
  assert.throws(
    () => validateCatalogFixtures(fixtures.list, details),
    /filename\/body ProductId mismatch/
  );
});

test('local harness CSP permits only same-origin images and blocks external traffic', () => {
  const harness = read('dist/local-harness.html');
  assert.match(
    harness,
    /<meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src 'self'; frame-src 'self'; connect-src 'none'; form-action 'none'; base-uri 'none'; object-src 'none'">/
  );
  assert.match(harness, /<link rel="icon" href="\.\/verification-image\.svg" type="image\/svg\+xml">/);
  assert.match(harness, /TwinsDoorBuilderVerificationImage/);
  assert.match(harness, /data-source-url/);
  assert.match(harness, /repository-generated deterministic local verification fixture/);
  assert.match(harness, /https:\/\/www\.clopaydoor\.com\/images\//);
});

test('generated verification image is deterministic, hash-backed and provenance-described', () => {
  const image = fs.readFileSync(path.resolve(__dirname, '..', 'dist', 'verification-image.svg'));
  const manifest = JSON.parse(read('dist/artifact-manifest.json'));
  const artifact = manifest.artifacts.find((entry) => entry.path === 'verification-image.svg');
  const fixture = manifest.verificationFixtures.find((entry) => entry.path === 'verification-image.svg');
  assert.match(image.toString('utf8'), /^<svg[^>]+width="960"[^>]+height="540"/);
  assert.equal(artifact.sha256, crypto.createHash('sha256').update(image).digest('hex'));
  assert.equal(fixture.sha256, artifact.sha256);
  assert.equal(fixture.provenance, 'repository-generated deterministic local verification fixture');
  assert.equal(fixture.productionUse, false);
});

test('production candidates do not enable the local verification fixture', () => {
  const wpcode = read('dist/twins-door-builder-wpcode.php');
  const funnel = read('dist/design-your-door-funnel.js');
  assert.doesNotMatch(wpcode, /window\.TwinsDoorBuilderVerificationImage\s*=/);
  assert.doesNotMatch(wpcode, /verification-image\.svg/);
  assert.doesNotMatch(funnel, /verification-image\.svg|data-source-url/);
});

test('README binds the preview server to loopback and documents image-enabled verification', () => {
  const readme = read('README.md');
  assert.match(
    readme,
    /python3 -m http\.server 8123 --bind 127\.0\.0\.1 --directory website\/door-builder-remediation\/dist/
  );
  assert.match(readme, /verification-image\.svg/);
  assert.match(readme, /naturalWidth > 0/);
  assert.match(readme, /five regular files in `dist\/`/);
});

test('local harness accepts only exact frozen GET routes', async () => {
  const runtime = harnessRuntime();
  const list = await runtime.fetch(LIST_URL);
  const detail = await runtime.fetch(DETAIL_URL + '330');
  assert.equal(list.status, 200);
  assert.equal((await list.json()).length, 23);
  assert.equal(detail.status, 200);
  assert.equal(String((await detail.json()).ProductId), '330');

  for (const [url, options] of [
    [LIST_URL + '&unexpected=1'],
    ['https://example.invalid/?next=' + encodeURIComponent(LIST_URL)],
    [DETAIL_URL + '330&unexpected=1'],
    [DETAIL_URL + '330/path'],
    ['https://example.invalid/details?productId=330'],
    [DETAIL_URL + '999'],
    [LIST_URL, { method: 'DELETE' }],
    [DETAIL_URL + '330', { method: 'POST' }]
  ]) {
    await assert.rejects(runtime.fetch(url, options), /harness blocked network/);
  }
});

test('local harness accepts POST only at the exact lead path', async () => {
  const runtime = harnessRuntime();
  const response = await runtime.fetch(LEAD_PATH, {
    method: 'POST',
    body: JSON.stringify({ name: 'Offline test' })
  });
  assert.equal(response.status, 200);
  assert.equal(JSON.stringify(runtime.__twxdbPosts), JSON.stringify([{ name: 'Offline test' }]));

  for (const [url, options] of [
    ['/__harness__/lead?unexpected=1', { method: 'POST', body: '{}' }],
    ['https://example.invalid/__harness__/lead', { method: 'POST', body: '{}' }],
    [LEAD_PATH, { method: 'post', body: '{}' }],
    [LEAD_PATH, { method: 'PUT', body: '{}' }],
    [LEAD_PATH]
  ]) {
    await assert.rejects(runtime.fetch(url, options), /harness blocked network/);
  }
});

test('UTF-8 artifact comparison rejects byte-different decoded text', async () => {
  const { sameUtf8Bytes } = await buildModule();
  const malformed = Buffer.from([0xc0]);
  assert.equal(malformed.toString('utf8'), '\uFFFD');
  assert.equal(sameUtf8Bytes(malformed, '\uFFFD'), false);
  assert.equal(sameUtf8Bytes(Buffer.from('\uFFFD', 'utf8'), '\uFFFD'), true);
});
