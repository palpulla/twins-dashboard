# Open Estimates Follow-Up Tracker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A daily Supabase job that rolls up open Housecall Pro estimates (created on/after 2026-01-01) per customer into a Google Sheet for the CSR to follow up on, appending new customers and moving resolved ones to a Closed tab without ever overwriting the CSR's columns.

**Architecture:** A new Deno edge function (`sync-estimate-tracker`) on the jwrpj Supabase project reads open estimates from the `jobs` table (kept fresh by the existing `sync-hcp-estimates` sync), aggregates them per customer with a pure, unit-tested module, then reconciles that desired state against the Google Sheet via the Sheets API using a Google service account. A `pg_cron` job invokes it nightly after `auto-sync-jobs`.

**Tech Stack:** Supabase edge functions (Deno), Postgres + `pg_cron` + `net.http_post`, Google Sheets API v4, service-account JWT auth via Web Crypto (RS256).

**Spec:** `docs/superpowers/specs/2026-06-25-open-estimates-tracker-design.md`

**Target sheet:** `1OK1BsJ7MvPa7ZR6b724duMxRomHeYMwG1oiMjdSL0nE`

---

## Data facts (verified against live jwrpj data 2026-06-25)

- Open estimate = `jobs` row where `job_type='Estimate'` AND `estimate_status='open'`.
- Scope: `(hcp_data->>'created_at')::timestamptz >= '2026-01-01'`. Currently 287 estimates across 225 customers (1,813 open all-time — the cutoff is essential to avoid stale 2021 rows).
- Customer: `hcp_data->'customer'` has `id`, `first_name`, `last_name`, `mobile_number`, `home_number`, `work_number`, `email`.
- **The estimate's own `total_amount` is NULL.** Dollar values live in `hcp_data->'options'` (array), each option has `total_amount` **in cents** (e.g. `757400` = $7,574.00).
- Options are good/better/best **alternatives** (never summed). 142 estimates have 1 option; 145 have 2–8.
- Per Daniel: show each estimate as a **range** (min–max option, low–high). Customer "Total Quoted" = sum of each estimate's **high** option.
- `estimate_number` lives at `hcp_data->>'estimate_number'`.
- Tech: `jobs.tech_id` → `technicians.name`.

## Column layout (single source of truth — referenced by every task)

Follow-Up tab and Closed tab share columns A–N. **Auto** columns are sync-managed; **CSR** columns are never written on update.

| Col | Header | Kind | Source |
|---|---|---|---|
| A | Customer | Auto | `first_name + ' ' + last_name` |
| B | Phone | Auto | `mobile_number` ‖ `home_number` ‖ `work_number`, formatted `(608) 555-1234` |
| C | # Open Est | Auto | count of customer's open estimates |
| D | Total Quoted | Auto | sum of each estimate's high option, e.g. `$12,450` |
| E | Estimate Details | Auto | `EST-2399 · $7,574 · 4/29` (single) or `EST-2386 · $30,000–$59,000 · 4/28`, joined by `; ` |
| F | Assigned Tech | Auto | distinct tech name(s), joined by `, ` |
| G | Oldest Est Date | Auto | min `created_at`, formatted `M/D/YYYY` |
| H | Follow-Up Status | CSR | dropdown: New · Attempted · Reached · Callback set · No answer · Do not contact |
| I | Booked? | CSR | dropdown: No · Yes · Partial |
| J | Last Follow-Up | CSR | date |
| K | Next Follow-Up | CSR | date |
| L | Notes | CSR | free text |
| M | Remove | CSR | dropdown: — · Remove |
| N | HCP Customer ID | Auto (hidden) | `hcp_data->'customer'->>'id'` — the upsert key |

Closed tab adds two columns after N: **O = Outcome** (Booked · Declined · Mixed · Removed by CSR), **P = Date Closed** (`M/D/YYYY`).

---

## Task 1: Provision Google service account and store secrets

**Files:** none in repo (infra setup). Records the SA email for Task 5's share step.

- [ ] **Step 1: Create the service account and key**

If `gcloud` is available and authenticated to the existing Twins Google Cloud project (the one already used for Google Ads / Maps), run:

```bash
# Find existing project
gcloud projects list

# Create the service account (replace PROJECT_ID)
gcloud iam service-accounts create estimate-tracker-sheets \
  --display-name="Estimate Tracker Sheets Writer" \
  --project=PROJECT_ID

# Enable Sheets API
gcloud services enable sheets.googleapis.com --project=PROJECT_ID

# Create + download a JSON key
gcloud iam service-accounts keys create /tmp/estimate-tracker-sa.json \
  --iam-account=estimate-tracker-sheets@PROJECT_ID.iam.gserviceaccount.com
```

If `gcloud` is not available, do this once in the Google Cloud Console: APIs & Services → Enable "Google Sheets API"; IAM → Service Accounts → Create (`estimate-tracker-sheets`); Keys → Add key → JSON → download. No IAM roles are needed (access is granted per-sheet by sharing, Task 5).

- [ ] **Step 2: Store the key in Supabase secrets**

The function needs two secrets. The JSON key is stored whole.

