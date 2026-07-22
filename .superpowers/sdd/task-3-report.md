# Task 3 report: premium location visual system

## RED

Replaced the retired large-mascot contract with block-scoped assertions for the retained Lilita One heading, thin hero photo rule, one-pixel service card, exact hero/guidance/final-right widths, location-only pointer handling, reduced motion, and removal of retired placements.

Command:

```sh
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Result: 8 passed, 1 failed. The new assertion failed because `.twins-location-hero h1` did not yet declare the required `Lilita One` family inside its own rule.

## GREEN

Implemented the location-only premium system:

- Editorial navy hero with low-contrast sectional-door texture, thin gold photo rule, preserved Lilita One heading, and restrained proof row.
- One-pixel, lightly rounded service, system, guidance, process, branch, and nearby surfaces without hard offset shadows.
- Exactly the hero, guidance, and final-right mascot geometries; all are `pointer-events: none`, with a 3–4px float and explicit reduced-motion reset.
- Responsive stacking and the brief's compact 480px mascot safe area, without retired system/final-left mascot selectors.

Focused contract command:

```sh
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/location-page-overhaul-contract.test.cjs
```

Result: 9 passed, 0 failed.

## Broader verification

```sh
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/contracts/*.test.cjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node --test tests/php-harnesses.test.cjs
/Users/daniel/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node tools/build-owned-images.mjs --check
git diff --check
```

- Full contract suite: 81 passed, 3 failed. The failures are the deferred CSS identity-manifest drift: two `package-contract.test.cjs` size assertions and one `site-unification.test.cjs` CSS hash assertion. Package manifests were intentionally not rebuilt; Task 5 owns the single final rebuild.
- PHP harness wrapper: 33 skipped because PHP CLI is unavailable locally; 0 failures.
- Owned-image check and whitespace check: passed.

## Files changed

- `website/twins-brand-experience/assets/css/twins-brand.css`
- `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`

## Self-review

- Contract regexes stop at the selected declaration block (`[^}]*`), so a later unrelated `box-shadow` cannot satisfy or defeat the service-card check.
- Retired `twins-location-twin--system` and `twins-location-twin--final-left` selectors are absent from the stylesheet.
- Location-Twin selectors are all page-scoped, non-interactive, responsive, and motion-safe.
- No templates, browser fixtures, package manifests, routing, analytics, deployment, or owned image assets were changed.
