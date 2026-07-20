# Production redirect plan

Captured 2026-07-17 from the live Rank Math redirections table (73 items,
read-only via wp-admin) plus HTTP verification. Review date matters: re-read
the table before executing cutover.

## Finding 1: the one real conflict comes from the WPCode shim, not Rank Math

`/garage-door-repair/` 301s to `/garage-door-repairs-to-make-before-spring/`
(verified live). This redirect is NOT in the Rank Math table; it is served by
the temporary WPCode snippets 7327/6781 (the stale Rank Math PRO redirections
shim). The new site makes `/garage-door-repair/` a primary service page.

**Action at cutover: retire the WPCode shim redirect for
`/garage-door-repair/` (and audit the shim's other entries) BEFORE the new
routes go live.** The shim was always temporary (pending Rank Math PRO
3.0.116+); cutover is its natural end of life.

> **CORRECTION (2026-07-19) — HARD PRE-LAUNCH BLOCKER. The source above is
> WRONG.** Re-verification on staging shows `/garage-door-repair/` returns HTTP
> **200 and then a CLIENT-SIDE redirect** (JS or meta refresh) to the article —
> it is NOT a server 301. Ruled out on staging: Rank Math redirections (no
> `url_to` to the article), all active WPCode snippets (7327 is only a wp-admin
> form fix; 6781 is an Elementor template — neither redirects this URL),
> `.htaccess`, a WP post at that path (none — virtual route), WP core old-slug
> meta (article ID 4591 has none), and the active snippets' JS. **Retiring the
> WPCode shim will therefore NOT clear this**, and the flagship
> `/garage-door-repair/` keyword URL would ship serving a blog post instead of
> the (built, schema-rich) service page. The real injector of the client-side
> redirect must be found and removed before launch. The staging CSP
> (`connect-src 'none'`) blocks fetching the raw HTML in-browser, so trace it
> server-side (render the URL via wp-cli / capture output, or check page-builder
> / theme / mu-plugin front-end injection).
>
> **RESOLVED (2026-07-19). It was never a configured redirect.**
> `/garage-door-repair/` had **no backing WP page** (a virtual service route with
> no post; get_page_by_path returned nothing). With no page it 404s, and
> WordPress core's `redirect_guess_404_permalink()` guessed the closest published
> slug — `garage-door-repairs-to-make-before-spring`, which literally starts with
> "garage-door-repair" — and redirected there. Same root cause as
> `/garage-door-tune-up/` and `/protection-plans/`, which 404'd outright.
> **Fix = create the backing page** (done on staging: repair 7726, tune-up 7724,
> protection-plans 7725; the overhaul then renders the service page from the code
> registry, with full Service/FAQPage/Breadcrumb schema). **CUTOVER ACTION:
> create these three pages on PRODUCTION.** No WPCode/Rank Math/OTTO change is
> needed for this. (Leftover Metasync/OTTO residue — options, empty
> `*_metasync_redirections` tables, `searchatlas_api_key`, `metasync_data/` logs —
> is unrelated to this redirect but worth cleaning up.)

## Finding 2: no Rank Math source collides with the new route registry

All 73 sources are legacy slugs (unprefixed Clopay names, old city-service
pages, old multisite paths, LiftMaster collection paths, blog renames,
typo/utility fixes). None of them equals a new-registry URL. The table can
stay as-is through cutover.

## Finding 3: chain risks to clean (targets that themselves redirect)

These targets point at OLD multisite paths that now redirect again
(`/madison/...` -> `/wi/...`, `/lexington/...` -> `/ky/...`), producing 301
chains. Update the targets to the final URLs:

| Source | Current target | Update to |
|---|---|---|
| `madison-garage-door-spring-repair-replacement-near-you/` | `/madison/garage-door-spring-repair/` | `/wi/garage-door-spring-repair/` |
| `madison-garage-door-spring-replacement-service-near-you/` | `/madison/garage-door-spring-repair/` | `/wi/garage-door-spring-repair/` |
| `madison-garage-door-opener-repair-installation-replacement-near-you/` | `/madison/garage-door-opener-repair/` | `/wi/garage-door-opener-repair/` |
| `madison-garage-door-installation-replacement-near-you/` | `/madison/garage-door-installation/` | `/wi/garage-door-installation/` |
| `madison-garage-door-repair-near-you/` | `/madison/` | `/wi/` |
| `twins-garage-doors-madison-wisconsin` | `/madison/` | `/wi/` |
| `madison/shorewood-hills/` | `/madison/about-us/` | `/wi/about-us/` |
| `deforest-garage-door-service-2/` | `/madison/garage-door-opener-in-deforest-wi/` | `/wi/location/deforest/` |
| `lexington-garage-door-opener-repair-installation-replacement-near-you/` | `/lexington/garage-door-opener-repair/` | `/ky/garage-door-opener-repair/` |
| `lexington-garagl-door-installation-replacement-near-you/` | `/lexington/garage-door-installation/` | `/ky/garage-door-installation/` |
| `service-in-madison-wi/` | `/location/madison/` | `/wi/location/madison/` |
| `deforest-garage-door-service` (2 rows) | self-referential `/deforest-garage-door-service` | `/wi/location/deforest/` (fix loop) |

Also: `lexington-garage-door-repair-near-you/` -> `/blog/` is a poor target;
point it at `/ky/garage-door-repair/`. Indianapolis sources point at
`/indianapolis/` (dead market); point them at `/locations/`.

## Finding 4: new redirects to ADD at cutover

| Source | Target | Reason |
|---|---|---|
| `/design-your-door/` | `/door-builder/` | Staging classifies it as builder; production should converge on one URL (or keep serving it as builder, in which case no redirect) |
| `/location/madison/` | `/wi/location/madison/` | Merge legacy location CPT duplicate |
| `/shorewood-hills/` | `/wi/service-area/` | Root-level stray city page |
| `/hormann-garage-doors/` | `/clopay-garage-doors/` | Owner decision: retire Hormann line |
| 10 pruned blog posts (see cutover decisions doc) | `/blog/` | Owner-approved prune |
| `/madison-garage-door-repair/` | keep as-is | Live SEO page; do NOT redirect (campaign-preserve) |
| Off-topic blog posts (HVAC, paint colors, energy tips) | `/blog/` | Only if pruned; list pending owner approval |

## Finding 5: keep-list

`/madison-garage-door-repair-lp/` and `/wi/thank-you-g-ppc-lp` are preserved
campaign routes; never redirect them. The 23 unprefixed->clopay-prefixed
product redirects carry live hits; keep.

## Execution order at cutover

1. Update the chain targets (Finding 3) in Rank Math.
2. Add the new redirects (Finding 4).
3. Retire the WPCode shim entries superseded by the above (Finding 1).
4. Crawl every source and every new-registry URL: assert exactly one hop,
   no loops, no 404s.
