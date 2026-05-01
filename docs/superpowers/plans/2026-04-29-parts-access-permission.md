# Parts-Access Permission Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Carve `/payroll/parts` and `/payroll/parts/count` out of the umbrella `payroll_access` flag so they can be granted independently via a new `parts_access` flag, without exposing the rest of the payroll workflow.

**Architecture:** Single SQL migration introduces `has_parts_access(uid)` (admin OR `payroll_access` OR `parts_access`) and widens RLS on 4 tables (price sheet + inventory count + voice aliases). New `<PartsGuard>` component mirrors `<PayrollGuard>` but accepts the wider permission set; swapped in on the 2 parts routes only. New flag added to the `/admin → Users & Access` toggle list. Dead duplicate UI file deleted. Backfill ensures every user with `payroll_access=true` today gets `parts_access=true` so toggles render the correct ON state on day one.

**Tech Stack:** Supabase Postgres (RLS + SECURITY DEFINER functions), React + TypeScript, Vitest + Testing Library, react-router v6.

---

## Repo Context

- **Repo root:** `/Users/daniel/twins-dashboard/twins-dash` (this is the inner repo — worktrees live inside it, not in the parent `/Users/daniel/twins-dashboard`).
- **Supabase project (prod, linked):** `jwrpjuqaynownxaoeayi` (twinsdash.com).
- **Migration history is desync'd** — `npx supabase db push` may fail with "must include all migrations" complaints about pre-existing migrations. If so, fall back to `npx supabase db query --linked -f <file>` to apply the new migration directly, then manually `INSERT INTO supabase_migrations.schema_migrations (version, name) VALUES (...)` to record the version. Pattern was used successfully in the previous tech-parts feature.
- **Existing `payroll_access` users (likely 1-2):** these MUST keep parts access after this ships. The migration's backfill handles this; verify in the post-apply step.

## File Structure

| File | Action | Purpose |
|---|---|---|
| `supabase/migrations/20260429120000_parts_access_permission.sql` | Create | SQL function `has_parts_access` + 4 RLS policy swaps + backfill UPDATE |
| `src/components/payroll/PartsGuard.tsx` | Create | Route guard accepting admin OR `payroll_access` OR `parts_access` |
| `src/components/payroll/__tests__/PartsGuard.test.tsx` | Create | 4 unit tests covering the permission matrix |
| `src/App.tsx` | Modify | Swap `<PayrollGuard>` → `<PartsGuard>` on `/payroll/parts` and `/payroll/parts/count` only |
| `src/components/admin/AdminUserManagement.tsx` | Modify | Add `{ key: 'parts_access', label: 'Parts & Inventory' }` to `PAGE_PERMISSIONS` |
| `src/components/admin/AdminPermissions.tsx` | Delete | Dead code (defined but not imported anywhere) |

---

## M1 — Worktree + Branch

### Task 0: Worktree + branch

**Files:** none

- [ ] **Step 1: Create worktree from main**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git fetch origin
git worktree add .worktrees/parts-access-permission -b feat/parts-access-permission origin/main
cd .worktrees/parts-access-permission
```

Expected: a new directory `.worktrees/parts-access-permission` containing a fresh checkout on branch `feat/parts-access-permission`. Subsequent steps run from this worktree.

- [ ] **Step 2: Sanity check**

```bash
git status
git log --oneline -3
```

Expected: clean working tree, branch `feat/parts-access-permission`, HEAD at the latest main commit (which should include the merged tech-parts auto-detect feature, sha `b960f58…`).

---

## M2 — Database migration

### Task 1: Write the migration

**Files:**
- Create: `supabase/migrations/20260429120000_parts_access_permission.sql`

The migration is a single file with three sections: function, RLS updates, backfill.

- [ ] **Step 1: Create the migration file**

```bash
cat > supabase/migrations/20260429120000_parts_access_permission.sql <<'EOF'
-- Parts-access permission: lets admin grant /payroll/parts + /payroll/parts/count
-- access independently of the umbrella payroll_access flag. Existing users with
-- payroll_access keep parts access via the OR clause in has_parts_access.
--
-- Reversibility: drop has_parts_access function, restore the four RLS policies
-- to call has_payroll_access. The backfilled JSONB key is harmless if left.

