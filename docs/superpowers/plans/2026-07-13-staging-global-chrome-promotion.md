# Staging Global Chrome Promotion Implementation Plan

**Status:** Implementation and whole-site verification complete on 2026-07-13. Repository publication is pending this milestone commit; temporary staging access remains intentionally active under Daniel's expanded authorization while the full staging overhaul continues, and will be removed at that program's final handoff.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Promote Claude's already-built header `7336`, saved menu `7333`, and footer `7344` from page-6065-only canary conditions to the entire private main staging site, while preserving originals `36`, `305`, and `1409` as an exact rollback.

**Architecture:** Add a staging-only WP-CLI transition tool with fixed document IDs, fixed staging identity, fresh template fingerprints, exact expected-old condition states, and compensating rollback. The tool uses Elementor Pro's installed `Conditions_Manager::save_conditions()` API, which updates post meta and regenerates `elementor_pro_theme_builder_conditions`; it never edits Elementor content or production. After the switch, clear generated Elementor CSS, flush SiteGround cache, run focused responsive browser QA, and run a complete same-origin crawl.

**Tech Stack:** WordPress multisite, WP-CLI, PHP, Elementor/Elementor Pro Theme Builder, Node.js `node:test`, SiteGround private staging, browser QA.

## Global Constraints

- Operate only on `https://danielj140.sg-host.com`, main-site blog ID `1`.
- Require `WP_ENVIRONMENT_TYPE === 'staging'` and `TWINS_STAGING_SAFETY === true` before any state read or write.
- Never access or modify `twinsgaragedoors.com`, DNS, WordPress production, SiteGround production files, real leads, email, analytics, ads, chat, CRM, or production integrations.
- Keep Basic Auth, noindex, CSP, disabled cron, outbound transport blocks, and the curated rendering-plugin allowlist unchanged.
- Do not enable WPCode or install the repository-only door-builder candidate.
- Treat live staging database documents `7336`, `7333`, and `7344` as the only source of truth for Claude's final canary; historical repository CSS/exports are reference-only.
- Preserve Elementor content, settings, titles, statuses, template types, element IDs, links, menu bindings, and document hashes byte-for-byte; change only `_elementor_conditions` and Elementor's derived conditions cache.
- Perform each transition once. Do not automatically retry a failed write. On a partial transition, run the explicit compensating state once and stop for review.
- Preserve footer `2179` on contact page `2123`; do not activate dormant alternate header `2163`.
- Production publication remains out of scope.

---

### Task 1: Add the fail-closed condition transition contract

**Files:**
- Create: `website/staging-safety/tools/staging-chrome-transition.php`
- Create: `website/staging-safety/tests/staging-chrome-transition-harness.php`
- Modify: `website/staging-safety/tests/staging-safety.test.cjs`

**Interfaces:**
- Consumes: WordPress functions `home_url()`, `get_current_blog_id()`, `get_post()`, `get_post_meta()` and Elementor Pro `ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()`.
- Produces: `twins_staging_chrome_snapshot(): array`, `twins_staging_chrome_classify(array $conditions): string`, `twins_staging_chrome_transition(string $mode, bool $dryRun): array`, and JSON CLI output with `productionWriteAuthority:false`, `stagingMutation`, `mode`, `beforeState`, `afterState`, `projectedState`, `changedDocumentIds`, and `projectedDocumentIds`.

- [x] **Step 1: Write failing contract tests**

Add Node assertions that require the new tool to contain all fixed IDs, the exact staging host/blog/environment gates, all fresh SHA-256 fingerprints, the canary/global/original condition maps, one-pass save operations, compensating rollback, and a constant `productionWriteAuthority:false`. Require the source not to contain `twinsgaragedoors.com`, caller-selected IDs/hosts, WPCode activation, HTTP requests, mail, or form submission.

Add a PHP harness that stubs posts/meta and verifies:

