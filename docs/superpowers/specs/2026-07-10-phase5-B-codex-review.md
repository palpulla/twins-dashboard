## 1. SHOWSTOPPERS

- **Tracking ownership is unresolved.** The draft says GA4, Meta, and GHL are entirely outside Elementor, then repeats the research warning that GTM/Meta/GHL loaders are in the shared header ([draft](/Users/daniel/twins-dashboard/docs/superpowers/specs/2026-07-10-phase5-B-chrome-reskin-DRAFT.md:13), [research](/Users/daniel/twins-dashboard/docs/marketing/audits/2026-07-09-phase4-research/README.md:26)). The change log additionally shows GHL loading from snippet 7152 plus a phantom `wp_body_open` injection, while active forms engine 7326 is omitted from the draft’s inventory ([change log](/Users/daniel/twins-dashboard/docs/marketing/change-log.md:160)). There is no authoritative current dependency map.

- **DOM coupling is already proven.** A global script targets `#menuhopin`, which header 36 supplies ([rendered baseline](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase1/baseline-main-6065.html:110)). Snippet 7050 also targets the exact Elementor nav widget ID `15c4a1b` for the 1201–1599px fix ([snippet](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase2-menus/snippet-7050-after-p2fix.php:207)). Rebuilding those elements can silently remove sticky-header and nav-fit behavior even though WPCode remains untouched.

- **The targets are not site-qualified.** Archived main and `/wi` output both use IDs 36, 305, 1409, and 466 ([main](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase1/baseline-main-6065.html:277), [/wi](/Users/daniel/twins-dashboard/docs/superpowers/backups/2026-07-09-phase1/baseline-wi-6756.html:574)). An ID-only runbook can edit the wrong multisite site. Every target needs the site URL/blog ID, REST base, editor URL, title, type, conditions, consumers, and pre-edit hash.

- **Rollback is incomplete and slow.** Saving only `_elementor_data` omits display conditions, document/page settings, template metadata, status, dependency references, and other postmeta ([draft](/Users/daniel/twins-dashboard/docs/superpowers/specs/2026-07-10-phase5-B-chrome-reskin-DRAFT.md:35)). Re-importing, regenerating CSS, and clearing multiple cache layers is not a seconds-fast rollback.

- **There is no valid public release gate.** Logged-in QA bypasses the anonymous WP Rocket/Batcache/edge path, while anonymous verification is merely “pending” ([handoff](/Users/daniel/twins-dashboard/docs/superpowers/specs/2026-07-09-twins-web-program-handoff.md:16)). A global cutover cannot safely proceed without an anonymous external vantage point.

- **The test protocol can damage live systems.** Forms now create CRM records, chooser/chat/SMS activity, and emails. A previous test upsert modified a real GHL contact ([change log](/Users/daniel/twins-dashboard/docs/marketing/change-log.md:221)). No reserved test identity, notification containment, cleanup procedure, or non-recording ad-conversion test is specified.

## 2. THE KEY ASSUMPTION

The assumption is only partially true. Tag storage may be separate from Elementor; tag behavior is not.

Concrete break paths:

