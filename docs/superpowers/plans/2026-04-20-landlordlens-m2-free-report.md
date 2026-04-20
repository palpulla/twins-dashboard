# LandlordLens M2 — Email-gated Free Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> **SCOPE AMENDMENT 2026-04-20 (mid-execution):** SoS scrapers (Tasks 9–13) and NSOPW scraper (Task 14) are **removed from M2** and consolidated into M3. Reason: real-portal scraping from the current environment hit network and anti-bot walls (WI DNS unreachable, IL HTTP/2 blocks, MN needs full POST flow, NSOPW is JS-rendered). All four portals need either browser-based investigation or a headless-browser-in-edge-function infrastructure which fits better alongside M3's already-planned state court scrapers.
>
> **Revised M2 ships:** Auth + email gate + simplified orchestrator (Owner Card + property snapshot + red-flag engine only, with honest "coming soon" placeholders for LLC piercing and SOR) + Report page + Dashboard + Home wire-up + deploy.
>
> **Still completed from original plan:** Tasks 1–8 (migrations, types, red-flag engine).
>
> **Task renumbering (new sequence):**
> - Tasks 1–8: unchanged (completed)
> - **New Task 9**: Simplified orchestrator (skip SoS + NSOPW calls)
> - **New Task 10**: Free-report HTTP handler + DB persistence + deploy
> - **New Task 11**: React Router setup
> - **New Task 12**: Auth helpers
> - **New Task 13**: EmailGateForm
> - **New Task 14**: Display components (LlcMembersCard, SorMatchCard, RedFlagSummary, DisclaimerBlock) — LLC and SOR cards render "coming soon" messaging when data is null
> - **New Task 15**: Report client + Report page + FreeReportSections
> - **New Task 16**: Dashboard page
> - **New Task 17**: Home wire-up (email gate + magic-link return)
> - **New Task 18**: Deploy + E2E smoke + tag v0.2.0-m2
>
> Red-flag engine rules remain; the two LLC-piercing rules (succeeded / failed-in-launch-state) can no longer fire under revised scope (llcMembers section always completes with null data). Engine logic does NOT need changes — those rules just don't trigger. The "LLC in non-launch state" rule widens implicitly to cover all LLCs. A new "LLC present — piercing coming soon" copy treatment is rendered by the LlcMembersCard component itself rather than by the engine.

**Goal:** Add Supabase Auth magic-link + Free Report layer (LLC piercing for WI/IL/MN, NSOPW sex-offender check, property snapshot expansion, rules-based red-flag summary) to the live LandlordLens product.

**Architecture:** Extend the existing M1 Supabase + React + Vite stack. Add 3 new edge functions (`sos-piercer`, `nsopw-scraper`, `free-report` orchestrator), 5 new DB tables, and 3 new frontend routes (`/login`, `/report/:id`, `/dashboard`). Red-flag engine is a pure function unit-tested in isolation. Scrapers are fixture-based TDD — capture real HTML once, build against fixture, verify live post-deploy.

**Tech Stack:**
- Frontend: React 18 + TypeScript + React Router + Tailwind + Supabase Auth
- Backend: Supabase (Postgres + Edge Functions + Auth)
- Data sources (unchanged M1): ATTOM for ownership
- Data sources (new M2):
  - WI Secretary of State: `wdfi.org/apps/corpsearch/`
  - IL Secretary of State: `apps.ilsos.gov/businessentitysearch/`
  - MN Secretary of State: `mblsportal.sos.mn.gov/`
  - NSOPW: `nsopw.gov`
- Testing: Vitest (frontend + shared logic), Deno test (edge functions)

**Spec:** [docs/superpowers/specs/2026-04-20-landlordlens-m2-free-report-design.md](../specs/2026-04-20-landlordlens-m2-free-report-design.md) (`df33ae8`)

**Milestone boundary:** This plan ships ONLY the Free Report. No criminal records, no Deep Report, no Stripe, no PDF, no landlord dispute flow — all M3/M4.

---

## Pre-flight

Before Task 1, confirm the following are in place (set up during M1):
- `landlordlens/` repo exists and pushes to `github.com/palpulla/landlordlens`
- Supabase project `viwrdwtxhpmcrjubomzp` is reachable and migrations are up to date
- Vercel project `landlordlens` is live
- `~/.config/landlordlens-credentials.env` contains: `SUPABASE_ACCESS_TOKEN`, `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_SERVICE_ROLE_KEY`, `ATTOM_API_KEY`, `GITHUB_TOKEN`, `VERCEL_TOKEN`, `SUPABASE_PROJECT_REF=viwrdwtxhpmcrjubomzp`
- Deno is at `/Users/daniel/.deno/bin/deno`

Source creds before any supabase CLI command: `set -a; source ~/.config/landlordlens-credentials.env; set +a`

---

## File Structure

### New files under `landlordlens/`

```
src/
├── lib/
│   ├── auth.ts                  # Supabase Auth wrappers
│   ├── report-client.ts         # API client for free-report endpoint
│   └── red-flag-engine.ts       # Pure rule engine, mirrored to edge fn
├── types/
│   ├── report.ts                # Report, ReportSection, SectionStatus
│   ├── finding.ts               # Finding (rule-engine output)
│   ├── llc-info.ts              # LlcInfo, LlcMember
│   └── sor-match.ts             # SorMatch
├── pages/
│   ├── Login.tsx                # /login — magic link form
│   ├── Report.tsx               # /report/:id — full Free Report view
│   └── Dashboard.tsx            # /dashboard — report history list
├── components/
│   ├── EmailGateForm.tsx        # inline on Home + Report
│   ├── FreeReportSections.tsx   # wraps the 4 new sections
│   ├── LlcMembersCard.tsx
│   ├── SorMatchCard.tsx         # one match = one card
│   ├── RedFlagSummary.tsx       # ordered Finding list
│   └── DisclaimerBlock.tsx      # NSOPW disclaimer wrapper
└── __tests__/
    ├── red-flag-engine.test.ts  # 8+ rule tests
    ├── LlcMembersCard.test.tsx
    ├── SorMatchCard.test.tsx
    ├── RedFlagSummary.test.tsx
    ├── EmailGateForm.test.tsx
    └── Dashboard.test.tsx

supabase/
├── migrations/
│   ├── 20260420000006_create_reports.sql
│   ├── 20260420000007_create_section_enums.sql
│   ├── 20260420000008_create_report_sections.sql
│   ├── 20260420000009_create_llc_members.sql
│   ├── 20260420000010_create_sor_matches.sql
│   └── 20260420000011_m2_rls_policies.sql
└── functions/
    ├── _shared/
    │   └── red-flag-engine.ts   # byte-identical copy of src/lib version
    ├── sos-piercer/
    │   ├── index.ts             # HTTP handler + state dispatcher
    │   ├── wi-sos.ts
    │   ├── il-sos.ts
    │   ├── mn-sos.ts
    │   ├── types.ts             # LlcInfo (mirrors src/types)
    │   ├── fixtures/
    │   │   ├── wi-sample.html
    │   │   ├── il-sample.html
    │   │   └── mn-sample.html
    │   ├── wi-sos.test.ts
    │   ├── il-sos.test.ts
    │   ├── mn-sos.test.ts
    │   └── index.test.ts        # dispatcher routing
    ├── nsopw-scraper/
    │   ├── index.ts             # HTTP handler
    │   ├── client.ts            # scraping logic
    │   ├── types.ts             # SorMatch
    │   ├── fixtures/
    │   │   └── nsopw-sample.html
    │   └── client.test.ts
    └── free-report/
        ├── index.ts             # HTTP handler
        ├── orchestrator.ts      # pure composition of services
        ├── db.ts                # DB persistence layer
        ├── types.ts             # Report, ReportSection
        └── orchestrator.test.ts
```

### File responsibilities

- **`src/lib/red-flag-engine.ts`** — Pure function. Input: `ReportData`. Output: `Finding[]`. No I/O, no async. Unit-testable with hand-crafted inputs.
- **`src/lib/auth.ts`** — Thin wrappers around Supabase Auth magic-link flow. One function per responsibility: `sendMagicLink(email, redirectTo)`, `getSession()`, `signOut()`.
- **`supabase/functions/sos-piercer/index.ts`** — HTTP entry. Parses `{state, llcName}`, dispatches to the right state scraper, caches in `llc_members`.
- **`supabase/functions/sos-piercer/{wi|il|mn}-sos.ts`** — One file per state. Each exports `async function search(llcName: string): Promise<LlcInfo | null>`. Common interface, state-specific HTML parsing.
- **`supabase/functions/nsopw-scraper/client.ts`** — Scraper + disclaimer capture + match-array builder. Caches in `sor_matches`.
- **`supabase/functions/free-report/orchestrator.ts`** — Pure composition. Takes three service functions (owner lookup, SoS piercer, NSOPW) + red-flag engine. Returns assembled `Report`. Testable with stubbed services.
- **`supabase/functions/free-report/index.ts`** — HTTP handler. Wires real services into `orchestrator`, persists result to DB.

---

## Task 1: `reports` table

**Files:**
- Create: `supabase/migrations/20260420000006_create_reports.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260420000006_create_reports.sql
create table public.reports (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  address_id uuid not null references public.addresses(id),
  tier text not null default 'free' check (tier in ('free', 'deep')),
  status text not null default 'pending' check (status in ('pending', 'complete', 'partial', 'failed')),
  created_at timestamptz not null default now(),
  expires_at timestamptz not null default now() + interval '30 days'
);

create index idx_reports_user on public.reports(user_id, created_at desc);
create index idx_reports_address on public.reports(address_id);
```

- [ ] **Step 2: Apply to remote**

```bash
cd /Users/daniel/twins-dashboard/landlordlens
set -a; source ~/.config/landlordlens-credentials.env; set +a
npx -y supabase@latest db push
```

Expected: `Applying migration 20260420000006_create_reports.sql... Finished supabase db push.`

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260420000006_create_reports.sql
git commit -m "feat(db): add reports table keyed on authenticated users"
```

---

## Task 2: Section enum types

**Files:**
- Create: `supabase/migrations/20260420000007_create_section_enums.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260420000007_create_section_enums.sql
create type public.section_type as enum (
  'owner_card',
  'llc_members',
  'sor',
  'property_snapshot',
  'red_flags'
);

create type public.section_status as enum (
  'pending',
  'complete',
  'failed'
);
```

- [ ] **Step 2: Apply and commit**

```bash
npx -y supabase@latest db push
git add supabase/migrations/20260420000007_create_section_enums.sql
git commit -m "feat(db): add section_type and section_status enums"
```

---

## Task 3: `report_sections` table

**Files:**
- Create: `supabase/migrations/20260420000008_create_report_sections.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260420000008_create_report_sections.sql
create table public.report_sections (
  id uuid primary key default gen_random_uuid(),
  report_id uuid not null references public.reports(id) on delete cascade,
  section_type public.section_type not null,
  status public.section_status not null default 'pending',
  data jsonb,
  source_attribution text,
  error_code text,
  error_message text,
  created_at timestamptz not null default now(),
  unique(report_id, section_type)
);

create index idx_report_sections_report on public.report_sections(report_id);
```

- [ ] **Step 2: Apply and commit**

```bash
npx -y supabase@latest db push
git add supabase/migrations/20260420000008_create_report_sections.sql
git commit -m "feat(db): add report_sections table with per-section error fields"
```

---

## Task 4: `llc_members` cache table

**Files:**
- Create: `supabase/migrations/20260420000009_create_llc_members.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260420000009_create_llc_members.sql
create table public.llc_members (
  id uuid primary key default gen_random_uuid(),
  llc_name_normalized text not null,
  state text not null,
  members jsonb not null default '[]'::jsonb,
  registered_agent text,
  filing_date date,
  source_url text,
  fetched_at timestamptz not null default now(),
  expires_at timestamptz not null default now() + interval '90 days',
  unique(llc_name_normalized, state)
);

create index idx_llc_members_expires on public.llc_members(expires_at);
```

- [ ] **Step 2: Apply and commit**

```bash
npx -y supabase@latest db push
git add supabase/migrations/20260420000009_create_llc_members.sql
git commit -m "feat(db): add llc_members cache table (90-day TTL)"
```

---

## Task 5: `sor_matches` cache table

**Files:**
- Create: `supabase/migrations/20260420000010_create_sor_matches.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260420000010_create_sor_matches.sql
create table public.sor_matches (
  id uuid primary key default gen_random_uuid(),
  name_normalized text not null,
  state text not null,
  matches jsonb not null default '[]'::jsonb,
  disclaimer_text text not null,
  fetched_at timestamptz not null default now(),
  expires_at timestamptz not null default now() + interval '30 days',
  unique(name_normalized, state)
);

create index idx_sor_matches_expires on public.sor_matches(expires_at);
```

- [ ] **Step 2: Apply and commit**

```bash
npx -y supabase@latest db push
git add supabase/migrations/20260420000010_create_sor_matches.sql
git commit -m "feat(db): add sor_matches cache table (30-day TTL)"
```

---

## Task 6: M2 RLS policies

**Files:**
- Create: `supabase/migrations/20260420000011_m2_rls_policies.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260420000011_m2_rls_policies.sql

-- Enable RLS on all M2 tables
alter table public.reports enable row level security;
alter table public.report_sections enable row level security;
alter table public.llc_members enable row level security;
alter table public.sor_matches enable row level security;

-- Users can select their own reports
create policy "users_select_own_reports" on public.reports
  for select using (auth.uid() = user_id);

-- Users can select sections belonging to their own reports
create policy "users_select_own_report_sections" on public.report_sections
  for select using (
    exists (
      select 1 from public.reports r
      where r.id = report_id and r.user_id = auth.uid()
    )
  );

