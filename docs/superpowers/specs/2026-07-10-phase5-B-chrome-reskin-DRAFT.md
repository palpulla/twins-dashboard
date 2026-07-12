# Phase 5 Workstream B — site chrome re-skin (DRAFT plan, for Codex review)

Date: 2026-07-10 · Status: DRAFT — awaiting adversarial review before any build · Program handoff: `2026-07-09-twins-web-program-handoff.md` · Research: `docs/marketing/audits/2026-07-09-phase4-research/README.md`

**This is the riskiest workstream: the shared chrome renders on every page, and the site's live GA4 + Meta pixel + GHL chat + paid-ad conversion tracking sit in the same blast radius.** This draft is deliberately handed to a full review before execution.

## Goal

Re-skin the shared site chrome (header, menu, footer, floating quote widget, shared library sections) and the main-site service pages from the legacy Elementor look to the approved twx v2 kit — without breaking navigation, tracking, chat, forms, or paid-ad conversion events.

## Current-state facts (verified live 2026-07-10)

- Main site: GA4 = yes, Meta pixel = yes, GHL chat = yes, GTM = no (direct GA4/pixel), ClickCease = not on main. **All three tracking systems load via WPCode snippets** (main active snippets seen in page source include 7016, 6915, 7050, 7028, 7072, 7127, 7165, 7330), NOT inside the Elementor header/footer Theme Builder documents. Implication: restyling the Elementor header/footer docs should not touch the tracking code paths.
- Shared Theme Builder docs (from research digest): header **36** + menu bar **305**, footer **1409** (contact-us uses alt footer **2179**), floating quote widget **466**, shared library sections **1498-1516** (injected across service pages).
- twx v2 kit is already loaded site-wide via snippet 7050 (main) / 6755 (/wi) — the `.twx2-*` classes, tokens, and components exist; this workstream applies them to chrome, it does not invent new design.
- Risky-to-touch list (digest): `/contact-us/` (Gravity Form #1 + alt footer 2179 + KY phone), `/design-your-door` funnels (live lead endpoint), `/wi/thank-you-g-ppc-lp` (Google Ads conversion page), HCP "Book Now" deep links, GTM/Meta/GHL loaders in shared header, phone split per site.

## Proposed scope (this workstream = MAIN site only)

1. **Batch B1 — shared chrome (~5 Theme Builder docs):** header 36, menu 305, footer 1409, alt footer 2179, floating quote widget 466. Restyle to twx v2 (navy/yellow, Montserrat, sticker accents) preserving every link, the mobile menu behavior, the GHL chat mount point, and all tel:/Book-Now hrefs.
2. **Batch B2 — shared library sections 1498-1516:** restyle the injected sections used across service pages.
3. **Batch B3 — ~10 main service pages** (one template family, ~36 near-identical sections each): rebuild on twx v2 like the Phase-4 catalog pages.

## Proposed sequencing (lowest-risk first, to validate the pipeline before the header)

1. Footer 1409 (low risk: links + NAP, no tracking, no nav JS) → full tracking/nav/chat regression on a live page.
2. Floating quote widget 466 → regression.
3. Menu 305 + header 36 (highest risk: nav, mobile menu, chat mount, phone) → regression.
4. Alt footer 2179 (contact-us) → regression, plus Gravity Form submit test.
5. Library sections 1498-1516 → regression.
6. Service pages B3 (one as a template pilot, then the rest).

## Rollback

Every Theme Builder doc + page: export `_elementor_data` to `docs/superpowers/backups/2026-07-10-phase5-B/` before editing; restore = re-import + regenerate Elementor CSS. WPCode snippets are NOT edited in this workstream (tracking stays untouched).

## Regression checklist (run after EACH chrome doc change, on a live page)

- GA4 pageview fires (network: `google-analytics.com/g/collect` or `googletagmanager`)
- Meta pixel fires (`facebook.com/tr`)
- GHL chat widget loads AND opens
- Desktop nav: all menu items present + correct hrefs; dropdowns work
- Mobile nav: hamburger opens, scroll works (Phase 2 fix must survive)
- Phone numbers correct per site (main 833-833-2010; per-metro rules on /wi)
- HCP "Book Now" deep links intact
- `/design-your-door` + `/contact-us` lead endpoints still submit
- `/wi/thank-you-g-ppc-lp` Google Ads conversion still fires
- No new console errors; no layout shift / horizontal scroll at 390px
- Anonymous cache purge (a8c Edge) + re-check (pending BlogVault IP whitelist for anon QA)

## Open questions for review

1. Is the "tracking is separable from Elementor chrome docs" assumption safe, or are there inline handlers / GTM dataLayer pushes embedded in the header doc that a re-skin could drop?
2. Better sequencing? Should the header (highest risk) be split into a staging clone first?
3. What's the safest way to edit a live Theme Builder header that renders on 100% of pages — is there a maintenance-window or clone-and-swap approach that beats in-place edits?
4. Any regression-checklist gaps (esp. paid-ad conversion integrity)?
5. Should B3 service pages wait for a separate session given token cost + the two subagent deaths earlier this session?
