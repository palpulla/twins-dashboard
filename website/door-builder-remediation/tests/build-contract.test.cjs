const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

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
  assert.match(css, /\.twxdb img\{[^}]*width:auto[^}]*height:auto[^}]*max-width:100%/);
  assert.match('.twxdb-card img{width:100%}', stretchingImage);
  assert.doesNotMatch('.twxdb-card img{max-width:100%}', stretchingImage);
  assert.doesNotMatch(css, stretchingImage);
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
