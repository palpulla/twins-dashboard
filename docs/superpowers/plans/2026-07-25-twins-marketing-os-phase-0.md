# Twins Marketing OS — Phase 0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop every automated marketing publisher, and reduce TwinsDash's marketing surface to read-only stats plus the assign-source worklist.

**Architecture:** Two defence layers on the Supabase side — disable the pg_cron triggers (the real guarantee), then add a fail-closed env gate inside the edge functions (covers manual invokes and warm isolates). Neutralise the GHL writer at its lowest-level function so every caller is covered. Delete the launchd agents outright. Then delete the scheduler UI from the TwinsDash React app. No data is dropped anywhere.

**Tech Stack:** Supabase (pg_cron, Deno edge functions), Deno test, React + TypeScript + Vitest, Python + pytest, macOS launchd.

---

## Two repositories — read before starting

| Path | Remote | Used in this plan for |
| --- | --- | --- |
| `~/twins-dashboard` | `palpulla/twins-dashboard` | This plan, `twins-content-engine/` (Python + launchd plists) |
| `~/twins-dashboard/twins-dash` | `palpulla/twins-dash` | Edge functions, React app |

`twins-dash` is a **separate git repo nested inside** `twins-dashboard`. Commits for edge functions and React go to the inner repo; commits for the content engine go to the outer one. Getting this wrong silently stages nothing.

## Deviation from the spec — read and confirm

The spec says to "consolidate what remains into one read-only Marketing page built from `Channels.tsx` and the funnel / live-lead / review panels of `MarketingSourceROI.tsx`."

**This plan does not merge those two pages.** Reading the code showed `Channels.tsx` (178 lines) is *already* exactly the intended end state: read-only rollup, honesty meter, plus the assign-source worklist Daniel asked to keep. Merging 316 lines of `MarketingSourceROI.tsx` into it is a substantial refactor of working, tested code with no functional gain, on a page that is about to be superseded by the new app anyway.

Instead: delete the three scheduler pages and keep the two read-only pages that already exist. If Daniel wants the single-page merge, it is a follow-up, not a prerequisite.

**Second deviation, stronger than specced.** The spec says to make `publish_to_ghl.py` dry-run-only. This plan instead guards `engine/ghl_social.py::create_social_post`, the function that actually issues the POST. `publish_to_ghl.py` is only one of its callers — `engine/ig_draft.py` and anything else importing it would otherwise still be able to publish. Guarding the writer covers every caller, including ones added later.

## Production facts this plan depends on (verified 2026-07-25)

- Project ref: `jwrpjuqaynownxaoeayi` (`twins-dash-prod`, eu-west-1)
- Cron **103** `publish-content-5min` — `*/5 * * * *`, active
- Cron **106** `poll-video-jobs-2min` — `*/2 * * * *`, active
- Crons **33, 36, 37, 86, 104, 105** stay active and MUST NOT be touched
- Cron **63** `call-intake-process-5min` is NOT a marketing scheduler — do not touch
- `deno 2.9.3` is on PATH
- `_shared/marketing/*.test.ts` are **Deno** tests (`deno test`), not vitest. Vitest only covers `src/**`, `services/**`, `_shared/**/__tests__/**`, `sync-gbp-reviews/__tests__/**`

Prod SQL runs through the Supabase MCP `execute_sql` tool or the Management API with the keychain PAT (`security find-generic-password -s "Supabase CLI" -w`). Docker is off, so edge function deploys need `--use-api`. Never `supabase db push`.

## File Structure

**Create (inner repo `twins-dash`):**
- `supabase/functions/_shared/marketing/publishing-gate.ts` — fail-closed kill switch. Pure functions, no I/O, so it is unit-testable and has one responsibility.
- `supabase/functions/_shared/marketing/publishing-gate.test.ts` — Deno test.

**Modify (inner repo `twins-dash`):**
- `supabase/functions/publish-content/index.ts` — import + gate at entry (imports at 11–14, `Deno.serve` at 40)
- `supabase/functions/poll-video-jobs/index.ts` — import + gate at entry (imports at 8–9, `Deno.serve` at 21)
- `src/App.tsx` — remove 3 lazy imports (59, 60, 62) and 3 routes (114, 115, 117)
- `src/components/AppShellWithNav.tsx` — remove 3 nav entries (66, 67, 69)

