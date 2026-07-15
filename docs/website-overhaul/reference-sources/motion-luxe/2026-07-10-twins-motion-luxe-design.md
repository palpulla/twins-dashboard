# Twins Motion Luxe Design

Date: 2026-07-10  
Status: Approved for prototype

## Goal

Bring the cost-page reference closer to the energetic polish of Good Golly Garage Doors while remaining unmistakably Twins. Improve CTA motion and section atmosphere, remove redundant header information, and consolidate pricing-source metadata.

## Header simplification

- Keep the active metro phone only in the gold utility rail.
- Remove the duplicate phone from the main navigation row.
- Keep one main-row CTA: `Request Exact Quote`.
- Preserve the compressed-header behavior and mobile sticky Call action.

## Motion CTA system

- Use pill-shaped primary buttons with an attached circular arrow treatment.
- Add a restrained gold glow, periodic sheen sweep, and slight icon travel or rotation on hover.
- Add a physical pressed state.
- Use the strongest animation only on primary CTAs.
- Secondary and dark buttons use quieter versions of the same component.
- Respect `prefers-reduced-motion` and retain visible focus states.

## Background system

- Replace large plain-white fields with layered Twins backgrounds.
- Light sections use soft blue-gray gradients, blueprint lines, garage-panel geometry, radial gold light, or subtle diagonal grain.
- Dark sections retain navy depth and low-contrast panel relief.
- Selected cards receive skewed gold or navy backing plates rather than additional bright full-width fields.
- Decorative layers never reduce text contrast or intercept pointer events.

## Section motion

- Below-fold sections receive restrained fade-and-rise entry motion using `IntersectionObserver`.
- Content remains visible when JavaScript is unavailable.
- The hero mascots retain their existing subtle animation.
- Do not add continuous parallax or heavy third-party animation libraries.

## Pricing metadata

- Remove the `Last reviewed` and `Source` line from the answer card.
- Show one consolidated line inside the expandable methodology disclosure:

`Pricing data reviewed July 10, 2026 · Based on completed Twins Garage Doors jobs from July 2025 through July 2026.`

- Keep the 516-job headline, sample sizes, middle-50% explanation, and individual-pricing disclaimer.

## Acceptance criteria

- Main header contains no duplicate phone block.
- Primary buttons have attached icon treatment, glow, sheen, hover travel, and pressed feedback.
- At least four light page sections use layered non-white background treatments.
- Below-fold sections reveal progressively without hiding content when JavaScript fails.
- Reduced-motion users receive no decorative animation.
- Pricing review date and source appear together once inside the methodology disclosure.
- All existing content, schema, ZIP behavior, tables, and disclaimers remain valid.
