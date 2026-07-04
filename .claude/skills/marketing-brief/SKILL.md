---
name: marketing-brief
description: Generate the weekly Twins Garage Doors Monday marketing brief — spend, jobs and earned revenue by source, reviews, content output, and 2-3 proposed moves for Daniel's approval. Use when the user asks for the "marketing brief", "Monday brief", "weekly marketing review", or a scheduled Monday run fires.
---

# Weekly Marketing Brief Generator

Produce the Monday brief in chat. Never send it by email/SMS/push. Never execute a proposed move — proposals wait for Daniel's approval.

## Windows

- **Jobs / revenue:** last complete payroll week, Friday–Thursday (`weekStartsOn: 5`; parse DB dates as local midnight). State the exact dates.
- **Spend:** same calendar span; always report `last_data_day` per platform so stale syncs are visible.
- Compare each number to the prior week and to the baseline (`docs/marketing/baselines/`).

## Data pull

Run the exact queries in `docs/marketing/DATA-SOURCES.md` (read-only, Supabase project `jwrpjuqaynownxaoeayi`) for:
1. Spend by canonical channel (§1)
2. Completed jobs + earned revenue by lead source (§2 — earned = `outstanding_balance = 0`, estimates excluded, "Unknown"/blank stays Unattributed; never classify by guesswork)
3. Review velocity (§3 — while the `reviews` table is empty, report card clicks only and flag the gap)
4. GHL new contacts (§4)
5. Content output (§5 — count approved drafts + note what shipped)
6. Source-capture compliance: % of the week's booked jobs with lead_source 'Unknown' or blank (`jobs.created_at` in window). Baseline 52% over the 180 days before 2026-07-03; the CSR intake standard started 2026-07-03, so this number should fall week over week — flag if it doesn't.

If any query returns nothing, write "no data", not a number.

## Brief template

```
# Marketing Brief — week of <Fri> to <Thu>

## What ran
<content published, campaigns live, sends — bullets>

## Funnel by channel
<table per channel: Spend → Leads → Qualified leads → Booked jobs → Completed jobs → Earned revenue.
KPI definitions are docs/marketing/KPI-DEFINITIONS.md, reused verbatim every week.
Stages with no data source yet (qualified tagging, call tracking) print "not yet measured" — never a guess.
Leads = jobs created with that lead_source (proxy until form/call tracking lands; say so in a footnote).>

## Spend detail
<table: source, spend, last_data_day; flag stale syncs>

## Reviews & leads
<card clicks, GHL contacts, review gap status>

## Appendix — traffic metrics
<clicks, impressions, CPC, CTR — demoted here per CAP 2026-07; never in the headline tables>

## Proposed moves (need your call)
1. <move> — cost $X, expected effect, kill criterion. Approve?
2. ...
```

## Rules for proposed moves

- 2–3 moves max, drawn from `docs/marketing/BACKLOG.md` or flagged explicitly as new.
- Every paid move states: test cap in dollars, kill/scale criterion, where the result will show up in next week's brief.
- Full dollar amounts ($5,243, never $5k). Real data only. No Lovable references. KPI math is immutable — read canonical calculations, never reinvent.
- Authority matrix in `docs/marketing/STRATEGY.md` governs: nothing that spends money, launches a campaign, or changes customer-facing copy happens without Daniel's approval.

## After Daniel responds

Record approved/rejected moves by updating `docs/marketing/BACKLOG.md` (status column) and commit. Approved builds go through the normal spec → plan → build cycle.
