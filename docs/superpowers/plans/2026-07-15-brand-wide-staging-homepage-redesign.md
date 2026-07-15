# Brand-wide staging homepage redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build and deploy the complete, functional, brand-wide Twins Garage Doors experience to the private SiteGround staging copy, with portable production adapters packaged and proven but never deployed or enabled on production in this scope.

**Architecture:** Preserve the existing fail-closed staging MU-plugin and legacy service/location/cost/builder renderers, then place all newly approved chrome, pages, assets, and interactions in a side-effect-free `website/twins-brand-experience/` core. The staging loader injects inert quote, booking, careers, route, asset, review, and CSP adapters only after its existing gates pass; a separately packaged and fixture-tested production loader injects the real provider contracts without depending on staging code. Real owned imagery and real reviews are copied into deterministic, hash-verified packages; deployment copies regular files to private staging only.

**Tech Stack:** WordPress/PHP 7.4-compatible procedural bridge plus namespaced PHP classes, HTML5, prefixed CSS, vanilla JavaScript, Node.js 20+, Node test runner, Playwright 1.61.1, Sharp 0.34.5, SiteGround private staging, existing Business Reviews Bundle collection 2178, existing Gravity Form 1, and exact Housecall Pro configuration.

## Global Constraints

- Lease/account identity for every authorized staging action is `CHATGPT_PROFILE_1`.
- Work only on `codex/staging-site-safety`; production WordPress, production settings, DNS, Google Business data, leads, mail, SMS, analytics, advertising, and booking records remain read-only or untouched.
- The only deployment target is private staging at `https://danielj140.sg-host.com/`; retain SiteGround Basic Authentication, default-deny host egress, disabled hosting cron, and the existing WordPress staging-safety MU-plugin.
- Do not send a real quote, employment application, email, SMS, CRM request, booking request, analytics event, ad event, or other production integration from staging or tests.
- Every primary estimate CTA on redesigned surfaces says exactly `Request a Quote`; `Get an Estimate` and `Request Exact Quote` are prohibited there.
- The root headline is exactly `Garage Door Repair & Installation, Done Right Today.` and the main root phone is exactly `(833) 833-2010`; no geolocation is requested.
- Desktop logo floors are 204 CSS pixels expanded at widths of 1201 and above and 180 CSS pixels compressed. Mobile/tablet floors are 190 at 1024, 176 at 768, 154 at 390, 148 at 360, and 140 at 320. The expanded logo crosses the gold divider by 12 pixels without clipping.
- Both Twin characters remain visible at every supported width; their distinct loops span 4.8–6.5 seconds, cover at least 12 CSS pixels vertically, and stop under `prefers-reduced-motion: reduce`.
- Use self-hosted Lilita One for display text and Nunito for body/interface text. Runtime Google Fonts requests are prohibited.
- Staging markets are `wi`, `ky`, and visibly labeled `il-preview`; production defaults to `wi` and `ky` and must refuse Illinois unless a separate explicit public-production flag enables it.
- The fixed review source is `https://twinsgaragedoors.com/wi/reviews/`, multisite path `/wi/`, page ID `2186`, and collection ID `2178`. Capture accepts no caller-selected URL and staging runtime performs no review shortcode, database, cache, Google, RichPlugins, or other network lookup.
- A valid staging review capture contains at least five exact-date records, is no more than 90 days old, and verifies the source response, provider version, per-record stable ID, and `recordSha256`; no sample or invented fallback reviews are permitted.
- Staging quote and careers previews use `<div role="form">`, inputs with labels but no submission names/form owner, and a final `type="button"`; booking is an in-page dialog with no live Housecall Pro anchor.
- Production Quote delegates only to same-origin `/contact-us/` and existing Gravity Form ID `1`. Production Booking uses only `TWINS_HCP_BOOK_URL` with exact value `https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true`. Production Careers uses same-origin `/wp-json/twins/v1/employment-applications` with protected server configuration; browser code never contains a GHL token.
- New shared CSS/JS/images deploy under `/wp-content/mu-plugins/twins-brand-experience/`. Existing `twins-staging-assets/` remains for Clopay and legacy compatibility; do not copy all 790 Clopay assets into the portable core.
- Use regular files only in packages and on staging; no symlinks, device nodes, caller-selected paths, remote hotlinks, embedded credentials, or generated production authority.
- Run red-first tests, make the minimum implementation pass, review each task, and commit each independently green unit. PHP suites that skip locally because PHP is absent must pass on the isolated SiteGround/CI PHP runtime before deployment completion.
- Browser acceptance widths are exactly 1440, 1201, 1024, 768, 390, 360, and 320 CSS pixels. The authenticated same-origin crawl is uncapped.
- Completion includes repository checker, all legacy and new tests, authenticated browser matrix, uncapped crawl, private-staging visual proof, deterministic manifests, rollback instructions, and `docs/handoff/HANDOFF-TEMPLATE.md`-compatible handoff. Production migration remains a separate authorization.

## Locked file and interface map

Create the portable package:

```text
website/twins-brand-experience/
├── bootstrap.php                         # pure require list; no hooks, globals, headers, or I/O
├── config/markets.php                    # fixed main/WI/KY/IL-preview data
├── src/Contracts.php                     # six injected adapter interfaces
├── src/Experience.php                    # pure renderer facade and scoped template accessors
├── src/MarketRegistry.php                # fixed market validation and IL production refusal
├── src/ReviewCodec.php                   # canonical review ID/hash/envelope verification
├── components/
│   ├── header.php                        # one shared accessible header/nav/drawer
│   ├── footer.php                        # one shared footer + mobile actions
│   ├── picture.php                       # fixed logical-key responsive pictures
│   ├── review-slider.php                 # normalized review cards only
├── templates/
│   ├── home.php                          # eleven approved homepage sections
│   ├── team.php                          # real crew + Tal story
│   ├── careers.php                       # approved rebuilt employment experience
│   ├── reviews.php                       # verified internal review index
│   └── contact.php                       # inert staging / production adapter slot
├── assets/
│   ├── css/twins-brand.css
│   ├── js/twins-brand.js
│   ├── fonts/{lilita-one-regular.woff2,nunito-variable.woff2}
│   ├── images/brand/{twins-logo.png,twin-left.png,twin-right.png,twins-service-truck-cutout.png,twins-service-truck-cutout.webp}
│   ├── images/team/{originals and deterministic WebP derivatives}
│   ├── images/door-builder/twins-before-after-install.webp
│   └── owned-assets.provenance.json
├── data/reviews/google-business-reviews-collection-2178.json
├── production/
│   ├── twins-brand-experience-loader.php # production-only root MU loader source
│   ├── twins-brand-experience.php
│   ├── ProductionWordPressBridge.php
│   ├── ProductionAdapters.php
│   ├── BusinessReviewsBundleProvider.php
│   ├── BusinessReviewsBundleParser.php
│   └── assets/twins-brand-production.js
├── manifests/{staging-runtime.json,production-runtime.json,host-verification.json}
├── tools/{build-owned-images.mjs,capture-google-reviews.mjs,build-packages.mjs,check-repository.mjs,crawl-staging.mjs,deploy-private-staging.mjs,private-staging-deploy.php}
├── tests/{contracts,php,browser,fixtures}/
├── package.json
├── package-lock.json
└── playwright.config.cjs
```

The six immutable interfaces in `Twins\BrandExperience` are:

```php
interface AssetResolver {
    public function url(string $assetKey): string;
}

interface RouteAdapter {
    public function normalizeContext(array $requestContext): array;
    public function route(string $routeKey, string $marketKey): string;
}

interface ReviewsProvider {
    public function collection(): array;
}

interface QuoteAdapter {
    public function action(array $context): array;
    public function renderExperience(array $context): string;
    public function assertReady(): void;
}

interface BookingAdapter {
    public function action(array $context): array;
    public function assertReady(): void;
}

interface ApplicationAdapter {
    public function clientContract(array $context): array;
    public function renderExperience(array $context): string;
    public function assertReady(): void;
}
```

`Twins\BrandExperience\Experience` exposes exactly:

```php
public function renderHeader(array $context): string;
public function renderFooter(array $context): string;
public function renderHome(array $context): string;
public function renderTeam(array $context): string;
public function renderCareers(array $context): string;
public function renderContact(array $context): string;
public function renderReviews(array $context): string;
public function assetHandles(): array;
public function asset(string $key): string;
public function route(string $key, string $market): string;
public function reviewCollection(): array;
public function quoteAdapter(): QuoteAdapter;
public function bookingAdapter(): BookingAdapter;
public function applicationAdapter(): ApplicationAdapter;
public function markets(): MarketRegistry;
```

The staging bridge creates `twins_overhaul_brand_runtime(): Twins\BrandExperience\Experience` and keeps the existing `twins_overhaul_render_header()`, `twins_overhaul_render_footer()`, and `twins_overhaul_render_home_template()` functions as compatibility facades. Every redesigned body retains `id="twins-overhaul-main"` and adds only `twins-brand-*` classes.

---

### Task 1: Establish reproducible Node tooling and protect the existing green baseline

**Files:**
- Modify: `.gitignore`
- Create: `website/twins-brand-experience/package.json`
- Create: `website/twins-brand-experience/package-lock.json`
- Create: `website/twins-brand-experience/tests/contracts/tooling.test.cjs`
- Modify: `website/staging-safety/tests/recovered-live-overhaul.test.cjs`
- Test: `website/staging-safety/tests/*.test.cjs`

**Interfaces:**
- Consumes: Node.js 20 or newer and the existing staging-safety Node suite.
- Produces: pinned Playwright/Sharp tooling, `npm run test:contracts`, and an intentional preserved-byte allowlist for untouched staging safety/legacy files.

- [ ] **Step 1: Synchronize safely and record the baseline before editing**

Fetch `origin`, then verify current `origin/main` is already an ancestor of the published task branch:

```bash
git fetch origin
git merge-base --is-ancestor origin/main HEAD
```

Expected: exit 0. If it exits 1, merge `origin/main` with a normal merge commit, resolve only genuine overlaps, rerun the approved spec/plan review, and never rebase or force-rewrite the published task branch.

Run:

```bash
node --test website/staging-safety/tests/*.test.cjs
git status --short
```

Expected: 48 tests total, 36 pass, 12 PHP-dependent skips, zero failures; only the approved spec/plan documentation is modified.

- [ ] **Step 2: Write the failing tooling contract**

Create `tests/contracts/tooling.test.cjs` with exact dependency and script assertions:

```js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');

test('brand tooling is pinned and side-effecting commands are explicit', () => {
  const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
  assert.equal(pkg.private, true);
  assert.equal(pkg.engines.node, '>=20');
  assert.equal(pkg.devDependencies['@playwright/test'], '1.61.1');
  assert.equal(pkg.devDependencies.sharp, '0.34.5');
  assert.equal(pkg.scripts['test:contracts'], 'node --test tests/contracts/*.test.cjs');
  assert.equal(pkg.scripts['check:assets'], 'node tools/build-owned-images.mjs --check');
  assert.equal(pkg.scripts['check:repo'], 'node tools/check-repository.mjs');
  assert.equal(pkg.scripts['install:browser'], 'playwright install chromium');
  assert.equal(pkg.scripts['test:browser'], 'playwright test');
  assert.equal(pkg.scripts['test:crawl'], 'node tools/crawl-staging.mjs');
});
```

- [ ] **Step 3: Run the contract to verify RED**

Run:

```bash
node --test website/twins-brand-experience/tests/contracts/tooling.test.cjs
```

Expected: FAIL with `ENOENT` for `website/twins-brand-experience/package.json`.

- [ ] **Step 4: Add the exact package manifest**

Create `website/twins-brand-experience/package.json`:

```json
{
  "name": "@twins/brand-experience",
  "version": "1.0.0",
  "private": true,
  "engines": { "node": ">=20" },
  "scripts": {
    "test:contracts": "node --test tests/contracts/*.test.cjs",
    "test:php": "node --test tests/php-harnesses.test.cjs",
    "check:assets": "node tools/build-owned-images.mjs --check",
    "build:packages": "node tools/build-packages.mjs",
    "check:packages": "node tools/build-packages.mjs --check",
    "check:repo": "node tools/check-repository.mjs",
    "install:browser": "playwright install chromium",
    "test:browser": "playwright test",
    "test:crawl": "node tools/crawl-staging.mjs",
    "test:all": "npm run test:contracts && npm run test:php && npm run check:assets && npm run build:packages && npm run check:packages && npm run check:repo && npm run test:browser && npm run test:crawl"
  },
  "devDependencies": {
    "@playwright/test": "1.61.1",
    "sharp": "0.34.5"
  }
}
```

Install from the package directory so npm creates a pinned lockfile:

```bash
cd website/twins-brand-experience
npm install --ignore-scripts
npm run install:browser
node -e 'const { chromium } = require("@playwright/test"); const fs = require("node:fs"); if (!fs.existsSync(chromium.executablePath())) process.exit(1)'
```

Expected: `package-lock.json` records only the declared development toolchain, the Playwright 1.61.1-managed Chromium executable exists before any browser RED test, and `npm audit` reports its result without modifying any other workspace package. CI runs the same explicit browser-provisioning command; no test relies on a globally installed browser.

Append these exact workspace-local build exclusions to `.gitignore`:

```gitignore
website/twins-brand-experience/node_modules/
website/twins-brand-experience/dist/
website/twins-brand-experience/test-results/
website/twins-brand-experience/playwright-report/
```

- [ ] **Step 5: Split preserved live-byte evidence from files intentionally replaced by this plan**

In `recovered-live-overhaul.test.cjs`, retain exact hashes for the root staging gates, safety plugin, builder, cost/catalog, legacy templates, and legacy asset catalog. Remove only these intentionally changed files from the byte-identity table:

```js
const intentionallyReplacedByPortableCore = new Set([
  'website/staging-safety/mu-plugins/twins-staging-overhaul/bootstrap.php',
  'website/staging-safety/mu-plugins/twins-staging-overhaul/components.php',
  'website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php',
  'website/staging-safety/mu-plugins/twins-staging-overhaul/routes.php',
  'website/staging-safety/mu-plugins/twins-staging-overhaul/templates/home.php',
]);

for (const [relativePath, expectedSha256] of Object.entries(liveHashes)) {
  if (intentionallyReplacedByPortableCore.has(relativePath)) continue;
  assert.equal(sha256(relativePath), expectedSha256, relativePath);
}
```

Add an assertion that the replacement set is exact, so later edits cannot silently escape both evidence systems:

```js
assert.deepEqual(
  [...intentionallyReplacedByPortableCore].sort(),
  [
    'website/staging-safety/mu-plugins/twins-staging-overhaul/bootstrap.php',
    'website/staging-safety/mu-plugins/twins-staging-overhaul/components.php',
    'website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php',
    'website/staging-safety/mu-plugins/twins-staging-overhaul/routes.php',
    'website/staging-safety/mu-plugins/twins-staging-overhaul/templates/home.php',
  ]
);
```

- [ ] **Step 6: Run GREEN tooling and legacy regression tests**

Run:

```bash
node --test website/twins-brand-experience/tests/contracts/tooling.test.cjs
node --test website/staging-safety/tests/*.test.cjs
```

Expected: tooling contract PASS; legacy suite remains zero-failure with PHP-only skips locally.

- [ ] **Step 7: Commit the independently green tooling baseline**

```bash
git add .gitignore website/twins-brand-experience/package.json website/twins-brand-experience/package-lock.json website/twins-brand-experience/tests/contracts/tooling.test.cjs website/staging-safety/tests/recovered-live-overhaul.test.cjs
git commit -m "test(web): establish portable brand tooling baseline"
```

### Task 2: Implement the pure portable contracts, market registry, and renderer facade

**Files:**
- Create: `website/twins-brand-experience/bootstrap.php`
- Create: `website/twins-brand-experience/config/markets.php`
- Create: `website/twins-brand-experience/src/Contracts.php`
- Create: `website/twins-brand-experience/src/MarketRegistry.php`
- Create: `website/twins-brand-experience/src/Experience.php`
- Create: `website/twins-brand-experience/components/header.php`
- Create: `website/twins-brand-experience/components/footer.php`
- Create: `website/twins-brand-experience/templates/{home,team,careers,contact,reviews}.php`
- Create: `website/twins-brand-experience/tests/contracts/portable-core.test.cjs`
- Create: `website/twins-brand-experience/tests/php/portable-core-harness.php`
- Create: `website/twins-brand-experience/tests/php-harnesses.test.cjs`

**Interfaces:**
- Consumes: the six locked adapter interfaces and fixed market names from the approved design.
- Produces: `MarketRegistry::resolve(string $key, string $environment): array`, `MarketRegistry::all(string $environment): array`, and the fifteen locked `Experience` methods, with no WordPress/staging dependency at bootstrap.

- [ ] **Step 1: Write static and executable RED contracts**

Create `portable-core.test.cjs`:

```js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');

test('portable bootstrap contains pure requires and no environment authority', () => {
  const source = fs.readFileSync(path.join(root, 'bootstrap.php'), 'utf8');
  assert.match(source, /require_once/);
  assert.doesNotMatch(source, /add_action|add_filter|header\s*\(|wp_enqueue|TWINS_STAGING|DISABLE_WP_CRON|danielj140|twinsgaragedoors\.com/i);
});

test('market data fixes staging and production visibility', () => {
  const source = fs.readFileSync(path.join(root, 'config/markets.php'), 'utf8');
  assert.match(source, /'wi'/);
  assert.match(source, /'ky'/);
  assert.match(source, /'il-preview'/);
  assert.match(source, /'productionEnabled'\s*=>\s*false/);
  assert.match(source, /\(833\) 833-2010/);
});
```

Create `tests/php/portable-core-harness.php` with fake adapters and no WordPress functions:

```php
<?php
declare(strict_types=1);

$beforeFunctions = get_defined_functions()['user'];
require $argv[1];
$afterFunctions = get_defined_functions()['user'];

if (function_exists('add_action') || function_exists('add_filter')) {
    fwrite(STDERR, "portable bootstrap created WordPress hooks\n");
    exit(1);
}

$registry = new Twins\BrandExperience\MarketRegistry(require dirname($argv[1]) . '/config/markets.php');
$staging = array_keys($registry->all('staging'));
$production = array_keys($registry->all('production'));

if ($staging !== ['main', 'wi', 'ky', 'il-preview']) {
    fwrite(STDERR, 'unexpected staging markets: ' . json_encode($staging) . "\n");
    exit(1);
}
if ($production !== ['main', 'wi', 'ky']) {
    fwrite(STDERR, 'production exposed Illinois: ' . json_encode($production) . "\n");
    exit(1);
}

try {
    $registry->resolve('il-preview', 'production');
    fwrite(STDERR, "production Illinois did not fail closed\n");
    exit(1);
} catch (DomainException $expected) {
}

$publicMethods = array_values(array_map(
    static fn(ReflectionMethod $method): string => $method->getName(),
    array_filter(
        (new ReflectionClass(Twins\BrandExperience\Experience::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        static fn(ReflectionMethod $method): bool => !$method->isConstructor()
    )
));
sort($publicMethods);
$expectedMethods = ['applicationAdapter', 'asset', 'assetHandles', 'bookingAdapter', 'markets', 'quoteAdapter', 'renderCareers', 'renderContact', 'renderFooter', 'renderHeader', 'renderHome', 'renderReviews', 'renderTeam', 'reviewCollection', 'route'];
sort($expectedMethods);
if ($publicMethods !== $expectedMethods) {
    fwrite(STDERR, 'portable Experience surface drift: ' . json_encode($publicMethods) . "\n");
    exit(1);
}

echo "portable-core-ok\n";
```

Create `php-harnesses.test.cjs` so PHP is required in CI and allowed to skip only on this PHP-less workstation:

```js
const assert = require('node:assert/strict');
const cp = require('node:child_process');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const php = cp.spawnSync('php', ['-v'], { encoding: 'utf8' });
const hasPhp = php.status === 0;
if (process.env.CI && !hasPhp) throw new Error('PHP is mandatory in CI');

function phpTest(name, harness, args = []) {
  test(name, { skip: !hasPhp && 'PHP CLI unavailable locally' }, () => {
    const result = cp.spawnSync('php', [path.join(root, 'tests/php', harness), ...args], { encoding: 'utf8' });
    assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
  });
}

phpTest('portable core boots without WordPress', 'portable-core-harness.php', [path.join(root, 'bootstrap.php')]);
```

- [ ] **Step 2: Run RED contracts**

Run:

```bash
cd website/twins-brand-experience
npm run test:contracts
npm run test:php
```

Expected: static tests FAIL because the PHP core files do not exist; the PHP test skips locally and must fail on a PHP runtime for the same missing file.

