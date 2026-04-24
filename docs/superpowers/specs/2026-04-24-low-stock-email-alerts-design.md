# Low-Stock Email Alerts Design Spec

**Date:** 2026-04-24
**Status:** Design approved, ready for implementation plan
**Scope:** Email the operator when a tracked part drops below its reorder threshold. Real-time after Finalize, with a weekly Monday-morning digest catching anything still low. Recipients = admins (auto) + non-admins who opt in.

## Context

The payroll inventory feature ([2026-04-24-inventory-tracking-design.md](2026-04-24-inventory-tracking-design.md)) stores `on_hand`, `min_stock`, and `track_inventory` per part in `payroll_parts_prices`, and decrements stock atomically on payroll Finalize via the `finalize_payroll_run` RPC. The Parts Library page and Payroll Home already flag low-stock items visually, but the operator has to be looking at the app to notice.

This spec adds an email channel so the operator finds out about low stock in their inbox — immediately when Finalize causes a crossing, and weekly on Monday as a reminder for anything still low.

The twins-dash app already has a mature email infrastructure (Resend via `supabase/functions/_shared/email/send.ts`; existing weekly-scorecard and daily-digest edge functions; `email_log`/`email_notifications` tables). This feature reuses all of it.

## Goals

- Fire an email **immediately after Finalize** for parts that just crossed below `min_stock` ("newly low"), via a fire-and-forget edge-function invocation.
- Send a **weekly Monday-morning digest** listing every part currently below threshold as a reminder, via `pg_cron`.
- Recipients are **admins (auto) + non-admins with an opt-in toggle** on the user management page. One email per recipient.
- Reuse the existing Resend-based email stack. No new third-party integrations.
- De-dupe so the operator doesn't get spammed: send-once on crossing, weekly reminder only.

## Non-goals

- No SMS / Slack / Teams notifications in v1.
- No per-part recipient routing (e.g. "Titan rollers alerts go to Tal").
- No auto-reorder / PO generation.
- No percentage-based thresholds (`min_stock` stays absolute).
- No per-admin opt-out — admins always receive.

## Data model

### Extend `payroll_parts_prices`

Add one column:

| Column | Type | Default | Purpose |
|---|---|---|---|
| `last_low_alert_at` | `TIMESTAMPTZ` | NULL | When the Finalize-triggered "newly low" email was sent for this part. Set by the RPC when a crossing is detected. Cleared by a trigger when the part's `on_hand` returns to `>= min_stock`. Only used for de-dupe; the weekly digest ignores it. |

### Reuse `user_roles.permissions` (JSONB, already exists)

Opt-in flag stored at `permissions.low_stock_alerts = true`. No schema change; just a new key inside the existing JSONB column. The recipient query reads:

```sql
SELECT DISTINCT u.email
FROM public.user_roles ur
JOIN auth.users u ON u.id = ur.user_id
WHERE (ur.role = 'admin'
   OR (ur.permissions->>'low_stock_alerts')::boolean = TRUE)
  AND u.email IS NOT NULL;
```

### Postgres trigger on `payroll_parts_prices`

Fires `BEFORE UPDATE` of `on_hand`. If the new value pushes the part's effective stock back to `>= min_stock` (and `last_low_alert_at` is currently non-null), set `last_low_alert_at = NULL` so the next drop can fire a fresh alert. "Effective" excludes `pending` from drafts (pending is ignored here — the alert is driven by absolute `on_hand`, and the UI separately shows the pending adjustment).

```sql
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

CREATE TRIGGER payroll_parts_clear_low_alert
BEFORE UPDATE ON public.payroll_parts_prices
FOR EACH ROW EXECUTE FUNCTION public.clear_low_alert_on_recovery();
```

## Trigger logic

### Real-time on Finalize

Extend the existing `finalize_payroll_run(p_run_id INT)` RPC. After the stock-decrement CTE, add one more pass that:

1. Collects parts where `old_on_hand >= min_stock AND new_on_hand < min_stock AND min_stock > 0 AND track_inventory = TRUE AND is_one_time = FALSE AND last_low_alert_at IS NULL` — the "newly low" set.
2. Sets `last_low_alert_at = NOW()` for each newly-low part.
3. Adds `newly_low: [{id, name, on_hand, min_stock, short_by}, …]` to the RPC's return JSON alongside the existing `stock_updates` / `skipped_unmatched`.

