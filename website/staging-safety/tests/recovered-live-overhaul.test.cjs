const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '../../..');
const SAFETY = 'website/staging-safety/mu-plugins/twins-staging-safety.php';
const LOADER = 'website/staging-safety/mu-plugins/twins-staging-overhaul.php';
const PACKAGE = 'website/staging-safety/mu-plugins/twins-staging-overhaul';
const ASSETS = 'website/staging-safety/mu-plugins/twins-staging-assets';
const BRAND = 'website/twins-brand-experience';

function absolute(relativePath) {
  return path.join(ROOT, relativePath);
}

function read(relativePath) {
  return fs.readFileSync(absolute(relativePath), 'utf8');
}

function sha256(relativePath) {
  return crypto.createHash('sha256').update(fs.readFileSync(absolute(relativePath))).digest('hex');
}

function functionBody(source, name) {
  const start = source.indexOf(`function ${name}`);
  assert.notEqual(start, -1, `${name} is missing`);
  const brace = source.indexOf('{', start);
  assert.notEqual(brace, -1, `${name} has no body`);

  let depth = 0;
  let quote = null;
  let escaped = false;
  for (let index = brace; index < source.length; index += 1) {
    const character = source[index];
    if (quote) {
      if (escaped) escaped = false;
      else if (character === '\\') escaped = true;
      else if (character === quote) quote = null;
      continue;
    }
    if (character === "'" || character === '"') quote = character;
    else if (character === '{') depth += 1;
    else if (character === '}') {
      depth -= 1;
      if (depth === 0) return source.slice(brace + 1, index);
    }
  }
  assert.fail(`${name} body is not balanced`);
}

const LIVE_HASHES = Object.freeze({
  [SAFETY]: '65c65d28c502d5465b2e6419a48108781d8c554473290ec70d2d9997263226d2',
  [LOADER]: '20a3e8b8d88917f54173457f112562c6a31250f9385a3144d9771704d63a2e90',
  [`${PACKAGE}/bootstrap.php`]: '4d534364b37cb91a9a70bbb4b13fa2c50eba30b71dd8c2ab6d0022271dac8e22',
  [`${PACKAGE}/components.php`]: 'dfc0548204787ca24743ebebc02099690a75ad3fdede21e8ba10fa488ac47556',
  [`${PACKAGE}/renderers.php`]: '38f169dc26c0088aad872f0dc5f01214bcaa934f152b42a159959ba380f3012b',
  [`${PACKAGE}/templates/home.php`]: 'a7c7fb3b8c16fddbd9089561dbaeae92c696f3c60a28540137d85c3be8d22dd5',
  [`${PACKAGE}/templates/builder.php`]: '488ed5c9646f6bcb6271e4dbf518b4e9e003323f169c1de2a4e90f9ffc9d22af',
  [`${ASSETS}/twins-overhaul.css`]: 'a3fb61ed0da87e839d53fe4983cd7b0b4b67b844902abd7c8a8ab09e22b051a8',
  [`${ASSETS}/twins-overhaul.js`]: '549faf277bbadc3d8a9cbbacacc682762a9584df23ab60c8fb98d4e9e031f0ae',
  [`${ASSETS}/clopay-products.json`]: 'ce960f1267327183719192d80d249f31c903a24e5fc6471992bed00dccda74f5',
  [`${ASSETS}/nunito-variable.woff2`]: 'ba344451eab25b217a165363b1982048a5e5830a0daf36577973955a04cac793',
  [`${ASSETS}/twins-logo.png`]: 'cc63412115076e387953b81e9d936a3d40559afa2edc314b912a66b79d0bc0f0',
  [`${ASSETS}/twin-left.png`]: '267ce3a33a3bbee09f9517409523c09246ac0488182625baeb9e4cdac84b293a',
  [`${ASSETS}/twin-right.png`]: '29daf3e0c87133635c59a22e4560fb56ac819762a7ac8e84ebfe253bfccf75fe',
  [`${ASSETS}/twins-service-truck-cutout.png`]: 'ecd200b41f69334cf97c73bc9d85a3b59288b8174f2e9aae5c30fd27d9940bf3',
  [`${ASSETS}/twins-service-truck-cutout.webp`]: 'df91d2f10c7facc90fb336f8dd229d28e80d66c6ce9d79f6d0efdc32d7127e6e'
});

