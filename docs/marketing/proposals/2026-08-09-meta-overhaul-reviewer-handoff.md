# Twins Garage Doors: Meta Ads Overhaul, Reviewer Handoff

**Prepared:** 2026-08-09
**For:** External reviewer
**Ad account:** act_388398022876424, "Twins Garage Doors"
**Business:** Residential garage door repair and installation, Madison WI
**Budget under discussion:** $90/day, approximately $2,700/month

---

## How to read this document

You are being asked to review a proposed teardown and rebuild of a Meta ad account before it
is executed. This document is self-contained. You do not need any prior context.

Every factual claim carries a confidence tag:

| Tag | Meaning |
|---|---|
| **[VERIFIED]** | Pulled live from the Meta Marketing API or the company database during this analysis |
| **[DERIVED]** | Arithmetic on verified numbers |
| **[ASSUMED]** | A judgment call, stated so you can attack it |
| **[UNKNOWN]** | A named gap. Not guessed at |

**Section 9 lists the specific decisions where a second opinion is most valuable.** If you read
nothing else, read Section 2 (current state), Section 5 (the plan), and Section 9.

---

## 1. Business context

Twins Garage Doors is a family-run residential garage door company in Madison, Wisconsin. The
owner runs marketing decisions personally. Until 2026-08-08 an agency (Legit5) managed Meta and
Google for a $2,400/month management fee on roughly $3,500/month of managed spend. That contract
has ended and the account is now managed in-house.

**[VERIFIED]** Unit economics as stated by the owner:
- Roughly 80% of inbound calls are booked into opportunities by the CSR
- Roughly 63% of booked opportunities close
- Average opportunity value $860
- **[DERIVED]** Expected revenue per inbound call ≈ 0.80 x 0.63 x $860 ≈ **$433**

**[UNKNOWN]** These are owner estimates, not measured values. The `calls_inbound` data feed is
empty, so there is no independent record to validate the 80% booking rate. Every cost threshold
in this plan derives from these three numbers. If you think they are wrong, say so, because
everything moves.

---

## 2. Current state of the account

### 2.1 Clutter

**[VERIFIED]** The account contains 80+ campaigns and 190+ ads accumulated across four eras
(a 2024 in-house era, two prior agencies, and 2026 in-house work). **Exactly one campaign is
currently active.**

**[VERIFIED]** Legacy ads are named `Ad 1`, `Ad 2`, `AD 5 Video`, `AD 3 Image – Copy`. The names
encode neither offer nor creative concept. **Consequence: no claim about which creative concepts
historically worked for this business is recoverable from this account.** The creative knowledge
base starts at zero.

### 2.2 Campaign performance, trailing 30 days to 2026-08-09

**[VERIFIED]**

| Campaign | Objective | Status | Spend | Result | Cost/result |
|---|---|---|---|---|---|
| Legit5 \| $49 Garage Door Tuneup | OUTCOME_LEADS | Paused 08-09 | $1,937.01 | 25 form leads | $77.48 |
| Madison WI – Call Leads | OUTCOME_LEADS | **Active** | $798.54 | 36 calls | **$22.18** |
| Twins – Reel – Calls | OUTCOME_LEADS | Paused | $317.65 | 6 calls | $52.94 |
| Madison WI – Messenger Leads | OUTCOME_ENGAGEMENT | Paused 08-09 | $289.50 | 23 conversations | $12.59 |
| Challenger – Review Carousel | LINK_CLICKS | Paused | $124.91 | 324 clicks, 0 leads | n/a |
| Challenger – Install Financing | LINK_CLICKS | Paused | $114.65 | 309 clicks, 0 leads | n/a |
| **Total** | | | **$3,582.26** | | |

### 2.3 Ad-level performance, same window

**[VERIFIED]**

| Ad | Spend | Result | Cost/result | CTR |
|---|---|---|---|---|
| Anti Bait Switch \| Image 1 | $1,385.57 | 19 form leads | $72.92 | 1.04% |
| Honest Tuneup \| Image 1 | $551.44 | 6 form leads | $91.91 | 1.20% |
| 01_Master Video – Madison Messenger | $289.50 | 23 conversations | $12.59 | 2.42% |
| Reel 2 – "It's not you, it's your garage door" | $277.61 | 3 calls | $92.54 | 0.95% |
| **Review Proof Carousel – 7 cards** | $124.91 | **0 leads** | n/a | **3.07%** |
| **Install Financing – Real Before/After** | $114.65 | **0 leads** | n/a | **0.40%** |
| Reel – "It's not you, it's your garage door" | $40.04 | 3 calls | $13.35 | 0.79% |

Two observations we consider load-bearing:

