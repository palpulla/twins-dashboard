# UNIT 1 — Main-site Elementor header + POP MENU safe execution package

## 1. PRESERVE-LIST

Scope is the root site `https://twinsgaragedoors.com`, never `/wi` or `/ky`. Those subsites reuse document IDs `36` and `305`.

The archived HTML is historical evidence, not the final current-state oracle: it predates the Phase-2 menu restructure and the current chat-widget ID. Immediately before execution, capture a fresh menu/event/rendered baseline and abort on unexplained drift.

### Document identity

| Document | Required state |
|---|---|
| Header `36` | Elementor Library type `header`; `_elementor_conditions=["include/general"]`; `_elementor_data` SHA-256 `783e2f36d672d9d8365efac326fab871a4e11b5c99745d32710cd13fc60c0570` |
| POP MENU `305` | Elementor Library type `section`; `_elementor_conditions=[]`; `_elementor_data` SHA-256 `c613986b529c1c37adef27846ad58ac78bd242221e482f965705eeee767003ed` |
| Dependency | Header widget `9a8b90d.settings.content_type="saved_rows"` and `ct_saved_rows="305"` |

Evidence: [tb-36.json](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-10-phase5-B/tb-36.json:1), [tb-305.json](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-10-phase5-B/tb-305.json:1).

### Every Elementor element ID

“Element ID” below means an ID reached through Elementor’s recursive `elements[]` tree—not attachment IDs, menu-item IDs, repeater IDs, or display-condition `_id` values.

| ID | Role and required token | What breaks if changed/lost |
|---|---|---|
| `33faaae` | Outer `section.secHEADER`, HTML ID `menuhopin` | Sticky wrapper, external scroll hook and generated CSS |
| `4a901c9` | Header’s outer 100% column | Section nesting/layout and scoped CSS |
| `739983d` | `section.topHEADER` | Desktop logo/tagline/phone/quote row and external flex CSS |
| `82f1616` | Desktop-logo column | Responsive visibility and logo positioning |
| `1120384` | Desktop `theme-site-logo.tgLOGO` | Dynamic site logo/home link and generated sizing |
| `fd48e27` | Tagline column | Desktop/tablet-extra layout and flex behavior |
| `6e02966` | `uael-infobox.headerTAG` | “T'Winning Every Time,” SVG attachments `167`/`159`, raw telephone setting |
| `ba98c14` | Phone column | Responsive order, padding and phone alignment |
| `f33e6c3` | Desktop `uael-infobox.headerCALL` | `tel:8338332010`, phone text/icon and possible external click rules |
| `e8368bf` | Quote column | Quote CTA alignment and responsive padding |
| `8d4d9e4` | Desktop `uael-marketing-button.headerBTN` | Quote link, label, arrow, blink span and possible click tracking |
| `cc876c5` | `section.botHEADER` | Desktop nav row and `.headershow .botHEADER` border/shadow |
| `6b4843e` | Desktop-nav/mobile-logo column | Nav spacing and responsive logo placement |
| `15c4a1b` | Desktop `nav-menu.headerMENU` | **Hard coupling:** the 1201–1599px nav-fit rule stops matching |
| `bdcac79` | Responsive `theme-site-logo.tgLOGO` | Tablet/mobile logo disappears |
| `d02809e` | Responsive modal column | Hamburger/mobile-menu visibility |
| `9a8b90d` | `uael-modal-popup.popMENU` | Modal trigger, ESC/overlay close and saved-row reference |
| `4b98c6c` | POP MENU 305 root section | Modal background/border and saved-row render |
| `4facb93a` | POP MENU column | Internal padding/layout |
| `5501e957` | POP MENU site-logo widget | Modal logo/home link |
| `78fc1bc8` | POP MENU `uael-nav-menu.headerMENU` | Vertical dynamic menu, submenus and generated menu ID |
| `fe4e792` | POP MENU `uael-infobox.headerCALL` | Modal telephone CTA |
| `6a5d8f6` | POP MENU `uael-marketing-button.headerBTN` | Modal quote CTA |

Preserve the resulting functional IDs as well:

```text
menuhopin
menu-1-15c4a1b
menu-2-15c4a1b
9a8b90d-overlay
modal-9a8b90d
menu-1-78fc1bc8
```

These are visible in the rendered header at [baseline-main-6065.html:277](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase1/baseline-main-6065.html:277).

Also preserve the UAEL menu repeater IDs `4dabec2`, `a3a3b16`, `8feffcc`, `eb00338`; dynamic-tag ID `df0b1d9`; media IDs `167`, `159`, `137`, `218`, `297`; and every existing `display_condition_list` verbatim.

### Classes, attributes and handlers

