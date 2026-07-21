# Handoff — rewrite the Wisconsin location pages (SEO / AEO / GEO)

Prepared 2026-07-20 for whoever writes the per-city copy (Daniel is routing the
writing to ChatGPT/Codex). Everything needed to do the work correctly on the staged
site is here. **Read the Hard rules and the Safety pipeline sections before editing
anything** — this repo has sealed contracts that will fail the build if you skip the
manifest repin step.

---

## 1. Mission

Rebuild the Wisconsin **location** pages (`/wi/location/<city>/`) into top-tier local
pages for search and AI answer engines, using **real Twins data** — never invented
local facts.

Why now: an AI-visibility audit (`docs/marketing/aeo/scoreboard-2026-07-20.md`) found
Twins is absent or buried on every high-intent Madison query. Competitors win by
publishing **specific extractable facts** — founding year, review counts, hours,
phone, real prices. Twins has better facts and publishes almost none of them.

## 2. Current state (important)

Location pages today render the **clean brand experience only**: hero + city-aware
answer paragraph + NAP block + service links + service-area panel + map + `Service`
schema. There is **no body copy**.

That is deliberate. On 2026-07-20 (release r18) the old per-city essays were
**suppressed** because they carried typos ("upfrot", "Houscall Superpro") and
unverified claims ("lifetime warranty", "24/7 emergency", "#1", "BEST"). Do **not**
restore them. You are writing replacements from scratch, grounded in real data.

Template: `twins-brand-experience/templates/editorial.php` — see the
`$kind === 'location'` branches. The body is skipped via `elseif ($kind !== 'location')`.

## 3. The real data — use this, invent nothing

Completed Twins jobs per Wisconsin city (from the production database, 2026-07-20).
`serving_since` = year of the first completed job in that city.

| City | Completed jobs | Serving since | Most recent |
|---|---|---|---|
| Madison | 1,581 | 2021 | 2026-07 |
| Verona | 307 | 2021 | 2026-07 |
| Fitchburg | 298 | 2021 | 2026-07 |
| Sun Prairie | 222 | 2021 | 2026-07 |
| Middleton | 212 | 2021 | 2026-07 |
| DeForest | 72 | 2021 | 2026-07 |
| Oregon | 70 | 2021 | 2026-07 |
| Waunakee | 69 | 2021 | 2026-06 |
| Janesville | 58 | 2023 | 2026-07 |
| McFarland | 56 | 2021 | 2026-07 |
| Monona | 48 | 2021 | 2026-05 |
| Cottage Grove | 46 | 2021 | 2026-06 |
| Pardeeville | 36 | 2024 | 2026-07 |
| Baraboo | 33 | 2025 | 2026-07 |
| Deerfield | 30 | 2021 | 2026-06 |
| Stoughton | 28 | 2021 | 2026-07 |
| Portage | 26 | 2023 | 2026-06 |
| Belleville | 19 | 2021 | 2026-06 |
| Reedsburg | 18 | 2025 | 2026-06 |
| Mount Horeb | 17 | 2022 | 2026-07 |
| Watertown | 15 | 2025 | 2026-07 |
| Edgerton | 14 | 2023 | 2026-06 |
| Evansville | 11 | 2023 | 2026-03 |
| Monroe | 10 | 2023 | 2026-06 |
| Cambridge | 10 | 2022 | 2026-07 |
| Rio | 10 | 2024 | 2026-05 |
| Cross Plains | 9 | 2022 | 2026-07 |
| Beloit | 9 | 2023 | 2026-06 |
| Brooklyn | 8 | 2021 | 2026-06 |
| Marshall | 7 | 2023 | 2026-07 |
| Columbus | 7 | 2021 | 2026-02 |
| Fall River | 7 | 2022 | 2025-12 |
| Lodi | 7 | 2025 | 2026-07 |
| Milton | 7 | 2023 | 2026-07 |
| New Glarus | 6 | 2024 | 2026-05 |
| Barneveld | 6 | 2025 | 2026-03 |
| Windsor | 6 | 2022 | 2024-10 |
| Sauk City | 5 | 2022 | 2025-07 |
| Fort Atkinson | 5 | 2025 | 2026-03 |

**Cities with real jobs but no page yet** (add these): Pardeeville, Baraboo, Portage,
Reedsburg, Watertown, Cambridge, Beloit, Brooklyn, Rio, Columbus, Monroe, Fall River,
Lodi, New Glarus, Barneveld, Sauk City.

