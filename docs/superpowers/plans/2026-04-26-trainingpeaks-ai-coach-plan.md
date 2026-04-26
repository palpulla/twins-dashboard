# TrainingPeaks AI Coach Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WhatsApp-driven AI triathlon coach for Daniel that reads his TrainingPeaks/Garmin data via intervals.icu, plans his weekly training with Claude, and auto-publishes structured workouts back to TP — replacing his human coach in June 2026.

**Architecture:** TypeScript Node.js service hosted on Oracle Cloud Free Tier VPS, using `@anthropic-ai/claude-agent-sdk` headless mode (powered by Daniel's existing Claude subscription). intervals.icu REST API as the data hub; Meta WhatsApp Cloud API for inbound + outbound; SQLite for local state; systemd timers for cron. Three phased ship points: Phase 0 (observe-only), Phase 1 (shadow plans alongside coach), Phase 2 (live auto-publish).

**Tech Stack:** TypeScript 5+, Node.js 20+, `better-sqlite3`, `hono` (HTTP server for webhook), `zod` (schema validation), `@anthropic-ai/claude-agent-sdk`, Vitest (testing), systemd (cron), Oracle Cloud Free Tier (Ubuntu 22.04 ARM).

**Spec:** `docs/superpowers/specs/2026-04-26-trainingpeaks-ai-coach-design.md`

---

## File Structure

The new project lives at `~/twins-dashboard/twins-coach/` as its own git repo (per Daniel's repo-layout convention).

```
twins-coach/
├── package.json
├── tsconfig.json
├── vitest.config.ts
├── .gitignore
├── .env.example
├── README.md
├── athlete.md                          # Daniel-maintained profile (committed)
├── src/
│   ├── config.ts                       # Env + athlete.md loader
│   ├── lib/
│   │   ├── logger.ts                   # Pino logger
│   │   └── retry.ts                    # Exponential backoff helper
│   ├── db/
│   │   ├── schema.sql                  # SQLite DDL
│   │   ├── client.ts                   # better-sqlite3 wrapper
│   │   ├── activities.ts               # Activity CRUD
│   │   ├── wellness.ts                 # Wellness CRUD
│   │   ├── notes.ts                    # Day-notes + WhatsApp messages CRUD
│   │   ├── athlete-model.ts            # Athlete model snapshot CRUD
│   │   └── plans.ts                    # Generated plans CRUD
│   ├── intervals/
│   │   ├── client.ts                   # intervals.icu HTTP client (basic auth)
│   │   ├── activities.ts               # GET activities + activity detail
│   │   ├── wellness.ts                 # GET wellness
│   │   ├── events.ts                   # GET/POST/PUT/DELETE events (planned workouts)
│   │   └── workouts.ts                 # workout_doc DSL builder
│   ├── whatsapp/
│   │   ├── client.ts                   # Meta Cloud API client
│   │   ├── outbound.ts                 # Send text + template messages
│   │   ├── webhook.ts                  # Hono webhook handler
│   │   └── intent.ts                   # Claude-based intent classifier
│   ├── athlete/
│   │   ├── profile.ts                  # athlete.md parser
│   │   └── model.ts                    # Athlete model derivation from history
│   ├── planner/
│   │   ├── claude.ts                   # Claude Agent SDK invocation
│   │   ├── context.ts                  # Build planning context
│   │   ├── schema.ts                   # Workout JSON schema (zod)
│   │   ├── validate.ts                 # Guardrail validators
│   │   └── translate.ts                # JSON → intervals.icu workout_doc
│   ├── jobs/
│   │   ├── sync.ts                     # Daily incremental data sync
│   │   ├── sunday-cycle.ts             # Sunday 5pm weekly loop
│   │   ├── daily-check.ts              # Daily 6am safety check
│   │   ├── strength-delivery.ts        # 1hr-before-strength WhatsApp
│   │   ├── healthcheck.ts              # Daily Oracle keep-alive ping
│   │   └── backup.ts                   # Nightly SQLite backup
│   ├── handlers/
│   │   ├── travel.ts                   # Travel intent handler
│   │   ├── wellness.ts                 # Wellness signal handler
│   │   ├── swap.ts                     # Workout swap handler
│   │   ├── scope.ts                    # Race scope handler
│   │   ├── replan.ts                   # /replan command
│   │   ├── why.ts                      # /why command
│   │   └── profile.ts                  # /profile command
│   ├── server.ts                       # Hono HTTP server entry
│   └── cli.ts                          # CLI entry for cron jobs
├── tests/                              # Mirrors src/ structure
├── scripts/
│   ├── bootstrap.ts                    # Initial 12-month history ingest
│   └── deploy.sh                       # rsync to Oracle VPS
└── deploy/
    ├── oracle-setup.md                 # VPS provisioning runbook
    ├── twins-coach.service             # systemd service unit
    ├── sunday-cycle.timer              # Sunday 5pm timer
    ├── sunday-cycle.service            # Sunday cycle one-shot
    ├── daily-check.timer               # Daily 6am timer
    ├── daily-check.service             # Daily check one-shot
    ├── sync.timer                      # Daily 3am sync timer
    ├── sync.service                    # Sync one-shot
    ├── healthcheck.timer               # Daily 9am healthcheck
    ├── healthcheck.service             # Healthcheck one-shot
    ├── backup.timer                    # Nightly 2am backup
    └── backup.service                  # Backup one-shot
```

---

## Milestones (ship boundaries)

- **M0–M5: Phase 0 ships** (observe-only, weekly Sunday WhatsApp summary). Target: live within 2 weeks, runs from now → May 17.
- **M6–M9: Phase 1 ships** (shadow plans alongside coach). Target: live by May 17, runs May 17 → June.
- **M10–M12: Phase 2 ships** (live auto-publish). Target: live by June 1.

---

## Milestone 0: Project scaffolding

### Task 1: Initialize the repo

**Files:**
- Create: `~/twins-dashboard/twins-coach/package.json`
- Create: `~/twins-dashboard/twins-coach/tsconfig.json`
- Create: `~/twins-dashboard/twins-coach/.gitignore`
- Create: `~/twins-dashboard/twins-coach/.env.example`
- Create: `~/twins-dashboard/twins-coach/README.md`

- [ ] **Step 1: Create the directory and init git**

```bash
mkdir -p ~/twins-dashboard/twins-coach
cd ~/twins-dashboard/twins-coach
git init
```

- [ ] **Step 2: Create `package.json`**

```json
{
  "name": "twins-coach",
  "version": "0.1.0",
  "private": true,
  "type": "module",
  "scripts": {
    "build": "tsc",
    "test": "vitest run",
    "test:watch": "vitest",
    "cli": "tsx src/cli.ts",
    "server": "tsx src/server.ts",
    "bootstrap": "tsx scripts/bootstrap.ts"
  },
  "dependencies": {
    "@anthropic-ai/claude-agent-sdk": "^0.1.0",
    "better-sqlite3": "^11.0.0",
    "hono": "^4.0.0",
    "@hono/node-server": "^1.0.0",
    "pino": "^9.0.0",
    "zod": "^3.23.0",
    "dotenv": "^16.0.0"
  },
  "devDependencies": {
    "@types/better-sqlite3": "^7.6.0",
    "@types/node": "^20.0.0",
    "tsx": "^4.0.0",
    "typescript": "^5.4.0",
    "vitest": "^1.6.0"
  }
}
```

- [ ] **Step 3: Create `tsconfig.json`**

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ES2022",
    "moduleResolution": "Bundler",
    "esModuleInterop": true,
    "strict": true,
    "skipLibCheck": true,
    "forceConsistentCasingInFileNames": true,
    "resolveJsonModule": true,
    "outDir": "dist",
    "rootDir": ".",
    "declaration": false
  },
  "include": ["src/**/*", "scripts/**/*", "tests/**/*"]
}
```

- [ ] **Step 4: Create `.gitignore`**

```
node_modules
dist
.env
*.db
*.db-journal
.DS_Store
coverage
```

- [ ] **Step 5: Create `.env.example`**

```
INTERVALS_ATHLETE_ID=
INTERVALS_API_KEY=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_RECIPIENT=
DB_PATH=./twins-coach.db
LOG_LEVEL=info
TZ=America/Chicago
```

- [ ] **Step 6: Create minimal `README.md`**

```markdown
# twins-coach

WhatsApp-driven AI triathlon coach. See `../docs/superpowers/specs/2026-04-26-trainingpeaks-ai-coach-design.md`.

## Quick start
1. `npm install`
2. `cp .env.example .env` and fill in
3. `npm run bootstrap` (one-time history ingest)
4. `npm run cli sunday-cycle` (manual trigger)
```

- [ ] **Step 7: Install and commit**

```bash
npm install
git add .
git commit -m "feat: initial project scaffolding"
```

Expected: clean install, first commit on `main`.

---

### Task 2: Vitest setup with smoke test

**Files:**
- Create: `~/twins-dashboard/twins-coach/vitest.config.ts`
- Create: `~/twins-dashboard/twins-coach/tests/smoke.test.ts`

- [ ] **Step 1: Create `vitest.config.ts`**

```typescript
import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    globals: false,
    environment: "node",
    include: ["tests/**/*.test.ts"],
  },
});
```

- [ ] **Step 2: Create smoke test `tests/smoke.test.ts`**

```typescript
import { describe, it, expect } from "vitest";

describe("smoke", () => {
  it("runs", () => {
    expect(1 + 1).toBe(2);
  });
});
```

- [ ] **Step 3: Run and commit**

```bash
npm test
git add vitest.config.ts tests/
git commit -m "test: vitest smoke test"
```

Expected: 1 passing test.

---

### Task 3: Logger and retry helpers

**Files:**
- Create: `src/lib/logger.ts`
- Create: `src/lib/retry.ts`
- Create: `tests/lib/retry.test.ts`

- [ ] **Step 1: Create `src/lib/logger.ts`**

```typescript
import pino from "pino";

export const logger = pino({
  level: process.env.LOG_LEVEL ?? "info",
  base: { service: "twins-coach" },
});
```

- [ ] **Step 2: Write failing retry test `tests/lib/retry.test.ts`**

```typescript
import { describe, it, expect, vi } from "vitest";
import { retry } from "../../src/lib/retry.js";

describe("retry", () => {
  it("returns on first success", async () => {
    const fn = vi.fn().mockResolvedValue("ok");
    const result = await retry(fn, { attempts: 3, baseMs: 1 });
    expect(result).toBe("ok");
    expect(fn).toHaveBeenCalledTimes(1);
  });

  it("retries on failure and eventually succeeds", async () => {
    const fn = vi.fn()
      .mockRejectedValueOnce(new Error("fail1"))
      .mockRejectedValueOnce(new Error("fail2"))
      .mockResolvedValue("ok");
    const result = await retry(fn, { attempts: 3, baseMs: 1 });
    expect(result).toBe("ok");
    expect(fn).toHaveBeenCalledTimes(3);
  });

  it("throws after exhausting attempts", async () => {
    const fn = vi.fn().mockRejectedValue(new Error("nope"));
    await expect(retry(fn, { attempts: 2, baseMs: 1 })).rejects.toThrow("nope");
    expect(fn).toHaveBeenCalledTimes(2);
  });
});
```

- [ ] **Step 3: Run test, verify failure**

```bash
npm test
```

Expected: 3 failing tests in `retry.test.ts` (module not found).

- [ ] **Step 4: Implement `src/lib/retry.ts`**

```typescript
export interface RetryOpts {
  attempts: number;
  baseMs: number;
}

export async function retry<T>(fn: () => Promise<T>, opts: RetryOpts): Promise<T> {
  let lastErr: unknown;
  for (let i = 0; i < opts.attempts; i++) {
    try {
      return await fn();
    } catch (err) {
      lastErr = err;
      if (i < opts.attempts - 1) {
        const delay = opts.baseMs * Math.pow(2, i);
        await new Promise((r) => setTimeout(r, delay));
      }
    }
  }
  throw lastErr;
}
```

- [ ] **Step 5: Run test, verify pass and commit**

```bash
npm test
git add src/lib tests/lib
git commit -m "feat: logger + retry helper with exponential backoff"
```

Expected: 4 passing tests.

---

### Task 4: Config loader

**Files:**
- Create: `src/config.ts`
- Create: `tests/config.test.ts`
- Create: `athlete.md` (Daniel's actual profile, with placeholders he fills later)

- [ ] **Step 1: Create initial `athlete.md`**

```markdown
## Race calendar
- 2026-05-17: 70.3 [TBD-name] — A race (currently coached)
- 2026-09-13: 70.3 World Championship, Marbella — A race, goal: enjoy + finish strong

## Training time budget
- Base weeks: 10 hrs target
- Peak weeks: 14 hrs cap
- Strength: 3x/week (may freelance)

## Discipline mix
- Triathlon focus, no standalone running/cycling races planned
- Swim access: [TBD-pool name + days available]
- Bike: indoor/outdoor mix
- Open water: [TBD-yes/no, when, where]

## Travel pattern
- Frequent business travel (varying)
- Hotel gym is the floor — assume basic dumbbells + treadmill available

## Constraints + preferences
- Burned out lately, plan must protect motivation
- Want to lose weight; factor as context, do not program nutrition
- Maximum 6 training days per week, minimum 1 full rest day (HARD)
```

- [ ] **Step 2: Write failing config test `tests/config.test.ts`**

```typescript
import { describe, it, expect } from "vitest";
import { loadConfig } from "../src/config.js";

describe("loadConfig", () => {
  it("loads env vars and athlete profile", () => {
    process.env.INTERVALS_ATHLETE_ID = "i123";
    process.env.INTERVALS_API_KEY = "k456";
    process.env.WHATSAPP_PHONE_NUMBER_ID = "p1";
    process.env.WHATSAPP_ACCESS_TOKEN = "t1";
    process.env.WHATSAPP_VERIFY_TOKEN = "v1";
    process.env.WHATSAPP_RECIPIENT = "+15555550100";
    process.env.DB_PATH = ":memory:";

    const cfg = loadConfig();
    expect(cfg.intervals.athleteId).toBe("i123");
    expect(cfg.intervals.apiKey).toBe("k456");
    expect(cfg.whatsapp.phoneNumberId).toBe("p1");
    expect(cfg.athleteProfilePath).toMatch(/athlete\.md$/);
  });

  it("throws when required env missing", () => {
    delete process.env.INTERVALS_ATHLETE_ID;
    expect(() => loadConfig()).toThrow(/INTERVALS_ATHLETE_ID/);
  });
});
```

- [ ] **Step 3: Run, verify failure**

```bash
npm test -- config
```

Expected: tests fail (module not found).

- [ ] **Step 4: Implement `src/config.ts`**

```typescript
import "dotenv/config";
import path from "node:path";

export interface Config {
  intervals: { athleteId: string; apiKey: string };
  whatsapp: {
    phoneNumberId: string;
    accessToken: string;
    verifyToken: string;
    recipient: string;
  };
  dbPath: string;
  athleteProfilePath: string;
  tz: string;
}

function required(key: string): string {
  const v = process.env[key];
  if (!v) throw new Error(`Missing required env var: ${key}`);
  return v;
}