1. **The Review Proof Carousel produced roughly 3x the CTR of anything else in the account and
   zero leads,** because it ran on a LINK_CLICKS objective pointed at a page. We read this as a
   working creative concept destroyed by its campaign settings, not a failed creative. The plan
   rebuilds it on a lead objective. **If you disagree, this is worth saying.**
2. **$239.56 across the two Challenger campaigns bought 633 link clicks and zero leads or
   calls.**

### 2.4 Spend versus earned revenue

**[VERIFIED]** Meta spend by month, from the company database:

| Month | Spend | Form leads | Calls | Conversations |
|---|---|---|---|---|
| 2026-04 | $1,230.99 | 21 | not measured | not measured |
| 2026-05 | $2,043.09 | 32 | not measured | not measured |
| 2026-06 | $2,015.10 | 23 | 1 | 4 |
| 2026-07 | $3,638.89 | 32 | 49 | 57 |
| 2026-08 (to 08-09) | $1,094.13 | 7 | 13 | 15 |

**[VERIFIED]** Completed jobs attributed to Meta, 2026-05-01 onward: **28 jobs, $14,440.00
revenue.**

**[DERIVED]** Meta spend over the same window: **$8,791.21**. Revenue-to-spend **1.64x on
revenue, not profit.** At typical residential garage-door gross margin this is at or near
break-even.

**[VERIFIED]** Same window, other channels: Google $70,691.00, Google LSA $39,412.20.

**[UNKNOWN]** Attribution is single-touch. A homeowner who sees a Meta reel, later searches, and
calls from Google books as Google. Meta is very likely under-credited by an unquantified amount.
**Treat 1.64x as a floor, not a point estimate.**

### 2.5 A messaging failure worth knowing about

**[VERIFIED]** The business owner was receiving Facebook "Stop" replies from people he had never
messaged. Root cause: the Messenger campaign's own auto-greeting fires on ad tap, before any of
the company's systems are involved, so the greeting never appears in their CRM. The greeting also
promised "tap an option below" while no quick-reply options rendered.

**[VERIFIED]** Census of 16 replies to that ad between Jul 27 and Aug 9: **7 Stop or
do-not-contact, 2 misclicks, 1 spam complaint, 1 angry existing customer, 3 real prospects.**
Meta billed $11.42 per "messaging conversation started." **[DERIVED]** Real cost per actual
prospect was approximately $100.

The creative was a goat video with a Send Message button. Our reading: unrelated-spectacle
creative on a messaging objective harvests curiosity taps from people with no purchase intent,
and the platform's own conversation metric conceals it.

---

## 3. Constraints (fixed by the owner, not open for review)

| Constraint | Value |
|---|---|
| Budget ceiling | $90/day, approximately $2,700/month |
| Geography | Madison WI + 25 miles. Milwaukee deferred despite an existing 414 number |
| Offers to test | All four: urgent repair, $49 tune-up, install + GoodLeap financing, free estimate / $0 service call |
| Spokesperson | The owner's real face. AI-generated spokesperson retired |
| Scope | Ad account plus a speed-to-lead SLA |

---

## 4. The arithmetic problem, and why the structure looks the way it does

**[ASSUMED, platform guidance]** Meta ad sets need roughly 50 optimization events per week to
exit the learning phase. This is Meta's own published guideline rather than something we
measured on this account, and reviewers who believe the real threshold is lower should say so,
because it changes how many cells this budget can support.

**[DERIVED]** At $90/day the ceilings are approximately **28 calls/week** at the current $22.18
cost per call, or approximately **12 form leads/week** at a $50 target.

**[DERIVED]** Four separately-budgeted offer campaigns would leave each with 3 to 7 events per
week. Permanently learning-limited: the algorithm never optimizes, costs stay inflated, and
weekly reads are noise. This is approximately what happened to the two Challenger campaigns.

**Resolution adopted: group by conversion action, not by offer.** Offers driving the same action
share an ad set and compete as creative, pooling volume. All four offers still run.

Two alternatives were considered and rejected:

- **Single Advantage+ campaign, all offers, undivided $90/day.** Maximum statistical power and
  arguably the current platform-recommended approach. Rejected because Meta would almost
  certainly push nearly all budget toward calls (the cheapest action to trigger), producing deep
  learning about one offer and nothing about the other three, which contradicts the owner's
  explicit goal.
- **Sequential offer testing, one offer at a time for three weeks each.** Cleanest reads, no
  fragmentation. Rejected on timeline (12 weeks to a decision) and because Wisconsin garage-door
  seasonality would confound an August result against an October one.

**If you think either rejected option is actually correct, this is the most valuable thing you
could tell us.**

---

## 5. Proposed structure

