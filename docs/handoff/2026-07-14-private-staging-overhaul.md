# Twins private staging overhaul handoff

**Owner:** Daniel

**Agent:** ChatGPT Profile 1 (`CHATGPT_PROFILE_1`)

**Branch:** `codex/staging-site-safety`

**Status:** `BRANCH_PUSH_PENDING`

**Production write authority:** `false`

## Review site

Private staging is live at:

`https://danielj140.sg-host.com/`

SiteGround HTTP Basic Authentication protects the host. Credentials are kept in
SiteGround/owner custody and are intentionally absent from Git and this handoff.

## What is staged

- A complete navy/yellow Twins homepage modeled on the approved Madison repair
  landing page rather than the former generic homepage.
- Larger logo crossing the yellow divider, readable navy-on-yellow phone number,
  prominent phone and estimate actions, and a restrained periodic CTA gleam.
- Both Twin characters visible and gently animated on mobile, with static output
  for reduced-motion users.
- A modern inset mobile action dock.
- Nunito body type and Lilita One display headings.
- Revised careers/employment and team experiences.
- Madison repair and tune-up landing pages.
- Wisconsin cost pages built from the approved cost-page reference.
- Wisconsin, Kentucky, and private-staging Illinois location/state surfaces.
- A local, non-submitting door-builder experience and bundled 23-product catalog.

## Direct review routes

- `/`
- `/careers/`
- `/our-team/`
- `/madison-garage-door-repair-lp/`
- `/madison-tune-up-lp/`
- `/wi/garage-door-cost-in-madison-wi/`
- `/wi/garage-door-cost-in-milwaukee-wi/`
- `/door-builder/`
- `/wi/door-builder/`
- `/ky/design-your-door/`
- `/il/door-builder/`
- `/il/`
- `/ky/location/lexington/`

## Completed live verification

- Browser matrix: **161/161** route/viewport visits, **0 violations**, at widths
  1440, 1201, 1024, 768, 390, 360, and 320; 14 screenshots captured.
- Whole-site crawl: **687/687 pages** and **1,335/1,335 assets**, zero failures,
  no caps, no production URLs, and no unexpected CSP-allowed external.
- Recovered repository suite: **48 tests**, 36 passed, zero failed, 12 expected
  local-PHP skips. All skipped PHP contracts passed on the staging host,
  including foundation, rendering, builder, cost, fail-closed bootstrap,
  route-state, legacy image, and Illinois provisioner harnesses.
- Final Illinois read-only status: `STAGING_IL_STATUS`, `EXACT`,
  `FRONTEND_INITIALIZED`, `productionWriteAuthority:false`,
  `stagingMutation:false`, `mismatches:[]`.
- SiteGround dynamic cache was purged after the final candidate.
- Both task-created `CHATGPT_PROFILE_1` SSH keys were removed. The recovery key
  was then rejected with `Permission denied (publickey)`, and its local private
  and public key files were deleted.

The exact deployed package was recovered after macOS cleared the disposable
worktree at midnight. The live staging site was not affected. Its 819-file source
archive is
`/home/customer/staging-safety/final-live-source-recovery-20260715.tar.gz`,
SHA-256
`3dc6bc90217307da4ecc445857b9bc3e15336192cf7f143df1d7e842b11cfb97`.

## Canonical live-source hashes

| Artifact | SHA-256 |
| --- | --- |
| Safety MU plugin | `0aedbd14df0ce5276b8400e6b4180af7eca0072e5403ac5d4280d6a01f9c6cd2` |
| Overhaul MU loader | `20a3e8b8d88917f54173457f112562c6a31250f9385a3144d9771704d63a2e90` |
| Bootstrap | `4d534364b37cb91a9a70bbb4b13fa2c50eba30b71dd8c2ab6d0022271dac8e22` |
| Renderers | `38f169dc26c0088aad872f0dc5f01214bcaa934f152b42a159959ba380f3012b` |
| Components | `dfc0548204787ca24743ebebc02099690a75ad3fdede21e8ba10fa488ac47556` |
| Home template | `a7c7fb3b8c16fddbd9089561dbaeae92c696f3c60a28540137d85c3be8d22dd5` |
| CSS | `a3fb61ed0da87e839d53fe4983cd7b0b4b67b844902abd7c8a8ab09e22b051a8` |
| JavaScript | `549faf277bbadc3d8a9cbbacacc682762a9584df23ab60c8fb98d4e9e031f0ae` |
| Builder template | `488ed5c9646f6bcb6271e4dbf518b4e9e003323f169c1de2a4e90f9ffc9d22af` |
| Nunito font | `ba344451eab25b217a165363b1982048a5e5830a0daf36577973955a04cac793` |
| Truck PNG | `ecd200b41f69334cf97c73bc9d85a3b59288b8174f2e9aae5c30fd27d9940bf3` |
| Truck WebP | `df91d2f10c7facc90fb336f8dd229d28e80d66c6ce9d79f6d0efdc32d7127e6e` |
| Local Clopay catalog | `ce960f1267327183719192d80d249f31c903a24e5fc6471992bed00dccda74f5` |

## Safety and rollback

Production was read/copied only. No production page, database, file, menu, DNS
record, integration, lead, email, SMS, booking, or analytics destination was
changed or exercised. The staged implementation grants no production write
authority.

The pre-overhaul database rollback is outside the web root:

`/home/customer/staging-safety/before-full-overhaul-20260714.sql.gz`

SHA-256:
`836dd8850730d4772956e041877cebd23d791700800a0a94588ebf1a9e12f374`

Rollback is staging-only: take the clone offline, restore this verified staging
snapshot or destroy/sanitize the clone, keep the safety MU plugin active until
restored production data is inaccessible, and rerun all safety/browser checks.
Never use rollback as authority to change production.

## Remaining closure gates

- Commit and push only `codex/staging-site-safety`.
- Change this status to `READY_FOR_VISUAL_APPROVAL` after that push is verified.

After that, Daniel's only step is visual approval of the private staged site.
Production publication, if desired later, requires a new explicit authorization,
new backups, a deployment plan, and production-specific verification.
