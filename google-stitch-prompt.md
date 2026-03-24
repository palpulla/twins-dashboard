# Twins Garage Doors — KPI Dashboard

Build a multi-role business intelligence dashboard for a garage door installation & repair company (Twins Garage Doors, Madison WI). The app uses **Next.js 16 (App Router), React 19, TypeScript, Tailwind CSS 4, Recharts** for charts, and **Supabase** for the backend.

---

## Brand & Styling

| Token | Value |
|-------|-------|
| Primary (navy) | `#012650` |
| Accent (yellow) | `#FBBC03` |
| Accent alt | `#FFBA41` |
| Dark text | `#3B445C` |
| Background | `#F5F6FA` |
| Surface / cards | `#FFFFFF` |
| Success | `#22C55E` |
| Warning | `#F59E0B` |
| Danger | `#EF4444` |
| Font (body) | Inter, system-ui, sans-serif |
| Font (mono/numbers) | JetBrains Mono, ui-monospace, monospace |

- Cards have white background, subtle shadow, rounded corners
- KPI status indicator: green when value >= 100% of target, amber at >= 90%, red below 90%. Inverted for "lower is better" metrics (e.g. Callback Rate)
- Sidebar is dark navy (`#012650`), active link highlighted with yellow accent (`#FBBC03`)
- All monetary values use monospace font

---

## User Roles & Access

| Role | Label | Sees |
|------|-------|------|
| `owner` | Owner / CEO | All pages: Company Dashboard, Technician, CSR, Marketing, Admin |
| `manager` | Manager | Dashboard, Technician (own team), CSR, Marketing |
| `technician` | Technician | Own Technician Scorecard only |
| `csr` | CSR | Own CSR Dashboard only |

Role hierarchy: owner (2) > manager (1) > technician/csr (0).

### Demo Users
| Name | Role | Commission Tier |
|------|------|----------------|
| Mike Johnson | Owner/CEO | — |
| Sarah Williams | Manager | — |
| Jake Martinez | Technician | Tier 2 (18%) |
| Ryan Cooper | Technician | Tier 1 (16%) |
| Marcus Thompson | Technician | Tier 3 (20%) |
| Emma Davis | CSR | — |

---

## Layout Structure

### Shell
- **Sidebar** (left): Fixed, dark navy background. Collapsible (64px collapsed / 256px expanded). Shows logo "TG" in yellow square + "Twins Garage Doors" text. Navigation links filtered by role. User avatar + name at bottom.
- **Main content area**: Scrollable, light gray background (`#F5F6FA`). Each page has a Header component at top with title, subtitle, and optional date range picker.
- **Mobile**: Bottom navigation bar replaces sidebar.

### Navigation Items
| Label | Route | Visible to |
|-------|-------|-----------|
| Dashboard | `/dashboard` | owner, manager |
| Technician | `/dashboard/technician` | owner, manager, technician |
| CSR | `/dashboard/csr` | owner, manager, csr |
| Marketing | `/dashboard/marketing` | owner, manager |
| Admin | `/dashboard/admin` | owner only |

---

## Pages — Detailed Layout

### 1. Company Dashboard (`/dashboard`)
**For:** Owner, Manager

**Header:** "Company Dashboard" / "Twins Garage Doors — Madison, WI" with date range picker

**Section A — Financial Summary Cards** (5 cards in a row, responsive grid)
| Card | Format | Color accent |
|------|--------|-------------|
| Total Revenue | Currency | Navy |
| Total Jobs | Count | Navy |
| Parts Cost | Currency | Amber |
| Commission Payouts | Currency | Red |
| Gross Profit | Currency | Green |

Each card: label (small uppercase), large animated number below.

**Section B — Company-Wide KPIs**
Heading: "Company-Wide KPIs"
Grid of KPI cards (see KPI Definitions below). Each card shows: name, large value, target indicator (color-coded), sparkline (7-day trend), and % change from previous period.

**Section C — Monthly Revenue vs Parts Cost Chart**
Recharts bar/line combo chart. Last 6 months. Bars = revenue (navy), line = parts cost (amber). X-axis = month names.

**Section D — Technician Leaderboard Table**
Table columns: Rank, Technician Name, Jobs Completed, Total Revenue, Avg Ticket, Commission Earned, Conversion Rate.
Rows are clickable — navigate to `/dashboard/technician/[id]`.

