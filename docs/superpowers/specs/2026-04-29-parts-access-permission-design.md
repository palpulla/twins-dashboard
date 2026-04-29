# Parts-Access Permission + Permission UI Cleanup

**Status:** Draft, awaiting Daniel's approval
**Author:** Claude (with Daniel)
**Date:** 2026-04-29

## Problem

Today, the Parts Library page (`/payroll/parts`) and the inventory Count Session page (`/payroll/parts/count`) both gate on a single permission flag, `payroll_access`. That same flag also opens the entire payroll workflow: Home, Run, Techs, History.

Daniel wants to grant a non-admin user (office helper, family member, future ops hire) access to manage parts and count inventory **without** also handing them the full payroll workflow (which exposes commission math, tech earnings, weekly run lifecycle, etc.).

A secondary issue: the per-user permissions UI under `/admin → Users & Access` has split into two component files (`AdminUserManagement.tsx` and `AdminPermissions.tsx`) that both define a `PAGE_PERMISSIONS` constant. They've drifted apart, and `AdminPermissions.tsx` is dead code (defined but not imported anywhere). This creates confusion when adding new flags and risks future drift.

## Goal

Introduce a single new `parts_access` permission flag covering both the Parts Library and the Count Session, granted independently of `payroll_access`. Existing users with `payroll_access=true` keep parts access automatically (no behavior change for anyone today).

While we're in there, delete the dead `AdminPermissions.tsx` and consolidate the permission flag list to one source of truth.

## Non-Goals

- **No subdivision of `payroll_access` further** (no separate `payroll_run_access`, `payroll_techs_access`, etc.). YAGNI — Daniel has no current use case for granting Run without Techs.
- **No granular admin sub-page permissions** (`/admin/parts`, `/admin/commission`, `/admin/scorecard-tiers`, `/admin/tech-requests` stay role-gated to `admin`). Daniel is the only admin and has no plan to delegate these.
- **No impersonation parity work** (admin acting "as" a tech with full mutation access). Tracked in a follow-up spec.

## Architecture

### Data model

Extend the existing `user_roles.permissions` JSONB pattern. New flag key: `parts_access` (boolean). Stored alongside the existing `payroll_access`, `view_leaderboard`, etc.

### SQL helper function

```sql
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
```

The function intentionally accepts `payroll_access` too — that's the **backward-compat path**: anyone who has full payroll today keeps parts access without any data migration on the permission column.

### RLS impact

The following tables today have RLS policies gated on `has_payroll_access`. They need to widen to also accept `has_parts_access`:

- `payroll_parts_prices` — the price sheet (single FOR ALL policy gated on `has_payroll_access`). Both read and write widen to `has_parts_access`.
- `inventory_count_sessions` — physical count workflow (single FOR ALL policy on `has_payroll_access`). Widens to `has_parts_access`.
- `inventory_count_lines` — per-row count entries (single FOR ALL policy on `has_payroll_access`). Widens to `has_parts_access`.
- `parts_voice_aliases` — voice-input alias mapping for the Count Session. Read policy is already open to any `authenticated` user (no change). The separate write policy gated on `has_payroll_access` widens to `has_parts_access`.

Approach: drop and re-create each policy substituting `has_payroll_access(auth.uid())` with `has_parts_access(auth.uid())`. Other payroll tables (`payroll_runs`, `payroll_jobs`, `payroll_techs`, `payroll_commissions`, `payroll_job_parts`, `payroll_bonus_tiers`) continue to gate on `has_payroll_access` — a parts-only user must NOT see them.

### Frontend route guards

Add a new `<PartsGuard>` component mirroring `<PayrollGuard>`:

```tsx
// src/components/payroll/PartsGuard.tsx
export function PartsGuard({ children }: { children: ReactNode }) {
  const { isAdmin, userRole, isLoading } = useAuth();
  const perms = userRole?.permissions as Record<string, unknown> | null | undefined;
  const hasAccess = isAdmin
    || Boolean(perms?.payroll_access)
    || Boolean(perms?.parts_access);
  // ...same toast + redirect pattern as PayrollGuard
}
```

