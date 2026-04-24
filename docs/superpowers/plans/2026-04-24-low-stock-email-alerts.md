# Low-Stock Email Alerts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Email admins + opted-in users when a tracked part drops below its reorder threshold, with send-once semantics per crossing and a weekly Monday-morning digest as a reminder for anything still low.

**Architecture:** Extend `payroll_parts_prices` with `last_low_alert_at`. Extend `finalize_payroll_run` RPC to detect "newly low" parts and return them to the client, which fire-and-forgets an edge function. A second edge function runs weekly via `pg_cron` to email anything currently below threshold. Both emails use the existing Resend-based `_shared/email/send.ts` pipeline with a new React-Email template. Per-user opt-in rides the existing `user_roles.permissions.low_stock_alerts` flag already surfaced in Admin → User Management.

**Tech Stack:** Supabase Postgres 15 + `pg_cron` + `pg_net`; Deno edge functions using `@react-email/components` via `esm.sh`; Resend (existing `_shared/email/send.ts`); React + TypeScript client in `twins-dash-payroll-work`; Vitest for pure-helper tests. Migrations applied via `npx supabase db push` against project ref `jwrpjuqaynownxaoeayi`. Work in worktree `/Users/daniel/twins-dashboard/twins-dash-payroll-work` on branch `feature/payroll-draft-sync`.

**Source spec:** [docs/superpowers/specs/2026-04-24-low-stock-email-alerts-design.md](../specs/2026-04-24-low-stock-email-alerts-design.md)

---

## File Structure

**Create:**
- `supabase/migrations/20260425100000_low_stock_alerts_schema.sql` — `last_low_alert_at` column, recovery trigger, `ALTER email_log.kind CHECK` to allow `low_stock_finalize` / `low_stock_weekly` / `low_stock_preview`.
- `supabase/migrations/20260425100100_finalize_payroll_run_v2.sql` — `CREATE OR REPLACE` of the existing RPC extended to detect newly-low parts and return them.
- `supabase/migrations/20260425100200_low_stock_weekly_cron.sql` — `cron.schedule(...)` for the weekly digest.
- `supabase/functions/_shared/email/lowStock.ts` — pure helpers: `detectNewlyLow`, `formatShortBy`, row shaping.
- `supabase/functions/_shared/email/LowStockAlert.tsx` — React-Email template.
- `supabase/functions/_shared/email/renderLowStock.ts` — `render(<LowStockAlert .../>)` wrapper mirroring `render.tsx`.
- `supabase/functions/_shared/email/__tests__/lowStock.test.ts` — Vitest unit tests.
- `supabase/functions/invoke-low-stock-email/index.ts` — handles `kind: "finalize" | "preview"`.
- `supabase/functions/cron-weekly-lowstock/index.ts` — weekly digest cron.

**Modify:**
- `src/pages/payroll/Run.tsx` — after `finalize_payroll_run` RPC success, fire-and-forget `supabase.functions.invoke("invoke-low-stock-email", ...)` when `newly_low.length > 0`.
- `src/pages/payroll/Parts.tsx` — admin-only "Preview low-stock email" button → `invoke("invoke-low-stock-email", { body: { kind: "preview" }})`.
- `src/components/admin/AdminUserManagement.tsx` — add `{ key: 'low_stock_alerts', label: 'Low-stock alerts' }` to the `KNOWN_PERMISSIONS` list.

**Untouched:**
- Existing `_shared/email/send.ts`, `email_settings`, `email_log` schemas (only the kind CHECK changes).

---

## Task 1: Schema migration — `last_low_alert_at` + recovery trigger + email_log kind

**Files:**
- Create: `supabase/migrations/20260425100000_low_stock_alerts_schema.sql`

**Context:** Three DDL changes. One column on `payroll_parts_prices`. One `BEFORE UPDATE` trigger that clears the column when stock recovers. ALTER the `email_log.kind` CHECK constraint to allow three new values.

- [ ] **Step 1: Create the migration file**

Write `supabase/migrations/20260425100000_low_stock_alerts_schema.sql`:

```sql
-- Adds last_low_alert_at to payroll_parts_prices for send-once de-dupe,
-- a trigger that clears it when stock recovers, and extends
-- email_log.kind to allow the three low-stock alert kinds.

ALTER TABLE public.payroll_parts_prices
  ADD COLUMN IF NOT EXISTS last_low_alert_at TIMESTAMPTZ;

-- Clear last_low_alert_at whenever on_hand rises back to or above min_stock.
-- This re-arms the Finalize alert so the next drop will email again.
CREATE OR REPLACE FUNCTION public.clear_low_alert_on_recovery()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
  IF OLD.last_low_alert_at IS NOT NULL
     AND NEW.on_hand >= NEW.min_stock
     AND NEW.min_stock > 0
  THEN
    NEW.last_low_alert_at := NULL;
  END IF;
  RETURN NEW;
END $$;

DROP TRIGGER IF EXISTS payroll_parts_clear_low_alert ON public.payroll_parts_prices;
CREATE TRIGGER payroll_parts_clear_low_alert
BEFORE UPDATE ON public.payroll_parts_prices
FOR EACH ROW EXECUTE FUNCTION public.clear_low_alert_on_recovery();

-- Extend email_log.kind CHECK to allow the new alert kinds.
ALTER TABLE public.email_log DROP CONSTRAINT IF EXISTS email_log_kind_check;
ALTER TABLE public.email_log
  ADD CONSTRAINT email_log_kind_check CHECK (kind IN (
    'daily','weekly','preview',
    'low_stock_finalize','low_stock_weekly','low_stock_preview'
  ));
```

