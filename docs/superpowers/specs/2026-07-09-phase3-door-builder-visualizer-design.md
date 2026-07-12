# Phase 3 — Twins-owned door-builder visualizer (design)

Date: 2026-07-09. Approved by Daniel in-session (approach A). Parent program: `2026-07-09-twins-web-program-handoff.md`.

## Investigation facts (settle the design space)

- Clopay Product API v2 (public, no key, CORS `*`) exposes exactly two content endpoints — `GetProductsList/GetProducts?productType=Residential` (23 products: ProductId, Title w/ HTML sup tags, ShortDescription, ShowcaseImage) and `GetProductDetails/GetProductData?productId={id}` — plus an image-gallery iframe embed. **No configuration-render endpoint exists** (that is Clopay's private Door Imagination System).
- Per product, GetProductData provides renderable assets: `ProductDesigns` (4-9 real door renders, each in ONE color only — e.g. all Modern Steel designs are black), `Colors` (9-24 swatch images, GroupName Standard/Premium), `TopSections` (window styles w/ thumbnails, grouped), `SpecialityGlassOptions` (0-6), `ProductImageGallery`, plus `ColorDisclaimer`/`TopSectionDisclaimer`/`DesignDisclaimer` strings.
- Consequence: the honest owned visualizer is a **configurator with real Clopay assets** (design render as hero, color as labeled swatch, window thumbnails) — not a fake photoreal composite. Canvas recoloring was considered and rejected (fabricated color fidelity; wrong for wood-grain finishes).

## What ships (v1)

### 1. Builder snippet — one new WPCode snippet per site (main + /wi)

"Twins Door Builder (visualizer)" — PHP snippet, Auto Insert Run Everywhere, registering shortcode `[twins_door_builder]` that outputs the app shell, scoped CSS, and JS. No build tooling — hand-written vanilla JS/CSS in the snippet, same as the house pattern.

- **CSS:** `.twxdb-` class prefix; colors via the existing `var(--tw-navy)` / `var(--tw-yellow)` tokens (also keeps the main site's RUCSS safelist filter matching). Mobile-first; desktop = wider grid, no separate layout.
- **Data:** client-side fetch straight from the Clopay API (CORS `*`), `sessionStorage` cache per product. If the list fetch fails (timeout 10s), the app collapses to a plain contact form with a short apology line — lead capture must never depend on Clopay uptime.
- **Steps** (progress dots; back button; a persistent "Skip to quote →" link on every step):
  1. **Collection** — grid of 23 cards (ShowcaseImage, Title, ShortDescription), ordered as the API returns them; 2-col on mobile.
  2. **Design** — ProductDesigns cards; the selected design's render becomes the persistent hero image for the rest of the flow. If a product has no designs, skip the step.
  3. **Color** — swatch chips grouped Standard / Premium with color names; selected swatch + name shown beside the hero with the caption "Colors shown as manufacturer swatches — you'll see your exact door at your free on-site consult." Render ColorDisclaimer if non-empty.
  4. **Windows** — TopSections thumbnails grouped by GroupName, plus a built-in first option "No windows (solid)". Render TopSectionDisclaimer if non-empty.
  5. **Glass** — only if a window was chosen AND SpecialityGlassOptions is non-empty; otherwise skipped.
  6. **Summary + quote** — hero render, list of selections, then form: name, phone, email, zip, optional notes; honeypot field. Submit → inline thank-you state (no redirect).
- **HTML in API strings:** Titles/descriptions may contain `<sup>`, `<br>`, `®`/`™` entities. Render after sanitizing to a whitelist (`sup`, `br` only; strip everything else). Plain-text versions (tags stripped) go in the lead payload.
- **Images:** native `loading="lazy"` EXCEPT anything that WP Rocket might see server-side — the app renders entirely client-side after page load, so Rocket's lazy-load rewriting (the Phase 1 gallery bug) does not apply; still add `data-no-lazy="1"` on the hero img for safety.

### 2. Lead endpoint extension (main snippet 7072)

`POST /wp-json/twins/v1/door-builder` gains OPTIONAL sanitized text fields: `collection`, `design`, `color`, `windows`, `glass`, `notes` (each `sanitize_text_field`, max 200 chars). When any are present the email body gets a "Door configuration" block. Everything else (region tag, honeypot, recipient contact@twinsgaragedoors.com) unchanged; existing /design-your-door forms keep working with no changes (fields optional → backward compatible). Builder on /wi posts to the same main-site endpoint with region field, exactly as the /wi funnel form does today.

### 3. New pages `/door-builder/` on main + /wi

Built natively per subsite with the proven Elementor builder-JS technique (`twx-page-builder.js` record), on the twx visual language:

1. Compact hero: H1 "Design Your New Garage Door" (main) / same + Wisconsin flavor allowed (/wi), one-line subhead, region phone CTA (main (833) 833-2010, /wi HTML (608) 888-8785 — the site-wide runtime script rewrites /wi display to (608) 925-2038, expected).
2. Shortcode widget: `[twins_door_builder]`.
3. Navy CTA band (call + free quote), same as other twx pages.
- Astra page title disabled via `{"meta":{"site-post-title":"disabled"}}` (single H1). Rank Math title/meta set per site via the updateMeta REST route. Pages indexable (unique conversion page, not noindex).
- No external Clopay links in the builder UI (keep visitors on-site). Dealer propId irrelevant here (no where-to-buy link in v1).

### 4. Funnel redirect swap

`/design-your-door/` pages: post-submit JS redirect changes from ezdoor.clopay.com to the builder — main 7073 → `/door-builder/`, /wi 6756 → `/wi/door-builder/`. **/ky 6386 keeps the EZDoor redirect** until Phase 8 gives /ky its builder page. Swap happens ONLY after the builder lead flow is E2E-verified on both sites.

## Error handling

- Clopay list/product fetch failure or timeout → fallback plain contact form (same submit endpoint), message "Our door catalog is taking a break — leave your details and we'll design it together."
- Submit failure (non-2xx) → inline error + the region phone number as the escape hatch.
- Missing asset arrays (empty ProductDesigns/Colors/etc.) → step auto-skips; summary lists only chosen attributes.

## Verification

- E2E on live, both sites: walk all steps (including a no-windows path and a skip-to-quote path), submit a clearly-marked test lead (name "TEST — Claude Phase 3", note asks to ignore), confirm endpoint 200 + email arrival in Daniel's inbox (Gmail search) or at minimum the endpoint success response; then swap the funnel redirects and verify each funnel form still emails + now lands on the builder.
- Desktop + mobile screenshots of each step on both sites.
- Throttle ≥6s on page fetches; anonymous checks still blocked by BlogVault (pending Daniel's whitelist) → logged-in verification.

## Reversibility

- New snippets: toggleable off (page shows nothing where the shortcode was — page can also be unpublished). Deployed code committed to `docs/superpowers/backups/2026-07-09-phase3-door-builder/`.
- Endpoint 7072 edit: before/after copies committed; revert = paste back prior body (backward-compatible change, so revert is safe any time).
- Redirect swap: single JS URL per page, before/after Elementor revisions + committed page snapshots; revert = restore EZDoor URL.
- Pages: unpublish to revert.
- Change-log entries with revert paths for every item.

## Out of scope (v1)

Canvas recoloring; /ky deployment (Phase 8); GA4/pixel events on builder steps; saving/sharing designs; pricing display (house rule: no fabricated prices); gallery iframe inside the builder; GHL CRM record (email lead only, matching the existing funnel).
