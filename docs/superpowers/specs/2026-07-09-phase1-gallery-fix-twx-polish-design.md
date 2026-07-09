# Phase 1 — Clopay gallery iframe fix + twx polish pass (design)

Date: 2026-07-09. Approved by Daniel in-session. Parent program: `2026-07-09-twins-web-program-handoff.md`.

## Program context (decisions confirmed with Daniel, 2026-07-09)

Phase order (one phase per session): **1)** this phase, **2)** menu restructure main + /wi, **3)** owned door-builder visualizer (Twins-built from Clopay API; no EZDoor-iframe stopgap), **4)** full Clopay catalog on main (all 23 residential collections, general/all-locations positioning), **5)** /wi build-out (Madison + Milwaukee + surrounding; complete Milwaukee page overhaul), **6)** /il subsite (unpublished; Twins brand; +1 815-800-2025; no address until provided; 12 cities: Rockford, Loves Park, Machesney Park, Belvidere, Roscoe, Rockton, Cherry Valley, Poplar Grove, South Beloit, Winnebago, Byron, Caledonia), **7)** /ky parity last. Milwaukee contact: (414) 800-9271, 11220 W Burleigh St Ste 100, Wauwatosa, WI 53222. Menu draft approved: LOCATIONS → State → Cities (Madison, Milwaukee top), Design Your Door top-level, Hörmann removed from menus only. SEO/AEO woven through every phase. goodgollygarage.com study happens at the start of phase 4 and feeds phases 4-7.

## Scope of this phase

Two deliverables on the 9 live twx pages, no new pages:

- Collection pages: main Modern Steel 6090, Gallery 6065, Classic 6034; /ky Classic 6198, Modern Steel 6378, Gallery 6379.
- Funnel pages `/design-your-door/`: main 7073, /wi 6756, /ky 6386.

### 1. Fix: Clopay gallery renders blank

Diagnosis (from handoff, already root-caused): WP Rocket's iframe lazy-load rewrites the gallery iframe to `src="about:blank" data-lazy-src=…` and never restores it, because it conflicts with the iframe's own `loading="lazy"`.

Fix, applied to the shared Clopay snippet on each site (main **7050**, /ky **6369**, /wi **6755**):

1. Remove `loading="lazy"` from the gallery iframe markup.
2. Add `data-no-lazy="1"` to the iframe (WP Rocket's documented opt-out).
3. If Rocket still rewrites it: add an iframe-lazyload exclusion for `clopaydoor.com` in WP Rocket settings, or disable iframe lazy-load (main + /ky only — /wi runs no WP Rocket, so first verify whether /wi is affected at all; if /wi renders fine untouched, its snippet still gets the same markup change for consistency).
4. Purge WP Rocket page cache via the button on `options-general.php?page=wprocket` (NOT the admin-bar link; snippet edits do not auto-purge).

Success criterion: logged-out (anonymous) HTML on a collection page shows the iframe with a direct `src="https://www.clopaydoor.com/image-gallery/…"` and the gallery visibly renders. Checked on one page per site that has the gallery section.

### 2. Polish: more brand color + real icons

Daniel wants the new twx pages to carry more yellow/navy and the icons used elsewhere on the sites. Approach:

1. **Icon audit first, no invented assets:** inspect established pages on each site (service pages, home) to identify which Media Library icons are actually in use, and collect their URLs per site (media is per-subsite). Only icons already in the library get used.
2. Apply accents primarily through the shared `.twx-` stylesheet inside the Clopay snippet (one edit covers all pages per site): e.g. yellow rules/underlines on section headings, navy/yellow treatments on why-cards and FAQ, icon slots in the why-Twins cards and how-it-works steps.
3. Where per-page HTML must change (adding `<img>` icons into widgets), edit via the proven Elementor builder-JS technique (`twx-page-builder.js` record) or targeted widget edits; never cross-site clones.
4. RUCSS constraint (main site): any new inline `<style>` must reference `var(--tw-navy)` or extend the existing `rocket_rucss_inline_content_exclusions` filter, or Rocket strips it.
5. Keep it simple and not busy (house rule): accents and icons, not a redesign; mobile must fit the screen.

## Verification

- Anonymous-visitor HTML check for the gallery src on all three sites.
- Desktop + mobile screenshots of each touched page after purge, compared against pre-change snapshots.
- Confirm region phone numbers unchanged (main 833-833-2010, /wi 608-888-8785, /ky 859-440-2227) and single H1 per page.

## Reversibility

- Snippet edits: current deployed PHP is committed at `docs/superpowers/backups/2026-07-08-clopay-pages/clopay-snippet-v2-deployed.php`; commit the updated deployed copy after the change. Revert = paste back the prior file (or deactivate the snippet).
- Page edits: Elementor revisions retained; fresh pre-change HTML snapshots committed to `docs/superpowers/backups/` before any page is touched.
- Every change gets a `docs/marketing/change-log.md` entry with its revert path.

## Out of scope (later phases)

Menus, new pages, /wi collection pages, Milwaukee overhaul, visualizer, /il, /ky parity, goodgollygarage study.
