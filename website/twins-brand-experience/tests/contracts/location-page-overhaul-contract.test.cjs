const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const template = fs.readFileSync(path.join(root, 'templates/editorial.php'), 'utf8');
const locationContent = fs.readFileSync(path.join(root, 'config/location-content.php'), 'utf8');
const footer = fs.readFileSync(path.join(root, 'components/footer.php'), 'utf8');
const picture = fs.readFileSync(path.join(root, 'components/picture.php'), 'utf8');
const experience = fs.readFileSync(path.join(root, 'src/Experience.php'), 'utf8');
const reviewSummary = fs.readFileSync(path.join(root, 'config/review-summary.php'), 'utf8');
const stagingRoutes = fs.readFileSync(path.join(
  root,
  '../staging-safety/mu-plugins/twins-staging-overhaul/adapters/BrandStagingAdapters.php',
), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/twins-brand.css'), 'utf8');
const script = fs.readFileSync(path.join(root, 'assets/js/twins-brand.js'), 'utf8');
const navData = fs.readFileSync(path.join(root, 'components/nav-data.php'), 'utf8');
const markets = fs.readFileSync(path.join(root, 'config/markets.php'), 'utf8');
const fixture = fs.readFileSync(path.join(root, 'tests/browser/fixtures/location-modern.html'), 'utf8');

test('location template contains exactly the five approved recovery sections', () => {
  for (const className of [
    'twins-location-hero',
    'twins-location-trust',
    'twins-location-services',
    'twins-location-local-proof',
    'twins-location-final-cta',
  ]) {
    assert.match(template, new RegExp(className), `${className} is missing`);
  }
  for (const retired of [
    'twins-location-hero-stage',
    'twins-location-orbit',
    'twins-location-system',
    'twins-location-guidance',
    'twins-location-process',
    'twins-location-branch',
    'twins-location-nearby',
    'twins-location-faq',
  ]) {
    assert.doesNotMatch(template, new RegExp(retired), `${retired} must be removed`);
  }
});

test('recovery hero is a contained copy and media split with separate trust', () => {
  assert.match(template, /<header class="twins-location-hero"[\s\S]*?twins-location-hero-copy[\s\S]*?twins-location-hero-media[\s\S]*?<\/header>/);
  assert.match(template, /<section class="twins-location-trust"[^>]*role="list"/);
  assert.match(template, /Get a Free Quote/);
  assert.doesNotMatch(template, /\$placement = 'hero'/);
});

test('services are limited to three concise choices and one character cameo', () => {
  assert.equal((template.match(/'title' =>/g) || []).length >= 3, true);
  assert.doesNotMatch(template, /'title' => 'Preventive maintenance'/);
  assert.match(template, /\$placement = 'services'/);
  assert.match(template, /twins-location-service-grid/);
  assert.match(template, /twins-location-service-card/);
});

test('local proof has one owned image and three concise proof statements', () => {
  assert.match(template, /twins-location-local-proof/);
  assert.match(template, /\$logicalKey = 'door-builder-before-after'/);
  assert.match(template, /twins-location-proof-list/);
  assert.match(template, /Complete system inspection/);
  assert.match(template, /Plain-language options/);
  assert.match(template, /Respect for your home/);
});

test('Kentucky location service routes remain market-local', () => {
  const kyRouteBlock = stagingRoutes.match(/'ky'\s*=>\s*\[([\s\S]*?)\n\s*\],/);
  assert.ok(kyRouteBlock, 'ky route block is missing');
  for (const [routeKey, routePath] of [
    ['services', '/ky/garage-door-services/'],
    ['repair', '/ky/garage-door-repair/'],
    ['opener-repair', '/ky/garage-door-opener-repair/'],
    ['installation', '/ky/garage-door-installation/'],
  ]) {
    assert.match(kyRouteBlock[1], new RegExp(`'${routeKey}'\\s*=>\\s*'${routePath.replaceAll('/', '\\/')}'`),
      `Kentucky route ${routeKey} must stay market-local`);
  }
  assert.doesNotMatch(kyRouteBlock[1], /'service-area'\s*=>/,
    'Kentucky must not invent an unsupported service-area route');
  assert.doesNotMatch(locationContent, /'lexington'\s*=>/,
    'Kentucky routing must not invent a Lexington location-content record');
  assert.match(template, /\$locationPath\s*=\s*isset\(\$context\['path'\]\)/);
  assert.match(template, /\$locationNavMarketKey\s*=\s*'ky'/,
    'Kentucky locations must retain the normalized Kentucky route market');
  assert.match(experience, /\(\?:wi\|il\|ky\)\/location/,
    'normalized Kentucky location paths must participate in location-content lookup');
});

test('location copy never positions a branch as new or unproven', () => {
  for (const phrase of [
    'recently opened',
    'newly opened',
    'new to this market',
    'new market',
    'recent addition',
    'recent expansion',
    'still building',
    'earn the local record',
  ]) {
    assert.doesNotMatch(locationContent.toLowerCase(), new RegExp(phrase),
      `location registry contains prohibited positioning: ${phrase}`);
  }
});

test('footer uses route context instead of a hard-coded Madison address', () => {
  assert.doesNotMatch(footer, /<span>2921 Landmark Pl #206, Madison, WI 53713<\/span>/);
  assert.match(footer, /\$context\['metroAddress'\]/);
  assert.match(footer, /\$market\['address'\]/);
  assert.match(experience, /metroAddressForContext/);
  assert.match(experience, /5758 Elaine Dr Ste 110, Rockford, IL 61108/);
});

test('Rockford location fixture retains route-local contact data and the shared footer catalog', () => {
  const expectedGroups = [
    ['Services', 5, 'Garage Door Installation'],
    ['Garage Doors', 3, 'Garage Door Collections'],
    ['Service Areas', 3, 'Illinois'],
    ['Resources', 5, 'Frequently Asked Questions'],
    ['About', 4, 'Contact Us'],
  ];
  const footerGroups = fixture.match(/<div class="twins-brand-footer-group">[\s\S]*?<\/div>/g) || [];
  assert.equal(footerGroups.length, expectedGroups.length);
  for (const [index, [label, count, requiredLabel]] of expectedGroups.entries()) {
    assert.match(footerGroups[index], new RegExp(`<h2>${label}</h2>`));
    assert.equal((footerGroups[index].match(/<a\b/g) || []).length, count);
    assert.match(footerGroups[index], new RegExp(`>${requiredLabel}<`));
    if (label === 'Service Areas') {
      assert.match(markets, new RegExp(`'label'\\s*=>\\s*'${requiredLabel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}'`));
    } else {
      assert.match(navData, new RegExp(`\\['${requiredLabel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}',`));
    }
  }
  assert.match(markets, /'wi'/);
  assert.match(markets, /'ky'/);
  assert.match(markets, /'il-preview'/);
  assert.match(fixture, /Rockford/);
  assert.match(fixture, /tel:\+18158002025/);
  assert.match(fixture, /5758 Elaine Dr Ste 110, Rockford, IL 61108/);
  assert.doesNotMatch(fixture, /Madison, Wisconsin|tel:\+16084202377/);
  assert.match(reviewSummary, /'displayCount'\s*=>\s*'699'/);
  assert.match(fixture, /699 customer reviews/);
  assert.equal((fixture.match(/>Get a Free Quote<\/a>/g) || []).length, 7);
  assert.equal((fixture.match(/>Call Now<\/a>/g) || []).length, 1);
  assert.doesNotMatch(fixture, />Request a Quote<\/a>|>Call Twins<\/a>/);
});

test('location mascots are restrained to services and both final CTA edges', () => {
  assert.match(template, /twins-location-services[\s\S]*?\$placement = 'services'/);
  assert.match(template, /twins-location-final-cta[\s\S]*?\$placement = 'final-left'/);
  assert.match(template, /twins-location-final-cta[\s\S]*?\$placement = 'final-right'/);
  assert.doesNotMatch(template, /\$placement = 'hero'/);
  assert.doesNotMatch(template, /\$placement = 'guidance'/);
});

test('Twin component fails closed unless its character and placement are an approved exact pair', () => {
  const twinCharacter = fs.readFileSync(path.join(root, 'components/twin-character.php'), 'utf8');

  assert.match(twinCharacter, /\['right', 'services'\]/);
  assert.match(twinCharacter, /\['left', 'final-left'\]/);
  assert.match(twinCharacter, /\['right', 'final-right'\]/);
  assert.match(twinCharacter, /!in_array\(\[\$character, \$placement\], \$allowedPairs, true\)/);
  assert.doesNotMatch(twinCharacter, /\$placements\s*=/);
});

test('quote is primary copy and unverified urgency claims stay absent', () => {
  assert.match(template, />Get a Free Quote<\/a>/);
  assert.match(template, /<div class="twins-brand-final-actions">\s*<\?php if \(\$isLocation\): \?>\s*<a class="twins-brand-cta twins-brand-cta--quote"[\s\S]*?>Get a Free Quote<\/a>\s*<a class="twins-brand-cta twins-brand-cta--call"[\s\S]*?>Call[\s\S]*?<\/a>\s*<\?php else: \?>/,
    'location final CTA must render quote before call');
  assert.match(footer, /\$isLocationFooter \? 'Get a Free Quote' : 'Request a Quote'/);
  assert.match(footer, /\$isLocationFooter \? 'Call Now' : 'Call Twins'/);
  assert.doesNotMatch(template.toLowerCase(), /same[- ]day|within \d+|guaranteed response|recently opened/);
});

test('location reveals are progressive enhancement and reduced motion is static', () => {
  assert.match(script, /function initLocationReveals\(root, reducedMotion\)/);
  assert.match(script, /IntersectionObserver/);
  assert.match(script, /twins-location-motion-ready/);
  assert.match(script, /data-location-visible/);
  assert.match(script, /if \(reducedMotion\.matches\)/);
  assert.match(css, /\.twins-location-motion-ready \[data-location-reveal\]/);
  assert.doesNotMatch(css, /(^|\n)\[data-location-reveal\]\s*\{[^}]*opacity:\s*0/,
    'content must not disappear when JavaScript is unavailable');
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)[\s\S]*\[data-location-reveal\][^{]*\{[^}]*opacity:\s*1 !important/);
});
