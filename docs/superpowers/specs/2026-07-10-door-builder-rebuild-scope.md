# SCOPE — Door Builder ("Design Your Door") rebuild

Owner ask (Daniel, 2026-07-10): the embedded door builder on the Gallery Steel page has "very low quality images and it also wont change colors, windows, glass type etc in illustration." Scope a real fix.

## What's actually wrong (root cause, not cosmetic)

The current on-site builder is the WPCode shortcode `[twins_door_builder]` (snippet 7127, a Phase-3 custom React-ish app). It reads Clopay's PUBLIC product API (`GetProductData?productId=12` for Gallery Steel) and shows option lists + a door image. The reason the picture is low-res and never reflects the chosen color/windows/glass is a **data limitation, not a bug we can style away**:

Clopay's public product API gives us, per product:
- **ProductDesigns** (Gallery Steel: 2 — Short Panel, Long Panel): one clean full-door image PER PANEL STYLE (e.g. `gallery-short-panel.webp`). These ARE full-door images, but only for the 2 panel styles.
- **Colors** (19), **TopSections/windows** (20), **SpecialityGlassOptions** (6), **HardwareDesigns** (12): each is a **swatch / thumbnail** (a small chip of the color, a thumbnail of the window shape, a glass sample) — NOT a full door rendered in that option.
- **ProductImageGallery** (9): real lifestyle photos of installed Gallery Steel doors — high quality, but each is a FIXED, already-built combination (not customizable).
- **ShowcaseImage**: one hero door photo.
- **No render / composite / visualizer endpoint** exists in the public API (searched: `render`, `visualiz`, `imagination`, `configurator` — absent).

=> There is no public way to get "a full door image showing Long Panel + Sandtone + ARCH3 windows + Seeded glass." So any custom builder built on the public API can only swap swatches next to a static panel image. That is exactly what our builder does, and it will always look like the door "won't change." The low resolution on top of that is because the panel `.webp`s are being displayed larger than their native size.

## Clopay's real tool already solves this: EZDoor / Door Imagination System (DIS)

Clopay provides dealers a professional visualizer — **EZDoor** (aka the Door Imagination System): the customer uploads a photo of their own home, picks a door + panel + windows + hardware + finish, and it overlays an accurate composite on their house, with save/share. This is the high-quality, option-accurate experience Daniel wants. Twins' `/ky` subsite still links out to EZDoor; the custom builder was built specifically to keep people on-site instead of handing off to EZDoor. That on-site goal is what forced the quality compromise.

## Options

**Option A — Use EZDoor (Clopay DIS) for the visualization.** Embed (iframe) or link the Twins-dealer EZDoor tool from the collection page. Highest quality + accurate + includes upload-your-home overlay; zero rendering to build. Downsides: it's Clopay-branded and lives partly off our site (weaker brand control, harder to track on-site), and an iframe of a full external tool won't match our chrome. Effort: LOW (mostly wiring + the dealer EZDoor URL). Open Q: does Twins' Clopay dealer account expose an embeddable EZDoor URL, or only a redirect link?

