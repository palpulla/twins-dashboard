# twins-dash Security Re-Audit + Safe Remediation

**Date:** 2026-05-28
**Repo:** `twins-dash` (live at twinsdash.com, Vercel-deployed, `jwrpj` Supabase)
**Predecessor:** `docs/superpowers/specs/2026-05-05-phase1-security-cleanup-design.md` (shipped)
**Source audit (stale):** `twins-dash/DASHBOARD_FULL_AUDIT.md` (2026-05-05)

---

## 1. Why this exists

Phase 1 (2026-05-05) locked down RLS, added HCP webhook signature verification, and introduced
`supabase/functions/_shared/auth.ts`. Since then the codebase has grown a large new attack surface
that the May 5 audit never covered:

- **Org-chart access tiers** (#223) and new roles: Field / Internal Operations Manager (#221, #222, #223).
- **Per-tier permission system**: `tier_permissions`, `tier_permissions_rpcs`, `tech_tier_overrides`,
  `scorecard_tier_thresholds`, `get_my_tier` RPC.
- **Dispatch system** (#224, #225, #227, #228): scoring, health, assignment, GPS, Azuga polling.
- **Email recipient matrix** (#216–#220): admin-configurable alert recipients.
- **Edge function sprawl**: 65 functions today, **32 with `verify_jwt = false`**, but only **11**
  reference the shared auth/signature helpers. ~20 publicly-reachable, DB-touching functions are unverified.
- A cluster of **one-off debug/investigation functions still deployed and public**:
  `debug-invoices`, `full-2025-analysis`, `investigate-hcp`, `investigate-month`, `accuracy-check`,
  `deep-dive-month`, `quick-month-check`, `deep-invoice-analysis`, `complete-revenue-analysis`,
  `final-revenue-calc`, `find-missing-invoices`.

This spec defines a **fresh re-audit + safe remediation**, run from a clean session, twins-dash only.

## 2. Goal

Find and close security gaps introduced since 2026-05-05, behind the Phase 1 safety pattern.
After this ships:

- No `verify_jwt = false` edge function is reachable without either a verified signature or an
  internal auth check.
- One-off debug/investigation functions are **deleted**, not secured.
- Every table/RPC added since 2026-05-05 has correct RLS (no `FOR ALL USING (true)`, no client-readable
  commission/permission rules).
- The org-chart tier + role system cannot be self-escalated; route gating is enforced server-side.
- `ghl-webhook-1/2` verify signatures; cron functions are not abusable.
- Supabase advisors are clean; SECURITY DEFINER RPCs do not leak across roles.
- The whole change is reversible via a single `revert-security-reaudit.sh` script.

Zero feature changes. No KPI math touched (KPIs immutable). No human loses access they have today.

## 3. Constraints (from project memory — LOAD-BEARING)

- **All changes reversible.** Branch + tag + forward/revert migration pairs + revert script.
- **KPIs immutable.** No KPI math edits, even if tempting while in the code.
- **Access stays as it is today.** Grant-inventory snapshot pre/post; diff must be empty for human users.
- **Live site keeps working.** Branch + Vercel preview + Supabase branch before any main merge.
- **Never disable HCP webhooks.** They are the live ingest pipeline. Signature work must not break them.
- **No automated webhook-health alerts.** Any observability is silent (table/pill), never email/SMS/push.
- **Don't fabricate data.** No invented values, names, or rules.

## 4. Audit surfaces (8)

The handed-off session produces a severity-ranked findings doc covering all 8, then remediates
Critical/High behind the safety pattern. Low findings are parked in a backlog, not fixed blind.

### 4.1 Edge function auth classification
Classify all 65 functions into **public / authed / admin-only**. For each `verify_jwt = false` function,
confirm it either verifies a signature (webhooks) or checks a service-role secret / internal auth before
any DB write. Flag every unverified one. Output: a function-by-function table in the findings doc.

### 4.2 Dead / debug function removal
Delete the one-off investigation functions listed in §1. Confirm zero callers in `src/`, cron config,
or other functions before deleting. These are unauthenticated DB-touching endpoints with no production use.

### 4.3 RLS on post-2026-05-05 tables
Re-verify RLS for tables/policies added after Phase 1: `tier_permissions`, `tech_tier_overrides`,
`scorecard_tier_thresholds`, `app_config`, `tech_dashboard_rls`, dispatch tables, role-title tables,
email-recipient tables. Checks: no `FOR ALL USING (true)`; commission/permission/threshold rows are not
client-readable by non-admins; service_role unaffected.

### 4.4 RBAC / privilege escalation
Prove a non-admin authed session cannot: self-promote in any role/tier table; grant itself a higher tier
via `tier_permissions_rpcs` or `tech_tier_overrides`; or reach admin-only routes whose gating exists only
in the React UI rather than in RLS/RPC. Re-run the Phase 1 self-promotion attack plus new tier-escalation
variants.

### 4.5 Webhook & cron hardening
`ghl-webhook-1`, `ghl-webhook-2`: confirm signature/secret verification (they are `verify_jwt = false`).
Cron functions (`cron-friday-paystub-send`, `cron-weekly-lowstock`, `auto-sync-jobs`, etc.): confirm they
require the service-role key / cron secret and cannot be triggered by an anonymous caller.

### 4.6 SECURITY DEFINER RPCs + Supabase advisors
Run `get_advisors` (security + performance lints) on `jwrpj`. Review every SECURITY DEFINER function for
search_path pinning and cross-role leakage. Reconcile with `20260427150000_fix_security_lints.sql`.

### 4.7 Secrets hygiene
Confirm no secrets logged in function bodies. Confirm frontend has no service-role key (verified clean on
2026-05-28). Flag the known LSA OAuth credential rotation (`project_credential_rotation_backlog.md`) and the
`EMAIL_CRON_SECRET` / Postgres GUC mismatch noted there — surface, do not silently rotate.

### 4.8 Dependencies + headers (lower priority)
`npm audit` for known CVEs (confirm `xlsx` stayed removed). CSP / HSTS headers were explicitly deferred in
Phase 1 — include here as a low-priority recommendation with a proposed Vercel `headers` config, not a
forced change.

## 5. Safety pattern (reuse Phase 1)

1. **Branch** off main: `security-reaudit-2026-05`.
2. **Tag** main before any change: `pre-security-reaudit-2026-05-28`.
3. **Supabase branch** for all RLS / function changes; test there first.
4. **Grant inventory** snapshot pre-change → `.planning/security-reaudit/grants-inventory-pre.json`;
   post-change → `grants-inventory-post.json`. Diff must be empty for human users.
5. **Forward + revert migration pairs** for every DB change.
6. **`scripts/revert-security-reaudit.sh`** committed, dry-run tested on the Supabase branch.
7. **Vercel preview** smoke test per role (admin, tech, manager) before main merge.
8. **Review gate:** findings doc + proposed fixes presented to Daniel before anything touches `jwrpj` prod.
9. **24-hour post-deploy** grant-inventory re-diff + webhook-log check; revert if anything drifted.

## 6. Deliverables

1. `twins-dash/SECURITY_REAUDIT_2026-05-28.md` — severity-ranked findings (Critical/High/Medium/Low),
   each with file/table/function, evidence, and proposed fix.
2. Remediation commits behind `security-reaudit-2026-05` (Critical/High only).
3. `scripts/revert-security-reaudit.sh` + the two grant-inventory JSON files + the pre-change git tag.
4. A backlog list of deferred Low findings.
5. A single PR with a description mapping to the §7 acceptance checklist.

## 7. Acceptance criteria

- [ ] All 65 edge functions classified; every `verify_jwt = false` function verified or deleted.
- [ ] Debug/investigation functions (§4.2) deleted with confirmed-zero callers.
- [ ] Self-promotion AND tier-escalation attacks both fail from a non-admin browser session.
- [ ] `get_advisors` security lints clean (or each remaining one documented as accepted).
- [ ] Grant-inventory diff empty for every human user.
- [ ] Each role sees/does exactly what it could the day before (admin, tech, manager smoke test).
- [ ] HCP + GHL webhooks still ingest (sync chip green); no real-traffic 401s in 24h.
- [ ] Vercel build green on branch and main. No KPI math changed. No feature shipped.
- [ ] `revert-security-reaudit.sh` committed and dry-run tested.

## 8. Out of scope

- Other apps (3b-holdings, landlordlens, etc.) — twins-dash only.
- The `wxip…` stale Supabase project decommission (separate operational task).
- KPI consolidation / perf / types (prior Spec 4).
- Actual rotation of the LSA OAuth credentials — surfaced here, executed from the rotation backlog.
- The in-session `security-guidance` plugin — that reviews NEW code as Claude writes it; it does not
  audit existing code. Complementary, not a substitute for this audit.
