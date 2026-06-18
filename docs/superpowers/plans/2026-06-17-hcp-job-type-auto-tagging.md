# HCP Job Type Auto-Tagging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically classify every completed Housecall Pro job into a Job Type from its line items and write that back to HCP, surfacing only the ambiguous tail for human review.

**Architecture:** A pure, fully unit-tested classifier (categorize line items → apply a deterministic decision tree) lives in `supabase/functions/_shared/job-type/`. It is shared by two callers: the real-time `hcp-webhook` (tags jobs as they complete) and a one-time backfill edge function. An explicit `part_categories` table (seeded from the price sheet) drives categorization — unrecognized parts make a job unclassifiable rather than guessed. Results land in a `job_type_classifications` table that a new `/admin/job-type-review` dashboard tab reads. Writes only fill HCP Job Types that are currently empty; disagreements with existing tags are flagged, never overwritten.

**Tech Stack:** TypeScript (Deno edge functions), Vitest (`npx vitest run`), Supabase/Postgres migrations, React + React Query + Vite for the dashboard tab. Repo: `/Users/daniel/twins-dashboard/twins-dash`.

**Source spec:** `docs/superpowers/specs/2026-06-17-hcp-job-type-auto-tagging-design.md`

---

## File Structure

Created:
- `supabase/functions/_shared/job-type/types.ts` — `PartCategory`, `JobType`, shared interfaces.
- `supabase/functions/_shared/job-type/categorize.ts` — line items + part map → categories/total/labor flag.
- `supabase/functions/_shared/job-type/classify.ts` — the decision tree (pure).
- `supabase/functions/_shared/job-type/run.ts` — orchestrator: load map, categorize, classify, decide write-vs-flag.
- `supabase/functions/_shared/job-type/__tests__/categorize.test.ts`
- `supabase/functions/_shared/job-type/__tests__/classify.test.ts`
- `supabase/functions/_shared/job-type/__tests__/run.test.ts`
- `supabase/functions/classify-job-types-backfill/index.ts` — one-time backfill.
- `supabase/migrations/20260617130000_part_categories.sql`
- `supabase/migrations/20260617130001_job_type_classifications.sql`
- `src/hooks/use-job-type-review.ts` — React Query hook for the tab.
- `src/pages/admin/JobTypeReview.tsx` — the review tab.

Modified:
- `supabase/functions/_shared/hcp/client.ts` — add `setJobType(...)` (contract from Task 1).
- `supabase/functions/_shared/hcp/__tests__/client.test.ts` — add `setJobType` tests.
- `supabase/functions/hcp-webhook/index.ts` — call classifier in `handleJobCompleted`.
- `src/App.tsx` — add the `/admin/job-type-review` route.
- `src/components/AppShellWithNav.tsx` — add the nav item.

---

## Task 1: Spike — confirm the HCP Job Type write contract and exact labels

No TDD; this is a live investigation whose output unblocks Tasks 4 and 7. The team has precedent: `_shared/hcp/client.ts` documents that `PUT /jobs/{id}` with a guessed body returned 404 and the real endpoint was found by probing live. Do the same here.

- [ ] **Step 1: Read how HCP represents Job Type today**

Read `supabase/functions/hcp-webhook/index.ts` and find `extractJobType(data)`. Note exactly which path in the HCP payload it reads (e.g. `data.job_fields?.job_type?.name`, `data.work_status`, or similar). Record the path and what an *unset* Job Type looks like (null / "" / missing).

- [ ] **Step 2: Pull one real job payload and list its current Job Type field + the exact label strings HCP uses**

Using the existing read path (the same `Token ${HOUSECALL_PRO_API_KEY}` auth as `sync-hcp-memberships`), GET a known completed job and inspect where Job Type lives and the literal label strings (e.g. is it "Service Call" or "Service call"?). Record the canonical label for each of: Door + Opener Install, Door Install, Opener Install, Opener + Repair, Repair, Service Call. **These exact strings become the `JobType` constants in Task 4.**

- [ ] **Step 3: Probe the write endpoint live (do not flip anything on yet)**

Determine the request that sets Job Type. Candidates to probe against a disposable/test job: `PUT /jobs/{id}` with a `job_fields`/`job_type` body, or a dedicated job-fields endpoint. Capture the working method + URL + body shape, mirroring the doc-comment style already in `client.ts`. If **no** API write exists, record that explicitly.

- [ ] **Step 4: Write the decision into the plan**

