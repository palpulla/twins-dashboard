# Meta Ads Overhaul, v2

**Date:** 2026-08-10
**Account:** act_388398022876424 ("Twins Garage Doors", business_id 120957490303553)
**Supersedes:** `2026-08-09-meta-ads-overhaul-design.md` and its reviewer handoff
**Status:** Executable plan, revised after external paid-social review

---

## 0. Verdict

**Proceed with changes.** The two-conversion-action split is a reasonable delivery backbone. The
v1 plan overweighted Meta's learning status, could not distinguish profitable offers from cheap
platform actions, and used decision thresholds contradicted by the account's own revenue data.

What survives from v1 unchanged: preserve the working Call Leads campaign, archive rather than
delete history, broad targeting inside Madison plus 25 miles, no interest layers at launch, owner
on camera, AI spokesperson retired, unrelated-spectacle creative removed, tracking number verified
before launch, Messenger greeting fixed, named speed-to-lead owner, calls and forms kept separate
at the optimization-action level.

What changed: the economics, the thresholds, the offer-test design, the kill rules, and every
claim that treated a platform metric as a business result.

### Confidence tags

| Tag | Meaning |
|---|---|
| **[VERIFIED]** | Directly observed in the Meta API or the company database |
| **[DERIVED]** | Arithmetic on verified inputs only |
| **[MIXED]** | Arithmetic combining verified and assumed inputs |
| **[ASSUMED]** | Owner estimate or judgment call, not measured |
| **[UNKNOWN]** | Named gap |

---

## 1. Fixed constraints

Not open for revision.

- $90/day, roughly $2,700/month
- Madison plus 25 miles. Milwaukee deferred
- **All four offers receive a real test.** Testing all four does not require them to run
  simultaneously or hold equal permanent budget. It requires each to get defined spend, valid
  tracking and an explicit decision window
- Owner on camera. AI spokesperson retired

---

## 2. Economics: what is assumed versus what is observed

**[ASSUMED, owner-reported]** Roughly 80% of inbound calls are booked, 63% of booked opportunities
close, average sold-job revenue is $860. **These values have not been validated against call,
booking, close or collected-revenue records.**

**[MIXED]** Assumed call-to-sale rate 80% × 63% = **50.4%**. Assumed expected revenue per inbound
call 50.4% × $860 = **$433.44**. At $65 per qualified call, implied sold-job CAC $65 ÷ 50.4% =
**$128.97**.

> The $65-per-call and $130-per-sold-job figures are **one assumption expressed at two stages**,
> not two independent validation layers. Passing both is not corroboration.

**[VERIFIED]** The Meta-attributed sample contains 28 completed jobs and $14,440.00 revenue.

**[DERIVED]** Revenue per completed Meta job **$515.71**. Media cost per completed Meta job
$8,791.21 ÷ 28 = **$313.97**. Media consumed $8,791.21 ÷ $14,440.00 = **60.9% of attributed
revenue**, before gross margin, overhead or agency cost.

**The $860 assumption and the $515.71 observation are unreconciled.** Do not silently prefer the
higher figure.

**[MIXED]** Applying a 15%-of-revenue marketing ceiling to the observed value: 15% × $515.71 =
**$77.36 sold-job CAC**, which at the assumed 50.4% call-to-sale rate implies **$38.99 per
qualified call**. Presented as the logical consequence of the stated rule against observed data,
**not as a target**.

### [UNKNOWN] Blocking economic gaps

Gross margin by offer · direct labour and material cost · GoodLeap dealer/financing fees ·
collected versus invoiced revenue · tune-up upsell and attach rate · average revenue by Meta offer
· booking and close rates by channel and offer.

---

## 3. Account state

**[VERIFIED]** 80+ campaigns, 190+ ads across four eras; one active. Legacy ads named `Ad 1`
through `Ad 4`, so no historical creative-concept learning is recoverable. Trailing 30 days to
2026-08-09, $3,582.26 total. Full campaign and ad tables are retained in v1 §2.2 and §2.3 and are
unchanged.

### 3.1 The call campaign is promising, not proven

**[VERIFIED]** `Madison WI – Call Leads` reports 36 results at $22.18 on $798.54. The metric is
**`click_to_call_native_call_placed`**, calls *placed*, not answered, connected or qualified.

