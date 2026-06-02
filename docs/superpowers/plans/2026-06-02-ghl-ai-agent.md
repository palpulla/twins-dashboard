# Twins Garage Doors — GHL AI Agent — Implementation Plan

> **For the person configuring this:** This is a GHL (Dunzo account) setup playbook, not a code project. Work the tasks top to bottom. Each task tells you exactly where to click, exactly what content to paste, and how to verify it before moving on. Steps use checkbox (`- [ ]`) syntax so you can track progress. GHL menu labels may differ by a word or two between versions; the tab names match what's visible in the AI Agents area today (Getting Started · Voice AI · Conversation AI · Knowledge Base · Agent Templates · Content AI · Agent Logs).

**Goal:** Stand up a GHL-native AI agent stack — Conversation AI (web chat + SMS), Voice AI (after-hours phone), and native call summaries — off one shared Knowledge Base, capturing leads for a human to confirm.

**Architecture:** One Knowledge Base feeds two bots. Both capture-and-confirm: qualify, answer FAQs, collect lead details, tag `AI-captured · needs confirm`. A human confirms the real appointment in HCP each morning. No direct booking, no custom code.

**Tech Stack:** GoHighLevel (Dunzo agency account) — Knowledge Base, Conversation AI, Voice AI, native call recording/summary, Smart Lists, Automation workflows.

**Spec:** `docs/superpowers/specs/2026-06-02-ghl-ai-agent-design.md`

---

## File / artifact map

This plan produces GHL configuration, not files. The artifacts it creates:

- **1 Knowledge Base** ("Twins Garage Doors") — the shared brain
- **1 exported KB master doc** — Twins-owned portability copy
- **1 Conversation AI bot** — web chat widget + SMS
- **1 Voice AI agent** — inbound number, after-hours schedule
- **1 capture workflow** — sets tag + creates opportunity
- **1 Smart List** — Ivory's morning-confirm queue
- **Native call summary** — toggled on

---

## Task 1: Gather the real facts (intake)

Nothing else can ship correctly until these are real values. Do not guess; leave a field blank rather than invent it, and the bot will defer to a human for anything blank.

**Artifact:** a filled copy of this list. Keep it open; Tasks 2 and 5 paste from it.

- [ ] **Step 1: Fill in each value**

| Field | Your real value |
|---|---|
| `HOURS` (live-CSR hours = defines "after hours") | |
| `SERVICE_AREA` (Madison WI + miles/counties) | |
| `SERVICES` (springs, openers, off-track, install, tune-ups, commercial?) | |
| `BRANDS` (LiftMaster, Clopay, etc. — real list) | |
| `SERVICE_FEE` (exact $ or "waived with repair", or leave blank) | |
| `TWINSHIELD` (plan price/terms, or leave blank) | |
| `FINANCING_WARRANTY` (basics if offered, or blank) | |
| `NEVER_DO` (e.g. "never promise same-day", "never quote final repair price sight-unseen") | |

- [ ] **Step 2: Verify**

Every row either has a real value or is intentionally blank. No placeholder text like "approx" or "around." Blank is safe; invented is not.

---

## Task 2: Build the Knowledge Base

**Where:** AI Agents → **Knowledge Base** → create new → name it `Twins Garage Doors`.

- [ ] **Step 1: Paste this KB body, substituting the Task 1 values**

Replace each `{{TOKEN}}` with the matching Task 1 value. If a Task 1 field was left blank, **delete that line entirely** (do not write "unknown").

```
TWINS GARAGE DOORS — KNOWLEDGE BASE

ABOUT
Twins Garage Doors is a local garage door company serving {{SERVICE_AREA}},
based in Madison, WI. Phone: (608) 888-8785. Email: daniel@twinsgaragedoors.com.
Website: twinsgaragedoors.com.

HOURS
Our team is available {{HOURS}}. Outside those hours, the assistant takes
details and a team member confirms the appointment first thing.

SERVICES
We handle: {{SERVICES}}.

BRANDS
We work on: {{BRANDS}}.

PRICING
Service/diagnostic fee: {{SERVICE_FEE}}.
Exact repair pricing is confirmed by a technician after seeing the door.
TwinShield protection plan: {{TWINSHIELD}}.
Financing / warranty: {{FINANCING_WARRANTY}}.

SCHEDULING
We do not auto-book. We collect the customer's preferred time window and a
team member confirms the actual appointment and books it.

WHAT WE NEVER DO
{{NEVER_DO}}
We never quote a final repair price before a technician sees the door.
We never promise a specific time or same-day service in the assistant.
```

- [ ] **Step 2: Save and let GHL finish processing the KB** (status shows ready/indexed).

- [ ] **Step 3: Verify**

Open the KB. Confirm: phone reads (608) 888-8785, no `{{TOKEN}}` text remains, and no line states a price you did not supply in Task 1.

---

## Task 3: Export a master copy you own

Insurance: if the agency relationship ends, you rebuild in minutes.

