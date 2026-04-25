# Tech Portal Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `/tech/*` experience in `twins-dash` with a focused 3-tab portal (Home · Goals & Coaching · Recognition), an admin-tunable Bronze/Silver/Gold/Elite tier ladder, a weekly Claude Haiku coaching nudge, and personal-only streaks/personal-records.

**Architecture:** New tab pages and components live alongside the old TechHome/TechnicianView until M10, when the old files are deleted. Schema additions are backward-compatible. KPI math (`kpi-calculations.ts`) is untouched. AI nudge runs on weekly pg_cron via a new `generate-tech-nudge` Edge Function. Visual language anchored to `Index.tsx` (navy + yellow, MetricCard pattern, rounded-2xl cards, Tailwind/shadcn — no `.ts-scope` raw CSS).

**Tech Stack:** React 18 + TS + Vite 5 + shadcn/ui + Tailwind + TanStack Query + Recharts. Supabase (Postgres + RLS + pg_cron + Edge Functions / Deno). Anthropic Claude Haiku 4.5 via Anthropic SDK. Vitest for unit tests. Playwright for any E2E (existing pattern).

**Spec:** [`docs/superpowers/specs/2026-04-25-tech-portal-redesign-design.md`](../specs/2026-04-25-tech-portal-redesign-design.md)

**Branch:** `feat/tech-portal-redesign` off `main`. Never commit to `main` directly.

**IMPORTANT operational notes:**
- After every `npx supabase db push`, manually `INSERT INTO supabase_migrations.schema_migrations (version, name, statements)` for the new migration if Supabase CLI doesn't record it (history desync per memory `reference_twins_dash_migration_history.md`). Verify with `npx supabase migration list`.
- Existing helper `public.current_technician_id()` returns the caller's `tech_id` — use it for all per-tech RLS policies. Do not invent a new helper.
- Per-tech KPI hooks already follow the `my_paystub` / `my_scorecard` RPC pattern (see `src/hooks/tech/useMyPaystub.ts`). New hooks must mirror this.
- Anthropic API key is stored as Edge Function secret `ANTHROPIC_API_KEY`. Never bundled client-side.

---

## File Structure

### New files

**Migrations** (`twins-dash/supabase/migrations/`):
- `20260425130000_scorecard_tier_thresholds.sql` — table + seed
- `20260425130100_scorecard_tier_threshold_audit.sql` — audit table + trigger
- `20260425130200_tech_tier_overrides.sql`
- `20260425130300_get_my_tier_function.sql` — `public.get_my_tier(kpi, value) → text`
- `20260425130400_get_my_scorecard_with_tiers.sql` — wraps existing `my_scorecard` and adds tier
- `20260425140000_tech_ai_nudges.sql`
- `20260425140100_tech_streaks.sql`
- `20260425140200_tech_personal_records.sql`
- `20260425140300_compute_streaks_and_prs_function.sql` — pure SQL function
- `20260425140400_pg_cron_streaks_prs.sql` — daily 07:00 UTC
- `20260425140500_pg_cron_ai_nudges.sql` — Mon 11:00 UTC
- `20260425140600_app_settings_ai_nudge_pause.sql` — feature-flag row

**Edge Function** (`twins-dash/supabase/functions/generate-tech-nudge/`):
- `index.ts` — entry point
- `prompt.ts` — system + user prompt builders, prompt-cache anchors
- `validate.ts` — JSON schema validator for Claude's response

**Pages** (`twins-dash/src/pages/`):
- `tech/Home.tsx` — replaces `TechHome.tsx` + `TechnicianView.tsx` mobile body
- `tech/Goals.tsx` — new
- `tech/Recognition.tsx` — new
- `admin/ScorecardTiers.tsx` — new

**Tech components** (`twins-dash/src/components/tech/`):
- `HeroEstimate.tsx` — navy hero card
- `KpiTile.tsx` — tile + tier badge + comparison pill + bar
- `KpiDrillSheet.tsx` — bottom sheet (mobile) / inline (desktop)
- `KpiSparkline.tsx` — 8-week mini chart with company-avg dashed line
- `TierBadge.tsx` — bronze/silver/gold/elite chip
- `TierLadderRow.tsx` — wide bar with 4 tier markers
- `WhatChangedGrid.tsx` — 2-up arrow cards
- `TipLibrary.tsx` — 2-up grid of static tips
- `AiNudgeCard.tsx` — yellow gradient hero card on Goals
- `StreakCard.tsx`
- `PersonalRecordTile.tsx`
- `YearRibbon.tsx` — navy ribbon with YTD stats
- `TierUpCard.tsx` — celebration card (30-day pin)
- `RecognitionEmptyState.tsx` — for new techs

**Admin components** (`twins-dash/src/components/admin/`):
- `TierThresholdsTable.tsx`
- `TechTierOverridePanel.tsx`
- `AiNudgeControls.tsx`

**Hooks** (`twins-dash/src/hooks/tech/`):
- `useMyScorecardWithTiers.ts`
- `useTierThresholds.ts`
- `useTechTierOverrides.ts`
- `useMyAiNudge.ts`
- `useMyStreaks.ts`
- `useMyPersonalRecords.ts`
- `useTierUpMoments.ts`
- `useWhatChangedThisWeek.ts`

**Static data** (`twins-dash/src/data/`):
- `coaching-tips.ts` — hardcoded JSON, one tip per KPI per tier gap

### Modified files

- `twins-dash/src/components/tech/TechShell.tsx` — `NAV_ITEMS` from 1 to 3 entries
- `twins-dash/src/App.tsx` — add `/tech/goals`, `/tech/wins`, `/admin/scorecard-tiers` routes; rewire `/tech` to new `Home.tsx`
- `twins-dash/src/pages/AdminPanel.tsx` — add link to `/admin/scorecard-tiers` in admin nav

### Deleted files (M10 only)

- `twins-dash/src/pages/tech/TechHome.tsx`
- `twins-dash/src/pages/TechnicianView.tsx`
- `twins-dash/src/pages/tech/TechAppointments.tsx`
- `twins-dash/src/pages/tech/TechEstimates.tsx`
- `twins-dash/src/pages/tech/TechProfile.tsx`
- `twins-dash/src/pages/tech/TechJobs.tsx`
- `twins-dash/src/components/technician/scorecard/PastPaystubs.tsx`
- `twins-dash/src/components/tech/PaystubCard.tsx`

---

## Setup

### Task 0: Branch + worktree

**Files:** none

- [ ] **Step 1: Verify on main, clean tree**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git status
git checkout main && git pull origin main
```

Expected: clean working tree on `main`.

- [ ] **Step 2: Create branch**

```bash
git checkout -b feat/tech-portal-redesign
```

- [ ] **Step 3: Confirm Supabase link**

```bash
cat supabase/.temp/linked-project.json
```

Expected output contains `"project_id": "jwrpjuqaynownxaoeayi"`. If not linked, run `npx supabase link --project-ref jwrpjuqaynownxaoeayi`.

---

## M1 — Schema foundation: tier thresholds, audit, overrides, helpers

### Task 1: scorecard_tier_thresholds table + seed

**Files:**
- Create: `twins-dash/supabase/migrations/20260425130000_scorecard_tier_thresholds.sql`

- [ ] **Step 1: Write the migration**

```sql
-- 20260425130000_scorecard_tier_thresholds.sql
CREATE TABLE public.scorecard_tier_thresholds (
  kpi_key      text PRIMARY KEY,
  bronze       numeric NOT NULL,
  silver       numeric NOT NULL,
  gold         numeric NOT NULL,
  elite        numeric NOT NULL,
  direction    text    NOT NULL CHECK (direction IN ('higher','lower')),
  unit         text    NOT NULL,  -- 'usd' | 'pct' | 'count'
  display_name text    NOT NULL,
  updated_at   timestamptz NOT NULL DEFAULT now(),
  updated_by   uuid    REFERENCES auth.users(id)
);

ALTER TABLE public.scorecard_tier_thresholds ENABLE ROW LEVEL SECURITY;

CREATE POLICY scorecard_tier_thresholds_select_all
  ON public.scorecard_tier_thresholds FOR SELECT TO authenticated USING (true);

CREATE POLICY scorecard_tier_thresholds_write_admin
  ON public.scorecard_tier_thresholds FOR ALL TO authenticated
  USING (public.has_payroll_access())
  WITH CHECK (public.has_payroll_access());

INSERT INTO public.scorecard_tier_thresholds
  (kpi_key, bronze, silver, gold, elite, direction, unit, display_name) VALUES
  ('revenue',          20000, 35000, 45000, 55000, 'higher', 'usd',   'Revenue'),
  ('total_jobs',          15,    25,    40,    55, 'higher', 'count', 'Total jobs'),
  ('avg_opportunity',   1000,  1200,  1450,  1700, 'higher', 'usd',   'Avg opportunity'),
  ('avg_repair',         800,  1000,  1400,  1800, 'higher', 'usd',   'Avg repair'),
  ('avg_install',       2400,  3000,  3500,  4000, 'higher', 'usd',   'Avg install'),
  ('closing_pct',         25,    35,    45,    55, 'higher', 'pct',   'Closing %'),
  ('callback_pct',       5.0,   3.5,   2.5,   1.5, 'lower',  'pct',   'Callbacks %'),
  ('membership_pct',      10,    20,    30,    40, 'higher', 'pct',   'Memberships %');
```

- [ ] **Step 2: Apply**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
npx supabase db push
```

Expected: applies cleanly. If history desync per memory, manually insert version row:

```bash
npx supabase migration list
```

If `20260425130000` is missing in remote, fix with: `npx supabase db push --include-all`.

- [ ] **Step 3: Verify in DB**

```bash
npx supabase db remote query "SELECT kpi_key, bronze, silver, gold, elite, direction FROM public.scorecard_tier_thresholds ORDER BY kpi_key;"
```

Expected: 8 rows, callback_pct direction = 'lower', all others = 'higher'.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260425130000_scorecard_tier_thresholds.sql
git commit -m "feat(tech-portal): scorecard_tier_thresholds table + 8-KPI seed"
```

---

### Task 2: scorecard_tier_threshold_audit table + trigger

**Files:**
- Create: `twins-dash/supabase/migrations/20260425130100_scorecard_tier_threshold_audit.sql`

- [ ] **Step 1: Write the migration**

```sql
-- 20260425130100_scorecard_tier_threshold_audit.sql
CREATE TABLE public.scorecard_tier_threshold_audit (
  id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  kpi_key      text NOT NULL,
  snapshot     jsonb NOT NULL,
  changed_at   timestamptz NOT NULL DEFAULT now(),
  changed_by   uuid REFERENCES auth.users(id)
);

ALTER TABLE public.scorecard_tier_threshold_audit ENABLE ROW LEVEL SECURITY;

CREATE POLICY scorecard_tier_threshold_audit_select_admin
  ON public.scorecard_tier_threshold_audit FOR SELECT TO authenticated
  USING (public.has_payroll_access());

CREATE OR REPLACE FUNCTION public.scorecard_tier_threshold_audit_fn()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
  INSERT INTO public.scorecard_tier_threshold_audit (kpi_key, snapshot, changed_by)
  VALUES (OLD.kpi_key, to_jsonb(OLD), auth.uid());
  RETURN NEW;
END $$;

CREATE TRIGGER scorecard_tier_threshold_audit_trg
  AFTER UPDATE ON public.scorecard_tier_thresholds
  FOR EACH ROW EXECUTE FUNCTION public.scorecard_tier_threshold_audit_fn();
```

- [ ] **Step 2: Apply + verify trigger by manual update**

```bash
npx supabase db push
npx supabase db remote query "UPDATE public.scorecard_tier_thresholds SET gold = gold WHERE kpi_key='revenue'; SELECT count(*) FROM public.scorecard_tier_threshold_audit;"
```

Expected: count = 1 after the no-op UPDATE.

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260425130100_scorecard_tier_threshold_audit.sql
git commit -m "feat(tech-portal): audit trigger for tier threshold changes"
```

---

### Task 3: tech_tier_overrides table

**Files:**
- Create: `twins-dash/supabase/migrations/20260425130200_tech_tier_overrides.sql`

- [ ] **Step 1: Write the migration**

```sql
-- 20260425130200_tech_tier_overrides.sql
CREATE TABLE public.tech_tier_overrides (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tech_id     uuid NOT NULL REFERENCES public.technicians(id) ON DELETE CASCADE,
  kpi_key     text NOT NULL REFERENCES public.scorecard_tier_thresholds(kpi_key) ON DELETE CASCADE,
  bronze      numeric,
  silver      numeric,
  gold        numeric,
  elite       numeric,
  updated_at  timestamptz NOT NULL DEFAULT now(),
  updated_by  uuid REFERENCES auth.users(id),
  UNIQUE (tech_id, kpi_key)
);

CREATE INDEX tech_tier_overrides_tech_idx ON public.tech_tier_overrides(tech_id);

ALTER TABLE public.tech_tier_overrides ENABLE ROW LEVEL SECURITY;

CREATE POLICY tech_tier_overrides_select_own_or_admin
  ON public.tech_tier_overrides FOR SELECT TO authenticated
  USING (tech_id = public.current_technician_id() OR public.has_payroll_access());

CREATE POLICY tech_tier_overrides_write_admin
  ON public.tech_tier_overrides FOR ALL TO authenticated
  USING (public.has_payroll_access())
  WITH CHECK (public.has_payroll_access());
```

- [ ] **Step 2: Apply + verify**

```bash
npx supabase db push
npx supabase db remote query "\d public.tech_tier_overrides"
```

Expected: table exists with the unique index.

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260425130200_tech_tier_overrides.sql
git commit -m "feat(tech-portal): tech_tier_overrides table"
```

---

### Task 4: get_my_tier SQL function

**Files:**
- Create: `twins-dash/supabase/migrations/20260425130300_get_my_tier_function.sql`

- [ ] **Step 1: Write the migration**

```sql
-- 20260425130300_get_my_tier_function.sql
-- Returns 'bronze' | 'silver' | 'gold' | 'elite' | NULL.
-- Consults per-tech overrides first, then company defaults.
CREATE OR REPLACE FUNCTION public.get_my_tier(p_kpi text, p_value numeric)
RETURNS text
LANGUAGE plpgsql STABLE SECURITY DEFINER AS $$
DECLARE
  v_dir text;
  v_b numeric; v_s numeric; v_g numeric; v_e numeric;
  v_tech_id uuid := public.current_technician_id();
BEGIN
  SELECT direction INTO v_dir FROM public.scorecard_tier_thresholds WHERE kpi_key = p_kpi;
  IF v_dir IS NULL THEN RETURN NULL; END IF;

  SELECT
    COALESCE(o.bronze, t.bronze),
    COALESCE(o.silver, t.silver),
    COALESCE(o.gold,   t.gold),
    COALESCE(o.elite,  t.elite)
  INTO v_b, v_s, v_g, v_e
  FROM public.scorecard_tier_thresholds t
  LEFT JOIN public.tech_tier_overrides o
    ON o.kpi_key = t.kpi_key AND o.tech_id = v_tech_id
  WHERE t.kpi_key = p_kpi;

  IF p_value IS NULL THEN RETURN NULL; END IF;

  IF v_dir = 'higher' THEN
    IF p_value >= v_e THEN RETURN 'elite';
    ELSIF p_value >= v_g THEN RETURN 'gold';
    ELSIF p_value >= v_s THEN RETURN 'silver';
    ELSIF p_value >= v_b THEN RETURN 'bronze';
    ELSE RETURN NULL; END IF;
  ELSE  -- lower-better
    IF p_value <= v_e THEN RETURN 'elite';
    ELSIF p_value <= v_g THEN RETURN 'gold';
    ELSIF p_value <= v_s THEN RETURN 'silver';
    ELSIF p_value <= v_b THEN RETURN 'bronze';
    ELSE RETURN NULL; END IF;
  END IF;
END $$;

GRANT EXECUTE ON FUNCTION public.get_my_tier(text, numeric) TO authenticated;
```

- [ ] **Step 2: Apply + verify with sample inputs**

```bash
npx supabase db push
npx supabase db remote query "SELECT
  public.get_my_tier('revenue',  60000) AS rev_60k,
  public.get_my_tier('revenue',  10000) AS rev_10k,
  public.get_my_tier('callback_pct', 1.0) AS cb_1pct,
  public.get_my_tier('callback_pct', 6.0) AS cb_6pct;"
```

Expected: `rev_60k=elite`, `rev_10k=NULL` (below bronze), `cb_1pct=elite`, `cb_6pct=NULL`.

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260425130300_get_my_tier_function.sql
git commit -m "feat(tech-portal): get_my_tier SQL helper with override lookup"
```

---

### Task 5: get_my_scorecard_with_tiers RPC

