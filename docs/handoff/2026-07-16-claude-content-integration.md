# Claude Content Integration Review

Date: 2026-07-16
Account: `CHATGPT_PROFILE_1`
Target branch: `codex/staging-site-safety`
Claude review branch: `claude/staging-content-aeo`

## Outcome

The Claude lane was reviewed selectively. No Claude commit was cherry-picked, and no
runtime content was changed.

The current five fixed service records in
`website/twins-brand-experience/config/page-content.php` remain the approved
runtime source. They conform to the Task 6 registry, preserve market-specific
contact context, use conservative repository-supported claims, and already cover
every useful concept in the Claude drafts more safely.

No sentence from the Claude draft pack was both:

1. supported by the approved repository evidence; and
2. materially better than the current fixed record.

## Material reviewed

The review covered:

- Claude commit `6047c9ba9079039bd1fbce7ba06d4bb89cdf389c`
  (`docs(staging): route inventory, verified content pack, TDD red phase`);
- Claude commit `29e6a92fb8c8f193cc9628ea7dadc2e6e239d311`
  (`docs(handoff): staging content/AEO lane handoff for Profile 1`);
- all 18 files added by those commits;
- the five service JSON drafts and six non-service JSON drafts;
- the route matrix, claim audit, README, handoff, and two proposed contract
  suites;
- the current fixed registry, service template, market/contact context, route
  classifier, approved design, and Task 6 contracts;
- the independent audit in
  `/private/tmp/claude-content-integration-audit.md` and the selective review in
  `/private/tmp/task8-integration-ready.md`.

## Ownership review

Claude stayed inside its assigned additive lane:

- documentation;
- non-runtime JSON drafts;
- new proposed tests; and
- a handoff.

Neither Claude commit modified existing PHP, CSS, JavaScript, deployment tools,
SiteGround state, WordPress state, or production files.

That clean ownership result does not make the branch safe to take wholesale.
The proposed data interface, loader contract, route assertions, and factual copy
conflict with the approved runtime.

## Concepts already incorporated

The following repository-supported concepts appear in the Claude material but
are already expressed more safely in the current runtime:

| Concept | Current approved treatment |
| --- | --- |
| Exact price is reviewed before work begins | Present in the applicable fixed service records without promising a universal price or outcome. |
| Springs require professional handling | The spring record explicitly says springs are under dangerous tension and should be handled by trained professionals, then prohibits replacement, adjustment, winding, unwinding, or release. |
| Door-builder imagery is a reference | The installation record separates reference imagery and selected options from final appearance confirmation. |
| Emergency availability and response timing require confirmation | The emergency record makes no fixed hours, arrival, response, or completion promise. |
| Regional phone and coverage belong to request context | The shared records contain no phone number or hard-coded market; the runtime supplies the validated current contact. |

The only exact Claude strings already present are route titles and ordinary link
labels. They create no integration delta.

## Structural conflicts rejected

### Wrong route-family count

Claude's route matrix and handoff say there are 16 classifier families. The
classifier at Claude's base contains 15 fixed classifications:

- 5 brand classifications;
- 9 other chrome-enabled classifications; and
- 1 no-chrome campaign-preserve classification.

The proposed shared-header test also hard-codes `known.length === 16`, so its
first failure is the wrong route-count assertion instead of the intended header
contract. The helper then scans the wrong later family array. The test cannot be
promoted unchanged.

### Incompatible content schema

Task 6 requires exactly:

`h1`, `directAnswer`, `needs`, `safety`, `process`, `options`, `prepare`,
`faqs`, `links`.

Every Claude service record omits the required top-level `safety` field and adds
an incompatible draft-pack schema, including metadata, market, SEO, citations,
unknowns, and prohibitions. Those fields do not belong in
`PageContentRegistry`.

### Conflicting loader contract

