# HCP Workflow Buttons Checklist Item Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an auto-detected 1-point minor checklist item "Pressed Start, OMW, and Finish in HCP" (No when a job misses any of `started_at`/`on_my_way_at`/`completed_at`), and retire the standalone `inform_arrival` OMW item so a missed OMW counts once.

**Architecture:** New catalog row (DB + hardcoded mirrors), a new `workflowButtonsPressed` prefill signal computed from HCP `work_timestamps`, and the old `inform_arrival` deactivated in the DB (kept in code/hardcoded catalogs as harmless dead pre-fill + historical label). Plus docs. No engine/points/ladder changes.

**Tech Stack:** Supabase Postgres (jwrpj), TypeScript, vitest, static HTML manuals.

---

## Pre-flight

- [ ] **Branch**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git checkout -b feat/hcp-workflow-buttons
git status
```

---

## Task 1: Database — new item + retire OMW

**Files:**
- Create: `supabase/migrations/20260708130000_violation_types_hcp_workflow_buttons.sql`

- [ ] **Step 1: Write the migration**

Create `supabase/migrations/20260708130000_violation_types_hcp_workflow_buttons.sql`:

```sql
-- New 1-point minor AUTO-detected checklist item: did the tech press the Start,
-- OMW, and Finish buttons in HCP? Detected from work_timestamps
-- (started_at / on_my_way_at / completed_at). Missing any => 1 point.
INSERT INTO public.violation_types
  (code, label, points, severity, is_checklist_item, auto_detectable, scope, sort_order, active)
VALUES
  ('hcp_workflow_buttons', 'Pressed Start, OMW, and Finish in HCP', 1, 'minor', true, true, 'ticket', 45, true)
ON CONFLICT (code) DO NOTHING;

-- OMW is now folded into hcp_workflow_buttons; retire the standalone item so a
-- missed OMW is not double-counted. Historical points keep their label.
UPDATE public.violation_types SET active = false WHERE code = 'inform_arrival';
```

- [ ] **Step 2: Apply to jwrpj**

Supabase MCP `apply_migration`: `project_id` = `jwrpjuqaynownxaoeayi`, `name` = `violation_types_hcp_workflow_buttons`, `query` = the SQL above.

- [ ] **Step 3: Record the version**

MCP `execute_sql`: `INSERT INTO supabase_migrations.schema_migrations (version, name) VALUES ('20260708130000', 'violation_types_hcp_workflow_buttons') ON CONFLICT (version) DO NOTHING;`

- [ ] **Step 4: Verify**

MCP `execute_sql`:

```sql
SELECT code, points, severity, is_checklist_item, auto_detectable, scope, sort_order, active
FROM public.violation_types WHERE code IN ('hcp_workflow_buttons','inform_arrival') ORDER BY code;
```
Expected: `hcp_workflow_buttons` active=true (points=1, minor, is_checklist_item=true, auto_detectable=true, scope=ticket, sort_order=45); `inform_arrival` active=false.

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260708130000_violation_types_hcp_workflow_buttons.sql
git commit -m "feat(point-system): add hcp_workflow_buttons item, retire inform_arrival (DB)"
```

---

## Task 2: Prefill signal + mapping (TDD)

**Files:**
- Modify: `src/lib/accountability/prefill.ts`
- Modify: `src/lib/accountability/__tests__/prefill.test.ts`

- [ ] **Step 1: Update the test first (failing)**

In `src/lib/accountability/__tests__/prefill.test.ts`, add the new signal to the `base` object. Change:

```ts
const base: TicketSignals = {
  optionCount: 3,
  invoiceSent: true,
  paymentCollected: true,
  informedArrival: true,
};
```

to:

```ts
const base: TicketSignals = {
  optionCount: 3,
  invoiceSent: true,
  paymentCollected: true,
  informedArrival: true,
  workflowButtonsPressed: true,
};
```

In the "all compliant -> all yes" test, add one assertion after the `inform_arrival` line:

```ts
    expect(p.hcp_workflow_buttons).toBe("yes");
```

Then add a new test at the end of the `describe` block (before its closing `});`):

```ts
  it("missing a workflow button -> hcp_workflow_buttons no", () => {
    expect(prefillForTicket({ ...base, workflowButtonsPressed: false }).hcp_workflow_buttons).toBe("no");
  });
```

- [ ] **Step 2: Run — expect FAIL**

Run: `npx vitest run src/lib/accountability/__tests__/prefill.test.ts`
Expected: FAIL — `TicketSignals` has no `workflowButtonsPressed`, and `prefillForTicket` returns no `hcp_workflow_buttons`.

- [ ] **Step 3: Implement in `prefill.ts`**

Add the field to `TicketSignals` (after `informedArrival: boolean;`):

```ts
  informedArrival: boolean;
  workflowButtonsPressed: boolean;
```

Add the code to `AUTO_TICKET_CODES` (after `"inform_arrival",`):

```ts
  "inform_arrival",
  "hcp_workflow_buttons",
```

