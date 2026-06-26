# Technician Accountability — Plan 3: Digest auto-points + email section

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Wire the existing (still-disabled) `daily-supervisor-digest` edge function into the accountability point system: emit ledger entries for the auto-detectable violations it already finds, and add an "Accountability" section to the digest email (per-tech balance, Level, weekly points, Level-crossing flags, and callbacks awaiting classification).

**Architecture:** The digest already detects `missing_buttons` and `missing_notes` per job and attributes a tech via the Charles co-tech rule. We map the three **auto-detectable** catalog violations onto those signals and insert idempotent `source='auto'` ledger rows (one per job+violation_code, ever). The engine + summarizer are vendored into the digest function (Deno) to compute the email section. Only `send_invoice` (INVOICE missing → 1pt), `failure_collect_payment` (PAY overdue → 3pt), and `inform_arrival` (OMW missing → 1pt) are emitted — the only `auto_detectable=true` rows in the catalog. SCHEDULE/START/FINISH and missing_notes stay operational-only (in `supervisor_alerts`, no points), faithful to "only auto-detectable items score."

**Tech Stack:** Deno edge function, TypeScript, Vitest (for the pure mapping + render logic via the vendored copies), Supabase JS.

**Repo:** Paths relative to `/Users/daniel/twins-dashboard/twins-dash`. Branch `feat/accountability-program`, worktree `.worktrees/accountability`. Ships in PR #269.

**Safety:** The digest is disabled (`supervisor_digest_config.enabled=false`); this code is wired-and-ready but does not run until Daniel enables it. The auto ledger insert is idempotent (dedupe on job_id+violation_code+source='auto', non-voided) so repeated daily runs never double-count an unpaid job.

---

## File Structure
- Create `src/lib/accountability/auto-mapping.ts` — pure `autoViolationsForJob(missingButtons, occurredOn)` → `{ violation_code, points, severity }[]`; vitest-tested.
- Create `src/lib/accountability/__tests__/auto-mapping.test.ts`.
- Modify `supabase/functions/daily-supervisor-digest/index.ts` — after evaluating alerts, build + insert idempotent auto ledger entries; gather accountability summary data for the email.
- Create `supabase/functions/daily-supervisor-digest/accountability-vendored.ts` — vendored copy of `src/lib/accountability/{types,engine,summary,auto-mapping}.ts` (Deno date-fns URL), with a sync test.
- Modify `src/lib/accountability/__tests__/vendored-sync.test.ts` OR add `accountability-digest-vendored-sync.test.ts` asserting the digest's vendored copy exports the needed symbols.
- Modify `supabase/functions/daily-supervisor-digest/render-email.ts` — add `renderAccountabilitySection(rows)` and include it in the digest HTML/text.
- Modify `supabase/functions/daily-supervisor-digest/__test__/render.test.ts` — cover the new section.

---

## Task 1: Auto-mapping module (pure TS, TDD)

**Files:** Create `src/lib/accountability/auto-mapping.ts`, test `__tests__/auto-mapping.test.ts`.

```typescript
import { violationByCode } from "./types";
import type { Severity } from "./types";

export interface AutoViolation { violation_code: string; points: number; severity: Severity; }

// Map the digest's "missing button" signals to the auto-detectable catalog
// violations. Only these three catalog rows have auto_detectable=true.
const BUTTON_TO_CODE: Record<string, string> = {
  INVOICE: "send_invoice",
  PAY: "failure_collect_payment",
  OMW: "inform_arrival",
};

export function autoViolationsForButtons(missing: readonly string[]): AutoViolation[] {
  const out: AutoViolation[] = [];
  for (const btn of missing) {
    const code = BUTTON_TO_CODE[btn];
    if (!code) continue;
    const cat = violationByCode(code);
    if (!cat) continue;
    out.push({ violation_code: code, points: cat.points, severity: cat.severity });
  }
  return out;
}
```

Tests: `["INVOICE","PAY"]` → send_invoice(1,minor) + failure_collect_payment(3,major); `["OMW"]` → inform_arrival(1,minor); `["SCHEDULE","START","FINISH"]` → [] (none auto-detectable); empty → [].

Commit: `feat(accountability): auto-detection → violation mapping`.

---

## Task 2: Vendor accountability into the digest + sync test

**Files:** Create `supabase/functions/daily-supervisor-digest/accountability-vendored.ts`; create `src/lib/accountability/__tests__/digest-vendored-sync.test.ts`.

- The vendored file = concatenation of `types.ts` + `engine.ts` + `summary.ts` + `auto-mapping.ts`, with: the `date-fns` import replaced by `https://esm.sh/date-fns@4.1.0`, and all intra-module imports (`./types`, `./engine`) removed (symbols co-located). Keep all exports byte-faithful.
- Sync test: assert the file exists and contains `export function autoViolationsForButtons`, `export function summarizeTech`, `export function computeBalance`, `export function computeLevel`, `export function weeklyPoints`, and `export const VIOLATION_CATALOG`.

