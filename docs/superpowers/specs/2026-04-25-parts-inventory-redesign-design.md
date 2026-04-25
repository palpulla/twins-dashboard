# Parts and Inventory Redesign — Design Spec

**Date:** 2026-04-25
**Repo:** `palpulla/twins-dash`
**Page in scope:** `/payroll/parts` (currently `src/pages/payroll/PartsLibrary.tsx`)
**Audience for the new flow:** field supervisor (Charles today) on a phone in the warehouse.

## Why

Three problems with the current Parts Library, all surfaced from live use on twinsdash.com:

1. **Stat tiles are unlabeled.** SKUs, On-hand, Needs reorder, Velocity 30d sit at the top of the page with no explanation. New users do not know what each one means.
2. **Mobile layout is broken.** The page is built for a 1280px desktop with a `1fr 320px` two-column grid, a wide table, and a side rail. On a phone the table overflows and the rail stacks awkwardly.
3. **No counting workflow.** The supervisor has to walk the warehouse with a phone, find each SKU manually, type into a small inline number input, and hope nothing gets missed. There is no progress indicator, no resume, no discrepancy log, no voice option.

The redesign keeps the existing desktop table for everyday admin work and adds a phone-first counting experience on top, with voice input.

## Out of scope

- Barcode or QR scanning. Parts are not labeled today. Revisit after voice usage settles.
- Location data on parts (aisle, bin). Not in the schema, not asked for.
- Multi-counter sessions. One supervisor counts at a time.
- Auto-scheduled cycle counts. Add later if useful.
- Changes to the existing audit trigger or activity feed (PR #16 covers those).
- Changes to the payroll Excel export or commission math.

## Audience and access

- **Admin** (Daniel): full access. Sees costs, edits everything, runs sessions.
- **Field supervisor** (Charles): can read everything except costs. Can edit on-hand, min-stock, category, run count sessions.
- **Tech / other roles:** no access to this page. Already gated by `PayrollGuard`.

## Layout per breakpoint

### Desktop (`md` and above)

Largely unchanged. Existing layout stays:

- Top bar (crumbs + import / upload / export / new part).
- Stat strip (4 tiles).
- Category strip.
- Two-column grid: parts table on the left, reorder rail and activity feed on the right.

Added on desktop:

- Each stat tile gets a small `info` icon. Hover shows a one-line explanation (canonical strings in section "Stat tile copy" below).
- A new accent button "Start count session" next to "New part," routes to `/payroll/parts/count`.

### Mobile (below `md`)

The entire grid collapses to a single column. Tabs at the top:

- `Parts` (default)
- `Reorder` (lists low-stock items, same data as the desktop reorder rail)
- `Activity` (last 50 changes from `audit_log`, same data as the desktop feed)

Stat tiles become a 2x2 grid above the tabs. The category strip becomes a horizontal scroll row.

The table is replaced by a stack of part cards (one per SKU). Filters, search, and sort live in a sticky header.

A floating action button at the bottom right says **Count** with a target icon. Tapping it opens the count session start screen.

## Components

### Part card (used on mobile and as a fallback when admin chooses "Compact" view on desktop)

```
+------------------------------------------+
| Titan Roller            [Rollers]        |
| Last counted Apr 12 - 30d use 8          |
|                                          |
| On hand:   [    30    ]   low stock      |
| Min stock: [    10    ]                  |
| ----------------------------------       |
| [ Edit ]   [ History ]                   |
+------------------------------------------+
```

- All inputs at least 44px tall, numeric keyboard on focus.
- Tapping the card body opens the same edit dialog as today (cost + category, cost hidden for non-admin).
- "History" opens a side sheet showing recent audit entries for this SKU only.
- "low stock" badge appears when `effective <= min_stock`.

### Stat tile copy

Each tile renders a small info icon. Tap or hover:

- **SKUs** — Total parts in your library, including non-tracked one-time costs.
- **On hand units** — Sum of physical inventory across tracked parts (counts only). Shown to all roles.
- **On-hand value** — Sum of `on_hand * total_cost` across tracked parts. Admin only.
- **Needs reorder** — Tracked parts where current on-hand is at or below your min stock.
- **Velocity 30d** — Total parts pulled from completed jobs in the last 30 days. This is the demand signal that drives reorder.

Strings live in a single `STAT_INFO` constant so copy edits are one place.

## Count session

New sub-route: `/payroll/parts/count`.

### Start screen

- Title: "New count session."
- Field: session name (default: `Count Apr 25, 2026`).
- Multi-select chip group: which categories to include. Default selects all.
- "Start counting" button.

If a session is already in progress for the current user, the start screen instead shows a "Resume" card with `last activity 12 minutes ago, 23 of 94 counted`.

### Per-SKU screen

Single SKU at a time. Vertical layout, all 44px+ targets:

```
+------------------------------------------+
| Progress: 23 / 94    Springs   1m 42s   |
| ==========------------ 24%              |
|                                         |
| Titan Roller                            |
| Rollers - last counted Apr 12           |
|                                         |
| System says:  30                        |
|                                         |
| You counted:                            |
| +---------------------+                 |
| |        30           |                 |
| +---------------------+                 |
|                                         |
| [ MIC ]   [ 0 1 2 3 4 5 6 7 8 9 ]       |
|                                         |
| [ Skip ]                  [ Save & Next ]
+------------------------------------------+
```

- The mic button is the primary affordance. Tapping starts speech capture. (See "Voice handler" below.)
- The number row is a fallback for typing.
- "Skip" logs a `skipped` line for that SKU and advances.
- "Save & Next" writes the count and advances to the next SKU in the chosen category, then the next category, etc.
- A small "back" button returns to the previous SKU and reopens it for editing within the same session.

### Discrepancy prompt

When the supervisor enters a count that differs from `system says` by more than 50% or more than 10 units (whichever is larger), the Save button opens a small inline note: "That is a big change. Quick note? (optional)" with a one-line text field. Saving without a note still works.

### End screen

- Summary stats: total counted, total skipped, discrepancies (count and units).
- A list of all discrepancies for review. Each row: SKU name, expected, counted, delta percent, note. Tap to edit the count or note before finalizing.
- "Finish session" button. Sets the session row's `status` to `completed`, writes any final updates to `payroll_parts_prices.on_hand` for SKUs that were not yet committed, and returns to the Parts page.

### Resumability

A session is "in progress" until either Finish or Cancel. The page restores the supervisor to the next pending SKU in the chosen category order. Closed app tabs are fine.

## Voice handler

New util `src/lib/parts/voiceCount.ts`.

### Capture

Wraps `webkitSpeechRecognition` (Chrome on Android and desktop Chrome) and `SpeechRecognition` (Safari iOS recent versions). Single-shot recognition (`continuous = false, interimResults = false`). Locale `en-US`.

### Parse

Input is the final transcript string. The parser pulls a trailing number token (digits like `28` or English number words like `twenty eight`) and treats the remaining text as a name query.

```ts
parseVoice("Titan roller thirty")
// { count: 30, query: "titan roller" }

parseVoice("twenty five SSWDE ten")
// { count: 10, query: "twenty five SSWDE" }
// (the parser only consumes the LAST number token)
```

### Match

Fuzzy match query against:

- `payroll_parts_prices.name`
- An alias index (initially empty, filled over time as Daniel teaches the system synonyms like `spring 25 => .225 #2 - 26"`).

Library: `fuse.js` (already a small footprint, easy to introduce). Returns top 3 matches with scores.

### Confirm card

Always shown after voice capture. Even at high confidence Charles taps Save. The card lists:

- "Heard: `Titan Roller x 30`" with an undo / edit pencil on the count and a swap button on the SKU name.
- Top match (large, primary). Tap Save to commit.
- Up to two alt matches as smaller chips. Tap to swap.
- "Cancel" closes the card. The session screen state is unchanged.

### Aliases

A new table `parts_voice_aliases (alias text, part_id int, created_by uuid, created_at timestamptz)`. When a match is wrong, an admin can add an alias from the confirm card or a new "Aliases" admin page. v1 ships with the table empty.

## Data model

### New tables

`inventory_count_sessions`

| Column | Type | Notes |
|---|---|---|
| id | bigint identity, pk | |
| created_by | uuid, not null, fk auth.users | |
| name | text | "Count Apr 25" or supervisor-typed |
| started_at | timestamptz, not null, default now | |
| completed_at | timestamptz | null while in progress |
| status | text, not null, default 'in_progress' | check in (in_progress, completed, cancelled) |
| categories_filter | text[] | empty array means all |
| note | text | optional |

`inventory_count_lines`

| Column | Type | Notes |
|---|---|---|
| id | bigint identity, pk | |
| session_id | bigint, not null, fk inventory_count_sessions | on delete cascade |
| part_id | int, not null, fk payroll_parts_prices | |
| expected | numeric | snapshot of `on_hand` when the line was opened |
| counted | numeric | the supervisor's value (null while skipped) |
| delta_pct | numeric | computed at write time |
| status | text | check in (counted, skipped, pending) |
| note | text | |
| created_at | timestamptz, not null, default now |
| committed_at | timestamptz | when the value was written through to `payroll_parts_prices.on_hand` |

`parts_voice_aliases`

| Column | Type | Notes |
|---|---|---|
| id | bigint identity, pk | |
| alias | text, not null | normalized lowercased |
| part_id | int, not null, fk payroll_parts_prices | |
| created_by | uuid | |
| created_at | timestamptz | |

Unique on `(alias, part_id)`.

### RLS additions

- `inventory_count_sessions`: insert / select / update by `has_payroll_access` or `field_supervisor`.
- `inventory_count_lines`: same.
- `parts_voice_aliases`: select by anyone authenticated (matching is open). Insert / update / delete by `has_payroll_access`.

### Commit semantics

When the supervisor saves a count line, a server-side function `commit_count_line(session_id bigint, part_id int, counted numeric, note text)` does both writes in a single transaction:

1. Inserts or updates the matching `inventory_count_lines` row.
2. Updates `payroll_parts_prices.on_hand = counted, last_count_at = now` for that part.

This re-uses the existing audit trigger from PR #16. The activity feed already shows the change, so we do not need a second feed for sessions.

## API surface (edge functions or RPCs)

- `start_count_session(name text, categories_filter text[]) returns bigint` (RPC, plpgsql).
- `commit_count_line(session_id bigint, part_id int, counted numeric, note text) returns void` (RPC, plpgsql).
- `skip_count_line(session_id bigint, part_id int, note text) returns void`.
- `finish_count_session(session_id bigint) returns void`.
- `cancel_count_session(session_id bigint) returns void`.

All gated by RLS on the underlying tables.

## UI primitives reused

- Existing `parts-library.css` tokens (`--pl-navy`, `--pl-yellow`, `--pl-line`, etc.) for desktop.
- Existing `ts-scope` styles from `src/styles/scorecard.css` for the count session screens (the same navy + yellow + Inter + JetBrains Mono treatment used on the tech scorecard).
- shadcn primitives: `Card`, `Button`, `Input`, `Dialog`, `Sheet`, `Tabs`, `Progress`, `Select`.

## File structure

```
src/pages/payroll/PartsLibrary.tsx                     - existing, gets InfoTip + tab layout
src/pages/payroll/CountSession.tsx                     - new, /payroll/parts/count
src/components/payroll/parts/PartCard.tsx              - new, mobile card variant
src/components/payroll/parts/StatInfoTip.tsx           - new, consistent info icon
src/components/payroll/parts/CountSessionStart.tsx     - new
src/components/payroll/parts/CountSessionPerSku.tsx    - new
src/components/payroll/parts/CountSessionEnd.tsx       - new
src/components/payroll/parts/VoiceConfirmCard.tsx      - new
src/lib/parts/voiceCount.ts                            - new
src/lib/parts/voiceParse.ts                            - new (testable in Vitest)
src/hooks/parts/useCountSession.ts                     - new
src/hooks/parts/useVoiceCount.ts                       - new

supabase/migrations/2026042500006_inventory_count_tables.sql       - new
supabase/migrations/2026042500007_count_session_rpcs.sql           - new
supabase/migrations/2026042500008_parts_voice_aliases.sql          - new
```

## Stat tile copy (canonical strings, single source)

```ts
export const STAT_INFO = {
  skus: "Total parts in your library, including non-tracked one-time costs.",
  on_hand_units: "Sum of physical inventory across tracked parts (counts only).",
  on_hand_value: "Sum of (on hand x cost) across tracked parts. Admin only.",
  needs_reorder: "Tracked parts where current on-hand is at or below your min stock.",
  velocity: "Total parts pulled from completed jobs in the last 30 days. Drives reorder.",
};
```

## Testing plan

- Unit tests for `voiceParse`: trailing digit token, English number words 0-99, leading vs trailing position, multi-token names, no-number input.
- Component test for `CountSessionPerSku`: discrepancy threshold prompt fires at the right deltas, skip writes the line, save advances.
- Integration test for `commit_count_line` RPC: writes both rows in a transaction, audit row appears.
- Manual: run a full session on Charles's actual phone in the warehouse, with voice. Adjust voice parser based on what speech-to-text produces for real part names.

## Open questions

- Voice on iOS Safari has historically been spotty. If this turns into a real issue, we add a note on the start screen "voice works best in Chrome" and revisit.
- The alias index is empty in v1. Acceptable cost: Charles will see a few wrong matches the first week. He can either type in the confirm card or admin can add aliases. The cost is low and the data quality grows over time.
