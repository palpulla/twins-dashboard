# twinsdash.com Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Audit twinsdash.com (twins-dash repo + jwrpj Supabase project) for security and breakage risks, fix findings worst-first in small reversible PRs, and install a code-health ratchet, per the approved spec `docs/superpowers/specs/2026-07-01-twinsdash-hardening-design.md`.

**Architecture:** Three phases. Phase 1 is a read-only five-stream audit that produces a ranked findings report. Phase 2 fixes findings in severity order; webhook validation ships log-only first, enforce later. Phase 3 adds an ESLint safe-autofix pass and a strict-TypeScript CI ratchet for changed files.

**Tech Stack:** Vite 5 + React 18 + TypeScript 5.9, Supabase (project ref starts with `jwrpj`), Deno edge functions, Vitest, GitHub Actions.

**Hard invariants (from spec — apply to every task):**
- KPI and payroll math byte-identical. Nothing in this plan edits `src/lib/kpi-calculations.ts` logic or payroll math.
- Webhooks never disabled or interrupted. Validation is log-only until real-traffic logs prove it safe to enforce.
- No new email/SMS/push alerting. Health data goes to tables/dashboard only.
- Every change on a branch, merged by PR, independently revertable.

**Operational facts the executor must know:**
- The app repo is `/Users/daniel/twins-dashboard/twins-dash` (its own git repo, remote `palpulla/twins-dash`, base branch `main`). The plan/spec/findings docs live in the OUTER repo `/Users/daniel/twins-dashboard` (also its own git repo). Never mix commits between them.
- Worktrees must be created INSIDE `twins-dash` (e.g. `twins-dash/.worktrees/<name>`), never in the outer directory.
- `gh` CLI is not available. Create PRs via the GitHub API:
  ```bash
  token=$(printf 'protocol=https\nhost=github.com\n\n' | git credential-osxkeychain get | sed -n 's/^password=//p')
  curl -s -H "Authorization: token $token" -H "Accept: application/vnd.github+json" \
    https://api.github.com/repos/palpulla/twins-dash/pulls \
    -d '{"title":"<title>","head":"<branch>","base":"main","body":"<body>\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"}'
  ```
- Supabase access is via the Supabase MCP tools (`list_projects`, `get_advisors`, `execute_sql`, `apply_migration`, `list_edge_functions`, `deploy_edge_function`, `get_logs`). Resolve the full project ref first: call `list_projects` and pick the ref starting with `jwrpj`. All SQL/advisor calls target that ref. NEVER touch the old `wxip` project.
- After applying any migration to jwrpj, the repo's migration history is desynced; manually insert the version row: `insert into supabase_migrations.schema_migrations (version) values ('<timestamp>');` (verify table shape first with `select * from supabase_migrations.schema_migrations limit 3`).
- Scratch space: `/private/tmp/claude-501/-Users-daniel-twins-dashboard/15ab0fcf-ba8a-458d-8290-7481fd2b2313/scratchpad`.

---

## Phase 1 — Audit (read-only; no app code changes)

All audit output accumulates in one working file:
`docs/superpowers/specs/2026-07-01-twinsdash-hardening-findings.md` (outer repo). Each stream appends a section. Severity scale: **High** = exploitable now or can corrupt/expose business data; **Medium** = exploitable with effort, or reliability risk; **Low** = hygiene.

### Task 1: Findings report skeleton

**Files:**
- Create: `/Users/daniel/twins-dashboard/docs/superpowers/specs/2026-07-01-twinsdash-hardening-findings.md`

- [ ] **Step 1: Create the skeleton**

```markdown
# twinsdash.com Hardening — Findings Report

**Audit date:** 2026-07-01 | **Status:** in progress

## Severity legend
- High: exploitable now, or can corrupt/expose business data
- Medium: exploitable with effort, or a real reliability risk
- Low: hygiene

## Ranked findings (filled after all streams complete)
| # | Severity | Area | Finding | Fix task |
|---|----------|------|---------|----------|

## Stream 1: Database wall (advisors + RLS)
## Stream 2: Edge function auth matrix
## Stream 3: XSS sinks
## Stream 4: Client bundle
## Stream 5: Dependencies
## After-scorecard (filled at project end)
```

- [ ] **Step 2: Commit (outer repo)**

```bash
cd /Users/daniel/twins-dashboard
git add docs/superpowers/specs/2026-07-01-twinsdash-hardening-findings.md
git commit -m "audit(hardening): findings report skeleton"
```

### Task 2: Stream 1 — Supabase advisors + RLS read

**Files:**
- Modify: findings report, Stream 1 section

- [ ] **Step 1: Resolve project ref.** Call MCP `list_projects`; record the ref starting with `jwrpj`. Use it for every Supabase call below.
- [ ] **Step 2: Run advisors.** Call `get_advisors` twice (`type: "security"`, `type: "performance"`). Save raw JSON to scratchpad (`advisors-security.json`, `advisors-performance.json`).
- [ ] **Step 3: Enumerate RLS state.** Via `execute_sql`:

```sql
select schemaname, tablename, rowsecurity
from pg_tables where schemaname = 'public' order by tablename;
```

```sql
select tablename, policyname, cmd, roles, qual, with_check
from pg_policies where schemaname = 'public' order by tablename, policyname;
```