**Delete (inner repo `twins-dash`):**
- `src/pages/marketing/Queue.tsx`, `Calendar.tsx`, `Spend.tsx`
- `src/pages/marketing/__tests__/Calendar.test.ts`
- `src/components/marketing/AiComposer.tsx`, `ContentCard.tsx`
- `src/components/marketing/__tests__/ContentCard.test.tsx`
- `src/hooks/marketing/use-content-queue.ts`, `use-draft-content.ts`, `use-generate-video.ts`, `use-post-performance.ts`, `use-spend-recommendations.ts`
- `src/hooks/marketing/__tests__/use-content-queue.test.ts`, `use-post-performance.test.ts`
- `src/lib/marketing/utm.ts` + `src/lib/marketing/__tests__/utm.test.ts` (UTM enforcement existed only for the scheduler; verified zero non-test importers)

**Keep untouched:** `src/pages/marketing/Channels.tsx`, `src/pages/MarketingSourceROI.tsx`, `src/hooks/marketing/use-channel-rollup.ts`, `src/lib/marketing/channels.ts`, `src/styles/marketing.css`.

**Modify (outer repo `twins-dashboard`):**
- `twins-content-engine/engine/ghl_social.py` — retirement guard in `create_social_post`
- `twins-content-engine/tests/test_ghl_social.py` — tests for the guard

**Delete (outer repo `twins-dashboard`):**
- `twins-content-engine/deploy/com.twins.ig-publish.plist`
- `twins-content-engine/deploy/com.twins.ig-generate.plist`
- `twins-content-engine/deploy/com.twins.ig-remind.plist`

---

### Task 1: Record the pre-teardown state

No code. This is the rollback record — without it you cannot prove what you changed.

**Files:**
- Create: `docs/marketing/2026-07-25-phase0-rollback.md` (outer repo)

- [ ] **Step 1: Capture the cron state**

Run this SQL against `jwrpjuqaynownxaoeayi`:

```sql
select jobid, jobname, schedule, active
from cron.job
where jobid in (33, 36, 37, 63, 86, 103, 104, 105, 106)
order by jobid;
```

Expected: 103 and 106 both `active = true`; all others `active = true`.

- [ ] **Step 2: Capture content table row counts**

```sql
select
  (select count(*) from content_items)          as content_items,
  (select count(*) from content_performance)    as content_performance,
  (select count(*) from video_jobs)             as video_jobs,
  (select count(*) from spend_recommendations)  as spend_recommendations;
```

- [ ] **Step 3: Confirm no launchd agent is currently loaded**

```bash
launchctl list | grep -i twins || echo "no twins agents loaded"
```

Expected: `no twins agents loaded`

- [ ] **Step 4: Write the rollback note**

Create `docs/marketing/2026-07-25-phase0-rollback.md` containing the two SQL result sets from Steps 1–2 verbatim, plus this restore procedure:

```markdown
# Phase 0 rollback

To restore automated publishing:

1. `select cron.alter_job(103, active := true);`
2. `select cron.alter_job(106, active := true);`
3. Set project secret `MARKETING_PUBLISHING_ENABLED=true`
4. `git revert` the Phase 0 commits in twins-dash and twins-dashboard
5. Re-install launchd agents from git history if Instagram automation is wanted
   (they were NOT loaded at teardown time, so this is a deliberate re-enable,
   not a restore)
```

- [ ] **Step 5: Commit**

```bash
cd ~/twins-dashboard
git add docs/marketing/2026-07-25-phase0-rollback.md
git commit -m "docs(marketing): record pre-teardown state for Phase 0 rollback"
```

---

### Task 2: Disable the two publishing crons

Do this first. It is the change that actually stops publishing; everything after is defence in depth.

- [ ] **Step 1: Disable both jobs**

```sql
select cron.alter_job(103, active := false);
select cron.alter_job(106, active := false);
```

- [ ] **Step 2: Verify they are off and nothing else moved**

```sql
select jobid, jobname, active
from cron.job
where jobid in (33, 36, 37, 63, 86, 103, 104, 105, 106)
order by jobid;
```

Expected: `103 → false`, `106 → false`, and `33, 36, 37, 63, 86, 104, 105 → true`.

If any job other than 103/106 changed, re-enable it immediately with
`select cron.alter_job(<id>, active := true);` and stop.

- [ ] **Step 3: Confirm the jobs stop firing**

Wait 6 minutes (cron 103 runs every 5), then:

```sql
select j.jobname, max(r.start_time) as last_run
from cron.job_run_details r
join cron.job j on j.jobid = r.jobid
where j.jobid in (103, 106)
group by j.jobname;
```

Expected: `last_run` for both is more than 5 minutes in the past and does not advance on repeat.

---

### Task 3: Build the fail-closed publishing gate

**Files:**
- Create: `twins-dash/supabase/functions/_shared/marketing/publishing-gate.ts`
- Test: `twins-dash/supabase/functions/_shared/marketing/publishing-gate.test.ts`

- [ ] **Step 1: Write the failing test**

