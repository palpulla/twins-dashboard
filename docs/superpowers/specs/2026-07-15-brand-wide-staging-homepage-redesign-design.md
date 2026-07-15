# Brand-wide staging homepage redesign

Date: 2026-07-15

Status: design approved; awaiting written-spec review

Target: private staging only (`danielj140.sg-host.com`)

Production: read-only during this work; no production deployment in this scope

## Decision record

Daniel approved a brand-wide homepage rather than a Madison-first homepage. The approved direction is a complete functional rebuild using the strongest navy-and-gold visual language from the Madison repair landing page, with these required amendments:

- every primary estimate CTA says **Request a Quote**;
- the Twins logo is materially larger than the current staging logo;
- genuine Twins team photography appears prominently;
- both Twin characters remain visible and animate gently on desktop and mobile;
- a branded review slider uses real Twins Google reviews and remains compatible with normal Google-review refreshes after migration;
- navigation and calls to action are genuinely operable on staging, subject to the existing rule that staging must not send real leads or invoke production booking, messaging, analytics, or advertising integrations.

The alternatives rejected were a small patch to the current incomplete homepage and a Madison-LP clone. A patch would retain the present generic/dead-end feeling. A Madison clone would misrepresent a brand-wide root spanning active Wisconsin/Kentucky markets and the private Illinois preview.

## Problem being corrected

The current staging homepage is incomplete by construction:

- its header takes a special path that omits the main navigation;
- desktop mascots do not animate;
- real team photography is absent;
- the original page is sanitized into a closed legacy-content disclosure;
- quote/contact destinations are presented as visual artifacts rather than a complete interaction;
- existing tests assert markup presence and deployment-byte identity but do not prove that visible controls can be clicked or that navigation succeeds.

The redesign removes those deliberate limitations without weakening private-staging isolation.

## Experience architecture

### 1. Shared brand header

The homepage uses the same full navigation model as other brand pages; it no longer returns a home-only compact header.

Desktop header:

1. A slim gold utility rail shows **Choose your service area** and the high-contrast main number, (833) 833-2010. Regional pages use their configured regional number; the root page does not infer location or request browser geolocation.
2. A navy fascia contains the logo, the complete primary navigation, **Book Online**, and **Request a Quote**.
3. The expanded logo is at least 204 CSS pixels wide at 1201 pixels and above, compared with the current 160.63-pixel computed homepage width (74-pixel height at the 178:82 asset ratio). It overlaps the gold divider by 12 pixels and remains visually dominant without obscuring navigation.
4. The phone uses navy text on gold or gold/white text on navy; white text on a yellow background is prohibited.
5. The fascia compresses on scroll; the compressed desktop logo remains at least 180 CSS pixels wide, still larger than the current expanded logo, and the two primary actions remain readable.

Primary navigation groups are Services, Garage Doors, Service Areas, Resources, and About. About exposes Our Team and Careers. Internal URLs are generated from the active WordPress home URL, never hard-coded to staging or production.

Mobile header:

- The expanded logo floors are 190 CSS pixels at 1024, 176 at 768, 154 at 390, 148 at 360, and 140 at 320. These exceed the current computed 160.63-pixel width at 1024/768, 138.93 at 390/360, and 125.90 at 320 (current heights at the 178:82 asset ratio). It is never clipped.
- A real Menu button opens an accessible drawer containing the complete navigation plus Book Online and Request a Quote.
- The drawer traps focus while open, closes with Escape and its close button, restores focus to the trigger, and does not leave an invisible overlay intercepting the page after it closes.
- A compact sticky action bar contains Call Twins and Request a Quote without covering content or other controls.

The Book Online and Request a Quote treatments use the rounded shape, circular-arrow detail, restrained gold sheen, clear pressed/focus states, and one brief attention pulse every 8 seconds defined by the approved Motion Luxe design at `/Users/daniel/Documents/Codex/2026-07-10/im-workign-on-website-overhaul-with-2/docs/superpowers/specs/2026-07-10-twins-motion-luxe-design.md` (SHA-256 `42b7cfe3ba64e5c95dc035b79d0834c61719e94165dec68547637015aac36502`). The desktop and mobile top references are `outputs/motion-luxe-desktop-top.png` (SHA-256 `02198a170221af1ee6225397ab1858c92ae3c15f9d4a4e9309c65575809e1c60`) and `outputs/motion-luxe-mobile-top.png` (SHA-256 `54ad14a4e41284dce99e8abad18b1300748ec6354e565f78be0ac5cc60593d87`) under the same July 10 project. Motion stops when the control is focused, hovered, or when reduced motion is requested.