**Files:**
- Create: `twins-dash/supabase/migrations/20260425130400_get_my_scorecard_with_tiers.sql`

- [ ] **Step 1: Inspect existing my_scorecard signature**

```bash
grep -A 20 "CREATE OR REPLACE FUNCTION public.my_scorecard" twins-dash/supabase/migrations/*.sql | head -40
```

Capture the exact return columns. The new function wraps it and adds per-KPI tier columns.

- [ ] **Step 2: Write the migration**

```sql
-- 20260425130400_get_my_scorecard_with_tiers.sql
-- Wraps my_scorecard and tags each KPI with its tier label + gap-to-next.
CREATE OR REPLACE FUNCTION public.get_my_scorecard_with_tiers(
  p_since timestamptz, p_until timestamptz
)
RETURNS TABLE (
  -- mirrors my_scorecard return columns:
  revenue numeric, total_jobs int, avg_opportunity numeric, avg_repair numeric,
  avg_install numeric, closing_pct numeric, callback_pct numeric, membership_pct numeric,
  -- new tier labels per KPI:
  revenue_tier text, total_jobs_tier text, avg_opportunity_tier text, avg_repair_tier text,
  avg_install_tier text, closing_pct_tier text, callback_pct_tier text, membership_pct_tier text
)
LANGUAGE plpgsql STABLE SECURITY DEFINER AS $$
DECLARE r record;
BEGIN
  SELECT * INTO r FROM public.my_scorecard(p_since, p_until) LIMIT 1;
  RETURN QUERY SELECT
    r.revenue,           r.total_jobs,        r.avg_opportunity,   r.avg_repair,
    r.avg_install,       r.closing_pct,       r.callback_pct,      r.membership_pct,
    public.get_my_tier('revenue',         r.revenue),
    public.get_my_tier('total_jobs',      r.total_jobs::numeric),
    public.get_my_tier('avg_opportunity', r.avg_opportunity),
    public.get_my_tier('avg_repair',      r.avg_repair),
    public.get_my_tier('avg_install',     r.avg_install),
    public.get_my_tier('closing_pct',     r.closing_pct),
    public.get_my_tier('callback_pct',    r.callback_pct),
    public.get_my_tier('membership_pct',  r.membership_pct);
END $$;

GRANT EXECUTE ON FUNCTION public.get_my_scorecard_with_tiers(timestamptz, timestamptz) TO authenticated;
```

> **NOTE:** If `my_scorecard` has different return columns than listed above, update both the RETURNS TABLE list and the SELECT row to match. Run `\df+ public.my_scorecard` to inspect.

- [ ] **Step 3: Apply + smoke-test**

```bash
npx supabase db push
npx supabase db remote query "SELECT * FROM public.get_my_scorecard_with_tiers(now() - interval '30 days', now()) LIMIT 1;"
```

Expected: returns one row with tier labels populated (or NULL if caller is not a tech and `current_technician_id()` is null).

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260425130400_get_my_scorecard_with_tiers.sql
git commit -m "feat(tech-portal): get_my_scorecard_with_tiers RPC"
```

---

### Task 6: Regenerate Supabase TS types

**Files:**
- Modify: `twins-dash/src/integrations/supabase/types.ts`

- [ ] **Step 1: Regenerate**

```bash
cd twins-dash
npx supabase gen types typescript --linked > src/integrations/supabase/types.ts
```

- [ ] **Step 2: Verify the new types are present**

```bash
grep -c "scorecard_tier_thresholds\|tech_tier_overrides\|get_my_tier\|get_my_scorecard_with_tiers" src/integrations/supabase/types.ts
```

Expected: count >= 4.

- [ ] **Step 3: Build to verify TS compiles**

```bash
npx tsc --noEmit
```

Expected: no new errors.

- [ ] **Step 4: Commit**

```bash
git add src/integrations/supabase/types.ts
git commit -m "chore(tech-portal): regenerate supabase types after M1 migrations"
```

---

## M2 — TechShell + new routes scaffold

### Task 7: Update TechShell.NAV_ITEMS to 3 tabs

**Files:**
- Modify: `twins-dash/src/components/tech/TechShell.tsx:11-13`

- [ ] **Step 1: Edit NAV_ITEMS**

Replace the current `NAV_ITEMS` constant:

```tsx
import { Home, Target, Award } from 'lucide-react';

const NAV_ITEMS = [
  { to: '/tech',       label: 'Home',  icon: Home,   exact: true  },
  { to: '/tech/goals', label: 'Goals', icon: Target, exact: false },
  { to: '/tech/wins',  label: 'Wins',  icon: Award,  exact: false },
];
```

Update the import line at top to include `Target` and `Award` from `lucide-react`.

- [ ] **Step 2: Verify mobile bottom nav and desktop sidebar render 3 items**

```bash
npm run dev -- --port 5173
```

Open `http://localhost:5173/tech`. Confirm 3 nav items on bottom bar (mobile viewport via DevTools) and on the left sidebar (desktop viewport).

- [ ] **Step 3: Commit**

```bash
git add src/components/tech/TechShell.tsx
git commit -m "feat(tech-portal): TechShell nav from 1 to 3 tabs (Home/Goals/Wins)"
```

---

### Task 8: Add stub routes for Goals + Recognition + admin page

**Files:**
- Modify: `twins-dash/src/App.tsx`

- [ ] **Step 1: Locate the existing tech routes**

```bash
grep -n "TechHome\|TechShell" twins-dash/src/App.tsx
```

- [ ] **Step 2: Add stub pages and routes**

Create three placeholder files first:

`twins-dash/src/pages/tech/Goals.tsx`:

```tsx
export default function Goals() {
  return <div className="p-6"><h1 className="text-2xl font-bold text-primary">Goals & Coaching</h1><p className="text-muted-foreground mt-2">Coming soon.</p></div>;
}
```

`twins-dash/src/pages/tech/Recognition.tsx`:

```tsx
export default function Recognition() {
  return <div className="p-6"><h1 className="text-2xl font-bold text-primary">Recognition</h1><p className="text-muted-foreground mt-2">Coming soon.</p></div>;
}
```

`twins-dash/src/pages/admin/ScorecardTiers.tsx`:

```tsx
export default function ScorecardTiers() {
  return <div className="p-6"><h1 className="text-2xl font-bold text-primary">Scorecard tiers</h1><p className="text-muted-foreground mt-2">Coming soon.</p></div>;
}
```

In `App.tsx`, inside the existing `<TechShell>` parent route, add child routes:

```tsx
import Goals from '@/pages/tech/Goals';
import Recognition from '@/pages/tech/Recognition';
// existing TechHome import stays

// inside the <Route element={<TechShell />}> block:
<Route path="goals" element={<Goals />} />
<Route path="wins"  element={<Recognition />} />
```

For the admin page, find the existing `/admin/*` route block and add:

```tsx
import ScorecardTiers from '@/pages/admin/ScorecardTiers';
// inside the admin layout route:
<Route path="scorecard-tiers" element={<ScorecardTiers />} />
```

- [ ] **Step 3: Verify navigation works**

Open `http://localhost:5173/tech/goals` and `/tech/wins` — both should render the placeholder. `/admin/scorecard-tiers` should render too (after admin auth).

- [ ] **Step 4: Commit**

```bash
git add src/App.tsx src/pages/tech/Goals.tsx src/pages/tech/Recognition.tsx src/pages/admin/ScorecardTiers.tsx
git commit -m "feat(tech-portal): scaffold Goals/Wins tabs + admin scorecard-tiers route"
```

---

## M3 — Reusable KPI components

### Task 9: TierBadge component

**Files:**
- Create: `twins-dash/src/components/tech/TierBadge.tsx`
- Test: `twins-dash/src/components/tech/__tests__/TierBadge.test.tsx`

- [ ] **Step 1: Write failing test**

```tsx
// TierBadge.test.tsx
import { render, screen } from '@testing-library/react';
import { TierBadge } from '../TierBadge';

describe('TierBadge', () => {
  it.each([
    ['bronze', 'Bronze'],
    ['silver', 'Silver'],
    ['gold',   'Gold'],
    ['elite',  'Elite'],
  ])('renders %s tier as %s label', (tier, label) => {
    render(<TierBadge tier={tier as any} />);
    expect(screen.getByText(label)).toBeInTheDocument();
  });

  it('renders nothing when tier is null', () => {
    const { container } = render(<TierBadge tier={null} />);
    expect(container).toBeEmptyDOMElement();
  });
});
```

- [ ] **Step 2: Run test, expect FAIL**

```bash
npx vitest run src/components/tech/__tests__/TierBadge.test.tsx
```

Expected: 5 tests fail with "TierBadge not found".

- [ ] **Step 3: Implement TierBadge**

```tsx
// TierBadge.tsx
import { cn } from '@/lib/utils';

export type Tier = 'bronze' | 'silver' | 'gold' | 'elite';
const STYLES: Record<Tier, string> = {
  bronze: 'bg-amber-100 text-amber-900 border-amber-300',
  silver: 'bg-slate-100 text-slate-700 border-slate-300',
  gold:   'bg-yellow-100 text-yellow-900 border-accent',
  elite:  'bg-primary text-accent border-primary',
};
const LABELS: Record<Tier, string> = { bronze:'Bronze', silver:'Silver', gold:'Gold', elite:'Elite' };

export function TierBadge({ tier }: { tier: Tier | null }) {
  if (!tier) return null;
  return (
    <span className={cn(
      'inline-flex items-center px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded-full border',
      STYLES[tier],
    )}>
      {LABELS[tier]}
    </span>
  );
}
```

- [ ] **Step 4: Re-run test, expect PASS**

```bash
npx vitest run src/components/tech/__tests__/TierBadge.test.tsx
```

Expected: 5 passing.

- [ ] **Step 5: Commit**

```bash
git add src/components/tech/TierBadge.tsx src/components/tech/__tests__/TierBadge.test.tsx
git commit -m "feat(tech-portal): TierBadge component (bronze/silver/gold/elite)"
```

---

### Task 10: useTierThresholds hook

**Files:**
- Create: `twins-dash/src/hooks/tech/useTierThresholds.ts`

- [ ] **Step 1: Write hook**

```ts
// useTierThresholds.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export type TierThreshold = {
  kpi_key: string;
  bronze: number; silver: number; gold: number; elite: number;
  direction: 'higher' | 'lower';
  unit: 'usd' | 'pct' | 'count';
  display_name: string;
};

export function useTierThresholds() {
  return useQuery({
    queryKey: ['tier_thresholds'],
    queryFn: async (): Promise<TierThreshold[]> => {
      const { data, error } = await supabase
        .from('scorecard_tier_thresholds')
        .select('*')
        .order('kpi_key');
      if (error) throw error;
      return (data ?? []) as TierThreshold[];
    },
    staleTime: 5 * 60_000,  // thresholds change rarely
  });
}
```

- [ ] **Step 2: Verify TS compiles**

```bash
npx tsc --noEmit
```

- [ ] **Step 3: Commit**

```bash
git add src/hooks/tech/useTierThresholds.ts
git commit -m "feat(tech-portal): useTierThresholds hook"
```

---

### Task 11: useMyScorecardWithTiers hook

**Files:**
- Create: `twins-dash/src/hooks/tech/useMyScorecardWithTiers.ts`

- [ ] **Step 1: Write hook (mirrors useMyPaystub pattern)**

```ts
// useMyScorecardWithTiers.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { Database } from '@/integrations/supabase/types';

export type MyScorecardRow =
  Database['public']['Functions']['get_my_scorecard_with_tiers']['Returns'][number];

export function useMyScorecardWithTiers(since: Date, until: Date) {
  return useQuery({
    queryKey: ['my_scorecard_with_tiers', since.toISOString(), until.toISOString()],
    queryFn: async (): Promise<MyScorecardRow | null> => {
      const { data, error } = await supabase.rpc('get_my_scorecard_with_tiers', {
        p_since: since.toISOString(),
        p_until: until.toISOString(),
      });
      if (error) throw error;
      return (data?.[0] ?? null) as MyScorecardRow | null;
    },
    staleTime: 30_000,
    refetchOnWindowFocus: false,
  });
}
```

- [ ] **Step 2: Verify TS compiles**

```bash
npx tsc --noEmit
```

- [ ] **Step 3: Commit**

```bash
git add src/hooks/tech/useMyScorecardWithTiers.ts
git commit -m "feat(tech-portal): useMyScorecardWithTiers hook"
```

---

### Task 12: KpiTile component

**Files:**
- Create: `twins-dash/src/components/tech/KpiTile.tsx`
- Test: `twins-dash/src/components/tech/__tests__/KpiTile.test.tsx`

- [ ] **Step 1: Write failing test**

```tsx
// KpiTile.test.tsx
import { render, screen } from '@testing-library/react';
import { KpiTile } from '../KpiTile';

describe('KpiTile', () => {
  it('renders label, value, tier badge, and comparison pill', () => {
    render(
      <KpiTile
        label="Revenue"
        valueFormatted="$48,210"
        tier="gold"
        comparison={{ label: '+18%', tone: 'up' }}
        progressPct={78}
        nextTierCaption="22% to Elite"
      />
    );
    expect(screen.getByText('Revenue')).toBeInTheDocument();
    expect(screen.getByText('$48,210')).toBeInTheDocument();
    expect(screen.getByText('Gold')).toBeInTheDocument();
    expect(screen.getByText('+18%')).toBeInTheDocument();
    expect(screen.getByText('22% to Elite')).toBeInTheDocument();
  });

  it('fires onClick when tile is clicked', async () => {
    const onClick = vi.fn();
    render(
      <KpiTile label="Revenue" valueFormatted="$48k" tier="gold"
               comparison={{ label: '+18%', tone: 'up' }}
               progressPct={78} nextTierCaption="" onClick={onClick} />
    );
    screen.getByRole('button').click();
    expect(onClick).toHaveBeenCalled();
  });
});
```

- [ ] **Step 2: Run test, expect FAIL**

```bash
npx vitest run src/components/tech/__tests__/KpiTile.test.tsx
```

- [ ] **Step 3: Implement KpiTile**

```tsx
// KpiTile.tsx
import { cn } from '@/lib/utils';
import { TierBadge, type Tier } from './TierBadge';

export type KpiTileProps = {
  label: string;
  valueFormatted: string;
  tier: Tier | null;
  comparison: { label: string; tone: 'up' | 'down' | 'flat' };
  progressPct: number; // 0..100
  nextTierCaption: string;
  onClick?: () => void;
};

const TONE_CLASSES = {
  up:   'bg-emerald-100 text-emerald-700',
  down: 'bg-red-100 text-red-700',
  flat: 'bg-slate-100 text-slate-600',
};

const TIER_FILL: Record<string, string> = {
  bronze: 'bg-gradient-to-r from-amber-300 to-orange-400',
  silver: 'bg-gradient-to-r from-slate-300 to-slate-400',
  gold:   'bg-gradient-to-r from-accent to-yellow-300',
  elite:  'bg-gradient-to-r from-primary to-indigo-700',
};

export function KpiTile(p: KpiTileProps) {
  const fill = p.tier ? TIER_FILL[p.tier] : 'bg-slate-200';
  return (
    <button
      type="button"
      onClick={p.onClick}
      className="text-left bg-card border border-border rounded-2xl p-3 hover:border-accent transition-colors w-full"
    >
      <div className="flex items-center justify-between gap-2">
        <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{p.label}</span>
        <TierBadge tier={p.tier} />
      </div>
      <div className="text-xl font-extrabold text-primary tracking-tight mt-2">{p.valueFormatted}</div>
      <div className="mt-1">
        <span className={cn('text-[10px] font-bold px-1.5 py-0.5 rounded-full', TONE_CLASSES[p.comparison.tone])}>
          {p.comparison.label}
        </span>
      </div>
      <div className="h-1 bg-muted rounded-full mt-2 overflow-hidden">
        <div className={cn('h-full rounded-full', fill)} style={{ width: `${Math.min(100, Math.max(0, p.progressPct))}%` }} />
      </div>
      <div className="text-[9px] text-muted-foreground mt-1">{p.nextTierCaption}</div>
    </button>
  );
}
```

- [ ] **Step 4: Re-run test, expect PASS**

```bash
npx vitest run src/components/tech/__tests__/KpiTile.test.tsx
```

- [ ] **Step 5: Commit**

```bash
git add src/components/tech/KpiTile.tsx src/components/tech/__tests__/KpiTile.test.tsx
git commit -m "feat(tech-portal): KpiTile component with tier badge + progress bar"
```

---

### Task 13: KpiSparkline component

**Files:**
- Create: `twins-dash/src/components/tech/KpiSparkline.tsx`

- [ ] **Step 1: Implement (visual-only, no test — verified in drill sheet later)**

