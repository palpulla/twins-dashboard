# Location Page Dark Showroom Revision Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the four sections below the approved location-page hero into a modern dark showroom with a yellow verified-proof strip, scan-friendly service and local-proof cards, static Twin accents, and subtle finite motion.

**Architecture:** Preserve the shared header, contained hero, canonical location data, route helpers, booking dialog, and five-section page contract. Add the showroom markup only to the location branch of `templates/editorial.php`, scope all new presentation and motion to `.twins-location-page`, reuse the existing fail-open `initLocationReveals()` behavior, and synchronize the deterministic Rockford fixture before package generation.

**Tech Stack:** PHP 8 templates, CSS, vanilla JavaScript, Node.js 20 built-in tests, Playwright 1.61.1, and existing Twins Garage Doors image, inline-SVG, font, and character assets.

## Global Constraints

- The shared header and approved contained hero remain structurally and visually unchanged; remove only the hero's reveal attribute so motion starts below it.
- Keep exactly five location-page sections: hero, trust/proof strip, services, local proof, and final CTA.
- The redesign starts immediately after the hero.
- Use deep navy as the dominant post-hero field, subtle static garage-panel lines, yellow as the focused accent, and the established `Lilita One` display face.
- The trust strip contains only the canonical Google rating/review count, `Family owned`, and `Licensed and insured`.
- Do not add same-day promises, `done today`, a zero-dollar service call, `most repairs in one visit`, guaranteed arrival language, `recently opened`, urgency, discounts, waived fees, market-leadership claims, or unverified review quotations.
- Keep three service cards and put the approved “what we fix” items inside those cards; do not add a sixth section.
- A yellow middle service card is compositional only and must not be labeled preferred, recommended, featured, or more important.
- Keep the genuine before-and-after image and cap it at 440 px desktop and 310 px mobile.
- Keep one static service Twin cameo and two static final-CTA Twins; hide the service cameo at 480 px and below.
- Allowed motion is a one-time post-hero reveal of no more than 10 px over approximately 420 ms, service-card hover/focus lift of no more than 3 px over approximately 160 ms, and CTA sheen only during direct hover/focus for less than 180 ms.
- Disallow infinite Twin movement, CTA pulsing, looping door movement, parallax, orbiting elements, moving textures, autonomous decorative animation, and page-controlled smooth scrolling.
- `prefers-reduced-motion: reduce` makes every location reveal and interaction immediately visible and static.
- If JavaScript or `IntersectionObserver` is unavailable, all content remains visible.
- Verify 1440, 1024, 768, 390, 360, and 320 px; no horizontal overflow; no character/control collisions; every interactive target is at least 44×44 px.
- Preserve canonical Rockford phone, address, rating, review count, route-local Wisconsin/Illinois/Kentucky behavior, booking dialog, quote links, phone links, and analytics hooks.
- Preserve unrelated worktree changes, including the existing edit to `tests/php/renderer-contract-harness.php`.
- Do not rotate a release or deploy to staging until the local desktop and mobile mockup receive explicit visual approval.

---

## File Structure

- `website/twins-brand-experience/templates/editorial.php`: keeps the approved hero, renders the three service item lists and the compositional middle-card modifier, removes the extra location FAQ block, and limits reveal attributes to the four post-hero sections.
- `website/twins-brand-experience/assets/css/twins-brand.css`: owns the location-only dark-showroom backgrounds, yellow proof strip, card framing, image bounds, static characters, responsive behavior, finite reveals, hover/focus lift, sheen, and reduced-motion overrides.
- `website/twins-brand-experience/assets/js/twins-brand.js`: remains the fail-open reveal implementation; it is verified but does not require source changes.
- `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`: pins structure, verified copy, service lists, visual hooks, motion bounds, no autonomous location animation, responsive rules, and forbidden claims.
- `website/twins-brand-experience/tests/browser/fixtures/location-modern.html`: deterministic Rockford output synchronized with the final template markup.
- `website/twins-brand-experience/tests/browser/location-modern.spec.cjs`: verifies visual hierarchy, color contrast, content, responsiveness, hover/focus motion, one-time reveal, fail-open behavior, reduced motion, booking behavior, controls, overflow, and collisions.
- `website/twins-brand-experience/dist/**`, `website/twins-brand-experience/manifests/host-verification.json`, and `website/twins-brand-experience/manifests/staging-runtime.json`: regenerated once after all source and local browser checks pass.

---

### Task 1: Lock and Render the Five-Section Showroom Content

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs:23-198`
- Modify: `website/twins-brand-experience/templates/editorial.php:77-98`
- Modify: `website/twins-brand-experience/templates/editorial.php:197-293`

**Interfaces:**
- Consumes: `$locationServiceCards`, `$napRating`, `$napCount`, `$napAddress`, `$locationRecord`, `$locationNavMarketKey`, `$experience->route()`, and the existing Twin and door-art components.
- Produces: one static hero plus four `[data-location-reveal]` sections; three `.twins-location-service-card` articles; one `.twins-location-service-card--spotlight`; three `.twins-location-service-items` lists; no location FAQ section.

- [ ] **Step 1: Add failing contracts for the post-hero reveal boundary and service details**

Add these tests after `services are limited to three concise choices and one character cameo`:

```js
test('only the four post-hero sections participate in location reveals', () => {
  assert.match(template, /<header class="twins-location-hero" aria-labelledby="twins-location-title">/);
  assert.doesNotMatch(template, /<header class="twins-location-hero"[^>]*data-location-reveal/);
  assert.equal((template.match(/data-location-reveal/g) || []).length, 4);
  for (const className of [
    'twins-location-trust',
    'twins-location-services',
    'twins-location-local-proof',
    'twins-location-final-cta',
  ]) {
    assert.match(template, new RegExp(`class="[^"]*${className}[^"]*"[^>]*data-location-reveal`));
  }
  assert.doesNotMatch(template, /<section class="twins-brand-faq"[^>]*twins-location-questions-title/);
});