```bash
cd /Users/daniel/twins-dashboard/twins-dash
supabase secrets set GOOGLE_SHEETS_SA_KEY="$(cat /tmp/estimate-tracker-sa.json)" --project-ref jwrpjuqaynownxaoeayi
# Convenience: the client_email from the same JSON, for the share step + logging
supabase secrets set GOOGLE_SHEETS_SA_EMAIL="$(jq -r .client_email /tmp/estimate-tracker-sa.json)" --project-ref jwrpjuqaynownxaoeayi
supabase secrets set ESTIMATE_TRACKER_SHEET_ID="1OK1BsJ7MvPa7ZR6b724duMxRomHeYMwG1oiMjdSL0nE" --project-ref jwrpjuqaynownxaoeayi
```

- [ ] **Step 3: Delete the local key file**

```bash
rm -f /tmp/estimate-tracker-sa.json
```

- [ ] **Step 4: Record the SA email**

Print and note the `client_email` (e.g. `estimate-tracker-sheets@PROJECT_ID.iam.gserviceaccount.com`). Daniel shares the sheet with it in Task 5.

No commit (no repo files changed).

---

## Task 2: Google Sheets auth + API helper

A small, dependency-free helper that authenticates as the service account (RS256 JWT → OAuth token) and exposes the Sheets calls we need. Pure-ish: the token mint is unit-testable in isolation.

**Files:**
- Create: `twins-dash/supabase/functions/_shared/google/sheets.ts`
- Test: `twins-dash/supabase/functions/_shared/google/sheets.test.ts`

- [ ] **Step 1: Write the failing test for the JWT claim builder**

```typescript
// sheets.test.ts
import { assertEquals } from 'https://deno.land/std@0.224.0/assert/mod.ts';
import { buildJwtClaims } from './sheets.ts';

Deno.test('buildJwtClaims targets the Sheets scope and token endpoint', () => {
  const claims = buildJwtClaims('sa@proj.iam.gserviceaccount.com', 1_700_000_000);
  assertEquals(claims.iss, 'sa@proj.iam.gserviceaccount.com');
  assertEquals(claims.scope, 'https://www.googleapis.com/auth/spreadsheets');
  assertEquals(claims.aud, 'https://oauth2.googleapis.com/token');
  assertEquals(claims.iat, 1_700_000_000);
  assertEquals(claims.exp, 1_700_000_000 + 3600);
});
```

- [ ] **Step 2: Run it, verify it fails**

Run: `cd twins-dash && deno test supabase/functions/_shared/google/sheets.test.ts`
Expected: FAIL — `buildJwtClaims` not exported.

- [ ] **Step 3: Implement the helper**

```typescript
// sheets.ts
// Minimal Google Sheets API v4 client for Deno edge functions.
// Auth: service-account RS256 JWT -> OAuth2 access token (no external deps).

export interface JwtClaims {
  iss: string; scope: string; aud: string; iat: number; exp: number;
}

export function buildJwtClaims(clientEmail: string, nowSeconds: number): JwtClaims {
  return {
    iss: clientEmail,
    scope: 'https://www.googleapis.com/auth/spreadsheets',
    aud: 'https://oauth2.googleapis.com/token',
    iat: nowSeconds,
    exp: nowSeconds + 3600,
  };
}

function b64url(bytes: Uint8Array): string {
  let s = btoa(String.fromCharCode(...bytes));
  return s.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
function b64urlStr(s: string): string { return b64url(new TextEncoder().encode(s)); }

function pemToPkcs8(pem: string): ArrayBuffer {
  const body = pem.replace(/-----BEGIN PRIVATE KEY-----/, '')
    .replace(/-----END PRIVATE KEY-----/, '').replace(/\s+/g, '');
  const raw = atob(body);
  const buf = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) buf[i] = raw.charCodeAt(i);
  return buf.buffer;
}

async function mintAccessToken(saKeyJson: string): Promise<string> {
  const key = JSON.parse(saKeyJson) as { client_email: string; private_key: string };
  const now = Math.floor(Date.now() / 1000);
  const header = { alg: 'RS256', typ: 'JWT' };
  const claims = buildJwtClaims(key.client_email, now);
  const unsigned = `${b64urlStr(JSON.stringify(header))}.${b64urlStr(JSON.stringify(claims))}`;
  const cryptoKey = await crypto.subtle.importKey(
    'pkcs8', pemToPkcs8(key.private_key),
    { name: 'RSASSA-PKCS1-v1_5', hash: 'SHA-256' }, false, ['sign']);
  const sig = new Uint8Array(await crypto.subtle.sign(
    'RSASSA-PKCS1-v1_5', cryptoKey, new TextEncoder().encode(unsigned)));
  const assertion = `${unsigned}.${b64url(sig)}`;

  const res = await fetch('https://oauth2.googleapis.com/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      grant_type: 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      assertion,
    }),
  });
  if (!res.ok) throw new Error(`Token mint failed: ${res.status} ${await res.text()}`);
  return (await res.json()).access_token as string;
}

export class SheetsClient {
  private token: string | null = null;
  constructor(private saKeyJson: string, private spreadsheetId: string) {}

  private async auth(): Promise<string> {
    if (!this.token) this.token = await mintAccessToken(this.saKeyJson);
    return this.token;
  }
  private async api(path: string, init?: RequestInit): Promise<any> {
    const token = await this.auth();
    const res = await fetch(
      `https://sheets.googleapis.com/v4/spreadsheets/${this.spreadsheetId}${path}`,
      { ...init, headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', ...(init?.headers || {}) } });
    if (!res.ok) throw new Error(`Sheets API ${path} -> ${res.status} ${await res.text()}`);
    return res.json();
  }

  getSpreadsheet(): Promise<any> { return this.api(''); }
  getValues(range: string): Promise<any> {
    return this.api(`/values/${encodeURIComponent(range)}`);
  }
  updateValues(range: string, values: any[][]): Promise<any> {
    return this.api(`/values/${encodeURIComponent(range)}?valueInputOption=USER_ENTERED`,
      { method: 'PUT', body: JSON.stringify({ values }) });
  }
  appendValues(range: string, values: any[][]): Promise<any> {
    return this.api(`/values/${encodeURIComponent(range)}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS`,
      { method: 'POST', body: JSON.stringify({ values }) });
  }
  batchUpdate(requests: any[]): Promise<any> {
    return this.api(':batchUpdate', { method: 'POST', body: JSON.stringify({ requests }) });
  }
}
```

- [ ] **Step 4: Run the test, verify it passes**

Run: `cd twins-dash && deno test supabase/functions/_shared/google/sheets.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/google/sheets.ts supabase/functions/_shared/google/sheets.test.ts
git commit -m "feat(estimates): Google Sheets service-account client for edge functions"
```

---

## Task 3: Per-customer aggregation (pure, fully unit-tested)

The heart of the feature. Pure function: raw open-estimate rows in, per-customer tracker rows out. No I/O, so it gets thorough TDD.

**Files:**
- Create: `twins-dash/supabase/functions/_shared/estimate-tracker/aggregate.ts`
- Test: `twins-dash/supabase/functions/_shared/estimate-tracker/aggregate.test.ts`

- [ ] **Step 1: Write failing tests**

```typescript
// aggregate.test.ts
import { assertEquals } from 'https://deno.land/std@0.224.0/assert/mod.ts';
import { aggregateByCustomer, formatPhone, estimateDetail, type EstimateRow } from './aggregate.ts';

