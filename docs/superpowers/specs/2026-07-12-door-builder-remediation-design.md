# Door Builder Remediation Design

Date: 2026-07-12

Status: Approved design; repository-only implementation pending

Owner choice: honest high-quality specification builder

## Purpose

Build a free, embedded Twins Garage Doors door-specification experience that uses high-quality Clopay reference imagery without claiming to render a visitor's exact color, window, glass, or home-photo combination.

This work creates a testable repository package and generated WordPress artifacts. It does not deploy to WordPress, alter hosting, create a grant, acquire a lock, or claim live write authority.

## Binding product decisions

- The builder remains embedded on `twinsgaragedoors.com`.
- The builder costs $0 and uses no paid visualizer service.
- The builder does not redirect visitors to EZDoor or another off-site designer.
- The launch promise is: build a door specification with honest, high-quality reference imagery.
- The builder must not promise photo upload, live compositing, or an exact visual match.
- MAIN and WI keep the same builder behavior and lead payload contract. KY remains outside this remediation.
- The existing 23-collection catalog and `?product=<safe-id>` deep links remain supported.

## Evidence and current limitations

The preserved Gallery Steel fixture exposes two panel-design images, 19 color samples, 20 window samples, six glass samples, and nine fixed lifestyle photos. It exposes no public endpoint or transparent layer set that can render an arbitrary design/color/window/glass combination.

The current builder promotes a small panel image to a wide hero and only changes that image when the design changes. Color, window, and glass selections update text and samples but cannot alter the hero. The correct remediation is therefore a truthful preview policy, not fabricated compositing.

The preservation repository does not contain current authoritative Elementor exports for pages 6065, 7073, or 7129. Repository work may define page contracts and validators, but it must not manufacture deployable page patches from stale rendered HTML.

## Approaches considered

### 1. Patch the preserved monolithic WPCode body

This is the shortest edit, but it leaves browser behavior entangled in one generated PHP file, provides poor unit-test seams, and would blur the boundary between immutable historical evidence and new implementation.

Rejected.

### 2. Replace the builder with a Gallery Steel-only experience

This could provide the strongest curated presentation for product 12, but it would remove the current 23-collection capability and break existing catalog deep links.

Rejected.

### 3. First-class source package with generated WordPress artifacts

Pure behavior is separated from the browser adapter, source artifacts generate the WPCode body and local harness deterministically, and all current collections remain available. Historical files remain unchanged.

Selected.

## Repository layout

New implementation files live under:

```text
website/door-builder-remediation/
  README.md
  assets/
    reference-manifest.json
  page-contracts/
    page-6065.json
    page-7073.json
    page-7129.json
  src/
    core.js
    app.js
    funnel-submit.js
    styles.css
    wpcode-wrapper.php.tmpl
  scripts/
    build.mjs
  tests/
    core.test.cjs
    funnel-submit.test.cjs
    build-contract.test.cjs
    page-contracts.test.cjs
  dist/
    twins-door-builder-wpcode.php
    design-your-door-funnel.js
    local-harness.html
    verification-image.svg
    artifact-manifest.json
```

The existing files below remain immutable historical inputs:

- `docs/superpowers/backups/2026-07-09-phase4-catalog/snippet-7127-after-deeplink-expected.php`
- `docs/superpowers/backups/2026-07-09-phase3-door-builder/endpoint-7072-deployed-final.php`
- `docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot/`

## Component design

### `src/core.js`

A dependency-free universal module that runs under both Node and a classic browser script. It owns pure behavior only:

- normalize catalog and detail responses;
- sanitize API labels while allowing only `sup` and `br` for display;
- produce plain-text lead values;
- validate image URLs against HTTPS and the `www.clopaydoor.com` host;
- assign preview evidence classifications;
- select the best honest preview and inspiration images;
- calculate available/skipped steps;
- build the existing lead JSON payload.

Preview evidence classifications are fixed:

- `exact-render`: permitted only when the repository manifest proves an image represents the exact selected combination;
- `reference-photo`: a high-resolution installed-door or lifestyle image that inspires but does not prove the selected combination;
- `panel-style`: a manufacturer design image that proves panel shape only;
- `swatch-only`: a finish, window, glass, or hardware sample.

The initial manifest contains no `exact-render` records. Adding one later requires an HTTPS source URL, Clopay product ID, exact covered selections, pixel dimensions, SHA-256 digest, provenance, and an explicit right-to-use statement.

### `src/app.js`

A browser adapter that consumes `TwinsDoorBuilderCore` and renders one builder mount. It preserves the existing collection, design, color, windows, glass, and summary flow.

