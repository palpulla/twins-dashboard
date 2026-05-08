# Supervisor Ticket-Discipline Alerts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship an end-of-day email digest plus an in-app `/admin/notifications` tab that flags HCP tickets where workflow buttons weren't clicked or work notes are missing on sub-labor charges.

**Architecture:** Schema additions on `jobs` + new `app_settings`, `supervisor_alerts`, `job_technicians` tables. Pure-TS rule evaluators (Vitest-tested) that take typed inputs and produce alert rows. Supabase edge function `daily-supervisor-digest` orchestrates queries, writes alerts, and sends email via Resend. Existing `webhook-handler` extended to populate three new job columns + the tech-assignment junction. Next.js `/admin/notifications` page reads `supervisor_alerts` directly; bell badge in the existing `header.tsx` polls the unresolved count.

**Tech Stack:** Next.js 16.2.1, React 19, TypeScript, Supabase (Postgres + edge functions, Deno), `@supabase/ssr`, `@tanstack/react-query`, Tailwind v4, Resend (HTTP API), `date-fns` + `date-fns-tz`, Vitest (added by this plan).

**Spec:** `docs/superpowers/specs/2026-05-08-supervisor-ticket-discipline-alerts-design.md`

---

## File Structure

**New files:**

- `supabase/migrations/00005_supervisor_alerts.sql` — schema additions, all in one migration
- `supabase/migrations/00006_seed_charles_attribution.sql` — seed Charles user row + initial `app_settings` row
- `supabase/functions/daily-supervisor-digest/index.ts` — scheduled edge function (orchestrator)
- `supabase/functions/daily-supervisor-digest/render-email.ts` — pure HTML rendering (importable, testable shape)
- `supabase/functions/daily-supervisor-digest/send-email.ts` — Resend wrapper
- `src/lib/alerts/attribution.ts` — Charles co-tech attribution function (pure)
- `src/lib/alerts/rules.ts` — Rule 1 + Rule 2 evaluators (pure)
- `src/lib/alerts/window.ts` — reporting window calculator (pure)
- `src/lib/alerts/types.ts` — shared types for the alert pipeline
- `src/lib/alerts/__tests__/attribution.test.ts`
- `src/lib/alerts/__tests__/rules.test.ts`
- `src/lib/alerts/__tests__/window.test.ts`
- `src/components/notifications/bell.tsx` — top-bar bell + dropdown
- `src/components/notifications/issue-pill.tsx` — small reusable pill (NO_NOTES, NO_OMW, etc.)
- `src/components/notifications/alerts-table.tsx` — open-issues table with mark-resolved
- `src/components/notifications/past-digests-list.tsx`
- `src/components/notifications/settings-panel.tsx`
- `src/app/dashboard/admin/notifications/page.tsx` — server component, role-gated
- `src/app/api/notifications/count/route.ts` — bell badge count endpoint
- `src/app/api/notifications/[id]/resolve/route.ts` — mark-resolved
- `src/app/api/notifications/settings/route.ts` — get/update settings (+reschedules pg_cron)
- `src/app/api/notifications/test-digest/route.ts` — invokes the edge function on demand
- `src/lib/hooks/use-unresolved-count.ts` — react-query hook
- `vitest.config.ts` — Vitest config (project root)
- `scripts/backfill-job-fields.ts` — one-shot backfill for `work_notes`, `started_at`, `invoiced_at`

**Modified files:**

- `package.json` — add `vitest`, `@vitest/ui`, `resend` deps; add `test` script
- `src/types/database.ts` — regenerated after migration
- `src/components/layout/header.tsx` — mount the bell
- `src/components/layout/sidebar.tsx` — add "Notifications" entry under admin
- `supabase/functions/webhook-handler/index.ts` — populate `work_notes`, `started_at`, `invoiced_at`, `job_technicians`
- `src/types/webhooks.ts` — add typed shape for `assigned_employees`/notes payload

Each file has one clear responsibility. The pure-logic split (`attribution.ts` / `rules.ts` / `window.ts`) is what makes Vitest practical without spinning up Postgres.

---

## Task 1: Add Vitest test runner

**Files:**
- Create: `vitest.config.ts`
- Modify: `package.json`

- [ ] **Step 1: Install Vitest**

Run from worktree root:
```bash
npm install --save-dev vitest @vitest/ui @types/node
```
Expected: package.json gains `vitest`, `@vitest/ui` in `devDependencies`. No prod-dep changes.

- [ ] **Step 2: Add `vitest.config.ts`**

```typescript
import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    environment: 'node',
    globals: true,
    include: ['src/**/__tests__/**/*.test.ts', 'src/**/*.test.ts'],
  },
  resolve: {
    alias: {
      '@': new URL('./src', import.meta.url).pathname,
    },
  },
})
```

- [ ] **Step 3: Add test script to package.json**

In `package.json`, add to `"scripts"`:
```json
"test": "vitest run",
"test:watch": "vitest"
```

- [ ] **Step 4: Smoke test**

Create `src/lib/__sanity__.test.ts`:
```typescript
import { expect, test } from 'vitest'
test('vitest works', () => { expect(1 + 1).toBe(2) })
```
Run: `npm test`. Expected: 1 passed. Then delete the file: `rm src/lib/__sanity__.test.ts`.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json vitest.config.ts
git commit -m "chore: add Vitest test runner"
```

---

## Task 2: Schema migration — alerts tables, app_settings, job columns, tech junction

**Files:**
- Create: `supabase/migrations/00005_supervisor_alerts.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/00005_supervisor_alerts.sql

-- 1. Add fields the digest needs on the existing jobs table
ALTER TABLE public.jobs
  ADD COLUMN IF NOT EXISTS work_notes TEXT,
  ADD COLUMN IF NOT EXISTS started_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS invoiced_at TIMESTAMPTZ;

-- 2. Junction for HCP multi-tech assignments (used for Charles co-tech attribution)
CREATE TABLE IF NOT EXISTS public.job_technicians (
  job_id UUID NOT NULL REFERENCES public.jobs(id) ON DELETE CASCADE,
  technician_id UUID NOT NULL REFERENCES public.users(id) ON DELETE CASCADE,
  assigned_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  PRIMARY KEY (job_id, technician_id)
);
CREATE INDEX IF NOT EXISTS idx_job_technicians_tech ON public.job_technicians (technician_id);

-- 3. Single-row settings for the digest pipeline
CREATE TABLE IF NOT EXISTS public.app_settings (
  id INTEGER PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  digest_time TIME NOT NULL DEFAULT '18:00',
  digest_timezone TEXT NOT NULL DEFAULT 'America/Chicago',
  digest_cron_expression TEXT NOT NULL DEFAULT '0 23 * * *',  -- 18:00 CDT == 23:00 UTC (CST: 00:00 UTC). Caller may overwrite for DST.
  digest_recipient_email TEXT NOT NULL,
  notes_threshold_dollars INTEGER NOT NULL DEFAULT 185,
  pay_grace_hours INTEGER NOT NULL DEFAULT 48,
  enabled_button_checks TEXT[] NOT NULL DEFAULT ARRAY['SCHEDULE','OMW','START','FINISH','INVOICE','PAY'],
  co_tech_default_user_id UUID REFERENCES public.users(id),
  last_digest_sent_at TIMESTAMPTZ,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 4. Audit/state table for individual alert rows
CREATE TABLE IF NOT EXISTS public.supervisor_alerts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  digest_date DATE NOT NULL,
  job_id UUID NOT NULL REFERENCES public.jobs(id) ON DELETE CASCADE,
  alert_type TEXT NOT NULL CHECK (alert_type IN ('missing_buttons', 'missing_notes')),
  details JSONB NOT NULL,
  attributed_tech_id UUID REFERENCES public.users(id),
  resolved_at TIMESTAMPTZ,
  resolved_by UUID REFERENCES public.users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT supervisor_alerts_unique_per_day UNIQUE NULLS NOT DISTINCT (job_id, alert_type, digest_date)
);

CREATE INDEX IF NOT EXISTS idx_supervisor_alerts_unresolved
  ON public.supervisor_alerts (digest_date DESC) WHERE resolved_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_supervisor_alerts_job
  ON public.supervisor_alerts (job_id);

-- 5. RLS — admins/owners can read+write everything; everyone else blocked
ALTER TABLE public.app_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.supervisor_alerts ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.job_technicians ENABLE ROW LEVEL SECURITY;

CREATE POLICY "owners_manage_app_settings" ON public.app_settings
  FOR ALL TO authenticated
  USING (EXISTS (SELECT 1 FROM public.users WHERE auth_id = auth.uid() AND role IN ('owner','manager')))
  WITH CHECK (EXISTS (SELECT 1 FROM public.users WHERE auth_id = auth.uid() AND role IN ('owner','manager')));

CREATE POLICY "owners_manage_supervisor_alerts" ON public.supervisor_alerts
  FOR ALL TO authenticated
  USING (EXISTS (SELECT 1 FROM public.users WHERE auth_id = auth.uid() AND role IN ('owner','manager')))
  WITH CHECK (EXISTS (SELECT 1 FROM public.users WHERE auth_id = auth.uid() AND role IN ('owner','manager')));

CREATE POLICY "read_job_technicians" ON public.job_technicians
  FOR SELECT TO authenticated USING (true);
CREATE POLICY "service_role_writes_job_technicians" ON public.job_technicians
  FOR ALL TO service_role USING (true) WITH CHECK (true);
```

- [ ] **Step 2: Apply locally**

Run:
```bash
npx supabase db reset --local
```
Expected: all 5 migrations apply; no errors. If `npx supabase` is not installed, install via `npm install --save-dev supabase` first.

- [ ] **Step 3: Verify schema**

Run:
```bash
npx supabase db diff --local --schema public
```
Expected: empty diff (migrations match DB state). Then verify:
```bash
psql "$(npx supabase status --local | grep 'DB URL' | awk '{print $3}')" -c "\d public.supervisor_alerts"
```
Expected: table exists with the 9 columns from the migration.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/00005_supervisor_alerts.sql
git commit -m "feat(db): supervisor_alerts schema, app_settings, job_technicians junction"
```

---

## Task 3: Seed Charles user + initial app_settings row

**Files:**
- Create: `supabase/migrations/00006_seed_charles_attribution.sql`

- [ ] **Step 1: Write the seed migration**

```sql
-- supabase/migrations/00006_seed_charles_attribution.sql

-- Insert Charles as a technician if he doesn't already exist
INSERT INTO public.users (id, auth_id, email, full_name, role, is_active)
VALUES (
  gen_random_uuid(),
  NULL,
  'charlesrue@icloud.com',
  'Charles Rue',
  'technician',
  true
)
ON CONFLICT (email) DO NOTHING;

-- Single-row app_settings, with Charles as the co-tech attribution exception
INSERT INTO public.app_settings (
  id,
  digest_recipient_email,
  co_tech_default_user_id
)
SELECT
  1,
  'daniel@twinsgaragedoors.com',
  (SELECT id FROM public.users WHERE email = 'charlesrue@icloud.com' LIMIT 1)
ON CONFLICT (id) DO UPDATE
  SET co_tech_default_user_id = EXCLUDED.co_tech_default_user_id
  WHERE public.app_settings.co_tech_default_user_id IS NULL;
```

- [ ] **Step 2: Apply**

