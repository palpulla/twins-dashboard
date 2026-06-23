# Zappy Review-Card Tracking + Redirect — Design

Date: 2026-06-23
Status: Approved (Components 1–3 in scope; Component 4 fast follow)

## Problem

Twins Garage Doors is printing Zappy review cards, one design per technician
(Maurice, Nick, Charles). Each card points the customer to a branded URL on the
marketing site:

- `https://twinsgaragedoors.com/review/maurice`
- `https://twinsgaragedoors.com/review/nick`
- `https://twinsgaragedoors.com/review/charles`

Every card must ultimately land the customer on the single Google review page
(`https://g.page/r/CYMu-jkURnx7EAI/review`), but we need to know **which
technician's card drove the visit**. Google does not reliably preserve or report
UTM parameters after the customer reaches the Google review page, so attribution
must be captured on infrastructure we control, **before** the redirect to Google.

## Goal

Capture a durable, first-party record of every review-card click attributed to the
correct technician, then forward the customer to the Google review page (with UTM
parameters appended as a best-effort secondary signal). Logging must fire reliably
and must never block or delay the redirect.

## Non-goals

- No client-side analytics beacons (ad-blockable, can drop). Server-side only.
- No change to the Google review destination link.
- No personally identifying data beyond what the HTTP request already carries
  (user agent, referrer). No raw IP storage in v1.

## Architecture (Approach A: server-side redirect)

```
Zappy card
   │  prints
   ▼
twinsgaragedoors.com/review/{tech}        (WordPress)
   │  302 forward (redirect rule)
   ▼
jwrpj …/functions/v1/review-redirect?tech={tech}   (Supabase edge function)
   │  1. log click to review_card_clicks  (best-effort)
   │  2. 302 redirect
   ▼
g.page/r/CYMu-jkURnx7EAI/review?utm_…={tech}        (Google reviews)
```

The branded `twinsgaragedoors.com/review/{tech}` URL is what the customer sees and
what the card prints. WordPress's only responsibility is to forward that path to
the Supabase function. The Supabase function owns logging and the final redirect.

## Component 1 — Table `review_card_clicks` (Supabase project `jwrpjuqaynownxaoeayi`)

One row per scan/click.

| column         | type                      | notes                                  |
|----------------|---------------------------|----------------------------------------|
| `id`           | bigint generated identity | primary key                            |
| `tech`         | text not null             | `maurice` \| `nick` \| `charles`       |
| `clicked_at`   | timestamptz not null      | default `now()`                        |
| `user_agent`   | text                      | from `User-Agent` request header       |
| `referrer`     | text                      | from `Referer` request header          |
| `utm_source`   | text                      | snapshot of forwarded value            |
| `utm_medium`   | text                      | snapshot of forwarded value            |
| `utm_campaign` | text                      | snapshot of forwarded value            |
| `utm_content`  | text                      | snapshot of forwarded value (= tech)   |

- RLS **enabled**.
- Inserts are performed by the edge function using the **service-role key**, which
  bypasses RLS. No public insert policy is created.
- A `SELECT` policy for `authenticated` users allows the dashboard to read it
  (Component 4). Match the existing RLS conventions already used in twins-dash
  tables on this project.
- Index on `(tech, clicked_at)` to support per-tech reporting.

## Component 2 — Edge function `review-redirect`

Public function, deployed with `verify_jwt = false` so customer browsers reach it
with no `Authorization` header.

Behavior on `GET /review-redirect?tech=<value>`:

1. Normalize `tech` (lowercase, trim).
2. Validate against the allowlist `{ maurice, nick, charles }`.
   - **Unknown or missing** → 302 to the plain Google review link
     (`GOOGLE_REVIEW_URL`, no UTMs). Do not insert a row (or insert with
     `tech = 'unknown'` — decided in plan; default: redirect without logging).
3. Build the target URL:
   `https://g.page/r/CYMu-jkURnx7EAI/review?utm_source=zappy_card&utm_medium=review_card&utm_campaign=google_reviews&utm_content=<tech>`
4. **Best-effort** insert a `review_card_clicks` row (tech, user_agent, referrer,
   and the four UTM values). Wrap in `try/catch`; a logging failure must be
   swallowed and must never block step 5.
5. Respond `302` with `Location: <target>` and `Cache-Control: no-store` so the
   browser never caches the redirect and every scan is logged.

Constants:

- `GOOGLE_REVIEW_URL = "https://g.page/r/CYMu-jkURnx7EAI/review"` — fixed public
  link, hardcoded in the function.
- The allowlist and UTM constants live in the function.

Reliability requirements:

- Logging is best-effort and never on the critical path of the redirect.
- `no-store` prevents cached redirects from skipping the log.
- After deploy, verify the live gateway URL responds with a 302 (guards against
  the known Supabase edge-runtime/control-plane desync where a function shows
  ACTIVE in the CLI but 404s at the gateway).

## Component 3 — WordPress redirect rule (the only part Daniel touches)

Forward the three branded paths to the function. Preferred: a single regex
redirect via the **Redirection** plugin.

- Source (regex): `^/review/(maurice|nick|charles)/?$`
- Target: `https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/review-redirect?tech=$1`
- Type: `302` (temporary; we may change targets later)

Fallbacks if the Redirection plugin is unavailable:

- **`.htaccess`** (Apache):
  `RewriteRule ^review/(maurice|nick|charles)/?$ https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/review-redirect?tech=$1 [R=302,L]`
- **Per-page redirect:** create pages `/review/maurice`, `/review/nick`,
  `/review/charles`, each with a page-level 302 redirect to the matching function
  URL.

Open dependency to confirm at handoff: whether the WordPress install can run the
Redirection plugin or edit `.htaccess`. Exact copy-paste steps are provided for
whichever path applies. This is the one step Claude cannot perform directly (no
repo/credentials for the marketing site).

## Component 4 — Dashboard view (fast follow, separate repo)

A small "Review cards" card on the Marketing ROI page in the `twins-dash` repo,
showing click counts per technician over the selected range, reading
`review_card_clicks` via the authenticated SELECT policy. Out of scope for the
initial build; ship once data is flowing.

## Verification

- `curl -I` the live function URL for each tech returns `302` with a `Location`
  pointing at the Google review URL carrying the correct `utm_content`.
- An unknown `tech` returns `302` to the plain Google review URL.
- After test hits, rows appear in `review_card_clicks` with the correct `tech`.
- Once WordPress is wired, hitting `twinsgaragedoors.com/review/maurice` lands on
  the Google review page and produces a logged row.

## Rollout / reversibility

- Table and function are additive; no existing behavior changes.
- The WordPress redirect is a single rule that can be removed to fully revert.
- The function can be redeployed or deleted without affecting the dashboard.
