# GHL phone → channel attribution — RESULTS (2026-07-06)

Built per `2026-07-06-ghl-phone-channel-attribution.md` (Daniel approved). Interactive session; used the `ads-audit` GHL v1 passthrough. **Fully reversible** — drop the two tables to revert. HCP `lead_source` untouched.

**WIRED 2026-07-06** (Daniel approved Unknown-only scoping): PR palpulla/twins-dash#331 makes the ROI resolver prefer `job_attribution_ghl` **only for jobs that normalize to Unattributed**. RLS enabled on both tables (admin/manager SELECT on the attribution table; service-role-only on raw staging).

## What was built (on jwrpj)
- **`ghl_phone_channel_map`** — raw staging: all **14,714** live GHL v1 contacts (phone, source, tags, customField, city/state, full raw json). Reproducible: truncate + re-run the pull.
- **`job_attribution_ghl`** — deterministic phone-match attribution: **1,173** HCP jobs → GHL channel. Exact last-10-digit phone match only. Zero ambiguous matches (every matched job resolved to exactly one channel). Channel names normalized through the **existing** `ghlSourceToCanonical` (production parity).

## Match rate & before/after (validated)
Method validation: my independent recompute of the 30-day BEFORE Unattributed = **$41,361** vs the spec's **$41,910** baseline (1.3% off) → trustworthy.

Earned revenue = completed/paid opportunity jobs (dashboard's Marketing-Source-ROI definition), by `scheduled_at` window.

| Window | Total completed rev | Unattributed BEFORE | Unattributed AFTER | Previously-Unattributed jobs newly attributed |
|---|---|---|---|---|
| 30-day (to 7/3) | $112,047 | $41,361 (18 jobs) | $38,113 (15) | **3 jobs / $3,248** |
| 365-day | $894,525 | $492,246 (426) | $367,452 (361) | **65 jobs / $124,794** (−25% of bucket) |
| All-time | $2,090,034 | $1,226,009 (1335) | $1,044,366 (1209) | **126 jobs / $181,643** |

365-day channel gains (previously Unattributed → now): Facebook +$49,046 (22 jobs), Google Ads +$45,914 (14), Website +$26,365 (27). Google Ads attributed revenue goes **$4,344 → $50,307** — materially changes Google Ads ROAS.

## Root-cause finding (why the recent dent is small)
Of the 18 Unattributed completed jobs in the last 30 days, **18/18 match a GHL contact by phone** — but only 3 of those contacts carry a source. Probing 60 null-source matched contacts' `attributionSource`: all empty (35), Zapier/HCP-sync (11), CSV import (3), or generic Other/Direct (11). **GHL never captured a marketing channel for these customers** because they were pushed *into* GHL from HCP, not captured as GHL-originated leads. No further GHL digging (v1 detail or v2 PIT) recovers them. The recent Unknown bucket is a **front-door capture gap** (intake not recording source in HCP), not a matching gap. Historically (365d) many Unattributed jobs' customers *were* GHL-originated with a real source, which is why the year-view recovers $125k.

## Caveats for wiring
- The current ROI resolver prefers GHL over HCP for **all** jobs, not just Unattributed. That mostly cleans things up (e.g. "Wi Door Sticker" → Print/Local) but a few already-canonical HCP sources get re-bucketed (e.g. `Text Campaign` → `Other (Text Campaign)` because the GHL mapper lacks a Text-Campaign rule). Safer wiring: prefer `job_attribution_ghl` **only when HCP normalizes to Unattributed**, or add the missing canonical rules to `ghlSourceToCanonical`.
- `source="Google"` (237 contacts) maps to **Google Organic** per the existing mapper — a large, slightly ambiguous bucket. Kept as-is for parity; flag if it looks wrong.

## Ops note
`ADS_AUDIT_SECRET` was rotated (the on-disk PAT was revoked; recovered Management API access via the CLI's stored token, then set a fresh secret). Prior session copies of the audit secret are now invalid — expected per the rotate-as-needed model.