```sql
select table_name, grantee, privilege_type
from information_schema.role_table_grants
where table_schema = 'public' and grantee in ('anon','authenticated')
order by table_name, grantee;
```

- [ ] **Step 4: Deep-read sensitive tables.** For each of: all `payroll_*` tables, `user_roles`, `invited_emails`, `hcp_data`, accountability/point-system tables (find by `tablename ilike '%point%' or tablename ilike '%accountab%' or tablename ilike '%streak%'`), answer in the report: RLS enabled? Can `anon` read or write? Can a `technician`-role user read other techs' pay data? Quote the policy `qual` as evidence.
- [ ] **Step 5: Write Stream 1 findings.** Every advisor item and every RLS gap becomes a row: severity, table/policy, evidence, proposed fix. Explicitly note items that are fine ("RLS on payroll_jobs verified: policy X restricts to role Y").
- [ ] **Step 6: Commit (outer repo)** — `git commit -am "audit(hardening): stream 1 — advisors + RLS"`

### Task 3: Stream 2 — Edge function auth matrix

**Files:**
- Modify: findings report, Stream 2 section

- [ ] **Step 1: Generate the raw matrix.** In `twins-dash`:

```bash
cd /Users/daniel/twins-dashboard/twins-dash
for d in supabase/functions/*/; do
  fn=$(basename "$d")
  [ -f "$d/index.ts" ] || continue
  auth="none"
  grep -q "requireAdminAuth" "$d/index.ts" && auth="admin"
  grep -q "authenticateRequest" "$d/index.ts" && auth="tech"
  grep -qiE "service_role|isInternalServiceCall|SERVICE_ROLE" "$d/index.ts" && auth="$auth+service-check"
  echo "$fn | $auth"
done
```

Also check `supabase/config.toml` for per-function `verify_jwt` settings and note them.

- [ ] **Step 2: Read every function marked `none`.** For each (expect ~60), record in the matrix: what it does (1 line), what it can write/read, whether it's called by external webhook / cron / browser, and a verdict: **correctly open** / **needs auth** / **needs signature validation** / **needs secret header**. Dispatch parallel read-only subagents in batches (~10 functions each) to keep this fast; each returns matrix rows only.
- [ ] **Step 3: Special cases get explicit verdicts:** `hcp-webhook`, `ghl-webhook-1`, `ghl-webhook-2`, `review-redirect` (public by design — verdict on abuse potential: can it be spammed to fabricate click stats?), every `sync-*`, `email-*`, `csr-*` function. Flag any function invokable by anyone that writes business tables with the service role — those are High.
- [ ] **Step 4: Write Stream 2 findings + full matrix table into the report. Commit (outer repo).**

### Task 4: Stream 3 — XSS sink trace

**Files:**
- Read: `twins-dash/src/components/dashboard/SuggestionsDrawer.tsx`, `twins-dash/src/components/tech/StreakCard.tsx`, `twins-dash/src/components/ui/chart.tsx`
- Modify: findings report, Stream 3 section

- [ ] **Step 1:** For SuggestionsDrawer and StreakCard: read the file, find the `dangerouslySetInnerHTML` expression, then trace its input backward (hook → query → table → what writes that table). Answer: can text originating from HCP/GHL (customer names, job descriptions, notes) or from any non-admin user reach the sink? Is there escaping before the HTML build, and is it complete (quotes, angle brackets)?
- [ ] **Step 2:** Confirm `chart.tsx` injects only build-time constants (theme CSS). Record the confirmation.
- [ ] **Step 3:** Write Stream 3 findings with severity (attacker-reachable + unescaped = High; escaped-but-fragile = Medium; constant-only = note, no finding). Commit (outer repo).

### Task 5: Stream 4 — Client bundle review

**Files:**
- Modify: findings report, Stream 4 section

