# GHL Booking Rate Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a booking-rate report (combined + per-GHL-account) to the existing GHL attribution panel on `/marketing-roi`, using data already in Supabase.

**Architecture:** The GHL matcher already sets `matched_hcp_job_id` on `ghl_contacts` rows. "Booking rate" = matched ÷ total contacts. We extract the existing inline aggregation in `useGhlSummary` into a pure, testable `aggregateGhlSummary` function, extend it with a per-`ghl_location_id` breakdown plus a maturation count, add a static account-label map, and rewrite `GhlAttributionPanel` to show a booking-rate headline and a per-account table. No schema change, no new GHL API calls, no parent-page change.

**Tech Stack:** React + TypeScript, TanStack Query, Vitest + React Testing Library, Tailwind, Supabase JS client.

**Repo:** `~/twins-dashboard/twins-dash` (its own git repo). Spec: `docs/superpowers/specs/2026-05-22-ghl-booking-rate-design.md` (outer repo).

---

## File Structure

**New files (in `twins-dash`):**
- `src/lib/ghl/ghl-accounts.ts` — static `ghl_location_id → display label` map + `ghlAccountLabel()` helper.
- `src/lib/ghl/__tests__/ghl-accounts.test.ts` — tests for the label helper.
- `src/hooks/__tests__/use-ghl-summary.test.ts` — tests for the extracted `aggregateGhlSummary` pure function.
- `src/components/marketing-roi/__tests__/GhlAttributionPanel.test.tsx` — tests for the rewritten panel.

**Modified files:**
- `src/hooks/use-ghl-summary.ts` — extract `aggregateGhlSummary`, add `perAccount` + `bookingRate` + `immatureContacts` to `GhlSummary`, add `ghl_location_id`/`contact_created_at` to the Supabase select.
- `src/components/marketing-roi/GhlAttributionPanel.tsx` — replace the 4-stat grid with a booking-rate headline + per-account table + maturation caption.

**Not modified:** `src/pages/MarketingSourceROI.tsx` — it already passes `summary={ghlSummary}`; the `GhlSummary` shape only gains fields, so the page needs no change.

---

## Task 1: Worktree setup

**Files:** none (git only)

- [ ] **Step 1: Create the worktree and branch**

```bash
cd ~/twins-dashboard/twins-dash
git worktree add .worktrees/ghl-booking-rate -b feat/ghl-booking-rate main
cd .worktrees/ghl-booking-rate
```

- [ ] **Step 2: Verify the worktree is on the new branch**

Run: `git branch --show-current`
Expected: `feat/ghl-booking-rate`

- [ ] **Step 3: Install dependencies in the worktree**

Run: `npm install`
Expected: completes without errors. (A fresh worktree has no `node_modules`.)

All remaining tasks run from `~/twins-dashboard/twins-dash/.worktrees/ghl-booking-rate`.

---

## Task 2: Account label map

**Files:**
- Create: `src/lib/ghl/ghl-accounts.ts`
- Test: `src/lib/ghl/__tests__/ghl-accounts.test.ts`

- [ ] **Step 1: Write the failing test**

Create `src/lib/ghl/__tests__/ghl-accounts.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { ghlAccountLabel } from "../ghl-accounts";

describe("ghlAccountLabel", () => {
  it("returns the known label for Dunzo's location", () => {
    expect(ghlAccountLabel("iRUlbIBg7PzSfLrPiR2j")).toBe("Dunzo");
  });

  it("falls back to a truncated-ID label for an unknown location", () => {
    expect(ghlAccountLabel("someUnknownLocationXYZ12")).toBe("Account XYZ12");
  });

  it("uses the whole id when it is 5 characters or shorter", () => {
    expect(ghlAccountLabel("ab12")).toBe("Account ab12");
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run src/lib/ghl/__tests__/ghl-accounts.test.ts`
Expected: FAIL — cannot resolve `../ghl-accounts`.

