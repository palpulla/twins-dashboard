# Twins Marketing OS — Design (Phase 0 + Phase 1)

**Date:** 2026-07-25
**Status:** Approved for planning
**Owner:** Daniel

## Repository layout — read this before touching files

Two **separate** git repositories, one nested inside the other:

| Path | Remote | Holds |
| --- | --- | --- |
| `~/twins-dashboard` | `palpulla/twins-dashboard` | This spec, `docs/`, `twins-content-engine/`, marketing audits |
| `~/twins-dashboard/twins-dash` | `palpulla/twins-dash` | The TwinsDash app — `src/`, `supabase/functions/`, migrations |

Specs live in the outer repo, code in the inner one. Phase 0's UI and function
changes are all inner-repo; the launchd plists under `twins-content-engine/deploy/`
are outer-repo.

## Baseline — trailing 90 days (verified 2026-07-25)

| Platform | Spend | Campaigns | Clicks | Leads |
| --- | --- | --- | --- | --- |
| Google LSA | $21,261.45 | 1 | 2,335 | 272 |
| Google Ads | $11,503.30 | 4 | 749 | 63 |
| Meta Ads | $7,213.98 | 7 | 4,164 | 95 |
| **Total** | **$39,978.73** | **12** | 7,248 | 430 |

Twelve campaigns carry all paid spend. That is small enough that campaign-level
analysis is tractable the moment revenue can be attached to it.

## Problem

All Twins marketing capability currently lives inside `twinsdash.com`, an ops and
field-service dashboard. Three things are wrong with that at once:

1. **The container.** Marketing is buried inside a dispatch tool. It is not branded,
   not designed for a CMO, and does not feel like a marketing product.
2. **The features.** Some are genuinely weak. Others only appear weak — the social
   scheduler has never published anything because it is blocked on Meta App Review
   and Google Business Profile API access, not because the code is broken.
3. **The gaps.** The half Daniel most cares about does not exist at all: WordPress
   publishing, technical SEO, backlinks, GA4 / Search Console / Bing ingestion,
   AEO/GEO, and campaign-level attribution.

Separately, four independent publishing paths are running or installable, with no
single place to see or control them.

## Locked decisions

| Decision | Choice |
| --- | --- |
| Build target | New standalone product: new GitHub repo, Vercel, new Supabase project |
| Data architecture | **Option B** — new Supabase project as marketing system of record, narrow nightly mirror of revenue and spend from `twins-dash-prod` |
| Build order | Money dashboard first; content studio at Phase 3 |
| Campaign attribution | Ship channel-level now; campaign schema built dormant; capture provisioned in parallel |
| Google Ads campaigns | In scope for Phase 1 — verify matching, read campaign CPA, add conversion values for campaign ROAS |
| Mirror refresh window | 365 days, not 90 (restatement beyond 90 days could not be ruled out) |
| Auth | Supabase Auth, invite-only, single shared `admin` role, no data partitioning |
| Stack | Next.js App Router, TypeScript, Tailwind, shadcn/ui, Framer Motion |
| Ad spend in Phase 1 | Mirrored from `twins-dash-prod`, not re-integrated (accepted debt) |
| GHL Social Planner | Stays usable by hand as the stopgap until Phase 3 |
| TwinsDash after teardown | One read-only Marketing page **plus** the assign-source worklist |

## Scope decomposition

The original request spans roughly eight independent subsystems. It is decomposed
into six sub-projects. **This document specifies Phase 0 and Phase 1 only.** Each
later phase gets its own brainstorm, spec and plan when it is reached.

| Phase | Scope | Nature |
| --- | --- | --- |
| **0** | Disable schedulers; strip TwinsDash to stats | Removal |
| **1** | New app + money dashboard (spend, ROAS, CAC, attribution) | Move + extend |
| 2 | ~~GA4, Search Console, Bing ingestion~~ **→ CONSUME what P1 of the GA4 track builds** | Revised — see §Collision |
| 3 | Content studio — social scheduling, AI posts and reels | Mostly port |
| 4 | WordPress publishing, technical SEO, AEO/GEO | **Premise invalid** — see §Collision |
| 5 | Backlinks and off-page | New |

