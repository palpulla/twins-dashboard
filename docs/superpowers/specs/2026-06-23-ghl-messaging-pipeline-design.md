# Twins Garage Doors — Customer Messaging Pipeline (GHL, one number) — Design

**Status:** Approved design (Option A). Ready for implementation plan. **No implementation until Daniel approves the plan.**
**Author:** Claude (with Daniel)
**Date:** 2026-06-23
**Supersedes:** `2026-06-22-ghl-messaging-visibility-design.md` (its findings are folded in here).
**Platforms:** HouseCall Pro (HCP, job/estimate system of record) · GoHighLevel (Dunzo agency account, single customer messaging channel) · Supabase project `jwrpjuqaynownxaoeayi` (the reliable bridge) · `twins-dash` repo for bridge code.

## Why this exists

Daniel asked to improve how Twins serves and acquires customers across the whole journey. Digging into the data showed the operational spine (book → tech-on-the-way → done → paid) works, but the **relationship messaging around it is thin or missing**, and the one funnel metric we report is broken. The approved direction: route relationship messaging through **GHL from a single number**, triggered reliably off HCP events, so the journey is consistent and **fires 100% of the time without breaking** (Daniel's load-bearing requirement).

## Verified findings (data, 2026-06-23, project `jwrpjuqaynownxaoeayi`)

- **Lead → job booking rate is under-reported.** Dashboard shows 16.7% (186 of 1,113 GHL leads). True rate ~30.6% (341 ever became an HCP job). Cause: the matcher (`twins-dash/supabase/functions/_shared/ghl/ghl-matcher.ts`) **stalled ~May 24** (0 matches in last 7 days) and **drops repeat customers** as "ambiguous." The 30-day window is not a material cause. (Held workstream, see below.)
- **Post-booking journey, 4,228 completed jobs:** on-my-way fires on 98%, started 95%, completed 100%, balance cleared 81%. **Only 27% of completed jobs get a review** (1,142 rated). After completion there is **zero** follow-up (no thank-you, membership, tune-up, or win-back).
- **Links are not in our data.** Searched every synced HCP record: invoice link, HCP pay/portal link, and GoodLeap link return **0 hits**. They exist only inside HCP. (Verified, drives the channel split below.)
- **Estimate vs Job tickets are distinct:** 2,477 estimate tickets (have `estimate_number` + `options`) vs 4,003 job tickets (have `invoice_number`). HCP marks **estimates as "completed" too**, which is a trap for any review trigger.

## Goal

A single, reliable customer messaging pipeline:
- **GHL one number** sends all relationship messages (lead/estimate follow-up, review request, financing, retention) using fixed links so nothing per-job can break.
- **HCP keeps** the two link-dependent transactional texts it does natively and well: on-my-way (live GPS tracking) and the invoice/receipt (carries the live pay link + warranty + T&Cs).
- Triggered off HCP events with a reconciliation safety net so coverage is effectively 100% and provable.

## Approved architecture (Option A)

```
HCP (job/estimate events)
   → HCP webhook  [already live; never disabled]
   → Supabase bridge: (a) upsert customer into GHL by phone, (b) check idempotent send-log,
                      (c) trigger the right GHL workflow/tag for the stage
   → GHL (ONE number) sends the stage message using merge fields + fixed links
   → Customer
```

### Reliability design (the load-bearing part)

1. **Event-driven trigger** off the existing HCP webhook pipe (the same one the dashboard already depends on). No third-party middleware (Zapier/Make) that can silently break.
2. **Reconciliation sweep** (scheduled ~every 15 min): scans every ticket whose status implies a message is due and confirms it actually sent; re-sends any miss. This is what turns "usually" into "always."
3. **Idempotent send-log** table keyed by (ticket_id + stage): each message sends at most once; coverage is provable ("every completed job in June got its review request").
4. **Silent health view**: a status pill on the dashboard. No SMS/email/push to Daniel (per standing rule). Observability only.

### Contact bridging
Messaging must work for **all** jobs, not just GHL-sourced ones. The bridge upserts each HCP customer into GHL by normalized phone (reuse the existing phone-normalizer) so every customer exists as a GHL contact before any message triggers.

### Channel split (minimum numbers + maximum reliability)
- **GHL (one number):** lead reply & follow-up, estimate close follow-up, review request, GoodLeap financing nudges, membership / annual tune-up / win-back.
- **HCP (native, unchanged):** on-my-way text, invoice/receipt. Rebuilding these in GHL would require fetching per-job links from HCP (a moving part that can break). **Option B (collapse to literally one number by syncing the invoice link) was considered and declined** for that reason.

## Two-track journey + message catalog

All copy: friendly, local, plain. **No em-dashes** (customer-facing). `{{merge}}` = GHL/HCP merge field. Fixed links inserted verbatim:
- GoodLeap financing: `https://www.goodleap.dev/twinsgaragedoorsllc/8fcb0f0d-2f74-4026-bb3c-6e93a3d18e3d`
- Google review: `https://g.page/r/CYMu-jkURnx7EAI/review`

### Pre-booking (lead track, GHL)
- **Instant reply (<5 min):** "Hi {{first}}, this is Twins Garage Doors in Madison. Thanks for reaching out! We'd love to get your door taken care of. What's going on with it, and what's the best address for a visit? Or call us at (608) 888-8785."
- **Missed-call text-back:** "Hi, this is Twins Garage Doors. Sorry we missed you! We can still help with your garage door. Text us what's going on and your address, or call back at (608) 888-8785."
- **After-hours capture:** "Thanks for reaching Twins Garage Doors! Our team is out right now. Text us your name, address, and what's wrong with the door, and we'll confirm your appointment first thing in the morning. Emergency? Call (608) 888-8785."
- **No-book follow-up, Day 1 / 3 / 7:** (close-the-lead nudges; Day 7 is the soft last touch). Full copy in the brainstorm screen `messaging-suggestions.html`.

### Estimate track (GHL) — NO review request
- **Estimate follow-up Day 1:** "Hi {{first}}, it's Twins Garage Doors. Did you get a chance to look over your estimate? Happy to walk through the options. We can also help you spread the cost with financing: {{goodleap_link}}."
- **Estimate follow-up Day 3:** "Hi {{first}}, just checking in on your garage door estimate. If you'd like to lock it in we can get you on the schedule. Call (608) 888-8785."
- Financing (GoodLeap) stays available on the estimate.

### Job track (GHL unless noted)
- **1. Booking confirmation:** "You're booked with Twins Garage Doors, {{first}}! We'll see you {{appt_window}} at {{address}}. Your tech will text when they're on the way. Questions? (608) 888-8785."
- **2. Reminder (morning of / night before):** "Reminder: Twins Garage Doors is scheduled for {{appt_window}} today at {{address}}. Reply C to confirm, or call (608) 888-8785 to reschedule."
- **3. On-my-way:** **HCP native** (live tracking link). Not rebuilt.
- **5. Job done / thank-you:** "All done, {{first}}! Thanks for trusting Twins Garage Doors. {{tech}} left your door running smoothly. If anything feels off, call us right away at (608) 888-8785, we stand behind our work."
- **6. Invoice / receipt:** **HCP native.** Already carries the live pay link + warranty + T&Cs. GHL does not restate warranty (Daniel confirmed it prints on the invoice).
- **7. Review request (the big lift, 27% → goal higher):**
  - SMS ~2h after completion: "{{first}}, it was a pleasure helping with your garage door today! A quick review really helps our small local team. Would you leave one here? https://g.page/r/CYMu-jkURnx7EAI/review Thank you, the Twins crew."
  - Email backstop 2 days later if no review: subject "How did we do, {{first}}?" body invites a Google review, offers to make anything right by reply. Full copy in `messaging-suggestions.html`.
- **8. After the job (retention):** membership/TwinShield nudge (1–2 wks), annual tune-up reminder (~11–12 mo), win-back for quiet customers. Copy in the brainstorm screens.
- **Financing on Job ticket (new, Daniel's idea):** for bigger repairs/installs: "Bigger repair or a new door, {{first}}? You can apply for financing with Twins in a few minutes through GoodLeap: https://www.goodleap.dev/twinsgaragedoorsllc/8fcb0f0d-2f74-4026-bb3c-6e93a3d18e3d Questions? (608) 888-8785."

### Trigger mapping
- Estimate events → estimate-track workflows. Job events → job-track workflows.
- **Review trigger fires on Job tickets only** (those with `invoice_number`), never estimate tickets (which HCP also marks "completed"). This guardrail is explicit because the naive rule would misfire.

## Guardrails (load-bearing)

- **No fabricated operational data.** Service fee, TwinShield price/benefits, warranty wording, financing terms: real values from Daniel only, or omitted. Bot/templates never invent.
- **No em-dashes** in any customer-facing copy.
- **Review requests on Job tickets only.** Never on estimates.
- **Never disable HCP webhooks** (live ingest; re-enabled 2026-05-26 after an incident).
- **No outbound pinging of Daniel.** Health is a silent dashboard view only.
- **Idempotent.** No customer is double-texted; the same number sends consistently.
- **Reversible; KPIs immutable.** Any KPI-touching change (the held matcher fix) ships with a diff and approval; KPI math is never silently altered. HCP stays source of truth; no GHL → HCP writes.

## Held workstream — booking-rate matcher fix

Not bundled. When taken up: diagnose the ~May 24 stall (cron/timeout), stop discarding repeat customers, backfill the ~155 hidden bookings, surface the corrected rate in `GhlAttributionPanel`. Treated as a KPI change (diff + approval).

## Facts still needed from Daniel (so nothing is invented)
- Service-call / diagnostic fee.
- TwinShield plan name, price, exact benefits.
- Business hours (defines "after hours").
- Financing terms beyond the link, if any messaging should state them.
- Confirm warranty + T&C wording is fully covered by the HCP invoice (so GHL never restates it).
- Preferred timing windows (review delay, reminder lead time, retention cadence).

## Out of scope
- Opportunity-stage sync (not needed for messaging).
- Rebuilding HCP-native templates (on-my-way, invoice) in GHL (Option B declined).
- GHL → HCP writes; HCP stays source of truth.
- Redesigning the dashboard beyond the silent health pill and the held booking-rate panel.

## Open questions (non-blocking)
- Whether to first **audit the messages GHL/HCP send today** (a short read-only pass) before flipping on new workflows, to avoid double-texting. Recommended as plan step 1.
- Exact reconciliation interval (default ~15 min) and review-request delay (default ~2h post-completion).

## Success criteria
1. For every completed **job** ticket, the review request sends exactly once (provable from the send-log); estimates never get one.
2. A dropped webhook does not cause a missed message: the reconciliation sweep catches it within one interval.
3. All relationship messages come from the single GHL number; on-my-way + invoice remain HCP-native.
4. GoodLeap financing appears on both estimate and job tracks via the fixed link.
5. No customer is double-texted; no message invents a price/term; no em-dashes.
6. Daniel can glance at a silent health pill to confirm the pipeline is flowing.

## Who executes
- **Claude:** bridge edge function(s), idempotent send-log + reconciliation cron, contact-upsert, health view, and all GHL workflow content/copy. Builds only after plan approval.
- **Daniel:** supplies the remaining facts; approves the plan; decides go-live.
- **Daniel / Aman:** wires the GHL workflows/templates in the GHL UI following the playbook.
