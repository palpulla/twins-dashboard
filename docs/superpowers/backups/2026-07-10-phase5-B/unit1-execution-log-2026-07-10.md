# UNIT 1 — Fresh live state captured 2026-07-10 (by Claude, logged-in as admin Tal Joseph, main site only)

## Drift / concurrency gate results (all via authenticated WP REST `?context=edit`, DLP-safe booleans)

- Header **36**: `_elementor_data` SHA-256 **MATCHES** runbook (`783e2f...c0570`). status=`publish`, template_type=`header`, conditions=`["include/general"]`, modified_gmt 2023-12-26T16:37:39, title "Header".
- POP MENU **305**: `_elementor_data` SHA-256 **MATCHES** runbook (`c61398...003ed`). status=`publish`, template_type=`section`, conditions=`[]`, modified_gmt 2022-10-21T01:43:59, title "POP Menu Template".
- Because both hashes match the committed backups byte-for-byte, the committed `tb-36.json`/`tb-305.json` == live data. All structural analysis below is from those backups.
- Widget `9a8b90d`: widgetType=`uael-modal-popup`, `content_type="saved_rows"`, `ct_saved_rows="305"` ✓ (the one permitted functional delta → String(MENU_CLONE_ID)).
- All 23 preserve-list element IDs present in 36+305. 305 root section id = `4b98c6c`. Tree: 36 has 17 nodes, 305 has 6.
- REST route `/wp-json/wp/v2/elementor_library` OPTIONS → methods `["GET","POST"]` (authenticated create/update available).

## Menu 13 fresh ledger (dynamic; drives both original + clone via settings.menu="menu")

51 items total. 8 top-level in order: Locations (2 kids: WI, KY), Garage Doors (4), Design Your Door (top-level, 0 kids), Openers (3), Services & Repair (href="#", 7), Emergency Services (0), About Us (5), Blog (0). Hörmann ABSENT ✓. Milwaukee PRESENT ✓. Matches runbook §1 enumeration.

## §3.1 step 8 external-coupling scan (live rendered /clopay-gallery-steel/, 375,691 bytes, logged-in fetch)

- ZERO occurrences of `.elementor-36` or `.elementor-305` (word-boundary) anywhere in the full rendered HTML (includes all inline `<style>`, snippet-7050 injected CSS, theme customizer CSS). => no hand-authored root-class coupling.
- Generated Elementor CSS is loaded as EXTERNAL files: `/wp-content/uploads/elementor/css/post-36.css`, `post-305.css`, `post-6065.css`, `post-1409.css`, `post-466.css`, `post-18.css`. Clone will regenerate `post-{clone}.css`.
- `data-elementor-id` on page: 36, 305, 6065, 1409, 466 (all expected). `#menuhopin` count = 1.

## Additive-class map (current value → appended; sections use `css_classes`, widgets use `_css_classes`)

| Element ID | elType/widget | key | current | append → new |
|---|---|---|---|---|
| 33faaae | section | css_classes | `secHEADER` | `secHEADER twx2-header` |
| 739983d | section | css_classes | `topHEADER` | `topHEADER twx2-header-top` |
| 1120384 | widget theme-site-logo | _css_classes | `tgLOGO` | `tgLOGO twx2-header-logo` |
| bdcac79 | widget theme-site-logo | _css_classes | `tgLOGO` | `tgLOGO twx2-header-logo` |
| 5501e957 | widget theme-site-logo | _css_classes | (none) | `twx2-header-logo` |
| 6e02966 | widget uael-infobox | _css_classes | `headerTAG` | `headerTAG twx2-header-tag` |
| f33e6c3 | widget uael-infobox | _css_classes | `headerCALL` | `headerCALL twx2-header-call` |
| fe4e792 | widget uael-infobox | _css_classes | `headerCALL` | `headerCALL twx2-header-call` |
| 8d4d9e4 | widget uael-marketing-button | _css_classes | `headerBTN` | `headerBTN twx2-header-cta` |
| 6a5d8f6 | widget uael-marketing-button | _css_classes | `headerBTN` | `headerBTN twx2-header-cta` |
| cc876c5 | section | css_classes | `botHEADER` | `botHEADER twx2-header-nav` |
| 15c4a1b | widget nav-menu | _css_classes | `headerMENU` | `headerMENU twx2-header-menu` |
| 9a8b90d | widget uael-modal-popup | _css_classes | `popMENU` | `popMENU twx2-pop-trigger` |
| 4b98c6c | section (305 root) | css_classes | (none) | `twx2-popmenu` |
| 78fc1bc8 | widget uael-nav-menu | _css_classes | `headerMENU` | `headerMENU twx2-header-menu` |

