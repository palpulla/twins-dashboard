# Zappy Review-Card Tracking + Redirect Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Capture a durable first-party record of every Zappy review-card click attributed to the correct technician (Maurice/Nick/Charles), then show a branded interstitial that redirects the customer to the Twins Google review page.

**Architecture:** WordPress 302-forwards `twinsgaragedoors.com/review/{tech}` to a public Supabase edge function (`review-redirect`) on project `jwrpjuqaynownxaoeayi`. The function logs the click server-side (best-effort, never blocking) into `public.review_card_clicks`, then returns a Twins-branded HTML interstitial that auto-redirects to `https://g.page/r/CYMu-jkURnx7EAI/review` with this tech's UTM params.

**Tech Stack:** Supabase Postgres (migration), Supabase Edge Functions (Deno + `@supabase/supabase-js@2.39.3`), Deno test runner, WordPress (Redirection plugin / `.htaccess`).

**Spec:** `docs/superpowers/specs/2026-06-23-zappy-review-card-tracking-design.md`
**Approved design mockup:** `docs/superpowers/specs/2026-06-23-zappy-review-pages/review-interstitial.html`

**Repo for all code:** `twins-dash` (its `supabase/` owns project `jwrpjuqaynownxaoeayi`). All paths below are relative to `twins-dash/`.

---

## File Structure

- Create: `supabase/migrations/20260623120000_review_card_clicks.sql` — table, index, RLS.
- Create: `supabase/functions/review-redirect/index.ts` — HTTP handler.
- Create: `supabase/functions/review-redirect/lib.ts` — pure helpers (tech validation, URL build).
- Create: `supabase/functions/review-redirect/interstitial.ts` — branded HTML template + token render.
- Create: `supabase/functions/review-redirect/lib.test.ts` — Deno unit tests for helpers.
- Modify: `supabase/config.toml` — add `[functions.review-redirect] verify_jwt = false`.

WordPress changes (Task 7) are done in the WordPress admin, not in this repo.

---

## Task 1: Database migration — `review_card_clicks`

**Files:**
- Create: `supabase/migrations/20260623120000_review_card_clicks.sql`

- [ ] **Step 1: Write the migration SQL**

```sql
-- Per-click log for Zappy review cards (one row per scan/click).
-- Inserts come from the review-redirect edge function via the service-role key,
-- which bypasses RLS. Reads are for authenticated dashboard users only.

create table if not exists public.review_card_clicks (
  id           bigint generated always as identity primary key,
  tech         text not null check (tech in ('maurice', 'nick', 'charles')),
  clicked_at   timestamptz not null default now(),
  user_agent   text,
  referrer     text,
  utm_source   text,
  utm_medium   text,
  utm_campaign text,
  utm_content  text
);

comment on table public.review_card_clicks is
  'Zappy review-card clicks, attributed per technician, logged by the review-redirect edge function before redirecting to Google reviews.';

create index if not exists review_card_clicks_tech_clicked_at_idx
  on public.review_card_clicks (tech, clicked_at desc);

alter table public.review_card_clicks enable row level security;

-- Authenticated users (dashboard) may read. No insert/update/delete policy:
-- the edge function uses the service-role key, which bypasses RLS.
drop policy if exists review_card_clicks_select_authenticated on public.review_card_clicks;
create policy review_card_clicks_select_authenticated
  on public.review_card_clicks
  for select
  to authenticated
  using (true);
```

- [ ] **Step 2: Apply the migration**

Apply to project `jwrpjuqaynownxaoeayi` using the Supabase MCP `apply_migration` (name: `review_card_clicks`, query = the SQL above). If applying via CLI instead (`supabase db push`), also follow the repo's migration-history note: after a manual `psql` apply, INSERT the version row into `supabase_migrations.schema_migrations` (see `docs`/memory `reference_twins_dash_migration_history`).

- [ ] **Step 3: Verify the table and RLS exist**

Run (Supabase MCP `execute_sql` on `jwrpjuqaynownxaoeayi`):

```sql
select
  (select count(*) from information_schema.tables
     where table_schema = 'public' and table_name = 'review_card_clicks') as table_exists,
  (select relrowsecurity from pg_class where oid = 'public.review_card_clicks'::regclass) as rls_enabled,
  (select count(*) from pg_policies
     where schemaname = 'public' and tablename = 'review_card_clicks') as policy_count;
```

