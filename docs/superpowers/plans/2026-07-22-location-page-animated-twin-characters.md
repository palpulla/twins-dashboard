# Location Page Animated Twin Characters Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the two existing Twin technician illustrations to three purposeful, animated moments on every registered location page without changing location copy, the homepage characters, or any non-location page.

**Architecture:** Add one fail-closed PHP image component that maps four approved placement tokens to the two fixed asset keys, then invoke it only from the location branch of the shared editorial template. Add location-scoped CSS for reserved safe areas, stacking, subtle CSS-only motion, mobile reductions, and reduced-motion behavior; preserve the existing immutable staging deployment model by rotating to transaction `staging-remediation-r28-20260722` and hash-pinning every changed deploy or verification file.

**Tech Stack:** PHP 8 strict templates, CSS custom properties/media queries/keyframes, Node.js 20 contract tests, PHP CLI renderer harness, Playwright 1.61.1, JSON staging manifests, immutable SSH staging deployer.

## Global Constraints

- Scope all new visual selectors beneath `.twins-location-page`; homepage selectors and animation behavior must remain unchanged.
- Reuse only fixed asset keys `twin-left` and `twin-right`; do not add, generate, duplicate, or download image files.
- Render characters only on registered location pages: system left, guidance right, final left, and final right.
- Keep the real technician photo as the only hero visual; do not add a character to the hero.
- Treat all four illustrations as decorative with `alt=""`, `aria-hidden="true"`, `loading="lazy"`, and `decoding="async"`.
- Use `pointer-events: none`; text, links, and buttons must remain above the character layer and fully interactive.
- Use CSS-only motion: left 4.8 seconds, right 6.2 seconds with a 0.7-second delay, at most 6 pixels of vertical movement and 1.25 degrees of rotation.
- Under `prefers-reduced-motion: reduce`, keep the static illustrations visible but disable their animation and transform.
- At widths above 768 pixels, render all four instances. At 768 pixels and below, keep system left at no more than 112 pixels, guidance right at no more than 142 pixels, hide final left, and keep final right at no more than 148 pixels.
- Preserve `scrollWidth === clientWidth` at 390 by 844 pixels and keep every character clear of copy, links, CTA buttons, and sticky mobile actions.
- Do not change headings, paragraphs, service claims, location records, structured data, analytics, dependencies, or network behavior.
- Preserve the unrelated Madison financing commit and all user-owned worktree changes.
- Deploy only to `https://danielj140.sg-host.com/`; production write authority remains false.

---

### Task 1: Add the fail-closed Twin character renderer

**Files:**
- Create: `website/twins-brand-experience/components/twin-character.php`
- Modify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php:554-584`
- Test: `website/twins-brand-experience/tests/php-harnesses.test.cjs`

**Interfaces:**
- Consumes: `$experience` as `Twins\BrandExperience\Experience`, `$character` as the literal string `left|right`, and `$placement` as the literal string `system|guidance|final-left|final-right`.
- Produces: one decorative `<img class="twins-location-twin ...">` string, or an empty string for any unsupported character or placement token.

- [ ] **Step 1: Write the failing component contract**

Insert this block immediately before the existing `$crewPicture` component test in `tests/php/renderer-contract-harness.php`:

```php
$systemTwin = $renderComponent($stagingExperience, $root . '/components/twin-character.php', [
    'character' => 'left',
    'placement' => 'system',
]);
$expect(strpos($systemTwin, 'src="/brand/twin-left.png"') !== false, 'system Twin omitted the fixed left asset');
$expect(strpos($systemTwin, 'width="196" height="534"') !== false, 'system Twin dimensions drifted');
$expect(strpos($systemTwin, 'class="twins-location-twin twins-location-twin--system twins-location-twin--left"') !== false, 'system Twin classes drifted');
$expect(strpos($systemTwin, 'alt="" aria-hidden="true"') !== false, 'system Twin is not decorative');
$expect(strpos($systemTwin, 'loading="lazy" decoding="async"') !== false, 'system Twin loading behavior drifted');

