# Phase 4 — Full Clopay catalog on twx v2 (design)

Date: 2026-07-09. Approved by Daniel in-session (Option A mockup, 3 rounds of visual calibration; artifact `claude.ai/code/artifact/1643e039-aba0-481a-bc67-c4506ed4d75f`, local copy `scratchpad/twx-v2-mockup.html` — committed to backups in the plan). Parent program: `2026-07-09-twins-web-program-handoff.md`.

## Research inputs (2026-07-09, this session)

1. **Madison LP audit** (/madison-garage-door-repair-lp/): loved features = twins mascots, stamp badge, sticker treatment, trust ribbon, checklist grid, 3-field callback card w/ UTM capture → Supabase, tech-named review cards, mobile sticky Call/Book bar. LP itself is a third palette/font system (Lilita One/Nunito) — features port, fonts/palettes do NOT.
2. **goodgollygarage.com study**: borrow = numbered "What to expect" 3-step, Book+Call pair closing sections, brands-strip-as-endorsement, bracketed eyebrows, city-tabbed reviews, per-city cost-FAQ pages (AEO), license+NAP footer, zip finder. Avoid = placeholder content in production, wrong-city schema, FAQ schema without visible FAQs, missing meta descriptions. They have NO door catalog — open field.
3. **Clopay pages audit**: 17 old pages (6403-6427) are orphaned manufacturer boilerplate w/ broken clopaydoor.com-path links; only 2 truly collide with twx pages; Ultra-Grain Plank (API id 340) is a DISTINCT product, not a Modern Steel dupe; 5 products have no page: Canyon Ridge Chevron (320), Bridgeport Inlay (380), Reserve Wood Extira (291), Reserve Wood Limited Edition (10), Reserve Wood Semi-Custom (9). Full residential catalog = 23 products.
4. **Site inventory**: Elementor kit colors already navy/yellow; twx stylesheet already loads site-wide; ~12 shared Theme Builder docs drive both sites; legacy = one consistent Elementor family + the thin Clopay-page generation. Rollout batches enumerated in the inventory report (recorded in the plan appendix for phases 5+).

## Daniel's decisions (locked)

