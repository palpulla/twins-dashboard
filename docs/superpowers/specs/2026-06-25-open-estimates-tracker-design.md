# Open Estimates Follow-Up Tracker — Design Spec

**Date:** 2026-06-25
**Owner:** Daniel (Twins Garage Doors)
**Status:** Approved design, pending implementation plan

## Purpose

Give the CSR a single Google Sheet listing every customer with an **open** Housecall
Pro estimate (from 1/1/2026 onward) so they can systematically follow up and convert
quotes into booked jobs. New open estimates appear automatically each day; resolved
ones move out of the way without losing the CSR's notes.

## Source of truth

All estimate data already lives in the **jwrpj** Supabase `jobs` table, kept fresh by
the existing `sync-hcp-estimates` edge function (daily HCP pull). We read from there —
we do **not** call the HCP API directly, and we do **not** re-implement the
"open estimate" definition anywhere new.

**Open estimate** is defined as a `jobs` row where:
- `job_type = 'Estimate'`, and
- `estimate_status = 'open'` (the canonical field; `sold` and `declined` are excluded), and
- estimate creation date `>= 2026-01-01`.

Customer linkage is via `hcp_data->>'customer'` (id, name, phone). Quote amount is the
estimate's `total_amount` (stored in `hcp_data` / `revenue_amount`). Assigned tech comes
from the estimate's assigned employee.

> Implementation note: confirm the exact estimate-creation-date column in `jobs` during
> planning (e.g. `created_at` vs an HCP `created_at` inside `hcp_data`). Scope is by
> estimate creation date, not scheduled date.

## Architecture

A new daily Supabase job:

1. **`pg_cron`** fires shortly after the nightly `auto-sync-jobs` run, so the sheet
   reflects the freshest HCP data each morning.
2. A new edge function (working name **`sync-estimate-tracker`**) queries open estimates,
   aggregates them **per customer**, and writes the result into the Google Sheet via a
   **Google service account** (JWT-signed Sheets API call from Deno).
3. The Sheets write is an **upsert keyed by HCP customer ID** (stored in a hidden column),
   never a full overwrite (see "Sync rules" below).

**Rejected alternatives:**
- *Google Apps Script calling HCP directly* — would duplicate the open-estimate logic
  outside the canonical code and store secrets in the sheet.
- *Zapier/Make connector* — fragile, cannot do the per-customer rollup or Closed-tab
  move, adds a paid dependency.

## Target sheet

Google Sheet ID: `1OK1BsJ7MvPa7ZR6b724duMxRomHeYMwG1oiMjdSL0nE`
(https://docs.google.com/spreadsheets/d/1OK1BsJ7MvPa7ZR6b724duMxRomHeYMwG1oiMjdSL0nE/edit)

Two tabs:
- **Follow-Up** — customers with ≥1 open estimate. The CSR's working list.
- **Closed** — customers whose estimates are all resolved (booked/declined), parked with
  outcome + date and the CSR's notes preserved.

## Row model

**One row per customer.** When a customer has multiple open estimates, they still occupy a
single row; the individual estimates are listed in the **Estimate Details** column.

## Columns

### Auto-filled (sync-managed; CSR should not edit)
| Column | Source / format |
|---|---|
| Customer | Customer full name |
| Phone | Customer phone (for the CSR to call) |
| # Open Est | Count of the customer's open estimates |
| Total Quoted | Sum of open-estimate quote amounts, full dollars (e.g. `$3,450`) |
| Estimate Details | One entry per open estimate: `EST-1042 · $1,200 · 5/3` joined by `; ` |
| Assigned Tech | Tech assigned to the estimate(s) |
| Oldest Est Date | Creation date of the customer's oldest open estimate (aging signal) |
| _HCP Customer ID_ | Hidden. Upsert key. |

### CSR-filled (manual; dropdowns + free text)
| Column | Type / options |
|---|---|
| Follow-Up Status | Dropdown: New · Attempted · Reached · Callback set · No answer · Do not contact |
| Booked? | Dropdown: No · Yes · Partial |
| Last Follow-Up | Date |
| Next Follow-Up | Date |
| Notes | Free text |
| Remove | Dropdown: — · Remove (CSR flag to suppress a row) |

Currency renders as full dollar amounts, no `$Xk` shorthand.

## Sync rules (the load-bearing behavior)

On every daily run, for each customer with open estimates:

- **Existing row (matched by HCP Customer ID):** refresh only the auto-filled columns.
  **Never touch the CSR-filled columns.**
- **New customer (no matching row):** append a new row at the bottom of Follow-Up with
  auto-filled columns populated and CSR columns blank (status defaults to `New`).
- **Customer with no remaining open estimates** (all became sold/declined): move the
  entire row — CSR notes intact — to the **Closed** tab, stamped with the outcome
  (Booked / Declined / Mixed) and the date moved.
- **`Remove` flagged by CSR:** the row is suppressed from the active follow-up flow
  (kept but visually/positionally deprioritized; exact mechanism decided in the plan —
  e.g. moved to the bottom or to Closed with reason `Removed by CSR`). The sync must not
  resurrect a removed customer.

The upsert must be resilient to the CSR sorting/filtering rows, because matching is by the
hidden HCP Customer ID, not by row position.

## One-time setup (manual, unavoidable)

Daniel shares the target Google Sheet as **Editor** with the service-account email
(generated during implementation). Google requires the file owner to grant a service
account access; this is the only manual step. Everything afterward is automatic.

Implementation will also store the Google service-account credentials in Supabase secrets
(vault), alongside the existing HCP and Google keys.

## Out of scope (YAGNI)

- No per-estimate rows (one row per customer was chosen deliberately).
- No notifications/alerts to Daniel (consistent with "no pings" preference).
- No two-way sync back into HCP — the sheet is a CSR workspace, not a system of record.
- No branded UI; this lives entirely in Google Sheets.

## Open items to resolve in the plan

1. Exact estimate-creation-date column in `jobs`.
2. Precise mechanism for the `Remove` flag (bottom of sheet vs Closed tab).
3. Service-account provisioning + Sheets API enablement.
4. Cron timing relative to `auto-sync-jobs` (must run after it completes).