---

### 2. Technician Scorecard (`/dashboard/technician`)
**For:** Technician (own data), Owner/Manager (any tech)

**Header:** "Technician Scorecard" / tech's name

**Section A — Profile Header**
- Large avatar circle (initials, navy background)
- Name (large bold)
- Badge: "Tier X — Y% Commission" (info variant)

**Section B — KPI Grid**
All 11 KPIs filtered to this technician's jobs. Same card layout as company dashboard.

**Section C — Job History Table**
Columns: Date, Customer, Job Type, Status (badge), Revenue, Parts Cost, Net Revenue, Protection Plan (checkmark or dash).
Filterable by job type and status.

---

### 3. Technician Profile (`/dashboard/technician/[id]`)
Same layout as Technician Scorecard but for a specific technician (accessed by owner/manager clicking from leaderboard).

---

### 4. CSR Dashboard (`/dashboard/csr`)
**For:** CSR (own data), Owner/Manager

**Header:** "CSR Dashboard" / CSR's name

**Section A — Profile Header**
Avatar circle + name + "Customer Service Representative" badge.

**Section B — KPI Cards** (3 cards)
| KPI | Target | Format |
|-----|--------|--------|
| Booking Rate | 70% | Percentage |
| Appointments Booked | 50 | Count |
| Total Calls | 60 | Count |

**Section C — Call Source Attribution**
Card with grid of mini-cards (2-4 per row). Each mini-card shows:
- Channel label (e.g. "Google Ads", "Google LSA", "Referral")
- Total calls (large number)
- "X booked (Y%)" subtitle

**Section D — Call Log Table**
Columns: Date/Time, Caller Name, Phone, Source/Channel, Duration (formatted m:ss), Outcome (badge: booked=green, not_booked=amber, voicemail=gray), Notes.

---

### 5. Marketing Dashboard (`/dashboard/marketing`)
**For:** Owner, Manager

**Header:** "Marketing Dashboard" / "Channel performance & ROI"

**Section A — KPI Cards** (4 cards)
| KPI | Target | Format | Notes |
|-----|--------|--------|-------|
| Total Leads | 200 | Count | |
| Booked Appointments | 150 | Count | |
| Total Ad Spend | $15,000 | Currency | |
| Avg. Cost per Acquisition | $100 | Currency | Inverted (lower=better) |

**Section B — Ad Spend vs Revenue Chart**
Recharts grouped bar chart. X-axis = marketing channel. Two bars per channel: ad spend (amber) and estimated revenue (navy). Only shows channels with spend > 0.

**Section C — Channel Breakdown Table**
Card with title "Channel Breakdown".
Columns: Channel, Leads, Booked, Spend, CPA, Est. Revenue, ROI (badge: green if positive, red if negative).

---

### 6. Admin Panel (`/dashboard/admin`)
**For:** Owner only

**Header:** "Admin Panel" / "System configuration & management" (no date picker)

Grid of 4 clickable cards (2 columns):

| Card | Description | Route |
|------|------------|-------|
| User Management | Add/remove team members, assign roles, assign techs to managers | `/dashboard/admin/users` |
| Commission Config | Set/change commission tiers, update manager bonus rules | `/dashboard/admin/commissions` |
| KPI Definitions | Add new KPIs, edit formulas, set targets, activate/deactivate | `/dashboard/admin/kpis` |
| Integration Health | Monitor webhook status, API connections, error rates | `/dashboard/admin/integrations` |

Each card has an icon (left), title + description (right). Hover shadow effect.

---

## Data Models (Database Schema)

### users
```
id: UUID (PK)
email: TEXT (unique)
full_name: TEXT
role: 'technician' | 'csr' | 'manager' | 'owner'
avatar_url: TEXT (nullable)
manager_id: UUID (FK → users, nullable) — links technician to their manager
is_active: BOOLEAN
created_at, updated_at: TIMESTAMPTZ
```

### commission_tiers
```
id: UUID (PK)
user_id: UUID (FK → users)
tier_level: INTEGER (1, 2, or 3)
rate: NUMERIC (0.16, 0.18, or 0.20)
effective_date: DATE
created_at: TIMESTAMPTZ
```

### customers
```
id: UUID (PK)
hcp_id: TEXT (unique) — HousecallPro external ID
name, email, phone, address: TEXT
created_at, updated_at: TIMESTAMPTZ
```