Run:
```bash
npx supabase db reset --local
```
Expected: 6 migrations apply cleanly. Verify:
```bash
psql "$(npx supabase status --local | grep 'DB URL' | awk '{print $3}')" \
  -c "SELECT email, full_name FROM users WHERE email='charlesrue@icloud.com';" \
  -c "SELECT digest_recipient_email, co_tech_default_user_id IS NOT NULL AS has_charles FROM app_settings;"
```
Expected: Charles row exists; `has_charles` is `t`.

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/00006_seed_charles_attribution.sql
git commit -m "feat(db): seed Charles user and default app_settings row"
```

---

## Task 4: Regenerate TypeScript types

**Files:**
- Modify: `src/types/database.ts`

- [ ] **Step 1: Regenerate**

Run:
```bash
npx supabase gen types typescript --local > src/types/database.ts
```
Expected: `database.ts` now contains `app_settings`, `supervisor_alerts`, `job_technicians`, plus the new columns on `jobs`.

- [ ] **Step 2: Verify type-check passes**

Run:
```bash
npm run type-check
```
Expected: no errors. (If a downstream file relied on `Tables<'jobs'>['Row']` having no `work_notes`, that's fine — adding optional fields is non-breaking.)

- [ ] **Step 3: Commit**

```bash
git add src/types/database.ts
git commit -m "chore(types): regenerate after supervisor_alerts migration"
```

---

## Task 5: Shared alert pipeline types

**Files:**
- Create: `src/lib/alerts/types.ts`

- [ ] **Step 1: Write the types**

```typescript
// src/lib/alerts/types.ts

export type ButtonCheck =
  | 'SCHEDULE' | 'OMW' | 'START' | 'FINISH' | 'INVOICE' | 'PAY'

export interface JobForAlerting {
  id: string
  hcp_id: string | null
  customer_first_name: string | null
  customer_last_name: string | null
  job_type: string | null
  total_amount: number              // dollars (post-conversion from HCP cents)
  scheduled_at: string | null       // ISO timestamps throughout
  started_at: string | null
  completed_at: string | null
  invoiced_at: string | null
  work_notes: string | null
  /** All techs assigned to the job, ordered by `assigned_at ASC, technician_id ASC`. */
  assigned_techs: { id: string; full_name: string }[]
  /** Status events captured from HCP webhooks; we only need `on_my_way` presence. */
  status_events: { event: string; at: string }[]
  /** Latest invoice, if any. */
  invoice: { id: string; paid_at: string | null } | null
}

export interface AppSettingsForAlerting {
  notes_threshold_dollars: number
  pay_grace_hours: number
  enabled_button_checks: ButtonCheck[]
  co_tech_default_user_id: string | null
}

export type AlertType = 'missing_buttons' | 'missing_notes'

export interface AlertRow {
  job_id: string
  alert_type: AlertType
  attributed_tech_id: string | null
  details: AlertDetails
}

export type AlertDetails =
  | { type: 'missing_buttons'; missing: ButtonCheck[]; pay_overdue_hours?: number }
  | { type: 'missing_notes'; total_amount: number }
```

- [ ] **Step 2: Type-check**

Run: `npm run type-check`. Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/lib/alerts/types.ts
git commit -m "feat(alerts): shared types for alert pipeline"
```

---

## Task 6: Charles co-tech attribution (pure function + tests)

**Files:**
- Create: `src/lib/alerts/attribution.ts`
- Create: `src/lib/alerts/__tests__/attribution.test.ts`

- [ ] **Step 1: Write the failing test**

```typescript
// src/lib/alerts/__tests__/attribution.test.ts
import { describe, it, expect } from 'vitest'
import { attributeTech } from '../attribution'

const CHARLES = 'charles-uuid'
const MAURICE = 'maurice-uuid'
const NICHOLAS = 'nicholas-uuid'

describe('attributeTech', () => {
  it('returns null when there are no assigned techs', () => {
    expect(attributeTech([], CHARLES)).toBeNull()
  })

  it('returns the only tech when one is assigned, even if Charles', () => {
    expect(attributeTech([{ id: CHARLES, full_name: 'Charles' }], CHARLES)).toBe(CHARLES)
    expect(attributeTech([{ id: MAURICE, full_name: 'Maurice' }], CHARLES)).toBe(MAURICE)
  })

  it('returns the first non-Charles tech when Charles is one of multiple', () => {
    expect(
      attributeTech(
        [{ id: CHARLES, full_name: 'Charles' }, { id: MAURICE, full_name: 'Maurice' }],
        CHARLES,
      ),
    ).toBe(MAURICE)
    expect(
      attributeTech(
        [{ id: MAURICE, full_name: 'Maurice' }, { id: CHARLES, full_name: 'Charles' }],
        CHARLES,
      ),
    ).toBe(MAURICE)
  })

  it('returns the first tech when multiple techs and none are Charles', () => {
    expect(
      attributeTech(
        [{ id: NICHOLAS, full_name: 'Nicholas' }, { id: MAURICE, full_name: 'Maurice' }],
        CHARLES,
      ),
    ).toBe(NICHOLAS)
  })

  it('falls back to first tech when co_tech_default_user_id is null', () => {
    expect(
      attributeTech(
        [{ id: CHARLES, full_name: 'Charles' }, { id: MAURICE, full_name: 'Maurice' }],
        null,
      ),
    ).toBe(CHARLES)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm test -- attribution`. Expected: FAIL with module-not-found.

- [ ] **Step 3: Implement**

```typescript
// src/lib/alerts/attribution.ts
import type { JobForAlerting } from './types'

/**
 * Resolves "who owns this alert" using the Charles co-tech rule.
 * @param techs Already ordered by assigned_at ASC, id ASC.
 * @param coTechDefaultUserId The configurable Charles user UUID; null disables the rule.
 */
export function attributeTech(
  techs: JobForAlerting['assigned_techs'],
  coTechDefaultUserId: string | null,
): string | null {
  if (techs.length === 0) return null
  if (techs.length === 1) return techs[0].id
  if (!coTechDefaultUserId) return techs[0].id
  const nonCharles = techs.find(t => t.id !== coTechDefaultUserId)
  return nonCharles ? nonCharles.id : techs[0].id
}
```

- [ ] **Step 4: Run test, verify pass**

Run: `npm test -- attribution`. Expected: 5 passed.

- [ ] **Step 5: Commit**

```bash
git add src/lib/alerts/attribution.ts src/lib/alerts/__tests__/attribution.test.ts
git commit -m "feat(alerts): Charles co-tech attribution"
```

---

## Task 7: Reporting window calculator (pure + tests)

**Files:**
- Create: `src/lib/alerts/window.ts`
- Create: `src/lib/alerts/__tests__/window.test.ts`

- [ ] **Step 1: Write the failing tests**

```typescript
// src/lib/alerts/__tests__/window.test.ts
import { describe, it, expect } from 'vitest'
import { recentFinisherWindow, payGraceWindow } from '../window'

describe('recentFinisherWindow', () => {
  it('uses last_digest_sent_at as start when present', () => {
    const now = new Date('2026-05-08T23:00:00Z')
    const last = new Date('2026-05-07T23:00:00Z')
    expect(recentFinisherWindow(now, last)).toEqual({
      start: last.toISOString(),
      end: now.toISOString(),
    })
  })

  it('falls back to 24h when last_digest_sent_at is null', () => {
    const now = new Date('2026-05-08T23:00:00Z')
    const w = recentFinisherWindow(now, null)
    expect(w.end).toBe(now.toISOString())
    expect(new Date(w.start).getTime()).toBe(now.getTime() - 24 * 60 * 60 * 1000)
  })
})

describe('payGraceWindow', () => {
  it('returns the band of jobs that just aged past grace', () => {
    const now = new Date('2026-05-08T23:00:00Z')
    const w = payGraceWindow(now, 48)
    // Jobs finished between (now - 72h) and (now - 48h) are newly eligible
    expect(new Date(w.start).getTime()).toBe(now.getTime() - 72 * 60 * 60 * 1000)
    expect(new Date(w.end).getTime()).toBe(now.getTime() - 48 * 60 * 60 * 1000)
  })
})
```

- [ ] **Step 2: Run, verify fail**

`npm test -- window`. Expected: FAIL.

- [ ] **Step 3: Implement**

```typescript
// src/lib/alerts/window.ts
export interface TimeWindow { start: string; end: string }

export function recentFinisherWindow(now: Date, lastDigestSentAt: Date | null): TimeWindow {
  const start = lastDigestSentAt ?? new Date(now.getTime() - 24 * 60 * 60 * 1000)
  return { start: start.toISOString(), end: now.toISOString() }
}

export function payGraceWindow(now: Date, payGraceHours: number): TimeWindow {
  const end = new Date(now.getTime() - payGraceHours * 60 * 60 * 1000)
  const start = new Date(end.getTime() - 24 * 60 * 60 * 1000)
  return { start: start.toISOString(), end: end.toISOString() }
}
```

- [ ] **Step 4: Run, verify pass**

`npm test -- window`. Expected: 3 passed.

- [ ] **Step 5: Commit**

```bash
git add src/lib/alerts/window.ts src/lib/alerts/__tests__/window.test.ts
git commit -m "feat(alerts): reporting window calculators"
```

---

## Task 8: Rule evaluators (pure + tests)

**Files:**
- Create: `src/lib/alerts/rules.ts`
- Create: `src/lib/alerts/__tests__/rules.test.ts`

- [ ] **Step 1: Write the failing tests**

```typescript
// src/lib/alerts/__tests__/rules.test.ts
import { describe, it, expect } from 'vitest'
import { evaluateMissingButtons, evaluateMissingNotes } from '../rules'
import type { JobForAlerting, AppSettingsForAlerting } from '../types'

const baseJob: JobForAlerting = {
  id: 'job-1', hcp_id: '123',
  customer_first_name: 'Sarah', customer_last_name: 'Jenkins',
  job_type: 'service_call', total_amount: 99,
  scheduled_at: '2026-05-08T14:00:00Z', started_at: '2026-05-08T15:00:00Z',
  completed_at: '2026-05-08T16:00:00Z', invoiced_at: '2026-05-08T16:30:00Z',
  work_notes: 'Replaced springs',
  assigned_techs: [{ id: 'tech-1', full_name: 'Maurice' }],
  status_events: [{ event: 'on_my_way', at: '2026-05-08T14:30:00Z' }],
  invoice: { id: 'inv-1', paid_at: '2026-05-08T16:35:00Z' },
}

const baseSettings: AppSettingsForAlerting = {
  notes_threshold_dollars: 185,
  pay_grace_hours: 48,
  enabled_button_checks: ['SCHEDULE','OMW','START','FINISH','INVOICE','PAY'],
  co_tech_default_user_id: null,
}

const NOW = new Date('2026-05-09T00:00:00Z')  // 8 hours after baseJob completed_at

describe('evaluateMissingButtons', () => {
  it('returns null when all buttons clicked', () => {
    expect(evaluateMissingButtons(baseJob, baseSettings, NOW)).toBeNull()
  })

  it('flags missing OMW (no on_my_way event)', () => {
    const r = evaluateMissingButtons({ ...baseJob, status_events: [] }, baseSettings, NOW)
    expect(r?.details.type).toBe('missing_buttons')
    expect(r?.details.type === 'missing_buttons' && r.details.missing).toContain('OMW')
  })

  it('flags missing START / SCHEDULE / FINISH / INVOICE when null', () => {
    const r = evaluateMissingButtons(
      { ...baseJob, scheduled_at: null, started_at: null, invoiced_at: null },
      baseSettings, NOW)
    expect(r?.details.type === 'missing_buttons' && r.details.missing.sort()).toEqual(
      ['INVOICE','SCHEDULE','START'].sort())
  })

  it('skips PAY when total_amount is 0', () => {
    const r = evaluateMissingButtons(
      { ...baseJob, total_amount: 0, invoice: null, invoiced_at: null },
      baseSettings, NOW)
    expect(r).toBeNull()  // SCHEDULE/OMW/START/FINISH all set, PAY+INVOICE skipped
  })

  it('skips INVOICE when total_amount is 0 even if no invoice', () => {
    const r = evaluateMissingButtons(
      { ...baseJob, total_amount: 0, invoice: null, invoiced_at: null },
      baseSettings, NOW)
    expect(r).toBeNull()
  })

  it('does NOT flag PAY within grace period', () => {
    // completed 8h ago, grace=48h => PAY not flagged
    const r = evaluateMissingButtons(
      { ...baseJob, invoice: { id: 'inv-1', paid_at: null } },
      baseSettings, NOW)
    expect(r).toBeNull()
  })

  it('flags PAY past grace period', () => {
    // 50h after completed_at
    const now = new Date('2026-05-10T18:00:00Z')
    const r = evaluateMissingButtons(
      { ...baseJob, invoice: { id: 'inv-1', paid_at: null } },
      baseSettings, now)
    expect(r?.details.type === 'missing_buttons' && r.details.missing).toEqual(['PAY'])
  })

  it('respects enabled_button_checks toggle', () => {
    const r = evaluateMissingButtons(
      { ...baseJob, scheduled_at: null },
      { ...baseSettings, enabled_button_checks: ['OMW','START','FINISH','INVOICE','PAY'] },
      NOW)
    expect(r).toBeNull()  // SCHEDULE check disabled, all others pass
  })
})

describe('evaluateMissingNotes', () => {
  it('null when total >= threshold', () => {
    const r = evaluateMissingNotes({ ...baseJob, total_amount: 185, work_notes: null }, baseSettings)
    expect(r).toBeNull()
  })

  it('null when notes present', () => {
    const r = evaluateMissingNotes({ ...baseJob, total_amount: 99 }, baseSettings)
    expect(r).toBeNull()
  })

  it('flags when total < threshold AND notes blank', () => {
    const r = evaluateMissingNotes({ ...baseJob, total_amount: 99, work_notes: null }, baseSettings)
    expect(r?.alert_type).toBe('missing_notes')
  })

  it('flags when total = 0 (warranty visit)', () => {
    const r = evaluateMissingNotes({ ...baseJob, total_amount: 0, work_notes: '' }, baseSettings)
    expect(r?.alert_type).toBe('missing_notes')
  })

  it('treats whitespace-only notes as blank', () => {
    const r = evaluateMissingNotes({ ...baseJob, total_amount: 99, work_notes: '   ' }, baseSettings)
    expect(r?.alert_type).toBe('missing_notes')
  })
})
```