In `src/App.tsx`, replace `<PayrollGuard>` with `<PartsGuard>` on:

- `/payroll/parts`
- `/payroll/parts/count`

All other `/payroll/*` routes keep `<PayrollGuard>`.

### Permission UI

Single source of truth: `AdminUserManagement.tsx`. Add `parts_access` to its `PAGE_PERMISSIONS` array:

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
  { key: 'parts_access', label: 'Parts & Inventory' },  // NEW
  { key: 'low_stock_alerts', label: 'Low-stock alerts' },
];
```

Delete `src/components/admin/AdminPermissions.tsx` (dead code, no consumer).

### Migration & data backfill

A single SQL migration (`<timestamp>_parts_access_permission.sql`):

1. Creates `has_parts_access(uid)` function
2. Updates RLS policies on `payroll_parts_prices`, `inventory_count_sessions`, `inventory_count_lines`, `parts_voice_aliases` to use the new function
3. Backfills existing users:

```sql
UPDATE public.user_roles
SET permissions = coalesce(permissions, '{}'::jsonb)
                  || jsonb_build_object('parts_access', true)
WHERE (permissions ->> 'payroll_access')::boolean = TRUE
  AND (permissions ->> 'parts_access') IS NULL;
```

This is purely cosmetic since `has_parts_access` already accepts `payroll_access` — but having the explicit flag set means the toggle in `/admin → Users & Access` shows the correct ON state for these users on day one.

### Reversibility

The migration is fully reversible:
- Drop `has_parts_access` function
- Restore old RLS policies on the 4 tables (calling `has_payroll_access` instead)
- The added `parts_access` JSONB key is harmless if left in place — `has_payroll_access` ignores it

The KPI math on the dashboard is untouched.

## Components Summary

| File | Action |
|---|---|
| `supabase/migrations/<new>.sql` | Create — function + RLS updates + backfill |
| `src/components/payroll/PartsGuard.tsx` | Create — mirrors PayrollGuard, accepts payroll_access OR parts_access |
| `src/App.tsx` | Modify — swap `<PayrollGuard>` → `<PartsGuard>` on the 2 parts routes |
| `src/components/admin/AdminUserManagement.tsx` | Modify — add `parts_access` to PAGE_PERMISSIONS |
| `src/components/admin/AdminPermissions.tsx` | Delete — dead code |

## Testing

### SQL tests (Supabase Studio query, manual)

- `has_parts_access` returns `true` for admin user ✅
- `has_parts_access` returns `true` for user with `payroll_access` only ✅
- `has_parts_access` returns `true` for user with `parts_access` only ✅
- `has_parts_access` returns `false` for user with neither flag ✅
- Reading `payroll_parts_prices` succeeds for parts-only user ✅
- Reading `payroll_runs` returns 0 rows for parts-only user (RLS blocks) ✅

### Frontend tests (vitest)

- `PartsGuard` renders children for admin
- `PartsGuard` renders children for `payroll_access=true`
- `PartsGuard` renders children for `parts_access=true`
- `PartsGuard` redirects + toasts for user with neither

### Manual smoke (live)

1. Authorize a new test email (or use an existing non-admin login) via `/admin → Users & Access`.
2. Toggle `parts_access` ON, leave `payroll_access` OFF.
3. Log in as that user.
4. `/payroll/parts` and `/payroll/parts/count` should load.
5. `/payroll`, `/payroll/run`, `/payroll/techs`, `/payroll/history` should redirect to `/`.
6. Toggle `parts_access` OFF — both parts pages now redirect.

## Observability

No new logging needed — this rides on the existing route-guard toast pattern (`"Access denied"` toast + redirect to `/`).

## Open Questions

None. Spec is small enough that any remaining ambiguity falls naturally out of the implementation plan.

## Follow-Up Specs

- **Admin impersonation write-parity** (Spec 2) — audit every tech-side mutation RPC (`add_job_part`, `tech_remove_job_part`, `submit_job_parts`, `tech_request_part_add`) for admin bypass; fix any gap so admin can interact with a tech's scorecard with full read+write parity.
