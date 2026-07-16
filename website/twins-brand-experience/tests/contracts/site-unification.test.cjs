const assert = require('node:assert/strict');
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

test('brand assets use a content-derived version', () => {
  const renderers = source('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  assert.match(renderers, /function twins_overhaul_brand_asset_version/);
  assert.doesNotMatch(renderers, /twins-brand\.css'[^;]*,\s*['"]1['"]/);
  assert.doesNotMatch(renderers, /twins-brand\.js'[^;]*,\s*['"]1['"]/);
});

test('ordinary brand routes do not enqueue the recovered global visual kit', () => {
  const renderers = source('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  assert.match(renderers, /campaign-preserve/);
  assert.match(renderers, /cost-madison|cost-milwaukee|builder/);
  assert.match(renderers, /in_array\(\s*\$classification\s*,\s*array\(\s*['"]cost-madison['"]\s*,\s*['"]cost-milwaukee['"]\s*,\s*['"]builder['"]\s*\)\s*,\s*true\s*\)/);
  assert.match(renderers, /wp_dequeue_style\(['"]twins-staging-twx-v2['"]\)/);
});

test('portable asset isolation registers after every MU plugin has loaded', () => {
  const renderers = source('website/staging-safety/mu-plugins/twins-staging-overhaul/renderers.php');
  const lateRegistration = functionBody(renderers, 'twins_overhaul_register_inert_response_boundary');
  const initialRegistration = functionBody(renderers, 'twins_overhaul_register_frontend_hooks');
  assert.match(lateRegistration, /add_action\(\s*['"]wp_enqueue_scripts['"]\s*,\s*['"]twins_overhaul_enqueue_assets['"]\s*,\s*PHP_INT_MAX/);
  assert.doesNotMatch(initialRegistration, /add_action\(\s*['"]wp_enqueue_scripts['"]/);
});
