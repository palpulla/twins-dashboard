# Location Page Overhaul Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild every registered city page as a substantial local service landing page and remove all copy that presents Twins as newly opened or unproven.

**Architecture:** Keep route resolution and the 59-record location registry as the data layer. Render a dedicated location composition inside `editorial.php`, while trust and article pages retain their current composition. Add location-only CSS classes to the existing brand stylesheet and protect the structure with the PHP renderer harness.

**Tech Stack:** PHP 8 templates, repository-native CSS, Node test runner, existing package/deployment tooling.

## Global Constraints

- Never describe any office, branch, market, or service area as recently opened, new, unproven, or still earning a record.
- Do not invent response times, prices, warranties, neighborhood familiarity, years in a city, or job-count claims.
- Preserve Twins navy, gold, cream, typography, borders, and industrial personality.
- Preserve the no-numerals rule for the 20 null-job-count cities.
- The shared location composition must render for all 59 registered routes.

---

### Task 1: Renderer contracts for the approved location composition

**Files:**
- Modify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php`

**Interfaces:**
- Consumes: `Experience::renderEditorial(array $context, string $content, string $kind): string`
- Produces: contract assertions for `.twins-location-*` sections, five FAQs, compact nearby areas, and prohibited-copy rejection.

- [ ] **Step 1: Write the failing Rockford structure and copy assertions**

```php
$rockfordLocation = $stagingExperience->renderEditorial([
    'environment' => 'staging',
    'market' => 'main',
    'path' => '/il/location/rockford/',
    'title' => 'Rockford',
], '<p>LEGACY ROCKFORD LOCATION BODY</p>', 'location');
foreach (['twins-location-hero', 'twins-location-proof', 'twins-location-services', 'twins-location-guidance', 'twins-location-process', 'twins-location-branch', 'twins-location-nearby'] as $className) {
    $expect(strpos($rockfordLocation, $className) !== false, 'Rockford omitted ' . $className);
}
$expect(substr_count($rockfordLocation, '<details') === 5, 'Rockford must render five FAQs');
foreach (['recently opened', 'new to this market', 'earn the local record'] as $prohibitedCopy) {
    $expect(stripos($rockfordLocation, $prohibitedCopy) === false, 'Rockford rendered prohibited copy: ' . $prohibitedCopy);
}
$expect(substr_count($rockfordLocation, '>Garage Door Repair</a>') === 1, 'Rockford duplicated the repair destination');
$expect(strpos($rockfordLocation, '5758 Elaine Dr Ste 110, Rockford, IL 61108') !== false, 'Rockford omitted its branch address');
```

- [ ] **Step 2: Run the PHP harness and verify RED**

Run: `npm run test:php`

Expected: FAIL because `twins-location-hero` and the other approved section classes do not exist.

- [ ] **Step 3: Add all-route structural assertions**

```php
foreach (['twins-location-hero', 'twins-location-services', 'twins-location-guidance', 'twins-location-process', 'twins-location-nearby'] as $className) {
    $expect(strpos($renderedLocation, $className) !== false, $slug . ' omitted ' . $className);
}
$expect(substr_count($renderedLocation, '<details') === 5, $slug . ' did not render five FAQs');
```

### Task 2: Rewrite weak new-market copy

**Files:**
- Modify: `website/twins-brand-experience/config/location-content.php`
- Modify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php`

**Interfaces:**
- Consumes: existing record shape `{label, metro, completedJobs, intro, localNotes, faq}`
- Produces: the same record shape with confident, customer-centered copy.

- [ ] **Step 1: Add a registry-wide prohibited-language assertion**

```php
$prohibitedLocationPhrases = ['recently opened', 'newly opened', 'new to this market', 'new market', 'recent addition', 'recent expansion', 'still building', 'earn the local record'];
foreach ($locationRecords as $slug => $record) {
    $recordText = strtolower($record['intro'] . ' ' . $record['localNotes'] . ' ' . implode(' ', array_merge(...array_map(static fn(array $faq): array => [$faq['q'], $faq['a']], $record['faq']))));
    foreach ($prohibitedLocationPhrases as $phrase) {
        $expect(strpos($recordText, $phrase) === false, $slug . ' contains prohibited positioning: ' . $phrase);
    }
}
```

- [ ] **Step 2: Run the PHP harness and verify RED**

Run: `npm run test:php`

Expected: FAIL on Milwaukee-, Wauwatosa-, and Rockford-cluster wording.

- [ ] **Step 3: Rewrite each failing intro or FAQ**

Use customer-centered sentences in this form, customized to each city:

```php
'intro' => 'Twins Garage Doors provides garage door repair, opener service, and replacement installation in Rockford and nearby northern Illinois communities. We inspect the complete door system, explain what failed, and give you clear repair or replacement options before work begins.',
```

Replace market-age FAQs with service questions; for Rockford:

```php
['q' => 'What garage door problems do you repair in Rockford?', 'a' => 'We diagnose broken springs, damaged cables, worn rollers, bent or misaligned tracks, noisy doors, doors that will not close correctly, and opener problems. The technician checks the full system so the recommendation addresses the cause, not only the loudest symptom.'],
```

- [ ] **Step 4: Run the PHP harness and verify the copy contract is GREEN**

Run: `npm run test:php`

Expected: structure assertions still fail, but prohibited-copy assertions pass.