- [ ] **Step 3: Write the implementation**

Create `src/lib/ghl/ghl-accounts.ts`:

```ts
// Display labels for GHL accounts, keyed by ghl_location_id.
//
// Adding a new account: add one line to GHL_ACCOUNT_LABELS below. The nightly
// sync function (supabase/functions/sync-ghl-contacts) discovers accounts from
// GHL_API_KEY_n / GHL_LOCATION_ID_n secrets; this map only controls how a
// location is labelled in the dashboard. An account whose location ID is not
// listed here still renders — it just gets a generic fallback label.

const GHL_ACCOUNT_LABELS: Record<string, string> = {
  iRUlbIBg7PzSfLrPiR2j: "Dunzo",
  // Twins' own GHL location ID goes here once its API key is configured.
};

/** Human label for a GHL location ID. Unknown IDs get a stable fallback. */
export function ghlAccountLabel(locationId: string): string {
  const known = GHL_ACCOUNT_LABELS[locationId];
  if (known) return known;
  const suffix = locationId.length > 5 ? locationId.slice(-5) : locationId;
  return `Account ${suffix}`;
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run src/lib/ghl/__tests__/ghl-accounts.test.ts`
Expected: PASS — 3 tests.

- [ ] **Step 5: Commit**

```bash
git add src/lib/ghl/ghl-accounts.ts src/lib/ghl/__tests__/ghl-accounts.test.ts
git commit -m "feat(ghl): add account label map for booking-rate report"
```

---

## Task 3: Per-account aggregation in useGhlSummary

**Files:**
- Modify: `src/hooks/use-ghl-summary.ts`
- Test: `src/hooks/__tests__/use-ghl-summary.test.ts`

This task extracts the inline aggregation from the hook's `queryFn` into an exported pure function `aggregateGhlSummary`, extends `GhlSummary` with `perAccount`, `bookingRate`, and `immatureContacts`, and adds `ghl_location_id` + `contact_created_at` to the Supabase select.

- [ ] **Step 1: Write the failing test**

Create `src/hooks/__tests__/use-ghl-summary.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { aggregateGhlSummary, type GhlContactRow } from "../use-ghl-summary";

function row(over: Partial<GhlContactRow>): GhlContactRow {
  return {
    ghl_location_id: "loc-a",
    contact_created_at: "2026-01-01T00:00:00Z",
    source: null,
    matched_hcp_job_id: null,
    attribution_source: null,
    raw_payload: null,
    tags: null,
    ...over,
  };
}

const NOW = new Date("2026-05-22T00:00:00Z");

describe("aggregateGhlSummary", () => {
  it("computes combined booking rate as booked / total", () => {
    const rows = [
      row({ matched_hcp_job_id: "j1" }),
      row({ matched_hcp_job_id: "j2" }),
      row({ matched_hcp_job_id: null }),
      row({ matched_hcp_job_id: null }),
    ];
    const s = aggregateGhlSummary(rows, NOW);
    expect(s.totalContacts).toBe(4);
    expect(s.matchedToHcp).toBe(2);
    expect(s.bookingRate).toBe(0.5);
  });

  it("breaks contacts and booking rate down per ghl_location_id", () => {
    const rows = [
      row({ ghl_location_id: "loc-a", matched_hcp_job_id: "j1" }),
      row({ ghl_location_id: "loc-a", matched_hcp_job_id: null }),
      row({ ghl_location_id: "loc-b", matched_hcp_job_id: "j2" }),
    ];
    const s = aggregateGhlSummary(rows, NOW);
    expect(s.perAccount).toHaveLength(2);
    const a = s.perAccount.find((p) => p.locationId === "loc-a")!;
    const b = s.perAccount.find((p) => p.locationId === "loc-b")!;
    expect(a.contacts).toBe(2);
    expect(a.booked).toBe(1);
    expect(a.bookingRate).toBe(0.5);
    expect(b.bookingRate).toBe(1);
  });

  it("sorts perAccount by contact volume descending", () => {
    const rows = [
      row({ ghl_location_id: "small" }),
      row({ ghl_location_id: "big" }),
      row({ ghl_location_id: "big" }),
      row({ ghl_location_id: "big" }),
    ];
    const s = aggregateGhlSummary(rows, NOW);
    expect(s.perAccount[0].locationId).toBe("big");
  });

  it("returns a null combined booking rate when there are no rows", () => {
    const s = aggregateGhlSummary([], NOW);
    expect(s.totalContacts).toBe(0);
    expect(s.bookingRate).toBeNull();
    expect(s.perAccount).toEqual([]);
  });

  it("counts contacts created within 30 days of now as immature", () => {
    const rows = [
      row({ contact_created_at: "2026-05-20T00:00:00Z" }), // 2 days before NOW
      row({ contact_created_at: "2026-05-10T00:00:00Z" }), // 12 days before NOW
      row({ contact_created_at: "2026-01-01T00:00:00Z" }), // old
    ];
    const s = aggregateGhlSummary(rows, NOW);
    expect(s.immatureContacts).toBe(2);
  });

  it("applies the static account label map", () => {
    const s = aggregateGhlSummary([row({ ghl_location_id: "iRUlbIBg7PzSfLrPiR2j" })], NOW);
    expect(s.perAccount[0].label).toBe("Dunzo");
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run src/hooks/__tests__/use-ghl-summary.test.ts`
Expected: FAIL — `aggregateGhlSummary` and `GhlContactRow` are not exported.

