# Twins Marketing OS — Design (Phase 0 + Phase 1)

**Date:** 2026-07-25
**Status:** Approved for planning
**Owner:** Daniel

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
| 2 | GA4, Google Search Console, Bing Webmaster ingestion | New |
| 3 | Content studio — social scheduling, AI posts and reels | Mostly port |
| 4 | WordPress publishing, technical SEO, AEO/GEO | New |
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
the dashboard's 7/30/90-day selectors read from that. What is bounded is the
*refresh* window: each nightly run re-pulls only the trailing 90 days, on the basis
that jobs older than 90 days no longer restate. Initial load backfills all
available history once.

**Volume:** roughly 3,000 rows per nightly refresh.

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
| `lp_leads` | 22 rows total; one free-text `utm` field |
| Phone calls attributed to campaign | 0 |

Spend is per-campaign; revenue is per-channel. Nearly all revenue arrives by phone,
and a call carries no campaign unless the caller dialled a campaign-specific number.
This is the same class of problem as the unattributed-revenue bucket: **a source
that was never recorded cannot be backfilled.**

**Design response.** Phase 1 ships channel-level truth, which is solid. A
`campaigns` table and a job→campaign link table carrying `method` and `confidence`
are built now and left empty. When capture goes live they populate with no
migration. The Campaigns page renders spend, clicks and leads — real today — and
renders ROAS and CAC as explicitly not-yet-measurable rather than as zeros.

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

## Out of scope for this document

Phases 2–5 (analytics ingestion, content studio, WordPress/SEO/AEO/GEO, backlinks).
Each gets its own brainstorm and spec. Their nav slots exist in Phase 1 so they
integrate without restructuring.
