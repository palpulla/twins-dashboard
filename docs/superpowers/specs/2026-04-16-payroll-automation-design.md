# Twins Payroll Automation — Design Spec

**Date:** 2026-04-16
**Status:** Draft for implementation planning
**Scope:** Automate the weekly Twins Garage Doors payroll calculation by pulling jobs and tech notes directly from the Housecall Pro (HCP) API and reconciling component costs against a locally-maintained parts price sheet.

## Context

Twins Garage Doors runs weekly payroll manually today. The operator:

1. Exports a service-request CSV from Housecall Pro
2. Opens each job's ticket in the HCP web UI to read the technician's notes (where spring sizes and specific components are written down)
3. Looks up each component in a local Excel parts sheet (`Weekly Payment Calculator (9).xlsx`, `Pricing` sheet) to find the cost
4. Calculates per-job `basis = (amount − tip) − parts_cost`
5. Applies commission percentages per tech, a step-tiered per-job bonus for the supervisor, and a 2% override on the supervisor for co-listed jobs
6. Types the resulting per-tech totals into the payroll provider

The business has HCP API + webhook access. This spec uses the API as the single source of truth for jobs and tech notes — no CSV export, no HCP web UI lookup. The operator's remaining manual work is reduced to: approving ownership on ambiguous jobs, typing component names with autocomplete (prefilled by the tech's notes shown inline), and pasting the weekly summary into the payroll provider.

This subsystem follows the CLI-first Python + SQLite + YAML pattern already established by `twins-media-generator/` and the content engine (`docs/superpowers/specs/2026-04-16-content-engine-design.md`), so the operator's mental model and toolchain stay consistent.

## Goals

- Pull jobs for the target week and their technician notes directly from the HCP API
- Walk the operator through each job interactively, showing the tech's notes inline, and prompting only for the exact cost-sheet component names + quantities
- Apply all commission rules correctly and reproducibly: base percentages, supervisor bonus tiers, supervisor 2% override, supervisor-co-listed ownership rule, tip pass-through
- Emit a weekly Excel output with a per-tech summary sheet, a per-job audit sheet, and a per-part detail sheet
- Store every run in SQLite and archive the raw HCP API responses so past weeks are reproducible and auditable even if prices change later
- Keep the commission configuration (rates, bonus tiers, tech roster) in YAML so adjustments don't require code edits

## Non-goals (v1)

- Webhook-triggered auto-draft runs (v2 — see "Out-of-scope but future-enabling")
- Direct export to a payroll provider (Gusto, QuickBooks, ADP). Output is Excel; the operator types into the provider.
- Web UI or dashboard — CLI only
- PDF paystubs for individual techs
- Multi-company / multi-business support
- Automatic product→component decomposition (e.g. expanding "UltraLife Double Spring Replacement (2 Car)" into its component parts). The operator reads tech notes and enters components.
- CSV ingest fallback (HCP API is the sole ingest path; CSV support can be added later if API access lapses)

## Tech roster and commission rules

```yaml
# config/techs.yaml (source of truth)
supervisor: Charles Rue
techs:
  - name: Charles Rue
    hcp_employee_id: <to be confirmed from HCP API on first sync>
    commission_pct: 0.20
    bonus_tier: step_tiers_charles
    override_on_others_pct: 0.02
  - name: Maurice
    hcp_employee_id: <tbd>
    commission_pct: 0.20
  - name: Nicholas Roccaforte
    hcp_employee_id: <tbd>
    commission_pct: 0.18
```

Both `name` and `hcp_employee_id` are stored so the tool can match either by HCP's employee ID (preferred, stable) or by fuzzy name match (fallback, for renames or legacy records).

**Step-tiered per-job bonus (Charles only), applied on `basis = amount − tip − parts_cost`:**

```yaml
# config/bonus_tiers.yaml
step_tiers_charles:
  band_width: 100
  band_start: 400     # nothing below $400
  bonus_start: 20     # first band pays $20
  bonus_step: 10      # each additional $100 band adds $10
  # $400–$499 → $20, $500–$599 → $30, $600–$699 → $40, ..., $1000+ → $80+
```

**Supervisor override rule ("Charles rule"):**

