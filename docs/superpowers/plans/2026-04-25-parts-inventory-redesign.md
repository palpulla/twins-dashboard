# Parts & Inventory Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the Parts & Inventory page (`/payroll/parts`) up to mobile-first usability, surface explanations on every stat tile, and add a phone-first count session with voice input that the field supervisor can use in the warehouse.

**Architecture:** Three new migrations introduce `inventory_count_sessions`, `inventory_count_lines`, `parts_voice_aliases` plus the RPCs that drive them. The existing `PartsLibrary.tsx` gains an InfoTip per stat tile, a tabbed mobile layout, and a "Start count session" entry point. A new `/payroll/parts/count` route hosts the per-SKU count UI, wraps `webkitSpeechRecognition` for voice, and uses Fuse.js to fuzzy-match spoken queries to the pricebook plus an alias index. Commit semantics are atomic via a Postgres RPC that updates both the count line and `payroll_parts_prices.on_hand` in one transaction.

**Tech Stack:** React + TypeScript + Vite, Supabase (Postgres + RLS + RPCs), TanStack Query, shadcn/ui, Web Speech API, Fuse.js, Vitest + Testing Library, ExcelJS (already used elsewhere).

**Spec:** [`docs/superpowers/specs/2026-04-25-parts-inventory-redesign-design.md`](../specs/2026-04-25-parts-inventory-redesign-design.md)

**Repo / working dir:** `palpulla/twins-dash` — branch off `main`, use `.worktrees/<branch>/` inside the inner repo.

---

## File structure

### New

```
supabase/migrations/2026042500006_inventory_count_tables.sql
supabase/migrations/2026042500007_count_session_rpcs.sql
supabase/migrations/2026042500008_parts_voice_aliases.sql

src/lib/parts/STAT_INFO.ts
src/lib/parts/voiceParse.ts
src/lib/parts/voiceParse.test.ts
src/lib/parts/voiceMatch.ts

src/hooks/parts/useCountSession.ts
src/hooks/parts/useVoiceCount.ts
src/hooks/parts/useCountLines.ts

src/components/payroll/parts/StatInfoTip.tsx
src/components/payroll/parts/PartCard.tsx
src/components/payroll/parts/CountSessionStart.tsx
src/components/payroll/parts/CountSessionPerSku.tsx
src/components/payroll/parts/CountSessionEnd.tsx
src/components/payroll/parts/VoiceConfirmCard.tsx

src/pages/payroll/CountSession.tsx
```

### Modified

```
src/components/payroll/parts/StatTile.tsx        — accept optional info string
src/pages/payroll/PartsLibrary.tsx               — wire InfoTips, tabbed mobile layout, Count CTA
src/App.tsx                                      — add /payroll/parts/count route
package.json                                     — add fuse.js dep
```

---

## Task 1: Install Fuse.js

**Files:**
- Modify: `package.json`

- [ ] **Step 1.1: Install dependency**

Run from inside the worktree:

```bash
npm install --save fuse.js@^7.0.0
```

- [ ] **Step 1.2: Verify**

```bash
grep -n '"fuse.js"' package.json
```

Expected: a single line showing the version.

- [ ] **Step 1.3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore(parts): add fuse.js for voice fuzzy matching"
```

---

## Task 2: Migration — inventory_count_sessions + inventory_count_lines

**Files:**
- Create: `supabase/migrations/2026042500006_inventory_count_tables.sql`

- [ ] **Step 2.1: Write migration**

Create the file with:

```sql
-- Inventory count sessions and per-SKU lines for the supervisor count flow.
-- Sessions persist so a closed phone tab can resume. Lines snapshot the
-- system value at line-open time so discrepancy math is stable.

CREATE TABLE IF NOT EXISTS public.inventory_count_sessions (
  id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  created_by        uuid NOT NULL REFERENCES auth.users(id),
  name              text NOT NULL,
  started_at        timestamptz NOT NULL DEFAULT now(),
  completed_at      timestamptz,
  status            text NOT NULL DEFAULT 'in_progress'
                    CHECK (status IN ('in_progress','completed','cancelled')),
  categories_filter text[] NOT NULL DEFAULT ARRAY[]::text[],
  note              text
);

CREATE INDEX IF NOT EXISTS idx_count_sessions_user_active
  ON public.inventory_count_sessions(created_by)
  WHERE status = 'in_progress';

CREATE TABLE IF NOT EXISTS public.inventory_count_lines (
  id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  session_id   bigint NOT NULL REFERENCES public.inventory_count_sessions(id) ON DELETE CASCADE,
  part_id      int NOT NULL REFERENCES public.payroll_parts_prices(id) ON DELETE CASCADE,
  expected     numeric,
  counted      numeric,
  delta_pct    numeric,
  status       text NOT NULL DEFAULT 'pending'
               CHECK (status IN ('pending','counted','skipped')),
  note         text,
  created_at   timestamptz NOT NULL DEFAULT now(),
  committed_at timestamptz
);

CREATE INDEX IF NOT EXISTS idx_count_lines_session
  ON public.inventory_count_lines(session_id, status);

CREATE UNIQUE INDEX IF NOT EXISTS uq_count_lines_session_part
  ON public.inventory_count_lines(session_id, part_id);

ALTER TABLE public.inventory_count_sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.inventory_count_lines    ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "payroll_access full count_sessions" ON public.inventory_count_sessions;
CREATE POLICY "payroll_access full count_sessions" ON public.inventory_count_sessions
  FOR ALL TO authenticated
  USING (
    public.has_payroll_access(auth.uid())
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  )
  WITH CHECK (
    public.has_payroll_access(auth.uid())
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  );

DROP POLICY IF EXISTS "payroll_access full count_lines" ON public.inventory_count_lines;
CREATE POLICY "payroll_access full count_lines" ON public.inventory_count_lines
  FOR ALL TO authenticated
  USING (
    public.has_payroll_access(auth.uid())
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  )
  WITH CHECK (
    public.has_payroll_access(auth.uid())
    OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)
  );

GRANT SELECT, INSERT, UPDATE, DELETE
  ON public.inventory_count_sessions, public.inventory_count_lines
  TO authenticated;
```

- [ ] **Step 2.2: Apply via CLI**

```bash
npx supabase db query --linked --file supabase/migrations/2026042500006_inventory_count_tables.sql
```

Expected: empty rows array, no error.

- [ ] **Step 2.3: Record in schema_migrations**

```bash
npx supabase db query --linked "INSERT INTO supabase_migrations.schema_migrations (version, name, statements) VALUES ('2026042500006', 'inventory_count_tables', ARRAY['-- applied via direct query']) ON CONFLICT (version) DO NOTHING RETURNING version"
```

Expected: a row with `"version": "2026042500006"`.

- [ ] **Step 2.4: Regenerate types**

```bash
npx supabase gen types typescript --linked > src/integrations/supabase/types.ts
```

After the file is written, open it and delete any leading `Initialising login role...` line if present (CLI banner leak — see `reference_twins_dash_migration_history.md`).

- [ ] **Step 2.5: Commit**

```bash
git add supabase/migrations/2026042500006_inventory_count_tables.sql src/integrations/supabase/types.ts
git commit -m "feat(parts): inventory_count_sessions + lines tables"
```

---

## Task 3: Migration — parts_voice_aliases

**Files:**
- Create: `supabase/migrations/2026042500008_parts_voice_aliases.sql`

- [ ] **Step 3.1: Write migration**

```sql
-- Optional alias index. Empty in v1; admin can add entries when voice
-- consistently mishears a part. Lookup is open to all authenticated; writes
-- are admin-only via has_payroll_access.

CREATE TABLE IF NOT EXISTS public.parts_voice_aliases (
  id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  alias       text NOT NULL,
  part_id     int  NOT NULL REFERENCES public.payroll_parts_prices(id) ON DELETE CASCADE,
  created_by  uuid REFERENCES auth.users(id),
  created_at  timestamptz NOT NULL DEFAULT now(),
  UNIQUE (alias, part_id)
);

CREATE INDEX IF NOT EXISTS idx_parts_voice_aliases_alias
  ON public.parts_voice_aliases(alias);

ALTER TABLE public.parts_voice_aliases ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "authenticated read aliases" ON public.parts_voice_aliases;
CREATE POLICY "authenticated read aliases" ON public.parts_voice_aliases
  FOR SELECT TO authenticated
  USING (auth.uid() IS NOT NULL);

DROP POLICY IF EXISTS "payroll_access write aliases" ON public.parts_voice_aliases;
CREATE POLICY "payroll_access write aliases" ON public.parts_voice_aliases
  FOR ALL TO authenticated
  USING (public.has_payroll_access(auth.uid()))
  WITH CHECK (public.has_payroll_access(auth.uid()));

GRANT SELECT, INSERT, UPDATE, DELETE
  ON public.parts_voice_aliases TO authenticated;
