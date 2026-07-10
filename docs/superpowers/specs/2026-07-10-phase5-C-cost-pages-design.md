# Phase 5 Workstream C — /wi cost pages + zip finder (design)

Date: 2026-07-10 · Status: approved by Daniel, ready to plan · Program handoff: `2026-07-09-twins-web-program-handoff.md`

Daniel approved scope: **two cost pages (Madison + Milwaukee) + a zip finder.** No city-tabbed reviews page (reviews are one business-wide Google profile; per-city tabs would repeat content).

## Goal

Close the AEO cost-query gap (`project_ai_search_aeo`: Twins is absent on "how much does..." queries). Give AI engines and searchers a data-backed, answer-first cost page per metro that gets Twins cited, and route ZIP entries to the right hub.

## Cost data (computed from real jwrpj `jobs`, status=completed, completed_at ≥ 2025-07-01, revenue > 0)

**Main price table (structured `job_type` field — clean, deterministic):**

| Service | Published range | Source |
|---|---|---|
| Service call / diagnostic | **$49** | Service Call job_type, p25=p75=49 (also the standing offer) |
| Garage door repair | **$400 to $1,050** | Repair, n=378 |
| New opener installed | **$900 to $1,450** | Opener Install, n=55 |
| New garage door installed | **$3,000 to $4,100** | Door Install, n=48 |
| New door + opener | **$4,400 to $7,250** | Door + Opener Install, n=35 |

**FAQ-only figure (framed as job total, NOT part-only):** repairs involving spring replacement typically **$780 to $1,660** (n=264 spring-involved jobs, p25–p75; derived from free-text match so it is deliberately kept out of the structured table and framed honestly in prose). Cable/roller ($334–$997, n=36) folded into the general repair line, not published separately.

All figures: full dollar amounts, no cents, "based on our completed jobs over the last 12 months." Daniel re-approves the $49 + spring FAQ figure at plan review (the 4 job-type ranges already approved in Workstream A).

## Page 1: `/wi/garage-door-cost-in-madison-wi/` (NEW) — phone (608) 420-2377

## Page 2: `/wi/garage-door-cost-in-milwaukee-wi/` (NEW) — phone (414) 800-9271

(6753 per-metro swap already keys on "milwaukee" in the path, so header/chrome auto-shows 414 on page 2 and 420 on page 1.)

**Section stack (twx v2 branded kit, identical component library to the hubs):**

1. Textured hero — H1 "How Much Does a Garage Door Cost in [City], WI?" + Call + Design-Your-Door CTAs
2. **Answer-first block** (the AI-quotable passage, 40–60 words): direct answer naming the headline ranges (repair $400–$1,050; new door $3,000–$4,100) + "based on our completed jobs over the last 12 months" + call to action. Freshness line "Last updated: July 10, 2026".
3. Trust ribbon
4. **Price table** (branded card): the 5 structured rows above. Full dollar amounts.
5. **"What affects the price"** — short factors list: door material (steel vs wood-look vs full-view), size (single vs double), insulation, single vs double spring, opener drive type. No fabricated numbers.
6. Financing band — GoodLeap + $0 service call (confirmed offers only)
7. Branded navy steps — how to get an exact quote (book/call → on-site diagnosis → flat quote)
8. **Deep FAQ + FAQPage schema** (8 Qs, targets query variations): overall cost, spring replacement (the $780–$1,660 honest framing), opener replacement cost, repair-vs-replace, why prices vary, financing, service-call fee, do you give free quotes. Answers verbatim in visible accordion AND JSON-LD.
9. Zip finder widget (`[twins_zip_finder]`)
10. Closer (Call + builder) + NAP footer (Madison: 2921 Landmark Pl Ste 206; Milwaukee: 11220 W Burleigh St Ste 100, Wauwatosa) + internal links to the repair/install hubs.

**JSON-LD:** LocalBusiness (per-metro NAP + phone) + FAQPage (8 Qs). Same deterministic hero-column injection as the hubs.

**Rank Math meta:** title "How Much Does a Garage Door Cost in [City], WI? (2026 Prices) | Twins Garage Doors"; description leads with the range + phone.

**Internal linking:** each cost page links to its metro hub; the hubs' FAQ "how much" answers get a "see full cost breakdown" link to the cost page (added during build).

## Zip finder — `[twins_zip_finder]` shortcode (new, in /wi snippet 6755)

Renders a branded input + button: "Enter your ZIP to check if we cover your area." On submit, deterministic ZIP-prefix routing:
- **537xx** → Madison install hub `/wi/garage-door-installation/`
- **531xx / 532xx** → Milwaukee hub `/wi/garage-door-repair-in-milwaukee-wi/`
- **anything else** → `/wi/contact-us/` with a "we may still cover your area — get in touch" message (no dead end)

Client-side only (no PHP lookup, no external calls). ZIP prefixes are geographic routing, not a business-rule classifier. Placed on: both cost pages + both hubs (append to the reusable snippet, drop the shortcode in a section).

## Build approach

Inline (no subagents). Proven pipeline per page: REST draft (new slug) → Rank Math meta → Elementor build → JSON-LD in hero column → Astra title meta last → publish → in-tab QA. Zip finder added to snippet 6755 with sha256 + white-screen drill. All new pages + snippet backed up to `docs/superpowers/backups/2026-07-10-phase5-C/` before publish; change-logged with revert paths.

## Verification

Per page: exactly one metro phone (text+tel+schema), FAQPage parses with 8 Qs, price table renders, zip finder routes correctly for a 537/532/other test ZIP, mobile fit, no em-dashes in copy, "Last updated" present. Internal links resolve.

## Out of scope

City-tabbed reviews page; /special-offers hub + coupon cards (later); main-site general cost page; any chrome/LP/tracking changes.

## Open items

- Daniel re-approves $49 + the $780–$1,660 spring FAQ framing at plan review.
- Anonymous QA still pending BlogVault whitelist (logged-in QA covers it meanwhile).

## Revert paths

New pages: trash via REST (they are new, so trashing fully reverts). Snippet 6755: restore `snippet-6755-before-C.php`.
