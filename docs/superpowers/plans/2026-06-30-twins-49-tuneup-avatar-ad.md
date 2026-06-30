# Twins "$49 Winter Tune-Up" AI-Avatar Ad — Implementation Plan

> **For agentic workers:** This is a media-production pipeline (Higgsfield + ffmpeg + motion graphics), not a TDD codebase. "Tests" = visual/audio review of each generated artifact against the spec. Steps use checkbox (`- [ ]`) syntax. Each generation step MUST preflight credits (`get_cost`) and report the running total before submitting.

**Goal:** Produce a vertical 9:16 ~30s branded ad for Twins' $49 winter tune-up, built around an AI avatar of Daniel, with branded B-roll, captions, and motion graphics, plus 4 CTA end-card variants.

**Architecture:** Generate a brand-accurate avatar portrait of Daniel (face from welcome video → yellow Twins polo + cap), animate short lip-synced talking clips to an AI voiceover, intercut with AI B-roll + animated real van/team photos, then composite with logo bug, captions, "$49" pop, music, and end card in ffmpeg.

**Tech Stack:** Higgsfield MCP (generate_image, generate_video, voice/audio, Seedance 2.0 / Wan 2.7, Soul ID), captions skill, HyperFrames for motion graphics, static ffmpeg (`scratchpad/ffmpeg`), local logo/photo assets.

**Spec:** `docs/superpowers/specs/2026-06-30-twins-49-tuneup-avatar-ad-design.md`

**Working dir for artifacts:** `twins-media-generator/outputs/tuneup-49-ad/` (create). Scratch frame/audio extraction stays in the session scratchpad.

---

## Phase 0: Asset prep (local, free)

**Files:**
- Create: `twins-media-generator/outputs/tuneup-49-ad/` (output dir)
- Source: `~/Desktop/welcome video tgd.mov` (face), 5 supplied van/team photos, `twins-media-generator/assets/logo/Twins_Garage_Doors_logo.png`

- [ ] **Step 1: Create output dir.** `mkdir -p twins-media-generator/outputs/tuneup-49-ad`
- [ ] **Step 2: Extract 3-4 candidate frontal face stills** from the welcome video at full res (ffmpeg `-ss` at picked timestamps where mouth is neutral and eyes face camera). Save to output dir as `face_ref_1.png`..`face_ref_n.png`.
- [ ] **Step 3: Pick the single best frontal still** (sharp, even light, neutral expression, looking at camera) → `avatar_face.png`. Verify by viewing.
- [ ] **Step 4: Stage the real B-roll source photos** (van solo, van+pickup+team, team closeup) into the output dir with clear names. These feed Phase 5.
- [ ] **Step 5: Confirm logo asset** opens and is the shield/twins lockup. (Already verified.)

**Checkpoint:** We have `avatar_face.png` + staged photos + logo. No credits spent.

---

## Phase 1: Avatar identity — CRITICAL GATE

Lock Daniel's likeness before spending on motion. Approach: generate a branded portrait conditioned on his face; if multi-shot consistency needs it, train Soul ID.

**Files:** `twins-media-generator/outputs/tuneup-49-ad/avatar_portrait_v*.png`

- [ ] **Step 1: Upload** `avatar_face.png` to Higgsfield (`media_upload`) → capture media_id.
- [ ] **Step 2: Preflight + generate branded portrait.** `generate_image` with the face as reference, prompt: photoreal portrait of the same man, wearing a **yellow Twins Garage Doors polo + matching cap**, friendly confident expression, clean neutral garage/studio background, soft natural light, sharp focus, 9:16, commercial ad quality; brand gold #F5B324 / navy #1E2B47. Generate 2-4 variants. Run `get_cost` first; report credits.
- [ ] **Step 3: Review likeness** — view variants, compare to `avatar_face.png`. Pick the strongest match.
- [ ] **Step 4 (if likeness drifts): Train Soul ID** on the face references for consistent identity, regenerate the portrait. Otherwise skip.
- [ ] **Step 5: GATE — show Daniel the chosen avatar portrait.** Do not proceed until he approves the likeness. Iterate prompt/reference if needed.

**Checkpoint:** Approved `avatar_portrait.png` (the locked avatar). This is the start_image for all talking clips.

