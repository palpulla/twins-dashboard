# Meta Ads Overhaul: Teardown and Rebuild

**Date:** 2026-08-09
**Account:** act_388398022876424 ("Twins Garage Doors", business_id 120957490303553)
**Status:** Design, pending review
**Author:** Claude, with Daniel (owner)

---

## 1. Purpose

Rebuild the Twins Garage Doors Meta ad account from a cluttered, multi-agency accumulation
into a small, measurable, iterating system that produces booked jobs in Madison WI at a
defensible cost, on a fixed budget of $90/day.

This document is the design. A companion reviewer handoff lives at
`docs/marketing/proposals/2026-08-09-meta-overhaul-reviewer-handoff.md` and is written to be
read without this document or the conversation that produced it.

### Confidence key

Used throughout. The reviewer asked for certainty to be labeled.

| Tag | Meaning |
|---|---|
| **[VERIFIED]** | Pulled from a live API or database during this analysis, with the query recorded |
| **[DERIVED]** | Arithmetic on verified numbers |
| **[ASSUMED]** | A judgment call. Stated so it can be attacked |
| **[UNKNOWN]** | Named gap. Not guessed at |

---

## 2. Current state

### 2.1 Account clutter

**[VERIFIED]** The campaign listing returns 80+ campaigns and the ad listing returns 190+ ads.
They span at least four distinct eras, identifiable by naming convention:

| Era | Naming pattern | Approx count | Status |
|---|---|---|---|
| Legacy (2024) | `Dunzo l SD l ...` | ~30 campaigns | All paused |
| Prior agency | `GDML \| TGD \| ...` | 6 campaigns | All paused |
| Self-serve duplicates | `Twins Garage Doors \| Lead Form \| ...` | ~20 campaigns | All paused |
| Legit5 (contract ended 2026-08-08) | `Legit5 \| ...` | 1 campaign | Paused 2026-08-09 |
| In-house 2026 | `Madison WI – ...`, `Twins – Challenger – ...` | 6 campaigns | 1 active, 5 paused |

Exactly **one** campaign is currently active: `Madison WI – Call Leads – 01_Master Video`.

Ad naming in the legacy eras is `Ad 1` through `Ad 4`, `AD 5 Video`, `AD 3 Image – Copy`. This
is load-bearing: it means the historical account **cannot** answer "which offer worked" or
"which hook worked," because the names encode neither. Any claim about what has historically
performed for Twins on Meta at the creative-concept level is unrecoverable from this account.

### 2.2 Campaign performance, trailing 30 days to 2026-08-09

**[VERIFIED]** via Meta MCP, `ads_get_ad_entities`, level=campaign, date_preset=last_30d.

| Campaign | Status | Spend | Result | Cost/result |
|---|---|---|---|---|
| Legit5 \| $49 Garage Door Tuneup | Paused 08-09 | $1,937.01 | 25 form leads | $77.48 |
| Madison WI – Call Leads – 01_Master Video | **Active** | $798.54 | 36 calls | **$22.18** |
| Twins – Reel – Calls (Madison) | Paused | $317.65 | 6 calls | $52.94 |
| Madison WI – Messenger Leads | Paused 08-09 | $289.50 | 23 conversations | $12.59 |
| Twins – Challenger – Review Carousel | Paused | $124.91 | 324 link clicks, **0 leads** | n/a |
| Twins – Challenger – Install Financing | Paused | $114.65 | 309 link clicks, **0 leads** | n/a |
| **Total** | | **$3,582.26** | | |

### 2.3 Ad-level performance, same window

**[VERIFIED]** Same source, level=ad.

