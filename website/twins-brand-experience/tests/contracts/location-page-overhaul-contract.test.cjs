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

test('location template contains the approved high-density landing page sections', () => {
  for (const className of [
    'twins-location-hero',
    'twins-location-proof',
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

test('location Twin characters are scoped, non-interactive, responsive, and motion-safe', () => {
  assert.match(css, /\.twins-location-page \.twins-location-twin\s*\{[\s\S]*?pointer-events:\s*none/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--system\s*\{[\s\S]*?clamp\(132px,\s*12vw,\s*176px\)/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--guidance\s*\{[\s\S]*?clamp\(180px,\s*16vw,\s*218px\)/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--final-left\s*\{[\s\S]*?clamp\(128px,\s*11vw,\s*166px\)/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--final-right\s*\{[\s\S]*?clamp\(180px,\s*16vw,\s*224px\)/);
  assert.match(css, /twins-location-float-left 4\.8s ease-in-out infinite/);
  assert.match(css, /twins-location-float-right 6\.2s ease-in-out \.7s infinite/);
  assert.match(css, /@keyframes twins-location-float-left[\s\S]*translateY\(-6px\) rotate\(-1\.25deg\)/);
  assert.match(css, /@keyframes twins-location-float-right[\s\S]*translateY\(-6px\) rotate\(1\.25deg\)/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*\.twins-location-page \.twins-location-twin--system[\s\S]*width:\s*min\(112px,\s*29vw\)/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*\.twins-location-page \.twins-location-twin--guidance[\s\S]*width:\s*min\(142px,\s*37vw\)/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*\.twins-location-page \.twins-location-twin--final-left\s*\{\s*display:\s*none/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*\.twins-location-page \.twins-location-twin--final-right[\s\S]*width:\s*min\(148px,\s*38vw\)/);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)[\s\S]*\.twins-location-page \.twins-location-twin[\s\S]*animation:\s*none !important[\s\S]*transform:\s*none !important/);
  assert.doesNotMatch(css, /(^|\n)\s*\.twins-location-twin/,
    'location Twin selectors must never escape the location-page scope');
});
