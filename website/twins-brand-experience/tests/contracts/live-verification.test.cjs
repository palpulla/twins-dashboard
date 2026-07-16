const assert = require('node:assert/strict');
const cp = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const { pathToFileURL } = require('node:url');

const root = path.resolve(__dirname, '../..');
const crawlerPath = path.join(root, 'tools/crawl-staging.mjs');
const liveSpecPath = path.join(root, 'tests/browser/live-private-staging.spec.cjs');
const routes = [
  '/',
  '/il/',
  '/reviews/',
  '/contact-us/',
  '/careers/',
  '/garage-door-spring-repair/',
  '/clopay-garage-doors/',
  '/clopay-gallery-steel/?product=12',
  '/door-builder/',
];
const viewports = [1440, 1200, 900, 768, 390, 360, 320];

test('private staging crawler exports the exact bounded route and viewport matrix', async () => {
  assert.equal(fs.existsSync(crawlerPath), true, 'private staging crawler is missing');
  const crawler = await import(`${pathToFileURL(crawlerPath).href}?contract=${Date.now()}`);
  assert.deepEqual(crawler.ROUTES, routes);
  assert.deepEqual(crawler.VIEWPORTS, viewports);
  assert.equal(crawler.STAGE_URL, 'https://danielj140.sg-host.com/');
  assert.deepEqual(crawler.SCREENSHOT_VIEWPORTS, [1440, 768, 390, 320]);
});

test('crawler request policy permits only GET or HEAD to the fixed staging origin', async () => {
  const crawler = await import(`${pathToFileURL(crawlerPath).href}?policy=${Date.now()}`);
  assert.deepEqual(crawler.validateRequest('GET', 'https://danielj140.sg-host.com/reviews/', 'document'), {
    method: 'GET',
    origin: 'https://danielj140.sg-host.com',
  });
  assert.deepEqual(crawler.validateRequest('HEAD', 'https://danielj140.sg-host.com/'), {
    method: 'HEAD',
    origin: 'https://danielj140.sg-host.com',
  });
  assert.throws(() => crawler.validateRequest('POST', 'https://danielj140.sg-host.com/contact-us/'), /READ_ONLY_METHOD_REQUIRED/);
  assert.throws(() => crawler.validateRequest('GET', 'https://twinsgaragedoors.com/'), /SAME_ORIGIN_REQUIRED/);
  assert.throws(() => crawler.validateRequest('GET', 'https://embedded:secret@danielj140.sg-host.com/'), /REQUEST_URL_CREDENTIALS_FORBIDDEN/);
  assert.throws(() => crawler.validateRequest('GET', 'not a url'), /REQUEST_URL_INVALID/);
  assert.throws(() => crawler.validateRequest('GET', 'https://danielj140.sg-host.com/wp-admin/', 'document'), /WORDPRESS_CONTROL_PATH_FORBIDDEN/);
  assert.throws(() => crawler.validateRequest('GET', 'https://danielj140.sg-host.com/?rest_route=/wp/v2/users'), /DANGEROUS_QUERY_FORBIDDEN/);
  assert.throws(() => crawler.validateRequest('GET', 'https://danielj140.sg-host.com/unapproved/', 'document'), /DOCUMENT_ROUTE_NOT_ALLOWED/);
  assert.throws(() => crawler.validateRequest('GET', 'https://danielj140.sg-host.com/custom.php?do=delete', 'fetch'), /STATIC_ASSET_PATH_FORBIDDEN/);
  assert.throws(() => crawler.validateRequest('GET', 'https://danielj140.sg-host.com/wp-content/themes/astra/app.css?do=delete', 'stylesheet'), /STATIC_ASSET_QUERY_FORBIDDEN/);
  assert.doesNotThrow(() => crawler.validateRequest('GET', 'https://danielj140.sg-host.com/wp-content/themes/astra/app.css?ver=123', 'stylesheet'));
});

test('crawler final-location and contrast helpers are executable and fail closed', async () => {
  const crawler = await import(`${pathToFileURL(crawlerPath).href}?helpers=${Date.now()}`);
  assert.deepEqual(
    crawler.validateFinalLocation('https://danielj140.sg-host.com/clopay-gallery-steel/?product=12', '/clopay-gallery-steel/?product=12'),
    { pathAndQuery: '/clopay-gallery-steel/?product=12' },
  );
  assert.throws(
    () => crawler.validateFinalLocation('https://danielj140.sg-host.com/clopay-gallery-steel/?product=13', '/clopay-gallery-steel/?product=12'),
    /FINAL_ROUTE_MISMATCH/,
  );
  assert.equal(crawler.contrastRatio([0, 0, 0], [255, 255, 255]), 21);
  assert.equal(crawler.contrastRatio([255, 255, 255], [255, 196, 37]) < 3, true);
});

