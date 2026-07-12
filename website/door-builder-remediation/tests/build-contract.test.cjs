const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const childProcess = require('node:child_process');

function read(relative) {
  const file = path.resolve(__dirname, '..', relative);
  return fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '';
}

test('browser app declares the honest reference labels and core dependency', () => {
  const app = read('src/app.js');
  const core = read('src/core.js');
  assert.ok(app.trim().length > 0, 'app source missing');
  assert.match(app, /TwinsDoorBuilderCore/);
  assert.match(app, /TwinsDoorBuilderTransport/);
  assert.match(app, /TwinsDoorBuilderFunnel/);
  assert.match(core, /Inspiration photo/);
  assert.match(core, /Panel-style reference shown at its original resolution/);
  assert.match(app, /core\.selectPreview/);
  assert.match(app, /function imageHTML/);
  assert.match(app, /if \(!image\) return ''/);
});

test('builder imagery uses the explicit no-upscale CSS contract', () => {
  const css = read('src/styles.css');
  const stretchingImage = /\.twxdb[^{}]*img\{[^}]*(?<![-\w])width\s*:\s*100%/;
  assert.ok(css.trim().length > 0, 'CSS source missing');
  assert.match(
    css,
    /\.twxdb img\{[^}]*width:auto[^}]*height:auto[^}]*max-width:min\(100%,var\(--twxdb-natural-width,100%\)\)/
  );
  assert.match('.twxdb-card img{width:100%}', stretchingImage);
  assert.doesNotMatch('.twxdb-card img{max-width:100%}', stretchingImage);
  assert.doesNotMatch(css, stretchingImage);
});

test('intrinsic image widths preserve responsive and component CSS caps', () => {
  const app = read('src/app.js');
  const css = read('src/styles.css');
  assert.match(
    app,
    /style\.setProperty\('--twxdb-natural-width', event\.target\.naturalWidth \+ 'px'\)/
  );
  assert.doesNotMatch(app, /style\.maxWidth/);
  assert.match(
    css,
    /\.twxdb-chip img\{[^}]*max-width:min\(46px,100%,var\(--twxdb-natural-width,46px\)\)/
  );
  assert.match(
    css,
    /\.twxdb-chip--wide img\{[^}]*max-width:min\(100%,var\(--twxdb-natural-width,100%\)\)/
  );
  assert.match(
    css,
    /\.twxdb-pick img\{[^}]*max-width:min\(26px,100%,var\(--twxdb-natural-width,26px\)\)/
  );
});

test('candidate source contains no prohibited visualization promise', () => {
  const source = (read('src/app.js') + '\n' + read('src/styles.css')).toLowerCase();
  assert.ok(source.trim().length > 0, 'candidate source missing');
  for (const phrase of [
    'upload your home',
    'try it on your house',
    'every option live',
    'exact render',
    "here's your door",
    'your design is on its way',
    "you'll see your exact door"
  ]) {
    assert.equal(source.includes(phrase), false, phrase);
  }
});

test('builder delegates payload and submission to tested modules', () => {
  const app = read('src/app.js');
  assert.ok(app.trim().length > 0, 'app source missing');
  assert.match(app, /core\.buildLeadPayload/);
  assert.match(app, /funnel\.submitLead/);
});

test('generated WPCode is one inactive candidate wrapper', () => {
  const php = read('dist/twins-door-builder-wpcode.php');
  assert.equal((php.match(/add_shortcode\(\s*['"]twins_door_builder['"]/g) || []).length, 1);
  assert.equal((php.match(/id="twxdb"/g) || []).length, 1);
  assert.match(php, /CANDIDATE ONLY/);
});

test('local harness contains fixtures but no real lead endpoint', () => {
  const harness = read('dist/local-harness.html');
  assert.match(harness, /TwinsDoorBuilderFixtures/);
  assert.match(harness, /twxdbfail/);
  assert.doesNotMatch(harness, /twinsgaragedoors\.com\/wp-json\/twins\/v1\/door-builder/);
});

test('committed dist matches deterministic generation', () => {
  const script = path.resolve(__dirname, '..', 'scripts', 'build.mjs');
  const result = childProcess.spawnSync(process.execPath, [script, '--check'], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stdout + result.stderr);
});

test('generated funnel candidate boots and configures the current lead form', () => {
  const candidate = read('dist/design-your-door-funnel.js');
  assert.match(candidate, /querySelector\(\"\.twx-db\"\)/);
  assert.match(candidate, /TwinsDoorBuilderFunnel\.bindFunnel/);
  assert.match(candidate, /twinsgaragedoors\.com\/wp-json\/twins\/v1\/door-builder/);
  assert.match(candidate, /successUrl:\"\/door-builder\/\"/);
  assert.match(candidate, /\(833\) 833-2010/);
});
