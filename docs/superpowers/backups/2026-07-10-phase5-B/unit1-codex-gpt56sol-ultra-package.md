### 1. GO / NO-GO

**GO — for building published, conditionless clones only.** This does not authorize canary conditions or global cutover. Do not send the first clone POST until these residual gates pass; any mismatch changes the decision to NO-GO:

1. Capture complete restore artifacts for 36 and 305. The committed JSON files contain only `id`, template type, conditions, and `_elementor_data`; additionally capture title, post type/status, content, excerpt, modified/revision fingerprint, taxonomy, all post meta—including non-REST-visible protected meta—and current conditions. If an approved all-meta export is unavailable, stop.

2. Inspect authenticated `OPTIONS` for both:

   - `/wp-json/wp/v2/elementor_library`
   - `/wp-json/wp/v2/elementor_library/305`

   Require create/update support plus writable schema locations and exact types for all copied meta, `_elementor_conditions`, content, excerpt, page template, and the Elementor library taxonomy. `methods=[GET,POST]` alone is insufficient.

3. Freeze all 51 menu items as ordered `(item ID,parent,order,label,url)` tuples and hash that ledger.

4. Search active WPCode, Elementor Custom Code/Custom CSS, theme/Customizer CSS, and authored linked stylesheets for `.elementor-36` and `.elementor-305`. Generated `post-36.css`/`post-305.css` references are expected; any hand-authored root coupling is an abort.

5. Capture the minimal runtime baseline on anonymous page 6065 after WP Rocket releases delayed JavaScript, under a fixed consent state:

   - One desktop load and one mobile load.
   - One config each for `G-XW0RGPTGSN` and `G-JR7LW39CND`.
   - One Meta init for `554750209097175` and one `PageView`.
   - Any current GTM container.
   - Current GHL loader count and ID `66b654c1e70da57b4d7e70ba`; exactly one visible/openable bubble.
   - Existing console/network failures.
   - On mobile: open, internal scroll, page lock, X/Escape/overlay closure.

6. Capture the eight-control event ledger: desktop phone/quote, popup phone/quote, mobile Call/HCP Book, one normal nav link, and one submenu link. Record `dataLayer`, `fbq`, Ads `event`/`send_to`/parameters, network payload, and destination without completing a production conversion.

7. Immediately before each POST, re-read source hashes, conditions, status/type, modified/revision fingerprints, `9a8b90d.ct_saved_rows`, and the menu-ledger hash.

### 2. Where the re-skin CSS must live

Use **document-level Elementor Custom CSS only**, stored in each clone’s `_elementor_page_settings.custom_css`.