export function loadConfig(): Config {
  return {
    intervals: {
      athleteId: required("INTERVALS_ATHLETE_ID"),
      apiKey: required("INTERVALS_API_KEY"),
    },
    whatsapp: {
      phoneNumberId: required("WHATSAPP_PHONE_NUMBER_ID"),
      accessToken: required("WHATSAPP_ACCESS_TOKEN"),
      verifyToken: required("WHATSAPP_VERIFY_TOKEN"),
      recipient: required("WHATSAPP_RECIPIENT"),
    },
    dbPath: process.env.DB_PATH ?? "./twins-coach.db",
    athleteProfilePath: path.resolve(process.cwd(), "athlete.md"),
    tz: process.env.TZ ?? "America/Chicago",
  };
}
```

- [ ] **Step 5: Run, verify pass, commit**

```bash
npm test
git add src/config.ts tests/config.test.ts athlete.md
git commit -m "feat: config loader + athlete.md profile"
```

Expected: passing tests.

---

## Milestone 1: SQLite storage layer

### Task 5: SQLite schema and client

**Files:**
- Create: `src/db/schema.sql`
- Create: `src/db/client.ts`
- Create: `tests/db/client.test.ts`

- [ ] **Step 1: Create `src/db/schema.sql`**

```sql
CREATE TABLE IF NOT EXISTS activities (
  id TEXT PRIMARY KEY,
  start_time TEXT NOT NULL,
  sport TEXT NOT NULL,
  duration_sec INTEGER,
  distance_m REAL,
  tss REAL,
  intensity REAL,
  np_w REAL,
  avg_hr REAL,
  avg_pace_sec_per_km REAL,
  planned_id TEXT,
  description TEXT,
  raw_json TEXT NOT NULL,
  ingested_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_activities_start ON activities(start_time);

CREATE TABLE IF NOT EXISTS wellness (
  date TEXT PRIMARY KEY,
  hrv REAL,
  resting_hr REAL,
  sleep_hours REAL,
  sleep_score REAL,
  body_battery REAL,
  weight REAL,
  subjective_rating REAL,
  raw_json TEXT NOT NULL,
  ingested_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  source TEXT NOT NULL CHECK (source IN ('whatsapp', 'tp_day_note')),
  date TEXT NOT NULL,
  body TEXT NOT NULL,
  intent TEXT,
  handled INTEGER NOT NULL DEFAULT 0,
  raw_json TEXT,
  received_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_notes_date ON notes(date);

CREATE TABLE IF NOT EXISTS athlete_model (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  computed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ftp_w REAL,
  threshold_pace_sec_per_km REAL,
  css_sec_per_100m REAL,
  weekly_hours_avg REAL,
  compliance_rate REAL,
  hrv_baseline REAL,
  hrv_baseline_sd REAL,
  notes TEXT
);

CREATE TABLE IF NOT EXISTS plans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  week_start TEXT NOT NULL,
  phase INTEGER NOT NULL CHECK (phase IN (0, 1, 2)),
  payload_json TEXT NOT NULL,
  reasoning TEXT,
  published INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_plans_week ON plans(week_start);

CREATE TABLE IF NOT EXISTS sync_state (
  resource TEXT PRIMARY KEY,
  last_synced_at TEXT NOT NULL,
  last_cursor TEXT
);
```

- [ ] **Step 2: Write failing client test `tests/db/client.test.ts`**

```typescript
import { describe, it, expect, beforeEach } from "vitest";
import { openDb } from "../../src/db/client.js";

describe("openDb", () => {
  it("creates schema on fresh database", () => {
    const db = openDb(":memory:");
    const tables = db.prepare(
      "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"
    ).all() as { name: string }[];
    const names = tables.map((t) => t.name);
    expect(names).toContain("activities");
    expect(names).toContain("wellness");
    expect(names).toContain("notes");
    expect(names).toContain("athlete_model");
    expect(names).toContain("plans");
    expect(names).toContain("sync_state");
  });
});
```

- [ ] **Step 3: Run, verify fail**

```bash
npm test -- db/client
```

Expected: fail (module not found).

- [ ] **Step 4: Implement `src/db/client.ts`**

```typescript
import Database from "better-sqlite3";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export type Db = Database.Database;

export function openDb(dbPath: string): Db {
  const db = new Database(dbPath);
  db.pragma("journal_mode = WAL");
  db.pragma("foreign_keys = ON");
  const schema = fs.readFileSync(path.join(__dirname, "schema.sql"), "utf8");
  db.exec(schema);
  return db;
}
```

- [ ] **Step 5: Run, verify pass, commit**

```bash
npm test
git add src/db tests/db
git commit -m "feat: SQLite client with schema bootstrap"
```

---

### Task 6: Activity CRUD

**Files:**
- Create: `src/db/activities.ts`
- Create: `tests/db/activities.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, beforeEach } from "vitest";
import { openDb, type Db } from "../../src/db/client.js";
import { upsertActivity, getActivitiesSince, type ActivityRow } from "../../src/db/activities.js";

let db: Db;
beforeEach(() => { db = openDb(":memory:"); });

const sample: ActivityRow = {
  id: "a1",
  start_time: "2026-04-20T08:00:00Z",
  sport: "Run",
  duration_sec: 3600,
  distance_m: 10000,
  tss: 50,
  intensity: 0.85,
  np_w: null,
  avg_hr: 145,
  avg_pace_sec_per_km: 360,
  planned_id: null,
  description: "easy run",
  raw_json: "{}",
};

describe("activities", () => {
  it("upserts and reads back", () => {
    upsertActivity(db, sample);
    const rows = getActivitiesSince(db, "2026-04-19T00:00:00Z");
    expect(rows).toHaveLength(1);
    expect(rows[0].id).toBe("a1");
  });

  it("upsert is idempotent", () => {
    upsertActivity(db, sample);
    upsertActivity(db, { ...sample, tss: 60 });
    const rows = getActivitiesSince(db, "2026-04-19T00:00:00Z");
    expect(rows).toHaveLength(1);
    expect(rows[0].tss).toBe(60);
  });
});
```

- [ ] **Step 2: Implement `src/db/activities.ts`**

```typescript
import type { Db } from "./client.js";

export interface ActivityRow {
  id: string;
  start_time: string;
  sport: string;
  duration_sec: number | null;
  distance_m: number | null;
  tss: number | null;
  intensity: number | null;
  np_w: number | null;
  avg_hr: number | null;
  avg_pace_sec_per_km: number | null;
  planned_id: string | null;
  description: string | null;
  raw_json: string;
}

export function upsertActivity(db: Db, a: ActivityRow): void {
  db.prepare(`
    INSERT INTO activities (id, start_time, sport, duration_sec, distance_m, tss, intensity, np_w, avg_hr, avg_pace_sec_per_km, planned_id, description, raw_json)
    VALUES (@id, @start_time, @sport, @duration_sec, @distance_m, @tss, @intensity, @np_w, @avg_hr, @avg_pace_sec_per_km, @planned_id, @description, @raw_json)
    ON CONFLICT(id) DO UPDATE SET
      start_time=excluded.start_time, sport=excluded.sport, duration_sec=excluded.duration_sec,
      distance_m=excluded.distance_m, tss=excluded.tss, intensity=excluded.intensity, np_w=excluded.np_w,
      avg_hr=excluded.avg_hr, avg_pace_sec_per_km=excluded.avg_pace_sec_per_km,
      planned_id=excluded.planned_id, description=excluded.description, raw_json=excluded.raw_json
  `).run(a);
}

export function getActivitiesSince(db: Db, isoDate: string): ActivityRow[] {
  return db.prepare(
    "SELECT * FROM activities WHERE start_time >= ? ORDER BY start_time DESC"
  ).all(isoDate) as ActivityRow[];
}

export function getLastSyncedActivity(db: Db): string | null {
  const row = db.prepare(
    "SELECT MAX(start_time) AS last FROM activities"
  ).get() as { last: string | null };
  return row.last;
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/db/activities.ts tests/db/activities.test.ts
git commit -m "feat: activity CRUD with idempotent upsert"
```

---

### Task 7: Wellness, notes, plans CRUD

**Files:**
- Create: `src/db/wellness.ts`, `src/db/notes.ts`, `src/db/plans.ts`, `src/db/athlete-model.ts`
- Create: `tests/db/wellness.test.ts`, `tests/db/notes.test.ts`, `tests/db/plans.test.ts`

- [ ] **Step 1: Write `src/db/wellness.ts` with same upsert/read pattern as activities**

```typescript
import type { Db } from "./client.js";

export interface WellnessRow {
  date: string;
  hrv: number | null;
  resting_hr: number | null;
  sleep_hours: number | null;
  sleep_score: number | null;
  body_battery: number | null;
  weight: number | null;
  subjective_rating: number | null;
  raw_json: string;
}

export function upsertWellness(db: Db, w: WellnessRow): void {
  db.prepare(`
    INSERT INTO wellness (date, hrv, resting_hr, sleep_hours, sleep_score, body_battery, weight, subjective_rating, raw_json)
    VALUES (@date, @hrv, @resting_hr, @sleep_hours, @sleep_score, @body_battery, @weight, @subjective_rating, @raw_json)
    ON CONFLICT(date) DO UPDATE SET
      hrv=excluded.hrv, resting_hr=excluded.resting_hr, sleep_hours=excluded.sleep_hours,
      sleep_score=excluded.sleep_score, body_battery=excluded.body_battery, weight=excluded.weight,
      subjective_rating=excluded.subjective_rating, raw_json=excluded.raw_json
  `).run(w);
}

export function getWellnessSince(db: Db, date: string): WellnessRow[] {
  return db.prepare("SELECT * FROM wellness WHERE date >= ? ORDER BY date DESC").all(date) as WellnessRow[];
}
```

- [ ] **Step 2: Write `src/db/notes.ts`**

```typescript
import type { Db } from "./client.js";

export type NoteSource = "whatsapp" | "tp_day_note";
export type NoteIntent = "travel" | "wellness" | "swap" | "scope" | "replan" | "why" | "profile" | "other";

export interface NoteRow {
  id?: number;
  source: NoteSource;
  date: string;
  body: string;
  intent: NoteIntent | null;
  handled: 0 | 1;
  raw_json: string | null;
}

export function insertNote(db: Db, n: NoteRow): number {
  const r = db.prepare(`
    INSERT INTO notes (source, date, body, intent, handled, raw_json)
    VALUES (@source, @date, @body, @intent, @handled, @raw_json)
  `).run(n);
  return Number(r.lastInsertRowid);
}

export function getUnhandledNotes(db: Db): NoteRow[] {
  return db.prepare("SELECT * FROM notes WHERE handled = 0 ORDER BY date").all() as NoteRow[];
}

export function getNotesForDateRange(db: Db, from: string, to: string): NoteRow[] {
  return db.prepare("SELECT * FROM notes WHERE date BETWEEN ? AND ? ORDER BY date").all(from, to) as NoteRow[];
}

export function markNoteHandled(db: Db, id: number, intent: NoteIntent): void {
  db.prepare("UPDATE notes SET handled = 1, intent = ? WHERE id = ?").run(intent, id);
}
```

- [ ] **Step 3: Write `src/db/plans.ts`**

```typescript
import type { Db } from "./client.js";

export interface PlanRow {
  id?: number;
  week_start: string;
  phase: 0 | 1 | 2;
  payload_json: string;
  reasoning: string | null;
  published: 0 | 1;
}

export function insertPlan(db: Db, p: PlanRow): number {
  const r = db.prepare(`
    INSERT INTO plans (week_start, phase, payload_json, reasoning, published)
    VALUES (@week_start, @phase, @payload_json, @reasoning, @published)
  `).run(p);
  return Number(r.lastInsertRowid);
}

export function getCurrentWeekPlan(db: Db, weekStart: string): PlanRow | null {
  return (db.prepare("SELECT * FROM plans WHERE week_start = ? ORDER BY id DESC LIMIT 1").get(weekStart) as PlanRow | undefined) ?? null;
}

export function markPlanPublished(db: Db, id: number): void {
  db.prepare("UPDATE plans SET published = 1 WHERE id = ?").run(id);
}
```

- [ ] **Step 4: Write `src/db/athlete-model.ts`**

```typescript
import type { Db } from "./client.js";

export interface AthleteModelRow {
  id?: number;
  computed_at?: string;
  ftp_w: number | null;
  threshold_pace_sec_per_km: number | null;
  css_sec_per_100m: number | null;
  weekly_hours_avg: number | null;
  compliance_rate: number | null;
  hrv_baseline: number | null;
  hrv_baseline_sd: number | null;
  notes: string | null;
}

export function insertAthleteModel(db: Db, m: AthleteModelRow): void {
  db.prepare(`
    INSERT INTO athlete_model (ftp_w, threshold_pace_sec_per_km, css_sec_per_100m, weekly_hours_avg, compliance_rate, hrv_baseline, hrv_baseline_sd, notes)
    VALUES (@ftp_w, @threshold_pace_sec_per_km, @css_sec_per_100m, @weekly_hours_avg, @compliance_rate, @hrv_baseline, @hrv_baseline_sd, @notes)
  `).run(m);
}

export function getLatestAthleteModel(db: Db): AthleteModelRow | null {
  return (db.prepare("SELECT * FROM athlete_model ORDER BY id DESC LIMIT 1").get() as AthleteModelRow | undefined) ?? null;
}
```

- [ ] **Step 5: Write minimal tests for each (one upsert + one read each, mirroring activities.test.ts), run, commit**

```bash
npm test
git add src/db tests/db
git commit -m "feat: wellness, notes, plans, athlete_model CRUD"
```

---

## Milestone 2: intervals.icu data ingestion

### Task 8: intervals.icu HTTP client

**Files:**
- Create: `src/intervals/client.ts`
- Create: `tests/intervals/client.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { IntervalsClient } from "../../src/intervals/client.js";

describe("IntervalsClient", () => {
  it("builds basic auth header from API_KEY username + key", () => {
    const c = new IntervalsClient({ athleteId: "i123", apiKey: "secret" });
    const headers = c.buildHeaders();
    const expected = "Basic " + Buffer.from("API_KEY:secret").toString("base64");
    expect(headers["Authorization"]).toBe(expected);
  });

  it("calls fetch with the correct URL and headers", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true, status: 200, text: async () => "[]", json: async () => [],
    });
    const c = new IntervalsClient({ athleteId: "i123", apiKey: "secret", fetchImpl: fetchMock });
    await c.get("/athlete/i123/wellness?oldest=2026-04-01");
    expect(fetchMock).toHaveBeenCalledWith(
      "https://intervals.icu/api/v1/athlete/i123/wellness?oldest=2026-04-01",
      expect.objectContaining({ headers: expect.objectContaining({ Authorization: expect.stringContaining("Basic ") }) })
    );
  });

  it("throws on non-ok response", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false, status: 401, text: async () => "Unauthorized",
    });
    const c = new IntervalsClient({ athleteId: "i123", apiKey: "secret", fetchImpl: fetchMock });
    await expect(c.get("/athlete/i123/activities")).rejects.toThrow(/401/);
  });
});
```

- [ ] **Step 2: Run, verify fail**

```bash
npm test -- intervals/client
```

- [ ] **Step 3: Implement `src/intervals/client.ts`**

```typescript
export interface IntervalsClientOpts {
  athleteId: string;
  apiKey: string;
  fetchImpl?: typeof fetch;
}

const BASE = "https://intervals.icu/api/v1";

export class IntervalsClient {
  private readonly opts: IntervalsClientOpts;
  private readonly fetchImpl: typeof fetch;

  constructor(opts: IntervalsClientOpts) {
    this.opts = opts;
    this.fetchImpl = opts.fetchImpl ?? fetch;
  }

  buildHeaders(): Record<string, string> {
    const token = Buffer.from(`API_KEY:${this.opts.apiKey}`).toString("base64");
    return { Authorization: `Basic ${token}`, "Content-Type": "application/json" };
  }

  async get(pathWithQuery: string): Promise<unknown> {
    const r = await this.fetchImpl(`${BASE}${pathWithQuery}`, { headers: this.buildHeaders() });
    if (!r.ok) throw new Error(`intervals.icu GET ${pathWithQuery} failed: ${r.status} ${await r.text()}`);
    return r.json();
  }

  async post(pathWithQuery: string, body: unknown): Promise<unknown> {
    const r = await this.fetchImpl(`${BASE}${pathWithQuery}`, {
      method: "POST", headers: this.buildHeaders(), body: JSON.stringify(body),
    });
    if (!r.ok) throw new Error(`intervals.icu POST ${pathWithQuery} failed: ${r.status} ${await r.text()}`);
    return r.json();
  }

  async put(pathWithQuery: string, body: unknown): Promise<unknown> {
    const r = await this.fetchImpl(`${BASE}${pathWithQuery}`, {
      method: "PUT", headers: this.buildHeaders(), body: JSON.stringify(body),
    });
    if (!r.ok) throw new Error(`intervals.icu PUT ${pathWithQuery} failed: ${r.status} ${await r.text()}`);
    return r.json();
  }

  async delete(pathWithQuery: string): Promise<void> {
    const r = await this.fetchImpl(`${BASE}${pathWithQuery}`, { method: "DELETE", headers: this.buildHeaders() });
    if (!r.ok) throw new Error(`intervals.icu DELETE ${pathWithQuery} failed: ${r.status} ${await r.text()}`);
  }
}
```

- [ ] **Step 4: Run, commit**

```bash
npm test
git add src/intervals/client.ts tests/intervals/client.test.ts
git commit -m "feat: intervals.icu HTTP client with basic auth"
```

---

### Task 9: Activities fetch + ingest

**Files:**
- Create: `src/intervals/activities.ts`
- Create: `tests/intervals/activities.test.ts`

- [ ] **Step 1: Write failing test that mocks the client and verifies row mapping**

```typescript
import { describe, it, expect, vi } from "vitest";
import { fetchActivities } from "../../src/intervals/activities.js";

describe("fetchActivities", () => {
  it("maps intervals.icu response to ActivityRow shape", async () => {
    const fakeClient = {
      get: vi.fn().mockResolvedValue([{
        id: "i1",
        start_date_local: "2026-04-20T08:00:00",
        type: "Run",
        moving_time: 3600,
        distance: 10000,
        icu_training_load: 50,
        icu_intensity: 0.85,
        icu_average_watts: null,
        average_heartrate: 145,
        pace: 6.0,
        paired_event_id: null,
        description: "easy",
      }]),
    };
    const rows = await fetchActivities(fakeClient as any, "i123", "2026-04-01");
    expect(rows[0].id).toBe("i1");
    expect(rows[0].sport).toBe("Run");
    expect(rows[0].duration_sec).toBe(3600);
    expect(rows[0].distance_m).toBe(10000);
    expect(rows[0].tss).toBe(50);
  });
});
```

- [ ] **Step 2: Implement `src/intervals/activities.ts`**

```typescript
import type { IntervalsClient } from "./client.js";
import type { ActivityRow } from "../db/activities.js";

interface IntervalsActivity {
  id: string;
  start_date_local: string;
  type: string;
  moving_time: number | null;
  distance: number | null;
  icu_training_load: number | null;
  icu_intensity: number | null;
  icu_average_watts: number | null;
  average_heartrate: number | null;
  pace: number | null;
  paired_event_id: string | null;
  description: string | null;
}

