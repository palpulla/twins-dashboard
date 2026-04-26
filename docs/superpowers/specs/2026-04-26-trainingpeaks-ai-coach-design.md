# TrainingPeaks AI Coach — Design Spec

**Date:** 2026-04-26
**Owner:** Daniel
**Status:** Design (pre-implementation)

## Problem and goal

Daniel is an age-group triathlete training for two 70.3 races in 2026: one on May 17 (already trained for, currently coached) and the 70.3 World Championship on September 13 (Marbella). His current coach leaves in June 2026. Daniel is partially burned out on the sport, wants to stay fit but cap weekly volume at 10 hours typical / 14 hours peak, travels frequently, and wants the training plan to absorb travel and life without becoming a chore.

The system replaces the coach in June: it reviews actuals, programs the plan toward race goals, publishes structured workouts directly into TrainingPeaks, and adapts mid-week when wellness or context demands it. Daniel's primary interface is WhatsApp; he never logs into a dashboard.

## Non-goals

- Not a coaching platform for other athletes; single-user system for Daniel
- Not a nutrition tracker; weight loss is honored as context, not programmed
- Not a swim-stroke or run-form analysis tool
- Not a race-day pacing strategy generator (that's a separate one-off conversation 2 weeks pre-race)
- Not building a UI; WhatsApp is the only interface

## Architecture overview

```
                    ┌──────────────────────────────────┐
                    │      Oracle Cloud Free VPS       │
                    │  (Node + Claude Code SDK auth)   │
                    │                                  │
   ┌─────────┐      │  ┌────────────────────────────┐  │      ┌──────────────┐
   │ systemd │ ───▶ │  │  Sunday 5pm: weekly loop   │  │ ───▶ │ intervals.icu│
   │ timers  │      │  │  Daily 6am: safety check   │  │      │     API      │
   └─────────┘      │  └────────────────────────────┘  │      └──────┬───────┘
                    │                                  │             │
                    │  ┌────────────────────────────┐  │             │ auto-sync
   ┌──────────┐     │  │  Webhook: WhatsApp inbound │  │             ▼
   │  Daniel  │ ──▶ │  └────────────────────────────┘  │      ┌──────────────┐
   │ WhatsApp │ ◀── │                                  │      │TrainingPeaks │
   └──────────┘     │  SQLite (athlete model + state)  │      └──────┬───────┘
                    └──────────────────────────────────┘             │
                                                                     │ auto-sync
                            ▲                                        ▼
                            │                                 ┌──────────────┐
                            └──────── reads ──────────────────┤Garmin Connect│
                                                              │   (watch)    │
                                                              └──────────────┘
```

Four input streams feed Claude → Claude generates a structured weekly plan → published to intervals.icu → auto-syncs to TP → auto-syncs to Garmin → Daniel executes → actuals flow back the same path.

## Phased rollout

The system rolls out in three phases to build trust before any auto-publishing happens. This is the single most important design decision: Daniel does not go from "coached athlete" to "Claude programs my training" cold.

### Phase 0: Observe (now → 2026-05-17, ~3 weeks)

Read-only. Claude pulls Daniel's full TrainingPeaks history (12+ months), his coach's prescribed workouts, his actuals, and his wellness data. It builds the **athlete model**: typical FTP, threshold pace, CSS, weekly distribution, recovery patterns, session compliance rates, response to training load. Sends one short WhatsApp on Sunday summarizing what it learned that week. **Zero plan writing. Zero risk to the May 17 race.**

### Phase 1: Shadow (2026-05-17 → June, ~3-4 weeks)

Coach is still publishing workouts. Each week, Claude generates the plan it *would* have published and includes a diff against the coach's actual plan in the Sunday WhatsApp summary. Example: "Coach gave you 4×1km Tuesday at threshold. I would have given 5×800m at VO2 — your last 4 weeks of TSS suggests you can absorb a harder VO2 day this block." This is the trust-building phase. Daniel sees Claude's reasoning before it touches anything live.

### Phase 2: Live (June onward)

Coach gone. Claude takes over. Sunday auto-publishes next week to intervals.icu → syncs to TP. Daily 6am safety check reads overnight Garmin data + inbound WhatsApps and adjusts mid-week if needed. Weekly summary every Sunday with the upcoming week + last week's review.

## Components

### 1. Data layer

Four input streams flow into a local SQLite store:

- **Activity history** (intervals.icu API): every workout for the last 12+ months — sport, duration, distance, TSS, IF, NP, HR/pace zones, planned vs actual, perceived exertion, coach notes. Pulled once at setup; incremental sync daily.
- **Wellness signals** (intervals.icu, sourced from Garmin): HRV, resting HR, sleep duration + score, body battery, weight if logged, subjective wellness ratings if filled in. Pulled daily.
- **Calendar context** (intervals.icu day-notes + WhatsApp inbound): TP day-notes mirror to intervals.icu and Claude reads them. WhatsApp messages to the dedicated Meta Cloud API number get parsed by Claude (intent classification: travel / wellness / workout swap / race scope / other) and tagged for the next planning cycle.
- **Athlete profile** (`athlete.md` in the project repo): the source of truth for intent and constraints. Claude reads every run, never edits without confirmation.

The store also caches the **athlete model** Claude derives from history — true zones, compliance rates, recovery profile, travel patterns — refreshed weekly so the planner doesn't re-derive from raw data every run.

Explicitly *not* tracked: nutrition, macros, calories.

### 2. Planner

Two loops.

#### Sunday weekly cycle (5pm local, default)

1. Refresh state — pull last 7 days of actuals, wellness, notes.
2. Determine block context — recovery / base / build / peak / taper, weeks to next race, last week's accomplishment vs plan.
3. Read upcoming-week constraints — TP day-notes for next week, recent WhatsApp messages, travel signals, wellness trend (HRV trend, sleep debt, training load ramp).
4. Generate the week as structured JSON: 7 days, each with sport, intent ("Z2 endurance" / "VO2 intervals" / "race-pace brick"), duration, structured intervals where applicable, equipment notes ("hotel gym OK"), one-line "why" per session.
5. Validate against guardrails:
   - Weekly TSS ramp ≤ ~10% from last week
   - No two hard sessions back-to-back
   - Strength sessions away from key SBR sessions
   - Total hours within Daniel's phase budget (10 base / 14 peak)
   - Travel days honored
   - **Maximum 6 training days per week, minimum 1 full rest day** (hard guardrail)
   - Race-week sessions are not auto-modified
6. Publish to intervals.icu → auto-syncs to TP.
7. Send Sunday WhatsApp summary.

#### Daily safety check (6am local)

1. Pull last 24 hours of wellness data + any new WhatsApp messages.
2. Compare today's planned session against fresh signals.
3. Adjust per these rules:
   - **Auto-execute downgrades.** HRV in red zone, sleep <5 hrs, "feeling cooked" texted → Claude swaps today's hard session for an easier one and texts the swap.
   - **Recommend-only for upgrades.** HRV elevated, body battery 95+, signals say Daniel can absorb more → Claude texts "Want me to add a 30' VO2 block to today's bike? Reply YES" and waits.
   - **Never adjusts more than today.** Reshaping the week is a Sunday-style replan, triggered by Daniel.
   - **Never adjusts a race or race-week session** automatically.

### 3. Publishing path

```
Claude JSON → translator → intervals.icu structured workout API
   → intervals.icu syncs to TP (Daniel's TP Premium connection)
   → TP syncs planned workouts to Garmin Connect
   → Garmin syncs to watch
```

The **translator module** is the only complex piece — converts Claude's clean schema into intervals.icu's structured workout DSL. Single module, well-tested; the rest of the system doesn't have to know about TP/intervals quirks.

**Sync delays:** intervals.icu → TP is near-realtime; TP → Garmin can take 5–30 min. The Sunday WhatsApp summary notes "workouts will appear on your watch within 30 min."

**Failure handling:** retries with backoff at every step; WhatsApp alert if a step fails to recover within 1 hour. Fallback for prolonged intervals.icu outage (>24 hrs): Claude texts the week as a structured WhatsApp message Daniel can paste/type into TP manually. Worst case = 5 minutes of typing; never "no plan."

**Strength sessions:** can't be structured like a bike interval. Get scheduled in TP as a calendar block with description ("Strength A: 4×{trap-bar deadlift 5 reps, single-leg RDL 8/leg, weighted plank 30s}"). Daniel logs them himself in TP (or skips — adherence tracked loosely). **Also delivered as a WhatsApp message 1 hour before the scheduled time** so the lift list is on Daniel's phone in the gym.

### 4. Communication

Exactly four types of outbound messages, all WhatsApp:

1. **Sunday weekly summary** (~5 lines). Format:
   > *Week of May 25 published — 9.5 hrs total*
   > *Hard day: Wed bike VO2 (5×4'). Long day: Sat 90' brick.*
   > *Travel honored Thu–Sat (run + hotel-gym strength).*
   > *Last week: 10/11 sessions ✓, HRV steady, ramp on track.*
2. **Strength morning delivery** (day-of, 1 hr before). Just the lift list.
3. **Mid-week safety adjustment.** Only fires when something changes — auto-downgrade or upgrade-recommendation request.
4. **Failure / system alert** (WhatsApp + email backup). Only when something broke and Daniel should know.

**Inbound:** Daniel texts free-form to the dedicated number. Claude classifies intent (travel / wellness / workout swap / race scope / other) and acts:
- Travel / wellness / scope → tagged for the next planning cycle
- Workout swap → executed within an hour, confirmation back
- `/replan` → forces immediate week regeneration
- `/why` → Claude replies with the reasoning behind the current week's plan
- `/profile` → Claude replies with the current athlete model snapshot

### 5. Athlete profile (`athlete.md`)

Daniel-maintained markdown file in the repo, source of truth for intent and constraints. Claude reads every run, suggests edits via WhatsApp ("your CSS dropped 2s/100m — update profile?") but never edits without confirmation. Template:

```markdown
## Race calendar
- 2026-05-17: 70.3 [name/location] — A race (currently coached)
- 2026-09-13: 70.3 World Championship, Marbella — A race, goal: enjoy + finish strong

## Training time budget
- Base weeks: 10 hrs target
- Peak weeks: 14 hrs cap
- Strength: 3x/week (may freelance)

## Discipline mix
- Triathlon focus, no standalone running/cycling races planned
- Swim access: [pool name + days available]
- Bike: indoor/outdoor mix, [trainer model if relevant]
- Open water: [yes/no, when, where]

## Travel pattern
- Frequent business travel (varying)
- Hotel gym is the floor — Claude assumes basic dumbbells + treadmill available

## Constraints + preferences
- Burned out lately, plan must protect motivation
- Want to lose weight; Claude factors as context, doesn't program nutrition
- Maximum 6 training days per week, minimum 1 full rest day (HARD)
- Avoid: [Daniel adds]
```

### 6. Athlete model (Claude-derived, in SQLite)

Continuously refreshed from activity history. Daniel never edits this directly; visible on demand via `/profile`.

- Training zones — FTP, threshold pace, CSS — recomputed monthly from recent test efforts and workout data
- Compliance rate per discipline
- Recovery profile — HRV rebound time after hard sessions
- Travel patterns from history
- Phase-transition responses
- Session-type preferences (which interval structures Daniel hits vs bonks on)

**Rule of thumb:** profile = what Daniel wants. Model = what's true about Daniel. Claude reconciles them every plan.

## Hosting

**Oracle Cloud Free Tier VPS** (4 ARM Ampere cores, 24 GB RAM, free forever). Runs:
- Node + Claude Code SDK authenticated as Daniel (uses his Pro/Max subscription, no Anthropic API bill)
- SQLite store (single file, nightly backup to S3-compatible free tier or git)
- pg_cron-equivalent via systemd timers for Sunday + daily 6am triggers
- HTTPS endpoint for Meta WhatsApp Cloud API webhook
- Healthcheck script that pings the instance daily and alerts via WhatsApp if Oracle reclaims it

**WhatsApp via Meta Cloud API directly** (1,000 free service conversations/month, well above expected ~60/month).

**Code:** TypeScript, repo under `~/twins-dashboard/coach/`, GitHub for source.

**Cost:** $0/month marginal. Daniel's existing Claude subscription powers all AI calls.

## Reliability and failure modes

| Failure | Detection | Response |
|---|---|---|
| Sunday plan generation fails | Cron exits non-zero | Retry 3× with backoff; WhatsApp alert if all fail |
| intervals.icu API down | API call fails | Retry; if down >1 hr, WhatsApp alert; if down >24 hr, fallback WhatsApp with manual workout list |
| TP sync stalls | Workouts not visible in TP after 30 min | WhatsApp alert |
| Garmin sync stalls | TP shows planned, Garmin doesn't | Note in summary; no auto-action |
| Oracle reclaims VPS | Daily healthcheck fails | WhatsApp alert; spin up replacement (manual ~30 min) |
| Daniel doesn't reply to upgrade-recommendation | No reply within 4 hrs | Default to no-upgrade (publish stays as-is) |
| WhatsApp/Meta API down | Meta API errors | Email fallback for critical alerts only |
| Claude SDK rate-limited by sub | SDK error | Retry with longer backoff; WhatsApp alert if persistent |

## Open questions for the implementation phase

These are spec-complete but need plan-phase resolution:

- Exact cadence of athlete-model refresh (weekly vs on-demand on big-zone-change detection)
- intervals.icu structured workout DSL nuances for swim sets (less mature than bike/run in their format)
- Sunday plan-generation prompt structure (system prompt, context window organization, output schema)
- Meta WhatsApp Cloud API onboarding (template approval for proactive messages outside the 24-hr session window)
- Backup strategy for SQLite (git-commit nightly vs S3)
- Initial athlete-model bootstrapping (how many weeks of history to ingest; expected token cost)

## Out of scope (deferred)

- Mobile/web dashboard
- Multi-user support
- Race-day pacing strategy generator
- Open-water swim-specific programming (treated same as pool until Daniel signals OWS access)
- Nutrition / fueling programming
- Strength video form analysis
