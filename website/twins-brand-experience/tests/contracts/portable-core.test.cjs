const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');

test('portable bootstrap contains pure requires and no environment authority', () => {
  const source = fs.readFileSync(path.join(root, 'bootstrap.php'), 'utf8');
  assert.match(source, /require_once/);
  assert.doesNotMatch(source, /add_action|add_filter|header\s*\(|wp_enqueue|TWINS_STAGING|DISABLE_WP_CRON|danielj140|twinsgaragedoors\.com/i);
});

test('market data fixes staging and production visibility', () => {
  const source = fs.readFileSync(path.join(root, 'config/markets.php'), 'utf8');
  assert.match(source, /'wi'/);
  assert.match(source, /'ky'/);
  assert.match(source, /'il-preview'/);
  assert.match(source, /'productionEnabled'\s*=>\s*false/);
  assert.match(source, /\(833\) 833-2010/);
});

test('portable regional literals byte-match the fixed staging registry', () => {
  const portableSource = fs.readFileSync(path.join(root, 'config/markets.php'), 'utf8');
  const stagingSource = fs.readFileSync(
    path.join(root, '../staging-safety/mu-plugins/twins-staging-overhaul/data.php'),
    'utf8',
  );

  const stagingMarkets = new Map();
  const stagingPattern = /\d+\s*=>\s*array\(\s*'key'\s*=>\s*'(main|wi|ky|il)'\s*,\s*'phone'\s*=>\s*'([^']*)'\s*,\s*'tel'\s*=>\s*'([^']*)'\s*,\s*'base'\s*=>\s*'([^']*)'/g;
  for (const match of stagingSource.matchAll(stagingPattern)) {
    stagingMarkets.set(match[1], {
      phoneDisplay: match[2],
      phoneHref: `tel:${match[3]}`,
      routePrefix: match[4],
    });
  }

  const portableMarkets = new Map();
  const portablePattern = /'(main|wi|ky|il-preview)'\s*=>\s*\[\s*'label'\s*=>\s*'[^']*'\s*,\s*'phoneDisplay'\s*=>\s*'([^']*)'\s*,\s*'phoneHref'\s*=>\s*'([^']*)'\s*,\s*'routePrefix'\s*=>\s*'([^']*)'/g;
  for (const match of portableSource.matchAll(portablePattern)) {
    portableMarkets.set(match[1], {
      phoneDisplay: match[2],
      phoneHref: match[3],
      routePrefix: match[4],
    });
  }

  assert.deepEqual([...stagingMarkets.keys()].sort(), ['il', 'ky', 'main', 'wi']);
  assert.deepEqual([...portableMarkets.keys()].sort(), ['il-preview', 'ky', 'main', 'wi']);

  const marketPairs = [
    ['main', 'main'],
    ['wi', 'wi'],
    ['ky', 'ky'],
    ['il-preview', 'il'],
  ];
  for (const [portableKey, stagingKey] of marketPairs) {
    const portable = portableMarkets.get(portableKey);
    const staging = stagingMarkets.get(stagingKey);
    for (const field of ['phoneDisplay', 'phoneHref', 'routePrefix']) {
      assert.equal(
        Buffer.compare(Buffer.from(portable[field], 'utf8'), Buffer.from(staging[field], 'utf8')),
        0,
        `${portableKey}.${field} drifted from the fixed staging registry`,
      );
    }
  }
});

test('market registry rejects any names outside the fixed four-market boundary', () => {
  const source = fs.readFileSync(path.join(root, 'src/MarketRegistry.php'), 'utf8');
  assert.match(source, /array_keys\(\$markets\)\s*!==\s*\['main',\s*'wi',\s*'ky',\s*'il-preview'\]/);
});

test('renderer isolates the full render pipeline inside its output-buffer cleanup boundary', () => {
  const source = fs.readFileSync(path.join(root, 'src/Experience.php'), 'utf8');
  const bufferIndex = source.indexOf('$bufferLevel = ob_get_level();');
  const tryIndex = source.indexOf('try {', bufferIndex);
  const catchIndex = source.indexOf('} catch (\\Throwable $error)', tryIndex);
  const normalizeIndex = source.indexOf('$context = $this->routes->normalizeContext($context);');
  const validationIndex = source.indexOf("if (!isset($context['environment'], $context['market'])");
  const marketIndex = source.indexOf('$market = $this->markets->resolve($marketKey, $environment);');
  const quoteIndex = source.indexOf('$quote = $this->quote->action($context);');
  const bookingIndex = source.indexOf('$booking = in_array($template');
  const templateIndex = source.indexOf("require $this->root . '/templates/'");
  assert.ok(bufferIndex >= 0 && tryIndex > bufferIndex && catchIndex > tryIndex);
  for (const [step, index] of [
    ['route normalization', normalizeIndex],
    ['normalized-context validation', validationIndex],
    ['market resolution', marketIndex],
    ['quote adapter', quoteIndex],
    ['booking adapter', bookingIndex],
    ['template include', templateIndex],
  ]) {
    assert.ok(index > tryIndex && index < catchIndex, `${step} must run inside the cleanup boundary`);
  }
});
