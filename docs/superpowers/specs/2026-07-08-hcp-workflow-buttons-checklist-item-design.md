# HCP Workflow Buttons — Daily Review Checklist Item

**Date:** 2026-07-08
**Repo:** palpulla/twins-dash (+ live jwrpj database) and the two static manuals
**Status:** Design approved, pending spec review

## Problem

Techs are supposed to press the Start, OMW, and Finish buttons in HCP on every job (it drives dispatch, customer notifications, and job timing). Only OMW is currently in the point system, as the separate `inform_arrival` ("notified customer of arrival") item. Twins wants missing any of the three buttons to cost a point.

## Goal

Add one auto-detected 1-point minor checklist item — "Pressed Start, OMW, and Finish in HCP" — that flags (No, 1 pt) when a completed job is missing any of `started_at` / `on_my_way_at` / `completed_at`. Retire the standalone `inform_arrival` item so a missed OMW is counted once, not twice.

## Non-Goals

- No points-math, Level-ladder, or decay changes.
- No change to the daily digest's own missing-button flagging (independent of the catalog).
- Do not delete `inform_arrival` — only deactivate it, so historical points keep their label.

## Design

### 1. New catalog item + retire OMW

Add to `violation_types`:

| field | value |
|---|---|
| `code` | `hcp_workflow_buttons` |
| `label` | `Pressed Start, OMW, and Finish in HCP` |
| `points` | `1` |
| `severity` | `minor` |
| `is_checklist_item` | `true` |
| `auto_detectable` | `true` |
| `scope` | `ticket` |
| `sort_order` | `45` (after `after_photos`=40, before `safety_inspection`=50) |
| `active` | `true` |

Label is positive (Yes = all three pressed = good; No = any missing = 1-point minor).

Deactivate the old item in the DB: `UPDATE violation_types SET active=false WHERE code='inform_arrival'`. It stops rendering and scoring going forward; its historical ledger points and label are untouched.

### 2. Auto-detection

The review pre-fills auto-detectable ticket items from HCP signals. Add a new signal and mapping:

- **Signal** `workflowButtonsPressed: boolean` — `true` when a job has all three `work_timestamps`: `started_at` AND `on_my_way_at` AND `completed_at`.
- **Prefill** (`prefillForTicket`): `hcp_workflow_buttons: s.workflowButtonsPressed ? "yes" : "no"`. So a job missing any timestamp pre-fills **No** (1 pt); Charles can still override.
- **Estimates** pre-fill `workflowButtonsPressed: true` (estimate tickets have no start/finish flow), exactly as `invoiceSent`/`paymentCollected`/`informedArrival` already do on estimates.
- Extend the `HcpData.work_timestamps` type in `useTechDayReview.ts` from `{ on_my_way_at?: string | null }` to also include `started_at?: string | null` and `completed_at?: string | null`.

`inform_arrival` stays in `AUTO_TICKET_CODES` / `prefillForTicket` / the `informedArrival` signal as harmless dead pre-fill (the item no longer renders because it is inactive in the DB) — leaving it avoids churning the existing prefill test. `auto-mapping.ts` (`OMW → inform_arrival`) is unused at runtime and unchanged.

### 3. Catalog synced everywhere

Add the `hcp_workflow_buttons` entry (`code, label, points: 1, severity: "minor", isChecklistItem: true, autoDetectable: true`) to:
- `src/lib/accountability/types.ts` `VIOLATION_CATALOG` (after `after_photos`) and `CHECKLIST_CODES` (after `after_photos`).
- `supabase/functions/daily-supervisor-digest/accountability-vendored.ts` — same two edits.
- `supabase/functions/accountability-decay/engine-vendored.ts` — same two edits.

`inform_arrival` stays in all three hardcoded catalogs (label lookups for historical points).

Update `src/lib/accountability/__tests__/catalog.test.ts`: `VIOLATION_CATALOG` length 31 → 32; `CHECKLIST_CODES` 10 → 11 with `hcp_workflow_buttons` inserted after `after_photos`.

Add a `prefill.test.ts` case: all three timestamps → `hcp_workflow_buttons: "yes"`; missing any → `"no"`.

### 4. Documentation

- `public/point-system.html`:
  - Minor · 1 point list: **remove** `Failed to inform customer of arrival`; **add** `Did not press the Start, OMW, or Finish buttons in HCP`.
  - Daily Checklist list: add `Pressed Start, OMW, and Finish in HCP (1)`.
- `public/point-system-guide.html` (FOM guide): add a line — this item is auto-detected from HCP; it flags when the tech skipped Start, OMW, or Finish on a job. Note OMW (customer arrival notification) is now part of this one item.

### 5. Files touched

**New**
- `supabase/migrations/<ts>_violation_types_hcp_workflow_buttons.sql` — INSERT the row + UPDATE `inform_arrival` inactive (idempotent).

**Changed**
- `src/lib/accountability/types.ts`, `.../__tests__/catalog.test.ts`
- `src/lib/accountability/prefill.ts`, `.../__tests__/prefill.test.ts`
- `src/hooks/useTechDayReview.ts`
- `supabase/functions/daily-supervisor-digest/accountability-vendored.ts`
- `supabase/functions/accountability-decay/engine-vendored.ts`
- `public/point-system.html`, `public/point-system-guide.html`

### 6. Rollout & verification

- Apply the migration to jwrpj via Supabase MCP; record the version row.
- Verify: the item appears in the daily review for a completed job; a job missing `started_at`/`on_my_way_at`/`completed_at` pre-fills **No** (adds 1 pt in the preview); a fully-stamped job pre-fills **Yes**; an estimate pre-fills **Yes**; the retired `inform_arrival` no longer appears; the Rules editor lists the new item under Minor and no longer lists OMW.
- `tsc` + build clean; `npx vitest run src/lib/accountability` green.
- Reversible: reactivate `inform_arrival` and deactivate `hcp_workflow_buttons` to revert.
