# Coach v2 (Lean) — Design Spec

**Date:** 2026-05-03
**Owner:** Daniel
**Status:** Design (approved 2026-05-03; replaces the automation-heavy parts of `2026-04-26-trainingpeaks-ai-coach-design.md`)
**Supersedes:** WhatsApp + cron + phased-rollout components of the original spec. Athlete model + intervals.icu data layer carry over unchanged.

## Why this spec exists

The original Coach design (2026-04-26) called for a long-running service on a VPS that ran weekly Sunday cycles, daily safety checks, and a WhatsApp inbound/outbound channel. We built M0–M6 (data layer, athlete model, WhatsApp outbound stubs, Phase 0 Sunday loop, macOS launchd deploy artifacts) before hitting two friction walls:

1. Oracle Always Free ARM capacity is unavailable in Daniel's only-allowed region (Ashburn, all 3 ADs out of capacity).
2. The Meta WhatsApp Business setup is a ~30-minute Daniel-only step plus a Meta template review delay before any value can be demonstrated.

After several hours of infrastructure work without any actual training value delivered, Daniel scoped down: **drop the automation entirely; just have Claude read his TP data and push a week directly when he asks.**

## Goal

Daniel pings Claude in conversation. Claude:

1. Refreshes intervals.icu data (existing `sync` job)
2. Reads the assembled state (last 4 weeks actuals, recent wellness, athlete model snapshot, race calendar, athlete.md constraints)
3. Reasons about and writes a structured week as JSON
4. **Auto-publishes** the workouts to intervals.icu (which auto-syncs to TP — no per-workout approval)
5. Confirms what landed in TP

No WhatsApp. No cron. No launchd. No phased rollout.

## Non-goals

- Daily mid-week safety check (auto-downgrade on HRV crash) — out of scope; if Daniel feels off mid-week, he pings Claude
- Mid-week race-week guardrail automation — relies on Claude being careful in the planning prompt
- Strength morning delivery message — out of scope
- WhatsApp inbound parsing — out of scope
- Coach-vs-Claude shadow diff — out of scope (his coach leaves before any shadow value would land)
- A separate hosting environment — runs from Daniel's Mac via the existing CLI when he asks

## Architecture overview

```
   Daniel pings Claude in chat
            │
            ▼
   Claude runs (in this conversation):
   ┌────────────────────────────────────────────┐
   │  npm run cli sync           ──────────────▶│ intervals.icu API ───▶ SQLite
   │  npm run cli state          ──────────────▶│ stdout markdown
   │                                            │
   │  Claude reads markdown, reasons,           │
   │  writes plan as JSON to /tmp/week.json     │
   │                                            │
   │  npm run cli publish-week /tmp/week.json   │
   │       │                                    │
   │       ▼                                    │
   │  validate w/ Zod schema                    │
   │  translate JSON → intervals.icu events     │
   │  POST /athlete/{id}/events (loop)          │
   └────────────────────────────────────────────┘
            │
            ▼
   intervals.icu auto-syncs planned events → TrainingPeaks
            │
            ▼
   Daniel sees the week in TP within minutes
```

## Components

### What's already built (kept unchanged)

- `src/intervals/client.ts` — REST client with basic auth + retry-aware error structure
- `src/intervals/{activities,wellness,events}.ts` — fetchers
- `src/db/*` — SQLite + queries (activities, wellness, notes, plans, athlete-model, sync-state)
- `src/athlete/{profile,model}.ts` — `athlete.md` parser + athlete-model derivation
- `src/jobs/sync.ts` — daily incremental sync (now ad-hoc, no cron)
- `src/jobs/refresh-model.ts` — athlete model refresh (now ad-hoc)
- `src/lib/{logger,retry}.ts`
- `scripts/bootstrap.ts` — one-time 12-month history ingest
- `src/config.ts` (only intervals.icu env vars are required; WhatsApp vars dropped from `required()` checks)
- All 43 existing tests stay green

### What's new (~150 lines total)

**`src/planner/schema.ts`** — Zod schema for a weekly plan (Sport enum, Intent enum, Step, Day, WeeklyPlan), per the original M8 design. Used by both the planner reasoning (Claude consumes the type) and the publisher (validates before POST).

**`src/planner/translate.ts`** — Converts a `Day` from the schema into an `IntervalsEventInput` for the intervals.icu Events API. Handles bike/run/swim/brick (workout body description), strength (calendar block with description), and rest (NOTE event). Per the original M10 design, the translator emits description-based workouts (not structured `workout_doc` JSON) — that's enough for TP to display the session and Daniel to execute on his Garmin.

**`src/intervals/publish.ts`** — `publishWeek(client, athleteId, weekStart, events)`: deletes any existing `[claude]`-tagged events in the target week (idempotent re-publishing), POSTs each new event with the `[claude]` tag prepended to its description.

**`src/planner/state.ts`** — Assembles a markdown snapshot of the planning context: race calendar (from athlete.md), training time budget, last 28 days of activities (sport / duration / TSS), last 14 days of wellness (HRV / sleep / body battery), latest athlete model snapshot. Output goes to stdout for Claude to read in conversation.

**`src/cli.ts`** gains two commands:
- `state` — runs `runSync` + `runRefreshModel` + emits the state markdown
- `publish-week <path>` — reads a JSON file at `<path>`, validates against `WeeklyPlanSchema`, translates, calls `publishWeek`, prints a summary

### What gets deleted

