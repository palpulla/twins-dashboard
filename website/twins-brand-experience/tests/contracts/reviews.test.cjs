const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const field = value => `${Buffer.byteLength(String(value), 'utf8')}:${value}\n`;
const stableId = record => crypto.createHash('sha256').update(
  'twins-review-id-v1\n' + [record.author, record.rating, record.publishedDate, record.text].map(field).join(''),
  'utf8',
).digest('hex');
const recordHash = record => crypto.createHash('sha256').update(
  'twins-review-v1\n' + [
    record.stableId,
    record.author,
    record.rating,
    record.publishedDate,
    record.text,
    record.sourceRecordUrl || '',
  ].map(field).join(''),
  'utf8',
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
  assert.match(capture.capturedAt, /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/);
  const capturedAt = new Date(capture.capturedAt);
  assert.equal(Number.isNaN(capturedAt.getTime()), false);
  assert.ok(capturedAt.getTime() <= Date.now() + 5 * 60 * 1000);
  assert.ok(Date.now() - capturedAt.getTime() <= 90 * 24 * 60 * 60 * 1000);
  assert.equal(capture.recordCount, capture.records.length);
  assert.match(capture.sourceResponseSha256, /^[a-f0-9]{64}$/);
  const raw = fs.readFileSync(path.join(root, 'tests/fixtures/reviews/brb-collection-2178.rendered.html'));
  assert.equal(crypto.createHash('sha256').update(raw).digest('hex'), capture.sourceResponseSha256);
  assert.ok(capture.records.length >= 5);
  for (const record of capture.records) {
    assert.deepEqual(Object.keys(record), [
      'stableId',
      'author',
      'rating',
      'publishedDate',
      'text',
      'sourceRecordUrl',
      'recordSha256',
    ]);
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
  assert.match(source, /method:\s*['"]GET['"]/);
  assert.match(source, /redirect:\s*['"]error['"]/);
  assert.match(source, /process\.argv\.length\s*!==\s*2/);
  assert.doesNotMatch(source, /process\.argv\[[2-9]\]|POST|PUT|PATCH|DELETE|cookie|authorization|api[_-]?key/i);
});

test('capture utility implements the fixed fallback ID encoder independently', () => {
  const source = fs.readFileSync(path.join(root, 'tools/capture-google-reviews.mjs'), 'utf8');
  assert.match(source, /twins-review-id-v1/);
  const vector = {
    author: 'José Example',
    rating: 5,
    publishedDate: '2026-06-30',
    text: 'Exact UTF-8 — review text.',
  };
  assert.equal(stableId(vector), 'b657807000b2e01841f84d9a31dc11a9c5bfed2c7d71d63e695ed339a8749609');
});

test('canonical records without a provider identifier use the fixed fallback ID', () => {
  const capture = JSON.parse(fs.readFileSync(
    path.join(root, 'data/reviews/google-business-reviews-collection-2178.json'),
    'utf8',
  ));
  const raw = fs.readFileSync(
    path.join(root, 'tests/fixtures/reviews/brb-collection-2178.rendered.html'),
    'utf8',
  );
  const providerIds = new Set([
    ...raw.matchAll(/data-(?:review|provider|google-review)-id=["']([^"']+)["']/g),
  ].map(match => match[1]));
  for (const record of capture.records) {
    if (!providerIds.has(record.stableId)) assert.equal(record.stableId, stableId(record));
  }
});