Add the mapping in `prefillForTicket`'s returned object (after the `inform_arrival:` line):

```ts
    inform_arrival: s.informedArrival ? "yes" : "no",
    hcp_workflow_buttons: s.workflowButtonsPressed ? "yes" : "no",
```

- [ ] **Step 4: Run — expect PASS**

Run: `npx vitest run src/lib/accountability/__tests__/prefill.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/lib/accountability/prefill.ts src/lib/accountability/__tests__/prefill.test.ts
git commit -m "feat(point-system): hcp_workflow_buttons prefill signal"
```

---

## Task 3: Compute the signal in the review hook

**Files:**
- Modify: `src/hooks/useTechDayReview.ts`

- [ ] **Step 1: Extend the `HcpData.work_timestamps` type**

Find (around line 158):

```ts
  work_timestamps?: { on_my_way_at?: string | null };
```

Replace with:

```ts
  work_timestamps?: { on_my_way_at?: string | null; started_at?: string | null; completed_at?: string | null };
```

- [ ] **Step 2: Add the signal to the JOB ticket**

Find the job ticket's `signals` block:

```ts
      signals: {
        optionCount,
        invoiceSent,
        paymentCollected,
        informedArrival: !!hcp?.work_timestamps?.on_my_way_at,
      },
```

Replace with:

```ts
      signals: {
        optionCount,
        invoiceSent,
        paymentCollected,
        informedArrival: !!hcp?.work_timestamps?.on_my_way_at,
        // "No" if the tech skipped any of Start / OMW / Finish in HCP.
        workflowButtonsPressed: !!(
          hcp?.work_timestamps?.started_at &&
          hcp?.work_timestamps?.on_my_way_at &&
          hcp?.work_timestamps?.completed_at
        ),
      },
```

- [ ] **Step 3: Add the signal to the ESTIMATE ticket (always compliant)**

Find the estimate ticket's `signals` block:

```ts
        invoiceSent: true,
        paymentCollected: true,
        informedArrival: true,
      },
```

Replace with:

```ts
        invoiceSent: true,
        paymentCollected: true,
        informedArrival: true,
        workflowButtonsPressed: true,
      },
```

- [ ] **Step 4: Type-check**

Run: `npx tsc --noEmit`
Expected: 0 errors (the `TicketSignals` shape from Task 2 now has `workflowButtonsPressed`, satisfied by both ticket blocks).

- [ ] **Step 5: Commit**

```bash
git add src/hooks/useTechDayReview.ts
git commit -m "feat(point-system): compute workflowButtonsPressed from HCP work_timestamps"
```

---

## Task 4: Hardcoded catalog mirror + test (TDD)

**Files:**
- Modify: `src/lib/accountability/__tests__/catalog.test.ts`
- Modify: `src/lib/accountability/types.ts`

- [ ] **Step 1: Update the test first (failing)**

In `src/lib/accountability/__tests__/catalog.test.ts`:

Change the count from 31 to 32:

```ts
  it("has 31 violation types", () => {
    expect(VIOLATION_CATALOG).toHaveLength(31);
  });
```

to:

```ts
  it("has 32 violation types", () => {
    expect(VIOLATION_CATALOG).toHaveLength(32);
  });
```

Change the checklist-codes assertion to 11 codes with `hcp_workflow_buttons` after `after_photos`:

```ts
  it("exposes exactly 11 checklist codes in display order", () => {
    expect(CHECKLIST_CODES).toEqual([
      "present_3_options","plaud_recording","document_parts","before_photos","after_photos",
      "hcp_workflow_buttons","safety_inspection","send_invoice","customer_signature",
      "failure_collect_payment","truck_restocked",
    ]);
  });
```

- [ ] **Step 2: Run — expect FAIL**

Run: `npx vitest run src/lib/accountability/__tests__/catalog.test.ts`
Expected: FAIL (length 31≠32, 10≠11 codes).

- [ ] **Step 3: Add to `VIOLATION_CATALOG` in `types.ts`**

Find the `after_photos` entry in `VIOLATION_CATALOG`:

```ts
  {
    code: "after_photos",
    label: "After photos uploaded",
    points: 1,
    severity: "minor",
    isChecklistItem: true,
    autoDetectable: false,
  },
```

Insert immediately after it:

```ts
  {
    code: "hcp_workflow_buttons",
    label: "Pressed Start, OMW, and Finish in HCP",
    points: 1,
    severity: "minor",
    isChecklistItem: true,
    autoDetectable: true,
  },
```

Update the count comment `// Checklist items (10)` to `// Checklist items (11)`.

- [ ] **Step 4: Add to `CHECKLIST_CODES` in `types.ts`**

Find:

```ts
  "after_photos",
  "safety_inspection",
```

Change to:

```ts
  "after_photos",
  "hcp_workflow_buttons",
  "safety_inspection",
```

- [ ] **Step 5: Run — expect PASS**

