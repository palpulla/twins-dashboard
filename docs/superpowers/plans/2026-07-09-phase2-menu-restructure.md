# Phase 2 Menu Restructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure the nav menus on twinsgaragedoors.com (main) + /wi per the approved tree: Locations → State → Cities (Madison, Milwaukee top), Design Your Door top-level, Hörmann removed from menus only.

**Architecture:** Both sites' headers (Elementor Theme Builder template 36 per site) render WP menu "Menu" (id 13 per site). All changes are WP REST `menu-items` edits executed as JavaScript in the logged-in Chrome admin session (`mcp__claude-in-chrome__javascript_tool` on a wp-admin tab, nonce from `wpApiSettings.nonce`). No Elementor edits. Full JSON backups first; caches purged after; desktop + mobile screenshots verify.

**Tech Stack:** WordPress multisite REST API (`wp/v2/menus`, `wp/v2/menu-items`), claude-in-chrome MCP, WP Rocket + a8c Edge Cache purges.

**Spec:** `docs/superpowers/specs/2026-07-09-phase2-menu-restructure-design.md`

**Hard rules for the executor:**
- Chrome JS tool output truncates around 1,400 chars and a DLP filter BLOCKS outputs containing `key=value` patterns — return chunked, plain-prose-formatted strings (the chunk-reader pattern in Task 1).
- The REST nonce lives only on wp-admin pages: run main-site calls from a tab on `https://twinsgaragedoors.com/wp-admin/nav-menus.php` and /wi calls from `https://twinsgaragedoors.com/wi/wp-admin/nav-menus.php` (navigate between tasks). `/wi` REST paths are `/wi/wp-json/...`.
- Throttle all page fetches ≥6s apart. Anonymous curl from this machine is firewall-blocked (BlogVault, machine IP redacted) — verify logged-in only; anonymous verification stays PENDING in the change-log.
- Menu-item ids in this plan were read 2026-07-09. Re-verify each id's title before deleting anything; abort the step if it doesn't match.

---

### Task 1: Pre-change JSON backups of both menus

**Files:**
- Create: `docs/superpowers/backups/2026-07-09-phase2-menus/main-menu13-before.json`
- Create: `docs/superpowers/backups/2026-07-09-phase2-menus/wi-menu13-before.json`

- [ ] **Step 1: Dump main menu 13 to a window variable** (tab on main `/wp-admin/nav-menus.php`)

```js
const h = {headers:{'X-WP-Nonce': wpApiSettings.nonce}};
const items = await fetch('/wp-json/wp/v2/menu-items?menus=13&per_page=100', h).then(r=>r.json());
window.__dump = JSON.stringify(items.map(i=>({id:i.id,title:i.title.rendered,url:i.url,parent:i.parent,menu_order:i.menu_order,type:i.type,object:i.object,object_id:i.object_id,status:i.status})), null, 1);
'chars ' + window.__dump.length;
```

- [ ] **Step 2: Read the dump in chunks and write the file**

Run repeatedly with n incremented (0,1,2,...) until it returns "END":
```js
const n = 0; const c = window.__dump.slice(n*1300,(n+1)*1300); c.length ? c : 'END';
```
Concatenate chunks byte-for-byte; Write to `main-menu13-before.json`. Sanity check locally: `python3 -m json.tool <file> >/dev/null` → exit 0.

- [ ] **Step 3: Repeat Steps 1-2 for /wi** (tab on `/wi/wp-admin/nav-menus.php`, fetch `/wi/wp-json/wp/v2/menu-items?menus=13&per_page=100`) → `wi-menu13-before.json`.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/backups/2026-07-09-phase2-menus/
git commit -m "backup(web): phase-2 pre-change menu dumps (main + /wi)"
```

---

### Task 2: Main site menu edits

All on a tab at `https://twinsgaragedoors.com/wp-admin/nav-menus.php`.

- [ ] **Step 1: Verify then delete the Hörmann item (6180)**

```js
const h = {headers:{'X-WP-Nonce': wpApiSettings.nonce, 'Content-Type':'application/json'}};
const it = await fetch('/wp-json/wp/v2/menu-items/6180', h).then(r=>r.json());
if (!/H(ö|&ouml;|o)rmann/i.test(it.title.rendered)) throw new Error('id 6180 is not Hörmann, ABORT: ' + it.title.rendered);
const del = await fetch('/wp-json/wp/v2/menu-items/6180?force=true', {method:'DELETE', ...h});
'delete status ' + del.status;
```
Expected: `delete status 200`.

- [ ] **Step 2: Create Design Your Door + Lexington + 21 Wisconsin city children**

Wisconsin parent is item **4957**, Kentucky parent is item **4956**. City order: Madison, Milwaukee, then alphabetical.

