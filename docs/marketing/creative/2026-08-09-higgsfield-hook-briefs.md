# Higgsfield Hook Briefs, Round 1

**Date:** 2026-08-09
**For:** act_388398022876424 rebuild, see `docs/superpowers/specs/2026-08-09-meta-ads-overhaul-design.md`
**Budget context:** 1,215.99 credits on Plus. `seedance_2_5`, 9:16, 5s = **32.5 credits**, so about
37 generations available, roughly 12 attempts per brief.

---

## The governing principle

Daniel's reference reels (@rourke's jet-blast hook, @thepostprotocol's surreal AI shots) work
because they stop the scroll. They are also **organic reach formats**, where a wrong viewer costs
nothing. Twins runs **paid local lead-gen**, where every wrong viewer is billed and Madison only
contains so many broken doors.

The failed hooks in this account (goat video, sports-car burnout, July 4th fireworks) all borrowed
attention from something unrelated to garage doors. Census result: 7 "Stop" replies out of 16.

**So: keep the stopping power, change what is being dramatized.**

> **AI generates the PROBLEM. Real footage carries the PROOF.**

The spectacle must BE the offer. A spring letting go is as arresting as a jet engine, and every
single person who stops for it owns a garage door.

### Hard guardrails, every brief

1. **Never imply AI footage is a Twins job.** AI shots depict a generic hazard, the way stock
   footage does. The moment a viewer could read an AI shot as "this is a house Twins worked on,"
   it becomes a fabricated before/after, which is the exact defect that produced 0.40% CTR.
2. **No AI-generated people.** Retired by decision 2026-08-09. Daniel is the only face.
3. **No invented numbers.** Any price, weight, tension, or response-time figure must be confirmed
   by Daniel before it goes on screen. Placeholders below are marked `[CONFIRM]`.
4. **Burned-in captions on every asset.** Reels play muted.
5. **Phone on endcard is (608) 688-9109**, the Facebook tracking number.
   **BLOCKED:** that number forwards to 916-712-3699 and is unverified. Test-call before any
   endcard is rendered.
6. Hook lands inside **0.5 seconds**. No logo-on-blank-color opener.
7. 1080x1920, under 20 seconds total.

---

## Brief 1: "Springs don't warn you"

| | |
|---|---|
| Ad slug | `Repair_SpringSnap_Reel_v1` |
| Cell | 1, Calls, $55/day |
| Offer | Urgent repair |
| Variable under test | **Hook type: hazard-dramatization vs the current talking-head open** |
| Incumbent it challenges | `Repair_DoorWontOpen_Reel_v1` |

### Shot list

| Time | Content | Source |
|---|---|---|
| 0.0-1.8s | Extreme slow motion, tight on a torsion spring above a garage door. The coil shears, snaps, recoils along the shaft. Dust and paint flakes burst off the bar. The door drops hard. | **Higgsfield** |
| 1.8-3.0s | Hard cut. Daniel, in the yellow polo, mid-sentence, real garage behind him. | Existing footage, re-cut |
| 3.0-9.0s | Daniel: what a broken spring actually means, why not to touch it, same-day availability. | **Daniel to film** |
| 9.0-12.0s | Endcard. $0 service call, phone, Twins logo. | Existing endcard system |

### Higgsfield prompt

```
Extreme slow motion macro shot, interior of a residential garage, tight on the steel torsion
spring mounted on the horizontal shaft above a white sectional garage door. The spring coil
suddenly shears and snaps, metal recoiling violently along the bar, a burst of dust and old
paint flakes exploding into a shaft of morning light. The garage door drops abruptly in the
background, out of focus. Handheld realism, natural light from a single side window, dust
motes in the air, cold desaturated palette, shallow depth of field, 9:16 vertical, no people,
no text, no logos, photoreal, documentary style, not cinematic slow-mo cliche.
```

- Model `seedance_2_5`, aspect_ratio `9:16`, duration `5`, count `3`
- Cost: 3 x 32.5 = **97.5 credits**
- Trim the best 1.8 seconds from whichever variant reads clearest at thumbnail size

### Captions

- 0.0s `SPRINGS DON'T WARN YOU.`
- 2.0s `They just go.`
- 3.5s onward: verbatim subtitles of Daniel's audio

### Why this should beat the incumbent

Highest-intent moment in the entire business. Someone whose spring just went is not shopping, they
are calling. Matches the offer already producing at $22.18 per call.

### Risk

A violent mechanical failure next to a "$0 service call" offer can read as fear-mongering. If the
day-7 read shows high CTR and poor call quality, that is the signal, and the fix is softening the
caption rather than killing the hook.

---

## Brief 2: "You left. It didn't close."

| | |
|---|---|
| Ad slug | `ServiceCall_LeftOpen_Reel_v1` |
| Cell | 1, Calls, $55/day |
| Offer | $0 service call |
| Variable under test | **Emotional driver: security/dread vs mechanical urgency (Brief 1)** |
| Incumbent it challenges | `ServiceCall_Zero_Reel_v1` |

### Seasonality note

The obvious Wisconsin hook is a door frozen half open in a January storm. **That is the wrong
asset for August.** It is specified at the bottom of this brief and queued for early October.
The summer-valid equivalent is the security version below.

### Shot list