> The only campaign reporting calls at a promising platform cost. Its commercial performance is
> **unverified** until those events are matched to unique call records with duration, answer
> status, qualification, booking and sold outcomes.

Meta separately reports call-confirmation clicks, calls placed, 20-second connected calls,
60-second connected calls and callback requests. Which of these the 36 represents must be
documented before any budget increase.

### 3.2 The Review Carousel is unproven

**[VERIFIED]** 324 link clicks, 3.07% CTR, zero recorded leads, $124.91.

> Strong click propensity under a link-click objective. It did **not** demonstrate
> lead-generation ability.

**[DERIVED]** If the true visitor-to-lead rate were 1%, the probability of zero leads across 324
clicks is **3.9%**. At 2%, **0.15%**. So "wrong objective" is plausible but **not a sufficient
explanation on its own.** A broken landing path, a measurement failure or genuinely poor traffic
quality remain live possibilities. Worth exactly one capped retest on a lead objective.

### 3.3 The Challenger failure was not a learning-volume failure

**[DERIVED]** If their optimization event was link clicks, the two Challenger campaigns generated
633 optimization events. They likely succeeded at the objective Meta was given. The failure was
objective selection, post-click experience, measurement, or traffic quality. Remove any
suggestion that they failed for lack of events.

### 3.4 Attribution

**[DERIVED]** 1.64x revenue-to-media-spend.

> An uncertain attribution estimate. Single-touch tracking may under-credit Meta-assisted Google
> conversions, but organic contamination, returning customers, lead-source changes and unmatched
> lead/job cohorts can bias it the other way. **Direction of bias unknown.**

**[UNKNOWN] Cohort mismatch.** The 28 jobs were grouped by completion date, while spend covers the
same calendar window. Those are different cohorts: some completed jobs originated before the
window, and recent installation leads have not matured. All future revenue comparisons must be
matched to **lead-created date**.

### 3.5 Other verified findings, unchanged from v1

Messenger auto-greeting root cause · fabricated before/after in `twins_beforeafter_demo.mp4` ·
dead first second on the cartoon ads · the (608) 688-9109 tracking number defect and its
unverified 916 forward · the eleven finished reels already in `~/Desktop/Twins Marketing/Reels/`.

---

## 4. Learning-phase reality

**[ASSUMED, platform guidance]** Meta cites roughly 50 optimization events per ad set per week to
exit the learning phase. This is a delivery-quality guideline, **not an eligibility test**.
"Learning limited" means less event data, greater variance and potentially higher cost. It does
**not** mean the algorithm never optimizes or that the account cannot be profitable.

**[DERIVED]** Expected optimization events per week:

| Scenario | Events/week |
|---|---:|
| Full $90/day at $22.18 per call | 28.4 |
| $55/day call cell at $22.18 | 17.4 |
| $55/day call cell at $28 | 13.8 |
| $55/day call cell at $40 | 9.6 |
| $35/day form cell at $50 | 4.9 |
| $35/day form cell at $77.48 | 3.2 |
| Four equal $22.50/day cells at $22.18 | 7.1 |
| Four equal $22.50/day cells at $77.48 | 2.0 |

Fifty weekly calls at $22.18 would require **$158.43/day**. Fifty weekly forms at $50 would
require **$357.14/day**.

> **No feasible structure at $90/day reliably reaches 50 weekly events per ad set.** The plan
> cannot be designed around escaping learning. It is designed to minimise fragmentation, preserve
> what works, guarantee enough spend to evaluate each required offer, and measure qualified and
> sold outcomes rather than platform events.

---

## 5. Structure

### 5.1 Call cell, $55/day

**Campaign:** preserve the existing `Madison WI – Call Leads`. Preserve its existing broad ad set.
**No permanent second ad set** for learning isolation.

Two active ads maximum:
1. Urgent-repair control
2. $0 service-call challenger

Creative must be matched: same presenter, same format and approximate duration, same proof level,
same production quality. **Only the offer and CTA differ.** This is what makes the comparison
about the offer rather than the production.

If the challenger receives under roughly $150 across ten days, it was starved, not beaten. Run a
formal 14-day test at $27.50/day per arm, then reconsolidate.

### 5.2 Form cell, $35/day

Two separate campaigns, **only one spending at a time**, separate forms, offer identity preserved
into the CRM:

- `TGD | Forms | Tuneup 49 | Madison | 2026-08`
- `TGD | Forms | Install Financing | Madison | 2026-08`

