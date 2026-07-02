# twinsdash.com Hardening — Findings Report

**Audit date:** 2026-07-01 | **Status:** audit complete, fixes in progress
**Project:** twins-dash (Vite app) + Supabase `jwrpjuqaynownxaoeayi` (prod)

## Severity legend
- **Critical:** unauthenticated, exploitable now, leads to full compromise (admin takeover)
- **High:** exploitable now; exposes customer PII or corrupts business/payroll/KPI data
- **Medium:** exploitable with effort (needs public anon key), or a real reliability/integrity risk
- **Low:** hygiene, defense-in-depth, unreachable-in-practice

## Ranked findings

Status key: ✅ fixed+verified+merged · ⏳ in progress · ⬜ pending

| # | Sev | Area | Finding | Fix PR | Status |
|---|-----|------|---------|--------|--------|
| C1 | Critical | DB RPC | `invite_user()` anon-executable, no caller check → mint an admin invite + self-signup = account takeover | PR-1 | ✅ #295 |
| C2 | Critical | DB RPC | `update_invite()` anon-executable → rewrite a live user's role to admin, cascades to `user_roles` | PR-1 | ✅ #295 |
| C3 | Critical | DB RPC | `set_role_permissions()` anon-executable → grant admin/payroll caps to the `technician` role wholesale | PR-1 | ✅ #295 |
| H1 | High | DB RPC | `revoke_invite()` / `claim_invite()` anon-executable → invite deletion (onboarding DoS) + bind invite role to attacker-controlled user id | PR-1 | ✅ #295 |
| H2 | High | Edge fn | `list-users` + `manage-user` have NO in-code role check; only the public anon key gates them → enumerate all users / create-elevate accounts | PR-2 | ✅ #296 |
| H3 | High | DB views | 13 `SECURITY DEFINER` views granted to `anon` bypass RLS; `v_jobs_with_parts` etc. leak customer PII (names/phones/addresses) + per-tech revenue to the anonymous internet | PR-3 | ✅ #297 (anon path; definer-for-authed tracked) |
| H4 | High | Edge fn | `hcp-webhook` unauthenticated; inserts/updates/**deletes** jobs/invoices/estimates/memberships with service role. Signature helper `_shared/verify-hcp-signature.ts` exists but is imported nowhere | PR-5 (Task 8/9) | ⏳ #303 log-only live; enforce blocked on `HCP_WEBHOOK_SECRET` + soak |
| H5 | High | Edge fn | `ghl-webhook-1` / `ghl-webhook-2` unauthenticated → inject fake jobs/leads with arbitrary revenue into Marketing ROI | PR-5 (Task 8/9) | ⏳ #303 log-only live; enforce blocked on `GHL_WEBHOOK_SECRET` + soak |
| H6 | High | Edge fn | `cron-friday-paystub-send` anon-triggerable; `?force=1&force_send=1` bypasses time + idempotency gates → re-fire real paystub emails to techs on demand | PR-4 | ✅ #298 |
| H7 | High | Edge fn | `reconcile-invoices-nightly` + `cron-weekly-lowstock` config/comments claim a secret gate that does NOT exist in code → anon can rewrite recognized revenue / spam emails | PR-4 | ✅ #298 |
| M1 | Medium | DB RPC | `recompute_commission_for_job()` anon-executable → overwrite admin-adjusted payroll commissions | PR-6 | ✅ #307 |
| M2 | Medium | DB RPC | `set_job_type_callback()` / `set_job_type_resolution()` anon-executable → pollute job-type/KPI pipeline | PR-6 | ✅ #307 |
| M3 | Medium | DB RPC | `check_invite()` / `get_invite_role()` anon-executable → enumerate pending invites + roles | PR-6 | ✅ `get_invite_role` revoked #295; `check_invite` kept anon (signup gate), accepted |
| M4 | Medium | DB RPC | Root cause: blanket `GRANT EXECUTE … TO anon` on SECURITY DEFINER functions | PR-6 | ◑ dangerous ones revoked (C1-C3/H1/M1-M2); guarded-admin ones accepted; blanket sweep deferred (needs full anon-flow verification) |
| M5 | Medium | Edge fn | ~23 sync/geocode/email/report functions rely solely on default `verify_jwt=true` (public anon key) with no role check | PR-7 | ◑ email-senders gated #309, orphan HCP dumps removed #308/#310; sync/geocode/dispatch/payroll-UI/dual-gate deferred (low-harm, see below) |
| M6 | Medium | XSS | `StreakCard` `whatHtml` interpolates `scorecard_tier_thresholds.display_name` (payroll_access-writable) with zero escaping → stored XSS in every tech + admin-impersonation session | PR-8 (Task 10) | ✅ #300 |
| M7 | Medium | Deps | `react-router-dom` 6.30.1 open-redirect/XSS advisory; `Auth.tsx:25` uses the exact `location.state.from` redirect pattern targeted. Fix = patch 6.30.4 | PR-9 | ✅ #299 |
| M8 | Medium | Edge fn | `review-redirect` click stats trivially forgeable (no dedup/rate-limit) → fabricate per-tech review-card click counts | PR-10 | ⬜ deferred (low-harm: corrupts a tracking metric only) |
| L1 | Low | Edge fn | 12 ad-hoc `investigate-*`/`debug-*`/revenue-analysis functions → leak revenue/invoice detail | PR-10 | ✅ #308 (were dead code, none deployed) |
| L2 | Low | XSS | `SettingsPanel.tsx:190` `document.write(previewHtml)` in app origin; escaped server-side today but fragile | PR-10 | ⬜ deferred (low-harm, likely already inert via noopener) |
| L3 | Low | Bundle | `.env` is git-tracked (anon-tier values only today) → invites a future secret commit | PR-10 | ⬜ deferred — verify Vercel has the VITE_ vars before `git rm --cached .env` (else the build loses them) |
| L4 | Low | DB | 19 functions with mutable `search_path`; `pg_net` in `public`; leaked-password protection off | PR-10 | ◑ search_path pinned #311; `pg_net`-in-public + leaked-password toggle (Auth dashboard) deferred |
| L5 | Low | DB perf | 149 `auth_rls_initplan` + 62 `multiple_permissive_policies` + 56 unindexed FKs + 26 unused indexes (reliability/cost, no exposure) | PR-11 (opt) | ⬜ deferred (perf/cost, no exposure) |

**Verified fine (no action):** All base-table RLS on payroll/user_roles/accountability/streaks — a technician cannot read another tech's pay. The 9 `rls_enabled_no_policy` tables are fail-closed. ~13 edge functions correctly protected by `requireAdminAuth`/tech gate/token. The shipped client bundle contains only the anon key (no service_role), no stray live keys. Admin-named RPCs (`set_payroll_access`, `admin_*`, `list_users_for_impersonation`, `my_paystub`) enforce internal guards despite the anon grant.

---

## Stream detail

Full per-stream evidence (SQL quotes, file:line references, exploit steps) preserved in the scratchpad stream files:
- Stream 1 database: `scratchpad/stream1-database.md` (+ `advisors-security.json`, `advisors-performance.json`)
- Stream 2 edge functions: `scratchpad/stream2-edge-functions.md`
- Stream 3 XSS: `scratchpad/stream3-xss.md`
- Stream 4 bundle: `scratchpad/stream4-bundle.md`
- Stream 5 deps: `scratchpad/stream5-deps.md` (+ `npm-audit.json`)

### Stream 1 — Database (13 findings)
179 security advisor items. Core issue: 68 `SECURITY DEFINER` functions + 13 `SECURITY DEFINER` views are all EXECUTE/SELECT-granted to `anon`. Most admin-named functions self-guard, but C1–C3, H1, H3, M1–M4 do not. Base-table RLS verified correct throughout — the exposure is entirely the anon-executable definer surface, not the table policies.

### Stream 2 — Edge functions (8 findings across 76 functions)
~13 correctly protected, 3 correctly open by design (`review-redirect`, `google-review-stats`, `dashboard-suggest-actions`), the rest either effectively open (`verify_jwt=false`, no gate) or protected only by the public anon key. Webhooks (H4/H5), user-admin functions (H2), and cron endpoints with phantom secret gates (H6/H7) are the priorities.

### Stream 3 — XSS (1 Medium, 1 Low, 2 clean)
`StreakCard` stored-XSS (M6) is the real one. `SuggestionsDrawer` is safe (complete escaping). `chart.tsx` constant-only. `SettingsPanel` `document.write` low-risk (L2).

### Stream 4 — Client bundle (1 Low)
Clean: only the anon-role JWT ships, no service_role, no stray live keys, nothing injected at build time. Only `.env` git-tracking (L3).

### Stream 5 — Dependencies (1 Medium, rest Low)
23 advisories, 0 critical. Only `react-router-dom` (M7) is prod-reachable. Remainder are dev-toolchain or unreachable code paths; `npm audit fix` (no --force) resolves 22/23 non-breakingly.

---

## Fix sequencing

Worst-first, one PR per group, each reversible:
1. **PR-1** (Critical): guard C1–C3 + H1 RPCs — migration. **Executes before all else.**
2. **PR-2** (High): `requireAdminAuth` on `list-users` + `manage-user`.
3. **PR-3** (High): SECURITY DEFINER views → `security_invoker` + revoke anon.
4. **PR-4** (High): real secret gates on `cron-friday-paystub-send`, `reconcile-invoices-nightly`, `cron-weekly-lowstock`.
5. **Task 8 → PR-5 start** (High): webhook observation (log-only) deployed early so the multi-day soak clock starts.
6. **PR-6** (Medium): remaining anon-RPC guards + anon EXECUTE sweep (careful: preserve signup flow).
7. **PR-7** (Medium): role checks on the ~23 verify_jwt-only functions.
8. **Task 10 → PR-8** (Medium): StreakCard XSS.
9. **PR-9** (Medium): react-router-dom 6.30.4.
10. **PR-10** (Low): review-redirect rate-limit, delete debug fns, SettingsPanel, .env gitignore, DB hygiene.
11. **Task 9** (High): webhook enforce, after 3-day soak.
12. **Phase 3:** ESLint autofix + strict-TS ratchet CI.
13. **Task 13:** after-scorecard (re-run advisors).

## Progress scorecard (interim — 2026-07-02)

**Merged & verified live (7 PRs):** C1–C3, H1 (#295) · H2 (#296) · H3 anon-path (#297) · H6, H7 (#298) · M7 (#299) · M6 (#300) · code-health ratchet CI (#301). Every fix was proven against prod with a live exploit test (401/403) before merge; no KPI/payroll math touched.

**14 PRs merged, all verified live pre-merge; no KPI/payroll math touched.**
C1-C3, H1 (#295) · H2 (#296) · H3 (#297) · H6/H7 (#298) · M7 (#299) · M6 (#300) ·
CI ratchet (#301) · stale tests (#302) · webhook log-only + token (#303/#306) ·
M1/M2 (#307) · L1 dead-fns (#308) · M5 email-senders (#309) · orphan HCP dumps (#310) ·
L4 search_path (#311).

**Supabase security advisor delta (baseline → after):**
- Total security items: **179 → 153**.
- `function_search_path_mutable`: **19 → 0** (L4).
- `anon_security_definer_function_executable`: **68 → 59** (invite/commission/job-type RPCs revoked).
- `security_definer_view` (14) + `authenticated_security_definer_function_executable` (68): unchanged — these flag the *structural* definer pattern regardless of grants. The anon-PII exposure is closed (verified anon GET → 401); the lint stays lit until the views are converted to `security_invoker` per-view (tracked). Most anon-executable definer *functions* self-guard (`admin only`) — safe despite the grant.
- Advisor counts understate the work: account-takeover, user-admin, PII-leak, cron, XSS, and email-blast exploits are all closed but only partly reflected in these structural lints. **Live per-fix 401/403 verification is the real evidence.**

**Deferred remainder (all lower-harm; documented, not blocking):**
- **H4/H5 webhooks** — enforcement blocked on Daniel: set `HCP_WEBHOOK_TOKEN` + append `?t=` to the HCP webhook URL (GHL likewise). Validation is LIVE in log-only mode; flip `WEBHOOK_VALIDATION_MODE=enforce` after a clean soak.
- **M5 remainder** (cron sync fns: poll-azuga, meta/google/ghl/lsa syncs, forecast-refresh, backfill-dispatch-ingest → cron-secret + cron-job update; frontend-admin payroll reads sync-hcp-week/list-hcp-week-jobs → requireAdminAuth w/ role caveat; get-drive-times → tech gate; generate-tech-nudge + daily-supervisor-digest → dual cron-secret-OR-admin gate; geocode-tech-home → DB-trigger header edit; geocode-job-address/classify-csr-notes → fn-to-fn header propagation). Full per-function table: `scratchpad/f6-gating-table.md`. Low-harm (idempotent syncs / anon-key-gated reads); deferred to avoid rushing dispatch/payroll-UI/cron changes on payroll day.
- **M4** blanket anon-EXECUTE sweep — the dangerous functions are individually revoked; the remaining anon-executable definer functions self-guard. Full sweep needs comprehensive pre-auth-flow verification.
- **M8** review-redirect rate-limit; **L2** SettingsPanel document.write; **L3** .env gitignore (verify Vercel env first); **L5** RLS-perf. All low-harm.
- **Deno edge tests** — 12 edge-fn tests need a `deno test` job (they can't run under vitest); then wire vitest into CI.