| Time | Content | Source |
|---|---|---|
| 0.0-2.0s | Static locked-off wide, suburban Midwest driveway, late dusk. Every house dark. One garage door standing wide open, interior light spilling out onto the drive. Nobody home. Slow push in. | **Higgsfield** |
| 2.0-3.0s | Hard cut to Daniel. | Existing footage |
| 3.0-9.0s | Daniel: doors that stop closing, why it is usually the sensor or the opener, $0 to come look. | **Daniel to film** |
| 9.0-12.0s | Endcard. | Existing |

### Higgsfield prompt

```
Static locked-off wide shot, quiet Midwestern suburban street at late dusk, overcast, damp
asphalt. A two-car attached garage with one door standing fully open, warm interior light
spilling out across the empty driveway. Every other window on the street is dark. No people,
no cars moving, completely still and slightly ominous. Very slow push in toward the open
garage. Photoreal, natural available light, muted blue-grey evening palette, 9:16 vertical,
no text, no logos, documentary stillness.
```

- Model `seedance_2_5`, aspect_ratio `9:16`, duration `5`, count `3`
- Cost: **97.5 credits**

### Captions

- 0.0s `YOU LEFT AT 7.`
- 1.2s `IT NEVER CLOSED.`
- 3.0s onward: subtitles

### Why this should work

Security dread is a stronger and more universal trigger than mechanical inconvenience, and it
reaches people whose door still "works," which is a much larger Madison audience than people
mid-failure. That widens Cell 1's addressable pool without leaving the offer.

### Queued winter variant, do not build until October

```
Static wide shot, Wisconsin driveway at dawn during heavy snowfall, deep snow on the ground.
A residential garage door jammed halfway open, snow blowing sideways into the exposed garage
interior. Bitter blue morning light, breath-fog cold, no people. 9:16 vertical, photoreal.
```
Caption: `IT'S 6AM.` / `IT'S NINE DEGREES.` / `YOUR DOOR IS STUCK LIKE THIS.`
`[CONFIRM]` the temperature figure or replace with "below zero."

---

## Brief 3: "Nobody believes a garage door ad"

| | |
|---|---|
| Ad slug | `Install_ReviewProof_Video_v1` |
| Cell | 2, Forms, $35/day |
| Offer | Free estimate, install, GoodLeap financing |
| Variable under test | **Format: video social-proof vs the static carousel that hit 3.07% CTR** |
| Incumbent it challenges | The retired `Review Proof Carousel` concept |

This is the **highest expected value asset in the set.** It takes the best-performing creative
concept this account has ever produced (3.07% CTR, roughly 3x anything else) and moves it onto a
format that actually gets delivery and onto an objective that actually captures leads.

### Shot list

| Time | Content | Source |
|---|---|---|
| 0.0-1.5s | Hook. Slow orbit around a garage door at golden hour on an ordinary Midwestern house. Ends on a hard beat. | **Higgsfield** |
| 1.5-3.0s | Daniel, direct to camera, delivers the disarming line. | **Daniel to film** |
| 3.0-9.0s | Real Google review quotes, one per beat, kinetic type over real Twins job photos. Reviewer first name + last initial only. | **Real GBP reviews + real CompanyCam photos** |
| 9.0-13.0s | Daniel: free estimate, financing available. | **Daniel to film** |
| 13.0-16.0s | Endcard. | Existing |

### Higgsfield prompt

```
Slow smooth orbital dolly around the front of an ordinary two-story Midwestern suburban home
at golden hour, camera centred on a closed two-car garage door. Warm low side light, long
shadows across a mown lawn, mature trees, late summer. Calm and aspirational but completely
ordinary, not a luxury property. Photoreal architectural videography, 9:16 vertical, no
people, no text, no logos, no signage.
```

- Model `seedance_2_5`, aspect_ratio `9:16`, duration `5`, count `3`
- Cost: **97.5 credits**

### Captions

- 0.0s `NOBODY BELIEVES A GARAGE DOOR AD.`
- 1.5s Daniel, spoken: "So I'll let our customers talk."
- 3.0s onward: review quotes as kinetic type, attributed

### Guardrails specific to this brief

- The AI house is a **backdrop, not a claim.** It must never be captioned or implied as a Twins
  installation. Keeping it door-closed and generic is deliberate.
- Review quotes must be **verbatim** from real Google reviews. No composites, no tidying.
- Job photos behind the quotes must be **real CompanyCam images**, and should not be paired with a
  specific quote unless that quote belongs to that job.

---

## Totals and sequencing

| Brief | Generations | Credits |
|---|---|---|
| 1, Spring snap | 3 | 97.5 |
| 2, Left open | 3 | 97.5 |
| 3, Review proof | 3 | 97.5 |
| **Round 1 total** | **9** | **292.5** |

Leaves roughly **923 credits** for iteration, which is about 28 further generations. No top-up
needed.

**Build order:** Brief 3 first. It is the highest expected value, it challenges a proven concept,
and its AI component is the lowest risk of the three. Then Brief 1, then Brief 2.

### Blocked on Daniel

1. Test-call **(608) 688-9109**. Blocks every endcard.
2. Film three pieces to camera: spring explainer, door-won't-close explainer, review intro plus
   estimate close. One session, yellow polo, real garage, same framing as
   `twins_49_REAL_welcome.mp4`.
3. Confirm or replace every `[CONFIRM]` figure.
4. Supply real CompanyCam job photos for Brief 3.

### Not being produced by AI, on purpose

A door-replacement before/after. Twins has no genuine one, and generating a synthetic one would
recreate the exact defect that produced the worst CTR in the account. That asset has to come from
a real job, photographed before and after, same house, same angle.