- [ ] **Step 1:** Copy the finished KB body from Task 2 into a Google Doc (or a file in this repo) titled `Twins KB master — 2026-06-02`. Store it in Twins' own Drive, not the agency's.

- [ ] **Step 2: Verify** the doc opens from an account Twins controls.

---

## Task 4: Create the Conversation AI bot

**Where:** AI Agents → **Conversation AI** → Create Bot.

- [ ] **Step 1:** Choose the **General Q&A** template (NOT Appointment booking). Name it `Twins Assistant`.

- [ ] **Step 2:** Attach Knowledge Base = `Twins Garage Doors` (from Task 2).

- [ ] **Step 3:** Enable channels: **Website chat widget** and **SMS**. Leave FB/IG off for now.

- [ ] **Step 4:** Set business-hours behavior: bot responds at all times; during the `HOURS` from Task 1, if the customer asks for a person or raises a billing/complex issue, it hands off to a live rep (Ivory) instead of answering.

- [ ] **Step 5: Verify** the bot shows: template General Q&A, KB attached, two channels enabled. Do not test yet — instructions come in Task 5.

---

## Task 5: Paste the bot instructions

**Where:** the `Twins Assistant` bot → personality / instructions / prompt field.

- [ ] **Step 1: Paste this exactly, substituting the Task 1 values** (same substitution rule: replace `{{TOKEN}}`, delete the line if that field was blank)

```
You are the virtual assistant for Twins Garage Doors, a local garage door
company in Madison, WI. You answer questions and help customers get scheduled.

VOICE: Friendly, local, plain-spoken. Short sentences. No corporate jargon.
Never use em-dashes. Helpful, never pushy.

WHAT YOU KNOW: Only what is in your Knowledge Base. Our phone is
(608) 888-8785. Hours: {{HOURS}}. We serve {{SERVICE_AREA}}. We work on
{{BRANDS}}. We do {{SERVICES}}.

HARD RULES:
1. Never invent a price, fee, financing term, or membership detail. If asked
   about cost and the Knowledge Base has no exact answer, say: "A technician
   will confirm exact pricing once we know what's going on with your door. I
   can get you scheduled so we can take a look."
2. Never promise a specific appointment time or same-day service. Collect their
   preferred window; a team member confirms the actual time.
3. If asked something you don't know, do not guess. Capture their info and say
   someone will follow up.

WHEN SOMEONE WANTS SERVICE, collect, one question at a time:
- Name
- Service address (residential or commercial?)
- What's wrong / what they need
- Best phone number
- Preferred day or time window
Then say: "Got it. Someone from Twins will confirm your appointment first
thing. You can also reach us at (608) 888-8785."

EMERGENCIES: If a customer describes a stuck-open door, a car trapped inside,
or a safety issue, tell them to call (608) 888-8785 directly, and still
capture their details.
```

- [ ] **Step 2: Verify** no `{{TOKEN}}` remains and the phone number is correct in the pasted text. Save/publish the bot.

---

## Task 6: Build the capture workflow (tag + opportunity)

**Where:** Automation → Workflows → Create. Name it `AI capture → needs confirm`.

- [ ] **Step 1:** Trigger = Conversation AI captures a lead / bot marks intent complete (use the bot-completion or "Customer Replied / captured contact" trigger available for the `Twins Assistant` bot).

- [ ] **Step 2:** Action 1 — Add/Update Contact with the collected fields (name, phone, address, notes).

