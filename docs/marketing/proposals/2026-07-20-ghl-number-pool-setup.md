# GHL number-pool call tracking — setup config (Daniel to deploy)

Goal: make every inbound **phone call carry a channel** using GoHighLevel's own
number pools + dynamic number insertion (DNI) — no third-party tool. Once calls
carry a `source`, the sync + attribution wiring (built alongside this) turns them
into `job_attribution_calls` and the Unknown bucket shrinks in the Monday brief.

Confirmed 2026-07-20: GHL already supports this (an old "Landing Page Number Pool"
source exists) — it's just not deployed on the live call flows.

## Source names — use these exactly

Name each tracked number / pool source to match the canonical names already in
`ghl_phone_channel_map` so the existing mapper normalizes them with zero new code:

| Where the call comes from | GHL source name to set | Normalizes to |
|---|---|---|
| Google Business Profile listing | `WI GBP` | Google (GBP) |
| Organic / direct / the published number | `Website` | Website |
| Website — paid Google click (DNI, gclid) | `Google Ads` | Google Ads |
| Website — LSA | `WI Google LSA` | Google LSA |
| Website — Facebook click (DNI, fbclid) | `Facebook` | Facebook |
| Website — organic/other | `Website` | Website |

(LSA and Google Ads *calls placed inside those platforms* are already attributed
natively — these DNI sources catch the ones that land on the website first.)

## What to create in GHL (LC Phone → Settings → Phone Numbers / Number Pools)

1. **GBP tracked number** — provision one LC Phone number, source `WI GBP`, and
   set it as the call number on the Google Business Profile. **Can go live now.**
2. **Organic/direct tracked number** — one number, source `Website`, placed as the
   site's default displayed number. **Can go live now** (even on the current site).
3. **Website DNI pool** — a small pool (5 numbers covers it on the base plan) with
   a swap rule by referrer/UTM: paid Google → `Google Ads`, Facebook → `Facebook`,
   LSA → `WI Google LSA`, everything else → `Website`. Add GHL's DNI/tracking
   script to the site. **This pairs with the production cutover** (the new site is
   where the swappable number lives).

**All pool numbers forward to (608) 888-8785**, preserve caller ID, and never
display the 833 van number (STRATEGY rule). Numbers are measurement only.

## Cost

GHL LC Phone usage only — per-number (~$1–3/mo each) + per-minute (cents), inside
the GHL plan you already pay for. ~6–7 numbers total ≈ a low double-digit monthly.
No new subscription. Kill any pool that isn't earning its cents (see the proposal).

## After you deploy

Tell me it's live (or that you want me to provision via the GHL API on your go).
Then: the GHL→`calls_inbound` sync runs, the match query populates
`job_attribution_calls` (table already created), and I report the before/after
Unknown split in the Monday brief before wiring the ROI resolver to prefer it.

## Sequencing recap

- **Now:** GBP + organic tracked numbers → start catching the biggest Unknown source.
- **At cutover:** website DNI pool on the new site.
- **Then (me):** sync → match → before/after → resolver wire.
