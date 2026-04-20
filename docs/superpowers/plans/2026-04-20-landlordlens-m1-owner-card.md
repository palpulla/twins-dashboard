# LandlordLens M1 — Owner Verification Card Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a public web page where anyone can enter a US property address and receive the county-record owner, last sale, and tax status — no auth, no payment, national coverage.

**Architecture:** New standalone repo (`landlordlens/`) scaffolded with Claude Design. React + TypeScript + Tailwind frontend calling a Supabase Edge Function that looks up ownership from the ATTOM Data API and caches results in Postgres. No auth in M1; public read-only endpoint.

**Tech Stack:**
- Frontend: React 18 + TypeScript + Tailwind (Claude Design output)
- Backend: Supabase (Postgres + Edge Functions + Storage)
- Ownership data: ATTOM Data Property Detail API
- Deployment: Vercel (frontend), Supabase (functions + DB)
- Testing: Vitest (frontend + shared logic), Deno test runner (edge functions)

**Spec:** [docs/superpowers/specs/2026-04-20-landlordlens-design.md](../specs/2026-04-20-landlordlens-design.md) (commit `fbe4909`)

**Milestone boundary:** This plan delivers ONLY the Owner Verification Card (spec §6.1). Criminal/SOR, court scrapers, violations, Stripe, dispute flow, auth — all deferred to M2–M4.

---

## File Structure

### New repository: `landlordlens/`

```
landlordlens/
├── README.md
├── package.json
├── tsconfig.json
├── vite.config.ts
├── vitest.config.ts
├── tailwind.config.ts
├── .env.example
├── .env.local                      # (gitignored)
├── index.html
├── src/
│   ├── main.tsx
│   ├── App.tsx                     # router root
│   ├── pages/
│   │   ├── Home.tsx                # address input form
│   │   ├── OwnerCard.tsx           # result display page
│   │   └── NotFound.tsx
│   ├── components/
│   │   ├── AddressInput.tsx
│   │   ├── OwnerCardDisplay.tsx
│   │   ├── LegalDisclaimer.tsx     # non-FCRA disclaimer (load-bearing!)
│   │   └── LoadingState.tsx
│   ├── lib/
│   │   ├── api.ts                  # fetch wrapper for edge function
│   │   ├── normalize.ts            # address normalization (cache key)
│   │   └── supabase.ts             # typed Supabase client
│   ├── types/
│   │   └── owner-card.ts           # OwnerCard shape shared with edge fn
│   └── __tests__/
│       ├── normalize.test.ts
│       ├── AddressInput.test.tsx
│       └── OwnerCardDisplay.test.tsx
└── supabase/
    ├── config.toml
    ├── migrations/
    │   ├── 20260420000001_create_addresses.sql
    │   ├── 20260420000002_create_owners.sql
    │   ├── 20260420000003_create_ownership_links.sql
    │   ├── 20260420000004_create_cached_lookups.sql
    │   └── 20260420000005_rls_policies.sql
    └── functions/
        ├── owner-lookup/
        │   ├── index.ts            # HTTP handler
        │   ├── attom-client.ts     # ATTOM API wrapper
        │   ├── owner-service.ts    # cache-aware lookup orchestration
        │   ├── types.ts            # shared with frontend via copy
        │   └── index.test.ts       # deno test
        └── _shared/
            ├── normalize.ts        # mirrors src/lib/normalize.ts
            └── cors.ts
```

### File responsibilities

- **`src/lib/normalize.ts`** — Pure address-string normalization for cache keys. Lowercase, trim, collapse whitespace, strip common suffixes. Must match the edge-function copy exactly.
- **`supabase/functions/owner-lookup/attom-client.ts`** — Thin wrapper over ATTOM's Property Detail endpoint. One function: `lookupOwner(normalizedAddress)`. Returns typed `OwnerCardRaw` or throws typed errors.
- **`supabase/functions/owner-lookup/owner-service.ts`** — Cache-aware orchestration. Checks `cached_lookups`, calls ATTOM on miss, persists to `addresses` / `owners` / `ownership_links`, returns `OwnerCard`.
- **`src/components/LegalDisclaimer.tsx`** — Renders the exact non-FCRA disclaimer required by spec §3. This component is rendered on every page. Do not parameterize the copy; changing the disclaimer should be a conscious code change.

---

## Prerequisites (one-time, before Task 1)

The engineer needs these before starting. If they don't exist, coordinate with Daniel.

- **ATTOM Data API key** (sandbox tier is fine for M1). Sign up at `api.developer.attomdata.com`. Store as `ATTOM_API_KEY`.
- **Supabase account** under Daniel's org. Create a new project named `landlordlens` in the `us-east-1` region. Note project ref + anon key + service role key.
- **Vercel account** connected to GitHub. Linked to the new `landlordlens` repo.
- **Google Places API key** (deferred — not used in M1; M2 adds autocomplete).

---

## Task 1: Scaffold repo and install tooling

**Files:**
- Create: `landlordlens/` (new directory at repo root)
- Create: `landlordlens/package.json`, `tsconfig.json`, `vite.config.ts`, `vitest.config.ts`, `tailwind.config.ts`, `index.html`, `src/main.tsx`, `src/App.tsx`, `.gitignore`, `.env.example`, `README.md`

- [ ] **Step 1: Create the directory and init git**

```bash
cd /Users/daniel/twins-dashboard
mkdir landlordlens
cd landlordlens
git init
```

- [ ] **Step 2: Scaffold Vite + React + TS**

```bash
npm create vite@latest . -- --template react-ts
npm install
```

- [ ] **Step 3: Install Tailwind, Vitest, Supabase client, React Router, Testing Library**

```bash
npm install -D tailwindcss postcss autoprefixer vitest @testing-library/react @testing-library/jest-dom @testing-library/user-event jsdom @types/node
npm install @supabase/supabase-js react-router-dom
npx tailwindcss init -p
```

- [ ] **Step 4: Configure Tailwind (`tailwind.config.ts`)**

```typescript
import type { Config } from 'tailwindcss';

export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: { extend: {} },
  plugins: [],
} satisfies Config;
```

- [ ] **Step 5: Add Tailwind to `src/index.css`**

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

- [ ] **Step 6: Configure Vitest (`vitest.config.ts`)**

```typescript
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/__tests__/setup.ts'],
  },
});
```

- [ ] **Step 7: Create `src/__tests__/setup.ts`**

```typescript
import '@testing-library/jest-dom';
```

- [ ] **Step 8: Create `.env.example` and `.gitignore` additions**

`.env.example`:
```
VITE_SUPABASE_URL=
VITE_SUPABASE_ANON_KEY=
```

Append to `.gitignore`:
```
.env
.env.local
```

- [ ] **Step 9: Verify tooling runs**

```bash
npm run dev   # verify server starts, ctrl-C to exit
npx vitest run   # expect "No test files found" or exit 0
```

Expected: dev server runs without errors; vitest exits cleanly.

- [ ] **Step 10: Commit**

```bash
git add .
git commit -m "chore: scaffold Vite + React + TS + Tailwind + Vitest"
```

---

## Task 2: Initialize Supabase project (local + remote)

**Files:**
- Create: `landlordlens/supabase/config.toml` (generated)
- Create: `landlordlens/.env.local` (gitignored — contains real keys)

- [ ] **Step 1: Install Supabase CLI (if not already installed)**

```bash
brew install supabase/tap/supabase
supabase --version
```

Expected: version ≥1.150.

- [ ] **Step 2: Create the remote Supabase project**

Via Supabase dashboard: create project `landlordlens` in `us-east-1`. Save the project ref, anon key, and service role key.

- [ ] **Step 3: Initialize local Supabase in the repo**

```bash
cd /Users/daniel/twins-dashboard/landlordlens
supabase init
```