- [ ] **Step 3: Define the exact interfaces and fixed market data**

Implement `src/Contracts.php` with the six signatures in the locked file map. Implement `config/markets.php` as data only:

```php
<?php
declare(strict_types=1);

return [
    'main' => [
        'label' => 'Twins Garage Doors',
        'phoneDisplay' => '(833) 833-2010',
        'phoneHref' => 'tel:+18338332010',
        'routePrefix' => '/',
        'stagingEnabled' => true,
        'productionEnabled' => true,
        'preview' => false,
    ],
    'wi' => [
        'label' => 'Wisconsin',
        'phoneDisplay' => '(608) 420-2377',
        'phoneHref' => 'tel:+16084202377',
        'routePrefix' => '/wi/',
        'stagingEnabled' => true,
        'productionEnabled' => true,
        'preview' => false,
    ],
    'ky' => [
        'label' => 'Kentucky',
        'phoneDisplay' => '(833) 833-2010',
        'phoneHref' => 'tel:+18338332010',
        'routePrefix' => '/ky/',
        'stagingEnabled' => true,
        'productionEnabled' => true,
        'preview' => false,
    ],
    'il-preview' => [
        'label' => 'Illinois preview',
        'phoneDisplay' => '(815) 800-2025',
        'phoneHref' => 'tel:+18158002025',
        'routePrefix' => '/il/',
        'stagingEnabled' => true,
        'productionEnabled' => false,
        'preview' => true,
    ],
];
```

The regional literals above are copied from the existing fixed staging registry in `twins-staging-overhaul/data.php`; add a contract that byte-compares them to that registry so this extraction cannot silently alter regional phone behavior. Do not infer or geolocate.

- [ ] **Step 4: Implement fail-closed market resolution**

Create `src/MarketRegistry.php`:

```php
<?php
declare(strict_types=1);

namespace Twins\BrandExperience;

final class MarketRegistry
{
    private array $markets;

    public function __construct(array $markets)
    {
        if (!isset($markets['main'], $markets['wi'], $markets['ky'], $markets['il-preview'])) {
            throw new \InvalidArgumentException('The fixed market registry is incomplete.');
        }
        $this->markets = $markets;
    }

    public function all(string $environment): array
    {
        $flag = $environment === 'staging' ? 'stagingEnabled' : ($environment === 'production' ? 'productionEnabled' : '');
        if ($flag === '') throw new \DomainException('Unknown environment.');
        return array_filter($this->markets, static fn(array $market): bool => $market[$flag] === true);
    }

    public function resolve(string $key, string $environment): array
    {
        $enabled = $this->all($environment);
        if (!isset($enabled[$key])) throw new \DomainException('Market is unavailable in this environment.');
        return $enabled[$key] + ['key' => $key];
    }
}
```

- [ ] **Step 5: Implement the injected facade without side effects**

Create `src/Experience.php` with constructor injection and template isolation:

```php
<?php
declare(strict_types=1);

namespace Twins\BrandExperience;

final class Experience
{
    private AssetResolver $assets;
    private RouteAdapter $routes;
    private ReviewsProvider $reviews;
    private QuoteAdapter $quote;
    private BookingAdapter $booking;
    private ApplicationAdapter $applications;
    private MarketRegistry $markets;
    private string $root;

    public function __construct(
        AssetResolver $assets,
        RouteAdapter $routes,
        ReviewsProvider $reviews,
        QuoteAdapter $quote,
        BookingAdapter $booking,
        ApplicationAdapter $applications,
        MarketRegistry $markets,
        string $root
    ) {
        $this->assets = $assets;
        $this->routes = $routes;
        $this->reviews = $reviews;
        $this->quote = $quote;
        $this->booking = $booking;
        $this->applications = $applications;
        $this->markets = $markets;
        $this->root = rtrim($root, '/');
    }

    private function render(string $template, array $context): string
    {
        $context = $this->routes->normalizeContext($context);
        if (!isset($context['environment'], $context['market']) || !is_string($context['environment']) || !is_string($context['market'])) {
            throw new \DomainException('Normalized render context is incomplete.');
        }
        if (!in_array($context['environment'], ['staging', 'production'], true)) {
            throw new \DomainException('Normalized render environment is invalid.');
        }
        $marketKey = $context['market'];
        $environment = $context['environment'];
        $market = $this->markets->resolve($marketKey, $environment);
        $quote = $this->quote->action($context);
        $booking = $template === '../components/header' ? $this->booking->action($context) : null;
        $experience = $this;
        $bufferLevel = ob_get_level();
        ob_start();
        try {
            require $this->root . '/templates/' . $template . '.php';
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            while (ob_get_level() > $bufferLevel) ob_end_clean();
            throw $error;
        }
    }

    public function renderHeader(array $context): string { return $this->render('../components/header', $context); }
    public function renderFooter(array $context): string { return $this->render('../components/footer', $context); }
    public function renderHome(array $context): string { return $this->render('home', $context); }
    public function renderTeam(array $context): string { return $this->render('team', $context); }
    public function renderCareers(array $context): string { return $this->render('careers', $context); }
    public function renderContact(array $context): string { return $this->render('contact', $context); }
    public function renderReviews(array $context): string { return $this->render('reviews', $context); }

    public function assetHandles(): array
    {
        return ['style' => 'twins-brand-experience', 'script' => 'twins-brand-experience'];
    }

    public function asset(string $key): string { return $this->assets->url($key); }
    public function route(string $key, string $market): string { return $this->routes->route($key, $market); }
    public function reviewCollection(): array { return $this->reviews->collection(); }
    public function quoteAdapter(): QuoteAdapter { return $this->quote; }
    public function bookingAdapter(): BookingAdapter { return $this->booking; }
    public function applicationAdapter(): ApplicationAdapter { return $this->applications; }
    public function markets(): MarketRegistry { return $this->markets; }
}
```

Create pure `bootstrap.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Contracts.php';
require_once __DIR__ . '/src/MarketRegistry.php';
require_once __DIR__ . '/src/Experience.php';
```

Create temporary regular template files that return empty semantic roots only so the facade can boot; each is replaced under its own TDD task:

```php
<?php declare(strict_types=1); ?>
<main id="twins-overhaul-main" class="twins-brand-page"></main>
```

Header/footer temporary components use `<header class="twins-brand-header"></header>` and `<footer class="twins-brand-footer"></footer>` respectively.

The portable PHP harness must add `missing-environment`, `missing-market`, `unknown-environment`, and `unknown-market` fake-adapter scenarios for both staging and production normalization. Every case throws before a template is included. Install an error handler that turns every PHP notice/warning into `ErrorException`; render every public surface so an undefined template variable fails. Add a throwing-template/adapter scenario, assert `ob_get_level()` is unchanged after the exception, then render a clean fixture and assert no leaked bytes. `Experience::render()` supplies the validated `$marketKey`, `$environment`, `$market`, `$quote`, and header-only `$booking` scope; templates/components never default context values, and no `?? 'staging'` or `?? 'main'` fallback is permitted.

- [ ] **Step 6: Run GREEN core tests on both available runtimes**

Run locally:

```bash
cd website/twins-brand-experience
npm run test:contracts
npm run test:php
```

Expected: Node PASS; PHP harness reports a documented local SKIP only if PHP remains unavailable. Run the exact PHP harness on SiteGround/CI:

```bash
php website/twins-brand-experience/tests/php/portable-core-harness.php website/twins-brand-experience/bootstrap.php
```

Expected: `portable-core-ok` and exit 0.

- [ ] **Step 7: Commit the portable boundary**

```bash
git add website/twins-brand-experience/bootstrap.php website/twins-brand-experience/config website/twins-brand-experience/src website/twins-brand-experience/components website/twins-brand-experience/templates website/twins-brand-experience/tests/contracts/portable-core.test.cjs website/twins-brand-experience/tests/php website/twins-brand-experience/tests/php-harnesses.test.cjs
git commit -m "feat(web): add portable brand experience core"
```

### Task 3: Import owned assets and generate deterministic responsive derivatives

**Files:**
- Create: `website/twins-brand-experience/tools/build-owned-images.mjs`
- Create: `website/twins-brand-experience/assets/owned-assets.provenance.json`
- Create: `website/twins-brand-experience/assets/images/brand/*`
- Create: `website/twins-brand-experience/assets/images/team/*`
- Create: `website/twins-brand-experience/assets/images/door-builder/*`
- Create: `website/twins-brand-experience/assets/fonts/*`
- Create: `website/twins-brand-experience/tests/contracts/assets.test.cjs`
- Create: `docs/website-overhaul/reference-sources/motion-luxe/*`
- Create: `docs/website-overhaul/reference-sources/careers/*`
- Create: `docs/website-overhaul/reference-sources/door-builder/twins-before-after-install-source.png`

**Interfaces:**
- Consumes: three exact owned-photo inputs and five exact-byte design references with approved SHA-256 values.
- Produces: fixed logical asset keys, originals plus WebP derivatives, font files, and `owned-assets.provenance.json`, all verified by `build-owned-images.mjs --check`.

- [ ] **Step 1: Write RED provenance and derivative tests**

Create `tests/contracts/assets.test.cjs`:

```js
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const digest = file => crypto.createHash('sha256').update(fs.readFileSync(path.join(root, file))).digest('hex');

const originals = {
  'assets/images/team/twins-crew-fleet.jpeg': '7b961919cbd0fdff119864d29eb66b094aa1ca110fe71d581981ef4a1a780e23',
  'assets/images/team/tal-joseph.jpeg': '1e6f9052110a49e075ed2270c3b14253c1f9f5f8e8d16f9b7068c19d8356c87f',
  'assets/images/team/twins-technician-at-work.png': 'a6de5842b51fa41449c02013c773c1910829a674b96f70586d12feadd1509b54',
};

test('owned originals remain exact and every derivative is manifested', () => {
  for (const [file, sha] of Object.entries(originals)) assert.equal(digest(file), sha, file);
  const manifest = JSON.parse(fs.readFileSync(path.join(root, 'assets/owned-assets.provenance.json'), 'utf8'));
  assert.equal(manifest.schemaVersion, 1);
  assert.deepEqual(manifest.assets.map(a => a.logicalName), ['crew-fleet', 'tal-portrait', 'technician-at-work']);
  assert.deepEqual(manifest.brandAssets.map(a => a.logicalName), ['logo', 'twin-left', 'twin-right', 'truck-original', 'truck-webp']);
  assert.equal(manifest.doorBuilderAssets.length, 1);
  assert.equal(manifest.doorBuilderAssets[0].logicalName, 'door-builder-before-after');
  assert.equal(digest(manifest.doorBuilderAssets[0].source.path), '86e5c945b84c38fe5d1fe176024d443669edcdf3c77001f3e99a0a464c22138a');
  assert.equal(manifest.doorBuilderAssets[0].derivative.sha256, 'e9a0b6c0d5c1a25b711103a132647ab50cbb4c9b3b120c97124f000537d6e346');
  assert.equal(digest(manifest.doorBuilderAssets[0].derivative.path), manifest.doorBuilderAssets[0].derivative.sha256);
  assert.deepEqual([manifest.doorBuilderAssets[0].derivative.width, manifest.doorBuilderAssets[0].derivative.height], [1080, 930]);
  for (const asset of manifest.brandAssets) {
    assert.equal(digest(asset.path), asset.sha256);
    assert.ok(asset.sourceLocator.startsWith('website/staging-safety/mu-plugins/twins-staging-assets/'));
    assert.ok(asset.width > 0 && asset.height > 0);
  }
  for (const asset of manifest.assets) {
    assert.ok(asset.approvedAlt.length >= 12);
    assert.ok(asset.derivatives.length >= 3);
    for (const derivative of asset.derivatives) {
      assert.equal(digest(derivative.path), derivative.sha256);
      assert.equal(derivative.mime, 'image/webp');
      assert.ok(derivative.width > 0 && derivative.height > 0);
    }
  }
});

test('executable assets contain no inert reference HTML', () => {
  const deployed = fs.readdirSync(path.join(root, 'assets'), { recursive: true }).map(String);
  assert.equal(deployed.some(name => /careers-widget|employment-page-prototype|motion-luxe-design/.test(name)), false);
});

test('asset check regenerates bytes instead of trusting a rewritten manifest', () => {
  const source = fs.readFileSync(path.join(root, 'tools/build-owned-images.mjs'), 'utf8');
  assert.match(source, /Buffer\.compare\(generatedBytes, committedBytes\)/);
  assert.match(source, /Derivative byte drift/);
});
```

- [ ] **Step 2: Run RED asset tests**

Run:

```bash
cd website/twins-brand-experience
node --test tests/contracts/assets.test.cjs
```

Expected: FAIL because the exact packaged originals and manifest are absent.

- [ ] **Step 3: Copy exact approved originals and inert references**

Copy, without transformation, these three files to the specified destinations and immediately verify hashes:

```bash
cp /Users/daniel/twins-dashboard/videos/twins-garage-doors-reel/capture/assets/best-garage-door-repair-installation-nea.jpeg website/twins-brand-experience/assets/images/team/twins-crew-fleet.jpeg
cp "/Users/daniel/twins-dashboard/twins-content-engine/assets/instagram/library/Team Photos/tal profile pic.jpeg" website/twins-brand-experience/assets/images/team/tal-joseph.jpeg
cp /Users/daniel/Documents/Codex/2026-07-09/twins-garage-doors-brochure-redesign/outputs/assets/technician.png website/twins-brand-experience/assets/images/team/twins-technician-at-work.png
shasum -a 256 website/twins-brand-experience/assets/images/team/{twins-crew-fleet.jpeg,tal-joseph.jpeg,twins-technician-at-work.png}
```

Expected hashes, in order: `7b9619…780e23`, `1e6f90…56c87f`, `a6de58…9b54`. Any mismatch stops the task.

Copy the current exact logo, Twin cutouts, and truck original/WebP from `website/staging-safety/mu-plugins/twins-staging-assets/` into `assets/images/brand/`, then record their calculated hashes in the manifest rather than editing image bytes.

Copy the tracked real Twins before/after installation creative from `/Users/daniel/twins-dashboard/docs/marketing/creative/2026-07-04-meta-challenger/financing_install.png` to docs-only `docs/website-overhaul/reference-sources/door-builder/twins-before-after-install-source.png` without transformation. It is Git blob `e7d3f2c561996108488841966d715a5df4c21bb4` from commit `e27c4ae2fb37de2b6da594795b1866689c5e1746`; adjacent tracked README lines 58–63 identify its base as a real Twins CompanyCam installation photo. Require exact PNG, `1080x1080`, SHA-256 `86e5c945b84c38fe5d1fe176024d443669edcdf3c77001f3e99a0a464c22138a`. The source remains provenance-only because its bottom 150 pixels contain campaign-specific Madison/financing copy; both runtime manifests explicitly exclude it.

Copy the Motion Luxe spec/screenshots and Careers reference files to the docs-only paths. Rename Careers HTML copies to `.html.txt`; add a README containing:

```markdown
# Inert design references

These are exact-byte visual/content references only. They contain real forms, named fields, remote URLs, submission controls, and browser transport code. They must never be included, enqueued, served, executed, or copied into a staging or production runtime package.
```

Verify the five approved reference hashes: `42b7cfe3…6502`, `02198a17…1c60`, `54ad14a4…d87`, `dfcf45eb…d0c`, and `a2beb2e4…1e9`.

- [ ] **Step 4: Implement deterministic image generation and verification**

Create `tools/build-owned-images.mjs` with fixed inputs and settings only:

```js
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const check = process.argv.length === 3 && process.argv[2] === '--check';
if (process.argv.length > (check ? 3 : 2)) throw new Error('No caller-selected asset paths are accepted.');

const specs = [
  { logicalName: 'crew-fleet', original: 'assets/images/team/twins-crew-fleet.jpeg', sourceLocator: 'videos/twins-garage-doors-reel/capture/assets/best-garage-door-repair-installation-nea.jpeg', sourceEvidence: 'videos/twins-garage-doors-reel/capture/extracted/tokens.json:701-705', widths: [768, 1280, 1920], alt: 'The Twins Garage Doors crew with their branded service fleet' },
  { logicalName: 'tal-portrait', original: 'assets/images/team/tal-joseph.jpeg', sourceLocator: 'twins-content-engine/assets/instagram/library/Team Photos/tal profile pic.jpeg', sourceEvidence: 'owner-approved Twins team-photo library', widths: [480, 768, 1066], alt: 'Twins Garage Doors co-founder Tal Joseph' },
  { logicalName: 'technician-at-work', original: 'assets/images/team/twins-technician-at-work.png', sourceLocator: '/Users/daniel/Documents/Codex/2026-07-09/twins-garage-doors-brochure-redesign/outputs/assets/technician.png', sourceEvidence: 'owner-approved Twins brochure asset', widths: [480, 768, 924], alt: 'A Twins Garage Doors technician working on a garage door' },
];

const brandSpecs = [
  { logicalName: 'logo', path: 'assets/images/brand/twins-logo.png', sha256: 'cc63412115076e387953b81e9d936a3d40559afa2edc314b912a66b79d0bc0f0', mime: 'image/png', width: 711, height: 325 },
  { logicalName: 'twin-left', path: 'assets/images/brand/twin-left.png', sha256: '267ce3a33a3bbee09f9517409523c09246ac0488182625baeb9e4cdac84b293a', mime: 'image/png', width: 196, height: 534 },
  { logicalName: 'twin-right', path: 'assets/images/brand/twin-right.png', sha256: '29daf3e0c87133635c59a22e4560fb56ac819762a7ac8e84ebfe253bfccf75fe', mime: 'image/png', width: 297, height: 538 },
  { logicalName: 'truck-original', path: 'assets/images/brand/twins-service-truck-cutout.png', sha256: 'ecd200b41f69334cf97c73bc9d85a3b59288b8174f2e9aae5c30fd27d9940bf3', mime: 'image/png', width: 1398, height: 821 },
  { logicalName: 'truck-webp', path: 'assets/images/brand/twins-service-truck-cutout.webp', sha256: 'df91d2f10c7facc90fb336f8dd229d28e80d66c6ce9d79f6d0efdc32d7127e6e', mime: 'image/webp', width: 1398, height: 821 },
].map(asset => ({ ...asset, sourceLocator: `website/staging-safety/mu-plugins/twins-staging-assets/${path.basename(asset.path)}` }));

const doorBuilderSpec = {
  logicalName: 'door-builder-before-after',
  source: '../../docs/website-overhaul/reference-sources/door-builder/twins-before-after-install-source.png',
  derivative: 'assets/images/door-builder/twins-before-after-install.webp',
  sourceSha256: '86e5c945b84c38fe5d1fe176024d443669edcdf3c77001f3e99a0a464c22138a',
  derivativeSha256: 'e9a0b6c0d5c1a25b711103a132647ab50cbb4c9b3b120c97124f000537d6e346',
  sourceLocator: 'docs/marketing/creative/2026-07-04-meta-challenger/financing_install.png',
  sourceEvidence: 'docs/marketing/creative/2026-07-04-meta-challenger/README.md:58-63; commit e27c4ae2fb37de2b6da594795b1866689c5e1746',
  crop: { left: 0, top: 0, width: 1080, height: 930 },
  approvedAlt: 'Before and after view of a real Twins garage door installation',
};

const sha256 = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const outputName = (original, width) => original.replace(/\.(jpeg|png)$/i, `-${width}w.webp`);

const assets = [];
for (const spec of specs) {
  const originalBytes = await fs.readFile(path.join(root, spec.original));
  const originalMeta = await sharp(originalBytes).metadata();
  const derivatives = [];
  for (const width of spec.widths) {
    const relative = outputName(spec.original, width);
    const absolute = path.join(root, relative);
    const generatedBytes = await sharp(originalBytes).rotate().resize({ width, withoutEnlargement: true })
      .webp({ quality: 82, effort: 6, smartSubsample: true }).toBuffer();
    let bytes = generatedBytes;
    if (check) {
      const committedBytes = await fs.readFile(absolute);
      if (Buffer.compare(generatedBytes, committedBytes) !== 0) throw new Error(`Derivative byte drift: ${relative}`);
      bytes = committedBytes;
    } else {
      await fs.writeFile(absolute, generatedBytes, { flag: 'w', mode: 0o644 });
    }
    const meta = await sharp(bytes).metadata();
    if (meta.format !== 'webp') throw new Error(`Derivative MIME drift: ${relative}`);
    derivatives.push({ path: relative, sha256: sha256(bytes), mime: 'image/webp', width: meta.width, height: meta.height });
  }
  assets.push({
    logicalName: spec.logicalName,
    importLocator: spec.sourceLocator,
    sourceEvidence: spec.sourceEvidence,
    original: { path: spec.original, sha256: sha256(originalBytes), mime: originalMeta.format === 'jpeg' ? 'image/jpeg' : 'image/png', width: originalMeta.width, height: originalMeta.height },
    approvedAlt: spec.alt,
    allowedUse: ['home', spec.logicalName === 'tal-portrait' ? 'team' : 'careers', 'team'],
    derivatives,
  });
}

const brandAssets = [];
for (const spec of brandSpecs) {
  const bytes = await fs.readFile(path.join(root, spec.path));
  const meta = await sharp(bytes).metadata();
  const expectedFormat = spec.mime === 'image/png' ? 'png' : 'webp';
  if (sha256(bytes) !== spec.sha256 || meta.format !== expectedFormat || meta.width !== spec.width || meta.height !== spec.height) throw new Error(`Brand asset mismatch: ${spec.path}`);
  brandAssets.push(spec);
}

const doorSourceBytes = await fs.readFile(path.join(root, doorBuilderSpec.source));
const doorSourceMeta = await sharp(doorSourceBytes).metadata();
if (sha256(doorSourceBytes) !== doorBuilderSpec.sourceSha256 || doorSourceMeta.format !== 'png' || doorSourceMeta.width !== 1080 || doorSourceMeta.height !== 1080) throw new Error('Door-builder source drift.');
const generatedDoorBytes = await sharp(doorSourceBytes, { failOn: 'error' }).extract(doorBuilderSpec.crop)
  .webp({ quality: 82, effort: 6, smartSubsample: true }).toBuffer();
if (sha256(generatedDoorBytes) !== doorBuilderSpec.derivativeSha256 || generatedDoorBytes.length !== 149790) throw new Error('Door-builder deterministic derivative drift.');
const doorDerivativePath = path.join(root, doorBuilderSpec.derivative);
let doorDerivativeBytes = generatedDoorBytes;
if (check) {
  const committedBytes = await fs.readFile(doorDerivativePath);
  if (Buffer.compare(generatedDoorBytes, committedBytes) !== 0) throw new Error('Door-builder derivative byte drift.');
  doorDerivativeBytes = committedBytes;
} else {
  await fs.writeFile(doorDerivativePath, generatedDoorBytes, { flag: 'w', mode: 0o644 });
}
const doorDerivativeMeta = await sharp(doorDerivativeBytes).metadata();
if (doorDerivativeMeta.format !== 'webp' || doorDerivativeMeta.width !== 1080 || doorDerivativeMeta.height !== 930) throw new Error('Door-builder derivative geometry drift.');
const doorBuilderAssets = [{
  logicalName: doorBuilderSpec.logicalName,
  source: { path: doorBuilderSpec.source, sha256: doorBuilderSpec.sourceSha256, mime: 'image/png', width: 1080, height: 1080, importLocator: doorBuilderSpec.sourceLocator, sourceEvidence: doorBuilderSpec.sourceEvidence },
  derivative: { path: doorBuilderSpec.derivative, sha256: doorBuilderSpec.derivativeSha256, size: 149790, mime: 'image/webp', width: 1080, height: 930, crop: doorBuilderSpec.crop },
  approvedAlt: doorBuilderSpec.approvedAlt,
  allowedUse: ['home', 'door-builder'],
}];

const fontSpecs = [
  { path: 'assets/fonts/lilita-one-regular.woff2', family: 'Lilita One', weight: '400', sha256: '8d6cd0f298738a92ca9bf6e13f54a9191afd06ce04ea00ebbf24499c017191b7', license: 'OFL-1.1', sourceLocator: 'https://github.com/google/fonts/blob/main/ofl/lilitaone/OFL.txt' },
  { path: 'assets/fonts/nunito-variable.woff2', family: 'Nunito', weight: '200-1000', sha256: 'ba344451eab25b217a165363b1982048a5e5830a0daf36577973955a04cac793', license: 'OFL-1.1', sourceLocator: 'https://github.com/google/fonts/blob/main/ofl/nunito/OFL.txt' },
];
const fonts = [];
for (const font of fontSpecs) {
  const bytes = await fs.readFile(path.join(root, font.path));
  if (sha256(bytes) !== font.sha256) throw new Error(`Font hash mismatch: ${font.path}`);
  fonts.push({ ...font, mime: 'font/woff2' });
}

const manifest = {
  schemaVersion: 1,
  generator: { name: 'sharp', version: sharp.versions.sharp, libvips: sharp.versions.vips, libwebp: sharp.versions.webp, settings: { rotate: true, withoutEnlargement: true, webpQuality: 82, webpEffort: 6, smartSubsample: true } },
  fonts,
  brandAssets,
  doorBuilderAssets,
  assets,
};
const manifestPath = path.join(root, 'assets/owned-assets.provenance.json');
if (check) {
  const committed = JSON.parse(await fs.readFile(manifestPath, 'utf8'));
  if (JSON.stringify(committed) !== JSON.stringify(manifest)) throw new Error('Owned-asset provenance drift.');
} else {
  await fs.writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, { flag: 'w' });
}
```

