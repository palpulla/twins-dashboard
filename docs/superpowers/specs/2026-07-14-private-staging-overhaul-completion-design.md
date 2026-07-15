# Private staging overhaul completion design

**Date:** 2026-07-14

**Branch:** `codex/staging-site-safety`

**Lease account:** `CHATGPT_PROFILE_1`

**Status:** `READY_FOR_VISUAL_APPROVAL`

This specification defines the finished private-staging experience for the Twins
Garage Doors WordPress multisite. The live staging implementation and its source
package have been recovered byte-for-byte. The recovered repository suite and
staging-host PHP harnesses pass, both task-created SiteGround SSH keys have been
removed, and the verified source tree is committed and pushed on this branch.

## Objective

Present the complete website overhaul on a private staging clone so Daniel can
review the whole experience before any separately authorized production change.
The staged site must combine the strongest elements of the Madison repair
landing page with the revised careers, cost, state/location, and door-builder
experiences while remaining visibly Twins branded and safe to browse.

## Fixed safety boundary

- The only deployment target is `https://danielj140.sg-host.com/`.
- SiteGround HTTP Basic Authentication remains enabled. Credentials are managed
  outside Git and are never recorded in this repository.
- Production may be read to build the clone, but it must not be changed.
- No DNS change, production publication, real lead, email, SMS, analytics event,
  chat message, booking, or production integration is authorized.
- The staging clone stays `noindex`, mail-suppressed, cron-disabled, and
  fail-closed on server-side WordPress HTTP requests.
- Default-deny hosting egress and disabled hosting cron remain mandatory; the MU
  plugin is defense in depth, not the sole isolation boundary.
- Any staging CTA or form surface is a visual-review artifact only. Testing uses
  synthetic data and must not reach a real recipient.
- This work grants no production write authority and creates no approval to copy
  the staged implementation to production.

## Architecture

The implementation is a staging-only WordPress MU-plugin package:

1. `twins-staging-safety.php` proves the staging constants and installs the
   fail-closed mail, network, cron, indexing, queue, and shortcode controls.
2. `twins-staging-overhaul.php` loads the overhaul only after the safety gate.
3. `twins-staging-overhaul/bootstrap.php` resolves network/site context and
   initializes local rendering.
4. `routes.php`, `data.php`, and `cost-data.php` provide deterministic route and
   content data without remote runtime dependencies.
5. `components.php`, `renderers.php`, and `templates/*.php` render shared page
   families and route-specific experiences.
6. `twins-staging-assets/` contains same-origin CSS, JavaScript, fonts, Twins
   artwork, truck imagery, and the local Clopay builder catalog.

The package must remain independently removable from the clone. It does not
replace production theme or Elementor content and is not installed on
production.

## Experience design

### Shared header and homepage

- Use the Twins navy/yellow brand system, Nunito for body copy, and Lilita One
  for prominent display headings.
- Allow the larger Twins logo to cross the yellow header divider slightly.
- Render the phone number in high-contrast navy on yellow.
- Make both phone and estimate actions prominent, with a restrained periodic
  gleam that respects `prefers-reduced-motion`.
- Preserve the two Twin characters in the mobile hero, at a reduced scale with
  gentle motion; reduced-motion users receive a static composition.
- Use a modern inset mobile action dock instead of legacy full-width buttons.
- Carry Madison landing-page elements—offer, urgent service promise, trust proof,
  repair categories, callback path, and local-brand visual language—into the
  staged homepage without enabling a live lead flow.

### Page families

- Revised careers/employment and team pages.
- Madison repair and tune-up landing pages.
- Wisconsin cost pages derived from the approved cost-page reference.
- State and location landing pages for Wisconsin, Kentucky, and the isolated
  Illinois staging site.
- A locally rendered door builder backed by the bundled catalog and imagery.
- Shared service, article, trust, cost, location, and builder templates so the
  overhaul is consistent across the staged network.

### Information architecture

The staged menus and routing support the state-first structure while preserving
existing public paths needed for review. The Illinois surface remains staging
only. No production menu, page, or network record is changed by this package.

