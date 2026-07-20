# Blog prune list — framed from the live corpus

Concrete answer to the "blog prune list pending owner approval" gate
(redirect-plan Finding 4). Measured against the staging clone on 2026-07-20.

## The finding: the prune is already curated

The revamp settled the keep/cut decision:
- **187 published posts** on the main blog — all garage-door-relevant, each with a
  rewritten title / meta / body / featured image. This is the **keep** set.
- **69 posts demoted to draft** (256 total − 187 published; 0 pending/private/
  trash). These are exactly the posts the revamp chose **not** to carry: the
  off-topic and the superseded/duplicate ones. **This is the prune list.**

On production these 69 are still **published and live**. The cutover action is to
unpublish each and **301-redirect it → `/blog/`** (SEO-safe: no 404s, any link
equity flows to the branded index). Do NOT delete outright.

## The 69, in three buckets

### A. Off-topic — prune, not a garage-door topic (16)
Keeping-Your-Home-Clutter-Free · Air Filters and Their Impact on Your Health ·
Simple Steps To Declutter And Organize Your Garage · Looking for Home Reno
Expert · Home Improvement Trends · Regular Roof Inspections And Maintenance ·
Cozy And Functional Home Office · Budget-Friendly Bathroom Upgrades · Benefits Of
Regular HVAC System Maintenance · Choosing The Right Paint Colors For Your Home ·
Top 10 Energy-Saving Tips · Common Plumbing Problems · Welcoming Outdoor Space For
Summer Entertaining · 5 Essential Home Maintenance Tasks This Spring · The Future
Of Home Automation · How To Paint Your Garage Door *(borderline — this one is
garage-door-adjacent; keep it out unless you want a painting guide, then it needs
a rewrite to brand voice)*.

### B. Junk — broken drafts, prune (2)
`Elementor #4547` · `Elementor #4136` (placeholder/broken Elementor drafts, no
real content — redirect or delete).

### C. On-topic but superseded — prune, replaced by a better keeper (51)
Garage-door posts whose subject is already covered (better, on-brand) by one of
the 187 keepers: broken-spring, opener repair/replace, sensor alignment, spring
lifespan, cold-weather opener, won't-close, door sounds, floor-crack repair,
spring cost, track geometry, roller bearings, IR safety-sensor mechanics, spring
types, rainy-season prep, storefront doors, sloped garage, carriage doors, garage
security, repair-or-replace, backed-into-door, noisy door, keep garage cool,
homeowners insurance, winter prep, stuck door, first-time buyers, garage
transformations, modernize home, curb appeal, evolution of garage doors, electric
door types, choosing a repair company, break-in security, DIY maintenance, 7
signs of repair, sealing techniques, gaskets, airtight doors, opener sensors,
reconnect after pulling the string, battery backup, opener camera, 5 signs to
replace, smart opener, long-lasting maintenance, resale value, troubleshooting
won't-open, importance of maintenance, DuraMaster torsion springs.

> A few of these read as old, off-brand engineering deep-dives (track geometry,
> roller-bearing dynamics, IR-beam circuitry) — demoted rightly. If you want any
> single one kept, it needs a brand-voice rewrite before publishing; otherwise
> redirect with the rest.

## Recommendation

**Approve all 69 as the prune set; 301 each → `/blog/` at cutover.** Rationale:
the revamp already replaced every on-topic subject with a stronger keeper among
the 187, and the off-topic/junk do not belong on a garage-door blog. One decision
covers all 69; no per-post judgement needed unless you want to rescue a specific
title (paint-your-door, or a technical piece) with a rewrite.

Optional refinement (only if any pruned URL has real backlinks/traffic): point
that one at its nearest keeper instead of the generic `/blog/` for better equity.
Worth checking GSC top-URLs for the pruned slugs before launch; otherwise `/blog/`
is fine for all.

## Staging state + redirect scope (verified 2026-07-20)

On the staging clone the prune is **already in effect**: all 69 are unpublished
(drafts), published count is exactly 187, so the site/index already show only the
keepers. There is nothing to "deploy" for the unpublish — the production cutover
replicates this state (unpublish the 69) on the live site.

The redirect layer is production-only (staging has no active Rank Math
redirections table). It matters because **`redirect_canonical` /
`redirect_guess_404_permalink` is ON** — an unpublished post whose URL is hit will
be *guessed* to a similar published slug (exactly the `/garage-door-repair/`
failure mode). So every pruned URL that was ever public needs an explicit 301.

Scope refinement: of the 69, only **12 ever had a public slug** (once-published)
on the clone; the other **57 were never-published drafts** with no URL (nothing to
redirect — just leave unpublished). The 12 reference slugs:
`duramaster-torsion-springs`, `how-to-permanently-repair-a-garage-floor-crack`,
`keeping-your-home-clutter-free`, `air-filters-and-their-impact-on-your-health`,
`simple-steps-to-declutter-and-organize-your-garage`, `looking-for-home-reno-expert`,
`home-improvement-trends-enhancing-your-living-spaces`,
`the-benefits-of-regular-hvac-system-maintenance`,
`the-ultimate-guide-to-choosing-the-right-paint-colors-for-your-home`,
`top-10-energy-saving-tips-for-lowering-your-utility-bills`,
`the-future-of-home-automation`, `opener-battery-backup`. Production may have more
of the 69 still published (it has not been pruned yet), so derive the final
redirect set from production's own live posts at cutover (step 1 below).

## Cutover mechanics

1. On production, pull the live post list: `wp post list --post_type=post
   --post_status=publish --fields=ID,post_name,post_title --format=csv`.
2. The prune set = live posts whose title/slug matches the 69 above (they are
   currently *published* on production, not draft — the draft status is only on
   the staging clone). Confirm by title.
3. For each: set to draft/private (unpublish) and add a 301 `→ /blog/` in Rank
   Math. Fold this into redirect-plan execution (Findings 3/4).
4. Verify: each pruned URL 301s to `/blog/` in one hop; the 187 keepers still
   resolve 200.

Supersedes the placeholder in redirect-plan Finding 4 ("10 pruned + off-topic
pending"); the real demoted set is these 69.