- If a job lists only Charles → Charles owns the job; he earns primary commission + bonus.
- If a job lists Charles + exactly one other tech → the other tech owns the job; the other tech earns primary commission, Charles earns a 2% override on the same basis (no bonus on override).
- If a job lists Charles + two or more other techs → the tool warns and prompts the operator to pick the primary owner; Charles still earns the 2% override.
- If a job has no Charles listed → the sole listed tech owns the job (warn if multiple non-supervisor techs are listed).
- Tips do not trigger overrides; tips are 100% pass-through to the primary owner.

## Architecture

Lives at `twins-dashboard/twins-payroll/`:

```
twins-payroll/
├── SKILL.md                        # discovery + weekly ritual
├── .env.example                    # HCP_API_KEY, HCP_BASE_URL
├── config/
│   ├── techs.yaml                  # roster + commission rates (+ HCP employee IDs)
│   ├── bonus_tiers.yaml            # Charles's step-tier bonus table
│   ├── hcp.yaml                    # API endpoints, filters, rate-limit settings
│   └── rules.yaml                  # price sheet path, week boundaries, notes field mapping
├── data/
│   ├── payroll.db                  # SQLite: runs, jobs, job_parts, commissions
│   └── hcp_cache/                  # archived raw API responses, one dir per run
├── engine/                         # pure library, unit-tested
│   ├── __init__.py
│   ├── db.py                       # SQLite layer
│   ├── hcp_client.py               # typed HCP API wrapper (auth, retry, paginate)
│   ├── hcp_sync.py                 # fetch + cache jobs for a date range
│   ├── hcp_normalize.py            # HCP API JSON → normalized Job dataclass
│   ├── price_sheet.py              # parse Pricing.xlsx → part lookup
│   ├── ownership.py                # Charles-co-listed ownership rule
│   ├── parts_prompt.py             # interactive prompt w/ autocomplete, inline notes
│   ├── commission.py               # primary + bonus + override math
│   ├── excel_writer.py             # weekly payroll Excel output
│   └── models.py                   # dataclasses: Job, JobPart, CommissionRow
├── scripts/                        # thin argparse CLIs calling engine/
│   ├── run_payroll.py              # main weekly command
│   ├── sync.py                     # pull from HCP without processing (cache-only)
│   ├── reprint.py                  # regenerate past week's output from DB
│   ├── show_run.py                 # print past run to terminal
│   └── validate_config.py          # sanity-check YAML + price sheet + HCP auth
├── tests/
│   ├── fixtures/                   # sanitized HCP JSON responses + price sheet xlsx
│   ├── test_commission.py
│   ├── test_ownership.py
│   ├── test_hcp_normalize.py
│   ├── test_hcp_client.py          # uses respx to mock HTTP
│   ├── test_price_sheet.py
│   └── test_end_to_end.py
├── output/                         # payroll_week_of_YYYY-MM-DD.xlsx files
└── pyproject.toml
```

### Component boundaries

- `engine/` is a pure library. No CLI code, no `argparse`, no `print` for user output. Every function is unit-testable in isolation. Network boundary is in `hcp_client.py` and is mocked at the HTTP layer in tests.
- `scripts/` are thin `argparse` wrappers that call `engine/` and handle user I/O (including `prompt_toolkit` prompts).
- `config/*.yaml` and `.env` are the full tuning surface. Adding a tech, rotating the API key, or changing a commission rate requires zero code changes.
- The parts price Excel remains the operator's live source of truth. The tool hashes the sheet at run time and snapshots which prices were used for each job's parts.
- The raw HCP API responses are archived under `data/hcp_cache/<run_id>/` (one JSON file per request) so any run can be replayed offline from its cache, and any historical analysis can be done without hitting HCP again.

## HCP API integration

### Authentication

- API key stored in `.env` as `HCP_API_KEY` (never committed)
- Base URL stored in `.env` as `HCP_BASE_URL` (defaults to `https://api.housecallpro.com` pending confirmation)
- Loaded via `python-dotenv`; absence of key produces an immediate, actionable error before any network call

### Client (`engine/hcp_client.py`)