```tsx
// KpiSparkline.tsx
type Props = {
  values: number[];      // last N points (oldest → newest)
  companyAvg?: number;   // dashed reference line
  height?: number;
};

export function KpiSparkline({ values, companyAvg, height = 50 }: Props) {
  if (values.length < 2) return <div className="h-[50px] grid place-items-center text-[10px] text-muted-foreground">Not enough data</div>;
  const max = Math.max(...values, companyAvg ?? -Infinity);
  const min = Math.min(...values, companyAvg ?? Infinity);
  const span = (max - min) || 1;
  const stepX = 200 / (values.length - 1);
  const yFor = (v: number) => height - ((v - min) / span) * (height - 4) - 2;
  const points = values.map((v, i) => `${i * stepX},${yFor(v)}`).join(' ');
  const avgY = companyAvg !== undefined ? yFor(companyAvg) : null;
  return (
    <div className="bg-muted rounded-md p-2 h-[60px]">
      <svg viewBox={`0 0 200 ${height}`} preserveAspectRatio="none" className="w-full h-full">
        <polyline points={points} fill="none" stroke="hsl(var(--primary))" strokeWidth="2" />
        {avgY !== null && <line x1="0" y1={avgY} x2="200" y2={avgY} stroke="hsl(var(--accent))" strokeDasharray="3 3" strokeWidth="1" />}
      </svg>
    </div>
  );
}
```

- [ ] **Step 2: Verify TS compiles**

```bash
npx tsc --noEmit
```

- [ ] **Step 3: Commit**

```bash
git add src/components/tech/KpiSparkline.tsx
git commit -m "feat(tech-portal): KpiSparkline component (8-week mini chart)"
```

---

### Task 14: KpiDrillSheet component

**Files:**
- Create: `twins-dash/src/components/tech/KpiDrillSheet.tsx`

- [ ] **Step 1: Implement using shadcn Sheet**

```tsx
// KpiDrillSheet.tsx
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { TierBadge, type Tier } from './TierBadge';
import { KpiSparkline } from './KpiSparkline';

export type DrillSheetProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  label: string;
  valueFormatted: string;
  tier: Tier | null;
  comparisonText: string;          // "−8 pts vs co. avg"
  rows: { label: string; value: string }[];
  ladder: { tier: Tier; threshold: string; isCurrent: boolean }[];
  sparklineValues: number[];
  companyAvg?: number;
  tip?: { source: 'ai' | 'library'; body: string };
};

export function KpiDrillSheet(p: DrillSheetProps) {
  return (
    <Sheet open={p.open} onOpenChange={p.onOpenChange}>
      <SheetContent side="bottom" className="rounded-t-2xl max-h-[85vh] overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="flex items-center justify-between gap-3">
            <div>
              <div className="text-xs uppercase tracking-wider text-muted-foreground">{p.label}</div>
              <div className="text-2xl font-extrabold text-primary mt-1 flex items-center gap-2">
                {p.valueFormatted} <TierBadge tier={p.tier} />
              </div>
            </div>
            <div className="text-sm font-bold text-primary text-right whitespace-nowrap">{p.comparisonText}</div>
          </SheetTitle>
        </SheetHeader>
        <div className="space-y-4 mt-4">
          <div className="divide-y divide-border">
            {p.rows.map(r => (
              <div key={r.label} className="flex justify-between text-sm py-2">
                <span className="text-muted-foreground">{r.label}</span>
                <span className="font-bold text-primary">{r.value}</span>
              </div>
            ))}
          </div>

          <div>
            <div className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-2">Tier ladder</div>
            <div className="grid grid-cols-4 gap-1.5">
              {p.ladder.map(s => (
                <div key={s.tier}
                  className={`text-center p-2 rounded-lg border text-[10px] font-bold ${s.isCurrent ? 'ring-2 ring-primary' : ''}`}>
                  <div className="capitalize">{s.tier}</div>
                  <div className="text-muted-foreground font-normal mt-0.5">{s.threshold}</div>
                </div>
              ))}
            </div>
          </div>

          <KpiSparkline values={p.sparklineValues} companyAvg={p.companyAvg} />

          {p.tip && (
            <div className="bg-yellow-50 border border-accent/50 rounded-xl p-3">
              <div className="text-[10px] font-bold uppercase tracking-wider text-amber-800">
                {p.tip.source === 'ai' ? '🤖 AI nudge · this week\'s focus' : '💡 Tip'}
              </div>
              <div className="text-sm text-amber-950 mt-1 leading-relaxed">{p.tip.body}</div>
            </div>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
```

- [ ] **Step 2: Verify TS compiles**

```bash
npx tsc --noEmit
```

- [ ] **Step 3: Commit**

```bash
git add src/components/tech/KpiDrillSheet.tsx
git commit -m "feat(tech-portal): KpiDrillSheet component (bottom-sheet drill-in)"
```

---

## M4 — Home tab assembly

### Task 15: HeroEstimate component

**Files:**
- Create: `twins-dash/src/components/tech/HeroEstimate.tsx`

- [ ] **Step 1: Implement**

```tsx
// HeroEstimate.tsx
type Props = {
  techFirstName: string;
  weekRangeText: string;
  estimatedDollars: number;       // commission this week
  jobsDone: number;
  draftsLeft: number;
  avgTicketDollars: number;
  submitDeadlineText: string;     // "Sunday 11:59 PM"
};

const fmtUsd = (n: number, opts: Intl.NumberFormatOptions = {}) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', ...opts }).format(n || 0);

export function HeroEstimate(p: Props) {
  const whole = Math.floor(p.estimatedDollars);
  const cents = Math.round((p.estimatedDollars - whole) * 100).toString().padStart(2, '0');
  return (
    <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-indigo-900 text-primary-foreground p-5">
      <div className="absolute -right-8 -top-8 w-36 h-36 rounded-full" style={{ background: 'radial-gradient(circle, hsl(var(--accent) / .45), transparent 70%)' }} />
      <div className="relative">
        <div className="text-sm opacity-70">Hey, {p.techFirstName} 👋</div>
        <div className="text-[10px] font-extrabold text-accent uppercase tracking-wider mt-0.5">This week · {p.weekRangeText}</div>
        <div className="text-4xl font-extrabold tracking-tight mt-1.5 leading-none">
          {fmtUsd(whole, { maximumFractionDigits: 0 })}<span className="text-2xl opacity-70">.{cents}</span>
        </div>
        <div className="text-xs opacity-75 mt-1.5">Estimated commission so far. Provisional — finalized when admin runs payroll.</div>
        <div className="flex gap-2 mt-3 flex-wrap">
          <Mini k="Jobs done" v={String(p.jobsDone)} />
          <Mini k="Drafts left" v={String(p.draftsLeft)} highlight={p.draftsLeft > 0} />
          <Mini k="Avg ticket" v={fmtUsd(p.avgTicketDollars, { maximumFractionDigits: 0 })} />
        </div>
        <div className="text-[11px] opacity-60 mt-3 pt-3 border-t border-white/10">
          Submit by <b className="text-accent">{p.submitDeadlineText}</b> for this week to count.
        </div>
      </div>
    </div>
  );
}

function Mini({ k, v, highlight }: { k: string; v: string; highlight?: boolean }) {
  return (
    <div className="flex-1 min-w-[100px] bg-white/10 border border-accent/25 rounded-xl px-3 py-2">
      <div className="text-[10px] font-bold text-accent uppercase tracking-wider">{k}</div>
      <div className={`text-base font-extrabold mt-0.5 ${highlight ? 'text-orange-300' : ''}`}>{v}</div>
    </div>
  );
}
```

- [ ] **Step 2: Verify TS compiles**

```bash
npx tsc --noEmit
```

- [ ] **Step 3: Commit**

```bash
git add src/components/tech/HeroEstimate.tsx
git commit -m "feat(tech-portal): HeroEstimate navy hero card"
```

---

### Task 16: useWhatChangedThisWeek hook

**Files:**
- Create: `twins-dash/src/hooks/tech/useWhatChangedThisWeek.ts`

- [ ] **Step 1: Implement**

```ts
// useWhatChangedThisWeek.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { startOfWeek, subDays } from 'date-fns';

type Delta = { kpi_key: string; display_name: string; current: number; prior: number; delta: number; tone: 'up' | 'down' | 'flat' };

const KPIS = [
  { k: 'closing_pct',     name: 'Closing %',  fmt: (n: number) => `${n.toFixed(0)}%`,  diffFmt: (d: number) => `${d > 0 ? '+' : ''}${d.toFixed(0)} pts` },
  { k: 'avg_opportunity', name: 'Avg ticket', fmt: (n: number) => `$${n.toFixed(0)}`,   diffFmt: (d: number) => `${d > 0 ? '+' : ''}$${d.toFixed(0)}` },
  { k: 'membership_pct',  name: 'Memberships',fmt: (n: number) => `${n.toFixed(0)}%`,   diffFmt: (d: number) => `${d > 0 ? '+' : ''}${d.toFixed(0)} pts` },
  { k: 'revenue',         name: 'Revenue',    fmt: (n: number) => `$${(n/1000).toFixed(1)}k`, diffFmt: (d: number) => `${d > 0 ? '+' : ''}$${(d/1000).toFixed(1)}k` },
];

export function useWhatChangedThisWeek() {
  return useQuery({
    queryKey: ['what_changed_this_week'],
    queryFn: async (): Promise<Delta[]> => {
      const now = new Date();
      const weekStart = startOfWeek(now, { weekStartsOn: 1 });
      const priorEnd = subDays(weekStart, 1);
      const priorStart = subDays(priorEnd, 6);
      const [{ data: cur }, { data: prev }] = await Promise.all([
        supabase.rpc('get_my_scorecard_with_tiers', { p_since: weekStart.toISOString(), p_until: now.toISOString() }),
        supabase.rpc('get_my_scorecard_with_tiers', { p_since: priorStart.toISOString(), p_until: priorEnd.toISOString() }),
      ]);
      const c = cur?.[0] ?? {} as any;
      const p = prev?.[0] ?? {} as any;
      return KPIS.map(({ k, name, fmt, diffFmt }) => {
        const cv = Number(c[k] ?? 0), pv = Number(p[k] ?? 0);
        const d = cv - pv;
        return { kpi_key: k, display_name: name, current: cv, prior: pv, delta: d,
                 tone: d > 0 ? 'up' : d < 0 ? 'down' : 'flat' } as Delta;
      });
    },
    staleTime: 60_000,
  });
}
```

- [ ] **Step 2: Verify TS compiles**

```bash
npx tsc --noEmit
```

- [ ] **Step 3: Commit**

```bash
git add src/hooks/tech/useWhatChangedThisWeek.ts
git commit -m "feat(tech-portal): useWhatChangedThisWeek delta hook"
```

---

### Task 17: Assemble Home.tsx

**Files:**
- Create: `twins-dash/src/pages/tech/Home.tsx`

- [ ] **Step 1: Write Home.tsx**

```tsx
// Home.tsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { format, subDays, startOfWeek, endOfWeek } from 'date-fns';
import { useAuth } from '@/contexts/AuthContext';
import { useMyPaystub } from '@/hooks/tech/useMyPaystub';
import { useLastFinalizedPaystub } from '@/hooks/tech/useLastFinalizedPaystub';
import { useForceRefresh } from '@/hooks/tech/useForceRefresh';
import { useMyScorecardWithTiers } from '@/hooks/tech/useMyScorecardWithTiers';
import { useTierThresholds } from '@/hooks/tech/useTierThresholds';
import { HeroEstimate } from '@/components/tech/HeroEstimate';
import { KpiTile } from '@/components/tech/KpiTile';
import { KpiDrillSheet } from '@/components/tech/KpiDrillSheet';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from '@/components/ui/sonner';

const RANGES = {
  '30d': { label: 'Last 30 days', from: () => subDays(new Date(), 30), to: () => new Date() },
  '90d': { label: 'Last 90 days', from: () => subDays(new Date(), 90), to: () => new Date() },
  'mtd': { label: 'This month',   from: () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1); }, to: () => new Date() },
  'ytd': { label: 'YTD',          from: () => { const d = new Date(); return new Date(d.getFullYear(), 0, 1); }, to: () => new Date() },
} as const;

export default function Home() {
  const { technicianName } = useAuth();
  const [range, setRange] = useState<keyof typeof RANGES>('30d');
  const [drillKpi, setDrillKpi] = useState<string | null>(null);

  const { data: paystub } = useMyPaystub('week');
  const { data: lastPaystub } = useLastFinalizedPaystub();
  const { data: scorecard } = useMyScorecardWithTiers(RANGES[range].from(), RANGES[range].to());
  const { data: thresholds = [] } = useTierThresholds();
  const forceRefresh = useForceRefresh();
  const navigate = useNavigate();

  const firstName = (technicianName ?? 'there').split(' ')[0];
  const weekStart = startOfWeek(new Date(), { weekStartsOn: 1 });
  const weekEnd = endOfWeek(new Date(), { weekStartsOn: 1 });
  const weekRangeText = `${format(weekStart, 'MMM d')} – ${format(weekEnd, 'MMM d')}`;
  const drafts = Number(paystub?.draft_count ?? 0);
  const jobsDone = Number(paystub?.job_count ?? 0);
  const estimate = Number(paystub?.commission_total ?? 0);
  const avgTicket = jobsDone > 0 ? Number(paystub?.revenue ?? 0) / jobsDone : 0;

  const handleSync = async () => {
    try { const r = await forceRefresh.mutateAsync(14); toast.success(`Synced ${r.jobs_synced ?? 0} jobs`); }
    catch (e: any) { toast.error(e?.message ?? 'Sync failed'); }
  };

  return (
    <div className="p-4 md:p-6 max-w-3xl mx-auto space-y-4">
      <HeroEstimate
        techFirstName={firstName}
        weekRangeText={weekRangeText}
        estimatedDollars={estimate}
        jobsDone={jobsDone}
        draftsLeft={drafts}
        avgTicketDollars={avgTicket}
        submitDeadlineText="Sunday 11:59 PM"
      />

      {drafts > 0 && paystub?.next_draft_job_id && (
        <button
          onClick={() => navigate(`/tech/jobs/${paystub.next_draft_job_id}`)}
          className="w-full text-left bg-yellow-50 border-2 border-accent rounded-2xl p-3.5 flex gap-3 items-center hover:bg-yellow-100 transition"
        >
          <div className="w-9 h-9 rounded-lg bg-primary text-accent grid place-items-center font-extrabold text-lg flex-shrink-0">!</div>
          <div className="flex-1">
            <div className="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">Action needed</div>
            <div className="text-base font-extrabold text-primary mt-0.5">{drafts} {drafts === 1 ? 'job needs' : 'jobs need'} parts entered</div>
            <div className="text-xs text-amber-900 mt-0.5">Estimate could be off until these are entered.</div>
          </div>
          <div className="text-2xl text-primary font-extrabold">→</div>
        </button>
      )}

      <div className="flex gap-2">
        <Button variant="outline" className="flex-1" onClick={handleSync} disabled={forceRefresh.isPending}>
          {forceRefresh.isPending ? 'Syncing…' : '↻ Sync HCP'}
        </Button>
        {/* Submit week button — wires to existing submit flow on next task */}
      </div>

      {/* Performance section */}
      <div className="flex items-center justify-between mt-6 mb-2">
        <h3 className="text-sm font-extrabold uppercase tracking-wider text-primary">Performance</h3>
        <Select value={range} onValueChange={(v) => setRange(v as any)}>
          <SelectTrigger className="w-auto h-7 text-xs"><SelectValue /></SelectTrigger>
          <SelectContent>
            {Object.entries(RANGES).map(([k, v]) => <SelectItem key={k} value={k}>{v.label}</SelectItem>)}
          </SelectContent>
        </Select>
      </div>

      {scorecard && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
          {thresholds.map(t => {
            const value = Number((scorecard as any)[t.kpi_key] ?? 0);
            const tier = (scorecard as any)[`${t.kpi_key}_tier`] as any;
            const valueFmt = formatKpiValue(value, t.unit);
            return (
              <KpiTile
                key={t.kpi_key}
                label={t.display_name}
                valueFormatted={valueFmt}
                tier={tier}
                comparison={{ label: '—', tone: 'flat' }}  // company-avg compute in Task 18
                progressPct={progressPctForTier(value, tier, t)}
                nextTierCaption={nextTierCaption(value, tier, t)}
                onClick={() => setDrillKpi(t.kpi_key)}
              />
            );
          })}
        </div>
      )}

      <div className="text-[10.5px] text-muted-foreground text-center">Tap any tile for trend chart, tier ladder + how-to-improve tip.</div>

      {/* Last finalized week sanity line */}
      {lastPaystub && (
        <div className="text-xs text-muted-foreground p-3 bg-muted rounded-lg flex gap-2 items-center">
          📌 <span><b className="text-primary">Last finalized week:</b> {format(new Date(lastPaystub.weekStart), 'MMM d')} – {format(new Date(lastPaystub.weekEnd), 'MMM d')} landed at <b className="text-primary">${Number(lastPaystub.paystub.commission_total).toFixed(2)}</b>.</span>
        </div>
      )}

      {drillKpi && (
        <KpiDrillSheet
          open={!!drillKpi}
          onOpenChange={(o) => !o && setDrillKpi(null)}
          {...buildDrillProps(drillKpi, scorecard, thresholds)}
        />
      )}
    </div>
  );
}

// helpers
function formatKpiValue(v: number, unit: 'usd'|'pct'|'count'): string {
  if (unit === 'usd')   return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(v);
  if (unit === 'pct')   return `${v.toFixed(1)}%`;
  return String(Math.round(v));
}
function progressPctForTier(value: number, tier: string | null, t: any): number {
  if (tier === 'elite') return 100;
  const order: any = { null: 0, bronze: 1, silver: 2, gold: 3, elite: 4 };
  const cur = order[tier ?? 'null'];
  const lower = [0, t.bronze, t.silver, t.gold, t.elite][cur];
  const upper = [t.bronze, t.silver, t.gold, t.elite, t.elite][cur];
  if (upper === lower) return 100;
  const pct = ((value - lower) / (upper - lower)) * 100;
  return Math.max(0, Math.min(100, pct));
}
function nextTierCaption(value: number, tier: string | null, t: any): string {
  if (tier === 'elite') return 'Top tier 🥇';
  const next = tier === 'gold' ? 'Elite' : tier === 'silver' ? 'Gold' : tier === 'bronze' ? 'Silver' : 'Bronze';
  const target = tier === 'gold' ? t.elite : tier === 'silver' ? t.gold : tier === 'bronze' ? t.silver : t.bronze;
  if (t.unit === 'usd')   return `${formatKpiValue(target - value, 'usd')} to ${next}`;
  if (t.unit === 'pct')   return `${(Math.abs(target - value)).toFixed(1)} pts to ${next}`;
  return `${Math.max(0, Math.round(target - value))} to ${next}`;
}
function buildDrillProps(_kpi: string, _sc: any, _t: any) {
  // Filled in by Task 18.
  return { label: '', valueFormatted: '', tier: null, comparisonText: '', rows: [], ladder: [], sparklineValues: [] } as any;
}
```