-- llc_members and sor_matches have NO anon/authenticated policies.
-- Only edge functions (service role) can read/write them.
-- This matches M1's pattern for addresses/owners/cached_lookups.
```

- [ ] **Step 2: Apply and commit**

```bash
npx -y supabase@latest db push
git add supabase/migrations/20260420000011_m2_rls_policies.sql
git commit -m "feat(db): enable RLS + user-scoped read policies for M2 tables"
```

---

## Task 7: Shared types (Finding, LlcInfo, SorMatch, Report)

**Files:**
- Create: `src/types/finding.ts`
- Create: `src/types/llc-info.ts`
- Create: `src/types/sor-match.ts`
- Create: `src/types/report.ts`

- [ ] **Step 1: Write `src/types/finding.ts`**

```typescript
// src/types/finding.ts
export type Severity = 'warn' | 'info' | 'ok';
export type FindingSection = 'owner' | 'llc' | 'sor' | 'property';

export interface Finding {
  severity: Severity;
  title: string;
  body: string;
  section: FindingSection;
}
```

- [ ] **Step 2: Write `src/types/llc-info.ts`**

```typescript
// src/types/llc-info.ts
export interface LlcMember {
  name: string;
  role: string;  // "Registered Agent", "Manager", "Member", etc.
  sourceUrl: string;
}

export interface LlcInfo {
  llcName: string;
  state: string;
  registeredAgent: string | null;
  members: LlcMember[];
  filingDate: string | null;  // ISO date
  sourceUrl: string;
  isRegisteredAgentService: boolean;  // heuristic flag
}
```

- [ ] **Step 3: Write `src/types/sor-match.ts`**

```typescript
// src/types/sor-match.ts
export interface SorMatch {
  name: string;
  dobYear: number | null;
  offense: string;
  jurisdiction: string;
  photoUrl: string | null;
  nsopwUrl: string;
}

export interface SorResult {
  matches: SorMatch[];
  disclaimerText: string;
  nameSearched: string;
  stateSearched: string;
}
```

- [ ] **Step 4: Write `src/types/report.ts`**

```typescript
// src/types/report.ts
import type { OwnerCard } from './owner-card';
import type { LlcInfo } from './llc-info';
import type { SorResult } from './sor-match';
import type { Finding } from './finding';

export type SectionStatus = 'pending' | 'complete' | 'failed';

export interface SectionError {
  code: string;
  message: string;
}

export interface ReportSection<T> {
  status: SectionStatus;
  data: T | null;
  sourceAttribution: string | null;
  error: SectionError | null;
}

export interface PropertySnapshotExpanded {
  ownerMailingAddress: string | null;
  ownerMailingState: string | null;
  unitCount: number | null;
  salesInLast5Years: number | null;
  zoningClass: string | null;
  lastAssessmentDate: string | null;
}

export interface Report {
  id: string;
  userId: string;
  addressId: string;
  tier: 'free' | 'deep';
  status: SectionStatus | 'partial';
  createdAt: string;
  expiresAt: string;
  sections: {
    ownerCard: ReportSection<OwnerCard>;
    llcMembers: ReportSection<LlcInfo>;
    sor: ReportSection<SorResult>;
    propertySnapshot: ReportSection<PropertySnapshotExpanded>;
    redFlags: ReportSection<Finding[]>;
  };
}
```

- [ ] **Step 5: Commit**

```bash
git add src/types/finding.ts src/types/llc-info.ts src/types/sor-match.ts src/types/report.ts
git commit -m "feat(types): add M2 shared types (Finding, LlcInfo, SorMatch, Report)"
```

---

## Task 8: Red-flag engine (pure module, TDD)

**Files:**
- Create: `src/__tests__/red-flag-engine.test.ts`
- Create: `src/lib/red-flag-engine.ts`
- Create: `supabase/functions/_shared/red-flag-engine.ts` (byte-identical copy)

- [ ] **Step 1: Write the failing tests**

`src/__tests__/red-flag-engine.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { runRedFlagEngine } from '../lib/red-flag-engine';
import type { Report } from '../types/report';

// Builds a baseline Report with all sections complete and nothing flagged.
function baselineReport(): Report {
  return {
    id: 'r1', userId: 'u1', addressId: 'a1',
    tier: 'free', status: 'complete',
    createdAt: '2026-04-20T12:00:00Z', expiresAt: '2026-05-20T12:00:00Z',
    sections: {
      ownerCard: {
        status: 'complete', sourceAttribution: 'ATTOM', error: null,
        data: {
          address: { normalized: '1 main st, madison, wi 53703', display: '1 Main St, Madison, WI 53703', city: 'Madison', state: 'WI', zip: '53703' },
          owner: { name: 'JOHN DOE', type: 'person' },
          parcel: { lastSaleDate: '2020-01-01', lastSalePriceCents: 25000000, taxStatus: 'current', propertyType: 'SFR', yearBuilt: 2000, sqft: 1500, assessedValueCents: 20000000 },
          source: 'attom', fetchedAt: '2026-04-20T12:00:00Z',
        },
      },
      llcMembers: { status: 'complete', sourceAttribution: null, error: null, data: null },
      sor: { status: 'complete', sourceAttribution: 'NSOPW', error: null,
        data: { matches: [], disclaimerText: 'NSOPW disclaimer', nameSearched: 'JOHN DOE', stateSearched: 'WI' },
      },
      propertySnapshot: { status: 'complete', sourceAttribution: 'ATTOM', error: null,
        data: { ownerMailingAddress: '1 Main St, Madison, WI', ownerMailingState: 'WI', unitCount: 1, salesInLast5Years: 1, zoningClass: 'R1', lastAssessmentDate: '2025-01-01' },
      },
      redFlags: { status: 'pending', sourceAttribution: null, error: null, data: null },
    },
  };
}

describe('runRedFlagEngine', () => {
  it('emits OK finding when no warns or infos trigger', () => {
    const findings = runRedFlagEngine(baselineReport());
    expect(findings.length).toBe(1);
    expect(findings[0].severity).toBe('ok');
    expect(findings[0].title).toMatch(/no red flags/i);
  });

  it('emits WARN for SOR matches', () => {
    const r = baselineReport();
    r.sections.sor.data!.matches = [
      { name: 'JOHN DOE', dobYear: 1970, offense: 'X', jurisdiction: 'Dane County, WI', photoUrl: null, nsopwUrl: 'https://nsopw.gov/x' },
    ];
    const findings = runRedFlagEngine(r);
    const sorFinding = findings.find(f => f.section === 'sor');
    expect(sorFinding?.severity).toBe('warn');
    expect(sorFinding?.title).toMatch(/sex offender registry match/i);
    expect(sorFinding?.body).toMatch(/JOHN DOE/);
    expect(sorFinding?.body).toMatch(/WI/);
  });

  it('emits WARN for tax delinquent', () => {
    const r = baselineReport();
    r.sections.ownerCard.data!.parcel.taxStatus = 'delinquent';
    const findings = runRedFlagEngine(r);
    const findingTitles = findings.map(f => f.title);
    expect(findingTitles.some(t => /taxes are delinquent/i.test(t))).toBe(true);
  });

  it('emits WARN when LLC piercing failed in launch state', () => {
    const r = baselineReport();
    r.sections.ownerCard.data!.owner = { name: 'ACME PROPERTIES LLC', type: 'llc' };
    r.sections.llcMembers = { status: 'failed', sourceAttribution: null, error: { code: 'scraper_failed', message: 'WI portal returned 500' }, data: null };
    const findings = runRedFlagEngine(r);
    const llcFinding = findings.find(f => f.section === 'llc');
    expect(llcFinding?.severity).toBe('warn');
    expect(llcFinding?.title).toMatch(/unclear/i);
  });

  it('emits INFO when LLC piercing succeeded', () => {
    const r = baselineReport();
    r.sections.ownerCard.data!.owner = { name: 'ACME PROPERTIES LLC', type: 'llc' };
    r.sections.llcMembers = {
      status: 'complete', sourceAttribution: 'WI DFI', error: null,
      data: { llcName: 'ACME PROPERTIES LLC', state: 'WI', registeredAgent: 'JANE SMITH', members: [{ name: 'JANE SMITH', role: 'Manager', sourceUrl: 'https://wdfi.org/x' }], filingDate: '2020-01-01', sourceUrl: 'https://wdfi.org/x', isRegisteredAgentService: false },
    };
    const findings = runRedFlagEngine(r);
    const llcFinding = findings.find(f => f.section === 'llc');
    expect(llcFinding?.severity).toBe('info');
    expect(llcFinding?.body).toMatch(/JANE SMITH/);
  });

  it('emits INFO for LLC in non-launch state', () => {
    const r = baselineReport();
    r.sections.ownerCard.data!.owner = { name: 'XYZ HOLDINGS LLC', type: 'llc' };
    r.sections.ownerCard.data!.address.state = 'CA';
    r.sections.llcMembers = { status: 'complete', sourceAttribution: null, error: null, data: null };
    const findings = runRedFlagEngine(r);
    const llcFinding = findings.find(f => f.section === 'llc');
    expect(llcFinding?.severity).toBe('info');
    expect(llcFinding?.title).toMatch(/out of our coverage area/i);
  });

  it('emits INFO for frequent ownership changes', () => {
    const r = baselineReport();
    r.sections.propertySnapshot.data!.salesInLast5Years = 4;
    const findings = runRedFlagEngine(r);
    expect(findings.some(f => /frequent ownership/i.test(f.title))).toBe(true);
  });

  it('emits INFO for out-of-state owner', () => {
    const r = baselineReport();
    r.sections.propertySnapshot.data!.ownerMailingState = 'FL';
    const findings = runRedFlagEngine(r);
    expect(findings.some(f => /out-of-state owner/i.test(f.title))).toBe(true);
  });

  it('orders findings warn > info > ok', () => {
    const r = baselineReport();
    r.sections.ownerCard.data!.parcel.taxStatus = 'delinquent';  // warn
    r.sections.propertySnapshot.data!.salesInLast5Years = 4;  // info
    const findings = runRedFlagEngine(r);
    const severities = findings.map(f => f.severity);
    const firstInfoIdx = severities.indexOf('info');
    const lastWarnIdx = severities.lastIndexOf('warn');
    expect(lastWarnIdx).toBeLessThan(firstInfoIdx);
    expect(severities.includes('ok')).toBe(false);  // ok suppressed when other findings exist
  });

  it('handles missing propertySnapshot data gracefully', () => {
    const r = baselineReport();
    r.sections.propertySnapshot = { status: 'failed', sourceAttribution: null, error: { code: 'upstream_error', message: 'x' }, data: null };
    const findings = runRedFlagEngine(r);
    // Should still run other rules; not crash
    expect(Array.isArray(findings)).toBe(true);
  });
});
```

- [ ] **Step 2: Run tests — verify they all fail**

```bash
cd /Users/daniel/twins-dashboard/landlordlens
npx vitest run src/__tests__/red-flag-engine.test.ts
```

Expected: FAIL with module-not-found.

- [ ] **Step 3: Implement `src/lib/red-flag-engine.ts`**

```typescript
// src/lib/red-flag-engine.ts
import type { Report } from '../types/report';
import type { Finding } from '../types/finding';

const LAUNCH_STATES = new Set(['WI', 'IL', 'MN']);

export function runRedFlagEngine(report: Report): Finding[] {
  const findings: Finding[] = [];
  const owner = report.sections.ownerCard.data?.owner;
  const propertyState = report.sections.ownerCard.data?.address.state ?? null;
  const parcel = report.sections.ownerCard.data?.parcel;
  const llcSection = report.sections.llcMembers;
  const sorSection = report.sections.sor;
  const snapshot = report.sections.propertySnapshot.data;

  // --- WARN rules ---

  // SOR match
  if (sorSection.status === 'complete' && sorSection.data && sorSection.data.matches.length > 0) {
    const count = sorSection.data.matches.length;
    findings.push({
      severity: 'warn',
      section: 'sor',
      title: 'Sex offender registry match',
      body: `${count} match${count === 1 ? '' : 'es'} found in ${sorSection.data.stateSearched} for individuals named ${sorSection.data.nameSearched}. Verify identity via photos before proceeding.`,
    });
  }

  // Tax delinquent
  if (parcel?.taxStatus === 'delinquent') {
    findings.push({
      severity: 'warn',
      section: 'property',
      title: 'Property taxes are delinquent',
      body: `County records show ${owner?.name ?? 'the owner'} is behind on property taxes. Rent you pay could be at risk if the property is seized.`,
    });
  }

  // LLC piercing failed in launch state
  if (
    owner?.type === 'llc' &&
    propertyState && LAUNCH_STATES.has(propertyState) &&
    llcSection.status === 'failed'
  ) {
    findings.push({
      severity: 'warn',
      section: 'llc',
      title: 'Who controls this LLC is unclear',
      body: `${owner.name} is registered with the state but member information isn't exposed publicly right now. Ask the landlord directly who manages the LLC.`,
    });
  }

  // --- INFO rules ---

  // LLC piercing succeeded
  if (
    owner?.type === 'llc' &&
    llcSection.status === 'complete' &&
    llcSection.data &&
    llcSection.data.members.length > 0
  ) {
    const memberNames = llcSection.data.members.map(m => m.name).join(', ');
    findings.push({
      severity: 'info',
      section: 'llc',
      title: 'Property held by LLC',
      body: `${owner.name} — member(s): ${memberNames}. Further checks are run against these individuals.`,
    });
  }

  // LLC in non-launch state
  if (
    owner?.type === 'llc' &&
    propertyState &&
    !LAUNCH_STATES.has(propertyState) &&
    llcSection.status !== 'failed'
  ) {
    findings.push({
      severity: 'info',
      section: 'llc',
      title: 'Property owned by LLC (out of our coverage area)',
      body: `${owner.name} is an LLC. Member lookup isn't supported in ${propertyState} yet. Search the state's SoS portal manually.`,
    });
  }

  // Frequent ownership changes
  if (snapshot?.salesInLast5Years !== null && snapshot?.salesInLast5Years !== undefined && snapshot.salesInLast5Years >= 3) {
    findings.push({
      severity: 'info',
      section: 'property',
      title: 'Frequent ownership changes',
      body: `This property has changed hands ${snapshot.salesInLast5Years} times in the last 5 years.`,
    });
  }

  // Out-of-state owner
  if (
    snapshot?.ownerMailingState &&
    propertyState &&
    snapshot.ownerMailingState !== propertyState
  ) {
    findings.push({
      severity: 'info',
      section: 'property',
      title: 'Out-of-state owner',
      body: `Owner's mailing address is in ${snapshot.ownerMailingState}; the property is in ${propertyState}. Expect remote property management.`,
    });
  }

  // --- OK fallback ---
  if (findings.length === 0) {
    findings.push({
      severity: 'ok',
      section: 'property',
      title: 'No red flags found in public records',
      body: "We couldn't find anything concerning about this rental in public records. This doesn't guarantee the rental is safe — always verify independently.",
    });
  }

  // Sort: warn > info > ok
  const severityRank: Record<Finding['severity'], number> = { warn: 0, info: 1, ok: 2 };
  findings.sort((a, b) => severityRank[a.severity] - severityRank[b.severity]);

  return findings;
}
```

- [ ] **Step 4: Run tests — verify all pass**

```bash
npx vitest run src/__tests__/red-flag-engine.test.ts
```

Expected: all 10 tests pass.

- [ ] **Step 5: Mirror to edge function shared dir**

```bash
cp src/lib/red-flag-engine.ts supabase/functions/_shared/red-flag-engine.ts
```

- [ ] **Step 6: Commit**

```bash
git add src/lib/red-flag-engine.ts src/__tests__/red-flag-engine.test.ts supabase/functions/_shared/red-flag-engine.ts
git commit -m "feat(core): add rules-based red-flag engine with 8 rules + test coverage"
```

---

## Task 9: SoS piercer — shared types + fixture capture

**Files:**
- Create: `supabase/functions/sos-piercer/types.ts`
- Create: `supabase/functions/sos-piercer/fixtures/` (directory)
- Create: `supabase/functions/sos-piercer/fixtures/wi-sample.html` (raw HTML)
- Create: `supabase/functions/sos-piercer/fixtures/il-sample.html` (raw HTML)
- Create: `supabase/functions/sos-piercer/fixtures/mn-sample.html` (raw HTML)

- [ ] **Step 1: Write `types.ts`**

```typescript
// supabase/functions/sos-piercer/types.ts
// Mirrors src/types/llc-info.ts for use inside edge functions.
export interface LlcMember {
  name: string;
  role: string;
  sourceUrl: string;
}

