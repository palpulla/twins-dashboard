# Paid measurement plan — Meta → website → GA4

What has to be true before Twins can measure paid website traffic, and the order
to build it. Investigated 2026-07-22 against the live Meta ad account, the live
production site, and GA4 property 344700899. Nothing in this document has been
applied.

The trigger was a narrow question — "set up the GA4 Meta cost import" — but the
import is the *last* step of a chain whose earlier links are missing or broken.
This is the chain, in dependency order.

## Verified state (2026-07-22)

### Meta

Ad account **388398022876424** (Twins Garage Doors portfolio). Last 30 days:
**$3,113.12 spent, 149,332 impressions, 855 link clicks**, 428 ads.

Spend is almost entirely on **non-website objectives**:

| Ad | Objective | Spend |
|---|---|---|
| 01_Master Video – Madison Calls | Calls placed | $481.09 |
| 01_Master Video – Madison Messenger | Messaging conversations | $199.03 |
| Honest Tuneup \| Image 1 | Leads (Form) | $754.63 |
| Anti Bait Switch \| Image 1 | Leads (Form) | $718.30 |
| Reel 2 / Reel – It's not you… | Calls placed | $448.66 |
| Photos \| Dynamic Creative | Leads (Form) | $511.41 |
| Install Financing / Review Proof (website) | Link Click | **$0.00 — campaign off** |

The top-spending ad is a call ad (dials (608) 688-9109); it has no website URL
and therefore no URL-parameters field at all. GA4 structurally cannot see any of
this spend. The conversions are real — they just happen inside Meta.

**The website-destination campaigns are the only ones GA4 could ever measure, and
they are switched off.**

### UTM tagging

The traffic ad `Install Financing – Real Before/After` is tagged correctly, with
the UTMs written **inline in the Website URL** rather than in Meta's ad-level
`URL parameters` field (which is empty):

```
https://twinsgaragedoors.com/wi/financing/
  ?utm_source=facebook&utm_medium=paid_social&utm_campaign=challenger_financing_2026_07
```

Two gaps:

1. **No `utm_id`.** The GA4 campaign-data import joins imported cost to sessions
   on Meta's *campaign ID*. A campaign name string will not join reliably.
2. **Inline, per-ad.** Hand-maintained across 428 ads, this drifts. Only one ad
   was inspected; the other traffic ads are unverified.

`Website events` is also unchecked in that ad's Tracking panel.

### Website

- **No forms.** `document.querySelectorAll('form').length === 0` on both `/` and
  `/wi/financing/`. The financing ad's landing page has no conversion mechanism
  beyond a `tel:` link.
- **Only `page_view` fires.** No `generate_lead`, no form-submit event, no
  click-to-call event. `form_start` count is 0.
- Working pattern already exists: `/madison-tune-up-lp/` (built 2026-07-09,
  noindex) has a real form — `name, phone, service, website` (honeypot),
  `consent` — and the LP number (608) 888-8785.

Four distinct phone numbers are in play across one campaign path: (608) 688-9109
(call ad), (608) 888-8785 (LP number, on the financing creative), (608) 420-2377
(`/wi/financing/`), 833-833-2010 (homepage). The first two are explained; the
other two should be confirmed as intentional.

### GA4

Property **344700899** ("Twins Garage Doors - Main"), measurement ID
**G-XW0RGPTGSN**, stream 4326634359. It *is* receiving website data.

Two blockers:

1. **~84% of the property is ghost spam.** Of 579 active users in 7 days, **486
   are `trafficheap.cc / referral`** (geo: Seychelles, page title
   `trafficheap.cc`). Real traffic is roughly 81 US users/week.

   These are **Measurement Protocol ghost hits** — sent directly to Google using
   the measurement ID, never touching the site. Consequences:
   - `List unwanted referrals` does **not** stop them (it only re-attributes real
     referrals).
   - GA4 data filters cover internal/developer traffic only — there is **no
     collection-side hostname filter**.
   - Bot filtering is already on and does not catch them.

   **There is no configuration fix.** `G-XW0RGPTGSN` is a burned ID. The durable
   remedy is a **new property with a fresh measurement ID**, which the cutover
   provides for free. Until then, any trustworthy report must be hostname-filtered
   in an exploration.