- WPCode can attach listeners to an Elementor ID/class. New Elementor IDs mean the listener finds nothing.
- An Elementor button can contain an inline `onclick`, custom attribute, HTML widget, shortcode, or `dataLayer` push that disappears during rebuild.
- Google Ads click tracking can require the button or phone link to call `gtag_report_conversion`; preserving only the destination URL is insufficient. [Google’s documented click-conversion pattern](https://support.google.com/google-ads/answer/6331304?hl=en-EN) explicitly couples tracking code to the clicked element.
- Meta point-and-click event rules live outside WordPress. Button text, IDs, classes, or nesting can change while the base pixel still reports PageView. Meta supports these external Event Setup rules through Events Manager. [Meta pixel setup](https://www.facebook.com/help/messenger-app/952192354843755)
- Phone-number insertion or click tracking may depend on `tel:` format, visible text, or anchor structure. Replacing an anchor with a JS button can break it.
- GHL may auto-mount under `body`; new `overflow`, transforms, z-index, or overlays can hide or block the bubble even though its loader fires. The existing double-load increases uncertainty.
- Cloning a template containing old HTML/tag widgets could duplicate GA, Meta, or chat events.
- Listeners attached once to old DOM nodes do not automatically follow replacement nodes.

Inspect first:

| Document | Required inspection |
|---|---|
| **36 header** | Conditions; `#menuhopin`; `secHEADER/topHEADER/botHEADER`; `.headerCALL/.headerBTN/.headerMENU/.popMENU`; widget `15c4a1b`; custom CSS/attributes/HTML; exact phone, quote, and HCP links; reference to 305. |
| **305 menu** | Every consumer; dynamic WP menu source; UAEL modal classes; all IDs/classes/hrefs; nested dropdown behavior; focus, Escape, overlay close, and scroll contract. |
| **1409 footer** | Conditions; nested shortcodes/templates—especially 466; all quote, phone, mail, social, NAP, and footer links; desktop/mobile variants. |
| **2179 alt footer** | Exact `/contact-us/` condition; KY-versus-main phone expectation; nested templates; form/chooser overlap; custom HTML and selector-bound tracking. |
| **466 widget** | Every embedding; widget `245d65a2`; both CTA DOM shapes; attributes/listeners; exact events and destinations. |

For all five, capture full Elementor data/settings/conditions, rendered HTML, event listeners, computed z-index/overflow, and a before-network baseline. Separately inspect every active WPCode body and condition, Elementor Custom Code, `wp_body_open`, the phantom GHL source, Google Ads conversion configuration, and Meta Events Manager rules.

## 3. SEQUENCING & ROLLBACK

“Lowest-risk-first on production” is wrong. Footer 1409 is global and embeds conversion widget 466; it is not merely links and NAP. Header 36 and menu 305 should not be changed simultaneously, but they also cannot be cloned independently because 36 references 305.

Safer sequence:

1. Reconcile dependencies and create a site-qualified manifest.
2. Duplicate the complete dependency pair: 36+305 and 1409+466. Leave duplicates without live display conditions.
3. Reskin duplicates only.
4. Canary them on one dedicated URL using an explicitly tested condition arrangement and an anonymous external device.
5. Rehearse cutover and rollback, including WP Rocket, Elementor files/data, and a8c Edge clearing.
6. Cut over during a low-traffic maintenance window, one dependency unit at a time.
7. Handle 2179 separately. Do B2 only after B1 stabilizes. Move B3 to its own exact plan/session.

Elementor documents that a site part without conditions is effectively non-live and warns when conditions conflict; it does not document an atomic two-template swap. Therefore, the installed-version swap behavior must be rehearsed rather than assumed. [Theme Builder](https://elementor.com/help/the-elementor-theme-builder/), [display conditions](https://elementor.com/help/conditions/)

Seconds-fast rollback requires:

- The original template and dependency documents remain completely untouched.
- Their original display conditions are preserved and preloaded for restoration.
- The condition switch-back is rehearsed and requires no import or editing.
- Exact abort triggers are defined: missing header/nav/chat, wrong tag ID/count, dead CTA, new console error, or failed form delivery.
- Cache-clearing actions are open and ready.

If a conflict-free condition swap and rollback cannot be demonstrated, do not promise seconds-fast rollback and do not perform the global cutover. Elementor also notes that regenerated files and caches can cause front-end/editor discrepancies. [Elementor cache guidance](https://elementor.com/help/regenerate-css-data/)

## 4. REGRESSION CHECKLIST GAPS

Add:

- Exact GA measurement ID, event name, parameters, and exactly-once count—not merely “some `g/collect` request.”
- Exact Meta pixel ID and expected event. PageView does not prove Lead.
- Exact Google Ads conversion ID/label, trigger, and `send_to`; use Tag Assistant/instrumentation rather than casually generating live conversions.
- CTA-specific first-click tests for header/footer phone, quote, Book Now, mobile bar, and navigation.
- No duplicate GA, Meta, GHL, or forms-engine initialization.
- Preservation of `gclid`, `gbraid/wbraid`, `fbclid`, UTMs, cookies, and form payload attribution.
- E2E form outcome: GF entry, email, `lp_leads` status, Dunzo contact, chooser cookie, chat/SMS—with a dedicated owned identity and cleanup record.
- GHL message send/arrival, not just “widget opens.”
- Route matrix: home, normal service page, injected-library page, contact/2179, design funnel, `/thank-you/`, Canvas paid LPs, post, search/404.
- Confirm paid LPs remain chrome-free where intended.
- Raw and rendered phones/NAP/schema, including dynamic rewrites.
- Real iPhone/Android testing plus 320, 390, 768, 1024, 1200, 1366, and 1600px; fully expanded mobile menu, keyboard/focus, and body-scroll behavior.
- Chat/mobile-bar/menu/footer z-index and pointer-event collisions.
- Logged-out cold-cache verification after WP Rocket, Elementor, and a8c Edge invalidation.
- Post-cutover monitoring and an immediate rollback owner.

The `/wi/thank-you-g-ppc-lp` check does not validate a main-only template change and can send a real conversion request. Replace it with a site-correct, non-recording validation method.

## 5. GO / NO-GO

**NO-GO as written.**

Do not proceed until:

1. Tracking/chat ownership and all DOM selectors are mapped.
2. Every target is site-qualified with exact dependencies and phone expectations.
3. Full-document backups and a clone/canary rollback drill succeed.
4. Anonymous external QA is available.
5. Conversion tests use exact event assertions and a safe test identity.
6. B3 is removed into a separate, fully enumerated plan.

After those modifications, this can become a controlled proceed—not an edit-in-place experiment on the live global header.

Cross-model review was offered but not run because no external CLI was authorized; findings are based on direct evidence plus two fresh-context adversarial reviews.