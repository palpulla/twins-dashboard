const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const css = fs.readFileSync(path.join(root, 'assets/css/twins-brand.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/twins-brand.js'), 'utf8');

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
