# Auto-Detect Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the tech-parts auto-detect feature populate parts/commission visibly at the scorecard level instead of requiring drill-into-each-job.

**Architecture:** Two trigger points wrapping a single bulk hook. (A) `CommissionTracker` fires detection on visible draft-with-zero-parts jobs when its data settles. (B) `Run.tsx` fires detection on the just-imported draft jobs after `merge_payroll_jobs` resolves. Both call the same `useApplyPartSuggestionsBulk` hook which Promise.allSettled the Edge Function calls, dedups within a 30s window, and invalidates scorecard query keys when complete.

**Tech Stack:** React + TypeScript + TanStack Query, Vitest + Testing Library. No backend changes — sits on top of the already-deployed `apply-part-suggestions` Edge Function.

---

## Repo Context

- **Repo root:** `/Users/daniel/twins-dashboard/twins-dash`. Worktrees live inside the inner repo at `.worktrees/<feature-name>`.
- **Branch from:** `origin/main` at sha `b960f58` or later (PR #40 already merged — that ships the Edge Function and the per-job trigger this plan extends).
- **Spec:** `docs/superpowers/specs/2026-04-29-auto-detect-visibility-design.md`
- **No SQL or Edge Function changes.** Pure frontend.

## File Structure

| File | Action | Purpose |
|---|---|---|
| `src/hooks/tech/useApplyPartSuggestionsBulk.ts` | Create | Bulk wrapper around `apply-part-suggestions` Edge Function: takes `number[]` of job IDs, dedups via 30s in-memory window, fires Promise.allSettled, invalidates scorecard query keys |
| `src/hooks/tech/__tests__/useApplyPartSuggestionsBulk.test.tsx` | Create | 4 unit tests: empty input, success path, dedup window suppression, partial-failure isolation |
| `src/components/technician/scorecard/CommissionTracker.tsx` | Modify | In `TrackerView`, add a `useEffect` that scans `data.jobs` for `payroll_id != null && submission_status === 'draft' && parts_cost === 0`, fires bulk detection on those IDs |
| `src/pages/payroll/Run.tsx` | Modify | After `merge_payroll_jobs` returns and `setJobs` is called, fire bulk detection on jobs with `submission_status === 'draft'`. Surface progress in the existing `syncing` state |

---

## M1 — Worktree + branch

### Task 0: Worktree + branch

**Files:** none

- [ ] **Step 1: Create worktree from main**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git fetch origin
git worktree add .worktrees/auto-detect-visibility -b feat/auto-detect-visibility origin/main
cd .worktrees/auto-detect-visibility
```

Expected: a new directory `.worktrees/auto-detect-visibility` containing a fresh checkout on branch `feat/auto-detect-visibility`. HEAD at `b960f58` (PR #40 merge) or later.

- [ ] **Step 2: Sanity check**

```bash
git status
git log --oneline -3
ls src/hooks/tech/useApplyPartSuggestions.ts
```

Expected: clean working tree on `feat/auto-detect-visibility`. The existing `useApplyPartSuggestions.ts` (per-job hook from PR #40) must exist — this plan extends it.

---

## M2 — Bulk hook

### Task 1: useApplyPartSuggestionsBulk hook + tests

**Files:**
- Create: `src/hooks/tech/useApplyPartSuggestionsBulk.ts`
- Create: `src/hooks/tech/__tests__/useApplyPartSuggestionsBulk.test.tsx`

TDD: tests first, fail, then implement, pass.

- [ ] **Step 1: Write the failing tests**

```bash
mkdir -p src/hooks/tech/__tests__
cat > src/hooks/tech/__tests__/useApplyPartSuggestionsBulk.test.tsx <<'EOF'
import { describe, it, expect, vi, beforeEach } from "vitest";
import { renderHook, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ReactNode } from "react";
import {
  useApplyPartSuggestionsBulk,
  __resetDedupForTests,
} from "../useApplyPartSuggestionsBulk";

const invokeMock = vi.fn();
vi.mock("@/integrations/supabase/client", () => ({
  supabase: {
    functions: { invoke: (...args: any[]) => invokeMock(...args) },
  },
}));

function makeWrapper() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  );
}

beforeEach(() => {
  invokeMock.mockReset();
  __resetDedupForTests();
});

