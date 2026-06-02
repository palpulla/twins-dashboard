# Twins Garage Doors — GHL AI Agent — Design

**Status:** Draft, ready for plan
**Author:** Claude (with Daniel)
**Date:** 2026-06-02
**Platform:** GoHighLevel (Dunzo agency account — `app.godunzo.com`, location "Twins Garage Doors")
**Deliverable type:** Configuration playbook + ready-to-paste content. No custom code.

## Problem

Twins has one live CSR (Ivory). The phone, the website chat widget, and SMS are all wired into the **Dunzo** GHL account. Outside Ivory's hours, four things bleed business:

1. **Phone rings out** after hours. Hot, ready-to-book callers (garage stuck, broken spring) hang up.
2. **Web/text leads go cold.** A 9pm chat or text sits until morning; the customer already called a competitor.
3. **The same FAQs** (hours, service area, brands, "do you do X") burn Ivory's time even during the day.
4. **No record** of what was said on calls, so coaching, follow-up, and lead quality are invisible.

GHL ships native tools for all four. The work is not building software. It is configuring those tools correctly and feeding them the right content.

## Goal

Stand up a GHL-native AI agent stack that, off a single shared Knowledge Base:

- Answers FAQs and captures leads on the **website chat widget + SMS** (Conversation AI).
- Answers and captures **after-hours phone calls** (Voice AI).
- Produces a **summary of every call** (GHL native call summary).

All capture-and-confirm: the AI qualifies, answers, and collects details, then a human confirms the actual appointment. HCP remains the source of truth for jobs. Conversation AI and Voice AI go live together in one push.

## Key decisions (settled with Daniel)

| Decision | Choice | Why |
|---|---|---|
| Build platform | 100% GHL-native, zero custom code | GHL already hosts the channels; native tooling covers every need |
| Account | Dunzo (agency GHL) | All inbound channels live there today; it works immediately |
| Booking authority | **Capture + human confirms** | No double-booking, no misrouted tech; HCP stays source of truth |
| Rollout | Conversation AI **and** Voice AI at once, off one KB | Same brain, one push |
| Call summaries | **GHL native to start** | Avoid premature custom build; revisit only if native proves too thin |
| Escalation | None in v1 | Capture-and-confirm only; no emergency paging logic yet |

## Non-goals

- **No direct calendar booking.** The bot never writes a confirmed appointment. It captures intent; a human confirms.
- **No custom call-summary pipeline.** GHL native only. A Claude-powered upgrade is a *possible future phase*, not this spec.
- **No GHL → HCP sync changes.** This spec touches no dashboard code and no existing sync.
- **No emergency escalation / on-call paging.** Deferred.
- **No invented business facts.** The bot never quotes a price, fee, or membership number that Daniel has not supplied (see Guardrails).
- **No outbound pinging of Daniel.** Summaries and captured leads live in GHL for review; nothing is pushed to his phone.

## The shape: one foundation, three native tools

```
        ┌─────────────────────────────┐
        │   KNOWLEDGE BASE (shared)    │  real facts only · exported copy owned by Twins
        │  services · area · hours ·   │
        │  brands · pricing guardrails │
        └──────────────┬──────────────┘
        ┌──────────────┼──────────────┐
   Conversation AI   Voice AI      Native Call
   (web chat + SMS) (after-hrs     Summary
                     phone)        (on by default)
                     │
                     ▼
        ┌─────────────────────────────┐
        │  Captured lead, tagged       │
        │  "AI-captured · needs confirm"│
        │  → Ivory confirms each AM     │
        └─────────────────────────────┘
```

---

## Section 0 — Knowledge Base (the foundation)

Every bot is only as smart as this. Both Conversation AI and Voice AI read from it. Build it once.

### 0a. Facts intake checklist (Daniel supplies — bot cannot rely on these until filled)

Until a value is provided, the bot's instructions force a safe fallback ("a technician will confirm that") instead of a guess.

- [ ] **Business hours** (the live-CSR hours — defines "after hours")
- [ ] **Service area** (Madison WI + how many miles / which counties)
- [ ] **Services offered** (e.g. spring repair, opener install/repair, off-track, full door install, tune-ups, commercial?)
- [ ] **Brands serviced / installed** (LiftMaster, Clopay, etc. — real list only)
- [ ] **Service-call / diagnostic fee** (exact $ or "waived with repair" — real policy only)
- [ ] **TwinShield protection plan** pricing/terms (cross-ref the TwinShield protection-plans spec; use real numbers only)
- [ ] **Financing / warranty** basics, if offered
- [ ] **What the bot must NOT do** (e.g. never promise same-day, never quote a final repair price sight-unseen)

### 0b. Known facts (already confirmed, safe to load)

- Brand: **Twins Garage Doors**, Madison, WI
- Phone: **(608) 888-8785**
- Email: **daniel@twinsgaragedoors.com**
- Site: **twinsgaragedoors.com**
- Tone: friendly, local, straightforward. Brand colors yellow + navy (for any widget styling).

### 0c. Portability insurance

Keep a master copy of the finished KB content as a plain document Twins owns (Google Doc / repo file). If the agency relationship ever ends, the entire brain rebuilds in Twins' own GHL in an afternoon instead of from scratch.

---

## Section 1 — Conversation AI (web chat widget + SMS)

### Setup

- **Create new bot → "General Q&A"** template (not "Appointment booking" — we capture, we do not auto-book).
- **Channels:** website chat widget + SMS. (FB/IG optional, same bot, enable later.)
- **Knowledge Base:** attach the Section 0 KB.
- **Business-hours awareness:** bot is the after-hours responder and the daytime FAQ-deflection layer. During CSR hours, on any request to talk to a person or any complex/billing issue, it hands off to Ivory rather than competing with her.

