# HCP Job Type Auto-Tagging — Design

**Date:** 2026-06-17
**Status:** Approved for planning; pivoted after spike (see Addendum)
**Owner:** Daniel (Twins Garage Doors)

## Addendum (2026-06-18) — spike outcome and pivot

The Task 1 live spike proved HCP's public API has **no endpoint to write Job Type**
(every job-update route returns a route-level 404; only line-items and dispatch are
writable). The "webhook auto-writes Job Type into HCP" half of this design is therefore
not buildable as written. Decision (Daniel): ship a **guided worklist** instead — the
system classifies and records a *proposed* Job Type per job, surfaced in the
`/admin/job-type-review` tab with a one-click HCP link; Daniel sets it in HCP. No browser
automation. No HCP writes anywhere. The `setJobType` writer was removed; the orchestrator
records status `proposed | blank | disagreement | agree` (the worklist shows the first
three). Classification still runs real-time on the completion webhook (non-fatal) and via
a one-time backfill, both read-only.

Confirmed during the spike: HCP labels match the six constants exactly; line-item amounts
are cents with the customer price in `amount` (`total_cost` is null); discounts arrive as
positive `fixed/percentage discount` lines and are **excluded** (the \$185 test uses the
**pre-discount subtotal**); gratuity excluded. `part_categories` is seeded from the real
HCP line-item vocabulary (72 parts). Rulings: Hung/Crashed Door = repair (not install, and
there is no "section" SKU, which resolves that edge case); Tune Up / Maintenance Plan =
repair_part so cheap maintenance lands on Service Call. The decision tree itself is
unchanged.

## Problem

Every job ticket in Housecall Pro needs a **Job Type** set. Today Daniel sets it
by hand on every ticket. The job's line items (parts + labor) and total already
contain a deterministic signal of what the job was, so the common cases can be
classified and written back automatically. The genuinely ambiguous cases should
be surfaced for a human call, never guessed.

This design covers **Job Type only**. **Lead Source** is explicitly out of scope
(it is not present in the ticket; it depends on GHL attribution and is tracked as
a separate follow-on, see "Out of Scope").

## Guiding constraint: no heuristic guessing

Twins has been burned once by inferring categories from free text: a description
keyword classifier mislabeled a real Door + Opener Install ($4,790) as a repair.
The rule from that incident stands: **only classify from explicit, structured
signals; if the signal isn't there, do not guess — surface it.**

This design honors that by:
- Categorizing line items from an **explicit part→category mapping**, not keyword
  matching on free text.
- Routing any job with unrecognized parts (or no clear signal) to a **review
  queue**, never to a default Job Type.
- Checking parts **before** the labor/threshold logic, so a "Labor" line on a
  big install can never demote it to a repair (the original failure mode).

## Job Type decision tree

Evaluated top to bottom; **first match wins**. Driven by line-item categories,
the job total, and the presence of a literal "Labor" line item.

| # | Condition | Job Type |
|---|-----------|----------|
| 1 | Has a `door` part **and** an `opener` part | **Door + Opener Install** |
| 2 | Has a `door` part, no `opener` | **Door Install** |
| 3 | Has an `opener` part **and** `repair_part`(s) (excluding keypad/remote) | **Opener + Repair** |
| 4 | Has an `opener` part with only `accessory` (keypad/remote) or nothing else | **Opener Install** |
| 5 | No `door`/`opener`, **literal "Labor" line present** | **Repair** |
| 6 | No `door`/`opener`, **job total ≥ $185** | **Repair** |
| 7 | No `door`/`opener`, **job total < $185** | **Service Call** |
| 8 | Nothing matches cleanly (unrecognized parts, no Labor line, empty) | **BLANK → review queue** |

Notes that make the tree unambiguous:
- **Accessories** = wireless keypad and remote. They are install add-ons, so an
  opener shipping with only these is **Opener Install** (row 4), not a repair.
- The **$185** threshold is compared against the **total job price**, strictly
  **less than** for Service Call.
- A literal **"Labor"** line (exact line-item name) forces **Repair** even under
  $185, because a tech may have discounted a real repair below the threshold
  (row 5 beats row 7).
- "Labor → Repair" only applies in the no-door/no-opener branch, because rows 1–4
  already consumed any job with door/opener parts.

### Part categories

Each line item maps to exactly one category via an explicit table:

| Category | Examples |
|----------|----------|
| `door` | door, section |
| `opener` | opener units (LiftMaster, etc.) |
| `accessory` | wireless keypad, remote |
| `repair_part` | springs, cables, rollers, hinges, and other repair parts |
| `labor` | line item literally named "Labor" |

