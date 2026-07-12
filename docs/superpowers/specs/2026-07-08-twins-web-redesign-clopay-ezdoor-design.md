# Twins Web Redesign — Clopay Collection Pages + EZDoor Funnel

**Date:** 2026-07-08
**Status:** Approved direction (Daniel: "finish it, then we review"). Supersedes the *visual* treatment in the two 2026-07-08 Clopay specs; their mechanics (API, capture funnel flow) stand.
**Sites:** twinsgaragedoors.com (main), /wi, /ky — WordPress multisite, Elementor + Astra, WP Rocket.

## Why

The Clopay API section shipped earlier today is visually bolted-on (tiny left-stuck gallery iframe, dead whitespace, heading hidden under sticky header, off-brand yellow). The underlying collection pages are busy review-style layouts. Daniel: rebuild the Clopay collection pages AND the EZDoor funnel to brand, with better UX. Decision made: **full rebuild keeping SEO core** (same URLs, same Rank Math titles/meta, H1 keywords kept, FAQ kept condensed; filler copy dropped).

## Brand tokens (extracted from live site CSS 2026-07-08)

| Token | Value |
|---|---|
| `--tw-navy` | `#022751` (Elementor global primary) |
| `--tw-navy-deep` | `#010D38` (dark bands) |
| `--tw-yellow` | `#FBBD04` (Elementor global accent — NOT #F5C518) |
| `--tw-bg-soft` | `#F5F5F5` / `#F2F5F7` (alt section backgrounds) |
| `--tw-text` | `#3A3A3A` body, `#7A7A7A` secondary |
| Font | Montserrat (already loaded site-wide) |
| Buttons | Primary: yellow bg / navy text, bold, slight radius. Secondary: navy bg / yellow text with yellow border (matches header "GET A FREE QUOTE"). |

These tokens live in one shared CSS block (`.twx-*` classes) shipped inside the Clopay WPCode snippet's `wp_head` output on each site — the de-facto brand stylesheet. No Global-Kit changes (blast radius).

## Architecture (Approach A — approved)

- **Elementor-native pages, class-based styling.** Page structure is Elementor sections/columns; visual identity comes from `.twx-*` classes + the shared stylesheet, so pages render identically on every subsite regardless of Global Kit differences (the cross-site kit lesson). Built **natively on each subsite** — never cloned cross-site.
- **One product-collection template, one funnel template**, expressed as a parameterized JS builder (product name, Clopay id, copy, region phone, links) run in the Elementor editor per page.
- **Clopay API section redesigned inside the existing PHP snippet** (7050 main / 6369 ky / new on wi): the snippet owns its markup + CSS, so the fix propagates to every page using the shortcode.

## Product-collection page template (top → bottom)

1. **Hero** (navy gradient over full-width door photo): H1 with same keywords ("Clopay Modern Steel™ Garage Doors"), one-line value prop, two CTAs — primary "Get a Free Quote" (existing quote link), secondary tel: region phone. No overlapping elements; mobile: stacked, fits screen.
2. **"Design this door on your home" band** (yellow): one sentence + button → that site's `/design-your-door` funnel page. Product pages feed the funnel.
3. **Live Clopay section** (`[clopay_product id=".." mode="specs"]`, redesigned): centered max-width column; gallery iframe full-width of column with 24/19 aspect; "Available Colors" as a wrapping swatch grid (first 12 + "+N more colors" expander); brochures/docs as pill buttons; Where-To-Buy propId CTA as secondary button.
4. **Why Twins** (3 compact cards): local + Clopay dealer, install expertise, warranty/service — real facts only, no invented claims.
5. **Condensed door copy** (2–3 short subsections from the old page's substance: who it's for, construction/insulation options, design options). Tight, scannable.
6. **FAQ** (accordion widget, condensed from existing FAQ content — substance kept).
7. **Final CTA band** (navy): quote button + phone.

**SEO preservation:** URLs unchanged; Rank Math title/meta untouched; H1 keyword-identical; FAQ retained; old Elementor JSON backed up to repo before replacement (reversibility).

**Pages:** main Modern Steel (6090, id 170), main Gallery (6065, id 12), main Classic (6034, id 13), /ky Classic (6198, id 13) — rebuilt in place. Plus **new** /ky Modern Steel + /ky Gallery built natively on /ky with Lexington-localized copy + KY Rank Math meta (fixes the earlier failed clone properly).

## EZDoor funnel page template (`/design-your-door` on main, /wi, /ky)

Per the approved capture-first funnel spec (mechanics unchanged), now with the same design language:

1. **Hero, one screen:** headline "Design Your Dream Garage Door", subhead ("see a Clopay door on a photo of your own home in under 5 minutes"), and the **GHL gate form** (name/phone/email/zip + hidden `lead_region`, submit "Start Designing") in a branded card. Region phone under form.
2. **How it works** (3 steps: tell us where to send it → design in the Clopay builder → Twins quotes it).
3. **Trust strip:** Clopay dealer line + real install photos from that subsite's media library.

GHL: 3 forms (Main/WI/KY) + 1 workflow → email contact@twinsgaragedoors.com with region-tagged subject; redirect to `https://ezdoor.clopay.com/` (interim until dealer-branded links). Region phones: main (833) 833-2010, /wi (608) 888-8785, /ky (859) 440-2227.

**Entry points:** "Design this door" bands on all collection pages; nav item per site.

## Execution order

1. Spec (this doc) → 2. Shared stylesheet + Clopay section redesign shipped (fixes the "terrible" immediately) → 3. Page backups + condensed copy → 4. Rebuild 4 existing collection pages → 5. Build 2 new KY pages → 6. GHL forms/workflow → 7. 3 funnel pages + entry points → 8. Verification sweep, change-log, review handoff to Daniel.

## Reversibility

Old page JSON exported to `docs/superpowers/backups/2026-07-08-clopay-pages/` before any replacement. Snippets are toggleable. Funnel pages can be unpublished; GHL forms/workflow disabled. Rank Math meta untouched.
