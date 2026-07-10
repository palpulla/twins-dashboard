# Task 5 — /wi phone + schema cleanup: before/after log (2026-07-10)

All values read live from /wi admin via logged-in in-tab fetch. Appended as work proceeds.

## Snippet backups (BEFORE)

| Snippet | Title | Location | Len | sha256 (before) | Backup file |
|---|---|---|---|---|---|
| 6657 | Main Site Number Pool | Site Wide Header, ACTIVE | 224 | f20cb078c0e5744dd1e3cd55cc061d2a13dcb457a100dea4cd2c6a9061589c27 | snippet-6657-before.php |
| 6753 | CAP 2026-07: mobile sticky call bar + viewport zoom fix | Site Wide Footer, ACTIVE | 1424 | 7380f12e06ddbf62a951b7781e54c09965935e644f2d039c5703501d7d0206e5 | snippet-6753-before.php |
| 6754 | CAP 2026-07: LocalBusiness schema | Site Wide Footer, ACTIVE | 450 | 50d2b4edda2643e2ae775fba63e0bcb72097b5c731fc79c60e2e0de1e0baa932 | snippet-6754-before.php |

Local backup files verified byte-identical (sha256 recomputed locally matches in-browser hash).

## Rank Math Local SEO (/wi, rank-math-options-titles) — BEFORE

Read from the embedded settings JSON on the options page (2026-07-10):

- phone: `(608) 888-8785`
- local_address.streetAddress: `620 N Carroll St`  (WRONG — old address)
- local_address.addressLocality: `Madison`
- local_address.addressRegion: `WI`
- local_address.postalCode: `53703`
- local_address.addressCountry: `USA`
- geo: `43.078530,-89.391760`  (620 N Carroll coords — stale)
- local_business_type: `HomeAndConstructionBusiness`
- local_seo_about_page: `1924`, local_seo_contact_page: `2123`, price_range: `$$`
- knowledgegraph_type: company, knowledgegraph_name: Twins Garage Doors
- (maps_api_key present in settings — not copied here)

Target AFTER: streetAddress `2921 Landmark Pl, Ste 206`, locality Madison, region WI, postal `53713`, phone `+16084202377`, geo cleared (no invented coords).