```

- [ ] **Step 3.2: Apply + record + regen types**

```bash
npx supabase db query --linked --file supabase/migrations/2026042500008_parts_voice_aliases.sql
npx supabase db query --linked "INSERT INTO supabase_migrations.schema_migrations (version, name, statements) VALUES ('2026042500008', 'parts_voice_aliases', ARRAY['-- applied via direct query']) ON CONFLICT (version) DO NOTHING RETURNING version"
npx supabase gen types typescript --linked > src/integrations/supabase/types.ts
```

Strip any leading CLI banner line in `types.ts` if present.

- [ ] **Step 3.3: Commit**

```bash
git add supabase/migrations/2026042500008_parts_voice_aliases.sql src/integrations/supabase/types.ts
git commit -m "feat(parts): parts_voice_aliases table for voice match overrides"
```

---

## Task 4: Migration — count session RPCs

**Files:**
- Create: `supabase/migrations/2026042500007_count_session_rpcs.sql`

- [ ] **Step 4.1: Write migration**

```sql
-- Atomic operations on count sessions. SECURITY DEFINER so the supervisor
-- can flip submission status + write the audited on_hand update in one
-- transaction. Each function gates on has_payroll_access OR field_supervisor.

CREATE OR REPLACE FUNCTION public.assert_can_count() RETURNS void
LANGUAGE plpgsql STABLE
AS $$
BEGIN
  IF NOT (public.has_payroll_access(auth.uid())
       OR public.has_role(auth.uid(), 'field_supervisor'::public.app_role)) THEN
    RAISE EXCEPTION 'forbidden: payroll access required';
  END IF;
END;
$$;

-- Start a session (or return the in-progress one for this user).
CREATE OR REPLACE FUNCTION public.start_count_session(
  p_name text,
  p_categories_filter text[] DEFAULT ARRAY[]::text[]
) RETURNS bigint
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public
AS $$
DECLARE v_existing bigint;
DECLARE v_id bigint;
BEGIN
  PERFORM public.assert_can_count();
  SELECT id INTO v_existing FROM public.inventory_count_sessions
   WHERE created_by = auth.uid() AND status = 'in_progress'
   LIMIT 1;
  IF v_existing IS NOT NULL THEN RETURN v_existing; END IF;
  INSERT INTO public.inventory_count_sessions (created_by, name, categories_filter)
    VALUES (auth.uid(), p_name, COALESCE(p_categories_filter, ARRAY[]::text[]))
    RETURNING id INTO v_id;
  RETURN v_id;
END;
$$;

-- Commit a counted line: upsert the line + update payroll_parts_prices.on_hand.
CREATE OR REPLACE FUNCTION public.commit_count_line(
  p_session_id bigint,
  p_part_id int,
  p_counted numeric,
  p_note text DEFAULT NULL
) RETURNS void
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public
AS $$
DECLARE v_expected numeric;
DECLARE v_delta_pct numeric;
BEGIN
  PERFORM public.assert_can_count();

  SELECT on_hand INTO v_expected FROM public.payroll_parts_prices WHERE id = p_part_id;
  IF v_expected IS NULL THEN v_expected := 0; END IF;

  IF v_expected = 0 THEN
    v_delta_pct := CASE WHEN p_counted = 0 THEN 0 ELSE 100 END;
  ELSE
    v_delta_pct := ABS(p_counted - v_expected) / NULLIF(v_expected, 0) * 100;
  END IF;

  INSERT INTO public.inventory_count_lines (session_id, part_id, expected, counted, delta_pct, status, note, committed_at)
    VALUES (p_session_id, p_part_id, v_expected, p_counted, v_delta_pct, 'counted', p_note, now())
    ON CONFLICT (session_id, part_id) DO UPDATE
      SET counted = EXCLUDED.counted,
          expected = COALESCE(public.inventory_count_lines.expected, EXCLUDED.expected),
          delta_pct = EXCLUDED.delta_pct,
          status = 'counted',
          note = EXCLUDED.note,
          committed_at = now();

  UPDATE public.payroll_parts_prices
     SET on_hand = p_counted, last_count_at = now()
   WHERE id = p_part_id;
END;
$$;

-- Mark a line skipped (no on_hand write).
CREATE OR REPLACE FUNCTION public.skip_count_line(
  p_session_id bigint,
  p_part_id int,
  p_note text DEFAULT NULL
) RETURNS void
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  PERFORM public.assert_can_count();
  INSERT INTO public.inventory_count_lines (session_id, part_id, expected, status, note)
    SELECT p_session_id, p_part_id, on_hand, 'skipped', p_note
      FROM public.payroll_parts_prices WHERE id = p_part_id
  ON CONFLICT (session_id, part_id) DO UPDATE
    SET status = 'skipped', note = EXCLUDED.note;
END;
$$;

-- Finalize: mark session completed.
CREATE OR REPLACE FUNCTION public.finish_count_session(p_session_id bigint)
RETURNS void
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  PERFORM public.assert_can_count();
  UPDATE public.inventory_count_sessions
     SET status = 'completed', completed_at = now()
   WHERE id = p_session_id AND created_by = auth.uid();
END;
$$;

-- Cancel: mark session cancelled.
CREATE OR REPLACE FUNCTION public.cancel_count_session(p_session_id bigint)
RETURNS void
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  PERFORM public.assert_can_count();
  UPDATE public.inventory_count_sessions
     SET status = 'cancelled', completed_at = now()
   WHERE id = p_session_id AND created_by = auth.uid();
END;
$$;

GRANT EXECUTE ON FUNCTION
  public.start_count_session(text, text[]),
  public.commit_count_line(bigint, int, numeric, text),
  public.skip_count_line(bigint, int, text),
  public.finish_count_session(bigint),
  public.cancel_count_session(bigint)
  TO authenticated;
```

- [ ] **Step 4.2: Apply + record + regen types**

```bash
npx supabase db query --linked --file supabase/migrations/2026042500007_count_session_rpcs.sql
npx supabase db query --linked "INSERT INTO supabase_migrations.schema_migrations (version, name, statements) VALUES ('2026042500007', 'count_session_rpcs', ARRAY['-- applied via direct query']) ON CONFLICT (version) DO NOTHING RETURNING version"
npx supabase gen types typescript --linked > src/integrations/supabase/types.ts
```

Strip leading CLI banner line in `types.ts`.

- [ ] **Step 4.3: Commit**

```bash
git add supabase/migrations/2026042500007_count_session_rpcs.sql src/integrations/supabase/types.ts
git commit -m "feat(parts): count session RPCs (start/commit/skip/finish/cancel)"
```

---

## Task 5: STAT_INFO + StatInfoTip + extend StatTile

**Files:**
- Create: `src/lib/parts/STAT_INFO.ts`
- Create: `src/components/payroll/parts/StatInfoTip.tsx`
- Modify: `src/components/payroll/parts/StatTile.tsx`

- [ ] **Step 5.1: Write STAT_INFO**

Create `src/lib/parts/STAT_INFO.ts`:

```ts
// Canonical strings for the stat-tile info tooltips. Edit copy here, not at
// the call sites.

export const STAT_INFO = {
  skus:           "Total parts in your library, including non-tracked one-time costs.",
  on_hand_units:  "Sum of physical inventory across tracked parts (counts only).",
  on_hand_value:  "Sum of (on hand x cost) across tracked parts. Admin only.",
  needs_reorder:  "Tracked parts where current on-hand is at or below your min stock.",
  velocity:       "Total parts pulled from completed jobs in the last 30 days. Drives reorder.",
} as const;

export type StatInfoKey = keyof typeof STAT_INFO;
```

- [ ] **Step 5.2: Write StatInfoTip**

Create `src/components/payroll/parts/StatInfoTip.tsx`:

```tsx
import { Info } from "lucide-react";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";

export function StatInfoTip({ content, label }: { content: string; label: string }) {
  return (
    <TooltipProvider delayDuration={150}>
      <Tooltip>
        <TooltipTrigger asChild>
          <button
            type="button"
            className="text-[var(--pl-muted)] hover:text-[var(--pl-navy)]"
            aria-label={`About ${label}`}
          >
            <Info className="h-3 w-3" />
          </button>
        </TooltipTrigger>
        <TooltipContent className="max-w-xs text-xs">{content}</TooltipContent>
      </Tooltip>
    </TooltipProvider>
  );
}
```

- [ ] **Step 5.3: Extend StatTile to accept `info`**

Replace `src/components/payroll/parts/StatTile.tsx` with:

```tsx
import { cn } from "@/lib/utils";
import type { ReactNode } from "react";
import { StatInfoTip } from "./StatInfoTip";

type StatTileProps = {
  label: string;
  value: ReactNode;
  sub?: ReactNode;
  /** Amber-tinted "alert" variant for the Needs reorder tile. */
  alert?: boolean;
  className?: string;
  /** One-line explanation surfaced on a tappable info icon. */
  info?: string;
};

