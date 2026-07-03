# Marketing OS First Cycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Twins Marketing OS: data-source map, real-data baseline, strategy one-pager, ranked backlog, reusable Monday-brief generator, and the Monday schedule.

**Architecture:** Everything lives in the outer repo (`/Users/daniel/twins-dashboard`) under `docs/marketing/`, plus one repo skill in `.claude/skills/marketing-brief/`. All numbers come read-only from the jwrpj Supabase project, mirroring the canonical logic in `twins-dash/src/hooks/use-marketing-source-roi.ts` (KPI math is immutable — we read, never reinvent). No dashboard code changes in this cycle.

**Tech Stack:** Markdown docs, Supabase MCP `execute_sql` (read-only SELECTs against jwrpj), Claude Code repo skill, scheduled task for Monday cadence.

**Spec:** `docs/superpowers/specs/2026-07-03-cmo-marketing-os-design.md`

**Standing guardrails (apply to every task):** real data only — if a query returns nothing, write "no data" rather than inventing numbers; full dollar amounts ($5,243, never $5k); earned revenue = `outstanding_balance == 0`; Charles co-tech attribution rule untouched (we never recompute per-tech numbers here); no Lovable references; read-only SQL only (SELECT; no INSERT/UPDATE/DDL).

---

### Task 1: Data source map (`docs/marketing/DATA-SOURCES.md`)

**Files:**
- Create: `docs/marketing/DATA-SOURCES.md`
- Read: `twins-dash/src/hooks/use-marketing-source-roi.ts`, `twins-dash/src/hooks/use-dashboard-data.ts`, `twins-dash/src/hooks/use-ghl-summary.ts`

- [ ] **Step 1: Extract the canonical source mapping**

Run:
```bash
grep -n "PLATFORM_TO_CANONICAL" -A 20 /Users/daniel/twins-dashboard/twins-dash/src/hooks/use-marketing-source-roi.ts
grep -n "lead_source\|source" /Users/daniel/twins-dashboard/twins-dash/src/hooks/use-dashboard-data.ts | head -20
```
Record: the platform→canonical map and which job column carries lead source.

- [ ] **Step 2: Confirm live tables and row coverage**

Via Supabase MCP `execute_sql` on project jwrpj (read-only):
```sql
SELECT platform, MIN(date) AS first, MAX(date) AS last, COUNT(*) AS rows
FROM marketing_spend GROUP BY platform ORDER BY platform;
```
And list marketing-relevant tables:
```sql
SELECT table_name FROM information_schema.tables
WHERE table_schema='public' AND (
  table_name ILIKE '%marketing%' OR table_name ILIKE '%review%'
  OR table_name ILIKE '%ghl%' OR table_name ILIKE '%lead%');
```
Expected: `marketing_spend` shows LSA (and possibly google_ads) rows through recent dates; review-card click table appears (built 2026-06-23).

- [ ] **Step 3: Write DATA-SOURCES.md**

Document, for each metric the Monday brief needs: metric name, table(s), exact SQL, canonical-logic file it mirrors, and known gaps (GHL attribution, funnel booked semantics — carried from the ROI polish backlog). Sections: Spend by channel; Jobs + earned revenue by source; Review velocity (review-card clicks + Google review counts if available); GHL lead summary; Content output (twins-content-engine `approved/` dir listing, not DB).

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard
git add docs/marketing/DATA-SOURCES.md
git commit -m "docs(marketing): data source map for Monday brief"
```

### Task 2: Baseline pull (`docs/marketing/baselines/2026-07-03-baseline.md`)

**Files:**
- Create: `docs/marketing/baselines/2026-07-03-baseline.md`
- Depends on: Task 1's DATA-SOURCES.md queries

- [ ] **Step 1: Run baseline queries for two windows — last 30 days (2026-06-03..2026-07-02) and MTD July**

Spend (exact query, columns confirmed from the hook):
```sql
SELECT platform, SUM(spend_amount)::numeric(12,2) AS spend,
       SUM(clicks) AS clicks, SUM(leads_generated) AS leads
