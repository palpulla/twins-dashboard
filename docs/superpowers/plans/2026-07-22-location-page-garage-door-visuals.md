# Location Page Garage-Door Visuals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the location-page animated garage door into an upper system showcase, add service-specific garage-door illustrations, and leave a static door mark in the final CTA.

**Architecture:** Reuse the dependency-free `twins_brand_door_art()` inline SVG component already shipped by the portable brand experience. Extend the shared location service-card data with fixed art keys, render one new location-only system band after the proof bar, and select a static final-CTA door only for location pages. Keep all motion CSS-controlled so the existing reduced-motion boundary remains authoritative.

**Tech Stack:** PHP 8 portable templates, inline SVG, CSS, Node.js contract tests, fixed-manifest staging packaging, SSH-based private staging deployment.

## Global Constraints

- Reuse existing inline SVG art; add no remote assets, scripts, forms, trackers, or network requests.
- Preserve the technician hero, location copy, heading order, CTA destinations, phone context, and route adapters.
- Render exactly one animated `door-open` illustration on each location page.
- Use `spring` for repair, `keypad` for opener service, and `door` for installation.
- Keep the final location CTA static while preserving animated final CTAs on non-location editorial pages.
- Preserve `prefers-reduced-motion` behavior and prevent horizontal overflow at 390 pixels.
- Deploy only to `https://danielj140.sg-host.com/`; production write authority remains false.
- Preserve unrelated and pre-existing worktree changes.

---

### Task 1: Add the visual-placement contracts

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`
- Modify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php`

**Interfaces:**
- Consumes: existing `twins_brand_door_art(string $kind, string $class, string $idSuffix): string`
- Produces: a static source contract and rendered-PHP assertions for the new location visual hierarchy

- [ ] **Step 1: Write the failing static contract**

Add `assets/css/twins-brand.css` to the contract inputs and append this test:

```js
const css = fs.readFileSync(path.join(root, 'assets/css/twins-brand.css'), 'utf8');

test('location pages move the animated door up and give every service a fixed visual', () => {
  assert.match(template, /twins-location-system/);
  assert.match(template, /twins_brand_door_art\('door-open', 'twins-location-system-art', 'location-system'\)/);
  assert.match(template, /'art' => 'spring'/);
  assert.match(template, /'art' => 'keypad'/);
  assert.match(template, /'art' => 'door'/);
  assert.match(template, /twins-location-service-art/);
  assert.match(template, /\$finalCtaArtKind = \$isLocation \? 'door' : 'door-open'/);
  assert.match(css, /\.twins-location-system/);
  assert.match(css, /\.twins-location-service-art/);
});
```

- [ ] **Step 2: Add rendered hierarchy assertions**

Immediately after the existing Rockford location assertions in `renderer-contract-harness.php`, add:

```php
$systemPosition = strpos($rockfordLocation, 'class="twins-location-system"');
$servicesPosition = strpos($rockfordLocation, 'class="twins-location-services"');
$expect(
    is_int($systemPosition) && is_int($servicesPosition) && $systemPosition < $servicesPosition,
    'Rockford must render the animated door system band before services'
);
$expect(
    substr_count($rockfordLocation, 'twins-brand-door-art--door-open') === 1,
    'Rockford must render exactly one animated door'
);
foreach (['spring', 'keypad', 'door'] as $artKind) {
    $expect(
        strpos($rockfordLocation, 'twins-brand-door-art twins-brand-door-art--' . $artKind . ' twins-location-service-art') !== false,
        'Rockford omitted service art: ' . $artKind
    );
}
```

- [ ] **Step 3: Run the focused contract and verify RED**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  --test website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: the new test fails because `twins-location-system`, the service art keys, and `$finalCtaArtKind` do not exist yet.

- [ ] **Step 4: Commit the contract**

```bash
git add website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs \
  website/twins-brand-experience/tests/php/renderer-contract-harness.php
git commit -m "test: define location garage door visuals"
```

Before committing, inspect `git diff --cached` and confirm only the approved location-page contract and renderer assertions are staged.

---

### Task 2: Render and style the upper door-system showcase

