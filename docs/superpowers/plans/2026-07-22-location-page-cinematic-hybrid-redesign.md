# Location Page Cinematic Hybrid Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the shared Twins Garage Doors location page as a 60% cinematic command-center / 40% established Twins hybrid, beginning with a locally reviewable Rockford mockup and making no staging deployment before user approval.

**Architecture:** Keep the existing PHP rendering and route/content registries, but replace the rejected split-hero and repeated-card composition inside `templates/editorial.php`. Scope the new visual system to `.twins-location-page` in the existing stylesheet, add a small progressive-enhancement reveal controller to the existing JavaScript, and convert the hand-authored browser fixture to verified Rockford content synchronized with rendered markup. Contract tests lock structure and factual safeguards; Playwright locks responsive, accessibility, motion, and conversion behavior.

**Tech Stack:** PHP 8 templates, CSS, vanilla JavaScript, Node.js 20 test runner, Playwright 1.61.1, existing Twins owned image/font assets.

## Global Constraints

- Direction is 60% cinematic command-center visual language / 40% established Twins website language.
- Do not say or imply that the Rockford location "recently opened."
- Do not invent response times, guarantees, ratings, certifications, review counts, or local facts.
- Do not alter booking behavior, analytics, routing, phone data, or location data.
- Do not redesign unrelated page types.
- Do not deploy this direction until the user approves a live local mockup.
- Preserve the current unrelated worktree edit in `website/twins-brand-experience/config/page-content.php`; never stage it in this plan.
- Use the current Twins logo, `Lilita One` display font, owned `technician-at-work` image variants, existing door art, and approved twin-character assets. Add no external asset dependency.
- Primary action text is `Get a Free Quote`; the verified location phone action remains the urgent secondary action.
- Required visual checks are 320, 360, 390, 768, 1024, and 1440 px.
- Touch targets are at least 44 px, the page has no horizontal overflow, and reduced-motion mode is complete and static.

## File map

- Modify `website/twins-brand-experience/templates/editorial.php`: location-only title treatment, integrated hero/proof composition, service pathway markup, reveal hooks, and conversion labels.
- Modify `website/twins-brand-experience/assets/css/twins-brand.css`: replace the location-page block with cinematic hero, warm/dark section rhythm, service pathway, garage-door textures, responsive rules, and reduced-motion fallbacks.
- Modify `website/twins-brand-experience/assets/js/twins-brand.js`: progressive reveal enhancement that fails open and respects reduced motion.
- Modify `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`: structural, content, CSS, and motion contracts for the new direction.
- Modify `website/twins-brand-experience/tests/browser/fixtures/location-modern.html`: Rockford browser fixture that mirrors the new rendered location markup and loads the production script.
- Modify `website/twins-brand-experience/tests/browser/location-modern.spec.cjs`: viewport, hierarchy, visual-system, reveal, interaction, and reduced-motion assertions.
- Modify `website/twins-brand-experience/components/footer.php`: location-path-only mobile labels `Call Now` and `Get a Free Quote`; all non-location labels remain unchanged.
- Verify but do not normally modify `website/twins-brand-experience/tests/php/renderer-contract-harness.php`: all registered city pages still render the shared classes, safe content, routes, mascots, and actions.
- Do not modify `website/twins-brand-experience/config/location-content.php`, `config/markets.php`, `config/review-summary.php`, `components/header.php`, `components/twin-character.php`, or `components/door-art.php` unless a failing pre-existing contract proves the approved behavior cannot otherwise be preserved.

---

### Task 1: Integrated Cinematic Hero and Glass Proof Cluster

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`
- Modify: `website/twins-brand-experience/templates/editorial.php:109-215`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css:1044-1110`

**Interfaces:**
- Consumes: `$locationLabel`, `$editorial['answer']`, `$napRating`, `$napCount`, `$quote['href']`, `$phoneHref`, `$phone`, `picture.php`, and the approved `['left', 'hero']` twin pair.
- Produces: `.twins-location-hero-stage`, `.twins-location-title-accent`, `.twins-location-hero-media`, `.twins-location-orbit`, and `.twins-location-hero-proof`; later browser checks depend on these exact class names.

- [ ] **Step 1: Replace the old hero geometry contract with the cinematic composition contract**

Add this test after the high-density section test:

```js
test('location hero is one cinematic composition with integrated proof', () => {
  for (const className of [
    'twins-location-hero-stage',
    'twins-location-title-accent',
    'twins-location-hero-media',
    'twins-location-orbit',
    'twins-location-hero-proof',
  ]) {
    assert.match(template, new RegExp(className), `${className} is missing from the cinematic hero`);
  }
  assert.match(template, /Get a Free Quote/);
  assert.match(template, /role="list"/);
  assert.match(template, /role="listitem"/);
  assert.doesNotMatch(template, /<\/header>\s*<section class="twins-location-proof"/,
    'proof must remain inside the unified hero stage');
});
```

In `location design preserves the old display font...`, replace the obsolete border-left assertion with:

```js
assert.match(css, /\.twins-location-hero h1\s*\{[^}]*font-family:\s*'Lilita One'/);
assert.match(css, /\.twins-location-title-accent\s*\{[^}]*color:\s*var\(--twins-gold\)/);
assert.match(css, /\.twins-location-hero-media\s*\{[^}]*position:\s*absolute/);
assert.match(css, /\.twins-location-hero-proof\s*\{[^}]*backdrop-filter:\s*blur/);
assert.doesNotMatch(css, /\.twins-location-hero-media\s*\{[^}]*border-left:/,
  'the hero photo must not return to a boxed split-column treatment');
```

