# Phase 2 — Menu restructure, main + /wi (design)

Date: 2026-07-09. Approved by Daniel in-session. Parent program: `2026-07-09-twins-web-program-handoff.md` (all program decisions confirmed there).

## Findings from investigation (2026-07-09)

- Headers on main and /wi are Elementor Theme Builder templates (id 36 on each site). Every nav widget in them (Elementor nav-menu desktop + UAEL mobile) renders the site's WordPress menu **"Menu" (id 13)**. Footer quicklinks = menu 14 (untouched). **All Phase 2 changes are menu-item data edits; no Elementor template edits.**
- WP REST menu endpoints (`/wp-json/wp/v2/menus`, `/wp-json/wp/v2/menu-items`) work read/write in the logged-in admin browser session with `wpApiSettings.nonce`. Edits go through REST, not the drag-and-drop admin UI.
- Main menu 13 today: Locations → (Wisconsin `/wi`, Kentucky `/ky`) · Garage Doors → (Hörmann item 6180, Clopay Classic, Clopay Modern Steel, Clopay Gallery) · Openers → 3 LiftMasters · Services & Repair → 7 · Emergency Services · About Us → 5 · Blog. Hörmann exists only in main's menu.
- /wi menu 13 today: Locations (parent link wrongly points at main-site homepage `/`) → 21 city items — 17 custom links + 4 `location`-post items appended out of alphabetical order (Deerfield duplicated: custom item 5506 + location item 5972) · Garage Doors (no children) · Garage Door Openers → 3 LiftMasters · Services & Repair → 8 (incl. Emergency, TwinShield) · About Us → 5 · Blog. No Milwaukee, no Design Your Door.
- Milwaukee pages on /wi: big SEO/Elementor page **6460** `/wi/garage-door-repair-in-milwaukee-wi/` (~430k chars Elementor content) and thin city post 6492 `/wi/location/milwaukee/`. Menu points at **6460** until the Phase 5 overhaul (Daniel approved).
- Lexington target: `/ky/location/lexington/` (location post 2415).

## Changes

### Main site menu "Menu" (13)

1. **Remove Hörmann** menu item 6180. Page 6132 `/hormann-garage-doors/` stays published.
2. **Add top-level "Design Your Door"** → `https://twinsgaragedoors.com/design-your-door/` (page 7073), positioned after Garage Doors, before Openers.
3. **Locations restructure** (3 levels):
   - Wisconsin (existing item 4957, link `/wi`) gets children, in this order: **Madison** → `/wi/location/madison/`, **Milwaukee** → `/wi/garage-door-repair-in-milwaukee-wi/`, then alphabetically: Belleville, Cottage Grove, Cross Plains, Deerfield, DeForest, Edgerton, Evansville, Fitchburg, Fort Atkinson, Janesville, Marshall, McFarland, Middleton, Milton, Monona, Oregon, Prairie Du Sac, Sun Prairie, Verona — each → its existing `/wi/location/{city}/` URL (cross-subsite custom links; no new pages).
   - Kentucky (existing item 4956, link `/ky`) gets one child: **Lexington** → `/ky/location/lexington/`.
   - Fallback (only if screenshot verification shows the mobile accordion is unusable): trim Wisconsin's children to Madison, Milwaukee, "All Wisconsin Cities" → `/wi`. Decision made from the screenshots, logged in the change-log either way.

### /wi menu "Menu" (13)

1. **Locations children reordered:** Madison first, **Milwaukee added second** → `/wi/garage-door-repair-in-milwaukee-wi/`, then the rest alphabetically (same list as above minus none — Fort Atkinson, Marshall, Prairie Du Sac fold into order). **Duplicate Deerfield removed** (delete location-post item 5972, keep custom item 5506 — same URL).
2. **Locations parent link fixed:** currently `/` (main homepage). New target: `/wi/service-area/` or `/wi/locations/` if such a page exists (checked during build); otherwise `#` (non-link dropdown parent, same pattern as Services & Repair). Never left pointing at the main homepage.
3. **Add top-level "Design Your Door"** → `https://twinsgaragedoors.com/wi/design-your-door/` (page 6756), after Garage Doors, before Garage Door Openers.
4. No state layer on /wi (it is the Wisconsin site); no Hörmann present (nothing to remove).

## Mechanics

- All edits via WP REST `menu-items` create/update/delete from the logged-in Chrome session (nonce auth). Ordering via `menu_order` + `parent`.
- Cache: WP Rocket purge via its settings-page button (main only; /wi has no Rocket), then main's Settings → Edge Cache → Clear (a8c edge CDN fronts anonymous traffic).
- Throttle rule applies to all automated site checks: ≥6s between requests, browser UA.

## Verification

- Logged-in rendered checks + **desktop and mobile screenshots of every changed dropdown, both sites** (hero requirement of this phase).
- Confirm: Hörmann absent from nav but `/hormann-garage-doors/` still live; Design Your Door present + clickable on both sites; every new Locations link resolves 200 to the intended page; region phones unchanged.
- Anonymous cached-output verification stays **PENDING** until Daniel whitelists the redacted machine IP at app.blogvault.net (BlogVault firewall, carried from Phase 1 change-log row P2).

## Reversibility

- Before any edit: full JSON dump of both sites' menus + items committed to `docs/superpowers/backups/2026-07-09-phase2-menus/` (main-menus.json, wi-menus.json).
- Revert = re-apply the dump via the same REST endpoints (deleted items are recreated with same title/URL/parent/order; new item ids are fine — nothing references menu-item ids).
- Change-log entries in `docs/marketing/change-log.md` with per-change revert paths.

## Out of scope (recorded intel for later phases)

- **Phase 4 correction:** main already has **17 published Clopay product pages** (/avante/, /classic-steel/, /classic-wood/, /modern-steel-ultra-grain-plank/, /avante-sleek/, /reserve-wood-custom/, /canyon-ridge-carriage-house-5-layer/, /coachman/, /canyon-ridge-louver/, /vertistack-avante/, /canyon-ridge-elements/, /canyon-ridge-modern/, /canyon-ridge-carriage-house-4-layer/, /gallery-steel/, /grand-harbor/, /reserve-wood-modern/, /bridgeport-steel/ — pages 6403-6427) plus an unused WP menu "Clopay Products Menu" (id 42, no theme location, not rendered on the homepage). Several overlap the new twx collection pages (e.g. /classic-steel/ vs /clopay-classic-collection/). Phase 4's "~20 new pages" assumption is wrong; it needs an audit + dedupe decision first.
- Milwaukee page overhaul (Phase 5), /wi collection pages (Phase 5), /il (Phase 6), /ky menu parity (Phase 8).
- /wi runtime phone rewrite to (608) 925-2038 by the "CAP: unify stray phone numbers" script — Daniel to confirm intended (carried from Phase 1).
