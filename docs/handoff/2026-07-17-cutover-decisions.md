# Production cutover decisions — 2026-07-17

Owner decisions captured from Daniel during the staging remediation review.
These bind the production cutover engineering for the site overhaul on branch
`claude/staging-remediation` (deployed to private staging through release r5).

## Booking: Housecall Pro (DECIDED)

- "Book Online" in the header, drawer, and booking dialog goes live as an
  external HCP online-booking link at cutover.
- URL (the same link production uses today):
  `https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true`
- The brand chrome already supports this: `environment === 'production'`
  requires booking mode `external` with `target="_blank"` and
  `rel="noopener noreferrer"` (enforced in `components/header.php`).
  The production BookingAdapter simply returns
  `['mode' => 'external', 'href' => <URL above>, 'target' => '_blank', 'rel' => 'noopener noreferrer']`.
- Staging keeps the inert dialog by contract; the renderer harness asserts
  `book.housecallpro.com` never appears in staging output. Do not weaken that.
- DECIDED (owner, 2026-07-17): "Request a Quote" is an LP-style callback form
  (Name, Phone, what-do-you-need picker, TCPA consent line, "Get My Call
  Back"). Staged inert on the contact page; at cutover, wire submission to
  the same lead destination as the Madison LP form (GHL/LeadConnector;
  capture the exact endpoint from the LP page source during cutover).

## Business address (CONFIRMED)

- Twins Garage Doors, 2921 Landmark Pl #206, Madison, WI 53713.
- Already rendered in the footer NAP and LocalBusiness schema.

## Phones (CONFIRMED)

- Corporate/root: (833) 833-2010. Wisconsin: Madison (608) 420-2377 and
  Milwaukee (414) 800-9271, both shown labeled in the WI utility bar with
  metro-pure body copy. Kentucky: (859) 440-2227. Illinois preview:
  (815) 800-2025 — forwarding still unproven; IL stays unpublished until the
  owner confirms a test call.

## Reviews (MECHANISM PENDING)

- Displayed claim is exact and verified: 4.9 from 699 Google reviews
  (place ChIJ6WuQE9VSBogRgy76ORRGfHs, checked 2026-07-17). Source of truth:
  `website/twins-brand-experience/config/review-summary.php` — refresh at
  every deploy until live.
- Owner wants a live auto-updating count. The staging package cannot ship it
  (its safety contracts prohibit outbound HTTP in every scanned file). At
  cutover, add a small server-side fetcher (Places API key WITHOUT referer
  restriction; the current GOOGLE_MAPS_API_KEY is referer-locked) that
  refreshes a cached summary daily and feeds the same config shape.

## Still open (owner)

- Clopay dealer licensing confirmation for the full per-combination builder
  image matrix.
- Hormann line: keep (page redesign) or retire (redirect at cutover).
- IL phone forwarding evidence.

## Blog prune list (PENDING OWNER YES/NO)

Off-topic posts proposed for removal + 301 to /blog/ at cutover (keep the two
smart-garage-door posts; they are opener-adjacent):

- /the-benefits-of-regular-hvac-system-maintenance/
- /top-10-energy-saving-tips-for-lowering-your-utility-bills/
- /simple-steps-to-declutter-and-organize-your-garage/
- /looking-for-home-reno-expert/
- /the-ultimate-guide-to-choosing-the-right-paint-colors-for-your-home/
- /how-to-permanently-repair-a-garage-floor-crack/
- /keeping-your-home-clutter-free/
- /air-filters-and-their-impact-on-your-health/
- /the-future-of-home-automation/
- /home-improvement-trends-enhancing-your-living-spaces/
