# Location Page Modern Conversion Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a real HTML/CSS location-page draft that feels premium and conversion-focused while retaining the existing rounded Twins display font, real technician photography, navy/gold identity, and restrained mascot personality.

**Architecture:** Keep the existing PHP editorial renderer and asset registry, but reduce location mascot output from four dominant full-body placements to three restrained cameos: hero, guidance, and final CTA. Refactor only the location-scoped CSS so shared routes, integrations, tracking, and production authority remain unchanged. Contract tests pin markup, typography, location scoping, accessibility, motion, and responsive behavior; a Playwright fixture verifies the rendered composition at desktop and mobile widths.

**Tech Stack:** PHP 8-compatible templates, CSS, Node.js built-in test runner, Playwright 1.61.1, existing Twins owned image/font assets.

## Global Constraints

- Keep the existing rounded Twins display typeface for primary headings and CTA labels.
- Keep the existing Twins logo, navy-and-gold palette, real technician photography, and existing mascot identities.
- Keep location copy truthful and never use language implying a branch recently opened.
- Present one dominant **Request a Quote** action and a quieter phone action.
- Use at most three mascot moments: hero, guidance, and final CTA.
- Do not use giant opposing full-body mascots, speech bubbles, circular seals, tip badges, or standalone mascot cards.
- Decorative mascots remain `alt=""`, `aria-hidden="true"`, non-interactive, and `pointer-events: none`.
- `prefers-reduced-motion: reduce` disables decorative animation and transforms.
- No horizontal overflow at 320, 360, 390, 768, 1024, or 1440 pixels.
- Do not change booking integrations, form handling, analytics, service routing, market data, or production deployment authority.

---

## File Structure

- `components/twin-character.php`: validates the exact three approved location mascot placement tokens and chooses eager loading only for the above-the-fold hero cameo.
- `templates/editorial.php`: places the hero cameo inside the technician figure, removes the system mascot, and reduces the final CTA to one mascot.
- `assets/css/twins-brand.css`: owns the location-only premium visual treatment, typography preservation, restrained mascot geometry, responsive behavior, and motion fallback.
- `tests/php/renderer-contract-harness.php`: proves three mascot instances, exact assets, decorative semantics, loading behavior, and non-location isolation.
- `tests/contracts/location-page-overhaul-contract.test.cjs`: pins the modern location design tokens and rejects the old heavy-card/giant-mascot treatment.
- `tests/browser/fixtures/location-modern.html`: deterministic rendered fixture with hero, service, guidance, and final CTA sections.
- `tests/browser/location-modern.spec.cjs`: verifies overflow, mascot limits, content clearance, CTA hierarchy, and responsive hiding.

---

### Task 1: Pin the restrained three-mascot renderer contract

**Files:**
- Modify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php:490-615`
- Modify: `website/twins-brand-experience/components/twin-character.php:1-30`

**Interfaces:**
- Consumes: `$experience` as `Twins\BrandExperience\Experience`, `$character` as `left|right`, and `$placement` as `hero|guidance|final-right`.
- Produces: one decorative `<img class="twins-location-twin ...">` string; hero uses `loading="eager"`, later cameos use `loading="lazy"`; invalid character/placement pairs produce an empty string.

- [ ] **Step 1: Update the PHP harness to require the new placement set**

Replace the four-placement assertions with:

```php
foreach (['hero', 'guidance', 'final-right'] as $placement) {
    $expect(
        substr_count($renderedLocation, 'twins-location-twin--' . $placement) === 1,
        $slug . ' did not render exactly one ' . $placement . ' Twin'
    );
}
$expect(strpos($renderedLocation, 'twins-location-twin--system') === false, $slug . ' rendered the retired system Twin');
$expect(strpos($renderedLocation, 'twins-location-twin--final-left') === false, $slug . ' rendered the retired final-left Twin');
$expect(substr_count($renderedLocation, '/brand/twin-left.png') === 1, $slug . ' did not render exactly one left Twin');
$expect(substr_count($renderedLocation, '/brand/twin-right.png') === 2, $slug . ' did not render exactly two right Twins');
$expect(substr_count($renderedLocation, 'alt="" aria-hidden="true"') === 3, $slug . ' Twin accessibility markup drifted');
```

Replace the component checks with:

```php
$heroTwin = $renderComponent($stagingExperience, $root . '/components/twin-character.php', [
    'character' => 'left',
    'placement' => 'hero',
]);
$expect(strpos($heroTwin, 'src="/brand/twin-left.png"') !== false, 'hero Twin omitted the fixed left asset');
$expect(strpos($heroTwin, 'class="twins-location-twin twins-location-twin--hero twins-location-twin--left"') !== false, 'hero Twin classes drifted');
$expect(strpos($heroTwin, 'alt="" aria-hidden="true"') !== false, 'hero Twin is not decorative');
$expect(strpos($heroTwin, 'loading="eager" decoding="async"') !== false, 'hero Twin loading behavior drifted');

