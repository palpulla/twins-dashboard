# Twins Garage Doors Location Page — Dark Showroom Revision

**Date:** July 23, 2026  
**Status:** Proposed for implementation  
**Primary route for review:** Rockford location page  
**Visual reference:** https://twinsgaragedoors.com/madison-garage-door-repair-lp/

## 1. Purpose

Revise the approved location-page redesign so the area below the hero feels modern, distinctive, and conversion-focused instead of pale, repetitive, or visually empty.

The approved direction is the **B-only dark showroom concept**:

- retain the current shared header and contained hero;
- redesign the trust, services, local-proof, and final-CTA sections as one coherent dark showroom system;
- add garage-door character through static panel textures, framed surfaces, and restrained yellow accents;
- use subtle, finite motion only where it improves hierarchy or feedback;
- selectively borrow the strongest visual and conversion patterns from the Madison landing page without copying its bulky form or unverified marketing claims.

This is a post-hero refinement, not another wholesale redesign.

## 2. Goals

- Give every post-hero section a clear visual role and intentional contrast.
- Make the service choices and primary calls to action faster to scan.
- Keep the brand recognizable through the established display type, navy, yellow, garage-door textures, and Twin characters.
- Feel current and polished without becoming a generic corporate dashboard.
- Preserve route-specific content, conversion behavior, accessibility, and the existing five-section page contract.
- Produce a locally reviewed desktop and mobile result before any staging deployment.

## 3. Scope and Page Structure

The existing five-section location-page structure remains:

1. contained hero;
2. trust/proof strip;
3. services;
4. local proof;
5. final call to action.

The shared header and approved contained hero remain structurally and visually unchanged. No new hero animation is introduced.

The redesign begins immediately after the hero and includes the trust strip, services, local-proof section, and final CTA. Navigation, phone actions, quote actions, booking dialog behavior, route handling, and existing structured data remain intact.

## 4. Visual System

### 4.1 Overall treatment

The post-hero page uses a dark-showroom visual language:

- deep navy fields as the dominant background;
- subtle, static garage-door panel lines or embossed framing in the background;
- yellow used as a focused spotlight for proof, selections, borders, and primary actions;
- off-white text and surfaces only where they create necessary contrast;
- controlled shadows and crisp borders rather than oversized imagery or heavy cinematic effects.

Textures are decorative and quiet. They must never reduce text contrast, compete with content, or create the appearance of clickable controls.

### 4.2 Trust and proof strip

The current pale trust area becomes a high-contrast yellow proof strip inspired by the Madison landing page.

It contains only verified information already available to the location renderer:

- Google rating and review count;
- Family owned;
- Licensed and insured.

The strip must not introduce same-day promises, “done today” language, a zero-dollar service-call offer, “most repairs in one visit,” guaranteed arrival language, or any other claim that is not explicitly supported by the Rockford location data.

On smaller screens, the items may wrap or stack while remaining compact and clearly separated.

### 4.3 Services

The services section becomes the main dark showroom:

- a dark paneled background;
- three clearly separated service cards;
- strong navy/yellow outlined construction;
- concise, legible service copy;
- full-card links with visible focus states and minimum 44-pixel targets.

One card may use a yellow surface to create compositional rhythm. If used, that treatment must not carry a badge, label, or copy implying that the card is preferred, recommended, or more important. The middle opener card is the default compositional choice.

Each card may include compact “what we fix” chips or checklist items derived only from services already supported by the page:

- **Garage door repair:** broken springs, cables and rollers, off-track or noisy movement;
- **Opener repair:** sensors, remotes, motors;
- **Garage door installation:** damaged-door replacement, style choices, insulation options.

These items live inside the three existing cards; they do not create a sixth page section.

A Twin character cameo may remain in this section as a static, subordinate accent. It must not overlap text or controls and may be hidden at 480 pixels and below when space is constrained.

### 4.4 Local proof

The local-proof section keeps the genuine before-and-after garage-door image and Rockford-specific content.

The revised composition uses:

- a dark navy field;
- a restrained yellow frame around the real image;
- strong outlined proof rows inspired by the Madison page’s scan-friendly cards;
- the verified Rockford address and existing location-specific copy;
- balanced image sizing so the photograph supports the message instead of dominating the viewport.

No new customer quote is added in this revision. Review quotations may be introduced later only from canonical, verified review data that is approved for public marketing use.

### 4.5 Final CTA

The final CTA uses:

- a dark paneled background;
- a compact, direct heading and supporting line;
- one yellow primary quote action;
- one outlined call action;
- static Twin characters framing the outer edges;
- the existing garage-door artwork, if retained, in a static state.

