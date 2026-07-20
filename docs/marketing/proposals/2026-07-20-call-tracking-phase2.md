# Proposal — Call tracking (attribution Phase 2)

**Status:** proposed 2026-07-20 for Daniel approval. **Spends money → needs explicit approval before signup** (authority matrix). This is the mini-plan; nothing is purchased until approved.
**Why now:** the current "Unknown" bucket is **phone callers** — 30.8% of created jobs / 53.5% of earned revenue this week, $475,566 over 365 days. Phase 1 (web→job) can't touch it (dry-run confirmed 0 web matches; the Unknown is phone). Automated call attribution is the one move that shrinks it, and it fits the "automate, never a manual/CSR step" rule.

## What's already attributed vs the gap

Not everything needs a tool:
- **Google LSA calls** — already attributed natively in the LSA dashboard (that's why "WI Google LSA" jobs are tagged). No tool needed.
- **Google Ads calls** — call reporting / call assets already capture these (see the CAP audit `google-ads/11-call-assets.json`, `12-call-reporting.json`). No tool needed.
- **The gap → Unknown:** calls from the **website's displayed number**, the **Google Business Profile listing**, and **organic/direct** carry no channel. These are the bulk of the phone Unknown.

So the tool's job is narrow: put a channel on **website + GBP + organic/direct** calls. That keeps the plan (and the bill) small.

## Recommended provider: CallRail

Standard for local home-services; has dynamic number insertion (DNI), call recording/transcription, webhooks, and CRM/HCP integrations we can pipe into Supabase.

**Pricing (published 2026):** base **Call Tracking $50/mo** (5 numbers, 250 local minutes, tracking + attribution + recording + transcription); overages local minutes **$0.05/min**, extra numbers **$3 each**. Realistic all-in for our volume runs above the base once minutes are counted — plan for **~$100–$150/mo**. **14-day free trial** on every tier — validate before a dollar is spent. (Alternatives if we dislike it: WhatConverts, CallTrackingMetrics — comparable, CallRail is the default.)

## Setup

1. **DNI on the new website** — a JS snippet swaps the *displayed* number by traffic source (paid / organic / GBP / direct). Every website-sourced call gets a channel. **Rings the same line — tracked numbers forward to the published (608) 888-8785; caller ID + local presence preserved** (STRATEGY: never surface the 833 van number; tracked numbers are measurement only).
2. **Tracked GBP number** — attribute calls straight off the Google Business Profile listing.
3. **A small DNI pool** (~5 numbers, base plan covers it) + one tracked number for organic/direct.

## Data wiring (mirrors Phase 1, fully reversible)

CallRail webhook → new `call_leads(call_id, tracked_number, channel, caller_phone, started_at, recording_url, raw jsonb)` on jwrpj → deterministic **caller-phone (last-10) → HCP job** match → `job_attribution_calls(job_id, channel, matched_phone, match_method, mapped_at)`. The ROI resolver prefers it **only when HCP `lead_source` normalizes to Unattributed** (same safe scoping as the GHL rescue, PR #331). HCP `lead_source` never overwritten; drop the tables to revert.

## Test cap & kill criterion

- **Test cap:** free 14-day trial, then up to **2 paid months at ≤$150/mo → hard cap $300** total before a keep/kill decision. No annual commitment.
- **Kill criterion:** if by day 60 call tracking has **not** put a real channel on ≥ ~40% of previously-Unknown *phone* jobs (i.e., the brief's Unknown% isn't visibly falling), cut it at the cap.
- **Where the result shows up:** the Monday brief — Unknown% (created + earned) and the earned-by-channel split, week over week.

## Success metric

Recent Unattributed falls automatically, phone jobs carry channels, and the LSA/Ads/GBP/organic split becomes trustworthy enough to make budget calls on — which is the exact prerequisite the spend-brain keeps flagging ("investigate Google Ads / Meta tracking before spending more") and what unblocks the paid pilots (backlog #8, #9).

## Sequencing note

The **DNI/website** portion switches on when the production site is live (it's a snippet on the new site) — so it pairs with the cutover. The **GBP + organic tracked numbers can start immediately** and begin capturing the biggest Unknown source right away. Recommend: approve now, stand up GBP/organic tracking on the trial this week, add website DNI at launch.

## The ask

Approve: **CallRail 14-day trial → up to $300 test spend**, GBP/organic tracking now + website DNI at cutover, kill at day 60 if Unknown isn't falling. On approval I build the `call_leads`/`job_attribution_calls` pipeline and the resolver tier (reversible), and report the before/after in the Monday brief.