```js
const h = {headers:{'X-WP-Nonce': wpApiSettings.nonce, 'Content-Type':'application/json'}};
const B = 'https://twinsgaragedoors.com';
async function mk(title, url, parent, order){
  const r = await fetch('/wp-json/wp/v2/menu-items', {method:'POST', ...h, body: JSON.stringify({title, url, parent, menu_order: order, menus: 13, type: 'custom', status: 'publish'})});
  const j = await r.json();
  return title + ' -> ' + (r.ok ? 'ok id ' + j.id : 'FAIL ' + r.status + ' ' + JSON.stringify(j).slice(0,80));
}
const cities = [
 ['Madison','/wi/location/madison/'],
 ['Milwaukee','/wi/garage-door-repair-in-milwaukee-wi/'],
 ['Belleville','/wi/location/belleville/'],
 ['Cottage Grove','/wi/location/cottage-grove/'],
 ['Cross Plains','/wi/location/cross-plains/'],
 ['Deerfield','/wi/location/deerfield/'],
 ['DeForest','/wi/location/deforest/'],
 ['Edgerton','/wi/location/edgerton/'],
 ['Evansville','/wi/location/evansville/'],
 ['Fitchburg','/wi/location/fitchburg/'],
 ['Fort Atkinson','/wi/location/fort-atkinson/'],
 ['Janesville','/wi/location/janesville/'],
 ['Marshall','/wi/location/marshall/'],
 ['McFarland','/wi/location/mcfarland/'],
 ['Middleton','/wi/location/middleton/'],
 ['Milton','/wi/location/milton/'],
 ['Monona','/wi/location/monona/'],
 ['Oregon','/wi/location/oregon/'],
 ['Prairie Du Sac','/wi/location/prairie-du-sac/'],
 ['Sun Prairie','/wi/location/sun-prairie/'],
 ['Verona','/wi/location/verona/']
];
const out = [];
for (let k=0; k<cities.length; k++) out.push(await mk(cities[k][0], B+cities[k][1], 4957, k+1));
out.push(await mk('Lexington', B+'/ky/location/lexington/', 4956, 1));
out.push(await mk('Design Your Door', B+'/design-your-door/', 0, 1));
window.__mk = out; 'created ' + out.length + ' items, failures: ' + out.filter(s=>s.includes('FAIL')).length;
```
Expected: `created 23 items, failures: 0`. If failures > 0, read `window.__mk` in chunks and fix before continuing.

- [ ] **Step 3: Renumber the whole menu into the final order**

Final top-level order: Locations, Garage Doors, Design Your Door, Openers, Services & Repair, Emergency Services, About Us, Blog. Children follow their parent; Wisconsin before Kentucky.

```js
const h = {headers:{'X-WP-Nonce': wpApiSettings.nonce, 'Content-Type':'application/json'}};
const items = await fetch('/wp-json/wp/v2/menu-items?menus=13&per_page=100', h).then(r=>r.json());
const byId = {}; items.forEach(i=>byId[i.id]=i);
const topOrder = ['Locations','Garage Doors','Design Your Door','Openers','Repair','Emergency','About','Blog']; // 'Repair' ranks "Services & Repair" — do not use 'Services', it also matches "Emergency Services"
const rank = t => topOrder.findIndex(k=>t.includes(k));
const roots = items.filter(i=>!i.parent).sort((a,b)=>rank(a.title.rendered)-rank(b.title.rendered));
if (roots.length !== 8 || roots.some(r=>rank(r.title.rendered)<0)) throw new Error('unexpected top level: ' + roots.map(r=>r.title.rendered).join(', '));
const seq = [];
function walk(parent){ items.filter(i=>i.parent===parent.id).sort((a,b)=>a.menu_order-b.menu_order).forEach(c=>{seq.push(c); walk(c);}); }
roots.forEach(r=>{seq.push(r); walk(r);});
let n=0, changed=0;
for (const i of seq){ n++; if (i.menu_order!==n){ const r = await fetch('/wp-json/wp/v2/menu-items/'+i.id, {method:'POST', ...h, body: JSON.stringify({menu_order:n})}); if(!r.ok) throw new Error('renumber fail on item '+i.id); changed++; } }
'renumbered, total ' + seq.length + ' items, updated ' + changed;
```
Note: the Wisconsin/Kentucky children created in Step 2 keep their Step-2 relative order (walk sorts by menu_order within a parent, and they were created with orders 1..21 / 1).

- [ ] **Step 4: Verify the resulting tree**

Re-fetch and print the indented tree (chunked, same pattern as investigation). Confirm: no Hörmann anywhere; Design Your Door top-level third; Wisconsin has 21 children starting Madison, Milwaukee; Kentucky has 1 child Lexington; totals: 8 top-level, 50 items (was 28, −1 Hörmann, +23 created).

---

### Task 3: /wi menu edits

