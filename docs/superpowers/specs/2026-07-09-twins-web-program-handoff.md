# HANDOFF PROMPT — Twins web program: Phase 5 (/wi build-out + site re-skin start)

Paste this whole document into a new Claude Code chat in `~/twins-dashboard`. (Rewritten at the end of the Phase 2-3-4 session, 2026-07-09 evening; prior versions in git history.)

---

You are continuing a large website program for Daniel (non-dev owner of Twins Garage Doors, Madison WI). Phases 1-4 are DONE and all program decisions are CONFIRMED (below — do not re-ask). Start by invoking `/superpowers:brainstorming` scoped to **Phase 5 only**: /wi build-out + the first site re-skin batches. Design → spec → plan → subagent build → verify → change-log. Everything reversible. Show plans before building.

## Environment — verified working patterns (reuse, don't rediscover)

- **Site:** WordPress multisite on SiteGround: `twinsgaragedoors.com` (main) + `/wi` + `/ky`. Elementor + Astra, WPCode, WP Rocket (main + /ky only; **/wi has NO Rocket**), Rank Math PRO. Chrome (claude-in-chrome MCP) logged in as admin "Tal Joseph"; reuse the session's existing tab.
- **Brand tokens:** navy `#022751`, deep `#010D38`, yellow `#FBBD04`, soft `#F2F5F7`, Montserrat — all in the site-wide `twx-ui` style block. **twx v2 kit** (`.twx2-` classes) is LIVE in snippets 7050 (main) + 6755 (/wi): twins pair + bob, sticker cards/buttons, trust ribbon, steps, closer, textured hero. Twins sizing is CALIBRATED (3 rounds w/ Daniel): front `min(34vw,420px)`, back `min(26vw,325px)`, bottom-right, ~250px right-aligned mobile, NEVER hidden, bob 5.5s/6.5s staggered + reduced-motion guard. Approved mockup: `docs/superpowers/backups/2026-07-09-phase4-catalog/twx-v2-mockup-approved.html` (also artifact "Twins twx v2 — design direction mockup").
- **Chrome MCP gotchas:** javascript_tool output truncates ~1,000 chars (stash in window vars, read ≤1,000-char slices); DLP blocks any key=value-shaped output (plain prose only); tool calls >45s keep running in-page — poll a window progress var, never "stand by" for a detached shell; CSS animations freeze while the tab is hidden (foreground before screenshots); `resize_window` is inert — test mobile via a 390px same-origin iframe; screenshot save_to_disk returns IDs but no local files (take final proof shots in the MAIN session so the user sees them inline). Subagents keep stalling on detached-shell waits — tell them to run fetch loops in FOREGROUND batches.
- **WPCode:** editor at `admin.php?page=wpcode-snippet-manager&snippet_id={id}` (per site); auto-prepends `<?php` (never include your own); Save can silently miss — verify the "Snippet updated." toast; edit via the CodeMirror instance (getValue/setValue) with sha256 computed in-page before AND after save; pbcopy transfer channels need `LANG=en_US.UTF-8` (multibyte mojibake otherwise). A PHP syntax error in a Run-Everywhere snippet can white-screen the site: fetch the homepage within seconds of saving and be ready to setValue the backup back.
- **Elementor page-building (the proven pipeline, 21 pages built with it):** builder script `docs/superpowers/backups/2026-07-09-phase4-catalog/twx2-page-builder.js` (READ ITS HEADER) + content pack + `qa-gate.py` in the same dir. Per-page order (hard-won): REST draft w/ placeholder → Rank Math `updateMeta` (nonce via `fetch('/wp-admin/admin-ajax.php?action=rest-nonce')`, re-fetch after every editor reload) → open editor, wait for `elementor.documents.getCurrent().container.children.length > 0` → run builder → save (15-20s; poll) → **JSON-LD widget drop is DETERMINISTIC (21/21 pages)**: reload, re-add via `$e.run('document/elements/create', …, {at:1})`, save, reload, verify → **Astra `site-post-title` meta LAST (Elementor saves wipe it)** → publish → QA gate. Batches of ~5 pages/agent ≈ 180k tokens.
- **Caches:** WP Rocket purge = the "Clear and preload" button/admin-post from a wp-admin page (NO toast on this site — verify via the 200-after-redirect); a8c Edge Cache (Settings → Edge Cache) DOES toast "Edge Cache has been cleared."; anonymous HTML is Batcache+edge w/ ~300s TTL and Accept-header variants. Meta-before-publish avoids all cache waits.
- **FIREWALL (important):** BlogVault 403-blocks ALL anonymous requests from this machine's redacted IP (re-blocked 2026-07-09 evening, ref 12819850836a50bfe361b7b — tripped by QA-fetch volume). Logged-in requests are unaffected — run every check through the logged-in tab via `fetch(path, {credentials:'same-origin'})` (redirects: `{redirect:'follow'}` then inspect response.url/status). Daniel must whitelist the IP at app.blogvault.net; until then, anonymous QA re-runs are pending. Throttle ≥6s between site requests REGARDLESS.
- **Rank Math:** per-page meta via `POST {site}/wp-json/rankmath/v1/updateMeta` (form-encoded, X-WP-Nonce; success `{"slug":true,"schemas":[]}`). **Redirections add/edit form is BROKEN site-wide** (form container missing, JS TypeError) — use Redirections → Import & Export → CSV import instead (17 rows imported that way; record: `redirects-import.csv`). A separate fix session may already be running (task_754986de).
- **Clopay API v2:** list `GetProductsList/GetProducts?productType=Residential` (23 products), detail `GetProductDetails/GetProductData?productId={id}` (NOT GetProductData/GetProductData). CORS `*`. Committed snapshot: `docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot/`. Bespoke products 8/291 have empty Colors/Designs arrays. Overview prose CONTRADICTS the option arrays on several products — always compute counts from arrays. Dealer propId 100841.
- **Git:** this checkout is SHARED with concurrent sessions — branch can change under you; commit early, only your own paths (never `git add -A`), verify `git branch --show-current` before committing; a temp `git worktree` on main is the safe way to commit if the checkout is on someone else's branch. Live-edit subagents must NOT commit (orchestrator commits).

