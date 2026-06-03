# Revenue recognition fix + clickable revenue breakdown

**Date:** 2026-06-03
**Repo:** `twins-dash` (Vite app, twinsdash.com), Supabase `jwrpjuqaynownxaoeayi`
**Branch base:** `origin/main`

## Problem

The main dashboard's "Total Sales" headline overstates earned revenue. It sums
`revenue_amount` for every job with `completed_at` set and `revenue_amount > 0`,
ignoring whether the customer has actually paid.

Confirmed against live data — week of May 22-28 (Fri-Thu):

| | Amount |
|---|---|
| Headline "Total Sales" | $28,747 |
| Paid in full (earned) | $12,884 |
| Sold, balance still owed | $15,863 (2 jobs) |

Almost all of the gap is **one job**: a $15,814 Bridgeport steel door install
(John Sturges, completed 5/25) where the full balance is still outstanding, plus
a $49 unpaid tune-up. A rolling 7-day window landing on that week reads ~$33k,
which is what Daniel saw and flagged as wrong.

This violates the documented recognition rule: **revenue is earned only when
`outstanding_balance == 0`**. Sold-but-unpaid work belongs in its own bucket and
must never inflate earned revenue.

## Decisions (confirmed with Daniel)

1. **Exclude sold-but-not-paid jobs from revenue entirely** — not just flag them.
   Earned revenue = `completed_at` set AND `revenue_amount > 0` AND
   `outstanding_balance == 0`.
2. **Breakdown opens as a slide-over panel** (reuse the existing orphaned
   `DrilldownSheet`), triggered by clicking the Total Sales card.

## Data notes

- `outstanding_balance` lives inside the `jobs.hcp_data` JSON, not a top-level
  column. It is stored in **cents**; `revenue_amount` is in **dollars**. The
  exclusion is a binary `> 0` check, so units don't matter for filtering — only
  for *displaying* the owed total (divide by 100).
- `outstanding_balance` can go stale after a payment until the next
  `sync-hcp-jobs` run. We trust the current synced value for display; this is a
  known caveat, not a blocker.
- Exclusion is per-job and binary: if `outstanding_balance > 0`, the whole job's
  `revenue_amount` is excluded from earned revenue (it does not partially count).

## Scope — what changes

The recognition rule must be applied consistently everywhere the **main
dashboard** computes earned revenue from completed jobs:

1. **`src/hooks/use-dashboard-data.ts`** — add `outstanding_balance == 0` to the
   revenue set. Filter in JS after fetch (value is in `hcp_data`). This corrects
   `totalRevenue`, `paidJobs`, and `completedRevenueJobsCount`, and — because
   `priorDashboard` reuses this hook — the prior-period number too. Also return
   the excluded set + owed total so the UI can show it.
2. **`src/hooks/use-period-comparison.ts`** — add `outstanding_balance == 0`
   (select `hcp_data`, filter in JS) so the "vs prior period" delta compares
   like-for-like.
3. **`src/hooks/use-drilldown-jobs.ts`** — replace the `is_opportunity`-based
   revenue predicate with the headline predicate. Surface owed jobs too (flag
   each row paid vs balance-due) so the panel explains the number.
4. **`src/pages/Index.tsx`** — make the Total Sales `HeroScoreboard` clickable to
   open the panel; add a small "awaiting payment: $X (N jobs)" line under the
   headline when owed > 0 (full dollars, no $Xk).
5. **`src/components/dashboard/DrilldownSheet.tsx`** — wire it in; show an
   earned-vs-owed subtotal split and a paid/owed flag per row.

## Out of scope (deliberately untouched)

Tech scorecards, payroll/commission math, and the rev-rise dashboard each have
their own revenue semantics and Daniel did not ask to change them. The
`ComparisonSection` widget computes its own revenue via `use-comparison-data`; if
it diverges visibly it gets the same fix, otherwise left alone to keep the change
focused.

## Success criteria

- Total Sales headline for May 22-28 reads ~$12,884, not $28,747.
- Clicking the headline opens a panel listing every job in the picked timeframe,
  each showing customer, tech, revenue, and a paid/balance-due flag, with an
  earned-vs-owed subtotal split.
- The "vs prior period" delta uses the corrected number on both sides.
- All currency renders as full dollar amounts (e.g. $12,884), never $13k.
- No change to tech scorecard, payroll, or rev-rise numbers.
- Change lands on a feature branch off `origin/main`; fully reversible.
