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
  assert.deepEqual(manifest.assets.map(a => a.logicalName), ['crew-fleet', 'tal-portrait', 'technician-at-work', 'daniel-portrait', 'charles-portrait', 'maurice-portrait', 'nicholas-portrait']);
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

test('self-hosted fonts retain verified bytes and license metadata', () => {
  const manifest = JSON.parse(fs.readFileSync(path.join(root, 'assets/owned-assets.provenance.json'), 'utf8'));
  for (const font of manifest.fonts) {
    assert.equal(digest(font.path), font.sha256);
    assert.equal(font.mime, 'font/woff2');
    assert.match(font.family, /^(Lilita One|Nunito)$/);
    assert.ok(font.license && font.sourceLocator);
  }
});

test('runtime styles never load fonts from Google hosts', () => {
  const runtimeFiles = fs.readdirSync(root, { recursive: true }).map(String)
    .filter(file => /\.css$/i.test(file));
  for (const file of runtimeFiles) {
    const source = fs.readFileSync(path.join(root, file), 'utf8');
    assert.doesNotMatch(source, /fonts\.(?:googleapis|gstatic)\.com/, file);
  }
});

test('exact-byte text references are protected from text normalization', () => {
  const attributes = fs.readFileSync(path.resolve(root, '../../docs/website-overhaul/reference-sources/.gitattributes'), 'utf8');
  assert.match(attributes, /^careers\/\*\.html\.txt binary$/m);
  assert.match(attributes, /^motion-luxe\/2026-07-10-twins-motion-luxe-design\.md binary$/m);
});
