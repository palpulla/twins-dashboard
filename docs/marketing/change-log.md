# Marketing change log

Standing rule (CAP doc §8): no conversion action, pixel rule, or bid-strategy change ships without an entry here.

## 2026-07-04 — Claude, per approved corrective-action plan (spec 2026-07-04)

### Google Ads (customer 7171993484, via ads-audit edge fn)

| # | Change | Detail | Revert |
|---|---|---|---|
| G1 | Created shared negative-keyword set "CAP negatives 2026-07" | `sharedSets/12144677471`; 42 PHRASE negatives (diy, how to, manual(ly), parts, kit, lowes, home depot, menards, harbor freight, amazon, youtube, video, instructions, panel only, jobs, hiring, salary, training, rental, apartment, weight, lubricant, wd-40/wd40, cost, for sale, supplier(s), watertown, janesville, hayward, beloit, milwaukee, green bay, appleton, oshkosh, eau claire, la crosse, wausau, kenosha, racine) + 4 EXACT brand negatives (twins garage doors variants) | Remove set or detach (G2) |
| G2 | Attached the set to both Search campaigns | `campaignSharedSets/23209603182~12144677471` (Repair), `23209631247~12144677471` (Installation) | Remove the two campaignSharedSets |
| G3 | Installation Search daily budget $38 → $15 | `campaignBudgets/15103082338` amountMicros 38000000 → 15000000. Rationale: LP has no form + dead CTAs; last conversion May 22 | Set amountMicros back to 38000000 |
| G4 | Demoted "Click to call" conversion action to Secondary | `conversionActions/7635727417` primaryForGoal true → false. A tap on a number is not a lead (CAP §1.3 policy) | Set primaryForGoal back to true |

Deliberately NOT changed: tCPA (deferred until conversion data is clean ~2 weeks); competitor-name negatives (borderline: ~$340 spend, 2 conversions ≈ $170/conv — Daniel/Legit5 call); PMax brand exclusion (needs brand lists, no public API path — Legit5 to do in UI); geo (already Presence-only).

### Meta (act_388398022876424)

| # | Change | Detail | Revert |
|---|---|---|---|
| M1 | Deactivated pixel 'Lead' event rule | Rule 3986528874925127 fired on the Google Ads thank-you URL (/wi/thank-you-g-ppc-lp/) — cross-channel contamination (CAP §1.1). Set INACTIVE (not deleted; UI-created rules hard-delete). Real per-form Lead rules ship with the form fix | Set status ACTIVE |

Deferred: custom-conversion rule separation — Meta CC rules are immutable (archive+recreate only); recreating now is pointless because the broken forms produce no thank-you URLs. Lands with the form rebuild. CAPI restoration remains blocked on Meta re-auth (token expired 2026-05-03).
