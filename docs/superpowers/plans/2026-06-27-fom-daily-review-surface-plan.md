# FOM Daily Review Surface (review-gated) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Replace auto-committed accountability points with a review-gated FOM surface: the system pre-fills what it can compute from HCP, Charles reviews/overrides per tech per day, and points post only when he saves.

**Architecture:** Reuse the existing detection (`src/lib/accountability/{three-options,auto-mapping,engine}.ts` and `src/lib/alerts/rules.ts`) to compute a per-ticket pre-fill; store the review in `tech_daily_review` (extended with `status`); write committed `accountability_points` on save (void-then-write). Remove the digest's auto-point emission; the digest email becomes a "needs review" nudge.

**Tech Stack:** React + TypeScript + React Query + shadcn/ui, Vitest, Deno (edge function), Supabase JS, date-fns.

**Base:** Branch off `feat/three-options-check` (or `main` after PR #270 merges) so `src/lib/accountability/three-options.ts` exists. This plan **supersedes the auto-commit behavior** of Plan 3 and PR #270 — close #270 after this lands (its detection logic is reused, its auto-emit is removed here).

**Repo:** Paths relative to `/Users/daniel/twins-dashboard/twins-dash`. Live-DB migrations applied by the controller via Supabase MCP (CLI `db push` is blocked by the jwrpj migration-history desync).

**Established patterns (reuse verbatim):** supabase client `@/integrations/supabase/client`; auth `useAuth()` (`session`, `user`); gating `enabled: !!session`; role gating `useEffectiveAuth()`; mutations with `useToast()` + `queryClient.invalidateQueries`; new tables use `.from("x" as never)` + `as unknown as T`; `useToday()`; `format(d,"yyyy-MM-dd")`; catalog hook `useViolationCatalog()`; summarizer `summarizeTech`.

---

## File Structure
- `supabase/migrations/20260627120000_review_surface_columns.sql` — add `violation_types.scope` + backfill; add `tech_daily_review.status` + backfill. (create)
- `src/lib/accountability/prefill.ts` — pure pre-fill computation per ticket. (create)
- `src/lib/accountability/__tests__/prefill.test.ts` — tests. (create)
- `src/hooks/useTechDayReview.ts` — load tickets+saved review, merge over pre-fill. (create)
- `src/hooks/useCommitDayReview.ts` — save mutation (void-then-write). (create)
- `src/components/accountability/DailyReviewCard.tsx` — the per-tech-per-day surface. (create)
- `src/components/accountability/AccountabilityTable.tsx` — add pending badge + "Review day" entry point; replace the old GradeChecklistDialog launch. (modify)
- `supabase/functions/daily-supervisor-digest/index.ts` — remove Task 3 + Task 3b auto-emit blocks; add pending-review count for the email. (modify)
- `supabase/functions/daily-supervisor-digest/render-email.ts` — add "N tech-days awaiting review" nudge line. (modify)

---

## Task 1: Migration — scope + status columns

**Files:** Create `supabase/migrations/20260627120000_review_surface_columns.sql`

- [ ] **Step 1: Write the migration**
```sql
-- Review-surface support: scope drives where each violation appears in the
-- FOM daily review (ticket row vs day-level vs ad-hoc); status marks whether
-- a tech-day review has been committed (points written) or is still pending.

ALTER TABLE public.violation_types
  ADD COLUMN IF NOT EXISTS scope text NOT NULL DEFAULT 'adhoc'
  CHECK (scope IN ('ticket','day','adhoc'));

UPDATE public.violation_types SET scope = 'ticket'
  WHERE code IN ('present_3_options','send_invoice','failure_collect_payment','inform_arrival',
                 'document_parts','before_photos','after_photos','safety_inspection','customer_signature');
UPDATE public.violation_types SET scope = 'day'
  WHERE code IN ('truck_restocked','company_uniform','vehicle_upkeep','rev_rise_call',
                 'missed_meeting','unapproved_absence','dirty_work_area');
-- everything else stays 'adhoc' (conduct/judgment violations)

ALTER TABLE public.tech_daily_review
  ADD COLUMN IF NOT EXISTS status text NOT NULL DEFAULT 'pending'
  CHECK (status IN ('pending','committed'));
-- any pre-existing rows were manual grades = treat as committed
UPDATE public.tech_daily_review SET status = 'committed' WHERE reviewed_at IS NOT NULL;
```

- [ ] **Step 2: Hand off to controller for live apply**
The controller applies via Supabase MCP `apply_migration` (name `review_surface_columns`) and records the version in `supabase_migrations.schema_migrations`. Do NOT run `supabase db push`.

- [ ] **Step 3: Commit**
```bash
git add supabase/migrations/20260627120000_review_surface_columns.sql
git commit -m "feat(accountability): scope + review status columns"
```

---

## Task 2: Pre-fill library (pure, TDD)

**Files:** Create `src/lib/accountability/prefill.ts`, test `src/lib/accountability/__tests__/prefill.test.ts`

The pre-fill answers, per ticket, for each auto item: is it a violation (flagged) or compliant? Result value is `'yes'` (compliant), `'no'` (violation/flagged), or `'na'`.

- [ ] **Step 1: Write the failing test**
```typescript
// src/lib/accountability/__tests__/prefill.test.ts
import { describe, it, expect } from "vitest";
import { prefillForTicket, type TicketSignals, type ItemResult } from "../prefill";

const base: TicketSignals = {
  optionCount: 3,        // options built on the ticket/linked estimate
  invoiceSent: true,
  paymentCollected: true,
  informedArrival: true,
};

describe("prefillForTicket", () => {
  it("all compliant -> all yes", () => {
    const p = prefillForTicket(base);
    expect(p.present_3_options).toBe<ItemResult>("yes");
    expect(p.send_invoice).toBe("yes");
    expect(p.failure_collect_payment).toBe("yes");
    expect(p.inform_arrival).toBe("yes");
  });
  it("fewer than 3 options -> present_3_options no", () => {
    expect(prefillForTicket({ ...base, optionCount: 1 }).present_3_options).toBe("no");
  });
  it("invoice not sent -> send_invoice no", () => {
    expect(prefillForTicket({ ...base, invoiceSent: false }).send_invoice).toBe("no");
  });
  it("payment not collected -> failure_collect_payment no", () => {
    expect(prefillForTicket({ ...base, paymentCollected: false }).failure_collect_payment).toBe("no");
  });
  it("not informed arrival -> inform_arrival no", () => {
    expect(prefillForTicket({ ...base, informedArrival: false }).inform_arrival).toBe("no");
  });
  it("null option count treated as fewer than 3 -> no", () => {
    expect(prefillForTicket({ ...base, optionCount: null }).present_3_options).toBe("no");
  });
});
```

- [ ] **Step 2: Run test to verify it fails**
Run: `npm test -- src/lib/accountability/__tests__/prefill.test.ts`
Expected: FAIL (cannot resolve ../prefill).

- [ ] **Step 3: Implement `prefill.ts`**
```typescript
// src/lib/accountability/prefill.ts
//
// Pure per-ticket pre-fill for the FOM daily review. Given HCP-derived signals
// for one ticket, returns the proposed checklist result for each AUTO item.
// 'yes' = compliant, 'no' = violation/flagged, 'na' = not applicable.
// Manual items are not pre-filled here (the UI leaves them blank).

import { needsThreeOptionsFlag } from "./three-options";

export type ItemResult = "yes" | "no" | "na";

export interface TicketSignals {
  /** Options built on the ticket or its linked estimate (null = none/unknown-as-zero). */
  optionCount: number | null;
  invoiceSent: boolean;
  paymentCollected: boolean;
  informedArrival: boolean;
}

/** The auto-detectable, ticket-scoped item codes. */
export const AUTO_TICKET_CODES = [
  "present_3_options",
  "send_invoice",
  "failure_collect_payment",
  "inform_arrival",
] as const;

export type AutoTicketCode = typeof AUTO_TICKET_CODES[number];

export function prefillForTicket(s: TicketSignals): Record<AutoTicketCode, ItemResult> {
  return {
    present_3_options: needsThreeOptionsFlag(s.optionCount) ? "no" : "yes",
    send_invoice: s.invoiceSent ? "yes" : "no",
    failure_collect_payment: s.paymentCollected ? "yes" : "no",
    inform_arrival: s.informedArrival ? "yes" : "no",
  };
}
```

- [ ] **Step 4: Run test to verify it passes**
Run: `npm test -- src/lib/accountability/__tests__/prefill.test.ts` → PASS (6 tests).

- [ ] **Step 5: Commit**
```bash
git add src/lib/accountability/prefill.ts src/lib/accountability/__tests__/prefill.test.ts
git commit -m "feat(accountability): per-ticket pre-fill computation"
```

---

## Task 3: `useTechDayReview` hook

**Files:** Create `src/hooks/useTechDayReview.ts`

Loads, for a tech + date: the tickets worked that day (jobs completed + estimates created), computes `TicketSignals` per ticket, builds the pre-fill, and merges any saved `tech_daily_review.results` over it. Returns the review model the card renders.

- [ ] **Step 1: Implement the hook**
```typescript
// src/hooks/useTechDayReview.ts
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/contexts/AuthContext";
import { prefillForTicket, type ItemResult } from "@/lib/accountability/prefill";

export interface ReviewTicket {
  job_id: string;            // internal jobs.id
  hcp_job_id: string | null;
  hcp_job_number: string | null;
  customer_name: string;
  is_estimate: boolean;
  items: Record<string, ItemResult>; // code -> result (auto prefilled + manual blanks as 'na')
}
export interface TechDayReview {
  status: "pending" | "committed";
  tickets: ReviewTicket[];
  day: Record<string, ItemResult>; // day-level code -> result
}

const DAY_DEFAULT: Record<string, ItemResult> = {}; // filled from catalog day items in the card

export function useTechDayReview(techId: string | null, dateIso: string) {
  const { session } = useAuth();
  return useQuery({
    queryKey: ["tech-day-review", techId, dateIso],
    enabled: !!session && !!techId,
    queryFn: async (): Promise<TechDayReview> => {
      // 1. saved review (if any)
      const { data: saved } = await supabase
        .from("tech_daily_review" as never)
        .select("results, status")
        .eq("technician_id", techId)
        .eq("review_date", dateIso)
        .maybeSingle() as { data: { results: { tickets?: Record<string, Record<string, ItemResult>>; day?: Record<string, ItemResult> }; status: "pending" | "committed" } | null };

      // 2. tickets worked that day, attributed to this tech. Jobs completed +
      //    estimates created on dateIso whose assigned_employees map to techId.
      //    (Reuse the digest's attribution: technicians.hcp_employee_id.)
      //    Fetch jobs/estimates for the date and compute signals.
      //    NOTE: implement the fetch + TicketSignals derivation here using
      //    hcp_data (work_timestamps.on_my_way_at -> informedArrival;
      //    invoice_number/invoiced_at -> invoiceSent; outstanding_balance==0 &&
      //    invoice_paid_at -> paymentCollected; linked estimate options ->
      //    optionCount). See Task 3 sub-steps below.
      const tickets = await loadTicketsForTechDay(techId!, dateIso);

      const savedTickets = saved?.results?.tickets ?? {};
      const savedDay = saved?.results?.day ?? {};

      const merged: ReviewTicket[] = tickets.map((t) => {
        const pf = prefillForTicket(t.signals);
        const items: Record<string, ItemResult> = {
          present_3_options: pf.present_3_options,
          send_invoice: pf.send_invoice,
          failure_collect_payment: pf.failure_collect_payment,
          inform_arrival: pf.inform_arrival,
          // manual ticket items start 'na' (unset) until Charles marks them
          document_parts: "na", before_photos: "na", after_photos: "na",
          safety_inspection: "na", customer_signature: "na",
          ...(savedTickets[t.job_id] ?? {}), // saved values win
        };
        return { job_id: t.job_id, hcp_job_id: t.hcp_job_id, hcp_job_number: t.hcp_job_number, customer_name: t.customer_name, is_estimate: t.is_estimate, items };
      });

      return { status: saved?.status ?? "pending", tickets: merged, day: { ...DAY_DEFAULT, ...savedDay } };
    },
    staleTime: 30_000,
  });
}
```

- [ ] **Step 2: Implement `loadTicketsForTechDay`** in the same file:
```typescript
interface RawTicket {
  job_id: string; hcp_job_id: string | null; hcp_job_number: string | null;
  customer_name: string; is_estimate: boolean;
  signals: { optionCount: number | null; invoiceSent: boolean; paymentCollected: boolean; informedArrival: boolean };
}
async function loadTicketsForTechDay(techId: string, dateIso: string): Promise<RawTicket[]> {
  // Resolve this tech's hcp_employee_id.
  const { data: tech } = await supabase.from("technicians").select("hcp_employee_id").eq("id", techId).maybeSingle();
  const hcpEmp = (tech as { hcp_employee_id: string | null } | null)?.hcp_employee_id ?? null;
  if (!hcpEmp) return [];
  const dayStart = `${dateIso}T00:00:00`; const dayEnd = `${dateIso}T23:59:59`;
  // Jobs completed that day, opportunities, non-estimate.
  const { data: jobs } = await supabase.from("jobs")
    .select("id, job_id, source_estimate_id, completed_at, hcp_data, is_opportunity, job_type")
    .gte("completed_at", dayStart).lte("completed_at", dayEnd).eq("is_opportunity", true);
  // Estimates created that day.
  const { data: ests } = await supabase.from("jobs")
    .select("id, job_id, created_at, hcp_data, job_type")
    .eq("job_type", "Estimate").gte("created_at", dayStart).lte("created_at", dayEnd);
  // Build estimate-options map for job->estimate lookups.
  // Filter both lists to tickets whose hcp_data.assigned_employees includes hcpEmp
  // (apply the Charles co-tech rule: if multiple techs, attribute to the non-Charles one).
  // For each retained ticket compute signals:
  //   informedArrival = !!hcp_data.work_timestamps.on_my_way_at
  //   invoiceSent     = !!hcp_data.invoice_number || !!jobs.invoiced_at
  //   paymentCollected= hcp_data.outstanding_balance == 0 (and an invoice exists)
  //   optionCount     = estimate ticket: len(hcp_data.options); job: linked estimate options or 0
  //   customer_name   = hcp_data.customer first+last
  // Return RawTicket[]. (Mirror attribution logic from supabase/functions/daily-supervisor-digest/index.ts fetchCandidateJobs.)
  return []; // replace with the assembled array per the rules above
}
```
> **Implementer note:** this is the one I/O-heavy function. Mirror the attribution + signal derivation already proven in `supabase/functions/daily-supervisor-digest/index.ts` (`toJobForAlerting`, `attributeTech`) and the three-options estimate-link logic. Keep it to bounded queries (no N+1): one jobs query, one estimates query, one technicians query, one estimate-options lookup for linked ids.

- [ ] **Step 3: Typecheck**
Run: `npx tsc --noEmit` → clean.

- [ ] **Step 4: Commit**
```bash
git add src/hooks/useTechDayReview.ts
git commit -m "feat(accountability): tech-day review loader with pre-fill merge"
```

---

## Task 4: `useCommitDayReview` mutation

**Files:** Create `src/hooks/useCommitDayReview.ts`

On save: upsert the review row (`status='committed'`), void prior `source IN ('checklist','auto')` points for that tech+date, insert one violation per "no" item (ticket items carry `job_id`; day items carry null). Points/severity from catalog.

- [ ] **Step 1: Implement**
```typescript
// src/hooks/useCommitDayReview.ts
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/contexts/AuthContext";
import { useToast } from "@/hooks/use-toast";
import type { ItemResult } from "@/lib/accountability/prefill";
import type { ViolationCatalogRow } from "@/hooks/useAccountability";

export interface CommitArgs {
  technicianId: string;
  dateIso: string;
  tickets: { job_id: string; items: Record<string, ItemResult> }[];
  day: Record<string, ItemResult>;
  catalog: ViolationCatalogRow[];
}

export function useCommitDayReview() {
  const qc = useQueryClient();
  const { user } = useAuth();
  const { toast } = useToast();
  return useMutation({
    mutationFn: async (a: CommitArgs) => {
      const byCode = new Map(a.catalog.map((c) => [c.code, c]));
      const now = new Date().toISOString();
      // 1. upsert review (committed)
      const { error: upErr } = await supabase.from("tech_daily_review" as never).upsert({
        technician_id: a.technicianId, review_date: a.dateIso,
        results: { tickets: Object.fromEntries(a.tickets.map((t) => [t.job_id, t.items])), day: a.day },
        status: "committed", reviewed_by: user?.id ?? null, reviewed_at: now,
      } as never, { onConflict: "technician_id,review_date" });
      if (upErr) throw upErr;
      // 2. void prior checklist/auto points for this tech+date
      const { error: voidErr } = await supabase.from("accountability_points" as never)
        .update({ voided_at: now, voided_by: user?.id ?? null } as never)
        .eq("technician_id", a.technicianId).eq("occurred_on", a.dateIso)
        .in("source", ["checklist", "auto"]).is("voided_at", null);
      if (voidErr) throw voidErr;
      // 3. insert one row per "no" item
      const rows: Record<string, unknown>[] = [];
      const push = (code: string, job_id: string | null) => {
        const cat = byCode.get(code); if (!cat) return;
        rows.push({ technician_id: a.technicianId, points: cat.points, reason_type: "violation",
          violation_code: code, severity: cat.severity, source: "checklist",
          occurred_on: a.dateIso, job_id, note: "FOM daily review", created_by: user?.id ?? null });
      };
      for (const t of a.tickets) for (const [code, res] of Object.entries(t.items)) if (res === "no") push(code, t.job_id);
      for (const [code, res] of Object.entries(a.day)) if (res === "no") push(code, null);
      if (rows.length) {
        const { error: insErr } = await supabase.from("accountability_points" as never).insert(rows as never);
        if (insErr) throw insErr;
      }
      return rows.length;
    },
    onSuccess: (_n, a) => {
      qc.invalidateQueries({ queryKey: ["tech-day-review", a.technicianId, a.dateIso] });
      qc.invalidateQueries({ queryKey: ["tech-accountability"] });
      qc.invalidateQueries({ queryKey: ["tech-ledger", a.technicianId] });
      qc.invalidateQueries({ queryKey: ["pending-reviews"] });
      toast({ title: "Day reviewed and saved" });
    },
    onError: (e: unknown) => toast({ title: "Save failed", description: String(e), variant: "destructive" }),
  });
}
```

- [ ] **Step 2: Typecheck** → `npx tsc --noEmit` clean.
- [ ] **Step 3: Commit**
```bash
git add src/hooks/useCommitDayReview.ts
git commit -m "feat(accountability): commit-day-review mutation (void-then-write)"
```

---

## Task 5: `DailyReviewCard` component

**Files:** Create `src/components/accountability/DailyReviewCard.tsx`

Renders the per-tech-per-day surface. Props: `{ technicianId; technicianName; dateIso; onOpenChange; open }` (a Dialog/Sheet). Uses `useTechDayReview`, `useViolationCatalog`, `useCommitDayReview`.

- [ ] **Step 1: Implement** (mirror the styling/patterns of `AddViolationDialog.tsx` and `GradeChecklistDialog.tsx`):
  - A date picker (default the passed `dateIso`, via `useToday()`).
  - **Per-ticket rows:** for each `review.tickets`, a block headed by customer name + "Ticket #{hcp_job_number}" + an HCP link `https://pro.housecallpro.com/app/jobs/{hcp_job_id}`. Under it, the ticket-scoped catalog items (`scope==='ticket'`), each a tri-state (yes/no/na) `Select` bound to local state seeded from `review.tickets[i].items`. Auto items (`auto_detectable`) show a small "auto" badge; their pre-filled value is editable.
  - **Day-level section:** the `scope==='day'` catalog items as tri-states bound to `review.day`.
  - **Add violation:** reuse `AddViolationDialog` for `scope==='adhoc'` (those still post immediately as manual — they're not part of the daily checklist commit). Keep it available from the card.
  - **Save button:** calls `useCommitDayReview().mutate({ technicianId, dateIso, tickets, day, catalog })`. Disable while pending / catalog loading. Show a "{N} points will post" preview (count of "no" items × catalog points).
  - Local state: `Record<job_id, Record<code, ItemResult>>` for tickets + `Record<code, ItemResult>` for day, initialized from the hook and updated on change.

- [ ] **Step 2: Typecheck** → clean.
- [ ] **Step 3: Commit**
```bash
git add src/components/accountability/DailyReviewCard.tsx
git commit -m "feat(accountability): daily review card (per-tech-per-day)"
```

---

## Task 6: Pending surface + wire into the tab

**Files:** Modify `src/components/accountability/AccountabilityTable.tsx`; add a `usePendingReviews` hook (in `src/hooks/useAccountability.ts`).

- [ ] **Step 1: Add `usePendingReviews(periodStartIso, periodEndIso)`** to `src/hooks/useAccountability.ts`: returns, per active tech, the count of work-days in range that have no `tech_daily_review` row with `status='committed'`. Implementation: query distinct (tech, day) that had tickets in range (jobs.completed_at / estimates.created_at), left-join committed `tech_daily_review`; count the gaps. `enabled: !!session`, queryKey `["pending-reviews", periodStartIso, periodEndIso]`.

- [ ] **Step 2: Modify `AccountabilityTable.tsx`:**
  - Replace the row's "Grade checklist" button with **"Review day"** that opens `DailyReviewCard` for that tech + today's date.
  - Add a **pending badge** in the tab header: total pending tech-days (from `usePendingReviews`), styled amber, e.g. "{N} days awaiting review".
  - Per-row: show a small "needs review" pill when the tech has pending days.

- [ ] **Step 3: Typecheck + tests** → `npx tsc --noEmit` clean; `npm test -- src/lib/accountability` green.
- [ ] **Step 4: Commit**
```bash
git add src/components/accountability/AccountabilityTable.tsx src/hooks/useAccountability.ts
git commit -m "feat(accountability): pending-review surface + Review day entry point"
```

---

## Task 7: Remove digest auto-emit; add review nudge

**Files:** Modify `supabase/functions/daily-supervisor-digest/index.ts` and `render-email.ts`.

- [ ] **Step 1: Remove auto-point emission.** In `index.ts`, delete the entire `// ---- Task 3: Emit idempotent auto ledger entries ----` try/catch block and the `// ---- Task 3b: Auto-flag tickets with fewer than 3 options ----` try/catch block. Keep the `supervisor_alerts` upsert (operational ticket list) and the email assembly. The accountability detection now lives in the dashboard pre-fill, not the digest.

- [ ] **Step 2: Add a pending-review count for the email.** After assembling tickets, query the count of pending tech-days (reuse the same logic as `usePendingReviews` over the digest window): tech-days with tickets in window lacking a committed `tech_daily_review`. Pass it to the renderer.

- [ ] **Step 3: Email nudge.** In `render-email.ts`, change the accountability section to lead with: **"{N} tech-days awaiting your review"** as a prominent banner linking to `https://twinsdash.com/admin/notifications` (the tab), above the committed standings table. Keep the committed standings (from `summarizeTech` over the ledger) below it. Update the Deno render test to assert the nudge line renders when count > 0.

- [ ] **Step 4: Verify**
Run: `deno test supabase/functions/daily-supervisor-digest/__test__/render.test.ts` → all pass.
- [ ] **Step 5: Commit**
```bash
git add supabase/functions/daily-supervisor-digest/
git commit -m "feat(digest): stop auto-writing points; lead with review nudge"
```

---

## Task 8: Full verification
- [ ] `npm test -- src/lib/accountability` (prefill + existing) green.
- [ ] `deno test supabase/functions/daily-supervisor-digest/__test__/render.test.ts` green.
- [ ] `npx tsc --noEmit` clean; `npm run build` succeeds.
- [ ] Controller: apply the migration (MCP), deploy the digest, deploy nothing else (frontend deploys on merge). Manual dry-run digest → confirms nudge renders and NO points are written.

## Self-Review (planning)
- **Spec coverage:** review-gated commit (Tasks 3-5), per-tech-per-day card (Task 5), pre-fill from HCP reusing detectors (Tasks 2-3), pending + flagging (Tasks 6-7), digest stops writing + nudge (Task 7), `tech_daily_review` as review state + `scope`/`status` (Task 1), void-then-write idempotency (Task 4). Un-reviewed never auto-commits (no code path writes points except commit). Ad-hoc conduct still via AddViolation (Task 5).
- **Placeholders:** `loadTicketsForTechDay` is specified by rule with a clear implementer note pointing at the proven digest logic to mirror; not a vague TODO. `DailyReviewCard` is specified structurally with explicit state shape + the existing components to mirror.
- **Type consistency:** `ItemResult`, `TicketSignals`, `AUTO_TICKET_CODES`, `ReviewTicket`/`TechDayReview`, `CommitArgs`, `ViolationCatalogRow` are consistent across Tasks 2-6; commit `source='checklist'`, void targets `source IN ('checklist','auto')`.

## Open items
- Day-level item set and the OMW (`inform_arrival`) keep/drop decision are catalog-driven (Task 1 backfill) and tunable without code; confirm with Charles before go-live.
- Close PR #270 once this lands (its 3-options auto-commit is superseded; its detection is reused).