const intentionallyReplacedByPortableCore = new Set([
  'website/staging-safety/mu-plugins/twins-staging-overhaul/bootstrap.php',
  'website/staging-safety/mu-plugins/twins-staging-overhaul/components.php',
  'website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php',
  'website/staging-safety/mu-plugins/twins-staging-overhaul/routes.php',
  'website/staging-safety/mu-plugins/twins-staging-overhaul/templates/home.php',
  'website/staging-safety/mu-plugins/twins-staging-overhaul/templates/builder.php',
]);

test('recovered deployment-critical files are regular blobs byte-identical to the live staging source', () => {
  const requiredPackageFiles = [
    'data.php',
    'cost-data.php',
    'routes.php',
    'components.php',
    'renderers.php',
    'brand-runtime.php',
    'adapters/BrandStagingAdapters.php',
    'adapters/BrandStagingPreviews.php',
    'templates/home.php',
    'templates/service.php',
    'templates/location.php',
    'templates/trust.php',
    'templates/article.php',
    'templates/cost.php',
    'templates/builder.php'
  ].map((file) => `${PACKAGE}/${file}`);

  for (const relativePath of [...Object.keys(LIVE_HASHES), ...requiredPackageFiles]) {
    const stat = fs.lstatSync(absolute(relativePath));
    assert.equal(stat.isSymbolicLink(), false, `${relativePath} must not be a symlink`);
    assert.equal(stat.isFile(), true, `${relativePath} must be a regular file`);
    assert.ok(stat.size > 0, `${relativePath} must not be empty`);
  }
  assert.deepEqual(
    [...intentionallyReplacedByPortableCore].sort(),
    [
      'website/staging-safety/mu-plugins/twins-staging-overhaul/bootstrap.php',
      'website/staging-safety/mu-plugins/twins-staging-overhaul/components.php',
      'website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php',
      'website/staging-safety/mu-plugins/twins-staging-overhaul/routes.php',
      'website/staging-safety/mu-plugins/twins-staging-overhaul/templates/builder.php',
      'website/staging-safety/mu-plugins/twins-staging-overhaul/templates/home.php',
    ]
  );

  for (const [relativePath, expectedSha256] of Object.entries(LIVE_HASHES)) {
    if (intentionallyReplacedByPortableCore.has(relativePath)) continue;
    assert.equal(sha256(relativePath), expectedSha256, relativePath);
  }
});