Display copy uses the self-hosted Lilita One face for the bold Madison-LP character and Nunito for body/interface copy. Remote Google Fonts requests are prohibited on staging and are unnecessary after migration because the font assets travel with the overhaul package.

### 2. Brand-wide hero

The root homepage represents the Twins brand across its enabled markets. It uses the headline **Garage Door Repair & Installation, Done Right Today**, supporting copy about fast local service and upfront answers, and visible Wisconsin and Kentucky pathways. On private staging it also displays a clearly labeled **Illinois preview** pathway. It does not present the company as Madison-only or imply that unpublished Illinois pages are public.

The hero contains:

- Call Twins and Request a Quote as the primary pair;
- both approved Twin character cutouts;
- the owned branded service-truck cutout behind the mascots as a supporting desktop visual and in the first proof panel immediately below the hero on mobile;
- the approved proof points **Same-day appointments**, **Upfront pricing**, and **Most repairs done in one visit**.

The Madison landing page supplies the visual language, not the geographic scope: deep navy garage-door texture, bold white/gold display type, branded characters, decisive spacing, and obvious conversion controls. A fixed market registry drives these pathways: staging enables `wi`, `ky`, and `il-preview`; production enables `wi` and `ky` by default and refuses to expose Illinois unless a separately authorized production configuration marks its site public.

### 3. Motion behavior

Both Twins are present at every supported viewport, including 320-pixel mobile. They receive distinct, subtle loops so they feel alive without distracting from the CTA:

- a 12-18-pixel slow float/bob;
- a small stagger between characters;
- a 1.5-degree staggered tilt on the rear character while the front character uses the vertical float only;
- 4.8-6.5-second durations with non-identical timing.

Desktop and mobile must show measurable movement. Under `prefers-reduced-motion: reduce`, the characters remain visible in an intentional static composition and all looping movement and CTA pulsing stops.

### 4. Real team photography

The page includes an open, visible **Meet the people behind Twins** section immediately after the principal service/trust content, not inside the legacy-content disclosure.

The lead image is the genuine crew/fleet photograph currently stored at:

`videos/twins-garage-doors-reel/capture/assets/best-garage-door-repair-installation-nea.jpeg`

It is a 2560x1372 JPEG with SHA-256 `7b961919cbd0fdff119864d29eb66b094aa1ca110fe71d581981ef4a1a780e23`. Its local provenance maps to the Twins production media-library source in `videos/twins-garage-doors-reel/capture/extracted/tokens.json`. Implementation copies the exact asset into the staging-overhaul asset package; it never hotlinks production.

The Our Team page also uses the 1066x1600 real Tal portrait at `twins-content-engine/assets/instagram/library/Team Photos/tal profile pic.jpeg` (SHA-256 `1e6f9052110a49e075ed2270c3b14253c1f9f5f8e8d16f9b7068c19d8356c87f`), and the homepage team story includes the 924x570 owned technician-at-work photograph at `/Users/daniel/Documents/Codex/2026-07-09/twins-garage-doors-brochure-redesign/outputs/assets/technician.png` (SHA-256 `a6de5842b51fa41449c02013c773c1910829a674b96f70586d12feadd1509b54`). Implementation copies these exact inputs into the portable brand package before use. Stock hiring imagery and placeholder employee portraits are prohibited. Every photograph receives meaningful alt text, intrinsic dimensions, a WebP derivative, and responsive `srcset` output while retaining its original source file and provenance record.

The section links to a rebuilt Our Team page. That page must show at least one genuine group image or two genuine staff portraits at both desktop and mobile sizes. Careers links to the stronger approved employment experience rather than a generic preserved WordPress page.

### 5. Branded Google review slider

