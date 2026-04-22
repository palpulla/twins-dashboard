"""
Entry point: delegates to twins_payroll.sync_rules.main().

Usage:
    python sync_rules.py [--effective-date YYYY-MM-DD] [--dry-run]

See twins_payroll/sync_rules.py for full docs.
"""
import sys
from twins_payroll.sync_rules import main

if __name__ == "__main__":
    sys.exit(main())