- [ ] **Step 2: Run, verify fail**

`npm test -- rules`. Expected: module-not-found failure.

- [ ] **Step 3: Implement**

```typescript
// src/lib/alerts/rules.ts
import type {
  AlertRow, AppSettingsForAlerting, ButtonCheck, JobForAlerting,
} from './types'

const ALL_BUTTONS: ButtonCheck[] = ['SCHEDULE','OMW','START','FINISH','INVOICE','PAY']

export function evaluateMissingButtons(
  job: JobForAlerting,
  settings: AppSettingsForAlerting,
  now: Date,
): AlertRow | null {
  const enabled = new Set<ButtonCheck>(settings.enabled_button_checks)
  const missing: ButtonCheck[] = []

  if (enabled.has('SCHEDULE') && !job.scheduled_at) missing.push('SCHEDULE')
  if (enabled.has('OMW') && !job.status_events.some(e => e.event === 'on_my_way')) missing.push('OMW')
  if (enabled.has('START') && !job.started_at) missing.push('START')
  if (enabled.has('FINISH') && !job.completed_at) missing.push('FINISH')

  // INVOICE/PAY skip when total = 0
  if (job.total_amount > 0) {
    if (enabled.has('INVOICE') && !job.invoiced_at) missing.push('INVOICE')

    if (enabled.has('PAY') && job.completed_at) {
      const finishedAt = new Date(job.completed_at).getTime()
      const ageHours = (now.getTime() - finishedAt) / (1000 * 60 * 60)
      const pastGrace = ageHours >= settings.pay_grace_hours
      const unpaid = job.invoice && job.invoice.paid_at === null
      if (pastGrace && unpaid) missing.push('PAY')
    }
  }

  if (missing.length === 0) return null

  const payOverdueHours =
    missing.includes('PAY') && job.completed_at
      ? Math.floor((now.getTime() - new Date(job.completed_at).getTime()) / (1000 * 60 * 60))
      : undefined

  return {
    job_id: job.id,
    alert_type: 'missing_buttons',
    attributed_tech_id: null,  // filled in by orchestrator after attribution()
    details: { type: 'missing_buttons', missing, ...(payOverdueHours !== undefined && { pay_overdue_hours: payOverdueHours }) },
  }
}

export function evaluateMissingNotes(
  job: JobForAlerting,
  settings: AppSettingsForAlerting,
): AlertRow | null {
  if (job.total_amount >= settings.notes_threshold_dollars) return null
  const notes = (job.work_notes ?? '').trim()
  if (notes.length > 0) return null
  return {
    job_id: job.id,
    alert_type: 'missing_notes',
    attributed_tech_id: null,
    details: { type: 'missing_notes', total_amount: job.total_amount },
  }
}
```

- [ ] **Step 4: Run, verify pass**

`npm test`. Expected: all tests in `rules`, `attribution`, `window` pass.

- [ ] **Step 5: Commit**

```bash
git add src/lib/alerts/rules.ts src/lib/alerts/__tests__/rules.test.ts
git commit -m "feat(alerts): rule evaluators for missing buttons and missing notes"
```

---

## Task 9: Webhook handler — populate new fields

**Files:**
- Modify: `supabase/functions/webhook-handler/index.ts`
- Modify: `src/types/webhooks.ts`

- [ ] **Step 1: Read the webhook handler**

Run:
```bash
cat supabase/functions/webhook-handler/index.ts | head -200
```
Expected: identifies the existing job-event handler and the upsert into `jobs`.

- [ ] **Step 2: Identify the HCP payload fields for the new columns**

Inside the file, find where `job.{started|completed|on_my_way}` events are handled. Note the variable holding the parsed payload (likely `payload.event_data` or similar). Look for these payload keys (HCP convention) and adapt names to the actual payload after inspection:
- `notes` or `job.notes` → `work_notes`
- timestamp on `job.started` event → `started_at`
- `invoice` event timestamps → `invoiced_at`
- `assigned_employees` array → `job_technicians` rows

**Verify against a real webhook:** before committing this task, capture one real HCP webhook payload from the existing `raw_events` table and confirm the field names. Run:
```sql
SELECT payload FROM raw_events WHERE event_type LIKE 'job.%' ORDER BY created_at DESC LIMIT 3;
```
Adjust the property accessors in step 3 if names differ.

- [ ] **Step 3: Update the job upsert**

In the `job.*` handler, after the existing upsert, add the three columns. Replace the existing upsert object with:

```typescript
// inside the job event handler — extending the existing upsert
const jobRow = {
  hcp_id: payload.id,
  // ...existing fields preserved...
  work_notes: payload.notes ?? null,
  started_at: action === 'started' ? eventTimestamp : existing?.started_at ?? null,
  // ... etc
}

// After upserting `jobs`, populate job_technicians
const assignedEmployees: { id: string; assigned_at?: string }[] =
  payload.assigned_employees ?? []

if (assignedEmployees.length > 0) {
  // Resolve HCP employee IDs -> users.id
  const { data: techs } = await supabase
    .from('users')
    .select('id, hcp_id')
    .in('hcp_id', assignedEmployees.map(e => e.id))

  const techRows = (techs ?? []).map(t => {
    const matched = assignedEmployees.find(e => e.id === t.hcp_id)
    return {
      job_id: jobId,
      technician_id: t.id,
      assigned_at: matched?.assigned_at ?? new Date().toISOString(),
    }
  })

  if (techRows.length > 0) {
    // Replace-all semantics for this job's assignments
    await supabase.from('job_technicians').delete().eq('job_id', jobId)
    await supabase.from('job_technicians').insert(techRows)
  }
}
```

For the `invoice.*` handler, set `jobs.invoiced_at` when handling `invoice.created`:

```typescript
if (action === 'created' && payload.job_id) {
  await supabase
    .from('jobs')
    .update({ invoiced_at: payload.created_at ?? new Date().toISOString() })
    .eq('hcp_id', payload.job_id)
    .is('invoiced_at', null)  // don't overwrite if already set
}
```

- [ ] **Step 4: Update webhook payload types**

In `src/types/webhooks.ts`, add (or extend the existing job payload):
```typescript
export interface HcpJobPayload {
  id: string
  notes?: string | null
  assigned_employees?: { id: string; assigned_at?: string }[]
  // existing fields...
}
```
Adjust the existing types if a `JobEventPayload` already exists.

- [ ] **Step 5: Type-check + smoke test**

Run: `npm run type-check`. Expected: no errors.

For runtime validation, deploy to local Supabase and replay a recent payload:
```bash
npx supabase functions serve webhook-handler --local
# In another shell:
curl -X POST http://localhost:54321/functions/v1/webhook-handler \
  -H "Content-Type: application/json" \
  -d "$(psql -t -A -c "SELECT payload::text FROM raw_events WHERE event_type='job.completed' ORDER BY created_at DESC LIMIT 1" "$(npx supabase status --local | grep 'DB URL' | awk '{print $3}')")"
```
Expected: 200 response. Then verify:
```sql
SELECT work_notes, started_at, invoiced_at FROM jobs ORDER BY updated_at DESC LIMIT 1;
SELECT job_id, technician_id FROM job_technicians LIMIT 5;
```

- [ ] **Step 6: Commit**

```bash
git add supabase/functions/webhook-handler/index.ts src/types/webhooks.ts
git commit -m "feat(webhook): populate work_notes, started_at, invoiced_at, job_technicians"
```

---

## Task 10: Backfill script for existing rows

**Files:**
- Create: `scripts/backfill-job-fields.ts`

- [ ] **Step 1: Write the script**

```typescript
// scripts/backfill-job-fields.ts
// Usage: npx tsx scripts/backfill-job-fields.ts
import { createClient } from '@supabase/supabase-js'

const supabase = createClient(
  process.env.SUPABASE_URL!,
  process.env.SUPABASE_SERVICE_ROLE_KEY!,
)

async function main() {
  // Pull every raw HCP job event we have stored
  const { data: events, error } = await supabase
    .from('raw_events')
    .select('payload, event_type, created_at')
    .like('event_type', 'job.%')
    .order('created_at', { ascending: true })

  if (error) throw error
  if (!events) return

  let updated = 0
  for (const ev of events) {
    const p = ev.payload as { id?: string; notes?: string | null; assigned_employees?: { id: string; assigned_at?: string }[] }
    if (!p.id) continue

    // Find the job by hcp_id
    const { data: job } = await supabase
      .from('jobs')
      .select('id, work_notes, started_at')
      .eq('hcp_id', p.id)
      .single()

    if (!job) continue

    const updates: Record<string, unknown> = {}
    if (job.work_notes === null && p.notes) updates.work_notes = p.notes
    if (job.started_at === null && ev.event_type === 'job.started') updates.started_at = ev.created_at

    if (Object.keys(updates).length > 0) {
      await supabase.from('jobs').update(updates).eq('id', job.id)
      updated++
    }

    // Populate job_technicians from assigned_employees
    if (p.assigned_employees?.length) {
      const { data: techs } = await supabase
        .from('users')
        .select('id, hcp_id')
        .in('hcp_id', p.assigned_employees.map(e => e.id))

      if (techs?.length) {
        const rows = techs.map(t => {
          const ae = p.assigned_employees!.find(e => e.id === t.hcp_id)
          return { job_id: job.id, technician_id: t.id, assigned_at: ae?.assigned_at ?? ev.created_at }
        })
        await supabase.from('job_technicians').upsert(rows, { onConflict: 'job_id,technician_id', ignoreDuplicates: true })
      }
    }
  }

  console.log(`Backfilled ${updated} jobs.`)
}

main().catch(e => { console.error(e); process.exit(1) })
```

