# Location Page Garage Door Textures — Design

**Date:** 2026-07-22
**Status:** Approved visual direction
**Scope:** Twins Garage Doors location pages only

## Context

The rebuilt location page has strong typography, service imagery, branded cards, and a garage-door system illustration. Its large cream, white, and navy section backgrounds still feel flat in comparison. The approved reference is the Twins Zoom background, which uses low-contrast recessed garage-door panels around the edges while preserving a calm center.

## Goal

Give every location page a more dimensional and unmistakably garage-door-specific visual identity without reducing readability, accessibility, responsiveness, or performance.

## Non-goals

- Do not apply the texture system to service, editorial, home, catalog, or builder pages in this release.
- Do not add decorative raster images or new external assets.
- Do not add animation, interaction, JavaScript, or content changes.
- Do not place strong texture directly behind body copy, headings, buttons, or form controls.
- Do not reproduce the Zoom background literally; use it as a directional reference.

## Chosen Direction

Use **Framed Door Panels**: recessed sectional-door geometry appears along the left and right edges of selected location-page sections. The center remains visually quiet. Light sections use cream or white embossed panels; dark sections use deeper navy panels. Restrained gold track or hinge details appear only at a few high-value transitions.

This direction was selected over:

- A full-width embossed door texture, which would become repetitive and busy behind long content.
- A hardware-blueprint texture, which feels more technical but less architectural and less aligned with the provided reference.

## Visual System

### Panel construction

The texture is CSS-only and built from pseudo-elements, borders, gradients, inset shadows, and bounded opacity. Each decorated section owns its left and right framing. Decorative layers use `pointer-events: none`, remain outside the accessibility tree, and sit behind section content.

Each panel frame includes:

- one partially off-canvas raised or recessed panel block;
- two internal rectangular panel impressions;
- subtle highlight and shadow edges to imply depth;
- an optional narrow gold track or compact hinge cue;
- a center fade that guarantees a clean reading field.

### Section rhythm

The visual system must not repeat at equal strength on every section.

- **Hero:** Keep the existing horizontal sectional-door seams. Refine the edge depth only if needed to connect it to the new system.
- **System band:** Use a dark embossed edge treatment at medium strength around the animated door illustration and heading.
- **Services:** Use light side panels at low strength so the service cards remain dominant.
- **Guidance:** Use one stronger cream/white edge frame, weighted away from the warning card.
- **Process:** Use shallow panel geometry and a limited gold track cue behind the three steps.
- **Branch:** Use dark navy panels at medium strength, echoing the Zoom background.
- **Nearby and FAQ:** Use the lightest treatment, alternating the dominant edge to avoid visual repetition.
- **Final CTA:** Retain the existing visual priority; use only a faint framing echo if the section otherwise feels disconnected.

## Layering and Content Safety

Every decorated section becomes a bounded stacking context. Decorative pseudo-elements sit at the background layer. Existing headings, copy, cards, links, art, and CTAs remain above them with no structural markup changes.

The texture must never cross the central content measure at an opacity that competes with text. A neutral or section-colored center mask may be used when needed. CSS failure must degrade to the current flat background with all content intact.

## Responsive Behavior

### Desktop

- Panels may occupy roughly 14–22% of each outer edge and remain partially off-canvas.
- The central 60–72% of the section stays visually calm.
- Stronger dimensional shadows are allowed because sufficient whitespace remains.

### Tablet

- Narrow the panel width and lower opacity.
- Keep all decorative geometry clear of stacked headings, cards, and warning content.
- Preserve the existing one-column layout behavior.

### Mobile

- Collapse full-height side frames into shallow corner or edge fragments.
- Remove optional hinge details when they compete with content.
- Keep at least one recognizable garage-door panel cue in major sections.
- Guarantee `scrollWidth <= clientWidth` at 390px, 360px, and 320px.

## Accessibility and Performance

- Preserve current foreground/background contrast for all text and controls.
- Use no motion; reduced-motion behavior remains unchanged.
- Decorative layers accept no focus, pointer, or screen-reader interaction.
- Use no network requests and no added asset bytes.
- Avoid filters or effects likely to cause costly full-page repainting.

## Implementation Boundaries

Primary implementation file:

- `website/twins-brand-experience/assets/css/twins-brand.css`

Contract coverage:

- `website/twins-brand-experience/tests/contracts/location-page-overhaul-contract.test.cjs`

No PHP template change is expected unless testing proves that the existing section boundaries cannot provide safe stacking contexts. Any markup change must remain decorative-only and contain no content or runtime behavior.

## Verification

The implementation is complete only when:

1. Contract tests prove the location texture selectors and responsive rules exist.
2. The complete 81-test contract suite passes.
3. Package build and repository integrity checks pass.
4. Desktop and mobile browser checks confirm no overflow, content obstruction, contrast regression, console error, or broken click target.
5. Rockford staging is visually inspected at desktop and 390px widths.
6. The same CSS works across the shared location-page template without city-specific styling.

## Release Strategy

Package the CSS through the existing immutable private-staging release flow. Deploy only after local verification. Validate the live Rockford route first, then spot-check one additional Illinois location to confirm that the treatment is template-wide rather than page-specific.