- [ ] **Step 1: Build and grep the bundle.**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npm run build 2>&1 | tail -5
grep -rloE "service_role|SERVICE_ROLE" dist/assets/ || echo "CLEAN: no service_role"
grep -rhoE "eyJ[A-Za-z0-9_-]{30,}" dist/assets/ | sort -u > /tmp/jwt-candidates.txt; wc -l /tmp/jwt-candidates.txt
```

For each JWT-looking string found, base64-decode the payload segment and confirm `"role":"anon"` (expected, fine). Anything else is High.

- [ ] **Step 2:** Review `src/integrations/supabase/client.ts` + `.env`: confirm only URL + publishable/anon key ship; note the localStorage session storage (standard, record as accepted). Check `.env` for any non-VITE secret accidentally present.
- [ ] **Step 3:** Grep for other embedded keys: `grep -rn "AIza\|pk_live\|sk_live" src/ dist/assets/ | head`. Google Maps `AIza` key is expected and referer-restricted (verify the restriction note, don't remove).
- [ ] **Step 4:** Write Stream 4 findings. Commit (outer repo).

### Task 6: Stream 5 — Dependency audit

- [ ] **Step 1:** `cd twins-dash && npm audit --json > <scratchpad>/npm-audit.json; npm audit | tail -20`
- [ ] **Step 2:** For each critical/high advisory: is the vulnerable path actually reachable (prod dependency vs dev-only)? Record package, advisory, reachability, and the upgrade path (`npm audit fix` safe vs semver-major).
- [ ] **Step 3:** Write Stream 5 findings. Commit (outer repo).

### Task 7: Rank findings + fix-task generation (PLAN AMENDMENT GATE)

- [ ] **Step 1:** Fill the ranked-findings table at the top of the report: every High and Medium ordered by severity, each with a proposed fix.
- [ ] **Step 2: Amend THIS plan.** For each High/Medium finding not already covered by Tasks 8–10, append a new Phase 2 task to this plan file following the **fix-task template** (below). Each appended task must contain the exact file paths, the exact SQL/code for the fix, and the verification steps. No fix proceeds from the report alone.
- [ ] **Step 3:** Commit report + amended plan (outer repo). Post a summary of the ranked findings to Daniel in the session (informational; work continues unless a finding requires a business decision, e.g. "is this endpoint supposed to be public?" — those get flagged with a recommended default and the default is taken if no answer).

**Fix-task template (for appended tasks):**

````markdown
### Task N: Fix <finding #> — <title>  (severity: <High|Medium>)

**Files:** Create/Modify exact paths | **Branch:** `fix/<slug>` off main in twins-dash

- [ ] Step 1: Write failing test or capture reproducing evidence (exact command/query + expected failure)
- [ ] Step 2: Apply fix (exact code/SQL shown here)
- [ ] Step 3: `npx vitest run` → all green; `npm run build` → succeeds
- [ ] Step 4: Invariant check: if the diff touches anything imported by kpi-calculations, payroll, or webhook handlers, run the relevant test files and state the result explicitly
- [ ] Step 5: Commit, push, open PR via API, merge after checks, verify live site loads + affected feature works
````

---

## Phase 2 — Fixes (worst first)

Tasks 8–10 are the fixes already known from the survey. Findings-driven tasks from Task 7 slot in by severity around them: **any High finding executes before Task 8.**

### Task 8: Webhook observation — log-only capture of real traffic

**Files (twins-dash repo, branch `fix/webhook-observe`):**
- Create: `supabase/functions/_shared/webhook-observe.ts`
- Modify: `supabase/functions/hcp-webhook/index.ts`, `supabase/functions/ghl-webhook-1/index.ts`, `supabase/functions/ghl-webhook-2/index.ts` (top of handler only)
- Create: migration for `webhook_request_log`

- [ ] **Step 1: Verify `user_roles` shape** (needed by the RLS policy): `execute_sql`: `select column_name, data_type from information_schema.columns where table_name = 'user_roles';` Adjust the policy below if columns differ.
- [ ] **Step 2: Apply migration** via MCP `apply_migration` (name `webhook_request_log`):

```sql
create table if not exists public.webhook_request_log (
  id bigint generated always as identity primary key,
  received_at timestamptz not null default now(),
  source text not null,
  method text not null,
  headers jsonb not null default '{}'::jsonb,
  verdict text not null default 'accepted', -- 'accepted' | 'would_reject'
  reason text
);
create index if not exists webhook_request_log_received_idx on public.webhook_request_log (received_at);
alter table public.webhook_request_log enable row level security;
create policy "admins read webhook log" on public.webhook_request_log
  for select to authenticated using (
    exists (select 1 from public.user_roles ur
            where ur.user_id = auth.uid() and ur.role in ('admin','manager'))
  );
```

Then insert the version row into `supabase_migrations.schema_migrations` (see Operational facts).

- [ ] **Step 3: Write the shared observer** — `supabase/functions/_shared/webhook-observe.ts`:

```ts
import type { SupabaseClient } from "https://esm.sh/@supabase/supabase-js@2";

const REDACT = new Set(["authorization", "cookie", "x-api-key", "apikey"]);

export async function logWebhookRequest(
  supabase: SupabaseClient,
  source: string,
  req: Request,
  verdict: "accepted" | "would_reject" = "accepted",
  reason: string | null = null,
): Promise<void> {
  try {
    const headers: Record<string, string> = {};
    req.headers.forEach((v, k) => {
      headers[k] = REDACT.has(k.toLowerCase()) ? "[redacted]" : v;
    });
    await supabase.from("webhook_request_log").insert({
      source, method: req.method, headers, verdict, reason,
    });
    // opportunistic 30-day purge, ~1% of requests
    if (Math.random() < 0.01) {
      await supabase.from("webhook_request_log").delete()
        .lt("received_at", new Date(Date.now() - 30 * 86400_000).toISOString());
    }
  } catch (_e) {
    // observation must NEVER break ingest — swallow all errors
  }
}
```

- [ ] **Step 4: Wire into the three webhook handlers.** At the top of each handler, after the existing service-role client is created, add one fire-and-forget line (do not `await` in the request path if the handler is latency-sensitive; otherwise await is fine):

```ts
import { logWebhookRequest } from "../_shared/webhook-observe.ts";
// inside the handler, before existing processing:
logWebhookRequest(supabase, "hcp-webhook", req); // source matches function name
```

**Change nothing else in these handlers.** The diff per handler must be ≤ 3 lines (import + call).

- [ ] **Step 5: Test locally that the module compiles:** `cd twins-dash && deno check supabase/functions/hcp-webhook/index.ts supabase/functions/ghl-webhook-1/index.ts supabase/functions/ghl-webhook-2/index.ts` → no errors. (If `deno` is unavailable locally, rely on deploy-time check in Step 7.)
- [ ] **Step 6: Commit, push, open PR, merge.**
- [ ] **Step 7: Deploy the three functions** via MCP `deploy_edge_function` from main. Immediately after, `get_logs` for each function and confirm no new errors and normal 200s continuing.
- [ ] **Step 8: Verify capture:** after ~1 hour, `execute_sql`: `select source, count(*), max(received_at) from webhook_request_log group by 1;` — rows for each source with recent timestamps. Then inspect one row per source: `select headers from webhook_request_log where source='hcp-webhook' order by id desc limit 1;` and record which signature headers exist (look for anything like `*signature*`, `*hmac*`, `*hook-secret*`).

### Task 9: Webhook validation — log-only, then enforce

Depends on Task 8's captured headers. **Do not start until at least 24h of capture exists.**

**Files (branch `fix/webhook-validate`):**
- Create: `supabase/functions/_shared/webhook-validate.ts`
- Modify: the three webhook handlers (guard block at top)

- [ ] **Step 1: Pick mechanism per source from captured headers.** (a) If a signature header exists: HMAC validation; obtain the signing secret — check Supabase function secrets and the provider's API first; if it can only come from a provider dashboard, tell Daniel exactly where to click and pause this task only. (b) If no signature header: secret path/query token — generate one (`openssl rand -hex 24`), store as function secret, and update the webhook URL registered at the provider via its API (HCP/GHL). The old URL keeps working until enforce-time.
- [ ] **Step 2: Write the validator** — `supabase/functions/_shared/webhook-validate.ts`:

```ts
export type VerdictMode = "off" | "log" | "enforce";
export const MODE: VerdictMode =
  (Deno.env.get("WEBHOOK_VALIDATION_MODE") as VerdictMode) ?? "log";