export interface LlcInfo {
  llcName: string;
  state: string;
  registeredAgent: string | null;
  members: LlcMember[];
  filingDate: string | null;
  sourceUrl: string;
  isRegisteredAgentService: boolean;
}

// Heuristic for well-known registered-agent services
const KNOWN_AGENT_SERVICES = [
  'CT CORPORATION', 'CORPORATION SERVICE COMPANY', 'NATIONAL REGISTERED AGENTS',
  'LEGALZOOM', 'HARBOR COMPLIANCE', 'NORTHWEST REGISTERED AGENT',
  'REGISTERED AGENT INC', 'INCORP SERVICES',
];

export function detectRegisteredAgentService(name: string | null): boolean {
  if (!name) return false;
  const upper = name.toUpperCase();
  return KNOWN_AGENT_SERVICES.some(s => upper.includes(s));
}
```

- [ ] **Step 2: Capture real HTML fixtures**

The implementer must do this manually because every SoS portal has different forms and CAPTCHAs. For each state, pick one real known LLC (e.g., `ACME LLC`-type test names won't work — use a real example you know is registered), submit the search, save the result page HTML to the fixture file.

For **WI**: Go to https://www.wdfi.org/apps/corpsearch/, search for "AMERICAN FAMILY MUTUAL INSURANCE COMPANY" (Madison-based, definitely in the DB), click the detail link, save the detail-page HTML to `fixtures/wi-sample.html`.

For **IL**: Go to https://apps.ilsos.gov/businessentitysearch/, search "WALGREEN CO", pick the matching LLC/corp result detail page, save to `fixtures/il-sample.html`.

For **MN**: Go to https://mblsportal.sos.mn.gov/, search "TARGET CORPORATION", save detail-page HTML to `fixtures/mn-sample.html`.

Fallback strategy if any portal requires a CAPTCHA on detail pages: save the intermediate search-results page HTML (which lists matches with basic info) and adjust the scraper in Task 10/11/12 to parse that instead. Document the fallback in the fixture file's first line as an HTML comment.

- [ ] **Step 3: Commit**

```bash
git add supabase/functions/sos-piercer/types.ts supabase/functions/sos-piercer/fixtures/
git commit -m "feat(sos): add LlcInfo types + real HTML fixtures from WI/IL/MN SoS portals"
```

---

## Task 10: WI SoS scraper (TDD against fixture)

**Files:**
- Create: `supabase/functions/sos-piercer/wi-sos.ts`
- Create: `supabase/functions/sos-piercer/wi-sos.test.ts`

- [ ] **Step 1: Write the failing test**

`supabase/functions/sos-piercer/wi-sos.test.ts`:

```typescript
import { assertEquals, assert } from 'https://deno.land/std@0.208.0/assert/mod.ts';
import { parseWiDetail } from './wi-sos.ts';

Deno.test('wi-sos: extracts LLC info from detail-page fixture', async () => {
  const html = await Deno.readTextFile(
    new URL('./fixtures/wi-sample.html', import.meta.url)
  );
  const result = parseWiDetail(html, 'AMERICAN FAMILY MUTUAL INSURANCE COMPANY', 'https://www.wdfi.org/apps/corpsearch/example');

  assertEquals(result.state, 'WI');
  assertEquals(result.llcName.toUpperCase().includes('AMERICAN FAMILY'), true);
  assert(result.registeredAgent !== null, 'registered agent should be extracted');
  assert(result.members.length >= 0, 'members array present (may be empty if portal hides members)');
});

Deno.test('wi-sos: flags known registered-agent services', async () => {
  // Synthetic HTML with a known agent service name
  const html = `
    <html><body>
      <div>Registered Agent: CT CORPORATION SYSTEM</div>
      <div>Filing Date: 01/15/2020</div>
    </body></html>
  `;
  const result = parseWiDetail(html, 'TEST LLC', 'https://example');
  assertEquals(result.isRegisteredAgentService, true);
});
```

- [ ] **Step 2: Run test to confirm FAIL**

```bash
/Users/daniel/.deno/bin/deno test --allow-read supabase/functions/sos-piercer/wi-sos.test.ts
```

Expected: FAIL (module not found).

- [ ] **Step 3: Implement the scraper**

This is exploratory — the implementer must inspect the fixture HTML to determine correct selectors. The structure below is a starting template. Adjust `querySelector` arguments and regex patterns based on actual HTML structure.

`supabase/functions/sos-piercer/wi-sos.ts`:

```typescript
import { DOMParser } from 'https://deno.land/x/deno_dom@v0.1.45/deno-dom-wasm.ts';
import { type LlcInfo, detectRegisteredAgentService } from './types.ts';

/**
 * Parse a WI DFI Corporate Search detail-page HTML string into LlcInfo.
 *
 * HTML structure notes (from fixtures/wi-sample.html):
 * - Registered agent appears in a "dt/dd" pair under an agent section
 * - Filing date in an ISO or MM/DD/YYYY format
 * - Member names, if shown, under a "Principals" or "Officers" table
 *
 * When the portal only exposes the search-results list (not detail),
 * this function is still called but returns mostly-null fields.
 */
export function parseWiDetail(html: string, llcName: string, sourceUrl: string): LlcInfo {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  if (!doc) {
    return emptyLlcInfo(llcName, sourceUrl);
  }

  // --- Registered agent ---
  // Adjust selector after inspecting actual HTML
  let registeredAgent: string | null = null;
  const agentRegex = /registered agent[:\s]*([^\n<]+)/i;
  const agentMatch = html.match(agentRegex);
  if (agentMatch) {
    registeredAgent = agentMatch[1].trim();
  }

  // --- Filing date ---
  let filingDate: string | null = null;
  const dateRegex = /(?:filing|incorporation) date[:\s]*(\d{1,2}\/\d{1,2}\/\d{4}|\d{4}-\d{2}-\d{2})/i;
  const dateMatch = html.match(dateRegex);
  if (dateMatch) {
    filingDate = normalizeFilingDate(dateMatch[1]);
  }

  // --- Members / principals ---
  // WI often hides member lists behind CAPTCHA for non-insurance entities.
  // For insurance companies (which is what our fixture uses), officers are public.
  // Extract from Officers/Principals table if present.
  const members = extractMembers(doc, sourceUrl);

  return {
    llcName,
    state: 'WI',
    registeredAgent,
    members,
    filingDate,
    sourceUrl,
    isRegisteredAgentService: detectRegisteredAgentService(registeredAgent),
  };
}

function extractMembers(doc: ReturnType<DOMParser['parseFromString']>, sourceUrl: string) {
  const members: { name: string; role: string; sourceUrl: string }[] = [];
  if (!doc) return members;
  // Start with a broad selector; refine against fixture
  const officerRows = doc.querySelectorAll('table tr');
  officerRows.forEach(row => {
    const cells = (row as Element).querySelectorAll('td');
    if (cells.length >= 2) {
      const role = (cells[0] as Element).textContent?.trim() ?? '';
      const name = (cells[1] as Element).textContent?.trim() ?? '';
      if (name && role && /officer|director|agent|member|manager/i.test(role)) {
        members.push({ name, role, sourceUrl });
      }
    }
  });
  return members;
}

function normalizeFilingDate(raw: string): string {
  if (raw.includes('/')) {
    const [mm, dd, yyyy] = raw.split('/');
    return `${yyyy}-${mm.padStart(2, '0')}-${dd.padStart(2, '0')}`;
  }
  return raw;
}

function emptyLlcInfo(llcName: string, sourceUrl: string): LlcInfo {
  return { llcName, state: 'WI', registeredAgent: null, members: [], filingDate: null, sourceUrl, isRegisteredAgentService: false };
}

/**
 * Live fetch + parse. Triggered by the HTTP handler.
 */
export async function searchWi(llcName: string): Promise<LlcInfo | null> {
  const searchUrl = `https://www.wdfi.org/apps/corpsearch/Search.aspx?SearchFor=${encodeURIComponent(llcName)}`;
  const resp = await fetch(searchUrl, { headers: { 'user-agent': 'LandlordLensBot/1.0 (public records lookup)' } });
  if (!resp.ok) {
    throw new Error(`scraper_failed: WI SoS returned ${resp.status}`);
  }
  const html = await resp.text();

  // If the search page lists 0 results, return null
  if (/no results|did not match/i.test(html)) {
    return null;
  }

  return parseWiDetail(html, llcName, resp.url);
}
```

- [ ] **Step 4: Iterate until tests pass**

```bash
/Users/daniel/.deno/bin/deno test --allow-read supabase/functions/sos-piercer/wi-sos.test.ts
```

If selectors don't match fixture reality, adjust `parseWiDetail` body (not test) until the fixture-based test passes. The synthetic "CT Corporation" test should already pass because it uses simple regex.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/sos-piercer/wi-sos.ts supabase/functions/sos-piercer/wi-sos.test.ts
git commit -m "feat(sos): add WI SoS scraper with fixture-based tests"
```

---

## Task 11: IL SoS scraper

**Files:**
- Create: `supabase/functions/sos-piercer/il-sos.ts`
- Create: `supabase/functions/sos-piercer/il-sos.test.ts`

Follow the same pattern as Task 10. Starting skeleton:

- [ ] **Step 1: Write test**

`il-sos.test.ts`:

```typescript
import { assertEquals, assert } from 'https://deno.land/std@0.208.0/assert/mod.ts';
import { parseIlDetail } from './il-sos.ts';

Deno.test('il-sos: extracts LLC info from IL SoS fixture', async () => {
  const html = await Deno.readTextFile(
    new URL('./fixtures/il-sample.html', import.meta.url)
  );
  const result = parseIlDetail(html, 'WALGREEN CO', 'https://apps.ilsos.gov/example');
  assertEquals(result.state, 'IL');
  assertEquals(result.llcName.toUpperCase().includes('WALGREEN'), true);
  assert(result.sourceUrl.startsWith('https://'));
});
```

- [ ] **Step 2: Implement `il-sos.ts`**

Same shape as `wi-sos.ts`. Adjust selectors for IL's portal HTML (which uses `<div class="line-item">` patterns based on typical ASP.NET state portals — verify against the fixture).

```typescript
import { DOMParser } from 'https://deno.land/x/deno_dom@v0.1.45/deno-dom-wasm.ts';
import { type LlcInfo, detectRegisteredAgentService } from './types.ts';

export function parseIlDetail(html: string, llcName: string, sourceUrl: string): LlcInfo {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  if (!doc) return empty(llcName, sourceUrl);

  const agentMatch = html.match(/agent\s*name[:\s]*([^\n<]+)/i);
  const registeredAgent = agentMatch ? agentMatch[1].trim() : null;

  const dateMatch = html.match(/(?:organization|incorporation)\s*date[:\s]*(\d{1,2}\/\d{1,2}\/\d{4})/i);
  const filingDate = dateMatch ? normalizeDate(dateMatch[1]) : null;

  return {
    llcName, state: 'IL', registeredAgent, members: [], filingDate, sourceUrl,
    isRegisteredAgentService: detectRegisteredAgentService(registeredAgent),
  };
}

function normalizeDate(raw: string): string {
  const [mm, dd, yyyy] = raw.split('/');
  return `${yyyy}-${mm.padStart(2, '0')}-${dd.padStart(2, '0')}`;
}

function empty(llcName: string, sourceUrl: string): LlcInfo {
  return { llcName, state: 'IL', registeredAgent: null, members: [], filingDate: null, sourceUrl, isRegisteredAgentService: false };
}

export async function searchIl(llcName: string): Promise<LlcInfo | null> {
  const url = `https://apps.ilsos.gov/businessentitysearch/Search.aspx?SearchFor=${encodeURIComponent(llcName)}`;
  const resp = await fetch(url, { headers: { 'user-agent': 'LandlordLensBot/1.0 (public records lookup)' } });
  if (!resp.ok) throw new Error(`scraper_failed: IL SoS returned ${resp.status}`);
  const html = await resp.text();
  if (/no results|no matches/i.test(html)) return null;
  return parseIlDetail(html, llcName, resp.url);
}
```

- [ ] **Step 3: Iterate tests to GREEN, then commit**

```bash
/Users/daniel/.deno/bin/deno test --allow-read supabase/functions/sos-piercer/il-sos.test.ts
git add supabase/functions/sos-piercer/il-sos.ts supabase/functions/sos-piercer/il-sos.test.ts
git commit -m "feat(sos): add IL SoS scraper with fixture-based tests"
```

---

## Task 12: MN SoS scraper

**Files:**
- Create: `supabase/functions/sos-piercer/mn-sos.ts`
- Create: `supabase/functions/sos-piercer/mn-sos.test.ts`

Same pattern. Full test + implementation:

- [ ] **Step 1: Write test**

```typescript
// mn-sos.test.ts
import { assertEquals, assert } from 'https://deno.land/std@0.208.0/assert/mod.ts';
import { parseMnDetail } from './mn-sos.ts';