**Files:**
- Modify: `website/twins-brand-experience/templates/editorial.php`
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css`
- Modify: `website/twins-brand-experience/tests/contracts/site-unification.test.cjs`

**Interfaces:**
- Consumes: `twins_brand_door_art()` from `components/door-art.php` and `$isLocation` from the editorial template
- Produces: `.twins-location-system`, `.twins-location-system-art`, `.twins-location-service-art`, and `$finalCtaArtKind`

- [ ] **Step 1: Add fixed service art keys**

Extend the three `$locationServiceCards` entries in `templates/editorial.php`:

```php
[
    'title' => 'Garage Door Repair',
    'route' => 'repair',
    'art' => 'spring',
    'description' => 'We diagnose broken springs, damaged cables, worn rollers, track problems, noisy movement, uneven travel, and doors that will not open or close correctly.',
    'items' => ['Full door-system inspection', 'Repair options explained first', 'Balance and safety check'],
],
[
    'title' => 'Garage door opener service',
    'route' => 'opener-repair',
    'art' => 'keypad',
    'description' => 'We troubleshoot motors, remotes, wall controls, safety sensors, travel settings, drive systems, and the connection between the opener and the door.',
    'items' => ['Sensor and control diagnosis', 'Drive and travel inspection', 'Opener replacement options'],
],
[
    'title' => 'Garage door installation',
    'route' => 'installation',
    'art' => 'door',
    'description' => 'When a door is extensively damaged or no longer fits the home, we measure the opening and explain construction, insulation, window, and finish choices.',
    'items' => ['Opening measured before quoting', 'Door and track options explained', 'Complete operating-system setup'],
],
```

Require `components/door-art.php` once before the `<main>` element:

```php
<?php require_once dirname(__DIR__) . '/components/door-art.php'; ?>
```

- [ ] **Step 2: Insert the system band after the proof bar**

Place this location-only section between `.twins-location-proof` and `.twins-location-services`:

```php
<section class="twins-location-system" aria-labelledby="twins-location-system-title">
  <div class="twins-location-system-visual">
    <?= twins_brand_door_art('door-open', 'twins-location-system-art', 'location-system') ?>
  </div>
  <div>
    <span class="twins-brand-kicker">Built as one complete system</span>
    <h2 id="twins-location-system-title">Every part affects how the door moves.</h2>
    <p>The door, springs, cables, rollers, tracks, opener, controls, and safety equipment must work together for smooth, secure operation.</p>
  </div>
