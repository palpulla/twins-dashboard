# AI-visibility scoreboard — 2026-07-20

Monthly audit per the [program spec](../../superpowers/specs/2026-07-07-ai-search-reddit-program-design.md).
Retrieval layer = WebSearch (proxy for what AI assistants cite), same method as the
2026-07-07 baseline, so months are comparable.

## Fixed prompt set (run every month, unchanged)

1. best garage door repair Madison WI
2. garage door spring repair cost Madison WI
3. same day emergency garage door repair Madison WI
4. family owned garage door company Madison WI reviews

## Results

| # | Query | Twins cited? | Position | Who dominates | Δ vs 2026-07-07 |
|---|---|---|---|---|---|
| 1 | best garage door repair Madison WI | Yes, under-featured | 7 of 8 links (6th in summary) | Expertise "15 Best", Yelp ×2, Angi, Precision, Lifetime, Madison Overhead | **unchanged** |
| 2 | garage door spring repair **cost** Madison WI | **No — absent** | — | HomeBlue (#1), Thumbtack, Overhead Door, ServiceAgent, R&R, HomeAdvisor | **unchanged** |
| 3 | same day emergency garage door repair Madison WI | Yes, buried | 7 of 10 links (6th in summary) | A1, Anytime ×2, Overhead Door, WI Garage Door Pro, Titan, Central, Precision | **unchanged** |
| 4 | family owned garage door company Madison WI reviews | **No — absent** | — | Expertise, Madison Overhead ×4 (Yelp/Angi/FB/testimonials), ThreeBestRated, R&R, Northland | ⚠️ **REGRESSED** (was "cited, out-featured") |

**Score: cited on 2 of 4, both buried; absent on 2 of 4. One regression, no gains.**

## What the results actually show

1. **Directories own the citation layer.** Every query is dominated by Expertise,
   Yelp, Angi, ThreeBestRated, ServiceAgent, HomeBlue, Thumbtack, YellowPages —
   not by competitor websites. **This validates Phase 2 (directory claim-and-optimize)
   as the highest-leverage work**, above more owned content.
2. **Competitors publish extractable specifics Twins hides.** Results surface rival
   phone numbers, addresses and hours inline (Overhead Door 608-271-4288; Precision
   with street address + 8am-8pm). Twins' entry carries none.
3. **Founding year is a recurring trust token.** Madison Overhead "since 2002",
   Northland "since 1983", Peak "more than 40 years", Lifetime "since 1964". Twins
   publishes no founding year.
4. **Twins' best asset is invisible.** 4.9 with **699** Google reviews is the
   strongest review profile in this set, yet Madison Overhead wins the family-owned
   query on **14 Yelp reviews** — because it has four indexed third-party profiles
   and explicit "family-owned … since 2002" language. Twins is family-owned by twin
   brothers and does not surface at all.
5. **The cost gap persists despite Twins having the best cost data.** Twins publishes
   real dated ranges from 516 completed jobs, including spring-inclusive invoices of
   $780–$1,660 — but has **no dedicated spring-cost page**; it is buried inside
   `/wi/garage-door-cost-in-madison-wi/`. HomeBlue ranks #1 with generic national
   figures ($200–$330).

## Actions this points to (ranked)

1. **Phase 2 directory sweep is now the top priority** — claim/optimize Expertise,
   ThreeBestRated, Yelp, Angi, ServiceAgent, HomeBlue, Thumbtack. That is where the
   citations come from.
2. **Dedicated spring-cost page** — the single query Twins is absent from where it
   already owns better data. Split it out of the Madison cost page.
3. **Publish the trust tokens** as extractable facts everywhere: founding year,
   "family owned and operated by twin brothers", 4.9/699 review count, hours, and the
   canonical (608) 888-8785. (`llms.txt` now carries most of these — publish it.)
4. **Get review volume onto third-party profiles**, not just Google — the family-owned
   query is won by Yelp/Angi/FB profiles.

## Method note

WebSearch is a proxy. Add direct ChatGPT/Perplexity/Gemini checks (or Otterly/Peec)
when available, per the spec. Re-run this exact prompt set monthly and append a new
dated file; report the delta in the monthly review.
