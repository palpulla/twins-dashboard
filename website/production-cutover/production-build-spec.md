# Production package build spec

What it takes to build the production deploy package, exactly. The pipeline
ships it as `productionPackage: 'DEFERRED'` by design (build-packages.mjs:187),
and two of the steps below are deliberate changes to security contracts — so
this is an **owner-authorized** build, not an autonomous one. This spec exists
so that when authorization + production access land, the build is mechanical and
reviewable rather than exploratory.

Prereqs before any of this runs: release-runbook step 0 (owner sign-off, backup
verified, production WP admin + SiteGround access).

## What the production package contains

Same brand core + overhaul that staging deploys, with three deltas:

| Include | Notes |
|---|---|
| `twins-brand-experience/**` (assets, src, templates, config, data) | identical to the staging deploy set |
| `twins-staging-overhaul/**` mu-plugin | identical **except** the two gated behaviors below |
| `production-cutover/production-adapters.php` | → installed next to `brand-runtime.php`'s adapters dir |
| `production-cutover/production-callback.js` | → production-only asset (staging JS contract bans `fetch`) |

| Exclude | Why |
|---|---|
| `twins-staging-safety.php` (+ `twins-staging-assets`) | the staging safety plugin refuses to boot outside staging on its own; it is intentionally never deployed |
| `production-cutover/*.md`, `blog-revamp/`, tests | docs/fixtures, not runtime |

Booking = HCP external; Quote = live callback form → `lp-lead-intake` edge
function; Careers = external `/careers/#apply`. All three live in
`production-adapters.php` (already bug-fixed: `assertReady()` present on all).

## BLOCKER A — the sealed build tool refuses production sources

> **STATUS: designed for review (`blocker-a-build-unseal.md`); un-seal NOT
> applied.** That doc has the measured deploy set (84 staging deploy entries +
> the 3 production files = 86), the additive `kind: 'production'` patch for
> `build-packages.mjs` (staging scope guard untouched; default runs byte-
> identical; write authority stays false), and the fixpoint chain. Also drafted +
> syntax-validated: `production-overhaul-loader.php` — production needs its own
> mu-plugin loader because the staging loader hard-refuses non-staging boot and is
> a verify-prerequisite (never deployed), and the safety plugin is absent on
> production. Remaining before this can be applied: owner authorization +
> production host inventory (to resolve the verify-prerequisite asset set).


`tools/build-packages.mjs` is sealed against exactly this package:

- **Line 89** rejects any `role: 'deploy'` entry whose source path contains
  `/production/` **or** whose destination is not under
  `twins-brand-experience/` or `twins-staging-overhaul/`. Both
  `production-adapters.php` (source under `production-cutover/`) and
  `production-callback.js` trip this.
- **Line 187** hardcodes `productionPackage: 'DEFERRED'`.
- Lines 21, 149, 185–186 hardcode `productionWriteAuthority: false`.

Building the package = a **reviewed un-seal**: add a `production-runtime.json`
manifest, a production build path that assembles the delta set above, and widen
(not remove) the line-89 guard to allow the two approved production-cutover
sources by exact path allowlist — never a blanket `/production/` pass. Keep
`productionWriteAuthority` gated on an explicit env/flag so a build can never
also *write* without a second authorization. This is a security-critical edit;
it should be reviewed as its own change, not folded into content work.

## BLOCKER B — the overhaul refuses the production callback form

> **STATUS: reviewable patch drafted + validated on staging (commit `a0cb62d0`).**
> The gate below is implemented via one shared environment seam
> (`twins_overhaul_environment_is_production()`); the pre-existing map embed gate
> now uses it too. Inert on staging (the staging LOADER fails closed outside
> `WP_ENVIRONMENT_TYPE === 'staging'`, so the production branch is exercised only
> by the production package boot — same testability profile as the map embed).
> Added the `environment-gate` renderers-harness scenario (locks the staging
> side). Full gate green (renderers 21/21, contracts 75/75, check:repo). Not
> deployed. The production-side end-to-end proof lands with the brand-runtime
> adapter env branch + production package boot.


