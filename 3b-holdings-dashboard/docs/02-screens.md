# 3B Holdings Dashboard — Screens

Claude Design: these are the screens to build. Layout and visual details are yours to decide; the contract is **what each screen must contain and how it behaves**.

## Global shell

Left sidebar + top bar + main content area.

**Sidebar items** (in this order):
- Home
- Properties
- Repairs
- Vendors
- Documents
- Settings

**Top bar:** current user name + avatar + logout menu.

**Responsive:** collapse sidebar to a hamburger menu below ~768px. Dashboard must be usable on a phone but is primarily desktop.

## 1. Home / Dashboard

Four widgets. Layout is yours; likely a grid. Each widget is clickable and navigates to its dedicated page.

- **Portfolio Equity.** Total equity across all properties = SUM(`current_estimated_value` − current principal balance) for each property's active mortgage. Show the total prominently; optionally a small per-property breakdown.
- **Expenses this month.** SUM of `expenses.amount` where `expense_date` is in the current calendar month. Show the total and optionally a small bar chart by category or by property.
- **Open Operations.** Count + compact list of repairs where `status IN ('open', 'in_progress')`. Each item shows title, property, how long it's been open.
- **Upcoming Deadlines.** Next 5–10 `deadlines` where `completed = false` and `due_date` is in the next ~90 days, ordered by due date. Each shows title, property, due date, days-until.

## 2. Properties (list)

Table view. Columns:
- Address (primary; click → detail)
- Units (count)
- Current value
- Equity % — (current_estimated_value − balance) / current_estimated_value
- Open repairs (count of open+in_progress)
- Expenses YTD

Search by address. Sort by any column. Add-Property button.

## 3. Property Detail (tabbed page)

Route: `/properties/:id`. Header shows address + actions. Tabs:

- **Overview** — property fields, current value (with "Edit value" inline), equity snapshot, quick stats, recent activity feed (last 10 `activity_log` entries for this property).
- **Units** — list of units with CRUD. Add, edit, delete.
- **Mortgage** — current terms summary, payment log table sorted newest first, amortization schedule (computed client-side from the mortgage row), "Log payment" action. If no mortgage, "Add mortgage" button.
- **Repairs** — repairs filtered to this property. Same columns as the global Repairs page but without the property column.
- **Expenses** — expenses filtered to this property. Category filter. Date range filter. "Export CSV" button that downloads the current filtered view.
- **Documents** — files for this property. Grid or list, category filter, upload button (drag-drop or click).
- **Deadlines** — upcoming (top) and completed (below). "Add deadline" action. Marking a recurring deadline complete should feel seamless and show that the next occurrence has been scheduled.
- **Activity** — `activity_log` rows filtered to `property_id = <this property>`, reverse chronological. Each row: actor, action, entity, timestamp.

## 4. Repairs (global list)

Default view: all repairs where `status IN ('open', 'in_progress')`, newest first. Toggle/filter to include `done` and `cancelled`. Columns: property, unit, title, status, assigned partner, vendor, opened date, cost.

Click a row → inline drawer or separate route to edit the repair and transition status.

## 5. Vendors

Simple list. Columns: name, category, phone, email, notes. Search + category filter. Add/edit via drawer or modal.

## 6. Documents (global)

Cross-portfolio file view. Property filter + category filter. Same actions as the per-property Documents tab (upload, download, delete, rename).

## 7. Settings

- **Partners** list — name, email, date joined. In Phase 1 new partners are added via the Supabase dashboard, not in-app. Still show the list here.
- **Global Activity** — the activity_log, newest first, with filters for actor, entity type, and date range.
- **Profile** — current user's name (editable), email (read-only).
- **Sign out** button.

## Auth screens

- **Login** — email field → send magic link. Confirmation screen says "check your email."
- **Accept invite** — landing after first magic-link click. If `partners` row for the user is missing a name, prompt for it.

## Global conventions

- Dates displayed as `MMM D, YYYY` (e.g., `Apr 18, 2026`).
- Money displayed with thousands separators and 2 decimals, e.g., `$1,234.56`.
- Empty states must be friendly: "No repairs open" with a hint of what to do, not a blank box.
- All destructive actions (delete property, delete mortgage) require confirmation.
- Form validation inline, not after submit.
