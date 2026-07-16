# Staging Site Unification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver one consistent, full-width, accessible, SEO/AEO-ready Twins Garage Doors experience across the private SiteGround staging site without changing production or enabling real submissions or integrations.

**Architecture:** The staging-overhaul MU plugin remains the fail-closed WordPress boundary and request classifier, while the portable `twins-brand-experience` package becomes the single customer-facing chrome and component system. Route families are migrated incrementally into portable branded templates; campaign/legal preservation and the frozen in-memory builder remain explicitly bounded. Each behavior is introduced through a failing contract, PHP harness, or Playwright test before runtime code changes.

**Tech Stack:** WordPress MU plugins, PHP 8-compatible templates, Node.js 20+, Node test runner, Playwright 1.61.1, CSS, browser-native JavaScript, local frozen JSON assets.

## Global Constraints

- Work only on `codex/staging-site-safety` in `/Users/daniel/twins-dashboard/.worktrees/staging-site-safety`.
- Use ChatGPT Profile 1 and identify the staging owner as `CHATGPT_PROFILE_1`.
- Production may be read but must not be changed.
- Do not change DNS, publish production, send real leads or emails, enable production booking, or contact production integrations.
- Keep SiteGround staging private and `noindex`.
- Illinois remains private, production-disabled, and addressless.
- Illinois staging phone is `(815) 800-2025` with `tel:+18158002025`; do not claim forwarding is verified.
- Use only owned local assets and frozen manufacturer-reference assets.
- Do not hotlink external images.
- Preserve campaign isolation, exact legal content, staging safety headers, inert forms, and same-origin GET/HEAD-only behavior.
- Every behavior change must follow red-green-refactor.
- Do not weaken an existing safety test or remove a gate to make a test pass.
- Use content-derived asset versions; do not use a permanent literal version such as `1`.
- Deploy only after repository checks and complete tests pass.

---

### Task 1: Make the Portable Brand Runtime Own Shared Chrome Everywhere

**Files:**
- Create: `website/twins-brand-experience/tests/contracts/site-unification.test.cjs`
- Modify: `website/staging-safety/tests/staging-overhaul-renderers-harness.php`
- Modify: `website/staging-safety/tests/staging-overhaul-harnesses.test.cjs`
- Modify: `website/staging-safety/mu-plugins/twins-staging-overhaul/components.php`
- Modify: `website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php`

**Interfaces:**
- Consumes: `twins_overhaul_current_classification(): string`, `twins_overhaul_should_render_chrome(string): bool`, `twins_overhaul_brand_runtime(): Experience`.
- Produces: `twins_overhaul_uses_brand_chrome(string): bool` and `twins_overhaul_brand_asset_version(string): string`.
- Invariant: `campaign-preserve` keeps its isolated approved presentation; every other chrome-enabled classification uses `Experience::renderHeader()` and `Experience::renderFooter()`.

- [ ] **Step 1: Write the failing universal-chrome contract**

Add tests that assert the classification allowlist is no longer repeated in header, footer, body-class, and asset branches:

```js
test('all non-campaign chrome routes use the portable brand header and footer', () => {
  const components = source('website/staging-safety/mu-plugins/twins-staging-overhaul/components.php');
  assert.match(components, /function twins_overhaul_uses_brand_chrome/);
  assert.match(components, /classification\s*!==\s*['"]campaign-preserve['"]/);
  assert.doesNotMatch(components, /home-brand'.*team-brand'.*careers-brand'.*reviews-brand'.*contact-brand/s);
});

test('brand assets use a content-derived version', () => {
  const renderers = source('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  assert.match(renderers, /function twins_overhaul_brand_asset_version/);
  assert.doesNotMatch(renderers, /twins-brand\.css'[^;]*,\s*['"]1['"]/);
  assert.doesNotMatch(renderers, /twins-brand\.js'[^;]*,\s*['"]1['"]/);
});

test('ordinary brand routes do not enqueue the recovered global visual kit', () => {
  const renderers = source('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  assert.match(renderers, /campaign-preserve/);
  assert.match(renderers, /cost-madison|cost-milwaukee|builder/);
  assert.match(renderers, /wp_dequeue_style\(['"]twins-staging-twx-v2['"]\)/);
});
```

- [ ] **Step 2: Run the focused contracts and confirm RED**

Run:

```bash
node --test website/twins-brand-experience/tests/contracts/site-unification.test.cjs
```

Expected: FAIL because the helper functions do not exist and ordinary service/catalog routes still use the older chrome.

- [ ] **Step 3: Extend the PHP renderer harness with service and catalog chrome scenarios**

Add `service-brand-chrome` and `catalog-brand-chrome` scenarios. For each, render the header/footer and assert:

```php
twins_overhaul_renderer_assert(strpos($header, 'class="twins-brand-header"') !== false, 'portable brand header missing');
twins_overhaul_renderer_assert(strpos($header, 'twins-overhaul-header') === false, 'legacy public header survived');
twins_overhaul_renderer_assert(strpos($footer, 'class="twins-brand-footer"') !== false, 'portable brand footer missing');
```