Deno.test('mn-sos: extracts LLC info from MN SoS fixture', async () => {
  const html = await Deno.readTextFile(
    new URL('./fixtures/mn-sample.html', import.meta.url)
  );
  const result = parseMnDetail(html, 'TARGET CORPORATION', 'https://mblsportal.sos.mn.gov/example');
  assertEquals(result.state, 'MN');
  assertEquals(result.llcName.toUpperCase().includes('TARGET'), true);
});
```

- [ ] **Step 2: Implement**

```typescript
// mn-sos.ts
import { DOMParser } from 'https://deno.land/x/deno_dom@v0.1.45/deno-dom-wasm.ts';
import { type LlcInfo, detectRegisteredAgentService } from './types.ts';

export function parseMnDetail(html: string, llcName: string, sourceUrl: string): LlcInfo {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  if (!doc) return empty(llcName, sourceUrl);

  const agentMatch = html.match(/(?:registered|resident)\s*agent[:\s]*([^\n<]+)/i);
  const registeredAgent = agentMatch ? agentMatch[1].trim() : null;

  const dateMatch = html.match(/(?:file|filed|formation)\s*date[:\s]*(\d{1,2}\/\d{1,2}\/\d{4})/i);
  const filingDate = dateMatch ? normalizeDate(dateMatch[1]) : null;

  return {
    llcName, state: 'MN', registeredAgent, members: [], filingDate, sourceUrl,
    isRegisteredAgentService: detectRegisteredAgentService(registeredAgent),
  };
}

function normalizeDate(raw: string): string {
  const [mm, dd, yyyy] = raw.split('/');
  return `${yyyy}-${mm.padStart(2, '0')}-${dd.padStart(2, '0')}`;
}

function empty(llcName: string, sourceUrl: string): LlcInfo {
  return { llcName, state: 'MN', registeredAgent: null, members: [], filingDate: null, sourceUrl, isRegisteredAgentService: false };
}

export async function searchMn(llcName: string): Promise<LlcInfo | null> {
  const url = `https://mblsportal.sos.mn.gov/BusinessSearch/BusinessSearch?searchText=${encodeURIComponent(llcName)}`;
  const resp = await fetch(url, { headers: { 'user-agent': 'LandlordLensBot/1.0 (public records lookup)' } });
  if (!resp.ok) throw new Error(`scraper_failed: MN SoS returned ${resp.status}`);
  const html = await resp.text();
  if (/no results|no match/i.test(html)) return null;
  return parseMnDetail(html, llcName, resp.url);
}
```

- [ ] **Step 3: Tests GREEN, commit**

```bash
/Users/daniel/.deno/bin/deno test --allow-read supabase/functions/sos-piercer/mn-sos.test.ts
git add supabase/functions/sos-piercer/mn-sos.ts supabase/functions/sos-piercer/mn-sos.test.ts
git commit -m "feat(sos): add MN SoS scraper with fixture-based tests"
```

---

## Task 13: SoS piercer HTTP handler

**Files:**
- Create: `supabase/functions/sos-piercer/index.ts`
- Create: `supabase/functions/sos-piercer/index.test.ts`

- [ ] **Step 1: Write test (dispatcher routing)**

```typescript
// index.test.ts
import { assertEquals } from 'https://deno.land/std@0.208.0/assert/mod.ts';
import { routeByState } from './index.ts';

Deno.test('sos-piercer: routes WI to searchWi', () => {
  assertEquals(routeByState('WI').state, 'WI');
});

Deno.test('sos-piercer: routes IL to searchIl', () => {
  assertEquals(routeByState('IL').state, 'IL');
});

Deno.test('sos-piercer: routes MN to searchMn', () => {
  assertEquals(routeByState('MN').state, 'MN');
});

Deno.test('sos-piercer: returns null for unsupported state', () => {
  assertEquals(routeByState('CA'), null);
});
```

- [ ] **Step 2: Implement handler**

```typescript
// supabase/functions/sos-piercer/index.ts
import { serve } from 'https://deno.land/std@0.208.0/http/server.ts';
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2.45.0';
import { corsHeaders } from '../_shared/cors.ts';
import { type LlcInfo } from './types.ts';
import { searchWi } from './wi-sos.ts';
import { searchIl } from './il-sos.ts';
import { searchMn } from './mn-sos.ts';

interface StateSearcher {
  state: string;
  search: (llcName: string) => Promise<LlcInfo | null>;
}

export function routeByState(state: string): StateSearcher | null {
  switch (state.toUpperCase()) {
    case 'WI': return { state: 'WI', search: searchWi };
    case 'IL': return { state: 'IL', search: searchIl };
    case 'MN': return { state: 'MN', search: searchMn };
    default: return null;
  }
}

function normalizeLlc(name: string): string {
  return name.toUpperCase().replace(/\s+/g, ' ').trim();
}

serve(async (req) => {
  if (req.method === 'OPTIONS') return new Response('ok', { headers: corsHeaders });
  if (req.method !== 'POST') return json({ ok: false, error: { code: 'invalid_input', message: 'POST required' } }, 405);

  let body: { state?: string; llcName?: string };
  try { body = await req.json(); } catch { return json({ ok: false, error: { code: 'invalid_input', message: 'bad JSON' } }, 400); }

  const state = (body.state ?? '').toString();
  const llcName = (body.llcName ?? '').toString();
  if (!state || !llcName) return json({ ok: false, error: { code: 'invalid_input', message: 'state and llcName required' } }, 400);

  const searcher = routeByState(state);
  if (!searcher) return json({ ok: false, error: { code: 'unsupported_state', message: `${state} not supported in M2` } }, 400);

  const nameNorm = normalizeLlc(llcName);
  const stateNorm = searcher.state;

  // Check cache
  const supabase = createClient(
    Deno.env.get('SUPABASE_URL') ?? '',
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? ''
  );
  const { data: cached } = await supabase
    .from('llc_members')
    .select('*')
    .eq('llc_name_normalized', nameNorm)
    .eq('state', stateNorm)
    .gt('expires_at', new Date().toISOString())
    .maybeSingle();

  if (cached) {
    return json({
      ok: true,
      data: {
        llcName,
        state: stateNorm,
        registeredAgent: cached.registered_agent,
        members: cached.members,
        filingDate: cached.filing_date,
        sourceUrl: cached.source_url,
        isRegisteredAgentService: false,  // stored already in members if needed
      },
    }, 200);
  }

  try {
    const result = await searcher.search(llcName);
    if (!result) {
      return json({ ok: false, error: { code: 'not_found', message: 'LLC not found' } }, 404);
    }
    await supabase.from('llc_members').upsert({
      llc_name_normalized: nameNorm,
      state: stateNorm,
      members: result.members,
      registered_agent: result.registeredAgent,
      filing_date: result.filingDate,
      source_url: result.sourceUrl,
      fetched_at: new Date().toISOString(),
      expires_at: new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toISOString(),
    }, { onConflict: 'llc_name_normalized,state' });
    return json({ ok: true, data: result }, 200);
  } catch (e) {
    return json({ ok: false, error: { code: 'scraper_failed', message: (e as Error).message } }, 502);
  }
});

function json(body: unknown, status: number) {
  return new Response(JSON.stringify(body), {
    status, headers: { ...corsHeaders, 'content-type': 'application/json' },
  });
}
```

- [ ] **Step 3: Tests and commit**

```bash
/Users/daniel/.deno/bin/deno test --allow-read supabase/functions/sos-piercer/index.test.ts
git add supabase/functions/sos-piercer/index.ts supabase/functions/sos-piercer/index.test.ts
git commit -m "feat(sos): add HTTP handler with caching + state dispatch"
```

- [ ] **Step 4: Deploy**

```bash
set -a; source ~/.config/landlordlens-credentials.env; set +a
SUPABASE_ACCESS_TOKEN=$SUPABASE_ACCESS_TOKEN npx -y supabase@latest functions deploy sos-piercer --project-ref viwrdwtxhpmcrjubomzp
```

- [ ] **Step 5: Live smoke test**

```bash
curl -sS -X POST "$SUPABASE_URL/functions/v1/sos-piercer" \
  -H "authorization: Bearer $SUPABASE_ANON_KEY" \
  -H "apikey: $SUPABASE_ANON_KEY" \
  -H "content-type: application/json" \
  -d '{"state":"WI","llcName":"AMERICAN FAMILY MUTUAL INSURANCE COMPANY"}'
```

Expect either `{"ok":true,"data":{...}}` or a known failure (scraper_failed). Document the outcome.

---

## Task 14: NSOPW scraper

**Files:**
- Create: `supabase/functions/nsopw-scraper/types.ts`
- Create: `supabase/functions/nsopw-scraper/fixtures/nsopw-sample.html`
- Create: `supabase/functions/nsopw-scraper/client.ts`
- Create: `supabase/functions/nsopw-scraper/client.test.ts`
- Create: `supabase/functions/nsopw-scraper/index.ts`

- [ ] **Step 1: Capture NSOPW fixture**

Go to https://www.nsopw.gov/en/Search/Results . Search a common name like "JOHN SMITH" scoped to WI (the form has a state dropdown). Save the resulting HTML (with matches) to `fixtures/nsopw-sample.html`.

- [ ] **Step 2: Write types**

```typescript
// supabase/functions/nsopw-scraper/types.ts
export interface SorMatch {
  name: string;
  dobYear: number | null;
  offense: string;
  jurisdiction: string;
  photoUrl: string | null;
  nsopwUrl: string;
}

export interface SorResult {
  matches: SorMatch[];
  disclaimerText: string;
  nameSearched: string;
  stateSearched: string;
}
```

- [ ] **Step 3: Write failing test**

```typescript
// client.test.ts
import { assertEquals, assert } from 'https://deno.land/std@0.208.0/assert/mod.ts';
import { parseNsopwResults } from './client.ts';

Deno.test('nsopw: parses match list from fixture', async () => {
  const html = await Deno.readTextFile(new URL('./fixtures/nsopw-sample.html', import.meta.url));
  const result = parseNsopwResults(html, 'JOHN SMITH', 'WI');
  assertEquals(result.nameSearched, 'JOHN SMITH');
  assertEquals(result.stateSearched, 'WI');
  assert(result.disclaimerText.length > 0, 'disclaimer extracted');
  assert(Array.isArray(result.matches), 'matches array present');
});

Deno.test('nsopw: returns empty matches array on no-results fixture', () => {
  const html = '<html><body><div class="no-results">No matches found.</div><div class="disclaimer">X</div></body></html>';
  const result = parseNsopwResults(html, 'NONEXISTENT NAME', 'XX');
  assertEquals(result.matches.length, 0);
});
```

- [ ] **Step 4: Implement `client.ts`**

```typescript
// supabase/functions/nsopw-scraper/client.ts
import { DOMParser } from 'https://deno.land/x/deno_dom@v0.1.45/deno-dom-wasm.ts';
import type { SorMatch, SorResult } from './types.ts';

export function parseNsopwResults(html: string, nameSearched: string, stateSearched: string): SorResult {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  if (!doc) return { matches: [], disclaimerText: '', nameSearched, stateSearched };

  // Disclaimer text — refine selector after inspecting fixture
  const disclaimerEl = doc.querySelector('.disclaimer, #disclaimer, [data-disclaimer]');
  const disclaimerText = disclaimerEl?.textContent?.trim() ?? '';

  // Match rows — refine after inspecting fixture
  const matchRows = doc.querySelectorAll('.result-item, .match-row, tr.offender-row');
  const matches: SorMatch[] = [];
  matchRows.forEach(row => {
    const el = row as Element;
    const name = el.querySelector('.name, .offender-name')?.textContent?.trim() ?? '';
    const dobText = el.querySelector('.dob, .birth-year')?.textContent?.trim() ?? '';
    const dobYear = dobText.match(/\d{4}/)?.[0] ? parseInt(dobText.match(/\d{4}/)![0], 10) : null;
    const offense = el.querySelector('.offense, .offense-desc')?.textContent?.trim() ?? '';
    const jurisdiction = el.querySelector('.jurisdiction, .location')?.textContent?.trim() ?? '';
    const photoUrl = el.querySelector('img')?.getAttribute('src') ?? null;
    const linkEl = el.querySelector('a');
    const nsopwUrl = linkEl?.getAttribute('href') ?? '';

    if (name) {
      matches.push({
        name, dobYear, offense, jurisdiction, photoUrl,
        nsopwUrl: nsopwUrl.startsWith('http') ? nsopwUrl : `https://www.nsopw.gov${nsopwUrl}`,
      });
    }
  });

  return { matches, disclaimerText, nameSearched, stateSearched };
}