Expected: `table_exists = 1`, `rls_enabled = true`, `policy_count = 1`.

- [ ] **Step 4: Commit**

```bash
git add supabase/migrations/20260623120000_review_card_clicks.sql
git commit -m "feat(reviews): review_card_clicks table + RLS"
```

---

## Task 2: Pure helpers + tests (`lib.ts`, `lib.test.ts`)

TDD the logic that has no I/O: tech validation and the Google review URL builder.

**Files:**
- Create: `supabase/functions/review-redirect/lib.ts`
- Test: `supabase/functions/review-redirect/lib.test.ts`

- [ ] **Step 1: Write the failing tests**

```ts
// supabase/functions/review-redirect/lib.test.ts
import { assertEquals } from 'https://deno.land/std@0.224.0/assert/mod.ts';
import { normalizeTech, resolveTech, buildReviewUrl, GOOGLE_REVIEW_URL } from './lib.ts';

Deno.test('normalizeTech lowercases and trims', () => {
  assertEquals(normalizeTech('  Maurice '), 'maurice');
  assertEquals(normalizeTech('NICK'), 'nick');
  assertEquals(normalizeTech(null), '');
});

Deno.test('resolveTech returns display name for valid techs', () => {
  assertEquals(resolveTech('maurice'), { slug: 'maurice', name: 'Maurice' });
  assertEquals(resolveTech('nick'), { slug: 'nick', name: 'Nick' });
  assertEquals(resolveTech('charles'), { slug: 'charles', name: 'Charles' });
});

Deno.test('resolveTech returns null for unknown or empty', () => {
  assertEquals(resolveTech('bob'), null);
  assertEquals(resolveTech(''), null);
});

Deno.test('buildReviewUrl appends the right UTMs for a tech', () => {
  const url = buildReviewUrl('maurice');
  assertEquals(
    url,
    GOOGLE_REVIEW_URL +
      '?utm_source=zappy_card&utm_medium=review_card&utm_campaign=google_reviews&utm_content=maurice',
  );
});

Deno.test('GOOGLE_REVIEW_URL is the fixed Twins link', () => {
  assertEquals(GOOGLE_REVIEW_URL, 'https://g.page/r/CYMu-jkURnx7EAI/review');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `deno test supabase/functions/review-redirect/lib.test.ts`
Expected: FAIL with "Module not found ./lib.ts" (file not created yet).

- [ ] **Step 3: Write the implementation**

```ts
// supabase/functions/review-redirect/lib.ts

export const GOOGLE_REVIEW_URL = 'https://g.page/r/CYMu-jkURnx7EAI/review';

// The only technicians with Zappy review cards. Slug -> display name.
const TECHS: Record<string, string> = {
  maurice: 'Maurice',
  nick: 'Nick',
  charles: 'Charles',
};

export function normalizeTech(raw: string | null | undefined): string {
  return (raw ?? '').trim().toLowerCase();
}

export function resolveTech(
  raw: string | null | undefined,
): { slug: string; name: string } | null {
  const slug = normalizeTech(raw);
  const name = TECHS[slug];
  return name ? { slug, name } : null;
}

