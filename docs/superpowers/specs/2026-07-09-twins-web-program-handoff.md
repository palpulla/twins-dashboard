# HANDOFF PROMPT — Twins web program: fix bugs, full Clopay catalog, in-site door builder, menus, /il subsite

Paste this whole document into a new Claude Code chat in `~/twins-dashboard`.

---

You are continuing a large website program for Daniel (non-dev owner of Twins Garage Doors, Madison WI). Start by invoking `/superpowers:brainstorming` with this scope, decompose it into phased specs/plans, confirm the open decisions below with Daniel, then execute phase by phase. Everything must be reversible. Show plans before building.

## Environment (verified working patterns — reuse, don't rediscover)

- **Site:** WordPress multisite on SiteGround: `twinsgaragedoors.com` (main) + `/wi` + `/ky` subsites. Elementor + Astra, WPCode, WP Rocket (main + /ky only, **/wi has NO WP Rocket**), Rank Math PRO. Chrome (claude-in-chrome MCP) is logged in as admin "Tal Joseph"; wp-admin per site at `{site}/wp-admin`.
- **GHL:** Dunzo whitelabel at app.gohighlevel.com, login via "Sign in with Google" SSO; Twins location `iRUlbIBg7PzSfLrPiR2j`. Its form builder resists automation (drag-and-drop) — avoid; the WP lead-endpoint pattern below is the house standard.
- **Brand tokens (extracted from live CSS):** navy `#022751`, deep navy `#010D38`, yellow `#FBBD04` (NOT #F5C518), soft bg `#F2F5F7`, font Montserrat. All live in the shared `.twx-` stylesheet.
- **Build technique that works:** pages are built natively per subsite in the Elementor editor via JS: inject a builder function, `$e.run('document/elements/empty',{force:true})`, create sections (`$e.run('document/elements/create',...)`), `$e.run('document/save/update')`. Content = class-based HTML widgets on `.twx-` classes → kit-independent (cross-site clone of kit-styled content BREAKS; class-based does not). Builder + template record: `docs/superpowers/backups/2026-07-08-clopay-pages/twx-page-builder.js`.
- **WPCode gotchas:** editor auto-prepends `<?php` (never include your own opening tag); Save/Update clicks silently miss — verify the "Snippet updated" toast or that navigation away raises no unsaved-changes dialog; the code-type modal sometimes reopens over the editor (dismiss by clicking the PHP card, content survives).
- **Caching gotchas:** WP Rocket page cache does NOT purge on snippet edits — purge via the button on its settings dashboard (`options-general.php?page=wprocket`), not via fetching the admin-bar link. WP Rocket Remove-Unused-CSS (main site only) strips inline styles; the Clopay snippet ships a `rocket_rucss_inline_content_exclusions` filter safelisting `twx-ui` / `--tw-navy` — any new inline `<style>` must reference `var(--tw-navy)` (or extend that filter) to survive. Query strings do NOT bypass the cache; logged-in browsing does.
- **Astra:** theme page-title (duplicate H1) is disabled per page via REST meta `{"meta":{"site-post-title":"disabled"}}` — it IS registered. New pages need this or they render two H1s.
- **Rank Math:** per-page title/meta via `POST {site}/wp-json/rankmath/v1/updateMeta` (form-encoded `objectID`, `objectType=post`, `meta[rank_math_title]`, `meta[rank_math_description]`, header `X-WP-Nonce` from `wpApiSettings.nonce`).
- **Clopay Product API v2:** public, no key, CORS `*`. `GET https://www.clopaydoor.com/api/v2/GetProductsList/GetProducts?productType=Residential` (23 products) and `GetProductDetails/GetProductData?productId={id}`. Full docs: `~/Downloads/INST-API_EN (2).pdf`. Known IDs: Modern Steel 170, Gallery Steel 12, Classic Steel 13, Coachman 11, Avante 16, Canyon Ridge CH 4-layer 29 / 5-layer 30, Grand Harbor 27, Reserve Wood Custom 8 / Semi-Custom 9 / Limited 10, Classic Wood 23 (full table in the PDF, pages 27-29). Dealer propId `100841` (locks Clopay's Where-To-Buy to Twins). Gallery iframe endpoint per product: `ImageGallery` field.

## Current live state (shipped 2026-07-08/09, all committed)

- **Snippets:** "Twins x Clopay Product API (fetch+cache+shortcode)" = main **7050**, /ky **6369**, /wi **6755** (Active, Run Everywhere; contains `.twx-` design tokens + `[clopay_product id mode]` shortcode + RUCSS filter + propId define). "Design Your Door lead endpoint (door-builder)" = main **7072** (`POST /wp-json/twins/v1/door-builder` → email contact@twinsgaragedoors.com, region-tagged, honeypot). "Madison Landing Page Lead Endpoint" = main **7028** (pre-existing pattern, don't touch). Deployed copies: `docs/superpowers/backups/2026-07-08-clopay-pages/*.php`.
- **Collection pages on the twx 7-section template** (hero / yellow design-band / Clopay live section / why-cards / condensed copy / FAQ / navy band): main Modern Steel **6090**, Gallery **6065**, Classic **6034**; /ky Classic **6198**, Modern Steel **6378**, Gallery **6379**. Pre-rebuild HTML backups in `docs/superpowers/backups/2026-07-08-clopay-pages/`; Elementor revisions retained.
- **Funnel pages** `/design-your-door/`: main **7073**, /wi **6756**, /ky **6386** (form name/phone/email/zip + honeypot → endpoint → redirect ezdoor.clopay.com). E2E verified. Region phones: main (833) 833-2010, /wi (608) 888-8785, /ky (859) 440-2227.
- **Specs/plans:** `docs/superpowers/specs/2026-07-08-twins-web-redesign-clopay-ezdoor-design.md` (current architecture), `2026-07-08-clopay-door-builder-landing-design.md`, `2026-07-08-clopay-product-api-pages-design.md`. Change-log entries: `docs/marketing/change-log.md` (top).
- **GHL leftovers to tidy sometime:** unused contact field `lead_region`; possible stray unsaved form "Door Builder - Main".

## BUG (fix first, small): Clopay gallery renders blank

Diagnosed: WP Rocket iframe lazy-load rewrites the gallery iframe to `src="about:blank" data-lazy-src=...` and never restores it (conflicts with the iframe's own `loading="lazy"`). Fix in snippet 7050/6369/6755: remove `loading="lazy"` from the iframe and add `data-no-lazy="1"` (and if still rewritten, add WP Rocket iframe-lazyload exclusion for `clopaydoor.com` or disable iframe lazyload); purge caches; verify anon HTML shows direct `src="https://www.clopaydoor.com/image-gallery/..."`. Hero background images are fine.

## NEW SCOPE (Daniel's words, structured; priorities: main + /wi first, /il new, /ky LAST)

1. **Fix the blank gallery pic** (above).
2. **Full Clopay catalog on our site:** ALL Clopay residential collections as collection pages (23 products via the API list endpoint), not just the 3 current ones. Clarify with Daniel: residential only, or commercial too? One page per product on the twx template, unique intro copy per door (no duplicate-content sludge), Rank Math meta each.
3. **/wi gets collection pages too** ("where are WI's pages?") — decide with Daniel how /wi pages differ from main (both are Wisconsin; risk of self-cannibalization — surface this, maybe /wi pages target Milwaukee since that location is opening).
4. **In-site door builder:** clients design doors ON our site instead of leaving to Clopay. Brainstorm options honestly: (a) iframe EZDoor into /design-your-door (works today — ezdoor.clopay.com sends malformed `x-frame-options: *`, no frame-ancestors — but could break anytime and loses the lead-capture-first flow unless gated), (b) build a Twins-owned visualizer from Clopay API data (colors/ProductDesigns/TopSections/gallery imagery) — real product, phased; (c) hybrid: gate form → embedded EZDoor iframe on our page (capture kept + stays on-site). Recommend (c) short-term + scope (b) properly.
5. **Menus:** add Design Your Door pages to menus; REMOVE Hörmann from menus (do NOT delete the page); restructure LOCATIONS as State → Cities with **Madison and Milwaukee as top cities** (Milwaukee location opening). Headers are Elementor Theme Builder — investigate which menu feeds the nav (Appearance → Menus: /ky has "Menu" id 13 + "Quicklinks" id 14; menus have no theme locations, the Elementor nav widget references them). Every menu change screenshot-verified desktop + mobile.
6. **More brand color + icons on the new twx pages:** Daniel wants more yellow/navy and the icons used on other pages — pull real icons from each site's Media Library (inspect existing pages for what's in use). No invented assets.
7. **NEW /il subsite:** create `twinsgaragedoors.com/il` in the multisite. Rockford + surrounding cities/towns location pages. Phone **+1 815-800-2025**. Address TBD (leave address out until Daniel provides — no placeholders). **DO NOT PUBLISH** — build everything as drafts/noindex until Daniel says go. Ask Daniel for the exact city list (suggest: Rockford, Loves Park, Machesney Park, Belvidere, Roscoe, Rockton, Freeport — confirm, don't assume).
8. **Steal the best of goodgollygarage.com** (study it fresh: layout, trust elements, CTAs, service-area UX, anything that converts) and adapt to Twins brand tokens. Document what was taken in the spec.
9. **SEO + AEO:** aim at top organic + AI-engine visibility — proper heading hierarchy, FAQ content, LocalBusiness/Service/FAQPage schema (Rank Math or JSON-LD via snippet), internal linking hub (state → city → service), clean titles/meta, image alts. There's an existing AEO baseline: memory `project_ai_search_aeo`.
10. **Reversibility:** every change logged in `docs/marketing/change-log.md` with revert path; page snapshots before rebuilds; snippets toggleable; /il unpublished.

## Open decisions to confirm with Daniel early (AskUserQuestion)

- All 23 residential collections or a curated subset? Commercial too?
- /wi collection pages: Milwaukee-targeted or generic-WI? (cannibalization risk vs main)
- Door builder: approve hybrid (gated EZDoor embed now, owned visualizer as phase 2)?
- Milwaukee: phone number + address for menus/location pages?
- /il: confirm city list; a name for the business entity there if different.
- Menu tree: exact desired structure (propose a draft first: LOCATIONS → Wisconsin (Madison, Milwaukee, …) / Kentucky (Lexington) / Illinois (hidden until live)).

## Suggested phases (each = own spec → plan → build → verify → change-log)

1. Bug fix + polish pass on existing 9 twx pages (gallery iframe, more color/icons) — small.
2. Menu restructure (state→city, Design Your Door in, Hörmann out) on main + /wi.
3. Full Clopay catalog on main (template is parameterized; content per door from API + unique intros).
4. /wi pages strategy + build.
5. In-site builder (hybrid v1).
6. /il subsite skeleton + Rockford-area location pages (unpublished).
7. goodgollygarage-inspired conversion upgrades woven through 1-6.
8. /ky parity LAST.

## Token budget estimate (rough, based on this session's actuals)

The 2026-07-08/09 session (9 pages + funnel + snippets, heavy browser automation) consumed roughly 400-500k tokens. Estimates: Phase 1 ~80-150k; Phase 2 ~100-180k (menu UIs are fiddly); Phase 3 ~300-500k (≈20 new pages, mostly repeated builder runs + per-page copy); Phase 4 ~150-250k; Phase 5 ~250-400k (new interactive component); Phase 6 ~200-350k; Phase 7 folded into others (+~100k for studying the reference site); Phase 8 ~100-150k. **Program total ≈ 1.3-2.1M tokens across multiple sessions.** Recommend one phase per session, updating this handoff (or memory) at each session end.

## House rules (from memory — binding)

Show plan/diff before implementing; never fabricate operational data (prices, addresses, people); no placeholder identity/contact — real Twins numbers only; keep pages simple and mobile-fit; no em-dashes in customer-facing copy; full dollar amounts; never reference Lovable; all changes reversible; commit docs to the repo as you go.