Client (`Run.tsx`) reads the `newly_low` array from the Finalize response. If non-empty, it fire-and-forgets a call to the new edge function:

```ts
supabase.functions.invoke("invoke-low-stock-email", {
  body: { kind: "finalize", part_ids: res.newly_low.map(p => p.id) },
});
```

No `await` — we don't want Finalize UX to depend on Resend responsiveness. Errors surface in `email_log`, not in the UI.

### Weekly Monday digest

New edge function `cron-weekly-lowstock`. Invoked by `pg_cron` at **Monday 07:00 America/Chicago** (UTC+5 or +6 depending on DST — cron stored as UTC, use `07:00 - offset`). The function:

1. Selects every part with `track_inventory=TRUE AND is_one_time=FALSE AND min_stock > 0 AND on_hand < min_stock`. (Pending stock is ignored for alert purposes; a part is "low" if its stored `on_hand` is below threshold.)
2. If zero results, returns early — no email sent.
3. Otherwise runs the same recipient query as above and sends one digest email per recipient via `_shared/email/send.ts`.
4. Does **not** touch `last_low_alert_at`. Digest is a reminder, not an alert; the Finalize path owns that flag.

### Pg_cron schedule

One new migration adds:

```sql
SELECT cron.schedule(
  'payroll-weekly-lowstock',
  '0 13 * * 1',  -- 07:00 America/Chicago in winter (UTC-6); in summer = 06:00 local, acceptable drift
  $$
    SELECT net.http_post(
      url := current_setting('app.edge_functions_url') || '/cron-weekly-lowstock',
      headers := jsonb_build_object('Authorization', 'Bearer ' || current_setting('app.cron_secret'))
    );
  $$
);
```

Scheduling pattern matches the existing `20260422220000_reconcile_invoices_cron.sql` migration. The edge function self-gates on the `cron_secret` header so only the cron can invoke it.

## Email content

### Real-time Finalize email

- **Subject:** `Low stock after payroll — {N} parts need reorder` (N > 1 handles plural)
- **Body:** navy/yellow branded header (reuse `_shared/email/components.tsx`). Short paragraph: *"The week-of {date} payroll just finalized. These parts dropped below their reorder threshold:"* Then a table:

| Part | On hand | Min | Short by |
|---|---:|---:|---:|
| Titan rollers | 5 | 20 | 15 |
| … | | | |

Footer: link to `https://twinsdash.com/payroll/parts` + the standard Twins email footer.

### Weekly Monday digest

- **Subject:** `Weekly low-stock reminder — {N} parts still low`
- **Body:** *"These parts have been below reorder threshold as of Monday {date}. Reorder when you get a chance."* Same table columns as the Finalize email. Same footer.

Both emails use the existing render pipeline (`_shared/email/render.tsx`) and land in `email_log` with a new `kind` value: `low_stock_finalize` or `low_stock_weekly`.

## Recipient management

### UI change — user management page

Add a **"Low-stock alerts"** checkbox column to the existing users list at `/admin/users` (or wherever `set_payroll_access` currently lives).

- Admins: checkbox shown **checked and disabled** with tooltip *"Admins receive alerts automatically."*
- Non-admins with `payroll_access`: checkbox toggleable. Writes `permissions.low_stock_alerts = true/false` via a new RPC.
- Non-admins without `payroll_access`: checkbox hidden entirely.

### New RPC

```sql
CREATE OR REPLACE FUNCTION public.set_low_stock_alerts(
  target_user_id UUID,
  allowed BOOLEAN
) RETURNS VOID
LANGUAGE plpgsql SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin') THEN
    RAISE EXCEPTION 'Only admins can change low-stock alert subscriptions';
  END IF;
  UPDATE public.user_roles
  SET permissions = COALESCE(permissions, '{}'::jsonb)
                    || jsonb_build_object('low_stock_alerts', allowed),
      updated_at = NOW()
  WHERE user_id = target_user_id;
END;
$$;
```

Mirrors the existing `set_payroll_access` pattern.

### Preview / test-send button