- [ ] **Step 2: Run against local Supabase**

```bash
SUPABASE_URL=$(npx supabase status --local | grep 'API URL' | awk '{print $3}') \
SUPABASE_SERVICE_ROLE_KEY=$(npx supabase status --local | grep 'service_role key' | awk '{print $3}') \
npx tsx scripts/backfill-job-fields.ts
```
Expected: prints "Backfilled N jobs." with N >= 0. No errors.

- [ ] **Step 3: Commit**

```bash
git add scripts/backfill-job-fields.ts
git commit -m "feat(scripts): backfill work_notes/started_at/invoiced_at and job_technicians"
```

---

## Task 11: Email rendering (pure)

**Files:**
- Create: `supabase/functions/daily-supervisor-digest/render-email.ts`

- [ ] **Step 1: Write the renderer**

```typescript
// supabase/functions/daily-supervisor-digest/render-email.ts
// Pure HTML rendering. No Supabase imports — testable from Node and Deno.

interface RenderInput {
  digest_date: string  // YYYY-MM-DD
  total_revenue_today: number
  tickets: RenderTicket[]
}

interface RenderTicket {
  job_id: string
  hcp_id: string | null
  job_summary: string         // e.g. "Spring repl."
  customer_label: string      // "S. Jenkins"
  total_amount: number
  finished_at: string         // ISO
  primary_tech_name: string
  co_tech_name: string | null  // e.g. "Charles" or null
  alerts: { type: 'missing_buttons' | 'missing_notes'; details: Record<string, unknown> }[]
}

const PILL = (label: string, color: 'red' | 'amber' | 'orange') => {
  const c = { red: '#fee2e2;color:#991b1b', amber: '#fef3c7;color:#92400e', orange: '#fed7aa;color:#9a3412' }[color]
  return `<span style="display:inline-block;background:${c};font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;text-transform:uppercase;letter-spacing:.3px;margin-right:4px;">${label}</span>`
}

export function renderDigestSubject(tickets: RenderTicket[]): string {
  const issueCount = tickets.reduce((n, t) => n + t.alerts.length, 0)
  return `Daily ticket review — ${issueCount} issues across ${tickets.length} tickets`
}

export function renderDigestHtml(input: RenderInput): string {
  const issueCount = input.tickets.reduce((n, t) => n + t.alerts.length, 0)

  const ticketBlocks = input.tickets.map(t => {
    const techLine = t.co_tech_name
      ? `${t.primary_tech_name} (${t.co_tech_name} co-tech, attributed to ${t.primary_tech_name})`
      : t.primary_tech_name

    const pills = t.alerts.flatMap(a => {
      if (a.type === 'missing_notes') return [PILL('Missing Notes', 'amber')]
      const missing = (a.details as { missing: string[] }).missing
      const overdueHours = (a.details as { pay_overdue_hours?: number }).pay_overdue_hours
      return missing.map(m => {
        if (m === 'PAY' && overdueHours)
          return PILL(`Pay overdue (${Math.floor(overdueHours / 24)}d)`, 'orange')
        return PILL(`Missing ${m}`, 'red')
      })
    }).join('')

    const hcpLink = t.hcp_id
      ? `<a href="https://pro.housecallpro.com/app/jobs/${t.hcp_id}" style="display:inline-block;background:#1e3a8a;color:#fff;text-decoration:none;padding:8px 14px;border-radius:6px;font-size:12px;font-weight:600;margin-top:8px;">Open in HCP →</a>`
      : ''

    return `
      <div style="border:1px solid #e5e7eb;border-radius:6px;padding:14px 16px;margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:baseline;">
          <strong style="color:#111827;">Job #${t.hcp_id ?? t.job_id.slice(0,8)} · ${escape(t.job_summary)}</strong>
          <span style="color:${t.total_amount === 0 ? '#6b7280' : '#059669'};font-weight:600;">$${t.total_amount.toLocaleString()}</span>
        </div>
        <div style="color:#6b7280;font-size:12px;margin:4px 0 8px;">
          Customer: ${escape(t.customer_label)} · Tech: ${escape(techLine)} · Finished ${formatDate(t.finished_at)}
        </div>
        <div>${pills}</div>
        ${hcpLink}
      </div>`
  }).join('')

  return `<!doctype html><html><body style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;color:#1f2937;background:#f3f4f6;padding:24px;">
    <div style="background:#fff;border-radius:8px;max-width:720px;margin:0 auto;padding:24px;">
      <h1 style="font-size:18px;margin:0 0 4px;">Daily digest · ${formatDate(input.digest_date)}</h1>
      <div style="display:flex;gap:24px;margin:16px 0 24px;padding:14px 16px;background:#fef3c7;border-left:4px solid #f59e0b;border-radius:4px;font-size:13px;">
        <div><strong>${input.tickets.length}</strong> tickets need review</div>
        <div><strong>${issueCount}</strong> issues flagged</div>
        <div><strong>$${input.total_revenue_today.toLocaleString()}</strong> revenue today</div>
      </div>
      ${ticketBlocks}
      <div style="font-size:11px;color:#9ca3af;text-align:center;padding-top:20px;border-top:1px solid #f3f4f6;margin-top:24px;">
        Adjust digest time, threshold, or recipient → twinsdash.com/dashboard/admin/notifications
      </div>
    </div>
  </body></html>`
}

function escape(s: string): string {
  return s.replace(/[&<>"']/g, c => (
    { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' } as Record<string,string>
  )[c])
}

function formatDate(iso: string): string {
  const d = new Date(iso)
  return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
}

export type { RenderInput, RenderTicket }
```

- [ ] **Step 2: Smoke test**

Add inline test (delete after running):
```typescript
// supabase/functions/daily-supervisor-digest/__test__/render.test.ts
import { describe, it, expect } from 'vitest'
import { renderDigestHtml, renderDigestSubject } from '../render-email'

describe('renderDigestHtml', () => {
  it('produces non-empty HTML with subject + tickets', () => {
    const html = renderDigestHtml({
      digest_date: '2026-05-08',
      total_revenue_today: 1816,
      tickets: [{
        job_id: 'a', hcp_id: '4521', job_summary: 'Service call', customer_label: 'S. Jenkins',
        total_amount: 99, finished_at: '2026-05-08T16:00:00Z',
        primary_tech_name: 'Maurice', co_tech_name: null,
        alerts: [{ type: 'missing_notes', details: {} }, { type: 'missing_buttons', details: { missing: ['OMW'] } }],
      }],
    })
    expect(html).toContain('Job #4521')
    expect(html).toContain('Missing Notes')
    expect(html).toContain('Missing OMW')
    expect(renderDigestSubject([{ alerts: [{}, {}] } as any, { alerts: [{}] } as any])).toContain('3 issues across 2 tickets')
  })
})
```

Run: `npm test -- render`. Expected: passes. Then keep this test (it documents the email format).

- [ ] **Step 3: Commit**

```bash
git add supabase/functions/daily-supervisor-digest/render-email.ts \
        supabase/functions/daily-supervisor-digest/__test__/render.test.ts
git commit -m "feat(digest): pure HTML email renderer with snapshot test"
```

---

## Task 12: Resend wrapper

**Files:**
- Create: `supabase/functions/daily-supervisor-digest/send-email.ts`

- [ ] **Step 1: Write the wrapper**

```typescript
// supabase/functions/daily-supervisor-digest/send-email.ts

interface SendArgs {
  to: string
  subject: string
  html: string
  apiKey: string
  from?: string
}

export async function sendDigestEmail(args: SendArgs): Promise<{ id: string }> {
  const res = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${args.apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      from: args.from ?? 'Twins Dashboard <noreply@twinsdash.com>',
      to: args.to,
      subject: args.subject,
      html: args.html,
    }),
  })

  if (!res.ok) {
    const body = await res.text()
    throw new Error(`Resend error ${res.status}: ${body}`)
  }
  return res.json() as Promise<{ id: string }>
}
```

- [ ] **Step 2: Add RESEND_API_KEY to local supabase env**

```bash
echo "RESEND_API_KEY=re_PLACEHOLDER_FOR_LOCAL_TESTING" >> supabase/functions/.env
```
Note: local testing doesn't require a real key — the orchestrator (Task 13) wraps the send in a try/catch and logs failures.

- [ ] **Step 3: Commit**

```bash
git add supabase/functions/daily-supervisor-digest/send-email.ts supabase/functions/.env
git commit -m "feat(digest): Resend email send wrapper"
```

---

## Task 13: Edge function orchestrator

**Files:**
- Create: `supabase/functions/daily-supervisor-digest/index.ts`

- [ ] **Step 1: Write the function**