This creates `supabase/config.toml` and `supabase/migrations/`.

- [ ] **Step 4: Link local to remote**

```bash
supabase link --project-ref <PROJECT_REF>
```

Prompt will ask for DB password — use Daniel's password manager.

- [ ] **Step 5: Populate `.env.local`**

```
VITE_SUPABASE_URL=https://<PROJECT_REF>.supabase.co
VITE_SUPABASE_ANON_KEY=<anon key>
```

- [ ] **Step 6: Verify local Supabase stack starts**

```bash
supabase start
```

Expected: Docker pulls images, stack comes up, prints local URL + anon key + service role key. `ctrl-C` to stop, or `supabase stop`.

- [ ] **Step 7: Commit**

```bash
git add supabase/config.toml .env.example
git commit -m "chore(supabase): initialize Supabase project + link to remote"
```

---

## Task 3: Create `addresses` table

**Files:**
- Create: `supabase/migrations/20260420000001_create_addresses.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260420000001_create_addresses.sql
create table public.addresses (
  id uuid primary key default gen_random_uuid(),
  raw_input text not null,
  normalized text not null unique,
  line1 text,
  city text,
  state text,
  zip text,
  county text,
  lat numeric,
  lng numeric,
  created_at timestamptz not null default now()
);

create index idx_addresses_normalized on public.addresses(normalized);
create index idx_addresses_state_city on public.addresses(state, city);
```

- [ ] **Step 2: Apply locally to verify syntax**

```bash
supabase db reset
```

Expected: migrations apply cleanly, no errors.

- [ ] **Step 3: Push to remote**

```bash
supabase db push
```

Expected: remote DB accepts migration.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260420000001_create_addresses.sql
git commit -m "feat(db): add addresses table"
```

---

## Task 4: Create `owners` table

**Files:**
- Create: `supabase/migrations/20260420000002_create_owners.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260420000002_create_owners.sql
create type public.owner_type as enum ('person', 'llc', 'corp', 'trust', 'other');
create type public.owner_source as enum ('attom', 'datatree', 'manual_dispute');

create table public.owners (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  type public.owner_type not null default 'other',
  address_of_record text,
  source public.owner_source not null,
  last_refreshed_at timestamptz not null default now(),
  created_at timestamptz not null default now()
);

create index idx_owners_name_lower on public.owners(lower(name));
```

- [ ] **Step 2: Apply + push**

```bash
supabase db reset
supabase db push
```

Expected: clean apply both locally and remote.

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260420000002_create_owners.sql
git commit -m "feat(db): add owners table with type/source enums"
```

---

## Task 5: Create `ownership_links` table

**Files:**
- Create: `supabase/migrations/20260420000003_create_ownership_links.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260420000003_create_ownership_links.sql
create table public.ownership_links (
  id uuid primary key default gen_random_uuid(),
  address_id uuid not null references public.addresses(id) on delete cascade,
  owner_id uuid not null references public.owners(id) on delete cascade,
  source public.owner_source not null,
  last_sale_date date,
  last_sale_price_cents bigint,
  tax_status text,
  property_type text,
  year_built int,
  sqft int,
  assessed_value_cents bigint,
  last_verified_at timestamptz not null default now(),
  created_at timestamptz not null default now(),
  unique(address_id, owner_id)
);

create index idx_ownership_links_address on public.ownership_links(address_id);
create index idx_ownership_links_owner on public.ownership_links(owner_id);
```

- [ ] **Step 2: Apply + push**

```bash
supabase db reset
supabase db push
```

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260420000003_create_ownership_links.sql
git commit -m "feat(db): add ownership_links join table with parcel snapshot"
```

---

## Task 6: Create `cached_lookups` table

**Files:**
- Create: `supabase/migrations/20260420000004_create_cached_lookups.sql`

- [ ] **Step 1: Write the migration**

```sql
-- supabase/migrations/20260420000004_create_cached_lookups.sql
create table public.cached_lookups (
  cache_key text primary key,
  response jsonb not null,
  fetched_at timestamptz not null default now(),
  expires_at timestamptz not null
);

create index idx_cached_lookups_expires on public.cached_lookups(expires_at);
```

- [ ] **Step 2: Apply + push**

```bash
supabase db reset
supabase db push
```

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260420000004_create_cached_lookups.sql
git commit -m "feat(db): add cached_lookups table for 30-day response caching"
```

---

## Task 7: RLS policies (anonymous-read for public lookup)

**Files:**
- Create: `supabase/migrations/20260420000005_rls_policies.sql`

- [ ] **Step 1: Write the migration**

M1's Owner Card endpoint is anonymous — we don't want unauthenticated clients reading the DB directly. Only the edge function (service role) writes/reads; the frontend only calls the edge function. So: enable RLS with no public-read policies.

```sql
-- supabase/migrations/20260420000005_rls_policies.sql
alter table public.addresses enable row level security;
alter table public.owners enable row level security;
alter table public.ownership_links enable row level security;
alter table public.cached_lookups enable row level security;

-- No policies = no access for anon or authenticated roles.
-- Edge functions use the service role key which bypasses RLS.
```

- [ ] **Step 2: Apply + push**

```bash
supabase db reset
supabase db push
```

- [ ] **Step 3: Commit**

```bash
git add supabase/migrations/20260420000005_rls_policies.sql
git commit -m "feat(db): enable RLS on all tables; no anon policies (edge-function-only access)"
```

---

## Task 8: Address normalization helper (frontend + edge function, mirrored)

**Files:**
- Create: `src/lib/normalize.ts`
- Create: `src/__tests__/normalize.test.ts`
- Create: `supabase/functions/_shared/normalize.ts` (byte-identical copy)

- [ ] **Step 1: Write the failing tests**

`src/__tests__/normalize.test.ts`:
```typescript
import { describe, it, expect } from 'vitest';
import { normalizeAddress } from '../lib/normalize';

describe('normalizeAddress', () => {
  it('lowercases and trims', () => {
    expect(normalizeAddress('  123 Main St, Madison, WI 53703  '))
      .toBe('123 main st, madison, wi 53703');
  });

  it('collapses whitespace', () => {
    expect(normalizeAddress('123   Main    St,   Madison, WI'))
      .toBe('123 main st, madison, wi');
  });

  it('strips trailing punctuation', () => {
    expect(normalizeAddress('123 Main St.,'))
      .toBe('123 main st');
  });

  it('expands common abbreviations to canonical short form', () => {
    expect(normalizeAddress('123 Main Street, Madison, Wisconsin'))
      .toBe('123 main st, madison, wi');
  });

  it('handles apartment/unit designators', () => {
    expect(normalizeAddress('123 Main St Apt 4B, Madison, WI'))
      .toBe('123 main st apt 4b, madison, wi');
  });

  it('is idempotent', () => {
    const once = normalizeAddress('  123 MAIN ST, MADISON, WI  ');
    expect(normalizeAddress(once)).toBe(once);
  });

  it('preserves state names when they appear in street names', () => {
    expect(normalizeAddress('875 N Michigan Ave, Chicago, IL 60611'))
      .toBe('875 n michigan ave, chicago, il 60611');
    expect(normalizeAddress('1600 Pennsylvania Ave, Washington, DC 20500'))
      .toBe('1600 pennsylvania ave, washington, dc 20500');
    expect(normalizeAddress('100 Virginia St, Reno, NV 89501'))
      .toBe('100 virginia st, reno, nv 89501');
  });
});
```

- [ ] **Step 2: Run tests — verify they fail**

```bash
npx vitest run src/__tests__/normalize.test.ts
```

Expected: FAIL — `normalizeAddress` not exported.

- [ ] **Step 3: Implement `src/lib/normalize.ts`**

