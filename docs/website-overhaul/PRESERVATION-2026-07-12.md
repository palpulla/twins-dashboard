# Website overhaul preservation record

## Purpose

This branch preserves the tracked, publish-safe repository artifacts from the July 8–11, 2026 Twins Garage Doors WordPress overhaul. It is an archival and handoff snapshot only. It does not attest current WordPress state, grant write authority, deploy code, or authorize a global chrome cutover.

## Provenance

- Source repository: `palpulla/twins-dashboard`
- Source commit: `76020481df2f69abe58d0b13f67ebe3155f84f93`
- Source boundary: final website-specific commit before unrelated text-agent work resumed
- Target base branch: `main`
- Target base commit: `1754fd2904df42b2e896a9a2a6ee6d56bf1f80c2`
- Import method: allowlisted `git archive` snapshot; no merge, rebase, or cherry-pick from the mixed local branch
- Imported artifact count: 115
- Imported artifact bytes: 2,524,485

The source checkout was dirty and 232 commits ahead of its remote. No working-tree or untracked content was copied. Every imported artifact came directly from the pinned source commit object.

## Preserved artifact groups

| Group | Files | Description |
| --- | ---: | --- |
| July 8 Clopay authored code | 3 | Early Clopay augmentation, lead endpoint, and page builder source |
| Phase 1 | 1 | Supporting icon map; raw rendered baselines intentionally omitted |
| Phase 2 | 6 | Main/WI menu snapshots and snippet changes |
| Phase 3 | 7 | Builder and endpoint source plus authored local harness |
| Phase 4 | 47 | 23-product API snapshot, page builder, content pack, redirect map, snippets, QA tools, and approved mockup |
| Phase 5A | 15 | WI hub pre-change exports, snippets, phone/NAP notes, and reconstruction artifacts |
| Phase 5B | 10 | Original chrome exports, initial clone CSS, execution record, and Codex package |
| Phase 5C | 3 | Madison/Milwaukee cost-page payloads and generator |
| Specs | 15 | Approved designs, handoffs, chrome runbook, and builder scope |
| Plans | 7 | Phase implementation plans |
| Research | 1 | Phase 4 research digest |

## Privacy handling

The target repository is public. The following source material was intentionally excluded:

1. `docs/marketing/change-log.md`, because website history is interleaved with unrelated advertising, CRM, SMS, lead, and operational records.
2. Seventeen full rendered live-page HTML captures, because they contain unnecessary runtime material and may contain expired WordPress nonces, public-review data, and other page-state details inappropriate for a public preservation branch.
3. All dirty and untracked source-checkout content.

The machine's public IP was redacted from these imported documents while preserving their operational meaning:

- `docs/superpowers/plans/2026-07-09-phase2-menu-restructure.md`
- `docs/superpowers/specs/2026-07-09-phase2-menu-restructure-design.md`
- `docs/superpowers/specs/2026-07-09-twins-web-program-handoff.md`
- `docs/superpowers/specs/2026-07-10-phase5-B-NEXT-SESSION-handoff.md`
- `docs/superpowers/specs/2026-07-10-phase5-wi-buildout-design.md`

All other imported files are intended to remain byte-identical to the pinned source commit.

## Omitted raw-capture provenance

These Git blob IDs identify the intentionally omitted captures in source commit `7602048`. The files are not part of this branch.