On `/payroll/parts`, add an admin-only **"Preview low-stock email"** button (next to Upload counts). Clicking it calls `invoke-low-stock-email` with `kind: "preview"` + a synthetic payload (3 made-up parts) addressed only to the current admin's email. Confirms end-to-end deliverability without needing a real Finalize.

## Error handling and edge cases

- **Resend 5xx / network failure:** `send.ts` already retries 3x with backoff, logs outcome to `email_log`. Finalize RPC is unaffected (fire-and-forget).
- **Empty recipient list:** skip send, log a warning entry. Not an error.
- **Concurrent Finalize by two operators on the same part:** the RPC's `SELECT … FOR UPDATE` on the run row serializes them; one clears the newly-low check via `last_low_alert_at IS NULL` gate. 5-minute debounce in the edge function (skip if any part's `last_low_alert_at` is within the last 5 minutes of the alert window) as a belt-and-suspenders guard.
- **Negative stock:** still below threshold → keeps alerting per the rules above.
- **Part toggled from tracked → untracked while low:** excluded from next query cycle. The outstanding `last_low_alert_at` stays set but is never read for that part (trigger still clears it if stock recovers).
- **Admin removed from `user_roles`:** loses alerts automatically on next send (recipient query runs live).
- **`min_stock` changed from non-zero to 0:** trigger doesn't fire on that update (no `on_hand` change), but future alerts are skipped because `min_stock > 0` is in the WHERE. `last_low_alert_at` becomes stale; acceptable cosmetic issue.
- **DST drift for weekly digest:** pg_cron stores UTC; at 13:00 UTC the digest fires at 07:00 CST (winter) or 08:00 CDT (summer). Operator sees it either way; not worth the complexity of timezone-aware cron.
- **Preview button spam:** rate-limit client-side to one per 60 seconds; edge function uses an idempotency key of `preview-{user_id}-{minute_bucket}`.

## Testing

**Unit (Vitest, pure helpers):**
- `detectNewlyLow({ before, after, min_stock })`: returns true only when `before >= min_stock AND after < min_stock AND min_stock > 0`.
- `renderLowStockEmail({ parts, kind, date })`: snapshot test that the HTML contains every part name, on_hand value, min_stock value, and the correct subject.
- `recipientSql` — integration test against a local Supabase seeded with 1 admin + 1 non-admin opt-in + 1 non-admin opt-out, asserts exactly 2 emails returned.

**Postgres smoke (`execute_sql` or pgTAP):**
1. Seed a part at `on_hand=10, min_stock=5`. Finalize a run consuming 6. Assert RPC return includes this part in `newly_low` and `last_low_alert_at` was set.
2. Update `on_hand = 20`. Assert trigger cleared `last_low_alert_at` to NULL.
3. Re-finalize a run consuming 16. Assert `newly_low` fires again (because `last_low_alert_at` was null).
4. Finalize another run consuming 2 (part still below threshold). Assert `newly_low` is **empty** (already alerted).

**Edge function integration:**
- Call `invoke-low-stock-email` with `kind: "finalize"` + 3 part ids, dry-run mode. Assert 1 Resend API call per recipient, correct subject, correct HTML table rows.
- Call `cron-weekly-lowstock` with seeded data. Assert digest email is one per recipient, subject matches, body lists all currently-low parts.
- Call with zero low parts. Assert no emails sent, log entry notes "skipped — none low."

**Manual E2E (post-deploy):**
1. Admin toggles low-stock alert for a non-admin user on the user-management page. Reloads — toggle persists.
2. Click "Preview low-stock email" on Parts Library. Admin receives sample email within 10s.
3. Finalize a real payroll run that decrements a part below its threshold. Admin + opted-in user receive the alert email.
4. Manually set one part's `on_hand` to 0 via inline edit on Parts Library (doesn't go through Finalize). Observe it shows up in Monday morning digest.
5. Reset the part's stock above threshold, finalize again to confirm `last_low_alert_at` cleared correctly.

## Out-of-scope followups

- Slack / Teams / SMS channels.
- Per-part recipient routing.
- Auto-reorder / PO generation via vendor API.
- Percentage-based thresholds (e.g., "20% of last counted quantity").
- Snooze / ack a specific part's alerts for N days.
- Per-admin opt-out.
