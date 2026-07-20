# Blocker A — un-seal the build tool for the production package

The design for assembling the production deploy package. **This is the
owner-authorized security change**: it adds a production build path to the sealed
`tools/build-packages.mjs`. Per the standing rule and `production-build-spec.md`,
it is drafted here for review and **not applied to the live tool** — the tool
stays sealed (`productionPackage: 'DEFERRED'`, `productionWriteAuthority: false`)
until an authorized change lands this.

Assembling a package (`dist/production-runtime/`) is **not** deploying — the
build tool never gains write authority. Deploy stays a separate, owner-authorized
step (release-runbook step 4) via the deploy CLI with a production transaction.

Companion drafted artifacts (both inert, already in this directory):
- `production-adapters.php` — the three production adapters (execution-proven by
  `production-adapters-harness.php`).
- `production-callback.js` — the callback submission script.
- `production-overhaul-loader.php` — the production mu-plugin loader (below).

## 1. What the staging pipeline actually ships (measured)

`staging-runtime.json` has 889 entries: **84 `deploy`** (written to the host) +
**805 `verify-prerequisite`** (must already exist on the host; verified, not
written).

- **84 deploy** = 66 → `twins-brand-experience/**` + 18 → `twins-staging-overhaul/**`.
  Both are portable application code; they carry to production unchanged (the
  overhaul now has the brand-runtime env branch and the env-gated form scan).
- **805 verify-prerequisite** = the content-addressed clopay catalog (803) + the
  fonts, brand images, `twins-overhaul.css/js`, `twx-v2-kit.css`, AND — key —
  **`twins-staging-overhaul.php` (the loader) and `twins-staging-safety.php`**.
  The pipeline does **not** deploy the loader or the safety plugin; they are
  placed on the host out of band.

Implication: the production package is **not** "staging deploy set minus safety."
The loader and safety plugin were never in the deploy set to begin with, and the
assets are host-prerequisites.

## 2. The production deploy set (86 files)

The 84 staging deploy entries, unchanged, PLUS:

| Source | Destination | Role |
|---|---|---|
| `production-cutover/production-adapters.php` | `twins-staging-overhaul/adapters/production-adapters.php` | deploy |
| `production-cutover/production-callback.js` | `twins-staging-overhaul/production-callback.js` | deploy |
| `production-cutover/production-overhaul-loader.php` | `twins-overhaul.php` | deploy |

The three production-cutover sources are the **only** files outside
`twins-brand-experience/` and `twins-staging-overhaul/` the production manifest
may deploy — enforced by an exact allowlist (section 4).

Not shipped: `twins-staging-safety.php` (production has no safety plugin) and the
staging loader `twins-staging-overhaul.php` (replaced by `twins-overhaul.php`).

## 3. The production loader (drafted: `production-overhaul-loader.php`)

The staging loader fails closed unless `WP_ENVIRONMENT_TYPE === 'staging'` and the
staging-only constants `TWINS_STAGING_SAFETY` / `DISABLE_WP_CRON` are set, and it
is a verify-prerequisite (never deployed). Production runs normal WordPress, so it
needs its own loader. The drafted one:

- Fails closed unless `WP_ENVIRONMENT_TYPE === 'production'` (cannot run on staging).
- Refuses to boot if `TWINS_STAGING_SAFETY` is set (detects a leaked staging plugin).
- Requires the same `twins-staging-overhaul/bootstrap.php` — the env branch then
  swaps in the production adapters, and the form scan opens for the callback form.
- Enqueues `production-callback.js` after the shared overhaul script (which has no
  fetch by contract).

It deploys to `wp-content/mu-plugins/twins-overhaul.php`. Syntax validated.

## 4. The build-packages.mjs patch (proposal — NOT applied)

Additive: a new `kind: 'production'` path. The staging scope guard (line 89) is
**unchanged** — staging can still never include a production source. Default runs
(`build:packages`, `--check`) stay byte-identical.