The mapping is seeded from the existing Twins price sheet into a Supabase
`part_categories` table (`sku`/`name` → `category`). **A line item whose
part is not in the table makes the whole job unclassifiable (row 8).** New parts
are added to the table over time; until then they surface in the queue.

## Architecture

Lives in **twins-dash / Supabase**. One classifier implementation in TypeScript,
shared by the real-time path and the backfill path.

### Components (isolated, single-purpose)

1. **Part categorizer** — given a job's line items, returns the set of categories
   present (and whether any line item was unrecognized). Pure function over the
   `part_categories` table.
2. **Job classifier** — given the category set + job total + literal-"Labor"
   flag, returns one Job Type string or `BLANK`. Pure function; this is the
   decision tree above. Fully unit-testable, no I/O.
3. **HCP writer** — writes a Job Type to a ticket. Mechanism decided by a spike
   (see below). Idempotent and guarded (see Safety).
4. **Classification store** — a Supabase table recording, per job: proposed Job
   Type, current HCP Job Type, status (`written` / `blank` / `disagreement` /
   `pending_write`), line-item snapshot, HCP link, timestamps.
5. **Review tab in twins-dash** — reads the store; shows BLANKs and
   disagreements (and `pending_write` if the writer is batched) with the HCP link
   and line items, so Daniel can resolve them.

### Run modes

- **Backfill (one-time):** iterate historical completed jobs, classify, record in
  the store. **Fill blanks only** — write a Job Type only where HCP's current Job
  Type is empty. Where a Job Type already exists and the system disagrees, record
  a `disagreement` for review; **never overwrite**.
- **Ongoing (real-time):** the existing HCP completion webhook triggers
  classification the moment a job reaches `complete unrated`, records in the
  store, and (if API write is supported) writes inline.

### Write-mechanism spike (FIRST implementation task)

Determine whether the HCP public API can set a job's Job Type:
- **If yes:** the webhook writes inline; fully automatic.
- **If no** (browser-automation fallback): an edge function cannot drive a
  browser, so confident classifications are recorded as `pending_write` and
  applied by a separate batched run (local computer-use against
  `pro.housecallpro.com`), surfaced/triggerable from the dash tab.

Either way, components 1, 2, 4, 5 are unchanged. Only where/how the write happens
moves. The spike's outcome is recorded in the implementation plan before building
the writer.

## Safety / reversibility

- **Dry-run by default.** A real write requires an explicit flag (same pattern as
  the paystub pipeline's `dry_run`). Dry-run prints/records the proposed writes
  without touching HCP.
- **Never overwrite a human tag.** Writes only fill **empty** Job Type fields.
  Existing tags that disagree are flagged, not changed.
- **Completed jobs only** (`work_status: complete unrated`), so line items are
  final before classifying.
- **Every write is logged** in the store so it can be audited and undone.
- **Job Type strings must match HCP's exact labels** (verified during the spike),
  so writes are accepted and round-trip cleanly.

## Edge cases (to confirm during planning)

These are written down so they get an explicit ruling rather than a silent
assumption:

1. **Replacement section.** A single replacement *section* is a `door` part but is
   really a repair. Options: treat any `door` part as install (current tree), or
   add a distinct `section` category that routes to the repair branch. **Needs
   Daniel's ruling.**
2. **Multiple openers on one ticket.** Confirm this is still a single
   Opener/Door+Opener Install and not a special case.
3. **Discount / gratuity / fee lines.** Confirm these are ignored by the
   categorizer (not parts) and that the **total** used for the $185 test is the
   job total Daniel means (pre- or post-discount, with/without tip).
4. **Estimates.** Exclude `job_type Estimate` / estimate records entirely (they
   are not jobs to tag), consistent with existing dashboard rules.

## Out of scope

- **Lead Source.** Not in the ticket. Depends on GHL attribution; tracked as a
  separate follow-on hanging off the Marketing ROI work, not built here.
- Any change to payroll math, revenue recognition, or other KPI logic. This
  feature reads line items and writes the Job Type field only.

## Success criteria

- Historical completed jobs with empty Job Type and recognized parts are tagged
  correctly per the decision tree, verified against a sample Daniel checks.
- New completed jobs get tagged automatically (inline or batched per spike).
- Zero silent overwrites of existing Job Types; all disagreements visible in the
  dash tab.
- Every unclassifiable job appears in the review tab with its link and line items;
  none are defaulted.