$guidanceTwin = $renderComponent($stagingExperience, $root . '/components/twin-character.php', [
    'character' => 'right',
    'placement' => 'guidance',
]);
$expect(strpos($guidanceTwin, 'src="/brand/twin-right.png"') !== false, 'guidance Twin omitted the fixed right asset');
$expect(strpos($guidanceTwin, 'width="297" height="538"') !== false, 'guidance Twin dimensions drifted');
$expect(strpos($guidanceTwin, 'class="twins-location-twin twins-location-twin--guidance twins-location-twin--right"') !== false, 'guidance Twin classes drifted');

$invalidTwinCharacter = $renderComponent($stagingExperience, $root . '/components/twin-character.php', [
    'character' => 'center',
    'placement' => 'system',
]);
$expect($invalidTwinCharacter === '', 'Twin renderer accepted an unsupported character');

$invalidTwinPlacement = $renderComponent($stagingExperience, $root . '/components/twin-character.php', [
    'character' => 'left',
    'placement' => 'hero',
]);
$expect($invalidTwinPlacement === '', 'Twin renderer accepted an unsupported placement');
```

- [ ] **Step 2: Run the focused PHP harness and confirm the red state**

Run from `website/twins-brand-experience`:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
```

Expected: FAIL in `shared component renderer contracts` because `components/twin-character.php` does not exist.

- [ ] **Step 3: Implement the minimal fixed-map renderer**

Create `components/twin-character.php` with exactly this implementation:

```php
<?php
declare(strict_types=1);

$characters = [
    'left' => [
        'asset' => 'twin-left',
        'width' => 196,
        'height' => 534,
    ],
    'right' => [
        'asset' => 'twin-right',
        'width' => 297,
        'height' => 538,
    ],
];
$placements = ['system', 'guidance', 'final-left', 'final-right'];

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
?>
<img src="<?= htmlspecialchars($experience->asset($selectedCharacter['asset']), ENT_QUOTES, 'UTF-8') ?>" width="<?= (int) $selectedCharacter['width'] ?>" height="<?= (int) $selectedCharacter['height'] ?>" class="twins-location-twin twins-location-twin--<?= htmlspecialchars($placement, ENT_QUOTES, 'UTF-8') ?> twins-location-twin--<?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true" loading="lazy" decoding="async">
```

- [ ] **Step 4: Run the focused PHP harness and confirm the green state**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
```

Expected: all PHP harness subtests PASS and the renderer harness emits `renderer-contracts-ok`.

- [ ] **Step 5: Commit the renderer slice**

```bash
git add website/twins-brand-experience/components/twin-character.php website/twins-brand-experience/tests/php/renderer-contract-harness.php
git commit -m "feat: add location Twin character renderer"
```

---

### Task 2: Render the four approved instances on location pages only

**Files:**
- Modify: `website/twins-brand-experience/templates/editorial.php:170-220,251-273,402-412`
- Modify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php:484-584`
- Test: `website/twins-brand-experience/tests/php-harnesses.test.cjs`

**Interfaces:**
- Consumes: `components/twin-character.php` from Task 1 through variables `$experience`, `$character`, and `$placement`.
- Produces: exactly two `/brand/twin-left.png` and two `/brand/twin-right.png` image instances per rendered location page; no `.twins-location-twin` markup for trust or article editorial output.

- [ ] **Step 1: Add failing location-output contracts**

Inside the existing `foreach ($locationRecords as $slug => $record)` loop, after the section-class assertions and before the FAQ count assertion, add:

```php
    foreach (['system', 'guidance', 'final-left', 'final-right'] as $placement) {
        $expect(
            substr_count($renderedLocation, 'twins-location-twin--' . $placement) === 1,
            $slug . ' did not render exactly one ' . $placement . ' Twin'
        );
    }
    $expect(substr_count($renderedLocation, '/brand/twin-left.png') === 2, $slug . ' did not render exactly two left Twins');
    $expect(substr_count($renderedLocation, '/brand/twin-right.png') === 2, $slug . ' did not render exactly two right Twins');
    $expect(substr_count($renderedLocation, 'alt="" aria-hidden="true"') === 4, $slug . ' Twin accessibility markup drifted');
```