export interface CheckResult { ok: boolean; reason?: string }

export async function checkHmac(
  rawBody: string, sigHeader: string | null, secretEnv: string,
): Promise<CheckResult> {
  const secret = Deno.env.get(secretEnv);
  if (!secret) return { ok: false, reason: `missing secret ${secretEnv}` };
  if (!sigHeader) return { ok: false, reason: "missing signature header" };
  const key = await crypto.subtle.importKey(
    "raw", new TextEncoder().encode(secret),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const mac = await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(rawBody));
  const expected = Array.from(new Uint8Array(mac))
    .map((b) => b.toString(16).padStart(2, "0")).join("");
  const given = sigHeader.trim().toLowerCase().replace(/^sha256=/, "");
  return timingSafeEq(expected, given)
    ? { ok: true } : { ok: false, reason: "signature mismatch" };
}

export function checkToken(url: URL, secretEnv: string): CheckResult {
  const secret = Deno.env.get(secretEnv);
  if (!secret) return { ok: false, reason: `missing secret ${secretEnv}` };
  const given = url.searchParams.get("t") ?? "";
  return timingSafeEq(secret, given) ? { ok: true } : { ok: false, reason: "bad token" };
}

function timingSafeEq(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}
```

- [ ] **Step 3: Wire the guard into each handler,** replacing the Task 8 log line:

```ts
const raw = await req.text(); // handlers already read body; reuse the raw string for both validate + JSON.parse
const check = await checkHmac(raw, req.headers.get("<header-from-task8>"), "<SECRET_ENV_NAME>");
if (!check.ok && MODE === "enforce") {
  logWebhookRequest(supabase, "hcp-webhook", req, "would_reject", check.reason);
  return new Response("unauthorized", { status: 401 });
}
logWebhookRequest(supabase, "hcp-webhook", req, check.ok ? "accepted" : "would_reject", check.reason ?? null);
```

(Adapt per source: `checkToken(new URL(req.url), ...)` for token sources. Ensure the handler's existing `JSON.parse(await req.text())` is refactored to reuse `raw` — the body can only be read once.)

- [ ] **Step 4: Set `WEBHOOK_VALIDATION_MODE=log`** as a function secret. Deploy the three functions. Confirm via `get_logs` + a live row check that traffic still flows and verdicts are being recorded.
- [ ] **Step 5: PR + merge** (same flow as Task 8).
- [ ] **Step 6: Soak 3+ days, then evaluate:** `select source, verdict, count(*) from webhook_request_log where received_at > now() - interval '3 days' group by 1,2;` — genuine provider traffic must show `accepted`. If ALL rows for a source are `would_reject`, the mechanism is wrong (bad secret/header) — fix before ever enforcing.
- [ ] **Step 7: Enforce:** set `WEBHOOK_VALIDATION_MODE=enforce`, redeploy, watch `get_logs` + the log table for 1 hour: legitimate traffic still `accepted`, dashboard data still updating (spot-check a recent HCP job appears). **Rollback = set mode back to `log` and redeploy — one step.**

### Task 10: XSS sink hardening

**Files (branch `fix/xss-sinks`):**
- Create: `src/lib/render-bold.tsx`
- Create: `src/lib/__tests__/render-bold.test.tsx`
- Modify: `src/components/dashboard/SuggestionsDrawer.tsx`, `src/components/tech/StreakCard.tsx`

- [ ] **Step 1: Write the failing test** — `src/lib/__tests__/render-bold.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { render } from "@testing-library/react";
import { renderBold } from "../render-bold";