## Verification contract

Completion requires all of the following:

- Complete repository tests pass with no unexpected skip or failure.
- PHP harnesses pass either locally or on the isolated staging host when local
  PHP is unavailable.
- Browser QA covers widths 1440, 1201, 1024, 768, 390, 360, and 320 and reports
  no horizontal overflow, broken local navigation, missing critical assets, or
  production integration authority.
- Whole-site crawl covers every discovered staged page and asset with no caps,
  no failures, no production URLs, and no unexpected CSP-allowed external.
- Desktop and mobile review proves the logo, phone contrast, two header actions,
  mobile Twin characters, action dock, animation, and reduced-motion behavior.
- The staging safety plugin stays active and the current Illinois network status
  remains exact and read-only.
- A source snapshot and a database rollback snapshot exist outside the web root.
- Temporary SiteGround recovery access is removed after verification.
- Only `codex/staging-site-safety` is committed and pushed; no merge is part of
  this work.

The previously completed live QA recorded 161/161 browser route/viewport visits
with zero violations and a complete 687-page/1,335-asset crawl with zero
failures. The recovered suite now records 48 tests: 36 passed, zero failed, and
12 expected local-PHP skips; every skipped PHP contract was run successfully on
the isolated staging host.

## Canonical live-source digests

| Artifact | SHA-256 |
| --- | --- |
| Safety MU plugin | `0aedbd14df0ce5276b8400e6b4180af7eca0072e5403ac5d4280d6a01f9c6cd2` |
| Overhaul MU loader | `20a3e8b8d88917f54173457f112562c6a31250f9385a3144d9771704d63a2e90` |
| Bootstrap | `4d534364b37cb91a9a70bbb4b13fa2c50eba30b71dd8c2ab6d0022271dac8e22` |
| Renderers | `38f169dc26c0088aad872f0dc5f01214bcaa934f152b42a159959ba380f3012b` |
| Components | `dfc0548204787ca24743ebebc02099690a75ad3fdede21e8ba10fa488ac47556` |
| Home template | `a7c7fb3b8c16fddbd9089561dbaeae92c696f3c60a28540137d85c3be8d22dd5` |
| Overhaul CSS | `a3fb61ed0da87e839d53fe4983cd7b0b4b67b844902abd7c8a8ab09e22b051a8` |
| Overhaul JavaScript | `549faf277bbadc3d8a9cbbacacc682762a9584df23ab60c8fb98d4e9e031f0ae` |
| Builder template | `488ed5c9646f6bcb6271e4dbf518b4e9e003323f169c1de2a4e90f9ffc9d22af` |
| Nunito font | `ba344451eab25b217a165363b1982048a5e5830a0daf36577973955a04cac793` |
| Truck PNG | `ecd200b41f69334cf97c73bc9d85a3b59288b8174f2e9aae5c30fd27d9940bf3` |
| Truck WebP | `df91d2f10c7facc90fb336f8dd229d28e80d66c6ce9d79f6d0efdc32d7127e6e` |
| Local Clopay catalog | `ce960f1267327183719192d80d249f31c903a24e5fc6471992bed00dccda74f5` |

The recovery archive at
`/home/customer/staging-safety/final-live-source-recovery-20260715.tar.gz`
has SHA-256
`3dc6bc90217307da4ecc445857b9bc3e15336192cf7f143df1d7e842b11cfb97`.

## Rollback and recovery

The pre-overhaul database snapshot is outside the web root at
`/home/customer/staging-safety/before-full-overhaul-20260714.sql.gz`, SHA-256
`836dd8850730d4772956e041877cebd23d791700800a0a94588ebf1a9e12f374`.

Rollback is staging-only and fail-closed: remove public access, restore the
verified staging snapshot or destroy/sanitize the clone, keep safety controls in
place until restored production data is inaccessible, and rerun the complete
safety and browser verification. Rollback never means changing production.

## Approval state

The live staged design is available for review. Repository verification,
temporary-key cleanup, commit, and branch publication are complete. The current
state is `READY_FOR_VISUAL_APPROVAL`; this is not production publish authority.
