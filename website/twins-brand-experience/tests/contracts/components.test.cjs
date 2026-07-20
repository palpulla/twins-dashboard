const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');

const source = name => {
  const file = path.join(root, 'components', name);
  return fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '';
};

const templateSource = name => {
  const file = path.join(root, 'templates', name);
  return fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '';
};

const phpHarnessSource = name => {
  const file = path.join(root, 'tests/php', name);
  return fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '';
};

test('header exposes the approved complete navigation and CTA copy', () => {
  const html = source('header.php') + source('nav-data.php');
  for (const label of ['Services', 'Garage Doors', 'Service Areas', 'Resources', 'About', 'Our Team', 'Careers', 'Book Online', 'Request a Quote']) {
    assert.match(html, new RegExp(label.replace(' ', '\\s+')));
  }
  assert.doesNotMatch(html, /Get an Estimate|Request Exact Quote/);
  assert.match(html, /aria-controls="twins-brand-drawer"/);
  assert.match(html, /aria-expanded="false"/);
  assert.match(html, /===\s*['"]dialog['"]/);
  assert.match(html, /===\s*['"]external['"]/);
});

test('header carries a cache-independent guard against duplicate legacy chrome', () => {
  const html = source('header.php');
  const guard = html.match(/<style id="twins-brand-critical-chrome">([\s\S]*?)<\/style>/);

  assert.ok(guard, 'header is missing its inline critical chrome guard');
  assert.ok(
    html.indexOf('<style id="twins-brand-critical-chrome">') < html.indexOf('<header class="twins-brand-header"'),
    'critical chrome guard must render before the branded header',
  );
  assert.match(guard[1], /body:has\(\.twins-brand-header\)\s+:where\(/);
  for (const selector of [
    '#masthead',
    '#colophon',
    'header.elementor-location-header',
    '[data-elementor-type="header"][data-elementor-id="7336"]',
    'footer.elementor-location-footer',
    '#menuhopin.twx2-header',
  ]) {
    assert.match(guard[1], new RegExp(selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  assert.match(guard[1], /display:\s*none\s*!important/);
});

test('header binds booking mode to environment and emits only exact safe external links', () => {
  const html = source('header.php');
  assert.match(html, /\$environment\s*===\s*['"]staging['"][\s\S]*?\$bookingMode\s*!==\s*['"]dialog['"]/);
  assert.match(html, /\$environment\s*===\s*['"]production['"][\s\S]*?\$bookingMode\s*!==\s*['"]external['"]/);
  assert.match(html, /\$booking\[['"]target['"]\]/);
  assert.match(html, /\$booking\[['"]rel['"]\]/);
  assert.equal((html.match(/target="_blank" rel="noopener noreferrer"/g) || []).length, 2);
});

test('header exposes the service-area chooser as a native market menu with approved phones', () => {
  const header = source('header.php');
  assert.match(header, /<details class="twins-brand-market-menu"/);
  assert.match(header, /<summary>Choose your service area<\/summary>/);
  assert.match(header, /\$availableMarket\[['"]phoneDisplay['"]\]/);
  assert.doesNotMatch(header, /<span>Choose your service area<\/span>/);
});

test('shared chrome uses normalized path contact while the market selector stays market-wide', () => {
  const header = source('header.php');
  const footer = source('footer.php');
  const experience = fs.readFileSync(path.join(root, 'src/Experience.php'), 'utf8');
  assert.match(
    experience,
    /in_array\(\$template,\s*\[['"]\.\.\/components\/header['"],\s*['"]\.\.\/components\/footer['"],\s*['"]service['"],\s*['"]editorial['"]\],\s*true\)/,
  );
  assert.match(header, /class="twins-brand-phone" href="<\?= htmlspecialchars\(\$phoneHref,[\s\S]*?<\?= htmlspecialchars\(\$phone,/);
  assert.doesNotMatch(header, /class="twins-brand-phone"[\s\S]*?\$market\[['"]phone(?:Href|Display)['"]\]/);
  assert.match(header, /\$availableMarket\[['"]phoneDisplay['"]\]/);
  assert.match(footer, /class="twins-brand-phone" href="<\?= htmlspecialchars\(\$phoneHref,[\s\S]*?<\?= htmlspecialchars\(\$phone,/);
  assert.match(footer, /twins-brand-mobile-actions[\s\S]*?htmlspecialchars\(\$phoneHref,/);
  assert.doesNotMatch(footer, /\$market\[['"]phone(?:Href|Display)['"]\]/);
});

test('review component exposes bounded featured and static list modes without dot controls', () => {
  const html = source('review-slider.php');
  assert.match(html, /twins-brand-review-slider/);
  assert.match(html, /data-review-mode="featured"/);
  assert.match(html, /array_slice\(\$records,\s*0,\s*9\)/);
  assert.match(html, /twins-brand-review-list/);
  assert.match(html, /data-review-page-status/);
  assert.match(html, /twins-brand-review-control/);
  assert.match(html, /count\(\$words\)\s*>\s*42/);
  assert.match(html, /\$listMode\s*&&\s*\$isLong/);
  assert.match(html, /<details/);
  assert.match(html, /Read full review/);
  assert.doesNotMatch(html, /twins-brand-review-dots/);
  assert.match(html, /id="twins-brand-reviews-title"/);
  assert.match(html, /Google reviews/);
  assert.match(html, /<div class="twins-brand-section-heading">[\s\S]*id="twins-brand-reviews-title"[\s\S]*twins-brand-google-attribution[\s\S]*<\/div>/);
  assert.match(html, /<a class="twins-brand-text-link" href="[^"]*">Read all reviews<\/a>/);
  assert.match(html, /data-review-stable-id/);
  assert.match(html, /allowExternalSourceAction/);
  assert.match(html, /===\s*true/);
  assert.doesNotMatch(html, /AggregateRating|ReviewRating|application\/ld\+json/);
  assert.doesNotMatch(html, /sourceRecordUrl|avatar|providerHtml/);
});

test('PHP renderer harness proves the static Reviews mode keeps the full collection and complete long quote', () => {
  const harness = phpHarnessSource('renderer-contract-harness.php');
  assert.match(harness, /reviews list did not render the full verified collection/);
  assert.match(harness, /reviews list changed the complete long quote/);
  assert.match(harness, /featured slider did not stay within nine records/);
  assert.match(harness, /['"]classification['"]\s*=>\s*['"]reviews-brand['"]/);
});

test('Reviews template explicitly selects static list mode without an autoplay marker', () => {
  const html = templateSource('reviews.php');
  assert.match(html, /\$context\[['"]classification['"]\]\s*=\s*['"]reviews-brand['"]/);
  assert.match(html, /components\/review-slider\.php/);
  assert.doesNotMatch(html, /data-twins-review-slider|data-review-mode="featured"/);
});

test('picture component accepts logical keys, not paths or URLs', () => {
  const html = source('picture.php');
  assert.match(html, /\$logicalKey/);
  for (const key of ['crew-fleet', 'tal-portrait', 'technician-at-work', 'door-builder-before-after']) {
    assert.match(html, new RegExp(key));
  }
  assert.match(html, /\$experience->asset\(/);
  assert.doesNotMatch(html, /\$_GET|\$_POST|parse_url|https?:\/\//);
});

test('footer has only the approved mobile quick actions and no fixed host', () => {
  const html = source('footer.php');
  assert.match(html, /twins-brand-mobile-actions/);
  assert.match(html, /Call Twins/);
  assert.match(html, /Request a Quote/);
  assert.doesNotMatch(html, /Get an Estimate|Request Exact Quote|https?:\/\//);
});

test('components never default a missing market or environment', () => {
  for (const name of ['header.php', 'footer.php', 'review-slider.php']) {
    assert.doesNotMatch(source(name), /\?\?\s*['"](?:staging|main)['"]/);
  }
});
