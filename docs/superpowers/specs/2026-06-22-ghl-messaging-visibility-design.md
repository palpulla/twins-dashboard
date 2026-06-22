# Dunzo / GHL Messaging Pipeline — Visibility First — Design

**Status:** Draft, ready for plan
**Author:** Claude (with Daniel)
**Date:** 2026-06-22
**Platform:** GoHighLevel (Dunzo agency account, `app.godunzo.com`, location "Twins Garage Doors")
**Repo touched:** `twins-dash` (palpulla/twins-dash) for any read/report code; outer repo for this spec.

## Why this spec exists

Daniel asked to look at the Dunzo pipeline, see how the messaging works, and find ways to serve and acquire customers better. Digging into the data surfaced a blocking truth: **we cannot answer "what to do better" yet, because the two things needed to judge the messaging are missing.**

1. The dashboard does not store a single outbound message. We sync GHL **contacts** only, never **conversations**. So the actual texts and emails leads receive are invisible.
2. The one funnel metric we do report, booking rate, is **wrong and drifting wronger** (details below).

So this spec is deliberately narrow: **make the pipeline visible and the number true, then stop and let Daniel decide what to improve.** No messaging is redesigned here.

## What the data actually shows (verified 2026-06-22, project `jwrpjuqaynownxaoeayi`)