After the `$rockfordLocation` art assertions and before the component-level `$systemTwin` tests, add this non-location regression contract:

```php
$trustEditorial = $stagingExperience->renderEditorial([
    'environment' => 'staging',
    'market' => 'main',
    'path' => '/about-us/',
    'title' => 'About Twins Garage Doors',
], '<p>Trusted local garage door service.</p>', 'trust');
$expect(strpos($trustEditorial, 'twins-location-twin') === false, 'trust editorial rendered location Twin markup');

$articleEditorial = $stagingExperience->renderEditorial([
    'environment' => 'staging',
    'market' => 'main',
    'path' => '/blog/garage-door-guide/',
    'title' => 'Garage Door Guide',
], '<p>Garage door care guidance.</p>', 'article');
$expect(strpos($articleEditorial, 'twins-location-twin') === false, 'article editorial rendered location Twin markup');
```

- [ ] **Step 2: Run the renderer contract and confirm the red state**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
```

Expected: FAIL with the first registered location reporting that `system` placement was not rendered.

- [ ] **Step 3: Wire the shared component path into the template**

Immediately after the existing `require_once` for `door-art.php`, add:

```php
$twinCharacterComponent = dirname(__DIR__) . '/components/twin-character.php';
```

- [ ] **Step 4: Add the system and guidance moments inside the existing location branch**

Add this component call as the first child of `<section class="twins-location-system" ...>`:

```php
      <?php
      $character = 'left';
      $placement = 'system';
      require $twinCharacterComponent;
      ?>
```

Add this component call as the first child of `<section class="twins-location-guidance" ...>`:

```php
      <?php
      $character = 'right';
      $placement = 'guidance';
      require $twinCharacterComponent;
      ?>
```

- [ ] **Step 5: Add the final pair behind the location final CTA**

Immediately after the opening `<section class="twins-brand-final-cta...">` tag and before `twins_brand_door_art(...)`, add:

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

This conditional is mandatory even though the component itself has a fixed allowlist: the renderer validates token identity, while the template owns page-scope identity.

- [ ] **Step 6: Run the renderer contract and confirm the green state**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
```

Expected: all PHP harness subtests PASS; every registered location has four approved placement tokens and non-location trust output has none.

- [ ] **Step 7: Commit the location-only placement slice**

```bash
git add website/twins-brand-experience/templates/editorial.php website/twins-brand-experience/tests/php/renderer-contract-harness.php
git commit -m "feat: place Twins across location pages"
```

---

### Task 3: Add safe-area styling, restrained motion, and mobile behavior

