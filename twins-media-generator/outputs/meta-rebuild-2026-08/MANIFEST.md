# Meta Rebuild, Round 1 Creative Manifest

**Generated:** 2026-08-10
**Account:** act_388398022876424
**Briefs:** `docs/marketing/creative/2026-08-09-higgsfield-hook-briefs.md`
**Spec:** `docs/superpowers/specs/2026-08-09-meta-ads-overhaul-design.md`

All generations: Higgsfield `seedance_2_5`, 9:16, 5.0s, 24fps, 32.5 credits each.
Upscales: `bytedance_video_upscale`, preset `aigc`, 1080p, 24fps, roughly 0.10 credits each.

Credits: 1,215.99 before, **923.19** after. Round 1 total cost about 293.

## Committed heroes

| File | Ad | Job ID | Source job |
|---|---|---|---|
| `brief3_hook_v2_UPSCALED.mp4` | `Install_ReviewProof_Video_v1`, Cell 2 Forms | `02cc6efe-dad1-42f2-b3fd-25a27b06cc3b` | `8297c26c-0eaf-44a5-b13a-c55e1f82f6d9` |
| `brief1_spring_v1_UPSCALED.mp4` | `Repair_SpringSnap_Reel_v1`, Cell 1 Calls | `05d96050-3827-4d72-b9a4-33f78ff766ea` | `c51e3628-3a4b-4b02-8b93-2742a2607876` |
| `brief2_leftopen_v1_UPSCALED.mp4` | `ServiceCall_LeftOpen_Reel_v1`, Cell 1 Calls | `d2135f6d-9326-4b4e-9380-370d5ef21ac4` | `55218c5f-9410-4c70-9444-c452ab1f8428` |

All three verified at 1080x1920.

## Rejects, gitignored, still on disk

| File | Job ID | Why rejected |
|---|---|---|
| `brief3_hook_v1.mp4` | `73ce2730-f53b-41e8-8408-26f3a89063a7` | Cooler light, plainer door than v2. Close second |
| `brief3_hook_v3.mp4` | `4846d974-da66-4c43-87ae-eacf4042f6d8` | Camera barely moves. Reads as a still with a drift |
| `brief1_spring_v2.mp4` | `297b8a58-845e-4db4-9187-430b8282d829` | Spring too small in frame, illegible at thumbnail size |
| `brief1_spring_v3.mp4` | `184415af-6fca-49a9-a676-c74473ec5e83` | Break point flares like a sparkle, not metal failing |
| `brief2_leftopen_v2.mp4` | `2720c465-a0ff-44fd-8907-197fd128bca8` | Shorter push, cluttered garage interior loses the emptiness |
| `brief2_leftopen_v3.mp4` | `981cf5a1-41ff-4a09-a3b2-a159893132ff` | Least motion, open door reads as a lit panel not a void |

## Prompt learnings, apply to all future jobs

1. **"Camera + lens + visible grain" produces photoreal.** "Photoreal architectural
   videography" produces CGI. Brief 1 and 2 used the former and are visibly more real than
   Brief 3.
2. **Never write "static locked-off."** It produced the frozen `brief3_hook_v3`. Dropping it
   from the Brief 2 prompt gave real camera motion in all three variants.
3. **`seedance_2_5` always renders 720x1280** and silently ignores explicit `width`/`height`
   params. Upscaling is the only route to 1080p. Budget one upscale per keeper.
4. A literal prompt can be intercepted by a **preset recommendation** ("IN THE DARK" fired on
   the spring prompt). Pass `declined_preset_id` to generate literally.

## Known issue, unresolved

`brief1_spring_v1` shows the spring **unwinding and expanding** rather than cleanly snapping.
A real torsion spring failure leaves a visible gap where the coil separated and happens far
faster. Reads fine to a homeowner, visibly wrong to a technician. Current plan is to cut away
inside 1.5 seconds rather than regenerate, since the first second is the strongest part.

## Not produced, deliberately

A door-replacement before/after. Twins has no genuine one and generating a synthetic pair
would recreate the defect behind the account's worst ad (0.40% CTR on 74,586 impressions,
`twins_beforeafter_demo.mp4`, two different houses). That asset must come from a real job.

## Blocked before any of this becomes an ad

1. Test-call **(608) 688-9109**, the Facebook tracking number. Blocks every endcard.
2. Daniel to film three pieces to camera, framing per `twins_49_REAL_welcome.mp4`.
3. Real CompanyCam photos for the review-proof ad.
4. Confirm the `[CONFIRM]` figures in the briefs.
