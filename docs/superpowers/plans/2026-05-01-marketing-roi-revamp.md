# Marketing ROI Revamp — Implementation Plan (Phase 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor `twins-dash/src/pages/MarketingSourceROI.tsx` into a five-section page (filter bar → stoplight hero → channel scorecards → funnel + live feed → all-sources table) using only data already in Supabase.

**Architecture:** Pure-function utilities + React Query hooks + presentational components composed by a thin page. Legacy page preserved at `?legacy=1` for 30-day rollback. No KPI math changes — hero/cards/table reconcile to the same per-source aggregation enforced by an integration test.

**Tech Stack:** React 18, TypeScript, Vite, Vitest 4, @testing-library/react, TanStack Query, Tailwind, shadcn/ui, Supabase JS client.

**Spec:** `docs/superpowers/specs/2026-05-01-marketing-roi-revamp-design.md`

**Repo:** `twins-dash` (palpulla/twins-dash). All paths below are relative to `twins-dash/`.

---

## Pre-flight

- [ ] **Step 1: Create feature branch**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
git checkout -b feat/marketing-roi-revamp
```

- [ ] **Step 2: Verify tests run cleanly on baseline**

```bash
npm test -- --run
```

Expected: existing tests pass. If anything is broken, stop and fix the baseline before continuing.

- [ ] **Step 3: Confirm `calls_inbound` schema matches spec assumption**

```bash
grep -A 16 "calls_inbound:" src/integrations/supabase/types.ts | head -20
```

Expected output should show columns: `id, date, source, phone_number, is_booked, is_lead_opportunity, duration, created_at, updated_at`. Note that `responded_at` does **not** exist — speed-to-lead is dropped from Phase 1's Live tile per the spec's open-question resolution.

---

## Task 1: URL state utility

**Files:**
- Create: `src/lib/marketing-roi/url-state.ts`
- Test: `src/lib/marketing-roi/__tests__/url-state.test.ts`

Encodes filter state (period, from, to, jobType, tech) to a URL query string and back. Default state (MTD, no filters) produces an empty query string.

- [ ] **Step 1: Write the failing test**

Create `src/lib/marketing-roi/__tests__/url-state.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { encodeFilterState, decodeFilterState, type FilterState } from "../url-state";

