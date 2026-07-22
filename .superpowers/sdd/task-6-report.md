# Task 6 report — location conversion chrome

## Scope

Implemented only the Task 6 owned header, mascot, stylesheet, contracts, PHP renderer harness, and this report. No routes, analytics, booking adapters, manifests, packages, browser fixtures, screenshots, or deployment files were changed.

## Changes

- Added a `classification === 'location'` header branch with compact direct service links, normalized phone and quote actions, and a matching mobile drawer. The location variant emits neither a booking CTA nor booking dialog markup.
- Preserved the complete existing header, market utility row, and dialog/external booking behavior for non-location classifications.
- Applied Lilita One explicitly to every `.twins-brand-cta`; made location service-card links 44px inline-flex touch targets.
- Restricted the Twin component to exactly `left + hero`, `right + guidance`, and `right + final-right`; every other pair returns no markup.

## TDD evidence

- RED: focused component/location contracts initially reported 20 pass / 3 fail: absent location header classification, CTA/service-link styling, and exact Twin-pair guard.
- GREEN: focused component/location contracts then reported 23 pass / 0 fail.

## Verification

- PHP wrapper: 33 tests skipped, accurately marked `PHP CLI unavailable locally`; no PHP executable is available in this environment.
- Full Node contracts: 84 pass / 3 fail. The three failures are the intentionally stale CSS byte/hash entries in the staging and host-verification package manifests plus `site-unification.test.cjs`; Task 6 explicitly prohibits rebuilding or repinning manifests/packages, and Task 7 owns that follow-up.
- Owned assets check: passed.
- `git diff --check`: passed with no whitespace errors.

## Commit

Pending atomic commit `fix: complete location conversion chrome`.