Create `twins-dash/supabase/functions/_shared/marketing/publishing-gate.test.ts`:

```ts
import { assertEquals } from "https://deno.land/std@0.224.0/assert/mod.ts";
import { publishingDisabledResponse, publishingEnabled } from "./publishing-gate.ts";

Deno.test("publishing stays disabled unless the flag is exactly \"true\"", () => {
  // Fails closed: an unset, empty, or malformed value must never enable publishing.
  assertEquals(publishingEnabled(undefined), false);
  assertEquals(publishingEnabled(""), false);
  assertEquals(publishingEnabled("false"), false);
  assertEquals(publishingEnabled("TRUE"), false);
  assertEquals(publishingEnabled("True"), false);
  assertEquals(publishingEnabled("1"), false);
  assertEquals(publishingEnabled("yes"), false);
  assertEquals(publishingEnabled(" true"), false);
});

Deno.test("publishing enables only on the exact string \"true\"", () => {
  assertEquals(publishingEnabled("true"), true);
});

Deno.test("disabled response is a 200 naming the function and the reason", async () => {
  const res = publishingDisabledResponse("publish-content");
  assertEquals(res.status, 200);
  assertEquals(res.headers.get("Content-Type"), "application/json");
  const body = await res.json();
  assertEquals(body.ok, false);
  assertEquals(body.disabled, true);
  assertEquals(body.fn, "publish-content");
  assertEquals(typeof body.reason, "string");
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd ~/twins-dashboard/twins-dash
deno test --allow-env --no-check supabase/functions/_shared/marketing/publishing-gate.test.ts
```

Expected: FAIL — `Module not found ... publishing-gate.ts`

- [ ] **Step 3: Write the implementation**

Create `twins-dash/supabase/functions/_shared/marketing/publishing-gate.ts`:

```ts
// Kill switch for outbound marketing publishing from TwinsDash.
//
// Phase 0 of the Twins Marketing OS project (2026-07-25) retired publishing
// from TwinsDash; it moves to the twins-marketing-os app in Phase 3. The
// pg_cron triggers (103 publish-content-5min, 106 poll-video-jobs-2min) are
// disabled and are the real guarantee. This gate is the second layer: it stops
// a manual invoke, and it stops a warm isolate that still holds old env.
//
// Fails CLOSED. Anything other than the exact string "true" means disabled,
// so a typo, a blank secret, or a deleted secret can never resume publishing.
//
// Sibling implementation: envEnabled() in _shared/text-agent/polling.ts. Kept
// separate deliberately — marketing should not import from the text agent.

export function publishingEnabled(value: string | undefined): boolean {
  return value === "true";
}

export function publishingDisabledResponse(fn: string): Response {
  return new Response(
    JSON.stringify({
      ok: false,
      disabled: true,
      fn,
      reason:
        "Marketing publishing was retired from TwinsDash on 2026-07-25. " +
        "Set MARKETING_PUBLISHING_ENABLED=true to re-enable.",
    }),
    { status: 200, headers: { "Content-Type": "application/json" } },
  );
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
cd ~/twins-dashboard/twins-dash
deno test --allow-env --no-check supabase/functions/_shared/marketing/publishing-gate.test.ts
```

Expected: `ok | 3 passed | 0 failed`

- [ ] **Step 5: Commit**

```bash
cd ~/twins-dashboard/twins-dash
git add supabase/functions/_shared/marketing/publishing-gate.ts \
        supabase/functions/_shared/marketing/publishing-gate.test.ts
git commit -m "feat(marketing): add fail-closed publishing kill switch"
```

---

### Task 4: Gate `publish-content`

**Files:**
- Modify: `twins-dash/supabase/functions/publish-content/index.ts:14` (imports) and `:40` (`Deno.serve`)

- [ ] **Step 1: Add the import**

After line 14 (`import { applyResults, planChannels, type ChannelResult } from "./logic.ts";`) add:

```ts
import { publishingDisabledResponse, publishingEnabled } from "../_shared/marketing/publishing-gate.ts";
```

- [ ] **Step 2: Add the gate as the first statement inside the handler**

Change line 40 from:

```ts
Deno.serve(async (req) => {
  const supabase = createClient(
```

to:

```ts
Deno.serve(async (req) => {
  // Phase 0 teardown: publishing is retired from TwinsDash. Checked before the
  // Supabase client is built so a disabled invoke does no work and touches no rows.
  if (!publishingEnabled(Deno.env.get("MARKETING_PUBLISHING_ENABLED"))) {
    return publishingDisabledResponse("publish-content");
  }

  const supabase = createClient(
```

- [ ] **Step 3: Type-check the function**

