const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../../../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

test('portable navigation exposes the dedicated repair route in every market', () => {
  const adapter = read('website/staging-safety/mu-plugins/twins-staging-overhaul/adapters/BrandStagingAdapters.php');
  const registry = read('website/twins-brand-experience/src/PageContentRegistry.php');
  const home = read('website/twins-brand-experience/templates/home.php');
  const navData = read('website/twins-brand-experience/components/nav-data.php');
  const header = read('website/twins-brand-experience/components/header.php') + navData;
  const footer = read('website/twins-brand-experience/components/footer.php') + navData;
  const rendererHarness = read('website/twins-brand-experience/tests/php/renderer-contract-harness.php');
  const stagingHarness = read('website/staging-safety/tests/staging-brand-adapters-harness.php');

  for (const [market, target] of [
    ['main', '/garage-door-repair/'],
    ['wi', '/wi/garage-door-repair/'],
    ['ky', '/ky/garage-door-repair/'],
    ['il-preview', '/il/garage-door-repair/'],
  ]) {
    const start = adapter.indexOf(`'${market}' => [`);
    assert.notEqual(start, -1, `${market} route map is missing`);
    const end = adapter.indexOf('\n        ],', start);
    const block = adapter.slice(start, end);
    assert.match(block, new RegExp(`'repair'\\s*=>\\s*'${target.replaceAll('/', '\\/')}'`), `${market} repair route`);
    assert.match(stagingHarness, new RegExp(`route\\('repair', '${market}'\\).*${target.replaceAll('/', '\\/')}`));
  }

  assert.match(registry, /private const LINK_ROUTES\s*=\s*\[[\s\S]*?'repair'/);
  assert.match(home, /\$experience->route\(['"]repair['"],\s*\$marketKey\)/);
  for (const component of [header, footer]) {
    assert.match(component, /\[['"]Garage Door Repair['"],\s*['"]repair['"]\]/);
    assert.match(component, /\[['"]Spring Repair['"],\s*['"]spring-repair['"]\]/);
  }
  assert.match(rendererHarness, /'repair'\s*=>\s*'\/garage-door-repair\/'/);
});

// The service menu drifted once and nobody noticed for nine days.
// $twinsNavServiceAvailability is a hand-written statement of what each
// market's route table carries; route() is fail-closed, so a key listed there
// that the table lacks throws on render and any test catches it immediately.
// The silent direction is the other one: the r30 host capture (603a097f) gave
// wi and il-preview a 'maintenance-plans' route, the list was not updated, and
// the Protection Plan page stayed reachable from the homepage CTA while the
// Wisconsin header offered no way in. Nothing failed, because a menu that is
// missing an item renders perfectly. This pins both directions.
test('every market advertises exactly the services its own route table carries', () => {
  const navData = read('website/twins-brand-experience/components/nav-data.php');
  const adapter = read('website/staging-safety/mu-plugins/twins-staging-overhaul/adapters/BrandStagingAdapters.php');

  const slice = (source, opening) => {
    const start = source.indexOf(opening);
    assert.notEqual(start, -1, `${opening} is missing`);
    return source.slice(start, source.indexOf('\n];', start));
  };

  const catalog = [...slice(navData, '$twinsNavServiceCatalog = [')
    .matchAll(/\['[^']*',\s*'([^']+)'\]/g)].map(match => match[1]);
  assert.equal(catalog.length > 0, true, 'the service catalog is empty');

  const availabilityBlock = slice(navData, '$twinsNavServiceAvailability = [');

  // il-preview's 'spring-repair' route points at /il/garage-door-repair/, the
  // same page as 'repair', so Illinois withholds it on purpose rather than
  // listing one page twice. Any other divergence is drift, not a decision.
  const withheld = new Set(['il-preview\u0000spring-repair']);

  for (const market of ['main', 'wi', 'ky', 'il-preview']) {
    const listed = [...(availabilityBlock.match(new RegExp(`'${market}' => \\[([^\\]]*)\\]`)) ?? [])[1]
      .matchAll(/'([^']+)'/g)].map(match => match[1]);
    assert.equal(listed.length > 0, true, `${market} advertises no services at all`);

    const routeBlock = slice(adapter, `        '${market}' => [`);

    for (const key of catalog) {
      const routed = routeBlock.includes(`'${key}' =>`);
      const advertised = listed.includes(key);
      if (!routed) {
        assert.equal(advertised, false,
          `${market} advertises '${key}', which its route table does not carry: route() throws on render`);
        continue;
      }
      if (withheld.has(`${market}\u0000${key}`)) {
        assert.equal(advertised, false, `${market} now advertises '${key}'; drop it from the withheld set`);
        continue;
      }
      assert.equal(advertised, true,
        `${market} routes '${key}' but no menu offers it: the page is live and unreachable from the chrome`);
    }

    for (const key of listed) {
      assert.equal(catalog.includes(key), true,
        `${market} advertises '${key}', which is not in $twinsNavServiceCatalog and so renders nothing`);
    }
  }
});

test('context-aware labels keep Illinois anchors truthful and qualify the Wisconsin cost guide', () => {
  const experience = read('website/twins-brand-experience/src/Experience.php');
  const service = read('website/twins-brand-experience/templates/service.php');
  const navData = read('website/twins-brand-experience/components/nav-data.php');
  const header = read('website/twins-brand-experience/components/header.php') + navData;
  const footer = read('website/twins-brand-experience/components/footer.php') + navData;
  const rendererHarness = read('website/twins-brand-experience/tests/php/renderer-contract-harness.php');
  const stagingHarness = read('website/staging-safety/tests/staging-brand-adapters-harness.php');

  assert.match(experience, /public function contextualRouteLabel\(string \$routeKey, string \$marketKey, string \$defaultLabel\): string/);
  assert.match(experience, /'il-preview'\s*=>\s*\[[\s\S]*?'spring-repair'\s*=>\s*'Garage Door Repair'[\s\S]*?'opener-repair'\s*=>\s*'Garage Door Openers'/);
  assert.match(service, /contextualRouteLabel\(\$link\[['"]route['"]\],\s*\$marketKey,\s*\$link\[['"]label['"]\]\)/);
  for (const component of [header, footer]) {
    assert.match(component, /contextualRouteLabel\(\$routeKey,\s*\$marketKey,\s*\$label\)/);
    assert.match(component, /Wisconsin Garage Door Cost Guide/);
  }
  assert.match(rendererHarness, /Illinois service links retained the misleading Spring Repair label/);
  assert.match(rendererHarness, /Illinois service links omitted the Garage Door Openers destination label/);
  assert.match(stagingHarness, /Illinois header retained the misleading Spring Repair label/);
  assert.match(stagingHarness, /Illinois header omitted the Garage Door Openers destination label/);
});

test('reduced-motion CSS explicitly disables smooth scrolling on the document element', () => {
  const css = read('website/twins-brand-experience/assets/css/twins-brand.css');
  // r30 added earlier, smaller reduced-motion blocks; assert the guarantee
  // holds in at least one of them rather than pinning block order.
  const reducedMotionBlocks = [...css.matchAll(/@media \(prefers-reduced-motion:\s*reduce\)\s*\{([\s\S]*?)\n\}/g)];
  assert.ok(reducedMotionBlocks.length > 0, 'reduced-motion block is missing');
  assert.ok(
    reducedMotionBlocks.some(block => /html\s*\{\s*scroll-behavior:\s*auto\s*!important;\s*\}/.test(block[1])),
    'no reduced-motion block disables smooth scrolling on the document element',
  );
});
