# Location Page Animated Twin Characters Design

**Date:** 2026-07-22
**Status:** Approved design
**Scope:** Registered location pages only

## Goal

Add recognizable Twins Garage Doors personality to the redesigned location pages by reusing the two existing illustrated technician characters at three purposeful story moments. The characters must make the long pages feel more branded and visually alive without competing with service information, calls to action, or the real technician photo in the hero.

## Existing System

The brand runtime already owns and deploys two transparent PNG illustrations:

- `assets/images/brand/twin-left.png`: arms-crossed technician, 196 by 534 pixels.
- `assets/images/brand/twin-right.png`: spring-holding, thumbs-up technician, 297 by 538 pixels.

Both files are already registered as the fixed asset keys `twin-left` and `twin-right`, included in the closed staging package, and animated on the homepage. The location template currently uses neither character. The location pages already share one template branch, a CSS-only garage-door texture system, real hero photography, inline garage-door illustrations, responsive layouts, and reduced-motion rules.

## Approaches Considered

### A. Signature story beats — selected

Place characters at the system explanation, safety guidance, and final call to action. This adds personality through the middle and end of the page while keeping each appearance connected to the surrounding content.

### B. Alternating section guides

Alternate one character on the left and right of nearly every major section. This is the most energetic direction, but it would compete with the service cards and make the page feel mascot-led rather than service-led.

### C. Hero duo and final call to action

Use both characters only at the top and bottom. This creates immediate recognition but leaves the long informational middle of the page visually unchanged, which does not solve the reported blandness.

## Selected Placement Design

The shared location template will render three character moments and four desktop image instances:

1. **System section:** the arms-crossed left Twin rises from the lower-left edge beside the animated garage-door system illustration. It reinforces expertise without entering the text column.
2. **Guidance and safety section:** the spring-holding right Twin rises from the lower-right edge beside the warning card. It visually supports the stop-and-call safety message without covering the warning copy.
3. **Final call to action:** the arms-crossed Twin and spring-holding Twin frame the content from opposite lower edges. The heading, paragraph, phone action, and quote action remain in a clean central safe area.

The hero will not receive a character. Its real technician photograph remains the primary human visual, and adding mascots there would weaken the hierarchy.

Every character is positioned within its section's visual boundary, sits behind the content layer, and has `pointer-events: none`. Text, links, and buttons always remain above the artwork and fully interactive.

## Component Boundary

Add a small location-character renderer under the existing component system rather than repeating raw `<img>` markup four times. The renderer will:

- Accept only the fixed character choices `left` and `right`.
- Accept only the approved placement tokens `system`, `guidance`, `final-left`, and `final-right`.
- Resolve the existing `twin-left` or `twin-right` asset through the current experience asset API.
- Emit intrinsic width and height values matching the source artwork.
- Emit an empty `alt` value and `aria-hidden="true"` because these repeated illustrations are decorative.
- Emit lazy loading and asynchronous decoding because every placement is below the hero.
- Return no markup for an unsupported character or placement token.

The location template will include this component only inside the existing location branch. Trust pages, editorial articles, service pages, the homepage, and other templates will not change.

## Visual Styling

All new selectors will be scoped beneath `.twins-location-page` and use a dedicated `.twins-location-twin` base class plus placement and character modifiers.

- Transparent PNG edges receive the same restrained navy drop shadow used by the homepage characters.
- Desktop characters sit partly against section edges to create depth without extending the document width.
- The system character uses `clamp(132px, 12vw, 176px)`.
- The guidance character uses `clamp(180px, 16vw, 218px)` so the spring remains legible.
- The final left character uses `clamp(128px, 11vw, 166px)` and the final right character uses `clamp(180px, 16vw, 224px)` to respect their source proportions and balance the centered call to action.
- Character layers do not replace or obscure the existing garage-door panel textures.
- Section content receives explicit stacking and safe-area spacing where required, rather than relying on incidental DOM order.

## Motion

Motion is CSS-only and intentionally subtle:

- Left-character motion uses a 4.8-second ease-in-out cycle. Right-character motion uses a 6.2-second cycle with a 0.7-second delay.
- Maximum vertical movement is 6 pixels.
- Maximum rotation is 1.25 degrees.
- Left and right characters use different durations and delays so they do not move in sync.
- The final pair alternates gently rather than bouncing together.
- No JavaScript, scroll listener, parallax, entrance observer, or new animation dependency is added.

Within `prefers-reduced-motion: reduce`, every new character animation is disabled and the static placement remains visible.

## Responsive Behavior

At desktop and tablet widths, all four character instances are visible.

At the existing mobile breakpoint:

- The system moment keeps the arms-crossed Twin fully contained at no more than 112 pixels wide.
- The guidance moment keeps the spring-holding Twin fully contained at no more than 142 pixels wide.
- The final call to action hides the left Twin and keeps the spring-holding, thumbs-up Twin at no more than 148 pixels wide.
- Each moment therefore shows no more than one character on mobile.
- Characters stay clear of headings, paragraphs, phone links, quote buttons, and the sticky mobile actions.
- The page must preserve `scrollWidth === clientWidth` at 390 by 844 pixels.

## Accessibility and Content

- Characters are decorative and must not add repetitive spoken labels.
- The empty `alt` value and `aria-hidden="true"` are required on every instance.
- Motion respects the user's reduced-motion preference.
- No heading, paragraph, link text, service claim, structured data, or location-specific content changes.
- No focusable or interactive element is introduced.

## Performance and Asset Safety

No new image asset is created. Both PNGs are already hash-pinned in the owned-assets and staging manifests. A location page will request the two unique files once each, 42,983 bytes total before normal transport compression and caching, even though the final section repeats the same URLs.

The implementation introduces no external request, runtime dependency, analytics event, storage, or data transport. Package and manifest identities will be regenerated only where the changed template, component, and stylesheet require it.

## Failure Behavior

- Unsupported renderer tokens fail closed by emitting no character markup.
- Missing fixed assets continue to use the existing asset resolver's fail-closed behavior.
- If viewport space is insufficient, the mobile rules reduce or hide artwork rather than allowing content overlap.
- If animation is unsupported or disabled, the static illustrations remain correctly positioned.

## Verification

### Automated contracts

Add or extend contracts that prove:

- The location output contains the `system`, `guidance`, `final-left`, and `final-right` placements.
- The fixed `twin-left` and `twin-right` asset keys are reused.
- Every rendered character has empty alternative text and is hidden from assistive technology.
- Unsupported renderer tokens produce no output.
- Non-location editorial output does not contain location-character markup.
- New CSS is scoped to `.twins-location-page`.
- Character layers cannot intercept pointer events.
- Reduced motion disables all new character animations.
- Mobile rules hide `final-left` and retain the approved single-character moments.

Run the complete contract suite, package build and check, repository gate, and whitespace check after implementation.

### Visual verification

Verify the private staging pages for Rockford and Loves Park at 1440 by 1000 pixels and at 390 by 844 pixels. Confirm:

- The three moments appear on both cities through shared template logic.
- Characters do not cover text, links, real photography, garage-door art, or panel textures.
- Motion feels slow and professional rather than playful or distracting.
- The final desktop pair balances the call to action.
- Mobile shows one character per moment and has no horizontal overflow.
- Reduced-motion mode leaves static characters in valid positions.
- No new browser console errors appear.

## Acceptance Criteria

The work is complete when all registered location pages receive the shared three-moment character treatment, the existing artwork and location content remain intact, automated gates pass, desktop and mobile staging checks show no overlap or overflow, and the final live staging bytes match the verified package.
