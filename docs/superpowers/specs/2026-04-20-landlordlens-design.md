# LandlordLens — v1 Design Spec

**Date:** 2026-04-20
**Working title:** LandlordLens (final branding deferred)
**Status:** Approved for implementation planning
**Stack:** Claude Design (frontend) + Supabase (backend) + Stripe (payments)

---

## 1. Problem

US renters have no consumer-grade way to answer three questions before signing a lease:

1. **Is this rental legitimate?** — Fake-landlord Craigslist/Facebook scams are a massive, underserved category. Scammers advertise properties they don't own, collect deposits, and disappear.
2. **Is this landlord safe?** — Basic criminal / sex-offender screening on the person collecting your money and holding your keys.
3. **Is this landlord a slumlord?** — Eviction churn, code violations, prior tenant litigation, and litigation patterns that predict how a tenancy will go.

Every existing tenant-screening product serves landlords, not renters. Products that *do* serve renters (Plinq in Brazil) don't exist in the US, partly because US public records are fragmented across ~3,143 counties.

## 2. Non-goals (v1)

- Not a Consumer Reporting Agency (CRA). Not FCRA-regulated.
- Not a tenant-screening product. Landlords cannot use reports to make housing decisions.
- No crowdsourced reviews or user-submitted content. Defamation risk deferred to v2.
- No subscription tier. Per-address pricing only.
- No mobile app. Mobile-responsive web only.
- No Spanish UI in v1.
- No landlord-facing dashboard. Landlords can dispute records via a form; no other landlord UX.
- No tenant-initiated screening (flipping the Plinq model for landlords to consume renter reports). That's v2.

## 3. Positioning and legal posture

**Positioning:** Personal-safety consumer information tool. Same regulatory shape as BeenVerified's consumer tier — not the same shape as a tenant-screening service.

**Required in ToS, homepage footer, and every report header:**

> *"LandlordLens is a personal-safety information service. This is not a consumer report under the Fair Credit Reporting Act (FCRA). This information may not be used for tenant screening, employment, credit, insurance, or any other FCRA-regulated purpose."*

**Data sourcing principle:** Only display public records or data derived from public-records sources. No user-submitted claims. No crowdsourced reviews.

**Landlord dispute flow:** Any landlord can submit a dispute via a public form → human review → if record is inaccurate or out-of-date, annotate (do not delete; public records remain visible with a clearly shown dispute note). Builds good-faith compliance record.

**Privacy:**
- No resale of user data.
- CCPA / CPRA compliant (deletion requests honored for California residents and anywhere else required by law).
- Cookies and analytics: minimal, first-party only in v1.

## 4. Scope — launch markets

**National (all US addresses):**
- Owner Verification Card
- Free Report (criminal + sex offender + property snapshot + red-flag summary)

**Deep Report ($12, premium) — available only in these three metros for v1:**
- Madison / Dane County, WI
- Chicago / Cook County, IL
- Minneapolis–St. Paul metro, MN

These three share two properties: (a) open statewide/county court data (WI CCAP, IL Cook County eFile, MN MNCIS), and (b) municipal open-data portals publishing code violations. That's why the Deep Report is feasible there and not elsewhere in v1.

## 5. User flows

### 5.1 Primary flow — new renter vetting a prospective rental

1. Renter arrives at homepage. One input: property address (autocomplete).
2. Submits address → **Owner Verification Card** returned immediately (free, no signup).
   - Shows: owner of record (person or LLC), last sale date, last sale price, tax delinquency flag.
3. To see anything more, renter enters email → magic-link sign-in (Supabase Auth).
4. **Free Report** delivered after sign-in:
   - Criminal + sex offender check on the owner
   - Property parcel snapshot (year built, sqft, assessed value)
   - Plain-English red-flag summary
5. In launch metros only: CTA — "Unlock Deep Report — $12" → Stripe Checkout.
6. After payment, **Deep Report** appended: eviction history, civil litigation, code violations on this address, portfolio of other properties owned by same entity. Report is PDF-downloadable and shareable via a unique link.

### 5.2 Landlord dispute flow

1. Landlord (or their attorney) clicks "Dispute a record" link in the footer of any report.
2. Form collects: which report, which record, the claim, supporting documentation.
3. Staff reviews within 5 business days.
4. If warranted: report is updated with a dispute annotation ("This landlord has disputed this record. [Link to statement.]"). The underlying public record is not deleted.
5. If the record is factually wrong per source data, it is corrected and an audit entry is logged.

### 5.3 Returning user

1. User signs in via magic link.
2. Dashboard shows prior reports (both free and paid) with run timestamps.
3. Reports older than 30 days show a "Refresh" button to re-run against current data (free tier refreshes free; paid tier refreshes at a reduced fee of $6).

## 6. Report structure

### 6.1 Owner Verification Card (free, no signup)

