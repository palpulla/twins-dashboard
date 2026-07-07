# AI Search (AEO/GEO) + Reddit Credibility Program — Design

**Date:** 2026-07-07
**Owner:** Daniel (approval) / Claude (CMO execution)
**Bucket:** Compound (assets that keep producing) — part of [Marketing OS](2026-07-03-cmo-marketing-os-design.md)

## Problem / opportunity

AI assistants (ChatGPT Search, Perplexity, Gemini, Google AI Overviews, Claude) increasingly answer "who should I hire for garage door repair in Madison" directly, citing a handful of sources. Winning that answer is a durable, compounding asset. Baseline audit (2026-07-07) shows Twins is technically crawlable and under-leveraged.

### Baseline AI-visibility audit (2026-07-07)

**Technical readiness — GOOD:**
- `robots.txt` allows every AI crawler: GPTBot, ChatGPT-User, ClaudeBot, anthropic-ai, Google-Extended, PerplexityBot, Applebot. No blocking to fix.
- `/wi` already ships `LocalBusiness` + `AggregateRating` schema (3 JSON-LD blocks).
- **Gaps:** no `llms.txt` (404); no `FAQPage` or `Service` schema.

**Query visibility — WEAK where it counts** (WebSearch as proxy for the retrieval layer AIs cite):

| Buyer query | Twins cited? | Who dominates the citations |
|---|---|---|
| best garage door repair Madison WI | Yes, under-featured | Yelp top-10, Expertise "15 Best", ThreeBestRated, Overhead Door |
| garage door spring repair **cost** Madison | **No** | HomeBlue, Overhead Door, ServiceAgent, ProMatcher |
| same-day emergency repair Madison | Yes, buried | Overhead Door, Anytime, Titan, Precision (they publish phone + hours) |
| family owned garage door Madison reviews | Yes, out-featured | Madison Overhead (4.9★, "since 2002"), R&R, Northland (review proof + founding year) |

**Root cause:** the AIs cite (a) third-party "best of" lists/directories and (b) competitors who publish specific extractable facts — costs, hours, phone, founding year, review counts. Twins is absent from informational queries (cost/how-to) and out-detailed where present.

## Goals

1. Get Twins **cited/recommended** by the major AI assistants for high-intent Madison garage-door queries.
2. Build **credible third-party presence** (the listicles/directories + Reddit) that the AIs pull from.
3. Do the Reddit piece **without spamming** — genuine, disclosed, human-posted help only.
4. Make it **measurable** — a monthly AI-visibility scoreboard, Twins vs competitors.

## Design — three pillars (per AEO best practice)

### Pillar 1 — Structure (make Twins extractable)
- **`llms.txt`** at site root: what Twins is, service area, offers ($0 service call, $49 tune-up, GoodLeap), phone (608), key page links. (Content-engine output; Daniel/WP publishes.)
- **Schema additions:** `Service` (repair, spring, opener, install), `FAQPage` on service pages. Use the schema-markup approach; never fabricate ratings — pull real review counts from the fixed reviews pipeline.
- **Extractable Q&A content** via the existing content engine (Anthropic-only, Madison intent clusters): the exact questions people ask AIs — "garage door spring repair cost Madison," "who does same-day garage door repair near me," "is a $49 tune-up worth it," "torsion vs extension spring." Each: direct 40–60 word answer first, real specifics (real price ranges, real hours, real phone), dated.
- Publish specifics competitors publish and Twins hides: **hours, response time, service area list, real review count, founding year.**

### Pillar 2 — Authority (make Twins citable)
- Reviews **volume + recency** (top trust signal) — fed by the now-fixed Google reviews pipeline; keep the review-card flow driving new reviews.
- Real **statistics/specifics** in content (jobs completed, response time, warranty), cited and dated. No fabrication — real values only, ask Daniel or ship empty.
- **Freshness:** visible "last updated" dates; quarterly refresh of the money pages.

### Pillar 3 — Presence (be where AI looks)
- **Get onto/up the lists the AIs cite:** claim/optimize Expertise.com, ThreeBestRated, ServiceAgent, Angi, Yelp top-10, BBB, HomeBlue. These third-party pages drive more AI citations than the owned site.
- **Reddit credibility program (see below).**
- Consistent **NAP** (name/address/phone — the 608 line) across every directory.

### Reddit credibility program (the careful one)
Reddit is high-credibility and ruthlessly anti-promotion; automated/mass posting gets removed, downvoted, and can get an account banned — brand damage. Model:
1. **Monitor** relevant subreddits (r/madisonwi, r/HomeImprovement, local threads) for genuine garage-door questions.
2. Claude **drafts a genuinely helpful reply** that answers the question first and **discloses the Twins affiliation**.
3. A **real human** (Daniel or a team member) posts it from a **real, aged account** — never a burner, never automated.
4. Cadence: opportunistic, low-volume, quality over quantity. Surface candidates in the Monday brief.
- **Hard guardrails:** never auto-post; always disclose affiliation; only where genuinely on-topic and helpful; obey each subreddit's self-promo rules; no astroturfing / fake accounts / fake reviews.

## Measurement — the scoreboard
Monthly **AI-visibility audit**: run a fixed set of ~15 buyer prompts through the retrieval layer (WebSearch now; add real ChatGPT/Perplexity/Gemini checks or a tool like Otterly/Peec later), record for each: is Twins cited, at what position, vs which competitors, and which page/source. Track month-over-month; report the delta in the monthly review. This is the KPI that tells us if any of this is working.

## Scope & phasing
- **Phase 1 (fast, owned):** `llms.txt` + Service/FAQ schema + 5 extractable money pages (cost, same-day, spring, opener, "$49 tune-up worth it") via content engine; publish real hours/response-time/review-count. Stand up the monthly AI-visibility audit as a repeatable process.
- **Phase 2 (presence):** directory/listicle claim-and-optimize sweep (Expertise, ThreeBestRated, ServiceAgent, Angi, Yelp, BBB); NAP consistency.
- **Phase 3 (Reddit):** monitoring + draft pipeline surfaced in the Monday brief; human posts.

## Guardrails (standing)
Real data only — never fabricate prices, hours, ratings, review counts (ask Daniel or ship empty). Reddit: disclose + human-post + no automation + no fake accounts. Publishing content/schema to the live WordPress site needs Daniel's go (customer-facing). No em-dashes in any customer-facing copy. Keep the 608 local number as NAP, not the 833 van number.

## Success criteria
- Twins cited in AI answers for the top Madison garage-door queries where it's currently absent (cost, same-day), and featured (not just mentioned) on "best/family-owned."
- A live monthly scoreboard showing Twins vs competitors, trending up.
- Reddit presence that reads as helpful and never triggers a spam removal.