describe("useApplyPartSuggestionsBulk", () => {
  it("no-ops on empty input array", async () => {
    const { result } = renderHook(() => useApplyPartSuggestionsBulk(), {
      wrapper: makeWrapper(),
    });
    const r = await result.current.mutateAsync([]);
    expect(r.fired).toBe(0);
    expect(r.skipped).toBe(0);
    expect(r.errors).toEqual([]);
    expect(invokeMock).not.toHaveBeenCalled();
  });

  it("fires one Edge Function call per jobId on first invocation", async () => {
    invokeMock.mockResolvedValue({ data: { applied: [], suggested: [], unmatched: [] }, error: null });
    const { result } = renderHook(() => useApplyPartSuggestionsBulk(), {
      wrapper: makeWrapper(),
    });
    const r = await result.current.mutateAsync([10, 11, 12]);
    expect(r.fired).toBe(3);
    expect(r.skipped).toBe(0);
    expect(r.errors).toEqual([]);
    expect(invokeMock).toHaveBeenCalledTimes(3);
    expect(invokeMock).toHaveBeenCalledWith("apply-part-suggestions", { body: { job_id: 10 } });
    expect(invokeMock).toHaveBeenCalledWith("apply-part-suggestions", { body: { job_id: 11 } });
    expect(invokeMock).toHaveBeenCalledWith("apply-part-suggestions", { body: { job_id: 12 } });
  });

  it("skips jobs fired within the dedup window", async () => {
    invokeMock.mockResolvedValue({ data: { applied: [], suggested: [], unmatched: [] }, error: null });
    const { result } = renderHook(() => useApplyPartSuggestionsBulk(), {
      wrapper: makeWrapper(),
    });
    await result.current.mutateAsync([10, 11]);
    expect(invokeMock).toHaveBeenCalledTimes(2);

    invokeMock.mockClear();
    const r = await result.current.mutateAsync([10, 11, 12]);
    expect(r.fired).toBe(1); // only 12 was new
    expect(r.skipped).toBe(2); // 10 and 11 were within dedup window
    expect(invokeMock).toHaveBeenCalledTimes(1);
    expect(invokeMock).toHaveBeenCalledWith("apply-part-suggestions", { body: { job_id: 12 } });
  });

  it("isolates errors — one failing job does not fail the batch", async () => {
    invokeMock
      .mockResolvedValueOnce({ data: null, error: { message: "boom" } })
      .mockResolvedValueOnce({ data: { applied: [], suggested: [], unmatched: [] }, error: null })
      .mockResolvedValueOnce({ data: { applied: [], suggested: [], unmatched: [] }, error: null });
    const { result } = renderHook(() => useApplyPartSuggestionsBulk(), {
      wrapper: makeWrapper(),
    });
    const r = await result.current.mutateAsync([10, 11, 12]);
    expect(r.fired).toBe(3);
    expect(r.skipped).toBe(0);
    expect(r.errors.length).toBe(1);
    expect(r.errors[0]).toMatchObject({ jobId: 10, message: expect.stringContaining("boom") });
  });
});
EOF
```

- [ ] **Step 2: Run tests — verify failure**

```bash
npx vitest run src/hooks/tech/__tests__/useApplyPartSuggestionsBulk.test.tsx
```

Expected: 4 failures with `Failed to resolve import "../useApplyPartSuggestionsBulk"`.

- [ ] **Step 3: Write the hook**

```bash
cat > src/hooks/tech/useApplyPartSuggestionsBulk.ts <<'EOF'
// src/hooks/tech/useApplyPartSuggestionsBulk.ts
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";

const DEDUP_WINDOW_MS = 30_000;

// Module-level dedup map. Persists across hook instances so a date-range toggle
// or a remount within 30s of a previous fire doesn't re-invoke the Edge Function
// for the same job. The Edge Function is already idempotent at the DB level —
// this is purely network/cost optimization.
const lastFiredAt = new Map<number, number>();

export function __resetDedupForTests(): void {
  lastFiredAt.clear();
}

export type BulkResult = {
  fired: number;
  skipped: number;
  errors: Array<{ jobId: number; message: string }>;
};