### jobs
```
id: UUID (PK)
hcp_id: TEXT (unique)
customer_id: UUID (FK → customers, nullable)
technician_id: UUID (FK → users, nullable)
job_type: TEXT — one of: 'Door Install', 'Repair', 'Opener Install', 'Opener + Repair', 'Door + Opener Install', 'Service Call', 'Maintenance Visit', 'Warranty Call'
status: TEXT — 'created' | 'scheduled' | 'completed' | 'canceled'
scheduled_at: TIMESTAMPTZ (nullable)
completed_at: TIMESTAMPTZ (nullable)
revenue: NUMERIC
parts_cost: NUMERIC
parts_cost_override: NUMERIC (nullable) — manual override for parts cost
protection_plan_sold: BOOLEAN
created_at, updated_at: TIMESTAMPTZ
```

### invoices
```
id: UUID (PK)
hcp_id: TEXT (unique)
job_id: UUID (FK → jobs, nullable)
customer_id: UUID (FK → customers, nullable)
amount: NUMERIC
status: TEXT — 'created' | 'paid' | 'voided' etc.
paid_at: TIMESTAMPTZ (nullable)
created_at, updated_at: TIMESTAMPTZ
```

### commission_records
```
id: UUID (PK)
job_id: UUID (FK → jobs)
technician_id: UUID (FK → users)
gross_revenue: NUMERIC
parts_cost: NUMERIC
net_revenue: NUMERIC
tier_rate: NUMERIC
commission_amount: NUMERIC — technician's commission
manager_id: UUID (FK → users, nullable)
manager_override: NUMERIC — 2% of net revenue
manager_bonus: NUMERIC — tiered bonus per job
created_at: TIMESTAMPTZ
```

### call_records
```
id: UUID (PK)
caller_name, caller_phone: TEXT (nullable)
source: TEXT — marketing source
channel: TEXT — marketing channel
duration_seconds: INTEGER
outcome: TEXT — 'booked' | 'not_booked' | 'voicemail'
notes: TEXT (nullable)
csr_id: UUID (FK → users, nullable)
ghl_agency: TEXT (nullable) — 'agency_a' | 'agency_b'
created_at: TIMESTAMPTZ
```

### marketing_spend
```
id: UUID (PK)
channel: TEXT — 'google_ads' | 'google_lsa' | 'meta_ads'
campaign: TEXT (nullable)
spend, impressions, clicks, conversions: NUMERIC/INTEGER
date: DATE
created_at: TIMESTAMPTZ
```

### reviews
```
id: UUID (PK)
google_review_id: TEXT (nullable)
reviewer_name: TEXT
rating: INTEGER (1-5)
review_text: TEXT (nullable)
technician_id: UUID (FK → users, nullable)
review_date: DATE
created_at: TIMESTAMPTZ
```

### kpi_definitions
```
id: UUID (PK)
name, description: TEXT
formula: TEXT — references a calculation function
data_source: TEXT — 'jobs' | 'commission_records' | 'reviews'
target: NUMERIC
display_format: 'currency' | 'percentage' | 'count'
is_active: BOOLEAN
inverted_status: BOOLEAN — true = lower is better
sort_order: INTEGER
created_at, updated_at: TIMESTAMPTZ
```

---

## KPI Definitions (11 Metrics)

| # | ID | Name | Description | Target | Format | Inverted? |
|---|-----|------|-------------|--------|--------|-----------|
| 1 | avg_ticket | Average Ticket | Revenue from completed opportunity jobs / count (exclude warranty, no-charge) | $500 | Currency | No |
| 2 | avg_opportunity | Average Opportunity | Revenue from ALL completed jobs / total completed jobs | $400 | Currency | No |
| 3 | conversion_rate | Conversion Rate | Jobs with invoice > $0 / total opportunities x 100 | 75% | Percentage | No |
| 4 | avg_repair_ticket | Avg Repair Ticket | Revenue from repair/service jobs / count | $350 | Currency | No |
| 5 | avg_install_ticket | Avg Install Ticket | Revenue from installation jobs / count | $2,000 | Currency | No |
| 6 | new_doors_installed | New Doors Installed | Count of completed "Door Install" or "Door + Opener Install" jobs | 10 | Count | No |
| 7 | total_opportunities | Total Opportunities | Count of all jobs assigned in period | 40 | Count | No |
| 8 | five_star_reviews | 5-Star Google Reviews | Count of 5-star reviews attributed to tech | 5 | Count | No |
| 9 | protection_plan_sales | Protection Plan Sales | Count of jobs where protection_plan_sold = true | 5 | Count | No |
| 10 | commission | Commission | Total commission earned in period | $5,000 | Currency | No |
| 11 | callback_rate | Callback Rate | Warranty/callback jobs / total completed jobs x 100 | 5% | Percentage | **Yes** (lower is better) |