The slider is fed by real Twins Google-review records from the existing Wisconsin Business Reviews Bundle collection. The canonical source is WordPress multisite path `/wi/`, Reviews page ID `2186`, shortcode `[brb_collection id="2178"]`, currently rendered at `https://twinsgaragedoors.com/wi/reviews/`. Review text, reviewer identity, star rating, and date are never fabricated or replaced with samples.

The integration boundary has three units:

1. `ReviewsProvider` returns a collection envelope with `businessReviewsUrl` plus normalized records using this fixed schema: `stableId`, `author`, integer `rating` from 1-5, exact `publishedDate` in `YYYY-MM-DD` form, verbatim `text`, optional provider-supplied `sourceRecordUrl`, and `recordSha256`. A relative date or a record without an exact calendar date is rejected rather than guessed.
2. The overhaul renderer accepts only those normalized records and produces Twins-branded cards.
3. Environment adapters obtain records without leaking provider-specific markup into the homepage.

The staging adapter reads a canonical JSON capture produced by a one-time, read-only export of the server-rendered collection. The manifest records `schemaVersion`, source URL, `businessReviewsUrl`, multisite path, page ID, collection ID, capture timestamp, complete source-response SHA-256, installed Business Reviews Bundle version, record count, and every normalized record/hash. The export utility accepts no caller-selected source, has no write method, stores no cookie/API credential, and refuses any source other than the fixed HTTPS production Reviews URL above. The committed capture must contain at least five provenance-verified records.

`recordSha256` is calculated over UTF-8 bytes beginning with `twins-review-v1\n`, followed in fixed order by `stableId`, `author`, decimal `rating`, `publishedDate`, `text`, and `sourceRecordUrl`. Each field is encoded as its decimal UTF-8 byte length, a colon, the unmodified field bytes, and a newline. The empty optional URL is encoded as `0:\n`. No whitespace, HTML, Unicode, punctuation, or review wording is normalized before hashing.

`stableId` uses the provider's review identifier when present. If the rendered provider output has no review identifier, it is lowercase SHA-256 over `twins-review-id-v1\n` plus the same length-prefixed encoding of `author`, decimal `rating`, `publishedDate`, and `text`; it never depends on carousel order or capture time.

At staging runtime, no shortcode executes and no plugin cache, database credential, Google endpoint, RichPlugins endpoint, or remote URL is consulted. Production credentials remain quarantined and both host- and WordPress-level outbound blocks remain unchanged. If the hash-verified JSON capture is absent, malformed, stale by more than 90 days, has fewer than five records, or fails any provenance check, the page shows only an unnumbered branded notice and internal Reviews-page link, and the staged redesign remains incomplete.

The production adapter resolves the exact `/wi/` multisite by fixed path, renders `[brb_collection id="2178"]` through the installed Business Reviews Bundle provider, normalizes its cached server output, restores the prior blog context, and fails closed if the fixed collection, exact-date fields, or expected DOM contract is unavailable. The business-level Google URL lives only in the collection envelope; `sourceRecordUrl` remains empty unless the provider supplies a distinct stable URL for that exact review. Before a migration can be approved, a production-mode harness must prove the adapter against a captured provider-output fixture and the migration manifest must record the installed plugin version. Any plugin-version or output-contract drift blocks migration pending an intentional fixture/parser update.

After migration, the staging-safety plugin and staging adapter are not deployed. The same portable renderer uses the production adapter, so Business Reviews Bundle owns its normal Google refresh credentials and schedule without changing homepage card markup or styling. This is the required meaning of “connected” and “works after migration”: staging proves the presentation with real, provenance-verified provider data, while production resumes the existing live review source.

Slider presentation:

- three cards at 1200 CSS pixels and wider, two from 768-1199, and one below 768;
- Google attribution, five-star display matching the record, reviewer name as supplied by the provider, date, and an unedited excerpt;
- previous/next buttons, position dots, keyboard support, and touch swipe;
- an internal Reviews-page link on private staging and a direct Google-reviews link only after the production adapter is active;
- auto-advance every 7 seconds, paused on hover, focus, pointer interaction, document invisibility, and reduced motion;
- no assertive live-region announcements during auto-advance;
- no Review or AggregateRating schema from the provider is copied or re-emitted by the slider; the existing production schema owner remains the only schema source.