**Two uninterrupted 45-day viability screens**, not alternating blocks. Alternating every 14 days
creates repeated delivery resets and implies rigour the volume cannot support.

**[DERIVED]** At $35/day and the observed $77.48 CPL:

| Window | Spend | Leads |
|---|---:|---:|
| 14-day block | $490 | 6.3 |
| Two 14-day blocks | $980 | 12.6 |
| **One 45-day block** | **$1,575** | **20.3** |
| Full 90 days on one offer | $3,150 | 40.7 |

> Twenty leads per offer is a **viability screen, not a winner test**. It can answer: does this
> offer produce valid, contactable, qualified prospects; does it produce any booked opportunities;
> are there obvious quality or economics problems; is it worth a larger future test. It **cannot**
> reliably separate a $60 CPL offer from a $75 CPL offer, and it does not control for seasonality.

**Order:** run the $49 tune-up first if GoodLeap category confirmation is not yet complete,
otherwise lead with whichever offer carries greater strategic priority. Reverse the order in a
later test if both remain viable.

### 5.3 GoodLeap category

A campaign promoting GoodLeap financing may relate to financial products and services and may
require the applicable **Special Ad Category** at campaign level, with associated targeting
restrictions and disclosures. **Category confirmation is required before the financing campaign
runs.** This is not a finding of violation, and it is not legal advice. The account operator must
confirm the correct category in Ads Manager and the disclosure requirements with GoodLeap.

Keeping financing in its own campaign also prevents category restrictions from being imposed on
the tune-up ads.

### 5.4 Targeting

Broad, Madison plus 25 miles, no homeowner interest, no home-improvement stack, no lookalike at
launch. Additionally: confirm the location setting prioritises people **living in** the service
area; exclude ZIPs or towns that cannot be served profitably; review recent-lead suppression
separately and do **not** blanket-exclude past customers, who need repairs.

### 5.5 Budget handling

If the call campaign's configured budget is materially below $55/day, step it up over several days
rather than in one edit, and document the expected delivery disruption.

### 5.6 Naming and CRM metadata

Retain the `{Offer}_{Hook}_{Format}_{version}` convention, but state plainly: **ad naming is not
measurement.** Names let you group; they do not isolate variables, because Meta does not allocate
equally or randomly.

Required downstream on every lead: paid channel · campaign ID and name · ad-set ID and name · ad
ID and name · form ID · offer · tracking number · lead-created timestamp. Calls require
offer-level attribution via separate numbers or another defensible method.

---

## 6. Measurement

### 6.1 The hierarchy

Every report must distinguish: Meta-reported result → valid lead → contactable lead → qualified
lead → booked opportunity → held appointment → sold job → completed and collected revenue → gross
profit. A generic call, form lead or conversation is **not** a prospect until reconciled.

**Valid call:** unique caller, reaches the tracking system, not spam, vendor, duplicate, existing
open-job service or internal test.
**Qualified call:** valid, in service area, relevant garage-door need, caller has authority to
book, not accidental.
**Valid form lead:** unique person, working phone or email, not spam, duplicate or clearly false.
**Qualified form lead:** valid, serviceable location, relevant need, decision-maker where
required, reasonable timeline and offer fit.

"Booked opportunity", "held appointment", "sold job" and "completed job" are four different things
and must never be collapsed into "booked job".

### 6.2 The $75 kill rule is removed

**[DERIVED]** Probability of zero results after $75 of spend:

| True cost per result | P(zero after $75) |
|---|---:|
| $50 | 22.3% |
| $65 | 31.5% |
| $77.48 | 38.0% |

Roughly **$150** is needed for a 95% chance of at least one event at a true $50 CPL; roughly
**$195** at a true $65 CPA. The v1 rule would have killed sound ads about a third of the time.

### 6.3 Provisional stop-losses

Bankroll protection, not proof of failure.

- **Immediate stop:** broken number, broken form, policy violation, complaint pattern,
  service-area leakage, material message mismatch
- Stop a call ad after roughly **$130 with zero qualified calls**
- Stop a form creative after roughly **$150 with zero valid, contactable leads**
- Never kill on raw CPA after a single result
- At 5 qualified outcomes: inspect cost per qualified lead and quality pattern
- At 10 qualified outcomes per offer: compare cost per qualified lead, booking rate, cost per
  booked opportunity
