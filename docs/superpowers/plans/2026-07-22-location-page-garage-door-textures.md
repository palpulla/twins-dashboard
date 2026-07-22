# Location Page Garage Door Textures Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add dimensional, garage-door-specific panel framing to every location-page section while keeping the central reading area clean and preserving accessibility, responsiveness, and performance.

**Architecture:** Implement the approved texture as a CSS-only system on the existing shared location-page section boundaries. Shared pseudo-elements draw two recessed panel frames with gradients, borders, inset shadows, and optional gold track edges; section-level custom properties control light/dark variants and intensity. No template markup, assets, JavaScript, data flow, or city-specific styling will be added.

**Tech Stack:** CSS custom properties and pseudo-elements, Node.js contract tests, Playwright browser verification, fixed-manifest staging packaging, SSH-based private staging deployment.

## Global Constraints

- Apply the texture system to Twins Garage Doors location pages only.
- Use the approved **Framed Door Panels** direction inspired by the supplied Zoom background.
- Keep the center behind headings, copy, cards, links, and CTAs visually calm and high-contrast.
- Use CSS-only decoration; add no raster assets, external requests, JavaScript, animation, interaction, or content changes.
- Decorative layers must use `pointer-events: none`, remain outside the accessibility tree, and degrade to the current flat backgrounds when unsupported.
- Preserve all current foreground/background contrast and reduced-motion behavior.
- Prevent horizontal overflow at 390, 360, and 320 pixels.
- Do not change the location template unless an existing section boundary cannot provide a safe stacking context.
- Deploy only to `https://danielj140.sg-host.com/`; production write authority remains false.
- Preserve unrelated and pre-existing worktree changes.

---

### Task 1: Define the garage-door texture contract

**Files:**
- Modify: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`

**Interfaces:**
- Consumes: the location-page stylesheet source loaded as `css`
- Produces: a static contract for the shared panel variables, decorative safety boundary, section rhythm, mobile simplification, and markup-free implementation

- [ ] **Step 1: Add the failing texture contract**

Append this test after the existing location visual test:

```js
test('location sections use accessible CSS-only garage door panel framing', () => {
  for (const section of [
    'system',
    'services',
    'guidance',
    'process',
    'branch',
    'nearby',
    'faq',
  ]) {
    assert.match(css, new RegExp(`\\.twins-location-${section}`),
      `location texture CSS omits ${section}`);
  }

  for (const token of [
    '--twins-location-panel-width',
    '--twins-location-panel-opacity',
    '--twins-location-panel-line',
    '--twins-location-panel-track',
  ]) {
    assert.match(css, new RegExp(token), `location texture CSS omits ${token}`);
  }

  assert.match(css, /pointer-events:\s*none/);
  assert.match(css, /isolation:\s*isolate/);
  assert.match(css, /\.twins-location-system[\s\S]*\.twins-location-faq[\s\S]*::before/);
  assert.match(css, /@media \(max-width: 768px\)[\s\S]*--twins-location-panel-width:\s*150px/);
  assert.match(css, /@media \(max-width: 480px\)[\s\S]*--twins-location-panel-width:\s*120px/);
  assert.doesNotMatch(template, /twins-location-panel/,
    'decorative location textures must not add template markup');
});
```

- [ ] **Step 2: Run the focused contract and verify RED**

Run from the repository root:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  --test website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: six existing tests pass and the new seventh test fails because `--twins-location-panel-width` and the remaining texture tokens do not exist.

- [ ] **Step 3: Commit the failing contract**

```bash
git add website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
git diff --cached --check
git commit -m "test: define location garage door textures"
```

Inspect `git diff --cached` before committing and confirm only the new location texture contract is staged.

---

### Task 2: Implement the responsive panel-framing system

**Files:**
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css`
- Modify: `website/twins-brand-experience/tests/contracts/site-unification.test.cjs`
- Test: `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`

**Interfaces:**
- Consumes: existing `.twins-location-*` section boundaries and Twins color custom properties
- Produces: `--twins-location-panel-*` section variables plus safe `::before` and `::after` decorative layers

- [ ] **Step 1: Add the shared stacking and panel variables**

Insert this block after `.twins-location-system p` and before the shared location section-padding rule:

```css
.twins-location-system,
.twins-location-services,
.twins-location-guidance,
.twins-location-process,
.twins-location-branch,
.twins-location-nearby,
.twins-location-faq {
  --twins-location-panel-width: clamp(190px, 18vw, 320px);
  --twins-location-panel-opacity: .24;
  --twins-location-panel-base: rgba(240, 232, 215, .78);
  --twins-location-panel-line: rgba(7, 29, 59, .18);
  --twins-location-panel-highlight: rgba(255, 255, 255, .72);
  --twins-location-panel-shadow: rgba(7, 29, 59, .11);
  --twins-location-panel-track: transparent;
  position: relative;
  isolation: isolate;
  overflow: hidden;
}
.twins-location-system > *,
.twins-location-services > *,
.twins-location-guidance > *,
.twins-location-process > *,
.twins-location-branch > *,
.twins-location-nearby > *,
.twins-location-faq > * {
  position: relative;
  z-index: 1;
}
```

The existing section backgrounds remain authoritative. This block only establishes bounded stacking and shared texture variables.

- [ ] **Step 2: Draw the two recessed side panels**

Add this immediately after the shared variables:

```css
.twins-location-system::before,
.twins-location-system::after,
.twins-location-services::before,
.twins-location-services::after,
.twins-location-guidance::before,
.twins-location-guidance::after,
.twins-location-process::before,
.twins-location-process::after,
.twins-location-branch::before,
.twins-location-branch::after,
.twins-location-nearby::before,
.twins-location-nearby::after,
.twins-location-faq::before,
.twins-location-faq::after {
  content: '';
  position: absolute;
  z-index: 0;
  top: 8%;
  bottom: 8%;
  width: var(--twins-location-panel-width);
  pointer-events: none;
  opacity: var(--twins-location-panel-opacity);
  border: 2px solid var(--twins-location-panel-line);
  background:
    linear-gradient(var(--twins-location-panel-line) 0 0) 50% 12% / 72% 2px no-repeat,
    linear-gradient(var(--twins-location-panel-line) 0 0) 50% 44% / 72% 2px no-repeat,
    linear-gradient(var(--twins-location-panel-line) 0 0) 14% 28% / 2px 32% no-repeat,
    linear-gradient(var(--twins-location-panel-line) 0 0) 86% 28% / 2px 32% no-repeat,
    linear-gradient(var(--twins-location-panel-line) 0 0) 50% 56% / 72% 2px no-repeat,
    linear-gradient(var(--twins-location-panel-line) 0 0) 50% 88% / 72% 2px no-repeat,
    linear-gradient(var(--twins-location-panel-line) 0 0) 14% 72% / 2px 32% no-repeat,
    linear-gradient(var(--twins-location-panel-line) 0 0) 86% 72% / 2px 32% no-repeat,
    linear-gradient(145deg, var(--twins-location-panel-highlight), var(--twins-location-panel-base));
  box-shadow:
    inset 4px 4px 0 var(--twins-location-panel-highlight),
    inset -5px -5px 0 var(--twins-location-panel-shadow);
}
.twins-location-system::before,
.twins-location-services::before,
.twins-location-guidance::before,
.twins-location-process::before,
.twins-location-branch::before,
.twins-location-nearby::before,
.twins-location-faq::before {
  left: 0;
  transform: translateX(-58%);
  border-right: 4px solid var(--twins-location-panel-track);
  border-radius: 0 28px 28px 0;
}
.twins-location-system::after,
.twins-location-services::after,
.twins-location-guidance::after,
.twins-location-process::after,
.twins-location-branch::after,
.twins-location-nearby::after,
.twins-location-faq::after {
  right: 0;
  transform: translateX(58%);
  border-left: 4px solid var(--twins-location-panel-track);
  border-radius: 28px 0 0 28px;
}
```

The eight positioned line layers draw two recessed rectangular impressions inside each partially off-canvas side slab. The visible panel width remains outside the central content shell.

- [ ] **Step 3: Add section-specific light, dark, and gold-track variants**

Add this after the panel drawing rules:

```css
.twins-location-services { --twins-location-panel-opacity: .26; }
.twins-location-guidance {
  --twins-location-panel-opacity: .34;
  --twins-location-panel-base: rgba(246, 241, 231, .9);
}
.twins-location-process {
  --twins-location-panel-opacity: .24;
  --twins-location-panel-track: rgba(255, 191, 47, .82);
}
.twins-location-nearby { --twins-location-panel-opacity: .2; }
.twins-location-faq {
  --twins-location-panel-opacity: .16;
  --twins-location-panel-base: rgba(255, 255, 255, .88);
}
.twins-location-system,
.twins-location-branch {
  --twins-location-panel-opacity: .32;
  --twins-location-panel-base: rgba(6, 27, 57, .82);
  --twins-location-panel-line: rgba(169, 195, 224, .22);
  --twins-location-panel-highlight: rgba(89, 124, 165, .2);
  --twins-location-panel-shadow: rgba(1, 12, 30, .46);
}
.twins-location-branch {
  --twins-location-panel-opacity: .38;
  --twins-location-panel-track: rgba(255, 191, 47, .9);
}
.twins-location-guidance::before,
.twins-location-process::after,
.twins-location-nearby::before,
.twins-location-faq::after { opacity: calc(var(--twins-location-panel-opacity) + .06); }
```

