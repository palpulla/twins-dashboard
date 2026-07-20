# Owner-gate checklist — the road to the go-decision

The gates only the owner can clear before the production cutover
(twinsgaragedoors.com). This is the decision tracker; `release-runbook.md` is the
execution sequence that runs *after* this checklist is all green. Work top to
bottom; the final go-decision (section E) is a single authorization once every
gate above it is checked.

Status legend: **[decided]** owner already chose (confirm still current) ·
**[open]** needs an owner answer · **[owner-provides]** needs access/an artifact
from the owner · **[authorize]** needs an explicit owner go.

Nothing here deploys anything. The engineering side is staged and validated on
`danielj140.sg-host.com`; these gates are what unlock building and shipping to
production.

## A. Business decisions

- [ ] **Clopay licensing / door-builder matrix** — [open]. Ship the builder
      matrix or explicitly defer it for launch. Done = "shipping with matrix" or
      "launching without it, add later" written down. Blocks nothing else if
      deferred; the builder route already renders.
- [x] **Hormann line** — [decided: retire]. `/hormann-garage-doors/` redirects to
      `/clopay-garage-doors/` (redirect-plan Finding 4). Confirm still the call.
- [x] **Illinois market** — [decided: dark at launch]. IL stays unpublished
      (`productionEnabled: false`); confirm no IL forwarding is expected day one.
- [x] **Lead flows** — [decided 2026-07-17]. Booking = Housecall Pro; Quote =
      callback form → `lp-lead-intake`; Careers = external application. Confirm
      the HCP link and the edge-function endpoint are still current.
- [ ] **Blog prune list** — [open]. Approve the list of posts to prune/redirect
      to `/blog/` (redirect-plan Finding 4 notes the list is pending owner
      approval; off-topic HVAC/paint/energy posts included only if approved).

## B. Content sign-off

- [ ] **Page-by-page sign-off** — [open]. Final owner OK on the staged site, page
      by page (most were walked in the sign-off session). Done = every page type
      approved or its change noted.
- [ ] **Review numbers current** — [open]. The site shows 4.9 / 699. Refresh from
      the live Google listing at cutover so launch numbers are accurate.
- [x] **FAQ / body copy** — [decided]. The 9-question FAQ and Wave 1–4 copy are
      owner-approved. Confirm no wording changes wanted before launch.
- [x] **Addresses** — [decided]. Madison WI (2921 Landmark Pl #206), KY Mt
      Sterling (3651 Aarons Run Rd, confirmed current). Confirm no change.

## C. Access + infrastructure (owner provides)

- [ ] **Production WordPress admin access** — [owner-provides]. Confirmed admin on
      twinsgaragedoors.com (needed for the 3 service pages, redirects, OTTO
      removal, verification).
- [ ] **SiteGround production access** — [owner-provides]. Site Tools + SSH for the
      production site (needed for deploy, host inventory, cache flush). Note: the
      re-issued deploy key is for *staging* only; production access is separate.
- [ ] **Full-site backup, verified restorable** — [owner-provides]. BlogVault (or
      equivalent) full backup of production, restore-tested, plus a fresh DB dump
      saved off-host. This is the rollback floor; do not proceed without it.
- [ ] **Search Atlas / OTTO dashboard login** — [owner-provides]. Needed to
      disconnect the site and revoke the API key during OTTO removal
      (`otto-removal-runbook.md`).

## D. Authorization (explicit owner go)

- [ ] **Authorize the build-tool un-seal (Blocker A)** — [authorize]. The
      production package cannot be assembled until the sealed `build-packages.mjs`
      is un-sealed. It is designed and reviewable (`blocker-a-build-unseal.md`),
      not applied. This is a one-way loosening of a safety guard; authorize it as
      its own reviewed change.
- [ ] **Authorize the deploy window** — [authorize]. Pick the go-live day/time
      (low-traffic window; file-atomic deploy, so no long downtime).

## E. THE GO-DECISION

Check only when every box in A–D is green:

- [ ] **GO.** All business decisions made, content signed off, access + verified
      backup in hand, un-seal and deploy window authorized. Proceed to
      `release-runbook.md` step 1.

On GO, the ordered execution (from the runbooks, all owner-authorized) is:
1. Host inventory → generate `production-runtime.json`; apply the un-seal; build
   the production package; `check:packages`.
2. On production: create the 3 service pages (`/garage-door-repair/`,
   `/garage-door-tune-up/`, `/protection-plans/`), execute redirect Findings 3/4,
   remove OTTO.
3. Deploy the production package (production transaction); flush cache.
4. Immediate verification (release-runbook step 5); watch week one.

## Quick status — what is actually blocking GO right now

- **Owner answers needed:** Clopay/builder-matrix call, blog prune list, final
  page-by-page sign-off, refreshed review numbers.
- **Owner must provide:** production WP admin + SiteGround access, verified
  restorable backup, Search Atlas login.
- **Owner must authorize:** the Blocker A un-seal, the deploy window.
- **Not blocking (done):** all staged engineering — Waves 1–4, design fixes,
  Blocker B, brand-runtime env branch, production adapters/loader/callback,
  Blocker A design. Everything inert-on-staging is complete and validated.
