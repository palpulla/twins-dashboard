# Private staging site unification handoff

**Owner:** Daniel

**Agent:** ChatGPT Profile 1 (`CHATGPT_PROFILE_1`)

**Branch:** `codex/staging-site-safety`

**Status:** `REMOTE_PREFLIGHT_COMPLETE — ONE-TIME STAGING DEPLOYMENT PENDING`

**Write authority:** `false`

**Production write authority:** `false`

## Current result

The repository candidate now contains the unified site-wide Twins chrome,
market-aware contact context, bounded review presentation, repaired Careers
experience, answer-first service pages, and the frozen local Clopay
catalog/builder presentation.

Task 9 adds the sealed, single-attempt staging release and read-only live
verification layer. The exact candidate has passed the PHP-enabled SiteGround
preflight, but it has not yet been deployed into the private staging web root.
Production remains untouched. The private staging URL must not be presented as
updated until every live gate below is completed.

## Candidate identity

- Local verification base: `3ff2c2305482` (`fix(staging): verify uploaded host bundle root`)
- Task 9 remote-preflight commit: the commit containing this handoff
- Deployed commit: `PENDING`
- Application identity: `https://danielj140.sg-host.com/`
- Candidate deploy package SHA-256:
  `c7ecfb396c829c08f62d1b7fb4eab63ad44e1b849d49a2d622b18bfde3884aaf`
- Candidate prerequisite-set SHA-256:
  `dadf04d0f2df09f7722451f6fb740ee66640247f781b9aaebf9a49598f9c5a77`
- Candidate host-verification SHA-256:
  `42e611d3bcd5758ed28c64094729075921d3532e2e1a8e71c79c1ba935f0c8bc`
- Candidate manifest SHA-256:
  `5c573314c8f9e1dfedae7d20f59652cd359dbcbd9c6d55e71b7abdb183e1d656`
- Deployed package identity: `PENDING — must be captured from the one successful deployment`

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

Current live evidence: `PENDING`

## Local verification completed

- Legacy staging-safety Node suite: **37 passed, 0 failed, 14 explicit PHP
  skips**.
- Brand contract suite: **73 passed, 0 failed**.
- Portable PHP suite: **33 explicit local skips, 0 failed** because PHP CLI is
  unavailable locally.
- Owned-asset check: **passed**.
- Package build and closed-package check: **passed**.
- Repository checker: `REPOSITORY_CHECK_PASSED`.
- Local Playwright suite: **31 passed, 0 failed, 66 authenticated-live skips**.
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

The one-time deployment, live browser matrix, and live crawler remain pending.

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

Completed live prerequisites:

1. Created and imported one temporary staging-only SSH key.
2. Confirmed the fixed staging host fingerprint.
3. Ran the full PHP-enabled remote preflight successfully.

Remaining live gates:

1. Capture the exact expected-old staging release.
2. Deploy the tested candidate exactly once with no automatic retry.
3. If the attempt conflicts or is indeterminate, stop; do not retry.
4. Purge only the private staging cache.
5. Run the authenticated 63-visit Playwright matrix.
6. Run the authenticated 63-visit crawler and capture the 36 success
    screenshots.
7. Record package identity, test output, and evidence paths here.
8. Remove the public key from SiteGround and delete the temporary local key.
9. Confirm the private staging site remains protected by HTTP Basic
    Authentication.
10. Perform owner visual review before any separate production-publication
    plan is considered.

`TWINS_STAGE_USER` and `TWINS_STAGE_PASSWORD` must be supplied only through the
process environment. They must never be written into commands, files,
screenshots, test artifacts, or logs.

## Safety boundary

The remote preflight accessed only the fixed private-staging SSH transaction
directory and read-only verification inputs. It did not modify the staging web
root, access WordPress administration, change DNS, or contact forms, booking,
email, SMS, leads, analytics destinations, or other production integrations.

Production pages, files, database state, menus, DNS, submissions, email, leads,
booking, and integrations remain unchanged by this local phase. Final live
closure must repeat that confirmation after the private staging deployment.

Publishing or changing production requires a separate explicit authorization,
production-specific backups, a new deployment plan, and independent
verification.