```typescript
// src/lib/normalize.ts
const STATE_NAMES_TO_ABBR: Record<string, string> = {
  alabama: 'al', alaska: 'ak', arizona: 'az', arkansas: 'ar',
  california: 'ca', colorado: 'co', connecticut: 'ct', delaware: 'de',
  florida: 'fl', georgia: 'ga', hawaii: 'hi', idaho: 'id',
  illinois: 'il', indiana: 'in', iowa: 'ia', kansas: 'ks',
  kentucky: 'ky', louisiana: 'la', maine: 'me', maryland: 'md',
  massachusetts: 'ma', michigan: 'mi', minnesota: 'mn', mississippi: 'ms',
  missouri: 'mo', montana: 'mt', nebraska: 'ne', nevada: 'nv',
  'new hampshire': 'nh', 'new jersey': 'nj', 'new mexico': 'nm',
  'new york': 'ny', 'north carolina': 'nc', 'north dakota': 'nd',
  ohio: 'oh', oklahoma: 'ok', oregon: 'or', pennsylvania: 'pa',
  'rhode island': 'ri', 'south carolina': 'sc', 'south dakota': 'sd',
  tennessee: 'tn', texas: 'tx', utah: 'ut', vermont: 'vt',
  virginia: 'va', washington: 'wa', 'west virginia': 'wv',
  wisconsin: 'wi', wyoming: 'wy',
};

const STREET_SUFFIX_ABBR: Record<string, string> = {
  street: 'st', avenue: 'ave', boulevard: 'blvd', road: 'rd',
  drive: 'dr', lane: 'ln', court: 'ct', place: 'pl',
  terrace: 'ter', parkway: 'pkwy', highway: 'hwy', circle: 'cir',
};

export function normalizeAddress(input: string): string {
  let s = input.toLowerCase().trim();
  // collapse whitespace
  s = s.replace(/\s+/g, ' ');
  // strip trailing punctuation
  s = s.replace(/[.,;:\s]+$/, '');

  // Split on commas; state-name replacement only affects the final segment
  // (where state actually lives in US addresses).
  const segments = s.split(',').map(seg => seg.trim());
  if (segments.length > 0) {
    let last = segments[segments.length - 1];
    for (const [full, abbr] of Object.entries(STATE_NAMES_TO_ABBR)) {
      last = last.replace(new RegExp(`\\b${full}\\b`, 'g'), abbr);
    }
    segments[segments.length - 1] = last;
  }
  s = segments.join(', ');

  // Street suffix contractions apply everywhere (OK because these aren't ambiguous).
  for (const [full, abbr] of Object.entries(STREET_SUFFIX_ABBR)) {
    s = s.replace(new RegExp(`\\b${full}\\b\\.?`, 'g'), abbr);
  }
  // drop remaining periods
  s = s.replace(/\./g, '');
  // collapse whitespace again after substitutions
  s = s.replace(/\s+/g, ' ').trim();
  return s;
}
```

- [ ] **Step 4: Run tests — verify pass**

```bash
npx vitest run src/__tests__/normalize.test.ts
```

Expected: all 7 tests pass.

- [ ] **Step 5: Mirror to edge-function shared dir**

Copy `src/lib/normalize.ts` byte-for-byte to `supabase/functions/_shared/normalize.ts`. The edge function runs on Deno and imports differently, but the function body is identical.

```bash
mkdir -p supabase/functions/_shared
cp src/lib/normalize.ts supabase/functions/_shared/normalize.ts
```

- [ ] **Step 6: Commit**

```bash
git add src/lib/normalize.ts src/__tests__/normalize.test.ts supabase/functions/_shared/normalize.ts
git commit -m "feat: add address normalization helper with test coverage"
```

---

## Task 9: Define shared `OwnerCard` type

**Files:**
- Create: `src/types/owner-card.ts`
- Create: `supabase/functions/owner-lookup/types.ts` (byte-identical copy)

- [ ] **Step 1: Write `src/types/owner-card.ts`**

```typescript
// src/types/owner-card.ts
export type OwnerType = 'person' | 'llc' | 'corp' | 'trust' | 'other';

export interface OwnerCard {
  address: {
    normalized: string;
    display: string;   // the pretty/cased string to show users
    city: string | null;
    state: string | null;
    zip: string | null;
  };
  owner: {
    name: string;
    type: OwnerType;
  };
  parcel: {
    lastSaleDate: string | null;   // ISO date
    lastSalePriceCents: number | null;
    taxStatus: 'current' | 'delinquent' | 'unknown';
    propertyType: string | null;
    yearBuilt: number | null;
    sqft: number | null;
    assessedValueCents: number | null;
  };
  source: 'attom' | 'cache';
  fetchedAt: string;   // ISO timestamp
}

export interface OwnerCardError {
  code: 'not_found' | 'rate_limited' | 'upstream_error' | 'invalid_input';
  message: string;
}

export type OwnerCardResult =
  | { ok: true; data: OwnerCard }
  | { ok: false; error: OwnerCardError };
```

- [ ] **Step 2: Mirror to edge-function dir**

```bash
mkdir -p supabase/functions/owner-lookup
cp src/types/owner-card.ts supabase/functions/owner-lookup/types.ts
```

- [ ] **Step 3: Commit**

```bash
git add src/types/owner-card.ts supabase/functions/owner-lookup/types.ts
git commit -m "feat: define shared OwnerCard / OwnerCardResult types"
```

---

## Task 10: ATTOM API client (edge function)

**Files:**
- Create: `supabase/functions/owner-lookup/attom-client.ts`
- Create: `supabase/functions/owner-lookup/attom-client.test.ts`

- [ ] **Step 1: Write the failing tests**

`supabase/functions/owner-lookup/attom-client.test.ts`:
```typescript
import { assertEquals, assertRejects } from 'https://deno.land/std@0.208.0/assert/mod.ts';
import { createAttomClient } from './attom-client.ts';

Deno.test('attom-client: returns OwnerCard on successful lookup', async () => {
  const fakeFetch = async (url: string): Promise<Response> => {
    return new Response(JSON.stringify({
      status: { code: 0 },
      property: [{
        identifier: { obPropId: 'prop-123' },
        address: {
          line1: '123 Main St',
          locality: 'Madison',
          countrySubd: 'WI',
          postal1: '53703',
        },
        owner: {
          owner1: { fullname: 'JOHN DOE' },
        },
        sale: { saleTransDate: '2020-06-15', amount: { saleAmt: 350000 } },
        summary: { proptype: 'SFR', yearBuilt: 1998 },
        building: { size: { universalsize: 1850 } },
        assessment: {
          tax: { taxAmt: 5500, delinquent: false },
          assessed: { assdTtlValue: 280000 },
        },
      }],
    }), { status: 200, headers: { 'content-type': 'application/json' } });
  };
  const client = createAttomClient({ apiKey: 'test', fetchFn: fakeFetch });
  const result = await client.lookupOwner('123 main st, madison, wi 53703');
  assertEquals(result.owner.name, 'JOHN DOE');
  assertEquals(result.owner.type, 'person');
  assertEquals(result.parcel.yearBuilt, 1998);
  assertEquals(result.parcel.lastSalePriceCents, 35000000);
});

Deno.test('attom-client: throws not_found when ATTOM returns no property', async () => {
  const fakeFetch = async (): Promise<Response> => {
    return new Response(JSON.stringify({
      status: { code: 1, msg: 'SuccessWithoutResult' },
      property: [],
    }), { status: 200 });
  };
  const client = createAttomClient({ apiKey: 'test', fetchFn: fakeFetch });
  await assertRejects(
    () => client.lookupOwner('0 nowhere rd, nowhere, xx'),
    Error,
    'not_found',
  );
});

Deno.test('attom-client: throws rate_limited on 429', async () => {
  const fakeFetch = async (): Promise<Response> => {
    return new Response('', { status: 429 });
  };
  const client = createAttomClient({ apiKey: 'test', fetchFn: fakeFetch });
  await assertRejects(
    () => client.lookupOwner('anything'),
    Error,
    'rate_limited',
  );
});

Deno.test('attom-client: detects LLC ownership type from name', async () => {
  const fakeFetch = async (): Promise<Response> => {
    return new Response(JSON.stringify({
      status: { code: 0 },
      property: [{
        identifier: { obPropId: 'p' },
        address: { line1: '1 Main', locality: 'Madison', countrySubd: 'WI', postal1: '53703' },
        owner: { owner1: { fullname: 'ACME PROPERTIES LLC' } },
        sale: {}, summary: {}, building: { size: {} }, assessment: { tax: {}, assessed: {} },
      }],
    }), { status: 200 });
  };
  const client = createAttomClient({ apiKey: 'test', fetchFn: fakeFetch });
  const result = await client.lookupOwner('1 main st');
  assertEquals(result.owner.type, 'llc');
});
```

