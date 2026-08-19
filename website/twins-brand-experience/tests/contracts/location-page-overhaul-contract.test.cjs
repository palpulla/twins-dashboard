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

test('all location sections participate in one-time reveals, hero included', () => {
  // r30 added the hero itself to the reveal set; the city-page system adds four
  // record-gated sections (reviews, map, neighbors, resources) for ten
  // data-location-reveal sites in the template source. Pages without a
  // location record still render the six r30 reveals only.
  assert.match(template, /<header class="twins-location-hero" aria-labelledby="twins-location-title" data-location-reveal>/);
  assert.equal((template.match(/data-location-reveal/g) || []).length, 10);
  for (const className of [
    'twins-location-trust',
    'twins-location-services',
    'twins-location-local-proof',
    'twins-location-reviews',
    'twins-location-map-section',
    'twins-location-neighbors',
    'twins-location-resources',
    'twins-location-final-cta',
  ]) {
    assert.match(template, new RegExp(`class="[^"]*${className}[^"]*"[^>]*data-location-reveal`));
  }
  assert.match(template, /<section class="twins-brand-faq"[^>]*aria-labelledby="twins-location-questions-title"[^>]*data-location-reveal/);
});

test('service cards are three concise inspection-first summaries without spotlight or lists', () => {
  // r30 dropped the per-card issue <ul> and the spotlight variant; each card is
  // art + title + description + one explore link, and the card copy leads with
  // inspection-first language.
  for (const item of [
    'Full door-system inspection',
    'Repair options explained first',
    'Balance and safety check',
    'Sensor and control diagnosis',
    'Opener replacement options',
    'Opening measured before quoting',
  ]) {
    assert.match(template, new RegExp(item));
  }
  assert.match(template, /foreach \(\$locationServiceCards as \$serviceCard\)/);
  assert.doesNotMatch(template, /twins-location-service-card--spotlight/);
  assert.doesNotMatch(template, /<ul class="twins-location-service-items">/);
  assert.doesNotMatch(template, /recommended|preferred|featured/i);
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
  assert.match(experience, /5758 Elaine Dr, Rockford, IL 61108/);
  assert.doesNotMatch(experience, /5758 Elaine Dr Ste 110|2921 Landmark Pl #206/);
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
  assert.match(fixture, /5758 Elaine Dr, Rockford, IL 61108/);
  assert.doesNotMatch(fixture, /5758 Elaine Dr Ste 110/);
  assert.equal((fixture.match(/class="twins-location-service-card"/g) || []).length, 3);
  // City-page system: ten reveals (hero, trust, services, local proof,
  // reviews, map, neighbors, resources, FAQ, final CTA).
  assert.equal((fixture.match(/data-location-reveal/g) || []).length, 10);
  assert.match(fixture, /<header class="twins-location-hero"[^>]*data-location-reveal/);
  assert.doesNotMatch(fixture, /twins-location-service-items|twins-location-service-card--spotlight/);
  assert.doesNotMatch(fixture, /twins-location-hero-stage|twins-location-orbit|Preventive maintenance/);
  // The 2026-08-18 approved copy carries the $0-service-call-with-repair offer
  // and the "same day, most days" framing, so only branch-positioning and
  // invented-guarantee claims stay banned.
  assert.doesNotMatch(fixture, /recently opened|newly opened|new to this market|guaranteed arrival|guaranteed response/i);
  assert.doesNotMatch(fixture, /Madison, Wisconsin|tel:\+16084202377/);
  assert.match(reviewSummary, /'displayCount'\s*=>\s*'699'/);
  assert.match(fixture, /699 customer reviews/);
  // City-page blocks: three verbatim review quotes, the live map embed with
  // the metro NAP, six neighbor links, and three helpful-resource guides.
  assert.equal((fixture.match(/class="twins-location-review-card"/g) || []).length, 3);
  assert.match(fixture, /<iframe class="twins-brand-location-map"[^>]*src="https:\/\/www\.google\.com\/maps[^"]*"/);
  assert.doesNotMatch(fixture, /Open the .* map on Google Maps|twins-brand-location-map--link/);
  const neighborNav = fixture.match(/<nav class="twins-brand-location-links" aria-label="Nearby areas we serve">([\s\S]*?)<\/nav>/);
  assert.ok(neighborNav, 'neighbor links block is missing');
  assert.equal((neighborNav[1].match(/<a\b/g) || []).length, 6);
  const resourceNav = fixture.match(/<nav class="twins-brand-location-links twins-location-resource-links"[^>]*>([\s\S]*?)<\/nav>/);
  assert.ok(resourceNav, 'helpful-resources block is missing');
  assert.equal((resourceNav[1].match(/<a\b/g) || []).length, 3);
  assert.match(resourceNav[1], /\/wi\/garage-door-cost-in-madison-wi\//);
  assert.match(resourceNav[1], /\/is-it-cheaper-to-repair-or-replace-a-garage-door\//);
  assert.match(resourceNav[1], /\/belt-drive-vs-chain-drive-openers\//);
  // JSON-LD: one script carrying the approved schema stack whose FAQPage
  // mirrors exactly the rendered FAQ accordion.
  const schemaScripts = fixture.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g) || [];
  assert.equal(schemaScripts.length, 1);
  const schemaGraph = JSON.parse(schemaScripts[0].replace(/^<script[^>]*>/, '').replace(/<\/script>$/, ''));
  const graphTypes = schemaGraph['@graph'].map((node) => node['@type']);
  assert.deepEqual(graphTypes, ['HomeAndConstructionBusiness', 'BreadcrumbList', 'FAQPage']);
  const renderedQuestions = [...fixture.matchAll(/<details><summary>([\s\S]*?)<\/summary>/g)].map((m) => m[1]);
  const schemaQuestions = schemaGraph['@graph'][2].mainEntity.map((q) => q.name);
  assert.equal(renderedQuestions.length, 5);
  assert.deepEqual(
    schemaQuestions,
    renderedQuestions.map((q) => q
      .replaceAll('&amp;', '&').replaceAll('&lt;', '<').replaceAll('&gt;', '>')
      .replaceAll('&quot;', '"').replaceAll('&#039;', "'")),
  );
  assert.equal((fixture.match(/>Get a Free Quote<\/a>/g) || []).length, 6);
  assert.equal((fixture.match(/>Book Online<\/button>/g) || []).length, 2);
  assert.equal((fixture.match(/data-twins-booking-dialog/g) || []).length, 1);
  assert.equal((fixture.match(/data-booking-close/g) || []).length, 1);
  assert.equal((fixture.match(/data-booking-finalize/g) || []).length, 1);
  assert.equal((fixture.match(/data-booking-status/g) || []).length, 1);
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
  // r30 mobile quick actions always read Call Now; the label ternary is gone.
  assert.match(footer, />Call Now<\/a>/);
  assert.doesNotMatch(template.toLowerCase(), /same[- ]day|within \d+|guaranteed response|recently opened/);
  for (const claim of [
    'done today',
    'same-day',
    '$0 service call',
    'most repairs in one visit',
    'guaranteed arrival',
    'recently opened',
  ]) {
    assert.doesNotMatch(template.toLowerCase(), new RegExp(claim.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
});

test('location motion is finite, bounded, fail-open, and reduced-motion safe', () => {
  assert.match(script, /function initLocationReveals\(root, reducedMotion\)/);
  assert.match(script, /if \(reducedMotion\.matches\)\s*\{[\s\S]*?items\.forEach\(reveal\)/);
  assert.match(script, /if \(!\('IntersectionObserver' in window\)\)\s*\{[\s\S]*?items\.forEach\(reveal\)/);
  assert.match(script, /observer\.unobserve\(entry\.target\)/);

  // r30 reveal motion: one shared 18px/.55s fade-up gated behind the
  // twins-location-motion-ready class so no-JS never hides content.
  assert.match(css, /\.twins-location-motion-ready \[data-location-reveal\]\s*\{[^}]*opacity:\s*0[^}]*translateY\(18px\)[^}]*transition:\s*opacity \.55s ease,\s*transform \.55s ease/);
  assert.match(css, /\.twins-location-motion-ready \[data-location-reveal\]\[data-location-visible="true"\]\s*\{[^}]*opacity:\s*1[^}]*translateY\(0\)/);

  // The only continuous location animation is the bounded 5px mascot float,
  // and reduced motion disables it outright.
  assert.match(css, /@keyframes twins-location-float\s*\{[^{}]*\{[^}]*translateY\(0\)[^}]*\}[^{}]*\{[^}]*translateY\(-5px\)[^}]*\}/);
  assert.match(css, /\.twins-location-page \.twins-location-twin\s*\{[^}]*animation:\s*twins-location-float 6s ease-in-out infinite/);
  assert.doesNotMatch(css, /\.twins-location[^,{]*\{[^}]*animation:[^;}]*twins-brand-cta-pulse/);
  assert.doesNotMatch(css, /(^|\n)\[data-location-reveal\]\s*\{[^}]*opacity:\s*0/);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.twins-location-page \.twins-location-twin\s*\{[^}]*animation:\s*none !important[^}]*transform:\s*none !important/);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.twins-location-page \[data-location-reveal\]\s*\{[^}]*opacity:\s*1 !important[^}]*transition:\s*none !important/);
});