- Sold-job CAC, revenue and gross profit only on matured **45–60 day** lead-created cohorts.
  Installation may need longer

### 6.4 Reporting

**Weekly operational**, by ad and offer: spend · impressions · reach · frequency · CPM · outbound
CTR · Meta-reported results · valid leads · contactable leads · qualified leads · cost per
qualified lead · unanswered calls · median response time · contact rate · booked opportunities.

**Monthly cohort**, by lead-created month and offer: qualified leads · booked opportunities · held
appointments · sold jobs · completed jobs · collected revenue · gross profit · cost per booked
opportunity · cost per sold job · revenue-to-spend · gross-profit-to-spend.

Never scale on CTR or raw lead cost alone.

### 6.5 Threshold status

> The $65 call, $50 form and $130 sold-job figures are **provisional planning markers, not
> validated unit economics.** They must be replaced with measured, offer-specific values. The $50
> form threshold in particular is unsupported: no form booking or close rate has ever been
> measured.

---

## 7. Creative

Retained: owner on camera · real local footage · genuine before/after only · no fabricated
before/after · no dead first seconds · no unrelated spectacle · AI spokesperson retired · clear ad
naming.

**Review Carousel:** a high-click, zero-lead concept worth **one controlled retest**, not a proven
winner. Retest on a lead objective, offer visible immediately, direct-response opening frame,
fewer and stronger proof points rather than seven browsing cards, clear CTA, real attribution
where permitted, consistent message from ad through to form, roughly $150 no-valid-lead stop-loss.

**Active ads:** two per ad set. A third only when total spend supports it or one is an established
control.

**Cadence:** replace v1's weekly rotation with the following.

> Introduce a new creative only after the incumbent has reached its spend or evidence gate.
> Calendar weeks do not guarantee equal or sufficient delivery.

Do not promise a fixed number of meaningful tests per quarter.

**Formats:** continue 1080x1920 for Reels and Stories, **add 4:5 feed variants**, check safe zones
for captions, phone numbers, prices and CTAs, and never rely on automated cropping for
offer-critical text.

---

## 8. Expected outcomes

The v1 month-two and month-three CPA projections are withdrawn as unsupported. Replaced with
measurement milestones.

**Month 1:** tracking and CRM attribution validated · existing call campaign reconciled against
call records · initial qualified-call baseline · first form block underway or complete ·
speed-to-lead compliance measured · some ads and offers still inconclusive, which is expected.

**Months 2–3:** directional cost per qualified lead by offer · initial booking and held-appointment
rates · matured repair outcomes · partial installation outcomes · an evidence-based decision on
which offer receives the form budget · offer-specific CAC and gross-profit conclusions only where
cohort maturity permits.

Testing necessarily spends money on losing ads. The goal is to cap that spend and extract
knowledge from it, not to claim it will be zero.

---

## 9. Blindspots

The eight from v1 stand: self-reported economics · unrecoverable historical creative learning ·
single-touch attribution · $90/day is small for four offers · uncontrolled seasonality ·
competitor auction pressure · the unverified 916 forward · the follow-up gap.

Added:

1. **Call-result validity.** $22.18 may be calls placed, not unique qualified inbound calls.
2. **Offer-level downstream attribution.** A generic Paid Meta source cannot say which offer
   produced a job.
3. **Intra-cell value imbalance.** Meta may favour the cheapest action even when it produces less
   revenue. Tune-up and installation leads are not economically interchangeable.
4. **Financial-products categorisation.** GoodLeap may require a Special Ad Category.
5. **Unmatched cohorts.** Installation outcomes are especially right-censored.
6. **Gross profit and technician capacity.** Cheap tune-ups and free service calls consume
   dispatch capacity and can displace higher-contribution work.
7. **Invalid experimental assumptions.** Normal delivery is neither equal allocation nor
   randomised assignment.
8. **Contact quality versus response attempt.** One outbound attempt can satisfy an SLA while
   creating no contact.
9. **Form validity and intent.** Instant forms carry accidental and low-intent submissions.
10. **Call-hour coverage.** Call ads running when nobody answers waste the highest-intent demand.
11. **Offer scope and bait-switch risk.** The $49 and $0 offers need explicit inclusions,
    exclusions and conditions, or they undermine the anti-bait-switch positioning.
12. **Placement-specific creative.** A 9:16-only library is not optimised for feed.

