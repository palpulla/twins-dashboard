# Location Page r29 Premium Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the rejected cinematic location experience with a short, polished five-section page built from the recognizable r29 Twins Garage Doors structure and proportions.

**Architecture:** Restore the shared r29-style site header, then replace the location-only branch of `templates/editorial.php` with five explicit sections: contained hero, trust strip, services, local proof, and final CTA. Keep all business data, routes, actions, owned assets, and progressive reveal behavior in their existing layers; scope the new layout to `.twins-location-page` and verify it with renderer contracts plus a deterministic Rockford Playwright fixture.

**Tech Stack:** PHP 8 templates, CSS, vanilla JavaScript, Node.js 20 built-in tests, Playwright 1.61.1, and existing Twins owned image/font assets.

## Global Constraints

- Use r29 as the structural and visual baseline. Do not reuse the cinematic composition.
- Keep the established Twins logo and classic rounded display typeface.
- Use one centered content container with a maximum width of 1180 px.
- Hero photography occupies no more than about 40–44% of desktop hero width and has a maximum visible height of approximately 460 px.
- Mobile hero photography appears after copy/actions, uses a compact 4:3 crop, and stays below approximately 310 px tall.
- Use no more than two substantial photographs: one in the hero and one in local proof.
- Keep five primary sections after the header: hero, trust strip, services, local proof, and final CTA.
- Use no more than two twin-character moments: one small service cameo and the final CTA edge treatment.
- Do not use full-bleed imagery, glass panels, orbit graphics, dashboard styling, cinematic wipes, parallax, or oversized cards.
- Do not say or imply that a location recently opened.
- Do not invent response times, ratings, review counts, guarantees, certifications, service areas, financing terms, or local facts.
- Primary action text is `Get a Free Quote`; the verified location phone is the secondary urgent action.
- Required visual checks are 320, 360, 390, 768, 1024, and 1440 px.
- Touch targets are at least 44 px; there is no horizontal overflow; reduced-motion mode is complete and static.
- Preserve routing, booking behavior, analytics, phone data, location configuration, and unrelated worktree changes.
- Do not deploy or rotate a release until the user explicitly approves the live local mockup.

---

## File Structure

- `website/twins-brand-experience/components/header.php`: restores the shared r29 header hierarchy for location pages while retaining the location quote label.
- `website/twins-brand-experience/components/twin-character.php`: permits only the approved service cameo and final CTA edge characters.
- `website/twins-brand-experience/templates/editorial.php`: owns the five-section location markup and concise verified copy.
- `website/twins-brand-experience/assets/css/twins-brand.css`: owns the location-only 1180 px grid, contained imagery, premium finish, responsive behavior, and motion fallback.
- `website/twins-brand-experience/assets/js/twins-brand.js`: retains the existing fail-open section reveals and mobile action behavior; no new visual system is added.
- `website/twins-brand-experience/tests/contracts/components.test.cjs`: pins shared header structure and twin placement rules.
- `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`: pins five-section structure, forbidden cinematic remnants, image caps, copy safeguards, and action hierarchy.
- `website/twins-brand-experience/tests/php/renderer-contract-harness.php`: verifies all registered locations render the same safe structure, routes, character pairs, and content data.
- `website/twins-brand-experience/tests/browser/fixtures/location-modern.html`: deterministic Rockford fixture synchronized to the recovery markup.
- `website/twins-brand-experience/tests/browser/location-modern.spec.cjs`: verifies responsive alignment, image proportions, section count, target sizes, motion, and overflow.
- `website/twins-brand-experience/dist/**` and package manifests: regenerated once after all source work passes.

---

### Task 1: Restore Familiar Shared Header and Approved Character Pairs

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/components.test.cjs:15-55`
- Modify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php:540-650`
- Modify: `website/twins-brand-experience/components/header.php:10-160`
- Modify: `website/twins-brand-experience/components/twin-character.php:1-30`

**Interfaces:**
- Consumes: `$context['classification']`, `$quote['href']`, `$phoneHref`, `$phone`, `$bookingMode`, `$nav`, and existing route/asset helpers.
- Produces: one shared `.twins-brand-header` structure on every page; location pages use `Get a Free Quote`; the character component accepts exactly `['right','services']`, `['left','final-left']`, and `['right','final-right']`.

- [ ] **Step 1: Replace the compact-location header contract with a shared-header contract**

Replace `location classification selects a compact direct-service header with no booking CTA` with:

```js
test('location classification keeps the familiar shared header and location quote label', () => {
  const header = source('header.php');

  assert.match(header, /\$isLocationHeader\s*=\s*isset\(\$context\['classification'\]\)/);
  assert.match(
    header,
    /\$headerQuoteLabel\s*=\s*\$isLocationHeader\s*\?\s*'Get a Free Quote'\s*:\s*'Request a Quote'/,
  );
  assert.equal((header.match(/<header class="twins-brand-header"/g) || []).length, 1);
  assert.equal((header.match(/<div class="twins-brand-fascia">/g) || []).length, 1);
  assert.match(header, /twins-brand-utility/);
  assert.match(header, /twins-brand-primary-nav/);
  assert.match(header, /twins-brand-phone/);
  assert.doesNotMatch(header, /twins-brand-header--location|twins-brand-fascia--location/);
  assert.doesNotMatch(header, /twins-brand-location-nav|twins-brand-drawer--location/);
});
```

Add the component contract:

```js
test('location characters are limited to one service cameo and the final CTA edges', () => {
  const twin = source('twin-character.php');
  for (const pair of [
    "['right', 'services']",
    "['left', 'final-left']",
    "['right', 'final-right']",
  ]) {
    assert.match(twin, new RegExp(pair.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  assert.doesNotMatch(twin, /\['left', 'hero'\]|\['right', 'guidance'\]/);
});
```

- [ ] **Step 2: Update renderer assertions for the approved character output**

For every rendered location, replace the hero/guidance character assertions with:

```php
foreach (['services', 'final-left', 'final-right'] as $placement) {
    $expect(
        substr_count($renderedLocation, 'twins-location-twin--' . $placement) === 1,
        $slug . ' did not render exactly one ' . $placement . ' Twin'
    );
}
$expect(strpos($renderedLocation, 'twins-location-twin--hero') === false, $slug . ' retained the rejected hero Twin');
$expect(strpos($renderedLocation, 'twins-location-twin--guidance') === false, $slug . ' retained the removed guidance Twin');
$expect(substr_count($renderedLocation, '/brand/twin-left.png') === 1, $slug . ' did not render one left Twin');
$expect(substr_count($renderedLocation, '/brand/twin-right.png') === 2, $slug . ' did not render two right Twins');
$expect(substr_count($renderedLocation, 'alt="" aria-hidden="true"') === 3, $slug . ' Twin accessibility markup drifted');
```

- [ ] **Step 3: Run the focused contracts and verify failure**