describe("renderBold", () => {
  it("renders **text** as <strong>", () => {
    const { container } = render(<span>{renderBold("save **$500** now")}</span>);
    expect(container.querySelector("strong")?.textContent).toBe("$500");
    expect(container.textContent).toBe("save $500 now");
  });
  it("never interprets HTML in the input", () => {
    const { container } = render(
      <span>{renderBold('<img src=x onerror=alert(1)> **<b>hi</b>**')}</span>);
    expect(container.querySelector("img")).toBeNull();
    expect(container.querySelector("b")).toBeNull();
    expect(container.textContent).toContain("<img src=x onerror=alert(1)>");
  });
});
```

- [ ] **Step 2: Run it, expect failure** — `npx vitest run src/lib/__tests__/render-bold.test.tsx` → fails (module not found).
- [ ] **Step 3: Implement** — `src/lib/render-bold.tsx`:

```tsx
import type { ReactNode } from "react";

/** Renders `**bold**` markdown as React nodes. Everything else is plain text —
 *  React escapes it, so no HTML in the input can ever execute. */
export function renderBold(text: string): ReactNode[] {
  return text.split(/(\*\*[^*]+\*\*)/g).map((part, i) =>
    part.startsWith("**") && part.endsWith("**") && part.length > 4
      ? <strong key={i}>{part.slice(2, -2)}</strong>
      : <span key={i}>{part}</span>,
  );
}
```

- [ ] **Step 4: Run test, expect pass.**
- [ ] **Step 5: Swap the sinks.** In SuggestionsDrawer and StreakCard, replace the `dangerouslySetInnerHTML={{ __html: ... }}` element with the same element rendering `{renderBold(sourceText)}` as children, where `sourceText` is the pre-escaping raw string (delete the local escape/replace helpers that built the HTML). If StreakCard's `whatHtml` turns out to use markup other than `**bold**` (check while editing), extend `renderBold`'s regex to cover exactly that markup with the same escaped-by-React approach and add a test for it — do not reintroduce raw HTML.
- [ ] **Step 6: Full check:** `npx vitest run` → green; `npm run build` → succeeds.
- [ ] **Step 7: Visual verify** on the preview server: open the dashboard, confirm suggestions and streak cards render with bolding intact.
- [ ] **Step 8: Commit, PR, merge, verify live.**

*(Findings-driven fix tasks from Task 7, appended below by severity.)*

---

## Phase 2b — Findings-driven fixes (from 2026-07-01 audit)

Severity order. Each is one branch/PR in `twins-dash`. DB fixes are migrations to
jwrpj via MCP `apply_migration`; after each, insert the version row into
`supabase_migrations.schema_migrations` (see Operational facts). Save the
migration SQL into `twins-dash/supabase/migrations/<timestamp>_<slug>.sql` too so
the repo and DB stay in sync. **Never** blind-write a function body: fetch the
current definition first (`select pg_get_functiondef(p.oid) from pg_proc p join
pg_namespace n on n.oid=p.pronamespace where n.nspname='public' and
p.proname='<fn>';`) and reapply it verbatim with only the guard added.

### Task F1: PR-1 — Guard critical unauthenticated privilege-escalation RPCs (Critical: C1–C3, H1)

**Branch:** `fix/rpc-auth-guards` | **Migration:** `guard_invite_rpcs`

- [ ] **Step 1: Capture current defs.** For each of `invite_user`, `update_invite`, `set_role_permissions`, `revoke_invite`, `claim_invite`, `check_invite`, `get_invite_role`, run `pg_get_functiondef` and save to scratchpad. These are the ground truth for the CREATE OR REPLACE bodies.
- [ ] **Step 2: Confirm the guard helper.** Verify `public.has_role(uuid, app_role)` exists and its signature (`select pg_get_functiondef(oid) from pg_proc where proname='has_role';`). The guard pattern: `if not public.has_role(auth.uid(), 'admin') then raise exception 'admin only'; end if;` — for anon, `auth.uid()` is null so it raises. Confirm `set_tier_permissions` uses exactly this (it's the working reference).
- [ ] **Step 3: Write the migration** (`apply_migration` name `guard_invite_rpcs`): for `invite_user`, `update_invite`, `set_role_permissions` — CREATE OR REPLACE with the captured body, guard inserted as the first statement, keep `security definer` + `set search_path = public, pg_temp`. For `revoke_invite` — same admin guard. For `claim_invite` — replace with a body that enforces `p_user_id = auth.uid()` and that the invite email matches the JWT email (`auth.jwt()->>'email'`), so a user can only claim their own invite. Then:
  ```sql
  revoke execute on function public.invite_user(text, text) from anon;
  revoke execute on function public.update_invite(text, text, jsonb, text) from anon;
  revoke execute on function public.set_role_permissions(text, jsonb) from anon;
  revoke execute on function public.revoke_invite(text) from anon;
  -- claim_invite/check_invite/get_invite_role: keep authenticated, revoke anon
  revoke execute on function public.claim_invite(uuid, text) from anon;
  ```
  (Verify each function's exact argument-type signature from Step 1 before writing the REVOKE — arg types must match exactly.)
- [ ] **Step 4: Verify signup still works.** Trace the signup/invite flow in the app: does any pre-auth (anon) path call `check_invite`/`claim_invite`? Read `src/integrations/supabase/invite-client.ts`, `AuthContext`, and the accept-invite page. If claim happens post-login (authenticated), the anon revoke is safe. If any legit anon call exists, keep that specific function anon-executable but guarded by the email-match check instead of a blanket revoke. Document the trace result in the PR.
- [ ] **Step 5: Prove the hole is closed.** After migration, simulate an anon RPC with the anon key only (no user JWT):
  ```bash
  curl -s -X POST "$VITE_SUPABASE_URL/rest/v1/rpc/invite_user" \
    -H "apikey: $VITE_SUPABASE_PUBLISHABLE_KEY" \
    -H "Content-Type: application/json" \
    -d '{"p_email":"probe@example.com","p_role":"admin"}'
  ```
  Expected: permission-denied / `admin only`, NOT success. Then confirm no `probe@example.com` row exists in `invited_emails`.
- [ ] **Step 6: Insert schema_migrations version row; save migration SQL into `twins-dash/supabase/migrations/`; commit; PR; merge.**
- [ ] **Step 7: Live smoke:** load twinsdash.com, confirm login works and the Users admin page still lists/invites (as admin).

### Task F2: PR-2 — requireAdminAuth on list-users + manage-user (High: H2)

**Branch:** `fix/user-admin-auth`
**Files:** `supabase/functions/list-users/index.ts`, `supabase/functions/manage-user/index.ts`

- [ ] **Step 1:** Read both handlers + `_shared/auth.ts` to confirm the `requireAdminAuth(req)` signature and its return shape (`{ error, user }` or similar).
- [ ] **Step 2:** Add as the first statement inside each handler (after CORS preflight handling): `const gate = await requireAdminAuth(req); if (gate.error) return gate.error;` (match the real shape from Step 1). Add the import.
- [ ] **Step 3:** `deno check` both files (or rely on deploy check).
- [ ] **Step 4:** Deploy both via `deploy_edge_function`. Verify with the admin UI (Users page still works) and an anon-key-only curl (expect 401/403).
- [ ] **Step 5:** Commit, PR, merge, live smoke.

### Task F3: PR-3 — SECURITY DEFINER views → security_invoker + revoke anon (High: H3)

**Branch:** `fix/definer-views` | **Migration:** `views_security_invoker`

- [ ] **Step 1:** For all 13 views (`tech_job_type_kpi_90d`, `tech_job_type_team_medians_90d`, `v_job_line_item_counts`, `v_my_jobs`, `v_my_job_parts`, `v_my_commissions`, `v_review_clicks_by_tech`, `v_jobs_with_parts`, `v_tech_records_ranked`, `v_ghl_booking_rate_accurate`, `v_reviews_by_tech`, `v_yesterday_recap`, `v_jobs_needing_review`), capture `pg_get_viewdef`.
- [ ] **Step 2:** Migration: `alter view public.<name> set (security_invoker = true);` for each (PG17 supports this). Then `revoke select on public.<name> from anon;` for every view that is staff-only (all except any genuinely public one — none here are public-facing). Keep `authenticated` grants.
- [ ] **Step 3: Critical regression check.** With `security_invoker`, each view now runs under the CALLER's RLS. Confirm the app still reads them as an authenticated tech/admin: the base-table policies must permit what the view needs. For each view, identify its caller (grep the frontend + hooks for the view name) and confirm the caller's role has base-table SELECT. If a view breaks (empty where it shouldn't be), the base table needs a matching SELECT policy — add it in the same migration or revert that view to definer with an explicit internal filter. This is the highest-regression-risk fix; test the affected dashboard pages on preview before merge.
- [ ] **Step 4:** Re-run `get_advisors` security → confirm `security_definer_view` count drops to 0 (or only intentional ones remain).
- [ ] **Step 5:** schema_migrations row; save SQL to repo; commit; PR; merge; live smoke of the tech scorecard + reviews + line-item pages.

### Task F4: PR-4 — Real secret gates on cron endpoints (High: H6, H7)

**Branch:** `fix/cron-secret-gates`
**Files:** `supabase/functions/cron-friday-paystub-send/index.ts`, `reconcile-invoices-nightly/index.ts`, `cron-weekly-lowstock/index.ts`

- [ ] **Step 1:** Confirm the existing cron secret. Check function secrets for `EMAIL_CRON_SECRET` (memory notes a possible GUC/secret mismatch — verify the actual name in use). Read how other correctly-gated crons read it.
- [ ] **Step 2:** Find the pg_cron jobs that invoke these three (`select jobname, command from cron.job;`) and confirm each `http_post` call's headers — the gate must match what cron already sends, or we update both together. **Do not deploy an enforce-gate until the cron caller is confirmed to send the matching secret**, or the real cron breaks (payroll emails, revenue reconcile).
- [ ] **Step 3:** Add to the top of each handler (after CORS): `if (req.headers.get('authorization') !== 'Bearer ' + Deno.env.get('EMAIL_CRON_SECRET')) return new Response('forbidden', { status: 403 });`. Keep `verify_jwt=false`. Remove the misleading "self-gates" comments.
- [ ] **Step 4:** Update the corresponding `cron.job` commands (via migration on jwrpj) to send `Authorization: Bearer <secret>` if they don't already. Apply cron update and function deploy together.
- [ ] **Step 5:** Verify: anon curl without the header → 403; then wait for / manually trigger one real cron cycle and confirm it still runs (check `get_logs` + that paystub/reconcile side effects occur on schedule). **Rollback = redeploy without the gate.**
- [ ] **Step 6:** Commit, PR, merge, monitor next real cron fire.

### Task F5: PR-6 — Remaining anon-RPC guards + anon EXECUTE sweep (Medium: M1–M4)

**Branch:** `fix/rpc-anon-sweep` | **Migration:** `rpc_anon_execute_sweep`

- [ ] **Step 1:** Add admin/payroll guards (capture-def-then-replace, as Task F1) to `recompute_commission_for_job` (guard `has_payroll_access(auth.uid())`), `set_job_type_callback` + `set_job_type_resolution` (guard admin/manager or `has_accountability_access`).
- [ ] **Step 2:** `revoke execute` from anon on those five + `check_invite`/`get_invite_role` (if not already done in F1).
- [ ] **Step 3: The sweep (do carefully).** Build the definitive list of functions anon legitimately needs (from the F1 signup trace + any other pre-auth RPC). Then: `revoke execute on all functions in schema public from anon;` followed by explicit `grant execute` re-grants for that small allowlist, plus `alter default privileges in schema public revoke execute on functions from anon;`. **Before applying, dry-run the impact:** list every function anon currently has EXECUTE on and cross-check against pre-auth app calls. Any uncertainty → keep the targeted per-function revokes from Steps 1–2 and defer the blanket sweep to a follow-up with Daniel's sign-off.
- [ ] **Step 4:** Re-run advisors → `anon_security_definer_function_executable` count drops sharply. Verify login/signup/anon flows still work on preview.
- [ ] **Step 5:** schema_migrations row; save SQL; commit; PR; merge; live smoke.

### Task F6: PR-7 — Role checks on verify_jwt-only edge functions (Medium: M5)

**Branch:** `fix/edgefn-role-checks`

- [ ] **Step 1:** For each function in Stream 2's F7 list, classify: admin-facing (add `requireAdminAuth`), tech-facing (add the tech gate), or pg_cron-only (add the `EMAIL_CRON_SECRET` Bearer check + update its cron.job header). Produce the per-function decision list.
- [ ] **Step 2:** Apply the gate to each (one commit per ~5 functions for reviewability). `deno check` each.
- [ ] **Step 3:** Deploy in batches; after each batch verify the corresponding UI/cron still works (`get_logs`, spot-check the feature). Any cron-only fn: confirm its cron.job sends the secret before enforcing.
- [ ] **Step 4:** Commit, PR, merge, monitor.

### Task F7: PR-9 — react-router-dom 6.30.4 (Medium: M7)

**Branch:** `fix/react-router-cve`

- [ ] **Step 1:** `cd twins-dash && npm install react-router-dom@6.30.4` (patch bump within 6.30.x — verify it's non-breaking: `npm ls react-router-dom`).
- [ ] **Step 2:** `npx vitest run` → green; `npm run build` → succeeds.
- [ ] **Step 3:** Preview smoke: login redirect (`Auth.tsx` `location.state.from` path), protected-route redirects, deep links all work.
- [ ] **Step 4:** `npm audit | grep react-router` → advisory gone. Commit, PR, merge.

### Task F8: PR-10 — Low-severity cleanup (M8, L1–L4)

**Branch:** `fix/low-sev-cleanup` (may split if diffs get large)

- [ ] **Step 1: review-redirect rate-limit (M8).** Add the same IP+slug rate-limit the webhooks use, and record a `user_agent`/bot flag on `review_card_clicks`; exclude obvious bots from the dashboard rollup query. Keep the redirect itself open. Deploy, verify a real card click still logs + redirects.
- [ ] **Step 2: debug functions (L1).** Confirm with `get_logs` that the 12 `investigate-*`/`debug-*`/analysis functions have no recent legitimate invocations, then delete their directories (reversible via git). If any is unexpectedly in use, add `requireAdminAuth` instead.
- [ ] **Step 3: SettingsPanel document.write (L2).** Replace `document.write(previewHtml)` with a sandboxed preview: render into an `<iframe sandbox srcDoc={previewHtml}>` in a modal, or a Blob URL opened with `noopener`. Preview-verify the digest preview renders.
- [ ] **Step 4: .env gitignore (L3).** `git rm --cached .env`, add `.env` to `.gitignore`, confirm `.env.example` still tracked. (Values are anon-tier; no rotation needed.)
- [ ] **Step 5: DB hygiene (L4).** Migration: enable leaked-password protection is an Auth dashboard setting (note it for Daniel — it's a console toggle, not SQL); `alter extension pg_net set schema extensions;` (verify no code references `net.` unqualified first); `alter function ... set search_path = public, pg_temp;` for the 19 flagged functions.
- [ ] **Step 6:** Tests green, build ok, commit, PR, merge, live smoke.

---

## Phase 3 — Code-health ratchet

### Task 11: ESLint safe autofix pass

**Files (branch `chore/eslint-safe-autofix`):** whatever `--fix` touches; no manual edits.

- [ ] **Step 1: Baseline:** `npx vitest run` → record green; `npx eslint src 2>&1 | tail -3` → record count.
- [ ] **Step 2: Layout-only autofix:** `npx eslint src --fix --fix-type layout` (whitespace/format-class fixes only — no behavior changes by construction).
- [ ] **Step 3: Verify:** `npx vitest run` → green; `npm run build` → succeeds; `git diff --stat | tail -3` recorded.
- [ ] **Step 4:** Commit, PR, merge. If the diff is empty, record "no layout fixes available" in the findings report and close the task.

### Task 12: Strict-TypeScript ratchet for changed files

**Files (branch `chore/strict-ratchet`):**
- Create: `tsconfig.strict.json`, `scripts/strict-ratchet.mjs`, `scripts/strict-ratchet-exempt.txt`
- Create or Modify: `.github/workflows/` (check what exists first: `ls .github/workflows/ 2>/dev/null`)

- [ ] **Step 1: Strict config** — `tsconfig.strict.json` (verify the base filename; Vite templates use `tsconfig.app.json`, else extend `tsconfig.json`):

```json
{
  "extends": "./tsconfig.app.json",
  "compilerOptions": { "strict": true, "noEmit": true, "skipLibCheck": true }
}
```

- [ ] **Step 2: Ratchet script** — `scripts/strict-ratchet.mjs`:

```js
#!/usr/bin/env node
// Changed .ts/.tsx files (vs base) must type-check under strict mode.
// tsc checks the whole program; we only FAIL on errors inside changed files.
import { execSync } from "node:child_process";
import { readFileSync, existsSync } from "node:fs";