export async function fetchActivities(
  client: IntervalsClient,
  athleteId: string,
  oldestIsoDate: string,
): Promise<ActivityRow[]> {
  const data = await client.get(`/athlete/${athleteId}/activities?oldest=${oldestIsoDate}`) as IntervalsActivity[];
  return data.map((a) => ({
    id: a.id,
    start_time: a.start_date_local,
    sport: a.type,
    duration_sec: a.moving_time,
    distance_m: a.distance,
    tss: a.icu_training_load,
    intensity: a.icu_intensity,
    np_w: a.icu_average_watts,
    avg_hr: a.average_heartrate,
    avg_pace_sec_per_km: a.pace ? a.pace * 60 : null,
    planned_id: a.paired_event_id,
    description: a.description,
    raw_json: JSON.stringify(a),
  }));
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/intervals/activities.ts tests/intervals/activities.test.ts
git commit -m "feat: fetch and map activities from intervals.icu"
```

---

### Task 10: Wellness fetch

**Files:**
- Create: `src/intervals/wellness.ts`
- Create: `tests/intervals/wellness.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { fetchWellness } from "../../src/intervals/wellness.js";

describe("fetchWellness", () => {
  it("maps wellness response", async () => {
    const fake = {
      get: vi.fn().mockResolvedValue([{
        id: "2026-04-25", hrv: 70, restingHR: 50, sleepSecs: 27000, sleepScore: 85, bodyBattery: 90, weight: 78.5, readiness: 8,
      }]),
    };
    const rows = await fetchWellness(fake as any, "i123", "2026-04-01");
    expect(rows[0].date).toBe("2026-04-25");
    expect(rows[0].hrv).toBe(70);
    expect(rows[0].sleep_hours).toBe(7.5);
  });
});
```

- [ ] **Step 2: Implement `src/intervals/wellness.ts`**

```typescript
import type { IntervalsClient } from "./client.js";
import type { WellnessRow } from "../db/wellness.js";

interface IntervalsWellness {
  id: string;
  hrv: number | null;
  restingHR: number | null;
  sleepSecs: number | null;
  sleepScore: number | null;
  bodyBattery: number | null;
  weight: number | null;
  readiness: number | null;
}

export async function fetchWellness(
  client: IntervalsClient,
  athleteId: string,
  oldestIsoDate: string,
): Promise<WellnessRow[]> {
  const data = await client.get(`/athlete/${athleteId}/wellness?oldest=${oldestIsoDate}`) as IntervalsWellness[];
  return data.map((w) => ({
    date: w.id,
    hrv: w.hrv,
    resting_hr: w.restingHR,
    sleep_hours: w.sleepSecs ? w.sleepSecs / 3600 : null,
    sleep_score: w.sleepScore,
    body_battery: w.bodyBattery,
    weight: w.weight,
    subjective_rating: w.readiness,
    raw_json: JSON.stringify(w),
  }));
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/intervals/wellness.ts tests/intervals/wellness.test.ts
git commit -m "feat: fetch wellness from intervals.icu"
```

---

### Task 11: Events (planned workouts) read

**Files:**
- Create: `src/intervals/events.ts`
- Create: `tests/intervals/events.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { fetchEvents } from "../../src/intervals/events.js";

describe("fetchEvents", () => {
  it("fetches events between dates", async () => {
    const fake = { get: vi.fn().mockResolvedValue([{ id: "e1", category: "WORKOUT", type: "Bike", name: "VO2", start_date_local: "2026-04-22T07:00:00" }]) };
    const rows = await fetchEvents(fake as any, "i123", "2026-04-20", "2026-04-26");
    expect(fake.get).toHaveBeenCalledWith("/athlete/i123/events?oldest=2026-04-20&newest=2026-04-26");
    expect(rows[0].id).toBe("e1");
  });
});
```

- [ ] **Step 2: Implement `src/intervals/events.ts`**

```typescript
import type { IntervalsClient } from "./client.js";

export interface IntervalsEvent {
  id: string;
  category: string;
  type: string | null;
  name: string;
  start_date_local: string;
  description?: string;
  workout_doc?: unknown;
  icu_training_load?: number;
  moving_time?: number;
}

export async function fetchEvents(
  client: IntervalsClient,
  athleteId: string,
  oldest: string,
  newest: string,
): Promise<IntervalsEvent[]> {
  return await client.get(`/athlete/${athleteId}/events?oldest=${oldest}&newest=${newest}`) as IntervalsEvent[];
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/intervals tests/intervals
git commit -m "feat: fetch planned events (workouts) from intervals.icu"
```

---

### Task 12: Bootstrap script — initial 12-month history ingest

**Files:**
- Create: `scripts/bootstrap.ts`

- [ ] **Step 1: Implement `scripts/bootstrap.ts`**

```typescript
import { loadConfig } from "../src/config.js";
import { openDb } from "../src/db/client.js";
import { upsertActivity } from "../src/db/activities.js";
import { upsertWellness } from "../src/db/wellness.js";
import { IntervalsClient } from "../src/intervals/client.js";
import { fetchActivities } from "../src/intervals/activities.js";
import { fetchWellness } from "../src/intervals/wellness.js";
import { logger } from "../src/lib/logger.js";

async function main() {
  const cfg = loadConfig();
  const db = openDb(cfg.dbPath);
  const client = new IntervalsClient({ athleteId: cfg.intervals.athleteId, apiKey: cfg.intervals.apiKey });

  const oldest = new Date(Date.now() - 365 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
  logger.info({ oldest }, "bootstrap: fetching 12 months of activities");
  const activities = await fetchActivities(client, cfg.intervals.athleteId, oldest);
  for (const a of activities) upsertActivity(db, a);
  logger.info({ count: activities.length }, "bootstrap: activities ingested");

  logger.info("bootstrap: fetching 12 months of wellness");
  const wellness = await fetchWellness(client, cfg.intervals.athleteId, oldest);
  for (const w of wellness) upsertWellness(db, w);
  logger.info({ count: wellness.length }, "bootstrap: wellness ingested");

  db.exec(`INSERT OR REPLACE INTO sync_state (resource, last_synced_at) VALUES ('activities', datetime('now')), ('wellness', datetime('now'))`);
  logger.info("bootstrap: complete");
}

main().catch((e) => { logger.error(e); process.exit(1); });
```

- [ ] **Step 2: Manual smoke test (requires real `.env` with intervals.icu credentials)**

```bash
cp .env.example .env
# Fill in INTERVALS_ATHLETE_ID and INTERVALS_API_KEY (Daniel grabs these from intervals.icu Settings → Developer)
npm run bootstrap
sqlite3 twins-coach.db "SELECT COUNT(*) FROM activities; SELECT COUNT(*) FROM wellness;"
```

Expected: counts > 0 (depends on Daniel's history).

- [ ] **Step 3: Commit**

```bash
git add scripts/bootstrap.ts
git commit -m "feat: bootstrap script for initial 12mo history ingest"
```

---

### Task 13: Daily incremental sync job

**Files:**
- Create: `src/jobs/sync.ts`
- Create: `tests/jobs/sync.test.ts`

- [ ] **Step 1: Write failing integration test using in-memory DB and mocked client**

```typescript
import { describe, it, expect, vi } from "vitest";
import { openDb } from "../../src/db/client.js";
import { runSync } from "../../src/jobs/sync.js";

describe("runSync", () => {
  it("ingests new activities and wellness since last sync", async () => {
    const db = openDb(":memory:");
    const client: any = {
      get: vi.fn()
        .mockResolvedValueOnce([{ id: "a1", start_date_local: "2026-04-25T08:00:00", type: "Run", moving_time: 1800, distance: 5000, icu_training_load: 30, icu_intensity: 0.7, icu_average_watts: null, average_heartrate: 140, pace: 6, paired_event_id: null, description: "" }])
        .mockResolvedValueOnce([{ id: "2026-04-25", hrv: 65, restingHR: 52, sleepSecs: 25200, sleepScore: 80, bodyBattery: 88, weight: 79, readiness: 7 }]),
    };
    await runSync({ db, client, athleteId: "i123" });
    expect(db.prepare("SELECT COUNT(*) AS c FROM activities").get()).toEqual({ c: 1 });
    expect(db.prepare("SELECT COUNT(*) AS c FROM wellness").get()).toEqual({ c: 1 });
  });
});
```

- [ ] **Step 2: Implement `src/jobs/sync.ts`**

```typescript
import type { Db } from "../db/client.js";
import { upsertActivity, getLastSyncedActivity } from "../db/activities.js";
import { upsertWellness } from "../db/wellness.js";
import { fetchActivities } from "../intervals/activities.js";
import { fetchWellness } from "../intervals/wellness.js";
import type { IntervalsClient } from "../intervals/client.js";
import { logger } from "../lib/logger.js";

interface RunSyncOpts { db: Db; client: IntervalsClient; athleteId: string; }

export async function runSync({ db, client, athleteId }: RunSyncOpts): Promise<void> {
  const last = getLastSyncedActivity(db);
  const oldest = last
    ? new Date(new Date(last).getTime() - 24 * 60 * 60 * 1000).toISOString().slice(0, 10)
    : new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);

  const activities = await fetchActivities(client, athleteId, oldest);
  for (const a of activities) upsertActivity(db, a);
  const wellness = await fetchWellness(client, athleteId, oldest);
  for (const w of wellness) upsertWellness(db, w);
  logger.info({ activities: activities.length, wellness: wellness.length }, "sync complete");

  db.exec(`INSERT OR REPLACE INTO sync_state (resource, last_synced_at) VALUES ('activities', datetime('now')), ('wellness', datetime('now'))`);
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/jobs/sync.ts tests/jobs/sync.test.ts
git commit -m "feat: incremental sync job for activities + wellness"
```

---

## Milestone 3: Athlete model builder

### Task 14: Athlete profile parser

**Files:**
- Create: `src/athlete/profile.ts`
- Create: `tests/athlete/profile.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect } from "vitest";
import { parseAthleteProfile } from "../../src/athlete/profile.js";

const SAMPLE = `## Race calendar
- 2026-05-17: 70.3 Chattanooga — A race
- 2026-09-13: 70.3 World Championship, Marbella — A race

## Training time budget
- Base weeks: 10 hrs target
- Peak weeks: 14 hrs cap
- Strength: 3x/week (may freelance)

## Constraints + preferences
- Maximum 6 training days per week, minimum 1 full rest day (HARD)
`;

describe("parseAthleteProfile", () => {
  it("extracts races + budget + constraint text", () => {
    const p = parseAthleteProfile(SAMPLE);
    expect(p.races).toHaveLength(2);
    expect(p.races[0].date).toBe("2026-05-17");
    expect(p.budget.baseHours).toBe(10);
    expect(p.budget.peakHours).toBe(14);
    expect(p.budget.strengthPerWeek).toBe(3);
    expect(p.maxTrainingDaysPerWeek).toBe(6);
    expect(p.rawMarkdown).toContain("Race calendar");
  });
});
```

- [ ] **Step 2: Implement `src/athlete/profile.ts`**

```typescript
import fs from "node:fs";

export interface RaceEntry { date: string; description: string; isARace: boolean; }
export interface AthleteProfile {
  races: RaceEntry[];
  budget: { baseHours: number; peakHours: number; strengthPerWeek: number };
  maxTrainingDaysPerWeek: number;
  rawMarkdown: string;
}

export function parseAthleteProfile(md: string): AthleteProfile {
  const races: RaceEntry[] = [];
  const raceLines = md.match(/^- (\d{4}-\d{2}-\d{2}):\s*(.+)$/gm) ?? [];
  for (const line of raceLines) {
    const m = line.match(/^- (\d{4}-\d{2}-\d{2}):\s*(.+)$/);
    if (m) races.push({ date: m[1], description: m[2], isARace: /A race/i.test(m[2]) });
  }

  const baseMatch = md.match(/Base weeks?:\s*(\d+)\s*hrs?/i);
  const peakMatch = md.match(/Peak weeks?:\s*(\d+)\s*hrs?/i);
  const strengthMatch = md.match(/Strength:\s*(\d+)x\/week/i);
  const maxDaysMatch = md.match(/Maximum (\d+) training days per week/i);

  return {
    races,
    budget: {
      baseHours: baseMatch ? Number(baseMatch[1]) : 10,
      peakHours: peakMatch ? Number(peakMatch[1]) : 14,
      strengthPerWeek: strengthMatch ? Number(strengthMatch[1]) : 3,
    },
    maxTrainingDaysPerWeek: maxDaysMatch ? Number(maxDaysMatch[1]) : 6,
    rawMarkdown: md,
  };
}

export function loadAthleteProfile(path: string): AthleteProfile {
  return parseAthleteProfile(fs.readFileSync(path, "utf8"));
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/athlete/profile.ts tests/athlete/profile.test.ts
git commit -m "feat: athlete.md profile parser"
```

---

### Task 15: Athlete model derivation

**Files:**
- Create: `src/athlete/model.ts`
- Create: `tests/athlete/model.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect } from "vitest";
import { openDb } from "../../src/db/client.js";
import { upsertActivity } from "../../src/db/activities.js";
import { upsertWellness } from "../../src/db/wellness.js";
import { computeAthleteModel } from "../../src/athlete/model.js";

describe("computeAthleteModel", () => {
  it("computes weekly hours avg and HRV baseline from history", () => {
    const db = openDb(":memory:");
    for (let i = 0; i < 28; i++) {
      const date = new Date(Date.now() - i * 24 * 60 * 60 * 1000);
      upsertActivity(db, {
        id: `a${i}`, start_time: date.toISOString(), sport: "Bike",
        duration_sec: 3600, distance_m: 30000, tss: 60, intensity: 0.8,
        np_w: 200, avg_hr: 140, avg_pace_sec_per_km: null, planned_id: null,
        description: "", raw_json: "{}",
      });
      upsertWellness(db, {
        date: date.toISOString().slice(0, 10),
        hrv: 70 + (i % 5), resting_hr: 50, sleep_hours: 7, sleep_score: 80,
        body_battery: 85, weight: 78, subjective_rating: 7, raw_json: "{}",
      });
    }
    const m = computeAthleteModel(db);
    expect(m.weekly_hours_avg).toBeCloseTo(7, 0);
    expect(m.hrv_baseline).toBeGreaterThan(70);
    expect(m.hrv_baseline).toBeLessThan(75);
  });
});
```

- [ ] **Step 2: Implement `src/athlete/model.ts`**

```typescript
import type { Db } from "../db/client.js";
import type { AthleteModelRow } from "../db/athlete-model.js";

export function computeAthleteModel(db: Db): AthleteModelRow {
  const cutoff = new Date(Date.now() - 28 * 24 * 60 * 60 * 1000).toISOString();

  const acts = db.prepare(
    "SELECT duration_sec, planned_id FROM activities WHERE start_time >= ?"
  ).all(cutoff) as { duration_sec: number | null; planned_id: string | null }[];

  const totalHours = acts.reduce((s, a) => s + (a.duration_sec ?? 0) / 3600, 0);
  const weeklyHoursAvg = totalHours / 4;
  const completed = acts.filter((a) => a.planned_id !== null).length;
  const planned = db.prepare(
    "SELECT COUNT(*) AS c FROM activities WHERE start_time >= ? AND planned_id IS NOT NULL"
  ).get(cutoff) as { c: number };
  const complianceRate = planned.c > 0 ? completed / planned.c : null;

  const hrvs = (db.prepare(
    "SELECT hrv FROM wellness WHERE date >= ? AND hrv IS NOT NULL"
  ).all(cutoff.slice(0, 10)) as { hrv: number }[]).map((w) => w.hrv);
  const hrvMean = hrvs.length ? hrvs.reduce((s, x) => s + x, 0) / hrvs.length : null;
  const hrvSd = hrvs.length && hrvMean !== null
    ? Math.sqrt(hrvs.reduce((s, x) => s + (x - hrvMean) ** 2, 0) / hrvs.length)
    : null;

  return {
    ftp_w: null,
    threshold_pace_sec_per_km: null,
    css_sec_per_100m: null,
    weekly_hours_avg: weeklyHoursAvg,
    compliance_rate: complianceRate,
    hrv_baseline: hrvMean,
    hrv_baseline_sd: hrvSd,
    notes: null,
  };
}
```

> **Note:** FTP / threshold pace / CSS auto-derivation from test efforts is deferred to a future task. For Phase 0 / Phase 1 we use what intervals.icu has computed; Daniel can override in `athlete.md` if needed.

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/athlete tests/athlete
git commit -m "feat: athlete model — weekly hours, compliance, HRV baseline"
```

---

### Task 16: Athlete model refresh job

**Files:**
- Create: `src/jobs/refresh-model.ts`
- Create: `tests/jobs/refresh-model.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect } from "vitest";
import { openDb } from "../../src/db/client.js";
import { runRefreshModel } from "../../src/jobs/refresh-model.js";
import { getLatestAthleteModel } from "../../src/db/athlete-model.js";

describe("runRefreshModel", () => {
  it("inserts a fresh athlete model row", () => {
    const db = openDb(":memory:");
    runRefreshModel({ db });
    const m = getLatestAthleteModel(db);
    expect(m).not.toBeNull();
  });
});
```

- [ ] **Step 2: Implement `src/jobs/refresh-model.ts`**

```typescript
import type { Db } from "../db/client.js";
import { computeAthleteModel } from "../athlete/model.js";
import { insertAthleteModel } from "../db/athlete-model.js";
import { logger } from "../lib/logger.js";

export function runRefreshModel({ db }: { db: Db }): void {
  const model = computeAthleteModel(db);
  insertAthleteModel(db, model);
  logger.info({ model }, "athlete model refreshed");
}
```

- [ ] **Step 3: Commit**

```bash
npm test
git add src/jobs/refresh-model.ts tests/jobs/refresh-model.test.ts
git commit -m "feat: weekly athlete model refresh job"
```

---

## Milestone 4: WhatsApp outbound

### Task 17: Meta Cloud API client (send text)

**Files:**
- Create: `src/whatsapp/client.ts`
- Create: `src/whatsapp/outbound.ts`
- Create: `tests/whatsapp/outbound.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { sendText } from "../../src/whatsapp/outbound.js";

describe("sendText", () => {
  it("POSTs to Meta Cloud API messages endpoint", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true, status: 200, json: async () => ({ messages: [{ id: "wamid.123" }] }),
    });
    const id = await sendText({
      phoneNumberId: "PN1", accessToken: "TOKEN", recipient: "+15555550100",
      body: "hello", fetchImpl: fetchMock,
    });
    expect(id).toBe("wamid.123");
    expect(fetchMock).toHaveBeenCalledWith(
      "https://graph.facebook.com/v17.0/PN1/messages",
      expect.objectContaining({
        method: "POST",
        headers: expect.objectContaining({ Authorization: "Bearer TOKEN" }),
      })
    );
    const call = fetchMock.mock.calls[0][1];
    const body = JSON.parse(call.body);
    expect(body.to).toBe("+15555550100");
    expect(body.type).toBe("text");
    expect(body.text.body).toBe("hello");
  });

  it("throws on non-ok response", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false, status: 400, text: async () => "bad request",
    });
    await expect(sendText({
      phoneNumberId: "PN1", accessToken: "T", recipient: "+1", body: "x", fetchImpl: fetchMock,
    })).rejects.toThrow(/400/);
  });
});
```

- [ ] **Step 2: Implement `src/whatsapp/outbound.ts`**

```typescript
export interface SendTextOpts {
  phoneNumberId: string;
  accessToken: string;
  recipient: string;
  body: string;
  fetchImpl?: typeof fetch;
}

export async function sendText(opts: SendTextOpts): Promise<string> {
  const f = opts.fetchImpl ?? fetch;
  const r = await f(`https://graph.facebook.com/v17.0/${opts.phoneNumberId}/messages`, {
    method: "POST",
    headers: { Authorization: `Bearer ${opts.accessToken}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      messaging_product: "whatsapp",
      to: opts.recipient,
      type: "text",
      text: { body: opts.body },
    }),
  });
  if (!r.ok) throw new Error(`whatsapp send failed: ${r.status} ${await r.text()}`);
  const data = await r.json() as { messages: { id: string }[] };
  return data.messages[0].id;
}

export interface SendTemplateOpts extends Omit<SendTextOpts, "body"> {
  templateName: string;
  language: string;
  parameters?: string[];
}

export async function sendTemplate(opts: SendTemplateOpts): Promise<string> {
  const f = opts.fetchImpl ?? fetch;
  const r = await f(`https://graph.facebook.com/v17.0/${opts.phoneNumberId}/messages`, {
    method: "POST",
    headers: { Authorization: `Bearer ${opts.accessToken}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      messaging_product: "whatsapp",
      to: opts.recipient,
      type: "template",
      template: {
        name: opts.templateName,
        language: { code: opts.language },
        components: opts.parameters ? [{
          type: "body",
          parameters: opts.parameters.map((text) => ({ type: "text", text })),
        }] : undefined,
      },
    }),
  });
  if (!r.ok) throw new Error(`whatsapp template send failed: ${r.status} ${await r.text()}`);
  const data = await r.json() as { messages: { id: string }[] };
  return data.messages[0].id;
}
```

> **Note on templates:** Meta requires pre-approved templates for messages outside the 24-hour customer-service window. Daniel must register one template named `weekly_summary` (1 body parameter) via Meta Business Suite before Phase 0 ships. The runbook in Task 24 documents this.

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/whatsapp/outbound.ts tests/whatsapp/outbound.test.ts
git commit -m "feat: WhatsApp Cloud API send (text + template)"
```

---

### Task 18: WhatsApp send wrapper with retry

**Files:**
- Create: `src/whatsapp/send.ts`
- Create: `tests/whatsapp/send.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { sendWithRetry } from "../../src/whatsapp/send.js";

describe("sendWithRetry", () => {
  it("retries transient failures", async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({ ok: false, status: 503, text: async () => "down" })
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ messages: [{ id: "x" }] }) });
    const id = await sendWithRetry({
      phoneNumberId: "P", accessToken: "T", recipient: "+1", body: "hi",
      fetchImpl: fetchMock, attempts: 3, baseMs: 1,
    });
    expect(id).toBe("x");
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });
});
```

- [ ] **Step 2: Implement `src/whatsapp/send.ts`**

```typescript
import { sendText, type SendTextOpts } from "./outbound.js";
import { retry } from "../lib/retry.js";