- [ ] **Step 2: Run tests — verify they fail**

```bash
cd landlordlens
supabase functions serve  # in one terminal, if needed for deno cache
# in another terminal:
deno test --allow-env --allow-net supabase/functions/owner-lookup/attom-client.test.ts
```

Expected: FAIL — `createAttomClient` not defined.

- [ ] **Step 3: Implement `attom-client.ts`**

```typescript
// supabase/functions/owner-lookup/attom-client.ts
import type { OwnerCard, OwnerType } from './types.ts';

interface AttomClientOptions {
  apiKey: string;
  fetchFn?: (url: string, init?: RequestInit) => Promise<Response>;
  baseUrl?: string;
}

interface AttomProperty {
  identifier: { obPropId: string };
  address: {
    line1: string;
    locality: string;
    countrySubd: string;
    postal1: string;
  };
  owner: { owner1: { fullname: string } };
  sale?: {
    saleTransDate?: string;
    amount?: { saleAmt?: number };
  };
  summary?: { proptype?: string; yearBuilt?: number };
  building?: { size?: { universalsize?: number } };
  assessment?: {
    tax?: { taxAmt?: number; delinquent?: boolean };
    assessed?: { assdTtlValue?: number };
  };
}

export function createAttomClient(opts: AttomClientOptions) {
  const fetchFn = opts.fetchFn ?? fetch;
  const baseUrl = opts.baseUrl ?? 'https://api.gateway.attomdata.com/propertyapi/v1.0.0';

  async function lookupOwner(normalizedAddress: string): Promise<OwnerCard> {
    // ATTOM expects address1 (street) + address2 (city, state, zip) separately.
    // Parse best-effort from normalized string "line1, city, state zip".
    const parts = normalizedAddress.split(',').map(s => s.trim());
    const line1 = parts[0] ?? normalizedAddress;
    const cityStateZip = parts.slice(1).join(', ') || '';

    const url = new URL(`${baseUrl}/property/detail`);
    url.searchParams.set('address1', line1);
    url.searchParams.set('address2', cityStateZip);

    const response = await fetchFn(url.toString(), {
      headers: { 'apikey': opts.apiKey, 'accept': 'application/json' },
    });

    if (response.status === 429) {
      throw new Error('rate_limited: ATTOM API rate limit exceeded');
    }
    if (!response.ok) {
      throw new Error(`upstream_error: ATTOM returned ${response.status}`);
    }

    const body = await response.json();
    if (!body.property || body.property.length === 0) {
      throw new Error('not_found: no property matched the address');
    }

    return mapAttomToOwnerCard(body.property[0], normalizedAddress);
  }

  return { lookupOwner };
}

function mapAttomToOwnerCard(p: AttomProperty, normalizedAddress: string): OwnerCard {
  const fullName = p.owner?.owner1?.fullname ?? 'Unknown';
  const addr = p.address;
  const sale = p.sale ?? {};
  const summary = p.summary ?? {};
  const bldg = p.building ?? { size: {} };
  const assess = p.assessment ?? { tax: {}, assessed: {} };

  return {
    address: {
      normalized: normalizedAddress,
      display: `${addr.line1}, ${addr.locality}, ${addr.countrySubd} ${addr.postal1}`,
      city: addr.locality ?? null,
      state: addr.countrySubd ?? null,
      zip: addr.postal1 ?? null,
    },
    owner: {
      name: fullName,
      type: detectOwnerType(fullName),
    },
    parcel: {
      lastSaleDate: sale.saleTransDate ?? null,
      lastSalePriceCents: sale.amount?.saleAmt ? sale.amount.saleAmt * 100 : null,
      taxStatus: assess.tax?.delinquent === true
        ? 'delinquent'
        : assess.tax?.delinquent === false
          ? 'current'
          : 'unknown',
      propertyType: summary.proptype ?? null,
      yearBuilt: summary.yearBuilt ?? null,
      sqft: bldg.size?.universalsize ?? null,
      assessedValueCents: assess.assessed?.assdTtlValue
        ? assess.assessed.assdTtlValue * 100
        : null,
    },
    source: 'attom',
    fetchedAt: new Date().toISOString(),
  };
}

function detectOwnerType(name: string): OwnerType {
  const upper = name.toUpperCase();
  if (/\b(LLC|L\.L\.C\.)\b/.test(upper)) return 'llc';
  if (/\b(INC|CORP|CORPORATION|CO\.?)\b/.test(upper)) return 'corp';
  if (/\bTRUST\b/.test(upper)) return 'trust';
  if (/\b(LP|LLP|PARTNERS|PARTNERSHIP)\b/.test(upper)) return 'other';
  return 'person';
}
```

- [ ] **Step 4: Run tests — verify pass**

```bash
deno test --allow-env --allow-net supabase/functions/owner-lookup/attom-client.test.ts
```

Expected: all 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/owner-lookup/attom-client.ts supabase/functions/owner-lookup/attom-client.test.ts
git commit -m "feat(edge): add ATTOM client with typed errors + owner-type detection"
```

---

## Task 11: Owner lookup service (cache orchestration)

**Files:**
- Create: `supabase/functions/owner-lookup/owner-service.ts`
- Create: `supabase/functions/owner-lookup/owner-service.test.ts`

- [ ] **Step 1: Write the failing tests**

`supabase/functions/owner-lookup/owner-service.test.ts`:
```typescript
import { assertEquals } from 'https://deno.land/std@0.208.0/assert/mod.ts';
import { createOwnerService } from './owner-service.ts';
import type { OwnerCard } from './types.ts';

const sampleCard: OwnerCard = {
  address: { normalized: '1 main st, madison, wi 53703', display: '1 Main St, Madison, WI 53703', city: 'Madison', state: 'WI', zip: '53703' },
  owner: { name: 'JOHN DOE', type: 'person' },
  parcel: { lastSaleDate: null, lastSalePriceCents: null, taxStatus: 'current', propertyType: null, yearBuilt: null, sqft: null, assessedValueCents: null },
  source: 'attom',
  fetchedAt: new Date().toISOString(),
};

Deno.test('owner-service: returns cached result without calling ATTOM', async () => {
  let attomCalls = 0;
  const stubDb = {
    async getCached(key: string) {
      return { ...sampleCard, source: 'cache' as const };
    },
    async putCached() {},
    async upsertAddress() { return 'addr-id'; },
    async upsertOwner() { return 'owner-id'; },
    async upsertOwnershipLink() {},
  };
  const stubAttom = {
    lookupOwner: async () => { attomCalls++; return sampleCard; },
  };
  const service = createOwnerService({ db: stubDb, attom: stubAttom });
  const result = await service.lookup('1 main st, madison, wi 53703');

  assertEquals(attomCalls, 0);
  assertEquals(result.source, 'cache');
});

