# Phase 1 Task 5 — Icon audit (existing Media Library assets only)

Date: 2026-07-09. READ-ONLY audit: nothing uploaded, edited, or deleted.
Every URL below was fetched with `curl -I` and returned HTTP 200 on 2026-07-09.

## Method

- Read rendered image URLs on each site's homepage + established service pages
  (garage-door-services, garage-door-spring-repair, garage-door-openers,
  garage-door-installation).
- Swept each site's Media Library via logged-in REST
  (`{site}/wp-json/wp/v2/media`, full pagination + keyword searches: icon, svg,
  badge, wrench, tool, star, check, handshake, warranty, certified, dealer, shield).
- Downloaded candidates and inspected them visually (contact sheet).

Key finding: all three sites carry the **same cloned icon inventory** (multisite
clones), including a branded "Why Choose Us" set — navy line art on a yellow
banner/shield background, 200x105 PNG, transparent corners. That set is already
in production on each site's `/garage-door-installation/` page as a 5-up icon
strip (premium-quality, time, wrench, conversation, warranty), so reusing it on
the Clopay "why Twins" cards is visually native to the sites.

Note: most other pictographic decoration on these sites is **inline SVG /
Elementor icon widgets** (627 inline `<svg>` nodes on the main homepage alone) —
those are not Media Library files and are not usable for this task.

## Chosen icons

Same 3 files on every site (each site's own library copy — WordPress media is
per-subsite, so the /ky and /wi pages must use the sites/N URLs, never the
main-site URL).

| Card | File | Why |
|---|---|---|
| 1. Official Clopay Dealer | `premium-quality.png` | Thumbs-up rosette/medal — badge semantics |
| 2. Install, Service & Repair | `wrench.png` | Hand holding wrench — service/repair semantics |
| 3. T'Winning Every Time | `conversation.png` | Two smiling people with chat bubbles — people/happy-customer semantics |

All three: 200x105 PNG (RGBA/indexed, transparent), navy `#1B3764`-family line
art on brand-yellow banner. As a matched set from one family they stay coherent
at 24x24 on the #F2F5F7 chip. Aspect note for the implementer: the files are
wide (200x105); render with `object-fit: contain` inside the 24x24 chip.

### Main site (twinsgaragedoors.com)

Used today: 5-up "Why Choose Us" strip on https://twinsgaragedoors.com/garage-door-installation/

1. Dealer: https://twinsgaragedoors.com/wp-content/uploads/2022/10/premium-quality.png — 200x105 PNG
2. Service: https://twinsgaragedoors.com/wp-content/uploads/2022/10/wrench.png — 200x105 PNG
3. T'Winning: https://twinsgaragedoors.com/wp-content/uploads/2022/10/conversation.png — 200x105 PNG

### /ky (Kentucky, site 3)

Used today: same 5-up strip on https://twinsgaragedoors.com/ky/garage-door-installation/

1. Dealer: https://twinsgaragedoors.com/ky/wp-content/uploads/sites/3/2022/10/premium-quality.png — 200x105 PNG
2. Service: https://twinsgaragedoors.com/ky/wp-content/uploads/sites/3/2022/10/wrench.png — 200x105 PNG
3. T'Winning: https://twinsgaragedoors.com/ky/wp-content/uploads/sites/3/2022/10/conversation.png — 200x105 PNG

### /wi (Wisconsin, site 4 — inventory for the later /wi phase; no collection pages yet)

Used today: same 5-up strip on https://twinsgaragedoors.com/wi/garage-door-installation/

1. Dealer: https://twinsgaragedoors.com/wi/wp-content/uploads/sites/4/2022/10/premium-quality.png — 200x105 PNG
2. Service: https://twinsgaragedoors.com/wi/wp-content/uploads/sites/4/2022/10/wrench.png — 200x105 PNG
3. T'Winning: https://twinsgaragedoors.com/wi/wp-content/uploads/sites/4/2022/10/conversation.png — 200x105 PNG

## Rejected candidates (present on all three sites unless noted)

Same-family alternates (viable fallbacks, weaker semantics):

- `2022/10/warranty.png` (200x105) — shield with % medal; shield reads well but
  the percent mark says "discount", wrong for the Dealer card.
  Verified 200: https://twinsgaragedoors.com/wp-content/uploads/2022/10/warranty.png
  (also /ky sites/3 and /wi sites/4 copies, verified 200).
- `2022/10/time.png` (200x105) — clock/24hr; no matching card meaning.
  Verified 200: https://twinsgaragedoors.com/wp-content/uploads/2022/10/time.png
  (also /ky sites/3 and /wi sites/4 copies, verified 200).

Other families (rejected for style mismatch or size):

- Orange-banner homepage services set, 200x176 (`repair.png`, `service.png`,
  `garage-opener.png`, `emergency-call.png`, `garagedoor*.png`) — used in the
  homepage "Our Services" section on all 3 sites; crossed-tools `repair.png`
  is semantically great for card 2 but the orange banner clashes with the
  yellow-banner set; mixing families breaks coherence.
  Verified 200: https://twinsgaragedoors.com/wp-content/uploads/2022/11/repair.png,
  https://twinsgaragedoors.com/wp-content/uploads/2022/11/service.png
- `s1.png`–`s8.png`, 135x135 — thin yellow line icons (garage, 24hr phone, tape,
  spring reel, tools, spring, parts-in-hand, door) used in a homepage strip on
  all 3 sites. No badge or people icon in the set (can't cover cards 1 and 3),
  and thin yellow strokes go faint at 24px on #F2F5F7.
  Verified 200: https://twinsgaragedoors.com/wp-content/uploads/2022/11/s5.png
- `2022/10/satisfaction-2.png` (256x256) — person + thumbs-up bubble, navy/yellow;
  best single icon for card 3 semantically, but it's from a different
  (banner-less) style family and has no badge/tool siblings, so a set built
  around it can't stay coherent.
  Verified 200: https://twinsgaragedoors.com/wp-content/uploads/2022/10/satisfaction-2.png
  (also /ky sites/3 and /wi sites/4 copies, verified 200).
- `2022/10/doorICON-checked.svg` (100x88) — garage door + checkmark SVG, used as
  a small motif on service pages; single-purpose (no matching tool/people
  siblings).
  Verified 200: https://twinsgaragedoors.com/wp-content/uploads/2022/10/doorICON-checked.svg
- `2022/10/Twins-Top.png` (83x102) — mascot man illustration; a character
  drawing, not an icon; unreadable at 24px.
  Verified 200: https://twinsgaragedoors.com/wp-content/uploads/2022/10/Twins-Top.png
- Small utility SVGs (`times-solid`, `bars-solid`, `arrow-right-solid`,
  `chat-quote-*`, `phone-call-svgrepo-com`, `calendar.svg`, `quote.svg`,
  `relaxing.svg`) — UI chrome / mismatched one-offs, not a card set.

## Status

- Main: OK — 3 icons chosen.
- /ky: OK — 3 icons chosen.
- /wi: OK — inventory recorded, same 3 icons available for the later /wi phase.