const row = (over: Partial<EstimateRow> & { custId: string }): EstimateRow => ({
  customer: { id: over.custId, first_name: 'Jane', last_name: 'Doe',
    mobile_number: '6085551234', home_number: null, work_number: null },
  estimate_number: '2399',
  created_at: '2026-04-29T18:53:59Z',
  options: [{ total_amount: 757400 }],
  tech_name: 'Charles',
  ...over,
});

Deno.test('formatPhone prefers mobile then home then work, formats US', () => {
  assertEquals(formatPhone({ mobile_number: '6085551234', home_number: null, work_number: null }), '(608) 555-1234');
  assertEquals(formatPhone({ mobile_number: null, home_number: '6082038635', work_number: null }), '(608) 203-8635');
  assertEquals(formatPhone({ mobile_number: null, home_number: null, work_number: null }), '');
});

Deno.test('estimateDetail shows single value for one option', () => {
  assertEquals(estimateDetail('2399', [{ total_amount: 757400 }], '2026-04-29T18:53:59Z'),
    'EST-2399 · $7,574 · 4/29');
});

Deno.test('estimateDetail shows a low-high range for multiple options', () => {
  assertEquals(estimateDetail('2386', [{ total_amount: 5900000 }, { total_amount: 3000000 }], '2026-04-28T16:35:34Z'),
    'EST-2386 · $30,000–$59,000 · 4/28');
});

Deno.test('one customer with two estimates becomes one row; quoted = sum of highs', () => {
  const out = aggregateByCustomer([
    row({ custId: 'cus_1', estimate_number: '2399', options: [{ total_amount: 757400 }] }),
    row({ custId: 'cus_1', estimate_number: '2400', created_at: '2026-05-02T10:00:00Z',
      options: [{ total_amount: 1000000 }, { total_amount: 500000 }], tech_name: 'Marcus' }),
  ]);
  assertEquals(out.length, 1);
  assertEquals(out[0].customerId, 'cus_1');
  assertEquals(out[0].openCount, 2);
  assertEquals(out[0].totalQuoted, '$17,574');           // 7574 + 10000 highs
  assertEquals(out[0].assignedTech, 'Charles, Marcus');  // distinct, stable order
  assertEquals(out[0].oldestDate, '4/29/2026');
  assertEquals(out[0].details, 'EST-2399 · $7,574 · 4/29; EST-2400 · $5,000–$10,000 · 5/2');
});

