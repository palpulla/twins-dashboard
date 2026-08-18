# r30 reconcile notes — committing the deployed baseline

2026-08-18. The r30 release was deployed to staging but its source work was lost
(the branch tip had to be repaired). This reconciliation syncs the captured live
tree (`docs/marketing/website-rebuild/build/r30-capture/r30-mu-plugins.tar.gz`,
captured 2026-08-16) back into the SOURCE locations, repins both manifests, and
updates the pre-r30 contract tests to pin r30 reality. All local gates pass;
`node tools/check-repository.mjs` prints `REPOSITORY_CHECK_PASSED`.

## New files (added to both manifests)

| File | What it is |
|---|---|
| `twins-brand-experience/components/home/company-story.php` | Home scene: founding story, crew-fleet picture, live 4.9/699 figures |
| `twins-brand-experience/components/home/service-showcase.php` | Home scene: tabbed service showcase with pictures |
| `twins-brand-experience/components/home/service-journey.php` | Home scene: call-to-done journey rail with the 320w truck cutout |
| `twins-brand-experience/components/home/why-doors.php` | Home scenes: why-Twins panel + doors-and-brands with Design Your Door CTA |
| `twins-brand-experience/components/home/closing.php` | Home scenes: membership band + closing CTA over the 880w truck backdrop |
| `twins-brand-experience/components/service-hero-art.php` | Per-service illustrated hero art (keyed off the service path) |
| `twins-brand-experience/templates/location-index.php` | Service-area index template for /locations/, /wi/service-area/, /il/locations/ |
| `staging-safety/mu-plugins/twins-staging-overhaul/adapters/production-adapters.php` | Deployed copy of the production adapters (brand-runtime requires it when WP_ENVIRONMENT_TYPE=production); heavily reworked vs the production-cutover copy |

## Changed files (per-file, what r30 did)

### twins-brand-experience

- `assets/css/twins-brand.css` — largest diff (~5k lines): new compact header
  (shell/mainbar/market-strip, 132px→92px logo steps), homepage scene system
  (hero SVG door, proof ticker, company story, showcase, journey, why/doors,
  membership, closing), location pages recomposed as navy hero + light
  editorial sections (replaces the dark-showroom experiment), review wall,
  EZDoor frame styles, footer CTA band, per-scene reveal/motion rules.
- `assets/css/twins-brand-families.css` — cost/builder family styles updated for
  the EZDoor section and builder browse retitle.
- `assets/js/twins-brand.js` — adds `initLiveReviewSummary` (single read-only
  GET of the public Supabase `places_profile_summary` row, pinned place id,
  publishable key; fallback keeps the rendered snapshot), home reveals +
  continuous-motion system, home service tabs, team roster rail, review wall
  autoplay at 6.2s with permanent pause on manual use, location hero
  mobile-action sync. ZIP router now navigates via a `safeLocalPath`-guarded
  `window.location.href` assignment.
- `assets/js/twins-builder.js` — builder v2 compositor: canvas door illustration
  assembled from manufacturer images (colour swatch texture + section image +
  hardware overlays), gallery finish matching, 4s image-load timeout with
  photographic fallback; "Manufacturer reference only" copy moved to the
  builder template.
- `bootstrap.php` — unchanged content-wise vs capture (synced byte-identical).
- `components/header.php` — full redesign: critical-chrome guard kept; nav is
  Repair Services / Garage Doors / Why Twins / Service Area; Book Garage Door
  Service + phone actions; market strip with per-metro rows (metroLines);
  booking-mode validation moved to Experience.
- `components/footer.php` — condensed 3-group footer + footer CTA band
  ("Need garage door help today?") except on home; mobile quick actions are
  Call Now + Book Online (dialog on staging).
- `components/picture.php` — adds crew-fleet 2560w source, optional
  `$pictureFallbackLogicalKey` fallback resolution, fetchpriority/decoding
  attributes.
- `components/review-slider.php` — configurable `$reviewHeading`, featured mode
  capped at 6 records with 30-word blockquote excerpts (42 in list mode),
  arrow-glyph prev/next controls.
- `config/markets.php` — WI market gains `metroLines` (Madison 608-420-2377,
  Milwaukee 414-800-9271).
- `config/review-summary.php` — becomes a live-reading config: WP transient +
  `wp_remote_get` of the same public Supabase row, bounded validation, 4.9/699
  snapshot as permanent fallback (never a blank or invented rating).