</section>
```

- [ ] **Step 3: Render one illustration in each service card**

Insert this as the first child of `.twins-location-service-card`, before its `<h3>`:

```php
<?= twins_brand_door_art(
    $serviceCard['art'],
    'twins-location-service-art',
    'location-service-' . $serviceCard['art']
) ?>
```

The service-card data is fixed locally, so the component receives only the three approved art keys.

- [ ] **Step 4: Make the location final CTA static**

Define the final art kind before the final CTA and remove the later duplicate `require_once`:

```php
<?php $finalCtaArtKind = $isLocation ? 'door' : 'door-open'; ?>
```

Render it with:

```php
<?= twins_brand_door_art($finalCtaArtKind, 'twins-brand-cta-art', 'editorial-final') ?>
```

This preserves the opening animation for trust and article editorial pages while making location-page final CTAs static.

- [ ] **Step 5: Add desktop system-band and service-art CSS**

Add the following to the location-page CSS block in `assets/css/twins-brand.css`:

```css
.twins-location-system {
  display: grid;
  grid-template-columns: minmax(190px, .42fr) minmax(0, 1fr);
  gap: clamp(26px, 5vw, 74px);
  align-items: center;
  padding: clamp(46px, 6vw, 82px) max(32px, calc((100vw - 1320px) / 2));
  color: var(--twins-white);
  background: var(--twins-navy-900);
  border-bottom: 4px solid var(--twins-gold);
}
.twins-location-system-visual {
  display: grid;
  place-items: center;
  min-height: 230px;
  padding: 24px;
  border: 3px solid var(--twins-gold);
  border-radius: 28px;
  background: rgba(3, 18, 43, .48);
  box-shadow: 14px 16px 0 rgba(255, 191, 47, .95);
}
.twins-location-system-art { width: min(100%, 250px); height: auto; }
.twins-location-system h2 {
  max-width: 720px;
  margin: 10px 0 16px;
  color: var(--twins-white);
  font-size: clamp(2.45rem, 5vw, 4.8rem);
  line-height: .96;
  text-transform: uppercase;
}
.twins-location-system p { max-width: 680px; margin: 0; color: #dce7f3; font-size: 1.08rem; }
.twins-location-service-art {
  width: 94px;
  height: 68px;
  margin: 0 0 22px;
  padding: 9px;
  border: 2px solid var(--twins-navy-900);
  border-radius: 14px;
  background: var(--twins-gold);
}
```

- [ ] **Step 6: Add the mobile composition**

Inside the existing location-page mobile breakpoint, add:

```css
.twins-location-system {
  grid-template-columns: 1fr;
  gap: 34px;
  padding: 48px 20px 58px;
}
.twins-location-system-visual {
  width: min(100%, 300px);
  min-height: 210px;
  margin-inline: auto;
  box-shadow: 9px 10px 0 var(--twins-gold);
}
.twins-location-system h2 { font-size: clamp(2.25rem, 12vw, 3.35rem); }
.twins-location-service-art { width: 82px; height: 60px; margin-bottom: 18px; }
```

Do not add a second animation rule. The existing reduced-motion rule for `.twins-brand-door-art--door-open .twins-da-curtain` must continue to cover the relocated illustration.

- [ ] **Step 7: Update the CSS hash contract**

Compute the new stylesheet hash:

```bash
shasum -a 256 website/twins-brand-experience/assets/css/twins-brand.css
```

Replace the exact expected SHA-256 prefix in `tests/contracts/site-unification.test.cjs` with the prefix emitted by that command.

- [ ] **Step 8: Run the focused contracts and verify GREEN**

Run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  --test website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs \
  website/twins-brand-experience/tests/contracts/site-unification.test.cjs
```

Expected: all focused tests pass.

- [ ] **Step 9: Commit the visual implementation**

```bash
git add website/twins-brand-experience/templates/editorial.php \
  website/twins-brand-experience/assets/css/twins-brand.css \
  website/twins-brand-experience/tests/contracts/site-unification.test.cjs
git commit -m "feat: add location door system visuals"
```

Because these files contain approved earlier location-page work, inspect the staged diff and ensure the commit represents the complete approved location-page implementation rather than discarding or hiding pre-existing changes.

---

### Task 3: Package, deploy, and visually verify staging

**Files:**
- Modify: `website/twins-brand-experience/manifests/staging-runtime.json`
- Modify: `website/twins-brand-experience/manifests/host-verification.json`
- Modify: `website/twins-brand-experience/tools/deploy-private-staging.mjs`
- Modify: `website/twins-brand-experience/tools/private-staging-deploy.php`
- Modify: `website/twins-brand-experience/tests/contracts/deployment-tool-contract.test.cjs`
- Modify: `website/twins-brand-experience/tests/contracts/package-contract.test.cjs`

**Interfaces:**
- Consumes: the verified template, stylesheet, PHP renderer harness, and fixed staging deployment tooling
- Produces: immutable transaction `staging-remediation-r23-20260722`, verified package metadata, and a live staging deployment

- [ ] **Step 1: Rotate the immutable staging transaction**

Replace every current `staging-remediation-r22-20260721` transaction literal in the six files listed above with:

```text
staging-remediation-r23-20260722
```

Do not change the fixed application identity, SSH port, web root, write-authority flags, or production boundary.

- [ ] **Step 2: Synchronize manifest size and SHA-256 entries**

Compute exact bytes and hashes for every changed file already present in either manifest:

```bash
wc -c \
  website/twins-brand-experience/assets/css/twins-brand.css \
  website/twins-brand-experience/templates/editorial.php \
  website/twins-brand-experience/tests/php/renderer-contract-harness.php \
  website/twins-brand-experience/tools/private-staging-deploy.php

shasum -a 256 \
  website/twins-brand-experience/assets/css/twins-brand.css \
  website/twins-brand-experience/templates/editorial.php \
  website/twins-brand-experience/tests/php/renderer-contract-harness.php \
  website/twins-brand-experience/tools/private-staging-deploy.php
```

Update the matching `size` and `sha256` fields in `staging-runtime.json` and `host-verification.json`. Then compute the staging manifest itself and update its entry in `host-verification.json`:

```bash
wc -c website/twins-brand-experience/manifests/staging-runtime.json
shasum -a 256 website/twins-brand-experience/manifests/staging-runtime.json
```

- [ ] **Step 3: Build and verify the closed packages**

From `website/twins-brand-experience`, run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs --check
```

Expected: `STAGING_PACKAGES_BUILT`, followed by `STAGING_PACKAGES_VERIFIED`; both results must report `productionWriteAuthority: false`.

- [ ] **Step 4: Run the complete local contract suite**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  --test tests/contracts/*.test.cjs
```

Run from `website/twins-brand-experience`. Expected: all tests pass with zero failures.

- [ ] **Step 5: Run the fixed remote dry run**

From `website/twins-brand-experience`, run:

```bash
TWINS_STAGE_SSH_TARGET='u2356-y8avsfoqgaqv@ssh.danielj140.sg-host.com' \
TWINS_STAGE_SSH_KEY='/Users/daniel/.ssh/twins_stage_deploy_20260717' \
TWINS_STAGE_SSH_HOSTKEY_SHA256='SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4' \
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  tools/deploy-private-staging.mjs --dry-run
```

Expected: `PRIVATE_STAGING_DRY_RUN_PASSED` for transaction r23.

- [ ] **Step 6: Capture exact rollback state and deploy**

Capture the current exact staging files:

```bash
TWINS_STAGE_SSH_TARGET='u2356-y8avsfoqgaqv@ssh.danielj140.sg-host.com' \
TWINS_STAGE_SSH_KEY='/Users/daniel/.ssh/twins_stage_deploy_20260717' \
TWINS_STAGE_SSH_HOSTKEY_SHA256='SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4' \
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  tools/deploy-private-staging.mjs --capture-expected-old
```

Then deploy the verified candidate:

```bash
TWINS_STAGE_SSH_TARGET='u2356-y8avsfoqgaqv@ssh.danielj140.sg-host.com' \
TWINS_STAGE_SSH_KEY='/Users/daniel/.ssh/twins_stage_deploy_20260717' \
TWINS_STAGE_SSH_HOSTKEY_SHA256='SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4' \
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  tools/deploy-private-staging.mjs --deploy
```

Expected statuses:

```text
EXPECTED_OLD_CAPTURED
PRIVATE_STAGING_DEPLOYED
```

Both results must report the same manifest and package identities from the r23 dry run and `productionWriteAuthority: false`.

- [ ] **Step 7: Verify the live Rockford page**

Open:

```text
https://danielj140.sg-host.com/il/location/rockford/?r23=1
```

At the default desktop viewport and at 390 by 844 pixels, confirm:

- `.twins-location-system` appears directly after the yellow proof bar.
- The system band contains the only opening-door animation.
- The service cards show spring, keypad, and closed-door art.
- The final CTA shows a static closed door.
- `document.documentElement.scrollWidth === document.documentElement.clientWidth`.
- Existing Rockford copy, five FAQs, branch address, phone, and CTAs remain intact.

- [ ] **Step 8: Commit deployment metadata**

```bash
git add website/twins-brand-experience/manifests/staging-runtime.json \
  website/twins-brand-experience/manifests/host-verification.json \
  website/twins-brand-experience/tools/deploy-private-staging.mjs \
  website/twins-brand-experience/tools/private-staging-deploy.php \
  website/twins-brand-experience/tests/contracts/deployment-tool-contract.test.cjs \
  website/twins-brand-experience/tests/contracts/package-contract.test.cjs
git commit -m "chore: package location door visuals for staging"
```

Inspect the staged diff before committing and preserve all unrelated worktree changes.
