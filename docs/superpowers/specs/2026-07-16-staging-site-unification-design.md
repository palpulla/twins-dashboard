# Staging Site Unification Design

**Status:** APPROVED

**Approved by:** Daniel

**Approval date:** 2026-07-16

**Implementation owner:** ChatGPT Profile 1 (`CHATGPT_PROFILE_1`)

**Parallel non-runtime contributor:** Claude Profile 3 (`CLAUDE_PROFILE_3`)

## Objective

Turn the private SiteGround staging copy into one consistent, complete, migration-ready Twins Garage Doors website experience. The staging site must use one shared brand system across customer-visible routes, eliminate the visual and accessibility failures found in the July 16 audit, and restructure important content around verified SEO/AEO best practices.

The resulting staging site must remain private and non-production. Production may be read and copied as a factual source, but this work does not authorize production writes, DNS changes, real submissions, live booking, real email, real lead delivery, or production integrations.

## Evidence and Root Cause

The audit covered the Illinois hub, Reviews, Contact, Careers, Clopay collection/product pages, and Garage Door Spring Repair. The defects are systemic:

1. The portable `twins-brand-experience` system renders only the home, team, careers, reviews, and contact classifications.
2. Service, location, trust, article, builder, and preserved catalog routes still use the older staging-overhaul renderer and header.
3. Brand pages load both full CSS systems, allowing legacy anchor, heading, layout, and theme styles to override branded components.
4. Astra, Elementor, and WordPress wrapper widths and padding remain active around some generated pages.
5. `catalog-preserve` retains legacy Clopay markup with weak contrast, duplicate content, and inconsistent spacing.
6. Service pages sanitize and dump legacy WordPress prose into a generic white body rather than a designed answer-first page.
7. The Reviews component loads all 87 reviews into one carousel and creates one large pagination control for every review page.
8. Illinois phone data exists but the Contact template suppresses it because Illinois is marked as a preview market.
9. “Choose your service area” is plain text rather than an interactive control.

The implementation must remove these architectural causes rather than patch individual screenshots.

## Approved Approach

Use one portable brand system to own the customer-visible header, footer, page shell, typography, color contracts, buttons, market controls, and reusable content components on every chrome-enabled staging route.

The existing staging-overhaul plugin remains the WordPress integration and safety boundary. It will classify requests, validate fixed route identity, block production-capable behavior, supply verified regional data, and dispatch into portable renderers. It must stop presenting a second public-facing design system.

Campaign and legal preservation behavior remains bounded and explicit. Campaign pages may retain their approved isolated presentation where required. Legal content may keep a restrained editorial layout, but customer-visible shared chrome and host-shell behavior must remain consistent when chrome is permitted.

## Ownership and Parallel Work

### ChatGPT Profile 1 owns

- Shared header and footer.
- Shared CSS tokens, layout, responsive behavior, and contrast.
- Reviews presentation and interaction.
- WordPress host-wrapper reset.
- Route dispatch and runtime renderers.
- Service, location, catalog, builder, careers, reviews, and contact integration.
- Automated tests, staging deployment, browser verification, and final handoff.

### Claude Profile 3 may own only

- Route inventory documentation.
- Structured non-runtime SEO/AEO content drafts.
- New TDD red-phase tests.
- Source citations and factual-content handoff.

Claude must not edit existing runtime files, deploy, access SiteGround, mutate WordPress, or merge branches. ChatGPT will review and selectively integrate Claude’s work.

## Global Visual System

### Brand tokens

- Primary navy: existing Twins navy family.
- Primary gold: existing Twins gold family.
- Cream and white may be used for readable content surfaces.
- Lilita One remains the display typeface.
- Nunito remains the reading and interface typeface.
- Every component must set an explicit foreground color for its background.
- No component may rely on inherited legacy anchor or heading colors.

### Full-width behavior

- Header, footer, hero backgrounds, section backgrounds, trust ribbons, review regions, and conversion bands must span the viewport.
- Readable content remains inside a shared inner shell, generally 1320–1450px maximum depending on page family.
- Scoped resets must neutralize Astra, Elementor, and WordPress outer widths, margins, padding, empty title rows, and unexpected spacer containers only on approved branded staging routes.
- Content must not create pale side gutters unless the page design intentionally uses them inside a full-bleed section.
- There must be no unexplained gap between the shared header and the first content section.

