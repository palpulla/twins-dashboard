const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const template = fs.readFileSync(path.join(root, 'templates/editorial.php'), 'utf8');
const locationContent = fs.readFileSync(path.join(root, 'config/location-content.php'), 'utf8');
const footer = fs.readFileSync(path.join(root, 'components/footer.php'), 'utf8');
const experience = fs.readFileSync(path.join(root, 'src/Experience.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/css/twins-brand.css'), 'utf8');
const navData = fs.readFileSync(path.join(root, 'components/nav-data.php'), 'utf8');
const markets = fs.readFileSync(path.join(root, 'config/markets.php'), 'utf8');
const fixture = fs.readFileSync(path.join(root, 'tests/browser/fixtures/location-modern.html'), 'utf8');

test('location template contains the approved high-density landing page sections', () => {
  for (const className of [
    'twins-location-hero',
    'twins-location-services',
    'twins-location-guidance',
    'twins-location-process',
    'twins-location-branch',
    'twins-location-nearby',
  ]) {
    assert.match(template, new RegExp(className), `${className} is missing`);
  }
  assert.doesNotMatch(template, /service-areas-panel\.php/,
    'location pages must not render the oversized all-city door panel');
});

test('location hero is one cinematic composition with integrated proof', () => {
  for (const className of [
    'twins-location-hero-stage',
    'twins-location-title-accent',
    'twins-location-hero-media',
    'twins-location-orbit',
    'twins-location-hero-proof',
  ]) {
    assert.match(template, new RegExp(className), `${className} is missing from the cinematic hero`);
  }
  assert.match(template, /Get a Free Quote/);
  assert.match(template, /role="list"/);
  assert.match(template, /role="listitem"/);
  assert.doesNotMatch(template, /<\/header>\s*<section class="twins-location-proof"/,
    'proof must remain inside the unified hero stage');
});

test('cinematic hero becomes a contained copy-media-proof sequence on narrow screens', () => {
  assert.match(css, /@media \(max-width: 1024px\)[\s\S]*?\.twins-location-hero-stage\s*\{[^}]*display:\s*grid[^}]*grid-template-columns:\s*1fr/);
  assert.match(css, /@media \(max-width: 1024px\)[\s\S]*?\.twins-location-hero-media\s*\{[^}]*position:\s*relative[^}]*inset:\s*auto[^}]*width:\s*auto/);
  assert.match(css, /@media \(max-width: 1024px\)[\s\S]*?\.twins-location-hero-proof\s*\{[^}]*position:\s*relative[^}]*grid-template-columns:\s*1fr/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*?\.twins-location-hero-copy\s*\{[^}]*width:\s*100%/);
  assert.match(css, /@media \(max-width: 480px\)[\s\S]*?\.twins-location-hero-media\s*\{[^}]*min-height:\s*clamp\(220px,\s*70vw,\s*300px\)/);
  assert.match(css, /@media \(max-width: 480px\)[\s\S]*?\.twins-location-page \.twins-location-twin--hero\s*\{[^}]*display:\s*none/);
});

