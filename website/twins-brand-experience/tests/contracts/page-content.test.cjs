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
];
const requiredKeys = [
  'h1',
  'directAnswer',
  'answerFacts',
  'priceRange',
  'warningSigns',
  'process',
  'costParagraphs',
  'safety',
  'plans',
  'reviewTag',
  'faqs',
  'links',
];
// Only these three pages render the tension-safety module.
const safetyRoutes = [
  '/garage-door-spring-repair/',
  '/garage-door-cable-repair/',
  '/emergency-garage-services/',
];
// No record publishes program rates. /protection-plans/ was the one that
// could, and it was retired into /maintenance-plans/ on 2026-08-27; the real
// tiers are pinned by twinshield-program.test.cjs, which also asserts that
// exactly one page path carries them. Every record here may publish only
// figures from data/price-ranges.json plus the two approved offers ($0 service
// call with repair, $49 tune-up), so a stale figure fails here, not live.
// p20/p80 pairs from docs/marketing/website-rebuild/data/price-ranges.json
// (generated 2026-08-18 from completed HCP jobs, last 24 months). The docs
// tree is not checked out everywhere, so the approved figures are pinned here
// verbatim: spring, opener repair, opener install, cable, single door,
// double door, tune-up.
const approvedPriceRanges = [
  [575, 1225],
  [100, 300],
  [775, 1400],
  [325, 625],
  [2625, 3525],
  [3425, 4400],
  [50, 100],
];
// The two approved offers plus every approved percentile figure.
const approvedServiceAmounts = new Set(['0', '49']);
for (const [low, high] of approvedPriceRanges) {
  approvedServiceAmounts.add(String(low));
  approvedServiceAmounts.add(String(high));
}
const guarantee = 'Done Right, or We Make It Right.';

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