2. **Key events: 0.** Nothing is counted as a conversion, consistent with the
   site-side finding.

A second tag, **`G-FX908KGRHH`**, also loads on the site (plus `G-VM8CYMCWS5`).
Given the Legit5 separation, this is most likely the agency's property. Confirm
ownership before removing, but it should come off at cutover.

## Why this belongs to the cutover

Every remediation lands on a site that is being replaced. Retrofitting GTM tags,
a third landing page, and a data-quality workaround onto the outgoing production
site creates work that is thrown away at cutover — the position
`/madison-tune-up-lp/` and `/madison-garage-door-repair-lp/` are already in
(runbook task 0.7, "repoint Meta ad destinations", is still open and owner-gated).

The new brand experience is the correct seam: it already has a form contract, the
production quote adapter, and the `lp-lead-intake` pipeline.

## Build order

Each step is blocked by the one above it.

1. **New GA4 property + fresh measurement ID**, provisioned as part of cutover.
   Kills the ghost spam and retires `G-XW0RGPTGSN`. Remove the Legit5 tag
   (`G-FX908KGRHH`) and `G-VM8CYMCWS5` in the same move. Carry over nothing but
   the config; the historical data in 344700899 is 84% noise anyway.

2. **Conversion events in the brand experience**, not retrofitted through GTM:
   - `generate_lead` on successful `lp-lead-intake` POST (the production callback
     form already exists — `production-callback.js`).
     Mark as a **key event**.
   - click-to-call on every `tel:` link. Note the WI metro phone rendering is
     server-side; the event must attach after that render.
   - Both need to survive the classified-output form scan (Blocker B env gate is
     already drafted).