### 6. Homepage section order

1. Full brand header
2. Brand-wide conversion hero
3. Gold trust ribbon
4. Repair / installation / opener service pathways
5. Branded Google review slider
6. Genuine team-and-fleet story
7. Door-design / door-builder preview
8. Wisconsin and Kentucky service-area selector with a clearly labeled private Illinois preview on staging
9. Careers invitation using the approved employment styling
10. Final Call Twins / Request a Quote closer
11. Full brand footer

This order keeps conversion proof near the top while giving real people, products, regions, and employment adequate space.

### 7. Supporting routes

The redesign includes the routes required to make the homepage journeys complete:

- **Our Team:** a branded introduction, genuine crew/fleet image, real Tal portrait, and company story. It does not invent employee names, positions, biographies, or portraits.
- **Careers:** the approved source is `/Users/daniel/Documents/Codex/2026-07-10/im-workign-on-website-overhaul-with-2/outputs/careers-widget.DEPLOYED.html` (SHA-256 `dfcf45ebf7a7e8a0467dd31a2a8937207cd36d5a9f226dc213937865a6512d0c`), with `/Users/daniel/Documents/Codex/2026-07-10/im-workign-on-website-overhaul-with-2/outputs/twins-employment-page-prototype.html` (SHA-256 `a2beb2e4c9bc10901db85f26057712fcbaf6aea3ceba88f0ce729e051a0611e9`) as the full-page visual reference. These files are visual/content references only: their real `form`, named fields, submit control, production asset URLs, `TWINS_ENDPOINT`, and `fetch()` implementation are neither executed nor copied. The staged route rebuilds the approved visual system with packaged owned imagery, functional internal section navigation, approved role/benefit content, and the same structurally non-submitting preview contract as the quote experience.
- **Contact / quote:** an accessible staged quote experience with complete field, validation, and error-state presentation. Its final `type="button"` action explains the staging block and cannot call mail, SMS, CRM, or another remote endpoint.
- **Reviews:** an internal branded review index using the same verified provider records as the homepage slider. On production, its Google source action resolves through the production adapter.

These routes share the same header, footer, type, color, CTA, provider, and responsive components rather than carrying isolated page-specific imitations.

## Interaction and migration behavior

All visible controls have one of three explicit behaviors:

- internal navigation resolves to a verified same-host staging route;
- telephone links use the approved number for the selected market;
- production-only actions use an environment-aware adapter.

On staging, Request a Quote reaches the staged `/contact-us/` quote experience and allows complete field interaction but cannot submit, send email/SMS, create a lead, or call an external endpoint. The preview is a `<div role="form">`, not an HTML `form`; its inputs have labels but no form owner or submission names, and its final control is `type="button"`. Local validation and the final staging explanation are presentation-only. If JavaScript fails, native submission remains impossible.

Book Online opens an accessible in-page booking-preview dialog with the intended production handoff copy and a `type="button"` final action that explains the staging block; it cannot invoke Housecall Pro. The dialog closes by its close button, Escape, and outside-pointer action, then restores focus to Book Online. The interface keeps the preview controls clickable while making side effects structurally impossible.

After migration, production adapters replace the preview components with the existing booking and quote workflows. No `danielj140.sg-host.com` URL, Basic Auth credential, staging notice, disabled-integration copy, or staging-only CSP ships with the production brand package.

Production workflow contracts are fixed and testable:

- **Quote:** every Request a Quote link resolves to same-host `/contact-us/`. The production contact adapter delegates only to the existing active Gravity Form ID `1`; it does not create or edit a form. It refuses readiness if Gravity Forms, form `1`, same-origin action handling, or the contact-page renderer is unavailable.
- **Booking:** the production booking adapter reads the fixed configuration key `TWINS_HCP_BOOK_URL`. Its required value is `https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true`. It emits that URL only in production mode with `target="_blank"` and `rel="noopener noreferrer"`; staging output and tests contain no live booking anchor.
- **Careers:** the production application adapter posts JSON only to same-origin `/wp-json/twins/v1/employment-applications`. The server route owns any CRM handoff and requires protected `TWINS_GHL_PIT` and `TWINS_GHL_LOCATION_ID` configuration plus its documented optional field IDs. Browser code never receives a CRM token or contacts LeadConnector directly. Production readiness fails unless the exact REST route, server configuration, validation/error contract, and adapter fixture pass. The current scope packages and tests this contract but does not enable the endpoint, workflows, or a real application submission.

