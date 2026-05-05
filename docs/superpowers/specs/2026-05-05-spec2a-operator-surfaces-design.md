# Spec 2A — Operator Surfaces (Daily-mode core)

**Date:** 2026-05-05
**Position in rollout:** Second of five phase specs. Builds on Phase 1 (Security & Cleanup, shipped 2026-05-05 as `palpulla/twins-dash#134`).
**Source audit:** `twins-dash/DASHBOARD_FULL_AUDIT.md` §6 (CEO/Operator) + §7 (UI) + §16 Phase 2.
**Repo:** `twins-dash` (live at twinsdash.com, Vercel-deployed).

---

## 1. Goal

Turn the dashboard from a retrospective scoreboard into a daily operator surface. After 2A ships, a hired COO (or Daniel before coffee) can answer four questions in under 30 seconds:

1. What happened yesterday?
2. What's on the schedule today?
3. Which jobs need my eyes (callbacks, low ratings, refunds)?
4. Click any KPI tile or per-tech metric → see the underlying jobs.

The work is delivered as **one cohesive PR** built on the same safety pattern as Phase 1 (branch + atomic commits + paired revert SQL + revert script).

## 2. Locked decisions (set during brainstorm)

| Decision | Choice |
|---|---|
| Rev & Rise daily-mode UX | Toggle in page header. Default = call-window (current behavior). Toggle to daily → body swaps to recap + dispatch + jobs-review + missed-money placeholder. |
| Drilldown UX | Radix Sheet panel reusing Twins' existing `KpiDrillSheet` styling. Click any KPI tile → sheet slides up with virtualized job list. |
| Jobs-needing-review rules (starter set) | Flag = `(is_callback within 30d)` OR `(linked review.rating ≤ 3 within 30d)` OR `(hcp_data.refunded_amount > 0)`. All signals come from existing fields. |
| Reviews/ratings source | The existing `public.reviews` table (Google Business Profile reviews, populated by an existing sync job) joined to jobs via `matched_job_id` and to techs via `tech_id`. Replaces the `reviews: 0, avgRating: 0` stubs at `use-rev-rise-data.ts:143-144`. The exact sync function and refresh cadence are identified during plan writing. |
| Reversibility | Same as Phase 1: pre-tag, paired revert migration, one-command `scripts/revert-spec2a.sh`. |

## 3. Out of scope (deferred to Spec 2B)

- Watchlist / "What changed since last week" panel on Index.
- Behaviors row per tech: spring attach %, financing offered %, photos taken %, membership pitch %.
- Open Estimates page (`/follow-ups` route).
- Anything that needs new HCP data extraction (e.g. spring attach requires parsing `line_items` for spring SKUs).

Spec 2A leaves a `<MissedMoneyPanel>` placeholder in daily-mode that shows count + total $ of open estimates aging > 48h, with a disabled "see all" CTA labeled "available in Spec 2B." That stub uses data already in the DB; no new extraction.

## 4. Constraints (from project memory)

- **All changes must be reversible.** Branch + paired revert migration + revert script.
- **KPIs immutable.** No KPI math changes. The daily-mode hooks compute the same numbers as call-window mode, just over a different date range. Reviews/ratings wiring replaces stubbed zeros — that is not a math change, it's a stub fix.
- **Don't ask, just do it.** Decisions inside this spec are mine to make; user reviews the spec before plan writing begins.
- **Live site keeps working.** Branch + Vercel preview + transaction-rollback dry-runs for any new SQL view.

## 5. Safety pattern (same as Phase 1)

1. Branch off `main`: `spec2a-operator-surfaces`. All work in this branch.
2. Tag `main` before any change: `pre-spec2a-2026-05-05`.
3. Vercel preview deploys automatically on branch push.
4. Forward + revert migration committed alongside the SQL views.
5. Apply forward migration to prod **inside a transaction-rollback dry-run first**, then for real.
6. `scripts/revert-spec2a.sh` mirrors `scripts/revert-phase1.sh`: drops the SQL views, git-reverts the merge commit, redeploys nothing (no edge function changes in 2A).
7. Each new component is additive — no surface that exists today is removed; toggles default to current behavior.

## 6. Work items (groups → atomic tasks)

Numbered in the order they ship. Each item is one git commit. Order matters: DB views must exist before the hooks that read them.

### Group A — Setup

- **A.1** Pre-flight tag and branch.
- **A.2** Pin spec/plan refs in `.planning/spec2a/README.md`.

### Group B — DB views

