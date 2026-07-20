# r8: blog redesign implementation plan (owner-approved)

Owner feedback: blog index looks old/outdated; articles must be full width
with relevant images. Image generation + featured-image assignment for all
187 posts is running separately (12 branded cluster illustrations via the
twins-media-generator skill; mapping CSV in scratchpad/blog-art-mapping.csv).

## Build steps

1. **Blog index template** (`twins-brand-experience/templates/blog-index.php`):
   brand hero ("Garage door answers from the Twins crew" + kicker + quote CTA),
   card grid (featured image via get_the_post_thumbnail_url passed in context,
   rewritten title, excerpt from new body first sentence, date), pagination.
   Renderer side: in `twins_overhaul_render_classified_content`, the posts
   index currently falls to `article`/editorial wrap of the legacy archive
   (chrome gate `twins_overhaul_is_allowed_chrome_request` already allows
   `is_home`, renderers.php:64). Add a `blog-index` branch: build a posts
   context array (id, title, url, date, excerpt, thumbnail url) in the
   overhaul layer (WP calls allowed there), pass to a new brand-core renderer
   `renderBlogIndex` (Experience + template). Respect paged queries.
2. **Article template**: articles ('article' kind) get a dedicated layout
   instead of the narrow editorial wrapper: featured image hero (full-bleed,
   1200x675), wide readable measure (~860px text column on a full-width
   shell), the new H2-structured bodies, related-services links block, final
   CTA. Either a new `article.php` brand template or an editorial variant
   gated on kind === 'article'. Keep exactly one H1 (post title).
3. **CSS** in twins-brand.css: blog card grid (3-col desktop / 1-col mobile,
   navy border + gold offset shadow cards, image top, 16:9 crop), article
   hero image treatment, wide article measure, pagination buttons.
4. **Tests**: renderers harness `blog-index` scenario currently asserts the
   legacy wrap — update to assert the new branded index (one H1, cards,
   no legacy `data-twins-original-content`). Contract tests: add template
   pins mirroring existing ones; templates.test one-main rule applies.
5. **Close**: css/js hash pins in site-unification.test.cjs, manifests repin
   to fixpoint (staging-runtime sorted by destination, host-verification by
   source; prerequisites pin deployed state), rotate transaction
   `staging-remediation-r8-20260717` in the 6 usual files, build:packages,
   contracts + legacy node suite + Playwright + check:repo all green, commit,
   deploy dry-run/capture/release, flush cache via site-tools-client, verify
   /blog/ + one article live in Chrome (authenticated), amend audit addendum.

## Verification targets
- /blog/ shows branded cards with images, no legacy Elementor chrome.
- Article pages full width with hero image; body typography clean.
- 187/187 posts show featured images (from the image agent's run).
- No console errors; suites green; origin still 401.
