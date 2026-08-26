const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const repository = path.resolve(root, '../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const unescapePhp = value => value.replace(/\\'/g, "'").replace(/\\\\/g, '\\');

// The program figures, pinned verbatim from
// docs/marketing/website-rebuild/data/twinshield-plans.json (Housecall Pro >
// Service plans > Plan templates, read 2026-08-25). HCP's public API does not
// expose service plans, so that file is the source of truth and this is its
// second copy: the docs tree is not checked out everywhere, exactly as
// page-content.test.cjs notes, so the figures are restated here and
// cross-checked against the JSON whenever it IS on disk. A rate that drifts in
// either place fails here instead of reaching a customer.
const sourceOfTruth = path.join(repository, 'docs/marketing/website-rebuild/data/twinshield-plans.json');
const tiers = [
  {
    key: 'core',
    name: 'TwinShield Core',
    tagline: 'Essential Care',
    featured: false,
    monthly: '$12.99',
    monthlyTotal: '$155.88',
    annual: '$149',
    visits: 1,
    jobDiscount: '5%',
    ratePct: 25,
    cap: '$150',
    example: '12 successful monthly payments earn $38.97. One $149 annual payment earns $37.25.',
  },
  {
    key: 'priority',
    name: 'TwinShield Priority',
    tagline: 'Best Value',
    featured: true,
    monthly: '$18.99',
    monthlyTotal: '$227.88',
    annual: '$199',
    visits: 1,
    jobDiscount: '7.5%',
    ratePct: 35,
    cap: '$300',
    example: '12 successful monthly payments earn $79.76. One $199 annual payment earns $69.65.',
  },
  {
    key: 'premier',
    name: 'TwinShield Premier',
    tagline: 'Maximum Care',
    featured: false,
    monthly: '$24.99',
    monthlyTotal: '$299.88',
    annual: '$279',
    visits: 2,
    jobDiscount: '10%',
    ratePct: 50,
    cap: '$500',
    example: '12 successful monthly payments earn $149.94. One $279 annual payment earns $139.50.',
  },
];
const sharedBenefit = '10% off the qualifying repair on the job where this new membership is purchased.';
const billingNote = 'Monthly billing is a payment option for the full 12-month term, not a cancel-anytime membership.';
const creditRules = 'Credit applies toward qualifying new garage-door or opener equipment. Failed, reversed, refunded, or charged-back payments do not earn credit. Credit already used is deducted. The Twins Garage Doors office must verify the exact balance before it is promised or applied.';
const limitations = 'Plan applies to one residential service address and one garage-door system. Unused visits do not roll over. Discounts do not combine unless Twins approves otherwise in writing. TwinShield is a maintenance-and-savings membership, not insurance or a repair/replacement warranty. All benefits and limitations are governed by the TwinShield Membership Agreement.';
// Every currency amount the program may publish. Nothing else may appear.
const approvedAmounts = new Set([
  '12.99', '149', '155.88', '38.97', '37.25', '150',
  '18.99', '199', '227.88', '79.76', '69.65', '300',
  '24.99', '279', '299.88', '149.94', '139.50', '500',
]);

