# Phase 4 research digests (2026-07-09)

Condensed from the four investigation agents that fed the Phase 4 spec. Phases 5-7 consume these.

## 1. Madison LP audit (/madison-garage-door-repair-lp/, page 7092)

- Self-contained `.tlp` block, Elementor canvas, its OWN palette/fonts (navy #16325c / gold #f6b21b / yellow #ffcf3f / cream, Lilita One + Nunito) — a third system; features ported to twx v2, fonts/palettes did not.
- Sections: sticky top bar (logo + call pill) → CSS-texture hero (pinstripes + gold glow, rotated "$0 SERVICE CALL" stamp, BOTH twins, dual CTA, star trust line) → yellow trust ribbon (3 checks) → 2-col: "What we fix" 6-card checklist + callback form card (name/phone/service dropdown, honeypot `website`, floating FAST RESPONSE tag) → 3 hardcoded review cards naming Maurice/Nicholas → navy CTA band w/ twins → 1-line footer → mobile sticky bar `#twins-callbar` (Call + HCP Book).
- Form posts JSON to Supabase edge fn `lp-lead-intake` (jwrpj) w/ utm/gclid/fbclid capture. GHL chat widget loader present. GA4 G-XW0RGPTGSN, Meta pixel 554750209097175, ClickCease.
- Bugs still open on the LP: literal `your-banner.jpg` preload; `.tlp *{animation:none!important}` kills all its entrance/bob animations; duplicate mascot sizing rules; wpautop-mangled markup; desktop has no Book link (mobile bar only). Page is noindex (fine). Phone (608) 888-8785 everywhere incl. schema.
- 2026-07-09: snippet 7044 (`#twins-callbar` + viewport fix) now runs ONLY on `-lp` URLs (WPCode Conditional Logic) — the twx2 site-wide bar owns everything else.

## 2. goodgollygarage.com teardown

- Webflow, multi-metro. Same navy+gold family as Twins. NO product/brand catalog — Twins' Clopay catalog is uncontested.
- Adopted into twx v2 (done): bracketed eyebrows, What-to-Expect 01-03 steps, Book+Call pair closing sections, brands-strip-as-endorsement, mobile bottom bar.
- Still on the shelf for phases 5-7: **zip finder** under hero routing to the right location page (w/ not-covered fallback page); **city-tabbed reviews page** with curated cards naming the tech + "Leave a Review" Google deep link; **per-metro cost-FAQ pages** with FAQPage schema targeting "how much does…" queries (fits the AEO gap — memory `project_ai_search_aeo`); **offer cards w/ coupon fine print** pointing at one /special-offers hub; **license number + full NAP in location footers**; metro-scoped nav on location hubs; 4-tier programmatic architecture (locations hub → metro hubs → suburb pages → sub-service × city pages, ~2,960 URLs) as the ceiling for /wi + /il expansion.
- Do NOT copy: placeholder content shipped to production ("[Insert Price Here]", "Button Text"), wrong-city schema from templating without QA, FAQ schema without visible FAQs, missing meta descriptions, SplitText duplicate-heading DOM. Twins' QA-gate (docs/superpowers/backups/2026-07-09-phase4-catalog/qa-gate.py) exists because of these.

## 3. Site template inventory (main + /wi)

- Main: 48 pages + 2 location + 198 posts. /wi: 69 pages + 22 location posts.
- ONE legacy Elementor family (kit colors already navy/yellow, Montserrat dominant) + the thin 17 Clopay pages (now drafted/301'd). twx-ui stylesheet already loads site-wide on both sites.
- Shared Theme Builder docs (restyle these first — touches everything): header 36 + menu bar 305, footer 1409 (contact-us uses alt footer 2179), floating quote widget 466, shared library sections 1498-1516 (injected across all service pages).
- Re-skin batches, in order: (1) shared chrome ~12 docs; (2) ~10 main service pages (one template family, 36 near-identical sections each); (3) ~~17 legacy Clopay~~ done in Phase 4; (4) LiftMaster pages 3+3; (5) trust pages ×7 (about, our-team, reviews, faqs, careers, financing, coupons); (6) ~11 /wi service pages (rebuild in-subsite, never cross-site clone); (7) 21 /wi location pages = ONE `location` post-type template; (8) ~40 /wi programmatic city-service pages (+content pruning); (9) homes last (/wi home shares Elementor ids with main page 335 — clone-risk); (10) blog = theme-level only.
- Risky-to-touch: /contact-us/ (Gravity Form #1 + alt footer + KY phone), /design-your-door funnels (live lead endpoint), /wi/thank-you-g-ppc-lp (Google Ads conversion page), HCP Book Now deep links on /wi, GTM/Meta/GHL loaders in shared header, phone split per site.
- Legacy-vs-twx gaps: flat photo heroes vs structured twx hero; Roboto remnants; 34-46 cluttered sections w/ repeated CTA blocks vs 8-10 purposeful bands; 0.9-1.3MB pages vs lean twx markup.

## 4. Clopay pages audit — resolved by Phase 4

17 orphaned manufacturer-copy pages (6403-6427) → 301'd to /clopay-*/ v2 pages + drafted; menu 42 deleted; all 23 catalog products covered. Bespoke products (Reserve Wood Custom 8, Extira 291) have empty Colors/Designs arrays — their specs sections render sparse-but-fine (cosmetic heading mention of colors; acceptable per Daniel's build).
