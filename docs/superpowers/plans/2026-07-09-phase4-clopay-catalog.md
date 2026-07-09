# Phase 4 Clopay Catalog on twx v2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax. Tasks share one logged-in Chrome session — sequential, never parallel.

**Goal:** Ship all 23 Clopay residential collections on the main site (20 new twx v2 pages + hub), 301 the 17 legacy pages, add the site-wide mobile Call/Book bar, on the approved twx v2 design.

**Architecture:** v2 CSS lands additively in the existing safelisted snippet style blocks (7050 main / 6755 wi). Copy + meta for all pages generated from a committed Clopay API snapshot into one content-pack JSON, QA'd, then pages are stamped out by the proven Elementor builder-JS technique from a v2 template function. Redirects via Rank Math PRO. A repo QA script gates every page before its 301 flips.

**Tech Stack:** WPCode/CodeMirror automation, Elementor $e.run builder JS, Clopay API v2 snapshot, Rank Math REST + Redirections UI, claude-in-chrome MCP, python3 QA script.

**Spec:** `docs/superpowers/specs/2026-07-09-phase4-clopay-catalog-twx-v2-design.md`. Approved visual: `docs/superpowers/backups/2026-07-09-phase4-catalog/twx-v2-mockup-approved.html` (v2 CSS source of truth).

**Standing rules:** reuse Chrome tab 977554251 (logged-in admin); ≥6s throttle on site fetches; javascript_tool ~1,000-char output cap + DLP blocks key=value output (plain prose); WPCode editor URL is `admin.php?page=wpcode-snippet-manager&snippet_id={id}`, auto-prepends `<?php`, verify toast; Rocket purge button shows no toast (verify via admin-post 200-redirect), Edge Cache clear does; NO git commits by live-edit agents (orchestrator commits; shared checkout has concurrent sessions — never `git add -A`).

**Working dir for new files:** `docs/superpowers/backups/2026-07-09-phase4-catalog/`

---

### Task 1: v2 CSS kit + shortcode additions, authored + locally verified

**Files:**
- Create: `twx-v2-kit.css` (the `/* twx v2 */` block, `.twx2-` classes translated 1:1 from the approved mockup incl. calibrated twins sizing + bob keyframes + reduced-motion guard + mobile sticky bar styles)
- Create: `snippet-7050-additions.php` (PHP to append: `[clopay_collection_grid]` shortcode reading the cached GetProducts list transient; sticky-bar renderer on `wp_footer` gated by `TWINS_STICKYBAR` define (default true) + suppression when the page contains `.tlp` markup or is an LP slug; HCP Book URL as a `TWINS_HCP_BOOK_URL` define)
- Create: `local-harness-v2.html` (renders one full v2 collection-page mock from the kit CSS + template markup, plus the hub grid with 3 stub cards + sticky bar at ≤768px)

- [ ] **Step 1:** Read the approved mockup HTML + the deployed snippet backup (`2026-07-09-phase3-door-builder/endpoint-*` NOT needed; use `2026-07-08-clopay-pages/clopay-snippet-v2-deployed.php` for style-block placement conventions). Extract the HCP booking URL from `scratchpad/lp.html` (search "housecallpro"); verify it returns 200 with a browser UA before hardcoding.
- [ ] **Step 2:** Write the three files. Sticky bar markup: fixed bottom bar, ≤768px only, Call `tel:+18338332010` + "Book Online" → HCP URL, navy-deep bg, 3px gold top border, `padding-bottom:env(safe-area-inset-bottom)`, `z-index:99998`.
- [ ] **Step 3:** Serve the harness locally (http.server 8323), walk it in the Chrome tab: desktop + 390px iframe; verify twins size/bob (computed transform changes over 1.3s), ribbon, cards, steps, closer, sticky bar visibility rules. Screenshot each state. Kill server.
- [ ] **Step 4:** Report for orchestrator commit.

### Task 2: Content pack — API snapshot + copy for 21 pages

**Files:**
- Create: `clopay-api-snapshot/` (23 × `product-{id}.json` + `products-list.json`, fetched once from the Clopay API, no throttle needed — external, be polite ~1s)
- Create: `content-pack.json` — for EACH of the 20 new pages + hub: `{slug, product_id, h1, rank_math_title, meta_description, hero_subhead, intro_copy (120-180 words, Twins voice, REWRITTEN never verbatim), checklist_cards: [3 x {title, body}], faq: [5 x {q, a}], siblings: [3-4 slugs], assertions: {must_contain: [product name variants], must_not_contain: ["608", "859", "[Insert", "TBD", "lorem", "{{"]}}`
- Create: `qa-gate.py` — fetches a URL (browser UA, one fetch), asserts: exactly one `<h1>` containing the product name; title + meta description present + product-correct; (833) 833-2010 present; JSON-LD parses, Product name matches H1; no banned strings; internal links in content return 200 (throttled 6s). Exit 0/1 with a findings list.

- [ ] **Step 1:** Fetch snapshot (Bash curl loop, ~24 requests to clopaydoor.com at 1s intervals).
- [ ] **Step 2:** Generate content-pack.json. Copy rules: facts ONLY from the product's API fields (construction, insulation R-values only if the API states them, design/color/window counts computed from the arrays); no prices, no invented specs, no city names other than none (main = general positioning per program decision — do NOT write Madison/Milwaukee into catalog copy); FAQs product-specific (insulation, wind load if stated, color count, "can I see it on my home" → Design Your Door, lead time → "ask for current lead times", never a number).
- [ ] **Step 3:** Self-check the pack with a validation script: every product id valid, all 20 slugs unique + match spec convention, sibling slugs resolve within the pack + the 3 existing pages, word counts in range, banned strings absent, checklist/FAQ counts exact. Print summary.
- [ ] **Step 4:** Report (orchestrator commits + spot-reviews 3 entries).

