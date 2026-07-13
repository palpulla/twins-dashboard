# Plaud AI Recording — Daily Review Checklist Item

**Date:** 2026-07-08
**Repo:** palpulla/twins-dash (+ live jwrpj database) and the two static manuals
**Status:** Design approved, pending spec review

## Problem

Twins wants technicians to record on-site customer conversations with the Plaud AI recorder on sales/estimate/diagnostic visits. There is no way to hold techs accountable for it. Add it as a 1-point minor checklist item in the accountability point system and document it in both manuals.

## Goal

Add one new checklist item — "Recorded on-site conversation (Plaud AI)" — to the daily review and both manuals. No changes to points math, the Level ladder, auto-detection, or any existing item.

## Non-Goals

- No auto-detection (Plaud is a separate device; HCP has no signal). Charles marks it.
- No new prefill/engine logic. The item rides the existing catalog-driven checklist.
- No change to how any other item scores.

## Design

### 1. Catalog item

Add one row to the `violation_types` catalog:

| field | value |
|---|---|
| `code` | `plaud_recording` |
| `label` | `Recorded on-site conversation (Plaud AI)` |
| `points` | `1` |
| `severity` | `minor` |
| `is_checklist_item` | `true` |
| `auto_detectable` | `false` |
| `scope` | `ticket` |
| `sort_order` | `15` (immediately after `present_3_options` = 10) |
| `active` | `true` |

Label is phrased positively (the good behavior), matching every other checklist row: **Yes = recorded (compliant), No = 1-point minor violation, N/A = not applicable**.

### 2. Behavior — no new logic

The daily review renders ticket-scoped checklist items straight from the catalog (`useViolationCatalog` reads `violation_types`). A non-auto-detectable item is **not** in `prefillForTicket`, so the review seeds it to **`na`** by default (same as `document_parts`, photos, `customer_signature`). Only a `no` posts a point.

Result: quick no-conversation jobs stay N/A and never flag; Charles sets Yes on recorded conversations and No when a conversation happened but wasn't recorded. The "only conversation visits" rule falls out of the N/A default — no code branch needed.

### 3. Catalog defined in two synchronized places

The catalog exists in two forms that must stay identical:

- **Database** `public.violation_types` — what the live review and the Rules editor read. Added via a migration and applied to jwrpj.
- **Hardcoded mirror** `VIOLATION_CATALOG` in `src/lib/accountability/types.ts`, plus the two vendored copies used by the edge functions:
  - `supabase/functions/daily-supervisor-digest/accountability-vendored.ts`
  - `supabase/functions/accountability-decay/engine-vendored.ts`

  The new entry (as a `ViolationType`: `code, label, points: 1, severity: "minor", isChecklistItem: true, autoDetectable: false`) is appended to each so tests and the engine agree with the DB.

`catalog.test.ts` asserts catalog shape — update it if it hardcodes the item count/list.

### 4. Documentation

- `public/point-system.html`: add "Recorded on-site conversation (Plaud AI)" to the **Minor · 1 point** list and to the **Daily Checklist** list (as "Recorded on-site conversation with Plaud AI (1)").
- `public/point-system-guide.html` (FOM guide): add a line to the checklist explanation — Charles marks it Yes/No on conversation visits (sales/estimate/diagnostic) and leaves quick jobs N/A.

### 5. Files touched

**New**
- `supabase/migrations/<ts>_violation_types_plaud_recording.sql` — INSERT the catalog row (idempotent `ON CONFLICT (code) DO NOTHING`).

**Changed**
- `src/lib/accountability/types.ts` — append to `VIOLATION_CATALOG`.
- `supabase/functions/daily-supervisor-digest/accountability-vendored.ts` — append to its catalog.
- `supabase/functions/accountability-decay/engine-vendored.ts` — append to its catalog (only if it carries the full catalog; skip if it doesn't).
- `src/lib/accountability/__tests__/catalog.test.ts` — update if it asserts the item list/count.
- `public/point-system.html`, `public/point-system-guide.html` — docs.

### 6. Rollout & verification

- Apply the migration to jwrpj via Supabase MCP (record the version row per the migration-history desync).
- Verify: the new item appears in the daily review checklist for a ticket, defaults to N/A, and posting a `no` adds 1 point; the Rules editor lists it under Minor.
- `tsc` + build clean; `npx vitest run src/lib/accountability` green.
- Reversible: one catalog row (can be set `active=false`) + additive doc lines.