- **1,115 leads** in `ghl_contacts` since 2026-03-12; 1,113 with a usable phone, near-zero duplicates, all typed `lead`.
- **Reported booking rate: 16.7%** (186 matched to an HCP job).
- **True booking rate: ~30.6%** (341 of those leads' phones appear on a real HCP job). The dashboard hides ~155 real bookings.
- **Source split is stark:** Google / Facebook / referral leads book at 54–80%; the 928 "(none)"-source leads book at ~10.8% and that bucket dominates volume.
- **Almost no segmentation:** only 16 of 1,115 contacts carry any tag at all.
- **Reported rate is sliding** month over month (Mar 32.6% → Apr 20.7% → May 15.7% → June reads 0), which is largely a measurement artifact, not a real collapse (see below).

### Why the booking number is wrong (root causes, verified)

The matcher lives at `twins-dash/supabase/functions/_shared/ghl/ghl-matcher.ts`.

1. **It stalled around May 24.** Last successful `matched_at` is **2026-05-24**; 0 matches in the last 7 days, 2 in 30 days. June shows 237 leads / 0 booked because nothing is being matched, not because nobody booked. The number degrades every day it runs like this. *Root cause (cron failure vs per-lead loop timeout as the unmatched backlog grew) is not yet confirmed and is the first task of Workstream B.*
2. **Repeat customers are discarded.** Line 55: if a lead's phone matches more than one HCP job in the window, the lead is skipped as "ambiguous" rather than counted. That erases ~26 bookings, and they are the best (repeat) customers.
3. Contributing detail: of 337 leads that had a matching job inside the existing 30-day window, only ~185 were caught; 126 single-match misses line up with the post-May-24 stall (those leads were never processed). The 30-day window itself is **not** a material cause — only 2 of 341 bookings landed outside it.

## What we cannot see from here (the gap this spec closes)

- **Outbound messages** (SMS + email bodies, automation vs human, timing): not synced. The GHL v1 REST API *does* expose a Conversations endpoint reachable with the key we already use for contacts, so this is recoverable read-only.
- **Opportunity pipeline stages** (do stages auto-advance?): not synced. Lives only in the GHL UI. Out of scope here; can be a later add if the message review shows it matters.

## Goal

Give Daniel a readable, truthful view of the real customer-messaging pipeline so he can decide what to improve, specifically:

- **A.** A one-time **thread viewer**: for a representative set of recent leads, the full conversation reconstructed from the GHL Conversations API, each message tagged automation-vs-human and SMS-vs-email with timestamps.
- **B.** A corrected, trustworthy **booking-rate** measurement (held until after A unless Daniel bundles it).

Then a **review gate**: Daniel reads the real messages and the corrected funnel, and the next spec designs concrete journey improvements with before/after proof.

## Scope & sequencing (settled with Daniel)

| Decision | Choice |
|---|---|
| Lead with | **Workstream A — see the messages.** Daniel chose "1 first." |
| Redesign messaging now? | **No.** Refuse to improve messages we have never read. |
| Booking-rate fix (B) | Documented here, **held until after A** unless Daniel says bundle. |
| Opportunity-stage sync | Out of scope. Revisit only if the review gate shows a need. |
| Permanent conversations sync | Out of scope. A is a **one-time discovery pull**, not a live sync. Decide on permanence only after Daniel sees the content (YAGNI). |

## Workstream A — surface the real texts & emails

### Data source
GHL v1 REST API, `https://rest.gohighlevel.com/v1`, Bearer key already stored as the Supabase secret used by `sync-ghl-contacts`. Conversations endpoints:
- list conversations for a contact / location,
- fetch messages for a conversation (type = SMS / Email, direction = inbound / outbound, body, timestamp).

### Sample selection (representative, not exhaustive)
Pull full threads for a small, deliberately varied set drawn from `ghl_contacts` (last ~60–90 days):
- one lead that **booked**,
- one that **went cold** (no match, no recent activity),
- one **after-hours** inbound,
- one **repeat customer** (phone on 2+ HCP jobs),
- 2–3 more spanning the high-converting sourced bucket vs the low-converting "(none)" bucket.

Roughly 6–10 threads: enough to see the real sequences and wording without building anything heavy.

### Deliverable
A readable report rendering each thread as a timeline:
`[timestamp] · SMS|Email · ⚙ automation | 👤 human · full body`, plus a per-thread header (lead source, outcome booked/cold, gaps where the sequence goes silent).

Form factor is the smaller open question (a temporary dashboard page vs a generated document). Default: whichever is faster to stand up read-only and throw away; this is discovery, not a permanent feature.

### Honesty guardrails (load-bearing)
- **No fabricated messages.** Every word shown is pulled from GHL. The brainstorm mockup's bracketed placeholders are layout only and never ship as content.
- **Read-only.** Nothing is written back to GHL or HCP. No webhooks touched.
- **No outbound pinging of Daniel.** The report is reviewed on demand.

### Fallback
If the agency's API key does not expose conversations (permission scope), Daniel screenshots 3–4 representative threads plus the workflow builder, and the same report is assembled from those.

## Workstream B — fix the booking-rate measurement (HELD)

Not started unless Daniel bundles it. When done, treated as a KPI change under Daniel's standing rules: **reversible, diff shown before applying, math documented, never silently altered.**

1. **Diagnose the stall** (cron health / function timeout as the unmatched backlog grew) and restore reliable matching.
2. **Stop discarding repeat customers** — count a clear booking even when the phone appears on more than one HCP job (e.g., take the earliest in-window job) instead of dropping the lead.
3. **Backfill** the ~155 hidden historical bookings.
4. Surface the corrected combined + per-account booking rate in the existing `GhlAttributionPanel` (the 2026-05-22 booking-rate spec already defines that panel).

## Non-goals

- No messaging redesign, new sequences, or new automations.
- No permanent conversations sync; no opportunity-stage sync.
- No GHL → HCP write path; HCP stays source of truth.
- No changes to existing KPI math beyond the booking-rate correction in Workstream B (and that only with diff + approval).
- No emails/SMS sent; no customer contact of any kind.

## Review gate (the "go from there")

After A (and B if bundled): Daniel reviews the real threads and the corrected funnel. The decision of what to improve, instant-reply / missed-lead recovery / follow-up sequences for the unbooked majority / post-job reviews / reactivation, is made **then**, from evidence, in a follow-up spec.

## Success criteria

1. Daniel can read the actual end-to-end SMS + email sequence for each sampled lead, with automation-vs-human and timing clearly marked.
2. At least one "went cold" thread makes the drop-off point visibly obvious (where follow-up stopped).
3. The corrected booking rate (~30%, vs the displayed 16.7%) is demonstrated with the underlying job links, if Workstream B is included.
4. Nothing was written to GHL or HCP; no message was sent.
5. Daniel has enough to choose the next improvement with evidence rather than guesswork.

## Open questions

- **Thread report form factor:** throwaway dashboard page vs generated document. Non-blocking; pick the faster read-only option at plan time.
- **Conversations API scope:** confirm the existing key returns conversations before relying on it; fall back to screenshots if not.
- **Bundle Workstream B or hold it:** Daniel's call; default is hold until after A.