The visual hierarchy is:

1. A high-resolution `reference-photo` from `ProductImageGallery.ImageUrl`, or the product `ShowcaseImage` when no gallery image exists.
2. A clearly labeled panel-style reference shown at intrinsic size.
3. Selected finish, window, and glass samples beside their names.
4. A persistent specification summary and quote form.

Every reference hero displays this label:

> Inspiration photo — your selected options are listed separately and Twins will confirm the final appearance.

Every panel image displays this label:

> Panel-style reference shown at its original resolution.

Images must use `width:auto`, `height:auto`, and `max-width:100%`. The app must never assign a rendered width greater than an image's `naturalWidth`.

### `src/funnel-submit.js`

This module provides the page-7073 lead-gate behavior. It keeps the current payload fields and endpoint, but redirects only when both conditions are true:

- the HTTP response is 2xx; and
- parsed JSON contains `ok: true`.

Network failures, non-2xx responses, malformed JSON, or `{ok:false}` keep the visitor on the form, re-enable the button, and show the regional phone fallback.

### `src/wpcode-wrapper.php.tmpl`

The wrapper registers `[twins_door_builder]`, renders one mount with region and endpoint data attributes, and inlines generated CSS, core, and app source. It does not change endpoint snippet 7072.

The generated PHP is a candidate deployment artifact only. Its header states that live use requires a fresh export, owner-signed grant, verified staging, lock acquisition, and expected-old rollback evidence.

### `assets/reference-manifest.json`

The manifest records curated imagery without upgrading reference evidence into an exact-render claim. Gallery Steel receives curated lifestyle-image ordering. Other collections safely fall back to their first valid high-resolution gallery image or showcase image.

URLs from unapproved hosts, HTTP URLs, missing images, and malformed records are ignored.

## Page contracts

Page contracts are validation and handoff documents, not Elementor mutation payloads.

### Page 6065 — Clopay Gallery Steel

- Exactly one `id="design-your-door"` section.
- Exactly one `[twins_door_builder]` mount.
- Exactly one visible `Design Your Gallery Steel Door` heading.
- Product deep link remains `?product=12`.
- Stale duplicate section `e26273a` is a removal candidate only after a fresh export proves its identity and expected content.
- Existing live builder section `78da141` is not presumed safe to replace without that export.

### Page 7073 — Design Your Door lead gate

Required hero copy:

> Choose your collection and options, then send the specification to Twins for a free quote.

Required supporting copy:

> Manufacturer photos and samples help you compare choices. Twins will confirm the exact appearance before ordering.

Forbidden claims include `upload your home`, `try it on your house`, `every option live`, `exact render`, and equivalent claims.

Successful submission redirects to `/door-builder/`. Any failure stays on the page and displays the fallback.

### Page 7129 — Door Builder

- Exactly one H1: `Design Your New Garage Door`.
- Exactly one `[twins_door_builder]` mount.
- No separate lead gate before the builder.
- SEO title: `Build Your Garage Door Specification | Twins Garage Doors`.
- Meta description: `Compare Clopay collections, panel styles, colors, windows and glass, then send your garage door specification to Twins for a free quote.`

## Data flow

1. The WordPress shortcode renders a mount and the generated assets.
2. The app loads the collection list from the public Clopay API with a 10-second timeout and session-scoped caching.
3. A deep-linked product is accepted only when its ID is a decimal safe identifier present in the fetched list.
4. The app loads product details and normalizes all arrays through `core.js`.
5. The preview policy selects labeled reference imagery and separately renders the visitor's option samples.
6. The summary generates the unchanged endpoint payload keys: `name`, `phone`, `email`, `zip`, `region`, `website`, `notes`, `collection`, `design`, `color`, `windows`, and `glass`.
7. The app posts only after explicit submission. It records no lead data in local or session storage.
8. A successful endpoint response produces an inline thank-you state. A failure leaves the form actionable and exposes the regional phone escape hatch.

## Error handling and safe defaults

- Collection or detail fetch failure renders the existing fallback quote form.
- Empty or malformed option arrays skip their steps.
- Missing gallery imagery falls back to a valid showcase image.
- If only a small panel image exists, it remains a labeled intrinsic-size secondary reference rather than a stretched hero.
- Invalid or unapproved image URLs are omitted.
- A manifest mismatch degrades to `reference-photo`, `panel-style`, or `swatch-only`; it never upgrades to `exact-render`.
- Lead failures never redirect and never show a success state.
- The local harness blocks calls to the real lead endpoint, records submissions in memory, rewrites rendered images to a deterministic same-origin verification fixture, and preserves original manufacturer URLs as source metadata.
- Build output is deterministic; `build.mjs --check` fails when committed `dist/` differs from generated output.