-- 1. has_parts_access(uid) — admin OR payroll_access OR parts_access.
CREATE OR REPLACE FUNCTION public.has_parts_access(uid uuid)
RETURNS boolean
LANGUAGE sql
SECURITY DEFINER
STABLE
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1 FROM public.user_roles
    WHERE user_id = uid
      AND (
        role = 'admin'
        OR (permissions ->> 'payroll_access')::boolean = TRUE
        OR (permissions ->> 'parts_access')::boolean = TRUE
      )
  );
$$;

GRANT EXECUTE ON FUNCTION public.has_parts_access(uuid)
  TO authenticated, service_role;

-- 2. RLS policy widens.
--
-- payroll_parts_prices — price sheet. Single FOR ALL policy named
-- "payroll access" was created by 20260418100001_payroll_rbac.sql.
DROP POLICY IF EXISTS "payroll access" ON public.payroll_parts_prices;
CREATE POLICY "parts access" ON public.payroll_parts_prices
  FOR ALL TO authenticated
  USING (public.has_parts_access(auth.uid()))
  WITH CHECK (public.has_parts_access(auth.uid()));

-- inventory_count_sessions — also accepts field_supervisor (unchanged).
DROP POLICY IF EXISTS "payroll_access full count_sessions" ON public.inventory_count_sessions;
CREATE POLICY "parts_access full count_sessions" ON public.inventory_count_sessions
  FOR ALL TO authenticated
  USING (
    public.has_parts_access(auth.uid())
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  )
  WITH CHECK (
    public.has_parts_access(auth.uid())
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  );

-- inventory_count_lines — also accepts field_supervisor (unchanged).
DROP POLICY IF EXISTS "payroll_access full count_lines" ON public.inventory_count_lines;
CREATE POLICY "parts_access full count_lines" ON public.inventory_count_lines
  FOR ALL TO authenticated
  USING (
    public.has_parts_access(auth.uid())
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  )
  WITH CHECK (
    public.has_parts_access(auth.uid())
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  );

-- parts_voice_aliases — only the WRITE policy widens.
-- Read policy ("authenticated read aliases") stays open to all authenticated.
DROP POLICY IF EXISTS "payroll_access write aliases" ON public.parts_voice_aliases;
CREATE POLICY "parts_access write aliases" ON public.parts_voice_aliases
  FOR ALL TO authenticated
  USING (public.has_parts_access(auth.uid()))
  WITH CHECK (public.has_parts_access(auth.uid()));

-- 3. Backfill: every user who has payroll_access=true today gets parts_access=true
-- explicitly. Cosmetic — has_parts_access already accepts payroll_access — but
-- this makes the toggle in /admin → Users & Access render the correct ON state
-- on day one for these users.
UPDATE public.user_roles
SET permissions = coalesce(permissions, '{}'::jsonb)
                  || jsonb_build_object('parts_access', true),
    updated_at = NOW()
WHERE (permissions ->> 'payroll_access')::boolean = TRUE
  AND (permissions ->> 'parts_access') IS NULL;
EOF
```

- [ ] **Step 2: Verify the file**

```bash
wc -l supabase/migrations/20260429120000_parts_access_permission.sql
head -5 supabase/migrations/20260429120000_parts_access_permission.sql
```

Expected: ~75 lines; first line starts with `-- Parts-access permission`.

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260429120000_parts_access_permission.sql
git commit -m "feat(parts-access): SQL migration — has_parts_access function + RLS widening + backfill"
```

---

### Task 2: Apply the migration to the linked Supabase project

**Files:** none (executes against live DB)

- [ ] **Step 1: Try the standard push**

```bash
npx supabase db push --linked
```

If this prints a single line confirming the new migration was applied and exits 0, skip Step 2 and proceed to Step 3.

- [ ] **Step 2: Fallback if `db push` complains about prior migration desync**

The error message will look like:
```
Remote database is up to date.
Run `supabase db push --include-all` to apply all migrations.
```

Or it will reference specific older versions. Recover by applying the new migration directly:

```bash
npx supabase db query --linked -f supabase/migrations/20260429120000_parts_access_permission.sql
```

Then record it in the migration history so future pushes are clean:

```bash
npx supabase db query --linked "INSERT INTO supabase_migrations.schema_migrations (version, name) VALUES ('20260429120000', 'parts_access_permission') ON CONFLICT DO NOTHING;"
```