- **B.1** Forward migration `<ts>_spec2a_views.sql`:
  - `CREATE OR REPLACE VIEW v_yesterday_recap` — yesterday's revenue, jobs, callbacks, cancellations grouped by tech (and a company-total row).
  - `CREATE OR REPLACE VIEW v_jobs_needing_review` — rule-flagged jobs with reason column. Columns: `job_id, tech_id, scheduled_at, completed_at, revenue_amount, customer_name, reason ('callback_30d' | 'low_rating' | 'refunded'), reason_detail, age_days`.
  - GRANT SELECT to authenticated for both.
- **B.2** Revert migration `<ts>_spec2a_views_revert.sql`: `DROP VIEW IF EXISTS` for both views.
- **B.3** Transaction-rollback dry-run on prod (BEGIN; create views; SELECT count from each; ROLLBACK). Same pattern as Phase 1 Task 5.
- **B.4** Apply forward migration to prod via `mcp__apply_migration`.

### Group C — Hooks

- **C.1** Extend `src/hooks/use-rev-rise-data.ts`:
  - Add `mode: 'call-window' | 'daily'` parameter.
  - When `mode === 'daily'`, override `dateRange` to yesterday's range.
  - Replace the `reviews: 0, avgRating: 0` stubs (lines 143-144) with a join against `public.reviews` filtered by tech_id and `review_at` within the range. Surface `count` and `avg`.
  - Keep call-window math unchanged so existing Rev & Rise views are untouched.
- **C.2** New `src/hooks/use-jobs-needing-review.ts` — reads `v_jobs_needing_review`. Returns `{ rows, isLoading, error }`. TanStack Query with stable queryKey (memoized range + tech filter).
- **C.3** New `src/hooks/use-drilldown-jobs.ts` — generic. Takes `{ kpi, tech_id?, dateRange }` filter spec. Returns matching `jobs` rows. Each KPI maps to a different `where` clause:
  - `revenue` → completed jobs in range
  - `closing` → all opportunities (sold + unsold) in range
  - `callback` → jobs where `is_callback = true` in range
  - `membership` → jobs where `membership_attached = true` in range
- **C.4** New `src/hooks/use-yesterday-recap.ts` — reads `v_yesterday_recap`.

### Group D — Drilldown component

- **D.1** New `src/components/dashboard/DrilldownSheet.tsx`:
  - Wraps `@radix-ui/react-dialog` Sheet (or `vaul` per Twins convention — confirm during planning).
  - Header shows KPI label + filter context (tech, date range).
  - Body uses `@tanstack/react-virtual` for the job list.
  - Each row: customer name, $ amount, scheduled date, tech, status pill, click → opens HCP at the right URL in new tab.
  - Loading/empty/error states match Twins' shadcn `EmptyState` pattern (or fallback to current loading-spinner pattern if EmptyState doesn't exist yet — that's a Spec 3 cleanup).
- **D.2** Drilldown wiring on `src/pages/Index.tsx` — make the 4 main KPI tiles clickable, opening `DrilldownSheet` with the right `kpi` arg.
- **D.3** Drilldown wiring on `src/components/dashboard/TechnicianBreakdown.tsx` — per-tech metric click → tech-scoped drilldown.
- **D.4** Drilldown wiring on `src/components/rev-rise/PerTechCards.tsx` — same pattern.

### Group E — Rev & Rise daily-mode

- **E.1** New `src/components/rev-rise/ModeToggle.tsx` — segmented control: `Call window | Daily`. Default = `call-window` reads from URL param `?mode=`.
- **E.2** Refactor `src/pages/RevRiseDashboard.tsx` — read `?mode` from URL. If `daily`, render the new daily blocks; if `call-window` (or unset), render current.
- **E.3** New `src/components/rev-rise/YesterdayRecap.tsx` — single tile, uses `use-yesterday-recap`.
- **E.4** New `src/components/rev-rise/DispatchQueue.tsx` — today's scheduled jobs by tech with unassigned bucket. Reuses `DayAhead.tsx` rendering against today's date instead of tomorrow's.
- **E.5** New `src/components/rev-rise/JobsNeedingReview.tsx` — uses `use-jobs-needing-review`. List with reason chip, click → drilldown to that single job in HCP (one-job-only sheet).
- **E.6** New `src/components/rev-rise/MissedMoneyPanel.tsx` — placeholder. Counts open estimates aging > 48h + total $ + a CTA disabled with tooltip "Spec 2B".

### Group F — Reviews/ratings surface

- **F.1** Update `src/components/rev-rise/PerTechCards.tsx` to render the per-tech rating chip when avgRating > 0. Format: `★ 4.7 (12)`.
- **F.2** Update `src/components/dashboard/TechnicianBreakdown.tsx` to render the same chip.