Before the first run, copy `lilita-one-regular.woff2` and `nunito-variable.woff2` from the current tracked `twins-staging-assets/` package into `assets/fonts/` without byte changes. Run once without arguments to generate; after reviewing the manifest, run `--check`. Check mode regenerates every derivative in memory with the pinned Sharp/libvips version and options, byte-compares it to the committed derivative, and only then compares the regenerated manifest. A modified derivative plus modified manifest therefore fails.

- [ ] **Step 5: Add self-hosted fonts with recorded license/source metadata**

Verify the copied font hashes are `8d6cd0f298738a92ca9bf6e13f54a9191afd06ce04ea00ebbf24499c017191b7` and `ba344451eab25b217a165363b1982048a5e5830a0daf36577973955a04cac793`. The generator records the tracked source facts plus official `OFL-1.1` license locators; do not download replacement fonts. Add this test:

```js
for (const font of manifest.fonts) {
  assert.equal(digest(font.path), font.sha256);
  assert.equal(font.mime, 'font/woff2');
  assert.match(font.family, /^(Lilita One|Nunito)$/);
  assert.ok(font.license && font.sourceLocator);
}
```

No CSS may point to `fonts.googleapis.com` or `fonts.gstatic.com`.

- [ ] **Step 6: Run GREEN asset and legacy tests**

Run:

```bash
cd website/twins-brand-experience
npm run check:assets
node --test tests/contracts/assets.test.cjs
cd ../..
node --test website/staging-safety/tests/*.test.cjs
```

Expected: all asset hashes/dimensions pass, no executable reference file is present, and legacy staging tests have zero failures.

- [ ] **Step 7: Commit deterministic owned assets**

```bash
git add website/twins-brand-experience/assets website/twins-brand-experience/tools/build-owned-images.mjs website/twins-brand-experience/tests/contracts/assets.test.cjs docs/website-overhaul/reference-sources
git commit -m "feat(web): package verified Twins brand assets"
```

### Task 4: Capture, normalize, and verify real Google reviews without staging runtime network access

**Files:**
- Create: `website/twins-brand-experience/src/ReviewCodec.php`
- Modify: `website/twins-brand-experience/bootstrap.php`
- Create: `website/twins-brand-experience/tools/capture-google-reviews.mjs`
- Create: `website/twins-brand-experience/data/reviews/google-business-reviews-collection-2178.json`
- Create: `website/twins-brand-experience/tests/contracts/reviews.test.cjs`
- Create: `website/twins-brand-experience/tests/php/review-codec-harness.php`
- Create: `website/twins-brand-experience/tests/fixtures/reviews/brb-collection-2178.rendered.html`
- Create: `website/twins-brand-experience/tests/fixtures/reviews/{valid,bad-record-hash,bad-source-hash,bad-record-count,bad-business-url,bad-source-record-url,impossible-date,stale,short,relative-date}.json`
- Modify: `website/twins-brand-experience/tests/php-harnesses.test.cjs`

**Interfaces:**
- Consumes: only the fixed public read-only Reviews URL and normalized record schema.
- Produces: `ReviewCodec::stableId(array $record): string`, `ReviewCodec::recordSha256(array $record): string`, `ReviewCodec::verifyCollection(array $envelope, DateTimeImmutable $now): array`, and a canonical capture file used by staging adapters.

- [ ] **Step 1: Write canonical hash-vector and failure-mode tests first**

Create `tests/contracts/reviews.test.cjs` with an independent encoder:

```js
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const field = value => `${Buffer.byteLength(String(value), 'utf8')}:${value}\n`;
const recordHash = r => crypto.createHash('sha256').update(
  'twins-review-v1\n' + [r.stableId, r.author, r.rating, r.publishedDate, r.text, r.sourceRecordUrl || ''].map(field).join(''),
  'utf8'
).digest('hex');

test('canonical capture is fixed, current, exact-date, and individually verified', () => {
  const capturePath = path.join(root, 'data/reviews/google-business-reviews-collection-2178.json');
  const capture = JSON.parse(fs.readFileSync(capturePath, 'utf8'));
  assert.equal(capture.schemaVersion, 1);
  assert.equal(capture.sourceUrl, 'https://twinsgaragedoors.com/wi/reviews/');
  assert.equal(capture.businessReviewsUrl.startsWith('https://'), true);
  assert.equal(capture.multisitePath, '/wi/');
  assert.equal(capture.pageId, 2186);
  assert.equal(capture.collectionId, 2178);
  assert.ok(capture.providerVersion);
  assert.equal(capture.recordCount, capture.records.length);
  assert.match(capture.sourceResponseSha256, /^[a-f0-9]{64}$/);
  const raw = fs.readFileSync(path.join(root, 'tests/fixtures/reviews/brb-collection-2178.rendered.html'));
  assert.equal(crypto.createHash('sha256').update(raw).digest('hex'), capture.sourceResponseSha256);
  assert.ok(capture.records.length >= 5);
  for (const record of capture.records) {
    assert.match(record.publishedDate, /^\d{4}-\d{2}-\d{2}$/);
    assert.equal(new Date(`${record.publishedDate}T00:00:00Z`).toISOString().slice(0, 10), record.publishedDate);
    assert.ok(Number.isInteger(record.rating) && record.rating >= 1 && record.rating <= 5);
    assert.ok(record.sourceRecordUrl === '' || /^https:\/\//.test(record.sourceRecordUrl));
    assert.equal(record.recordSha256, recordHash(record));
  }
});

test('capture utility has no caller-selected source or write transport', () => {
  const source = fs.readFileSync(path.join(root, 'tools/capture-google-reviews.mjs'), 'utf8');
  assert.match(source, /https:\/\/twinsgaragedoors\.com\/wi\/reviews\//);
  assert.doesNotMatch(source, /process\.argv\[[2-9]\]|POST|PUT|PATCH|DELETE|cookie|authorization|api[_-]?key/i);
});
```

Add `review-codec-harness.php` scenarios `valid`, `bad-record-hash`, `bad-source-hash`, `bad-record-count`, `bad-business-url`, `bad-source-record-url`, `impossible-date`, `stale`, `short`, and `relative-date`; valid must echo `review-codec-ok`, every invalid scenario must catch `UnexpectedValueException` and echo `review-codec-rejected`.

- [ ] **Step 2: Run RED review tests**

Run:

```bash
cd website/twins-brand-experience
node --test tests/contracts/reviews.test.cjs
npm run test:php
```

Expected: FAIL for absent codec, fixtures, capture utility, and canonical capture.

- [ ] **Step 3: Implement the exact codec**

Create `src/ReviewCodec.php`:

```php
<?php
declare(strict_types=1);

namespace Twins\BrandExperience;

final class ReviewCodec
{
    private static function field(string $value): string
    {
        return strlen($value) . ':' . $value . "\n";
    }

    public static function stableId(array $record): string
    {
        if (isset($record['providerId']) && is_string($record['providerId']) && $record['providerId'] !== '') {
            return $record['providerId'];
        }
        $body = "twins-review-id-v1\n";
        foreach (['author', 'rating', 'publishedDate', 'text'] as $key) {
            $body .= self::field((string) $record[$key]);
        }
        return hash('sha256', $body);
    }

    public static function recordSha256(array $record): string
    {
        $body = "twins-review-v1\n";
        foreach (['stableId', 'author', 'rating', 'publishedDate', 'text', 'sourceRecordUrl'] as $key) {
            $body .= self::field((string) ($record[$key] ?? ''));
        }
        return hash('sha256', $body);
    }

    public static function verifyCollection(array $envelope, \DateTimeImmutable $now): array
    {
        $fixed = [
            'schemaVersion' => 1,
            'sourceUrl' => 'https://twinsgaragedoors.com/wi/reviews/',
            'multisitePath' => '/wi/',
            'pageId' => 2186,
            'collectionId' => 2178,
        ];
        foreach ($fixed as $key => $value) {
            if (($envelope[$key] ?? null) !== $value) throw new \UnexpectedValueException('Review provenance mismatch: ' . $key);
        }
        $businessReviewsUrl = $envelope['businessReviewsUrl'] ?? null;
        if (!is_string($businessReviewsUrl) || filter_var($businessReviewsUrl, FILTER_VALIDATE_URL) === false || parse_url($businessReviewsUrl, PHP_URL_SCHEME) !== 'https') throw new \UnexpectedValueException('Invalid business reviews URL.');
        if (!preg_match('/^[a-f0-9]{64}$/', (string) ($envelope['sourceResponseSha256'] ?? ''))) throw new \UnexpectedValueException('Invalid source hash.');
        if (!is_string($envelope['providerVersion'] ?? null) || $envelope['providerVersion'] === '') throw new \UnexpectedValueException('Missing provider version.');
        $captured = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', (string) ($envelope['capturedAt'] ?? ''), new \DateTimeZone('UTC'));
        if (!$captured || $captured->diff($now)->days > 90 || $captured > $now) throw new \UnexpectedValueException('Review capture is stale or future-dated.');
        if (!is_array($envelope['records'] ?? null) || count($envelope['records']) < 5) throw new \UnexpectedValueException('Insufficient verified reviews.');
        if (!is_int($envelope['recordCount'] ?? null) || $envelope['recordCount'] !== count($envelope['records'])) throw new \UnexpectedValueException('Review record count mismatch.');
        $ids = [];
        foreach ($envelope['records'] as $record) {
            $publishedDate = (string) ($record['publishedDate'] ?? '');
            $calendarDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $publishedDate, new \DateTimeZone('UTC'));
            if (!$calendarDate || $calendarDate->format('Y-m-d') !== $publishedDate) throw new \UnexpectedValueException('Review date is not an exact calendar date.');
            $rating = $record['rating'] ?? null;
            if (!is_int($rating) || $rating < 1 || $rating > 5) throw new \UnexpectedValueException('Invalid rating.');
            if (!is_string($record['author'] ?? null) || !is_string($record['text'] ?? null)) throw new \UnexpectedValueException('Invalid review text fields.');
            $sourceRecordUrl = $record['sourceRecordUrl'] ?? '';
            if (!is_string($sourceRecordUrl) || ($sourceRecordUrl !== '' && (filter_var($sourceRecordUrl, FILTER_VALIDATE_URL) === false || parse_url($sourceRecordUrl, PHP_URL_SCHEME) !== 'https'))) throw new \UnexpectedValueException('Invalid source record URL.');
            if (!is_string($record['stableId'] ?? null) || $record['stableId'] === '' || strlen($record['stableId']) > 256 || preg_match('/[\x00-\x1f\x7f]/', $record['stableId'])) throw new \UnexpectedValueException('Invalid stable ID.');
            if (($record['recordSha256'] ?? '') !== self::recordSha256($record)) throw new \UnexpectedValueException('Invalid record hash.');
            if (isset($ids[$record['stableId']])) throw new \UnexpectedValueException('Duplicate stable ID.');
            $ids[$record['stableId']] = true;
        }
        return $envelope;
    }
}
```

Append the pure class require to `bootstrap.php` with no hook or environment behavior:

```php
require_once __DIR__ . '/src/ReviewCodec.php';
```

Extend the PHP wrapper with one `phpTest()` call per fixture scenario, always passing a fixed fixture path and fixed current time `2026-07-15T00:00:00Z`.

- [ ] **Step 4: Implement the fixed read-only capture tool**

Create `tools/capture-google-reviews.mjs` with these non-negotiable constants and guards:

```js
const SOURCE_URL = 'https://twinsgaragedoors.com/wi/reviews/';
const MULTISITE_PATH = '/wi/';
const PAGE_ID = 2186;
const COLLECTION_ID = 2178;

if (process.argv.length !== 2) throw new Error('This utility accepts no arguments.');
const response = await fetch(SOURCE_URL, { method: 'GET', redirect: 'error', headers: { accept: 'text/html' } });
if (!response.ok || response.url !== SOURCE_URL) throw new Error(`Fixed review source failed: ${response.status}`);
const bytes = Buffer.from(await response.arrayBuffer());
const html = bytes.toString('utf8');
```

The remainder must:

1. write the exact raw response only to `tests/fixtures/reviews/brb-collection-2178.rendered.html`;
2. extract the Business Reviews Bundle version from its fixed plugin asset `?ver=` value and reject zero/multiple conflicting versions;
3. parse only the collection-2178 wrapper, rejecting a missing or duplicated wrapper;
4. extract provider ID when present, author, integer rating, exact displayed calendar date, verbatim text, and distinct per-review URL when present; assign `stableId` directly from that provider ID, or use the fixed fallback encoder when no provider ID exists;
5. normalize the provider date to `YYYY-MM-DD` only when the rendered day/month/year is exact;
6. compute stable ID and record hashes using the same byte encoder as `ReviewCodec`;
7. emit a canonical envelope with `capturedAt` in UTC, source-response hash, count, and records;
8. atomically replace only `data/reviews/google-business-reviews-collection-2178.json` after all checks pass.

It must reject relative dates, fewer than five records, duplicate IDs, absent source hash, or a business Reviews URL that is not HTTPS. It performs no POST/write request, receives no cookie/token, and accepts no input URL.

- [ ] **Step 5: Perform the authorized read-only capture and create failure fixtures**

Run from a network-enabled environment:

```bash
cd website/twins-brand-experience
node tools/capture-google-reviews.mjs
node --test tests/contracts/reviews.test.cjs
```

Expected: capture contains real production-rendered records, source response SHA-256, exact provider version, at least five records, and no credential. Manually compare the first five author/date/text values with the read-only production Reviews page; do not edit review wording.

Derive invalid fixtures mechanically from the valid envelope: change one record hash, one source hash format, the declared record count, the business Reviews URL scheme, one source-record URL scheme, one date to `2026-99-99`, `capturedAt` to `2025-01-01T00:00:00Z`, truncate to four records, and replace one exact date with `2 weeks ago`. The capture test must independently prove any record lacking a provider ID uses the specified fallback hash; the runtime envelope deliberately does not add a seventh `providerId` field to the fixed normalized schema. Never invent a fallback review used by runtime.

- [ ] **Step 6: Run GREEN review, portable, and legacy suites**

Run:

```bash
cd website/twins-brand-experience
npm run test:contracts
npm run test:php
cd ../..
node --test website/staging-safety/tests/*.test.cjs
```

Expected: all Node review vectors pass; all PHP review scenarios pass on PHP runtime; invalid cases fail closed; legacy staging suite remains zero-failure.

- [ ] **Step 7: Commit the verified review source**

```bash
git add website/twins-brand-experience/bootstrap.php website/twins-brand-experience/src/ReviewCodec.php website/twins-brand-experience/tools/capture-google-reviews.mjs website/twins-brand-experience/data/reviews website/twins-brand-experience/tests/contracts/reviews.test.cjs website/twins-brand-experience/tests/php/review-codec-harness.php website/twins-brand-experience/tests/php-harnesses.test.cjs website/twins-brand-experience/tests/fixtures/reviews
git commit -m "feat(web): add verified Google review capture contract"
```

### Task 5: Build the full shared header, footer, responsive picture, and normalized review components

**Files:**
- Modify: `website/twins-brand-experience/components/header.php`
- Modify: `website/twins-brand-experience/components/footer.php`
- Create: `website/twins-brand-experience/components/picture.php`
- Create: `website/twins-brand-experience/components/review-slider.php`
- Create: `website/twins-brand-experience/tests/contracts/components.test.cjs`
- Create: `website/twins-brand-experience/tests/php/renderer-contract-harness.php`
- Modify: `website/twins-brand-experience/tests/php-harnesses.test.cjs`

**Interfaces:**
- Consumes: `Experience::asset()`, `Experience::route()`, `Experience::markets()`, injected quote/booking adapters, and verified review envelopes.
- Produces: one accessible shared navigation, full footer, fixed logical-key picture renderer, and schema-free review slider markup used by every new template.