**Option B — Keep the custom builder but make it honest + crisp.** Rebuild the preview to: show the full-res panel design image (Short/Long Panel) as the hero, present color/window/glass as high-quality labeled swatches (accurately — that's what they are), and show a filtered strip of the real lifestyle gallery photos that match the chosen design. Frame it as "Build your spec — your Twins rep confirms the exact look," and the real deliverable is a good lead with the full config (already emailed via snippet 7072). Effort: MEDIUM. Result: looks professional and never lies, but is NOT a live full-composite render.

**Option C — Hybrid (RECOMMENDED).** On-brand custom section for fast browsing (Option B: crisp panel image + quality swatches + matching real photos + capture the spec/lead), PLUS a prominent "See it on YOUR home →" button that hands off to EZDoor for the accurate, upload-your-home composite. Best of both: brand + lead capture on-site, accurate visualization via Clopay's real tool. Effort: MEDIUM (Option B work + EZDoor handoff wiring).

**Not viable:** DIY compositing of arbitrary color/window/glass onto the door — the per-option assets are swatches, not layers; there's nothing to composite.

## Recommendation

Go **Option C (Hybrid)**. It gives the polished, honest, on-brand experience Daniel wants for browsing + lead capture, and routes the "show me my actual door on my house" moment to EZDoor, which does it properly. It also stops overpromising (a live composite we cannot deliver from the public API).

## Immediate stopgap (this week, independent of the rebuild)

The current builder overpromises (implies a live render it can't do) and shows scaled-up low-res panels. Until the rebuild: EITHER (a) fix snippet 7127 to use the full-res `ProductImage` `.webp` at native size + relabel the preview honestly ("Panel style shown; colors/windows are samples"), OR (b) hide the builder section on page 6065 (delete section `78da141`) and keep the existing "Design Your Door →" CTA, so a weak tool isn't customer-facing. Daniel's call; hiding is the safer default.

## Open questions for Daniel / next session
1. Does Twins' Clopay dealer account (propId 100841) have an embeddable/whitelabel EZDoor URL, or only the public EZDoor redirect? (Determines Option A/C wiring.)
2. Is on-site lead capture (spec → snippet 7072 endpoint) more important than pixel-accurate visualization? (If yes, Option B alone may suffice.)
3. Stopgap now: fix-in-place vs hide the builder on 6065?

## Facts / references
- Clopay API v2: `GetProductData?productId=12` (Gallery Steel). Snapshot: `docs/superpowers/backups/2026-07-09-phase4-catalog/clopay-api-snapshot/product-12.json`. CORS `*`, dealer propId 100841.
- Current builder: WPCode snippet 7127 `[twins_door_builder]`, mounts `#twxdb`, reads `?product=` from URL; lead endpoint snippet 7072 `POST /wp-json/twins/v1/door-builder`.
- On page 6065 the builder is embedded in Elementor section `78da141` (preload html widget sets `?product=12`).
- EZDoor: https://www.clopaydoor.com/ezdoor ; Door Imagination System overview: https://garagedoormore.com/residential/door-imagination-system/

## Cost research (Option C / licensed visualizer) — 2026-07-10
- **RenoGlance** (published dealer pricing, directly comparable): Dealer Starter **$299/mo**, Dealer Pro **$999/mo**, Manufacturer $2,500+/mo. Starter = on-home photo→catalog renderings + lead capture + basic analytics.
- **Renoworks** (the platform that powers tools like Clopay EZDoor): quote-only, dealer tier in the same ~$300–$1,000/mo range; likely + one-time setup/onboarding fee. Sales 1-877-980-3880.
- Clopay **EZDoor is FREE** to use but is OFF-SITE (redirect only — cannot iframe, malformed X-Frame-Options).

## *** DANIEL'S DECISION (2026-07-10, binding) ***
- The designer **MUST be embedded ON twinsgaragedoors.com** (in the site, not an off-site link).
- It **MUST cost $0** (no monthly SaaS).
- => **Paid vendors (Renoworks/RenoGlance) are RULED OUT.** **Off-site EZDoor handoff is RULED OUT.**
- => The next session must build/improve a **FREE, on-site** door designer that is genuinely good, using only free assets (Clopay public API images, real product photos, WPCode). Be honest about what "photorealistic composite like EZDoor" can/can't be done for free — but push HARD on the free approaches below before concluding anything is impossible.

## Free on-site approaches for the next session to investigate (in priority order)
1. **Deep-mine Clopay's free assets for per-combination full-door images.** I only did a shallow pass. Re-examine ALL image URLs in `product-12.json` (and other products) + try URL-pattern manipulation for higher-res / per-design+color+window renders. Check if Clopay's own product page (clopaydoor.com/gallerysteel) swaps a FULL door image when you change options (inspect its network) — if so, harvest/construct those URLs (free, hotlinkable from Clopay CDN).
2. **Harvest EZDoor's rendered door assets.** EZDoor (`ezdoor.clopay.com`, an Angular app) renders accurate doors. Drive its flow in the logged-in Chrome (claude-in-chrome MCP) and watch the network (read_network_requests) for the door-image CDN URLs it loads per (design/color/window/glass). If they're constructable/hotlinkable, build an on-page preview that shows the real rendered door for the selected combo (this gets ~EZDoor quality for the DOOR image, minus the upload-your-home overlay — which is acceptable and FREE + on-site).
3. **Curated real-photo visualization.** `ProductImageGallery` has ~9 REAL full-door lifestyle photos per product (full doors, high quality). Map design+finish → the closest real photo so the "preview" is always a real, crisp door. Not a live composite but honest + beautiful + free.
4. **Improve the existing builder (snippet 7127 `[twins_door_builder]`) regardless:** use full-res `.webp` at native size (the current low-res is scaled-up thumbnails), crisp swatches, honest labels.
Combine 1/2/3 as available. Goal: an embedded, free, genuinely-good "Design Your Door" that shows an accurate/real door image for the selected options.
