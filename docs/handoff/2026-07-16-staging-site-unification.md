# Private staging site unification handoff

**Owner:** Daniel

**Agent:** ChatGPT Profile 1 (`CHATGPT_PROFILE_1`)

**Branch:** `codex/staging-site-safety`

**Status:** `PRIVATE STAGING DEPLOYED — CORRECTIVE RELEASE CANDIDATE VERIFIED LOCALLY, NOT DEPLOYED`

**Write authority:** `false`

**Production write authority:** `false`

## Current result

The repository candidate now contains the unified site-wide Twins chrome,
market-aware contact context, bounded review presentation, repaired Careers
experience, answer-first service pages, and the frozen local Clopay
catalog/builder presentation.

Task 9's sealed, single-attempt release completed successfully on the private
SiteGround staging web root. Production remained untouched. Independent
unauthenticated verification proved that the staging origin still responds
with HTTP 401 and a Basic authentication challenge.

The first authenticated live audit then exposed one real shared-shell defect:
the legacy WordPress/Elementor header and footer remained visible around the
new portable header and footer. It also exposed two audit-harness defects: the
supposedly unauthenticated Playwright context inherited credentials, and the
static-asset allowlist rejected valid fixed Illinois multisite asset paths.

The corrective repository candidate now suppresses the exact Astra and
Elementor legacy chrome only on `body.twins-brand-experience`, fixes both audit
harness defects, and passes the complete local package, repository, and browser
suite. It has **not** been deployed. The original release's one-attempt/no-retry
contract remains honored; any corrective live release must use a new fixed
transaction and a new exact expected-old capture.

## Candidate identity

- Deployed commit: `350d64bfa4555245c2fa3a54b7ff18aa389ab4cc`
- Application identity: `https://danielj140.sg-host.com/`
- Deployed manifest SHA-256:
  `5c573314c8f9e1dfedae7d20f59652cd359dbcbd9c6d55e71b7abdb183e1d656`
- Deployed package SHA-256:
  `c7ecfb396c829c08f62d1b7fb4eab63ad44e1b849d49a2d622b18bfde3884aaf`
- Deployed prerequisite-set SHA-256:
  `dadf04d0f2df09f7722451f6fb740ee66640247f781b9aaebf9a49598f9c5a77`
- Deployed host-verification SHA-256:
  `42e611d3bcd5758ed28c64094729075921d3532e2e1a8e71c79c1ba935f0c8bc`
- Corrective candidate manifest SHA-256:
  `9c272ddc602a965cd131e5d3d5d92ab433d885477bf8efa4f34089e0ccbe9361`
- Corrective candidate deploy package SHA-256:
  `667c4f77a71951ea8df067971d1e31059de98c382615e954e7080ded01a1b8fb`
- Corrective candidate prerequisite-set SHA-256:
  `dadf04d0f2df09f7722451f6fb740ee66640247f781b9aaebf9a49598f9c5a77`
- Corrective candidate host-verification SHA-256:
  `8514685ca23544a1a6b3a9070b2f102fa93105413f7205cdc06e53cc792103e9`

## Exact live verification matrix

The authenticated Playwright specification and crawler are fixed to these nine
routes:

- `/`
- `/il/`
- `/reviews/`
- `/contact-us/`
- `/careers/`
- `/garage-door-spring-repair/`
- `/clopay-garage-doors/`
- `/clopay-gallery-steel/?product=12`
- `/door-builder/`

Every route must be verified at:

- 1440px
- 1200px
- 900px
- 768px
- 390px
- 360px
- 320px

For all 63 route/viewport visits, verification requires:

- HTTP status below 400;
- an `X-Robots-Tag` response containing `noindex`;
- exactly one `.twins-brand-header` and one `.twins-brand-footer`;
- no `.twins-overhaul-header`;
- exactly one visible H1;
- a viewport-wide first major section;
- no horizontal overflow;
- no form or submission surface; and
- only `GET` or `HEAD` requests to the fixed staging origin.

The crawler captures full-page evidence for all nine routes at 1440px, 768px,
390px, and 320px: 36 success screenshots, plus bounded failure evidence when a
gate fails. Evidence path after the live crawl:

`website/twins-brand-experience/test-results/staging-crawl/`

First live audit result:

- privacy boundary: **passed** independently with HTTP 401 and
  `WWW-Authenticate: Basic`;
- inert same-origin interactions: **passed**;
- reduced-motion mobile Twins: **passed**;
- 55 route/viewport visits reported `HEADER_SECTION_GAP` because the real
  legacy host header remained between the portable header and first section;
- Illinois visits were rendered without fixed multisite assets by an audit
  allowlist bug, not by a runtime asset failure;
- the Playwright Basic-auth failure was a harness inheritance bug; the
  credential-independent crawler proved the real boundary;
- crawler result: `PRIVATE_STAGING_CRAWL_FAILED`, 0 accepted visits and 63
  bounded failures, with no write authority and no staging mutation.

The failure screenshots were inspected before the subsequent local Playwright
run replaced the ephemeral `test-results` directory. A new corrective live
release must regenerate durable evidence rather than treating the first audit
as acceptance evidence.

## Local verification completed