test('fixed page-content config contains exactly twelve approved-copy service records', () => {
  const source = read('config/page-content.php');
  const keys = [...source.matchAll(/^ {4}'(\/[^']+\/)'\s*=>\s*\[/gm)].map(match => match[1]);
  assert.deepEqual(keys, routes);

  for (const [route, block] of recordBlocks(source)) {
    const answer = scalar(block, 'directAnswer');
    assert.ok(wordCount(answer) >= 40 && wordCount(answer) <= 60, `${route} direct answer word count`);
    const questions = [...block.matchAll(/'question'\s*=>\s*'((?:\\'|[^'])*)'/g)].map(match => unescapePhp(match[1]));
    assert.equal(questions.length, 5, `${route} FAQ count is not five`);
    assert.ok(questions.every(question => question.endsWith('?')), `${route} FAQ punctuation`);
    assert.match(block, /'answerFacts'\s*=>\s*\[/, `${route} answer facts are missing`);
    assert.match(block, /'call'\s*=>\s*'/, `${route} when-to-call fact is missing`);
    assert.match(block, /'reviewTag'\s*=>\s*'(?:springs|openers|installation|emergency|general)'/, `${route} review tag`);
    assert.match(block, /'costParagraphs'\s*=>\s*\[/, `${route} cost section copy is missing`);
    assert.match(block, /'warningSigns'\s*=>\s*\[/, `${route} warning signs are missing`);

    if (safetyRoutes.includes(route)) {
      const safety = scalar(block, 'safety');
      assert.match(safety, /dangerous tension/i, `${route} safety framing`);
      assert.match(safety, /trained professionals?/i, `${route} safety handling`);
    } else {
      assert.match(block, /'safety'\s*=>\s*null/, `${route} must not carry a safety module`);
    }
    assert.match(block, /'plans'\s*=>\s*null/, `${route} must not carry plan tiers`);

    const linkRoutes = [...block.matchAll(/'route'\s*=>\s*'([a-z-]+)'/g)].map(match => match[1]);
    assert.ok(linkRoutes.length >= 2 && linkRoutes.length <= 4, `${route} related-services count`);
    assert.equal(new Set(linkRoutes).size, linkRoutes.length, `${route} related-services duplicates`);
  }

  const values = [...recordBlocks(source).values()].flatMap(customerValues).join('\n');
  // Market-neutral records: one record serves every market prefix.
  assert.doesNotMatch(values, /\(\d{3}\)\s*\d{3}-\d{4}|(?:Wisconsin|Kentucky|Illinois|Madison|Milwaukee|Rockford|Lexington)/i);
  // Style law: no em-dashes, no 24/7, no lifetime claims, no superlatives,
  // no warranty/guarantee wording in records (the guarantee is template-owned
  // and rendered verbatim), no $Xk money formatting, no DIY spring framing.
  assert.doesNotMatch(values, /—|–/, 'records carry an em- or en-dash');
  assert.doesNotMatch(values, /24\s*\/\s*7|\b365\b|\blifetime\b/i);
  assert.doesNotMatch(values, /#1|number one|No\.\s*1|top-rated/i);
  assert.doesNotMatch(values, /\b(?:warrant(?:y|ies)|guaranteed?)\b/i);
  assert.doesNotMatch(values, /replace (?:the )?spring yourself|DIY spring/i);
  assert.doesNotMatch(values, /\$\d+(?:\.\d+)?k\b/i, 'full dollar amounts only');
  assert.ok(!values.includes(guarantee), 'the guarantee line is template-owned, not record copy');

  const blocks = recordBlocks(source);
  // Every published figure traces to data/price-ranges.json or an approved
  // offer. There is no longer a page exempt from this: the membership rates
  // left page content with /protection-plans/.
  for (const [route, block] of blocks) {
    for (const amount of customerValues(block).join('\n').matchAll(/\$(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/g)) {
      assert.ok(
        approvedServiceAmounts.has(amount[1].replace(/,/g, '')),
        `${route} publishes $${amount[1]}, which is not in price-ranges.json or an approved offer`,
      );
    }
  }
  assert.doesNotMatch(values, /\bbest\b/i, 'service pages carry no superlative');
  assert.doesNotMatch(values, /\bUSD\b/i, 'published amounts use one currency form');

  // The retired page is gone from the config, and no record may reintroduce a
  // plan tier through the back door: no plan name, no per-tier price line, and
  // no `tradeoff` key anywhere in page content.
  assert.doesNotMatch(source, /^ {4}'\/protection-plans\/'\s*=>/m, 'the retired route still has a page-content record');
  assert.doesNotMatch(source, /'tradeoff'/, 'plan tiers are no longer page content');
  for (const rate of ['12.99', '155.88', '18.99', '227.88', '24.99', '299.88']) {
    assert.ok(!source.includes(`$${rate}`), `page content republishes the $${rate} program rate`);
  }
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
  // Approved price-range pairs in the registry mirror data/price-ranges.json.
  for (const [low, high] of approvedPriceRanges) {
    assert.match(
      source,
      new RegExp(`\\[${low}, ${high}\\]`),
      `registry is missing the approved ${low}-${high} range`,
    );
  }
  assert.match(source, /SAFETY_PATHS/);
  assert.match(source, /REVIEW_TAGS/);
  assert.match(source, /APPROVED_SERVICE_AMOUNTS/);
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
    'twins-brand-service-facts',
    'twins-brand-service-signs',
    'twins-brand-service-process',
    'twins-brand-service-guarantee-title',
    'twins-brand-service-cost',
    'twins-brand-service-safety',
    'twins-brand-membership',
    'twins-brand-service-reviews',
    'twins-brand-service-related',
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
  assert.doesNotMatch(service, /data-twins-original-content|\$content\b|replace it yourself|DIY spring|#1/i);
  assert.match(service, /\$phoneHref/);
  assert.match(service, /\$phone/);
  assert.doesNotMatch(service, /\$market\[['"]phone(?:Href|Display)['"]\]/);
  assert.match(service, /\$experience->route\(\$link\[['"]route['"]\], \$marketKey\)/);
  assert.match(service, /\$quote\[['"]href['"]\]/);

  // The guarantee is template-owned and verbatim.
  assert.ok(service.includes(`$serviceGuarantee = '${guarantee}'`), 'the verbatim guarantee is missing');
  // The safety module renders only when the record carries safety copy, and
  // the membership cards only when the record carries plan tiers.
  assert.match(service, /\$serviceSafety = \$pageContent\['safety'\]/);
  assert.match(service, /if \(\$serviceSafety !== null\)/);
  assert.match(service, /\$servicePlans = \$pageContent\['plans'\]/);
  assert.match(service, /if \(\$servicePlans !== null\)/);
  // Verbatim service-tagged review quotes reuse the city-system pool with the
  // deterministic per-path picker.
  assert.match(service, /config\/review-quotes\.php/);
  assert.match(service, /crc32\(\$serviceNormalizedPath\)/);
  assert.match(service, /\$pageContent\['reviewTag'\]/);
  // Template-owned JSON-LD: Service + BreadcrumbList + FAQPage mirroring the
  // rendered accordion, prices only from the validated record range.
  for (const marker of [
    "'@type' => 'Service'",
    "'@type' => 'BreadcrumbList'",
    "'@type' => 'FAQPage'",
    "'@type' => 'PriceSpecification'",
    '$pageContent[\'faqs\']',
    'JSON_HEX_TAG | JSON_HEX_AMP',
  ]) assert.ok(service.includes(marker), `service schema marker missing: ${marker}`);
  assert.match(service, /type="application\/ld\+json"/);

  assert.equal((editorial.match(/<h1\b/g) || []).length, 1);
  assert.match(editorial, /twins-brand-editorial-page/);
  assert.match(editorial, /\$content/);
  assert.match(editorial, /\$phoneHref/);
  assert.match(editorial, /\$phone/);
  assert.doesNotMatch(editorial, /\$market\[['"]phone(?:Href|Display)['"]\]/);
  assert.match(editorial, /\$quote\[['"]href['"]\]/);
  // Exactly two approved external literals: the financing page's Wisetack
  // prequalify CTA (rendered target=_blank with the fixed rel) and the
  // JSON-LD '@context' vocabulary identifier. Map embed URLs come from the
  // validated location record, never from a template literal.
  const wisetackHref = "$financingApplyHref = 'https://wisetack.us/#/ifbtqfh/prequalify';";
  assert.ok(editorial.includes(wisetackHref), 'approved Wisetack apply link is missing');
  assert.match(editorial, /htmlspecialchars\(\$financingApplyHref[^)]*\)\s*\?>" target="_blank" rel="noopener noreferrer"/);
  const schemaContext = "'@context' => 'https://schema.org',";
  assert.ok(editorial.includes(schemaContext), 'JSON-LD schema context is missing');
  assert.doesNotMatch(
    editorial.replaceAll(wisetackHref, '').replaceAll(schemaContext, ''),
    /elementor|<form\b|type=["']submit["']|https?:\/\//i,
  );
  // The service template's only external literals are schema vocabulary
  // identifiers inside the JSON-LD graph.
  assert.doesNotMatch(
    service.replaceAll(schemaContext, ''),
    /elementor|<form\b|type=["']submit["']|https?:\/\//i,
  );
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
  // The renderer-level service schema stands down: the brand template owns
  // the Service + BreadcrumbList + FAQPage stack.
  const serviceSchemaBranch = renderers.match(/\} elseif \(\$classification === 'service'\) \{([\s\S]*?)\} elseif \(\$classification === 'reviews-brand'\)/);
  assert.ok(serviceSchemaBranch, 'service schema branch is missing');
  assert.match(serviceSchemaBranch[1], /return '';/);
  assert.doesNotMatch(serviceSchemaBranch[1], /'@type' => 'Service'/);
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
    "substr_count($milwaukeeWithoutMarketMenu, '(414) 800-9271') >= 2",
    "substr_count($milwaukeeWithoutMarketMenu, 'tel:+14148009271') >= 2",
    "substr_count($wisconsinWithoutMarketMenu, '(608) 420-2377') >= 2",
    "substr_count($wisconsinWithoutMarketMenu, 'tel:+16084202377') >= 2",
    "substr_count($illinoisWithoutMarketMenu, '(815) 800-2025') >= 2",
    "substr_count($illinoisWithoutMarketMenu, 'tel:+18158002025') >= 2",
  ]) assert.ok(harness.includes(assertion), assertion);
});

test('service and editorial CSS is responsive and pins readable foreground colors', () => {
  const css = read('assets/css/twins-brand.css');
  for (const selector of [
    '.twins-brand-service-page',
    '.twins-brand-service-hero',
    '.twins-brand-direct-answer',
    '.twins-brand-service-facts',
    '.twins-brand-service-signs',
    '.twins-brand-service-guarantee',
    '.twins-brand-service-cost',
    '.twins-brand-service-cost-range',
    '.twins-brand-service-safety-section',
    '.twins-brand-service-reviews',
    '.twins-brand-service-related',
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
