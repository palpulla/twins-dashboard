# HANDOFF — Phase 5 Workstream B: chrome re-skin execution (paste into a NEW Claude Code chat in ~/twins-dashboard)

## Your job this session

Execute the **site chrome re-skin** (main site header/menu/footer/widget → twx v2), starting with **Unit 1 (header 36 + POP menu 305)**. This is the HIGHEST-RISK work in the whole web program: the header renders on 100% of pages and sits next to live Google Ads / Meta pixel / GA4 / GHL chat. Do NOT edit the live chrome in place — Codex already reviewed and NO-GO'd that. Use the committed clone-and-swap runbook.

## USE CODEX gpt-5.6-sol AT ULTRA EFFORT AS THE BRAIN (required by Daniel)

Codex is the authoring + verification engine for this workstream. Claude is the hands (only Claude can drive the logged-in Chrome/Elementor editor via the claude-in-chrome MCP; Codex runs in a terminal and cannot touch the live site).

- **The in-app Codex MCP tool may be stale** (token from a prior login). If `mcp__codex__codex` errors with "access token could not be refreshed," DO NOT fight it — a fresh session should have reconnected it; if it still errors, use the CLI below. Daniel can also fully quit + reopen the Claude app to refresh the MCP connection.
- **Run Codex via the CLI** (this is what worked; `gpt-5.6-sol` + `model_reasoning_effort=ultra` = "sol 5.6 ultra"):

```bash
cat /path/to/prompt.txt | codex exec \
  -m gpt-5.6-sol -c model_reasoning_effort="ultra" \
  -s read-only --skip-git-repo-check \
  -C /Users/daniel/twins-dashboard \
  -o /path/to/codex-out.md -
```

Use Codex `gpt-5.6-sol` ultra to: (a) re-verify the Unit 1 runbook against fresh live state before executing, (b) author each subsequent unit's package (footer 1409+466, alt header/footer 2163/2179, library sections), and (c) verify each canary + cutover result. Claude executes the browser steps Codex specifies and feeds live data back to Codex.

## Read these first (all committed)

- **THE RUNBOOK to execute:** `docs/superpowers/specs/2026-07-10-phase5-B-unit1-header-menu-runbook.md` (Codex gpt-5.6-sol ultra authored; 655 lines: preserve-list, re-skin spec, clone-and-swap steps 3.1→3.9, regression matrix, residual risks).
- **Why in-place was rejected:** `docs/superpowers/specs/2026-07-10-phase5-B-codex-review.md`.
- **Draft scope + open questions:** `docs/superpowers/specs/2026-07-10-phase5-B-chrome-reskin-DRAFT.md`.
- **Full backups (rollback source):** `docs/superpowers/backups/2026-07-10-phase5-B/tb-{36,305,466,1409,2163,2179}.json` (each = full `_elementor_data` + conditions + type).
- **Program context + all live-editing gotchas:** `docs/superpowers/specs/2026-07-09-twins-web-program-handoff.md` (READ the Environment section: WPCode toast-verify, sha256 before/after, ≥6s request throttle, Elementor saves wipe Astra meta, JSON-LD widget re-add, foreground fetch loops, white-screen drill, tab-freeze → open ONE fresh tab).
- **twx v2 design language:** `docs/superpowers/backups/2026-07-09-phase4-catalog/twx-v2-mockup-approved.html`.

## Site-qualified manifest (MAIN site — twinsgaragedoors.com, NEVER /wi or /ky)

| Doc | Type | Condition | Notes |
|---|---|---|---|
| 36 | header | `include/general` (every page) | references menu 305 via widget `9a8b90d.ct_saved_rows="305"` |
| 305 | section | `[]` | the POP (mobile) menu, rendered inside header 36 |
| 1409 | footer | `include/general` (every page) | embeds widget 466 |
| 466 | widget | `[]` | Dual Button |
| 2179 | footer | page 2123 (`/contact-us/`) | alt footer |
| 2163 | header | `[]` | alt header (Header Contact Us) |
| 1498-1516 | section | (library) | shared service-page sections |

## Execution order (Codex's prescribed safe path)