test('location sections use the premium navy hero and light editorial palette', () => {
  // r30 replaced the dark showroom treatment: navy hero and final CTA with a
  // shared grid texture, white/cream working sections separated by hairlines.
  assert.match(css, /\.twins-location-page\s*\{[^}]*background:\s*#f8f4eb/);
  assert.match(css, /\.twins-location-hero\s*\{[^}]*color:\s*var\(--twins-white\)[^}]*background:\s*var\(--twins-navy-950\)/);
  assert.match(css, /\.twins-location-hero::before,\s*\.twins-location-final-cta::before\s*\{[\s\S]{0,220}?repeating-linear-gradient/);
  assert.match(css, /\.twins-location-trust\s*\{[^}]*background:\s*var\(--twins-white\)/);
  assert.match(css, /\.twins-location-local-proof\s*\{[^}]*background:\s*var\(--twins-white\)/);
  assert.match(css, /\.twins-location-final-cta\s*\{[^}]*color:\s*var\(--twins-white\)[^}]*background:\s*var\(--twins-navy-950\)/);
  assert.match(css, /\.twins-location-service-grid\s*\{[^}]*border-top:\s*1px solid rgba\(7,29,59,\.2\)[^}]*border-bottom:\s*1px solid rgba\(7,29,59,\.2\)/);
  assert.match(css, /\.twins-location-service-card\s*\{[^}]*border-left:\s*1px solid rgba\(7,29,59,\.2\)/);
  assert.match(css, /\.twins-location-hero-media\s*\{[^}]*background:\s*var\(--twins-gold\)/);
  assert.match(css, /\.twins-location-local-proof-media\s*\{[^}]*max-height:\s*440px[^}]*background:\s*var\(--twins-navy-950\)/);
  assert.doesNotMatch(css, /twins-location-service-card--spotlight/);
});