```bash
cd ~/twins-dashboard/twins-dash
deno check --no-lock supabase/functions/publish-content/index.ts
```

Expected: no errors. (Remote-module type errors unrelated to this change may appear; the gate lines must be clean.)

- [ ] **Step 4: Commit**

```bash
cd ~/twins-dashboard/twins-dash
git add supabase/functions/publish-content/index.ts
git commit -m "feat(marketing): gate publish-content behind the publishing kill switch"
```

---

### Task 5: Gate `poll-video-jobs`

**Files:**
- Modify: `twins-dash/supabase/functions/poll-video-jobs/index.ts:9` (imports) and `:21` (`Deno.serve`)

- [ ] **Step 1: Add the import**

After line 9 (`import { getProvider } from "../_shared/marketing/video-providers.ts";`) add:

```ts
import { publishingDisabledResponse, publishingEnabled } from "../_shared/marketing/publishing-gate.ts";
```

- [ ] **Step 2: Add the gate as the first statement inside the handler**

Change line 21 from:

```ts
Deno.serve(async (req) => {
```

to:

```ts
Deno.serve(async (req) => {
  // Phase 0 teardown: reel rendering is retired from TwinsDash along with publishing.
  if (!publishingEnabled(Deno.env.get("MARKETING_PUBLISHING_ENABLED"))) {
    return publishingDisabledResponse("poll-video-jobs");
  }
```

- [ ] **Step 3: Type-check the function**

```bash
cd ~/twins-dashboard/twins-dash
deno check --no-lock supabase/functions/poll-video-jobs/index.ts
```

Expected: no errors on the gate lines.

- [ ] **Step 4: Commit**

```bash
cd ~/twins-dashboard/twins-dash
git add supabase/functions/poll-video-jobs/index.ts
git commit -m "feat(marketing): gate poll-video-jobs behind the publishing kill switch"
```

---

### Task 6: Deploy both functions and verify the gate live

Docker is off, so `--use-api` is required.

- [ ] **Step 1: Confirm the secret is absent**

The gate fails closed, so *not* setting `MARKETING_PUBLISHING_ENABLED` is the disabled state. Confirm it is not already set to `true`:

```bash
PAT=$(security find-generic-password -s "Supabase CLI" -w)
curl -sS -H "Authorization: Bearer $PAT" \
  https://api.supabase.com/v1/projects/jwrpjuqaynownxaoeayi/secrets \
  | python3 -c "import json,sys;print([s['name'] for s in json.load(sys.stdin) if 'MARKETING' in s['name']] or 'not set — correct')"
```

Expected: `not set — correct`

- [ ] **Step 2: Deploy both functions**

```bash
cd ~/twins-dashboard/twins-dash
npx supabase functions deploy publish-content --project-ref jwrpjuqaynownxaoeayi --use-api
npx supabase functions deploy poll-video-jobs --project-ref jwrpjuqaynownxaoeayi --use-api
```

Expected: both report success and a bumped version.

- [ ] **Step 3: Verify the gate returns disabled**

Invoke each function the way its cron does, with a valid cron secret:

```sql
select net.http_post(
  url := 'https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/publish-content',
  headers := jsonb_build_object(
    'Content-Type', 'application/json',
    'x-cron-secret', (select decrypted_secret from vault.decrypted_secrets where name = 'email_cron_secret')
  ),
  body := '{}'::jsonb
);
```

Then read the response:

```sql
select status_code, content::jsonb ->> 'disabled' as disabled, content::jsonb ->> 'fn' as fn
from net._http_response order by id desc limit 1;
```

Expected: `status_code = 200`, `disabled = true`, `fn = publish-content`.

Repeat for `poll-video-jobs`, expecting `fn = poll-video-jobs`.

- [ ] **Step 4: Confirm nothing published**

```sql
select status, count(*) from content_items group by status order by status;
```

Expected: identical to the Task 1 snapshot. No item moved to `published`.

---

### Task 7: Retire the GHL social writer

The guard goes in `create_social_post`, not in the CLI script, so every caller is covered — including `engine/ig_draft.py` and anything else that imports it.

**Files:**
- Modify: `twins-dashboard/twins-content-engine/engine/ghl_social.py:138`
- Test: `twins-dashboard/twins-content-engine/tests/test_ghl_social.py`

- [ ] **Step 1: Write the failing tests**

Append to `twins-content-engine/tests/test_ghl_social.py`:

```python
def test_create_social_post_is_retired_by_default(tmp_path, monkeypatch):
    """Phase 0 retired GHL publishing from the content engine."""
    monkeypatch.delenv("GHL_SOCIAL_WRITES_ENABLED", raising=False)
    env_file = _write_env(tmp_path)
    with pytest.raises(RuntimeError, match="retired"):
        create_social_post(
            env_file=env_file,
            account_ids=["acct-1"],
            caption="A perfectly ordinary caption that is well over twenty characters long.",
            image_url="https://example.com/a.jpg",
            status="draft",
        )


def test_create_social_post_retirement_cannot_be_bypassed_by_truthy_values(tmp_path, monkeypatch):
    """Fails closed — only the exact string "true" re-enables writes."""
    env_file = _write_env(tmp_path)
    for value in ("1", "yes", "TRUE", "True", "", " true"):
        monkeypatch.setenv("GHL_SOCIAL_WRITES_ENABLED", value)
        with pytest.raises(RuntimeError, match="retired"):
            create_social_post(
                env_file=env_file,
                account_ids=["acct-1"],
                caption="A perfectly ordinary caption that is well over twenty characters long.",
                image_url="https://example.com/a.jpg",
                status="draft",
            )
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd ~/twins-dashboard/twins-content-engine
.venv/bin/pytest tests/test_ghl_social.py -k retired -v
```

Expected: FAIL — `DID NOT RAISE RuntimeError`

- [ ] **Step 3: Add the guard**

In `engine/ghl_social.py`, `os` is **not** currently imported. Line 22 reads
`from pathlib import Path` and line 24 reads `import requests`. Add `import os`
immediately above line 22 so the stdlib imports stay grouped:

```python
import os
from pathlib import Path
```

Then insert this function immediately above `create_social_post` (currently line 138):

```python
def _reject_if_retired() -> None:
    """Phase 0 (2026-07-25) retired GHL social publishing from the content engine.

    Scheduling moved to the twins-marketing-os app. The GHL Social Planner UI
    stays usable by hand; this only stops code from writing posts. Fails closed:
    only the exact string "true" re-enables writes.
    """
    if os.environ.get("GHL_SOCIAL_WRITES_ENABLED") != "true":
        raise RuntimeError(
            "GHL social writes are retired (Phase 0, 2026-07-25). Scheduling now "
            "lives in twins-marketing-os; use the GHL Social Planner UI by hand. "
            "Set GHL_SOCIAL_WRITES_ENABLED=true to override."
        )
```

Then make it the first statement of `create_social_post`, before `_reject_if_test_content`:

```python
def create_social_post(
    env_file: Path,
    account_ids: list[str],
    caption: str,
    image_url: str,
    status: str = "published",
) -> dict:
    """POST a post to the given GHL social accounts.

    Raises RuntimeError if GHL writes are retired (the default since Phase 0).
    Raises ValueError if env vars are missing. Raises RuntimeError on a
    non-2xx response, a response where success is not True, or when a
    non-draft (live) post looks like test/probe content. The message includes
    the status code and first 300 chars of the response body, never the token.
    """
    _reject_if_retired()
    _reject_if_test_content(caption, status)
```

- [ ] **Step 4: Run the full test file**

```bash
cd ~/twins-dashboard/twins-content-engine
.venv/bin/pytest tests/test_ghl_social.py -v
```

Expected: the two new tests PASS. Pre-existing tests that call `create_social_post` and expect success will now FAIL — that is correct behaviour, not a regression. Fix each by adding `monkeypatch.setenv("GHL_SOCIAL_WRITES_ENABLED", "true")` as the first line of those tests, since they are asserting the HTTP contract rather than the retirement policy.

- [ ] **Step 5: Run the whole engine suite**

```bash
cd ~/twins-dashboard/twins-content-engine
.venv/bin/pytest -q
```

Expected: all pass. Any other failure is a caller that publishes to GHL — record it, it is evidence of another publishing path.

- [ ] **Step 6: Commit**

```bash
cd ~/twins-dashboard
git add twins-content-engine/engine/ghl_social.py twins-content-engine/tests/test_ghl_social.py
git commit -m "feat(content-engine): retire GHL social writes behind a fail-closed guard"
```

---

### Task 8: Decommission the launchd agents

These were not loaded at teardown time, but the plists are live ammunition — a single `launchctl load` restarts Instagram publishing.

**Files:**
- Delete: `twins-content-engine/deploy/com.twins.ig-publish.plist`
- Delete: `twins-content-engine/deploy/com.twins.ig-generate.plist`
- Delete: `twins-content-engine/deploy/com.twins.ig-remind.plist`

- [ ] **Step 1: Confirm nothing is loaded and unload anything that is**

```bash
launchctl list | grep -i twins || echo "none loaded"
```

If any `com.twins.*` agent appears, unload it before deleting:

```bash
launchctl bootout gui/$(id -u)/com.twins.ig-publish 2>/dev/null || true
launchctl bootout gui/$(id -u)/com.twins.ig-generate 2>/dev/null || true
launchctl bootout gui/$(id -u)/com.twins.ig-remind 2>/dev/null || true
rm -f ~/Library/LaunchAgents/com.twins.ig-*.plist
```

- [ ] **Step 2: Delete the plists from the repo**

```bash
cd ~/twins-dashboard
git rm twins-content-engine/deploy/com.twins.ig-publish.plist \
       twins-content-engine/deploy/com.twins.ig-generate.plist \
       twins-content-engine/deploy/com.twins.ig-remind.plist
```

- [ ] **Step 3: Verify nothing else references them**

```bash
grep -rIn "com.twins.ig-" ~/twins-dashboard --exclude-dir=node_modules --exclude-dir=.git || echo "no references remain"
```

If `twins-content-engine/README.md` or `SKILL.md` documents installing them, update that text to say Instagram publishing was retired in Phase 0 and now belongs to twins-marketing-os.

- [ ] **Step 4: Commit**

```bash
cd ~/twins-dashboard
git add -A twins-content-engine/
git commit -m "chore(content-engine): decommission Instagram launchd agents"
```

---

### Task 9: Delete the scheduler UI from TwinsDash

**Files:**
- Modify: `twins-dash/src/App.tsx`, `twins-dash/src/components/AppShellWithNav.tsx`
- Delete: the pages, components, hooks and tests listed in File Structure

- [ ] **Step 1: Remove the routes**

In `src/App.tsx`, delete these three lazy imports (lines 59, 60, 62):

```tsx
const MarketingQueue = lazy(() => import("./pages/marketing/Queue"));
const MarketingCalendar = lazy(() => import("./pages/marketing/Calendar"));
const MarketingSpend = lazy(() => import("./pages/marketing/Spend"));
```

Delete these three routes (lines 114, 115, 117):

```tsx
<Route path="/marketing" element={<ProtectedRoute requiredRole="admin_or_manager"><AppShellWithNav><Suspense fallback={<PageSpinner />}><MarketingQueue /></Suspense></AppShellWithNav></ProtectedRoute>} />
<Route path="/marketing/calendar" element={<ProtectedRoute requiredRole="admin_or_manager"><AppShellWithNav><Suspense fallback={<PageSpinner />}><MarketingCalendar /></Suspense></AppShellWithNav></ProtectedRoute>} />
<Route path="/marketing/spend" element={<ProtectedRoute requiredRole="admin_or_manager"><AppShellWithNav><Suspense fallback={<PageSpinner />}><MarketingSpend /></Suspense></AppShellWithNav></ProtectedRoute>} />
```

Keep `MarketingSourceROI` (line 58) and `MarketingChannels` (line 61) and their routes (113, 116).

- [ ] **Step 2: Remove the nav entries**

In `src/components/AppShellWithNav.tsx`, delete lines 66, 67 and 69:

```tsx
{ to: `/marketing${navSuffix}`, label: "Content Queue", icon: <Send className="h-4 w-4" />, section: "marketing" as const, show: isAdmin || isManager },
{ to: `/marketing/calendar${navSuffix}`, label: "Calendar", icon: <CalendarDays className="h-4 w-4" />, section: "marketing" as const, show: isAdmin || isManager },
{ to: `/marketing/spend${navSuffix}`, label: "Spend", icon: <Wallet className="h-4 w-4" />, section: "marketing" as const, show: isAdmin || isManager },
```

Keep the ROI entry (65) and the Channels entry (68).

- [ ] **Step 3: Remove the now-unused icon imports**

`Send`, `CalendarDays` and `Wallet` may now be unused in that file. Check each:

```bash
cd ~/twins-dashboard/twins-dash
for i in Send CalendarDays Wallet; do
  echo "$i: $(grep -c "$i" src/components/AppShellWithNav.tsx) occurrences"
done
```

Any icon with exactly 1 occurrence remains only in the `lucide-react` import line — remove it from that import. `BarChart` and `Coins` are still used and must stay.

- [ ] **Step 4: Delete the files**

```bash
cd ~/twins-dashboard/twins-dash
git rm src/pages/marketing/Queue.tsx \
       src/pages/marketing/Calendar.tsx \
       src/pages/marketing/Spend.tsx \
       src/pages/marketing/__tests__/Calendar.test.ts \
       src/components/marketing/AiComposer.tsx \
       src/components/marketing/ContentCard.tsx \
       src/components/marketing/__tests__/ContentCard.test.tsx \
       src/hooks/marketing/use-content-queue.ts \
       src/hooks/marketing/use-draft-content.ts \
       src/hooks/marketing/use-generate-video.ts \
       src/hooks/marketing/use-post-performance.ts \
       src/hooks/marketing/use-spend-recommendations.ts \
       src/hooks/marketing/__tests__/use-content-queue.test.ts \
       src/hooks/marketing/__tests__/use-post-performance.test.ts \
       src/lib/marketing/utm.ts \
       src/lib/marketing/__tests__/utm.test.ts
```