### Other verified facts you may use

- Family owned and operated by twin brothers. **Founded 2016.**
- **4.9 stars from 699 Google reviews** (verified 2026-07-20).
- Licensed and insured. Hours per Google Business Profile: open 24 hours.
- Madison address: 2921 Landmark Pl #206, Madison, WI 53713.
- Real price ranges (already published, from 516 completed jobs, reviewed 2026-07-10):
  repair **$400–$1,050**, new opener installed **$900–$1,450**, new door installed
  **$3,000–$4,100**, door + opener **$4,400–$7,250**; repair jobs including spring
  replacement, middle 50% of invoices **$780–$1,660**.
- **Service call $49, waived when the customer completes a repair with Twins.**
- Authorized Clopay dealer.

## 4. Hard rules (non-negotiable)

1. **Never invent a fact.** No made-up local landmarks, neighborhoods, job counts,
   years, testimonials, prices, or stats. If you want a fact you do not have, ask
   Daniel or leave it out. Everything above is verified; anything else is not.
2. **No em-dashes** anywhere in customer-facing copy.
3. **Crew voice** — plain, direct, how a technician would explain it. Avoid AI-tell
   words: ultimate, comprehensive, seamless, hassle-free, elevate, unlock, delve.
4. **No superlatives or unverified claims** — no "#1", "best", "lifetime warranty",
   "24/7 emergency", "same-day guaranteed". These are exactly what got the old copy
   removed.
5. **Safety framing is mandatory** wherever springs/cables come up: springs are under
   high tension and must be handled by trained technicians.
6. **Pricing model:** the technician inspects and gives the exact price on site.
   Published ranges are historical planning ranges, not quotes. Daniel explicitly
   does not want more price-list pages.
7. **Phones:** use the template's market phone variable (`$market['phoneDisplay']` /
   `phoneHref`). Do not hardcode. Canonical main office number is **(608) 888-8785**.
   (Known open item: the Google listing shows (608) 422-4900 and the WI market shows
   (608) 420-2377 — NAP reconciliation is pending, do not "fix" it in copy.)

## 4b. URL architecture — state → metro → city (decided 2026-07-20)

Daniel's structure: state, then major city, then the rest. Expressed as **hub pages
with flat URLs** (not nested paths):

```
/wi/                  state hub
/wi/madison/          metro hub   (1,581 completed jobs)
/wi/milwaukee/        metro hub
/wi/verona/           city page   ← peer depth, not /wi/madison/verona/
/wi/fitchburg/        city page
/il/                  state hub
/il/rockford/         metro hub
/ky/                  state hub
/ky/lexington/        metro hub
```

- The `/location/` path segment is **dropped** (`/wi/location/verona/` → `/wi/verona/`).
- Metro hubs own the "<metro> area" queries and link down to their cluster; individual
  cities link back up. The hierarchy lives in **internal linking + schema `areaServed`**,
  not in nested URLs. Verona is a peer city, not a child of Madison.
- These URLs are **not live yet**, so changing them now only costs a route-registry
  edit plus updating `production-cutover/redirect-plan.md` targets. Do that as part of
  this work.

### Market / metro NAP — real, confirmed by Daniel 2026-07-20

| Metro | Address | Phone |
|---|---|---|
| Madison (WI) | 2921 Landmark Pl #206, Madison, WI 53713 | (608) 420-2377 |
| Milwaukee (WI) | 11220 W Burleigh St Ste 100, Wauwatosa, WI 53222 | (414) 800-9271 |
| Rockford (IL) | 5758 Elaine Dr Ste 110, Rockford, IL 61108 | (815) 800-2025 |
| Mt Sterling (KY) | 3651 Aarons Run Rd, Mt Sterling, KY 40353 | (859) 440-2227 |

Main office number for company-level NAP: **(608) 888-8785**.

**Data-model change required:** today `twins_overhaul_regions()` carries one address
per *market*. Wisconsin now needs **two metros** (Madison and Milwaukee) with their own
address and phone, so the model needs a **metro layer between market and city**, and
each city must be assigned to its metro. A city page shows the NAP and phone of the
metro that serves it.

**Milwaukee and Illinois carry real NAP but no job history** (1 completed job each).
They may use the address, phone, founding year, reviews and price ranges. They must
**not** claim completed-job counts or "serving since" for those cities.