export async function fetchNsopw(name: string, state: string): Promise<SorResult> {
  // NSOPW's search form uses POST with form-encoded body (auto-accepts disclaimer via cookie/hidden field).
  // Adjust form fields based on actual inspection of the site's form.
  const url = 'https://www.nsopw.gov/en/Search/Results';
  const body = new URLSearchParams({
    firstName: name.split(' ')[0] ?? '',
    lastName: name.split(' ').slice(1).join(' ') ?? '',
    state,
    acceptedDisclaimer: 'true',
  });

  const resp = await fetch(url, {
    method: 'POST',
    headers: {
      'content-type': 'application/x-www-form-urlencoded',
      'user-agent': 'LandlordLensBot/1.0 (public records lookup)',
    },
    body: body.toString(),
  });

  if (!resp.ok) {
    throw new Error(`scraper_failed: NSOPW returned ${resp.status}`);
  }

  const html = await resp.text();
  return parseNsopwResults(html, name, state);
}
```

- [ ] **Step 5: Implement HTTP handler**

```typescript
// supabase/functions/nsopw-scraper/index.ts
import { serve } from 'https://deno.land/std@0.208.0/http/server.ts';
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2.45.0';
import { corsHeaders } from '../_shared/cors.ts';
import { fetchNsopw } from './client.ts';

function normalizeName(name: string): string {
  return name.toUpperCase().replace(/[.,]/g, '').replace(/\s+/g, ' ').trim();
}

serve(async (req) => {
  if (req.method === 'OPTIONS') return new Response('ok', { headers: corsHeaders });
  if (req.method !== 'POST') return json({ ok: false, error: { code: 'invalid_input' } }, 405);

  let body: { name?: string; state?: string };
  try { body = await req.json(); } catch { return json({ ok: false, error: { code: 'invalid_input' } }, 400); }
  const name = normalizeName(body.name ?? '');
  const state = (body.state ?? '').toUpperCase();
  if (!name || !state) return json({ ok: false, error: { code: 'invalid_input', message: 'name and state required' } }, 400);

  const supabase = createClient(
    Deno.env.get('SUPABASE_URL') ?? '',
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? ''
  );
  const { data: cached } = await supabase
    .from('sor_matches')
    .select('*')
    .eq('name_normalized', name)
    .eq('state', state)
    .gt('expires_at', new Date().toISOString())
    .maybeSingle();

  if (cached) {
    return json({
      ok: true,
      data: {
        matches: cached.matches,
        disclaimerText: cached.disclaimer_text,
        nameSearched: name,
        stateSearched: state,
      },
    }, 200);
  }

  try {
    const result = await fetchNsopw(name, state);
    await supabase.from('sor_matches').upsert({
      name_normalized: name,
      state,
      matches: result.matches,
      disclaimer_text: result.disclaimerText,
      fetched_at: new Date().toISOString(),
      expires_at: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
    }, { onConflict: 'name_normalized,state' });
    return json({ ok: true, data: result }, 200);
  } catch (e) {
    return json({ ok: false, error: { code: 'scraper_failed', message: (e as Error).message } }, 502);
  }
});

function json(body: unknown, status: number) {
  return new Response(JSON.stringify(body), {
    status, headers: { ...corsHeaders, 'content-type': 'application/json' },
  });
}
```

- [ ] **Step 6: Tests GREEN, deploy, live smoke**

```bash
/Users/daniel/.deno/bin/deno test --allow-read supabase/functions/nsopw-scraper/client.test.ts
git add supabase/functions/nsopw-scraper/
git commit -m "feat(nsopw): add scraper with fixture-based tests + HTTP handler + caching"
set -a; source ~/.config/landlordlens-credentials.env; set +a
SUPABASE_ACCESS_TOKEN=$SUPABASE_ACCESS_TOKEN npx -y supabase@latest functions deploy nsopw-scraper --project-ref viwrdwtxhpmcrjubomzp
curl -sS -X POST "$SUPABASE_URL/functions/v1/nsopw-scraper" \
  -H "authorization: Bearer $SUPABASE_ANON_KEY" \
  -H "apikey: $SUPABASE_ANON_KEY" \
  -H "content-type: application/json" \
  -d '{"name":"JOHN SMITH","state":"WI"}'
```

Expect JSON response. Document the outcome.

---

## Task 15: Free-report orchestrator (pure logic, TDD)

**Files:**
- Create: `supabase/functions/free-report/types.ts`
- Create: `supabase/functions/free-report/orchestrator.ts`
- Create: `supabase/functions/free-report/orchestrator.test.ts`

- [ ] **Step 1: Write types**

```typescript
// types.ts — mirrors src/types/report.ts, simplified for edge-fn use
export type SectionStatus = 'pending' | 'complete' | 'failed';
export interface SectionError { code: string; message: string; }
export interface ReportSection<T> {
  status: SectionStatus;
  data: T | null;
  sourceAttribution: string | null;
  error: SectionError | null;
}
// other types imported from sibling functions' types.ts
```

- [ ] **Step 2: Write failing tests**

```typescript
// orchestrator.test.ts
import { assertEquals, assertExists } from 'https://deno.land/std@0.208.0/assert/mod.ts';
import { runOrchestrator } from './orchestrator.ts';

const LAUNCH_STATES = new Set(['WI', 'IL', 'MN']);

const sampleOwner = {
  address: { normalized: '1 main st, madison, wi 53703', display: '1 Main St, Madison, WI 53703', city: 'Madison', state: 'WI', zip: '53703' },
  owner: { name: 'JOHN DOE', type: 'person' as const },
  parcel: { lastSaleDate: null, lastSalePriceCents: null, taxStatus: 'current' as const, propertyType: 'SFR', yearBuilt: 2000, sqft: 1500, assessedValueCents: 20000000 },
  source: 'attom' as const,
  fetchedAt: '2026-04-20T12:00:00Z',
};

Deno.test('orchestrator: runs all sections for person-owned property in launch state', async () => {
  const result = await runOrchestrator({
    addressId: 'a1',
    services: {
      ownerLookup: async () => sampleOwner,
      sosPiercer: async () => null,  // not called for person-owned
      nsopwSearch: async () => ({ matches: [], disclaimerText: 'NSOPW disclaimer', nameSearched: 'JOHN DOE', stateSearched: 'WI' }),
    },
  });

  assertEquals(result.sections.ownerCard.status, 'complete');
  assertEquals(result.sections.llcMembers.status, 'complete');
  assertEquals(result.sections.llcMembers.data, null);  // not an LLC
  assertEquals(result.sections.sor.status, 'complete');
  assertEquals(result.sections.propertySnapshot.status, 'complete');
  assertEquals(result.sections.redFlags.status, 'complete');
  assertExists(result.sections.redFlags.data);
});

Deno.test('orchestrator: triggers SoS piercing for LLC in launch state', async () => {
  const llcOwner = { ...sampleOwner, owner: { name: 'ACME PROPERTIES LLC', type: 'llc' as const } };
  let piercerCalled = false;
  await runOrchestrator({
    addressId: 'a1',
    services: {
      ownerLookup: async () => llcOwner,
      sosPiercer: async () => { piercerCalled = true; return { llcName: 'ACME PROPERTIES LLC', state: 'WI', registeredAgent: 'JANE', members: [{ name: 'JANE', role: 'Manager', sourceUrl: 'x' }], filingDate: '2020-01-01', sourceUrl: 'x', isRegisteredAgentService: false }; },
      nsopwSearch: async () => ({ matches: [], disclaimerText: 'd', nameSearched: 'JANE', stateSearched: 'WI' }),
    },
  });
  assertEquals(piercerCalled, true);
});

Deno.test('orchestrator: skips SoS piercing for LLC outside launch states', async () => {
  const caOwner = { ...sampleOwner, owner: { name: 'XYZ LLC', type: 'llc' as const }, address: { ...sampleOwner.address, state: 'CA' } };
  let piercerCalled = false;
  const result = await runOrchestrator({
    addressId: 'a1',
    services: {
      ownerLookup: async () => caOwner,
      sosPiercer: async () => { piercerCalled = true; return null; },
      nsopwSearch: async () => ({ matches: [], disclaimerText: 'd', nameSearched: 'XYZ LLC', stateSearched: 'CA' }),
    },
  });
  assertEquals(piercerCalled, false);
  assertEquals(result.sections.llcMembers.status, 'complete');
  assertEquals(result.sections.llcMembers.data, null);
});

Deno.test('orchestrator: marks sor section failed if nsopw throws', async () => {
  const result = await runOrchestrator({
    addressId: 'a1',
    services: {
      ownerLookup: async () => sampleOwner,
      sosPiercer: async () => null,
      nsopwSearch: async () => { throw new Error('scraper_failed: NSOPW down'); },
    },
  });
  assertEquals(result.sections.sor.status, 'failed');
  assertEquals(result.sections.sor.error?.code, 'scraper_failed');
});
```

- [ ] **Step 3: Implement orchestrator**

```typescript
// orchestrator.ts
import { runRedFlagEngine } from '../_shared/red-flag-engine.ts';
import type { OwnerCard } from '../owner-lookup/types.ts';
import type { LlcInfo } from '../sos-piercer/types.ts';
import type { SorResult } from '../nsopw-scraper/types.ts';

const LAUNCH_STATES = new Set(['WI', 'IL', 'MN']);

export interface OrchestratorServices {
  ownerLookup: (addressId: string) => Promise<OwnerCard>;
  sosPiercer: (state: string, llcName: string) => Promise<LlcInfo | null>;
  nsopwSearch: (name: string, state: string) => Promise<SorResult>;
}

export async function runOrchestrator(opts: { addressId: string; services: OrchestratorServices }): Promise<any> {
  const { addressId, services } = opts;
  const sections: any = {
    ownerCard: { status: 'pending', data: null, sourceAttribution: null, error: null },
    llcMembers: { status: 'pending', data: null, sourceAttribution: null, error: null },
    sor: { status: 'pending', data: null, sourceAttribution: null, error: null },
    propertySnapshot: { status: 'pending', data: null, sourceAttribution: null, error: null },
    redFlags: { status: 'pending', data: null, sourceAttribution: null, error: null },
  };

  // 1. Owner lookup
  try {
    const owner = await services.ownerLookup(addressId);
    sections.ownerCard = { status: 'complete', data: owner, sourceAttribution: 'ATTOM Data Solutions', error: null };
  } catch (e) {
    sections.ownerCard = { status: 'failed', data: null, sourceAttribution: null, error: { code: 'upstream_error', message: (e as Error).message } };
    // Without owner card, we cannot continue meaningfully.
    sections.llcMembers.status = 'failed';
    sections.sor.status = 'failed';
    sections.propertySnapshot.status = 'failed';
    sections.redFlags = { status: 'complete', data: runRedFlagEngine({ sections } as any), sourceAttribution: null, error: null };
    return { sections };
  }

  const owner = sections.ownerCard.data;
  const propertyState = owner.address.state;
  const isLlc = owner.owner.type === 'llc';
  const isLaunchState = propertyState && LAUNCH_STATES.has(propertyState);

  // 2. LLC piercing — only for LLC owners in launch states
  if (isLlc && isLaunchState) {
    try {
      const llc = await services.sosPiercer(propertyState, owner.owner.name);
      if (llc) {
        sections.llcMembers = { status: 'complete', data: llc, sourceAttribution: `${propertyState} Secretary of State`, error: null };
      } else {
        sections.llcMembers = { status: 'complete', data: null, sourceAttribution: null, error: null };
      }
    } catch (e) {
      sections.llcMembers = { status: 'failed', data: null, sourceAttribution: null, error: { code: 'scraper_failed', message: (e as Error).message } };
    }
  } else {
    // Non-LLC or non-launch-state: mark complete with no data
    sections.llcMembers = { status: 'complete', data: null, sourceAttribution: null, error: null };
  }

  // 3. SOR search — always run, against candidate names
  const candidateName = isLlc
    ? (sections.llcMembers.data?.members[0]?.name ?? owner.owner.name)
    : owner.owner.name;
  try {
    const sor = await services.nsopwSearch(candidateName, propertyState ?? '');
    sections.sor = { status: 'complete', data: sor, sourceAttribution: 'NSOPW (DOJ)', error: null };
  } catch (e) {
    sections.sor = { status: 'failed', data: null, sourceAttribution: null, error: { code: 'scraper_failed', message: (e as Error).message } };
  }

  // 4. Property snapshot expansion — derived from ownerCard data (no new call)
  const parcel = owner.parcel;
  // Note: salesInLast5Years, ownerMailingAddress not currently in OwnerCard type.
  // These derive from the full ATTOM response. For now, mark complete with whatever
  // we have; richer derivation added if ATTOM response is exposed.
  sections.propertySnapshot = {
    status: 'complete',
    data: {
      ownerMailingAddress: null,
      ownerMailingState: null,
      unitCount: null,
      salesInLast5Years: null,
      zoningClass: parcel.propertyType,
      lastAssessmentDate: null,
    },
    sourceAttribution: 'ATTOM Data Solutions',
    error: null,
  };

  // 5. Red-flag engine — always runs, adapts to whatever sections succeeded
  const reportForEngine = { sections };
  sections.redFlags = { status: 'complete', data: runRedFlagEngine(reportForEngine as any), sourceAttribution: null, error: null };

  return { sections };
}
```

- [ ] **Step 4: Tests GREEN, commit**

```bash
/Users/daniel/.deno/bin/deno test --allow-read supabase/functions/free-report/orchestrator.test.ts
git add supabase/functions/free-report/types.ts supabase/functions/free-report/orchestrator.ts supabase/functions/free-report/orchestrator.test.ts
git commit -m "feat(free-report): add orchestrator with stubbable services + graceful degradation"
```

---

## Task 16: Free-report HTTP handler + DB persistence

**Files:**
- Create: `supabase/functions/free-report/db.ts`
- Create: `supabase/functions/free-report/index.ts`

- [ ] **Step 1: Write `db.ts`**

```typescript
// supabase/functions/free-report/db.ts
import { createClient, SupabaseClient } from 'https://esm.sh/@supabase/supabase-js@2.45.0';

