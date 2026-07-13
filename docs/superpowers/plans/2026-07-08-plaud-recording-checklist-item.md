# Plaud AI Recording Checklist Item Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a 1-point minor, ticket-scoped, manually-marked checklist item — "Recorded on-site conversation (Plaud AI)" — to the accountability point system and both manuals.

**Architecture:** The daily review renders ticket-scoped catalog items straight from the `violation_types` database table, and non-auto-detectable items default to N/A — so this is a pure catalog addition (DB row + its hardcoded mirrors) plus documentation. No engine, prefill, points, or ladder changes.

**Tech Stack:** Supabase Postgres (jwrpj), TypeScript, vitest, static HTML manuals.

---

## Pre-flight

- [ ] **Create a branch**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git checkout -b feat/plaud-recording-checklist
git status   # expect clean
```

---

## Task 1: Catalog row in the database

**Files:**
- Create: `supabase/migrations/20260708120000_violation_types_plaud_recording.sql`

- [ ] **Step 1: Write the migration**

Create `supabase/migrations/20260708120000_violation_types_plaud_recording.sql`:

```sql
-- New 1-point minor checklist item: did the tech record the on-site customer
-- conversation with the Plaud AI recorder? Manual (not auto-detectable) — the
-- review defaults it to N/A; Charles marks Yes/No on conversation visits.
INSERT INTO public.violation_types
  (code, label, points, severity, is_checklist_item, auto_detectable, scope, sort_order, active)
VALUES
  ('plaud_recording', 'Recorded on-site conversation (Plaud AI)', 1, 'minor', true, false, 'ticket', 15, true)
ON CONFLICT (code) DO NOTHING;
```

- [ ] **Step 2: Apply to jwrpj**

Apply via the Supabase MCP `apply_migration` tool: `project_id` = the jwrpj project (`jwrpjuqaynownxaoeayi`), `name` = `violation_types_plaud_recording`, `query` = the SQL above.

- [ ] **Step 3: Record the migration version (history desync workaround)**

Via Supabase MCP `execute_sql`:

```sql
INSERT INTO supabase_migrations.schema_migrations (version, name)
VALUES ('20260708120000', 'violation_types_plaud_recording')
ON CONFLICT (version) DO NOTHING;
```

- [ ] **Step 4: Verify the row**

Via `execute_sql`:

```sql
SELECT code, label, points, severity, is_checklist_item, auto_detectable, scope, sort_order, active
FROM public.violation_types WHERE code = 'plaud_recording';
```
Expected: one row, `points=1`, `severity=minor`, `is_checklist_item=true`, `auto_detectable=false`, `scope=ticket`, `sort_order=15`, `active=true`.

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260708120000_violation_types_plaud_recording.sql
git commit -m "feat(point-system): add plaud_recording checklist item to catalog (DB)"
```

---

## Task 2: Hardcoded catalog mirror + test (TDD)

**Files:**
- Modify: `src/lib/accountability/__tests__/catalog.test.ts`
- Modify: `src/lib/accountability/types.ts`

- [ ] **Step 1: Update the test to the new expected state (write the failing test)**

In `src/lib/accountability/__tests__/catalog.test.ts`, change the count assertion. Replace:

```ts
  it("has 30 violation types", () => {
    expect(VIOLATION_CATALOG).toHaveLength(30);
  });
```

with:

```ts
  it("has 31 violation types", () => {
    expect(VIOLATION_CATALOG).toHaveLength(31);
  });
```

Then change the checklist-codes assertion. Replace:

```ts
  it("exposes exactly 9 checklist codes in display order", () => {
    expect(CHECKLIST_CODES).toEqual([
      "present_3_options","document_parts","before_photos","after_photos",
      "safety_inspection","send_invoice","customer_signature",
      "failure_collect_payment","truck_restocked",
    ]);
  });
```

with:

```ts
  it("exposes exactly 10 checklist codes in display order", () => {
    expect(CHECKLIST_CODES).toEqual([
      "present_3_options","plaud_recording","document_parts","before_photos","after_photos",
      "safety_inspection","send_invoice","customer_signature",
      "failure_collect_payment","truck_restocked",
    ]);
  });
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run src/lib/accountability/__tests__/catalog.test.ts`
Expected: FAIL — `VIOLATION_CATALOG` has length 30 (not 31) and `CHECKLIST_CODES` has 9 codes.

- [ ] **Step 3: Add the entry to `VIOLATION_CATALOG` in `types.ts`**

In `src/lib/accountability/types.ts`, find the `present_3_options` entry (the first entry in `VIOLATION_CATALOG`):

```ts
  {
    code: "present_3_options",
    label: "Presented 3 options",
    points: 1,
    severity: "minor",
    isChecklistItem: true,
    autoDetectable: false,
  },
```

Insert the new entry immediately after it:

```ts
  {
    code: "plaud_recording",
    label: "Recorded on-site conversation (Plaud AI)",
    points: 1,
    severity: "minor",
    isChecklistItem: true,
    autoDetectable: false,
  },
```

Also update the count comment: change `// Checklist items (9)` to `// Checklist items (10)`.

- [ ] **Step 4: Add the code to `CHECKLIST_CODES` in `types.ts`**

Find:

```ts
export const CHECKLIST_CODES = [
  "present_3_options",
  "document_parts",
```

Change to:

```ts
export const CHECKLIST_CODES = [
  "present_3_options",
  "plaud_recording",
  "document_parts",
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `npx vitest run src/lib/accountability/__tests__/catalog.test.ts`
Expected: PASS (all cases, including the new 31-count and 10-code checks).

- [ ] **Step 6: Commit**

```bash
git add src/lib/accountability/types.ts src/lib/accountability/__tests__/catalog.test.ts
git commit -m "feat(point-system): add plaud_recording to hardcoded catalog + CHECKLIST_CODES"
```

---

## Task 3: Vendored catalog copies (digest + decay engines)

Both edge-function engines carry their own copy of the catalog. Keep them in sync so the digest/decay label lookups match the DB. The `vendored-sync` tests only check that the symbols exist (not byte-identity), so these edits are additive and safe.

**Files:**
- Modify: `supabase/functions/daily-supervisor-digest/accountability-vendored.ts`
- Modify: `supabase/functions/accountability-decay/engine-vendored.ts`

- [ ] **Step 1: Add the entry + code to the digest's vendored copy**

In `supabase/functions/daily-supervisor-digest/accountability-vendored.ts`, find the `present_3_options` entry in `VIOLATION_CATALOG`:

```ts
  {
    code: "present_3_options",
    label: "Presented 3 options",
    points: 1,
    severity: "minor",
    isChecklistItem: true,
    autoDetectable: false,
  },
```

Insert immediately after it:

```ts
  {
    code: "plaud_recording",
    label: "Recorded on-site conversation (Plaud AI)",
    points: 1,
    severity: "minor",
    isChecklistItem: true,
    autoDetectable: false,
  },
```

Then find its `CHECKLIST_CODES`:

```ts
export const CHECKLIST_CODES = [
  "present_3_options",
  "document_parts",
```

Change to:

```ts
export const CHECKLIST_CODES = [
  "present_3_options",
  "plaud_recording",
  "document_parts",
```

- [ ] **Step 2: Add the entry + code to the decay engine's vendored copy**

Repeat the exact same two edits in `supabase/functions/accountability-decay/engine-vendored.ts` (same `present_3_options` entry to anchor on, same `CHECKLIST_CODES` block).

- [ ] **Step 3: Run the vendored-sync tests**

Run: `npx vitest run src/lib/accountability/__tests__/vendored-sync.test.ts src/lib/accountability/__tests__/digest-vendored-sync.test.ts`
Expected: PASS (symbols still present; edits are additive).

- [ ] **Step 4: Commit**

```bash
git add supabase/functions/daily-supervisor-digest/accountability-vendored.ts supabase/functions/accountability-decay/engine-vendored.ts
git commit -m "feat(point-system): mirror plaud_recording into vendored catalogs"
```

---

## Task 4: Documentation (both manuals)

**Files:**
- Modify: `public/point-system.html`
- Modify: `public/point-system-guide.html`

- [ ] **Step 1: Add to the point-system.html "Minor · 1 point" list**

In `public/point-system.html`, find:

```html
          <li>Failed to present 3 repair options</li>
```

Insert immediately after it (the Minor list phrases items as the violation):

```html
          <li>Did not record the on-site customer conversation with Plaud AI</li>
```

- [ ] **Step 2: Add to the point-system.html "Daily Checklist" list**

In `public/point-system.html`, find:

```html
          <li>Presented 3 options (1)</li>
```

Insert immediately after it (the Daily Checklist phrases items as the good behavior + points):

```html
          <li>Recorded on-site conversation with Plaud AI (1)</li>
```

- [ ] **Step 3: Add a line to the FOM guide checklist explanation**

In `public/point-system-guide.html`, find the "Classify callback" heading block that starts:

```html
      <div class="h">Classify callback — what the three choices mean</div>
```

Insert this card immediately BEFORE that `<div class="h">` line:

```html
      <div class="h">Plaud AI recording</div>
      <div class="card">
        <p style="margin:0">On sales, estimate, and diagnostic visits the tech should record the customer conversation with the Plaud AI recorder. In a day's <span class="btn">Review day</span>, mark <b>Recorded on-site conversation (Plaud AI)</b> as Yes when they recorded it, No when there was a conversation and they did not (1 point), and leave it <b>N/A</b> on quick jobs with no real conversation.</p>
      </div>
```

- [ ] **Step 4: Commit**

```bash
git add public/point-system.html public/point-system-guide.html
git commit -m "docs(point-system): document the Plaud AI recording checklist item"
```

---

## Task 5: Full verification

- [ ] **Step 1: Run the accountability test suite**

Run: `npx vitest run src/lib/accountability`
Expected: all pass, including `catalog.test.ts` (31 types, 10 checklist codes) and both vendored-sync tests.

- [ ] **Step 2: Type-check + build**

Run: `npx tsc --noEmit && npx vite build`
Expected: 0 type errors; build succeeds.

- [ ] **Step 3: Manual smoke (dev server)**

Start the dev server, sign in as admin, open `/admin/point-system`:
- Rules tab → "Edit point values" → confirm "Recorded on-site conversation (Plaud AI)" appears under Minor (1 pt).
- Overview → a tech's row → "Review day" on a ticket → confirm the "Recorded on-site conversation (Plaud AI)" row appears (right after "Presented 3 options"), defaults to **N/A**, and setting it to **No** adds 1 point to the "points will post" preview.

- [ ] **Step 4: Verify the docs render**

Open `/point-system.html` and `/point-system-guide.html` (served from `public/`): the new item appears in the Minor list, the Daily Checklist, and the FOM guide.

---

## Self-review notes (spec coverage)

- Spec §1 catalog item → Task 1 (DB) + Task 2 (hardcoded) + Task 3 (vendored).
- Spec §2 no new logic (N/A default) → verified in Task 5 Step 3, no prefill/engine files touched.
- Spec §3 two synchronized catalog locations → Tasks 2 + 3; `catalog.test.ts` updated in Task 2.
- Spec §4 documentation → Task 4 (both manuals).
- Spec §6 rollout/verification → Task 1 (apply + record + verify) and Task 5.
