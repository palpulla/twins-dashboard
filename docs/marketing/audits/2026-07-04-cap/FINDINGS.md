# Marketing Corrective Action — Audit Findings (Phase 0)

2026-07-04. Sources: live Google Ads API (customer 7171993484), live Meta Ads account, live site tests, jwrpj database. Raw pulls in `google-ads/` and `meta/`.

## Headline: June wasn't a demand problem. Paid clicks landed on pages that cannot convert.

**The #1 finding (new, not in the CAP doc):** the embedded lead form on the live paid landing pages posts to a third-party GHL location (`ATDh3QGRFcbWAxmrvh2G`) and gets **HTTP 401**. Visitors see nothing — the button greys out. Form leads from paid traffic are not "untracked"; they are **lost entirely**, and have been since at least early June (account-wide form conversions: May 5, June 0). The install LP is worse: it has **no form at all** and its "GET STARTED" CTAs are dead buttons. Every dollar of June's $1,191.69 install spend bought clicks to a page whose only working action is a phone link.

## The CAP doc's open questions, answered

| Question (doc §) | Answer |
|---|---|
| What's in the "Conversions" column? (§1.2) | 13 enabled actions feed bidding, including "Click to call" (a tap, not a call), a MANY_PER_CLICK Zapier upload, and 4 overlapping webpage form actions. Full inventory in `google-ads/01`. |
| Why 34 → 19? (§1.1) | Repair held steady (10→10 ad calls, its real signal). The drop = PMax 15→8 and Install 5→0. Combined with the form 401, June's "decline" is mostly broken conversion paths, not worse marketing. |
| Was May's Install 5 real? (§2.2) | Mostly: 4 phone calls (2 ad-asset calls + 2 GHL pool calls) + 1 form. Real leads. Last conversion May 22; zero since. |
| PMax's cheap conversions real? (§2.3) | More real than feared: mostly ad-asset phone calls (8 May, 5 June) + click-to-call taps (5, 2). View-through = 0 across account. The $19.93–$40 CPA is inflated by tap-actions but isn't fake-pageview inflation. The report's 0.26–0.36% "conversion rate" uses interactions (~3,000–4,200) as denominator — confirmed, matches doc §1.4 math. |
| Search-term waste? (§2.1) | ~$340 competitor-brand terms (Precision, Jenko, Rod's, Bower City, Fuller, Ideal), ~$193 DIY/cost/retail (Menards, Home Depot), ~$140 supplier intent, plus out-of-area (Watertown, Janesville, Hayward). Broad match live on both campaigns (11 + 6 keywords). |
| Geo setting? (§2.1) | Already PRESENCE (doc's guess wrong). Waste comes through broad match, not geo-interest. |
| Bidding? (§2.1) | Maximize Conversions, NO tCPA, on all three campaigns — chasing 13 polluted actions. |
| Change history | ZERO account changes since June 5 (30-day window). Nobody touched the account while Install burned $275+/week at zero conversions. |
| Meta $0-delivery campaigns (§3.1) | Launched late June; both delivering now (Call Leads $48.05, Messenger $20.34 through Jul 4). Not stuck. |
| Custom-conversion overlap (§1.1) | Confirmed live: Primary rule `URL contains /thank-you` (also matches /ei-thank-you); Secondary dead since Nov 2025. |
| Do Meta leads book? (§3.2 critical unknown) | **Yes: ~54% booking rate** (29 distinct FB-attributed customers from 54 leads), 21 completed jobs, $10,372.95 earned revenue on $4,058.19 spend (~2.6x). Cost per booked customer ≈ $140 vs Search's $166–$234 per raw conversion. Cut-Meta is off the table; June softness = creative fatigue (bookings fell harder than leads). |
| 833 number ownership (§2.4) | Partially answered: GHL number pools exist ("GHL Website Number Pool" conversions fire; /go/ pages dial (608) 447-5351). The orphan /wi/ install LP rotates CALIFORNIA numbers ((916) 775-0615, (916) 712-3699). Five different numbers visible across the site. |

## Phase 1 go/no-go (as executed)

| Fix | Verdict |
|---|---|
| Fix/replace LP forms → Twins GHL location (NEW, top priority) | GO — biggest revenue leak |
| Negative keyword list (competitors + DIY/retail + out-of-area) | GO — refined from actual terms |
| Cap Install budget $15/day | GO — page can't convert; every day is ~$38 wasted |
| Demote junk conversion actions (Click to call, Zapier, engagement) | GO — keep ad calls + real forms + GHL pool calls primary |
| Brand negatives | GO |
| Geo Presence-only | SKIP — already set |
| tCPA on Repair | DEFER — set after conversion actions are clean for ~2 weeks, else it locks in pollution |
| Meta pixel Lead-rule fix + custom-conv separation | GO |
| Website sprint (call bar, phone unify, zoom, hero badge, metas, DIY CTAs, schema) | GO — plus new item: kill/redirect the orphan 916-number LP |