| Ad | Spend | Result | Cost/result | CTR |
|---|---|---|---|---|
| Anti Bait Switch \| Image 1 | $1,385.57 | 19 form leads | $72.92 | 1.04% |
| Honest Tuneup \| Image 1 | $551.44 | 6 form leads | $91.91 | 1.20% |
| 01_Master Video – Madison Messenger | $289.50 | 23 conversations | $12.59 | 2.42% |
| Reel 2 – It's not you, it's your garage door | $277.61 | 3 calls | $92.54 | 0.95% |
| Review Proof Carousel – 7 cards | $124.91 | 0 leads | n/a | **3.07%** |
| Install Financing – Real Before/After | $114.65 | 0 leads | n/a | **0.40%** |
| Reel – It's not you, it's your garage door | $40.04 | 3 calls | $13.35 | 0.79% |

Two facts to carry forward:

- **Review Proof Carousel produced the highest CTR in the account by roughly 3x and zero
  leads,** because it was built on a LINK_CLICKS objective pointed at a page. That is a
  working creative wasted by its campaign settings, not a failed creative.
- **$239.56 across the two Challenger campaigns produced 633 link clicks and zero leads or
  calls.** This is the "I just get clicks" complaint, quantified.

### 2.4 Spend versus earned revenue

**[VERIFIED]** Spend from `marketing_spend` (Supabase jwrpj), platform Meta.

| Month | Spend | Form leads | Calls | Conversations |
|---|---|---|---|---|
| 2026-04 | $1,230.99 | 21 | n/a | n/a |
| 2026-05 | $2,043.09 | 32 | n/a | n/a |
| 2026-06 | $2,015.10 | 23 | 1 | 4 |
| 2026-07 | $3,638.89 | 32 | 49 | 57 |
| 2026-08 (to 08-09) | $1,094.13 | 7 | 13 | 15 |

`calls` and `conversations` are NULL before June because those columns did not exist. NULL
means not measured, not zero.

**[VERIFIED]** Revenue from `jobs`, completed non-Estimate jobs, 2026-05-01 onward:

| lead_source | Completed jobs | Revenue |
|---|---|---|
| `Facebook ` (trailing space) | 21 | $11,925.00 |
| `Facebook Ads` | 7 | $2,515.00 |
| **Total** | **28** | **$14,440.00** |

**[DERIVED]** Meta spend 2026-05-01 to 2026-08-09 = $8,791.21. Revenue-to-spend = **1.64x on
revenue, not profit.** At typical residential garage-door gross margin this is at or near
break-even.

**[VERIFIED]** For scale, same window: Google $70,691.00, Google LSA $39,412.20.

### 2.5 The lead_source label migration

**[VERIFIED]** This was initially misdiagnosed as a paid-versus-organic contamination problem.
It is not. Monthly counts:

| Month | `Facebook Ads` | `Facebook ` | `Facebook Organic` | `Facebook Group` |
|---|---|---|---|---|
| 2026-01 | 5 | 0 | 0 | 0 |
| 2026-02 | 26 | 0 | 0 | 0 |
| 2026-03 | 27 | 0 | 1 | 0 |
| 2026-04 | 7 | 6 | 0 | 0 |
| 2026-05 | 9 | 27 | 0 | 0 |
| 2026-06 | 8 | 5 | 0 | 0 |
| 2026-07 | 2 | 16 | 0 | 0 |
| 2026-08 | 0 | 2 | 0 | 0 |

`Facebook Ads` spans 2022-02-14 to 2026-07-21 (110 jobs all-time). `Facebook ` spans
2026-04-23 to 2026-08-05 (56 jobs). This is one label being replaced by another during
April to July 2026, not two different traffic types.

Dedicated organic values exist (`Facebook Organic` 2 jobs, `Facebook Group` 1 job, none since
March 2026). **[UNKNOWN]** Whether CSRs reliably choose the organic values, or default to the
generic `Facebook ` for organic inquiries. Contamination is therefore possible but small and
unquantified. Do not claim it is zero.

**[VERIFIED]** A third legacy value `Zory FB` holds 7 jobs from 2025.

### 2.6 Root cause of the "Stop" replies

**[VERIFIED]** Documented separately in the 2026-08-09 investigation. The Messenger campaign's
own auto-greeting fires on ad tap, before any Twins system is involved, and Twins GHL never
sees it. Census of 16 ad replies Jul 27 to Aug 9: 7 Stop or do-not-contact, 2 misclicks, 1
spam complaint, 1 angry existing customer, 3 real prospects. Meta billed $11.42 per
"conversation"; real cost per actual prospect was approximately $100.

