# Phase 3 Door-Builder Visualizer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Tasks share one logged-in Chrome session — always sequential, never parallel.

**Goal:** Ship the Twins-owned door configurator at `/door-builder/` on main + /wi, extend the lead endpoint to carry door configurations, and swap the /design-your-door post-submit redirect from EZDoor to the builder.

**Architecture:** One new WPCode PHP snippet per site renders shortcode `[twins_door_builder]` (vanilla JS + scoped CSS, client-side fetch straight from the Clopay API with sessionStorage cache and a fallback form). Existing main-site endpoint snippet 7072 gains optional config fields. Pages built with the proven Elementor builder-JS technique. Everything reversible; change-log entries throughout.

**Tech Stack:** WPCode snippets (PHP/JS/CSS, CodeMirror editor automation), Clopay Product API v2 (client-side, CORS `*`), WP REST (Rank Math meta, Astra page-title meta), claude-in-chrome MCP.

**Spec:** `docs/superpowers/specs/2026-07-09-phase3-door-builder-visualizer-design.md`

**Standing rules for every executor:** reuse Chrome tab 977554251 (logged-in admin); throttle site fetches ≥6s; javascript_tool output truncates ~1,000 chars and a DLP filter blocks key=value-formatted output (plain prose only); long in-page loops: stash progress in window vars and poll; WPCode editor auto-prepends `<?php` (never include your own); verify "Snippet updated." toast on save; main's WP Rocket purge button shows NO toast (normal), the Edge Cache clear DOES ("Edge Cache has been cleared."); anonymous requests from this machine are BlogVault-blocked — verify logged in.

---

### Task 1: Author the builder snippet + endpoint patch as repo files, test the app locally

**Files:**
- Create: `docs/superpowers/backups/2026-07-09-phase3-door-builder/twins-door-builder-snippet.php` (the full new snippet: shortcode + CSS + JS)
- Create: `docs/superpowers/backups/2026-07-09-phase3-door-builder/door-builder-endpoint-v2.php` (full replacement body for snippet 7072)
- Create: `docs/superpowers/backups/2026-07-09-phase3-door-builder/local-harness.html` (dev harness)
- Reference: `docs/superpowers/backups/2026-07-08-clopay-pages/door-builder-endpoint-deployed.php` (current 7072 body — the baseline to extend)
- Reference: `docs/superpowers/backups/2026-07-08-clopay-pages/clopay-snippet-v2-deployed.php` (house CSS/token conventions)

- [ ] **Step 1: Read the two reference files** to absorb the house conventions (twx tokens, how the endpoint builds its email, honeypot field name, region tagging).

- [ ] **Step 2: Write `twins-door-builder-snippet.php`** implementing the spec exactly. Contract:

PHP (keep minimal):
```php
// NO opening <?php tag in the deployed WPCode body (editor adds it); the repo file MAY have one for lint tooling — strip when deploying.
add_shortcode('twins_door_builder', function () {
  $region = (strpos(home_url(), '/wi') !== false) ? 'wi' : 'main'; // path-based, no blog-id assumptions
  ob_start(); ?>
  <div id="twxdb" class="twxdb" data-region="<?php echo esc_attr($region); ?>"
       data-endpoint="https://twinsgaragedoors.com/wp-json/twins/v1/door-builder"></div>
  <style id="twxdb-css">/* all CSS here; every color via var(--tw-navy)/var(--tw-yellow)/#F2F5F7; .twxdb- prefix on every class */</style>
  <script>/* entire app, IIFE, no globals except none */</script>
  <?php return ob_get_clean();
});
```

JS app contract (vanilla, IIFE, ~state machine):
- `state = { step, products, product, design, color, window, glass, skipped:{} }`; steps: `collection, design, color, windows, glass, summary` (+ `fallback`, `thanks`, `error` states).
- Data: `fetchJSON(url, timeoutMs=10000)` with AbortController; list URL `https://www.clopaydoor.com/api/v2/GetProductsList/GetProducts?productType=Residential`; detail URL `...GetProductDetails/GetProductData?productId=` + id; sessionStorage keys `twxdb:list`, `twxdb:p:{id}`.
- List fetch failure → render `fallback`: message "Our door catalog is taking a break — leave your details and we'll design it together." + the same contact form as the summary step (submits with whatever config fields exist, i.e. none).
- Sanitizer: `san(html)` keeps only `<sup>`,`<br>` (regex: strip all tags except those two, strip attributes from them); `plain(html)` strips ALL tags and collapses whitespace — used for lead payload and image alt.
- Step rendering rules from the spec: skip design step if `ProductDesigns` empty; windows step prepends built-in option "No windows (solid)"; glass step only if a real window chosen AND `SpecialityGlassOptions.length`; disclaimers (`ColorDisclaimer`, `TopSectionDisclaimer`) render small under their step when non-empty. Hero image: ShowcaseImage until a design is picked, then the design's ProductImage, with `data-no-lazy="1"` and `loading="eager"` on the hero only; all card/thumb imgs `loading="lazy"`.
- Persistent UI: progress dots (6 max, reflect skipped), Back button (except step 1), "Skip to quote →" link every step → jumps to summary with whatever is chosen.
- Color caption under swatch selection: "Colors shown as manufacturer swatches — you'll see your exact door at your free on-site consult."
- Summary + form: fields name (required), phone (required), email (required), zip (required), notes (textarea optional), honeypot input IDENTICAL in name + hidden technique to the existing funnel form (read it from the reference endpoint file). Submit body: JSON `{name, phone, email, zip, notes, region, collection, design, color, windows, glass, hp-field...}` where config values are `plain()`ed titles; omit keys for unchosen/skipped. POST to `data-endpoint`; 2xx → `thanks` state ("Thanks — your design is on its way to our team. We'll call you shortly."); non-2xx → inline error + region phone (main "(833) 833-2010", wi "(608) 888-8785") as tel: link.
- No external links anywhere in the app.

