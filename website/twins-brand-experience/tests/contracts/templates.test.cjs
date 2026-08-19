const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const template = name => fs.readFileSync(path.join(root, 'templates', name), 'utf8');

test('home contains every approved scene in order', () => {
  // r30 home story: hero + proof ticker in the template, then the scene
  // components in their include order.
  const html = template('home.php');
  const markers = ['brand-hero', 'data-home-ticker', 'components/home/company-story.php', 'components/home/service-showcase.php', 'review-slider', 'components/home/service-journey.php', 'components/home/why-doors.php', 'components/home/closing.php', 'components/home/structured-data.php'];
  let cursor = -1;
  for (const marker of markers) {
    const next = html.indexOf(marker);
    assert.ok(next > cursor, `${marker} is missing or out of order`);
    cursor = next;
  }
  assert.match(html, /Garage door trouble\? <em>Call the Twins\.<\/em>/);
  assert.match(html, /twins-brand-hero-tag/);
  assert.match(html, /\$0 Service Call With Repair/);
  assert.match(html, /twins-brand-hero-proof/);
  assert.match(html, /Licensed and insured/);
  assert.match(html, /Family owned and operated/);
  assert.match(html, /You approve a flat price before any work starts/);
  assert.doesNotMatch(html, /Same-day appointments|Most repairs done in one visit/);
});

test('home carries the approved 2026-08-18 copy set and traceable figures', () => {
  const html = template('home.php');
  // Every dollar figure traces to docs/marketing/website-rebuild/data/price-ranges.json.
  for (const figure of ['$575 and $1,225', '$575 to $1,225', '$325 to $625', '$2,625 to $3,525', '$3,425 to $4,400']) {
    assert.ok(html.includes(figure), `home template lost the approved figure ${figure}`);
  }
  assert.match(html, /Done Right, or We Make It Right\./);
  assert.match(html, /Financing through GoodLeap/);
  assert.doesNotMatch(html, /—|–/, 'home template carries an em- or en-dash');
  assert.doesNotMatch(html, /24\s*\/\s*7|\blifetime\b/i);
  assert.doesNotMatch(html, /verified contact path|verified number/i);
  const journey = fs.readFileSync(path.join(root, 'components/home/service-journey.php'), 'utf8');
  assert.match(journey, /Done Right, or We Make It Right\./);
  const closing = fs.readFileSync(path.join(root, 'components/home/closing.php'), 'utf8');
  assert.match(closing, /TwinShield\. The no-surprises plan\./);
  assert.match(closing, /GoodLeap/);
  assert.doesNotMatch(closing, /\$\d+\s*(?:\/|per)\s*(?:mo|month|year)/i, 'TwinShield card must not invent a price');
});

test('home structured data is Organization + FAQPage and defers LocalBusiness to the renderer', () => {
  const schema = fs.readFileSync(path.join(root, 'components/home/structured-data.php'), 'utf8');
  assert.match(schema, /function_exists\('home_url'\)/);
  assert.match(schema, /'@type' => 'Organization'/);
  assert.match(schema, /'slogan' => 'Your Garage Door, Done Right\.'/);
  assert.match(schema, /'@type' => 'FAQPage'/);
  assert.match(schema, /\$homeFaqs\s*\)/, 'FAQPage must be built from the rendered $homeFaqs list');
  assert.doesNotMatch(schema, /'@type' => 'LocalBusiness'/, 'renderers.php owns the LocalBusiness node for home-brand');
  assert.match(schema, /markets\.php/);
});

