# Private staging overhaul completion plan

**Spec:**
`docs/superpowers/specs/2026-07-14-private-staging-overhaul-completion-design.md`

**Branch:** `codex/staging-site-safety`

**Status:** `READY_FOR_VISUAL_APPROVAL`

## Goal

Deliver the complete Twins website overhaul on the private SiteGround staging
clone, verify the entire staged multisite without enabling production effects,
recover the exact live source into Git, and hand the result to Daniel for visual
approval. Production publication is a separate, future authorization.

## Hard rules

- Work only on the private staging host and this task branch.
- Keep SiteGround Basic Authentication, staging safety constants, noindex,
  disabled cron, mail suppression, and default-deny egress in place.
- Never submit a real form, send a real message, change DNS, change production,
  enable a production integration, merge to `main`, or infer publish authority.
- Use same-origin local assets and deterministic local data.
- Preserve exact rollback evidence outside the web root.
- Treat every failed or incomplete safety proof as a stop condition.

## Execution record

### 1. Protect the clone

- [x] Confirm the target is `danielj140.sg-host.com`, not production.
- [x] Install and validate the fail-closed staging MU plugin.
- [x] Require the staging constants and `DISABLE_WP_CRON`.
- [x] Suppress mail, server-side WordPress HTTP, queues, and indexing.
- [x] Retain hosting-level HTTP Basic Authentication and isolation.
- [x] Capture the pre-overhaul database rollback snapshot outside the web root.

### 2. Implement the overhaul with tests first

- [x] Define route/component contracts before changing renderers.
- [x] Build shared components and templates for home, service, article, trust,
  location, cost, and builder pages.
- [x] Add local fonts, Twins imagery, truck artwork, Clopay catalog, and builder
  assets with documented provenance.
- [x] Reproduce approved Madison landing-page visual language on the homepage.
- [x] Deliver revised careers, cost, location/state, and builder surfaces.
- [x] Add the larger crossing logo, readable phone treatment, prominent phone
  and estimate CTAs, periodic gleam, mobile Twin characters, and modern mobile
  action dock.
- [x] Respect reduced motion and keep interactions visual-only on staging.
- [x] Preserve existing staging status and safety behavior.

### 3. Verify the staged multisite

- [x] Run the Node/PHP contract suite on the deployed candidate: 89 tests, 77
  passed, zero failed, and 12 expected local-PHP skips.
- [x] Run staging-host PHP harnesses and verify the live safety/renderer gates.
- [x] Run browser QA at 1440, 1201, 1024, 768, 390, 360, and 320: 161/161
  visits, zero violations, 14 screenshots.
- [x] Run the uncapped staged crawl: 687/687 pages and 1,335/1,335 assets, zero
  failures, no production URLs, no unexpected CSP-allowed external.
- [x] Purge the SiteGround dynamic cache after the final candidate.
- [x] Verify Illinois read-only status as `EXACT`, with no mismatch and no
  production write authority.

### 4. Recover the canonical live source

- [x] Create a short-lived SiteGround SSH recovery key scoped to this task.
- [x] Archive the exact deployed source outside the web root.
- [x] Download the archive and verify matching SHA-256 on both sides.
- [x] Recover all 819 files into the persistent task worktree.
- [x] Verify the principal recovered files against their live SHA-256 values.
- [x] Restore/rebuild the repository-side tests and host harnesses lost with the
  cleared disposable worktree.
- [x] Run the complete recovered repository suite: 48 tests, 36 passed, zero
  failed, and 12 expected local-PHP skips; run every skipped contract on staging.
- [x] Run repository integrity, secret, production-domain, and diff checks.

### 5. Close temporary access and publish the branch

- [x] Remove both task-created SiteGround SSH keys while preserving unrelated
  keys.
- [x] Prove the recovery key no longer authenticates and remove its local files.
- [x] Obtain a final independent implementation/safety review.
- [x] Update this plan and the handoff to `READY_FOR_VISUAL_APPROVAL`.
- [x] Commit the complete recovered package and evidence on
  `codex/staging-site-safety`.
- [x] Push that branch normally; do not merge it into `main`.

## Acceptance evidence

The closing handoff must contain:

- the private staging URL and a note that Basic Auth credentials are out of Git;
- direct review routes for the homepage, careers, Madison landing pages, cost
  pages, builder, and WI/KY/IL surfaces;
- the browser and crawl counts;
- the canonical live-source hashes and source-recovery archive hash;
- the rollback snapshot path and hash;
- confirmation that the SiteGround recovery keys were removed;
- the final test/check results and pushed branch commit;
- an explicit statement that production, DNS, and live integrations were not
  changed.

## Stop conditions

Stop and report rather than weakening a gate if the live source differs from the
recorded digests, staging cannot be proven isolated, a temporary key cannot be
removed, any test or crawl is incomplete, or a requested action would mutate
production. Visual approval does not authorize production publication.