## Current live state (all committed; change-log has K/D/M/G-rows with revert paths)

- **Phase 1** (gallery fix + polish), **Phase 2** (menus: Locations→State→Cities w/ Madison+Milwaukee top, Design Your Door top-level, Hörmann removed, mobile-menu scroll fix, nav-fit fix), **Phase 3** (door-builder visualizer at /door-builder/ main 7129 + /wi 6766; endpoint 7072 v2 emails full door config; funnels redirect to the builder, /ky still EZDoor), **Phase 4** (below) — all DONE 2026-07-09.
- **Catalog:** all 23 Clopay residential collections live on main at /clopay-*/ (v2 pages 7137-7302 + pre-existing 6034/6065/6090), hub `/clopay-garage-doors/` (7141, server-rendered 23-card grid via `[clopay_collection_grid]`), menu item "All Clopay Collections" first under Garage Doors. Hero CTAs deep-link `/door-builder/?product={id}` (builder snippet 7127 supports the param). 17 legacy pages 301'd (Rank Math CSV) + drafted; menu 42 deleted. 24/24 pages passed the QA sweep (logged-in).
- **Site-wide mobile Call/Book bar** on main (`#twx2-stickybar`: (833) 833-2010 + HCP booking `https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true`); old `#twins-callbar` (snippet 7044, dials 608) scoped to `-lp` pages via WPCode Conditional Logic.
- **Snippet inventory:** main — 7050 (twx-ui + v2 kit + grid + sticky bar + RUCSS safelist + propId), 7072 (door-builder lead endpoint v2 → contact@), 7127 (door-builder app + ?product deeplink), 7044 (LP callbar, -lp only), 7028 (Madison LP lead endpoint, don't touch); /wi — 6755 (twx-ui + v2 CSS), 6765 (door-builder app); /ky — 6369 (twx-ui v1). All deployed bodies + before-files in `docs/superpowers/backups/2026-07-09-phase{3,4}-*/`.
- **Research digests for phases 5-7:** `docs/marketing/audits/2026-07-09-phase4-research/README.md` (Madison-LP feature audit, goodgollygarage teardown w/ shelf-list, site template inventory w/ re-skin batches + risky-list, Clopay audit resolution). READ THIS EARLY in Phase 5.

## DECISIONS — confirmed by Daniel (do not re-ask)

- Design: **Option A twx v2** everywhere (twins + sticker accents + textured heroes; Montserrat, one palette; review-card component ships only when fed by REAL Google reviews — never sample text).
- Positioning: MAIN = general/all locations; **/wi = Madison + Milwaukee + surrounding**; /il = Rockford region (Phase 6); /ky LAST (Phase 8).
- Phones: main (833) 833-2010 · /wi (608) 888-8785 · **Milwaukee-specific pages (414) 800-9271** · /il (815) 800-2025. Milwaukee address: 11220 W Burleigh St Ste 100, Wauwatosa, WI 53222.
- Full site chrome on all real pages (menus + full footer w/ NAP); thin footers are LP-only. **WI license number: still NEEDED from Daniel — never fabricate; ship footers without it until provided.**
- /il (Phase 6): UNPUBLISHED build, no address until provided, 12 cities: Rockford, Loves Park, Machesney Park, Belvidere, Roscoe, Rockton, Cherry Valley, Poplar Grove, South Beloit, Winnebago, Byron, Caledonia.

## Phase 5 scope (NEXT — needs its own brainstorm/spec; suggested shape, confirm details with Daniel only where genuinely new)

1. **/wi collection pages** — decide count/targeting (Madison+Milwaukee positioning; avoid cannibalizing main's general pages — surface the strategy in the spec; the Phase-4 pipeline stamps them out cheaply once decided).
2. **Milwaukee page complete overhaul** (existing `/wi/garage-door-repair-in-milwaukee-wi/` page 6460, big legacy Elementor build) on twx v2 with (414) 800-9271 + the Wauwatosa address; menu items (main + /wi) may then repoint.
3. **Site re-skin batch 1-2:** shared chrome (~12 Theme Builder docs: header 36/menu 305/footer 1409+2179/widget 466/library sections 1498-1516) then main service pages (~10, one template). Follow the inventory's risky-list; retest GTM/pixels/GHL chat after chrome changes.
4. Goodgolly shelf items that fit /wi: zip finder, city-tabbed reviews page (real reviews only), Milwaukee+Madison cost-FAQ pages w/ FAQPage schema (AEO gap).
5. LP bug fixes if Daniel OKs touching the live paid LP (placeholder preload, animation kill-switch, desktop Book link).

Phases after: 6 = /il subsite (unpublished), 7 = conversion upgrades woven through (largely absorbed by v2), 8 = /ky parity incl. its builder page + EZDoor redirect swap.

## Open follow-ups (start-of-session checklist)

- **BlogVault whitelist** (Daniel, app.blogvault.net): machine IP redacted, refs 2893084716a4fa5da9dd88 + 12819850836a50bfe361b7b. Once clear: re-run `qa-gate.py --all` anonymously over the 21 catalog pages (throttled) to close K5's pending item.
- **Rank Math redirection form fix** — separate session may be running (task_754986de); if not fixed, keep using CSV import.
- **/wi phone display** — runtime rewrite shows (608) 925-2038 over HTML's 888-8785; Daniel has still not confirmed which is the intended tracking number. Ask once during Phase 5 (it decides what the /wi rebuilds hardcode).
- **WI license number** from Daniel for footers.
- 5 TEST leads in contact@ inbox named "TEST — Claude Phase 3 …" — Daniel can delete.
- GHL leftovers (unchanged, low priority): unused contact field `lead_region`; possible stray unsaved form "Door Builder - Main".

## Token budget (actuals)

Phase 1 ~750k · Phase 2 ~900k · Phase 3 ~1.1M · Phase 4 ~2.4M (three design-calibration rounds + firewall re-block + two session-limit cuts; the page pipeline itself ≈180k per 5-page batch). Phase 5 estimate: **1.5-2.5M** (chrome re-skin is new territory; /wi pages ride the proven pipeline). One phase per session; UPDATE THIS HANDOFF at session end.

## Phase 5 progress (updated 2026-07-10)

**Workstream A (/wi Madison + Milwaukee) — DONE this session.** Design + plan + change-log rows P5-1..P5-7 committed. Delivered:
- Milwaukee hub (page 6460) + Madison install hub (page 1616) rebuilt on twx v2 via the Phase-4 pipeline: branded navy/yellow bands, collection **carousel** (23 cards, builder deep-links), reviews **carousel** (brb 2178, was a 437-review wall), 5-Q FAQ + FAQPage/LocalBusiness JSON-LD, cost ranges (from real jwrpj jobs) in FAQ only. Milwaukee = (414) 800-9271 + Wauwatosa NAP; Madison = (608) 420-2377 + 2921 Landmark Pl NAP.
- /wi phone standardized: GHL number pool (snippet 6657) deactivated; snippet 6753 rewritten as a **per-metro** runtime unifier (Milwaukee→414, else→420) covering header/footer/mobile-bar text + tel. Rank Math Local SEO NAP fixed (620 N Carroll → 2921 Landmark Pl). Live-verified rendered: Madison header 420-2377, Milwaukee header 414, zero 888-8785/925-2038/620-N-Carroll displayed.

**Workstream A open follow-ups:**
- **Forward test (Daniel):** dial (608) 420-2377, confirm it rings the business line. Not yet verified.
- Header/footer chrome still carries 888-8785 in *raw HTML* (client-side JS swaps the *display* to 420/414 correctly). Optional later pass: hardcode header doc 36 + footer doc 1409 to the tracking number for raw-HTML/AEO cleanliness. Low priority (display is correct; schema is correct).
- Anonymous QA (`qa-gate.py`) + a manual a8c Edge Cache purge still pending the BlogVault IP whitelist. Elementor saves auto-purge the two page URLs, and the phone swap is client-side JS (runs even on cached HTML), so display is correct regardless.
- Snippet 6754 emits a third, address-less LocalBusiness block (harmless; primary schema is clean). Remove in a later pass if desired.
- Collection carousel images lazy-load on horizontal scroll (first few cards load immediately; verified all load).

**Workstream C (cost pages + zip finder) — DONE 2026-07-10 (same session).** Two NEW /wi cost pages (Madison 6807 + Milwaukee 6808) on twx v2: answer-first "short answer" card, branded price table (real jwrpj job ranges + $49 service call), what-affects-price, financing, navy steps, 8-Q FAQ + FAQPage/LocalBusiness JSON-LD, deterministic zip finder (537→Madison hub, 531/532→Milwaukee hub, else→contact). Phones per metro (420 / 414). Targets the AEO cost-query gap. Design: `2026-07-10-phase5-C-cost-pages-design.md`; change-log rows P5C-1..3; artifacts in `docs/superpowers/backups/2026-07-10-phase5-C/`. Optional follow-ups: link hub FAQ "how much" answers to these cost pages; add the zip finder onto the hubs.

**Workstream B (chrome re-skin) — PREPPED, execution deferred to a fresh session (Daniel's call 2026-07-10).** Do NOT edit in place; Codex reviewed and NO-GO'd that. Safe path is authored and committed:
- Reviewed by Codex `gpt-5.6-sol` ultra (via CLI `codex exec`; the in-app codex MCP tool has a stale token and needs a host-app restart). Review verdict + reasons: `docs/superpowers/specs/2026-07-10-phase5-B-codex-review.md`.
- Full backups of all 6 main-site chrome docs (Elementor data + conditions): `docs/superpowers/backups/2026-07-10-phase5-B/tb-{36,305,466,1409,2163,2179}.json`.
- Site-qualified manifest (MAIN site): header **36** + footer **1409** = `include/general` (every page); menu **305** + widget **466** = no condition (referenced); alt footer **2179** = page 2123 (/contact-us); alt header **2163**. Library sections 1498-1516.
- **Unit 1 execution package (header 36 + POP menu 305)** authored by Codex `gpt-5.6-sol` ultra: `docs/superpowers/specs/2026-07-10-phase5-B-unit1-header-menu-runbook.md`. Contains the preserve-list (22 element IDs, `#menuhopin` sticky, `15c4a1b` nav-fit coupling, chat mount, all links + SHA-256 drift hashes), re-skin spec (additive `.twx2-*` classes only, NEVER rename an ID), clone-and-swap runbook (canary page = `/clopay-gallery-steel/` id 6065), regression matrix (9 widths, anon+logged-in), rollback (condition-flip, seconds-fast).

**NEXT-SESSION ENTRY POINT for B:** open the Unit 1 runbook; re-verify the drift hashes still match (another editor session may have changed 36/305); then execute stages 3.1→3.9. Clone+reskin+canary is safe/reversible (no global impact); the GLOBAL CUTOVER (§3.8) requires Daniel present + a low-traffic window. BlogVault whitelist is DONE, so anonymous QA works now. After Unit 1: repeat for footer 1409+466, then alt header/footer 2163/2179, then library sections, then B3 service pages (separate plan).

**Workstream D (LP fixes) — NOT started.** Needs Daniel's explicit OK to touch the live paid LP. Fresh session.

## House rules (binding, from memory)

Show plan/diff before implementing; never fabricate operational data (prices, addresses, people, reviews); real Twins numbers only; keep pages simple and mobile-fit; no em-dashes in customer-facing copy; full dollar amounts; never reference Lovable; all changes reversible + change-logged; commit docs as you go (own paths only — shared checkout).