```php
assert(twins_staging_chrome_classify($canary) === 'CANARY');
assert(twins_staging_chrome_classify($global) === 'GLOBAL');
assert(twins_staging_chrome_classify($original) === 'ORIGINAL');
assert(twins_staging_chrome_classify($unknown) === 'UNKNOWN');
assert(twins_staging_chrome_target_conditions('promote') === $global);
assert(twins_staging_chrome_target_conditions('restore-canary') === $canary);
assert(twins_staging_chrome_target_conditions('rollback') === $original);
```

- [x] **Step 2: Run tests and verify the missing tool fails**

Run:

```bash
node --test website/staging-safety/tests/staging-safety.test.cjs
```

Expected: FAIL because `website/staging-safety/tools/staging-chrome-transition.php` does not exist.

- [x] **Step 3: Implement the fixed transition tool**

Use this immutable manifest:

```php
$manifest = [
    36   => ['title' => 'Header', 'type' => 'header', 'dataSha256' => 'f433dcb2b40578ee75394c486e7c13b987dc9f0cc20d9c83ab2d9c195996072d'],
    305  => ['title' => 'POP Menu Template', 'type' => 'section', 'dataSha256' => '4df9f5ae619f65b8eb4fdb674ee0fffa7b21d4f4ba3577509f1aa1d6b5360341'],
    7333 => ['title' => 'UNIT 1 DEP — POP MENU 305 twx2 — 2026-07-10', 'type' => 'section', 'dataSha256' => 'd00c1141386ddcb162200d0767741cd46901336a07e58b0fac2be3fe77605c8d'],
    7336 => ['title' => 'UNIT 1 CANARY — Header 36 twx2 — 2026-07-10', 'type' => 'header', 'dataSha256' => 'f158f14cc66da49e7621d0002da7536c38a34e5103abc54e2f83e155e9a743c0'],
    1409 => ['title' => 'Footer', 'type' => 'footer', 'dataSha256' => '4db2fe9f8f1fd6772a1b2908faafba3aa3a093a4b6a603ee9465cdb9263be296'],
    7344 => ['title' => 'UNIT 2 CANARY — Footer 1409 twx2 — 2026-07-10', 'type' => 'footer', 'dataSha256' => '4db2fe9f8f1fd6772a1b2908faafba3aa3a093a4b6a603ee9465cdb9263be296'],
    2163 => ['title' => 'Header Contact Us', 'type' => 'header', 'dataSha256' => '0928a31330c97748fa522910fe065bdc36e8f0b3b57c4aebf472e271dc19b7c7'],
    2179 => ['title' => 'Footer Contact Us', 'type' => 'footer', 'dataSha256' => 'a1b6a10f7aa12bbab7d138ca71cf6e30d73e8adf8f9f2b36985f459a8bdeef32'],
];
```

Normalize missing/empty conditions to `[]` and use exact maps:

```php
$canary = [36 => ['include/general', 'exclude/singular/page/6065'], 305 => [], 7333 => [], 7336 => ['include/singular/page/6065'], 1409 => ['include/general', 'exclude/singular/page/6065'], 7344 => ['include/singular/page/6065'], 2163 => [], 2179 => ['include/singular/page/2123']];
$global = [36 => [], 305 => [], 7333 => [], 7336 => ['include/general'], 1409 => [], 7344 => ['include/general'], 2163 => [], 2179 => ['include/singular/page/2123']];
$original = [36 => ['include/general'], 305 => [], 7333 => [], 7336 => [], 1409 => ['include/general'], 7344 => [], 2163 => [], 2179 => ['include/singular/page/2123']];
```

Call `save_conditions()` with slash segments converted to ordered arrays. Promotion order is `7336`, `36`, `7344`, `1409`; rollback order is `36`, `7336`, `1409`, `7344`. Verify read-back after every call. If a promotion operation fails, apply the canary map once, verify it, report `TRANSITION_COMPENSATED`, and exit nonzero.

Require every template to remain `publish` in every snapshot. If a
`restore-canary` or `rollback` operation fails after any write is attempted,
apply the exact `GLOBAL` map once as its compensating state, verify it, and exit
nonzero. A compensation preflight read failure remains conservative:
`stagingMutation:true`, `afterState:"UNKNOWN"`, and
`TRANSITION_COMPENSATION_FAILED`. A failure before any write is attempted must
not compensate or overwrite independently drifted state.