Deno.test('owner-service: calls ATTOM on cache miss and persists result', async () => {
  let putCalled = false;
  let upsertAddrCalled = false;
  let upsertOwnerCalled = false;
  let upsertLinkCalled = false;
  const stubDb = {
    async getCached() { return null; },
    async putCached() { putCalled = true; },
    async upsertAddress() { upsertAddrCalled = true; return 'addr-id'; },
    async upsertOwner() { upsertOwnerCalled = true; return 'owner-id'; },
    async upsertOwnershipLink() { upsertLinkCalled = true; },
  };
  const stubAttom = { lookupOwner: async () => sampleCard };
  const service = createOwnerService({ db: stubDb, attom: stubAttom });
  const result = await service.lookup('1 main st, madison, wi 53703');

  assertEquals(result.source, 'attom');
  assertEquals(putCalled, true);
  assertEquals(upsertAddrCalled, true);
  assertEquals(upsertOwnerCalled, true);
  assertEquals(upsertLinkCalled, true);
});

Deno.test('owner-service: propagates not_found from ATTOM', async () => {
  const stubDb = {
    async getCached() { return null; },
    async putCached() {},
    async upsertAddress() { return 'a'; },
    async upsertOwner() { return 'o'; },
    async upsertOwnershipLink() {},
  };
  const stubAttom = {
    lookupOwner: async () => { throw new Error('not_found: no property'); },
  };
  const service = createOwnerService({ db: stubDb, attom: stubAttom });
  try {
    await service.lookup('nowhere');
    throw new Error('expected service.lookup to throw');
  } catch (e) {
    assertEquals((e as Error).message.startsWith('not_found'), true);
  }
});
```

- [ ] **Step 2: Run tests — verify they fail**

```bash
deno test --allow-env --allow-net supabase/functions/owner-lookup/owner-service.test.ts
```

Expected: FAIL — `createOwnerService` not defined.

- [ ] **Step 3: Implement `owner-service.ts`**

```typescript
// supabase/functions/owner-lookup/owner-service.ts
import type { OwnerCard } from './types.ts';

const CACHE_TTL_DAYS = 30;

export interface OwnerServiceDb {
  getCached(cacheKey: string): Promise<OwnerCard | null>;
  putCached(cacheKey: string, card: OwnerCard, expiresAt: Date): Promise<void>;
  upsertAddress(normalized: string, rawInput: string, card: OwnerCard): Promise<string>;
  upsertOwner(card: OwnerCard): Promise<string>;
  upsertOwnershipLink(addressId: string, ownerId: string, card: OwnerCard): Promise<void>;
}

export interface OwnerServiceAttom {
  lookupOwner(normalizedAddress: string): Promise<OwnerCard>;
}

interface Deps {
  db: OwnerServiceDb;
  attom: OwnerServiceAttom;
  now?: () => Date;
}

export function createOwnerService(deps: Deps) {
  const now = deps.now ?? (() => new Date());

  async function lookup(normalizedAddress: string): Promise<OwnerCard> {
    const cacheKey = `attom:${normalizedAddress}`;
    const cached = await deps.db.getCached(cacheKey);
    if (cached) {
      return { ...cached, source: 'cache' };
    }

    const card = await deps.attom.lookupOwner(normalizedAddress);

    const expiresAt = new Date(now().getTime() + CACHE_TTL_DAYS * 24 * 60 * 60 * 1000);
    await deps.db.putCached(cacheKey, card, expiresAt);
    const addressId = await deps.db.upsertAddress(normalizedAddress, normalizedAddress, card);
    const ownerId = await deps.db.upsertOwner(card);
    await deps.db.upsertOwnershipLink(addressId, ownerId, card);

    return card;
  }

  return { lookup };
}
```

- [ ] **Step 4: Run tests — verify pass**

```bash
deno test --allow-env --allow-net supabase/functions/owner-lookup/owner-service.test.ts
```

Expected: all 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/owner-lookup/owner-service.ts supabase/functions/owner-lookup/owner-service.test.ts
git commit -m "feat(edge): add cache-aware owner lookup service"
```

---

## Task 12: Edge function HTTP handler

**Files:**
- Create: `supabase/functions/owner-lookup/index.ts`
- Create: `supabase/functions/_shared/cors.ts`
- Create: `supabase/functions/owner-lookup/db.ts` (Postgres-backed impl of `OwnerServiceDb`)

- [ ] **Step 1: Write the shared CORS helper**

`supabase/functions/_shared/cors.ts`:
```typescript
export const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
};
```

- [ ] **Step 2: Write the Postgres-backed DB impl**

`supabase/functions/owner-lookup/db.ts`:
```typescript
import { createClient, SupabaseClient } from 'https://esm.sh/@supabase/supabase-js@2.45.0';
import type { OwnerCard } from './types.ts';
import type { OwnerServiceDb } from './owner-service.ts';

export function createPostgresDb(url: string, serviceRoleKey: string): OwnerServiceDb {
  const supabase: SupabaseClient = createClient(url, serviceRoleKey);

  return {
    async getCached(cacheKey: string) {
      const { data } = await supabase
        .from('cached_lookups')
        .select('response, expires_at')
        .eq('cache_key', cacheKey)
        .maybeSingle();
      if (!data) return null;
      if (new Date(data.expires_at) < new Date()) return null;
      return data.response as OwnerCard;
    },

    async putCached(cacheKey, card, expiresAt) {
      await supabase.from('cached_lookups').upsert({
        cache_key: cacheKey,
        response: card,
        fetched_at: new Date().toISOString(),
        expires_at: expiresAt.toISOString(),
      });
    },

    async upsertAddress(normalized, rawInput, card) {
      const { data, error } = await supabase
        .from('addresses')
        .upsert({
          normalized,
          raw_input: rawInput,
          line1: card.address.display.split(',')[0] ?? null,
          city: card.address.city,
          state: card.address.state,
          zip: card.address.zip,
        }, { onConflict: 'normalized' })
        .select('id')
        .single();
      if (error) throw new Error(`db_error: upsertAddress failed: ${error.message}`);
      return data.id;
    },

    async upsertOwner(card) {
      const { data, error } = await supabase
        .from('owners')
        .insert({
          name: card.owner.name,
          type: card.owner.type,
          source: 'attom',
          last_refreshed_at: card.fetchedAt,
        })
        .select('id')
        .single();
      if (error) throw new Error(`db_error: upsertOwner failed: ${error.message}`);
      return data.id;
    },

    async upsertOwnershipLink(addressId, ownerId, card) {
      const { error } = await supabase
        .from('ownership_links')
        .upsert({
          address_id: addressId,
          owner_id: ownerId,
          source: 'attom',
          last_sale_date: card.parcel.lastSaleDate,
          last_sale_price_cents: card.parcel.lastSalePriceCents,
          tax_status: card.parcel.taxStatus,
          property_type: card.parcel.propertyType,
          year_built: card.parcel.yearBuilt,
          sqft: card.parcel.sqft,
          assessed_value_cents: card.parcel.assessedValueCents,
          last_verified_at: card.fetchedAt,
        }, { onConflict: 'address_id,owner_id' });
      if (error) throw new Error(`db_error: upsertOwnershipLink failed: ${error.message}`);
    },
  };
}
```

- [ ] **Step 3: Write the HTTP handler**