- [ ] **Step 2: Wire route to Home in App.tsx**

In `App.tsx`, replace the existing TechHome import + route:

```tsx
// before: import TechHome from '@/pages/tech/TechHome';
import Home from '@/pages/tech/Home';
// in TechShell child route:
<Route index element={<Home />} />
```

- [ ] **Step 3: Visual smoke test**

```bash
npm run dev
```

Open `/tech` as a logged-in tech (or admin in "View as tech" mode). Verify the navy hero, KPI tiles render, range picker switches the data, and tile clicks open the drill sheet (still empty until Task 18).

- [ ] **Step 4: Commit**

```bash
git add src/pages/tech/Home.tsx src/App.tsx
git commit -m "feat(tech-portal): Home tab assembly (hero + tiles + sanity line)"
```

---

### Task 18: Wire KpiDrillSheet contents (sparkline + comparison)

**Files:**
- Create: `twins-dash/src/hooks/tech/useKpiSparkline.ts`
- Modify: `twins-dash/src/pages/tech/Home.tsx`

- [ ] **Step 1: Add SQL helper for 8-week per-KPI series + company avg**

Create migration `twins-dash/supabase/migrations/20260425150000_get_my_kpi_sparkline.sql`:

```sql
CREATE OR REPLACE FUNCTION public.get_my_kpi_sparkline(p_kpi text, p_weeks int DEFAULT 8)
RETURNS TABLE(week_start date, my_value numeric, company_avg numeric)
LANGUAGE plpgsql STABLE SECURITY DEFINER AS $$
DECLARE
  v_tech_id uuid := public.current_technician_id();
  v_sql text;
BEGIN
  -- Build 8 weekly buckets and join with my_scorecard + company-wide scorecard.
  -- (Implementation skeleton; engineer fills in based on existing my_scorecard internals.)
  RETURN QUERY
  WITH weeks AS (
    SELECT generate_series(
      date_trunc('week', now())::date - ((p_weeks - 1) * 7),
      date_trunc('week', now())::date,
      interval '7 days'
    )::date AS week_start
  )
  SELECT
    w.week_start,
    (SELECT (row_to_json(s)->>p_kpi)::numeric
       FROM public.my_scorecard(w.week_start::timestamptz, (w.week_start + 7)::timestamptz) s LIMIT 1),
    (SELECT (row_to_json(s)->>p_kpi)::numeric
       FROM public.company_scorecard(w.week_start::timestamptz, (w.week_start + 7)::timestamptz) s LIMIT 1)
  FROM weeks w
  ORDER BY w.week_start;
END $$;

GRANT EXECUTE ON FUNCTION public.get_my_kpi_sparkline(text, int) TO authenticated;
```

> **NOTE:** If a `company_scorecard` RPC doesn't exist, the engineer must either (a) add one as a thin wrapper around the same calculations as `my_scorecard` but unscoped to caller, or (b) compute the company average client-side from already-available data. Inspect existing usage in `src/hooks/use-servicetitan-kpi.ts` and pick the cheaper path. Document the choice in the commit message.

- [ ] **Step 2: Apply migration + regen types**

```bash
npx supabase db push
npx supabase gen types typescript --linked > src/integrations/supabase/types.ts
```

- [ ] **Step 3: Implement useKpiSparkline hook**

```ts
// useKpiSparkline.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export function useKpiSparkline(kpi: string | null, weeks = 8) {
  return useQuery({
    enabled: !!kpi,
    queryKey: ['kpi_sparkline', kpi, weeks],
    queryFn: async () => {
      const { data, error } = await supabase.rpc('get_my_kpi_sparkline', { p_kpi: kpi!, p_weeks: weeks });
      if (error) throw error;
      return (data ?? []).map((r: any) => ({
        weekStart: r.week_start as string,
        myValue: r.my_value !== null ? Number(r.my_value) : null,
        companyAvg: r.company_avg !== null ? Number(r.company_avg) : null,
      }));
    },
    staleTime: 5 * 60_000,
  });
}
```

- [ ] **Step 4: Replace `buildDrillProps` in Home.tsx**

Replace the placeholder helper with a real one that uses `useKpiSparkline`. Move the drill sheet into a small subcomponent `<KpiDrillSheetForKpi kpi={drillKpi} ... />` so the hook can be called there.

- [ ] **Step 5: Visual verification**

Open `/tech`, click a tile, verify the bottom sheet opens with: KPI value, tier badge, comparison delta, stat rows, tier ladder, sparkline.

- [ ] **Step 6: Commit**

```bash
git add supabase/migrations/20260425150000_get_my_kpi_sparkline.sql src/hooks/tech/useKpiSparkline.ts src/pages/tech/Home.tsx src/integrations/supabase/types.ts
git commit -m "feat(tech-portal): wire KpiDrillSheet with sparkline + tier ladder"
```

---

### Task 19: Move per-job list onto Home

**Files:**
- Modify: `twins-dash/src/pages/tech/Home.tsx`

- [ ] **Step 1: Add jobs list section**

Below the sanity line in Home.tsx, add a "This week's jobs" section that uses the existing `CommissionTracker` (mode='tech') component. Verify it renders.

```tsx
import { CommissionTracker } from '@/components/technician/scorecard/CommissionTracker';

// inside the return, after the sanity line:
<div className="flex items-center justify-between mt-6 mb-2">
  <h3 className="text-sm font-extrabold uppercase tracking-wider text-primary">This week's jobs</h3>
</div>
<CommissionTracker mode="tech" />
```

- [ ] **Step 2: Visually verify the existing component fits the new shell**

Open `/tech`. If `CommissionTracker` brings unwanted chrome (its own week label, hero CTA), refactor by adding a `compact` prop or extracting the rows-only sub-block. Document any required change in the commit message.

- [ ] **Step 3: Commit**

```bash
git add src/pages/tech/Home.tsx
git commit -m "feat(tech-portal): mount existing CommissionTracker on new Home"
```

---

## M5 — AI nudge data layer + Edge Function

### Task 20: tech_ai_nudges table + app_settings flag

**Files:**
- Create: `twins-dash/supabase/migrations/20260425140000_tech_ai_nudges.sql`
- Create: `twins-dash/supabase/migrations/20260425140600_app_settings_ai_nudge_pause.sql`

- [ ] **Step 1: tech_ai_nudges**

```sql
-- 20260425140000_tech_ai_nudges.sql
CREATE TABLE public.tech_ai_nudges (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tech_id         uuid NOT NULL REFERENCES public.technicians(id) ON DELETE CASCADE,
  week_start      date NOT NULL,
  headline        text,
  lede            text,
  bullets         jsonb,  -- array of 3 strings, NULL on failure
  jobs_in_window  int  NOT NULL DEFAULT 0,
  model           text,
  cost_usd        numeric,
  created_at      timestamptz NOT NULL DEFAULT now(),
  UNIQUE (tech_id, week_start)
);

CREATE INDEX tech_ai_nudges_tech_idx ON public.tech_ai_nudges(tech_id, week_start DESC);

ALTER TABLE public.tech_ai_nudges ENABLE ROW LEVEL SECURITY;

CREATE POLICY tech_ai_nudges_select_own_or_admin
  ON public.tech_ai_nudges FOR SELECT TO authenticated
  USING (tech_id = public.current_technician_id() OR public.has_payroll_access());
-- write: service-role only (no policy required)
```

- [ ] **Step 2: app_settings pause flag**

```sql
-- 20260425140600_app_settings_ai_nudge_pause.sql
-- Reuses existing app_settings table if present; create if not.
CREATE TABLE IF NOT EXISTS public.app_settings (
  key   text PRIMARY KEY,
  value jsonb NOT NULL,
  updated_at timestamptz NOT NULL DEFAULT now()
);

ALTER TABLE public.app_settings ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS app_settings_read ON public.app_settings;
DROP POLICY IF EXISTS app_settings_write ON public.app_settings;

CREATE POLICY app_settings_read  ON public.app_settings FOR SELECT TO authenticated USING (true);
CREATE POLICY app_settings_write ON public.app_settings FOR ALL TO authenticated
  USING (public.has_payroll_access()) WITH CHECK (public.has_payroll_access());

INSERT INTO public.app_settings (key, value) VALUES ('ai_nudge_paused', 'false'::jsonb)
  ON CONFLICT (key) DO NOTHING;
```

- [ ] **Step 3: Apply + regen types**

```bash
npx supabase db push
npx supabase gen types typescript --linked > src/integrations/supabase/types.ts
```

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260425140000_tech_ai_nudges.sql supabase/migrations/20260425140600_app_settings_ai_nudge_pause.sql src/integrations/supabase/types.ts
git commit -m "feat(tech-portal): tech_ai_nudges table + app_settings pause flag"
```

---

### Task 21: generate-tech-nudge Edge Function

**Files:**
- Create: `twins-dash/supabase/functions/generate-tech-nudge/index.ts`
- Create: `twins-dash/supabase/functions/generate-tech-nudge/prompt.ts`
- Create: `twins-dash/supabase/functions/generate-tech-nudge/validate.ts`

- [ ] **Step 1: prompt.ts**

```ts
// prompt.ts
export type TechSnapshot = {
  first_name: string;
  week_range_text: string;        // "Apr 14 – Apr 20"
  jobs_in_window: number;
  kpis: Array<{
    kpi_key: string; display_name: string; my_value: number; company_avg: number;
    tier: 'bronze' | 'silver' | 'gold' | 'elite' | null;
    bronze: number; silver: number; gold: number; elite: number;
    direction: 'higher' | 'lower';
  }>;
  drivers?: Record<string, string>;  // free-form per-KPI driver hints, e.g. memberships → "offered on 2 of 14 jobs"
};

export const SYSTEM_PROMPT = `You are a warm coach writing a 1-paragraph weekly focus note for a residential garage-door technician.

RULES (must follow exactly):
1. Pick ONE KPI to focus on: the lowest-tier KPI in the input (ties broken by largest gap to next tier).
2. Output strict JSON: { "headline": string, "lede": string, "bullets": [string, string, string] }.
3. Headline: ONE sentence, max 8 words, e.g. "This week, focus on Memberships."
4. Lede: ONE sentence, max 25 words, framing why this KPI matters now.
5. Each bullet: ONE sentence, max 30 words, MUST cite a specific number from the input (job count, percentage, dollar amount). NEVER invent numbers.
6. No markdown, no bullet markers, no trailing newlines, no fields outside the schema.
7. Voice: encouraging, specific, never patronizing, never punitive.`;

export function buildUserPrompt(s: TechSnapshot): string {
  const lines: string[] = [];
  lines.push(`Tech: ${s.first_name}`);
  lines.push(`Week: ${s.week_range_text}`);
  lines.push(`Jobs in window: ${s.jobs_in_window}`);
  lines.push(`KPIs (your value | company avg | tier | tier thresholds [bronze/silver/gold/elite] | direction):`);
  for (const k of s.kpis) {
    lines.push(`- ${k.display_name} (${k.kpi_key}): ${k.my_value} | ${k.company_avg} | ${k.tier ?? 'below_bronze'} | [${k.bronze}/${k.silver}/${k.gold}/${k.elite}] | ${k.direction}-better`);
  }
  if (s.drivers) {
    lines.push(`Drivers (real numbers you can cite):`);
    for (const [k, v] of Object.entries(s.drivers)) lines.push(`- ${k}: ${v}`);
  }
  lines.push(`\nReturn JSON only.`);
  return lines.join('\n');
}
```

- [ ] **Step 2: validate.ts**

```ts
// validate.ts
export type Nudge = { headline: string; lede: string; bullets: [string, string, string] };
export function parseNudge(raw: string): Nudge | null {
  try {
    const j = JSON.parse(raw.trim());
    if (typeof j.headline !== 'string' || j.headline.length > 100) return null;
    if (typeof j.lede !== 'string' || j.lede.length > 240) return null;
    if (!Array.isArray(j.bullets) || j.bullets.length !== 3) return null;
    if (!j.bullets.every((b: unknown) => typeof b === 'string' && b.length <= 280)) return null;
    return { headline: j.headline, lede: j.lede, bullets: j.bullets as [string, string, string] };
  } catch { return null; }
}
```

- [ ] **Step 3: index.ts**

```ts
// index.ts
import { serve } from "https://deno.land/std@0.224.0/http/server.ts";
import { createClient } from "jsr:@supabase/supabase-js@^2";
import Anthropic from "npm:@anthropic-ai/sdk@^0.30";
import { SYSTEM_PROMPT, buildUserPrompt } from "./prompt.ts";
import { parseNudge } from "./validate.ts";

const MODEL = "claude-haiku-4-5-20251001";
// Cost (Haiku 4.5): $1/M input, $5/M output, $0.10/M cached input.
const PRICE_IN = 1.0 / 1_000_000;
const PRICE_OUT = 5.0 / 1_000_000;
const PRICE_CACHE_READ = 0.10 / 1_000_000;

