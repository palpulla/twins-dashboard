# Technician Accountability Program — Plan 1: Data Model + Engine + Decay

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the database backbone and pure-TS scoring engine for the Technician Accountability Program — the violation catalog, the signed-entry points ledger, the daily-checklist audit table, the discipline-action log, the balance/Level/decay/reward logic, and the daily decay cron.

**Architecture:** Four additive Postgres tables on `jwrpj` (catalog + ledger + daily review + actions), each RLS-gated to admin/field_supervisor mirroring the existing `inventory_count_*` pattern. All scoring logic lives in a pure-TS module `src/lib/accountability/` (matching how `src/lib/alerts/` is tested with vitest, not SQL), so balance/Level/decay/reward are unit-testable. A new `accountability-decay` edge function vendors that engine (with a sync test, like `daily-supervisor-digest`) and a pg_cron job runs it daily.

**Tech Stack:** Supabase (Postgres + pg_cron + pg_net + Deno edge functions), Supabase CLI migrations, TypeScript, Vitest, date-fns.

**Scope:** This is **Plan 1 of 3**. Plan 2 = FOM "Tech Accountability" tab UI (grade checklist, add violation, classify callback, log action, aggregate table). Plan 3 = refactor `daily-supervisor-digest` to emit ledger entries from auto-detections + add the email Accountability section. This plan delivers a tested, working data + engine layer with no UI consumer yet; that is the intended foundation.

**Repo:** All paths are relative to the inner repo root `/Users/daniel/twins-dashboard/twins-dash` unless noted. The plan file itself lives in the outer repo (`docs/superpowers/plans/`).

**Remote project:** `jwrpjuqaynownxaoeayi` (jwrpj). Migrations applied with `npx supabase db push --linked`. Known desync: after a push, if the version isn't recorded, INSERT it into `schema_migrations` (see Task 1 Step 4).

---

## File Structure

**Migrations (create):**
- `supabase/migrations/20260626120000_accountability_violation_types.sql` — catalog table + seed.
- `supabase/migrations/20260626120100_accountability_points_ledger.sql` — ledger table + RLS + indexes.
- `supabase/migrations/20260626120200_accountability_daily_review.sql` — daily checklist audit table + RLS.
- `supabase/migrations/20260626120300_accountability_actions.sql` — discipline/talk log table + RLS.
- `supabase/migrations/20260626120400_accountability_decay_cron.sql` — pg_cron registration.

**TS engine (create):**
- `src/lib/accountability/types.ts` — types, the violation catalog constant, checklist item list, Level thresholds.
- `src/lib/accountability/engine.ts` — `computeBalance`, `computeLevel`, `weeklyPoints`, `daysSinceLastPoint`, `decayDue`, `rewardEligible`, `parseDateLocal`.
- `src/lib/accountability/__tests__/engine.test.ts` — vitest coverage.
- `src/lib/accountability/__tests__/catalog.test.ts` — catalog integrity tests.

**Edge function (create):**
- `supabase/functions/accountability-decay/index.ts` — daily decay runner.
- `supabase/functions/accountability-decay/engine-vendored.ts` — byte-faithful copy of types.ts + engine.ts.
- `src/lib/accountability/__tests__/vendored-sync.test.ts` — asserts the vendored copy exports the same symbols.
- `supabase/config.toml` — add `[functions.accountability-decay]` (modify).

---

## The Violation Catalog (authoritative list used in Task 1 and Task 5)

| code | label | points | severity | checklist? | auto? |
|---|---|---|---|---|---|
| `present_3_options` | Presented 3 options | 1 | minor | yes | no |
| `document_parts` | Parts listed correctly | 1 | minor | yes | no |
| `before_photos` | Before photos uploaded | 1 | minor | yes | no |
| `after_photos` | After photos uploaded | 1 | minor | yes | no |
| `customer_signature` | Customer signature obtained | 1 | minor | yes | no |
| `send_invoice` | Invoice complete / sent | 1 | minor | yes | yes |
| `truck_restocked` | Truck restocked at end of day | 1 | minor | yes | no |
| `dirty_work_area` | Dirty work area after job | 1 | minor | no | no |
| `inform_arrival` | Failed to inform customer of arrival | 1 | minor | no | yes |
| `company_uniform` | Not wearing company uniform | 1 | minor | no | no |
| `vehicle_upkeep` | Failed to upkeep company vehicle | 1 | minor | no | no |
| `rev_rise_call` | Missing Rev & Rise call | 1 | minor | no | no |
| `sensor_alignment_return` | Did not explain safety-sensor alignment, return call | 1 | minor | no | no |
| `customer_complaint_negligence` | Customer complaint from tech negligence | 2 | serious | no | no |
| `safety_inspection` | Safety inspection completed | 2 | serious | yes | no |
| `recommend_safety_issues` | Failed to recommend obvious safety issues | 2 | serious | no | no |
| `left_job_incomplete` | Left job incomplete without approval | 2 | serious | no | no |
| `incorrect_diagnosis_return` | Incorrect diagnosis resulting in return visit | 2 | serious | no | no |
| `no_late_call` | Failed to call customer when running late | 2 | serious | no | no |
| `missing_tools` | Missing required tools | 2 | serious | no | no |
| `repeat_after_coaching` | Repeated 1-point violation after a coaching discussion | 2 | serious | no | no |
| `missed_meeting` | Missed company L10 or sales-training meeting | 2 | serious | no | no |
| `unsafe_practices` | Unsafe work practices | 3 | major | no | no |
| `incomplete_arrival_inspection` | Incomplete required arrival-inspection fields | 3 | major | no | no |
| `quote_dishonesty` | Dishonesty on a quote | 3 | major | no | no |
| `property_damage` | Damaging customer property due to negligence | 3 | major | no | no |
| `failure_collect_payment` | Failure to collect payment | 3 | major | yes | yes |
| `unapproved_absence` | Unapproved absence | 3 | major | no | no |
| `disrespect_customer` | Disrespectful behavior toward a customer | 3 | major | no | no |
| `disrespect_peers` | Disrespectful behavior toward peers | 3 | major | no | no |