test('crawler skips a fully unconfigured local run and fails closed on partial secret configuration', () => {
  const unconfigured = { ...process.env };
  delete unconfigured.TWINS_STAGE_URL;
  delete unconfigured.TWINS_STAGE_USER;
  delete unconfigured.TWINS_STAGE_PASSWORD;
  const skipped = cp.spawnSync(process.execPath, [crawlerPath], {
    cwd: root,
    env: unconfigured,
    encoding: 'utf8',
  });
  assert.equal(skipped.status, 0, skipped.stderr);
  const skippedOutput = JSON.parse(skipped.stdout);
  assert.equal(skippedOutput.status, 'PRIVATE_STAGING_CRAWL_SKIPPED');
  assert.equal(skippedOutput.writeAuthority, false);
  assert.equal(skippedOutput.productionWriteAuthority, false);

  const partial = {
    ...unconfigured,
    TWINS_STAGE_URL: 'https://danielj140.sg-host.com/',
    TWINS_STAGE_USER: 'contract-secret-user',
  };
  const rejected = cp.spawnSync(process.execPath, [crawlerPath], {
    cwd: root,
    env: partial,
    encoding: 'utf8',
  });
  assert.equal(rejected.status, 2, rejected.stderr);
  assert.equal(`${rejected.stdout}\n${rejected.stderr}`.includes('contract-secret-user'), false);
  const rejectedOutput = JSON.parse(rejected.stdout);
  assert.equal(rejectedOutput.status, 'PRIVATE_STAGING_CONFIGURATION_INCOMPLETE');
  assert.equal(rejectedOutput.writeAuthority, false);
  assert.equal(rejectedOutput.productionWriteAuthority, false);

  const invalidArgument = cp.spawnSync(process.execPath, [crawlerPath, '--url=https://example.com/'], {
    cwd: root,
    env: unconfigured,
    encoding: 'utf8',
  });
  assert.equal(invalidArgument.status, 2, invalidArgument.stderr);
  assert.equal(JSON.parse(invalidArgument.stdout).status, 'INVALID_ARGUMENT');
});

test('live Playwright specification contains the same exact matrix and all required route gates', () => {
  const source = fs.readFileSync(liveSpecPath, 'utf8');
  const routeLiteral = source.match(/const routes = (\[[\s\S]*?\]);/);
  const viewportLiteral = source.match(/const viewports = (\[[\s\S]*?\]);/);
  assert.notEqual(routeLiteral, null);
  assert.notEqual(viewportLiteral, null);
  assert.deepEqual(Function(`"use strict"; return (${routeLiteral[1]});`)(), routes);
  assert.deepEqual(Function(`"use strict"; return (${viewportLiteral[1]});`)(), viewports);
  for (const required of [
    'x-robots-tag',
    '.twins-brand-header',
    '.twins-brand-footer',
    '.twins-overhaul-header',
    'document.documentElement.scrollWidth',
    '#twins-overhaul-main > section, main.twins-brand-page > section',
    'GET',
    'HEAD',
    'twins-brand-reviews-page',
    'twins-brand-careers-page',
    'twins-brand-direct-answer',
    'twins-brand-catalog-page',
    'data-twins-overhaul-builder',
    'tel:+18158002025',
    'BASIC_AUTH_CHALLENGE_MISSING',
    'FINAL_ROUTE_MISMATCH',
    'WORDPRESS_CONTROL_PATH_FORBIDDEN',
    'DANGEROUS_QUERY_FORBIDDEN',
    'DOCUMENT_ROUTE_NOT_ALLOWED',
    'STATIC_ASSET_PATH_FORBIDDEN',
    'STATIC_ASSET_QUERY_FORBIDDEN',
    'HEADER_SECTION_GAP',
    'SECTION_NOT_VIEWPORT_WIDE',
    'TEXT_CONTRAST_INVALID',
    'backgroundImage',
    'hasOpaqueOverlay',
    'fontSize >= 24',
    '4.5',
    '.twins-brand-kicker',
    'main span',
    'main li',
    'main dt',
    'main dd',
    "trace: 'off'",
    "screenshot: 'off'",
    'MOBILE_MENU_INVALID',
    'TWIN_MOTION_MISSING',
    'REDUCED_MOTION_INVALID',
  ]) {
    assert.match(source, new RegExp(required.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  assert.match(source, /page\.route\(['"]\*\*\/\*['"]/);
  assert.match(source, /route\.abort\(['"]blockedbyclient['"]\)/);
  assert.match(source, /READ_ONLY_METHOD_REQUIRED/);
  assert.match(source, /SAME_ORIGIN_REQUIRED/);
  assert.doesNotMatch(source, /page\.on\(['"]request['"]/);
  assert.match(source, /__blocked-post/);
  assert.match(source, /__blocked-off-origin/);
  assert.match(source, /__fixture-ledger/);
  for (const required of [
    'BASIC_AUTH_CHALLENGE_MISSING',
    'FINAL_ROUTE_MISMATCH',
    'WORDPRESS_CONTROL_PATH_FORBIDDEN',
    'DANGEROUS_QUERY_FORBIDDEN',
    'HEADER_SECTION_GAP',
    'SECTION_NOT_VIEWPORT_WIDE',
    'TEXT_CONTRAST_INVALID',
    'failure-screenshots',
    '.twins-brand-kicker',
    'main span',
    'main li',
    'main dt',
    'main dd',
  ]) {
    assert.match(fs.readFileSync(crawlerPath, 'utf8'), new RegExp(required));
  }
});
