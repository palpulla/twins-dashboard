<?php
declare(strict_types=1);

/**
 * The TwinShield Protection Plan block: the tier row, the comparison table,
 * the equipment credit, and the limitations panel.
 *
 * Data comes from config/twinshield-program.php, which is a verbatim
 * transcription of docs/marketing/website-rebuild/data/twinshield-plans.json
 * (Housecall Pro > Service plans > Plan templates, read 2026-08-25). This
 * component publishes money, so it is fail-closed the way PageContentRegistry
 * is: a record with a missing key, an unexpected shape, or a dollar figure
 * outside the approved program set throws instead of rendering. A page that
 * has no program record renders exactly as it did before.
 *
 * The thin `plans` block in templates/service.php is untouched and still
 * serves /protection-plans/; this is a second, richer block that only the
 * paths listed in the program config receive.
 */

/**
 * Every currency amount this program may publish, pinned to the approved
 * figures. Tier order: Core, Priority, Premier. Same set as
 * PageContentRegistry::MEMBERSHIP_RATES, restated here because this component
 * must be able to refuse on its own.
 */
const TWINS_TWINSHIELD_RATES = [
    '12.99', '149', '155.88', '38.97', '37.25', '150',
    '18.99', '199', '227.88', '79.76', '69.65', '300',
    '24.99', '279', '299.88', '149.94', '139.50', '500',
];

/**
 * Resolve the program record for a page path, or null when the page does not
 * publish the program.
 *
 * @param string $path Request path, with or without a market prefix.
 * @return array|null
 */
function twins_brand_twinshield_program(string $path): ?array
{
    // Market pages carry their route prefix (/wi/..., /ky/..., /il/...); the
    // program is keyed by the market-neutral service path, as page content is.
    $normalized = '/' . trim(preg_replace('~^/(?:wi|ky|il)/~', '/', '/' . ltrim($path, '/')), '/') . '/';
    $file = dirname(__DIR__) . '/config/twinshield-program.php';
    if (!is_file($file)) {
        return null;
    }
    $catalog = require $file;
    if (!is_array($catalog) || !isset($catalog[$normalized])) {
        return null;
    }
    $program = $catalog[$normalized];
    if (!is_array($program)) {
        throw new DomainException('The TwinShield program record is invalid.');
    }
    twins_brand_twinshield_assert($program);
    return $program;
}

/**
 * Fail closed on any drift in the program record.
 *
 * @param array $program Program record.
 * @return void
 */
