# Twins Garage Doors: Instagram Publishing System

Design spec. Created 2026-07-07.

## Overview

A semi-automated Instagram publishing system for the Twins Garage Doors account
(`twins.garage.doors`, a Business account connected via Composio). The system
auto-drafts posts from the existing content engine, runs them through safeguard
checks, presents a small weekly batch for human approval, then publishes
approved posts on a fixed schedule. A monthly performance loop feeds results
back into the next month's plan.

Twins is presented only as a Wisconsin business serving Madison, Dane County,
and surrounding communities. No Kentucky or Lexington references anywhere.

## Goals (priority order)

1. Build local trust and social proof so the account looks active, established,
   and credible when a homeowner checks it before booking.
2. Generate calls, website bookings, estimates, and qualified DMs.
3. Recruit garage door technicians (only when actively hiring).

## Non-goals

- Optimizing primarily for likes or follower growth.
- Commercial garage door content.
- Any fully automated publishing without human approval.
- Talking-head video that depends on technicians speaking on camera.

## Posting structure

Three posts per week (Monday, Wednesday, Friday), on a two-week repeating cycle.

**Week 1**
- Monday: Proof
- Wednesday: Value
- Friday: Direct booking or offer

**Week 2**
- Monday: Proof
- Wednesday: Value or decision-help
- Friday: Flex slot

**Flex slot rule (Week 2 Friday):** Recruiting appears here at most once per month,
and only when Twins is actively hiring or accepting applications. When not
hiring, the flex slot is filled with company, owner, local proof, customer
proof, or behind-the-scenes content instead. Recruiting cadence can be
temporarily raised during an active hiring push and dropped back afterward.

## Monthly content mix (roughly 12 posts)

- 4 real job / review / before-and-after / completed-work posts
- 3 educational / diagnostic / maintenance posts
- 2 direct booking or offer posts
- 1 pricing / comparison / repair-versus-replacement post
- 1 company / owner / local / behind-the-scenes post
- 1 recruiting post **when hiring**; otherwise a second company / owner / local
  proof / customer proof / behind-the-scenes post

Posts can serve more than one goal. A broken-spring repair post is proof and
carries a booking CTA at the same time.

## Formats

Target weekly mix, not a rigid requirement: 1 Reel, 1 carousel, 1 static
(job/review/proof/offer), plus 3 to 5 simple Story ideas. Do not force a Reel
when there is no useful real video; fall back to carousel or static.

Technicians are only expected to capture, without speaking on camera:
1. A short wide video of the door
2. A close-up of the failed part
3. A short clip of the repaired door operating
4. One clear completed-job photo

## Content categories

**Booking-driving symptom and decision topics** (core of Wednesday and much of
Friday): door opens a few inches and stops; loud bang from the garage; door
suddenly feels heavy; opener runs but door doesn't move; door reverses for no
reason; cable off the drum; crooked door; repair vs replacement; replace one
spring or both; when to replace an opener; what the $49 tune-up includes; what
affects new-door cost; when a problem becomes unsafe.

**Cut entirely** (too vague, no booking pull): "why garage doors are important,"
"5 benefits of maintenance," "committed to quality," "your satisfaction is our
priority."

**Wording:** use homeowner-facing labels ("Recent repair," "This week's job,"
"Broken spring repair in Verona," "New garage door installation in Madison"),
not internal labels like "win of the week."

## CTA rules

One main CTA per post. Most posts get one, even when not classified as an offer
post. Approved CTAs:
- Call or book through the link in our profile
- Send us a photo of the spring and opener label
- Tell us what the door is doing and what city you are in
- Request a new-door estimate
- Book the $49 tune-up
- Ask about GoodLeap financing
- Save this post before winter
- Send this to someone whose garage door is stuck

## Visual sourcing and proof fallback hierarchy

Real content is the default: real job photos, reviews, completed work, tools,
trucks, field video.

When real job content for a Proof slot is unavailable, fall back in this order:
1. Real completed job
2. Real before-and-after
3. Verified customer review
4. Real truck, tools, parts, or work-in-progress photo
5. Educational branded graphic

Missing proof content must never be replaced with a fake AI-generated job.

AI-generated graphics are allowed only for: educational diagrams, offer
graphics, seasonal graphics, maintenance illustrations, branded backgrounds,
and text-led recruiting posts. As real content builds up, fully AI-generated
feed graphics stay at roughly one-third of posts or less.

Never use AI to create fake technicians, customers, completed jobs,
before-and-afters, reviews, or customer homes, and never present an AI image as
real Twins work. No AI-generated people presented as employees.

## Local discovery

Focus only on Madison, Dane County, and nearby WI communities: Madison,
Middleton, Verona, Fitchburg, Sun Prairie, Waunakee, DeForest, Cottage Grove,
McFarland, Monona, Oregon, Stoughton, Mount Horeb, Cross Plains.