Elements 33faaae, 739983d, cc876c5, 9a8b90d, 8d4d9e4, 6a5d8f6, 6e02966, f33e6c3, 15c4a1b live in header 36. Elements 4b98c6c, 4facb93a, 5501e957, 78fc1bc8, fe4e792, 6a5d8f6(POP quote) live in POP menu 305 (the saved row). NOTE: the modal popup DOM (`#modal-9a8b90d`, `.popMENU-popup`) is mounted at body level by the header widget; the saved-row section 4b98c6c renders inside that body-level modal.

## FROZEN passive baseline — page 6065 original header 36 (logged-in, delayed-JS released)
- GA: `G-XW0RGPTGSN` ×1 runtime config, `G-JR7LW39CND` ×1 runtime config; each ×2 source mentions. No GTM container.
- Meta: `fbq('init','554750209097175')` ×1, `fbq('track','PageView')` ×1 (from source, stable).
- Chat: 2 `leadconnectorhq.com/loader.js` in source (known WPCode + phantom wp_body_open double-load), 1 unique data-widget-id, **1 visible bubble** (svg).
- Header DOM: 1 `header.elementor-location-header` (data-elementor-id=36), 1 `#menuhopin`.
- CANARY REQUIREMENT: all identical except header data-elementor-id=HEADER_CLONE_ID and nested saved section data-elementor-id=MENU_CLONE_ID; header & #menuhopin counts stay 1; GA/fbq/chat counts unchanged; no 3rd loader; still 1 visible bubble.

## REST capability + pre-clone state (2026-07-10)
- REST elementor_library WRITABLE meta: _elementor_data(string), _elementor_conditions(array), _elementor_edit_mode, _elementor_template_type, _elementor_page_settings(object). Top-level: title, status, template (rw).
- NOT REST-writable: content, excerpt, taxonomy elementor_library_type, _elementor_version/_pro_version/_wp_page_template. => taxonomy + post_content reconciled by Elementor editor save (wp_set_object_terms + regenerate). Clone stays conditionless until AFTER that save, so no live taxonomy-less window. Prove via rendered resolution on MENU clone.
- :has() supported in browser (modal CSS valid).
- Source 36 & 305 _elementor_page_settings = {} (NO existing custom_css to preserve). edit_mode=builder, template="".
- Menu-13 baseline: 51 items, ledger sha256 head 92a5b8ac70a6 (window.__menuLedgerHash) — re-compare before every condition flip.

## ROLLBACK SOURCE (originals never edited except conditions)
- Header 36: conditions ["include/general"], _elementor_data sha256 783e2f...c0570 (== committed tb-36.json).
- POP 305: conditions [], _elementor_data sha256 c61398...003ed (== committed tb-305.json).
- Clone IDs (filled as created): MENU_CLONE_ID=?, HEADER_CLONE_ID=?

## BUILD PROGRESS
- MENU_CLONE_ID = 7333 (section). BUILT+VERIFIED 2026-07-10: tree = source305 + EXACTLY 5 approved class deltas (4b98c6c→twx2-popmenu, 5501e957→twx2-header-logo, 78fc1bc8→+twx2-header-menu, fe4e792→+twx2-header-call, 6a5d8f6→+twx2-header-cta). custom_css set (sha256 4bbf33e0…) survived editor save. conditions []. Elementor added benign page setting hide_title:"yes" (clone-only, saved-row has no page title → no effect). post-7333.css not yet generated (conditionless; will generate at canary render).
- HEADER_CLONE_ID = (creating now)

