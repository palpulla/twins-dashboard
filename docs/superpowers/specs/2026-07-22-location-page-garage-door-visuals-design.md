# Location Page Garage-Door Visual Enhancement

**Date:** July 22, 2026  
**Status:** Approved design direction; awaiting written-spec review  
**Selected approach:** Option A — Door-system showcase

## Goal

Strengthen the visual garage-door identity of location pages without weakening the improved copy, real technician photography, or conversion flow. The animated garage door currently placed in the final call to action will move into the upper page, where it can establish the product and system story before the service cards.

## Page Composition

The existing location-page order remains intact except for one new visual band:

1. Technician hero
2. Yellow review and trust bar
3. New complete-door-system visual band
4. Service-card section
5. Local guidance, process, branch, nearby areas, FAQs, and final CTA

The new band will use the existing animated `door-open` inline SVG. It will pair the visual with a short heading and one explanatory sentence about the garage door operating as a connected system. It will not add a long paragraph or another competing call to action.

## Service-Card Visuals

Each of the three service cards will gain a distinct existing inline SVG above its heading:

- Garage Door Repair: spring illustration
- Garage Door Opener Service: keypad illustration
- Garage Door Installation: complete closed-door illustration

The illustrations are visual identifiers, not separate content blocks. They will share a consistent size, background treatment, and alignment so the three cards still read as one system. Existing descriptions, checklists, and service links remain unchanged.

## Final CTA

The final CTA will no longer contain the animated opening door. It will use a smaller static closed-door mark, preserving garage-door identity while keeping the only motion higher on the page. CTA copy and actions remain unchanged.

## Responsive and Motion Behavior

On desktop, the new system band will display the animated door and explanatory copy side by side. On phones, the visual will stack above the text with bounded width and no horizontal overflow. The service illustrations will remain visible at compact sizes.

The existing reduced-motion behavior will continue to disable the opening cycle when a visitor requests reduced motion. The page will contain exactly one animated door instance.

## Accessibility and Technical Boundaries

All added illustrations will reuse the project’s dependency-free inline SVG component and remain decorative with `aria-hidden="true"`. The change will add no remote assets, scripts, forms, trackers, or network requests. Existing heading order, CTA destinations, phone context, and route adapters will remain unchanged.

## Verification

Implementation is complete only when:

- A contract proves the animated door appears in the new upper system band and not in the final CTA.
- The three service cards render the approved spring, keypad, and closed-door illustrations.
- The page contains one `door-open` illustration.
- Reduced-motion coverage remains intact.
- The full local contract suite and package verification pass.
- The remote staging dry run and controlled deployment pass.
- Live Rockford verification confirms the new hierarchy at desktop and 390-pixel mobile widths with no horizontal overflow.

## Non-Goals

This change will not replace the technician hero image, introduce stock photography, rewrite the service copy, add a gallery, add additional animations, or change production. It is a focused staging enhancement to the shared location-page template.