Retiring the Messenger objective removes this failure mode at the source. It is not a
messaging-policy fix, a GHL fix, or a Legit5 fix.

---

## 3. Constraints and decisions taken

| Decision | Value | Rationale |
|---|---|---|
| Budget ceiling | $90/day, ~$2,700/mo | Daniel, 2026-08-09. Flat versus what the account is set to spend today |
| Scope | Ads plus speed-to-lead SLA | Follow-up gap is the larger leak; measuring cost per booked job requires the funnel to work |
| Geography | Madison WI plus 25 miles | Daniel. Milwaukee explicitly deferred |
| Offers tested | All four | Daniel. Urgent repair, $49 tune-up, install plus GoodLeap, free estimate / $0 service call |
| Spokesperson | Daniel's real face. **Retire the AI avatar** | Daniel, 2026-08-09 |
| Old campaigns | Archive, not delete | Preserves reporting history and the Legit5 evidence record. Delete is permanent and buys nothing |
| Working campaign | Keep `Madison WI – Call Leads` running | Only performer in the account. Do not destroy the winner to prove a rebuild happened |

### 3.1 The learning-phase constraint

**[ASSUMED, platform guidance]** Meta ad sets need roughly 50 optimization events per week to
exit the learning phase and optimize reliably. This is Meta's published guideline, not something
measured on this account.

**[DERIVED]** At $90/day, ceilings are approximately 28 calls/week at the current $22.18 cost
per call, or approximately 12 form leads/week at a $50 target.

**[DERIVED]** Four separately-budgeted offer campaigns would give each cell 3 to 7 events per
week. That is permanently learning-limited: Meta never optimizes, costs stay inflated, and
weekly reads are statistically meaningless. This is close to what already happened to the two
Challenger campaigns.

**Resolution: group by conversion action, not by offer.** Offers driving the same action share
an ad set and compete as creative, pooling volume instead of fragmenting it. All four offers
run; the algorithm allocates within each cell.

---

## 4. Target design

### 4.1 Structure