function twins_brand_twinshield_assert(array $program): void
{
    $required = [
        'kicker', 'heading', 'deck', 'term', 'sharedLabel', 'billingNote', 'sharedBenefit',
        'creditHeading', 'creditDeck', 'creditRules', 'limitsHeading', 'limitations',
        'compareHeading', 'compareAxes', 'tiers',
    ];
    $keys = array_keys($program);
    sort($keys);
    $expected = $required;
    sort($expected);
    if ($keys !== $expected) {
        throw new DomainException('The TwinShield program record has an unknown shape.');
    }
    foreach ([
        'kicker', 'heading', 'deck', 'term', 'sharedLabel', 'billingNote', 'sharedBenefit',
        'creditHeading', 'creditDeck', 'creditRules', 'limitsHeading', 'compareHeading',
    ] as $field) {
        if (!is_string($program[$field]) || trim($program[$field]) === '' || strlen($program[$field]) > 600) {
            throw new DomainException('A TwinShield program field is invalid: ' . $field);
        }
    }
    if (!is_array($program['limitations']) || count($program['limitations']) < 3) {
        throw new DomainException('The TwinShield limitations list is invalid.');
    }
    if (!is_array($program['compareAxes']) || count($program['compareAxes']) !== 5) {
        throw new DomainException('The TwinShield comparison axes are invalid.');
    }
    if (!is_array($program['tiers']) || count($program['tiers']) !== 3) {
        throw new DomainException('The TwinShield program must carry exactly three tiers.');
    }

    $featured = 0;
    foreach ($program['tiers'] as $tier) {
        if (!is_array($tier)) {
            throw new DomainException('A TwinShield tier is invalid.');
        }
        $tierKeys = array_keys($tier);
        sort($tierKeys);
        $tierExpected = [
            'key', 'name', 'tagline', 'featured', 'monthly', 'monthlyTotal', 'annual',
            'terms', 'benefits', 'creditLine', 'creditExample', 'compare', 'extraLimitation',
        ];
        sort($tierExpected);
        if ($tierKeys !== $tierExpected) {
            throw new DomainException('A TwinShield tier has an unknown shape.');
        }
        foreach (['key', 'name', 'tagline', 'monthly', 'monthlyTotal', 'annual', 'terms', 'creditLine', 'creditExample'] as $field) {
            if (!is_string($tier[$field]) || trim($tier[$field]) === '') {
                throw new DomainException('A TwinShield tier field is invalid: ' . $field);
            }
        }
        if (!is_bool($tier['featured'])) {
            throw new DomainException('A TwinShield tier featured flag is invalid.');
        }
        $featured += $tier['featured'] ? 1 : 0;
        if (!is_array($tier['benefits']) || $tier['benefits'] === []) {
            throw new DomainException('A TwinShield tier carries no benefits.');
        }
        if (!is_array($tier['compare']) || count($tier['compare']) !== count($program['compareAxes'])) {
            throw new DomainException('A TwinShield tier does not answer every comparison axis.');
        }
        if ($tier['extraLimitation'] !== null && !is_string($tier['extraLimitation'])) {
            throw new DomainException('A TwinShield tier limitation is invalid.');
        }
        if (strncmp($tier['name'], 'TwinShield ', 11) !== 0) {
            throw new DomainException('A TwinShield tier name is outside the program.');
        }
    }
    if ($featured !== 1) {
        throw new DomainException('The TwinShield program must feature exactly one tier.');
    }

    // Every published dollar amount traces to the approved program figures,
    // and no plain text may carry markup or an em-dash.
    $values = [];
    array_walk_recursive($program, static function ($value) use (&$values): void {
        if (is_string($value)) {
            $values[] = $value;
        }
    });
    $text = implode("\n", $values);
    if (strpos($text, '<') !== false || strpos($text, '>') !== false || preg_match('/\x{2013}|\x{2014}/u', $text) === 1) {
        throw new DomainException('The TwinShield program carries prohibited characters.');
    }
    if (preg_match('/\$\d+(?:\.\d+)?k\b/i', $text) === 1) {
        throw new DomainException('The TwinShield program abbreviates a dollar amount.');
    }
    if (preg_match_all('/\$(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/', $text, $amounts) === false) {
        throw new DomainException('A TwinShield amount could not be read.');
    }
    foreach ($amounts[1] as $amount) {
        if (!in_array(str_replace(',', '', $amount), TWINS_TWINSHIELD_RATES, true)) {
            throw new DomainException('A TwinShield amount is not an approved program figure.');
        }
    }
}

/**
 * The tier short name used in the card CTA ("Ask about Priority").
 *
 * @param array $tier Tier record.
 * @return string
 */
function twins_brand_twinshield_short_name(array $tier): string
{
    return substr($tier['name'], 11);
}

/**
 * Structured data for the three tiers: an OfferCatalog hung off the page's
 * Service node, with one Offer per tier and both payment options as unit
 * price specifications. Every value comes from the program record.
 *
 * @param array $program Validated program record.
 * @return array
 */
function twins_brand_twinshield_offer_catalog(array $program): array
{
    $money = static function (string $amount): float {
        return (float) str_replace([',', '$'], '', $amount);
    };
    $offers = [];
    foreach ($program['tiers'] as $tier) {
        $description = implode(' ', array_merge([$program['sharedBenefit']], $tier['benefits']));
        $offers[] = [
            '@type' => 'Offer',
            'name' => $tier['name'],
            'priceCurrency' => 'USD',
            'priceSpecification' => [
                [
                    '@type' => 'UnitPriceSpecification',
                    'name' => 'Monthly',
                    'price' => $money($tier['monthly']),
                    'priceCurrency' => 'USD',
                    'unitCode' => 'MON',
                    'billingIncrement' => 1,
                    'billingDuration' => 12,
                ],
                [
                    '@type' => 'UnitPriceSpecification',
                    'name' => 'Annual',
                    'price' => $money($tier['annual']),
                    'priceCurrency' => 'USD',
                    'unitCode' => 'ANN',
                    'billingIncrement' => 1,
                ],
            ],
            'itemOffered' => [
                '@type' => 'Service',
                'name' => $tier['name'],
                'serviceType' => $program['term'],
                'description' => $description,
            ],
        ];
    }
    return [
        '@type' => 'OfferCatalog',
        'name' => 'TwinShield Protection Plan',
        'itemListElement' => $offers,
    ];
}