---

## Phase 2: Voiceover

**Files:** `outputs/tuneup-49-ad/vo_full.mp3`, `vo_timings.json`

- [ ] **Step 1: Generate VO** for the locked script (spec §6) — natural AI male voice, confident/warm, US Midwest neutral, ~30s. Use Higgsfield voice/audio (`create_voice`/`generate_audio`) or fallback TTS. Preflight cost.
- [ ] **Step 2: Review audio** — listen/inspect duration (~28-32s). Re-generate if pacing is off or it mispronounces "608-888-8785" (use "six oh eight, eight eight eight, eight seven eight five").
- [ ] **Step 3: Get word/segment timings** for captions and clip cuts (transcribe-align the VO; ffmpeg + caption skill). Save `vo_timings.json`.
- [ ] **Step 4: Split VO into 3 segments** matching storyboard rows 1, 3, 5 (hook / offer / trust) for lip-sync. Save `vo_seg1.mp3`..`vo_seg3.mp3`.

**Checkpoint:** Approved VO + timings + 3 segments.

---

## Phase 3: Avatar talking clips

Short lip-synced cuts only (≤4s each) to avoid uncanny drift.

**Files:** `outputs/tuneup-49-ad/avatar_clip_1.mp4`..`avatar_clip_3.mp4`

- [ ] **Step 1: Upload** `avatar_portrait.png` + each `vo_seg*.mp3` (`media_upload`).
- [ ] **Step 2: Preflight + generate clip 1 (hook, ~3s)** — Seedance 2.0 (or Wan 2.7 for tighter lip-sync), start_image = portrait, audio_references = vo_seg1, 9:16, talking to camera, subtle natural head/shoulder motion. `get_cost` first.
- [ ] **Step 3: Review clip 1** — check lip-sync + likeness hold. If weak, try Wan 2.7 / regenerate.
- [ ] **Step 4: Generate clips 2 (offer) and 3 (trust)** the same way once clip 1 looks good. Review each.

**Checkpoint:** 3 approved avatar clips.

---

## Phase 4: AI B-roll (5 clips)

Brand-matched cutaways. 9:16, ~2-3s each.

**Files:** `outputs/tuneup-49-ad/broll_ai_1.mp4`..`broll_ai_5.mp4`

- [ ] **Step 1: Generate, each with cost preflight** (`generate_video`, 9:16):
  1. `broll_ai_1` — snowy Madison suburban home, closed white garage door, icicles, overcast winter, cinematic.
  2. `broll_ai_2` — extreme closeup of a worn/rusty garage torsion spring in a dim garage, shallow depth of field.
  3. `broll_ai_3` — gloved technician hands lubricating garage door rollers/hinges, closeup, professional.
  4. `broll_ai_4` — a clean modern garage door gliding smoothly upward, bright daytime, satisfying motion.
  5. `broll_ai_5` — before/after curb-appeal: same home, neglected door → crisp serviced door.
- [ ] **Step 2: Review each** for realism + brand fit (no fake artifacts, no wrong logos). Regenerate any weak clip.

**Checkpoint:** 5 approved B-roll clips.

---

## Phase 5: Real-photo B-roll (authentic vehicles/team)

Animate the supplied real photos for authenticity.

**Files:** `outputs/tuneup-49-ad/broll_real_1.mp4`..`broll_real_2.mp4`

- [ ] **Step 1: Upload** the staged van/team photos (`media_upload`).
- [ ] **Step 2: Preflight + animate** (image→video, start_image = photo, subtle parallax/push-in, 9:16, ~2-3s):
  1. `broll_real_1` — the yellow/navy ProMaster van (cleanest van photo), slow push-in.
  2. `broll_real_2` — van + pickup + team shot, gentle parallax (reveals the real branded fleet).
- [ ] **Step 3: Review** — ensure wrap text/logo stays crisp and undistorted.

**Checkpoint:** 2 approved animated real-photo clips.

---

## Phase 6: Motion graphics + captions

**Files:** `outputs/tuneup-49-ad/gfx/` — `logo_bug.mov` (alpha), `price_pop_49.mov` (alpha), `endcard_master.mov`, `endcard_dm.mov`, `endcard_paid.mov`, `endcard_call.mov`, `endcard_shorts.mov`; `captions.ass`/overlay.

