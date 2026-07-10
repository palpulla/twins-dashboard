# Phase 5 Workstream A — /wi Build-out Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Overhaul /wi Milwaukee page (6460) into a full metro hub and /wi garage-door-installation (1616) into a product-forward install hub, both on twx v2 with AI-SEO structure, real-review band, cost FAQs from real job data, plus /wi phone + schema cleanup.

**Architecture:** Live WordPress multisite edits via the logged-in Chrome tab (claude-in-chrome MCP), using the proven Phase-4 Elementor pipeline (builder script + QA gate). No app code; git is for docs/backups only. Spec: `docs/superpowers/specs/2026-07-10-phase5-wi-buildout-design.md`.

**Tech Stack:** WP multisite (SiteGround), Elementor, WPCode, Rank Math (updateMeta REST), twx v2 kit (snippets 7050 main / 6755 wi), Business Reviews Bundle plugin, builder `docs/superpowers/backups/2026-07-09-phase4-catalog/twx2-page-builder.js`, `qa-gate.py` same dir.

**Binding environment rules (from handoff, read before ANY browser step):** logged-in `fetch(path,{credentials:'same-origin'})` only (BlogVault blocks anonymous from this IP); throttle ≥6s between site requests; javascript_tool output ≤1,000 chars (stash in `window.*` vars); no key=value-shaped output (DLP); WPCode saves need toast verify + sha256 before/after; foreground fetch loops only; Elementor saves wipe Astra meta so `site-post-title` goes LAST; JSON-LD widget re-add is a separate deterministic pass. A PHP error in a Run-Everywhere snippet can white-screen the site: fetch homepage seconds after save, keep backup ready to setValue back.

**Approved published cost ranges (computed 2026-07-10 from jwrpj `jobs`, status=completed, completed_at ≥ 2025-07-01, revenue > 0, structured `job_type` — Daniel approves via plan review):**

| Published claim | Source |
|---|---|
| Garage door repair: **$400 to $1,050** | Repair, n=378, p25 401 / p75 1033 |
| Opener installed: **$900 to $1,450** | Opener Install, n=55, p25 921 / p75 1416 |
| New door installed: **$3,000 to $4,100** | Door Install, n=48, p25 3075 / p75 4056 |
| Door + opener: **$4,400 to $7,250** | Door + Opener Install, n=35, p25 4404 / p75 7237 |

Framing on-page: "based on our completed jobs over the last 12 months". Business-wide (no city split; Milwaukee sample too small). Reproduce with:

```sql
SELECT job_type, count(*) n,
  percentile_cont(0.25) WITHIN GROUP (ORDER BY revenue_amount) p25,
  percentile_cont(0.75) WITHIN GROUP (ORDER BY revenue_amount) p75
FROM jobs WHERE status='completed' AND completed_at >= '2025-07-01' AND revenue_amount > 0
GROUP BY job_type ORDER BY n DESC;
```

---

### Task 1: Backups + reconnaissance

**Files:**
- Create: `docs/superpowers/backups/2026-07-10-phase5-wi/page-6460-elementor-before.json`
- Create: `docs/superpowers/backups/2026-07-10-phase5-wi/page-1616-elementor-before.json`
- Create: `docs/superpowers/backups/2026-07-10-phase5-wi/recon-notes.md`

- [ ] **Step 1: Verify tab + login.** `tabs_context_mcp`; navigate to `https://twinsgaragedoors.com/wi/wp-admin/` and confirm the dashboard renders (logged in as Tal Joseph). If login page appears, STOP and tell Daniel.

- [ ] **Step 2: Export both pages' Elementor data.** In the tab:

```js
async function grab(id){
  const r=await fetch(`/wi/wp-json/wp/v2/pages/${id}?context=edit`,{credentials:'same-origin'});
  const j=await r.json();
  const meta=await fetch(`/wi/wp-admin/post.php?post=${id}&action=edit`,{credentials:'same-origin'}).then(x=>x.text());
  const m=meta.match(/name="_elementor_data"[^>]*value="([\s\S]*?)"\s*\/?>/);
  return {title:j.title.raw, status:j.status, elementorDataFound:!!m, len:m?m[1].length:0, raw:m?m[1]:null};
}
window.__b6460=await grab(6460); await new Promise(r=>setTimeout(r,6000));
window.__b1616=await grab(1616);
[window.__b6460.len, window.__b1616.len].join(' / ');
```