`supabase/functions/owner-lookup/index.ts`:
```typescript
import { serve } from 'https://deno.land/std@0.208.0/http/server.ts';
import { normalizeAddress } from '../_shared/normalize.ts';
import { corsHeaders } from '../_shared/cors.ts';
import { createAttomClient } from './attom-client.ts';
import { createOwnerService } from './owner-service.ts';
import { createPostgresDb } from './db.ts';
import type { OwnerCardResult } from './types.ts';

serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: corsHeaders });
  }
  if (req.method !== 'POST') {
    return jsonResponse({ ok: false, error: { code: 'invalid_input', message: 'POST required' } }, 405);
  }

  let body: { address?: string };
  try {
    body = await req.json();
  } catch {
    return jsonResponse({ ok: false, error: { code: 'invalid_input', message: 'invalid JSON body' } }, 400);
  }

  const raw = (body.address ?? '').toString();
  if (raw.trim().length < 5) {
    return jsonResponse({ ok: false, error: { code: 'invalid_input', message: 'address too short' } }, 400);
  }

  const normalized = normalizeAddress(raw);
  const attom = createAttomClient({ apiKey: Deno.env.get('ATTOM_API_KEY') ?? '' });
  const db = createPostgresDb(
    Deno.env.get('SUPABASE_URL') ?? '',
    Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? '',
  );
  const service = createOwnerService({ db, attom });

  try {
    const card = await service.lookup(normalized);
    return jsonResponse({ ok: true, data: card }, 200);
  } catch (e) {
    const message = (e as Error).message;
    if (message.startsWith('not_found')) {
      return jsonResponse({ ok: false, error: { code: 'not_found', message } }, 404);
    }
    if (message.startsWith('rate_limited')) {
      return jsonResponse({ ok: false, error: { code: 'rate_limited', message } }, 429);
    }
    console.error('owner-lookup error', e);
    return jsonResponse({ ok: false, error: { code: 'upstream_error', message } }, 502);
  }
});

function jsonResponse(body: OwnerCardResult | { ok: false; error: { code: string; message: string } }, status: number) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { ...corsHeaders, 'content-type': 'application/json' },
  });
}
```

- [ ] **Step 4: Set ATTOM_API_KEY as a Supabase secret**

```bash
supabase secrets set ATTOM_API_KEY=<the key>
```

- [ ] **Step 5: Serve locally + smoke-test**

```bash
supabase functions serve owner-lookup --env-file .env.local
# in another terminal:
curl -X POST http://localhost:54321/functions/v1/owner-lookup \
  -H "content-type: application/json" \
  -H "apikey: $VITE_SUPABASE_ANON_KEY" \
  -d '{"address": "4 Mansion Hill Dr, Madison, WI 53703"}'
```

Expected: a JSON response with `{"ok": true, "data": {...}}` containing owner + parcel.

- [ ] **Step 6: Deploy**

```bash
supabase functions deploy owner-lookup
```

- [ ] **Step 7: Commit**

```bash
git add supabase/functions/owner-lookup/index.ts supabase/functions/owner-lookup/db.ts supabase/functions/_shared/cors.ts
git commit -m "feat(edge): add owner-lookup HTTP handler with CORS + error mapping"
```

---

## Task 13: Frontend API client

**Files:**
- Create: `src/lib/supabase.ts`
- Create: `src/lib/api.ts`

- [ ] **Step 1: Write `src/lib/supabase.ts`**

```typescript
import { createClient } from '@supabase/supabase-js';

const url = import.meta.env.VITE_SUPABASE_URL;
const anonKey = import.meta.env.VITE_SUPABASE_ANON_KEY;

if (!url || !anonKey) {
  throw new Error('Missing VITE_SUPABASE_URL or VITE_SUPABASE_ANON_KEY');
}

export const supabase = createClient(url, anonKey);
```

- [ ] **Step 2: Write `src/lib/api.ts`**

```typescript
import { supabase } from './supabase';
import type { OwnerCardResult } from '../types/owner-card';

export async function lookupOwner(address: string): Promise<OwnerCardResult> {
  const { data, error } = await supabase.functions.invoke<OwnerCardResult>(
    'owner-lookup',
    { body: { address } },
  );
  if (error) {
    return {
      ok: false,
      error: { code: 'upstream_error', message: error.message },
    };
  }
  if (!data) {
    return {
      ok: false,
      error: { code: 'upstream_error', message: 'empty response' },
    };
  }
  return data;
}
```

- [ ] **Step 3: Commit**

```bash
git add src/lib/supabase.ts src/lib/api.ts
git commit -m "feat(web): add supabase client + owner-lookup api wrapper"
```

---

## Task 14: Legal disclaimer component

**Files:**
- Create: `src/components/LegalDisclaimer.tsx`
- Create: `src/__tests__/LegalDisclaimer.test.tsx`

- [ ] **Step 1: Write the test**

`src/__tests__/LegalDisclaimer.test.tsx`:
```typescript
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { LegalDisclaimer } from '../components/LegalDisclaimer';

describe('LegalDisclaimer', () => {
  it('renders the exact FCRA-exclusion language from the spec', () => {
    render(<LegalDisclaimer />);
    expect(screen.getByText(/not a consumer report under the Fair Credit Reporting Act/i)).toBeInTheDocument();
    expect(screen.getByText(/may not be used for tenant screening, employment, credit, insurance/i)).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run test — verify fail**

```bash
npx vitest run src/__tests__/LegalDisclaimer.test.tsx
```

Expected: FAIL.

- [ ] **Step 3: Implement the component**

```typescript
// src/components/LegalDisclaimer.tsx
export function LegalDisclaimer() {
  return (
    <div className="text-xs text-gray-500 border-t pt-4 mt-8 max-w-2xl">
      <p>
        <strong>LandlordLens is a personal-safety information service.</strong>{' '}
        This is not a consumer report under the Fair Credit Reporting Act (FCRA).
        This information may not be used for tenant screening, employment, credit,
        insurance, or any other FCRA-regulated purpose.
      </p>
    </div>
  );
}
```

- [ ] **Step 4: Run test — verify pass**

```bash
npx vitest run src/__tests__/LegalDisclaimer.test.tsx
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/components/LegalDisclaimer.tsx src/__tests__/LegalDisclaimer.test.tsx
git commit -m "feat(web): add LegalDisclaimer with locked non-FCRA copy"
```

---

## Task 15: AddressInput component

**Files:**
- Create: `src/components/AddressInput.tsx`
- Create: `src/__tests__/AddressInput.test.tsx`

- [ ] **Step 1: Write the tests**

`src/__tests__/AddressInput.test.tsx`:
```typescript
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AddressInput } from '../components/AddressInput';