| Token | Contract / failure if lost |
|---|---|
| `secHEADER`, `topHEADER`, `botHEADER` | Site CSS and scroll-state styling depend on these section names |
| `tgLOGO` | Existing logo sizing/position rules stop matching |
| `headerTAG` | Tagline styling stops matching |
| `headerCALL` | Phone styling/hover behavior and possible external event rules stop matching |
| `headerBTN` | CTA styling and possible Meta point-and-click rules stop matching |
| `headerMENU` | Desktop and modal submenu styling stops matching |
| `popMENU`, generated `popMENU-popup` | Modal positioning, close-button and content rules stop matching |
| `headershow` | Added externally to `#menuhopin`; its gold border/shadow state disappears if renamed |
| `.elementor-nav-menu--main .elementor-item` | Required by the nav-fit selector |
| `.uael-modal.uael-modal-saved_rows .uael-content` | Required by the mobile internal-scroll fix |
| `.uael-html-modal` | UAEL’s transient page-scroll lock class; do not override it |
| `.uael-nav-menu`, `.uael-menu-item`, `.uael-sub-menu-item` | UAEL submenu interaction and styling |
| `#twx2-stickybar`, `.twx2-sb-call`, `.twx2-sb-book`, `body.twx2-hasbar` | Current site-wide mobile Call/Book bar; outside 36/305 but must remain unobstructed |

`elementor-36` and `elementor-305` are document-root classes and necessarily become `elementor-{HEADER_CLONE_ID}` and `elementor-{MENU_CLONE_ID}`. Before canary, search all active WPCode, Elementor Custom CSS/Custom Code and theme CSS for literal `.elementor-36` or `.elementor-305`. Port any non-generated rule to an additive preserved class; otherwise abort.

All five authored link `custom_attributes` values are exactly `""`; `is_external` and `nofollow` are also empty. Do not invent replacements.

The rendered modal attributes must be regenerated unchanged:

```text
data-trigger-on="icon"
data-close-on-esc="yes"
data-close-on-overlay="yes"
data-content="saved_rows"
data-autoplay="no"
data-device="false"
data-scroll-direction="down"
data-scroll-percentage="30"
data-page-views-enabled="no"
data-page-views-count="3"
data-page-views-scope="global"
data-sessions-enabled="no"
data-sessions-count="2"
```

Empty modal attributes—exit intent, delay, cookies, custom selector, async and scroll element—also remain empty. See [baseline-main-6065.html:590](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase1/baseline-main-6065.html:590).

There is no authored `onclick`, `data-rocket-onclick`, `javascript:` link, `dataLayer` call, `gtag` call or `fbq` call inside 36/305. Do not add one. The external scroll handler is:

```js
if ($(document).scrollTop() > 0) {
  $('#menuhopin').addClass('headershow');
} else {
  $('#menuhopin').removeClass('headershow');
}
```

[baseline-main-6065.html:103](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase1/baseline-main-6065.html:103).

### Non-menu links

| Owner | Exact preserved value | Failure if changed |
|---|---|---|
| All three logo widgets | Dynamic `site-logo` + `site-url`; rendered `https://twinsgaragedoors.com` | Wrong logo/home destination |
| `6e02966` tagline | Raw `infobox_text_link.url="tel:8338332010"` even though the baseline renders no tagline anchor | Clone ceases to be data-equivalent |
| `f33e6c3` desktop phone | `tel:8338332010`; visible `(833) 833-2010` | Call CTA and possible click attribution fail |
| `fe4e792` POP phone | `tel:8338332010`; visible `(833) 833-2010` | Mobile call CTA fails |
| `8d4d9e4` desktop quote | Dynamic internal URL: post `2123`; renders `https://twinsgaragedoors.com/contact-us/` | Quote CTA points elsewhere or loses dynamic resolution |
| `6a5d8f6` POP quote | Literal `/contact-us` | Mobile quote CTA fails |
| Current mobile Book bar | `https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true` | HCP online booking fails |

The HCP link is not inside 36/305. The older baseline shows it under `#twins-callbar`; the current main-site implementation is `#twx2-stickybar` at widths ≤768 and is suppressed on `-lp`/`.tlp` pages. Do not copy either bar into the clone. [baseline-main-6065.html:1337](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase1/baseline-main-6065.html:1337), [change-log.md:12](/Users/daniel/twins-dashboard/docs/marketing/change-log.md:12).

### Dynamic WordPress menu and every expected href

Both widgets use `settings.menu="menu"`, the root site’s WordPress menu **Menu, ID 13**. Do not duplicate or hard-code its HTML. The UAEL widget’s four `menu_items` rows are placeholders, not the source of the rendered links. [change-log.md:35](/Users/daniel/twins-dashboard/docs/marketing/change-log.md:35).

Expected current menu, combining the post-P2 rendered snapshot with the later Phase-4 addition:

