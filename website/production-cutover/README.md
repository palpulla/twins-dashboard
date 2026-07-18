# Production cutover kit

Prepared 2026-07-17 against branch `claude/staging-remediation` (staging
releases r1-r6 live on the private host). This directory is OUTSIDE both
deploy manifests on purpose: it contains production-only wiring that the
staging safety contracts forbid inside the deployed packages (live booking
host, live form endpoint, production domain). Nothing here executes on
staging.

Contents:
- `production-adapters.php` — drop-in adapters for the go-live WordPress
  install (HCP booking, live callback form, careers application).
- `redirect-plan.md` — the full captured Rank Math table, reconciliation with
  the new route registry, and required changes.
- `release-runbook.md` — the production release sequence with backups,
  verification gates, and rollback.

## The three adapter swaps at cutover

The brand chrome already branches on `environment === 'production'`; these
adapters supply the production behavior:

1. **Booking (DECIDED: Housecall Pro).** `ProductionBookingAdapter` returns
   the external HCP link. Every "Book Online" control goes live in one move.
2. **Quote (DECIDED: LP-style callback form).** `ProductionQuoteAdapter`
   renders the same callback form fields as staging but as a real `<form>`
   that POSTs JSON to the existing Supabase edge function
   `https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/lp-lead-intake`
   (the exact endpoint the Madison LP uses today). Payload contract captured
   from the live LP script:
   `{ name, phone, email, zip, message, service, page, form_variant,
      chooser_token, consent, website }` where `website` is the honeypot and
   must be empty; the function also accepts event beacons
   `{ event, page, variant, session_id }`. Before go-live, confirm
   `lp-lead-intake` handles pages outside `/madison-garage-door-repair-lp/`
   (it receives `page: location.href`) and tag these leads distinctly
   (suggest `form_variant: "site-callback"`).
3. **Careers.** Wire the application preview to the existing GHL careers
   pipeline (location `iRUlbIBg7PzSfLrPiR2j`, employment funnel per
   `docs/.../project_twins_employment_funnel`), or embed the production
   careers form from page 2322.

## Also required at cutover (from the decisions doc)

- Server-side WI metro phone rendering already ships; keep the JS rewrite as
  belt-and-suspenders for preserved legacy bodies.
- Reviews live counter: server-side Places fetch (key WITHOUT referer lock)
  refreshing the `config/review-summary.php` shape daily.
- IL stays dark until the owner proves (815) 800-2025 forwarding.
