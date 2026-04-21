# Brandeis Recruiting Email Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce a reusable Cowork prompt that drafts a personalized Brandeis recruiting email per student resume into Daniel's Gmail, validated against one sample resume in this session before batch use.

**Architecture:** A single self-contained prompt file. Daniel pastes it into each Cowork session (one per resume). The session has the resume attached + Gmail draft access. The prompt instructs Claude to read the resume, personalize, self-check, and create a Gmail draft. Validation happens in this session: Daniel attaches one sample resume, we run the prompt logic manually to produce the email text, iterate until right, then lock the prompt.

**Tech Stack:** Markdown (the prompt is text). Cowork for execution. Gmail connector for draft creation.

**Spec reference:** [docs/superpowers/specs/2026-04-21-brandeis-recruiting-email-design.md](../specs/2026-04-21-brandeis-recruiting-email-design.md)

---

## File Structure

- Create: `docs/superpowers/prompts/2026-04-21-brandeis-recruiting-cowork-prompt.md`
  Responsibility: the exact prompt text Daniel pastes into Cowork. Self-contained — includes the email skeleton, personalization rules, tone guardrails, self-check, Gmail draft instruction, and output format. This is the deliverable.
- Create: `docs/superpowers/plans/2026-04-21-brandeis-recruiting-email-sample-output.md` (validation artifact)
  Responsibility: record the sample email produced during validation + any iteration notes. Not shipped — serves as evidence the prompt works and as a reference example.

No code, no tests in the unit-test sense. Validation is empirical: does the sample email meet every criterion in spec Section 2.

---

## Task 1: Draft the Cowork Prompt (v1)

**Files:**
- Create: `docs/superpowers/prompts/2026-04-21-brandeis-recruiting-cowork-prompt.md`

- [ ] **Step 1: Create the prompts directory if it doesn't exist**

Run: `mkdir -p /Users/daniel/twins-dashboard/docs/superpowers/prompts`

- [ ] **Step 2: Write the prompt file with exactly this content**

The prompt file must contain all of the following sections, in order, as a single Markdown file. Copy the content verbatim (the prompt itself is the deliverable — its exact wording matters).

````markdown
# Brandeis Recruiting Email — Cowork Prompt

Paste this entire prompt into a Cowork session that has (a) one student's resume attached and (b) Daniel's Gmail connected with draft-creation permission. Claude will produce one Gmail draft per run.

---

You are helping Daniel (co-founder of Twins Garage Doors, Madison WI) write a personalized recruiting email to a Brandeis University student whose resume is attached to this session.

## Context you need

**About Twins Garage Doors:**
- Family-run garage door company in Madison, Wisconsin.
- Co-founded by twin brothers Tal and Daniel.
- Tal: BS Chemical Engineering (Yale), MS + PhD Mechanical Engineering (MIT).
- Daniel: BS UC Davis.
- The business is profitable and growing. We're building toward a sale to private equity in the next couple of years.

**The role we're hiring for:**
- Remote.
- Primary mission: help us streamline operations, build accountability systems, and scale the business so we can sell it.
- AI is the leverage, not the job. We want to use AI (Claude, automation, agents) aggressively wherever it makes us faster and more accountable. But the work is operational — process design, metrics, systems thinking — with AI as the multiplier throughout.
- We have not hired for this kind of role before. It is undefined by design. We want someone who will shape it.
- Preferred long-term, but we're happy to start as an internship and grow into full-time.
- Works directly with the founders.

**Who we want:**
- Initiative. Thinks beyond the job description.
- Comfortable with ambiguity.
- Sharp enough that founders with Yale/MIT/UC Davis backgrounds want them on the team.
- Not looking for a polished corporate internship.

## What to do

### Step 1 — Read the resume carefully

Extract:
- Full name
- Email address
- School + expected graduation year
- Major(s) / concentration(s)
- Key experiences, projects, and skills
- Any signals of: initiative/scrappiness, AI/tech exposure, operational or business experience, unique angles

### Step 2 — Pick the 1–2 strongest signals on THIS specific resume

Priority order:
1. Initiative / scrappiness (side project, startup attempt, founded a club, self-directed research, hackathons)
2. Technical / AI exposure (Python, ML, LLMs, automation, data work)
3. Ops / business lean (internship at a small company, consulting, finance/ops coursework, running something operationally)
4. Major + year (decides internship-first vs. long-term-first framing)
5. Unique angle (language, unusual background, surprising deep interest)

