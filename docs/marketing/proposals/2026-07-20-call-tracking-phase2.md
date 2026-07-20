# Proposal — Call attribution via GoHighLevel (Phase 2)

**Status:** proposed 2026-07-20 for Daniel approval. **No new SaaS** — uses GHL, which Twins already pays for. Daniel: "no CallRail, GoHighLevel tracks calls."
**Why now:** the current "Unknown" bucket is **phone callers** — 30.8% of created jobs / 53.5% of earned revenue this week; $475,566 over 365 days. Phase 1 (web→job) can't touch it. Automated call attribution is the move that shrinks it, with zero manual/CSR step and no new tool.

## What already exists (found 2026-07-20)

The plumbing is half-built and idle:
- **`calls_inbound`** table already exists on jwrpj — columns `date, source, duration, is_lead_opportunity, is_booked, phone_number` — clearly designed to hold GHL call logs **with a channel `source` per call**. **It is empty (0 rows): nothing syncs GHL calls into it.**
- **`job_attribution_ghl` + `ghl_phone_channel_map`** — the deterministic GHL phone→channel→job pipeline from PR #331 already exists and is wired into the ROI resolver (Unattributed-only).
- **LSA and Google Ads calls** are already attributed natively; the gap is website / GBP / organic-direct calls, which is exactly what GHL call tracking + `calls_inbound` is meant to cover.

So this isn't "buy a call tracker" — it's **turn on the GHL call sync that was scaffolded and never finished, then wire it to jobs.**

## Build (no new vendor spend)

1. **GHL → `calls_inbound` sync.** Pull GHL call logs (LC Phone / LeadConnector) into the existing table: `phone_number`, `source` (channel), `duration`, `is_booked`, `date`. Via the same `ads-audit` GHL passthrough the 2026-07-06 build used (interactive session with the GHL secret — the Monday-brief session only has public keys). Backfill history + a daily cron.
2. **Match → attribution.** `calls_inbound.phone_number` (last-10) → HCP job → write channel to `job_attribution_calls` (or extend `job_attribution_ghl`). ROI resolver prefers it **only when HCP `lead_source` normalizes to Unattributed** (same safe scoping as PR #331). HCP `lead_source` never overwritten; reversible (drop the table).
3. **Report** the before/after Unknown split in the Monday brief.

## The one thing to confirm first (don't assume)

Does GHL capture a **channel/source per inbound call**, or does it only log the call? The `calls_inbound.source` column assumes the former, but `ghl_contacts.source` is null on 83% of contacts and the 2026-07-06 RESULTS found HCP-pushed contacts carry no source. **First build step (interactive, GHL access): pull a sample of GHL call logs and check whether `source` is populated with a real channel.**
- **If yes** → the sync + match is the whole job. Zero new spend, done.
- **If no** (calls ring the published number directly with no channel) → set up **GHL's own tracking numbers + DNI within LC Phone** (per channel + a website pool), so calls carry a source. Still inside the GHL platform — only GHL's per-number / per-minute usage (cents), no third-party subscription. This needs a small GHL usage OK, not a CallRail bill.

## Test cap & kill criterion

- **Cap:** the sync + wire is build-time only (no spend). If step 3b (GHL tracking numbers) is needed, cap GHL call-number usage at a small monthly number to confirm (~$20–30/mo of LC Phone usage) — a fraction of CallRail's ~$100–150.
- **Kill:** if by day 60 the brief's Unknown% isn't visibly falling, stop provisioning numbers.
- **Where it shows up:** the Monday brief — Unknown% (created + earned) and the earned-by-channel split.

## Success metric

Recent Unattributed falls automatically, phone jobs carry channels, and the channel ROI becomes trustworthy enough to decide budget on — the prerequisite the spend-brain keeps flagging, and what unblocks the paid pilots (backlog #8, #9).

## Sequencing

GHL is already live, so the **sync + backfill can start now** against whatever call history GHL holds. The website-DNI portion (if needed) pairs with the cutover (the new site is where the swappable number lives).

## The ask

Approve: **build the GHL→`calls_inbound` sync + call→job attribution now (no spend)**, and — only if the confirm step shows GHL isn't capturing per-call channel — a small **GHL tracking-number usage budget (~$20–30/mo) to test**. Report the before/after in the Monday brief. Nothing purchased outside the GHL platform you already have.