**a. Production source allowlist** (top of file):
```js
const PRODUCTION_SOURCE_ALLOWLIST = new Set([
  'production-cutover/production-adapters.php',
  'production-cutover/production-callback.js',
  'production-cutover/production-overhaul-loader.php',
]);
```

**b. `validateManifest` gains `kind === 'production'`** — same closed/sorted/dup
checks as staging, plus its own identity and scope guard:
```js
if (kind === 'production' && (manifest.applicationIdentity !== 'https://twinsgaragedoors.com/' || manifest.environment !== 'production')) {
  fail('APPLICATION_IDENTITY_INVALID');
}
// ...inside the per-entry loop, mirroring the staging block...
if (kind === 'production') {
  // (dup destination + sorted checks, same as staging)
  if (entry.role === 'deploy') {
    const fromProdCutover = /(^|\/)production-cutover\//.test(entry.source);
    const destOk = /^(?:twins-brand-experience|twins-staging-overhaul)\//.test(entry.destination)
      || entry.destination === 'twins-overhaul.php';
    const sourceOk = !fromProdCutover || PRODUCTION_SOURCE_ALLOWLIST.has(entry.source);
    if (/\/tests\//.test(`/${entry.source}/`) || !destOk || !sourceOk) {
      fail('PRODUCTION_DEPLOY_SCOPE_INVALID', entry.source);
    }
  }
}
```
`productionWriteAuthority !== false` still hard-fails at the existing schema check
(line 70) — the production manifest must also carry `false`.

**c. Production build/check branch** — mirrors the staging branch into
`dist/production-runtime/` from `manifests/production-runtime.json` +
`manifests/production-verification.json`, gated behind an explicit argument
(`--production` / `--production-check`) so nothing changes on the default path:
```js
} else if (argument === '--production' || argument === '--production-check') {
  validateManifest(manifests.production, 'production');
  validateManifest(manifests.productionVerification, 'verification');
  // ...same copy (build) or closed+byte-exact (check) logic as staging,
  //    into dist/production-runtime/, productionWriteAuthority still false...
}
```

**d. Output** — on the production path, `status: PRODUCTION_PACKAGES_BUILT|VERIFIED`
and `productionPackage: 'BUILT'`. The default path still emits
`productionPackage: 'DEFERRED'`. `writeAuthority`/`productionWriteAuthority` stay
`false` on every path.

**e. Fixpoint chain** — `production-runtime.json` self-hash pinned into
`production-verification.json` (top of chain, self-unpinned), same discipline as
staging.

## 5. production-runtime.json — generated at authorized-build time

The deploy portion (86 files) is mechanically derivable: the 84 staging deploy
entries verbatim + the three rows in section 2, re-sorted by destination, each
size+sha256 pinned from source. NOT generated here because:

- The **verify-prerequisite set for production needs host inventory** (production
  access) — which of the assets/fonts/clopay catalog already exist on
  twinsgaragedoors.com (verify) vs must be shipped (deploy), and at what paths.
  `bootstrap.php` hardcodes asset URLs under
  `/wp-content/mu-plugins/twins-staging-assets/`, so that directory (or a renamed
  production equivalent, a cutover decision) must hold those files.
- Committing a manifest the sealed tool cannot read would rot silently.

So this stays a build-time step, done once access + the un-seal are in place.

## 6. Open cutover decisions (need production access)

- Verify-prerequisite resolution: inventory twinsgaragedoors.com; classify each
  asset as verify vs deploy; confirm the `twins-staging-assets` directory name (or
  rename and update `bootstrap.php` + the loader enqueue path together).
- Confirm no deployed overhaul file hard-requires `TWINS_STAGING_SAFETY` at
  runtime (grep before cutover; staging-only behaviors should live in the safety
  plugin, which is absent on production).
- Multisite: the loader is `Network: true`; confirm activation across blogs
  1/3/4/5 and that IL stays dark (productionEnabled already false).

## Deliberately NOT done here

The build-packages.mjs edit, the production manifests, and any deploy are held for
owner authorization + production access. Drafted and inert: the production
adapters, the callback JS, and the production loader — all syntax/interface
validated. This doc makes the un-seal a reviewable, mechanical change.
