const test = require('node:test');
const assert = require('node:assert/strict');
const childProcess = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..');
const PLUGIN_PATH = path.join(ROOT, 'mu-plugins', 'twins-staging-safety.php');
const README_PATH = path.join(ROOT, 'README.md');
const TWX_V2_CSS_PATH = path.join(ROOT, 'mu-plugins', 'twins-staging-assets', 'twx-v2-kit.css');
const HARNESS_PATH = path.join(__dirname, 'wordpress-harness.php');
const CHROME_TRANSITION_PATH = path.join(ROOT, 'tools', 'staging-chrome-transition.php');
const CHROME_TRANSITION_HARNESS_PATH = path.join(__dirname, 'staging-chrome-transition-harness.php');
const EXPECTED_CSP_DIRECTIVES = [
  "default-src 'self'",
  "base-uri 'self'",
  "object-src 'none'",
  "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: https://www.clopaydoor.com",
  "font-src 'self'",
  "connect-src 'self'",
  "media-src 'self'",
  "frame-src 'self'",
  "child-src 'self'",
  "worker-src 'self'",
  "manifest-src 'self'",
  "form-action 'self'",
  "frame-ancestors 'self'",
  "navigate-to 'self'"
];

function read(file) {
  return fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '';
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
      if (escaped) {
        escaped = false;
      } else if (character === '\\') {
        escaped = true;
      } else if (character === quote) {
        quote = null;
      }
      continue;
    }
    if (character === "'" || character === '"') {
      quote = character;
    } else if (character === '{') {
      depth += 1;
    } else if (character === '}') {
      depth -= 1;
      if (depth === 0) return source.slice(brace + 1, index);
    }
  }
  assert.fail(`${name} body is not balanced`);
}

function compactPhpCode(source) {
  let compact = '';
  let quote = null;
  let escaped = false;
  for (const character of source) {
    if (quote) {
      compact += character;
      if (escaped) {
        escaped = false;
      } else if (character === '\\') {
        escaped = true;
      } else if (character === quote) {
        quote = null;
      }
    } else if (character === "'" || character === '"') {
      quote = character;
      compact += character;
    } else if (!/\s/.test(character)) {
      compact += character;
    }
  }
  return compact;
}