```
TGD | Calls | Madison | 2026-08                      $55/day
├── AS: Broad 25-65+ | Madison +25mi     (EXISTING ad set, preserved)
│   ├── Repair_BrokenSpring_Reel_v1
│   └── Repair_DoorWontOpen_Reel_v1
└── AS: Broad 25-65+ | Madison +25mi | B (NEW, isolates learning risk)
    └── ServiceCall_Zero_Reel_v1

TGD | Forms | Madison | 2026-08                      $35/day
└── AS: Broad 25-65+ | Madison +25mi
    ├── Tuneup_49_RealFace_v1
    ├── Install_GoodLeap_BeforeAfter_v1
    └── Install_ReviewProof_Video_v1     ← rebuild of the 3.07% CTR carousel
```

**Teardown:** all legacy campaigns **archived, not deleted.** Archive preserves reporting history
and the record of prior agency work; delete is permanent and gains nothing operationally.

**The one campaign that is NOT being rebuilt** is `Madison WI – Call Leads`, currently at $22.18
per call. It stays live. We consider destroying the only working campaign in order to demonstrate
a rebuild to be a mistake.

**Targeting:** broad, age 25-65+, Madison +25 miles, no interest layering, no lookalikes at
launch. **[ASSUMED]** At this budget, interest stacking starves delivery.

**Naming convention:** `{Offer}_{Hook}_{Format}_{version}` at ad level, so that offer and hook
can be analyzed as independent variables. The current account cannot do this.

---

## 6. Measurement and decision rules

Two-tier, anchored to the $433-per-call figure in Section 1.

**Tier 1, day 7, kill decisions only:**
- Cost per call above **$65** (≈15% marketing cost of sale), or
- Cost per form lead above **$50**, or
- Zero results after **$75** spent

**Tier 2, day 30, scale decisions only:**
- Cost per booked job in the field-service system below **$130**

**Rationale for two tiers:** a single fast metric is what made the $12.59 "messaging conversation"
look like a success when the real cost per prospect was approximately $100. A single slow metric
produces too few events per creative at this budget to make timely kills.

**Reporting cadence:**
- Weekly, per ad: spend, impressions, CTR, results, cost per result, day-7 verdict
- Monthly, per cell: cost per booked job against $130
- Weekly: count of Meta leads with no outbound response within 30 minutes

---

## 7. Creative

### 7.1 What the existing account says about creative

| Creative | CTR | Reading |
|---|---|---|
| Review Proof Carousel (7 real review quotes) | **3.07%** | Social proof is the strongest lever available |
| Honest Tuneup, static | 1.20% | Direct claim, mid |
| Anti Bait Switch, static | 1.04% | Direct claim, mid |
| Reel, "It's not you, it's your garage door" | 0.95% | Clever metaphor underperformed |
| Install Financing, before/after | **0.40%** | Worst in account on 74,586 impressions |

### 7.2 Asset audit, performed by frame extraction

**[VERIFIED]** 555 video files exist, 496 unique by checksum. All deliverables are 1080x1920.
Findings:

1. **The 0.40% before/after ad uses a fabricated before/after.** Frame extraction shows the
   "before" is a gray two-story craftsman and the "after" is an entirely different modern dark
   house. Our read is that the audience detected this. **[ASSUMED]** but strongly supported by
   the impression volume.
2. **Seven "offer ads" have a dead first second:** logo on empty color at t=0.2s, headline at
   t=1.0s, frozen frame through t=2.0s, flat vector illustration rather than real doors.
3. **Two assets reuse the failed hook pattern:** a sports-car burnout with "wait for it..." and
   fireworks with "wait for the finale:". Same unrelated-spectacle structure as the goat video
   that produced 7 Stop replies out of 16.
4. **Five assets use an AI-generated spokesperson.** Retired by owner decision, on the grounds
   that a synthetic person undercuts a family-run local business's core claim.
5. **Genuine real-person and real before/after footage already exists** and was not being used.

### 7.3 Call-tracking defect

**[VERIFIED]** The business has a dedicated Facebook tracking number, **(608) 688-9109**. The
entire tune-up creative family and all seven offer ads instead burn **(608) 888-8785**, the raw
main line. Calls driven by that creative are invisible to Meta attribution.

**[UNKNOWN] and high severity:** the tracking number forwards to 916-712-3699, a California
number, and we have not confirmed the forward is live. **This must be test-called before the
number goes into any creative.** Putting a dead number into every ad would be materially worse
than the current untracked state.

### 7.4 Iteration cadence, stated honestly

**[DERIVED]** Cell 1 at $55/day across a maximum of 3 live ads is approximately **$128 per ad per
week**. That buys a reliable CTR read and a directional cost-per-call read, not a conclusive one.

- Maximum 3 live ads per cell
- One new creative in, one loser out, per cell, per week
- Each new creative changes exactly **one** variable versus the incumbent

**[DERIVED]** Real throughput is approximately **2 new creatives per week, 26 per quarter,
tested sequentially.** Not 20 concurrent. We state this because the owner asked for "many
iterations" and we do not want the plan oversold.

