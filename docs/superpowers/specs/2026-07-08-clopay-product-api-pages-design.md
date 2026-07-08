# Clopay Product Page API → Live Product Pages — Design

**Date:** 2026-07-08
**Status:** Approved design, ready for implementation plan
**Sites:** twinsgaragedoors.com (main) + /wi + /ky (WordPress multisite, SiteGround)
**Related:** workstream 2 of [[project_clopay_door_builder]]; the EZDoor lead funnel is the separate spec `2026-07-08-clopay-door-builder-landing-design.md`.

## Goal

Replace Twins' hand-built, static Clopay product pages with pages whose content
is pulled live from Clopay's official **API v2**, so models, colors, images,
specs, and brochures stay current automatically, and route Clopay "Where To Buy"
traffic so Twins is the only dealer shown.

## Approach: server-side render + cache (chosen)

Considered three approaches:
- **A. Server-side render + cache (CHOSEN)** — WordPress fetches the Clopay API
  server-side, caches the JSON in a transient, and outputs real HTML via a
  shortcode. Best for SEO (Google sees full HTML), fast, current. Existing URLs
  and Rank Math meta are preserved so current rankings are protected.
- **B. Live client-side JS** — rejected: content is injected in the browser, so
  Google sees less of it, risking the rankings these pages already hold. Bad for
  an SEO-driven site.
- **C. Augment existing pages only** — rejected for v1: keeps core copy manual,
  so it does not deliver the "auto-updating" value; can revisit if A proves heavy.

## Verified API facts (tested live 2026-07-08)

Base: `https://www.clopaydoor.com/api/v2/`. **Public, no API key.** Responses are
JSON. **CORS `access-control-allow-origin: *`** (client-side would work, but we
chose server-side for SEO). Endpoints:

| Purpose | Method + URL |
|---|---|
| List products | `GET /api/v2/GetProductsList/GetProducts?productType=Residential` (or `Commercial`) |
| Full product details | `GET /api/v2/GetProductDetails/GetProductData?productId={id}` |
| Selected fields | `POST /api/v2/GetProductDetails/GetProductDataByField` (JSON body of `productId` + boolean field flags) |

Confirmed sample: `GetProductData?productId=170` → Title "Modern Steel™", 24
Colors, 13 ProductImageGallery items, `ImageGallery` iframe URL
`https://www.clopaydoor.com/image-gallery/modern-steel`. Residential list returns
23 products.

## Product mapping (Twins page → Clopay productId, verified)

| Twins page (all three sites) | Clopay product | productId | Clopay canonical |
|---|---|---|---|
| /clopay-modern-steel/ | Modern Steel™ | **170** | /modernsteel |
| /clopay-gallery-steel/ | Gallery® Steel | **12** | /gallerysteel |
| /clopay-classic-collection/ | Classic™ Steel | **13** | /classic |

(All three are Residential doors. The full productId reference table lives in the
Clopay API v2 guide, `~/Downloads/INST-API_EN (2).pdf`, pages 27-30.)

## Architecture

### 1. Fetch + cache layer (one WordPress mechanism)
- A **WPCode PHP snippet** (or small mu-plugin) provides a function that calls a
  Clopay endpoint via `wp_remote_get` and caches the decoded JSON in a **transient**
  keyed by endpoint+arg (e.g., `clopay_product_170`), **TTL 24h**.
- On a cache hit, return cached data (no network call). On miss, fetch, cache,
  return. On fetch failure, return the last-good transient if present.
- A daily **WP-cron** event warms/refreshes the transients for the mapped product
  IDs so visitors never trigger a cold fetch.

### 2. Shortcode renderer
- Register a shortcode, e.g. `[clopay_product id="170"]`, that reads the cached
  product data and outputs server-side HTML for these sections (Residential):
  - **Title / ShortDescription / Description**
  - **Overview** and **Construction** (Residential rich-text HTML blocks)
  - **Colors** grid (swatch image + name, from `Colors[]`)
  - **Product designs / material options / glass options / top sections** where present
  - **Image gallery** via the provided `ImageGallery` iframe (see below)
  - **Brochures + Installation & Care** document links (`ProductBrochures[]`,
    `ProductInstallationAndCare[]`) and **SpecsModels** doc links
- Clopay rich-text fields already contain HTML; output them wrapped in a scoped
  container. Escape/attribute-guard the plain fields (titles, alt text).

### 3. Image gallery iframe (per the guide)
- Build `<iframe src="{ImageGallery}" width="480" height="380"
  style="border:0; max-width:100%;">`. For mobile responsiveness add
  `aspect-ratio: 24/19; height:auto;` (guide note: may not apply on iOS >14.8; set
  a max-height fallback for small widths).

### 4. Page wiring (Elementor)
- On each existing product page (all three sites), replace the static body with
  an Elementor Shortcode/HTML widget containing the matching `[clopay_product]`
  shortcode. **Keep the same page slug, H1, and Rank Math title/meta** so SEO
  equity carries over.