1. **Unit 1 = header 36 + menu 305.** Follow the runbook. In short: re-verify drift SHA-256 hashes still match (another editor session may have changed the docs — abort if drift); clone 305 → clone 36 → wire clone-to-clone → re-skin the CLONES with additive `.twx2-*` classes ONLY (never rename an element ID — that's what preserves tracking + the `15c4a1b` nav-fit + `#menuhopin` sticky); regenerate Elementor CSS; **canary on `/clopay-gallery-steel/` (page 6065) only**; run the full regression matrix at 9 widths ANONYMOUSLY (BlogVault whitelist is DONE now) + logged-in; rehearse rollback; then cut over.
2. **GLOBAL CUTOVER GATE:** the live cutover (runbook §3.8) requires **Daniel present + a low-traffic window + rollback console open**. Do the clone + re-skin + canary + rollback-rehearsal freely (no global impact, reversible); PAUSE for Daniel before the global condition flip.
3. After Unit 1 ships: repeat the same clone-and-swap for footer **1409 + 466**, then alt header/footer **2163 + 2179**, then library sections **1498-1516**. Have Codex author each unit's package first.
4. **B3 main service pages** (~10) = a SEPARATE plan/session (Codex flagged it out of scope for the chrome units).

## Hard rules (binding)

- Additive CSS classes only; NEVER rename an existing Elementor element ID or its `css_classes`/`_css_classes` — renaming silently breaks nav-fit, sticky, and any external Meta/Ads click rules keyed off IDs/classes.
- Original docs 36/305 stay UNTOUCHED until cutover (they are the seconds-fast rollback via a condition flip).
- Preserve exact phone/quote/HCP links, the dynamic WP menu (Menu ID 13 — do not hard-code its HTML), all modal `data-*` attributes, and both malformed-but-load-bearing trailing CSS blocks (`10px; }`) verbatim.
- Verify tracking parity before/after: one `gtag('config','G-XW0RGPTGSN')` + one `G-JR7LW39CND`, one `fbq('init','554750209097175')`+PageView, exactly one visible GHL chat bubble, no duplicate loaders.
- Every cache change → full invalidation routine (Elementor Clear Files & Data → clone CSS 200 → WP Rocket Clear+Preload → a8c Edge → logged-in reload → anonymous cold+warm verify).
- No em-dashes in customer copy; full dollar amounts; real Twins data only; commit docs as you go (own paths only — shared checkout, verify `git branch --show-current`).

## Current state (as of 2026-07-10, end of prior session)

- **DONE + live + verified:** Workstream A (/wi Madison hub 1616 + Milwaukee hub 6460, twx v2, carousels, per-metro phones, schema NAP fixed, number pool retired). Workstream C (/wi cost pages 6807 Madison + 6808 Milwaukee + zip finder, AEO FAQPage schema). The (608) 420-2377 tracking-number forward to the business line is **confirmed working** by Daniel.
- **BUILT + CANARY-VALIDATED + ROLLBACK-PROVEN, awaiting cutover (this workstream, 2026-07-10):** B Unit 1 clones are live-built, re-skinned to twx v2, and fully verified. **MENU_CLONE_ID = 7333** (section, = source 305 + 5 approved twx2 class deltas, custom_css set, dormant). **HEADER_CLONE_ID = 7336** (header, = source 36 + 10 class deltas + ct_saved_rows rewired 305→7333, custom_css set, dormant). Canary on page 6065 rendered clone 7336+7333 correctly (navy/yellow sticker header, sticky+headershow, POP modal opens/styled/closes, all 8 menu items, nav-fit 13px/.25px/6px, tracking parity GA×2/fbq init+PV/2 chat loaders/1 bubble, no console errors). Rollback proven (6065 reverted to 36), then **canary REAPPLIED for Daniel's review**. **END STATE (2026-07-10): CANARY LIVE on page 6065 only** — 6065 renders header clone 7336 + menu 7333; every other page renders original 36/305 (36 cond `["include/general","exclude/singular/page/6065"]`, 7336 cond `["include/singular/page/6065"]`, 305 & 7333 `[]`). Originals 36/305 data untouched (hashes intact). Seconds-fast revert available (36→`["include/general"]`, 7336→`[]`, then regenerate via editor `theme-builder-publish/save`). See UNIT 1 SESSION section below for the load-bearing conditions-cache mechanism + exact cutover steps.
- **NOT started:** Workstream D (live paid LP bug fixes) — needs Daniel's explicit OK before touching the live paid LP.

## Open items for Daniel

- **BlogVault is STILL IP-blocking anonymous requests from this machine (machine IP redacted) — anonymous request to a live page returns 403 firewall page.** This is a HARD GATE for Unit 1 cutover (runbook §4: "anonymous external QA unavailable" = NO-GO). Daniel must whitelist the IP at app.blogvault.net before cutover so anonymous convergence can be verified. (Real visitors on other IPs are unaffected; only this machine's external QA is blocked.)
- **Unit 1 cutover** needs Daniel present + low-traffic window + BlogVault whitelist. It is otherwise fully prepped (clones built + validated); cutover is ~4 REST condition writes + one editor regenerate + QA (see below).
- Global cutover of each chrome unit needs Daniel present + low-traffic window.
- WI license number still not provided (footers ship without it).
- In-app Codex MCP tool: if still erroring, quit+reopen the Claude app once. (This session used the Codex CLI `gpt-5.6-sol` ultra successfully.)

## Daniel design revisions (2026-07-10, applied live to the clones on canary 6065)
- **Tagline** header infobox 6e02966 `infobox_title`: "T'Winning Every Time" → **"Garage Doors, Done Right"** (owner-requested copy change on header clone 7336).
- **Quote CTA always-on shine**: appended a reduced-motion-safe `::before` shine-sweep animation (`@keyframes twx2-shine`, 3.4s) to the CTA in BOTH 7336 (`.twx2-header-cta`) and 7333 (`.twx2-popmenu .twx2-header-cta`) custom_css. Button now shines continuously, not just on hover.
- **FOOTER re-skin = UNIT 2 (footer 1409), FOOTER_CLONE_ID = 7344** (type footer). Dependency: footer embeds the Dual Button widget 466 (`btnDUAL`, uael-buttons) via shortcode `[elementor-template id="466"]`, so 466 renders INSIDE the footer clone DOM → reskinned by CSS scoped to `.elementor-7344 .btnDUAL` (NO separate 466 clone needed). Pure-CSS reskin (no element deltas): deep-navy `#010D38` bg, yellow `#FBBD04` headings + icon-list arrows + contact icons + social sticker-icons, light `#CFDBEA` text/links w/ yellow hover, dual button as yellow sticker. Preserved: phone tel:8338332010, mailto:contact@twinsgaragedoors.com, WI+KY NAP, 4 social links. Canary on 6065 verified (footer data-elementor-id=7344, bg #010D38). Original 1409 untouched (`["include/general"]`).
- **CURRENT LIVE STATE on page 6065**: header clone 7336 + menu clone 7333 + footer clone 7344 (all twx2, cohesive). All other pages = original 36/305/1409. Clones 7336(`include/singular/page/6065`), 7333(`[]`), 7344(`include/singular/page/6065`); originals 36+1409 have `exclude/singular/page/6065`.
- Committed CSS snapshots (unit1-clone-*.css) predate the shine+tagline tweaks; live clone custom_css is source of truth.
- **Round-2 revisions (2026-07-10, live on 6065):** (a) LOGO swapped to shield wordmark `twins-logo-text-final.png` (media id 6829) via clone-scoped CSS `content:url()` on `.elementor-7336 .twx2-header-logo img` (canary-only; global site logo id 32 unchanged). (b) HEADER top-row rebalanced: `.twx2-header-top > .elementor-container { align-items:center; justify-content:space-between }` + columns `width:auto;flex:0 1 auto` (killed the 667px tagline-column gap), tagline 17px. (c) BACKGROUND: `body { #EDF1F6 + faint navy radial-dot pattern }` added to FOOTER clone 7344 custom_css (renders on 6065 only; content sections are transparent so body shows through). All in clone custom_css, canary-scoped.
- **DONE (Daniel round-2 #3): door builder embedded on collection page 6065.** Builder is the self-contained WPCode shortcode **`[twins_door_builder]`** (snippet 7127; mounts `#twxdb`, app inlined, no iframe/tracking pollution; reads `?product={id}` from URL to auto-open a collection's design step). Added a new Elementor section (id `e26273a`) at position 1 (after hero) on page 6065: heading "Design Your Gallery® Steel Door" + subhead + shortcode widget `[twins_door_builder]`. Saved (Astra `site-post-title` still "disabled" — not wiped). Verified live: #twxdb mounts, renders collection picker, no errors. NOTE: this is PAGE CONTENT (shared, not canary-scoped) → live for ALL visitors of /clopay-gallery-steel/ now (reversible: delete section e26273a). Gallery Steel product id = **12**.
  - REFINEMENT PENDING: builder opens at the collection-picker step (not preloaded to Gallery Steel) because it reads product only from `?product=` URL param, and the shortcode takes no product attr. To auto-open Gallery Steel: small backward-compatible snippet-7127 edit (shortcode `product` attr → `data-product` on #twxdb → JS reads data-product then falls back to URL param). Not yet done (avoided live-builder-snippet edit).


- **Round-3 revisions (2026-07-10, live on 6065):** (a) FOOTER cohesion — overrode the dated orange CTA panel (`.elementor-7344 .elementor-element-08a213c` bg #010D38, heading yellow) and a stray white section (`.elementor-element-ac7fbea` bg transparent) so the whole footer is navy+yellow. (b) BACKGROUND stronger/branded — `body` now `#E6EDF5` + navy blueprint-grid (`linear-gradient` 1px lines, 34px, ~6% opacity) via footer-clone 7344 custom_css. (c) DOOR BUILDER now PRELOADS Gallery Steel: added an Elementor `html` widget (preload `<script>` doing `history.replaceState` to add `?product=12` before the builder reads `location.search`) as the FIRST child of the builder section, ABOVE the `[twins_door_builder]` shortcode. Rebuilt the builder section cleanly (new id `78da141`: html-preload + heading + shortcode) after an incremental widget-add had dropped the shortcode. Verified: builder opens on "Collection: Gallery® Steel / Pick your design" (skips collection picker). NOTE: preload adds `?product=12` to the 6065 URL in the address bar (cosmetic; canonical meta unaffected).


- **Round-4 revisions (2026-07-10, live on 6065):** (a) FOOTER white shape-divider chunk on the CTA panel recolored to navy (`.elementor-7344 .elementor-element-08a213c .elementor-shape-top ... fill:#010D38`). (b) FAQ upgrade — the FAQ is a custom HTML widget `.twx-faq` using `<details>/<summary>`; restyled via 7344 custom_css with yellow "?" icon badges (summary::before), yellow +/− toggles (summary::after), 6px yellow left accent bar, 12px rounded cards + sticker shadow, navy questions. (c) OPEN/ESCALATED: **door builder image quality** — the `[twins_door_builder]` app (snippet 7127, Phase-3 build) shows LOW-RES grayscale door thumbnails and does NOT re-render the door illustration when color/windows/glass change (shows a generic door, not the selected composite). This is a fundamental limitation of the existing tool, NOT a CSS fix — needs high-quality per-option Clopay renders or a builder rendering rebuild. Flagged to Daniel as a dedicated project; consider hiding the builder until improved.

## UNIT 1 — Session 2026-07-10 status + LOAD-BEARING mechanism + cutover procedure

### *** Conditions-cache mechanism (load-bearing for cutover AND every future chrome unit) ***
Elementor Pro caches theme-builder display conditions server-side. **A raw REST `_elementor_conditions` postmeta write is NOT honored on render until the cache regenerates. "Clear Files & Data" does NOT regenerate it.** The proven trigger:
1. REST-write the target `_elementor_conditions` on the doc(s) (format proven: array of strings; a specific page = `"include/singular/page/{ID}"` / `"exclude/singular/page/{ID}"`, verified against live footer 2179 = `include/singular/page/2123`).
2. Open the header clone in the Elementor editor (`/wp-admin/post.php?post=7336&action=elementor`); it loads conditions from postmeta into `elementorPro.modules.themeBuilder.conditionsModel` (verify it reflects the intended conditions).
3. Run `$e.run('theme-builder-publish/save')` (component namespace `theme-builder-publish`; commands next/save/preview-settings). This persists conditions AND regenerates the WHOLE conditions cache from all docs' postmeta (so it also picks up doc 36's condition change).
Then the render flips. Verify via a logged-in `fetch(path, {cache:'no-store'})` — edge shows `x-ac: ... BYPASS` (live PHP); parse the `<header ... data-elementor-id>`.

Editor/REST gotchas discovered: `$e.run('document/elements/settings',...)` for class deltas works; **`$e.run('document/save/default')` returns a NON-thenable** — fire it then poll `elementor.documents.getCurrent().editor.isChanged` until false (header doc save takes >8s). Elementor injects benign page-setting `hide_title:"yes"` and nav-menu `menu_name:"Menu"` (the menu's own name; `menu` selection unchanged) on save — both adjudicated benign (Codex ultra). REST elementor_library writable meta: `_elementor_data`, `_elementor_conditions`, `_elementor_template_type`, `_elementor_edit_mode`, `_elementor_page_settings`; NOT writable: content/excerpt/taxonomy/version (taxonomy `elementor_library_type` term is set by the editor save — verified 7336=header, 7333=section). Reskin CSS lives in each clone's `_elementor_page_settings.custom_css`, scoped `.elementor-{cloneid} .twx2-*`; body-mounted modal uses `.popMENU-popup:has(.elementor-7333 .twx2-popmenu)` (`:has()` supported).

### Exact CUTOVER procedure (when Daniel present + BlogVault whitelisted + low-traffic)
1. Drift recheck: 36 hash `783e2f…c0570` + cond `["include/general"]`; 305 hash `c61398…003ed` + cond `[]`; 7336 cond `[]` + 9a8b90d.ct_saved_rows `7333`; 7333 cond `[]`; menu-13 ledger head `92a5b8ac70a6` (51 items).
2. From canary state OR from dormant: REST-write **7336 → `["include/general"]`**, then within 5s **36 → `[]`** (do not warm public URLs between). If 2nd write fails, compensate: 7336 → `["include/singular/page/6065"]`, 36 → `["include/general","exclude/singular/page/6065"]` (recovers canary), stop.
3. Open 7336 editor → confirm conditionsModel = include/general → `$e.run('theme-builder-publish/save')` to regenerate.
4. Invalidation: Elementor Clear Files & Data (button id `elementor-clear-cache-button`) → WP Rocket Clear+Preload → a8c Edge Clear → verify clone CSS `post-7336.css`/`post-7333.css` return 200.
5. Verify: homepage + a normal service page + /contact-us/ + a post + a Canvas LP all render header 7336 + menu 7333; NO `elementor-36`/`elementor-305`; one header, one #menuhopin; tracking parity (GA `G-XW0RGPTGSN`/`G-JR7LW39CND` ×1 each, fbq init `554750209097175`+PageView ×1, 2 leadconnector loaders, 1 visible bubble). Anonymous cold+warm ×2 (needs BlogVault whitelist).
6. Rollback if any abort: 36 → `["include/general"]`, 7336 → `[]`, regenerate via editor, invalidate. (Proven this session: reverts in seconds + regen.)

### Then subsequent units (Codex gpt-5.6-sol ultra authors each package first, same clone-and-swap + this mechanism)
Footer **1409 + widget 466** → alt header/footer **2163 + 2179** → library sections **1498-1516**. Backups already committed in `docs/superpowers/backups/2026-07-10-phase5-B/`.

## Token budget note

Prior phases: A ~heavy (subagent deaths), C moderate, B-prep moderate. B execution (clone + reskin + canary + regression at 9 widths + cutover, per unit) is substantial and careful — pace it one unit per session, and lean on Codex gpt-5.6-sol ultra for the authoring/verification thinking so Claude's context stays focused on the live browser execution. UPDATE this handoff at session end.