- [ ] **Step 3: Rewrite `src/hooks/use-ghl-summary.ts`**

Replace the entire file contents with:

```ts
// useGhlSummary — pulls GHL contact volume, HCP-match coverage, per-account
// booking rate, and the top dialed-tracking-number names ("source") for the
// date range. Powers the GHL Attribution panel on /marketing-roi.

import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";
import type { DateRange } from "react-day-picker";
import { ghlSourceToCanonical } from "@/lib/ghl/source-mapper";
import { ghlAccountLabel } from "@/lib/ghl/ghl-accounts";

/** A 30-day forward window is how the GHL matcher links a contact to a job. */
const MATCH_WINDOW_DAYS = 30;

export interface GhlSourceBreakdown {
  /** Raw GHL `source` field. "WI Google LSA", "Facebook Ads", null, etc. */
  rawSource: string | null;
  count: number;
}

/** One row of `public.ghl_contacts` as selected by this hook. */
export interface GhlContactRow {
  ghl_location_id: string;
  contact_created_at: string;
  source: string | null;
  matched_hcp_job_id: string | null;
  attribution_source: unknown;
  raw_payload: { customField?: Array<{ fieldKey?: string; value?: string }> } | null;
  tags: string[] | null;
}

/** Booking-rate breakdown for a single GHL account (location). */
export interface GhlAccountSummary {
  locationId: string;
  /** Human label from the static account map. */
  label: string;
  /** Contacts created in the date range for this account. */
  contacts: number;
  /** Subset matched to an HCP job — the "booked" count. */
  booked: number;
  /** booked / contacts, as a 0..1 fraction. Null when contacts === 0. */
  bookingRate: number | null;
}

export interface GhlSummary {
  /** Total GHL contacts created in the date range, across all locations. */
  totalContacts: number;
  /** Subset matched to an HCP job by phone-number within 30 days. */
  matchedToHcp: number;
  /** Subset whose GHL `source` field is non-null (workflow attributed it). */
  withSource: number;
  /** Contacts whose source/utm/customField produces a non-Other canonical. */
  channelMapped: number;
  /** Top dialed-tracking-number names (descending by count). */
  topSources: GhlSourceBreakdown[];
  /** Combined booking rate: matchedToHcp / totalContacts, 0..1. Null if no contacts. */
  bookingRate: number | null;
  /** Per-account booking-rate breakdown, sorted by contact volume descending. */
  perAccount: GhlAccountSummary[];
  /** Contacts created within the last 30 days — still inside the match window. */
  immatureContacts: number;
}

const EMPTY: GhlSummary = {
  totalContacts: 0,
  matchedToHcp: 0,
  withSource: 0,
  channelMapped: 0,
  topSources: [],
  bookingRate: null,
  perAccount: [],
  immatureContacts: 0,
};

/**
 * Pure aggregation of GHL contact rows into a GhlSummary. Extracted from the
 * hook so it can be unit-tested without a Supabase client. `now` is injectable
 * so the maturation cutoff is deterministic in tests.
 */
export function aggregateGhlSummary(
  rows: GhlContactRow[],
  now: Date = new Date(),
): GhlSummary {
  const counts = new Map<string, number>();
  const perAccountMap = new Map<string, { contacts: number; booked: number }>();
  let matchedToHcp = 0;
  let withSource = 0;
  let channelMapped = 0;
  let immatureContacts = 0;
  const immatureCutoff = now.getTime() - MATCH_WINDOW_DAYS * 86_400_000;

  for (const r of rows) {
    const acct = perAccountMap.get(r.ghl_location_id) ?? { contacts: 0, booked: 0 };
    acct.contacts++;
    if (r.matched_hcp_job_id) {
      matchedToHcp++;
      acct.booked++;
    }
    perAccountMap.set(r.ghl_location_id, acct);

    if (r.contact_created_at && new Date(r.contact_created_at).getTime() >= immatureCutoff) {
      immatureContacts++;
    }

    const src = (r.source ?? "").trim();
    if (src) {
      withSource++;
      counts.set(src, (counts.get(src) ?? 0) + 1);
    }

    // Inject customField into attributionSource so the mapper can see gclid.
    const attrWithCf =
      r.attribution_source && typeof r.attribution_source === "object"
        ? { ...(r.attribution_source as object), customField: r.raw_payload?.customField ?? [] }
        : r.raw_payload?.customField
          ? { customField: r.raw_payload.customField }
          : null;
    const canonical = ghlSourceToCanonical(r.source, attrWithCf, r.tags ?? []);
    if (canonical && !canonical.startsWith("Other (")) channelMapped++;
  }

  const topSources = Array.from(counts.entries())
    .sort((a, b) => b[1] - a[1])
    .slice(0, 6)
    .map(([rawSource, count]) => ({ rawSource, count }));

  const perAccount: GhlAccountSummary[] = Array.from(perAccountMap.entries())
    .map(([locationId, v]) => ({
      locationId,
      label: ghlAccountLabel(locationId),
      contacts: v.contacts,
      booked: v.booked,
      bookingRate: v.contacts > 0 ? v.booked / v.contacts : null,
    }))
    .sort((a, b) => b.contacts - a.contacts);

  const totalContacts = rows.length;
  return {
    totalContacts,
    matchedToHcp,
    withSource,
    channelMapped,
    topSources,
    bookingRate: totalContacts > 0 ? matchedToHcp / totalContacts : null,
    perAccount,
    immatureContacts,
  };
}

export function useGhlSummary(
  dateRange: DateRange | undefined,
): { data: GhlSummary; isLoading: boolean } {
  const { data, isLoading } = useQuery({
    queryKey: ["ghl-summary", dateRange?.from?.getTime(), dateRange?.to?.getTime()],
    queryFn: async (): Promise<GhlSummary> => {
      if (!dateRange?.from || !dateRange?.to) return EMPTY;
      const fromIso = dateRange.from.toISOString();
      const toIso = dateRange.to.toISOString();
      const { data, error } = await supabase
        .from("ghl_contacts" as never)
        .select(
          "ghl_location_id, contact_created_at, source, matched_hcp_job_id, attribution_source, raw_payload, tags",
        )
        .gte("contact_created_at", fromIso)
        .lte("contact_created_at", toIso);
      if (error) throw error;
      return aggregateGhlSummary((data as GhlContactRow[] | null) ?? []);
    },
    staleTime: 60_000,
    refetchOnWindowFocus: false,
    enabled: !!dateRange?.from && !!dateRange?.to,
  });
  return { data: data ?? EMPTY, isLoading };
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run src/hooks/__tests__/use-ghl-summary.test.ts`
Expected: PASS — 6 tests.