```text
Locations → https://twinsgaragedoors.com/locations/
  Wisconsin → /wi
    Madison → https://twinsgaragedoors.com/wi/location/madison/
    Milwaukee → https://twinsgaragedoors.com/wi/garage-door-repair-in-milwaukee-wi/
    Belleville → https://twinsgaragedoors.com/wi/location/belleville/
    Cottage Grove → https://twinsgaragedoors.com/wi/location/cottage-grove/
    Cross Plains → https://twinsgaragedoors.com/wi/location/cross-plains/
    Deerfield → https://twinsgaragedoors.com/wi/location/deerfield/
    DeForest → https://twinsgaragedoors.com/wi/location/deforest/
    Edgerton → https://twinsgaragedoors.com/wi/location/edgerton/
    Evansville → https://twinsgaragedoors.com/wi/location/evansville/
    Fitchburg → https://twinsgaragedoors.com/wi/location/fitchburg/
    Fort Atkinson → https://twinsgaragedoors.com/wi/location/fort-atkinson/
    Janesville → https://twinsgaragedoors.com/wi/location/janesville/
    Marshall → https://twinsgaragedoors.com/wi/location/marshall/
    McFarland → https://twinsgaragedoors.com/wi/location/mcfarland/
    Middleton → https://twinsgaragedoors.com/wi/location/middleton/
    Milton → https://twinsgaragedoors.com/wi/location/milton/
    Monona → https://twinsgaragedoors.com/wi/location/monona/
    Oregon → https://twinsgaragedoors.com/wi/location/oregon/
    Prairie Du Sac → https://twinsgaragedoors.com/wi/location/prairie-du-sac/
    Sun Prairie → https://twinsgaragedoors.com/wi/location/sun-prairie/
    Verona → https://twinsgaragedoors.com/wi/location/verona/
  Kentucky → /ky
    Lexington → https://twinsgaragedoors.com/ky/location/lexington/

Garage Doors → https://twinsgaragedoors.com/garage-door-services/
  All Clopay Collections → https://twinsgaragedoors.com/clopay-garage-doors/
  Clopay Classic™ Collection → https://twinsgaragedoors.com/clopay-classic-collection/
  Clopay Modern Steel™ → https://twinsgaragedoors.com/clopay-modern-steel/
  Clopay Gallery® Steel → https://twinsgaragedoors.com/clopay-gallery-steel/

Design Your Door → https://twinsgaragedoors.com/design-your-door/

Openers → https://twinsgaragedoors.com/garage-door-openers/
  LiftMaster 6690L → https://twinsgaragedoors.com/liftmaster-6690l/
  LiftMaster 6580L → https://twinsgaragedoors.com/liftmaster-6580l/
  LiftMaster 2220L → https://twinsgaragedoors.com/liftmaster-2220l/

Services & Repair → #
  Maintenance Packages → https://twinsgaragedoors.com/maintenance-plans/
  Garage Door Installation → https://twinsgaragedoors.com/garage-door-installation/
  Garage Door Spring Repair → https://twinsgaragedoors.com/garage-door-spring-repair/
  Garage Door Opener Repair → https://twinsgaragedoors.com/garage-door-opener-repair/
  Garage Door Cable Repair → https://twinsgaragedoors.com/garage-door-cable-repair/
  Weatherstripping Repair → https://twinsgaragedoors.com/garage-weatherstripping-repair/
  Property Management Services → https://twinsgaragedoors.com/property-management-services/

Emergency Services → https://twinsgaragedoors.com/emergency-garage-services/

About Us → https://twinsgaragedoors.com/about-us/
  Reviews → https://twinsgaragedoors.com/reviews/
  FAQ’S → https://twinsgaragedoors.com/faqs/
  Coupons & Offers → https://twinsgaragedoors.com/coupons-offers/
  Financing → https://twinsgaragedoors.com/financing/
  Careers → https://twinsgaragedoors.com/careers/

Blog → https://twinsgaragedoors.com/blog/
```

The archived baseline’s Hörmann item `6180` is stale and **must not be reintroduced**; it was removed. Items `7094–7114`, `7115`, `7116`, and `7145` were added later. Evidence: [post-P2 rendered header](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase3-door-builder/page-main-door-builder-built.html:288), [change-log.md:39](/Users/daniel/twins-dashboard/docs/marketing/change-log.md:39), [change-log.md:13](/Users/daniel/twins-dashboard/docs/marketing/change-log.md:13).

Immediately before cloning, fetch menu 13 through REST and freeze an ordered `(item ID,parent,order,label,url)` ledger. That fresh ledger overrides this archived list if it shows an intentional later change.

### Behavior and integrations

