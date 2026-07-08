# Clopay Product Page API → Live Product Pages — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Render Twins' three Clopay product pages (Modern Steel, Gallery Steel, Classic Steel) from Clopay's API v2, server-side and cached, across all three multisite properties, preserving existing URLs and SEO.

**Architecture:** A WordPress PHP layer (WPCode snippet or mu-plugin) fetches Clopay API v2 server-side, caches JSON in 24h transients (warmed by a daily cron), and exposes a `[clopay_product id="…"]` shortcode that outputs server-rendered HTML. Each existing Elementor product page embeds the matching shortcode; page slug/H1/Rank Math meta stay unchanged. Links to clopaydoor.com carry `?propId=` to lock the Where-To-Buy locator to Twins.

**Tech Stack:** WordPress multisite (SiteGround) + WPCode (PHP snippets) + Elementor + WP Rocket; Clopay API v2 (`https://www.clopaydoor.com/api/v2/`, public, no key, CORS `*`). Build/verify via the Chrome admin session and `curl`.

**Reference spec:** `docs/superpowers/specs/2026-07-08-clopay-product-api-pages-design.md`
**Reference API guide:** `~/Downloads/INST-API_EN (2).pdf`

**Execution note:** "Tests" are acceptance checks against the live API and live pages (`curl` for raw pre-JS HTML, page loads, a simulated-outage fallback check). There is no pytest. All changes reversible: deactivate the WPCode snippet, restore Elementor revisions.

---

## Verified facts (2026-07-08)

- `GET /api/v2/GetProductDetails/GetProductData?productId=170` → 200, Modern Steel™, 24 Colors, 13 gallery items, `ImageGallery=https://www.clopaydoor.com/image-gallery/modern-steel`.
- Product IDs: Modern Steel **170**, Gallery Steel **12**, Classic Steel **13** (all Residential).
- API is public (no key) and sends `access-control-allow-origin: *`. We still render server-side for SEO.

---

## Task 1: Fetch + cache function (WPCode PHP snippet)

**Systems:** WordPress → WPCode → Add Snippet (PHP), name "Clopay API — fetch+cache". Run everywhere, network-active for multisite.

- [ ] **Step 1: Acceptance-first**

Expected: calling `twins_clopay_get_product(170)` returns a decoded array with key `Title` = "Modern Steel™", served from a 24h transient after the first call, and returns the last-good cached array if a later fetch fails.

- [ ] **Step 2: Write the snippet**

```php
<?php
// Clopay API v2 fetch + cache. Returns decoded product array or null.
function twins_clopay_get_product( $product_id ) {
    $product_id = (int) $product_id;
    $key = 'twins_clopay_prod_' . $product_id;
    $cached = get_transient( $key );
    if ( $cached !== false ) {
        return $cached;
    }
    $url = 'https://www.clopaydoor.com/api/v2/GetProductDetails/GetProductData?productId=' . $product_id;
    $resp = wp_remote_get( $url, array( 'timeout' => 10 ) );
    if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
        $last = get_option( 'twins_clopay_lastgood_' . $product_id );
        return $last ? $last : null; // fallback ladder
    }
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( ! is_array( $data ) || empty( $data['ProductId'] ) ) {
        $last = get_option( 'twins_clopay_lastgood_' . $product_id );
        return $last ? $last : null;
    }
    set_transient( $key, $data, DAY_IN_SECONDS );
    update_option( 'twins_clopay_lastgood_' . $product_id, $data, false ); // durable last-good
    return $data;
}
```

- [ ] **Step 3: Activate + acceptance check**

Activate the snippet. Temporarily add `error_log( wp_json_encode( array( 'title' => twins_clopay_get_product(170)['Title'] ?? 'NULL' ) ) );` in a scratch snippet, load any admin page, and check the debug log.
Expected: log shows `"title":"Modern Steel™"`. Remove the scratch line.

- [ ] **Step 4: Record** the snippet ID in the build log (Task 10).

---

## Task 2: Shortcode renderer

**Systems:** Same WPCode snippet (append) or a second snippet "Clopay API — shortcode".

