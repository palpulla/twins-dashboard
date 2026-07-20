# Page-by-page sign-off pass — 2026-07-20

Verified rendered content (not screenshots) on the staging site via authenticated
browser. One finding blocks a clean sign-off: the **location pages** carry raw
legacy essays. Everything else passes.

## Matrix

| Page type | URL checked | Verdict |
|---|---|---|
| Home | `/` | ✅ title, one H1, 4.9/699, pathways, real reviews, IL dark |
| Market home — WI | `/wi/` | ✅ geo title, 21 service-area cities |
| Market home — KY | `/ky/` | ✅ geo title, Lexington area |
| Service — repair (new) | `/garage-door-repair/` | ✅ full service page (was the broken flagship) |
| Service — tune-up (new) | `/garage-door-tune-up/` | ✅ full service page |
| Service — TwinShield (new) | `/protection-plans/` | ✅ full page, no invented price |
| Service — market-prefixed | `/wi/garage-door-spring-repair/` | ✅ WI context + 608 phone |
| Reviews | `/reviews/` | ✅ static list of real dated Google reviews |
| Team | `/our-team/` | ✅ 7 crew (3 initial-avatar placeholders as intended) |
| Contact | `/contact-us/` | ✅ callback form UI, per-market phones, IL dark |
| FAQ | `/faqs/` | ✅ 9-question branded accordion |
| Trust — financing | `/financing/` | ✅ clean Wisetack content |
| Blog index | `/blog/` | ✅ branded index + card |
| Article | `/garage-door-repairs-to-make-before-spring/` | ✅ clean crew-voice rewrite |
| Catalog | `/clopay-garage-doors/` | ✅ 23 collections + counts (Official Clopay Dealer) |
| Builder | `/door-builder/` | ✅ collection selector + reference disclaimer |
| **Location pages** | `/wi/location/madison/`, `/…/middleton/`, `/ky/location/lexington/` | ⚠️ **FLAG — see below** |

## FINDING — location pages leak the raw legacy essays

The brand experience at the top of each location page is correct (city-aware
answer, "Where we are" NAP with the real address + 4.9/699, service list, map,
Service schema). **But below it, each city page dumps its full original Elementor
SEO essay**, which carries:

- **Typos** — "upfrot", "Promis", "Houscall Pro Superpro".
- **Unverified / risky claims** — "lifetime parts and warranty", "lifetime
  warranty", "24/7 emergency services", "#1 Garage Door Repair", "BEST", "premier",
  "Superpro Status", "since 2016". These are factual assertions that publish
  publicly; several may not be true and shouldn't ship unverified.
- **Banned AI-tell words** — "hassle", "minimal hassles" (violates the crew-voice
  content rule).
- **Duplicate structure** — a second FAQ block and a second service-area list per
  page, plus a redundant staging "reviews disabled" notice mid-page.

Confirmed on 3 cities across both markets (Madison, Middleton, Lexington), so it
is **systemic to every `/wi/location/*` and `/ky/location/*` page** (~25+ pages).
This is the known "honesty-ceiling" content (Wave 3 note: the per-city essays must
come from the owner) — the sign-off question is what ships at launch.

### Recommended disposition: suppress the legacy body for launch

Ship the location pages as the **clean brand experience only** (hero + city
answer + NAP + services + map + Service schema) and stop rendering the preserved
legacy essay. Rationale: the brand experience already reads well and is accurate;
the legacy essays add typos, unverified claims, and duplication that are worse
than no essay. This is a code change to how location routes handle the preserved
body (inert-on-staging verifiable, like the other overhaul work). Reversible: add
curated per-city copy later when the owner provides local facts.

Alternatives: (b) clean every essay in place — heavy manual work across 25+ pages,
still thin; (c) rewrite per city — needs owner's local facts (the honesty
ceiling). Not recommended for launch.

Owner decision needed: **suppress (recommended) / keep-and-clean / defer.** On
"suppress", the change is small and I can implement + validate it.

## Everything else: signed off

All non-location page types render correctly with accurate copy, correct
per-market phones, IL dark, no OTTO, no staging-preview leaks in the wrong places,
and no errors/refusals. The 3 previously-broken service pages now render fully.