| Behavior | Exact contract | What breaks if lost |
|---|---|---|
| Sticky | `33faaae.settings.sticky="top"`; rendered `sticky_on` includes desktop, laptop, tablet-extra, tablet and mobile | Header stops sticking or differs by breakpoint |
| Scroll state | `#menuhopin` receives `.headershow`; `.headershow .botHEADER` gets `2px #FBBD04` border and shadow | Scrolled header loses state |
| Desktop threshold | Desktop nav above 1200; responsive logo/modal at 1200 and below | Wrong header variant or two variants |
| Nav-fit | `@media(min-width:1201px) and (max-width:1599px)` targeting `.elementor-element-15c4a1b`; `13px`, `.25px`, `6px` side margins | `Blog` wraps and logo overlaps `Locations` |
| Mobile internal scroll | `.uael-modal.uael-modal-saved_rows` and `.uael-content`, `max-height:calc(100vh - 72px)`, `overflow-y:auto`, momentum scroll, `overscroll-behavior:contain` | Expanded 21-city menu becomes unreachable |
| Page scroll lock | UAEL transient `.uael-html-modal{overflow:hidden!important}` behavior | Page scrolls behind the open menu |
| Close behavior | `modal_on:"icon"`, `esc_keypress:"yes"`, `overlay_click:"yes"`, effect `uael-effect-5` | Hamburger, Escape or overlay close fails |
| WP Rocket delayed JS | SmartMenus, UAEL modal/nav and Elementor sticky run as delayed scripts | Testing before interaction can give false negatives |
| Chat | Current widget ID `66b654c1e70da57b4d7e70ba`; loader self-mounts under `body` | Chat disappears, duplicates or sits behind the new header |
| Mobile bar | `#twx2-stickybar`, ≤768, z-index `99998` | Call/Book actions disappear or obstruct the modal |

Nav-fit/internal-scroll evidence: [snippet-7050-after-p2fix.php:207](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase2-menus/snippet-7050-after-p2fix.php:207). Current chat has two loaders for the same widget on main—durable WPCode plus phantom `wp_body_open`—but one visible bubble. This unit must add no third loader and no second bubble. [change-log.md:160](/Users/daniel/twins-dashboard/docs/marketing/change-log.md:160).

Tracking is outside 36/305, but DOM-based event rules can still depend on the preserved controls:

- GA loader/config IDs: `G-XW0RGPTGSN` and `G-JR7LW39CND`.
- Meta Pixel: `554750209097175`, `PageView`.
- No `GTM-*`, `gtag_report_conversion`, header `gtag('event')`, header `fbq` event or header `dataLayer.push` exists in the archived HTML.
- Preserve button text, anchor shape and nesting because current external Meta/Ads click rules are not visible in the export.

Evidence: [baseline-main-6065.html:117](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase1/baseline-main-6065.html:117).

## 2. RE-SKIN SPEC

Use the approved Option A language: navy `#022751`, deep navy `#010D38`, yellow `#FBBD04`, soft `#F2F5F7`, Montserrat, 10–12px radii, 3px borders and hard 3–4px sticker shadows. Sticker treatment belongs on interactive/featured controls—not body text. [twx-v2-mockup-approved.html:3](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase4-catalog/twx-v2-mockup-approved.html:3), [twx-v2-mockup-approved.html:39](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase4-catalog/twx-v2-mockup-approved.html:39), [twx-v2-mockup-approved.html:213](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase4-catalog/twx-v2-mockup-approved.html:213).

All CSS must live in clone-scoped Elementor settings/custom CSS. Do not edit snippet 7050 in this unit.

| Existing section/element | Additive class | Required treatment |
|---|---|---|
| `33faaae.secHEADER#menuhopin` | `twx2-header` | Keep `sticky:"top"`, z-index `5`, margins and ID. No newly authored persistent `overflow`, `transform`, `filter`, `perspective` or `contain`. |
| `739983d.topHEADER` | `twx2-header-top` | Deep navy `#010D38`; Montserrat; retain columns, responsive visibility and existing logo overlap geometry. Desktop padding may become `8px 28px`, laptop/tablet-extra `8px 10px`; no structural moves. |
| `1120384`, `bdcac79`, `5501e957` | `twx2-header-logo` | Preserve dynamic logo/link, widths and negative margins. Add only `filter:drop-shadow(2px 3px 0 rgba(1,13,56,.5))` to the image. |
| `6e02966.headerTAG` | `twx2-header-tag` | Keep text/assets/raw tel setting. Convert to compact featured chip: white text, yellow icon, 2px yellow outline, radius 10px, `8px 12px`, small deep-navy shadow. Do not make it newly clickable. |
| `f33e6c3`, `fe4e792` | `twx2-header-call` | Preserve link/nesting/text. Yellow icon; white prefix/number; Montserrat 700/800. Use a subtle 2px yellow-alpha outline/radius 10 around the existing module, without replacing its overlay anchor. |
| `8d4d9e4`, `6a5d8f6` | `twx2-header-cta` | Gold sticker button: `#FBBD04` background, `#022751` text, 3px `#010D38` border, 10px radius, `4px 4px 0 rgba(1,13,56,.85)`, Montserrat 800. Keep label, href/dynamic tag, arrow and `blink_effect:"yes"`. |
| `cc876c5.botHEADER` | `twx2-header-nav` | Navy `#022751`, `10px 28px`, 3px yellow bottom border. Preserve content width, columns and `.headershow` state. |
| `15c4a1b.headerMENU` | `twx2-header-menu` | Never rename the ID. Montserrat 13px/700, `.4px`, `#CFDBEA`; active/hover/focus yellow. Keep horizontal widget, dynamic menu and responsive hide flags. Existing nav-fit rule remains authoritative at 1201–1599. |
| Desktop dropdowns | via `twx2-header-menu` | Deep navy background, white text, yellow active state, 3px yellow border, 10px radius and hard sticker shadow. Preserve caret, parent links, submenu DOM and keyboard behavior. |
| `9a8b90d.popMENU` | `twx2-pop-trigger` | Keep UAEL widget and all behavior settings. Style existing action wrap as gold sticker control with deep-navy icon/border, 10px radius and 4px shadow. |
| `4b98c6c` POP root | `twx2-popmenu` | Deep navy, 3px yellow border, radius 12, hard shadow. Do not set persistent overflow on `.uael-modal`, `.uael-content`, `html` or `body`. |
| `78fc1bc8.headerMENU` | `twx2-header-menu` | Keep `layout:"vertical"` and `menu:"menu"`. White top-level links, yellow active/focus, soft low-opacity hover surface; maintain all submenu expand controls and 250px dropdown width. |
| POP phone/quote | classes above | Same phone/CTA language as the desktop versions; preserve mobile padding and exact links. |

