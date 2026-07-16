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
  const header = read('website/twins-brand-experience/components/header.php');
  const footer = read('website/twins-brand-experience/components/footer.php');
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

test('context-aware labels keep Illinois anchors truthful and qualify the Wisconsin cost guide', () => {
  const experience = read('website/twins-brand-experience/src/Experience.php');
  const service = read('website/twins-brand-experience/templates/service.php');
  const header = read('website/twins-brand-experience/components/header.php');
  const footer = read('website/twins-brand-experience/components/footer.php');
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
  const reducedMotion = css.match(/@media \(prefers-reduced-motion:\s*reduce\)\s*\{([\s\S]*?)\n\}/);
  assert.ok(reducedMotion, 'reduced-motion block is missing');
  assert.match(reducedMotion[1], /html\s*\{\s*scroll-behavior:\s*auto\s*!important;\s*\}/);
});