The proposed test named `the staging renderer loads the verified content pack`
requires runtime JSON-directory loading. The approved architecture requires
reviewed text to be mapped deliberately into the fixed PHP registry. No draft
directory or caller-selected content source may become a runtime loader.

### Hard-coded shared phone

All five Claude service direct answers embed `(833) 833-2010`. One fixed service
record serves unprefixed, Wisconsin, Kentucky, and private Illinois paths.
Wisconsin uses `(608) 420-2377`, Illinois preview uses `(815) 800-2025`, and
path-specific contact context can supply another approved local number.
Embedding the main phone would create cross-market leakage.

## Factual copy rejected

The draft pack was not imported because it mixes supported ideas with claims
that are not established by the approved repository evidence.

Rejected categories include:

- cause rankings such as `most`, `often`, and `usually`;
- symptom-to-diagnosis claims made before inspection;
- fixed inspection, repair, installation, balancing, testing, or completion
  procedures;
- same-visit, response-time, parts-availability, vehicle-release, or property-
  securing outcomes;
- comparative price, lifespan, noise, temperature, maintenance, privacy, or
  performance claims;
- paired-spring, cycle-history, winding-bar, and customer troubleshooting
  instructions;
- claims that Twins currently offers every record in the 23-item frozen Clopay
  reference catalog;
- a static 87-review count/date and the overbroad claim that nothing on the
  reviews experience is curated or edited;
- contact copy that omits the private Illinois preview context;
- unconfirmed Careers claims about locations, requirements, workflow, or crew
  growth; and
- production-read observations that were not independently established as
  repository facts.

The current spring safety text is retained:

> Garage door springs are under dangerous tension and should be handled by
> trained professionals. Do not attempt to replace, adjust, wind, unwind, or
> release a spring.

## Per-record decision

| Claude draft | Decision |
| --- | --- |
| Garage Door Repair | Keep current fixed record; reject unsupported diagnosis, workflow, comparative, and hard-coded-phone copy. |
| Garage Door Installation | Keep current fixed record; reject unverified install workflow and material/opener tradeoffs. |
| Garage Door Spring Repair | Keep current fixed record; it is safer and contains the required explicit safety boundary. |
| Garage Door Opener Repair | Keep current fixed record; reject cause ranking, detailed diagnostics, parts predictions, and repair-versus-replace advice. |
| Emergency Garage Door Service | Keep current fixed record; reject fixed outcomes, parts, timing, securing, and vehicle-release promises. |
| Reviews | Do not place in the service registry; the implemented review runtime owns validated review data and movement behavior. |
| Contact | Do not place in the service registry; shared market/contact context owns current phone and preview status. |
| Careers | Do not place in the service registry; the implemented Careers template retains its verified, non-opening-specific copy. |
| Illinois hub | Do not place in the service registry; the market runtime owns private/noindex Illinois status and phone. |
| Clopay catalog drafts | Do not place in the service registry; the frozen Task 7 catalog runtime owns bounded product data. |

## Test disposition

Neither Claude test file was copied.

Useful concepts were already covered in the task-specific suites:

- fixed registry shape;
- 40–60-word direct answers;
- 4–6 FAQs;
- explicit spring safety;
- no inherited raw service body;
- no DIY spring instructions or unsupported `#1` claim;
- market-aware contact context and internal routes;
- review movement boundaries;
- Careers CTA visibility;
- shared chrome and full-width wrapper behavior; and
- frozen catalog/builder safety.

The proposed JSON loader assertion, route-count assertion, source-regex parser,
and weak `verifiedClaims` nonempty check were rejected.

## Safety and deployment boundary

This review:

- did not deploy;
- did not access or modify SiteGround;
- did not modify WordPress or production;
- did not change DNS;
- did not enable forms, booking, email, lead delivery, analytics, or any other
  integration;
- did not create a production package; and
- did not change the private/noindex Illinois policy.

Task 9 may proceed using the verified current runtime. The Claude branch remains
an archival source/audit lane, not an integration dependency.