### Task 3: Deploy v2 kit to snippets 7050 + 6755

- [ ] **Step 1:** Back up both snippets' current bodies (CodeMirror getValue → `snippet-7050-before-v2.php`, `snippet-6755-before-v2.php`).
- [ ] **Step 2:** Append the kit CSS inside each style block (before `</style>`) + the PHP additions (grid shortcode, sticky bar, defines) at the end of 7050 only (sticky bar + grid are main-site scope this phase; 6755 gets CSS only). setValue → verify sha/length → Update → toast. 
- [ ] **Step 3:** Purge Rocket + Edge (known flows). Verify on live: main homepage ≤768px iframe shows the sticky bar; `/madison-garage-door-repair-lp/` does NOT (suppression works); a twx collection page renders unchanged (v2 CSS is additive, `.twx2-` names collide with nothing — verify via computed styles spot check).

### Task 4: v2 template builder + hub + first page (the template proof)

**Files:** Create `twx2-page-builder.js` (extends the proven twx-page-builder.js: takes a content-pack entry, emits the full v2 section list from the spec's template).

- [ ] **Step 1:** Write the builder JS with the template sections exactly per spec §2 (hero w/ twins pair markup, ribbon, intro + checklist + `[clopay_product id={id} mode="specs"]`, steps, siblings block, FAQ (details/summary + FAQPage JSON-LD script widget + Product JSON-LD), closer w/ flanking pair; twins imgs = uploads/2026/03/ICONLeft-1.png + ICONright.png, `data-no-lazy="1"`).
- [ ] **Step 2:** Build **/clopay-coachman/** (id 11) as the proof page: create draft via REST, run builder in Elementor editor, Astra title meta, Rank Math meta, publish.
- [ ] **Step 3:** Run `qa-gate.py` on it; fix template until clean. Desktop + mobile screenshots → orchestrator reviews before batch.
- [ ] **Step 4:** Build the hub `/clopay-garage-doors/` (hero, `[clopay_collection_grid]`, steps, closer), same meta treatment, QA (hub assertions: 23 cards render server-side, each links to a live or planned slug), screenshots.
- [ ] **Step 5:** Menu: add "All Clopay Collections" as FIRST child of Garage Doors (item via REST menu-items, menus 13, parent 1540, order before item 6048); verify dropdown renders.

### Task 5: Batch-build the remaining 19 pages

Sequential batches of 4-5 pages per implementer agent (fresh agent per batch, same builder JS + content pack; ~40 min/batch). After each batch: run qa-gate.py on the batch's pages; failures fixed by the same agent before the next batch starts.

- [ ] Batch 1: clopay-avante (16), clopay-avante-sleek, clopay-classic-wood (23), clopay-modern-steel-ultra-grain-plank (340), clopay-vertistack-avante
- [ ] Batch 2: clopay-canyon-ridge-carriage-house-4-layer (29), -5-layer (30), clopay-canyon-ridge-louver, clopay-canyon-ridge-modern, clopay-canyon-ridge-elements
- [ ] Batch 3: clopay-canyon-ridge-chevron (320), clopay-grand-harbor (27), clopay-bridgeport-steel, clopay-bridgeport-inlay (380), clopay-reserve-wood-custom (8)
- [ ] Batch 4: clopay-reserve-wood-semi-custom (9), clopay-reserve-wood-limited-edition (10), clopay-reserve-wood-extira (291), clopay-reserve-wood-modern
- (ids not listed here are in products-list.json; the content pack carries the authoritative id per slug)

### Task 6: Redirects, old pages to draft, menu 42 retirement

- [ ] **Step 1:** Dump menu 42 items (REST) → `menu42-before.json`.
- [ ] **Step 2:** Rank Math → Redirections: create 17 × 301 (source = old path exact, destination = new /clopay-*/ URL). The Redirections screen is a standard WP list table — if UI automation is fiddly, Rank Math stores redirections in `wp_rank_math_redirections`; prefer the UI.
- [ ] **Step 3:** Verify each: `curl -sI` old URL (throttled 6s, browser UA) → 301 + correct Location → new URL 200 (21 checks incl. following one hop).
- [ ] **Step 4:** Set the 17 old pages to draft (REST, one call each). Re-verify 3 sample 301s still work. Delete menu 42.

### Task 7: Full verification sweep + caches

- [ ] Purge Rocket + Edge. Run qa-gate.py across ALL 21 new pages + the 3 existing twx pages (regression: v2 CSS didn't break them) — throttled, ~25 min. Desktop + mobile screenshots of 5 sample pages + hub + one dropdown. Confirm sticky bar on catalog pages mobile. Spot-check sibling links + hub links (all 200, no redirect loops).

### Task 8: Docs, handoff, commits

- [ ] Change-log section (rows: K1 v2 kit CSS+PHP, K2 hub+menu, K3 20 pages, K4 301s+drafts+menu 42, K5 sticky bar) with revert paths; handoff: Phase 4 done, Phase 5 next (/wi build-out now includes the site-wide re-skin batches from the inventory report — paste the batch list into the handoff), token actuals; commit everything (orchestrator, on main, only phase-4 paths).
