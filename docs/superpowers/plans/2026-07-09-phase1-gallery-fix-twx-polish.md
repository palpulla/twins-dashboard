# Phase 1 — Clopay Gallery Fix + twx Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the blank Clopay gallery iframe (WP Rocket lazy-load conflict) on all 3 sites, then add brand-color accents and real Media Library icons to the 9 live twx pages.

**Architecture:** All work is on the LIVE WordPress multisite via browser automation (claude-in-chrome MCP, logged in as admin "Tal Joseph") plus anonymous `curl` for cache-truth verification. The shared Clopay WPCode snippet (one per site) carries both the iframe markup and the `.twx-` stylesheet, so snippet edits fix/style every page on that site at once; icons need per-page Elementor HTML-widget patches.

**Tech Stack:** WPCode (CodeMirror editor), WP Rocket (main + /ky only; **/wi has none**), Elementor `$e` JS API, Rank Math untouched.

**Spec:** `docs/superpowers/specs/2026-07-09-phase1-gallery-fix-twx-polish-design.md`

---

## Environment facts (read before starting)

- Sites: `https://twinsgaragedoors.com` (main), `…/wi`, `…/ky`. wp-admin per site at `{site}/wp-admin`. Chrome MCP is already authenticated.
- Clopay snippet ("Twins x Clopay Product API (fetch+cache+shortcode)"): main id **7050**, /ky **6369**, /wi **6755**. Find via `{site}/wp-admin/admin.php?page=wpcode` → click the snippet name.
- Current deployed snippet source (all 3 sites identical apart from nothing relevant here): `docs/superpowers/backups/2026-07-08-clopay-pages/clopay-snippet-v2-deployed.php`.
- Pages with the Clopay gallery section (6): main 6090 `/garage-doors/modern-steel/`-style URLs — get exact URLs from wp-admin Pages list; /ky 6198, 6378, 6379; main 6065, 6034. Funnel pages (3, no gallery): main 7073, /wi 6756, /ky 6386, all at `{site}/design-your-door/`.
- **WPCode gotchas:** editor auto-prepends `<?php` (never add your own); Save clicks silently miss — confirm the "Snippet updated" toast; the code-type modal may reopen over the editor (dismiss by clicking the PHP card; content survives).
- **Cache gotchas:** WP Rocket does NOT purge on snippet edits — purge via the button on `{site}/wp-admin/options-general.php?page=wprocket`. Query strings do NOT bypass the cache; logged-in browsing DOES bypass it (that's why verification must be anonymous `curl`).
- **RUCSS (main only):** any inline `<style>` must contain `var(--tw-navy)` or `twx-ui` (the existing `rocket_rucss_inline_content_exclusions` filter) or Rocket strips it. All CSS in this plan goes inside the snippet's existing `<style id="twx-ui">` block, which is already safelisted.
- Region phones (must NOT change): main (833) 833-2010, /wi (608) 888-8785, /ky (859) 440-2227.
- House rules: no invented assets (icons must already exist in each site's Media Library); keep pages simple; every change gets a `docs/marketing/change-log.md` entry with a revert path.

---

### Task 1: Pre-flight + baseline snapshots (confirm the bug)

**Files:**
- Create: `docs/superpowers/backups/2026-07-09-phase1/baseline-<site>-<id>.html` — one per twx page, **all 9** (spec requires a pre-change snapshot of every page before it is touched)

- [ ] **Step 1: Verify browser session**

Navigate to `https://twinsgaragedoors.com/wp-admin/` with claude-in-chrome. Expected: dashboard loads (not a login form). If login form appears, stop and tell Daniel.

- [ ] **Step 2: Capture anonymous baselines for all 9 pages and confirm the bug**

Get the exact public URLs of the 6 collection pages from each site's wp-admin Pages list (Pages → search "Modern Steel" / "Gallery" / "Classic"); the 3 funnel URLs are `{site}/design-your-door/`. Then, for each of the 9 (naming: `baseline-main-6090.html`, `baseline-ky-6198.html`, `baseline-wi-6756.html`, …):

```bash
mkdir -p docs/superpowers/backups/2026-07-09-phase1
curl -sL "<PAGE-URL>" -o docs/superpowers/backups/2026-07-09-phase1/baseline-<site>-<id>.html
# bug confirmation on the collection pages:
grep -c 'data-lazy-src' docs/superpowers/backups/2026-07-09-phase1/baseline-main-6090.html
grep -o 'clopaydoor.com/image-gallery[^"]*' docs/superpowers/backups/2026-07-09-phase1/baseline-main-6090.html | head -2
```

Expected: main + /ky collection baselines contain an iframe with `src="about:blank"` + `data-lazy-src="https://www.clopaydoor.com/image-gallery/…"` (bug confirmed). Record whether any /wi page is affected — /wi has no WP Rocket, and /wi's only twx page is the funnel (no gallery), so /wi is likely fine; note the finding.

- [ ] **Step 3: Commit baselines**

```bash
git add docs/superpowers/backups/2026-07-09-phase1/
git commit -m "backup(web): phase-1 anonymous baselines before gallery fix"
```

---

### Task 2: Gallery iframe fix — main snippet 7050

**Files:** none (live WPCode snippet; repo copy updated in Task 4)

- [ ] **Step 1: Open the snippet editor**

Navigate to `https://twinsgaragedoors.com/wp-admin/admin.php?page=wpcode` → click "Twins x Clopay Product API (fetch+cache+shortcode)" (id 7050). If the code-type modal opens over the editor, click the PHP card to dismiss it.

- [ ] **Step 2: Apply the markup change via CodeMirror**

The iframe line currently reads (see backup line 71-72):

```php
<iframe src="<?php echo esc_url( $p['ImageGallery'] ); ?>" width="480" height="380"
    loading="lazy" title="<?php echo esc_attr( wp_strip_all_tags( $p['Title'] ) ); ?> photo gallery"></iframe>
```

The string `loading="lazy" title=` occurs exactly once in the snippet (the color-swatch `<img>` uses `title="…" loading="lazy" width=`, different order — leave it alone; image lazy-load is not broken). Run in the page console (javascript_tool):

```js
(function(){
  const cm = document.querySelector('.CodeMirror').CodeMirror;
  let v = cm.getValue();
  const hits = v.split('loading="lazy" title=').length - 1;
  if (hits !== 1) return 'ABORT: expected 1 occurrence, found ' + hits;
  v = v.replace('loading="lazy" title=', 'data-no-lazy="1" title=');
  cm.setValue(v);
  return 'OK: replaced';
})()
```

Expected: `OK: replaced`. On `ABORT`, stop — the live snippet differs from the repo backup; diff before touching anything.

- [ ] **Step 3: Save and verify the save took**

Click "Update". Confirm the "Snippet updated" toast appears (a silent miss is a known failure). If no toast, click Update again and re-check.

- [ ] **Step 4: Purge WP Rocket cache**

Navigate to `https://twinsgaragedoors.com/wp-admin/options-general.php?page=wprocket` → click "Clear and preload cache" (the dashboard button, NOT the admin-bar link).

- [ ] **Step 5: Verify as an anonymous visitor**

```bash
curl -sL "<MAIN-6090-URL>" | grep -o '<iframe[^>]*image-gallery[^>]*>' | head -1
```

Expected: the iframe tag shows a direct `src="https://www.clopaydoor.com/image-gallery/…"` and `data-no-lazy="1"`, with NO `data-lazy-src` and NO `about:blank`. Also load the page in the browser as-is and confirm the gallery visibly renders.

- [ ] **Step 6 (only if Step 5 still shows the rewrite): WP Rocket iframe exclusion**

WP Rocket settings → Media tab → LazyLoad section → add `clopaydoor.com` to the "Excluded images or iframes" field → Save → purge (Step 4) → re-verify (Step 5). If STILL rewritten, untick "Enable for iframes and videos" instead, save, purge, re-verify, and note in the change-log that iframe lazy-load is now globally off on main.

---

### Task 3: Gallery iframe fix — /ky 6369 and /wi 6755

**Files:** none (live snippets)

- [ ] **Step 1: Repeat Task 2 Steps 1-5 for /ky**

Editor at `https://twinsgaragedoors.com/ky/wp-admin/admin.php?page=wpcode` → snippet 6369. Same JS, same one-occurrence guard, same toast check. Purge /ky's own WP Rocket at `…/ky/wp-admin/options-general.php?page=wprocket`. Verify with `curl` on the /ky Classic page (6198). Apply Step 6 fallback on /ky's Rocket settings if needed.

- [ ] **Step 2: Apply the same markup change on /wi (6755)**

Editor at `https://twinsgaragedoors.com/wi/wp-admin/admin.php?page=wpcode` → snippet 6755. Same JS + toast check. /wi has no WP Rocket: no purge step, and no fallback needed. This keeps the three snippets textually identical (matters for future diffs). /wi currently has no gallery page, so verification is just that `/wi/design-your-door/` still renders normally via `curl` (HTTP 200, form present).

---

### Task 4: Sync repo copy + change-log the bug fix

**Files:**
- Modify: `docs/superpowers/backups/2026-07-08-clopay-pages/clopay-snippet-v2-deployed.php:71-72`
- Modify: `docs/marketing/change-log.md` (new entry at top)

- [ ] **Step 1: Apply the same one-line change to the repo copy**

In `clopay-snippet-v2-deployed.php`, change line 72 `loading="lazy" title=` → `data-no-lazy="1" title=` so the repo copy matches the deployed code.

- [ ] **Step 2: Add the change-log entry**

New dated section at the top of `docs/marketing/change-log.md`:

```markdown
## 2026-07-09 — Claude, Phase 1a: Clopay gallery iframe fix (Daniel-approved spec)

Spec: `docs/superpowers/specs/2026-07-09-phase1-gallery-fix-twx-polish-design.md`.

| # | Change | Detail | Revert |
|---|---|---|---|
| G1 | Gallery iframe un-lazied | WP Rocket rewrote the Clopay gallery iframe to `about:blank` (conflict with the iframe's own `loading="lazy"`). Snippets 7050/6369/6755: removed `loading="lazy"`, added `data-no-lazy="1"`. Rocket caches purged (main + /ky). Verified anon HTML shows direct clopaydoor.com src. | Re-add `loading="lazy"`, remove `data-no-lazy` (repo backup holds prior text) |
```

If Task 2 Step 6 fired, add a G2 row for the Rocket setting change with its revert.

- [ ] **Step 3: Commit**

```bash
git add docs/superpowers/backups/2026-07-08-clopay-pages/clopay-snippet-v2-deployed.php docs/marketing/change-log.md
git commit -m "fix(web): un-lazy Clopay gallery iframe (data-no-lazy) across 3 snippets + change-log"
```

---

### Task 5: Icon audit — what already exists in each Media Library

**Files:**
- Create: `docs/superpowers/backups/2026-07-09-phase1/icon-map.md`

- [ ] **Step 1: Inspect established pages for icons in use**

On each site (main, /wi, /ky), open the homepage and one established service page in the browser and read the rendered image URLs (read_page / get_page_text). Note every small pictographic asset (SVG/PNG icons, not photos) and where it's used.

- [ ] **Step 2: Sweep each site's Media Library**

`{site}/wp-admin/upload.php?mode=list&s=icon` (also try `s=svg`). Media is per-subsite — a main-site URL cannot be used on /ky or /wi pages unless the file also exists there. Record full URLs per site.

- [ ] **Step 3: Pick the card mapping and write it down**

Choose 3 icons per site for the why-cards, matched to meaning: card 1 "Official Clopay Dealer" (badge/certificate-like), card 2 "Install, Service & Repair" (tool/wrench-like), card 3 "T'Winning Every Time" (people/home/star-like). Write `icon-map.md` listing: per site, the 3 chosen icon URLs + a one-line note of where each icon already appears. **If a site's library has no suitable icons, STOP and ask Daniel — do not upload or invent assets.**

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/backups/2026-07-09-phase1/icon-map.md
git commit -m "docs(web): phase-1 icon audit map (existing Media Library assets only)"
```

---

### Task 6: Brand-accent CSS in all 3 snippets

**Files:** none live (repo copy synced in Task 9)

- [ ] **Step 1: Append accent rules to the twx stylesheet in snippet 7050 (main)**

Open the snippet editor (as Task 2 Step 1) and run:

```js
(function(){
  const cm = document.querySelector('.CodeMirror').CodeMirror;
  let v = cm.getValue();
  if (v.includes('/* P1 accents */')) return 'ABORT: already applied';
  const css = '/* P1 accents */\n' +
    '.twx-card{border-top:4px solid var(--tw-yellow)}\n' +
    '.twx-step{border-top:4px solid var(--tw-yellow)}\n' +
    '.twx-card .twx-ico{background:var(--tw-soft)}\n' +
    '.twx-card .twx-ico img{width:24px;height:24px;object-fit:contain;display:block}\n';
  const hits = v.split('/* mobile */').length - 1;
  if (hits !== 1) return 'ABORT: mobile marker not unique: ' + hits;
  v = v.replace('/* mobile */', css + '/* mobile */');
  cm.setValue(v);
  return 'OK: css appended';
})()
```

Expected: `OK: css appended`. These rules ride inside the existing `<style id="twx-ui">` block and reference `var(--tw-yellow)`/`var(--tw-soft)`, so the RUCSS safelist already covers them.

- [ ] **Step 2: Save (toast check), then repeat Step 1 on /ky 6369 and /wi 6755**

Same JS on each site's snippet editor. Toast check every save.

- [ ] **Step 3: Purge and spot-check**

Purge WP Rocket on main and /ky (settings-dashboard button). Then `curl` one collection page and confirm the new rules are present in the inline stylesheet:

```bash
curl -sL "<MAIN-6090-URL>" | grep -c 'P1 accents'
```

Expected: `1`.

---

### Task 7: Icons + heading underlines on the 6 collection pages

**Files:** none (live Elementor documents; revisions are the revert path)

Pages: main 6090, 6065, 6034; /ky 6378, 6379, 6198. Use each site's icon URLs from `icon-map.md`.

- [ ] **Step 1: Open the Elementor editor for the page**

`{site}/wp-admin/post.php?post=<ID>&action=elementor`. Wait for the editor to finish loading (preview iframe rendered).

- [ ] **Step 2: Patch the why-cards HTML widget**

The cards + their H2 live in one HTML widget (from the builder template): `<h2 class="twx-h2">Installed by the local pros</h2>` followed by three `<div class="twx-card"><h3>…`. Run in the editor console:

```js
(function(){
  const icons = ['<ICON-1-URL>','<ICON-2-URL>','<ICON-3-URL>']; // from icon-map.md, THIS site's URLs
  function collect(c, out){ (c.children ? Array.from(c.children) : []).forEach(ch => { out.push(ch); collect(ch, out); }); return out; }
  const all = collect(elementor.getPreviewContainer(), []);
  const w = all.find(c => c.model && c.model.get('widgetType') === 'html' && String(c.settings.get('html')||'').includes('twx-cards'));
  if (!w) return 'ABORT: cards widget not found';
  let html = w.settings.get('html');
  if (html.includes('twx-ico')) return 'ABORT: icons already present';
  let i = 0;
  html = html.replace(/<div class="twx-card">(?=<h3>)/g,
    m => m + '<span class="twx-ico"><img src="' + icons[i++] + '" alt="" width="24" height="24" loading="lazy"></span>');
  if (i !== 3) return 'ABORT: patched ' + i + ' cards, expected 3';
  html = html.replace('<h2 class="twx-h2">Installed', '<h2 class="twx-h2 twx-underline">Installed');
  $e.run('document/elements/settings', { container: w, settings: { html: html } });
  return 'OK: 3 icons + underline';
})()
```

Expected: `OK: 3 icons + underline`. On any ABORT, skip the page and record why (do not force).

- [ ] **Step 3: Underline the FAQ heading (same technique, FAQ widget)**

```js
(function(){
  function collect(c, out){ (c.children ? Array.from(c.children) : []).forEach(ch => { out.push(ch); collect(ch, out); }); return out; }
  const all = collect(elementor.getPreviewContainer(), []);
  const w = all.find(c => c.model && c.model.get('widgetType') === 'html' && String(c.settings.get('html')||'').includes('twx-faq'));
  if (!w) return 'ABORT: faq widget not found';
  let html = w.settings.get('html');
  if (html.includes('twx-h2 twx-underline')) return 'ABORT: already underlined';
  html = html.replace('<h2 class="twx-h2">Frequently', '<h2 class="twx-h2 twx-underline">Frequently');
  $e.run('document/elements/settings', { container: w, settings: { html: html } });
  return 'OK: faq underlined';
})()
```

- [ ] **Step 4: Save the document**

```js
$e.run('document/save/update')
```

Confirm the editor shows the saved state (no unsaved-changes indicator).

- [ ] **Step 5: Repeat Steps 1-4 for the remaining 5 collection pages**

Use the matching site's icon URLs for the /ky pages (per-subsite media).

---

### Task 8: Heading underlines on the 3 funnel pages

**Files:** none (live Elementor documents)

Pages: main 7073, /wi 6756, /ky 6386. The steps section keeps its numbered navy/yellow circles — no icons added there (numbers + icons together is busy; house rule is simple).

- [ ] **Step 1: For each funnel page, underline its section H2s**

Open the Elementor editor and run the collect-and-patch pattern from Task 7 against each HTML widget whose html contains `class="twx-h2"` but not `twx-underline`, replacing `class="twx-h2"` with `class="twx-h2 twx-underline"` (all occurrences in that widget). Skip any widget already patched. Then `$e.run('document/save/update')`.

```js
(function(){
  function collect(c, out){ (c.children ? Array.from(c.children) : []).forEach(ch => { out.push(ch); collect(ch, out); }); return out; }
  const all = collect(elementor.getPreviewContainer(), []);
  let n = 0;
  all.forEach(c => {
    if (!c.model || c.model.get('widgetType') !== 'html') return;
    let html = String(c.settings.get('html') || '');
    if (!html.includes('class="twx-h2"')) return;
    html = html.split('class="twx-h2"').join('class="twx-h2 twx-underline"');
    $e.run('document/elements/settings', { container: c, settings: { html: html } });
    n++;
  });
  return 'OK: patched ' + n + ' widgets';
})()
```

Expected: `OK: patched 1` or `2` widgets per page (hero H1 is untouched — it is an `<h1>`).

---

### Task 9: Full verification sweep + sync repo snippet copy

**Files:**
- Modify: `docs/superpowers/backups/2026-07-08-clopay-pages/clopay-snippet-v2-deployed.php` (append the P1 accents CSS, matching live)

- [ ] **Step 1: Purge both Rocket caches once more** (main + /ky settings-dashboard buttons; /wi has none).

- [ ] **Step 2: Anonymous HTML checks on all 9 pages**

For each page URL:

```bash
curl -sL "<URL>" > /tmp/p.html
grep -c '<h1' /tmp/p.html                     # expected: 1
grep -o 'twx-ico' /tmp/p.html | wc -l         # collection pages: ≥3; funnel: 0
grep -o '<iframe[^>]*image-gallery[^>]*>' /tmp/p.html   # collection pages: direct clopaydoor src, no about:blank
grep -o '833-2010\|888-8785\|440-2227' /tmp/p.html | sort -u   # exactly the region's own number
```

(Use the scratchpad dir for the temp file, not /tmp, when executing.)

- [ ] **Step 3: Visual check, desktop + mobile**

In the browser, load each of the 9 pages; screenshot desktop, then resize the window to 390×844 and screenshot mobile. Confirm: gallery renders (6 pages), icons show with sane contrast, yellow card/step borders look intentional, nothing overflows on mobile. If icon contrast fails against `--tw-soft`, change that one CSS rule to `background:var(--tw-navy)` in all 3 snippets and re-verify.

- [ ] **Step 4: Sync the repo snippet copy and commit**

Append the same `/* P1 accents */` CSS block before `/* mobile */` in `clopay-snippet-v2-deployed.php`.

```bash
git add docs/superpowers/backups/2026-07-08-clopay-pages/clopay-snippet-v2-deployed.php
git commit -m "feat(web): P1 accent CSS synced to repo snippet copy"
```

---

### Task 10: Change-log the polish + wrap up

**Files:**
- Modify: `docs/marketing/change-log.md`
- Modify: `docs/superpowers/specs/2026-07-09-twins-web-program-handoff.md` (mark Phase 1 done)

- [ ] **Step 1: Add the polish entry to the change-log 2026-07-09 section**

```markdown
| P1 | Brand accents on twx pages | Yellow top-borders on cards/steps + `.twx-ico` sizing added to snippets 7050/6369/6755; yellow heading underlines + 3 Media Library icons per why-cards section patched into 6 collection pages; underlines on 3 funnel pages. Icons are pre-existing library assets only (see `docs/superpowers/backups/2026-07-09-phase1/icon-map.md`). | CSS: remove the `/* P1 accents */` block per snippet. Pages: restore Elementor revision per page |
```

- [ ] **Step 2: Mark Phase 1 complete in the handoff doc**

Add a line under "Current live state": `Phase 1 (gallery fix + polish) DONE 2026-07-09 — see change-log entries G1/P1.`

- [ ] **Step 3: Commit and report**

```bash
git add docs/marketing/change-log.md docs/superpowers/specs/2026-07-09-twins-web-program-handoff.md
git commit -m "docs(web): phase-1 change-log entries + handoff status"
```

Report to Daniel: what changed, screenshots, and that Phase 2 (menus) is next.
