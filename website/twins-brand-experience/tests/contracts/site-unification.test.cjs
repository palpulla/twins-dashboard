const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../../../..');
const source = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8');

const functionBody = (contents, name) => {
  const start = contents.indexOf(`function ${name}`);
  assert.notEqual(start, -1, `${name} is missing`);
  const brace = contents.indexOf('{', start);
  assert.notEqual(brace, -1, `${name} has no body`);

  let depth = 0;
  let quote = null;
  let escaped = false;
  for (let index = brace; index < contents.length; index += 1) {
    const character = contents[index];
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
      if (depth === 0) return contents.slice(brace + 1, index);
    }
  }
  assert.fail(`${name} body is not balanced`);
};

test('all non-campaign chrome routes use the portable brand header and footer', () => {
  const components = source('website/staging-safety/mu-plugins/twins-staging-overhaul/components.php');
  assert.match(components, /function twins_overhaul_uses_brand_chrome/);
  assert.match(components, /classification\s*!==\s*['"]campaign-preserve['"]/);
  assert.doesNotMatch(components, /home-brand'.*team-brand'.*careers-brand'.*reviews-brand'.*contact-brand/s);
});

test('brand assets use independently derived bounded SHA-256 versions and fail closed', () => {
  const renderers = source('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  const body = functionBody(renderers, 'twins_overhaul_brand_asset_version');
  const versions = {
    css: crypto.createHash('sha256')
      .update(fs.readFileSync(path.join(root, 'website/twins-brand-experience/assets/css/twins-brand.css')))
      .digest('hex')
      .slice(0, 16),
    familyCss: crypto.createHash('sha256')
      .update(fs.readFileSync(path.join(root, 'website/twins-brand-experience/assets/css/twins-brand-families.css')))
      .digest('hex')
      .slice(0, 16),
    js: crypto.createHash('sha256')
      .update(fs.readFileSync(path.join(root, 'website/twins-brand-experience/assets/js/twins-brand.js')))
      .digest('hex')
      .slice(0, 16),
    builderJs: crypto.createHash('sha256')
      .update(fs.readFileSync(path.join(root, 'website/twins-brand-experience/assets/js/twins-builder.js')))
      .digest('hex')
      .slice(0, 16),
  };

  assert.deepEqual(versions, {
    css: '4331facaffc57fbc',
    familyCss: '78438c13eea7716e',
    js: 'a27a7a219e280a80',
    builderJs: 'ac571c9244f2fdca',
  });
  assert.match(body, /\$digest\s*=\s*@hash_file\(\s*['"]sha256['"]\s*,\s*\$path\s*\)/);
  assert.match(
    body,
    /if\s*\(\s*!is_string\(\$digest\)\s*\|\|\s*preg_match\([^;]+,\s*\$digest\)\s*!==\s*1\s*\)\s*\{[^}]*twins_overhaul_refuse_route\(\s*['"]brand asset hash is unavailable\.['"]\s*\)/,
  );
  assert.match(body, /return\s+substr\(\s*\$digest\s*,\s*0\s*,\s*16\s*\)/);
  assert.doesNotMatch(renderers, /twins-brand\.css'[^;]*,\s*['"]1['"]/);
  assert.doesNotMatch(renderers, /twins-brand\.js'[^;]*,\s*['"]1['"]/);
});

test('only the exact-preserve campaign keeps the recovered global family assets', () => {
  const renderers = source('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  const legacy = functionBody(renderers, 'twins_overhaul_uses_legacy_family_assets');
  const enqueue = functionBody(renderers, 'twins_overhaul_enqueue_assets');
  assert.match(legacy, /return\s+\$classification\s*===\s*['"]campaign-preserve['"]/);
  assert.doesNotMatch(legacy, /cost-madison|cost-milwaukee|builder|catalog-preserve/);
  assert.match(enqueue, /twins-brand-families/);
  assert.match(enqueue, /twins-builder/);
  assert.match(renderers, /wp_dequeue_style\(['"]twins-staging-twx-v2['"]\)/);
});

test('portable asset isolation registers after every MU plugin has loaded', () => {
  const renderers = source('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  const lateRegistration = functionBody(renderers, 'twins_overhaul_register_inert_response_boundary');
  const initialRegistration = functionBody(renderers, 'twins_overhaul_register_frontend_hooks');
  assert.match(lateRegistration, /add_action\(\s*['"]wp_enqueue_scripts['"]\s*,\s*['"]twins_overhaul_enqueue_assets['"]\s*,\s*PHP_INT_MAX/);
  assert.doesNotMatch(initialRegistration, /add_action\(\s*['"]wp_enqueue_scripts['"]/);
});