Read `TWINS_STAGING_CHROME_DRY_RUN` as an exact `0`/`1` flag. A dry run performs every identity, fingerprint, state, and target calculation but never calls `save_conditions()`; it reports the actual unchanged `afterState`, `stagingMutation:false`, no `changedDocumentIds`, and the target only in `projectedState`/`projectedDocumentIds`. A real `promote`, `restore-canary`, or `rollback` reports `stagingMutation:true` only after the target state is verified. `status` is always read-only and has no projected changes.

- [x] **Step 4: Run local tests**

Run:

```bash
node --test website/staging-safety/tests/staging-safety.test.cjs
git diff --check
```

Expected: all executable tests pass; PHP harness may skip locally only because PHP is unavailable.

- [x] **Step 5: Run the mandatory remote PHP harness**

Copy only the tool and harness to `/home/customer/staging-safety/`, then run the harness with the host PHP binary. Expected: `STAGING_CHROME_TRANSITION_HARNESS_OK` and PHP lint success.

### Task 2: Capture exact rollback evidence and preflight

**Files:**
- Create remotely outside webroot: `/home/customer/staging-safety/chrome-before-20260713.json`
- Create remotely outside webroot: `/home/customer/staging-safety/before-global-chrome-20260713.sql.gz`

**Interfaces:**
- Consumes: Task 1 status mode and existing Basic Auth staging pages.
- Produces: mode-600 backup artifacts and a `CANARY` preflight receipt.

- [x] **Step 1: Run status mode**

Run the tool with `TWINS_STAGING_CHROME_MODE=status`. Require `beforeState:"CANARY"`, `productionWriteAuthority:false`, `stagingMutation:false`, all eight fingerprints exact, and no changed IDs.

- [x] **Step 2: Preserve exact metadata and database**

Write the complete eight-document snapshot plus current `elementor_pro_theme_builder_conditions` option to the JSON artifact. Create a new compressed `mysqldump` outside webroot using `--single-transaction --quick --skip-lock-tables`; refuse to overwrite an existing file. Set mode `600`, record SHA-256, and run `gzip -t`.

- [x] **Step 3: Verify rendered canary/control identity**

Require:

```text
/clopay-gallery-steel/ -> header 7336, nested saved section 7333, footer 7344
/ -> header 36, footer 1409
/garage-door-services/ -> header 36, footer 1409
/contact-us/ -> header 36, footer 2179
```

Every page must return `200`, Basic Auth must remain `401` without credentials, and all authenticated responses must carry the staging CSP and `X-Robots-Tag` headers.

### Task 3: Promote Claude's canary chrome globally on staging

**Files:**
- Modify remotely through supported Elementor condition APIs only: `_elementor_conditions` for `7336`, `36`, `7344`, `1409`
- Derived cache: WordPress option `elementor_pro_theme_builder_conditions`

**Interfaces:**
- Consumes: exact `CANARY` receipt from Task 2.
- Produces: exact `GLOBAL` condition state, with originals published but conditionless.

- [x] **Step 1: Execute promotion once**

Run `TWINS_STAGING_CHROME_MODE=promote wp eval-file ...`. Expected JSON: `afterState:"GLOBAL"`, `changedDocumentIds:[7336,36,7344,1409]`, `productionWriteAuthority:false`, `stagingMutation:true`.

- [x] **Step 2: Clear generated presentation caches**

Run Elementor's installed CSS cache clear once, confirm clone CSS files `post-7336.css`, `post-7333.css`, and `post-7344.css` return `200`, then flush SiteGround Dynamic Cache once. Do not enable any cache plugin.

- [x] **Step 3: Verify global resolution**

Require header `7336` and saved menu `7333` on home, service, post, catalog, and contact controls. Require footer `7344` everywhere except `/contact-us/`, which must retain footer `2179`. Require no page to render originals `36`, `305`, or `1409` and no page to render duplicate header/footer location elements.

### Task 4: Responsive and behavioral QA

**Files:**
- Create: `/private/tmp/staging-global-chrome-browser-qa.json`