Deno.test('options with null total_amount are ignored; no-option estimate shows no dollars', () => {
  assertEquals(estimateDetail('2401', [{ total_amount: null }], '2026-05-03T10:00:00Z'),
    'EST-2401 · 5/3');
});
```

- [ ] **Step 2: Run, verify failure**

Run: `cd twins-dash && deno test supabase/functions/_shared/estimate-tracker/aggregate.test.ts`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement**

```typescript
// aggregate.ts
export interface EstimateOption { total_amount: number | null; }
export interface EstimateCustomer {
  id: string; first_name?: string | null; last_name?: string | null;
  mobile_number?: string | null; home_number?: string | null; work_number?: string | null;
}
export interface EstimateRow {
  customer: EstimateCustomer;
  estimate_number: string | null;
  created_at: string;            // ISO
  options: EstimateOption[];
  tech_name: string | null;
}
export interface TrackerRow {
  customerId: string; customer: string; phone: string;
  openCount: number; totalQuoted: string; details: string;
  assignedTech: string; oldestDate: string;
}

export function formatPhone(c: Pick<EstimateCustomer, 'mobile_number' | 'home_number' | 'work_number'>): string {
  const raw = (c.mobile_number || c.home_number || c.work_number || '').replace(/\D/g, '');
  if (raw.length !== 10) return raw ? raw : '';
  return `(${raw.slice(0, 3)}) ${raw.slice(3, 6)}-${raw.slice(6)}`;
}

function usd(dollars: number): string {
  return `$${Math.round(dollars).toLocaleString('en-US')}`;
}
function mD(iso: string): string {
  const d = new Date(iso);
  return `${d.getUTCMonth() + 1}/${d.getUTCDate()}`;
}
function mDY(iso: string): string {
  const d = new Date(iso);
  return `${d.getUTCMonth() + 1}/${d.getUTCDate()}/${d.getUTCFullYear()}`;
}

function optionDollars(options: EstimateOption[]): number[] {
  return options.map(o => o.total_amount).filter((n): n is number => typeof n === 'number' && n > 0).map(c => c / 100);
}

export function estimateDetail(estimateNumber: string | null, options: EstimateOption[], createdAt: string): string {
  const num = estimateNumber ? `EST-${estimateNumber}` : 'EST-?';
  const vals = optionDollars(options);
  const date = mD(createdAt);
  if (vals.length === 0) return `${num} · ${date}`;
  const lo = Math.min(...vals), hi = Math.max(...vals);
  const money = lo === hi ? usd(hi) : `${usd(lo)}–${usd(hi)}`;
  return `${num} · ${money} · ${date}`;
}