- **Option A** design: twx v2 = twx base (Montserrat, navy #022751, yellow #FBBD04, ONE palette — no comic fonts) + twins pair + sticker accents + upgraded trust ribbon + goodgolly patterns.
- **Twins sizing (calibrated, 3 rounds):** front min(34vw,420px), back min(26vw,325px), staggered, bottom-RIGHT of hero; ~250px right-aligned on mobile; NEVER hidden. Subtle bob: translateY ~9px, 5.5s/6.5s ease-in-out infinite, staggered delays, disabled under prefers-reduced-motion. Smaller flanking pair on navy CTA bands (~104px, bob).
- Full site chrome on all catalog/site pages (complete menu + full footer). Thin footers stay LP-only.
- Phones: main (833) 833-2010 · /wi (608) 888-8785 · Milwaukee-specific pages (414) 800-9271 · /il (815) 800-2025. Phase 4 pages are all MAIN site → (833) 833-2010 everywhere.
- 301 the 17 old Clopay slugs to the new /clopay-*/ pages. Retire WP menu 42 ("Clopay Products Menu").

## What ships in Phase 4 (main site only; /wi catalog is Phase 5, /ky Phase 8)

### 1. twx v2 component kit (CSS, in the existing snippets' style block)

New `/* twx v2 */` section appended inside the safelisted `twx-ui` style block of snippets **7050 (main)** and **6755 (/wi)** (deploy both; /wi consumes it in Phase 5). Classes (all `.twx2-` prefix to avoid collisions with existing .twx usage): `twx2-pair` (+`--band` flanking variant, bob keyframes), `twx2-stamp`, `twx2-eyebrow` (bracketed), `twx2-ribbon` + `twx2-rib-item` (icon chip + claim + proof line), `twx2-card` (sticker: 3px navy border, 4px 4px 0 offset shadow, yellow or navy shadow variants), `twx2-btn` sticker variants, `twx2-steps`/`twx2-step` (numbered 01-03), `twx2-review` (big gold quote mark, tech-named), `twx2-brands` strip, `twx2-closer` (Book+Call pair band). Mockup file is the visual source of truth; translate its CSS 1:1 with the class rename.

### 2. Twenty new collection pages on main, twx v2 template

Template sections: full chrome (existing header/footer untouched) → v2 hero (eyebrow "[ OFFICIAL CLOPAY DEALER ]", H1 "Clopay {Product} Garage Doors", product-specific subhead, stamp "FREE ON-SITE QUOTE", Call + Design This Door CTAs, star trust line, twins pair) → trust ribbon → intro/why section: **unique copy per door** (from API Overview/Construction/ShortDescription rewritten in Twins voice — never verbatim manufacturer text, no invented specs/prices) + 3 checklist cards + live Clopay section (`[clopay_product id="{id}" mode="specs"]` — colors/designs/gallery, existing shortcode) → What-to-expect 3-step → sibling-collections block (3-4 related doors, cross-links) → 5-item FAQ (visible, product-specific) → v2 closer band (twins flanking, Book Online + Call). 

- **Pages (20):** the 14 old-page products rebuilt fresh at `/clopay-{slug}/`, Ultra-Grain Plank (340) as its own page, and the 5 never-built products. Slug convention: `/clopay-avante/`, `/clopay-coachman/`, `/clopay-canyon-ridge-carriage-house-4-layer/`, etc. (mirroring the 3 existing twx pages' `/clopay-*` style).
- Per page: Astra page-title disabled meta; Rank Math title "BEST Clopay {Product} Garage Doors Near You" pattern + unique description; Product + FAQPage JSON-LD **matching visible content only**; image alts.
- "Book Online" → the HCP online-booking link already used on the LP mobile bar (read the exact URL from the LP audit copy in scratchpad/lp.html; verify it loads before shipping).

### 3. Collections hub: `/clopay-garage-doors/` on main

v2 page: hero (no product) + grid of ALL 23 collections (ShowcaseImage card, one-line hook, link) fed server-side by a new `[clopay_collection_grid]` shortcode added to snippet 7050 (uses the cached GetProducts list; graceful empty-state if API down) + what-to-expect + closer. Menu: Garage Doors dropdown gains first child "All Clopay Collections" → hub (existing 3 collection items stay; the other 20 pages are reachable via hub + sibling blocks, NOT all in the menu).

### 4. Redirects + menu retirement

- 17 × 301: old slug → its `/clopay-*/` counterpart, via Rank Math PRO Redirections. `/classic-steel/`→`/clopay-classic-collection/`, `/gallery-steel/`→`/clopay-gallery-steel/`, `/modern-steel-ultra-grain-plank/`→`/clopay-modern-steel-ultra-grain-plank/`, the rest 1:1 to their rebuilt pages.
- Old pages set to draft AFTER redirects verified (301 wins regardless; drafting removes them from sitemap). Delete WP menu 42.

### 5. Mobile sticky Call/Book bar — site-wide on main

Rendered by snippet 7050 on all front-end pages ≤768px: Call (833) 833-2010 + Book Online (HCP link), navy-deep bar, gold top border, safe-area padding, z-index below admin bar. Suppressed on pages that already have their own bar (the LP has one — suppress by URL match or a body-class check for `.tlp`). Own change-log row; toggleable via a `TWINS_STICKYBAR` define in the snippet.

### 6. QA gates (from goodgolly's failures)

Automated per-page assertions after build (script in repo, run against served HTML): exactly one H1 containing the right product name; title + meta description present and product-correct; (833) 833-2010 present, no 608/859 numbers; JSON-LD parses and its product name matches the H1; zero "[Insert", "TBD", "lorem", "{{" strings; all internal links 200. Every page must pass before its 301 goes live.

## Error handling

- Clopay API down during builds → the shortcode's existing last-good-cache fallback covers the live section; hub grid shows fallback message. Copy generation works from a committed JSON snapshot of API data (fetched once, reviewed), not live calls at build time.
- Redirect mistakes → Rank Math redirections are individually deletable; old pages stay in DB as drafts.

## Verification

Local render test of the v2 template (harness, like Phase 3) before any live build; first live page reviewed as the template proof, then batch builds; QA gate script on all 21 pages (20 + hub); desktop + mobile screenshots (sample of 5 pages + hub); 301 checks (curl -I each old slug → 301 → new URL 200); caches purged; change-log rows per change class.

## Reversibility

Snippet edits: before/after copies committed (kit CSS is additive; revert = remove `/* twx v2 */` block). Pages: unpublish. Redirects: delete in Rank Math. Menu 42: recorded in Phase 2 backups (main-menu13-before.json era) + fresh dump before deletion. Sticky bar: `TWINS_STICKYBAR` define false. Old pages: drafts, restorable to publish.

## Deferred (recorded, not in Phase 4)

Site-wide legacy re-skins (batches from the inventory: shared chrome first, then service pages, trust pages, /wi templates, homes) → phases 5+; /wi collection pages + Milwaukee overhaul → Phase 5; per-city cost-FAQ AEO pages, city-tabbed reviews page, zip finder → phase 5/7 conversion pass; LP bug fixes (placeholder preload, animation kill-switch, desktop Book link) → small follow-up, needs Daniel's OK to touch a live paid LP; WI license number in footers → **needs the license number from Daniel, do not fabricate**; footer NAP upgrades ride the shared-chrome batch.