Run from `website/twins-brand-experience`:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/components.test.cjs tests/php-harnesses.test.cjs
```

Expected: FAIL because the compact location-only header and hero/guidance character pairs still exist.

- [ ] **Step 4: Restore one shared header structure**

Keep the existing `$isLocationHeader` and `$headerQuoteLabel`, remove `$locationHeaderNav`, and remove the entire `if ($isLocationHeader)` header branch. The header opening and shared fascia must be:

```php
<header class="twins-brand-header" data-twins-header>
  <div class="twins-brand-utility">
    <details class="twins-brand-market-menu">
      <summary>Choose your service area</summary>
      <div class="twins-brand-market-menu-panel">
        <?php foreach ($experience->markets()->all($environment) as $availableKey => $availableMarket): ?>
          <?php if ($availableKey === 'main') continue; ?>
          <a href="<?= htmlspecialchars($experience->route($availableKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>">
            <strong><?= htmlspecialchars($availableMarket['label'], ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars($availableMarket['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($availableMarket['preview'] === true): ?><small>Private staging preview</small><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </details>
    <?php if ($marketKey === 'wi'): ?>
      <span class="twins-brand-utility-phones">
        <a class="twins-brand-phone" href="tel:+16084202377"><small>Madison</small> (608) 420-2377</a>
        <a class="twins-brand-phone" href="tel:+14148009271"><small>Milwaukee</small> (414) 800-9271</a>
      </span>
    <?php else: ?>
      <a class="twins-brand-phone" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endif; ?>
  </div>
  <div class="twins-brand-fascia">
    <a class="twins-brand-logo" href="<?= htmlspecialchars($experience->route('home', $marketKey), ENT_QUOTES, 'UTF-8') ?>" aria-label="Twins Garage Doors home">
      <img src="<?= htmlspecialchars($experience->asset('logo'), ENT_QUOTES, 'UTF-8') ?>" width="711" height="325" alt="Twins Garage Doors">
    </a>
    <nav class="twins-brand-primary-nav" aria-label="Primary navigation">
      <?php foreach ($nav as $group => $items): ?>
        <div class="twins-brand-nav-group">
          <button type="button" class="twins-brand-nav-trigger" aria-expanded="false"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></button>
          <div class="twins-brand-nav-panel<?= count($items) > 8 ? ' twins-brand-nav-panel--wide' : '' ?>">
            <?php foreach ($items as [$label, $routeKey]): ?>
              <a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($routeKey, $marketKey, $label), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </nav>
    <?php if ($bookingMode === 'dialog'): ?>
      <button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Online</button>
    <?php elseif ($bookingMode === 'external'): ?>
      <a class="twins-brand-cta twins-brand-cta--book" href="<?= htmlspecialchars($booking['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Book Online</a>
    <?php endif; ?>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($headerQuoteLabel, ENT_QUOTES, 'UTF-8') ?></a>
    <button type="button" class="twins-brand-menu-trigger" aria-expanded="false" aria-controls="twins-brand-drawer">Menu</button>
  </div>
```

Keep the existing shared drawer markup and booking dialog immediately after this block. In the fascia, drawer, and final dialog output, replace each `!$isLocationHeader && $bookingMode === ...` condition with `$bookingMode === ...` so the restored shared header retains its established booking behavior. Remove all location-only header classes and links.

- [ ] **Step 5: Restrict the character component to the approved pairs**

Use:

```php
$allowedPairs = [
    ['right', 'services'],
    ['left', 'final-left'],
    ['right', 'final-right'],
];
```

Keep the current fail-closed validation, decorative `alt="" aria-hidden="true"`, intrinsic dimensions, and lazy loading. Set `$loading = 'lazy';` for every approved pair because none appears in the hero.

- [ ] **Step 6: Run the focused contracts and verify they pass**

Run the Step 3 command.

Expected: component contracts PASS. The PHP wrapper PASSes when PHP is available; otherwise it reports only the existing PHP-unavailable skips.

- [ ] **Step 7: Commit the shared chrome and character contract**

```bash
git add website/twins-brand-experience/components/header.php website/twins-brand-experience/components/twin-character.php website/twins-brand-experience/tests/contracts/components.test.cjs website/twins-brand-experience/tests/php/renderer-contract-harness.php
git diff --cached --check
git commit -m "refactor: restore familiar location chrome"
```

---

### Task 2: Rebuild the Location Template as Five Focused Sections

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`
- Modify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php:540-700`
- Modify: `website/twins-brand-experience/templates/editorial.php:55-390,453-480`

**Interfaces:**
- Consumes: `$locationLabel`, `$locationRecord`, `$napRating`, `$napCount`, `$napAddress`, `$quote`, `$phoneHref`, `$phone`, `$experience->route()`, `picture.php`, `door-art.php`, and the Task 1 character pairs.
- Produces: exactly `.twins-location-hero`, `.twins-location-trust`, `.twins-location-services`, `.twins-location-local-proof`, and `.twins-location-final-cta` as the five location sections.

- [ ] **Step 1: Replace cinematic and high-density structure assertions**

Replace obsolete location structure tests with:

```js
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

test('local proof has one owned image and three concise proof statements', () => {
  assert.match(template, /twins-location-local-proof/);
  assert.match(template, /\$logicalKey = 'door-builder-before-after'/);
  assert.match(template, /twins-location-proof-list/);
  assert.match(template, /Complete system inspection/);
  assert.match(template, /Plain-language options/);
  assert.match(template, /Respect for your home/);
});
```

- [ ] **Step 2: Update renderer expectations before changing markup**

For every location record, require:

```php
foreach (['twins-location-hero', 'twins-location-trust', 'twins-location-services', 'twins-location-local-proof', 'twins-location-final-cta'] as $className) {
    $expect(strpos($renderedLocation, $className) !== false, $slug . ' omitted ' . $className);
}
foreach (['twins-location-system', 'twins-location-process', 'twins-location-branch', 'twins-location-nearby', 'twins-location-faq'] as $retiredClass) {
    $expect(strpos($renderedLocation, $retiredClass) === false, $slug . ' retained ' . $retiredClass);
}
$expect(substr_count($renderedLocation, 'class="twins-location-service-card"') === 3, $slug . ' did not render three service choices');
$expect(strpos($renderedLocation, 'Preventive maintenance') === false, $slug . ' retained the fourth service module');
$expect(substr_count($renderedLocation, '/door-builder/twins-before-after-install.webp') === 1, $slug . ' did not render the owned local-proof photo exactly once');
$expect(strpos($renderedLocation, 'LEGACY LOCATION BODY FOR ' . $slug) === false, $slug . ' rendered its legacy body');
```

Remove renderer expectations for five visible FAQ disclosures, nearby-area grids, process steps, and maintenance routes. Keep every route-locality, phone, address, prohibited-copy, and legacy-body safety assertion that remains applicable.

- [ ] **Step 3: Run focused contracts and verify failure**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs tests/php-harnesses.test.cjs
```

Expected: FAIL because the cinematic stage and seven supporting modules still render.

- [ ] **Step 4: Reduce the service data to three choices**

Set `$locationServiceCards` to the existing repair, opener service, and installation records only. Preserve their current routes, descriptions, art keys, and three bullet items. Remove the `Preventive maintenance` record completely.

- [ ] **Step 5: Replace the location branch with the five approved sections**

Use this structure inside `<?php if ($isLocation): ?>`:

```php
<header class="twins-location-hero" aria-labelledby="twins-location-title" data-location-reveal>
  <div class="twins-location-hero-copy">
    <span class="twins-brand-kicker">Garage door help in <?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?></span>
    <?= $editorialTitleMarkup ?>
    <p><?= htmlspecialchars($editorial['answer'], ENT_QUOTES, 'UTF-8') ?></p>
    <div class="twins-location-actions">
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Get a Free Quote</a>
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
    </div>
  </div>
  <figure class="twins-location-hero-media">
    <?php
    $logicalKey = 'technician-at-work';
    $sizes = '(max-width: 768px) 100vw, 42vw';
    $class = 'twins-location-hero-image';
    $loading = 'eager';
    require dirname(__DIR__) . '/components/picture.php';
    ?>
    <figcaption>Careful diagnosis. Clear options. Work centered on the complete door system.</figcaption>
  </figure>
</header>

<section class="twins-location-trust" role="list" aria-label="Why homeowners call Twins Garage Doors" data-location-reveal>
  <div role="listitem">
    <?php if ($napRating !== null): ?>
      <strong><span class="twins-brand-stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span> <?= htmlspecialchars((string) $napRating, ENT_QUOTES, 'UTF-8') ?> on Google</strong>
      <span><?= $napCount !== '' ? htmlspecialchars($napCount, ENT_QUOTES, 'UTF-8') . ' customer reviews' : 'Verified customer reviews' ?></span>
    <?php else: ?>
      <strong>Customer-reviewed service</strong><span>Real feedback from Twins customers</span>
    <?php endif; ?>
  </div>
  <div role="listitem"><strong>Family owned</strong><span>Run by twin brothers, not a franchise</span></div>
  <div role="listitem"><strong>Licensed and insured</strong><span>Professional service for your home</span></div>
</section>

<section class="twins-location-services" aria-labelledby="twins-location-services-title" data-location-reveal>
  <?php
  $character = 'right';
  $placement = 'services';
  require $twinCharacterComponent;
  ?>
  <div class="twins-location-section-heading">
    <span class="twins-brand-kicker">How we can help</span>
    <h2 id="twins-location-services-title">The right service for the door you have.</h2>
    <p>Twins checks the complete system before recommending repair, opener work, or replacement.</p>
  </div>
  <div class="twins-location-service-grid">
    <?php foreach ($locationServiceCards as $serviceCard): ?>
      <article class="twins-location-service-card">
        <?= twins_brand_door_art($serviceCard['art'], 'twins-location-service-art', 'location-service-' . $serviceCard['art']) ?>
        <h3><?= htmlspecialchars($serviceCard['title'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= htmlspecialchars($serviceCard['description'], ENT_QUOTES, 'UTF-8') ?></p>
        <a class="twins-location-service-link" href="<?= htmlspecialchars($experience->route($serviceCard['route'], $locationNavMarketKey), ENT_QUOTES, 'UTF-8') ?>">Explore <?= htmlspecialchars($serviceCard['title'], ENT_QUOTES, 'UTF-8') ?></a>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="twins-location-local-proof" aria-labelledby="twins-location-local-proof-title" data-location-reveal>
  <figure class="twins-location-local-proof-media">
    <?php
    $logicalKey = 'door-builder-before-after';
    $sizes = '(max-width: 768px) 100vw, 42vw';
    $class = 'twins-location-local-proof-image';
    $loading = 'lazy';
    require dirname(__DIR__) . '/components/picture.php';
    ?>
  </figure>
  <div class="twins-location-local-proof-copy">
    <span class="twins-brand-kicker">Local garage door service</span>
    <h2 id="twins-location-local-proof-title">Practical help for <?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?> homeowners.</h2>
    <?php if ($locationRecord !== null && $locationRecord['localNotes'] !== ''): ?>
      <p><?= htmlspecialchars($locationRecord['localNotes'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($napAddress !== ''): ?><p class="twins-location-address"><?= htmlspecialchars($napAddress, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <ul class="twins-location-proof-list">
      <li><strong>Complete system inspection</strong><span>Door, hardware, opener, controls, and safety equipment checked together.</span></li>
      <li><strong>Plain-language options</strong><span>Repair and replacement paths explained before work moves forward.</span></li>
      <li><strong>Respect for your home</strong><span>Licensed and insured service centered on safe, reliable operation.</span></li>
    </ul>
  </div>
</section>
```

Keep the shared non-location branch unchanged.

- [ ] **Step 6: Restore both twins to the location final CTA**

Inside `.twins-location-final-cta`, use:

```php
<?php if ($isLocation): ?>
  <?php
  $character = 'left';
  $placement = 'final-left';
  require $twinCharacterComponent;
  $character = 'right';
  $placement = 'final-right';
  require $twinCharacterComponent;
  ?>
<?php endif; ?>
```

Keep `Get a Free Quote` first, the verified phone action second, and the existing concise final-CTA copy.

- [ ] **Step 7: Run focused template and renderer tests**

Run the Step 3 command.

Expected: all focused tests PASS; each registered location contains five sections, three services, one supporting photo, and three decorative character images across two moments.

- [ ] **Step 8: Commit the template recovery**

```bash
git add website/twins-brand-experience/templates/editorial.php website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs website/twins-brand-experience/tests/php/renderer-contract-harness.php
git diff --cached --check
git commit -m "refactor: rebuild location page from r29"
```

---

### Task 3: Apply the Restrained Premium r29 Visual System

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css:228-274,1042-1660,1831-2060`

**Interfaces:**
- Consumes: the five section classes and three character placement classes from Tasks 1-2.
- Produces: shared 1180 px alignment, bounded hero/local images, warm-white body rhythm, subtle door-panel texture, restrained character geometry, and static reduced-motion behavior.

- [ ] **Step 1: Write measurable recovery CSS contracts**

Add:

```js
test('recovery CSS locks alignment and contained image proportions', () => {
  assert.match(css, /--twins-location-max:\s*1180px/);
  assert.match(css, /padding-inline:\s*max\(var\(--twins-location-gutter\),\s*calc\(\(100vw - var\(--twins-location-max\)\)\s*\/\s*2\)\)/);
  assert.match(css, /\.twins-location-hero\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1\.15fr\) minmax\(320px,\s*\.85fr\)/);
  assert.match(css, /\.twins-location-hero-media\s*\{[^}]*max-height:\s*460px/);
  assert.match(css, /\.twins-location-hero-image\s*\{[^}]*aspect-ratio:\s*4\s*\/\s*3/);
  assert.match(css, /\.twins-location-local-proof\s*\{[^}]*grid-template-columns:\s*minmax\(320px,\s*\.85fr\) minmax\(0,\s*1\.15fr\)/);
  assert.match(css, /@media \(max-width:\s*768px\)[\s\S]*?\.twins-location-hero-media\s*\{[^}]*max-height:\s*310px/);
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
```

- [ ] **Step 2: Run the location contract and verify failure**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: FAIL on cinematic classes, missing 1180 px token, and old media geometry.

- [ ] **Step 3: Remove location-only header CSS**

Delete `.twins-brand-header--location`, `.twins-brand-fascia--location`, `.twins-brand-location-nav`, `.twins-brand-location-phone`, `.twins-brand-drawer--location`, and all responsive variants. Shared header CSS remains unchanged.

- [ ] **Step 4: Replace the location block with the recovery CSS**

Replace the location-page block beginning at the `/* Location pages... */` comment with:

```css
/* Location pages: r29 structure with restrained premium finishing. */
.twins-location-page {
  --twins-location-max: 1180px;
  --twins-location-gutter: clamp(20px, 4vw, 32px);
  color: var(--twins-navy-950);
  background: #f8f4eb;
}
.twins-location-page :is(.twins-location-hero, .twins-location-trust, .twins-location-services, .twins-location-local-proof) {
  width: 100%;
  padding-inline: max(var(--twins-location-gutter), calc((100vw - var(--twins-location-max)) / 2));
}
.twins-location-hero {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
  align-items: center;
  gap: clamp(42px, 6vw, 76px);
  min-height: 610px;
  padding-block: clamp(64px, 7vw, 84px);
  color: var(--twins-white);
  background: var(--twins-navy-950);
  isolation: isolate;
}
.twins-location-hero::before,
.twins-location-final-cta::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  opacity: .22;
  background-image:
    repeating-linear-gradient(0deg, transparent 0 86px, rgba(159,190,220,.22) 87px 88px),
    linear-gradient(90deg, transparent 49.8%, rgba(159,190,220,.18) 50%, transparent 50.2%);
}
.twins-location-hero-copy { max-width: 660px; }
body.twins-brand-experience .twins-location-hero .twins-brand-kicker { color: var(--twins-gold); }
.twins-location-hero h1 {
  display: grid;
  max-width: 680px;
  margin: 14px 0 22px;
  color: var(--twins-white);
  font-family: 'Lilita One', Impact, sans-serif;
  font-size: clamp(3.25rem, 5.1vw, 4.65rem);
  line-height: .95;
  text-transform: uppercase;
}
.twins-location-title-accent { color: var(--twins-gold); }
.twins-location-hero-copy > p { max-width: 62ch; margin: 0; color: #e5eef8; font-size: 1.08rem; line-height: 1.62; }
.twins-location-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
.twins-location-hero-media {
  max-height: 460px;
  margin: 0;
  padding: 10px;
  overflow: hidden;
  background: var(--twins-gold);
  border: 1px solid rgba(255,255,255,.45);
  border-radius: 14px;
  box-shadow: 0 22px 44px rgba(0,0,0,.22);
}
.twins-location-hero-media picture { display: block; height: calc(100% - 42px); overflow: hidden; border-radius: 8px; }
.twins-location-hero-image { display: block; width: 100%; height: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
.twins-location-hero-media figcaption { min-height: 42px; display: flex; align-items: center; padding: 8px 4px 0; color: var(--twins-navy-950); font-size: .78rem; font-weight: 900; }
.twins-location-trust {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  padding-block: 0;
  background: var(--twins-white);
  border-bottom: 1px solid rgba(7,29,59,.16);
}
.twins-location-trust > div { min-height: 92px; display: grid; align-content: center; gap: 3px; padding: 18px 28px; border-left: 1px solid rgba(7,29,59,.14); }
.twins-location-trust > div:first-child { border-left: 0; }
.twins-location-trust strong { color: var(--twins-navy-950); font-weight: 1000; }
.twins-location-trust span { color: var(--twins-muted); font-size: .86rem; font-weight: 800; }
.twins-location-trust .twins-brand-stars { color: #9b6a00; }
.twins-location-services {
  position: relative;
  padding-block: clamp(72px, 7vw, 88px);
  overflow: hidden;
}
.twins-location-section-heading { display: grid; grid-template-columns: minmax(0, 1fr) minmax(280px, .7fr); gap: 42px; align-items: end; margin-bottom: 36px; }
.twins-location-section-heading .twins-brand-kicker { grid-column: 1; }
.twins-location-section-heading h2 { max-width: 720px; margin: 8px 0 0; font-size: clamp(2.55rem, 4.3vw, 4rem); line-height: .98; text-transform: uppercase; }
.twins-location-section-heading > p { grid-column: 2; grid-row: 1 / span 2; max-width: 54ch; margin: 0; color: var(--twins-muted); }
.twins-location-service-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-top: 1px solid rgba(7,29,59,.2); border-bottom: 1px solid rgba(7,29,59,.2); }
.twins-location-service-card { display: grid; align-content: start; gap: 14px; min-height: 350px; padding: 32px; background: transparent; border-left: 1px solid rgba(7,29,59,.2); }
.twins-location-service-card:first-child { border-left: 0; }
.twins-location-service-art { width: 76px; height: 56px; margin-bottom: 8px; }
.twins-location-service-card h3 { margin: 0; color: var(--twins-navy-950); font-size: 1.8rem; text-transform: uppercase; }
.twins-location-service-card p { margin: 0; color: var(--twins-muted); }
body.twins-brand-experience .twins-location-service-link { display: inline-flex; align-items: center; min-height: 44px; margin-top: auto; color: var(--twins-navy-800); font-weight: 1000; text-underline-offset: 4px; }
.twins-location-local-proof {
  display: grid;
  grid-template-columns: minmax(320px, .85fr) minmax(0, 1.15fr);
  gap: clamp(44px, 6vw, 78px);
  align-items: center;
  padding-block: clamp(72px, 7vw, 88px);
  background: var(--twins-white);
  border-top: 1px solid rgba(7,29,59,.12);
}
.twins-location-local-proof-media { max-height: 440px; margin: 0; padding: 8px; overflow: hidden; background: var(--twins-navy-950); border-radius: 14px; }
.twins-location-local-proof-media picture { display: block; height: 100%; overflow: hidden; border-radius: 8px; }
.twins-location-local-proof-image { display: block; width: 100%; height: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
.twins-location-local-proof-copy h2 { max-width: 760px; margin: 10px 0 18px; font-size: clamp(2.55rem, 4.3vw, 4rem); line-height: .98; text-transform: uppercase; }
.twins-location-local-proof-copy > p { max-width: 64ch; color: var(--twins-muted); }
.twins-location-address { color: var(--twins-navy-800) !important; font-weight: 1000; }
.twins-location-proof-list { display: grid; gap: 0; margin: 26px 0 0; padding: 0; border-top: 1px solid rgba(7,29,59,.16); list-style: none; }
.twins-location-proof-list li { display: grid; grid-template-columns: minmax(170px, .6fr) minmax(0, 1fr); gap: 20px; padding: 16px 0; border-bottom: 1px solid rgba(7,29,59,.16); }
.twins-location-proof-list strong { color: var(--twins-navy-950); }
.twins-location-proof-list span { color: var(--twins-muted); }
.twins-location-page .twins-location-twin { position: absolute; z-index: 2; display: block; height: auto; pointer-events: none; filter: drop-shadow(3px 7px 7px rgba(0,0,0,.2)); animation: twins-location-float 6s ease-in-out infinite; }
.twins-location-twin--services { right: var(--twins-location-gutter); bottom: 14px; width: clamp(72px, 7vw, 104px); }
.twins-location-twin--final-left { left: max(18px, calc((100vw - 1320px) / 2)); bottom: -14px; width: clamp(86px, 8vw, 118px); }
.twins-location-twin--final-right { right: max(18px, calc((100vw - 1320px) / 2)); bottom: -14px; width: clamp(92px, 8vw, 124px); }
.twins-location-final-cta { position: relative; isolation: isolate; min-height: 360px; padding: 72px clamp(140px, 13vw, 210px); overflow: hidden; color: var(--twins-white); background: var(--twins-navy-950); }
.twins-location-final-cta > p { max-width: 650px; margin: 0 auto 24px; color: #e5eef8; }
.twins-location-motion-ready [data-location-reveal] { opacity: 0; transform: translateY(18px); transition: opacity .55s ease, transform .55s ease; }
.twins-location-motion-ready [data-location-reveal][data-location-visible="true"] { opacity: 1; transform: translateY(0); }
```

- [ ] **Step 5: Add the responsive recovery rules**

```css
@media (max-width: 1024px) {
  .twins-location-hero { grid-template-columns: minmax(0, 1.05fr) minmax(300px, .95fr); gap: 34px; min-height: 560px; }
  .twins-location-service-card { min-height: 330px; padding: 26px; }
  .twins-location-local-proof { grid-template-columns: minmax(280px, .9fr) minmax(0, 1.1fr); gap: 38px; }
}
@media (max-width: 768px) {
  .twins-location-page { --twins-location-gutter: 20px; }
  .twins-location-hero { grid-template-columns: 1fr; gap: 30px; min-height: 0; padding-block: 52px; }
  .twins-location-hero h1 { font-size: clamp(2.7rem, 11vw, 3.9rem); }
  .twins-location-hero-media { width: min(100%, 520px); max-height: 310px; }
  .twins-location-hero-media picture { height: calc(100% - 42px); }
  .twins-location-trust { grid-template-columns: 1fr; }
  .twins-location-trust > div { min-height: 74px; padding: 14px 20px; border-top: 1px solid rgba(7,29,59,.14); border-left: 0; }
  .twins-location-trust > div:first-child { border-top: 0; }
  .twins-location-services,
  .twins-location-local-proof { padding-block: 56px; }
  .twins-location-section-heading { grid-template-columns: 1fr; gap: 14px; margin-bottom: 28px; }
  .twins-location-section-heading > p { grid-column: 1; grid-row: auto; }
  .twins-location-service-grid { grid-template-columns: 1fr; }
  .twins-location-service-card { min-height: 0; padding: 26px 0; border-top: 1px solid rgba(7,29,59,.2); border-left: 0; }
  .twins-location-service-card:first-child { border-top: 0; }
  .twins-location-local-proof { grid-template-columns: 1fr; }
  .twins-location-local-proof-media { width: min(100%, 520px); max-height: 310px; }
  .twins-location-proof-list li { grid-template-columns: 1fr; gap: 5px; }
  .twins-location-final-cta { min-height: 340px; padding: 60px 116px 100px; }
}
@media (max-width: 480px) {
  .twins-location-page { --twins-location-gutter: 20px; }
  .twins-location-actions .twins-brand-cta { flex: 1 1 100%; justify-content: center; }
  .twins-location-twin--services { display: none; }
  .twins-location-final-cta { padding: 54px 20px 142px; }
  .twins-location-twin--final-left { left: 16px; width: 76px; }
  .twins-location-twin--final-right { right: 16px; width: 82px; }
}
@media (max-width: 320px) {
  .twins-location-page { --twins-location-gutter: 16px; }
  .twins-location-hero h1 { font-size: 2.5rem; }
}
@media (prefers-reduced-motion: reduce) {
  .twins-location-page [data-location-reveal] { opacity: 1 !important; transform: none !important; transition: none !important; }
  .twins-location-page .twins-location-twin { animation: none !important; transform: none !important; }
}
```

Remove every obsolete location media-query rule after installing these blocks. Preserve unrelated global and page-family CSS.

- [ ] **Step 6: Run focused CSS and script contracts**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs tests/contracts/styles-and-script.test.cjs tests/contracts/components.test.cjs
```

Expected: all focused contracts PASS. Existing `initLocationReveals()` remains fail-open and no new JavaScript is required.

- [ ] **Step 7: Commit the recovery visual system**

```bash
git add website/twins-brand-experience/assets/css/twins-brand.css website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
git diff --cached --check
git commit -m "style: apply premium r29 location system"
```

---

### Task 4: Synchronize Rockford Fixture and Lock Responsive Layout

**Files:**
- Modify: `website/twins-brand-experience/tests/browser/fixtures/location-modern.html`
- Modify: `website/twins-brand-experience/tests/browser/location-modern.spec.cjs`
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`

**Interfaces:**
- Consumes: final shared header, five-section markup, CSS selectors, production JavaScript, verified Rockford phone/address/copy, and shared footer.
- Produces: deterministic fixture plus six viewport tests and one reduced-motion test.

- [ ] **Step 1: Replace cinematic browser expectations with recovery expectations**

At every viewport, require:

```js
await expect(page.locator('.twins-brand-header')).toHaveCount(1);
await expect(page.locator('.twins-brand-header--location')).toHaveCount(0);
await expect(page.locator('.twins-brand-utility')).toHaveCount(1);
await expect(page.locator('.twins-brand-primary-nav')).toHaveCount(1);
await expect(page.locator('.twins-location-hero')).toHaveCount(1);
await expect(page.locator('.twins-location-trust')).toHaveCount(1);
await expect(page.locator('.twins-location-service-card')).toHaveCount(3);
await expect(page.locator('.twins-location-local-proof')).toHaveCount(1);
await expect(page.locator('.twins-location-final-cta')).toHaveCount(1);
await expect(page.locator('.twins-location-hero-stage, .twins-location-orbit, .twins-location-process, .twins-location-nearby, .twins-location-faq')).toHaveCount(0);
await expect(page.locator('.twins-location-hero .twins-brand-cta--quote')).toHaveText('Get a Free Quote');
await expect(page.locator('.twins-location-twin')).toHaveCount(3);
await expect(page.locator('.twins-location-twin--services')).toHaveCount(1);
await expect(page.locator('.twins-location-twin--final-left')).toHaveCount(1);
await expect(page.locator('.twins-location-twin--final-right')).toHaveCount(1);
```

Update `visibleTargetAudit()` to inspect shared header navigation, shared drawer actions, service links, hero/final actions, and mobile actions. Remove every `.twins-brand-header--location` selector.

- [ ] **Step 2: Add measurable alignment and image-size assertions**

```js
const layout = await page.evaluate(() => {
  const rect = selector => document.querySelector(selector).getBoundingClientRect();
  const hero = rect('.twins-location-hero');
  const services = rect('.twins-location-services');
  const local = rect('.twins-location-local-proof');
  const heroMedia = rect('.twins-location-hero-media');
  const heroCopy = rect('.twins-location-hero-copy');
  const servicesHeading = rect('.twins-location-section-heading');
  const localMedia = rect('.twins-location-local-proof-media');
  const localCopy = rect('.twins-location-local-proof-copy');
  return { hero, services, local, heroMedia, heroCopy, servicesHeading, localMedia, localCopy };
});
expect(Math.abs(layout.heroCopy.left - layout.servicesHeading.left)).toBeLessThanOrEqual(1);
expect(Math.abs(layout.servicesHeading.left - layout.localMedia.left)).toBeLessThanOrEqual(1);
expect(layout.hero.right).toBeLessThanOrEqual(viewport.width + 1);
expect(layout.services.right).toBeLessThanOrEqual(viewport.width + 1);
expect(layout.local.right).toBeLessThanOrEqual(viewport.width + 1);
expect(layout.heroMedia.height).toBeLessThanOrEqual(viewport.width <= 768 ? 311 : 461);
expect(layout.localMedia.height).toBeLessThanOrEqual(viewport.width <= 768 ? 311 : 441);
if (viewport.width > 768) {
  expect(layout.heroMedia.width / layout.hero.width).toBeLessThanOrEqual(.45);
  expect(layout.localMedia.height).toBeLessThanOrEqual(layout.localCopy.height + 1);
} else {
  expect(layout.heroCopy.bottom).toBeLessThanOrEqual(layout.heroMedia.top + 1);
}
```

Keep the existing horizontal-overflow, clipped-element, 44 px target, contrast, CTA hierarchy, footer-catalog, and reduced-motion audits. Remove process connector, orbit layering, glass blur, full-stage, fourth-service, FAQ, and maintenance assertions.

- [ ] **Step 3: Run the browser test and verify the old fixture fails**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node node_modules/playwright/cli.js test tests/browser/location-modern.spec.cjs
```

Expected: FAIL because the fixture still contains the cinematic stage, compact location header, four services, and retired modules.

- [ ] **Step 4: Synchronize the fixture exactly to Tasks 1-3**

In `tests/browser/fixtures/location-modern.html`:

- replace the location-only header with the shared utility/fascia/navigation/drawer structure emitted by `components/header.php`;
- keep verified Rockford values `(815) 800-2025`, `tel:+18158002025`, and `5758 Elaine Dr Ste 110, Rockford, IL 61108`;
- replace the cinematic hero stage with `.twins-location-hero` containing copy and one framed technician picture;
- place the three proof items in the separate `.twins-location-trust[role="list"]` section;
- render exactly three `.twins-location-service-card` articles for repair, opener service, and installation;
- render `.twins-location-local-proof` with the owned before/after picture and the three proof-list statements from Task 2;
- remove system, process, branch, nearby, FAQ, orbit, glass proof, maintenance, hero twin, and guidance twin markup;
- render the service cameo plus both final CTA edge characters with decorative semantics;
- keep the shared footer and mobile actions unchanged;
- load `<script src="/assets/js/twins-brand.js"></script>` and do not add fixture-only behavior.

Update the fixture contract with:

```js
assert.match(fixture, /Rockford/);
assert.match(fixture, /tel:\+18158002025/);
assert.match(fixture, /5758 Elaine Dr Ste 110, Rockford, IL 61108/);
assert.equal((fixture.match(/class="twins-location-service-card"/g) || []).length, 3);
assert.doesNotMatch(fixture, /twins-location-hero-stage|twins-location-orbit|Preventive maintenance/);
assert.doesNotMatch(fixture, /recently opened|newly opened|new to this market/i);
```

- [ ] **Step 5: Run all six viewport cases and reduced motion**

Run the Step 3 command.

Expected: seven Playwright tests PASS. The page has no horizontal overflow, no character/content collision, no image over the specified caps, consistent grid edges, and all visible actions are at least 44×44 px.

- [ ] **Step 6: Commit fixture and responsive coverage**

```bash
git add website/twins-brand-experience/tests/browser/fixtures/location-modern.html website/twins-brand-experience/tests/browser/location-modern.spec.cjs website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
git diff --cached --check
git commit -m "test: lock premium r29 location layout"
```

---

### Task 5: Full Verification and Package Refresh

**Files:**
- Verify: `website/twins-brand-experience/tests/contracts/*.test.cjs`
- Verify: `website/twins-brand-experience/tests/php-harnesses.test.cjs`
- Verify: `website/twins-brand-experience/tests/browser/*.spec.cjs`
- Regenerate: `website/twins-brand-experience/dist/**`
- Regenerate: `website/twins-brand-experience/manifests/host-verification.json`
- Regenerate: `website/twins-brand-experience/manifests/staging-runtime.json`

**Interfaces:**
- Consumes: completed source, tests, and deterministic fixture.
- Produces: green repository verification and hash-pinned packages without deployment.

- [ ] **Step 1: Run renderer and contract suites**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/*.test.cjs
```

Expected: both commands exit 0 with no failed tests. PHP-unavailable skips are acceptable only where already documented by the wrapper.

- [ ] **Step 2: Verify assets and regenerate packages once**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-owned-images.mjs --check
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs --check
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/check-repository.mjs
```

Expected: every command exits 0. Generated package changes correspond only to the completed header, component, template, CSS, and test fixture source.

- [ ] **Step 3: Run the complete browser suite**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node node_modules/playwright/cli.js test
```

Expected: all local Playwright tests PASS. Do not treat `live-private-staging.spec.cjs` as proof of the local redesign; staging intentionally remains unchanged.

- [ ] **Step 4: Scan forbidden language and inspect scope**

```bash
rg -ni "recently opened|newly opened|new to this market|same-day|guaranteed response" templates/editorial.php config/location-content.php tests/browser/fixtures/location-modern.html
git status --short
git diff --check
```

Expected: the phrase scan prints no matches, diff check is clean, and status contains only intentional generated package changes.

- [ ] **Step 5: Commit generated packages**

```bash
git add website/twins-brand-experience/dist website/twins-brand-experience/manifests/host-verification.json website/twins-brand-experience/manifests/staging-runtime.json
git diff --cached --check
git commit -m "build: refresh premium location packages"
```

Omit any path that the package builder does not change.

---

### Task 6: Live Local Mockup and Approval Gate

**Files:**
- No source modification expected.
- Create review images under `/Users/daniel/Documents/Codex/2026-07-21/files-mentioned-by-the-user-you/`.

**Interfaces:**
- Consumes: verified fixture and production assets.
- Produces: live local URL, desktop screenshot, mobile screenshot, and an explicit user approval checkpoint.

- [ ] **Step 1: Start the fixture server on the established local port**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tests/browser/fixture-server.mjs --port 41739
```

Expected: the server remains running at `http://127.0.0.1:41739/tests/browser/fixtures/location-modern.html`.

- [ ] **Step 2: Open and inspect the live local mockup**

Use `browser:control-in-app-browser` to open:

```text
http://127.0.0.1:41739/tests/browser/fixtures/location-modern.html
```

Expected: familiar shared header; contained split hero; separate compact trust row; three restrained service choices; one local proof image; focused final CTA; no cinematic stage or oversized media.

- [ ] **Step 3: Capture desktop and mobile review images**

Capture 1440×1000 and 390×844 views to:

```text
/Users/daniel/Documents/Codex/2026-07-21/files-mentioned-by-the-user-you/r29-premium-recovery-desktop.png
/Users/daniel/Documents/Codex/2026-07-21/files-mentioned-by-the-user-you/r29-premium-recovery-mobile.png
```

Expected: desktop and mobile share the same visual system; grid edges align; both substantial images stay contained; the service cameo and final CTA characters remain secondary.

- [ ] **Step 4: Present the local URL and screenshots**

State that the mockup is local, staging has not changed, and no release number has been rotated. Ask for explicit visual approval or precise corrections.

- [ ] **Step 5: Stop before deployment**

Do not run `deploy:staging:capture`, `deploy:staging:release`, rotate a release number, or alter staging. Deployment begins only after explicit visual approval.
