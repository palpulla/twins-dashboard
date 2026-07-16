const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const css = fs.readFileSync(path.join(root, 'assets/css/twins-brand.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/twins-brand.js'), 'utf8');
const homeTemplate = fs.readFileSync(path.join(root, 'templates/home.php'), 'utf8');
const browserFixture = fs.readFileSync(path.join(root, 'tests/browser/fixtures/brand-home.html'), 'utf8');

test('CSS pins fonts, logo floors, breakpoints, Twin motion, and reduced motion', () => {
  assert.match(css, /font-family:\s*['"]Lilita One['"]/);
  assert.match(css, /font-family:\s*['"]Nunito['"]/);
  assert.doesNotMatch(css, /fonts\.(googleapis|gstatic)\.com/);
  for (const token of ['--twins-logo-expanded: 204px', '--twins-logo-compressed: 180px', 'width: 190px', 'width: 176px', 'width: 154px', 'width: 148px', 'width: 140px']) assert.ok(css.includes(token), token);
  assert.match(css, /@keyframes twins-brand-float-left/);
  assert.match(css, /@keyframes twins-brand-float-right/);
  assert.match(css, /\.twins-brand-cta--book::after[\s\S]*content:\s*['"]→['"]/);
  assert.match(css, /linear-gradient/);
  assert.match(css, /\.twins-brand-cta:active/);
  assert.match(css, /\.twins-brand-mobile-proof[\s\S]*display:\s*none/);
  assert.match(css, /\.twins-brand-truck--hero[\s\S]*display:\s*none/);
  assert.match(css, /prefers-reduced-motion:\s*reduce/);
  assert.match(css, /animation:\s*none\s*!important/);
});

test('runtime script contains no transport, analytics, or external destination', () => {
  assert.doesNotMatch(js, /fetch\s*\(|XMLHttpRequest|sendBeacon|WebSocket|EventSource|\.submit\s*\(|requestSubmit|location\s*=|window\.open|gtag|dataLayer|fbq/i);
  for (const marker of ['Escape', 'visibilitychange', 'pointerdown', 'touchstart', 'focusin', 'aria-expanded']) assert.ok(js.includes(marker), marker);
});

test('review runtime uses bounded status controls and permanently pauses after manual navigation', () => {
  assert.match(js, /data-review-page-status/);
  assert.match(js, /let permanentlyPaused\s*=\s*false/);
  assert.match(js, /const manualGo\s*=/);
  assert.match(js, /permanentlyPaused\s*=\s*true/);
  assert.match(js, /12000/);
  assert.doesNotMatch(js, /setInterval\([^;]*7000/);
  assert.doesNotMatch(js, /twins-brand-review-dots/);
  assert.match(css, /\.twins-brand-review-control/);
  assert.match(css, /\.twins-brand-review-status/);
  assert.match(css, /\.twins-brand-review-list[\s\S]*align-items:\s*start/);
  assert.match(css, /\.twins-brand-review-card blockquote[\s\S]*font-style:\s*normal/);
  assert.doesNotMatch(css, /\.twins-brand-review-card\s*\{[^}]*min-height:\s*310px/s);
  assert.doesNotMatch(css, /twins-brand-review-dots/);
});

test('brand stylesheet covers every supporting route surface', () => {
  for (const selector of [
    '.twins-brand-page-hero',
    '.twins-brand-team-crew',
    '.twins-brand-team-portraits',
    '.twins-brand-page-nav',
    '.twins-brand-careers-hero',
    '.twins-brand-value-grid',
    '.twins-brand-role-grid',
    '.twins-brand-process-grid',
    '.twins-brand-careers-application',
    '.twins-brand-contact-market-grid',
    '.twins-brand-reviews-collection',
    '.twins-brand-reviews-next',
  ]) assert.ok(css.includes(selector), selector);
});

test('door-builder CTA fixture classes exactly match runtime and use contextual styling', () => {
  const classesFor = source => {
    const match = source.match(/<a class="([^"]+)"(?:(?!<\/a>)[\s\S])*?>Design Your Door<\/a>/);
    assert.ok(match, 'Design Your Door CTA is missing');
    return match[1].trim().split(/\s+/).sort();
  };
  assert.deepEqual(classesFor(browserFixture), classesFor(homeTemplate));
  assert.deepEqual(classesFor(homeTemplate), ['twins-brand-cta']);
  assert.match(css, /\.twins-brand-door-builder\s+\.twins-brand-cta\s*\{/);
});