Register the scenarios in `staging-overhaul-harnesses.test.cjs`.

- [ ] **Step 4: Run the PHP harness and confirm RED**

Run:

```bash
node --test website/staging-safety/tests/staging-overhaul-harnesses.test.cjs
```

Expected: FAIL in the new scenarios because service and catalog routes still render the legacy header/footer.

- [ ] **Step 5: Implement the single chrome decision**

In `components.php`, add:

```php
function twins_overhaul_uses_brand_chrome(string $classification): bool {
    return twins_overhaul_should_render_chrome($classification)
        && $classification !== 'campaign-preserve';
}
```

Use it in both shared header and footer renderers:

```php
if (twins_overhaul_uses_brand_chrome($classification)) {
    return twins_overhaul_brand_runtime()->renderHeader($context);
}
```

Campaign preservation remains the only non-brand public-chrome exception.

- [ ] **Step 6: Give every brand-chrome route the brand body class**

Replace the five-classification body allowlist with:

```php
if (twins_overhaul_uses_brand_chrome($classification)) {
    $classes[] = 'twins-brand-experience';
    $classes[] = 'twins-brand-route-' . sanitize_html_class($classification);
}
```

If the local harness does not define `sanitize_html_class`, use a fixed internal mapping rather than accepting caller-selected class text.

- [ ] **Step 7: Implement content-derived brand asset versions**

Add:

```php
function twins_overhaul_brand_asset_version(string $relativePath): string {
    $allowed = array('assets/css/twins-brand.css', 'assets/js/twins-brand.js');
    if (!in_array($relativePath, $allowed, true)) {
        twins_overhaul_refuse_route('brand asset path is outside the fixed allowlist.');
    }
    $root = dirname(__DIR__) . '/twins-brand-experience/';
    $path = $root . $relativePath;
    $stat = @lstat($path);
    if (!is_array($stat) || is_link($path) || !is_file($path)) {
        twins_overhaul_refuse_route('brand asset is not a bounded regular file.');
    }
    $size = @filesize($path);
    if (!is_int($size) || $size < 1 || $size > 2097152) {
        twins_overhaul_refuse_route('brand asset size is outside the fixed boundary.');
    }
    return substr(hash_file('sha256', $path), 0, 16);
}
```

Use the returned CSS and JS hashes in `wp_enqueue_style` and `wp_enqueue_script`.

- [ ] **Step 8: Isolate legacy family assets during migration**

- Keep `campaign-preserve` on its isolated approved legacy assets.
- Enqueue portable brand CSS and JavaScript for every non-campaign chrome route.
- Explicitly dequeue `twins-staging-twx-v2` before adding portable brand assets.
- Keep narrowly scoped temporary support-asset exceptions for `cost-madison`, `cost-milwaukee`, and `builder` only until Task 7 extracts those family styles and behavior.
- Do not enqueue the complete recovered global visual kit on home, service, location, trust, article, catalog, team, careers, reviews, or contact routes.

- [ ] **Step 9: Run focused and existing suites**

Run:

```bash
node --test website/twins-brand-experience/tests/contracts/site-unification.test.cjs
node --test website/staging-safety/tests/staging-overhaul-harnesses.test.cjs
```

Expected: PASS.

- [ ] **Step 10: Commit Task 1**

```bash
git add website/twins-brand-experience/tests/contracts/site-unification.test.cjs \
  website/staging-safety/tests/staging-overhaul-renderers-harness.php \
  website/staging-safety/tests/staging-overhaul-harnesses.test.cjs \
  website/staging-safety/mu-plugins/twins-staging-overhaul/components.php \
  website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php
git commit -m "feat(staging): unify shared brand chrome"
```

### Task 2: Reset the WordPress Host Shell and Enforce Component Contrast

**Files:**
- Create: `website/twins-brand-experience/tests/browser/fixtures/brand-host-shell.html`
- Create: `website/twins-brand-experience/tests/browser/site-unification.spec.cjs`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css`

**Interfaces:**
- Consumes: body classes from Task 1.
- Produces: `--twins-header-height` and `--twins-content-shell` CSS contracts.
- Invariant: viewport-wide section backgrounds with bounded inner content; no Astra/Elementor wrapper may constrain branded routes.

- [ ] **Step 1: Create a failing host-shell fixture**

Wrap a minimal branded header/main/footer inside realistic host elements:

```html
<body class="twins-overhaul-preview twins-brand-experience ast-single-post">
  <div id="page" class="site">
    <div id="content" class="site-content">
      <div class="ast-container">
        <div id="primary" class="content-area">
          <main id="main" class="site-main">
            <article class="ast-article-single">
              <div class="entry-content">
                <!-- branded header and full-bleed test sections -->
              </div>
            </article>
          </main>
        </div>
      </div>
    </div>
  </div>