```
TGD | Calls | Madison | 2026-08                      $55/day
├── AS: Broad 25-65+ | Madison +25mi     (EXISTING, preserved)
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

**[ASSUMED]** Staging new creative into a second ad set rather than into the existing one
preserves the campaign-level signal while isolating learning-phase risk. Adding creative to
the existing ad set would likely reset its learning. This is the single call in the design I
am least sure of and the reviewer should weigh in. The alternative, accepting a learning reset
on the one campaign that works, has a real cost at $22.18 per call.

### 4.2 Naming convention

- Campaign: `TGD | {Cell} | {Geo} | {YYYY-MM}`
- Ad set: `{Audience} | {Geo+radius}`
- Ad: `{Offer}_{Hook}_{Format}_{version}`

Offer and hook are separate tokens so that after eight weeks the account can answer "do
tune-up offers beat repair offers" and "do urgency hooks beat price hooks" as two independent
questions from the same spend. The current account can answer neither.

### 4.3 Targeting

Madison plus 25 miles, broad, age 25-65+, no interest layering, no lookalikes at launch.

**[ASSUMED]** At $90/day, interest stacking starves delivery, and broad targeting has generally
outperformed hand-built interest audiences at this budget tier. A reviewer may reasonably
argue for a homeowner-behavior layer; the counter is audience size.

**Out of scope:** Milwaukee, despite an existing 414 number and Wauwatosa address. Splitting
$90/day across two metros puts both below learning threshold.

### 4.4 Measurement

Two-tier decision rule, anchored to Daniel's stated economics: 80% of calls book, 63% of
booked opportunities close, $860 average ticket, therefore approximately **$433 expected
revenue per inbound call**.

**Tier 1, day 7, kill only:**
- Cost per call above $65 (approximately 15% marketing cost of sale), or
- Cost per form lead above $50, or
- Zero results after $75 spent

**Tier 2, day 30, scale only:**
- Cost per booked HCP job below $130

**[ASSUMED]** The $65 and $130 thresholds derive from the $433-per-call figure, which is
Daniel's estimate rather than a measured value. If the close rate or average ticket is off,
every threshold moves. Recompute if those inputs change.

**Reporting:**
- Weekly, per ad: spend, impressions, CTR, results, cost per result, day-7 verdict
- Monthly, per cell: cost per booked HCP job against $130
- Weekly, alongside both: count of Meta leads with no outbound attempt within 30 minutes

### 4.5 Lead routing and speed-to-lead

Cell 1 lands as phone calls. Cell 2 lands as Meta instant forms into Twins GHL, **[VERIFIED]**
working as of 2026-08-08 (contact Leonard Wagner arrived tagged "Facebook form lead").

- **SLA: 5 minutes during business hours, 15 minutes outside.**
- **Named owner per shift.** Unowned means unanswered.
- **Enforcement metric: zero Meta-sourced leads with no outbound attempt after 30 minutes**,
  reported weekly next to the ad spend so a cheap lead that died is visible beside its cost.

**[VERIFIED]** Motivating case: Lisa Miller Otis, Facebook form lead 2026-07-25 for a two-car
door, estimate booked 07-28, her 07-27 request for arrival notice never answered, and on 08-07
she received the "sorry you chose another company" message. Approximately 20 distinct phone
numbers had unread inbound in the four days to 08-09, one with 21 unread.

**[UNKNOWN]** Unread in GHL does not prove no callback happened, because CSRs return calls from
the phone system. The named cases with zero outbound are unambiguous; the count of 20 is an
upper bound.

---

## 5. Creative

### 5.1 Asset inventory

**[VERIFIED]** 555 mp4 files under `twins-media-generator/outputs/`, 496 unique by checksum.
All deliverable-grade assets are 1080x1920 (9:16), which is feed-safe for Reels and Stories.
`~/Desktop/Twins Marketing/Ads/` is a byte-identical duplicate of the repo copies.

The `tuneup-49-ad` family is a working variant system: 5 base cuts x 5 CTA endcards
(master / call / dm / paid / shorts), all distinct by checksum. The endcard-swap machinery
works and should be reused rather than rebuilt.

### 5.2 Audit findings

| # | Finding | Confidence |
|---|---|---|
| 1 | `twins_beforeafter_demo.mp4` is a **fake** before/after. "Before" is a gray two-story craftsman; "after" is a different modern dark house. Very likely explains the 0.40% CTR on 74,586 impressions | [VERIFIED] by frame extraction |
| 2 | The 7 cartoon "offer ads" have a dead first second (logo on empty color at t=0.2s, headline at t=1.0s, frozen through t=2.0s) and use flat vector illustration rather than real doors | [VERIFIED] |
| 3 | `Twins_HOOK_FIXED_SAMPLE` (sports-car burnout, "wait for it...") and `Twins_49_TuneUp_July4th_Reel_v2` (fireworks, "wait for the finale:") use unrelated-spectacle hooks. This is the goat-video pattern that produced 7 Stop replies out of 16 | [VERIFIED] |
| 4 | Five assets use an AI-generated spokesperson. **Retired by decision** | [VERIFIED] |
| 5 | `twins_49_REAL_welcome.mp4` is real-person footage, native 9:16, 30.6s, with a "$49 WINTER TUNE-UP" lower third already burned in | [VERIFIED] |
| 6 | `before and after garage door repair.mp4` is a genuine **same-garage** before/after, labeled in-frame, 1920x1080, 52.8s. **It is an OPENER replacement, not a door replacement** (the door panels and track are identical in both halves; only the ceiling unit changes). Visually subtle on a phone. Usable as an opener-upgrade asset or B-roll, **not** as the install-offer hero | [VERIFIED] by frame comparison |
| 6b | **Twins has NO genuine door-replacement before/after footage.** The only door before/after in the library is the fabricated one. This is a real production gap, not a re-cut task | [VERIFIED] |
| 7 | Real-face footage carries **no burned-in captions**. Most Reels play muted | [VERIFIED] |

### 5.3 The phone number defect

**[VERIFIED]** **(608) 688-9109** is the dedicated "Facebook Ad WI" tracking number
(`twins-dash/docs/voice-agent/phase0-findings.md`), forwarding to 916-712-3699 with a 60-second
timeout.

The entire tune-up family and all seven cartoon ads burn **(608) 888-8785**, the raw main line.
Calls driven by those creatives are invisible to Meta attribution. Only three assets use
688-9109.

**Requirement:** every ad creative and every ad-level call CTA uses **(608) 688-9109**.

**[UNKNOWN]** Whether 688-9109 currently forwards correctly. The forward target 916-712-3699 is
a California number. **This must be test-called before any spend runs against it.** If that
forward is dead, correcting the number in creative would send every Meta call into a void,
which is worse than the current untracked state.

### 5.4 Creative disposition

| Verdict | Assets | Work |
|---|---|---|
| Promote | `twins_49_REAL_welcome.mp4` | Swap phone to 688-9109, trim to under 20s, add burned-in captions, strengthen the 0-1s hook |
| Promote after re-crop | `before and after garage door repair.mp4` | Vertical 9:16 crop, hard trim, replaces the fake before/after |
| Re-crop | `welcome video tgd.mov` (1280x720 landscape) | Vertical crop before any Reels use |
| Re-cut | 7 cartoon offer ads | Real door footage, hook inside 0.5s, tracking number |
| Salvage endcard only | `HOOK_FIXED_SAMPLE`, `July4th_Reel_v2` | Strip the bait hooks entirely |
| Retire | `twins_beforeafter_demo.mp4` | Fake before/after |
| Retire | All 5 AI-avatar assets | Decision 2026-08-09 |

**[VERIFIED]** The real face in `twins_49_REAL_welcome.mp4` is **Daniel, the owner** (confirmed
by Daniel 2026-08-09). This makes it the reference asset for the spokesperson format: all new
talking-head creative features Daniel on camera.

### 5.5 Higgsfield production gaps

In priority order:

1. **Review-proof video.** The 3.07% CTR carousel exists only as static cards. A video version
   with Daniel on camera reading real GBP review quotes is the single highest-expected-value
   new asset, because it combines the account's best-performing concept with the format that
   gets delivery.
2. **Broken-spring urgency spot.** Real footage, Daniel on camera, matches the offer that is
   already winning at $22.18 per call.
3. **Same-house before/after**, if the existing real footage does not re-crop cleanly.

**Creative bar, every asset:** real logo, real door, hook inside the first 0.5 seconds, explicit
offer, burned-in captions, (608) 688-9109, 9:16 1080x1920, under 20 seconds.

**Anti-requirements, learned from this account:** no unrelated-spectacle hooks, no fabricated
before/after pairs, no AI-generated people, no flat vector illustration standing in for a real
door.

### 5.6 Iteration cadence

**[DERIVED]** Cell 1 at $55/day across a maximum of 3 live ads is approximately $128 per ad per
week. That buys a reliable CTR read and a directional cost-per-call read, not a conclusive one.

- Maximum 3 live ads per cell. More fragments delivery below readability.
- **One new creative in, one loser out, per cell, per week.**
- Each new creative changes exactly **one** variable versus the incumbent: hook, offer, format,
  or proof type. The ad name records which.

**[DERIVED]** Real throughput is roughly 2 new creatives per week across both cells, about 26
per quarter, tested sequentially. Not 20 concurrent. Any plan promising meaningful concurrent
testing of 20 creatives at $90/day is not arithmetically honest.

---

## 6. Prerequisites, blocking

1. **Standardize the Meta lead_source value.** One canonical value for paid Meta, distinct from
   organic Facebook, applied at HCP intake. Until this exists, cost per booked job cannot be
   computed and the Tier 2 decision rule is inoperable.
2. **Test-call (608) 688-9109** and confirm it reaches Twins. Blocks putting it in creative.
3. **Turn off the Messenger ad auto-greeting** before archiving the campaign, so no further
   greetings fire.
4. **Name the speed-to-lead owner per shift.** Blocks the SLA being real rather than aspirational.

---

## 7. Current versus projected

| Metric | Last 30 days actual | Month 1 projected | Months 2-3 projected |
|---|---|---|---|
| Spend | $3,582.26 | $2,700 | $2,700 |
| Active campaigns | 1 of 80+ | 2 | 2 |
| Spend on zero-result campaigns | $239.56 | $0 | $0 |
| Cost per call | $22.18 | $28-40 | $20-30 |
| Cost per form lead | $77.48 | $60-80 | $45-60 |
| Cost per booked job | Not computable | Not computable | $130 target |
| Meta leads unanswered >30 min | ~20 in 4 days | 0 | 0 |

**Month 1 is projected to look worse than today.** Learning-phase resets on new ad sets inflate
costs before they improve. Confidence: **[ASSUMED]**, medium. Months 2-3 confidence: **low**
until the attribution prerequisite lands and one full cycle of real data exists.

---

## 8. Blindspots

Stated plainly, because the reviewer asked for them.

1. **The $433-per-call economics are self-reported, not measured.** Every threshold in this
   document rests on them. `calls_inbound` is empty, so there is no independent record to
   validate the 80% booking rate against.
2. **Historical creative learning is unrecoverable.** Ads named "Ad 1" through "Ad 4" mean the
   account cannot tell us which concepts worked before 2026. We are starting the creative
   knowledge base from zero, and this plan's naming convention is the fix going forward, not
   backward.
3. **Attribution is single-touch and last-click-ish.** A homeowner who sees a Meta reel, then
   searches "garage door repair Madison" and calls from Google, books as Google. Meta is
   almost certainly under-credited by an unknown amount. The 1.64x is a floor, not a point
   estimate.
4. **$90/day is genuinely small for four offers.** Approach A mitigates fragmentation but does
   not eliminate it. Cell 2 at $35/day will stay learning-limited and its reads will be
   directional for the foreseeable future. This is stated in the weekly report rather than
   papered over.
5. **Seasonality is uncontrolled.** Garage door demand in Madison is weather-driven. An August
   baseline compared against an October result confounds creative performance with the first
   freeze. No plan at this budget can separate them.
6. **One competitor action can move everything.** Auction dynamics in a single metro at
   $90/day are sensitive to any larger advertiser entering. Not controllable, only observable.
7. **The 916-712-3699 forward is unverified.** See 5.3. This is the highest-severity unknown
   in the document because acting on the phone-number fix without testing it could zero out
   Meta calls entirely.

---

## 9. Advantages and disadvantages of this plan

**Advantages**

- Preserves the only working campaign rather than rebuilding it for appearance
- Every offer Daniel asked to test actually runs
- Eliminates the "Stop" reply mechanism at its source
- Recovers the account's best creative concept (3.07% CTR) from a broken objective
- Replaces a fabricated before/after with real footage that already exists
- Fixes call attribution, which is currently invisible on most creative
- Naming convention makes future performance questions answerable
- Total marketing cost falls, since $2,400/mo of Legit5 fees ended and Meta spend stays flat

**Disadvantages**

- Cell 2 will remain statistically weak at $35/day
- Month 1 will look worse before it looks better
- The two-ad-set structure in Cell 1 is a compromise, and a reviewer may prefer a clean reset
- Retiring the AI avatar invalidates five finished assets and creates a production dependency
  on Daniel's filming availability
- Adds an operational burden (speed-to-lead SLA) that the ad account cannot itself enforce

---

## 10. Out of scope

- Milwaukee and the 414 number
- Google Ads and LSA
- Organic social and the Facebook groups program
- Website and landing page changes
- Any change to the HCP job workflow beyond the single lead_source value