This varies strength and alternates the dominant edge. The final four-rule selector adds only a bounded numeric opacity adjustment; it does not move the decoration toward the content.

- [ ] **Step 4: Add tablet and mobile simplification rules**

Inside the existing `@media (max-width: 1024px)` location block, append:

```css
  .twins-location-system::before,
  .twins-location-system::after,
  .twins-location-services::before,
  .twins-location-services::after,
  .twins-location-guidance::before,
  .twins-location-guidance::after,
  .twins-location-process::before,
  .twins-location-process::after,
  .twins-location-branch::before,
  .twins-location-branch::after,
  .twins-location-nearby::before,
  .twins-location-nearby::after,
  .twins-location-faq::before,
  .twins-location-faq::after {
    width: 190px;
    opacity: .18;
  }
```

Inside the existing `@media (max-width: 768px)` location block, append:

```css
  .twins-location-system,
  .twins-location-services,
  .twins-location-guidance,
  .twins-location-process,
  .twins-location-branch,
  .twins-location-nearby,
  .twins-location-faq { --twins-location-panel-width: 150px; }
  .twins-location-system::before,
  .twins-location-services::before,
  .twins-location-guidance::before,
  .twins-location-process::before,
  .twins-location-branch::before,
  .twins-location-nearby::before,
  .twins-location-faq::before {
    top: 14px;
    bottom: auto;
    height: 132px;
    transform: translateX(-70%);
    border-right-color: transparent;
  }
  .twins-location-system::after,
  .twins-location-services::after,
  .twins-location-guidance::after,
  .twins-location-process::after,
  .twins-location-branch::after,
  .twins-location-nearby::after,
  .twins-location-faq::after {
    top: auto;
    bottom: 14px;
    height: 132px;
    transform: translateX(70%);
    border-left-color: transparent;
  }
```

Inside the existing `@media (max-width: 480px)` location block, append:

```css
  .twins-location-system,
  .twins-location-services,
  .twins-location-guidance,
  .twins-location-process,
  .twins-location-branch,
  .twins-location-nearby,
  .twins-location-faq { --twins-location-panel-width: 120px; }
  .twins-location-system::before,
  .twins-location-system::after,
  .twins-location-services::before,
  .twins-location-services::after,
  .twins-location-guidance::before,
  .twins-location-guidance::after,
  .twins-location-process::before,
  .twins-location-process::after,
  .twins-location-branch::before,
  .twins-location-branch::after,
  .twins-location-nearby::before,
  .twins-location-nearby::after,
  .twins-location-faq::before,
  .twins-location-faq::after {
    height: 108px;
    opacity: .14;
  }
```

- [ ] **Step 5: Run the focused contract and verify GREEN**

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  --test website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs
```

Expected: seven tests pass, zero fail.

- [ ] **Step 6: Synchronize the bounded stylesheet version contract**

The asset-version contract pins the first 16 characters of the stylesheet SHA-256 digest. Update only its CSS value in `tests/contracts/site-unification.test.cjs`:

```js
  assert.deepEqual(versions, {
    css: 'f3d9198e6787465e',
    familyCss: '8bfb855366111b32',
    js: 'a27a7a219e280a80',
    builderJs: 'fb10e274bd3fcbf6',
  });
```

The complete manifest suite remains intentionally red until Task 3 synchronizes both manifests. This sequence keeps the CSS implementation commit separate from deployment metadata.

- [ ] **Step 7: Run the focused source contracts**

From `website/twins-brand-experience`, run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  --test tests/contracts/location-page-overhaul-contract.test.cjs \
  tests/contracts/site-unification.test.cjs
```

Expected: all location and site-unification source contracts pass with zero failures.

- [ ] **Step 8: Run the local browser safety suite**

From `website/twins-brand-experience`, run:

```bash
env PATH=/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin \
  /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  node_modules/@playwright/test/cli.js test tests/browser/site-unification.spec.cjs
```

Expected: all site-unification tests pass with zero overflow, contrast, or interaction failures.

- [ ] **Step 9: Commit the texture implementation**

```bash
git add website/twins-brand-experience/assets/css/twins-brand.css \
  website/twins-brand-experience/tests/contracts/site-unification.test.cjs
git diff --cached --check
git commit -m "feat: add location garage door textures"
```