export function StatTile({ label, value, sub, alert, className, info }: StatTileProps) {
  return (
    <div className={cn("pl-stat", alert && "pl-stat--alert", className)}>
      <div className="pl-stat-lbl" style={{ display: "inline-flex", alignItems: "center", gap: 6 }}>
        <span>{label}</span>
        {info ? <StatInfoTip content={info} label={label} /> : null}
      </div>
      <div className="pl-stat-val num">{value}</div>
      {sub && <div className="pl-stat-sub">{sub}</div>}
    </div>
  );
}
```

- [ ] **Step 5.4: Wire into PartsLibrary**

In `src/pages/payroll/PartsLibrary.tsx`, find the four StatTile usages (the SKUs / On-hand / Needs reorder / Velocity block, around line 436) and add `info={STAT_INFO.<key>}` to each. Add the import at the top:

```ts
import { STAT_INFO } from "@/lib/parts/STAT_INFO";
```

Map:
- `<StatTile label="SKUs" ... info={STAT_INFO.skus} />`
- `<StatTile label="On-hand value" ... info={STAT_INFO.on_hand_value} />` (admin branch)
- `<StatTile label="On hand" ... info={STAT_INFO.on_hand_units} />` (non-admin branch)
- `<StatTile label="Needs reorder" ... info={STAT_INFO.needs_reorder} />`
- `<StatTile label="Velocity (30d)" ... info={STAT_INFO.velocity} />`

- [ ] **Step 5.5: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/lib/parts/STAT_INFO.ts src/components/payroll/parts/StatInfoTip.tsx src/components/payroll/parts/StatTile.tsx src/pages/payroll/PartsLibrary.tsx
git commit -m "feat(parts): stat tile info tooltips with canonical copy"
```

Expected: tsc clean.

---

## Task 6: PartCard component (mobile)

**Files:**
- Create: `src/components/payroll/parts/PartCard.tsx`

- [ ] **Step 6.1: Implement**

```tsx
import { Pencil, History } from "lucide-react";
import { StockPill } from "./StockPill";

export interface PartCardData {
  id: number;
  name: string;
  category: string;
  on_hand: number;
  min_stock: number;
  effective: number;
  used30: number;
  last_count_at: string | null;
  trackable: boolean;
}

interface Props {
  part: PartCardData;
  onChangeOnHand: (next: number) => void;
  onChangeMinStock: (next: number) => void;
  onEdit: () => void;
  onHistory: () => void;
}

function fmtShort(iso: string | null): string {
  if (!iso) return "never";
  const d = new Date(iso);
  if (isNaN(d.getTime())) return "never";
  return d.toLocaleDateString("en-US", { month: "short", day: "numeric" });
}

export function PartCard({ part, onChangeOnHand, onChangeMinStock, onEdit, onHistory }: Props) {
  const showStockPill = part.trackable && part.min_stock > 0;
  return (
    <div className="pl-part-card">
      <div className="pl-part-card-head">
        <div className="pl-part-name">{part.name}</div>
        <span className="pl-cat-tag">{part.category}</span>
      </div>
      <div className="pl-part-meta">
        Last counted {fmtShort(part.last_count_at)} · 30d use {part.used30 || 0}
      </div>
      <div className="pl-part-grid">
        <label className="pl-part-field">
          <span>On hand</span>
          <input
            type="number"
            inputMode="numeric"
            min={0}
            defaultValue={Number(part.on_hand)}
            key={`oh-${part.id}-${part.on_hand}`}
            onBlur={(e) => {
              const v = Number(e.target.value);
              if (Number.isFinite(v) && v >= 0 && v !== Number(part.on_hand)) onChangeOnHand(v);
            }}
            disabled={!part.trackable}
            aria-label={`On-hand for ${part.name}`}
          />
        </label>
        <label className="pl-part-field">
          <span>Min stock</span>
          <input
            type="number"
            inputMode="numeric"
            min={0}
            defaultValue={Number(part.min_stock)}
            key={`min-${part.id}-${part.min_stock}`}
            onBlur={(e) => {
              const v = Number(e.target.value);
              if (Number.isFinite(v) && v !== Number(part.min_stock)) onChangeMinStock(v);
            }}
            disabled={!part.trackable}
            aria-label={`Min stock for ${part.name}`}
          />
        </label>
        {showStockPill && <StockPill qty={part.effective} threshold={part.min_stock} withThreshold />}
      </div>
      <div className="pl-part-actions">
        <button type="button" onClick={onEdit} className="pl-part-action">
          <Pencil className="h-3.5 w-3.5" /> Edit
        </button>
        <button type="button" onClick={onHistory} className="pl-part-action">
          <History className="h-3.5 w-3.5" /> History
        </button>
      </div>
    </div>
  );
}
```

- [ ] **Step 6.2: Append CSS to `src/styles/parts-library.css`**

Add at the end of the file:

```css
/* ---------- Mobile part card ---------- */
.parts-lib .pl-part-card {
  background: var(--pl-card);
  border: 1px solid var(--pl-line);
  border-radius: 10px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.parts-lib .pl-part-card-head {
  display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;
}
.parts-lib .pl-part-name {
  font-size: 15px; font-weight: 700; color: var(--pl-navy); line-height: 1.25;
}
.parts-lib .pl-part-meta {
  font-size: 11px; color: var(--pl-muted);
}
.parts-lib .pl-part-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
  align-items: end;
}
.parts-lib .pl-part-field {
  display: flex; flex-direction: column; gap: 4px;
  font-size: 11px; color: var(--pl-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
}
.parts-lib .pl-part-field input {
  height: 44px; padding: 0 12px;
  font-size: 16px; font-weight: 700; color: var(--pl-navy);
  border: 1px solid var(--pl-line-2); border-radius: 8px;
  background: var(--pl-card);
  font-variant-numeric: tabular-nums;
}
.parts-lib .pl-part-field input:focus { outline: 2px solid var(--pl-yellow); outline-offset: 1px; }
.parts-lib .pl-part-field input:disabled { background: var(--pl-hover); color: var(--pl-muted); }
.parts-lib .pl-part-actions {
  display: flex; gap: 10px; justify-content: flex-end;
  border-top: 1px dashed var(--pl-line); padding-top: 10px;
}
.parts-lib .pl-part-action {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 600; color: var(--pl-navy);
  background: transparent; border: none; cursor: pointer;
  padding: 4px 6px; border-radius: 6px;
}
.parts-lib .pl-part-action:hover { background: var(--pl-hover); }
```

- [ ] **Step 6.3: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/components/payroll/parts/PartCard.tsx src/styles/parts-library.css
git commit -m "feat(parts): mobile-first PartCard component"
```

---

## Task 7: Mobile tabs in PartsLibrary

**Files:**
- Modify: `src/pages/payroll/PartsLibrary.tsx`

- [ ] **Step 7.1: Wrap content in a Tabs component below `md:`**

The desktop layout stays. Below `md:` we render a `<Tabs>` with three triggers (`Parts`, `Reorder`, `Activity`) and move the corresponding sections inside the tab panels.

Add the import:

```ts
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
```

Identify the existing two-column grid (`pl-main-grid`) around line 487. Wrap the entire `pl-main-grid` block in two parallel renders behind a `useIsMobile` flag. There's already a `useIsMobile` hook at `src/hooks/use-mobile.tsx`.

```ts
import { useIsMobile } from "@/hooks/use-mobile";
// inside the component
const isMobile = useIsMobile();
```

- [ ] **Step 7.2: Conditional render**

Replace the existing `pl-main-grid` block with:

```tsx
{isMobile ? (
  <Tabs defaultValue="parts" style={{ marginTop: 16 }}>
    <TabsList style={{ width: "100%" }}>
      <TabsTrigger value="parts" style={{ flex: 1 }}>Parts</TabsTrigger>
      <TabsTrigger value="reorder" style={{ flex: 1 }}>Reorder</TabsTrigger>
      <TabsTrigger value="activity" style={{ flex: 1 }}>Activity</TabsTrigger>
    </TabsList>
    <TabsContent value="parts">
      {/* same filter bar, then PartCard list (Task 8 wires this) */}
    </TabsContent>
    <TabsContent value="reorder">
      <ReorderRail
        items={reorderItems}
        outOfStockCount={outOfStock.length}
        onOrder={(id) => toast({ title: `Reorder queued for #${id}` })}
        onCreatePO={() => toast({ title: "Create PO" })}
      />
    </TabsContent>
    <TabsContent value="activity">
      <ActivityFeed limit={50} />
    </TabsContent>
  </Tabs>
) : (
  <div className="pl-main-grid" style={{ display: "grid", gridTemplateColumns: "1fr 320px", gap: 16, marginTop: 16, alignItems: "flex-start" }}>
    {/* ...existing two-column content as-is... */}
  </div>
)}
```

The Parts tab body will be filled by Task 8.

- [ ] **Step 7.3: Typecheck**

```bash
./node_modules/.bin/tsc --noEmit
```

If type errors surface around how the existing grid was wired, scope them by extracting the grid contents into a local helper (e.g., `renderDesktopMain()` and `renderMobileParts()` returning JSX). Keep them inside the component to preserve closures.

- [ ] **Step 7.4: Commit**

```bash
git add src/pages/payroll/PartsLibrary.tsx
git commit -m "feat(parts): mobile tabs (Parts | Reorder | Activity)"
```

---

## Task 8: Mobile parts list using PartCard

**Files:**
- Modify: `src/pages/payroll/PartsLibrary.tsx`

- [ ] **Step 8.1: Render PartCard list inside the Parts tab**

In the `<TabsContent value="parts">` from Task 7, render the same filter bar (search + sort + view toggle ignored on mobile — table-only doesn't apply) plus a vertical stack of `<PartCard>`:

```tsx
<TabsContent value="parts">
  <div className="pl-filter-bar" style={{ marginBottom: 12 }}>
    <Button variant="outline" size="sm" onClick={cycleSort}>
      Sort: {sortLabel} <ChevronDown className="h-3.5 w-3.5" />
    </Button>
    <div style={{ flex: 1 }} />
    <span className="pl-showing">
      {filtered.length} of {parts.length}
    </span>
  </div>
  <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
    {filtered.map((p) => (
      <PartCard
        key={p.id}
        part={{
          id: p.id,
          name: p.name,
          category: p.category,
          on_hand: Number(p.on_hand ?? 0),
          min_stock: Number(p.min_stock ?? 0),
          effective: p.effective,
          used30: p.used30,
          last_count_at: p.last_count_at,
          trackable: !p.is_one_time && p.track_inventory,
        }}
        onChangeOnHand={(v) =>
          updateMutation.mutate({ id: p.id, on_hand: v, last_count_at: new Date().toISOString() })
        }
        onChangeMinStock={(v) => updateMutation.mutate({ id: p.id, min_stock: v })}
        onEdit={() => openEdit(p)}
        onHistory={() => toast({ title: `History for ${p.name}`, description: "Coming next" })}
      />
    ))}
    {filtered.length === 0 && (
      <div className="pl-muted-cell" style={{ padding: 40, textAlign: "center" }}>
        No parts match. Try clearing the search or picking a different category.
      </div>
    )}
  </div>