test('both MU-plugin roots fail closed on environment, safety flag, and cron before loading behavior', () => {
  for (const relativePath of [SAFETY, LOADER]) {
    const source = read(relativePath);
    assert.match(source, /defined\(\s*'WP_ENVIRONMENT_TYPE'\s*\)/);
    assert.match(source, /WP_ENVIRONMENT_TYPE\s*!==\s*'staging'/);
    assert.match(source, /defined\(\s*'TWINS_STAGING_SAFETY'\s*\)/);
    assert.match(source, /TWINS_STAGING_SAFETY\s*!==\s*true/);
    assert.match(source, /defined\(\s*'DISABLE_WP_CRON'\s*\)/);
    assert.match(source, /DISABLE_WP_CRON\s*!==\s*true/);
    assert.match(source, /wp_die\s*\([\s\S]*?(?:response|status)['"]?\s*=>\s*503/);

    const firstLoadOrHook = source.search(/require_once\b|add_(?:action|filter)\s*\(/);
    const cronGate = source.search(/DISABLE_WP_CRON\s*!==\s*true/);
    assert.ok(firstLoadOrHook > cronGate, `${relativePath} must prove every root gate before loading or registering behavior`);
  }

  const safety = read(SAFETY);
  assert.match(safety, /add_filter\(\s*'pre_option_cron'\s*,\s*'twins_staging_safety_empty_cron'/);
  assert.match(safety, /add_filter\(\s*'pre_schedule_event'\s*,\s*'twins_staging_safety_block_cron_schedule'/);
  assert.match(safety, /add_filter\(\s*'pre_schedule_single_event'\s*,\s*'twins_staging_safety_block_cron_schedule'/);
});

test('the preview has no production-domain or outbound submission authority', () => {
  const sourceFiles = [
    LOADER,
    `${PACKAGE}/bootstrap.php`,
    `${PACKAGE}/data.php`,
    `${PACKAGE}/cost-data.php`,
    `${PACKAGE}/routes.php`,
    `${PACKAGE}/components.php`,
    `${PACKAGE}/renderers.php`,
    `${PACKAGE}/brand-runtime.php`,
    `${PACKAGE}/adapters/BrandStagingAdapters.php`,
    `${PACKAGE}/adapters/BrandStagingPreviews.php`,
    ...fs.readdirSync(absolute(`${PACKAGE}/templates`)).filter((file) => file.endsWith('.php')).map((file) => `${PACKAGE}/templates/${file}`),
    `${ASSETS}/twins-overhaul.js`,
    `${BRAND}/assets/js/twins-brand.js`,
    `${BRAND}/assets/js/twins-builder.js`,
  ];
  const combined = sourceFiles.map(read).join('\n');
  const javascript = read(`${ASSETS}/twins-overhaul.js`);
  const portableJavascript = [
    read(`${BRAND}/assets/js/twins-brand.js`),
    read(`${BRAND}/assets/js/twins-builder.js`),
  ].join('\n');
  const safety = read(SAFETY);

  assert.doesNotMatch(combined, /(?:https?:\/\/)?(?:www\.)?twinsgaragedoors\.com/i);
  assert.doesNotMatch(
    combined,
    /\b(?:wp_(?:safe_)?remote_(?:get|post|request)|wp_mail|mail|curl_exec|fsockopen|pfsockopen|stream_socket_client)\s*\(/i
  );
  assert.doesNotMatch(javascript, /\b(?:fetch|XMLHttpRequest|WebSocket|EventSource|sendBeacon|requestSubmit)\b|\.submit\s*\(/);
  assert.doesNotMatch(javascript, /\b(?:localStorage|sessionStorage|indexedDB)\b/);
  assert.doesNotMatch(portableJavascript, /\b(?:fetch|XMLHttpRequest|WebSocket|EventSource|sendBeacon|requestSubmit)\b|\.submit\s*\(/);
  assert.doesNotMatch(portableJavascript, /\b(?:localStorage|sessionStorage|indexedDB)\b/);
  assert.match(javascript, /form\.removeAttribute\('action'\)/);
  assert.match(javascript, /form\.removeAttribute\('method'\)/);
  assert.match(javascript, /form\.addEventListener\('submit'[\s\S]*?event\.preventDefault\(\)[\s\S]*?event\.stopImmediatePropagation\(\)/);
  assert.match(safety, /add_filter\(\s*'pre_wp_mail'\s*,\s*'twins_staging_safety_block_mail'/);
  assert.match(safety, /add_filter\(\s*'pre_http_request'\s*,\s*'twins_staging_safety_filter_http'/);
  assert.match(functionBody(safety, 'twins_staging_safety_filter_http'), /new\s+WP_Error\s*\(\s*'twins_staging_http_blocked'/);
});

test('homepage header has the large crossing logo, two prominent actions, and readable navy-on-yellow phone', () => {
  const components = read(`${PACKAGE}/components.php`);
  const css = read(`${ASSETS}/twins-overhaul.css`);
  const header = functionBody(components, 'twins_overhaul_render_header');

  assert.match(header, /twins-overhaul-header--home/);
  assert.match(header, /twins-overhaul-logo[\s\S]*?twins-logo\.png|twins_overhaul_asset_url\('logo'\)/);
  assert.match(header, /width="178" height="82"/);
  assert.match(header, /twins-overhaul-header__phone--home[\s\S]*?href="tel:/);
  assert.match(header, /twins-overhaul-header__estimate--home[\s\S]*?Get an Estimate/);
  assert.match(css, /\.twins-overhaul-header--home \.twins-overhaul-logo img\s*\{[^}]*height:\s*74px[^}]*transform:\s*translateY\(10px\)/s);
  assert.match(css, /\.twins-overhaul-header--home \.twins-overhaul-header__phone--home\s*\{[^}]*color:\s*var\(--twins-deep\)[^}]*background:\s*linear-gradient/s);
  assert.match(css, /\.twins-overhaul-header__phone--home::before,[\s\S]*?\.twins-overhaul-header__estimate--home::before\s*\{[^}]*animation:\s*brandGleam 4\.8s/s);
});

test('mobile keeps both Twin characters animated and uses a modern inset action dock with reduced-motion safety', () => {
  const home = read(`${PACKAGE}/templates/home.php`);
  const components = read(`${PACKAGE}/components.php`);
  const css = read(`${ASSETS}/twins-overhaul.css`);

  assert.match(home, /\$twinLeft\s*=\s*twins_overhaul_asset_url\('twin-left'\)/);
  assert.match(home, /\$twinRight\s*=\s*twins_overhaul_asset_url\('twin-right'\)/);
  assert.match(home, /twins-home-hero__mobile-mascots[\s\S]*?esc_url\(\$twinLeft\)[\s\S]*?esc_url\(\$twinRight\)/);
  assert.match(css, /@media\s*\(max-width:\s*767px\)[\s\S]*?\.twins-home-hero__mobile-mascots\s*\{[^}]*display:\s*flex/s);
  assert.match(css, /\.twins-home-hero__mobile-mascots img\s*\{[^}]*animation:\s*twinsMascotFloat/s);
  assert.match(components, /twins-overhaul-mobile-actions[\s\S]*?>Call Now<[\s\S]*?>Get an Estimate</);
  assert.match(css, /\.twins-overhaul-mobile-actions\s*\{[^}]*position:\s*fixed[^}]*right:\s*10px[^}]*left:\s*10px[^}]*border-radius:\s*20px/s);
  assert.match(css, /\.twins-overhaul-mobile-actions a:last-child\s*\{[^}]*color:\s*var\(--twins-deep\)[^}]*linear-gradient/s);
  assert.match(css, /@media\s*\(prefers-reduced-motion:\s*reduce\)[\s\S]*?animation:\s*none\s*!important/s);
});

test('fixed routing preserves campaign work while branding Careers and retaining regional aliases and builder routes', () => {
  const safety = read(SAFETY);
  const routes = read(`${PACKAGE}/routes.php`);
  const data = read(`${PACKAGE}/data.php`);
  const redirect = functionBody(safety, 'twins_staging_safety_legacy_redirect_path');
  const classify = functionBody(routes, 'twins_overhaul_classify_request');

  assert.match(redirect, /['"]\/madison\/['"]\s*=>\s*['"]\/wi\/['"]/);
  assert.match(redirect, /strpos\(\s*\$path\s*,\s*['"]\/madison\/['"]\s*\)\s*===\s*0/);
  assert.match(redirect, /return\s+['"]\/wi\/['"]\s*\.\s*\$suffix/);
  assert.match(redirect, /['"]\/wi\/careers\/['"]\s*=>\s*['"]\/careers\/['"]/);
  assert.match(redirect, /['"]\/ky\/careers\/['"]\s*=>\s*['"]\/careers\/['"]/);
  assert.match(classify, /in_array\(\s*\$postId,\s*array\(7092,\s*7093\),\s*true\)/);
  assert.match(classify, /\$postId\s*===\s*7341[\s\S]*?return\s+['"]careers-brand['"]/);
  for (const [blog, route] of [[1, '/door-builder'], [3, '/ky/design-your-door'], [4, '/wi/door-builder'], [5, '/il/door-builder']]) {
    assert.match(classify, new RegExp(`${blog}\\s*=>\\s*array\\(['"]${route.replaceAll('/', '\\/')}['"]`));
  }
  assert.match(classify, /1\s*=>\s*array\('\/door-builder',\s*'\/design-your-door'\)/);
  assert.equal((data.match(/twins_overhaul_navigation_item\(['"]Careers['"],\s*['"]\/careers\/['"]\)/g) || []).length, 4);
});

test('private staging brand bridge is fixed-origin, inert, and isolated behind the unchanged root gates', () => {
  const runtimePath = `${PACKAGE}/brand-runtime.php`;
  const adaptersPath = `${PACKAGE}/adapters/BrandStagingAdapters.php`;
  const previewsPath = `${PACKAGE}/adapters/BrandStagingPreviews.php`;
  const runtime = read(runtimePath);
  const adapters = read(adaptersPath);
  const previews = read(previewsPath);
  const routes = read(`${PACKAGE}/routes.php`);
  const renderers = read(`${PACKAGE}/renderers.php`);
  const bootstrap = read(`${PACKAGE}/bootstrap.php`);

  assert.equal(sha256(LOADER), LIVE_HASHES[LOADER], 'root gate loader changed');
  assert.match(bootstrap, /require_once\s+__DIR__\s*\.\s*['"]\/brand-runtime\.php['"]/);
  assert.match(runtime, /count\(\$valid\)\s*!==\s*1/);
  assert.match(runtime, /Portable brand core resolution is unavailable or ambiguous/);
  assert.match(runtime, /content_url\(\s*['"]mu-plugins\/twins-brand-experience['"]\s*\)/);
  assert.match(runtime, /network_home_url\(\s*['"]\/['"]\s*\)/);
  assert.match(runtime, /WPMU_PLUGIN_DIR[\s\S]*?google-business-reviews-collection-2178\.json/);

  for (const classification of ['home-brand', 'team-brand', 'careers-brand', 'reviews-brand', 'contact-brand']) {
    assert.match(routes, new RegExp(`['"]${classification}['"]`));
    assert.match(renderers, new RegExp(`['"]${classification}['"]`));
  }
  for (const obsolete of ['careers-preserve', 'team-preserve']) {
    assert.doesNotMatch(routes, new RegExp(`['"]${obsolete}['"]`));
  }

  assert.match(adapters, /final\s+class\s+StagingAssetResolver\s+implements\s+AssetResolver/);
  assert.match(adapters, /final\s+class\s+StagingRouteAdapter\s+implements\s+RouteAdapter/);
  assert.match(adapters, /final\s+class\s+CapturedReviewsProvider\s+implements\s+ReviewsProvider/);
  assert.match(adapters, /final\s+class\s+StagingQuoteAdapter\s+implements\s+QuoteAdapter/);
  assert.match(adapters, /final\s+class\s+StagingQuoteAdapter[\s\S]*?private\s+string\s+\$href/);
  assert.match(adapters, /final\s+class\s+StagingBookingAdapter\s+implements\s+BookingAdapter/);
  assert.match(adapters, /final\s+class\s+StagingApplicationAdapter\s+implements\s+ApplicationAdapter/);
  assert.match(adapters, /Cross-origin staging assets are forbidden/);
  assert.match(adapters, /Unknown asset key/);
  assert.match(adapters, /Unknown staging route key|Unknown route key/);
  assert.match(adapters, /Unknown staging adapter context key/);
  assert.match(adapters, /\$expectedMarket/);
  assert.match(adapters, /ReviewCodec::verifyCollection/);
  assert.match(adapters, /lstat\s*\(/);
  assert.match(adapters, /2097152/);
  for (const field of ['dev', 'ino', 'mode', 'uid', 'gid', 'size', 'mtime', 'ctime']) {
    assert.match(adapters, new RegExp(`['"]${field}['"]`));
  }
  assert.match(adapters, /sameSnapshot\(\$before,\s*\$readSnapshot\)/);
  assert.match(adapters, /sameSnapshot\(\$before,\s*\$after\)/);
  assert.doesNotMatch(adapters, /\b(?:do_shortcode|wp_(?:safe_)?remote_(?:get|post|request)|get_transient|set_transient)\s*\(/i);
  assert.doesNotMatch(adapters, /\$_(?:GET|POST|REQUEST)|\$wpdb\b/);

  assert.doesNotMatch(previews, /<form\b|type=["'](?:submit|image)["']|\bname=|\bformaction=|https?:\/\//i);
  assert.match(previews, /data-preview-kind=["']quote["']/);
  assert.match(previews, /data-preview-kind=["']application["']/);
  assert.match(previews, /data-twins-booking-dialog/);
  assert.match(renderers, /twins-brand-experience/);
  assert.match(renderers, /connect-src 'none'/);
  assert.match(renderers, /form-action 'none'/);
});

test('Lexington exception is pinned to the exact blog, location, queried post, and Elementor document tuple', () => {
  const renderers = read(`${PACKAGE}/renderers.php`);
  const body = functionBody(renderers, 'twins_overhaul_is_fixed_lexington_theme_document');
  assert.match(body, /get_current_blog_id\(\)\s*===\s*3/);
  assert.match(body, /get_queried_object_id\(\)\s*===\s*2415/);
  assert.match(body, /get_post_type\(2415\)\s*===\s*'location'/);
  assert.match(body, /twins_overhaul_current_request_path\(\)\s*===\s*'\/ky\/location\/lexington\/'/);
  assert.match(body, /\$documentId\s*===\s*2427/);
});

test('responsive-image repair removes exactly the two known stale candidates', () => {
  const safety = read(SAFETY);
  const body = functionBody(safety, 'twins_staging_safety_filter_broken_legacy_srcset');
  const expected = [
    '/wp-content/uploads/2022/11/elementor/thumbs/Liftmaster-pxvfkw06sw4jutr19cwz68jdicnan2uq0t6tgqr0w0.jpg',
    '/wp-content/uploads/2023/05/elementor/thumbs/liftmaster-84505r-150x150.png'
  ];
  assert.equal((body.match(/\/elementor\/thumbs\//g) || []).length, 2);
  for (const candidate of expected) assert.ok(body.includes(candidate), `${candidate} is not pinned`);
  assert.match(body, /in_array\(\s*\$path,\s*\$missing_paths,\s*true\s*\)/);
  assert.match(body, /unset\(\s*\$sources\[\$width\]\s*\)/);
  assert.match(safety, /add_filter\(\s*'wp_calculate_image_srcset'\s*,\s*'twins_staging_safety_filter_broken_legacy_srcset'/);
});

test('door builder is a frozen local-only 23-product catalog whose content-addressed images all exist', () => {
  const catalogPath = `${ASSETS}/clopay-products.json`;
  const catalogBytes = read(catalogPath);
  const catalog = JSON.parse(catalogBytes);
  const builderPhp = read(`${PACKAGE}/templates/builder.php`);
  const builderJs = read(`${BRAND}/assets/js/twins-builder.js`);
  const expectedOrder = [
    '330', '320', '30', '29', '240', '26', '170', '340', '12', '16', '290', '370',
    '250', '380', '11', '27', '291', '8', '10', '25', '9', '13', '23'
  ];

  assert.deepEqual(Object.keys(catalog), ['schemaVersion', 'productOrder', 'products']);
  assert.equal(catalog.schemaVersion, 1);
  assert.deepEqual(catalog.productOrder, expectedOrder);
  assert.equal(catalog.products.length, 23);
  assert.deepEqual(catalog.products.map((product) => product.id), expectedOrder);
  assert.doesNotMatch(catalogBytes, /(?:https?:|\/\/www\.|clopaydoor\.com)/i);
  assert.match(builderPhp, new RegExp(LIVE_HASHES[catalogPath]));
  assert.match(builderPhp, /filesize\(\$path\)[\s\S]*?>\s*2097152/);
  assert.match(builderJs, /BUILDER_LOCAL_IMAGE\s*=\s*\/\^\\\/wp-content\\\/mu-plugins\\\/twins-staging-assets\\\/clopay/);
  assert.doesNotMatch(builderJs, /\b(?:fetch|XMLHttpRequest|sendBeacon|localStorage|sessionStorage|indexedDB)\b/);

  const imageRecords = [];
  function collect(value) {
    if (Array.isArray(value)) return value.forEach(collect);
    if (!value || typeof value !== 'object') return;
    if (Object.keys(value).join(',') === 'src,width,height,alt') imageRecords.push(value);
    for (const nested of Object.values(value)) collect(nested);
  }
  collect(catalog.products);
  assert.ok(imageRecords.length > 23, 'catalog must include product and option reference images');

  for (const image of imageRecords) {
    assert.match(image.src, /^\/wp-content\/mu-plugins\/twins-staging-assets\/clopay\/[a-f0-9]{2}\/[a-f0-9]{64}\.(?:webp|jpg)$/);
    const relativeAsset = image.src.replace('/wp-content/mu-plugins/', 'website/staging-safety/mu-plugins/');
    const stat = fs.lstatSync(absolute(relativeAsset));
    assert.equal(stat.isFile(), true, `${relativeAsset} is missing`);
    assert.equal(stat.isSymbolicLink(), false, `${relativeAsset} must not be a symlink`);
    assert.equal(sha256(relativeAsset), path.basename(image.src).split('.')[0], `${relativeAsset} digest is not content-addressed`);
  }
});

test('Illinois provisioner pins the current safety-plugin digest when the recovered operational tool is present', (t) => {
  const relativePath = 'website/staging-safety/tools/staging-il-provision.php';
  if (!fs.existsSync(absolute(relativePath))) {
    t.skip('operational Illinois tool has not been recovered into this worktree yet');
    return;
  }
  const source = read(relativePath);
  assert.match(source, /65c65d28c502d5465b2e6419a48108781d8c554473290ec70d2d9997263226d2/);
  assert.match(source, /https:\/\/danielj140\.sg-host\.com/);
  assert.doesNotMatch(source, /(?:https?:\/\/)?(?:www\.)?twinsgaragedoors\.com/i);
});
