# Location Page Modern Conversion Redesign

Date: 2026-07-22
Status: Approved direction; implementation pending

## Goal

Modernize the Twins Garage Doors location-page presentation without erasing the recognizable brand. The result should feel premium, calm, and conversion-focused rather than like a contractor flyer, sports graphic, or cartoon poster.

## Non-negotiable brand elements

- Keep the existing rounded Twins display typeface for primary headings and CTA labels.
- Keep the existing Twins logo, navy-and-gold palette, real technician photography, and existing mascot identities.
- Keep the location-specific service copy truthful and avoid any language implying that a branch recently opened.

## Visual direction

- Use a disciplined editorial grid, strong alignment, and substantially more negative space.
- Use deep navy as the principal surface, off-white for readable copy, and gold only for emphasis, active states, thin rules, and the primary CTA.
- Use subtle garage-door panel texture at low contrast. Texture must support the page rather than compete with text or photography.
- Prefer sharp or lightly softened geometry. Avoid heavy shadows, thick repeated outlines, glossy gradients, glassmorphism, decorative seals, and badge clusters.
- Let real job photography carry the visual credibility. Use clean editorial crops and thin framing rather than bulky cards.

## Conversion hierarchy

- Present one dominant primary action: **Request a Quote**.
- Present the market phone number as a quieter secondary action.
- Place concise trust proof near the first CTA: Google rating, family ownership, and licensed/insured status.
- Shorten the hero body copy to one clear promise followed by the two actions and trust proof.
- Preserve descriptive service content below the fold, but make each section easier to scan through stronger hierarchy and spacing.

## Mascot treatment

- Mascots remain part of the personality but never dominate the composition.
- Use a maximum of three deliberate mascot moments across a full location page: one small hero/photo cameo, one contextual service or guidance cameo, and one restrained final-CTA appearance.
- Crop mascots to waist-up or partial silhouettes when possible. Integrate them into a photo edge, section boundary, or shallow illustration field.
- Do not use giant full-body mascots at opposite sides of a section.
- Do not wrap mascots in speech bubbles, circular seals, tip badges, or standalone promotional cards.
- Motion, when enabled, is limited to a slow 3–5 pixel float or subtle reveal. Reduced-motion mode remains static.

## Hero composition

- Compact single-row header with a smaller logo, simplified navigation, phone text link, and one gold quote button.
- Two-column desktop hero: conversion copy on the left and real technician photography on the right.
- Preserve the existing rounded display font for the main headline.
- Use one thin gold vertical or horizontal rule to structure the layout.
- Add a single small mascot cameo at the lower photo edge, occupying less than 10 percent of the desktop hero.
- Stack cleanly on mobile: copy, actions, proof, then photo. The mascot cameo may hide below 480 pixels if it threatens clarity.

## Supporting sections

- System/service sections use larger whitespace, restrained dividers, and one focal illustration or photo—not repeated boxed decorations.
- Service cards reduce border weight and rely on spacing, typography, and small gold markers for hierarchy.
- Guidance and safety content uses a single dark contrast panel with direct language and no decorative mascot competition.
- The final CTA uses one mascot at most, a short headline, one primary quote button, and one secondary phone link.

## Responsive and accessibility requirements

- No horizontal overflow at 320, 360, 390, 768, 1024, or 1440 pixels.
- Touch targets remain at least 44 by 44 pixels.
- Text and actions must never overlap mascots, texture, or photography.
- Decorative mascots remain empty-alt, aria-hidden, non-interactive, and pointer-events none.
- `prefers-reduced-motion: reduce` disables all decorative motion and transforms.
- Color contrast remains WCAG AA for body text and controls.

## Success criteria

- The original rounded display font is visibly retained.
- The page reads as a modern premium service brand at first glance.
- The quote action is the strongest visual target without competing CTA clutter.
- Real technician photography is the primary proof element.
- Mascots feel like restrained brand signatures rather than pasted-on stickers.
- Desktop and mobile renders pass overflow, overlap, contrast, keyboard, and reduced-motion checks.

## Scope boundary

This draft redesign applies to the location-page experience only. It does not change booking integrations, form handling, analytics, service routing, market data, or production deployment authority.