- [ ] **Step 3:** Action 2 — Create Opportunity in your sales pipeline, stage **"New / needs confirm"** (create that stage if it doesn't exist).

- [ ] **Step 4:** Action 3 — Apply tag **`AI-captured · needs confirm`** (create the tag).

- [ ] **Step 5: Verify** the workflow is published and the tag exists in Settings → Tags.

---

## Task 7: Build Ivory's morning-confirm Smart List

**Where:** Contacts → Smart Lists → create. Name it `AI-captured — confirm today`.

- [ ] **Step 1:** Filter = contacts with tag `AI-captured · needs confirm`.

- [ ] **Step 2:** Save it as a shared/visible list so Ivory sees it.

- [ ] **Step 3:** Write the one-line SOP for Ivory and pin it where she'll see it:
  > "Each morning, work the `AI-captured — confirm today` list. Call each contact, confirm the real appointment time, book it in HCP, then remove the `AI-captured · needs confirm` tag."

- [ ] **Step 4: Verify** the Smart List loads and the tag filter is active.

---

## Task 8: Test Conversation AI end to end

- [ ] **Step 1 — FAQ test:** On the live website chat widget, ask a known KB question (e.g. "what areas do you serve?"). Expected: correct answer pulled from the KB, no invented detail.

- [ ] **Step 2 — No-price-invention test:** Ask "how much to fix a broken spring?" Expected: the bot does NOT quote a number; it gives the "a technician will confirm pricing" line and offers to schedule.

- [ ] **Step 3 — Capture test:** Say "my garage door won't open, I need someone out." Expected: bot collects name, address, problem, phone, preferred window, then gives the "someone will confirm first thing" close.

- [ ] **Step 4 — Workflow test:** Check Contacts. Expected: the test contact now carries tag `AI-captured · needs confirm`, has an opportunity in "New / needs confirm," and appears in the `AI-captured — confirm today` Smart List.

- [ ] **Step 5 — SMS test:** Text the Twins number the same "need service" message. Expected: same capture behavior over SMS.

If any step fails, fix the KB (Task 2) or bot instructions (Task 5) and re-test before moving on.

---

## Task 9: Enable Voice AI on the inbound number

**Where:** AI Agents → **Voice AI**.

- [ ] **Step 1:** Create a Voice AI agent named `Twins Voice`. Attach Knowledge Base = `Twins Garage Doors`.

- [ ] **Step 2:** Assign it to the main inbound number, scheduled for **after-hours** (the inverse of the Task 1 `HOURS`) plus **no-answer overflow** during hours.

- [ ] **Step 3:** Point its post-call actions at the same `AI capture → needs confirm` workflow (Task 6), so voice leads get the same tag + opportunity.

- [ ] **Step 4: Verify** the agent shows KB attached, schedule = after-hours + overflow, and the capture workflow linked.

---

## Task 10: Set the Voice AI brain + greeting

**Where:** the `Twins Voice` agent → instructions/prompt.

- [ ] **Step 1:** Paste the **same instruction block from Task 5** (same hard rules, same five capture fields), then set the spoken greeting:

```
Thanks for calling Twins Garage Doors. Our team is out right now, but I can
take down what you need and have someone confirm your appointment first thing.
What's going on with your garage door?
```

- [ ] **Step 2: Verify** the greeting has no em-dashes, the hard rules match Task 5, and the agent is published.

---

## Task 11: Pin the Voice AI cost before trusting it

- [ ] **Step 1:** In GHL's Voice AI billing/usage info, record the **current per-minute rate** and any per-call minimum. Write it in the KB master doc from Task 3.

- [ ] **Step 2: Verify** you have a real number, so a busy after-hours night holds no billing surprise.

---

## Task 12: Test Voice AI with a real call

- [ ] **Step 1 — After-hours call test:** Outside the Task 1 `HOURS`, call the Twins number from a personal phone. Expected: `Twins Voice` answers with the greeting.

- [ ] **Step 2 — Capture test:** Describe a broken door and a preferred time. Expected: it collects the five fields by voice and closes with the morning-confirm promise. It does NOT quote a price or promise a time.

- [ ] **Step 3 — Workflow test:** Confirm the call created a contact tagged `AI-captured · needs confirm`, an opportunity, and a Smart List entry.

- [ ] **Step 4 — Overflow test (optional):** During hours, let a call ring unanswered and confirm Voice AI picks up the overflow.

---

## Task 13: Turn on native call summaries

**Where:** Settings → Phone/Calls (call recording) and the Voice AI / call settings for AI summary.

- [ ] **Step 1:** Enable inbound **call recording** and **AI call summary**.

- [ ] **Step 2: Verify** the Task 12 test call now shows a recording + a readable summary on the contact's conversation timeline. Nothing is texted/pushed to Daniel — the summary lives in GHL for review.

---

## Task 14: One-week supervised launch + tuning loop

Because both channels went live together and voice mistakes are louder than text, watch it for a week before fully trusting it.

- [ ] **Step 1:** Each morning for 7 days, Daniel or Ivory reads the prior night's **call summaries** and the `AI-captured — confirm today` list.

- [ ] **Step 2:** Note any miss — a wrong answer, a missed capture field, a price the bot shouldn't have touched.

- [ ] **Step 3:** Fix it at the source: update the **KB** (Task 2) for facts, or the **bot/voice instructions** (Tasks 5/10) for behavior. Re-export the KB master (Task 3) after KB edits.

- [ ] **Step 4: Verify (end of week):** the five success criteria from the spec hold —
  1. Chat answers a known FAQ and captures all five fields + applies the tag.
  2. A voice call does the same and produces a readable summary.
  3. A pricing question with no KB answer is deferred to a human, not invented.
  4. Ivory has one saved list of leads to confirm each morning.
  5. Twins holds an exported master copy of the KB.

---

## Self-review notes

- **Spec coverage:** KB (Task 2) · portability (Task 3) · Conversation AI chat+SMS (Tasks 4–5, 8) · capture-and-confirm tag/opportunity/Smart List (Tasks 6–7) · Voice AI after-hours (Tasks 9–12) · native summaries (Task 13) · no-fabrication guardrail (enforced in Tasks 2/5/10, tested in Tasks 8/12) · cost pinning (Task 11) · supervised launch (Task 14). All spec sections map to a task.
- **No invented data:** every business fact routes through the Task 1 intake; blank-means-defer is the rule, repeated at each paste step.
- **Both-at-once rollout** honored: Conversation AI and Voice AI both reach "live" before the Task 14 review window.
- **Out of scope (unchanged):** custom Claude summaries, emergency escalation, direct calendar booking, migration to Twins' own GHL.