- [ ] **Step 1: Write RED component contracts**

Create `tests/contracts/components.test.cjs`:

```js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const root = path.resolve(__dirname, '../..');

const source = name => fs.readFileSync(path.join(root, 'components', name), 'utf8');

test('header exposes the approved complete navigation and CTA copy', () => {
  const html = source('header.php');
  for (const label of ['Services', 'Garage Doors', 'Service Areas', 'Resources', 'About', 'Our Team', 'Careers', 'Book Online', 'Request a Quote']) {
    assert.match(html, new RegExp(label.replace(' ', '\\s+')));
  }
  assert.doesNotMatch(html, /Get an Estimate|Request Exact Quote/);
  assert.match(html, /aria-controls="twins-brand-drawer"/);
  assert.match(html, /aria-expanded="false"/);
});

test('slider emits normalized records but no review schema owner', () => {
  const html = source('review-slider.php');
  assert.match(html, /twins-brand-review-slider/);
  assert.match(html, /id="twins-brand-reviews-title"/);
  assert.match(html, /Google reviews/);
  assert.match(html, /data-review-stable-id/);
  assert.match(html, /allowExternalSourceAction/);
  assert.doesNotMatch(html, /AggregateRating|ReviewRating|application\/ld\+json/);
});

test('picture component accepts logical keys, not paths or URLs', () => {
  const html = source('picture.php');
  assert.match(html, /\$logicalKey/);
  assert.match(html, /door-builder-before-after/);
  assert.doesNotMatch(html, /\$_GET|\$_POST|parse_url|https?:\/\//);
});

test('components never default a missing market or environment', () => {
  for (const name of ['header.php', 'footer.php', 'review-slider.php']) {
    assert.doesNotMatch(source(name), /\?\?\s*['"](?:staging|main)['"]/);
  }
});
```

Create a PHP renderer harness whose six fakes return only fixed same-host routes/assets and whose assertions require:

```php
$header = $experience->renderHeader(['environment' => 'staging', 'market' => 'main']);
assert(substr_count($header, 'aria-label="Primary navigation"') === 1);
assert(strpos($header, 'Request a Quote') !== false);
assert(strpos($header, 'Book Online') !== false);
assert(strpos($header, 'Our Team') !== false);
assert(strpos($header, 'Get an Estimate') === false);
assert(strpos($header, 'https://twinsgaragedoors.com') === false);
assert(strpos($header, 'book.housecallpro.com') === false);
```

Render the slider twice with fixed verified fake records containing punctuation, line breaks, a non-five-star rating, and exact dates. In staging mode assert the output preserves author/text/rating/date byte content after HTML decoding, links only to the internal Reviews route, contains visible Google attribution, and contains no `businessReviewsUrl` or per-record external URL. In production mode set exact boolean `allowExternalSourceAction => true` and assert exactly one `businessReviewsUrl` action appears with `rel="noopener noreferrer"`; false, absent, or non-boolean values must remain internal-only. Assert the `aria-labelledby` target exists exactly once and the stars' accessible rating matches each record.

Have the fake route adapter return same-origin paths from this exact closed key set: `home`, `services`, `installation`, `spring-repair`, `opener-repair`, `emergency-service`, `garage-doors`, `classic-collection`, `modern-steel`, `gallery-steel`, `door-builder`, `wi`, `ky`, `il-preview`, `reviews`, `cost-guide`, `financing`, `offers`, `faqs`, `blog`, `about`, `team`, `careers`, and `contact`. Use `/`, `/garage-door-services/`, `/garage-door-installation/`, `/garage-door-spring-repair/`, `/garage-door-opener-repair/`, `/emergency-garage-services/`, `/clopay-garage-doors/`, `/clopay-classic-collection/`, `/clopay-modern-steel/`, `/clopay-gallery-steel/`, `/door-builder/`, `/wi/`, `/ky/`, `/il/`, `/reviews/`, `/wi/garage-door-cost-in-madison-wi/`, `/financing/`, `/coupons-offers/`, `/faqs/`, `/blog/`, `/about-us/`, `/our-team/`, `/careers/`, and `/contact-us/` respectively. An unknown route key throws `DomainException`.

- [ ] **Step 2: Run RED tests**

Run:

```bash
cd website/twins-brand-experience
node --test tests/contracts/components.test.cjs
npm run test:php
```

Expected: FAIL because the temporary components do not implement the approved contracts.

- [ ] **Step 3: Implement one full shared header**

Replace `components/header.php` with semantic markup containing:

```php
<?php
declare(strict_types=1);
$serviceAreas = [];
foreach ($experience->markets()->all($environment) as $key => $availableMarket) {
    if ($key === 'main') continue;
    $serviceAreas[] = [$availableMarket['label'], $key];
}
$nav = [
    'Services' => [['All Services', 'services'], ['Garage Door Installation', 'installation'], ['Spring Repair', 'spring-repair'], ['Opener Repair', 'opener-repair'], ['Emergency Service', 'emergency-service']],
    'Garage Doors' => [['Garage Door Collections', 'garage-doors'], ['Classic Collection', 'classic-collection'], ['Modern Steel', 'modern-steel'], ['Gallery Steel', 'gallery-steel'], ['Design Your Door', 'door-builder']],
    'Service Areas' => $serviceAreas,
    'Resources' => [['Reviews', 'reviews'], ['Cost Guide', 'cost-guide'], ['Financing', 'financing'], ['Offers', 'offers'], ['Frequently Asked Questions', 'faqs'], ['Blog', 'blog']],
    'About' => [['About Twins', 'about'], ['Our Team', 'team'], ['Careers', 'careers'], ['Contact Us', 'contact']],
];
?>
<header class="twins-brand-header" data-twins-header>
  <div class="twins-brand-utility">
    <span>Choose your service area</span>
    <a class="twins-brand-phone" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($market['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?>
    </a>
  </div>
  <div class="twins-brand-fascia">
    <a class="twins-brand-logo" href="<?= htmlspecialchars($experience->route('home', $marketKey), ENT_QUOTES, 'UTF-8') ?>" aria-label="Twins Garage Doors home">
      <img src="<?= htmlspecialchars($experience->asset('logo'), ENT_QUOTES, 'UTF-8') ?>" width="711" height="325" alt="Twins Garage Doors">
    </a>
    <nav class="twins-brand-primary-nav" aria-label="Primary navigation">
      <?php foreach ($nav as $group => $items): ?>
        <div class="twins-brand-nav-group">
          <button type="button" class="twins-brand-nav-trigger" aria-expanded="false"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></button>
          <div class="twins-brand-nav-panel">
            <?php foreach ($items as [$label, $routeKey]): ?>
              <a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </nav>
    <button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Online</button>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    <button type="button" class="twins-brand-menu-trigger" aria-expanded="false" aria-controls="twins-brand-drawer">Menu</button>
  </div>
  <div id="twins-brand-drawer" class="twins-brand-drawer" hidden aria-hidden="true">
    <div class="twins-brand-drawer-panel" role="dialog" aria-modal="true" aria-label="Main menu">
      <button type="button" class="twins-brand-drawer-close" aria-label="Close menu">Close</button>
      <nav aria-label="Mobile navigation">
        <?php foreach ($nav as $group => $items): ?>
          <div class="twins-brand-drawer-group"><strong><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php foreach ($items as [$label, $routeKey]): ?><a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </nav>
      <button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Online</button>
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </div>
  <?php if (($booking['mode'] ?? '') === 'dialog' && is_string($booking['experienceHtml'] ?? null)) echo $booking['experienceHtml']; ?>
</header>
```

The production booking adapter may return an external `href`; the shared header must render it only when `action()['mode'] === 'external'`. For staging `mode === 'dialog'`, it must render only the `type="button"` shown above. Add an explicit branch and a harness assertion for each mode.

- [ ] **Step 4: Implement fixed-key responsive pictures and verified review cards**

`components/picture.php` receives `$logicalKey`, `$sizes`, `$class`, and `$loading`; it uses this closed key map:

```php
$pictures = [
    'crew-fleet' => ['original' => 'crew-fleet-original', 'sources' => ['crew-fleet-768w 768w', 'crew-fleet-1280w 1280w', 'crew-fleet-1920w 1920w'], 'width' => 2560, 'height' => 1372, 'alt' => 'The Twins Garage Doors crew with their branded service fleet'],
    'tal-portrait' => ['original' => 'tal-portrait-original', 'sources' => ['tal-portrait-480w 480w', 'tal-portrait-768w 768w', 'tal-portrait-1066w 1066w'], 'width' => 1066, 'height' => 1600, 'alt' => 'Twins Garage Doors co-founder Tal Joseph'],
    'technician-at-work' => ['original' => 'technician-original', 'sources' => ['technician-480w 480w', 'technician-768w 768w', 'technician-924w 924w'], 'width' => 924, 'height' => 570, 'alt' => 'A Twins Garage Doors technician working on a garage door'],
    'door-builder-before-after' => ['original' => 'door-builder-before-after', 'sources' => [], 'width' => 1080, 'height' => 930, 'alt' => 'Before and after view of a real Twins garage door installation'],
];
if (!isset($pictures[$logicalKey])) throw new DomainException('Unknown picture key.');
```

Build `srcset` by resolving each fixed logical asset key through `Experience::asset()`. Escape every URL/alt/class. Never accept a path or caller URL.

`components/review-slider.php` must call `reviewCollection()` once, render the unnumbered failure notice when it returns `['status' => 'unavailable']`, and otherwise render:

```php
<section class="twins-brand-reviews" aria-labelledby="twins-brand-reviews-title">
  <h2 id="twins-brand-reviews-title">What our customers say</h2>
  <p class="twins-brand-google-attribution" aria-label="Verified Google reviews">Google reviews from real Twins customers</p>
  <div class="twins-brand-review-slider" data-twins-review-slider data-review-count="<?= count($collection['records']) ?>">
    <div class="twins-brand-review-track">
      <?php foreach ($collection['records'] as $index => $review): ?>
        <article class="twins-brand-review-card" data-review-stable-id="<?= htmlspecialchars($review['stableId'], ENT_QUOTES, 'UTF-8') ?>" data-review-index="<?= $index ?>">
          <div class="twins-brand-review-stars" aria-label="<?= (int) $review['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $review['rating']) ?></div>
          <blockquote><?= nl2br(htmlspecialchars($review['text'], ENT_QUOTES, 'UTF-8')) ?></blockquote>
          <footer><strong><?= htmlspecialchars($review['author'], ENT_QUOTES, 'UTF-8') ?></strong><time datetime="<?= htmlspecialchars($review['publishedDate'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($review['publishedDate'], ENT_QUOTES, 'UTF-8') ?></time></footer>
        </article>
      <?php endforeach; ?>
    </div>
    <button type="button" data-review-prev aria-label="Previous reviews">Previous</button>
    <div class="twins-brand-review-dots" role="group" aria-label="Choose review page"></div>
    <button type="button" data-review-next aria-label="Next reviews">Next</button>
  </div>
  <a href="<?= htmlspecialchars($experience->route('reviews', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Read all reviews</a>
  <?php if (($collection['allowExternalSourceAction'] ?? null) === true): ?>
    <a href="<?= htmlspecialchars($collection['businessReviewsUrl'], ENT_QUOTES, 'UTF-8') ?>" rel="noopener noreferrer">See our reviews on Google</a>
  <?php endif; ?>
</section>
```

Do not emit provider HTML, reviewer avatar hotlinks, JSON-LD, Review schema, or AggregateRating schema. The production-only external action is allowed only after `ReviewCodec` has verified the collection envelope and the adapter sets exact boolean `allowExternalSourceAction`; staging always renders the internal Reviews-page action only.

- [ ] **Step 5: Implement the footer and sticky mobile actions**

The footer repeats internal service/market/about routes, main phone, and exact `Request a Quote`. The mobile action bar contains only:

```php
<div class="twins-brand-mobile-actions" aria-label="Quick actions">
  <a href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
  <a href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
</div>
```

Add `padding-bottom` support later in CSS so the bar never covers content. Footer output contains no hard-coded staging/production hostname.

- [ ] **Step 6: Run GREEN component tests**

Run:

```bash
cd website/twins-brand-experience
npm run test:contracts
npm run test:php
```

Expected: static component tests PASS; renderer harness PASS on PHP; no prohibited CTA or live staging booking URL appears.

- [ ] **Step 7: Commit shared components**

```bash
git add website/twins-brand-experience/components website/twins-brand-experience/tests/contracts/components.test.cjs website/twins-brand-experience/tests/php/renderer-contract-harness.php website/twins-brand-experience/tests/php-harnesses.test.cjs
git commit -m "feat(web): add functional Twins shared chrome"
```

### Task 6: Build the approved homepage, Our Team, Careers, Contact, and Reviews surfaces

**Files:**
- Modify: `website/twins-brand-experience/templates/home.php`
- Modify: `website/twins-brand-experience/templates/team.php`
- Modify: `website/twins-brand-experience/templates/careers.php`
- Modify: `website/twins-brand-experience/templates/contact.php`
- Modify: `website/twins-brand-experience/templates/reviews.php`
- Create: `website/twins-brand-experience/tests/contracts/templates.test.cjs`
- Modify: `website/twins-brand-experience/tests/php/renderer-contract-harness.php`

**Interfaces:**
- Consumes: shared header/footer/picture/review components and injected quote/booking/application adapters.
- Produces: the eleven-section homepage and four complete supporting journeys, all sharing exact CTA/route/asset/provider contracts.

- [ ] **Step 1: Write RED template coverage**

Create `tests/contracts/templates.test.cjs`:

```js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const root = path.resolve(__dirname, '../..');
const template = name => fs.readFileSync(path.join(root, 'templates', name), 'utf8');

test('home contains every approved section in order', () => {
  const html = template('home.php');
  const markers = ['brand-hero', 'trust-ribbon', 'service-pathways', 'review-slider', 'team-story', 'door-builder', 'market-selector', 'careers', 'final-cta'];
  let cursor = -1;
  for (const marker of markers) {
    const next = html.indexOf(marker);
    assert.ok(next > cursor, `${marker} is missing or out of order`);
    cursor = next;
  }
  assert.match(html, /Garage Door Repair & Installation, Done Right Today\./);
  assert.match(html, /Same-day appointments/);
  assert.match(html, /Upfront pricing/);
  assert.match(html, /Most repairs done in one visit/);
});

test('team and careers use real fixed picture keys', () => {
  assert.match(template('team.php'), /crew-fleet/);
  assert.match(template('team.php'), /tal-portrait/);
  assert.match(template('careers.php'), /crew-fleet|technician-at-work/);
  assert.doesNotMatch(template('careers.php'), /https?:\/\/|fetch\s*\(|TWINS_ENDPOINT|type="submit"|<form/i);
});

test('all redesigned templates use exact quote copy', () => {
  for (const name of ['home.php', 'team.php', 'careers.php', 'contact.php', 'reviews.php']) {
    const html = template(name);
    assert.doesNotMatch(html, /Get an Estimate|Request Exact Quote/);
    assert.match(html, /Request a Quote/);
  }
});

test('homepage has deterministic desktop and mobile truck placements', () => {
  const html = template('home.php');
  assert.match(html, /twins-brand-hero[\s\S]*twins-brand-truck--hero/);
  assert.match(html, /twins-brand-mobile-proof[\s\S]*twins-brand-truck--mobile-proof/);
  assert.match(html, /door-builder-before-after/);
});
```

Extend the PHP renderer harness so the five body renderers (`renderHome`, `renderTeam`, `renderCareers`, `renderContact`, and `renderReviews`) each contain exactly one `id="twins-overhaul-main"`; `renderHeader` and `renderFooter` contain none. The bridge must compose header/footer once around each body, and staging form markup has zero `<form`, `type="submit"`, `type="image"`, `name=`, external URL, `fetch`, XHR, or beacon primitive.

- [ ] **Step 2: Run RED template tests**

Run:

```bash
cd website/twins-brand-experience
node --test tests/contracts/templates.test.cjs
npm run test:php
```

Expected: FAIL because the temporary templates lack the approved sections and real imagery.

- [ ] **Step 3: Implement the homepage in the locked order**

In `home.php`, render one `<main id="twins-overhaul-main" class="twins-brand-page twins-brand-home">` with the following concrete sections:

```php
<section class="twins-brand-hero" data-section="brand-hero">
  <div class="twins-brand-hero-copy">
    <span class="twins-brand-kicker">Local garage door service across our communities</span>
    <h1>Garage Door Repair &amp; Installation, Done Right Today.</h1>
    <p>Fast local service, straight answers, and upfront options from the Twins crew.</p>
    <div class="twins-brand-hero-actions">
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </div>
  <div class="twins-brand-hero-art" aria-label="The Twins Garage Doors team">
    <img class="twins-brand-truck twins-brand-truck--hero" src="<?= htmlspecialchars($experience->asset('truck-webp'), ENT_QUOTES, 'UTF-8') ?>" width="1398" height="821" alt="Twins Garage Doors branded service truck">
    <img class="twins-brand-twin twins-brand-twin--left" src="<?= htmlspecialchars($experience->asset('twin-left'), ENT_QUOTES, 'UTF-8') ?>" width="196" height="534" alt="Twins Garage Doors technician character">
    <img class="twins-brand-twin twins-brand-twin--right" src="<?= htmlspecialchars($experience->asset('twin-right'), ENT_QUOTES, 'UTF-8') ?>" width="297" height="538" alt="Twins Garage Doors technician character">
  </div>
</section>
<section class="twins-brand-mobile-proof" aria-label="Local Twins service team">
  <img class="twins-brand-truck twins-brand-truck--mobile-proof" src="<?= htmlspecialchars($experience->asset('truck-webp'), ENT_QUOTES, 'UTF-8') ?>" width="1398" height="821" alt="" aria-hidden="true">
  <p>Local crews. Branded trucks. Straight answers.</p>
</section>
<section class="twins-brand-trust-ribbon" data-section="trust-ribbon" aria-label="Service promises">
  <span>Same-day appointments</span><span>Upfront pricing</span><span>Most repairs done in one visit</span>
</section>
```

Then implement, in exact order:

1. `twins-brand-service-pathways`: cards for Repair, Installation, and Openers with same-host routes.
2. Include `components/review-slider.php` inside a `data-section="review-slider"` wrapper.
3. `twins-brand-team-story`: open/visible `crew-fleet` and `technician-at-work` pictures, heading `Meet the people behind Twins`, honest copy without invented staff claims, and Our Team link.
4. `twins-brand-door-builder`: call the fixed picture component with exact logical key `door-builder-before-after`, link only to the legacy builder route, and use exact `Design Your Door` CTA. The source creative is never rendered or packaged.
5. `twins-brand-market-selector`: Wisconsin/Kentucky plus `Illinois preview` only when the registry returns it for staging; production render cannot obtain it.
6. `twins-brand-careers`: packaged real photo, approved benefits/role invitation, internal Careers link.
7. `twins-brand-final-cta`: Call Twins plus exact Request a Quote.

The mobile proof section is hidden at `1201px` and above. At `1200px` and below the hero truck is hidden, the proof truck is displayed immediately after the hero, and both Twin characters remain visible in the hero. Desktop shows the hero truck and hides the duplicate proof section. These are locked DOM/CSS states, not an “if required” implementation choice.

Do not include the old closed legacy `<details>` disclosure on this rebuilt homepage.

- [ ] **Step 4: Implement Our Team and Careers without invented people or submission authority**

`team.php` renders an intro, real crew/fleet picture, Tal portrait identified only by the approved known name, technician-at-work picture, company values/story, Careers link, and quote closer. Do not add employee names, titles, biographies, or portraits not present in approved sources.

`careers.php` recreates the navy/gold reference language with in-page links `#why-twins`, `#roles`, `#process`, and `#apply`, real owned imagery, and the exact approved content structure from the inert reference:

- hero: `Do work you are proud to put your name on.` with `Clear expectations`, `A customer-first crew`, and `Room to learn the craft`;
- values: `Own the outcome`, `Treat people right`, and `Keep learning`, using the reference's approved explanatory sentences verbatim;
- contribution lanes: `Service and repair`, `Installations`, `Sales and estimates`, `Customer care and operations`, and `Something else`, using the approved one-sentence descriptions verbatim;
- hiring path: `Share your interest`, `Quick screen`, `Meet the team`, and `Clear decision`, while changing any claim of automatic real follow-up to clear staging-preview language;
- application intro: `Tell us where you could make an impact.` and the explicit note that submitting interest does not guarantee a current opening.

Its application experience is returned only by:

```php
<?= $experience->applicationAdapter()->renderExperience($context) ?>
```