Rules:
- Pick 1–2 signals, not all of them. Mad Libs emails fail.
- If AI/initiative signals are thin, lead with whatever the resume DOES show strongly — rigor, leadership, curiosity, grit. Every resume has something. Find it.
- When multiple strong signals exist, pick the one most relevant to "operational streamlining + scale," not the most technically impressive one.

### Step 3 — Draft the email

Follow this skeleton:

1. **Subject line** — specific hook drawn from the resume. Not "Internship opportunity at Twins Garage Doors."
2. **Opener (1–2 sentences)** — a specific, non-flattering detail from the resume showing you actually read it.
3. **Bridge (1 sentence)** — why that detail made us think of them for this role.
4. **The story (short paragraph)** — who Twins is, where we're headed (PE sale in a couple of years), and why we're hiring someone Brandeis-caliber for this kind of role for the first time.
5. **The role (3–4 bullets)** — remote; operational streamlining + accountability for scale; AI-aggressive throughout; internship → long-term, preferred long-term; works directly with founders; undefined by design.
6. **Founder credibility (1 sentence)** — Tal (Yale ChemE BS, MIT MechE MS+PhD) and Daniel (UC Davis BS). Framed as "we think like engineers — we want someone who thinks bigger than the job description."
7. **CTA** — "If this sounds interesting, reply and we'll set up a quick call."
8. **STOP. No sign-off. No signature. No "Best," or "Thanks," or names.** Daniel's Gmail adds his signature automatically. The body ends at the CTA.

Total length: 150–200 words. Short sentences. First person plural.

### Step 4 — Tone guardrails (hard rules)

- No corporate filler: do not use "exciting opportunity," "dynamic team," "fast-paced environment," "rockstar," "ninja," "passionate."
- No flattery ("your impressive background…"). Specificity replaces flattery.
- No emoji. No exclamation points.
- Honest about the PE trajectory — "building toward a sale in the next couple of years." Not cocky. Not hedged.
- Honest that the role is undefined — that's a feature. State it plainly.

### Step 5 — Self-check before you create the draft

Ask yourself:
- Does the opener reference something SPECIFICALLY from this resume (not a generic student trait)?
- Is the whole email ≤ 200 words?
- Is the CTA soft (reply if interested, not "do a task first")?
- Does the body end at the CTA with no sign-off or signature?
- Any corporate filler, emoji, or exclamation points? If yes, rewrite.
- Does the opener + bridge feel tailored, or could this email be sent to anyone?

If any check fails, revise before moving on.

### Step 6 — Create the Gmail draft

Create a draft in Daniel's connected Gmail:
- `to:` the student's email from the resume
- `from:` Daniel's connected Gmail
- `subject:` the tailored subject line
- `body:` the email body, plain text, ending at the CTA
- **Do not send.**

### Step 7 — Failure handling

- If the resume has no email address: halt. Output `NO EMAIL FOUND on resume for [Student Name] — please provide manually.` Do not create a draft.
- If the resume is unreadable or mostly blank: halt. Output `Resume unreadable for [filename] — please re-attach.` Do not create a draft.
- If you are unsure which signal to lead with: pick the strongest initiative signal. Do not ask Daniel. Proceed.

### Step 8 — Output confirmation

After creating the draft (and only after), output exactly one line:

`Draft created for [Student Name] — subject: "[subject line]"`

That is the entire output. No commentary, no email preview, no explanation.
````

- [ ] **Step 3: Commit v1 of the prompt**

