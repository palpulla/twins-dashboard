# 3B Holdings Dashboard — Product Spec

## Who uses this

Three partners at 3B Holdings, a real estate partnership. All three have equal admin access. They manage the portfolio remotely — none are tenants or on-site property managers. The dashboard is where they check on the business.

## What problem this solves

Today the owner-side data (mortgage terms, equity, repair history, vendor contacts, purchase documents, tax returns, insurance policies, per-property expenses) is scattered across email, spreadsheets, folders, and memory. When one partner asks "who paid the plumber in March?" or "when does the Oak Ave insurance renew?" there's no single place to look.

This dashboard is that single place for everything the partners own and manage — with a real audit trail so the partnership stays accountable.

Tenant-facing work (communication, maintenance requests, rent collection, lease tracking) lives in RentRedi and is **out of scope**. Do not build tenant-facing views.

## Scope — what Phase 1 includes

- **Properties & units.** CRUD on properties (address, purchase info, current estimated value, notes). CRUD on units under a property (label, bedrooms, bathrooms, sqft).
- **Mortgages.** One or more mortgages per property. Terms (lender, principal, rate, term, start date, monthly payment, escrow flag). Payment log with principal / interest / escrow split and extra-principal field. Auto-computed current balance and amortization schedule.
- **Repairs.** Status flow: open → in progress → done (or cancelled). Per property, optionally per unit. Assign a vendor and/or a partner. Log cost + completed date when done. Marking done auto-creates an `expenses` row in the repairs category.
- **Vendors.** Simple contact list (name, category, phone, email, notes). Categories: plumber, electrician, HVAC, handyman, landscaping, other.
- **Expenses.** Per-property ledger. Categories: mortgage, insurance, tax, utilities, repairs, HOA, other. CSV export.
- **Documents.** Per-property library backed by Supabase Storage. Categories: purchase, tax, insurance, mortgage statement, inspection, receipt, other. Upload / download / delete / rename.
- **Deadlines.** Per-property one-off or recurring deadlines (insurance renewal, tax due, HOA dues). Marking a recurring deadline complete auto-creates the next occurrence.
- **Audit log.** Every create / update / delete writes to `activity_log` via server-side trigger. Viewable per property and globally.

## What Phase 1 deliberately does NOT include

- Any tenant-facing feature.
- Mortgage what-if calculators (refi, extra-principal payoff, projections). Deferred to Phase 2.
- Year-end tax report PDF. Deferred to Phase 2.
- RentRedi integration for rent income. Deferred to Phase 2.
- QuickBooks sync. Out forever.
- Multi-company / multi-tenant support. Out forever.
- Native mobile app. Out forever. (Responsive web only.)
- Rent-income tracking of any kind in Phase 1. The dashboard shows **expenses** only; income is a Phase 2 concern.

## Users & access

- Invite-only.
- Three partners, all with role = `admin` (full read/write on everything).
- Auth via Supabase magic link — no passwords.
- A 4th+ admin can be invited later by any partner.
- Every write operation records actor, action, entity, and a diff to `activity_log`.

## Scale targets

- **Today:** 4 properties, 12 units.
- **5-year target:** 100+ properties. Tables, lists, and search must remain usable at that scale.

## Success criteria

- All 3 partners sign in via magic link and see the same data.
- Adding a property, logging a mortgage payment, opening and completing a repair, uploading a document, and viewing the dashboard all work end-to-end.
- Every write is visible in the Activity view with the correct actor.
- The 4 home dashboard widgets (equity, expenses, open ops, deadlines) reflect real data.

## Tone

Professional, data-friendly, legible. It's a working tool, not a marketing site.