- Built on `httpx.Client` (sync; the tool's workload is linear and doesn't benefit from async)
- Bearer auth header applied automatically
- Pagination handled internally: client methods return full result lists, iterating `next`-style cursors until exhausted
- Rate limiting: exponential backoff on HTTP 429 with `Retry-After` honored; cap of 5 retries; final failure raises a typed `HCPRateLimitError`
- Network/5xx failures: up to 3 retries with backoff; then raises a typed `HCPServerError` with full request/response context
- Auth failures (401/403): immediate raise with `HCPAuthError` — no retry
- Every request archived to `data/hcp_cache/<run_id>/<timestamp>_<endpoint_slug>.json` before returning

### Endpoints the tool uses

The following endpoints are the design's contract with HCP. Exact paths, pagination cursor names, and field names will be confirmed on the first real API call during implementation and pinned into `config/hcp.yaml`. The data shape below reflects what the tool needs, not necessarily the exact HCP schema.

| Purpose | Endpoint (approximate) | Fields used |
|---|---|---|
| Fetch jobs closed in a week | `GET /jobs?scheduled_start_min=<iso>&scheduled_start_max=<iso>&work_status=completed` | `id`, `invoice_number`, `description`, `scheduled_start`, `customer`, `assigned_employees`, `total_amount`, `tip`, `subtotal`, `labor_total`, `materials_total`, `discount`, `cc_fee` |
| Fetch full job detail | `GET /jobs/{id}` | All of the above, plus `notes`, `line_items`, `service_notes`, `internal_notes` |
| Fetch employee roster | `GET /employees` | `id`, `first_name`, `last_name`, `role` |

### Tech notes (the critical field)

The operator confirmed that technicians write spring sizes and component specifics in a **notes section on the HCP ticket**. The tool fetches whichever notes field(s) the job-detail endpoint exposes. `config/rules.yaml` specifies the field name(s) to read so implementation can adapt if HCP uses `service_notes`, `notes`, `description`, or multiple fields:

```yaml
# config/rules.yaml (excerpt)
hcp:
  notes_fields:
    - notes
    - service_notes
    - internal_notes
  # displayed in prompt order; joined with a separator
```

During the interactive walkthrough, all fetched notes are displayed inline in the prompt (see "Weekly run flow"). If the relevant notes field is determined to be a single specific one on first real-data inspection, the list collapses to that one.

### Caching and idempotence

- Every `run_payroll.py` invocation creates a fresh cache directory for that run
- Re-running `run_payroll.py --offline <run_id>` replays from the cache without hitting the API — useful for debugging, price sheet edits, or re-emitting output
- Cache hashes archived in `runs.hcp_cache_sha256` for audit
- Cache is never silently shared across runs; explicitly pass `--reuse-cache <run_id>` to do so

## Data formats

### HCP API normalized shape

The tool maps raw HCP JSON into a normalized `Job` dataclass:

```python
@dataclass
class Job:
    hcp_id: str                      # HCP internal ID (primary key)
    hcp_job_number: str              # human-readable "14404"
    job_date: date                   # scheduled_start → date
    customer_display: str            # "Sarah Johnson"
    description: str                 # HCP description field
    line_items_text: str             # rendered "SERVICES / MATERIALS" block for display
    notes_text: str                  # concatenation of configured notes fields
    amount: float                    # total_amount (tip-inclusive, post-discount)
    tip: float
    subtotal: float                  # archive only
    labor: float                     # archive only
    materials_charged: float         # archive only (NOT used as parts cost)
    cc_fee: float                    # archive only
    discount: float                  # archive only
    assigned_employees: list[EmployeeRef]  # id + display name
    raw_techs: list[str]             # display names in assignment order
```

### Parts price Excel (unchanged from prior CSV-based design)

File: operator-maintained, default path `../prices/parts.xlsx` (overridable in `rules.yaml`).

Real structure (from the provided `Weekly Payment Calculator (9).xlsx`):

- Single sheet named `Pricing`
- Rows 1–7: reference/header content for opener specs (ignored)
- Row 8: main header `Part | List Price | Multiplier | Our Price | 5.5% Sales Tax | Energy Surcharge | Total`
- Rows 9–71: primary price table (one part per row)
- Row 72+: supplemental/outlier entries in different column layouts (e.g. `[98022, 619.47, 671.06]`, rows 79+ labeled `3/2/26 UPDATE`)