- [ ] **Step 2: Apply via `npx supabase db push`**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx supabase db push
```

If the CLI complains about remote drift, run `npx supabase migration repair --status reverted <id>` for each listed id and retry. MCP `apply_migration` has no access to this project — use the CLI only.

- [ ] **Step 3: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add supabase/migrations/20260425100000_low_stock_alerts_schema.sql
git commit -m "$(cat <<'EOF'
feat(payroll): low_stock_alerts schema — last_low_alert_at + recovery trigger

- payroll_parts_prices.last_low_alert_at TIMESTAMPTZ: timestamp of the
  most recent Finalize-triggered alert. Cleared by trigger when stock
  recovers to >= min_stock so the next drop re-arms an alert.
- email_log.kind CHECK extended with low_stock_finalize,
  low_stock_weekly, low_stock_preview.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Extend `finalize_payroll_run` RPC

**Files:**
- Create: `supabase/migrations/20260425100100_finalize_payroll_run_v2.sql`

**Context:** `CREATE OR REPLACE FUNCTION` overrides the existing RPC in-place. New return shape adds `newly_low` as an array; existing `status`, `stock_updates`, `skipped_unmatched` keys stay so the current client keeps working. The RPC now also sets `last_low_alert_at = NOW()` on each newly-low row.

A part is "newly low" if ALL of:
- `pp.track_inventory = TRUE AND pp.is_one_time = FALSE`
- `pp.min_stock > 0`
- pre-update `on_hand >= min_stock`
- post-update `on_hand < min_stock`
- `last_low_alert_at IS NULL` (not already alerted since last recovery)

- [ ] **Step 1: Create the migration file**

Write `supabase/migrations/20260425100100_finalize_payroll_run_v2.sql`:

> **Note on approach:** the stamp of `last_low_alert_at` is a standalone UPDATE statement after the CTE (not a CTE itself). Postgres only executes CTEs whose output is in the final SELECT, and wrapping the stamp as a CTE risks it being optimized away. A separate statement guarantees the stamp runs.

```sql
CREATE OR REPLACE FUNCTION public.finalize_payroll_run(p_run_id INT)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path = public
AS $$
DECLARE
  v_status   TEXT;
  v_updated  INT := 0;
  v_skipped  INT := 0;
  v_newly    JSONB := '[]'::JSONB;
BEGIN
  SELECT status INTO v_status
    FROM public.payroll_runs
    WHERE id = p_run_id
    FOR UPDATE;
  IF v_status IS NULL THEN
    RAISE EXCEPTION 'Run % not found', p_run_id;
  END IF;
  IF v_status <> 'in_progress' THEN
    RAISE EXCEPTION 'Run % is % (must be in_progress)', p_run_id, v_status;
  END IF;

  UPDATE public.payroll_runs SET status = 'final' WHERE id = p_run_id;

  WITH consumed AS (
    SELECT LOWER(TRIM(jp.part_name)) AS key, SUM(jp.quantity) AS qty
      FROM public.payroll_job_parts jp
      JOIN public.payroll_jobs j ON jp.job_id = j.id
     WHERE j.run_id = p_run_id
     GROUP BY LOWER(TRIM(jp.part_name))
  ),
  before_snapshot AS (
    SELECT pp.id, pp.name, pp.on_hand AS before_on_hand, pp.min_stock,
           pp.last_low_alert_at, c.qty
      FROM public.payroll_parts_prices pp
      JOIN consumed c ON LOWER(TRIM(pp.name)) = c.key
     WHERE pp.track_inventory = TRUE AND pp.is_one_time = FALSE
  ),
  updated AS (
    UPDATE public.payroll_parts_prices pp
       SET on_hand = pp.on_hand - bs.qty
      FROM before_snapshot bs
     WHERE pp.id = bs.id
     RETURNING pp.id, pp.name, bs.before_on_hand, pp.on_hand AS after_on_hand,
               pp.min_stock, bs.last_low_alert_at
  )
  SELECT
    (SELECT COUNT(*) FROM updated),
    (SELECT COUNT(*) FROM consumed) - (SELECT COUNT(*) FROM updated),
    COALESCE((
      SELECT jsonb_agg(jsonb_build_object(
        'id', u.id, 'name', u.name, 'on_hand', u.after_on_hand,
        'min_stock', u.min_stock, 'short_by', (u.min_stock - u.after_on_hand)::NUMERIC
      ))
        FROM updated u
       WHERE u.min_stock > 0
         AND u.before_on_hand >= u.min_stock
         AND u.after_on_hand < u.min_stock
         AND u.last_low_alert_at IS NULL
    ), '[]'::JSONB)
  INTO v_updated, v_skipped, v_newly;

  -- Stamp last_low_alert_at on each newly-low part so we don't re-alert
  -- on the next Finalize while the part remains below threshold. The
  -- recovery trigger clears this when stock goes back up.
  UPDATE public.payroll_parts_prices pp
     SET last_low_alert_at = NOW()
   WHERE pp.id IN (
     SELECT (value->>'id')::INT FROM jsonb_array_elements(v_newly)
   );

  RETURN jsonb_build_object(
    'status',            'final',
    'stock_updates',     v_updated,
    'skipped_unmatched', v_skipped,
    'newly_low',         v_newly
  );
END;
$$;

GRANT EXECUTE ON FUNCTION public.finalize_payroll_run(INT) TO authenticated;
```

- [ ] **Step 2: Apply via `npx supabase db push`**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx supabase db push
```

- [ ] **Step 3: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add supabase/migrations/20260425100100_finalize_payroll_run_v2.sql
git commit -m "$(cat <<'EOF'
feat(payroll): finalize_payroll_run returns newly_low + stamps alert ts

CREATE OR REPLACE of the existing RPC. New return key `newly_low` is a
JSONB array of parts that just crossed below min_stock during this
finalize. For each such part, last_low_alert_at is set to NOW() so
subsequent finalizes don't re-alert while the part stays low (cleared
automatically when stock recovers, via the trigger shipped in
20260425100000).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Pure helpers + Vitest

**Files:**
- Create: `supabase/functions/_shared/email/lowStock.ts`
- Create: `supabase/functions/_shared/email/__tests__/lowStock.test.ts`

**Context:** Two small pure functions the edge functions and the client both rely on. Deno-incompatible (Vitest-only) — NO `https://` imports in the lowStock.ts or its test. Keep it pure TS so both Deno (edge functions) and Node (Vitest) can consume it.

- [ ] **Step 1: Write the failing test first (TDD)**