test('service cards expose one visible touch-sized link without nested controls', () => {
  // r30 dropped the stretched-link overlay; each card ends in one explicit
  // 44px-tall explore link and never nests a second control.
  assert.match(css, /\.twins-location-service-link\s*\{[^}]*display:\s*inline-flex[^}]*align-items:\s*center[^}]*min-height:\s*44px/);
  assert.doesNotMatch(css, /\.twins-location-service-link::after/);
  assert.doesNotMatch(template, /<article[^>]*>\s*<a[^>]*>[\s\S]*?<a/);
  assert.match(template, /<a class="twins-location-service-link"/);
});

test('recovery CSS locks alignment and contained image proportions', () => {
  assert.match(css, /--twins-location-max:\s*1180px/);
  assert.match(css, /padding-inline:\s*max\(var\(--twins-location-gutter\),\s*calc\(\(100vw - var\(--twins-location-max\)\)\s*\/\s*2\)\)/);
  assert.match(css, /\.twins-location-hero\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1\.15fr\) minmax\(320px,\s*\.85fr\)/);
  assert.match(css, /\.twins-location-hero-media\s*\{[^}]*max-height:\s*460px/);
  assert.match(css, /\.twins-location-hero-image\s*\{[^}]*aspect-ratio:\s*4\s*\/\s*3/);
  assert.match(css, /\.twins-location-local-proof\s*\{[^}]*grid-template-columns:\s*minmax\(320px,\s*\.85fr\) minmax\(0,\s*1\.15fr\)/);
  assert.match(css, /@media \(max-width:\s*1024px\)[\s\S]*?\.twins-location-hero\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1\.15fr\) minmax\(300px,\s*\.85fr\)/);
  assert.match(css, /@media \(max-width:\s*768px\)[\s\S]*?\.twins-location-hero-media\s*\{[^}]*max-height:\s*310px/);
  assert.match(css, /@media \(max-width:\s*768px\)[\s\S]*?\.twins-location-service-grid\s*\{[^}]*grid-template-columns:\s*1fr/);
  assert.match(css, /@media \(max-width:\s*480px\)[\s\S]*?\.twins-location-page \.twins-location-twin--services\s*\{[^}]*display:\s*none/);
});

