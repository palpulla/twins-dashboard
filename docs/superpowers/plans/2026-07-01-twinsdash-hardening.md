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

*(Findings-driven fix tasks from Task 7 are appended here by severity.)*

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