Append classes; never replace existing `css_classes` on sections or `_css_classes` on widgets.

Protected settings that must not change:

```text
id, elType, widgetType, elements[], isInner
sticky and every hide_* flag
display_condition_list
__dynamic__
menu and menu_items
link and infobox_text_link
modal_on, content_type, esc_keypress, overlay_click, modal_effect
icons/media IDs and URLs
all labels and visible phone text
```

The only functional delta permitted is:

```text
clone header 9a8b90d.settings.ct_saved_rows:
"305" → String(MENU_CLONE_ID)
```

Mechanically diff source vs clone after saving. Allowed differences are only:

- The one saved-row reference above.
- Appended `twx2-*` class tokens.
- Explicitly listed color, typography, padding, border, radius, shadow and clone-scoped custom-CSS paths.

Anything else is an abort.

## 3. CLONE-AND-SWAP RUNBOOK

### 3.1 Identity, drift and concurrency gate

1. Open only `https://twinsgaragedoors.com/wp-admin/`. The URL must contain neither `/wi/` nor `/ky/`.
2. Fetch a fresh REST nonce from `/wp-admin/admin-ajax.php?action=rest-nonce`.
3. Confirm `OPTIONS /wp-json/wp/v2/elementor_library` permits authenticated create/update and exposes the required meta/taxonomy fields. If not, stop; do not improvise with database forms.
4. Fetch posts 36 and 305 with `?context=edit`. Immediately record:

   - Post ID, title, status, post type, modified timestamp and current revision.
   - Elementor library/template type and taxonomy.
   - `_elementor_edit_mode`, `_elementor_template_type`, `_elementor_page_settings`, `_wp_page_template`, Elementor/Core/Pro version meta.
   - `_elementor_data`, `_elementor_conditions`.
   - Header widget `9a8b90d.ct_saved_rows`.
   - Exact recursive `(path,id,elType,widgetType)` manifest.

5. Require the hashes and conditions from §1, `status="publish"`, header type, section type and `ct_saved_rows==="305"`. Any mismatch is a NO-GO until the evidence package is refreshed.
6. Freeze a fresh menu-13 ordered ledger and fresh rendered/event baselines on page 6065. Re-fetch these immediately before every live condition change. Abort on a revision, hash, condition or menu-tree change by another session.
7. Capture full restore artifacts for both posts: post fields, all Elementor metadata, conditions, taxonomy and status—not only `_elementor_data`.
8. Search live active snippets/Custom Code/theme CSS for `.elementor-36`, `.elementor-305`, every preserve-list ID/class, `menuhopin`, phone/quote labels and tracking handlers. Resolve any root-document coupling before cloning.

### 3.2 Create the dependency clone first

Create POP MENU clone before the header:

```text
Title: UNIT 1 DEP — POP MENU 305 twx2 — 2026-07-10
Post type: elementor_library
Status: publish
Template type/taxonomy: exact copy of 305 ("section")
_elementor_conditions: []
```

Copy explicitly:

```text
content.raw and excerpt.raw
_elementor_data
_elementor_edit_mode
_elementor_template_type
_elementor_page_settings
_wp_page_template
_elementor_version
_elementor_pro_version
the same Elementor library-type taxonomy field/term
```

Do not copy:

