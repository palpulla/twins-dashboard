# Marketing Corrective Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline). This work is stateful (live browser sessions, MCP auth, ad-account mutations) — do NOT dispatch fresh subagents per task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute the July 2026 Marketing Corrective Action Plan autonomously: audit Google Ads + Meta tracking, apply reversible fixes, and build the reporting/offline-conversion plumbing.

**Architecture:** A temporary `ads-audit` edge function on jwrpj reuses the existing `GOOGLE_ADS_*` secrets to run arbitrary GAQL reads and whitelisted mutations. Meta work goes through the connected Meta Ads MCP (browser fallback for Events Manager). Website work goes through WPCode + Elementor + Rank Math in the browser. Everything lands in `docs/marketing/audits/` and `docs/marketing/change-log.md`.

**Tech Stack:** Supabase edge functions (Deno), Google Ads API v23 (GAQL + mutate), Meta Marketing API via MCP, claude-in-chrome, jwrpj Postgres.

**Spec:** `docs/superpowers/specs/2026-07-04-marketing-corrective-actions-design.md`

**Constraints (from spec):** no fabricated data; mutations limited to the listed set; KPI math untouched; every ad-account change logged in `docs/marketing/change-log.md`; new paid campaigns stay PAUSED until Daniel approves.

---

## Phase 0 — Audit (read-only)

### Task 1: Deploy the `ads-audit` edge function

**Files:**
- Create: `twins-dash/supabase/functions/ads-audit/index.ts`