/**
 * Bulk-invoke the apply-part-suggestions Edge Function for an array of job IDs.
 * - Empty input array is a no-op.
 * - Each job is fired at most once per DEDUP_WINDOW_MS (30s).
 * - One Edge Function failure does not fail the batch (Promise.allSettled).
 * - On settle, invalidates scorecard query keys so the UI re-renders with new
 *   parts_cost / commission values.
 */
export function useApplyPartSuggestionsBulk() {
  const qc = useQueryClient();
  return useMutation<BulkResult, Error, number[]>({
    mutationFn: async (jobIds): Promise<BulkResult> => {
      if (jobIds.length === 0) return { fired: 0, skipped: 0, errors: [] };

      const now = Date.now();
      const fresh: number[] = [];
      let skipped = 0;
      for (const id of jobIds) {
        const last = lastFiredAt.get(id);
        if (last !== undefined && now - last < DEDUP_WINDOW_MS) {
          skipped++;
        } else {
          fresh.push(id);
          lastFiredAt.set(id, now);
        }
      }

      const settled = await Promise.allSettled(
        fresh.map((id) =>
          supabase.functions.invoke("apply-part-suggestions", { body: { job_id: id } })
            .then((r) => ({ id, r }))
        )
      );

      const errors: Array<{ jobId: number; message: string }> = [];
      for (const s of settled) {
        if (s.status === "rejected") {
          errors.push({ jobId: -1, message: String(s.reason) });
        } else if (s.value.r.error) {
          errors.push({ jobId: s.value.id, message: s.value.r.error.message ?? String(s.value.r.error) });
        }
      }

      return { fired: fresh.length, skipped, errors };
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: ["my_jobs"] });
      qc.invalidateQueries({ queryKey: ["my_scorecard"] });
      qc.invalidateQueries({ queryKey: ["admin_tech_jobs"] });
      qc.invalidateQueries({ queryKey: ["apply_part_suggestions"] });
    },
  });
}
EOF
```

- [ ] **Step 4: Run tests — verify pass**

```bash
npx vitest run src/hooks/tech/__tests__/useApplyPartSuggestionsBulk.test.tsx
```

Expected: 4 passing.

- [ ] **Step 5: TS compile**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add src/hooks/tech/useApplyPartSuggestionsBulk.ts src/hooks/tech/__tests__/useApplyPartSuggestionsBulk.test.tsx
git commit -m "feat(auto-detect-visibility): useApplyPartSuggestionsBulk hook (Promise.allSettled + 30s dedup + 4 unit tests)"
```

---

## M3 — Trigger A: Scorecard auto-fires

### Task 2: Wire scorecard auto-detection in CommissionTracker

**Files:**
- Modify: `src/components/technician/scorecard/CommissionTracker.tsx`

The scorecard's `TrackerView` is shared between `AdminTracker` (admin viewing a tech) and `TechTracker` (tech viewing self). Adding the effect there means both modes get auto-detection without code duplication.

- [ ] **Step 1: Add the import**

In `src/components/technician/scorecard/CommissionTracker.tsx`, find the existing imports near the top (lines 1-11). Add immediately after the last `@/hooks/...` import (around line 5):

```tsx
import { useApplyPartSuggestionsBulk } from '@/hooks/tech/useApplyPartSuggestionsBulk';
```

- [ ] **Step 2: Add the effect inside TrackerView**

Find the `TrackerView` function (starts around line 78). After the `const overridePct = ...` line (where the existing `data` destructuring ends, ~line 84), add the auto-detect effect. Use Edit to insert it BEFORE the `return (` statement.

The effect reads `jobs` (already in scope from `data?.jobs ?? []`), filters to draft jobs in payroll with zero parts, fires bulk detection on their `payroll_id`s.

```tsx
  // Auto-detect parts on visible draft jobs that haven't been processed yet.
  // Idempotent at both the dedup-window level (here) and the DB level (Edge Function).
  // Fires in the background; does NOT block the render.
  const bulkDetect = useApplyPartSuggestionsBulk();
  useEffect(() => {
    if (!jobs || jobs.length === 0) return;
    const candidates = jobs
      .filter((j: any) =>
        j.payroll_id != null
        && j.submission_status === 'draft'
        && Number(j.parts_cost ?? 0) === 0
      )
      .map((j: any) => Number(j.payroll_id));
    if (candidates.length === 0) return;
    bulkDetect.mutate(candidates);
    // bulkDetect identity is stable from useMutation — exclude from deps to
    // avoid an effect loop. Only re-fire when the list of candidate IDs
    // actually changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [jobs?.map((j: any) => `${j.payroll_id}:${j.parts_cost}`).join('|')]);
```