```bash
cd /Users/daniel/twins-dashboard
git add docs/superpowers/prompts/2026-04-21-brandeis-recruiting-cowork-prompt.md
git commit -m "feat(recruiting): Brandeis recruiting Cowork prompt v1

Reusable prompt that drafts a personalized outreach email per student
resume and creates the Gmail draft. To be validated against one sample
resume before batch use.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Validate the Prompt Against a Sample Resume

**Files:**
- Create: `docs/superpowers/plans/2026-04-21-brandeis-recruiting-email-sample-output.md`

- [ ] **Step 1: Ask Daniel to attach one sample resume to this session**

Say to Daniel, verbatim:

> "Attach one Brandeis student resume to this session — PDF, docx, or pasted text is fine. Pick one you think is a typical case (not your top pick, not your weakest). I'll run the prompt logic against it and produce the email text for your review before we lock the prompt."

Wait for the attachment.

- [ ] **Step 2: Run the prompt logic manually on the attached resume**

Execute Steps 1–5 of the prompt (read resume → pick signals → draft → self-check) against the real resume. Do NOT call the Gmail draft tool yet — this is a text-only validation. Produce:

- The subject line
- The email body
- A short note: which 1–2 signals were picked and why

- [ ] **Step 3: Save the sample output for review**

Write the subject + body + signal-choice note to `docs/superpowers/plans/2026-04-21-brandeis-recruiting-email-sample-output.md` with this structure:

```markdown
# Sample Output — Validation Run

**Resume:** [student name]
**Date:** 2026-04-21

## Signals chosen
- Signal 1: [what + why]
- Signal 2 (if used): [what + why]

## Subject
[subject line]

## Body
[email body]

## Self-check results
- Opener references specific resume detail: [yes/no + what]
- Word count: [N]
- CTA is soft: [yes/no]
- No sign-off: [yes/no]
- No corporate filler: [yes/no]
```

- [ ] **Step 4: Present the sample to Daniel for review**

Show Daniel the subject + body in chat. Ask:

> "Here's the draft this prompt would produce for [student name]. Specifically — is the opener specific enough? Does the role framing land the way you want? Tone right? Anything to change before we lock the prompt?"

Wait for feedback.

- [ ] **Step 5: Iterate if needed**

If Daniel wants changes:
- Small wording tweaks → update the prompt file directly (Task 1 Step 2 content).
- Structural changes → update the spec first, then the prompt.
- Re-run Task 2 Steps 2–4 on the same resume to confirm the fix works.
- Commit each iteration: `git commit -m "fix(recruiting): [what changed] per validation feedback"`

Do not proceed to Task 3 until Daniel says the sample is right.

- [ ] **Step 6: Commit the sample output as the locked-in example**

```bash
cd /Users/daniel/twins-dashboard
git add docs/superpowers/plans/2026-04-21-brandeis-recruiting-email-sample-output.md
git commit -m "docs(recruiting): Brandeis prompt validation sample

Validated the Cowork prompt against one sample resume. Sample email
approved by Daniel.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Hand Off to Daniel

**Files:**
- None (chat-only handoff).

- [ ] **Step 1: Output the handoff message**

Say to Daniel, verbatim (substituting the real file path):

> "Prompt locked. Here's how to run it:
>
> 1. Open a Cowork session.
> 2. Attach one student's resume to the session.
> 3. Make sure your Gmail is connected with draft permission.
> 4. Paste the entire content of [docs/superpowers/prompts/2026-04-21-brandeis-recruiting-cowork-prompt.md](../prompts/2026-04-21-brandeis-recruiting-cowork-prompt.md) as your message.
> 5. Claude will create the draft in your Gmail and output one line: `Draft created for [Name] — subject: "..."`.
> 6. Review the draft in Gmail, edit if you want, send when ready.
>
> If a resume has no email, Cowork will halt and tell you — provide the email manually and re-run. Same for unreadable resumes.
>
> Run one new Cowork session per student. Don't reuse a session — each needs its own resume context."

- [ ] **Step 2: Offer to do the first 1–2 live batch runs together**

Say:

> "Want to do the first 1–2 real runs together here (not in Cowork), so I can spot anything the validation sample didn't cover? If yes, attach the next resume. Otherwise you're set — go run the batch in Cowork."

If Daniel wants to continue here, repeat Task 2 Steps 2–4 per resume (this time calling the Gmail draft tool at the end) until he's confident. Then stop.

---

## Self-Review Results

- **Spec coverage:** Every requirement in spec §2 Success Criteria is enforced in the prompt's Step 5 self-check. Spec §3 skeleton → prompt Step 3. Spec §4 personalization → prompt Step 2. Spec §5 tone → prompt Step 4. Spec §6 execution → prompt Steps 6–8. Spec §7 validation → Task 2.
- **Placeholders:** None. The prompt text in Task 1 Step 2 is complete.
- **Consistency:** "Draft created for [Student Name] — subject: \"[subject line]\"" is used consistently in the prompt and in the spec.