- [ ] **Step 2: Run the focused contract and verify the new assertions fail**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: FAIL for missing `twins-location-hero-stage`, `twins-location-title-accent`, `twins-location-orbit`, and `.twins-location-hero-proof` blur styling.

- [ ] **Step 3: Give location titles a safe two-line brand treatment**

Replace the title construction at `templates/editorial.php:109-111` with:

```php
$articleHeroImage = $isArticle && isset($articleHero) && is_string($articleHero) ? $articleHero : '';
$editorialTitleId = $isLocation ? 'twins-location-title' : 'twins-brand-editorial-title';
$editorialTitleMarkup = $isLocation
    ? '<h1 id="' . $editorialTitleId . '"><span>Garage door service</span><span class="twins-location-title-accent">in ' . htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') . '</span></h1>'
    : '<h1 id="' . $editorialTitleId . '">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
```

This keeps the city escaped, gives the hero one gold phrase, and leaves trust/article titles unchanged.

- [ ] **Step 4: Replace the split hero and separate proof strip with one stage**

Replace the current location `<header>` plus the following `.twins-location-proof` section with:

```php
<header class="twins-location-hero" aria-labelledby="twins-location-title">
  <div class="twins-location-hero-stage" data-location-reveal>
    <span class="twins-location-orbit twins-location-orbit--one" aria-hidden="true"></span>
    <span class="twins-location-orbit twins-location-orbit--two" aria-hidden="true"></span>
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
      $sizes = '(max-width: 1024px) 100vw, 58vw';
      $class = 'twins-location-hero-image';
      $loading = 'eager';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
      <?php
      $character = 'left';
      $placement = 'hero';
      require $twinCharacterComponent;
      ?>
    </figure>
    <div class="twins-location-hero-proof" role="list" aria-label="Why homeowners call Twins Garage Doors">
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
    </div>
  </div>
</header>
```

- [ ] **Step 5: Replace only the hero/proof CSS with the cinematic stage**

Use these declarations in place of the old `.twins-location-hero` through `.twins-location-proof .twins-brand-stars` block:

```css
.twins-location-page { background: var(--twins-cream); }
.twins-location-hero {
  position: relative;
  min-height: min(860px, calc(100svh - 84px));
  padding: clamp(22px, 3vw, 46px) var(--twins-content-shell) clamp(34px, 5vw, 70px);
  color: var(--twins-white);
  background:
    radial-gradient(circle at 76% 42%, rgba(44,105,174,.3), transparent 29%),
    radial-gradient(circle at 42% 18%, rgba(255,200,61,.11), transparent 20%),
    linear-gradient(132deg, #020d20 0%, var(--twins-navy-950) 48%, #092853 100%);
  overflow: hidden;
}
.twins-location-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: .28;
  background-image:
    linear-gradient(rgba(164,195,226,.1) 1px, transparent 1px),
    linear-gradient(90deg, rgba(164,195,226,.1) 1px, transparent 1px);
  background-size: 72px 72px;
  mask-image: linear-gradient(90deg, #000, transparent 76%);
}
.twins-location-hero-stage {
  position: relative;
  max-width: 1480px;
  min-height: 720px;
  margin-inline: auto;
  border: 1px solid rgba(167,198,231,.22);
  border-radius: clamp(18px, 2vw, 30px);
  overflow: hidden;
  isolation: isolate;
}
.twins-location-hero-copy {
  position: relative;
  z-index: 4;
  width: min(670px, 55%);
  padding: clamp(74px, 8vw, 124px) 0 190px clamp(26px, 5vw, 78px);
}
body.twins-brand-experience .twins-location-hero .twins-brand-kicker { color: var(--twins-gold); }
.twins-location-hero h1 {
  display: grid;
  max-width: 760px;
  margin: 18px 0 24px;
  color: var(--twins-white);
  font-family: 'Lilita One', Impact, sans-serif;
  font-size: clamp(3.6rem, 6.15vw, 6.6rem);
  line-height: .84;
  letter-spacing: .005em;
  text-transform: uppercase;
}
.twins-location-title-accent { color: var(--twins-gold); }
.twins-location-hero-copy > p {
  max-width: 640px;
  margin: 0;
  color: #eaf2fb;
  font-size: clamp(1.05rem, 1.35vw, 1.22rem);
  font-weight: 700;
  line-height: 1.62;
}
.twins-location-actions { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 30px; }
.twins-location-hero-media {
  position: absolute;
  z-index: 1;
  inset: 0 0 0 36%;
  margin: 0;
  overflow: hidden;
}
.twins-location-hero-media::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  background: linear-gradient(90deg, #05152e 0%, rgba(5,21,46,.76) 14%, transparent 48%), linear-gradient(0deg, rgba(2,13,32,.9), transparent 48%);
}
.twins-location-hero-media picture { display: block; width: 100%; height: 100%; }
.twins-location-hero-image { display: block; width: 100%; height: 100%; object-fit: cover; object-position: center; }
.twins-location-orbit {
  position: absolute;
  z-index: 3;
  pointer-events: none;
  border: 1px solid rgba(255,200,61,.3);
  border-radius: 50%;
}
.twins-location-orbit--one { width: 620px; aspect-ratio: 1; right: -110px; top: -180px; }
.twins-location-orbit--two { width: 390px; aspect-ratio: 1; right: 118px; top: 58px; border-color: rgba(167,198,231,.25); }
.twins-location-hero-proof {
  position: absolute;
  z-index: 5;
  right: clamp(18px, 3vw, 44px);
  bottom: clamp(18px, 3vw, 38px);
  left: clamp(18px, 3vw, 44px);
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  background: rgba(3,18,43,.68);
  border: 1px solid rgba(184,210,237,.28);
  border-radius: 14px;
  backdrop-filter: blur(18px);
}
.twins-location-hero-proof > div { display: grid; align-content: center; gap: 3px; min-height: 96px; padding: 18px 24px; border-left: 1px solid rgba(184,210,237,.2); }
.twins-location-hero-proof > div:first-child { border-left: 0; }
.twins-location-hero-proof strong { color: var(--twins-white); font-size: 1.02rem; font-weight: 1000; }
.twins-location-hero-proof span { color: #cbd9e8; font-size: .86rem; font-weight: 800; }
.twins-location-hero-proof .twins-brand-stars { color: var(--twins-gold); }
```

