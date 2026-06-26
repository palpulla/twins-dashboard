# Technician Accountability — Plan 2: FOM Dashboard Tab

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Add a "Tech Accountability" tab to `/admin/notifications` where Charles sees a per-tech aggregate (balance, Level, weekly points, severity breakdown, callback rate, days clean, last action), expands a tech to see the ledger + action log, and performs the field actions: grade the daily checklist, add a violation, log a talk/discipline action, and classify a callback.

**Architecture:** Reads the Plan 1 tables (`accountability_points`, `accountability_actions`, `tech_daily_review`, `violation_types`) + `technicians` + `jobs` (callbacks). A pure-TS summarizer (`src/lib/accountability/summary.ts`, vitest-tested) turns raw ledger rows into per-tech summaries using the Plan 1 engine. React Query hooks (gated on session) feed the UI. All writes go straight to the tables via the RLS that already allows admin + field_supervisor. Nothing auto-disciplines.

**Tech Stack:** React, TypeScript, React Query, shadcn/ui, Vitest, date-fns, Supabase JS.

**Repo:** Paths relative to `/Users/daniel/twins-dashboard/twins-dash`. Work continues on branch `feat/accountability-program` in worktree `.worktrees/accountability` (Plan 1 lives here; this ships in the same PR #269).

**Key established patterns (use verbatim):**
- Supabase client: `import { supabase } from "@/integrations/supabase/client";`
- Auth: `const { isAdmin, isFieldSupervisor } = useEffectiveAuth();` (from `@/contexts/EffectiveAuthContext`) for gating; `const { user } = useAuth();` (from `@/contexts/AuthContext`) for `user?.id` on inserts.
- Query gating: every supabase `useQuery` must include `enabled: !!session` where session comes from `useAuth()` — anon-keyed first fetch returns [] under RLS and caches forever otherwise.
- FK-join select: `.select("...cols, technician:technician_id(id, name)")`.
- Mutation: `useMutation({ mutationFn, onSuccess: () => { queryClient.invalidateQueries({queryKey:[...]}); toast({title}); }, onError })` with `useToast()` from `@/hooks/use-toast`.
- Active techs: `supabase.from("technicians").select("id, name").eq("is_active", true).order("name")`.
- Currency (not used much here) full dollars: `$${Math.round(x).toLocaleString("en-US")}`.
- Local date string: `format(d, "yyyy-MM-dd")` from date-fns.
- shadcn imports from `@/components/ui/{tabs,table,badge,dialog,button,checkbox,select,textarea,label,card}`.

---

## File Structure

- Create `src/lib/accountability/summary.ts` — pure `summarizeTech(entries, actions, today)` → per-tech summary; `severityBreakdown`. Tested.
- Create `src/lib/accountability/__tests__/summary.test.ts`.
- Create `src/hooks/useAccountability.ts` — query hooks: `useViolationCatalog()`, `useTechAccountability()` (techs + ledger + actions → summaries), `useTechLedger(techId)`, `useCallbacksToClassify()`.
- Create `src/components/accountability/TechAccountabilityTab.tsx` — tab body: period note, table, dialogs.
- Create `src/components/accountability/AccountabilityTable.tsx` — the per-tech table + row expand.
- Create `src/components/accountability/SeverityPills.tsx` — minor/serious/major count pills (mirror IssuePill).
- Create `src/components/accountability/AddViolationDialog.tsx`.
- Create `src/components/accountability/LogActionDialog.tsx`.
- Create `src/components/accountability/GradeChecklistDialog.tsx`.
- Create `src/components/accountability/ClassifyCallbackDialog.tsx`.
- Modify `src/pages/admin/Notifications.tsx` — wrap content in Tabs, add the new tab.

---

## Task 1: Per-tech summarizer (pure TS, TDD)

**Files:** Create `src/lib/accountability/summary.ts`, test `src/lib/accountability/__tests__/summary.test.ts`.

Define:
```typescript
import { computeBalance, computeLevel, weeklyPoints, daysSinceLastPoint, rewardEligible } from "./engine";
import type { LedgerEntry, Level, Severity } from "./types";

export interface SeverityBreakdown { minor: number; serious: number; major: number; }

export interface TechSummary {
  technician_id: string;
  balance: number;
  level: Level;
  weekly_points: number;       // current Fri-Thu week
  days_clean: number | null;   // null if never any point
  reward_eligible: boolean;
  severity: SeverityBreakdown; // counts of non-voided violation entries by severity
}

export function severityBreakdown(entries: LedgerEntry[]): SeverityBreakdown;
export function summarizeTech(technician_id: string, entries: LedgerEntry[], today: Date): TechSummary;
```
- `severityBreakdown`: count non-voided `reason_type==='violation'` entries grouped by `severity` (count of violations, not points).
- `summarizeTech`: compose the engine fns. `entries` are that tech's ledger rows.

Tests (TDD): a tech with mixed entries → correct balance/level/weekly/days_clean/reward/severity; voided entries excluded from severity; empty entries → balance 0, level 0, days_clean null, reward true, zero severity.

Commit: `feat(accountability): per-tech summarizer with tests`.

---

## Task 2: Query hooks

**Files:** Create `src/hooks/useAccountability.ts`.

```typescript
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/contexts/AuthContext";
import { summarizeTech, type TechSummary } from "@/lib/accountability/summary";
import type { LedgerEntry } from "@/lib/accountability/types";
```

Hooks (all `enabled: !!session` via `const { session } = useAuth();`):
- `useViolationCatalog()` — `supabase.from("violation_types").select("code,label,points,severity,is_checklist_item,auto_detectable,sort_order").eq("active",true).order("sort_order")`. staleTime 5min.
- `useTechAccountability(today: Date)` — fetch active technicians (`id,name`) and ALL non-voided `accountability_points` (`select("id,technician_id,points,reason_type,violation_code,severity,source,occurred_on,job_id,note,voided_at").is("voided_at",null)`) and `accountability_actions` (`id,technician_id,action_type,occurred_on,notes` order occurred_on desc). Group entries by technician_id; build `TechSummary` per tech via `summarizeTech`; attach `name` and `last_action` (most recent action's type+date). Return array sorted worst-first (balance desc, then weekly desc). Query key includes `today` ISO date.
- `useTechLedger(techId: string)` — non-voided + voided entries for one tech (for the expand view), plus that tech's actions. Joins `violation:violation_code(label)`. `enabled: !!session && !!techId`.
- `useCallbacksToClassify(sinceISO: string)` — jobs where `is_callback=true`, completed since `sinceISO`, that do NOT yet have an `accountability_points` row with `reason_type='violation'` and a matching `job_id` and a recall classification note. Implemented as two queries: (a) callback jobs `select("id,job_id,job_type,completed_at,revenue_amount,hcp_data, job_technicians(technician_id)")` with `.eq("is_callback",true).gte("completed_at",sinceISO)`; (b) existing `accountability_points` job_ids with `note ilike 'Callback classified%'`. Filter out already-classified in JS. Return per-job with attributed tech (Charles co-tech rule via the existing attribution helper if available, else first non-Charles tech).

Commit: `feat(accountability): query hooks for summaries, catalog, ledger, callbacks`.

---

## Task 3: Tab wrapper + table + severity pills (read-only UI)

**Files:** Modify `src/pages/admin/Notifications.tsx`; create `SeverityPills.tsx`, `AccountabilityTable.tsx`, `TechAccountabilityTab.tsx`.

- `Notifications.tsx`: wrap the existing three Cards inside `<Tabs defaultValue="issues">` with `<TabsList>`: "Open issues", "Past digests", "Tech Accountability", and (admin only) keep Settings inside the Tech tab area or its own tab. Simpler: keep the existing cards under a "Triage" tab and add a "Tech Accountability" tab rendering `<TechAccountabilityTab/>`. Preserve the `{isAdmin && <Settings/>}` gate.
- `SeverityPills.tsx`: render minor/serious/major counts as small pills (gray/amber/red) — mirror `IssuePill` styling; hide a tier when its count is 0.
- `AccountabilityTable.tsx`: consumes `useTechAccountability(useToday())`. Columns: Tech | Balance | Level (badge L0-L4, color-banded) | This week | Severity (SeverityPills) | Days clean | Last action. Worst-first. A reward-eligible tech shows a small green "Reward eligible" badge by the name. Each row is expandable (Radix? simplest: a chevron button toggling a detail `<TableRow>` below, like AlertsTable's two-row pattern) showing `useTechLedger(techId)`: a list of ledger entries (date, label/severity, points, source, note, void button) and the action log (date, action_type, notes). Row action buttons: "Grade checklist", "Add violation", "Log action", "Classify callback" (open the dialogs from Tasks 4-7; wire `onDone` to invalidate `["tech-accountability"]`).
- `TechAccountabilityTab.tsx`: header + a note explaining cumulative balance + Fri-Thu weekly + the Level ladder; renders `<AccountabilityTable/>`. Gated: render only if `isAdmin || isFieldSupervisor` (the route already enforces field_supervisor).

Commit: `feat(accountability): notifications tab + per-tech table`.

---

## Task 4: Add-violation dialog

**Files:** Create `AddViolationDialog.tsx`.

Props: `{ technicianId: string; technicianName: string; open; onOpenChange; onDone }`. Fields: violation `Select` (from `useViolationCatalog()`, grouped/labeled with points+severity), `occurred_on` date input (default today, `format(useToday(),"yyyy-MM-dd")`), optional note `Textarea`. On submit, insert into `accountability_points`:
```typescript
{ technician_id, points: cat.points, reason_type: "violation", violation_code: cat.code,
  severity: cat.severity, source: "manual", occurred_on, note, created_by: user?.id }
```
Mutation invalidates `["tech-accountability"]` + `["tech-ledger", technicianId]`, toast, close. Disable submit while pending / if no violation selected.

Commit: `feat(accountability): add-violation dialog`.

---

## Task 5: Log-action dialog

**Files:** Create `LogActionDialog.tsx`.

Props include the tech's current `balance` and `level` (from the row summary) to snapshot. Fields: action_type `Select` (the 8 enum values with friendly labels: Coaching discussion, Improvement plan, Written warning, 1-day suspension, Final written warning, 3-day suspension, Termination review, Recognition), `occurred_on` (default today), notes `Textarea`. Insert into `accountability_actions`:
```typescript
{ technician_id, action_type, occurred_on, notes, level_at_time: level, balance_at_time: balance, created_by: user?.id }
```
Invalidate `["tech-accountability"]` + `["tech-ledger", technicianId]`, toast, close.

Commit: `feat(accountability): log-action dialog`.

---

## Task 6: Daily checklist grading dialog (idempotent re-grade)

**Files:** Create `GradeChecklistDialog.tsx`.

Props: `{ technicianId; technicianName; open; onOpenChange; onDone }`. Fields: `review_date` date (default today), and the 9 checklist items (from `useViolationCatalog()` filtered to `is_checklist_item`, ordered by `sort_order`) each as a yes/no/na tri-state (default "yes"). An optional note.

On submit, in one logical flow:
1. Upsert `tech_daily_review` on `(technician_id, review_date)`: `{ technician_id, review_date, results: {code: 'yes'|'no'|'na', ...}, note, reviewed_by: user?.id, reviewed_at: now }` (use `.upsert(..., { onConflict: "technician_id,review_date" })`).
2. **Re-grade safety:** void any existing checklist-source ledger entries for this tech+date before inserting fresh ones, so re-grading doesn't double-count:
   `supabase.from("accountability_points").update({ voided_at: now, voided_by: user?.id }).eq("technician_id",technicianId).eq("occurred_on",review_date).eq("source","checklist").is("voided_at",null)`.
3. For each item marked "no", insert a ledger violation entry: `{ technician_id, points: cat.points, reason_type:"violation", violation_code: cat.code, severity: cat.severity, source:"checklist", occurred_on: review_date, note: "Daily checklist", created_by: user?.id }`.
Run as sequential awaits inside the mutationFn (no transaction needed; void-then-insert is safe). Invalidate `["tech-accountability"]`, `["tech-ledger", technicianId]`, toast, close.

This is the trickiest task — the void-before-insert idempotency is the key correctness point. Note it clearly.

Commit: `feat(accountability): daily checklist grading (idempotent)`.

---

## Task 7: Callback classification + callback-rate column

**Files:** Create `ClassifyCallbackDialog.tsx`; extend `AccountabilityTable.tsx` with a callback-rate cell; extend `useAccountability.ts` with a callback-rate computation.

- Callback rate per tech: over a trailing 90-day window, `callbacks attributed / opportunities attributed * 100`. Reuse `calculateCallbackRate` from `src/lib/kpi-calculations.ts` if it can be fed per-tech job arrays; otherwise compute counts directly. Show color-banded (<4% green / 4-8% amber / >8% red) per the program's Section 9 reference. Add a "N to classify" chip when `useCallbacksToClassify` returns rows for that tech.
- `ClassifyCallbackDialog.tsx`: lists the tech's unclassified callback jobs (job #, customer, completed date). For each, a `Select`: Recall (tech fault) / Warranty / Training gap. On submit: for `recall`, insert a ledger entry `{ technician_id, points: 2, reason_type:"violation", violation_code:"incorrect_diagnosis_return", severity:"serious", source:"manual", occurred_on: <job completed date>, job_id, note: "Callback classified: recall" }`; for warranty/training_gap, insert a 0-point marker row `{ points:0, reason_type:"adjustment", source:"manual", occurred_on, job_id, note: "Callback classified: warranty" | "training_gap" }` so it's recorded as classified and filtered out of "to classify" next time. (Plan 1 ledger has no positivity CHECK, so points:0 is allowed.)
- The "to classify" filter in `useCallbacksToClassify` keys off `note ilike 'Callback classified%'` for that job_id.

Commit: `feat(accountability): callback classification + callback-rate column`.

---

## Task 8: Verify

- [ ] `npm test -- src/lib/accountability` (summary tests + Plan 1 tests pass).
- [ ] `npx tsc --noEmit` clean.
- [ ] Preview the app (`preview_start`), open `/admin/notifications`, confirm the Tech Accountability tab renders, the table loads (empty techs OK), and each dialog opens. Screenshot.
- [ ] Manually exercise: add a violation to a test tech → balance updates; grade a checklist with one "no" → ledger entry appears; re-grade → no double count; log an action → appears in expand; classify a callback → recall adds points.

Commit any fixes.

## Self-Review (planning)
- Spec coverage: aggregate table (T3), grade checklist (T6), add violation (T4), log talk/discipline (T5), classify callback (T7), callback rate (T7), severity breakdown (T3), reward badge (T3), cumulative balance + Level + Fri-Thu weekly (T1/engine), reversible voids (T3 expand + T6 re-grade). Gating to admin+field_supervisor (T3).
- Placeholders: none — interfaces and insert shapes are explicit.
- Type consistency: `TechSummary`, `SeverityBreakdown`, hook names, and insert column names match Plan 1 schema exactly.