### Task 3: Build the dedicated location-page composition

**Files:**
- Modify: `website/twins-brand-experience/templates/editorial.php`
- Reuse: `website/twins-brand-experience/components/picture.php`
- Reuse: `website/twins-brand-experience/components/nav-data.php`

**Interfaces:**
- Consumes: `$locationRecord`, `$context`, `$market`, `$marketKey`, `$phone`, `$phoneHref`, `$quote`, `$experience`
- Produces: semantic `.twins-location-*` markup; trust/article rendering remains on the legacy editorial branch.

- [ ] **Step 1: Build five location FAQs**

```php
$locationSharedFaqs = [
    ['question' => 'Can you repair my garage door, or will it need to be replaced?', 'answer' => 'Many doors can be repaired when the panels and main structure are in sound condition. Replacement may make more sense when damage is extensive, sections are failing, or the door no longer fits the home’s safety, insulation, or appearance needs. We inspect the system and explain both options when both are reasonable.'],
    ['question' => 'Do you service garage door openers?', 'answer' => 'Yes. We troubleshoot opener power, controls, safety sensors, travel settings, drive systems, and the connection between the opener and the door. We also check whether the door moves freely by hand, because a door problem can look like an opener failure.'],
    ['question' => 'What should I do if a spring or cable breaks?', 'answer' => 'Stop operating the door and keep people, pets, and vehicles clear of it. Springs and cables hold significant tension, and a damaged door can move unexpectedly. Call Twins so a trained technician can inspect the system and make the repair safely.'],
];
$editorialFaqs = array_slice(array_merge($editorialFaqs, $locationSharedFaqs), 0, 5);
```

- [ ] **Step 2: Replace the location hero and answer blocks**

Render one `.twins-location-hero` containing the kicker, H1, intro, call and quote actions, and `picture.php` with `technician-at-work`.

- [ ] **Step 3: Add trust, services, guidance, and process sections**

Render `.twins-location-proof`, three `.twins-location-service-card` articles, a two-column `.twins-location-guidance`, and a three-item `.twins-location-process-list`. Use only the existing route keys `repair`, `opener-repair`, and `installation`.

- [ ] **Step 4: Add branch and compact nearby sections**

Use `$context['metroAddress']` first and the market address second. From `nav-data.php`, exclude the current city and render at most six `.twins-location-nearby` links plus the existing `service-area` route.

- [ ] **Step 5: Keep non-location editorial pages unchanged**

Wrap the new composition in `if ($kind === 'location')` and retain the existing hero/body/FAQ composition in the `else` branch for trust and article kinds.

- [ ] **Step 6: Run the PHP harness and verify GREEN**

Run: `npm run test:php`

Expected: PASS.

### Task 4: Responsive visual system for location pages

**Files:**
- Modify: `website/twins-brand-experience/assets/css/twins-brand.css`

**Interfaces:**
- Consumes: `.twins-location-*` markup from Task 3
- Produces: dense two-column desktop layout and single-column mobile layout without the full door-map panel.

- [ ] **Step 1: Add desktop location styles**

Create scoped rules for a two-column hero, full-bleed proof band, three-card service grid, split guidance, three-step process, branch card, nearby grid, and location FAQ. Use existing CSS variables only.

- [ ] **Step 2: Add tablet and mobile rules**

At `max-width: 1024px`, collapse two-column sections. At `max-width: 768px`, reduce heading sizes and padding, make service/process cards single-column, and keep nearby cities in two compact columns. At `max-width: 480px`, use one nearby column only if labels overflow.

- [ ] **Step 3: Run repository and browser tests**

Run: `npm run test:contracts && npm run test:php && npm run check:assets && npm run test:browser`

Expected: PASS with zero failures.

### Task 5: Package, deploy, and visually verify private staging

**Files:**
- Modify: `website/twins-brand-experience/manifests/host-verification.json`
- Modify: `website/twins-brand-experience/manifests/staging-runtime.json`
- Modify: `website/twins-brand-experience/tools/deploy-private-staging.mjs`
- Modify: `website/twins-brand-experience/tools/private-staging-deploy.php`
- Modify: deployment contract files only where the immutable transaction changes.

**Interfaces:**
- Consumes: verified repository state
- Produces: the next immutable private-staging transaction and a live Rockford proof page.

- [ ] **Step 1: Run full pre-deploy verification**

Run: `npm run test:all`

Expected: PASS.

- [ ] **Step 2: Rotate the immutable staging transaction**

Change the exact `r20` transaction references to `r21` in the deployer, manifest remote directory, and exact contract expectations. Do not alter staging safety files.

- [ ] **Step 3: Build and verify the package**

Run: `npm run build:packages && npm run check:packages`

Expected: PASS and a deterministic package hash.

- [ ] **Step 4: Dry-run, capture expected-old, and deploy**

Run: `npm run deploy:staging:dry-run`, then `npm run deploy:staging:capture`, then `npm run deploy:staging:release`.

Expected: signed dry-run transport, expected-old capture, and `PRIVATE_STAGING_DEPLOYED`.

- [ ] **Step 5: Inspect Rockford at desktop and mobile sizes**

Verify the hero, proof bar, service cards, guidance, process, branch, compact nearby cities, five FAQs, correct Rockford address, and absence of all prohibited new-market wording.
