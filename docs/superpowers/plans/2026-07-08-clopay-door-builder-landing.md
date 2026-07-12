# Clopay "Design Your Door" Landing — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a `/design-your-door` capture-first landing page on three WordPress multisite properties (main, /wi, /ky) that collects a lead via a GoHighLevel form, emails contact@twinsgaragedoors.com, and redirects the visitor into Clopay's EZDoor builder.

**Architecture:** No code repo and no iframe. The gate is a GHL form embedded on an Elementor page per site. On submit, GHL creates the contact, fires one shared notification workflow (email to contact@), and redirects to the region's Clopay EZDoor link. Three sites = three near-identical Elementor pages, each embedding its own GHL form instance that carries a hidden `region` value and its own redirect URL.

**Tech Stack:** WordPress multisite (SiteGround) + Elementor + WPCode + WP Rocket; GoHighLevel (Twins location `iRUlbIBg7PzSfLrPiR2j`) for the form, redirect, and notification. Build/verify via the Chrome admin session (logged in as admin "Tal Joseph") and the GHL UI.

**Reference spec:** `docs/superpowers/specs/2026-07-08-clopay-door-builder-landing-design.md`

**Execution note:** "Tests" here are acceptance checks against the live systems (form submission, GHL contact record, notification email, rendered phone number, mobile layout). There is no pytest. Do the check step and confirm the stated Expected result before moving on. All changes are reversible (unpublish pages, disable the form/workflow).

---

## Confirmed facts (verified 2026-07-08)

- `https://twinsgaragedoors.com/ky/` → 200, `/wi/` → 200 (both path-based multisite subsites exist).
- `/design-your-door/` → 404 on main and `/ky/` (slug is free; no collision).
- EZDoor sends `x-frame-options: *` (malformed) and no `frame-ancestors`. We do NOT iframe it; we redirect. This is why the design avoids embedding.

## Per-region configuration (single source of truth)

| Setting | main (Madison WI) | /wi (Wisconsin) | /ky (Kentucky) |
|---|---|---|---|
| Page URL | twinsgaragedoors.com/design-your-door | .../wi/design-your-door | .../ky/design-your-door |
| Phone on page | **(833) 833-2010** | **(608) 888-8785** | **(859) 440-2227** |
| `tel:` link | tel:+18338332010 | tel:+16088888785 | tel:+18594402227 |
| GHL form name | Door Builder – Main | Door Builder – WI | Door Builder – KY |
| Hidden `lead_region` value | `main` | `wi` | `ky` |
| Redirect (interim) | https://ezdoor.clopay.com/ | https://ezdoor.clopay.com/ | https://ezdoor.clopay.com/ |
| Redirect (final) | Clopay dealer link *(pending)* | Clopay dealer link *(pending)* | Clopay dealer link *(pending)* |
| Lead alert email | contact@twinsgaragedoors.com | contact@twinsgaragedoors.com | contact@twinsgaragedoors.com |

## Page copy (identical on all three sites; no em-dashes; region only changes the phone + service line)

- **Headline:** Design Your Dream Garage Door
- **Subhead:** See a Clopay door on a photo of your own home in under 5 minutes. Tell us where to send your design and Twins will help you make it real.
- **Form heading:** Start with a few details, then design your door
- **Submit button:** Start Designing
- **Under-button microcopy:** No obligation. We only use this to send you your design and a quote.
- **Trust strip line (main + /wi):** Clopay garage doors, expertly installed across Wisconsin by Twins Garage Doors.
- **Trust strip line (/ky):** Clopay garage doors, expertly installed across Kentucky by Twins Garage Doors.
- **Phone CTA line:** Prefer to talk? Call [PHONE].  (Substitute the region phone + tel: link from the config table.)

> Do NOT add a "Master Authorized Dealer" badge or any review-count claim until Daniel confirms the exact Clopay dealer tier Twins is entitled to display. Interim trust content = the trust-strip line above + 2 existing real install photos already in that subsite's media library.

---