</TabsContent>
```

Add the import:

```ts
import { PartCard } from "@/components/payroll/parts/PartCard";
```

- [ ] **Step 8.2: Stat strip becomes 2x2 on mobile**

Find `.pl-stats` in `src/styles/parts-library.css` and add a media query:

```css
@media (max-width: 767px) {
  .parts-lib .pl-stats { grid-template-columns: 1fr 1fr; gap: 10px; }
}
```

Add this only if the existing grid is currently `repeat(4, 1fr)` and not already responsive.

- [ ] **Step 8.3: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/pages/payroll/PartsLibrary.tsx src/styles/parts-library.css
git commit -m "feat(parts): mobile PartCard list inside Parts tab + 2x2 stat grid"
```

---

## Task 9: voiceParse pure util + tests

**Files:**
- Create: `src/lib/parts/voiceParse.ts`
- Create: `src/lib/parts/voiceParse.test.ts`

- [ ] **Step 9.1: Write the failing test**

Create `src/lib/parts/voiceParse.test.ts`:

```ts
import { describe, it, expect } from "vitest";
import { parseVoice } from "./voiceParse";

describe("parseVoice", () => {
  it("digits at end", () => {
    expect(parseVoice("titan roller 30")).toEqual({ count: 30, query: "titan roller" });
  });
  it("english number word at end", () => {
    expect(parseVoice("titan roller thirty")).toEqual({ count: 30, query: "titan roller" });
  });
  it("compound english number word", () => {
    expect(parseVoice("part name twenty five")).toEqual({ count: 25, query: "part name" });
  });
  it("only the LAST number is the count", () => {
    expect(parseVoice("twenty five SSWDE ten")).toEqual({ count: 10, query: "twenty five SSWDE" });
  });
  it("no number found", () => {
    expect(parseVoice("titan roller")).toEqual({ count: null, query: "titan roller" });
  });
  it("empty input", () => {
    expect(parseVoice("")).toEqual({ count: null, query: "" });
  });
  it("only a number", () => {
    expect(parseVoice("forty two")).toEqual({ count: 42, query: "" });
  });
  it("ignores trailing punctuation", () => {
    expect(parseVoice("seal kit, 8.")).toEqual({ count: 8, query: "seal kit" });
  });
});
```

- [ ] **Step 9.2: Run test (should fail)**

```bash
npx vitest run src/lib/parts/voiceParse.test.ts
```

Expected: module not found.

- [ ] **Step 9.3: Implement parseVoice**

Create `src/lib/parts/voiceParse.ts`:

```ts
export interface VoiceParse {
  count: number | null;
  query: string;
}

const NUMBER_WORDS: Record<string, number> = {
  zero: 0, one: 1, two: 2, three: 3, four: 4, five: 5, six: 6, seven: 7, eight: 8, nine: 9,
  ten: 10, eleven: 11, twelve: 12, thirteen: 13, fourteen: 14, fifteen: 15, sixteen: 16,
  seventeen: 17, eighteen: 18, nineteen: 19,
  twenty: 20, thirty: 30, forty: 40, fifty: 50, sixty: 60, seventy: 70, eighty: 80, ninety: 90,
  hundred: 100,
};

const UNITS = new Set(["one","two","three","four","five","six","seven","eight","nine"]);
const TENS  = new Set(["twenty","thirty","forty","fifty","sixty","seventy","eighty","ninety"]);

// Pull the trailing number off a voice transcript. Returns the matched count
// (digits or English word combo at the end of the string) and the remaining
// "query" portion stripped of that number and trailing punctuation.
export function parseVoice(input: string): VoiceParse {
  const cleaned = input.replace(/[.,!?]+$/g, "").trim();
  if (cleaned.length === 0) return { count: null, query: "" };

  const tokens = cleaned.split(/\s+/);
  if (tokens.length === 0) return { count: null, query: "" };

  // 1. Try a trailing digit token first.
  const last = tokens[tokens.length - 1];
  if (/^\d+$/.test(last)) {
    const count = Number(last);
    const rest = tokens.slice(0, -1).join(" ").replace(/[,;]+$/, "").trim();
    return { count, query: rest };
  }

  // 2. Try a trailing English number — possibly compound like "twenty five".
  const lower = tokens.map((t) => t.toLowerCase().replace(/[,;:]+$/, ""));
  let endIdx = lower.length;
  let consumed = 0;
  let value: number | null = null;

  // a) Try the last token as a single number word.
  const oneToken = NUMBER_WORDS[lower[lower.length - 1]];
  if (oneToken !== undefined) {
    value = oneToken;
    consumed = 1;
  }

  // b) Try a two-token compound: "twenty five", "thirty one", etc.
  if (lower.length >= 2) {
    const a = lower[lower.length - 2];
    const b = lower[lower.length - 1];
    if (TENS.has(a) && UNITS.has(b)) {
      value = NUMBER_WORDS[a] + NUMBER_WORDS[b];
      consumed = 2;
    }
  }

  if (value !== null) {
    endIdx = lower.length - consumed;
    const rest = tokens.slice(0, endIdx).join(" ").replace(/[,;]+$/, "").trim();
    return { count: value, query: rest };
  }

  return { count: null, query: cleaned };
}
```

- [ ] **Step 9.4: Run tests (should pass)**

```bash
npx vitest run src/lib/parts/voiceParse.test.ts
```

Expected: 8 tests pass.

- [ ] **Step 9.5: Commit**

```bash
git add src/lib/parts/voiceParse.ts src/lib/parts/voiceParse.test.ts
git commit -m "feat(parts): voiceParse — strip trailing number from voice transcript"
```

---

## Task 10: voiceMatch — Fuse-backed SKU matcher

**Files:**
- Create: `src/lib/parts/voiceMatch.ts`

- [ ] **Step 10.1: Implement**

```ts
import Fuse from "fuse.js";

export interface MatchablePart {
  id: number;
  name: string;
  aliases?: string[];
}

export interface VoiceMatch {
  part_id: number;
  name: string;
  score: number;
}

/**
 * Fuzzy-match a voice query against the pricebook + alias list. Returns the
 * top N matches sorted by score (lower = better, per Fuse). Names and aliases
 * share the same searchable index; aliases get a small weight bonus so they
 * win ties against close but unrelated SKU names.
 */
export function matchVoiceQuery(
  query: string,
  parts: MatchablePart[],
  topN = 3,
): VoiceMatch[] {
  if (!query.trim() || parts.length === 0) return [];

  const docs: Array<{ part_id: number; name: string; haystack: string; weight: number }> = [];
  for (const p of parts) {
    docs.push({ part_id: p.id, name: p.name, haystack: p.name, weight: 1 });
    for (const a of p.aliases ?? []) {
      docs.push({ part_id: p.id, name: p.name, haystack: a, weight: 0.7 });
    }
  }

  const fuse = new Fuse(docs, {
    keys: [{ name: "haystack", weight: 1 }],
    includeScore: true,
    threshold: 0.5,
    ignoreLocation: true,
    isCaseSensitive: false,
  });

  const results = fuse.search(query);
  const seen = new Set<number>();
  const out: VoiceMatch[] = [];
  for (const r of results) {
    if (seen.has(r.item.part_id)) continue;
    seen.add(r.item.part_id);
    const adjusted = (r.score ?? 1) * r.item.weight;
    out.push({ part_id: r.item.part_id, name: r.item.name, score: adjusted });
    if (out.length >= topN) break;
  }
  return out;
}
```

