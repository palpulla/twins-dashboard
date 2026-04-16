---
name: twins-payroll
description: Automate weekly Twins Garage Doors payroll. Use this skill whenever the user asks to run payroll, process payroll, calculate commissions, generate a weekly payroll report, pull jobs from Housecall Pro for payroll, compute tech pay, or reconcile parts costs against revenue for commission calculation. Also trigger on phrases like "weekly payroll", "HCP payroll", "commission report", "tech commission", "bonus tier", "supervisor override", "payroll week of", "parts cost reconciliation", or any request involving Twins technicians (Charles, Maurice, Nicholas) and pay. Pulls jobs + tech notes directly from Housecall Pro API, walks the operator through interactive parts entry with cost-sheet autocomplete, applies commission rules (20/20/18% base + Charles step-tier bonus + 2% supervisor override on co-listed jobs), and emits a weekly Excel file with Summary / Jobs / Parts sheets.
---

# Twins Payroll

Weekly payroll automation for Twins Garage Doors. Pulls jobs + technician notes directly from Housecall Pro, reconciles parts cost from a local Excel price sheet, applies the commission rules, and emits a weekly Excel summary.

## Setup (one-time)

```bash
cd twins-payroll
python -m venv .venv
source .venv/bin/activate
pip install -e ".[dev]"
cp .env.example .env
# edit .env with your HCP_API_KEY
```

Place or symlink your parts price Excel at the path configured in `config/rules.yaml` (default: `../prices/parts.xlsx`).

Validate config:

```bash
python scripts/validate_config.py
```

## Weekly ritual

1. Run the weekly command, with Monday of the target week:
   ```bash
   python scripts/run_payroll.py --week 2026-04-13
   ```
2. For each job, review the displayed HCP line items + tech notes. Type the actual cost-sheet component names used (with autocomplete). `s` skips a job, `q` quits and saves progress.
3. When done, open `output/payroll_week_of_2026-04-13.xlsx` and copy the Summary row values into your payroll provider.

## Commission rules (source of truth: `config/techs.yaml` and `config/bonus_tiers.yaml`)

- **Charles Rue** (supervisor): 20% of `(amount − tip − parts_cost)` + per-job step-tier bonus + 2% override on jobs where he's co-listed with another tech
- **Maurice**: 20% of `(amount − tip − parts_cost)`
- **Nicholas Roccaforte**: 18% of `(amount − tip − parts_cost)`
- **Tips**: 100% pass-through to the owner tech
- **Ownership rule**: if Charles is listed with one other tech, the other tech owns the job

Charles's bonus tier (applied per job):
- Below $400 basis → $0
- $400–$499 → $20
- $500–$599 → $30
- $600–$699 → $40, etc. (+$10 per $100 band)

## Other commands

- `python scripts/reprint.py --week 2026-04-13` — regenerate Excel from the DB without prompts or HCP calls
- `python scripts/show_run.py --week 2026-04-13 [--jobs]` — print a past run's summary to the terminal
- `python scripts/sync.py --week 2026-04-13` — only fetch from HCP and archive, no processing
- `python scripts/validate_config.py` — sanity-check YAMLs + price sheet + API key

## Where things live

- `config/*.yaml` — commission rates, bonus tiers, HCP endpoint config, week boundary, price sheet path
- `data/payroll.db` — SQLite: runs, jobs, parts, commissions (all history)
- `data/hcp_cache/<run_id>/*.json` — archived raw HCP responses per run
- `output/payroll_week_of_*.xlsx` — weekly Excel outputs

## Troubleshooting

- "HCP_API_KEY not set" → fill in `.env`
- "Part not in price sheet" → add the part to the Excel price sheet, or type it exactly as it appears there
- "Ambiguous owner" → a job has Charles + 2 others, or 2 non-Charles techs; the CLI prompts you to pick