## Task 1: Build the master GHL gate form

**Systems:**
- GoHighLevel, Twins location `iRUlbIBg7PzSfLrPiR2j`
- Location: Sites → Forms → Builder

- [ ] **Step 1: Confirm the custom field exists (acceptance-first)**

In GHL: Settings → Custom Fields. Confirm a contact field with key `lead_region` exists.
Expected: field is missing (first run). If present, note its exact key and skip Step 2.

- [ ] **Step 2: Create the `lead_region` custom field**

Settings → Custom Fields → Add Field → Text, name "Lead Region", object "Contact".
Confirm the resulting field key is `contact.lead_region` (GHL may suffix it; record the real key for Step 5 and Task 2).

- [ ] **Step 3: Build the form fields**

Sites → Forms → Builder → New Form. Name it exactly **Door Builder – Main**. Add fields in this order:
- Full Name (standard, required)
- Phone (standard, required)
- Email (standard, required)
- Postal Code / Zip (standard field `postal_code`, required)
- Lead Region → add the `lead_region` custom field as a **Hidden** field, default value `main`.

- [ ] **Step 4: Style + submit behavior**

- Styles: single column, full-width submit button, button label **Start Designing**, Twins yellow (#F5C518-ish, match existing site button color) with navy text.
- Options → On Submit → **Open URL / Redirect** → `https://ezdoor.clopay.com/` (interim; swapped in Task 11).
- Options → enable **Sticky Contact** off, **Save on partial** off. Keep it simple.

- [ ] **Step 5: Acceptance check — form saves and hidden field defaults**

Save the form. Open the form's live/preview link, submit test data (name "TEST Main", a test phone, a test email you control, zip 53704).
Expected: submission succeeds and redirects to ezdoor.clopay.com. In GHL → Contacts, the new contact "TEST Main" shows `Lead Region = main`.

- [ ] **Step 6: Record (no git commit — external system)**

Note the form ID/embed code for **Door Builder – Main** in the build log (Task 12). Delete the TEST Main contact after Task 2 verification.

---

## Task 2: Build the shared lead-notification workflow

**Systems:** GHL → Automation → Workflows

- [ ] **Step 1: Acceptance-first — define what "done" means**

Expected end state: submitting ANY of the three Door Builder forms sends an email to contact@twinsgaragedoors.com within ~1 minute, and the email body states the region and the lead's name, phone, email, and zip.

- [ ] **Step 2: Create the workflow + trigger**

New Workflow "Door Builder Lead Alert". Trigger: **Form Submitted**. Filter: Form **is one of** → Door Builder – Main (WI and KY forms are added to this same filter in Task 3, Step 3).

- [ ] **Step 3: Add the email action**

Action → **Send Email**.
- To: `contact@twinsgaragedoors.com`
- Subject: `New Door Builder lead ({{contact.lead_region}}): {{contact.full_name}}`
- Body (plain, no em-dashes):
```
New "Design Your Door" lead.

Region: {{contact.lead_region}}
Name:   {{contact.full_name}}
Phone:  {{contact.phone}}
Email:  {{contact.email}}
Zip:    {{contact.postal_code}}

They were sent into the Clopay EZDoor builder after submitting. Follow up with a quote.
```
(If the `lead_region` merge token differs, use the real key recorded in Task 1 Step 2.)

- [ ] **Step 4: Publish the workflow**

Toggle the workflow to **Publish/Live**.

- [ ] **Step 5: Acceptance check — end to end on the Main form**

Re-submit the Door Builder – Main form with test data (name "TEST Alert", zip 53704).
Expected: within ~1 min, an email arrives at contact@twinsgaragedoors.com with subject `New Door Builder lead (main): TEST Alert` and the body fields populated. Confirm by checking the contact@ inbox OR the workflow's execution log (Contact → Automation history shows the email sent).

- [ ] **Step 6: Clean up test contacts**

Delete the "TEST Main" and "TEST Alert" contacts in GHL so they do not pollute reporting.

---

## Task 3: Duplicate the form for /wi and /ky

**Systems:** GHL → Sites → Forms

- [ ] **Step 1: Duplicate for WI**

Duplicate "Door Builder – Main" → rename **Door Builder – WI**. Change the hidden `lead_region` default to `wi`. Redirect stays `https://ezdoor.clopay.com/` for now. Save.

- [ ] **Step 2: Duplicate for KY**

Duplicate again → rename **Door Builder – KY**. Change hidden `lead_region` default to `ky`. Redirect `https://ezdoor.clopay.com/`. Save.

- [ ] **Step 3: Add both forms to the notification workflow trigger**

Automation → Door Builder Lead Alert → Trigger → Form filter → add **Door Builder – WI** and **Door Builder – KY** so the filter is "Form is one of: Main, WI, KY". Save/republish.

- [ ] **Step 4: Acceptance check — region tagging per form**

Submit the WI form (name "TEST WI") and the KY form (name "TEST KY") with test data.
Expected: two alert emails arrive with subjects `New Door Builder lead (wi): TEST WI` and `New Door Builder lead (ky): TEST KY`. In GHL the contacts show `Lead Region = wi` and `= ky` respectively.

- [ ] **Step 5: Clean up**

Delete TEST WI and TEST KY contacts. Record all three form embed codes/IDs in the build log.

---

## Task 4: Build the main landing page

**Systems:** WordPress admin for **main site** (network admin → main site) → Pages → Elementor. Do this via the Chrome admin session.

- [ ] **Step 1: Create the page**

Pages → Add New. Title "Design Your Door". Confirm the permalink is `/design-your-door/`. Edit with Elementor.

- [ ] **Step 2: Build the layout (single column, mobile-first)**

Section 1 (hero): Heading widget = "Design Your Dream Garage Door"; Text widget = the Subhead copy. Below it, an HTML/Shortcode widget embedding the **Door Builder – Main** GHL form (use the form's embed code from Task 1). Above the form put the Form heading copy; below the submit put the microcopy line.

Section 2 (trust): the main/wi trust-strip line + two existing real install photos from this site's media library. Then the Phone CTA line with **(833) 833-2010** linked as `tel:+18338332010`.

Keep total layout to two simple stacked sections. No sliders, no clutter (house style: simple, mobile fits screen).

- [ ] **Step 3: Mobile check inside Elementor**

Switch Elementor to responsive/mobile view. Expected: form and text fit a phone width with no horizontal scroll; headline is readable; button is full-width.

- [ ] **Step 4: Publish + clear cache**

Publish. Then WP Rocket → **Clear and Preload Cache**.

- [ ] **Step 5: Acceptance check — live page renders correctly**

Load `https://twinsgaragedoors.com/design-your-door/?nc=1` (cache-buster).
Expected: page shows the headline, the embedded form (name/phone/email/zip + "Start Designing"), and the phone reads **(833) 833-2010**. On a mobile viewport, no horizontal scroll.

- [ ] **Step 6: Acceptance check — full funnel from the live page**

Submit the live form (name "TEST Main Live", zip 53704).
Expected: redirect to ezdoor.clopay.com, alert email `New Door Builder lead (main): TEST Main Live` arrives at contact@. Delete the test contact after.

---

## Task 5: Build the /wi landing page

**Systems:** WordPress admin for the **/wi subsite** → Pages → Elementor.

- [ ] **Step 1: Create the page**

On the /wi subsite: Pages → Add New. Title "Design Your Door". Confirm permalink `/wi/design-your-door/`. Edit with Elementor.

- [ ] **Step 2: Build the layout**

Same two-section layout and copy as Task 4 Step 2, with these differences:
- Embed the **Door Builder – WI** form (not Main).
- Phone CTA line reads **(608) 888-8785** linked as `tel:+16088888785`.
- Trust line is the same Wisconsin line.
Use install photos from the /wi subsite's own media library (media is per-subsite).

- [ ] **Step 3: Mobile check** (same as Task 4 Step 3). Expected: fits phone, no horizontal scroll.

- [ ] **Step 4: Publish + WP Rocket Clear and Preload Cache.**

- [ ] **Step 5: Acceptance check — live**

Load `https://twinsgaragedoors.com/wi/design-your-door/?nc=1`.
Expected: headline + WI form present; phone reads **(608) 888-8785**.

- [ ] **Step 6: Acceptance check — funnel**

Submit live WI form (name "TEST WI Live", zip 53704).
Expected: redirect to ezdoor.clopay.com; alert `New Door Builder lead (wi): TEST WI Live` at contact@. Delete test contact.

---

## Task 6: Build the /ky landing page

**Systems:** WordPress admin for the **/ky subsite** → Pages → Elementor.

- [ ] **Step 1: Create the page**

On the /ky subsite: Pages → Add New. Title "Design Your Door". Confirm permalink `/ky/design-your-door/`. Edit with Elementor.

- [ ] **Step 2: Build the layout**

Same layout/copy as Task 4 Step 2, with:
- Embed the **Door Builder – KY** form.
- Phone CTA line reads **(859) 440-2227** linked as `tel:+18594402227`.
- Trust line = the **Kentucky** variant ("...across Kentucky by Twins Garage Doors.").
Use install photos from the /ky subsite's media library. If /ky has no suitable photos, reuse a neutral Clopay door photo already present on /ky; do NOT invent Kentucky-specific claims.

- [ ] **Step 3: Mobile check.** Expected: fits phone, no horizontal scroll.

- [ ] **Step 4: Publish + WP Rocket Clear and Preload Cache.**

- [ ] **Step 5: Acceptance check — live**

Load `https://twinsgaragedoors.com/ky/design-your-door/?nc=1`.
Expected: headline + KY form present; phone reads **(859) 440-2227**; trust line says Kentucky.

- [ ] **Step 6: Acceptance check — funnel**

Submit live KY form (name "TEST KY Live", zip 40202).
Expected: redirect to ezdoor.clopay.com; alert `New Door Builder lead (ky): TEST KY Live` at contact@. Delete test contact.

---

## Task 7: Verify the phone-rewrite snippet does not clobber the main 833 number

**Systems:** WPCode (network + per-site). Risk: snippet **6753** rewrites 833→608 on /wi; a network-wide variant could rewrite the 833 on the main page.

- [ ] **Step 1: Acceptance-first**

Expected end state: the main page keeps showing **(833) 833-2010** in both the visible text and the `tel:` link after full page load and JS execution.

- [ ] **Step 2: Inspect the live rendered number**

Load `https://twinsgaragedoors.com/design-your-door/?nc=2` and inspect the phone element after JS runs (Chrome DevTools / read the DOM).
Expected: text and href both remain 833-833-2010.

- [ ] **Step 3: If the number was rewritten to 608**

Find snippet 6753 (and any phone-rewrite snippet) in WPCode. Determine its scope. Add a page/selector exclusion so it does not run on `/design-your-door` on the main site (e.g., guard on `location.pathname !== '/design-your-door/'`, or scope the selector to exclude this page's phone element). Re-run Step 2 until the 833 sticks. Record the exact snippet edit in the build log for reversibility.

- [ ] **Step 4: Confirm /wi and /ky phones are correct too**

Re-inspect /wi (expect 608-888-8785) and /ky (expect 859-440-2227) after JS. Expected: each shows its own configured number, unmodified.

---

## Task 8: Add entry points (nav + homepage) on each site

**Systems:** WordPress Appearance → Menus and the homepage (Elementor) on each of the three sites.

- [ ] **Step 1: Main site nav + homepage button**

Appearance → Menus (main): add a menu item **Design Your Door** → `/design-your-door/`. On the homepage, add a secondary button "Design Your Door" → `/design-your-door/` near the primary hero CTA. Publish; WP Rocket Clear and Preload Cache.

- [ ] **Step 2: /wi nav + homepage button**

Repeat on /wi: menu item **Design Your Door** → `/wi/design-your-door/`; homepage button to the same. Publish; clear cache.

- [ ] **Step 3: /ky nav + homepage button**

Repeat on /ky: menu item **Design Your Door** → `/ky/design-your-door/`; homepage button to the same. Publish; clear cache.

- [ ] **Step 4: Acceptance check — links resolve**

On each site load the homepage (`?nc=3`) and click the new nav item and homepage button.
Expected: each lands on that site's `/design-your-door/` page (correct region phone visible). No 404s.

---

## Task 9: Full end-to-end verification pass (all three sites)

- [ ] **Step 1: Desktop + mobile render**

For each of the three live pages, load with a fresh cache-buster on desktop and a mobile viewport.
Expected: correct region phone; no horizontal scroll on mobile; form visible above the fold on desktop.

- [ ] **Step 2: One clean lead per site**

Submit each live form once with clearly-labeled test data (e.g., "VERIFY Main/WI/KY", valid test email/phone).
Expected: 3 alert emails at contact@, each naming the correct region; each submission redirects into ezdoor.clopay.com; 3 GHL contacts with the correct `lead_region`.

- [ ] **Step 3: Clean up all verification contacts**

Delete every VERIFY/TEST contact created during the build so GHL reporting stays clean.

- [ ] **Step 4: Reversibility check**

Confirm each artifact can be rolled back: pages can be set to Draft (unpublish), the GHL workflow can be toggled off, and the three forms can be disabled. Note this in the build log.

---

## Task 10: Record the build for tracking + reversibility

**Files:**
- Modify: `docs/marketing/change-log.md` (append a dated entry, matching the existing W# row format with a "reversal" note)

- [ ] **Step 1: Append a change-log entry**

Add a row/section dated 2026-07-08 describing: three `/design-your-door` pages (main/wi/ky), the three GHL forms + shared "Door Builder Lead Alert" workflow, any WPCode snippet-6753 exclusion made in Task 7, and the reversal steps (unpublish pages, disable forms, toggle workflow off, revert snippet edit).

- [ ] **Step 2: Commit the doc change**

```bash
git add docs/marketing/change-log.md
git commit -m "docs(web): log Clopay Design Your Door landing build (main/wi/ky)

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Task 11: Swap in real Clopay dealer EZDoor links (when Daniel provides them)

**Blocked on input:** real dealer-branded EZDoor URL(s) from the Clopay dealer portal/rep.

- [ ] **Step 1: Update each form's redirect**

In GHL, for each form (Main/WI/KY), Options → On Submit → Redirect → replace `https://ezdoor.clopay.com/` with that region's real dealer link (or the single shared dealer link if Clopay issues only one account). Save.

- [ ] **Step 2: Acceptance check**

Submit each live form once.
Expected: redirect now lands on the dealer-branded EZDoor URL, and Clopay's end-of-flow "get a quote from your dealer" is scoped to Twins. Delete the test contacts. Update the build log to mark the interim fallback replaced.

---

## Task 12: Build log (living note during execution)

- [ ] Keep a running note (in the change-log entry from Task 10) capturing: the exact `lead_region` field key, the three form IDs/embed codes, the workflow ID, any snippet-6753 edit, and the media assets used per subsite. This is what makes the whole thing reversible and hand-off-able.

---

## Self-review notes

- **Spec coverage:** landing page (Tasks 4-6), GHL gate form (Task 1,3), Capture 1 email (Task 2), redirect handoff (Task 1 Step 4 + Task 11), per-region config (config table + Tasks 4-6), entry points (Task 8), snippet-6753 risk (Task 7), WP Rocket cache (each publish step), reversibility (Task 9 Step 4, Task 10), out-of-scope items intentionally omitted (no attribution/HCP/SMS tasks). Capture 2 (Clopay dealer email) is Clopay-native and needs no Twins build; it is enabled by Task 11's dealer links.
- **Pending inputs** are isolated to Task 11 (dealer links) and the trust-media/badge note, so the page can go live with the generic redirect and be upgraded without rework.
