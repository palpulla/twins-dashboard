const assert = require('node:assert/strict');
const cp = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const nodeTool = path.join(root, 'tools/deploy-private-staging.mjs');
const phpTool = path.join(root, 'tools/private-staging-deploy.php');

test('deployment CLI accepts only four fixed operations and no caller-selected target fields', () => {
  for (const invalid of ['--host=x', '--root=/tmp/x', '--manifest=x', '--expected-old=x', '--retry=2', '--deploy=x']) {
    const result = cp.spawnSync(process.execPath, [nodeTool, invalid], { encoding: 'utf8' });
    assert.equal(result.status, 2, `${invalid}: ${result.stdout}\n${result.stderr}`);
    const output = JSON.parse(result.stdout);
    assert.equal(output.status, 'INVALID_OPERATION');
    assert.equal(output.writeAuthority, false);
    assert.equal(output.productionWriteAuthority, false);
  }
});
test('deployment source fixes application identity, paths, safe transport, and one attempt', () => {
  const nodeSource = fs.readFileSync(nodeTool, 'utf8');
  const phpSource = fs.readFileSync(phpTool, 'utf8');
  const combined = `${nodeSource}\n${phpSource}`;
  assert.match(combined, /https:\/\/danielj140\.sg-host\.com\//);
  assert.match(combined, /\/home\/customer\/www\/danielj140\.sg-host\.com\/public_html/);
  assert.match(combined, /WP_ENVIRONMENT_TYPE/);
  assert.match(nodeSource, /TWINS_STAGE_SSH_TARGET/);
  assert.match(nodeSource, /TWINS_STAGE_SSH_KEY/);
  assert.match(nodeSource, /TWINS_STAGE_SSH_HOSTKEY_SHA256/);
  assert.match(nodeSource, /shell:\s*false/);
  assert.doesNotMatch(combined, /twinsgaragedoors\.com|wp-config|ALTER\s+TABLE|UPDATE\s+wp_/i);
  assert.doesNotMatch(combined, /for\s*\([^)]*(?:retry|attempt)|while\s*\([^)]*(?:retry|attempt)/i);
});

test('missing transport secrets fails closed without revealing values', () => {
  const env = { ...process.env };
  delete env.TWINS_STAGE_SSH_TARGET;
  delete env.TWINS_STAGE_SSH_KEY;
  delete env.TWINS_STAGE_SSH_HOSTKEY_SHA256;
  const result = cp.spawnSync(process.execPath, [nodeTool, '--dry-run'], { encoding: 'utf8', env });
  assert.equal(result.status, 1, result.stderr);
  const output = JSON.parse(result.stdout);
  assert.equal(output.status, 'TRANSPORT_CONFIGURATION_REQUIRED');
  assert.equal(output.writeAuthority, false);
  assert.equal(output.productionWriteAuthority, false);
  assert.equal(result.stdout.includes('TWINS_STAGE_SSH_TARGET='), false);
});