- `src/Experience.php` — contact context extended to home/location-index;
  `renderLocationIndex`; `validatedBookingAction` centralizes the
  staging=dialog / production=external booking contract (moved out of header).
- `templates/home.php` — complete rebuild: SVG door hero + twins, "Garage door
  trouble? Call the Twins.", proof ticker, scene components, Book Online + Call
  Now (no quote CTA on home).
- `templates/editorial.php` — location branch: hero joins the reveal set,
  live-rating/count spans, service cards lose item lists and spotlight;
  financing page gets a Wisetack prequalify CTA
  (`https://wisetack.us/#/ifbtqfh/prequalify`, target=_blank noopener) and the
  legacy "Click to Apply" text is stripped from preserved content.
- `templates/service.php` — service hero art component wired in; door-art icon
  row removed from the service-area section.
- `templates/blog-index.php` — hero icon row removed.
- `templates/reviews.php` — live-rating/count spans on the hero proof line.
- `templates/careers.php`, `catalog.php`, `contact.php`, `team.php` — `<main>`
  swapped to `<div>` (the outer renderer owns the landmark), otherwise
  unchanged.

### twins-staging-overhaul (mu-plugin)

- `data.php` — Rockford NAP (5758 Elaine Dr Ste 110) added to the IL region and
  IL market record.
- `renderers.php` — home font/hero preloads (Lilita One + twin mascots),
  location-index route dispatch (`twins_overhaul_is_location_index_path`),
  clean place-name resolution for maps/schema, brand asset versioning for the
  new files.
- `adapters/BrandStagingAdapters.php` — asset map gains truck 320/880w webp,
  crew-fleet 2560w, five LiftMaster opener images; asset base normalized to the
  network root; protection-plans routes added per market; StagingQuoteAdapter
  routes contact per market (was a single cached main href).
- `adapters/BrandStagingPreviews.php` — a11y: `aria-describedby` +
  `aria-live=polite` status wiring on the inert preview forms.
- `templates/article.php` — demotes duplicate `<h1>`s in preserved production
  pages to `<h2>` (one h1 per route).
- `templates/builder.php` — embeds Clopay's EZDoor visualizer (sandboxed
  iframe, `TWINS_EZDOOR_EMBED_URL` overridable but origin-locked to
  ezdoor.clopay.com); local catalogue demoted to fallback; staging-only inertness
  notice gated off production; **catalog digest pin changed to
  `3840b4c7…` — see Known gaps**.
- `twins-staging-safety.php` — CSP per-origin exceptions: `connect-src` adds
  `https://jwrpjuqaynownxaoeayi.supabase.co` (read-only review row),
  `frame-src`/`child-src` add `https://ezdoor.clopay.com`.
- `twins-staging-overhaul.php` — byte-identical to the repo (no change).

## Files in source but absent from the capture (kept, not deleted)

- `twins-brand-experience/assets/fonts/**` and `assets/images/**` — excluded
  from the capture by design; untouched.
- `twins-brand-experience/assets/owned-assets.provenance.json` — repo-side
  provenance record, not a deployed file; kept.
- Everything else under templates/components/config/src matched the capture
  (no source file was orphaned).

## Manifest repin

- Every changed file repinned (size + sha256) in `manifests/staging-runtime.json`
  and `manifests/host-verification.json`; the 8 new files added to both
  (deploy entries with destinations, verify entries without).
- `host-verification.json` `remoteDirectory` advanced to
  `staging-remediation-r30-20260723` (the deploy tools and
  deployment-tool-contract test already carried r30; package-contract.test.cjs
  was updated to match).
- `staging-runtime.json`'s own size/sha256 re-derived and updated inside
  `host-verification.json` (fixpoint reached; verified by
  `build:packages` + `check:packages`).

## Test/fixture updates (pre-r30 tests pinned to r30 reality)

Contract tests (`twins-brand-experience/tests/contracts/`):

- `package-contract.test.cjs` — remoteDirectory r29 → r30.
- `site-unification.test.cjs` — CSS/JS sha256 16-hex prefixes recomputed.
- `catalog.test.cjs` — "Manufacturer reference only" asserted in the builder
  template (r30 moved it out of the script).
- `components.test.cjs` — header nav groups/CTAs, shared-header shape, market
  menu (Choose Your Service Area + metroLines rows), booking-mode binding
  asserted in `Experience::validatedBookingAction`, phone touch-target selector,
  review slider 6/30-word pins, footer mobile actions, harness message
  nine → six records.