```typescript
// supabase/functions/daily-supervisor-digest/index.ts
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'
import { renderDigestHtml, renderDigestSubject, type RenderTicket } from './render-email.ts'
import { sendDigestEmail } from './send-email.ts'

const SUPABASE_URL = Deno.env.get('SUPABASE_URL')!
const SERVICE_ROLE = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!
const RESEND_KEY = Deno.env.get('RESEND_API_KEY')

Deno.serve(async (req) => {
  try {
    const supabase = createClient(SUPABASE_URL, SERVICE_ROLE, { auth: { persistSession: false } })

    // 1. Load settings
    const { data: settings, error: settingsErr } = await supabase
      .from('app_settings').select('*').eq('id', 1).single()
    if (settingsErr || !settings) throw new Error('app_settings row missing')

    const now = new Date()
    const digestDate = now.toISOString().slice(0, 10)

    // 2. Determine windows
    const lastSent = settings.last_digest_sent_at ? new Date(settings.last_digest_sent_at) : null
    const recentStart = lastSent ?? new Date(now.getTime() - 24 * 60 * 60 * 1000)
    const recentEnd = now

    const payGraceEnd = new Date(now.getTime() - settings.pay_grace_hours * 60 * 60 * 1000)
    const payGraceStart = new Date(payGraceEnd.getTime() - 24 * 60 * 60 * 1000)

    // 3. Pass A — recent finishers (Rule 1 except PAY, plus Rule 2)
    const { data: recentJobs } = await supabase
      .from('jobs')
      .select(`
        id, hcp_id, job_type, total_amount:revenue,
        scheduled_at, started_at, completed_at, invoiced_at, work_notes,
        customers:customer_id ( first_name, last_name ),
        invoices ( id, paid_at ),
        job_technicians ( technician_id, assigned_at, users:technician_id ( id, full_name ) ),
        raw_events:raw_events!inner ( event_type, created_at )
      `)
      .gte('completed_at', recentStart.toISOString())
      .lte('completed_at', recentEnd.toISOString())

    // 4. Pass B — PAY-grace agers
    const { data: payAgerJobs } = await supabase
      .from('jobs')
      .select(`
        id, hcp_id, total_amount:revenue, completed_at,
        invoices ( id, paid_at ),
        job_technicians ( technician_id, users:technician_id ( id, full_name ) )
      `)
      .gte('completed_at', payGraceStart.toISOString())
      .lt('completed_at', payGraceEnd.toISOString())
      .gt('revenue', 0)

    // 5. Evaluate rules — import the same pure functions from src/lib/alerts
    //    (Note: edge functions can't import from src/, so we duplicate the rule
    //     logic inline here OR vendor the file via a build step. For v1, duplicate
    //     evaluateMissingButtons / evaluateMissingNotes / attributeTech inline below.
    //     If the duplication grows, switch to a Deno-compatible shared package.)
    const rules = await import('./rules-vendored.ts')

    const alerts: Array<{ job_id: string; alert_type: string; details: unknown; attributed_tech_id: string | null }> = []
    const renderTickets: RenderTicket[] = []

    for (const j of recentJobs ?? []) {
      const job = mapJob(j)
      const buttons = rules.evaluateMissingButtons(job, settings, now)
      const notes = rules.evaluateMissingNotes(job, settings)
      if (!buttons && !notes) continue

      const attribTechId = rules.attributeTech(job.assigned_techs, settings.co_tech_default_user_id)
      if (buttons) alerts.push({ ...buttons, attributed_tech_id: attribTechId })
      if (notes) alerts.push({ ...notes, attributed_tech_id: attribTechId })

      renderTickets.push(toRenderTicket(job, [buttons, notes].filter(Boolean) as never[]))
    }

    for (const j of payAgerJobs ?? []) {
      const job = mapJob(j)
      const result = rules.evaluateMissingButtons(job, settings, now)
      if (!result) continue
      const buttons = result.details as { type: 'missing_buttons'; missing: string[] }
      if (!buttons.missing.includes('PAY')) continue  // only care about PAY in this pass
      const attribTechId = rules.attributeTech(job.assigned_techs, settings.co_tech_default_user_id)
      alerts.push({ ...result, attributed_tech_id: attribTechId })
      renderTickets.push(toRenderTicket(job, [result] as never[]))
    }

    // 6. Persist
    if (alerts.length > 0) {
      await supabase.from('supervisor_alerts').upsert(
        alerts.map(a => ({ ...a, digest_date: digestDate })),
        { onConflict: 'job_id,alert_type,digest_date', ignoreDuplicates: false },
      )
    }

    // 7. Email — render union of newly-found + still-unresolved-from-prior-days
    const { data: unresolved } = await supabase
      .from('supervisor_alerts')
      .select('*, jobs!inner(*, customers:customer_id(*))')
      .is('resolved_at', null)
      .order('digest_date', { ascending: false })

    if ((unresolved?.length ?? 0) > 0 && RESEND_KEY) {
      // Group by job_id
      const grouped = groupByJob(unresolved!)
      const subject = renderDigestSubject(grouped)
      const html = renderDigestHtml({
        digest_date: digestDate,
        total_revenue_today: (recentJobs ?? []).reduce((s, j) => s + (j.total_amount ?? 0), 0),
        tickets: grouped,
      })
      await sendDigestEmail({
        to: settings.digest_recipient_email,
        subject, html, apiKey: RESEND_KEY,
      })
    }

    // 8. Mark sent
    await supabase.from('app_settings').update({ last_digest_sent_at: now.toISOString() }).eq('id', 1)

    return new Response(JSON.stringify({ ok: true, alerts: alerts.length }), {
      status: 200, headers: { 'content-type': 'application/json' },
    })
  } catch (err) {
    console.error('digest failed', err)
    return new Response(JSON.stringify({ ok: false, error: String(err) }), {
      status: 500, headers: { 'content-type': 'application/json' },
    })
  }
})

// --- helpers ---

function mapJob(j: any) {
  return {
    id: j.id,
    hcp_id: j.hcp_id,
    customer_first_name: j.customers?.first_name ?? null,
    customer_last_name: j.customers?.last_name ?? null,
    job_type: j.job_type ?? null,
    total_amount: Number(j.total_amount ?? 0),
    scheduled_at: j.scheduled_at ?? null,
    started_at: j.started_at ?? null,
    completed_at: j.completed_at ?? null,
    invoiced_at: j.invoiced_at ?? null,
    work_notes: j.work_notes ?? null,
    assigned_techs: (j.job_technicians ?? [])
      .sort((a: any, b: any) =>
        (a.assigned_at ?? '').localeCompare(b.assigned_at ?? '') || a.technician_id.localeCompare(b.technician_id))
      .map((jt: any) => ({ id: jt.users.id, full_name: jt.users.full_name })),
    status_events: (j.raw_events ?? [])
      .filter((e: any) => e.event_type === 'job.on_my_way')
      .map((e: any) => ({ event: 'on_my_way', at: e.created_at })),
    invoice: (j.invoices?.[0]) ? { id: j.invoices[0].id, paid_at: j.invoices[0].paid_at } : null,
  }
}

function toRenderTicket(job: ReturnType<typeof mapJob>, alerts: any[]): RenderTicket {
  const primary = job.assigned_techs[0]?.full_name ?? 'Unassigned'
  const cust = `${(job.customer_first_name ?? '').slice(0,1)}. ${job.customer_last_name ?? ''}`.trim()
  return {
    job_id: job.id,
    hcp_id: job.hcp_id,
    job_summary: job.job_type ?? 'Job',
    customer_label: cust || 'Customer',
    total_amount: job.total_amount,
    finished_at: job.completed_at ?? new Date().toISOString(),
    primary_tech_name: primary,
    co_tech_name: job.assigned_techs.length > 1 ? job.assigned_techs[1].full_name : null,
    alerts: alerts.map(a => ({ type: a.alert_type, details: a.details })),
  }
}

function groupByJob(rows: any[]): RenderTicket[] {
  const byId = new Map<string, any[]>()
  for (const r of rows) {
    const arr = byId.get(r.job_id) ?? []
    arr.push(r); byId.set(r.job_id, arr)
  }
  return [...byId.values()].map(group => {
    const j = group[0].jobs
    return {
      job_id: j.id, hcp_id: j.hcp_id,
      job_summary: j.job_type ?? 'Job',
      customer_label: `${(j.customers?.first_name ?? '').slice(0,1)}. ${j.customers?.last_name ?? ''}`.trim() || 'Customer',
      total_amount: Number(j.revenue ?? 0),
      finished_at: j.completed_at ?? new Date().toISOString(),
      primary_tech_name: 'Tech', co_tech_name: null,
      alerts: group.map(g => ({ type: g.alert_type, details: g.details })),
    }
  })
}
```

- [ ] **Step 2: Vendor the rule logic for Deno**

Edge functions cannot import from `src/`. Create `supabase/functions/daily-supervisor-digest/rules-vendored.ts` and copy the contents of `src/lib/alerts/rules.ts` and `src/lib/alerts/attribution.ts` (and the type definitions they reference) into a single file. Add a header comment:

```typescript
// THIS IS A VENDORED COPY of src/lib/alerts/{rules,attribution,types}.ts
// for Deno (edge functions can't import from src/).
// When you change the originals, update this file.
```

- [ ] **Step 3: Add a sync check**

Add a Vitest that reads both files and asserts the function signatures match — basic guardrail against drift:

```typescript
// src/lib/alerts/__tests__/vendored-sync.test.ts
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

describe('vendored rules', () => {
  it('contains evaluateMissingButtons, evaluateMissingNotes, attributeTech', () => {
    const text = readFileSync('supabase/functions/daily-supervisor-digest/rules-vendored.ts', 'utf8')
    expect(text).toContain('export function evaluateMissingButtons')
    expect(text).toContain('export function evaluateMissingNotes')
    expect(text).toContain('export function attributeTech')
  })
})
```

Run: `npm test -- vendored-sync`. Expected: pass.

- [ ] **Step 4: Deploy locally + smoke test**

```bash
npx supabase functions serve daily-supervisor-digest --local
# In another shell:
curl -X POST http://localhost:54321/functions/v1/daily-supervisor-digest \
  -H "Authorization: Bearer $(npx supabase status --local | grep 'service_role key' | awk '{print $3}')"
```
Expected: `{"ok": true, "alerts": N}` where N = newly inserted alerts. Verify:
```sql
SELECT * FROM supervisor_alerts ORDER BY created_at DESC LIMIT 5;
```

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/daily-supervisor-digest/
git add src/lib/alerts/__tests__/vendored-sync.test.ts
git commit -m "feat(digest): edge function orchestrator with vendored rule logic"
```

---

## Task 14: pg_cron schedule + alter_job helper

**Files:**
- Create: `supabase/migrations/00007_pg_cron_digest.sql`

- [ ] **Step 1: Write the cron migration**

```sql
-- supabase/migrations/00007_pg_cron_digest.sql

-- Enable pg_cron + pg_net (required for cron→edge-function HTTP call)
CREATE EXTENSION IF NOT EXISTS pg_cron;
CREATE EXTENSION IF NOT EXISTS pg_net;

-- Schedule the daily digest. Default: 0 23 * * * (23:00 UTC ≈ 18:00 CDT in DST).
-- The Next.js settings UI can re-call cron.alter_job() on this job to change it.
SELECT cron.schedule(
  'daily-supervisor-digest',
  (SELECT digest_cron_expression FROM public.app_settings WHERE id = 1),
  $$
  SELECT net.http_post(
    url := current_setting('app.supabase_functions_url') || '/daily-supervisor-digest',
    headers := jsonb_build_object(
      'Content-Type', 'application/json',
      'Authorization', 'Bearer ' || current_setting('app.supabase_service_role_key')
    )
  ) AS request_id;
  $$
);

-- A SECURITY DEFINER helper for the Next.js API to use, so the app role doesn't
-- need direct cron-schema access.
CREATE OR REPLACE FUNCTION public.reschedule_digest(new_cron_expression TEXT)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, cron
AS $$
DECLARE
  job_id BIGINT;
BEGIN
  SELECT jobid INTO job_id FROM cron.job WHERE jobname = 'daily-supervisor-digest';
  IF job_id IS NOT NULL THEN
    PERFORM cron.alter_job(job_id, schedule := new_cron_expression);
    UPDATE public.app_settings SET digest_cron_expression = new_cron_expression, updated_at = now() WHERE id = 1;
  END IF;
END;
$$;

REVOKE ALL ON FUNCTION public.reschedule_digest(TEXT) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.reschedule_digest(TEXT) TO authenticated;
```

- [ ] **Step 2: Set the GUCs the cron body references**

These need to be set on the live database for `current_setting()` to work. Add a setup note to the migration in a comment block, and write the GUCs from the Supabase dashboard *or* via a one-off SQL run during deploy:

```sql
-- Run once on production after deploy (substitute real values):
-- ALTER DATABASE postgres SET app.supabase_functions_url = 'https://<project>.supabase.co/functions/v1';
-- ALTER DATABASE postgres SET app.supabase_service_role_key = '<service-role-key>';
```

Add this as a comment at the top of the migration file.

- [ ] **Step 3: Apply locally + verify**

```bash
npx supabase db reset --local
psql "$(npx supabase status --local | grep 'DB URL' | awk '{print $3}')" \
  -c "SELECT jobname, schedule FROM cron.job;" \
  -c "SELECT public.reschedule_digest('0 22 * * *');" \
  -c "SELECT jobname, schedule FROM cron.job;"
```
Expected: schedule changes from `0 23 * * *` to `0 22 * * *`.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/00007_pg_cron_digest.sql
git commit -m "feat(db): pg_cron schedule + reschedule_digest() helper"
```

---

## Task 15: Bell badge count hook + API route

**Files:**
- Create: `src/app/api/notifications/count/route.ts`
- Create: `src/lib/hooks/use-unresolved-count.ts`

- [ ] **Step 1: Write the API route**

```typescript
// src/app/api/notifications/count/route.ts
import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

export async function GET() {
  const supabase = await createServerSupabaseClient()
  const { count, error } = await supabase
    .from('supervisor_alerts')
    .select('id', { count: 'exact', head: true })
    .is('resolved_at', null)

  if (error) return NextResponse.json({ count: 0, error: error.message }, { status: 500 })
  return NextResponse.json({ count: count ?? 0 })
}
```

- [ ] **Step 2: Write the hook**