- Use the actual municipality where the job happened when known.
- For local-relevant posts: name the city in the opening caption lines, add the
  city-level location tag, put the city on the Reel cover or first carousel
  slide, and use 3 to 5 relevant hashtags (for example #MadisonWI, #DaneCountyWI,
  #GarageDoorRepair, #VeronaWI).
- Never use generic reach tags (#viral, #fyp, #explorepage).
- Never expose customer addresses, house numbers, license plates, or other
  private info.

## Recruiting approach

At most once per month, only when hiring. Until real crew photos exist,
recruiting posts use real trucks, tools, parts, installation work,
warehouse/supplier pickups, owner photos, text-led carousels, and jobsite
details that do not expose customer info. Topics: what a normal tech day looks
like, what the role involves, training provided, expectations, the Madison-area
territory, who is a good fit, how to apply, and compensation only when approved.
No stock workers, no AI-generated fake techs.

## Profile setup (one-time)

Bio communicates: residential garage door repair and installation; Madison,
Dane County, and surrounding WI communities; 13 years in business; $0 service
call; direct booking link.

Three pinned posts:
1. What Twins Garage Doors does and where we work
2. Reviews and completed jobs
3. Current offers and how to book

Highlights: Reviews, Repairs, New Doors, Openers, Financing, Service Area,
Careers.

## Draft source record

Every draft carries an internal source record (not published) so approval and
audit are fast and honest:
- Asset source (real photo / real video / verified review / AI graphic)
- Job folder or source reference
- Whether the review is verified
- Confirmed city
- Offer used (if any)
- Facts or wording requiring approval before publishing

## Approval and publishing workflow

1. **Draft (auto):** Weekly, the system builds the next 3 posts. It resolves the
   slot from the two-week rotation, selects a topic, pulls a caption from the
   content engine, resolves a visual via the fallback hierarchy (real job folder
   first, permitted AI graphic only where allowed), picks a format, and attaches
   the draft source record.
2. **Preflight check (auto):** Each draft is run against the safeguard checklist
   below before it reaches a human. Failures are held with a clear reason, never
   published.
3. **Approve (human, about 5 minutes per week):** The 3 drafts are reviewed
   (caption, image, CTA, city, hashtags, source record). Approve, edit, or swap
   in a real photo.
4. **Publish (auto):** Composio publishes each approved post at its Mon/Wed/Fri
   slot on Central time. Nothing publishes without approval.
5. **Missing-facts rule:** If there are not enough real facts to write a genuine
   job post, the draft ships with clear placeholders or a request for the
   missing info. It never invents details to fill the gap.

## Monthly performance loop

Once per month, review Instagram-attributed results: qualified DMs, calls,
booking-link clicks, estimate requests, profile visits, saves, shares, booked
jobs, and booked revenue. Instagram-side metrics come from Composio insights;
booked jobs and revenue come from the Twins dashboard (jwrpj) attribution. Use
the results to adjust next month's topics, formats, CTAs, and mix. Do not
optimize primarily for likes or follower growth.

## Automation safeguard checklist

Every draft is checked against all of these; any violation holds the draft:
- No same-day service promise
- No invented offer, customer, review, location, or job details
- No fake completed work
- No commercial garage door content
- No Kentucky or Lexington references
- No emojis
- No corporate wording ("specialist," "journey," "transformation,"
  "solutions," "experience")
- No unsupported pricing claims
- No publishing without human approval

Only these offers may be used: $0 service call, $49 tune-up, GoodLeap financing.
Any offer wording, conditions, or exclusions are flagged for confirmation
before publishing.

## System architecture (components)

- **Content engine (existing):** produces captions per pillar/topic. Reused, not
  rebuilt.
- **Slot planner:** tracks the two-week rotation and monthly mix targets,
  including the hiring-aware flex-slot logic, and picks the next slot + topic.
- **Visual resolver:** applies the proof fallback hierarchy. Checks a real job
  drop-folder first; generates a permitted AI graphic (Nano Banana) only where
  allowed; enforces the one-third AI cap.
- **Draft assembler:** combines caption, visual, format, city, hashtags, CTA,
  and the draft source record into a draft object.
- **Preflight validator:** rule-based checks implementing the safeguard
  checklist (banned terms, offer whitelist, city whitelist, emoji detection,
  same-day detection, KY detection, approval-flag surfacing).
- **Approval queue:** holds drafts for weekly human review; approved drafts move
  to a scheduled state.
- **Publisher:** at each slot time, publishes the next approved post via Composio
  Instagram tools (image, carousel, or Reel/video containers), on Central time.
- **Monthly reviewer:** pulls Composio insights + dashboard attribution and
  writes a monthly review with recommended adjustments.

## Testing

- Preflight validator: unit tests for each safeguard rule (same-day, emoji,
  banned words, KY, offer whitelist, city whitelist, private-info patterns).
- Slot planner: tests that a simulated month hits the target mix and that the
  flex slot only schedules recruiting when hiring is on.
- Visual resolver: tests the fallback hierarchy order and the one-third AI cap.
- Publisher: dry-run mode that assembles the Composio call without publishing.

## Open items to confirm before go-live

1. **Same-day claim:** the content engine `brand.yaml` currently asserts
   "same-day emergency service," which contradicts the no-same-day rule. Update
   `brand.yaml` and confirm the correct promise before any offer post.
2. **Public phone number:** confirm the single correct public number to show in
   bio and posts (multiple numbers exist across assets).
3. **Booking link:** confirm the exact profile booking link/destination.
4. **Hiring status:** confirm current hiring status so the flex slot logic knows
   whether recruiting is active.
5. **Offer fine print:** confirm any conditions/exclusions for $0 service call,
   $49 tune-up, and GoodLeap financing.