## Testing strategy

Testing uses Node's built-in `node:test` runner and no third-party dependencies.

### Core unit tests

- Gallery Steel fixture normalizes to two designs, 19 colors, 20 windows, six glass choices, and nine reference photos.
- Deep links accept decimal product IDs and reject missing, negative, non-decimal, and unlisted values.
- Reference photos never report `exact-render`.
- Panel images report `panel-style` and carry a no-upscale policy.
- Swatches report `swatch-only`.
- Invalid image protocols and hosts are rejected.
- Lead payloads preserve the existing field names and strip HTML.

### Funnel tests

- 2xx plus `{ok:true}` redirects once.
- Non-2xx never redirects.
- 2xx plus malformed JSON never redirects.
- 2xx plus `{ok:false}` never redirects.
- Network rejection never redirects.

### Generated-artifact contracts

- The WPCode artifact contains one shortcode registration and one mount.
- The generated CSS contains the no-upscale image contract.
- The app contains no real endpoint call in `local-harness.html`.
- Generated copy contains none of the forbidden visualization promises.
- A second build produces byte-identical files, the exact five-regular-file `dist/` set, and matching SHA-256 records. `--check` rejects extra or non-regular entries.

### Page-contract tests

- Every page contract has a numeric page ID, fixed site, exact heading rules, and explicit mutation status `blocked-pending-fresh-export`.
- Page 6065 requires one mount, one section ID, and one heading.
- Page 7073 contains truthful approved copy and the full forbidden-claim list.
- Page 7129 has a unique SEO title and one H1.

### Local browser verification

After automated tests pass, the generated harness is exercised at desktop and 390-pixel widths. Verification covers the full option path, no-window path, skip-to-quote path, API-failure fallback, non-2xx lead failure, and `?product=12` deep link. The hash-backed same-origin fixture must load with `naturalWidth > 0`; rendered images must preserve original Clopay source metadata and remain within intrinsic, container, and component caps. CSP allows same-origin verification images while blocking external subresources, connections, and form actions. No test submits to the real endpoint.

## Build and verification commands

```bash
node --test website/door-builder-remediation/tests/*.test.cjs
node website/door-builder-remediation/scripts/build.mjs --check
python3 -m http.server 8123 --bind 127.0.0.1 --directory website/door-builder-remediation/dist
```

The HTTP server is used only for local browser verification and is stopped afterward.

## Delivery and rollout boundary

Repository delivery consists of source, deterministic generated artifacts, tests, page contracts, and a rollback-oriented handoff. It does not include live exports or mutations.

Before any future deployment:

1. Capture fresh authoritative exports for WPCode 7127, 6765, 7072, the relevant 7050/6755 dependencies, and Elementor pages 6065, 7073, and 7129.
2. Record hashes, activation state, current modified timestamps, and exact rollback artifacts in the private control plane.
3. Verify WP Cloud staging and rehearse deployment and rollback there.
4. Clear the production freeze through the owner-controlled process.
5. Obtain an active owner-signed grant for the exact object set.
6. Acquire and attest the global lease for `CHATGPT_PROFILE_1`.
7. Perform one expected-old transition with no automatic retry.

## Out of scope

- Live WordPress, WP Cloud, SiteGround, grant, lock, freeze, or control-plane mutation.
- Paid SaaS or off-site EZDoor integration.
- Customer photo upload or client-side home-photo compositing.
- Hotlinking or harvesting private EZDoor application assets.
- Native WordPress menu changes or the separate WI/KY phone-leak remediation.
- Global header/menu/footer rollout.
- KY builder deployment.
- Changing endpoint 7072 unless fresh baseline evidence later proves a separate defect.

## Acceptance criteria

- Historical preservation files remain byte-identical.
- All implementation lives in the new remediation package.
- The package supports all 23 preserved collections and safe `?product=` deep links.
- High-resolution reference imagery is clearly distinguished from panel and option samples.
- No image is intentionally upscaled beyond intrinsic dimensions.
- No UI or metadata promises upload-your-home, live compositing, or exact combination rendering.
- Lead failures never redirect or display success.
- The local harness cannot call the real lead endpoint.
- Automated tests and deterministic-build checks pass without third-party dependencies.
- Page contracts remain blocked until fresh authoritative exports exist.
- No live system is mutated by this branch.