- `src/whatsapp/` (entire directory)
- `src/jobs/sunday-cycle.ts`
- `src/planner/observation.ts`
- `deploy/macos/` (entire directory)
- `deploy/sunday-cycle.{service,timer}`
- `deploy/sync.{service,timer}`
- `deploy/oracle-setup.md`
- `deploy/whatsapp-setup.md`
- `scripts/deploy.sh`
- All matching test files
- `WHATSAPP_*` env vars no longer required by `loadConfig()`

The deleted code remains in git history (commits `8069dbf`, `2ac4312`, `4578511`, etc.) for future reference if Daniel ever wants the WhatsApp path back.

## Data flow

1. **Refresh** — `cli sync` calls intervals.icu, upserts new activities + wellness into SQLite, updates per-resource `sync_state`.
2. **State assembly** — `cli state` queries SQLite for the last 28 days of activities + 14 days of wellness, loads latest athlete model row, parses `athlete.md`, formats as markdown to stdout.
3. **Reasoning** — Claude (in this conversation) reads the markdown. Considers race countdown, training time budget, last week's actuals vs. plan, recent HRV trend. Writes a JSON week to `/tmp/week-YYYY-MM-DD.json`.
4. **Publish** — `cli publish-week` validates JSON against `WeeklyPlanSchema`, translates each `Day` to an `IntervalsEventInput`, deletes any prior `[claude]`-tagged events in that week's date range, POSTs each new event. intervals.icu syncs to TP automatically (Daniel's TP Premium connection).
5. **Confirm** — `cli publish-week` prints `{ created: [event_ids], deleted: [event_ids] }`. Claude shows Daniel a summary in chat with a TP calendar link.

## Error handling

- **intervals.icu API errors:** `IntervalsClient.get/post/put/delete` already throws on non-2xx with status + body. The publish loop is best-effort: if event N fails, events 1..N-1 are committed and the failure is reported. Daniel can re-run `publish-week` on the same JSON; the delete-existing step is idempotent.
- **Schema validation fails:** `cli publish-week` exits non-zero with the Zod error message. Claude fixes the JSON and re-runs.
- **TP sync delay:** intervals.icu → TP sync is "near-real-time" (Daniel's TP Premium); typical delay 1–5 minutes. Claude includes a "may take a few minutes to appear in TP" note in the confirmation.
- **No data:** if intervals.icu has no activities (e.g., Daniel hasn't connected TP+Garmin yet), `cli state` emits a markdown report with empty sections. Claude detects the empty state and tells Daniel to connect a source before planning.

## Athlete profile (`athlete.md`)

Same template as the original spec; Daniel maintains it. The hard guardrail "max 6 training days per week, min 1 full rest day" is enforced **by Claude in reasoning**, not by code. The original `validate.ts` auto-correct routine is dropped (Claude handles it).

## What Daniel does once

1. **Connect data sources in intervals.icu** — Settings → Connections → connect TrainingPeaks (for planned workouts visibility + actuals from his coach) and Garmin Connect (for HRV + sleep + body battery wellness data). One-time OAuth flow, ~5 min. **Without this, intervals.icu has zero data and the system can't plan anything.**

2. **Edit `athlete.md`** to fill in the placeholders (race names, swim pool, OWS access). The current file at `~/twins-dashboard/coach/athlete.md` already has Daniel's two A races (May 17 70.3, Sept 13 70.3 Worlds Marbella).

That's it. Everything else happens in conversation with Claude.

## Tradeoffs

| Original automation feature | What we lose | Why it's OK |
|---|---|---|
| Sunday 5pm cron | Plans don't auto-arrive on Sunday | Daniel can ping Claude any day; the planning value isn't time-locked to Sunday evening |
| Daily 6am safety check | No auto-downgrade on HRV crash | Daniel can text Claude "feeling cooked" mid-week and Claude re-publishes today's session |
| WhatsApp outbound summary | No phone notification | Daniel sees the week in TP — same place he'd train from anyway |
| WhatsApp inbound + intent classifier | Can't reply with travel/wellness updates | Daniel tells Claude in conversation; Claude updates `athlete.md` if needed |
| Phase 0 / Phase 1 / Phase 2 | No shadow comparison vs his coach | Daniel sees his coach's plans in TP already; no value in Claude shadowing for 4 weeks |
| Strength morning delivery | No 1-hour-before lift list | Daniel reads the strength block in TP that morning |
| Healthcheck + backup jobs | No nightly DB backup | DB is rebuildable from intervals.icu (re-run `bootstrap` if needed); on his Mac with Time Machine anyway |

## Reliability

- This is a 100% synchronous, on-demand system. No background jobs, no servers to crash, no timers to miss. The only thing that can fail is the moment Daniel actively asks for a plan — and at that moment Claude is right here to retry, re-prompt, or escalate.
- DB lives at `~/twins-dashboard/coach/coach.db` on Daniel's Mac. If the file is lost, `npm run bootstrap` rebuilds it from intervals.icu in ~30 seconds.

## Open questions for the implementation phase

- Default workout time-of-day for published events (intervals.icu requires `start_date_local`). Original spec hardcoded 06:00; carry forward.
- Whether `cli state` should also include the most recent 7 days of *planned* events from intervals.icu (so Claude can see what coach prescribed). Recommend yes — small addition, big value during the May 17 race-prep block.
- Whether to store published plans in the `plans` SQLite table for `/why` retrospection. Recommend yes — already designed, costs ~3 lines in `cli publish-week`.

## Out of scope (deferred)

- WhatsApp reintegration (the modules exist in git history)
- Hosted long-running automation (revisit if/when Daniel wants Sunday evenings to be hands-off)
- intervals.icu native `workout_doc` structured workouts (richer Garmin sync) — current path uses descriptions, which is enough to start