- [ ] **Step 1: Acceptance-first**

Expected: a page/post containing `[clopay_product id="170"]` renders, server-side, a container with the product title, overview, construction, a color-swatch grid, the gallery iframe, and brochure links.

- [ ] **Step 2: Write the shortcode**

```php
<?php
function twins_clopay_product_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'id' => '' ), $atts );
    $p = twins_clopay_get_product( $atts['id'] );
    if ( ! $p ) {
        // Safe fallback: never render broken/empty.
        return '<div class="twins-clopay"><p>Explore this Clopay door. <a href="https://www.clopaydoor.com/where-to-buy">Talk to Twins about it.</a></p></div>';
    }
    $prop = defined('TWINS_CLOPAY_PROPID') && TWINS_CLOPAY_PROPID ? '?propId=' . rawurlencode( TWINS_CLOPAY_PROPID ) : '';
    ob_start(); ?>
    <div class="twins-clopay">
      <h2 class="twins-clopay-title"><?php echo wp_kses_post( $p['Title'] ); ?></h2>
      <?php if ( ! empty( $p['ShortDescription'] ) ) : ?>
        <p class="twins-clopay-lead"><?php echo esc_html( $p['ShortDescription'] ); ?></p>
      <?php endif; ?>
      <?php if ( ! empty( $p['Overview'] ) ) : ?>
        <div class="twins-clopay-overview"><?php echo wp_kses_post( $p['Overview'] ); ?></div>
      <?php endif; ?>
      <?php if ( ! empty( $p['Construction'] ) ) : ?>
        <div class="twins-clopay-construction"><?php echo wp_kses_post( $p['Construction'] ); ?></div>
      <?php endif; ?>
      <?php if ( ! empty( $p['ImageGallery'] ) ) : ?>
        <div class="twins-clopay-gallery">
          <iframe src="<?php echo esc_url( $p['ImageGallery'] ); ?>" width="480" height="380"
                  loading="lazy" style="border:0; max-width:100%; aspect-ratio:24/19; height:auto;"
                  title="<?php echo esc_attr( wp_strip_all_tags( $p['Title'] ) ); ?> gallery"></iframe>
        </div>
      <?php endif; ?>
      <?php if ( ! empty( $p['Colors'] ) && is_array( $p['Colors'] ) ) : ?>
        <div class="twins-clopay-colors">
          <h3>Colors</h3>
          <ul class="twins-clopay-swatches">
            <?php foreach ( $p['Colors'] as $c ) : ?>
              <li>
                <img src="<?php echo esc_url( $c['ProductImage'] ?? '' ); ?>" alt="<?php echo esc_attr( $c['AlternativeText'] ?? ( $c['Title'] ?? '' ) ); ?>" loading="lazy" width="64" height="64">
                <span><?php echo esc_html( $c['Title'] ?? '' ); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php if ( ! empty( $p['ProductBrochures'] ) && is_array( $p['ProductBrochures'] ) ) : ?>
        <div class="twins-clopay-docs">
          <h3>Brochures</h3>
          <ul>
            <?php foreach ( $p['ProductBrochures'] as $d ) : ?>
              <li><a href="<?php echo esc_url( $d['Url'] ?? '#' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $d['Title'] ?? 'Brochure' ); ?> (<?php echo esc_html( $d['Extension'] ?? '' ); ?>)</a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <p class="twins-clopay-cta">
        <a href="<?php echo esc_url( 'https://www.clopaydoor.com/where-to-buy' . $prop ); ?>" target="_blank" rel="noopener">See this door in Clopay's Where To Buy</a>
      </p>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'clopay_product', 'twins_clopay_product_shortcode' );
```

- [ ] **Step 3: Add scoped CSS** (WPCode CSS snippet or the same page) so swatches wrap and the block fits mobile:

```css
.twins-clopay-swatches{display:flex;flex-wrap:wrap;gap:12px;list-style:none;padding:0}
.twins-clopay-swatches li{width:80px;text-align:center;font-size:12px}
.twins-clopay-swatches img{border-radius:6px;width:64px;height:64px;object-fit:cover}
.twins-clopay{max-width:100%;overflow-x:hidden}
```