### Shared header

Every chrome-enabled customer route uses the same header component:

- One staging warning rail.
- A clickable “Choose your service area” control.
- Current regional phone number.
- Large Twins logo that may cross the gold divider slightly.
- Consistent desktop navigation and accessible dropdowns.
- Book Online and Request a Quote actions.
- Consistent mobile menu and mobile call/quote action bar.
- One computed header-height contract used by any sticky in-page navigation.

The old compact header with “Local garage door help with clear next steps” and “Request Exact Quote” must not remain on ordinary customer routes.

### Market selector

The utility control opens or navigates to a keyboard-accessible market selector showing:

- Wisconsin: `(608) 420-2377`
- Kentucky/main: `(833) 833-2010`
- Illinois staging preview: `(815) 800-2025`

Illinois remains labeled as a private preview. Preview status must not hide its approved phone number. The implementation must not claim that Illinois call forwarding has been tested.

### Conversion controls

- Use “Request a Quote” consistently.
- Preserve “Book Online” as a separate prominent action.
- Buttons must have readable text, at least a 44px target, visible focus state, and explicit foreground/background colors.
- The gentle branded attention animation may remain, but must stop for reduced-motion users and pause during direct interaction.
- No white-on-white or dark-on-dark controls are permitted.

## Reviews Experience

The current all-record carousel is replaced with a bounded presentation.

### Homepage and regional hubs

- Three cards on wide desktop, two on tablet, one on mobile.
- Use a curated, verified featured subset rather than all 87 records in the moving track.
- Autoplay interval must be at least 12 seconds.
- Autoplay pauses on hover, focus, touch, pointer interaction, hidden document state, and reduced-motion preference.
- After deliberate user navigation, autoplay remains paused for that session.

### Reviews page

- No autoplay.
- Provide a compact verified-source summary.
- Display concise cards with accessible expansion for long review text.
- Keep a crawlable collection of verified reviews without forcing all records into one carousel track.
- Use separate branded Previous and Next controls.
- Show a bounded indicator such as “2 of 8” or no more than five visible pagination indicators.
- Controls may never overlap each other or the cards.
- Cards must not all stretch to the height of the longest review.

## Page-Family Contracts

### Service pages

Important service pages must use a shared answer-first template rather than raw legacy body output.

Each page contains:

1. One unique H1.
2. A 40–60-word direct answer.
3. When the service is needed.
4. Safety guidance.
5. What the technician checks.
6. Repair or replacement options and tradeoffs.
7. What happens during service.
8. What the customer can prepare.
9. Local service coverage and regional phone.
10. Four to six natural-language FAQs.
11. Relevant internal links.
12. Real owned team, fleet, or work imagery where available.
13. A clear call and Request a Quote path.

The Garage Door Spring Repair page must explicitly state that springs are under dangerous tension and should be handled by trained professionals. It must remove DIY replacement instructions and unsupported “#1” language.

### Location pages

- Use the same shared shell and page components.
- Include genuinely distinct, supportable regional facts.
- Do not fabricate local stories, technicians, landmarks, customer proof, addresses, or response times.
- Thin Illinois city pages remain private and `noindex` until adequate verified content exists.

### Clopay collections and product pages

- Replace ordinary `catalog-preserve` presentation with a local catalog-detail renderer backed by the frozen repository catalog.
- Use manufacturer attribution and extractable facts such as construction, insulation choices, designs, colors, windows, and selection guidance.
- Hero photography must use a strong contrast overlay or a separate solid text panel.
- Remove duplicate headings and concatenated links.
- Provide a consistent route into the safe local door-builder preview.
- The disabled-integration state must look intentional and helpful, not like a broken technical notice.

### Door builder

- Continue using only frozen local assets and in-memory staging behavior.
- Do not contact manufacturer, booking, lead, email, analytics, or production services.
- Retain honest “manufacturer reference” language.
- Keep the selected options and reference imagery clearly separate.
- Confirm responsive keyboard, touch, and screen-reader behavior.

### Careers

