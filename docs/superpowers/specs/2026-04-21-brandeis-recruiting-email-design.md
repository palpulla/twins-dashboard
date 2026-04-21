# Brandeis Recruiting Email — Design Spec

**Date:** 2026-04-21
**Owner:** Daniel (Twins Garage Doors)
**Deliverable:** A reusable Cowork prompt that drafts a personalized recruiting email to a Brandeis University student based on their attached resume, and creates the draft directly in Daniel's Gmail.

---

## 1. Goal

Send a personalized outreach email to each of a batch of Brandeis students, written to feel founder-authored and to produce replies from the kind of student Twins actually wants: someone with initiative who will help streamline operations, build accountability systems, and scale the business toward a PE sale over the next couple of years — using AI aggressively as the leverage.

The primary deliverable is a **Cowork prompt**, not a finished batch of emails. Daniel will run the prompt in a Cowork session per student (each session has one resume attached + Gmail draft access). The prompt produces one draft in Gmail per run.

This session will also validate the prompt against one sample resume before Daniel runs it at scale.

## 2. Success Criteria

- Every draft references a **specific, non-generic detail** from the student's resume in the opener.
- Draft is **≤ 200 words**, reads as founder-written (short sentences, first person plural, no corporate filler).
- Role is framed as **operational streamlining + accountability for scale** with AI as the multiplier — not as "an AI role."
- PE exit trajectory is stated honestly, not flexed.
- CTA is soft: reply if interested, happy to set up a call.
- Draft is created in Daniel's Gmail (recipient = student email extracted from resume, no send).
- Daniel's existing Gmail signature handles the sign-off — the email body ends at the CTA, no closing or signature lines in the draft.
- Prompt outputs a one-line confirmation (student name + subject line) so Daniel can scan the batch.

## 3. Email Skeleton

Every email follows this structure:

1. **Subject line** — specific hook from their resume. Not "Internship opportunity at Twins Garage Doors."
2. **Opener (1–2 sentences)** — a specific, non-flattering detail from the resume showing the email was actually read.
3. **Bridge (1 sentence)** — why that detail made us think of them for this role.
4. **The story (short paragraph)** — who Twins is, where we're headed (PE exit in a couple of years), why we're hiring someone Brandeis-caliber for the first time for this kind of role.
5. **The role (3–4 bullets)** — remote, operational streamlining + accountability to scale, AI-aggressive, internship → long-term, works directly with founders, undefined by design.
6. **Founder credibility (1 sentence)** — Tal: Yale ChemE BS, MIT MechE MS + PhD. Daniel: UC Davis BS. Framed as "we think like engineers — we want someone who thinks bigger than the job description."
7. **CTA** — "If this sounds interesting, reply and we'll set up a quick call."
8. **No sign-off, no signature.** Gmail adds Daniel's signature automatically.

Total length: ~150–200 words.

## 4. Personalization Rules

### 4.1 Signals to look for (in priority order)

1. **Initiative / scrappiness** — side project, startup attempt, hackathon, founded a club, self-directed research.
2. **Technical / AI exposure** — Python, ML, LLMs, automation, data work, CS coursework.
3. **Ops / business lean** — internship at a small company, consulting project, finance/ops/strategy class work, running something operationally.
4. **Major + year** — determines whether to lean internship-first (underclassmen) or long-term-first (seniors/grad students).
5. **Unique angle** — something surprising (languages, non-traditional path, unusual deep interest). Used sparingly to keep the email human.

### 4.2 How to use them

- Pick the **1–2 strongest signals on that specific resume** and build the opener + bridge around those. Not all five — that produces a Mad Libs email.
- If AI/initiative signals are weak or absent, lead with whatever the resume *does* show strongly (rigor, leadership, curiosity, grit). The AI/PE-exit story still lands — as "here's what we're building, we think you'd be good at it" rather than "we picked you because you already do this." Every resume has something — the job is to find it.
- Every student gets a drafted email. No skipping for thin AI signal.

### 4.3 What tailors vs. what stays constant

**Tailors per student:**
- Subject line
- Opener + bridge
- Weight of internship vs. long-term framing in the role bullets

**Stays constant (with light variation only):**
- Twins story + PE trajectory
- Role bullets (content, not order)
- Founder credibility line
- CTA

## 5. Tone + Voice

- **Founder-written, not recruiter-written.** Short sentences. First person plural.
- **No corporate phrases** — avoid "exciting opportunity," "dynamic team," "fast-paced environment," "rockstar," "ninja," "passionate."
- **Ambitious but not cocky about the PE exit.** "We're building toward a sale in the next couple of years" — honest about the trajectory, not flexing.
- **Honest about the role being undefined.** "This isn't a role we've had before" is a feature — the student who reads that as exciting is the right filter.
- **No emoji. No exclamation points.**
- **No flattery** ("your impressive background…"). Specificity replaces flattery.

## 6. Execution — The Cowork Prompt

### 6.1 Pre-conditions

Each Cowork session must have:
- One student's resume attached (PDF, docx, or pasted text).
- Daniel's Gmail connected with draft-creation permission.

### 6.2 What the prompt instructs Claude to do

1. **Read the resume carefully.** Extract: full name, email, school + expected graduation year, major(s), key experiences, projects, skills, and any signals of initiative or AI/tech/ops exposure.
2. **Identify the 1–2 strongest signals** per Section 4.1. If strong AI/initiative signals exist, use those. Otherwise fall back to the strongest non-AI signal per Section 4.2.
3. **Draft the email** following the Section 3 skeleton, the Section 4 personalization rules, and the Section 5 tone.
4. **Self-check before creating the draft:**
   - Does the opener reference something specifically from this resume (not a generic student trait)?
   - Is it ≤ 200 words?
   - Is the CTA soft — reply-if-interested, not "complete a task"?
   - No sign-off or signature lines in the body?
   - No corporate filler or emoji?
5. **Create the draft in Gmail.**
   - `to:` student's email from resume. If resume has no email, halt and output: `NO EMAIL FOUND on resume for [Name] — please provide manually.`
   - `from:` Daniel's connected Gmail.
   - `subject:` the tailored subject line.
   - `body:` the email body, plain text.
   - **Do not send.**
6. **Output one-line confirmation:** `Draft created for [Student Name] — subject: "[subject line]"`.

### 6.3 Failure modes the prompt must handle

- **Resume has no email:** halt with the message in 6.2 step 5.
- **Resume is unreadable / mostly blank:** halt and say `Resume unreadable for [filename] — please re-attach.`
- **Unsure which signal to lead with:** default to the strongest signal of initiative. Do not ask Daniel — proceed.
- **Multiple strong signals:** pick the one most relevant to "operational streamlining + scale," not the most technically impressive.

## 7. Validation in This Session

Before Daniel runs the prompt at scale, this session will:

1. Have Daniel attach **one sample resume**.
2. Run the prompt logic manually and produce the draft email in-session (no Gmail call yet — just the text).
3. Daniel reviews, we iterate on the prompt until the sample email is right.
4. Lock the prompt text and hand it to Daniel to paste into Cowork for the rest.

## 8. Out of Scope

- Bulk execution in this session (the point of Cowork is one-per-resume execution there).
- Send automation — Daniel reviews and sends each draft himself.
- Follow-up sequence — if replies come in, that's a separate conversation.
- Non-Brandeis students — the prompt is tuned for Brandeis but is not school-specific in its text; the Brandeis targeting happens via which resumes Daniel feeds in.

## 9. Open Items

None. Ready to build.