- [ ] **Step 6: Run the focused contract and verify it passes**

Run the Step 2 command.

Expected: all location contract tests PASS.

- [ ] **Step 7: Commit the hero unit without staging unrelated content**

```bash
git add website/twins-brand-experience/templates/editorial.php website/twins-brand-experience/assets/css/twins-brand.css website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
git diff --cached --check
git commit -m "feat: build cinematic location hero"
```

Expected: the commit contains exactly the three listed files; `config/page-content.php` remains unstaged.

---

### Task 2: Connected Complete-Service Pathway

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`
- Modify: `website/twins-brand-experience/templates/editorial.php:217-255`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css:1111-1311`

**Interfaces:**
- Consumes: `$locationServiceCards`, `twins_brand_door_art()`, and `$experience->route()`.
- Produces: `.twins-location-service-pathway`, `.twins-location-service-node`, `.twins-location-service-index`, and `.twins-location-service-link`; browser and layout tests use these names.

- [ ] **Step 1: Write the service-pathway contract**

Replace `location service navigation has one repair destination and three explained cards` with:

```js
test('location services form one connected complete-system pathway', () => {
  assert.match(template, /twins-location-service-pathway/);
  assert.match(template, /twins-location-service-node/);
  assert.match(template, /twins-location-service-index/);
  assert.match(template, /twins-location-service-link/);
  assert.equal((template.match(/\['Garage Door Repair', 'repair'\]/g) || []).length, 1);
  assert.match(template, /Garage door opener service/);
  assert.match(template, /Garage door installation/);
  assert.match(css, /\.twins-location-service-pathway::before/);
  assert.doesNotMatch(template, /twins-location-service-card/,
    'services must not return to three generic detached cards');
});
```

Replace service-card selectors in the premium-geometry test with:

```js
assert.match(css, /\.twins-location-service-node\s*\{[^}]*border-top:\s*1px solid/);
assert.doesNotMatch(css, /\.twins-location-service-node\s*\{[^}]*box-shadow:/);
```

- [ ] **Step 2: Run the contract and verify it fails on the old cards**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: FAIL because `.twins-location-service-card` still exists and pathway classes do not.

- [ ] **Step 3: Recompose the services as a connected sequence**

Keep the `.twins-location-system` section and replace only `.twins-location-services` with:

```php
<section class="twins-location-services" aria-labelledby="twins-location-services-title" data-location-reveal>
  <div class="twins-location-section-heading">
    <div>
      <span class="twins-brand-kicker">Complete garage door service</span>
      <h2 id="twins-location-services-title">Find the failure. Restore the whole system.</h2>
    </div>
    <p>Twins checks how the door, counterbalance hardware, opener, controls, and safety equipment work together before recommending the next step.</p>
  </div>
  <div class="twins-location-service-pathway">
    <?php foreach ($locationServiceCards as $index => $serviceCard): ?>
      <article class="twins-location-service-node">
        <span class="twins-location-service-index" aria-hidden="true">0<?= $index + 1 ?></span>
        <?= twins_brand_door_art(
            $serviceCard['art'],
            'twins-location-service-art',
            'location-service-' . $serviceCard['art']
        ) ?>
        <h3><?= htmlspecialchars($serviceCard['title'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= htmlspecialchars($serviceCard['description'], ENT_QUOTES, 'UTF-8') ?></p>
        <ul>
          <?php foreach ($serviceCard['items'] as $serviceItem): ?>
            <li><?= htmlspecialchars($serviceItem, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
        <a class="twins-location-service-link" href="<?= htmlspecialchars($experience->route($serviceCard['route'], $locationNavMarketKey), ENT_QUOTES, 'UTF-8') ?>">Explore <?= htmlspecialchars($serviceCard['title'], ENT_QUOTES, 'UTF-8') ?></a>
      </article>
    <?php endforeach; ?>
  </div>
</section>
```

- [ ] **Step 4: Replace the detached card grid with the connected pathway CSS**

Replace `.twins-location-service-grid` through its link rule with:

```css
.twins-location-services { color: var(--twins-white); background: #061831; }
.twins-location-services .twins-location-section-heading h2 { color: var(--twins-white); }
.twins-location-services .twins-location-section-heading > p { color: #cbd9e8; }
.twins-location-service-pathway {
  position: relative;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: clamp(24px, 4vw, 56px);
}
.twins-location-service-pathway::before {
  content: '';
  position: absolute;
  top: 35px;
  right: 8%;
  left: 8%;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255,200,61,.75) 15% 85%, transparent);
}
.twins-location-service-node {
  position: relative;
  z-index: 1;
  display: flex;
  min-width: 0;
  flex-direction: column;
  padding: 28px 0 0;
  color: #eaf2fb;
  border-top: 1px solid rgba(177,204,233,.27);
}
.twins-location-service-index {
  display: inline-grid;
  place-items: center;
  width: 70px;
  height: 70px;
  margin: -64px 0 28px;
  color: var(--twins-navy-950);
  background: var(--twins-gold);
  border: 8px solid #061831;
  border-radius: 50%;
  font-family: 'Lilita One', Impact, sans-serif;
  font-size: 1.3rem;
}
.twins-location-service-art { width: 92px; height: 66px; margin: 0 0 22px; padding: 8px; background: rgba(255,200,61,.9); border-radius: 8px; }
.twins-location-service-node h3 { margin: 0 0 14px; color: var(--twins-white); font-size: clamp(1.7rem, 2.3vw, 2.35rem); text-transform: uppercase; }
.twins-location-service-node p { margin: 0; color: #cbd9e8; }
.twins-location-service-node ul { display: grid; gap: 9px; margin: 24px 0 28px; padding: 0; list-style: none; }
.twins-location-service-node li { position: relative; padding-left: 24px; color: #eef5fb; font-weight: 800; }
.twins-location-service-node li::before { content: '—'; position: absolute; left: 0; color: var(--twins-gold); }
body.twins-brand-experience .twins-location-service-link { display: inline-flex; align-items: center; min-height: 44px; margin-top: auto; color: var(--twins-gold); font-weight: 1000; text-underline-offset: 4px; }
```

