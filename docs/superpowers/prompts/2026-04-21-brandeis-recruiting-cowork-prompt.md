# Brandeis Recruiting Email — Cowork Prompt

Paste this entire prompt into a Cowork session that has (a) one student's resume attached and (b) Daniel's Gmail connected with draft-creation permission. Claude will produce one Gmail draft per run.

---

You are helping Daniel (co-founder of Twins Garage Doors) write a personalized recruiting email to a Brandeis University student whose resume is attached to this session.

## Context you need

**About Twins Garage Doors:**
- Residential garage door business based in Madison, Wisconsin.
- Co-founded by twin brothers Tal and Daniel, who live in Cambridge, MA and run the Madison business remotely.
- Tal: BS Chemical Engineering (Yale), MS and PhD Mechanical Engineering (MIT).
- Daniel: BS UC Davis.
- Profitable and growing. Building toward a sale to private equity in the next couple of years.
- Resumes are coming to us via Brandeis University (Brandeis grad programs and career services).

**The role we're hiring for:**
- Remote.
- Primary mission: help us streamline operations and build accountability systems across the whole business so we can scale and sell.
- In the trenches with our technicians, CSRs, and dispatchers. Day to day: hiring and onboarding more technicians, installers, and dispatchers; designing KPIs and SOPs across dispatch, CRM, margin, and field ops; using AI aggressively to remove manual work.
- AI is the leverage, not the job. Do not name specific AI products (no "Claude," no tool brand names) in the email. Just say "AI."
- We have not hired for this kind of role before. It is undefined by design. The person shapes it.
- Preferred long-term, but happy to start as an internship and grow into full-time.
- Works directly with the founders.

**Who we want:**
- Initiative. Thinks beyond the job description.
- Comfortable with ambiguity.
- Sharp enough that founders with Yale/MIT/UC Davis backgrounds want them on the team.

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
1. Operational leadership / running something real (founded a business, ran a team, managed ops for a company)
2. Initiative / scrappiness (side project, startup attempt, founded a club, self-directed research, hackathons)
3. KPI / SOP / accountability / process work (building metrics, control testing, audit findings, forecasting systems)
4. Technical / AI / data exposure (Python, SQL, ML, automation, dashboards)
5. Unique angle (language, unusual background, surprising deep interest)

Rules:
- Pick 1–2 signals, not all of them. Mad Libs emails fail.
- If the strong signals are thin, lead with whatever the resume does show (rigor, leadership, curiosity, grit). Every resume has something.
- When multiple signals exist, pick the one most relevant to "in the trenches, building operational systems while growing the team."

### Step 3 — Draft the email

Use this structure. The Twins context and role come first. The personalization to the student comes AFTER, in its own paragraph.

**Subject line:** `Building the operations layer at Twins Garage Doors`

**Body:**

```
[First name],

I'm Daniel, co-founder of Twins Garage Doors. Your resume came to us through Brandeis, so I wanted to reach out directly. My twin brother Tal (Yale ChemE BS, MIT MechE MS and PhD) and I (UC Davis BS) are based in Cambridge; the business is in Madison, Wisconsin. It's profitable and growing, and over the next couple of years we're building toward a sale to private equity.

To get there, we need someone in the trenches with our techs, CSRs, and dispatchers. The role is remote, reports directly to us, and has not existed here before. You would shape it. Day to day spans hiring and onboarding technicians, installers, and dispatchers, building KPIs and SOPs across dispatch, CRM, margin, and field ops, and using AI aggressively to remove manual work.

Happy to start as an internship around your Brandeis schedule; long-term is the preference.

[PERSONALIZATION PARAGRAPH: 2 to 4 sentences. Lead with "Your [specific thing] work stood out" or similar. Name a specific, verifiable detail from the resume. Connect it to the role in one sentence. Add one short second signal if the resume supports it. No flattery. No generic statements.]

If this sounds interesting, reply and we'll set up a quick call.
```

**Stop there. No sign-off. No "Best," no "Thanks," no typed name. Daniel's Gmail adds his signature automatically.**

Target length: 180 to 220 words total.

### Step 4 — Hard rules (check every draft against these)

- **No em-dashes (`—`) or en-dashes (`–`) anywhere in the email.** Use periods, commas, colons, semicolons, or parentheses. Em-dashes read as AI writing and Daniel wants these emails to read as founder-written.
- **No naming specific AI products.** Just "AI." No "Claude," no "ChatGPT," no "agents," no "automation tools."
- **No corporate filler:** no "exciting opportunity," "dynamic team," "fast-paced environment," "rockstar," "ninja," "passionate."
- **No flattery ("your impressive background…").** Specificity replaces flattery.
- **No emoji. No exclamation points.**
- **Honest about the PE trajectory.** "Building toward a sale in the next couple of years." Not cocky. Not hedged.
- **Honest that the role is undefined.** That is a feature.

### Step 5 — Self-check before creating the draft

- Does the personalization paragraph reference something specifically from this resume?
- Is the body 180 to 220 words?
- Does the body end at the CTA with no sign-off, no name, no signature?
- Zero em-dashes? (Search the draft for `—` and `–`. If any, replace.)
- No specific AI product names?
- No corporate filler, no emoji, no exclamation points?
- The personalization paragraph comes AFTER the Twins context, not at the top?

If any check fails, revise.

### Step 6 — Create the Gmail draft

- `to:` the student's email from the resume
- `from:` Daniel's connected Gmail
- `subject:` `Building the operations layer at Twins Garage Doors`
- `body:` the email body, plain text, ending at the CTA
- **Do not send.**

### Step 7 — Failure handling

- Resume has no email: halt. Output `NO EMAIL FOUND on resume for [Student Name] — please provide manually.`
- Resume unreadable or blank: halt. Output `Resume unreadable for [filename] — please re-attach.`
- Unsure which signal to lead with: pick the strongest operational or initiative signal. Do not ask Daniel.

### Step 8 — Output confirmation

After creating the draft, output exactly one line:

`Draft created for [Student Name] — [1-2 word signal used]`

Example: `Draft created for Peter Mitelman — Viatelease KPI work`

That is the entire output. No commentary, no preview, no explanation.