const base = process.argv[2] ?? "origin/main";
const exempt = existsSync("scripts/strict-ratchet-exempt.txt")
  ? new Set(readFileSync("scripts/strict-ratchet-exempt.txt", "utf8").split("\n").filter(Boolean))
  : new Set();

const changed = execSync(`git diff --name-only --diff-filter=ACMR ${base}...HEAD`, { encoding: "utf8" })
  .split("\n")
  .filter((f) => /^src\/.*\.(ts|tsx)$/.test(f) && !f.includes("__tests__") && !exempt.has(f));

if (changed.length === 0) { console.log("strict-ratchet: no changed src files"); process.exit(0); }

let out = "";
try { execSync("npx tsc -p tsconfig.strict.json --pretty false", { encoding: "utf8" }); }
catch (e) { out = (e.stdout ?? "") + (e.stderr ?? ""); }

const bad = out.split("\n").filter((line) => changed.some((f) => line.startsWith(f)));
if (bad.length) {
  console.error(`strict-ratchet: ${changed.length} changed file(s) must pass strict TS:\n` + bad.join("\n"));
  process.exit(1);
}
console.log(`strict-ratchet: ${changed.length} changed file(s) strict-clean`);
```

- [ ] **Step 3: Test the script locally** on a throwaway branch: add `const x: string = 1 as any; console.log(x)` type error... instead create a scratch file `src/lib/__ratchet_probe.ts` containing `export const n: number = "no";`, commit it, run `node scripts/strict-ratchet.mjs origin/main` → exits 1 naming the probe file. Delete the probe, run again → exits 0. Reset the throwaway commits.
- [ ] **Step 4: Seed the exempt list** with the files known to be far from strict-clean so touching them doesn't wall a future fix: start EMPTY; only add a file when a real PR hits an unreasonable wall, and record each addition in the PR description. (Empty file committed with a comment header.)
- [ ] **Step 5: CI workflow.** If `.github/workflows/` has an existing CI file, add a job; else create `.github/workflows/ci.yml`:

```yaml
name: ci
on:
  pull_request:
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with: { fetch-depth: 0 }
      - uses: actions/setup-node@v4
        with: { node-version: 20, cache: npm }
      - run: npm ci
      - run: npx vitest run
      - run: npm run build
      - run: node scripts/strict-ratchet.mjs origin/${{ github.base_ref }}