- [ ] **Step 1: Logo bug** — render the Twins logo as a small semi-transparent top-center bug, transparent background (HyperFrames → PNG/ProRes 4444 with alpha), 9:16 canvas.
- [ ] **Step 2: "$49" price-pop** — gold #F5B324 fill, navy #1E2B47 outline, bouncy entrance ~1.5s, transparent bg.
- [ ] **Step 3: End card (master)** — navy bg, Twins logo, "$49 TUNE-UP" (gold), "T'Winning Every Time", "100% Satisfaction Guaranteed", **(608) 888-8785**, "Book Today". ~4s.
- [ ] **Step 4: 4 CTA end-card variants** — duplicate master, swap the CTA line per spec §9 (DM "Comment TUNE-UP" / Paid "Tap Book Now" / Call "(608) 888-8785" / Shorts "Search Twins Garage Doors Madison").
- [ ] **Step 5: Captions** — from `vo_timings.json`, generate styled captions (bold white, **gold keyword highlight** on key words like "$49", "tune-up", "springs", "Madison"), reference style. Use the captions skill / `.ass` subtitle styled to brand.
- [ ] **Step 6: Review** all graphics on a transparent/checker preview for correct color + legibility.

**Checkpoint:** All overlays + captions ready.

---

## Phase 7: Composite master

**Files:** `outputs/tuneup-49-ad/twins_49_tuneup_master.mp4`

- [ ] **Step 1: Source music** — upbeat confident royalty-free bed (~30s), `music_bed.mp3`. Keep under VO (duck to ~-18dB under voice).
- [ ] **Step 2: Build the video timeline** per storyboard (spec §7) with ffmpeg: avatar_clip_1 (0-3) → broll_ai_1+2 (3-8) → avatar_clip_2 + price_pop (8-13) → broll_ai_3/4/5 montage (13-20) → broll_real_1/2 (20-25) → endcard_master (25-30). Scale/crop all to 1080×1920, consistent fps.
- [ ] **Step 3: Overlay** persistent logo_bug (rows 1-5) + burned captions + price_pop at 8-13s.
- [ ] **Step 4: Mix audio** — VO full + ducked music; ensure end-card has a button-press/whoosh accent (optional).
- [ ] **Step 5: Render** `twins_49_tuneup_master.mp4` (H.264, 1080×1920, ~30s, high bitrate).
- [ ] **Step 6: GATE — show Daniel the master.** Review pacing, captions, branding, audio. Iterate any segment.

**Checkpoint:** Approved master.

---

## Phase 8: CTA variants + delivery

**Files:** `outputs/tuneup-49-ad/twins_49_tuneup_{dm,paid,call,shorts}.mp4`

- [ ] **Step 1: Render 4 variants** — re-composite the master but swap the last 4s end card for each CTA end card. (Everything before 25s is identical; concat master-minus-endcard + variant endcard.)
- [ ] **Step 2: Review** each variant's end card.
- [ ] **Step 3: Deliver** — send Daniel all 5 files (master + 4 variants) via SendUserFile, report total credits spent.
- [ ] **Step 4: Save the pipeline as a reusable template** note in the output dir (script + prompts) so future offers = swap script + price + end card.

**Checkpoint:** 5 delivered videos + reusable template.

---

## Self-review (coverage vs spec)

- Spec §4 avatar route → Phases 1, 3. ✓
- §6 script → Phase 2. ✓
- §7 storyboard → Phases 3-5 (clips) + Phase 7 (assembly). ✓
- §8 branding layer (logo bug, captions, $49 pop, end card, music) → Phases 6, 7. ✓
- §9 CTA variants → Phase 8. ✓
- §10 pipeline → Phases 1-7 mirror it. ✓
- §11 credits → every generation step preflights `get_cost`. ✓
- §12 risks (uncanny, drift, brand accuracy) → ≤4s avatar cuts (Ph3), Soul ID option (Ph1), real-photo match (Ph4-5). ✓
- (608) phone, confirmed checklist → Phase 6 end card, Phase 2 script. ✓

**Critical-path gates:** Phase 1 (avatar likeness) and Phase 7 (master) require Daniel's sign-off before proceeding.