describe("url-state", () => {
  it("round-trips MTD default to empty query", () => {
    const state: FilterState = { period: "mtd", jobType: "all", tech: "all" };
    expect(encodeFilterState(state)).toBe("");
    expect(decodeFilterState("")).toEqual(state);
  });

  it("encodes Today period", () => {
    expect(encodeFilterState({ period: "today", jobType: "all", tech: "all" })).toBe("period=today");
  });

  it("encodes job-type and tech filters", () => {
    const q = encodeFilterState({ period: "mtd", jobType: "repair", tech: "abc-123" });
    expect(q).toContain("jobType=repair");
    expect(q).toContain("tech=abc-123");
  });

  it("encodes custom range with from/to", () => {
    const q = encodeFilterState({
      period: "custom", jobType: "all", tech: "all",
      from: "2026-04-01", to: "2026-04-15",
    });
    expect(q).toBe("period=custom&from=2026-04-01&to=2026-04-15");
  });

  it("decodes malformed query to defaults", () => {
    expect(decodeFilterState("garbage=xxx")).toEqual({ period: "mtd", jobType: "all", tech: "all" });
  });

  it("decodes custom range", () => {
    const s = decodeFilterState("period=custom&from=2026-04-01&to=2026-04-15");
    expect(s.period).toBe("custom");
    expect(s.from).toBe("2026-04-01");
    expect(s.to).toBe("2026-04-15");
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
npm test -- --run src/lib/marketing-roi/__tests__/url-state.test.ts
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Write minimal implementation**

Create `src/lib/marketing-roi/url-state.ts`:

```ts
export type Period = "today" | "wtd" | "mtd" | "qtd" | "ytd" | "custom";

export interface FilterState {
  period: Period;
  jobType: "all" | "repair" | "installation";
  tech: string;
  from?: string;
  to?: string;
}

const VALID_PERIODS: Period[] = ["today", "wtd", "mtd", "qtd", "ytd", "custom"];
const VALID_JOB_TYPES = ["all", "repair", "installation"] as const;
const DEFAULTS: FilterState = { period: "mtd", jobType: "all", tech: "all" };

export function encodeFilterState(state: FilterState): string {
  const parts: string[] = [];
  if (state.period !== "mtd") parts.push(`period=${state.period}`);
  if (state.period === "custom") {
    if (state.from) parts.push(`from=${state.from}`);
    if (state.to) parts.push(`to=${state.to}`);
  }
  if (state.jobType !== "all") parts.push(`jobType=${state.jobType}`);
  if (state.tech !== "all") parts.push(`tech=${encodeURIComponent(state.tech)}`);
  return parts.join("&");
}

export function decodeFilterState(query: string): FilterState {
  const params = new URLSearchParams(query.startsWith("?") ? query.slice(1) : query);
  const period = params.get("period") as Period | null;
  const jobType = params.get("jobType") as FilterState["jobType"] | null;
  return {
    period: period && VALID_PERIODS.includes(period) ? period : DEFAULTS.period,
    jobType: jobType && (VALID_JOB_TYPES as readonly string[]).includes(jobType) ? jobType : DEFAULTS.jobType,
    tech: params.get("tech") ?? DEFAULTS.tech,
    from: params.get("from") ?? undefined,
    to: params.get("to") ?? undefined,
  };
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
npm test -- --run src/lib/marketing-roi/__tests__/url-state.test.ts
```

Expected: 6 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/lib/marketing-roi/url-state.ts src/lib/marketing-roi/__tests__/url-state.test.ts
git commit -m "feat(marketing-roi): url-state encode/decode for filter persistence"
```

---

## Task 2: Ribbon ranking utility

**Files:**
- Create: `src/lib/marketing-roi/ribbon-ranking.ts`
- Test: `src/lib/marketing-roi/__tests__/ribbon-ranking.test.ts`

Pure function. Given an array of channel metrics + their prior-period revenue, returns a `Map<canonicalSource, Ribbon>` assigning at most one ribbon per channel:
- **gold** = "TOP ROI" — highest ROI ratio, requires `adSpend > 500` (no rewarding low-spend flukes)
- **silver** = "RISING" — highest period-over-period revenue growth, requires `revenue > 1000`
- **bronze** = "VOLUME LEAD" — highest opportunity count, requires `opportunities >= 10`

A channel that wins multiple categories keeps gold (highest priority). Ties broken by revenue then alphabetically.

- [ ] **Step 1: Write the failing test**

Create `src/lib/marketing-roi/__tests__/ribbon-ranking.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { rankRibbons, type RibbonInput } from "../ribbon-ranking";

const make = (overrides: Partial<RibbonInput> & { source: string }): RibbonInput => ({
  revenue: 0, adSpend: 0, opportunities: 0, priorRevenue: 0, ...overrides,
});

describe("rankRibbons", () => {
  it("assigns gold to highest ROI when adSpend threshold met", () => {
    const ribbons = rankRibbons([
      make({ source: "Google Ads", revenue: 17000, adSpend: 4000, opportunities: 38 }),
      make({ source: "Meta Ads", revenue: 6000, adSpend: 2000, opportunities: 22 }),
    ]);
    expect(ribbons.get("Google Ads")).toEqual({ tier: "gold", criterion: "TOP ROI" });
  });

  it("does not award gold to a channel with adSpend below $500", () => {
    const ribbons = rankRibbons([
      make({ source: "FlukeAd", revenue: 5000, adSpend: 100, opportunities: 1 }),
      make({ source: "Google Ads", revenue: 17000, adSpend: 4000, opportunities: 38 }),
    ]);
    expect(ribbons.get("FlukeAd")).toBeUndefined();
    expect(ribbons.get("Google Ads")).toEqual({ tier: "gold", criterion: "TOP ROI" });
  });

  it("assigns silver to highest revenue growth", () => {
    const ribbons = rankRibbons([
      make({ source: "Meta Ads", revenue: 6000, priorRevenue: 3000, adSpend: 2000, opportunities: 22 }),
      make({ source: "Google Ads", revenue: 17000, priorRevenue: 16000, adSpend: 4000, opportunities: 38 }),
    ]);
    expect(ribbons.get("Meta Ads")).toEqual({ tier: "silver", criterion: "RISING" });
  });

  it("assigns bronze to highest opportunities", () => {
    const ribbons = rankRibbons([
      make({ source: "GHL", revenue: 18000, adSpend: 0, opportunities: 87, priorRevenue: 16000 }),
      make({ source: "Google Ads", revenue: 17000, adSpend: 4000, opportunities: 38, priorRevenue: 12000 }),
      make({ source: "Meta Ads", revenue: 6000, adSpend: 2000, opportunities: 22, priorRevenue: 5500 }),
    ]);
    // Google Ads wins gold (highest ROI), GHL wins bronze (highest opps), Google Ads also has highest growth so silver goes to next-best growth (Meta Ads)
    expect(ribbons.get("Google Ads")).toEqual({ tier: "gold", criterion: "TOP ROI" });
    expect(ribbons.get("GHL")).toEqual({ tier: "bronze", criterion: "VOLUME LEAD" });
  });

  it("a single channel wins gold and gets only gold", () => {
    const ribbons = rankRibbons([
      make({ source: "Google Ads", revenue: 17000, priorRevenue: 12000, adSpend: 4000, opportunities: 38 }),
    ]);
    expect(ribbons.get("Google Ads")).toEqual({ tier: "gold", criterion: "TOP ROI" });
    expect(ribbons.size).toBe(1);
  });

  it("empty input returns empty map", () => {
    expect(rankRibbons([]).size).toBe(0);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
npm test -- --run src/lib/marketing-roi/__tests__/ribbon-ranking.test.ts
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Write minimal implementation**

Create `src/lib/marketing-roi/ribbon-ranking.ts`:

```ts
export interface RibbonInput {
  source: string;
  revenue: number;
  adSpend: number;
  opportunities: number;
  priorRevenue: number;
}

export type RibbonTier = "gold" | "silver" | "bronze";
export interface Ribbon {
  tier: RibbonTier;
  criterion: "TOP ROI" | "RISING" | "VOLUME LEAD";
}

const GOLD_MIN_SPEND = 500;
const SILVER_MIN_REVENUE = 1000;
const BRONZE_MIN_OPPS = 10;

export function rankRibbons(channels: RibbonInput[]): Map<string, Ribbon> {
  const out = new Map<string, Ribbon>();
  if (channels.length === 0) return out;

  // Gold: highest ROI ratio with adSpend >= threshold
  const goldEligible = channels
    .filter(c => c.adSpend >= GOLD_MIN_SPEND)
    .map(c => ({ source: c.source, score: c.revenue / c.adSpend, revenue: c.revenue }))
    .sort((a, b) => b.score - a.score || b.revenue - a.revenue || a.source.localeCompare(b.source));
  if (goldEligible.length > 0) {
    out.set(goldEligible[0].source, { tier: "gold", criterion: "TOP ROI" });
  }

  // Silver: highest growth, excluding gold winner, requires revenue >= threshold
  const silverEligible = channels
    .filter(c => !out.has(c.source) && c.revenue >= SILVER_MIN_REVENUE && c.priorRevenue > 0)
    .map(c => ({ source: c.source, score: (c.revenue - c.priorRevenue) / c.priorRevenue, revenue: c.revenue }))
    .sort((a, b) => b.score - a.score || b.revenue - a.revenue || a.source.localeCompare(b.source));
  if (silverEligible.length > 0 && silverEligible[0].score > 0) {
    out.set(silverEligible[0].source, { tier: "silver", criterion: "RISING" });
  }

  // Bronze: highest opportunities, excluding gold/silver winners, requires opps >= threshold
  const bronzeEligible = channels
    .filter(c => !out.has(c.source) && c.opportunities >= BRONZE_MIN_OPPS)
    .sort((a, b) => b.opportunities - a.opportunities || b.revenue - a.revenue || a.source.localeCompare(b.source));
  if (bronzeEligible.length > 0) {
    out.set(bronzeEligible[0].source, { tier: "bronze", criterion: "VOLUME LEAD" });
  }

  return out;
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
npm test -- --run src/lib/marketing-roi/__tests__/ribbon-ranking.test.ts
```

Expected: 6 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/lib/marketing-roi/ribbon-ranking.ts src/lib/marketing-roi/__tests__/ribbon-ranking.test.ts
git commit -m "feat(marketing-roi): ribbon ranking (gold/silver/bronze) for channel cards"
```

---

## Task 3: Drop-off alerts utility

**Files:**
- Create: `src/lib/marketing-roi/drop-off-alerts.ts`
- Test: `src/lib/marketing-roi/__tests__/drop-off-alerts.test.ts`

Given funnel data per (source, step), surface alerts where a channel's conversion at a given step is more than 20 percentage points below the all-channel average for that step **and** the channel has at least 10 leads in the period.

The four hand-written hints from the spec:

| step | hint |
|---|---|
| `lead_to_booked` (Meta) | "Lower-intent traffic. Tighten audience targeting or refresh ad creative." |
| `lead_to_booked` (Google) | "Booking ratio low. Check landing page speed or call-tracking failures." |
| `booked_to_completed` (GHL) | "Customers booking but not showing up. Confirm appointment-reminder workflow." |
| `completed_to_paid` (any) | "Completed jobs not closing payment. Check invoice send delay or follow-up cadence." |

Hints map to (step, source) pairs; if no specific match, the alert renders without a hint line.

- [ ] **Step 1: Write the failing test**

Create `src/lib/marketing-roi/__tests__/drop-off-alerts.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { computeDropOffAlerts, type FunnelData } from "../drop-off-alerts";

const fd = (overrides: Partial<FunnelData["bySource"]["x"]> & { source: string; leads: number }) => ({
  leads: overrides.leads,
  booked: overrides.booked ?? 0,
  completed: overrides.completed ?? 0,
  paid: overrides.paid ?? 0,
  source: overrides.source,
});

describe("computeDropOffAlerts", () => {
  it("flags Meta lead_to_booked when 20+ pp below avg with sufficient leads", () => {
    const data: FunnelData = {
      bySource: {
        "Meta Ads": fd({ source: "Meta Ads", leads: 22, booked: 8 }),       // 36%
        "Google Ads": fd({ source: "Google Ads", leads: 38, booked: 23 }),  // 61%
      },
    };
    const alerts = computeDropOffAlerts(data);
    expect(alerts).toHaveLength(1);
    expect(alerts[0].source).toBe("Meta Ads");
    expect(alerts[0].step).toBe("lead_to_booked");
    expect(alerts[0].channelPct).toBeCloseTo(0.36, 2);
    expect(alerts[0].hint).toContain("Tighten audience");
  });

  it("does not alert when sample is below 10 leads", () => {
    const data: FunnelData = {
      bySource: {
        "Tiny": fd({ source: "Tiny", leads: 5, booked: 1 }),
        "Big":  fd({ source: "Big", leads: 50, booked: 30 }),
      },
    };
    expect(computeDropOffAlerts(data)).toHaveLength(0);
  });

  it("does not alert when channel matches average", () => {
    const data: FunnelData = {
      bySource: {
        "A": fd({ source: "A", leads: 20, booked: 12 }),
        "B": fd({ source: "B", leads: 20, booked: 12 }),
      },
    };
    expect(computeDropOffAlerts(data)).toHaveLength(0);
  });

  it("emits completed_to_paid alert with generic hint", () => {
    const data: FunnelData = {
      bySource: {
        "A": fd({ source: "A", leads: 30, booked: 20, completed: 18, paid: 5 }),
        "B": fd({ source: "B", leads: 30, booked: 20, completed: 18, paid: 17 }),
      },
    };
    const alerts = computeDropOffAlerts(data);
    expect(alerts.find(a => a.step === "completed_to_paid")?.hint).toContain("Completed jobs not closing");
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
npm test -- --run src/lib/marketing-roi/__tests__/drop-off-alerts.test.ts
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Write minimal implementation**

Create `src/lib/marketing-roi/drop-off-alerts.ts`:

```ts
export type FunnelStep = "lead_to_booked" | "booked_to_completed" | "completed_to_paid";

export interface FunnelData {
  bySource: Record<string, {
    source: string;
    leads: number;
    booked: number;
    completed: number;
    paid: number;
  }>;
}

export interface DropOffAlert {
  source: string;
  step: FunnelStep;
  channelPct: number;
  averagePct: number;
  hint?: string;
}

const MIN_LEADS_FOR_ALERT = 10;
const DROP_THRESHOLD_PP = 0.20; // 20 percentage points

const HINTS: Record<string, string> = {
  "lead_to_booked|Meta Ads":
    "Lower-intent traffic. Tighten audience targeting or refresh ad creative.",
  "lead_to_booked|Google Ads":
    "Booking ratio low. Check landing page speed or call-tracking failures.",
  "booked_to_completed|GHL":
    "Customers booking but not showing up. Confirm appointment-reminder workflow.",
  "completed_to_paid|*":
    "Completed jobs not closing payment. Check invoice send delay or follow-up cadence.",
};

function ratio(num: number, denom: number): number {
  return denom > 0 ? num / denom : 0;
}

function stepNumDenom(step: FunnelStep, src: FunnelData["bySource"][string]) {
  switch (step) {
    case "lead_to_booked":      return { num: src.booked, denom: src.leads };
    case "booked_to_completed": return { num: src.completed, denom: src.booked };
    case "completed_to_paid":   return { num: src.paid, denom: src.completed };
  }
}

export function computeDropOffAlerts(data: FunnelData): DropOffAlert[] {
  const sources = Object.values(data.bySource);
  const alerts: DropOffAlert[] = [];
  const steps: FunnelStep[] = ["lead_to_booked", "booked_to_completed", "completed_to_paid"];

  for (const step of steps) {
    let totalNum = 0, totalDen = 0;
    for (const s of sources) {
      const { num, denom } = stepNumDenom(step, s);
      totalNum += num; totalDen += denom;
    }
    const avg = ratio(totalNum, totalDen);
    if (avg === 0) continue;

    for (const s of sources) {
      if (s.leads < MIN_LEADS_FOR_ALERT) continue;
      const { num, denom } = stepNumDenom(step, s);
      if (denom === 0) continue;
      const pct = ratio(num, denom);
      if (avg - pct >= DROP_THRESHOLD_PP) {
        const specific = HINTS[`${step}|${s.source}`];
        const generic = HINTS[`${step}|*`];
        alerts.push({
          source: s.source, step, channelPct: pct, averagePct: avg,
          hint: specific || generic,
        });
      }
    }
  }

  return alerts;
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
npm test -- --run src/lib/marketing-roi/__tests__/drop-off-alerts.test.ts
```

Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/lib/marketing-roi/drop-off-alerts.ts src/lib/marketing-roi/__tests__/drop-off-alerts.test.ts
git commit -m "feat(marketing-roi): drop-off alerts with four hand-written hints"
```

---

## Task 4: Add `roi_target` goal default

**Files:**
- Modify: `src/hooks/use-company-goals.ts`

Add a new goal key `roi_target` (default 3.0) so the Stoplight Hero ROI tile has a target. No schema change — the hook already returns hard-coded defaults.

- [ ] **Step 1: Read current defaults**

```bash
grep -n "DEFAULT_GOALS" src/hooks/use-company-goals.ts
```

- [ ] **Step 2: Add `roi_target` to the defaults array**

In `src/hooks/use-company-goals.ts`, in the `DEFAULT_GOALS` array, add a new entry:

```ts
{ id: "4", metric_key: "roi_target", target_value: 3.0, label: "ROI Target", period: "monthly", updated_at: new Date().toISOString() },
```

So the array becomes:

```ts
const DEFAULT_GOALS: CompanyGoal[] = [
  { id: "1", metric_key: "opportunity_average", target_value: 1000, label: "Opportunity Average", period: "weekly", updated_at: new Date().toISOString() },
  { id: "2", metric_key: "conversion_rate", target_value: 80, label: "Conversion Rate", period: "weekly", updated_at: new Date().toISOString() },
  { id: "3", metric_key: "membership_rate", target_value: 20, label: "Membership Rate", period: "weekly", updated_at: new Date().toISOString() },
  { id: "4", metric_key: "roi_target", target_value: 3.0, label: "ROI Target", period: "monthly", updated_at: new Date().toISOString() },
];
```

- [ ] **Step 3: Verify nothing breaks**

```bash
npm test -- --run
```

Expected: existing tests pass.

- [ ] **Step 4: Commit**

```bash
git add src/hooks/use-company-goals.ts
git commit -m "feat(goals): add roi_target default (3.0) for marketing-roi hero"
```

---

## Task 5: useStoplightMetrics hook

**Files:**
- Create: `src/hooks/use-stoplight-metrics.ts`
- Test: `src/hooks/__tests__/use-stoplight-metrics.test.tsx`

Composes goal + revenue + spend + live counts into the three tile inputs (ROI, Pacing, Live). Pure function under the hood; the React-Query wrapper is thin.

The hook takes:
- `currentTotals: { totalRevenue, totalAdSpend }` — from `useMarketingSourceROI`
- `priorTotals: { totalRevenue }` — same hook for prior period
- `dateRange: DateRange | undefined`
- `liveCounts: { callsToday, bookedToday, lastCallAt }` — from `useLiveLeadFeed`
- `goalRevenue: number | null`
- `goalRoi: number | null` (default 3.0)

And returns three tile descriptors with `lab`, `big`, `sub`, `ctx`, `delta`, `tone`.

- [ ] **Step 1: Write the failing test**

Create `src/hooks/__tests__/use-stoplight-metrics.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { computeStoplightTiles } from "../use-stoplight-metrics";

const baseInput = {
  currentTotals: { totalRevenue: 32820, totalAdSpend: 9610 },
  priorTotals: { totalRevenue: 28000 },
  goalRevenue: 50000,
  goalRoi: 3.0,
  liveCounts: { callsToday: 14, bookedToday: 8, lastCallAt: new Date(Date.now() - 14 * 60_000) },
  daysElapsed: 18,
  daysInPeriod: 30,
  asOf: new Date(),
};

describe("computeStoplightTiles", () => {
  it("ROI tile is green when ROI >= goal", () => {
    const tiles = computeStoplightTiles(baseInput);
    expect(tiles.roi.tone).toBe("good");
    expect(tiles.roi.big).toBe("3.4×");
  });

  it("ROI tile is red when ROI is far below goal", () => {
    const tiles = computeStoplightTiles({
      ...baseInput,
      currentTotals: { totalRevenue: 5000, totalAdSpend: 5000 },
    });
    expect(tiles.roi.tone).toBe("poor");
    expect(tiles.roi.big).toBe("1.0×");
  });

  it("ROI tile shows '—' when spend is 0", () => {
    const tiles = computeStoplightTiles({
      ...baseInput,
      currentTotals: { totalRevenue: 5000, totalAdSpend: 0 },
    });
    expect(tiles.roi.big).toBe("—");
    expect(tiles.roi.tone).toBe("neutral");
  });

  it("Pacing tile is green when running rate >= required", () => {
    const tiles = computeStoplightTiles(baseInput);
    expect(tiles.pacing.tone).toMatch(/good|ok/);
    expect(tiles.pacing.big).toContain("66%");
  });

  it("Pacing tile shows neutral when goal is missing", () => {
    const tiles = computeStoplightTiles({ ...baseInput, goalRevenue: null });
    expect(tiles.pacing.tone).toBe("neutral");
  });

  it("Live tile is amber when last call was 2-4 hours ago", () => {
    const tiles = computeStoplightTiles({
      ...baseInput,
      liveCounts: { callsToday: 2, bookedToday: 1, lastCallAt: new Date(Date.now() - 3 * 3600 * 1000) },
    });
    expect(tiles.live.tone).toBe("ok");
  });

  it("delta vs prior is set when prior > 0", () => {
    const tiles = computeStoplightTiles(baseInput);
    expect(tiles.roi.delta).not.toBeNull();
  });

  it("delta is null when prior is 0", () => {
    const tiles = computeStoplightTiles({ ...baseInput, priorTotals: { totalRevenue: 0 } });
    expect(tiles.roi.delta).toBeNull();
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
npm test -- --run src/hooks/__tests__/use-stoplight-metrics.test.tsx
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Write minimal implementation**

Create `src/hooks/use-stoplight-metrics.ts`:

```ts
export type Tone = "good" | "ok" | "poor" | "neutral";

export interface StoplightTile {
  lab: string;
  big: string;
  sub: string;
  ctx: string;
  delta: { label: string; tone: "up" | "down" | "flat" } | null;
  tone: Tone;
}

export interface StoplightInput {
  currentTotals: { totalRevenue: number; totalAdSpend: number };
  priorTotals: { totalRevenue: number };
  goalRevenue: number | null;
  goalRoi: number | null;
  liveCounts: { callsToday: number; bookedToday: number; lastCallAt: Date | null };
  daysElapsed: number;
  daysInPeriod: number;
  asOf: Date;
}

const fmtMoney = (n: number) =>
  n >= 1000 ? `$${(n / 1000).toFixed(1)}k` : `$${Math.round(n).toLocaleString()}`;

function computeRoi(input: StoplightInput): StoplightTile {
  const { currentTotals, priorTotals, goalRoi } = input;
  const goal = goalRoi ?? 3.0;
  if (currentTotals.totalAdSpend <= 0) {
    return {
      lab: "ROI · Period",
      big: "—",
      sub: `${fmtMoney(currentTotals.totalRevenue)} revenue · no ad spend`,
      ctx: "Connect a paid channel to see ROI",
      delta: null,
      tone: "neutral",
    };
  }
  const roi = currentTotals.totalRevenue / currentTotals.totalAdSpend;
  const tone: Tone = roi >= goal ? "good" : roi >= goal * 0.8 ? "ok" : "poor";

  let delta: StoplightTile["delta"] = null;
  if (priorTotals.totalRevenue > 0) {
    const pct = ((currentTotals.totalRevenue - priorTotals.totalRevenue) / priorTotals.totalRevenue) * 100;
    if (Math.abs(pct) < 0.5) delta = { label: "≈ flat vs prior", tone: "flat" };
    else delta = { label: `${pct > 0 ? "↑" : "↓"} ${Math.abs(pct).toFixed(0)}%`, tone: pct > 0 ? "up" : "down" };
  }

  return {
    lab: "ROI · Period",
    big: `${roi.toFixed(1)}×`,
    sub: `${fmtMoney(currentTotals.totalRevenue)} revenue / ${fmtMoney(currentTotals.totalAdSpend)} spend`,
    ctx: `target ${goal.toFixed(1)}× · ${roi >= goal ? "ahead" : "below"} of target`,
    delta,
    tone,
  };
}

function computePacing(input: StoplightInput): StoplightTile {
  const { currentTotals, goalRevenue, daysElapsed, daysInPeriod } = input;
  if (!goalRevenue || goalRevenue <= 0) {
    return {
      lab: "Pacing · Period goal",
      big: "—",
      sub: `${fmtMoney(currentTotals.totalRevenue)} revenue`,
      ctx: "Set a revenue goal to enable pacing",
      delta: null,
      tone: "neutral",
    };
  }
  const pct = (currentTotals.totalRevenue / goalRevenue) * 100;
  const expectedPct = (daysElapsed / daysInPeriod) * 100;
  const tone: Tone = pct >= expectedPct ? "good" : pct >= expectedPct * 0.9 ? "ok" : "poor";
  const daysRemaining = Math.max(daysInPeriod - daysElapsed, 0);
  const required = daysRemaining > 0 ? (goalRevenue - currentTotals.totalRevenue) / daysRemaining : 0;
  const running = daysElapsed > 0 ? currentTotals.totalRevenue / daysElapsed : 0;
  return {
    lab: `Pacing · Period goal ${fmtMoney(goalRevenue)}`,
    big: `${pct.toFixed(0)}%`,
    sub: `${fmtMoney(currentTotals.totalRevenue)} / ${fmtMoney(goalRevenue)} · day ${daysElapsed} of ${daysInPeriod}`,
    ctx: `need ${fmtMoney(required)}/day to hit goal · running ${fmtMoney(running)}/day`,
    delta: null,
    tone,
  };
}

function computeLive(input: StoplightInput): StoplightTile {
  const { liveCounts, asOf } = input;
  const lastCall = liveCounts.lastCallAt;
  const minutesSince = lastCall ? (asOf.getTime() - lastCall.getTime()) / 60_000 : Infinity;

  let tone: Tone = "good";
  if (minutesSince > 240) tone = "poor";
  else if (minutesSince > 120) tone = "ok";

  const lastCallStr = lastCall
    ? minutesSince < 60 ? `${Math.round(minutesSince)}m ago` : `${(minutesSince / 60).toFixed(1)}h ago`
    : "no calls today";

  return {
    lab: "Live · Today",
    big: String(liveCounts.callsToday),
    sub: `${liveCounts.bookedToday} booked · ${liveCounts.callsToday - liveCounts.bookedToday} open`,
    ctx: `last call ${lastCallStr}`,
    delta: null,
    tone,
  };
}

export function computeStoplightTiles(input: StoplightInput): {
  roi: StoplightTile;
  pacing: StoplightTile;
  live: StoplightTile;
} {
  return {
    roi: computeRoi(input),
    pacing: computePacing(input),
    live: computeLive(input),
  };
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
npm test -- --run src/hooks/__tests__/use-stoplight-metrics.test.tsx
```

Expected: 8 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/hooks/use-stoplight-metrics.ts src/hooks/__tests__/use-stoplight-metrics.test.tsx
git commit -m "feat(marketing-roi): useStoplightMetrics — pure ROI/Pacing/Live computation"
```

---

## Task 6: useLiveLeadFeed hook

**Files:**
- Create: `src/hooks/use-live-lead-feed.ts`
- Test: `src/hooks/__tests__/use-live-lead-feed.test.tsx`

Reads the latest 20 rows from `calls_inbound`, ordered by `date desc, created_at desc`. Returns rows with masked phone, source pill, and a `booked` flag. Polls every 60 seconds.

- [ ] **Step 1: Write the failing test (pure helper only — full hook tested via integration smoke)**

Create `src/hooks/__tests__/use-live-lead-feed.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { maskPhone, summarizeFeedRow, type RawCallRow } from "../use-live-lead-feed";

describe("maskPhone", () => {
  it("masks middle digits of US-style number", () => {
    expect(maskPhone("(608) 555-4429")).toBe("(608) ···-4429");
    expect(maskPhone("6085554429")).toBe("(608) ···-4429");
  });
  it("returns original when format is unrecognized", () => {
    expect(maskPhone("abc")).toBe("abc");
    expect(maskPhone(null)).toBe("");
  });
});

describe("summarizeFeedRow", () => {
  const row: RawCallRow = {
    id: "1",
    date: "2026-05-01",
    source: "GoHighLevel Account 1",
    phone_number: "6085554429",
    is_booked: true,
    is_lead_opportunity: true,
    duration: 142,
    created_at: "2026-05-01T18:08:00Z",
    updated_at: "2026-05-01T18:08:00Z",
  };
  it("normalizes the source label and produces a feed row", () => {
    const out = summarizeFeedRow(row);
    expect(out.id).toBe("1");
    expect(out.booked).toBe(true);
    expect(out.sourceLabel).toBe("GHL");
    expect(out.maskedPhone).toBe("(608) ···-4429");
  });
  it("falls back to source string when not GHL", () => {
    expect(summarizeFeedRow({ ...row, source: "Google Ads" }).sourceLabel).toBe("Google");
    expect(summarizeFeedRow({ ...row, source: "Meta Ads" }).sourceLabel).toBe("Meta");
    expect(summarizeFeedRow({ ...row, source: "Direct" }).sourceLabel).toBe("Direct");
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
npm test -- --run src/hooks/__tests__/use-live-lead-feed.test.tsx
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Write minimal implementation**

Create `src/hooks/use-live-lead-feed.ts`:

```ts
import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";

export interface RawCallRow {
  id: string;
  date: string;
  source: string;
  phone_number: string | null;
  is_booked: boolean | null;
  is_lead_opportunity: boolean | null;
  duration: number | null;
  created_at: string;
  updated_at: string;
}

export interface FeedRow {
  id: string;
  whenISO: string;
  maskedPhone: string;
  sourceLabel: string;
  booked: boolean;
}

export function maskPhone(input: string | null): string {
  if (!input) return "";
  const digits = input.replace(/\D/g, "");
  if (digits.length === 10) return `(${digits.slice(0, 3)}) ···-${digits.slice(6)}`;
  if (digits.length === 11 && digits.startsWith("1")) return `(${digits.slice(1, 4)}) ···-${digits.slice(7)}`;
  return input;
}

function normalizeSource(source: string): string {
  const s = source.toLowerCase();
  if (s.includes("highlevel") || s.includes("ghl")) return "GHL";
  if (s.includes("google ads")) return "Google";
  if (s.includes("meta") || s.includes("facebook")) return "Meta";
  if (s.includes("lsa")) return "LSA";
  if (s.includes("organic") || s.includes("direct")) return "Direct";
  return source;
}

export function summarizeFeedRow(row: RawCallRow): FeedRow {
  return {
    id: row.id,
    whenISO: row.created_at,
    maskedPhone: maskPhone(row.phone_number),
    sourceLabel: normalizeSource(row.source),
    booked: !!row.is_booked,
  };
}

export interface LiveLeadFeed {
  rows: FeedRow[];
  callsToday: number;
  bookedToday: number;
  lastCallAt: Date | null;
  isLoading: boolean;
}

export function useLiveLeadFeed(): LiveLeadFeed {
  const today = new Date().toISOString().slice(0, 10);

  const { data, isLoading } = useQuery({
    queryKey: ["live-lead-feed", today],
    queryFn: async () => {
      const { data, error } = await supabase
        .from("calls_inbound")
        .select("*")
        .order("date", { ascending: false })
        .order("created_at", { ascending: false })
        .limit(20);
      if (error) throw error;
      return (data ?? []) as RawCallRow[];
    },
    staleTime: 60_000,
    refetchInterval: 60_000,
    refetchOnWindowFocus: true,
  });

  const rawRows = data ?? [];
  const rows = rawRows.map(summarizeFeedRow);
  const todayRows = rawRows.filter(r => r.date === today);
  const callsToday = todayRows.length;
  const bookedToday = todayRows.filter(r => r.is_booked).length;
  const lastCallAt = todayRows.length > 0 ? new Date(todayRows[0].created_at) : null;

  return { rows, callsToday, bookedToday, lastCallAt, isLoading };
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
npm test -- --run src/hooks/__tests__/use-live-lead-feed.test.tsx
```

Expected: 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/hooks/use-live-lead-feed.ts src/hooks/__tests__/use-live-lead-feed.test.tsx
git commit -m "feat(marketing-roi): useLiveLeadFeed reads calls_inbound + masks phones"
```

---

## Task 7: useLeadFunnel hook

**Files:**
- Create: `src/hooks/use-lead-funnel.ts`
- Test: `src/hooks/__tests__/use-lead-funnel.test.tsx`

Combines `calls_inbound` (Calls step) + opportunity-derived data from `useMarketingSourceROI` (Leads / Booked / Completed / Paid) into a `FunnelData` shape suitable for both the funnel viz and the drop-off-alerts utility.

- [ ] **Step 1: Write the failing test**

Create `src/hooks/__tests__/use-lead-funnel.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { computeFunnel, type FunnelInput } from "../use-lead-funnel";

const sm = (s: string, opps: number, completed: number, revenue: number) => ({
  canonicalSource: s,
  opportunities: opps,
  completedJobs: completed,
  revenue,
  // remaining MarketingSourceMetrics fields not needed for funnel calc
});

describe("computeFunnel", () => {
  it("aggregates calls_inbound and opportunities by source", () => {
    const input: FunnelInput = {
      callsBySource: { "Google Ads": 50, "Meta Ads": 30, "GHL": 20 },
      sourceMetrics: [
        sm("Google Ads", 38, 25, 17000) as any,
        sm("Meta Ads", 22, 12, 6000) as any,
        sm("GHL", 18, 15, 18000) as any,
      ] as any,
      bookedBySource: { "Google Ads": 30, "Meta Ads": 8, "GHL": 16 },
      paidBySource: { "Google Ads": 22, "Meta Ads": 10, "GHL": 14 },
    };
    const out = computeFunnel(input);
    expect(out.totals.calls).toBe(100);
    expect(out.totals.leads).toBe(78);
    expect(out.totals.booked).toBe(54);
    expect(out.totals.completed).toBe(52);
    expect(out.totals.paid).toBe(46);
    expect(out.bySource["Google Ads"].leads).toBe(38);
  });

  it("returns zero totals on empty input", () => {
    const out = computeFunnel({ callsBySource: {}, sourceMetrics: [] as any, bookedBySource: {}, paidBySource: {} });
    expect(out.totals.calls).toBe(0);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
npm test -- --run src/hooks/__tests__/use-lead-funnel.test.tsx
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Write minimal implementation**

Create `src/hooks/use-lead-funnel.ts`:

```ts
import type { MarketingSourceMetrics } from "./use-marketing-source-roi";

export interface FunnelInput {
  callsBySource: Record<string, number>;
  sourceMetrics: MarketingSourceMetrics[];
  bookedBySource: Record<string, number>;
  paidBySource: Record<string, number>;
}

export interface FunnelOutput {
  totals: { calls: number; leads: number; booked: number; completed: number; paid: number };
  bySource: Record<string, {
    source: string;
    calls: number;
    leads: number;
    booked: number;
    completed: number;
    paid: number;
  }>;
}

export function computeFunnel(input: FunnelInput): FunnelOutput {
  const sources = new Set<string>([
    ...Object.keys(input.callsBySource),
    ...input.sourceMetrics.map(s => s.canonicalSource),
  ]);

  const bySource: FunnelOutput["bySource"] = {};
  for (const src of sources) {
    const m = input.sourceMetrics.find(x => x.canonicalSource === src);
    bySource[src] = {
      source: src,
      calls: input.callsBySource[src] ?? 0,
      leads: m?.opportunities ?? 0,
      booked: input.bookedBySource[src] ?? 0,
      completed: m?.completedJobs ?? 0,
      paid: input.paidBySource[src] ?? 0,
    };
  }

  const totals = Object.values(bySource).reduce(
    (acc, s) => ({
      calls: acc.calls + s.calls,
      leads: acc.leads + s.leads,
      booked: acc.booked + s.booked,
      completed: acc.completed + s.completed,
      paid: acc.paid + s.paid,
    }),
    { calls: 0, leads: 0, booked: 0, completed: 0, paid: 0 },
  );

  return { totals, bySource };
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
npm test -- --run src/hooks/__tests__/use-lead-funnel.test.tsx
```

Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/hooks/use-lead-funnel.ts src/hooks/__tests__/use-lead-funnel.test.tsx
git commit -m "feat(marketing-roi): useLeadFunnel pure aggregator"
```

---

## Task 8: Extend useMarketingSourceROI with daily series

**Files:**
- Modify: `src/hooks/use-marketing-source-roi.ts`

Adds `dailySeries: { date: string; revenue: number; spend: number }[]` to each `MarketingSourceMetrics` row. Sparklines on channel cards consume this. Existing fields are unchanged so no consumer breaks.

- [ ] **Step 1: Read current shape**

```bash
grep -n "MarketingSourceMetrics" src/hooks/use-marketing-source-roi.ts | head -10
```

- [ ] **Step 2: Add `dailySeries` to the interface**

In `src/hooks/use-marketing-source-roi.ts`, find the `MarketingSourceMetrics` interface and add at the bottom (before the closing `}`):

```ts
  dailySeries: { date: string; revenue: number; spend: number }[];
```

- [ ] **Step 3: Compute the series in the aggregation step**

Inside the `useMemo` block in `useMarketingSourceROI`, find the section that builds `sourceMetrics` (the `Array.from(allCanonicalSources).map(...)`). Before it, build a per-source-per-date map:

```ts
const seriesAgg: Record<string, Record<string, { revenue: number; spend: number }>> = {};

for (const opp of opportunities) {
  const rawSource = opp.lead_source || "";
  const canonicalSource = normalizeLeadSource(rawSource);
  const isCompleted = opp.type === "job" && (opp.status === "completed" || opp.status === "paid");
  if (!isCompleted) continue;
  const day = (opp.completed_at || opp.invoice_paid_at || opp.scheduled_at || "").slice(0, 10);
  if (!day) continue;
  seriesAgg[canonicalSource] ??= {};
  seriesAgg[canonicalSource][day] ??= { revenue: 0, spend: 0 };
  seriesAgg[canonicalSource][day].revenue += opp.value || 0;
}
```

For ad-spend-by-day, fetch a separate query in the existing `adSpendQuery` callback. Modify `fetchAdSpend` to also return per-day spend:

Find `async function fetchAdSpend(from, to)`. Change its return type and aggregation:

```ts
interface AdSpendBySource {
  spend: number;
  clicks: number;
  leads: number;
  daily: Record<string, number>; // ISO date → spend
}
```

In the `for (const row of (data || []))` loop, append:

```ts
const day = row.date as string;
spendAgg[canonical].daily ??= {};
spendAgg[canonical].daily[day] = (spendAgg[canonical].daily[day] ?? 0) + Number(row.spend_amount || 0);
```

And initialize `daily: {}` in the `if (!spendAgg[canonical])` block.

Then, in the `sourceMetrics` mapping, build the series:

```ts
const allDays = new Set<string>([
  ...Object.keys(seriesAgg[canonicalSource] ?? {}),
  ...Object.keys(spendData?.daily ?? {}),
]);
const dailySeries = Array.from(allDays)
  .sort()
  .map(date => ({
    date,
    revenue: seriesAgg[canonicalSource]?.[date]?.revenue ?? 0,
    spend: spendData?.daily?.[date] ?? 0,
  }));
```

And add `dailySeries` to the returned object alongside the existing fields.

- [ ] **Step 4: Verify type compiles**

```bash
npx tsc --noEmit
```

Expected: no new errors. (The repo may have pre-existing TS warnings; only this hook should be clean.)

- [ ] **Step 5: Verify existing tests pass**

```bash
npm test -- --run
```

Expected: existing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add src/hooks/use-marketing-source-roi.ts
git commit -m "feat(marketing-roi): add dailySeries to MarketingSourceMetrics for sparklines"
```

---

## Task 9: MarketingStoplightHero component

**Files:**
- Create: `src/components/marketing-roi/MarketingStoplightHero.tsx`
- Test: `src/components/marketing-roi/__tests__/MarketingStoplightHero.test.tsx`

Three navy-yellow tiles using the same gradient and yellow-glow accent as `HeroScoreboard`. Each tile is a `StoplightTile` from `useStoplightMetrics`.

- [ ] **Step 1: Write the failing test**

Create `src/components/marketing-roi/__tests__/MarketingStoplightHero.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { MarketingStoplightHero } from "../MarketingStoplightHero";
import type { StoplightTile } from "@/hooks/use-stoplight-metrics";

const tile = (overrides: Partial<StoplightTile> = {}): StoplightTile => ({
  lab: "ROI · MTD",
  big: "3.4×",
  sub: "$32k revenue / $9.6k spend",
  ctx: "target 3.0× · ahead of target",
  delta: { label: "↑ 12%", tone: "up" },
  tone: "good",
  ...overrides,
});

describe("MarketingStoplightHero", () => {
  it("renders all three tiles", () => {
    render(
      <MarketingStoplightHero
        roi={tile({ lab: "ROI · MTD", big: "3.4×" })}
        pacing={tile({ lab: "Pacing · MTD", big: "64%" })}
        live={tile({ lab: "Live · Today", big: "14" })}
      />
    );
    expect(screen.getByText("ROI · MTD")).toBeInTheDocument();
    expect(screen.getByText("Pacing · MTD")).toBeInTheDocument();
    expect(screen.getByText("Live · Today")).toBeInTheDocument();
    expect(screen.getByText("3.4×")).toBeInTheDocument();
    expect(screen.getByText("64%")).toBeInTheDocument();
    expect(screen.getByText("14")).toBeInTheDocument();
  });

  it("renders delta pill when present", () => {
    render(
      <MarketingStoplightHero
        roi={tile({ delta: { label: "↑ 12%", tone: "up" } })}
        pacing={tile({ delta: null })}
        live={tile({ delta: null })}
      />
    );
    expect(screen.getByText("↑ 12%")).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
npm test -- --run src/components/marketing-roi/__tests__/MarketingStoplightHero.test.tsx
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Write minimal implementation**

Create `src/components/marketing-roi/MarketingStoplightHero.tsx`:

```tsx
import type { StoplightTile } from "@/hooks/use-stoplight-metrics";

const DELTA_BG: Record<"up" | "down" | "flat", string> = {
  up:   "rgba(4, 120, 87, 0.85)",
  down: "rgba(185, 28, 28, 0.85)",
  flat: "rgba(100, 116, 139, 0.85)",
};

const LABEL_COLOR: Record<StoplightTile["tone"], string> = {
  good:    "#7CF0AE",
  ok:      "#F7B801",
  poor:    "#FF8B8B",
  neutral: "#F7B801",
};

function Tile({ tile }: { tile: StoplightTile }) {
  return (
    <div
      className="relative overflow-hidden rounded-2xl p-5"
      style={{
        background: "linear-gradient(135deg, #0f1d4d 0%, #1e3a8a 100%)",
        color: "#ffffff",
        boxShadow: "0 6px 18px rgba(15, 29, 77, 0.12)",
      }}
    >
      <div
        className="absolute pointer-events-none"
        style={{
          right: "-30px", top: "-40px", width: "160px", height: "160px",
          background: "radial-gradient(circle, rgba(247, 184, 1, 0.45), transparent 70%)",
        }}
      />
      <div className="relative flex items-start justify-between gap-3">
        <div
          className="text-[11px] font-extrabold uppercase tracking-wider"
          style={{ color: LABEL_COLOR[tile.tone] }}
        >
          {tile.lab}
        </div>
        {tile.delta && (
          <span
            className="text-[10px] font-extrabold px-2 py-0.5 rounded-full whitespace-nowrap"
            style={{ background: DELTA_BG[tile.delta.tone], color: "#ffffff" }}
          >
            {tile.delta.label}
          </span>
        )}
      </div>
      <div className="relative font-extrabold tracking-tight leading-none mt-2 text-4xl md:text-5xl">
        {tile.big}
      </div>
      {tile.sub && (
        <div className="relative text-xs mt-1.5" style={{ color: "rgba(255,255,255,0.7)" }}>
          {tile.sub}
        </div>
      )}
      {tile.ctx && (
        <div
          className="relative text-[10.5px] mt-3 pt-2.5"
          style={{ color: "rgba(255,255,255,0.55)", borderTop: "1px solid rgba(255,255,255,0.1)" }}
        >
          {tile.ctx}
        </div>
      )}
    </div>
  );
}

export interface MarketingStoplightHeroProps {
  roi: StoplightTile;
  pacing: StoplightTile;
  live: StoplightTile;
}

export function MarketingStoplightHero({ roi, pacing, live }: MarketingStoplightHeroProps) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
      <Tile tile={roi} />
      <Tile tile={pacing} />
      <Tile tile={live} />
    </div>
  );
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
npm test -- --run src/components/marketing-roi/__tests__/MarketingStoplightHero.test.tsx
```

Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/components/marketing-roi/MarketingStoplightHero.tsx src/components/marketing-roi/__tests__/MarketingStoplightHero.test.tsx
git commit -m "feat(marketing-roi): MarketingStoplightHero — three-tile navy-yellow hero"
```

---

## Task 10: ChannelScorecardCard component

**Files:**
- Create: `src/components/marketing-roi/ChannelScorecardCard.tsx`
- Test: `src/components/marketing-roi/__tests__/ChannelScorecardCard.test.tsx`

Per-channel card with logo, three KPI tiles, ROI bar, sparkline, optional ribbon, footer. KPI variant differs by channel kind:

- `paid` (Google Ads, Meta Ads, LSA) → Spend / Leads / Revenue
- `calls` (GHL) → Calls / Booked / Revenue
- `organic` (GA4, Direct) → Sessions / Form fills / Revenue (placeholder values for now)

- [ ] **Step 1: Write the failing test**

Create `src/components/marketing-roi/__tests__/ChannelScorecardCard.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { ChannelScorecardCard } from "../ChannelScorecardCard";

describe("ChannelScorecardCard", () => {
  it("renders paid channel with Spend/Leads/Revenue", () => {
    render(
      <ChannelScorecardCard
        kind="paid"
        name="Google Ads"
        subtitle="12 active campaigns"
        kpis={{ spend: 4210, leads: 38, revenue: 17200, roi: 4.1, costPerLead: 110, closeRate: 0.61 }}
        sparkline={[
          { date: "2026-04-01", revenue: 100, spend: 50 },
          { date: "2026-04-02", revenue: 200, spend: 80 },
        ]}
        ribbon={{ tier: "gold", criterion: "TOP ROI" }}
        roiGoal={3.0}
      />
    );
    expect(screen.getByText("Google Ads")).toBeInTheDocument();
    expect(screen.getByText(/Spend/i)).toBeInTheDocument();
    expect(screen.getByText("4.1×")).toBeInTheDocument();
    expect(screen.getByText("TOP ROI")).toBeInTheDocument();
  });

  it("renders calls channel with Calls/Booked/Revenue and book rate", () => {
    render(
      <ChannelScorecardCard
        kind="calls"
        name="GHL · Calls & Forms"
        subtitle="87 inbound this month"
        kpis={{ calls: 87, booked: 53, revenue: 18400, bookRate: 0.61 }}
        sparkline={[]}
        ribbon={null}
        roiGoal={3.0}
      />
    );
    expect(screen.getByText("87")).toBeInTheDocument();
    expect(screen.getByText("53")).toBeInTheDocument();
  });

  it("renders organic channel with placeholder copy when GA4 disconnected", () => {
    render(
      <ChannelScorecardCard
        kind="organic"
        name="GA4 · Organic / Direct"
        subtitle="not connected"
        kpis={{ sessions: null, formFills: null, revenue: 11820 }}
        sparkline={[]}
        ribbon={null}
        roiGoal={3.0}
      />
    );
    expect(screen.getByText(/GA4/i)).toBeInTheDocument();
    // sessions field is null → renders em-dash
    expect(screen.getAllByText("—").length).toBeGreaterThan(0);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
npm test -- --run src/components/marketing-roi/__tests__/ChannelScorecardCard.test.tsx
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Write minimal implementation**

Create `src/components/marketing-roi/ChannelScorecardCard.tsx`:

```tsx
export type ChannelKind = "paid" | "calls" | "organic";

export interface PaidKpis {
  spend: number;
  leads: number;
  revenue: number;
  roi: number;
  costPerLead: number;
  closeRate: number;
}
export interface CallsKpis {
  calls: number;
  booked: number;
  revenue: number;
  bookRate: number;
}
export interface OrganicKpis {
  sessions: number | null;
  formFills: number | null;
  revenue: number;
}
export type ChannelKpis = PaidKpis | CallsKpis | OrganicKpis;

export interface ChannelRibbon {
  tier: "gold" | "silver" | "bronze";
  criterion: "TOP ROI" | "RISING" | "VOLUME LEAD";
}

export interface ChannelScorecardCardProps {
  kind: ChannelKind;
  name: string;
  subtitle: string;
  kpis: ChannelKpis;
  sparkline: { date: string; revenue: number; spend: number }[];
  ribbon: ChannelRibbon | null;
  roiGoal: number;
}

const RIBBON_BG: Record<ChannelRibbon["tier"], string> = {
  gold:   "linear-gradient(90deg,#e8a900,#f7b801)",
  silver: "linear-gradient(90deg,#8a93a8,#b6bfd2)",
  bronze: "linear-gradient(90deg,#a35a23,#d18248)",
};

const fmtMoney = (n: number) =>
  n >= 1000 ? `$${(n / 1000).toFixed(1)}k` : `$${Math.round(n).toLocaleString()}`;
const fmtCount = (n: number | null) => (n === null ? "—" : n.toLocaleString());

function Sparkline({ data }: { data: { date: string; revenue: number; spend: number }[] }) {
  if (data.length < 2) return <div className="h-[36px]" />;
  const maxRev = Math.max(...data.map(d => d.revenue), 1);
  const maxSpend = Math.max(...data.map(d => d.spend), 1);
  const w = 100;
  const h = 24;
  const stepX = w / (data.length - 1);
  const revPath = data.map((d, i) => `${i * stepX},${h - (d.revenue / maxRev) * (h - 2) - 1}`).join(" ");
  const spendPath = data.map((d, i) => `${i * stepX},${h - (d.spend / maxSpend) * (h - 2) - 1}`).join(" ");
  return (
    <div className="rounded-md p-1.5" style={{ background: "linear-gradient(180deg, rgba(247,184,1,.10), transparent)" }}>
      <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" className="w-full h-[28px]">
        <polyline points={spendPath} fill="none" stroke="#1e3a8a" strokeWidth="1.4" opacity="0.6" />
        <polyline points={revPath} fill="none" stroke="#f7b801" strokeWidth="1.6" />
      </svg>
    </div>
  );
}

function RoiBar({ value, goal }: { value: number; goal: number }) {
  const pct = Math.max(0, Math.min(100, (value / goal) * 100 * 0.5));
  const tone = value >= goal ? "good" : value >= goal * 0.6 ? "ok" : "poor";
  const fillBg = tone === "good"
    ? "linear-gradient(90deg,#1FB45D,#26D26B)"
    : tone === "ok"
    ? "linear-gradient(90deg,#F7B801,#FFD43B)"
    : "linear-gradient(90deg,#E64E4E,#FF8181)";
  return (
    <div className="flex items-center gap-2.5">
      <span className="text-[11px] font-bold text-muted-foreground uppercase tracking-wider min-w-[60px]">ROI</span>
      <div className="flex-1 h-2 bg-[#eef1f7] rounded-full overflow-hidden">
        <div className="h-full rounded-full" style={{ width: `${pct}%`, background: fillBg }} />
      </div>
      <span className="text-[13px] font-extrabold text-primary min-w-[42px] text-right">{value.toFixed(1)}×</span>
    </div>
  );
}

function Kpi({ label, value, delta }: { label: string; value: string; delta?: string }) {
  return (
    <div className="bg-[#f5f7fb] rounded-[10px] px-3 py-2.5">
      <div className="text-[9.5px] uppercase tracking-wider font-bold text-muted-foreground">{label}</div>
      <div className="text-[18px] font-extrabold text-primary tracking-tight leading-none mt-1">{value}</div>
      {delta && <div className="text-[10.5px] mt-0.5 font-semibold text-emerald-700">{delta}</div>}
    </div>
  );
}

export function ChannelScorecardCard(props: ChannelScorecardCardProps) {
  const { kind, name, subtitle, kpis, sparkline, ribbon, roiGoal } = props;

  let kpiTiles: React.ReactNode;
  let footer: React.ReactNode;
  let roiValue: number | null = null;

  if (kind === "paid") {
    const p = kpis as PaidKpis;
    roiValue = p.roi;
    kpiTiles = (
      <>
        <Kpi label="Spend" value={fmtMoney(p.spend)} />
        <Kpi label="Leads" value={String(p.leads)} />
        <Kpi label="Revenue" value={fmtMoney(p.revenue)} />
      </>
    );
    footer = <span>{fmtMoney(p.costPerLead)} cost / lead · {(p.closeRate * 100).toFixed(0)}% closed</span>;
  } else if (kind === "calls") {
    const c = kpis as CallsKpis;
    kpiTiles = (
      <>
        <Kpi label="Calls" value={String(c.calls)} />
        <Kpi label="Booked" value={String(c.booked)} />
        <Kpi label="Revenue" value={fmtMoney(c.revenue)} />
      </>
    );
    footer = <span>{(c.bookRate * 100).toFixed(0)}% book rate</span>;
  } else {
    const o = kpis as OrganicKpis;
    kpiTiles = (
      <>
        <Kpi label="Sessions" value={fmtCount(o.sessions)} />
        <Kpi label="Form fills" value={fmtCount(o.formFills)} />
        <Kpi label="Revenue" value={fmtMoney(o.revenue)} />
      </>
    );
    footer = <span>{o.sessions === null ? "GA4 not connected" : "from organic / direct"}</span>;
  }

  return (
    <div className="relative overflow-hidden bg-card rounded-2xl border border-border p-[18px] flex flex-col gap-2.5 shadow-sm">
      {ribbon && (
        <div
          className="absolute top-3.5 right-[-32px] py-1 px-9 text-[9px] font-extrabold tracking-wider text-white uppercase"
          style={{ transform: "rotate(35deg)", background: RIBBON_BG[ribbon.tier] }}
        >
          {ribbon.criterion}
        </div>
      )}
      <div className="flex items-center gap-2.5">
        <div className="w-9 h-9 rounded-lg flex items-center justify-center font-black text-sm text-white"
             style={{ background: kind === "calls" ? "#0f1d4d" : kind === "organic" ? "#F9AB00" : "#4285F4" }}>
          {name.charAt(0)}
        </div>
        <div>
          <div className="text-sm font-extrabold text-primary">{name}</div>
          <div className="text-[11px] text-muted-foreground mt-0.5">{subtitle}</div>
        </div>
      </div>
      <div className="grid grid-cols-3 gap-2 mt-1">{kpiTiles}</div>
      {roiValue !== null && <RoiBar value={roiValue} goal={roiGoal} />}
      <Sparkline data={sparkline} />
      <div className="flex justify-between items-center text-[11px] text-muted-foreground pt-1 border-t border-dashed border-border mt-1">
        {footer}
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
npm test -- --run src/components/marketing-roi/__tests__/ChannelScorecardCard.test.tsx
```

Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/components/marketing-roi/ChannelScorecardCard.tsx src/components/marketing-roi/__tests__/ChannelScorecardCard.test.tsx
git commit -m "feat(marketing-roi): ChannelScorecardCard with paid/calls/organic variants"
```

---

## Task 11: ChannelScorecardConnect placeholder

**Files:**
- Create: `src/components/marketing-roi/ChannelScorecardConnect.tsx`

Static placeholder card with "+ Connect <Channel>" and a one-line subtitle. No tests beyond the integration test in Task 16.

- [ ] **Step 1: Create the component**

Create `src/components/marketing-roi/ChannelScorecardConnect.tsx`:

```tsx
export interface ChannelScorecardConnectProps {
  channel: string;
  hint: string;
  setupTime?: string;
}

export function ChannelScorecardConnect({ channel, hint, setupTime }: ChannelScorecardConnectProps) {
  return (
    <div className="bg-[#fafbfd] rounded-2xl border border-dashed border-[#cfd5e2] p-[18px] flex items-center justify-center text-center min-h-[180px]">
      <div className="space-y-1">
        <div className="w-[42px] h-[42px] rounded-full border border-dashed border-[#b6bfd2] flex items-center justify-center mx-auto text-[22px] font-light text-muted-foreground">+</div>
        <div className="font-extrabold text-primary mt-2">Connect {channel}</div>
        <div className="text-[11px] text-muted-foreground">{hint}</div>
        {setupTime && <div className="text-[10px] text-muted-foreground mt-2">{setupTime}</div>}
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Type-check**

```bash
npx tsc --noEmit
```

Expected: no new errors.

- [ ] **Step 3: Commit**

```bash
git add src/components/marketing-roi/ChannelScorecardConnect.tsx
git commit -m "feat(marketing-roi): ChannelScorecardConnect placeholder card"
```

---

## Task 12: LeadFunnelPanel component

**Files:**
- Create: `src/components/marketing-roi/LeadFunnelPanel.tsx`
- Test: `src/components/marketing-roi/__tests__/LeadFunnelPanel.test.tsx`

Renders the 5-step funnel as a row of bars with conversion percentages, plus a list of drop-off alerts beneath. Sources are color-coded inside each bar (stacked).

- [ ] **Step 1: Write the failing test**

Create `src/components/marketing-roi/__tests__/LeadFunnelPanel.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { LeadFunnelPanel } from "../LeadFunnelPanel";

describe("LeadFunnelPanel", () => {
  const funnel = {
    totals: { calls: 184, leads: 142, booked: 88, completed: 71, paid: 64 },
    bySource: {},
  };
  it("renders all 5 step labels with totals", () => {
    render(<LeadFunnelPanel funnel={funnel} alerts={[]} />);
    expect(screen.getByText("Calls")).toBeInTheDocument();
    expect(screen.getByText("Leads")).toBeInTheDocument();
    expect(screen.getByText("Booked")).toBeInTheDocument();
    expect(screen.getByText("Completed")).toBeInTheDocument();
    expect(screen.getByText("Paid")).toBeInTheDocument();
    expect(screen.getByText("184")).toBeInTheDocument();
    expect(screen.getByText("64")).toBeInTheDocument();
  });

  it("renders an alert row when given", () => {
    render(
      <LeadFunnelPanel
        funnel={funnel}
        alerts={[{
          source: "Meta Ads", step: "lead_to_booked",
          channelPct: 0.36, averagePct: 0.61,
          hint: "Lower-intent traffic. Tighten audience targeting or refresh ad creative.",
        }]}
      />
    );
    expect(screen.getByText(/Meta Ads/)).toBeInTheDocument();
    expect(screen.getByText(/Tighten audience/)).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
npm test -- --run src/components/marketing-roi/__tests__/LeadFunnelPanel.test.tsx
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Write minimal implementation**

Create `src/components/marketing-roi/LeadFunnelPanel.tsx`:

```tsx
import type { FunnelOutput } from "@/hooks/use-lead-funnel";
import type { DropOffAlert } from "@/lib/marketing-roi/drop-off-alerts";

const STEP_LABELS: { key: keyof FunnelOutput["totals"]; label: string }[] = [
  { key: "calls", label: "Calls" },
  { key: "leads", label: "Leads" },
  { key: "booked", label: "Booked" },
  { key: "completed", label: "Completed" },
  { key: "paid", label: "Paid" },
];

const STEP_GRADIENTS = [
  "linear-gradient(180deg,#1e3a8a,#0f1d4d)",
  "linear-gradient(180deg,#3a55a8,#1e3a8a)",
  "linear-gradient(180deg,#5d77c2,#3a55a8)",
  "linear-gradient(180deg,#7e95d2,#5d77c2)",
  "linear-gradient(180deg,#f7b801,#e8a900)",
];

export interface LeadFunnelPanelProps {
  funnel: FunnelOutput;
  alerts: DropOffAlert[];
}

export function LeadFunnelPanel({ funnel, alerts }: LeadFunnelPanelProps) {
  const max = Math.max(funnel.totals.calls, 1);
  return (
    <div className="bg-card rounded-2xl border border-border p-[18px] shadow-sm">
      <h3 className="text-[13px] font-extrabold text-primary uppercase tracking-wide mb-3">Lead funnel · period</h3>
      <div className="flex gap-1.5 items-end h-[130px]">
        {STEP_LABELS.map((s, i) => {
          const value = funnel.totals[s.key];
          const heightPct = Math.max((value / max) * 100, 12);
          const prev = i === 0 ? value : funnel.totals[STEP_LABELS[i - 1].key];
          const conv = i === 0 ? 100 : prev > 0 ? (value / prev) * 100 : 0;
          return (
            <div key={s.key} className="flex-1 flex flex-col items-center">
              <div
                className={`w-full rounded-t-lg text-white font-extrabold text-[13px] flex items-center justify-center py-1.5 ${i === 4 ? "text-primary" : ""}`}
                style={{ height: `${heightPct}%`, background: STEP_GRADIENTS[i] }}
              >
                {value}
              </div>
              <div className="text-[10px] uppercase tracking-wider font-bold text-muted-foreground mt-1.5">{s.label}</div>
              <div className="text-[10px] font-semibold text-muted-foreground/80">{conv.toFixed(0)}%</div>
            </div>
          );
        })}
      </div>
      {alerts.length > 0 && (
        <div className="mt-3 space-y-1.5">
          {alerts.map((a, idx) => (
            <div key={idx} className="text-[11px] bg-[#fbe2e2] text-[#a73030] rounded-lg p-2.5">
              <b>⚠ {a.source} {a.step.replace(/_/g, " → ")}:</b>{" "}
              {(a.channelPct * 100).toFixed(0)}% (vs {(a.averagePct * 100).toFixed(0)}% avg).
              {a.hint && <> {a.hint}</>}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
npm test -- --run src/components/marketing-roi/__tests__/LeadFunnelPanel.test.tsx
```

Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/components/marketing-roi/LeadFunnelPanel.tsx src/components/marketing-roi/__tests__/LeadFunnelPanel.test.tsx
git commit -m "feat(marketing-roi): LeadFunnelPanel — 5-step funnel + drop-off alerts"
```

---

## Task 13: LiveLeadFeed component

**Files:**
- Create: `src/components/marketing-roi/LiveLeadFeed.tsx`
- Test: `src/components/marketing-roi/__tests__/LiveLeadFeed.test.tsx`

Renders the feed rows from `useLiveLeadFeed`. Booked rows tinted green. Empty state shows a one-line "No inbound calls today."

- [ ] **Step 1: Write the failing test**

Create `src/components/marketing-roi/__tests__/LiveLeadFeed.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { LiveLeadFeed } from "../LiveLeadFeed";
import type { FeedRow } from "@/hooks/use-live-lead-feed";

const row = (overrides: Partial<FeedRow> = {}): FeedRow => ({
  id: "r1",
  whenISO: "2026-05-01T18:08:00Z",
  maskedPhone: "(608) ···-4429",
  sourceLabel: "Google",
  booked: true,
  ...overrides,
});

describe("LiveLeadFeed", () => {
  it("renders 'no calls' empty state", () => {
    render(<LiveLeadFeed rows={[]} />);
    expect(screen.getByText(/No inbound calls today/i)).toBeInTheDocument();
  });

  it("renders rows with masked phone and source pill", () => {
    render(<LiveLeadFeed rows={[row({ id: "r1" }), row({ id: "r2", sourceLabel: "GHL", booked: false })]} />);
    expect(screen.getAllByText("(608) ···-4429")).toHaveLength(2);
    expect(screen.getByText("Google")).toBeInTheDocument();
    expect(screen.getByText("GHL")).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
npm test -- --run src/components/marketing-roi/__tests__/LiveLeadFeed.test.tsx
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Write minimal implementation**

Create `src/components/marketing-roi/LiveLeadFeed.tsx`:

```tsx
import type { FeedRow } from "@/hooks/use-live-lead-feed";

function fmtTime(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", hour12: false });
}

export interface LiveLeadFeedProps {
  rows: FeedRow[];
}

export function LiveLeadFeed({ rows }: LiveLeadFeedProps) {
  return (
    <div className="bg-card rounded-2xl border border-border p-[18px] shadow-sm">
      <h3 className="text-[13px] font-extrabold text-primary uppercase tracking-wide mb-3">Live lead feed · today</h3>
      {rows.length === 0 ? (
        <div className="text-[12px] text-muted-foreground py-6 text-center">No inbound calls today.</div>
      ) : (
        <div>
          {rows.map(r => (
            <div
              key={r.id}
              className={`flex justify-between items-center py-2 px-1 border-b border-[#eef1f7] last:border-b-0 text-[12px] ${r.booked ? "bg-[#e6f7ec]/50" : ""}`}
            >
              <span className="text-muted-foreground text-[11px] min-w-[48px]">{fmtTime(r.whenISO)}</span>
              <span className="flex-1 px-2.5">{r.maskedPhone}</span>
              <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider ${r.booked ? "bg-[#e6f7ec] text-[#168a3c]" : "bg-[#f5f7fb] text-primary"}`}>
                {r.sourceLabel}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
npm test -- --run src/components/marketing-roi/__tests__/LiveLeadFeed.test.tsx
```

Expected: 2 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/components/marketing-roi/LiveLeadFeed.tsx src/components/marketing-roi/__tests__/LiveLeadFeed.test.tsx
git commit -m "feat(marketing-roi): LiveLeadFeed component"
```

---

## Task 14: Snapshot legacy page

**Files:**
- Create: `src/pages/MarketingSourceROIv1.tsx`

Verbatim copy of the current `MarketingSourceROI.tsx`. Reachable via `?legacy=1` for 30 days post-deploy as a rollback safety net.

- [ ] **Step 1: Copy the current page**

```bash
cp src/pages/MarketingSourceROI.tsx src/pages/MarketingSourceROIv1.tsx
```

- [ ] **Step 2: Rename the default export and component**

In `src/pages/MarketingSourceROIv1.tsx`, change:

```tsx
export default function MarketingSourceROI() {
```

to:

```tsx
export default function MarketingSourceROIv1() {
```

Leave the rest of the file unchanged — it must render identical output to the current page.

- [ ] **Step 3: Type-check**

```bash
npx tsc --noEmit
```

Expected: no new errors.

- [ ] **Step 4: Commit**

```bash
git add src/pages/MarketingSourceROIv1.tsx
git commit -m "chore(marketing-roi): snapshot current page as v1 for rollback gate"
```

---

## Task 15: Refactor MarketingSourceROI page

**Files:**
- Modify: `src/pages/MarketingSourceROI.tsx`

Replace the current page body with a composition of the new components. Add a `?legacy=1` gate that renders `MarketingSourceROIv1`.

- [ ] **Step 1: Read the current page imports for reference**

```bash
head -25 src/pages/MarketingSourceROI.tsx
```

- [ ] **Step 2: Write the new page**

Open `src/pages/MarketingSourceROI.tsx` and replace its full contents with:

```tsx
import { useState, useEffect, useMemo } from "react";
import { useSearchParams } from "react-router-dom";
import { DateRange } from "react-day-picker";
import { startOfMonth, startOfWeek, startOfQuarter, startOfYear, endOfDay, subDays, differenceInCalendarDays, getDaysInMonth } from "date-fns";
import { BarChart3 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { DateRangePicker } from "@/components/dashboard/DateRangePicker";
import { ExportButtons } from "@/components/dashboard/ExportButtons";
import { exportToExcel, exportToPDF, fmtCurrency, fmtPercent } from "@/lib/export-utils";

import { MarketingStoplightHero } from "@/components/marketing-roi/MarketingStoplightHero";
import { ChannelScorecardCard } from "@/components/marketing-roi/ChannelScorecardCard";
import { ChannelScorecardConnect } from "@/components/marketing-roi/ChannelScorecardConnect";
import { LeadFunnelPanel } from "@/components/marketing-roi/LeadFunnelPanel";
import { LiveLeadFeed } from "@/components/marketing-roi/LiveLeadFeed";
import { MarketingSourceTable } from "@/components/marketing-roi/MarketingSourceTable";

import { useMarketingSourceROI, JobTypeFilter } from "@/hooks/use-marketing-source-roi";
import { useCompanyGoals } from "@/hooks/use-company-goals";
import { useLiveLeadFeed } from "@/hooks/use-live-lead-feed";
import { computeStoplightTiles } from "@/hooks/use-stoplight-metrics";
import { computeFunnel } from "@/hooks/use-lead-funnel";
import { rankRibbons } from "@/lib/marketing-roi/ribbon-ranking";
import { computeDropOffAlerts } from "@/lib/marketing-roi/drop-off-alerts";
import { priorPeriodAsDateRange } from "@/lib/dashboard/period-helpers";
import { supabase } from "@/integrations/supabase/client";

import MarketingSourceROIv1 from "./MarketingSourceROIv1";

type Period = "today" | "wtd" | "mtd" | "qtd" | "ytd" | "custom";

function periodToRange(period: Period, custom?: DateRange): DateRange {
  const now = new Date();
  switch (period) {
    case "today": return { from: new Date(now.setHours(0, 0, 0, 0)), to: endOfDay(new Date()) };
    case "wtd":   return { from: startOfWeek(new Date(), { weekStartsOn: 1 }), to: endOfDay(new Date()) };
    case "mtd":   return { from: startOfMonth(new Date()), to: endOfDay(new Date()) };
    case "qtd":   return { from: startOfQuarter(new Date()), to: endOfDay(new Date()) };
    case "ytd":   return { from: startOfYear(new Date()), to: endOfDay(new Date()) };
    case "custom": return custom ?? { from: subDays(new Date(), 30), to: new Date() };
  }
}

const PERIOD_BUTTONS: { key: Period; label: string }[] = [
  { key: "today", label: "Today" },
  { key: "wtd",   label: "WTD" },
  { key: "mtd",   label: "MTD" },
  { key: "qtd",   label: "QTD" },
  { key: "ytd",   label: "YTD" },
  { key: "custom", label: "Custom" },
];

export default function MarketingSourceROI() {
  const [searchParams, setSearchParams] = useSearchParams();
  if (searchParams.get("legacy") === "1") return <MarketingSourceROIv1 />;

  const [period, setPeriod] = useState<Period>((searchParams.get("period") as Period) || "mtd");
  const [customRange, setCustomRange] = useState<DateRange | undefined>(undefined);
  const [jobTypeFilter, setJobTypeFilter] = useState<JobTypeFilter>((searchParams.get("jobType") as JobTypeFilter) || "all");
  const [technicianId, setTechnicianId] = useState<string>(searchParams.get("tech") || "all");
  const [technicians, setTechnicians] = useState<{ id: string; name: string }[]>([]);

  const dateRange = useMemo(() => periodToRange(period, customRange), [period, customRange]);

  useEffect(() => {
    const next = new URLSearchParams();
    if (period !== "mtd") next.set("period", period);
    if (jobTypeFilter !== "all") next.set("jobType", jobTypeFilter);
    if (technicianId !== "all") next.set("tech", technicianId);
    setSearchParams(next, { replace: true });
  }, [period, jobTypeFilter, technicianId, setSearchParams]);

  useEffect(() => {
    supabase.from("technicians").select("id, name").eq("is_active", true).order("name")
      .then(({ data }) => { if (data) setTechnicians(data); });
  }, []);

  const { sourceMetrics, totals, isLoading } = useMarketingSourceROI(
    dateRange, jobTypeFilter, technicianId === "all" ? undefined : technicianId,
  );

  const priorRange = useMemo(() => priorPeriodAsDateRange(dateRange), [dateRange]);
  const { totals: priorTotals, sourceMetrics: priorSourceMetrics } = useMarketingSourceROI(
    priorRange, jobTypeFilter, technicianId === "all" ? undefined : technicianId,
  );

  const live = useLiveLeadFeed();
  const { getGoal } = useCompanyGoals();

  const daysInPeriod = useMemo(() => {
    if (period === "mtd") return getDaysInMonth(new Date());
    if (period === "wtd") return 7;
    if (period === "today") return 1;
    if (dateRange.from && dateRange.to) return differenceInCalendarDays(dateRange.to, dateRange.from) + 1;
    return 30;
  }, [period, dateRange]);

  const daysElapsed = useMemo(() => {
    if (!dateRange.from) return 1;
    return Math.max(differenceInCalendarDays(new Date(), dateRange.from) + 1, 1);
  }, [dateRange]);

  const tiles = useMemo(() => computeStoplightTiles({
    currentTotals: totals,
    priorTotals,
    goalRevenue: getGoal("revenue"),
    goalRoi: getGoal("roi_target") ?? 3.0,
    liveCounts: { callsToday: live.callsToday, bookedToday: live.bookedToday, lastCallAt: live.lastCallAt },
    daysElapsed, daysInPeriod, asOf: new Date(),
  }), [totals, priorTotals, getGoal, live, daysElapsed, daysInPeriod]);

  const ribbons = useMemo(() => rankRibbons(
    sourceMetrics.map(s => ({
      source: s.canonicalSource,
      revenue: s.revenue,
      adSpend: s.adSpend,
      opportunities: s.opportunities,
      priorRevenue: priorSourceMetrics.find(p => p.canonicalSource === s.canonicalSource)?.revenue ?? 0,
    })),
  ), [sourceMetrics, priorSourceMetrics]);

  const callsBySource = useMemo(() => {
    const out: Record<string, number> = {};
    for (const r of live.rows) out[r.sourceLabel] = (out[r.sourceLabel] ?? 0) + 1;
    return out;
  }, [live.rows]);

  const funnel = useMemo(() => computeFunnel({
    callsBySource,
    sourceMetrics,
    bookedBySource: Object.fromEntries(sourceMetrics.map(s => [s.canonicalSource, Math.round(s.opportunities * (s.completedJobs / Math.max(s.opportunities, 1)))])),
    paidBySource: Object.fromEntries(sourceMetrics.map(s => [s.canonicalSource, s.completedJobs])),
  }), [callsBySource, sourceMetrics]);

  const alerts = useMemo(() => computeDropOffAlerts(funnel), [funnel]);
  const roiGoal = getGoal("roi_target") ?? 3.0;

  const featuredSources = ["Google Ads", "Meta Ads", "Google LSA"];
  const ghlMetric = sourceMetrics.find(s => s.canonicalSource === "GoHighLevel" || s.canonicalSource === "GHL");

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-primary flex items-center gap-2">
          <BarChart3 className="h-5 w-5" />
          Marketing Source ROI
        </h1>
      </div>

      {/* Filter bar */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="inline-flex gap-1 bg-card border border-border rounded-xl p-1">
          {PERIOD_BUTTONS.map(b => (
            <button
              key={b.key}
              onClick={() => setPeriod(b.key)}
              className={`px-3 py-1.5 text-xs font-bold rounded-lg ${period === b.key ? "bg-primary text-primary-foreground" : "text-muted-foreground"}`}
            >
              {b.label}
            </button>
          ))}
        </div>
        {period === "custom" && <DateRangePicker dateRange={customRange} setDateRange={setCustomRange} />}
        <Select value={jobTypeFilter} onValueChange={v => setJobTypeFilter(v as JobTypeFilter)}>
          <SelectTrigger className="w-[140px]"><SelectValue placeholder="Job Type" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Types</SelectItem>
            <SelectItem value="repair">Repair Only</SelectItem>
            <SelectItem value="installation">Install Only</SelectItem>
          </SelectContent>
        </Select>
        <Select value={technicianId} onValueChange={setTechnicianId}>
          <SelectTrigger className="w-[160px]"><SelectValue placeholder="Technician" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Technicians</SelectItem>
            {technicians.map(t => <SelectItem key={t.id} value={t.id}>{t.name}</SelectItem>)}
          </SelectContent>
        </Select>
        <div className="ml-auto">
          <ExportButtons
            onExportExcel={() => exportToExcel("Marketing-ROI", [{
              name: "Sources",
              headers: ["Source","Revenue","Ad Spend","Opportunities","Conv. %","Opp. Avg","Repair Avg","Install Avg"],
              rows: sourceMetrics.map(s => [
                s.canonicalSource, fmtCurrency(s.revenue), fmtCurrency(s.adSpend),
                s.opportunities, fmtPercent(s.conversionRate),
                fmtCurrency(s.avgOpportunity), fmtCurrency(s.avgRepair), fmtCurrency(s.avgInstallation),
              ]),
            }])}
            onExportPDF={() => exportToPDF("Marketing-ROI", {
              title: "Marketing Source ROI",
              subtitle: "Twins Garage Doors",
              sections: [{
                title: "Source Breakdown",
                headers: ["Source","Revenue","Ad Spend","Opps","Conv%","Opp Avg","Repair","Install"],
                rows: sourceMetrics.map(s => [
                  s.canonicalSource, fmtCurrency(s.revenue), fmtCurrency(s.adSpend),
                  s.opportunities, fmtPercent(s.conversionRate),
                  fmtCurrency(s.avgOpportunity), fmtCurrency(s.avgRepair), fmtCurrency(s.avgInstallation),
                ]),
              }],
            })}
          />
        </div>
      </div>

      {/* Stoplight hero */}
      <MarketingStoplightHero roi={tiles.roi} pacing={tiles.pacing} live={tiles.live} />

      {/* Channel scorecards */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {sourceMetrics
          .filter(s => featuredSources.includes(s.canonicalSource))
          .map(s => (
            <ChannelScorecardCard
              key={s.canonicalSource}
              kind="paid"
              name={s.canonicalSource}
              subtitle={`${s.opportunities} leads · ${(s.dailySeries ?? []).length} active days`}
              kpis={{
                spend: s.adSpend, leads: s.opportunities, revenue: s.revenue,
                roi: s.adSpend > 0 ? s.revenue / s.adSpend : 0,
                costPerLead: s.adLeads > 0 ? s.adSpend / s.adLeads : 0,
                closeRate: s.opportunities > 0 ? s.completedJobs / s.opportunities : 0,
              }}
              sparkline={s.dailySeries ?? []}
              ribbon={ribbons.get(s.canonicalSource) ?? null}
              roiGoal={roiGoal}
            />
          ))}
        {ghlMetric && (
          <ChannelScorecardCard
            kind="calls"
            name="GHL · Calls & Forms"
            subtitle={`${live.callsToday} today · webhook live`}
            kpis={{
              calls: ghlMetric.opportunities, booked: ghlMetric.completedJobs, revenue: ghlMetric.revenue,
              bookRate: ghlMetric.opportunities > 0 ? ghlMetric.completedJobs / ghlMetric.opportunities : 0,
            }}
            sparkline={ghlMetric.dailySeries ?? []}
            ribbon={ribbons.get(ghlMetric.canonicalSource) ?? null}
            roiGoal={roiGoal}
          />
        )}
        <ChannelScorecardCard
          kind="organic"
          name="GA4 · Organic / Direct"
          subtitle="not connected"
          kpis={{ sessions: null, formFills: null, revenue: 0 }}
          sparkline={[]}
          ribbon={null}
          roiGoal={roiGoal}
        />
        <ChannelScorecardConnect
          channel="Google LSA"
          hint="Local Services Ads — leads with green checkmark"
          setupTime="~10 min OAuth setup"
        />
      </div>

      {/* Funnel + live feed */}
      <div className="grid grid-cols-1 md:grid-cols-[1.4fr_1fr] gap-4">
        <LeadFunnelPanel funnel={funnel} alerts={alerts} />
        <LiveLeadFeed rows={live.rows} />
      </div>

      {/* Source table */}
      <div>
        <h2 className="text-lg font-semibold mb-4">All Marketing Sources</h2>
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary" />
          </div>
        ) : (
          <MarketingSourceTable data={sourceMetrics} />
        )}
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Type-check**

```bash
npx tsc --noEmit
```

Expected: no new errors.

- [ ] **Step 4: Run all tests**

```bash
npm test -- --run
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/pages/MarketingSourceROI.tsx
git commit -m "feat(marketing-roi): refactor page into stoplight + channel cards + funnel + live feed"
```

---

## Task 16: Math reconciliation integration test

**Files:**
- Create: `src/pages/__tests__/MarketingSourceROI.reconciliation.test.tsx`

The non-negotiable guard: hero ROI must equal `sum(channel-card revenue) / sum(channel-card spend)` to within 1 cent on the same date range.

- [ ] **Step 1: Write the test**

Create `src/pages/__tests__/MarketingSourceROI.reconciliation.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { computeStoplightTiles } from "@/hooks/use-stoplight-metrics";
import type { MarketingSourceMetrics } from "@/hooks/use-marketing-source-roi";

const sm = (overrides: Partial<MarketingSourceMetrics> & { canonicalSource: string }): MarketingSourceMetrics => ({
  canonicalSource: overrides.canonicalSource,
  rawSources: [],
  revenue: overrides.revenue ?? 0,
  opportunities: overrides.opportunities ?? 0,
  completedJobs: overrides.completedJobs ?? 0,
  conversionRate: 0,
  avgOpportunity: 0,
  avgRepair: 0,
  avgInstallation: 0,
  repairCount: 0,
  installationCount: 0,
  repairRevenue: 0,
  installationRevenue: 0,
  adSpend: overrides.adSpend ?? 0,
  adClicks: 0,
  adLeads: 0,
  hasAdSpendData: !!overrides.adSpend,
  costPerLead: 0,
  roi: 0,
  dailySeries: [],
});

describe("Marketing ROI math reconciliation", () => {
  it("hero ROI equals sum(card revenue) / sum(card spend)", () => {
    const cards = [
      sm({ canonicalSource: "Google Ads", revenue: 17200, adSpend: 4210 }),
      sm({ canonicalSource: "Meta Ads", revenue: 5980, adSpend: 2140 }),
      sm({ canonicalSource: "GHL", revenue: 18420, adSpend: 0 }),
    ];
    const totalRevenue = cards.reduce((s, c) => s + c.revenue, 0);
    const totalAdSpend = cards.reduce((s, c) => s + c.adSpend, 0);
    const tiles = computeStoplightTiles({
      currentTotals: { totalRevenue, totalAdSpend },
      priorTotals: { totalRevenue: 0 },
      goalRevenue: 50000,
      goalRoi: 3.0,
      liveCounts: { callsToday: 0, bookedToday: 0, lastCallAt: null },
      daysElapsed: 18, daysInPeriod: 30, asOf: new Date(),
    });
    const heroRoiNum = parseFloat(tiles.roi.big.replace("×", ""));
    const expected = totalRevenue / totalAdSpend;
    expect(Math.abs(heroRoiNum - expected)).toBeLessThan(0.05); // 1-decimal display tolerance
  });
});
```

- [ ] **Step 2: Run the test**

```bash
npm test -- --run src/pages/__tests__/MarketingSourceROI.reconciliation.test.tsx
```

Expected: 1 test passes.

- [ ] **Step 3: Commit**

```bash
git add src/pages/__tests__/MarketingSourceROI.reconciliation.test.tsx
git commit -m "test(marketing-roi): hero ROI reconciliation guard"
```

---

## Task 17: Manual smoke + push branch

- [ ] **Step 1: Run full test suite**

```bash
npm test -- --run
```

Expected: all tests pass.

- [ ] **Step 2: Type-check**

```bash
npx tsc --noEmit
```

Expected: no new errors. (Pre-existing repo warnings are out of scope.)

- [ ] **Step 3: Build**

```bash
npm run build
```

Expected: build succeeds.

- [ ] **Step 4: Local dev smoke**

```bash
npm run dev
```

Open the dev URL, navigate to `/marketing-roi`. Verify:

1. Stoplight hero renders with three navy-yellow tiles.
2. Period toggle changes the URL query and refreshes the page.
3. At least one channel card renders for Google Ads (assuming local DB has spend data).
4. Live feed renders (empty state if no calls today is fine).
5. Funnel panel renders.
6. Source table renders below.
7. Visiting `/marketing-roi?legacy=1` renders the old page byte-for-byte.

- [ ] **Step 5: Push branch**

```bash
git push -u origin feat/marketing-roi-revamp
```

- [ ] **Step 6: Open PR via GitHub API**

Per `reference_gh_via_api.md` in memory, use the GitHub API directly (not `gh` CLI). Token from osxkeychain:

```bash
TOKEN=$(printf "host=github.com\nprotocol=https\n\n" | git credential-osxkeychain get | awk -F= '/^password=/ {print $2}')
curl -s -X POST -H "Authorization: token $TOKEN" -H "Accept: application/vnd.github+json" \
  https://api.github.com/repos/palpulla/twins-dash/pulls \
  -d '{"title":"feat(marketing-roi): five-section revamp (Phase 1)","head":"feat/marketing-roi-revamp","base":"main","body":"Refactor of MarketingSourceROI.tsx per spec docs/superpowers/specs/2026-05-01-marketing-roi-revamp-design.md and plan docs/superpowers/plans/2026-05-01-marketing-roi-revamp.md.\n\nNo new APIs, no schema changes. Old page reachable at ?legacy=1 for 30 days.\n\nCo-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"}'
```

Expected: returns a PR URL. Share it with Daniel for review.

- [ ] **Step 7: Production verification**

After PR merge, navigate to https://twinsdash.com/marketing-roi on Daniel's phone and verify:

1. Hero ROI value matches `https://twinsdash.com/marketing-roi?legacy=1` on the same period.
2. Page is legible at 375 px width.
3. Funnel and live feed render.

Do not merge until Daniel manually verifies the production page.

---

## Self-review checklist (already run)

**1. Spec coverage:**

| Spec section | Plan task |
|---|---|
| Filter bar (period segmented + filters + URL state) | Task 1 (url-state), Task 15 (page) |
| Stoplight hero | Task 5 (hook), Task 9 (component) |
| Channel scorecards grid | Task 2 (ribbon-ranking), Task 8 (dailySeries), Task 10 (component), Task 11 (placeholder), Task 15 (page) |
| Lead funnel + drop-off alerts | Task 3 (drop-off-alerts), Task 7 (funnel hook), Task 12 (component) |
| Live feed | Task 6 (hook), Task 13 (component) |
| All-sources table | Task 15 (page) — existing component reused |
| `roi_target` goal | Task 4 |
| Legacy `?legacy=1` gate | Task 14 (snapshot), Task 15 (gate) |
| Math reconciliation | Task 16 |

**2. Placeholder scan:** No "TBD"/"TODO"/"add appropriate" placeholders. Each task has complete code blocks.

**3. Type consistency:** `MarketingSourceMetrics.dailySeries` declared in Task 8, consumed in Task 10/15. `StoplightTile` declared in Task 5, consumed in Task 9/15. `FeedRow` declared in Task 6, consumed in Task 13. `FunnelOutput` declared in Task 7, consumed in Task 12.

**4. Open spec questions:**
- Speed-to-lead — dropped from Phase 1's Live tile per spec resolution. Live tile uses last-call-at instead.
- GA4 top-page placeholder — `subtitle="not connected"` on the GA4 card.
- Drop-off alert hints — 4 hand-written hints embedded in Task 3.