Commit: `feat(accountability): vendor engine+summary+mapping into digest`.

---

## Task 3: Emit idempotent auto ledger entries in the digest

**Files:** Modify `supabase/functions/daily-supervisor-digest/index.ts`.

In the main flow, after `evaluated` alerts are built (each has `job_id`, `attributed_tech_id`, and `details` with `missing` buttons) and BEFORE/ALONGSIDE the existing `supervisor_alerts` upsert:

1. Build candidate auto-violation rows: for each evaluated `missing_buttons` alert, call `autoViolationsForButtons(details.missing)`; for each result, a candidate `{ technician_id: attributed_tech_id, points, reason_type:"violation", violation_code, severity, source:"auto", occurred_on: <job completed_at as YYYY-MM-DD in digest tz>, job_id: <internal job id>, note: "Auto-detected from ticket" }`. Skip candidates with null `technician_id`.
2. **Idempotency:** query existing non-voided auto rows for the candidate job_ids: `supabase.from("accountability_points").select("job_id,violation_code").eq("source","auto").is("voided_at",null).in("job_id", candidateJobIds)`. Build a Set of `${job_id}|${violation_code}`; filter candidates to those NOT already present.
3. Insert the surviving candidates (`.insert(rows)`), if any.
4. Log counts. Wrap in try/catch that logs but does NOT fail the digest (accountability is additive to the existing digest behavior).

Use `import { autoViolationsForButtons } from "./accountability-vendored.ts";`. Reuse the existing `attributeTech` result already computed per job. Note: occurred_on should be the job completion date; the index already has `localDateIso(now, tz)` for digest_date — for the violation use the job's completion date if available, else digest_date.

Commit: `feat(accountability): emit idempotent auto ledger entries from digest`.

---

## Task 4: Accountability section in the digest email

**Files:** Modify `supabase/functions/daily-supervisor-digest/index.ts` (gather data) and `render-email.ts` (render) + `__test__/render.test.ts`.

1. In `index.ts`, after the ledger insert, load the data for the email section: all active technicians (`id,name`), all non-voided `accountability_points`, and the count of callbacks awaiting classification (reuse the same logic shape as the dashboard: callback jobs in trailing 90 days minus those with note ilike 'Callback classified%'; if this is heavy, a simpler count of `is_callback` jobs in last 30 days with no classified marker is acceptable — keep it one query). Group ledger by tech; for each tech compute `summarizeTech`. Build `accountabilityRows: { name, balance, level, weekly_points, days_clean }[]`, sorted by balance desc, INCLUDING only techs with balance>0 or weekly_points>0 (don't list clean techs). Also compute `conversationNeeded`: techs at level>=1.
2. In `render-email.ts`, add `renderAccountabilitySection(rows, callbacksToClassify)` returning HTML + text. Show a small table: Tech | Balance | Level | This week | Days clean, with a header note "Level 1 at 2 pts (coaching), L2 at 4 (written warning), L3 at 6 (final warning), L4 at 8 (termination review). Suggested only." Highlight rows at level>=1. Append a line: "N callbacks awaiting classification." If no techs have points, render "All technicians clean — no accountability points this period." Insert this section into the existing digest body (after the ticket issues).
3. Extend `render.test.ts` with a snapshot/string-contains test: a row at level 2 shows "L2"; the coaching note is present; the clean-state message renders when rows is empty.

Keep currency rules N/A here (points, not dollars). No em-dashes in any email copy (use periods/commas).

Commit: `feat(accountability): accountability section in supervisor digest email`.

---

## Task 5: Verify
- [ ] `npm test -- src/lib/accountability` (auto-mapping + sync tests green).
- [ ] `npm test -- supabase/functions/daily-supervisor-digest/__test__/render.test.ts` — note: this is a Deno-style test that may not run under vitest; if it's excluded from the vitest run (like other `supabase/functions/_shared` tests), instead assert the render logic via a vitest test that imports the vendored copy, OR run it with `deno test` if available. Document which path was used.
- [ ] `npx tsc --noEmit` clean; `npm run build` succeeds.
- [ ] Deploy the updated digest function via CLI (`npx supabase functions deploy daily-supervisor-digest`) — controller does this, not the implementer.
- [ ] Manual dry-run: `curl -X POST .../daily-supervisor-digest -H "x-manual:true" -H "x-dry-run:true"` returns the rendered HTML preview including the Accountability section, WITHOUT sending email (digest stays disabled). Controller runs this.

## Self-Review (planning)
- Spec coverage: auto-points from auto-detectable signals only (T1/T3), idempotent (T3), email Accountability section with Level ladder + callbacks-to-classify (T4), engine reused via vendoring (T2). Digest stays disabled; additive try/catch so accountability never breaks the existing digest.
- Placeholders: none — mapping, insert shape, and dedupe key are explicit.
- Type consistency: `autoViolationsForButtons`, `summarizeTech`, ledger column names match Plan 1/2.
