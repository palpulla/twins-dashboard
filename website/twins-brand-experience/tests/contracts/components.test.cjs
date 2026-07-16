const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');

const source = name => {
  const file = path.join(root, 'components', name);
  return fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '';
};

test('header exposes the approved complete navigation and CTA copy', () => {
  const html = source('header.php');
  for (const label of ['Services', 'Garage Doors', 'Service Areas', 'Resources', 'About', 'Our Team', 'Careers', 'Book Online', 'Request a Quote']) {
    assert.match(html, new RegExp(label.replace(' ', '\\s+')));
  }
  assert.doesNotMatch(html, /Get an Estimate|Request Exact Quote/);
  assert.match(html, /aria-controls="twins-brand-drawer"/);
  assert.match(html, /aria-expanded="false"/);
  assert.match(html, /===\s*['"]dialog['"]/);
  assert.match(html, /===\s*['"]external['"]/);
});

test('header binds booking mode to environment and emits only exact safe external links', () => {
  const html = source('header.php');
  assert.match(html, /\$environment\s*===\s*['"]staging['"][\s\S]*?\$bookingMode\s*!==\s*['"]dialog['"]/);
  assert.match(html, /\$environment\s*===\s*['"]production['"][\s\S]*?\$bookingMode\s*!==\s*['"]external['"]/);
  assert.match(html, /\$booking\[['"]target['"]\]/);
  assert.match(html, /\$booking\[['"]rel['"]\]/);
  assert.equal((html.match(/target="_blank" rel="noopener noreferrer"/g) || []).length, 2);
});

test('slider emits normalized records but no review schema owner', () => {
  const html = source('review-slider.php');
  assert.match(html, /twins-brand-review-slider/);
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