Repository fixtures stub Gravity Form `1`, the exact HCP URL, and the employment REST route. They assert rendered destinations, request shapes, validation/error handling, and the absence of secrets. Migration smoke tests remain non-submitting; any real quote, booking, or application test requires separate authorization at migration time.

## Portable architecture and safety ownership

The current `twins-staging-overhaul.php` entry point remains staging-only and is never copied to production. It currently refuses to boot without staging constants and depends on the staging safety CSP; those safeguards remain correct for that loader.

Reusable presentation moves behind a portable core boundary:

- `website/twins-brand-experience/` owns side-effect-free templates, shared components, styles, interaction scripts, packaged assets, market registry, and adapter interfaces.
- `website/staging-safety/mu-plugins/twins-staging-overhaul.php` remains the fail-closed staging loader. It proves the existing staging constants and safety functions, then supplies staging review, quote, booking, route, and CSP adapters to the portable core.
- a separately packaged production loader supplies production review, quote, booking, route, and response adapters. It is built and exercised by repository tests but is not installed or executed on production in this scope.
- staging-specific response hardening, including `twins_overhaul_inert_csp_policy()`, stays in the staging loader/adapter and is not referenced by portable templates or production-mode code.

A production-mode PHP harness boots the portable core and production adapters without `TWINS_STAGING_SAFETY`, `DISABLE_WP_CRON`, `twins_staging_safety_csp_policy()`, or the staging MU plugin. It proves that rendering has no staging hostname, notice, Basic Auth value, inert-action copy, preview-only Illinois exposure, or staging CSP dependency. This harness proves package portability; it does not touch production.

Safety ownership remains layered:

- SiteGround owns HTTP Basic Authentication, default-deny host/server outbound egress, and disabled hosting cron.
- `twins-staging-safety.php` is defense in depth for exact staging constants, noindex headers/meta/robots, `wp_mail()` suppression, WordPress HTTP blocking, integration/credential quarantine, WordPress cron and persistent-queue refusal, shortcode isolation, and the baseline CSP.
- the staging overhaul adapter tightens eligible pages to `connect-src 'none'` and `form-action 'none'` and provides structurally non-submitting preview components.
- the portable brand core owns no authentication, egress, cron, mail, credential, or production-integration authority.

The review work creates no network exception at either the host or WordPress layer. Every existing safety test, staging-host harness, Basic Auth check, header check, and full same-origin crawl remains a release gate.

## Accessibility and responsive requirements

- Semantic landmarks and one accessible primary navigation per page.
- Visible keyboard focus on every control.
- WCAG AA contrast for ordinary text and controls.
- No hover-only information or action.
- Images do not cause material layout shift.
- Controls have at least a 44x44 CSS-pixel touch target on mobile.
- The page has no horizontal overflow at 320, 360, 390, 768, 1024, 1201, or 1440 CSS pixels.
- The menu, review slider, and staged forms work with keyboard only.
- Reduced-motion behavior is tested, not merely declared.

## Testing and acceptance gates

Implementation follows test-driven development. Tests first demonstrate the current failures, then pass only after the redesign is implemented.

### Renderer and contract tests