- [ ] **Step 4: Acceptance check on a scratch page**

Create a Draft page with body `[clopay_product id="170"]`, preview it.
Expected: renders Modern Steel title, overview, construction, a wrapping swatch grid, the gallery iframe, and brochure links. No PHP errors. Delete the scratch page.

---

## Task 3: Daily cache-refresh cron

**Systems:** Same snippet (append).

- [ ] **Step 1: Write the cron warmer**

```php
<?php
add_action( 'twins_clopay_refresh', function () {
    foreach ( array( 170, 12, 13 ) as $pid ) {
        delete_transient( 'twins_clopay_prod_' . $pid );
        twins_clopay_get_product( $pid ); // re-fetch + re-cache + update last-good
    }
} );
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'twins_clopay_refresh' ) ) {
        wp_schedule_event( time() + 300, 'daily', 'twins_clopay_refresh' );
    }
} );
```

- [ ] **Step 2: Acceptance check**

After saving, confirm the event is scheduled: install/trigger via WP-Crontrol or add a temporary `error_log( (string) wp_next_scheduled('twins_clopay_refresh') );`.
Expected: a future timestamp is returned (event scheduled). Manually run the hook once; confirm the three transients repopulate.

---

## Task 4: Wire the Modern Steel page

**Systems:** WordPress (main site) → Pages → /clopay-modern-steel/ → Elementor.

- [ ] **Step 1: Snapshot current SEO**

Record the current page H1, Rank Math title, and meta description (so we can confirm they are unchanged after).

- [ ] **Step 2: Replace body with the shortcode**

In Elementor, keep the header/hero and the existing H1. Replace the static product-detail body section with a Shortcode widget containing `[clopay_product id="170"]`. Leave the Twins phone CTA and any Twins-specific trust content in place.

- [ ] **Step 3: Publish + WP Rocket Clear and Preload Cache.**

- [ ] **Step 4: Acceptance check — SEO-safe server HTML**

Run: `curl -s "https://twinsgaragedoors.com/clopay-modern-steel/?nc=1" | grep -o "Modern Steel" | head`
Expected: "Modern Steel" appears in the **raw HTML** (proves server-side render, not JS-only). Confirm the H1 and Rank Math `<title>`/meta description are unchanged from Step 1.

- [ ] **Step 5: Acceptance check — visual + mobile**

Load the page desktop + mobile viewport. Expected: colors grid, gallery iframe, and content render; no horizontal scroll on mobile.

---

## Task 5: Wire the Gallery Steel page

**Systems:** main site → /clopay-gallery-steel/ → Elementor.

- [ ] **Step 1:** Snapshot current H1 + Rank Math meta.
- [ ] **Step 2:** Replace body with `[clopay_product id="12"]` (same pattern as Task 4 Step 2).
- [ ] **Step 3:** Publish + WP Rocket Clear and Preload Cache.
- [ ] **Step 4:** `curl -s "https://twinsgaragedoors.com/clopay-gallery-steel/?nc=1" | grep -o "Gallery" | head` → Expected: "Gallery" in raw HTML; H1/meta unchanged.
- [ ] **Step 5:** Visual + mobile check. Expected: renders, no horizontal scroll.

---

## Task 6: Wire the Classic Steel page

**Systems:** main site → /clopay-classic-collection/ → Elementor.

- [ ] **Step 1:** Snapshot current H1 + Rank Math meta.
- [ ] **Step 2:** Replace body with `[clopay_product id="13"]`.
- [ ] **Step 3:** Publish + WP Rocket Clear and Preload Cache.
- [ ] **Step 4:** `curl -s "https://twinsgaragedoors.com/clopay-classic-collection/?nc=1" | grep -o "Classic" | head` → Expected: "Classic" in raw HTML; H1/meta unchanged.
- [ ] **Step 5:** Visual + mobile check.

---

## Task 7: Replicate on /wi and /ky

**Systems:** the /wi and /ky subsites.

- [ ] **Step 1: Confirm the snippet is network-active**

