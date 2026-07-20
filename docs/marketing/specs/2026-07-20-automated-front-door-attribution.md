# Automated front-door lead attribution

**Status:** **Phase 1 APPROVED by Daniel 2026-07-20** (build the web→job attribution wiring; reversible, no spend). Phase 2 (call tracking) still needs a budget call. Phase 3 is maintenance on the existing GHL rescue.
**Goal:** capture lead source **automatically at the front door**, so the "Unknown"/Unattributed bucket shrinks by system, never by a person tagging it. No CSR/manual-intake step (`twins-automate-not-manual`).

## Why

- Unknown is still the biggest measurement problem: **30.8% of created jobs and 53.5% of earned revenue** this week (7/10–7/16); 35% of earned on the 30-day baseline.
- The 2026-07-06 GHL phone→channel build already proved the recent bucket is **not a matching problem — it's a front-door capture gap.** Of the last-30-day Unattributed jobs, **18/18 matched a GHL contact by phone, but the channel was never captured** (they were pushed into GHL from HCP, not captured as leads). After-the-fact matching cannot recover a source that was never recorded.
- The one front door that currently records source automatically is **web forms** (`lp-lead-intake` captures utm/page/form_variant → `lp_leads` + GHL "Website LP"). The hole is **phone calls** — the dominant channel — where the *only* current source capture is a human typing it into HCP. That is exactly the manual step to automate away.

So: finish wiring the capture that already exists (web), and add the capture that's missing (calls). Keep the GHL rescue for history.

## Build (phased)

### Phase 1 — Web lead source → job attribution (no budget; the website launch feeds it)
`lp-lead-intake` already stores each web lead's real source (`utm_*`, `gclid`/`fbclid`, `page`, `form_variant`) in `lp_leads`. It does **not** yet attribute the resulting HCP *job*.
1. New table `job_attribution_web(job_id, channel, utm jsonb, lp_lead_id, matched_phone, match_method, mapped_at)`.
2. Deterministic match: `lp_leads.phone` (last-10) → HCP job phone, within a sane time window; map `utm_source`/`gclid`/`fbclid` to the canonical channel set via the **existing** `ghlSourceToCanonical`/`PLATFORM_TO_CANONICAL` (never a new keyword classifier — `feedback_no_heuristic_classifiers_for_business_rules`).
3. ROI resolver prefers `job_attribution_web` **only when HCP `lead_source` normalizes to Unattributed** (same safe-scoping the GHL rescue landed on in PR #331).
- **The production website callback form posts to `lp-lead-intake`** (the path we just fixed), so once the site is live, every web lead self-attributes with zero human step. Phase 1 turns that captured source into job attribution.

### Phase 2 — Call tracking + dynamic number insertion (the big one — needs Daniel budget approval)
Phone is where the Unknown revenue actually hides, and no software captures it today.
1. Per-channel tracked numbers (Google LSA, Google Ads, Facebook, GBP, plus a DNI pool that swaps the site number by referrer/utm) via a call-tracking provider (CallRail-class). **Paid service → test cap + kill criterion + Daniel approval per the authority matrix.**
2. Provider webhook → `call_leads(call_id, tracked_number, channel, caller_phone, started_at, …)`.
3. Deterministic match `caller_phone` (last-10) → HCP job → `job_attribution_calls`; ROI resolver prefers it for Unattributed-only, same as web.
4. Local-number rule from STRATEGY.md still holds — tracked numbers are for measurement; published local number is (608) 888-8785.
- Kill criterion suggestion: if after 30 days call tracking attributes < (some %) of previously-Unknown *phone* jobs, it isn't earning its monthly fee — cut it.

### Phase 3 — Keep + harden the GHL rescue (already built)
`job_attribution_ghl` recovers *historical* GHL-originated leads (365-day: 65 jobs / $124,794). Keep it, scope it to Unattributed-only, and add the missing canonical rules the RESULTS flagged (e.g. Text Campaign) so it stops re-bucketing already-good HCP sources.

## Success metric

Recent Unattributed **falls week-over-week automatically** in the Monday brief — both created-job % and earned-revenue % — with **no human tagging in the loop**. Report the corrected earned-by-channel split (esp. true Google Ads vs organic, and paid Facebook) so channel ROI becomes trustworthy enough to decide budget on. This is the prerequisite the spend-brain keeps asking for ("investigate Google Ads / Meta tracking before spending more").

## Guardrails

- **Reversible:** all attribution lands in new tables; HCP `lead_source` is never overwritten; drop the tables to revert. KPI math immutable.
- **Deterministic only:** exact last-10 phone (or gclid/fbclid) matches. No keyword/heuristic channel guessing.
- **Real data only**, full dollar amounts, canonical channel names via existing mappers (production parity).
- **Authority:** Phase 1 + 3 are reversible builds (no spend) — build on approval. Phase 2 spends money → explicit Daniel approval with a dollar test cap and kill criterion before any provider is signed.

## Related

- Builds directly on `specs/2026-07-06-ghl-phone-channel-attribution*` (the root-cause finding is the reason this spec exists).
- BACKLOG #3 (attack Unknown) and #6 (ROI attribution) — this **replaces** #3's manual "Ivory intake script" approach with automated capture. Re-rank on approval.
- Fed by the website launch: the production callback form → `lp-lead-intake` (see `production-cutover/`).