- [ ] **Step 5: Verify no dangling imports**

```bash
cd ~/twins-dashboard/twins-dash
npx tsc -p tsconfig.app.json --noEmit
```

Expected: no errors. Any "cannot find module" points at an importer that was missed — fix it before continuing.

- [ ] **Step 6: Run the full test suite**

```bash
cd ~/twins-dashboard/twins-dash
npx vitest run
```

Expected: all pass, with the deleted tests gone from the count. Compare against the pre-change baseline — the only reduction should be the 5 deleted test files.

- [ ] **Step 7: Build**

```bash
cd ~/twins-dashboard/twins-dash
npm run build
```

Expected: succeeds.

- [ ] **Step 8: Commit**

```bash
cd ~/twins-dashboard/twins-dash
git add -A src/
git commit -m "feat(marketing): remove scheduler UI, leave read-only stats and assign-source"
```

---

### Task 10: Verify the end state

> **How this was actually verified (2026-07-25).** Steps 1–2 below require logging in, and
> credentials were not available. A stronger, auth-free check was substituted: rebuild and
> grep the shipped bundle for precise markers. Results from a fresh `npm run build`:
>
> | Marker | Expected | Result |
> | --- | --- | --- |
> | `/marketing/calendar`, `/marketing/spend` | gone | absent ✓ |
> | `MarketingQueue`, `MarketingCalendar`, `MarketingSpend` | gone | absent ✓ |
> | `Content Queue` (nav label) | gone | absent ✓ |
> | `claim_due_content_items`, `spend_recommendations` (RPCs) | gone | absent ✓ |
> | `/marketing/channels`, `/marketing-source-roi` | kept | present ✓ |
> | `Fix unknown sources` (assign-source worklist) | kept | present ✓ |
>
> This proves the removal at the artifact level — what actually ships — rather than at the
> source level. Note `dist/` was stale when first checked (built by concurrent work), so the
> rebuild was necessary; grepping a stale bundle would have been meaningless. Also note that
> grepping for bare words like `Queue`, `Calendar` or `Spend` produces false positives from
> unrelated features — use route paths and symbol names.
>
> Step 2's assign-source write path remains unverified end-to-end, since that genuinely needs
> an authenticated session. The RPC and UI are present and untouched.

- [ ] **Step 1: Confirm the surviving marketing surface**

```bash
cd ~/twins-dashboard/twins-dash
npm run dev
```

Visit `/marketing/channels`. Confirm all of the following are present: the rollup table, the honesty meter, the market and range filters, and the "Fix unknown sources" worklist with a working Assign button.

Visit `/marketing-source-roi`. Confirm it loads.

Visit `/marketing`, `/marketing/calendar` and `/marketing/spend`. Each must 404 or redirect — not render.

Confirm the sidebar Marketing group shows exactly two entries: ROI and Channels.

- [ ] **Step 2: Confirm the assign-source path still writes**

In the worklist, assign a source to one job and confirm it disappears from the list. Then verify the audit row:

```sql
select entity_table, action, created_at
from audit_log
where entity_table = 'jobs'
order by created_at desc limit 1;
```

If `set_job_lead_source` writes to a different audit surface, confirm via:

```sql
select lead_source, updated_at from jobs where id = '<the job id>';
```

Expected: `lead_source` is the value you assigned.

- [ ] **Step 3: Confirm the ingestion crons are untouched and healthy**

```sql
select j.jobid, j.jobname, j.active,
       max(r.start_time) as last_run,
       (array_agg(r.status order by r.start_time desc))[1] as last_status
from cron.job j
left join cron.job_run_details r on r.jobid = j.jobid
where j.jobid in (33, 36, 37, 63, 86, 104, 105)
group by j.jobid, j.jobname, j.active
order by j.jobid;
```

Expected: all `active = true`, and each `last_status` is `succeeded`.

- [ ] **Step 4: Confirm no data was dropped**

```sql
select
  (select count(*) from content_items)          as content_items,
  (select count(*) from content_performance)    as content_performance,
  (select count(*) from video_jobs)             as video_jobs,
  (select count(*) from spend_recommendations)  as spend_recommendations;
```

Expected: identical to the Task 1 snapshot.

- [ ] **Step 5: Open the PRs**