export function createFreeReportDb(url: string, serviceRoleKey: string) {
  const supabase: SupabaseClient = createClient(url, serviceRoleKey);

  return {
    async createReport(userId: string, addressId: string): Promise<string> {
      const { data, error } = await supabase
        .from('reports')
        .insert({ user_id: userId, address_id: addressId, tier: 'free', status: 'pending' })
        .select('id')
        .single();
      if (error) throw new Error(`db_error: createReport ${error.message}`);
      return data.id;
    },

    async saveSection(reportId: string, sectionType: string, section: any) {
      const { error } = await supabase
        .from('report_sections')
        .upsert({
          report_id: reportId,
          section_type: sectionType,
          status: section.status,
          data: section.data,
          source_attribution: section.sourceAttribution,
          error_code: section.error?.code ?? null,
          error_message: section.error?.message ?? null,
        }, { onConflict: 'report_id,section_type' });
      if (error) throw new Error(`db_error: saveSection ${error.message}`);
    },

    async markReportComplete(reportId: string, anyFailed: boolean) {
      const status = anyFailed ? 'partial' : 'complete';
      const { error } = await supabase
        .from('reports')
        .update({ status })
        .eq('id', reportId);
      if (error) throw new Error(`db_error: markReportComplete ${error.message}`);
    },

    async getAddressByNormalized(normalized: string): Promise<string | null> {
      const { data } = await supabase
        .from('addresses')
        .select('id')
        .eq('normalized', normalized)
        .maybeSingle();
      return data?.id ?? null;
    },

    // Helper to verify the JWT the client sent belongs to a real user.
    async getUserFromToken(token: string): Promise<string | null> {
      const { data: userData } = await supabase.auth.getUser(token);
      return userData?.user?.id ?? null;
    },
  };
}
```

- [ ] **Step 2: Write HTTP handler**

```typescript
// supabase/functions/free-report/index.ts
import { serve } from 'https://deno.land/std@0.208.0/http/server.ts';
import { corsHeaders } from '../_shared/cors.ts';
import { normalizeAddress } from '../_shared/normalize.ts';
import { runOrchestrator } from './orchestrator.ts';
import { createFreeReportDb } from './db.ts';

const SUPABASE_URL = Deno.env.get('SUPABASE_URL') ?? '';
const SUPABASE_SERVICE_ROLE = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? '';

serve(async (req) => {
  if (req.method === 'OPTIONS') return new Response('ok', { headers: corsHeaders });
  if (req.method !== 'POST') return json({ ok: false, error: { code: 'invalid_input' } }, 405);

  // Verify auth
  const authHeader = req.headers.get('authorization') ?? '';
  const token = authHeader.replace(/^Bearer\s+/i, '');
  if (!token) return json({ ok: false, error: { code: 'unauthorized', message: 'Bearer token required' } }, 401);

  const db = createFreeReportDb(SUPABASE_URL, SUPABASE_SERVICE_ROLE);
  const userId = await db.getUserFromToken(token);
  if (!userId) return json({ ok: false, error: { code: 'unauthorized', message: 'invalid token' } }, 401);

  let body: { address?: string };
  try { body = await req.json(); } catch { return json({ ok: false, error: { code: 'invalid_input' } }, 400); }
  const rawAddress = (body.address ?? '').trim();
  if (rawAddress.length < 5) return json({ ok: false, error: { code: 'invalid_input' } }, 400);

  const normalized = normalizeAddress(rawAddress);
  const addressId = await db.getAddressByNormalized(normalized);
  if (!addressId) {
    return json({ ok: false, error: { code: 'not_found', message: 'Run Owner Card lookup first' } }, 404);
  }

  const reportId = await db.createReport(userId, addressId);

  // Real services wire through our existing edge functions
  const services = {
    ownerLookup: async (_addressId: string) => {
      const resp = await fetch(`${SUPABASE_URL}/functions/v1/owner-lookup`, {
        method: 'POST',
        headers: { authorization: `Bearer ${SUPABASE_SERVICE_ROLE}`, apikey: SUPABASE_SERVICE_ROLE, 'content-type': 'application/json' },
        body: JSON.stringify({ address: rawAddress }),
      });
      const data = await resp.json();
      if (!data.ok) throw new Error(data.error?.message ?? 'owner lookup failed');
      return data.data;
    },
    sosPiercer: async (state: string, llcName: string) => {
      const resp = await fetch(`${SUPABASE_URL}/functions/v1/sos-piercer`, {
        method: 'POST',
        headers: { authorization: `Bearer ${SUPABASE_SERVICE_ROLE}`, apikey: SUPABASE_SERVICE_ROLE, 'content-type': 'application/json' },
        body: JSON.stringify({ state, llcName }),
      });
      const data = await resp.json();
      if (!data.ok) return null;  // null = not found / failed, orchestrator handles
      return data.data;
    },
    nsopwSearch: async (name: string, state: string) => {
      const resp = await fetch(`${SUPABASE_URL}/functions/v1/nsopw-scraper`, {
        method: 'POST',
        headers: { authorization: `Bearer ${SUPABASE_SERVICE_ROLE}`, apikey: SUPABASE_SERVICE_ROLE, 'content-type': 'application/json' },
        body: JSON.stringify({ name, state }),
      });
      const data = await resp.json();
      if (!data.ok) throw new Error(data.error?.message ?? 'nsopw failed');
      return data.data;
    },
  };

  const assembled = await runOrchestrator({ addressId, services });

  // Persist each section
  for (const [sectionType, section] of Object.entries(assembled.sections)) {
    // Map frontend key to DB enum
    const dbType = sectionTypeToDbEnum(sectionType);
    await db.saveSection(reportId, dbType, section);
  }
  const anyFailed = Object.values(assembled.sections).some((s: any) => s.status === 'failed');
  await db.markReportComplete(reportId, anyFailed);

  return json({ ok: true, data: { reportId, ...assembled, status: anyFailed ? 'partial' : 'complete' } }, 200);
});

function sectionTypeToDbEnum(key: string): string {
  const map: Record<string, string> = {
    ownerCard: 'owner_card',
    llcMembers: 'llc_members',
    sor: 'sor',
    propertySnapshot: 'property_snapshot',
    redFlags: 'red_flags',
  };
  return map[key] ?? key;
}

function json(body: unknown, status: number) {
  return new Response(JSON.stringify(body), {
    status, headers: { ...corsHeaders, 'content-type': 'application/json' },
  });
}
```

- [ ] **Step 3: Deploy**

```bash
set -a; source ~/.config/landlordlens-credentials.env; set +a
SUPABASE_ACCESS_TOKEN=$SUPABASE_ACCESS_TOKEN npx -y supabase@latest functions deploy free-report --project-ref viwrdwtxhpmcrjubomzp
```

- [ ] **Step 4: Commit**

```bash
git add supabase/functions/free-report/
git commit -m "feat(free-report): add HTTP handler + DB persistence + service wiring"
```

---

## Task 17: React Router setup

**Files:**
- Modify: `src/App.tsx`
- Modify: `src/main.tsx` (if needed for router provider)

- [ ] **Step 1: Wire React Router into App**

`src/App.tsx`:

```typescript
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { Home } from './pages/Home';

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Home />} />
        {/* additional routes added in later tasks */}
      </Routes>
    </BrowserRouter>
  );
}
```

- [ ] **Step 2: Verify dev server still boots**

```bash
timeout 8 npm run dev 2>&1 | tee /tmp/router-boot.log | grep -E "(VITE|error)"
```

Expect "VITE ready" line, no errors.

- [ ] **Step 3: Commit**

```bash
git add src/App.tsx
git commit -m "feat(web): add React Router with Home at /"
```

---

## Task 18: Auth helpers (TDD)

**Files:**
- Create: `src/lib/auth.ts`
- Create: `src/__tests__/auth.test.ts`

- [ ] **Step 1: Write tests**

```typescript
// src/__tests__/auth.test.ts
import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mock supabase client before import
vi.mock('../lib/supabase', () => {
  const signInWithOtp = vi.fn();
  const signOut = vi.fn();
  const getSession = vi.fn();
  return {
    supabase: { auth: { signInWithOtp, signOut, getSession } },
  };
});

import { sendMagicLink, signOut, getCurrentSession } from '../lib/auth';
import { supabase } from '../lib/supabase';

describe('auth helpers', () => {
  beforeEach(() => vi.clearAllMocks());

  it('sendMagicLink calls supabase.auth.signInWithOtp with correct redirect', async () => {
    (supabase.auth.signInWithOtp as any).mockResolvedValue({ data: {}, error: null });
    const result = await sendMagicLink('test@example.com', 'https://app/report/123');
    expect(supabase.auth.signInWithOtp).toHaveBeenCalledWith({
      email: 'test@example.com',
      options: { emailRedirectTo: 'https://app/report/123' },
    });
    expect(result.ok).toBe(true);
  });

  it('sendMagicLink returns error on supabase failure', async () => {
    (supabase.auth.signInWithOtp as any).mockResolvedValue({ data: null, error: { message: 'rate limited' } });
    const result = await sendMagicLink('test@example.com', 'https://app');
    expect(result.ok).toBe(false);
    expect((result as any).error).toMatch(/rate limited/);
  });

  it('signOut calls supabase.auth.signOut', async () => {
    (supabase.auth.signOut as any).mockResolvedValue({ error: null });
    await signOut();
    expect(supabase.auth.signOut).toHaveBeenCalled();
  });

  it('getCurrentSession returns session when present', async () => {
    (supabase.auth.getSession as any).mockResolvedValue({ data: { session: { user: { id: 'u1', email: 'x' } } } });
    const session = await getCurrentSession();
    expect(session?.user.id).toBe('u1');
  });
});
```

- [ ] **Step 2: Run — FAIL**

```bash
npx vitest run src/__tests__/auth.test.ts
```

- [ ] **Step 3: Implement**

```typescript
// src/lib/auth.ts
import { supabase } from './supabase';

export async function sendMagicLink(
  email: string,
  redirectTo: string,
): Promise<{ ok: true } | { ok: false; error: string }> {
  const { error } = await supabase.auth.signInWithOtp({
    email,
    options: { emailRedirectTo: redirectTo },
  });
  if (error) return { ok: false, error: error.message };
  return { ok: true };
}

export async function signOut(): Promise<void> {
  await supabase.auth.signOut();
}

export async function getCurrentSession() {
  const { data } = await supabase.auth.getSession();
  return data.session;
}
```

- [ ] **Step 4: Tests GREEN, commit**

```bash
npx vitest run src/__tests__/auth.test.ts
git add src/lib/auth.ts src/__tests__/auth.test.ts
git commit -m "feat(auth): add magic-link + session helper functions with test coverage"
```

---

## Task 19: EmailGateForm component (TDD)

**Files:**
- Create: `src/components/EmailGateForm.tsx`
- Create: `src/__tests__/EmailGateForm.test.tsx`

- [ ] **Step 1: Write tests**

```typescript
// EmailGateForm.test.tsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { EmailGateForm } from '../components/EmailGateForm';

describe('EmailGateForm', () => {
  it('calls onSubmit with entered email', async () => {
    const onSubmit = vi.fn(async () => ({ ok: true as const }));
    render(<EmailGateForm onSubmit={onSubmit} />);
    await userEvent.type(screen.getByLabelText(/email/i), 'renter@example.com');
    await userEvent.click(screen.getByRole('button', { name: /send/i }));
    expect(onSubmit).toHaveBeenCalledWith('renter@example.com');
  });

  it('shows success state after successful submission', async () => {
    const onSubmit = vi.fn(async () => ({ ok: true as const }));
    render(<EmailGateForm onSubmit={onSubmit} />);
    await userEvent.type(screen.getByLabelText(/email/i), 'renter@example.com');
    await userEvent.click(screen.getByRole('button', { name: /send/i }));
    expect(await screen.findByText(/check your email/i)).toBeInTheDocument();
  });

  it('shows error on submission failure', async () => {
    const onSubmit = vi.fn(async () => ({ ok: false as const, error: 'rate limited' }));
    render(<EmailGateForm onSubmit={onSubmit} />);
    await userEvent.type(screen.getByLabelText(/email/i), 'renter@example.com');
    await userEvent.click(screen.getByRole('button', { name: /send/i }));
    expect(await screen.findByText(/rate limited/i)).toBeInTheDocument();
  });

  it('rejects obviously invalid emails', async () => {
    const onSubmit = vi.fn();
    render(<EmailGateForm onSubmit={onSubmit} />);
    await userEvent.type(screen.getByLabelText(/email/i), 'notanemail');
    await userEvent.click(screen.getByRole('button', { name: /send/i }));
    expect(onSubmit).not.toHaveBeenCalled();
  });
});
```

- [ ] **Step 2: Run — FAIL**

- [ ] **Step 3: Implement**

```typescript
// src/components/EmailGateForm.tsx
import { FormEvent, useState } from 'react';

type Result = { ok: true } | { ok: false; error: string };

interface Props {
  onSubmit: (email: string) => Promise<Result>;
}