FROM marketing_spend
WHERE date BETWEEN '2026-06-03' AND '2026-07-02'
GROUP BY platform ORDER BY spend DESC;
```
Jobs + earned revenue by source, review velocity, GHL summary: run the exact SQL recorded in DATA-SOURCES.md for the same windows.

- [ ] **Step 2: Sanity-check against the live ROI page**

Cross-check one number (e.g., LSA spend last 30 days) against `MarketingSourceROI.tsx` reconciliation expectations: totals must be in the same ballpark as the dashboard. If a query disagrees wildly with the dashboard, STOP and reconcile before writing the baseline (do not publish two versions of truth).

- [ ] **Step 3: Write the baseline doc**

Table per metric, both windows, with an "unattributed" row where source data lacks a structured tag (never guess from free text). Note data gaps explicitly.

- [ ] **Step 4: Commit**

```bash
git add docs/marketing/baselines/2026-07-03-baseline.md
git commit -m "docs(marketing): 2026-07-03 baseline — spend, revenue by source, reviews"
```

### Task 3: Strategy one-pager (`docs/marketing/STRATEGY.md`)

**Files:**
- Create: `docs/marketing/STRATEGY.md`

- [ ] **Step 1: Write STRATEGY.md** with exactly these sections (content from spec §2 + Task 2 baseline):
  - **Positioning:** family-owned twin-brothers company, Madison WI; real techs (bios exist for Charles, Maurice, Nicholas); honest fast service. Contact: real Twins 608 local number and twinsgaragedoors.com only (never the 833 van number for local ads — known mismatch).
  - **Confirmed offers:** $0 service call, $49 tune-up, GoodLeap financing. Never promise same-day.
  - **Segments:** emergency repair / planned replacement / maintenance, each mapped to its portfolio bucket.
  - **Channel ranking:** ranked by baseline ROI numbers from Task 2, not theory. Channels with no data rank "unproven", listed below proven ones.
  - **Budget rule:** ROI-driven; every new paid initiative needs a test cap + kill/scale criterion approved by Daniel.
  - **Authority matrix:** copy the table from spec §1 verbatim.

- [ ] **Step 2: Verify no fabricated facts** — every number traces to the baseline doc; every offer/phone/name checks against `reference_external_systems` facts. No em-dashes if any copy is customer-facing (internal doc: fine).

- [ ] **Step 3: Commit**

```bash
git add docs/marketing/STRATEGY.md
git commit -m "docs(marketing): strategy one-pager seeded with 2026-07-03 baseline"
```

### Task 4: Ranked backlog (`docs/marketing/BACKLOG.md`)

**Files:**
- Create: `docs/marketing/BACKLOG.md`

- [ ] **Step 1: Write BACKLOG.md** — table: rank, initiative, bucket (Capture/Compound/Base), status, next action, approval needed. Seed rows (from spec §5):
  1. Finish $49 tune-up avatar ad — Capture — build in progress — resume build — creative sign-off.
  2. Launch GHL messaging Phase 1 — Base — spec approved v3.1 — build bridge — copy sign-off before any send.
  3. ROI attribution gaps (GHL attribution, booked semantics, GA4) — Measurement — backlog exists — spec next item.
  4. Google Ads pilot — Capture — new — proposal w/ test cap in a Monday brief.
  5. Open estimates CSR tracker — Base — spec+plan exist — schedule build.
  6. Meta ads pilot w/ media-generator creative — Capture — new — proposal w/ test cap.

- [ ] **Step 2: Commit**

```bash
git add docs/marketing/BACKLOG.md
git commit -m "docs(marketing): ranked initiative backlog"
```

### Task 5: Monday-brief generator skill

**Files:**
- Create: `.claude/skills/marketing-brief/SKILL.md`

- [ ] **Step 1: Write SKILL.md** with frontmatter (`name: marketing-brief`, description triggering on "marketing brief", "Monday brief") and body containing: (a) the exact queries by reference to `docs/marketing/DATA-SOURCES.md`; (b) the brief template — What ran / Spend by channel / Booked jobs + earned revenue by source / Reviews & content output / 2–3 proposed moves with cost + expected effect + kill criterion; (c) guardrails — real data only, full dollar amounts, unattributed stays unattributed, proposals never self-execute, brief is chat-only (no email/SMS/push).

- [ ] **Step 2: Verify the skill loads** — run `ls .claude/skills/marketing-brief/SKILL.md` and confirm frontmatter parses (name + description present).

- [ ] **Step 3: Commit**

```bash
git add .claude/skills/marketing-brief/SKILL.md
git commit -m "feat(marketing): /marketing-brief skill — weekly brief generator"
```

### Task 6: Monday cadence

**Files:**
- None (scheduler state) + Modify: `docs/marketing/STRATEGY.md` (append "Cadence" section documenting the mechanism)

- [ ] **Step 1: Create the schedule** — a scheduled task running Mondays 08:00 America/New_York (Daniel is Eastern) with prompt: "Run /marketing-brief for last week (Fri–Thu payroll week + calendar week spend) and deliver the brief in chat." Use the schedule skill / scheduled-tasks MCP.

- [ ] **Step 2: Verify** — list scheduled tasks; confirm the Monday routine exists with correct cron + timezone.

- [ ] **Step 3: Document fallback** — if scheduled/headless runs can't reach jwrpj (MCP auth absent headless), the documented fallback is Daniel typing `/marketing-brief` Monday morning; note whichever mechanism was verified in STRATEGY.md "Cadence" section.

- [ ] **Step 4: Commit**

```bash
git add docs/marketing/STRATEGY.md
git commit -m "docs(marketing): cadence mechanism + fallback"
```

### Task 7: Week-1 baseline brief (deliverable)

- [ ] **Step 1: Generate the brief** using the Task 5 skill against the Task 2 baseline: last-30-day + MTD numbers, plus 2–3 concrete proposed moves, each with cost, expected effect, and kill criterion. Proposals must only draw from BACKLOG.md items or explicitly new ideas flagged as new.

- [ ] **Step 2: Deliver in chat** as the final message of the cycle — this is the mini-audit and the first approve/reject decision point for Daniel. No spend, no sends, no campaign changes until he answers.

---

## Self-review

- **Spec coverage:** spec §1 authority → Tasks 3/5 (matrix + guardrails); §2 strategy → Task 3; §3 portfolio → Tasks 3/4; §4 rhythm → Tasks 5/6/7; §5 backlog → Task 4; §6 data → Tasks 1/2. Implementation scope items 1–5 all mapped. ✓
- **Placeholders:** Task 2 jobs-by-source SQL intentionally defers to DATA-SOURCES.md written in Task 1 (real dependency, discovery-then-use; the spend SQL is fully specified). No TBDs. ✓
- **Consistency:** file paths and doc names match across tasks; windows (2026-06-03..2026-07-02, MTD July) consistent between Tasks 2 and 7. ✓