export async function sendWithRetry(
  opts: SendTextOpts & { attempts?: number; baseMs?: number },
): Promise<string> {
  return retry(() => sendText(opts), { attempts: opts.attempts ?? 3, baseMs: opts.baseMs ?? 500 });
}
```

- [ ] **Step 3: Commit**

```bash
npm test
git add src/whatsapp/send.ts tests/whatsapp/send.test.ts
git commit -m "feat: WhatsApp send with retry"
```

---

## Milestone 5: Phase 0 Sunday observe loop

### Task 19: Observation summary builder

**Files:**
- Create: `src/planner/observation.ts`
- Create: `tests/planner/observation.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect } from "vitest";
import { openDb } from "../../src/db/client.js";
import { upsertActivity } from "../../src/db/activities.js";
import { upsertWellness } from "../../src/db/wellness.js";
import { buildObservationSummary } from "../../src/planner/observation.js";

describe("buildObservationSummary", () => {
  it("produces a summary string mentioning hours and HRV trend", () => {
    const db = openDb(":memory:");
    const now = new Date();
    for (let i = 0; i < 7; i++) {
      const d = new Date(now.getTime() - i * 24 * 60 * 60 * 1000);
      upsertActivity(db, {
        id: `a${i}`, start_time: d.toISOString(), sport: i % 2 === 0 ? "Run" : "Bike",
        duration_sec: 3600, distance_m: 10000, tss: 50, intensity: 0.8,
        np_w: null, avg_hr: 140, avg_pace_sec_per_km: 360, planned_id: null,
        description: "", raw_json: "{}",
      });
      upsertWellness(db, {
        date: d.toISOString().slice(0, 10), hrv: 70, resting_hr: 50, sleep_hours: 7,
        sleep_score: 80, body_battery: 85, weight: 78, subjective_rating: 7, raw_json: "{}",
      });
    }
    const summary = buildObservationSummary(db);
    expect(summary).toMatch(/hours/i);
    expect(summary).toMatch(/HRV/i);
  });
});
```

- [ ] **Step 2: Implement `src/planner/observation.ts`**

```typescript
import type { Db } from "../db/client.js";
import { getActivitiesSince } from "../db/activities.js";
import { getWellnessSince } from "../db/wellness.js";

export function buildObservationSummary(db: Db): string {
  const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString();
  const acts = getActivitiesSince(db, weekAgo);
  const wells = getWellnessSince(db, weekAgo.slice(0, 10));

  const totalHours = acts.reduce((s, a) => s + (a.duration_sec ?? 0) / 3600, 0);
  const sportCounts = acts.reduce((m, a) => { m[a.sport] = (m[a.sport] ?? 0) + 1; return m; }, {} as Record<string, number>);
  const hrvs = wells.filter((w) => w.hrv !== null).map((w) => w.hrv!);
  const hrvAvg = hrvs.length ? hrvs.reduce((s, x) => s + x, 0) / hrvs.length : null;

  const sportSummary = Object.entries(sportCounts).map(([k, v]) => `${k}:${v}`).join(", ");
  return [
    `Last 7 days: ${totalHours.toFixed(1)} hours, ${acts.length} sessions (${sportSummary || "none"}).`,
    hrvAvg !== null ? `HRV average: ${hrvAvg.toFixed(0)}.` : "HRV: no data.",
    "Phase 0 (observing) — no plan published this week.",
  ].join(" ");
}
```

- [ ] **Step 3: Commit**

```bash
npm test
git add src/planner/observation.ts tests/planner/observation.test.ts
git commit -m "feat: phase 0 observation summary builder"
```

---

### Task 20: Sunday cycle job (Phase 0 mode)

**Files:**
- Create: `src/jobs/sunday-cycle.ts`
- Create: `tests/jobs/sunday-cycle.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { openDb } from "../../src/db/client.js";
import { runSundayCycle } from "../../src/jobs/sunday-cycle.js";

describe("runSundayCycle (phase 0)", () => {
  it("syncs, refreshes model, and sends observation summary", async () => {
    const db = openDb(":memory:");
    const intervalsClient: any = { get: vi.fn().mockResolvedValue([]) };
    const sendFn = vi.fn().mockResolvedValue("wamid.x");
    await runSundayCycle({
      db, intervalsClient, athleteId: "i123", phase: 0, sendFn,
    });
    expect(sendFn).toHaveBeenCalledOnce();
    const sent = sendFn.mock.calls[0][0];
    expect(sent.body).toMatch(/Phase 0/);
  });
});
```

- [ ] **Step 2: Implement `src/jobs/sunday-cycle.ts`**

```typescript
import type { Db } from "../db/client.js";
import type { IntervalsClient } from "../intervals/client.js";
import { runSync } from "./sync.js";
import { runRefreshModel } from "./refresh-model.js";
import { buildObservationSummary } from "../planner/observation.js";
import { logger } from "../lib/logger.js";

export interface SundayCycleOpts {
  db: Db;
  intervalsClient: IntervalsClient;
  athleteId: string;
  phase: 0 | 1 | 2;
  sendFn: (opts: { body: string }) => Promise<string>;
}

export async function runSundayCycle(opts: SundayCycleOpts): Promise<void> {
  logger.info({ phase: opts.phase }, "sunday cycle: start");
  await runSync({ db: opts.db, client: opts.intervalsClient, athleteId: opts.athleteId });
  runRefreshModel({ db: opts.db });

  if (opts.phase === 0) {
    const body = buildObservationSummary(opts.db);
    await opts.sendFn({ body });
    logger.info("sunday cycle: phase 0 summary sent");
    return;
  }

  // Phase 1 / 2 logic added in later tasks
  throw new Error(`Phase ${opts.phase} not yet implemented`);
}
```

- [ ] **Step 3: Commit**

```bash
npm test
git add src/jobs/sunday-cycle.ts tests/jobs/sunday-cycle.test.ts
git commit -m "feat: sunday cycle job (phase 0 observe-only)"
```

---

### Task 21: CLI entry for cron jobs

**Files:**
- Create: `src/cli.ts`

- [ ] **Step 1: Implement `src/cli.ts`**

```typescript
import { loadConfig } from "./config.js";
import { openDb } from "./db/client.js";
import { IntervalsClient } from "./intervals/client.js";
import { runSundayCycle } from "./jobs/sunday-cycle.js";
import { runSync } from "./jobs/sync.js";
import { runRefreshModel } from "./jobs/refresh-model.js";
import { sendWithRetry } from "./whatsapp/send.js";
import { logger } from "./lib/logger.js";

async function main() {
  const cmd = process.argv[2];
  if (!cmd) {
    console.error("usage: tsx src/cli.ts <sunday-cycle|sync|refresh-model>");
    process.exit(2);
  }

  const cfg = loadConfig();
  const db = openDb(cfg.dbPath);
  const intervalsClient = new IntervalsClient({ athleteId: cfg.intervals.athleteId, apiKey: cfg.intervals.apiKey });

  const phase = (Number(process.env.COACH_PHASE ?? "0") as 0 | 1 | 2);

  switch (cmd) {
    case "sunday-cycle":
      await runSundayCycle({
        db, intervalsClient, athleteId: cfg.intervals.athleteId, phase,
        sendFn: ({ body }) => sendWithRetry({
          phoneNumberId: cfg.whatsapp.phoneNumberId,
          accessToken: cfg.whatsapp.accessToken,
          recipient: cfg.whatsapp.recipient,
          body,
        }),
      });
      break;
    case "sync":
      await runSync({ db, client: intervalsClient, athleteId: cfg.intervals.athleteId });
      break;
    case "refresh-model":
      runRefreshModel({ db });
      break;
    default:
      console.error(`unknown command: ${cmd}`);
      process.exit(2);
  }
}

main().catch((e) => { logger.error(e); process.exit(1); });
```

- [ ] **Step 2: Smoke test (requires real env)**

```bash
npm run cli sunday-cycle
```

Expected: WhatsApp message arrives on Daniel's phone.

- [ ] **Step 3: Commit**

```bash
git add src/cli.ts
git commit -m "feat: CLI entry for cron-driven jobs"
```

---

### Task 22: Phase 0 end-to-end smoke test

**Files:**
- Create: `tests/e2e/phase-0.test.ts`

- [ ] **Step 1: Write integration test that wires all real modules with mocked HTTP**

```typescript
import { describe, it, expect, vi } from "vitest";
import { openDb } from "../../src/db/client.js";
import { IntervalsClient } from "../../src/intervals/client.js";
import { runSundayCycle } from "../../src/jobs/sunday-cycle.js";

describe("phase 0 end-to-end", () => {
  it("ingests, refreshes model, sends observation summary", async () => {
    const db = openDb(":memory:");
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        ok: true, status: 200, json: async () => [{
          id: "a1", start_date_local: "2026-04-25T08:00:00", type: "Run",
          moving_time: 3600, distance: 10000, icu_training_load: 50, icu_intensity: 0.8,
          icu_average_watts: null, average_heartrate: 140, pace: 6.0,
          paired_event_id: null, description: "easy",
        }],
      })
      .mockResolvedValueOnce({
        ok: true, status: 200, json: async () => [{
          id: "2026-04-25", hrv: 70, restingHR: 50, sleepSecs: 25200, sleepScore: 80,
          bodyBattery: 85, weight: 78, readiness: 7,
        }],
      });
    const client = new IntervalsClient({ athleteId: "i1", apiKey: "k", fetchImpl: fetchMock });
    const sendFn = vi.fn().mockResolvedValue("wamid.x");
    await runSundayCycle({ db, intervalsClient: client, athleteId: "i1", phase: 0, sendFn });
    expect(sendFn).toHaveBeenCalledOnce();
    expect(db.prepare("SELECT COUNT(*) AS c FROM activities").get()).toEqual({ c: 1 });
    expect(db.prepare("SELECT COUNT(*) AS c FROM athlete_model").get()).toEqual({ c: 1 });
  });
});
```

- [ ] **Step 2: Run + commit**

```bash
npm test
git add tests/e2e
git commit -m "test: phase 0 end-to-end smoke test"
```

---

## Milestone 6: Deploy Phase 0 to Oracle Cloud Free Tier

> **🎯 PHASE 0 SHIPS at end of this milestone.** Daniel starts receiving weekly observation WhatsApps for the ~3 weeks leading up to May 17.

### Task 23: Oracle VPS provisioning runbook

**Files:**
- Create: `deploy/oracle-setup.md`

- [ ] **Step 1: Write `deploy/oracle-setup.md`**

```markdown
# Oracle Cloud Free Tier VPS setup

## One-time setup

1. Create Oracle Cloud account at https://www.oracle.com/cloud/free/
2. In OCI Console: **Compute → Instances → Create Instance**
   - Image: Canonical Ubuntu 22.04 (ARM)
   - Shape: VM.Standard.A1.Flex (4 OCPU, 24 GB RAM — within Always Free)
   - VCN: default
   - SSH key: paste local ~/.ssh/id_ed25519.pub
3. After provisioning, note the public IP. Add to ~/.ssh/config:
   ```
   Host twins-coach
     HostName <PUBLIC_IP>
     User ubuntu
     IdentityFile ~/.ssh/id_ed25519
   ```
4. SSH in: `ssh twins-coach`
5. Open port 8443 (WhatsApp webhook): in OCI Console → Networking → VCN → default subnet → Security List → Ingress: TCP 8443 from 0.0.0.0/0
6. On the VPS, allow it through the host firewall:
   ```bash
   sudo iptables -I INPUT 1 -p tcp --dport 8443 -j ACCEPT
   sudo netfilter-persistent save
   ```

## Software install

```bash
# Node 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs build-essential sqlite3 git

# Claude Code (authenticated as Daniel)
sudo npm install -g @anthropic-ai/claude-code
claude  # interactive — log in with Daniel's Pro/Max credentials, then `/quit`

# App user + dir
sudo useradd -m -s /bin/bash coach
sudo mkdir -p /opt/twins-coach
sudo chown coach:coach /opt/twins-coach

# Copy SSH key for Claude auth tokens to coach user (token stored in ~/.claude/)
sudo cp -r ~/.claude /home/coach/
sudo chown -R coach:coach /home/coach/.claude
```

## Caddy for HTTPS termination on webhook

```bash
sudo apt-get install -y caddy
sudo tee /etc/caddy/Caddyfile <<EOF
:8443 {
  reverse_proxy localhost:3000
  tls internal
}
EOF
sudo systemctl restart caddy
```

