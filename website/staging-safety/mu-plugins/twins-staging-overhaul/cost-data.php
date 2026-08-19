<?php
/**
 * Fixed, approved cost-page data for the private staging preview.
 *
 * Rewritten 2026-08-19 from docs/marketing/website-rebuild/copy/guides/
 * madison-cost-guide.md. Every dollar figure and sample size traces to
 * docs/marketing/website-rebuild/data/price-ranges.json (completed HCP jobs,
 * 24-month window ending 2026-08-18, middle 60% of job totals, p20/p80
 * rounded to the nearest $25) or to one of the two approved offers
 * ($0 service call with repair, $49 tune-up).
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Return one of the two approved cost-page datasets.
 *
 * @param string $market Exact fixed market key.
 * @return array
 */
function twins_overhaul_cost_data(string $market): array {
    $shortDisclaimer = 'Planning ranges from our own completed jobs, not quotes. Every project gets its own flat number, in person, before any work starts.';
    $fullDisclaimer = 'Prices shown are ranges from completed Twins Garage Doors jobs and are provided for planning only. They are not estimates, offers, or guaranteed prices. Every project is inspected and priced individually based on the door, required parts, labor, site conditions, product selections, and scope of work. Your written Twins Garage Doors quote is the only price that applies to your project.';
    $sourceLine = 'Pricing data generated August 18, 2026 · Completed Twins Garage Doors jobs from August 2024 through August 2026.';

    // Three honest footnotes from the approved cost-guide copy. The spring
    // range is whole-ticket, the opener-repair sample skews small, and the
    // single-door sample (n=15) earns "typically", never a promise.
    $footnotes = array(
        'The spring range is the whole ticket. Springs are usually replaced together with the parts that wear alongside them, cables, bearings, rollers, so most spring jobs land between $575 and $1,225 including those parts. That is what a real customer pays, which is more useful than a bare-spring part price you will never be invoiced for alone.',
        'The opener repair range covers small fixes only. Small opener repairs like sensors and keypads usually run $100 to $300. A failing motor head usually means replacement, which runs $775 to $1,400 installed. Our repair sample skews toward small part swaps, so do not read $300 as the ceiling for major opener surgery.',
        'The single-door sample is thin. Fifteen jobs is enough to say "typically," not enough to promise. Doubles, with 47 jobs behind them, are the firmer range. Door-plus-opener bundle jobs are excluded from the door ranges so opener cost does not inflate them.',
    );

    $priceRows = array(
        array(
            'service' => 'Service call and diagnostic',
            'range' => '$49',
            'label' => 'Current service-call fee',
            'coverage' => 'On-site inspection and an exact flat quote. $0 when we do the repair.',
        ),
        array(
            'service' => 'Spring replacement (whole ticket)',
            'range' => '$575 to $1,225',
            'label' => 'Based on 432 completed jobs',
            'coverage' => 'The most common repair. Includes the parts that wear with the springs: cables, bearings, rollers.',
        ),
        array(
            'service' => 'Cable repair',
            'range' => '$325 to $625',
            'label' => 'Based on 50 completed jobs',
            'coverage' => 'One cable or both, plus the condition of the drums and bottom brackets they anchor to.',
        ),
        array(
            'service' => 'Minor opener repair',
            'range' => '$100 to $300',
            'label' => 'Based on 23 completed jobs',
            'coverage' => 'Sensors, keypads, remotes, and other small fixes. A failing motor head usually means replacement instead.',
        ),
        array(
            'service' => 'New opener, installed (LiftMaster)',
            'range' => '$775 to $1,400',
            'label' => 'Based on 93 completed jobs',
            'coverage' => 'Opener equipment and professional installation. Drive type, battery backup, and smart features set where a unit lands.',
        ),
        array(
            'service' => 'New single door, installed (8 to 10 ft)',
            'range' => 'typically $2,625 to $3,525',
            'label' => 'Based on 15 completed jobs',
            'coverage' => 'Door, required hardware, and installation. Thin sample, so "typically" is the honest word.',
        ),
        array(
            'service' => 'New double door, installed (14 to 20 ft)',
            'range' => '$3,425 to $4,400',
            'label' => 'Based on 47 completed jobs',
            'coverage' => 'Door, required hardware, and installation. The firmer of the two door ranges.',
        ),
        array(
            'service' => 'Tune-up / maintenance visit',
            'range' => '$49',
            'label' => 'Current tune-up offer',
            'coverage' => 'Full inspection and lubrication. Most completed maintenance visits ran $50 to $100 across 52 jobs.',
        ),
    );

    $common = array(
        'lead' => 'Real price ranges from our own completed jobs, with the sample size shown for every number. No national averages, and a flat quote you approve before any work starts.',
        'heroNotes' => array('Real completed jobs', 'Sample size on every line', 'Flat price approved before work'),
        'promise' => array('Exact price before work starts', 'Based on completed local jobs'),
        'shortDisclaimer' => $shortDisclaimer,
        'fullDisclaimer' => $fullDisclaimer,
        'footnotes' => $footnotes,
        'sourceLine' => $sourceLine,
        'priceRows' => $priceRows,
        'answerEyebrow' => 'The short answer',
        'answerHeading' => 'What should you expect to pay?',
        'methodEyebrow' => 'Why we publish our prices',
        'methodLabel' => 'How we calculated these ranges',
        'pricingEyebrow' => 'The price table',
        'pricingLede' => 'All figures are what customers actually paid on completed jobs over the last two years, including tax, after discounts. "Most jobs" means the middle of the range; unusually small or large jobs fall outside it.',
        'factorsEyebrow' => 'What changes the number',
        'factorsHeading' => 'What pushes a price up or down the range',
        'factorsLede' => 'The final price comes from the door, the home, and the work required. Your tech prices all of it into one flat quote after measuring, so the surprises happen before you approve, not after.',
        'factors' => array(
            array('title' => 'Size, first and always', 'copy' => 'A double door is more steel, more spring, more track, and more labor than a single. Nothing moves the number as far as the width of your opening.'),
            array('title' => 'Insulation', 'copy' => 'Clopay insulated doors sandwich Intellicore insulation between steel skins. In Wisconsin this is the upgrade that earns its keep, and it costs more than a single-skin door.'),
            array('title' => 'Style and windows', 'copy' => 'A Classic steel raised-panel door anchors the bottom of the range. Gallery, Coachman, and Modern Steel designs climb from there, and window sections add cost per row.'),
            array('title' => 'What the install uncovers', 'copy' => 'New doors get new tracks and springs sized to them, that is included. Rotted framing, an out-of-square opening, or a low-headroom conversion adds work.'),
            array('title' => 'Catching it early', 'copy' => 'A $49 tune-up finds the fraying cable before it snaps. It is the cheapest line on this page and it protects every other line.'),
        ),
        'decisionEyebrow' => 'Repair or replace',
        'decisionHeading' => 'Repair or replace? The honest math.',
        'decisionLede' => 'The repair is the right answer more often than the industry wants you to believe, and our techs quote it first. When replacement makes more sense, we say so and show you both numbers side by side.',
        'decisionCards' => array(
            array(
                'tag' => 'Repair when',
                'title' => 'The door itself is sound',
                'items' => array(
                    'A broken spring on a solid door is a $575 to $1,225 fix on most tickets, not a reason to buy a new door',
                    'Cables, rollers, seals, and small opener parts are routine fixes',
                    'A steel door in good shape routinely outlives two or three sets of springs',
                    'You approve the repair quote first, every time',
                ),
            ),
            array(
                'tag' => 'Replace when',
                'title' => 'The door is the problem',
                'items' => array(
                    'Rust is eating the bottom sections',
                    'Cracks run across multiple panels',
                    'The frame is out of square, or the repair history reads like a subscription',
                    'The fix costs a meaningful share of a new door and the next failure is already visible',
                ),
            ),
        ),
        'climateEyebrow' => 'Wisconsin buying guidance',
        'climateCards' => array(
            array('title' => 'Insulation', 'copy' => 'An insulated door makes a real difference in the rooms above and beside an attached garage.'),
            array('title' => 'Weather seals', 'copy' => 'Correctly fitted bottom, side, and top seals manage drafts, moisture, and debris.'),
            array('title' => 'Salt and slush', 'copy' => 'Cables, drums, and bottom brackets rust in the salt-and-slush zone at the bottom of every Wisconsin garage.'),
            array('title' => 'Springs and cold', 'copy' => 'Springs age through a rated life of about 10,000 cycles, and cold mornings are when tired ones let go.'),
        ),
        'financeEyebrow' => 'Financing',
        'financeHeading' => 'Monthly payments on new doors',
        'financeCopy' => 'A new door is a real purchase, and you should not have to wait for the perfect month to make it. We offer financing through GoodLeap, with monthly payments available on new doors. Terms come from GoodLeap at application time, so we will not promise rates here, but the process is quick and your tech can walk you through it when he quotes the job.',
        'financeDisclosure' => 'Approval and terms are provided by GoodLeap, the financing partner.',
        'processEyebrow' => 'No mystery pricing',
        'processHeading' => 'How to get your actual number',
        'processLede' => 'Ranges plan the budget; a flat quote settles it. Here is the path from this page to a real number.',
        'process' => array(
            array('title' => 'Book online or call', 'copy' => 'Tell us repair or new door. Same-day slots exist for repairs.'),
            array('title' => 'A real local tech measures and diagnoses', 'copy' => 'For repairs, the service call is $0 when we do the work. For new doors, we measure the opening and walk the Clopay styles, insulation, and windows at whatever depth you want.'),
            array('title' => 'One flat number, before anything starts', 'copy' => 'No hourly meter, no surprise line items, no additions you did not approve.'),
            array('title' => 'The guarantee backs it', 'copy' => '"Done Right, or We Make It Right." If something about our work is not right, we come back and fix it. No arguing, no fine print.'),
        ),
        'faqEyebrow' => 'Straight answers',
        'faqHeading' => 'Cost questions, answered',
        'coverageEyebrow' => 'Check your location',
    );

    $markets = array(
        'madison' => array_replace($common, array(
            'key' => 'madison',
            'city' => 'Madison',
            'eyebrow' => 'Madison garage door pricing · 2026',
            'titleBefore' => 'What a garage door costs in ',
            'titleEmphasis' => 'Madison',
            'titleAfter' => '. Real numbers.',
            'phone' => '(608) 420-2377',
            'tel' => '+16084202377',
            'street' => '2921 Landmark Pl, Ste 206',
            'locality' => 'Madison',
            'region' => 'WI',
            'postalCode' => '53713',
            'addressLine' => 'Madison, WI 53713',
            'areaServed' => array('Madison WI'),
            'url' => 'https://danielj140.sg-host.com/wi/garage-door-cost-in-madison-wi/',
            'sticker' => 'Real ranges from completed Madison-area jobs',
            'localPromise' => 'Madison service team',
            'directAnswer' => 'A new installed single garage door in Madison typically runs $2,625 to $3,525, and a double runs $3,425 to $4,400, based on 62 completed installations in the Madison area over the last two years. The most common repair, a broken spring, lands between $575 and $1,225 on most jobs including the parts that wear with the springs. A new opener runs $775 to $1,400 installed. These are our own completed-job numbers, not national averages. Every job gets a flat quote you approve before work starts.',
            'methodHeading' => 'Why we publish our prices when nobody else will',
            'methodIntro' => 'Search "garage door cost Madison" and you will find thousands of words that never print a dollar sign. We think that is backwards. So here are ours: every number on this page comes from our own completed jobs in the Madison area over the last two years, and we tell you how many jobs sit behind each line. When a sample is small, we say so.',
            'methodology' => 'We show the range where most completed jobs landed, the middle 60 percent of job totals, so one unusual mansion door does not skew your expectations. Figures include tax, reflect discounts, and exclude tips. Door-plus-opener bundle jobs are excluded from the door ranges so opener cost does not inflate them.',
            'pricingHeading' => 'The Madison price table',
            'pricingLede' => 'All figures are what customers actually paid on completed jobs in the Madison area over the last two years, including tax, after discounts. "Most jobs" means the middle of the range; unusually small or large jobs fall outside it.',
            'samples' => array(
                array('count' => '432 jobs', 'label' => 'Spring replacements'),
                array('count' => '93 jobs', 'label' => 'New opener installations'),
                array('count' => '62 jobs', 'label' => 'New garage doors'),
                array('count' => '50 jobs', 'label' => 'Cable repairs'),
            ),
            'climateHeading' => 'What Wisconsin weather does to a Madison garage door',
            'climateLede' => 'A Madison garage door handles seasonal temperature swings, moisture, road salt, and daily use. These factors are worth discussing when you compare products.',
            'faqAsideCount' => '1,600+',
            'faqAsideHeading' => 'completed jobs in the last two years',
            'faqAsideCopy' => 'Original local data with the sample size shown for every line. When a sample is thin, we say so.',
            'faqs' => array(
                array(
                    'question' => 'How much does a garage door cost in Madison?',
                    'answer' => 'A new installed single door typically runs $2,625 to $3,525 and a double runs $3,425 to $4,400, based on 62 completed Madison-area installations over the last two years. Style, insulation, and windows move the number inside those ranges, and you approve one flat installed quote after we measure.',
                ),
                array(
                    'question' => 'How much is the most common garage door repair?',
                    'answer' => 'That is a broken spring, and most spring jobs land between $575 and $1,225 including the parts that wear with the springs, from 432 completed local jobs. Cable repairs run $325 to $625. The service call is $0 when we do the repair.',
                ),
                array(
                    'question' => 'Why are your prices different from national cost websites?',
                    'answer' => 'National sites average markets that are not Madison and jobs that never happened. Our ranges are what local customers actually paid on our own completed jobs over the last two years, including tax, with the sample size shown for every line. When our sample is thin, like single doors at 15 jobs, we say so.',
                ),
                array(
                    'question' => 'Does a new opener come with the new door price?',
                    'answer' => 'No, and that is deliberate: bundling would blur both numbers. The door ranges, typically $2,625 to $3,525 single and $3,425 to $4,400 double, cover the door installed. A new LiftMaster opener is quoted separately at $775 to $1,400 installed, and doing both in one visit is common.',
                ),
                array(
                    'question' => 'Can I finance a garage door in Madison?',
                    'answer' => 'Yes, through GoodLeap, with monthly payments available on new doors. Rates and approval come from GoodLeap when you apply, so we do not promise terms here. Ask when you book at (608) 420-2377 or when your tech quotes the job, and he will walk you through the application.',
                ),
            ),
        )),
        'milwaukee' => array_replace($common, array(
            'key' => 'milwaukee',
            'city' => 'Milwaukee',
            'eyebrow' => 'Milwaukee garage door pricing · 2026',
            'titleBefore' => 'What a garage door costs in ',
            'titleEmphasis' => 'Milwaukee',
            'titleAfter' => '. Real numbers.',
            'phone' => '(414) 800-9271',
            'tel' => '+14148009271',
            'street' => '11220 W Burleigh St Ste 100',
            'locality' => 'Wauwatosa',
            'region' => 'WI',
            'postalCode' => '53222',
            'addressLine' => 'Wauwatosa, WI 53222',
            'areaServed' => array('Milwaukee WI', 'Wauwatosa WI'),
            'url' => 'https://danielj140.sg-host.com/wi/garage-door-cost-in-milwaukee-wi/',
            'sticker' => 'Real ranges from completed Twins jobs',
            'localPromise' => 'Milwaukee service team',
            'promise' => array('Exact price before work starts', 'Real completed-job ranges'),
            'directAnswer' => 'A new installed single garage door typically runs $2,625 to $3,525, and a double runs $3,425 to $4,400, based on 62 completed Twins Garage Doors installations over the last two years. The most common repair, a broken spring, lands between $575 and $1,225 on most jobs including the parts that wear with the springs. A new opener runs $775 to $1,400 installed. These ranges come from our own completed jobs across our Wisconsin service area, not national averages, and every Milwaukee project gets a flat quote you approve before work starts.',
            'methodHeading' => 'Why we publish our prices when nobody else will',
            'methodIntro' => 'Search "garage door cost Milwaukee" and you will find thousands of words that never print a dollar sign. We think that is backwards. So here are ours: every number on this page comes from our own completed Twins Garage Doors jobs over the last two years, and we tell you how many jobs sit behind each line. When a sample is small, we say so.',
            'methodology' => 'The ranges come from completed Twins Garage Doors jobs across our Wisconsin service area, most of them in the Madison area where we have worked longest. We show the range where most completed jobs landed, the middle 60 percent of job totals. Figures include tax, reflect discounts, and exclude tips. Door-plus-opener bundle jobs are excluded from the door ranges so opener cost does not inflate them.',
            'pricingHeading' => 'The price table for Milwaukee planning',
            'pricingLede' => 'All figures are what customers actually paid on completed Twins Garage Doors jobs across our Wisconsin service area over the last two years, including tax, after discounts. "Most jobs" means the middle of the range; unusually small or large jobs fall outside it. Every Milwaukee project is priced individually.',
            'samples' => array(
                array('count' => '432 jobs', 'label' => 'Spring replacements'),
                array('count' => '93 jobs', 'label' => 'New opener installations'),
                array('count' => '62 jobs', 'label' => 'New garage doors'),
                array('count' => '50 jobs', 'label' => 'Cable repairs'),
            ),
            'climateHeading' => 'What Wisconsin weather does to a Milwaukee garage door',
            'climateLede' => 'A Milwaukee-area garage door handles seasonal temperature swings, lake-effect moisture, road salt, and daily use. These factors are worth discussing when you compare products.',
            'faqAsideCount' => '',
            'faqAsideHeading' => 'Real job data, honestly labeled',
            'faqAsideCopy' => 'The ranges come from completed Twins Garage Doors jobs across our Wisconsin service area, and every Milwaukee-area project receives its own inspected, written price.',
            'faqs' => array(
                array(
                    'question' => 'How much does a garage door cost in Milwaukee?',
                    'answer' => 'A new installed single door typically runs $2,625 to $3,525 and a double runs $3,425 to $4,400, based on 62 completed Twins Garage Doors installations over the last two years across our Wisconsin service area. Style, insulation, and windows move the number inside those ranges, and you approve one flat installed quote after we measure.',
                ),
                array(
                    'question' => 'How much is the most common garage door repair?',
                    'answer' => 'That is a broken spring, and most spring jobs land between $575 and $1,225 including the parts that wear with the springs, from 432 completed Twins Garage Doors jobs. Cable repairs run $325 to $625. The service call is $0 when we do the repair.',
                ),
                array(
                    'question' => 'Why are your prices different from national cost websites?',
                    'answer' => 'National sites average markets that are not Wisconsin and jobs that never happened. Our ranges are what customers actually paid on our own completed jobs over the last two years, including tax, with the sample size shown for every line. When our sample is thin, like single doors at 15 jobs, we say so.',
                ),
                array(
                    'question' => 'Does a new opener come with the new door price?',
                    'answer' => 'No, and that is deliberate: bundling would blur both numbers. The door ranges, typically $2,625 to $3,525 single and $3,425 to $4,400 double, cover the door installed. A new LiftMaster opener is quoted separately at $775 to $1,400 installed, and doing both in one visit is common.',
                ),
                array(
                    'question' => 'Can I finance a garage door in Milwaukee?',
                    'answer' => 'Yes, through GoodLeap, with monthly payments available on new doors. Rates and approval come from GoodLeap when you apply, so we do not promise terms here. Ask when you book at (414) 800-9271 or when your tech quotes the job, and he will walk you through the application.',
                ),
            ),
        )),
    );

    if (!isset($markets[$market])) {
        twins_overhaul_refuse_route('cost market is outside the fixed Madison/Milwaukee map.');
    }

    return $markets[$market];
}