Append a short "HCP Job Type write contract (confirmed YYYY-MM-DD)" note to `client.ts`'s top comment block with the exact endpoint/body, OR "No API write available — classifications are recorded as `pending_write` and applied out-of-band." This single finding decides whether Task 7 writes inline or is a no-op recorder, and whether the webhook (Task 9) writes inline.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/hcp/client.ts docs/superpowers/plans/2026-06-17-hcp-job-type-auto-tagging.md
git commit -m "spike(hcp): confirm Job Type write contract + canonical labels"
```

---

## Task 2: `part_categories` table + seed

- [ ] **Step 1: Write the migration**

Create `supabase/migrations/20260617130000_part_categories.sql`:

```sql
-- Explicit part-name -> category map that drives Job Type classification.
-- Seeded from the Twins price sheet. A line item whose part is absent here
-- makes its job unclassifiable (routed to review), never guessed.
CREATE TABLE IF NOT EXISTS public.part_categories (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  match_name   TEXT NOT NULL,            -- lowercased exact line-item name to match
  category     TEXT NOT NULL CHECK (category IN
                 ('door','opener','accessory','repair_part')),
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS part_categories_match_name_key
  ON public.part_categories (match_name);

ALTER TABLE public.part_categories ENABLE ROW LEVEL SECURITY;

-- Read for authenticated dashboard users; writes via service_role only.
CREATE POLICY part_categories_read ON public.part_categories
  FOR SELECT TO authenticated USING (true);
```

Note: the literal "Labor" line is detected by name in code (Task 5), not stored here — it is not a part.

- [ ] **Step 2: Apply the migration**

Run: `npx supabase db push` (or apply via the Supabase MCP `apply_migration` against project `jwrpj`).
Expected: `part_categories` table exists. Per repo quirk (see memory: migration-history desync), if using direct apply, also INSERT the version row into `schema_migrations`.

- [ ] **Step 3: Seed from the price sheet**

The price sheet lives in `twins-payroll` (xlsx). Produce a one-off `INSERT` from its part names. For each part, lowercase the name and assign a category. Example shape (real values come from the sheet):

```sql
INSERT INTO public.part_categories (match_name, category) VALUES
  ('liftmaster 8500w', 'opener'),
  ('wireless keypad', 'accessory'),
  ('remote', 'accessory'),
  ('torsion spring', 'repair_part'),
  ('steel door 16x7', 'door')
ON CONFLICT (match_name) DO NOTHING;
```

Save the generated INSERT as `supabase/migrations/20260617130000_part_categories.sql` (appended) so the seed is reproducible. Unmapped parts are intentionally left out.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260617130000_part_categories.sql
git commit -m "feat(job-type): part_categories table seeded from price sheet"
```

---

## Task 3: `job_type_classifications` table

- [ ] **Step 1: Write the migration**

Create `supabase/migrations/20260617130001_job_type_classifications.sql`:

```sql
-- One row per classified job. Drives the review tab and the write decision.
CREATE TABLE IF NOT EXISTS public.job_type_classifications (
  id                 UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id             TEXT NOT NULL UNIQUE,
  proposed_job_type  TEXT,                    -- null = unclassifiable (BLANK)
  current_job_type   TEXT,                    -- HCP's Job Type at classify time
  status             TEXT NOT NULL CHECK (status IN
                       ('written','blank','disagreement','pending_write','skipped')),
  reason             TEXT NOT NULL,           -- which decision-tree row fired
  unrecognized_parts TEXT[] NOT NULL DEFAULT '{}',
  line_items_snapshot JSONB,
  hcp_link           TEXT,
  classified_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.job_type_classifications ENABLE ROW LEVEL SECURITY;

CREATE POLICY jtc_read ON public.job_type_classifications
  FOR SELECT TO authenticated USING (true);
```

- [ ] **Step 2: Apply and commit**

Run: `npx supabase db push`. Expected: table exists.

```bash
git add supabase/migrations/20260617130001_job_type_classifications.sql
git commit -m "feat(job-type): job_type_classifications tracking table"
```

---

## Task 4: Shared types and Job Type constants

**Files:** Create `supabase/functions/_shared/job-type/types.ts`

- [ ] **Step 1: Write the module**

Use the **exact** label strings confirmed in Task 1 Step 2. The values below are the agreed labels; correct them if the spike found different casing.

```typescript
export type PartCategory = 'door' | 'opener' | 'accessory' | 'repair_part';

export const JOB_TYPE = {
  DOOR_OPENER_INSTALL: 'Door + Opener Install',
  DOOR_INSTALL: 'Door Install',
  OPENER_INSTALL: 'Opener Install',
  OPENER_REPAIR: 'Opener + Repair',
  REPAIR: 'Repair',
  SERVICE_CALL: 'Service Call',
} as const;

export type JobType = (typeof JOB_TYPE)[keyof typeof JOB_TYPE];

export const SERVICE_CALL_THRESHOLD_CENTS = 18500; // $185, total job price

export interface HcpLineItem {
  name?: string;
  kind?: string;        // 'material' | 'labor' | 'parts' | 'equipment' | 'fixed gratuity' ...
  quantity?: number;
  unit_cost?: number;   // cents
  total_cost?: number;  // cents
  amount?: number;      // cents
}

export interface CategorizeResult {
  categories: Set<PartCategory>;
  hasLaborLine: boolean;       // a line item literally named "Labor"
  totalCents: number;          // sum of part/labor line totals (excludes gratuity)
  unrecognizedParts: string[]; // part-bearing line names not in the map
}

export interface ClassifyResult {
  jobType: JobType | null;     // null = BLANK / unclassifiable
  reason: string;              // human-readable decision-tree row
}
```

- [ ] **Step 2: Commit**

```bash
git add supabase/functions/_shared/job-type/types.ts
git commit -m "feat(job-type): shared types and Job Type constants"
```

---

## Task 5: `categorize.ts` — line items → categories (TDD)

**Files:**
- Create: `supabase/functions/_shared/job-type/categorize.ts`
- Test: `supabase/functions/_shared/job-type/__tests__/categorize.test.ts`

- [ ] **Step 1: Write the failing test**

```typescript
import { describe, it, expect } from 'vitest';
import { categorizeLineItems } from '../categorize';
import type { HcpLineItem } from '../types';

const MAP = new Map<string, 'door' | 'opener' | 'accessory' | 'repair_part'>([
  ['liftmaster 8500w', 'opener'],
  ['wireless keypad', 'accessory'],
  ['remote', 'accessory'],
  ['torsion spring', 'repair_part'],
  ['steel door 16x7', 'door'],
]);

const li = (name: string, total: number, kind = 'material'): HcpLineItem =>
  ({ name, total_cost: total, kind });

describe('categorizeLineItems', () => {
  it('maps known parts to categories and sums totals', () => {
    const r = categorizeLineItems(
      [li('Steel Door 16x7', 90000), li('LiftMaster 8500W', 40000)],
      MAP,
    );
    expect([...r.categories].sort()).toEqual(['door', 'opener']);
    expect(r.totalCents).toBe(130000);
    expect(r.unrecognizedParts).toEqual([]);
    expect(r.hasLaborLine).toBe(false);
  });

  it('detects a literal "Labor" line via name (case-insensitive)', () => {
    const r = categorizeLineItems([li('Labor', 12000, 'labor')], MAP);
    expect(r.hasLaborLine).toBe(true);
    expect(r.categories.size).toBe(0);
    expect(r.unrecognizedParts).toEqual([]); // Labor is not an unrecognized part
  });

  it('flags part-bearing lines absent from the map as unrecognized', () => {
    const r = categorizeLineItems([li('Mystery Bracket', 5000)], MAP);
    expect(r.unrecognizedParts).toEqual(['Mystery Bracket']);
    expect(r.categories.size).toBe(0);
  });

  it('excludes gratuity lines from the total and from unrecognized', () => {
    const r = categorizeLineItems(
      [li('Torsion Spring', 8000), li('Tip', 2000, 'fixed gratuity')],
      MAP,
    );
    expect(r.totalCents).toBe(8000);
    expect(r.unrecognizedParts).toEqual([]);
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run supabase/functions/_shared/job-type/__tests__/categorize.test.ts`
Expected: FAIL — `categorizeLineItems` is not defined.

- [ ] **Step 3: Write the implementation**

```typescript
import type { CategorizeResult, HcpLineItem, PartCategory } from './types';

const GRATUITY_KINDS = new Set(['fixed gratuity', 'gratuity', 'percentage gratuity', 'tip']);

function lineTotal(item: HcpLineItem): number {
  if (typeof item.total_cost === 'number') return item.total_cost;
  if (typeof item.amount === 'number') return item.amount;
  return (item.unit_cost ?? 0) * (item.quantity ?? 1);
}

export function categorizeLineItems(
  items: HcpLineItem[],
  partMap: Map<string, PartCategory>,
): CategorizeResult {
  const categories = new Set<PartCategory>();
  const unrecognizedParts: string[] = [];
  let hasLaborLine = false;
  let totalCents = 0;

  for (const item of items) {
    const name = (item.name ?? '').trim();
    const kind = (item.kind ?? '').toLowerCase();

    if (GRATUITY_KINDS.has(kind)) continue; // never counts toward total or category

    totalCents += lineTotal(item);

    if (name.toLowerCase() === 'labor') {
      hasLaborLine = true;
      continue;
    }

    const cat = partMap.get(name.toLowerCase());
    if (cat) {
      categories.add(cat);
    } else if (kind === 'labor') {
      // a labor-kind line that is not literally "Labor" (e.g. "Install Labor")
      // contributes to total but is neither a part nor the discount signal.
      continue;
    } else {
      unrecognizedParts.push(name);
    }
  }

  return { categories, hasLaborLine, totalCents, unrecognizedParts };
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run supabase/functions/_shared/job-type/__tests__/categorize.test.ts`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/job-type/categorize.ts supabase/functions/_shared/job-type/__tests__/categorize.test.ts
git commit -m "feat(job-type): categorize line items into part categories"
```

---

## Task 6: `classify.ts` — the decision tree (TDD)

This is the correctness heart. Every row of the spec's decision tree gets a test.

**Files:**
- Create: `supabase/functions/_shared/job-type/classify.ts`
- Test: `supabase/functions/_shared/job-type/__tests__/classify.test.ts`

- [ ] **Step 1: Write the failing test**

```typescript
import { describe, it, expect } from 'vitest';
import { classifyJobType } from '../classify';
import { JOB_TYPE, type PartCategory } from '../types';

const input = (
  cats: PartCategory[],
  totalCents: number,
  hasLaborLine = false,
  unrecognizedParts: string[] = [],
) => ({
  categories: new Set(cats),
  totalCents,
  hasLaborLine,
  unrecognizedParts,
});

describe('classifyJobType', () => {
  it('row1: door + opener => Door + Opener Install', () => {
    expect(classifyJobType(input(['door', 'opener'], 130000)).jobType)
      .toBe(JOB_TYPE.DOOR_OPENER_INSTALL);
  });

  it('row2: door only => Door Install', () => {
    expect(classifyJobType(input(['door'], 90000)).jobType)
      .toBe(JOB_TYPE.DOOR_INSTALL);
  });

  it('row3: opener + repair_part => Opener + Repair', () => {
    expect(classifyJobType(input(['opener', 'repair_part'], 60000)).jobType)
      .toBe(JOB_TYPE.OPENER_REPAIR);
  });

  it('row4: opener + only accessory => Opener Install', () => {
    expect(classifyJobType(input(['opener', 'accessory'], 50000)).jobType)
      .toBe(JOB_TYPE.OPENER_INSTALL);
  });

  it('row4: opener alone => Opener Install', () => {
    expect(classifyJobType(input(['opener'], 45000)).jobType)
      .toBe(JOB_TYPE.OPENER_INSTALL);
  });

  it('row5: no door/opener, Labor line under threshold => Repair (discount-safe)', () => {
    expect(classifyJobType(input([], 9000, true)).jobType)
      .toBe(JOB_TYPE.REPAIR);
  });

  it('row5 beats row7: Labor line wins even when total < $185', () => {
    expect(classifyJobType(input(['repair_part'], 10000, true)).jobType)
      .toBe(JOB_TYPE.REPAIR);
  });

  it('row6: no door/opener, total >= $185 => Repair', () => {
    expect(classifyJobType(input(['repair_part'], 18500)).jobType)
      .toBe(JOB_TYPE.REPAIR);
  });

  it('row7: no door/opener, total < $185, no Labor => Service Call', () => {
    expect(classifyJobType(input(['repair_part'], 12000)).jobType)
      .toBe(JOB_TYPE.SERVICE_CALL);
  });

  it('row8: unrecognized parts present => BLANK (null)', () => {
    const r = classifyJobType(input([], 30000, false, ['Mystery Bracket']));
    expect(r.jobType).toBeNull();
    expect(r.reason).toMatch(/unrecognized/i);
  });

  it('row8: nothing at all => BLANK (null)', () => {
    expect(classifyJobType(input([], 0)).jobType).toBeNull();
  });

  it('threshold boundary: exactly $185 => Repair (>=)', () => {
    expect(classifyJobType(input(['repair_part'], 18500)).jobType)
      .toBe(JOB_TYPE.REPAIR);
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run supabase/functions/_shared/job-type/__tests__/classify.test.ts`
Expected: FAIL — `classifyJobType` is not defined.

- [ ] **Step 3: Write the implementation**

```typescript
import {
  JOB_TYPE,
  SERVICE_CALL_THRESHOLD_CENTS,
  type ClassifyResult,
  type PartCategory,
} from './types';

export interface ClassifyInput {
  categories: Set<PartCategory>;
  totalCents: number;
  hasLaborLine: boolean;
  unrecognizedParts: string[];
}

export function classifyJobType(inp: ClassifyInput): ClassifyResult {
  const { categories, totalCents, hasLaborLine, unrecognizedParts } = inp;

  // Row 8 (guard): if any part-bearing line was unrecognized, never guess.
  if (unrecognizedParts.length > 0) {
    return { jobType: null, reason: `row8: unrecognized parts: ${unrecognizedParts.join(', ')}` };
  }

  const hasDoor = categories.has('door');
  const hasOpener = categories.has('opener');
  const hasRepairPart = categories.has('repair_part');

  // Row 1
  if (hasDoor && hasOpener) {
    return { jobType: JOB_TYPE.DOOR_OPENER_INSTALL, reason: 'row1: door + opener' };
  }
  // Row 2
  if (hasDoor) {
    return { jobType: JOB_TYPE.DOOR_INSTALL, reason: 'row2: door, no opener' };
  }
  // Row 3
  if (hasOpener && hasRepairPart) {
    return { jobType: JOB_TYPE.OPENER_REPAIR, reason: 'row3: opener + repair parts' };
  }
  // Row 4
  if (hasOpener) {
    return { jobType: JOB_TYPE.OPENER_INSTALL, reason: 'row4: opener with only accessories' };
  }
  // Row 5 — literal Labor line forces Repair (discount-safe), beats the threshold.
  if (hasLaborLine) {
    return { jobType: JOB_TYPE.REPAIR, reason: 'row5: labor line present' };
  }
  // Row 6
  if (totalCents >= SERVICE_CALL_THRESHOLD_CENTS) {
    return { jobType: JOB_TYPE.REPAIR, reason: 'row6: total >= $185' };
  }
  // Row 7 — only when there is a real (recognized) repair signal under threshold.
  if (hasRepairPart) {
    return { jobType: JOB_TYPE.SERVICE_CALL, reason: 'row7: total < $185' };
  }
  // Row 8 — empty / no usable signal.
  return { jobType: null, reason: 'row8: no recognizable signal' };
}
```

Note: an empty ticket with `totalCents < $185` and no parts/labor falls through to row 8 (BLANK), which is correct — there is nothing to classify from. A bare service call with no line items therefore surfaces for review rather than being defaulted.

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run supabase/functions/_shared/job-type/__tests__/classify.test.ts`
Expected: PASS (12 tests).

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/job-type/classify.ts supabase/functions/_shared/job-type/__tests__/classify.test.ts
git commit -m "feat(job-type): deterministic Job Type decision tree"
```

---

## Task 7: `setJobType` HCP writer (TDD) — contract from Task 1

If Task 1 found **no** API write path, skip Steps 1-4 and instead add a `setJobType` that returns `{ ok: false, error: 'no-api-write' }` so callers record `pending_write`; note that in the commit. Otherwise implement the confirmed contract. The test below assumes the most likely contract (`PUT /jobs/{id}` with a job-fields body); **adjust the URL/body to match Task 1's finding**.

**Files:**
- Modify: `supabase/functions/_shared/hcp/client.ts`
- Modify: `supabase/functions/_shared/hcp/__tests__/client.test.ts`

- [ ] **Step 1: Write the failing test (append to client.test.ts)**

```typescript
import { setJobType } from '../client';

describe('setJobType', () => {
  beforeEach(() => vi.restoreAllMocks());

  it('PUTs the confirmed Job Type contract and returns ok on 2xx', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response('{}', { status: 200 }),
    );
    const res = await setJobType({ jobId: 'job_1', jobType: 'Repair', apiKey: 'k' });
    expect(res.ok).toBe(true);
    const init = fetchMock.mock.calls[0][1] as RequestInit;
    expect(init.method).toBe('PUT');
    expect((init.headers as Record<string, string>).Authorization).toBe('Token k');
  });

  it('returns ok=false with the error body on non-2xx', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ message: 'bad' }), { status: 422 }),
    );
    const res = await setJobType({ jobId: 'job_1', jobType: 'Repair', apiKey: 'k' });
    expect(res.ok).toBe(false);
    expect(res.error).toMatch(/422/);
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run supabase/functions/_shared/hcp/__tests__/client.test.ts`
Expected: FAIL — `setJobType` is not exported.

- [ ] **Step 3: Implement `setJobType` (match Task 1's confirmed endpoint/body)**

```typescript
export interface SetJobTypeArgs {
  jobId: string;
  jobType: string;
  apiKey: string;
}

export async function setJobType(args: SetJobTypeArgs): Promise<HcpWriteResult> {
  // Endpoint + body confirmed live in Task 1. Update both if the spike differs.
  const url = `https://api.housecallpro.com/jobs/${encodeURIComponent(args.jobId)}`;
  try {
    const res = await fetch(url, {
      method: 'PUT',
      headers: {
        Authorization: `Token ${args.apiKey}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ job_fields: { job_type: { name: args.jobType } } }),
    });
    if (!res.ok) {
      const text = await res.text().catch(() => '');
      return { ok: false, error: `HCP ${res.status}: ${text.slice(0, 500)}` };
    }
    return { ok: true };
  } catch (e) {
    return { ok: false, error: e instanceof Error ? e.message : String(e) };
  }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run supabase/functions/_shared/hcp/__tests__/client.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/hcp/client.ts supabase/functions/_shared/hcp/__tests__/client.test.ts
git commit -m "feat(hcp): setJobType writer per confirmed contract"
```

---

## Task 8: `run.ts` orchestrator (TDD)

Decides, for one job, what to record and whether to write. Pure except it accepts an injected `writeFn` and the already-loaded part map, so it is fully testable without network or DB.

**Files:**
- Create: `supabase/functions/_shared/job-type/run.ts`
- Test: `supabase/functions/_shared/job-type/__tests__/run.test.ts`

- [ ] **Step 1: Write the failing test**

```typescript
import { describe, it, expect, vi } from 'vitest';
import { decideAndWrite } from '../run';
import type { PartCategory } from '../types';

const MAP = new Map<string, PartCategory>([
  ['torsion spring', 'repair_part'],
  ['steel door 16x7', 'door'],
]);

const li = (name: string, total: number, kind = 'material') => ({ name, total_cost: total, kind });

describe('decideAndWrite', () => {
  it('writes when HCP Job Type is empty and classification is confident', async () => {
    const writeFn = vi.fn().mockResolvedValue({ ok: true });
    const r = await decideAndWrite({
      jobId: 'job_1',
      lineItems: [li('Steel Door 16x7', 90000)],
      currentJobType: '',
      partMap: MAP,
      writeFn,
      dryRun: false,
    });
    expect(writeFn).toHaveBeenCalledWith('job_1', 'Door Install');
    expect(r.status).toBe('written');
    expect(r.proposedJobType).toBe('Door Install');
  });

  it('does not write in dryRun, records pending_write', async () => {
    const writeFn = vi.fn().mockResolvedValue({ ok: true });
    const r = await decideAndWrite({
      jobId: 'job_1',
      lineItems: [li('Steel Door 16x7', 90000)],
      currentJobType: '',
      partMap: MAP,
      writeFn,
      dryRun: true,
    });
    expect(writeFn).not.toHaveBeenCalled();
    expect(r.status).toBe('pending_write');
  });

  it('flags a disagreement and never overwrites an existing tag', async () => {
    const writeFn = vi.fn().mockResolvedValue({ ok: true });
    const r = await decideAndWrite({
      jobId: 'job_1',
      lineItems: [li('Steel Door 16x7', 90000)],
      currentJobType: 'Repair',
      partMap: MAP,
      writeFn,
      dryRun: false,
    });
    expect(writeFn).not.toHaveBeenCalled();
    expect(r.status).toBe('disagreement');
    expect(r.proposedJobType).toBe('Door Install');
    expect(r.currentJobType).toBe('Repair');
  });

  it('skips when existing tag already equals the proposal', async () => {
    const writeFn = vi.fn().mockResolvedValue({ ok: true });
    const r = await decideAndWrite({
      jobId: 'job_1',
      lineItems: [li('Steel Door 16x7', 90000)],
      currentJobType: 'Door Install',
      partMap: MAP,
      writeFn,
      dryRun: false,
    });
    expect(writeFn).not.toHaveBeenCalled();
    expect(r.status).toBe('skipped');
  });

  it('records blank for unclassifiable jobs', async () => {
    const writeFn = vi.fn().mockResolvedValue({ ok: true });
    const r = await decideAndWrite({
      jobId: 'job_1',
      lineItems: [li('Mystery Bracket', 5000)],
      currentJobType: '',
      partMap: MAP,
      writeFn,
      dryRun: false,
    });
    expect(writeFn).not.toHaveBeenCalled();
    expect(r.status).toBe('blank');
    expect(r.proposedJobType).toBeNull();
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run supabase/functions/_shared/job-type/__tests__/run.test.ts`
Expected: FAIL — `decideAndWrite` is not defined.

- [ ] **Step 3: Write the implementation**

```typescript
import { categorizeLineItems } from './categorize';
import { classifyJobType } from './classify';
import type { HcpLineItem, PartCategory } from './types';

export type WriteFn = (jobId: string, jobType: string) => Promise<{ ok: boolean; error?: string }>;

export interface DecideArgs {
  jobId: string;
  lineItems: HcpLineItem[];
  currentJobType: string | null;
  partMap: Map<string, PartCategory>;
  writeFn: WriteFn;
  dryRun: boolean;
}

export interface DecideResult {
  jobId: string;
  proposedJobType: string | null;
  currentJobType: string | null;
  status: 'written' | 'blank' | 'disagreement' | 'pending_write' | 'skipped';
  reason: string;
  unrecognizedParts: string[];
}

export async function decideAndWrite(args: DecideArgs): Promise<DecideResult> {
  const cat = categorizeLineItems(args.lineItems, args.partMap);
  const { jobType, reason } = classifyJobType({
    categories: cat.categories,
    totalCents: cat.totalCents,
    hasLaborLine: cat.hasLaborLine,
    unrecognizedParts: cat.unrecognizedParts,
  });
  const current = (args.currentJobType ?? '').trim();

  const base = {
    jobId: args.jobId,
    proposedJobType: jobType,
    currentJobType: current || null,
    reason,
    unrecognizedParts: cat.unrecognizedParts,
  };

  if (jobType === null) {
    return { ...base, status: 'blank' };
  }
  if (current && current === jobType) {
    return { ...base, status: 'skipped' };
  }
  if (current && current !== jobType) {
    return { ...base, status: 'disagreement' }; // never overwrite a human tag
  }
  // current is empty -> fill it
  if (args.dryRun) {
    return { ...base, status: 'pending_write' };
  }
  const w = await args.writeFn(args.jobId, jobType);
  return { ...base, status: w.ok ? 'written' : 'pending_write', reason: w.ok ? reason : `${reason}; write failed: ${w.error}` };
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run supabase/functions/_shared/job-type/__tests__/run.test.ts`
Expected: PASS (5 tests).

- [ ] **Step 5: Run the whole job-type suite + commit**

Run: `npx vitest run supabase/functions/_shared/job-type/`
Expected: PASS (all categorize + classify + run tests).

```bash
git add supabase/functions/_shared/job-type/run.ts supabase/functions/_shared/job-type/__tests__/run.test.ts
git commit -m "feat(job-type): orchestrator decides write vs flag, fill-blanks-only"
```

---

## Task 9: Wire the classifier into the real-time webhook

**Files:** Modify `supabase/functions/hcp-webhook/index.ts` (inside `handleJobCompleted`, after the `jobs` upsert).

- [ ] **Step 1: Add a part-map loader helper near the top of the file**

```typescript
import { decideAndWrite } from '../_shared/job-type/run.ts';
import { setJobType } from '../_shared/hcp/client.ts';
import type { PartCategory } from '../_shared/job-type/types.ts';

async function loadPartMap(supabase: any): Promise<Map<string, PartCategory>> {
  const { data, error } = await supabase
    .from('part_categories')
    .select('match_name, category');
  if (error) throw error;
  return new Map((data ?? []).map((r: any) => [r.match_name as string, r.category as PartCategory]));
}
```

- [ ] **Step 2: Call the classifier after the upsert in `handleJobCompleted`**

Insert immediately after the existing `jobs` upsert (the block ending around line 713). Use the same `extractJobType(data)` the file already defines for the current HCP value, and `data.line_items` for items.

```typescript
  // --- Job Type auto-tagging ---
  try {
    const partMap = await loadPartMap(supabase);
    const apiKey = Deno.env.get('HOUSECALL_PRO_API_KEY') ?? Deno.env.get('HCP_API_KEY') ?? '';
    const dryRun = (Deno.env.get('JOB_TYPE_DRY_RUN') ?? 'true') === 'true';
    const decision = await decideAndWrite({
      jobId,
      lineItems: data.line_items ?? [],
      currentJobType: extractJobType(data),
      partMap,
      writeFn: (id, jt) => setJobType({ jobId: id, jobType: jt, apiKey }),
      dryRun,
    });
    await supabase.from('job_type_classifications').upsert({
      job_id: decision.jobId,
      proposed_job_type: decision.proposedJobType,
      current_job_type: decision.currentJobType,
      status: decision.status,
      reason: decision.reason,
      unrecognized_parts: decision.unrecognizedParts,
      line_items_snapshot: data.line_items ?? [],
      hcp_link: `https://pro.housecallpro.com/app/jobs/${jobId}`,
      updated_at: new Date().toISOString(),
    }, { onConflict: 'job_id' });
  } catch (e) {
    console.error('job-type tagging failed (non-fatal):', e);
  }
```

The classifier is wrapped so a failure never breaks webhook ingestion. It defaults to **dry-run** (`JOB_TYPE_DRY_RUN` unset → `'true'`): the webhook records `pending_write` rows without touching HCP until the env flag is flipped to `false`.

- [ ] **Step 3: Type-check / lint the function**

Run: `npx tsc --noEmit -p tsconfig.json` (or the repo's edge-function check). Expected: no new errors in `hcp-webhook`.

- [ ] **Step 4: Deploy and smoke-test against one completed job**

Deploy: `npx supabase functions deploy hcp-webhook` (project `jwrpj`). Replay/complete one test job and confirm a `job_type_classifications` row appears with the expected `status` (`pending_write` while dry-run). Verify HCP is untouched in dry-run.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/hcp-webhook/index.ts
git commit -m "feat(hcp-webhook): auto-tag Job Type on job.completed (dry-run default)"
```

---

## Task 10: Backfill edge function

**Files:** Create `supabase/functions/classify-job-types-backfill/index.ts`

- [ ] **Step 1: Write the function**

```typescript
import { createClient } from 'jsr:@supabase/supabase-js@2';
import { decideAndWrite } from '../_shared/job-type/run.ts';
import { setJobType } from '../_shared/hcp/client.ts';
import type { PartCategory } from '../_shared/job-type/types.ts';

Deno.serve(async (req) => {
  const supabase = createClient(
    Deno.env.get('SUPABASE_URL')!,
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,
  );
  const apiKey = Deno.env.get('HOUSECALL_PRO_API_KEY') ?? Deno.env.get('HCP_API_KEY') ?? '';
  const body = await req.json().catch(() => ({}));
  const dryRun = body.dryRun !== false; // default true

  const { data: parts } = await supabase.from('part_categories').select('match_name, category');
  const partMap = new Map<string, PartCategory>(
    (parts ?? []).map((r: any) => [r.match_name, r.category]),
  );

  // Completed jobs only; line items live in jobs.hcp_data.
  const { data: jobs, error } = await supabase
    .from('jobs')
    .select('job_id, hcp_data')
    .eq('status', 'completed');
  if (error) return new Response(JSON.stringify({ error: error.message }), { status: 500 });

  let written = 0, blank = 0, disagreement = 0, skipped = 0, pending = 0;
  for (const job of jobs ?? []) {
    const hcp = job.hcp_data ?? {};
    const decision = await decideAndWrite({
      jobId: job.job_id,
      lineItems: hcp.line_items ?? [],
      currentJobType: hcp.job_fields?.job_type?.name ?? '', // align with extractJobType path from Task 1
      partMap,
      writeFn: (id, jt) => setJobType({ jobId: id, jobType: jt, apiKey }),
      dryRun,
    });
    await supabase.from('job_type_classifications').upsert({
      job_id: decision.jobId,
      proposed_job_type: decision.proposedJobType,
      current_job_type: decision.currentJobType,
      status: decision.status,
      reason: decision.reason,
      unrecognized_parts: decision.unrecognizedParts,
      line_items_snapshot: hcp.line_items ?? [],
      hcp_link: `https://pro.housecallpro.com/app/jobs/${job.job_id}`,
      updated_at: new Date().toISOString(),
    }, { onConflict: 'job_id' });
    if (decision.status === 'written') written++;
    else if (decision.status === 'blank') blank++;
    else if (decision.status === 'disagreement') disagreement++;
    else if (decision.status === 'skipped') skipped++;
    else pending++;
  }
  return new Response(JSON.stringify({ total: jobs?.length ?? 0, written, blank, disagreement, skipped, pending }), {
    headers: { 'Content-Type': 'application/json' },
  });
});
```

The `currentJobType` path must match what Task 1 Step 1 found `extractJobType` reads. Update it if the spike found a different path.

- [ ] **Step 2: Deploy and run a dry-run first**

Deploy: `npx supabase functions deploy classify-job-types-backfill`.
Invoke dry-run: POST `{ "dryRun": true }`. Expected: a JSON tally; `job_type_classifications` populated; **HCP untouched**. Spot-check 5-10 `written`-candidate rows against the live tickets before any real write.

- [ ] **Step 3: Run the real backfill once satisfied**

Invoke `{ "dryRun": false }`. Expected: blanks-only writes land in HCP; tally returns. Re-running is safe (idempotent upsert; already-correct jobs return `skipped`).

- [ ] **Step 4: Commit**

```bash
git add supabase/functions/classify-job-types-backfill/index.ts
git commit -m "feat(job-type): one-time backfill edge function (dry-run default)"
```

---

## Task 11: Dashboard review tab

**Files:**
- Create: `src/hooks/use-job-type-review.ts`
- Create: `src/pages/admin/JobTypeReview.tsx`
- Modify: `src/App.tsx`
- Modify: `src/components/AppShellWithNav.tsx`

- [ ] **Step 1: Write the React Query hook (session-gated, per repo convention)**

```typescript
// src/hooks/use-job-type-review.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/contexts/AuthContext';

