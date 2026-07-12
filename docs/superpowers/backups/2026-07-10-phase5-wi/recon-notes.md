# Phase 5 /wi recon notes — 2026-07-10 (Task 1, read-only)

All findings gathered logged-in via the Chrome tab (BlogVault blocks anonymous requests). No site content or settings were modified.

## Backups in this directory

| File | Source | Chars | Verified |
|---|---|---|---|
| `page-6460-elementor-before.json` | `_elementor_data` meta of /wi page 6460 (slug `garage-door-repair-in-milwaukee-wi`) via REST `context=edit` + X-WP-Nonce | 112,499 | byte count matches in-page length; parses as valid JSON |
| `page-1616-elementor-before.json` | `_elementor_data` meta of /wi page 1616 (slug `garage-door-installation`) | 59,980 | byte count matches in-page length; parses as valid JSON |

Method note: the Elementor editor was never opened; data came from the REST API (`/wi/wp-json/wp/v2/pages/{id}?context=edit` with a nonce from `admin-ajax.php?action=rest-nonce`), so there is zero save-risk. Cookie-only REST returns 401; the nonce header is required.

## a) Reviews page + Business Reviews Bundle shortcode

- Reviews page id: **2186** (slug `reviews`).
- Exact shortcode (verbatim, from both `content.raw` and the page's Elementor data, inside an HTML/text widget):

```
[brb_collection id="2178"]
```

- The page's Elementor data also references `rplg` assets 21 times (Business Reviews Bundle plugin internals, grw/rplg family) but the only shortcode is the one above.

## b) Phone-swap snippet (WPCode on /wi)

Active /wi WPCode snippets (12 total; ids 20 and 19 inactive, all others active):
6781 (TEMP Rank Math redirections shim, admin only), 6777 (forms v2 engine, footer), 6773 (GHL chat widget, footer), 6765 (Door Builder, everywhere), 6755 (Clopay Product API, everywhere), 6754 (LocalBusiness schema, footer), 6753 (mobile call bar + swap, footer), 6657 (Main Site Number Pool, header), 6412 (SEO AGENT = Alli AI widget, header), 22 (PHP, everywhere).

Two separate phone mechanisms exist:

1. **Snippet 6753 — "CAP 2026-07: mobile sticky call bar + viewport zoom fix"** (Site Wide Footer, ACTIVE, 1,424 chars). Contains the literal swap script: an IIFE commented "CAP: unify stray phone numbers to the main line (833 pool split corrupts attribution)" that finds `a[href*="8338332010"], a[href*="833-2010"]`, rewrites the href to `tel:+16088888785`, and replaces visible text `(833) 833-2010` with `(608) 888-8785`. So YES — the 833→608 rewrite lives in the SAME snippet as the mobile sticky call bar (plus a viewport meta fix). The snippet contains no `925-2038` string.
2. **Snippet 6657 — "Main Site Number Pool"** (Site Wide Header, ACTIVE, 224 chars). Loads GHL LeadConnector `number_pool.js` (location ATDh3QGRFcbWAxmrvh2G, pool OadDgnK0RFyyIPVUJzHs) + `user_session.js`. This is the client-side dynamic-number-insertion that swaps the visible (608) 888-8785 to a tracking pool number (e.g. 608-925-2038) at runtime for visitors. No literal phone digits are in the snippet; the pool number comes from GHL remotely.

Raw HTML never contains 925-2038; it appears only after the GHL pool script runs in-browser.

## c) 620 N Carroll sources

The /wi homepage emits 3 JSON-LD blocks:

| Block | Emitted by | @id / type | Address |
|---|---|---|---|
| 0 (head) | **Rank Math Local SEO** (Titles & Meta settings) | `/wi/#place`, `/wi/#organization` (HomeAndConstructionBusiness/Organization), `/wi/#website`, `/wi/#logo` | **620 N Carroll St, Madison WI 53703 (WRONG)** |
| 1 (body) | **Elementor HTML widget `05a3ae7` on the /wi homepage document itself** (not a snippet) | `/wi/#tgd-local-2026-05`, HomeAndConstructionBusiness, founders Daniel + Tal Joseph | **2921 Landmark Pl, Ste 206, Madison WI 53713 (RIGHT)** |
| 2 (footer) | **WPCode snippet 6754** ("CAP 2026-07: LocalBusiness schema") | LocalBusiness, no @id | No street address (locality Madison only), tel +16088888785 |

620 N Carroll confirmed in Rank Math settings: `admin.php?page=rank-math-options-titles` → Local SEO — settings JSON shows `local_address.streetAddress: "620 N Carroll St"`, postalCode 53703, phone (608) 888-8785, geo 43.078530,-89.391760, about page 1924, contact page 2123. **Fixing block 0 = edit Rank Math Titles & Meta → Local SEO address (and geo) on /wi.** No page-level `rank_math_schema` involvement found.

/wi/reviews/ has only 2 JSON-LD blocks: Rank Math BreadcrumbList + the snippet-6754 LocalBusiness. No street address on that page; the Rank Math org/place block (620 Carroll) is homepage-only.

## d) 888-8785 chrome locations (29 raw occurrences on /wi/ = 15 "888-8785" + 14 "8888785")

- **Head/schema (1):** Rank Math JSON-LD block 0 (`+1-608-888-8785`).
- **Header, Elementor Theme Builder header doc id 36 (2):** UAEL infobox — `tel:6088888785` link + visible `(608) 888-8785` title.
- **Page body (13):** six Elementor button widgets, each a `tel:6088888785` href + `(608) 888-8785` label pair (12), plus one more UAEL infobox pair near page bottom counted here (2 of the 13) — and the `#tgd-local-2026-05` JSON-LD telephone (1).
- **Footer, Elementor Theme Builder footer doc id 1409 (4):** one button pair + one icon-list pair.
- **Injected scripts/widgets in footer output (9):** forms-v2 engine fallback copy "or call (608) 888-8785" (snippet 6777, 2), form error alert + chat connection-error copy (6777/6773, 2), snippet 6754 JSON-LD (1), snippet 6753 call bar `tel:+16088888785` (1) + its 833-swap script containing the replacement literals (2 or 3).

Theme-builder doc ids from rendered wrappers: **header = 36, footer = 1409** (`data-elementor-type="header|footer" data-elementor-id=...`).

## e) /wi Elementor Theme Builder / template library (first list page, 20 items)

6599 "Garage Door Repair & Installation Milwau…" (Page), 6563 TwinShield Protection Plans (Page), 6539 SOMMER 2060 evo+ (Page), 6537 LiftMaster 98022 (Page), 6535 LiftMaster 6690L (Page), 6533 LiftMaster 6580L (Page), 6517 LiftMaster 2220L (Page), 6508 Financing GoodLeap WI (Page), 6498 GoodLeap Financing (Page), 4337 Main Location Page (Page), 4256 Location – Other (Page), 3041 Services old (Section), 2814 Section New Area (Section), 2792 Location Main (Page), 2682 Location Main Template (Single Page), 2514 Landing Page Version 2 (Page), 2465 Landing Page Version 1 (Page), 2427 Locations Inner – Madison (Single Post), 2404 Single Post (Single Post), 2360 Blog (Archive). All Published.

Header (36) and footer (1409) docs are older and sit past page 1 of the list; their ids are confirmed from the rendered homepage wrappers above.

## Surprises / concerns

- Page 6460 already exists as a large document (112k chars of Elementor data) — Task 3 is a rebuild, not a from-scratch page.
- Template 6599 ("Garage Door Repair & Installation Milwaukee…") looks like a prior Milwaukee draft in the library — worth checking before building page 6460.
- The homepage carries THREE overlapping LocalBusiness-family schema blocks (Rank Math, page widget 05a3ae7, snippet 6754) — cleanup in Task 5 should decide on one canonical block; the duplicate telephone/no-address block from 6754 is redundant with the widget block.
- Rank Math Local SEO stores a Google Maps API key in its settings (not copied here).