If `_elementor_data` isn't in the edit screen HTML (common), fall back to reading it via a one-off logged-in REST probe of the meta through the Elementor editor: open `post.php?post={id}&action=elementor`, wait for `elementor.documents.getCurrent()`, then `window.__bX = JSON.stringify(elementor.documents.getCurrent().container.model.toJSON())`. Either representation is an acceptable restore artifact.

- [ ] **Step 3: Transfer backups to disk.** Read `window.__b6460.raw` (or editor JSON) in ≤1,000-char slices; write to the two backup files with the Write/Edit tools. Verify byte length matches `len`.

- [ ] **Step 4: Recon the reviews shortcode.** Open `/wi/wp-admin/post.php?post=<reviews page id>&action=edit` (find id: REST `pages?slug=reviews`, id noted in recon-notes). Extract the exact Business Reviews Bundle shortcode used (family `[brb ...]` / `[grw ...]` — copy verbatim including attributes). Record in `recon-notes.md`.

- [ ] **Step 5: Recon the phone-swap script + 620 N Carroll sources.** On `/wi` homepage in the tab:

```js
// Which WPCode snippet carries the swap? List /wi snippets:
// navigate to /wi/wp-admin/admin.php?page=wpcode then read titles+ids from DOM.
// Separately, find where 620 N Carroll lives: it is JSON-LD, so check Rank Math
// Local SEO settings (admin.php?page=rank-math-options-titles) and page-level
// rank_math_schema entries on the homepage + reviews page.
```

Record in `recon-notes.md`: (a) snippet id carrying the 925-2038 swap, (b) every admin location emitting `620 N Carroll` or a second LocalBusiness address, (c) where the footer/header phone text 888-8785 instances live (Elementor Theme Builder doc ids on /wi).

- [ ] **Step 6: Commit backups.**

```bash
git add docs/superpowers/backups/2026-07-10-phase5-wi/
git commit -m "backup(phase5): /wi pages 6460+1616 pre-overhaul + recon notes"
```

### Task 2: Port the Clopay collection grid to /wi

**Files:**
- Modify: WPCode snippet **6755** on /wi (twx-ui + v2 CSS) — append grid renderer + register `[clopay_collection_grid]` for /wi
- Create: `docs/superpowers/backups/2026-07-10-phase5-wi/snippet-6755-before.php` (+ `-after.php`)

- [ ] **Step 1: Read main snippet 7050's grid code.** Open `admin.php?page=wpcode-snippet-manager&snippet_id=7050` on MAIN site; extract the `clopay_collection_grid` shortcode handler + its card data (23 collections) via CodeMirror `getValue()` in ≤1,000-char slices. Stash to disk.

- [ ] **Step 2: Backup /wi snippet 6755.** Same CodeMirror read on `/wi/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=6755`; compute sha256 in-page; save before-file.

- [ ] **Step 3: Append the grid handler to 6755, with two /wi-specific changes:** (a) card primary link → `/wi/door-builder/?product={id}`; (b) secondary link per card "View full collection" → main `https://twinsgaragedoors.com/clopay-{slug}/`. No `<?php` of your own (WPCode auto-prepends). setValue, sha256 before AND after, Save, verify "Snippet updated." toast.

- [ ] **Step 4: White-screen guard.** Within seconds of save: `fetch('/wi/',{credentials:'same-origin'})` → expect HTTP 200 and HTML containing `</html>`. If 500/fatal: immediately setValue the before-file back and save.

- [ ] **Step 5: Render test.** Create a private draft page via REST with content `[clopay_collection_grid]`, fetch it logged-in, assert 23 cards render and hrefs point at `/wi/door-builder/?product=`. Delete the draft. Save after-file; commit both files.

```bash
git add docs/superpowers/backups/2026-07-10-phase5-wi/snippet-6755-*.php
git commit -m "feat(phase5): clopay grid ported to /wi snippet 6755 (builder deep-links)"
```

### Task 3: Build the Milwaukee hub (page 6460)

