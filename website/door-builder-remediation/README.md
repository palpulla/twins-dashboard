# Door builder remediation handoff

## Purpose and honest-reference promise

This dependency-free, repository-only package preserves and tests a Twins Garage Doors specification builder for MAIN and WI across all 23 frozen Clopay collections. It combines frozen catalog data with clearly labeled manufacturer reference photos, panel-style references, and option samples. Those images are evidence for choosing a specification; they are not a photo-upload or compositing feature, a live option render, an exact render of the selected combination, or a promise of final appearance. Twins must confirm the exact appearance before ordering.

Everything under `dist/` is a generated, inactive candidate. This package does not authorize or perform a WordPress, hosting, grant, lease, freeze, or private-control-plane change.

## Directory map

```text
website/door-builder-remediation/
├── assets/
│   └── reference-manifest.json       # curated honest-reference evidence
├── page-contracts/
│   ├── page-6065.json                # blocked Gallery Steel page contract
│   ├── page-7073.json                # blocked lead-gate page contract
│   └── page-7129.json                # blocked builder page contract
├── scripts/
│   └── build.mjs                     # deterministic generator/checker
├── src/
│   ├── app.js                        # browser UI and navigation
│   ├── core.js                       # normalization, steps, payload, evidence
│   ├── funnel-submit.js              # success-only lead submission
│   ├── styles.css                    # responsive no-upscale presentation
│   ├── transport.js                  # fetch and session-cache boundaries
│   └── wpcode-wrapper.php.tmpl       # inactive wrapper source template
├── tests/                            # dependency-free Node test suite
├── dist/                             # deterministic generated candidates
│   ├── artifact-manifest.json
│   ├── design-your-door-funnel.js
│   ├── local-harness.html
│   ├── twins-door-builder-wpcode.php
│   └── verification-image.svg
└── README.md
```

The build reads the frozen 23-product catalog snapshot at `docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot/`. Historical files in `docs/superpowers/backups/` are immutable inputs.

## Commands

Run from the repository root.

```bash
# Complete automated suite (currently 82 tests)
node --test website/door-builder-remediation/tests/*.test.cjs

# Regenerate all five files in dist/
node website/door-builder-remediation/scripts/build.mjs

# Prove committed generated files are byte-identical to fresh output
node website/door-builder-remediation/scripts/build.mjs --check

# Repository whitespace and historical-preservation checks
git diff --check
git diff origin/main -- docs/superpowers/backups

# Localhost-only preview; stop with Ctrl-C after verification
python3 -m http.server 8123 --bind 127.0.0.1 --directory website/door-builder-remediation/dist
```

Use these localhost-only cases:

```text
http://localhost:8123/local-harness.html?product=12
http://localhost:8123/local-harness.html?product=8
http://localhost:8123/local-harness.html?product=16
http://localhost:8123/local-harness.html?product=291
http://localhost:8123/local-harness.html?product=12&leadFail=1
http://localhost:8123/local-harness.html?twxdbfail=1
```

The harness replaces catalog and lead requests with same-document frozen fixtures and local recording. It rewrites rendered images to the same-origin `verification-image.svg` before the browser can request the original, while preserving each original HTTPS Clopay URL in `data-source-url`. The repository-generated fixture is described and SHA-256-backed in `artifact-manifest.json`; it is local verification evidence, not a manufacturer photograph or production asset. The Content Security Policy permits only same-origin images and blocks external subresources, connections, and form actions. Browser checks require `naturalWidth > 0` plus intrinsic, container, and component caps at desktop and 390px.

There are exactly five regular files in `dist/`. `build.mjs --check` rejects changed, missing, extra, or non-regular entries.

## Source and generated artifacts

| Path | Kind | Meaning |
| --- | --- | --- |
| `src/core.js`, `src/transport.js`, `src/funnel-submit.js`, `src/app.js`, `src/styles.css` | source | Hand-maintained builder behavior, evidence policy, transport, submission, UI, and CSS. |
| `src/wpcode-wrapper.php.tmpl` | source template | Thin shortcode wrapper with an explicit candidate-only header. |
| `assets/reference-manifest.json` | source evidence | Curated manufacturer reference-photo metadata; no entry claims an exact render. |
| `page-contracts/*.json` | source contracts | Testable requirements only; they are not Elementor exports or deployable page patches. |
| `dist/twins-door-builder-wpcode.php` | generated candidate | Inactive shortcode candidate assembled from the template and source modules. |
| `dist/design-your-door-funnel.js` | generated candidate | Inactive page-7073 lead-gate candidate using the current endpoint payload and success-only behavior. |
| `dist/local-harness.html` | generated verification artifact | Network-closed localhost harness with all 23 frozen catalog fixtures and a local-only lead recorder. Never a production page. |
| `dist/verification-image.svg` | generated verification fixture | Deterministic 960×540 same-origin placeholder used only to prove image loading and responsive intrinsic-size caps without external traffic. |
| `dist/artifact-manifest.json` | generated integrity record | Stable SHA-256 records for the other four generated files plus local-fixture provenance and dimensions. |

Do not hand-edit `dist/`. Edit source, regenerate, and run the deterministic check.

## Inactive and blocked status

- This package is a WordPress candidate plus a localhost-only harness; a Vercel preview is not a deployment or validation gate for it.
- The production Twins operations dashboard is the separate `palpulla/twins-dash` repository and Vercel project. Do not point the outer `palpulla/twins-dashboard` Vercel project at `3b-holdings-dashboard` or treat that unrelated property-management prototype as this package's application.
- Endpoint `7072` is unchanged. This package neither edits nor replaces it.
- Pages `6065`, `7073`, and `7129` remain blocked pending fresh authoritative Elementor exports. Their JSON files are contracts only.
- Snippets `7127`, `6765`, `7072`, `7050`, and `6755` are unchanged by this package.
- No generated candidate is active, deployed, or approved for live use.

The exact future baseline set is: `7127`, `6765`, `7072`, `7050`, `6755`, `6065`, `7073`, and `7129`.

## Rollback and control-plane gates

There is no deployment without byte-identical before exports for every object in the baseline set and recorded expected-old evidence. A future operator must also record hashes, activation state, modification timestamps, and exact rollback artifacts in the private control plane.

Only after that evidence exists may a separately authorized deployment proceed through all of these gates:

1. Verify and rehearse the change and rollback on staging.
2. Obtain production-freeze clearance through the owner-controlled process.
3. Obtain an active owner-signed grant for the exact object set.
4. Acquire and attest the global `CHATGPT_PROFILE_1` lease.
5. Perform one expected-old transition attempt with no automatic retry.

This README is a handoff, not authority to bypass any gate.

## Out of scope

- WI/KY phone-leak remediation.
- Native WordPress menu changes.
- Global header, menu, footer, or other global-chrome work.
- KY builder behavior or deployment.
- Any live WordPress, WP Cloud, SiteGround, hosting, grant, lease, freeze, or control-plane mutation.