test('MU plugin fails closed before registering hooks outside an explicitly configured staging environment', () => {
  const source = read(PLUGIN_PATH);
  assert.ok(source, 'staging-safety MU plugin is missing');

  const firstHook = source.search(/add_(?:filter|action)\s*\(/);
  const refusal = source.indexOf('twins_staging_safety_refuse_boot');
  assert.notEqual(refusal, -1, 'fail-closed boot refusal is missing');
  assert.ok(firstHook > refusal, 'boot refusal must run before any hook is registered');
  assert.match(source, /defined\(\s*'WP_ENVIRONMENT_TYPE'\s*\)/);
  assert.match(source, /WP_ENVIRONMENT_TYPE\s*!==\s*'staging'/);
  assert.match(source, /defined\(\s*'TWINS_STAGING_SAFETY'\s*\)/);
  assert.match(source, /TWINS_STAGING_SAFETY\s*!==\s*true/);
  assert.match(source, /wp_die\s*\(/);
  assert.match(source, /(?:response|status)\s*['"]?\s*=>\s*503/);
  const refusalBody = functionBody(source, 'twins_staging_safety_refuse_boot');
  assert.match(refusalBody, /wp_die\s*\([\s\S]*\);\s*exit\s*;/);
});

test('mail is short-circuited as a handled non-send', () => {
  const source = read(PLUGIN_PATH);
  assert.match(source, /add_filter\(\s*'pre_wp_mail'\s*,\s*'twins_staging_safety_block_mail'\s*,\s*PHP_INT_MAX\s*,\s*2\s*\)/);
  const body = functionBody(source, 'twins_staging_safety_block_mail');
  assert.match(body, /return\s+true\s*;/);
});

test('outbound HTTP defaults to blocked and returns a WP_Error', () => {
  const source = read(PLUGIN_PATH);
  assert.match(source, /add_filter\(\s*'pre_http_request'\s*,\s*'twins_staging_safety_filter_http'\s*,\s*PHP_INT_MAX\s*,\s*3\s*\)/);
  const filter = functionBody(source, 'twins_staging_safety_filter_http');
  assert.match(filter, /new\s+WP_Error\s*\(\s*'twins_staging_http_blocked'/);
  assert.match(filter, /unset\(\s*\$preempt\s*,\s*\$arguments\s*,\s*\$url\s*\)/);
  assert.doesNotMatch(source, /twins_staging_safety_http_allowed|twins_staging_safety_harden_http_args|twins_staging_safety_http_args_are_safe/);
  assert.doesNotMatch(source, /add_filter\(\s*'http_request_args'/);
});

test('all responses and crawler surfaces are marked noindex, nofollow and noarchive', () => {
  const source = read(PLUGIN_PATH);
  assert.match(source, /add_action\(\s*'send_headers'\s*,\s*'twins_staging_safety_send_headers'/);
  assert.match(source, /X-Robots-Tag:\s*noindex,\s*nofollow,\s*noarchive/);
  assert.match(source, /add_filter\(\s*'wp_robots'\s*,\s*'twins_staging_safety_robots_meta'/);
  const robotsMeta = functionBody(source, 'twins_staging_safety_robots_meta');
  for (const directive of ['noindex', 'nofollow', 'noarchive']) {
    assert.match(robotsMeta, new RegExp(`['"]${directive}['"]\\s*\\]\\s*=\\s*true`));
  }
  assert.match(source, /add_filter\(\s*'robots_txt'\s*,\s*'twins_staging_safety_robots_txt'/);
  assert.match(functionBody(source, 'twins_staging_safety_robots_txt'), /Disallow:\s*\//);
});

test('quarantine CSP permits same-origin connections and Clopay images only', () => {
  const source = read(PLUGIN_PATH);
  const policyBody = functionBody(source, 'twins_staging_safety_csp_policy');
  const directives = Array.from(policyBody.matchAll(/^\s*"([^"]+)",?\s*$/gm), (match) => match[1]);
  assert.deepEqual(directives, EXPECTED_CSP_DIRECTIVES);

  const policy = directives.join('; ');
  assert.equal((policy.match(/https:\/\//g) || []).length, 1);
  assert.equal((policy.match(/data:/g) || []).length, 1);
  assert.deepEqual(
    directives.filter((directive) => directive.includes('https://')),
    ["img-src 'self' data: https://www.clopaydoor.com"]
  );
  assert.deepEqual(
    directives.filter((directive) => directive.includes('data:')),
    ["img-src 'self' data: https://www.clopaydoor.com"]
  );
  assert.match(policy, /img-src 'self' data: https:\/\/www\.clopaydoor\.com/);
  assert.match(policy, /connect-src 'self'/);
  assert.doesNotMatch(policy, /connect-src[^;]*https:\/\//);
  assert.doesNotMatch(policy, /(?:^|\s)https:(?:\s|;|$)/);
  assert.doesNotMatch(policy, /\*/);

  const headers = functionBody(source, 'twins_staging_safety_send_headers');
  assert.match(headers, /Content-Security-Policy:\s*'\s*\.\s*twins_staging_safety_csp_policy\(\)/);
  assert.match(source, /add_action\(\s*'muplugins_loaded'\s*,\s*'twins_staging_safety_send_headers'/);
  assert.match(source, /add_action\(\s*'send_headers'\s*,\s*'twins_staging_safety_send_headers'/);
  assert.match(source, /add_action\(\s*'admin_init'\s*,\s*'twins_staging_safety_send_headers'/);
  assert.match(source, /add_action\(\s*'login_init'\s*,\s*'twins_staging_safety_send_headers'/);
});

test('STAGING is visibly labelled in WordPress admin and on the public frontend', () => {
  const source = read(PLUGIN_PATH);
  assert.match(source, /add_action\(\s*'admin_notices'\s*,\s*'twins_staging_safety_admin_banner'/);
  assert.match(source, /add_action\(\s*'network_admin_notices'\s*,\s*'twins_staging_safety_admin_banner'/);
  assert.match(source, /add_action\(\s*'wp_body_open'\s*,\s*'twins_staging_safety_frontend_banner'/);
  assert.match(source, /add_action\(\s*'wp_footer'\s*,\s*'twins_staging_safety_frontend_banner'/);
  const admin = functionBody(source, 'twins_staging_safety_admin_banner');
  const frontend = functionBody(source, 'twins_staging_safety_frontend_banner');
  assert.match(admin, /STAGING/);
  assert.match(frontend, /STAGING/);
  assert.match(frontend, /position:\s*fixed/);
});

test('Claude twx v2 visual layer is restored locally without enabling integrations', () => {
  const source = read(PLUGIN_PATH);
  const css = read(TWX_V2_CSS_PATH);
  assert.ok(css, 'the local-only twx v2 stylesheet is missing');

  assert.match(source, /add_action\(\s*'wp_enqueue_scripts'\s*,\s*'twins_staging_safety_enqueue_visual_preview_styles'\s*,\s*PHP_INT_MAX\s*\)/);
  const enqueue = functionBody(source, 'twins_staging_safety_enqueue_visual_preview_styles');
  assert.match(enqueue, /plugins_url\(\s*'twins-staging-assets\/twx-v2-kit\.css'\s*,\s*__FILE__\s*\)/);
  assert.match(enqueue, /wp_enqueue_style\(\s*'twins-staging-twx-v2'/);

  for (const selector of [
    '.twx2-hero',
    '.twx2-pair',
    '.twx2-btn--gold',
    '.twx2-ribbon',
    '.twx2-card',
    '.twx2-steps',
    '.twx2-closer',
    '.twx2-grid',
    '#twx2-stickybar'
  ]) {
    assert.ok(css.includes(selector), `${selector} is missing from the visual kit`);
  }
  assert.doesNotMatch(css, /@import|url\s*\(|https?:|javascript:|expression\s*\(/i);
});

test('Wisconsin staging preview keeps Claude per-metro phone presentation local-only', () => {
  const source = read(PLUGIN_PATH);
  assert.match(source, /add_action\(\s*'wp_footer'\s*,\s*'twins_staging_safety_wi_phone_preview'\s*,\s*PHP_INT_MAX\s*-\s*1\s*\)/);
  const preview = functionBody(source, 'twins_staging_safety_wi_phone_preview');
  assert.match(preview, /get_current_blog_id\(\)\s*!==\s*4/);
  assert.match(preview, /milwaukee/i);
  assert.match(preview, /\(414\) 800-9271/);
  assert.match(preview, /\(608\) 420-2377/);
  assert.match(preview, /tel:\+14148009271/);
  assert.match(preview, /tel:\+16084202377/);
  assert.doesNotMatch(preview, /https?:|fetch\s*\(|XMLHttpRequest|sendBeacon|WebSocket|<form/i);
});

test('legacy staging-only links resolve through a bounded same-origin redirect map', () => {
  const source = read(PLUGIN_PATH);
  const map = functionBody(source, 'twins_staging_safety_legacy_redirect_path');
  const handler = functionBody(source, 'twins_staging_safety_redirect_legacy_request');

  assert.match(map, /strpos\(\s*\$path\s*,\s*['"]\/madison\/['"]\s*\)\s*===\s*0/);
  assert.match(map, /['"]\/madison\/hello-world\/['"]\s*=>\s*['"]\/wi\/garage-door-services\/['"]/);
  assert.match(map, /['"]\/wi\/location\/wi\/['"]\s*=>\s*['"]\/wi\/location\/madison\/['"]/);
  assert.match(map, /['"]\/emergency-services\/['"]\s*=>\s*['"]\/wi\/emergency-garage-services\/['"]/);
  assert.match(map, /['"]\/ky\/location\/lexington\/garage-door-installation\/['"]\s*=>\s*['"]\/ky\/garage-door-installation\/['"]/);
  assert.match(map, /['"]\/wi\/maintenance-plans\/['"]\s*=>\s*['"]\/wi\/protection-plans\/['"]/);
  for (const path of [
    '/ky/author/',
    '/wi/author/',
    '/ky/category/madison/page/2/',
    '/wi/category/broken-cable/page/3/',
    '/wi/category/construction/page/3/',
    '/wi/category/garage-door-installation/page/2/',
    '/wi/category/garage-door-installation/page/3/',
    '/wi/category/replace-a-broken-cable/page/3/',
    '/wi/category/torsion-spring-conversion/page/3/',
    '/wi/category/torsion-spring/page/3/'
  ]) {
    assert.ok(map.includes(`'${path}'`), `${path} is missing from the explicit map`);
  }

  assert.match(handler, /wp_safe_redirect\(\s*network_home_url\(\s*\$destination\s*\)\s*,\s*302/);
  assert.doesNotMatch(handler, /wp_safe_redirect\(\s*home_url\(/);
  assert.match(source, /add_action\(\s*'template_redirect'\s*,\s*'twins_staging_safety_redirect_legacy_request'\s*,\s*PHP_INT_MIN\s*\)/);
  assert.doesNotMatch(map, /https?:\/\//);
});

test('inactive production reviews render a fixed local-only staging placeholder', () => {
  const source = read(PLUGIN_PATH);
  assert.match(source, /add_shortcode\(\s*'brb_collection'\s*,\s*'twins_staging_safety_review_placeholder'\s*\)/);
  const placeholder = functionBody(source, 'twins_staging_safety_review_placeholder');
  assert.match(placeholder, /Reviews are intentionally disabled on this private staging copy/);
  assert.doesNotMatch(placeholder, /https?:|<script|<iframe|<form|src=|href=/i);
});

test('Clopay and door-builder integrations render fixed local-only staging placeholders', () => {
  const source = read(PLUGIN_PATH);
  for (const shortcode of ['clopay_product', 'clopay_collection_grid', 'twins_door_builder']) {
    assert.match(
      source,
      new RegExp("add_shortcode\\(\\s*['\"]" + shortcode + "['\"]\\s*,\\s*['\"]twins_staging_safety_disabled_integration_placeholder['\"]\\s*\\)")
    );
  }
  const placeholder = functionBody(source, 'twins_staging_safety_disabled_integration_placeholder');
  assert.match(placeholder, /Interactive product and door-builder integrations are intentionally disabled on this private staging copy/);
  assert.doesNotMatch(placeholder, /https?:|<script|<iframe|<form|src=|href=/i);
});

test('protected shortcodes are reasserted after later plugin and theme registration', () => {
  const source = read(PLUGIN_PATH);
  const register = functionBody(source, 'twins_staging_safety_register_placeholders');

  for (const shortcode of ['brb_collection', 'clopay_product', 'clopay_collection_grid', 'twins_door_builder']) {
    assert.match(register, new RegExp("remove_shortcode\\(\\s*['\"]" + shortcode + "['\"]\\s*\\)"));
    assert.match(register, new RegExp("add_shortcode\\(\\s*['\"]" + shortcode + "['\"]"));
  }

  for (const hook of ['plugins_loaded', 'after_setup_theme', 'init', 'wp_loaded']) {
    assert.match(
      source,
      new RegExp("add_action\\(\\s*['\"]" + hook + "['\"]\\s*,\\s*['\"]twins_staging_safety_register_placeholders['\"]\\s*,\\s*PHP_INT_MAX\\s*\\)")
    );
  }
});

test('pre_do_shortcode_tag is a fail-safe for every protected shortcode', () => {
  const source = read(PLUGIN_PATH);
  assert.match(source, /add_filter\(\s*'pre_do_shortcode_tag'\s*,\s*'twins_staging_safety_prevent_production_shortcode'\s*,\s*PHP_INT_MAX\s*,\s*4\s*\)/);
  const failSafe = functionBody(source, 'twins_staging_safety_prevent_production_shortcode');
  assert.match(failSafe, /brb_collection/);
  for (const shortcode of ['clopay_product', 'clopay_collection_grid', 'twins_door_builder']) {
    assert.match(failSafe, new RegExp(shortcode));
  }
  assert.match(failSafe, /twins_staging_safety_review_placeholder/);
  assert.match(failSafe, /twins_staging_safety_disabled_integration_placeholder/);
  assert.match(failSafe, /return\s+\$output\s*;/);
});

test('credential-bearing integration options cannot be repopulated on staging', () => {
  const source = read(PLUGIN_PATH);
  const optionNames = functionBody(source, 'twins_staging_safety_quarantined_option_names');
  for (const optionName of [
    '_elementor_pro_api_requests_lock',
    '_elementor_pro_license_data',
    '_elementor_pro_license_data_fallback',
    '_temporary_login_site_token',
    'ai1wm_secret_key',
    'appsero_c6aa184e76ef48e61c74d4a212a611e3_manage_license',
    'brb_auth_code',
    'brb_last_error',
    'clickcease_api_key',
    'clickcease_bot_zapping_authenticated',
    'clickcease_client_id',
    'clickcease_domain_key',
    'clickcease_secret_key',
    'duplicator_pro_license_key',
    'elementor_connect_site_key',
    'image_optimizer_access_token',
    'image_optimizer_client_id',
    'image_optimizer_client_secret',
    'image_optimizer_connect_data',
    'image_optimizer_refresh_token',
    'image_optimizer_token_id',
    'jetpack_active_plan',
    'jetpack_connection_active_plugins',
    'jetpack_licenses',
    'jetpack_options',
    'jetpack_persistent_blog_id',
    'jetpack_private_options',
    'jetpack_unique_connection',
    'jetpack_unique_registrations',
    'lead_connector_plugin_options',
    'metasync_telemetry_jwt_secret',
    'nitropack-webhookToken',
    'postmark_settings',
    'rank_math_analytics_all_services',
    'rank_math_analytics_permissions',
    'rank_math_google_analytic_profile',
    'rank_math_google_oauth_tokens',
    'siteground_data_token',
    'wordpress_api_key',
    'wpcode_usage_tracking_config',
    'wpil_2_license_data',
    'wpil_2_license_key',
    'wpil_ai_access_token'
  ]) {
    assert.match(optionNames, new RegExp("['\"]" + optionName + "['\"]"));
  }
  assert.doesNotMatch(optionNames, /['"]brainstrom_products['"]/);
  assert.match(source, /add_filter\(\s*'pre_update_option_'\s*\.\s*\$option_name\s*,\s*'twins_staging_safety_keep_quarantined_option_empty'\s*,\s*PHP_INT_MAX\s*,\s*3\s*\)/);
  const blocker = functionBody(source, 'twins_staging_safety_keep_quarantined_option_empty');
  assert.match(blocker, /return\s+\$old_value\s*;/);
  assert.match(source, /add_action\(\s*'added_option'\s*,\s*'twins_staging_safety_delete_quarantined_option_after_add'\s*,\s*PHP_INT_MAX\s*,\s*2\s*\)/);
  const postAdd = functionBody(source, 'twins_staging_safety_delete_quarantined_option_after_add');
  assert.match(postAdd, /in_array\([\s\S]*twins_staging_safety_quarantined_option_names\(\)[\s\S]*true/);
  assert.match(postAdd, /delete_option\(\s*\$option\s*\)/);
});

test('quarantined ordinary and network options are guarded and pre-existing values refuse requests', () => {
  const source = read(PLUGIN_PATH);
  assert.match(source, /add_action\(\s*'add_option'\s*,\s*'twins_staging_safety_reject_unsafe_option_add'\s*,\s*PHP_INT_MIN\s*,\s*2\s*\)/);
  const preInsert = functionBody(source, 'twins_staging_safety_reject_unsafe_option_add');
  assert.match(preInsert, /twins_staging_safety_quarantined_option_names\(\)/);
  assert.match(preInsert, /twins_staging_safety_refuse_boot/);
  assert.match(source, /add_filter\(\s*'pre_add_site_option_'\s*\.\s*\$option_name\s*,\s*'twins_staging_safety_filter_new_quarantined_network_option'\s*,\s*PHP_INT_MIN\s*,\s*3\s*\)/);
  assert.match(source, /add_filter\(\s*'pre_update_site_option_'\s*\.\s*\$option_name\s*,\s*'twins_staging_safety_keep_quarantined_option_empty'\s*,\s*PHP_INT_MAX\s*,\s*4\s*\)/);
  assert.match(source, /add_action\(\s*'add_site_option'\s*,\s*'twins_staging_safety_delete_quarantined_site_option_after_add'\s*,\s*PHP_INT_MAX\s*,\s*3\s*\)/);
  const networkAdd = functionBody(source, 'twins_staging_safety_delete_quarantined_site_option_after_add');
  assert.match(networkAdd, /delete_network_option\(\s*\$network_id\s*,\s*\$option\s*\)/);

  const assertion = functionBody(source, 'twins_staging_safety_assert_quarantine_empty');
  assert.match(assertion, /get_option\(\s*\$option_name/);
  assert.match(assertion, /get_site_option\(\s*\$option_name/);
  assert.match(assertion, /twins_staging_safety_value_is_empty/);
  assert.match(assertion, /twins_staging_safety_refuse_boot/);
  const assertionCall = source.indexOf('twins_staging_safety_assert_quarantine_empty();');
  const firstHook = source.search(/add_(?:filter|action)\s*\(/);
  assert.ok(assertionCall > 0 && assertionCall < firstHook, 'quarantine preflight must precede hook registration');
});

test('brainstrom_products preserves safe registry data but recursively rejects secret-bearing fields', () => {
  const source = read(PLUGIN_PATH);
  const recursiveCheck = functionBody(source, 'twins_staging_safety_registry_contains_sensitive_value');
  assert.match(recursiveCheck, /twins_staging_safety_sensitive_registry_key/);
  assert.match(recursiveCheck, /twins_staging_safety_value_is_empty/);
  assert.match(recursiveCheck, /twins_staging_safety_registry_contains_sensitive_value\(\s*\$nested_value/);

  assert.doesNotMatch(source, /pre_add_option_brainstrom_products/);
  const preInsert = functionBody(source, 'twins_staging_safety_reject_unsafe_option_add');
  assert.match(preInsert, /brainstrom_products/);
  assert.match(preInsert, /twins_staging_safety_registry_contains_sensitive_value/);
  assert.match(preInsert, /twins_staging_safety_refuse_boot/);
  assert.match(source, /add_filter\(\s*'pre_update_option_brainstrom_products'\s*,\s*'twins_staging_safety_filter_brainstrom_registry_update'/);
  assert.match(source, /add_filter\(\s*'pre_add_site_option_brainstrom_products'\s*,\s*'twins_staging_safety_filter_new_brainstrom_registry'/);
  assert.match(source, /add_filter\(\s*'pre_update_site_option_brainstrom_products'\s*,\s*'twins_staging_safety_filter_brainstrom_registry_update'/);

  const update = functionBody(source, 'twins_staging_safety_filter_brainstrom_registry_update');
  assert.match(update, /return\s+\$old_value\s*;/);
  assert.match(update, /return\s+\$value\s*;/);
  const add = functionBody(source, 'twins_staging_safety_filter_new_brainstrom_registry');
  assert.match(add, /return\s+array\(\s*\)\s*;/);
  assert.match(add, /return\s+\$value\s*;/);
});

test('WordPress cron is both config-gated and emptied at runtime', () => {
  const source = read(PLUGIN_PATH);
  assert.match(source, /defined\(\s*'DISABLE_WP_CRON'\s*\)/);
  assert.match(source, /DISABLE_WP_CRON\s*!==\s*true/);
  assert.match(source, /add_filter\(\s*'pre_option_cron'\s*,\s*'twins_staging_safety_empty_cron'/);
  assert.match(source, /add_filter\(\s*'pre_schedule_event'\s*,\s*'twins_staging_safety_block_cron_schedule'/);
  assert.match(source, /add_filter\(\s*'pre_schedule_single_event'\s*,\s*'twins_staging_safety_block_cron_schedule'/);
});

test('runbook requires safety installation before restore and gives an explicit rollback', () => {
  const readme = read(README_PATH);
  assert.ok(readme, 'staging-safety runbook is missing');
  assert.match(readme, /install[^\n]*before[^\n]*(?:database|restore)/i);
  assert.match(readme, /WP_ENVIRONMENT_TYPE[^\n]*staging/);
  assert.match(readme, /TWINS_STAGING_SAFETY[^\n]*true/);
  assert.match(readme, /DISABLE_WP_CRON[^\n]*true/);
  assert.match(readme, /Rollback/i);
  assert.match(readme, /production[^\n]*(?:must not|never|do not)/i);
  assert.match(readme, /remove[^\n]*MU plugin/i);
});

test('runbook warns that server-side same-origin HTTP is blocked while browser navigation is unaffected', () => {
  const readme = read(README_PATH);
  const normalized = readme.replace(/\s+/g, ' ');
  assert.match(readme, /hosts[- ]file/i);
  assert.match(normalized, /server-side .*same-origin .*block/i);
  assert.match(normalized, /browser .*(?:navigation|request) .*(?:unaffected|not blocked)/i);
});

test('runbook requires host egress, early drop-in, direct-transport and client-script quarantine', () => {
  const readme = read(README_PATH);
  const normalized = readme.replace(/\s+/g, ' ');
  assert.match(normalized, /MU plugin .*not sufficient/i);
  assert.match(normalized, /hosting.*server.*egress.*deny/i);
  assert.match(normalized, /direct curl/i);
  assert.match(normalized, /PHP mail/i);
  assert.match(normalized, /action queues/i);
  for (const dropin of ['advanced-cache.php', 'object-cache.php', 'db.php', 'sunrise.php']) {
    assert.match(readme, new RegExp(dropin.replace('.', '\\.'), 'i'));
  }
  assert.match(normalized, /(?:disable|inspect).*drop-in.*before the first WordPress request/i);
  for (const client of ['GTM', 'GHL', 'chat', 'analytics']) {
    assert.match(readme, new RegExp(client, 'i'));
  }
  assert.match(normalized, /(?:sanitize|deactivate).*before the first frontend load/i);
});

test('runbook requires independent safety verification for every multisite surface', () => {
  const readme = read(README_PATH);
  const normalized = readme.replace(/\s+/g, ' ');
  assert.match(normalized, /main.*\/wi.*\/ky/i);
  assert.match(normalized, /each site.*URL.*form action.*WPCode.*cron.*queue.*mail.*network/i);
});

test('runbook documents the all-HTTP, shortcode and option fail-closed gates', () => {
  const readme = read(README_PATH);
  const normalized = readme.replace(/\s+/g, ' ');
  assert.match(normalized, /every WordPress server-side HTTP request.*blocked/i);
  assert.doesNotMatch(normalized, /permit only .*Clopay API|Clopay API connections/i);
  assert.match(normalized, /shortcode.*reassert/i);
  assert.match(normalized, /pre-existing.*(?:ordinary|network).*option.*503/i);
  assert.match(normalized, /brainstrom_products.*(?:license|purchase|token|key)/i);
});

test('staging chrome transition pins the staging identity and immutable template manifest', () => {
  const source = read(CHROME_TRANSITION_PATH);
  assert.ok(source, 'staging chrome transition tool is missing');

  assert.match(source, /home_url\(\)/);
  assert.match(source, /https:\/\/danielj140\.sg-host\.com/);
  assert.match(source, /get_current_blog_id\(\)\s*!==\s*1/);
  assert.match(source, /defined\(\s*'WP_ENVIRONMENT_TYPE'\s*\)/);
  assert.match(source, /WP_ENVIRONMENT_TYPE\s*!==\s*'staging'/);
  assert.match(source, /defined\(\s*'TWINS_STAGING_SAFETY'\s*\)/);
  assert.match(source, /TWINS_STAGING_SAFETY\s*!==\s*true/);

  const manifest = [
    [36, 'Header', 'header', 'f433dcb2b40578ee75394c486e7c13b987dc9f0cc20d9c83ab2d9c195996072d'],
    [305, 'POP Menu Template', 'section', '4df9f5ae619f65b8eb4fdb674ee0fffa7b21d4f4ba3577509f1aa1d6b5360341'],
    [7333, 'UNIT 1 DEP — POP MENU 305 twx2 — 2026-07-10', 'section', 'd00c1141386ddcb162200d0767741cd46901336a07e58b0fac2be3fe77605c8d'],
    [7336, 'UNIT 1 CANARY — Header 36 twx2 — 2026-07-10', 'header', 'f158f14cc66da49e7621d0002da7536c38a34e5103abc54e2f83e155e9a743c0'],
    [1409, 'Footer', 'footer', '4db2fe9f8f1fd6772a1b2908faafba3aa3a093a4b6a603ee9465cdb9263be296'],
    [7344, 'UNIT 2 CANARY — Footer 1409 twx2 — 2026-07-10', 'footer', '4db2fe9f8f1fd6772a1b2908faafba3aa3a093a4b6a603ee9465cdb9263be296'],
    [2163, 'Header Contact Us', 'header', '0928a31330c97748fa522910fe065bdc36e8f0b3b57c4aebf472e271dc19b7c7'],
    [2179, 'Footer Contact Us', 'footer', 'a1b6a10f7aa12bbab7d138ca71cf6e30d73e8adf8f9f2b36985f459a8bdeef32']
  ];
  const expectedManifest = `return[${manifest.map(([id, title, type, sha256]) =>
    `${id}=>['title'=>'${title}','type'=>'${type}','dataSha256'=>'${sha256}'],`
  ).join('')}];`;
  assert.equal(compactPhpCode(functionBody(source, 'twins_staging_chrome_manifest')), expectedManifest);

  const identity = compactPhpCode(functionBody(source, 'twins_staging_chrome_assert_identity'));
  assert.ok(identity.includes("if(!defined('WP_ENVIRONMENT_TYPE')||WP_ENVIRONMENT_TYPE!=='staging')"));
  assert.ok(identity.includes("if(!defined('TWINS_STAGING_SAFETY')||TWINS_STAGING_SAFETY!==true)"));
  assert.ok(identity.includes("if(rtrim(home_url(),'/')!=='https://danielj140.sg-host.com')"));
  assert.ok(identity.includes('if(get_current_blog_id()!==1)'));
  assert.equal((source.match(/danielj140\.sg-host\.com/g) || []).length, 1, 'the fixed staging host must have one authority source');
  assert.match(source, /hash\(\s*'sha256'\s*,/);
  const authorityFields = source.match(/['"]productionWriteAuthority['"]\s*=>/g) || [];
  const falseAuthorityFields = source.match(/['"]productionWriteAuthority['"]\s*=>\s*false/g) || [];
  assert.ok(authorityFields.length >= 1, 'production authority receipt is missing');
  assert.equal(authorityFields.length, falseAuthorityFields.length, 'production authority must be literal false in every receipt');
});

test('staging chrome transition fixes all known condition states and one-pass write orders', () => {
  const source = read(CHROME_TRANSITION_PATH);
  assert.ok(source, 'staging chrome transition tool is missing');
  const compact = source.replace(/\s+/g, ' ');
  const canary = [[36, ['include/general', 'exclude/singular/page/6065']], [305, []], [7333, []], [7336, ['include/singular/page/6065']], [1409, ['include/general', 'exclude/singular/page/6065']], [7344, ['include/singular/page/6065']], [2163, []], [2179, ['include/singular/page/2123']]];
  const global = [[36, []], [305, []], [7333, []], [7336, ['include/general']], [1409, []], [7344, ['include/general']], [2163, []], [2179, ['include/singular/page/2123']]];
  const original = [[36, ['include/general']], [305, []], [7333, []], [7336, []], [1409, ['include/general']], [7344, []], [2163, []], [2179, ['include/singular/page/2123']]];
  const phpMap = (entries) => `[${entries.map(([id, conditions]) =>
    `${id}=>[${conditions.map((condition) => `'${condition}'`).join(',')}],`
  ).join('')}]`;
  const expectedMaps = `$canary=${phpMap(canary)};$global=${phpMap(global)};$original=${phpMap(original)};return['CANARY'=>$canary,'GLOBAL'=>$global,'ORIGINAL'=>$original,];`;
  assert.equal(compactPhpCode(functionBody(source, 'twins_staging_chrome_condition_maps')), expectedMaps);

  for (const mode of ['status', 'promote', 'restore-canary', 'rollback']) {
    assert.ok(source.includes(`'${mode}'`), `${mode} mode is missing`);
  }

  assert.match(compact, /'promote'\s*=>\s*\[7336,\s*36,\s*7344,\s*1409\]/);
  assert.match(compact, /'restore-canary'\s*=>\s*\[36,\s*7336,\s*1409,\s*7344\]/);
  assert.match(compact, /'rollback'\s*=>\s*\[36,\s*7336,\s*1409,\s*7344\]/);
  assert.equal((source.match(/->save_conditions\s*\(/g) || []).length, 1, 'writes must pass through one one-shot save call');
  assert.match(source, /explode\(\s*'\/'\s*,\s*\$condition\s*\)/);
  assert.match(source, /twins_staging_chrome_read_conditions\(\s*\$document_id\s*\)/);
  assert.match(source, /TRANSITION_COMPENSATED/);
  assert.doesNotMatch(source, /\bretry\b|\bwhile\s*\(/i);
  assert.doesNotMatch(source, /function\s+twins_staging_chrome_apply_conditions\s*\(/, 'no globally callable write boundary is allowed');
  assert.match(source, /private\s+static\s+function\s+execute\s*\(/);
  assert.match(source, /private\s+static\s+function\s+write_target\s*\(/);
  assert.match(source, /'compensate'\s*=>\s*\[36,\s*7336,\s*1409,\s*7344\]/);
  assert.match(source, /function\s+twins_staging_chrome_cli_exit_code\s*\(/);
  assert.match(source, /TRANSITION_COMPENSATION_FAILED/);
  assert.match(source, /TRANSITION_FAILED/);
});

test('staging chrome transition keeps dry-run actuality separate from projection', () => {
  const source = read(CHROME_TRANSITION_PATH);
  assert.ok(source, 'staging chrome transition tool is missing');

  assert.match(source, /TWINS_STAGING_CHROME_DRY_RUN/);
  assert.match(source, /in_array\(\s*\$dry_run_raw\s*,\s*\[\s*'0'\s*,\s*'1'\s*\]\s*,\s*true\s*\)/);
  assert.match(source, /'stagingMutation'\s*=>\s*false/);
  assert.match(source, /'afterState'\s*=>\s*\$before_state/);
  assert.match(source, /(?:'projectedState'\s*=>|\['projectedState'\]\s*=)\s*\$projected_state/);
  assert.match(source, /'changedDocumentIds'\s*=>\s*\[\s*\]/);
  assert.match(source, /(?:'projectedDocumentIds'\s*=>|\['projectedDocumentIds'\]\s*=)\s*\$projected_document_ids/);
});

test('staging chrome transition has no production authority or integration side channel', () => {
  const source = read(CHROME_TRANSITION_PATH);
  assert.ok(source, 'staging chrome transition tool is missing');

  assert.doesNotMatch(source, /twinsgaragedoors\.com/i);
  assert.doesNotMatch(source, /\$_(?:GET|POST|REQUEST|SERVER|ENV)|\$(?:argv|argc)\b|\bSTDIN\b|php:\/\/stdin|fgets\s*\(|stream_get_contents\s*\(/i);
  assert.doesNotMatch(source, /TWINS_STAGING_CHROME_(?:HOST|BLOG|DOCUMENT|IDS?)/i);
  const getenvNames = Array.from(source.matchAll(/getenv\(\s*['"]([^'"]+)['"]\s*\)/g), (match) => match[1]);
  assert.deepEqual(getenvNames, ['TWINS_STAGING_CHROME_MODE', 'TWINS_STAGING_CHROME_DRY_RUN']);
  assert.equal((source.match(/getenv\s*\(/g) || []).length, getenvNames.length, 'all environment input must be fixed and named');
  assert.doesNotMatch(source, /wpcode|activate_plugin|wp_(?:safe_)?remote_|WP_Http|Requests::|curl_|fsockopen|pfsockopen|stream_socket_client|file_get_contents|\bfopen\s*\(|\breadfile\s*\(|\bmail\s*\(|wp_mail|<form|form[_-]?submit|submit[_-]?form/i);
});

const phpProbe = childProcess.spawnSync('php', ['-v'], { encoding: 'utf8' });
const phpAvailable = phpProbe.status === 0;

test('PHP runtime is required so CI cannot silently skip the WordPress harness', { skip: !process.env.CI }, () => {
  assert.equal(phpAvailable, true, 'PHP CLI is required to execute website/staging-safety/tests/wordpress-harness.php');
});

test('WordPress-stubbed runtime exercises the fail-closed gates and network policy', { skip: !phpAvailable }, () => {
  const result = childProcess.spawnSync('php', [HARNESS_PATH, PLUGIN_PATH], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stdout + result.stderr);
  const report = JSON.parse(result.stdout);
  assert.deepEqual(report.boot, {
    missingEnvironment: 'refused',
    wrongEnvironment: 'refused',
    missingSafetyFlag: 'refused',
    falseSafetyFlag: 'refused',
    missingCronDisable: 'refused',
    preexistingOrdinary: 'refused',
    preexistingNetwork: 'refused',
    preexistingBrainstrom: 'refused',
    configuredStaging: 'booted'
  });
  assert.equal(report.mailShortCircuit, true);
  assert.deepEqual(report.http, {
    sameOriginGet: 'twins_staging_http_blocked',
    sameOriginPost: 'twins_staging_http_blocked',
    clopayGet: 'twins_staging_http_blocked',
    clopayHead: 'twins_staging_http_blocked',
    arbitraryExternal: 'twins_staging_http_blocked'
  });
  assert.equal(report.csp, EXPECTED_CSP_DIRECTIVES.join('; ') + ';');
  assert.match(report.reviewPlaceholder, /Reviews are intentionally disabled on this private staging copy/);
  assert.doesNotMatch(report.reviewPlaceholder, /https?:|<script|<iframe|<form|src=|href=/i);
  assert.doesNotMatch(report.reviewPlaceholder, /attacker|onload/i);
  assert.match(report.integrationPlaceholder, /Interactive product and door-builder integrations are intentionally disabled on this private staging copy/);
  assert.doesNotMatch(report.integrationPlaceholder, /https?:|<script|<iframe|<form|src=|href=|attacker|onload/i);
  assert.equal(report.quarantinedOptionUpdate, false);
  assert.deepEqual(report.quarantinedOptionAdded, ['elementor_connect_site_key']);
  assert.deepEqual(report.quarantinedNetworkOptionAdded, [[7, 'elementor_connect_site_key']]);
  assert.equal(report.quarantinedNetworkPreAdd, false);
  assert.deepEqual(report.ordinaryAddGuard, {
    quarantinedSecret: 'refused',
    quarantinedEmpty: 'allowed',
    brainstromSecret: 'refused',
    brainstromSafe: 'allowed',
    ordinary: 'allowed'
  });
  assert.deepEqual(report.legacyRedirects, {
    madisonPage: '/wi/garage-door-opener-in-madison-wi/',
    madisonException: '/wi/garage-door-services/',
    wiMenu: '/wi/location/madison/',
    kyPagination: '/ky/category/madison/',
    ordinaryMissing: null,
    unsafeRelative: null,
    unsafeTraversal: null
  });
  assert.deepEqual(report.lateShortcodes, {
    brb_collection: 'twins_staging_safety_review_placeholder',
    clopay_product: 'twins_staging_safety_disabled_integration_placeholder',
    clopay_collection_grid: 'twins_staging_safety_disabled_integration_placeholder',
    twins_door_builder: 'twins_staging_safety_disabled_integration_placeholder'
  });
  assert.match(report.shortcodeFailSafe.reviews, /Reviews are intentionally disabled/);
  assert.match(report.shortcodeFailSafe.clopay, /Interactive product and door-builder integrations are intentionally disabled/);
  assert.doesNotMatch(report.shortcodeFailSafe.reviews + report.shortcodeFailSafe.clopay, /attacker/);
  assert.equal(report.shortcodeFailSafe.ordinary, 'unchanged');
  assert.deepEqual(report.brainstrom.safeUpdate, { 'astra-addon': { version: '4.0.0', enabled: true } });
  assert.deepEqual(report.brainstrom.safeAdd, { 'astra-addon': { version: '4.0.0', enabled: true } });
  assert.deepEqual(report.brainstrom.licenseUpdate, { 'astra-addon': { version: '3.9.0' } });
  assert.deepEqual(report.brainstrom.purchaseAdd, []);
  assert.deepEqual(report.brainstrom.tokenAdd, []);
  assert.deepEqual(report.brainstrom.keyAdd, []);
});

test('PHP runtime exercises the staging chrome transition state contract', { skip: !phpAvailable }, () => {
  const result = childProcess.spawnSync('php', [CHROME_TRANSITION_HARNESS_PATH, CHROME_TRANSITION_PATH], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stdout + result.stderr);
  assert.equal(result.stdout.trim(), 'STAGING_CHROME_TRANSITION_HARNESS_OK');
});