test('service cards render approved scan-friendly issue lists and one visual spotlight', () => {
  for (const item of [
    'Broken springs',
    'Cables and rollers',
    'Off-track or noisy movement',
    'Safety sensors',
    'Remotes and wall controls',
    'Motors and drive systems',
    'Damaged door replacement',
    'Style and window choices',
    'Insulation options',
  ]) {
    assert.match(template, new RegExp(item));
  }
  assert.match(template, /foreach \(\$locationServiceCards as \$serviceIndex => \$serviceCard\)/);
  assert.match(template, /\$serviceIndex === 1 \? ' twins-location-service-card--spotlight' : ''/);
  assert.match(template, /<ul class="twins-location-service-items">[\s\S]*?foreach \(\$serviceCard\['items'\] as \$serviceItem\)/);
  assert.doesNotMatch(template, /recommended|preferred|featured/i);
});
```

Extend `quote is primary copy and unverified urgency claims stay absent`:

```js
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
```

- [ ] **Step 2: Run the focused contract and verify failure**

Run from `website/twins-brand-experience`:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: FAIL because the hero still has `data-location-reveal`, the location FAQ block still exists, the issue lists are not rendered, and no middle-card modifier exists.

- [ ] **Step 3: Replace the service item arrays with the approved issue labels**

In `$locationServiceCards`, keep every existing title, route, art kind, and description, and replace only the `items` values:

```php
'items' => ['Broken springs', 'Cables and rollers', 'Off-track or noisy movement'],
```

```php
'items' => ['Safety sensors', 'Remotes and wall controls', 'Motors and drive systems'],
```

```php
'items' => ['Damaged door replacement', 'Style and window choices', 'Insulation options'],
```

- [ ] **Step 4: Remove hero motion and render the spotlight and lists**

Change the hero opening tag to:

```php
<header class="twins-location-hero" aria-labelledby="twins-location-title">
```

Replace the service loop with:

```php
<?php foreach ($locationServiceCards as $serviceIndex => $serviceCard): ?>
  <article class="twins-location-service-card<?= $serviceIndex === 1 ? ' twins-location-service-card--spotlight' : '' ?>">
    <?= twins_brand_door_art($serviceCard['art'], 'twins-location-service-art', 'location-service-' . $serviceCard['art']) ?>
    <h3><?= htmlspecialchars($serviceCard['title'], ENT_QUOTES, 'UTF-8') ?></h3>
    <p><?= htmlspecialchars($serviceCard['description'], ENT_QUOTES, 'UTF-8') ?></p>
    <ul class="twins-location-service-items">
      <?php foreach ($serviceCard['items'] as $serviceItem): ?>
        <li><?= htmlspecialchars($serviceItem, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
    <a class="twins-location-service-link" href="<?= htmlspecialchars($experience->route($serviceCard['route'], $locationNavMarketKey), ENT_QUOTES, 'UTF-8') ?>">Explore <?= htmlspecialchars($serviceCard['title'], ENT_QUOTES, 'UTF-8') ?></a>
  </article>
<?php endforeach; ?>
```

The middle-card class is visual only. Do not add a badge, accessible label, or copy that assigns it higher priority.

- [ ] **Step 5: Remove the extra location FAQ render block**

Delete the location-branch block beginning with:

```php
<?php if ($editorialFaqs !== []): ?>
  <section class="twins-brand-faq" aria-labelledby="twins-location-questions-title" data-location-reveal>
```

and ending at its matching:

```php
<?php endif; ?>
```

immediately after `.twins-location-local-proof`. Keep the later non-location FAQ rendering unchanged.

- [ ] **Step 6: Run the focused contract and verify pass**

Run the Step 2 command.

Expected: all location overhaul contracts PASS, and the template contains exactly four reveal attributes below the static hero.

- [ ] **Step 7: Commit the location markup**

```bash
git add website/twins-brand-experience/templates/editorial.php website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
git diff --cached --check
git commit -m "feat: add showroom service content"
```

Expected: the commit contains only the template and location contract. Do not stage `tests/php/renderer-contract-harness.php`.

---

### Task 2: Build the Dark Showroom Visual System

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs:199-283`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css:1058-1224`

**Interfaces:**
- Consumes: the existing location classes plus `.twins-location-service-card--spotlight` and `.twins-location-service-items` from Task 1.
- Produces: yellow verified-proof strip; dark paneled service, local-proof, and final-CTA fields; outlined cards; bounded yellow-framed image; static character layout; mobile stacking.

- [ ] **Step 1: Replace pale-layout contracts with dark-showroom contracts**

Add:

```js
test('post-hero sections use the dark showroom palette and static panel texture', () => {
  assert.match(css, /\.twins-location-trust\s*\{[^}]*background:\s*var\(--twins-gold\)/);
  assert.match(css, /\.twins-location-services\s*\{[^}]*color:\s*var\(--twins-white\)[^}]*background-color:\s*var\(--twins-navy-950\)/);
  assert.match(css, /\.twins-location-local-proof\s*\{[^}]*color:\s*var\(--twins-white\)[^}]*background-color:\s*#081f40/);
  assert.match(css, /\.twins-location-final-cta\s*\{[^}]*background-color:\s*var\(--twins-navy-950\)/);
  assert.match(css, /\.twins-location-services::before,\s*\.twins-location-local-proof::before,\s*\.twins-location-final-cta::before\s*\{[^}]*repeating-linear-gradient/);
  assert.match(css, /\.twins-location-service-card\s*\{[^}]*border:\s*2px solid rgba\(255,\s*200,\s*61,\s*\.5\)/);
  assert.match(css, /\.twins-location-service-card--spotlight\s*\{[^}]*background:\s*var\(--twins-gold\)/);
  assert.match(css, /\.twins-location-proof-list li\s*\{[^}]*border:\s*1px solid rgba\(255,\s*200,\s*61,\s*\.5\)/);
  assert.match(css, /\.twins-location-local-proof-media\s*\{[^}]*max-height:\s*440px[^}]*background:\s*var\(--twins-gold\)/);
});

