# Goals Visibility — Design Spec

**Date:** 2026-07-03
**Repo:** palpulla/twins-dash (Vite app, twinsdash.com)
**Problem:** Company and individual goals exist in the system (Admin → Goals, `tech_revenue_goals`, tier ladders) but the team doesn't know them. The Annual Revenue hero on the company dashboard is hidden behind an off-by-default toggle; techs see their goal only if they dig. This is a visibility problem, not a missing-feature problem.

**Decision trail:** Daniel confirmed (a) goals in the system are correct, just invisible; (b) techs should see own goal + company goal (not other techs' goals); (c) annual goal is the headline with a this-month supporting line; (d) layout option B — upgraded personal hero + slim company ribbon; (e) UI must be visually appealing, premium-feeling, not busy.

---

## Scope

Two pages change. No new tabs, no new admin screens, no notifications, no changes to Leaderboard, Wins, tier ladders, or any KPI/payroll math. Goals continue to be edited where they are today (Admin → Goals, tech revenue goals panel).

### 1. Tech Home (`/tech`) — main fix

**Upgraded personal hero** (evolves existing `HeroEstimate`):
- Headline: YTD revenue vs annual personal goal (from `tech_revenue_goals`), full dollar amounts ("$212,400 of $480,000"), percent complete.
- **Pace chip:** "on pace" (emerald) or "behind pace — need $2,300/wk more" (amber). Computed as % of goal reached vs % of year elapsed.
- **This-month line:** "July: $9,800 of $40,000" — tech MTD revenue vs annual goal ÷ 12.
- Animated progress bar (see UI polish).

**Slim company ribbon** directly beneath the hero:
- One line: "Company: $1,264,000 of $2,400,000 (53%)" + thin progress bar + pace chip.
- Quiet visual weight: smaller type, muted background, thin bar. The personal hero stays the star.

If a tech has no revenue goal set for the current year, the personal hero shows revenue without goal framing (current behavior) and the company ribbon still renders.

### 2. Company dashboard (`/`) — Index.tsx

- `AnnualRevenueHero` becomes **always-on** (remove the `showGoalCompare` gating for the hero; the toggle may remain for other compare features it controls, or be removed if it controls only this — verify during implementation).
- Add the same **pace chip** and **this-month line** ("July: $31,200 of $200,000").
- No other layout changes to Index.tsx.

### 3. Display math (display-layer only)

- **Pace:** `pctOfGoal = ytdRevenue / annualGoal`; `pctOfYear = dayOfYear / daysInYear`. On pace when `pctOfGoal >= pctOfYear`. Local-time dates, consistent with existing dashboard conventions.
- **Monthly target:** `annualGoal / 12`, rounded for display. (Seasonal weighting explicitly deferred — revisit if winter months feel off.)
- **Needed weekly run-rate (behind-pace chip only):** when behind pace, the chip reads "behind pace — need $X/wk" where `X = (annualGoal − ytdRevenue) / weeksRemainingInYear`, rounded to the nearest $100. When on pace the chip is just "on pace". Keep the chip copy short.
- **Revenue definition:** identical to what each page already displays (canonical kpi-calculations functions; earned revenue with `outstanding_balance == 0` recognition). No new revenue math — these components consume existing computed values.
- All currency: full dollar amounts, rounded, no cents, never "$Xk".

### 4. Data access for techs (one new read path)

Techs' RLS scope today blocks company-wide aggregates. Add one **SECURITY DEFINER RPC** (or equivalently scoped view), e.g. `get_company_goal_progress()`:
- Returns exactly: company YTD earned revenue (canonical definition), company MTD earned revenue, annual company revenue goal (`company_goals.revenue_annual`). Current calendar year, server-computed.
- Callable by any authenticated user (all roles); no parameters that widen the read; no per-job or per-tech data exposed.
- `search_path` pinned, EXECUTE revoked from `anon`, per the hardening conventions already in place.

Frontend: one new hook (e.g. `useCompanyGoalProgress`) with `enabled: !!session` (standing rule) consumed by both the tech ribbon and available to Index if convenient.

---

## UI polish requirements (first-class, per Daniel)

The visual bar is "premium scoreboard", anchored to Index.tsx's existing visual language (gold/amber accent, emerald for good, clean cards):

- **Animated fill:** progress bars animate from 0 to value on mount (CSS transition, ~700ms ease-out). No re-animation on data refetch.
- **Gradient bars:** amber→gold gradient for in-progress, emerald gradient when ≥100% or on pace, matching existing `GoalGauge` tones.
- **Pace chip:** pill with subtle background tint (emerald-50/amber-50 style tokens consistent with the app's palette), small dot or icon, no flashing/pulsing.
- **Milestone ticks:** faint tick marks on the annual bar at 25/50/75% so progress reads spatially at a glance.
- **Typography:** big value uses the same numeric styling as existing hero cards (tabular figures if already used); goal amount in muted text.
- **Restraint:** no confetti, no charts added to the hero, no more than one accent color per element. The section must not make the page feel busier than today.
- **Mobile:** hero and ribbon stack cleanly at mobile widths, fit the screen with no horizontal scroll; the ribbon stays one line by truncating to "Company: 53% · on pace" form at the narrowest widths.
- **Dark mode:** verify both components in the app's existing dark theme if present; use theme tokens, not hardcoded hex.

---

## Components

| Unit | Purpose | Depends on |
|---|---|---|
| `PaceChip` (new, shared) | Renders on-pace/behind-pace pill from `{pct, pctOfYear, weeklyNeeded?}` | none (pure) |
| `GoalHero` (evolved `HeroEstimate`) | Personal annual+monthly goal display | `useTechRevenueGoal`, existing tech revenue data, `PaceChip` |
| `CompanyGoalRibbon` (new) | Slim company progress line | `useCompanyGoalProgress`, `PaceChip` |
| `AnnualRevenueHero` (modified) | Always-on, gains pace chip + month line | existing props + `PaceChip` |
| `useCompanyGoalProgress` (new hook) | Fetch RPC, `enabled: !!session` | new RPC |
| `get_company_goal_progress()` (new RPC) | Safe aggregate for all roles | migration |

Pace/monthly math lives in one small pure module (e.g. `src/lib/goal-pace.ts`) with unit tests, shared by both pages so the numbers can never disagree.

## Testing

- Unit tests for `goal-pace.ts`: on/behind pace boundaries, year edges (Jan 1, Dec 31), leap year, missing goal (returns null framing), monthly target rounding.
- Component render tests: hero with goal, hero without goal, ribbon truncation variant.
- Existing KPI snapshot/parity tests must pass untouched (proof of zero KPI-math change).
- RPC: verify anon is rejected, authenticated tech receives exactly the three fields.

## Error/empty handling

- RPC failure or no `revenue_annual` goal set → ribbon renders nothing (no error card, no layout jump beyond its own absence).
- No tech goal set → hero falls back to current no-goal presentation.
- Loading → skeleton shimmer consistent with existing cards, no layout shift.

## Rollout / reversibility

- One branch, one PR, standard CI (build + strict ratchet + vitest). Migration is additive (one function); rollback = revert PR + drop function.
- No data migrations, no writes to any table.