All on a tab at `https://twinsgaragedoors.com/wi/wp-admin/nav-menus.php`; REST base `/wi/wp-json/`.

- [ ] **Step 1: Check for a /wi locations index page (decides the Locations parent link)**

```js
const h = {headers:{'X-WP-Nonce': wpApiSettings.nonce}};
const r = await fetch('/wi/wp-json/wp/v2/pages?slug=service-area,locations&per_page=5', h).then(r=>r.json());
r.length ? r.map(p=>p.id + ' ' + p.link).join(' ; ') : 'none';
```
If a page exists → Locations parent URL becomes that page's link. If `none` → parent URL becomes `#`.

- [ ] **Step 2: Delete duplicate Deerfield (item 5972) and fix the Locations parent (item 5502)**

```js
const h = {headers:{'X-WP-Nonce': wpApiSettings.nonce, 'Content-Type':'application/json'}};
const it = await fetch('/wi/wp-json/wp/v2/menu-items/5972', h).then(r=>r.json());
if (!/Deerfield/i.test(it.title.rendered)) throw new Error('id 5972 is not Deerfield, ABORT: ' + it.title.rendered);
const del = await fetch('/wi/wp-json/wp/v2/menu-items/5972?force=true', {method:'DELETE', ...h});
const NEW_PARENT_URL = '#'; // or the page link found in Step 1
const upd = await fetch('/wi/wp-json/wp/v2/menu-items/5502', {method:'POST', ...h, body: JSON.stringify({url: NEW_PARENT_URL})});
'delete status ' + del.status + ', parent update status ' + upd.status;
```
Expected: both 200.

- [ ] **Step 3: Create Milwaukee child + Design Your Door top-level**

```js
const h = {headers:{'X-WP-Nonce': wpApiSettings.nonce, 'Content-Type':'application/json'}};
const B = 'https://twinsgaragedoors.com';
async function mk(title, url, parent, order){
  const r = await fetch('/wi/wp-json/wp/v2/menu-items', {method:'POST', ...h, body: JSON.stringify({title, url, parent, menu_order: order, menus: 13, type: 'custom', status: 'publish'})});
  const j = await r.json();
  return title + ' -> ' + (r.ok ? 'ok id ' + j.id : 'FAIL ' + r.status);
}
const a = await mk('Milwaukee', B+'/wi/garage-door-repair-in-milwaukee-wi/', 5502, 2);
const b = await mk('Design Your Door', B+'/wi/design-your-door/', 0, 1);
a + ' ; ' + b;
```

- [ ] **Step 4: Renumber /wi menu — Madison first, Milwaukee second, cities alphabetical, Design Your Door after Garage Doors**

```js
const h = {headers:{'X-WP-Nonce': wpApiSettings.nonce, 'Content-Type':'application/json'}};
const items = await fetch('/wi/wp-json/wp/v2/menu-items?menus=13&per_page=100', h).then(r=>r.json());
const topOrder = ['Locations','Garage Doors','Design Your Door','Openers','Repair','About','Blog']; // 'Repair' ranks "Services & Repair"
const rank = t => topOrder.findIndex(k=>t.includes(k));
const roots = items.filter(i=>!i.parent).sort((a,b)=>rank(a.title.rendered)-rank(b.title.rendered));
if (roots.some(r=>rank(r.title.rendered)<0)) throw new Error('unexpected top level: ' + roots.map(r=>r.title.rendered).join(', '));
const cityRank = t => t==='Madison' ? '0' : t==='Milwaukee' ? '1' : '2'+t;
const seq = [];
for (const r of roots){
  seq.push(r);
  let kids = items.filter(i=>i.parent===r.id);
  if (/Locations/.test(r.title.rendered)) kids.sort((a,b)=>cityRank(a.title.rendered).localeCompare(cityRank(b.title.rendered)));
  else kids.sort((a,b)=>a.menu_order-b.menu_order);
  kids.forEach(k=>seq.push(k));
}
let n=0, changed=0;
for (const i of seq){ n++; if (i.menu_order!==n){ const r = await fetch('/wi/wp-json/wp/v2/menu-items/'+i.id, {method:'POST', ...h, body: JSON.stringify({menu_order:n})}); if(!r.ok) throw new Error('renumber fail on item '+i.id); changed++; } }
'renumbered ' + seq.length + ' items, updated ' + changed;
```
(/wi has no Emergency top-level — it sits under Services & Repair; the 7-entry topOrder list is correct for /wi.)

- [ ] **Step 5: Verify the resulting /wi tree** — re-fetch, print indented (chunked). Confirm: Locations children start Madison, Milwaukee, then alphabetical with exactly one Deerfield; Design Your Door top-level third; totals: 7 top-level, 44 items.

---

### Task 4: Cache purges (main only — /wi has no WP Rocket, but shares main's edge CDN)