export interface JobTypeReviewRow {
  job_id: string;
  proposed_job_type: string | null;
  current_job_type: string | null;
  status: string;
  reason: string;
  unrecognized_parts: string[];
  hcp_link: string | null;
  classified_at: string;
}

export function useJobTypeReview() {
  const { session } = useAuth();
  return useQuery({
    queryKey: ['job-type-review'],
    enabled: !!session,
    queryFn: async (): Promise<JobTypeReviewRow[]> => {
      const { data, error } = await supabase
        .from('job_type_classifications' as never)
        .select('*')
        .in('status', ['blank', 'disagreement', 'pending_write'])
        .order('classified_at', { ascending: false });
      if (error) throw error;
      return (data as JobTypeReviewRow[] | null) ?? [];
    },
  });
}
```

- [ ] **Step 2: Write the page**

```tsx
// src/pages/admin/JobTypeReview.tsx
import { Loader2 } from 'lucide-react';
import { useJobTypeReview } from '@/hooks/use-job-type-review';

const STATUS_LABEL: Record<string, string> = {
  blank: 'Needs a Job Type',
  disagreement: 'Disagrees with current tag',
  pending_write: 'Pending write to HCP',
};

export default function JobTypeReview() {
  const { data, isLoading, error } = useJobTypeReview();

  if (isLoading) return <div className="p-6 flex items-center gap-2"><Loader2 className="h-4 w-4 animate-spin" /> Loading…</div>;
  if (error) return <div className="p-6 text-red-600">Failed to load: {String(error)}</div>;

  const rows = data ?? [];
  return (
    <div className="p-6 space-y-4">
      <h1 className="text-xl font-semibold">Job Type Review</h1>
      <p className="text-sm text-muted-foreground">{rows.length} jobs need attention.</p>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left border-b">
              <th className="py-2 pr-4">Job</th>
              <th className="py-2 pr-4">Status</th>
              <th className="py-2 pr-4">Proposed</th>
              <th className="py-2 pr-4">Current</th>
              <th className="py-2 pr-4">Why</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.job_id} className="border-b">
                <td className="py-2 pr-4">
                  {r.hcp_link ? <a className="text-blue-600 underline" href={r.hcp_link} target="_blank" rel="noreferrer">{r.job_id}</a> : r.job_id}
                </td>
                <td className="py-2 pr-4">{STATUS_LABEL[r.status] ?? r.status}</td>
                <td className="py-2 pr-4">{r.proposed_job_type ?? '—'}</td>
                <td className="py-2 pr-4">{r.current_job_type ?? '—'}</td>
                <td className="py-2 pr-4 text-muted-foreground">
                  {r.reason}{r.unrecognized_parts.length ? ` (${r.unrecognized_parts.join(', ')})` : ''}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Add the route in `src/App.tsx`**

Mirror the existing admin route pattern (lazy import + `ProtectedRoute` + `AppShellWithNav` + `Suspense`):

```tsx
const JobTypeReviewPage = lazy(() => import('./pages/admin/JobTypeReview'));

// inside <Routes>:
<Route
  path="/admin/job-type-review"
  element={
    <ProtectedRoute requiredPermission="view_job_type_review">
      <AppShellWithNav>
        <Suspense fallback={<PageSpinner />}>
          <JobTypeReviewPage />
        </Suspense>
      </AppShellWithNav>
    </ProtectedRoute>
  }
/>
```

If `view_job_type_review` is not a defined permission, gate on `isAdmin` instead (match the simplest existing admin-only route in `App.tsx`).

- [ ] **Step 4: Add the nav item in `src/components/AppShellWithNav.tsx`**

Near the existing nav array (around lines 64-66), mirroring the Dispatch entry:

```tsx
{ to: `/admin/job-type-review${navSuffix}`, label: "Job Type Review", icon: <CheckCircle className="h-4 w-4" />, show: isAdmin },
```

Import `CheckCircle` from `lucide-react` if not already imported.

- [ ] **Step 5: Verify in the browser**

Start the dev server and open `/admin/job-type-review` (use the preview_* workflow). Confirm the tab renders, lists rows from `job_type_classifications`, and HCP links open. Confirm a non-admin does not see the nav item.

- [ ] **Step 6: Commit**

```bash
git add src/hooks/use-job-type-review.ts src/pages/admin/JobTypeReview.tsx src/App.tsx src/components/AppShellWithNav.tsx
git commit -m "feat(dashboard): Job Type Review tab for blanks and disagreements"
```

---

## Task 12: Go-live verification

- [ ] **Step 1: Full suite green**

Run: `npx vitest run supabase/functions/_shared/job-type/ supabase/functions/_shared/hcp/`
Expected: all PASS.

- [ ] **Step 2: Backfill dry-run review**

Confirm the dry-run tally is sane and spot-checked (Task 10 Step 2). Resolve obvious `blank` clusters by adding missing parts to `part_categories`, then re-run dry-run.

- [ ] **Step 3: Flip real-time writes on**

Set `JOB_TYPE_DRY_RUN=false` in the `jwrpj` function env and redeploy `hcp-webhook`. Complete one test job; confirm HCP now shows the written Job Type and the row status is `written`.

- [ ] **Step 4: Run the real backfill**

Invoke `classify-job-types-backfill` with `{ "dryRun": false }`. Confirm tally; spot-check a few written tickets in HCP; confirm the review tab now shows only the genuine `blank`/`disagreement` tail.

- [ ] **Step 5: Commit any part-map additions**

```bash
git add supabase/migrations/20260617130000_part_categories.sql
git commit -m "chore(job-type): expand part_categories from backfill review"
```

---

## Self-Review notes

- **Spec coverage:** decision tree → Task 6; explicit part map / no guessing → Tasks 2, 5, 6 (row-8 guard); review queue as dash tab → Tasks 3, 11; real-time webhook → Task 9; blanks-only backfill + disagreement flagging → Tasks 8, 10; dry-run + never-overwrite + completed-only + reversibility → Tasks 8, 9, 10, 12; write-mechanism spike → Task 1; estimates excluded → backfill filters `status = 'completed'` (estimates are not completed jobs) and the webhook only fires on `job.completed`.
- **Open edge-case rulings (from spec §Edge cases), to confirm before/while executing:** replacement *section* handling (currently any `door` part ⇒ install — add a `section` category + row only if Daniel wants section⇒repair); multiple openers (single install — current tree handles it); discount/tip treatment (tip excluded via gratuity kinds; confirm the $185 total is the number Daniel means).
- **Type consistency:** `JobType`/`PartCategory`/`CategorizeResult`/`ClassifyResult` defined in Task 4 and used unchanged in Tasks 5, 6, 8; `decideAndWrite`/`DecideResult` defined in Task 8 and consumed in Tasks 9, 10; `setJobType` signature defined in Task 7 and called in Tasks 9, 10.