$invalidTwinPlacement = $renderComponent($stagingExperience, $root . '/components/twin-character.php', [
    'character' => 'left',
    'placement' => 'system',
]);
$expect($invalidTwinPlacement === '', 'Twin renderer accepted a retired placement');
```

- [ ] **Step 2: Run the PHP harness wrapper and verify the new assertions fail**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
```

Expected: local runs skip when PHP CLI is unavailable; the mandatory repository/remote PHP gate later must execute the new harness. If PHP is available, FAIL because `hero` is unsupported and four mascot placements still render.

- [ ] **Step 3: Restrict the component to the exact new placements**

Change `components/twin-character.php` to:

```php
<?php
declare(strict_types=1);

$characters = [
    'left' => ['asset' => 'twin-left', 'width' => 196, 'height' => 534],
    'right' => ['asset' => 'twin-right', 'width' => 297, 'height' => 538],
];
$placements = ['hero', 'guidance', 'final-right'];

if (
    !isset($character, $placement)
    || !is_string($character)
    || !is_string($placement)
    || !isset($characters[$character])
    || !in_array($placement, $placements, true)
) {
    return;
}

$selectedCharacter = $characters[$character];
$loading = $placement === 'hero' ? 'eager' : 'lazy';
?>
<img src="<?= htmlspecialchars($experience->asset($selectedCharacter['asset']), ENT_QUOTES, 'UTF-8') ?>" width="<?= (int) $selectedCharacter['width'] ?>" height="<?= (int) $selectedCharacter['height'] ?>" class="twins-location-twin twins-location-twin--<?= htmlspecialchars($placement, ENT_QUOTES, 'UTF-8') ?> twins-location-twin--<?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true" loading="<?= $loading ?>" decoding="async">
```

- [ ] **Step 4: Run the focused PHP wrapper and record deferred package drift**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
```

Expected: PHP harness executes and PASSes when PHP is available, otherwise reports only the documented PHP-unavailable skips. Do not rebuild manifests during this task: source-byte changes intentionally leave the hash-pinned package contract failing until Task 5 performs the single final package rebuild after all source changes.

- [ ] **Step 5: Commit**

```bash
git add components/twin-character.php tests/php/renderer-contract-harness.php
git commit -m "refactor: restrain location mascot renderer"
```

---

### Task 2: Move mascots into purposeful page moments

**Files:**
- Modify: `website/twins-brand-experience/templates/editorial.php:175-230,414-430`
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`

**Interfaces:**
- Consumes: the `hero|guidance|final-right` renderer contract from Task 1.
- Produces: exactly three location mascot instances; hero mascot lives inside `.twins-location-hero-media`; system contains no mascot; final CTA contains only the right mascot.

- [ ] **Step 1: Add failing template assertions**

Add:

```js
test('location mascots are restrained to hero, guidance, and one final CTA cameo', () => {
  assert.match(template, /twins-location-hero-media[\s\S]*?\$placement = 'hero'/);
  assert.match(template, /twins-location-guidance[\s\S]*?\$placement = 'guidance'/);
  assert.match(template, /twins-location-final-cta[\s\S]*?\$placement = 'final-right'/);
  assert.doesNotMatch(template, /\$placement = 'system'/);
  assert.doesNotMatch(template, /\$placement = 'final-left'/);
});
```

- [ ] **Step 2: Run the template contract and verify failure**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: FAIL because the hero placement is absent and the system/final-left placements remain.

- [ ] **Step 3: Move the left mascot into the hero figure**

Immediately after the hero `<picture>` component, add:

```php
<?php
$character = 'left';
$placement = 'hero';
require $twinCharacterComponent;
?>
```

Remove the mascot include block from `.twins-location-system`.

- [ ] **Step 4: Reduce final CTA to one right mascot**

Replace the location final CTA mascot block with:

```php
<?php if ($isLocation): ?>
  <?php
  $character = 'right';
  $placement = 'final-right';
  require $twinCharacterComponent;
  ?>
<?php endif; ?>
```

- [ ] **Step 5: Run the focused Node and PHP contracts**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add templates/editorial.php tests/contracts/location-page-overhaul-contract.test.cjs
git commit -m "refactor: integrate location mascots with content"
```

---

### Task 3: Replace the heavy visual treatment with the premium location system

**Files:**
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css:991-1535,1700-1735`
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`

**Interfaces:**
- Consumes: the three placement classes emitted by Task 2.
- Produces: location-only modern hero, proof row, service surfaces, guidance panel, restrained mascots, responsive stacking, and reduced-motion behavior.

- [ ] **Step 1: Replace old mascot-size assertions with modern design contracts**

Use:

```js
test('location design preserves the old display font and uses restrained premium geometry', () => {
  assert.match(css, /\.twins-location-hero h1\s*\{[\s\S]*?font-family:\s*'Lilita One'/);
  assert.match(css, /\.twins-location-hero-media\s*\{[\s\S]*?border-left:\s*2px solid var\(--twins-gold\)/);
  assert.match(css, /\.twins-location-service-card\s*\{[\s\S]*?border:\s*1px solid/);
  assert.doesNotMatch(css, /\.twins-location-service-card\s*\{[\s\S]*?box-shadow:\s*8px 9px 0/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--hero\s*\{[\s\S]*?clamp\(72px,\s*7vw,\s*104px\)/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--guidance\s*\{[\s\S]*?clamp\(92px,\s*8vw,\s*124px\)/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--final-right\s*\{[\s\S]*?clamp\(96px,\s*9vw,\s*132px\)/);
  assert.doesNotMatch(css, /twins-location-twin--system|twins-location-twin--final-left/);
});
```

- [ ] **Step 2: Run the contract and verify failure**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: FAIL on the old heavy borders, shadows, and mascot selectors.

- [ ] **Step 3: Refactor the hero and proof row**

Replace the location hero/proof blocks with the following contract values:

```css
.twins-location-page { background: var(--twins-cream); }
.twins-location-hero {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, .88fr) minmax(420px, 1.12fr);
  align-items: center;
  gap: clamp(52px, 7vw, 112px);
  padding: clamp(72px, 8vw, 124px) var(--twins-content-shell);
  color: var(--twins-white);
  background-color: var(--twins-navy-950);
  background-image: repeating-linear-gradient(0deg, transparent 0 92px, rgba(255,255,255,.035) 93px 95px);
  overflow: hidden;
}
.twins-location-hero::after { content: ''; position: absolute; inset: auto var(--twins-content-shell) 0; height: 1px; background: rgba(255,200,61,.68); }
.twins-location-hero-copy { align-self: center; max-width: 700px; }
.twins-location-hero h1 {
  max-width: 760px;
  margin: 18px 0 24px;
  color: var(--twins-white);
  font-family: 'Lilita One', Impact, sans-serif;
  font-size: clamp(3.2rem, 5.15vw, 5.45rem);
  line-height: .95;
  letter-spacing: .005em;
  text-transform: uppercase;
}
.twins-location-hero-media { position: relative; margin: 0; padding-left: 26px; border-left: 2px solid var(--twins-gold); overflow: visible; }
.twins-location-hero-media picture { display: block; overflow: hidden; }
.twins-location-hero-image { display: block; width: 100%; min-height: 430px; aspect-ratio: 1.32 / 1; object-fit: cover; }
.twins-location-hero-media figcaption { padding: 15px 0 0; color: #d7e3f0; background: transparent; font-size: .85rem; font-weight: 800; }
.twins-location-proof { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); padding: 0 var(--twins-content-shell); color: var(--twins-navy-950); background: var(--twins-white); border-bottom: 1px solid rgba(7,29,59,.16); }
.twins-location-proof > div { min-height: 92px; display: grid; align-content: center; gap: 2px; padding: 18px clamp(20px,3vw,40px); border-left: 1px solid rgba(7,29,59,.14); }
```

- [ ] **Step 4: Simplify services, system, guidance, and cards**

Use one-pixel structure, small radii, and no hard offset shadows:

```css
.twins-location-system { grid-template-columns: minmax(180px,.34fr) minmax(0,1fr); padding: clamp(72px,7vw,108px) var(--twins-content-shell); border-bottom: 1px solid rgba(255,200,61,.55); }
.twins-location-system-visual { min-height: 210px; padding: 22px; border: 1px solid rgba(255,200,61,.5); border-radius: 10px; background: rgba(3,18,43,.42); box-shadow: none; }
.twins-location-service-card { padding: clamp(28px,3vw,38px); background: var(--twins-white); border: 1px solid rgba(7,29,59,.2); border-radius: 10px; box-shadow: none; }
.twins-location-service-art { width: 80px; height: 58px; padding: 7px; border: 0; border-radius: 8px; background: rgba(255,200,61,.72); }
.twins-location-warning-card { padding: clamp(34px,4vw,48px); border: 1px solid rgba(255,200,61,.72); border-radius: 10px; box-shadow: none; }
.twins-location-process-list li { min-height: 230px; padding: 30px; border: 1px solid rgba(7,29,59,.18); border-top: 3px solid var(--twins-gold); border-radius: 8px; box-shadow: none; }
.twins-location-branch aside { border: 1px solid rgba(255,255,255,.72); border-radius: 10px; box-shadow: none; }
```

- [ ] **Step 5: Implement restrained mascot geometry and motion**

```css
.twins-location-page .twins-location-twin { position: absolute; z-index: 2; display: block; height: auto; pointer-events: none; filter: drop-shadow(4px 8px 8px rgba(0,0,0,.2)); transform-origin: 50% 100%; }
.twins-location-page .twins-location-twin--hero { right: -34px; bottom: -14px; width: clamp(72px,7vw,104px); }
.twins-location-page .twins-location-twin--guidance { right: max(14px,calc((100vw - 1480px)/2)); bottom: -8px; width: clamp(92px,8vw,124px); }
.twins-location-page .twins-location-twin--final-right { right: max(14px,calc((100vw - 1480px)/2)); bottom: -10px; width: clamp(96px,9vw,132px); }
@keyframes twins-location-float-left { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
@keyframes twins-location-float-right { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
.twins-location-page .twins-location-twin--left { animation: twins-location-float-left 5.6s ease-in-out infinite; }
.twins-location-page .twins-location-twin--right { animation: twins-location-float-right 6.4s ease-in-out .8s infinite; }
```

- [ ] **Step 6: Add responsive rules without artwork padding rows**

```css
@media (max-width: 1024px) {
  .twins-location-hero { grid-template-columns: 1fr; }
  .twins-location-hero-media { width: min(760px,100%); }
  .twins-location-proof { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .twins-location-hero { gap: 42px; padding: 58px 20px; }
  .twins-location-hero h1 { font-size: clamp(2.7rem,12vw,4.1rem); }
  .twins-location-hero-media { padding-left: 14px; }
  .twins-location-hero-image { min-height: 280px; }
  .twins-location-system { grid-template-columns: 1fr; padding: 58px 20px; }
  .twins-location-page .twins-location-twin--guidance { right: 8px; width: min(96px,25vw); }
  .twins-location-page .twins-location-twin--final-right { right: 8px; width: min(104px,27vw); }
  .twins-location-page .twins-location-guidance,
  .twins-location-page .twins-location-final-cta { padding-right: max(20px,28vw); }
}
@media (max-width: 480px) {
  .twins-location-page .twins-location-twin--hero { display: none; }
  .twins-location-page .twins-location-guidance,
  .twins-location-page .twins-location-final-cta { padding-right: 20px; padding-bottom: 154px; }
}
@media (prefers-reduced-motion: reduce) {
  .twins-location-page .twins-location-twin { animation: none !important; transform: none !important; }
}
```

- [ ] **Step 7: Run all location design contracts**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add assets/css/twins-brand.css tests/contracts/location-page-overhaul-contract.test.cjs
git commit -m "style: modernize location conversion experience"
```

---

### Task 4: Prove desktop and mobile rendered behavior

**Files:**
- Create: `website/twins-brand-experience/tests/browser/fixtures/location-modern.html`
- Create: `website/twins-brand-experience/tests/browser/location-modern.spec.cjs`
- Remove: `website/twins-brand-experience/tests/browser/fixtures/location-mobile.html`
- Remove: `website/twins-brand-experience/tests/browser/location-twins-mobile.spec.cjs`

**Interfaces:**
- Consumes: final template class names and CSS from Tasks 2–3.
- Produces: deterministic viewport evidence for CTA hierarchy, three-mascot limit, overlap, overflow, and reduced-motion behavior.

- [ ] **Step 1: Create a fixture matching the final three mascot placements**

Copy the existing location fixture structure, add the real hero markup and technician picture, and emit only:

```html
<img src="/assets/images/brand/twin-left.png" width="196" height="534" class="twins-location-twin twins-location-twin--hero twins-location-twin--left" alt="" aria-hidden="true">
<img src="/assets/images/brand/twin-right.png" width="297" height="538" class="twins-location-twin twins-location-twin--guidance twins-location-twin--right" alt="" aria-hidden="true">
<img src="/assets/images/brand/twin-right.png" width="297" height="538" class="twins-location-twin twins-location-twin--final-right twins-location-twin--right" alt="" aria-hidden="true">
```

Use one `.twins-brand-cta--quote` and one `.twins-brand-cta--call` in the hero, and the existing location copy/headings.

- [ ] **Step 2: Write the Playwright acceptance test**

```js
const { test, expect } = require('@playwright/test');
const fixture = '/tests/browser/fixtures/location-modern.html';

for (const viewport of [
  { width: 1440, height: 1000 },
  { width: 1024, height: 900 },
  { width: 768, height: 900 },
  { width: 390, height: 844 },
  { width: 360, height: 844 },
  { width: 320, height: 844 },
]) {
  test(`modern location layout holds at ${viewport.width}px`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto(fixture);
    await expect(page.locator('.twins-location-twin')).toHaveCount(3);
    await expect(page.locator('.twins-location-hero .twins-brand-cta--quote')).toHaveCount(1);
    await expect(page.locator('.twins-location-hero .twins-brand-cta--call')).toHaveCount(1);
    expect(await page.evaluate(() => document.documentElement.scrollWidth === document.documentElement.clientWidth)).toBeTruthy();
    const overlaps = await page.evaluate(() => Array.from(document.querySelectorAll('.twins-location-twin')).some((twin) => {
      if (getComputedStyle(twin).display === 'none') return false;
      const a = twin.getBoundingClientRect();
      return Array.from(twin.closest('section,header,figure').querySelectorAll('h1,h2,h3,p,a')).some((node) => {
        const b = node.getBoundingClientRect();
        return a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
      });
    }));
    expect(overlaps).toBeFalsy();
  });
}

test('reduced motion keeps mascots static', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto(fixture);
  for (const animation of await page.locator('.twins-location-twin').evaluateAll(nodes => nodes.map(node => getComputedStyle(node).animationName))) {
    expect(animation).toBe('none');
  }
});
```

- [ ] **Step 3: Run the browser test and verify all seven cases pass**

Run:

```bash
env PATH=/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin:/usr/bin:/bin:/usr/sbin:/sbin /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node node_modules/@playwright/test/cli.js test tests/browser/location-modern.spec.cjs
```

Expected: `7 passed`; no overflow or overlap failures.

- [ ] **Step 4: Commit**

```bash
git add tests/browser/fixtures/location-modern.html tests/browser/location-modern.spec.cjs tests/browser/fixtures/location-mobile.html tests/browser/location-twins-mobile.spec.cjs
git commit -m "test: verify modern location layout"
```

---

### Task 5: Verify and render the coded draft without deploying

**Files:**
- Modify: `website/twins-brand-experience/manifests/staging-runtime.json`
- Modify: `website/twins-brand-experience/manifests/host-verification.json`
- Modify: `website/twins-brand-experience/tests/contracts/site-unification.test.cjs` when the generated CSS identity changes
- Modify generated/ignored package, preview, and test-result artifacts.

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: clean contract/package gates plus desktop and mobile screenshots for user review. Does not deploy to staging or production.

- [ ] **Step 1: Rebuild the closed staging packages once after all source changes**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs
```

Expected: package and host-verification manifests are regenerated from the final source bytes with `writeAuthority:false` and `productionWriteAuthority:false`; the CSS identity in `tests/contracts/site-unification.test.cjs` is updated to the generated first 16 SHA-256 characters when required.

- [ ] **Step 2: Run the complete local contract suite**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/*.test.cjs
```

Expected: all contracts PASS with zero failures.

- [ ] **Step 3: Verify assets and package drift**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-owned-images.mjs --check
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs --check
```

Expected: asset check exits 0; package checker reports `STAGING_PACKAGES_VERIFIED`, `writeAuthority:false`, and `productionWriteAuthority:false`.

- [ ] **Step 4: Run repository gate with the pinned npm shim**

```bash
env PATH=/tmp/twins-location-twins-npm:/usr/bin:/bin:/usr/sbin:/sbin /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/check-repository.mjs
```

Expected: `REPOSITORY_CHECK_PASSED`; PHP-unavailable skips are recorded rather than misreported as local PHP execution.

- [ ] **Step 5: Render desktop and mobile screenshots from the coded fixture**

Capture:

```text
1440x1000 — full hero plus proof row
390x844 — hero, actions, proof, and photo stack
```

Save both images under the current writable handoff directory with `modern-location-desktop.png` and `modern-location-mobile.png`.

- [ ] **Step 6: Inspect the screenshots and correct any remaining visual defect**

Acceptance checklist:

```text
- original rounded display font is visibly retained;
- real technician photo is the dominant proof visual;
- only one small hero mascot cameo appears and never overlaps copy;
- Request a Quote is the strongest action;
- phone is visually secondary;
- no badge cluster, giant side mascot, thick repeated outlines, or offset shadows;
- no text clipping or horizontal overflow.
```

- [ ] **Step 7: Request independent code review**

Review the implementation commits against:

```text
docs/superpowers/specs/2026-07-22-location-page-modern-conversion-redesign.md
docs/superpowers/plans/2026-07-22-location-page-modern-conversion-redesign.md
```

Fix every Critical or Important finding before showing the draft.

- [ ] **Step 8: Show the coded desktop and mobile draft to the user**

Do not deploy. Present both rendered screenshots and state clearly that staging remains on r29 until the user approves the new coded direction.
