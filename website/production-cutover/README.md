# Production cutover kit

Wiring, runbooks, and decisions for taking the overhaul from staging
(`danielj140.sg-host.com`, at r18) live on `twinsgaragedoors.com`, on branch
`claude/staging-remediation`. This directory is **outside both deploy manifests
on purpose**: it holds production-only wiring that the staging safety contracts
forbid inside the deployed packages (live booking host, live form endpoint,
production domain, a production loader). Nothing here executes on staging.

## Start here

`owner-gate-checklist.md` is the road to the go-decision — the gates only the
owner can clear (business calls, access, verified backup, authorizations). Work
that first; it hands off to `release-runbook.md` step 1.

## Contents

**Runbooks**
- `release-runbook.md` — the go-live sequence (preconditions → build → preflight
  → rehearsal → release → verify → rollback).
- `otto-removal-runbook.md` — remove Metasync/Search Atlas OTTO from production
  (validated on staging).
- `redirect-plan.md` — captured Rank Math table reconciled with the new route
  registry, the redirects to change/add, and the standing `redirect_guess`
  caveat.
- `blog-prune-list.md` — the 69 pruned posts (already unpublished on the clone),
  the 301→`/blog/` scope, and the once-published slugs that need redirects.

**Decisions / status**
- `owner-gate-checklist.md` — the gate tracker + go-decision.
- `page-signoff.md` — the 2026-07-20 page-by-page pass (all types signed off;
  the location-essay finding fixed and deployed as r18).

**Production package (build path)**
- `production-build-spec.md` — what the production package contains and the two
  blockers (B fixed, A designed).
- `blocker-a-build-unseal.md` — the reviewed un-seal design for
  `build-packages.mjs` + the production manifest derivation (owner-authorized,
  not applied).
- `production-adapters.php` — production booking/quote/careers adapters
  (execution-proven by `production-adapters-harness.php`).
- `production-callback.js` — the callback-form submission script (production-only
  asset; staging JS forbids fetch).
- `production-overhaul-loader.php` — the production mu-plugin loader (boots the
  overhaul under `WP_ENVIRONMENT_TYPE=production`; the staging loader refuses
  non-staging boot and is never deployed).
- `blog-revamp/` — the revamped blog meta/body batches for the production replay.

## The three adapter swaps at cutover

`brand-runtime.php` now branches on `WP_ENVIRONMENT_TYPE === 'production'` (the
env branch shipped this session, inert on staging), swapping in these adapters:

1. **Booking (DECIDED: Housecall Pro).** `ProductionBookingAdapter` returns the
   external HCP link. Every "Book Online" control goes live in one move.
2. **Quote (DECIDED: LP-style callback form).** `ProductionQuoteAdapter` renders
   a real `<form>` that POSTs JSON to the existing Supabase edge function
   `https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/lp-lead-intake` (the
   endpoint the Madison LP uses today). Payload:
   `{ name, phone, email, zip, message, service, page, form_variant,
      chooser_token, consent, website }` where `website` is the honeypot and must
   be empty. Before go-live, confirm `lp-lead-intake` handles pages outside
   `/madison-garage-door-repair-lp/` (it receives `page: location.href`) and tag
   these leads distinctly (`form_variant: "site-callback"`).
3. **Careers.** `ProductionApplicationAdapter` sends applications to the
   production careers flow (`/careers/#apply`; GHL location
   `iRUlbIBg7PzSfLrPiR2j` / page 2322 — confirm the final embed at cutover).

The overhaul's classified-output form scan is environment-gated so the production
callback form is allowed (Blocker B); on staging the form contract is unchanged.

## Also required at cutover

- Reviews live counter: server-side Places fetch (key WITHOUT referer lock)
  refreshing the `config/review-summary.php` shape (currently 4.9 / 699,
  re-verified 2026-07-20).
- IL stays dark until the owner proves (815) 800-2025 forwarding.
- Server-side WI metro phone rendering already ships; the JS rewrite stays as
  belt-and-suspenders for any preserved legacy bodies.
