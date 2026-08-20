const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const website = path.resolve(root, '..');
const navData = fs.readFileSync(path.join(root, 'components/nav-data.php'), 'utf8');
const locationContent = fs.readFileSync(path.join(root, 'config/location-content.php'), 'utf8');
const stagingAdapters = fs.readFileSync(
  path.join(website, 'staging-safety/mu-plugins/twins-staging-overhaul/adapters/BrandStagingAdapters.php'),
  'utf8',
);

// ---------------------------------------------------------------------------
// The navigation tree is the one place where "which towns exist" and "which
// towns the menu advertises" could silently drift apart. This file makes the
// rule mechanical: a town is in the header menu if and only if it has a record
// in config/location-content.php, filed under that record's own metro. Every
// other provisioned town belongs on the metro hub page, never in the menu.
// ---------------------------------------------------------------------------

function parseMetroTree(source) {
  const treeStart = source.indexOf('$twinsNavMetroTree = [');
  assert.notEqual(treeStart, -1, 'nav-data.php no longer defines $twinsNavMetroTree');
  const treeEnd = source.indexOf('\n];', treeStart);
  assert.notEqual(treeEnd, -1, '$twinsNavMetroTree is not terminated');
  const body = source.slice(treeStart, treeEnd);

  const metros = new Map();
  const metroPattern = /^    '([a-z-]+)' => \[\n([\s\S]*?)\n    \],$/gm;
  for (const match of body.matchAll(metroPattern)) {
    const [, key, block] = match;
    const scalar = name => {
      const found = block.match(new RegExp(`'${name}' => '((?:[^'\\\\]|\\\\.)*)'`));
      assert.notEqual(found, null, `metro ${key} is missing '${name}'`);
      return found[1];
    };
    const listStart = block.indexOf("'towns' => [");
    assert.notEqual(listStart, -1, `metro ${key} is missing 'towns'`);
    const towns = [...block.slice(listStart).matchAll(/\['((?:[^'\\]|\\.)*)', '(city-[a-z0-9-]+)'\]/g)]
      .map(([, label, routeKey]) => ({ label: label.replace(/\\'/g, "'"), routeKey }));
    const featuredBlock = block.match(/'featured' => \[([^\]]*)\]/);
    assert.notEqual(featuredBlock, null, `metro ${key} is missing 'featured'`);
    const featured = [...featuredBlock[1].matchAll(/'(city-[a-z0-9-]+)'/g)].map(([, routeKey]) => routeKey);
    metros.set(key, {
      key,
      label: scalar('label'),
      market: scalar('market'),
      hubLabel: scalar('hubLabel'),
      hubAnchor: scalar('hubAnchor'),
      towns,
      featured,
    });
  }
  assert.equal(metros.size > 0, true, 'no metros parsed out of $twinsNavMetroTree');
  return metros;
}

function parseLocationRecords(source) {
  const records = new Map();
  const pattern = /^    '([a-z0-9-]+)' => \[\n([\s\S]*?)\n    \],$/gm;
  for (const match of source.matchAll(pattern)) {
    const [, slug, block] = match;
    const metro = block.match(/'metro' => '([a-z-]+)'/);
    if (!metro) continue;
    const jobs = block.match(/'completedJobs' => (\d+|null)/);
    records.set(slug, {
      slug,
      metro: metro[1],
      completedJobs: jobs && jobs[1] !== 'null' ? Number(jobs[1]) : null,
    });
  }
  assert.equal(records.size > 0, true, 'no records parsed out of location-content.php');
  return records;
}

const metros = parseMetroTree(navData);
const records = parseLocationRecords(locationContent);
const treeTowns = [...metros.values()].flatMap(metro =>
  metro.towns.map(town => ({ ...town, metroKey: metro.key, market: metro.market })));

test('every town in the service-area tree has an approved copy record', () => {
  for (const town of treeTowns) {
    const slug = town.routeKey.replace(/^city-/, '');
    assert.equal(
      records.has(slug),
      true,
      `${town.routeKey} is in the header menu but has no record in config/location-content.php`,
    );
  }
});

test('every approved copy record appears exactly once in the service-area tree', () => {
  const seen = new Map();
  for (const town of treeTowns) {
    const slug = town.routeKey.replace(/^city-/, '');
    assert.equal(seen.has(slug), false, `${slug} is filed under two metros`);
    seen.set(slug, town);
  }
  const missing = [...records.keys()].filter(slug => !seen.has(slug));
  assert.deepEqual(missing, [], 'towns with approved copy that the header menu hides');
  assert.equal(seen.size, records.size);
});

test('each town is filed under the metro its own record names', () => {
  for (const town of treeTowns) {
    const record = records.get(town.routeKey.replace(/^city-/, ''));
    assert.equal(
      town.metroKey,
      record.metro,
      `${town.routeKey} is in the ${town.metroKey} column but its record says ${record.metro}`,
    );
  }
});

test('each metro is owned by the market whose route table holds its city routes', () => {
  // BrandStagingAdapters::ROUTES is one route map per market key, keyed by the
  // same portable keys the registry uses. Slice it on the top-level market
  // boundaries so a route key can never be credited to the wrong market.
  const routesStart = stagingAdapters.indexOf('private const ROUTES = [');
  assert.notEqual(routesStart, -1, 'could not find BrandStagingAdapters::ROUTES');
  const routesBody = stagingAdapters.slice(routesStart);
  const boundaries = [...routesBody.matchAll(/^ {8}'(main|wi|ky|il-preview)' => \[$/gm)];
  assert.equal(boundaries.length, 4, 'could not read the four staging route tables');
  const marketBlocks = new Map();
  boundaries.forEach((boundary, index) => {
    const end = index + 1 < boundaries.length ? boundaries[index + 1].index : routesBody.length;
    marketBlocks.set(boundary[1], routesBody.slice(boundary.index, end));
  });
  for (const metro of metros.values()) {
    const block = marketBlocks.get(metro.market);
    assert.notEqual(block, undefined, `metro ${metro.key} names an unknown market ${metro.market}`);
    for (const town of metro.towns) {
      assert.equal(
        block.includes(`'${town.routeKey}' =>`),
        true,
        `${town.routeKey} is filed under market ${metro.market}, which does not own that route`,
      );
    }
  }
});

test('the retired Kentucky market is absent from the whole navigation data file', () => {
  assert.doesNotMatch(navData, /city-lexington|Lexington/);
  for (const metro of metros.values()) {
    assert.notEqual(metro.market, 'ky', `metro ${metro.key} points at the retired Kentucky market`);
  }
  // $twinsNavCityLinks must not carry a 'ky' block either. The 'ky' entry in
  // $twinsNavServiceAvailability stays: it is archive for the blog-3 subsite,
  // is unreachable behind the r33 redirect, and advertises nothing.
  const cityLinksStart = navData.indexOf('$twinsNavCityLinks = [');
  assert.notEqual(cityLinksStart, -1);
  const cityLinks = navData.slice(cityLinksStart, navData.indexOf('\n];', cityLinksStart));
  assert.doesNotMatch(cityLinks, /^ {4}'ky' => \[/m);
});

test("Madison's featured towns are exactly the records with 50+ completed jobs", () => {
  const madison = metros.get('madison');
  assert.notEqual(madison, undefined);
  const expected = [...records.values()]
    .filter(record => record.metro === 'madison' && record.completedJobs !== null && record.completedJobs >= 50)
    .map(record => `city-${record.slug}`)
    .sort();
  assert.deepEqual([...madison.featured].sort(), expected);
});

test('every featured route key is a town of its own metro', () => {
  for (const metro of metros.values()) {
    const known = new Set(metro.towns.map(town => town.routeKey));
    for (const routeKey of metro.featured) {
      assert.equal(known.has(routeKey), true, `${routeKey} is featured in ${metro.key} but is not one of its towns`);
    }
  }
});

test('each metro lists its hub city first, then strict alphabetical order', () => {
  const hubCityByMetro = { madison: 'Madison', milwaukee: 'Milwaukee', rockford: 'Rockford' };
  for (const metro of metros.values()) {
    assert.equal(metro.towns[0].label, hubCityByMetro[metro.key], `${metro.key} does not lead with its hub city`);
    const tail = metro.towns.slice(1).map(town => town.label);
    const sorted = [...tail].sort((a, b) => a.toLowerCase().localeCompare(b.toLowerCase(), 'en'));
    assert.deepEqual(tail, sorted, `${metro.key} town list is not alphabetical after its hub city`);
  }
});

test('every metro carries a hub row whose anchor the hub page can render', () => {
  const locationIndex = fs.readFileSync(path.join(root, 'templates/location-index.php'), 'utf8');
  assert.match(locationIndex, /ltrim\(\$twinsNavMetro\['hubAnchor'\], '#'\)/);
  for (const metro of metros.values()) {
    assert.match(metro.hubAnchor, /^#[a-z-]+$/, `${metro.key} hub anchor is not a plain fragment`);
    assert.equal(metro.hubLabel.length > 0, true);
  }
});

test('the service-area menu never carries a phone number or a service name', () => {
  // Per-metro phones are the market disclosure's exclusive job, and a service
  // name in an area label would break the exact-count pins in
  // renderer-contract-harness.php.
  const header = fs.readFileSync(path.join(root, 'components/header.php'), 'utf8');
  const areasStart = header.indexOf('twins-brand-nav-group--areas');
  const areasEnd = header.indexOf('</nav>', areasStart);
  const areas = header.slice(areasStart, areasEnd);
  assert.doesNotMatch(areas, /tel:|phoneDisplay|phoneHref/);
  assert.doesNotMatch(areas, /Garage Door Repair|Garage Door Installation|Garage Door Openers/);
  assert.doesNotMatch(areas, /role="menu"|role="menuitem"|aria-haspopup/);
});