- Legacy staging-safety Node suite: **37 passed, 0 failed, 14 explicit PHP
  skips**.
- Brand contract suite: **73 passed, 0 failed**.
- Portable PHP suite: **33 explicit local skips, 0 failed** because PHP CLI is
  unavailable locally.
- Owned-asset check: **passed**.
- Package build and closed-package check: **passed**.
- Repository checker: `REPOSITORY_CHECK_PASSED`.
- Corrective local Playwright suite: **31 passed, 0 failed, 66 authenticated-live skips**.
- Local crawler invocation without credentials:
  `PRIVATE_STAGING_CRAWL_SKIPPED`,
  `writeAuthority:false`, `productionWriteAuthority:false`,
  `stagingMutation:false`.
- Temporary staging-only SSH identity was accepted for the fixed staging host;
  the independently confirmed host-key fingerprint is
  `SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4`.
- PHP 8.2 SiteGround host verification: **passed** for the portable core,
  renderer contracts, review codec, 21 deployment-safety scenarios, foundation,
  overhaul, builder, cost, bootstrap, all 20 renderer scenarios, owned brand
  assets, adapters, legacy image isolation, Illinois provisioning, chrome
  transition, and WordPress safety report.
- Fixed-target remote dry-run: `PRIVATE_STAGING_DRY_RUN_PASSED`,
  `writeAuthority:false`, `productionWriteAuthority:false`.
- One-time deployment: `PRIVATE_STAGING_DEPLOYED`, `writeAuthority:false`,
  `productionWriteAuthority:false`.
- Private-cache purge was attempted once with the fixed SiteGround command and
  was unavailable on the host. It was not retried.
- Corrective repository verification: **73/73 contracts passed**, package build
  and closed-package check passed, `REPOSITORY_CHECK_PASSED`, and **31/31 local
  browser tests passed**. The PHP-enabled host suite was already green for the
  deployed source family; a new corrective release must repeat its own fixed
  remote preflight.

## Release safety implemented

- The fixed staging targets are exactly `twins-brand-experience` and
  `twins-staging-overhaul`.
- Existing target bytes are captured before deployment and checked again
  immediately before mutation.
- Candidate copies are closed, nonempty, hash-pinned, PHP-checked remotely,
  and rechecked after copying into their incoming paths.
- Activation moves each expected-old target to a fixed same-filesystem backup,
  tracks the exact per-target phase (`unprocessed`, `backed-up`, or
  `activated`), and compensates only when the observed state exactly matches
  that attempt.
- Partial activation, activation deletion, activation successor drift, and an
  activation callback that returns after modifying bytes all fail closed.
- The complete activated target set is compared to the manifest after the
  WordPress activation probe and before backups are removed.
- Local and remote deploy-attempt markers are exclusive, and no push,
  transport, or deployment operation is retried automatically.
- Remote stderr must be empty, and every successful remote result must be one
  exact JSON envelope with the expected operation, application identity,
  environment, manifest hash, deploy-package hash, prerequisite-set hash, and
  both authority fields set to `false`.

## Illinois contact caveat

The private Illinois preview displays:

- `(815) 800-2025`
- `tel:+18158002025`

Display and route-context behavior are covered by repository contracts. Actual
call forwarding has not been verified and must remain an owner-reviewed fact;
no test call is authorized by this task.

## Claude content review

Claude Opus 4.8 work on `claude/staging-content-aeo` was reviewed selectively.
No Claude commit was cherry-picked and no unsafe runtime content was imported.
The current fixed service records remain the approved runtime source.

The full disposition is recorded in
`docs/handoff/2026-07-16-claude-content-integration.md`.

## Remaining live gates

1. Create a new fixed corrective-release transaction; do not reuse or erase the
   consumed `staging-unification-20260716` attempt.
2. Repeat the fixed PHP-enabled remote preflight for the exact corrective
   candidate.
3. Capture the exact expected-old staging targets, then perform one exact-CAS
   corrective deployment with no automatic retry.
4. If that attempt conflicts or is indeterminate, stop; do not retry.
5. Run the corrected authenticated 63-visit Playwright matrix and 63-visit
   crawler, capture all required screenshots, and confirm zero layout,
   contrast, network, or interaction failures.
6. Independently reconfirm HTTP 401 Basic authentication and `noindex`.
7. Remove the temporary public key from SiteGround and delete the local private
   key after the final remote verification.
8. Complete owner visual review before any separate production-publication
   plan is considered.

`TWINS_STAGE_USER` and `TWINS_STAGE_PASSWORD` must be supplied only through the
process environment. They must never be written into commands, files,
screenshots, test artifacts, or logs.

## Safety boundary

The completed release modified only the fixed private-staging MU-plugin targets.
It did not access WordPress administration, change DNS, or contact forms,
booking, email, SMS, leads, analytics destinations, or other production
integrations. All live verification was read-only.

Production pages, files, database state, menus, DNS, submissions, email, leads,
booking, and integrations remain unchanged by this local phase. Final live
closure must repeat that confirmation after the private staging deployment.

Publishing or changing production requires a separate explicit authorization,
production-specific backups, a new deployment plan, and independent
verification.