The dependency string fingerprints the candidate set: changes to `payroll_id` or `parts_cost` re-trigger; merely re-rendering the same data does not.

- [ ] **Step 3: Verify TS compile**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 4: Commit**

```bash
git add src/components/technician/scorecard/CommissionTracker.tsx
git commit -m "feat(auto-detect-visibility): TrackerView fires bulk detection on visible draft-with-zero-parts jobs"
```

---

## M4 — Trigger B: Run Payroll import auto-fires

### Task 3: Wire post-merge auto-detection in Run.tsx

**Files:**
- Modify: `src/pages/payroll/Run.tsx`

The Run page's sync handler calls `merge_payroll_jobs`, then re-reads `payroll_jobs` for the run, then re-reads `payroll_job_parts`. After those settle, fire bulk detection on the run's draft jobs.

- [ ] **Step 1: Add the import**

Find the existing imports at the top of `src/pages/payroll/Run.tsx`. After the last `@/hooks/...` import (or after `import { extractPartMentions } from "@/lib/payroll/partsMatcher";`), add:

```tsx
import { useApplyPartSuggestionsBulk } from "@/hooks/tech/useApplyPartSuggestionsBulk";
```

- [ ] **Step 2: Hook the bulk mutation in the component body**

Find where the existing hooks are wired (around the `const [syncing, setSyncing] = useState(false)` line, ~line 132). Add immediately after the existing useState block, before the sync handler is defined:

```tsx
  const bulkDetect = useApplyPartSuggestionsBulk();
```

- [ ] **Step 3: Fire detection after the merge succeeds**

Find the section in the sync handler around line 314-325 — specifically the block that does:

```tsx
      const { data: jobsData } = await supabase
        .from("payroll_jobs").select("*").eq("run_id", runId).order("job_date");
      setJobs((jobsData as DBJob[]) ?? []);

      const ids = (jobsData ?? []).map((j: DBJob) => j.id);
      if (ids.length) {
        const { data: jp } = await supabase.from("payroll_job_parts").select("*").in("job_id", ids);
        setJobParts((jp as DBJobPart[]) ?? []);
      } else setJobParts([]);
```

Immediately AFTER the `setJobParts(...)` call (and before the existing `const d = (delta ?? {})` line that builds the toast text), insert the bulk detection trigger:

```tsx
      // Trigger B: auto-detect parts on every draft job in this run. The Edge
      // Function is idempotent at the DB level; the bulk hook also dedups at
      // the network level. Fires in the background — does not block the toast.
      const draftIds = (jobsData ?? [])
        .filter((j: DBJob) => (j as any).submission_status === 'draft')
        .map((j: DBJob) => j.id);
      if (draftIds.length > 0) {
        bulkDetect.mutate(draftIds);
      }
```

- [ ] **Step 4: Verify TS compile**

```bash
npx tsc --noEmit
```

Expected: clean. (`submission_status` is on `DBJob` even though we cast through `any` defensively because the local type alias may not list every column.)

- [ ] **Step 5: Commit**

```bash
git add src/pages/payroll/Run.tsx
git commit -m "feat(auto-detect-visibility): Run.tsx fires bulk detection on all draft jobs after merge_payroll_jobs"
```

---

## M5 — Final verification + PR

### Task 4: Final tsc + tests + build + push + PR

**Files:** none

- [ ] **Step 1: Run all tests**

```bash
npx vitest run 2>&1 | tail -10
```