| Field | Source |
|---|---|
| Property address (normalized) | Input |
| Owner of record (person or LLC name) | County assessor/recorder (via ATTOM or DataTree API) |
| Last sale date and price | Same |
| Current tax status (delinquent flag) | Same |
| Property type (SFR / multi-unit / condo) | Same |

### 6.2 Free Report (email-gated)

Adds to the Owner Card:

| Section | Data |
|---|---|
| Criminal history | National criminal records aggregator (consumer tier API) keyed on owner name |
| Sex offender registry | National Sex Offender Public Website or equivalent |
| Property snapshot | Year built, sqft, bed/bath, assessed value, last assessment date |
| Red-flag summary | Plain-English synthesis of any concerning findings ("This landlord has a sex-offender registry match" / "Property has had 3 ownership changes in the past 5 years" / "No red flags found in public records") |

### 6.3 Deep Report ($12, launch metros only)

Adds to the Free Report:

| Section | Data source (per metro) |
|---|---|
| Eviction history | WI: CCAP by party name. IL: Cook County eFile. MN: MNCIS. Shows count of filings in last 3 years, outcome (judgment / dismissed / pending), and properties involved. |
| Civil litigation | Same court systems; small-claims and civil filings with this owner as plaintiff or defendant. |
| Property code violations | Madison: City of Madison Building Inspection records. Chicago: Chicago Data Portal (Building Violations dataset). Minneapolis / St. Paul: respective city open data portals. |
| Portfolio | Other properties owned by the same entity, pulled from the same ATTOM/DataTree data that powered the Owner Card. |
| PDF export + shareable link | Generated on demand; link is tokenized and expires after 30 days unless renewed. |

## 7. Pricing

- Owner Verification Card: free, no signup.
- Free Report: free, email signup required (magic link).
- Deep Report: $12 flat per address, one-time. Launch metros only.
- Report refresh: free tier free to refresh; Deep Report refresh $6 after 30 days.

No subscription in v1. Subscription tiers are a v2 experiment.

**Unit economics target:**
- Free Report COGS: ~$0.50–$1.00 per run (ATTOM + criminal API).
- Deep Report COGS: ~$1.50–$2.50 per run (adds scraper compute + city data fetches). Gross margin ~80–85% at $12.

## 8. Architecture

### 8.1 Frontend
- Claude Design for UI scaffolding.
- Single-page site with these routes: `/`, `/report/:id`, `/dashboard`, `/dispute`, `/legal`, `/about`.
- Responsive mobile-first layout.

### 8.2 Backend
- Supabase:
  - Auth (magic-link email)
  - Postgres DB (users, reports, cached lookups, disputes)
  - Edge functions for data-layer orchestration
  - Storage for generated PDFs

### 8.3 Data layer (Supabase edge functions)
- **OwnershipService** → calls ATTOM or DataTree API. Returns owner, parcel, snapshot.
- **CriminalService** → calls consumer-records aggregator (Endato-class; specific vendor TBD after ToS review for consumer/safety use case). Returns criminal + SOR matches keyed on name + DOB/location where available.
- **CourtScraperService** → per-state modules:
  - `wi_ccap_scraper.ts`
  - `il_cook_efile_scraper.ts`
  - `mn_mncis_scraper.ts`
  - Each module implements a common interface: `searchEvictions(name)`, `searchCivil(name)`.
- **ViolationsService** → per-city modules calling municipal open-data APIs.
- **ReportAssembler** → orchestrates, assembles, caches, and renders reports.

### 8.4 Caching
- Results cached per `(address_normalized, tier)` for 30 days in a `cached_lookups` table.
- Cache hits cost us $0; cache misses incur COGS.
- On cache hit, report generation is near-instant. On miss, show a loading state with progress indicators per data source.

### 8.5 Payments
- Stripe Checkout (hosted) for $12 Deep Report.
- Webhook-driven fulfillment: on `checkout.session.completed`, unlock Deep Report for the user and trigger data fetch if not cached.

### 8.6 Resilience
- Scrapers fail constantly (court systems go down, HTML changes). Each scraper has:
  - Exponential backoff
  - Per-source timeout (15s)
  - Graceful degradation: if a source fails, the report is generated with available data and a "Partial report — refresh in 24h for updated data" banner. User is not charged twice on refresh for the missing section.
- Monitoring: Supabase logs + Sentry for errors; scraper-failure alerts to email.

## 9. Data model (Supabase tables)