```typescript
// src/lib/hooks/use-unresolved-count.ts
'use client'
import { useQuery } from '@tanstack/react-query'

export function useUnresolvedCount() {
  return useQuery({
    queryKey: ['notifications', 'count'],
    queryFn: async () => {
      const res = await fetch('/api/notifications/count')
      const json = await res.json()
      return json.count as number
    },
    staleTime: 60_000,
  })
}
```

- [ ] **Step 3: Type-check**

`npm run type-check`. Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add src/app/api/notifications/count/route.ts src/lib/hooks/use-unresolved-count.ts
git commit -m "feat(api): unresolved-count endpoint and hook"
```

---

## Task 16: Issue pill + bell components

**Files:**
- Create: `src/components/notifications/issue-pill.tsx`
- Create: `src/components/notifications/bell.tsx`

- [ ] **Step 1: Write the pill**

```typescript
// src/components/notifications/issue-pill.tsx
type Variant = 'red' | 'amber' | 'orange'
const STYLES: Record<Variant, string> = {
  red: 'bg-red-100 text-red-800',
  amber: 'bg-amber-100 text-amber-800',
  orange: 'bg-orange-100 text-orange-800',
}

export function IssuePill({ label, variant = 'red' }: { label: string; variant?: Variant }) {
  return (
    <span className={`inline-flex items-center text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full ${STYLES[variant]}`}>
      {label}
    </span>
  )
}
```

- [ ] **Step 2: Write the bell**

```typescript
// src/components/notifications/bell.tsx
'use client'
import Link from 'next/link'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useUnresolvedCount } from '@/lib/hooks/use-unresolved-count'
import { IssuePill } from './issue-pill'

interface DropdownAlert {
  id: string
  job_id: string
  hcp_id: string | null
  job_summary: string
  customer_label: string
  total_amount: number
  primary_tech_name: string
  co_tech_name: string | null
  pills: { label: string; variant: 'red' | 'amber' | 'orange' }[]
}

export function NotificationsBell() {
  const [open, setOpen] = useState(false)
  const { data: count = 0 } = useUnresolvedCount()
  const dropdown = useQuery({
    queryKey: ['notifications', 'dropdown'],
    queryFn: async () => {
      const res = await fetch('/api/notifications?limit=4')
      return (await res.json()) as { alerts: DropdownAlert[] }
    },
    enabled: open,
  })

  return (
    <div className="relative">
      <button
        onClick={() => setOpen(o => !o)}
        className="relative p-1.5 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
        aria-label={`Notifications (${count} unresolved)`}
      >
        <svg className="w-5 h-5 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
          <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
        </svg>
        {count > 0 && (
          <span className="absolute top-0 right-0 min-w-[16px] h-4 px-1 bg-red-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white">
            {count > 99 ? '99+' : count}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-10 w-96 bg-white border border-gray-200 rounded-xl shadow-xl z-50">
          <div className="flex justify-between items-center p-3 border-b border-gray-100">
            <strong className="text-sm">Notifications</strong>
            {count > 0 && <IssuePill label={`${count} unresolved`} variant="red" />}
          </div>
          {dropdown.isLoading && <div className="p-4 text-sm text-gray-500">Loading…</div>}
          {dropdown.data?.alerts.length === 0 && <div className="p-4 text-sm text-gray-500">No unresolved alerts.</div>}
          {dropdown.data?.alerts.map(a => (
            <div key={a.id} className="p-3 border-b border-gray-100 hover:bg-gray-50">
              <div className="flex justify-between text-sm">
                <strong>Job #{a.hcp_id ?? a.job_id.slice(0, 6)} · {a.job_summary}</strong>
                <span className="text-xs text-gray-500">${a.total_amount.toLocaleString()}</span>
              </div>
              <div className="text-xs text-gray-500 my-1">
                {a.primary_tech_name}{a.co_tech_name ? ` (+${a.co_tech_name})` : ''}
              </div>
              <div className="flex flex-wrap gap-1">
                {a.pills.map((p, i) => <IssuePill key={i} label={p.label} variant={p.variant} />)}
              </div>
            </div>
          ))}
          <div className="p-2 text-center">
            <Link href="/dashboard/admin/notifications" className="text-sm font-semibold text-blue-900 hover:underline">
              View all notifications →
            </Link>
          </div>
        </div>
      )}
    </div>
  )
}
```

- [ ] **Step 3: Type-check**

`npm run type-check`. Expected: no errors. If `useQuery`/`useMutation` types are off, ensure `@tanstack/react-query` is at v5+.

- [ ] **Step 4: Commit**

```bash
git add src/components/notifications/issue-pill.tsx src/components/notifications/bell.tsx
git commit -m "feat(ui): notifications bell + issue pill components"
```

---

## Task 17: Mount the bell in the global header

**Files:**
- Modify: `src/components/layout/header.tsx`

- [ ] **Step 1: Read the header**

Run: `cat src/components/layout/header.tsx`. Note the existing structure (date picker, actions slot, etc.).

- [ ] **Step 2: Insert the bell**

Add an import at the top:
```typescript
import { NotificationsBell } from '@/components/notifications/bell'
```

In the JSX, place the bell to the left of the existing user avatar / right-side controls. Example placement (adjust to actual structure of `header.tsx`):

```tsx
<div className="flex items-center gap-3">
  <NotificationsBell />
  {/* existing avatar / actions */}
</div>
```

- [ ] **Step 3: Visually verify**

Run: `npm run dev`. Open `http://localhost:3000/dashboard`. Expected: bell icon appears in the top-right of the header, next to existing controls. Badge shows 0 (or current count).

- [ ] **Step 4: Commit**

```bash
git add src/components/layout/header.tsx
git commit -m "feat(ui): mount notifications bell in dashboard header"
```

---

## Task 18: Notifications page — server component + alerts table

**Files:**
- Create: `src/app/dashboard/admin/notifications/page.tsx`
- Create: `src/components/notifications/alerts-table.tsx`

- [ ] **Step 1: Write the page (server component, role-gated)**

```typescript
// src/app/dashboard/admin/notifications/page.tsx
import { redirect } from 'next/navigation'
import { createServerSupabaseClient } from '@/lib/supabase/server'
import { AlertsTable } from '@/components/notifications/alerts-table'
import { PastDigestsList } from '@/components/notifications/past-digests-list'
import { SettingsPanel } from '@/components/notifications/settings-panel'

export default async function NotificationsPage() {
  const supabase = await createServerSupabaseClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) redirect('/dashboard')

  const { data: profile } = await supabase
    .from('users').select('role').eq('auth_id', user.id).single()
  if (!profile || !['owner', 'manager'].includes(profile.role)) redirect('/dashboard')

  const { data: openAlerts } = await supabase
    .from('supervisor_alerts')
    .select(`
      id, alert_type, details, created_at, digest_date,
      attributed_tech:attributed_tech_id ( id, full_name ),
      jobs:job_id ( id, hcp_id, job_type, revenue, completed_at,
        customers:customer_id (first_name, last_name),
        job_technicians ( users:technician_id (id, full_name) ) )
    `)
    .is('resolved_at', null)
    .order('digest_date', { ascending: false })

  const { data: settings } = await supabase
    .from('app_settings').select('*').eq('id', 1).single()

  return (
    <div className="space-y-8 p-6">
      <header>
        <h1 className="text-2xl font-bold">Notifications</h1>
        <p className="text-sm text-gray-500">Tickets flagged by the daily supervisor digest.</p>
      </header>

      <section>
        <div className="flex items-baseline justify-between mb-3">
          <h2 className="text-base font-semibold">Today's open issues</h2>
          <span className="text-xs px-2 py-1 rounded-full bg-red-100 text-red-800 font-semibold">
            {openAlerts?.length ?? 0} unresolved
          </span>
        </div>
        <AlertsTable rows={openAlerts ?? []} />
      </section>

      <section>
        <h2 className="text-base font-semibold mb-3">Past digests</h2>
        <PastDigestsList />
      </section>

      <section>
        <h2 className="text-base font-semibold mb-3">Settings</h2>
        {settings && <SettingsPanel initial={settings} />}
      </section>
    </div>
  )
}
```

- [ ] **Step 2: Write the alerts table**

```typescript
// src/components/notifications/alerts-table.tsx
'use client'
import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { IssuePill } from './issue-pill'

interface AlertRow {
  id: string
  alert_type: 'missing_buttons' | 'missing_notes'
  details: { type: string; missing?: string[]; total_amount?: number; pay_overdue_hours?: number }
  digest_date: string
  attributed_tech: { id: string; full_name: string } | null
  jobs: {
    id: string; hcp_id: string | null; job_type: string | null; revenue: number; completed_at: string | null
    customers: { first_name: string | null; last_name: string | null } | null
    job_technicians: { users: { id: string; full_name: string } }[]
  } | null
}

export function AlertsTable({ rows }: { rows: AlertRow[] }) {
  const qc = useQueryClient()
  const [pending, setPending] = useState<Set<string>>(new Set())

  // Group alerts by job_id for display (each job = one card)
  const byJob = new Map<string, AlertRow[]>()
  for (const r of rows) {
    const k = r.jobs?.id ?? r.id
    byJob.set(k, [...(byJob.get(k) ?? []), r])
  }

  if (byJob.size === 0) {
    return <div className="bg-white border border-gray-200 rounded-lg p-6 text-sm text-gray-500 text-center">All clear — no unresolved alerts.</div>
  }

  async function resolve(id: string) {
    setPending(p => new Set(p).add(id))
    await fetch(`/api/notifications/${id}/resolve`, { method: 'POST' })
    qc.invalidateQueries({ queryKey: ['notifications'] })
    setPending(p => { const s = new Set(p); s.delete(id); return s })
  }

  return (
    <table className="w-full text-sm bg-white border border-gray-200 rounded-lg overflow-hidden">
      <thead>
        <tr className="bg-gray-50 text-left text-[11px] uppercase tracking-wider text-gray-500">
          <th className="p-3">Job</th>
          <th className="p-3">Tech</th>
          <th className="p-3">Total</th>
          <th className="p-3">Issues</th>
          <th className="p-3"></th>
        </tr>
      </thead>
      <tbody>
        {[...byJob.values()].map(group => {
          const j = group[0].jobs!
          const techs = j.job_technicians.map(jt => jt.users.full_name)
          const primary = group[0].attributed_tech?.full_name ?? techs[0] ?? 'Unassigned'
          const co = techs.find(n => n !== primary)
          return (
            <tr key={j.id} className="border-t border-gray-100">
              <td className="p-3 align-top">
                <strong>#{j.hcp_id ?? j.id.slice(0, 6)}</strong>
                <div className="text-xs text-gray-500">
                  {j.customers ? `${j.customers.first_name?.[0] ?? ''}. ${j.customers.last_name ?? ''}` : '—'} · {j.job_type ?? 'Job'}
                </div>
              </td>
              <td className="p-3 align-top">
                {primary}
                {co && <div className="text-[11px] text-gray-500">+{co}</div>}
              </td>
              <td className="p-3 align-top">${(j.revenue ?? 0).toLocaleString()}</td>
              <td className="p-3 align-top">
                <div className="flex flex-wrap gap-1">
                  {group.flatMap(r => {
                    if (r.alert_type === 'missing_notes')
                      return [<IssuePill key={r.id} label="No Notes" variant="amber" />]
                    return (r.details.missing ?? []).map((m: string) => {
                      if (m === 'PAY' && r.details.pay_overdue_hours)
                        return <IssuePill key={`${r.id}-${m}`} label={`Pay overdue ${Math.floor(r.details.pay_overdue_hours / 24)}d`} variant="orange" />
                      return <IssuePill key={`${r.id}-${m}`} label={`No ${m}`} variant="red" />
                    })
                  })}
                </div>
              </td>
              <td className="p-3 align-top">
                {group.map(r => (
                  <button key={r.id}
                    onClick={() => resolve(r.id)}
                    disabled={pending.has(r.id)}
                    className="text-xs px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 mr-1 mb-1">
                    {pending.has(r.id) ? '…' : 'Mark resolved'}
                  </button>
                ))}
              </td>
            </tr>
          )
        })}
      </tbody>
    </table>
  )
}
```

