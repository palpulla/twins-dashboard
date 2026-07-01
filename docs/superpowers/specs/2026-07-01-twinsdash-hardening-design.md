# twinsdash.com Hardening: Security Audit + Ranked Fixes + Code-Health Ratchet

**Date:** 2026-07-01
**Status:** Approved design, pending implementation plan
**Target:** `twins-dash` repo (Vite app at twinsdash.com) + Supabase edge functions on project `jwrpj`

## Goal

Bring twinsdash.com up to standard on security, stability, and code health:
no unverified open endpoints, no XSS holes, database policies audited, and
strictness ratcheted up on the codebase, all without destabilizing a live app.

## Non-goals / invariants (hard rules)

- **KPI and payroll math stay byte-identical.** No fix may alter dashboard
  numbers or payroll outputs. Any change near that code is verified against
  existing tests and, where applicable, output parity checks.
- **Webhooks are never disabled or interrupted.** The HCP/GHL webhooks are the
  live ingest pipeline. Validation rolls out log-only first (see Phase 2).
- **No new alerting.** Any health signal produced by this work is silent
  observability (tables, in-dashboard pills), never email/SMS/push.
- **No mass rewrite.** We do not flip TypeScript strict mode repo-wide or
  bulk-fix 7,900 lint warnings in one pass. Strictness applies to new and
  changed code via a CI ratchet.
- **Everything reversible.** All work on branches, merged via small PRs, each
  independently revertable.

## Current state (survey, 2026-07-01)

- 592 TS/TSX files, ~78k lines. Vite 5 + React 18 + TypeScript 5.9 + Supabase.
- No hardcoded secret keys or passwords found in source.
- 76 edge functions: 13 use `requireAdminAuth()`, ~62 accept unauthenticated
  requests (webhooks, cron, internal sync); webhook signature validation not
  observed.
- 3 uses of `dangerouslySetInnerHTML` (chart.tsx theme CSS: safe constant;
  SuggestionsDrawer and StreakCard: data source unverified).
- All edge functions send `Access-Control-Allow-Origin: *`.
- tsconfig: `strict: false`, `strictNullChecks: false`; ~7,896 ESLint findings
  (mostly `no-explicit-any`).
- 113 test files (Vitest 4). Dependencies (82 direct) well-curated.
- Largest files: `pages/payroll/Run.tsx` (1,810 lines),
  `pages/payroll/PartsLibrary.tsx` (1,038), `lib/kpi-calculations.ts` (1,034),
  `pages/Index.tsx` (970).

## Phase 1: Audit (no code changes)

Five streams. Output: one ranked findings report committed to the repo
(`docs/superpowers/specs/<completion-date>-twinsdash-hardening-findings.md`,
dated the day the audit finishes), each
finding with severity (High / Medium / Low), evidence, and proposed fix.

1. **Database wall.** Run Supabase security + performance advisors on jwrpj.
   Manually read RLS policies on sensitive tables: payroll tables,
   `user_roles`, `invited_emails`, `hcp_data`, accountability/point-system
   tables. Verify anon and technician roles cannot read or write beyond their
   scope.
2. **Edge function auth matrix.** Classify all 76 functions:
   admin-authed / service-internal / external webhook / intentionally public.
   Verdict per function: "correctly open," "needs auth," or "needs signature
   validation." Special attention to `hcp-webhook`, `ghl-webhook-*`,
   `review-redirect`, and every `sync-*` / `email-*` function.
3. **XSS.** Trace data flow into the SuggestionsDrawer and StreakCard
   `dangerouslySetInnerHTML` sinks; confirm whether any attacker-controllable
   text (customer names, job descriptions, HCP/GHL fields) can reach them.
   Confirm chart.tsx remains constant-only.
4. **Client bundle.** Review env/key handling, localStorage token usage, and
   whether anything sensitive ships in the built JS. Confirm the anon key +
   RLS posture is sound (expected: yes, that is the Supabase model).
5. **Dependencies.** `npm audit` on the 82 direct dependencies; flag known
   vulnerabilities with upgrade paths.

## Phase 2: Fixes, worst first

Findings fixed in severity order, one small PR per finding (or per tight
cluster). Each PR: tests green, preview smoke-checked, merged, live site
smoke-checked.

**Webhook validation (the delicate one), two-step rollout:**

1. Deploy signature/secret validation in **log-only mode**: every request is
   accepted exactly as today, but requests that would fail validation are
   logged to a table.
2. After several days of clean logs against real HCP/GHL traffic, flip to
   enforce. If the provider does not support signatures, fall back to a
   secret path token or header check, same two-step rollout.
   Rollback at any point is a one-line flag change.

Expected fix categories (final list comes from the audit): webhook
validation, auth added to wrongly-open edge functions, RLS policy gaps,
XSS sink hardening (sanitize or replace raw HTML rendering), CORS narrowed
where a function is browser-facing rather than webhook/cron, dependency
upgrades for real vulnerabilities.

## Phase 3: Code-health ratchet

- **ESLint safe auto-fix pass:** only rule fixes that cannot change behavior;
  reviewed as its own PR.
- **CI strictness ratchet:** new and changed files must pass strict
  TypeScript checks (via a CI script that type-checks touched files against a
  strict config). The codebase gets stricter every time it is touched; no
  big-bang migration.
- **Oversized files:** split only when a Phase 2 fix already touches them.
  No refactoring-for-its-own-sake PRs.
- **Dead code:** delete anything the audit proves unused.

## Verification and done criteria

- Existing Vitest suite green on every PR.
- KPI/payroll parity confirmed for any PR near that code.
- Supabase advisors re-run at the end as the "after" scorecard: clean, or
  only items Daniel has explicitly accepted.
- Findings report shows every High/Medium item fixed or explicitly accepted.
- Webhook validation enforcing; CI ratchet merged and running.

## Decisions made during design

- Approach chosen: audit deep, then fix ranked ("A"), over fix-known-list-now
  and guardrails-first.
- Driver: general hardening, no specific incident.
- Depth: full audit, fixes in ranked order, small reversible PRs.