- Use the same full-width shell and shared header.
- Fix the in-page navigation overlap by using the shared header-height contract or making the subnavigation non-sticky.
- Ensure all CTA text is visible and nonempty.
- Show authentic crew/work imagery near the beginning of the page.
- Present verified work locations, real open roles, responsibilities, requirements, benefits, and hiring steps only when confirmed.
- Generic interest collection must not use `JobPosting` schema.

### Contact

- Provide a compact, useful introduction immediately after the header.
- Show all approved regional phone numbers, including Illinois staging.
- Keep the quote preview inert on staging.
- Market cards and utility selector must link consistently to the correct regional routes.

### Articles, trust, campaigns, and legal pages

- Articles and trust pages use the same shared chrome and readable editorial components.
- Campaign routes may preserve an explicitly approved isolated landing-page layout.
- Legal pages retain exact legal content and must not be rewritten for marketing.
- No route may accidentally inherit a second public-facing header or footer.

## SEO and AEO Rules

- One unique H1 aligned with the route and market.
- Unique title and meta description.
- A concise direct-answer block near the top.
- Natural follow-up questions and answers.
- Descriptive, verified internal links.
- Descriptive alt text for owned imagery.
- Only verified reviews, dates, prices, warranties, addresses, certifications, service areas, and phone numbers.
- No fabricated superlatives or unsupported rank claims.
- Appropriate `Service`, `Product`, `FAQPage`, and `LocalBusiness` structured data only after rendered validation and only when the underlying facts support it.
- Staging remains `noindex`.
- Illinois remains unpublished and `noindex` until separately approved.

## Asset and Cache Contract

- Do not use a permanent literal asset version such as `1`.
- CSS and JavaScript URLs must use a content-derived version or repository-controlled build version so staging changes cannot remain hidden behind stale cache.
- All images required for staging must be local, owned, approved, or frozen manufacturer reference assets.
- Do not hotlink external images.

## Accessibility and Interaction

- Meet WCAG AA contrast for normal text and controls.
- All controls must work by keyboard and expose accurate names and state.
- Minimum target size is 44px.
- Focus must remain visible.
- Overlays must not intercept the page while hidden.
- Reduced-motion preference disables decorative and automatic motion.
- Responsive layouts must work at 320, 360, 390, 480, 768, 900, 1200, and wide-desktop viewports.

## Test-Driven Implementation

Every behavior change follows red-green-refactor:

1. Add a failing contract, PHP harness, or browser test reproducing the defect.
2. Run it and confirm it fails for the intended reason.
3. Implement the smallest runtime change that satisfies it.
4. Run the focused test and the relevant existing suite.
5. Refactor only while tests remain green.
6. Commit the independently reviewable unit.

Required automated coverage includes:

- One header variant across route families.
- Full-width host-shell behavior.
- No unexplained header/content gaps.
- Illinois phone and interactive market selector.
- Bounded review controls and autoplay policy.
- Explicit contrast and visible CTA states.
- Careers subnavigation geometry.
- Service AEO structure and prohibited spring language.
- Clopay contrast, single heading, and safe builder path.
- Asset cache versioning.
- Staging network and submission safety.

## Deployment and Verification

- Build and test locally first.
- Run the repository checker and complete test suites.
- Deploy only to the private SiteGround staging copy.
- Use a temporary staging-only SSH key when deployment access is required.
- Verify the host fingerprint through approved SiteGround evidence.
- Deploy once per verified release attempt; do not make production changes.
- Purge staging cache after a successful deployment.
- Perform an authenticated browser crawl across representative routes in every page family and desktop/mobile viewport.
- Delete the temporary SSH key locally and remove the imported public key from SiteGround after verification.

## Completion Criteria

The work is complete only when:

- All chrome-enabled customer routes use one shared brand system.
- Screenshots no longer reproduce the identified overlap, contrast, gap, width, phone, and consistency failures.
- Important services and Clopay pages use structured branded templates rather than raw legacy output.
- All existing and new tests pass.
- Repository checker passes.
- The private staged site is visually and functionally verified.
- Production remains unchanged.
- Final handoff documents commits, deployment evidence, remaining factual unknowns, and production migration prerequisites.
