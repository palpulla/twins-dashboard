# Twins Payroll

Weekly payroll automation for Twins Garage Doors. Pulls jobs + tech notes from Housecall Pro, walks the operator through interactive parts entry, applies commission rules, and emits a weekly Excel summary.

See `SKILL.md` for the weekly ritual.
See `docs/superpowers/specs/2026-04-16-payroll-automation-design.md` for the design spec.

## Setup

```bash
cd twins-payroll
python -m venv .venv
source .venv/bin/activate
pip install -e ".[dev]"
cp .env.example .env
# edit .env with your HCP_API_KEY
```

## Run the weekly payroll

```bash
python scripts/run_payroll.py --week 2026-04-13
```
