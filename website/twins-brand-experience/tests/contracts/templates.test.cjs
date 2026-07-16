const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const template = name => fs.readFileSync(path.join(root, 'templates', name), 'utf8');

test('home contains every approved section in order', () => {
  const html = template('home.php');
  const markers = ['brand-hero', 'trust-ribbon', 'service-pathways', 'review-slider', 'team-story', 'door-builder', 'market-selector', 'careers', 'final-cta'];
  let cursor = -1;
  for (const marker of markers) {
    const next = html.indexOf(marker);
    assert.ok(next > cursor, `${marker} is missing or out of order`);
    cursor = next;
  }
  assert.match(html, /Garage Door Repair & Installation, Done Right Today\./);
  assert.match(html, /Same-day appointments/);
  assert.match(html, /Upfront pricing/);
  assert.match(html, /Most repairs done in one visit/);
});

test('team and careers use real fixed picture keys', () => {
  assert.match(template('team.php'), /crew-fleet/);
  assert.match(template('team.php'), /tal-portrait/);
  assert.match(template('careers.php'), /crew-fleet|technician-at-work/);
  assert.doesNotMatch(template('careers.php'), /https?:\/\/|fetch\s*\(|TWINS_ENDPOINT|type="submit"|<form/i);
});

test('all redesigned templates use exact quote copy', () => {
  for (const name of ['home.php', 'team.php', 'careers.php', 'contact.php', 'reviews.php']) {
    const html = template(name);
    assert.doesNotMatch(html, /Get an Estimate|Request Exact Quote/);
    assert.match(html, /Request a Quote/);
  }
});

test('homepage has deterministic desktop and mobile truck placements', () => {
  const html = template('home.php');
  assert.match(html, /twins-brand-hero[\s\S]*twins-brand-truck--hero/);
  assert.match(html, /twins-brand-mobile-proof[\s\S]*twins-brand-truck--mobile-proof/);
  assert.match(html, /door-builder-before-after/);
  assert.match(html, /twins-brand-careers-copy[\s\S]*\$environment\s*===\s*['"]staging['"]/);
});

test('supporting journeys preserve approved copy and adapter boundaries', () => {
  const team = template('team.php');
  assert.match(team, /Tal Joseph/);
  assert.match(team, /technician-at-work/);
  assert.match(team, /Careers/);
  assert.match(team, /data-section="company-story"/);

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
  assert.match(careers, /class="twins-brand-cta" href="#apply">\s*<\?php if \(\$environment === 'staging'\): \?>\s*Preview the application\s*<\?php else: \?>\s*Start your application\s*<\?php endif; \?>\s*<\/a>/);
  assert.match(careers, /<h3>Share your interest<\/h3>\s*<\?php if \(\$environment === 'staging'\): \?>\s*<p>Preview the essentials[^<]*<\/p>\s*<\?php else: \?>\s*<p>Give us the essentials[^<]*<\/p>\s*<\?php endif; \?>/);
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
  for (const name of ['home.php', 'team.php', 'careers.php', 'contact.php', 'reviews.php']) {
    assert.doesNotMatch(template(name), prohibited, name);
  }
});

test('each body owns exactly one main while shared chrome owns none', () => {
  for (const name of ['home.php', 'team.php', 'careers.php', 'contact.php', 'reviews.php']) {
    assert.equal((template(name).match(/id="twins-overhaul-main"/g) || []).length, 1, name);
  }
  for (const name of ['header.php', 'footer.php']) {
    const chrome = fs.readFileSync(path.join(root, 'components', name), 'utf8');
    assert.equal((chrome.match(/id="twins-overhaul-main"/g) || []).length, 0, name);
  }
});