### KPI Card Component
Each KPI card displays:
- **Name** (small, uppercase label)
- **Value** (large, animated number in the appropriate format)
- **Target** indicator: colored dot or bar (green/amber/red based on % of target)
- **Sparkline** (tiny line chart, 7 data points showing recent trend)
- **% change** vs previous period (optional, with up/down arrow)

---

## Commission Engine

### Technician Commission
```
Net Revenue = Invoice Total - Parts Cost (or Parts Cost Override if set)
Tech Commission = Net Revenue x Tier Rate
```

Tier rates:
| Tier | Rate |
|------|------|
| 1 | 16% |
| 2 | 18% |
| 3 | 20% |

### Manager Compensation (per job by their technicians)
```
Manager Override = Net Revenue x 2%

Manager Bonus (based on net revenue of individual job):
  < $400  → $0
  $400-499 → $20
  $500-599 → $30
  $600-699 → $40
  $700+    → $50
  (Pattern: $20 base at $400, +$10 per additional $100)
```

---

## Job Types & Categories

| Job Type | Category |
|----------|----------|
| Door Install | Installation |
| Opener Install | Installation |
| Door + Opener Install | Installation |
| Repair | Repair |
| Service Call | Repair |
| Opener + Repair | Repair |
| Maintenance Visit | Maintenance |
| Warranty Call | Warranty |

Revenue ranges for seed data:
| Job Type | Revenue | Parts Cost |
|----------|---------|-----------|
| Door Install | $1,800–4,500 | $600–1,500 |
| Door + Opener Install | $2,500–5,500 | $900–2,000 |
| Opener Install | $400–900 | $150–350 |
| Repair / Service Call | $150–800 | $30–200 |
| Opener + Repair | $300–1,200 | $100–400 |
| Maintenance Visit | $100–250 | $10–50 |
| Warranty Call | $0 | $0 |

---

## Marketing Channels

| ID | Display Label |
|----|--------------|
| google_ads | Google Ads |
| google_lsa | Google LSA |
| meta_ads | Meta Ads |
| website_contact_form | Website Contact Form |
| website_chat | Website Chat |
| organic | Organic / Direct |
| referral | Referral |

---

## UI Components Inventory

### Layout
- **Sidebar**: Fixed left nav, navy background, collapsible, role-filtered links, user info at bottom
- **Header**: Page title + subtitle + optional date range picker + optional action buttons
- **MobileNav**: Bottom tab bar for mobile screens

### KPI Components
- **KpiGrid**: Responsive grid of KPI cards (auto-fills 2-4 columns)
- **KpiCard**: Name, animated value, target status color, sparkline, % change
- **AnimatedNumber**: Counter that animates from 0 to target value on mount. Formats as currency ($X,XXX), percentage (XX.X%), or count (X,XXX)
- **Sparkline**: Tiny SVG line chart (no axes, just the trend line)

### Charts (Recharts)
- **RevenueChart**: Bar chart (revenue) + line overlay (parts cost). Monthly X-axis, last 6 months
- **ChannelChart**: Grouped bar chart comparing spend vs revenue by marketing channel
- **TrendChart**: Simple line chart for time series data

### Tables
- **DataTable**: Generic reusable table. Props: columns (key, header, render function), data array, keyExtractor, emptyMessage. Supports custom cell rendering
- **LeaderboardTable**: Ranked list of technicians with stats. Clickable rows
- **JobHistoryTable**: Job records with type badges, status badges, revenue columns
- **CallLogTable**: Call records with duration formatting, outcome badges, source labels