**Daily checklist** = the 9 `checklist=yes` rows, displayed in this order: `present_3_options, document_parts, before_photos, after_photos, safety_inspection, send_invoice, customer_signature, failure_collect_payment, truck_restocked`.

---

## Task 1: Violation catalog table + seed

**Files:**
- Create: `supabase/migrations/20260626120000_accountability_violation_types.sql`

- [ ] **Step 1: Write the migration**

```sql
-- Editable catalog of accountability violations. Point values are config so
-- Daniel/Charles can tune them without a deploy. Seeded from Charles's
-- Technician Accountability Program (1/2/3-point lists + daily checklist).

CREATE TABLE IF NOT EXISTS public.violation_types (
  code              text PRIMARY KEY,
  label             text NOT NULL,
  points            int  NOT NULL CHECK (points BETWEEN 1 AND 3),
  severity          text NOT NULL CHECK (severity IN ('minor','serious','major')),
  is_checklist_item boolean NOT NULL DEFAULT false,
  auto_detectable   boolean NOT NULL DEFAULT false,
  active            boolean NOT NULL DEFAULT true,
  sort_order        int  NOT NULL DEFAULT 0,
  created_at        timestamptz NOT NULL DEFAULT now()
);

INSERT INTO public.violation_types
  (code, label, points, severity, is_checklist_item, auto_detectable, sort_order)
VALUES
  ('present_3_options','Presented 3 options',1,'minor',true,false,10),
  ('document_parts','Parts listed correctly',1,'minor',true,false,20),
  ('before_photos','Before photos uploaded',1,'minor',true,false,30),
  ('after_photos','After photos uploaded',1,'minor',true,false,40),
  ('safety_inspection','Safety inspection completed',2,'serious',true,false,50),
  ('send_invoice','Invoice complete / sent',1,'minor',true,true,60),
  ('customer_signature','Customer signature obtained',1,'minor',true,false,70),
  ('failure_collect_payment','Failure to collect payment',3,'major',true,true,80),
  ('truck_restocked','Truck restocked at end of day',1,'minor',true,false,90),
  ('dirty_work_area','Dirty work area after job',1,'minor',false,false,100),
  ('inform_arrival','Failed to inform customer of arrival',1,'minor',false,true,110),
  ('company_uniform','Not wearing company uniform',1,'minor',false,false,120),
  ('vehicle_upkeep','Failed to upkeep company vehicle',1,'minor',false,false,130),
  ('rev_rise_call','Missing Rev & Rise call',1,'minor',false,false,140),
  ('sensor_alignment_return','Did not explain safety-sensor alignment, return call',1,'minor',false,false,150),
  ('customer_complaint_negligence','Customer complaint from tech negligence',2,'serious',false,false,160),
  ('recommend_safety_issues','Failed to recommend obvious safety issues',2,'serious',false,false,170),
  ('left_job_incomplete','Left job incomplete without approval',2,'serious',false,false,180),
  ('incorrect_diagnosis_return','Incorrect diagnosis resulting in return visit',2,'serious',false,false,190),
  ('no_late_call','Failed to call customer when running late',2,'serious',false,false,200),
  ('missing_tools','Missing required tools',2,'serious',false,false,210),
  ('repeat_after_coaching','Repeated 1-point violation after a coaching discussion',2,'serious',false,false,220),
  ('missed_meeting','Missed company L10 or sales-training meeting',2,'serious',false,false,230),
  ('unsafe_practices','Unsafe work practices',3,'major',false,false,240),
  ('incomplete_arrival_inspection','Incomplete required arrival-inspection fields',3,'major',false,false,250),
  ('quote_dishonesty','Dishonesty on a quote',3,'major',false,false,260),
  ('property_damage','Damaging customer property due to negligence',3,'major',false,false,270),
  ('unapproved_absence','Unapproved absence',3,'major',false,false,280),
  ('disrespect_customer','Disrespectful behavior toward a customer',3,'major',false,false,290),
  ('disrespect_peers','Disrespectful behavior toward peers',3,'major',false,false,300)
ON CONFLICT (code) DO UPDATE SET
  label = EXCLUDED.label,
  points = EXCLUDED.points,
  severity = EXCLUDED.severity,
  is_checklist_item = EXCLUDED.is_checklist_item,
  auto_detectable = EXCLUDED.auto_detectable,
  sort_order = EXCLUDED.sort_order;

ALTER TABLE public.violation_types ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "read violation_types" ON public.violation_types;
CREATE POLICY "read violation_types" ON public.violation_types
  FOR SELECT TO authenticated
  USING (auth.uid() IS NOT NULL);

DROP POLICY IF EXISTS "admin manage violation_types" ON public.violation_types;
CREATE POLICY "admin manage violation_types" ON public.violation_types
  FOR ALL TO authenticated
  USING (public.has_role(auth.uid(), 'admin'::public.app_role))
  WITH CHECK (public.has_role(auth.uid(), 'admin'::public.app_role));

GRANT SELECT, INSERT, UPDATE, DELETE ON public.violation_types TO authenticated;
```

- [ ] **Step 2: Apply the migration**

Run: `cd /Users/daniel/twins-dashboard/twins-dash && npx supabase db push --linked`
Expected: applies `20260626120000_accountability_violation_types`; no errors.

- [ ] **Step 3: Verify the seed**

Run: `npx supabase db push --linked` then in the Supabase SQL editor (or via MCP `execute_sql`): `SELECT count(*) FROM public.violation_types;`
Expected: `30`. And `SELECT count(*) FROM public.violation_types WHERE is_checklist_item;` → `9`.

- [ ] **Step 4: Record migration version if desynced**