export function buildReviewUrl(slug: string): string {
  const params = new URLSearchParams({
    utm_source: 'zappy_card',
    utm_medium: 'review_card',
    utm_campaign: 'google_reviews',
    utm_content: slug,
  });
  return `${GOOGLE_REVIEW_URL}?${params.toString()}`;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `deno test supabase/functions/review-redirect/lib.test.ts`
Expected: PASS (5 tests).

Note on `buildReviewUrl`: `URLSearchParams` renders the four params in insertion order with `&` separators and no encoding needed for these values, matching the asserted string exactly.

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/review-redirect/lib.ts supabase/functions/review-redirect/lib.test.ts
git commit -m "feat(reviews): review-redirect pure helpers + tests"
```

---

## Task 3: Branded interstitial template (`interstitial.ts`)

Port the approved mockup into a function-served template, minus the preview-only switcher/demo JS, with real auto-redirect + JS-disabled fallbacks. TDD the token replacement and HTML-escaping.

**Files:**
- Create: `supabase/functions/review-redirect/interstitial.ts`
- Test: add cases to `supabase/functions/review-redirect/lib.test.ts`

- [ ] **Step 1: Write the failing tests (append to lib.test.ts)**

```ts
import { renderInterstitial } from './interstitial.ts';

Deno.test('renderInterstitial injects tech name and redirect url', () => {
  const html = renderInterstitial({
    techName: 'Maurice',
    redirectUrl: 'https://g.page/r/CYMu-jkURnx7EAI/review?utm_content=maurice',
    delayMs: 1500,
  });
  // tech name shown in the headline
  if (!html.includes('Maurice')) throw new Error('tech name missing');
  // redirect url present for JS replace, meta refresh, and the manual button
  if (!html.includes('https://g.page/r/CYMu-jkURnx7EAI/review?utm_content=maurice')) {
    throw new Error('redirect url missing');
  }
  if (!html.includes('1500')) throw new Error('delay missing');
  // no preview-only switcher leaked in
  if (html.includes('data-preview')) throw new Error('preview switcher leaked');
  // meta refresh fallback present (delay in seconds, rounded up)
  if (!html.includes('http-equiv="refresh"')) throw new Error('meta refresh missing');
});

Deno.test('renderInterstitial escapes special chars in the url', () => {
  const html = renderInterstitial({
    techName: 'Nick',
    redirectUrl: 'https://x.test/?a=1&b="2"',
    delayMs: 1500,
  });
  // ampersand and quotes are entity-escaped inside attributes
  if (html.includes('a=1&b="2"')) throw new Error('url was not escaped');
  if (!html.includes('a=1&amp;b=&quot;2&quot;')) throw new Error('expected escaped url');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `deno test supabase/functions/review-redirect/lib.test.ts`
Expected: FAIL with "Module not found ./interstitial.ts".

- [ ] **Step 3: Write the implementation**

```ts
// supabase/functions/review-redirect/interstitial.ts
// Branded Twins interstitial served after the click is logged. Derived from the
// approved mockup docs/superpowers/specs/2026-06-23-zappy-review-pages/review-interstitial.html
// (preview switcher + demo JS removed; real auto-redirect + JS-disabled fallbacks added).

function escapeHtml(s: string): string {
  return s
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

export function renderInterstitial(opts: {
  techName: string;
  redirectUrl: string;
  delayMs: number;
}): string {
  const name = escapeHtml(opts.techName);
  const url = escapeHtml(opts.redirectUrl);
  const delayMs = Math.max(0, Math.floor(opts.delayMs));
  const refreshSeconds = Math.ceil(delayMs / 1000);

  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="robots" content="noindex" />
<meta http-equiv="refresh" content="${refreshSeconds};url=${url}" />
<title>Thanks from Twins Garage Doors</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
<style>
  :root{--navy-900:#081428;--navy-800:#0b1f3a;--yellow:#ffc400;--yellow-soft:#ffd54a;--ink:#eaf1ff;--ink-dim:#9fb2d0;--star:#ffc400;--star-off:rgba(255,255,255,.14);--delay:${delayMs}ms}
  *{box-sizing:border-box;margin:0;padding:0}html,body{height:100%}
  body{font-family:"Hanken Grotesk",system-ui,sans-serif;color:var(--ink);background:radial-gradient(1200px 600px at 50% -10%,rgba(255,196,0,.10),transparent 60%),radial-gradient(900px 500px at 90% 110%,rgba(18,42,77,.9),transparent 55%),linear-gradient(180deg,var(--navy-800),var(--navy-900));min-height:100svh;display:grid;place-items:center;padding:24px;overflow:hidden;position:relative}
  body::before{content:"";position:absolute;inset:0;background-image:repeating-linear-gradient(180deg,transparent 0,transparent 86px,rgba(255,255,255,.035) 86px,rgba(255,255,255,.035) 88px);-webkit-mask-image:radial-gradient(circle at 50% 40%,#000,transparent 75%);mask-image:radial-gradient(circle at 50% 40%,#000,transparent 75%);pointer-events:none}
  .card{position:relative;z-index:1;width:min(440px,100%);text-align:center;padding:44px 32px 36px;border-radius:24px;background:linear-gradient(180deg,rgba(18,42,77,.55),rgba(8,20,40,.35));border:1px solid rgba(255,255,255,.08);box-shadow:0 30px 80px -20px rgba(0,0,0,.65),inset 0 1px 0 rgba(255,255,255,.06);backdrop-filter:blur(8px);animation:rise .6s cubic-bezier(.2,.8,.2,1) both}
  @keyframes rise{from{opacity:0;transform:translateY(16px) scale(.98)}to{opacity:1;transform:none}}
  .brand{display:inline-flex;align-items:center;gap:9px;letter-spacing:.18em;font-size:12px;font-weight:700;text-transform:uppercase;color:var(--yellow);margin-bottom:22px}
  .brand .dot{width:7px;height:7px;border-radius:50%;background:var(--yellow);box-shadow:0 0 14px var(--yellow)}
  .stars{display:flex;justify-content:center;gap:8px;margin-bottom:22px}
  .stars svg{width:34px;height:34px;display:block;fill:var(--star);filter:drop-shadow(0 4px 10px rgba(255,196,0,.45));transform:scale(.6);opacity:0;animation:pop .45s cubic-bezier(.2,1.4,.4,1) forwards}
  .stars svg:nth-child(1){animation-delay:.25s}.stars svg:nth-child(2){animation-delay:.40s}.stars svg:nth-child(3){animation-delay:.55s}.stars svg:nth-child(4){animation-delay:.70s}.stars svg:nth-child(5){animation-delay:.85s}
  @keyframes pop{to{transform:scale(1);opacity:1}}
  h1{font-family:"Anton",sans-serif;font-weight:400;font-size:clamp(30px,8vw,40px);line-height:.98;letter-spacing:.01em;text-transform:uppercase;margin-bottom:14px}
  h1 .accent{color:var(--yellow)}
  p.lead{color:var(--ink-dim);font-size:16px;line-height:1.5;margin:0 auto 26px;max-width:32ch}
  .progress{height:5px;width:100%;border-radius:999px;background:rgba(255,255,255,.10);overflow:hidden;margin-bottom:26px}
  .progress>i{display:block;height:100%;width:0;border-radius:999px;background:linear-gradient(90deg,var(--yellow-soft),var(--yellow));box-shadow:0 0 12px rgba(255,196,0,.5);animation:fill var(--delay) linear forwards;animation-delay:.3s}
  @keyframes fill{to{width:100%}}
  .cta{display:inline-flex;align-items:center;gap:10px;text-decoration:none;font-weight:700;font-size:16px;color:var(--navy-900);background:linear-gradient(180deg,var(--yellow-soft),var(--yellow));padding:14px 26px;border-radius:999px;box-shadow:0 12px 30px -8px rgba(255,196,0,.6);transition:transform .15s ease,box-shadow .15s ease}
  .cta:hover{transform:translateY(-2px)}.cta svg{width:18px;height:18px}
  .foot{margin-top:22px;font-size:12.5px;color:var(--ink-dim);letter-spacing:.02em}.foot a{color:var(--ink);text-decoration:none}
  @media (prefers-reduced-motion:reduce){.card,.stars svg,.progress>i{animation:none!important}.stars svg{opacity:1;transform:none}}
</style>
</head>
<body>
  <main class="card" role="status" aria-live="polite">
    <div class="brand"><span class="dot"></span> Twins Garage Doors</div>
    <div class="stars" aria-hidden="true">
      <svg viewBox="0 0 24 24"><path d="M12 2l2.9 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21l1.18-6.88-5-4.87 7.1-1.01z"/></svg>
      <svg viewBox="0 0 24 24"><path d="M12 2l2.9 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21l1.18-6.88-5-4.87 7.1-1.01z"/></svg>
      <svg viewBox="0 0 24 24"><path d="M12 2l2.9 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21l1.18-6.88-5-4.87 7.1-1.01z"/></svg>
      <svg viewBox="0 0 24 24"><path d="M12 2l2.9 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21l1.18-6.88-5-4.87 7.1-1.01z"/></svg>
      <svg viewBox="0 0 24 24"><path d="M12 2l2.9 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77 5.82 21l1.18-6.88-5-4.87 7.1-1.01z"/></svg>
    </div>
    <h1>Thanks for choosing<br /><span class="accent">${name}</span></h1>
    <p class="lead">Taking you to Google so you can share how ${name} did. It only takes a moment.</p>
    <div class="progress" aria-hidden="true"><i></i></div>
    <a class="cta" href="${url}" rel="noopener">
      Leave your review
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
    <div class="foot">Not redirected? <a href="${url}">Tap here</a> &middot; (608) 888-8785</div>
  </main>
  <script>setTimeout(function(){location.replace("${url}");}, ${delayMs});</script>
</body>
</html>`;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `deno test supabase/functions/review-redirect/lib.test.ts`
Expected: PASS (all tests, including the two new interstitial tests).

- [ ] **Step 5: Commit**

```bash
git add supabase/functions/review-redirect/interstitial.ts supabase/functions/review-redirect/lib.test.ts
git commit -m "feat(reviews): branded interstitial template + tests"
```

---

## Task 4: HTTP handler (`index.ts`) + config

**Files:**
- Create: `supabase/functions/review-redirect/index.ts`
- Modify: `supabase/config.toml`

- [ ] **Step 1: Write the handler**

```ts
// supabase/functions/review-redirect/index.ts
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2.39.3';
import { buildReviewUrl, GOOGLE_REVIEW_URL, resolveTech } from './lib.ts';
import { renderInterstitial } from './interstitial.ts';

const REDIRECT_DELAY_MS = 1500;

const UTMS = {
  utm_source: 'zappy_card',
  utm_medium: 'review_card',
  utm_campaign: 'google_reviews',
} as const;

// Best-effort insert. Never throws into the request path.
async function logClick(args: {
  slug: string;
  userAgent: string | null;
  referrer: string | null;
}): Promise<void> {
  try {
    const supabase = createClient(
      Deno.env.get('SUPABASE_URL')!,
      Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!,
    );
    await supabase.from('review_card_clicks').insert({
      tech: args.slug,
      user_agent: args.userAgent,
      referrer: args.referrer,
      ...UTMS,
      utm_content: args.slug,
    });
  } catch (err) {
    console.error('review-redirect: failed to log click', err);
  }
}

Deno.serve(async (req) => {
  const url = new URL(req.url);
  const tech = resolveTech(url.searchParams.get('tech'));

  // Unknown / missing tech: send straight to the plain Google review page.
  if (!tech) {
    return new Response(null, {
      status: 302,
      headers: { Location: GOOGLE_REVIEW_URL, 'Cache-Control': 'no-store' },
    });
  }

  await logClick({
    slug: tech.slug,
    userAgent: req.headers.get('user-agent'),
    referrer: req.headers.get('referer'),
  });

  const html = renderInterstitial({
    techName: tech.name,
    redirectUrl: buildReviewUrl(tech.slug),
    delayMs: REDIRECT_DELAY_MS,
  });

  return new Response(html, {
    status: 200,
    headers: {
      'Content-Type': 'text/html; charset=utf-8',
      'Cache-Control': 'no-store',
    },
  });
});
```

- [ ] **Step 2: Add the public-function declaration to config.toml**

Append to `supabase/config.toml` (near the other `verify_jwt = false` functions):

```toml
# Public review-card redirect: logs the click server-side, then serves a branded
# interstitial that redirects to the Twins Google review page. Customers are not
# authenticated, so JWT verification is disabled.
[functions.review-redirect]
verify_jwt = false
```

- [ ] **Step 3: Type-check the function**

Run: `deno check supabase/functions/review-redirect/index.ts`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add supabase/functions/review-redirect/index.ts supabase/config.toml
git commit -m "feat(reviews): review-redirect edge function + public config"
```

---

## Task 5: Deploy + verify live

- [ ] **Step 1: Deploy the function**

Deploy `review-redirect` to project `jwrpjuqaynownxaoeayi` using the Supabase MCP `deploy_edge_function` (include `index.ts`, `lib.ts`, `interstitial.ts`). It must deploy with JWT verification OFF (the `config.toml` entry handles this for CLI; for the MCP deploy, confirm the function is reachable without an `Authorization` header in Step 2). If using CLI: `supabase functions deploy review-redirect --no-verify-jwt --project-ref jwrpjuqaynownxaoeayi`.

- [ ] **Step 2: Verify a valid tech returns the branded page (no auth header)**

Run:

```bash
curl -s "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/review-redirect?tech=maurice" | grep -o "Thanks for choosing.*Maurice\|utm_content=maurice" | head
```

Expected: output contains `utm_content=maurice` and the Maurice headline. (Guards against the known edge-runtime/control-plane desync where a function 404s at the gateway despite showing ACTIVE — see memory `feedback_supabase_edge_runtime_desync`.)

- [ ] **Step 3: Verify an unknown tech 302s to plain Google review URL**

Run:

```bash
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" "https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/review-redirect?tech=bogus"
```

Expected: `302 https://g.page/r/CYMu-jkURnx7EAI/review`.

- [ ] **Step 4: Verify the click was logged**

Run (Supabase MCP `execute_sql`):

```sql
select tech, utm_content, user_agent is not null as has_ua, clicked_at
from public.review_card_clicks
order by clicked_at desc
limit 5;
```

Expected: a recent row with `tech = 'maurice'`, `utm_content = 'maurice'`. (The `tech=bogus` hit should NOT have created a row.)

- [ ] **Step 5: Clean up test rows (optional)**

Run (Supabase MCP `execute_sql`), only if you want to remove the verification hits:

```sql
delete from public.review_card_clicks where clicked_at > now() - interval '10 minutes';
```

---

## Task 6: Browser smoke test

- [ ] **Step 1: Open each function URL in a browser**

Visit each of:
- `https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/review-redirect?tech=maurice`
- `.../review-redirect?tech=nick`
- `.../review-redirect?tech=charles`

Expected for each: the branded Twins interstitial shows the correct first name, the progress bar fills, and after ~1.5s the page auto-redirects to the Google review page. The "Leave your review" button also navigates there. Confirm `utm_content` matches the tech in the final URL.

- [ ] **Step 2: No commit** (verification only).

---

## Task 7: WordPress redirect rule (done in WordPress admin)

This is the one part not in this repo. Forward the three branded paths to the function. **Confirm first** whether the WordPress install can run the Redirection plugin or edit `.htaccess`.

- [ ] **Step 1 (preferred): Add one regex redirect via the Redirection plugin**

In WordPress admin → Tools → Redirection → Add new:
- "Source URL": `^/review/(maurice|nick|charles)/?$`  (enable the **Regex** toggle)
- "Target URL": `https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/review-redirect?tech=$1`
- HTTP code: **302**

- [ ] **Step 1 (fallback A): `.htaccess`** (Apache hosts), add above the WordPress block:

```apache
RewriteEngine On
RewriteRule ^review/(maurice|nick|charles)/?$ https://jwrpjuqaynownxaoeayi.supabase.co/functions/v1/review-redirect?tech=$1 [R=302,L]
```

- [ ] **Step 1 (fallback B): per-page redirect** — create pages `/review/maurice`, `/review/nick`, `/review/charles`, each set to a 302 redirect (via a page-redirect plugin) to the matching function URL with `?tech=<slug>`.

- [ ] **Step 2: Verify end to end**

Visit `https://twinsgaragedoors.com/review/maurice` (and nick, charles). Expected: the branded interstitial shows, then redirects to the Google review page, and a row appears in `review_card_clicks`.

---

## Out of scope (fast follow, separate plan)

**Component 4 — dashboard view.** A "Review cards" card on the Marketing ROI page in `twins-dash` (Vite app) showing clicks per tech over the selected range, reading `review_card_clicks` via the authenticated SELECT policy. Build once data is flowing; not part of this plan.

---

## Self-Review Notes

- **Spec coverage:** Component 1 (table) → Task 1; Component 2 (function) → Tasks 2–5; Component 2b (interstitial) → Task 3; Component 3 (WordPress) → Task 7; Component 4 (dashboard) → explicitly deferred. Verification section of spec → Tasks 5–7.
- **Unknown-tech decision:** redirect to plain Google URL, no row logged (matches spec default). Implemented in `index.ts` Step 1.
- **Reliability:** `logClick` swallows all errors and is awaited before render but cannot throw; `Cache-Control: no-store` on both responses; post-deploy gateway curl guards the edge-runtime desync risk.
- **Type consistency:** `resolveTech` returns `{slug, name}` used identically in `index.ts`; `buildReviewUrl(slug)` and `renderInterstitial({techName, redirectUrl, delayMs})` signatures match across tasks.