**Parser strategy:**

- Locate the header row by scanning for a row containing both `Part` and `Total` in column positions
- Read rows below the header until hitting a row with no string in the `Part` column and no numeric `Total`
- For rows below the primary table, apply a tolerant second pass: treat any row where column A is a non-empty string and the last numeric cell in the row is a positive number as a supplemental part (column A = name, last numeric = cost). Emit a `supplemental: <name>` warning.
- **Cost used for commission math is the `Total` column** (after sales tax and energy surcharge). Not `Our Price`, not `List Price`.
- Numeric-only part names (e.g. integer `98022`) are stringified before lookup.
- Names are the exact key; fuzzy matching happens at prompt time but the stored key matches the sheet exactly.

## Data model (SQLite)

```sql
runs
  id                     INTEGER PK
  week_start             DATE              -- Monday of week
  week_end               DATE              -- Sunday
  hcp_cache_dir          TEXT              -- data/hcp_cache/<run_id>/
  hcp_cache_sha256       TEXT              -- hash of combined cache contents
  price_sheet_path       TEXT
  price_sheet_sha256     TEXT              -- audit: which prices were used
  created_at             TIMESTAMP
  status                 TEXT              -- 'in_progress' | 'final' | 'superseded'
  notes                  TEXT

jobs
  id                     INTEGER PK
  run_id                 INTEGER FK runs.id
  hcp_id                 TEXT              -- HCP internal id
  hcp_job_number         TEXT              -- "14404"
  job_date               DATE
  customer_display       TEXT
  description            TEXT
  line_items_text        TEXT              -- HCP line items rendered for display/audit
  notes_text             TEXT              -- tech notes concatenated
  amount                 REAL
  tip                    REAL
  subtotal               REAL              -- archive only
  labor                  REAL              -- archive only
  materials_charged      REAL              -- archive only
  cc_fee                 REAL              -- archive only
  discount               REAL              -- archive only
  raw_techs              TEXT              -- comma-separated display names
  owner_tech             TEXT              -- resolved primary owner
  skip_reason            TEXT              -- 'zero_revenue' | NULL
  notes                  TEXT

job_parts
  id                     INTEGER PK
  job_id                 INTEGER FK jobs.id
  part_name              TEXT              -- exact match to price sheet
  quantity               INTEGER
  unit_price             REAL              -- snapshot from price sheet Total col
  total                  REAL              -- quantity * unit_price
  source                 TEXT              -- 'manual' (v1)

commissions
  id                     INTEGER PK
  job_id                 INTEGER FK jobs.id
  tech_name              TEXT
  kind                   TEXT              -- 'primary' | 'override'
  basis                  REAL              -- amount - tip - parts_cost, clamped at 0
  commission_pct         REAL              -- 0.20, 0.18, or 0.02
  commission_amt         REAL              -- basis * commission_pct (0 on override)
  bonus_amt              REAL              -- Charles step-tier bonus (0 elsewhere)
  override_amt           REAL              -- basis * 0.02 on override rows (0 on primary)
  tip_amt                REAL              -- tip on primary row, 0 on override
  total                  REAL              -- commission_amt + bonus_amt + override_amt + tip_amt
```

**Notes:**
- `runs.status = 'superseded'` is used when an in-progress run is discarded and replaced with a fresh one
- `job_parts.source = 'manual'` is the only value in v1 (future work could add `'hcp_parsed'` if we later decide to auto-infer from HCP line items)
- `commissions` has one `primary` row per non-skipped job, and optionally one `override` row if Charles was co-listed on a non-Charles-owned job

## Commission math

Pure function: `compute_commissions(job, techs_config, bonus_tiers_config) → list[CommissionRow]`. No I/O.