### Bot instructions (paste into the bot's prompt/personality field — fill `{{...}}` from Section 0a)

```
You are the virtual assistant for Twins Garage Doors, a local garage door
company in Madison, WI. You answer questions and help customers get scheduled.

VOICE: Friendly, local, plain-spoken. Short sentences. No corporate jargon.
Never use em-dashes. You are helpful, never pushy.

WHAT YOU KNOW: Only what is in your Knowledge Base. Our phone is
(608) 888-8785. Hours: {{HOURS}}. We serve {{SERVICE_AREA}}. We work on
{{BRANDS}}. We do {{SERVICES}}.

HARD RULES:
1. Never invent a price, fee, financing term, or membership detail. If a
   customer asks about cost and the Knowledge Base does not have an exact
   answer, say: "A technician will confirm exact pricing once we know what's
   going on with your door. I can get you scheduled so we can take a look."
2. Never promise a specific appointment time or same-day service. You collect
   their preferred window; a team member confirms the actual time.
3. If asked something you don't know, do not guess. Capture their info and
   say someone will follow up.

WHEN SOMEONE WANTS SERVICE, collect, one question at a time:
- Name
- Service address (and whether it's residential or commercial)
- What's wrong / what they need
- Best phone number
- Preferred day or time window
Then say: "Got it. Someone from Twins will confirm your appointment first
thing. You can also reach us at (608) 888-8785." Tag the contact and create
the opportunity (see below).

EMERGENCIES: If a customer describes a stuck-open door, a car trapped inside,
or a safety issue, tell them to call (608) 888-8785 directly, and still
capture their details.
```

### Capture workflow (GHL)

On a completed capture, the bot/workflow must:
1. Create or update the **contact** with name, phone, address, notes.
2. Create an **opportunity** in the sales pipeline (stage: "New / needs confirm").
3. Apply tag **`AI-captured · needs confirm`**.

### Morning-confirm loop

Ivory works a saved **Smart List / conversation filter** = contacts tagged `AI-captured · needs confirm`. She calls each, confirms the real slot, books it in HCP (source of truth), and clears the tag. This is the human checkpoint that makes capture-and-confirm safe.

---

## Section 2 — Voice AI (after-hours phone)

### Setup

- Enable **Voice AI** on the inbound number, scheduled for **after-hours + no-answer overflow**.
- **Same Knowledge Base** as Section 1.
- **Same capture-and-confirm behavior**, spoken. Books nothing. Collects the same fields, logs to the contact, applies the same `AI-captured · needs confirm` tag, promises a morning confirm.

### Voice script brain

Reuse the Section 1 instructions verbatim, with a spoken greeting:

```
"Thanks for calling Twins Garage Doors. Our team is out right now, but I can
take down what you need and have someone confirm your appointment first thing.
What's going on with your garage door?"
```

Same hard rules: no invented prices, no promised times, emergencies routed to the callback while still capturing details.

### Cost + soft launch

- Voice AI is **metered per minute.** Pin GHL's current per-minute rate before go-live so there's no surprise bill. Keep prompts tight to keep calls short.
- Because "both at once" puts voice live immediately and voice mistakes are louder than text, run a **1-week supervised window:** Daniel/Ivory review the call summaries each morning before fully trusting it. Adjust the KB and instructions from what real callers actually say.

---

## Section 3 — Call summaries (GHL native)

- Turn on GHL's **native call recording + AI summary** for inbound calls (covers Voice AI calls and human calls).
- Summaries live on the contact's conversation timeline in GHL. Nothing is pushed to Daniel's phone.
- This is the morning-review surface for the Section 2 soft launch.
- **Revisit trigger:** if after ~2 weeks the native summaries are too thin to coach from or judge lead quality, open a *separate* future spec for a Claude-powered summary upgrade (pull recordings into Supabase, summarize with Claude, surface silently in twins-dash). Not in scope now.

---

## Guardrails (load-bearing)

- **No fabricated operational data.** Prices, fees, membership terms, service promises: real values from Section 0a only, or the bot defers to a human. This is non-negotiable and is enforced in the bot instructions, not just by convention.
- **No outbound pinging.** Captured leads and summaries are reviewed inside GHL; nothing texts or calls Daniel automatically.
- **Customer-facing copy uses no em-dashes.**
- **HCP is the source of truth for jobs.** The AI never writes a confirmed booking.

## Who executes

- **Claude:** authors all content — the Knowledge Base body, the bot instructions, the voice greeting, the tag/workflow spec, the morning-confirm saved-list definition.
- **Daniel:** supplies the Section 0a real facts; decides go-live.
- **Aman (intern) or Daniel:** clicks through the GHL configuration following the playbook.

## Success criteria

1. A test chat on the website widget answers a known FAQ correctly and, on a service request, captures all five fields and applies the `AI-captured · needs confirm` tag.
2. A test after-hours call does the same by voice and produces a readable native summary.
3. On a pricing question with no KB answer, the bot defers to a human instead of inventing a number.
4. Ivory has a single saved list of AI-captured leads to confirm each morning.
5. Twins holds an exported master copy of the Knowledge Base content.

## Rollout

One push: KB built → Conversation AI live (chat + SMS) → Voice AI live (after-hours) → native summaries on → 1-week supervised review → tune from real conversations.

## Possible future phases (out of scope now)

- Claude-powered structured call summaries inside twins-dash.
- Emergency escalation / on-call paging.
- Direct calendar booking with GHL → HCP sync.
- Migration of the whole stack into Twins' own GHL for full ownership.