```text
_elementor_conditions
_elementor_css
_elementor_element_cache
_elementor_controls_usage
revision/autosave/edit-lock metadata
```

Record the returned ID as `MENU_CLONE_ID`.

Open its Elementor editor, wait until the document container loads, apply §2 settings by ID with:

```js
$e.run('document/elements/settings', {
  container,
  settings: { /* exact allowed setting delta */ }
});
await $e.run('document/save/default');
```

Never run `document/elements/empty`, rebuild the tree or use a duplication path that regenerates IDs.

Reload, refetch and verify:

- Type is `section`, status is `publish`, conditions are `[]`.
- Every `(path,id,elType,widgetType)` equals source 305.
- Protected fields match.
- Editor opens without error.

### 3.3 Create and wire the header clone

Create:

```text
Title: UNIT 1 CANARY — Header 36 twx2 — 2026-07-10
Post type: elementor_library
Status: publish
Template type/taxonomy: exact copy of 36 ("header")
_elementor_conditions: []
```

Record the ID as `HEADER_CLONE_ID`.

The clone must begin with the exact element tree from 36. In its editor, locate element `9a8b90d` and apply:

```js
$e.run('document/elements/settings', {
  container: modalContainer,
  settings: { ct_saved_rows: String(MENU_CLONE_ID) }
});
await $e.run('document/save/default');
```

Apply §2 styles, save, reload and refetch. Require:

```text
9a8b90d.content_type === "saved_rows"
9a8b90d.ct_saved_rows === String(MENU_CLONE_ID)
_elementor_conditions === []
```

It must contain no remaining `"ct_saved_rows":"305"`.

