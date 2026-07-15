<?php
/**
 * Fixed, approved cost-page data for the private staging preview.
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
    $shortDisclaimer = 'Historical planning ranges only. Every project is evaluated and priced individually.';
    $fullDisclaimer = 'Prices shown are historical ranges from completed Twins Garage Doors jobs and are provided for general planning only. They are not estimates, offers, or guaranteed prices. Every project is inspected and priced individually based on the door, required parts, labor, site conditions, product selections, and scope of work. Your written Twins Garage Doors quote is the only price that applies to your project.';
    $springClarification = 'Among completed repair jobs that included spring replacement, the middle 50% of total invoices ranged from $780 to $1,660. Those totals may include labor, cables, bearings, rollers, or other required work. This is not a spring-only parts price.';
    $sourceLine = 'Pricing data reviewed July 10, 2026 · Based on completed Twins Garage Doors jobs from July 2025 through July 2026.';

    $priceRows = array(
        array(
            'service' => 'Service call and diagnostic',
            'range' => '$49',
            'label' => 'Current service-call fee',
            'coverage' => 'On-site inspection and exact repair quote',
        ),
        array(
            'service' => 'Garage door repair',
            'range' => '$400 to $1,050',
            'label' => 'Historical planning range',
            'coverage' => 'Completed repair jobs across common door problems',
        ),
        array(
            'service' => 'New opener installed',
            'range' => '$900 to $1,450',
            'label' => 'Historical planning range',
            'coverage' => 'Opener equipment and professional installation',
        ),
        array(
            'service' => 'New garage door installed',
            'range' => '$3,000 to $4,100',
            'label' => 'Historical planning range',
            'coverage' => 'Door, required hardware, and installation',
        ),
        array(
            'service' => 'New door and opener',
            'range' => '$4,400 to $7,250',
            'label' => 'Historical planning range',
            'coverage' => 'Combined door and opener installation',
        ),
    );

    $common = array(
        'lead' => 'See real price ranges from completed Twins Garage Doors jobs, then get an exact quote for your home.',
        'heroNotes' => array('Real completed jobs', 'Clear methodology', 'Price approved before work'),
        'promise' => array('Exact price before work starts', 'Based on completed local jobs'),
        'shortDisclaimer' => $shortDisclaimer,
        'fullDisclaimer' => $fullDisclaimer,
        'springClarification' => $springClarification,
        'sourceLine' => $sourceLine,
        'priceRows' => $priceRows,
        'answerEyebrow' => 'The short answer',
        'answerHeading' => 'What should you expect to pay?',
        'methodEyebrow' => 'Transparent methodology',
        'methodLabel' => 'How we calculated these ranges',
        'pricingEyebrow' => 'Typical local ranges',
        'pricingLede' => 'These are planning ranges, not instant quotes. Door condition, parts, size, and product selection determine the exact price.',
        'factorsEyebrow' => 'What changes the number',
        'factorsHeading' => 'Why two garage door quotes can look different',
        'factorsLede' => 'The final price comes from the door, the home, and the work required. We show the options and price them before installation begins.',
        'factors' => array(
            array('title' => 'Door material', 'copy' => 'Insulated steel, wood-look composite, and full-view glass have different product costs.'),
            array('title' => 'Door size', 'copy' => 'Single and double doors use different amounts of material, track, and hardware.'),
            array('title' => 'Insulation', 'copy' => 'Construction, insulation level, and thermal breaks affect product selection.'),
            array('title' => 'Hardware', 'copy' => 'Spring setup, tracks, cables, rollers, and bearings can change the scope.'),
            array('title' => 'Opener type', 'copy' => 'Drive system, lifting capacity, controls, and smart features affect opener pricing.'),
        ),
        'decisionEyebrow' => 'A practical decision',
        'decisionHeading' => 'Should you repair or replace your garage door?',
        'decisionLede' => 'Repair often makes sense when the problem is limited. Replacement is worth comparing when age, damage, or repeated failures affect the whole system.',
        'decisionCards' => array(
            array(
                'tag' => 'Repair may fit when',
                'title' => 'The door itself is still sound',
                'items' => array(
                    'The problem is limited to one component',
                    'The panels still fit and seal correctly',
                    'Compatible replacement parts are available',
                    'The door meets your current comfort and style needs',
                ),
            ),
            array(
                'tag' => 'Compare replacement when',
                'title' => 'Problems affect the whole system',
                'items' => array(
                    'Several panels or major components are damaged',
                    'Rust, rot, gaps, or poor fit are widespread',
                    'Repairs have become frequent',
                    'You want a different insulation level or appearance',
                ),
            ),
        ),
        'climateEyebrow' => 'Local buying guidance',
        'climateCards' => array(
            array('title' => 'Insulation', 'copy' => 'Compare door construction when the garage shares walls or living space with the home.'),
            array('title' => 'Weather seals', 'copy' => 'Correctly fitted bottom, side, and top seals help manage drafts, moisture, and debris.'),
            array('title' => 'Corrosion exposure', 'copy' => 'Ask how hardware and finishes should be maintained around winter moisture and road salt.'),
            array('title' => 'Balanced operation', 'copy' => 'Springs, tracks, and rollers should work together consistently through the seasons.'),
        ),
        'financeEyebrow' => 'Project financing',
        'financeHeading' => 'Plan the project around your home and budget',
        'financeCopy' => 'Twins Garage Doors offers financing through GoodLeap for qualifying new-door projects. Available options are explained during the quote process.',
        'financeDisclosure' => 'Approval and terms are provided by the financing partner.',
        'processEyebrow' => 'No mystery pricing',
        'processHeading' => 'Your exact price in three steps',
        'processLede' => 'A simple path from the first call to an approved repair or installation.',
        'process' => array(
            array('title' => 'Tell us what is happening', 'copy' => 'Call or book online and share the problem, door type, and timing.'),
            array('title' => 'Get an on-site diagnosis', 'copy' => 'A technician inspects the system and identifies the required work.'),
            array('title' => 'Approve the exact price', 'copy' => 'You see the price and available options before repair or installation begins.'),
        ),
        'faqEyebrow' => 'Straight answers',
        'faqHeading' => 'Frequently asked garage door cost questions',
        'coverageEyebrow' => 'Check your location',
    );

    $markets = array(
        'madison' => array_replace($common, array(
            'key' => 'madison',
            'city' => 'Madison',
            'eyebrow' => 'Madison garage door pricing · 2026',
            'titleBefore' => 'What does a garage door ',
            'titleEmphasis' => 'really cost',
            'titleAfter' => ' in Madison?',
            'phone' => '(608) 420-2377',
            'tel' => '+16084202377',
            'street' => '2921 Landmark Pl, Ste 206',
            'locality' => 'Madison',
            'region' => 'WI',
            'postalCode' => '53713',
            'addressLine' => 'Madison, WI 53713',
            'areaServed' => array('Madison WI'),
            'url' => 'https://danielj140.sg-host.com/wi/garage-door-cost-in-madison-wi/',
            'sticker' => 'Pricing built from 516 completed jobs',
            'localPromise' => 'Madison service team',
            'directAnswer' => 'In Madison, most garage door repairs cost $400 to $1,050. A new garage door with professional installation typically costs $3,000 to $4,100, while a new opener installed costs $900 to $1,450. These ranges come from Twins Garage Doors jobs completed during the past 12 months. Exact pricing depends on door size, material, insulation, hardware, and installation requirements.',
            'methodHeading' => 'How we calculated these Madison garage door prices',
            'methodIntro' => "We use completed local jobs to publish useful planning ranges while pricing every customer's project individually.",
            'methodology' => 'We analyzed 516 completed Twins Garage Doors jobs from July 2025 through July 2026. The ranges show the middle 50% of completed-job totals, which reduces the effect of unusually small and unusually complex projects.',
            'pricingHeading' => 'Garage door repair and installation prices in Madison',
            'samples' => array(
                array('count' => '378 jobs', 'label' => 'Garage door repairs'),
                array('count' => '55 jobs', 'label' => 'New opener installations'),
                array('count' => '48 jobs', 'label' => 'New garage doors'),
                array('count' => '35 jobs', 'label' => 'Door and opener packages'),
            ),
            'climateHeading' => 'How Madison weather affects garage door selection',
            'climateLede' => 'A Madison garage door needs to handle seasonal temperature changes, moisture, and daily use. These factors are worth discussing when you compare products.',
            'faqAsideCount' => '516',
            'faqAsideHeading' => 'completed jobs behind these ranges',
            'faqAsideCopy' => 'Original local data, clearly explained and reviewed for freshness.',
            'faqs' => array(
                array(
                    'question' => 'How much does a garage door cost in Madison?',
                    'answer' => 'Most garage door repairs in Madison cost $400 to $1,050. A new garage door installed typically costs $3,000 to $4,100, while a new opener installed costs $900 to $1,450, based on completed Twins Garage Doors jobs during the past 12 months.',
                ),
                array(
                    'question' => 'How much does garage door spring replacement cost?',
                    'answer' => 'Among completed repair jobs that included spring replacement, the middle 50% of total invoices ranged from $780 to $1,660. Those totals may include labor and related parts. This is not a spring-only parts price.',
                ),
                array(
                    'question' => 'Is there a fee for a garage door service call?',
                    'answer' => 'The service call and diagnostic fee is $49. A technician inspects the door and provides an exact price before repair work begins.',
                ),
                array(
                    'question' => 'Can I finance a new garage door?',
                    'answer' => 'Twins Garage Doors offers financing through GoodLeap for qualifying new garage door projects. Available terms are explained during the quote process.',
                ),
                array(
                    'question' => 'Are these garage door prices guaranteed?',
                    'answer' => 'No. The displayed figures are historical planning ranges, not estimates, offers, or guaranteed prices. Every project is inspected and priced individually. Your written Twins Garage Doors quote is the only price that applies to your project.',
                ),
            ),
        )),
        'milwaukee' => array_replace($common, array(
            'key' => 'milwaukee',
            'city' => 'Milwaukee',
            'eyebrow' => 'Milwaukee garage door pricing · 2026',
            'titleBefore' => 'What does a garage door ',
            'titleEmphasis' => 'really cost',
            'titleAfter' => ' in Milwaukee?',
            'phone' => '(414) 800-9271',
            'tel' => '+14148009271',
            'street' => '11220 W Burleigh St Ste 100',
            'locality' => 'Wauwatosa',
            'region' => 'WI',
            'postalCode' => '53222',
            'addressLine' => 'Wauwatosa, WI 53222',
            'areaServed' => array('Milwaukee WI', 'Wauwatosa WI'),
            'url' => 'https://danielj140.sg-host.com/wi/garage-door-cost-in-milwaukee-wi/',
            'sticker' => 'Historical job data behind these ranges',
            'localPromise' => 'Milwaukee service team',
            'promise' => array('Exact price before work starts', 'Historical planning ranges'),
            'directAnswer' => 'Historical planning ranges from completed Twins Garage Doors jobs are $400 to $1,050 for garage door repair, $3,000 to $4,100 for a new garage door installed, and $900 to $1,450 for a new opener installed. Every Milwaukee project is inspected and priced individually.',
            'methodHeading' => 'How we calculated these garage door price ranges',
            'methodIntro' => 'We use completed Twins Garage Doors jobs to publish useful planning ranges while pricing every project individually.',
            'methodology' => 'The ranges show the middle 50% of completed Twins Garage Doors job totals, which reduces the effect of unusually small and unusually complex projects.',
            'pricingEyebrow' => 'Historical planning ranges',
            'pricingHeading' => 'Garage door repair and installation price ranges for Milwaukee',
            'samples' => array(),
            'climateHeading' => 'How Milwaukee weather affects garage door selection',
            'climateLede' => 'A Milwaukee-area garage door needs to handle seasonal temperature changes, moisture, and daily use. These factors are worth discussing when you compare products.',
            'faqAsideCount' => '',
            'faqAsideHeading' => 'Historical ranges, clearly labeled',
            'faqAsideCopy' => 'Every Milwaukee-area project receives its own inspected and written price.',
            'faqs' => array(
                array(
                    'question' => 'How much does a garage door cost in Milwaukee?',
                    'answer' => 'Historical planning ranges from completed Twins Garage Doors jobs are $400 to $1,050 for garage door repair, $3,000 to $4,100 for a new garage door installed, and $900 to $1,450 for a new opener installed. Every Milwaukee project is inspected and priced individually.',
                ),
                array(
                    'question' => 'How much does garage door spring replacement cost?',
                    'answer' => 'Among completed repair jobs that included spring replacement, the middle 50% of total invoices ranged from $780 to $1,660. Those totals may include labor and related parts. This is not a spring-only parts price.',
                ),
                array(
                    'question' => 'Is there a fee for a garage door service call?',
                    'answer' => 'The service call and diagnostic fee is $49. A technician inspects the door and provides an exact price before repair work begins.',
                ),
                array(
                    'question' => 'Can I finance a new garage door?',
                    'answer' => 'Twins Garage Doors offers financing through GoodLeap for qualifying new garage door projects. Available terms are explained during the quote process.',
                ),
                array(
                    'question' => 'Are these garage door prices guaranteed?',
                    'answer' => 'No. The displayed figures are historical planning ranges, not estimates, offers, or guaranteed prices. Every project is inspected and priced individually. Your written Twins Garage Doors quote is the only price that applies to your project.',
                ),
            ),
        )),
    );

    if (!isset($markets[$market])) {
        twins_overhaul_refuse_route('cost market is outside the fixed Madison/Milwaukee map.');
    }

    return $markets[$market];
}