| Git blob | Source path |
| --- | --- |
| `1cfb36f6eaa8f7a72648c45a30490cb59442f65a` | `docs/superpowers/backups/2026-07-08-clopay-pages/ky-clopay-classic-6198.html` |
| `f284570e6ab798b35b443b4e7755d127ebbe0fd3` | `docs/superpowers/backups/2026-07-08-clopay-pages/main-clopay-classic-6034.html` |
| `df43c2f99c497717bab18ddcae828164ae09900f` | `docs/superpowers/backups/2026-07-08-clopay-pages/main-clopay-gallery-steel-6065.html` |
| `b0be15738db91860e9dbcc5d6bada4d8865f5a7c` | `docs/superpowers/backups/2026-07-08-clopay-pages/main-clopay-modern-steel-6090.html` |
| `5a495c2de3e171254d86fe2033d855bf7a72126d` | `docs/superpowers/backups/2026-07-09-phase1/baseline-ky-6198.html` |
| `f6b6bd032939bf68d10b722ae31d8243fe5364b1` | `docs/superpowers/backups/2026-07-09-phase1/baseline-ky-6378.html` |
| `61aa63a59c4cfce887d3b16b5262ce0326fc39f1` | `docs/superpowers/backups/2026-07-09-phase1/baseline-ky-6379.html` |
| `87e6132c4bee7d0fa5eeb8caf5b164df501c3df4` | `docs/superpowers/backups/2026-07-09-phase1/baseline-ky-6386.html` |
| `33d1826c86b3ca929b6705508b47d80b6ebde800` | `docs/superpowers/backups/2026-07-09-phase1/baseline-main-6034.html` |
| `150c39dea340e92d02dfac9297229819ef864a93` | `docs/superpowers/backups/2026-07-09-phase1/baseline-main-6065.html` |
| `c54eaba91af82650c19429a184191a469f58a70b` | `docs/superpowers/backups/2026-07-09-phase1/baseline-main-6090.html` |
| `cf14f0095274952338a39e62661d940f7275bca0` | `docs/superpowers/backups/2026-07-09-phase1/baseline-main-7073.html` |
| `6def2d2cb9e729cc36ae9c4d71d12fba339acdc5` | `docs/superpowers/backups/2026-07-09-phase1/baseline-wi-6756.html` |
| `681ad52041357a188569ceb76b0ff3c32fc9a7af` | `docs/superpowers/backups/2026-07-09-phase3-door-builder/funnel-6756-before.html` |
| `6e98ee695d821b5521ee5295457e37602b304439` | `docs/superpowers/backups/2026-07-09-phase3-door-builder/funnel-7073-before.html` |
| `c556ff9fe2c826336fe5a3e96c3e81120dbab8e1` | `docs/superpowers/backups/2026-07-09-phase3-door-builder/page-main-door-builder-built.html` |
| `46e409857d02eae1972834ef45b0d0fc581a704a` | `docs/superpowers/backups/2026-07-09-phase3-door-builder/page-wi-door-builder-built.html` |

References to these files in historical specs are evidence pointers only and will not resolve in this branch.

## Read-only live takeover observations

Codex performed a public, read-only browser audit on July 12, 2026. No form was submitted and no WordPress or hosting mutation occurred.

- The Clopay catalog hub exposed all 23 collection links.
- WI page `1616` and Milwaukee page `6460` rendered the redesigned hubs with the expected public phone routing and addresses.
- Cost pages `6807` and `6808` rendered their pricing, ZIP-routing content, and FAQ schema.
- Page `6065` rendered the chrome canary with header `7336`, menu `7333`, and footer `7344` after normal browser interaction. The homepage continued to render originals `36`, `305`, and `1409`.
- The page-6065 canary fit a 390-pixel viewport without horizontal overflow in the sampled browser check.
- Public page `7073` presented a lead gate promising photo-based, live door visualization, then routed to builder page `7129`.
- Builder page `7129` loaded after the delayed script received normal browser interaction. Its Gallery Steel design image was 134×117 pixels and remained unchanged after sampled color and window selections.
- The current live page `7073` is newer than the repository artifacts. This snapshot contains no current Elementor export, after-state, or rollback artifact for it.

These observations are a point-in-time public check, not a canonical WordPress export or baseline.

## Known preservation gaps

- The preserved Phase 4 `validate-pack.py` check reports one pre-existing source failure: the `clopay-modern-steel-ultra-grain-plank` Rank Math title is 64 characters while the archival validator caps titles at 62. The same failure reproduces directly from source commit `7602048`; it was not introduced by this import and is not altered in this archival snapshot.
- `git diff --check` reports three source-origin formatting findings in byte-identical archival files: trailing whitespace in `wpcode-global-header-before.html` and `2026-07-09-phase4-clopay-catalog.md`, plus trailing whitespace in `2026-07-09-phase4-clopay-catalog-twx-v2-design.md`; the HTML file also has a blank line at end of file. They are retained so the files continue to match the pinned source rather than being silently normalized.
- Phase 5A has pre-overhaul page exports but no final post-overhaul Elementor exports.
- Phase 5B clone CSS predates later live revisions. There are no complete exports for clones `7333`, `7336`, `7344`, or final page `6065` state.
- The existing Phase 5B handoff contains contradictory footer-condition descriptions and one abbreviated cutover check that does not match its own recorded canary state. The detailed Unit 1 runbook is the safer historical reference.
- Global chrome cutover, alternate chrome, library-section reskin, and B3 service-page work were not completed in the preserved repository state.
- The improved free, embedded visual builder was scoped but not implemented in the source boundary.
- This snapshot does not include current WordPress metadata, plugin configuration, menu state, media-library assets, Rank Math state, WPCode activation state, external reviews, or Clopay-hosted images.
- Several historical documents contain local absolute paths or stale filenames. They are preserved as historical records, not executable instructions.

## Authority boundary

This preservation branch must not be used as evidence of WordPress write authority. Before any live mutation, the separate private control-plane repository must establish a fresh verified release, complete baseline and rollback evidence, verified staging, an active owner-signed grant for the exact task, and a successfully attested global lease for the assigned profile.