Implement the already locked `ApplicationAdapter::renderExperience(array $context): string` method in every fake/staging/production adapter so type consistency remains exact.

The staging implementation must output:

```html
<div class="twins-brand-preview-form" role="form" aria-labelledby="twins-brand-careers-form-title" data-preview-kind="application">
  <label>Full name <input type="text" autocomplete="name"></label>
  <label>Email <input type="email" autocomplete="email"></label>
  <label>Phone <input type="tel" autocomplete="tel"></label>
  <label>Role of interest <select><option>Service and repair</option><option>Installations</option><option>Sales and estimates</option><option>Customer care and operations</option><option>Something else</option></select></label>
  <label>Tell us about your experience <textarea></textarea></label>
  <button type="button" data-preview-finalize>Review application on staging</button>
  <p role="status" hidden data-preview-status>This private preview cannot send an application.</p>
</div>
```

No field has `name`, `form`, `formaction`, or a remote source.

- [ ] **Step 5: Implement Contact, Reviews, and booking/quote preview dialogs**

`contact.php` renders phone/service-area choices and delegates its quote body to `QuoteAdapter::renderExperience($context)`. The staging adapter supplies a preview with Full name, Phone, Email, ZIP code, Service needed, and Message; final control is `type="button"` and explains the staging block.

`reviews.php` renders the complete verified record collection as branded cards with internal navigation; production-only Google source action comes from `businessReviewsUrl` only when the production provider sets `allowExternalSourceAction: true`.

The staging booking adapter in Task 7 returns this dialog as the trusted `experienceHtml` value inside its typed `BookingAdapter::action()` payload:

```html
<div class="twins-brand-booking-dialog" data-twins-booking-dialog hidden>
  <div role="dialog" aria-modal="true" aria-labelledby="twins-brand-booking-title">
    <button type="button" data-booking-close aria-label="Close booking preview">Close</button>
    <h2 id="twins-brand-booking-title">Book with Twins</h2>
    <p>Choose a convenient time after this experience moves to production.</p>
    <button type="button" data-booking-finalize>Continue on staging</button>
    <p role="status" hidden data-booking-status>Booking is intentionally disabled on this private staging copy.</p>
  </div>
</div>
```

This markup contains no Housecall Pro URL and is stored only under the staging adapter boundary, never in the portable or production package. The production booking action returns external mode and no `experienceHtml` field.

- [ ] **Step 6: Run GREEN template and renderer tests**

Run:

```bash
cd website/twins-brand-experience
npm run test:contracts
npm run test:php
```

Expected: exact section order and copy PASS; real picture keys PASS; staging HTML contains no submission or external transport primitive; all seven render methods PASS on PHP.

- [ ] **Step 7: Commit all complete portable pages**

```bash
git add website/twins-brand-experience/templates website/twins-brand-experience/src/Contracts.php website/twins-brand-experience/tests/contracts/templates.test.cjs website/twins-brand-experience/tests/php/renderer-contract-harness.php
git commit -m "feat(web): build brand-wide Twins page journeys"
```

### Task 7: Inject the portable core through the existing fail-closed staging loader

**Files:**
- Create: `website/staging-safety/mu-plugins/twins-staging-overhaul/brand-runtime.php`
- Create: `website/staging-safety/mu-plugins/twins-staging-overhaul/adapters/BrandStagingAdapters.php`
- Create: `website/staging-safety/mu-plugins/twins-staging-overhaul/adapters/BrandStagingPreviews.php`
- Modify: `website/staging-safety/mu-plugins/twins-staging-overhaul/bootstrap.php`
- Modify: `website/staging-safety/mu-plugins/twins-staging-overhaul/components.php`
- Modify: `website/staging-safety/mu-plugins/twins-staging-overhaul/routes.php`
- Modify: `website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php`
- Modify: `website/staging-safety/mu-plugins/twins-staging-overhaul/templates/home.php`
- Create: `website/staging-safety/tests/staging-brand-adapters-harness.php`
- Modify: `website/staging-safety/tests/staging-overhaul-renderers-harness.php`
- Modify: `website/staging-safety/tests/staging-overhaul-harnesses.test.cjs`
- Modify: `website/staging-safety/tests/recovered-live-overhaul.test.cjs`

**Interfaces:**
- Consumes: the pure portable package and unchanged root staging gates/safety plugin.
- Produces: `twins_overhaul_brand_runtime()`, five branded route classifications, staging-only inert adapters, core asset enqueue, and compatibility facades that preserve every legacy page family.

- [ ] **Step 1: Turn the obsolete home/preserve expectations RED**

Add `home-brand` to the renderer scenario list in `staging-overhaul-harnesses.test.cjs`. In `staging-overhaul-renderers-harness.php`, replace the old assertions that nav/menu/quote are absent with:

```php
twins_overhaul_harness_assert(substr_count($output, 'aria-label="Primary navigation"') === 1, 'home lacks exactly one primary navigation');
twins_overhaul_harness_assert(strpos($output, 'Request a Quote') !== false, 'home lacks exact quote CTA');
twins_overhaul_harness_assert(strpos($output, 'Book Online') !== false, 'home lacks booking control');
twins_overhaul_harness_assert(strpos($output, 'Our Team') !== false, 'home lacks Our Team journey');
twins_overhaul_harness_assert(strpos($output, 'Get an Estimate') === false, 'obsolete CTA survived');
```

Change the current home outcome to `home-brand`, and change page ID/slug expectations for Team, Careers, Reviews, and Contact from preserve/trust to `team-brand`, `careers-brand`, `reviews-brand`, and `contact-brand`. Add all five values to the fixed known-classification allowlist and remove the obsolete `home`, `careers-preserve`, and `team-preserve` outcomes only after their replacement scenarios are green. Add scenarios proving legacy service/location/article/cost/builder output remains unchanged.

Create `staging-brand-adapters-harness.php` that replaces `do_shortcode`, `wp_remote_get`, `wp_remote_post`, `get_transient`, and database access with counters that throw if invoked. Render all staging adapters and assert every counter remains zero. Add missing/unknown context scenarios proving absent `key`, unknown `key`, missing normalized environment/market, and a fake production environment all throw before any template include; no adapter may default to main or staging.

- [ ] **Step 2: Run the staging suite to verify RED**

Run locally and then on PHP:

```bash
node --test website/staging-safety/tests/staging-overhaul-harnesses.test.cjs
php website/staging-safety/tests/staging-overhaul-renderers-harness.php website/staging-safety/mu-plugins/twins-staging-overhaul.php home-brand
php website/staging-safety/tests/staging-brand-adapters-harness.php website/staging-safety/mu-plugins/twins-staging-overhaul.php website/twins-brand-experience
```

Expected: the current compact homepage/preserve behavior FAILS the new assertions; local wrapper may skip only because PHP is absent.

- [ ] **Step 3: Implement fixed staging adapters**

`BrandStagingAdapters.php` defines:

```php
final class StagingAssetResolver implements AssetResolver {
    private string $base;
    private const MAP = [
        'logo' => 'assets/images/brand/twins-logo.png',
        'twin-left' => 'assets/images/brand/twin-left.png',
        'twin-right' => 'assets/images/brand/twin-right.png',
        'truck-original' => 'assets/images/brand/twins-service-truck-cutout.png',
        'truck-webp' => 'assets/images/brand/twins-service-truck-cutout.webp',
        'crew-fleet-original' => 'assets/images/team/twins-crew-fleet.jpeg',
        'crew-fleet-768w' => 'assets/images/team/twins-crew-fleet-768w.webp',
        'crew-fleet-1280w' => 'assets/images/team/twins-crew-fleet-1280w.webp',
        'crew-fleet-1920w' => 'assets/images/team/twins-crew-fleet-1920w.webp',
        'tal-portrait-original' => 'assets/images/team/tal-joseph.jpeg',
        'tal-portrait-480w' => 'assets/images/team/tal-joseph-480w.webp',
        'tal-portrait-768w' => 'assets/images/team/tal-joseph-768w.webp',
        'tal-portrait-1066w' => 'assets/images/team/tal-joseph-1066w.webp',
        'technician-original' => 'assets/images/team/twins-technician-at-work.png',
        'technician-480w' => 'assets/images/team/twins-technician-at-work-480w.webp',
        'technician-768w' => 'assets/images/team/twins-technician-at-work-768w.webp',
        'technician-924w' => 'assets/images/team/twins-technician-at-work-924w.webp',
        'door-builder-before-after' => 'assets/images/door-builder/twins-before-after-install.webp',
    ];
    public function __construct(string $base, string $networkHome) {
        foreach ([$base, $networkHome] as $url) {
            $parts = parse_url($url);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) throw new DomainException('Invalid staging asset origin.');
        }
        $baseParts = parse_url($base);
        $homeParts = parse_url($networkHome);
        $baseOrigin = strtolower($baseParts['host']) . ':' . ($baseParts['port'] ?? 443);
        $homeOrigin = strtolower($homeParts['host']) . ':' . ($homeParts['port'] ?? 443);
        if ($baseOrigin !== $homeOrigin) throw new DomainException('Cross-origin staging assets are forbidden.');
        $this->base = rtrim($base, '/');
    }
    public function url(string $assetKey): string {
        if (!isset(self::MAP[$assetKey])) throw new DomainException('Unknown asset key.');
        return $this->base . '/' . self::MAP[$assetKey];
    }
}
```

The staging runtime supplies only `content_url('mu-plugins/twins-brand-experience')` and fixed `network_home_url('/')` to `StagingAssetResolver`. The adapter harness covers HTTP, userinfo, query, fragment, cross-host, non-default-port mismatch, unknown asset key, and valid same-origin cases. No hotlink or caller asset base is accepted.

`StagingRouteAdapter` derives one same-origin base from fixed `network_home_url('/')`, verifies it is HTTPS with no userinfo/query/fragment, and joins only a closed route map copied from the existing verified `twins_overhaul_navigation()` paths. The route map is keyed by route key plus market and includes `/`, `/wi/`, `/ky/`, `/il/`, the existing service/catalog/builder/cost/resource paths, and the four rebuilt routes; it rejects an unknown key/market and never accepts a caller URL/path. Its `normalizeContext()` requires the existing fixed context key to be one of `main`, `wi`, `ky`, or `il`, returns `environment => staging`, and maps only `il` to portable `market => il-preview` (`main`, `wi`, and `ky` remain identical). It rejects a missing/unknown key instead of defaulting to main. `CapturedReviewsProvider` reads exactly `WPMU_PLUGIN_DIR . '/twins-brand-experience/data/reviews/google-business-reviews-collection-2178.json'` only when `lstat()` proves a non-symlink regular file from 1 byte through 2 MiB. It compares device/inode/mode/uid/gid/size/mtime/ctime before and after the bounded read, decodes it, calls `ReviewCodec::verifyCollection()`, and returns `['status' => 'unavailable']` on any failure or race. It performs no shortcode/cache/database/network call.

`StagingQuoteAdapter`, `StagingBookingAdapter`, and `StagingApplicationAdapter` return only `mode => preview/dialog`, same-host `/contact-us/` for Quote, and inert markup from staging-only `BrandStagingPreviews.php`. Every `assertReady()` proves the structural no-authority invariants. `StagingBookingAdapter::action()` includes the approved dialog as `experienceHtml`; the production action omits that field.

- [ ] **Step 4: Compose the staging runtime only after existing gates**

Create `brand-runtime.php`:

```php
<?php
declare(strict_types=1);

use Twins\BrandExperience\Experience;
use Twins\BrandExperience\MarketRegistry;

function twins_overhaul_brand_runtime(): Experience
{
    static $runtime = null;
    if ($runtime instanceof Experience) return $runtime;
    $candidates = [dirname(__DIR__) . '/twins-brand-experience'];
    if (PHP_SAPI === 'cli') $candidates[] = dirname(__DIR__, 3) . '/twins-brand-experience';
    $valid = array_values(array_filter($candidates, static function (string $candidate): bool {
        return is_dir($candidate) && is_file($candidate . '/bootstrap.php') && !is_link($candidate) && !is_link($candidate . '/bootstrap.php');
    }));
    if (count($valid) !== 1) throw new RuntimeException('Portable brand core resolution is unavailable or ambiguous.');
    $core = $valid[0];
    $bootstrap = $core . '/bootstrap.php';
    require_once $bootstrap;
    require_once __DIR__ . '/adapters/BrandStagingAdapters.php';
    require_once __DIR__ . '/adapters/BrandStagingPreviews.php';
    $markets = new MarketRegistry(require $core . '/config/markets.php');
    $runtime = new Experience(
        new StagingAssetResolver(content_url('mu-plugins/twins-brand-experience'), network_home_url('/')),
        new StagingRouteAdapter(),
        new CapturedReviewsProvider($core . '/data/reviews/google-business-reviews-collection-2178.json'),
        new StagingQuoteAdapter(),
        new StagingBookingAdapter(),
        new StagingApplicationAdapter(),
        $markets,
        $core
    );
    return $runtime;
}
```

Modify only the existing implementation bootstrap, after root gate success, to require `brand-runtime.php`. The root `twins-staging-overhaul.php` pre-bootstrap checks remain byte-identical and still include zero implementation files/hooks on any failed gate.

- [ ] **Step 5: Delegate redesigned surfaces while preserving legacy renderers**

Keep the public compatibility function names in `components.php` and `templates/home.php`, but make them delegate to the runtime. Add fixed route classifications for home, team, careers, reviews, and contact. In `renderers.php`:

```php
$brandRenderers = [
    'home-brand' => 'renderHome',
    'team-brand' => 'renderTeam',
    'careers-brand' => 'renderCareers',
    'reviews-brand' => 'renderReviews',
    'contact-brand' => 'renderContact',
];
if (isset($brandRenderers[$classification])) {
    $runtime = twins_overhaul_brand_runtime();
    $context = twins_overhaul_current_context($classification);
    return $runtime->{$brandRenderers[$classification]}($context);
}
```

Enqueue `twins-brand.css` and `twins-brand.js` from the portable path after the current legacy CSS/JS only on the five branded classifications. Add `twins-brand-experience` to the body classes. Compose shared header/footer exactly once; do not double-render nav through both theme and content hooks.

Tighten eligible-page CSP to `connect-src 'none'; form-action 'none'` without changing the baseline safety CSP for other pages.

- [ ] **Step 6: Prove staging gates, adapters, new routes, and legacy preservation**

Run:

```bash
node --test website/staging-safety/tests/*.test.cjs
php website/staging-safety/tests/staging-overhaul-bootstrap-harness.php website/staging-safety/mu-plugins/twins-staging-overhaul.php missingEnvironment
php website/staging-safety/tests/staging-overhaul-renderers-harness.php website/staging-safety/mu-plugins/twins-staging-overhaul.php home-brand
php website/staging-safety/tests/staging-brand-adapters-harness.php website/staging-safety/mu-plugins/twins-staging-overhaul.php website/twins-brand-experience
```

Expected: every invalid gate includes zero implementation files/hooks; five brand routes pass; preview adapters invoke zero shortcode/cache/database/network calls; service/location/article/cost/builder/status/acquire/heartbeat-adjacent repository behavior remains unchanged; all staging suites exit zero on PHP runtime.

- [ ] **Step 7: Commit staging integration**

```bash
git add website/staging-safety/mu-plugins/twins-staging-overhaul website/staging-safety/tests/staging-brand-adapters-harness.php website/staging-safety/tests/staging-overhaul-renderers-harness.php website/staging-safety/tests/staging-overhaul-harnesses.test.cjs website/staging-safety/tests/recovered-live-overhaul.test.cjs
git commit -m "feat(web): integrate portable brand core on private staging"
```

### Task 8: Package and fixture-test production adapters without touching production

**Files:**
- Create: `website/twins-brand-experience/production/twins-brand-experience-loader.php`
- Create: `website/twins-brand-experience/production/twins-brand-experience.php`
- Create: `website/twins-brand-experience/production/ProductionWordPressBridge.php`
- Create: `website/twins-brand-experience/production/ProductionAdapters.php`
- Create: `website/twins-brand-experience/production/BusinessReviewsBundleProvider.php`
- Create: `website/twins-brand-experience/production/BusinessReviewsBundleParser.php`
- Create: `website/twins-brand-experience/production/assets/twins-brand-production.js`
- Create: `website/twins-brand-experience/tests/contracts/production.test.cjs`
- Create: `website/twins-brand-experience/tests/php/production-loader-harness.php`
- Create: `website/twins-brand-experience/tests/fixtures/adapters/{gravity-form-1.json,employment-rest-route.json,brb-provider-contract.json}`
- Create: `website/twins-brand-experience/tests/fixtures/adapters/brb-provider-output.html`
- Modify: `website/twins-brand-experience/tests/php-harnesses.test.cjs`

**Interfaces:**
- Consumes: portable core, exact production workflow contracts, and captured provider fixture; no staging constant/function/plugin.
- Produces: a dormant explicit-enable production loader, real adapter readiness checks, a parser/provider isolated from templates, and transport JS excluded from staging packages.

- [ ] **Step 1: Write RED production isolation and readiness tests**

Create `tests/contracts/production.test.cjs`:

```js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('production package contains exact contracts and no staging dependency', () => {
  const files = [
    'production/twins-brand-experience-loader.php',
    'production/twins-brand-experience.php',
    'production/ProductionWordPressBridge.php',
    'production/ProductionAdapters.php',
    'production/BusinessReviewsBundleProvider.php',
    'production/BusinessReviewsBundleParser.php',
    'production/assets/twins-brand-production.js',
  ];
  const source = files.map(read).join('\n');
  assert.match(source, /Gravity Form(?:s)?[^\n]*1|FORM_ID\s*=\s*1/);
  assert.match(source, /TWINS_HCP_BOOK_URL/);
  assert.match(source, /TWINS_BRAND_ILLINOIS_PUBLIC/);
  assert.match(source, /\/wp-json\/twins\/v1\/employment-applications/);
  assert.match(source, /TWINS_GHL_PIT/);
  assert.match(source, /TWINS_GHL_LOCATION_ID/);
  assert.doesNotMatch(source, /danielj140|TWINS_STAGING_SAFETY|DISABLE_WP_CRON|twins_staging_safety/i);
  assert.doesNotMatch(source, /BrandStaging|CapturedReviewsProvider|StagingAssetResolver|StagingRouteAdapter|twins_overhaul_inert_csp_policy/i);
});

test('browser transport exposes no protected credential', () => {
  const source = read('production/assets/twins-brand-production.js');
  assert.doesNotMatch(source, /GHL|LeadConnector|TWINS_GHL|pit[_-]?token|authorization/i);
  assert.match(source, /\/wp-json\/twins\/v1\/employment-applications/);
});
```

Create PHP harness scenarios: `boot-disabled`, `boot-enabled`, `wrong-environment`, `runtime-wiring`, `context-missing-environment`, `context-missing-market`, `context-unknown-environment`, `context-unknown-market`, `asset-ready`, `asset-unknown`, `quote-ready`, `quote-form-inactive`, `quote-form-trashed`, `quote-renderer-missing`, `quote-contact-renderer-missing`, `quote-subsite-path-refused`, `quote-cross-origin-refused`, `booking-ready`, `booking-wrong-url`, `application-ready`, `application-missing`, `reviews-ready`, `reviews-no-site`, `reviews-ambiguous-site`, `reviews-plugin-drift`, `reviews-parser-drift`, `reviews-invalid-envelope`, `reviews-shortcode-error`, `reviews-restore-failure`, `illinois-refused`, and `illinois-explicit-public`. Stub WordPress, Gravity Forms, multisite, shortcode, and REST functions in memory; every scenario proves zero real network, database, mail, lead, booking, or production writes.

`runtime-wiring` must construct the complete `Experience` graph and render all seven surfaces without defining or loading any staging constant, function, plugin, adapter, capture JSON, CSP helper, hostname, or preview copy. It must assert that no path containing `/staging-safety/` appears in `get_included_files()`. Quote fixtures prove exact root `/contact-us/`, including when the current blog is `/wi/`; inactive/trashed form `1`, missing Gravity Forms renderer, missing contact renderer, `/wi/contact-us/`, and cross-origin actions all fail closed. `illinois-explicit-public` proves the package has an explicit future switch but is never run against production in this scope.

The harness must build the production manifest into a disposable exact MU-plugin topology and boot from root destination `wp-content/mu-plugins/twins-brand-experience-loader.php`, just as WordPress would. It must prove the root loader exists only in the production package, requires the subdirectory entry point by fixed relative path, and cannot be loaded from the staging package.

- [ ] **Step 2: Run RED production tests**

Run:

```bash
cd website/twins-brand-experience
node --test tests/contracts/production.test.cjs
npm run test:php
```

Expected: FAIL because production package files and harness behavior are absent.