### Group G — Revert script

- **G.1** New `scripts/revert-spec2a.sh` mirroring `revert-phase1.sh`:
  - Apply revert migration (drops the 2 views).
  - Find spec2a merge commit on main, `git revert -m 1`, push.
  - No edge function redeploy needed (no edge function changes in 2A).
  - Verify both views are gone with `SELECT COUNT(*) FROM information_schema.views WHERE table_name IN ('v_yesterday_recap','v_jobs_needing_review')` → 0.

### Group H — Verification & merge

- **H.1** Local verification: `npm run build`, `npm test`, `npm run lint`.
- **H.2** Vercel preview smoke: open the preview URL, click through `/`, `/leaderboard`, `/admin`, `/rev-rise` (toggle to daily), confirm drilldowns open and show data.
- **H.3** Open PR.
- **H.4** Merge with merge-commit (so revert script can find the merge SHA).
- **H.5** Post-deploy verification on twinsdash.com: same click-through. Drilldown data matches what the underlying KPI tile says.

## 7. The revert procedure ("type revert")

```sh
./scripts/revert-spec2a.sh
```

Same shape as Phase 1's revert. Pre-merge tag `pre-spec2a-2026-05-05` is the hard-reset target if the script itself fails.

## 8. Verification plan

1. **Build green** on the branch and on main after merge.
2. **Lint warning count** stays at or below current baseline (~496 — Phase 1 set this floor).
3. **Daily-mode toggle** visibly switches the body of `/rev-rise` between call-window and daily layouts.
4. **Yesterday recap card** renders with non-zero values (you complete a real ticket in HCP, wait 60s, refresh — yesterday's bar should reflect it).
5. **Jobs-needing-review** populates from `v_jobs_needing_review`. The reason chip matches the underlying rule that flagged the row.
6. **Drilldown sheet** opens on Index/TechnicianBreakdown/PerTechCards. Job rows match the count shown in the parent KPI tile.
7. **Reviews/ratings** chip appears on per-tech cards for techs with at least one attributed review. Star rating matches `AVG(rating)` from `public.reviews`.
8. **No regression** in call-window-mode Rev & Rise behavior. Revenue, closing %, avg ticket all match what they showed before this branch merged.

Acceptance = 8/8 pass.

## 9. Open questions

None. All four locked during brainstorm:
1. Scope: 2A is daily-mode + drilldowns + reviews; 2B is watchlist + behaviors + open-estimates.
2. Daily-mode UX: toggle in header, default = call-window.
3. Drilldown UX: Radix Sheet panel.
4. Jobs-review rules: starter set (callback / low rating / refunded).

## 10. Acceptance criteria

- [ ] All 8 verification items above pass.
- [ ] `scripts/revert-spec2a.sh` is committed, executable, transaction-rollback-tested on prod.
- [ ] Pre-spec2a git tag `pre-spec2a-2026-05-05` exists on origin.
- [ ] Branch is merged to main with a merge commit (revert target).
- [ ] No KPI math changed.
- [ ] Reviews/ratings replace `// TODO` stubs (`use-rev-rise-data.ts:143-144`).
- [ ] Documented in `.planning/spec2a/DEFERRED.md`: what's pushed to Spec 2B + why.

## 11. Deliverables

When 2A ships:

1. One merged PR on `palpulla/twins-dash` containing the full Spec 2A work.
2. `scripts/revert-spec2a.sh` in the repo.
3. The pre-spec2a git tag.
4. New `.planning/spec2a/` directory with README, DEFERRED, and verification report.
5. PR description matching the verification checklist.

## 12. Out-of-scope footguns to be aware of during execution

- **Watchlist** is genuinely high-leverage (CEO agent flagged it as the single best Index addition). Don't be tempted to slip it into 2A. It's 2B's whole purpose.
- **Spring attach / financing / photos** require parsing HCP `line_items` SKUs and identifying customer-facing actions in `hcp_data` — non-trivial extraction work. Strictly 2B.
- **Coaching-assignment write-back** (mentioned in audit §6) is its own surface — write a checkpoint, mark done, persist. Defer to Spec 2C if we split further.
- **Capacity utilization** and **CSR / unbooked-call drilldown** also live in 2B/2C territory.

## 13. Next phase

After 2A ships and observes for ~48h, brainstorm starts for **Spec 2B — CEO leverage:** Watchlist panel + Behaviors row + Open Estimates page (`/follow-ups`). Same brainstorm → spec → plan → execute loop.