**Interfaces:**
- Consumes: global staging chrome state.
- Produces: desktop/mobile evidence for the global header, menu, and footer.

- [x] **Step 1: Test representative URLs at fixed widths**

Test `/`, `/garage-door-services/`, `/clopay-gallery-steel/`, `/contact-us/`, `/blog/`, `/wi/`, and `/ky/` at `390`, `768`, `1024`, `1201`, `1366`, and `1600` pixels. Main pages must use clone `7336/7333/7344`; WI/KY retain their own site chrome.

- [x] **Step 2: Test interactions without external side effects**

Open and close the main mobile menu by trigger, close control, Escape, and overlay where supported; expand one submenu; verify internal scroll and page scroll lock; verify desktop eight-item navigation stays one row. Read phone, quote, and Book targets without clicking them. Do not submit forms, click phone/booking controls, or invoke integrations.

- [x] **Step 3: Record safety assertions**

Require one staging banner, one header, one menu trigger, one footer, no horizontal overflow, no new console exception, no production-domain navigation, and no third-party browser requests beyond approved same-origin/Clopay assets.

### Task 5: Full crawl, rollback gate, and repository handoff

**Files:**
- Create: `/private/tmp/staging-global-chrome-exhaustive-crawl-report.json`
- Modify: `website/staging-safety/README.md`

**Interfaces:**
- Consumes: Tasks 1–4 results.
- Produces: final whole-site evidence and documented rollback command.

- [x] **Step 1: Run complete same-origin crawl**

Discover all main, WI, and KY links from the three roots. Require every bounded page and CSS/JS asset to return `200`; every page must retain exact staging safety headers, no raw protected shortcodes, no production-domain URL, and no fatal text. Require every main eligible page to render clone header `7336` exactly once and the correct footer exactly once.

- [x] **Step 2: Verify rollback without executing it**

Run status mode and require `GLOBAL`. Run `TWINS_STAGING_CHROME_MODE=rollback TWINS_STAGING_CHROME_DRY_RUN=1` and require actual `afterState:"GLOBAL"`, projected target `projectedState:"ORIGINAL"`, `stagingMutation:false`, empty `changedDocumentIds`, projected fixed IDs in `projectedDocumentIds`, and no writes. Document:

```bash
TWINS_STAGING_CHROME_MODE=rollback wp eval-file /home/customer/staging-safety/staging-chrome-transition.php
```

Do not execute rollback when all gates pass.

- [x] **Step 3: Update README and run the full package verification**

Document the global staging chrome state, fixed IDs, safety differences, backup locations, and rollback command. Run:

```bash
node --test website/staging-safety/tests/staging-safety.test.cjs
git diff --check
```

- [ ] **Step 4: Commit and publish the scoped branch**

Stage only `website/staging-safety` and this plan. Commit with `feat(web): promote Claude chrome on private staging`. Push only `codex/staging-site-safety`; do not merge or touch production.

- [ ] **Step 5: Remove temporary access after the expanded staging-overhaul program**

Delete the SiteGround key named `CHATGPT_PROFILE_1 staging batch 2`, verify the local private key no longer authenticates, then delete the local private key file. Leave the working private staging review tab open for the user.

## Self-Review

- Spec coverage: fixed staging identity, exact expected-old canary conditions, immutable content fingerprints, supported Elementor save/cache API, one-pass transition, compensating rollback, responsive QA, complete crawl, backup, and access cleanup are covered.
- Placeholder scan: no `TBD`, `TODO`, inferred document IDs, caller-selected refs, or unspecified test outcomes remain.
- Type consistency: the three state names are `CANARY`, `GLOBAL`, and `ORIGINAL`; the three write modes are `promote`, `restore-canary`, and `rollback`; status and `TWINS_STAGING_CHROME_DRY_RUN=1` never write.
- Deferred scope: alternate chrome `2163/2179`, shared library sections `1498-1516`, B3 service-page redesign, paid LP fixes, Kentucky parity, Illinois, and door-builder rendering are intentionally separate implementation plans because no deployable Claude artifacts exist for them.