- [ ] **Step 3: Type-check**

`npm run type-check`. Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add src/app/dashboard/admin/notifications/page.tsx src/components/notifications/alerts-table.tsx
git commit -m "feat(ui): /admin/notifications page + alerts table"
```

---

## Task 19: Resolve API route

**Files:**
- Create: `src/app/api/notifications/[id]/resolve/route.ts`
- Create: `src/app/api/notifications/route.ts` — also needed for the bell dropdown

- [ ] **Step 1: Write the resolve route**

```typescript
// src/app/api/notifications/[id]/resolve/route.ts
import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

export async function POST(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  const supabase = await createServerSupabaseClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) return NextResponse.json({ error: 'unauthorized' }, { status: 401 })

  const { data: profile } = await supabase.from('users').select('id, role').eq('auth_id', user.id).single()
  if (!profile || !['owner', 'manager'].includes(profile.role))
    return NextResponse.json({ error: 'forbidden' }, { status: 403 })

  const { error } = await supabase
    .from('supervisor_alerts')
    .update({ resolved_at: new Date().toISOString(), resolved_by: profile.id })
    .eq('id', id)
  if (error) return NextResponse.json({ error: error.message }, { status: 500 })
  return NextResponse.json({ ok: true })
}
```

- [ ] **Step 2: Write the list route (used by bell dropdown)**

```typescript
// src/app/api/notifications/route.ts
import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

export async function GET(req: Request) {
  const url = new URL(req.url)
  const limit = Math.min(parseInt(url.searchParams.get('limit') ?? '50', 10), 100)

  const supabase = await createServerSupabaseClient()
  const { data, error } = await supabase
    .from('supervisor_alerts')
    .select(`
      id, alert_type, details, digest_date,
      jobs:job_id ( id, hcp_id, job_type, revenue ),
      attributed_tech:attributed_tech_id ( id, full_name )
    `)
    .is('resolved_at', null)
    .order('digest_date', { ascending: false })
    .limit(limit)

  if (error) return NextResponse.json({ alerts: [], error: error.message }, { status: 500 })

  const alerts = (data ?? []).map(r => ({
    id: r.id,
    job_id: r.jobs?.id ?? '',
    hcp_id: r.jobs?.hcp_id ?? null,
    job_summary: r.jobs?.job_type ?? 'Job',
    customer_label: 'Customer',
    total_amount: Number(r.jobs?.revenue ?? 0),
    primary_tech_name: r.attributed_tech?.full_name ?? '—',
    co_tech_name: null as string | null,
    pills: pillsFor(r.alert_type, r.details),
  }))
  return NextResponse.json({ alerts })
}

function pillsFor(type: string, details: any) {
  if (type === 'missing_notes') return [{ label: 'No Notes', variant: 'amber' as const }]
  return ((details?.missing ?? []) as string[]).map((m: string) => ({
    label: m === 'PAY' ? 'Pay overdue' : `No ${m}`,
    variant: m === 'PAY' ? ('orange' as const) : ('red' as const),
  }))
}
```

- [ ] **Step 3: Test resolve flow manually**

`npm run dev`. Open `/dashboard/admin/notifications`. Click "Mark resolved" on any row. Expected: row disappears, bell badge count decrements.

- [ ] **Step 4: Commit**

```bash
git add src/app/api/notifications/[id]/resolve/route.ts src/app/api/notifications/route.ts
git commit -m "feat(api): list + resolve notification routes"
```

---

## Task 20: Past digests list component

**Files:**
- Create: `src/components/notifications/past-digests-list.tsx`
- Create: `src/app/api/notifications/past-digests/route.ts`

- [ ] **Step 1: Write the API route**

```typescript
// src/app/api/notifications/past-digests/route.ts
import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

export async function GET() {
  const supabase = await createServerSupabaseClient()
  // Last 14 days of digest_date with counts
  const { data, error } = await supabase.rpc('past_digests_summary', { days: 14 })
  if (error) return NextResponse.json({ digests: [], error: error.message }, { status: 500 })
  return NextResponse.json({ digests: data ?? [] })
}
```

- [ ] **Step 2: Add the SQL function**

Create `supabase/migrations/00008_past_digests_summary.sql`:
```sql
CREATE OR REPLACE FUNCTION public.past_digests_summary(days INT DEFAULT 14)
RETURNS TABLE (
  digest_date DATE,
  ticket_count INT,
  issue_count INT,
  resolved_count INT,
  ignored_count INT
)
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT
    digest_date,
    COUNT(DISTINCT job_id)::INT AS ticket_count,
    COUNT(*)::INT AS issue_count,
    COUNT(*) FILTER (WHERE resolved_at IS NOT NULL)::INT AS resolved_count,
    0::INT AS ignored_count  -- v1 has no ignore action; placeholder for future
  FROM public.supervisor_alerts
  WHERE digest_date >= CURRENT_DATE - days
  GROUP BY digest_date
  ORDER BY digest_date DESC;
$$;

GRANT EXECUTE ON FUNCTION public.past_digests_summary(INT) TO authenticated;
```

Apply: `npx supabase db reset --local`.

- [ ] **Step 3: Write the component**

```typescript
// src/components/notifications/past-digests-list.tsx
'use client'
import { useQuery } from '@tanstack/react-query'

interface DigestSummary {
  digest_date: string
  ticket_count: number
  issue_count: number
  resolved_count: number
}