- [ ] **Step 3: Write `door-builder-endpoint-v2.php`** — the FULL new body for snippet 7072: start from the deployed baseline file and add, in the handler, after existing field reads:
```php
$config_keys = array('collection','design','color','windows','glass','notes');
$config_lines = array();
foreach ($config_keys as $k) {
  $v = isset($params[$k]) ? sanitize_text_field(substr((string)$params[$k], 0, 200)) : '';
  if ($v !== '') $config_lines[] = ucfirst($k) . ': ' . $v;
}
if ($config_lines) $body .= "\n\nDoor configuration\n" . implode("\n", $config_lines);
```
Adapt variable names ($params/$body) to whatever the baseline actually uses — read it first; change NOTHING else (recipient, honeypot, region, response shape all stay).

- [ ] **Step 4: Write `local-harness.html`** — a minimal page that inlines the shortcode's HTML shell + `<style>` + `<script>` extracted from the snippet file, defines the CSS custom properties (`--tw-navy:#022751; --tw-yellow:#FBBD04;`), sets data-region "main" and data-endpoint to `https://httpbin.org/status/200` (never the real endpoint from the harness).

- [ ] **Step 5: Test locally.** `python3 -m http.server 8123 --directory docs/superpowers/backups/2026-07-09-phase3-door-builder/` (Bash, background), open `http://localhost:8123/local-harness.html` in the Chrome tab. Walk: full path incl. glass (Modern Steel 170 has 6 glass options), a no-windows path, a skip-to-quote-from-step-1 path, and the fallback path (temporarily point the list URL at an invalid host via devtools override or a harness query flag — implement `?twxdbfail=1` in the app to force list-fetch failure for testing). Verify submit posts the expected JSON shape (log it in the harness page, read via javascript_tool). Screenshot desktop + narrow (device-emulate via iframe if window resize is inert). Kill the server after.

- [ ] **Step 6: Commit**
```bash
git add docs/superpowers/backups/2026-07-09-phase3-door-builder/
git commit -m "feat(web): door-builder snippet + endpoint v2 + local harness (phase 3, pre-deploy)"
```

---

### Task 2: Deploy endpoint v2 + builder snippet to the MAIN site

- [ ] **Step 1: Back up the live 7072 body.** Open `https://twinsgaragedoors.com/wp-admin/admin.php?page=wpcode&view=edit&id=7072`, read CodeMirror `getValue()` out in ≤1,000-char chunks, save as `docs/superpowers/backups/2026-07-09-phase3-door-builder/endpoint-7072-before.php`. Diff against the 2026-07-08 deployed copy — investigate any drift before proceeding.
- [ ] **Step 2: Deploy endpoint v2.** CodeMirror `setValue()` with the v2 body (WITHOUT an opening `<?php` tag), verify with `getValue()` length + spot-string, click Update, confirm "Snippet updated." toast. The change is backward-compatible — the existing funnel forms keep working.
- [ ] **Step 3: Regression-check the funnel.** Submit a test lead through the live `/design-your-door/` main form (mark it "TEST — Claude Phase 3 regression, please ignore") and confirm HTTP 200 + redirect fires (still to EZDoor at this point).
- [ ] **Step 4: Create the builder snippet on main.** WPCode → Add Snippet → blank PHP snippet, title "Twins Door Builder (visualizer)", paste the snippet body (no `<?php`), Auto Insert / Run Everywhere, Active. Record the new snippet id. (Region mapping is path-based via `home_url()` — no blog-id assumptions to confirm.)
- [ ] **Step 5: Smoke-test the shortcode** on a throwaway DRAFT page: create draft "DB smoke" with content `[twins_door_builder]` via REST, preview it logged-in, confirm the app boots and step 1 renders 23 cards. Delete the draft after.

---

### Task 3: Build the `/door-builder/` page on main

