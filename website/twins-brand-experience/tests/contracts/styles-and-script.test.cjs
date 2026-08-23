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
  // r30 compact header: 132px base logo stepping down to 92px on the
  // narrowest breakpoints; the mobile-proof band and right-twin float retired.
  for (const token of ['width: 132px', 'width: 120px', 'width: 110px', 'width: 100px', 'width: 92px']) assert.ok(css.includes(token), token);
  assert.match(css, /@keyframes twins-brand-float-left/);
  assert.match(css, /@keyframes twins-location-float/);
  assert.match(css, /\.twins-brand-cta--book::after[\s\S]*content:\s*['"]→['"]/);
  assert.match(css, /linear-gradient/);
  assert.match(css, /\.twins-brand-cta:active/);
  assert.match(css, /prefers-reduced-motion:\s*reduce/);
  assert.match(css, /animation:\s*none\s*!important/);
});

test('runtime script has no transport beyond the two approved guarded operations', () => {
  // r30 allows exactly two narrow exceptions, both mirrored by the staging
  // CSP: the ZIP router's safeLocalPath-guarded local navigation, and the
  // read-only GET of the pinned public Supabase review-summary row (the same
  // origin the safety plugin's connect-src names). Everything else stays
  // forbidden. r39's email popup adds NO transport here: its runtime is
  // chrome + localStorage only, and the popup submission is a production-only
  // script (production-cutover/production-popup.js) pinned by its own test
  // below.
  assert.match(js, /const destination = safeLocalPath\(ZIP_ROUTES\[zip\.slice\(0, 3\)\] \|\| ZIP_FALLBACK\);/);
  assert.equal((js.match(/(?:window\.)?location(?:\.href|\.assign|\.replace)?\s*=|window\.open/g) || []).length, 1);
  assert.equal((js.match(/fetch\s*\(/g) || []).length, 1);
  assert.match(js, /const endpoint = 'https:\/\/jwrpjuqaynownxaoeayi\.supabase\.co\/rest\/v1\/places_profile_summary'\n\s*\+ '\?select=rating,user_rating_count&place_id=eq\.ChIJ6WuQE9VSBogRgy76ORRGfHs';/);
  assert.doesNotMatch(js, /method:|POST|PUT|PATCH|DELETE/);
  const withoutGuardedOperations = js
    .replace('window.location.href = destination;', '')
    .replace('fetch(endpoint, {', '');
  assert.doesNotMatch(withoutGuardedOperations, /fetch\s*\(|XMLHttpRequest|sendBeacon|WebSocket|EventSource|\.submit\s*\(|requestSubmit|location\s*=|window\.open|gtag|dataLayer|fbq/i);
  for (const marker of ['Escape', 'visibilitychange', 'pointerdown', 'touchstart', 'focusin', 'aria-expanded']) assert.ok(js.includes(marker), marker);
});

test('review runtime uses bounded status controls and permanently pauses after manual navigation', () => {
  assert.match(js, /data-review-page-status/);
  assert.match(js, /let permanentlyPaused\s*=\s*false/);
  assert.match(js, /const manualGo\s*=/);
  assert.match(js, /permanentlyPaused\s*=\s*true/);
  assert.match(js, /toggleAttribute\(['"]inert['"]/);
  assert.match(js, /setAttribute\(['"]aria-hidden['"],\s*['"]true['"]\)/);
  assert.match(js, /removeAttribute\(['"]aria-hidden['"]\)/);
  // r30 autoplay advances every 6.2s and still stops for good on manual use.
  assert.match(js, /6200/);
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
  // r30 moved the Design Your Door CTA into the why-doors home component.
  const whyDoors = fs.readFileSync(path.join(root, 'components/home/why-doors.php'), 'utf8');
  assert.deepEqual(classesFor(browserFixture), classesFor(whyDoors));
  assert.deepEqual(classesFor(whyDoors), ['twins-brand-cta']);
  assert.match(css, /\.twins-brand-door-builder\s+\.twins-brand-cta\s*\{/);
});

test('email popup: storage-bounded chrome runtime, approved copy, production-only guarded submission', () => {
  const component = fs.readFileSync(path.join(root, 'components/email-capture.php'), 'utf8');
  const productionPopup = fs.readFileSync(path.resolve(root, '../production-cutover/production-popup.js'), 'utf8');
  const productionLoader = fs.readFileSync(path.resolve(root, '../production-cutover/production-overhaul-loader.php'), 'utf8');

  // Shared runtime: 20s dwell timer (never shortened in shipped code), the
  // fixed suppression key with 180-day arithmetic, the shared focus trap, and
  // desktop-only exit intent. Transport stays impossible - the guarded-
  // operations test above proves the popup added none.
  assert.match(js, /window\.setTimeout\(openPopup, 20000\)/);
  assert.doesNotMatch(js, /setTimeout\(openPopup, (?!20000)\d/);
  assert.match(js, /POPUP_KEY = 'twins-popup-v1'/);
  assert.match(js, /180 \* 24 \* 60 \* 60 \* 1000/);
  assert.match(js, /trapTab\(event, popupDialog \|\| popup\)/);
  assert.match(js, /\(hover: hover\) and \(pointer: fine\)/);
  // Never stacks on the menu drawer or the quote dialog: the three share one
  // scroll-lock slot, and a popup opened on top of either left the page
  // scroll-locked after both closed. While one is open the popup waits out
  // another full dwell (the same 20s, never a shorter timer).
  assert.match(js, /const popupBlockedByOverlay = \(\) => Boolean\(\(drawer && !drawer\.hidden\) \|\| \(booking && !booking\.hidden\)\);/);
  assert.match(js, /if \(popupBlockedByOverlay\(\)\) \{\s*popupTimer = window\.setTimeout\(openPopup, 20000\);\s*return;\s*\}\s*popupShown = true;/);

  // Component: approved copy byte-for-byte, dialog semantics, decorative art,
  // spam-gate fields, and NO URL of its own - the endpoint is a server-side
  // declaration (TWINS_POPUP_LEAD_ENDPOINT) validated against the approved
  // Supabase host, exactly like the callback form's data attribute.
  for (const copy of [
    'FIRST DIBS ON TUNE-UP SEASON',
    'One useful email when it matters. Spring and fall maintenance reminders, current offers like the $49 tune-up, and what Wisconsin weather is about to do to your garage door. No spam, unsubscribe any time.',
    'Email address',
    'SIGN ME UP',
    'No thanks',
    'We send a handful of emails a year. Unsubscribe with one click.',
    'This private staging preview does not submit or store lead information.',
  ]) assert.ok(component.includes(copy), `component lost approved copy: ${copy}`);
  assert.doesNotMatch(component, /—|–/, 'popup copy carries an em- or en-dash');
  assert.match(component, /role="dialog" aria-modal="true"/);
  assert.match(component, /class="twins-popup-art" aria-hidden="true"/);
  assert.match(component, /name="website"/);
  assert.match(component, /data-popup-rendered-at/);
  assert.match(component, /TWINS_POPUP_LEAD_ENDPOINT/);
  assert.match(component, /'jwrpjuqaynownxaoeayi\.supabase\.co'/);
  assert.doesNotMatch(component, /https?:\/\//, 'component may not hardcode any URL');
  assert.match(component, /config\/popup\.php/);
  assert.match(component, /'campaign-preserve', 'legal-preserve', 'contact-brand', 'builder'/);

  // Kill switch: single constant in config, default enabled.
  const popupConfig = fs.readFileSync(path.join(root, 'config/popup.php'), 'utf8');
  assert.match(popupConfig, /define\('POPUP_ENABLED', true\)/);

  // Production submission script: posts ONLY to the server-declared attribute
  // (no caller-selected or hardcoded URL), sends exactly the bounded payload,
  // makes one attempt with no retries, never logs the address, honors the
  // honeypot, writes the shared suppression key, and guards the optional GA4
  // event behind a typeof check.
  assert.match(productionPopup, /fetch\(wrap\.dataset\.popupEndpoint,/);
  assert.equal((productionPopup.match(/fetch\s*\(/g) || []).length, 1);
  assert.doesNotMatch(productionPopup, /https?:\/\//);
  assert.doesNotMatch(productionPopup, /XMLHttpRequest|sendBeacon|WebSocket|EventSource/);
  assert.match(productionPopup, /source: 'website-popup'/);
  assert.match(productionPopup, /path: window\.location\.pathname/);
  for (const key of ['email:', 'website:', 'rendered_at:', 'elapsed_ms:']) assert.ok(productionPopup.includes(key), `payload lost ${key}`);
  // The payload is bounded: exactly these keys, in this order, nothing else.
  const payloadLiteral = productionPopup.match(/var payload = \{([\s\S]*?)\n\s*\};/);
  assert.ok(payloadLiteral, 'production popup lost its single payload literal');
  assert.deepEqual(
    [...payloadLiteral[1].matchAll(/^\s*([a-z_]+):/gm)].map(match => match[1]),
    ['email', 'source', 'path', 'page', 'website', 'rendered_at', 'elapsed_ms'],
  );
  assert.doesNotMatch(productionPopup, /console\.|retry/i);
  assert.match(productionPopup, /'twins-popup-v1'/);
  assert.match(productionPopup, /if \(honeypot !== ''\) \{/);
  assert.match(productionPopup, /typeof window\.gtag === 'function'/);
  assert.match(productionLoader, /TWINS_POPUP_LEAD_ENDPOINT/);
  assert.match(productionLoader, /twins-overhaul-popup/);

  // Stylesheet: namespaced .twins-popup-* family, one dim overlay, hairline
  // 10px-radius dialog, gold pill action with navy ink, mobile bottom sheet
  // capped at min(76vh, 560px) so the decline link, fineprint, and staging
  // notice are visible without scrolling (the design-polish pass raised it
  // from 40vh, which cut "No thanks" in half on a 390x844 phone), and an
  // entrance animation that exists only under no-preference plus an explicit
  // reduced-motion kill.
  for (const selector of [
    '.twins-popup[hidden]',
    '.twins-popup-dialog',
    '.twins-popup-art',
    '.twins-popup-submit',
    '.twins-popup-close',
    '.twins-popup-decline',
    '.twins-popup-fineprint',
  ]) assert.ok(css.includes(selector), selector);
  assert.match(css, /\.twins-popup-dialog \{[^}]*border-radius: 10px/s);
  assert.match(css, /\.twins-popup-submit \{[^}]*color: var\(--twins-navy-950\);\s*background: var\(--twins-gold\)/s);
  assert.match(css, /\.twins-popup-dialog \{[^}]*max-height: min\(76vh, 560px\)/s);
  assert.match(css, /@media \(prefers-reduced-motion: no-preference\) \{\s*\.twins-popup-dialog \{ animation: twins-popup-enter/);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\) \{\s*\.twins-popup-dialog \{ animation: none !important; \}/);
});