test('service cards expose one stretched link without nested controls', () => {
  assert.match(css, /\.twins-location-service-card\s*\{[^}]*position:\s*relative/);
  assert.match(css, /\.twins-location-service-link::after\s*\{[^}]*position:\s*absolute[^}]*inset:\s*0/);
  assert.match(css, /\.twins-location-service-card:focus-within\s*\{[^}]*outline:/);
  assert.doesNotMatch(template, /<article[^>]*>\s*<a[^>]*>[\s\S]*?<a/);
});
```

Update responsive assertions to retain:

```js
assert.match(css, /@media \(max-width:\s*768px\)[\s\S]*?\.twins-location-service-grid\s*\{[^}]*grid-template-columns:\s*1fr/);
assert.match(css, /@media \(max-width:\s*480px\)[\s\S]*?\.twins-location-page \.twins-location-twin--services\s*\{[^}]*display:\s*none/);
```

- [ ] **Step 2: Run the focused contract and verify failure**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: FAIL on the yellow strip, dark fields, outlined cards, stretched link, and panel texture assertions.

- [ ] **Step 3: Preserve the hero rules and replace only the post-hero base rules**

Keep `.twins-location-page`, the shared width rule, and every `.twins-location-hero*` declaration unchanged. Replace the rules from `.twins-location-trust` through `.twins-location-final-cta > p` with:

```css
.twins-location-trust {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  padding-block: 0;
  color: var(--twins-navy-950);
  background: var(--twins-gold);
  border-block: 2px solid var(--twins-navy-950);
}
.twins-location-trust > div {
  min-height: 96px;
  display: grid;
  align-content: center;
  gap: 3px;
  padding: 18px 28px;
  border-left: 1px solid rgba(7, 29, 59, .28);
}
.twins-location-trust > div:first-child { border-left: 0; }
.twins-location-trust strong { color: var(--twins-navy-950); font-weight: 1000; }
.twins-location-trust span { color: #18355c; font-size: .86rem; font-weight: 850; }
.twins-location-trust .twins-brand-stars { color: var(--twins-navy-950); }

.twins-location-services,
.twins-location-local-proof,
.twins-location-final-cta {
  position: relative;
  isolation: isolate;
  overflow: hidden;
}
.twins-location-services,
.twins-location-local-proof { color: var(--twins-white); }
.twins-location-services {
  padding-block: clamp(76px, 7vw, 96px);
  background-color: var(--twins-navy-950);
}
.twins-location-services::before,
.twins-location-local-proof::before,
.twins-location-final-cta::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  opacity: .24;
  background-image:
    repeating-linear-gradient(0deg, transparent 0 86px, rgba(159, 190, 220, .22) 87px 88px),
    linear-gradient(90deg, transparent 49.8%, rgba(159, 190, 220, .18) 50%, transparent 50.2%);
}
.twins-location-section-heading {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(280px, .7fr);
  gap: 42px;
  align-items: end;
  margin-bottom: 36px;
}
.twins-location-section-heading .twins-brand-kicker { grid-column: 1; color: var(--twins-gold); }
.twins-location-section-heading h2 {
  max-width: 720px;
  margin: 8px 0 0;
  color: var(--twins-white);
  font-size: clamp(2.55rem, 4.3vw, 4rem);
  line-height: .98;
  text-transform: uppercase;
}
.twins-location-section-heading > p {
  grid-column: 2;
  grid-row: 1 / span 2;
  max-width: 54ch;
  margin: 0;
  color: #dbe8f8;
}
.twins-location-service-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}
.twins-location-service-card {
  position: relative;
  display: grid;
  align-content: start;
  gap: 14px;
  min-height: 430px;
  padding: 30px;
  color: var(--twins-white);
  background: #0b2c58;
  border: 2px solid rgba(255, 200, 61, .5);
  border-radius: 14px;
  box-shadow: 0 18px 36px rgba(0, 0, 0, .2);
}
.twins-location-service-card--spotlight {
  color: var(--twins-navy-950);
  background: var(--twins-gold);
  border-color: var(--twins-gold);
}
.twins-location-service-art { width: 76px; height: 56px; margin-bottom: 8px; }
.twins-location-service-card h3 {
  margin: 0;
  color: inherit;
  font-size: 1.8rem;
  text-transform: uppercase;
}
.twins-location-service-card > p { margin: 0; color: #dbe8f8; }
.twins-location-service-card--spotlight > p { color: #18355c; }
.twins-location-service-items {
  display: grid;
  gap: 8px;
  margin: 4px 0 12px;
  padding: 0;
  list-style: none;
}
.twins-location-service-items li {
  position: relative;
  padding: 8px 10px 8px 30px;
  color: inherit;
  background: rgba(255, 255, 255, .07);
  border: 1px solid rgba(255, 255, 255, .18);
  border-radius: 8px;
  font-size: .9rem;
  font-weight: 850;
}
.twins-location-service-items li::before {
  content: '✓';
  position: absolute;
  left: 10px;
  color: var(--twins-gold);
  font-weight: 1000;
}
.twins-location-service-card--spotlight .twins-location-service-items li {
  background: rgba(7, 29, 59, .08);
  border-color: rgba(7, 29, 59, .2);
}
.twins-location-service-card--spotlight .twins-location-service-items li::before { color: var(--twins-navy-950); }
body.twins-brand-experience .twins-location-service-link {
  position: static;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  margin-top: auto;
  color: var(--twins-gold);
  font-weight: 1000;
  text-underline-offset: 4px;
}
body.twins-brand-experience .twins-location-service-card--spotlight .twins-location-service-link { color: var(--twins-navy-950); }
.twins-location-service-link::after { content: ''; position: absolute; inset: 0; }
.twins-location-service-card:focus-within { outline: 3px solid var(--twins-white); outline-offset: 4px; }
.twins-location-service-card--spotlight:focus-within { outline-color: var(--twins-navy-950); }

.twins-location-local-proof {
  display: grid;
  grid-template-columns: minmax(320px, .85fr) minmax(0, 1.15fr);
  gap: clamp(44px, 6vw, 78px);
  align-items: center;
  padding-block: clamp(76px, 7vw, 96px);
  background-color: #081f40;
  border-top: 1px solid rgba(255, 200, 61, .25);
}
.twins-location-local-proof-media {
  max-height: 440px;
  margin: 0;
  padding: 8px;
  overflow: hidden;
  background: var(--twins-gold);
  border: 1px solid rgba(255, 255, 255, .35);
  border-radius: 14px;
  box-shadow: 0 20px 38px rgba(0, 0, 0, .24);
}
.twins-location-local-proof-media picture {
  display: block;
  width: 100%;
  height: auto;
  aspect-ratio: 4 / 3;
  overflow: hidden;
  border-radius: 8px;
}
.twins-location-local-proof-image { display: block; width: 100%; height: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
.twins-location-local-proof-copy .twins-brand-kicker { color: var(--twins-gold); }
.twins-location-local-proof-copy h2 {
  max-width: 760px;
  margin: 10px 0 18px;
  color: var(--twins-white);
  font-size: clamp(2.55rem, 4.3vw, 4rem);
  line-height: .98;
  text-transform: uppercase;
}
.twins-location-local-proof-copy > p { max-width: 64ch; color: #dbe8f8; }
.twins-location-address { color: var(--twins-gold) !important; font-weight: 1000; }
.twins-location-proof-list { display: grid; gap: 10px; margin: 26px 0 0; padding: 0; list-style: none; }
.twins-location-proof-list li {
  display: grid;
  grid-template-columns: minmax(170px, .6fr) minmax(0, 1fr);
  gap: 20px;
  padding: 16px 18px;
  background: rgba(11, 44, 88, .78);
  border: 1px solid rgba(255, 200, 61, .5);
  border-radius: 10px;
}
.twins-location-proof-list strong { color: var(--twins-white); }
.twins-location-proof-list span { color: #dbe8f8; }
.twins-location-page .twins-location-twin {
  position: absolute;
  z-index: 2;
  display: block;
  height: auto;
  pointer-events: none;
  filter: drop-shadow(3px 7px 7px rgba(0, 0, 0, .24));
  animation: none;
}
.twins-location-twin--services { right: var(--twins-location-gutter); bottom: 14px; width: clamp(72px, 7vw, 104px); }
.twins-location-twin--final-left { left: max(18px, calc((100vw - 1320px) / 2)); bottom: -14px; width: clamp(86px, 8vw, 118px); }
.twins-location-twin--final-right { right: max(18px, calc((100vw - 1320px) / 2)); bottom: -14px; width: clamp(92px, 8vw, 124px); }
.twins-location-final-cta {
  min-height: 360px;
  padding: 72px clamp(140px, 13vw, 210px);
  color: var(--twins-white);
  background-color: var(--twins-navy-950);
}
.twins-location-final-cta > p { max-width: 650px; margin: 0 auto 24px; color: #e5eef8; }
```

The `.twins-location-service-link::after` stretched-link rule must remain after the link's `position: relative`; it creates one full-card target without wrapping or nesting another interactive element.

- [ ] **Step 4: Replace the location responsive rules**

Keep the hero declarations in each media query unchanged. Use:

```css
@media (max-width: 1024px) {
  .twins-location-hero { grid-template-columns: minmax(0, 1.15fr) minmax(300px, .85fr); gap: 34px; min-height: 560px; }
  .twins-location-services { padding-bottom: 144px; }
  .twins-location-service-card { min-height: 410px; padding: 24px; }
  .twins-location-local-proof { grid-template-columns: minmax(280px, .9fr) minmax(0, 1.1fr); gap: 38px; }
  .twins-location-twin--services { right: var(--twins-location-gutter); bottom: 16px; width: 64px; }
  .twins-location-final-cta { padding-bottom: 190px; }
  .twins-location-twin--final-left { bottom: 0; width: 64px; }
  .twins-location-twin--final-right { bottom: 0; width: 68px; }
}
@media (max-width: 768px) {
  .twins-location-page { --twins-location-gutter: 20px; }
  .twins-location-hero { grid-template-columns: 1fr; gap: 30px; min-height: 0; padding-block: 52px; }
  .twins-location-hero h1 { font-size: clamp(2.7rem, 11vw, 3.9rem); }
  .twins-location-hero-media { width: min(100%, 320px); max-height: 310px; }
  .twins-location-hero-media figcaption { height: 64px; min-height: 64px; line-height: 1.25; text-wrap: balance; }
  .twins-location-trust { grid-template-columns: 1fr; }
  .twins-location-trust > div { min-height: 74px; padding: 14px 20px; border-top: 1px solid rgba(7, 29, 59, .28); border-left: 0; }
  .twins-location-trust > div:first-child { border-top: 0; }
  .twins-location-services,
  .twins-location-local-proof { padding-block: 58px; }
  .twins-location-services { padding-bottom: 128px; }
  .twins-location-section-heading { grid-template-columns: 1fr; gap: 14px; margin-bottom: 28px; }
  .twins-location-section-heading > p { grid-column: 1; grid-row: auto; }
  .twins-location-service-grid { grid-template-columns: 1fr; }
  .twins-location-service-card { min-height: 0; padding: 26px; }
  .twins-location-local-proof { grid-template-columns: 1fr; }
  .twins-location-local-proof-media { width: min(100%, 408px); max-height: 310px; }
  .twins-location-proof-list li { grid-template-columns: 1fr; gap: 5px; }
  .twins-location-twin--services { bottom: 14px; width: 56px; }
  .twins-location-final-cta { min-height: 340px; padding: 60px 96px 170px; }
  .twins-location-twin--final-left { bottom: 0; width: 56px; }
  .twins-location-twin--final-right { bottom: 0; width: 60px; }
}
@media (max-width: 480px) {
  .twins-location-page { --twins-location-gutter: 20px; }
  .twins-location-actions .twins-brand-cta { flex: 1 1 100%; justify-content: center; }
  .twins-location-services { padding-bottom: 56px; }
  .twins-location-page .twins-location-twin--services { display: none; }
  .twins-location-final-cta { padding: 54px 20px 150px; }
  .twins-location-twin--final-left { left: 16px; bottom: 0; width: 48px; }
  .twins-location-twin--final-right { right: 16px; bottom: 0; width: 54px; }
}
@media (max-width: 320px) {
  .twins-location-page { --twins-location-gutter: 16px; }
  .twins-location-hero h1 { font-size: 2.5rem; }
  .twins-location-service-card { padding: 22px 18px; }
  .twins-location-twin--final-right { right: 12px; width: 40px; }
}
```

- [ ] **Step 5: Run focused contracts and verify pass**

Run the Step 2 command.

Expected: all tests PASS, including the existing hero sizing, responsive character, photo cap, and route-local assertions.

- [ ] **Step 6: Commit the showroom visual system**

```bash
git add website/twins-brand-experience/assets/css/twins-brand.css website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
git diff --cached --check
git commit -m "style: build dark showroom location sections"
```

---

### Task 3: Enforce Subtle Finite Motion

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs:185-198`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css:1168-1175`
- Verify only: `website/twins-brand-experience/assets/js/twins-brand.js:71-93`

**Interfaces:**
- Consumes: four post-hero `[data-location-reveal]` elements and the existing `initLocationReveals(root, reducedMotion)` function.
- Produces: 10 px/420 ms one-time reveals, 3 px/160 ms direct card feedback, 160 ms direct CTA sheen, no autonomous location animation, and complete reduced-motion overrides.

- [ ] **Step 1: Replace the broad reveal contract with exact motion bounds**

Replace `location reveals are progressive enhancement and reduced motion is static` with:

```js
test('location motion is finite, bounded, fail-open, and reduced-motion safe', () => {
  assert.match(script, /function initLocationReveals\(root, reducedMotion\)/);
  assert.match(script, /if \(reducedMotion\.matches\)\s*\{[\s\S]*?items\.forEach\(reveal\)/);
  assert.match(script, /if \(!\('IntersectionObserver' in window\)\)\s*\{[\s\S]*?items\.forEach\(reveal\)/);
  assert.match(script, /observer\.unobserve\(entry\.target\)/);

  assert.match(css, /\.twins-location-motion-ready \[data-location-reveal\]\s*\{[^}]*translateY\(10px\)[^}]*420ms ease-out/);
  assert.match(css, /\.twins-location-service-card\s*\{[^}]*transition:\s*transform 160ms ease-out/);
  assert.match(css, /\.twins-location-service-card:is\(:hover,\s*:focus-within\)\s*\{[^}]*translateY\(-3px\)/);
  assert.match(css, /\.twins-location-page \.twins-brand-cta--quote\s*\{[^}]*animation:\s*none[^}]*background-position:\s*100% 0[^}]*160ms ease-out/);
  assert.match(css, /\.twins-location-page \.twins-brand-cta--quote:is\(:hover,\s*:focus-visible\)\s*\{[^}]*background-position:\s*0 0/);
  assert.match(css, /\.twins-location-page \.twins-location-twin\s*\{[^}]*animation:\s*none/);

  assert.doesNotMatch(css, /\.twins-location[^,{]*\{[^}]*animation:[^;}]*infinite/);
  assert.doesNotMatch(css, /\.twins-location[^,{]*\{[^}]*animation:[^;}]*twins-brand-cta-pulse/);
  assert.doesNotMatch(css, /(^|\n)\[data-location-reveal\]\s*\{[^}]*opacity:\s*0/);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.twins-location-page \[data-location-reveal\][^{]*\{[^}]*opacity:\s*1 !important[^}]*transition:\s*none !important/);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)[\s\S]*?\.twins-location-page \.twins-location-service-card[^{]*\{[^}]*transform:\s*none !important[^}]*transition:\s*none !important/);
});
```

- [ ] **Step 2: Run the contract and verify failure**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: FAIL because the current reveal is 18 px/550 ms, location quote actions inherit the global pulse, and card interaction limits are absent.

- [ ] **Step 3: Implement the exact finite motion rules**

Replace the location reveal rules and add the direct interaction rules:

```css
.twins-location-motion-ready [data-location-reveal] {
  opacity: 0;
  transform: translateY(10px);
  transition: opacity 420ms ease-out, transform 420ms ease-out;
}
.twins-location-motion-ready [data-location-reveal][data-location-visible="true"] {
  opacity: 1;
  transform: translateY(0);
}
.twins-location-service-card { transition: transform 160ms ease-out; }
.twins-location-service-card:is(:hover, :focus-within) { transform: translateY(-3px); }
.twins-location-page .twins-brand-cta--quote {
  animation: none;
  background-position: 100% 0;
  transition: transform .12s ease, box-shadow .12s ease, filter .12s ease, background-position 160ms ease-out;
}
.twins-location-page .twins-brand-cta--quote:is(:hover, :focus-visible) { background-position: 0 0; }
.twins-location-page .twins-brand-cta--call { animation: none; }
```

Do not add JavaScript timers, scroll listeners, replay state, or new animation keyframes.

- [ ] **Step 4: Complete the location-specific reduced-motion override**

Inside the existing `@media (prefers-reduced-motion: reduce)` block, retain the global protections and use:

```css
.twins-location-page [data-location-reveal] {
  opacity: 1 !important;
  transform: none !important;
  transition: none !important;
}
.twins-location-page .twins-location-service-card,
.twins-location-page .twins-brand-cta {
  animation: none !important;
  transform: none !important;
  transition: none !important;
}
.twins-location-page .twins-location-twin,
.twins-location-page .twins-brand-door-art--door-open .twins-da-curtain {
  animation: none !important;
  transform: none !important;
}
```

- [ ] **Step 5: Run focused motion contracts and verify pass**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs tests/contracts/styles-and-script.test.cjs
```

Expected: all tests PASS. `assets/js/twins-brand.js` remains unchanged because it already reveals once, unobserves visible items, handles reduced motion, and fails open.

- [ ] **Step 6: Commit finite motion**

```bash
git add website/twins-brand-experience/assets/css/twins-brand.css website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
git diff --cached --check
git commit -m "fix: bound location page motion"
```

---

### Task 4: Synchronize the Rockford Fixture and Browser Coverage

**Files:**
- Modify: `website/twins-brand-experience/tests/browser/fixtures/location-modern.html`
- Modify: `website/twins-brand-experience/tests/browser/location-modern.spec.cjs:1-406`
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs:128-159`

**Interfaces:**
- Consumes: final template markup, production CSS and JavaScript, canonical Rockford values, booking dialog, shared header/footer, and mobile actions.
- Produces: one deterministic fixture, six responsive tests, one controlled reveal test, one no-observer test, and one reduced-motion test.

- [ ] **Step 1: Update browser expectations before changing the fixture**

In each viewport case, change the reveal count and add the service-list checks:

```js
await expect(page.locator('#twins-overhaul-main > [data-location-reveal]')).toHaveCount(4);
await expect(page.locator('.twins-location-hero')).not.toHaveAttribute('data-location-reveal', '');
await expect(page.locator('.twins-location-service-card--spotlight')).toHaveCount(1);
await expect(page.locator('.twins-location-service-card--spotlight')).toHaveJSProperty('tagName', 'ARTICLE');
await expect(page.locator('.twins-location-service-items')).toHaveCount(3);
await expect(page.locator('.twins-location-service-items li')).toHaveCount(9);
await expect(page.locator('.twins-brand-faq[aria-labelledby="twins-location-questions-title"]')).toHaveCount(0);
```

Add the service item text assertion:

```js
await expect(page.locator('.twins-location-service-items li')).toHaveText([
  'Broken springs',
  'Cables and rollers',
  'Off-track or noisy movement',
  'Safety sensors',
  'Remotes and wall controls',
  'Motors and drive systems',
  'Damaged door replacement',
  'Style and window choices',
  'Insulation options',
]);
```

Replace the old pale-background contrast sample with:

```js
const contrast = await page.evaluate(() => {
  const hero = document.querySelector('.twins-location-hero');
  const heroKicker = hero.querySelector('.twins-brand-kicker');
  const heroParagraph = hero.querySelector('.twins-location-hero-copy > p');
  const trust = document.querySelector('.twins-location-trust');
  const trustStrong = trust.querySelector('strong');
  const services = document.querySelector('.twins-location-services');
  const serviceHeading = services.querySelector('h2');
  const local = document.querySelector('.twins-location-local-proof');
  const localCopy = local.querySelector('.twins-location-local-proof-copy > p');
  return {
    heroBackground: getComputedStyle(hero).backgroundColor,
    heroKicker: getComputedStyle(heroKicker).color,
    heroParagraph: getComputedStyle(heroParagraph).color,
    trustBackground: getComputedStyle(trust).backgroundColor,
    trustStrong: getComputedStyle(trustStrong).color,
    servicesBackground: getComputedStyle(services).backgroundColor,
    serviceHeading: getComputedStyle(serviceHeading).color,
    localBackground: getComputedStyle(local).backgroundColor,
    localCopy: getComputedStyle(localCopy).color,
  };
});
expect(contrastRatio(contrast.heroKicker, contrast.heroBackground)).toBeGreaterThanOrEqual(4.5);
expect(contrastRatio(contrast.heroParagraph, contrast.heroBackground)).toBeGreaterThanOrEqual(4.5);
expect(contrastRatio(contrast.trustStrong, contrast.trustBackground)).toBeGreaterThanOrEqual(4.5);
expect(contrastRatio(contrast.serviceHeading, contrast.servicesBackground)).toBeGreaterThanOrEqual(4.5);
expect(contrastRatio(contrast.localCopy, contrast.localBackground)).toBeGreaterThanOrEqual(4.5);
expect(luminance(contrast.trustBackground)).toBeGreaterThan(luminance(contrast.servicesBackground));
```

Add `.twins-location-service-items` and `.twins-location-service-items li` to `layoutSelectors`.

- [ ] **Step 2: Add browser assertions for static characters, finite lift, and CTA sheen**

Inside each viewport case:

```js
const locationMotion = await page.evaluate(() => {
  const twinAnimations = [...document.querySelectorAll('.twins-location-twin')]
    .map(node => getComputedStyle(node).animationName);
  const quote = document.querySelector('.twins-location-final-cta .twins-brand-cta--quote');
  const card = document.querySelector('.twins-location-service-card');
  return {
    twinAnimations,
    quoteAnimation: getComputedStyle(quote).animationName,
    quoteBackgroundPosition: getComputedStyle(quote).backgroundPosition,
    cardTransition: getComputedStyle(card).transitionDuration,
  };
});
expect(locationMotion.twinAnimations).toEqual(['none', 'none', 'none']);
expect(locationMotion.quoteAnimation).toBe('none');
expect(locationMotion.cardTransition).toContain('0.16s');
```

For viewports wider than 768 px:

```js
const firstCard = page.locator('.twins-location-service-card').first();
await firstCard.hover();
await expect.poll(() => firstCard.evaluate(node => {
  const matrix = getComputedStyle(node).transform;
  return Number(matrix.match(/matrix\([^,]+,[^,]+,[^,]+,[^,]+,[^,]+,\s*([^)]+)\)/)?.[1]);
})).toBeCloseTo(-3, 1);

const finalQuote = page.locator('.twins-location-final-cta .twins-brand-cta--quote');
const restingPosition = await finalQuote.evaluate(node => getComputedStyle(node).backgroundPosition);
await finalQuote.hover();
await expect.poll(() => finalQuote.evaluate(node => getComputedStyle(node).backgroundPosition))
  .not.toBe(restingPosition);
```

- [ ] **Step 3: Add controlled reveal and fail-open tests**

Add:

```js
test('post-hero reveals move once by ten pixels and then settle', async ({ page }) => {
  await page.addInitScript(() => {
    window.__locationObservers = [];
    window.IntersectionObserver = class {
      constructor(callback) {
        this.callback = callback;
        this.targets = [];
        window.__locationObservers.push(this);
      }
      observe(target) { this.targets.push(target); }
      unobserve(target) { this.targets = this.targets.filter(item => item !== target); }
      disconnect() { this.targets = []; }
    };
  });
  await page.goto(fixture);

  const hero = page.locator('.twins-location-hero');
  const reveals = page.locator('#twins-overhaul-main > [data-location-reveal]');
  await expect(hero).toHaveCSS('opacity', '1');
  await expect(reveals).toHaveCount(4);
  await expect(reveals.first()).toHaveCSS('opacity', '0');
  expect(await reveals.first().evaluate(node => getComputedStyle(node).transform)).toContain(', 10)');

  await page.evaluate(() => {
    for (const observer of window.__locationObservers) {
      const entries = observer.targets.map(target => ({ target, isIntersecting: true }));
      observer.callback(entries);
    }
  });
  await expect(reveals.first()).toHaveAttribute('data-location-visible', 'true');
  await page.waitForTimeout(450);
  await expect(reveals.first()).toHaveCSS('opacity', '1');
  await expect(reveals.first()).toHaveCSS('transform', 'matrix(1, 0, 0, 1, 0, 0)');
});

test('location content fails open without IntersectionObserver', async ({ page }) => {
  await page.addInitScript(() => {
    delete window.IntersectionObserver;
  });
  await page.goto(fixture);

  await expect(page.locator('html')).not.toHaveClass(/twins-location-motion-ready/);
  const reveals = page.locator('#twins-overhaul-main > [data-location-reveal]');
  await expect(reveals).toHaveCount(4);
  for (const reveal of await reveals.all()) {
    await expect(reveal).toHaveAttribute('data-location-visible', 'true');
    await expect(reveal).toHaveCSS('opacity', '1');
    await expect(reveal).toHaveCSS('transform', 'none');
  }
});
```

In the reduced-motion test, change `expect(reveals).toHaveLength(5)` to `expect(reveals).toHaveLength(4)` and also assert:

```js
const interactions = await page.evaluate(() => {
  const card = getComputedStyle(document.querySelector('.twins-location-service-card'));
  const quote = getComputedStyle(document.querySelector('.twins-location-final-cta .twins-brand-cta--quote'));
  return {
    cardTransition: card.transitionDuration,
    cardTransform: card.transform,
    quoteTransition: quote.transitionDuration,
    quoteAnimation: quote.animationName,
  };
});
expect(interactions.cardTransition).toBe('0s');
expect(interactions.cardTransform).toBe('none');
expect(interactions.quoteTransition).toBe('0s');
expect(interactions.quoteAnimation).toBe('none');
```

- [ ] **Step 4: Run the browser test and verify the old fixture fails**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node node_modules/@playwright/test/cli.js test tests/browser/location-modern.spec.cjs
```

Expected: FAIL because the fixture still has a hero reveal, no service issue lists or spotlight class, and the old pale post-hero layout.

- [ ] **Step 5: Synchronize the fixture with Tasks 1–3**

In `tests/browser/fixtures/location-modern.html`:

- remove `data-location-reveal` from `.twins-location-hero`;
- keep `data-location-reveal` on trust, services, local proof, and final CTA;
- add `twins-location-service-card--spotlight` only to the opener-service article;
- add the three `.twins-location-service-items` lists with the exact nine labels from Task 1;
- remove the location FAQ section if present;
- preserve the Rockford phone `(815) 800-2025`, `tel:+18158002025`, address `5758 Elaine Dr Ste 110, Rockford, IL 61108`, rating, review count, routes, shared header/footer, booking dialog, and mobile actions;
- keep the service cameo and both final-CTA characters decorative and static;
- keep production CSS and `/assets/js/twins-brand.js`; add no fixture-only visual logic.

Extend the fixture contract:

```js
assert.equal((fixture.match(/data-location-reveal/g) || []).length, 4);
assert.doesNotMatch(fixture, /<header class="twins-location-hero"[^>]*data-location-reveal/);
assert.equal((fixture.match(/class="twins-location-service-items"/g) || []).length, 3);
assert.equal((fixture.match(/class="twins-location-service-card twins-location-service-card--spotlight"/g) || []).length, 1);
assert.doesNotMatch(fixture, /done today|same-day|\$0 service call|most repairs in one visit|recently opened/i);
```

- [ ] **Step 6: Run responsive and motion browser coverage**

Run the Step 4 command.

Expected: nine tests PASS: six viewports, controlled reveal, fail-open, and reduced motion. There is no horizontal overflow, clipped content, character collision, failed contrast check, oversized image, undersized action, autonomous Twin/CTA animation, or broken booking interaction.

- [ ] **Step 7: Commit the synchronized fixture and browser tests**

```bash
git add website/twins-brand-experience/tests/browser/fixtures/location-modern.html website/twins-brand-experience/tests/browser/location-modern.spec.cjs website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
git diff --cached --check
git commit -m "test: lock dark showroom location layout"
```

---

### Task 5: Full Verification and Package Refresh

**Files:**
- Verify: `website/twins-brand-experience/templates/editorial.php`
- Verify: `website/twins-brand-experience/assets/css/twins-brand.css`
- Verify: `website/twins-brand-experience/assets/js/twins-brand.js`
- Verify: `website/twins-brand-experience/tests/contracts/*.test.cjs`
- Verify: `website/twins-brand-experience/tests/php-harnesses.test.cjs`
- Verify: `website/twins-brand-experience/tests/browser/*.spec.cjs`
- Regenerate: `website/twins-brand-experience/dist/**`
- Regenerate: `website/twins-brand-experience/manifests/host-verification.json`
- Regenerate: `website/twins-brand-experience/manifests/staging-runtime.json`

**Interfaces:**
- Consumes: completed source, contracts, and deterministic browser fixture.
- Produces: green local verification and hash-pinned packages without staging deployment.

- [ ] **Step 1: Run renderer wrappers and all contracts**

From `website/twins-brand-experience`:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/*.test.cjs
```

Expected: both commands exit 0 with no failed tests. A local PHP-unavailable skip is acceptable only where the existing wrapper explicitly reports `PHP CLI unavailable locally`; the PHP renderer gate must run in a PHP-enabled environment before deployment.

- [ ] **Step 2: Verify owned assets**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-owned-images.mjs --check
```

Expected: exit 0 with no asset drift. This revision adds no new image files.

- [ ] **Step 3: Run the complete local browser suite**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node node_modules/@playwright/test/cli.js test
```

Expected: all local browser tests PASS. Staging-live checks are not evidence for this local revision because staging remains unchanged.

- [ ] **Step 4: Scan claims and inspect the exact source scope**

```bash
rg -ni "recently opened|newly opened|new to this market|done today|same-day|\\$0 service call|most repairs in one visit|guaranteed arrival" templates/editorial.php config/location-content.php tests/browser/fixtures/location-modern.html
git diff --check
git status --short
```

Expected: the phrase scan prints no matches, diff check is clean, and status shows no accidental change to `assets/js/twins-brand.js` or the pre-existing user edit in `tests/php/renderer-contract-harness.php`.

- [ ] **Step 5: Regenerate and verify packages once**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs --check
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/check-repository.mjs
```

Expected: every command exits 0. Generated outputs reflect only the completed template, CSS, contracts, and fixture source.

- [ ] **Step 6: Commit generated package changes**

```bash
git add website/twins-brand-experience/dist website/twins-brand-experience/manifests/host-verification.json website/twins-brand-experience/manifests/staging-runtime.json
git diff --cached --check
git commit -m "build: refresh dark showroom packages"
```

Omit any path the package builder does not change. Do not stage the unrelated renderer-harness edit.

---

### Task 6: Live Local Mockup and Approval Gate

**Files:**
- No source modification expected.
- Create review images under `/Users/daniel/Documents/Codex/2026-07-21/files-mentioned-by-the-user-you/`.

**Interfaces:**
- Consumes: verified production fixture and assets.
- Produces: live local URL, desktop screenshot, mobile screenshot, and an explicit visual-approval checkpoint.

- [ ] **Step 1: Start the established fixture server**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tests/browser/fixture-server.mjs --port 41739
```

Expected: the server remains running at `http://127.0.0.1:41739/tests/browser/fixtures/location-modern.html`.

- [ ] **Step 2: Open and inspect the live local mockup**

Use `browser:control-in-app-browser` to open:

```text
http://127.0.0.1:41739/tests/browser/fixtures/location-modern.html
```

Expected: unchanged shared header and contained hero; yellow verified-proof strip; dark paneled three-card services with a compositional yellow middle card; restrained real before-and-after image; outlined proof rows; compact final CTA; static Twins; subtle one-time reveals and direct hover/focus feedback only.

- [ ] **Step 3: Capture desktop and mobile review images**

Capture 1440×1000 and 390×844 views to:

```text
/Users/daniel/Documents/Codex/2026-07-21/files-mentioned-by-the-user-you/dark-showroom-location-desktop.png
/Users/daniel/Documents/Codex/2026-07-21/files-mentioned-by-the-user-you/dark-showroom-location-mobile.png
```

Expected: the two views share one coherent visual system, both photographs remain contained, service items scan cleanly, characters stay outside controls, and the lower page no longer reads as a sequence of pale empty bands.

- [ ] **Step 4: Present the live local URL and screenshots**

State that the mockup is local, staging is unchanged, and no release number has been rotated. Ask for explicit visual approval or precise revisions.

- [ ] **Step 5: Stop before deployment**

Do not run `deploy:staging:capture`, `deploy:staging:release`, rotate a release number, or alter staging. Deployment begins only after explicit visual approval and a PHP-enabled renderer gate.
