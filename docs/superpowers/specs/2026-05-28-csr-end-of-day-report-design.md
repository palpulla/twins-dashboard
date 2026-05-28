# Twins CSR: End of Day Report (Typeform) — Design

## Purpose

A daily end-of-shift report the CSR completes in Typeform. The goal is accountability,
manager visibility, and making sure nothing falls through the cracks at the end of a shift.
It is not a "how was your day" survey.

The booking tracker / dashboard remains the source of truth for lead counts, booked jobs,
unbooked leads, and qualified vs. unqualified. The Typeform deliberately does NOT re-ask
those numbers. It confirms the tracker is current and flags the handful of items that need
manager attention.

## Core principles

- **No duplicate data entry.** Customer details live in the booking tracker. The form never
  asks the CSR to re-type names or phone numbers. For each flagged category she confirms the
  item is logged or flagged in the tracker, and the manager pulls specifics from there.
- **No vague wording.** No "roughly how many." Counts are owned by the tracker.
- **Gated detail.** Every detail question sits behind a Yes/No gate, so a quiet day is a
  handful of taps and detail only appears when there is something real to report.
- **Required = actionable.** If the manager needs to act on it or hold someone to it, the
  field is required. Color and upside are optional.

## Dependency

The tracker needs a simple way to tag a lead: pricing objection (captured as a no-booking
reason), cancellation/waitlist, needs follow-up. If a status/tag column already exists, no
change is needed. If not, add one small tag column so the manager can filter to flagged leads.

## Form structure

### Section 1 — Shift basics
| # | Question | Field type | Required |
|---|---|---|---|
| 1 | Date of shift | Date (defaults to today) | Yes |
| 2 | Your name | Dropdown (CSR names preloaded) | Yes |

Dropdown over short text to avoid typos and allow filtering by person. Real name(s) loaded at
build time, no placeholder.

### Section 2 — Tracker confirmation
| # | Question | Field type | Required |
|---|---|---|---|
| 3 | Is the booking tracker fully updated with every call and lead from today? | Yes/No | Yes |
| 3a | (if No) What still needs to be added, and why isn't it done yet? | Long text | Yes |

Logic: Q3 = No shows Q3a. Q3 = Yes skips to Section 3.

### Section 3 — Leads that never got a response
| # | Question | Field type | Required |
|---|---|---|---|
| 4 | Were there any missed calls, voicemails, texts, or web leads that did NOT get a response today? | Yes/No | Yes |
| 4a | (if Yes) Are they logged in the tracker so they can be followed up? | Yes/No | Yes |

Logic: Q4 = Yes shows Q4a. Q4 = Yes triggers manager alert.
Why: missed calls, voicemails, unanswered texts, and web leads with no callback are silent
revenue leaks. The manager needs them same-day while the lead is warm.

### Section 4 — Unbooked qualified leads
| # | Question | Field type | Required |
|---|---|---|---|
| 5 | Did any qualified leads NOT book today? | Yes/No | Yes |
| 5a | (if Yes) Main reasons they didn't book (check all that apply) | Multi-select | Yes |

Q5a options: Price / quote objection · Scheduling or availability · Wanted to think it over ·
Getting other quotes · Needed to ask spouse or partner · Went with a competitor ·
Quoted a price over the phone · Other.

Logic: Q5 = Yes shows Q5a. Q5 = Yes triggers manager alert.
Why: a qualified lead (real job, in service area, ready) that didn't book is the highest-value
recoverable follow-up. The multi-select gives trendable reasons without per-customer entry.
The "Quoted a price over the phone" option captures the price-coaching signal here rather than
in its own section.

### Section 5 — Cancellation / waitlist opportunities
| # | Question | Field type | Required |
|---|---|---|---|
| 6 | Any customers who wanted service but couldn't book because of scheduling? | Yes/No | Yes |
| 6a | (if Yes) Have you added them to the cancellation list? | Yes/No | Yes |

Logic: Q6 = Yes shows Q6a.
Why: customers blocked only by scheduling are exactly who to call when a slot opens. Builds a
standing cancellation list instead of losing the job.

### Section 6 — Difficult or upset customers
| # | Question | Field type | Required |
|---|---|---|---|
| 7 | Any difficult or upset customers today? | Yes/No | Yes |
| 7a | (if Yes) What happened, and is it resolved or still open? | Long text | Yes |

Logic: Q7 = Yes shows Q7a. Q7 = Yes triggers manager alert.
Why: service recovery. The manager may need to step in before a bad call becomes a bad review.
Free-text here is an incident description, not a customer-roster entry.

### Section 7 — Manager action items
| # | Question | Field type | Required |
|---|---|---|---|
| 8 | Is there anything that needs the manager's action tomorrow? | Yes/No | Yes |
| 8a | (if Yes) What needs action, and how urgent? (today / this week / whenever) | Long text | Yes |
| 9 | Any questions you need answered? | Long text | No |
| 10 | Any ideas or suggestions to improve how we work? | Long text | No |

Logic: Q8 = Yes shows Q8a. Q8 = Yes triggers manager alert.
Why: one place the manager checks first thing in the morning. The "nothing falls through the
cracks" backstop.

### Section 8 — Quick check-in
| # | Question | Field type | Required |
|---|---|---|---|
| 11 | Win of the day | Short text | No |
| 12 | How did today feel? | Opinion scale 1 to 5 | Yes |
| 13 | Anything you need from your manager? | Long text | No |

Why: keeps the manager in touch with how she is doing and surfaces friction early. Kept to
fast taps so it never becomes the reason the report gets skipped.

## Required vs. optional summary

- **Required:** Date, Name, every Yes/No gate (Q3, 4, 5, 6, 7, 8), every conditional detail
  that opens, and the daily mood score (Q12).
- **Optional:** questions, ideas, win of the day, anything-you-need (Q9, 10, 11, 13).

## Field-type rationale

- **Yes/No** for gates: fastest input, and each becomes a clean daily metric to trend
  (e.g., "days with unresponded leads").
- **Long text** only where the manager needs an incident description or action item
  (upset customer, manager action). Not used for customer rosters.
- **Multi-select** only for Q5b, for structured, trendable no-booking reasons.
- **Dropdown** for name (no typos). **Opinion scale** for mood (one tap).
  **Date** defaults to today.

## Automations

1. **Standard summary email** to the manager on every submit (Typeform built-in notification).
2. **Priority "ACTION NEEDED" alert** when any of Q4, Q5, Q7, or Q8 = Yes, with a subject line
   naming what triggered it. Typeform Logic or a Make/Zapier step for cleaner formatting.
3. **Cancellation-list capture:** Q6 = Yes routes the entry to a running cancellation list
   (Google Sheet or Airtable to start).
4. **Compliance nudge (optional):** a Make/Zapier scenario that pings if no report is submitted
   by the shift-end cutoff.
5. **Dashboard later:** a future phase pipes submissions into twinsdash.com next to the CSR's
   other metrics. Manager email is the only delivery channel for v1.

## Open items to resolve at build time

- Manager email address for notifications (not guessed).
- CSR name(s) for the Section 1 dropdown.
- Confirm the tracker has (or add) a tag/status column for flagged leads.
- Shift-end cutoff time, if the compliance nudge is wanted.

## Out of scope (v1)

- Per-customer structured capture in the form (deliberately avoided to prevent duplicate entry).
- Dashboard integration (later phase).
- A standalone pricing-objection section (folded into Section 4 as a checkbox).