test('team and careers use real fixed picture keys', () => {
  assert.match(template('team.php'), /crew-fleet/);
  assert.match(template('team.php'), /tal-portrait/);
  assert.match(template('careers.php'), /crew-fleet|technician-at-work/);
  assert.doesNotMatch(template('careers.php'), /https?:\/\/|fetch\s*\(|TWINS_ENDPOINT|type="submit"|<form/i);
});

test('all redesigned templates use exact quote copy', () => {
  // r30 home converts through Book Online and Call Now instead of a quote CTA.
  for (const name of ['team.php', 'careers.php', 'contact.php', 'reviews.php', 'blog-index.php']) {
    const html = template(name);
    assert.doesNotMatch(html, /Get an Estimate|Request Exact Quote/);
    assert.match(html, /Request a Quote/);
  }
  const home = template('home.php');
  assert.doesNotMatch(home, /Get an Estimate|Request Exact Quote/);
  assert.match(home, /Book Online/);
  assert.match(home, /Call Now/);
});

test('homepage has deterministic hero and truck placements', () => {
  // r30 hero: illustrated SVG door with both eager mascots; the branded truck
  // now anchors the service journey (320w) and closing backdrop (880w).
  const home = template('home.php');
  assert.match(home, /twins-brand-hero[\s\S]*twins-brand-hero-door/);
  assert.match(home, /twins-brand-hero-twin--left[\s\S]*twins-brand-hero-twin--right/);
  assert.equal((home.match(/fetchpriority="high"/g) || []).length, 2);
  const journey = fs.readFileSync(path.join(root, 'components/home/service-journey.php'), 'utf8');
  assert.match(journey, /twins-brand-journey-truck[\s\S]*truck-webp-320/);
  const closing = fs.readFileSync(path.join(root, 'components/home/closing.php'), 'utf8');
  assert.match(closing, /twins-brand-closing-backdrop[\s\S]*truck-webp-880/);
});

test('supporting journeys preserve approved copy and adapter boundaries', () => {
  const team = template('team.php');
  assert.match(team, /Tal Joseph/);
  assert.match(team, /Daniel Joseph/);
  assert.match(team, /Charles Rue/);
  assert.match(team, /Maurice Williams/);
  assert.match(team, /Nicholas Roccaforte/);
  assert.match(team, /Ivory Tianga/);
  assert.match(team, /Aman Kharga/);
  assert.match(team, /daniel-portrait/);
  assert.match(team, /charles-portrait/);
  assert.match(team, /maurice-portrait/);
  assert.match(team, /twins_brand_door_avatar/);
  assert.match(team, /twins-brand-crew-grid/);
  assert.match(team, /Careers/);

  const careers = template('careers.php');
  for (const anchor of ['#why-twins', '#roles', '#process', '#apply']) assert.match(careers, new RegExp(anchor));
  for (const copy of [
    'Do work you are proud to put your name on.',
    'Clear expectations',
    'A customer-first crew',
    'Room to learn the craft',
    'Own the outcome',
    'Treat people right',
    'Keep learning',
    'Service and repair',
    'Installations',
    'Sales and estimates',
    'Customer care and operations',
    'Something else',
    'Share your interest',
    'Quick screen',
    'Meet the team',
    'Clear decision',
    'Tell us where you could make an impact.',
    'Submitting your interest does not guarantee that a position is currently open.',
    'No black hole. Just clear next steps.',
    'Communicate clearly, follow through, and leave the work better than you found it.',
    'Respect the customer, respect the crew, and make the next person’s job easier.',
    'Ask good questions, practice the details, and stay coachable as the work changes.',
    'Diagnose problems, explain options, and restore safe operation.',
    'Deliver clean, careful work on new and replacement doors.',
    'Listen well, educate clearly, and help customers choose confidently.',
    'Keep communication, scheduling, and the day moving smoothly.',
    'Tell us about a skill set that could make the team stronger.',
  ]) assert.match(careers, new RegExp(copy.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  assert.match(careers, /applicationAdapter\(\)->renderExperience\(\$context\)/);

  const contact = template('contact.php');
  assert.match(contact, /quoteAdapter\(\)->renderExperience\(\$context\)/);
  assert.match(contact, /Service areas/);

  const reviews = template('reviews.php');
  assert.match(reviews, /review-slider\.php/);
  assert.match(reviews, /Verified customer reviews/);
});

test('contact always renders approved market phones independently from preview status', () => {
  const contact = template('contact.php');
  assert.match(contact, /phoneDisplay/);
  assert.doesNotMatch(contact, /if \(\$availableMarket\['preview'\] === true\)[\s\S]*Private staging preview[\s\S]*else/s);
});

test('careers binds every staging preview label to the normalized environment', () => {
  const careers = template('careers.php');
  assert.match(careers, /<a href="#apply">\s*<\?php if \(\$environment === 'staging'\): \?>\s*Application preview\s*<\?php else: \?>\s*Apply\s*<\?php endif; \?>\s*<\/a>/);
  assert.match(careers, /class="twins-brand-cta twins-brand-cta--quote" href="#apply">\s*<\?php if \(\$environment === 'staging'\): \?>\s*Preview the application\s*<\?php else: \?>\s*Start your application\s*<\?php endif; \?>\s*<\/a>/);
  assert.match(careers, /<h3>Share your interest<\/h3>\s*<\?php if \(\$environment === 'staging'\): \?>\s*<p>Preview the essentials[^<]*<\/p>\s*<\?php else: \?>\s*<p>Give us the essentials[^<]*<\/p>\s*<\?php endif; \?>/);
});

test('careers keeps the eager owned crew image inside the initial hero grid', () => {
  const careers = template('careers.php');
  const hero = careers.match(/<section class="twins-brand-careers-hero"[\s\S]*?<\/section>/);
  assert.ok(hero, 'Careers hero is missing');
  assert.match(hero[0], /twins-brand-careers-hero-copy[\s\S]*twins-brand-careers-hero-image/);
  assert.match(hero[0], /\$logicalKey = 'crew-fleet'/);
  assert.match(hero[0], /\$class = 'twins-brand-careers-crew-photo'/);
  assert.match(hero[0], /\$loading = 'eager'/);
});

test('renderer safety contract scans full composition and proves unsafe booking rejection', () => {
  const harness = fs.readFileSync(path.join(root, 'tests/php/renderer-contract-harness.php'), 'utf8');
  assert.match(harness, /\$stagingDocuments/);
  assert.match(harness, /\$assertInertComposition/);
  assert.match(harness, /\$unsafeBookingFragments/);
  assert.match(harness, /\$assertInertComposition\(\$document/);
  assert.match(harness, /renderHeader\(\['environment' => 'staging', 'market' => 'main'\]\)/);
  assert.match(harness, /unsafe booking[^']*was not rejected/i);
});

test('portable templates contain no direct submission or network primitive', () => {
  const prohibited = /<form\b|type\s*=\s*["'](?:submit|image)["']|\sname\s*=|\sform\s*=|formaction\s*=|https?:\/\/|fetch\s*\(|XMLHttpRequest|sendBeacon\s*\(/i;
  for (const name of ['home.php', 'team.php', 'careers.php', 'contact.php', 'reviews.php', 'blog-index.php']) {
    assert.doesNotMatch(template(name), prohibited, name);
  }
});

test('each body owns exactly one main while shared chrome owns none', () => {
  for (const name of ['home.php', 'team.php', 'careers.php', 'contact.php', 'reviews.php', 'blog-index.php']) {
    assert.equal((template(name).match(/id="twins-overhaul-main"/g) || []).length, 1, name);
  }
  for (const name of ['header.php', 'footer.php']) {
    const chrome = fs.readFileSync(path.join(root, 'components', name), 'utf8');
    assert.equal((chrome.match(/id="twins-overhaul-main"/g) || []).length, 0, name);
  }
});

test('blog index and article layouts keep approved structure and context-only data', () => {
  const blog = template('blog-index.php');
  assert.equal((blog.match(/<h1\b/g) || []).length, 1);
  assert.match(blog, /twins-brand-blog-page/);
  assert.match(blog, /Garage door answers from the Twins crew/);
  assert.match(blog, /twins-brand-blog-card/);
  assert.match(blog, /twins-brand-blog-card-media[\s\S]*loading="lazy"/);
  assert.match(blog, /twins-brand-blog-pagination/);
  assert.match(blog, /twins-brand-blog-page-link/);
  assert.match(blog, /\$quote\[['"]href['"]\]/);
  assert.match(blog, /\$blogIndex/);
  assert.doesNotMatch(blog, /get_the_post_thumbnail_url|WP_Query|get_permalink|\$market\[['"]phone(?:Href|Display)['"]\]/);

  const editorial = template('editorial.php');
  assert.match(editorial, /twins-brand-article-page/);
  assert.match(editorial, /twins-brand-article-hero-media/);
  assert.match(editorial, /\$articleHeroImage !== ''/);
  assert.match(editorial, /twins-brand-article-content/);
  assert.match(editorial, /Services related to this guide/);
});