- HEADER_CLONE_ID = 7336 (header). BUILT 2026-07-10: tree = source36 + 10 approved class deltas + 9a8b90d.ct_saved_rows 305->7333 + 9a8b90d._css_classes popMENU->popMENU twx2-pop-trigger. custom_css set (sha256 330b68a2…) survived save. conditions []. No remaining ct_saved_rows "305". Original 36 untouched (hash + conditions ["include/general"]).
  - SAVE GOTCHA: $e.run('document/save/default') returns a NON-thenable; must fire then poll doc.editor.isChanged until false. Header doc save took >8s (larger than menu). elementor.saver.update({}) + wait helped it flush.
  - UNEXPECTED DELTA (adjudicating w/ Codex ultra): 15c4a1b.menu_name "<absent>"->"Menu". FACTS: menu selection unchanged (source & clone menu="menu" -> WP menu 13); menu 13's registered name IS "Menu"; clone-only; originals intact. High-confidence benign Elementor cache-populate. PAUSED before canary per Codex §6 stop-gate until cleared.

## LIVE STATE RIGHT NOW (nothing live changed)
- 36 conditions ["include/general"] (LIVE header, untouched). 305 conditions [] (LIVE saved-row, untouched).
- 7333 conditions [] (not live). 7336 conditions [] (not live). => zero global impact so far; both clones dormant.

## ADJUDICATION (Codex gpt-5.6-sol ultra) — menu_name delta
VERDICT: benign default materialization. menu_name only sets the nav aria-label (source already rendered aria-label="Menu" by default => output-equivalent). Operative selector menu="menu" -> wp_nav_menu() -> ID 13 unchanged. No preserve-list breach. Recorded as adjudicated default-injection exception to the diff allowlist. GO for page-6065-only reversible CANARY (NOT cutover).

## CANARY PLAN (§3.5) — condition states
- Canary: 36 -> ["include/general","exclude/singular/page/6065"]; 7336 -> ["include/singular/page/6065"]; 305 & 7333 stay [].
- Rollback (seconds): 36 -> ["include/general"]; 7336 -> []; then invalidate.
- RISK: Elementor may cache theme-builder conditions; REST postmeta write may need cache rebuild (Elementor Clear Files & Data). VERIFY via rendered resolution, not meta read-back.

## *** KEY MECHANISM (load-bearing for canary + cutover + all future units) ***
Elementor Pro caches theme-builder conditions server-side. A raw REST _elementor_conditions postmeta write is NOT honored on render until the cache regenerates. "Clear Files & Data" did NOT regenerate it. Correct trigger:
  1. Open the theme-builder doc in the Elementor editor (it loads conditions from postmeta into elementorPro.modules.themeBuilder.conditionsModel — verified it reflected include/singular/page/6065).
  2. Run: $e.run('theme-builder-publish/save')  (component namespace "theme-builder-publish", commands: next/save/preview-settings). This persists conditions AND regenerates the whole conditions cache from all docs' postmeta (picks up 36's exclude too).
=> After that, page 6065 renders header 7336 + nested menu 7333; controls stay on 36/305. Edge BYPASS live render confirmed.
For CUTOVER: after REST-writing final conditions (7336->include/general, 36->[]), must run theme-builder-publish/save on 7336 to regenerate. (Condition format proven: ["include","singular","page","2123"] on real footer 2179.)

## CANARY LIVE 2026-07-10 (page 6065 only)
- 6065 -> header 7336 + menu 7333 (twx2). Homepage + controls -> 36/305 unchanged. 36 conditions ["include/general","exclude/singular/page/6065"], 7336 ["include/singular/page/6065"].