Run: `npx vitest run src/lib/accountability/__tests__/catalog.test.ts`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/lib/accountability/types.ts src/lib/accountability/__tests__/catalog.test.ts
git commit -m "feat(point-system): add hcp_workflow_buttons to hardcoded catalog + CHECKLIST_CODES"
```

---

## Task 5: Vendored catalog copies

**Files:**
- Modify: `supabase/functions/daily-supervisor-digest/accountability-vendored.ts`
- Modify: `supabase/functions/accountability-decay/engine-vendored.ts`

- [ ] **Step 1: Edit the digest's vendored copy**

In `supabase/functions/daily-supervisor-digest/accountability-vendored.ts`:
- Insert the `hcp_workflow_buttons` entry (the same 7-line block from Task 4 Step 3) immediately after the `after_photos` entry in `VIOLATION_CATALOG`.
- In `CHECKLIST_CODES`, insert `  "hcp_workflow_buttons",` immediately after `  "after_photos",`.
- Update the `// Checklist items (10)` comment to `(11)`.

- [ ] **Step 2: Edit the decay engine's vendored copy**

Repeat the exact same three edits in `supabase/functions/accountability-decay/engine-vendored.ts`.

- [ ] **Step 3: Run the sync tests**

Run: `npx vitest run src/lib/accountability/__tests__/vendored-sync.test.ts src/lib/accountability/__tests__/digest-vendored-sync.test.ts`
Expected: PASS (symbols present; edits additive).

- [ ] **Step 4: Commit**

```bash
git add supabase/functions/daily-supervisor-digest/accountability-vendored.ts supabase/functions/accountability-decay/engine-vendored.ts
git commit -m "feat(point-system): mirror hcp_workflow_buttons into vendored catalogs"
```

---

## Task 6: Documentation

**Files:**
- Modify: `public/point-system.html`
- Modify: `public/point-system-guide.html`

- [ ] **Step 1: Point-system.html — retire OMW line, add the new violation to the Minor list**

Find:

```html
          <li>Failed to inform customer of arrival</li>
```

Replace with:

```html
          <li>Did not press the Start, OMW, or Finish buttons in HCP</li>
```

- [ ] **Step 2: Point-system.html — add to the Daily Checklist**

Find:

```html
          <li>Recorded on-site conversation with Plaud AI (1)</li>
```

Insert immediately after it:

```html
          <li>Pressed Start, OMW, and Finish in HCP (1)</li>
```

- [ ] **Step 3: FOM guide — explain the item**

In `public/point-system-guide.html`, find:

```html
      <div class="h">Plaud AI recording</div>
```

Insert this card immediately BEFORE that line:

```html
      <div class="h">HCP workflow buttons</div>
      <div class="card">
        <p style="margin:0"><b>Pressed Start, OMW, and Finish in HCP</b> is detected automatically from the job's Housecall Pro timestamps. It pre-fills <b>No</b> (1 point) when the tech skipped any of the Start, On-My-Way, or Finish buttons, and Yes when all three were pressed. On-My-Way (the customer arrival notification) is now part of this one item.</p>
      </div>
```

- [ ] **Step 4: Commit**

```bash
git add public/point-system.html public/point-system-guide.html
git commit -m "docs(point-system): document the HCP workflow-buttons item; retire OMW line"
```

---

## Task 7: Full verification

- [ ] **Step 1: Accountability tests**

Run: `npx vitest run src/lib/accountability`
Expected: all pass (catalog: 32 types / 11 checklist codes; prefill: new case; vendored-sync green).

- [ ] **Step 2: Type-check + build**

Run: `npx tsc --noEmit && npx vite build`
Expected: 0 type errors; build succeeds.

- [ ] **Step 3: Manual smoke**

Start the dev server, sign in as admin, open `/admin/point-system`:
- Rules tab → "Edit point values" → "Pressed Start, OMW, and Finish in HCP" appears under Minor; "Failed to inform customer of arrival" no longer appears.
- Overview → a tech row → "Review day" on a completed job → the "Pressed Start, OMW, and Finish in HCP" row appears; a job missing a timestamp pre-fills **No** (adds 1 pt in the preview); a fully-stamped job pre-fills **Yes**; the old "Notified customer of arrival" row is gone.
- `/point-system.html` and `/point-system-guide.html`: the new item shows; OMW line replaced.

---

## Self-review notes (spec coverage)

- Spec §1 new item + retire OMW → Task 1 (DB) + Task 4/5 (hardcoded).
- Spec §2 auto-detection → Task 2 (prefill signal) + Task 3 (compute from work_timestamps, estimate default, HcpData type).
- Spec §3 catalog sync + tests → Task 4 (types.ts + catalog.test) + Task 5 (vendored); prefill.test in Task 2.
- Spec §4 docs → Task 6 (both manuals).
- Spec §6 rollout/verification → Task 1 (apply/record/verify) + Task 7.
- `inform_arrival` intentionally kept in code (`AUTO_TICKET_CODES`/`prefill`/`informedArrival` signal) and hardcoded catalogs — deactivated only in the DB, per spec §2. `auto-mapping.ts` unchanged (unused at runtime).
