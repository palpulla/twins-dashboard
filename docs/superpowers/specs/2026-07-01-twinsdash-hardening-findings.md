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
| H4 | High | Edge fn | `hcp-webhook` unauthenticated; inserts/updates/**deletes** jobs/invoices/estimates/memberships with service role. Signature helper `_shared/verify-hcp-signature.ts` exists but is imported nowhere | PR-5 (Task 8/9) | ⬜ |
| H5 | High | Edge fn | `ghl-webhook-1` / `ghl-webhook-2` unauthenticated → inject fake jobs/leads with arbitrary revenue into Marketing ROI | PR-5 (Task 8/9) | ⬜ |
| H6 | High | Edge fn | `cron-friday-paystub-send` anon-triggerable; `?force=1&force_send=1` bypasses time + idempotency gates → re-fire real paystub emails to techs on demand | PR-4 | ✅ #298 |
| H7 | High | Edge fn | `reconcile-invoices-nightly` + `cron-weekly-lowstock` config/comments claim a secret gate that does NOT exist in code → anon can rewrite recognized revenue / spam emails | PR-4 | ✅ #298 |
| M1 | Medium | DB RPC | `recompute_commission_for_job()` anon-executable → overwrite admin-adjusted payroll commissions | PR-6 |
| M2 | Medium | DB RPC | `set_job_type_callback()` / `set_job_type_resolution()` anon-executable → pollute job-type/KPI pipeline | PR-6 |
| M3 | Medium | DB RPC | `check_invite()` / `get_invite_role()` anon-executable → enumerate pending invites + roles (recon for C1–H1) | PR-6 |
| M4 | Medium | DB RPC | Root cause: blanket `GRANT EXECUTE … TO anon` on all SECURITY DEFINER functions; any future unguarded one is exploitable by default | PR-6 |
| M5 | Medium | Edge fn | ~23 sync/geocode/email/report functions rely solely on default `verify_jwt=true` (public anon key) with no role check | PR-7 |
| M6 | Medium | XSS | `StreakCard` `whatHtml` interpolates `scorecard_tier_thresholds.display_name` (payroll_access-writable) with zero escaping → stored XSS in every tech + admin-impersonation session | PR-8 (Task 10) | ✅ #300 |
| M7 | Medium | Deps | `react-router-dom` 6.30.1 open-redirect/XSS advisory; `Auth.tsx:25` uses the exact `location.state.from` redirect pattern targeted. Fix = patch 6.30.4 | PR-9 | ✅ #299 |
| M8 | Medium | Edge fn | `review-redirect` click stats trivially forgeable (no dedup/rate-limit) → fabricate per-tech review-card click counts | PR-10 |
| L1 | Low | Edge fn | 12 ad-hoc `investigate-*`/`debug-*`/revenue-analysis functions unauthenticated → leak revenue/invoice detail. Likely one-off leftovers → delete or guard | PR-10 |
| L2 | Low | XSS | `SettingsPanel.tsx:190` `document.write(previewHtml)` in app origin; escaped server-side today but fragile (and likely broken by `noopener`) | PR-10 |
| L3 | Low | Bundle | `.env` is git-tracked (anon-tier values only today, history verified clean) → invites a future secret commit | PR-10 |
| L4 | Low | DB | `auth_leaked_password_protection` disabled; `pg_net` in `public`; 19 functions with mutable `search_path` | PR-10 |
| L5 | Low | DB perf | 149 `auth_rls_initplan` + 62 `multiple_permissive_policies` + 56 unindexed FKs + 26 unused indexes (reliability/cost, no exposure) | PR-11 (opt) |

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

**Supabase security advisor delta (coarse structural metric):**
- `anon_security_definer_function_executable`: 68 → 62 (the invite/role RPCs locked in F1)
- `security_definer_view` (14) and `authenticated_security_definer_function_executable` (68) largely unchanged — these flag the *structural* definer pattern regardless of grants, so the H3 anon-revoke and the remaining definer-for-authenticated work (tracked follow-up) don't move them. The real anon-PII exposure is closed (verified: anon GET on the views → 401); the advisor stays lit until the views are converted to `security_invoker` per-view.
- `function_search_path_mutable` (19), `extension_in_public` (1), `auth_leaked_password_protection` (1): unchanged — queued in F8 (L4).
- Advisor counts understate the improvement: account-takeover, user-admin, PII-leak, and cron exploits are all closed but only partially reflected in these structural lints. Live per-fix verification is the real evidence.

**Remaining (see plan Tasks F5, F6, F8, 8/9, 13):**
- H4/H5 webhooks — blocked on the HCP + GHL signing secrets (Daniel).
- M1–M4 anon-RPC sweep, M5 edge-fn role checks — payroll-adjacent; deferred past the active payroll day, then log-only/verify.
- M8 + L1–L5 lows — cleanup batch.
- Final after-scorecard: re-run advisors once the above land.