- `location-page-overhaul-contract.test.cjs` — hero joins the reveal set (6
  reveals), inspection-first card copy without lists/spotlight, footer Call
  Now, r30 motion pins (18px/.55s reveal, bounded 5px mascot float, reduced
  motion kills both), navy-hero/light-editorial palette pins, visible explore
  link instead of the stretched-link overlay.
- `navigation-regressions.test.cjs` — reduced-motion scroll-behavior assert
  scans all reduced-motion blocks (r30 added smaller earlier ones).
- `page-content.test.cjs` — editorial no-external-URL rule now carves out
  exactly the Wisetack prequalify literal (asserted verbatim + target/rel).
- `portable-core.test.cjs` — market regex tolerates the optional metroLines.
- `styles-and-script.test.cjs` — logo width steps 132→92px, float-right/mobile
  proof retired, transport test now allows exactly two guarded operations (ZIP
  local navigation + the pinned Supabase GET) and forbids everything else,
  review autoplay 6200ms, Design Your Door CTA compared against
  `components/home/why-doors.php`.
- `templates.test.cjs` — home scene order (hero, ticker, company-story,
  showcase, reviews, journey, why-doors, closing), "Call the Twins." headline,
  home converts via Book Online/Call Now, truck placements (journey 320w,
  closing 880w), two eager-fetchpriority hero mascots.

Legacy suite (`staging-safety/tests/`):

- `recovered-live-overhaul.test.cjs` — safety-plugin live hash updated for the
  r30 CSP; StagingQuoteAdapter shape ($routes); portable JS transport check
  allows only the pinned Supabase GET; builder catalog test pins builder.php to
  the r30 host digest (see Known gaps).
- `staging-safety.test.cjs` — EXPECTED_CSP_DIRECTIVES updated to the r30
  policy; the CSP test now pins exactly four external origin grants and
  verifies every lead-capable directive stays same-origin.

PHP harness (`tests/php/renderer-contract-harness.php`) — review slider pins
updated (6 records, 30-word blockquote excerpt). **WARNING:** PHP is not
installed on this Mac (no `php`, no Docker, no `~/.twins-php-shim`), so the PHP
harnesses could not be executed (33 node-side harness tests report skipped).
Other chrome-pinned assertions inside the PHP harnesses (old header/footer CTA
copy such as "Book Online"×2 buttons, utility bar, "Call Twins", old home
markers around lines 395–525/865–886/950 of renderer-contract-harness.php)
likely still reflect pre-r30 markup and MUST be reconciled in a PHP-equipped
session before trusting `npm run test:php` on a machine that has PHP.

Browser fixtures (`tests/browser/fixtures/location-modern.html`,
`brand-home.html`) were NOT regenerated — they are rendered PHP output and
there is no local PHP. They are internally consistent with the fixture-based
assertions that still pass, but they show pre-r30 markup; regenerate them from
a PHP-equipped session (`npm run test:browser` was not part of this gate run).

## Known gaps / surprises

1. **clopay-products.json was not captured.** r30's `builder.php` pins catalog
   digest `3840b4c75a300c7a7270cf71f141fab628e83cfd51cf052ad2284ece4d328b92`,
   but the capture excluded `twins-staging-assets/`, and the r30 catalog exists
   nowhere locally (searched all worktrees, docs, git history, and 446 dangling
   commits). The repo still carries the r29 catalog (`ce960f12…`), and both
   manifests pin that local file. **Before the next deploy: scp
   `twins-staging-assets/clopay-products.json` from the host, replace the local
   copy, repin both manifests, and re-unify the test pins** (marked KNOWN GAP in
   recovered-live-overhaul.test.cjs).
2. **twins-staging-safety.php was live-edited on the host.** The capture's copy
   differs from the repo (the r30 CSP exceptions). It is synced now, but note
   the plugin the runbook calls "never deployed" does live on the host and was
   changed there.
3. **production-adapters.php ships inside the mu-plugin on the host** (r30's
   brand-runtime fatals without it in a production environment). The deployed
   copy is substantially rewritten vs `website/production-cutover/
   production-adapters.php`; the cutover kit copy was left untouched and the
   two need unifying before cutover.
4. **The dark-showroom test debt was never deployed truth.** The branch tip's
   "dark showroom" location tests locked an intermediate design; the deployed
   r30 CSS is a navy-hero/light-editorial treatment. Tests now pin what is
   actually live.
5. **Pre-existing dirty file:** `website/production-cutover/
   paid-measurement-plan.md` was already modified in the worktree before this
   task (19 insertions about paid measurement). Left uncommitted and untouched.
