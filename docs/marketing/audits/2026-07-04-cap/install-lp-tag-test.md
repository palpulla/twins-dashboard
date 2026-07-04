# Landing-page test lead + tag observation — 2026-07-04

## What the ads actually land on (from live ad final URLs, GAQL)
- Repair Search → twinsgaragedoors.com/go/garage-door-repair, /go/opener-repair, /go/ppc-springs, offer.twinsgaragedoors.com
- Installation Search → twinsgaragedoors.com/go/garage-door-installation, offer.twinsgaragedoors.com
- The CAP doc's `/wi/garage-door-installation-lp-ppc/` is an ORPHAN page (no live ads point at it). It shows rotating CALIFORNIA pool numbers ((916) 775-0615 rendered, (916) 712-3699 in source) and its 4 "GET YOUR FREE QUOTE" CTAs anchor to an icon widget — no form exists on it.

## Test lead result (live repair LP /go/garage-door-repair)
Filled the embedded GHL form (TEST LEAD IGNORE, office phone, daniel@) and submitted:
- POST backend.leadconnectorhq.com/forms/submit?formId=wZ6lQnDelhWZOhOBbBgL&locationId=ATDh3QGRFcbWAxmrvh2G → **HTTP 401 Unauthorized**
- Submit button greys out; NO error shown to the visitor; NO thank-you redirect; form silently fails.
- ZERO Google Ads conversion request, ZERO GA4 hit, ZERO Meta pixel event at any point in the flow.

## Root cause
The embedded form posts to GHL location `ATDh3QGRFcbWAxmrvh2G` — NOT Twins' GHL location (`iRUlbIBg7PzSfLrPiR2j`, the one with 1,229 synced contacts). The form is wired to a third-party (agency) GHL sub-account whose authorization is now dead. **Form leads from paid landing pages are not lost-to-tracking; they are lost entirely.** The office never sees them (consistent with the test lead never appearing in Twins' GHL).

## Other page defects confirmed
- /go/garage-door-installation: NO form at all; "GET STARTED" buttons have empty href and do nothing on click. Only conversion path is the phone link.
- Phone-number chaos across pages: (608) 888-8785 main, (608) 447-5351 GHL pool on /go/ pages (displayed text sometimes shows 888-8785 while href dials 447-5351), (608) 933-4223 on offer.twinsgaragedoors.com, (916) 775-0615 / (916) 712-3699 on the orphan /wi/ LP, (833) 833-2010 in the mobile menu.
- Viewport meta is malformed AND disables zoom: `width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=0', shrink-to-fit=no` (stray apostrophe).

## What this explains
- Account-wide form conversions went 5 (May) → 0 (June): the GHL form path died and/or the ad final URLs moved to /go/ pages whose forms can't submit.
- Install campaign 5 → 0: page has no form, dead CTA buttons, and only ad-level call assets ever produced conversions (last one May 22).
- June was not a demand collapse; paid clicks landed on pages that cannot convert except by phone call.