```bash
cd ~/twins-dashboard/twins-dash
git push -u origin HEAD
gh pr create --title "Phase 0: retire marketing publishing from TwinsDash" \
  --body "Disables crons 103 and 106, adds a fail-closed publishing kill switch to publish-content and poll-video-jobs, and removes the Queue, Calendar and Spend UI. Channels (read-only stats + assign-source worklist) and Source ROI are kept. No data dropped.

Spec: docs/superpowers/specs/2026-07-25-twins-marketing-os-design.md
Plan: docs/superpowers/plans/2026-07-25-twins-marketing-os-phase-0.md

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

```bash
cd ~/twins-dashboard
git push -u origin HEAD
gh pr create --title "Phase 0: retire content-engine publishing paths" \
  --body "Retires GHL social writes behind a fail-closed guard and decommissions the three Instagram launchd agents. Generation scripts are kept — they seed Phase 4.

Spec: docs/superpowers/specs/2026-07-25-twins-marketing-os-design.md

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

---

## Done when

- Crons 103 and 106 are inactive; 33, 36, 37, 63, 86, 104, 105 still active and succeeding
- `publish-content` and `poll-video-jobs` return `disabled: true` to a valid cron-authenticated invoke
- `create_social_post` raises unless `GHL_SOCIAL_WRITES_ENABLED=true`
- No `com.twins.*` plist exists in the repo or in `~/Library/LaunchAgents`
- TwinsDash Marketing nav shows exactly ROI and Channels; the three scheduler routes no longer render
- The assign-source worklist still writes
- `tsc` clean, `vitest run` green, `npm run build` succeeds, `pytest` green
- Row counts for `content_items`, `content_performance`, `video_jobs`, `spend_recommendations` unchanged

## Execution findings — two gaps this plan originally missed

Both were found during execution, not planning. Recorded so the plan is not read
later as if it had been complete.

### 1. `generate-video` was an ungated spend path

The plan gated `poll-video-jobs`, which *harvests* finished AI reel videos, but
left `generate-video`, which *submits* them, running. That is the worst possible
split: jobs would be submitted and **billed by the provider**, then never
collected, leaving `video_jobs` rows pending forever.

Found by the code-quality reviewer. Fixed by applying the same gate to
`generate-video` (commit `c39dc6c`). The gate sits before the `video_jobs`
insert, verified live — invoking the deployed function left the row count
unchanged at 0.

**Lesson for later phases: gate the paying half of a pipeline before, or at the
same time as, the consuming half.** Turning off the consumer alone converts a
working system into a silent money leak.

### 2. The GHL guard did not cover its main caller

The plan asserted that guarding `engine/ghl_social.py::create_social_post` would
cover every caller, and used that to justify deviating from the spec's
"make `publish_to_ghl.py` dry-run-only".

**That assertion was wrong.** `scripts/publish_to_ghl.py` never imports
`create_social_post`. It has its own `ghl_post()` → `session.post()` write path,
reached via `schedule_draft()`, and remained fully able to stage draft posts.

Found by the implementing agent, verified directly, fixed in a follow-up commit:
`_reject_if_retired` was made public as `reject_if_retired` and is now called
from `publish_to_ghl.main()` immediately after arg parsing, before any token read
or network call. `--dry-run` still works. Four new tests in
`tests/test_publish_to_ghl.py` pin the behaviour, including that the guard fires
*before* the pre-existing missing-token `sys.exit` — otherwise an operator with
no token would see "token not set" and conclude the script still worked once
configured.

**Lesson: "guarding the writer covers every caller" is a claim to verify, not
assume.** A grep for importers would have caught this at planning time.

### Production verification performed

- Crons 103 and 106 disabled, then confirmed silent for 33 minutes — 6 missed
  5-minute intervals and 16 missed 2-minute intervals respectively.
- Cron 63 `call-intake-process-5min` confirmed still firing on schedule, proving
  the change did not touch inbound call lead capture.
- `MARKETING_PUBLISHING_ENABLED` confirmed unset before deploy, so the gate
  deployed closed rather than accidentally open.
- All three gated functions deployed and invoked with the exact headers the real
  cron sends; each returned `200 {ok:false, disabled:true}` with its own correct
  `fn` name.
- Table row counts unchanged before and after: `content_items` 0,
  `content_performance` 0, `video_jobs` 0, `spend_recommendations` 6.

Note: the three content tables were **already empty** before teardown. The
scheduler shipped in July 2026 and never held a single item, independently
corroborating that it never published anything.

## Not in this plan

The GHL Social Planner UI stays usable by hand — that is the deliberate stopgap until Phase 3. Tombstoning `sync-google-ads` (the `marketing_spend` double-write hazard) is recorded in the spec as an owner decision and is not Phase 0 work.