- [ ] **Step 3: Implement explicit-enable loader and WordPress bridge**

The source file `production/twins-brand-experience-loader.php` is packaged only to root MU destination `twins-brand-experience-loader.php`; it contains only a fixed relative require of `twins-brand-experience/production/twins-brand-experience.php` and a fixed call to `twins_brand_production_register_hooks()`. It accepts no configurable path. The subdirectory production entry point must refuse to register hooks unless all of these are true:

```php
if (!defined('TWINS_BRAND_EXPERIENCE_ENABLED') || TWINS_BRAND_EXPERIENCE_ENABLED !== true) return;
if (!defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'production') throw new RuntimeException('Production environment identity is not exact.');
if (!defined('TWINS_HCP_BOOK_URL')) throw new RuntimeException('Booking configuration is missing.');
```

`twins_brand_production_build_runtime(): Experience` constructs only production adapters. `twins_brand_production_register_hooks(): void` registers the same five page families/body class/assets but does not reference any staging plugin, CSP, Basic Auth, preview copy, or Illinois path. The loader is built and tested only; do not install it on production.

By default, `ProductionRouteAdapter` derives the same HTTPS/userinfo-free base from `network_home_url('/')`, uses only the fixed WI/KY/main route map, and refuses `il-preview`. A future separately authorized deployment may define `TWINS_BRAND_ILLINOIS_PUBLIC` as exact boolean `true`; only then may the loader copy the fixed registry, set the Illinois entry `productionEnabled => true`, `preview => false`, and `label => 'Illinois'`, and enable the fixed `/il/` route map. No string/truthy value or caller path is accepted. The harness proves both default refusal and explicit-public output with no `preview` label.

Define `ProductionAssetResolver` with the same closed logical asset-key map as `StagingAssetResolver`, but derive its base only from `content_url('mu-plugins/twins-brand-experience')`. It rejects unknown keys and any base with non-HTTPS scheme, userinfo, query, fragment, or an origin different from `network_home_url('/')`.

`twins_brand_production_build_runtime()` must explicitly construct the complete graph in this order: fixed production route adapter; production asset resolver; fixed BRB parser/provider; Gravity Forms quote adapter injected with the route adapter and the bridge's fixed contact-renderer readiness probe; exact Housecall Pro booking adapter; production application adapter; gated production market registry; then `Experience($assets, $routes, $reviews, $quote, $booking, $applications, $markets, $core)`. The production provider reads no staging capture JSON. The enabled harness renders header, footer, home, team, careers, contact, and reviews through this exact graph.

- [ ] **Step 4: Implement exact Quote, Booking, and Careers adapters**

In `ProductionAdapters.php`, `GravityFormsQuoteAdapter` receives the fixed `ProductionRouteAdapter` and the production bridge's non-caller-selectable contact-renderer readiness probe. It never calls `home_url()`. Its internal destination is only `$routes->route('contact', 'main')`, which must parse as exact path `/contact-us/`, with no userinfo, query, or fragment, and the same normalized scheme, host, and effective port as `network_home_url('/')`.

`assertReady()` fails unless `GFAPI` exists; `GFAPI::get_form(1)` returns an array for exact ID `1`; `is_active` is `1`; `is_trash` is `0`; `gravity_form()` exists; the fixed contact-brand renderer is registered; and the exact root contact route passes the origin/path check. It renders form `1` with `echo=false`, verifies exactly one form, and accepts only an absent/empty action or the exact same-origin `/contact-us/` action. Any subsite-prefixed or external action fails closed. The validated render may be cached for that request so `renderExperience()` does not invoke Gravity Forms twice.

`action()` returns only `['mode' => 'internal', 'href' => $verifiedRootContactUrl]`. `renderExperience()` returns only the already validated Gravity Forms HTML. The adapter never creates, edits, activates, or submits a form.

The booking adapter remains exact:

```php
final class HousecallProBookingAdapter implements BookingAdapter {
    private const EXACT = 'https://book.housecallpro.com/book/Twins-Garage-Doors/26a3ce69028d4f018531ac62b1029d43?v2=true';
    public function assertReady(): void {
        if (!defined('TWINS_HCP_BOOK_URL') || TWINS_HCP_BOOK_URL !== self::EXACT) throw new RuntimeException('Booking URL mismatch.');
    }
    public function action(array $context): array { $this->assertReady(); return ['mode' => 'external', 'href' => self::EXACT, 'target' => '_blank', 'rel' => 'noopener noreferrer']; }
}
```

`EmploymentApplicationAdapter` returns a client contract containing only same-origin `/wp-json/twins/v1/employment-applications`, method `POST`, JSON field allowlist, validation/error codes, and nonce. Its readiness requires the exact REST route plus protected server-side `TWINS_GHL_PIT` and `TWINS_GHL_LOCATION_ID`; it never renders either value. Its production `renderExperience()` keeps the same accessible fields but assigns only the names defined in `employment-rest-route.json` and loads production transport separately.

- [ ] **Step 5: Build a complete verified BRB envelope and restore exactly one blog switch**

`BusinessReviewsBundleParser` receives the bounded captured provider HTML plus the fixed provider-contract fixture. It returns only the contract-locked `businessReviewsUrl` and normalized records. It rejects a missing/duplicate collection wrapper, relative date, malformed rating, fewer than five records, duplicate IDs, contract/output-fixture hash drift, or a record URL that merely repeats the business-level URL. `sourceRecordUrl` stays empty unless the provider exposes a distinct stable URL for that exact review.

`BusinessReviewsBundleProvider::collection()` resolves exactly one `/wi/` site with fixed bounded arguments, records the current blog ID, switches exactly once, verifies the installed BRB version against `brb-provider-contract.json`, renders only `[brb_collection id="2178"]`, and parses the result. It then constructs this production envelope:

```php
[
    'schemaVersion' => 1,
    'status' => 'verified',
    'sourceUrl' => 'https://twinsgaragedoors.com/wi/reviews/',
    'businessReviewsUrl' => $parsed['businessReviewsUrl'],
    'multisitePath' => '/wi/',
    'pageId' => 2186,
    'collectionId' => 2178,
    'capturedAt' => $now->format('Y-m-d\TH:i:s\Z'),
    'sourceResponseSha256' => hash('sha256', $html),
    'providerVersion' => $verifiedPluginVersion,
    'providerContractSha256' => $fixedContractSha256,
    'recordCount' => count($parsed['records']),
    'allowExternalSourceAction' => true,
    'records' => $parsed['records'],
]
```

Before return, require exact `status`, exact boolean `allowExternalSourceAction`, fixed provenance fields, contract-locked HTTPS business URL, matching record count/raw-response hash, and pass the whole envelope through `ReviewCodec::verifyCollection($envelope, $now)`. Any failure returns no partial collection and throws fail-closed.

Do not use a `while` restoration loop because it could pop a pre-existing multisite switch. Track whether this provider switched; in `finally`, call `restore_current_blog()` exactly once, require success, and require `get_current_blog_id()` to equal the recorded prior ID. Restoration failure is itself fatal. The harness begins from both root and an already-switched prior context and proves exact restoration after success, plugin drift, shortcode failure, parser failure, codec/envelope failure, and thrown exceptions. Missing or ambiguous `/wi/` resolution must fail before any switch. No staging safety rule is weakened to support this production-only fixture test.

- [ ] **Step 6: Run GREEN production isolation/readiness matrix**

Run:

```bash
cd website/twins-brand-experience
npm run test:contracts
npm run test:php
```

Expected: the disabled loader registers zero hooks; enabled fixture mode requires exact production identity and renders through the fully wired production-only graph; all asset keys resolve locally and unknown keys fail; Gravity Form `1` is active/non-trashed and uses exact same-origin root `/contact-us/`; every review record and the complete production envelope pass `ReviewCodec`; blog context is restored exactly once on every path; every missing/drift/restore case fails closed; no staging file/function/constant/provider is loaded; Illinois is absent by default and becomes non-preview only in the explicit-public fixture scenario; no test makes network, database, mail, lead, booking, or production writes.

- [ ] **Step 7: Commit the dormant production migration package**

```bash
git add website/twins-brand-experience/production website/twins-brand-experience/tests/contracts/production.test.cjs website/twins-brand-experience/tests/php/production-loader-harness.php website/twins-brand-experience/tests/fixtures/adapters website/twins-brand-experience/tests/php-harnesses.test.cjs
git commit -m "feat(web): add fixture-tested production adapters"
```

### Task 9: Implement the branded responsive CSS and all no-authority browser interactions

**Files:**
- Create: `website/twins-brand-experience/assets/css/twins-brand.css`
- Create: `website/twins-brand-experience/assets/js/twins-brand.js`
- Create: `website/twins-brand-experience/tests/contracts/styles-and-script.test.cjs`
- Create: `website/twins-brand-experience/tests/browser/fixture-server.mjs`
- Create: `website/twins-brand-experience/tests/browser/fixtures/brand-home.html`
- Create: `website/twins-brand-experience/tests/browser/interactions.spec.cjs`
- Create: `website/twins-brand-experience/playwright.config.cjs`

**Interfaces:**
- Consumes: `twins-brand-*` markup, fixed seven-width acceptance matrix, and staging structural no-authority rules.
- Produces: self-hosted font styling, Madison-derived navy/gold brand system, exact logo floors, Twin motion/reduced-motion, drawer/dropdown/dialog/slider/local-validation behavior, and reproducible local browser tests.

- [ ] **Step 1: Write RED source contracts for responsive/motion/accessibility behavior**

Create `tests/contracts/styles-and-script.test.cjs`:

```js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const root = path.resolve(__dirname, '../..');
const css = fs.readFileSync(path.join(root, 'assets/css/twins-brand.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/twins-brand.js'), 'utf8');

test('CSS pins fonts, logo floors, breakpoints, Twin motion, and reduced motion', () => {
  assert.match(css, /font-family:\s*['"]Lilita One['"]/);
  assert.match(css, /font-family:\s*['"]Nunito['"]/);
  assert.doesNotMatch(css, /fonts\.(googleapis|gstatic)\.com/);
  for (const token of ['--twins-logo-expanded: 204px', '--twins-logo-compressed: 180px', 'width: 190px', 'width: 176px', 'width: 154px', 'width: 148px', 'width: 140px']) assert.ok(css.includes(token), token);
  assert.match(css, /@keyframes twins-brand-float-left/);
  assert.match(css, /@keyframes twins-brand-float-right/);
  assert.match(css, /\.twins-brand-cta--book::after[\s\S]*content:\s*['"]→['"]/);
  assert.match(css, /linear-gradient/);
  assert.match(css, /\.twins-brand-cta:active/);
  assert.match(css, /\.twins-brand-mobile-proof[\s\S]*display:\s*none/);
  assert.match(css, /\.twins-brand-truck--hero[\s\S]*display:\s*none/);
  assert.match(css, /prefers-reduced-motion:\s*reduce/);
  assert.match(css, /animation:\s*none\s*!important/);
});

test('runtime script contains no transport, analytics, or external destination', () => {
  assert.doesNotMatch(js, /fetch\s*\(|XMLHttpRequest|sendBeacon|WebSocket|EventSource|\.submit\s*\(|requestSubmit|location\s*=|window\.open|gtag|dataLayer|fbq/i);
  for (const marker of ['Escape', 'visibilitychange', 'pointerdown', 'touchstart', 'focusin', 'aria-expanded']) assert.ok(js.includes(marker), marker);
});
```

- [ ] **Step 2: Run source contracts to prove RED**

Run:

```bash
cd website/twins-brand-experience
node --test tests/contracts/styles-and-script.test.cjs
```

Expected: FAIL because the branded stylesheet/runtime do not exist.

- [ ] **Step 3: Create the local no-network fixture and RED browser behavior tests**

Before writing the stylesheet or runtime, create `fixtures/brand-home.html` with the exact rendered component/template structure, local relative CSS/JS, and five records labeled `Fixture only — browser mechanics`; it is never packaged as runtime review data. `fixture-server.mjs` binds only `127.0.0.1`, serves files under the portable root after `realpath` containment checks, records every method/path, and rejects non-GET/HEAD with 405.

Create `interactions.spec.cjs` first. It must cover the drawer focus trap/close/restore/click-through, keyboard dropdowns, booking preview, slider buttons/keyboard/touch/pause, JS-disabled structural non-submission, visible focus, CTA pseudo-element/pressed states, seven-width computed contrast, deterministic desktop/mobile truck placement, and reduced-motion static Twins. Start with:

```js
const { test, expect } = require('@playwright/test');

test('drawer traps focus, closes, restores focus, and stops intercepting clicks', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/tests/browser/fixtures/brand-home.html');
  const menu = page.getByRole('button', { name: 'Menu' });
  await menu.click();
  await expect(page.locator('#twins-brand-drawer')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#twins-brand-drawer')).toBeHidden();
  await expect(menu).toBeFocused();
  await page.getByRole('link', { name: 'Call Twins' }).click({ trial: true });
});

test('staging previews make zero POST or external requests', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const requests = [];
  page.on('request', request => requests.push([request.method(), new URL(request.url()).origin]));
  await page.goto('/tests/browser/fixtures/brand-home.html');
  await page.getByRole('button', { name: 'Book Online' }).click();
  await page.getByRole('button', { name: 'Continue on staging' }).click();
  expect(requests.filter(([method]) => method !== 'GET')).toEqual([]);
  expect(requests.filter(([, origin]) => origin !== new URL(page.url()).origin)).toEqual([]);
});
```

Add a desktop dropdown test that explicitly sets `1440x1000`; drawer tests explicitly set `390x844`. Loop `[1440,1201,1024,768,390,360,320]` inside the contrast/logo/truck test so every width actually runs, sampling both initial and scrolled/compressed header states. Run `npm run test:browser` and record the expected RED behavior failures caused by the absent CSS/JS. A hidden-control mistake, fixture-server failure, or browser bootstrap failure does not count as the intended RED state and must be fixed before implementation.

- [ ] **Step 4: Implement the brand tokens, self-hosted fonts, layout, and exact logo floors**

Start `twins-brand.css` with:

```css
@font-face { font-family: 'Lilita One'; src: url('../fonts/lilita-one-regular.woff2') format('woff2'); font-weight: 400; font-style: normal; font-display: swap; }
@font-face { font-family: 'Nunito'; src: url('../fonts/nunito-variable.woff2') format('woff2'); font-weight: 200 1000; font-style: normal; font-display: swap; }

:root {
  --twins-navy-950: #071d3b;
  --twins-navy-900: #0b2a55;
  --twins-navy-800: #123a70;
  --twins-gold: #ffc83d;
  --twins-gold-strong: #ffb800;
  --twins-cream: #fff9ed;
  --twins-white: #ffffff;
  --twins-ink: #111827;
  --twins-logo-expanded: 204px;
  --twins-logo-compressed: 180px;
  --twins-shadow: 6px 7px 0 rgba(3, 18, 43, .55);
}

.twins-brand-experience { overflow-x: clip; font-family: 'Nunito', system-ui, sans-serif; color: var(--twins-ink); background: var(--twins-cream); }
.twins-brand-experience :where(h1,h2,h3,.twins-brand-kicker) { font-family: 'Lilita One', Impact, sans-serif; letter-spacing: .01em; }
.twins-brand-header { position: relative; z-index: 1000; background: var(--twins-navy-950); border-bottom: 4px solid var(--twins-gold); }
.twins-brand-fascia { min-height: 98px; display: grid; grid-template-columns: auto 1fr auto auto; align-items: center; gap: 18px; padding: 0 28px; }
.twins-brand-logo { width: var(--twins-logo-expanded); margin-bottom: -12px; z-index: 2; }
.twins-brand-logo img { display: block; width: 100%; height: auto; }
@media (min-width: 1201px) { .twins-brand-header[data-compressed="true"] .twins-brand-logo { width: var(--twins-logo-compressed); } }
.twins-brand-utility { min-height: 38px; display: flex; align-items: center; justify-content: flex-end; gap: 24px; padding: 4px 28px; background: var(--twins-gold); color: var(--twins-navy-950); font-weight: 800; }
.twins-brand-phone { min-height: 44px; display: inline-flex; align-items: center; padding: 6px 16px; border-radius: 999px; color: var(--twins-navy-950); background: var(--twins-gold); text-decoration: none; font-size: 1.05rem; font-weight: 900; }
.twins-brand-cta { min-height: 48px; display: inline-flex; align-items: center; justify-content: center; gap: 10px; padding: 11px 20px; border: 3px solid var(--twins-gold); border-radius: 999px; font-weight: 900; text-decoration: none; }
.twins-brand-cta--book { color: var(--twins-gold); background-color: var(--twins-navy-900); background-image: linear-gradient(115deg, transparent 34%, rgba(255,255,255,.16) 50%, transparent 66%); }
.twins-brand-cta--quote { color: var(--twins-navy-950); background-color: var(--twins-gold); background-image: linear-gradient(115deg, transparent 34%, rgba(255,255,255,.42) 50%, transparent 66%); }
.twins-brand-cta--book::after, .twins-brand-cta--quote::after { content: '→'; width: 1.75rem; height: 1.75rem; border: 2px solid currentColor; border-radius: 50%; display: inline-grid; place-items: center; line-height: 1; }
.twins-brand-cta--quote:focus-visible { outline: 3px solid var(--twins-navy-950); outline-offset: 3px; box-shadow: 0 0 0 6px var(--twins-white); }
.twins-brand-cta--book:focus-visible { outline: 3px solid var(--twins-white); outline-offset: 3px; box-shadow: 0 0 0 6px var(--twins-navy-950); }
.twins-brand-cta:active { transform: translateY(3px); box-shadow: 2px 2px 0 rgba(3,18,43,.72); }
.twins-brand-mobile-proof { display: none; }
```

Use min/max/clamp only where it does not reduce a locked floor. Add breakpoints with literal required widths:

```css
@media (max-width: 1200px) { .twins-brand-logo { width: 190px; } .twins-brand-primary-nav, .twins-brand-fascia > .twins-brand-cta { display: none; } .twins-brand-menu-trigger { display: inline-flex; } .twins-brand-truck--hero { display: none; } .twins-brand-mobile-proof { display: grid; } .twins-brand-twin { display: block; } }
@media (max-width: 768px) { .twins-brand-logo { width: 176px; } }
@media (max-width: 390px) { .twins-brand-logo { width: 154px; } }
@media (max-width: 360px) { .twins-brand-logo { width: 148px; } }
@media (max-width: 320px) { .twins-brand-logo { width: 140px; } }
```

At 1201 and above show complete desktop navigation/actions, the hero truck, and no mobile proof duplicate. At 1200 and below show the real menu button/drawer, hide only the hero truck, show the mobile proof truck immediately after the hero, and keep both Twins visible. Ensure every interactive control is at least 44x44, focus is visible, phone text is navy on gold or gold/white on navy, and mobile sticky actions add a matching body/footer safe-area inset.

The RED browser test must calculate WCAG relative luminance from computed foreground/background colors (including alpha compositing) at all seven widths and after scrolling the header at each width. Assert at least 4.5:1 for normal phone/nav/CTA/form text, at least 3:1 for large text and focus indicators, and the same thresholds in hover/focus/pressed states. Each two-color focus ring must provide at least 3:1 against both its control and the adjacent page/header background. Explicitly reject white text on the gold phone/quote background. It must also read the CTA `::after` content/shape, verify the sheen background, press each primary CTA to observe the nonzero translated state, verify focus is never indicated by color alone, and measure the logo against the locked initial/scrolled floor for that width.

- [ ] **Step 5: Implement Twin and CTA motion with strict reduced-motion overrides**

Use distinct loops:

```css
@keyframes twins-brand-float-left { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }
@keyframes twins-brand-float-right { 0%,100% { transform: translateY(0) rotate(-.75deg); } 50% { transform: translateY(-18px) rotate(.75deg); } }
@keyframes twins-brand-cta-pulse { 0%,82%,100% { box-shadow: var(--twins-shadow); } 88% { box-shadow: 0 0 0 6px rgba(255,200,61,.28), var(--twins-shadow); } }
.twins-brand-twin--left { animation: twins-brand-float-left 4.8s ease-in-out infinite; }
.twins-brand-twin--right { animation: twins-brand-float-right 6.5s ease-in-out .65s infinite; }
.twins-brand-cta--book, .twins-brand-cta--quote { animation: twins-brand-cta-pulse 8s ease-in-out infinite; }
.twins-brand-cta:is(:hover,:focus-visible,:active) { animation-play-state: paused; }
@media (prefers-reduced-motion: reduce) {
  .twins-brand-experience *, .twins-brand-experience *::before, .twins-brand-experience *::after { animation: none !important; scroll-behavior: auto !important; transition-duration: .01ms !important; }
  .twins-brand-twin { transform: none !important; }
}
```