## 5. Where the per-city copy must live (architecture)

**Do not put city content in `config/page-content.php`.** That registry is locked:
`PageContentRegistry` has a hardcoded `BESPOKE_PATHS` list of the 13 **service** paths
and a service-shaped `REQUIRED_KEYS`, and a contract asserts it contains *exactly
thirteen* records. Adding cities there breaks the build.

Recommended: add a **new config file** (e.g. `twins-brand-experience/config/location-content.php`)
keyed by city slug, with a small loader used by the location branch of
`editorial.php`. It must be:
- registered in **both** manifests (`staging-runtime.json` and `host-verification.json`), and
- covered by the existing validation (see Safety pipeline).

## 6. What a top-tier page should contain (AEO/GEO)

Per the program spec (`docs/superpowers/specs/2026-07-07-ai-search-reddit-program-design.md`):

- **Answer-first**: a direct 40–60 word answer at the top that names the city.
- **Extractable specifics**: completed jobs in that city, serving since <year>, family
  owned since 2016, 4.9/699 reviews, licensed and insured, hours, the $49-waived-with-repair
  model, real price ranges. These are the facts AI assistants cite.
- **Genuine local relevance without invention**: Wisconsin climate realities are true
  and usable (cold snaps, snowmelt, road salt on hardware, seasonal spring failures).
  City-specific claims beyond the data table are not.
- **Structured Q&A** per city (the schema pipeline already emits `FAQPage`, `Service`,
  `BreadcrumbList`, `LocalBusiness`, `AggregateRating` — do not rebuild schema).
- **Freshness**: a visible last-updated date.
- Keep exactly **one H1**.

## 7. Safety pipeline — read this or you will break the build

This repo deploys through a sealed, hash-pinned pipeline. Any file you edit that is
in a manifest must be repinned or the gate fails.

**Repin fixpoint (in order):**
1. Edit the file.
2. Update its `size` + `sha256` in **`manifests/staging-runtime.json`** and
   **`manifests/host-verification.json`**.
3. Recompute `staging-runtime.json`'s own size/sha256 and update **its pin inside
   `host-verification.json`** (host-verification is top of chain, self-unpinned).
4. `npm run build:packages && npm run check:packages`.
5. `node tools/check-repository.mjs` must print `REPOSITORY_CHECK_PASSED`.

Also run: `npm run test:contracts` (75 contracts) and the PHP harnesses. PHP is
available locally via a Docker shim at `~/.twins-php-shim/php`; node suites that call
bare `php` need `PATH="$HOME/.twins-php-shim:$PATH"`.

**Deploy (staging only):** rotate the release id (`staging-remediation-rNN-20260717`)
across the 5 files that carry it, repin `tools/private-staging-deploy.php`, then
`deploy:staging:dry-run` → `:capture` → `:release`, then flush the SiteGround cache.
Current release is **r19**.

### Do not touch

- `twins-staging-safety.php` (staging safety plugin — never deployed).
- `website/production-cutover/**` (production-only wiring).
- `tools/build-packages.mjs` seal — `productionPackage: 'DEFERRED'` and
  `productionWriteAuthority: false` stay as they are.
- Nothing gets published to the live WordPress site without Daniel's go.

## 8. Scope and order (Daniel's instruction)

1. **Wisconsin first** — rewrite existing WI city pages and add the missing cities
   listed in section 3.
2. **Milwaukee and Illinois** — ⚠️ **decision pending.** The database shows
   **1 completed job in Milwaukee and 1 in Illinois**. These pages cannot carry local
   proof. Precedent: the existing Milwaukee **cost** page handles this honestly by
   leaving the job count blank and using company-wide ranges. Do not fabricate local
   proof. Confirm with Daniel whether to build honest-but-thin pages now or wait for
   real work in those markets. (Illinois is also currently dark pending phone-forwarding
   proof.)
3. **Kentucky last** — 51 jobs total and dormant since November 2024.

## 9. Definition of done

- Every claim on the page traces to section 3 or is universally true.
- No em-dashes, no banned words, no superlatives, one H1.
- `check:repo` PASSES, contracts 75/75, harnesses green, manifests repinned.
- Verified live on staging after deploy (the site is behind auth; use an
  authenticated browser session).
- A short note back to Daniel listing any fact you wanted but did not have.
