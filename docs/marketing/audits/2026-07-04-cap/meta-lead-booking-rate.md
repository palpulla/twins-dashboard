# Meta lead → HCP booking analysis (CAP doc §3.2 critical unknown)

**Method:** jobs in jwrpj with `lead_source` in ('Facebook','Facebook Ads'), created 2026-05-01..06-30, vs Meta account spend/leads (live pull). Customer identity via hcp_data customer id. Caveats: (1) includes any organic-Facebook walk-ins the office tagged Facebook; (2) June cohort still maturing (open estimates not yet completed); (3) lead→job lag can cross month boundaries.

| Month | Meta spend | Meta leads | FB-tagged HCP jobs created | Distinct customers | Estimates | Completed jobs | Earned revenue | Canceled |
|---|---|---|---|---|---|---|---|---|
| May | $2,043.09 | 30 | 39 | 21 | 14 | 15 | $7,515.95 | 9 |
| June | $2,015.10 | 24 | 12 | 8 | 4 | 6 | $2,857.00 | 2 |
| Total | $4,058.19 | 54 | 51 | 29 | 18 | 21 | $10,372.95 | 11 |

## Answers
- **Do instant-form leads book?** Yes. ~29 distinct Facebook-attributed customers from 54 leads ≈ **54% booking rate** (upper bound; treat 40–54% as the honest range given organic contamination). The doc's decision threshold was 30%.
- **Cost per booked customer:** $4,058.19 / 29 ≈ **$140** — cheaper than Search's $166–$234 per *raw conversion* (not even booked).
- **Revenue return:** $10,372.95 earned on $4,058.19 spend ≈ 2.6x, with June still maturing.
- **June softness is real but smaller than lead counts imply:** bookings fell harder (21→8 customers) than leads (30→24), consistent with creative fatigue attracting lower-intent form fills. Supports the creative-refresh plan; does NOT support cutting Meta.

**Verdict for the doc's §3.2 question:** Meta is not "expensive noise" — it produces booked, completed, revenue-positive jobs at a cost per booked customer below Search. Scale decision still waits for tracking fixes, but the cut-Meta scenario is off the table.