Do not add or change per-element `custom_css`. That would create additional element-tree deltas and could normalize the malformed existing marketing-button CSS. Elementor also documents that its special `selector` keyword is for element-level CSS, not page-level CSS. Therefore the blocks below use literal clone document classes rather than `selector`. [Elementor CSS selector guidance](https://elementor.com/help/css-selectors-in-elementor/)

Before pasting:

- Replace every `__HEADER_CLONE_ID__` with the returned decimal header clone ID.
- Replace every `__MENU_CLONE_ID__` with the returned decimal menu clone ID.
- Append the block after any copied document Custom CSS; do not overwrite or reformat the copied prefix.
- Paste no `<style>` tags.

Selector strategy:

- Header/in-DOM rules: `.elementor-__HEADER_CLONE_ID__ .twx2-header …`
- Saved-row content: `.elementor-__MENU_CLONE_ID__ .twx2-popmenu …`
- Body-mounted popup ancestors: `.popMENU-popup:has(.elementor-__MENU_CLONE_ID__ .twx2-popmenu) …`

A raw `#modal-9a8b90d`, `.uamodal-9a8b90d`, `.popMENU-popup`, or `.uael-modal` selector is unsafe because originals and clones share those tokens.

Before canary, require:

```js
CSS.supports('selector(:has(*))') === true
```

Then inspect generated `post-{MENU_CLONE_ID}.css` and the runtime DOM. Require exactly one matching popup wrapper, containing the menu-clone root and no `.elementor-305`. If `:has()` is stripped, unsupported by the approved browser policy, or the action wrapper is reparented outside both scoped paths, this reskin is NO-GO; do not substitute an unscoped selector.

### 3. Exact REST clone payloads

The committed backups do not contain literal source content/excerpt/meta/taxonomy values. Use the fresh `context=edit`/all-meta capture values without parsing or reserializing stored strings.

Let `TAXONOMY_REST_FIELD` be the exact writable field from authenticated `OPTIONS`, and let its value be the source’s exact term-ID array.

**MENU_CLONE — create first**

```js
const menuBody = {
  title: "UNIT 1 DEP — POP MENU 305 twx2 — 2026-07-10",
  status: "publish",
  content: source305.content.raw,
  excerpt: source305.excerpt.raw,
  meta: {
    _elementor_data: source305.meta._elementor_data,
    _elementor_edit_mode: source305.meta._elementor_edit_mode,
    _elementor_template_type: source305.meta._elementor_template_type,
    _elementor_page_settings: source305.meta._elementor_page_settings,
    _wp_page_template: source305.meta._wp_page_template,
    _elementor_version: source305.meta._elementor_version,
    _elementor_pro_version: source305.meta._elementor_pro_version,
    _elementor_conditions: []
  },
  [TAXONOMY_REST_FIELD]: structuredClone(
    source305[TAXONOMY_REST_FIELD]
  )
};
```

**HEADER_CLONE**

```js
const headerBody = {
  title: "UNIT 1 CANARY — Header 36 twx2 — 2026-07-10",
  status: "publish",
  content: source36.content.raw,
  excerpt: source36.excerpt.raw,
  meta: {
    _elementor_data: source36.meta._elementor_data,
    _elementor_edit_mode: source36.meta._elementor_edit_mode,
    _elementor_template_type: source36.meta._elementor_template_type,
    _elementor_page_settings: source36.meta._elementor_page_settings,
    _wp_page_template: source36.meta._wp_page_template,
    _elementor_version: source36.meta._elementor_version,
    _elementor_pro_version: source36.meta._elementor_pro_version,
    _elementor_conditions: []
  },
  [TAXONOMY_REST_FIELD]: structuredClone(
    source36[TAXONOMY_REST_FIELD]
  )
};
```

If `OPTIONS` exposes `_wp_page_template` only as core’s top-level `template` field, use:

```js
body.template = source.template;
delete body.meta._wp_page_template;
```

Never send it in both locations.

Do not include or copy:

```text
source id/type/title/slug/GUID/author/dates
source _elementor_conditions
_elementor_css
_elementor_element_cache
_elementor_controls_usage
revision/autosave/edit-lock metadata
any unlisted meta or taxonomy
```

Use an eight-second request timeout. A `201` response is not proof of success. Immediately GET the new ID with `?context=edit` and require:

- REST post `type === "elementor_library"`.
- Exact title and `status === "publish"`.
- Exact content and excerpt.
- Every copied meta value has exact value and JSON type.
- `_elementor_conditions` is an actual empty array—not missing, `"[]"`, or serialized PHP.
- Pre-editor `_elementor_data` raw-string hash equals the source hash.
- `_elementor_template_type === "section"` for MENU and `"header"` for HEADER.
- Exact taxonomy term assignment, resolving to `section`/`header`.
- Originals’ hashes, conditions, and revision fingerprints remain unchanged.

Protected meta generally must be registered for REST exposure; `wp.data` saves through the same REST schema and is not a bypass. [WordPress REST meta guidance](https://developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/)

Fallback policy:

- `_elementor_data`, conditions, type, or taxonomy not writable/readable exactly: **NO-GO**.
- Do not use Elementor import to seed the tree; Elementor’s local template path replaces element IDs. [Elementor template source](https://github.com/elementor/elementor/blob/main/includes/template-library/sources/local.php)
- Page Settings UI plus Elementor save is acceptable only after the exact tree/type/taxonomy already exists, and only when read-back proves every pre-existing page-setting key remains exact.
- `_elementor_conditions` must remain REST-writable and later proven by rendered resolution.
- Never edit 36 or 305 as a fallback.

### 4. Exact settings-delta operations

Resolve `C[id]` only from the currently open clone document and require exactly one match:

```js
function collect(container, out = []) {
  for (const child of Array.from(container.children || [])) {
    out.push(child);
    collect(child, out);
  }
  return out;
}

const all = collect(elementor.getPreviewContainer());

function elementId(container) {
  return String(
    container.id ??
    container.model?.id ??
    container.model?.get?.("id") ??
    ""
  );
}

const C = new Proxy({}, {
  get(_target, id) {
    const hits = all.filter(container => elementId(container) === String(id));
    if (hits.length !== 1) {
      throw new Error(`ABORT: ${String(id)} matched ${hits.length} containers`);
    }
    return hits[0];
  }
});
```

Also assert the editor URL/current document ID equals the intended clone. Do not resolve containers from the public DOM or from a header preview containing another saved-row document.

**MENU_CLONE document**

```js
await $e.run('document/elements/settings', {
  container: C['4b98c6c'],
  settings: { css_classes: 'twx2-popmenu' }
});

await $e.run('document/elements/settings', {
  container: C['5501e957'],
  settings: { _css_classes: 'twx2-header-logo' }
});

await $e.run('document/elements/settings', {
  container: C['78fc1bc8'],
  settings: { _css_classes: 'headerMENU twx2-header-menu' }
});

await $e.run('document/elements/settings', {
  container: C['fe4e792'],
  settings: { _css_classes: 'headerCALL twx2-header-call' }
});

await $e.run('document/elements/settings', {
  container: C['6a5d8f6'],
  settings: { _css_classes: 'headerBTN twx2-header-cta' }
});
```

Preconditions: `4b98c6c.settings` has no `css_classes`; `5501e957.settings` has no `_css_classes`; every other old value must equal the first token shown above. Then:

```js
await $e.run('document/save/default');
```

**HEADER_CLONE document**

```js
await $e.run('document/elements/settings', {
  container: C['33faaae'],
  settings: { css_classes: 'secHEADER twx2-header' }
});

await $e.run('document/elements/settings', {
  container: C['739983d'],
  settings: { css_classes: 'topHEADER twx2-header-top' }
});

await $e.run('document/elements/settings', {
  container: C['1120384'],
  settings: { _css_classes: 'tgLOGO twx2-header-logo' }
});

await $e.run('document/elements/settings', {
  container: C['bdcac79'],
  settings: { _css_classes: 'tgLOGO twx2-header-logo' }
});

await $e.run('document/elements/settings', {
  container: C['6e02966'],
  settings: { _css_classes: 'headerTAG twx2-header-tag' }
});

await $e.run('document/elements/settings', {
  container: C['f33e6c3'],
  settings: { _css_classes: 'headerCALL twx2-header-call' }
});

await $e.run('document/elements/settings', {
  container: C['8d4d9e4'],
  settings: { _css_classes: 'headerBTN twx2-header-cta' }
});

await $e.run('document/elements/settings', {
  container: C['cc876c5'],
  settings: { css_classes: 'botHEADER twx2-header-nav' }
});

await $e.run('document/elements/settings', {
  container: C['15c4a1b'],
  settings: { _css_classes: 'headerMENU twx2-header-menu' }
});

await $e.run('document/elements/settings', {
  container: C['9a8b90d'],
  settings: {
    _css_classes: 'popMENU twx2-pop-trigger',
    ct_saved_rows: String(MENU_CLONE_ID)
  }
});
```

Before the `9a8b90d` call, require:

```js
C['9a8b90d'].settings.get('content_type') === 'saved_rows'
C['9a8b90d'].settings.get('ct_saved_rows') === '305'
```

Do not include `content_type` in the payload. Then save once:

```js
await $e.run('document/save/default');
```

Read-back must prove:

```js
content_type === "saved_rows"
ct_saved_rows === String(MENU_CLONE_ID)
typeof ct_saved_rows === "string"
```

The full class strings are idempotent targets, not strings to concatenate blindly. On a resumed run, accept only the exact original state or exact completed state.

### 5. The full clone-scoped custom CSS

Replace both ID tokens with the returned decimal IDs before pasting. Abort if either token remains or if either block contains `.elementor-36`/`.elementor-305`.

**HEADER clone `_elementor_page_settings.custom_css`**

```css
.elementor-__HEADER_CLONE_ID__ .twx2-header {
  --twx2-navy: #022751;
  --twx2-deep: #010D38;
  --twx2-yellow: #FBBD04;
  --twx2-soft: #F2F5F7;
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-top {
  background-color: #010D38 !important;
  padding: 8px 28px !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-logo img {
  filter: drop-shadow(2px 3px 0 rgba(1, 13, 56, .5));
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-tag .uael-infobox-left-right-wrap {
  padding: 8px 12px;
  border: 2px solid #FBBD04;
  border-radius: 10px;
  box-shadow: 3px 3px 0 rgba(1, 13, 56, .65);
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-tag .uael-infobox-title {
  color: #FFFFFF !important;
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif !important;
  font-size: 16px !important;
  font-weight: 800 !important;
  line-height: 1.2 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-tag .uael-icon,
.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-tag .uael-icon i {
  color: #FBBD04 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-tag .uael-icon svg,
.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-tag .uael-icon svg * {
  fill: #FBBD04 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-call .uael-infobox-left-right-wrap {
  outline: 2px solid rgba(251, 189, 4, .35);
  outline-offset: -2px;
  border-radius: 10px;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-call .uael-infobox-title-prefix {
  color: #FFFFFF !important;
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif !important;
  font-weight: 700 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-call .uael-infobox-title {
  color: #FFFFFF !important;
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif !important;
  font-weight: 800 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-call .uael-icon,
.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-call .uael-icon i {
  color: #FBBD04 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-call .uael-icon svg,
.elementor-__HEADER_CLONE_ID__ .twx2-header .twx2-header-call .uael-icon svg * {
  fill: #FBBD04 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-cta .uael-marketing-button a.elementor-button,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-cta .uael-marketing-button a.elementor-button:hover,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-cta .uael-marketing-button a.elementor-button:focus {
  background-color: #FBBD04 !important;
  color: #022751 !important;
  border: 3px solid #010D38 !important;
  border-radius: 10px !important;
  box-shadow: 4px 4px 0 rgba(1, 13, 56, .85);
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif !important;
  font-weight: 800 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-cta .elementor-button-text,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-cta .elementor-button-icon {
  color: #022751 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-cta .elementor-button-icon svg {
  fill: #022751 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-nav {
  background-color: #022751 !important;
  padding: 10px 28px !important;
  border-bottom: 3px solid #FBBD04;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header.headershow .twx2-header-nav {
  border-bottom: 2px solid #FBBD04;
  box-shadow: 0 13px 25px -12px rgba(0, 0, 0, .4);
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .elementor-item {
  color: #CFDBEA !important;
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: .4px;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .elementor-item:hover,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .elementor-item:focus,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .elementor-item.highlighted,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .elementor-item.elementor-item-active,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .current-menu-item > .elementor-item,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .current-menu-ancestor > .elementor-item {
  color: #FBBD04 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--dropdown,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .sub-menu {
  background-color: #010D38 !important;
  border: 3px solid #FBBD04 !important;
  border-radius: 10px !important;
  box-shadow: 4px 4px 0 rgba(1, 13, 56, .85);
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--dropdown .elementor-sub-item,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .sub-menu .elementor-sub-item {
  color: #FFFFFF !important;
  background-color: transparent !important;
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--dropdown .elementor-sub-item:hover,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--dropdown .elementor-sub-item:focus,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--dropdown .elementor-sub-item.elementor-item-active,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .sub-menu .elementor-sub-item:hover,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .sub-menu .elementor-sub-item:focus,
.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-menu .elementor-nav-menu--main .sub-menu .elementor-sub-item.elementor-item-active {
  color: #FBBD04 !important;
  background-color: rgba(242, 245, 247, .10) !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-pop-trigger .uael-modal-action-wrap {
  background-color: #FBBD04 !important;
  border: 3px solid #010D38;
  border-radius: 10px;
  box-shadow: 4px 4px 0 rgba(1, 13, 56, .85);
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-pop-trigger .uael-modal-action i {
  color: #010D38 !important;
}

.elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-pop-trigger .uael-modal-action svg {
  fill: #010D38 !important;
}

@media (max-width: 1599px) {
  .elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element.twx2-header-top {
    padding: 8px 10px !important;
  }
}

@media (min-width: 1201px) and (max-width: 1599px) {
  .elementor-__HEADER_CLONE_ID__ .twx2-header .elementor-element-15c4a1b .elementor-nav-menu--main .elementor-item {
    font-size: 13px !important;
    letter-spacing: .25px !important;
    margin-left: 6px !important;
    margin-right: 6px !important;
  }
}
```

**MENU clone `_elementor_page_settings.custom_css`**

```css
.elementor-__MENU_CLONE_ID__ .elementor-element.twx2-popmenu {
  --twx2-navy: #022751;
  --twx2-deep: #010D38;
  --twx2-yellow: #FBBD04;
  --twx2-soft: #F2F5F7;
  background-color: #010D38 !important;
  border: 3px solid #FBBD04 !important;
  border-radius: 12px !important;
  box-shadow: 4px 4px 0 rgba(1, 13, 56, .85);
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .twx2-header-logo img {
  filter: drop-shadow(2px 3px 0 rgba(1, 13, 56, .5));
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu {
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu a.uael-menu-item {
  color: #FFFFFF !important;
  background-color: transparent !important;
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif;
  font-weight: 700;
  letter-spacing: .4px;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu a.uael-menu-item:hover,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu a.uael-menu-item:focus,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu a.uael-menu-item.highlighted,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu .current-menu-item > a.uael-menu-item,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu .current-menu-ancestor > .uael-has-submenu-container > a.uael-menu-item,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu .current-menu-parent > .uael-has-submenu-container > a.uael-menu-item {
  color: #FBBD04 !important;
  background-color: rgba(242, 245, 247, .10) !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu .sub-menu {
  background-color: #022751 !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu a.uael-sub-menu-item {
  color: #FFFFFF !important;
  background-color: transparent !important;
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif;
  font-weight: 600;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu a.uael-sub-menu-item:hover,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu a.uael-sub-menu-item:focus,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu a.uael-sub-menu-item.uael-sub-menu-item-active {
  color: #FBBD04 !important;
  background-color: rgba(242, 245, 247, .10) !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu .uael-menu-toggle,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu .uael-menu-toggle i {
  color: #FBBD04 !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu .uael-menu-toggle svg,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-menu .uael-menu-toggle svg * {
  fill: #FBBD04 !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .twx2-header-call .uael-infobox-left-right-wrap {
  outline: 2px solid rgba(251, 189, 4, .35);
  outline-offset: -2px;
  border-radius: 10px;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .twx2-header-call .uael-infobox-title-prefix {
  color: #FFFFFF !important;
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif !important;
  font-weight: 700 !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .twx2-header-call .uael-infobox-title {
  color: #FFFFFF !important;
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif !important;
  font-weight: 800 !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .twx2-header-call .uael-icon,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .twx2-header-call .uael-icon i {
  color: #FBBD04 !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .twx2-header-call .uael-icon svg,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .twx2-header-call .uael-icon svg * {
  fill: #FBBD04 !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-cta .uael-marketing-button a.elementor-button,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-cta .uael-marketing-button a.elementor-button:hover,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-cta .uael-marketing-button a.elementor-button:focus {
  background-color: #FBBD04 !important;
  color: #022751 !important;
  border: 3px solid #010D38 !important;
  border-radius: 10px !important;
  box-shadow: 4px 4px 0 rgba(1, 13, 56, .85);
  font-family: Montserrat, "Avenir Next", "Helvetica Neue", Arial, sans-serif !important;
  font-weight: 800 !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-cta .elementor-button-text,
.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-cta .elementor-button-icon {
  color: #022751 !important;
}

.elementor-__MENU_CLONE_ID__ .twx2-popmenu .elementor-element.twx2-header-cta .elementor-button-icon svg {
  fill: #022751 !important;
}

.popMENU-popup:has(.elementor-__MENU_CLONE_ID__ .twx2-popmenu) .uael-modal-action-wrap {
  background-color: #FBBD04 !important;
  border: 3px solid #010D38;
  border-radius: 10px;
  box-shadow: 4px 4px 0 rgba(1, 13, 56, .85);
}

.popMENU-popup:has(.elementor-__MENU_CLONE_ID__ .twx2-popmenu) .uael-modal-action i {
  color: #010D38 !important;
}

.popMENU-popup:has(.elementor-__MENU_CLONE_ID__ .twx2-popmenu) .uael-modal-action svg {
  fill: #010D38 !important;
}

.popMENU-popup:has(.elementor-__MENU_CLONE_ID__ .twx2-popmenu) .uael-modal-close i {
  color: #FBBD04 !important;
}

.popMENU-popup:has(.elementor-__MENU_CLONE_ID__ .twx2-popmenu) .uael-modal-close svg,
.popMENU-popup:has(.elementor-__MENU_CLONE_ID__ .twx2-popmenu) .uael-modal-close svg * {
  fill: #FBBD04 !important;
}

@media (max-width: 1200px) {
  .popMENU-popup:has(.elementor-__MENU_CLONE_ID__ .twx2-popmenu) .uael-modal.uael-modal-saved_rows {
    max-height: calc(100vh - 72px) !important;
  }

  .popMENU-popup:has(.elementor-__MENU_CLONE_ID__ .twx2-popmenu) .uael-modal.uael-modal-saved_rows .uael-content {
    max-height: calc(100vh - 72px) !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
  }
}
```

No rule sets persistent `overflow`, `transform`, `filter`, `perspective`, or `contain` on the sticky wrapper or modal layers. The only new overflow is the required responsive `.uael-content` scroll; the only new filter is the approved logo-image drop shadow.

### 6. Post-save mechanical verification

Use the fresh pre-save source strings as the comparison baseline; their hashes must still equal the committed hashes. Normalize only object-key order. Do not trim strings, sort arrays, normalize URLs/HTML, collapse missing/empty values, or coerce strings/numbers.

```js
const own = (object, key) =>
  Object.prototype.hasOwnProperty.call(object, key);

function flatten(nodes, base = '$', out = []) {
  nodes.forEach((node, index) => {
    const path = `${base}[${index}]`;
    out.push({ path, node });
    flatten(node.elements || [], `${path}.elements`, out);
  });
  return out;
}

function canonical(value) {
  if (Array.isArray(value)) {
    return `[${value.map(canonical).join(',')}]`;
  }

  if (value && typeof value === 'object') {
    return `{${Object.keys(value).sort().map(
      key => `${JSON.stringify(key)}:${canonical(value[key])}`
    ).join(',')}}`;
  }

  return JSON.stringify(value);
}

function assert(condition, message) {
  if (!condition) throw new Error(`ABORT: ${message}`);
}

const HEADER_EDITS = [
  ['33faaae', 'css_classes',  true, 'secHEADER',   'secHEADER twx2-header'],
  ['739983d', 'css_classes',  true, 'topHEADER',   'topHEADER twx2-header-top'],
  ['1120384', '_css_classes', true, 'tgLOGO',      'tgLOGO twx2-header-logo'],
  ['bdcac79', '_css_classes', true, 'tgLOGO',      'tgLOGO twx2-header-logo'],
  ['6e02966', '_css_classes', true, 'headerTAG',    'headerTAG twx2-header-tag'],
  ['f33e6c3', '_css_classes', true, 'headerCALL',   'headerCALL twx2-header-call'],
  ['8d4d9e4', '_css_classes', true, 'headerBTN',    'headerBTN twx2-header-cta'],
  ['cc876c5', 'css_classes',  true, 'botHEADER',    'botHEADER twx2-header-nav'],
  ['15c4a1b', '_css_classes', true, 'headerMENU',   'headerMENU twx2-header-menu'],
  ['9a8b90d', '_css_classes', true, 'popMENU',      'popMENU twx2-pop-trigger']
];

const MENU_EDITS = [
  ['4b98c6c', 'css_classes',  false, undefined,    'twx2-popmenu'],
  ['5501e957', '_css_classes', false, undefined,   'twx2-header-logo'],
  ['78fc1bc8', '_css_classes', true, 'headerMENU', 'headerMENU twx2-header-menu'],
  ['fe4e792', '_css_classes', true, 'headerCALL',  'headerCALL twx2-header-call'],
  ['6a5d8f6', '_css_classes', true, 'headerBTN',   'headerBTN twx2-header-cta']
];

function verifyTree(sourceRaw, cloneRaw, kind, menuCloneId) {
  const source = JSON.parse(sourceRaw);
  const actual = JSON.parse(cloneRaw);

  const sourceFlat = flatten(source);
  const actualFlat = flatten(actual);
  const expectedCount = kind === 'header' ? 17 : 6;

  assert(sourceFlat.length === expectedCount,
    `${kind} source count ${sourceFlat.length}`);
  assert(actualFlat.length === expectedCount,
    `${kind} clone count ${actualFlat.length}`);

  const manifest = flat => flat.map(({ path, node }) => [
    path,
    node.id,
    node.elType,
    own(node, 'widgetType') ? node.widgetType : null
  ]);

  assert(
    canonical(manifest(sourceFlat)) === canonical(manifest(actualFlat)),
    `${kind} manifest/path mismatch`
  );

  const ids = actualFlat.map(({ node }) => node.id);
  assert(new Set(ids).size === ids.length, `${kind} duplicate element ID`);

  const expected = JSON.parse(JSON.stringify(source));
  const expectedFlat = flatten(expected);
  const expectedById = new Map(
    expectedFlat.map(({ node }) => [node.id, node])
  );

  const edits = kind === 'header' ? HEADER_EDITS : MENU_EDITS;

  for (const [id, key, wasPresent, before, after] of edits) {
    const node = expectedById.get(id);
    assert(node, `missing expected target ${id}`);

    const present = own(node.settings, key);
    assert(present === wasPresent, `${id}.${key} presence changed`);

    if (wasPresent) {
      assert(node.settings[key] === before,
        `${id}.${key} source was ${JSON.stringify(node.settings[key])}`);
    }

    node.settings[key] = after;
  }

  if (kind === 'header') {
    const modal = expectedById.get('9a8b90d');

    assert(modal.settings.content_type === 'saved_rows',
      '9a8b90d.content_type source mismatch');
    assert(modal.settings.ct_saved_rows === '305',
      '9a8b90d.ct_saved_rows source mismatch');

    modal.settings.ct_saved_rows = String(menuCloneId);
  }

  assert(
    canonical(expected) === canonical(actual),
    `${kind} contains an unapproved element-tree delta`
  );

  if (kind === 'header') {
    const modal = flatten(actual)
      .map(({ node }) => node)
      .find(node => node.id === '9a8b90d');

    assert(modal.settings.content_type === 'saved_rows',
      'content_type changed');
    assert(modal.settings.ct_saved_rows === String(menuCloneId),
      'wrong clone dependency');
    assert(typeof modal.settings.ct_saved_rows === 'string',
      'ct_saved_rows is not a string');
    assert(
      !flatten(actual).some(
        ({ node }) => node.settings?.ct_saved_rows === '305'
      ),
      'header clone still contains ct_saved_rows "305"'
    );
  }

  return true;
}
```

Run:

```js
verifyTree(source305Raw, menuCloneRaw, 'menu', MENU_CLONE_ID);
verifyTree(source36Raw, headerCloneRaw, 'header', MENU_CLONE_ID);
```

Separately compare `_elementor_page_settings`:

1. Deep-copy the source settings.
2. Set only `custom_css` to the exact complete field value actually pasted—copied source prefix plus the appropriate block.
3. Canonical-deep-compare to clone settings.
4. Any other page-setting delta is an abort.

Then require after each clone save:

- Clone conditions remain exact `[]`.
- Copied non-cache meta, content, excerpt, type, taxonomy, and status remain exact.
- Existing per-element `custom_css`, including malformed CTA tails, remains byte-equivalent through the tree comparison.
- Clone-owned `_elementor_css`, element cache, controls-usage data, and revisions were not copied from the source; if Elementor generated new clone-owned records, inventory them separately rather than treating them as source equivalence.
- Originals 36/305 still have the committed raw hashes, original conditions, and unchanged modified/revision fingerprints.
- Header generated CSS contains `.elementor-{HEADER_CLONE_ID}` and no `.elementor-36`.
- Menu generated CSS contains `.elementor-{MENU_CLONE_ID}` and no `.elementor-305`.
- Every body-level emitted rule contains `:has(.elementor-{MENU_CLONE_ID} .twx2-popmenu)`.
- No unresolved `__HEADER_CLONE_ID__`, `__MENU_CLONE_ID__`, or literal `selector` remains.
- Both `post-{clone-id}.css` requests return 200.
- Runtime popup inspection finds exactly one clone-matching wrapper; it contains one `.elementor-{MENU_CLONE_ID}`, one `.twx2-popmenu`, the expected action/close controls, and zero `.elementor-305`.
- Anonymous homepage, page 6065, and one service-page control still resolve exactly one original header 36/menu 305 and contain neither clone ID. This proves clone publication/save did not unexpectedly alter Theme Builder resolution.

Any Elementor migration/default injection, wrapper ambiguity, stale source fingerprint, or additional tree/page-setting delta means: leave both clones published but conditionless, make no condition change, and stop.