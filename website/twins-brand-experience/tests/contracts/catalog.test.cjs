const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../../../..');
const read = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8');

function functionBody(contents, name) {
  const start = contents.indexOf(`function ${name}`);
  assert.notEqual(start, -1, `${name} is missing`);
  const brace = contents.indexOf('{', start);
  assert.notEqual(brace, -1, `${name} has no body`);

  let depth = 0;
  let quote = null;
  let escaped = false;
  for (let index = brace; index < contents.length; index += 1) {
    const character = contents[index];
    if (quote) {
      if (escaped) escaped = false;
      else if (character === '\\') escaped = true;
      else if (character === quote) quote = null;
      continue;
    }
    if (character === "'" || character === '"') quote = character;
    else if (character === '{') depth += 1;
    else if (character === '}') {
      depth -= 1;
      if (depth === 0) return contents.slice(brace + 1, index);
    }
  }
  assert.fail(`${name} body is not balanced`);
}

test('catalog template is a portable contrast-safe frozen manufacturer reference', () => {
  const template = read('website/twins-brand-experience/templates/catalog.php');

  assert.match(template, /twins-brand-catalog-hero/);
  assert.match(template, /manufacturer reference/i);
  assert.equal((template.match(/<h1/g) || []).length, 1);
  assert.doesNotMatch(template, /iframe|https?:\/\//);
  assert.match(template, /Design This Door/);
  assert.match(template, /Request a Quote/);
  assert.match(template, /Private staging preview/);
  assert.match(template, /array_slice\(\$product\[['"]gallery['"]\],\s*0,\s*3\)/);
});

test('catalog rendering accepts only the fixed view assembled from the current proven path', () => {
  const experience = read('website/twins-brand-experience/src/Experience.php');
  const renderers = read('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  const catalogView = functionBody(renderers, 'twins_overhaul_catalog_view');
  const classified = functionBody(renderers, 'twins_overhaul_render_classified_content');

  assert.match(experience, /renderCatalog\(array \$context, array \$catalogView\): string/);
  assert.match(experience, /return \$this->render\(['"]catalog['"], \$context, \$catalogView\)/);
  assert.match(experience, /strpos\(\$path,\s*['"]\/\/['"]\)\s*!==\s*false/);
  assert.match(experience, /strpos\(\$path,\s*['"]%['"]\)\s*!==\s*false/);
  assert.match(catalogView, /twins_overhaul_builder_catalog\(\)/);
  assert.match(catalogView, /twins_overhaul_current_request_path\(\)/);
  assert.match(catalogView, /\$context\[['"]path['"]\]/);
  assert.match(catalogView, /catalog request path does not match the proven context/i);
  assert.match(catalogView, /'mode'\s*=>\s*'overview'/);
  assert.match(catalogView, /'mode'\s*=>\s*'product'/);
  assert.match(catalogView, /\$builderPath\s*=\s*\$region\[['"]base['"]\][\s\S]*?'builderPath'\s*=>\s*\$builderPath/);
  assert.doesNotMatch(catalogView, /\$context\[['"](?:productId|product|id|url|href)['"]\]/);

  assert.match(classified, /\$classification === 'catalog-preserve'[\s\S]*twins_overhaul_catalog_view\(\$context\)/);
  assert.match(classified, /\$classification === 'catalog-preserve'[\s\S]*renderCatalog\(/);
  const catalogBranch = classified.match(/\} elseif \(\$classification === 'catalog-preserve'\) \{([\s\S]*?)\} elseif/);
  assert.ok(catalogBranch, 'catalog renderer branch is missing');
  assert.doesNotMatch(catalogBranch[1], /twins_overhaul_wrap_preserved_content|twins_overhaul_make_preserved_forms_inert/);
});

test('every catalog route maps to one exact frozen product in fixed order', () => {
  const renderers = read('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  const routes = functionBody(renderers, 'twins_overhaul_catalog_routes');
  const expected = [
    ['/clopay-canyon-ridge-elements/', '330'],
    ['/clopay-canyon-ridge-chevron/', '320'],
    ['/clopay-canyon-ridge-carriage-house-5-layer/', '30'],
    ['/clopay-canyon-ridge-carriage-house-4-layer/', '29'],
    ['/clopay-canyon-ridge-louver/', '240'],
    ['/clopay-canyon-ridge-modern/', '26'],
    ['/clopay-modern-steel/', '170'],
    ['/clopay-modern-steel-ultra-grain-plank/', '340'],
    ['/clopay-gallery-steel/', '12'],
    ['/clopay-avante/', '16'],
    ['/clopay-avante-sleek/', '290'],
    ['/clopay-vertistack-avante/', '370'],
    ['/clopay-bridgeport-steel/', '250'],
    ['/clopay-bridgeport-inlay/', '380'],
    ['/clopay-coachman/', '11'],
    ['/clopay-grand-harbor/', '27'],
    ['/clopay-reserve-wood-extira/', '291'],
    ['/clopay-reserve-wood-custom/', '8'],
    ['/clopay-reserve-wood-limited-edition/', '10'],
    ['/clopay-reserve-wood-modern/', '25'],
    ['/clopay-reserve-wood-semi-custom/', '9'],
    ['/clopay-classic-collection/', '13'],
    ['/clopay-classic-wood/', '23'],
  ];

  assert.equal((routes.match(/=>/g) || []).length, expected.length);
  let previous = -1;
  for (const [requestPath, productId] of expected) {
    const marker = `'${requestPath}' => '${productId}'`;
    const position = routes.indexOf(marker);
    assert.notEqual(position, -1, marker);
    assert.ok(position > previous, `${marker} is outside the fixed product order`);
    previous = position;
  }
  for (const [requestPath, productId] of [
    ['/clopay-modern-steel/', '170'],
    ['/clopay-gallery-steel/', '12'],
    ['/clopay-classic-collection/', '13'],
  ]) assert.match(renderers, new RegExp(`'${requestPath.replaceAll('/', '\\/')}'\\s*=>\\s*'${productId}'`));
});

test('catalog, cost, and builder use only scoped portable family assets', () => {
  const familyPath = path.join(root, 'website/twins-brand-experience/assets/css/twins-brand-families.css');
  const builderPath = path.join(root, 'website/twins-brand-experience/assets/js/twins-builder.js');
  assert.equal(fs.existsSync(familyPath), true, 'portable family stylesheet is missing');
  assert.equal(fs.existsSync(builderPath), true, 'portable builder script is missing');

  const families = fs.readFileSync(familyPath, 'utf8');
  const builder = fs.readFileSync(builderPath, 'utf8');
  const brand = read('website/twins-brand-experience/assets/js/twins-brand.js');
  const renderers = read('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  const legacyFamily = functionBody(renderers, 'twins_overhaul_uses_legacy_family_assets');
  const enqueue = functionBody(renderers, 'twins_overhaul_enqueue_assets');

  for (const routeClass of [
    'twins-brand-route-cost-madison',
    'twins-brand-route-cost-milwaukee',
    'twins-brand-route-builder',
    'twins-brand-route-catalog-preserve',
  ]) assert.match(families, new RegExp(routeClass));
  for (const marker of [
    'twins-cost-hero',
    'twins-cost-zip',
    'twins-builder__progress',
    'twins-builder__collection-grid',
    'twins-brand-catalog-hero',
    'twins-brand-catalog-card',
  ]) assert.match(families, new RegExp(marker));
  assert.doesNotMatch(families, /@font-face|\.twx-|twins-overhaul-menu|twins-overhaul-application|twins-overhaul-campaign/);
  assert.doesNotMatch(families, /body\.twins-overhaul-preview\s+\./);

  assert.match(builder, /BUILDER_PRODUCT_ORDER/);
  assert.match(builder, /BUILDER_LOCAL_IMAGE/);
  assert.match(builder, /data-twins-overhaul-builder/);
  assert.match(builder, /data-builder-enhanced/);
  assert.match(builder, /Manufacturer reference only/);
  assert.doesNotMatch(
    builder,
    /\b(?:fetch|XMLHttpRequest|WebSocket|EventSource|sendBeacon|requestSubmit|localStorage|sessionStorage|indexedDB)\b|\.submit\s*\(/,
  );
  assert.doesNotMatch(builder, /ZIP_ROUTES|initZip|initMenu|initPreservedForms|initReveal|TwinsOverhaulPreview/);

  assert.match(brand, /'537'\s*:\s*'\/wi\/garage-door-cost-in-madison-wi\/'/);
  assert.match(brand, /'531'\s*:\s*'\/wi\/garage-door-cost-in-milwaukee-wi\/'/);
  assert.match(brand, /'532'\s*:\s*'\/wi\/garage-door-cost-in-milwaukee-wi\/'/);
  assert.match(brand, /ZIP_FALLBACK\s*=\s*'\/wi\/contact-us\/'/);
  assert.match(brand, /data-twins-overhaul-zip/);
  assert.match(brand, /data-twins-zip-input/);
  assert.match(brand, /data-twins-zip-route/);
  assert.match(brand, /data-twins-zip-status/);
  assert.match(brand, /\^\\d\{5\}\$/);

  assert.match(legacyFamily, /return\s+\$classification\s*===\s*['"]campaign-preserve['"]/);
  assert.doesNotMatch(legacyFamily, /cost-madison|cost-milwaukee|builder|catalog-preserve/);
  assert.match(enqueue, /twins-brand-families/);
  assert.match(enqueue, /twins-builder/);
  assert.match(enqueue, /cost-madison/);
  assert.match(enqueue, /cost-milwaukee/);
  assert.match(enqueue, /catalog-preserve/);
  assert.match(enqueue, /\$classification\s*===\s*['"]builder['"]/);
});

test('runtime manifests deploy the portable catalog family files', () => {
  const staging = JSON.parse(read('website/twins-brand-experience/manifests/staging-runtime.json'));
  const host = JSON.parse(read('website/twins-brand-experience/manifests/host-verification.json'));
  const destinations = new Set(staging.files.filter(file => file.role === 'deploy').map(file => file.destination));
  const verified = new Set(host.files.map(file => file.source));

  for (const file of [
    'twins-brand-experience/assets/css/twins-brand-families.css',
    'twins-brand-experience/assets/js/twins-builder.js',
    'twins-brand-experience/templates/catalog.php',
  ]) {
    assert.equal(destinations.has(file), true, `${file} is not a deploy entry`);
  }
  assert.equal(verified.has('twins-brand-experience/templates/catalog.php'), true, 'catalog template is not a host-verification entry');
});