export function EmailGateForm({ onSubmit }: Props) {
  const [email, setEmail] = useState('');
  const [state, setState] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle');
  const [errorMsg, setErrorMsg] = useState('');

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;
    setState('submitting');
    const result = await onSubmit(email);
    if (result.ok) {
      setState('success');
    } else {
      setState('error');
      setErrorMsg(result.error);
    }
  }

  if (state === 'success') {
    return (
      <div className="bg-green-50 border border-green-200 rounded p-4 max-w-2xl">
        <p className="font-medium">Check your email.</p>
        <p className="text-sm text-gray-700 mt-1">We sent a sign-in link to {email}. It expires in 10 minutes.</p>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="bg-white border border-gray-200 rounded-lg p-6 max-w-2xl">
      <h3 className="font-semibold mb-2">See the full report — free</h3>
      <p className="text-sm text-gray-600 mb-4">
        We'll check LLC members, sex offender registry, property history, and surface any red flags.
        No password needed. We'll send a one-time sign-in link to your email.
      </p>
      <label htmlFor="email" className="block text-sm font-medium mb-1">Email</label>
      <div className="flex flex-col sm:flex-row gap-2">
        <input
          id="email"
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="you@example.com"
          className="flex-1 border border-gray-300 rounded px-3 py-2"
          autoComplete="email"
        />
        <button
          type="submit"
          disabled={state === 'submitting'}
          className="bg-blue-600 text-white px-4 py-2 rounded font-medium disabled:opacity-50"
        >
          {state === 'submitting' ? 'Sending…' : 'Send me the full report'}
        </button>
      </div>
      {state === 'error' && (
        <p className="mt-2 text-sm text-red-700">{errorMsg}</p>
      )}
    </form>
  );
}
```

- [ ] **Step 4: GREEN, commit**

```bash
npx vitest run src/__tests__/EmailGateForm.test.tsx
git add src/components/EmailGateForm.tsx src/__tests__/EmailGateForm.test.tsx
git commit -m "feat(web): add EmailGateForm with validation + submit states"
```

---

## Task 20: Section display components (LlcMembersCard + SorMatchCard + RedFlagSummary + DisclaimerBlock)

**Files:**
- Create: `src/components/LlcMembersCard.tsx` + test
- Create: `src/components/SorMatchCard.tsx` + test
- Create: `src/components/RedFlagSummary.tsx` + test
- Create: `src/components/DisclaimerBlock.tsx`

- [ ] **Step 1: LlcMembersCard test + impl**

Test:
```typescript
// LlcMembersCard.test.tsx
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { LlcMembersCard } from '../components/LlcMembersCard';
import type { LlcInfo } from '../types/llc-info';

const llc: LlcInfo = {
  llcName: 'ACME PROPERTIES LLC',
  state: 'WI',
  registeredAgent: 'JOHN DOE',
  members: [{ name: 'JOHN DOE', role: 'Manager', sourceUrl: 'https://wdfi.org/x' }],
  filingDate: '2020-01-15',
  sourceUrl: 'https://wdfi.org/x',
  isRegisteredAgentService: false,
};