Inspect the staged diff and confirm it contains only the CSS texture system and its exact derived short-hash contract. There must be no template, JavaScript, content, or unrelated asset changes.

---

### Task 3: Package the immutable staging release

**Files:**
- Modify: `website/twins-brand-experience/manifests/staging-runtime.json`
- Modify: `website/twins-brand-experience/manifests/host-verification.json`
- Modify: `website/twins-brand-experience/tools/deploy-private-staging.mjs`
- Modify: `website/twins-brand-experience/tools/private-staging-deploy.php`
- Modify: `website/twins-brand-experience/tests/contracts/deployment-tool-contract.test.cjs`
- Modify: `website/twins-brand-experience/tests/contracts/package-contract.test.cjs`
- Temporary verification helper: `/tmp/twins-location-texture-npm/npm`

**Interfaces:**
- Consumes: the verified `twins-brand.css` texture implementation and the existing fixed staging deployment boundary
- Produces: immutable transaction `staging-remediation-r27-20260722` and closed, hash-pinned staging packages

**Execution recovery:** The pre-activation r24 deploy attempt stopped after uploading 79 of 85 verification files at the former 120-second transport ceiling. The r25 dry run also stopped safely before activation at the extended 180-second ceiling after uploading 53 of 85 verification files. Local evidence showed the text-heavy host-verification package compresses from 3.4 MB to 605 KB, so a failing deployment-tool contract was added before enabling fixed SSH/SCP compression with `'-C'`. That made the r26 dry run pass, but its deploy attempt stopped safely before activation after the mostly incompressible 9.6 MB runtime reached only 52 of 87 candidate files. A read-only capability check confirmed rsync 3.4.4 on the host, and a failing contract now requires the fixed candidate upload to use rsync with `--copy-dest=${WEB_ROOT}` so byte-identical live files are copied server-side while only package deltas cross the slow transport. The bounded three-minute default remains in place. Because deploy attempts are intentionally single-use, all remaining steps use the fresh r27 transaction; r24 through r26 never reached activation.

- [ ] **Step 1: Rotate the immutable staging transaction**

Replace every current `staging-remediation-r23-20260722` transaction literal in the six files listed above with:

```text
staging-remediation-r27-20260722
```

Do not change the fixed application identity, SSH port, remote web root, write-authority flags, or production boundary.

- [ ] **Step 2: Synchronize the stylesheet manifest records**

Compute the exact stylesheet size and SHA-256 digest:

```bash
wc -c website/twins-brand-experience/assets/css/twins-brand.css
shasum -a 256 website/twins-brand-experience/assets/css/twins-brand.css
```

Update the matching `size` and `sha256` entry for `twins-brand-experience/assets/css/twins-brand.css` in both `staging-runtime.json` and `host-verification.json`.

Then compute the updated staging manifest identity:

```bash
wc -c website/twins-brand-experience/manifests/staging-runtime.json
shasum -a 256 website/twins-brand-experience/manifests/staging-runtime.json
```

Update the `staging-runtime.json` record in `host-verification.json` with those exact values.

- [ ] **Step 3: Synchronize the changed deploy-harness record**

Because the transaction rotation changes `private-staging-deploy.php`, compute:

```bash
wc -c website/twins-brand-experience/tools/private-staging-deploy.php
shasum -a 256 website/twins-brand-experience/tools/private-staging-deploy.php
```

Update its matching record in `host-verification.json`. The PHP deploy harness is verification-only and does not appear in `staging-runtime.json`. Then recompute and update the staging-manifest identity in `host-verification.json` once more.

- [ ] **Step 4: Build and verify the closed packages**