---

## 8. Current versus projected

| Metric | Last 30 days actual | Month 1 projected | Months 2-3 projected |
|---|---|---|---|
| Spend | $3,582.26 | $2,700 | $2,700 |
| Active campaigns | 1 of 80+ | 2 | 2 |
| Spend on zero-result campaigns | $239.56 | $0 | $0 |
| Cost per call | $22.18 | $28-40 | $20-30 |
| Cost per form lead | $77.48 | $60-80 | $45-60 |
| Cost per booked job | Not computable | Not computable | $130 target |
| Meta leads unanswered >30 min | ~20 in 4 days | 0 | 0 |

**Month 1 is projected to be worse than today** because of learning-phase resets. Month 1
confidence **medium**; months 2-3 confidence **low** until attribution is fixed and one full
cycle of real data exists.

---

## 9. Where we most want your opinion

These are the calls we are least confident in. Ranked by how much damage a wrong answer does.

1. **Should the working Call Leads campaign be preserved, or cleanly rebuilt?** We propose
   staging new creative into a *second* ad set inside the existing campaign, to keep the
   campaign-level signal while isolating learning-phase risk. The alternative is accepting a
   learning reset on the only thing producing at $22.18 per call. We are genuinely unsure.
2. **Is grouping by conversion action the right response to the learning-phase constraint,** or
   should this be one Advantage+ campaign accepting that calls will dominate? See Section 4.
3. **Is $35/day for the forms cell worth running at all,** given it will stay learning-limited,
   or is that budget better consolidated into calls until there is more headroom?
4. **Is the Review Carousel read correct?** We are treating 3.07% CTR with zero leads as "good
   creative, wrong objective" and rebuilding it on a lead objective. The pessimistic reading is
   that high CTR on a link-click objective selects for curiosity clickers and the concept will
   not convert. Which is it?
5. **Are the $65 and $130 thresholds defensible** given they rest on owner-estimated close rates
   rather than measured ones?
6. **Broad targeting with no interest layer at $90/day in a single metro.** Correct, or should
   there be a homeowner or home-improvement behavior layer?
7. **Anything in Section 10 we have not thought of.**

---

## 10. Known blindspots

1. **The $433-per-call economics are self-reported.** No independent call record exists to
   validate them. Every threshold rests on them.
2. **Historical creative learning is unrecoverable** because of the `Ad 1` naming. Starting from
   zero.
3. **Attribution is single-touch.** Meta is under-credited by an unknown amount. 1.64x is a floor.
4. **$90/day is small for four offers.** The proposed structure mitigates fragmentation but does
   not eliminate it. The forms cell will stay statistically weak.
5. **Seasonality is uncontrolled.** Madison garage-door demand is weather-driven. An August
   baseline against an October result confounds creative performance with the first freeze.
6. **Competitor auction pressure** in a single metro at this budget is observable but not
   controllable.
7. **The (608) 688-9109 forward is unverified.** Highest-severity unknown in the document.
8. **The follow-up gap may dominate everything.** See Section 11.

---

## 11. The thing that may matter more than any of this

**[VERIFIED]** Meta leads reach the company CRM correctly. They then sit unanswered.

Documented case: a Facebook form lead arrived 2026-07-25 for a new two-car door. An estimate was
booked 07-28. Her 07-27 message asking for 20 minutes notice before arrival was never answered.
On 08-07 she received an automated "sorry you chose another company" message.

**[VERIFIED]** Approximately 20 distinct phone numbers had unread inbound messages in the four
days to 2026-08-09, one with 21 unread.

**[UNKNOWN]** Unread in the CRM does not prove no callback occurred, because CSRs return calls
from the phone system. The named cases with zero outbound of any kind are unambiguous; the count
of 20 is an upper bound.

The plan therefore includes a speed-to-lead SLA: 5 minutes during business hours, 15 minutes
outside, a named owner per shift, and a weekly count of Meta leads with no outbound attempt
within 30 minutes reported directly alongside ad spend.

**We would rather hear that this plan's media structure is imperfect but the SLA is right, than
the reverse.** Optimizing cost per lead while leads die in an inbox is the failure mode this
business just lived through.

---

## 12. Blocking prerequisites

Nothing launches until these four are done.

1. **Standardize the paid-Meta lead source value** in the field-service system, distinct from
   organic Facebook. Until then, cost per booked job cannot be computed and the Tier 2 rule is
   inoperable. (Context: the current value migrated from `Facebook Ads` to `Facebook ` during
   April to July 2026, and dedicated organic values exist but are nearly unused, so organic
   contamination is possible and unquantified.)
2. **Test-call (608) 688-9109** and confirm it reaches the business.
3. **Disable the Messenger ad auto-greeting** before archiving that campaign.
4. **Name the speed-to-lead owner per shift.**