- [ ] **Step 3: Verify the function exists**

```bash
npx supabase db query --linked "SELECT proname FROM pg_proc WHERE proname = 'has_parts_access';"
```

Expected: returns one row with `proname = has_parts_access`.

- [ ] **Step 4: Verify the RLS policies were swapped**

```bash
npx supabase db query --linked "SELECT polname FROM pg_policy WHERE polrelid::regclass::text IN ('payroll_parts_prices', 'inventory_count_sessions', 'inventory_count_lines', 'parts_voice_aliases') ORDER BY polrelid::regclass::text, polname;"
```

Expected output includes:
- `parts access` on `payroll_parts_prices`
- `parts_access full count_lines` on `inventory_count_lines`
- `parts_access full count_sessions` on `inventory_count_sessions`
- `authenticated read aliases` AND `parts_access write aliases` on `parts_voice_aliases`
- NO rows starting with `payroll_access` or `payroll access` on those tables.

- [ ] **Step 5: Verify backfill worked**

```bash
npx supabase db query --linked "SELECT user_id, role, permissions->>'payroll_access' AS payroll, permissions->>'parts_access' AS parts FROM public.user_roles WHERE (permissions->>'payroll_access')::boolean = TRUE OR (permissions->>'parts_access')::boolean = TRUE;"
```