**Files:**
- Modify: live page 6460 via Elementor pipeline
- Reference: builder script + content-pack format in `docs/superpowers/backups/2026-07-09-phase4-catalog/` (READ `twx2-page-builder.js` HEADER FIRST)

Pipeline order (hard-won, per page): Rank Math `updateMeta` (nonce via `fetch('/wp-admin/admin-ajax.php?action=rest-nonce')`, re-fetch after every editor reload) → open Elementor editor, wait `elementor.documents.getCurrent().container.children.length > 0` → run builder with content pack → save (poll 15-20s) → reload, JSON-LD widget re-add via `$e.run('document/elements/create', …, {at:1})`, save, reload, verify → Astra `site-post-title` meta LAST → publish → QA.

- [ ] **Step 1: Rank Math meta.**
  - Title: `Garage Door Repair & Installation in Milwaukee, WI | Twins Garage Doors`
  - Description: `Twins Garage Doors repairs and installs garage doors in Milwaukee and Wauwatosa. Same-day repair, Clopay doors, opener installs. Call (414) 800-9271.`

- [ ] **Step 2: Build sections from this content pack** (twx v2 components; twins sizing is calibrated, do not adjust):

1. **Hero (textured):** H1 `Garage Door Repair & Installation in Milwaukee, WI`. Sub: `Local technicians serving Milwaukee, Wauwatosa, and the surrounding metro. Same-day repair available.` CTAs: `Call (414) 800-9271` (tel:+14148009271) + `Book Online` (HCP booking URL from handoff §current-live-state).
2. **Answer-first intro (plain band):** `Twins Garage Doors repairs and installs garage doors across Milwaukee, Wauwatosa, and nearby communities. Our technicians handle broken springs, doors off track, opener replacements, and full new door installations, with same-day appointments available. Call (414) 800-9271 or book online for an exact quote.` Then freshness line: `Last updated: July 10, 2026`.
3. **Trust ribbon** (standard 3 checks from kit).
4. **Services cards ×3** with query-shaped H2 `What garage door services do you offer in Milwaukee?`: Repair (springs, cables, off-track, panels) · Installation (new Clopay doors, free on-site quote) · Openers (install + replacement, major brands).
5. **Collection grid band**, H2 `Which garage doors can I choose from?` + `[clopay_collection_grid]`.
6. **Steps 01-03** (kit standard: book, on-site quote, same-day work).
7. **Service-area band**, H2 `What areas do you serve around Milwaukee?`: `Milwaukee, Wauwatosa, and the surrounding metro area.` + Wauwatosa address line.
8. **Reviews band**, H2 `What do customers say about Twins Garage Doors?` + the exact Business Reviews Bundle shortcode from Task 1 recon.
9. **FAQ band** (visible accordion/list, exact copy):
   - `How much does garage door repair cost in Milwaukee?` → `Most garage door repairs from Twins Garage Doors run between $400 and $1,050, based on our completed jobs over the last 12 months. The exact price depends on the parts your door needs. Call (414) 800-9271 for an exact quote.`
   - `How much does a new garage door cost installed?` → `Most new garage door installations run between $3,000 and $4,100, based on our completed jobs over the last 12 months. A new door with a new opener typically runs $4,400 to $7,250. Financing is available through GoodLeap.`
   - `How much does a garage door opener cost installed?` → `Most opener installations run between $900 and $1,450, based on our completed jobs over the last 12 months, including the opener and labor.`
   - `Do you offer same-day garage door repair in Milwaukee?` → `Yes. Twins Garage Doors offers same-day garage door repair in the Milwaukee area when the schedule allows. Call in the morning for the best chance of a same-day appointment.`
   - `What brands of garage doors do you install?` → `Twins Garage Doors is an authorized Clopay dealer and installs all 23 Clopay residential collections. You can design your own door and get a quote with our online door builder.`
10. **Closer:** Book + Call pair (414 number).
11. **Footer content within page scope:** NAP line `Twins Garage Doors, 11220 W Burleigh St Ste 100, Wauwatosa, WI 53222 · (414) 800-9271`.

Copy rules: no em-dashes anywhere in this content, full dollar amounts, real numbers only.

- [ ] **Step 3: JSON-LD pass (deterministic re-add).** One HTML widget at `{at:1}` containing LocalBusiness + FAQPage:

```json
{"@context":"https://schema.org","@graph":[
 {"@type":"LocalBusiness","name":"Twins Garage Doors","telephone":"+14148009271",
  "address":{"@type":"PostalAddress","streetAddress":"11220 W Burleigh St Ste 100","addressLocality":"Wauwatosa","addressRegion":"WI","postalCode":"53222"},
  "areaServed":["Milwaukee WI","Wauwatosa WI"],"url":"https://twinsgaragedoors.com/wi/garage-door-repair-in-milwaukee-wi/"},
 {"@type":"FAQPage","mainEntity":[/* one Question/acceptedAnswer per FAQ above, answer text verbatim */]}]}
```

- [ ] **Step 4: Astra `site-post-title` meta LAST, then publish.**
- [ ] **Step 5: QA.** `qa-gate.py` logged-in mode on the page URL; then in-tab checks: exactly ONE phone (414 800-9271) in text+tel+schema; ZERO `925-2038`, `888-8785`, `620 N Carroll`; FAQPage JSON parses; grid renders 23 cards; reviews band shows plugin cards; 390px iframe mobile fit; twins visible.

### Task 4: Build the Madison install hub (page 1616)

Same pipeline as Task 3. Slug stays `garage-door-installation`.

- [ ] **Step 1: Rank Math meta.**
  - Title: `New Garage Doors & Installation in Madison, WI | Twins Garage Doors`
  - Description: `Design your new Clopay garage door and get it installed by Twins Garage Doors in Madison, WI. Real pricing, GoodLeap financing. Call (608) 420-2377.`

- [ ] **Step 2: Content pack:**

1. **Hero:** H1 `New Garage Doors & Installation in Madison, WI`. Sub: `Choose from 23 Clopay collections, design your door online, and have it installed by our local Madison team.` CTAs: `Call (608) 420-2377` (tel:+16084202377) + `Design Your Door` (→ `/wi/door-builder/`).
2. **Answer-first intro:** `Twins Garage Doors installs new Clopay garage doors across Madison and the surrounding area. Most new door installations run between $3,000 and $4,100, and you can design your exact door online with our door builder to get a quote. Call (608) 420-2377 to talk through options.` Freshness line: `Last updated: July 10, 2026`.
3. **Trust ribbon.**
4. **Collection grid centerpiece**, H2 `Which garage door styles can I choose from?` + `[clopay_collection_grid]`.
5. **Door-builder CTA band:** `Design your exact door, see it on your home, and get a quote.` → `/wi/door-builder/`.
6. **Financing band** (confirmed offers only): GoodLeap financing + $0 service call.
7. **Steps 01-03.**
8. **Reviews band** (same shortcode).
9. **FAQ band** (visible, exact copy):
   - `How much does a new garage door cost in Madison?` → `Most new garage door installations from Twins Garage Doors run between $3,000 and $4,100, based on our completed jobs over the last 12 months. A new door with a new opener typically runs $4,400 to $7,250. Design your door online for an exact quote.`
   - `Does the price include installation?` → `Yes. The ranges above are complete job totals from our real completed installations, including the door and installation labor.`
   - `Can I finance a new garage door?` → `Yes. Twins Garage Doors offers financing through GoodLeap, so you can spread the cost of a new door over monthly payments.`
   - `How long does garage door installation take?` → `Most single-door installations are completed in one visit. We confirm your timeline when we schedule your installation.`
   - `What brands do you install?` → `We are an authorized Clopay dealer and install all 23 Clopay residential collections, from classic raised-panel steel to modern full-view glass and carriage house designs.`
10. **Closer** (608 420-2377 + builder link).
11. **NAP line:** `Twins Garage Doors, 2921 Landmark Pl, Ste 206, Madison, WI 53713 · (608) 420-2377`.

- [ ] **Step 3: JSON-LD:** LocalBusiness (Madison NAP, `+16084202377`, areaServed Madison WI) + FAQPage, same deterministic pass.
- [ ] **Step 4: Astra meta LAST, publish.**
- [ ] **Step 5: QA** — same checklist; phone must be 420-2377 only.

### Task 5: /wi phone + schema cleanup