- [ ] **Step 5: Commit**

```bash
git add src/hooks/use-ghl-summary.ts src/hooks/__tests__/use-ghl-summary.test.ts
git commit -m "feat(ghl): per-account booking rate in useGhlSummary"
```

---

## Task 4: Booking-rate UI in GhlAttributionPanel

**Files:**
- Modify: `src/components/marketing-roi/GhlAttributionPanel.tsx`
- Test: `src/components/marketing-roi/__tests__/GhlAttributionPanel.test.tsx`

The 4-stat grid is replaced by a booking-rate headline, a per-account table (Account · Leads · Booked · Rate) with an "All accounts" total row when there is more than one account, and a maturation caption. The top-sources list stays; source-attributed / channel-mapped collapse into one caption line.

- [ ] **Step 1: Write the failing test**

Create `src/components/marketing-roi/__tests__/GhlAttributionPanel.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { render, screen } from "@testing-library/react";
import { GhlAttributionPanel } from "../GhlAttributionPanel";
import type { GhlSummary } from "@/hooks/use-ghl-summary";

const base: GhlSummary = {
  totalContacts: 1000,
  matchedToHcp: 240,
  withSource: 600,
  channelMapped: 500,
  topSources: [{ rawSource: "WI Google LSA", count: 120 }],
  bookingRate: 0.24,
  perAccount: [
    { locationId: "loc-dunzo", label: "Dunzo", contacts: 800, booked: 200, bookingRate: 200 / 800 },
    { locationId: "loc-twins", label: "Twins", contacts: 200, booked: 40, bookingRate: 40 / 200 },
  ],
  immatureContacts: 0,
};

describe("GhlAttributionPanel", () => {
  it("renders the combined booking rate headline", () => {
    render(<GhlAttributionPanel summary={base} />);
    expect(screen.getByText(/240 of 1,000 leads booked/)).toBeInTheDocument();
  });

  it("renders one row per account with its rate", () => {
    render(<GhlAttributionPanel summary={base} />);
    expect(screen.getByText("Dunzo")).toBeInTheDocument();
    expect(screen.getByText("Twins")).toBeInTheDocument();
    expect(screen.getByText("25%")).toBeInTheDocument(); // Dunzo 200/800
    expect(screen.getByText("20%")).toBeInTheDocument(); // Twins 40/200
  });

  it("renders an All accounts total row when there is more than one account", () => {
    render(<GhlAttributionPanel summary={base} />);
    expect(screen.getByText("All accounts")).toBeInTheDocument();
  });

  it("shows the maturation caption when recent contacts are still maturing", () => {
    render(<GhlAttributionPanel summary={{ ...base, immatureContacts: 42 }} />);
    expect(screen.getByText(/42 contacts created in the last 30 days/)).toBeInTheDocument();
  });

  it("renders the empty state when there are no contacts", () => {
    render(
      <GhlAttributionPanel
        summary={{ ...base, totalContacts: 0, matchedToHcp: 0, bookingRate: null, perAccount: [] }}
      />,
    );
    expect(screen.getByText(/No GHL contacts in this period/)).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `npx vitest run src/components/marketing-roi/__tests__/GhlAttributionPanel.test.tsx`
Expected: FAIL — the panel does not yet render "leads booked", per-account rows, or "All accounts".

- [ ] **Step 3: Rewrite `src/components/marketing-roi/GhlAttributionPanel.tsx`**

Replace the entire file contents with:

```tsx
// GHL Attribution panel — shows GoHighLevel contact volume and the booking
// rate (share of contacts that became a real, phone-matched HCP job), broken
// down per GHL account plus a combined total. A "booking" is a matched HCP
// job; the matcher links a contact to a job created within 30 days.

