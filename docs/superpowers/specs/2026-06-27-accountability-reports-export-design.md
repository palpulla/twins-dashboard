# Accountability Reports (PDF + Excel) Export Design

**Date:** 2026-06-27
**Owner:** Daniel (CEO), Charles (FOM)
**Status:** Approved design, pending implementation plan
**Builds on:** [[project_tech_accountability_program]] (live). Read-only reporting over the committed accountability ledger.

## Purpose

Let Daniel and Charles export the technician accountability data for any period as a branded **PDF** (a polished report with charts + summary tables, for sharing/printing/coaching) and an **Excel** workbook (the raw underlying data for their own slicing). Emphasis on **trends** so they can see who is improving or sliding over time.

## Decisions locked in brainstorming

- **Scope:** accountability only (points/levels stats + the violation/review detail). Not customer reviews, not the broader dashboard KPIs.
- **PDF vs Excel:** PDF = branded report (charts + summary tables). Excel = raw data for analysis.
- **Charts:** core set **plus per-tech trend lines**.
- **Period:** a date-range picker (any start/end) with presets, and trend buckets that auto-size to the range (day / Fri-Thu week / month).
- Reports **committed** points only (the review-gated ledger), so they reflect what Charles confirmed. Read-only; no writes, no KPI impact.

## Architecture

### Layers
1. **Data fetch** (`useAccountabilityReportData(startIso, endIso)` hook): pulls, gated on session, for the period:
   - non-voided `accountability_points` with `occurred_on` in [start,end] (plus all non-voided rows up to `end` for balance-as-of-end),
   - `accountability_actions` in range,
   - active `technicians` (id, name),
   - the violation catalog (`violation_types`: code, label, points, severity).
2. **Aggregation (pure, tested)** — `src/lib/accountability/report.ts`: the single source both exports read. Given the fetched rows, produces an `AccountabilityReport` object (see Data shapes). Includes the bucketing logic.
3. **PDF builder** — `src/lib/accountability/pdf-report.ts`: jsPDF + jspdf-autotable; embeds chart images.
4. **Excel builder** — `src/lib/accountability/excel-report.ts`: ExcelJS + file-saver.
5. **UI** — `src/components/accountability/AccountabilityExport.tsx`: date-range picker (+ presets) + Download PDF / Download Excel buttons; an off-screen chart-render area for the PDF. Mounted on the Tech Accountability tab, gated to admin + field_supervisor.

### Bucketing (in report.ts)
`bucketGranularity(start, end)`: range ≤ 28 days → `day`; ≤ ~183 days → `week` (Fri-Thu, `weekStartsOn:5`, matching payroll); else `month`. `bucketKey(date, granularity)` returns the bucket label. Pure + unit-tested at the boundaries.

### Data shapes
```
AccountabilityReport {
  period: { startIso, endIso, granularity };
  buckets: string[];                                  // ordered bucket labels
  teamSeries: { bucket: string; points: number }[];   // team points per bucket
  perTech: {
    technician_id; name;
    periodPoints; balanceAsOfEnd; level;              // level from balanceAsOfEnd
    severity: { minor; serious; major };
    daysClean: number | null;
    series: { bucket: string; points: number }[];     // this tech's points per bucket (for trend lines)
  }[];
  violations: {                                        // detail rows (non-voided, in range)
    occurred_on; technician_name; code; label; points; severity; source; job_id; note;
  }[];
  actions: { occurred_on; technician_name; action_type; notes }[];
}
```
- `level` reuses `computeLevel` from the engine; `balanceAsOfEnd` = sum of non-voided points with `occurred_on <= end`.
- `daysClean` reuses `daysSinceLastPoint` as of `end`.
- Charles co-tech attribution is already baked into each ledger row's `technician_id`; no re-attribution needed here.

### PDF report (pdf-report.ts)
- **Header:** navy band, Twins logo (`twinsdash.com/twins-logo.png`), title "Technician Accountability Report", period label, generated date. Yellow accent rule.
- **Charts** (rendered off-screen as Recharts in the component, captured to PNG via html2canvas, passed into the builder as data URLs):
  1. Team points over time (line, x=buckets).
  2. Per-tech trend lines (one multi-series line; one line per tech).
  3. Per-tech period totals (ranked horizontal bar).
  4. Severity breakdown (stacked bar or donut: minor/serious/major).
- **Standings table** (jspdf-autotable): Tech | Period points | Balance | Level | Days clean, sorted by balance desc; Level cells color-banded.
- **Violations detail table:** Date | Tech | Item | Pts | Severity | Ticket. Paginated by autoTable.
- Footer: "Twins Garage Doors internal report. Generated automatically." No em-dashes.
- File name: `twins-accountability_{start}_{end}.pdf`.

### Excel workbook (excel-report.ts)
Branded header rows (navy fill, yellow accent). Sheets:
- **Summary:** Tech, Period points, Minor, Serious, Major, Balance, Level, Days clean.
- **Violations:** Date, Tech, Code, Label, Points, Severity, Source, Ticket #, Note.
- **Actions:** Date, Tech, Action, Notes.
- **Daily trend:** Bucket, then one column per tech (points per bucket) for the user's own charting.
File name: `twins-accountability_{start}_{end}.xlsx`.

### Chart capture approach
The component renders the four charts in a hidden, fixed-size container. On Download PDF: for each chart node, `html2canvas` → PNG data URL; pass all four to `buildAccountabilityPdf(report, chartImages)`. This reuses Recharts so charts match the dashboard. (If capture proves flaky, a follow-up can switch to vector drawing; not in scope now.)

## Components / boundaries
- `report.ts` — pure aggregation + bucketing. No I/O. Tested.
- `pdf-report.ts` — takes `AccountabilityReport` + chart image data URLs → jsPDF doc. No data fetching.
- `excel-report.ts` — takes `AccountabilityReport` → ExcelJS workbook → `saveAs`. No data fetching.
- `useAccountabilityReportData` — the only data-fetching unit.
- `AccountabilityExport.tsx` — wires picker + hook + off-screen charts + the two builders.

## Error handling / safety
- Read-only: no writes, no KPI impact, nothing touches the ledger.
- Empty period → a valid report with empty series and an "No accountability activity in this period" note on the PDF; Excel sheets render headers with no rows.
- Large ranges are bounded by bucketing (monthly) so charts stay readable and queries stay bounded (one points query, one actions query).
- Currency rule N/A (points, not dollars). No em-dashes in any copy.

## Testing
- `report.ts` unit tests: `bucketGranularity` boundaries (28 days, ~183 days); `bucketKey` Fri-Thu week alignment; per-tech period points + severity counts (voided excluded); `balanceAsOfEnd` and `level`; team series sums equal sum of per-tech series; empty-period shape.
- PDF/Excel builders: smoke tests that a report object produces a non-empty Blob/doc and the standings/violations rows match counts (builders are hard to assert deeply; assert structure + row counts).
- Hook: not unit-tested (I/O); covered by the report.ts tests on the shaping logic.

## Open items for the plan
- Confirm the per-tech trend chart stays readable with many techs (current roster is small; if it grows, switch to small-multiples or top-N).
- Decide PDF chart image resolution (scale factor for html2canvas) for crispness vs file size; start at 2x.