### UI Primitives
- **Button**: Variants: primary (navy bg, white text), secondary (outlined), ghost (no bg), danger (red). Sizes: sm, md, lg
- **Card / CardHeader / CardTitle / CardContent**: White surface, rounded corners, subtle shadow
- **Badge**: Variants: success (green), warning (amber), danger (red), info (blue), default (gray). Small pill-shaped labels
- **DateRangePicker**: Dropdown with preset options (Today, This Week, Last Week, This Month, Last Month, This Quarter, Last Quarter, This Year, Last Year, All Time) + custom date range with calendar
- **Modal**: Overlay dialog with backdrop, title, content, action buttons
- **Skeleton**: Loading placeholder with pulse animation (rectangular and circular variants)

---

## Date Range Filtering

The dashboard has a global date range filter that affects all KPI calculations and data tables. Presets:
- Today, Yesterday
- This Week, Last Week
- This Month, Last Month
- This Quarter, Last Quarter
- This Year, Last Year
- All Time
- Custom (date picker)

When a date range changes, all KPIs recalculate, charts update, and tables filter accordingly.

---

## External Integrations (Context Only)

The dashboard pulls data from these external systems — you don't need to build the integrations, just understand the data flow:

1. **HousecallPro** — Field service management. Sends webhooks for: customer.*, job.*, invoice.*, estimate.*, lead.* events. This is where jobs, customers, and invoices come from.
2. **GoHighLevel** (2 agencies) — CRM/call tracking. Provides call records and SMS/email data.
3. **Google Ads API** — Campaign spend, impressions, clicks, conversions.
4. **Google Business Profile API** — Review tracking and star ratings.

---

## Sample Data for Prototyping

### Sample Job Record
```json
{
  "id": "job-001",
  "customer_id": "cust-001",
  "technician_id": "user-tech-001",
  "job_type": "Door Install",
  "status": "completed",
  "scheduled_at": "2026-03-15T09:00:00Z",
  "completed_at": "2026-03-15T12:30:00Z",
  "revenue": 3200,
  "parts_cost": 950,
  "parts_cost_override": null,
  "protection_plan_sold": true
}
```

### Sample Commission Record
```json
{
  "id": "comm-001",
  "job_id": "job-001",
  "technician_id": "user-tech-001",
  "gross_revenue": 3200,
  "parts_cost": 950,
  "net_revenue": 2250,
  "tier_rate": 0.18,
  "commission_amount": 405,
  "manager_id": "user-mgr-001",
  "manager_override": 45,
  "manager_bonus": 50
}
```

### Sample Call Record
```json
{
  "id": "call-001",
  "caller_name": "John Smith",
  "caller_phone": "(608) 555-1234",
  "source": "google_ads",
  "channel": "google_ads",
  "duration_seconds": 245,
  "outcome": "booked",
  "notes": "Appointment scheduled",
  "csr_id": "user-csr-001",
  "ghl_agency": "agency_a"
}
```

### Sample Marketing Spend Record
```json
{
  "id": "spend-001",
  "channel": "google_ads",
  "campaign": "Garage Door Repair Madison",
  "spend": 1200,
  "impressions": 18500,
  "clicks": 450,
  "conversions": 22,
  "date": "2026-03-01"
}
```

---

## Financial Summary Formulas (Company Dashboard)

```
Total Revenue = SUM(revenue) for completed jobs in date range
Total Parts = SUM(parts_cost_override ?? parts_cost) for completed jobs in date range
Commission Payouts = SUM(commission_amount + manager_override + manager_bonus) for records in date range
Gross Profit = Total Revenue - Total Parts - Commission Payouts
```

---

## Key Behaviors

1. **Auto-login in demo mode**: If no real Supabase credentials, auto-login as CEO (owner)
2. **Role-based redirects**: Technicians accessing `/dashboard` get redirected to `/dashboard/technician`. CSRs get redirected to `/dashboard/csr`
3. **Technician leaderboard rows are clickable** — navigate to individual tech profile
4. **KPI values animate on load** — numbers count up from 0
5. **Date range is global** — changing it updates all data on the current page
6. **Sidebar collapse persists** — state managed in client store
7. **All tables support empty states** with a message like "No data available"
8. **Badge variants** for job status: completed=green, scheduled=info/blue, canceled=danger/red
9. **Duration formatting**: seconds → "Xm Ys" (e.g. 245s → "4m 5s")
10. **Phone formatting**: raw digits → "(XXX) XXX-XXXX"