Run Elementor → Tools → General → **Clear Files & Data** (older label: Regenerate CSS & Data). Open both clone previews and require their generated `post-{clone-id}.css` requests to return 200. Elementor documents this cache-clearing operation at [Clear Files & Data](https://elementor.com/help/regenerate-css-data/).

No WP Rocket/edge purge is needed yet because neither clone has a header display condition.

### 3.4 Exact condition states

`MENU_CLONE_ID` and original menu 305 always keep `[]`.

| State | Original header 36 | Header clone |
|---|---|---|
| Build / safe fallback | `["include/general"]` | `[]` |
| Canary only | `["include/general","exclude/singular/page/6065"]` | `["include/singular/page/6065"]` |
| Global clone | `[]` | `["include/general"]` |
| Rolled back | `["include/general"]` | `[]` |

Elementor’s on-site condition encoding is proven by `include/general` and another site document using `include/singular/page/{ID}`. Elementor supports Include/Exclude conditions but warns about conflicts; no-condition site parts are non-live. [Elementor conditions](https://elementor.com/help/conditions/), [Theme Builder](https://elementor.com/help/the-elementor-theme-builder/).

Use the authenticated library REST route and array values—not manually serialized PHP strings. Each write must have:

- An 8-second request timeout.
- Exact before-state assertion.
- HTTP-success assertion.
- Immediate REST read-back.
- Exact array equality.
- Theme Builder UI/actual page-resolution verification.

If `_elementor_conditions` is not writable or Elementor does not resolve the REST-written state, global execution is NO-GO.

### 3.5 Canary activation and partial-state recovery

Canary URL:

```text
https://twinsgaragedoors.com/clopay-gallery-steel/
WordPress page ID: 6065
```

1. Recheck all drift/concurrency gates.
2. Write original 36 to:

   ```json
   ["include/general","exclude/singular/page/6065"]
   ```

3. Within five seconds, write `HEADER_CLONE_ID` to:

   ```json
   ["include/singular/page/6065"]
   ```

4. Do not load or warm any public URL between the two writes.

If step 3 fails, immediately restore original 36 to `["include/general"]`; verify header clone remains `[]`. Do not clear caches until the safe fallback state is restored.

After both writes succeed, run the full invalidation routine below.

The canary must then render:

```text
exactly one header.elementor-location-header
data-elementor-id = HEADER_CLONE_ID
exactly one #menuhopin
nested saved section data-elementor-id = MENU_CLONE_ID
no elementor-36 or nested elementor-305 inside the canary header
```

Homepage and `/garage-door-services/` must still render exactly one header 36 and nested menu 305. A Canvas paid LP must remain chrome-free.

### 3.6 Invalidation routine after every condition-state change

Run after canary activation, canary reversal, canary reactivation, global cutover and rollback:

1. **Elementor:** Clear Files & Data; confirm the action succeeds.
2. **Clone CSS:** Require both active clone CSS URLs to return HTTP 200 and contain the clone root selectors.
3. **WP Rocket:** Dashboard → Clear and Preload Cache. This installed version may show no toast; require its admin-post request to complete `200 → redirect`, record the timestamp and wait for preload completion.
4. **a8c Edge:** Settings → Edge Cache → Clear Edge Cache. Leave edge caching enabled; record success/timestamp.
5. **Logged-in:** hard reload canonical canary/control URLs and verify document IDs.
6. **External anonymous:** request canonical URLs without cookies or `elementor-preview`; inspect `x-ac`/cache headers and document IDs. Require the correct result twice consecutively—first cold/warm request and a follow-up.
7. If stale or mixed output appears, restore the safe fallback condition state **before** attempting another purge.

### 3.7 Canary QA and rollback rehearsal

Run the complete §4 matrix in both contexts:

| Context | Requirement |
|---|---|
| Logged-in editor/browser | All widths; catches editor/runtime and admin-bar sticky behavior |
| External anonymous cold cache | All widths; authoritative for Rocket/a8c output |
| External anonymous warmed cache | Repeat page identity, nav, tracking and chat counts |

Then rehearse the reverse:

1. Write original 36 to `["include/general"]`.
2. Within five seconds, write header clone to `[]`.
3. If step 2 fails, return original 36 to the prior canary-exclusion state so only the clone remains on 6065; do not leave overlapping headers.
4. Run the full invalidation routine.
5. Prove 6065 is back to header 36/menu 305 anonymously and logged-in.
6. Time the database reversal and cache convergence. If the condition reversal is not seconds-fast or anonymous convergence is not bounded and repeatable, global cutover is NO-GO.
7. Reapply the canary state and repeat its identity checks before cutover.

### 3.8 Global cutover

Only during a low-traffic window with the owner watching and the rollback console/cache pages already open:

1. Recheck source/clone hashes, current conditions, revisions, menu ledger and event ledger.
2. From canary state, write header clone to:

   ```json
   ["include/general"]
   ```

3. Within five seconds, write original 36 to:

   ```json
   []
   ```

4. Do not fetch or warm public pages during this interval.

If step 3 fails, immediately compensate:

```text
header clone → ["include/singular/page/6065"]
original 36 remains → ["include/general","exclude/singular/page/6065"]
```

Then verify the recovered canary state and stop.

After both writes succeed, run the full invalidation routine and the complete regression matrix. Require controls beyond page 6065: homepage, normal service page, `/contact-us/`, a post and a Canvas LP.

### 3.9 Seconds-fast rollback

Trigger rollback on any §4 abort condition:

1. Write original 36 to:

   ```json
   ["include/general"]
   ```

2. Within five seconds, write header clone to:

   ```json
   []
   ```

3. If step 2 fails, return original 36 to `[]`; this restores the last-known global-clone state and eliminates overlap while the condition path is repaired.
4. Read back all three relevant documents:

   ```text
   36 → ["include/general"]
   HEADER_CLONE_ID → []
   305 → []
   MENU_CLONE_ID → []
   ```

5. Run the full invalidation routine.
6. Prove original header 36 and menu 305 on anonymous and logged-in controls.
7. Leave both clones published but conditionless for diagnosis. Do not import, rebuild or edit the originals.

The database condition reversal is the seconds-fast portion. Anonymous convergence is only as fast as the rehearsed Elementor/Rocket/edge invalidation; do not claim full rollback complete until external anonymous output is verified.

## 4. ABORT TRIGGERS + REGRESSION MATRIX

### Immediate abort/rollback triggers

- Wrong site root, post type, status, conditions, title, taxonomy, hash, revision or menu ledger.
- REST writes conditions but Theme Builder/page resolution does not honor them.
- More or less than one header, `#menuhopin`, popup, desktop menu or mobile menu.
- Canary clone appears on any URL except page 6065 before global cutover.
- Canary still contains header 36, menu 305 or `ct_saved_rows:"305"`.
- Any preserved element ID/class, label, dynamic tag, attribute, link or menu item is missing or changed.
- `Blog` wraps, nav becomes two rows, or logo overlaps navigation.
- Sticky fails; `.headershow` is not toggled; header jumps under the admin bar.
- Hamburger, submenu, Escape, close control, overlay close, focus return, internal scrolling or transient page scroll-lock differs from the original baseline.
- Mobile bar or chat blocks the menu; chat is missing, has two visible bubbles, uses the obsolete `69f8fbd...` ID, or adds a third loader.
- Phone, quote or HCP link differs or fires its observed analytics event zero/more than once.
- GA/Meta/Ads event ledger differs, including duplicate initialization.
- New console exception, CSS 404, failed UAEL/Elementor script, horizontal overflow, inaccessible control or unexpected layout shift.
- Anonymous external QA is unavailable.
- Any stale/mixed template output after one full invalidation. Restore safe conditions first; a second purge is diagnostic, not permission to leave the bad state live.

### Width and behavior matrix

| Width | Expected header mode | Required checks |
|---:|---|---|
| `320` | Responsive logo + POP MENU | Open fully; expand Wisconsin/21 cities; reach Blog; internal content scrolls; page behind stays fixed; close by X/ESC/overlay; no horizontal scroll; Call/Book bar usable |
| `390` | Responsive logo + POP MENU | Same as 320; chat and `#twx2-stickybar` do not cover trigger, menu links or close control |
| `768` | Responsive mode + mobile bar boundary | Exactly one variant; no 767/768 double render; safe-area padding; popup/bar z-index |
| `1024` | Responsive POP MENU | Correct logo, phone/quote row, submenu interaction and sticky behavior |
| `1200` | Last responsive width | Desktop nav hidden; popup shown; nav-fit media query not active |
| `1201` | First desktop/nav-fit width | Desktop nav shown once; computed item font `13px`, letter spacing `.25px`, 6px side margins |
| `1366` | Desktop/nav-fit | Eight top-level items one row; no logo/Locations overlap |
| `1599` | Last nav-fit width | Same computed fit values and one row |
| `1600` | Desktop, nav-fit rule off | Base clone typography applies; all eight items still fit |

Run at scroll position 0 and after scrolling. At every width require one header, correct clone/original post ID for the current phase and no horizontal document overflow.

### Navigation/link checks

- Compare desktop, hidden Elementor dropdown copy and UAEL mobile menu against the frozen menu-13 ledger.
- Parent order, child order, item IDs, labels and hrefs must match.
- Test keyboard focus/Enter for all top-level parents and at least one child in every submenu.
- Check active/current classes on page 6065.
- Validate every destination without changing menu data.
- `Services & Repair` remains `href="#"`; do not “fix” it during reskin.

### CTA and event ledger

Before cloning, record each original control under the same consent/cookie state:

```text
desktop phone
desktop quote
mobile POP phone
mobile POP quote
mobile Call bar
mobile HCP Book
one normal nav link
one submenu link
```

For each, record exact:

```text
dataLayer arguments
fbq queue/calls
Google Ads event name, send_to and parameters, if present
network endpoint/payload
navigation destination
```

Use Tag Assistant/data-layer instrumentation and intercept navigation safely. Do not submit forms, visit a production conversion-only thank-you page or manufacture a live ad conversion.

Expected passive baseline:

| System | Exact expectation |
|---|---|
| GA | One `gtag('config','G-XW0RGPTGSN')` and one `gtag('config','G-JR7LW39CND')`; compare page-view requests by measurement ID, not total `collect` count |
| Meta | One `fbq('init','554750209097175')` and one `PageView` |
| GTM | No `GTM-*` container in the archived baseline; any current container must be captured in the fresh ledger |
| Ads/click events | Exactly the fresh original count per control—one if the original fires one, zero if it fires none |
| Chat | Current loader count unchanged from the fresh baseline; exactly one visible/openable bubble |

Any count or parameter delta is rollback-worthy.

## 5. RESIDUAL RISKS

- The two-template condition switch is not transactional. The chosen order prevents a headerless global interval but can create a brief overlapping condition state. Rehearsal and five-second compensating writes are mandatory.
- Condition postmeta read-back does not by itself prove Elementor’s internal condition registry updated; rendered canary/control resolution is the gate.
- Cache clears can outlast the database reversal. “Seconds-fast rollback” applies to conditions, not anonymous convergence unless the rehearsal proves otherwise.
- Preserving element IDs does not preserve root `.elementor-36/.elementor-305` classes. Undiscovered external root selectors will miss the clones.
- The supplied baseline is stale for dynamic menu and chat state. The fresh pre-change menu/render/event ledger is authoritative.
- Main currently double-loads the same current LeadConnector widget via two owners. This unit must not “clean up” that debt; require one visible bubble and no added loader.
- Body scroll lock is implemented by delayed UAEL runtime code, not exported Elementor JSON. Test behavior after WP Rocket releases delayed scripts.
- `#twx2-stickybar` sits at z-index `99998`; legacy UAEL modal layers may be lower. A visually correct header can still leave the Book bar over the open menu.
- Every element contains legacy `display_condition_list` subscriber records even though the anonymous baseline renders. Preserve them verbatim; do not reinterpret or normalize them.
- Both marketing-button widgets contain malformed trailing custom CSS (`10px; }`). Preserve it during this unit and add new scoped rules after it; cleanup belongs in a separate change.
- External Meta Event Setup or Ads click rules can bind by text/nesting without appearing in WordPress. Exact before/after event parity is the only safe proof.
- WP Rocket’s delayed JavaScript can make immediate cold-load interaction tests fail transiently. Test both before and after `rocket-allScriptsLoaded`.
- A live canary on page 6065 is customer-visible during the test window. Keep the window short and low-traffic.
- Another editor/menu change can invalidate the package mid-run. Recheck revisions, conditions, hashes and menu 13 immediately before each mutation.
- If REST cloning cannot reproduce the document type/taxonomy, generated CSS and condition behavior exactly, do not fall back to an in-place edit: UNIT 1 remains NO-GO.