- [ ] **Step 1:** Create page via the proven Elementor builder-JS technique (`docs/superpowers/backups/2026-07-08-clopay-pages/twx-page-builder.js` pattern): slug `door-builder`, title "Design Your New Garage Door". Sections: (1) compact twx hero — H1 "Design Your New Garage Door", subhead "Pick your collection, style, color and windows — we'll bring it to life at your free on-site consult.", phone CTA (833) 833-2010; (2) HTML/shortcode widget `[twins_door_builder]`; (3) navy CTA band (same classes as the other twx pages).
- [ ] **Step 2:** Disable the Astra page title: REST `POST /wp-json/wp/v2/pages/{id}` body `{"meta":{"site-post-title":"disabled"}}`.
- [ ] **Step 3:** Rank Math meta via `POST /wp-json/rankmath/v1/updateMeta` (form-encoded, X-WP-Nonce): title "Design Your Garage Door Online | Twins Garage Doors", description "Build your new garage door online — pick a Clopay collection, style, color and windows, then get a free quote from Twins Garage Doors."
- [ ] **Step 4:** Publish, load logged-in, walk all six steps + submit a marked test lead → expect 200 + thanks state. Record page id.

---

### Task 4: Deploy snippet + build page on /wi

- [ ] **Step 1:** Create the same builder snippet on /wi (`https://twinsgaragedoors.com/wi/wp-admin/admin.php?page=wpcode`), identical body. Record snippet id.
- [ ] **Step 2:** Build `/wi/door-builder/` page same as Task 3 (hero phone (608) 888-8785 in HTML; runtime rewrite to (608) 925-2038 is expected and fine), Astra title meta, Rank Math meta: title "Design Your Garage Door Online | Twins Garage Doors Wisconsin", description localized to Madison + Milwaukee.
- [ ] **Step 3:** Walk the wizard on /wi logged-in, submit a marked test lead, confirm 200 + thanks + region "wi" in payload.

---

### Task 5: E2E verification + screenshots (both sites)

- [ ] **Step 1:** Confirm the two test leads ARRIVED as emails: search Daniel's Gmail (query `subject:(door) "TEST — Claude Phase 3"` and variants; the endpoint emails contact@twinsgaragedoors.com which lands in this inbox) — verify the Door configuration block lists the chosen options. If email can't be found within ~5 minutes, STOP and debug before the redirect swap.
- [ ] **Step 2:** Screenshots desktop + mobile-width (iframe technique) of: collection grid, design step w/ hero, color step w/ caption, windows step, summary w/ form, thanks state — each site. Save what you can; transcript-embedded is acceptable.
- [ ] **Step 3:** Fallback path on live: load `/door-builder/?twxdbfail=1` — confirm the fallback form renders.

---

### Task 6: Funnel redirect swap (main + /wi only)

- [ ] **Step 1:** For main page 7073 and /wi page 6756: snapshot current page HTML to `docs/superpowers/backups/2026-07-09-phase3-door-builder/` (funnel-7073-before.html, funnel-6756-before.html), then edit the post-submit JS (Elementor HTML widget) replacing the `ezdoor.clopay.com` redirect URL with `https://twinsgaragedoors.com/door-builder/` (main) / `https://twinsgaragedoors.com/wi/door-builder/` (/wi). Use the Elementor editor JS technique; save via `$e.run('document/save/update')`.
- [ ] **Step 2:** DO NOT touch /ky 6386 (keeps EZDoor until Phase 8).
- [ ] **Step 3:** Live test both funnels: submit marked test leads, confirm 200 and that the browser lands on the respective /door-builder/ page.

---

### Task 7: Cache purge + anonymous-ish final check

- [ ] **Step 1:** Main WP Rocket "Clear and preload" (no toast expected) + Settings → Edge Cache → "Clear Edge Cache" (toast expected). /wi has no Rocket.
- [ ] **Step 2:** Logged-in reload of both /door-builder/ pages and both funnel pages; confirm render + single H1 each (`document.querySelectorAll('h1').length` is 1). Anonymous verification stays PENDING (BlogVault).

---

### Task 8: Docs + handoff

- [ ] **Step 1:** Deployed-state copies: final snippet bodies (main + /wi + endpoint 7072) into the phase-3 backups dir (read out via CodeMirror if any drift from the repo files).
- [ ] **Step 2:** `docs/marketing/change-log.md` new section "Phase 3: door-builder visualizer" — rows: D1 endpoint v2 (revert: paste back endpoint-7072-before.php), D2 builder snippet main (revert: deactivate), D3 /door-builder/ main page (revert: unpublish), D4 snippet + page /wi, D5 redirect swap 7073/6756 (revert: restore ezdoor URL from snapshots), noting /ky untouched and anon verification PENDING.
- [ ] **Step 3:** Handoff doc: mark Phase 3 DONE w/ date + pointers, set Phase 4 (catalog, starting with goodgollygarage study + existing-pages audit) as NEXT, add Phase 3 token actuals.
- [ ] **Step 4:** Commit docs + backups:
```bash
git add docs/ && git commit -m "docs(web): phase-3 door-builder shipped — change-log + handoff current"
```
