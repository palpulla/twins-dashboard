<?php
declare(strict_types=1);

/**
 * The TwinShield membership program, transcribed from
 * docs/marketing/website-rebuild/data/twinshield-plans.json (read out of
 * Housecall Pro > Service plans > Plan templates on 2026-08-25, because HCP's
 * public API does not expose service plans at all).
 *
 * THAT FILE IS THE SOURCE OF TRUTH. Every price, percentage, cap, visit count,
 * worked example and limitation sentence below is copied from it verbatim. No
 * figure here may be rounded, re-worded or "improved"; if a claim is not in
 * that file it does not belong on the page. tests/contracts/twinshield-
 * program.test.cjs pins this file against the JSON whenever the docs tree is
 * checked out, and against a verbatim copy of the approved figures when it is
 * not, so a stale or mistyped rate fails there instead of reaching a customer.
 *
 * Shape notes:
 *  - Keyed by the market-neutral page path, so one record serves /wi/, /ky/
 *    and /il/ the way config/page-content.php does. Only the paths listed here
 *    render the program; every other service page is untouched.
 *  - 'benefits' are the tier's own benefits from the JSON MINUS the enrollment
 *    discount every tier shares, which is hoisted to 'sharedBenefit' and
 *    rendered ONCE, in a cue strip above the row, rather than as the first
 *    bullet of all three cards. Three identical opening bullets buried the
 *    thing that actually separates the tiers (5%, 7.5%, 10%) one line down; now
 *    each card's first bullet is its own discount. The union of sharedBenefit
 *    and each tier's benefits is that tier's JSON benefit list, in order, byte
 *    for byte, and the schema offers still publish the union.
 *  - 'compare' holds the same facts as short cells for the comparison table.
 *    'Not included' is the one string in this file that is not lifted from the
 *    JSON: it marks a tier that does not carry a benefit its siblings do, which
 *    is exactly what an exhaustive plan template means by omission.
 *  - 'limitations' is the JSON's single limitations string split at its own
 *    sentence boundaries so it can be read as a list. Joined with one space it
 *    is that string, character for character; the contract test asserts it.
 */
return [
    '/maintenance-plans/' => [
        'kicker' => 'TwinShield membership',
        'heading' => 'Three tiers, one 12-month term',
        'deck' => 'The choice comes down to two things: how often you want the door looked at, and how much you expect to spend on repairs this year.',
        'term' => '12-month membership',
        'sharedLabel' => 'Every tier',
        'billingNote' => 'Monthly billing is a payment option for the full 12-month term, not a cancel-anytime membership.',
        'sharedBenefit' => '10% off the qualifying repair on the job where this new membership is purchased.',
        'creditHeading' => 'The equipment credit',
        'creditRules' => 'Credit applies toward qualifying new garage-door or opener equipment. Failed, reversed, refunded, or charged-back payments do not earn credit. Credit already used is deducted. The Twins Garage Doors office must verify the exact balance before it is promised or applied.',
        'limitsHeading' => 'What TwinShield is, and what it is not',
        'limitations' => [
            'Plan applies to one residential service address and one garage-door system.',
            'Unused visits do not roll over.',
            'Discounts do not combine unless Twins approves otherwise in writing.',
            'TwinShield is a maintenance-and-savings membership, not insurance or a repair/replacement warranty.',
            'All benefits and limitations are governed by the TwinShield Membership Agreement.',
        ],
        'compareHeading' => 'What changes between tiers',
        'compareAxes' => [
            'Tune-up and safety inspection visits',
            'Discount on future qualifying repairs',
            'Service-call fees',
            'Scheduling',
            'Equipment credit',
        ],
        'tiers' => [
            [
                'key' => 'core',
                'name' => 'TwinShield Core',
                'tagline' => 'Essential Care',
                'featured' => false,
                'monthly' => '$12.99',
                'monthlyTotal' => '$155.88',
                'annual' => '$149',
                'terms' => '$12.99 a month for 12 months, $155.88 total, or $149 paid once.',
                'benefits' => [
                    '5% off future qualifying repairs while active and current.',
                    '1 garage-door tune-up and safety inspection during the 12-month term.',
                    '50% off future service-call fees.',
                ],
                'creditLine' => '25% of what you pay, up to $150.',
                'creditExample' => '12 successful monthly payments earn $38.97. One $149 annual payment earns $37.25.',
                'compare' => [
                    '1 during the term',
                    '5%',
                    '50% off future fees',
                    'Not included',
                    '25% of what you pay, up to $150',
                ],
                'extraLimitation' => null,
            ],
            [
                'key' => 'priority',
                'name' => 'TwinShield Priority',
                'tagline' => 'Best Value',
                'featured' => true,
                'monthly' => '$18.99',
                'monthlyTotal' => '$227.88',
                'annual' => '$199',
                'terms' => '$18.99 a month for 12 months, $227.88 total, or $199 paid once.',
                'benefits' => [
                    '7.5% off future qualifying repairs while active and current.',
                    '1 garage-door tune-up and safety inspection during the 12-month term.',
                    'Priority scheduling, subject to appointment and technician availability.',
                    '1 service-call fee waived during the 12-month term.',
                    '50% off additional service-call fees during that term.',
                ],
                'creditLine' => '35% of what you pay, up to $300.',
                'creditExample' => '12 successful monthly payments earn $79.76. One $199 annual payment earns $69.65.',
                'compare' => [
                    '1 during the term',
                    '7.5%',
                    '1 waived, then 50% off',
                    'Priority scheduling',
                    '35% of what you pay, up to $300',
                ],
                'extraLimitation' => 'Priority scheduling does not guarantee same-day service or a specific appointment.',
            ],
            [
                'key' => 'premier',
                'name' => 'TwinShield Premier',
                'tagline' => 'Maximum Care',
                'featured' => false,
                'monthly' => '$24.99',
                'monthlyTotal' => '$299.88',
                'annual' => '$279',
                'terms' => '$24.99 a month for 12 months, $299.88 total, or $279 paid once.',
                'benefits' => [
                    '10% off future qualifying repairs while active and current.',
                    '2 garage-door tune-ups and safety inspections during the 12-month term.',
                    'Highest scheduling priority, subject to appointment and technician availability.',
                    '1 service-call fee waived during the 12-month term.',
                    '50% off additional service-call fees during that term.',
                    'One standard compatible garage-door-opener surge-protection device benefit, subject to the Membership Agreement.',
                ],
                'creditLine' => '50% of what you pay, up to $500.',
                'creditExample' => '12 successful monthly payments earn $149.94. One $279 annual payment earns $139.50.',
                'compare' => [
                    '2 during the term',
                    '10%',
                    '1 waived, then 50% off',
                    'Highest priority',
                    '50% of what you pay, up to $500',
                ],
                'extraLimitation' => 'Highest-priority scheduling does not guarantee same-day service or a specific appointment. Surge protection reduces risk but does not guarantee that electrical damage will not occur.',
            ],
        ],
    ],
];