- [ ] **Step 5: Update component touch-target selector without weakening the rule**

In `tests/contracts/components.test.cjs`, replace `.twins-location-service-card a` with `.twins-location-service-link` in the existing 44 px assertion. Do not alter the expected `display`, `align-items`, or `min-height` values.

- [ ] **Step 6: Run focused contracts and verify they pass**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs tests/contracts/components.test.cjs
```

Expected: all tests PASS.

- [ ] **Step 7: Commit the pathway unit**

```bash
git add website/twins-brand-experience/templates/editorial.php website/twins-brand-experience/assets/css/twins-brand.css website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs website/twins-brand-experience/tests/contracts/components.test.cjs
git diff --cached --check
git commit -m "feat: connect location service pathway"
```

---

### Task 3: Cinematic/Warm Section Rhythm and Restrained Mascots

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`
- Modify: `website/twins-brand-experience/templates/editorial.php:257-358,414-438`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css:1111-1515`
- Modify: `website/twins-brand-experience/components/footer.php:15-18,36,50-52`
- Modify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php:443-461`

**Interfaces:**
- Consumes: existing `.twins-location-guidance`, `.twins-location-process`, `.twins-location-branch`, `.twins-location-nearby`, `.twins-location-faq`, `.twins-location-final-cta`, and approved twin placements.
- Produces: one warm homeowner-guidance plane, one connected process strip, one dark local-trust plane, a quiet FAQ, and one cinematic closing stage; adds `data-location-reveal` hooks consumed in Task 4.

- [ ] **Step 1: Add section-rhythm and conversion contracts**

Append:

```js
test('supporting sections alternate cinematic and warm planes without generic card grids', () => {
  assert.match(template, /class="twins-location-guidance"[^>]*data-location-reveal/);
  assert.match(template, /class="twins-location-process"[^>]*data-location-reveal/);
  assert.match(template, /class="twins-location-branch"[^>]*data-location-reveal/);
  assert.match(template, /class="twins-brand-faq twins-location-faq"[^>]*data-location-reveal/);
  assert.match(template, /class="twins-brand-final-cta twins-location-final-cta"[^>]*data-location-reveal/);
  assert.match(css, /\.twins-location-guidance\s*\{[^}]*background:\s*#f4ead6/);
  assert.match(css, /\.twins-location-process-list::before/);
  assert.match(css, /\.twins-location-branch\s*\{[^}]*background:/);
  assert.match(css, /\.twins-location-faq\s*\{[^}]*background:\s*var\(--twins-white\)/);
});

test('quote is primary copy and unverified urgency claims stay absent', () => {
  assert.match(template, />Get a Free Quote<\/a>/);
  assert.match(template, /if \(\$isLocation\):[\s\S]*twins-brand-cta--quote[\s\S]*twins-brand-cta--call[\s\S]*else:/,
    'location final CTA must render quote before call');
  assert.match(footer, /\$isLocationFooter \? 'Get a Free Quote' : 'Request a Quote'/);
  assert.match(footer, /\$isLocationFooter \? 'Call Now' : 'Call Twins'/);
  assert.doesNotMatch(template.toLowerCase(), /same[- ]day|within \d+|guaranteed response|recently opened/);
});
```

- [ ] **Step 2: Run the contract and verify it fails on missing reveal hooks and old section geometry**

Run the location contract command from Task 1.

Expected: FAIL for missing `data-location-reveal`, missing process connector, and old guidance background.

- [ ] **Step 3: Add reveal hooks and update location conversion labels**

Add `data-location-reveal` to the opening tags of the system, guidance, process, branch, nearby, FAQ, and location final CTA sections. Keep their existing `aria-labelledby` attributes.

Change location-only visible quote labels from `Request a Quote` to `Get a Free Quote` in the hero, branch, and final CTA. Do not change `$quote['href']` or the header component.

Make the final location CTA quote-first without changing non-location ordering:

```php
<div class="twins-brand-final-actions">
  <?php if ($isLocation): ?>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Get a Free Quote</a>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
  <?php else: ?>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= $isArticle ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : 'Twins' ?></a>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  <?php endif; ?>
</div>
```

The final CTA opening tag must resolve to:

```php
<section class="twins-brand-final-cta<?= $isLocation ? ' twins-location-final-cta' : '' ?>" aria-labelledby="twins-brand-editorial-final-title"<?= $isLocation ? ' data-location-reveal' : '' ?>>
```

In `components/footer.php`, add the location-path check after `$footerAddress` and use the derived labels for the footer quote and sticky mobile actions:

```php
$footerPath = isset($context['path']) && is_string($context['path']) ? $context['path'] : '';
$isLocationFooter = preg_match('~^/(?:wi|il)/location/[a-z][a-z0-9-]{0,39}/$~D', $footerPath) === 1;
$footerQuoteLabel = $isLocationFooter ? 'Get a Free Quote' : 'Request a Quote';
$mobileCallLabel = $isLocationFooter ? 'Call Now' : 'Call Twins';
```

Render the labels with escaping while preserving the existing hrefs:

```php
<a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($footerQuoteLabel, ENT_QUOTES, 'UTF-8') ?></a>
```

```php
<div class="twins-brand-mobile-actions" aria-label="Quick actions">
  <a href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mobileCallLabel, ENT_QUOTES, 'UTF-8') ?></a>
  <a href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($footerQuoteLabel, ENT_QUOTES, 'UTF-8') ?></a>
</div>
```

In the renderer harness, assert that the main footer still contains `Call Twins` and `Request a Quote`, while `$rockfordFooter` contains `Call Now` and `Get a Free Quote`. This makes the wording change location-only.

- [ ] **Step 4: Restyle the approved supporting sections as alternating planes**

Preserve the existing low-contrast door-panel pseudo-elements, then apply these exact structural rules in the location block:

```css
.twins-location-guidance { background: #f4ead6; }
.twins-location-warning-card {
  color: var(--twins-white);
  background: linear-gradient(145deg, rgba(5,24,52,.98), rgba(10,45,86,.98));
  border: 1px solid rgba(255,200,61,.55);
  border-radius: 14px;
  box-shadow: 0 22px 70px rgba(7,29,59,.16);
}
.twins-location-process { color: var(--twins-white); background: #061831; }
.twins-location-process .twins-location-section-heading h2 { color: var(--twins-white); }
.twins-location-process .twins-location-section-heading > p { color: #cbd9e8; }
.twins-location-process-list {
  position: relative;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0;
  margin: 0;
  padding: 0;
  list-style: none;
}
.twins-location-process-list::before {
  content: '';
  position: absolute;
  top: 28px;
  right: 12%;
  left: 12%;
  height: 1px;
  background: rgba(255,200,61,.6);
}
.twins-location-process-list li { position: relative; min-height: 0; padding: 0 clamp(22px, 3vw, 42px) 0 0; background: transparent; border: 0; }
.twins-location-process-list span { position: relative; z-index: 1; display: inline-grid; place-items: center; width: 56px; height: 56px; margin-bottom: 26px; color: var(--twins-navy-950); background: var(--twins-gold); border: 7px solid #061831; border-radius: 50%; font-family: 'Lilita One', Impact, sans-serif; font-size: 1.25rem; }
.twins-location-process-list h3 { margin: 0 0 10px; color: var(--twins-white); font-size: 1.55rem; text-transform: uppercase; }
.twins-location-process-list p { margin: 0; color: #cbd9e8; }
.twins-location-branch {
  background:
    radial-gradient(circle at 82% 26%, rgba(255,200,61,.12), transparent 25%),
    linear-gradient(135deg, #031126, #092b55);
}
.twins-location-branch aside {
  color: var(--twins-white);
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(181,209,237,.28);
  border-radius: 14px;
  backdrop-filter: blur(16px);
}
.twins-location-nearby { background: var(--twins-cream); }
.twins-location-faq { background: var(--twins-white); }
.twins-location-faq details { border-right: 0; border-left: 0; border-radius: 0; box-shadow: none; }
.twins-location-final-cta {
  background:
    radial-gradient(circle at 78% 38%, rgba(47,112,181,.32), transparent 26%),
    repeating-linear-gradient(0deg, transparent 0 84px, rgba(177,204,233,.09) 85px 86px),
    #031126;
}
```

Remove the old white-card process styles and old white branch-aside styles so they cannot win by source order.

- [ ] **Step 5: Preserve the three approved mascot placements and mobile clearance**

Keep only `hero`, `guidance`, and `final-right`. Keep all mascot selectors scoped below `.twins-location-page`. Set:

```css
.twins-location-page .twins-location-twin { pointer-events: none; animation: twins-location-float 5.8s ease-in-out infinite; }
.twins-location-page .twins-location-twin--guidance { width: clamp(88px, 7vw, 116px); }
.twins-location-page .twins-location-twin--final-right { width: clamp(92px, 8vw, 124px); }
@keyframes twins-location-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
```

At `max-width: 480px`, hide the hero twin and keep 210 px bottom clearance only for guidance and final CTA. Do not add a system or second final-CTA twin.