Rough sizing with focused build time: Phase 0 ~½ day, Phase 1 ~6–8 days, Phase 2
~3 days, Phase 3 ~3 days, Phases 4–5 ~2 weeks. The content studio is small because
it is largely a port; it lands in week 3 because three phases precede it.

---

# Phase 0 — Strip TwinsDash back to stats

## Inventory (verified live, 2026-07-25)

**Publishing and generation — to be switched off**

| Where | What | Detail |
| --- | --- | --- |
| `twins-dash-prod` cron 103 | `publish-content-5min` | Auto-posts approved queue items to Facebook, Instagram and GBP every 5 min. Has never published live. |
| `twins-dash-prod` cron 106 | `poll-video-jobs-2min` | Renders reel videos via Grok Imagine, attaches to queue items. |
| macOS launchd | `com.twins.ig-publish` | Mon/Wed/Fri 12:00, runs `publish_ig.py --live` direct to Instagram. **Last published a real post 2026-07-15.** Not currently loaded; plist still present in `twins-content-engine/deploy/`. |
| macOS launchd | `com.twins.ig-generate`, `com.twins.ig-remind` | Weekly IG batch generation; 09:00 approval reminder. Same status. |
| CLI → GHL | `twins-content-engine/scripts/publish_to_ghl.py` | Stages approved posts as drafts in the GHL Social Planner. |

**Data ingestion — stays running**

| Where | What |
| --- | --- |
| cron 36 | `meta-ads-sync-nightly` |
| cron 37 | `meta-leads-sync-nightly` |
| cron 33 | `ghl-contacts-sync-nightly` |
| cron 105 | `sync-post-performance-nightly` (read-only) |
| cron 104 | `spend-recommendations-monday` — proposes only, executes nothing |

**Already paused** — Ashley `text-agent` (cron 99) and voice-agent crons 91, 100,
101, 102, paused 2026-07-23. No action.

> `call-intake-process-5min` (cron 63) must NOT be touched. It is not a marketing
> scheduler; it is inbound call lead capture.

## Steps

1. `select cron.alter_job(103, active := false);` and the same for `106`.
2. Add env kill switches to the `publish-content` and `poll-video-jobs` edge
   functions so a manual invoke cannot publish. The cron disable is the real
   guarantee; this covers the warm-isolate case.
3. Delete `twins-content-engine/deploy/*.plist` and verify via `launchctl list`
   that no `com.twins.*` agent is loaded. The engine's *generation* scripts remain —
   they are the seed for Phase 4.
4. Make `publish_to_ghl.py` dry-run-only (remove the write path, keep the preview).
   The GHL Social Planner UI itself stays usable by hand.
5. Remove from `twins-dash`: `src/pages/marketing/Queue.tsx`,
   `src/pages/marketing/Calendar.tsx`, `src/pages/marketing/Spend.tsx`,
   `src/components/marketing/AiComposer.tsx`,
   `src/components/marketing/ContentCard.tsx`, their routes, hooks and nav entries.
   Consolidate what remains into one read-only Marketing page built from
   `Channels.tsx` and the funnel / live-lead / review panels of
   `MarketingSourceROI.tsx`.
6. **Retain the assign-source worklist** and its guarded `set_job_lead_source` RPC.
   It is a data-quality tool, not a publishing control, and it is what has been
   shrinking the unattributed-revenue bucket.
7. **Drop no data.** `content_items`, `content_performance`, `video_jobs` and
   `spend_recommendations` keep every row.

## Reversibility

Every step is reversible except step 3 (plist deletion), which is recoverable from
git history. Cron re-enable is one statement per job.

---

# Phase 1 — New app + money dashboard

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│ twins-marketing-os  (new GitHub repo → Vercel)          │
│ Next.js App Router · TS · Tailwind · shadcn/ui · Motion  │
│ Supabase Auth — invite-only, single `admin` role         │
└──────────────────────────┬──────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────┐
│ NEW Supabase project (us-east-1)                        │
│  · measurement schema — owned channel logic + rollup    │
│  · mirrored facts — jobs_slice, spend                   │
│  · campaign layer — built, dormant until capture exists │
└──────────────────────────┬──────────────────────────────┘
                           │ nightly PULL (read-only RPC + dedicated key)