```

- [ ] **Step 6:** Commit, push, open PR — the PR itself exercises the workflow; `scripts/*.mjs` files are new so the ratchet runs on... (scripts/ isn't under src/, so ratchet reports "no changed src files" — expected). Confirm all three CI steps pass on the PR, then merge.

### Task 13: After-scorecard + close-out

- [ ] **Step 1:** Re-run `get_advisors` (security + performance) on jwrpj. Diff against Task 2's raw JSON. Every previously-reported item must be resolved or listed as accepted-with-reason.
- [ ] **Step 2:** Fill the "After-scorecard" section of the findings report: before/after advisor counts, webhook validation mode (`enforce` + soak evidence), XSS sinks remaining (target: chart.tsx constant-only), npm audit remaining criticals (target: 0 reachable), CI ratchet status.
- [ ] **Step 3:** Verify done-criteria from the spec one by one, quoting evidence (test runs, log-table queries, live-site check). Anything unmet goes back to Task 7's amendment flow.
- [ ] **Step 4:** Commit final report (outer repo). Summarize before/after for Daniel in plain terms.

---

## Execution notes

- **Order:** 1→7 strictly sequential-ish (streams 2–6 can run as parallel read-only subagents), then Task 8 immediately (its 24h+ capture soak runs while other fixes proceed), High findings, Task 10, Mediums, Task 9 (after soak), Phase 3, Task 13.
- **One branch per task** in twins-dash; findings/plan/report commits go to the outer repo. Commit early and often — the shared checkout has a history of branch-clobber incidents.
- **If a webhook or payroll invariant is ever at risk, stop that task and flag it** rather than improvising.