- [ ] **Step 6: Run location and renderer contracts**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs tests/php-harnesses.test.cjs
```

Expected: all contract and PHP harness tests PASS. If the renderer harness fails only because it asserts the old quote label, update that assertion to `Get a Free Quote` while retaining the exact href checks.

- [ ] **Step 7: Commit the supporting-page unit**

```bash
git add website/twins-brand-experience/templates/editorial.php website/twins-brand-experience/assets/css/twins-brand.css website/twins-brand-experience/components/footer.php website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs website/twins-brand-experience/tests/php/renderer-contract-harness.php
git diff --cached --check
git commit -m "feat: shape cinematic location page rhythm"
```

If the renderer harness did not change, omit it from `git add`.

---

### Task 4: Fail-Open Reveal Motion and Reduced-Motion Safety

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`
- Modify: `website/twins-brand-experience/assets/js/twins-brand.js:316-320`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css` within the location block and existing reduced-motion query

**Interfaces:**
- Consumes: `[data-location-reveal]` hooks from Tasks 1 and 3 and `matchMedia('(prefers-reduced-motion: reduce)')`.
- Produces: root class `.twins-location-motion-ready` and element state `[data-location-visible="true"]`. Without JavaScript, content stays visible.

- [ ] **Step 1: Write the fail-open motion contract**

At the top of the contract file, load JavaScript:

```js
const script = fs.readFileSync(path.join(root, 'assets/js/twins-brand.js'), 'utf8');
```

Append:

```js
test('location reveals are progressive enhancement and reduced motion is static', () => {
  assert.match(script, /function initLocationReveals\(root, reducedMotion\)/);
  assert.match(script, /IntersectionObserver/);
  assert.match(script, /twins-location-motion-ready/);
  assert.match(script, /data-location-visible/);
  assert.match(script, /if \(reducedMotion\.matches\)/);
  assert.match(css, /\.twins-location-motion-ready \[data-location-reveal\]/);
  assert.doesNotMatch(css, /(^|\n)\[data-location-reveal\]\s*\{[^}]*opacity:\s*0/,
    'content must not disappear when JavaScript is unavailable');
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)[\s\S]*\[data-location-reveal\][^{]*\{[^}]*opacity:\s*1 !important/);
});
```

- [ ] **Step 2: Run the contract and verify the motion assertions fail**

Run the Task 1 contract command.

Expected: FAIL because `initLocationReveals` and motion-ready styles do not exist.

- [ ] **Step 3: Add the reveal controller before `start()`**

Insert after `trapTab()`:

```js
function initLocationReveals(root, reducedMotion) {
  const items = [...root.querySelectorAll('[data-location-reveal]')];
  if (!items.length) return;
  if (reducedMotion.matches || !('IntersectionObserver' in window)) {
    items.forEach(item => item.dataset.locationVisible = 'true');
    return;
  }

  document.documentElement.classList.add('twins-location-motion-ready');
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.dataset.locationVisible = 'true';
      observer.unobserve(entry.target);
    });
  }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });
  items.forEach(item => observer.observe(item));
}
```

Inside `start()`, immediately after the existing `const reducedMotion = ...` line, call:

```js
initLocationReveals(document, reducedMotion);
```

- [ ] **Step 4: Add reveal CSS that activates only after JavaScript opts in**

Add:

```css
.twins-location-motion-ready [data-location-reveal] { opacity: 0; transform: translateY(24px); transition: opacity .7s ease, transform .7s ease; }
.twins-location-motion-ready [data-location-reveal][data-location-visible="true"] { opacity: 1; transform: translateY(0); }
```

In the existing reduced-motion query, include:

```css
.twins-location-page [data-location-reveal] { opacity: 1 !important; transform: none !important; transition: none !important; }
.twins-location-page .twins-location-twin,
.twins-location-page .twins-brand-door-art--door-open .twins-da-curtain { animation: none !important; transform: none !important; }
```

- [ ] **Step 5: Run focused script and location contracts**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs tests/contracts/styles-and-script.test.cjs
```

Expected: all tests PASS.

- [ ] **Step 6: Commit the motion unit**

```bash
git add website/twins-brand-experience/assets/js/twins-brand.js website/twins-brand-experience/assets/css/twins-brand.css website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
git diff --cached --check
git commit -m "feat: add restrained location reveal motion"
```

---

### Task 5: Responsive Cinematic Layout and Synced Browser Fixture

**Files:**
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css` location media queries
- Modify: `website/twins-brand-experience/tests/browser/fixtures/location-modern.html`
- Modify: `website/twins-brand-experience/tests/browser/location-modern.spec.cjs`

**Interfaces:**
- Consumes: exact markup and class names from Tasks 1-4.
- Produces: desktop integrated composition, mobile source order of copy → photo → proof, responsive service/process sequences, and a fixture that exercises production JavaScript.

- [ ] **Step 1: Update browser selectors and add visual-system assertions before changing the fixture**

Change `.twins-location-service-card a` in `visibleTargetAudit()` to `.twins-location-service-link`.

Replace card selectors in `layoutSelectors` with:

```js
'.twins-location-service-pathway',
'.twins-location-service-node',
'.twins-location-service-link',
```

Add after the existing header assertions:

```js
await expect(page.locator('.twins-location-hero-stage')).toHaveCount(1);
await expect(page.locator('.twins-location-title-accent')).toContainText('in Rockford');
await expect(page.locator('.twins-location-hero-proof [role="listitem"]')).toHaveCount(3);
await expect(page.locator('.twins-location-service-node')).toHaveCount(3);
await expect(page.locator('.twins-location-service-card')).toHaveCount(0);
await expect(page.locator('.twins-location-hero .twins-brand-cta--quote')).toHaveText('Get a Free Quote');
await expect(page.locator('.twins-location-final-cta .twins-brand-final-actions > a').first()).toHaveClass(/twins-brand-cta--quote/);
await expect(page.locator('.twins-location-final-cta .twins-brand-final-actions > a').last()).toHaveClass(/twins-brand-cta--call/);
```

Remove the old browser assertions that require the final CTA call action to come first.

After hierarchy checks, add:

```js
const visualSystem = await page.evaluate(() => {
  const hero = getComputedStyle(document.querySelector('.twins-location-hero-stage'));
  const proof = getComputedStyle(document.querySelector('.twins-location-hero-proof'));
  const warm = getComputedStyle(document.querySelector('.twins-location-guidance'));
  const dark = getComputedStyle(document.querySelector('.twins-location-process'));
  return {
    heroRadius: Number.parseFloat(hero.borderRadius),
    proofBackdrop: proof.backdropFilter || proof.webkitBackdropFilter,
    warmBackground: warm.backgroundColor,
    darkBackground: dark.backgroundColor,
  };
});
expect(visualSystem.heroRadius).toBeGreaterThanOrEqual(18);
expect(visualSystem.proofBackdrop).toContain('blur');
expect(luminance(visualSystem.warmBackground)).toBeGreaterThan(luminance(visualSystem.darkBackground));
```

- [ ] **Step 2: Run the browser test and verify the old fixture fails**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node node_modules/playwright/cli.js test tests/browser/location-modern.spec.cjs
```