describe('AddressInput', () => {
  it('calls onSubmit with the entered address', async () => {
    const onSubmit = vi.fn();
    render(<AddressInput onSubmit={onSubmit} loading={false} />);
    const input = screen.getByLabelText(/property address/i);
    await userEvent.type(input, '4 Mansion Hill Dr, Madison, WI');
    await userEvent.click(screen.getByRole('button', { name: /check/i }));
    expect(onSubmit).toHaveBeenCalledWith('4 Mansion Hill Dr, Madison, WI');
  });

  it('disables the button while loading', () => {
    render(<AddressInput onSubmit={() => {}} loading={true} />);
    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('does not submit when input is empty', async () => {
    const onSubmit = vi.fn();
    render(<AddressInput onSubmit={onSubmit} loading={false} />);
    await userEvent.click(screen.getByRole('button'));
    expect(onSubmit).not.toHaveBeenCalled();
  });
});
```

- [ ] **Step 2: Run tests — verify fail**

```bash
npx vitest run src/__tests__/AddressInput.test.tsx
```

Expected: FAIL.

- [ ] **Step 3: Implement the component**

```typescript
// src/components/AddressInput.tsx
import { FormEvent, useState } from 'react';

interface Props {
  onSubmit: (address: string) => void;
  loading: boolean;
}

export function AddressInput({ onSubmit, loading }: Props) {
  const [value, setValue] = useState('');

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const trimmed = value.trim();
    if (trimmed.length < 5) return;
    onSubmit(trimmed);
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col sm:flex-row gap-2 w-full max-w-2xl">
      <label htmlFor="address" className="sr-only">Property address</label>
      <input
        id="address"
        type="text"
        placeholder="Enter property address (e.g. 4 Mansion Hill Dr, Madison, WI)"
        value={value}
        onChange={(e) => setValue(e.target.value)}
        className="flex-1 border border-gray-300 rounded px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-blue-500"
        autoComplete="street-address"
      />
      <button
        type="submit"
        disabled={loading}
        className="bg-blue-600 text-white px-6 py-3 rounded font-medium disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {loading ? 'Checking…' : 'Check landlord'}
      </button>
    </form>
  );
}
```

- [ ] **Step 4: Run tests — verify pass**

```bash
npx vitest run src/__tests__/AddressInput.test.tsx
```

Expected: all 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/components/AddressInput.tsx src/__tests__/AddressInput.test.tsx
git commit -m "feat(web): add AddressInput form with loading + empty-input handling"
```

---

## Task 16: OwnerCardDisplay component

**Files:**
- Create: `src/components/OwnerCardDisplay.tsx`
- Create: `src/__tests__/OwnerCardDisplay.test.tsx`

- [ ] **Step 1: Write the tests**

`src/__tests__/OwnerCardDisplay.test.tsx`:
```typescript
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { OwnerCardDisplay } from '../components/OwnerCardDisplay';
import type { OwnerCard } from '../types/owner-card';

const baseCard: OwnerCard = {
  address: {
    normalized: '4 mansion hill dr, madison, wi 53703',
    display: '4 Mansion Hill Dr, Madison, WI 53703',
    city: 'Madison', state: 'WI', zip: '53703',
  },
  owner: { name: 'JOHN DOE', type: 'person' },
  parcel: {
    lastSaleDate: '2020-06-15',
    lastSalePriceCents: 35000000,
    taxStatus: 'current',
    propertyType: 'SFR',
    yearBuilt: 1998,
    sqft: 1850,
    assessedValueCents: 28000000,
  },
  source: 'attom',
  fetchedAt: '2026-04-20T12:00:00Z',
};

describe('OwnerCardDisplay', () => {
  it('renders the owner name and type', () => {
    render(<OwnerCardDisplay card={baseCard} />);
    expect(screen.getByText('JOHN DOE')).toBeInTheDocument();
    expect(screen.getByText(/individual/i)).toBeInTheDocument();
  });

  it('renders LLC label for LLC owners', () => {
    const llcCard = { ...baseCard, owner: { name: 'ACME PROPERTIES LLC', type: 'llc' as const } };
    render(<OwnerCardDisplay card={llcCard} />);
    expect(screen.getByText(/LLC \/ business entity/i)).toBeInTheDocument();
  });

  it('renders the last sale price formatted as currency', () => {
    render(<OwnerCardDisplay card={baseCard} />);
    expect(screen.getByText('$350,000')).toBeInTheDocument();
  });

  it('renders "Unknown" for missing parcel fields', () => {
    const sparse = { ...baseCard, parcel: { ...baseCard.parcel, yearBuilt: null, sqft: null } };
    render(<OwnerCardDisplay card={sparse} />);
    expect(screen.getAllByText(/unknown/i).length).toBeGreaterThanOrEqual(1);
  });

  it('renders a "delinquent taxes" warning when tax status is delinquent', () => {
    const delinquent = { ...baseCard, parcel: { ...baseCard.parcel, taxStatus: 'delinquent' as const } };
    render(<OwnerCardDisplay card={delinquent} />);
    expect(screen.getByText(/property taxes are delinquent/i)).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run tests — verify fail**

```bash
npx vitest run src/__tests__/OwnerCardDisplay.test.tsx
```

Expected: FAIL.

- [ ] **Step 3: Implement the component**

```typescript
// src/components/OwnerCardDisplay.tsx
import type { OwnerCard, OwnerType } from '../types/owner-card';

interface Props {
  card: OwnerCard;
}

const OWNER_TYPE_LABEL: Record<OwnerType, string> = {
  person: 'Individual',
  llc: 'LLC / business entity',
  corp: 'Corporation',
  trust: 'Trust',
  other: 'Other',
};

function formatCents(cents: number | null): string {
  if (cents === null) return 'Unknown';
  return `$${(cents / 100).toLocaleString('en-US', { maximumFractionDigits: 0 })}`;
}

function formatDate(iso: string | null): string {
  if (!iso) return 'Unknown';
  return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatNumber(n: number | null): string {
  if (n === null) return 'Unknown';
  return n.toLocaleString('en-US');
}

export function OwnerCardDisplay({ card }: Props) {
  const { address, owner, parcel } = card;

  return (
    <div className="bg-white border border-gray-200 rounded-lg shadow-sm p-6 max-w-2xl">
      <h2 className="text-sm uppercase tracking-wide text-gray-500 mb-1">Property</h2>
      <p className="text-lg font-medium mb-6">{address.display}</p>

      <div className="border-t pt-4 mb-6">
        <h3 className="text-sm uppercase tracking-wide text-gray-500 mb-2">Owner of record</h3>
        <p className="text-2xl font-semibold">{owner.name}</p>
        <p className="text-sm text-gray-600">{OWNER_TYPE_LABEL[owner.type]}</p>
      </div>

      {parcel.taxStatus === 'delinquent' && (
        <div className="bg-red-50 border border-red-200 rounded p-3 mb-4 text-sm text-red-800">
          ⚠️ Property taxes are delinquent according to county records.
        </div>
      )}

      <div className="grid grid-cols-2 gap-4 text-sm">
        <div>
          <p className="text-gray-500">Last sale</p>
          <p className="font-medium">{formatDate(parcel.lastSaleDate)}</p>
        </div>
        <div>
          <p className="text-gray-500">Sale price</p>
          <p className="font-medium">{formatCents(parcel.lastSalePriceCents)}</p>
        </div>
        <div>
          <p className="text-gray-500">Year built</p>
          <p className="font-medium">{formatNumber(parcel.yearBuilt)}</p>
        </div>
        <div>
          <p className="text-gray-500">Square footage</p>
          <p className="font-medium">{parcel.sqft === null ? 'Unknown' : `${formatNumber(parcel.sqft)} sqft`}</p>
        </div>
        <div>
          <p className="text-gray-500">Assessed value</p>
          <p className="font-medium">{formatCents(parcel.assessedValueCents)}</p>
        </div>
        <div>
          <p className="text-gray-500">Property type</p>
          <p className="font-medium">{parcel.propertyType ?? 'Unknown'}</p>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Run tests — verify pass**

```bash
npx vitest run src/__tests__/OwnerCardDisplay.test.tsx
```

Expected: all 5 tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/components/OwnerCardDisplay.tsx src/__tests__/OwnerCardDisplay.test.tsx
git commit -m "feat(web): add OwnerCardDisplay with owner + parcel + tax-delinquent warning"
```

---

## Task 17: Loading state + error state components

**Files:**
- Create: `src/components/LoadingState.tsx`
- Create: `src/components/ErrorState.tsx`

- [ ] **Step 1: Implement LoadingState**

```typescript
// src/components/LoadingState.tsx
export function LoadingState() {
  return (
    <div className="bg-white border border-gray-200 rounded-lg p-6 max-w-2xl animate-pulse">
      <div className="h-4 bg-gray-200 rounded w-1/3 mb-3"></div>
      <div className="h-8 bg-gray-200 rounded w-2/3 mb-6"></div>
      <div className="h-4 bg-gray-200 rounded w-1/4 mb-2"></div>
      <div className="h-6 bg-gray-200 rounded w-1/2"></div>
    </div>
  );
}
```

- [ ] **Step 2: Implement ErrorState**

```typescript
// src/components/ErrorState.tsx
import type { OwnerCardError } from '../types/owner-card';

interface Props {
  error: OwnerCardError;
  onRetry: () => void;
}

const ERROR_MESSAGES: Record<OwnerCardError['code'], string> = {
  not_found: "We couldn't find that property in county records. Check the address for typos, or try the mailing address exactly as it appears on a lease or online listing.",
  rate_limited: "We're hitting our lookup limit for the moment. Please try again in a minute.",
  upstream_error: "Our data provider didn't respond properly. Try again in a moment.",
  invalid_input: "That address doesn't look complete. Include street, city, and state.",
};

export function ErrorState({ error, onRetry }: Props) {
  return (
    <div className="bg-amber-50 border border-amber-200 rounded-lg p-6 max-w-2xl">
      <h2 className="font-semibold mb-2">We couldn't complete your search</h2>
      <p className="text-sm text-gray-700 mb-4">{ERROR_MESSAGES[error.code]}</p>
      <button
        onClick={onRetry}
        className="bg-amber-700 text-white px-4 py-2 rounded text-sm"
      >
        Try again
      </button>
    </div>
  );
}
```

- [ ] **Step 3: Commit**

```bash
git add src/components/LoadingState.tsx src/components/ErrorState.tsx
git commit -m "feat(web): add LoadingState and ErrorState components"
```

---

## Task 18: Home page

**Files:**
- Create: `src/pages/Home.tsx`

- [ ] **Step 1: Implement the page**

```typescript
// src/pages/Home.tsx
import { useState } from 'react';
import { AddressInput } from '../components/AddressInput';
import { OwnerCardDisplay } from '../components/OwnerCardDisplay';
import { LoadingState } from '../components/LoadingState';
import { ErrorState } from '../components/ErrorState';
import { LegalDisclaimer } from '../components/LegalDisclaimer';
import { lookupOwner } from '../lib/api';
import type { OwnerCard, OwnerCardError } from '../types/owner-card';

type ViewState =
  | { kind: 'idle' }
  | { kind: 'loading' }
  | { kind: 'result'; card: OwnerCard }
  | { kind: 'error'; error: OwnerCardError };

export function Home() {
  const [state, setState] = useState<ViewState>({ kind: 'idle' });

  async function handleSubmit(address: string) {
    setState({ kind: 'loading' });
    const result = await lookupOwner(address);
    if (result.ok) {
      setState({ kind: 'result', card: result.data });
    } else {
      setState({ kind: 'error', error: result.error });
    }
  }

  function handleRetry() {
    setState({ kind: 'idle' });
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-3xl mx-auto px-4 py-12">
        <header className="mb-10">
          <h1 className="text-3xl font-bold mb-2">Who actually owns this rental?</h1>
          <p className="text-gray-600">
            Enter a property address to see the owner of record — straight from county records.
            Free. No signup. Stop getting scammed on Craigslist.
          </p>
        </header>

        <div className="mb-8">
          <AddressInput onSubmit={handleSubmit} loading={state.kind === 'loading'} />
        </div>

        {state.kind === 'loading' && <LoadingState />}
        {state.kind === 'result' && <OwnerCardDisplay card={state.card} />}
        {state.kind === 'error' && <ErrorState error={state.error} onRetry={handleRetry} />}

        <LegalDisclaimer />
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Wire into App.tsx**

Replace contents of `src/App.tsx`:
```typescript
import { Home } from './pages/Home';

export default function App() {
  return <Home />;
}
```

- [ ] **Step 3: Run dev server and manually test**

```bash
npm run dev
```

In a browser, open `http://localhost:5173`. Enter `4 Mansion Hill Dr, Madison, WI 53703`. Verify: loading state appears, then owner card displays, then disclaimer is visible below.

- [ ] **Step 4: Commit**

```bash
git add src/pages/Home.tsx src/App.tsx
git commit -m "feat(web): add home page with address search + result/error/loading states"
```

---

## Task 19: End-to-end smoke test

**Files:**
- No new files; manual verification only.

- [ ] **Step 1: Verify all tests pass**

```bash
npx vitest run
```

Expected: all frontend tests pass.

```bash
deno test --allow-env --allow-net supabase/functions/
```

Expected: all edge-function tests pass.

- [ ] **Step 2: Smoke-test the full pipeline against live ATTOM**

With `supabase functions serve` running and `npm run dev` running:

1. Open `http://localhost:5173`.
2. Enter three test addresses (one per launch metro):
   - Madison: `4 Mansion Hill Dr, Madison, WI 53703`
   - Chicago: `875 N Michigan Ave, Chicago, IL 60611`
   - Minneapolis: `100 Portland Ave, Minneapolis, MN 55401`
3. For each: verify the Owner Card renders with a plausible owner name and parcel data.
4. Enter a deliberately bad address (`0 Fake St, Nowhere, XX`). Verify the `not_found` error state displays.

- [ ] **Step 3: Verify caching**

1. Enter the Madison address again. Note the response time — should be noticeably faster.
2. Query the DB:

```bash
supabase db remote status
# then in the Supabase SQL editor or via psql:
# SELECT cache_key, fetched_at, expires_at FROM cached_lookups;
```

Expected: one row per distinct address, `expires_at` ~30 days out.

- [ ] **Step 4: Commit notes (if any)**

No commit needed unless fixes were made.

---

## Task 20: Deploy frontend to Vercel

**Files:**
- Modify: `README.md` (deployment instructions)

- [ ] **Step 1: Push repo to GitHub**

```bash
gh repo create landlordlens --private --source=. --push
```

- [ ] **Step 2: Import to Vercel**

Via Vercel dashboard: import the `landlordlens` repo. Framework preset: Vite. Add environment variables:
- `VITE_SUPABASE_URL`
- `VITE_SUPABASE_ANON_KEY`

- [ ] **Step 3: Deploy and verify**

Let Vercel build. Open the Vercel URL. Enter a Madison address. Verify the full flow works in production.

- [ ] **Step 4: Update README**

Append to `README.md`:

```markdown
## Live

- Production: https://landlordlens.vercel.app (or final domain once set)
- Edge function: https://<project-ref>.supabase.co/functions/v1/owner-lookup

## Local development

1. `npm install`
2. Copy `.env.example` → `.env.local`, fill in keys
3. `supabase start`
4. `supabase functions serve --env-file .env.local`
5. `npm run dev`
```

- [ ] **Step 5: Commit + tag**

```bash
git add README.md
git commit -m "docs: deployment + local dev instructions"
git tag v0.1.0-m1
git push origin main --tags
```

---

## Milestone exit criteria

M1 is complete when:

1. All tests (frontend + edge function) pass.
2. The Vercel-deployed site accepts a property address and returns an Owner Card for properties in the three launch metros plus arbitrary US addresses.
3. Repeated lookups of the same address return `source: 'cache'` and respond in <500ms.
4. The non-FCRA legal disclaimer appears on every page.
5. `not_found`, `rate_limited`, `invalid_input`, and `upstream_error` all display appropriate UI.

On all 5 ✅, tag `v0.1.0-m1` and hand off to M2 planning (email-gated Free Report adding criminal + SOR).

---

## Deferred to M2+

- Google Places autocomplete for address input
- Auth (Supabase magic-link)
- Criminal + sex-offender registry
- Eviction + civil litigation (court scrapers)
- Code violations
- Stripe $12 Deep Report
- PDF export
- Landlord dispute flow
- Rate limiting on the public endpoint
- Sentry / error monitoring
- Spanish UI