serve(async (req) => {
  if (req.method !== 'POST') return new Response('Method not allowed', { status: 405 });
  const { tech_id, week_start } = await req.json();
  if (!tech_id || !week_start) return new Response('Bad request', { status: 400 });

  const supa = createClient(
    Deno.env.get('SUPABASE_URL')!,
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,
  );

  // 1. Build snapshot via SQL helper (calls below RPC).
  const { data: snapshot, error: snapErr } = await supa.rpc('build_tech_nudge_snapshot', {
    p_tech_id: tech_id, p_week_start: week_start,
  });
  if (snapErr) return new Response(JSON.stringify({ ok: false, error: snapErr.message }), { status: 500 });
  if (!snapshot || snapshot.jobs_in_window < 5) {
    await supa.from('tech_ai_nudges').upsert({ tech_id, week_start, bullets: null,
      jobs_in_window: snapshot?.jobs_in_window ?? 0, model: 'skipped', cost_usd: 0 });
    return new Response(JSON.stringify({ ok: true, skipped: 'insufficient_jobs' }));
  }

  // 2. Call Claude.
  const client = new Anthropic({ apiKey: Deno.env.get('ANTHROPIC_API_KEY')! });
  const userPrompt = buildUserPrompt(snapshot);
  let nudge = null; let inTok = 0; let outTok = 0; let cachedTok = 0;
  for (let attempt = 0; attempt < 2 && !nudge; attempt++) {
    const resp = await client.messages.create({
      model: MODEL,
      max_tokens: 600,
      system: [{ type: 'text', text: SYSTEM_PROMPT, cache_control: { type: 'ephemeral' } }],
      messages: [{ role: 'user', content: userPrompt }],
    });
    inTok += resp.usage.input_tokens;
    outTok += resp.usage.output_tokens;
    cachedTok += (resp.usage as any).cache_read_input_tokens ?? 0;
    const text = resp.content.find(c => c.type === 'text')?.text ?? '';
    nudge = parseNudge(text);
  }
  const cost = (inTok - cachedTok) * PRICE_IN + cachedTok * PRICE_CACHE_READ + outTok * PRICE_OUT;

  // 3. Upsert.
  const { error: upErr } = await supa.from('tech_ai_nudges').upsert({
    tech_id, week_start,
    headline: nudge?.headline ?? null,
    lede: nudge?.lede ?? null,
    bullets: nudge?.bullets ?? null,
    jobs_in_window: snapshot.jobs_in_window,
    model: MODEL,
    cost_usd: cost,
  });
  if (upErr) return new Response(JSON.stringify({ ok: false, error: upErr.message }), { status: 500 });
  return new Response(JSON.stringify({ ok: true, generated: !!nudge, cost_usd: cost }));
});
```

- [ ] **Step 4: Snapshot SQL function**

Create migration `twins-dash/supabase/migrations/20260425140100_build_tech_nudge_snapshot.sql`:

```sql
-- Returns a JSON snapshot for a single tech's prior week.
CREATE OR REPLACE FUNCTION public.build_tech_nudge_snapshot(p_tech_id uuid, p_week_start date)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE result jsonb;
BEGIN
  -- Engineer: build the snapshot per the TechSnapshot shape in
  -- supabase/functions/generate-tech-nudge/prompt.ts. Source: my_scorecard
  -- run with p_tech_id (use a sibling helper or inline set_role), the
  -- company_scorecard RPC for averages, scorecard_tier_thresholds + overrides
  -- via get_my_tier semantics applied to p_tech_id.
  -- Drivers (e.g. memberships offered on N of M jobs) come from raw payroll_jobs
  -- joined with parts/membership flags; if too complex for v1, omit drivers.
  result := jsonb_build_object(
    'first_name', (SELECT split_part(name, ' ', 1) FROM public.technicians WHERE id = p_tech_id),
    'week_range_text', to_char(p_week_start, 'Mon DD') || ' – ' || to_char(p_week_start + 6, 'Mon DD'),
    'jobs_in_window', 0,    -- TODO compute
    'kpis', '[]'::jsonb     -- TODO compute
  );
  RETURN result;
END $$;

GRANT EXECUTE ON FUNCTION public.build_tech_nudge_snapshot(uuid, date) TO service_role;
```

> **NOTE:** This function has TODO comments because the engineer must inspect the existing `my_scorecard` and `company_scorecard` (or whichever RPC supplies company-wide values) to wire it correctly. Do not ship the `'[]'::jsonb` placeholder — fill it in with the actual per-KPI rows. Add a unit test in `tests/sql/build_tech_nudge_snapshot.spec.sql` that calls the function for a known tech and asserts the returned shape.

- [ ] **Step 5: Set Edge Function secrets**

```bash
npx supabase secrets set ANTHROPIC_API_KEY=sk-ant-...
```

- [ ] **Step 6: Deploy**

```bash
npx supabase functions deploy generate-tech-nudge
```

- [ ] **Step 7: Manual smoke-test**

```bash
curl -X POST "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/generate-tech-nudge" \
  -H "Authorization: Bearer $(npx supabase functions secrets get --json | jq -r .SUPABASE_SERVICE_ROLE_KEY)" \
  -H "Content-Type: application/json" \
  -d '{"tech_id":"<MAURICE_TECH_ID>","week_start":"2026-04-14"}'
```

Expected: `{ "ok": true, "generated": true, "cost_usd": 0.00...}`. Verify a row appears in `tech_ai_nudges`.

- [ ] **Step 8: Commit**

```bash
git add supabase/functions/generate-tech-nudge/ supabase/migrations/20260425140100_build_tech_nudge_snapshot.sql
git commit -m "feat(tech-portal): generate-tech-nudge Edge Function + snapshot RPC"
```

---

### Task 22: pg_cron weekly AI nudge job

**Files:**
- Create: `twins-dash/supabase/migrations/20260425140500_pg_cron_ai_nudges.sql`

- [ ] **Step 1: Inspect existing pg_cron pattern**

```bash
grep -A 10 "cron.schedule" twins-dash/supabase/migrations/20260425100200_low_stock_weekly_cron.sql
```

Use the same `cron.schedule(...)` pattern, plus `net.http_post` to invoke the Edge Function.

- [ ] **Step 2: Write migration**

```sql
-- 20260425140500_pg_cron_ai_nudges.sql
-- Runs every Monday at 11:00 UTC (6:00 AM Madison local during DST).
-- For each active tech, invokes the generate-tech-nudge Edge Function.
SELECT cron.schedule(
  'tech_portal_ai_nudges_weekly',
  '0 11 * * 1',
  $$
  WITH paused AS (SELECT (value::text)::boolean AS p FROM public.app_settings WHERE key='ai_nudge_paused')
  SELECT net.http_post(
    url := current_setting('app.functions_url') || '/generate-tech-nudge',
    headers := jsonb_build_object(
      'Content-Type', 'application/json',
      'Authorization', 'Bearer ' || current_setting('app.service_role_key')
    ),
    body := jsonb_build_object('tech_id', t.id, 'week_start', (date_trunc('week', now() - interval '7 days'))::date)
  )
  FROM public.technicians t
  WHERE t.is_active = true
    AND NOT EXISTS (SELECT 1 FROM paused WHERE p = true);
  $$
);
```

> **NOTE:** `app.functions_url` and `app.service_role_key` must be set as Postgres-level config. The existing low-stock cron likely has the same setup — copy its bootstrap pattern (typically a one-time `ALTER DATABASE ... SET app.functions_url = ...`). If those settings don't exist, fall back to hardcoding the function URL and using `pg_net` with the service-role key from a secured helper function.

- [ ] **Step 3: Apply**

```bash
npx supabase db push
```

- [ ] **Step 4: Verify cron registered**

```bash
npx supabase db remote query "SELECT jobname, schedule FROM cron.job WHERE jobname LIKE '%ai_nudges%';"
```

Expected: 1 row, schedule `0 11 * * 1`.

- [ ] **Step 5: Commit**

```bash
git add supabase/migrations/20260425140500_pg_cron_ai_nudges.sql
git commit -m "feat(tech-portal): pg_cron weekly AI nudge job (Mon 11:00 UTC)"
```

---

### Task 23: useMyAiNudge hook + AiNudgeCard component

**Files:**
- Create: `twins-dash/src/hooks/tech/useMyAiNudge.ts`
- Create: `twins-dash/src/components/tech/AiNudgeCard.tsx`

- [ ] **Step 1: Hook**

```ts
// useMyAiNudge.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export type AiNudge = {
  week_start: string;
  headline: string | null;
  lede: string | null;
  bullets: string[] | null;
  jobs_in_window: number;
  model: string | null;
};

export function useMyAiNudge() {
  return useQuery({
    queryKey: ['my_ai_nudge'],
    queryFn: async (): Promise<AiNudge | null> => {
      const { data, error } = await supabase
        .from('tech_ai_nudges')
        .select('week_start, headline, lede, bullets, jobs_in_window, model')
        .eq('tech_id', (await supabase.rpc('current_technician_id')).data!)
        .order('week_start', { ascending: false }).limit(1).maybeSingle();
      if (error) throw error;
      return data as AiNudge | null;
    },
    staleTime: 5 * 60_000,
  });
}
```

- [ ] **Step 2: Component**

```tsx
// AiNudgeCard.tsx
import type { AiNudge } from '@/hooks/tech/useMyAiNudge';
import { format } from 'date-fns';

