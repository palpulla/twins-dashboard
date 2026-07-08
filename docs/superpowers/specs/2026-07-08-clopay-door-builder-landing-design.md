# Clopay "Design Your Door" Lead-Capture Landing — Design

**Date:** 2026-07-08
**Status:** Approved design, ready for implementation plan
**Sites:** twinsgaragedoors.com (main), /wi, /ky (WordPress multisite, SiteGround)

## Goal

Let customers "build their own Clopay door" on the Twins website, and turn that
interest into leads Twins can act on. The builder is the hook; **capturing the
lead is the point.** Ship on all three sites.

## Approach: capture-first (chosen over two alternatives)

Considered three approaches:
- **A. Capture-first (CHOSEN)** — a Twins gate form collects the contact, then
  hands the visitor to Clopay's builder. Twins owns the lead regardless of what
  Clopay's tool does, and it does not depend on embedding Clopay's tool (which
  their HTTP headers make unreliable).
- **B. Design-first, CTA after** — embed Clopay's EZDoor, ask for contact only
  at the end. Rejected: fewer leads (only those who finish AND click), and it
  relies on iframing EZDoor, which sends a malformed `x-frame-options: *` header
  and no `frame-ancestors` — framing works today but is undocumented and could
  break anytime.
- **C. Custom Twins configurator** — rejected for v1: most build effort and
  loses EZDoor's "see the door on a photo of your own house" visualization.

Clopay does **not** offer a public drop-in builder API. The supported path is
their hosted **EZDoor** tool (ezdoor.clopay.com, relaunched June 2025 with AI
photo detection). Twins is a Clopay dealer, so a dealer-branded EZDoor link
routes Clopay's own end-of-flow "get a quote from your dealer" back to Twins.

## The funnel

```
Ad / main-nav link / homepage button
        ↓
1. Twins "Design Your Door" landing page  (Twins-branded, per site)
        ↓
2. Gate form: name · phone · email · zip  →  "Start designing →"
        ↓                     └── CAPTURE 1: email to contact@twinsgaragedoors.com
        ↓                          (includes region so the inbox knows which site)
3. Redirect to Clopay dealer-branded EZDoor (same tab — no iframe)
        ↓
4. Customer builds, clicks Clopay's "get a quote from your dealer"
                              └── CAPTURE 2: Clopay emails Twins the design specs
```

Two nets: bail after the gate and Twins still has the contact (Capture 1);
finish and Twins also gets the exact door design (Capture 2), so the quote is
essentially pre-filled.

## Components

### 1. Landing page (one per site)
- Built in **Elementor** on each subsite, Twins branding, the **region's real
  phone number** (see config table).
- Above the fold: headline ("Design your dream Clopay door and see it on your
  home"), one line of value, and the gate form.
- Trust strip below the fold: Clopay Master Authorized Dealer badge, 2–3 real
  install photos (per-subsite media), reviews.
- Simple, uncluttered, must fit a phone screen without horizontal scroll.
- Slug on every site: **`/design-your-door`**
  (twinsgaragedoors.com/design-your-door, /wi/design-your-door,
  /ky/design-your-door).

### 2. Gate form (GHL)
- A **GoHighLevel form** embedded on the Elementor page. GHL location is Twins'
  own: `iRUlbIBg7PzSfLrPiR2j`.
- Fields: **name, phone, email, zip** + submit. Plus a **hidden `region` field**
  set per site (`main` | `wi` | `ky`) so downstream alert and redirect know
  which site the lead came from.
- One form instance per site (three forms) so each can carry its own hidden
  region value and its own post-submit redirect URL to that region's EZDoor
  link. All three share one notification workflow.
- On submit, GHL: (a) creates/updates the contact (quiet CRM record, free),
  (b) fires the alert workflow, (c) redirects to the region's EZDoor link.

### 3. Capture 1 — lead alert
- GHL workflow emails **contact@twinsgaragedoors.com** on every submit.
- Email subject/body includes the **region** and the submitted name, phone,
  email, zip — so whoever reads the shared inbox knows it's a door-builder lead
  and which market.
- Email only for v1 (no SMS).

### 4. Builder handoff — redirect
- Post-submit redirect to the region's **dealer-branded EZDoor link**.
- Until Twins provides the real dealer links, redirect to public
  `https://ezdoor.clopay.com/` and swap per site when links arrive. Capture 1
  already carries the correct region contact, so leads are never lost while
  links are pending.
- No iframe is used anywhere — the redirect sidesteps EZDoor's framing headers
  entirely.

### 5. Capture 2 — Clopay dealer email
- Clopay's EZDoor "get a quote from your dealer" routes the design specs to
  Twins because Twins is the dealer. This is Clopay-native; no Twins build.
- Caveat: if Clopay issues Twins only one dealer account, all three sites' specs
  land in the same Clopay inbox. That is acceptable — Capture 1 (the GHL alert)
  is what carries the correct region's phone and market tag.

## Per-region configuration

| Setting | main (Madison WI) | /wi (Wisconsin) | /ky (Kentucky) |
|---|---|---|---|
| Page slug | /design-your-door | /wi/design-your-door | /ky/design-your-door |
| Phone shown on page | (833) 833-2010 | (608) 888-8785 | (859) 440-2227 |
| Hidden `region` value | `main` | `wi` | `ky` |
| Lead alert email | contact@twinsgaragedoors.com | contact@twinsgaragedoors.com | contact@twinsgaragedoors.com |
| EZDoor redirect | dealer link *(pending)* → generic fallback | dealer link *(pending)* → generic fallback | dealer link *(pending)* → generic fallback |

## Known risks & dependencies

- **Phone-rewrite snippet conflict:** WPCode snippet 6753 rewrites 833→608 on
  `/wi`. The main page intentionally shows **(833) 833-2010** — verify snippet
  6753 (and any network-wide phone-rewrite snippet) does **not** rewrite the
  833 number on the main site's landing page. Confirm the rendered number on
  each live page after build.
- **Dealer EZDoor links pending:** real per-region dealer-branded EZDoor URLs
  from the Clopay dealer portal/rep are required before launch to make Capture 2
  route correctly. Generic EZDoor is the interim redirect.
- **WP Rocket cache:** clear and preload cache after publishing each page;
  verify with a `?nc=` cache-buster.
- **GHL form region wiring:** the three form instances must each set the correct
  hidden `region` value and correct redirect; a copy-paste mistake would
  mislabel a market. Verify each site end-to-end.

## Out of scope for v1 (easy add-ons later)

- Dashboard attribution (a "Door Builder" source on twinsdash.com). Add later by
  tagging the GHL contact + UTMs; the CRM record already exists.
- Auto-creating a Housecall Pro estimate from the lead.
- SMS alerts.

## Success criteria

- `/design-your-door` is live and mobile-clean on all three sites with the
  correct region phone number on each.
- Submitting the gate form on each site sends a lead-alert email to
  contact@twinsgaragedoors.com that names the correct region, and redirects the
  visitor into the Clopay EZDoor builder.
- No lead is lost if a visitor abandons the builder (Capture 1 already fired).
- All changes reversible (unpublish pages, disable GHL forms/workflow).

## Inputs needed from Daniel before launch

1. Real dealer-branded EZDoor link(s) from Clopay (per region if available, else
   one shared link).
2. Confirmation of which install photos / trust media to use per subsite.