```
users (Supabase Auth — standard)

addresses
  id pk
  raw_input text
  normalized text  -- standardized (USPS / libpostal)
  lat, lng
  county, state

owners
  id pk
  name text
  type enum('person', 'llc', 'corp', 'trust', 'other')
  address_of_record text
  source enum('attom', 'datatree', 'manual_dispute')
  last_refreshed_at

ownership_links  -- many-to-many addresses <-> owners
  address_id fk
  owner_id fk
  source
  last_verified_at

reports
  id pk
  user_id fk users
  address_id fk
  tier enum('free', 'deep')
  status enum('pending', 'complete', 'partial', 'failed')
  created_at
  expires_at  -- 30 days from creation; after that, refresh required

report_sections
  report_id fk
  section_type enum('owner_card', 'criminal', 'sor', 'property_snapshot', 'eviction', 'civil', 'violations', 'portfolio', 'summary')
  status enum('pending', 'complete', 'failed')
  data jsonb
  source_attribution text

cached_lookups
  cache_key text pk  -- e.g., "attom:123 Main St, Madison, WI"
  response jsonb
  fetched_at
  expires_at

disputes
  id pk
  report_id fk
  record_reference text  -- which specific datapoint
  claim text
  supporting_docs_urls text[]
  status enum('submitted', 'under_review', 'upheld', 'annotated', 'corrected', 'rejected')
  created_at
  resolved_at

payments
  id pk
  user_id fk
  report_id fk
  stripe_session_id text
  amount_cents int
  status enum('pending', 'succeeded', 'failed', 'refunded')
  created_at
```

## 10. Security and compliance

- All database access via Supabase RLS. Users can read only their own reports. Public owner-card endpoint is rate-limited and does not require auth but returns only minimal data.
- Criminal / SOR data is encrypted at rest. PDF reports signed and tokenized; shareable links expire.
- Stripe webhooks verified via signature.
- Scraper user-agents identify the service honestly; we respect robots.txt where it exists on public records portals. We rate-limit aggressively to avoid being classified as abusive.
- Vendor ToS review required before first API contract: we need written confirmation from each consumer-records aggregator that our use case (consumer personal-safety lookup on landlords) is permitted.
- Backup and disaster recovery: daily Supabase backups (standard).

## 11. Metrics for v1

- **Primary:** % of email-gated users who purchase a Deep Report in launch metros.
- **Secondary:** Owner Card → Free Report conversion, Free Report → Deep Report conversion, refresh rate, dispute rate, time-to-report (TTR) for cache miss.
- **Quality:** manual audit of 50 reports from Madison against ground truth (Access Dane + CCAP + city records) before launch.

## 12. Risks and open questions

### Risks
1. **Aggregator ToS rejection.** The main consumer-records APIs may refuse service once they learn the target is landlord lookups. Mitigation: engage vendor sales before committing to one; have a fallback provider list; worst case, build direct scrapers for state criminal records (much more work).
2. **Scraper fragility.** State and city portals change HTML/structure without notice. Mitigation: per-source monitoring with synthetic test cases run daily; dedicated "scraper health" dashboard; graceful degradation.
3. **Cost overruns.** If ATTOM or criminal API pricing is higher than modeled, margins compress fast. Mitigation: aggressive 30-day caching; pre-launch contract negotiation; ability to push Deep Report to $15 if needed.
4. **Landlord legal pushback.** A landlord with resources may attempt a defamation or tortious-interference claim. Mitigation: strict public-records-only sourcing, clear dispute flow, ToS language limiting use, insurance (E&O + media liability) before launch.
5. **FCRA scope creep.** If users start using the tool for screening and a regulator notices, we're in trouble. Mitigation: aggressive in-product disclaimers, no enterprise/landlord pricing tier, block B2B signups, monitor usage patterns for enterprise abuse.

### Open questions (to resolve during implementation planning)
1. Final choice of property-data vendor: ATTOM vs DataTree vs CoreLogic. Depends on pricing, API quality, and ToS.
2. Final choice of consumer-records aggregator. Need ToS review for consumer-safety-lookup use case.
3. PDF rendering approach: server-side in edge function vs client-side from structured data.
4. Final brand name and domain.
5. Whether to require US-resident age verification (18+) at signup.

## 13. Out-of-scope items explicitly parked for v2+

- Crowdsourced reviews (Rate-My-Landlord flavor) — separate legal profile, needs moderation infrastructure.
- Subscription pricing tiers.
- Tenant-initiated screening for landlord consumption (original Option 1 from brainstorming).
- Original crowdsourced tenancy-history concept from the March 2026 `tenant-screening/` exploration — remains parked.
- Expansion markets beyond Madison / Chicago / Minneapolis for the Deep Report tier.
- Mobile app.
- Spanish-language UI.
- Beneficial-owner piercing beyond what's in public records.
- Landlord-facing dashboard.

## 14. Separate from prior tenant-screening work

The existing `tenant-screening/` and `tenant-safe/` folders in this repo contain a prior exploration (March 2026) of a *crowdsourced tenancy-history* product. That product has a different shape, different legal profile, and a different user persona. LandlordLens does not inherit from or depend on that code. The prior work stays parked.