**Files:**
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css:1105-1132,1331-1380,1832-1840`
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`
- Test: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`

**Interfaces:**
- Consumes: `.twins-location-twin`, placement modifiers, and left/right modifiers emitted by Tasks 1 and 2.
- Produces: location-scoped absolute positioning, explicit content stacking, safe-area padding, two animation keyframes, mobile single-character behavior, and a static reduced-motion state.

- [ ] **Step 1: Add the failing CSS contract**

Append this test to `tests/contracts/location-page-overhaul-contract.test.cjs`:

```javascript
test('location Twin characters are scoped, non-interactive, responsive, and motion-safe', () => {
  assert.match(css, /\.twins-location-page \.twins-location-twin\s*\{[\s\S]*?pointer-events:\s*none/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--system\s*\{[\s\S]*?clamp\(132px,\s*12vw,\s*176px\)/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--guidance\s*\{[\s\S]*?clamp\(180px,\s*16vw,\s*218px\)/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--final-left\s*\{[\s\S]*?clamp\(128px,\s*11vw,\s*166px\)/);
  assert.match(css, /\.twins-location-page \.twins-location-twin--final-right\s*\{[\s\S]*?clamp\(180px,\s*16vw,\s*224px\)/);
  assert.match(css, /twins-location-float-left 4\.8s ease-in-out infinite/);
  assert.match(css, /twins-location-float-right 6\.2s ease-in-out \.7s infinite/);
  assert.match(css, /@keyframes twins-location-float-left[\s\S]*translateY\(-6px\) rotate\(-1\.25deg\)/);
  assert.match(css, /@keyframes twins-location-float-right[\s\S]*translateY\(-6px\) rotate\(1\.25deg\)/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*\.twins-location-page \.twins-location-twin--system[\s\S]*width:\s*min\(112px,\s*29vw\)/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*\.twins-location-page \.twins-location-twin--guidance[\s\S]*width:\s*min\(142px,\s*37vw\)/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*\.twins-location-page \.twins-location-twin--final-left\s*\{\s*display:\s*none/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*\.twins-location-page \.twins-location-twin--final-right[\s\S]*width:\s*min\(148px,\s*38vw\)/);
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)[\s\S]*\.twins-location-page \.twins-location-twin[\s\S]*animation:\s*none !important[\s\S]*transform:\s*none !important/);
  assert.doesNotMatch(css, /(^|\n)\s*\.twins-location-twin/,
    'location Twin selectors must never escape the location-page scope');
});
```

- [ ] **Step 2: Run the focused contract and confirm the red state**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: the new test FAILS because `.twins-location-page .twins-location-twin` is absent.

- [ ] **Step 3: Add the desktop character layer and reserved safe areas**

Insert this block immediately after the existing shared location-section child stacking rule and before the section `::before`/`::after` texture rules:

```css
.twins-location-page .twins-location-twin {
  position: absolute;
  z-index: 1;
  display: block;
  height: auto;
  pointer-events: none;
  filter: drop-shadow(10px 16px 7px rgba(0, 0, 0, .34));
  transform-origin: 50% 100%;
}
.twins-location-page :is(.twins-location-system, .twins-location-guidance, .twins-location-final-cta) > :not(.twins-location-twin) {
  position: relative;
  z-index: 2;
}
.twins-location-page .twins-location-twin--system {
  left: max(12px, calc((100vw - 1420px) / 2));
  bottom: -12px;
  width: clamp(132px, 12vw, 176px);
}
.twins-location-page .twins-location-twin--guidance {
  right: max(8px, calc((100vw - 1480px) / 2));
  bottom: -20px;
  width: clamp(180px, 16vw, 218px);
}
.twins-location-page .twins-location-twin--final-left {
  left: max(12px, calc((100vw - 1420px) / 2));
  bottom: -14px;
  width: clamp(128px, 11vw, 166px);
}
.twins-location-page .twins-location-twin--final-right {
  right: max(8px, calc((100vw - 1480px) / 2));
  bottom: -20px;
  width: clamp(180px, 16vw, 224px);
}
.twins-location-page .twins-location-system {
  padding-left: max(210px, calc((100vw - 1320px) / 2 + 176px));
}
.twins-location-page .twins-location-guidance {
  padding-right: max(220px, calc((100vw - 1320px) / 2 + 190px));
}
.twins-location-page .twins-location-final-cta {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  padding-inline: clamp(190px, 18vw, 260px);
}
```

The extra left and right padding is the collision boundary: the characters occupy the reserved gutters, while the animated door, warning card, and final CTA content remain in the higher `z-index: 2` safe area.

- [ ] **Step 4: Add the two restrained animation cycles**

Insert this block adjacent to the existing homepage Twin keyframes, but keep the new names and selectors separate:

```css
@keyframes twins-location-float-left {
  0%, 100% { transform: translateY(0) rotate(0); }
  50% { transform: translateY(-6px) rotate(-1.25deg); }
}
@keyframes twins-location-float-right {
  0%, 100% { transform: translateY(0) rotate(0); }
  50% { transform: translateY(-6px) rotate(1.25deg); }
}
.twins-location-page .twins-location-twin--left { animation: twins-location-float-left 4.8s ease-in-out infinite; }
.twins-location-page .twins-location-twin--right { animation: twins-location-float-right 6.2s ease-in-out .7s infinite; }
```

- [ ] **Step 5: Add the mobile single-character layout**

At the end of the existing `@media (max-width: 768px)` block, add:

```css
  .twins-location-page .twins-location-system {
    padding-right: 20px;
    padding-bottom: 142px;
    padding-left: 20px;
  }
  .twins-location-page .twins-location-guidance {
    padding-right: 20px;
    padding-bottom: 174px;
    padding-left: 20px;
  }
  .twins-location-page .twins-location-final-cta {
    padding-right: 20px;
    padding-bottom: 174px;
    padding-left: 20px;
  }
  .twins-location-page .twins-location-twin--system {
    left: 12px;
    bottom: 0;
    width: min(112px, 29vw);
  }
  .twins-location-page .twins-location-twin--guidance {
    right: 8px;
    bottom: 0;
    width: min(142px, 37vw);
  }
  .twins-location-page .twins-location-twin--final-left { display: none; }
  .twins-location-page .twins-location-twin--final-right {
    right: 8px;
    bottom: 0;
    width: min(148px, 38vw);
  }
```

The added bottom padding creates a dedicated artwork row below each content stack instead of allowing a decorative image to cover text or controls.

- [ ] **Step 6: Add the explicit reduced-motion contract implementation**

Inside the existing `@media (prefers-reduced-motion: reduce)` block, after `.twins-brand-twin { transform: none !important; }`, add:

```css
  .twins-location-page .twins-location-twin {
    animation: none !important;
    transform: none !important;
  }
```

- [ ] **Step 7: Run the focused contract and PHP output tests**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
```

Expected: both commands PASS. The CSS contract proves exact durations, movement, mobile caps, scoping, pointer safety, and reduced-motion behavior; the PHP harness still proves exact output counts.

- [ ] **Step 8: Commit the visual behavior slice**

```bash
git add website/twins-brand-experience/assets/css/twins-brand.css website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
git commit -m "feat: animate Twins on location pages"
```

---

### Task 4: Hash-pin the release and rotate the immutable staging transaction

**Files:**
- Modify: `website/twins-brand-experience/manifests/staging-runtime.json`
- Modify: `website/twins-brand-experience/manifests/host-verification.json`
- Modify: `website/twins-brand-experience/tests/contracts/package-contract.test.cjs`
- Modify: `website/twins-brand-experience/tests/contracts/deployment-tool-contract.test.cjs`
- Modify: `website/twins-brand-experience/tests/contracts/site-unification.test.cjs`
- Modify: `website/twins-brand-experience/tools/deploy-private-staging.mjs`
- Modify: `website/twins-brand-experience/tools/private-staging-deploy.php`
- Test: `website/twins-brand-experience/tests/contracts/package-contract.test.cjs`
- Test: `website/twins-brand-experience/tests/contracts/deployment-tool-contract.test.cjs`
- Test: `website/twins-brand-experience/tests/contracts/site-unification.test.cjs`

**Interfaces:**
- Consumes: final bytes from Tasks 1-3.
- Produces: a closed staging package containing the new component and changed CSS/template, a closed verification package containing every changed remotely verified file, and one fresh transaction identity: `/home/customer/staging-safety/staging-remediation-r28-20260722`.

- [ ] **Step 1: Update package and deployment expectations first**

In `tests/contracts/package-contract.test.cjs`, change the expected verification directory to:

```javascript
  assert.equal(manifest.remoteDirectory, '/home/customer/staging-safety/staging-remediation-r28-20260722/verification/');
```

Add this assertion beside the existing deploy-set assertions:

```javascript
  assert.equal(deploy.has('twins-brand-experience/components/twin-character.php'), true);
```

Add this entry to the exact `required` verification-file array:

```javascript
    'twins-brand-experience/components/twin-character.php',
```

In `tests/contracts/deployment-tool-contract.test.cjs`, change the release constant and state-root expectation to:

```javascript
const releaseRoot = '/home/customer/staging-safety/staging-remediation-r28-20260722';
```

```javascript
  assert.match(nodeSource, /path\.join\(stateParent, 'staging-remediation-r28-20260722'\)/);
```

- [ ] **Step 2: Run the focused package/deployment contracts and confirm the red state**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/package-contract.test.cjs tests/contracts/deployment-tool-contract.test.cjs
```

Expected: FAIL because manifests and deployment source still use `r27`, and the component is not yet in either closed manifest.

- [ ] **Step 3: Rotate exactly the five source/manifest transaction literals**

Use `apply_patch` to replace every `staging-remediation-r27-20260722` literal with `staging-remediation-r28-20260722` in exactly these files:

```text
website/twins-brand-experience/manifests/host-verification.json
website/twins-brand-experience/tools/deploy-private-staging.mjs
website/twins-brand-experience/tools/private-staging-deploy.php
website/twins-brand-experience/tests/contracts/deployment-tool-contract.test.cjs
website/twins-brand-experience/tests/contracts/package-contract.test.cjs
```

Then verify the boundary:

```bash
rg -n "staging-remediation-r(27|28)-20260722" manifests/host-verification.json tools/deploy-private-staging.mjs tools/private-staging-deploy.php tests/contracts/deployment-tool-contract.test.cjs tests/contracts/package-contract.test.cjs
```

Expected: only `r28` appears; `deploy-private-staging.mjs` contains it in both `TRANSACTION_ROOT` and the local state-directory name.

- [ ] **Step 4: Derive exact size and SHA-256 identities for all changed pinned files**

Run from `website/twins-brand-experience`:

```bash
for twins_release_file in assets/css/twins-brand.css components/twin-character.php templates/editorial.php tests/php/renderer-contract-harness.php tools/private-staging-deploy.php; do wc -c "$twins_release_file"; shasum -a 256 "$twins_release_file"; done
```

Expected: each path prints one integer byte count and one lowercase 64-character SHA-256. Use those exact reported values; do not round sizes, shorten hashes, or retain any pre-change identity.

- [ ] **Step 5: Update the closed staging manifest in byte-sorted order**

In `manifests/staging-runtime.json`:

- Replace the `size` and `sha256` fields for `assets/css/twins-brand.css` with the values from Step 4.
- Replace the `size` and `sha256` fields for `templates/editorial.php` with the values from Step 4.
- Add one `role: "deploy"` record for `components/twin-character.php` immediately after `components/service-areas-panel.php` and before `config/location-content.php`, using the exact size and SHA-256 from Step 4 and identical source/destination paths.

Print the exact component record with this deterministic command:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node -e "const fs=require('node:fs');const crypto=require('node:crypto');const bytes=fs.readFileSync('components/twin-character.php');console.log(JSON.stringify({role:'deploy',source:'twins-brand-experience/components/twin-character.php',destination:'twins-brand-experience/components/twin-character.php',size:bytes.length,sha256:crypto.createHash('sha256').update(bytes).digest('hex')},null,2))"
```

Insert the emitted object verbatim. Verify the closed manifest immediately:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/package-contract.test.cjs
```

Expected: it may still fail on the not-yet-updated verification manifest, but it must not report `SOURCE_DRIFT` for the CSS, component, or editorial template staging entries.

- [ ] **Step 6: Update the closed host-verification manifest**

In `manifests/host-verification.json`:

- Replace the existing CSS, editorial template, renderer harness, and private deployment tool sizes/hashes with the exact Step 4 values.
- Add one `role: "verify"` record for `twins-brand-experience/components/twin-character.php` immediately after `components/service-areas-panel.php` and before `config/location-content.php`, using the exact component identity from Step 4.
- Keep `remoteDirectory` fixed to `/home/customer/staging-safety/staging-remediation-r28-20260722/verification/`.

Print the exact verification record with this deterministic command:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node -e "const fs=require('node:fs');const crypto=require('node:crypto');const bytes=fs.readFileSync('components/twin-character.php');console.log(JSON.stringify({role:'verify',source:'twins-brand-experience/components/twin-character.php',size:bytes.length,sha256:crypto.createHash('sha256').update(bytes).digest('hex')},null,2))"
```

Insert the emitted object verbatim.

Now derive the final staging-manifest identity:

```bash
wc -c manifests/staging-runtime.json
shasum -a 256 manifests/staging-runtime.json
```

Replace the `twins-brand-experience/manifests/staging-runtime.json` record in `host-verification.json` with those exact values. Finally run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/package-contract.test.cjs
```

Expected: PASS; every recorded file has an exact byte count and SHA-256, both manifests remain byte-sorted, and the new component occurs exactly once in each closed set.

- [ ] **Step 7: Refresh the independently derived CSS version contract**

Run:

```bash
shasum -a 256 assets/css/twins-brand.css
```

Take the first 16 characters of the reported hash and replace only the `css` value in the expected `versions` object in `tests/contracts/site-unification.test.cjs`. Leave the family CSS, JavaScript, and builder JavaScript identities unchanged.

- [ ] **Step 8: Build the packages and run the focused integrity contracts**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/package-contract.test.cjs tests/contracts/deployment-tool-contract.test.cjs tests/contracts/site-unification.test.cjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs --check
```

Expected: all three contract files PASS; package build reports success; package check reports no drift; the staging package includes `components/twin-character.php` exactly once.

- [ ] **Step 9: Commit the release identity slice**

```bash
git add website/twins-brand-experience/manifests/staging-runtime.json website/twins-brand-experience/manifests/host-verification.json website/twins-brand-experience/tests/contracts/package-contract.test.cjs website/twins-brand-experience/tests/contracts/deployment-tool-contract.test.cjs website/twins-brand-experience/tests/contracts/site-unification.test.cjs website/twins-brand-experience/tools/deploy-private-staging.mjs website/twins-brand-experience/tools/private-staging-deploy.php
git commit -m "chore: package animated location Twins"
```

---

### Task 5: Run full verification, deploy once, and perform authenticated visual QA

**Files:**
- Verify: `website/twins-brand-experience/dist/staging-runtime/`
- Verify: `website/twins-brand-experience/dist/host-verification/`
- Verify: `website/twins-brand-experience/tests/browser/live-private-staging.spec.cjs`
- Verify: Rockford and Loves Park private staging routes.

**Interfaces:**
- Consumes: the hash-pinned `r28` package from Task 4 and the existing fixed SSH identity.
- Produces: a passing repository gate, one immutable staging deployment report, authenticated desktop/mobile/reduced-motion evidence, and confirmed live-byte identities.

- [ ] **Step 1: Create the bounded temporary npm compatibility shim used by the repository gate**

The desktop runtime has a fixed Node binary but no global npm executable. Create `/tmp/twins-location-twins-npm/npm` with `apply_patch` using exactly this content, then mark it executable:

```sh
#!/bin/sh
set -eu

if [ "$1" != "run" ]; then
  echo "unsupported npm invocation" >&2
  exit 2
fi

case "$2" in
  test:contracts)
    exec /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/*.test.cjs
    ;;
  test:php)
    exec /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
    ;;
  check:assets)
    exec /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-owned-images.mjs --check
    ;;
  check:packages)
    exec /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs --check
    ;;
  *)
    echo "unsupported npm script: $2" >&2
    exit 2
    ;;
esac
```

Run:

```bash
chmod 755 /tmp/twins-location-twins-npm/npm
```

- [ ] **Step 2: Run the complete local quality gate**

Run from `website/twins-brand-experience`:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/*.test.cjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-owned-images.mjs --check
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs --check
env PATH="/tmp/twins-location-twins-npm:$PATH" /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/check-repository.mjs
git diff --check
```

Expected: every contract and PHP harness PASS, owned assets remain unchanged, package check and repository gate PASS, and `git diff --check` prints nothing. If any command fails, stop and fix the failing layer before deployment.

- [ ] **Step 3: Confirm the deploy boundary before using the one-shot transaction**

Run:

```bash
git status --short
git log -8 --oneline
rg -n "staging-remediation-r28-20260722" manifests/host-verification.json tools/deploy-private-staging.mjs tools/private-staging-deploy.php tests/contracts/deployment-tool-contract.test.cjs tests/contracts/package-contract.test.cjs
```

Expected: only planned files or ignored `dist/` artifacts are present; the four implementation commits are visible; every active transaction literal is `r28`; production manifests and production URLs are untouched.

- [ ] **Step 4: Run dry-run, capture, and deploy exactly once**

Run each operation separately from `website/twins-brand-experience` with the fixed staging SSH identity:

```bash
env TWINS_STAGE_SSH_TARGET='u2356-y8avsfoqgaqv@ssh.danielj140.sg-host.com' TWINS_STAGE_SSH_KEY='/Users/daniel/.ssh/twins_stage_deploy_20260717' TWINS_STAGE_SSH_HOSTKEY_SHA256='SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4' /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/deploy-private-staging.mjs --dry-run
```

Expected status: `PRIVATE_STAGING_DRY_RUN_PASSED`.

```bash
env TWINS_STAGE_SSH_TARGET='u2356-y8avsfoqgaqv@ssh.danielj140.sg-host.com' TWINS_STAGE_SSH_KEY='/Users/daniel/.ssh/twins_stage_deploy_20260717' TWINS_STAGE_SSH_HOSTKEY_SHA256='SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4' /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/deploy-private-staging.mjs --capture-expected-old
```

Expected status: `EXPECTED_OLD_CAPTURED`.

```bash
env TWINS_STAGE_SSH_TARGET='u2356-y8avsfoqgaqv@ssh.danielj140.sg-host.com' TWINS_STAGE_SSH_KEY='/Users/daniel/.ssh/twins_stage_deploy_20260717' TWINS_STAGE_SSH_HOSTKEY_SHA256='SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4' /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/deploy-private-staging.mjs --deploy
```

Expected status: `PRIVATE_STAGING_DEPLOYED`, with matching manifest, deploy-package, and prerequisite-set SHA-256 identities. Do not retry a consumed transaction. On failure, preserve the report and diagnose before creating any subsequent transaction.

- [ ] **Step 5: Run authenticated live browser verification when credentials are configured**

Never print or commit Basic Auth credentials. With `TWINS_STAGE_USER` and `TWINS_STAGE_PASSWORD` already present in the execution environment, run:

```bash
env TWINS_STAGE_URL='https://danielj140.sg-host.com/' /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/crawl-staging.mjs
env TWINS_STAGE_URL='https://danielj140.sg-host.com/' /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/live-verification.test.cjs
```

Expected: `PRIVATE_STAGING_CRAWL_PASSED`, screenshots are captured at the configured desktop/mobile widths, and the live verification contract PASSes. If credentials are not configured, record `PRIVATE_STAGING_CONFIGURATION_REQUIRED`; do not call visual QA complete and do not expose secrets in shell history.

- [ ] **Step 6: Inspect the two required cities at exact acceptance viewports**

Open these authenticated cache-busted staging routes:

```text
https://danielj140.sg-host.com/il/location/rockford/?r28=1
https://danielj140.sg-host.com/il/location/loves-park/?r28=1
```

For each route, inspect at 1440 by 1000 and 390 by 844 pixels and record these checks:

```text
Desktop 1440x1000
- System left Twin is visible beside the animated garage-door visual and does not cover system copy.
- Guidance right Twin is visible beside the warning card and does not cover the card or local guidance.
- Final left and final right Twins balance the central CTA without covering its heading, paragraph, phone link, or quote button.
- Motion is limited to a restrained float/tilt and the two characters do not move in sync.

Mobile 390x844
- System shows one left Twin at or below 112px.
- Guidance shows one right Twin at or below 142px.
- Final CTA hides the left Twin and shows one right Twin at or below 148px.
- No Twin covers text, buttons, or sticky mobile actions.
- document.documentElement.scrollWidth === document.documentElement.clientWidth.

Reduced motion
- All visible characters remain statically positioned.
- Every .twins-location-twin reports animation-name: none.

Both sizes
- Existing garage-door textures and door illustrations remain visible.
- Hero contains the real technician photo and no Twin character.
- Browser console has no new warnings or errors.
```

- [ ] **Step 7: Confirm live-byte identity and finish only with evidence**

Compare the deployed report’s CSS, component, and editorial-template identities with their corresponding `staging-runtime.json` entries. Confirm that the authenticated live stylesheet version is the first 16 characters recorded in `tests/contracts/site-unification.test.cjs` and that Rockford and Loves Park render all expected placement classes.

Expected final state: full local gates PASS, `r28` reports `PRIVATE_STAGING_DEPLOYED`, authenticated desktop/mobile/reduced-motion checks pass for both cities, live bytes match the package, and production remains untouched. If authentication blocks Steps 5-7, report deployment as complete but visual verification as explicitly pending rather than claiming the feature fully verified.