- [ ] **Step 1: WP Rocket purge via its settings dashboard** — navigate to `https://twinsgaragedoors.com/wp-admin/options-general.php?page=wprocket`, click the **Clear and preload cache** button on the dashboard tab, confirm the success notice appears (screenshot it). Do NOT use the admin-bar link.

- [ ] **Step 2: Edge cache clear** — navigate to main wp-admin **Settings → Edge Cache**, click **Clear Edge Cache**, confirm the success notice (screenshot). This covers /wi's anonymous traffic too.

---

### Task 5: Verification — desktop + mobile screenshots, link checks

- [ ] **Step 1: Desktop screenshots (viewport ≥1280 wide).** On `https://twinsgaragedoors.com/` (logged in): hover **Locations**, then hover **Wisconsin** so the city flyout is open → screenshot; hover **Kentucky** → screenshot; screenshot the nav bar showing **Design Your Door** and no Hörmann under Garage Doors (hover Garage Doors → screenshot). Repeat on `https://twinsgaragedoors.com/wi/`: hover Locations (city list with Madison, Milwaukee on top) → screenshot; nav bar with Design Your Door → screenshot. Use `save_to_disk: true` and send the files to Daniel.

- [ ] **Step 2: Mobile screenshots.** Resize the browser window to 390×844 (`mcp__claude-in-chrome__resize_window`). On each site: open the hamburger, expand Locations (and Wisconsin on main), screenshot the accordion top and bottom (scroll); expand Garage Doors on main (no Hörmann); confirm Design Your Door visible. Restore window size after.

- [ ] **Step 3: Mobile usability decision (spec fallback).** If the Wisconsin accordion on main renders broken (overlapping, clipped, or unscrollable — not merely long), apply the trim variant: delete the 19 non-Madison/Milwaukee city items under 4957 and add one item "All Wisconsin Cities" → `https://twinsgaragedoors.com/wi`, re-screenshot, and record the trim in the change-log. A long but scrollable list PASSES — do not trim for length alone.

- [ ] **Step 4: Link checks (logged in, ≥6s apart, browser UA).** Fetch each distinct new URL once from the admin tab and expect HTTP 200: the 21 `/wi/...` city URLs, `/ky/location/lexington/`, `/design-your-door/`, `/wi/design-your-door/`, and `/hormann-garage-doors/` (page must still be live). Report any non-200 and fix its menu item before closing the task.

```js
const urls = ['/wi/location/madison/','/wi/garage-door-repair-in-milwaukee-wi/','/wi/location/belleville/','/wi/location/cottage-grove/','/wi/location/cross-plains/','/wi/location/deerfield/','/wi/location/deforest/','/wi/location/edgerton/','/wi/location/evansville/','/wi/location/fitchburg/','/wi/location/fort-atkinson/','/wi/location/janesville/','/wi/location/marshall/','/wi/location/mcfarland/','/wi/location/middleton/','/wi/location/milton/','/wi/location/monona/','/wi/location/oregon/','/wi/location/prairie-du-sac/','/wi/location/sun-prairie/','/wi/location/verona/','/ky/location/lexington/','/design-your-door/','/wi/design-your-door/','/hormann-garage-doors/'];
window.__lc = []; // run one URL per javascript_tool call, ≥6s apart:
const u = urls[0]; const r = await fetch(u, {credentials:'same-origin'}); window.__lc.push(u + ' ' + r.status); u + ' ' + r.status;
```

---

### Task 6: Documentation + handoff updates

**Files:**
- Modify: `docs/marketing/change-log.md` (new section at top)
- Modify: `docs/superpowers/specs/2026-07-09-twins-web-program-handoff.md` (mark Phase 2 done, add Phase 4 intel, set Phase 3 next)

- [ ] **Step 1: Change-log section** — add `## 2026-07-09 — Claude, Phase 2: menu restructure main + /wi (Daniel-approved spec)` with one row per change (M1 Hörmann removal, M2 main Locations tree, M3 main Design Your Door, M4 /wi Locations reorder + Milwaukee + Deerfield dedupe + parent-link fix, M5 /wi Design Your Door), each with its revert path referencing `docs/superpowers/backups/2026-07-09-phase2-menus/*.json`; note anonymous verification PENDING on the BlogVault unblock.

- [ ] **Step 2: Handoff update** — in the handoff doc: strike Phase 2 from "NEXT" and mark DONE with date + change-log pointer; set Phase 3 (owned door-builder visualizer) as NEXT; add the Phase 4 intel paragraph (17 existing published Clopay product pages 6403-6427 + unused menu 42 — audit/dedupe needed before building "new" pages); add Phase 2 token actuals.

- [ ] **Step 3: Commit**

```bash
git add docs/marketing/change-log.md docs/superpowers/specs/2026-07-09-twins-web-program-handoff.md
git commit -m "docs(web): phase-2 menu restructure change-log + handoff current"
```