Expected: 197 + 4 = 201 passing (was 197 with PartsGuard + previous; +4 from this branch's bulk hook tests). 8 pre-existing Deno-import failures unrelated.

- [ ] **Step 2: Final tsc**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 3: Final build**

```bash
npm run build 2>&1 | tail -5
```

Expected: `✓ built in <N>s`. Chunk-size warnings pre-existing.

- [ ] **Step 4: Push the branch**

```bash
git push -u origin feat/auto-detect-visibility
```

- [ ] **Step 5: Open PR via GitHub API**

```bash
TOKEN=$(printf "protocol=https\nhost=github.com\n" | git credential-osxkeychain get | grep '^password=' | cut -d= -f2)
curl -s -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.github+json" \
  -d "$(cat <<'EOF'
{
  "title": "feat(auto-detect-visibility): scorecard + Run Payroll import fire auto-detect (no manual drill-into-each-job)",
  "head": "feat/auto-detect-visibility",
  "base": "main",
  "body": "Implements `docs/superpowers/specs/2026-04-29-auto-detect-visibility-design.md`.\n\n## Summary\n\nThe tech-parts auto-detect feature (PR #40) only fires when someone opens an individual job's detail page. This PR adds two trigger points so the scorecard reflects auto-detect without manual drill-in:\n\n- **Trigger A (scorecard auto-fires).** When `CommissionTracker` mounts (or its date range changes), scan visible jobs for `payroll_id != null && submission_status === 'draft' && parts_cost === 0` and fire the Edge Function in bulk. Catch-up for historical jobs imported before PR #40 shipped.\n- **Trigger B (Run Payroll import auto-fires).** After `merge_payroll_jobs` resolves in `Run.tsx`, fire detection on every draft job in the run. Forward-path coverage so the scorecard is already populated by the time anyone opens it.\n\nBoth triggers call the same `useApplyPartSuggestionsBulk` hook which Promise.allSettles the Edge Function calls, dedups within a 30s window, and invalidates `my_jobs` / `my_scorecard` / `admin_tech_jobs` query keys when complete.\n\n## Behavior preserved\n- Edge Function is idempotent (name-based dedup + soft-delete check) — re-firing is safe.\n- Tech still never sees prices.\n- Soft-deleted parts (`removed_by_tech = true`) are NOT re-added.\n- No blocking spinners — auto-detect runs in background.\n- Existing per-job trigger in `TechJobDetail.tsx` unchanged.\n\n## Files\n- New: `src/hooks/tech/useApplyPartSuggestionsBulk.ts` (bulk wrapper hook)\n- New: `src/hooks/tech/__tests__/useApplyPartSuggestionsBulk.test.tsx` (4 unit tests: empty input, success path, dedup window, error isolation)\n- Modified: `src/components/technician/scorecard/CommissionTracker.tsx` (effect in `TrackerView`)\n- Modified: `src/pages/payroll/Run.tsx` (post-merge call)\n\nNo SQL changes. No Edge Function changes.\n\n## Test plan\n- [x] Unit tests: 4 new bulk-hook tests passing\n- [x] tsc + vite build clean\n- [x] vitest run: full suite passing (8 pre-existing Deno-import failures unrelated)\n- [ ] Manual smoke (Vercel preview): open `/tech?as=<charles-uuid>`, watch network tab, verify N Edge Function calls fire, table re-renders with non-zero PARTS values within 1-2s. Then open `/payroll/run`, sync the current week, watch the same auto-population happen for new draft jobs.\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"
}
EOF
)" | python3 -c "import sys,json; d=json.load(sys.stdin); print('PR URL:', d.get('html_url','ERR')); print('Number:', d.get('number','ERR'))"
```

Expected: returns PR URL + number.

---

## Self-Review

**Spec coverage:**

- ✅ Bulk wrapper hook with dedup + invalidation → Task 1
- ✅ Trigger A (scorecard auto-fires when visible draft jobs with zero parts exist) → Task 2
- ✅ Trigger B (Run.tsx fires after merge_payroll_jobs) → Task 3
- ✅ Edge Function idempotency relied upon (no SQL changes) → covered in Task 1 hook code + comments
- ✅ Tech never sees prices (no UI cell change) → covered by absence of edits to render code
- ✅ Existing per-job trigger preserved → not touched
- ✅ Performance bounds documented and accepted → spec has the analysis
- ✅ 4 unit tests cover empty / success / dedup / partial-failure → Task 1 step 1
- ✅ Reversibility: gate the effect, remove the post-merge call → covered by Files structure (3 isolated changes)

**Placeholder scan:** None.

**Type consistency:** `useApplyPartSuggestionsBulk` shared between Task 1 (definition) and Tasks 2/3 (consumers). `BulkResult` shape (`fired`, `skipped`, `errors`) used identically in tests and assertions.