- [ ] **Step 10.2: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/lib/parts/voiceMatch.ts
git commit -m "feat(parts): voiceMatch via Fuse.js with aliases"
```

---

## Task 11: useVoiceCount hook

**Files:**
- Create: `src/hooks/parts/useVoiceCount.ts`

- [ ] **Step 11.1: Implement**

```ts
import { useCallback, useEffect, useRef, useState } from "react";

interface UseVoiceCount {
  supported: boolean;
  listening: boolean;
  transcript: string;
  error: string | null;
  start: () => void;
  stop: () => void;
  reset: () => void;
}

// Wraps the browser's SpeechRecognition. Single-shot: caller starts, the
// browser listens, fires onresult, and we stop. Used inside the count
// session screen with a mic button.

export function useVoiceCount(): UseVoiceCount {
  const Ctor =
    (typeof window !== "undefined"
      ? (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition
      : null) as undefined | { new (): any };
  const supported = !!Ctor;

  const [listening, setListening] = useState(false);
  const [transcript, setTranscript] = useState("");
  const [error, setError] = useState<string | null>(null);
  const recognitionRef = useRef<any>(null);

  const reset = useCallback(() => {
    setTranscript("");
    setError(null);
  }, []);

  const start = useCallback(() => {
    if (!supported || !Ctor) {
      setError("Voice not supported on this browser. Use Chrome.");
      return;
    }
    setError(null);
    setTranscript("");
    const rec = new Ctor();
    rec.lang = "en-US";
    rec.continuous = false;
    rec.interimResults = false;
    rec.maxAlternatives = 1;
    rec.onresult = (e: any) => {
      const t = e.results?.[0]?.[0]?.transcript ?? "";
      setTranscript(t);
    };
    rec.onerror = (e: any) => setError(String(e?.error ?? "voice error"));
    rec.onend = () => setListening(false);
    rec.start();
    recognitionRef.current = rec;
    setListening(true);
  }, [Ctor, supported]);

  const stop = useCallback(() => {
    try { recognitionRef.current?.stop(); } catch { /* no-op */ }
    setListening(false);
  }, []);

  useEffect(() => () => stop(), [stop]);

  return { supported, listening, transcript, error, start, stop, reset };
}
```

- [ ] **Step 11.2: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/hooks/parts/useVoiceCount.ts
git commit -m "feat(parts): useVoiceCount — Web Speech API single-shot wrapper"
```

---

## Task 12: useCountSession + useCountLines hooks

**Files:**
- Create: `src/hooks/parts/useCountSession.ts`
- Create: `src/hooks/parts/useCountLines.ts`

- [ ] **Step 12.1: useCountSession**

Create `src/hooks/parts/useCountSession.ts`:

```ts
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase as supabaseTyped } from "@/integrations/supabase/client";

const supabase = supabaseTyped as any;

export interface CountSession {
  id: number;
  name: string;
  status: "in_progress" | "completed" | "cancelled";
  started_at: string;
  completed_at: string | null;
  categories_filter: string[];
  note: string | null;
}

export function useActiveCountSession() {
  return useQuery({
    queryKey: ["count_session", "active"],
    queryFn: async (): Promise<CountSession | null> => {
      const { data, error } = await supabase
        .from("inventory_count_sessions")
        .select("*")
        .eq("status", "in_progress")
        .order("started_at", { ascending: false })
        .limit(1)
        .maybeSingle();
      if (error) throw error;
      return (data as CountSession | null) ?? null;
    },
    staleTime: 10_000,
  });
}

export function useStartCountSession() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: { name: string; categories: string[] }) => {
      const { data, error } = await supabase.rpc("start_count_session", {
        p_name: args.name,
        p_categories_filter: args.categories,
      });
      if (error) throw error;
      return data as number;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["count_session"] }),
  });
}

export function useFinishCountSession() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (sessionId: number) => {
      const { error } = await supabase.rpc("finish_count_session", { p_session_id: sessionId });
      if (error) throw error;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["count_session"] }),
  });
}

export function useCancelCountSession() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (sessionId: number) => {
      const { error } = await supabase.rpc("cancel_count_session", { p_session_id: sessionId });
      if (error) throw error;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["count_session"] }),
  });
}
```

- [ ] **Step 12.2: useCountLines**

Create `src/hooks/parts/useCountLines.ts`:

```ts
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase as supabaseTyped } from "@/integrations/supabase/client";

const supabase = supabaseTyped as any;

export interface CountLine {
  id: number;
  session_id: number;
  part_id: number;
  expected: number | null;
  counted: number | null;
  delta_pct: number | null;
  status: "pending" | "counted" | "skipped";
  note: string | null;
  created_at: string;
  committed_at: string | null;
}

export function useCountLines(sessionId: number | null) {
  return useQuery({
    queryKey: ["count_lines", sessionId],
    enabled: !!sessionId,
    queryFn: async (): Promise<CountLine[]> => {
      const { data, error } = await supabase
        .from("inventory_count_lines")
        .select("*")
        .eq("session_id", sessionId!)
        .order("created_at", { ascending: false });
      if (error) throw error;
      return (data ?? []) as CountLine[];
    },
    staleTime: 5_000,
  });
}

export function useCommitCountLine() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: { sessionId: number; partId: number; counted: number; note?: string }) => {
      const { error } = await supabase.rpc("commit_count_line", {
        p_session_id: args.sessionId,
        p_part_id: args.partId,
        p_counted: args.counted,
        p_note: args.note ?? null,
      });
      if (error) throw error;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["count_lines"] });
      qc.invalidateQueries({ queryKey: ["payroll", "parts"] });
    },
  });
}

export function useSkipCountLine() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (args: { sessionId: number; partId: number; note?: string }) => {
      const { error } = await supabase.rpc("skip_count_line", {
        p_session_id: args.sessionId,
        p_part_id: args.partId,
        p_note: args.note ?? null,
      });
      if (error) throw error;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ["count_lines"] }),
  });
}
```

- [ ] **Step 12.3: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/hooks/parts/useCountSession.ts src/hooks/parts/useCountLines.ts
git commit -m "feat(parts): count session + line hooks"
```

---

## Task 13: VoiceConfirmCard component

**Files:**
- Create: `src/components/payroll/parts/VoiceConfirmCard.tsx`

- [ ] **Step 13.1: Implement**

```tsx
import { Check, X, Pencil } from "lucide-react";
import type { VoiceMatch } from "@/lib/parts/voiceMatch";

interface Props {
  count: number | null;
  matches: VoiceMatch[];
  onConfirm: (partId: number, count: number) => void;
  onCancel: () => void;
  onEditCount: (next: number) => void;
}

export function VoiceConfirmCard({ count, matches, onConfirm, onCancel, onEditCount }: Props) {
  const top = matches[0] ?? null;
  return (
    <div className="pl-voice-confirm">
      <div className="pl-voice-head">
        <span className="pl-voice-lbl">HEARD</span>
        <button type="button" className="pl-voice-x" onClick={onCancel} aria-label="Cancel">
          <X className="h-3.5 w-3.5" />
        </button>
      </div>
      <div className="pl-voice-line">
        <div className="pl-voice-name">{top ? top.name : "no match"}</div>
        <div className="pl-voice-x-mul">×</div>
        <input
          type="number"
          inputMode="numeric"
          className="pl-voice-count"
          defaultValue={count ?? 0}
          onBlur={(e) => onEditCount(Number(e.target.value))}
          aria-label="Count"
        />
      </div>
      <button
        type="button"
        className="pl-voice-save"
        disabled={!top || count == null}
        onClick={() => top && count != null && onConfirm(top.part_id, count)}
      >
        <Check className="h-4 w-4" /> Save count
      </button>
      {matches.slice(1).length > 0 && (
        <div className="pl-voice-alts">
          <span style={{ fontSize: 11, color: "var(--pl-muted)" }}>Or:</span>
          {matches.slice(1).map((m) => (
            <button
              key={m.part_id}
              type="button"
              className="pl-voice-alt"
              onClick={() => count != null && onConfirm(m.part_id, count)}
            >
              {m.name}
            </button>
          ))}
        </div>
      )}
      <button type="button" className="pl-voice-edit" onClick={onCancel}>
        <Pencil className="h-3 w-3" /> none of these — type instead
      </button>
    </div>
  );
}
```

- [ ] **Step 13.2: Append CSS**

Append to `src/styles/parts-library.css`:

```css
/* ---------- Voice confirm card ---------- */
.parts-lib .pl-voice-confirm {
  background: var(--pl-card);
  border: 2px solid var(--pl-yellow);
  border-radius: 12px;
  padding: 14px;
  display: flex; flex-direction: column; gap: 12px;
  box-shadow: 0 8px 28px rgba(247,184,1,.18);
}
.parts-lib .pl-voice-head { display: flex; justify-content: space-between; align-items: center; }
.parts-lib .pl-voice-lbl { font-size: 10px; font-weight: 800; color: var(--pl-amber-ink); letter-spacing: .14em; }
.parts-lib .pl-voice-x { background: transparent; border: none; color: var(--pl-muted); cursor: pointer; }
.parts-lib .pl-voice-line { display: flex; align-items: center; gap: 10px; }
.parts-lib .pl-voice-name { flex: 1; font-size: 16px; font-weight: 800; color: var(--pl-navy); }
.parts-lib .pl-voice-x-mul { color: var(--pl-muted); font-size: 18px; }
.parts-lib .pl-voice-count {
  width: 80px; height: 44px; padding: 0 10px;
  font-size: 18px; font-weight: 800; text-align: right;
  border: 1px solid var(--pl-line-2); border-radius: 8px;
  font-variant-numeric: tabular-nums;
}
.parts-lib .pl-voice-save {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  height: 48px; border-radius: 10px;
  background: var(--pl-yellow); color: var(--pl-navy); border: none;
  font-size: 15px; font-weight: 800; cursor: pointer;
}
.parts-lib .pl-voice-save:disabled { opacity: .5; cursor: not-allowed; }
.parts-lib .pl-voice-alts { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.parts-lib .pl-voice-alt {
  background: #fff; color: var(--pl-navy);
  border: 1px solid var(--pl-line-2);
  border-radius: 99px;
  padding: 5px 10px; font-size: 12px; font-weight: 600; cursor: pointer;
}
.parts-lib .pl-voice-alt:hover { background: var(--pl-hover); }
.parts-lib .pl-voice-edit {
  background: transparent; border: none; color: var(--pl-muted);
  font-size: 12px; cursor: pointer; align-self: flex-start;
  display: inline-flex; gap: 6px; align-items: center;
}
```

- [ ] **Step 13.3: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/components/payroll/parts/VoiceConfirmCard.tsx src/styles/parts-library.css
git commit -m "feat(parts): VoiceConfirmCard for voice match review"
```

---

## Task 14: CountSessionStart component

**Files:**
- Create: `src/components/payroll/parts/CountSessionStart.tsx`

- [ ] **Step 14.1: Implement**

```tsx
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { PART_CATEGORIES, type PartCategory } from "@/components/payroll/parts/CategoryIcon";
import { useStartCountSession } from "@/hooks/parts/useCountSession";

interface Props {
  onStarted: (sessionId: number) => void;
}

export function CountSessionStart({ onStarted }: Props) {
  const today = new Date().toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
  const [name, setName] = useState(`Count ${today}`);
  const [selected, setSelected] = useState<Set<PartCategory>>(new Set(PART_CATEGORIES));
  const start = useStartCountSession();

  const toggle = (c: PartCategory) =>
    setSelected((prev) => {
      const next = new Set(prev);
      next.has(c) ? next.delete(c) : next.add(c);
      return next;
    });

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 16, padding: 16 }}>
      <h2 style={{ fontSize: 22, fontWeight: 700, color: "var(--pl-navy)" }}>New count session</h2>
      <div className="space-y-1">
        <Label htmlFor="cs-name">Session name</Label>
        <Input id="cs-name" value={name} onChange={(e) => setName(e.target.value)} />
      </div>
      <div>
        <Label style={{ marginBottom: 8, display: "block" }}>Categories to count</Label>
        <div style={{ display: "flex", flexWrap: "wrap", gap: 6 }}>
          {PART_CATEGORIES.map((c) => (
            <button
              key={c}
              type="button"
              onClick={() => toggle(c)}
              style={{
                padding: "6px 12px",
                borderRadius: 99,
                border: `1px solid ${selected.has(c) ? "var(--pl-yellow)" : "var(--pl-line-2)"}`,
                background: selected.has(c) ? "var(--pl-amber-soft)" : "#fff",
                color: "var(--pl-navy)",
                fontSize: 12,
                fontWeight: 600,
                cursor: "pointer",
              }}
            >
              {c}
            </button>
          ))}
        </div>
      </div>
      <Button
        variant="accent"
        size="lg"
        disabled={!name.trim() || start.isPending}
        onClick={async () => {
          const id = await start.mutateAsync({ name: name.trim(), categories: Array.from(selected) });
          onStarted(id);
        }}
      >
        {start.isPending ? "Starting…" : "Start counting"}
      </Button>
    </div>
  );
}
```

- [ ] **Step 14.2: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/components/payroll/parts/CountSessionStart.tsx
git commit -m "feat(parts): CountSessionStart screen"
```

---

## Task 15: CountSessionPerSku component

**Files:**
- Create: `src/components/payroll/parts/CountSessionPerSku.tsx`

- [ ] **Step 15.1: Implement**

```tsx
import { useEffect, useMemo, useState } from "react";
import { Mic, MicOff, SkipForward, ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useVoiceCount } from "@/hooks/parts/useVoiceCount";
import { parseVoice } from "@/lib/parts/voiceParse";
import { matchVoiceQuery, type MatchablePart } from "@/lib/parts/voiceMatch";
import { VoiceConfirmCard } from "./VoiceConfirmCard";
import { useCommitCountLine, useSkipCountLine } from "@/hooks/parts/useCountLines";

export interface CountSku {
  id: number;
  name: string;
  category: string;
  on_hand: number;
}

interface Props {
  sessionId: number;
  pending: CountSku[];
  allParts: MatchablePart[];
  totalCount: number;
  doneCount: number;
  onAllDone: () => void;
  onBack: () => void;
}

const DISCREPANCY_PCT = 50;
const DISCREPANCY_UNITS = 10;

export function CountSessionPerSku({ sessionId, pending, allParts, totalCount, doneCount, onAllDone, onBack }: Props) {
  const sku = pending[0];
  const [draft, setDraft] = useState<number | "">("");
  const [showVoice, setShowVoice] = useState(false);
  const [voiceMatches, setVoiceMatches] = useState<{ count: number | null; matches: ReturnType<typeof matchVoiceQuery> }>({ count: null, matches: [] });
  const [noteOpen, setNoteOpen] = useState(false);
  const [note, setNote] = useState("");

  const voice = useVoiceCount();
  const commit = useCommitCountLine();
  const skip = useSkipCountLine();

  // When the voice transcript lands, parse + match.
  useEffect(() => {
    if (!voice.transcript) return;
    const parsed = parseVoice(voice.transcript);
    const matches = parsed.query ? matchVoiceQuery(parsed.query, allParts) : [];
    setVoiceMatches({ count: parsed.count, matches });
    setShowVoice(true);
  }, [voice.transcript, allParts]);

  // Reset draft when SKU changes.
  useEffect(() => {
    setDraft("");
    setNote("");
    setNoteOpen(false);
  }, [sku?.id]);

  const isBigDelta = useMemo(() => {
    if (!sku || draft === "") return false;
    const expected = Number(sku.on_hand ?? 0);
    const counted = Number(draft);
    if (!Number.isFinite(counted)) return false;
    const deltaUnits = Math.abs(counted - expected);
    if (deltaUnits >= DISCREPANCY_UNITS) return true;
    if (expected === 0) return counted !== 0;
    return Math.abs(counted - expected) / expected * 100 >= DISCREPANCY_PCT;
  }, [sku, draft]);

  if (!sku) {
    onAllDone();
    return null;
  }

  const handleSave = async (overridePart?: { id: number }) => {
    if (draft === "" || !Number.isFinite(Number(draft))) return;
    if (isBigDelta && !noteOpen) {
      setNoteOpen(true);
      return;
    }
    const partId = overridePart?.id ?? sku.id;
    await commit.mutateAsync({
      sessionId,
      partId,
      counted: Number(draft),
      note: note.trim() || undefined,
    });
    setDraft("");
    setNote("");
    setNoteOpen(false);
  };

  const handleSkip = async () => {
    await skip.mutateAsync({ sessionId, partId: sku.id });
  };

  const pct = Math.round((doneCount / Math.max(totalCount, 1)) * 100);

  return (
    <div style={{ display: "flex", flexDirection: "column", gap: 14, padding: 14 }}>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", fontSize: 12, color: "var(--pl-muted)" }}>
        <button type="button" onClick={onBack} style={{ background: "transparent", border: "none", display: "inline-flex", gap: 6, alignItems: "center", color: "var(--pl-navy)", cursor: "pointer" }}>
          <ArrowLeft className="h-4 w-4" /> Back
        </button>
        <span className="num">{doneCount} / {totalCount}</span>
      </div>
      <div style={{ height: 6, background: "var(--pl-line)", borderRadius: 99 }}>
        <div style={{ height: "100%", width: `${pct}%`, background: "var(--pl-navy)", borderRadius: 99, transition: "width 200ms" }} />
      </div>

      <div>
        <div style={{ fontSize: 11, fontWeight: 700, color: "var(--pl-muted)", textTransform: "uppercase", letterSpacing: ".06em" }}>{sku.category}</div>
        <h2 style={{ fontSize: 22, fontWeight: 800, color: "var(--pl-navy)", margin: "2px 0 0", lineHeight: 1.2 }}>{sku.name}</h2>
        <div style={{ marginTop: 8, fontSize: 13, color: "var(--pl-muted)" }}>
          System says <b className="num" style={{ color: "var(--pl-navy)" }}>{sku.on_hand}</b>
        </div>
      </div>

      <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
        <input
          type="number"
          inputMode="numeric"
          autoFocus
          value={draft}
          onChange={(e) => setDraft(e.target.value === "" ? "" : Number(e.target.value))}
          placeholder="You counted…"
          style={{
            height: 64,
            fontSize: 28,
            fontWeight: 800,
            textAlign: "center",
            border: "1px solid var(--pl-line-2)",
            borderRadius: 12,
            color: "var(--pl-navy)",
            fontVariantNumeric: "tabular-nums",
          }}
          aria-label="Counted"
        />
        <div style={{ display: "flex", gap: 8 }}>
          <Button
            variant={voice.listening ? "destructive" : "outline"}
            onClick={() => (voice.listening ? voice.stop() : voice.start())}
            disabled={!voice.supported}
            style={{ flex: 1, height: 48 }}
          >
            {voice.listening ? <><MicOff className="h-5 w-5" /> Stop</> : <><Mic className="h-5 w-5" /> Voice</>}
          </Button>
          <Button variant="ghost" onClick={handleSkip} style={{ height: 48 }}>
            <SkipForward className="h-4 w-4" /> Skip
          </Button>
        </div>
      </div>

      {noteOpen && (
        <div style={{ background: "var(--pl-amber-soft)", border: "1px solid #FDE68A", borderRadius: 8, padding: 12 }}>
          <div style={{ fontSize: 12, fontWeight: 700, color: "var(--pl-amber-ink)" }}>That's a big change — quick note?</div>
          <input
            type="text"
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Optional"
            style={{ marginTop: 6, width: "100%", height: 36, padding: "0 10px", border: "1px solid var(--pl-line-2)", borderRadius: 6, fontSize: 13 }}
          />
        </div>
      )}

      <Button variant="accent" size="lg" onClick={() => handleSave()} disabled={draft === ""} style={{ height: 56, fontSize: 16 }}>
        Save & next
      </Button>

      {showVoice && (
        <VoiceConfirmCard
          count={voiceMatches.count}
          matches={voiceMatches.matches}
          onConfirm={(partId, count) => {
            setDraft(count);
            handleSave({ id: partId });
            setShowVoice(false);
          }}
          onCancel={() => setShowVoice(false)}
          onEditCount={(n) => setVoiceMatches((v) => ({ ...v, count: n }))}
        />
      )}
    </div>
  );
}
```

- [ ] **Step 15.2: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/components/payroll/parts/CountSessionPerSku.tsx
git commit -m "feat(parts): CountSessionPerSku screen with mic + discrepancy prompt"
```

---

## Task 16: CountSessionEnd component

**Files:**
- Create: `src/components/payroll/parts/CountSessionEnd.tsx`

- [ ] **Step 16.1: Implement**

```tsx
import { Button } from "@/components/ui/button";
import { useCountLines } from "@/hooks/parts/useCountLines";
import { useFinishCountSession } from "@/hooks/parts/useCountSession";

interface Props {
  sessionId: number;
  onFinished: () => void;
}

export function CountSessionEnd({ sessionId, onFinished }: Props) {
  const { data: lines } = useCountLines(sessionId);
  const finish = useFinishCountSession();

  const counted = (lines ?? []).filter((l) => l.status === "counted");
  const skipped = (lines ?? []).filter((l) => l.status === "skipped");
  const discrepancies = counted.filter((l) => Number(l.delta_pct ?? 0) >= 50 || Math.abs(Number(l.counted ?? 0) - Number(l.expected ?? 0)) >= 10);

  return (
    <div style={{ padding: 16, display: "flex", flexDirection: "column", gap: 14 }}>
      <h2 style={{ fontSize: 22, fontWeight: 800, color: "var(--pl-navy)" }}>Count summary</h2>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 10 }}>
        <Stat label="Counted" value={counted.length} />
        <Stat label="Skipped" value={skipped.length} />
        <Stat label="Discrepancies" value={discrepancies.length} alert={discrepancies.length > 0} />
      </div>

      {discrepancies.length > 0 && (
        <section>
          <h3 style={{ fontSize: 14, fontWeight: 700, color: "var(--pl-navy)", margin: "8px 0" }}>Discrepancies to review</h3>
          <ul style={{ margin: 0, padding: 0, listStyle: "none", display: "flex", flexDirection: "column", gap: 8 }}>
            {discrepancies.map((d) => (
              <li key={d.id} style={{ background: "#fff", border: "1px solid var(--pl-line)", borderRadius: 8, padding: 10 }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: "var(--pl-navy)" }}>Part #{d.part_id}</div>
                <div style={{ fontSize: 12, color: "var(--pl-muted)" }} className="num">
                  expected {d.expected ?? 0} → counted {d.counted ?? 0} ({Math.round(Number(d.delta_pct ?? 0))}% off)
                </div>
                {d.note && <div style={{ fontSize: 12, fontStyle: "italic", marginTop: 4 }}>{d.note}</div>}
              </li>
            ))}
          </ul>
        </section>
      )}

      <Button
        variant="accent"
        size="lg"
        disabled={finish.isPending}
        onClick={async () => {
          await finish.mutateAsync(sessionId);
          onFinished();
        }}
        style={{ height: 56, fontSize: 16 }}
      >
        Finish session
      </Button>
    </div>
  );
}

function Stat({ label, value, alert }: { label: string; value: number; alert?: boolean }) {
  return (
    <div style={{
      background: alert ? "var(--pl-amber-soft)" : "#fff",
      border: `1px solid ${alert ? "#FDE68A" : "var(--pl-line)"}`,
      borderRadius: 8, padding: "10px 12px", textAlign: "center",
    }}>
      <div style={{ fontSize: 11, fontWeight: 700, color: alert ? "var(--pl-amber-ink)" : "var(--pl-muted)", textTransform: "uppercase", letterSpacing: ".06em" }}>{label}</div>
      <div style={{ fontSize: 22, fontWeight: 800, color: "var(--pl-navy)" }} className="num">{value}</div>
    </div>
  );
}
```

- [ ] **Step 16.2: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/components/payroll/parts/CountSessionEnd.tsx
git commit -m "feat(parts): CountSessionEnd summary screen"
```

---

## Task 17: CountSession page + route + entry CTA

**Files:**
- Create: `src/pages/payroll/CountSession.tsx`
- Modify: `src/App.tsx`
- Modify: `src/pages/payroll/PartsLibrary.tsx`

- [ ] **Step 17.1: Implement CountSession page**

Create `src/pages/payroll/CountSession.tsx`:

```tsx
import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { supabase as supabaseTyped } from "@/integrations/supabase/client";
import { useActiveCountSession, useCancelCountSession } from "@/hooks/parts/useCountSession";
import { useCountLines } from "@/hooks/parts/useCountLines";
import { CountSessionStart } from "@/components/payroll/parts/CountSessionStart";
import { CountSessionPerSku, type CountSku } from "@/components/payroll/parts/CountSessionPerSku";
import { CountSessionEnd } from "@/components/payroll/parts/CountSessionEnd";
import type { MatchablePart } from "@/lib/parts/voiceMatch";

const supabase = supabaseTyped as any;

export default function CountSession() {
  const navigate = useNavigate();
  const [sessionId, setSessionId] = useState<number | null>(null);
  const [showEnd, setShowEnd] = useState(false);
  const cancel = useCancelCountSession();

  const { data: active } = useActiveCountSession();
  const effectiveSession = sessionId ?? active?.id ?? null;

  const { data: lines } = useCountLines(effectiveSession);

  const { data: parts } = useQuery({
    queryKey: ["payroll", "parts", "for-count"],
    queryFn: async () => {
      const { data, error } = await supabase
        .from("payroll_parts_prices")
        .select("id, name, category, on_hand, is_one_time, track_inventory")
        .order("name");
      if (error) throw error;
      return data as Array<{ id: number; name: string; category: string | null; on_hand: number; is_one_time: boolean; track_inventory: boolean }>;
    },
  });

  const { data: aliases } = useQuery({
    queryKey: ["parts_voice_aliases"],
    queryFn: async () => {
      const { data, error } = await supabase.from("parts_voice_aliases").select("alias, part_id");
      if (error) throw error;
      return (data ?? []) as Array<{ alias: string; part_id: number }>;
    },
    staleTime: 60_000,
  });

  const allMatchable: MatchablePart[] = useMemo(() => {
    if (!parts) return [];
    const aliasByPart = new Map<number, string[]>();
    for (const a of aliases ?? []) {
      if (!aliasByPart.has(a.part_id)) aliasByPart.set(a.part_id, []);
      aliasByPart.get(a.part_id)!.push(a.alias);
    }
    return parts.map((p) => ({ id: p.id, name: p.name, aliases: aliasByPart.get(p.id) ?? [] }));
  }, [parts, aliases]);

  const pending: CountSku[] = useMemo(() => {
    if (!parts || !active) return [];
    const trackable = parts.filter((p) => p.track_inventory && !p.is_one_time);
    const filteredCats = active.categories_filter ?? [];
    const inFilter = filteredCats.length === 0
      ? trackable
      : trackable.filter((p) => filteredCats.includes(p.category ?? "Uncategorized"));
    const handled = new Set((lines ?? []).map((l) => l.part_id));
    return inFilter
      .filter((p) => !handled.has(p.id))
      .map((p) => ({ id: p.id, name: p.name, category: p.category ?? "Uncategorized", on_hand: Number(p.on_hand ?? 0) }));
  }, [parts, active, lines]);

  const totalCount = useMemo(() => {
    if (!parts || !active) return 0;
    const trackable = parts.filter((p) => p.track_inventory && !p.is_one_time);
    const filteredCats = active.categories_filter ?? [];
    return filteredCats.length === 0
      ? trackable.length
      : trackable.filter((p) => filteredCats.includes(p.category ?? "Uncategorized")).length;
  }, [parts, active]);

  const doneCount = (lines ?? []).length;

  // No active session — show start screen.
  if (!active) {
    return (
      <div className="parts-lib">
        <CountSessionStart onStarted={(id) => setSessionId(id)} />
      </div>
    );
  }

  // End screen toggled or all done.
  const everythingHandled = pending.length === 0;
  if (showEnd || everythingHandled) {
    return (
      <div className="parts-lib">
        <CountSessionEnd sessionId={active.id} onFinished={() => navigate("/payroll/parts")} />
      </div>
    );
  }

  return (
    <div className="parts-lib" style={{ paddingBottom: 80 }}>
      <CountSessionPerSku
        sessionId={active.id}
        pending={pending}
        allParts={allMatchable}
        totalCount={totalCount}
        doneCount={doneCount}
        onAllDone={() => setShowEnd(true)}
        onBack={() => navigate("/payroll/parts")}
      />
      <div style={{ position: "fixed", bottom: 12, left: 12, right: 12, display: "flex", gap: 10 }}>
        <button
          type="button"
          style={{ flex: 1, height: 44, background: "#fff", border: "1px solid var(--pl-line-2)", borderRadius: 8, color: "var(--pl-navy)", fontWeight: 600 }}
          onClick={() => setShowEnd(true)}
        >
          End early
        </button>
        <button
          type="button"
          style={{ flex: 1, height: 44, background: "#fff", border: "1px solid var(--pl-line-2)", borderRadius: 8, color: "var(--pl-red-ink)", fontWeight: 600 }}
          onClick={async () => {
            if (!confirm("Cancel this session? Counts already saved will stay in audit log.")) return;
            await cancel.mutateAsync(active.id);
            navigate("/payroll/parts");
          }}
        >
          Cancel session
        </button>
      </div>
    </div>
  );
}
```

- [ ] **Step 17.2: Add route in App.tsx**

In `src/App.tsx`, near the existing `/payroll/parts` route:

```tsx
const PayrollPartsCount = lazy(() => import("./pages/payroll/CountSession"));
```

```tsx
<Route
  path="/payroll/parts/count"
  element={
    <ProtectedRoute>
      <AppShellWithNav>
        <Suspense fallback={<PageSpinner />}>
          <PayrollGuard><PayrollPartsCount /></PayrollGuard>
        </Suspense>
      </AppShellWithNav>
    </ProtectedRoute>
  }
/>
```

- [ ] **Step 17.3: Add CTAs in PartsLibrary**

In `src/pages/payroll/PartsLibrary.tsx`:

1. Desktop top bar: add a primary "Start count session" button next to "New part":

```tsx
<Button variant="accent" size="sm" onClick={() => navigate("/payroll/parts/count")}>
  Start count session
</Button>
```

Add `import { useNavigate } from "react-router-dom";` and `const navigate = useNavigate();`.

2. Mobile floating action button — render only when `isMobile`:

```tsx
{isMobile && (
  <button
    type="button"
    onClick={() => navigate("/payroll/parts/count")}
    style={{
      position: "fixed", right: 16, bottom: 16, height: 56, padding: "0 18px",
      borderRadius: 28, background: "var(--pl-yellow)", color: "var(--pl-navy)",
      fontSize: 15, fontWeight: 800, border: "none", boxShadow: "0 8px 22px rgba(247,184,1,.4)",
    }}
  >
    Count
  </button>
)}
```

- [ ] **Step 17.4: Typecheck + commit**

```bash
./node_modules/.bin/tsc --noEmit
git add src/pages/payroll/CountSession.tsx src/App.tsx src/pages/payroll/PartsLibrary.tsx
git commit -m "feat(parts): /payroll/parts/count route + entry CTAs"
```

---

## Task 18: Build + manual QA pass

**Files:**
- (none — verification only)

- [ ] **Step 18.1: Build**

```bash
npm run build
```

Expected: clean build. Fix any type / import errors found.

- [ ] **Step 18.2: Run all tests**

```bash
npx vitest run
```

Expected: voiceParse tests pass; existing payroll/scorecard tests still pass; pre-existing Deno-shaped failures (https imports) unchanged.

- [ ] **Step 18.3: Push branch**

```bash
git push -u origin <branch>
```

- [ ] **Step 18.4: Open PR via GitHub API**

Use the API technique from `reference_gh_via_api.md`. Title: `Parts & Inventory redesign — info tooltips, mobile cards, voice count session`. Body should reference the spec and list the migrations applied.

- [ ] **Step 18.5: Smoke test on Vercel preview**

Open the preview URL once Vercel deploys.

Desktop:
- [ ] Hover each stat tile — info icon shows the canonical copy
- [ ] "Start count session" button visible next to "New part"
- [ ] Layout otherwise unchanged

Mobile (375px viewport, dev tools):
- [ ] Stat tiles show as 2x2
- [ ] Tabs show: Parts | Reorder | Activity
- [ ] Parts tab shows PartCards with on-hand input (44px tall, numeric keyboard)
- [ ] Floating "Count" button bottom-right

Count session (mobile):
- [ ] Start screen: name + category chips
- [ ] Per-SKU: shows progress bar, system count, big input, mic button
- [ ] Tapping mic listens; saying "thirty" fills the count
- [ ] Saying a name + count opens the voice confirm card
- [ ] Save & next advances to the next SKU
- [ ] Skip works
- [ ] Big delta (>50% or >10 units) triggers the optional note prompt
- [ ] End screen lists discrepancies and closes the session on Finish

If any check fails, file a follow-up task; do not block the merge unless the failure prevents core counting.

---

## Self-review (performed on this plan)

**Spec coverage:**

- Sec "Layout per breakpoint" → Tasks 5 (info tips), 6 (PartCard), 7 (mobile tabs), 8 (mobile parts list)
- Sec "Components → Part card" → Task 6
- Sec "Components → Stat tile copy" → Task 5 + canonical strings file
- Sec "Count session" (Start, Per-SKU, Confirm, End, Resume) → Tasks 14, 15, 13, 16, 17
- Sec "Voice handler" (capture, parse, match, confirm) → Tasks 9, 10, 11, 13
- Sec "Data model" (3 tables) → Tasks 2, 3, 4
- Sec "API surface" (5 RPCs) → Task 4
- Sec "Commit semantics" (atomic write) → covered inside `commit_count_line` body
- Sec "UI primitives reused" → reused via existing imports
- Sec "Testing plan" → Task 9 unit tests, Task 18 manual QA

**Placeholder scan:** no `TODO`, `TBD`, `implement later`, `similar to Task N`, or vague "handle errors" steps. All code blocks contain complete implementations.

**Type consistency:**
- `CountSku` type defined in Task 15 and consumed in Task 17 — names match.
- `MatchablePart` defined in Task 10, consumed in Task 15 + 17.
- `useCommitCountLine` arg shape `{ sessionId, partId, counted, note }` consistent across hook def (Task 12) and usage (Task 15).
- RPC arg names (`p_session_id`, `p_part_id`, `p_counted`, `p_note`) match between SQL (Task 4) and JS (Task 12).
- `inventory_count_lines` columns match between migration (Task 2) and hook return shape (Task 12).

**Known small risks flagged for the implementer:**
- Speech recognition behavior on iOS Safari may need a feature-detect fallback message; Task 11 already returns a `supported` flag — surface it visibly if false.
- The `CountSessionPerSku` "back" button currently navigates back to the parts page rather than the previous SKU; if Daniel asks for true previous-SKU navigation, that is a follow-up.
- Existing parts-library tests do not exercise the mobile breakpoint. Manual QA in Task 18 is the safety net for now.