describe('LlcMembersCard', () => {
  it('renders LLC name, agent, members', () => {
    render(<LlcMembersCard llc={llc} />);
    expect(screen.getByText('ACME PROPERTIES LLC')).toBeInTheDocument();
    expect(screen.getAllByText('JOHN DOE').length).toBeGreaterThanOrEqual(1);
  });

  it('flags registered-agent service explicitly', () => {
    render(<LlcMembersCard llc={{ ...llc, registeredAgent: 'CT CORPORATION', isRegisteredAgentService: true }} />);
    expect(screen.getByText(/registered agent service/i)).toBeInTheDocument();
  });

  it('shows failure state when llc is null + status=failed', () => {
    render(<LlcMembersCard llc={null} state="WI" llcName="ACME LLC" status="failed" />);
    expect(screen.getByText(/information isn't exposed publicly/i)).toBeInTheDocument();
  });
});
```

Impl:
```typescript
// src/components/LlcMembersCard.tsx
import type { LlcInfo } from '../types/llc-info';

interface Props {
  llc: LlcInfo | null;
  state?: string;
  llcName?: string;
  status?: 'complete' | 'failed';
}

export function LlcMembersCard(props: Props) {
  const { llc, status, state, llcName } = props;

  if (!llc && status === 'failed') {
    return (
      <div className="bg-amber-50 border border-amber-200 rounded p-4">
        <h3 className="font-semibold mb-1">LLC member information unavailable</h3>
        <p className="text-sm text-gray-700">
          {state} Secretary of State's database information isn't exposed publicly right now for {llcName}. Try the state's portal directly.
        </p>
      </div>
    );
  }

  if (!llc) return null;

  return (
    <div className="bg-white border border-gray-200 rounded p-4">
      <h3 className="font-semibold mb-2">{llc.llcName}</h3>
      {llc.isRegisteredAgentService && (
        <p className="text-xs bg-gray-100 rounded px-2 py-1 inline-block mb-2">
          ⚠ {llc.registeredAgent} is a registered agent service — not the real property owner.
        </p>
      )}
      <dl className="grid grid-cols-2 gap-2 text-sm">
        <dt className="text-gray-500">Registered agent</dt>
        <dd>{llc.registeredAgent ?? 'Unknown'}</dd>
        <dt className="text-gray-500">Filing date</dt>
        <dd>{llc.filingDate ?? 'Unknown'}</dd>
      </dl>
      {llc.members.length > 0 && (
        <>
          <p className="text-gray-500 mt-4 mb-2 text-sm">Members / officers</p>
          <ul className="space-y-1 text-sm">
            {llc.members.map((m, i) => (
              <li key={i}>
                <strong>{m.name}</strong> — {m.role}
              </li>
            ))}
          </ul>
        </>
      )}
      <p className="text-xs text-gray-400 mt-3">
        Source: <a href={llc.sourceUrl} className="underline" target="_blank" rel="noopener noreferrer">{llc.state} Secretary of State</a>
      </p>
    </div>
  );
}
```

- [ ] **Step 2: SorMatchCard test + impl**

Test:
```typescript
// SorMatchCard.test.tsx
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { SorMatchCard } from '../components/SorMatchCard';

const match = {
  name: 'JOHN DOE',
  dobYear: 1970,
  offense: 'Lewd conduct',
  jurisdiction: 'Dane County, WI',
  photoUrl: 'https://nsopw.gov/photo.jpg',
  nsopwUrl: 'https://nsopw.gov/offender/123',
};

describe('SorMatchCard', () => {
  it('renders name, offense, jurisdiction', () => {
    render(<SorMatchCard match={match} />);
    expect(screen.getByText('JOHN DOE')).toBeInTheDocument();
    expect(screen.getByText(/lewd conduct/i)).toBeInTheDocument();
    expect(screen.getByText(/dane county/i)).toBeInTheDocument();
  });

  it('renders photo when photoUrl present', () => {
    render(<SorMatchCard match={match} />);
    expect(screen.getByRole('img')).toHaveAttribute('src', match.photoUrl);
  });

  it('renders no-photo placeholder when photoUrl is null', () => {
    render(<SorMatchCard match={{ ...match, photoUrl: null }} />);
    expect(screen.getByText(/no photo/i)).toBeInTheDocument();
  });

  it('includes NSOPW verification link', () => {
    render(<SorMatchCard match={match} />);
    const link = screen.getByRole('link', { name: /verify/i });
    expect(link).toHaveAttribute('href', match.nsopwUrl);
  });
});
```

Impl:
```typescript
// src/components/SorMatchCard.tsx
import type { SorMatch } from '../types/sor-match';

export function SorMatchCard({ match }: { match: SorMatch }) {
  return (
    <div className="bg-white border border-amber-200 rounded p-4 flex gap-4">
      <div className="w-24 h-32 bg-gray-100 rounded flex items-center justify-center overflow-hidden flex-shrink-0">
        {match.photoUrl
          ? <img src={match.photoUrl} alt={match.name} className="w-full h-full object-cover" />
          : <span className="text-xs text-gray-500 text-center p-2">No photo on file</span>}
      </div>
      <div className="flex-1">
        <h4 className="font-semibold">{match.name}</h4>
        {match.dobYear && <p className="text-sm text-gray-600">Born ~{match.dobYear}</p>}
        <p className="text-sm mt-1"><span className="font-medium">Offense:</span> {match.offense}</p>
        <p className="text-sm"><span className="font-medium">Jurisdiction:</span> {match.jurisdiction}</p>
        <a
          href={match.nsopwUrl}
          className="inline-block mt-2 text-sm text-blue-700 underline"
          target="_blank"
          rel="noopener noreferrer"
        >
          Verify on NSOPW →
        </a>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: RedFlagSummary test + impl**

Test:
```typescript
// RedFlagSummary.test.tsx
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { RedFlagSummary } from '../components/RedFlagSummary';
import type { Finding } from '../types/finding';

describe('RedFlagSummary', () => {
  it('renders findings in order (warn > info > ok)', () => {
    const findings: Finding[] = [
      { severity: 'warn', section: 'sor', title: 'SOR match', body: 'x' },
      { severity: 'info', section: 'property', title: 'Out-of-state', body: 'y' },
    ];
    render(<RedFlagSummary findings={findings} />);
    const titles = screen.getAllByRole('heading');
    expect(titles[0]).toHaveTextContent('SOR match');
    expect(titles[1]).toHaveTextContent('Out-of-state');
  });

  it('applies warn styling to warn findings', () => {
    const findings: Finding[] = [{ severity: 'warn', section: 'property', title: 'Tax delinquent', body: 'x' }];
    render(<RedFlagSummary findings={findings} />);
    const el = screen.getByText('Tax delinquent').closest('div');
    expect(el?.className).toMatch(/amber|red/);
  });

  it('applies ok styling to ok findings', () => {
    const findings: Finding[] = [{ severity: 'ok', section: 'property', title: 'No red flags', body: 'x' }];
    render(<RedFlagSummary findings={findings} />);
    const el = screen.getByText('No red flags').closest('div');
    expect(el?.className).toMatch(/green/);
  });
});
```

Impl:
```typescript
// src/components/RedFlagSummary.tsx
import type { Finding, Severity } from '../types/finding';

const STYLES: Record<Severity, { wrap: string; icon: string }> = {
  warn: { wrap: 'bg-amber-50 border-amber-300 text-amber-900', icon: '⚠' },
  info: { wrap: 'bg-blue-50 border-blue-200 text-blue-900', icon: 'ℹ' },
  ok:   { wrap: 'bg-green-50 border-green-200 text-green-900', icon: '✓' },
};

export function RedFlagSummary({ findings }: { findings: Finding[] }) {
  return (
    <div className="space-y-3">
      {findings.map((f, i) => (
        <div key={i} className={`border rounded p-3 ${STYLES[f.severity].wrap}`}>
          <h4 className="font-semibold text-sm">
            <span className="mr-2">{STYLES[f.severity].icon}</span>{f.title}
          </h4>
          <p className="text-sm mt-1">{f.body}</p>
        </div>
      ))}
    </div>
  );
}
```

- [ ] **Step 4: DisclaimerBlock**

```typescript
// src/components/DisclaimerBlock.tsx
export function DisclaimerBlock({ text, source }: { text: string; source: string }) {
  return (
    <div className="bg-gray-50 border border-gray-200 rounded p-3 text-xs text-gray-600 mt-4">
      <p className="font-semibold mb-1">{source} disclaimer:</p>
      <p className="whitespace-pre-line">{text}</p>
    </div>
  );
}
```

- [ ] **Step 5: All tests pass, commit**

```bash
npx vitest run
git add src/components/LlcMembersCard.tsx src/components/SorMatchCard.tsx src/components/RedFlagSummary.tsx src/components/DisclaimerBlock.tsx src/__tests__/LlcMembersCard.test.tsx src/__tests__/SorMatchCard.test.tsx src/__tests__/RedFlagSummary.test.tsx
git commit -m "feat(web): add LlcMembersCard, SorMatchCard, RedFlagSummary, DisclaimerBlock components"
```

---

## Task 21: Report client + Report page

**Files:**
- Create: `src/lib/report-client.ts`
- Create: `src/pages/Report.tsx`
- Create: `src/components/FreeReportSections.tsx`
- Modify: `src/App.tsx` (add `/report/:id` route)

- [ ] **Step 1: Report client**

```typescript
// src/lib/report-client.ts
import { supabase } from './supabase';
import type { Report } from '../types/report';

export async function runFreeReport(address: string): Promise<{ ok: true; data: Report } | { ok: false; error: string }> {
  const { data, error } = await supabase.functions.invoke<{ ok: boolean; data?: any; error?: { message: string } }>(
    'free-report',
    { body: { address } },
  );
  if (error || !data?.ok) {
    return { ok: false, error: error?.message ?? data?.error?.message ?? 'unknown error' };
  }
  return { ok: true, data: data.data as Report };
}

export async function fetchReportById(reportId: string): Promise<Report | null> {
  const { data: report } = await supabase
    .from('reports')
    .select('*')
    .eq('id', reportId)
    .maybeSingle();
  if (!report) return null;
  const { data: sections } = await supabase
    .from('report_sections')
    .select('*')
    .eq('report_id', reportId);

  const sectionsMap: any = {
    ownerCard: empty(), llcMembers: empty(), sor: empty(), propertySnapshot: empty(), redFlags: empty(),
  };
  (sections ?? []).forEach(s => {
    const key = dbToKey(s.section_type);
    if (key) {
      sectionsMap[key] = {
        status: s.status,
        data: s.data,
        sourceAttribution: s.source_attribution,
        error: s.error_code ? { code: s.error_code, message: s.error_message } : null,
      };
    }
  });

  return {
    id: report.id,
    userId: report.user_id,
    addressId: report.address_id,
    tier: report.tier,
    status: report.status,
    createdAt: report.created_at,
    expiresAt: report.expires_at,
    sections: sectionsMap,
  };
}

function empty() { return { status: 'pending', data: null, sourceAttribution: null, error: null }; }
function dbToKey(t: string): string | null {
  const map: Record<string, string> = {
    owner_card: 'ownerCard', llc_members: 'llcMembers', sor: 'sor',
    property_snapshot: 'propertySnapshot', red_flags: 'redFlags',
  };
  return map[t] ?? null;
}
```

- [ ] **Step 2: FreeReportSections component**

```typescript
// src/components/FreeReportSections.tsx
import type { Report } from '../types/report';
import { OwnerCardDisplay } from './OwnerCardDisplay';
import { LlcMembersCard } from './LlcMembersCard';
import { SorMatchCard } from './SorMatchCard';
import { RedFlagSummary } from './RedFlagSummary';
import { DisclaimerBlock } from './DisclaimerBlock';

export function FreeReportSections({ report }: { report: Report }) {
  const { ownerCard, llcMembers, sor, redFlags } = report.sections;

  return (
    <div className="space-y-8">
      {ownerCard.status === 'complete' && ownerCard.data && <OwnerCardDisplay card={ownerCard.data} />}

      {ownerCard.data?.owner.type === 'llc' && (
        <section>
          <h2 className="text-xl font-semibold mb-3">Who's behind this LLC?</h2>
          <LlcMembersCard
            llc={llcMembers.data}
            state={ownerCard.data.address.state ?? undefined}
            llcName={ownerCard.data.owner.name}
            status={llcMembers.status === 'failed' ? 'failed' : 'complete'}
          />
        </section>
      )}

      {sor.status === 'complete' && sor.data && (
        <section>
          <h2 className="text-xl font-semibold mb-3">Sex offender registry check</h2>
          {sor.data.matches.length === 0 ? (
            <p className="text-sm text-green-900 bg-green-50 border border-green-200 rounded p-3">
              No matches found in {sor.data.stateSearched} for someone named "{sor.data.nameSearched}".
            </p>
          ) : (
            <div className="space-y-3">
              <p className="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded p-3">
                <strong>{sor.data.matches.length}</strong> match(es) found. This doesn't mean the landlord IS any of these people — verify identity via photos.
              </p>
              {sor.data.matches.map((m, i) => <SorMatchCard key={i} match={m} />)}
            </div>
          )}
          <DisclaimerBlock text={sor.data.disclaimerText} source="NSOPW" />
        </section>
      )}

      {sor.status === 'failed' && (
        <section>
          <h2 className="text-xl font-semibold mb-3">Sex offender registry check</h2>
          <p className="text-sm bg-amber-50 border border-amber-200 rounded p-3">
            Couldn't reach NSOPW. Try refreshing in a few minutes.
          </p>
        </section>
      )}

      {redFlags.status === 'complete' && redFlags.data && (
        <section>
          <h2 className="text-xl font-semibold mb-3">Red flags</h2>
          <RedFlagSummary findings={redFlags.data} />
        </section>
      )}
    </div>
  );
}
```

- [ ] **Step 3: Report page**

```typescript
// src/pages/Report.tsx
import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { FreeReportSections } from '../components/FreeReportSections';
import { LegalDisclaimer } from '../components/LegalDisclaimer';
import { fetchReportById } from '../lib/report-client';
import type { Report as ReportType } from '../types/report';

export function Report() {
  const { id } = useParams<{ id: string }>();
  const [report, setReport] = useState<ReportType | null>(null);
  const [state, setState] = useState<'loading' | 'ready' | 'not_found'>('loading');

  useEffect(() => {
    if (!id) return;
    fetchReportById(id).then(r => {
      if (r) { setReport(r); setState('ready'); }
      else { setState('not_found'); }
    });
  }, [id]);

  if (state === 'loading') return <div className="p-8">Loading report…</div>;
  if (state === 'not_found') return <div className="p-8">Report not found.</div>;

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-3xl mx-auto px-4 py-8">
        <h1 className="text-2xl font-bold mb-6">Your landlord report</h1>
        {report && <FreeReportSections report={report} />}
        <LegalDisclaimer />
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Add route**

Modify `src/App.tsx`:

```typescript
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { Home } from './pages/Home';
import { Report } from './pages/Report';

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/report/:id" element={<Report />} />
      </Routes>
    </BrowserRouter>
  );
}
```

- [ ] **Step 5: Commit**

```bash
git add src/lib/report-client.ts src/pages/Report.tsx src/components/FreeReportSections.tsx src/App.tsx
git commit -m "feat(web): add Report page + FreeReportSections + report-client"
```

---

## Task 22: Dashboard page

**Files:**
- Create: `src/pages/Dashboard.tsx`
- Create: `src/__tests__/Dashboard.test.tsx`
- Modify: `src/App.tsx` (add `/dashboard` route)

- [ ] **Step 1: Write Dashboard**

```typescript
// src/pages/Dashboard.tsx
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { supabase } from '../lib/supabase';
import { signOut } from '../lib/auth';

interface ReportRow {
  id: string;
  address: string;
  created_at: string;
  status: string;
}

export function Dashboard() {
  const [reports, setReports] = useState<ReportRow[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      const { data } = await supabase
        .from('reports')
        .select('id, created_at, status, addresses(raw_input)')
        .order('created_at', { ascending: false });
      setReports((data ?? []).map((r: any) => ({
        id: r.id,
        address: r.addresses?.raw_input ?? '(unknown address)',
        created_at: r.created_at,
        status: r.status,
      })));
      setLoading(false);
    })();
  }, []);

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-3xl mx-auto px-4 py-8">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-bold">Your reports</h1>
          <button onClick={signOut} className="text-sm text-gray-600 underline">Sign out</button>
        </div>
        {loading ? (
          <p>Loading…</p>
        ) : reports.length === 0 ? (
          <p className="text-gray-600">No reports yet. <Link to="/" className="text-blue-700 underline">Run your first one →</Link></p>
        ) : (
          <ul className="bg-white border border-gray-200 rounded divide-y">
            {reports.map(r => (
              <li key={r.id}>
                <Link to={`/report/${r.id}`} className="block p-4 hover:bg-gray-50">
                  <p className="font-medium">{r.address}</p>
                  <p className="text-sm text-gray-500">
                    {new Date(r.created_at).toLocaleDateString()} · {r.status}
                  </p>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Simple smoke test**

```typescript
// src/__tests__/Dashboard.test.tsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

vi.mock('../lib/supabase', () => ({
  supabase: {
    from: () => ({
      select: () => ({
        order: () => Promise.resolve({ data: [] }),
      }),
    }),
    auth: { signOut: vi.fn() },
  },
}));

import { Dashboard } from '../pages/Dashboard';

describe('Dashboard', () => {
  it('renders empty state when no reports', async () => {
    render(<MemoryRouter><Dashboard /></MemoryRouter>);
    expect(await screen.findByText(/no reports yet/i)).toBeInTheDocument();
  });
});
```

- [ ] **Step 3: Add route**

```typescript
// App.tsx
import { Dashboard } from './pages/Dashboard';
// inside <Routes>:
<Route path="/dashboard" element={<Dashboard />} />
```

- [ ] **Step 4: Commit**

```bash
git add src/pages/Dashboard.tsx src/__tests__/Dashboard.test.tsx src/App.tsx
git commit -m "feat(web): add Dashboard page showing user's report history"
```

---

## Task 23: Wire EmailGateForm into Home + magic-link return flow

**Files:**
- Modify: `src/pages/Home.tsx`

- [ ] **Step 1: Update Home to show email gate after Owner Card**

```typescript
// src/pages/Home.tsx (updated)
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AddressInput } from '../components/AddressInput';
import { OwnerCardDisplay } from '../components/OwnerCardDisplay';
import { LoadingState } from '../components/LoadingState';
import { ErrorState } from '../components/ErrorState';
import { LegalDisclaimer } from '../components/LegalDisclaimer';
import { EmailGateForm } from '../components/EmailGateForm';
import { lookupOwner } from '../lib/api';
import { sendMagicLink, getCurrentSession } from '../lib/auth';
import { runFreeReport } from '../lib/report-client';
import type { OwnerCard, OwnerCardError } from '../types/owner-card';

type ViewState =
  | { kind: 'idle' }
  | { kind: 'loading' }
  | { kind: 'result'; card: OwnerCard }
  | { kind: 'error'; error: OwnerCardError };

export function Home() {
  const navigate = useNavigate();
  const [state, setState] = useState<ViewState>({ kind: 'idle' });
  const [signedIn, setSignedIn] = useState(false);
  const [lastAddress, setLastAddress] = useState('');

  useEffect(() => {
    getCurrentSession().then(s => setSignedIn(!!s));
  }, []);

  async function handleSubmit(address: string) {
    setLastAddress(address);
    setState({ kind: 'loading' });
    const result = await lookupOwner(address);
    if (result.ok) setState({ kind: 'result', card: result.data });
    else setState({ kind: 'error', error: result.error });
  }

  async function handleEmailSubmit(email: string) {
    const redirectTo = `${window.location.origin}/report/pending`;  // Report page will look up
    return await sendMagicLink(email, redirectTo);
  }

  async function handleSignedInRunReport() {
    const result = await runFreeReport(lastAddress);
    if (result.ok) {
      navigate(`/report/${result.data.id}`);
    } else {
      setState({ kind: 'error', error: { code: 'upstream_error', message: result.error } });
    }
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-3xl mx-auto px-4 py-12">
        <header className="mb-10">
          <h1 className="text-3xl font-bold mb-2">Who actually owns this rental?</h1>
          <p className="text-gray-600">
            Enter a property address to see the owner of record. Free. No signup needed for the basics.
          </p>
        </header>

        <div className="mb-8">
          <AddressInput onSubmit={handleSubmit} loading={state.kind === 'loading'} />
        </div>

        {state.kind === 'loading' && <LoadingState />}
        {state.kind === 'result' && (
          <>
            <OwnerCardDisplay card={state.card} />
            <div className="mt-8">
              {signedIn
                ? <button onClick={handleSignedInRunReport} className="bg-blue-600 text-white px-4 py-2 rounded">Run full report</button>
                : <EmailGateForm onSubmit={handleEmailSubmit} />
              }
            </div>
          </>
        )}
        {state.kind === 'error' && <ErrorState error={state.error} onRetry={() => setState({ kind: 'idle' })} />}

        <LegalDisclaimer />
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Handle magic-link return (`/report/pending`)**

Update `src/pages/Report.tsx` to handle the `pending` id: when id === 'pending', look at the session + run a new report for the last-viewed address (persisted in localStorage for simplicity).

Updated Report.tsx snippet (add before existing logic):

```typescript
useEffect(() => {
  if (id === 'pending') {
    const lastAddress = localStorage.getItem('landlordlens:lastAddress');
    if (lastAddress) {
      runFreeReport(lastAddress).then(result => {
        if (result.ok) {
          navigate(`/report/${result.data.id}`, { replace: true });
        } else {
          setState('not_found');
        }
      });
      return;
    }
  }
  if (!id || id === 'pending') return;
  fetchReportById(id).then(r => { ... });
}, [id]);
```

Also update Home.tsx's `handleSubmit` to `localStorage.setItem('landlordlens:lastAddress', address)`.

Both modifications live in this task.

- [ ] **Step 3: Commit**

```bash
git add src/pages/Home.tsx src/pages/Report.tsx
git commit -m "feat(web): wire EmailGateForm into Home + handle magic-link return flow"
```

---

## Task 24: Deploy + E2E smoke test + tag v0.2.0-m2

**Files:** None new; deploy + verify.

- [ ] **Step 1: Run full test suite**

```bash
cd /Users/daniel/twins-dashboard/landlordlens
npx vitest run
/Users/daniel/.deno/bin/deno test --allow-read --allow-net --allow-env supabase/functions/
```

Expect: all tests pass, zero failures. If any fail, STOP and debug before deploy.

- [ ] **Step 2: Deploy edge functions (fresh push)**

```bash
set -a; source ~/.config/landlordlens-credentials.env; set +a
for fn in owner-lookup sos-piercer nsopw-scraper free-report; do
  SUPABASE_ACCESS_TOKEN=$SUPABASE_ACCESS_TOKEN npx -y supabase@latest functions deploy $fn --project-ref viwrdwtxhpmcrjubomzp
done
```

All four should report "Deployed Functions on project viwrdwtxhpmcrjubomzp".

- [ ] **Step 3: Deploy frontend**

```bash
npx -y vercel@latest deploy --prod --yes --token $VERCEL_TOKEN
```

Record the production URL (should be `https://landlordlens-gamma.vercel.app`).

- [ ] **Step 4: E2E smoke test**

1. Open the live URL in a browser.
2. Enter `875 N Michigan Ave, Chicago, IL 60611` → confirm Owner Card renders.
3. Click the EmailGateForm, enter a real email address (e.g., your own) → verify you receive a magic-link email within 60 seconds.
4. Click the link → confirm you land on `/report/:id` with a rendered Free Report containing: Owner Card, SOR check (likely zero matches for "Unknown"), property snapshot, red-flag summary ("No red flags found" is acceptable).
5. Navigate to `/dashboard` → confirm the run report shows up.
6. Enter a WI/IL/MN LLC-owned address (you'll need a known one — ask Daniel) → run full report → confirm LLC piercing either succeeds or shows honest failure message.

If any step fails, STOP and report BLOCKED with specifics.

- [ ] **Step 5: Tag and push**

```bash
git tag v0.2.0-m2
git push origin main --tags
```

- [ ] **Step 6: Update memory**

Update `/Users/daniel/.claude/projects/-Users-daniel-twins-dashboard/memory/project_tenant_screening.md`:
- Status line → "M2 SHIPPED."
- Add tag `v0.2.0-m2`
- Mark M2 checklist items done, keep M3 items pending

Commit memory update.

---

## Milestone exit criteria

M2 is complete when:

1. All tests pass (vitest + deno).
2. All four edge functions (`owner-lookup`, `sos-piercer`, `nsopw-scraper`, `free-report`) are deployed and respond.
3. Magic-link sign-in flow works end-to-end in a real browser.
4. A signed-in user can run a Free Report on any US address and see Owner Card + SOR + property snapshot + red-flag summary.
5. For LLC-owned WI/IL/MN addresses, the LLC members section attempts piercing; honest failure messaging when it doesn't work.
6. Dashboard lists prior reports.
7. NSOPW disclaimer appears whenever SOR section has match data.
8. Red-flag engine's 10 test cases all pass.
9. Tag `v0.2.0-m2` pushed.

---

## Deferred to M3+

- Criminal records (via state court scrapers)
- Eviction filings by landlord
- Civil litigation
- Code violations
- Portfolio view (other properties by same entity)
- Stripe $12 Deep Report
- PDF export / shareable links
- Landlord dispute flow
- Rate limiting
- Monitoring / Sentry
- E&O insurance
- Vercel ↔ GitHub auto-deploy (install Vercel GitHub app on `palpulla/landlordlens`)
- Spanish UI
- Pre-push hook running `npm run build`
- Property snapshot `ownerMailingAddress` + `salesInLast5Years` enrichment (requires surfacing more of ATTOM's raw response through the data model)
