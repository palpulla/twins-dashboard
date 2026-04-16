# Twins Content Engine

Content strategy + generation engine for Twins Garage Doors.

See [SKILL.md](SKILL.md) for usage. See the design spec at
`../docs/superpowers/specs/2026-04-16-content-engine-design.md`.

## Setup

```bash
python3.11 -m venv .venv
source .venv/bin/activate
pip install -e ".[dev]"
export ANTHROPIC_API_KEY="sk-ant-..."
```

## Running tests

```bash
pytest                        # unit + smoke tests
INTEGRATION=1 pytest -m integration   # + real API integration test
```