The WPCode snippet from Tasks 1-3 must run on /wi and /ky too (network activation, or replicate the snippet on each subsite). Verify `[clopay_product id="170"]` renders on a /wi Draft page.
Expected: renders identically (content is region-agnostic).

- [ ] **Step 2: Wire the three product pages on /wi**

If /wi has its own /clopay-modern-steel, /clopay-gallery-steel, /clopay-classic-collection pages, replace each body with the matching shortcode (170/12/13), preserving each page's H1 + Rank Math meta. Publish + clear cache. If a given page does not exist on /wi, skip it and note in the build log.

- [ ] **Step 3: Wire the three product pages on /ky** (same as Step 2).

- [ ] **Step 4: Acceptance check**

`curl` each existing /wi and /ky product page with a cache-buster and grep for the product name in raw HTML. Expected: product name present server-side on each.

---

## Task 8: Fallback / resilience check

- [ ] **Step 1: Simulate an API outage**

Temporarily edit the fetch function URL to a bad host (e.g., `clopaydoor.invalid`) in a staging copy of the snippet, OR set the transient + last-good option to empty for productId 170 and block the host, then load /clopay-modern-steel/.
Expected: the page renders the **safe fallback block** (a short line + Twins CTA), never a PHP error or empty section. Restore the correct URL immediately after.

- [ ] **Step 2: Confirm last-good survives a failed refresh**

With a valid last-good option present, force a failed fetch. Expected: the page still shows full product content from last-good, not the minimal fallback.

---

## Task 9: End-to-end verification pass

- [ ] **Step 1:** For all wired pages across the three sites, `curl` raw HTML and confirm the product name renders server-side (SEO-safe).
- [ ] **Step 2:** Confirm every wired page's H1 and Rank Math title/description match the pre-change snapshots (no SEO regression).
- [ ] **Step 3:** Mobile viewport pass on each page (no horizontal scroll; gallery + swatches fit).
- [ ] **Step 4:** Reversibility check — deactivating the WPCode snippet makes the shortcode inert and the pages restorable via Elementor revisions. Note in the build log.

---

## Task 10: Record the build

**Files:** Modify `docs/marketing/change-log.md` (append a dated entry).

- [ ] **Step 1:** Append an entry dated 2026-07-08: the WPCode snippet(s) (fetch+cache, shortcode, cron) with IDs, the three product IDs wired (170/12/13), which pages were changed on each site, the SEO snapshots, and reversal steps (deactivate snippet, restore Elementor revisions).
- [ ] **Step 2: Commit**

```bash
git add docs/marketing/change-log.md
git commit -m "docs(web): log Clopay Product API v2 product-page build

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 11: Add the Where-To-Buy dealer lock (when Daniel provides the prop ID)

**Blocked on input:** Twins dealer prop ID from Clopay.

- [ ] **Step 1:** In the WPCode snippet, define the constant once: `define( 'TWINS_CLOPAY_PROPID', '1234' );` using the real prop ID. (The shortcode already appends `?propId=` when this is defined.)
- [ ] **Step 2: Acceptance check**

Load a wired page; inspect the "Where To Buy" link.
Expected: href is `https://www.clopaydoor.com/where-to-buy?propId={REAL_ID}`. Visiting it shows only Twins in Clopay's locator. Update the build log to mark the lock active.

---

## Self-review notes

- **Spec coverage:** fetch+cache (Task 1), shortcode render incl. overview/construction/colors/gallery/brochures (Task 2), daily refresh (Task 3), the three pages wired with SEO preserved (Tasks 4-6), multisite (Task 7), fallback ladder/resilience (Task 8), propId Where-To-Buy lock (Task 11), reversibility + change-log (Tasks 9-10). Out-of-scope items (product index, extra pages, commercial fields) intentionally omitted.
- **Type/name consistency:** `twins_clopay_get_product()`, transient key `twins_clopay_prod_{id}`, option `twins_clopay_lastgood_{id}`, cron hook `twins_clopay_refresh`, shortcode `clopay_product`, constant `TWINS_CLOPAY_PROPID` are used identically across all tasks.
- **Pending input** isolated to Task 11 (prop ID); everything else ships without it.