```python
parts_cost = sum(p.total for p in job.parts)
basis = max(0.0, job.amount - job.tip - parts_cost)
rows = []

owner_cfg = techs_config.get(job.owner_tech)
primary_comm = round(basis * owner_cfg.commission_pct, 2)

bonus = 0.0
if owner_cfg.bonus_tier:
    bonus = compute_step_bonus(basis, bonus_tiers_config[owner_cfg.bonus_tier])

rows.append(CommissionRow(
    tech_name=job.owner_tech,
    kind='primary',
    basis=basis,
    commission_pct=owner_cfg.commission_pct,
    commission_amt=primary_comm,
    bonus_amt=bonus,
    override_amt=0.0,
    tip_amt=job.tip,
))

supervisor = techs_config.supervisor
if job.owner_tech != supervisor and supervisor in job.raw_techs:
    sup_cfg = techs_config.get(supervisor)
    override_amt = round(basis * sup_cfg.override_on_others_pct, 2)
    rows.append(CommissionRow(
        tech_name=supervisor,
        kind='override',
        basis=basis,
        commission_pct=sup_cfg.override_on_others_pct,
        commission_amt=0.0,
        bonus_amt=0.0,
        override_amt=override_amt,
        tip_amt=0.0,
    ))

return rows


def compute_step_bonus(basis, tier):
    if basis < tier.band_start:
        return 0.0
    bands_above_start = int((basis - tier.band_start) // tier.band_width)
    return tier.bonus_start + bands_above_start * tier.bonus_step
```

### Rounding and aggregation

- All money values rounded to 2 decimals via Python `round()`
- Rounding applied per line item, then summed
- Weekly per-tech total = sum of all `commission_amt + bonus_amt + override_amt + tip_amt` across that tech's rows

### Negative basis

When `parts_cost > amount - tip`:
- `basis` clamped to `0.0`
- `commission_amt = 0`, `bonus_amt = 0`, `override_amt = 0`
- `tip_amt` still flows to owner
- Job flagged with `⚠ parts exceeded revenue` note in Jobs sheet

### Zero revenue

When `job.amount = 0` (service calls, estimates):
- No interactive parts prompt (skip)
- `skip_reason = 'zero_revenue'`
- No commission rows written
- Job still appears in the `Jobs` output sheet for audit, with the skip reason

## Weekly run flow

Command: `python scripts/run_payroll.py --week <Monday YYYY-MM-DD>`

(Week is explicit to avoid ambiguity. `--week today` resolves to the current Monday; `--week last` resolves to the prior Monday.)

1. **Validate preconditions.**
   - Load `.env`; fail fast if `HCP_API_KEY` missing
   - Validate YAML configs; fail fast on parse errors
   - Open the price sheet and confirm the header row is found
   - Probe HCP API with a cheap call (e.g. `GET /employees` or similar); fail fast on auth error
   - If an `in_progress` run exists for this week: prompt `Resume [r] / discard and restart [d] / abort [a]` (default resume)

2. **Create run row.** Insert `runs` with `status='in_progress'` and the week boundaries.

3. **Sync from HCP.**
   - Fetch the list of completed jobs for the week
   - For each job, fetch full detail (notes + line items)
   - Archive all raw responses under `data/hcp_cache/<run_id>/`
   - Normalize into `Job` dataclasses via `hcp_normalize.py`
   - Insert `jobs` rows

4. **Resolve ownership.** Apply the Charles rule to every job. Interactive prompt only when the rule can't resolve (Charles + 2+ non-supervisor techs; or no Charles with multiple techs listed).

5. **Load price sheet.** Build `{name: Total}` lookup; log warnings for supplemental rows.

6. **Interactive parts walkthrough.** For each non-skipped job in date order, display:

   ```
   ──────────────────────────────────────────────────────────
   Job #14404 — 2026-04-14 — Sarah Johnson
   Amount: $1,687.00   Tip: $0.00   Owner: Maurice
   Listed techs: Charles Rue, Maurice

   HCP Description: Labor

   HCP Line Items:
     SERVICES: Labor - $185.00
     MATERIALS:
       UltraLife Double Spring Replacement (2 Car) - $869.00
       Roller (Titan Strength) - $350.00
       End Bearing Plate (Pair) - $134.00
       Torsion Tube 16' - $145.00

   Tech notes (from HCP):
     ───────────────────────────────────────
     Replaced 2x .243 #2 30.5" springs
     New 7' cables (pair), 2 drums, 10 long-stem rollers
     New bottom brackets, center bracket, tube (16')
     Door cycled 3x, no issues
     ───────────────────────────────────────

   Enter parts used (blank line to finish this job):
     part > .243█
       .243 #1 - 27.5"       $43.72
       .243 #2 - 30.5"       $45.76
     qty > 2
     added: .243 #2 - 30.5" × 2 @ $45.76 = $91.52
     part > _
   ```

   - Autocomplete: fuzzy match (substring + prefix bias) against price sheet names
   - Quantity prompt: default `1`, integer
   - Unknown part names rejected with closest matches listed; no silent $0 inserts
   - Job-level shortcuts: `s` skip (no parts, no commission), `b` back to previous, `q` quit and save progress
   - Each confirmed entry → `job_parts` row with `unit_price` snapshotted from the price sheet at entry time