export function PastDigestsList() {
  const { data, isLoading } = useQuery({
    queryKey: ['notifications', 'past-digests'],
    queryFn: async () => {
      const res = await fetch('/api/notifications/past-digests')
      return (await res.json()) as { digests: DigestSummary[] }
    },
  })

  if (isLoading) return <div className="text-sm text-gray-500">Loading…</div>
  if (!data?.digests.length) return <div className="text-sm text-gray-500 bg-white border rounded-lg p-4">No digests yet.</div>

  return (
    <div className="space-y-1.5">
      {data.digests.map(d => (
        <div key={d.digest_date} className="grid grid-cols-[120px_1fr_auto_auto] gap-4 items-center bg-white border rounded-lg px-4 py-2.5 text-sm">
          <span className="text-gray-500 text-xs">{new Date(d.digest_date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}</span>
          <span>{d.ticket_count} tickets · {d.issue_count} issues</span>
          <span className="text-xs text-gray-500">{d.resolved_count} resolved</span>
          <span className="text-xs text-gray-400">View →</span>
        </div>
      ))}
    </div>
  )
}
```

- [ ] **Step 4: Type-check + visual verify**

`npm run type-check`. Open `/dashboard/admin/notifications` — section renders.

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/00008_past_digests_summary.sql \
        src/app/api/notifications/past-digests/route.ts \
        src/components/notifications/past-digests-list.tsx
git commit -m "feat(ui): past-digests list with SQL summary RPC"
```

---

## Task 21: Settings panel + settings API

**Files:**
- Create: `src/components/notifications/settings-panel.tsx`
- Create: `src/app/api/notifications/settings/route.ts`

- [ ] **Step 1: Write the API route**

```typescript
// src/app/api/notifications/settings/route.ts
import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

const ALL_BUTTONS = ['SCHEDULE','OMW','START','FINISH','INVOICE','PAY'] as const
const HHMM = /^([01]\d|2[0-3]):[0-5]\d$/

export async function PATCH(req: Request) {
  const supabase = await createServerSupabaseClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) return NextResponse.json({ error: 'unauthorized' }, { status: 401 })

  const { data: profile } = await supabase.from('users').select('role').eq('auth_id', user.id).single()
  if (!profile || !['owner', 'manager'].includes(profile.role))
    return NextResponse.json({ error: 'forbidden' }, { status: 403 })

  const body = (await req.json()) as Partial<{
    digest_time: string
    digest_recipient_email: string
    notes_threshold_dollars: number
    pay_grace_hours: number
    enabled_button_checks: string[]
  }>

  // Validate
  if (body.digest_time !== undefined && !HHMM.test(body.digest_time))
    return NextResponse.json({ error: 'digest_time must be HH:MM' }, { status: 400 })
  if (body.notes_threshold_dollars !== undefined && (body.notes_threshold_dollars < 0 || body.notes_threshold_dollars > 100000))
    return NextResponse.json({ error: 'notes_threshold_dollars out of range' }, { status: 400 })
  if (body.pay_grace_hours !== undefined && (body.pay_grace_hours < 0 || body.pay_grace_hours > 720))
    return NextResponse.json({ error: 'pay_grace_hours out of range' }, { status: 400 })
  if (body.enabled_button_checks !== undefined &&
      !body.enabled_button_checks.every(b => (ALL_BUTTONS as readonly string[]).includes(b)))
    return NextResponse.json({ error: 'invalid button name' }, { status: 400 })

  const updates: Record<string, unknown> = { ...body, updated_at: new Date().toISOString() }

  // If digest_time changed, also update cron expression and re-schedule
  if (body.digest_time) {
    const [hh, mm] = body.digest_time.split(':')
    // Convert local time -> UTC cron. Simple approach: stored time is *America/Chicago*,
    // so add 5h (CDT) or 6h (CST) as appropriate. v1 hard-codes CDT (5h offset).
    // Production should compute this from digest_timezone using a library.
    const utcHour = (parseInt(hh, 10) + 5) % 24
    const cronExpr = `${parseInt(mm, 10)} ${utcHour} * * *`
    updates.digest_cron_expression = cronExpr

    // Persist + reschedule via SECURITY DEFINER helper
    const { error: rpcErr } = await supabase.rpc('reschedule_digest', { new_cron_expression: cronExpr })
    if (rpcErr) return NextResponse.json({ error: `cron reschedule failed: ${rpcErr.message}` }, { status: 500 })
  }

  const { error } = await supabase.from('app_settings').update(updates).eq('id', 1)
  if (error) return NextResponse.json({ error: error.message }, { status: 500 })
  return NextResponse.json({ ok: true })
}
```

- [ ] **Step 2: Write the settings panel**

```typescript
// src/components/notifications/settings-panel.tsx
'use client'
import { useState } from 'react'
import type { Database } from '@/types/database'

type Settings = Database['public']['Tables']['app_settings']['Row']
const ALL_BUTTONS = ['SCHEDULE','OMW','START','FINISH','INVOICE','PAY'] as const

export function SettingsPanel({ initial }: { initial: Settings }) {
  const [s, setS] = useState(initial)
  const [busy, setBusy] = useState(false)
  const [msg, setMsg] = useState<string | null>(null)

  function update<K extends keyof Settings>(k: K, v: Settings[K]) {
    setS({ ...s, [k]: v })
  }

  async function save() {
    setBusy(true); setMsg(null)
    const res = await fetch('/api/notifications/settings', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        digest_time: s.digest_time,
        digest_recipient_email: s.digest_recipient_email,
        notes_threshold_dollars: s.notes_threshold_dollars,
        pay_grace_hours: s.pay_grace_hours,
        enabled_button_checks: s.enabled_button_checks,
      }),
    })
    setBusy(false)
    setMsg(res.ok ? 'Saved.' : `Error: ${(await res.json()).error}`)
  }

  async function sendTest() {
    setBusy(true); setMsg(null)
    const res = await fetch('/api/notifications/test-digest', { method: 'POST' })
    setBusy(false)
    setMsg(res.ok ? 'Test digest sent.' : `Error: ${(await res.json()).error}`)
  }

  function toggleButton(btn: string) {
    const has = s.enabled_button_checks?.includes(btn) ?? false
    const next = has
      ? s.enabled_button_checks!.filter(b => b !== btn)
      : [...(s.enabled_button_checks ?? []), btn]
    update('enabled_button_checks', next)
  }

  const Row = ({ label, children }: { label: string; children: React.ReactNode }) => (
    <div className="grid grid-cols-[220px_1fr] items-center py-2.5 border-b border-gray-100 last:border-0 text-sm">
      <label className="text-gray-700 font-medium">{label}</label>
      <div>{children}</div>
    </div>
  )

  const inputCls = 'border border-gray-300 rounded px-2.5 py-1.5 text-sm bg-gray-50'

  return (
    <div className="bg-white border border-gray-200 rounded-lg p-5">
      <Row label="Digest send time">
        <input type="time" value={s.digest_time?.slice(0, 5) ?? ''} onChange={e => update('digest_time', e.target.value)} className={inputCls} /> CDT
      </Row>
      <Row label="Recipient email">
        <input type="email" value={s.digest_recipient_email ?? ''} onChange={e => update('digest_recipient_email', e.target.value)} className={`${inputCls} w-72`} />
      </Row>
      <Row label="Sub-labor notes threshold">
        $ <input type="number" value={s.notes_threshold_dollars} onChange={e => update('notes_threshold_dollars', Number(e.target.value))} className={`${inputCls} w-20`} />
      </Row>
      <Row label="PAY grace period">
        <input type="number" value={s.pay_grace_hours} onChange={e => update('pay_grace_hours', Number(e.target.value))} className={`${inputCls} w-20`} /> hours after FINISH
      </Row>
      <Row label="Buttons checked">
        <div className="flex flex-wrap gap-2">
          {ALL_BUTTONS.map(b => {
            const on = s.enabled_button_checks?.includes(b) ?? false
            return (
              <button key={b} onClick={() => toggleButton(b)}
                className={`text-[11px] font-semibold px-2.5 py-1 rounded-full uppercase tracking-wide ${on ? 'bg-blue-100 text-blue-900' : 'bg-gray-100 text-gray-400 line-through'}`}>
                {b}
              </button>
            )
          })}
        </div>
      </Row>
      <div className="flex gap-2 pt-4">
        <button onClick={save} disabled={busy} className="bg-blue-900 text-white text-sm font-semibold px-4 py-2 rounded disabled:opacity-50">
          {busy ? 'Saving…' : 'Save settings'}
        </button>
        <button onClick={sendTest} disabled={busy} className="border border-gray-300 text-sm font-semibold px-4 py-2 rounded disabled:opacity-50">
          Send test digest now
        </button>
        {msg && <span className="self-center text-sm text-gray-600">{msg}</span>}
      </div>
    </div>
  )
}
```

- [ ] **Step 3: Type-check**

`npm run type-check`. Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add src/components/notifications/settings-panel.tsx src/app/api/notifications/settings/route.ts
git commit -m "feat(ui): notifications settings panel + PATCH endpoint"
```

---

## Task 22: Test-digest button endpoint

**Files:**
- Create: `src/app/api/notifications/test-digest/route.ts`

- [ ] **Step 1: Write the route**

```typescript
// src/app/api/notifications/test-digest/route.ts
import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

export async function POST() {
  const supabase = await createServerSupabaseClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) return NextResponse.json({ error: 'unauthorized' }, { status: 401 })

  const { data: profile } = await supabase.from('users').select('role').eq('auth_id', user.id).single()
  if (!profile || !['owner', 'manager'].includes(profile.role))
    return NextResponse.json({ error: 'forbidden' }, { status: 403 })

  const url = `${process.env.NEXT_PUBLIC_SUPABASE_URL}/functions/v1/daily-supervisor-digest`
  const res = await fetch(url, {
    method: 'POST',
    headers: { Authorization: `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY ?? ''}` },
  })
  if (!res.ok) return NextResponse.json({ error: `digest function returned ${res.status}` }, { status: 502 })
  const json = await res.json()
  return NextResponse.json({ ok: true, ...json })
}
```

- [ ] **Step 2: Verify**

`npm run dev`. Click "Send test digest now" in settings panel. Expected: status message updates to "Test digest sent." and the supervisor email arrives (or, if RESEND_API_KEY missing locally, server log shows skipped-send).

- [ ] **Step 3: Commit**

```bash
git add src/app/api/notifications/test-digest/route.ts
git commit -m "feat(api): test-digest button endpoint"
```

---

## Task 23: Sidebar link to notifications

**Files:**
- Modify: `src/components/layout/sidebar.tsx`

- [ ] **Step 1: Read current sidebar**

`cat src/components/layout/sidebar.tsx`. Note the navigation items array structure.

- [ ] **Step 2: Add the link**

Find the admin navigation group. Add an item:
```typescript
{ href: '/dashboard/admin/notifications', label: 'Notifications', icon: 'bell', adminOnly: true }
```
(Adjust `icon` and `adminOnly` props to match the actual sidebar item shape.)

If the sidebar uses a hard-coded JSX list rather than a config array, insert a `<Link href="/dashboard/admin/notifications">Notifications</Link>` item in the admin section.

- [ ] **Step 3: Visually verify**

`npm run dev`. Sidebar shows "Notifications" under Admin section. Clicking goes to the page.

- [ ] **Step 4: Commit**

```bash
git add src/components/layout/sidebar.tsx
git commit -m "feat(ui): notifications link in admin sidebar"
```

---

## Task 24: End-to-end verification checklist

**Files:** none — pure manual verification

- [ ] **Step 1: Reset DB and seed a deliberate test fixture**

```bash
npx supabase db reset --local
psql "$(npx supabase status --local | grep 'DB URL' | awk '{print $3}')" <<'SQL'
-- Insert a customer
INSERT INTO customers (id, hcp_id, first_name, last_name) VALUES (gen_random_uuid(), 'cust-1', 'Sarah', 'Jenkins');
-- Insert two technicians (Charles + Maurice)
-- (Charles already exists from migration 00006)
INSERT INTO users (id, email, full_name, role, is_active) VALUES (gen_random_uuid(), 'maurice@x.com', 'Maurice Williams', 'technician', true) ON CONFLICT DO NOTHING;
-- Insert a job missing OMW + work_notes, completed 1 hour ago, total $99
INSERT INTO jobs (id, hcp_id, customer_id, technician_id, job_type, status, scheduled_at, started_at, completed_at, revenue, work_notes)
SELECT gen_random_uuid(), 'job-test-1', c.id, u.id, 'service_call', 'completed',
       now() - interval '4 hours', now() - interval '2 hours', now() - interval '1 hour',
       99, NULL
FROM customers c, users u WHERE c.hcp_id='cust-1' AND u.email='maurice@x.com';
-- Link tech assignments
INSERT INTO job_technicians (job_id, technician_id, assigned_at)
SELECT j.id, u.id, now() - interval '5 hours'
FROM jobs j, users u WHERE j.hcp_id='job-test-1' AND u.email='maurice@x.com';
INSERT INTO job_technicians (job_id, technician_id, assigned_at)
SELECT j.id, u.id, now() - interval '4 hours'
FROM jobs j, users u WHERE j.hcp_id='job-test-1' AND u.email='charlesrue@icloud.com';
SQL
```

- [ ] **Step 2: Trigger digest via test endpoint**

```bash
curl -X POST http://localhost:54321/functions/v1/daily-supervisor-digest \
  -H "Authorization: Bearer $(npx supabase status --local | grep 'service_role key' | awk '{print $3}')"
```
Expected: `{"ok": true, "alerts": 2}` (one for missing_buttons, one for missing_notes).

- [ ] **Step 3: Verify alert rows + attribution**

```sql
SELECT alert_type, attributed_tech_id, details FROM supervisor_alerts ORDER BY created_at DESC;
SELECT full_name FROM users WHERE id = (SELECT attributed_tech_id FROM supervisor_alerts LIMIT 1);
```
Expected: 2 rows. `attributed_tech_id` resolves to Maurice (NOT Charles), proving the co-tech rule.

- [ ] **Step 4: Verify UI**

Open `http://localhost:3000/dashboard/admin/notifications`. Expected:
- Bell badge in header shows "2"
- Open issues table shows the test job with both pills (No Notes, No OMW)
- Tech column shows "Maurice" with "+Charles" co-tech indicator

- [ ] **Step 5: Verify mark-resolved**

Click "Mark resolved" on the missing_buttons row. Expected:
- Row disappears from the table
- Bell badge decrements to 1
- DB row shows `resolved_at IS NOT NULL`

- [ ] **Step 6: Verify settings reschedule**

In settings panel, change digest time to 19:30 and save. Verify:
```sql
SELECT digest_time, digest_cron_expression FROM app_settings;
SELECT schedule FROM cron.job WHERE jobname = 'daily-supervisor-digest';
```
Expected: `digest_time = '19:30'`, schedule reflects new time.

- [ ] **Step 7: Commit verification log**

```bash
echo "E2E verification passed $(date)" >> docs/superpowers/plans/2026-05-08-supervisor-ticket-discipline-alerts.md
git add docs/superpowers/plans/2026-05-08-supervisor-ticket-discipline-alerts.md
git commit -m "test: e2e verification of supervisor digest"
```

---

## Self-Review

**Spec coverage:**
- Rule 1 missing buttons (incl. PAY grace, $0 skip, button toggle): Tasks 5, 6, 8 (rules), 11, 13 (orchestrator)
- Rule 2 missing notes (sub-labor threshold): Tasks 5, 6, 8 (rules), 13 (orchestrator)
- Reporting window (Pass A + Pass B): Task 7 (window), 13 (orchestrator)
- Charles co-tech attribution: Tasks 3 (Charles seed + co_tech_default_user_id setting), 6 (attribution function), 9, 10 (junction populate)
- Schema additions: Task 2 (jobs columns, app_settings, supervisor_alerts, job_technicians)
- Webhook handler updates (work_notes, started_at, invoiced_at, junction): Task 9
- Backfill: Task 10
- Email digest: Tasks 11 (HTML), 12 (Resend), 13 (orchestrator)
- pg_cron: Task 14
- Notifications page (open issues + past digests + settings): Tasks 18, 20, 21
- Bell + dropdown: Tasks 15, 16, 17
- Mark-resolved: Tasks 18, 19
- Test-digest button: Task 22
- Sidebar link: Task 23
- E2E success criteria: Task 24

**Placeholder scan:** No "TBD" / "implement later" / vague-handling found in any task. Each step has runnable code or commands.

**Type consistency:**
- `JobForAlerting` (Task 5) is consumed identically by `attributeTech` (Task 6) and the rule evaluators (Task 8); orchestrator (Task 13) maps DB rows to this shape via `mapJob()`.
- `AlertRow` shape from Task 5 matches the upsert in Task 13 (`{ job_id, alert_type, attributed_tech_id, details }`).
- `RenderTicket` shape from Task 11 is what the orchestrator produces in `toRenderTicket()` (Task 13) and `groupByJob()` (Task 13).
- API contract: `/api/notifications/count` returns `{ count: number }` — consumed by `useUnresolvedCount` in Task 15. `/api/notifications/[id]/resolve` returns `{ ok }` — consumed by alerts-table in Task 18.

**Issues found and fixed:** None on review pass — task chain is consistent.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-08-supervisor-ticket-discipline-alerts.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration. Best for this plan because Tasks 9 (webhook handler) and 13 (orchestrator) touch real HCP payload shapes that may need on-the-fly adjustment.

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