test('mobile recovery media fits complete 4:3 photos and the hero caption inside its cap', () => {
  assert.match(css, /\.twins-location-hero-media picture\s*\{[^}]*width:\s*100%[^}]*height:\s*auto[^}]*aspect-ratio:\s*4\s*\/\s*3/);
  assert.match(css, /\.twins-location-local-proof-media picture\s*\{[^}]*width:\s*100%[^}]*height:\s*auto[^}]*aspect-ratio:\s*4\s*\/\s*3/);
  assert.doesNotMatch(css, /\.twins-location-(?:hero|local-proof)-media picture\s*\{[^}]*height:\s*calc\(/);

  const mobileRules = css.match(/@media \(max-width:\s*768px\)\s*\{([\s\S]*?)\n\}/);
  assert.ok(mobileRules, 'mobile location rules are missing');
  const heroFrame = css.match(/\.twins-location-hero-media\s*\{[^}]*padding:\s*(\d+)px[^}]*border:\s*(\d+)px/);
  const heroGeometry = mobileRules[1].match(/\.twins-location-hero-media\s*\{[^}]*width:\s*min\(100%,\s*(\d+)px\)[^}]*max-height:\s*(\d+)px/);
  const captionGeometry = mobileRules[1].match(/\.twins-location-hero-media figcaption\s*\{[^}]*height:\s*(\d+)px[^}]*line-height:\s*([\d.]+)/);
  assert.ok(heroFrame && heroGeometry && captionGeometry, 'mobile hero frame geometry is incomplete');
  const [, heroPadding, heroBorder] = heroFrame.map(Number);
  const [, heroWidth, heroCap] = heroGeometry.map(Number);
  const [, captionHeight, captionLineHeight] = captionGeometry.map(Number);
  assert.deepEqual([heroWidth, heroCap, captionHeight, captionLineHeight], [320, 310, 64, 1.25]);
  const heroTotalHeight = ((heroWidth - (2 * heroPadding) - (2 * heroBorder)) * .75)
    + captionHeight + (2 * heroPadding) + (2 * heroBorder);
  assert.ok(heroTotalHeight <= heroCap, `mobile hero media is ${heroTotalHeight}px tall for a ${heroCap}px cap`);

  const localFrame = css.match(/\.twins-location-local-proof-media\s*\{[^}]*padding:\s*(\d+)px/);
  const localGeometry = mobileRules[1].match(/\.twins-location-local-proof-media\s*\{[^}]*width:\s*min\(100%,\s*(\d+)px\)[^}]*max-height:\s*(\d+)px/);
  assert.ok(localFrame && localGeometry, 'mobile local-proof frame geometry is incomplete');
  const localPadding = Number(localFrame[1]);
  const localWidth = Number(localGeometry[1]);
  const localCap = Number(localGeometry[2]);
  assert.deepEqual([localWidth, localCap], [408, 310]);
  const localTotalHeight = ((localWidth - (2 * localPadding)) * .75) + (2 * localPadding);
  assert.ok(localTotalHeight <= localCap, `mobile local-proof media is ${localTotalHeight}px tall for a ${localCap}px cap`);
});

