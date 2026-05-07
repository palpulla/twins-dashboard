# Options / Ticket KPI — Design

**Status:** approved (pending implementation plan)
**Date:** 2026-05-07
**Scope:** Add an "Options / Ticket" KPI tile to the company dashboard and to every technician scorecard.

## Goal

Surface the average number of HCP estimate options written per ticket, both company-wide and per technician. This is the standard "Options per Opportunity" coaching metric for trades businesses: it measures whether techs are presenting priced choices (Good / Better / Best) on every opportunity, which is the lever that moves close rate and average ticket.

## Definitions

**Ticket.** A unit of customer opportunity. Counts as one ticket if EITHER:
- A standalone HCP estimate (no parent job), OR
- An HCP job whose `job_type` is NOT in `WARRANTY_JOB_TYPES` (callbacks/warranty work excluded — those are not sales opportunities).

**Option.** An HCP estimate option (a single priced line in the Good/Better/Best block of an estimate). Tracked per HCP webhook event `estimate.option.created`.

**Average options per ticket.**
```
denominator = count(tickets in window)
numerator   = sum(options across every estimate linked to those tickets)
            (a job ticket with multiple estimates rolls all of their options up)
avg         = denominator > 0 ? numerator / denominator : 0
```

**Time window.** Same as the existing dashboard date-range picker. Tickets are filtered by `created_at` (matches the convention used by all other ticket-level KPIs in this codebase).

**Charles co-tech attribution.** Per the load-bearing rule: a ticket worked by Charles plus any other tech attributes 100% to the OTHER tech, for every per-tech metric including this one. The current `jobs` schema stores only a single `technician_id`, so this rule is satisfied automatically if HCP's primary-tech assignment already follows the convention. If HCP exposes co-tech data and we later capture it, this calculator must be updated alongside every other per-tech calculator.

## Data model

### New table: `estimate_options`

```sql
CREATE TABLE public.estimate_options (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  hcp_id          TEXT UNIQUE NOT NULL,
  estimate_hcp_id TEXT NOT NULL,
  estimate_id     UUID REFERENCES public.estimates(id),
  name            TEXT,
  amount          NUMERIC(12,2) NOT NULL DEFAULT 0,
  status          TEXT NOT NULL DEFAULT 'created',
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_estimate_options_estimate_hcp ON public.estimate_options(estimate_hcp_id);
CREATE INDEX idx_estimate_options_estimate ON public.estimate_options(estimate_id, created_at);

ALTER TABLE public.estimate_options ENABLE ROW LEVEL SECURITY;

-- Read policy mirrors estimates_select (managers/owners read all; techs read own).
CREATE POLICY estimate_options_select ON public.estimate_options FOR SELECT USING (
  EXISTS (
    SELECT 1 FROM public.estimates e
    WHERE e.id = estimate_options.estimate_id
      AND (
        e.technician_id = auth.uid()
        OR EXISTS (SELECT 1 FROM public.users u WHERE u.id = auth.uid() AND u.role IN ('manager','owner'))
      )
  )
);
```

`estimate_id` is nullable because option webhooks can arrive before the parent estimate row exists in our DB. A nightly reconciliation (out of scope for v1) can backfill `estimate_id` from `estimate_hcp_id`. The KPI calculator joins by `estimate_hcp_id` to avoid being blocked by missing FKs.

### New column on `estimates`

```sql
ALTER TABLE public.estimates ADD COLUMN job_id UUID REFERENCES public.jobs(id);
CREATE INDEX idx_estimates_job ON public.estimates(job_id);
```

Required to identify "standalone estimate" tickets (rows where `job_id IS NULL`) and to roll options up to a parent job. Captured from the `data.job_id` field on `estimate.created` / `estimate.updated` webhook payloads.

### Database type updates

`src/types/database.ts` adds the `estimate_options` table type and a `job_id: string | null` field on the `estimates` Row/Insert/Update interfaces.

## Ingestion

### Edge function changes (`supabase/functions/webhook-handler/index.ts`)

1. **Fix the event-namespace parsing bug.** The current code is:
   ```ts
   const [category, action] = payload.event.split('.');
   ```
   For `estimate.option.created` this binds `action = 'option'` and silently drops `created`. Replace with namespace-aware routing:
   ```ts
   const parts = payload.event.split('.');
   const category = parts[0];
   const action = parts.slice(1).join('.'); // 'option.created', 'created', 'sent', etc.
   ```
   All existing handlers that match on `action` (`'created'`, `'completed'`, etc.) keep working unchanged.

2. **Add `handleEstimateOptionEvent(action, data)`** dispatched from the existing `handleEstimateEvent` when `action.startsWith('option.')`:
   - `option.created` → upsert into `estimate_options` keyed by `hcp_id`. Set `status='created'`, fill `name`, `amount`, `estimate_hcp_id` from payload.
   - `option.approval_status_changed` → update `status` (`approved` / `declined`) and `updated_at`.

3. **Capture `job_id` on estimate events.** In `handleEstimateEvent`, add `job_id: data.job_id` to the upsert payload so standalone vs. job-attached estimates are distinguishable.

### Backfill script (`scripts/backfill-estimate-options.ts`)

Replays historical option events that are already sitting in `raw_events`:

1. Select all `raw_events` rows where `event_type LIKE 'estimate.option.%'` ordered by `received_at`.
2. For each, call the same upsert logic the edge function uses (extracted into a shared helper or duplicated; the duplication is fine for one script).
3. Idempotent via `hcp_id` upsert — safe to re-run.

A second pass backfills `estimates.job_id` from any historical `estimate.*` events in `raw_events` whose payload includes `data.job_id`.

## KPI calculator

`src/lib/utils/kpi-calculations.ts` gains:

```ts
export function calcAvgOptionsPerTicket(input: KpiInput): number {
  const tickets = computeTickets(input.jobs, input.estimates);
  if (tickets.length === 0) return 0;
  const optionTotal = tickets.reduce((sum, t) => sum + countOptionsForTicket(t, input.estimateOptions), 0);
  return optionTotal / tickets.length;
}
```

Where:
- `computeTickets(jobs, estimates)` returns `{ kind: 'job' | 'estimate', estimateHcpIds: string[] }[]`:
  - One entry per job whose `job_type ∉ WARRANTY_JOB_TYPES`, with `estimateHcpIds` = HCP IDs of all estimates whose `job_id` matches. **A job with zero linked estimates is still included** — it contributes 0 options to the numerator and 1 to the denominator. Do not filter empty tickets out; that's the whole point of the metric (it captures whether techs are presenting options at all).
  - One entry per estimate where `job_id IS NULL`, with `estimateHcpIds` = `[that estimate's hcp_id]`.
- `countOptionsForTicket(ticket, options)` sums `options.filter(o => ticket.estimateHcpIds.includes(o.estimate_hcp_id)).length`.

Register in `KPI_CALCULATORS`:
```ts
avg_options_per_ticket: calcAvgOptionsPerTicket,
```

### `KpiInput` extension

Add two fields to the type and to every site that constructs it:

```ts
type KpiInput = {
  jobs: Job[];
  commissionRecords: CommissionRecord[];
  reviews: Review[];
  estimates: Estimate[];           // NEW
  estimateOptions: EstimateOption[]; // NEW
};
```

Both `useCompanyKpis()` and `useTechnicianKpis()` already follow the `useWithFallback(supabase, seed)` pattern. Add:
- `useSupabaseEstimates(from, to, technicianId?)` and seed analog.
- `useSupabaseEstimateOptions(from, to, technicianId?)` — joins to `estimates` for tech filtering. The window is "options whose parent estimate's `created_at` is in window," which keeps the metric stable across periods.
- Seed-data updates: `SEED_ESTIMATES` (already present? confirm) and a new `SEED_ESTIMATE_OPTIONS` so the local-dev fallback shows realistic numbers.

## KPI definition row

```sql
INSERT INTO public.kpi_definitions (
  id, name, description, formula, data_source, target,
  display_format, is_active, inverted_status, sort_order
) VALUES (
  gen_random_uuid(),
  'Options / Ticket',
  'Average HCP estimate options written per ticket. Higher = techs presenting more pricing choices on every opportunity.',
  'avg_options_per_ticket',
  'estimates+jobs',
  3.0,
  'decimal',
  true,
  false,
  -- sort_order: place after avg_ticket and before conversion_rate
  (SELECT COALESCE(sort_order, 0) + 1 FROM public.kpi_definitions WHERE formula = 'avg_ticket')
);
```

`display_format='decimal'` renders as `2.7` (one decimal place). Confirm the existing format renderer supports this; if not, add `decimal` as a new case alongside `currency`, `percentage`, `count`.

## UI placement

The KpiGrid is data-driven — once the `kpi_definitions` row is inserted and the calculator is registered, the new tile appears automatically in:

- `src/app/dashboard/page.tsx` (Company-Wide KPIs section), AND
- `src/app/dashboard/technician/[id]/page.tsx` (tech scorecard KpiGrid).

No JSX changes required. Tile styling matches existing KPIs.

## Reversibility

- Migration is additive only: new table + nullable column. Reverting drops the table and column, no data loss elsewhere.
- KPI tile can be hidden by setting `kpi_definitions.is_active=false` for this row — no redeploy needed.
- Calculator and KpiInput changes are local to two files.
- Backfill script is idempotent (`hcp_id` upsert).
- Edge function namespace-parsing fix is backwards-compatible: existing `action` values (`created`, `sent`, `completed`, etc.) match identically.

## Out of scope (v1)

- Per-option approval-rate KPI ("% of options approved") — the table supports it; calculator is future work.
- Co-tech ingestion: this design assumes HCP's `technician_id` already represents the right tech per the Charles attribution rule. If we later need to capture co-techs, every per-tech calculator (not just this one) needs updating in a separate change.
- A `nightly_reconciliation` job to fill `estimate_options.estimate_id` from `estimate_hcp_id` — the calculator joins by `estimate_hcp_id` so the FK gap is informational only.
- Commission impact: this KPI is reporting-only. It does not feed commission math.

## Files touched

```
supabase/migrations/<next>_estimate_options.sql                 NEW
supabase/functions/webhook-handler/index.ts                     edit (parse fix, option handler, job_id capture)
scripts/backfill-estimate-options.ts                            NEW
src/types/database.ts                                           edit (add estimate_options table; add estimates.job_id)
src/lib/utils/kpi-calculations.ts                               edit (add calcAvgOptionsPerTicket + helpers)
src/lib/constants/kpi-defaults.ts                               edit (add definition row to seed)
src/lib/seed-data.ts                                            edit (SEED_ESTIMATE_OPTIONS, ensure SEED_ESTIMATES has job_id)
src/lib/hooks/use-supabase-data.ts                              edit (useSupabaseEstimates, useSupabaseEstimateOptions)
src/lib/hooks/use-seed-data.ts                                  edit (wire new fallbacks into KpiInput for company + tech)
```

## Open items requiring confirmation during implementation

1. Exact field names on the HCP `estimate.option.created` payload (verified at implementation time against `raw_events` samples; spec assumes standard fields `id`, `estimate_id`, `name`, `total`/`amount`, `approval_status`).
2. Whether `display_format='decimal'` already exists in the formatter or needs to be added as a new case.
3. Whether `SEED_ESTIMATES` already exists (referenced in `use-seed-data.ts`, confirm shape).