</body>
```

- [ ] **Step 2: Write failing Playwright geometry and contrast tests**

Test 1440, 768, 390, and 320px widths:

```js
for (const width of [1440, 768, 390, 320]) {
  await page.setViewportSize({ width, height: 900 });
  await page.goto('/tests/browser/fixtures/brand-host-shell.html');
  const hero = await page.locator('.twins-brand-page-hero').boundingBox();
  expect(hero.x).toBeLessThanOrEqual(0.5);
  expect(hero.width).toBeGreaterThanOrEqual(width - 1);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
}
```

Also inject the known conflicting host rules:

```css
body.twins-overhaul-preview a { color: inherit; }
.entry-content h2 { color: #3a3a3a; }
```

Assert the Careers CTA, review kicker, header controls, and every visible button retain at least 4.5:1 normal-text contrast.

- [ ] **Step 3: Run the new browser test and confirm RED**

Run:

```bash
cd website/twins-brand-experience
npx playwright test tests/browser/site-unification.spec.cjs
```

Expected: FAIL because `.ast-container` and related wrappers remain constrained and some generic brand CTA/kicker states inherit host colors.

- [ ] **Step 4: Add the scoped host reset**

Add a single scoped reset:

```css
body.twins-brand-experience :where(
  #page, .site, #content, .site-content, .ast-container, .content-area,
  .site-main, .ast-article-single, .entry-content
) {
  width: 100%;
  max-width: none;
  margin: 0;
  padding: 0;
}

body.twins-brand-experience :where(.entry-header, .page-header, .ast-article-post > .entry-title) {
  display: none;
}
```

Do not apply these resets outside `body.twins-brand-experience`.

- [ ] **Step 5: Add explicit foreground contracts**

Add explicit colors for:

```css
body.twins-brand-experience .twins-brand-kicker { color: var(--twins-navy-800); }
body.twins-brand-experience .twins-brand-page-hero .twins-brand-kicker,
body.twins-brand-experience .twins-brand-reviews-collection .twins-brand-kicker,
body.twins-brand-experience .twins-brand-careers-process .twins-brand-kicker {
  color: var(--twins-gold);
}
body.twins-brand-experience a.twins-brand-cta { text-decoration: none; }
body.twins-brand-experience .twins-brand-careers-hero a.twins-brand-cta {
  color: var(--twins-navy-950);
  background: var(--twins-gold);
}
```

Add `--twins-header-height` to the header and update it at existing breakpoints.

- [ ] **Step 6: Run browser and contract suites**

Run:

```bash
cd website/twins-brand-experience
npx playwright test tests/browser/site-unification.spec.cjs tests/browser/interactions.spec.cjs
npm run test:contracts
```

Expected: PASS.

- [ ] **Step 7: Commit Task 2**

```bash
git add website/twins-brand-experience/tests/browser/fixtures/brand-host-shell.html \
  website/twins-brand-experience/tests/browser/site-unification.spec.cjs \
  website/twins-brand-experience/assets/css/twins-brand.css
git commit -m "fix(staging): normalize host layout and contrast"
```

### Task 3: Add the Interactive Market Selector and Illinois Phone

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/components.test.cjs`
- Modify: `website/twins-brand-experience/tests/contracts/templates.test.cjs`
- Modify: `website/twins-brand-experience/tests/browser/interactions.spec.cjs`
- Modify: `website/twins-brand-experience/components/header.php`
- Modify: `website/twins-brand-experience/templates/contact.php`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css`

**Interfaces:**
- Consumes: `MarketRegistry::all(string): array` and `Experience::route(string, string): string`.
- Produces: `.twins-brand-market-menu` using native `<details>` semantics.
- Invariant: the preview label never suppresses an approved phone number.

- [ ] **Step 1: Write failing component contracts**

Assert:

```js
assert.match(header, /<details class="twins-brand-market-menu"/);
assert.match(header, /<summary>Choose your service area<\/summary>/);
assert.match(header, /phoneDisplay/);
assert.doesNotMatch(header, /<span>Choose your service area<\/span>/);
assert.match(contact, /phoneDisplay/);
assert.doesNotMatch(contact, /if \(\$availableMarket\['preview'\] === true\)[\s\S]*Private staging preview[\s\S]*else/s);
```

- [ ] **Step 2: Run contracts and confirm RED**

Run:

```bash
node --test website/twins-brand-experience/tests/contracts/components.test.cjs \
  website/twins-brand-experience/tests/contracts/templates.test.cjs
```

Expected: FAIL because the utility label is a `<span>` and Illinois phone is hidden.

- [ ] **Step 3: Implement native market-selector markup**

Replace the utility `<span>` with:

```php
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
```

Use native details/summary so keyboard activation works without adding a second dialog implementation.

- [ ] **Step 4: Show Illinois phone in Contact**

Always render:

```php
<a href="<?= htmlspecialchars($availableMarket['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">
  <?= htmlspecialchars($availableMarket['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?>
</a>
```

Render “Private staging preview” as a separate `<small>` when `preview === true`.

- [ ] **Step 5: Add CSS and browser interaction checks**

Style the menu panel with explicit navy/gold/white states, 44px targets, visible focus, and responsive placement. In Playwright:

```js
const selector = page.locator('.twins-brand-market-menu');
await selector.locator('summary').click();
await expect(selector.getByText('(815) 800-2025')).toBeVisible();
await page.keyboard.press('Escape');
```

If native `<details>` does not close on Escape in the target browser, add a bounded key handler to the existing same-origin script and test it.

- [ ] **Step 6: Run tests**

Run:

```bash
cd website/twins-brand-experience
npm run test:contracts
npx playwright test tests/browser/interactions.spec.cjs
```

Expected: PASS.

- [ ] **Step 7: Commit Task 3**

```bash
git add website/twins-brand-experience/tests/contracts/components.test.cjs \
  website/twins-brand-experience/tests/contracts/templates.test.cjs \
  website/twins-brand-experience/tests/browser/interactions.spec.cjs \
  website/twins-brand-experience/components/header.php \
  website/twins-brand-experience/templates/contact.php \
  website/twins-brand-experience/assets/css/twins-brand.css
git commit -m "feat(staging): expose market selector and Illinois phone"
```

### Task 4: Replace the Unbounded Review Carousel

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/components.test.cjs`
- Modify: `website/twins-brand-experience/tests/contracts/styles-and-script.test.cjs`
- Modify: `website/twins-brand-experience/tests/browser/interactions.spec.cjs`
- Modify: `website/twins-brand-experience/components/review-slider.php`
- Modify: `website/twins-brand-experience/templates/reviews.php`
- Modify: `website/twins-brand-experience/assets/js/twins-brand.js`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css`

**Interfaces:**
- Homepage/regional mode: `data-review-mode="featured"` with at most nine records.
- Reviews-page mode: `.twins-brand-review-list` with all verified records and no autoplay.
- Slider status: `[data-review-page-status]` containing `current of total`.

- [ ] **Step 1: Write failing review contracts**

Assert the component:

```js
assert.match(html, /data-review-mode/);
assert.match(html, /data-review-page-status/);
assert.doesNotMatch(html, /twins-brand-review-dots/);
assert.match(js, /12000/);
assert.doesNotMatch(js, /setInterval\([^;]*7000/);
assert.match(js, /interaction.*permanent|manual/i);
```

Assert the Reviews template chooses list mode and contains no slider autoplay marker.

- [ ] **Step 2: Run contracts and confirm RED**

Run:

```bash
node --test website/twins-brand-experience/tests/contracts/components.test.cjs \
  website/twins-brand-experience/tests/contracts/styles-and-script.test.cjs
```

Expected: FAIL because the current component creates one dot per page and advances every seven seconds.

- [ ] **Step 3: Implement the two review modes**

In `review-slider.php`:

```php
$isReviewsPage = ($context['classification'] ?? '') === 'reviews-brand';
$records = $collection['records'];
if (!$isReviewsPage) $records = array_slice($records, 0, 9);
```

For the Reviews page, render a static list. For quotes longer than 42 words, render a concise excerpt and native `<details>` containing the complete verified quote.

For featured mode, render:

```html
<button type="button" class="twins-brand-review-control" data-review-prev>Previous</button>
<output class="twins-brand-review-status" data-review-page-status aria-live="polite">1 of 1</output>
<button type="button" class="twins-brand-review-control" data-review-next>Next</button>
```

- [ ] **Step 4: Implement bounded slider behavior**

Replace dot construction with:

```js
const status = slider.querySelector('[data-review-page-status]');
const paint = () => {
  track.style.transform = `translate3d(-${current * 100}%, 0, 0)`;
  status.textContent = `${current + 1} of ${pages}`;
  previous.disabled = pages <= 1;
  next.disabled = pages <= 1;
};
```

Use:

```js
let permanentlyPaused = false;
const manualGo = page => {
  permanentlyPaused = true;
  go(page);
};
setInterval(() => {
  if (!permanentlyPaused && !isPaused()) go(current + 1);
}, 12000);
```

All button, keyboard, and swipe navigation must call `manualGo`.

- [ ] **Step 5: Add failing-then-passing browser assertions**

Update the existing slider test:

```js
await expect(slider.locator('[data-review-page-status]')).toHaveText('1 of 5');
await expect(slider.locator('.twins-brand-review-dots')).toHaveCount(0);
await slider.getByRole('button', { name: 'Next reviews' }).click();
const afterManual = await track.evaluate(element => getComputedStyle(element).transform);
await page.waitForTimeout(12_500);
expect(await track.evaluate(element => getComputedStyle(element).transform)).toBe(afterManual);
```

Add a Reviews-page fixture/test proving there is no moving track or interval-dependent movement.

- [ ] **Step 6: Compact card styling**

- Remove the 310px minimum card height.
- Use readable non-italic body text.
- Separate Previous/Next as rectangular branded controls.
- Keep the status between controls.
- Ensure list cards do not stretch to the longest quote.

- [ ] **Step 7: Run tests**

Run:

```bash
cd website/twins-brand-experience
npm run test:contracts
npx playwright test tests/browser/interactions.spec.cjs tests/browser/site-unification.spec.cjs
```

Expected: PASS.

- [ ] **Step 8: Commit Task 4**

```bash
git add website/twins-brand-experience/tests/contracts/components.test.cjs \
  website/twins-brand-experience/tests/contracts/styles-and-script.test.cjs \
  website/twins-brand-experience/tests/browser/interactions.spec.cjs \
  website/twins-brand-experience/components/review-slider.php \
  website/twins-brand-experience/templates/reviews.php \
  website/twins-brand-experience/assets/js/twins-brand.js \
  website/twins-brand-experience/assets/css/twins-brand.css
git commit -m "fix(staging): bound the verified review experience"
```

### Task 5: Repair Careers Geometry, CTA Visibility, and Team Emphasis

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/templates.test.cjs`
- Modify: `website/twins-brand-experience/tests/browser/site-unification.spec.cjs`
- Modify: `website/twins-brand-experience/templates/careers.php`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css`

**Interfaces:**
- Consumes: `--twins-header-height` from Task 2.
- Produces: visible Careers CTA, non-overlapping subnavigation, and early owned team imagery.

- [ ] **Step 1: Write failing Careers browser assertions**

At 1440, 768, 390, and 320px:

```js
const header = await page.locator('.twins-brand-header').boundingBox();
const subnav = await page.locator('.twins-brand-page-nav').boundingBox();
expect(subnav.y).toBeGreaterThanOrEqual(header.y + header.height - 1);
await expect(page.locator('.twins-brand-careers-hero .twins-brand-cta')).toHaveText(/Preview the application|Start your application/);
expect((await computedContrast(page.locator('.twins-brand-careers-hero .twins-brand-cta'))).ratio).toBeGreaterThanOrEqual(4.5);
```

Assert the crew image intersects the first 1100px of the desktop document.

- [ ] **Step 2: Run and confirm RED**

Run:

```bash
cd website/twins-brand-experience
npx playwright test tests/browser/site-unification.spec.cjs --grep Careers
```

Expected: FAIL against the current hard-coded `top: 140px` and inherited CTA color.

- [ ] **Step 3: Bind navigation to the header contract**

Use:

```css
.twins-brand-page-nav {
  top: var(--twins-header-height);
}
```

At viewports where the combined sticky stack would cover meaningful content, make the page navigation non-sticky:

```css
@media (max-width: 768px) {
  .twins-brand-page-nav { position: relative; top: auto; }
}
```

- [ ] **Step 4: Make the CTA explicit and bring the image forward**

Use `twins-brand-cta--quote` on the Careers hero application link and keep the crew image in the initial hero grid. Reduce the desktop heading clamp enough that the image and CTA remain visible without cropping.

- [ ] **Step 5: Run tests and commit**

Run:

```bash
cd website/twins-brand-experience
npm run test:contracts
npx playwright test tests/browser/site-unification.spec.cjs
```

Then:

```bash
git add website/twins-brand-experience/tests/contracts/templates.test.cjs \
  website/twins-brand-experience/tests/browser/site-unification.spec.cjs \
  website/twins-brand-experience/templates/careers.php \
  website/twins-brand-experience/assets/css/twins-brand.css
git commit -m "fix(staging): stabilize the careers journey"
```

### Task 6: Replace Raw Service Output with an Answer-First Branded Template

**Files:**
- Create: `website/twins-brand-experience/config/page-content.php`
- Create: `website/twins-brand-experience/src/PageContentRegistry.php`
- Create: `website/twins-brand-experience/templates/service.php`
- Create: `website/twins-brand-experience/templates/editorial.php`
- Create: `website/twins-brand-experience/tests/contracts/page-content.test.cjs`
- Modify: `website/twins-brand-experience/bootstrap.php`
- Modify: `website/twins-brand-experience/src/Experience.php`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css`
- Modify: `website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php`
- Modify: `website/staging-safety/tests/staging-overhaul-renderers-harness.php`

**Interfaces:**
- `PageContentRegistry::resolve(string $path, string $title): array`
- Required record keys: `h1`, `directAnswer`, `needs`, `safety`, `process`, `options`, `prepare`, `faqs`, `links`.
- `Experience::renderService(array $context): string`
- `Experience::renderEditorial(array $context, string $content, string $kind): string`

- [ ] **Step 1: Write failing registry contracts**

Test:

```js
for (const route of [
  '/garage-door-repair/',
  '/garage-door-installation/',
  '/garage-door-spring-repair/',
  '/garage-door-opener-repair/',
  '/emergency-garage-services/',
]) {
  const record = registry.resolve(route, 'Fallback title');
  assert.equal(wordCount(record.directAnswer) >= 40, true);
  assert.equal(wordCount(record.directAnswer) <= 60, true);
  assert.equal(record.faqs.length >= 4 && record.faqs.length <= 6, true);
}
assert.doesNotMatch(JSON.stringify(records), /#1|replace (?:the )?spring yourself|DIY spring/i);
assert.match(records['/garage-door-spring-repair/'].safety, /dangerous tension|trained professional/i);
```

- [ ] **Step 2: Run and confirm RED**

Run:

```bash
node --test website/twins-brand-experience/tests/contracts/page-content.test.cjs
```

Expected: FAIL because no registry exists.

- [ ] **Step 3: Implement a strict fixed registry**

`PageContentRegistry` must:

- Accept only a normalized root-relative path and bounded title.
- Strip `/wi`, `/ky`, or `/il` market prefixes before service-record lookup.
- Reject unknown record shapes.
- Return a safe generic service record for known service classifications whose slug has no bespoke record.
- Never read caller-selected files or URLs.

The initial fixed records must cover the five routes in Step 1 and use only verified, nonnumeric service guidance.

- [ ] **Step 4: Implement the portable Service template**

Render:

```php
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-service-page">
  <section class="twins-brand-service-hero">...</section>
  <section class="twins-brand-direct-answer">...</section>
  <section class="twins-brand-service-needs">...</section>
  <section class="twins-brand-service-process">...</section>
  <section class="twins-brand-service-options">...</section>
  <section class="twins-brand-service-area">...</section>
  <section class="twins-brand-faq">...</section>
  <section class="twins-brand-final-cta">...</section>
</main>
```

Use the fixed regional phone and quote adapter. Do not render the original legacy body on service routes.

- [ ] **Step 5: Add a branded editorial fallback**

For location, trust, and article routes, keep inert verified source content but place it inside a branded editorial shell:

- One generated H1.
- Concise direct-answer introduction based on the fixed classification.
- Readable maximum line length.
- Explicit heading/link colors.
- Regional phone and quote CTA.
- No raw Elementor wrapper or active controls.

- [ ] **Step 6: Route through the portable runtime**

In `twins_overhaul_render_classified_content`:

```php
} elseif ($classification === 'service') {
    $rendered = twins_overhaul_brand_runtime()->renderService($context);
} elseif (in_array($classification, array('location', 'trust', 'article'), true)) {
    $rendered = twins_overhaul_brand_runtime()->renderEditorial(
        $context,
        twins_overhaul_prepare_family_content($content),
        $classification
    );
```

- [ ] **Step 7: Add PHP harness assertions**

For Spring Repair, assert:

```php
twins_overhaul_renderer_assert(substr_count($rendered, '<h1') === 1, 'service H1 count changed');
twins_overhaul_renderer_assert(substr_count($rendered, '<details') >= 4, 'service FAQ count is insufficient');
twins_overhaul_renderer_assert(stripos($rendered, 'dangerous tension') !== false, 'spring safety answer missing');
twins_overhaul_renderer_assert(stripos($rendered, 'replace it yourself') === false, 'unsafe DIY spring copy survived');
twins_overhaul_renderer_assert(strpos($rendered, 'data-twins-original-content') === false, 'raw service body survived');
```

- [ ] **Step 8: Run tests**

Run:

```bash
node --test website/twins-brand-experience/tests/contracts/page-content.test.cjs
node --test website/twins-brand-experience/tests/php-harnesses.test.cjs
node --test website/staging-safety/tests/staging-overhaul-harnesses.test.cjs
```

Expected: PASS.

- [ ] **Step 9: Commit Task 6**

```bash
git add website/twins-brand-experience/config/page-content.php \
  website/twins-brand-experience/src/PageContentRegistry.php \
  website/twins-brand-experience/templates/service.php \
  website/twins-brand-experience/templates/editorial.php \
  website/twins-brand-experience/tests/contracts/page-content.test.cjs \
  website/twins-brand-experience/bootstrap.php \
  website/twins-brand-experience/src/Experience.php \
  website/twins-brand-experience/assets/css/twins-brand.css \
  website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php \
  website/staging-safety/tests/staging-overhaul-renderers-harness.php
git commit -m "feat(staging): add answer-first service templates"
```

### Task 7: Replace Legacy Clopay Presentation and Polish the Frozen Builder

**Files:**
- Create: `website/twins-brand-experience/templates/catalog.php`
- Create: `website/twins-brand-experience/tests/contracts/catalog.test.cjs`
- Create: `website/twins-brand-experience/assets/css/twins-brand-families.css`
- Create: `website/twins-brand-experience/assets/js/twins-builder.js`
- Modify: `website/twins-brand-experience/src/Experience.php`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css`
- Modify: `website/twins-brand-experience/manifests/staging-runtime.json`
- Modify: `website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php`
- Modify: `website/staging-safety/mu-plugins/twins-staging-overhaul/templates/builder.php`
- Modify: `website/staging-safety/tests/staging-overhaul-builder-harness.php`
- Modify: `website/staging-safety/tests/staging-overhaul-renderers-harness.php`

**Interfaces:**
- `Experience::renderCatalog(array $context, array $catalogView): string`
- `twins_overhaul_catalog_view(array $context): array`
- Fixed featured mappings:
  - `/clopay-modern-steel/` → product `170`
  - `/clopay-gallery-steel/` → product `12`
  - `/clopay-classic-collection/` → product `13`

- [ ] **Step 1: Write failing catalog contracts**

Assert:

```js
assert.match(template, /twins-brand-catalog-hero/);
assert.match(template, /manufacturer reference/i);
assert.equal((template.match(/<h1/g) || []).length, 1);
assert.doesNotMatch(template, /iframe|https?:\/\//);
assert.match(template, /Design This Door/);
```

The renderer contract must reject the old `twins_overhaul_wrap_preserved_content` path for `catalog-preserve`.

- [ ] **Step 2: Run and confirm RED**

Run:

```bash
node --test website/twins-brand-experience/tests/contracts/catalog.test.cjs
node --test website/staging-safety/tests/staging-overhaul-harnesses.test.cjs
```

Expected: FAIL because catalog pages still preserve legacy markup.

- [ ] **Step 3: Build a bounded catalog view**

Use `twins_overhaul_builder_catalog()` as the only product source. Resolve the fixed product mapping from the proven request path. Return:

```php
array(
    'mode' => 'product',
    'product' => $product,
    'builderPath' => $region['base'] . ($region['key'] === 'ky' ? 'design-your-door/' : 'door-builder/'),
)
```

For the collection overview, return the fixed product order and featured records. Do not accept a caller-selected product ID.

- [ ] **Step 4: Render contrast-safe catalog pages**

The product template must use:

- A navy text panel or strong opaque scrim.
- White heading/body text.
- One H1.
- Product construction and selection facts from the frozen record.
- Local reference images only.
- “Design This Door” and “Request a Quote” actions.
- One intentional private-preview notice below the useful content.

- [ ] **Step 5: Polish the existing frozen builder**

Keep its in-memory catalog and same-origin safety. Change only presentation/copy needed to:

- Use the shared branded page shell.
- Avoid a large debug-style disabled notice.
- Keep “Manufacturer reference only.”
- Keep all steps keyboard/touch accessible.
- Use the correct regional phone.

- [ ] **Step 6: Extract only the required cost and builder family assets**

- Move the required cost/builder presentation rules into scoped `twins-brand-families.css`.
- Extract only builder interaction behavior into `twins-builder.js`.
- Do not carry over recovered menu, application, campaign, analytics, remote-network, or unrelated global behavior.
- Enqueue the family stylesheet only for cost, builder, and catalog routes.
- Enqueue builder JavaScript only for the builder route.
- Remove Task 1's temporary legacy CSS/JavaScript exceptions after the scoped assets are active.
- Add both files to the fixed runtime manifest and release package with content-derived versions.

- [ ] **Step 7: Add harness assertions**

Assert:

```php
twins_overhaul_renderer_assert(strpos($rendered, 'Clopay Gallery') !== false, 'Gallery title missing');
twins_overhaul_renderer_assert(substr_count($rendered, '<h1') === 1, 'catalog H1 count changed');
twins_overhaul_renderer_assert(strpos($rendered, 'data-twins-original-content') === false, 'legacy catalog body survived');
twins_overhaul_renderer_assert(strpos($rendered, 'https://') === false, 'remote catalog URL survived');
```

- [ ] **Step 8: Run tests and commit**

Run:

```bash
node --test website/twins-brand-experience/tests/contracts/catalog.test.cjs
node --test website/staging-safety/tests/staging-overhaul-harnesses.test.cjs
cd website/twins-brand-experience && npm run test:browser
```

Then:

```bash
git add website/twins-brand-experience/templates/catalog.php \
  website/twins-brand-experience/tests/contracts/catalog.test.cjs \
  website/twins-brand-experience/assets/css/twins-brand-families.css \
  website/twins-brand-experience/assets/js/twins-builder.js \
  website/twins-brand-experience/src/Experience.php \
  website/twins-brand-experience/assets/css/twins-brand.css \
  website/twins-brand-experience/manifests/staging-runtime.json \
  website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php \
  website/staging-safety/mu-plugins/twins-staging-overhaul/templates/builder.php \
  website/staging-safety/tests/staging-overhaul-builder-harness.php \
  website/staging-safety/tests/staging-overhaul-renderers-harness.php
git commit -m "feat(staging): rebuild Clopay catalog presentation"
```

### Task 8: Integrate Reviewed Claude Content Without Runtime Overlap

**Files:**
- Review only: Claude branch `claude/staging-content-aeo`
- Modify only when reviewed content is accepted: `website/twins-brand-experience/config/page-content.php`
- Create or update: `docs/handoff/2026-07-16-claude-content-integration.md`

**Interfaces:**
- Consumes: Claude’s non-runtime content drafts, citations, route matrix, and red tests.
- Produces: verified content improvements only; no blind cherry-pick of conflicting runtime files.

- [ ] **Step 1: Inspect Claude’s branch and changed-file list**

Run:

```bash
git log --oneline codex/staging-site-safety..claude/staging-content-aeo
git diff --name-status codex/staging-site-safety...claude/staging-content-aeo
```

Expected: only new documentation, new non-runtime content drafts, new tests, and a handoff.

- [ ] **Step 2: Reject ownership violations**

If Claude changed existing CSS, JavaScript, PHP runtime, deploy tools, SiteGround state, WordPress, or production files, do not cherry-pick that commit. Extract only reviewed non-conflicting files with `git show <commit>:<path>` and add them deliberately.

- [ ] **Step 3: Verify every factual claim**

For each candidate record:

- Confirm its repository or production-read citation.
- Remove unsupported numeric claims, guarantees, availability, addresses, benefits, and superlatives.
- Preserve Illinois private/noindex status.
- Keep Spring Repair safety language explicit.

- [ ] **Step 4: Integrate only approved content/tests**

Map verified drafts into `config/page-content.php` without changing the registry interface from Task 6. Run the focused registry and rendering tests.

- [ ] **Step 5: Document and commit the integration**

```bash
git add website/twins-brand-experience/config/page-content.php \
  docs/handoff/2026-07-16-claude-content-integration.md
git commit -m "content(staging): integrate verified AEO drafts"
```

If Claude has not completed, record that the runtime remains based on the verified initial records and continue to Task 9.

### Task 9: Complete Repository Verification, Private Deployment, and Visual Crawl

**Files:**
- Modify: `website/twins-brand-experience/tests/browser/live-private-staging.spec.cjs`
- Create or modify: `website/twins-brand-experience/tools/crawl-staging.mjs`
- Create: `docs/handoff/2026-07-16-staging-site-unification.md`

**Interfaces:**
- Consumes: all completed runtime tasks.
- Produces: verified private staging release and structured handoff.

- [ ] **Step 1: Expand live private-staging coverage**

Add exact routes:

```js
const routes = [
  '/',
  '/il/',
  '/reviews/',
  '/contact-us/',
  '/careers/',
  '/garage-door-spring-repair/',
  '/clopay-garage-doors/',
  '/clopay-gallery-steel/?product=12',
  '/door-builder/',
];
```

For every route assert:

- Status below 400.
- `X-Robots-Tag` contains `noindex`.
- Exactly one `.twins-brand-header` and one `.twins-brand-footer`.
- No `.twins-overhaul-header`.
- One visible H1.
- Viewport-wide first major section.
- No horizontal overflow.
- No non-GET/HEAD or cross-origin requests.

Add route-specific Illinois, Reviews, Careers, Spring, and Clopay assertions.

- [ ] **Step 2: Run the complete local suite**

Run:

```bash
node --test website/staging-safety/tests/*.test.cjs
cd website/twins-brand-experience
npm run test:all
```

Expected: all local tests and repository checks PASS with no warnings.

- [ ] **Step 3: Build the staging release package**

Run:

```bash
cd website/twins-brand-experience
npm run build:packages
npm run check:packages
npm run deploy:staging:dry-run
```

Expected: package and dry-run PASS with only the approved staging runtime files.

- [ ] **Step 4: Establish temporary SiteGround access**

- Create one temporary staging-only SSH key.
- Import only its public key into SiteGround.
- Confirm the SSH host fingerprint through approved SiteGround evidence.
- Do not reuse or expose a private key.

- [ ] **Step 5: Deploy once**

Capture the expected old staging release and deploy the tested package through the existing deployment tool. Do not retry automatically after a conflict or indeterminate result.

- [ ] **Step 6: Purge staging cache and run the authenticated live crawl**

`TWINS_STAGE_USER` and `TWINS_STAGE_PASSWORD` must already exist in the process environment. Never write them into a command, file, screenshot, test artifact, or log.

```bash
TWINS_STAGE_URL='https://danielj140.sg-host.com/' npm run verify:staging-live
```

Run the route crawler and Playwright matrix at 1440, 1200, 900, 768, 390, 360, and 320px. Capture screenshots for the key routes.

- [ ] **Step 7: Remove temporary access**

- Delete the temporary private key locally.
- Remove the imported public key from SiteGround.
- Confirm the staging site remains Basic-Auth protected.

- [ ] **Step 8: Complete the handoff**

Document:

- Final branch and commit hashes.
- Test and checker results.
- Deployed package identity.
- Routes and viewports verified.
- Screenshots/evidence paths.
- Illinois phone display and unverified forwarding caveat.
- Claude content integrated or still pending.
- Remaining owner-review facts.
- Confirmation that production, DNS, real submissions, email, leads, booking, and integrations were unchanged.

- [ ] **Step 9: Commit the final handoff**

```bash
git add website/twins-brand-experience/tests/browser/live-private-staging.spec.cjs \
  website/twins-brand-experience/tools/crawl-staging.mjs \
  docs/handoff/2026-07-16-staging-site-unification.md
git commit -m "docs(staging): complete site unification handoff"
```
