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

## Confirm step — DONE 2026-07-20 (GHL sample from `ghl_phone_channel_map`, 14,714 contacts)

Result: **GHL is not capturing a channel for most calls today, but it has the native capability — it's just switched off.**
- **94% of GHL contacts (13,877 / 14,714) have a null/blank source**; 458 of the ~last-60-day adds are still blank.
- The 6% with a source carry **real channels** (Google Ads 91, Facebook 63, WI Google LSA 25, Thumbtack 26, …) — GHL captures channel fine *when the lead comes through a tracked path*.
- One source value is **"Landing Page Number Pool (Number Pool)" (11 contacts)** — that is **GHL's own dynamic number-pool call tracking**. It works, but it's on 11 contacts ever and **0 in the last 60 days**. Not deployed on the live call flows.

**Conclusion → we're on the "deploy GHL's own tracking" branch (no third party).** Set up **GHL number pools + DNI within LC Phone** on the call flows (website pool + a GBP/organic tracked number, all forwarding to (608) 888-8785), so inbound calls carry a channel. GHL then stamps `source`, the existing `job_attribution_ghl` pipeline attributes the resulting job, and `calls_inbound` fills. Cost = GHL's per-number/per-minute usage only (cents), no CallRail. Needs a small GHL usage OK + the in-GHL setup — not a new subscription.

## Test cap & kill criterion

- **Cap:** the sync + wire is build-time only (no spend). If step 3b (GHL tracking numbers) is needed, cap GHL call-number usage at a small monthly number to confirm (~$20–30/mo of LC Phone usage) — a fraction of CallRail's ~$100–150.
- **Kill:** if by day 60 the brief's Unknown% isn't visibly falling, stop provisioning numbers.
- **Where it shows up:** the Monday brief — Unknown% (created + earned) and the earned-by-channel split.

## Success metric

Recent Unattributed falls automatically, phone jobs carry channels, and the channel ROI becomes trustworthy enough to decide budget on — the prerequisite the spend-brain keeps flagging, and what unblocks the paid pilots (backlog #8, #9).

## Sequencing

GHL is already live, so the **sync + backfill can start now** against whatever call history GHL holds. The website-DNI portion (if needed) pairs with the cutover (the new site is where the swappable number lives).

## Build status (2026-07-20)

Wiring built and ready (reversible), waiting on the GHL pools + sync:
- **`job_attribution_calls` table created** (migration `create_job_attribution_calls`; RLS mirrors `job_attribution_ghl` — admin/manager read, service write). `DROP TABLE` to revert.
- **Match backfill written:** `2026-07-20-phase2-call-attribution-backfill.sql` (deterministic phone match → channel; runs once `calls_inbound` has data).
- **GHL setup config for Daniel:** `2026-07-20-ghl-number-pool-setup.md`.
- **`calls_inbound` sync already exists (as a placeholder) — found 2026-07-20.** `ghl-webhook-1/2` already insert into `calls_inbound` on GHL contact/appointment events, but hardcode `source: 'GoHighLevel Account N'` (not the real channel) and the table is empty (0 rows) because the GHL webhook isn't registered to fire to these functions. **Fix (one change, both webhooks):** in the `calls_inbound` insert, replace the hardcoded source with the real channel from the payload using the existing mapper — `import { ghlSourceToCanonical } from '../_shared/ghl/ghl-source-mapper.ts'` then `source: ghlSourceToCanonical(data.contact.source, data.contact.attributionSource) ?? data.contact.source`, and keep `phone_number`. NOT applied here: `twins-dash` is on `fix/present3-manual` with 1,185 dirty files — this belongs on a clean branch by whoever owns that repo, and it can't be validated until events flow.
- **Critical path is GHL-side, not code:** for `calls_inbound` to fill, (1) apply that source-mapper fix, (2) **register the GHL webhook** to send ContactCreate/Update to `ghl-webhook-1/2`, (3) **deploy the number pools** so contacts carry a real source. All three are GHL-account setup, not something buildable from here.
- **Then (software, I own):** run the backfill → `job_attribution_calls`, report the before/after, wire the ROI resolver's call tier (Unattributed-only) once numbers check out.

## The ask

Approve: **build the GHL→`calls_inbound` sync + call→job attribution now (no spend)**, and — only if the confirm step shows GHL isn't capturing per-call channel — a small **GHL tracking-number usage budget (~$20–30/mo) to test**. Report the before/after in the Monday brief. Nothing purchased outside the GHL platform you already have.
