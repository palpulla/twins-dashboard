# Private staging site unification handoff

**Owner:** Daniel

**Agent:** ChatGPT Profile 1 (`CHATGPT_PROFILE_1`)

**Branch:** `codex/staging-site-safety`

**Status:** `TASK_9_LOCAL_VERIFICATION_COMPLETE — LIVE DEPLOYMENT PENDING`

**Write authority:** `false`

**Production write authority:** `false`

## Current result

The repository candidate now contains the unified site-wide Twins chrome,
market-aware contact context, bounded review presentation, repaired Careers
experience, answer-first service pages, and the frozen local Clopay
catalog/builder presentation.

Task 9 adds the sealed, single-attempt staging release and read-only live
verification layer. Nothing in this local phase was deployed to SiteGround,
WordPress, or production. The private staging URL must not be presented as
updated until every live gate below is completed.

## Candidate identity

- Local verification base: `40810b96` (`fix(staging): resolve navigation and contrast blockers`)
- Task 9 local verification commit: the commit containing this handoff
- Deployed commit: `PENDING`
- Application identity: `https://danielj140.sg-host.com/`
- Candidate deploy package SHA-256:
  `1452014968413512edb23fbf70c2a7d08137d283dcd7d60e6c453f16a9d42603`
- Candidate prerequisite-set SHA-256:
  `dadf04d0f2df09f7722451f6fb740ee66640247f781b9aaebf9a49598f9c5a77`
- Candidate host-verification SHA-256:
  `d02113f4da07db501dcbfd12aab6cb460dfe3ee9781d266f82b362e2a9917a7f`
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
- Deployment dry-run without transport authority failed closed as
  `TRANSPORT_CONFIGURATION_REQUIRED`; no network or host access occurred.

The PHP-backed host verification, remote dry-run, live browser matrix, and live
crawler remain pending.

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

All items below are still pending:

1. Create one temporary staging-only SSH key.
2. Import only its public key into SiteGround.
3. Confirm the SSH fingerprint through approved SiteGround evidence.
4. Run the remote staging dry-run and PHP-enabled host verification.
5. Capture the exact expected-old staging release.
6. Deploy the tested candidate exactly once with no automatic retry.
7. If the attempt conflicts or is indeterminate, stop; do not retry.
8. Purge only the private staging cache.
9. Run the authenticated 63-visit Playwright matrix.
10. Run the authenticated 63-visit crawler and capture the 36 success
    screenshots.
11. Record package identity, test output, and evidence paths here.
12. Remove the public key from SiteGround and delete the temporary local key.
13. Confirm the private staging site remains protected by HTTP Basic
    Authentication.
14. Perform owner visual review before any separate production-publication
    plan is considered.

`TWINS_STAGE_USER` and `TWINS_STAGE_PASSWORD` must be supplied only through the
process environment. They must never be written into commands, files,
screenshots, test artifacts, or logs.

## Safety boundary

The local Task 9 work did not access SiteGround, WordPress administration, DNS,
production hosting, forms, booking, email, SMS, leads, analytics destinations,
or other production integrations.

Production pages, files, database state, menus, DNS, submissions, email, leads,
booking, and integrations remain unchanged by this local phase. Final live
closure must repeat that confirmation after the private staging deployment.

Publishing or changing production requires a separate explicit authorization,
production-specific backups, a new deployment plan, and independent
verification.