### 5. Where-To-Buy dealer lock
- Every link from these pages to clopaydoor.com (canonical product links, "see
  more / where to buy") appends **`?propId={TWINS_PROP_ID}`** so Twins is the only
  dealer shown in Clopay's locator. Example:
  `https://www.clopaydoor.com/where-to-buy?propId={TWINS_PROP_ID}`.
- `{TWINS_PROP_ID}` is a **pending input** from Clopay. Until provided, links omit
  the param (still valid, just not dealer-locked) and are updated in one place.

## Caching, freshness, and resilience

- **TTL 24h transient + daily cron refresh** balances Clopay's "content may change
  at any time" caveat against page speed and API load.
- **Fallback ladder** if the API is unreachable: last-good transient → if none,
  render a minimal safe block (product title + a Twins CTA + a link to the Clopay
  canonical page). The page must **never** render broken or empty.
- A manual "purge Clopay cache" action (delete the transients) for when Clopay
  pushes an update Twins wants reflected immediately.

## Multisite handling

- The fetch/cache/shortcode mechanism is **network-level** (mu-plugin or a WPCode
  snippet active on all three sites). Product **content is region-agnostic**
  (identical Clopay data), so unlike the EZDoor funnel there is no per-region
  content. The only shared value is `{TWINS_PROP_ID}` (one Twins dealer account).
- Transients are per-site by default; that is fine (each site caches independently).

## SEO preservation (explicit requirement)

- Do not change the three page URLs, H1s, or Rank Math titles/descriptions.
- Output is server-rendered HTML (not JS-injected) so Googlebot indexes it.
- After launch, confirm each page still returns its product content in raw HTML
  (view-source / curl), not just after JS.

## Out of scope for v1

- A dynamic product-index/listing page built from `GetProducts` (nice later).
- Adding product pages beyond the three that exist today.
- Commercial-door fields (Options/Models/FeaturesBenefits) — Twins' three pages
  are Residential.
- Any change to the EZDoor lead funnel (separate spec).

## Pending input

- **Twins dealer prop ID** for the `?propId=` Where-To-Buy lock (from the Clopay
  dealer portal/rep). Build ships without it and is upgraded in one place.

## Success criteria

- The three product pages render live Clopay content (title, overview,
  construction, colors, gallery, brochures) server-side, current within 24h of a
  Clopay change.
- Page URLs, H1s, and Rank Math meta are unchanged; raw HTML (pre-JS) contains the
  product content.
- If Clopay's API is down, pages still render a safe fallback, never broken.
- clopaydoor.com links carry `?propId=` once the prop ID is provided.
- All changes reversible (deactivate the snippet/shortcode; the prior static
  Elementor content is restorable from revisions).

## 2026-07-08 build revision — AUGMENT, not replace (approved by Daniel)

Discovered during the live build: the three existing Clopay pages
(/clopay-modern-steel, /clopay-gallery-steel, /clopay-classic-collection on each
site) are NOT thin static product pages. They are rich, unique-content,
review-style SEO pages (H1 per door, plus "What We Like", "What can be improved",
"Quick Verdict", "Unboxing", "Documentation/User Guide", FAQ, local CTAs) with
titles like "BEST Clopay … Near You". Wholesale-replacing that content with the
generic Clopay API copy every dealer pulls would risk their rankings.

Decision (Daniel chose "Augment"): keep all existing unique content and ADD a
live Clopay section to each page. The shortcode gains a `mode` attribute:
`[clopay_product id="170" mode="specs"]` renders only the auto-updating, additive
value — official **Available Colors** swatches, the **photo-gallery iframe**,
**Brochures & Documents** (brochures + install/care), and the **Where-To-Buy
dealer-lock CTA** — and deliberately OMITS Clopay's generic Overview/Construction
prose so the page's unique content stays primary. `mode="full"` remains available
for any future standalone page.

Live state as of this revision: WPCode PHP snippet **"Twins x Clopay Product API"
(snippet ID 7050)** is created and **active on the main site** (Auto Insert / Run
Everywhere). Verified: the shortcode renders live Clopay data server-side on a
Twins-branded page (title, overview, construction, gallery carousel, color
swatches, brochures). Snippet then upgraded to add `mode="specs"` + swatch CSS.

## Risks

- **Clopay "recommends linking, not cloning" caveat (guide p.2).** We are
  incorporating their API content (the doc's stated purpose) and keep it live via
  cache+refresh, not a frozen copy. Acceptable and sanctioned, but keep TTL modest
  and always link back to the Clopay canonical.
- **External dependency on a live marketing page.** Mitigated by the transient +
  daily cron + fallback ladder.
- **Rich-text HTML from the API** is Clopay-authored (trusted) but still wrapped in
  a scoped container to avoid layout bleed.