test('location service navigation has one repair destination and three explained cards', () => {
  assert.match(template, /twins-location-service-card/);
  assert.equal((template.match(/\['Garage Door Repair', 'repair'\]/g) || []).length, 1);
  assert.match(template, /Garage door opener service/);
  assert.match(template, /Garage door installation/);
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

test('location template expands city FAQs with three practical shared answers', () => {
  assert.match(template, /Can you repair my garage door, or will it need to be replaced\?/);
  assert.match(template, /Do you service garage door openers\?/);
  assert.match(template, /What should I do if a spring or cable breaks\?/);
  assert.match(template, /array_slice\(array_merge\(\$editorialFaqs, \$locationSharedFaqs\), 0, 5\)/);
});

test('footer uses route context instead of a hard-coded Madison address', () => {
  assert.doesNotMatch(footer, /<span>2921 Landmark Pl #206, Madison, WI 53713<\/span>/);
  assert.match(footer, /\$context\['metroAddress'\]/);
  assert.match(footer, /\$market\['address'\]/);
  assert.match(experience, /metroAddressForContext/);
  assert.match(experience, /5758 Elaine Dr Ste 110, Rockford, IL 61108/);
});

test('location pages move the animated door up and give every service a fixed visual', () => {
  assert.match(template, /twins-location-system/);
  assert.match(template, /twins_brand_door_art\('door-open', 'twins-location-system-art', 'location-system'\)/);
  assert.match(template, /'art' => 'spring'/);
  assert.match(template, /'art' => 'keypad'/);
  assert.match(template, /'art' => 'door'/);
  assert.match(template, /twins-location-service-art/);
  assert.match(template, /\$finalCtaArtKind = \$isLocation \? 'door' : 'door-open'/);
  assert.match(css, /\.twins-location-system/);
  assert.match(css, /\.twins-location-service-art/);
});

test('full location fixture retains exact generated door curtain classes and the WI footer catalog', () => {
  const arts = fixture.match(/<svg\b[^>]*twins-brand-door-art--(?:door-open|door)\b[\s\S]*?<\/svg>/g) || [];
  assert.equal(arts.length, 3, 'fixture must include system, final-CTA, and footer door art');
  assert.match(arts[0], /^<svg viewBox="0 0 220 190" class="twins-brand-door-art twins-brand-door-art--door-open twins-location-system-art" aria-hidden="true" focusable="false">/);
  assert.match(arts[0], /<defs><clipPath id="twins-door-clip-location-system"><rect x="20" y="20" width="180" height="150" rx="4"\/><\/clipPath><\/defs><rect x="2" y="2" width="216" height="186" rx="10" class="twins-da-gold"\/><rect x="11" y="11" width="198" height="168" rx="6" class="twins-da-navy"\/><rect x="20" y="20" width="180" height="150" rx="4" class="twins-da-interior"\/><ellipse cx="110" cy="168" rx="74" ry="26" class="twins-da-glow"\/><rect x="34" y="150" width="152" height="6" rx="3" class="twins-da-floor"\/><g clip-path="url\(#twins-door-clip-location-system\)"><g class="twins-da-curtain"><rect x="20" y="20" width="180" height="150" rx="4" class="twins-da-face"\/>/);
  const expectedPlainClasses = [
    'twins-brand-cta-art',
    'twins-brand-footer-door',
  ];
  for (const [index, extraClass] of expectedPlainClasses.entries()) {
    const art = arts[index + 1];
    assert.match(art, new RegExp(`^<svg viewBox="0 0 220 190" class="twins-brand-door-art twins-brand-door-art--door ${extraClass}" aria-hidden="true" focusable="false"><rect x="2" y="2" width="216" height="186" rx="10" class="twins-da-gold"\\/><rect x="11" y="11" width="198" height="168" rx="6" class="twins-da-navy"\\/><rect x="20" y="20" width="180" height="150" rx="4" class="twins-da-face"\\/><rect x="26.0" y="26.0"`));
  }
  assert.doesNotMatch(fixture, /twins-da-gold-outer|viewBox="0 0 240 220"/);
  for (const art of arts) {
    assert.equal((art.match(/class="twins-da-window-frame"/g) || []).length, 4);
    assert.equal((art.match(/class="twins-da-glass"/g) || []).length, 4);
    assert.equal((art.match(/class="twins-da-glass-hi"/g) || []).length, 4);
    assert.equal((art.match(/class="twins-da-panel"/g) || []).length, 12);
    assert.equal((art.match(/class="twins-da-panel-inner"/g) || []).length, 12);
  }
  assert.match(arts[0], /class="twins-da-curtain"/);

  const expectedGroups = [
    ['Services', 9, 'Garage Door Installation'],
    ['Garage Doors', 3, 'Garage Door Collections'],
    ['Service Areas', 3, 'Illinois'],
    ['Resources', 6, 'Wisconsin Garage Door Cost Guide'],
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
});

test('location sections use accessible CSS-only garage door panel framing', () => {
  for (const section of [
    'system',
    'services',
    'guidance',
    'process',
    'branch',
    'nearby',
    'faq',
  ]) {
    assert.match(css, new RegExp(`\\.twins-location-${section}`),
      `location texture CSS omits ${section}`);
  }

  for (const token of [
    '--twins-location-panel-width',
    '--twins-location-panel-opacity',
    '--twins-location-panel-line',
    '--twins-location-panel-track',
  ]) {
    assert.match(css, new RegExp(token), `location texture CSS omits ${token}`);
  }

  assert.match(css, /pointer-events:\s*none/);
  assert.match(css, /isolation:\s*isolate/);
  assert.match(css, /\.twins-location-system[\s\S]*\.twins-location-faq[\s\S]*::before/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*--twins-location-panel-width:\s*150px/);
  assert.match(css, /@media \(max-width: 480px\)[\s\S]*--twins-location-panel-width:\s*120px/);
  assert.doesNotMatch(template, /twins-location-panel/,
    'decorative location textures must not add template markup');
});

test('location design preserves the old display font and uses restrained premium geometry', () => {
  assert.match(css, /\.twins-location-hero h1\s*\{[^}]*font-family:\s*'Lilita One'/);
  assert.match(css, /\.twins-location-title-accent\s*\{[^}]*color:\s*var\(--twins-gold\)/);
  assert.match(css, /\.twins-location-hero-media\s*\{[^}]*position:\s*absolute/);
  assert.match(css, /\.twins-location-hero-proof\s*\{[^}]*backdrop-filter:\s*blur/);
  assert.doesNotMatch(css, /\.twins-location-hero-media\s*\{[^}]*border-left:/,
    'the hero photo must not return to a boxed split-column treatment');
  assert.match(css, /\.twins-location-service-card\s*\{[^}]*border:\s*1px solid/);
  assert.doesNotMatch(css, /\.twins-location-service-card\s*\{[^}]*box-shadow:\s*8px 9px 0/);
  assert.match(css, /\.twins-location-page \.twins-location-twin\s*\{[^}]*pointer-events:\s*none/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--hero\s*\{[^}]*clamp\(72px,\s*7vw,\s*104px\)/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--guidance\s*\{[^}]*clamp\(92px,\s*8vw,\s*124px\)/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--final-right\s*\{[^}]*clamp\(96px,\s*9vw,\s*132px\)/);
  assert.match(css, /@media \(max-width: 480px\)\s*\{[\s\S]*?\.twins-location-page \.twins-location-guidance,\s*\.twins-location-page \.twins-location-final-cta\s*\{[^}]*padding-bottom:\s*210px/,
    'small screens must reserve 210px for the right Twin and content clearance');
  assert.match(css, /\.twins-location-branch aside\s*\{[^}]*color:\s*var\(--twins-navy-950\)[^}]*background:\s*var\(--twins-white\)[^}]*border:\s*1px solid rgba\(255,200,61,\.72\)/);
  assert.doesNotMatch(css, /\.twins-location-branch aside\s*\{[^}]*background:\s*var\(--twins-gold\)/,
    'the branch panel must not return to a full gold surface');
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.twins-location-page \.twins-location-twin\s*\{[^}]*animation:\s*none !important[^}]*transform:\s*none !important/);
  assert.doesNotMatch(css, /twins-location-twin--system|twins-location-twin--final-left/);
  assert.doesNotMatch(css, /(^|\n)\s*\.twins-location-twin/,
    'location Twin selectors must never escape the location-page scope');
  assert.match(css, /@media \(max-width: 480px\)[\s\S]*?\.twins-location-hero\s*\{[^}]*gap:\s*24px[^}]*padding:\s*30px 20px/,
    'small screens must bring the real hero photo into the first viewport');
  assert.match(css, /@media \(max-width: 480px\)[\s\S]*?\.twins-location-hero h1\s*\{[^}]*font-size:\s*clamp\(2\.35rem,\s*11vw,\s*2\.75rem\)/,
    'small screens must use the compact readable hero heading scale');
});

test('location mascots are restrained to hero, guidance, and one final CTA cameo', () => {
  assert.match(template, /twins-location-hero-media[\s\S]*?\$placement = 'hero'/);
  assert.match(template, /twins-location-guidance[\s\S]*?\$placement = 'guidance'/);
  assert.match(template, /twins-location-final-cta[\s\S]*?\$placement = 'final-right'/);
  assert.doesNotMatch(template, /\$placement = 'system'/);
  assert.doesNotMatch(template, /\$placement = 'final-left'/);
});

test('Twin component fails closed unless its character and placement are an approved exact pair', () => {
  const twinCharacter = fs.readFileSync(path.join(root, 'components/twin-character.php'), 'utf8');

  assert.match(twinCharacter, /\['left', 'hero'\]/);
  assert.match(twinCharacter, /\['right', 'guidance'\]/);
  assert.match(twinCharacter, /\['right', 'final-right'\]/);
  assert.match(twinCharacter, /!in_array\(\[\$character, \$placement\], \$allowedPairs, true\)/);
  assert.doesNotMatch(twinCharacter, /\$placements\s*=/);
});