At every mobile breakpoint both Twins fit above the fold or in the immediately visible hero art. The locked CSS hides `.twins-brand-truck--hero` and shows `.twins-brand-truck--mobile-proof` in the first proof panel immediately below the hero; it never hides either Twin. The browser matrix asserts the inverse desktop state and exact mobile state.

- [ ] **Step 6: Implement drawer, dropdown, dialog, slider, and local preview logic without transport**

In `twins-brand.js`, wrap code in an IIFE and implement these exact state helpers:

```js
const focusables = root => [...root.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')];
const setExpanded = (button, value) => button.setAttribute('aria-expanded', String(value));

function trapTab(event, root) {
  if (event.key !== 'Tab') return;
  const items = focusables(root);
  if (!items.length) return;
  const first = items[0], last = items[items.length - 1];
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
  if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}
```

Drawer open stores the trigger, removes `hidden`, sets `aria-hidden=false`, locks page scroll, sets trigger expanded, and focuses Close. Close by close button, Escape, or backdrop restores all attributes/body scroll and trigger focus. A closed overlay must have `hidden` and `pointer-events:none`.

Booking dialog uses the same focus/restore behavior, plus outside-pointer close. Preview final controls only reveal their local status text after local required-field checks; they never call a transport or native submission API.

Slider behavior:

- derive cards-per-page from `matchMedia('(min-width: 1200px)')` => 3, `min-width: 768px` => 2, otherwise 1;
- Prev/Next, generated dots, ArrowLeft/ArrowRight, and horizontal touch swipe update a translated track and roving dot state;
- 7-second auto-advance pauses during hover, `focusin`, active pointer/touch, document hidden, and reduced motion;
- no assertive live region is updated during automatic movement;
- resize recalculates page count and clamps current page.

Header compression must use scroll position only to set `data-compressed=true/false`; it must never reduce logo below 180 desktop pixels.

- [ ] **Step 7: Run GREEN source and local browser tests**

Use this Playwright config:

```js
const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({
  testDir: './tests/browser',
  timeout: 30000,
  use: { baseURL: process.env.TWINS_TEST_BASE_URL || 'http://127.0.0.1:41739', trace: 'retain-on-failure', screenshot: 'only-on-failure' },
  webServer: process.env.TWINS_TEST_BASE_URL ? undefined : { command: 'node tests/browser/fixture-server.mjs --port 41739', port: 41739, reuseExistingServer: false },
});
```

Run:

```bash
cd website/twins-brand-experience
npm run test:contracts
npm run test:browser
```

Expected: all source contracts and local interaction tests PASS; request ledger contains GET/HEAD to 127.0.0.1 only.

- [ ] **Step 8: Commit branded presentation and interactions**

```bash
git add website/twins-brand-experience/assets/css website/twins-brand-experience/assets/js website/twins-brand-experience/tests/contracts/styles-and-script.test.cjs website/twins-brand-experience/tests/browser website/twins-brand-experience/playwright.config.cjs
git commit -m "feat(web): add responsive Twins presentation and interactions"
```

### Task 10: Add deterministic runtime packages, repository checker, live browser matrix, and uncapped crawler

**Files:**
- Create: `website/twins-brand-experience/manifests/staging-runtime.json`
- Create: `website/twins-brand-experience/manifests/production-runtime.json`
- Create: `website/twins-brand-experience/manifests/host-verification.json`
- Create: `website/twins-brand-experience/tools/build-packages.mjs`
- Create: `website/twins-brand-experience/tools/check-repository.mjs`
- Create: `website/twins-brand-experience/tools/crawl-staging.mjs`
- Create: `website/twins-brand-experience/tools/deploy-private-staging.mjs`
- Create: `website/twins-brand-experience/tools/private-staging-deploy.php`
- Create: `website/twins-brand-experience/tests/contracts/package-contract.test.cjs`
- Create: `website/twins-brand-experience/tests/contracts/checker-contract.test.cjs`
- Create: `website/twins-brand-experience/tests/contracts/deployment-tool-contract.test.cjs`
- Create: `website/twins-brand-experience/tests/php/private-staging-deploy-harness.php`
- Modify: `website/twins-brand-experience/tests/php-harnesses.test.cjs`
- Create: `website/twins-brand-experience/tests/browser/live-homepage.spec.cjs`
- Create: `website/twins-brand-experience/tests/browser/live-routes.spec.cjs`
- Create: `website/twins-brand-experience/tests/browser/live-safety.spec.cjs`
- Modify: `website/twins-brand-experience/playwright.config.cjs`

**Interfaces:**
- Consumes: completed portable/staging/production trees and environment-only `TWINS_STAGE_URL`, `TWINS_STAGE_USER`, `TWINS_STAGE_PASSWORD` for live checks.
- Produces: exact regular-file package manifests/hashes, `npm run check:repo`, a seven-width authenticated browser suite, and a same-origin uncapped BFS crawler.

- [ ] **Step 1: Write RED package and checker contracts**

Create `package-contract.test.cjs`:

```js
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const root = path.resolve(__dirname, '../..');

test('staging and production runtime manifests separate deploy files from verified prerequisites', () => {
  const staging = JSON.parse(fs.readFileSync(path.join(root, 'manifests/staging-runtime.json'), 'utf8'));
  const production = JSON.parse(fs.readFileSync(path.join(root, 'manifests/production-runtime.json'), 'utf8'));
  const verification = JSON.parse(fs.readFileSync(path.join(root, 'manifests/host-verification.json'), 'utf8'));

  for (const manifest of [staging, production]) {
    assert.equal(manifest.schemaVersion, 1);
    assert.equal(manifest.productionWriteAuthority, false);
    for (const file of manifest.files) {
      assert.match(file.role, /^(deploy|verify-prerequisite)$/);
      assert.match(file.source, /^(twins-brand-experience|staging-safety)\//);
      assert.doesNotMatch(file.source, /(^|\/)\.\.?(\/|$)/);
      assert.doesNotMatch(file.destination, /(^|\/)\.\.?(\/|$)/);
      assert.match(file.sha256, /^[a-f0-9]{64}$/);
    }
  }

  const deploy = new Set(staging.files.filter(file => file.role === 'deploy').map(file => file.destination));
  const prerequisites = new Set(staging.files.filter(file => file.role === 'verify-prerequisite').map(file => file.destination));

  assert.equal([...deploy].some(file => file.includes('/production/')), false);
  assert.equal([...deploy].some(file => /twins-brand-production\.js$/.test(file)), false);
  assert.equal(deploy.has('twins-brand-experience/bootstrap.php'), true);
  assert.equal(deploy.has('twins-brand-experience/assets/images/door-builder/twins-before-after-install.webp'), true);
  assert.equal([...deploy].some(file => /twins-before-after-install-source\.png$/.test(file)), false);
  assert.equal(deploy.has('twins-staging-overhaul/brand-runtime.php'), true);
  assert.equal(deploy.has('twins-staging-overhaul/adapters/BrandStagingAdapters.php'), true);
  assert.equal(deploy.has('twins-staging-overhaul/adapters/BrandStagingPreviews.php'), true);
  assert.equal(deploy.has('twins-staging-overhaul.php'), false);
  assert.equal(deploy.has('twins-brand-experience-loader.php'), false);
  assert.equal(prerequisites.has('twins-staging-overhaul.php'), true);
  assert.equal(prerequisites.has('twins-staging-safety.php'), true);
  assert.equal([...prerequisites].some(file => file.startsWith('twins-staging-assets/')), true);
  assert.equal(production.files.some(file => file.role === 'deploy' && file.destination === 'twins-brand-experience/production/assets/twins-brand-production.js'), true);
  assert.equal(production.files.some(file => /twins-before-after-install-source\.png$/.test(file.destination)), false);
  assert.equal(production.files.some(file => file.destination === 'twins-brand-experience/assets/images/door-builder/twins-before-after-install.webp'), true);
  assert.equal(production.files.some(file => file.role === 'deploy' && file.source === 'twins-brand-experience/production/twins-brand-experience-loader.php' && file.destination === 'twins-brand-experience-loader.php'), true);
  assert.equal(verification.schemaVersion, 1);
  assert.equal(verification.productionWriteAuthority, false);
  assert.equal(verification.files.every(file => !Object.hasOwn(file, 'destination')), true);
  assert.equal(verification.files.some(file => file.source.endsWith('private-staging-deploy-harness.php')), true);
  for (const file of verification.files) {
    assert.match(file.source, /^(twins-brand-experience|staging-safety)\//);
    assert.match(file.sha256, /^[a-f0-9]{64}$/);
    const bytes = fs.readFileSync(path.resolve(root, '..', file.source));
    assert.equal(bytes.length, file.size);
    assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), file.sha256);
  }
  assert.equal(staging.files.some(file => /\/production\/|\/tests\//.test(file.source)), false);
  assert.equal(production.files.some(file => /\/tests\//.test(file.source)), false);
  assert.equal([...staging.files, ...production.files].some(file => /private-staging-deploy-harness|host-verification/.test(file.destination)), false);
});
```

Create `checker-contract.test.cjs` that runs `node tools/check-repository.mjs --self-test` and asserts its JSON output includes every gate name and `writeAuthority:false`.

Create `deployment-tool-contract.test.cjs` and `private-staging-deploy-harness.php` before the deployment tool. They must prove:

- the only accepted operations are `--dry-run`, `--capture-expected-old`, `--deploy`, and `--rollback`; there is no caller hostname, web-root, destination, manifest, expected-old, or retry argument;
- the canonical web root is exactly `/home/customer/www/danielj140.sg-host.com/public_html`, the fixed destinations are its two MU-plugin directories, and the fixed application identity is `https://danielj140.sg-host.com/` with exact staging environment;
- transport-only `TWINS_STAGE_SSH_TARGET`, `TWINS_STAGE_SSH_KEY`, and `TWINS_STAGE_SSH_HOSTKEY_SHA256` never alter application identity or destination and are never printed; the first dry-run pins the owner-session-approved target identity hash plus an independently authenticated server host-key fingerprint, and every later operation rejects a changed target or fingerprint;
- a disposable local fake remote filesystem exercises wrong transport target, host-key drift, absent-core install, expected-old overhaul replacement, prerequisite drift, unexpected existing core, isolated-core-boot failure, overhaul activation failure, rollback to absent-core state, symlink/non-regular rejection, and package/hash drift;
- every failure stops without retry; the source contains no loop around upload/rename, no production hostname/path, no DNS/database/config mutation, and every JSON result reports `writeAuthority:false` and `productionWriteAuthority:false`.

Register `private-staging-deploy-harness.php` in `tests/php-harnesses.test.cjs` with fixed scenarios `dry-run`, `absent-core-install`, `prerequisite-drift`, `unexpected-core`, `core-boot-failure`, `activation-failure`, `rollback-absent-core`, and `non-regular-rejected`. Each wrapper invocation uses only a harness-created disposable root and fixed fixture manifest; no network or real staging path is available to the harness.

- [ ] **Step 2: Run RED packaging tests**

Run:

```bash
cd website/twins-brand-experience
node --test tests/contracts/package-contract.test.cjs tests/contracts/checker-contract.test.cjs tests/contracts/deployment-tool-contract.test.cjs
npm run test:php
```

Expected: FAIL because manifests/build/check/deploy tools and the deploy harness implementation do not exist. A local PHP skip is permitted only on the documented PHP-less workstation; CI/SiteGround must observe the RED harness before implementation.

- [ ] **Step 3: Implement closed regular-file manifests and deterministic package checks**

`staging-runtime.json` is the complete closed staging release manifest. Every regular-file entry has `role`, `source`, `destination`, `size`, and `sha256`. `role` is exactly `deploy` or `verify-prerequisite`.

`deploy` contains only the complete portable `twins-brand-experience/` runtime and complete `twins-staging-overhaul/` implementation directory. It includes the cropped door-builder WebP but explicitly excludes the docs-only full creative source with campaign copy. `verify-prerequisite` contains the unchanged root `twins-staging-overhaul.php`, root `twins-staging-safety.php`, and every bounded regular file in the existing `twins-staging-assets/` tree. Prerequisites are hash-verified against repository and live staging but are never copied, replaced, archived as candidate payload, or granted write authority.

`production-runtime.json` uses the same schema, sets `productionWriteAuthority:false`, includes the portable renderer/assets plus `production/`, and maps source `production/twins-brand-experience-loader.php` to the root MU-plugin destination `twins-brand-experience-loader.php`. It excludes every staging bridge/safety/preview adapter, review capture JSON, raw fixture, tools/tests, and docs references. It identifies only a dormant future production package. It contains the exact-boolean `TWINS_BRAND_ILLINOIS_PUBLIC` gate but ships with no enabling configuration; default production harness output excludes Illinois. Building it neither grants nor exercises production deployment authority.

`host-verification.json` is a third closed, hash-pinned, non-runtime bundle. It contains only the PHP harnesses, their fixed fixtures, portable/production source needed by those harnesses, and staging loader/safety source needed for isolated checks. It builds at `dist/host-verification/`, has no `wp-content` destination, is uploaded only to `/home/customer/staging-safety/brand-wide-20260715/verification/` outside web root, is never included by WordPress, and is deleted after bounded results are recorded. Its canonical hash is separate from both runtime packages.

`build-packages.mjs` accepts only no argument or `--check`. With no argument it builds regular-file trees at `dist/staging-runtime/wp-content/mu-plugins/`, `dist/production-runtime/wp-content/mu-plugins/`, and `dist/host-verification/`; check mode creates no files and byte-verifies those already-built trees against current sources. It validates every runtime manifest entry but copies only `role:"deploy"` entries into runtime outputs. It writes manifests outside deployable `wp-content` trees as metadata. The canonical deploy-package hash is derived only from sorted deploy entries; a separate canonical prerequisite-set hash is derived from sorted verify-prerequisite entries; the verification bundle has its own canonical hash. The builder rejects any set drifting, rejects prerequisites/tests in runtime output, rejects runtime output in the verification bundle, and never uploads or deploys.

Package creation resolves every source beneath the fixed repository `website/` directory and every destination beneath the fixed package root; rejects duplicates, traversal, absolute paths, symlinks, non-regular modes, size-bound violations, undeclared output files, and source/copy hash drift; sorts paths bytewise; copies with mode `0644`; and hashes length-prefixed path, decimal size, and SHA-256 fields with no filesystem timestamp. Staging forbidden-byte scans cover HCP/GHL/production transport/network primitives; production scans cover staging hostname, Basic Auth, safety constants, disabled-integration copy, and staging CSP. The inert `il-preview` registry record is allowed only because executable production harnesses prove it cannot render or route by default. Every discrepancy fails closed.

Implement the deployment pair as a tested fixed-target state machine, not ad hoc shell commands. `deploy-private-staging.mjs` accepts exactly one of `--dry-run`, `--capture-expected-old`, `--deploy`, or `--rollback`; reads only the freshly built staging package, host-verification bundle, and checked manifests at fixed repository paths; and uses `TWINS_STAGE_SSH_TARGET`/`TWINS_STAGE_SSH_KEY` solely as task-scoped transport. The owner-session preflight also supplies exact `TWINS_STAGE_SSH_HOSTKEY_SHA256` obtained through an authenticated SiteGround-controlled channel (dashboard if actually available, otherwise SiteGround support). If no independent fingerprint can be obtained, stop before SSH; trust-on-first-use is prohibited. `--dry-run` verifies that fingerprint with strict host-key checking, creates the fixed transaction directory, and stores only a SHA-256 of canonical `user@host` plus the public server fingerprint; every later operation requires both values unchanged. `--capture-expected-old` reuses and re-verifies those exact remote bytes and uploads no candidate; `--deploy` uploads the deploy tree and host-verification bundle exactly once, then activates the already-reviewed transaction; `--rollback` uploads nothing. It uses argument arrays with `shell:false`, a task-owned known-hosts file, bounded output, no credential/target logging, and no automatic retry. A transport endpoint is never accepted as application identity: the remote fixed-root/home/environment checks remain mandatory.

`private-staging-deploy.php` is CLI-only and fixes the application root to `/home/customer/www/danielj140.sg-host.com/public_html`. Before every mutating operation it proves the WordPress home URL is exact `https://danielj140.sg-host.com/`, `WP_ENVIRONMENT_TYPE` is exact `staging`, the two destination paths remain beneath the fixed MU-plugin directory, package/manifest hashes match, every entry is a bounded regular file, and all `verify-prerequisite` hashes match. `--dry-run` performs every check but no archive/upload rename. `--capture-expected-old` creates the mode-600 fixed rollback archive and secret-free expected-old manifest. `--deploy` requires that captured file unchanged and executes the guarded order in Task 11. `--rollback` requires the captured manifest/archive and restores exactly the recorded present/absent states. Each operation writes one bounded JSON result with `writeAuthority:false`, `productionWriteAuthority:false`, operation, hashes, and state; never environment values.

The disposable PHP harness supplies a temporary fake root through an internal test-only constructor/function boundary that is unavailable from the production CLI entry point. It proves the full state machine, including failed activation recovery and deletion of a newly installed core when expected-old recorded it absent. The deploy entry point cannot accept that override. Run this harness on CI/SiteGround because local PHP may be unavailable.

- [ ] **Step 4: Implement the canonical repository checker**

`check-repository.mjs` runs, in order, without shell interpolation:

```js
const gates = [
  ['legacy-node', process.execPath, ['--test', '../staging-safety/tests/*.test.cjs']],
  ['brand-contracts', 'npm', ['run', 'test:contracts']],
  ['brand-php', 'npm', ['run', 'test:php']],
  ['owned-assets', 'npm', ['run', 'check:assets']],
  ['runtime-packages', 'npm', ['run', 'check:packages']],
];
```

Resolve globbing in Node rather than passing `*` to a shell; spawn with `shell:false`. In CI, PHP skip is failure. Emit one final JSON object:

```json
{
  "status": "REPOSITORY_CHECK_PASSED",
  "writeAuthority": false,
  "productionWriteAuthority": false,
  "gates": ["legacy-node", "brand-contracts", "brand-php", "owned-assets", "runtime-packages"]
}
```

Any failure exits 1 and reports only the failed gate plus bounded stdout/stderr; never print environment values.

- [ ] **Step 5: Write the live authenticated browser matrix before deployment**

Parameterize widths `[1440,1201,1024,768,390,360,320]`. Require all three environment values before live projects run; never store/log credentials. Playwright `httpCredentials` receives them from `process.env`.

For each width, `live-homepage.spec.cjs` must assert:

- successful authenticated load, `X-Robots-Tag` safety header, no console/page errors;
- exact root H1/CTA copy, full desktop nav or mobile drawer as appropriate;
- measured logo floor and unobscured center point;
- computed WCAG contrast for phone, nav, CTAs, preview fields, focus, hover, and pressed states; ordinary text is at least 4.5:1, large/focus graphics at least 3:1, and white-on-gold is forbidden;
- every visible button/link center point resolves to itself/descendant and trial click succeeds;
- two Twin elements visible; 100-ms transform samples for seven seconds span at least 12 vertical pixels for each;
- reduced-motion context keeps both Twins visible with stable bounds and no animation;
- desktop shows the hero truck and no mobile-proof duplicate; every mobile width hides the hero truck and shows the proof truck immediately after the hero while both Twins remain visible;
- real team section is outside `<details>`, image MIME/dimensions/alt/srcset are valid;
- slider has a valid heading relationship and visible Google attribution, preserves exact verified author/text/rating/date, exposes no external Google action on staging, and reaches at least five stable IDs with Prev/Next/keyboard/touch and correct 3/2/1 cards per width;
- no horizontal overflow and all touch controls meet 44x44 at mobile widths.

`live-routes.spec.cjs` checks `/our-team/`, `/careers/`, `/contact-us/`, `/reviews/`, legacy Madison LP, WI cost page, door builder, and WI/KY/IL-preview routes. It verifies exact real-image requirements, shared nav/footer/CTA, functional internal links, and no generic preserved page for the four rebuilt routes.

`live-safety.spec.cjs` records every request/response while exercising quote/application/booking with JS enabled and disabled. It fails on any POST, external request, production hostname, mail/CRM/SMS/booking/analytics host, non-GET/HEAD method, unauthenticated request, or live HCP anchor. For HTML documents it additionally requires noindex/CSP/canonical safety and valid hash-link targets. For CSS/JS/images/fonts it requires successful bounded response, expected MIME, no external redirect, and exact size/SHA-256 when the asset is declared in a runtime or prerequisite manifest; it does not incorrectly demand document CSP/noindex headers on static assets.