`renderers.php:1497` scans every classified route's output for `<form>` and
refuses the route if any survives. It ships to production and is **not**
environment-gated. The contact page renders the callback form via
`quoteAdapter()->renderExperience()` (contact.php:39 → `renderContact` →
classification `contact-brand`), so on production the contact page would
**refuse to render** the moment `ProductionQuoteAdapter` emits its `<form>`.

Fix: environment-gate the form scan the same way the service-area map already is
(`renderers.php:1533` uses `WP_ENVIRONMENT_TYPE === 'production'`). On
production the trusted adapter form must be allowed; on staging the scan stays
exactly as-is. Precise, minimal shape:

```php
$isProduction = defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production';
if (!is_string($rendered) || (!$isProduction && preg_match('~</?form\b~i', $rendered))) {
    twins_overhaul_refuse_route('classified staging output retained form markup.');
}
```

Scope check done: the *only* adapter that emits a form is the quote adapter
(contact). Careers emits an `<a>`; booking is external. The preserved-content
inert/refuse machinery (552, 947, 966, 1139–1205) acts on **legacy** content,
not on trusted adapter output, so it does not need touching for the callback —
leave it hardened. Do NOT weaken these globally.

> Testing constraint: this change cannot be exercised on this Mac (no production
> WP runtime), and the staging harness asserts the current ungated form contract
> — so the gate + its harness scenario must be updated together and validated
> against a production-env fixture, not silently loosened.

## Mechanical piece — brand-runtime adapter env branch

> **STATUS: drafted + validated on staging (commit `642409f4`).** Implemented
> exactly as below, using the shared seam. Only the three quote/booking/careers
> adapters swap; the portable infra (asset resolver, route adapter, reviews
> provider) stays shared. Inert on staging (production-adapters.php is never
> shipped there and the LOADER fails closed outside staging). Added
> `production-adapters-harness.php` — the first execution proof of the production
> adapters (interface conformance, assertReady, the callback form the gate
> allows). Full gate green; not deployed. The production `require_once` target is
> `adapters/production-adapters.php` next to brand-runtime.php — Blocker A's build
> must copy `production-cutover/production-adapters.php` there.


`brand-runtime.php` currently wires the three staging adapters unconditionally.
Add an environment branch (inert on staging — staging keeps the exact current
path):

```php
if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
    require_once __DIR__ . '/adapters/production-adapters.php';
    $quote = new ProductionQuoteAdapter();
    $booking = new ProductionBookingAdapter();
    $applications = new ProductionApplicationAdapter();
} else {
    // ...existing staging adapters, unchanged...
}
$quote->assertReady(); $booking->assertReady(); $applications->assertReady();
```

The production branch never executes on staging (env gate) and its
`require_once` target is absent from the staging package, so staging behavior and
its gate stay green. This file is pinned in both manifests, so it repins like any
other deployed file.

## Mechanical piece — production-callback.js delivery

Staging's shared JS contract bans `fetch`; `production-callback.js` is a
separate production-only file (already materialized). Wire it into the
production asset enqueue only (mirror how the map iframe is production-only), and
add it to `production-runtime.json` with its size+sha256 pin.

## Build sequence (once authorized)

1. Land Blocker B (gated form scan + harness scenario) and the brand-runtime env
   branch on the branch; full staging gate stays green (both are inert on
   staging). Ship or hold with the next staging release as preferred.
2. Land Blocker A (production manifest + build path + reviewed line-89 widen).
3. `npm run build:packages` produces `dist/production-runtime/`; `check:packages`
   verifies the pin chain. **No deploy yet** — assembling ≠ firing.
4. Deploy is release-runbook step 4 (owner-authorized window, production
   transaction id, dry-run → capture → release), then OTTO removal
   (`otto-removal-runbook.md`) and the 3 missing service pages
   (`redirect-plan.md` / release-runbook step 2).

## Deliberately NOT done here

The reviewed un-seal (Blocker A), the safety-contract gate (Blocker B), and any
live-deploy execution are held for owner authorization + production access. This
spec makes each one a mechanical, reviewable step; it does not perform them.