If the push reports the version wasn't tracked, run:
`INSERT INTO supabase_migrations.schema_migrations (version) VALUES ('20260626120000') ON CONFLICT DO NOTHING;`

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260626120000_accountability_violation_types.sql
git commit -m "feat(accountability): violation_types catalog table + seed"
```

---

## Task 2: Points ledger table

**Files:**
- Create: `supabase/migrations/20260626120100_accountability_points_ledger.sql`

- [ ] **Step 1: Write the migration**

```sql
-- Signed-entry points ledger. Current balance = SUM(points) over non-voided
-- rows for a tech. Violations are positive; decay and corrections are
-- negative/explicit entries so the record is auditable and reversible
-- (soft-void only, never hard-delete).

CREATE TABLE IF NOT EXISTS public.accountability_points (
  id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  technician_id  uuid NOT NULL REFERENCES public.technicians(id),
  points         int  NOT NULL,
  reason_type    text NOT NULL CHECK (reason_type IN ('violation','decay','adjustment')),
  violation_code text REFERENCES public.violation_types(code),
  severity       text CHECK (severity IN ('minor','serious','major')),
  source         text NOT NULL DEFAULT 'manual'
                 CHECK (source IN ('auto','checklist','manual','system')),
  occurred_on    date NOT NULL,
  job_id         uuid REFERENCES public.jobs(id),
  note           text,
  created_by     uuid REFERENCES auth.users(id),
  voided_at      timestamptz,
  voided_by      uuid REFERENCES auth.users(id),
  created_at     timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_acct_points_tech_date
  ON public.accountability_points(technician_id, occurred_on)
  WHERE voided_at IS NULL;

ALTER TABLE public.accountability_points ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "fom manage acct_points" ON public.accountability_points;
CREATE POLICY "fom manage acct_points" ON public.accountability_points
  FOR ALL TO authenticated
  USING (
    public.has_role(auth.uid(), 'admin'::public.app_role)
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  )
  WITH CHECK (
    public.has_role(auth.uid(), 'admin'::public.app_role)
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  );

GRANT SELECT, INSERT, UPDATE ON public.accountability_points TO authenticated;
```

- [ ] **Step 2: Apply**

Run: `npx supabase db push --linked`
Expected: applies `20260626120100`; no errors.

- [ ] **Step 3: Verify**

Via SQL editor / MCP: `SELECT count(*) FROM public.accountability_points;` → `0` (table exists, empty).

- [ ] **Step 4: Record version if desynced**

`INSERT INTO supabase_migrations.schema_migrations (version) VALUES ('20260626120100') ON CONFLICT DO NOTHING;`

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260626120100_accountability_points_ledger.sql
git commit -m "feat(accountability): signed-entry points ledger table"
```

---

## Task 3: Daily checklist audit table

**Files:**
- Create: `supabase/migrations/20260626120200_accountability_daily_review.sql`

- [ ] **Step 1: Write the migration**

```sql
-- One row per tech per day capturing the supervisor's checklist grading.
-- results is a JSON object keyed by violation_type.code with value
-- 'yes' | 'no' | 'na'. Each 'no' spawns a ledger violation entry (done in
-- Plan 2's grading UI). Storing the full result set gives an audit trail
-- even for clean days.

CREATE TABLE IF NOT EXISTS public.tech_daily_review (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  technician_id uuid NOT NULL REFERENCES public.technicians(id),
  review_date   date NOT NULL,
  results       jsonb NOT NULL DEFAULT '{}'::jsonb,
  note          text,
  reviewed_by   uuid REFERENCES auth.users(id),
  reviewed_at   timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT uq_daily_review_tech_date UNIQUE (technician_id, review_date)
);

ALTER TABLE public.tech_daily_review ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "fom manage daily_review" ON public.tech_daily_review;
CREATE POLICY "fom manage daily_review" ON public.tech_daily_review
  FOR ALL TO authenticated
  USING (
    public.has_role(auth.uid(), 'admin'::public.app_role)
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  )
  WITH CHECK (
    public.has_role(auth.uid(), 'admin'::public.app_role)
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  );

GRANT SELECT, INSERT, UPDATE ON public.tech_daily_review TO authenticated;
```

- [ ] **Step 2: Apply**

Run: `npx supabase db push --linked`
Expected: applies `20260626120200`; no errors.

- [ ] **Step 3: Verify**

`SELECT count(*) FROM public.tech_daily_review;` → `0`.

- [ ] **Step 4: Record version if desynced**

`INSERT INTO supabase_migrations.schema_migrations (version) VALUES ('20260626120200') ON CONFLICT DO NOTHING;`

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260626120200_accountability_daily_review.sql
git commit -m "feat(accountability): tech_daily_review checklist audit table"
```

---

## Task 4: Discipline/talk action log table

**Files:**
- Create: `supabase/migrations/20260626120300_accountability_actions.sql`

- [ ] **Step 1: Write the migration**

```sql
-- Log of the talks and disciplinary actions Charles/Daniel actually take.
-- The Level ladder only SUGGESTS; this records what happened. balance and
-- level at the time are snapshotted for the history record.

CREATE TABLE IF NOT EXISTS public.accountability_actions (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  technician_id   uuid NOT NULL REFERENCES public.technicians(id),
  action_type     text NOT NULL CHECK (action_type IN (
                    'coaching_discussion','improvement_plan','written_warning',
                    'suspension_1day','final_written_warning','suspension_3day',
                    'termination_review','recognition')),
  occurred_on     date NOT NULL,
  notes           text,
  level_at_time   int,
  balance_at_time int,
  created_by      uuid REFERENCES auth.users(id),
  created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_acct_actions_tech
  ON public.accountability_actions(technician_id, occurred_on DESC);

ALTER TABLE public.accountability_actions ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "fom manage acct_actions" ON public.accountability_actions;
CREATE POLICY "fom manage acct_actions" ON public.accountability_actions
  FOR ALL TO authenticated
  USING (
    public.has_role(auth.uid(), 'admin'::public.app_role)
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  )
  WITH CHECK (
    public.has_role(auth.uid(), 'admin'::public.app_role)
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  );

GRANT SELECT, INSERT, UPDATE ON public.accountability_actions TO authenticated;
```

- [ ] **Step 2: Apply**

Run: `npx supabase db push --linked`
Expected: applies `20260626120300`; no errors.

- [ ] **Step 3: Verify**

`SELECT count(*) FROM public.accountability_actions;` → `0`.

- [ ] **Step 4: Record version if desynced**

`INSERT INTO supabase_migrations.schema_migrations (version) VALUES ('20260626120300') ON CONFLICT DO NOTHING;`

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260626120300_accountability_actions.sql
git commit -m "feat(accountability): accountability_actions discipline log table"
```

---

## Task 5: TS types + violation catalog constant

**Files:**
- Create: `src/lib/accountability/types.ts`
- Test: `src/lib/accountability/__tests__/catalog.test.ts`

- [ ] **Step 1: Write the failing test**

```typescript
// src/lib/accountability/__tests__/catalog.test.ts
import { describe, it, expect } from "vitest";
import {
  VIOLATION_CATALOG,
  CHECKLIST_CODES,
  LEVEL_THRESHOLDS,
} from "../types";

describe("violation catalog", () => {
  it("has 30 violation types", () => {
    expect(VIOLATION_CATALOG).toHaveLength(30);
  });

  it("every code is unique", () => {
    const codes = VIOLATION_CATALOG.map((v) => v.code);
    expect(new Set(codes).size).toBe(codes.length);
  });

  it("points are 1/2/3 and match severity", () => {
    for (const v of VIOLATION_CATALOG) {
      expect([1, 2, 3]).toContain(v.points);
      if (v.severity === "minor") expect(v.points).toBe(1);
      if (v.severity === "serious") expect(v.points).toBe(2);
      if (v.severity === "major") expect(v.points).toBe(3);
    }
  });

  it("exposes exactly 9 checklist codes in display order", () => {
    expect(CHECKLIST_CODES).toEqual([
      "present_3_options",
      "document_parts",
      "before_photos",
      "after_photos",
      "safety_inspection",
      "send_invoice",
      "customer_signature",
      "failure_collect_payment",
      "truck_restocked",
    ]);
  });

  it("Level thresholds are 2/4/6/8", () => {
    expect(LEVEL_THRESHOLDS).toEqual([
      { level: 1, points: 2 },
      { level: 2, points: 4 },
      { level: 3, points: 6 },
      { level: 4, points: 8 },
    ]);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test -- src/lib/accountability/__tests__/catalog.test.ts`
Expected: FAIL — cannot resolve `../types`.

- [ ] **Step 3: Write `types.ts`**

```typescript
// src/lib/accountability/types.ts
//
// Types + the violation catalog for the Technician Accountability Program.
// The DB table public.violation_types is the editable source of truth at
// runtime; this constant mirrors the seed for pure-TS logic and tests, and
// is vendored into the accountability-decay edge function.

export type Severity = "minor" | "serious" | "major";
export type ReasonType = "violation" | "decay" | "adjustment";
export type PointSource = "auto" | "checklist" | "manual" | "system";

export interface ViolationType {
  code: string;
  label: string;
  points: 1 | 2 | 3;
  severity: Severity;
  isChecklistItem: boolean;
  autoDetectable: boolean;
}

export interface LedgerEntry {
  id: string;
  technician_id: string;
  points: number; // signed: violations +, decay/adjustment may be -
  reason_type: ReasonType;
  violation_code: string | null;
  severity: Severity | null;
  source: PointSource;
  occurred_on: string; // 'YYYY-MM-DD'
  job_id: string | null;
  note: string | null;
  voided_at: string | null;
}

export type Level = 0 | 1 | 2 | 3 | 4;

export const LEVEL_THRESHOLDS: { level: Exclude<Level, 0>; points: number }[] = [
  { level: 1, points: 2 },
  { level: 2, points: 4 },
  { level: 3, points: 6 },
  { level: 4, points: 8 },
];

export const VIOLATION_CATALOG: ViolationType[] = [
  { code: "present_3_options", label: "Presented 3 options", points: 1, severity: "minor", isChecklistItem: true, autoDetectable: false },
  { code: "document_parts", label: "Parts listed correctly", points: 1, severity: "minor", isChecklistItem: true, autoDetectable: false },
  { code: "before_photos", label: "Before photos uploaded", points: 1, severity: "minor", isChecklistItem: true, autoDetectable: false },
  { code: "after_photos", label: "After photos uploaded", points: 1, severity: "minor", isChecklistItem: true, autoDetectable: false },
  { code: "safety_inspection", label: "Safety inspection completed", points: 2, severity: "serious", isChecklistItem: true, autoDetectable: false },
  { code: "send_invoice", label: "Invoice complete / sent", points: 1, severity: "minor", isChecklistItem: true, autoDetectable: true },
  { code: "customer_signature", label: "Customer signature obtained", points: 1, severity: "minor", isChecklistItem: true, autoDetectable: false },
  { code: "failure_collect_payment", label: "Failure to collect payment", points: 3, severity: "major", isChecklistItem: true, autoDetectable: true },
  { code: "truck_restocked", label: "Truck restocked at end of day", points: 1, severity: "minor", isChecklistItem: true, autoDetectable: false },
  { code: "dirty_work_area", label: "Dirty work area after job", points: 1, severity: "minor", isChecklistItem: false, autoDetectable: false },
  { code: "inform_arrival", label: "Failed to inform customer of arrival", points: 1, severity: "minor", isChecklistItem: false, autoDetectable: true },
  { code: "company_uniform", label: "Not wearing company uniform", points: 1, severity: "minor", isChecklistItem: false, autoDetectable: false },
  { code: "vehicle_upkeep", label: "Failed to upkeep company vehicle", points: 1, severity: "minor", isChecklistItem: false, autoDetectable: false },
  { code: "rev_rise_call", label: "Missing Rev & Rise call", points: 1, severity: "minor", isChecklistItem: false, autoDetectable: false },
  { code: "sensor_alignment_return", label: "Did not explain safety-sensor alignment, return call", points: 1, severity: "minor", isChecklistItem: false, autoDetectable: false },
  { code: "customer_complaint_negligence", label: "Customer complaint from tech negligence", points: 2, severity: "serious", isChecklistItem: false, autoDetectable: false },
  { code: "recommend_safety_issues", label: "Failed to recommend obvious safety issues", points: 2, severity: "serious", isChecklistItem: false, autoDetectable: false },
  { code: "left_job_incomplete", label: "Left job incomplete without approval", points: 2, severity: "serious", isChecklistItem: false, autoDetectable: false },
  { code: "incorrect_diagnosis_return", label: "Incorrect diagnosis resulting in return visit", points: 2, severity: "serious", isChecklistItem: false, autoDetectable: false },
  { code: "no_late_call", label: "Failed to call customer when running late", points: 2, severity: "serious", isChecklistItem: false, autoDetectable: false },
  { code: "missing_tools", label: "Missing required tools", points: 2, severity: "serious", isChecklistItem: false, autoDetectable: false },
  { code: "repeat_after_coaching", label: "Repeated 1-point violation after a coaching discussion", points: 2, severity: "serious", isChecklistItem: false, autoDetectable: false },
  { code: "missed_meeting", label: "Missed company L10 or sales-training meeting", points: 2, severity: "serious", isChecklistItem: false, autoDetectable: false },
  { code: "unsafe_practices", label: "Unsafe work practices", points: 3, severity: "major", isChecklistItem: false, autoDetectable: false },
  { code: "incomplete_arrival_inspection", label: "Incomplete required arrival-inspection fields", points: 3, severity: "major", isChecklistItem: false, autoDetectable: false },
  { code: "quote_dishonesty", label: "Dishonesty on a quote", points: 3, severity: "major", isChecklistItem: false, autoDetectable: false },
  { code: "property_damage", label: "Damaging customer property due to negligence", points: 3, severity: "major", isChecklistItem: false, autoDetectable: false },
  { code: "failure_collect_payment_dup_guard", label: "(unused)", points: 3, severity: "major", isChecklistItem: false, autoDetectable: false },
  { code: "unapproved_absence", label: "Unapproved absence", points: 3, severity: "major", isChecklistItem: false, autoDetectable: false },
  { code: "disrespect_customer", label: "Disrespectful behavior toward a customer", points: 3, severity: "major", isChecklistItem: false, autoDetectable: false },
  { code: "disrespect_peers", label: "Disrespectful behavior toward peers", points: 3, severity: "major", isChecklistItem: false, autoDetectable: false },
].filter((v) => v.code !== "failure_collect_payment_dup_guard") as ViolationType[];

// Checklist codes in display order (9 items).
export const CHECKLIST_CODES: string[] = [
  "present_3_options",
  "document_parts",
  "before_photos",
  "after_photos",
  "safety_inspection",
  "send_invoice",
  "customer_signature",
  "failure_collect_payment",
  "truck_restocked",
];

export function violationByCode(code: string): ViolationType | undefined {
  return VIOLATION_CATALOG.find((v) => v.code === code);
}
```

> Note: the `_dup_guard` placeholder above keeps the array at exactly 30 real entries after `.filter`; if you prefer, just write the 30 entries directly and drop the filter — the test asserts `toHaveLength(30)` either way. **Implementer: write the 30 real entries directly and delete the guard line; it is only here to make the count explicit.**

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test -- src/lib/accountability/__tests__/catalog.test.ts`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/lib/accountability/types.ts src/lib/accountability/__tests__/catalog.test.ts
git commit -m "feat(accountability): TS catalog + types with integrity tests"
```

---

## Task 6: Scoring engine (balance, Level, weekly, decay, reward)

**Files:**
- Create: `src/lib/accountability/engine.ts`
- Test: `src/lib/accountability/__tests__/engine.test.ts`

- [ ] **Step 1: Write the failing test**

```typescript
// src/lib/accountability/__tests__/engine.test.ts
import { describe, it, expect } from "vitest";
import {
  computeBalance,
  computeLevel,
  weeklyPoints,
  daysSinceLastPoint,
  decayDue,
  rewardEligible,
} from "../engine";
import type { LedgerEntry } from "../types";

function entry(p: Partial<LedgerEntry>): LedgerEntry {
  return {
    id: Math.random().toString(36).slice(2),
    technician_id: "t1",
    points: 1,
    reason_type: "violation",
    violation_code: "present_3_options",
    severity: "minor",
    source: "checklist",
    occurred_on: "2026-06-01",
    job_id: null,
    note: null,
    voided_at: null,
    ...p,
  };
}

describe("computeBalance", () => {
  it("sums non-voided points", () => {
    const e = [entry({ points: 2 }), entry({ points: 3 }), entry({ points: 1, voided_at: "2026-06-02T00:00:00Z" })];
    expect(computeBalance(e)).toBe(5);
  });
  it("decay entries reduce the balance", () => {
    const e = [entry({ points: 3 }), entry({ points: -1, reason_type: "decay", violation_code: null, severity: null, source: "system" })];
    expect(computeBalance(e)).toBe(2);
  });
});

describe("computeLevel", () => {
  it.each([
    [0, 0], [1, 0], [2, 1], [3, 1], [4, 2], [5, 2], [6, 3], [7, 3], [8, 4], [12, 4],
  ])("balance %i -> level %i", (bal, lvl) => {
    expect(computeLevel(bal)).toBe(lvl);
  });
});

describe("weeklyPoints (Fri-Thu)", () => {
  it("counts violation points in the current Fri-Thu week only", () => {
    // Thursday 2026-06-25; that week runs Fri 2026-06-19 .. Thu 2026-06-25.
    const today = new Date(2026, 5, 25);
    const e = [
      entry({ points: 2, occurred_on: "2026-06-19" }), // in week
      entry({ points: 1, occurred_on: "2026-06-25" }), // in week
      entry({ points: 3, occurred_on: "2026-06-18" }), // prior week (Thu before)
      entry({ points: 1, occurred_on: "2026-06-20", reason_type: "decay" }), // not a violation
    ];
    expect(weeklyPoints(e, today)).toBe(3);
  });
});

describe("daysSinceLastPoint", () => {
  it("returns days since the most recent non-decay positive entry", () => {
    const today = new Date(2026, 5, 30);
    const e = [entry({ occurred_on: "2026-06-20" }), entry({ occurred_on: "2026-06-10" })];
    expect(daysSinceLastPoint(e, today)).toBe(10);
  });
  it("is null when there are no scoring entries", () => {
    expect(daysSinceLastPoint([], new Date(2026, 5, 30))).toBeNull();
  });
});

describe("decayDue", () => {
  it("is due when balance>0 and 30+ days since last point or decay", () => {
    const today = new Date(2026, 6, 1); // Jul 1
    const e = [entry({ points: 2, occurred_on: "2026-06-01" })]; // 30 days prior
    expect(decayDue(e, today)).toBe(true);
  });
  it("is not due before 30 days", () => {
    const today = new Date(2026, 5, 20);
    const e = [entry({ points: 2, occurred_on: "2026-06-01" })];
    expect(decayDue(e, today)).toBe(false);
  });
  it("is not due at zero balance", () => {
    const today = new Date(2026, 6, 1);
    const e = [entry({ points: 1, occurred_on: "2026-06-01" }), entry({ points: -1, reason_type: "decay", occurred_on: "2026-06-01" })];
    expect(decayDue(e, today)).toBe(false);
  });
  it("clock restarts after a decay entry", () => {
    const today = new Date(2026, 6, 1); // Jul 1
    const e = [
      entry({ points: 3, occurred_on: "2026-05-01" }),
      entry({ points: -1, reason_type: "decay", violation_code: null, severity: null, occurred_on: "2026-06-20" }),
    ];
    // last event = decay on Jun 20 -> only 11 days -> not due
    expect(decayDue(e, today)).toBe(false);
  });
});

describe("rewardEligible", () => {
  it("true when no violation points in the current calendar month", () => {
    const today = new Date(2026, 5, 30); // June
    const e = [entry({ points: 2, occurred_on: "2026-05-15" })]; // May only
    expect(rewardEligible(e, today)).toBe(true);
  });
  it("false when a violation occurred this month", () => {
    const today = new Date(2026, 5, 30);
    const e = [entry({ points: 1, occurred_on: "2026-06-03" })];
    expect(rewardEligible(e, today)).toBe(false);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test -- src/lib/accountability/__tests__/engine.test.ts`
Expected: FAIL — cannot resolve `../engine`.

- [ ] **Step 3: Write `engine.ts`**

```typescript
// src/lib/accountability/engine.ts
//
// Pure scoring logic for the Technician Accountability Program. No I/O.
// Callers pass a tech's ledger entries; these functions derive balance,
// Level, weekly (Fri-Thu) points, decay-due, and reward-eligibility.
//
// Date handling: occurred_on is a 'YYYY-MM-DD' string. parseDateLocal builds
// a local-midnight Date so day math never drifts across timezones (tests pin
// TZ=UTC; production runs in the browser's local zone).

import { startOfWeek } from "date-fns";
import { LEVEL_THRESHOLDS, type LedgerEntry, type Level } from "./types";

export function parseDateLocal(s: string): Date {
  const [y, m, d] = s.split("-").map(Number);
  return new Date(y, m - 1, d);
}

function active(entries: LedgerEntry[]): LedgerEntry[] {
  return entries.filter((e) => !e.voided_at);
}

export function computeBalance(entries: LedgerEntry[]): number {
  return active(entries).reduce((sum, e) => sum + e.points, 0);
}

export function computeLevel(balance: number): Level {
  let level: Level = 0;
  for (const t of LEVEL_THRESHOLDS) {
    if (balance >= t.points) level = t.level;
  }
  return level;
}

// Scoring entries = positive violation entries (what "receiving a point" means).
function scoringEntries(entries: LedgerEntry[]): LedgerEntry[] {
  return active(entries).filter((e) => e.reason_type === "violation" && e.points > 0);
}

export function weeklyPoints(entries: LedgerEntry[], today: Date): number {
  const weekStart = startOfWeek(today, { weekStartsOn: 5 }); // Friday
  return scoringEntries(entries)
    .filter((e) => parseDateLocal(e.occurred_on) >= weekStart)
    .reduce((sum, e) => sum + e.points, 0);
}

const DAY_MS = 24 * 60 * 60 * 1000;

export function daysSinceLastPoint(entries: LedgerEntry[], today: Date): number | null {
  const dates = scoringEntries(entries).map((e) => parseDateLocal(e.occurred_on).getTime());
  if (dates.length === 0) return null;
  const last = Math.max(...dates);
  return Math.floor((startOfDayMs(today) - last) / DAY_MS);
}

function startOfDayMs(d: Date): number {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
}

export function decayDue(entries: LedgerEntry[], today: Date): boolean {
  if (computeBalance(entries) <= 0) return false;
  // Clock reference = most recent scoring point OR most recent decay entry.
  const refs = active(entries)
    .filter((e) => (e.reason_type === "violation" && e.points > 0) || e.reason_type === "decay")
    .map((e) => parseDateLocal(e.occurred_on).getTime());
  if (refs.length === 0) return false;
  const last = Math.max(...refs);
  return Math.floor((startOfDayMs(today) - last) / DAY_MS) >= 30;
}

export function rewardEligible(entries: LedgerEntry[], today: Date): boolean {
  const y = today.getFullYear();
  const m = today.getMonth();
  return !scoringEntries(entries).some((e) => {
    const d = parseDateLocal(e.occurred_on);
    return d.getFullYear() === y && d.getMonth() === m;
  });
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm test -- src/lib/accountability/__tests__/engine.test.ts`
Expected: PASS (all describe blocks green).

- [ ] **Step 5: Commit**

```bash
git add src/lib/accountability/engine.ts src/lib/accountability/__tests__/engine.test.ts
git commit -m "feat(accountability): scoring engine (balance/level/weekly/decay/reward)"
```

---

## Task 7: Vendor the engine for the edge runtime + sync test

**Files:**
- Create: `supabase/functions/accountability-decay/engine-vendored.ts`
- Test: `src/lib/accountability/__tests__/vendored-sync.test.ts`

- [ ] **Step 1: Write the vendored copy**

Create `supabase/functions/accountability-decay/engine-vendored.ts` containing the **concatenated contents of `src/lib/accountability/types.ts` and `src/lib/accountability/engine.ts`**, with one change: replace the `import { startOfWeek } from "date-fns";` line with a Deno-compatible import:

```typescript
// supabase/functions/accountability-decay/engine-vendored.ts
//
// VENDORED copy of src/lib/accountability/{types,engine}.ts for the Deno
// runtime. Edge functions can't import from src/. Keep byte-faithful except
// the date-fns import URL below. The vendored-sync test asserts the same
// named symbols are exported from both sides.

import { startOfWeek } from "https://esm.sh/date-fns@3.6.0";

// ... paste the full bodies of types.ts (types, LEVEL_THRESHOLDS,
//     VIOLATION_CATALOG, CHECKLIST_CODES, violationByCode) and engine.ts
//     (parseDateLocal, computeBalance, computeLevel, weeklyPoints,
//     daysSinceLastPoint, decayDue, rewardEligible) here, minus their own
//     import lines.
```

(Confirm the date-fns version matches `package.json`; the example uses 3.6.0. Use the version in `package.json`'s `date-fns` entry.)

- [ ] **Step 2: Write the sync test**

```typescript
// src/lib/accountability/__tests__/vendored-sync.test.ts
import { describe, it, expect } from "vitest";
import { readFileSync, existsSync } from "node:fs";
import { resolve } from "node:path";

const VENDORED = resolve(
  __dirname,
  "../../../../supabase/functions/accountability-decay/engine-vendored.ts",
);

describe("accountability engine vendored copy", () => {
  it("exists", () => {
    expect(existsSync(VENDORED)).toBe(true);
  });

  it("exports the same named symbols as the src engine", () => {
    const src = readFileSync(VENDORED, "utf8");
    for (const sym of [
      "VIOLATION_CATALOG",
      "CHECKLIST_CODES",
      "LEVEL_THRESHOLDS",
      "computeBalance",
      "computeLevel",
      "weeklyPoints",
      "daysSinceLastPoint",
      "decayDue",
      "rewardEligible",
      "parseDateLocal",
    ]) {
      expect(src).toContain(`export function ${sym}`);
      // constants use `export const`
    }
  });
});
```

> Adjust the relative depth of `VENDORED` if needed so it resolves to `supabase/functions/accountability-decay/engine-vendored.ts` from the test file. Confirm with `node -e "console.log(require('path').resolve(...))"` or by running the test.

- [ ] **Step 3: Run the sync test**

Run: `npm test -- src/lib/accountability/__tests__/vendored-sync.test.ts`
Expected: PASS once the vendored file exists and contains the symbols. (The symbol check for `const` vs `function`: update the assertion to check `export const ${sym}` for `VIOLATION_CATALOG`, `CHECKLIST_CODES`, `LEVEL_THRESHOLDS` and `export function ${sym}` for the rest.)

- [ ] **Step 4: Commit**

```bash
git add supabase/functions/accountability-decay/engine-vendored.ts src/lib/accountability/__tests__/vendored-sync.test.ts
git commit -m "feat(accountability): vendor engine for edge runtime + sync test"
```

---

## Task 8: Decay edge function

**Files:**
- Create: `supabase/functions/accountability-decay/index.ts`
- Modify: `supabase/config.toml`

- [ ] **Step 1: Write the function**

```typescript
// supabase/functions/accountability-decay/index.ts
//
// Daily decay runner. For every active tech with a positive balance whose
// last scoring point (or last decay) is >= 30 days old, insert a -1 'decay'
// ledger entry dated today. Idempotent within a day: after inserting, the
// clock reference becomes today, so a second run won't re-fire.

import { createClient } from "https://esm.sh/@supabase/supabase-js@2.39.3";
import { computeBalance, decayDue, type LedgerEntry } from "./engine-vendored.ts";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

function todayIso(): string {
  // YYYY-MM-DD in America/Chicago (Twins' operating zone).
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: "America/Chicago",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date());
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response(null, { headers: corsHeaders });

  const supabase = createClient(
    Deno.env.get("SUPABASE_URL")!,
    Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!,
  );

  try {
    const today = new Date();
    const todayStr = todayIso();

    // Pull all non-voided ledger rows; group by tech.
    const { data: rows, error } = await supabase
      .from("accountability_points")
      .select("id, technician_id, points, reason_type, violation_code, severity, source, occurred_on, job_id, note, voided_at")
      .is("voided_at", null);
    if (error) throw new Error(`load ledger: ${error.message}`);

    const byTech = new Map<string, LedgerEntry[]>();
    for (const r of (rows ?? []) as LedgerEntry[]) {
      const arr = byTech.get(r.technician_id) ?? [];
      arr.push(r);
      byTech.set(r.technician_id, arr);
    }

    const inserts: Array<Record<string, unknown>> = [];
    for (const [techId, entries] of byTech) {
      if (computeBalance(entries) > 0 && decayDue(entries, today)) {
        inserts.push({
          technician_id: techId,
          points: -1,
          reason_type: "decay",
          source: "system",
          occurred_on: todayStr,
          note: "Auto decay: 30 consecutive days without a point",
        });
      }
    }

    if (inserts.length > 0) {
      const { error: insErr } = await supabase.from("accountability_points").insert(inserts);
      if (insErr) throw new Error(`insert decay: ${insErr.message}`);
    }

    return new Response(JSON.stringify({ ok: true, decayed: inserts.length }), {
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  } catch (e) {
    console.error("accountability-decay error:", e);
    return new Response(JSON.stringify({ ok: false, error: String(e) }), {
      status: 500,
      headers: { ...corsHeaders, "Content-Type": "application/json" },
    });
  }
});
```

- [ ] **Step 2: Add the config.toml entry**

Append to `supabase/config.toml`:

```toml
[functions.accountability-decay]
verify_jwt = false
```

- [ ] **Step 3: Deploy the function**

Run: `npx supabase functions deploy accountability-decay`
Expected: deploy succeeds; function listed for project jwrpj.

- [ ] **Step 4: Smoke test the deployed function**

Run (manual invoke; safe — only inserts decay when a tech is genuinely due, and the table is empty so 0 inserts):
```bash
curl -s -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/accountability-decay" \
  -H "Content-Type: application/json" -d '{}'
```
Expected: `{"ok":true,"decayed":0}`.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/accountability-decay/index.ts supabase/config.toml
git commit -m "feat(accountability): daily decay edge function"
```

---

## Task 9: Decay cron registration

**Files:**
- Create: `supabase/migrations/20260626120400_accountability_decay_cron.sql`

- [ ] **Step 1: Write the migration**

```sql
-- Run the decay function once daily at 08:00 UTC (~02:00/03:00 Central).
-- pg_net posts to the edge function; verify_jwt=false so no auth header
-- is required. Idempotent within a day via the engine's clock reference.

BEGIN;
CREATE EXTENSION IF NOT EXISTS pg_cron;
CREATE EXTENSION IF NOT EXISTS pg_net;

DO $$
BEGIN
  PERFORM cron.unschedule(jobid) FROM cron.job
   WHERE jobname = 'accountability-decay';
END $$;

SELECT cron.schedule(
  'accountability-decay',
  '0 8 * * *',
  $cron$
    SELECT net.http_post(
      url := 'https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/accountability-decay',
      headers := jsonb_build_object('Content-Type', 'application/json'),
      body := '{}'::jsonb
    );
  $cron$
);

COMMIT;
```

- [ ] **Step 2: Apply**

Run: `npx supabase db push --linked`
Expected: applies `20260626120400`; no errors.

- [ ] **Step 3: Verify the job is registered**

Via SQL editor / MCP: `SELECT jobname, schedule FROM cron.job WHERE jobname = 'accountability-decay';`
Expected: one row, schedule `0 8 * * *`.

- [ ] **Step 4: Record version if desynced**

`INSERT INTO supabase_migrations.schema_migrations (version) VALUES ('20260626120400') ON CONFLICT DO NOTHING;`

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260626120400_accountability_decay_cron.sql
git commit -m "feat(accountability): daily decay pg_cron job"
```

---

## Task 10: Full-suite verification

- [ ] **Step 1: Run the full test suite**

Run: `npm test`
Expected: all accountability tests pass; no pre-existing tests broken (especially `src/lib/alerts/__tests__/`).

- [ ] **Step 2: Typecheck / lint as the repo defines**

Run: `npm run build` (or the repo's typecheck script if separate)
Expected: no TypeScript errors from the new files.

- [ ] **Step 3: Final commit if anything was fixed**

```bash
git add -A
git commit -m "test(accountability): green full suite for Plan 1"
```

---

## Self-Review (completed during planning)

- **Spec coverage:** catalog (Task 1, 5), weighted points (catalog), ledger as single signed source (Task 2, 6), daily-review audit table (Task 3), action log (Task 4), cumulative balance + Level 2/4/6/8 (Task 6), Fri-Thu weekly lens (Task 6), 30-day decay + clock restart (Task 6, 8, 9), reward eligibility (Task 6), reversibility via soft-void (Task 2), RLS to admin+field_supervisor (Tasks 2-4), Charles already field_supervisor (no new role). **Deferred to Plan 2/3 (noted in scope):** daily-checklist UI + add-violation + classify-callback + log-action + aggregate table (Plan 2); digest auto-detection → ledger mapping + email Accountability section + callback-rate display (Plan 3). Job-linked co-tech attribution applies when Plan 3 writes auto entries; manual entries set technician_id directly.
- **Placeholders:** none left except the explicit `_dup_guard` teaching note in Task 5, which instructs the implementer to write 30 entries directly.
- **Type consistency:** `LedgerEntry`, `ViolationType`, `Level`, `LEVEL_THRESHOLDS`, `CHECKLIST_CODES`, and the engine function names are identical across Tasks 5, 6, 7, 8.

## Open confirmations carried into Plan 2/3
- Confirm `failure_collect_payment = 3` and `incomplete_arrival_inspection = 3` with Charles (catalog is editable; change the seed + `VIOLATION_CATALOG` together if adjusted).
- Verify which HCP fields back the checklist auto-prefills (photos/attachments, signature, line items) before Plan 2 relies on them; only `send_invoice` and `failure_collect_payment` are confirmed auto-detectable today.
- Decide in Plan 3 whether `supervisor_alerts` is subsumed by the ledger or kept as the operational "open issues" view feeding auto entries.