7. **Compute commissions.** Run `compute_commissions(...)` for every non-skipped job. Insert `commissions` rows.

8. **Write output.** Build `output/payroll_week_of_<week_start>.xlsx` with the three sheets below. Update `runs.status = 'final'`. Print a one-line per-tech summary to the terminal.

### Failure and resume behavior

- Ctrl-C during the walkthrough: save progress, leave `runs.status = 'in_progress'`, exit code 1. Re-running resumes from the next unprocessed job.
- `--edit-job <hcp_job_number>`: re-open parts entry for a single job, delete its `job_parts` and downstream `commissions` rows, recompute on save.
- `--fresh`: discard the in-progress run (soft delete, `status='superseded'`) and start over from step 2. HCP API is re-polled.
- `--offline <run_id>`: replay from an existing cache without hitting HCP. Useful when refining parts entry on a week that was already synced.
- `--dry-run`: run full flow without writing to DB or emitting Excel (tests config + HCP auth + price sheet readability).

## Output Excel format

File: `output/payroll_week_of_<week_start>.xlsx` (e.g. `payroll_week_of_2026-04-13.xlsx`)

Each sheet has a two-line header above the table: run timestamp + HCP cache dir + HCP response count on line 1; price sheet SHA-8 + tech roster summary on line 2. Frozen header rows. Currency formatting `$#,##0.00`.

### Sheet 1: `Summary`

One row per tech. Totals row at the bottom.

| Tech | Jobs | Gross Revenue | Tips | Parts Cost | Basis (Rev−Tip−Parts) | Commission | Bonuses | Overrides | **Final Pay** |
|---|---|---|---|---|---|---|---|---|---|
| Charles Rue | 4 | $2,180.00 | $45.00 | $215.00 | $1,920.00 | $384.00 | $110.00 | $74.30 | **$613.30** |
| Maurice | 9 | $4,820.00 | $80.00 | $512.00 | $4,228.00 | $845.60 | — | — | **$925.60** |
| Nicholas Roccaforte | 7 | $3,410.00 | $25.00 | $388.00 | $2,997.00 | $539.46 | — | — | **$564.46** |
| **Total** | 20 | $10,410.00 | $150.00 | $1,115.00 | $9,145.00 | $1,769.06 | $110.00 | $74.30 | **$2,103.36** |

