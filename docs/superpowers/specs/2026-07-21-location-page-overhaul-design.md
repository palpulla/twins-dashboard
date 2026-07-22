# Location Page Overhaul Design

## Goal

Replace the sparse location-page shell with a credible, useful local service landing page that explains what Twins does, demonstrates trust, answers common customer questions, and converts visitors on desktop and mobile.

## Approved Direction

Use one shared location-page template for all supported cities, with market- and city-specific content from the existing location registry. Rockford is the proof page for staging verification, but the structure must work for all 59 registered cities without creating thin or doorway-style pages.

## Copy Guardrails

- Never describe any office, branch, market, or service area as recently opened, new, unproven, or still earning a record.
- Remove the Rockford sentences that say Twins recently opened, is new to the market, or will earn the local record.
- Lead with customer needs, concrete services, useful diagnostic guidance, and verifiable business information.
- Do not invent response times, prices, warranties, neighborhood familiarity, years in a city, or job-count claims.
- Keep the tone direct, neighborly, confident, and specific without hype.
- Preserve the existing no-numerals rule for the 20 cities whose registry content must not contain numeric claims.

## Page Structure

1. **Local hero:** City-specific H1, substantial introduction, local phone action, quote action, and a branded garage-door visual using existing repository assets.
2. **Trust bar:** Google rating and review count from the existing review summary, licensed and insured status, family-owned positioning, and local service availability.
3. **Service cards:** Repair, opener service, and installation cards with short explanatory copy and one correct destination per card. Remove the duplicate repair link.
4. **Local guidance:** Combine the city-specific local note with practical warning signs and repair-versus-replacement guidance in a dense two-column section.
5. **Simple process:** Explain the customer journey in three steps without unsupported timing promises.
6. **Branch and proof:** Display the correct market/metro address, rating, and phone. Never fall back to the Madison footer address for Rockford page-level branch information.
7. **Nearby areas:** Show a compact subset of nearby cities and a single route to the broader service-area index rather than a dominant all-city panel.
8. **Expanded FAQ:** Render five useful questions for location pages. The two city-specific questions come first, followed by three shared service questions.
9. **Final CTA:** Use a short, specific close with phone and quote actions.

## Visual Direction

- Preserve Twins navy, gold, cream, typography, borders, and industrial personality.
- Replace oversized empty sections with denser split layouts, cards, proof bands, and useful supporting copy.
- Keep one strong H1 and reduce the hero's vertical footprint.
- Maintain readable line lengths and clear action hierarchy.
- On mobile, avoid long button stacks and oversized service-area panels; use compact cards and a short nearby-city grid.
- Keep the fixed mobile call/quote bar, but ensure it does not obscure page content.

## Data and Rendering

- Continue resolving location records in `Experience.php` from the current route.
- Extend normalized location content only where structured per-city fields are genuinely needed; shared service/process/FAQ content belongs in the template.
- Derive city and market labels from existing context. Do not duplicate NAP or route logic.
- Keep schema and visible FAQ content aligned.

## Verification

- Renderer contracts must prove the new sections render for Rockford and for all 59 registered location routes.
- Contracts must fail on prohibited phrases such as `recently opened`, `new to this market`, and `earn the local record`.
- Contracts must prove the duplicate repair link is gone, the compact nearby-area treatment is present, five FAQs render, and Rockford uses the Rockford address.
- PHP syntax, package contracts, full test suite, package verification, desktop screenshot, and mobile screenshot must pass before staging deployment is reported complete.