From `website/twins-brand-experience`, run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-packages.mjs --check
```

Expected: `STAGING_PACKAGES_BUILT`, followed by `STAGING_PACKAGES_VERIFIED`. Both results must report `writeAuthority: false`, `productionWriteAuthority: false`, and `productionPackage: "DEFERRED"`.

- [ ] **Step 5: Run the complete contract and repository gates**

From `website/twins-brand-experience`, run:

```bash
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  --test tests/contracts/*.test.cjs
```

The bundled runtime does not include an `npm` executable. Create `/tmp/twins-location-texture-npm/npm` with this exact content using `apply_patch`, then make it executable:

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
chmod +x /tmp/twins-location-texture-npm/npm
env PATH=/tmp/twins-location-texture-npm:/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin \
  /Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  tools/check-repository.mjs
```

Expected: `REPOSITORY_CHECK_PASSED` with `writeAuthority: false` and `productionWriteAuthority: false`.

- [ ] **Step 6: Commit deployment metadata**

```bash
git add website/twins-brand-experience/manifests/staging-runtime.json \
  website/twins-brand-experience/manifests/host-verification.json \
  website/twins-brand-experience/tools/deploy-private-staging.mjs \
  website/twins-brand-experience/tools/private-staging-deploy.php \
  website/twins-brand-experience/tests/contracts/deployment-tool-contract.test.cjs \
  website/twins-brand-experience/tests/contracts/package-contract.test.cjs
git diff --cached --check
git commit -m "chore: package location textures for staging"
```

Inspect the staged diff before committing and confirm it contains only the r27 transaction rotation, fixed delta transport, and exact package identities.

---

### Task 4: Deploy and visually verify the location texture release

**Files:**
- Verify: `website/twins-brand-experience/dist/`
- Verify: live private staging at `https://danielj140.sg-host.com/`

**Interfaces:**
- Consumes: immutable r27 staging package, fixed SSH identity, and exact expected-old capture flow
- Produces: a live, rollback-safe location texture release plus desktop/mobile visual evidence

- [ ] **Step 1: Run the fixed remote dry run**

From `website/twins-brand-experience`, run:

```bash
TWINS_STAGE_SSH_TARGET='u2356-y8avsfoqgaqv@ssh.danielj140.sg-host.com' \
TWINS_STAGE_SSH_KEY='/Users/daniel/.ssh/twins_stage_deploy_20260717' \
TWINS_STAGE_SSH_HOSTKEY_SHA256='SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4' \
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  tools/deploy-private-staging.mjs --dry-run
```

Expected: `PRIVATE_STAGING_DRY_RUN_PASSED` for transaction r27 with `productionWriteAuthority: false`.

- [ ] **Step 2: Capture exact rollback state**

```bash
TWINS_STAGE_SSH_TARGET='u2356-y8avsfoqgaqv@ssh.danielj140.sg-host.com' \
TWINS_STAGE_SSH_KEY='/Users/daniel/.ssh/twins_stage_deploy_20260717' \
TWINS_STAGE_SSH_HOSTKEY_SHA256='SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4' \
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  tools/deploy-private-staging.mjs --capture-expected-old
```

Expected: `EXPECTED_OLD_CAPTURED`, with the same package and manifest identities reported by the dry run.

- [ ] **Step 3: Deploy the verified candidate**

```bash
TWINS_STAGE_SSH_TARGET='u2356-y8avsfoqgaqv@ssh.danielj140.sg-host.com' \
TWINS_STAGE_SSH_KEY='/Users/daniel/.ssh/twins_stage_deploy_20260717' \
TWINS_STAGE_SSH_HOSTKEY_SHA256='SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4' \
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node \
  tools/deploy-private-staging.mjs --deploy
```

Expected: `PRIVATE_STAGING_DEPLOYED`, with `writeAuthority: false` and `productionWriteAuthority: false`.

- [ ] **Step 4: Inspect Rockford at desktop width**

Open:

```text
https://danielj140.sg-host.com/il/location/rockford/?r27=1
```

At the default desktop viewport, verify:

- light sections show recessed cream/white side panels;
- the system and branch bands show dark navy panel framing;
- process and branch include restrained gold track edges;
- panels remain at the edges and do not compete with headings, copy, cards, links, or CTAs;
- existing technician photo, animated system door, service art, Rockford copy, five FAQs, phone, address, and CTA destinations remain unchanged;
- `document.documentElement.scrollWidth === document.documentElement.clientWidth`;
- the browser console contains no errors.

- [ ] **Step 5: Inspect Rockford at 390 by 844 pixels**

At 390 by 844 pixels, verify:

- full-height side slabs simplify into small top-left and bottom-right panel fragments;
- gold track edges are removed from the mobile fragments;
- no decorative layer covers text, art, cards, links, or buttons;
- `document.documentElement.scrollWidth === document.documentElement.clientWidth`;
- all buttons and links retain their existing hit areas and behavior.

- [ ] **Step 6: Spot-check one additional shared-template location**

Open:

```text
https://danielj140.sg-host.com/il/location/loves-park/?r27=1
```

Confirm the same section texture system appears without Rockford-specific selectors or missing sections. Verify the Loves Park content remains distinct and readable.

- [ ] **Step 7: Run final branch-integrity checks**

From the repository root, run:

```bash
git diff --check
git status --short
git log -5 --oneline
```

Expected: no unstaged implementation or deployment files, no whitespace errors, and the texture contract, implementation, and r27 package commits at the top of the feature branch. Preserve the worktree after completion because local `main` contains unrelated uncommitted work.