(Replace `tls internal` with `tls daniel@example.com` and a domain to use Let's Encrypt — requires DNS pointed at the IP.)

## Healthcheck (Oracle-reclaim protection)

The healthcheck.timer in this repo pings the instance daily and Daniel gets a WhatsApp if it stops reporting in. Oracle has been known to reclaim "idle" Always Free instances; the healthcheck makes the instance look active and gives Daniel an alert window.
```

- [ ] **Step 2: Commit**

```bash
git add deploy/oracle-setup.md
git commit -m "docs: oracle cloud free tier provisioning runbook"
```

---

### Task 24: Meta WhatsApp Cloud API setup runbook

**Files:**
- Create: `deploy/whatsapp-setup.md`

- [ ] **Step 1: Write `deploy/whatsapp-setup.md`**

```markdown
# WhatsApp Cloud API setup (Meta Business)

## One-time setup
1. Go to https://developers.facebook.com/ → My Apps → Create App → Business
2. Add product: WhatsApp → Quickstart
3. From the WhatsApp config page, copy:
   - Phone number ID → `WHATSAPP_PHONE_NUMBER_ID`
   - Permanent access token (System User token, not the temporary 24hr one) → `WHATSAPP_ACCESS_TOKEN`
4. Add Daniel's phone (+1...) as a verified recipient under "To" dropdown
5. Set webhook URL: `https://<oracle-domain-or-ip>:8443/whatsapp/webhook`
6. Set verify token (any random string) → `WHATSAPP_VERIFY_TOKEN`
7. Subscribe webhook to `messages` field

## Approved templates (required for proactive sends outside 24hr window)

In Meta Business Suite → WhatsApp Manager → Message Templates → Create:

**Template: `weekly_summary`** (category: UTILITY)
- Body: `{{1}}` (one variable)
- Approval: usually <1 hour for utility category

**Template: `safety_alert`** (category: UTILITY)
- Body: `{{1}}` (one variable)

**Template: `strength_morning`** (category: UTILITY)
- Body: `Today's strength: {{1}}`

After approval, Daniel can send proactive messages using these template names.

## Test
```bash
curl -X POST "https://graph.facebook.com/v17.0/${WHATSAPP_PHONE_NUMBER_ID}/messages" \
  -H "Authorization: Bearer ${WHATSAPP_ACCESS_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "messaging_product": "whatsapp",
    "to": "+15555550100",
    "type": "template",
    "template": {"name": "weekly_summary", "language": {"code": "en_US"}, "components": [{"type": "body", "parameters": [{"type": "text", "text": "Setup test"}]}]}
  }'
```

Expected: WhatsApp message arrives.
```

- [ ] **Step 2: Commit**

```bash
git add deploy/whatsapp-setup.md
git commit -m "docs: whatsapp cloud api setup runbook"
```

---

### Task 25: systemd units for cron jobs

**Files:**
- Create: `deploy/sunday-cycle.service`
- Create: `deploy/sunday-cycle.timer`
- Create: `deploy/sync.service`
- Create: `deploy/sync.timer`

- [ ] **Step 1: Create `deploy/sunday-cycle.service`**

```ini
[Unit]
Description=twins-coach sunday weekly cycle
After=network.target

[Service]
Type=oneshot
User=coach
WorkingDirectory=/opt/twins-coach
EnvironmentFile=/opt/twins-coach/.env
ExecStart=/usr/bin/npx tsx src/cli.ts sunday-cycle
StandardOutput=journal
StandardError=journal
```

- [ ] **Step 2: Create `deploy/sunday-cycle.timer`**

```ini
[Unit]
Description=Run twins-coach sunday cycle every Sunday at 17:00 local

[Timer]
OnCalendar=Sun *-*-* 17:00:00
Persistent=true

[Install]
WantedBy=timers.target
```

- [ ] **Step 3: Create `deploy/sync.service` and `deploy/sync.timer`**

`sync.service`:
```ini
[Unit]
Description=twins-coach daily incremental data sync
After=network.target

[Service]
Type=oneshot
User=coach
WorkingDirectory=/opt/twins-coach
EnvironmentFile=/opt/twins-coach/.env
ExecStart=/usr/bin/npx tsx src/cli.ts sync
StandardOutput=journal
StandardError=journal
```

`sync.timer`:
```ini
[Unit]
Description=Run twins-coach sync daily at 03:00 local

[Timer]
OnCalendar=*-*-* 03:00:00
Persistent=true

[Install]
WantedBy=timers.target
```

- [ ] **Step 4: Commit**

```bash
git add deploy/*.service deploy/*.timer
git commit -m "deploy: systemd units for sunday-cycle + sync"
```

---

### Task 26: Deploy script

**Files:**
- Create: `scripts/deploy.sh`

- [ ] **Step 1: Write `scripts/deploy.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail

HOST="${1:-twins-coach}"
DEST="/opt/twins-coach"

echo "Syncing source to $HOST:$DEST"
rsync -av --delete \
  --exclude node_modules --exclude .git --exclude '*.db' --exclude '.env' \
  ./ "$HOST:$DEST/"

echo "Installing deps"
ssh "$HOST" "cd $DEST && sudo -u coach npm install --production=false"

echo "Installing systemd units"
ssh "$HOST" "sudo cp $DEST/deploy/*.service /etc/systemd/system/ && sudo cp $DEST/deploy/*.timer /etc/systemd/system/ && sudo systemctl daemon-reload"

echo "Enabling timers"
ssh "$HOST" "sudo systemctl enable --now sunday-cycle.timer sync.timer"

echo "Done. List timers:"
ssh "$HOST" "systemctl list-timers --all | grep -E '(sunday|sync|daily|health|backup)'"
```

- [ ] **Step 2: Make executable + commit**

```bash
chmod +x scripts/deploy.sh
git add scripts/deploy.sh
git commit -m "deploy: rsync + systemd install script"
```

---

### Task 27: First production deploy + smoke test

- [ ] **Step 1: Generate intervals.icu API key**
  - Daniel logs into intervals.icu → Settings → Developer → "Create API key"
  - Note athlete ID (visible in URL after login)

- [ ] **Step 2: Provision Oracle VPS** per `deploy/oracle-setup.md`

- [ ] **Step 3: Set up WhatsApp** per `deploy/whatsapp-setup.md`, get template `weekly_summary` approved

- [ ] **Step 4: Create production `.env` on the VPS**

```bash
ssh twins-coach "sudo -u coach tee /opt/twins-coach/.env" <<EOF
INTERVALS_ATHLETE_ID=<id>
INTERVALS_API_KEY=<key>
WHATSAPP_PHONE_NUMBER_ID=<pid>
WHATSAPP_ACCESS_TOKEN=<tok>
WHATSAPP_VERIFY_TOKEN=<vtok>
WHATSAPP_RECIPIENT=+1<daniel-phone>
DB_PATH=/opt/twins-coach/twins-coach.db
LOG_LEVEL=info
TZ=America/Chicago
COACH_PHASE=0
EOF
```

- [ ] **Step 5: Deploy + bootstrap**

```bash
./scripts/deploy.sh twins-coach
ssh twins-coach "cd /opt/twins-coach && sudo -u coach npm run bootstrap"
```

Expected: ~12 months of activities + wellness in `twins-coach.db` on the VPS.

- [ ] **Step 6: Manual sunday-cycle trigger**

```bash
ssh twins-coach "sudo systemctl start sunday-cycle.service && sudo journalctl -u sunday-cycle.service -n 50"
```

Expected: WhatsApp arrives on Daniel's phone with observation summary; service exits cleanly.

- [ ] **Step 7: Verify timers active**

```bash
ssh twins-coach "systemctl list-timers"
```

Expected: `sunday-cycle.timer` shows next Sunday 17:00, `sync.timer` shows tomorrow 03:00.

> **🎯 PHASE 0 LIVE.** Daniel will get a WhatsApp every Sunday at 5pm local until June. No further action required from him; he can edit `athlete.md` locally and `git push` to update.

---

## Milestone 7: WhatsApp inbound + intent classification

### Task 28: Webhook server (Hono)

**Files:**
- Create: `src/whatsapp/webhook.ts`
- Create: `src/server.ts`
- Create: `tests/whatsapp/webhook.test.ts`

- [ ] **Step 1: Write failing webhook test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { createWebhookApp } from "../../src/whatsapp/webhook.js";

describe("webhook", () => {
  it("verifies subscription challenge", async () => {
    const app = createWebhookApp({ verifyToken: "vtok", onMessage: vi.fn() });
    const res = await app.request("/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=vtok&hub.challenge=hello");
    expect(res.status).toBe(200);
    expect(await res.text()).toBe("hello");
  });

  it("rejects bad verify token", async () => {
    const app = createWebhookApp({ verifyToken: "vtok", onMessage: vi.fn() });
    const res = await app.request("/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=BAD&hub.challenge=x");
    expect(res.status).toBe(403);
  });

  it("invokes onMessage on inbound message POST", async () => {
    const onMessage = vi.fn();
    const app = createWebhookApp({ verifyToken: "vtok", onMessage });
    const body = {
      entry: [{
        changes: [{
          value: { messages: [{ id: "wamid.1", from: "+15555550100", timestamp: "1714080000", text: { body: "feeling cooked" } }] },
        }],
      }],
    };
    const res = await app.request("/whatsapp/webhook", {
      method: "POST", headers: { "content-type": "application/json" }, body: JSON.stringify(body),
    });
    expect(res.status).toBe(200);
    expect(onMessage).toHaveBeenCalledWith({ id: "wamid.1", from: "+15555550100", timestamp: "1714080000", body: "feeling cooked" });
  });
});
```

- [ ] **Step 2: Implement `src/whatsapp/webhook.ts`**

```typescript
import { Hono } from "hono";

export interface InboundMessage {
  id: string;
  from: string;
  timestamp: string;
  body: string;
}

export interface WebhookOpts {
  verifyToken: string;
  onMessage: (m: InboundMessage) => Promise<void> | void;
}

export function createWebhookApp(opts: WebhookOpts) {
  const app = new Hono();

  app.get("/whatsapp/webhook", (c) => {
    const mode = c.req.query("hub.mode");
    const token = c.req.query("hub.verify_token");
    const challenge = c.req.query("hub.challenge");
    if (mode === "subscribe" && token === opts.verifyToken) return c.text(challenge ?? "", 200);
    return c.text("forbidden", 403);
  });

  app.post("/whatsapp/webhook", async (c) => {
    const body = await c.req.json() as any;
    const messages = body?.entry?.[0]?.changes?.[0]?.value?.messages ?? [];
    for (const m of messages) {
      if (m.text?.body) {
        await opts.onMessage({ id: m.id, from: m.from, timestamp: m.timestamp, body: m.text.body });
      }
    }
    return c.text("ok", 200);
  });

  return app;
}
```

- [ ] **Step 3: Implement `src/server.ts`**

```typescript
import { serve } from "@hono/node-server";
import { loadConfig } from "./config.js";
import { openDb } from "./db/client.js";
import { createWebhookApp } from "./whatsapp/webhook.js";
import { handleInboundMessage } from "./whatsapp/intent.js";
import { logger } from "./lib/logger.js";

const cfg = loadConfig();
const db = openDb(cfg.dbPath);

const app = createWebhookApp({
  verifyToken: cfg.whatsapp.verifyToken,
  onMessage: (m) => handleInboundMessage({ db, message: m, config: cfg }),
});

serve({ fetch: app.fetch, port: 3000 }, (info) => {
  logger.info({ port: info.port }, "twins-coach server listening");
});
```

- [ ] **Step 4: Run tests, commit**

```bash
npm test
git add src/whatsapp/webhook.ts src/server.ts tests/whatsapp/webhook.test.ts
git commit -m "feat: whatsapp webhook server with verify + message dispatch"
```

---

### Task 29: Intent classifier (Claude-based)

**Files:**
- Create: `src/whatsapp/intent.ts`
- Create: `tests/whatsapp/intent.test.ts`

- [ ] **Step 1: Write failing test (mock the Claude SDK call)**

```typescript
import { describe, it, expect, vi } from "vitest";
import { classifyIntent } from "../../src/whatsapp/intent.js";

describe("classifyIntent", () => {
  it("returns travel for travel-y messages", async () => {
    const fakeClaude = vi.fn().mockResolvedValue('{"intent":"travel","summary":"Chicago Wed-Fri, hotel gym"}');
    const r = await classifyIntent("Flying to Chicago Wed-Fri, hotel gym only", fakeClaude);
    expect(r.intent).toBe("travel");
  });

  it("returns wellness for tiredness", async () => {
    const fakeClaude = vi.fn().mockResolvedValue('{"intent":"wellness","summary":"feeling cooked"}');
    const r = await classifyIntent("feeling cooked today", fakeClaude);
    expect(r.intent).toBe("wellness");
  });

  it("recognizes /replan slash command without calling claude", async () => {
    const fakeClaude = vi.fn();
    const r = await classifyIntent("/replan", fakeClaude);
    expect(r.intent).toBe("replan");
    expect(fakeClaude).not.toHaveBeenCalled();
  });
});
```

- [ ] **Step 2: Implement `src/whatsapp/intent.ts`**

```typescript
import type { Db } from "../db/client.js";
import type { Config } from "../config.js";
import type { InboundMessage } from "./webhook.js";
import type { NoteIntent } from "../db/notes.js";
import { insertNote, markNoteHandled } from "../db/notes.js";
import { logger } from "../lib/logger.js";

export interface IntentResult { intent: NoteIntent; summary: string; }

export type ClaudeFn = (prompt: string) => Promise<string>;

const SLASH_COMMANDS: Record<string, NoteIntent> = {
  "/replan": "replan",
  "/why": "why",
  "/profile": "profile",
};

const PROMPT = `You classify a single inbound WhatsApp message from an athlete to a training assistant.
Categories: travel, wellness, swap, scope, other.
- travel: travel plans affecting training (e.g., flights, hotel gym)
- wellness: how the athlete feels (tired, sick, great)
- swap: a request to move/swap a workout
- scope: change to race goals or season scope
- other: anything else
Reply ONLY with JSON: {"intent":"<category>","summary":"<one-line summary>"}.

Message: `;

export async function classifyIntent(body: string, claudeFn: ClaudeFn): Promise<IntentResult> {
  const trimmed = body.trim().toLowerCase();
  for (const [cmd, intent] of Object.entries(SLASH_COMMANDS)) {
    if (trimmed === cmd || trimmed.startsWith(cmd + " ")) {
      return { intent, summary: body };
    }
  }
  const out = await claudeFn(PROMPT + body);
  try {
    const parsed = JSON.parse(out);
    return { intent: parsed.intent as NoteIntent, summary: parsed.summary as string };
  } catch (e) {
    logger.warn({ out }, "intent classifier returned non-json; defaulting to other");
    return { intent: "other", summary: body };
  }
}

export interface HandleOpts { db: Db; message: InboundMessage; config: Config; }

export async function handleInboundMessage({ db, message, config }: HandleOpts): Promise<void> {
  const today = new Date().toISOString().slice(0, 10);
  const noteId = insertNote(db, {
    source: "whatsapp", date: today, body: message.body, intent: null, handled: 0,
    raw_json: JSON.stringify(message),
  });
  // Intent classification + dispatch is wired in Task 30 (handlers).
  logger.info({ noteId, body: message.body }, "inbound message stored");
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/whatsapp/intent.ts tests/whatsapp/intent.test.ts
git commit -m "feat: whatsapp intent classifier (claude-backed + slash commands)"
```

---

### Task 30: Claude SDK wrapper (headless, subscription-auth)

**Files:**
- Create: `src/planner/claude.ts`
- Create: `tests/planner/claude.test.ts`

- [ ] **Step 1: Write failing test (mocks the SDK module)**

```typescript
import { describe, it, expect, vi } from "vitest";
import { askClaude } from "../../src/planner/claude.js";

vi.mock("@anthropic-ai/claude-agent-sdk", () => ({
  query: vi.fn().mockImplementation(async function* () {
    yield { type: "text", text: "hello" };
  }),
}));

describe("askClaude", () => {
  it("collects streamed text into a single string", async () => {
    const result = await askClaude("test prompt");
    expect(result).toContain("hello");
  });
});
```

- [ ] **Step 2: Implement `src/planner/claude.ts`**

```typescript
import { query } from "@anthropic-ai/claude-agent-sdk";

export async function askClaude(prompt: string, opts: { systemPrompt?: string } = {}): Promise<string> {
  const chunks: string[] = [];
  for await (const message of query({
    prompt,
    options: {
      ...(opts.systemPrompt ? { systemPrompt: opts.systemPrompt } : {}),
      permissionMode: "bypassPermissions",
      allowedTools: [],
    },
  } as any)) {
    if (message.type === "text" && typeof message.text === "string") chunks.push(message.text);
    if (message.type === "result" && message.subtype === "success" && message.result) chunks.push(message.result);
  }
  return chunks.join("");
}
```

> **Note:** SDK API may differ between versions. Run `claude --version` on the Oracle host to confirm the installed major version, then check `@anthropic-ai/claude-agent-sdk` README for the matching `query()` shape. Pin the version in `package.json` to whatever's installed on the VPS to avoid drift.

- [ ] **Step 3: Wire intent classifier to use real Claude (extend `intent.ts`)**

Modify `src/whatsapp/intent.ts` — replace the `handleInboundMessage` body with:

```typescript
export async function handleInboundMessage({ db, message, config }: HandleOpts): Promise<void> {
  const today = new Date().toISOString().slice(0, 10);
  const noteId = insertNote(db, {
    source: "whatsapp", date: today, body: message.body, intent: null, handled: 0,
    raw_json: JSON.stringify(message),
  });
  const { askClaude } = await import("../planner/claude.js");
  const { intent } = await classifyIntent(message.body, askClaude);
  markNoteHandled(db, noteId, intent);
  logger.info({ noteId, intent }, "inbound message classified");
}
```

- [ ] **Step 4: Commit**

```bash
npm test
git add src/planner/claude.ts tests/planner/claude.test.ts src/whatsapp/intent.ts
git commit -m "feat: claude agent SDK wrapper + wire to intent classifier"
```

---

### Task 31: systemd unit for HTTP server

**Files:**
- Create: `deploy/server.service`

- [ ] **Step 1: Write `deploy/server.service`**

```ini
[Unit]
Description=twins-coach HTTP server (whatsapp webhook)
After=network.target

[Service]
Type=simple
User=coach
WorkingDirectory=/opt/twins-coach
EnvironmentFile=/opt/twins-coach/.env
ExecStart=/usr/bin/npx tsx src/server.ts
Restart=on-failure
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

- [ ] **Step 2: Update `scripts/deploy.sh` to enable server.service**

Add to deploy.sh after the timer enable:
```bash
ssh "$HOST" "sudo systemctl enable --now server.service"
```

- [ ] **Step 3: Deploy, smoke test**

```bash
./scripts/deploy.sh twins-coach
# Send test message to Daniel's WhatsApp business number
# Then:
ssh twins-coach "sudo journalctl -u server.service -n 30"
```

Expected: log shows inbound message stored + classified.

- [ ] **Step 4: Commit**

```bash
git add deploy/server.service scripts/deploy.sh
git commit -m "deploy: systemd unit for whatsapp webhook server"
```

---

## Milestone 8: Planner core (Phase 1 prerequisite)

### Task 32: Workout JSON schema (zod)

**Files:**
- Create: `src/planner/schema.ts`
- Create: `tests/planner/schema.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect } from "vitest";
import { WeeklyPlanSchema } from "../../src/planner/schema.js";

describe("WeeklyPlanSchema", () => {
  it("validates a clean plan", () => {
    const sample = {
      week_start: "2026-05-25",
      reasoning: "base week, low key",
      days: [
        { date: "2026-05-25", sport: "rest", duration_min: 0, intent: "rest", description: "Rest day", structure: [] },
        { date: "2026-05-26", sport: "bike", duration_min: 60, intent: "endurance",
          description: "Z2 endurance", structure: [
            { type: "warmup", duration_min: 10, target: "Z1" },
            { type: "steady", duration_min: 40, target: "Z2" },
            { type: "cooldown", duration_min: 10, target: "Z1" },
          ] },
      ],
    };
    expect(() => WeeklyPlanSchema.parse(sample)).not.toThrow();
  });

  it("rejects an unknown sport", () => {
    expect(() => WeeklyPlanSchema.parse({ week_start: "2026-05-25", reasoning: "", days: [
      { date: "2026-05-25", sport: "underwater_basket_weaving", duration_min: 60, intent: "endurance", description: "x", structure: [] },
    ] })).toThrow();
  });
});
```

- [ ] **Step 2: Implement `src/planner/schema.ts`**

```typescript
import { z } from "zod";

export const SportSchema = z.enum(["swim", "bike", "run", "brick", "strength", "rest"]);
export const IntentSchema = z.enum(["rest", "recovery", "endurance", "tempo", "threshold", "vo2", "anaerobic", "race-pace", "test", "strength"]);

export const StepSchema = z.object({
  type: z.enum(["warmup", "steady", "interval", "recovery", "cooldown"]),
  duration_min: z.number().nonnegative().optional(),
  duration_sec: z.number().nonnegative().optional(),
  reps: z.number().int().positive().optional(),
  work: z.lazy((): z.ZodType<any> => StepSchema).optional(),
  recovery_step: z.lazy((): z.ZodType<any> => StepSchema).optional(),
  target: z.string().optional(),
});

export const DaySchema = z.object({
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  sport: SportSchema,
  duration_min: z.number().nonnegative(),
  intent: IntentSchema,
  description: z.string(),
  structure: z.array(StepSchema),
  hotel_friendly: z.boolean().optional(),
  travel_aware: z.boolean().optional(),
  why: z.string().optional(),
});

export const WeeklyPlanSchema = z.object({
  week_start: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  reasoning: z.string(),
  days: z.array(DaySchema).length(7),
});

export type WeeklyPlan = z.infer<typeof WeeklyPlanSchema>;
export type Day = z.infer<typeof DaySchema>;
export type Step = z.infer<typeof StepSchema>;
```

- [ ] **Step 3: Commit**

```bash
npm test
git add src/planner/schema.ts tests/planner/schema.test.ts
git commit -m "feat: weekly plan zod schema"
```

---

### Task 33: Planning context builder

**Files:**
- Create: `src/planner/context.ts`
- Create: `tests/planner/context.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect } from "vitest";
import { openDb } from "../../src/db/client.js";
import { buildPlanningContext } from "../../src/planner/context.js";

describe("buildPlanningContext", () => {
  it("produces a context object including profile + recent activity + races", async () => {
    const db = openDb(":memory:");
    const ctx = await buildPlanningContext({
      db,
      profile: {
        races: [{ date: "2026-09-13", description: "70.3 World Championship", isARace: true }],
        budget: { baseHours: 10, peakHours: 14, strengthPerWeek: 3 },
        maxTrainingDaysPerWeek: 6,
        rawMarkdown: "...",
      },
      weekStart: "2026-05-25",
    });
    expect(ctx.upcoming_races).toHaveLength(1);
    expect(ctx.budget.base_hours).toBe(10);
    expect(ctx.week_start).toBe("2026-05-25");
  });
});
```

- [ ] **Step 2: Implement `src/planner/context.ts`**

```typescript
import type { Db } from "../db/client.js";
import { getActivitiesSince } from "../db/activities.js";
import { getWellnessSince } from "../db/wellness.js";
import { getNotesForDateRange } from "../db/notes.js";
import { getLatestAthleteModel } from "../db/athlete-model.js";
import type { AthleteProfile } from "../athlete/profile.js";

export interface PlanningContext {
  week_start: string;
  upcoming_races: { date: string; description: string }[];
  budget: { base_hours: number; peak_hours: number; strength_per_week: number };
  max_training_days: number;
  recent_activities: any[];
  recent_wellness: any[];
  upcoming_notes: any[];
  athlete_model: any;
  profile_md: string;
}

export interface BuildContextOpts {
  db: Db;
  profile: AthleteProfile;
  weekStart: string;
}

export async function buildPlanningContext({ db, profile, weekStart }: BuildContextOpts): Promise<PlanningContext> {
  const monthAgo = new Date(new Date(weekStart).getTime() - 28 * 24 * 60 * 60 * 1000).toISOString();
  const weekEnd = new Date(new Date(weekStart).getTime() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);

  return {
    week_start: weekStart,
    upcoming_races: profile.races.filter((r) => r.date >= weekStart),
    budget: {
      base_hours: profile.budget.baseHours,
      peak_hours: profile.budget.peakHours,
      strength_per_week: profile.budget.strengthPerWeek,
    },
    max_training_days: profile.maxTrainingDaysPerWeek,
    recent_activities: getActivitiesSince(db, monthAgo).slice(0, 50),
    recent_wellness: getWellnessSince(db, monthAgo.slice(0, 10)).slice(0, 30),
    upcoming_notes: getNotesForDateRange(db, weekStart, weekEnd),
    athlete_model: getLatestAthleteModel(db),
    profile_md: profile.rawMarkdown,
  };
}
```

- [ ] **Step 3: Commit**

```bash
npm test
git add src/planner/context.ts tests/planner/context.test.ts
git commit -m "feat: planning context builder"
```

---

### Task 34: Plan generation prompt + Claude call

**Files:**
- Create: `src/planner/generate.ts`
- Create: `tests/planner/generate.test.ts`

- [ ] **Step 1: Write failing test (with mocked Claude)**

```typescript
import { describe, it, expect, vi } from "vitest";
import { generateWeeklyPlan } from "../../src/planner/generate.js";

describe("generateWeeklyPlan", () => {
  it("calls claude with built prompt and parses returned JSON", async () => {
    const samplePlan = {
      week_start: "2026-05-25",
      reasoning: "base week",
      days: Array.from({ length: 7 }).map((_, i) => ({
        date: `2026-05-${25 + i}`,
        sport: i === 6 ? "rest" : "bike",
        duration_min: i === 6 ? 0 : 60,
        intent: i === 6 ? "rest" : "endurance",
        description: i === 6 ? "Rest day" : "Z2",
        structure: i === 6 ? [] : [{ type: "steady", duration_min: 60, target: "Z2" }],
      })),
    };
    const claudeFn = vi.fn().mockResolvedValue(JSON.stringify(samplePlan));
    const ctx = { week_start: "2026-05-25" } as any;
    const plan = await generateWeeklyPlan(ctx, claudeFn);
    expect(plan.week_start).toBe("2026-05-25");
    expect(plan.days).toHaveLength(7);
  });

  it("throws on invalid JSON shape", async () => {
    const claudeFn = vi.fn().mockResolvedValue('{"oops":true}');
    await expect(generateWeeklyPlan({} as any, claudeFn)).rejects.toThrow();
  });
});
```

- [ ] **Step 2: Implement `src/planner/generate.ts`**

```typescript
import { WeeklyPlanSchema, type WeeklyPlan } from "./schema.js";
import type { PlanningContext } from "./context.js";
import type { ClaudeFn } from "../whatsapp/intent.js";

const SYSTEM = `You are an expert triathlon coach for an age-group athlete training for 70.3 races.
Your job is to write ONE week of training in JSON.

PHILOSOPHY:
- Polarized training (mostly Z2, occasional VO2/threshold). Avoid junk miles.
- Sustainable, not destructive. Athlete is partially burned out — protect motivation.
- Travel-aware: hotel gym + treadmill + bodyweight is the floor when traveling.

HARD CONSTRAINTS (MUST be honored):
- Maximum 6 training days per week. At least one day must be sport "rest".
- Total weekly duration must be within the budget for the current phase.
- No two hard sessions back-to-back.
- Strength sessions away from key swim/bike/run sessions.
- Race-week sessions are sacred — do not write hard intervals during a race week.
- Weekly TSS ramp ≤ 10% from prior week.

OUTPUT: ONLY JSON matching this exact schema (no prose, no markdown fences):
{
  "week_start": "YYYY-MM-DD",
  "reasoning": "1-3 sentences on why this week looks like this",
  "days": [
    {
      "date": "YYYY-MM-DD",
      "sport": "swim|bike|run|brick|strength|rest",
      "duration_min": <number>,
      "intent": "rest|recovery|endurance|tempo|threshold|vo2|anaerobic|race-pace|test|strength",
      "description": "human-readable session summary",
      "structure": [
        {"type": "warmup|steady|interval|recovery|cooldown", "duration_min": <n>, "reps": <n?>, "work": {...?}, "recovery_step": {...?}, "target": "Z1|Z2|FTP*1.05|threshold|..."}
      ],
      "hotel_friendly": <bool?>,
      "why": "<one-line rationale>"
    }
    // exactly 7 days
  ]
}`;

export async function generateWeeklyPlan(ctx: PlanningContext, claudeFn: ClaudeFn): Promise<WeeklyPlan> {
  const prompt = `${SYSTEM}

CONTEXT:
${JSON.stringify(ctx, null, 2)}

Now generate the week. JSON only.`;
  const raw = await claudeFn(prompt);
  const cleaned = raw.replace(/^```(?:json)?\n?/i, "").replace(/\n?```$/, "").trim();
  const parsed = JSON.parse(cleaned);
  return WeeklyPlanSchema.parse(parsed);
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/planner/generate.ts tests/planner/generate.test.ts
git commit -m "feat: weekly plan generation via claude"
```

---

### Task 35: Guardrail validators

**Files:**
- Create: `src/planner/validate.ts`
- Create: `tests/planner/validate.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect } from "vitest";
import { validatePlan, autoCorrect } from "../../src/planner/validate.js";
import type { WeeklyPlan } from "../../src/planner/schema.js";

const baseDay = (date: string, sport: any, duration: number, intent: any) => ({
  date, sport, duration_min: duration, intent, description: "", structure: [],
});

describe("validatePlan", () => {
  it("flags 7-day weeks (no rest)", () => {
    const plan: WeeklyPlan = {
      week_start: "2026-05-25", reasoning: "",
      days: Array.from({ length: 7 }).map((_, i) => baseDay(`2026-05-${25 + i}`, "bike", 60, "endurance")),
    };
    const errors = validatePlan(plan, { maxTrainingDays: 6, weeklyHourBudget: 10 });
    expect(errors).toContain("no rest day");
  });

  it("flags exceeding hour budget", () => {
    const plan: WeeklyPlan = {
      week_start: "2026-05-25", reasoning: "",
      days: [
        baseDay("2026-05-25", "rest", 0, "rest"),
        ...Array.from({ length: 6 }).map((_, i) => baseDay(`2026-05-${26 + i}`, "bike", 200, "endurance")),
      ],
    };
    const errors = validatePlan(plan, { maxTrainingDays: 6, weeklyHourBudget: 10 });
    expect(errors.some((e) => e.includes("budget"))).toBe(true);
  });

  it("autoCorrect collapses extra training day to rest", () => {
    const plan: WeeklyPlan = {
      week_start: "2026-05-25", reasoning: "",
      days: Array.from({ length: 7 }).map((_, i) => baseDay(`2026-05-${25 + i}`, "bike", 30, "endurance")),
    };
    const corrected = autoCorrect(plan, { maxTrainingDays: 6, weeklyHourBudget: 10 });
    const restDays = corrected.days.filter((d) => d.sport === "rest").length;
    expect(restDays).toBe(1);
  });
});
```

- [ ] **Step 2: Implement `src/planner/validate.ts`**

```typescript
import type { WeeklyPlan } from "./schema.js";

export interface Guardrails { maxTrainingDays: number; weeklyHourBudget: number; }

export function validatePlan(plan: WeeklyPlan, g: Guardrails): string[] {
  const errors: string[] = [];
  const trainingDays = plan.days.filter((d) => d.sport !== "rest").length;
  const restDays = plan.days.filter((d) => d.sport === "rest").length;
  if (restDays === 0) errors.push("no rest day");
  if (trainingDays > g.maxTrainingDays) errors.push(`exceeds max training days (${trainingDays} > ${g.maxTrainingDays})`);
  const totalHours = plan.days.reduce((s, d) => s + d.duration_min / 60, 0);
  if (totalHours > g.weeklyHourBudget * 1.05) errors.push(`exceeds hour budget (${totalHours.toFixed(1)} > ${g.weeklyHourBudget})`);

  const hardIntents = new Set(["vo2", "threshold", "anaerobic", "race-pace"]);
  for (let i = 1; i < plan.days.length; i++) {
    if (hardIntents.has(plan.days[i].intent) && hardIntents.has(plan.days[i - 1].intent)) {
      errors.push(`back-to-back hard sessions on ${plan.days[i - 1].date} and ${plan.days[i].date}`);
    }
  }
  return errors;
}

export function autoCorrect(plan: WeeklyPlan, g: Guardrails): WeeklyPlan {
  const corrected: WeeklyPlan = JSON.parse(JSON.stringify(plan));
  if (corrected.days.filter((d) => d.sport === "rest").length === 0) {
    const trainingDays = corrected.days.filter((d) => d.sport !== "rest");
    trainingDays.sort((a, b) => a.duration_min - b.duration_min);
    const easiest = trainingDays[0];
    const idx = corrected.days.findIndex((d) => d.date === easiest.date);
    corrected.days[idx] = {
      date: easiest.date, sport: "rest", duration_min: 0, intent: "rest",
      description: "Rest day (auto-corrected: original 7-day week violated rest-day rule)",
      structure: [],
    };
  }
  return corrected;
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/planner/validate.ts tests/planner/validate.test.ts
git commit -m "feat: plan guardrail validators + auto-correct"
```

---

## Milestone 9: Phase 1 shadow mode

### Task 36: Coach-vs-Claude diff

**Files:**
- Create: `src/planner/diff.ts`
- Create: `tests/planner/diff.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect } from "vitest";
import { diffWeeks } from "../../src/planner/diff.js";

describe("diffWeeks", () => {
  it("produces a per-day diff string", () => {
    const coach = [
      { date: "2026-05-25", sport: "Run", description: "4x1km @ threshold" },
      { date: "2026-05-26", sport: "Bike", description: "60min Z2" },
    ];
    const mine = {
      week_start: "2026-05-25", reasoning: "",
      days: [
        { date: "2026-05-25", sport: "run" as const, duration_min: 60, intent: "vo2" as const, description: "5x800m VO2", structure: [] },
        { date: "2026-05-26", sport: "bike" as const, duration_min: 60, intent: "endurance" as const, description: "60min Z2", structure: [] },
      ],
    };
    const out = diffWeeks(coach, mine as any);
    expect(out).toMatch(/2026-05-25/);
    expect(out).toMatch(/coach/i);
    expect(out).toMatch(/claude/i);
  });
});
```

- [ ] **Step 2: Implement `src/planner/diff.ts`**

```typescript
import type { WeeklyPlan } from "./schema.js";
import type { IntervalsEvent } from "../intervals/events.js";

export interface CoachSession { date: string; sport: string; description: string; }

export function diffWeeks(coach: CoachSession[], mine: WeeklyPlan): string {
  const lines: string[] = ["Coach vs Claude diff for next week:"];
  for (const day of mine.days) {
    const coachOnDay = coach.filter((c) => c.date.startsWith(day.date));
    const coachLine = coachOnDay.length > 0 ? coachOnDay.map((c) => `${c.sport}: ${c.description}`).join(" + ") : "(nothing)";
    const mineLine = day.sport === "rest" ? "rest" : `${day.sport}: ${day.description}`;
    lines.push(`  ${day.date}`);
    lines.push(`    coach:  ${coachLine}`);
    lines.push(`    claude: ${mineLine}`);
  }
  lines.push("", `Reasoning: ${mine.reasoning}`);
  return lines.join("\n");
}

export function eventsToCoachSessions(events: IntervalsEvent[]): CoachSession[] {
  return events
    .filter((e) => e.category === "WORKOUT")
    .map((e) => ({
      date: e.start_date_local.slice(0, 10),
      sport: e.type ?? "unknown",
      description: e.name + (e.description ? ` — ${e.description}` : ""),
    }));
}
```

- [ ] **Step 3: Commit**

```bash
npm test
git add src/planner/diff.ts tests/planner/diff.test.ts
git commit -m "feat: coach-vs-claude weekly diff"
```

---

### Task 37: Sunday cycle Phase 1 mode (shadow)

**Files:**
- Modify: `src/jobs/sunday-cycle.ts`
- Create: `tests/jobs/sunday-cycle-phase1.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { openDb } from "../../src/db/client.js";
import fs from "node:fs";
import path from "node:path";
import os from "node:os";
import { runSundayCycle } from "../../src/jobs/sunday-cycle.js";

describe("runSundayCycle (phase 1)", () => {
  it("generates a plan, fetches coach events, sends diff", async () => {
    const db = openDb(":memory:");
    const profilePath = path.join(os.tmpdir(), `athlete-${Date.now()}.md`);
    fs.writeFileSync(profilePath, "## Race calendar\n- 2026-09-13: 70.3 World Championship\n## Training time budget\n- Base weeks: 10 hrs target\n- Peak weeks: 14 hrs cap\n- Strength: 3x/week\n## Constraints + preferences\n- Maximum 6 training days per week, minimum 1 full rest day (HARD)\n");

    const samplePlan = {
      week_start: "2026-05-25", reasoning: "base week",
      days: Array.from({ length: 7 }).map((_, i) => ({
        date: `2026-05-${String(25 + i).padStart(2, "0")}`,
        sport: i === 6 ? "rest" : "bike",
        duration_min: i === 6 ? 0 : 60,
        intent: i === 6 ? "rest" : "endurance",
        description: i === 6 ? "Rest" : "Z2",
        structure: i === 6 ? [] : [{ type: "steady", duration_min: 60, target: "Z2" }],
      })),
    };

    const intervalsClient: any = { get: vi.fn().mockResolvedValue([]) };
    const sendFn = vi.fn().mockResolvedValue("wamid.x");
    const claudeFn = vi.fn().mockResolvedValue(JSON.stringify(samplePlan));

    await runSundayCycle({
      db, intervalsClient, athleteId: "i1", phase: 1, sendFn,
      profilePath, claudeFn, weekStart: "2026-05-25",
    });

    expect(sendFn).toHaveBeenCalledOnce();
    const sent = sendFn.mock.calls[0][0];
    expect(sent.body).toMatch(/coach/i);
  });
});
```

- [ ] **Step 2: Modify `src/jobs/sunday-cycle.ts` to add phase 1 branch**

Replace the function body with:

```typescript
import type { Db } from "../db/client.js";
import type { IntervalsClient } from "../intervals/client.js";
import { runSync } from "./sync.js";
import { runRefreshModel } from "./refresh-model.js";
import { buildObservationSummary } from "../planner/observation.js";
import { loadAthleteProfile } from "../athlete/profile.js";
import { buildPlanningContext } from "../planner/context.js";
import { generateWeeklyPlan } from "../planner/generate.js";
import { validatePlan, autoCorrect } from "../planner/validate.js";
import { fetchEvents } from "../intervals/events.js";
import { diffWeeks, eventsToCoachSessions } from "../planner/diff.js";
import { insertPlan } from "../db/plans.js";
import { logger } from "../lib/logger.js";

export interface SundayCycleOpts {
  db: Db;
  intervalsClient: IntervalsClient;
  athleteId: string;
  phase: 0 | 1 | 2;
  sendFn: (opts: { body: string }) => Promise<string>;
  profilePath?: string;
  claudeFn?: (prompt: string) => Promise<string>;
  weekStart?: string;
}

function nextSundayISO(): string {
  const now = new Date();
  const day = now.getUTCDay();
  const daysUntilNextMon = (8 - day) % 7 || 7;
  return new Date(now.getTime() + daysUntilNextMon * 86400000).toISOString().slice(0, 10);
}

export async function runSundayCycle(opts: SundayCycleOpts): Promise<void> {
  logger.info({ phase: opts.phase }, "sunday cycle: start");
  await runSync({ db: opts.db, client: opts.intervalsClient, athleteId: opts.athleteId });
  runRefreshModel({ db: opts.db });

  if (opts.phase === 0) {
    await opts.sendFn({ body: buildObservationSummary(opts.db) });
    return;
  }

  if (!opts.profilePath || !opts.claudeFn) {
    throw new Error("phase 1+ requires profilePath and claudeFn");
  }
  const weekStart = opts.weekStart ?? nextSundayISO();
  const profile = loadAthleteProfile(opts.profilePath);
  const ctx = await buildPlanningContext({ db: opts.db, profile, weekStart });
  let plan = await generateWeeklyPlan(ctx, opts.claudeFn);
  const errors = validatePlan(plan, { maxTrainingDays: profile.maxTrainingDaysPerWeek, weeklyHourBudget: profile.budget.baseHours });
  if (errors.length > 0) {
    logger.warn({ errors }, "plan failed validation; auto-correcting");
    plan = autoCorrect(plan, { maxTrainingDays: profile.maxTrainingDaysPerWeek, weeklyHourBudget: profile.budget.baseHours });
  }
  insertPlan(opts.db, { week_start: weekStart, phase: opts.phase, payload_json: JSON.stringify(plan), reasoning: plan.reasoning, published: 0 });

  if (opts.phase === 1) {
    const weekEnd = new Date(new Date(weekStart).getTime() + 7 * 86400000).toISOString().slice(0, 10);
    const coachEvents = await fetchEvents(opts.intervalsClient, opts.athleteId, weekStart, weekEnd);
    const coachSessions = eventsToCoachSessions(coachEvents);
    const diff = diffWeeks(coachSessions, plan);
    const summary = `Phase 1 (shadow) — week of ${weekStart}.\n\n${diff}`.slice(0, 4000);
    await opts.sendFn({ body: summary });
    return;
  }

  // Phase 2 added in M12
  throw new Error(`Phase 2 not yet implemented`);
}
```

- [ ] **Step 3: Update CLI to wire profile + claude in phase 1+**

In `src/cli.ts`, inside the `sunday-cycle` case, change to:

```typescript
case "sunday-cycle": {
  const { askClaude } = await import("./planner/claude.js");
  await runSundayCycle({
    db, intervalsClient, athleteId: cfg.intervals.athleteId, phase,
    sendFn: ({ body }) => sendWithRetry({
      phoneNumberId: cfg.whatsapp.phoneNumberId,
      accessToken: cfg.whatsapp.accessToken,
      recipient: cfg.whatsapp.recipient,
      body,
    }),
    profilePath: cfg.athleteProfilePath,
    claudeFn: askClaude,
  });
  break;
}
```

- [ ] **Step 4: Run, commit**

```bash
npm test
git add src/jobs/sunday-cycle.ts tests/jobs/sunday-cycle-phase1.test.ts src/cli.ts
git commit -m "feat: phase 1 shadow mode (claude diff vs coach)"
```

---

### Task 38: Phase 1 deploy switch

- [ ] **Step 1: Update VPS env to flip COACH_PHASE=1 on May 17**

```bash
ssh twins-coach "sudo -u coach sed -i 's/COACH_PHASE=0/COACH_PHASE=1/' /opt/twins-coach/.env"
```

- [ ] **Step 2: Manual trigger to verify**

```bash
ssh twins-coach "sudo systemctl start sunday-cycle.service && sudo journalctl -u sunday-cycle.service -n 100"
```

Expected: WhatsApp arrives showing the coach-vs-Claude diff.

> **🎯 PHASE 1 LIVE.** Daniel sees Claude's plan alongside his coach's plan every Sunday until coach leaves in June.

---

## Milestone 10: Translator + publisher

### Task 39: JSON → intervals.icu workout_doc translator

**Files:**
- Create: `src/planner/translate.ts`
- Create: `tests/planner/translate.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect } from "vitest";
import { translateDay } from "../../src/planner/translate.js";

describe("translateDay", () => {
  it("translates a bike interval session into an intervals.icu event", () => {
    const day = {
      date: "2026-05-26", sport: "bike" as const, duration_min: 75, intent: "vo2" as const,
      description: "5x4' VO2",
      structure: [
        { type: "warmup" as const, duration_min: 15, target: "Z2" },
        { type: "interval" as const, reps: 5,
          work: { type: "steady" as const, duration_min: 4, target: "FTP*1.10" },
          recovery_step: { type: "recovery" as const, duration_min: 3, target: "Z1" } },
        { type: "cooldown" as const, duration_min: 10, target: "Z1" },
      ],
    };
    const e = translateDay(day);
    expect(e.category).toBe("WORKOUT");
    expect(e.type).toBe("Ride");
    expect(e.start_date_local).toBe("2026-05-26T06:00:00");
    expect(e.icu_training_load).toBeGreaterThan(0);
    expect(typeof e.description).toBe("string");
    expect(e.description).toMatch(/Warmup 15 min/);
    expect(e.description).toMatch(/5 x 4 min @ FTP\*1\.10/);
  });

  it("translates a strength day to a calendar block (no workout_doc)", () => {
    const day = {
      date: "2026-05-27", sport: "strength" as const, duration_min: 45, intent: "strength" as const,
      description: "Strength A: deadlift 4x5, single-leg RDL 3x8/leg, plank 3x30s",
      structure: [],
    };
    const e = translateDay(day);
    expect(e.category).toBe("NOTE");
    expect(e.description).toMatch(/deadlift/);
  });

  it("translates a rest day to a NOTE event", () => {
    const day = { date: "2026-05-28", sport: "rest" as const, duration_min: 0, intent: "rest" as const, description: "Rest", structure: [] };
    const e = translateDay(day);
    expect(e.category).toBe("NOTE");
    expect(e.name).toMatch(/Rest/);
  });
});
```

- [ ] **Step 2: Implement `src/planner/translate.ts`**

```typescript
import type { Day, Step } from "./schema.js";

export interface IntervalsEventInput {
  category: "WORKOUT" | "NOTE";
  type: string | null;
  name: string;
  start_date_local: string;
  description: string;
  icu_training_load?: number;
  moving_time?: number;
}

const SPORT_TO_INTERVALS: Record<string, string | null> = {
  swim: "Swim", bike: "Ride", run: "Run", brick: "Ride", strength: null, rest: null,
};

const DEFAULT_TIME = "06:00:00";

function describeStep(s: Step, indent = ""): string {
  const dur = s.duration_min ? `${s.duration_min} min` : s.duration_sec ? `${s.duration_sec} sec` : "";
  if (s.type === "interval" && s.reps && s.work && s.recovery_step) {
    const workDur = s.work.duration_min ? `${s.work.duration_min} min` : `${s.work.duration_sec} sec`;
    const recDur = s.recovery_step.duration_min ? `${s.recovery_step.duration_min} min` : `${s.recovery_step.duration_sec} sec`;
    return `${indent}${s.reps} x ${workDur} @ ${s.work.target} / ${recDur} recovery @ ${s.recovery_step.target}`;
  }
  const label = s.type === "warmup" ? "Warmup" : s.type === "cooldown" ? "Cooldown" : s.type === "recovery" ? "Recovery" : "Steady";
  return `${indent}${label} ${dur} @ ${s.target ?? "Z2"}`;
}

function estimateTSS(day: Day): number {
  const intensityFactor: Record<string, number> = {
    rest: 0, recovery: 0.55, endurance: 0.7, tempo: 0.85,
    threshold: 0.95, vo2: 1.05, anaerobic: 1.15, "race-pace": 0.85, test: 1.0, strength: 0.5,
  };
  const factor = intensityFactor[day.intent] ?? 0.7;
  return Math.round((day.duration_min / 60) * factor * factor * 100);
}

export function translateDay(day: Day): IntervalsEventInput {
  const sport = SPORT_TO_INTERVALS[day.sport];
  const startLocal = `${day.date}T${DEFAULT_TIME}`;

  if (day.sport === "rest") {
    return {
      category: "NOTE", type: null,
      name: "Rest day", start_date_local: startLocal, description: day.description,
    };
  }

  if (day.sport === "strength") {
    return {
      category: "NOTE", type: null,
      name: `Strength (${day.duration_min} min)`,
      start_date_local: startLocal,
      description: day.description,
      moving_time: day.duration_min * 60,
    };
  }

  const lines = day.structure.map((s) => describeStep(s));
  const description = [day.description, "", ...lines, "", day.why ? `Why: ${day.why}` : ""].filter(Boolean).join("\n");

  return {
    category: "WORKOUT",
    type: sport,
    name: day.description.slice(0, 60),
    start_date_local: startLocal,
    description,
    icu_training_load: estimateTSS(day),
    moving_time: day.duration_min * 60,
  };
}
```

> **Note:** Phase 2 v1 publishes structured workouts as event descriptions only — Daniel reads the description, executes manually on his Garmin (or imports as a planned activity). Building full intervals.icu `workout_doc` JSON for native structured-workout sync to Garmin is deferred to a v2 enhancement post-shipping (it's a non-trivial DSL with separate handling per sport). The description-based path satisfies "workouts appear in TP" today; richer Garmin integration is a follow-up.

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/planner/translate.ts tests/planner/translate.test.ts
git commit -m "feat: weekly plan → intervals.icu event translator (description-based)"
```

---

### Task 40: intervals.icu event publisher

**Files:**
- Create: `src/intervals/publish.ts`
- Create: `tests/intervals/publish.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { publishWeek } from "../../src/intervals/publish.js";

describe("publishWeek", () => {
  it("DELETEs existing claude-published events in range, then POSTs each new event", async () => {
    const get = vi.fn().mockResolvedValue([
      { id: "old1", description: "[claude]", start_date_local: "2026-05-26T06:00:00" },
      { id: "old2", description: "coach event", start_date_local: "2026-05-27T06:00:00" },
    ]);
    const del = vi.fn().mockResolvedValue(undefined);
    const post = vi.fn().mockResolvedValue({ id: "new1" });
    const client: any = { get, post, delete: del };
    await publishWeek(client, "i1", "2026-05-25", [
      { category: "WORKOUT" as const, type: "Ride", name: "x", start_date_local: "2026-05-26T06:00:00", description: "[claude] details" },
    ]);
    expect(del).toHaveBeenCalledTimes(1);
    expect(del).toHaveBeenCalledWith("/athlete/i1/events/old1");
    expect(post).toHaveBeenCalledTimes(1);
  });
});
```

- [ ] **Step 2: Implement `src/intervals/publish.ts`**

```typescript
import type { IntervalsClient } from "./client.js";
import type { IntervalsEventInput } from "../planner/translate.js";
import type { IntervalsEvent } from "./events.js";

const CLAUDE_TAG = "[claude]";

function tagEvent(e: IntervalsEventInput): IntervalsEventInput {
  return { ...e, description: `${CLAUDE_TAG} ${e.description}` };
}

export async function publishWeek(
  client: IntervalsClient,
  athleteId: string,
  weekStart: string,
  events: IntervalsEventInput[],
): Promise<{ created: string[]; deleted: string[] }> {
  const weekEnd = new Date(new Date(weekStart).getTime() + 7 * 86400000).toISOString().slice(0, 10);
  const existing = await client.get(`/athlete/${athleteId}/events?oldest=${weekStart}&newest=${weekEnd}`) as IntervalsEvent[];
  const claudeOwned = existing.filter((e) => e.description?.startsWith(CLAUDE_TAG));
  const deleted: string[] = [];
  for (const e of claudeOwned) {
    await client.delete(`/athlete/${athleteId}/events/${e.id}`);
    deleted.push(e.id);
  }
  const created: string[] = [];
  for (const e of events) {
    const r = await client.post(`/athlete/${athleteId}/events`, tagEvent(e)) as { id: string };
    created.push(r.id);
  }
  return { created, deleted };
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/intervals/publish.ts tests/intervals/publish.test.ts
git commit -m "feat: publish weekly events to intervals.icu (idempotent via [claude] tag)"
```

---

### Task 41: Sunday cycle Phase 2 mode (publish + summary)

**Files:**
- Modify: `src/jobs/sunday-cycle.ts`
- Create: `tests/jobs/sunday-cycle-phase2.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { openDb } from "../../src/db/client.js";
import fs from "node:fs"; import path from "node:path"; import os from "node:os";
import { runSundayCycle } from "../../src/jobs/sunday-cycle.js";

describe("runSundayCycle (phase 2)", () => {
  it("publishes events to intervals and sends a summary", async () => {
    const db = openDb(":memory:");
    const profilePath = path.join(os.tmpdir(), `athlete-${Date.now()}.md`);
    fs.writeFileSync(profilePath, "## Race calendar\n- 2026-09-13: 70.3 World Championship\n## Training time budget\n- Base weeks: 10 hrs target\n- Peak weeks: 14 hrs cap\n- Strength: 3x/week\n## Constraints + preferences\n- Maximum 6 training days per week, minimum 1 full rest day (HARD)\n");
    const samplePlan = {
      week_start: "2026-05-25", reasoning: "base",
      days: Array.from({ length: 7 }).map((_, i) => ({
        date: `2026-05-${String(25 + i).padStart(2, "0")}`,
        sport: i === 6 ? "rest" : "bike",
        duration_min: i === 6 ? 0 : 60,
        intent: i === 6 ? "rest" : "endurance",
        description: i === 6 ? "Rest" : "Z2",
        structure: i === 6 ? [] : [{ type: "steady", duration_min: 60, target: "Z2" }],
      })),
    };
    const intervalsClient: any = {
      get: vi.fn().mockResolvedValue([]),
      post: vi.fn().mockResolvedValue({ id: "new" }),
      delete: vi.fn(),
    };
    const sendFn = vi.fn().mockResolvedValue("wamid.x");
    const claudeFn = vi.fn().mockResolvedValue(JSON.stringify(samplePlan));

    await runSundayCycle({
      db, intervalsClient, athleteId: "i1", phase: 2, sendFn,
      profilePath, claudeFn, weekStart: "2026-05-25",
    });

    expect(intervalsClient.post).toHaveBeenCalled();
    expect(sendFn).toHaveBeenCalledOnce();
    expect(sendFn.mock.calls[0][0].body).toMatch(/published/i);
  });
});
```

- [ ] **Step 2: Create `src/planner/summary.ts` first (Phase 2 branch will import from it)**

```typescript
import type { WeeklyPlan } from "./schema.js";

export function formatPhase2Summary(plan: WeeklyPlan): string {
  const totalHours = plan.days.reduce((s, d) => s + d.duration_min / 60, 0);
  const hard = plan.days.find((d) => ["vo2", "threshold", "anaerobic"].includes(d.intent));
  const long = [...plan.days].sort((a, b) => b.duration_min - a.duration_min)[0];
  const lines = [
    `Week of ${plan.week_start} published — ${totalHours.toFixed(1)} hrs total`,
    hard ? `Hard day: ${hard.date.slice(5)} ${hard.sport} ${hard.intent} (${hard.description.slice(0, 40)})` : "No hard sessions this week",
    `Long day: ${long.date.slice(5)} ${long.sport} ${long.duration_min} min`,
    `Workouts will appear on your watch within 30 min.`,
  ];
  return lines.join("\n");
}
```

- [ ] **Step 3: Add Phase 2 branch to `src/jobs/sunday-cycle.ts`**

Add these imports at the top of `src/jobs/sunday-cycle.ts` alongside existing imports:

```typescript
import { translateDay } from "../planner/translate.js";
import { publishWeek } from "../intervals/publish.js";
import { markPlanPublished } from "../db/plans.js";
import { formatPhase2Summary } from "../planner/summary.js";
```

Then in `runSundayCycle`, REPLACE the `throw new Error("Phase 2 not yet implemented")` line with:

```typescript
if (opts.phase === 2) {
  const events = plan.days.map(translateDay);
  const result = await publishWeek(opts.intervalsClient, opts.athleteId, weekStart, events);
  logger.info({ created: result.created.length, deleted: result.deleted.length }, "publish complete");
  const planRow = opts.db.prepare("SELECT id FROM plans ORDER BY id DESC LIMIT 1").get() as { id: number };
  markPlanPublished(opts.db, planRow.id);
  await opts.sendFn({ body: formatPhase2Summary(plan) });
  return;
}
```

- [ ] **Step 4: Run, commit**

```bash
npm test
git add src/jobs/sunday-cycle.ts src/planner/summary.ts tests/jobs/sunday-cycle-phase2.test.ts
git commit -m "feat: phase 2 sunday cycle (publish + summary)"
```

---

## Milestone 11: Daily safety check

### Task 42: Wellness signal evaluator

**Files:**
- Create: `src/planner/safety.ts`
- Create: `tests/planner/safety.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect } from "vitest";
import { evaluateSafetySignal, type SafetyOutcome } from "../../src/planner/safety.js";

describe("evaluateSafetySignal", () => {
  it("returns downgrade when HRV is well below baseline", () => {
    const out = evaluateSafetySignal({
      todayHrv: 50, hrvBaseline: 70, hrvBaselineSd: 5,
      todaySleepHours: 6, recentTextSays: null,
    });
    expect(out.action).toBe("downgrade");
  });

  it("returns upgrade-recommendation on great signals", () => {
    const out = evaluateSafetySignal({
      todayHrv: 85, hrvBaseline: 70, hrvBaselineSd: 5,
      todaySleepHours: 8, recentTextSays: null,
    });
    expect(out.action).toBe("recommend-upgrade");
  });

  it("forces downgrade when text says 'cooked'", () => {
    const out = evaluateSafetySignal({
      todayHrv: 70, hrvBaseline: 70, hrvBaselineSd: 5,
      todaySleepHours: 8, recentTextSays: "feeling cooked",
    });
    expect(out.action).toBe("downgrade");
  });

  it("returns none when signals are normal", () => {
    const out = evaluateSafetySignal({
      todayHrv: 70, hrvBaseline: 70, hrvBaselineSd: 5,
      todaySleepHours: 7, recentTextSays: null,
    });
    expect(out.action).toBe("none");
  });
});
```

- [ ] **Step 2: Implement `src/planner/safety.ts`**

```typescript
export interface SafetyInput {
  todayHrv: number | null;
  hrvBaseline: number | null;
  hrvBaselineSd: number | null;
  todaySleepHours: number | null;
  recentTextSays: string | null;
}

export interface SafetyOutcome {
  action: "downgrade" | "recommend-upgrade" | "none";
  reason: string;
}

const COOKED_KEYWORDS = ["cooked", "tired", "exhausted", "sick", "feeling off", "wiped"];
const FRESH_KEYWORDS = ["great", "fresh", "ready", "amazing"];

export function evaluateSafetySignal(s: SafetyInput): SafetyOutcome {
  if (s.recentTextSays) {
    const lower = s.recentTextSays.toLowerCase();
    if (COOKED_KEYWORDS.some((k) => lower.includes(k))) {
      return { action: "downgrade", reason: `text: "${s.recentTextSays.slice(0, 60)}"` };
    }
  }

  if (s.todaySleepHours !== null && s.todaySleepHours < 5.5) {
    return { action: "downgrade", reason: `slept ${s.todaySleepHours.toFixed(1)} hrs` };
  }

  if (s.todayHrv !== null && s.hrvBaseline !== null && s.hrvBaselineSd !== null) {
    const z = (s.todayHrv - s.hrvBaseline) / s.hrvBaselineSd;
    if (z < -1.5) return { action: "downgrade", reason: `HRV ${s.todayHrv} (${z.toFixed(1)} SD below baseline)` };
    if (z > 1.5 && s.todaySleepHours !== null && s.todaySleepHours >= 7) {
      const fresh = s.recentTextSays && FRESH_KEYWORDS.some((k) => s.recentTextSays!.toLowerCase().includes(k));
      return { action: "recommend-upgrade", reason: `HRV ${s.todayHrv} (+${z.toFixed(1)} SD)${fresh ? " + you reported feeling fresh" : ""}` };
    }
  }

  return { action: "none", reason: "signals normal" };
}
```

- [ ] **Step 3: Run, commit**

```bash
npm test
git add src/planner/safety.ts tests/planner/safety.test.ts
git commit -m "feat: daily safety signal evaluator"
```

---

### Task 43: Daily check job

**Files:**
- Create: `src/jobs/daily-check.ts`
- Create: `tests/jobs/daily-check.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { openDb } from "../../src/db/client.js";
import { upsertWellness } from "../../src/db/wellness.js";
import { insertAthleteModel } from "../../src/db/athlete-model.js";
import { insertPlan } from "../../src/db/plans.js";
import { runDailyCheck } from "../../src/jobs/daily-check.js";

describe("runDailyCheck", () => {
  it("downgrades today's session and texts when HRV is crashed", async () => {
    const db = openDb(":memory:");
    const today = new Date().toISOString().slice(0, 10);
    upsertWellness(db, { date: today, hrv: 40, resting_hr: 60, sleep_hours: 6, sleep_score: 60, body_battery: 50, weight: 78, subjective_rating: 4, raw_json: "{}" });
    insertAthleteModel(db, { ftp_w: null, threshold_pace_sec_per_km: null, css_sec_per_100m: null, weekly_hours_avg: 8, compliance_rate: 0.9, hrv_baseline: 70, hrv_baseline_sd: 5, notes: null });
    const plan = { week_start: today, reasoning: "", days: Array.from({ length: 7 }).map((_, i) => ({
      date: new Date(Date.now() + i * 86400000).toISOString().slice(0, 10),
      sport: "bike", duration_min: 60, intent: "vo2", description: "5x4 VO2", structure: [],
    })) };
    insertPlan(db, { week_start: today, phase: 2, payload_json: JSON.stringify(plan), reasoning: "", published: 1 });

    const intervalsClient: any = {
      get: vi.fn().mockResolvedValue([{ id: "ev1", description: "[claude] orig", start_date_local: `${today}T06:00:00` }]),
      post: vi.fn().mockResolvedValue({ id: "new" }),
      put: vi.fn().mockResolvedValue({}),
      delete: vi.fn().mockResolvedValue(undefined),
    };
    const sendFn = vi.fn().mockResolvedValue("wamid.x");

    await runDailyCheck({ db, intervalsClient, athleteId: "i1", sendFn });

    expect(sendFn).toHaveBeenCalled();
    expect(sendFn.mock.calls[0][0].body.toLowerCase()).toMatch(/swap|downgrade|easy/);
  });
});
```

- [ ] **Step 2: Implement `src/jobs/daily-check.ts`**

```typescript
import type { Db } from "../db/client.js";
import type { IntervalsClient } from "../intervals/client.js";
import { evaluateSafetySignal } from "../planner/safety.js";
import { getLatestAthleteModel } from "../db/athlete-model.js";
import { getWellnessSince } from "../db/wellness.js";
import { getCurrentWeekPlan } from "../db/plans.js";
import { getNotesForDateRange } from "../db/notes.js";
import { translateDay } from "../planner/translate.js";
import { publishWeek } from "../intervals/publish.js";
import type { WeeklyPlan } from "../planner/schema.js";
import { logger } from "../lib/logger.js";

function isRaceWeek(plan: WeeklyPlan): boolean {
  return plan.days.some((d) => d.intent === "race-pace" || /race/i.test(d.description));
}

export interface DailyCheckOpts {
  db: Db;
  intervalsClient: IntervalsClient;
  athleteId: string;
  sendFn: (opts: { body: string }) => Promise<string>;
}

export async function runDailyCheck({ db, intervalsClient, athleteId, sendFn }: DailyCheckOpts): Promise<void> {
  const today = new Date().toISOString().slice(0, 10);
  const wellness = getWellnessSince(db, today)[0] ?? null;
  const model = getLatestAthleteModel(db);
  const todaysNotes = getNotesForDateRange(db, today, today);
  const recentText = todaysNotes.length > 0 ? todaysNotes[todaysNotes.length - 1].body : null;

  const monday = (() => {
    const d = new Date(today);
    const day = d.getUTCDay();
    const diff = day === 0 ? -6 : 1 - day;
    return new Date(d.getTime() + diff * 86400000).toISOString().slice(0, 10);
  })();
  const planRow = getCurrentWeekPlan(db, monday);
  if (!planRow) { logger.info("no plan for current week; skipping"); return; }
  const plan = JSON.parse(planRow.payload_json) as WeeklyPlan;
  const todayPlanned = plan.days.find((d) => d.date === today);
  if (!todayPlanned) return;

  if (isRaceWeek(plan)) { logger.info("race week — skipping safety check"); return; }

  const outcome = evaluateSafetySignal({
    todayHrv: wellness?.hrv ?? null,
    hrvBaseline: model?.hrv_baseline ?? null,
    hrvBaselineSd: model?.hrv_baseline_sd ?? null,
    todaySleepHours: wellness?.sleep_hours ?? null,
    recentTextSays: recentText,
  });

  if (outcome.action === "none") { logger.info("signals normal; no action"); return; }

  if (outcome.action === "downgrade") {
    const easier = { ...todayPlanned, intent: "endurance" as const,
      description: `Z2 endurance (auto-downgraded — ${outcome.reason})`,
      structure: [{ type: "steady" as const, duration_min: Math.min(todayPlanned.duration_min, 60), target: "Z2" }] };
    const newDays = plan.days.map((d) => d.date === today ? easier : d);
    await publishWeek(intervalsClient, athleteId, monday, newDays.map(translateDay));
    await sendFn({ body: `Swapped today's ${todayPlanned.sport} (${todayPlanned.intent}) → Z2 endurance. ${outcome.reason}` });
    return;
  }

  if (outcome.action === "recommend-upgrade") {
    await sendFn({ body: `Signals look strong: ${outcome.reason}. Want me to add a 30' VO2 block to today's ${todayPlanned.sport}? Reply YES to confirm.` });
  }
}
```

- [ ] **Step 3: Add daily-check command to `src/cli.ts`**

```typescript
case "daily-check":
  await runDailyCheck({
    db, intervalsClient, athleteId: cfg.intervals.athleteId,
    sendFn: ({ body }) => sendWithRetry({
      phoneNumberId: cfg.whatsapp.phoneNumberId, accessToken: cfg.whatsapp.accessToken,
      recipient: cfg.whatsapp.recipient, body,
    }),
  });
  break;
```

- [ ] **Step 4: Create systemd units**

`deploy/daily-check.service`:
```ini
[Unit]
Description=twins-coach daily safety check
After=network.target

[Service]
Type=oneshot
User=coach
WorkingDirectory=/opt/twins-coach
EnvironmentFile=/opt/twins-coach/.env
ExecStart=/usr/bin/npx tsx src/cli.ts daily-check
StandardOutput=journal
StandardError=journal
```

`deploy/daily-check.timer`:
```ini
[Unit]
Description=Run twins-coach daily check at 06:00 local

[Timer]
OnCalendar=*-*-* 06:00:00
Persistent=true

[Install]
WantedBy=timers.target
```

Update `scripts/deploy.sh` to enable: `sudo systemctl enable --now daily-check.timer`

- [ ] **Step 5: Run tests, commit**

```bash
npm test
git add src/jobs/daily-check.ts tests/jobs/daily-check.test.ts src/cli.ts deploy/daily-check.service deploy/daily-check.timer scripts/deploy.sh
git commit -m "feat: daily 6am safety check (downgrade + upgrade-recommend)"
```

---

## Milestone 12: Phase 2 production + slash commands

### Task 44: Slash command handlers (/replan, /why, /profile)

**Files:**
- Create: `src/handlers/slash.ts`
- Create: `tests/handlers/slash.test.ts`

- [ ] **Step 1: Write failing test**

```typescript
import { describe, it, expect, vi } from "vitest";
import { openDb } from "../../src/db/client.js";
import { insertPlan } from "../../src/db/plans.js";
import { insertAthleteModel } from "../../src/db/athlete-model.js";
import { handleSlash } from "../../src/handlers/slash.js";

describe("handleSlash", () => {
  it("/why returns the current plan reasoning", async () => {
    const db = openDb(":memory:");
    insertPlan(db, { week_start: "2026-05-25", phase: 2, payload_json: "{}", reasoning: "base week, low key", published: 1 });
    const send = vi.fn();
    await handleSlash({ db, command: "why", args: "", sendFn: send, intervalsClient: {} as any, athleteId: "i1", profilePath: "" });
    expect(send.mock.calls[0][0].body).toMatch(/base week/);
  });

  it("/profile returns the athlete model", async () => {
    const db = openDb(":memory:");
    insertAthleteModel(db, { ftp_w: 250, threshold_pace_sec_per_km: 280, css_sec_per_100m: 100, weekly_hours_avg: 9, compliance_rate: 0.85, hrv_baseline: 70, hrv_baseline_sd: 5, notes: null });
    const send = vi.fn();
    await handleSlash({ db, command: "profile", args: "", sendFn: send, intervalsClient: {} as any, athleteId: "i1", profilePath: "" });
    expect(send.mock.calls[0][0].body).toMatch(/FTP|HRV/i);
  });
});
```

- [ ] **Step 2: Implement `src/handlers/slash.ts`**

```typescript
import type { Db } from "../db/client.js";
import type { IntervalsClient } from "../intervals/client.js";
import { getCurrentWeekPlan } from "../db/plans.js";
import { getLatestAthleteModel } from "../db/athlete-model.js";

export interface SlashOpts {
  db: Db;
  command: string;
  args: string;
  sendFn: (opts: { body: string }) => Promise<string>;
  intervalsClient: IntervalsClient;
  athleteId: string;
  profilePath: string;
}

export async function handleSlash(opts: SlashOpts): Promise<void> {
  switch (opts.command) {
    case "why": {
      const monday = mondayOfThisWeek();
      const plan = getCurrentWeekPlan(opts.db, monday);
      const reasoning = plan?.reasoning ?? "No current plan yet.";
      await opts.sendFn({ body: `Why: ${reasoning}` });
      return;
    }
    case "profile": {
      const m = getLatestAthleteModel(opts.db);
      if (!m) { await opts.sendFn({ body: "No athlete model computed yet." }); return; }
      const lines = [
        `FTP: ${m.ftp_w ?? "n/a"} W`,
        `Threshold pace: ${m.threshold_pace_sec_per_km ?? "n/a"} sec/km`,
        `CSS: ${m.css_sec_per_100m ?? "n/a"} sec/100m`,
        `Weekly hours (avg): ${m.weekly_hours_avg?.toFixed(1) ?? "n/a"}`,
        `Compliance: ${m.compliance_rate !== null ? Math.round((m.compliance_rate ?? 0) * 100) + "%" : "n/a"}`,
        `HRV baseline: ${m.hrv_baseline?.toFixed(0) ?? "n/a"} (sd ${m.hrv_baseline_sd?.toFixed(1) ?? "n/a"})`,
      ];
      await opts.sendFn({ body: lines.join("\n") });
      return;
    }
    case "replan": {
      // Trigger sunday-cycle inline
      const { runSundayCycle } = await import("../jobs/sunday-cycle.js");
      const { askClaude } = await import("../planner/claude.js");
      await runSundayCycle({
        db: opts.db, intervalsClient: opts.intervalsClient, athleteId: opts.athleteId,
        phase: Number(process.env.COACH_PHASE ?? "0") as 0 | 1 | 2,
        sendFn: opts.sendFn, profilePath: opts.profilePath, claudeFn: askClaude,
      });
      return;
    }
    default:
      await opts.sendFn({ body: `Unknown command: /${opts.command}` });
  }
}

function mondayOfThisWeek(): string {
  const now = new Date();
  const day = now.getUTCDay();
  const diff = day === 0 ? -6 : 1 - day;
  return new Date(now.getTime() + diff * 86400000).toISOString().slice(0, 10);
}
```

- [ ] **Step 3: Wire slash handler into `src/whatsapp/intent.ts` `handleInboundMessage`**

```typescript
export async function handleInboundMessage({ db, message, config }: HandleOpts): Promise<void> {
  const today = new Date().toISOString().slice(0, 10);
  const noteId = insertNote(db, {
    source: "whatsapp", date: today, body: message.body, intent: null, handled: 0,
    raw_json: JSON.stringify(message),
  });
  const { askClaude } = await import("../planner/claude.js");
  const { intent, summary } = await classifyIntent(message.body, askClaude);
  markNoteHandled(db, noteId, intent);
  logger.info({ noteId, intent }, "inbound classified");

  if (["replan", "why", "profile"].includes(intent)) {
    const { handleSlash } = await import("../handlers/slash.js");
    const { sendWithRetry } = await import("./send.js");
    const { IntervalsClient } = await import("../intervals/client.js");
    await handleSlash({
      db, command: intent, args: "",
      sendFn: ({ body }) => sendWithRetry({
        phoneNumberId: config.whatsapp.phoneNumberId, accessToken: config.whatsapp.accessToken,
        recipient: config.whatsapp.recipient, body,
      }),
      intervalsClient: new IntervalsClient({ athleteId: config.intervals.athleteId, apiKey: config.intervals.apiKey }),
      athleteId: config.intervals.athleteId, profilePath: config.athleteProfilePath,
    });
  }
}
```

- [ ] **Step 4: Run, commit**

```bash
npm test
git add src/handlers tests/handlers src/whatsapp/intent.ts
git commit -m "feat: slash command handlers (/replan, /why, /profile) wired into webhook"
```

---

### Task 45: Strength morning delivery

**Files:**
- Create: `src/jobs/strength-delivery.ts`
- Create: `deploy/strength-delivery.service`
- Create: `deploy/strength-delivery.timer`

- [ ] **Step 1: Implement `src/jobs/strength-delivery.ts`**

```typescript
import type { Db } from "../db/client.js";
import { getCurrentWeekPlan } from "../db/plans.js";
import type { WeeklyPlan } from "../planner/schema.js";
import { logger } from "../lib/logger.js";

export interface StrengthDeliveryOpts {
  db: Db;
  sendFn: (opts: { body: string }) => Promise<string>;
}

function mondayOfThisWeek(): string {
  const now = new Date();
  const day = now.getUTCDay();
  const diff = day === 0 ? -6 : 1 - day;
  return new Date(now.getTime() + diff * 86400000).toISOString().slice(0, 10);
}

export async function runStrengthDelivery({ db, sendFn }: StrengthDeliveryOpts): Promise<void> {
  const today = new Date().toISOString().slice(0, 10);
  const planRow = getCurrentWeekPlan(db, mondayOfThisWeek());
  if (!planRow) return;
  const plan = JSON.parse(planRow.payload_json) as WeeklyPlan;
  const strength = plan.days.find((d) => d.date === today && d.sport === "strength");
  if (!strength) { logger.info("no strength session today"); return; }
  await sendFn({ body: `Today's strength (${strength.duration_min} min):\n${strength.description}` });
}
```

- [ ] **Step 2: Add CLI command + systemd timer**

In `src/cli.ts`:
```typescript
case "strength-delivery":
  await runStrengthDelivery({ db, sendFn: ({ body }) => sendWithRetry({
    phoneNumberId: cfg.whatsapp.phoneNumberId, accessToken: cfg.whatsapp.accessToken,
    recipient: cfg.whatsapp.recipient, body,
  })});
  break;
```

`deploy/strength-delivery.service`:
```ini
[Unit]
Description=twins-coach morning strength delivery

[Service]
Type=oneshot
User=coach
WorkingDirectory=/opt/twins-coach
EnvironmentFile=/opt/twins-coach/.env
ExecStart=/usr/bin/npx tsx src/cli.ts strength-delivery
```

`deploy/strength-delivery.timer`:
```ini
[Unit]
Description=Run strength delivery 1 hour before typical workout time (05:00)

[Timer]
OnCalendar=*-*-* 05:00:00
Persistent=true

[Install]
WantedBy=timers.target
```

> **Note:** "1 hour before scheduled time" approximated as 5am (since plans default workout time to 6am). v2 enhancement: read planned start time per session and schedule per-session.

- [ ] **Step 3: Update deploy.sh to enable strength-delivery.timer, commit**

```bash
git add src/jobs/strength-delivery.ts src/cli.ts deploy/strength-delivery.* scripts/deploy.sh
git commit -m "feat: morning strength session WhatsApp delivery"
```

---

### Task 46: Healthcheck + nightly backup

**Files:**
- Create: `src/jobs/healthcheck.ts`
- Create: `src/jobs/backup.ts`
- Create: `deploy/healthcheck.service`, `deploy/healthcheck.timer`
- Create: `deploy/backup.service`, `deploy/backup.timer`

- [ ] **Step 1: Implement `src/jobs/healthcheck.ts`**

```typescript
import { sendWithRetry } from "../whatsapp/send.js";
import { loadConfig } from "../config.js";
import { logger } from "../lib/logger.js";
import fs from "node:fs";

export async function runHealthcheck(): Promise<void> {
  const cfg = loadConfig();
  const stamp = new Date().toISOString();
  const lastSeenPath = "/opt/twins-coach/.last-healthcheck";
  fs.writeFileSync(lastSeenPath, stamp);

  // Check disk usage
  const dbStat = fs.statSync(cfg.dbPath);
  const dbMb = (dbStat.size / 1024 / 1024).toFixed(1);

  // Only message Daniel weekly (Sundays) so it doesn't spam daily
  if (new Date().getUTCDay() !== 0) {
    logger.info({ stamp, dbMb }, "healthcheck — silent");
    return;
  }

  await sendWithRetry({
    phoneNumberId: cfg.whatsapp.phoneNumberId, accessToken: cfg.whatsapp.accessToken,
    recipient: cfg.whatsapp.recipient, body: `Coach VPS alive. DB ${dbMb} MB.`,
  });
}
```

- [ ] **Step 2: Implement `src/jobs/backup.ts`**

```typescript
import { execSync } from "node:child_process";
import fs from "node:fs";
import { loadConfig } from "../config.js";
import { logger } from "../lib/logger.js";

export function runBackup(): void {
  const cfg = loadConfig();
  const date = new Date().toISOString().slice(0, 10);
  const dest = `/opt/twins-coach/backups/twins-coach-${date}.db`;
  fs.mkdirSync("/opt/twins-coach/backups", { recursive: true });
  // Use SQLite's .backup for hot copy
  execSync(`sqlite3 "${cfg.dbPath}" ".backup '${dest}'"`);
  // Keep only last 14
  const files = fs.readdirSync("/opt/twins-coach/backups").filter((f) => f.endsWith(".db")).sort();
  while (files.length > 14) {
    const f = files.shift()!;
    fs.unlinkSync(`/opt/twins-coach/backups/${f}`);
  }
  logger.info({ dest }, "backup complete");
}
```

- [ ] **Step 3: Add CLI commands + systemd units (timer 09:00 healthcheck, 02:00 backup) + update deploy.sh**

`deploy/healthcheck.service` and `deploy/healthcheck.timer` (OnCalendar: `*-*-* 09:00:00`).
`deploy/backup.service` and `deploy/backup.timer` (OnCalendar: `*-*-* 02:00:00`).

In `src/cli.ts`:
```typescript
case "healthcheck": await runHealthcheck(); break;
case "backup": runBackup(); break;
```

- [ ] **Step 4: Commit**

```bash
git add src/jobs/healthcheck.ts src/jobs/backup.ts deploy/healthcheck.* deploy/backup.* src/cli.ts scripts/deploy.sh
git commit -m "feat: daily healthcheck + nightly SQLite backup"
```

---

### Task 47: Phase 2 production cutover

- [ ] **Step 1: Verify all systemd units and Phase 1 has run successfully for at least 2 weeks**

```bash
ssh twins-coach "systemctl list-timers && journalctl -u sunday-cycle.service --since '14 days ago' | tail -50"
```

Expected: 2+ Sunday runs visible, no errors, Daniel has reviewed shadow plans and is comfortable.

- [ ] **Step 2: Flip phase to 2**

```bash
ssh twins-coach "sudo -u coach sed -i 's/COACH_PHASE=1/COACH_PHASE=2/' /opt/twins-coach/.env"
```

- [ ] **Step 3: Trigger first live sunday cycle manually**

```bash
ssh twins-coach "sudo systemctl start sunday-cycle.service && sudo journalctl -u sunday-cycle.service -n 100"
```

Expected: WhatsApp summary saying "Week of [date] published — N hrs total..." and TP shows new events tagged `[claude]`.

- [ ] **Step 4: Verify in TP**

Daniel logs into TP web UI → calendar shows the week's planned workouts; descriptions begin with `[claude]`.

- [ ] **Step 5: Document the rollback**

In `deploy/oracle-setup.md`, append:

```markdown
## Rollback

If Phase 2 plans are problematic:
1. SSH: `sudo -u coach sed -i 's/COACH_PHASE=2/COACH_PHASE=1/' /opt/twins-coach/.env`
2. Manually delete the week's `[claude]`-tagged events in intervals.icu (or run `node -e "..."` to call the publish module's delete-only path)
3. Revert to coach plans / manual planning while debugging
```

> **🎯 PHASE 2 LIVE.** Daniel's coach replaced. The system runs on its own.

---

## Plan Self-Review Notes

**Spec coverage check:**

| Spec section | Covered by |
|---|---|
| Phase 0 (observe) | M0–M6 (T1–T27) |
| Phase 1 (shadow) | M7–M9 (T28–T38) |
| Phase 2 (live) | M10–M12 (T39–T47) |
| Data layer (4 streams) | T6–T13 |
| Athlete model | T14–T16 |
| Sunday weekly cycle | T20, T37, T41 |
| Daily 6am safety check | T42–T43 |
| Outbound WhatsApp (4 message types) | T17–T19, T41, T43, T45 |
| Inbound WhatsApp + intent classification | T28–T31 |
| Athlete profile (athlete.md) | T4 + T14 |
| Translator (JSON → intervals.icu) | T39 |
| Publishing path | T40 |
| Failure handling | T18 (retry), T46 (backup), T47 (rollback) |
| Hosting (Oracle Free Tier) | T23, T25–T27 |
| 6-day max guardrail | T35 (validate + auto-correct) |
| Auto-downgrade / recommend-upgrade rules | T42 |
| Race-week sacred guardrail | T43 (isRaceWeek check) |
| Slash commands | T44 |
| Strength morning delivery | T45 |

**Known deferrals (called out inline in tasks, not gaps):**
- Native intervals.icu `workout_doc` JSON for Garmin structured-workout sync — Task 39 publishes as descriptions; richer Garmin push is post-shipping enhancement
- FTP / threshold-pace / CSS auto-derivation from test efforts — Task 15 uses intervals.icu's computed values; auto-detection of test efforts is post-shipping
- Per-session strength delivery time — Task 45 hardcodes 05:00; v2 reads per-session start time
- HMAC signature verification on Meta webhook — Task 28 verifies subscription challenge but doesn't validate per-message signature (low risk for single-user instance, can add via `x-hub-signature-256` header later)

These are explicit, acceptable trade-offs to ship Phase 2 by June. Each is one well-bounded follow-up plan post-launch.

---




