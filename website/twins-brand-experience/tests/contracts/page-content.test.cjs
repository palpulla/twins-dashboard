const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const repository = path.resolve(root, '../..');
const routes = [
  '/garage-door-repair/',
  '/garage-door-installation/',
  '/garage-door-spring-repair/',
  '/garage-door-opener-repair/',
  '/emergency-garage-services/',
  '/garage-door-services/',
  '/garage-door-cable-repair/',
  '/garage-door-openers/',
  '/garage-weatherstripping-repair/',
  '/garage-door-tune-up/',
  '/maintenance-plans/',
  '/property-management-services/',
  '/protection-plans/',
];
const requiredKeys = ['h1', 'directAnswer', 'needs', 'safety', 'process', 'options', 'prepare', 'faqs', 'links'];

const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const wordCount = value => value.trim().split(/\s+/).length;
const unescapePhp = value => value.replace(/\\'/g, "'").replace(/\\\\/g, '\\');

function recordBlocks(source) {
  const starts = routes.map(route => {
    const marker = `    '${route}' => [`;
    const index = source.indexOf(marker);
    assert.notEqual(index, -1, `${route} record is missing`);
    return [route, index];
  }).sort((one, two) => one[1] - two[1]);
  return new Map(starts.map(([route, index], position) => [
    route,
    source.slice(index, starts[position + 1]?.[1] ?? source.lastIndexOf('];')),
  ]));
}

function scalar(block, key) {
  const match = block.match(new RegExp(`'${key}'\\s*=>\\s*'((?:\\\\'|[^'])*)'`));
  assert.ok(match, `${key} scalar is missing`);
  return unescapePhp(match[1]);
}

function customerValues(block) {
  return [...block.matchAll(/=>\s*'((?:\\'|[^'])*)'/g)].map(match => unescapePhp(match[1]));
}

test('fixed page-content config contains exactly thirteen conservative bespoke records', () => {
  const source = read('config/page-content.php');
  const keys = [...source.matchAll(/^ {4}'(\/[^']+\/)'\s*=>\s*\[/gm)].map(match => match[1]);
  assert.deepEqual(keys, routes);

  for (const [route, block] of recordBlocks(source)) {
    const answer = scalar(block, 'directAnswer');
    assert.ok(wordCount(answer) >= 40 && wordCount(answer) <= 60, `${route} direct answer word count`);
    const questions = [...block.matchAll(/'question'\s*=>\s*'((?:\\'|[^'])*)'/g)].map(match => unescapePhp(match[1]));
    assert.ok(questions.length >= 4 && questions.length <= 6, `${route} FAQ count`);
    assert.ok(questions.every(question => question.endsWith('?')), `${route} FAQ punctuation`);
  }

  const values = [...recordBlocks(source).values()].flatMap(customerValues).join('\n');
  assert.doesNotMatch(values, /\(\d{3}\)\s*\d{3}-\d{4}|(?:Wisconsin|Kentucky|Illinois|Madison|Milwaukee|Rockford|Lexington)/i);
  assert.doesNotMatch(values, /#1|number one|No\.\s*1|top-rated|replace (?:the )?spring yourself|DIY spring|with the proper tools/i);
  assert.doesNotMatch(values, /\b(?:24\/7|365|same-day|same-visit|in-one-visit|fastest|most|often|usually|likely|quieter|fewer return visits)\b/i);

  const spring = recordBlocks(source).get('/garage-door-spring-repair/');
  assert.match(scalar(spring, 'safety'), /dangerous tension/i);
  assert.match(scalar(spring, 'safety'), /trained professionals?/i);
});

test('PageContentRegistry is fixed-shape, fail-closed, and performs no caller-selected I/O', () => {
  const source = read('src/PageContentRegistry.php');
  const harness = read('tests/php/portable-core-harness.php');
  assert.match(source, /final class PageContentRegistry/);
  assert.match(source, /public function resolve\(string \$path, string \$title\): array/);
  for (const key of requiredKeys) assert.match(source, new RegExp(`['"]${key}['"]`), key);
  for (const prefix of ['wi', 'ky', 'il']) assert.match(source, new RegExp(`['"]${prefix}['"]`), prefix);
  for (const route of routes) assert.match(source, new RegExp(route.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  assert.match(source, /genericServiceRecord/);
  assert.match(source, /garage-door-cable-repair/);
  assert.match(source, /garage-door-tune-up/);
  assert.match(source, /private const FALLBACK_TITLES\s*=\s*\[/);
  assert.match(source, /'\/garage-door-cable-repair\/'\s*=>\s*'Garage Door Cable Repair'/);
  assert.match(source, /genericServiceRecord\(self::FALLBACK_TITLES\[\$path\]\)/);
  assert.doesNotMatch(source, /validateTitle/);
  assert.doesNotMatch(source, /\b(?:file_get_contents|fopen|readfile|include|require|curl_|stream_|glob|scandir)\b/i);
  assert.match(source, /InvalidArgumentException|DomainException/);
  assert.match(source, /%\(2f\|5c\)|%2f|%5c/i);
  assert.match(source, /40/);
  assert.match(source, /60/);
  for (const assertion of [
    "$pageRegistry->resolve('/wi/garage-door-spring-repair/', '<script>ignored bespoke title</script>')",
    "$pageRegistry->resolve('/wi/garage-door-cable-repair/', '<script>hostile mutable title</script>')",
    "$expect($fallback['h1'] === 'Garage Door Cable Repair'",
    "$pageRegistry->resolve('/wi/not-a-service/', 'Ignored')",
    "unset($malformedPageRecords['/garage-door-repair/']['safety'])",
  ]) assert.ok(harness.includes(assertion), assertion);
});

test('portable service and editorial templates keep adapters and inert content boundaries explicit', () => {
  const service = read('templates/service.php');
  const editorial = read('templates/editorial.php');
  const serviceSections = [
    'twins-brand-service-hero',
    'twins-brand-direct-answer',
    'twins-brand-service-needs',
    'twins-brand-service-process',
    'twins-brand-service-options',
    'twins-brand-service-area',
    'twins-brand-faq',
    'twins-brand-final-cta',
  ];
  let cursor = -1;
  for (const marker of serviceSections) {
    const next = service.indexOf(marker);
    assert.ok(next > cursor, `${marker} is missing or out of order`);
    cursor = next;
  }
  assert.equal((service.match(/<h1\b/g) || []).length, 1);
  assert.doesNotMatch(service, /data-twins-original-content|\$content|replace it yourself|DIY spring|#1/i);
  assert.match(service, /\$phoneHref/);
  assert.match(service, /\$phone/);
  assert.doesNotMatch(service, /\$market\[['"]phone(?:Href|Display)['"]\]/);
  assert.match(service, /\$experience->route\(\$link\[['"]route['"]\], \$marketKey\)/);
  assert.match(service, /\$quote\[['"]href['"]\]/);

  assert.equal((editorial.match(/<h1\b/g) || []).length, 1);
  assert.match(editorial, /twins-brand-editorial-page/);
  assert.match(editorial, /\$content/);
  assert.match(editorial, /\$phoneHref/);
  assert.match(editorial, /\$phone/);
  assert.doesNotMatch(editorial, /\$market\[['"]phone(?:Href|Display)['"]\]/);
  assert.match(editorial, /\$quote\[['"]href['"]\]/);
  assert.doesNotMatch(editorial, /elementor|<form\b|type=["']submit["']|https?:\/\//i);
});

test('portable runtime wires registry resolution and classified renderer dispatch without weakening preserved routes', () => {
  const bootstrap = read('bootstrap.php');
  const experience = read('src/Experience.php');
  const renderers = fs.readFileSync(
    path.join(repository, 'website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php'),
    'utf8',
  );
  assert.match(bootstrap, /PageContentRegistry\.php/);
  assert.match(experience, /renderService\(array \$context\): string/);
  assert.match(experience, /renderEditorial\(array \$context, string \$content, string \$kind\): string/);
  assert.match(experience, /PageContentRegistry/);
  assert.match(experience, /config\/page-content\.php/);
  assert.match(renderers, /\$classification === 'service'[\s\S]*renderService\(\$context\)/);
  assert.match(renderers, /in_array\(\$classification, array\('location', 'trust', 'article'\), true\)[\s\S]*renderEditorial/);
  assert.match(renderers, /twins_overhaul_prepare_family_content\(\$content\)/);
  assert.match(renderers, /\$classification === 'legal-preserve'[\s\S]*twins_overhaul_render_article_template/);
  assert.match(renderers, /\$classification === 'campaign-preserve'[\s\S]*twins_overhaul_remove_campaign_remote_font_links/);
  assert.match(renderers, /\$classification === 'catalog-preserve'[\s\S]*renderCatalog\([\s\S]*twins_overhaul_catalog_view\(\$context\)/);
  const catalogBranch = renderers.match(/\} elseif \(\$classification === 'catalog-preserve'\) \{([\s\S]*?)\} elseif/);
  assert.ok(catalogBranch, 'catalog renderer branch is missing');
  assert.doesNotMatch(catalogBranch[1], /twins_overhaul_wrap_preserved_content/);
});

test('renderer harness pins spring safety, one H1, FAQ depth, and raw-body removal', () => {
  const harness = fs.readFileSync(
    path.join(repository, 'website/staging-safety/tests/staging-overhaul-renderers-harness.php'),
    'utf8',
  );
  for (const assertion of [
    "substr_count($rendered, '<h1') === 1",
    "substr_count($rendered, '<details') >= 4",
    "stripos($rendered, 'dangerous tension') !== false",
    "stripos($rendered, 'trained professionals') !== false",
    "stripos($rendered, 'replace it yourself') === false",
    "strpos($rendered, 'data-twins-original-content') === false",
  ]) assert.ok(harness.includes(assertion), assertion);
  for (const assertion of [
    '$milwaukeeHeader = twins_overhaul_render_header($milwaukeeContext)',
    '$milwaukeeBody = twins_overhaul_render_classified_content(',
    '$milwaukeeFooter = twins_overhaul_render_footer($milwaukeeContext)',
    "strpos($milwaukeeWithoutMarketMenu, '(608) 420-2377') === false",
    "substr_count($milwaukeeWithoutMarketMenu, '(414) 800-9271') === 3",
    "substr_count($milwaukeeWithoutMarketMenu, 'tel:+14148009271') === 5",
    "substr_count($wisconsinWithoutMarketMenu, '(608) 420-2377') === 3",
    "substr_count($wisconsinWithoutMarketMenu, 'tel:+16084202377') === 6",
    "substr_count($illinoisWithoutMarketMenu, '(815) 800-2025') === 3",
    "substr_count($illinoisWithoutMarketMenu, 'tel:+18158002025') === 6",
  ]) assert.ok(harness.includes(assertion), assertion);
});

test('service and editorial CSS is responsive and pins readable foreground colors', () => {
  const css = read('assets/css/twins-brand.css');
  for (const selector of [
    '.twins-brand-service-page',
    '.twins-brand-service-hero',
    '.twins-brand-direct-answer',
    '.twins-brand-service-grid',
    '.twins-brand-service-options',
    '.twins-brand-service-area',
    '.twins-brand-faq',
    '.twins-brand-editorial-page',
    '.twins-brand-editorial-content',
  ]) assert.ok(css.includes(selector), selector);
  assert.match(css, /\.twins-brand-editorial-content[\s\S]*max-width:/);
  assert.match(css, /\.twins-brand-editorial-content[\s\S]*color:/);
  assert.match(css, /\.twins-brand-editorial-content[\s\S]*\ba\s*\{[^}]*color:/);
  for (const surface of ['service-hero', 'editorial-hero', 'direct-answer', 'service-area']) {
    assert.match(
      css,
      new RegExp(`body\\.twins-brand-experience \\.twins-brand-${surface} \\.twins-brand-kicker[\\s\\S]*?color:\\s*var\\(--twins-gold\\)`),
      `${surface} kicker contrast`,
    );
  }
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*twins-brand-service-grid/);
});
