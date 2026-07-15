import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const SOURCE_URL = 'https://twinsgaragedoors.com/wi/reviews/';
const MULTISITE_PATH = '/wi/';
const PAGE_ID = 2186;
const COLLECTION_ID = 2178;
const MAX_RESPONSE_BYTES = 5 * 1024 * 1024;
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const field = value => `${Buffer.byteLength(String(value), 'utf8')}:${value}\n`;
const fallbackStableId = record => crypto.createHash('sha256').update(
  'twins-review-id-v1\n' + [record.author, record.rating, record.publishedDate, record.text].map(field).join(''),
  'utf8',
).digest('hex');
const recordSha256 = record => crypto.createHash('sha256').update(
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

function decodeEntities(value) {
  const named = new Map([
    ['amp', '&'],
    ['apos', "'"],
    ['gt', '>'],
    ['lt', '<'],
    ['nbsp', '\u00a0'],
    ['quot', '"'],
  ]);
  return value.replace(/&(#(?:x[0-9a-f]+|[0-9]+)|[a-z]+);/gi, (entity, token) => {
    if (token[0] !== '#') {
      const decoded = named.get(token.toLowerCase());
      if (decoded === undefined) throw new Error(`Unsupported named entity: ${entity}`);
      return decoded;
    }
    const hex = token[1].toLowerCase() === 'x';
    const codePoint = Number.parseInt(token.slice(hex ? 2 : 1), hex ? 16 : 10);
    if (!Number.isInteger(codePoint) || codePoint < 0 || codePoint > 0x10ffff) {
      throw new Error(`Invalid numeric entity: ${entity}`);
    }
    return String.fromCodePoint(codePoint);
  });
}

function textContent(fragment) {
  const withBreaks = fragment.replace(/<br\s*\/?\s*>/gi, '\n');
  if (/<[^>]+>/.test(withBreaks)) throw new Error('Ambiguous nested review text markup.');
  return decodeEntities(withBreaks).trim();
}

function attribute(tag, name) {
  const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const matches = [...tag.matchAll(new RegExp(`(?:^|\\s)${escaped}\\s*=\\s*(["'])(.*?)\\1`, 'gi'))];
  if (matches.length > 1) throw new Error(`Duplicate ${name} attribute.`);
  return matches.length === 1 ? decodeEntities(matches[0][2]) : null;
}

function classTokens(tag) {
  const value = attribute(tag, 'class');
  return value === null ? [] : value.trim().split(/\s+/).filter(Boolean);
}

function hasClasses(tag, required) {
  const tokens = new Set(classTokens(tag));
  return required.every(token => tokens.has(token));
}

function openingTags(html, name) {
  return [...html.matchAll(new RegExp(`<${name}\\b[^>]*>`, 'gi'))];
}

function balancedElement(html, start, name) {
  const pattern = new RegExp(`<\\/?${name}\\b[^>]*>`, 'gi');
  pattern.lastIndex = start;
  let depth = 0;
  let match;
  while ((match = pattern.exec(html)) !== null) {
    if (match.index === start && match[0][1] === '/') throw new Error(`Invalid ${name} root.`);
    depth += match[0][1] === '/' ? -1 : 1;
    if (depth === 0) return html.slice(start, pattern.lastIndex);
    if (depth < 0) break;
  }
  throw new Error(`Unbalanced ${name} markup.`);
}

function uniqueClassElement(html, name, requiredClasses) {
  const matches = openingTags(html, name).filter(match => hasClasses(match[0], requiredClasses));
  if (matches.length !== 1) throw new Error(`Expected one ${requiredClasses.join('.')} element, found ${matches.length}.`);
  return balancedElement(html, matches[0].index, name);
}

function uniqueInnerHtml(html, name, requiredClasses) {
  const element = uniqueClassElement(html, name, requiredClasses);
  const openEnd = element.indexOf('>') + 1;
  return element.slice(openEnd, element.length - (`</${name}>`.length));
}

function extractProviderId(card) {
  const values = new Set();
  for (const tag of card.matchAll(/<[^/!][^>]*>/g)) {
    for (const key of ['data-review-id', 'data-provider-id', 'data-google-review-id']) {
      const value = attribute(tag[0], key);
      if (value !== null && value !== '') values.add(value);
    }
  }
  if (values.size > 1) throw new Error('Ambiguous provider review identifier.');
  return values.size === 1 ? [...values][0] : '';
}

function parseDisplayedDate(timeTag, displayed) {
  const match = /^(\d{2}):(\d{2}) (\d{2}) ([A-Z][a-z]{2}) (\d{2})$/.exec(displayed);
  if (!match) throw new Error(`Review date is not an exact displayed calendar date: ${displayed}`);
  const unix = attribute(timeTag, 'data-time');
  if (!unix || !/^\d{9,12}$/.test(unix)) throw new Error('Review date lacks an exact provider timestamp.');
  const date = new Date(Number(unix) * 1000);
  if (Number.isNaN(date.getTime())) throw new Error('Review provider timestamp is invalid.');
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const expected = [
    String(date.getUTCHours()).padStart(2, '0') + ':' + String(date.getUTCMinutes()).padStart(2, '0'),
    String(date.getUTCDate()).padStart(2, '0'),
    months[date.getUTCMonth()],
    String(date.getUTCFullYear()).slice(-2),
  ].join(' ');
  if (displayed !== expected) throw new Error(`Displayed review date conflicts with provider timestamp: ${displayed}`);
  return date.toISOString().slice(0, 10);
}

function parseCollection(html) {
  const pluginVersions = new Set();
  const assetPattern = /https:\/\/twinsgaragedoors\.com\/wi\/wp-content\/plugins\/business-reviews-bundle\/assets\/(?:css|js)\/[a-z0-9._-]+\.(?:css|js)\?ver=([^"'&\s>]+)/gi;
  for (const match of html.matchAll(assetPattern)) pluginVersions.add(decodeEntities(match[1]));
  if (pluginVersions.size !== 1) throw new Error(`Expected one Business Reviews Bundle version, found ${pluginVersions.size}.`);

  const wrappers = openingTags(html, 'div').filter(match => (
    attribute(match[0], 'data-id') === String(COLLECTION_ID) && hasClasses(match[0], ['rplg'])
  ));
  if (wrappers.length !== 1) throw new Error(`Expected one collection-${COLLECTION_ID} wrapper, found ${wrappers.length}.`);
  const wrapper = balancedElement(html, wrappers[0].index, 'div');

  const businessLinks = openingTags(wrapper, 'a').filter(match => {
    const element = balancedElement(wrapper, match.index, 'a');
    return textContent(element.slice(element.indexOf('>') + 1, -4)) === 'See all reviews';
  });
  if (businessLinks.length !== 1) throw new Error(`Expected one business Reviews URL, found ${businessLinks.length}.`);
  const businessReviewsUrl = attribute(businessLinks[0][0], 'href');
  if (!businessReviewsUrl || !/^https:\/\//.test(businessReviewsUrl)) throw new Error('Business Reviews URL must use HTTPS.');

  const reviewsGrid = uniqueClassElement(wrapper, 'div', ['rplg-grid-row', 'rplg-reviews']);
  const cards = openingTags(reviewsGrid, 'div')
    .filter(match => hasClasses(match[0], ['rplg-col']) && classTokens(match[0]).some(token => /^rplg-col-\d+$/.test(token)))
    .map(match => balancedElement(reviewsGrid, match.index, 'div'));
  if (cards.length < 5) throw new Error('Fewer than five exact review records were rendered.');
  if (cards.length > 500) throw new Error('Review collection exceeds the fixed capture bound.');

  const ids = new Set();
  const records = cards.map(card => {
    const nameElement = uniqueClassElement(card, 'a', ['rplg-review-name']);
    const nameTag = nameElement.slice(0, nameElement.indexOf('>') + 1);
    const author = textContent(nameElement.slice(nameElement.indexOf('>') + 1, -4));
    if (author === '') throw new Error('Review author is empty.');

    const stars = uniqueClassElement(card, 'div', ['rplg-stars']);
    const starsTag = stars.slice(0, stars.indexOf('>') + 1);
    const info = attribute(starsTag, 'data-info');
    const ratingMatch = /^([1-5]),/.exec(info || '');
    if (!ratingMatch) throw new Error('Review rating is not an integer from one through five.');
    const rating = Number(ratingMatch[1]);

    const timeElement = uniqueClassElement(card, 'div', ['rplg-review-time']);
    const timeTag = timeElement.slice(0, timeElement.indexOf('>') + 1);
    const displayedDate = textContent(timeElement.slice(timeElement.indexOf('>') + 1, -6));
    const publishedDate = parseDisplayedDate(timeTag, displayedDate);

    const text = textContent(uniqueInnerHtml(card, 'span', ['rplg-review-text']));
    if (text === '') throw new Error('Review text is empty.');

    const linkedUrl = attribute(nameTag, 'href') || '';
    const sourceRecordUrl = linkedUrl !== businessReviewsUrl ? linkedUrl : '';
    if (sourceRecordUrl !== '' && !/^https:\/\//.test(sourceRecordUrl)) throw new Error('Per-review source URL must use HTTPS.');
    const providerId = extractProviderId(card);
    const base = { author, rating, publishedDate, text };
    const stableId = providerId || fallbackStableId(base);
    if (stableId === '' || stableId.length > 256 || /[\x00-\x1f\x7f]/.test(stableId)) throw new Error('Review stable ID is invalid.');
    if (ids.has(stableId)) throw new Error('Duplicate review stable ID.');
    ids.add(stableId);
    const record = { stableId, author, rating, publishedDate, text, sourceRecordUrl };
    return { ...record, recordSha256: recordSha256(record) };
  });

  return {
    providerVersion: [...pluginVersions][0],
    businessReviewsUrl,
    records,
  };
}

async function atomicReplace(filePath, contents) {
  await fs.mkdir(path.dirname(filePath), { recursive: true });
  const temporary = path.join(path.dirname(filePath), `.${path.basename(filePath)}.${process.pid}.tmp`);
  await fs.writeFile(temporary, contents, { flag: 'wx' });
  await fs.rename(temporary, filePath);
}

if (process.argv.length !== 2) throw new Error('This utility accepts no arguments.');
const response = await fetch(SOURCE_URL, { method: 'GET', redirect: 'error', headers: { accept: 'text/html' } });
if (!response.ok || response.url !== SOURCE_URL) throw new Error(`Fixed review source failed: ${response.status}`);
const bytes = Buffer.from(await response.arrayBuffer());
if (bytes.length === 0 || bytes.length > MAX_RESPONSE_BYTES) throw new Error('Fixed review source exceeded the capture byte boundary.');
const html = bytes.toString('utf8');
if (Buffer.from(html, 'utf8').compare(bytes) !== 0) throw new Error('Fixed review source was not valid UTF-8.');
const parsed = parseCollection(html);
const sourceResponseSha256 = crypto.createHash('sha256').update(bytes).digest('hex');
if (!/^[a-f0-9]{64}$/.test(sourceResponseSha256)) throw new Error('Source response hash was absent.');
const capturedAt = new Date().toISOString().replace(/\.\d{3}Z$/, 'Z');
const envelope = {
  schemaVersion: 1,
  sourceUrl: SOURCE_URL,
  businessReviewsUrl: parsed.businessReviewsUrl,
  multisitePath: MULTISITE_PATH,
  pageId: PAGE_ID,
  collectionId: COLLECTION_ID,
  providerVersion: parsed.providerVersion,
  capturedAt,
  sourceResponseSha256,
  recordCount: parsed.records.length,
  records: parsed.records,
};

await atomicReplace(
  path.join(root, 'tests/fixtures/reviews/brb-collection-2178.rendered.html'),
  bytes,
);
await atomicReplace(
  path.join(root, 'data/reviews/google-business-reviews-collection-2178.json'),
  `${JSON.stringify(envelope, null, 2)}\n`,
);
process.stdout.write(`Captured ${envelope.recordCount} verified reviews from collection ${COLLECTION_ID}.\n`);
