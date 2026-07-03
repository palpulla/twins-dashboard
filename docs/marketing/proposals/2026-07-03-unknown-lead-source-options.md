# Shrinking the "Unknown" lead source — options for Daniel

**Date:** 2026-07-03 (approved as spec-only in the 2026-07-03 brief)
**Problem:** 18 of 95 completed jobs in the trailing 30 days carry HCP lead source "Unknown" — $41,910 of earned revenue (35%) with no usable attribution. Every scale/kill decision on paid channels is half-blind until this shrinks.

**Where it comes from:** `lead_source` is set by whoever books the job in HousecallPro. "Unknown" is a literal dropdown value being chosen (or defaulted) at intake — this is a process gap, not a data bug. Standing constraint: we never guess attribution from free text (no heuristic classifiers); fixes must be at the source or via deterministic matching.

## Option A — CSR intake discipline (recommended, do first)
Ivory (and anyone booking) asks "how did you hear about us?" on every new-customer call and picks a real source; "Unknown" stops being an acceptable default. One line added to the intake script; can be tracked in her EOD form as a simple "jobs booked / sources captured" count.
- Cost: $0. Effect: cuts new Unknowns at the source, starting immediately.
- Needs from you: tell Ivory this is now required (or approve me drafting the script line + EOD tweak for her).

## Option B — HCP source-list hygiene (do with A)
The current HCP source list mixes eras and granularities ("Google" vs "Reserve with Google" vs "WI Google LSA" vs "Facebook" vs "Facebook Ads"). Tighten it so paid and organic are separable: e.g. Google Ads (paid), Google LSA, Google organic/Maps, Facebook, Referral, Existing Customer, Door Sticker, Online Booking, Other. Keep it under ~10 choices so CSRs actually use it.
- Cost: $0, a few minutes in HCP settings. Effect: makes the "Google" bucket ($24,694/30d) finally splittable into paid vs organic.
- Needs from you: approve the list (I'll propose the exact final list), then edit in HCP settings (or grant me the steps to walk through with you — HCP has no API write for this).

## Option C — Deterministic GHL rescue (build later, after A/B)
`ghl_contacts` already phone-matches contacts to HCP jobs (186 matched today). Once GHL attribution capture is wired (backlog #6 — currently 0 of those 186 have attribution data), we can fill in the source for Unknown jobs by exact phone match, displayed as a separate "rescued attribution" layer like the ROI page's existing LSA/Meta rescue — never overwriting HCP data.
- Cost: build cycle (spec → plan → build) after backlog #6. Effect: retroactive + ongoing rescue of Unknowns that came through GHL-tracked channels.
- Needs from you: nothing yet; it becomes a proposal in a future Monday brief once #6 lands.

## Recommendation
A + B this week (both free, both stop the bleeding), C as the follow-on build.

---

## EXECUTED 2026-07-03 (Daniel approved "as you recommend")

**B — done.** HCP lead-source list cleaned via the HCP settings UI: 44 → 20 active. Kept: CareFree Club, CSR AI (reserved for call-intake pipeline), Existing Customer, Facebook, Facebook Ads, Google, HCP Chat, HouseCall App, HouseCall Marketplace, Lead Form, Online Booking, Referral, Reserve with Google, Text Campaign, Thumbtack, Toll Free Number, Van, WI Door Sticker, WI Google Ads, WI Google LSA. Removed 24 dead/junk/duplicate entries (son, truck, Zory FB/Google Ads, Google Local Services, Facebook Group/Organic, WI BNI/Business Card/Door Hanger/GBP/ValPak/Yard Sign, AC Doctor, Cold Call, Door Knocking, Legit5, Next Door, Old Site, OnCall Air, Prospex AI, ResponsiBid, TV Ads, Website Builder). HCP-default sources were archived (restorable from the Archived tab); custom ones were deleted (restorable by re-creating the name). Historical job records unchanged (confirmed by HCP's own dialog + jwrpj retains raw strings). No renames, so no downstream mapping was touched.

**A — script ready.** Note for Daniel to forward to Ivory (below). Compliance is tracked automatically: the Monday brief now reports the weekly Unknown% of booked jobs (baseline: 52% of jobs over the prior 180 days).

> Hi Ivory, one new standard starting today: every job we book needs a real lead source in Housecall Pro. When you book, ask "By the way, how did you hear about us?" and pick the matching option from the Lead Source dropdown. I cleaned the list up so it is short now. If the caller is vague, one quick follow-up works: "Was that Google, Facebook, or a referral?" Existing customers get Existing Customer. Please never leave the field empty. We decide our ad budget from these numbers, so this really matters. Thank you!

**C — deferred** until GHL attribution capture (backlog #6) lands, as planned.