- [ ] **Step 1: Write the function.** Read-only GAQL executor, gated by a shared-secret header compared against an env var that already exists on jwrpj (use `EMAIL_CRON_SECRET`; if the local value can't be found in `twins-payroll/.env` / `twins-dash/.env*` / edge-function code, deploy with a fresh `ADS_AUDIT_SECRET` set via `supabase secrets set` using the CLI auth that deployed PR #327).

```typescript
// twins-dash/supabase/functions/ads-audit/index.ts
// TEMPORARY audit tool — remove after the corrective-action work completes.
const API = "v23";

async function getAccessToken(): Promise<string> {
  let refreshToken = Deno.env.get("GOOGLE_ADS_REFRESH_TOKEN")!;
  try { const p = JSON.parse(refreshToken); if (p.refresh_token) refreshToken = p.refresh_token; } catch { /* raw token */ }
  const resp = await fetch("https://oauth2.googleapis.com/token", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      client_id: Deno.env.get("GOOGLE_ADS_CLIENT_ID")!,
      client_secret: Deno.env.get("GOOGLE_ADS_CLIENT_SECRET")!,
      refresh_token: refreshToken,
      grant_type: "refresh_token",
    }),
  });
  const data = await resp.json();
  if (!resp.ok) throw new Error(`token refresh failed: ${JSON.stringify(data)}`);
  return data.access_token;
}

Deno.serve(async (req) => {
  const secret = Deno.env.get("ADS_AUDIT_SECRET") ?? Deno.env.get("EMAIL_CRON_SECRET");
  if (!secret || req.headers.get("x-audit-secret") !== secret) {
    return new Response(JSON.stringify({ error: "unauthorized" }), { status: 401 });
  }
  const { query } = await req.json();            // one GAQL string per call
  if (typeof query !== "string") return new Response(JSON.stringify({ error: "query required" }), { status: 400 });

  const customerId = Deno.env.get("GOOGLE_ADS_CUSTOMER_ID")!.replace(/-/g, "");
  const token = await getAccessToken();
  const resp = await fetch(`https://googleads.googleapis.com/${API}/customers/${customerId}/googleAds:searchStream`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "developer-token": Deno.env.get("GOOGLE_ADS_DEVELOPER_TOKEN")!,
      "login-customer-id": customerId,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ query }),
  });
  const text = await resp.text();
  return new Response(text, { status: resp.status, headers: { "Content-Type": "application/json" } });
});
```

- [ ] **Step 2: Deploy** via Supabase MCP `deploy_edge_function` to project `jwrpjuqaynownxaoeayi`.
- [ ] **Step 3: Smoke test** with `SELECT customer.id, customer.descriptive_name FROM customer LIMIT 1`. Expected: HTTP 200 with the Twins account row. A 401 means the secret guess is wrong — fix before proceeding. If the account turns out to be an MCC, list clients per the pattern in `sync-google-ads/index.ts:58` and target the Twins client id in all later queries.
- [ ] **Step 4: Commit** the function.

### Task 2: Run the Google Ads audit queries

**Files:**
- Create: `docs/marketing/audits/2026-07-04-cap/google-ads/*.json` (one file per query, raw responses)

Run each GAQL below through `ads-audit`; save raw JSON. Windows are 2026-05-01..2026-06-30 unless noted.

- [ ] **Step 1: Conversion-action inventory** (spec item 1)

```sql
SELECT conversion_action.id, conversion_action.name, conversion_action.category,
       conversion_action.type, conversion_action.status, conversion_action.origin,
       conversion_action.primary_for_goal, conversion_action.counting_type,
       conversion_action.include_in_conversions_metric,
       conversion_action.attribution_model_settings.attribution_model,
       conversion_action.view_through_lookback_window_days
FROM conversion_action WHERE conversion_action.status != 'REMOVED'
```

- [ ] **Step 2: Conversions by campaign × action × month** (settles the 34 → 19 swing and PMax's claims — spec items 1+2)

```sql
SELECT campaign.name, segments.conversion_action_name, segments.month,
       metrics.conversions, metrics.all_conversions
FROM campaign
WHERE segments.date BETWEEN '2026-05-01' AND '2026-06-30'
```

- [ ] **Step 3: View-through share** — same shape with `metrics.view_through_conversions` (no conversion_action segment; it's incompatible), per campaign per month.

- [ ] **Step 4: Search terms, cost-sorted** (spec item 3)

```sql
SELECT campaign.name, ad_group.name, search_term_view.search_term,
       metrics.cost_micros, metrics.clicks, metrics.conversions
FROM search_term_view
WHERE segments.date BETWEEN '2026-05-01' AND '2026-06-30'
ORDER BY metrics.cost_micros DESC
LIMIT 500
```

- [ ] **Step 5: Keywords + match types + campaign geo/bidding settings**

```sql
SELECT campaign.name, ad_group.name, ad_group_criterion.keyword.text,
       ad_group_criterion.keyword.match_type, ad_group_criterion.status
FROM keyword_view WHERE campaign.status = 'ENABLED'
```
```sql
SELECT campaign.id, campaign.name, campaign.status, campaign.bidding_strategy_type,
       campaign.maximize_conversions.target_cpa_micros,
       campaign.geo_target_type_setting.positive_geo_target_type,
       campaign.geo_target_type_setting.negative_geo_target_type,
       campaign_budget.id, campaign_budget.amount_micros
FROM campaign WHERE campaign.status != 'REMOVED'
```

- [ ] **Step 6: User-location report** (where spend actually lands)

```sql
SELECT campaign.name, user_location_view.country_criterion_id,
       segments.geo_target_most_specific_location, metrics.cost_micros, metrics.clicks
FROM user_location_view
WHERE segments.date BETWEEN '2026-05-01' AND '2026-06-30'
```
(If `segments.geo_target_most_specific_location` is rejected in v23, fall back to `segments.geo_target_city`.)

- [ ] **Step 7: Change history** (change_event only reaches back ~30 days — covers June 4+, enough to catch a June install-LP tag change)

```sql
SELECT change_event.change_date_time, change_event.change_resource_type,
       change_event.changed_fields, change_event.user_email, change_event.client_type
FROM change_event
WHERE change_event.change_date_time BETWEEN '2026-06-04 00:00:00' AND '2026-07-04 23:59:59'
LIMIT 200
```

- [ ] **Step 8: Commit** raw JSON outputs.

### Task 3: Meta account reads (MCP)

**Files:**
- Create: `docs/marketing/audits/2026-07-04-cap/meta/*.json`

- [ ] **Step 1:** `ads_get_ad_accounts` → account attribution spec (`attribution_spec` field); record whether view-through is included.
- [ ] **Step 2:** `ads_get_customconversions` → May/June counts for both custom conversions (fills the two [REQUEST] cells in the doc's Table 0).
- [ ] **Step 3:** `ads_get_ad_entities` on the two $0-delivery campaigns (Messenger Leads, Call Leads) → status, budget, delivery/review status. Answers "why $0".
- [ ] **Step 4: Commit.**

### Task 4: Meta lead-quality match against HCP

**Files:**
- Create: `docs/marketing/audits/2026-07-04-cap/meta-lead-booking-rate.md`

- [ ] **Step 1:** Export May–June instant-form leads. Try the Graph API path first (leadgen forms on the page via MCP tools); fallback: claude-in-chrome → Meta Leads Center export CSV. Save to the scratchpad, NOT the repo (PII).
- [ ] **Step 2:** Normalize phones (strip to 10 digits) and query jwrpj via `execute_sql`:

```sql
-- one row per lead phone; matched = customer exists; booked = any non-Estimate job
SELECT c.mobile_number, MIN(j.created_at) AS first_job_created,
       COUNT(j.id) FILTER (WHERE j.job_type <> 'Estimate') AS jobs,
       SUM(j.revenue_amount) FILTER (WHERE COALESCE((j.hcp_data->>'outstanding_balance')::numeric,0) = 0) AS earned_revenue
FROM customers c LEFT JOIN jobs j ON j.customer_id = c.id
WHERE regexp_replace(c.mobile_number, '\D', '', 'g') = ANY(:lead_phones)
GROUP BY 1;
```
(Verify actual customer table/column names via `list_tables` first; adjust. If there is no customers table, match on `jobs.customer_phone`-equivalent inside `hcp_data`.)

- [ ] **Step 3:** Write the booking-rate summary (X of 53 matched, Y booked, Z completed, revenue) — aggregate numbers only, no PII in the repo. Compute cost per booked job vs Search's $166–$234. Commit.

### Task 5: Install-LP test lead + tag observation

**Files:**
- Create: `docs/marketing/audits/2026-07-04-cap/install-lp-tag-test.md`

- [ ] **Step 1:** Open `https://twinsgaragedoors.com/wi/garage-door-installation-lp-ppc/` in Chrome; record which tags load (GTM-TSRL3M4K containers, gtag config ids, Meta pixel).
- [ ] **Step 2:** Submit the form with name "TEST LEAD - IGNORE (Claude tracking audit)", Daniel's email, the office phone. Watch network for: `google-analytics.com/g/collect`, `googleadservices.com/pagead/conversion`, `facebook.com/tr`, and the post-submit redirect URL (thank-you path).
- [ ] **Step 3:** Document: did a Google Ads conversion request fire, what thank-you URL loaded, did it match the Meta custom-conversion substring rules. Commit findings.

### Task 6: Findings report (gates Phase 1)

- [ ] **Step 1:** Write `docs/marketing/audits/2026-07-04-cap/FINDINGS.md`: the doc's open questions answered (real vs fake conversion actions; PMax verdict; install-LP break confirmed/refuted; Meta booking rate; search-term waste). Include a "Phase 1 go/no-go per fix" table — e.g., tCPA only ships if conversion actions are clean.
- [ ] **Step 2:** Commit and summarize to Daniel in chat.

---

## Phase 1 — Fixes (reversible, logged)

Every mutation gets a row in `docs/marketing/change-log.md`: date, platform, what changed, why, how to revert.

### Task 7: Extend `ads-audit` with whitelisted mutations

**Files:**
- Modify: `twins-dash/supabase/functions/ads-audit/index.ts`

- [ ] **Step 1:** Add a `mutate` op accepting `{service, payload}` restricted to an explicit allowlist — `sharedSets`, `sharedCriteria`, `campaignSharedSets`, `campaigns`, `campaignBudgets`, `campaignCriteria`, `conversionActions`, `customerNegativeCriteria`. Same header gate. POST to `https://googleads.googleapis.com/v23/customers/{cid}/{service}:mutate`.
- [ ] **Step 2:** Redeploy, smoke-test with a no-op (validateOnly: true payload), commit.

### Task 8: Google Ads hygiene mutations

All payloads below run with `validateOnly: true` first, then live. Record resource names returned (needed for revert) in the change log.

- [ ] **Step 1: Shared negative keyword list.** Create shared set "CAP negatives 2026-07", add the doc §2.1 list (diy, how to, manual, manually, parts, kit, lowes, home depot, menards, harbor freight, amazon, youtube, video, instructions, panel only, jobs, hiring, salary, training, rental, apartment, weight, lubricant, wd-40, spring cost) PLUS the top waste terms found in Task 2 Step 4, as PHRASE match. Attach to both Search campaigns via `campaignSharedSets:mutate`.
- [ ] **Step 2: Geo presence-only.** `campaigns:mutate` set `geoTargetTypeSetting.positiveGeoTargetType: PRESENCE` on all enabled campaigns whose audit value is `PRESENCE_OR_INTEREST`.
- [ ] **Step 3: Installation budget cap.** `campaignBudgets:mutate` the Installation Search budget to `amountMicros: 15000000` ($15/day). Record the prior value.
- [ ] **Step 4: Demote junk conversion actions.** For every conversion action in the Task 2 inventory that is not a call/form/booking/message and has `primary_for_goal: true` or `include_in_conversions_metric: true`: `conversionActions:mutate` set `primaryForGoal: false`. Do NOT touch actions the audit shows are the real lead signals. Skip entirely if the inventory is ambiguous — flag for the Legit5 meeting instead.
- [ ] **Step 5: Brand negatives.** Add "twins garage doors", "twins garage door", "twin garage doors" as EXACT negatives to the shared set (protects Search). PMax brand exclusions need a brand list (BrandSuggestionService); attempt via API — if unsupported for this account tier, log it as a Legit5 meeting item.
- [ ] **Step 6: tCPA on Repair — ONLY if** Task 2 shows Repair's 11–14 conversions are real lead actions: `campaigns:mutate` `maximizeConversions.targetCpaMicros: 150000000`. Otherwise defer (per doc §2.1: "a tCPA aimed at polluted conversion data locks in the pollution").
- [ ] **Step 7:** Update change log, commit.

### Task 9: Meta pixel + custom-conversion fixes

- [ ] **Step 1:** Via MCP `ads_pixel_event_read` on pixel 554750209097175, list rules. Delete/retire the 'Lead' rule firing on `/wi/thank-you-g-ppc-lp/` (`ads_pixel_event_delete`), replace with Lead rules matching the real per-form thank-you URLs discovered in Task 5 (exact path, not substring).
- [ ] **Step 2:** Custom conversions: the MCP has no update tool → claude-in-chrome into Events Manager → edit "Primary Lead Form" rule from `URL contains /thank-you` to an exact-path rule that cannot match `/ei-thank-you`. Screenshot before/after.
- [ ] **Step 3:** Change log + commit. (CAPI restoration stays blocked on Meta re-auth — noted in the log.)

### Task 10: Website sprint (twinsgaragedoors.com, browser)

Order: lowest-risk first. After each change: hard-reload the live page, verify, screenshot. All copy uses the real number (608) 888-8785 and the live review count read from the site/GBP at edit time (do not hardcode 687 without checking).

- [ ] **Step 1: Remove pinch-zoom lock.** WPCode JS snippet (site-wide, footer): rewrite the viewport meta to `width=device-width, initial-scale=1` (drop `user-scalable=0, maximum-scale=1`). Verify with `document.querySelector('meta[name=viewport]').content`.
- [ ] **Step 2: Phone unification.** WP Admin → Appearance → Menus (both main and /wi subsite): replace the (833) 833-2010 mobile-menu item with (608) 888-8785. If the 833 number turns out to be an intentional tracking number, STOP and flag instead (doc says its ownership is unknown).
- [ ] **Step 3: Sticky mobile call bar.** WPCode snippet, mobile-only (max-width 768px), fixed bottom bar: `[📞 Call Now]` → `tel:+16088888785`, `[Book Online]` → the existing Housecall Pro booking URL. Navy background, yellow buttons (brand colors). Exclude admin pages. Verify on a phone-width viewport.
- [ ] **Step 4: Hero trust badge.** Elementor edit on the homepage(s): add "4.9★ · [live count] Google Reviews · Family-owned · 24/7" strip + "$49 Tune-Up" offer above the fold. Match existing hero styling.
- [ ] **Step 5: Title/meta rewrites** (Rank Math, per doc §4.2): repair page title → `24/7 Garage Door Repair Madison | 4.9★ ([live count] Reviews)`; description with same-day promise. Same treatment for the madison-wi variant page.
- [ ] **Step 6: DIY-post CTA blocks.** On the top 5 DIY/parts posts (genie install instructions, can't-open-manually, how-to-reset, clopay low-headroom, low-headroom track — locate via WP admin search): insert a styled callout block: "This job goes wrong easily — call (608) 888-8785 for same-day help" + booking link.
- [ ] **Step 7: 'ippt' page.** Find the page ranking for "ippt meaning" (WP admin content search for "ippt"). If it's stray content: Rank Math → noindex. If it's a real service page with an odd paragraph: rewrite the paragraph.
- [ ] **Step 8: Schema.** WPCode JSON-LD snippet: LocalBusiness (name, phone 608, address, hours, aggregateRating from live GBP values) sitewide + FAQPage on pages that already have FAQ content. Validate with Google's Rich Results test.
- [ ] **Step 9: Financing surfacing.** Add a "Financing available" section/link (to the existing financing page) on the installation and repair pages.
- [ ] **Step 10:** Change log + commit; screenshots to Daniel.

---

## Phase 2 — Plumbing (build)

### Task 11: Weekly qualified-lead → Google Ads offline upload

**Files:**
- Create: `twins-dash/supabase/functions/offline-conversions-weekly/index.ts`

- [ ] **Step 1:** Design note first (this task gets its own mini-plan at execution — upload mechanics depend on Task 2's conversion-action inventory). v1 scope: enhanced conversions for leads (hashed email/phone from HCP booked jobs, no GCLID required), one `UPLOAD_CLICKS`-type conversion action "Booked Job (HCP)" created via API as SECONDARY (observation) first. Meta offline events wait for re-auth.
- [ ] **Step 2:** Implement + deploy + first manual run; verify upload accepted in the API response; commit.

### Task 12: Extend /marketing-brief to full-funnel format

**Files:**
- Modify: `.claude/skills/marketing-brief/SKILL.md` (or its data-sources doc)
- Modify: `docs/marketing/DATA-SOURCES.md`

- [ ] **Step 1:** Add the §6 funnel per channel: Spend → Leads → Qualified → Booked → Completed → Revenue, using `marketing_spend`, `jobs.lead_source`, and the §6 KPI definitions table verbatim. Clicks/CPC move to an appendix section. Where a stage has no data source yet (qualified tagging, call tracking), the brief prints "not yet measured" — never a guess.
- [ ] **Step 2:** Dry-run the brief once, commit.

### Task 13: Meta creative drafts (staged PAUSED)

- [ ] **Step 1:** Generate assets via twins-media-generator: (a) review-proof carousel cards from real GBP reviews with reviewer towns; (b) repair-emergency reel copy/storyboard ("Door won't open? Same-day spring repair in Madison"); (c) financing install image. Real offers only ($0 service call, $49 tune-up, GoodLeap financing); never same-day promises in guarantees beyond confirmed offers.
- [ ] **Step 2:** Create campaigns/ad sets/ads via MCP with `status: PAUSED`, budgets $25–30/day combined per doc §3.3. Nothing activates.
- [ ] **Step 3:** Send Daniel previews (`ads_get_ad_preview`) + the go/no-go ask. Change log + commit.

### Task 14: Legit5 email draft + wrap-up

- [ ] **Step 1:** Gmail `create_draft` to Legit5: trimmed request list = only what Claude couldn't pull (report denominators explanation + rebuilt report; rank-tracker export; 833-number ownership/call data; PageSpeed baselines optional; CAPI restoration plan; change-log standing rule). Attach nothing; reference the findings doc.
- [ ] **Step 2:** Delete or disable the `ads-audit` function's mutate path (leave read path only, or remove the function) — it was temporary.
- [ ] **Step 3:** Final report to Daniel: what changed, what's measured now, what's blocked (Meta re-auth, GBP OAuth, CallRail decision). Update memory (project file for this initiative). Commit everything.

---

## Self-review notes

- Spec coverage: spec items 1–6 → Tasks 2–6; 7 → Task 8; 8 → Task 9; 9 → Task 10; 10 → Task 11; 11 → Task 12; 12 → Task 13; Daniel items → Task 14. ✔
- Mutations all carry validateOnly-first + revert data in the change log. ✔
- Unknowns are handled as explicit stop/flag branches (833 number, ambiguous conversion inventory, PMax brand lists), not guesses. ✔
