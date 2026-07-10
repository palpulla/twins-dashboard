# AI text-agent — SMS go-live runbook (supervised, ~10 min)

Everything except outbound SMS is already live and verified: website forms v2 (wizard + A/B), the post-submit chooser, on-page chat with the real Grok brain, the `/thank-you/` chooser for the main contact form, and the SMS consent disclosure. The on-page **Chat** path works today. This runbook turns on the **Text me** (SMS) path, which the spec intentionally gates behind a supervised test.

By design, the AI does not text anyone until you finish step 2 below.

## What is already done

- `text-agent` edge fn deployed to jwrpj (`/start`, `/chat`, `/ghl-webhook`), token-gated, `TEXT_AGENT_ENABLED=true`, `XAI_API_KEY` set and verified.
- The chooser's "Text me now" button already calls `/start`, which sends the opening SMS via the Dunzo GHL API. The location can send SMS (the `call-intake-pipeline` integration already does).
- The only missing wiring is the **inbound** side: when a lead texts back, GHL must call the agent so it can reply. That is the one piece that lives in GHL (step 1).

## Step 1 — Build the "Customer Replied" workflow in Dunzo (one time)

Dunzo → Automation → Workflows → **Create Workflow** (blank).

- **Trigger:** "Customer Replied". Add filter **Reply Channel = SMS**.
- **Action:** "Webhook".
  - Method: **POST**
  - URL: `https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/text-agent/ghl-webhook?t=<TEXT_AGENT_TOKEN>`
    - Replace `<TEXT_AGENT_TOKEN>` with the value of the `TEXT_AGENT_TOKEN` Supabase secret (also saved locally at `~/.twins-text-agent-token`). Do not share it anywhere else.
  - Custom JSON body:
    ```json
    {
      "contact_id": "{{contact.id}}",
      "phone": "{{contact.phone}}",
      "message": { "body": "{{message.body}}", "id": "{{message.id}}" }
    }
    ```
    (`message.id` is best-effort; if GHL does not offer it as a merge field, delete that line. The agent still de-dupes and caps without it.)
- Leave the workflow in **Draft** until you are at step 2.

## Step 2 — Supervised test (you + your own cell)

1. Publish the workflow.
2. On a phone or private window, open `twinsgaragedoors.com/madison-tune-up-lp/` (or `/wi/contact-us/`), submit the form with **your own** name + cell, then pick **Text me now**.
3. You should get an opening text from (608) 888-8785 that references your issue and offers to check arrival windows.
4. Reply a couple of times. Confirm the replies are on-brand, offer real windows, and never quote a firm price or book a job (it captures and says the office confirms).
5. **Human-takeover test:** from Dunzo Conversations, have Ivory (or you) send a manual text in that thread. The AI must go silent for that thread from then on.
6. **STOP test:** text `STOP`. GHL unsubscribes natively; the agent also treats it as mute.
7. If all four behave, it is live for real leads. If anything is off, flip the kill switch (below) and tell me what happened.

## Kill switch

- `TEXT_AGENT_ENABLED=false` (Supabase → Edge Functions → Secrets) disables every send immediately; the chooser then shows the call CTA only. Set it back to `true` to resume.
- To disable only inbound auto-replies, unpublish the GHL workflow.

## After go-live

- Merge PR #353 (github.com/palpulla/twins-dash/pull/353) into main.
- A/B readout: `select variant, count(*) filter (where event='view') views, count(*) filter (where event='submit') submits from lp_form_events group by variant;` plus submits-by-variant from `lp_leads.utm->>'form_variant'`.
- Optional cleanup: delete leftover TEST GHL contacts dodLmd4NTcMXdrbmYXZT, 1JzDDK0629JH7YnGSnIS, VpQxsJJHkrJlagJSES6m.