export function aggregateByCustomer(rows: EstimateRow[]): TrackerRow[] {
  const byCustomer = new Map<string, EstimateRow[]>();
  for (const r of rows) {
    const arr = byCustomer.get(r.customer.id) ?? [];
    arr.push(r);
    byCustomer.set(r.customer.id, arr);
  }
  const out: TrackerRow[] = [];
  for (const [custId, ests] of byCustomer) {
    // oldest first, for stable detail ordering + oldest date
    ests.sort((a, b) => a.created_at.localeCompare(b.created_at));
    const c = ests[0].customer;
    const totalHigh = ests.reduce((sum, e) => {
      const vals = optionDollars(e.options);
      return sum + (vals.length ? Math.max(...vals) : 0);
    }, 0);
    const techs: string[] = [];
    for (const e of ests) if (e.tech_name && !techs.includes(e.tech_name)) techs.push(e.tech_name);
    out.push({
      customerId: custId,
      customer: `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim(),
      phone: formatPhone(c),
      openCount: ests.length,
      totalQuoted: totalHigh > 0 ? usd(totalHigh) : '',
      details: ests.map(e => estimateDetail(e.estimate_number, e.options, e.created_at)).join('; '),
      assignedTech: techs.join(', '),
      oldestDate: mDY(ests[0].created_at),
    });
  }
  // newest-customer-first ordering is applied at write time; keep input order stable here
  return out;
}
```

- [ ] **Step 4: Run, verify pass**

Run: `cd twins-dash && deno test supabase/functions/_shared/estimate-tracker/aggregate.test.ts`
Expected: PASS (all 5 tests).

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/estimate-tracker/
git commit -m "feat(estimates): per-customer open-estimate aggregation with option ranges"
```

---

## Task 4: Sheet reconciliation logic (pure, unit-tested)

Decides, given the desired tracker rows and the sheet's current state, what to update / append / move-to-closed. Pure so it is fully testable; the edge function (Task 5) just feeds it sheet data and executes its decisions.

**Files:**
- Create: `twins-dash/supabase/functions/_shared/estimate-tracker/reconcile.ts`
- Test: `twins-dash/supabase/functions/_shared/estimate-tracker/reconcile.test.ts`

- [ ] **Step 1: Write failing tests**

```typescript
// reconcile.test.ts
import { assertEquals } from 'https://deno.land/std@0.224.0/assert/mod.ts';
import { reconcile, type ExistingRow } from './reconcile.ts';
import type { TrackerRow } from './aggregate.ts';

const desired = (id: string, customer = 'Jane Doe'): TrackerRow => ({
  customerId: id, customer, phone: '(608) 555-1234', openCount: 1,
  totalQuoted: '$7,574', details: 'EST-2399 · $7,574 · 4/29',
  assignedTech: 'Charles', oldestDate: '4/29/2026',
});
// rowNumber is the 1-based sheet row; customerId read from hidden col N
const existing = (rowNumber: number, id: string): ExistingRow => ({ rowNumber, customerId: id });

Deno.test('new customer with no existing row -> append', () => {
  const plan = reconcile([desired('cus_1')], [], new Map());
  assertEquals(plan.appends.length, 1);
  assertEquals(plan.updates.length, 0);
  assertEquals(plan.closes.length, 0);
  assertEquals(plan.appends[0].customerId, 'cus_1');
});

Deno.test('existing customer still open -> update auto cells only, by row', () => {
  const plan = reconcile([desired('cus_1')], [existing(2, 'cus_1')], new Map());
  assertEquals(plan.appends.length, 0);
  assertEquals(plan.updates.length, 1);
  assertEquals(plan.updates[0].rowNumber, 2);
});

Deno.test('existing customer no longer open -> close with stamped outcome', () => {
  const plan = reconcile([], [existing(2, 'cus_1')], new Map([['cus_1', 'Booked']]));
  assertEquals(plan.updates.length, 0);
  assertEquals(plan.closes.length, 1);
  assertEquals(plan.closes[0].rowNumber, 2);
  assertEquals(plan.closes[0].outcome, 'Booked');
});

Deno.test('outcome defaults to Mixed when not resolvable', () => {
  const plan = reconcile([], [existing(5, 'cus_9')], new Map());
  assertEquals(plan.closes[0].outcome, 'Mixed');
});
```

- [ ] **Step 2: Run, verify failure**

Run: `cd twins-dash && deno test supabase/functions/_shared/estimate-tracker/reconcile.test.ts`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement**

```typescript
// reconcile.ts
import type { TrackerRow } from './aggregate.ts';

export interface ExistingRow { rowNumber: number; customerId: string; }
export interface ClosePlan { rowNumber: number; customerId: string; outcome: string; }
export interface UpdatePlan { rowNumber: number; row: TrackerRow; }
export interface ReconcilePlan {
  appends: TrackerRow[];
  updates: UpdatePlan[];
  closes: ClosePlan[];   // rows to copy to Closed then delete from Follow-Up
}

/**
 * desired   = current per-customer open-estimate rollup (Task 3 output)
 * existing  = rows currently on the Follow-Up tab (rowNumber + hidden customerId)
 * outcomes  = customerId -> 'Booked'|'Declined'|'Mixed' for customers leaving the open set
 */
export function reconcile(
  desired: TrackerRow[],
  existing: ExistingRow[],
  outcomes: Map<string, string>,
): ReconcilePlan {
  const existingById = new Map(existing.map(e => [e.customerId, e]));
  const desiredIds = new Set(desired.map(d => d.customerId));

  const appends: TrackerRow[] = [];
  const updates: UpdatePlan[] = [];
  for (const d of desired) {
    const match = existingById.get(d.customerId);
    if (match) updates.push({ rowNumber: match.rowNumber, row: d });
    else appends.push(d);
  }

  const closes: ClosePlan[] = [];
  for (const e of existing) {
    if (!desiredIds.has(e.customerId)) {
      closes.push({ rowNumber: e.rowNumber, customerId: e.customerId, outcome: outcomes.get(e.customerId) ?? 'Mixed' });
    }
  }
  return { appends, updates, closes };
}
```

- [ ] **Step 4: Run, verify pass**

Run: `cd twins-dash && deno test supabase/functions/_shared/estimate-tracker/reconcile.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/_shared/estimate-tracker/reconcile.ts supabase/functions/_shared/estimate-tracker/reconcile.test.ts
git commit -m "feat(estimates): reconcile desired rollup against current sheet state"
```

---

## Task 5: The `sync-estimate-tracker` edge function

Wires it together: query DB → aggregate → ensure sheet structure → read sheet → reconcile → execute writes. Also handles the CSR `Remove` flag and the one-time tab/dropdown bootstrap.

**Files:**
- Create: `twins-dash/supabase/functions/sync-estimate-tracker/index.ts`
- Create: `twins-dash/supabase/functions/sync-estimate-tracker/structure.ts`
- Modify: `twins-dash/supabase/config.toml` (register function, `verify_jwt = false`)

- [ ] **Step 1: Sheet structure bootstrap (`structure.ts`)**

Idempotent: ensures the Follow-Up and Closed tabs exist with headers, a frozen header row, the hidden ID column, and data-validation dropdowns. Safe to run every invocation (it diffs against the live spreadsheet metadata before issuing batchUpdate requests).

```typescript
// structure.ts
import { SheetsClient } from '../_shared/google/sheets.ts';

export const HEADERS = ['Customer','Phone','# Open Est','Total Quoted','Estimate Details',
  'Assigned Tech','Oldest Est Date','Follow-Up Status','Booked?','Last Follow-Up',
  'Next Follow-Up','Notes','Remove','HCP Customer ID'];
export const CLOSED_HEADERS = [...HEADERS, 'Outcome', 'Date Closed'];

const STATUS_OPTS = ['New','Attempted','Reached','Callback set','No answer','Do not contact'];
const BOOKED_OPTS = ['No','Yes','Partial'];
const REMOVE_OPTS = ['—','Remove'];

export async function ensureStructure(sheets: SheetsClient): Promise<{ followUpId: number; closedId: number }> {
  const meta = await sheets.getSpreadsheet();
  const byTitle = new Map<string, any>(meta.sheets.map((s: any) => [s.properties.title, s.properties]));
  const requests: any[] = [];

  if (!byTitle.has('Follow-Up')) requests.push({ addSheet: { properties: { title: 'Follow-Up' } } });
  if (!byTitle.has('Closed')) requests.push({ addSheet: { properties: { title: 'Closed' } } });
  if (requests.length) { await sheets.batchUpdate(requests); }

  // Re-read to get sheetIds for any newly created tabs
  const meta2 = await sheets.getSpreadsheet();
  const ids = new Map<string, number>(meta2.sheets.map((s: any) => [s.properties.title, s.properties.sheetId]));
  const followUpId = ids.get('Follow-Up')!;
  const closedId = ids.get('Closed')!;

  // Headers
  await sheets.updateValues('Follow-Up!A1:N1', [HEADERS]);
  await sheets.updateValues('Closed!A1:P1', [CLOSED_HEADERS]);

  // Freeze header, hide column N (HCP Customer ID), dropdowns on H/I/M for first 1000 rows
  const dv = (sheetId: number, colStart: number, colEnd: number, values: string[]) => ({
    setDataValidation: {
      range: { sheetId, startRowIndex: 1, endRowIndex: 1000, startColumnIndex: colStart, endColumnIndex: colEnd },
      rule: { condition: { type: 'ONE_OF_LIST', values: values.map(v => ({ userEnteredValue: v })) },
              showCustomUi: true, strict: false },
    },
  });
  await sheets.batchUpdate([
    { updateSheetProperties: { properties: { sheetId: followUpId, gridProperties: { frozenRowCount: 1 } }, fields: 'gridProperties.frozenRowCount' } },
    { updateDimensionProperties: { range: { sheetId: followUpId, dimension: 'COLUMNS', startIndex: 13, endIndex: 14 }, properties: { hiddenByUser: true }, fields: 'hiddenByUser' } },
    dv(followUpId, 7, 8, STATUS_OPTS),   // H Follow-Up Status
    dv(followUpId, 8, 9, BOOKED_OPTS),   // I Booked?
    dv(followUpId, 12, 13, REMOVE_OPTS), // M Remove
  ]);
  return { followUpId, closedId };
}
```

- [ ] **Step 2: The function (`index.ts`)**

```typescript
// index.ts
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2.39.3';
import { corsHeaders } from '../_shared/auth.ts';
import { SheetsClient } from '../_shared/google/sheets.ts';
import { aggregateByCustomer, type EstimateRow } from '../_shared/estimate-tracker/aggregate.ts';
import { reconcile, type ExistingRow } from '../_shared/estimate-tracker/reconcile.ts';
import { ensureStructure, HEADERS, CLOSED_HEADERS } from './structure.ts';

const CUTOFF = '2026-01-01';

function rowValues(r: ReturnType<typeof aggregateByCustomer>[number]): any[] {
  // A..N — CSR columns H..M left blank on append (status defaults applied below)
  return [r.customer, r.phone, r.openCount, r.totalQuoted, r.details, r.assignedTech, r.oldestDate,
    'New', '', '', '', '', '—', r.customerId];
}

Deno.serve(async (req) => {
  if (req.method === 'OPTIONS') return new Response(null, { headers: corsHeaders });
  try {
    const supabase = createClient(Deno.env.get('SUPABASE_URL')!, Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!);
    const sheets = new SheetsClient(Deno.env.get('GOOGLE_SHEETS_SA_KEY')!, Deno.env.get('ESTIMATE_TRACKER_SHEET_ID')!);

    // 1. Pull open estimates since cutoff, joined to tech name
    const { data: jobs, error } = await supabase
      .from('jobs')
      .select('hcp_data, tech_id, technicians(name)')
      .eq('job_type', 'Estimate')
      .eq('estimate_status', 'open')
      .gte('hcp_data->>created_at', CUTOFF);
    if (error) throw error;

    const estimateRows: EstimateRow[] = (jobs ?? [])
      .filter((j: any) => j.hcp_data?.customer?.id)
      .map((j: any) => ({
        customer: j.hcp_data.customer,
        estimate_number: j.hcp_data.estimate_number ?? null,
        created_at: j.hcp_data.created_at,
        options: Array.isArray(j.hcp_data.options) ? j.hcp_data.options : [],
        tech_name: (j.technicians as any)?.name ?? null,
      }));
    const desired = aggregateByCustomer(estimateRows);
    const desiredIds = new Set(desired.map(d => d.customerId));

    // 2. Ensure tabs/headers/dropdowns
    await ensureStructure(sheets);

    // 3. Read current Follow-Up rows (A2:N), build existing index from hidden col N
    const cur = await sheets.getValues('Follow-Up!A2:N');
    const curRows: string[][] = cur.values ?? [];
    const existing: ExistingRow[] = curRows
      .map((row, i) => ({ rowNumber: i + 2, customerId: row[13] ?? '', removeFlag: (row[12] ?? '').trim() }))
      .filter(e => e.customerId);

    // 4. Resolve outcomes for customers leaving the open set (Booked/Declined/Mixed)
    const leavingIds = existing.filter(e => !desiredIds.has(e.customerId)).map(e => e.customerId);
    const outcomes = new Map<string, string>();
    // CSR Remove flag wins
    for (const e of existing) if (e.removeFlag === 'Remove') outcomes.set(e.customerId, 'Removed by CSR');
    if (leavingIds.length) {
      const { data: statusRows } = await supabase
        .from('jobs')
        .select('hcp_data->customer->>id as cust_id, estimate_status')
        .eq('job_type', 'Estimate')
        .in('hcp_data->customer->>id', leavingIds);
      const byCust = new Map<string, Set<string>>();
      for (const s of (statusRows ?? []) as any[]) {
        const set = byCust.get(s.cust_id) ?? new Set<string>();
        set.add(s.estimate_status); byCust.set(s.cust_id, set);
      }
      for (const id of leavingIds) {
        if (outcomes.has(id)) continue; // Remove flag already set
        const set = byCust.get(id) ?? new Set();
        outcomes.set(id, set.has('sold') && set.has('declined') ? 'Mixed'
          : set.has('sold') ? 'Booked' : set.has('declined') ? 'Declined' : 'Mixed');
      }
    }
    // Also treat CSR-Removed (even if still open) as leaving
    const removedExisting = existing.filter(e => e.removeFlag === 'Remove');

    // 5. Reconcile
    const plan = reconcile(desired, existing.filter(e => e.removeFlag !== 'Remove'), outcomes);
    // force-close CSR-removed rows
    for (const e of removedExisting) plan.closes.push({ rowNumber: e.rowNumber, customerId: e.customerId, outcome: 'Removed by CSR' });

    // 6. Execute updates (auto cells A:G only — never CSR H:M; N stays as-is)
    for (const u of plan.updates) {
      const r = u.row;
      await sheets.updateValues(`Follow-Up!A${u.rowNumber}:G${u.rowNumber}`,
        [[r.customer, r.phone, r.openCount, r.totalQuoted, r.details, r.assignedTech, r.oldestDate]]);
    }

    // 7. Append new customers (full A:N)
    if (plan.appends.length) {
      await sheets.appendValues('Follow-Up!A:N', plan.appends.map(rowValues));
    }

    // 8. Closes: copy full row to Closed (+ Outcome, Date Closed), then delete from Follow-Up bottom-up
    const today = new Date();
    const stamp = `${today.getUTCMonth() + 1}/${today.getUTCDate()}/${today.getUTCFullYear()}`;
    const closeByRow = new Map(plan.closes.map(c => [c.rowNumber, c]));
    if (plan.closes.length) {
      const closedRows = plan.closes
        .sort((a, b) => a.rowNumber - b.rowNumber)
        .map(c => { const src = curRows[c.rowNumber - 2] ?? []; return [...HEADERS.map((_, i) => src[i] ?? ''), c.outcome, stamp]; });
      await sheets.appendValues('Closed!A:P', closedRows);

      // delete from Follow-Up, descending row order so indices don't shift
      const meta = await sheets.getSpreadsheet();
      const followUpId = meta.sheets.find((s: any) => s.properties.title === 'Follow-Up').properties.sheetId;
      const deletes = [...closeByRow.keys()].sort((a, b) => b - a).map(rowNumber => ({
        deleteDimension: { range: { sheetId: followUpId, dimension: 'ROWS', startIndex: rowNumber - 1, endIndex: rowNumber } },
      }));
      await sheets.batchUpdate(deletes);
    }

    return new Response(JSON.stringify({
      success: true, open_estimates: estimateRows.length, customers: desired.length,
      appended: plan.appends.length, updated: plan.updates.length, closed: plan.closes.length,
    }), { headers: { ...corsHeaders, 'Content-Type': 'application/json' } });
  } catch (e) {
    console.error('estimate-tracker sync error:', e);
    return new Response(JSON.stringify({ error: 'Estimate tracker sync failed' }),
      { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } });
  }
});
```

- [ ] **Step 3: Register the function in `config.toml`**

Append (match the pattern of other internal sync functions):

```toml
[functions.sync-estimate-tracker]
verify_jwt = false
```

- [ ] **Step 4: Type-check**

Run: `cd twins-dash && deno check supabase/functions/sync-estimate-tracker/index.ts`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/sync-estimate-tracker/ supabase/config.toml
git commit -m "feat(estimates): sync-estimate-tracker edge function (DB -> Google Sheet)"
```

---

## Task 6: Deploy, share the sheet, and run the first sync

**Files:** none (deploy + manual verification).

- [ ] **Step 1: Deploy**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
supabase functions deploy sync-estimate-tracker --project-ref jwrpjuqaynownxaoeayi
```

- [ ] **Step 2: Daniel shares the sheet with the SA**

Open the sheet, Share → add the `GOOGLE_SHEETS_SA_EMAIL` value (from Task 1 Step 4) as **Editor**. This is the one unavoidable manual step. Verify by listing access.

- [ ] **Step 3: Invoke the first sync**

```bash
curl -s -X POST 'https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/sync-estimate-tracker' \
  -H "Authorization: Bearer $(supabase projects api-keys --project-ref jwrpjuqaynownxaoeayi -o json | jq -r '.[] | select(.name=="anon").api_key')" \
  -H 'Content-Type: application/json' -d '{}'
```

Expected JSON: `{"success":true,"open_estimates":287,"customers":225,"appended":225,"updated":0,"closed":0}` (numbers will drift with live data).

- [ ] **Step 4: Verify the sheet by hand**

Confirm in the Follow-Up tab: ~225 rows, customer/phone/quote/details populated, ranges render for multi-option estimates, dropdowns work on Follow-Up Status / Booked? / Remove, column N (HCP Customer ID) is hidden. Confirm the Closed tab exists with its header.

- [ ] **Step 5: Verify CSR columns are preserved on re-run**

Type a note into L2 (Notes) and set H2 to "Reached", then re-invoke the curl from Step 3. Confirm L2 and H2 are unchanged and `updated` is now non-zero. This proves the no-clobber rule.

No commit.

---

## Task 7: Nightly cron

Run the sync nightly, after the `auto-sync-jobs` refresh so the sheet reflects the freshest HCP data. The existing dispatch backfill cron runs at 03:30 UTC; schedule this at 04:30 UTC to follow the nightly estimate sync.

**Files:**
- Create: `twins-dash/supabase/migrations/20260625120000_estimate_tracker_cron.sql`

- [ ] **Step 1: Write the migration**

```sql
-- Nightly Google Sheets estimate-tracker sync.
-- 04:30 UTC, after auto-sync-jobs / sync-hcp-estimates have refreshed the jobs table.
-- Same invocation pattern as 20260511121000_backfill_dispatch_cron.sql
-- (hardcoded project URL + vault.decrypted_secrets for the bearer).
BEGIN;

SELECT cron.schedule(
  'estimate-tracker-nightly',
  '30 4 * * *',
  $$
  SELECT net.http_post(
    url := 'https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/sync-estimate-tracker',
    headers := jsonb_build_object(
      'Authorization', 'Bearer ' || (SELECT decrypted_secret FROM vault.decrypted_secrets WHERE name = 'supabase_anon_key'),
      'Content-Type', 'application/json'
    ),
    body := jsonb_build_object('source', 'pg_cron'),
    timeout_milliseconds := 300000
  );
  $$
);

COMMIT;
```

- [ ] **Step 2: Apply the migration**

```bash
cd /Users/daniel/twins-dashboard/twins-dash
supabase db push --project-ref jwrpjuqaynownxaoeayi
```

> Note: per the repo's known migration-history desync, if `db push` reports the remote is ahead, apply this file's SQL via the Supabase SQL editor / `execute_sql` and then INSERT its version row into `supabase_migrations.schema_migrations` (version `20260625120000`).

- [ ] **Step 3: Verify the cron is registered**

Run (via `execute_sql`): `SELECT jobname, schedule FROM cron.job WHERE jobname = 'estimate-tracker-nightly';`
Expected: one row, schedule `30 4 * * *`.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260625120000_estimate_tracker_cron.sql
git commit -m "feat(estimates): nightly cron for estimate-tracker sheet sync"
```

---

## Self-review notes (spec coverage)

- One row per customer → Task 3 `aggregateByCustomer`. ✓
- Multiples shown, not duplicated → `details` column join. ✓
- Quote as range, Total Quoted = sum of highs → Task 3 + tests. ✓
- From 2026-01-01 → `CUTOFF` predicate in Task 5. ✓
- Auto vs CSR columns; never clobber CSR → Task 5 Step 6 writes A:G only; Task 6 Step 5 verifies. ✓
- New estimates auto-populate → `appends` in reconcile + nightly cron. ✓
- Resolved → Closed tab with outcome+date, notes preserved → Task 4 `closes` + Task 5 Step 8. ✓
- Remove flag → force-close as "Removed by CSR" in Task 5 Step 4–5. ✓
- Phone (mobile→home→work) → Task 3 `formatPhone`. ✓
- Dropdowns, hidden ID col, frozen header → Task 5 `ensureStructure`. ✓
- Service account + secrets + one-time share → Tasks 1 & 6. ✓

## Open risk to watch during execution

- `.gte('hcp_data->>created_at', CUTOFF)` does a **text** comparison on ISO timestamps; ISO-8601 strings sort lexically the same as chronologically, so `'2026-...' >= '2026-01-01'` is correct. If any `created_at` is stored without a leading zero / non-ISO, revisit. Verified current data is ISO `2026-04-29T...`.
- Sheets API `updateValues` per-row in Task 5 Step 6 is one HTTP call per updated customer. At ~225 rows the first run only appends (1 call); steady-state daily updates are small. If update volume grows, batch via `values:batchUpdate` — noted, not needed now (YAGNI).
