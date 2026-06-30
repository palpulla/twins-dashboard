# Twins Garage Doors — "$49 Winter Tune-Up" AI-Avatar Ad (Design Spec)

**Date:** 2026-06-30
**Owner:** Daniel (Twins Garage Doors, Madison WI)
**Status:** Design approved in substance; pending spec review → build plan

## 1. Goal & deliverable

A short, fully-branded vertical video ad promoting Twins' **$49 garage-door winter tune-up**, generated (not filmed) using an **AI avatar of Daniel** plus AI/animated B-roll and a full Twins branding layer.

- **Format:** vertical 9:16, 1080×1920.
- **Length:** ~30 seconds (punchy).
- **Output:** one master + 4 swappable end-card CTA variants (organic Reels / paid Meta / call / Shorts).
- **Runs on:** IG + FB Reels, paid Meta, YouTube Shorts, general "call us" use.

## 2. Reference style (what we're matching)

Three competitor tune-up ads supplied by Daniel (Lone Crest $49, a $49 spring/tune-up, MN Garage Door $25). Shared genre:
vertical UGC talking-head, ~30–60s, arc = **hook (winter problem) → the offer → what's included → trust → CTA**, with bold auto-captions (keyword highlight), a persistent logo bug, a price-pop graphic, B-roll cutaways, and a music bed. We match this genre but with a generated avatar instead of live filming.

## 3. Brand system (grounded in twinsgaragedoors.com + assets)

- **Logo:** two cartoon twin techs (yellow polos + caps) on a navy shield reading TWINS / GARAGE DOORS. File: `twins-media-generator/assets/logo/Twins_Garage_Doors_logo.png`.
- **Colors:** gold `#F5B324`, navy `#1E2B47`, white; orange spring accent.
- **Tagline:** *"T'Winning Every Time."*
- **Uniform:** yellow Twins polo + cap (iconic) — current team also wears navy Twins hoodies.
- **Vehicles:** yellow/navy RAM ProMaster van + RAM pickup, both wrapped (cartoon twins, TwinsGarageDoors.com, 833·833·2010). Real photos supplied.
- **Trust signals:** IDEA-certified techs, 100% Satisfaction Guaranteed, 5-star Google reviews, locally owned Madison, 24/7 / same-day, lifetime spring warranty.
- **Phone (CTA):** **(608) 888-8785** (Madison local) — CONFIRMED. (Vehicle wraps show toll-free (833) 833-2010; do not use it on the end card.)

## 4. Approach (locked)

AI-avatar spokesperson + branded B-roll + motion-graphics layer. No live filming. Avatar carries the recurring "face"; kept to **short cuts (a few seconds each)** intercut with B-roll to stay convincing.

## 5. Assets

**Have:**
- Avatar face reference — best frontal frames from `~/Desktop/welcome video tgd.mov` (clear, looking at camera).
- Real vehicle + team photos (van, pickup, team in navy hoodies) — for animated real B-roll and AI-B-roll brand matching.
- Logo PNG, palette, tagline, trust signals.

**Confirmed:** tune-up checklist accurate (section 6); CTA phone = (608) 888-8785. No open items.

## 6. Script (locked, natural AI male voice, ~30s)

> "It's Wisconsin. Your garage door takes a beating every winter. Worn springs, dry rollers, loose hardware. That's how a door fails at ten below. So right now, Twins Garage Doors is doing a complete forty-nine-dollar tune-up. We tighten the hardware, lube the rollers and springs, balance the door, and test the safety reverse. Top to bottom. Local Madison techs, done right the first time. Book your forty-nine-dollar tune-up. Call us at six-oh-eight, eight-eight-eight, eight-seven-eight-five."

**CONFIRMED** by Daniel: "tighten hardware, lube rollers + springs, balance door, test safety reverse" is the actual $49 service. Accurate to ship.

## 7. Storyboard (timed, ~30s)

| # | Time | On screen | Source |
|---|------|-----------|--------|
| 1 | 0–3s | Avatar (you) talking, hook | Avatar clip (lip-sync to VO) |
| 2 | 3–8s | Snowy Madison home + closed door → worn spring closeup | AI B-roll |
| 3 | 8–13s | Avatar + gold "$49" pop | Avatar clip + graphic |
| 4 | 13–20s | Service montage: tightening hardware, lubing rollers, door gliding up smooth, safety-reverse test | AI B-roll |
| 5 | 20–25s | Real Twins van/pickup reveal + team by trucks | Animated real photos |
| 6 | 25–30s | End card: logo, "$49 TUNE-UP", "T'Winning Every Time", "100% Satisfaction Guaranteed", phone, "Book Today" | Motion graphic |

## 8. Branding / graphics layer

- Persistent semi-transparent **Twins logo bug** top-center.
- **Captions** auto-generated from VO: bold white, **gold keyword highlights**, rounded reference style.
- **"$49" price-pop:** gold fill, navy outline, bouncy entrance.
- **End card** motion graphic in navy/gold with logo, tagline, guarantee, phone, CTA line.
- **Music:** upbeat, confident, royalty-free bed.

## 9. CTA variants (master stays identical; only end card swaps)

1. **Organic Reels** — "Comment TUNE-UP and we'll DM you."
2. **Paid Meta** — "Tap Book Now" → booking/landing link.
3. **Call** — "Call (608) 888-8785" prominent.
4. **Shorts** — "Search Twins Garage Doors Madison."

## 10. Production pipeline

1. **Avatar identity:** extract clean frontal still from welcome video → Higgsfield Soul ID (train consistent "Daniel") → generate branded portrait in **yellow Twins polo + cap**.
2. **VO:** generate natural AI male voice for the locked script (Higgsfield audio / TTS).
3. **Avatar talking clips:** image→video lip-synced to VO segments (Seedance 2.0 / Wan 2.7), 9:16, 3 short clips.
4. **AI B-roll:** ~5 clips (snow/door, worn spring, rollers, door glide, before/after), brand-matched to supplied photos.
5. **Real-photo B-roll:** animate van/pickup/team photos (subtle motion/parallax) via Higgsfield image→video.
6. **Captions:** generate from VO (captions skill), styled to brand.
7. **Graphics:** logo bug, "$49" pop, end card — motion graphics (HyperFrames), rendered as transparent overlays.
8. **Composite + music:** assemble in ffmpeg → master 9:16 ~30s.
9. **Variants:** render 4 end-card swaps.

## 11. Cost / credits

Higgsfield Plus, 1,210 credits available. Spend ≈ Soul ID train + ~3 avatar clips + ~5 AI B-roll + ~3 animated-photo clips. Preflight each generation with `get_cost` before submitting; report running total. Expected to fit comfortably; confirm before large batches.

## 12. Risks / mitigations

- **Avatar uncanny over long takes** → keep avatar cuts ≤3–4s, lean on B-roll + captions.
- **Likeness drift** → Soul ID for identity consistency; pick best frontal reference.
- **Brand accuracy** → match supplied real photos for van/uniform; no fabricated operational claims (verify tune-up checklist).
- **Reusability** → save the pipeline as a template for future Twins ads (different offer = swap script + price-pop + end card).

## 13. Out of scope (v1)

Live filming, multi-language, A/B copy testing, posting/scheduling, paid-ad setup. (Possible follow-ups.)