Create `supabase/functions/_shared/email/__tests__/lowStock.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { detectNewlyLow, formatShortBy, shapeLowStockRows, type LowStockPart } from "../lowStock";

describe("detectNewlyLow", () => {
  it("fires when crossing from >= min_stock to < min_stock", () => {
    expect(detectNewlyLow({ before: 10, after: 4, min_stock: 5 })).toBe(true);
  });
  it("does not fire when already below before the change", () => {
    expect(detectNewlyLow({ before: 4, after: 3, min_stock: 5 })).toBe(false);
  });
  it("does not fire when min_stock is 0 (no threshold)", () => {
    expect(detectNewlyLow({ before: 10, after: 0, min_stock: 0 })).toBe(false);
  });
  it("does not fire when after stays at threshold", () => {
    expect(detectNewlyLow({ before: 10, after: 5, min_stock: 5 })).toBe(false);
  });
  it("does fire when crossing exactly below threshold (4.99 < 5)", () => {
    expect(detectNewlyLow({ before: 10, after: 4.99, min_stock: 5 })).toBe(true);
  });
});

describe("formatShortBy", () => {
  it("formats whole number without decimal", () => {
    expect(formatShortBy(5)).toBe("5");
  });
  it("formats fractional with 1 decimal", () => {
    expect(formatShortBy(2.5)).toBe("2.5");
  });
  it("zero renders as 0", () => {
    expect(formatShortBy(0)).toBe("0");
  });
});

describe("shapeLowStockRows", () => {
  it("sorts by short_by descending (biggest gaps first)", () => {
    const parts: LowStockPart[] = [
      { id: 1, name: "Titan rollers", on_hand: 8, min_stock: 10 },
      { id: 2, name: "8' Cables",     on_hand: 1, min_stock: 20 },
      { id: 3, name: "Drum",          on_hand: 4, min_stock: 10 },
    ];
    const rows = shapeLowStockRows(parts);
    expect(rows.map((r) => r.id)).toEqual([2, 3, 1]);
  });
  it("returns empty array when input is empty", () => {
    expect(shapeLowStockRows([])).toEqual([]);
  });
  it("computes short_by as min_stock minus on_hand", () => {
    const rows = shapeLowStockRows([{ id: 9, name: "X", on_hand: 3, min_stock: 10 }]);
    expect(rows[0].short_by).toBe(7);
  });
});
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx vitest run supabase/functions/_shared/email/__tests__/lowStock.test.ts
```

Expected: module-not-found error — `../lowStock` doesn't exist yet.

- [ ] **Step 3: Write the implementation**

Create `supabase/functions/_shared/email/lowStock.ts`:

```ts
// Pure TS helpers used by both the edge functions (Deno) and the
// Vitest test suite (Node). No external imports so both runtimes work.

export type LowStockPart = {
  id: number;
  name: string;
  on_hand: number;
  min_stock: number;
};

export type LowStockRow = LowStockPart & { short_by: number };

export function detectNewlyLow(args: { before: number; after: number; min_stock: number }): boolean {
  const { before, after, min_stock } = args;
  if (!(min_stock > 0)) return false;
  return before >= min_stock && after < min_stock;
}

export function formatShortBy(n: number): string {
  if (!Number.isFinite(n)) return "—";
  if (Number.isInteger(n)) return String(n);
  return n.toFixed(1);
}

export function shapeLowStockRows(parts: LowStockPart[]): LowStockRow[] {
  return parts
    .map((p) => ({
      ...p,
      short_by: Number((Number(p.min_stock) - Number(p.on_hand)).toFixed(2)),
    }))
    .sort((a, b) => b.short_by - a.short_by);
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx vitest run supabase/functions/_shared/email/__tests__/lowStock.test.ts
```

Expected: 11 tests pass.

- [ ] **Step 5: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add supabase/functions/_shared/email/lowStock.ts supabase/functions/_shared/email/__tests__/lowStock.test.ts
git commit -m "$(cat <<'EOF'
feat(payroll): pure helpers for low-stock alert emails

detectNewlyLow (threshold-crossing predicate), formatShortBy (display
helper), shapeLowStockRows (sorts by biggest gap first) — consumed by
invoke-low-stock-email and cron-weekly-lowstock edge functions plus
the email template. Zero external imports so Vitest can drive them.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Email template (React Email)

**Files:**
- Create: `supabase/functions/_shared/email/LowStockAlert.tsx`
- Create: `supabase/functions/_shared/email/renderLowStock.ts`

**Context:** Mirrors the existing `DailyDigest.tsx` / `render.tsx` pattern. Deno-side code, uses `https://esm.sh` imports.

- [ ] **Step 1: Create the template**

Write `supabase/functions/_shared/email/LowStockAlert.tsx`:

```tsx
// React-Email template for the low-stock alert. Rendered to HTML by
// the invoke-low-stock-email and cron-weekly-lowstock edge functions.

import React from "https://esm.sh/react@18.3.1";
import {
  Html, Head, Body, Preview, Container, Section, Text, Row, Column, Hr, Button,
} from "https://esm.sh/@react-email/components@0.0.22?deps=react@18.3.1";
import { BRAND, EmailHeader } from "./components.tsx";
import type { LowStockRow } from "./lowStock.ts";

export interface LowStockAlertProps {
  kind: "finalize" | "weekly" | "preview";
  rows: LowStockRow[];
  dateLabel: string;    // "Apr 24, 2026"
  dashboardUrl: string; // e.g. https://twinsdash.com/payroll/parts
}

const subtitleFor = (kind: LowStockAlertProps["kind"]): string => {
  if (kind === "finalize") return "Payroll just finalized";
  if (kind === "weekly") return "Weekly reminder";
  return "Preview — sample data";
};

const leadFor = (kind: LowStockAlertProps["kind"], dateLabel: string): string => {
  if (kind === "finalize") return `The week-of ${dateLabel} payroll just finalized. These parts dropped below their reorder threshold:`;
  if (kind === "weekly") return `These parts have been below reorder threshold as of ${dateLabel}. Reorder when you get a chance.`;
  return `Preview send — this is what a real alert looks like with a few sample parts.`;
};

export const LowStockAlert: React.FC<LowStockAlertProps> = ({ kind, rows, dateLabel, dashboardUrl }) => {
  const n = rows.length;
  const title = n === 1 ? "1 part below reorder threshold" : `${n} parts below reorder threshold`;
  return (
    <Html>
      <Head />
      <Preview>{title} — Twins Garage Doors</Preview>
      <Body style={{ background: BRAND.surface, margin: 0, padding: 0, fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" }}>
        <Container style={{ maxWidth: 640, margin: "0 auto" }}>
          <EmailHeader title={title} subtitle={subtitleFor(kind)} />
          <Section style={{ background: "#fff", padding: "20px 24px" }}>
            <Text style={{ fontSize: 14, color: BRAND.text, lineHeight: "20px" }}>
              {leadFor(kind, dateLabel)}
            </Text>
            <Hr style={{ border: "none", borderTop: `1px solid ${BRAND.line}`, margin: "12px 0" }} />
            <Row style={{ borderBottom: `1px solid ${BRAND.line}`, paddingBottom: 6 }}>
              <Column style={{ fontSize: 12, color: BRAND.muted, fontWeight: 600 }}>Part</Column>
              <Column align="right" style={{ fontSize: 12, color: BRAND.muted, fontWeight: 600 }}>On hand</Column>
              <Column align="right" style={{ fontSize: 12, color: BRAND.muted, fontWeight: 600 }}>Min</Column>
              <Column align="right" style={{ fontSize: 12, color: BRAND.muted, fontWeight: 600 }}>Short by</Column>
            </Row>
            {rows.map((r) => (
              <Row key={r.id} style={{ borderBottom: `1px solid ${BRAND.line}`, padding: "8px 0" }}>
                <Column style={{ fontSize: 13, color: BRAND.text }}>{r.name}</Column>
                <Column align="right" style={{ fontSize: 13, color: BRAND.text, fontVariantNumeric: "tabular-nums" }}>{r.on_hand}</Column>
                <Column align="right" style={{ fontSize: 13, color: BRAND.text, fontVariantNumeric: "tabular-nums" }}>{r.min_stock}</Column>
                <Column align="right" style={{ fontSize: 13, color: BRAND.red, fontWeight: 600, fontVariantNumeric: "tabular-nums" }}>{r.short_by}</Column>
              </Row>
            ))}
            <Section style={{ textAlign: "center", padding: "20px 0 8px" }}>
              <Button href={dashboardUrl} style={{ background: BRAND.navy, color: "#fff", padding: "10px 20px", borderRadius: 6, textDecoration: "none", fontWeight: 600, fontSize: 13 }}>
                Open Parts Library
              </Button>
            </Section>
          </Section>
          <Section style={{ padding: "14px 24px", textAlign: "center" }}>
            <Text style={{ fontSize: 11, color: BRAND.muted, margin: 0 }}>
              Twins Garage Doors · Inventory alert
            </Text>
          </Section>
        </Container>
      </Body>
    </Html>
  );
};
```

- [ ] **Step 2: Create the render wrapper**

Write `supabase/functions/_shared/email/renderLowStock.ts`:

```ts
// Mirror of render.tsx: wraps @react-email/render for the low-stock
// template so edge functions don't import React directly.

import React from "https://esm.sh/react@18.3.1";
import { render } from "https://esm.sh/@react-email/render@0.0.17?deps=react@18.3.1";
import { LowStockAlert, type LowStockAlertProps } from "./LowStockAlert.tsx";

export function renderLowStockAlert(props: LowStockAlertProps): { html: string; text: string } {
  const element = React.createElement(LowStockAlert, props);
  return {
    html: render(element, { pretty: false }),
    text: render(element, { plainText: true }),
  };
}
```

