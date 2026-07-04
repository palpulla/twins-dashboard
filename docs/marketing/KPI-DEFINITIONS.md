# KPI definitions (written once, reused verbatim — CAP doc §6)

| KPI | Definition | Source of truth |
|---|---|---|
| Lead | Qualified call ≥60s, form submit, booking, or qualified text conversation | Call tracking + forms + Housecall Pro (interim proxy: HCP jobs created with the channel's lead_source) |
| Qualified lead | In service area, real service need, reachable | Office tagging in Housecall Pro within 24h (not yet measured) |
| Cost per qualified lead | Channel spend ÷ qualified leads | Computed |
| Booked appointment / booking rate | Jobs scheduled ÷ qualified leads | Housecall Pro (jwrpj `jobs`) |
| Cost per booked appointment | Channel spend ÷ booked jobs | Computed |
| Missed-call rate | Unanswered tracked calls ÷ total tracked calls, by hour | Call tracking (not yet measured) |
| Speed to lead | Median minutes from lead to first outbound contact attempt | HCP / call tracking (not yet measured) |
| Show rate | Completed appointments ÷ booked | Housecall Pro |
| Close rate | Sold jobs ÷ completed appointments | Housecall Pro (canonical kpi-calculations fns) |
| Earned revenue | `revenue_amount` where `outstanding_balance = 0`, estimates excluded | jwrpj `jobs` (immutable dashboard math) |
| Revenue per lead / ROAS | Attributed completed-job earned revenue ÷ leads; revenue ÷ spend | HCP + offline import |

Stages marked "not yet measured" print exactly that in the brief until their plumbing ships. Never approximate them.