Expected: FAIL on missing cinematic hero and service-pathway selectors.

- [ ] **Step 3: Synchronize the fixture with the rendered markup**

Mirror the final location-only markup from `templates/editorial.php` in `tests/browser/fixtures/location-modern.html` and convert the fixture from Madison to Rockford:

- set the document title, kicker, H1 accent, guidance heading, branch heading, FAQ heading, and final CTA kicker to Rockford;
- replace every Madison phone occurrence with the verified Illinois market values `(815) 800-2025` and `tel:+18158002025`;
- replace the branch/footer address with `5758 Elaine Dr Ste 110, Rockford, IL 61108`;
- use the existing Rockford intro and local notes from `config/location-content.php`; do not invent any fixture-only market claim;
- keep route structure, footer groups, door-art SVG content, and three approved mascot elements unchanged;
- move proof items inside `.twins-location-hero-stage` as `.twins-location-hero-proof` with list roles;
- use the two-line title with `.twins-location-title-accent`;
- replace all three service cards with `.twins-location-service-node` pathway articles;
- add the same `data-location-reveal` attributes as the template;
- change only location-content quote labels to `Get a Free Quote`;
- mirror the quote-first, call-second location final-action order;
- replace the fixture's inline drawer script with:

```html
<script src="/assets/js/twins-brand.js"></script>
```

Set the fixed `.twins-brand-mobile-actions` labels to `Call Now` and `Get a Free Quote`, update its phone href to the verified Rockford phone, and preserve the production quote href behavior.

In `location-page-overhaul-contract.test.cjs`, rename `full location fixture retains exact generated door curtain classes and the WI footer catalog` to `Rockford location fixture retains exact generated door curtain classes and the shared footer catalog`. Keep the footer group/count assertions and add:

```js
assert.match(fixture, /Rockford/);
assert.match(fixture, /tel:\+18158002025/);
assert.match(fixture, /5758 Elaine Dr Ste 110, Rockford, IL 61108/);
assert.doesNotMatch(fixture, /Madison, Wisconsin|tel:\+16084202377/);
```

Change the browser final-CTA kicker assertion from `Madison` to `Rockford`.

- [ ] **Step 4: Implement tablet and mobile composition rules**

Replace obsolete location media-query rules with these layout decisions:

```css
@media (max-width: 1024px) {
  .twins-location-hero { min-height: 0; }
  .twins-location-hero-stage { display: grid; min-height: 0; }
  .twins-location-hero-copy { width: auto; padding: 62px 40px 34px; }
  .twins-location-hero-media { position: relative; inset: auto; height: clamp(330px, 50vw, 520px); grid-row: 2; }
  .twins-location-hero-media::after { background: linear-gradient(0deg, rgba(2,13,32,.75), transparent 55%); }
  .twins-location-hero-proof { position: relative; inset: auto; grid-row: 3; margin: -48px 20px 20px; }
  .twins-location-orbit--one { right: -260px; top: 220px; }
  .twins-location-orbit--two { display: none; }
  .twins-location-service-pathway { grid-template-columns: 1fr; gap: 34px; }
  .twins-location-service-pathway::before { top: 0; bottom: 0; left: 34px; width: 1px; height: auto; }
  .twins-location-service-node { padding: 0 0 26px 94px; }
  .twins-location-service-index { position: absolute; top: 0; left: 0; margin: 0; }
  .twins-location-guidance,
  .twins-location-branch { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
  .twins-location-hero { padding: 16px 14px 34px; }
  .twins-location-hero-stage { border-radius: 18px; }
  .twins-location-hero-copy { padding: 44px 22px 28px; }
  .twins-location-hero h1 { font-size: clamp(2.85rem, 13vw, 4.8rem); }
  .twins-location-hero-media { height: clamp(270px, 78vw, 420px); }
  .twins-location-hero-proof { grid-template-columns: 1fr; margin: -26px 12px 12px; }
  .twins-location-hero-proof > div { min-height: 78px; border-top: 1px solid rgba(184,210,237,.2); border-left: 0; }
  .twins-location-hero-proof > div:first-child { border-top: 0; }
  .twins-location-services,
  .twins-location-guidance,
  .twins-location-process,
  .twins-location-branch,
  .twins-location-nearby,
  .twins-location-faq { padding: 54px 20px; }
  .twins-location-process-list { grid-template-columns: 1fr; gap: 30px; }
  .twins-location-process-list::before { top: 28px; bottom: 28px; left: 27px; width: 1px; height: auto; }
  .twins-location-process-list li { padding: 0 0 0 82px; }
  .twins-location-process-list span { position: absolute; top: 0; left: 0; }
}

@media (max-width: 480px) {
  .twins-location-hero-copy { padding: 34px 18px 24px; }
  .twins-location-hero h1 { font-size: clamp(2.55rem, 13vw, 3.45rem); line-height: .88; }
  .twins-location-hero-copy > p { font-size: 1rem; line-height: 1.5; }
  .twins-location-actions .twins-brand-cta { flex: 1 1 100%; justify-content: center; }
  .twins-location-hero-media { height: 260px; }
  .twins-location-page .twins-location-twin--hero { display: none; }
  .twins-location-nearby-grid { grid-template-columns: 1fr; }
  .twins-location-page .twins-location-guidance,
  .twins-location-page .twins-location-final-cta { padding-right: 20px; padding-bottom: 210px; }
}
```

Retain the existing 150 px and 120 px door-texture tokens at 768 and 480 px. Remove any earlier conflicting mobile hero/grid/card rules.

- [ ] **Step 5: Update mascot and mobile-photo expectations**