- Homepage output contains exactly one accessible primary navigation and an Our Team link.
- Generated primary quote copy is exactly **Request a Quote** across shared chrome, homepage, Our Team, Careers, Reviews, contact, and closing CTAs; Get an Estimate and Request Exact Quote do not appear in those redesigned surfaces.
- Header output includes Book Online and Request a Quote at desktop and mobile breakpoints.
- CSS/renderer contracts encode logo floors above the measured current baselines at expanded desktop, compressed desktop, 390-pixel mobile, and 320-pixel mobile sizes.
- The review renderer accepts normalized provider records, preserves their text/rating/date, and emits no sample fallback review.
- The staging review capture names `/wi/`, page `2186`, collection `2178`, records the required provider/version/source hashes, and contains at least five individually hash-verified records.
- A missing, stale, malformed, short, or unverifiable review source produces the unnumbered notice plus internal Reviews-page link and prevents staging-complete status.
- Staging safety continues blocking mail, lead endpoints, external booking, analytics, advertising, and general outbound requests.
- Quote, booking, and Careers application previews contain no submitting form, named submission field, submit/image input, external URL, fetch/XHR/beacon call, or side-effect-capable control, including with JavaScript disabled.
- Review-provider separation proves that hash-verified staging rendering does not require a runtime network, shortcode, or plugin-cache call and that the production adapter can be enabled independently of the staging safety plugin.
- The production-mode harness boots and renders without staging constants/functions and excludes Illinois unless an explicit public-production configuration enables it.
- Production adapter fixtures assert same-host `/contact-us/` with Gravity Form `1`, the exact configured Housecall Pro URL, same-host `/wp-json/twins/v1/employment-applications`, expected request/validation shapes, and zero credential exposure.
- The complete existing staging safety suite and staging-host PHP harnesses remain green.

### Browser tests

At 1440, 1201, 1024, 768, 390, 360, and 320 CSS pixels:

- the rendered logo bounding box meets the specified floor, exceeds the captured before-state baseline at the same viewport/state, and remains unobscured;
- the primary nav is visible on desktop; the mobile menu opens, traps focus, closes by Escape, restores focus, and leaves no blocking overlay;
- the center point of every button, menu link, card link, slider control, region link, and sticky action resolves to the intended interactive element;
- every same-host link navigates to the expected successful staging route, and no unexpected production or external navigation occurs;
- both Twin characters are visible; sampling each transform at 100-millisecond intervals for 7 seconds (longer than the maximum 6.5-second loop) proves animation progression and at least 12 pixels between the observed vertical minimum and maximum;
- reduced motion leaves both characters visible with stable bounds and no looping animation;
- the genuine team section is visible outside any closed disclosure and its images load with correct MIME type, dimensions, and alt text;
- the review slider shows at least five provenance-verified provider records across its sequence, supports buttons/keyboard/touch, pauses correctly, and does not shift the page;
- Our Team and Careers show their approved experiences and real-owned imagery requirements;
- quote/application/booking preview interaction with JavaScript enabled and disabled produces zero POST requests, zero external requests, and zero mail, CRM, SMS, booking, analytics, or production effects;
- the complete authenticated same-origin crawl has no route cap and reports no broken internal link, missing local asset, production hostname, unexpected external allowance, or safety-header failure.

Tests capture screenshots and traces on failure. A final visual review covers desktop and mobile in the authenticated private staging environment.

## Deployment boundary

This scope is authorized to change repository files and deploy them only to the private SiteGround staging copy. It is authorized to read and copy approved production-owned public assets, but it must not modify production WordPress, DNS, SiteGround production settings, Google Business data, reviews, booking records, leads, email, SMS, or analytics.

Completion means the private staged site presents the entire approved brand-wide overhaul, passes the repository checker and full relevant test suite, and has a documented migration checklist. Publishing or migrating to production is a separate explicitly authorized operation.

## Migration checklist requirement

The implementation handoff must record:

1. the exact portable-core, staging-loader, production-loader, review-capture, and asset versions deployed or packaged;
2. every staging-only component that must not migrate, including the staging MU entry points, safety constants, preview adapters, Illinois-preview enablement, Basic Auth values, safety CSP, and disabled-integration copy;
3. production URL, market, phone, Gravity Form `1`, exact `TWINS_HCP_BOOK_URL`, employment REST route, protected GHL configuration presence, and application-workflow activation state;
4. Business Reviews Bundle collection `2178`, installed plugin version, output-fixture hash, parser-contract result, live refresh verification, and duplicate-schema result;
5. production-mode harness evidence showing no staging dependency or Illinois exposure;
6. cache purge and exact rollback instructions;
7. desktop/mobile navigation, CTA, review, team-image, schema, and reduced-motion production smoke tests;
8. a final no-real-lead dry run followed by a separately approved real end-to-end test, if Daniel authorizes one at migration time.

No migration is considered ready merely because staging looks correct; the production adapter and rollback path must also be proven.