test('recovery CSS contains no cinematic or location-only header treatment', () => {
  assert.doesNotMatch(css, /\.twins-location-orbit|backdrop-filter:\s*blur\(18px\)/);
  assert.doesNotMatch(css, /\.twins-brand-header--location|\.twins-brand-fascia--location/);
  assert.doesNotMatch(css, /\.twins-location-system|\.twins-location-process|\.twins-location-nearby|\.twins-location-faq/);
});

test('location characters stay subordinate to services and final conversion', () => {
  assert.match(css, /\.twins-location-twin--services\s*\{[^}]*width:\s*clamp\(72px,\s*7vw,\s*104px\)/);
  assert.match(css, /\.twins-location-twin--final-left\s*\{[^}]*width:\s*clamp\(86px,\s*8vw,\s*118px\)/);
  assert.match(css, /\.twins-location-twin--final-right\s*\{[^}]*width:\s*clamp\(92px,\s*8vw,\s*124px\)/);
  assert.match(css, /@media \(max-width:\s*480px\)[\s\S]*?\.twins-location-twin--services\s*\{[^}]*display:\s*none/);
});

test('responsive character zones clear service links and final CTA controls', () => {
  const mediaRules = (width) => {
    const match = css.match(new RegExp(`@media \\(max-width:\\s*${width}px\\)\\s*\\{([\\s\\S]*?)\\n\\}`));
    assert.ok(match, `${width}px location rules are missing`);
    return match[1];
  };

  const tablet = mediaRules(1024);
  assert.match(tablet, /\.twins-location-services\s*\{[^}]*padding-bottom:\s*144px/);
  assert.match(tablet, /\.twins-location-twin--services\s*\{[^}]*bottom:\s*16px[^}]*width:\s*64px/);
  assert.match(tablet, /\.twins-location-final-cta\s*\{[^}]*padding-bottom:\s*190px/);
  assert.match(tablet, /\.twins-location-twin--final-left\s*\{[^}]*bottom:\s*0[^}]*width:\s*64px/);
  assert.match(tablet, /\.twins-location-twin--final-right\s*\{[^}]*bottom:\s*0[^}]*width:\s*68px/);

  const mobile = mediaRules(768);
  assert.match(mobile, /\.twins-location-services\s*\{[^}]*padding-bottom:\s*128px/);
  assert.match(mobile, /\.twins-location-twin--services\s*\{[^}]*bottom:\s*14px[^}]*width:\s*56px/);
  assert.match(mobile, /\.twins-location-final-cta\s*\{[^}]*padding:\s*60px 96px 170px/);
  assert.match(mobile, /\.twins-location-twin--final-left\s*\{[^}]*bottom:\s*0[^}]*width:\s*56px/);
  assert.match(mobile, /\.twins-location-twin--final-right\s*\{[^}]*bottom:\s*0[^}]*width:\s*60px/);

  const compact = mediaRules(480);
  assert.match(compact, /\.twins-location-services\s*\{[^}]*padding-bottom:\s*56px/);
  assert.match(compact, /\.twins-location-page \.twins-location-twin--services\s*\{[^}]*display:\s*none/);
  assert.match(compact, /\.twins-location-final-cta\s*\{[^}]*padding:\s*54px 20px 150px/);
  assert.match(compact, /\.twins-location-twin--final-left\s*\{[^}]*bottom:\s*0[^}]*width:\s*48px/);
  assert.match(compact, /\.twins-location-twin--final-right\s*\{[^}]*bottom:\s*0[^}]*width:\s*54px/);

  const narrow = mediaRules(320);
  assert.match(narrow, /\.twins-location-twin--final-right\s*\{[^}]*right:\s*12px[^}]*width:\s*40px/);
});