┌──────────────────────────▼──────────────────────────────┐
│ twins-dash-prod (eu-west-1) — unchanged source of truth  │
│  jobs · hcp_data · marketing_spend                       │
│  read-only Marketing page + assign-source worklist       │
└─────────────────────────────────────────────────────────┘
```

### Why these choices

**New project, not a schema in `twins-dash-prod`.** `twins-dash-prod` cannot test
migrations before production — preview branches always fail there — and Phase 1
writes a lot of new schema. It has also already hit disk-IO exhaustion once;
hanging a marketing platform off it risks taking dispatch and payroll down with it.

**US East region.** All users are in Wisconsin. `twins-dash-prod` is in `eu-west-1`,
which costs a transatlantic round trip per query. No reason to inherit that.

**Pull, not push.** The new project reaches into TwinsDash through a single
read-only function with a dedicated key. TwinsDash gains no marketing awareness and
no new write paths. A broken mirror makes marketing stale and touches nothing
operational.

**Measurement logic is ported, not called.** If the new app called TwinsDash's
rollup, marketing definitions would still be changed by editing TwinsDash — the
exact coupling this project exists to remove. The new project owns
`_canonical_channel` and the rollup.

### Accepted debt

Phase 1 mirrors ad spend rather than re-integrating the Meta, Google Ads and LSA
APIs into the new project. Those syncs work and rebuilding them produces nothing
user-visible. The consequence is that marketing still depends on a TwinsDash cron
until a later phase migrates them. Stated deliberately, not discovered later.

## Data mirror

**Direction:** new project pulls, on a nightly schedule.

**Interface:** one read-only, versioned function on `twins-dash-prod` returning a
narrow slice. Callable only by a dedicated key.

**`jobs_slice` columns:** job id, customer id, completed date, revenue, canonical
channel, raw lead source, business unit, market, service zip.

**`spend` columns:** mirrored from `marketing_spend` — date, platform,
campaign_name, spend_amount, clicks, leads_generated.

**Retention vs refresh — these are different.** The mirror **retains full history**;
the dashboard's period selectors read from that. What is bounded is the *refresh*
window. Initial load backfills all available history once.

**Refresh window: 365 days.** An attempt to justify a 90-day window against live
data failed to do so. Of 1,094 jobs completed in the last year, **296 (27%) were
written to more than 90 days after completion**, with a maximum lag of 270 days.
That signal is `jobs.updated_at`, which is confounded — a bulk sync rewriting a row
looks identical to a genuine revenue restatement, and `jobs` is not in `audit_log`,
so the two cannot be separated from the database. Rather than assume, the window is
set to 365 days: that is ~1,100 rows per night instead of ~3,000 per quarter, so the
volume is irrelevant at this scale and there is nothing to gain by tightening it.

**Restatement handling — critical.** Jobs grow after completion and invoice sync
self-heals, so revenue for an already-completed job changes. The nightly run
**upserts the trailing window rather than appending**. An append-only mirror would
drift from TwinsDash within a week.

**Atomicity.** Each run is all-or-nothing for its window. A shape mismatch against
the versioned contract fails loudly to the health check rather than writing partial
rows.

## Campaign attribution — current reality

Verified 2026-07-25. **Campaign-level ROAS is not computable today.**

| Signal | State |
| --- | --- |
| `gclid`, `fbclid`, `wbraid`, `gbraid` | **No such column anywhere in the database** |
| `jobs` campaign identifier | None — canonical channel only |
| `marketing_spend` | Per-campaign ✔ (`campaign_name`) |
| `meta_leads` | 38 rows, carries `meta_campaign_id` and `meta_ad_id` |
| `lsa_leads` | 309 rows, no campaign identifier |
| `lp_leads` | 22 rows total. Its `utm` column is jsonb but holds **no UTM data** — only `consent`, `service`, `form_variant`, `chooser_token`, `zip`. The name is misleading. |
| Phone calls attributed to campaign | 0 |

The search covered column names *and* the contents of every `jsonb` column in the
database, including `jobs.hcp_data`, `ghl_contacts.raw_payload`,
`lsa_leads.raw_payload` and `meta_leads.raw_payload`.
`ghl_contacts.attribution_source` exists but is NULL on all 1,531 rows.

Spend is per-campaign; revenue is per-channel. Nearly all revenue arrives by phone,
and a call carries no campaign unless the caller dialled a campaign-specific number.
This is the same class of problem as the unattributed-revenue bucket: **a source
that was never recorded cannot be backfilled.**

### Google Ads is the exception — a cheaper path already exists

`offline-conversions-weekly` (cron 86, Fridays 10:07 UTC) has been uploading every
booked HCP job to Google Ads as an enhanced conversion since 2026-07-04, keyed on
SHA-256 hashed email and phone, deduped by `transactionId = jobs.id`. It is healthy:
32 of 32 accepted on 2026-07-24, `status = ok`. Google therefore matches these
against its own click records and can attribute **booked jobs per campaign**.

Two gaps sit between that and usable numbers:

1. **The read side.** `google-ads-sync` requests
   `campaign.name, segments.date, metrics.cost_micros, metrics.clicks,
   metrics.conversions` with no `segments.conversion_action`. The uploaded action
   ("Booked Job (HCP)", id 7672808531) is **SECONDARY**, so it is expected to be
   excluded from the `metrics.conversions` figure currently stored as
   `leads_generated`. Segmenting by conversion action should surface campaign-level
   booked jobs, and therefore **campaign CPA for Google Ads**.
2. **The write side.** The upload payload carries `transactionId`, `eventTimestamp`
   and `userIdentifiers` — and **no `conversionValue` or `currency`**. Google can
   count the jobs but does not know what any of them is worth, so campaign ROAS is
   unavailable until values are sent. Jobs upload at *booking*, when final revenue
   is unknown, so this requires value-adjustment uploads after completion rather
   than simply adding a field.

**Limits, stated plainly.** This is Google's modelled attribution, not ground truth:
it will not reconcile to the jobs table, the match rate is not observable from our
side, and it covers Google Ads only — $11,503 of $39,979 in trailing-90-day spend.
It does **not** replace call tracking, which remains the only path to campaign
attribution for LSA ($21,261) and Meta ($7,214). It has also **not** been verified
against the live Google Ads account that matches are actually landing; that
verification is the first task of this workstream, before anything is built on it.

**Design response.** Phase 1 ships channel-level truth, which is solid. A
`campaigns` table and a job→campaign link table carrying `method` and `confidence`
are built now and left empty for the call-tracking path. The Campaigns page renders
spend, clicks and leads — real today — plus Google Ads campaign CPA once verified,
and renders anything not yet measurable as explicitly unavailable rather than as
zeros. Where a figure comes from Google's model rather than our own attribution, the
UI must say so.

## Surfaces

Four pages ship. Later-phase nav entries are visible but disabled with phase badges,
so the product shape is legible from first login and each phase fills a slot rather
than rearranging navigation.

| Page | Contents |
| --- | --- |
| **Overview** | Spend, revenue, blended ROAS, CAC, jobs. Period selector (7/30/90d), market filter. Spend-vs-revenue trend. Confidence strip. |
| **Channels** | Per-channel spend, revenue, jobs, ROAS, CAC. Unknown-revenue honesty meter. |
| **Campaigns** | Per-campaign spend, clicks, leads. ROAS/CAC shown as not-yet-measurable. |
| **Data health** | Attribution confidence, campaign coverage, mirror freshness. |

**Data health is a first-class page, not a settings tab.** A third of revenue was
unattributed a week ago and campaign coverage starts at zero. The number describing
how much to trust the dashboard belongs beside the dashboard. The Overview
confidence strip carries the same signal so no ROAS figure can be read without
seeing how much revenue it accounts for.

## Error handling

| Failure | Behaviour |
| --- | --- |
| Mirror stale | Freshness badge amber past 24h, red past 48h, explicit "as of" timestamp. Dashboard never blanks and never presents stale data as current. |
| Upstream schema drift | Versioned contract; mirror fails to health check, writes nothing partial. |
| Revenue restated upstream | Trailing-window upsert corrects it on the next run. |
| Date-boundary mismatch | Spend is platform-dated, revenue is completion-dated. All normalised to `America/Chicago`, documented in code. (Precedent: the scheduler shipped a timezone bug that would have posted off-schedule.) |
| Zero-spend channel | ROAS is **undefined**, rendered `n/a`. Never `∞` — an infinity symbol in a ROAS column trains the reader to ignore the column. |
| Small sample | Below the existing 8-job floor used by the spend rules, figures render with a low-confidence marker rather than a confident-looking value. |
| Auth | Invite-only, no public signup. RLS default-deny on every new table. |

## Testing

**Acceptance test for ported measurement logic:** the new project's rollup must
reconcile **to the dollar** against TwinsDash's existing rollup over the same
90-day window. Pass/fail, not a judgement call. This is how the original rollup was
validated.

Additionally:

- Mirror idempotency — run twice, identical result.
- Restatement — change revenue upstream, confirm the mirror corrects it.
- Contract test — malformed upstream response writes nothing.
- Migrations tested on preview branches (the capability the fresh project buys).
- Component and E2E coverage on period switching, market filter, and the stale,
  empty and low-confidence states.

## Rollout

1. Phase 0 lands first, fully reversible.
2. Phase 1 deploys to a non-public URL.
3. Parallel-run against the TwinsDash page until the two agree to the dollar.
4. Only then is the TwinsDash page described as legacy.
5. No TwinsDash data is dropped at any point.

## Parallel owner track — start immediately

None of this is code; all of it is wall-clock, and all of it gates later phases.

1. **Meta App Review + Google Business Profile API access.** 1–4 weeks of external
   waiting and the longest pole in the project. Started now, it clears roughly when
   the Phase 3 content studio is ready. Started at Phase 3, auto-posting begins
   around week 7.
2. **Per-campaign call tracking numbers.** The automated fix for the attribution
   gap — dynamic number insertion captures the channel from the number dialled, with
   no human step.
3. **`gclid` / `fbclid` capture** in GTM on `twinsgaragedoors.com`, carried through
   to the job.

Deliberately **not** recommended: making Lead Source a required field in HCP. It
works, but it inserts a human step in front of every job and decays. Call tracking
captures the same information automatically.

## Known risk discovered during this design: untracked production functions

An audit of all 97 deployed edge functions against the `twins-dash` repo found
**ten running in production with no source in any repo**. Their only copy was on
Supabase's servers.

**Recovered and committed** to branch `chore/recover-untracked-edge-functions` in
the `twins-dash` repo, all extracted from the deployed bundles' source maps so they
are original TypeScript rather than transpiled output:
`offline-conversions-weekly`, `ads-audit`, `sync-google-ads`.

**Still unrecovered** (owner decision, non-marketing): `ghl-env-probe`,
`hcp-twinshield-api-probe`, `hcp-twinshield-price-forms-setup`,
`internal-ops-eod-response`, `reconcile-jobs-weekly`, `voice-agent-call-recap`,
`xai-probe`. Nothing in the repo is undeployed, so the drift is one-directional.

### `sync-google-ads` is a live hazard to Phase 1

Recovering it revealed a **second writer to `marketing_spend`**, duplicating the
cron-driven `google-ads-sync`. The two disagree on the platform label:

| | Platform written | Trigger | Dedupe |
| --- | --- | --- | --- |
| `google-ads-sync` (in repo) | `google_ads` | cron 34, nightly | `upsertAdSpendRows` |
| `sync-google-ads` (was untracked) | `Google Ads` | admin auth, manual only | deletes only `platform = 'Google Ads'` |

Because its delete-before-insert matches only its own label, it cannot remove the
cron's rows and the cron cannot remove its. An admin invoking it would add a
parallel set of rows that **no dedupe on either side clears**, silently inflating
Google Ads spend for any query summing `marketing_spend` without a platform filter —
including the mirror this project depends on.

**Verified not yet triggered (2026-07-25):** `marketing_spend` contains only
`google_ads`, `google_lsa` and `meta_ads` across all 1,799 rows since 2025-01-01.
No `'Google Ads'` rows exist and no cron calls the function. Current spend figures
are sound.

**Owner decision required.** Recommended: tombstone `sync-google-ads` the way
`xai-probe` was retired, so the trap cannot be sprung. Phase 1's mirror should
additionally filter `marketing_spend` to the known platform allow-list rather than
trusting the table, so a stray writer can never silently move a ROAS number.

### `ads-audit` is useful here, not just debt

It is a read-only GAQL executor against the Google Ads account, gated by
`ADS_AUDIT_SECRET`, self-described as temporary. It is the natural instrument for
Phase 1's first task — verifying that the offline-conversion matches are actually
landing per campaign — and should not be deleted until that verification is done.

## Collision with parallel work — READ BEFORE PLANNING PHASE 1

Discovered 2026-07-25 after Phase 0 shipped, by reading
`twins-google-ads/docs/superpowers/specs/2026-07-25-p0-sever-and-baseline-design.md`
(branch `feat/attr1-pipelines`) and the code beside it. **Three findings invalidate parts of
this document.** Phase 0 is unaffected and stands; Phases 1, 2 and 4 are not safe to plan
until these are resolved.

### 1. A better attribution ledger already exists, and it is homeless

`twins-google-ads/google_ads_ops/attribution/` is a **Postgres event ledger** (`ledger.py`,
`psycopg` against a DSN) with 12 test files. It models far more than this spec's proposed
`campaigns` table plus job→campaign link:

- `LeadEvent` with **`gclid` / `gbraid` / `wbraid`**, `caller_e164`, `call_start_at`,
  `value_micros`, `click_at` (documented "None => windows fail closed"), and
  `consent_ad_user_data`
- `FunnelStage` initial → qualified → booked → completed; `Origin` form / booking / call,
  where booking means a confirmed HCP book-now webhook and **never** a button click
- `Route` data_manager / call_import, plus `routing`, `windows`, `consent`, `acceptance`,
  `health`, `datamanager`, `call_upload`, `feeds`
- Idempotent insert-once claims where the uniqueness constraint IS the route lock

It is **not deployed and has no database** — it needs a Postgres DSN.

**Consequence:** building this spec's campaign layer as written would create a second, weaker
attribution system beside a better unshipped one. Three systems would then claim attribution:
`twins-dash-prod` (channel-level, live), this ledger (click-level, designed, homeless), and
the new project.

**Likely synthesis, but an owner decision:** the new Supabase project becomes the Postgres
home for the existing ledger, rather than growing a parallel model. **Do not plan Phase 1's
campaign layer until this is decided.**

### 2. Phase 4's premise is invalid

This document assumed blog posts and pages could be scheduled into WordPress. In fact
`twinsgaragedoors.com` is a **WordPress multisite currently being relaunched**
(`danielj140.sg-host.com` → production, carrying `/wi`, `/ky`, later `/il`), and the new site
is **contract-gated**: changes are hash-pinned code shipped through a release pipeline
(`renderers.php` `wp_head`, dual manifests, release-id rotation), not plugin toggles or REST
API writes. Automated publishing into that is a different problem from the one specced here.

### 3. Phase 2 should consume, not build

The parallel track's P1 creates one new GA4 property with market as a dimension, and its P2
adds `ga_client_id`/`ga_session_id` to the attribution ledger. Phase 2 here should **ingest
from that property**, not stand up its own GA4/GSC work.

### Scheduling conflict

That track's **P0 has a hard deadline of 2026-08-08**, tied to the Legit5 manager-link
revocation. It competes directly with Phase 1 for attention, and it is deadline-driven where
Phase 1 is not.

## Out of scope for this document

Phases 2–5 (analytics ingestion, content studio, WordPress/SEO/AEO/GEO, backlinks).
Each gets its own brainstorm and spec. Their nav slots exist in Phase 1 so they
integrate without restructuring.