Tests first run against the current private staging bytes and are expected to fail on the known old header/home/team/reviews gaps. They are not considered green until Task 11 deploys the exact candidate package.

- [ ] **Step 6: Implement an authenticated same-origin uncapped crawler**

`crawl-staging.mjs` must:

- require `TWINS_STAGE_URL` to equal exactly `https://danielj140.sg-host.com/` and require env-only Basic Auth values;
- use a queue/set BFS with no page or asset count cap;
- normalize only same-origin HTTP(S) links, strip fragments, and reject unexpected production/external navigation;
- follow internal pages/assets to completion, including CSS `url()` and `srcset` candidates;
- apply Basic Auth to every request, use a fixed 15-second per-request deadline, permit at most three same-origin redirect hops, reject any external redirect, and stream with a hard 5 MiB HTML/CSS/JS/font bound and 32 MiB image bound while rejecting any declared `Content-Length` that disagrees with received bytes;
- for HTML documents, reject 4xx/5xx, non-HTML MIME, missing noindex/CSP, broken hash links, duplicate/foreign canonical routes, or unauthenticated access; for static assets, reject non-success status, wrong MIME, over-limit bytes, or size/hash drift from runtime/prerequisite manifests, and record SHA-256 for any fixed baseline WordPress asset not owned by those manifests;
- never submit forms, click CTAs, send non-GET/HEAD, or consult robots as crawl authority;
- write only `artifacts/crawl-report.json` with counts, sorted visited URLs, violations, timestamps, branch/commit/package hashes, and `writeAuthority:false`.

Add fixture-mode tests using the local server for: a graph with 250 linked pages to prove there is no hidden count cap; a hanging response; four-hop redirect; external redirect; oversized/chunked HTML; oversized image; false content length; HTML missing safety headers; and a valid static asset without document-only headers. Every resource failure must terminate within the fixed timeout and return a bounded violation rather than hang or exhaust memory.

- [ ] **Step 7: Run GREEN deterministic checks and local crawler/browser mechanics**

Run before live deployment:

```bash
cd website/twins-brand-experience
npm run build:packages
npm run check:packages
npm run check:repo
npm run test:browser
TWINS_CRAWL_FIXTURE=1 npm run test:crawl
```

Expected: deterministic packages are freshly materialized, their copied trees and hashes pass check mode, and checker/local browser/250-page fixture crawl PASS. Live projects remain excluded unless all live env values are present.

- [ ] **Step 8: Commit release tooling and acceptance tests**

```bash
git add website/twins-brand-experience/manifests website/twins-brand-experience/tools/build-packages.mjs website/twins-brand-experience/tools/check-repository.mjs website/twins-brand-experience/tools/crawl-staging.mjs website/twins-brand-experience/tools/deploy-private-staging.mjs website/twins-brand-experience/tools/private-staging-deploy.php website/twins-brand-experience/tests/contracts/package-contract.test.cjs website/twins-brand-experience/tests/contracts/checker-contract.test.cjs website/twins-brand-experience/tests/contracts/deployment-tool-contract.test.cjs website/twins-brand-experience/tests/php/private-staging-deploy-harness.php website/twins-brand-experience/tests/php-harnesses.test.cjs website/twins-brand-experience/tests/browser/live-*.spec.cjs website/twins-brand-experience/playwright.config.cjs
git commit -m "test(web): add staging release and browser gates"
```

### Task 11: Deploy the exact candidate to private staging and prove every live gate

**Files:**
- Modify: `website/staging-safety/README.md`
- Create: `docs/handoff/2026-07-15-brand-wide-private-staging.md`
- Create: `website/twins-brand-experience/artifacts/browser-summary.json`
- Create: `website/twins-brand-experience/artifacts/crawl-report.json`
- Create: `website/twins-brand-experience/artifacts/deployment-manifest.json`

**Interfaces:**
- Consumes: exact staging runtime manifest/package, current private staging safety gates, SiteGround owner session, env-only Basic Auth, and staging-only temporary SSH access.
- Produces: byte-identical regular files on private staging, fresh rollback archive, passing host PHP/live browser/crawl evidence, purged staging cache, removed temporary access, and a migration-safe handoff.

- [ ] **Step 1: Run the full pre-deployment repository gate**

Run from the portable package directory:

```bash
npm run build:packages
npm run check:packages
npm run check:repo
npm run test:browser
TWINS_CRAWL_FIXTURE=1 npm run test:crawl
git status --short
```

Expected: a fresh candidate exists at the fixed `dist/staging-runtime/` path, its copied tree and canonical hash pass check mode, all repository/local gates PASS, and the working tree contains only reviewed task files; no credential, private key, generated node_modules, trace, or unmanifested package output is staged.

- [ ] **Step 2: Verify private staging safety before any mutation**

Using the authenticated owner browser/temporary task-scoped access, verify:

```text
Target: https://danielj140.sg-host.com/
Unauthenticated response: 401
Authenticated response: 200
X-Robots-Tag: contains noindex, nofollow, noarchive
WP_ENVIRONMENT_TYPE: staging
TWINS_STAGING_SAFETY: true
DISABLE_WP_CRON: true
Host egress: default deny
Hosting cron: disabled
Root safety MU set: existing exact files present
Production write authority: false
```

If any line cannot be proven, stop without deploying. Do not weaken a WordPress or host gate.

From the authenticated SiteGround owner dashboard, create or select only the task-scoped staging SSH access and record the exact displayed SSH account/host. Obtain the SHA-256 server host-key fingerprint through an authenticated SiteGround-controlled channel—use the dashboard only if it actually displays the fingerprint, otherwise obtain it from SiteGround support. Export these as `TWINS_STAGE_SSH_TARGET`, `TWINS_STAGE_SSH_KEY`, and `TWINS_STAGE_SSH_HOSTKEY_SHA256` without printing them. If an independent fingerprint is unavailable, stop before SSH. The first deployment dry-run stores only the target identity hash plus public host-key fingerprint in the remote transaction; subsequent operations reject any drift. Never use `StrictHostKeyChecking=no`, accept a first-seen key, reuse a production account, or commit the target/key/known-hosts file.

Set Basic Auth values in the local process without echoing them, then run the live specifications against the current staging bytes before mutation:

```zsh
export TWINS_STAGE_URL='https://danielj140.sg-host.com/'
read "TWINS_STAGE_USER?Staging username: "
read -s "TWINS_STAGE_PASSWORD?Staging password: "
print
export TWINS_STAGE_USER TWINS_STAGE_PASSWORD
cd website/twins-brand-experience
TWINS_LIVE=1 npm run test:browser
```

Record the expected RED assertions for the known old header/home/team/review gaps. The safety/auth/header/no-side-effect assertions must already pass; a credential, network, test-bootstrap, or safety-gate failure is not the intended RED state and blocks deployment. Keep the credentials only in this process through Step 8 and never write them to a command, file, artifact, shell history, screenshot, or Git.

The current workstation-only `/etc/hosts` mapping is `35.215.94.137 danielj140.sg-host.com`. Before relying on it, compare that address read-only with the current SiteGround staging destination. If it drifted, do not alter public DNS; update only the local hosts mapping after explicit system approval, then re-prove TLS hostname validation and the unauthenticated 401 boundary.

- [ ] **Step 3: Create a fresh staging-only rollback archive and exact expected-old manifest**

Create a mode-600 rollback archive outside web root containing the current live `twins-staging-overhaul/` directory and, if present, the current `twins-brand-experience/` directory. Record explicit absence when a destination does not exist. Capture a canonical tree hash plus `lstat`, path, size, mode, and SHA-256 for every old regular file. Reject symlinks, non-regular files, unexpected roots, or paths escaping the fixed staging MU-plugin directory.

Independently hash every live `verify-prerequisite` path and require exact agreement with the checked staging manifest. The deployment engine accepts no caller-selected hostname, WordPress root, destination, manifest, or expected-old value; it must prove the canonical target is the private staging installation and `WP_ENVIRONMENT_TYPE` is exactly `staging`. Any production path or identity aborts before mutation.

Run the tested fixed-target operations exactly once each:

```bash
node tools/deploy-private-staging.mjs --dry-run
node tools/deploy-private-staging.mjs --capture-expected-old
```

The engine derives all values from the freshly checked repository package and live staging state:

```js
const deploymentManifest = {
  schemaVersion: 1,
  target: 'private-staging-only',
  host: 'danielj140.sg-host.com',
  leaseAccount: 'CHATGPT_PROFILE_1',
  writeAuthority: false,
  productionWriteAuthority: false,
  candidateCommit,
  transportIdentitySha256,
  sshHostKeyFingerprint,
  stagingManifestSha256,
  deployPackageSha256,
  prerequisiteSetSha256,
  expectedOld: {
    core: expectedOldCore,
    overhaul: expectedOldOverhaul,
    prerequisites: verifiedPrerequisites
  },
  expectedOldArchive: {
    path: '/home/customer/staging-safety/before-brand-wide-20260715.tar.gz',
    sha256: archiveSha256,
    mode: '0600'
  },
  candidateFiles: deployFiles
};
```

Immediately before each rename, recompute the relevant expected-old tree/absence state and all prerequisite hashes. Any drift or indeterminate result stops deployment. Do not recalculate an expected value from the drifted state, retry automatically, or continue with partial evidence.

- [ ] **Step 4: Copy the exact regular-file candidate once**

Upload only `role:"deploy"` entries once into the fixed task-scoped sibling directories outside web root but on the same verified filesystem. Prove device identity, regular-file status, closed contents, individual hashes, and canonical deploy-package hash. Never upload a `verify-prerequisite` entry.

Run exactly once:

```bash
node tools/deploy-private-staging.mjs --deploy
```

The tested engine activates in this fixed order:

1. Re-prove every prerequisite and expected-old state.
2. Install `twins-brand-experience/` first. This is expected to be a first install: require the destination to remain absent, then rename the verified candidate directory into place with one same-filesystem rename. If it unexpectedly exists, stop for review rather than overwriting it.
3. Verify the installed core tree byte-for-byte and run its isolated boot harness. The still-live old overhaul bridge does not activate the new experience.
4. Stage and verify the complete candidate `twins-staging-overhaul/` directory. Re-prove the live overhaul expected-old tree and every prerequisite immediately before activation.
5. Move the exact old overhaul directory to its rollback sibling, then rename the candidate overhaul directory into the live path. The candidate-to-live rename is the single overhaul activation point. The backup move and activation rename are separate guarded operations; do not claim that this pair, or the core and overhaul installation together, is atomic. If activation rename fails, restore the exact old overhaul directory immediately and stop without retrying the candidate.
6. Verify both live deploy trees against the deploy manifest and prove prerequisites remained unchanged. Leave the root loaders, safety plugin, legacy assets, configuration, database, DNS, production files, and integrations untouched.

On any failure after either live destination has been mutated—including core tree verification, isolated core boot, candidate overhaul staging, backup move, activation, or post-activation verification—run `node tools/deploy-private-staging.mjs --rollback` once. The engine rolls back in reverse dependency order: restore and verify the exact old overhaul when it was moved; remove the newly installed core to restore its recorded expected-absent state; purge only staging cache; and re-prove old tree hashes, prerequisites, Basic Auth, noindex/CSP, and staging identity. The fixed `core-boot-failure` harness proves the old overhaul remains live and the new core is removed. Retain the mode-600 rollback archive and record the failed deployment. Never touch production or automatically redeploy.

The overhaul candidate contains every preserved legacy template byte from the single checked manifest, not only changed files. Do not deploy `production/`, tools, tests, docs references, raw review HTML, production JS, credentials, root loaders, the safety plugin, or legacy asset prerequisites.

- [ ] **Step 5: Run every PHP and safety harness on the isolated staging host**

The single `--deploy` upload placed the separately hashed host-verification bundle at `/home/customer/staging-safety/brand-wide-20260715/verification/` outside web root. Recompute its canonical hash and compare it with `host-verification.json`; require every entry to be a regular mode-`0444` or `0644` file and prove no path under it is included by WordPress. Then run exact repository harnesses from that fixed bundle root:

```bash
cd /home/customer/staging-safety/brand-wide-20260715/verification
php website/twins-brand-experience/tests/php/portable-core-harness.php website/twins-brand-experience/bootstrap.php
php website/twins-brand-experience/tests/php/renderer-contract-harness.php website/twins-brand-experience/bootstrap.php
php website/twins-brand-experience/tests/php/review-codec-harness.php website/twins-brand-experience/bootstrap.php valid
php website/twins-brand-experience/tests/php/production-loader-harness.php website/twins-brand-experience/production/twins-brand-experience-loader.php boot-disabled
php website/twins-brand-experience/tests/php/private-staging-deploy-harness.php dry-run
php website/staging-safety/tests/staging-overhaul-foundation-harness.php website/staging-safety/mu-plugins/twins-staging-overhaul.php
php website/staging-safety/tests/staging-overhaul-harness.php website/staging-safety/mu-plugins/twins-staging-overhaul.php
php website/staging-safety/tests/staging-overhaul-builder-harness.php website/staging-safety/mu-plugins/twins-staging-overhaul
php website/staging-safety/tests/staging-overhaul-cost-harness.php website/staging-safety/mu-plugins/twins-staging-overhaul
php website/staging-safety/tests/staging-brand-adapters-harness.php website/staging-safety/mu-plugins/twins-staging-overhaul.php website/twins-brand-experience
php website/staging-safety/tests/wordpress-harness.php website/staging-safety/mu-plugins/twins-staging-safety.php
```

Also run all bootstrap/renderer/deployment scenario loops through the Node wrappers with `CI=1`. Expected: zero skips, zero failures. After recording bounded results, delete the exact fixed verification directory and verifier source while retaining only the transaction manifest/rollback archive needed through final review; prove nothing from the verification bundle exists under web root. Runtime packages remain exact.

- [ ] **Step 6: Purge staging cache and run the complete authenticated live matrix**

Using the still-exported process-only credentials from Step 2, purge only SiteGround private-staging dynamic cache. Then run:

```bash
cd website/twins-brand-experience
TWINS_LIVE=1 npm run test:browser
npm run test:crawl
```

Expected:

```text
All seven viewport projects: PASS
All homepage interaction/motion/reduced-motion checks: PASS
Our Team/Careers/Contact/Reviews journeys: PASS
Zero POST/non-GET side effects: PASS
Zero external/production requests: PASS
Uncapped authenticated crawl: zero violations
Safety headers/CSP/Basic Auth: PASS
```

Write bounded, secret-free summaries to `artifacts/browser-summary.json` and `artifacts/crawl-report.json`; screenshots/traces are retained only for failures and must contain no entered personal data.

- [ ] **Step 7: Perform owner-visible visual review at desktop and mobile**

Open authenticated staging at `/`, `/our-team/`, `/careers/`, `/contact-us/`, and `/reviews/`. Confirm against the approved Madison reference language:

- materially larger crossing logo, readable phone, full nav, prominent Book Online/Request a Quote;
- both Twins visible and subtly moving on desktop/mobile;
- real crew/fleet, Tal, and technician imagery is crisp and honest;
- branded review slider shows real verified provider text;
- no dead menu/button, generic preserved surface, closed legacy disclosure, stock team portrait, or old CTA copy;
- 320/390 mobile has no overlap, clipping, hidden Twins, or content covered by sticky actions.

Any visible defect reopens the relevant TDD task; do not document a known visual failure as complete.

- [ ] **Step 8: Remove temporary access and re-prove privacy**

Remove every task-created SSH key/user/token from SiteGround and the local machine. Verify the removed key is rejected. Unset `TWINS_STAGE_USER` and `TWINS_STAGE_PASSWORD`. Confirm unauthenticated staging still returns 401, authenticated owner access returns 200, host egress remains default-deny, and production remains unchanged.

- [ ] **Step 9: Update README and create the migration-safe handoff**

Change `website/staging-safety/README.md` status to `BRAND_WIDE_STAGING_READY_FOR_OWNER_REVIEW` only after all live gates pass. Create `docs/handoff/2026-07-15-brand-wide-private-staging.md` following the existing handoff structure and record:

1. exact branch/commit/package/core/staging-loader/production-loader/review-capture/asset versions and hashes;
2. private staging review URL/routes and Basic Auth custody statement without credentials;
3. repository/PHP/browser/crawl counts and artifact paths;
4. exact fresh staging rollback archive/mode/hash and rollback commands;
5. staging-only exclusions: safety MU entry points/constants, preview adapters, Illinois preview, Basic Auth, safety CSP, disabled-integration copy;
6. production readiness contracts for URL/markets/phones/GF1/exact HCP/employment REST/protected config/BRB collection 2178/provider version/parser fixture/schema ownership;
7. proof production harness has no staging dependency or Illinois exposure;
8. production migration smoke/rollback checklist, including a no-real-lead dry run and separately authorized real end-to-end test;
9. `productionWriteAuthority:false` and explicit statement that no production deployment occurred.

If `docs/handoff/HANDOFF-TEMPLATE.md` exists on the then-current branch, use its headings verbatim; otherwise preserve every field above and the current repository handoff format.

- [ ] **Step 10: Commit the proven staging evidence**

```bash
git add website/staging-safety/README.md docs/handoff/2026-07-15-brand-wide-private-staging.md website/twins-brand-experience/artifacts/browser-summary.json website/twins-brand-experience/artifacts/crawl-report.json website/twins-brand-experience/artifacts/deployment-manifest.json
git commit -m "docs(web): record verified brand-wide private staging"
```

### Task 12: Final verification, independent review, and branch publication

**Files:**
- Modify only files required by a failing final gate or actionable review finding.
- Review: every file in `git diff origin/codex/staging-site-safety...HEAD`.

**Interfaces:**
- Consumes: all completed tasks and live evidence.
- Produces: one clean, pushed task branch with no merge to `main`, no production change, and a concise owner handoff URL/status.

- [ ] **Step 1: Run the full final matrix from a clean checkout state**

Run:

```bash
node --test website/staging-safety/tests/*.test.cjs
cd website/twins-brand-experience
TWINS_LIVE=1 npm run test:all
cd ../..
git diff --check
git status --short
```

Expected: zero failures; PHP has zero skips on CI/host evidence; all live browser/crawl gates pass with env credentials; `git diff --check` prints nothing; only intentional evidence files are uncommitted before final commit.

- [ ] **Step 2: Audit requirements and forbidden authority one final time**

Search the staging manifest/package and staged output:

```bash
rg -n "Get an Estimate|Request Exact Quote|danielj140|TWINS_GHL_PIT|TWINS_GHL_LOCATION_ID|book\.housecallpro\.com|fetch\s*\(|XMLHttpRequest|sendBeacon|type=[\"']submit|<form" website/twins-brand-experience website/staging-safety/mu-plugins
```

Expected: staging runtime contains none of the forbidden CTA/transport/credential/live-booking/submitting-form strings; `danielj140` occurs only in authorized docs/tests/capture boundaries; HCP/GHL/transport strings occur only in production adapter/test fixtures and are absent from the staging package. Review every allowed match individually.

Check all tracked file modes and symlinks:

```bash
git ls-files -s website/twins-brand-experience website/staging-safety | awk '$1 != 100644 { print }'
```

Expected: no output for runtime/data/docs artifacts; executable tooling remains invoked through Node/PHP and does not need executable mode.

- [ ] **Step 3: Request two-stage independent review**

First reviewer checks spec compliance against `docs/superpowers/specs/2026-07-15-brand-wide-staging-homepage-redesign-design.md`. Second reviewer checks implementation quality/safety, focusing on route confinement, staging no-authority, review provenance, production/staging package separation, focus/slider behavior, asset integrity, and rollback evidence. Resolve every actionable finding through a new failing test and rerun the affected/full gates.

- [ ] **Step 4: Verify production remained read-only**

Record read-only evidence that production page/database/file/menu/DNS/settings/integration state was not mutated during the run. Verify no live form/booking/application was submitted. Do not perform a synthetic production mutation merely to prove this.

- [ ] **Step 5: Commit any review fixes and push only the task branch**

```bash
git status --short
git log --oneline --decorate -12
git push origin codex/staging-site-safety
```

Expected: push succeeds as a normal fast-forward to `origin/codex/staging-site-safety`; do not merge into `main`, do not force-push, and do not push another branch.

- [ ] **Step 6: Report the owner-ready result**

Report only after verification:

```text
Private staging: https://danielj140.sg-host.com/
Status: BRAND_WIDE_STAGING_READY_FOR_OWNER_REVIEW
Production write authority: false
Production deployment: not performed
Branch: codex/staging-site-safety
Handoff: docs/handoff/2026-07-15-brand-wide-private-staging.md
```

Include direct review routes and the exact final test/browser/crawl counts from committed artifacts. Do not call the production migration complete; list it as separately authorized future work.