import { Fragment } from "react";
import type { GhlSummary } from "@/hooks/use-ghl-summary";
import { InfoTip } from "./InfoTip";

export interface GhlAttributionPanelProps {
  summary: GhlSummary;
}

/** Format a 0..1 booking rate as a whole-percent string, or an em-dash when null. */
function formatRate(rate: number | null): string {
  if (rate === null) return "—";
  return `${Math.round(rate * 100)}%`;
}

export function GhlAttributionPanel({ summary }: GhlAttributionPanelProps) {
  const {
    totalContacts,
    matchedToHcp,
    withSource,
    channelMapped,
    topSources,
    bookingRate,
    perAccount,
    immatureContacts,
  } = summary;
  const sourcePct = totalContacts > 0 ? Math.round((withSource / totalContacts) * 100) : 0;
  const channelPct = totalContacts > 0 ? Math.round((channelMapped / totalContacts) * 100) : 0;

  return (
    <div className="bg-card rounded-2xl border border-border p-[18px] shadow-sm">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-[13px] font-extrabold text-primary uppercase tracking-wide flex items-center gap-1.5">
          GoHighLevel attribution
          <InfoTip text="GHL contacts pulled nightly from the v1 API. Booking rate is the share of those contacts that became a real HCP job — a contact is 'booked' when its phone number matched an HCP job created within 30 days. Sync runs at 04:15 UTC." />
        </h3>
      </div>

      {totalContacts === 0 ? (
        <div className="text-[12px] text-muted-foreground py-6 text-center">
          No GHL contacts in this period. Nightly sync runs at 04:15 UTC.
        </div>
      ) : (
        <>
          {/* Booking-rate headline */}
          <div className="bg-[#fafbfd] rounded-xl px-3 py-3 mb-3">
            <div className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
              Booking rate
              <InfoTip text="Booked contacts divided by total contacts. 'Booked' means the contact's phone number matched a real HCP job created within 30 days." />
            </div>
            <div className="flex items-baseline gap-2 mt-0.5">
              <span className="text-[28px] font-extrabold text-primary tabular-nums">
                {formatRate(bookingRate)}
              </span>
              <span className="text-[12px] text-muted-foreground tabular-nums">
                {matchedToHcp.toLocaleString()} of {totalContacts.toLocaleString()} leads booked
              </span>
            </div>
          </div>

          {/* Per-account table */}
          <div className="mb-3">
            <div className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">
              By account
            </div>
            <div className="grid grid-cols-[1fr_auto_auto_auto] gap-x-4 gap-y-1 text-[12px]">
              <div className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Account</div>
              <div className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider text-right">Leads</div>
              <div className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider text-right">Booked</div>
              <div className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider text-right">Rate</div>

              {perAccount.map((a) => (
                <Fragment key={a.locationId}>
                  <div className="truncate">{a.label}</div>
                  <div className="text-right tabular-nums">{a.contacts.toLocaleString()}</div>
                  <div className="text-right tabular-nums">{a.booked.toLocaleString()}</div>
                  <div className="text-right tabular-nums font-semibold text-primary">
                    {formatRate(a.bookingRate)}
                  </div>
                </Fragment>
              ))}

              {perAccount.length > 1 && (
                <Fragment>
                  <div className="font-semibold border-t border-[#eef1f7] pt-1">All accounts</div>
                  <div className="text-right tabular-nums font-semibold border-t border-[#eef1f7] pt-1">
                    {totalContacts.toLocaleString()}
                  </div>
                  <div className="text-right tabular-nums font-semibold border-t border-[#eef1f7] pt-1">
                    {matchedToHcp.toLocaleString()}
                  </div>
                  <div className="text-right tabular-nums font-semibold text-primary border-t border-[#eef1f7] pt-1">
                    {formatRate(bookingRate)}
                  </div>
                </Fragment>
              )}
            </div>
          </div>

          {/* Maturation caption */}
          {immatureContacts > 0 && (
            <div className="mb-3 text-[11px] text-muted-foreground bg-[#fafbfd] border border-[#eef1f7] rounded-lg px-2.5 py-2 leading-relaxed">
              {immatureContacts.toLocaleString()} {immatureContacts === 1 ? "contact" : "contacts"} created
              in the last 30 days are still inside the 30-day match window and may yet convert. Booking rate
              for recent periods will rise as matches land.
            </div>
          )}

          {/* Top sources */}
          {topSources.length > 0 && (
            <div>
              <div className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-1.5">
                Top dialed numbers / sources
              </div>
              <div className="space-y-1">
                {topSources.map((s) => (
                  <div
                    key={s.rawSource ?? "unknown"}
                    className="flex justify-between items-center py-1.5 px-1 border-b border-[#eef1f7] last:border-b-0 text-[12px]"
                  >
                    <span className="flex-1">{s.rawSource}</span>
                    <span className="text-[11px] font-semibold text-primary tabular-nums">{s.count}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Attribution-coverage caption */}
          <div className="mt-3 text-[11px] text-muted-foreground">
            Source attributed: {sourcePct}% · Channel-mapped: {channelPct}%
          </div>
        </>
      )}
    </div>
  );
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `npx vitest run src/components/marketing-roi/__tests__/GhlAttributionPanel.test.tsx`
Expected: PASS — 5 tests.

- [ ] **Step 5: Commit**

```bash
git add src/components/marketing-roi/GhlAttributionPanel.tsx src/components/marketing-roi/__tests__/GhlAttributionPanel.test.tsx
git commit -m "feat(ghl): booking-rate headline and per-account table on attribution panel"
```

---

## Task 5: Full verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `npx vitest run`
Expected: PASS — all suites green, including the pre-existing `marketing-roi` and `hooks` tests. If any pre-existing test fails, it must be because of a real regression introduced here — diagnose and fix before continuing.

- [ ] **Step 2: Typecheck**

Run: `npx tsc --noEmit -p tsconfig.app.json`
Expected: no output (exit 0). The new `perAccount` / `bookingRate` / `immatureContacts` fields must be consistent everywhere `GhlSummary` is used.

- [ ] **Step 3: Lint**

Run: `npm run lint`
Expected: no new errors in `ghl-accounts.ts`, `use-ghl-summary.ts`, or `GhlAttributionPanel.tsx`.

- [ ] **Step 4: Visual check in the browser**

Run: `npm run dev`, open `/marketing-roi`, scroll to the "GoHighLevel attribution" panel. Confirm: a booking-rate headline (≈24% for the default range), a "By account" table with a "Dunzo" row, and — since the date range likely includes the last 30 days — the maturation caption. With only one account configured, no "All accounts" total row appears (correct).

- [ ] **Step 5: Commit (only if Step 1–3 required fixes)**

```bash
git add -A
git commit -m "fix(ghl): resolve verification findings for booking-rate report"
```

If Steps 1–3 passed clean, skip this step — there is nothing to commit.

---

## Done

The booking-rate report is live on `/marketing-roi` for every configured GHL account. When Twins' own GHL API key is available:

1. Add Supabase secrets `GHL_API_KEY_2` + `GHL_LOCATION_ID_2` (Project Settings → Edge Functions).
2. Add Twins' location ID + label to `GHL_ACCOUNT_LABELS` in `src/lib/ghl/ghl-accounts.ts`.

The next nightly sync picks up the second account and the panel shows both rows plus the "All accounts" total automatically — no further code change.
```