**Files:**
- Modify: the swap-carrying snippet (id from Task 1 recon) — remove the 888-8785→925-2038 blanket swap
- Modify: /wi Theme Builder header/footer docs' phone text (ids from recon) → (608) 420-2377
- Modify: Rank Math Local SEO settings on /wi → single address 2921 Landmark Pl Ste 206, phone 420-2377
- Create: `docs/superpowers/backups/2026-07-10-phase5-wi/phone-cleanup-before-after.md`

- [ ] **Step 1: Snippet edit.** Backup (getValue+sha256) → delete ONLY the swap block (keep unrelated code, e.g. the 833→608 header rewrite if it lives in the same snippet: update its target to 420-2377 instead of deleting) → save w/ toast verify → homepage white-screen guard fetch.
- [ ] **Step 2: Theme Builder phone text.** For each /wi header/footer doc from recon: open in Elementor, replace visible 888-8785 → `(608) 420-2377` and tel: hrefs → `tel:+16084202377`. Save each, verify.
- [ ] **Step 3: Rank Math Local SEO.** Set the single org address + phone; remove/overwrite whichever setting emitted `620 N Carroll St`. If the second address comes from a page-level `rank_math_schema` post, edit that post's schema instead. Record before-values.
- [ ] **Step 4: Verify sweep.** Logged-in fetch of: /wi home, /wi/reviews/, both hub pages, one Madison suburb service page. Assert across all: zero `925-2038`, zero `620 N Carroll`; `888-8785` appears at most in non-customer-facing residue (target: zero; log any stragglers + their template source in the change-log for follow-up). Throttle 6s+.
- [ ] **Step 5: Caches.** WP Rocket does NOT run on /wi (handoff): only clear a8c Edge Cache (Settings → Edge Cache, expect toast). Then re-run the sweep once.
- [ ] **Step 6: Commit** backup/notes file.

```bash
git add docs/superpowers/backups/2026-07-10-phase5-wi/phone-cleanup-before-after.md
git commit -m "feat(phase5): /wi phone standardized to 420-2377, swap script removed, NAP fixed"
```

### Task 6: Final verification + proof

- [ ] **Step 1: Full QA gate** on both hubs (logged-in mode). All checks green.
- [ ] **Step 2: Schema validation.** Parse each hub's JSON-LD in-page: exactly one LocalBusiness address+phone, FAQPage has 5 Question entities, no duplicate AggregateRating (if Business Reviews Bundle emits its own schema in the band, remove `AggregateRating` from OUR JSON-LD, keep the plugin's).
- [ ] **Step 3: Mobile.** 390px same-origin iframe on both hubs: sticky bar correct number per page, twins visible, no horizontal scroll.
- [ ] **Step 4: Proof screenshots in MAIN session** (desktop + 390px iframe, both hubs) so Daniel sees them inline.
- [ ] **Step 5: Forward test (Daniel).** Ask Daniel to dial (608) 420-2377 and confirm it rings the business line. Workstream is NOT done until confirmed.

### Task 7: Change-log + handoff update

**Files:**
- Modify: the program change-log (K/D/M/G-row format per prior phases, in the phase-4/5 docs dir)
- Modify: `docs/superpowers/specs/2026-07-09-twins-web-program-handoff.md` (Phase 5 progress + token actuals + remaining workstreams B/C/D)

- [ ] **Step 1:** Add change-log rows (pages 6460/1616 overhauls w/ backup paths as revert, snippet 6755 grid, swap-script removal, Rank Math NAP fix, Theme Builder phone edits) each with revert path.
- [ ] **Step 2:** Update handoff: workstream A DONE, B/C/D pending, open items (license number, BlogVault whitelist, forward-test result).
- [ ] **Step 3: Commit docs** (own paths only, verify branch first):

```bash
git branch --show-current
git add docs/superpowers/
git commit -m "docs(phase5): workstream A change-log + handoff update"
```

---

## Self-review notes

- Spec coverage: hubs (T3/T4), grid port (T2), phone/schema cleanup (T5), cost FAQs (pre-computed, gated on Daniel's plan approval), reviews band (T1 recon + T3/T4), freshness lines, verification incl. forward test (T6), change-log/revert (T1/T7). License line intentionally absent.
- Live-edit subagents do NOT commit; orchestrator commits (shared checkout).
- All copy above is final and em-dash-free; dollar figures full amounts.