- `Gross Revenue` = sum of `amount` for primary-owned jobs
- `Tips` = sum of `tip` on those same jobs (100% pass-through)
- `Parts Cost` = sum of parts cost on those same jobs
- `Basis` = `Gross Revenue − Tips − Parts Cost` on primary-owned jobs (overrides not included)
- `Commission` = sum of `commission_amt` (primary rows only)
- `Bonuses` = sum of `bonus_amt` (Charles, own primary jobs)
- `Overrides` = sum of `override_amt` (Charles, co-listed on others' jobs)
- `Final Pay` = `Commission + Bonuses + Overrides + Tips`

### Sheet 2: `Jobs` (audit trail)

One row per job.

| Job # | HCP Id | Date | Customer | Amount | Tip | Parts Cost | Basis | Listed Techs | Owner | Primary Comm | Charles Bonus | Charles Override | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 14404 | abc123 | 2026-04-14 | Sarah J. | $1,687.00 | $0.00 | $215.00 | $1,472.00 | Charles Rue, Maurice | Maurice | $294.40 | — | $29.44 | |
| 14371 | def456 | 2026-04-13 | Bob S. | $1,157.00 | $25.00 | $410.00 | $722.00 | Charles Rue | Charles Rue | $144.40 | $50.00 | — | solo; bonus tier $700 |
| 14406 | ghi789 | 2026-04-14 | Kim L. | $212.75 | $27.75 | $0.00 | $185.00 | Nicholas | Nicholas | $33.30 | — | — | under $400 — no bonus |
| 14044-2 | jkl012 | 2026-04-12 | Jim R. | $0.00 | $0.00 | — | — | Charles Rue | Charles Rue | — | — | — | zero revenue — skipped |

`HCP Id` is used to build a direct hyperlink to the job in HCP once the URL pattern is confirmed on first real run.

### Sheet 3: `Parts` (per-part detail)

| Job # | Part Name | Qty | Unit Price | Total | Source |
|---|---|---|---|---|---|
| 14404 | .243 #2 - 30.5" | 2 | $45.76 | $91.52 | manual |
| 14404 | 7' Cables | 2 | $8.47 | $16.94 | manual |
| 14404 | Drum | 2 | $6.86 | $13.72 | manual |
| 14404 | Long-Stem Roller | 10 | $4.00 | $40.00 | manual |
| 14404 | End Plate (pair) | 1 | $10.52 | $10.52 | manual |
| 14371 | 7' LiftMaster 2220L | 1 | $316.36 | $316.36 | manual |

## CLI surface

### `scripts/run_payroll.py --week <YYYY-MM-DD>`
Main weekly command. Flags:
- `--resume` — explicitly resume an in-progress run for this week (default behavior on conflict)
- `--fresh` — discard in-progress run for this week and start over
- `--edit-job <hcp_job_number>` — re-open parts entry for a single job in the most recent run
- `--offline <run_id>` — replay from an existing HCP cache without re-polling the API
- `--dry-run` — run full flow without DB writes or Excel output; validates everything end-to-end

### `scripts/sync.py --week <YYYY-MM-DD>`
Fetch jobs + details from HCP and cache locally. No DB writes, no processing. Useful for pre-staging before the full run, or for debugging the API layer.

### `scripts/reprint.py --week <YYYY-MM-DD>`
Regenerate the Excel output from DB without prompts or API calls.

### `scripts/show_run.py --week <YYYY-MM-DD>`
Print the Summary table to the terminal. `--jobs` flag prints per-job detail.

### `scripts/validate_config.py`
Sanity-check YAMLs, open the price sheet and count rows, probe HCP API with a cheap call to confirm auth. Zero side effects.

## SKILL.md

Follows the frontmatter pattern from `twins-media-generator/SKILL.md`. Description triggers on phrases: `run payroll`, `process payroll`, `weekly payroll`, `calculate commissions`, `payroll report`, `pull from HCP`, `tech pay`, `commission`, `bonus`, `Housecall Pro`, and related.

## Failure modes handled explicitly

| Scenario | Behavior |
|---|---|
| `HCP_API_KEY` missing or invalid | Fail fast before any processing; actionable error |
| HCP rate limit (429) | Exponential backoff honoring `Retry-After`; up to 5 retries |
| HCP network/5xx | Up to 3 retries with backoff; typed error if exhausted |
| HCP returns zero jobs for the week | Warn but proceed; valid outcome (e.g. vacation week) |
| Job has empty `assigned_employees` | Warn, prompt operator to assign from tech roster |
| `assigned_employees` contains unknown tech | Warn, prompt to (a) pick from known, (b) add to `techs.yaml` and re-run |
| Notes field is empty on a job | Show "(no tech notes on ticket)" in prompt; operator enters parts from memory or skips |
| Part name not in price sheet | Reject entry, show fuzzy matches, prompt again. No silent $0. |
| Price sheet edited mid-run | Hash captured at run start; prices locked within a run |
| Duplicate `hcp_id` in API response | Error out — should not occur |
| Ctrl-C during prompts | Save progress, mark `in_progress`, exit cleanly |
| Supplemental price rows in irregular layout | Parse tolerantly, warn |
| `amount` < 0 in HCP response | Sanity-check failure; abort with actionable message |

## Testing strategy

- **Unit tests** for `engine/commission.py`, `engine/ownership.py`, `engine/price_sheet.py` using pure-function coverage of every rule and edge case
- **Unit tests** for `engine/hcp_normalize.py` using sanitized fixture JSON responses (captured once from a real API call, then scrubbed of PII)
- **Unit tests** for `engine/hcp_client.py` using `respx` to mock `httpx` at the HTTP layer: auth header, pagination, 429 retry, 500 retry, 401 immediate-fail
- **Bonus tier edge cases:** $399.99 → $0, $400 → $20, $400.01 → $20, $499.99 → $20, $500 → $30, $1000 → $80, $5000 → $480
- **Ownership edge cases:** solo Charles, Charles + 1 other, Charles + 2 others (prompt), sole non-Charles tech, multiple non-Charles (prompt)
- **End-to-end test:** scripted flow over a fixture HCP cache (offline mode) + fixture price sheet with `prompt_toolkit` prompts stubbed to canned responses; asserts the output Excel summary has expected row values
- No live API calls in CI. Real API is exercised only during manual dev runs with a test key.

## Dependencies

`pyproject.toml`:

```toml
[project]
name = "twins-payroll"
version = "0.1.0"
requires-python = ">=3.11"

dependencies = [
    "httpx>=0.27",            # HCP API client
    "python-dotenv>=1.0",     # .env loading
    "openpyxl>=3.1",          # Excel read/write
    "prompt_toolkit>=3.0",    # autocomplete prompts
    "pyyaml>=6.0",
    "rich>=13.0",             # pretty terminal tables
]

[project.optional-dependencies]
dev = [
    "pytest>=8.0",
    "pytest-xdist>=3.5",
    "respx>=0.21",            # HTTP mocking for httpx
]
```

No runtime network deps beyond HCP itself. Fully offline replay via `--offline <run_id>`.

## Out-of-scope but future-enabling

- **Webhook-driven auto-draft runs (v2):** a lightweight FastAPI receiver subscribed to HCP `job.closed` events would create pending entries in a `hcp_events` table. A new `scripts/draft_from_events.py` would pre-populate a run from accumulated events, skipping step 3's API polling. Requires a small always-on process or serverless endpoint; deferred to v2 to keep v1's footprint minimal.
- **Product→component mapping (v2):** `config/product_maps.yaml` could pre-fill the parts prompt when HCP line items contain known product names (e.g. "UltraLife Double Spring Replacement (2 Car)" → default component list). Operator adjusts rather than enters from scratch.
- **Payroll provider export (v2):** a new script consumes the same DB rows and emits Gusto/QuickBooks/ADP CSVs — zero changes to `engine/`.
- **Multi-week / YTD reports (v2):** the DB already stores every run; rollups are a query away.
- **CSV ingest fallback (v2 if ever needed):** the normalized `Job` dataclass is the seam; a `csv_loader.py` can be added parallel to `hcp_sync.py` without touching downstream code.

## Open questions for first-run verification

These are confirmed on the first real API call during implementation and pinned into `config/hcp.yaml`:

1. **Exact HCP endpoint paths + pagination cursor names.** The design uses approximate RESTful paths (`/jobs`, `/jobs/{id}`, `/employees`); exact forms confirmed on first probe.
2. **Canonical notes field name.** The tool reads a configurable list of notes fields from the job-detail response; first-run inspection pins the exact field(s) containing the spring-size write-downs.
3. **Work-status filter for "jobs that should be on payroll."** Likely `completed` or `invoice_sent`; confirmed by comparing a week's API results to the CSV the operator currently pulls.
4. **Week boundary convention.** Monday-to-Sunday is the default; adjustable in `rules.yaml` if the business prefers a different cut.
5. **HCP ticket deep-link URL format.** For the Excel `HCP Id` column hyperlink (e.g. `https://pro.housecallpro.com/app/jobs/{id}`).
6. **Employee matching.** Preferred: by HCP `employee_id` (stable). Fallback: by `first + last` name match. First sync populates `techs.yaml`'s `hcp_employee_id` fields.
7. **`total_amount` semantics.** The design assumes HCP's `total_amount` already reflects discounts (what the customer actually paid). If the API returns pre-discount totals with a separate `discount` field, the commission formula becomes `basis = (amount − discount) − tip − parts_cost`. Verified on first real run by comparing to one known-discounted job from the sample export (job `14411`: Subtotal $3398 − discount = Amount $3223).

None of these block implementation — they are pinned during the first real run and the design accommodates either resolution.
