# GHL Booking Rate Report — Design

**Status:** Draft, ready for plan
**Author:** Claude (with Daniel)
**Date:** 2026-05-22
**Repo:** `twins-dash` (palpulla/twins-dash)
**Live page:** `/marketing-roi` (`twins-dash/src/pages/MarketingSourceROI.tsx`)

## Problem

Twins runs lead intake through two GoHighLevel accounts — **Dunzo** (the marketing agency's GHL) and **Twins' own GHL**. The dashboard pulls GHL *contacts* nightly into `public.ghl_contacts` and phone-matches each one to a Home Service Pro (HCP) job, but it never reports the obvious question an owner-operator asks: **of the leads GHL captured, what fraction turned into a real booked job?**

The existing `GhlAttributionPanel` on `/marketing-roi` shows contact volume and a raw "matched to HCP" count, but:

1. There is no **booking rate** as a first-class metric.
2. Numbers are **combined across accounts** — Dunzo and Twins' GHL cannot be compared.
3. Only **one account is wired** today. The sync function already supports multiple accounts; the second account's credentials simply have not been added.

## Goal

Add a **booking rate report** to the existing GHL attribution panel: a combined booking rate plus a per-account breakdown (Dunzo vs Twins), using data already in Supabase. No new GHL API surface, no schema change.

## Definitions

- **Lead** — one row in `public.ghl_contacts`, i.e. one GHL contact, bucketed by `contact_created_at`.
- **Booked** — that contact has a non-null `matched_hcp_job_id`. The existing GHL matcher sets this when the contact's phone matches an HCP job created within a 30-day forward window.
- **Booking rate** — `booked ÷ leads` for a given account and date range.

Today's baseline (Dunzo, all data): 184 booked of 767 leads ≈ **24%**.

## Non-goals

- **No GHL appointments or opportunities sync.** "Booked" means a real HCP job, not a GHL calendar event or pipeline stage. The HCP job is the source of truth for a scheduled job.
- **No booking-rate trend chart.** v1 reports the rate for the selected date range only. A weekly time series can be a later phase.
- **No admin-editable account labels.** Two accounts that change almost never do not justify a DB table + RLS + admin UI. Labels live in a small static config file.
- **No KPI math changes.** Contact and match counts already drive the channel-scorecard attribution overlay; that aggregation is untouched.
- **No >30-day history backfill for the new account.** The sync's first run for a new account pulls 30 days; deeper history is out of scope.
- **No new auth/role logic.** The page audience is owner-operator only, unchanged.

## Architecture

### Approach

Extend the existing `GhlAttributionPanel` rather than build a new panel or a DB-backed accounts table. Rejected alternatives:

- **`ghl_accounts` DB table + dedicated panel** — a migration, RLS, and a new panel for two rarely-changing accounts is over-engineering (YAGNI).
- **Booking rate as a top-line channel card** — booking rate is a GHL funnel/quality metric, not revenue-per-channel; placing it among the channel scorecards muddies their meaning.

### Data flow

```
nightly sync-ghl-contacts  →  public.ghl_contacts (one row per contact, keyed by ghl_location_id)
                           →  GHL matcher sets matched_hcp_job_id (30-day forward phone match)
useGhlSummary              →  aggregates contacts + matched, grouped by ghl_location_id
GhlAttributionPanel        →  renders combined booking rate + per-account table
```

The report is account-agnostic: it groups by whatever `ghl_location_id` values appear in `ghl_contacts`. Twins' account surfaces automatically once its sync runs — no code change needed beyond adding its label.

### Components

**New — `src/lib/ghl/ghl-accounts.ts`**
A static map from `ghl_location_id` to a display label, plus a `ghlAccountLabel(locationId)` helper.
- `iRUlbIBg7PzSfLrPiR2j` → `"Dunzo"`.
- Twins' own location ID is added here once provided.
- Unknown IDs fall back to a stable truncated-ID label (e.g. `Account PiR2j`) so an unconfigured account never breaks the panel.

**Modify — `src/hooks/use-ghl-summary.ts`**
The hook already fetches every `ghl_contacts` row in range. Extend its return value with:
- `perAccount: GhlAccountSummary[]` — one entry per `ghl_location_id`, each `{ locationId, label, contacts, booked, bookingRate }`.
- `bookingRate: number` — combined `booked ÷ contacts` across all accounts.

Existing fields (`totalContacts`, `matchedToHcp`, `withSource`, `channelMapped`, `topSources`) are kept so the current panel content and any other consumers stay valid.

**Modify — `src/components/marketing-roi/GhlAttributionPanel.tsx`**
- Add a **combined booking-rate** headline stat.
- Add a **per-account table**: columns `Account · Leads · Booked · Rate`, one row per account, plus a combined total row.
- Keep the **top dialed numbers / sources** list.
- Demote the existing `Source attributed` / `Channel-mapped` stats into a single small caption line, so the panel gains the booking table without becoming busy.

### Maturation caveat

The matcher's 30-day forward window means a recently created contact can still convert. So a cohort from the last 30 days reads artificially low. When the selected date range ends within 30 days of today, the panel shows a caption: *"N contacts created in the last 30 days are still inside the 30-day match window and may yet convert."* No "mature-only" toggle in v1 — the caption is enough to keep the number honest without adding controls.

### Error / empty states

- **No contacts in range** — existing "No GHL contacts in this period" message is reused.
- **One account only** — the per-account table simply shows one row plus the total; no special case.
- **Unknown `ghl_location_id`** — falls back to the truncated-ID label; the row still renders.
- **Zero leads for an account** — booking rate renders as `—`, not `0%` or a divide-by-zero.

## Testing

- `use-ghl-summary` test: per-account aggregation, combined booking rate, zero-lead guard.
- `GhlAttributionPanel` test: renders one row per account, a combined total row, and the maturation caption when the range is recent.
- Existing tests in `src/components/marketing-roi/__tests__/` and `src/hooks/__tests__/` must stay green.

## Prerequisite to see both accounts (ops, not code)

The report works with one account immediately. To make Twins' own GHL appear:

1. Add Supabase secrets `GHL_API_KEY_2` and `GHL_LOCATION_ID_2` (the sync function auto-discovers numbered account pairs).
2. Add Twins' location ID and label to `src/lib/ghl/ghl-accounts.ts`.

On the next nightly run, `sync-ghl-contacts` pulls the new account's last 30 days and the panel shows both accounts. Until then it cleanly shows Dunzo only.

## Open questions

None blocking. Twins' GHL API key, location ID, and preferred display label are needed only for the ops step above, not for implementation.
