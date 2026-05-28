# HANDOFF PROMPT — twins-dash Security Re-Audit

Open a **fresh Claude Code session in `/Users/daniel/twins-dashboard/twins-dash`** and paste
everything inside the fenced block below. (No `/reload-plugins` needed — skills load at session start.)

---

```
You are doing a security re-audit and safe remediation of the twins-dash dashboard (live at
twinsdash.com, Vercel-deployed, Supabase project jwrpj). Work in /Users/daniel/twins-dashboard/twins-dash.

READ FIRST, in order:
1. docs/superpowers/specs/2026-05-28-twins-dash-security-reaudit-design.md  (the spec — your scope)
2. docs/superpowers/specs/2026-05-05-phase1-security-cleanup-design.md      (what already shipped)
3. twins-dash/DASHBOARD_FULL_AUDIT.md                                        (the stale 2026-05-05 audit)

CONTEXT (verified 2026-05-28):
- 65 edge functions; 32 have verify_jwt = false; only 11 reference supabase/functions/_shared/auth.ts
  or verify-hcp-signature.ts. The other ~20 verify_jwt=false functions are the priority.
- One-off debug functions still deployed and public, to DELETE after confirming zero callers:
  debug-invoices, full-2025-analysis, investigate-hcp, investigate-month, accuracy-check,
  deep-dive-month, quick-month-check, deep-invoice-analysis, complete-revenue-analysis,
  final-revenue-calc, find-missing-invoices.
- New surface since Phase 1: org-chart access tiers (#223), Field/Internal Operations Manager roles,
  tier_permissions + tier_permissions_rpcs + tech_tier_overrides + scorecard_tier_thresholds +
  get_my_tier RPC, the dispatch system, the email recipient matrix, app_config.
- Frontend has NO service-role key and no hardcoded secrets (already verified clean).

HARD CONSTRAINTS (do not violate):
- Every change reversible: branch security-reaudit-2026-05, tag main pre-security-reaudit-2026-05-28,
  forward+revert migration pairs, scripts/revert-security-reaudit.sh.
- KPIs immutable — change no KPI math.
- No human loses access they have today — snapshot grants pre/post, diff must be empty for human users.
- Never disable the HCP webhooks. Test all Supabase changes on a Supabase branch first.
- Any webhook/integration health you add must be SILENT (table/pill), never email/SMS/push.
- Charles co-tech attribution and Fri–Thu payroll weeks are load-bearing — don't touch that logic.
- Do not fabricate any values, names, or rules. If source data lacks a structured tag, exclude or ask.

WORKFLOW (superpowers):
1. Invoke the brainstorming skill ONLY if you find the spec's scope is wrong; otherwise skip to step 2.
2. Run the full audit across the 8 surfaces in the spec. Produce a severity-ranked findings doc at
   twins-dash/SECURITY_REAUDIT_2026-05-28.md (Critical/High/Medium/Low; each with file/table/function,
   evidence, and proposed fix). Use the Supabase MCP get_advisors tool for security lints.
3. STOP and show Daniel the findings doc. Wait for his go-ahead before any prod change.
4. After approval, invoke the writing-plans skill to turn the Critical/High fixes into an implementation
   plan, then execute it with the safety pattern above. Park Low findings in a backlog; do not fix blind.
5. Verify against the spec's §7 acceptance checklist. Open one PR.

DELIVERABLES: the findings doc, remediation commits on security-reaudit-2026-05, the revert script, the
two grant-inventory JSON files, the pre-change git tag, and a backlog of deferred Low findings.

Start by reading the three files above, then give me the audit findings doc. Do not change prod until I
approve the findings.
```

---

**After you paste:** the session will read the spec, run the read-only audit, and hand you a
severity-ranked findings doc. Nothing touches `jwrpj` prod until you approve. Remediation runs on a
branch with a one-command revert.
