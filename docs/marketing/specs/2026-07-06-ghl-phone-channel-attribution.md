# GHL phone → channel attribution match

**Status:** approved by Daniel 2026-07-06, to run in an interactive Claude Code session (needs GHL/audit secrets; the Monday-brief scheduled session only has public keys).
**Goal:** collapse the "Unknown"/Unattributed lead-source bucket by attributing HCP jobs from GHL's phone→channel data. Deterministic phone match only — never keyword-guess a channel (see `feedback_no_heuristic_classifiers_for_business_rules`).

## Why
- ~35% of 30-day earned revenue is HCP lead_source "Unknown" ($41,910 in the 7/3 baseline); 5 jobs/$3,529.70 in the 6/26–7/2 week.
- `ghl_contacts.attribution_source` (jsonb) is empty and `.source` is null on 83% of the 1,222 contacts, so the already-synced fields are too sparse. Daniel: there is a GHL reporting page linking **every phone number → a marketing channel name**; match those against HCP clients.

## Build
1. **Reach GHL** via the `ads-audit` edge fn GHL passthrough: POST `{ghl: {path, v1: true}}`, gate header `x-audit-secret: ADS_AUDIT_SECRET` (rotate via `npx supabase secrets set` if the session copy is stale — see `reference_ads_audit_fn`). Key is a v1 location key (rest.gohighlevel.com).
2. **Pull the phone → channel map** (the reporting page Daniel referenced). Capture: tracking/contact phone, channel name, and whatever campaign granularity GHL exposes. Store raw in a new staging table `ghl_phone_channel_map` for reproducibility.
3. **Normalize phones** to last-10-digits on both sides. Join GHL phone → HCP customer/job phone (`jobs` / customers on jwrpj).
4. **Write attribution to a NEW table** `job_attribution_ghl(job_id, channel, matched_phone, match_method, mapped_at)` — do **not** overwrite HCP `lead_source`. The ROI hook can prefer this table; keeps the change fully reversible and KPI math immutable.
5. **Exact phone matches only.** Unmatched jobs stay Unattributed. No fuzzy/keyword inference.
6. Normalize channel names to the canonical set already used by `use-marketing-source-roi.ts` (Google Ads / Google LSA / Facebook / etc.).
7. Report: how many previously-Unknown jobs got attributed, and the corrected earned-revenue-by-channel split. Surface in the next Monday brief.

## Guardrails
- Reversible: everything lands in new tables; drop them to revert.
- Show Daniel the match-rate + before/after split before wiring the ROI page to prefer it.
- Respect Charles co-tech and revenue-recognition rules downstream (attribution only sets channel, not revenue eligibility).

## Related
- Backlog #3 (attack Unknown) + #6 (GHL attribution). This is the "C" piece deferred in the 2026-07-03 CAP.
- Separate/unrelated: the $34,947 historical LSA-misfiled-in-Google-Ads cleanup (change-log 2026-07-06). Low urgency, not part of this build.
