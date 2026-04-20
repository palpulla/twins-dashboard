# LandlordLens M2 — Email-gated Free Report Design Spec

**Date:** 2026-04-20
**Status:** Approved for implementation planning
**Parent spec:** [2026-04-20-landlordlens-design.md](./2026-04-20-landlordlens-design.md)
**Predecessor milestone:** M1 shipped 2026-04-20 (live at https://landlordlens-gamma.vercel.app)

---

## 1. Problem

M1 shipped the free Owner Verification Card. That answers "who owns this rental?" but not the two follow-up questions renters immediately ask:

1. **If it's an LLC, who is actually behind it?** Most rentals in major US metros (~60–80% in Chicago, Minneapolis, Madison) are held via LLC. "ACME PROPERTIES LLC" tells a renter nothing about the human they're about to sign a lease with.
2. **Is the landlord on a sex offender registry?** This is the highest-severity safety signal consumers care about. NSOPW (the DOJ's national registry) is free and public, but unusable for casual lookup — users rarely search it, and it has fragile UX.

M2 adds both, plus an expanded property snapshot and a rules-based red-flag summary, behind a lightweight email gate (magic link).

## 2. Non-goals (M2)

- **No criminal records.** Deferred to M3 alongside the state court scrapers already planned. Vendor compliance review for consumer-records aggregators takes 1–3 weeks; keeping it out of M2 removes that dependency from the critical path.
- **No Stripe / paywall / Deep Report.** M3.
- **No eviction, litigation, or code-violation data.** M3.
- **No PDF export.** M3.
- **No landlord dispute flow or legal-hardening features.** M4.
- **No Spanish UI.**
- **No rate limiting on public endpoints.** M4.
- **No behavioral biometrics, captcha, or anti-abuse beyond Supabase Auth defaults.** M4.

## 3. Positioning and legal posture (unchanged from parent spec)

- **Personal-safety consumer information service**, not a CRA.
- Report header, ToS, every email-linked report page show the non-FCRA disclaimer from §3 of the parent spec. Copy is load-bearing — do not rephrase without legal review.
- Shows only public records or records derived from public-records sources. No crowdsourced or user-submitted content.
- **Name-match UI discipline:** report copy never asserts "this landlord is a sex offender" or similar. Always phrased as "N matches in [state] for individuals named [name] — verify identity before proceeding." Photos and NSOPW deep-links let the user visually confirm.
- NSOPW's own disclaimer must be displayed on any page showing SOR match data. Scraper auto-accepts the upstream disclaimer to fetch results; the UI reproduces the disclaimer to the user.

## 4. Scope

### National (all US addresses)
- Owner Verification Card (unchanged from M1)
- Sex offender registry check via NSOPW, scoped to property state
- Property snapshot expansion (additional fields from existing ATTOM response)
- Red-flag summary

### Launch states only (WI / IL / MN) — LLC piercing
- Secretary of State lookup of LLC members / registered agents for properties owned by LLCs
- Outside launch states: when owner is an LLC, the report honestly states "public records in [state] don't expose LLC member information in a machine-readable way" and offers a link to the state's SoS portal for manual lookup.

## 5. User flows

### 5.1 First-time renter

1. Renter enters address at `/` → Owner Card renders (free, anonymous — M1 flow unchanged).
2. Below the Owner Card, a new section: **"See the full picture"** with:
   - Teaser list of what's in the Free Report (LLC member lookup, sex-offender check, red flags)
   - Inline email input + "Send me the full report" button
3. On submit:
   - Email captured → Supabase Auth sends magic link
   - UI shows "Check your email. Link expires in 10 minutes."
4. User clicks email link → lands at `/report/:id` with full Free Report rendered.
5. Session persists via Supabase Auth cookie. Returning users don't need to re-auth.

### 5.2 Returning renter

1. Returning user hits `/` → signed in automatically (cookie).
2. Address bar at top shows "Welcome back, [email]. Dashboard."
3. Dashboard (`/dashboard`) lists prior reports: address, date run, status, link to re-open.
4. Re-running a prior address hits the 30-day cache; re-runs beyond 30 days re-fetch fresh data (still free).

### 5.3 Report in progress / partial

When some sections succeed and others fail (e.g., NSOPW is down, but SoS piercer works), the report renders with:
- Completed sections fully populated
- Failed sections shown with an amber box: "Couldn't load sex-offender check. We'll retry automatically. Refresh in a few minutes."
- Red-flag summary adapts to available data — it does not fabricate findings from missing data.

## 6. Report structure (M2 additions)

### 6.1 Owner Card (unchanged from M1)

### 6.2 LLC Member Lookup (launch states only, shown only if owner is LLC)

| Field | Source |
|---|---|
| LLC legal name | From Owner Card |
| State of formation | Inferred from property state; edge case: LLC formed in a different state (Delaware, etc.) — if ATTOM surfaces the formation state, use that; otherwise use property state and note the limitation. |
| Registered agent | Scraped from state SoS portal |
| Members / managers (names) | Scraped from SoS portal |
| Date of most recent filing | Scraped |
| Source URL | Link back to the state SoS page for user verification |

When piercing fails (timeout, portal change, or the state doesn't publish member data machine-readable): section shows "This state's SoS portal doesn't expose member information in a scrapeable format. Search manually: [link to state portal]."

### 6.3 Sex Offender Registry (national via NSOPW)

| Field | Source |
|---|---|
| Candidate name(s) | Owner name(s) — person owner directly, or pierced LLC member names |
| State scope | Property state (not national — constrains false-positive rate) |
| Match count | NSOPW search result count |
| Per-match: name, DOB-year, offense type, jurisdiction, photo thumbnail, NSOPW deep-link | NSOPW result page |
| Disclaimer | NSOPW's own text, displayed verbatim |

**False-positive mitigation:** UI copy never says "landlord IS a sex offender." Always phrased as "N matches in [state] for someone named [name]" + requires visual verification via NSOPW deep link. If name is very common (JOHN SMITH in Illinois likely has many matches), UI shows a warning: "This is a common name. Expect many false positives — verify identity via photos on NSOPW."

### 6.4 Property Snapshot Expansion

Additional fields surfaced from the ATTOM Property Detail response (already fetched for M1, not previously displayed):

| Field | Use |
|---|---|
| Owner mailing address | Flags out-of-state investor-owners (red flag if different state from property) |
| Unit count | For multi-family properties |
| Sale history (count of sales in last 5 years) | Flags frequent-flip properties |
| Zoning / property use class | Surfaces residential-vs-commercial mismatches |
| Year of last assessment | Data freshness indicator |

No additional ATTOM calls needed — all from existing response.

### 6.5 Red-Flag Summary (rules-based)

Deterministic rules engine. Input: fully assembled report JSON. Output: ordered array of `Finding` objects:

```ts
interface Finding {
  severity: 'warn' | 'info' | 'ok';
  title: string;
  body: string;
  section: 'owner' | 'llc' | 'sor' | 'property';
}
```

**Rules (v1 set):**

| Rule | Severity | Title | Body (template) |
|---|---|---|---|
| ≥1 SOR match in property state | `warn` | "Sex offender registry match" | "{N} match(es) found in {state} for individuals named {ownerName}. Verify identity via photos before proceeding." |
| LLC piercing failed in launch state | `warn` | "Who controls this LLC is unclear" | "{llcName} is registered with the state but member information isn't exposed publicly. Ask the landlord directly." |
| LLC piercing succeeded | `info` | "Property held by LLC" | "{llcName} — member(s): {memberNames}. Further checks run against these individuals." |
| LLC in non-launch state | `info` | "Property owned by LLC (out of our coverage area)" | "{llcName} is an LLC. Member lookup isn't supported in {state} yet. Search manually: {sosUrl}." |
| Property tax delinquent | `warn` | "Property taxes are delinquent" | "County records show {ownerName} is behind on property taxes. Rent you pay could be at risk if the property is seized." |
| ≥3 ownership changes in last 5 years | `info` | "Frequent ownership changes" | "This property has changed hands {N} times in the last 5 years." |
| Owner mailing address in different state from property | `info` | "Out-of-state owner" | "Owner's mailing address is in {ownerState}; the property is in {propertyState}. Expect remote property management." |
| No warns or infos triggered | `ok` | "No red flags found in public records" | "We couldn't find anything concerning about this rental in public records. This doesn't guarantee the rental is safe — always verify independently." |

Ordering: all `warn` findings first (any order among them), then `info`, then `ok`. The `ok` finding only appears if no others do.

## 7. Architecture

### 7.1 Edge functions (new)

- **`free-report`** — orchestrator, triggered when user hits `/report/:id` (or when auto-run after magic-link login). Calls:
  1. `owner-lookup` (existing M1 function, via internal invocation or shared service layer)
  2. `sos-piercer` — only if owner is LLC + launch state
  3. `nsopw-scraper` — always (national)
  4. `red-flag-engine` — pure function, assembles Finding[]
  Persists results to `report_sections`. Returns assembled report.

- **`sos-piercer`** — per-state dispatcher. Routes by state code:
  - `wi_sos.ts` — scrapes https://www.wdfi.org/apps/corpsearch/
  - `il_sos.ts` — scrapes https://apps.ilsos.gov/businessentitysearch/
  - `mn_sos.ts` — scrapes https://mblsportal.sos.mn.gov/
  - Common interface: `searchLlc(name: string): Promise<LlcInfo | null>`
  - Caches via `llc_members` table (90-day TTL).

- **`nsopw-scraper`** — scrapes https://www.nsopw.gov/
  - Auto-accepts upstream disclaimer
  - Search by name + state
  - Returns array of match records with photos (thumbnails hotlinked to NSOPW)
  - Caches via `sor_matches` table (30-day TTL).

- **`red-flag-engine`** — pure TypeScript module, no I/O. Unit-testable in isolation.

### 7.2 Frontend additions

- **Route: `/report/:id`** — full Free Report page for signed-in users
- **Route: `/dashboard`** — list of user's reports
- **Route: `/login`** — magic-link form (also embedded inline on `/`)
- **Component: `EmailGateForm`** — inline email input + submit + success state
- **Component: `FreeReportSections`** — renders LLC / SOR / property-snapshot / red-flags sections
- **Component: `RedFlagSummary`** — styled list of findings with icon + severity color
- **Component: `SorMatchCard`** — individual SOR match display with photo, offense, NSOPW link, "verify this is not your landlord" prompt
- **Component: `LlcMembersCard`** — renders pierced LLC member list with source link

### 7.3 Auth

- Supabase Auth, magic-link email (no password).
- `signInWithOtp({ email })` → redirect-to URL points at `/report/:id` for the report the user was viewing when they submitted their email.
- Cookie-based session, 7-day default (Supabase default).
- Sign-out link in dashboard header.

### 7.4 Caching

- Inherit M1's 30-day `cached_lookups` for ATTOM.
- Add 30-day `sor_matches` (NSOPW freshness).
- Add 90-day `llc_members` (SoS data rarely changes).
- Reports themselves don't cache — they're assembled per-request from cached section data. Assembly is cheap.

### 7.5 Failure handling

Each edge function returns typed errors same as M1 pattern:
- `not_found` — upstream said no data
- `rate_limited` — upstream rate-limited us
- `upstream_error` — upstream returned unexpected error
- `scraper_failed` — HTML parse failed (likely structure changed)

Orchestrator's `free-report` function continues with remaining sections when one fails. Failed sections render as amber-box states in UI.

### 7.6 Section attribution

Every section stores `source_attribution` (e.g., "Wisconsin Department of Financial Institutions, retrieved 2026-04-20") in `report_sections.source_attribution`. UI displays this as small footnote text beneath each section. This is both trust-building and legal cover (we're quoting public records, not making claims).

## 8. Data model additions

```sql
-- Reports tied to authenticated users
create table public.reports (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  address_id uuid not null references public.addresses(id),
  tier text not null check (tier in ('free', 'deep')) default 'free',
  status text not null check (status in ('pending', 'complete', 'partial', 'failed')) default 'pending',
  created_at timestamptz not null default now(),
  expires_at timestamptz not null default now() + interval '30 days'
);

create index idx_reports_user on public.reports(user_id, created_at desc);
create index idx_reports_address on public.reports(address_id);

-- Sections within a report
create type public.section_type as enum (
  'owner_card', 'llc_members', 'sor', 'property_snapshot', 'red_flags'
);
create type public.section_status as enum ('pending', 'complete', 'failed');

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

-- LLC member cache (pierced data from SoS portals)
create table public.llc_members (
  id uuid primary key default gen_random_uuid(),
  llc_name_normalized text not null,
  state text not null,
  members jsonb not null,  -- [{ name: string, role: string, source_url: string }]
  registered_agent text,
  filing_date date,
  source_url text,
  fetched_at timestamptz not null default now(),
  expires_at timestamptz not null default now() + interval '90 days',
  unique(llc_name_normalized, state)
);

create index idx_llc_members_expires on public.llc_members(expires_at);

-- SOR match cache
create table public.sor_matches (
  id uuid primary key default gen_random_uuid(),
  name_normalized text not null,
  state text not null,
  matches jsonb not null,  -- [{ name, dob_year, offense, jurisdiction, photo_url, nsopw_url }]
  disclaimer_text text not null,  -- NSOPW's own disclaimer at time of fetch
  fetched_at timestamptz not null default now(),
  expires_at timestamptz not null default now() + interval '30 days',
  unique(name_normalized, state)
);

create index idx_sor_matches_expires on public.sor_matches(expires_at);
```

### RLS policies (new)

```sql
alter table public.reports enable row level security;
alter table public.report_sections enable row level security;
alter table public.llc_members enable row level security;
alter table public.sor_matches enable row level security;

-- Users can read only their own reports
create policy "users_select_own_reports" on public.reports
  for select using (auth.uid() = user_id);

create policy "users_select_own_sections" on public.report_sections
  for select using (
    exists (select 1 from public.reports r where r.id = report_id and r.user_id = auth.uid())
  );

-- llc_members + sor_matches: no anon policies (edge-function-only via service role).
-- This matches M1's pattern for addresses/owners/cached_lookups.
```

## 9. Security and compliance notes

- **NSOPW scraping etiquette:** Rate-limit our scraper to 1 req/sec. User-agent identifies the service honestly. Honor any robots.txt directives (NSOPW doesn't currently have one that restricts our usage, but re-check at build time).
- **SoS portals:** Same etiquette. Use per-state rate limits appropriate to each portal (some are slower than others).
- **NSOPW disclaimer must be shown with every SOR result display.** Captured into `sor_matches.disclaimer_text` on scrape so we always show the exact text that was in force at fetch time.
- **No storage of full personal data from SOR.** We cache the match metadata + deep-links. Photos are hotlinked, not copied. When a cache entry expires or is refreshed, stale data is discarded.
- **Do not log user email addresses** in edge function logs. Supabase Auth handles the email flow; our logs should only show user UUID.
- **User deletion on request:** CCPA/CPRA compliance — user can delete account, which cascades to `reports` and `report_sections` via the FK ON DELETE CASCADE.

## 10. Metrics for M2

- **Primary:** % of Owner-Card viewers who enter their email.
- **Secondary:**
  - % of magic-link emails that get clicked within 10 minutes
  - % of full reports that have ≥1 non-Owner section complete (report quality)
  - SOR scraper success rate
  - SoS scraper success rate per state
  - Cache hit rate on `llc_members` and `sor_matches`
- **Quality:** manual audit of 30 reports pre-launch — pick 10 addresses in each launch state, verify LLC piercing and SOR results against ground-truth SoS/NSOPW.

## 11. Risks and open questions

### Risks
1. **NSOPW site structure changes break the scraper.** NSOPW has been stable for years, but any HTML change breaks us. Mitigation: monitoring with synthetic test cases run daily; quick-fix plan for when breakage happens.
2. **State SoS portals block scrapers.** Wisconsin's portal in particular has CAPTCHAs on some queries. Mitigation: solve CAPTCHA only where we must (start conservative; if WI blocks us, fall back to manual-lookup UI).
3. **False-positive SOR matches erode trust.** A common name in a big state could return 50+ matches and cause panic. Mitigation: UI discipline on language, sort matches by closeness-to-property-location when possible, surface photo prominently.
4. **Users enter wrong email or don't click magic link.** UX dead-end. Mitigation: show "didn't get the email?" link with resend flow; Supabase's magic-link UX is already well-tested.
5. **LLC piercing surfaces a registered-agent service** (e.g., CT Corporation) rather than the real owner. Mitigation: heuristic to flag known registered-agent services and label them as such; UI honestly says "this is a registered-agent service, not the property owner."

### Open questions (resolve during implementation planning)
1. Which CAPTCHA-solving service (if any) for stubborn SoS portals — 2Captcha vs Anti-Captcha vs manual fallback. Probably defer and see which portals actually block us.
2. NSOPW photo hotlinking vs local caching — hotlinking is simpler but breaks if NSOPW rotates image URLs. Start with hotlinking; add image caching if we see breakage.
3. Session duration — Supabase default 7 days is fine for M2, revisit if we see re-auth friction.
4. Magic-link email template — use Supabase's default or customize. Start with default for M2; customize in M3 when branding matters more.

## 12. Out-of-scope items explicitly parked for M3+

- Criminal records (via state court scrapers — planned in M3)
- Eviction filings by landlord (M3)
- Civil litigation (M3)
- Property code violations (M3)
- Portfolio view (other properties owned by same entity) (M3)
- $12 Deep Report + Stripe (M3)
- PDF export / shareable links (M3)
- Landlord dispute flow (M4)
- Rate limiting on public endpoints (M4)
- Sentry / advanced monitoring (M4)
- E&O / media-liability insurance (before real launch in M4)
- Spanish UI

## 13. Acceptance criteria for M2

M2 ships when:

1. All new tests (auth UI, red-flag engine, SoS piercers, NSOPW scraper, orchestrator) pass.
2. A signed-in user can run a Free Report on a WI/IL/MN LLC-owned address and see: Owner Card + LLC members + SOR matches + property snapshot + red-flag summary.
3. A signed-in user can run a Free Report on an address outside launch states and see: Owner Card + (LLC notice OR normal owner) + SOR matches + property snapshot + red-flag summary.
4. NSOPW scraper runs successfully against a name known to have matches and returns expected match count.
5. SoS piercer runs successfully against a known LLC in each launch state.
6. Magic-link login end-to-end works: email → click → land on report page → session persists.
7. Red-flag summary passes engine unit tests: every rule fires on crafted test input and doesn't fire on crafted negative input.
8. Report page shows NSOPW disclaimer verbatim when SOR section has any match.
9. Dashboard lists prior reports and re-opening a report returns cached data within 500ms.
10. Manual QA against 30 addresses across three launch states shows ≥70% of LLC-owned properties get successful SoS piercing (lower is acceptable, but we should know the floor).

On all 10 ✅, tag `v0.2.0-m2` and hand off to M3 planning.
