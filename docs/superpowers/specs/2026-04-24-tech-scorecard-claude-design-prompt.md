# Claude Design Prompt — Tech Scorecard (Twins Garage Doors)

Paste the content below into Claude Design (`https://claude.ai/design/`) to generate the visual design. Then share the result back and I'll rebuild the React components to match it exactly.

---

## Prompt (copy from here)

Design a mobile-first **Tech Scorecard** page for Twins Garage Doors, a residential garage-door service company operating out of Madison, WI. The page lives in an existing React + Tailwind + shadcn/ui app called "Twins Dashboard" (twinsdash.com). The visual language must match the existing main dashboard page (the one with SemiGauge, MetricCard, ComparisonSection, RevenueTrendChart, DateRangePicker, navy + yellow color palette). Do not design it like a sidebar-heavy admin tool. Lead with the feel of a modern owner-operator KPI dashboard.

### Who uses this page

1. **Field technicians** (Maurice, Nicholas, etc.) — log in from their phones between jobs. They are not office workers. They want to see their performance, enter the parts they used, and know what they'll get paid. They do not care about company averages except as a personal benchmark. They should never see parts costs or the underlying commission math. Primary device: phone (375–414px wide). Must be legible in a truck cab.

2. **Admin (Daniel)** and **field supervisor (Charles)** — on desktop. Use the page to drill into any tech's performance, review open modification requests, and flag pricing gaps. They see everything.

### What the page does

Three things, all on one URL (`/tech` for tech viewing own, `/tech/:id` for admin viewing another):

1. **KPI scorecard** — 8 tiles with period-over-period filtering by date range:
   - Revenue (tech's own in the period)
   - Jobs (count)
   - Avg Opportunity ($)
   - Avg Repair ($)
   - Avg Install ($)
   - Closing %
   - Callback Rate (lower is better)
   - Membership Conversion %

   Each tile must show: label, value, and a small indicator comparing the tech's number to the company average (green up-arrow better, red down-arrow worse, gray dash near average). Each tile has a small info icon with a one-sentence tooltip explaining what it measures.

2. **Commission tracker** — per-week view of the tech's jobs with parts-entry status. States: current week with draft jobs (tech is still entering parts), current week with all jobs submitted (waiting on admin payroll), past weeks (locked, read-only). Shows a list of jobs with status badges (Not entered, Entered, Ready, Locked). The primary action for a tech with outstanding work is a big accent-colored button like "Continue entering parts" that jumps to the next draft job. When all jobs are ready, the CTA becomes "Finish Week." Finish is irreversible from the tech's side. After submit, the tracker flips to a **week summary** showing total commission earned and per-job commission amounts (no parts cost, no effective rate), with a "Request Modification" button on each row.

3. **Past paystubs** — last 4 finalized weeks, tap to drill into a read-only breakdown.

### Three workflows to design visually

**A) Tech mobile flow** — Maurice opens the app on his phone while sitting in the truck. Show the full page on a 375px-wide frame:
   - Top: "Your scorecard" + DateRangePicker
   - Disclaimer (one line or banner): "Estimates. Final amounts confirmed when admin runs payroll."
   - Hero action card: big CTA like "Continue entering parts (3 jobs left)" with an arrow, styled with the yellow accent brand color
   - KPIs grid (2 columns on mobile)
   - Commission tracker: stacked job cards with status badge, tap to drill into parts entry
   - Last paystub teaser: "You earned $X last week" row, tap to see more

**B) Parts entry (full-screen on mobile)** — tech is on a job, opens the per-job entry. Show:
   - Job header (number, customer, ticket amount — NOT parts cost)
   - Search field to find pricebook parts (name only, no price visible)
   - Chips of parts already added with quantity
   - "Add custom part" button that opens a sub-form asking for part name, quantity, and short notes. Custom part shows a "Pending price" tag until admin fills it in.
   - Primary CTA: "Mark job ready"
   - Warning line (if the job's HCP notes mention parts the tech hasn't entered): "HCP notes mention springs, cables. Did you use any?"

**C) Admin desktop flow** — Daniel opens the scorecard from the sidebar. He has not picked a tech yet. Show on a desktop frame (1280px+):
   - Header "Tech scorecard" + date range
   - Hero card with a prominent dropdown: "Select a technician…" — this IS the primary action until a tech is chosen
   - Below, until a tech is picked, a placeholder card saying what will appear
   - After picking a tech, the full scorecard renders below (same 8 KPIs, tracker, paystubs), with an admin-only card showing "Tech Requests — N open" linking to `/admin/tech-requests`
   - The commission tracker in admin mode is read-only with a button "Open in payroll view" that deep-links into the existing payroll workflow
   - At the top-right of the tracker, a "Switch tech" dropdown so the admin can jump between techs without returning to the picker

### Admin queue page (`/admin/tech-requests`)

Secondary screen. Design a desktop and mobile layout:

- Header "Tech Requests" + small count badge of open items
- Filter bar: status (open / resolved / all), type (price needed / modification / all), tech name
- List of request cards. Two card variants:
  - **Price needed** — tech name, job number, the custom part name they submitted with notes, and an inline form (part name + unit price + "Mark priced" button).
  - **Modification** — tech name, job number, reason chips (Forgot parts, Wrong part, Wrong quantity, Wrong job tag, Customer dispute, Other), free-text details. Actions: "Open in payroll editor" (link), then a required resolution note textarea + "Mark resolved" and "Reject" buttons.

### Explicit visual constraints

- **Do not** render anything that looks like a parts cost, a per-part price, a parts-total dollar figure, or a per-job net subtotal on any tech-facing view. Tech only ever sees their earned commission, and only after week summary.
- **Do not** include line charts or bar charts on this page. No trends — those live on the main dashboard.
- **Do** show a period disclaimer wherever the tech sees their numbers: "Estimates. Final amounts confirmed when admin runs payroll. Parts you have not entered will skew the math."
- **Do** use the yellow accent for primary CTAs (Continue entering parts, Finish Week, Select technician). Use navy for secondary and muted for tertiary.
- **Do** make the finish-week action feel final — confirmation dialog with plain language about what happens.
- **Mobile first.** All interactive targets at least 44px tall. Grids collapse to single-column under 640px except KPIs which can stay 2-column for density. Dropdowns open full-screen on mobile.
- Typography: page titles `text-2xl font-bold text-primary`, section titles `text-base font-semibold`, body `text-sm`, muted labels `text-[11px] uppercase tracking-wide text-muted-foreground`.

### What to deliver

- Mobile tech view (375px): full vertical flow with all three sections
- Mobile parts entry sheet (375px)
- Mobile finish-week confirmation modal and post-finish week summary
- Desktop admin pre-pick state (1280px) with the prominent picker
- Desktop admin post-pick state (1280px)
- Desktop admin tech-requests queue

Use realistic data: three or four job rows with varied customer names and ticket amounts, one pending-price custom part, one open modification request.

Match the existing navy + yellow brand. Keep the aesthetic closer to a modern owner-operator dashboard than to a traditional CRM form.

---

## After Claude Design produces mockups

Share the generated screens or code back in the chat. I will:

1. Adopt any reusable components/classes it generates.
2. Rebuild `TechnicianView`, `TechScorecardKPIs`, `CommissionTracker`, `WeekSummary`, and `TechRequests` to match pixel-for-pixel.
3. Update the spec and plan docs to reflect the final visual direction.
