# Twins Garage Doors Location Page Cinematic Hybrid Redesign

**Date:** 2026-07-22  
**Status:** Approved for implementation planning  
**Scope:** Location-page experience only; Rockford is the reference implementation  
**Direction:** 60% cinematic command-center visual language / 40% established Twins website language

## Objective

Replace the rejected generic split-hero/card-stack treatment with a distinctive, modern, conversion-focused location page. The page must combine the depth and editorial drama of the cinematic company-scorecard reference with the recognizability and friendliness of Twins Garage Doors.

Success means the page feels purpose-built for a modern garage-door company, reads clearly on first scan, and drives either a free-quote request or an urgent phone call without looking like a literal analytics dashboard.

## Non-goals and guardrails

- Do not say or imply that the Rockford location "recently opened."
- Do not invent response times, guarantees, ratings, certifications, review counts, or local facts.
- Do not alter booking behavior, analytics, routing, phone data, or location data.
- Do not redesign unrelated page types.
- Do not deploy this direction until the user approves a live local mockup.
- Preserve existing user changes in the worktree, including unrelated edits.

## Visual system

The page uses a cinematic navy canvas with controlled gold highlights, layered lighting, thin borders, subtle grid/orbit graphics, and restrained translucent panels. Garage-door panel geometry should appear as low-contrast architectural texture rather than a repeated decorative pattern.

Established brand elements remain visible: the current Twins logo, the existing rounded display typeface, real technician and garage-door photography, accurate service content, and the twin characters. Gold is reserved for the principal action, active states, and selected headline words. Warm off-white sections interrupt the dark canvas so the experience retains the approachable family-business character of the existing site.

The design must not become either of these extremes:

- a generic traditional service website with a boxed split hero and repeated card grids;
- a literal business-intelligence dashboard filled with charts, side navigation, or data visualizations that do not help a homeowner.

## Header and hero

The compact site header sits within or immediately above the cinematic stage and preserves familiar navigation, logo, phone access, and quote access.

The hero is one integrated composition rather than text beside a framed photograph:

- An oversized, short headline uses the existing Twins display font and highlights one phrase in gold.
- Real technician or truck photography bleeds into the background with deliberate masking, lighting, and depth.
- Garage-door panel texture, a faint grid, and orbit/ring lines establish the automotive-command-center mood without competing with copy.
- A focused proof cluster may use up to three compact translucent panels. Every displayed claim must come from verified site data.
- "Get a Free Quote" is the dominant gold action. Calling is the persistent secondary action and remains prominent for urgent problems.
- One twin character may appear at the lower transition as a small brand signature, not as the hero's focal point.

The hero must communicate the service, location, homeowner benefit, and next action within the first viewport.

## Page structure and rhythm

The page alternates high-contrast cinematic modules with warmer, simpler service content. Repeating identical rounded cards is prohibited; every section needs a distinct compositional purpose.

1. **Cinematic hero:** integrated photography, headline, proof, primary quote action, secondary phone action.
2. **Complete garage-door service:** a dark service pathway that connects repair, installation, openers, and maintenance as parts of one system rather than four generic cards.
3. **Homeowner problem modules:** warm off-white content pairing real garage-door imagery with benefit-led copy for issues such as broken springs, stuck doors, noisy openers, unsafe movement, and damaged panels.
4. **How service works:** a horizontal three-step command sequence on larger screens and a clear vertical sequence on small screens.
5. **Local trust:** accurate Rockford-area details, verified review/proof content, and clear expectations without fabricated promises.
6. **FAQ:** a clean, low-decoration disclosure pattern that answers practical booking and service questions.
7. **Final conversion stage:** cinematic garage-door texture, concise closing copy, one twin-character signature, quote action, and phone number.

## Twin characters and garage-door imagery

Twin characters are brand signatures, not decorative wallpaper. Use them at a maximum of two or three meaningful transitions across the page. Motion should be a subtle 3–5 px float, small entrance, or contextual gesture. They must never overlap important text, controls, or photography and may be hidden when mobile space is constrained.

Garage-door imagery must be visually present throughout the page through real doors, technician context, panel geometry, track/hinge details, or controlled sectional-door animation. Texture remains low contrast and cannot reduce readability.

## Copy and conversion

Copy begins with recognizable homeowner problems and outcomes. Headlines remain short and confident. Supporting copy explains enough to reduce uncertainty without becoming a wall of text.

Primary action: **Get a Free Quote**.  
Urgent secondary action: **Call** using the verified location phone number.

The page should address, where supported by existing content:

- stuck or non-opening doors;
- broken springs and cables;
- noisy or unreliable openers;
- damaged panels or tracks;
- unsafe or uneven door movement;
- replacement and installation needs.

All location-specific statements and proof points must resolve from the existing content/configuration layer rather than being embedded only in Rockford markup.

## Responsive behavior

Mobile keeps the cinematic identity but does not reproduce cramped desktop overlays. The order becomes headline, supporting copy/actions, full-width photography, then stacked proof panels. Dense art direction such as orbit lines and layered glass panels is reduced when it threatens clarity.

A sticky mobile action bar provides Call and Free Quote actions without covering page content. Touch targets must be at least 44 px, text must remain readable without zoom, and the page must have no horizontal overflow.

Required visual checks: 320, 360, 390, 768, 1024, and 1440 px widths.

## Motion and accessibility

Motion is restrained and purposeful:

- slow ambient lighting or background drift;
- controlled section/panel reveals;
- small twin-character movement;
- one subtle sectional garage-door opening effect where it reinforces the subject.

Avoid constant bouncing, aggressive parallax, hover-dependent meaning, or heavy shadows. `prefers-reduced-motion: reduce` must produce a stable static layout with no missing content. Keyboard focus, semantic heading order, contrast, and disclosure controls must remain usable.

## Implementation boundaries

Build this as an evolution of the current location-page system. Reuse verified content sources, routes, components, assets, and conversion behavior where they remain correct. Replace the rejected visual composition instead of merely layering new CSS over its split-hero and card-stack skeleton.

The first implementation target is a local Rockford mockup. After visual and functional approval, the same system may be applied to other location pages through shared components and per-location content.

## Acceptance criteria

- The first viewport is visibly different from the rejected split-hero layout and reads as a single cinematic composition.
- The result clearly blends the cinematic reference and the established Twins site rather than copying either one wholesale.
- The original logo and display font remain recognizable.
- Real garage-door/technician imagery and low-contrast garage-door texture are present.
- Twin characters appear as restrained animated signatures and never obstruct conversion content.
- Quote and phone actions work using existing verified behavior.
- No "recently opened" copy or fabricated claims appear.
- Sections do not collapse into a repeated generic card grid.
- Mobile layouts pass the specified widths with no overflow or covered content.
- Reduced-motion mode remains complete and readable.
- Existing routing, analytics, booking, and location configuration remain intact.
- A local live mockup is presented to the user before any staging deployment.

## Approval record

The user selected Direction A (60% cinematic / 40% original Twins) and separately approved the visual system, hero composition, supporting-page structure, mobile/motion behavior, and content/conversion rules on 2026-07-22.