3. **Financing landing page** — **BUILT on staging 2026-07-22, page ID 7727,
   `/madison-financing-lp/`.** Cloned from `/madison-tune-up-lp/` (7093): same
   `.tlp` design system and style block verbatim, `elementor_canvas` template,
   `rank_math_robots = ['noindex']`, LP number (608) 888-8785, identical form
   shape (`name, phone, service, website` honeypot, `consent`), with
   `data-page="/madison-financing-lp/"`. Copy adapted to the GoodLeap financing
   offer that the `Install Financing – Real Before/After` ad runs.

   Content verified stored intact (15,868 bytes; `<style>`, font `<link>`, form,
   honeypot all survived WP sanitization).

   **Open items on this page:**
   - **The form is inert on staging** — there is no WPCode plugin and no
     `lp-lead-intake` reference anywhere on the staging filesystem; the handler
     is production-only. `/madison-tune-up-lp/` on staging has the same dead
     form, so this matches existing conditions rather than adding a defect. The
     handler must be ported at cutover, or the page rebuilt against
     `ProductionQuoteAdapter` / `production-callback.js`.
   - Give it a distinct `form_variant` (e.g. `financing-lp`) when the handler is
     wired, so financing leads are separable in `lp_leads`.
   - **Not visually verified.** Staging is behind nginx basic auth (401 from both
     the browser and the server's own loopback), so no render check was possible.
     Needs an eyes-on pass by someone with the staging credentials.
   - Uses **4.9** for the Google rating (the re-verified 4.9/699 figure).
     `/madison-tune-up-lp/` still says **5.0** — one of the two is stale and they
     should be reconciled.

4. **Meta tagging**, applied with the destination repoint (task 0.7):
   - Move UTMs out of the inline Website URL into the ad's **`URL parameters`**
     field (Tracking section), using dynamic macros:
     `utm_source=facebook&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_id={{campaign.id}}`

     Note: Ads Manager has **no campaign-level URL-parameter setting** — this
     field is per-ad. The drift protection comes from the macros, not from a
     single location: every ad carries the identical literal string and Meta
     resolves the values, so there is nothing ad-specific left to mistype. Strip
     the inline query string from the Website URL when applying, or the
     parameters duplicate. At scale this should be applied via the Marketing API
     rather than by hand.
   - Enable **Website events** on the traffic ads, pointed at pixel
     `554750209097175`.

   **APPLIED 2026-07-22 to `Install Financing – Real Before/After`
   (ad 120255240336000399) — STAGED AS A DRAFT, NOT PUBLISHED.** Set the
   `URL parameters` field to the macro string above, checked `Website events`
   (auto-linked to pixel `554750209097175`), and stripped the inline query
   string so the Website URL is now the bare
   `https://twinsgaragedoors.com/wi/financing/` with no duplicate parameters.

   **It is deliberately unpublished.** Meta publishes the whole draft batch at
   once, and the account already held two unrelated drafts — an
   "Updated: Creative" change and a new `$49 Tune-Up – Hook Fixed (burnout)` ad.
   Publishing this tagging would also push those live. The account now shows
   "Review and publish (3)"; someone who knows what the tune-up drafts are needs
   to clear or publish them, then this ships with them.

   **ALSO APPLIED 2026-07-22 to `Review Proof Carousel – 7 cards`
   (ad 120255240333670399) — ALSO STAGED AS A DRAFT.** Checked `Website events`
   (same pixel) and set `URL parameters` to **`utm_id={{campaign.id}}` only**.

   Deliberately different from the other ad: this is a 7-card carousel whose
   destination is `/wi/` and whose card URLs render lazily, one expansion at a
   time. Editing seven inline query strings by hand risks missing one and
   double-emitting a parameter on that card. The three inline UTMs
   (`utm_source`, `utm_medium`, `utm_campaign`) are already correct, so adding
   only the missing `utm_id` gets the identical end state — exactly one set of
   four parameters — with none of that risk. If these cards are ever rebuilt,
   move them to the full macro string for consistency.

   Account draft count after both edits: **"Review and publish (4)"**.

   **Caveat Meta surfaced when saving:** campaign/ad-set/ad names are frozen at
   publish time and `{{campaign.name}}` keeps resolving to the original name even
   if the campaign is later renamed. `utm_id={{campaign.id}}` is unaffected — one
   more reason the ID is the join key and the name is only a label.
   - Keep `utm_medium=paid_social` (do not switch to `cpc` later — changing it
     breaks historical joins).

5. **Budget on** — ~$300–500/mo (≈$10–16/day, matching how the traffic ad sets
   were already configured at $8–12/day). **Alongside** the existing call and
   lead-form campaigns, which stay untouched: they are producing 31 calls, 18
   messaging conversations and 27 form leads a month and are not the problem.
   Turn on only after a test lead is verified end-to-end into `lp_leads`.

6. **GA4 Campaign data import** (Admin → Data import → *Campaign data*, Meta
   BETA connector). This is the correct and only applicable data type — `Item
   data`, `User data by User ID`, and `Custom event data` never apply here.
   Declare the same constant `utm_source` / `utm_medium` as step 4.

## Deferred: closing the loop

The higher-value follow-on is **offline conversion import** — pushing "became a
booked job, worth $X" from GHL/HCP back into GA4, via the `Events` or `User data
by Client ID` import types. That requires capturing GA's `client_id` on form
submit and storing it alongside the lead.

This is the same attribution gap tracked as call-tracking phase 2
(`docs/marketing/proposals/2026-07-20-call-tracking-phase2.md`): the fix is
automated capture, not a human tagging step. No new SaaS — GHL LC Phone already
does call tracking.

## Caveats

- One traffic ad was inspected for UTM tagging, not all 428. Audit the rest
  before assuming the tagging is uniform.
- Ownership of `G-FX908KGRHH` and `G-VM8CYMCWS5` is inferred from the Legit5
  context, not confirmed.
- (608) 420-2377 and 833-833-2010 are unexplained; confirm before the repoint.
- No changes were made to the Meta ad account during this investigation. Two
  pre-existing unpublished drafts ("Review and publish (2)") plus a `$49 Tune-Up
  – Hook Fixed (burnout)` draft ad were present before and after.