- [ ] **Step 3: Typecheck only (Vitest won't run these Deno files; Deno deploy will pick them up at deploy time)**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx tsc --noEmit
```

Expected: Vitest's tsc project config excludes `supabase/functions/**` — should exit clean. If there are errors in those paths, they were pre-existing; report but don't fix unless your change introduced them.

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add supabase/functions/_shared/email/LowStockAlert.tsx supabase/functions/_shared/email/renderLowStock.ts
git commit -m "$(cat <<'EOF'
feat(payroll): React-Email template for low-stock alerts

LowStockAlert.tsx supports three kinds (finalize/weekly/preview) via
kind-specific lead copy. Navy/yellow header (via shared BRAND +
EmailHeader), parts table with On hand / Min / Short by columns,
"Open Parts Library" CTA. renderLowStock.ts wraps @react-email/render
mirroring the existing render.tsx pattern.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: `invoke-low-stock-email` edge function

**Files:**
- Create: `supabase/functions/invoke-low-stock-email/index.ts`

**Context:** Handles two `kind` values: `finalize` (called by the Run.tsx fire-and-forget) and `preview` (admin test send from Parts Library). Body shape:

```ts
// finalize
{ kind: "finalize", part_ids: number[] }
// preview
{ kind: "preview" }
```

In both cases: query recipients, render template, send via `sendEmail`, log to `email_log`.

- [ ] **Step 1: Create the edge function**

Write `supabase/functions/invoke-low-stock-email/index.ts`:

```ts
// deno-lint-ignore-file no-explicit-any
// Real-time low-stock email. Called fire-and-forget by Run.tsx after
// finalize_payroll_run returns a non-empty newly_low array, and by the
// Parts Library admin "Preview" button.

import { createClient } from "https://esm.sh/@supabase/supabase-js@2.45.0";
import { sendEmail } from "../_shared/email/send.ts";
import { renderLowStockAlert } from "../_shared/email/renderLowStock.ts";
import { shapeLowStockRows } from "../_shared/email/lowStock.ts";

const CORS = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, content-type, apikey",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
};

interface FinalizeBody { kind: "finalize"; part_ids: number[]; }
interface PreviewBody  { kind: "preview"; }
type Body = FinalizeBody | PreviewBody;

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response("ok", { headers: CORS });
  if (req.method !== "POST") return new Response("Method not allowed", { status: 405, headers: CORS });

  const SUPABASE_URL = Deno.env.get("SUPABASE_URL")!;
  const SERVICE_KEY  = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const RESEND_KEY   = Deno.env.get("RESEND_API_KEY")!;
  if (!SUPABASE_URL || !SERVICE_KEY || !RESEND_KEY) {
    return new Response(JSON.stringify({ error: "missing env" }), { status: 500, headers: CORS });
  }

  const sb = createClient(SUPABASE_URL, SERVICE_KEY, {
    auth: { persistSession: false, autoRefreshToken: false },
  });

  let body: Body;
  try { body = await req.json(); } catch { return new Response("bad json", { status: 400, headers: CORS }); }

  // 1. Shape the rows
  let rawParts: { id: number; name: string; on_hand: number; min_stock: number }[] = [];
  if (body.kind === "preview") {
    rawParts = [
      { id: -1, name: "Titan rollers",          on_hand: 3,  min_stock: 20 },
      { id: -2, name: "7' LiftMaster 8365 opener", on_hand: 0, min_stock: 2 },
      { id: -3, name: ".243 #2 - 41\"",         on_hand: 4,  min_stock: 10 },
    ];
  } else {
    const ids = Array.isArray(body.part_ids) ? body.part_ids : [];
    if (ids.length === 0) {
      return new Response(JSON.stringify({ status: "skipped_empty" }), { status: 200, headers: CORS });
    }
    const { data, error } = await sb
      .from("payroll_parts_prices")
      .select("id, name, on_hand, min_stock")
      .in("id", ids);
    if (error) return new Response(JSON.stringify({ error: error.message }), { status: 500, headers: CORS });
    rawParts = (data ?? []).map((p: any) => ({
      id: p.id, name: p.name, on_hand: Number(p.on_hand), min_stock: Number(p.min_stock),
    }));
  }
  const rows = shapeLowStockRows(rawParts);

  // 2. Resolve recipients: admins (auto) + non-admins with permissions.low_stock_alerts=true
  //    For preview: only the current caller (admin who clicked the button).
  let recipients: string[] = [];
  if (body.kind === "preview") {
    const authHeader = req.headers.get("Authorization") ?? "";
    const jwt = authHeader.replace(/^Bearer\s+/i, "");
    const { data: user } = await sb.auth.getUser(jwt);
    const email = user?.user?.email;
    if (!email) return new Response(JSON.stringify({ error: "no auth email" }), { status: 401, headers: CORS });
    recipients = [email];
  } else {
    const { data, error } = await sb.rpc("list_low_stock_alert_recipients");
    if (error) return new Response(JSON.stringify({ error: error.message }), { status: 500, headers: CORS });
    recipients = ((data ?? []) as { email: string }[]).map((r) => r.email);
  }
  if (recipients.length === 0) {
    return new Response(JSON.stringify({ status: "skipped_no_recipients" }), { status: 200, headers: CORS });
  }

  // 3. Load email_settings
  const { data: settings } = await sb.from("email_settings").select("*").eq("id", 1).single();
  if (!settings) return new Response(JSON.stringify({ error: "email_settings missing" }), { status: 500, headers: CORS });

  // 4. Render + send
  const kindLabel: "finalize" | "weekly" | "preview" = body.kind === "preview" ? "preview" : "finalize";
  const dateLabel = new Date().toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
  const subjectN = rows.length === 1 ? "1 part" : `${rows.length} parts`;
  const subject = body.kind === "preview"
    ? `[Preview] Low stock — ${subjectN}`
    : `Low stock after payroll — ${subjectN} need reorder`;
  const { html, text } = renderLowStockAlert({
    kind: kindLabel,
    rows,
    dateLabel,
    dashboardUrl: "https://twinsdash.com/payroll/parts",
  });

  const logKind = body.kind === "preview" ? "low_stock_preview" : "low_stock_finalize";
  const results: any[] = [];
  for (const to of recipients) {
    const result = await sendEmail({ settings, to, subject, html, text, apiKey: RESEND_KEY });
    await sb.from("email_log").insert({
      tech_id: null,
      kind: logKind,
      recipient: result.recipient ?? to,
      subject: result.subject ?? subject,
      dry_run: settings.dry_run,
      resend_id: result.resend_id ?? null,
      status: result.status,
      error: result.error ?? null,
    });
    results.push({ to, status: result.status });
  }

  return new Response(JSON.stringify({ status: "ok", sent: results }), {
    status: 200,
    headers: { ...CORS, "Content-Type": "application/json" },
  });
});
```

- [ ] **Step 2: Add the recipient RPC (referenced above)**

Append to a new migration file `supabase/migrations/20260425100150_low_stock_recipient_rpc.sql`:

```sql
-- Returns the set of email addresses that should receive low-stock alerts.
-- Admins (role='admin') are always included. Non-admins are included only
-- if their user_roles.permissions.low_stock_alerts flag is TRUE.

CREATE OR REPLACE FUNCTION public.list_low_stock_alert_recipients()
RETURNS TABLE (email TEXT)
LANGUAGE sql
SECURITY DEFINER
STABLE
SET search_path = public
AS $$
  SELECT DISTINCT u.email::TEXT
    FROM public.user_roles ur
    JOIN auth.users u ON u.id = ur.user_id
   WHERE (ur.role = 'admin'
      OR  (ur.permissions->>'low_stock_alerts')::BOOLEAN = TRUE)
     AND u.email IS NOT NULL;
$$;

GRANT EXECUTE ON FUNCTION public.list_low_stock_alert_recipients() TO service_role;
```

Apply:

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx supabase db push
```

- [ ] **Step 3: Deploy the edge function**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx supabase functions deploy invoke-low-stock-email --no-verify-jwt=false
```

The `--no-verify-jwt=false` is the default for payroll edge functions (verify_jwt=true) — check existing `supabase/config.toml` if the pattern differs. If the deploy step fails due to auth, add an entry under `[functions.invoke-low-stock-email]` in `config.toml` mirroring `[functions.email-send-preview]`.

- [ ] **Step 4: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add supabase/functions/invoke-low-stock-email/index.ts supabase/migrations/20260425100150_low_stock_recipient_rpc.sql
git commit -m "$(cat <<'EOF'
feat(payroll): invoke-low-stock-email edge function + recipient RPC

Edge function handles two kinds:
- finalize: given part_ids from the RPC's newly_low array, resolves
  parts + recipients, renders + sends one email per recipient.
- preview: sends a single email with synthetic data to the calling
  admin only — for testing from the Parts Library.

list_low_stock_alert_recipients RPC is the canonical recipient query
(admins auto + opt-ins). SECURITY DEFINER so the edge function can
call it with the service-role client. Every send is logged to
email_log with kind=low_stock_finalize or low_stock_preview.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: `cron-weekly-lowstock` edge function + schedule

**Files:**
- Create: `supabase/functions/cron-weekly-lowstock/index.ts`
- Create: `supabase/migrations/20260425100200_low_stock_weekly_cron.sql`

**Context:** Monday 07:00 America/Chicago (= 13:00 UTC in standard time, 12:00 UTC in DST; cron stored as UTC, pick `13:00` and accept one-hour drift in summer per the spec). Function scans for anything currently low and emails a digest. Does NOT touch `last_low_alert_at`.

- [ ] **Step 1: Create the cron edge function**

Write `supabase/functions/cron-weekly-lowstock/index.ts`:

```ts
// deno-lint-ignore-file no-explicit-any
// Weekly low-stock digest. Invoked by pg_cron every Monday 13:00 UTC.
// Self-gates on x-cron-secret header so only the scheduler can call it.

import { createClient } from "https://esm.sh/@supabase/supabase-js@2.45.0";
import { sendEmail } from "../_shared/email/send.ts";
import { renderLowStockAlert } from "../_shared/email/renderLowStock.ts";
import { shapeLowStockRows } from "../_shared/email/lowStock.ts";

Deno.serve(async (req) => {
  if (req.method !== "POST") return new Response("method not allowed", { status: 405 });

  const CRON_SECRET = Deno.env.get("EMAIL_CRON_SECRET")!;
  const got = req.headers.get("x-cron-secret") ?? "";
  if (!CRON_SECRET || got !== CRON_SECRET) {
    return new Response("forbidden", { status: 403 });
  }

  const SUPABASE_URL = Deno.env.get("SUPABASE_URL")!;
  const SERVICE_KEY  = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
  const RESEND_KEY   = Deno.env.get("RESEND_API_KEY")!;
  const sb = createClient(SUPABASE_URL, SERVICE_KEY, {
    auth: { persistSession: false, autoRefreshToken: false },
  });

  // 1. Currently-low parts. PostgREST doesn't support `on_hand < min_stock`
  //    (column-vs-column comparison), so fetch all tracked/non-one-time/
  //    min_stock>0 candidates and filter client-side.
  const { data: parts, error } = await sb
    .from("payroll_parts_prices")
    .select("id, name, on_hand, min_stock")
    .eq("track_inventory", true)
    .eq("is_one_time", false)
    .gt("min_stock", 0);
  if (error) return new Response(JSON.stringify({ error: error.message }), { status: 500 });

  const raw = (parts ?? []).map((p: any) => ({
    id: p.id, name: p.name, on_hand: Number(p.on_hand), min_stock: Number(p.min_stock),
  }));
  const lowRows = raw.filter((p) => p.on_hand < p.min_stock);
  if (lowRows.length === 0) {
    return new Response(JSON.stringify({ status: "skipped_none_low" }), { status: 200 });
  }
  const rows = shapeLowStockRows(lowRows);

  // 2. Recipients
  const { data: recipRows, error: rErr } = await sb.rpc("list_low_stock_alert_recipients");
  if (rErr) return new Response(JSON.stringify({ error: rErr.message }), { status: 500 });
  const recipients = ((recipRows ?? []) as { email: string }[]).map((r) => r.email);
  if (recipients.length === 0) {
    return new Response(JSON.stringify({ status: "skipped_no_recipients" }), { status: 200 });
  }

  // 3. Settings + render
  const { data: settings } = await sb.from("email_settings").select("*").eq("id", 1).single();
  if (!settings) return new Response(JSON.stringify({ error: "email_settings missing" }), { status: 500 });

  const dateLabel = new Date().toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
  const subjectN = rows.length === 1 ? "1 part" : `${rows.length} parts`;
  const subject = `Weekly low-stock reminder — ${subjectN} still low`;
  const { html, text } = renderLowStockAlert({
    kind: "weekly",
    rows,
    dateLabel,
    dashboardUrl: "https://twinsdash.com/payroll/parts",
  });

  const results: any[] = [];
  for (const to of recipients) {
    const result = await sendEmail({ settings, to, subject, html, text, apiKey: RESEND_KEY });
    await sb.from("email_log").insert({
      tech_id: null,
      kind: "low_stock_weekly",
      recipient: result.recipient ?? to,
      subject: result.subject ?? subject,
      dry_run: settings.dry_run,
      resend_id: result.resend_id ?? null,
      status: result.status,
      error: result.error ?? null,
    });
    results.push({ to, status: result.status });
  }
  return new Response(JSON.stringify({ status: "ok", count: results.length, rows: rows.length }), {
    status: 200, headers: { "Content-Type": "application/json" },
  });
});
```

- [ ] **Step 2: Create the pg_cron schedule migration**

Write `supabase/migrations/20260425100200_low_stock_weekly_cron.sql`:

```sql
-- Schedule the weekly low-stock digest. Monday 13:00 UTC ≈ 07:00 CST
-- (winter) / 08:00 CDT (summer). The one-hour DST drift is accepted per
-- spec § "DST drift for weekly digest".

SELECT cron.schedule(
  'payroll-weekly-lowstock',
  '0 13 * * 1',
  $$
    SELECT net.http_post(
      url := current_setting('app.edge_functions_url', true) || '/cron-weekly-lowstock',
      headers := jsonb_build_object(
        'Content-Type', 'application/json',
        'x-cron-secret', current_setting('app.email_cron_secret', true)
      ),
      body := '{}'::jsonb
    ) AS request_id;
  $$
);
```

The `app.edge_functions_url` and `app.email_cron_secret` settings are already configured on this project (used by the existing `reconcile-invoices-nightly` cron). If `supabase db push` complains, check `20260422220000_reconcile_invoices_cron.sql` for the existing setting pattern and mirror it verbatim.

- [ ] **Step 3: Apply migration + deploy function**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx supabase db push
npx supabase functions deploy cron-weekly-lowstock
```

- [ ] **Step 4: Verify the cron row exists**

If MCP `execute_sql` works for read-only queries on this project, run:

```sql
SELECT jobid, jobname, schedule, active FROM cron.job
WHERE jobname = 'payroll-weekly-lowstock';
```

Expected: one row, schedule `0 13 * * 1`, active `true`. If MCP is blocked (likely), skip this step.

- [ ] **Step 5: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add supabase/functions/cron-weekly-lowstock/index.ts supabase/migrations/20260425100200_low_stock_weekly_cron.sql
git commit -m "$(cat <<'EOF'
feat(payroll): weekly low-stock digest via pg_cron

cron-weekly-lowstock edge function + Monday 13:00 UTC schedule.
Scans payroll_parts_prices for anything tracked, non-one-time, with
min_stock > 0 and on_hand < min_stock; skips send if none. Sends one
digest email per recipient (admins + opt-ins). Does not modify
last_low_alert_at (digest is a reminder, not an alert).

Gated by x-cron-secret header so only pg_cron can invoke it.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Wire Run.tsx Finalize to fire-and-forget the email

**Files:**
- Modify: `src/pages/payroll/Run.tsx` — the existing `onFinalize` handler

**Context:** After the existing `finalize_payroll_run` RPC call succeeds, if the response's `newly_low` array has at least one entry, fire-and-forget `supabase.functions.invoke("invoke-low-stock-email", ...)`. Don't `await` — don't block the toast + nav.

- [ ] **Step 1: Extend the onFinalize handler**

In `src/pages/payroll/Run.tsx`, find the `onFinalize={async () =>` handler (around the Step 4 block). It currently looks like:

```tsx
onFinalize={async () => {
  const { data, error } = await supabase.rpc("finalize_payroll_run", { p_run_id: runId! });
  if (error) { toast({ title: "Finalize failed", description: error.message, variant: "destructive" }); return; }
  const res = (data ?? {}) as { stock_updates?: number; skipped_unmatched?: number };
  const bits: string[] = [];
  if ((res.stock_updates ?? 0) > 0) bits.push(`${res.stock_updates} parts deducted from stock`);
  if ((res.skipped_unmatched ?? 0) > 0) bits.push(`${res.skipped_unmatched} parts not tracked`);
  qc.invalidateQueries({ queryKey: ["payroll", "parts"] });
  qc.invalidateQueries({ queryKey: ["payroll", "pending-stock"] });
  qc.invalidateQueries({ queryKey: ["payroll", "tracked-parts-with-min"] });
  toast({ title: "Run finalized", description: bits.length ? bits.join(" · ") : undefined });
  nav(`/payroll/history/${runId}`);
}}
```

Replace with:

```tsx
onFinalize={async () => {
  const { data, error } = await supabase.rpc("finalize_payroll_run", { p_run_id: runId! });
  if (error) { toast({ title: "Finalize failed", description: error.message, variant: "destructive" }); return; }
  const res = (data ?? {}) as { stock_updates?: number; skipped_unmatched?: number; newly_low?: Array<{ id: number }> };
  const bits: string[] = [];
  if ((res.stock_updates ?? 0) > 0) bits.push(`${res.stock_updates} parts deducted from stock`);
  if ((res.skipped_unmatched ?? 0) > 0) bits.push(`${res.skipped_unmatched} parts not tracked`);
  if ((res.newly_low?.length ?? 0) > 0) {
    bits.push(`${res.newly_low!.length} low-stock alert${res.newly_low!.length === 1 ? "" : "s"} sent`);
    // Fire-and-forget — don't block the UI on Resend latency.
    void supabase.functions.invoke("invoke-low-stock-email", {
      body: { kind: "finalize", part_ids: res.newly_low!.map((p) => p.id) },
    });
  }
  qc.invalidateQueries({ queryKey: ["payroll", "parts"] });
  qc.invalidateQueries({ queryKey: ["payroll", "pending-stock"] });
  qc.invalidateQueries({ queryKey: ["payroll", "tracked-parts-with-min"] });
  toast({ title: "Run finalized", description: bits.length ? bits.join(" · ") : undefined });
  nav(`/payroll/history/${runId}`);
}}
```

- [ ] **Step 2: Typecheck**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 3: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add src/pages/payroll/Run.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): Run.tsx Finalize fires low-stock email fire-and-forget

When finalize_payroll_run returns a non-empty newly_low array, the
client invokes invoke-low-stock-email without awaiting. The toast
description now includes "N low-stock alerts sent" so the operator
knows the email went out.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: "Preview low-stock email" button on Parts Library

**Files:**
- Modify: `src/pages/payroll/Parts.tsx`

**Context:** Admin-only button next to "Upload counts". Calls `invoke-low-stock-email` with `kind: "preview"`. Client-side rate limit: one per 60s via `sessionStorage` key `low-stock-preview-last-at`.

- [ ] **Step 1: Add the button + handler**

In `src/pages/payroll/Parts.tsx`, find the button cluster (`<div className="flex flex-wrap gap-2">` containing Import from Excel / Upload counts / Add Part). Add a handler at the top of the component alongside `onInventoryCsvFile`:

```ts
async function onSendPreview() {
  const key = "low-stock-preview-last-at";
  const last = Number(sessionStorage.getItem(key) ?? 0);
  if (Date.now() - last < 60_000) {
    toast({ title: "Wait a minute before sending another preview" });
    return;
  }
  sessionStorage.setItem(key, String(Date.now()));
  const { data, error } = await supabase.functions.invoke("invoke-low-stock-email", {
    body: { kind: "preview" },
  });
  if (error) {
    toast({ title: "Preview send failed", description: error.message, variant: "destructive" });
    return;
  }
  const res = data as { status: string; sent?: Array<{ to: string; status: string }> };
  toast({
    title: "Preview email sent",
    description: res.sent?.[0]?.to ? `Sent to ${res.sent[0].to}` : undefined,
  });
}
```

Add the button inside the existing cluster (next to Upload counts):

```tsx
<Button variant="outline" onClick={() => void onSendPreview()}>
  <Mail className="mr-2 h-4 w-4" /> Preview low-stock email
</Button>
```

Extend the `lucide-react` import at the top to include `Mail`:

```ts
import { Upload, Plus, Trash2, Pencil, GitMerge, Mail } from "lucide-react";
```

- [ ] **Step 2: Typecheck + run Vitest to confirm nothing broke**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx tsc --noEmit
npx vitest run
```

Expected: tsc clean, same pass count as before.

- [ ] **Step 3: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add src/pages/payroll/Parts.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): Preview low-stock email button on Parts Library

Calls invoke-low-stock-email with kind=preview; edge function resolves
the caller's email from the Supabase JWT and sends a single sample
alert to just them. Client-side 60s rate-limit via sessionStorage.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: Expose `low_stock_alerts` in the role-permissions UI

**Files:**
- Modify: `src/components/admin/AdminUserManagement.tsx` — the `KNOWN_PERMISSIONS` array

**Context:** The existing Admin → User Management page renders a permissions matrix driven by a hardcoded `KNOWN_PERMISSIONS` list. Adding one entry wires the `low_stock_alerts` flag into the existing grid so admins can toggle it per role. The underlying storage (`user_roles.permissions` JSONB) is already in place; the recipient RPC from Task 5 reads it directly.

- [ ] **Step 1: Add the permission to the list**

In `src/components/admin/AdminUserManagement.tsx`, find:

```ts
const KNOWN_PERMISSIONS = [
  // ...existing entries...
  { key: 'payroll_access', label: 'Payroll' },
];
```

Change to:

```ts
const KNOWN_PERMISSIONS = [
  // ...existing entries...
  { key: 'payroll_access', label: 'Payroll' },
  { key: 'low_stock_alerts', label: 'Low-stock alerts' },
];
```

No other changes — the grid UI renders the new column automatically.

- [ ] **Step 2: Typecheck**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
npx tsc --noEmit
```

Expected: clean.

- [ ] **Step 3: Commit**

```bash
cd /Users/daniel/twins-dashboard/twins-dash-payroll-work
git branch --show-current  # MUST be feature/payroll-draft-sync
git add src/components/admin/AdminUserManagement.tsx
git commit -m "$(cat <<'EOF'
feat(payroll): expose low_stock_alerts toggle in Admin → User Management

One-line addition to KNOWN_PERMISSIONS. The underlying storage
(user_roles.permissions JSONB) is already there; the recipient RPC
reads permissions.low_stock_alerts directly. Admins are always on the
recipient list regardless of this flag.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 10: End-to-end manual test

**Files:** none (testing only)

**Context:** Walk the full flow once on the deployed app. Requires auth + Resend keys + a real finalizable payroll. Operator runs this post-deploy.

- [ ] **Step 1: Confirm environment**

Via the Parts Library, note down the current `on_hand` and `min_stock` for one reference part (e.g. Titan rollers). Make sure `on_hand > min_stock` and `min_stock > 0`. If not, inline-edit until they're. Record the baseline.

- [ ] **Step 2: Preview test**

Click **Preview low-stock email** on `/payroll/parts`. Within 30 seconds, the admin's Resend inbox should receive a sample email: subject `[Preview] Low stock — 3 parts`, with three synthetic rows.

If `email_settings.dry_run = true`, the email lands at the dry-run recipient instead, with a `[TEST → ...]` subject prefix — confirm that too.

- [ ] **Step 3: Admin → User Management toggle test**

Go to Admin → User Management. Confirm a new **Low-stock alerts** column is in the permissions matrix. Toggle it on for a non-admin role (e.g. `manager`) and save. Reload — it persists.

- [ ] **Step 4: Finalize crossing-below test**

Start a new payroll run for the current week (or reuse an in-progress draft). Sync HCP. In Step 3 Review, add enough Titan rollers to a job that the decrement will drop `on_hand` below `min_stock`. Finalize.

Expected:
- Toast reads `Run finalized · N parts deducted from stock · 1 low-stock alert sent`.
- Admin + any opted-in users receive the alert email within 30 seconds. Subject `Low stock after payroll — 1 part need reorder`.
- `email_log` has one row per recipient with `kind = low_stock_finalize`, `status = sent`.
- `payroll_parts_prices.last_low_alert_at` for Titan rollers is now non-null.

- [ ] **Step 5: De-dupe test**

Start another run, consume more Titan rollers (stock stays below threshold), Finalize again. Expected:
- Toast does NOT include a low-stock-alert segment (newly_low array is empty).
- No new `email_log` row for this part.

- [ ] **Step 6: Recovery + re-arm test**

Inline-edit Titan rollers' `on_hand` on `/payroll/parts` to a value at or above `min_stock`. Expected: the recovery trigger clears `last_low_alert_at` to NULL.

Run another Finalize that drops it below again. The Finalize alert fires as in Step 4 — the part was properly re-armed.

- [ ] **Step 7: Weekly cron test (manual trigger)**

Don't wait until Monday. Manually invoke the cron function to verify it works:

```bash
curl -X POST \
  -H "x-cron-secret: $EMAIL_CRON_SECRET" \
  -H "Content-Type: application/json" \
  -d '{}' \
  https://<project-ref>.supabase.co/functions/v1/cron-weekly-lowstock
```

(Use the secret configured on the project. Easier: admin can trigger via the Supabase Studio "Invoke function" UI.)

Expected: if any part is currently low, every recipient gets a digest email with `kind = low_stock_weekly`. If nothing is low, the response is `{"status":"skipped_none_low"}` and no email is sent.

- [ ] **Step 8: Reset state**

Inline-edit Titan rollers back to its original baseline. Clean up any test drafts via the history delete action. No commit needed.

---

## Out-of-scope (not in this plan)

Deferred per spec:

- SMS / Slack / Teams channels.
- Per-part recipient routing.
- Auto-reorder / PO API integration.
- Percentage-based thresholds.
- Snooze/ack per-part.
- Per-admin opt-out.
- Dedicated Admin page for managing low-stock recipients as separate emails (non-user addresses).