/**
 * Render the program block.
 *
 * @param array  $program     Validated program record.
 * @param string $contactHref Route for the tier CTAs.
 * @return string
 */
function twins_brand_twinshield_section(array $program, string $contactHref): string
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $tiers = $program['tiers'];

    $markup = '<section class="twins-brand-twinshield" aria-labelledby="twins-brand-twinshield-title">';
    $markup .= '<div class="twins-brand-section-heading">';
    $markup .= '<span class="twins-brand-kicker">' . $escape($program['kicker']) . '</span>';
    $markup .= '<h2 id="twins-brand-twinshield-title">' . $escape($program['heading']) . '</h2>';
    $markup .= '<p>' . $escape($program['deck']) . '</p>';
    $markup .= '</div>';

    // The benefit every tier shares, said once. It used to open all three
    // benefit lists, which meant the first line of every card was identical
    // and the figure that actually separates the tiers (5%, 7.5%, 10%) sat one
    // line down in every column. Hoisted here it frames the row instead of
    // padding it.
    $markup .= '<p class="twins-l10-cue twins-brand-twinshield-shared">';
    $markup .= '<span class="twins-l10-cue__label">' . $escape($program['sharedLabel']) . '</span>';
    $markup .= $escape($program['sharedBenefit']);
    $markup .= '</p>';

    // The tier row, in the L10 deck's card language: the featured tier is the
    // inverted navy card with the gold numeral, its siblings are white cards
    // on a hairline. Order is the price ladder, so the row reads left to right
    // the way the money does. On a phone, where only one card is on screen at
    // a time, the featured tier is moved to the top of the stack in CSS: a
    // recommendation the reader has to scroll past two screens to find is not
    // a recommendation.
    $markup .= '<div class="twins-brand-twinshield-tiers">';
    foreach ($tiers as $tier) {
        $lead = $tier['featured'] === true;
        $markup .= '<article class="twins-brand-twinshield-card' . ($lead ? ' twins-brand-twinshield-card--lead' : '') . '">';
        $markup .= '<p class="twins-brand-twinshield-card__tag">' . $escape($tier['tagline']) . '</p>';
        $markup .= '<h3 class="twins-brand-twinshield-card__name">' . $escape($tier['name']) . '</h3>';
        $markup .= '<p class="twins-brand-twinshield-card__price">';
        $markup .= '<span class="twins-brand-twinshield-card__figure">' . $escape($tier['monthly']) . '</span>';
        $markup .= '<span class="twins-brand-twinshield-card__unit">a month</span>';
        $markup .= '</p>';
        // The terms line already says "for 12 months"; a term pill directly
        // under it repeated the same three words in a second shape.
        $markup .= '<p class="twins-brand-twinshield-card__terms">' . $escape($tier['terms']) . '</p>';
        $markup .= '<ul class="twins-brand-twinshield-card__benefits">';
        foreach ($tier['benefits'] as $benefit) {
            $markup .= '<li>' . $escape((string) $benefit) . '</li>';
        }
        $markup .= '</ul>';
        // The credit, where it is decided. The rate and the ceiling on their own
        // read as a promise of $150 or $500; the worked example is the number
        // the buyer actually earns over a full term, so it is stated here in
        // the tier's own words rather than left to a block further down.
        $markup .= '<div class="twins-brand-twinshield-card__credit">';
        $markup .= '<p class="twins-brand-twinshield-card__credit-label">Equipment credit</p>';
        $markup .= '<p class="twins-brand-twinshield-card__credit-line">' . $escape($tier['creditLine']) . '</p>';
        $markup .= '<p class="twins-brand-twinshield-card__credit-example">' . $escape($tier['creditExample']) . '</p>';
        $markup .= '</div>';
        $markup .= '<a class="twins-brand-cta twins-brand-cta--quote twins-brand-twinshield-card__cta" href="' . $escape($contactHref) . '">';
        $markup .= 'Ask about ' . $escape(twins_brand_twinshield_short_name($tier)) . '</a>';
        $markup .= '</article>';
    }
    $markup .= '</div>';

    // L5 cue strip: the term is a buying fact, not fine print.
    $markup .= '<p class="twins-l10-cue twins-brand-twinshield-term">';
    $markup .= '<span class="twins-l10-cue__label">The term</span>';
    $markup .= $escape($program['billingNote']);
    $markup .= '</p>';

    // The comparison table. Wide by nature, so it rides the house rail:
    // its own scroll container, contained inline overscroll, a visible
    // affordance, and a focusable labelled region for keyboard users.
    $markup .= '<div class="twins-brand-twinshield-compare">';
    $markup .= '<h3 id="twins-brand-twinshield-compare-title">' . $escape($program['compareHeading']) . '</h3>';
    $markup .= '<p class="twins-brand-twinshield-compare-hint">Scroll the table sideways to see every tier.</p>';
    $markup .= '<div class="twins-brand-twinshield-compare-scroll" role="region" aria-labelledby="twins-brand-twinshield-compare-title" tabindex="0">';
    $markup .= '<table class="twins-brand-twinshield-table">';
    $markup .= '<thead><tr><th scope="col">What you get</th>';
    foreach ($tiers as $tier) {
        $markup .= '<th scope="col"' . ($tier['featured'] === true ? ' class="twins-brand-twinshield-table__lead"' : '') . '>';
        $markup .= $escape(twins_brand_twinshield_short_name($tier));
        $markup .= '<span>' . $escape($tier['monthly']) . ' a month</span>';
        $markup .= '</th>';
    }
    $markup .= '</tr></thead><tbody>';
    foreach ($program['compareAxes'] as $axisIndex => $axis) {
        $markup .= '<tr><th scope="row">' . $escape((string) $axis) . '</th>';
        foreach ($tiers as $tier) {
            $markup .= '<td' . ($tier['featured'] === true ? ' class="twins-brand-twinshield-table__lead"' : '') . '>';
            $markup .= $escape((string) $tier['compare'][$axisIndex]);
            $markup .= '</td>';
        }
        $markup .= '</tr>';
    }
    $markup .= '</tbody></table>';
    $markup .= '</div></div>';

    // The credit rules. The rates, the ceilings and the worked examples are on
    // the cards and in the comparison table; repeating all three a third time
    // here as a callout row was the block's own filler. What only this block
    // can say is what governs the credit, so that is all it says.
    $markup .= '<div class="twins-brand-twinshield-credit">';
    $markup .= '<h3 id="twins-brand-twinshield-credit-title">' . $escape($program['creditHeading']) . '</h3>';
    $markup .= '<p class="twins-brand-twinshield-credit-deck">' . $escape($program['creditDeck']) . '</p>';
    $markup .= '<p class="twins-brand-twinshield-credit-rules">' . $escape($program['creditRules']) . '</p>';
    $markup .= '</div>';

    // The limitations, stated plainly and in the open.
    $markup .= '<div class="twins-brand-twinshield-limits">';
    $markup .= '<h3 id="twins-brand-twinshield-limits-title">' . $escape($program['limitsHeading']) . '</h3>';
    $markup .= '<ul>';
    foreach ($program['limitations'] as $limitation) {
        $markup .= '<li>' . $escape((string) $limitation) . '</li>';
    }
    foreach ($tiers as $tier) {
        if ($tier['extraLimitation'] === null) {
            continue;
        }
        $markup .= '<li><strong>' . $escape(twins_brand_twinshield_short_name($tier)) . ':</strong> ' . $escape($tier['extraLimitation']) . '</li>';
    }
    $markup .= '</ul>';
    $markup .= '</div>';

    $markup .= '</section>';
    return $markup;
}