Expected: every row with `payroll = t` also has `parts = t`. (Admin users may not appear here because admins typically don't store explicit permissions.)

- [ ] **Step 6: Spot-check `has_parts_access` returns true for an existing payroll user**

```bash
npx supabase db query --linked "SELECT public.has_parts_access(user_id) AS ok, user_id, role FROM public.user_roles WHERE (permissions->>'payroll_access')::boolean = TRUE LIMIT 3;"
```

Expected: every row has `ok = t`.

---

## M3 — Frontend route guard

### Task 3: PartsGuard component + tests

**Files:**
- Create: `src/components/payroll/PartsGuard.tsx`
- Create: `src/components/payroll/__tests__/PartsGuard.test.tsx`

- [ ] **Step 1: Write the failing tests**

```bash
cat > src/components/payroll/__tests__/PartsGuard.test.tsx <<'EOF'
import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { PartsGuard } from "../PartsGuard";

vi.mock("@/contexts/AuthContext", () => ({
  useAuth: vi.fn(),
}));
vi.mock("@/hooks/use-toast", () => ({
  useToast: () => ({ toast: vi.fn() }),
}));
import { useAuth } from "@/contexts/AuthContext";

function setAuth(isAdmin: boolean, perms: Record<string, unknown> | null) {
  (useAuth as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
    isAdmin,
    userRole: { permissions: perms },
    isLoading: false,
  });
}

describe("PartsGuard", () => {
  it("renders children when user is admin", () => {
    setAuth(true, null);
    render(
      <MemoryRouter>
        <PartsGuard><div>PARTS CONTENT</div></PartsGuard>
      </MemoryRouter>,
    );
    expect(screen.getByText("PARTS CONTENT")).toBeInTheDocument();
  });

  it("renders children when user has payroll_access permission (backward compat)", () => {
    setAuth(false, { payroll_access: true });
    render(
      <MemoryRouter>
        <PartsGuard><div>PARTS CONTENT</div></PartsGuard>
      </MemoryRouter>,
    );
    expect(screen.getByText("PARTS CONTENT")).toBeInTheDocument();
  });

  it("renders children when user has parts_access permission only", () => {
    setAuth(false, { parts_access: true });
    render(
      <MemoryRouter>
        <PartsGuard><div>PARTS CONTENT</div></PartsGuard>
      </MemoryRouter>,
    );
    expect(screen.getByText("PARTS CONTENT")).toBeInTheDocument();
  });

  it("does NOT render children when user has neither flag", () => {
    setAuth(false, { payroll_access: false, parts_access: false });
    render(
      <MemoryRouter>
        <PartsGuard><div>PARTS CONTENT</div></PartsGuard>
      </MemoryRouter>,
    );
    expect(screen.queryByText("PARTS CONTENT")).not.toBeInTheDocument();
  });
});
EOF
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
npx vitest run src/components/payroll/__tests__/PartsGuard.test.tsx
```

Expected: 4 failures with "Cannot find module '../PartsGuard'" or similar import error.

- [ ] **Step 3: Write the PartsGuard component**

```bash
cat > src/components/payroll/PartsGuard.tsx <<'EOF'
import { ReactNode, useEffect } from "react";
import { Navigate } from "react-router-dom";
import { useAuth } from "@/contexts/AuthContext";
import { useToast } from "@/hooks/use-toast";

/**
 * Route guard for the Parts Library + Inventory Count pages.
 *
 * Admits any of:
 *  - role === 'admin' (always)
 *  - permissions.payroll_access === true (backward compat — full payroll users
 *    keep parts access without an explicit parts_access flag)
 *  - permissions.parts_access === true (new — granted independently)
 *
 * Mirrors PayrollGuard's redirect-and-toast UX on denial.
 */
export function PartsGuard({ children }: { children: ReactNode }) {
  const { isAdmin, userRole, isLoading: loading } = useAuth();
  const { toast } = useToast();

  const perms = userRole?.permissions as Record<string, unknown> | null | undefined;
  const hasAccess =
    isAdmin
    || Boolean(perms?.payroll_access)
    || Boolean(perms?.parts_access);

  useEffect(() => {
    if (!loading && !hasAccess) {
      toast({
        title: "Access denied",
        description: "You don't have access to Parts & Inventory.",
        variant: "destructive",
      });
    }
  }, [loading, hasAccess, toast]);

  if (loading) return null;
  if (!hasAccess) return <Navigate to="/" replace />;
  return <>{children}</>;
}
EOF
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
npx vitest run src/components/payroll/__tests__/PartsGuard.test.tsx
```

Expected: 4 passing.

- [ ] **Step 5: Commit**

```bash
git add src/components/payroll/PartsGuard.tsx src/components/payroll/__tests__/PartsGuard.test.tsx
git commit -m "feat(parts-access): PartsGuard component + 4 unit tests"
```

---

### Task 4: Wire PartsGuard into App.tsx

**Files:**
- Modify: `src/App.tsx` (add import line ~13, swap guards on lines ~134 and ~146)

- [ ] **Step 1: Add the PartsGuard import**

Open `src/App.tsx` and find this line near the top imports:

```tsx
import { PayrollGuard } from "@/components/payroll/PayrollGuard";
```

Add immediately below it:

```tsx
import { PartsGuard } from "@/components/payroll/PartsGuard";
```

- [ ] **Step 2: Swap the guard on `/payroll/parts`**

Find the route block for `path="/payroll/parts"` (around line 128-139). Replace ONLY the inner guard line.

Before:
```tsx
                      <PayrollGuard><PayrollParts /></PayrollGuard>
```

After:
```tsx
                      <PartsGuard><PayrollParts /></PartsGuard>
```

- [ ] **Step 3: Swap the guard on `/payroll/parts/count`**

Find the route block for `path="/payroll/parts/count"` (around line 140-151). Replace ONLY the inner guard line.

Before:
```tsx
                      <PayrollGuard><PayrollPartsCount /></PayrollGuard>
```

After:
```tsx
                      <PartsGuard><PayrollPartsCount /></PartsGuard>
```

DO NOT touch the other PayrollGuard usages on `/payroll`, `/payroll/run`, `/payroll/techs`, `/payroll/history`, `/payroll/history/:id` — those stay on PayrollGuard.

- [ ] **Step 4: Verify the swap**

```bash
grep -n "PartsGuard\|PayrollGuard" src/App.tsx
```

Expected:
- 1 line: `import { PartsGuard } from ...`
- 1 line: `import { PayrollGuard } from ...`
- 2 lines: `<PartsGuard>...</PartsGuard>` (parts + parts/count)
- 5 lines: `<PayrollGuard>...</PayrollGuard>` (home + run + techs + history + history/:id)

- [ ] **Step 5: TS compile check**

```bash
npx tsc --noEmit
```

Expected: clean (no output).

- [ ] **Step 6: Commit**

```bash
git add src/App.tsx
git commit -m "feat(parts-access): swap PayrollGuard -> PartsGuard on /payroll/parts and /payroll/parts/count"
```

---

## M4 — Admin UI flag list

### Task 5: Add `parts_access` to the toggle list

**Files:**
- Modify: `src/components/admin/AdminUserManagement.tsx` (PAGE_PERMISSIONS array near line 31-41)

- [ ] **Step 1: Add the new entry to PAGE_PERMISSIONS**

Open `src/components/admin/AdminUserManagement.tsx`. Find the `PAGE_PERMISSIONS` const (around lines 31-41).

Before:
```ts
const PAGE_PERMISSIONS = [
  { key: 'view_dashboard', label: 'Dashboard' },
  { key: 'view_leaderboard', label: 'Leaderboard' },
  { key: 'view_team', label: 'Team' },
  { key: 'view_memberships', label: 'Memberships' },
  { key: 'view_marketing', label: 'Marketing ROI' },
  { key: 'view_what_if', label: 'What-If Scenarios' },
  { key: 'view_technician', label: 'Technician Detail' },
  { key: 'payroll_access', label: 'Payroll' },
  { key: 'low_stock_alerts', label: 'Low-stock alerts' },
];
```

After:
```ts
const PAGE_PERMISSIONS = [
  { key: 'view_dashboard', label: 'Dashboard' },
  { key: 'view_leaderboard', label: 'Leaderboard' },
  { key: 'view_team', label: 'Team' },
  { key: 'view_memberships', label: 'Memberships' },
  { key: 'view_marketing', label: 'Marketing ROI' },
  { key: 'view_what_if', label: 'What-If Scenarios' },
  { key: 'view_technician', label: 'Technician Detail' },
  { key: 'payroll_access', label: 'Payroll' },
  { key: 'parts_access', label: 'Parts & Inventory' },
  { key: 'low_stock_alerts', label: 'Low-stock alerts' },
];
```

The new entry is inserted between `payroll_access` and `low_stock_alerts` so the related items group together visually.

- [ ] **Step 2: Verify the change**

```bash
grep -n "parts_access\|Parts & Inventory" src/components/admin/AdminUserManagement.tsx
```

Expected: one match showing the new line with both `parts_access` key and `Parts & Inventory` label.

- [ ] **Step 3: TS compile check**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 4: Commit**

```bash
git add src/components/admin/AdminUserManagement.tsx
git commit -m "feat(parts-access): add parts_access toggle to /admin Users & Access"
```

---

## M5 — Cleanup

### Task 6: Delete dead AdminPermissions.tsx

**Files:**
- Delete: `src/components/admin/AdminPermissions.tsx`

- [ ] **Step 1: Confirm there are no consumers**

```bash
grep -rn "AdminPermissions\b" src/ --include="*.tsx" --include="*.ts" | grep -v "AdminPermissions.tsx:"
```

Expected: no output. (If anything appears, STOP — there's an unexpected import; investigate and update plan before deleting.)

- [ ] **Step 2: Delete the file**

```bash
git rm src/components/admin/AdminPermissions.tsx
```

- [ ] **Step 3: TS compile check**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 4: Commit**

```bash
git commit -m "chore(parts-access): remove dead AdminPermissions.tsx (no consumers, drift risk)"
```

---

## M6 — Final verification + PR

### Task 7: Final tsc + tests + build + push + PR

**Files:** none

- [ ] **Step 1: Run all tests**

```bash
npx vitest run 2>&1 | tail -10
```

Expected: all previously-passing tests still pass + 4 new PartsGuard tests pass. Pre-existing 8 Deno-import failures in `supabase/functions/_shared/forecast/__tests__/*` remain (unrelated). Total: 197 passing (was 193) on a similar number of test files.

- [ ] **Step 2: Final tsc**

```bash
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 3: Final build**

```bash
npm run build 2>&1 | tail -5
```

Expected: `✓ built in <N>s`. Chunk-size warnings are pre-existing and OK.

- [ ] **Step 4: Push the branch**

```bash
git push -u origin feat/parts-access-permission
```

- [ ] **Step 5: Open PR via GitHub API**

```bash
TOKEN=$(printf "protocol=https\nhost=github.com\n" | git credential-osxkeychain get | grep '^password=' | cut -d= -f2)
curl -s -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.github+json" \
  -d "$(cat <<'EOF'
{
  "title": "feat(parts-access): split parts/inventory permission out of payroll_access umbrella",
  "head": "feat/parts-access-permission",
  "base": "main",
  "body": "Implements `docs/superpowers/specs/2026-04-29-parts-access-permission-design.md`.\n\n## Summary\n\nAdds a new `parts_access` permission flag that gates `/payroll/parts` and `/payroll/parts/count` independently of the umbrella `payroll_access` flag, so admin can grant parts/inventory access to a non-admin (office helper, future ops hire) without exposing the full payroll workflow.\n\n## Behavior preserved\n- New SQL function `has_parts_access(uid)` accepts admin OR `payroll_access` OR `parts_access` — every existing user with `payroll_access=true` keeps parts access automatically.\n- One-shot backfill explicitly sets `parts_access=true` for those users so the toggle in `/admin → Users & Access` renders ON state on day one.\n- Other `/payroll/*` routes (Home, Run, Techs, History) stay on `<PayrollGuard>`.\n\n## Schema changes (1 migration, applied to prod)\n- `20260429120000_parts_access_permission.sql` — function + RLS swap on 4 tables (payroll_parts_prices, inventory_count_sessions, inventory_count_lines, parts_voice_aliases-write-only) + JSONB backfill\n\n## Frontend\n- New `<PartsGuard>` component (admin OR payroll_access OR parts_access)\n- Swapped on the 2 parts routes only\n- Added `parts_access` toggle to `/admin → Users & Access`\n- Deleted dead duplicate `AdminPermissions.tsx`\n\n## Test plan\n- [x] Unit tests: 4 new PartsGuard tests (admin / payroll-only / parts-only / neither)\n- [x] tsc + vite build clean\n- [x] vitest run: full suite passing (8 pre-existing Deno-import failures unrelated)\n- [x] SQL: has_parts_access verified for existing payroll users; RLS policies confirmed swapped\n- [ ] Manual smoke (Vercel preview): authorize a fresh email with parts_access only, verify /payroll/parts loads but /payroll/run redirects\n\n🤖 Generated with [Claude Code](https://claude.com/claude-code)"
}
EOF
)" | python3 -c "import sys,json; d=json.load(sys.stdin); print('PR URL:', d.get('html_url','ERR')); print('Number:', d.get('number','ERR'))"
```

Expected: returns the PR URL and number.

---

## Self-Review

**Spec coverage:**

- ✅ New `parts_access` flag → Task 1 (function), Task 5 (UI toggle), Task 3-4 (frontend gate)
- ✅ `has_parts_access(uid)` SQL function with admin OR payroll_access OR parts_access → Task 1 step 1
- ✅ RLS widens on `payroll_parts_prices` → Task 1 step 1, Task 2 step 4 verifies
- ✅ RLS widens on `inventory_count_sessions` + `inventory_count_lines` (preserves field_supervisor branch) → Task 1 step 1, Task 2 step 4 verifies
- ✅ RLS widens on `parts_voice_aliases` write policy only (read stays open) → Task 1 step 1, Task 2 step 4 verifies
- ✅ Backfill: payroll_access=true users get parts_access=true → Task 1 step 1, Task 2 step 5 verifies
- ✅ Other payroll tables (`payroll_runs`, `payroll_jobs`, etc.) untouched → confirmed by listing only the 4 affected tables in Task 1
- ✅ `<PartsGuard>` accepts admin OR payroll_access OR parts_access → Task 3 component code
- ✅ `<PartsGuard>` mirrors PayrollGuard redirect-and-toast UX → Task 3 component code
- ✅ Wire `<PartsGuard>` into the 2 parts routes only → Task 4
- ✅ Add `parts_access` to AdminUserManagement.tsx PAGE_PERMISSIONS → Task 5
- ✅ Delete dead AdminPermissions.tsx → Task 6
- ✅ Reversibility — function drop + RLS restore → covered in migration comment
- ✅ Frontend tests for PartsGuard (4 cases) → Task 3 step 1
- ✅ SQL tests (manual) — Task 2 steps 3-6

**No gaps.** Every spec requirement maps to a task.

**Placeholder scan:** No "TBD" / "TODO" / "fill in details" / "similar to". All step contents are complete.

**Type consistency:** `has_parts_access` (SQL), `PartsGuard` (component), `parts_access` (JSONB key) used consistently across migration, component, tests, and admin UI.