Keep the existing visibility map:

```js
const expectedVisibility = {
  'twins-location-twin--hero': viewport.width >= 481,
  'twins-location-twin--guidance': true,
  'twins-location-twin--final-right': true,
};
```

Update the 390 px mobile hero check to assert the photo is at least 240 px tall and appears after copy but before proof:

```js
if (viewport.width === 390) {
  const mobileHero = await page.locator('.twins-location-hero-stage').evaluate(stage => {
    const copy = stage.querySelector('.twins-location-hero-copy').getBoundingClientRect();
    const photo = stage.querySelector('.twins-location-hero-media').getBoundingClientRect();
    const proof = stage.querySelector('.twins-location-hero-proof').getBoundingClientRect();
    return { copyBottom: copy.bottom, photoTop: photo.top, photoHeight: photo.height, proofTop: proof.top };
  });
  expect(mobileHero.photoTop).toBeGreaterThanOrEqual(mobileHero.copyBottom - 1);
  expect(mobileHero.photoHeight).toBeGreaterThanOrEqual(240);
  expect(mobileHero.proofTop).toBeGreaterThan(mobileHero.photoTop);
}
```

- [ ] **Step 6: Run browser checks at all required widths**

Run the Step 2 command.

Expected: 7 Playwright tests PASS: six viewport cases plus reduced motion. No clipped audited element, no horizontal overflow, no mascot/text overlap, and every visible action is at least 44×44 px.

- [ ] **Step 7: Commit responsive code, fixture, and browser checks**

```bash
git add website/twins-brand-experience/assets/css/twins-brand.css website/twins-brand-experience/tests/browser/fixtures/location-modern.html website/twins-brand-experience/tests/browser/location-modern.spec.cjs
git diff --cached --check
git commit -m "test: lock responsive cinematic location layout"
```

---

### Task 6: Rendered-Page Regression and Full Repository Verification

**Files:**
- Verify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php`
- Verify: `website/twins-brand-experience/tests/contracts/*.test.cjs`
- Verify: `website/twins-brand-experience/tests/browser/*.spec.cjs`
- Generated only if the existing build requires it: `website/twins-brand-experience/dist/**`

**Interfaces:**
- Consumes: completed template, CSS, JavaScript, fixture, and tests.
- Produces: a green repository-level verification record with no unrelated file staged and no deployment action.

- [ ] **Step 1: Run the rendered-location harness**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
```

Expected: PASS for all registered location pages, including Rockford; route hrefs, verified phone/address data, three mascot pairs, and final CTA semantics remain intact.

- [ ] **Step 2: Run all contract tests**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/*.test.cjs
```

Expected: all contract tests PASS with zero failures.

- [ ] **Step 3: Verify owned assets and generated packages**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-owned-images.mjs --check
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs --check
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/check-repository.mjs
```

Expected: every command exits 0. If package generation changes tracked `dist` files, inspect them, verify the changes contain only the completed location implementation, and commit them with:

```bash
git add website/twins-brand-experience/dist
git diff --cached --check
git commit -m "build: refresh location experience packages"
```

- [ ] **Step 4: Run the complete local browser suite**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node node_modules/playwright/cli.js test
```

Expected: all local Playwright tests PASS. Do not run `live-private-staging.spec.cjs` against staging as proof of the new design because staging intentionally remains on r29.

- [ ] **Step 5: Confirm forbidden language and deployment boundaries**

```bash
rg -ni "recently opened|newly opened|new to this market|same-day|guaranteed response" templates/editorial.php config/location-content.php tests/browser/fixtures/location-modern.html
git status --short
```

Expected: the phrase search prints no matches. Status may still show the pre-existing `config/page-content.php` edit, but no uncommitted implementation file and no staging release artifact.

---

### Task 7: Local Rockford Mockup and User Approval Checkpoint

**Files:**
- No source modification expected.
- Create local review images outside the repository under `/Users/daniel/Documents/Codex/2026-07-21/files-mentioned-by-the-user-you/`.

**Interfaces:**
- Consumes: verified browser fixture and the production CSS/JavaScript/assets.
- Produces: live local URL plus desktop and mobile review images; user approval is required before any deployment plan begins.

- [ ] **Step 1: Start the fixture server on the established local port**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tests/browser/fixture-server.mjs --port 41739
```

Expected: the process remains running and serves `http://127.0.0.1:41739/tests/browser/fixtures/location-modern.html`.

- [ ] **Step 2: Open the live local mockup in the in-app browser**

Use the `browser:control-in-app-browser` skill to navigate to:

```text
http://127.0.0.1:41739/tests/browser/fixtures/location-modern.html
```

Expected: the first viewport shows one integrated cinematic hero—not a left/right boxed split—with gold title accent, blended technician image, glass proof cluster, and quote-first action hierarchy.

- [ ] **Step 3: Capture desktop and mobile review images**

Capture 1440×1000 and 390×844 views to:

```text
/Users/daniel/Documents/Codex/2026-07-21/files-mentioned-by-the-user-you/cinematic-hybrid-location-desktop.png
/Users/daniel/Documents/Codex/2026-07-21/files-mentioned-by-the-user-you/cinematic-hybrid-location-mobile.png
```

Expected: both images show the same brand system; mobile uses copy → photo → proof order and the sticky actions do not cover content.

- [ ] **Step 4: Present the local URL and both images for explicit approval**

State clearly that this is a local mockup, staging is still r29, and no deployment has occurred. Ask the user to approve or identify changes.

- [ ] **Step 5: Stop at the approval gate**

Do not run `deploy:staging:capture`, `deploy:staging:release`, rotate a release number, or alter staging. Deployment is a separate follow-up only after explicit visual approval.