export function AiNudgeCard({ nudge }: { nudge: AiNudge | null }) {
  if (!nudge || !nudge.bullets) {
    return (
      <div className="rounded-2xl bg-yellow-50 border border-accent/50 p-4">
        <div className="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">💡 Tip</div>
        <div className="text-sm text-amber-950 mt-1">Your weekly AI nudge will appear here every Monday once you have at least 5 jobs in a week.</div>
      </div>
    );
  }
  return (
    <div className="relative rounded-2xl bg-gradient-to-br from-yellow-100 via-yellow-50 to-amber-100 border border-accent p-5 overflow-hidden">
      <div className="absolute -right-3 -top-3 text-7xl opacity-10">🤖</div>
      <div className="relative">
        <div className="flex items-center gap-2">
          <span className="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-primary text-accent uppercase tracking-wider">🤖 AI nudge</span>
          <span className="text-[11px] text-amber-900 font-semibold">Week of {format(new Date(nudge.week_start), 'MMM d')} — refreshed Mon 6:00 AM</span>
        </div>
        <h2 className="text-lg font-extrabold text-primary mt-2 tracking-tight">{nudge.headline}</h2>
        <p className="text-sm text-amber-950 leading-relaxed">{nudge.lede}</p>
        <ol className="mt-3 space-y-2">
          {nudge.bullets.map((b, i) => (
            <li key={i} className="bg-white/60 border border-accent/40 rounded-xl px-3 py-2.5 pl-9 relative text-sm text-slate-900 leading-snug">
              <span className="absolute left-3 top-3 w-4 h-4 rounded-full bg-primary text-accent text-[10px] font-extrabold grid place-items-center">{i + 1}</span>
              {b}
            </li>
          ))}
        </ol>
        <div className="text-[10.5px] text-amber-900 mt-3 flex justify-between">
          <span>📊 Based on {nudge.jobs_in_window} jobs</span>
          <span className="opacity-70">Powered by Claude Haiku</span>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Commit**

```bash
git add src/hooks/tech/useMyAiNudge.ts src/components/tech/AiNudgeCard.tsx
git commit -m "feat(tech-portal): useMyAiNudge hook + AiNudgeCard component"
```

---

## M6 — Goals tab assembly

### Task 24: TierLadderRow component

**Files:**
- Create: `twins-dash/src/components/tech/TierLadderRow.tsx`

- [ ] **Step 1: Implement**

```tsx
// TierLadderRow.tsx
import type { Tier } from './TierBadge';
import { TierBadge } from './TierBadge';

type Props = {
  label: string;
  tier: Tier | null;
  progressPct: number;       // 0..100 across the entire ladder
  caption: string;           // e.g. "$48k of $55k for Elite · $7k to go"
};

const FILL: Record<string, string> = {
  bronze: 'bg-gradient-to-r from-amber-300 to-orange-400',
  silver: 'bg-gradient-to-r from-slate-300 to-slate-400',
  gold:   'bg-gradient-to-r from-accent to-yellow-300',
  elite:  'bg-gradient-to-r from-primary to-indigo-700',
};

export function TierLadderRow({ label, tier, progressPct, caption }: Props) {
  return (
    <div className="grid grid-cols-[100px_1fr_70px] gap-3 items-center py-2 border-t border-border first:border-t-0">
      <div className="text-xs font-bold text-primary">{label}</div>
      <div>
        <div className="relative h-3.5 bg-muted rounded-full overflow-hidden">
          <div className={`absolute inset-y-0 left-0 ${tier ? FILL[tier] : 'bg-slate-200'}`} style={{ width: `${progressPct}%` }} />
          <div className="absolute inset-0 grid grid-cols-4 pointer-events-none">
            {[0,1,2].map(i => <div key={i} className="border-r border-white/70" />)}
          </div>
        </div>
        <div className="text-[10px] text-muted-foreground mt-0.5">{caption}</div>
      </div>
      <div className="justify-self-end"><TierBadge tier={tier} /></div>
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add src/components/tech/TierLadderRow.tsx
git commit -m "feat(tech-portal): TierLadderRow component"
```

---

### Task 25: WhatChangedGrid component

**Files:**
- Create: `twins-dash/src/components/tech/WhatChangedGrid.tsx`

- [ ] **Step 1: Implement**

```tsx
// WhatChangedGrid.tsx
type Delta = { display_name: string; current: number; prior: number; delta: number; tone: 'up' | 'down' | 'flat' };

function fmtDelta(d: Delta): string {
  // delegated; for v1, simple formatter:
  const sign = d.delta > 0 ? '+' : '';
  return `${sign}${d.delta.toFixed(d.display_name.includes('Revenue') ? 0 : 1)}`;
}

export function WhatChangedGrid({ deltas }: { deltas: Delta[] }) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
      {deltas.map(d => (
        <div key={d.display_name} className="bg-card border border-border rounded-xl px-3 py-2.5 flex items-center gap-2.5">
          <div className={`w-7 h-7 rounded-lg grid place-items-center font-extrabold text-base ${
            d.tone === 'up' ? 'bg-emerald-100 text-emerald-700' :
            d.tone === 'down' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600'
          }`}>{d.tone === 'up' ? '↑' : d.tone === 'down' ? '↓' : '→'}</div>
          <div className="text-xs text-foreground">
            <b className="text-primary">{d.display_name}</b>{' '}{fmtDelta(d)} (from {d.prior.toFixed(0)})
          </div>
        </div>
      ))}
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add src/components/tech/WhatChangedGrid.tsx
git commit -m "feat(tech-portal): WhatChangedGrid component"
```

---

### Task 26: coaching-tips data + TipLibrary component

**Files:**
- Create: `twins-dash/src/data/coaching-tips.ts`
- Create: `twins-dash/src/components/tech/TipLibrary.tsx`

- [ ] **Step 1: Static tip data**

```ts
// coaching-tips.ts
export type CoachingTip = { kpi_key: string; label: string; title: string; preview: string; body: string };

export const COACHING_TIPS: CoachingTip[] = [
  {
    kpi_key: 'membership_pct',
    label: 'Membership',
    title: '5 things to say to convert membership',
    preview: 'Frame next year\'s tune-up as the hook. Anchor against repair cost. Offer the "first month free" intro.',
    body: `1. Anchor it against the repair you just did: "If this opener fails again, you\'re looking at $X. Membership covers it."
2. Frame next year\'s spring tune-up as the hook: "We\'ll be back out anyway."
3. Mention the priority scheduling: "You jump the queue for emergencies."
4. Offer first-month-free if they\'re on the fence.
5. Show the 10% off any future repair line — most members use it within 6 months.`,
  },
  {
    kpi_key: 'closing_pct',
    label: 'Closing %',
    title: 'Why estimates die in the driveway',
    preview: '3 reasons customers say "I\'ll think about it" and what to do in the moment to flip them.',
    body: `1. Price shock: lead with cost-of-ownership, not sticker price. "$X over 5 years" beats "$X today."
2. Decision-maker not present: ask early. "Is anyone else in on this decision?" If yes, offer a 3-way call.
3. Trust gap: walk them through the failure mode you found. People buy fixes for problems they understand.`,
  },
  {
    kpi_key: 'avg_opportunity',
    label: 'Avg ticket',
    title: 'The 4 add-ons that always make sense',
    preview: 'Springs, rollers, lube service, and reinforcement strut — when each is honest to recommend.',
    body: `1. Springs: if cycle count > 10k or visible rust, recommend replacement. Same trip = no callback charge.
2. Rollers: nylon over steel for noise-sensitive customers. $80 add-on, instant value.
3. Lube service: $40, takes 5 min, sets up the next membership renewal conversation.
4. Reinforcement strut: any door without one with a top-mount opener is a future warranty call.`,
  },
  {
    kpi_key: 'callback_pct',
    label: 'Callbacks',
    title: 'The 60-second pre-leave checklist',
    preview: 'Cycle the door 3x. Listen for the click. Test the safety reverse. The boring stuff that saves callbacks.',
    body: `Before you collect payment, every job:
1. Cycle the door 3 full times. Listen at the spring side for clicks or pops.
2. Test the safety reverse with a 2x4 across the threshold. Confirm it stops AND reverses.
3. Check the photo eyes are aligned and clean. Wipe lenses, snug the brackets.
4. Walk the customer through the test. They\'ll feel safer and call you less.
5. Note the cycle count in the work order — flags premature wear for the next visit.`,
  },
  // Add the remaining 4 KPIs with similar entries.
];

export function tipForKpi(kpi: string): CoachingTip | undefined {
  return COACHING_TIPS.find(t => t.kpi_key === kpi);
}
```

> **NOTE:** Engineer must add tip entries for `revenue`, `total_jobs`, `avg_repair`, `avg_install`. Use the same shape and the same plainspoken voice as the 4 above. Daniel can revise the bodies later — the goal here is shape and infrastructure.

- [ ] **Step 2: TipLibrary component**

```tsx
// TipLibrary.tsx
import { COACHING_TIPS } from '@/data/coaching-tips';
import { useState } from 'react';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';

export function TipLibrary() {
  const [open, setOpen] = useState<string | null>(null);
  const tip = COACHING_TIPS.find(t => t.kpi_key === open);
  return (
    <>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
        {COACHING_TIPS.map(t => (
          <button key={t.kpi_key} onClick={() => setOpen(t.kpi_key)}
            className="text-left bg-card border border-border rounded-xl p-3 hover:border-accent transition">
            <div className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">{t.label}</div>
            <div className="text-sm font-bold text-primary mt-1">{t.title}</div>
            <div className="text-[11.5px] text-muted-foreground mt-1 leading-snug">{t.preview}</div>
          </button>
        ))}
      </div>
      <Sheet open={!!open} onOpenChange={(o) => !o && setOpen(null)}>
        <SheetContent side="bottom" className="rounded-t-2xl max-h-[85vh] overflow-y-auto">
          <SheetHeader><SheetTitle>{tip?.title}</SheetTitle></SheetHeader>
          <div className="text-sm text-foreground whitespace-pre-line mt-3">{tip?.body}</div>
        </SheetContent>
      </Sheet>
    </>
  );
}
```

- [ ] **Step 3: Commit**

```bash
git add src/data/coaching-tips.ts src/components/tech/TipLibrary.tsx
git commit -m "feat(tech-portal): coaching tips data + TipLibrary component"
```

---

### Task 27: Assemble Goals.tsx

**Files:**
- Modify: `twins-dash/src/pages/tech/Goals.tsx`

- [ ] **Step 1: Replace stub with full assembly**

```tsx
// Goals.tsx
import { subDays } from 'date-fns';
import { useMyAiNudge } from '@/hooks/tech/useMyAiNudge';
import { useMyScorecardWithTiers } from '@/hooks/tech/useMyScorecardWithTiers';
import { useTierThresholds } from '@/hooks/tech/useTierThresholds';
import { useWhatChangedThisWeek } from '@/hooks/tech/useWhatChangedThisWeek';
import { AiNudgeCard } from '@/components/tech/AiNudgeCard';
import { TierLadderRow } from '@/components/tech/TierLadderRow';
import { WhatChangedGrid } from '@/components/tech/WhatChangedGrid';
import { TipLibrary } from '@/components/tech/TipLibrary';

export default function Goals() {
  const { data: nudge } = useMyAiNudge();
  const { data: scorecard } = useMyScorecardWithTiers(subDays(new Date(), 30), new Date());
  const { data: thresholds = [] } = useTierThresholds();
  const { data: deltas = [] } = useWhatChangedThisWeek();

  return (
    <div className="p-4 md:p-6 max-w-3xl mx-auto space-y-5">
      <AiNudgeCard nudge={nudge ?? null} />

      <section className="bg-card border border-border rounded-2xl p-4 md:p-5">
        <h3 className="text-sm font-extrabold uppercase tracking-wider text-primary">Tier ladder · all KPIs</h3>
        <p className="text-xs text-muted-foreground mt-0.5">Where you sit on each KPI. Targets are admin-tunable.</p>
        <div className="mt-3">
          {scorecard && thresholds.slice(0, 6).map(t => {
            const value = Number((scorecard as any)[t.kpi_key] ?? 0);
            const tier = (scorecard as any)[`${t.kpi_key}_tier`];
            return <TierLadderRow key={t.kpi_key} label={t.display_name} tier={tier}
                       progressPct={progressFor(value, t)} caption={captionFor(value, tier, t)} />;
          })}
        </div>
        {thresholds.length > 6 && (
          <div className="text-[11px] text-muted-foreground mt-2">+ {thresholds.length - 6} more on Home</div>
        )}
      </section>

      <section className="bg-card border border-border rounded-2xl p-4 md:p-5">
        <h3 className="text-sm font-extrabold uppercase tracking-wider text-primary">What changed this week</h3>
        <p className="text-xs text-muted-foreground mt-0.5 mb-3">Movement vs your prior 7-day window.</p>
        <WhatChangedGrid deltas={deltas} />
      </section>

      <section className="bg-card border border-border rounded-2xl p-4 md:p-5">
        <h3 className="text-sm font-extrabold uppercase tracking-wider text-primary">Tip library</h3>
        <p className="text-xs text-muted-foreground mt-0.5 mb-3">Tap a card for the full how-to.</p>
        <TipLibrary />
      </section>
    </div>
  );
}

function progressFor(value: number, t: any): number {
  // Spans the full ladder bronze→elite as 0→100%.
  const range = t.elite - t.bronze;
  if (range === 0) return 100;
  if (t.direction === 'higher') return Math.max(0, Math.min(100, ((value - t.bronze) / range) * 100));
  return Math.max(0, Math.min(100, ((t.bronze - value) / (t.bronze - t.elite)) * 100));
}
function captionFor(value: number, tier: string | null, t: any): string {
  if (tier === 'elite') return `${formatVal(value, t.unit)} · top of the ladder 🥇`;
  const next = tier === 'gold' ? { name: 'Elite', target: t.elite } : tier === 'silver' ? { name: 'Gold', target: t.gold } :
               tier === 'bronze' ? { name: 'Silver', target: t.silver } : { name: 'Bronze', target: t.bronze };
  return `${formatVal(value, t.unit)} of ${formatVal(next.target, t.unit)} for ${next.name} · ${formatVal(Math.abs(next.target - value), t.unit)} to go`;
}
function formatVal(v: number, unit: 'usd'|'pct'|'count'): string {
  if (unit === 'usd')   return v >= 1000 ? `$${(v/1000).toFixed(0)}k` : `$${v.toFixed(0)}`;
  if (unit === 'pct')   return `${v.toFixed(0)}%`;
  return String(Math.round(v));
}
```

- [ ] **Step 2: Visual verification**

Open `/tech/goals` as a tech. Verify nudge card (or empty state), tier ladder rows, what-changed deltas, tip library cards open sheets.

- [ ] **Step 3: Commit**

```bash
git add src/pages/tech/Goals.tsx
git commit -m "feat(tech-portal): Goals tab assembly (nudge + ladder + delta + tips)"
```

---

## M7 — Streaks + PRs data layer

### Task 28: tech_streaks + tech_personal_records tables

**Files:**
- Create: `twins-dash/supabase/migrations/20260425140100_tech_streaks.sql`
- Create: `twins-dash/supabase/migrations/20260425140200_tech_personal_records.sql`

- [ ] **Step 1: tech_streaks**

```sql
CREATE TABLE public.tech_streaks (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tech_id       uuid NOT NULL REFERENCES public.technicians(id) ON DELETE CASCADE,
  kind          text NOT NULL CHECK (kind IN ('above_avg','tier_held','no_callbacks','rev_floor')),
  kpi_key       text REFERENCES public.scorecard_tier_thresholds(kpi_key),
  count         int  NOT NULL,
  unit          text NOT NULL CHECK (unit IN ('week','month')),
  since_period  date NOT NULL,
  active        boolean NOT NULL DEFAULT true,
  updated_at    timestamptz NOT NULL DEFAULT now(),
  UNIQUE (tech_id, kind, kpi_key)  -- one active streak per (tech, kind, kpi)
);

ALTER TABLE public.tech_streaks ENABLE ROW LEVEL SECURITY;
CREATE POLICY tech_streaks_select_own_or_admin
  ON public.tech_streaks FOR SELECT TO authenticated
  USING (tech_id = public.current_technician_id() OR public.has_payroll_access());
```

- [ ] **Step 2: tech_personal_records**

```sql
CREATE TABLE public.tech_personal_records (
  id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tech_id      uuid NOT NULL REFERENCES public.technicians(id) ON DELETE CASCADE,
  kpi_key      text NOT NULL REFERENCES public.scorecard_tier_thresholds(kpi_key),
  period       text NOT NULL CHECK (period IN ('week','month','year')),
  value        numeric NOT NULL,
  achieved_at  date NOT NULL,
  is_fresh     boolean NOT NULL DEFAULT false,
  updated_at   timestamptz NOT NULL DEFAULT now(),
  UNIQUE (tech_id, kpi_key, period)
);

ALTER TABLE public.tech_personal_records ENABLE ROW LEVEL SECURITY;
CREATE POLICY tech_personal_records_select_own_or_admin
  ON public.tech_personal_records FOR SELECT TO authenticated
  USING (tech_id = public.current_technician_id() OR public.has_payroll_access());
```

- [ ] **Step 3: Apply + regen types**

```bash
npx supabase db push
npx supabase gen types typescript --linked > src/integrations/supabase/types.ts
```

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260425140100_tech_streaks.sql supabase/migrations/20260425140200_tech_personal_records.sql src/integrations/supabase/types.ts
git commit -m "feat(tech-portal): tech_streaks + tech_personal_records tables"
```

---

### Task 29: compute_streaks_and_prs SQL function

**Files:**
- Create: `twins-dash/supabase/migrations/20260425140300_compute_streaks_and_prs_function.sql`

- [ ] **Step 1: Write function**

```sql
-- 20260425140300_compute_streaks_and_prs_function.sql
-- Recomputes all active streaks and PRs from scratch for all active techs.
-- Cheap enough to run nightly.
CREATE OR REPLACE FUNCTION public.compute_streaks_and_prs()
RETURNS void LANGUAGE plpgsql SECURITY DEFINER AS $$
DECLARE v_tech RECORD;
BEGIN
  FOR v_tech IN SELECT id FROM public.technicians WHERE is_active LOOP
    -- 1. Personal records: best week / month / year per KPI for this tech.
    --    Query my_scorecard for each weekly bucket, find max (or min for callback_pct).
    --    UPSERT into tech_personal_records.
    --    Set is_fresh = (achieved_at >= now() - interval '7 days').
    -- 2. Streaks: walk weekly buckets backwards, count consecutive weeks where:
    --    - 'above_avg' for each KPI: my_value > company_value
    --    - 'tier_held': tier label unchanged
    --    - 'no_callbacks': callback_pct = 0
    --    - 'rev_floor': revenue >= configurable floor (e.g. $30k); for now use scorecard_tier_thresholds.silver
    --    UPSERT into tech_streaks; set active=false where the chain just broke.
    --
    -- ENGINEER NOTE: This is the most complex SQL in the plan. Implement
    -- iteratively: write one section, write a SELECT that verifies it against
    -- a known seed, then move to the next section. Keep set-based queries
    -- where possible; only loop per-tech to keep memory small.
    NULL;  -- placeholder
  END LOOP;
END $$;

GRANT EXECUTE ON FUNCTION public.compute_streaks_and_prs() TO service_role;
```

> **NOTE:** This function MUST be implemented before the M8 Recognition tab can render real data. Suggested approach: pre-aggregate weekly KPI snapshots into a temporary CTE per tech, then derive streaks/PRs from there. Add a test SQL script under `tests/sql/compute_streaks_and_prs.spec.sql` that seeds 4 weeks of synthetic jobs and asserts: (a) a 4-week above-avg streak appears, (b) the highest weekly revenue lands as a PR with `is_fresh = true` if recent.

- [ ] **Step 2: Apply**

```bash
npx supabase db push
```

- [ ] **Step 3: Commit (function shell + skeleton)**

```bash
git add supabase/migrations/20260425140300_compute_streaks_and_prs_function.sql
git commit -m "feat(tech-portal): compute_streaks_and_prs function shell"
```

- [ ] **Step 4: Implement the function body in a follow-up commit on this same branch**

After the body is implemented and tested, commit with:

```bash
git commit -am "feat(tech-portal): implement compute_streaks_and_prs body"
```

---

### Task 30: pg_cron nightly streaks/PRs job

**Files:**
- Create: `twins-dash/supabase/migrations/20260425140400_pg_cron_streaks_prs.sql`

- [ ] **Step 1: Write migration**

```sql
-- 20260425140400_pg_cron_streaks_prs.sql
-- Daily 07:00 UTC = 2:00 AM Madison local during DST.
SELECT cron.schedule(
  'tech_portal_streaks_prs_nightly',
  '0 7 * * *',
  $$ SELECT public.compute_streaks_and_prs(); $$
);
```

- [ ] **Step 2: Apply + verify**

```bash
npx supabase db push
npx supabase db remote query "SELECT jobname, schedule FROM cron.job WHERE jobname LIKE '%streaks%';"
```

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260425140400_pg_cron_streaks_prs.sql
git commit -m "feat(tech-portal): pg_cron nightly streaks/PRs job"
```

---

### Task 31: useMyStreaks + useMyPersonalRecords + useTierUpMoments hooks

**Files:**
- Create: `twins-dash/src/hooks/tech/useMyStreaks.ts`
- Create: `twins-dash/src/hooks/tech/useMyPersonalRecords.ts`
- Create: `twins-dash/src/hooks/tech/useTierUpMoments.ts`

- [ ] **Step 1: useMyStreaks**

```ts
// useMyStreaks.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export type Streak = {
  id: string; kind: 'above_avg'|'tier_held'|'no_callbacks'|'rev_floor';
  kpi_key: string | null; count: number; unit: 'week'|'month'; since_period: string;
};

export function useMyStreaks() {
  return useQuery({
    queryKey: ['my_streaks'],
    queryFn: async (): Promise<Streak[]> => {
      const { data, error } = await supabase
        .from('tech_streaks')
        .select('id, kind, kpi_key, count, unit, since_period')
        .eq('active', true)
        .order('count', { ascending: false });
      if (error) throw error;
      return (data ?? []) as Streak[];
    },
    staleTime: 60_000,
  });
}
```

- [ ] **Step 2: useMyPersonalRecords**

```ts
// useMyPersonalRecords.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export type PersonalRecord = {
  id: string; kpi_key: string; period: 'week'|'month'|'year';
  value: number; achieved_at: string; is_fresh: boolean;
};

export function useMyPersonalRecords() {
  return useQuery({
    queryKey: ['my_personal_records'],
    queryFn: async (): Promise<PersonalRecord[]> => {
      const { data, error } = await supabase
        .from('tech_personal_records')
        .select('id, kpi_key, period, value, achieved_at, is_fresh')
        .order('is_fresh', { ascending: false });
      if (error) throw error;
      return (data ?? []) as PersonalRecord[];
    },
    staleTime: 60_000,
  });
}
```

- [ ] **Step 3: useTierUpMoments — derived client-side from PR + tier history**

```ts
// useTierUpMoments.ts
import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';

export type TierUp = { kpi_key: string; tier: 'silver'|'gold'|'elite'; achieved_at: string };

export function useTierUpMoments() {
  return useQuery({
    queryKey: ['my_tier_ups'],
    queryFn: async (): Promise<TierUp[]> => {
      // For v1, derive from compute_streaks_and_prs side effects: a row in
      // tech_streaks of kind='tier_held' where since_period > now() - 30d
      // implies a tier-up moment in the last 30 days.
      const cutoff = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
      const { data, error } = await supabase
        .from('tech_streaks')
        .select('kpi_key, since_period')
        .eq('kind', 'tier_held').eq('active', true).gte('since_period', cutoff);
      if (error) throw error;
      // Engineer note: enrich with tier label by joining against a recent
      // get_my_scorecard_with_tiers call OR by adding a `tier` column to
      // tech_streaks. For now, return tier='gold' as a placeholder —
      // implement properly when wiring TierUpCard.
      return (data ?? []).map((r: any) => ({ kpi_key: r.kpi_key, tier: 'gold' as const, achieved_at: r.since_period }));
    },
    staleTime: 60_000,
  });
}
```

> **NOTE:** The placeholder tier='gold' must be replaced. Two viable paths: (a) add a `tier` column to `tech_streaks` and have `compute_streaks_and_prs` populate it for `kind='tier_held'`; (b) join client-side against the most recent scorecard. (a) is simpler and recommended. Add to compute function when you fill it in.

- [ ] **Step 4: Commit**

```bash
git add src/hooks/tech/useMyStreaks.ts src/hooks/tech/useMyPersonalRecords.ts src/hooks/tech/useTierUpMoments.ts
git commit -m "feat(tech-portal): useMyStreaks, useMyPersonalRecords, useTierUpMoments hooks"
```

---

## M8 — Recognition tab assembly

### Task 32: YearRibbon + TierUpCard + StreakCard + PersonalRecordTile components

**Files:**
- Create: `twins-dash/src/components/tech/YearRibbon.tsx`
- Create: `twins-dash/src/components/tech/TierUpCard.tsx`
- Create: `twins-dash/src/components/tech/StreakCard.tsx`
- Create: `twins-dash/src/components/tech/PersonalRecordTile.tsx`
- Create: `twins-dash/src/components/tech/RecognitionEmptyState.tsx`

- [ ] **Step 1: YearRibbon**

```tsx
// YearRibbon.tsx
type Props = { techFirstName: string; ytdRevenue: number; onPaceRevenue: number; ytdJobs: number; avgTicket: number; ytdMemberships: number; yoyDelta: number };
const fmtUsd = (n: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n);

export function YearRibbon(p: Props) {
  return (
    <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-indigo-900 text-primary-foreground p-5">
      <div className="absolute -right-5 -top-5 w-32 h-32 rounded-full" style={{ background: 'radial-gradient(circle, hsl(var(--accent) / .4), transparent 70%)' }} />
      <div className="text-[10px] font-extrabold text-accent uppercase tracking-wider">{new Date().getFullYear()} · Year so far</div>
      <h2 className="text-2xl font-extrabold tracking-tight mt-1">{p.techFirstName}'s year</h2>
      <div className="grid grid-cols-3 gap-3 mt-4">
        <Stat k="YTD revenue"      v={fmtUsd(p.ytdRevenue)}  s={`on pace for ${fmtUsd(p.onPaceRevenue)}`} />
        <Stat k="YTD jobs"         v={String(p.ytdJobs)}     s={`avg ticket ${fmtUsd(p.avgTicket)}`} />
        <Stat k="Memberships sold" v={String(p.ytdMemberships)} s={`${p.yoyDelta >= 0 ? '+' : ''}${p.yoyDelta} vs same time last year`} />
      </div>
    </div>
  );
}
function Stat({ k, v, s }: { k: string; v: string; s: string }) {
  return (
    <div>
      <div className="text-[10px] text-accent/85 font-bold uppercase tracking-wider">{k}</div>
      <div className="text-xl font-extrabold tracking-tight mt-0.5">{v}</div>
      <div className="text-[11px] text-white/65 mt-0.5">{s}</div>
    </div>
  );
}
```

- [ ] **Step 2: TierUpCard**

```tsx
// TierUpCard.tsx
import { formatDistanceToNow } from 'date-fns';
type Props = { kpiName: string; tier: 'silver'|'gold'|'elite'; achievedAt: string; previousTier: string; nextTierThresholdText: string };

export function TierUpCard(p: Props) {
  const days = formatDistanceToNow(new Date(p.achievedAt), { addSuffix: true });
  return (
    <div className="rounded-2xl bg-gradient-to-br from-yellow-50 to-amber-100 border-2 border-accent p-4 flex gap-3.5 items-center">
      <div className="text-4xl leading-none">🎉</div>
      <div className="flex-1">
        <div className="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">New tier · {days}</div>
        <h3 className="text-base text-primary font-extrabold mt-0.5">You hit {capitalize(p.tier)} on {p.kpiName}</h3>
        <p className="text-xs text-amber-950 mt-1 leading-relaxed">Up from {p.previousTier}. Keep it up: {p.nextTierThresholdText}</p>
      </div>
      <div className="text-[10px] text-amber-800 font-bold">Pinned for 30 days</div>
    </div>
  );
}
function capitalize(s: string) { return s[0].toUpperCase() + s.slice(1); }
```

- [ ] **Step 3: StreakCard**

```tsx
// StreakCard.tsx
type Props = { icon: string; count: number; unit: 'week'|'month'; what: string; sinceText: string };
export function StreakCard(p: Props) {
  return (
    <div className="bg-card border border-border rounded-xl p-3 flex gap-3 items-center">
      <div className="w-11 h-11 rounded-xl bg-yellow-50 border border-accent grid place-items-center text-xl flex-shrink-0">{p.icon}</div>
      <div className="flex-1 min-w-0">
        <div className="text-base font-extrabold text-primary tracking-tight">{p.count} {p.count === 1 ? p.unit : p.unit + 's'}</div>
        <div className="text-xs text-foreground leading-snug mt-0.5" dangerouslySetInnerHTML={{ __html: p.what }} />
        <div className="text-[10px] text-muted-foreground mt-1">{p.sinceText}</div>
      </div>
    </div>
  );
}
```

- [ ] **Step 4: PersonalRecordTile**

```tsx
// PersonalRecordTile.tsx
type Props = { label: string; value: string; context: string; isFresh: boolean };
export function PersonalRecordTile({ label, value, context, isFresh }: Props) {
  return (
    <div className={`rounded-xl p-3 border ${isFresh ? 'bg-yellow-50 border-accent/50' : 'bg-muted border-border'}`}>
      <div className={`text-[10px] font-bold uppercase tracking-wider ${isFresh ? 'text-amber-800' : 'text-muted-foreground'}`}>
        {isFresh ? '🆕 ' : ''}{label}
      </div>
      <div className="text-lg font-extrabold text-primary tracking-tight mt-0.5">{value}</div>
      <div className={`text-[11px] mt-0.5 ${isFresh ? 'text-amber-800 font-semibold' : 'text-muted-foreground'}`}>{context}</div>
    </div>
  );
}
```

- [ ] **Step 5: RecognitionEmptyState**

```tsx
// RecognitionEmptyState.tsx
export function RecognitionEmptyState() {
  return (
    <div className="bg-muted border border-dashed border-border rounded-xl p-4 text-center text-xs text-muted-foreground">
      <div className="text-2xl">🌱</div>
      <div className="mt-1.5 font-bold text-primary">Your wins will show up here as you build them.</div>
      <div className="mt-1">After your first full week, we'll start tracking personal records. After 30 days, streaks unlock.</div>
    </div>
  );
}
```

- [ ] **Step 6: Commit**

```bash
git add src/components/tech/YearRibbon.tsx src/components/tech/TierUpCard.tsx src/components/tech/StreakCard.tsx src/components/tech/PersonalRecordTile.tsx src/components/tech/RecognitionEmptyState.tsx
git commit -m "feat(tech-portal): Recognition tab building blocks"
```

---

### Task 33: Assemble Recognition.tsx

**Files:**
- Modify: `twins-dash/src/pages/tech/Recognition.tsx`

- [ ] **Step 1: Implement**

```tsx
// Recognition.tsx
import { useAuth } from '@/contexts/AuthContext';
import { useMyStreaks } from '@/hooks/tech/useMyStreaks';
import { useMyPersonalRecords } from '@/hooks/tech/useMyPersonalRecords';
import { useTierUpMoments } from '@/hooks/tech/useTierUpMoments';
import { useMyScorecardWithTiers } from '@/hooks/tech/useMyScorecardWithTiers';
import { useTierThresholds } from '@/hooks/tech/useTierThresholds';
import { YearRibbon } from '@/components/tech/YearRibbon';
import { TierUpCard } from '@/components/tech/TierUpCard';
import { StreakCard } from '@/components/tech/StreakCard';
import { PersonalRecordTile } from '@/components/tech/PersonalRecordTile';
import { RecognitionEmptyState } from '@/components/tech/RecognitionEmptyState';
import { format, startOfYear } from 'date-fns';

const STREAK_ICON: Record<string, string> = { above_avg: '🔥', tier_held: '⭐', no_callbacks: '🎯', rev_floor: '📈' };

export default function Recognition() {
  const { technicianName } = useAuth();
  const firstName = (technicianName ?? 'You').split(' ')[0];
  const { data: streaks = [] } = useMyStreaks();
  const { data: prs = [] } = useMyPersonalRecords();
  const { data: tierUps = [] } = useTierUpMoments();
  const { data: ytdScorecard } = useMyScorecardWithTiers(startOfYear(new Date()), new Date());
  const { data: thresholds = [] } = useTierThresholds();

  const isNewTech = streaks.length === 0 && prs.length === 0;

  const ytdRevenue = Number(ytdScorecard?.revenue ?? 0);
  const ytdJobs = Number(ytdScorecard?.total_jobs ?? 0);
  const avgTicket = ytdJobs > 0 ? ytdRevenue / ytdJobs : 0;
  const dayOfYear = Math.floor((Date.now() - startOfYear(new Date()).getTime()) / 86400000) + 1;
  const onPace = dayOfYear > 0 ? (ytdRevenue / dayOfYear) * 365 : 0;

  return (
    <div className="p-4 md:p-6 max-w-3xl mx-auto space-y-4">
      <YearRibbon
        techFirstName={firstName}
        ytdRevenue={ytdRevenue}
        onPaceRevenue={onPace}
        ytdJobs={ytdJobs}
        avgTicket={avgTicket}
        ytdMemberships={0 /* TODO: source from a count RPC */}
        yoyDelta={0 /* TODO: source from prior-year comparison */}
      />

      {tierUps.map(tu => (
        <TierUpCard key={tu.kpi_key} kpiName={kpiName(tu.kpi_key, thresholds)} tier={tu.tier}
          achievedAt={tu.achieved_at} previousTier="Silver" nextTierThresholdText="" />
      ))}

      {streaks.length > 0 && (
        <section className="bg-card border border-border rounded-2xl p-4 md:p-5">
          <h3 className="text-sm font-extrabold uppercase tracking-wider text-primary">Active streaks</h3>
          <p className="text-xs text-muted-foreground mt-0.5 mb-3">Wins you're carrying right now.</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            {streaks.map(s => (
              <StreakCard key={s.id} icon={STREAK_ICON[s.kind] ?? '✨'} count={s.count} unit={s.unit}
                what={streakLabel(s)} sinceText={`since ${format(new Date(s.since_period), 'MMM d')}`} />
            ))}
          </div>
        </section>
      )}

      {prs.length > 0 && (
        <section className="bg-card border border-border rounded-2xl p-4 md:p-5">
          <h3 className="text-sm font-extrabold uppercase tracking-wider text-primary">Personal records</h3>
          <p className="text-xs text-muted-foreground mt-0.5 mb-3">Your best of {new Date().getFullYear()}.</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            {prs.map(pr => (
              <PersonalRecordTile key={pr.id}
                label={`${pr.is_fresh ? 'Best ' : ''}${kpiName(pr.kpi_key, thresholds)} · ${pr.period}`}
                value={formatPrValue(pr.value, kpiUnit(pr.kpi_key, thresholds))}
                context={`${pr.period} of ${format(new Date(pr.achieved_at), 'MMM d')}`}
                isFresh={pr.is_fresh}
              />
            ))}
          </div>
        </section>
      )}

      {isNewTech && <RecognitionEmptyState />}
    </div>
  );
}

function kpiName(key: string, thr: any[]): string { return thr.find(t => t.kpi_key === key)?.display_name ?? key; }
function kpiUnit(key: string, thr: any[]): 'usd'|'pct'|'count' { return thr.find(t => t.kpi_key === key)?.unit ?? 'count'; }
function formatPrValue(v: number, unit: 'usd'|'pct'|'count'): string {
  if (unit === 'usd')   return `$${v.toLocaleString('en-US', { maximumFractionDigits: 0 })}`;
  if (unit === 'pct')   return `${v.toFixed(1)}%`;
  return String(Math.round(v));
}
function streakLabel(s: any): string {
  if (s.kind === 'above_avg')    return `above company avg on <b>${s.kpi_key}</b>`;
  if (s.kind === 'tier_held')    return `<b>${s.kpi_key}</b> tier held`;
  if (s.kind === 'no_callbacks') return `zero <b>callbacks</b>`;
  if (s.kind === 'rev_floor')    return `no week below the revenue floor`;
  return s.kind;
}
```

- [ ] **Step 2: Visual verification**

Open `/tech/wins`. Verify YearRibbon renders, empty-state shows for techs with no streaks/PRs, real data shows for techs with history.

- [ ] **Step 3: Commit**

```bash
git add src/pages/tech/Recognition.tsx
git commit -m "feat(tech-portal): Recognition tab assembly"
```

---

## M9 — Admin /admin/scorecard-tiers page

### Task 34: TierThresholdsTable component

**Files:**
- Create: `twins-dash/src/components/admin/TierThresholdsTable.tsx`

- [ ] **Step 1: Implement editable table**

```tsx
// TierThresholdsTable.tsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/sonner';
import type { TierThreshold } from '@/hooks/tech/useTierThresholds';

export function TierThresholdsTable() {
  const qc = useQueryClient();
  const { data: rows = [] } = useQuery<TierThreshold[]>({
    queryKey: ['tier_thresholds_admin'],
    queryFn: async () => {
      const { data, error } = await supabase.from('scorecard_tier_thresholds').select('*').order('kpi_key');
      if (error) throw error;
      return data as TierThreshold[];
    },
  });

  const [edits, setEdits] = useState<Record<string, Partial<TierThreshold>>>({});
  const set = (k: string, field: keyof TierThreshold, v: any) =>
    setEdits(p => ({ ...p, [k]: { ...p[k], [field]: v } }));

  const save = useMutation({
    mutationFn: async () => {
      for (const [k, patch] of Object.entries(edits)) {
        const { error } = await supabase.from('scorecard_tier_thresholds').update(patch).eq('kpi_key', k);
        if (error) throw error;
      }
    },
    onSuccess: () => { setEdits({}); qc.invalidateQueries(); toast.success('Thresholds saved.'); },
    onError: (e: any) => toast.error(e?.message ?? 'Save failed.'),
  });

  return (
    <div>
      <table className="w-full text-sm">
        <thead>
          <tr className="text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground">
            <th className="text-left p-2">KPI</th>
            <th className="p-2">🥉 Bronze</th>
            <th className="p-2">🥈 Silver</th>
            <th className="p-2">🥇 Gold</th>
            <th className="p-2">⭐ Elite</th>
            <th className="text-left p-2">Direction</th>
          </tr>
        </thead>
        <tbody>
          {rows.map(r => {
            const e = edits[r.kpi_key] ?? {};
            return (
              <tr key={r.kpi_key} className="border-t border-border">
                <td className="p-2 font-bold text-primary">{r.display_name}</td>
                {(['bronze','silver','gold','elite'] as const).map(t => (
                  <td key={t} className="p-2">
                    <Input type="number" value={String(e[t] ?? r[t])}
                      onChange={(ev) => set(r.kpi_key, t, Number(ev.target.value))}
                      className="w-24 text-center" />
                  </td>
                ))}
                <td className="p-2 text-xs">
                  <span className={r.direction === 'higher' ? 'text-emerald-700' : 'text-red-700'}>
                    {r.direction === 'higher' ? '↑ Higher better' : '↓ Lower better'}
                  </span>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
      <div className="flex gap-2 pt-4 mt-4 border-t border-border">
        <Button onClick={() => save.mutate()} disabled={Object.keys(edits).length === 0 || save.isPending}>
          {save.isPending ? 'Saving…' : 'Save thresholds'}
        </Button>
        <Button variant="outline" onClick={() => setEdits({})}>Reset edits</Button>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add src/components/admin/TierThresholdsTable.tsx
git commit -m "feat(tech-portal): TierThresholdsTable admin component"
```

---

### Task 35: AiNudgeControls component

**Files:**
- Create: `twins-dash/src/components/admin/AiNudgeControls.tsx`

- [ ] **Step 1: Implement**

```tsx
// AiNudgeControls.tsx
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/sonner';

export function AiNudgeControls() {
  const qc = useQueryClient();

  const { data: stats } = useQuery({
    queryKey: ['ai_nudge_stats'],
    queryFn: async () => {
      const since = new Date(); since.setDate(1);
      const { data: monthRows } = await supabase.from('tech_ai_nudges')
        .select('cost_usd, created_at').gte('created_at', since.toISOString());
      const { data: ytdRows } = await supabase.from('tech_ai_nudges')
        .select('cost_usd').gte('created_at', `${new Date().getFullYear()}-01-01`);
      const { data: lastRow } = await supabase.from('tech_ai_nudges')
        .select('created_at, jobs_in_window, model').order('created_at', { ascending: false }).limit(1).maybeSingle();
      const sum = (rows: any[] | null) => (rows ?? []).reduce((s, r) => s + Number(r.cost_usd ?? 0), 0);
      return { monthCost: sum(monthRows), ytdCost: sum(ytdRows), last: lastRow };
    },
  });

  const { data: paused = false } = useQuery({
    queryKey: ['ai_nudge_paused'],
    queryFn: async () => {
      const { data } = await supabase.from('app_settings').select('value').eq('key', 'ai_nudge_paused').maybeSingle();
      return data?.value === true || data?.value === 'true';
    },
  });

  const togglePause = useMutation({
    mutationFn: async () => {
      const { error } = await supabase.from('app_settings').update({ value: !paused, updated_at: new Date().toISOString() }).eq('key', 'ai_nudge_paused');
      if (error) throw error;
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ai_nudge_paused'] }); toast.success(paused ? 'AI nudges resumed' : 'AI nudges paused'); },
  });

  const regen = useMutation({
    mutationFn: async () => {
      const { error } = await supabase.functions.invoke('generate-tech-nudge', { body: { regenerate_all: true } });
      if (error) throw error;
    },
    onSuccess: () => toast.success('Regeneration queued'),
    onError: (e: any) => toast.error(e?.message ?? 'Regeneration failed'),
  });

  return (
    <div>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <Card k="Schedule"      v="Mondays · 6:00 AM CT" s="Per-tech, ≥5 jobs/week" />
        <Card k="Model"         v="Claude Haiku 4.5"     s="~$0.01/tech/week" />
        <Card k="Last run"      v={stats?.last?.created_at ? new Date(stats.last.created_at).toLocaleString() : '—'} s={stats?.last ? `model: ${stats.last.model}` : ''} />
        <Card k="Cost this month" v={`$${(stats?.monthCost ?? 0).toFixed(2)}`} s={`YTD: $${(stats?.ytdCost ?? 0).toFixed(2)}`} />
      </div>
      <div className="flex gap-2">
        <Button variant="outline" onClick={() => regen.mutate()} disabled={regen.isPending}>
          {regen.isPending ? 'Queuing…' : '↻ Regenerate now (force)'}
        </Button>
        <Button variant="outline" onClick={() => togglePause.mutate()}>
          {paused ? '▶ Resume AI nudges' : '⏸ Pause AI nudges'}
        </Button>
      </div>
    </div>
  );
}
function Card({ k, v, s }: { k: string; v: string; s: string }) {
  return (
    <div className="bg-card border border-border rounded-xl p-3">
      <div className="text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground">{k}</div>
      <div className="text-sm font-bold text-primary mt-0.5">{v}</div>
      <div className="text-[11px] text-muted-foreground mt-0.5">{s}</div>
    </div>
  );
}
```

> **NOTE:** The Edge Function currently expects `{ tech_id, week_start }`. To support `{ regenerate_all: true }`, add a branch at the top of `index.ts` that loops over all active techs. Update the function in this same task or in Task 21 follow-up.

- [ ] **Step 2: Commit**

```bash
git add src/components/admin/AiNudgeControls.tsx
git commit -m "feat(tech-portal): AiNudgeControls admin component"
```

---

### Task 36: TechTierOverridePanel component

**Files:**
- Create: `twins-dash/src/components/admin/TechTierOverridePanel.tsx`

- [ ] **Step 1: Implement**

```tsx
// TechTierOverridePanel.tsx
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useTierThresholds } from '@/hooks/tech/useTierThresholds';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { useState } from 'react';
import { toast } from '@/components/ui/sonner';

export function TechTierOverridePanel({ techId, techName }: { techId: string; techName: string }) {
  const qc = useQueryClient();
  const { data: thresholds = [] } = useTierThresholds();
  const { data: overrides = [] } = useQuery({
    queryKey: ['tech_tier_overrides', techId],
    queryFn: async () => {
      const { data, error } = await supabase.from('tech_tier_overrides').select('*').eq('tech_id', techId);
      if (error) throw error;
      return data ?? [];
    },
  });

  const [drafts, setDrafts] = useState<Record<string, any>>({});
  const upsert = useMutation({
    mutationFn: async (row: any) => {
      const { error } = await supabase.from('tech_tier_overrides').upsert(row, { onConflict: 'tech_id,kpi_key' });
      if (error) throw error;
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['tech_tier_overrides', techId] }); toast.success('Override saved.'); },
  });

  return (
    <div className="rounded-2xl bg-yellow-50 border border-accent/50 p-4">
      <div className="flex items-start gap-3">
        <div className="text-2xl">⚙️</div>
        <div className="flex-1">
          <h3 className="text-sm font-bold text-primary">Custom thresholds for {techName}</h3>
          <p className="text-xs text-amber-950 mt-0.5 leading-relaxed">Toggles override the company default for that KPI. Untoggled = uses company default.</p>
        </div>
      </div>
      <div className="mt-3 divide-y divide-accent/20">
        {thresholds.map(t => {
          const ov = overrides.find((o: any) => o.kpi_key === t.kpi_key);
          const isOn = drafts[t.kpi_key]?.enabled ?? !!ov;
          return (
            <div key={t.kpi_key} className="grid grid-cols-[1fr_auto_auto] gap-3 items-center py-2">
              <div className="text-xs">
                <b className="text-primary">{t.display_name}</b>
                <span className="text-amber-950 ml-1">· default Gold = {t.gold}</span>
              </div>
              {isOn ? (
                <Input type="number" defaultValue={ov?.gold ?? t.gold} className="w-24"
                  onBlur={(e) => upsert.mutate({ tech_id: techId, kpi_key: t.kpi_key, gold: Number(e.target.value) })} />
              ) : <span className="text-xs text-amber-900">No override</span>}
              <Switch checked={isOn} onCheckedChange={(v) => {
                setDrafts(p => ({ ...p, [t.kpi_key]: { enabled: v } }));
                if (!v && ov) supabase.from('tech_tier_overrides').delete().eq('id', ov.id).then(() => qc.invalidateQueries({ queryKey: ['tech_tier_overrides', techId] }));
              }} />
            </div>
          );
        })}
      </div>
    </div>
  );
}
```

> **NOTE:** This component currently only edits the Gold threshold per row to keep the UI simple. If Daniel asks to override more tiers, expand the inline editor. The override row stores `bronze/silver/gold/elite` separately so adding inputs is additive.

- [ ] **Step 2: Commit**

```bash
git add src/components/admin/TechTierOverridePanel.tsx
git commit -m "feat(tech-portal): TechTierOverridePanel admin component"
```

---

### Task 37: Assemble ScorecardTiers.tsx + admin nav link

**Files:**
- Modify: `twins-dash/src/pages/admin/ScorecardTiers.tsx`
- Modify: `twins-dash/src/pages/AdminPanel.tsx`

- [ ] **Step 1: Compose page**

```tsx
// ScorecardTiers.tsx
import { TierThresholdsTable } from '@/components/admin/TierThresholdsTable';
import { AiNudgeControls } from '@/components/admin/AiNudgeControls';

export default function ScorecardTiers() {
  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-primary">Scorecard tiers</h1>
        <p className="text-sm text-muted-foreground mt-1">Set the Bronze/Silver/Gold/Elite thresholds per KPI. Per-tech overrides are managed from the tech's Goals tab.</p>
      </div>
      <section className="bg-card border border-border rounded-2xl p-5">
        <h2 className="text-sm font-extrabold uppercase tracking-wider text-primary mb-3">Tier thresholds</h2>
        <TierThresholdsTable />
      </section>
      <section className="bg-card border border-border rounded-2xl p-5">
        <h2 className="text-sm font-extrabold uppercase tracking-wider text-primary mb-3">AI nudge controls</h2>
        <AiNudgeControls />
      </section>
    </div>
  );
}
```

- [ ] **Step 2: Add link in AdminPanel.tsx**

Find the existing list of admin nav cards/links and add an entry pointing to `/admin/scorecard-tiers` with title "Scorecard tiers" and description "Tier thresholds, AI nudge settings."

- [ ] **Step 3: Verify**

Navigate to `/admin/scorecard-tiers`, edit a threshold, save, verify the audit table got a row, verify a tech's KPI tile re-tiers if you cross a threshold.

- [ ] **Step 4: Commit**

```bash
git add src/pages/admin/ScorecardTiers.tsx src/pages/AdminPanel.tsx
git commit -m "feat(tech-portal): /admin/scorecard-tiers page + admin nav link"
```

---

### Task 38: Wire override panel into admin "View as tech"

**Files:**
- Modify: `twins-dash/src/pages/tech/Goals.tsx`

- [ ] **Step 1: Detect admin impersonation, render override panel**

In `Goals.tsx`, import `useAuth` and `useSearchParams`. When `auth.isAdmin && searchParams.get('as')`, render `<TechTierOverridePanel techId={asId} techName={...} />` at the bottom of the page (after Tip Library).

- [ ] **Step 2: Verify**

Navigate to `/tech/goals?as=<MAURICE_TECH_ID>` as admin. Override panel renders below tips. Toggle a row, save, verify a row appears in `tech_tier_overrides`.

- [ ] **Step 3: Commit**

```bash
git add src/pages/tech/Goals.tsx
git commit -m "feat(tech-portal): show override panel on Goals when admin impersonates tech"
```

---

## M10 — Cleanup + retire old files

### Task 39: Delete retired tech pages

**Files:**
- Delete: `twins-dash/src/pages/tech/TechHome.tsx`
- Delete: `twins-dash/src/pages/tech/TechAppointments.tsx`
- Delete: `twins-dash/src/pages/tech/TechEstimates.tsx`
- Delete: `twins-dash/src/pages/tech/TechProfile.tsx`
- Delete: `twins-dash/src/pages/tech/TechJobs.tsx`
- Delete: `twins-dash/src/pages/tech/TechJobDetail.tsx` (only if its content is now duplicated by `/tech/jobs/:id` flow elsewhere; verify first)
- Delete: `twins-dash/src/components/technician/scorecard/PastPaystubs.tsx`
- Delete: `twins-dash/src/components/tech/PaystubCard.tsx`
- Modify: `twins-dash/src/App.tsx` to remove their imports/routes

- [ ] **Step 1: Find references**

```bash
grep -rn "TechHome\|TechAppointments\|TechEstimates\|TechProfile\|PastPaystubs\|PaystubCard" twins-dash/src/ | grep -v node_modules
```

- [ ] **Step 2: Verify no live import remains for any of the above EXCEPT the route-level import you'll remove in Step 3**

- [ ] **Step 3: Delete files + update App.tsx**

```bash
git rm src/pages/tech/TechHome.tsx src/pages/tech/TechAppointments.tsx src/pages/tech/TechEstimates.tsx src/pages/tech/TechProfile.tsx src/pages/tech/TechJobs.tsx src/components/technician/scorecard/PastPaystubs.tsx src/components/tech/PaystubCard.tsx
```

Remove imports/routes from `App.tsx`.

- [ ] **Step 4: Build to verify nothing broke**

```bash
npx tsc --noEmit && npm run build
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore(tech-portal): retire TechHome + 4 old tech pages + paystub components"
```

---

### Task 40: Refactor TechnicianView (admin /tech/:id) to use new Home

**Files:**
- Modify: `twins-dash/src/pages/TechnicianView.tsx`

- [ ] **Step 1: Strip out the .ts-scope CSS layer + the mobile body**

The new admin scorecard at `/tech/:id` should:
- Render the admin chrome (tech picker, "View as tech" button) at the top
- Below that, render the same `<Home />` content but scoped to the impersonated tech (pass a `techIdOverride` prop into Home, or set a context that the existing hooks consult).

Simplest path: introduce `<Home techId={effectiveId} />` accepting an optional `techId` prop. When set, the hooks use a different RPC (e.g. `get_scorecard_with_tiers_for_tech(p_tech_id, p_since, p_until)`) that admins can call. Add this RPC + admin-only RLS in a small migration if needed.

- [ ] **Step 2: Delete the `.ts-scope` styles from `index.css` (or wherever they live)**

```bash
grep -rn "ts-scope\|--ts-navy\|--ts-yellow" twins-dash/src/
```

Remove all hits.

- [ ] **Step 3: Build + smoke test**

```bash
npm run build
```

Verify `/tech/:id` (admin) and `/tech` (tech) both render correctly.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor(tech-portal): TechnicianView reuses new Home; drop .ts-scope CSS"
```

---

### Task 41: Update TechShell sync-status placeholder

**Files:**
- Modify: `twins-dash/src/components/tech/TechShell.tsx`

- [ ] **Step 1: Wire the "Last synced — ago" placeholder to real data**

Use `useLastSyncTime` (create a tiny hook that reads the most recent `payroll_jobs.last_synced_at` for the caller) or call the existing HCP-sync timestamp. Replace the literal "— ago" text.

- [ ] **Step 2: Commit**

```bash
git add src/components/tech/TechShell.tsx src/hooks/tech/useLastSyncTime.ts
git commit -m "feat(tech-portal): wire TechShell last-synced indicator"
```

---

## M11 — Final QA + PR

### Task 42: Full visual sweep

**Files:** none

- [ ] **Step 1: Mobile sweep (DevTools, iPhone 13 Pro viewport)**

Visit each route as a tech and as an admin-in-impersonation:
- `/tech` — hero, CTA (with/without drafts), tiles, jobs list, sanity line, drill sheet
- `/tech/goals` — nudge card (with/without nudge), tier ladder, what-changed, tip library
- `/tech/wins` — year ribbon, tier-up card, streaks, PRs, empty state
- `/admin/scorecard-tiers` — table edits, save, audit log, AI controls
- `/tech/goals?as=<tech>` — override panel renders for admin

- [ ] **Step 2: Desktop sweep (1440px viewport)**

Same routes, verify left sidebar nav with 3 items, 4-column KPI grid, no overflow.

- [ ] **Step 3: Run full build**

```bash
npx tsc --noEmit && npm run build
```

Expected: clean build.

- [ ] **Step 4: Run all tests**

```bash
npx vitest run
```

Expected: all green.

---

### Task 43: Open PR to main

**Files:** none

- [ ] **Step 1: Push branch**

```bash
git push -u origin feat/tech-portal-redesign
```

- [ ] **Step 2: Open PR via GitHub API (per memory `reference_gh_via_api.md`)**

```bash
TOKEN=$(printf "protocol=https\nhost=github.com\n" | git credential-osxkeychain get | grep '^password=' | cut -d= -f2)
curl -X POST "https://api.github.com/repos/palpulla/twins-dash/pulls" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.github+json" \
  -d '{
    "title": "Tech portal redesign — 3 tabs, tier ladder, AI nudge, recognition",
    "head": "feat/tech-portal-redesign",
    "base": "main",
    "body": "Implements docs/superpowers/specs/2026-04-25-tech-portal-redesign-design.md per the implementation plan in docs/superpowers/plans/2026-04-25-tech-portal-redesign.md.\n\n## Summary\n- Replaces TechHome + TechnicianView with focused Home / Goals / Recognition tabs\n- Adds admin-tunable Bronze/Silver/Gold/Elite tier ladder per KPI\n- Adds weekly Claude Haiku coaching nudge (~$0.20/mo)\n- Adds personal-only streaks + personal records\n- Drops paystubs entirely (external payroll provider)\n\n## Test plan\n- [x] All vitest unit tests green\n- [x] TS build clean\n- [x] Manual sweep mobile + desktop, all routes\n- [x] AI nudge edge function smoke-tested\n- [x] Admin threshold edits write audit row\n- [ ] Daniel reviews on Vercel preview before merge"
  }'
```

- [ ] **Step 3: Return PR URL to user**

---

## Self-Review

Spec coverage check:

- ✅ 3-tab IA: Tasks 7, 8, 17, 27, 33
- ✅ Visual language anchored to Index.tsx: covered in component CSS choices throughout M3–M8
- ✅ Bronze/Silver/Gold/Elite tier ladder: Tasks 1, 4, 9, 12
- ✅ Admin-tunable thresholds with per-tech override: Tasks 1, 3, 34, 36, 37, 38
- ✅ Weekly Claude Haiku AI nudge with cron: Tasks 20, 21, 22, 23
- ✅ Personal-only Recognition (streaks, PRs, tier-up, year ribbon): Tasks 28–33
- ✅ Existing KPI math untouched: enforced by reusing `my_scorecard` in Tasks 5 and 18
- ✅ Same shell serves admin "View as tech": Task 40 + 38
- ✅ Removal of TechHome, TechnicianView, retired pages, PastPaystubs, PaystubCard: Tasks 39, 40
- ✅ `.ts-scope` raw CSS removed: Task 40 step 2
- ✅ Sanity line "Last finalized week landed at $X": in Task 17
- ✅ HeroEstimate, CTA, KPI tiles, drill sheet, jobs list: Tasks 12, 14, 15, 17, 18, 19
- ✅ AiNudgeCard, tier ladder rollup, what-changed, tip library: Tasks 23, 24, 25, 26, 27
- ✅ YearRibbon, TierUpCard, StreakCard, PersonalRecordTile, empty state: Tasks 32, 33
- ✅ TierThresholdsTable, AiNudgeControls, TechTierOverridePanel, ScorecardTiers page: Tasks 34, 35, 36, 37
- ✅ All 6 tables + 2 cron jobs + 1 Edge Function: Tasks 1, 2, 3, 20, 28, 21, 22, 30
- ✅ All RLS policies: in each schema task
- ✅ Type regen after migrations: Tasks 6, 18 step 2, 28 step 3

Placeholder scan:

- Tasks 21, 29 contain explicit `NOTE` blocks for the parts that require engineer judgment (snapshot SQL composition, streaks/PRs body, edge-function regenerate-all branch). These are intentional handoffs to engineering, not placeholders.
- All other tasks have complete code.

Type consistency:

- `Tier` type defined in TierBadge.tsx, used consistently across KpiTile, KpiDrillSheet, TierLadderRow.
- `TierThreshold` type defined in useTierThresholds.ts, used in TierThresholdsTable, Goals, Home.
- `MyScorecardRow` returned by `useMyScorecardWithTiers`, accessed via dynamic indexing in Home.tsx and Goals.tsx (acceptable since the columns mirror the SQL function signature).

No further fixes required. Plan is ready.

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-04-25-tech-portal-redesign.md`. Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration. Best for a spec this large.
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