// Quote-delimited PHP strings. The file's opening docblock quotes key names,
// which would throw the pairing off, so scanning starts at the return.
function programStrings(source) {
  const body = source.includes('return [') ? source.slice(source.indexOf('return [')) : source;
  return [...body.matchAll(/'((?:\\'|[^'])*)'/g)].map(match => unescapePhp(match[1]));
}

test('the pinned TwinShield figures still match the Housecall Pro source of truth', { skip: !fs.existsSync(sourceOfTruth) && 'docs tree is not checked out' }, () => {
  const truth = JSON.parse(fs.readFileSync(sourceOfTruth, 'utf8'));
  assert.equal(truth.shared.billing_note, billingNote);
  assert.equal(truth.shared.equipment_credit_rules, creditRules);
  assert.equal(truth.shared.limitations, limitations);
  assert.equal(truth.tiers.length, tiers.length);
  truth.tiers.forEach((source, index) => {
    const pinned = tiers[index];
    assert.equal(source.key, pinned.key);
    assert.equal(source.name, pinned.name);
    assert.equal(source.tagline, pinned.tagline);
    assert.equal(source.featured === true, pinned.featured);
    assert.equal(`$${source.monthly}`, pinned.monthly);
    assert.equal(`$${source.monthly_total}`, pinned.monthlyTotal);
    assert.equal(`$${source.annual}`, pinned.annual);
    assert.equal(source.visits_per_year, pinned.visits);
    assert.equal(`${source.job_discount_pct}%`, pinned.jobDiscount);
    assert.equal(source.equipment_credit.rate_pct, pinned.ratePct);
    assert.equal(`$${source.equipment_credit.max_balance}`, pinned.cap);
    assert.equal(source.equipment_credit.example, pinned.example);
    assert.equal(source.benefits[0], sharedBenefit);
  });
});

test('config/twinshield-program.php publishes the approved program and nothing else', () => {
  const source = read('config/twinshield-program.php');
  const values = programStrings(source);
  const text = values.join('\n');

  // One registered page. The program renders there and nowhere else.
  const paths = [...source.matchAll(/^ {4}'(\/[^']+\/)'\s*=>\s*\[/gm)].map(match => match[1]);
  assert.deepEqual(paths, ['/maintenance-plans/']);

  // Every published amount is an approved program figure.
  for (const amount of text.matchAll(/\$(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/g)) {
    assert.ok(approvedAmounts.has(amount[1].replace(/,/g, '')), `unapproved published rate $${amount[1]}`);
  }
  // House style: full dollar amounts, no em- or en-dashes, one currency form.
  assert.doesNotMatch(text, /\$\d+(?:\.\d+)?k\b/i, 'full dollar amounts only');
  assert.doesNotMatch(text, /—|–/, 'the program carries an em- or en-dash');
  assert.doesNotMatch(text, /\bUSD\b/i, 'published rates use one currency form');

  // The shared enrollment benefit is stated once, not once per tier.
  assert.equal(values.filter(value => value === sharedBenefit).length, 1);
  assert.ok(source.includes(billingNote), 'the 12-month term note is missing');
  assert.ok(source.includes(creditRules), 'the equipment credit rules are missing');

  // The limitations list is the source string split at its own sentence
  // boundaries: joined with one space it is that string, character for
  // character. Nothing is softened, dropped or re-worded.
  const limitsBlock = source.match(/'limitations' => \[([\s\S]*?)\],/);
  assert.ok(limitsBlock, 'the limitations list is missing');
  assert.equal(programStrings(limitsBlock[1]).join(' '), limitations);

  for (const tier of tiers) {
    const block = source.match(new RegExp(`'key' => '${tier.key}',([\\s\\S]*?)'extraLimitation'`));
    assert.ok(block, `${tier.key} tier is missing`);
    const tierText = block[1];
    assert.ok(tierText.includes(`'name' => '${tier.name}'`), `${tier.key} name`);
    assert.ok(tierText.includes(`'tagline' => '${tier.tagline}'`), `${tier.key} tagline`);
    assert.ok(tierText.includes(`'featured' => ${tier.featured},`), `${tier.key} featured flag`);
    assert.ok(tierText.includes(`'monthly' => '${tier.monthly}'`), `${tier.key} monthly rate`);
    assert.ok(tierText.includes(`'annual' => '${tier.annual}'`), `${tier.key} annual rate`);
    assert.ok(tierText.includes(`'creditExample' => '${tier.example}'`), `${tier.key} worked example`);
    // Dual pricing on the card: monthly, the twelve-month total, and the
    // annual option, in full dollars, in one sentence a buyer can check.
    assert.ok(
      tierText.includes(`'terms' => '${tier.monthly} a month for 12 months, ${tier.monthlyTotal} total, or ${tier.annual} paid once.'`),
      `${tier.key} terms line`,
    );
    assert.ok(tierText.includes(`'creditLine' => '${tier.ratePct}% of what you pay, up to ${tier.cap}.'`), `${tier.key} credit line`);
    assert.ok(tierText.includes(`${tier.jobDiscount} off future qualifying repairs while active and current.`), `${tier.key} repair discount`);
  }
  // Exactly one featured tier, and it is the one Housecall Pro calls Best Value.
  assert.equal((source.match(/'featured' => true,/g) || []).length, 1);
  assert.match(source, /'tagline' => 'Best Value',\s*\n\s*'featured' => true,/);
});

test('the TwinShield component is fail-closed and renders the whole offer', () => {
  const component = read('components/twinshield-plan.php');
  for (const marker of [
    'function twins_brand_twinshield_program(string $path): ?array',
    'function twins_brand_twinshield_assert(array $program): void',
    'function twins_brand_twinshield_section(array $program, string $contactHref): string',
    'function twins_brand_twinshield_offer_catalog(array $program): array',
    'const TWINS_TWINSHIELD_RATES',
  ]) assert.ok(component.includes(marker), marker);
  // Same pinned rate set as the registry and this test.
  for (const amount of approvedAmounts) assert.ok(component.includes(`'${amount}'`), `component rate pin ${amount}`);
  // It refuses rather than renders: shape drift, an unapproved amount, an
  // abbreviated amount and markup in plain text all throw.
  for (const refusal of [
    'A TwinShield amount is not an approved program figure.',
    'The TwinShield program abbreviates a dollar amount.',
    'The TwinShield program record has an unknown shape.',
    'The TwinShield program must feature exactly one tier.',
    'A TwinShield tier does not answer every comparison axis.',
  ]) assert.ok(component.includes(refusal), refusal);
  assert.doesNotMatch(component, /\b(?:file_get_contents|fopen|readfile|curl_|stream_|glob|scandir)\b/i);
  // No external literals: the schema node carries no vocabulary URL of its own.
  assert.doesNotMatch(component, /https?:\/\//i);

  // The rendered block: L10 card row with one inverted lead card, the house
  // scroll-rail on the comparison table, the credit callouts, the limits.
  for (const marker of [
    'twins-brand-twinshield-card--lead',
    'twins-brand-twinshield-compare-scroll',
    'role="region"',
    'tabindex="0"',
    'twins-l10-callout',
    'twins-l10-badge',
    'twins-l10-cue',
    'twins-brand-twinshield-limits',
    'scope="col"',
    'scope="row"',
  ]) assert.ok(component.includes(marker), `rendered marker ${marker}`);

  // Structured data for the tiers, hung off the page's own Service node.
  const template = read('templates/service.php');
  assert.match(template, /require_once dirname\(__DIR__\) \. '\/components\/twinshield-plan\.php'/);
  assert.match(template, /\$twinshieldProgram = twins_brand_twinshield_program\(\$serviceNormalizedPath\)/);
  assert.match(template, /\$serviceSchemaService\['hasOfferCatalog'\] = twins_brand_twinshield_offer_catalog\(\$twinshieldProgram\)/);
  assert.match(template, /if \(\$twinshieldProgram !== null\)/);
  // The thin membership block still exists for the page that uses it.
  assert.match(template, /\$servicePlans = \$pageContent\['plans'\]/);
  assert.match(template, /if \(\$servicePlans !== null\)/);
});

test('the TwinShield block rides the container contract and never puts gold on a light ground', () => {
  const css = read('assets/css/twins-brand.css');
  const block = css.slice(css.indexOf('   TwinShield Protection Plan'), css.indexOf('   Verification pass: two corrections'));
  assert.ok(block.length > 2000, 'the TwinShield style block is missing');

  // The section is a top-level band: it takes its gutter from the one
  // container contract and defines no maximum of its own.
  assert.match(block, /\.twins-brand-twinshield \{[^}]*padding: var\(--twins-section-space\) var\(--twins-content-shell\)/);
  assert.doesNotMatch(block, /max\(\s*\d+px,\s*calc\(/, 'the block reintroduced a hard-coded page gutter');
  assert.doesNotMatch(block, /margin-inline:\s*auto/);

  // Deck discipline inside the cards: hairline, 10px radius, no shadow, no
  // gradient, no texture.
  assert.match(block, /\.twins-brand-twinshield-card \{[^}]*border: 1px solid var\(--twins-l10-hairline\)/);
  assert.match(block, /\.twins-brand-twinshield-card \{[^}]*border-radius: var\(--twins-l10-radius\)/);
  assert.match(block, /\.twins-brand-twinshield-card \{[^}]*box-shadow: none/);
  const declarations = block.slice(block.indexOf('*/') + 2);
  assert.doesNotMatch(declarations, /gradient/i, 'the card surfaces took a gradient');
  assert.doesNotMatch(declarations, /box-shadow: [^n]/, 'the card surfaces took a shadow');

  // Gold is the lead card's colour and nothing else's: on the white and cream
  // grounds it is 1.5:1, so the micro-labels there are ochre.
  assert.match(block, /\.twins-brand-twinshield-card__tag \{[^}]*color: var\(--twins-l10-ochre\)/);
  assert.match(block, /--lead \.twins-brand-twinshield-card__tag \{ color: var\(--twins-gold\); \}/);
  assert.match(block, /\.twins-brand-twinshield-card__figure \{[^}]*color: var\(--twins-navy-950\)/);
  assert.match(block, /--lead \.twins-brand-twinshield-card__figure \{ color: var\(--twins-gold\); \}/);

  // The comparison table is the house rail: its own scroll container,
  // contained inline overscroll, a focus ring, and an affordance that appears
  // exactly where the scroll does.
  assert.match(block, /\.twins-brand-twinshield-compare-scroll \{[^}]*overflow-x: auto/);
  assert.match(block, /\.twins-brand-twinshield-compare-scroll \{[^}]*overscroll-behavior-inline: contain/);
  assert.match(block, /\.twins-brand-twinshield-compare-scroll:focus-visible \{[^}]*outline:/);
  assert.match(block, /\.twins-brand-twinshield-table \{[^}]*min-width: 700px/);
  assert.match(block, /@media \(max-width: 755px\)[\s\S]*twins-brand-twinshield-compare-hint \{ display: block; \}/);
  // The row collapses to one column on a phone, as the deck's stat row does.
  assert.match(block, /@media \(max-width: 900px\)[\s\S]*\.twins-brand-twinshield-tiers \{ grid-template-columns: 1fr; \}/);
});