---

## 10. Lead handling

The most operationally important section. **[VERIFIED]** documented failures: Lisa Miller Otis,
form lead 2026-07-25, estimate booked 07-28, her 07-27 message never answered, sent the
"sorry you chose another company" message 08-07. Roughly 20 distinct numbers with unread inbound
across four days.

**[ASSUMED]** That fixing this returns more than any media change is directionally likely but
**not measured**. Treat it as a top priority, not a quantified claim.

Track the SLA as a funnel, not a single compliance number: lead received → first outbound attempt
→ first successful contact → qualified → booked → appointment held → sold → lost reason.

Report: median and 90th-percentile first-attempt time · % attempted within 5 minutes · % contacted
within 30 minutes · contact rate · booking rate after contact · no-show and cancellation rate ·
lost reason · leads with no human disposition after 24 hours.

**Measure successful contact and booking, not merely an attempt.** A 15-minute out-of-hours
promise is not credible without a named person on duty. For call ads, live answering beats
callback delay; schedule call delivery around live coverage and route out-of-hours demand to forms.

---

## 11. Gates

Three levels, not one list. Only the first blocks launch.

### Gate A: before the relevant ad spends
1. Facebook tracking number test-called successfully
2. Broken Messenger auto-greeting disabled
3. Paid Meta standardised as a distinct source value
4. Lead routing verified end to end
5. Named response owner on duty, with a backup, per shift
6. Offer terms, inclusions and exclusions written and approved
7. GoodLeap category and disclosures confirmed. **Blocks the financing campaign only**

### Gate B: before any budget increase
8. All 36 `click_to_call_native_call_placed` events reconciled against phone logs
9. Calls placed distinguished from answered, 20-second and 60-second calls
10. Valid and qualified lead definitions documented and in use
11. Offer identity retained downstream
12. Contact and booking outcomes reportable
13. The 633-click Challenger landing path checked for mobile load, form function and analytics

### Gate C: before any profitability or winner claim
14. Lead-created cohorts matched to jobs
15. Sold and completed outcomes matured
16. Revenue and, ideally, gross profit available by offer

---

## 12. 90-day plan

**Days 1–45**
- *Calls, $55/day:* existing campaign, existing ad set, urgent-repair control plus $0 service-call
  challenger. Reconcile every reported result against call records. If the challenger is starved
  under ~$150 in ten days, run the formal $27.50/$27.50 test.
- *Forms, $35/day:* **$49 tune-up only**, uninterrupted, separate form. Track valid, contactable,
  qualified, booked.

**Days 46–90**
- *Calls:* consolidate after any formal test. Retain the offer with the better qualified-call cost
  and booking rate, not raw call count. If evidence is inadequate, say so rather than invent a
  winner. Introduce new creative only at an evidence gate.
- *Forms:* **installation financing only**, uninterrupted, after Gate A item 7 clears.

**Day 90 onward**
- $55/day to the call structure with the strongest qualified and booked performance.
- $35/day to the form offer with better qualified-opportunity economics. If outcomes remain
  immature, continue running one offer at a time rather than choosing on raw CPL.
- Review Proof retest as a capped challenger only once the relevant offer has a working baseline.
  Its historical CTR does not confer incumbent status.

---

## 13. Information request

1. 90 or 180-day Ads Manager export at campaign, ad-set and ad level
2. Calls placed, 20-second calls, 60-second calls
3. Call-tracking logs with duration, answer status, disposition
4. CRM lead cohorts with ad, form and offer identity
5. Contacted, qualified, booked, held, sold, completed stages
6. Collected revenue and gross profit
7. Offer-specific average revenue and margin
8. Tune-up upsell rate
9. GoodLeap approval rate, dealer fee, cancellation rate
10. Challenger landing-page URL and mobile funnel analytics
11. Current Call Leads budget, optimization event, attribution window, schedule
12. Live-answer coverage and technician capacity by day

---

## Appendix: source note

Meta Help Centre articles consulted for the delivery-status and Special Ad Category points are
"View campaign, ad set or ad delivery status in Meta Ads Manager" (`650774041651557`) and
"Create ad campaigns in Meta Ads Manager" (`621956575422138`). Both are genuine but redirect
unauthenticated visitors to a login page, so they are named here rather than linked inline. The
readable public reference for the call-metric distinction is Meta's lead-ads-with-calling page.
