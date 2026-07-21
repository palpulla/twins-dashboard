# AEO/GEO Phase 1 — build status

Program spec: [`../../superpowers/specs/2026-07-07-ai-search-reddit-program-design.md`](../../superpowers/specs/2026-07-07-ai-search-reddit-program-design.md)
(backlog #10). Phase 1 = `llms.txt` + Service/FAQ schema + 5 extractable money
pages + a monthly AI-visibility scoreboard. Started 2026-07-20.

## Status

| Phase 1 item | Status |
|---|---|
| Service / FAQPage schema | ✅ **Already done** — closed by the Wave 2–4 website work |
| `llms.txt` | 🟡 **Drafted** (`llms.txt` here) — 2 facts to confirm, then publish |
| 5 extractable money pages | ⬜ Next — real pricing exists, so this is unblocked |
| Monthly AI-visibility scoreboard | ⬜ Next — define the fixed prompt set + run a baseline |

### Schema — done (re-audited 2026-07-20)

The spec's 2026-07-07 baseline said "no `FAQPage` or `Service` schema." That gap is
closed. The overhaul now emits: `LocalBusiness`, `AggregateRating`, `PostalAddress`,
`Place`, `Brand`, `Service`, `FAQPage`, `Question`/`Answer`, `BreadcrumbList`,
`ListItem`, `Product`.

## ✅ Both facts resolved by Daniel 2026-07-20

- **Service call = $49** (current fee).
- **Canonical phone = (608) 888-8785** — the main office number. Used as NAP in
  `llms.txt`.

### ⚠️ But this exposes a live content bug — the homepage advertises "$0 Service Call"

`templates/home.php` renders an offer chip reading **"$0 Service Call"**, and it is
live on staging (and would ship to production). With the fee confirmed at **$49**,
that hero claim is **factually wrong as written** — a customer-facing pricing claim
that contradicts the cost page on the same site.

Needs Daniel's call, then a small website fix before launch:
- **(a)** the $0 offer is dead → change/remove the chip (say `$49 Service Call`, or
  drop it), **or**
- **(b)** it's a live *conditional* promo (STRATEGY lists "$0 service call" for
  repair-capture campaigns) → the chip must state the condition (e.g. service call
  waived with a completed repair) so it stops contradicting the $49 fee.

Do not publish `llms.txt` (or the money pages) while the site still advertises a
contradictory price — that is exactly the failure mode AEO punishes.

### NAP follow-up (Phase 2, not blocking)

The Google Business Profile currently lists **(608) 422-4900**, and the site's
Wisconsin market shows **(608) 420-2377** — neither matches the canonical
(608) 888-8785. Per-market site numbers may be intentional routing, but **GBP should
be reconciled** with the canonical NAP as part of the Phase 2 directory sweep.

## Pricing available for the money pages (real, dated, sourced)

From `cost-data.php` — reviewed 2026-07-10, from completed jobs Jul 2025–Jul 2026,
published as historical planning ranges with the standard disclaimer:

| Service | Range |
|---|---|
| Garage door repair | $400 to $1,050 |
| New opener installed | $900 to $1,450 |
| New garage door installed | $3,000 to $4,100 |
| New door and opener | $4,400 to $7,250 |
| Repair jobs including spring replacement | $780 to $1,660 (middle 50% of total invoices) |
| Service call and diagnostic | $49 (confirmed 2026-07-20) |

This is exactly what the baseline audit says competitors publish and Twins hides —
the reason Twins is absent from "garage door spring repair **cost** Madison."

## Publishing

`llms.txt` goes at the **site root** (`https://twinsgaragedoors.com/llms.txt`).
Per the spec, publishing customer-facing content to live WordPress **needs Daniel's
go**. Resolve the two `[CONFIRM]` facts first, then publish.

## Next

1. Resolve the two facts above.
2. Build the 5 money pages (cost, same-day, spring, opener, "$49 tune-up worth it"),
   answer-first 40–60 words, real specifics, dated.
3. Stand up the monthly AI-visibility scoreboard (fixed ~15 buyer prompts, Twins vs
   competitors, month over month).
