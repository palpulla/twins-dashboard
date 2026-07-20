# Prompt for a new Claude Code session — Twins staging website overhaul

Paste everything below this line into a fresh session started from `/Users/daniel/twins-dashboard`.

---

Continue the Twins Garage Doors staging website overhaul. Read this whole brief before touching anything.

## Where the work lives
- Worktree: `/Users/daniel/twins-dashboard/.worktrees/staging-site-safety`, branch `claude/staging-remediation` (~36 commits on top of `codex/staging-site-safety`). All website work happens THERE, not in the outer repo.
- Brand core (portable, no WordPress functions, no `https://` literals, no `<form>`/`name=` in templates): `website/twins-brand-experience/` (templates/, components/, src/, config/, assets/).
- WordPress overhaul layer (route classifier, renderers, adapters, bridge templates): `website/staging-safety/mu-plugins/twins-staging-overhaul/`.
- Production cutover kit (adapters incl. HCP booking + Supabase lead intake, 73-rule redirect plan, release runbook): `website/production-cutover/`.
- Audit + decisions: `docs/reviews/2026-07-17-full-staging-audit.md`, `docs/handoff/2026-07-17-cutover-decisions.md`.
- Memory file `project_staging_overhaul_remediation.md` (auto-memory) has the full pipeline knowledge — trust it.

## Current state (all deployed and live-verified on https://danielj140.sg-host.com/, behind 401)
12 sealed releases r1–r12 shipped. Done: nav/menus (Garage Doors = Collections/Openers/Design Your Door only), LP-parity home hero, 13 crew-voice service pages, location pages + illustrated door map, JSON-LD, phones per market (main 833-833-2010, WI dual Madison 608-420-2377 / Milwaukee 414-800-9271, KY 859-440-2227, IL 815-800-2025), footer condensed + NAP (2921 Landmark Pl #206, Madison WI 53713), reviews pinned 4.9 × 699, callback form (LP-style), team page = uniform 7-card crew grid (Daniel, Tal, Charles Rue, Maurice Williams, Nicholas Roccaforte, Ivory Tianga CSR, Aman Kharga Ops Mgr; branded SVG door-avatar placeholders for the last three until real headshots exist), garage-door SVG art system (`components/door-art.php`: door / animated door-open / spring / roller / keypad; pure CSS `twins-door-cycle` animation), door builder primary preview = parametric illustrated SVG door (`builderDoorSvg` in `assets/js/twins-builder.js` — panel/window/glass styles keyword-inferred from option titles, face painted via `builderDominantColor`; NEVER interpolate option titles into that SVG string), contact page = callback form + Book Online (inert dialog on staging, HCP link via production adapter at cutover), blog fully revamped: 187 posts with rewritten titles/metas/bodies, branded index at /blog/, full-width articles, and 187/187 featured images (12 branded cluster illustrations, staging attachments 7712–7723).

## Deploy pipeline (load-bearing — follow exactly)
From `website/twins-brand-experience`:
1. Env: `TWINS_STAGE_SSH_TARGET='u2356-y8avsfoqgaqv@ssh.danielj140.sg-host.com'`, `TWINS_STAGE_SSH_KEY="$HOME/.ssh/twins_stage_deploy_20260717"`, `TWINS_STAGE_SSH_HOSTKEY_SHA256='SHA256:HlFY3XZvLg3jVR6hUb/G5YQzCs81HtAc1+XvqSRbPo4'`. SSH port 18765.
2. Every release: rotate transaction id `staging-remediation-rN-20260717` (next is r13) in `tools/deploy-private-staging.mjs`, `tools/private-staging-deploy.php`, `tests/contracts/package-contract.test.cjs`, `tests/contracts/deployment-tool-contract.test.cjs`, `manifests/host-verification.json` (and staging-runtime.json if it appears there).
3. If CSS/JS changed: update the 16-hex sha256 prefixes in `tests/contracts/site-unification.test.cjs`.
4. Repin BOTH manifests to fixpoint (recompute size+sha256 of every entry from local files; staging-runtime first, then host-verification twice). NEW files must be ADDED to both manifests (deploy entry with destination in staging-runtime, verify entry in host-verification) or they silently don't deploy.
5. `npm run build:packages` → STAGING_PACKAGES_BUILT, then suites: `npm run test:contracts` (75), `npm run check:repo` (needs PHP — Docker shim pattern: a `php` wrapper running `docker run php:8.3-cli`), `(cd ../staging-safety && node --test tests/*.test.cjs)` (50), `npm run test:browser` (31 local).
6. `npm run deploy:staging:dry-run` → `:capture` → `:release`, then flush cache: `ssh -i $TWINS_STAGE_SSH_KEY -p 18765 $TWINS_STAGE_SSH_TARGET "site-tools-client -j domain-all update id=1 flush_cache=1"`.
7. Verify live through Daniel's logged-in Chrome (claude-in-chrome MCP; staging 401s for everyone else). Always append `?rN=1` cache-busters; note screenshots can catch images mid-decode (re-shoot before calling something broken).
- wp-cli over SSH at `~/www/danielj140.sg-host.com/public_html`. GOTCHA: `wp eval-file` silently no-ops on large files — use `wp eval 'require getenv("HOME")."/staging-safety/<f>.php";'` and assert echoed counts. `ssh` inside `while read` loops needs `-n`. `wp db export` fails (proc_open disabled) — use mysqldump with wp-config creds.

## Hard rules
- Staging contracts: zero outbound HTTP, zero `<form>` elements, `book.housecallpro.com` must never render on staging, safety plugin `twins-staging-safety.php` is intentionally NOT deployable.
- Never invent people, prices, stats, or reviews. No em-dashes in any copy for Daniel. Crew voice, no AI-tell words (ultimate/comprehensive/seamless/hassle-free). Commit early and often; never touch the outer repo's other projects.
- Booking must stay inert on staging; production gets HCP via `production-cutover/production-adapters.php`.

## Open items (the actual to-do)
1. Owner gates before cutover: Daniel's page-by-page sign-off; IL test call (815) 800-2025; Clopay licensing check (clopaydealer.com); pick go-live day → then execute `website/production-cutover/release-runbook.md` (redirects incl. WPCode shim 7327/6781 retirement, adapters, blog replay to production, live Google review-count integration — needs referer-unrestricted Places key).
2. Real headshots for Nicholas, Ivory, Aman → replace door-avatar placeholders in `templates/team.php` (add images via `tools/build-owned-images.mjs` spec + picture.php registry; regenerate provenance).
3. Scrub the dead hardcoded Gemini key from `twins-media-generator/scripts/generate_testimonial.py` and 3 worktree settings files (it's invalid but shouldn't sit in git). Note: Gemini free-tier keys have 0 quota for image models; image gen goes through Daniel's Higgsfield Plus MCP (nano_banana_pro, 2 credits/img, ~18 credits left).
4. Any new feedback from Daniel on the staged site (screenshots from him may be stale browser cache — verify live with a cache-buster before believing a regression).

Start by running `git -C /Users/daniel/twins-dashboard/.worktrees/staging-site-safety log --oneline -5` and `git status` there to confirm the branch state, then ask Daniel what he wants to tackle, or proceed with his message if he gave one.