The characters must support the composition without obscuring the copy or controls. The CTA must not add urgency, offers, guarantees, or service promises beyond the verified current content.

## 5. Motion Contract

Motion is progressive enhancement and is intentionally restrained.

### Allowed motion

- Post-hero sections reveal once as they enter the viewport:
  - opacity from 0 to 1;
  - vertical translation of no more than 10 pixels;
  - approximately 420 milliseconds;
  - ease-out timing;
  - no replay after the section becomes visible.
- Service cards move upward by no more than 3 pixels on direct hover or keyboard focus:
  - approximately 160 milliseconds;
  - no scale effect.
- Primary CTA buttons may show a quick sheen only during direct hover or keyboard focus:
  - less than 180 milliseconds;
  - no autonomous repetition.

### Disallowed motion

- infinite Twin floating or bobbing;
- CTA pulsing;
- looping garage-door opening or closing;
- parallax, orbiting elements, or moving background textures;
- autonomous decorative animation;
- page-controlled smooth scrolling.

If `prefers-reduced-motion: reduce` is active, all location-page reveals, transforms, transitions, and sheen effects are disabled and content is immediately visible in its final static state.

The reveal behavior is fail-open. If `IntersectionObserver` is unavailable or JavaScript fails before initialization, content remains visible.

## 6. Content and Data Rules

- Preserve the canonical Rockford phone, address, rating, review count, and service data.
- Preserve the existing booking dialog rather than adding the Madison page’s callback form.
- Do not say or imply that the Rockford business “recently opened.”
- Do not add unsupported urgency, discounts, waived fees, completion-time promises, guarantees, or market-leadership claims.
- Preserve route-correct market data for all supported location pages; the Rockford review must not introduce Illinois copy into Wisconsin or Kentucky routes.
- Retain the existing service-page links, phone links, quote actions, and analytics hooks.

## 7. Responsive Behavior

The layout must be verified at 1440, 1024, 768, 390, 360, and 320 pixels.

- Service cards appear in three columns where space permits and stack cleanly on mobile.
- No section may create horizontal overflow.
- Images remain bounded and must not take over the screen.
- Twin characters never overlap headings, body copy, buttons, or sticky actions.
- The service-section Twin cameo may be hidden at 480 pixels and below.
- The existing mobile sticky actions remain collision-free.
- Existing hero media caps remain unchanged.
- Interactive targets remain at least 44 by 44 pixels.

## 8. Accessibility

- Text and controls must meet WCAG AA contrast requirements.
- Keyboard focus states must remain clearly visible without depending on motion.
- Decorative panel textures and character accents remain semantically inert.
- Document order and reading order remain unchanged.
- Card-wide links must not create nested interactive controls.
- Reduced-motion behavior is part of the acceptance criteria, not an optional enhancement.

## 9. Validation and Acceptance Criteria

### Contract coverage

Add or update contract tests to verify:

- the four post-hero sections use the dark-showroom styling hooks;
- the trust strip renders yellow with verified data;
- no location-page Twin, CTA, or garage-door element uses an infinite animation;
- section and card motion stay within the specified distance and timing limits;
- reduced-motion styles disable all location motion;
- all five existing sections and conversion controls remain present.

### Browser coverage

Verify:

- the hero remains unchanged;
- post-hero composition at all target widths;
- no overflow, clipped copy, collisions, or oversized photos;
- initial and final reveal states;
- the fail-open state without `IntersectionObserver`;
- the static reduced-motion state;
- service-card hover and keyboard-focus feedback;
- booking dialog open and close behavior;
- call and quote links;
- mobile sticky-action behavior.

### Visual approval

Capture fresh local screenshots at desktop and mobile widths and obtain user approval before any release rotation or staging deployment.

Asset manifests are rebuilt only in the final implementation task after source and browser checks pass. The current lack of a local PHP CLI remains a documented predeployment constraint; the PHP renderer gate must pass in a PHP-enabled environment before deployment.

## 10. Out of Scope

- redesigning the shared header or approved hero;
- changing the five-section architecture;
- adding a callback form;
- adding unsupported Madison landing-page claims or offers;
- adding unverified review quotations;
- changing route or market strategy;
- staging deployment, release rotation, or production deployment;
- broad redesign of non-location templates.

## 11. Implementation Boundaries

The implementation should primarily affect the location template, the location-specific brand stylesheet, the existing location reveal behavior, and their contract/browser tests. Shared components may be adjusted only where the location page can opt in without causing a visual or behavioral regression elsewhere.

Unrelated working-tree changes must be preserved.
