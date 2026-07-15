<?php
/**
 * Canonical Madison and Milwaukee cost-page renderer for private staging.
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Render one fixed approved market without trusting caller context.
 *
 * @param string $market Exact market key.
 * @param array  $context Internal renderer context; identity is intentionally ignored.
 * @return string
 */
function twins_overhaul_render_cost_page(string $market, array $context): string {
    unset($context);
    $data = twins_overhaul_cost_data($market);
    $rows = $data['priceRows'];
    $contact = '/wi/contact-us/';

    $markup = '<div id="twins-overhaul-main" class="twins-overhaul-main twins-cost" tabindex="-1">';

    $markup .= '<section class="twins-cost-hero"><div class="twins-overhaul-shell twins-cost-hero__grid">';
    $markup .= '<div class="twins-cost-hero__copy">';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($data['eyebrow']) . '</p>';
    $markup .= '<h1 class="twins-cost-hero__title">' . esc_html($data['titleBefore']) . '<span>' . esc_html($data['titleEmphasis']) . '</span>' . esc_html($data['titleAfter']) . '</h1>';
    $markup .= '<p class="twins-cost-hero__lead">' . esc_html($data['lead']) . '</p>';
    $markup .= '<div class="twins-cost-hero__actions">';
    $markup .= '<a class="twins-overhaul-button twins-overhaul-button--primary" href="' . esc_url($contact) . '">Price My Project</a>';
    $markup .= '<a class="twins-overhaul-button twins-cost-secondary-button" href="tel:' . esc_attr($data['tel']) . '">Speak With Twins</a>';
    $markup .= '</div><ul class="twins-cost-hero__notes">';
    foreach ($data['heroNotes'] as $note) {
        $markup .= '<li>' . esc_html($note) . '</li>';
    }
    $markup .= '</ul></div>';
    $markup .= '<aside class="twins-cost-hero__art" aria-label="Garage door cost highlights">';
    $markup .= '<p class="twins-cost-sticker">' . esc_html($data['sticker']) . '</p>';
    $markup .= '<div class="twins-cost-hero__mascots" aria-hidden="true"><img src="' . esc_url(twins_overhaul_asset_url('twin-left')) . '" width="196" height="534" alt=""><img src="' . esc_url(twins_overhaul_asset_url('twin-right')) . '" width="297" height="538" alt=""></div>';
    $markup .= '<div class="twins-cost-hero__deck">';
    foreach (array($rows[1], $rows[3], $rows[2]) as $row) {
        $markup .= '<div class="twins-cost-hero__price"><span>' . esc_html($row['service']) . '</span><strong>' . esc_html($row['range']) . '</strong></div>';
    }
    $markup .= '<p class="twins-cost-short-disclaimer">' . esc_html($data['shortDisclaimer']) . '</p>';
    $markup .= '</div></aside></div></section>';

    $markup .= '<section class="twins-cost-promise" aria-label="Pricing promises"><div class="twins-overhaul-shell"><ul>';
    foreach (array_merge($data['promise'], array($data['localPromise'])) as $promise) {
        $markup .= '<li>' . esc_html($promise) . '</li>';
    }
    $markup .= '</ul></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-cost-answer"><div class="twins-overhaul-shell twins-cost-answer__grid">';
    $markup .= '<article class="twins-cost-answer__card"><p class="twins-overhaul-eyebrow">' . esc_html($data['answerEyebrow']) . '</p><h2>' . esc_html($data['answerHeading']) . '</h2><p class="twins-cost-answer__lead">' . esc_html($data['directAnswer']) . '</p></article>';
    $markup .= '<aside class="twins-overhaul-card twins-cost-method">';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($data['methodEyebrow']) . '</p><h2>' . esc_html($data['methodHeading']) . '</h2><p>' . esc_html($data['methodIntro']) . '</p>';
    $markup .= '<details class="twins-cost-method__disclosure"><summary>' . esc_html($data['methodLabel']) . '</summary><div class="twins-cost-method__body">';
    $markup .= '<p class="twins-cost-source-line">' . esc_html($data['sourceLine']) . '</p><p>' . esc_html($data['methodology']) . '</p>';
    if ($data['samples'] !== array()) {
        $markup .= '<dl class="twins-cost-samples">';
        foreach ($data['samples'] as $sample) {
            $markup .= '<div><dt>' . esc_html($sample['count']) . '</dt><dd>' . esc_html($sample['label']) . '</dd></div>';
        }
        $markup .= '</dl>';
    } else {
        $markup .= '<p class="twins-cost-method__nonnumeric">Historical job data behind these ranges</p>';
    }
    $markup .= '</div></details></aside></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-cost-pricing"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($data['pricingEyebrow']) . '</p><h2>' . esc_html($data['pricingHeading']) . '</h2><p class="twins-cost-section-lede">' . esc_html($data['pricingLede']) . '</p>';
    $markup .= '<div class="twins-cost-table-wrap"><table><thead><tr><th scope="col">Service</th><th scope="col">Typical range</th><th scope="col">What it covers</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $markup .= '<tr><th scope="row">' . esc_html($row['service']) . '</th><td><strong>' . esc_html($row['range']) . '</strong><span>' . esc_html($row['label']) . '</span></td><td>' . esc_html($row['coverage']) . '</td></tr>';
    }
    $markup .= '</tbody></table></div>';
    $markup .= '<p class="twins-cost-short-disclaimer">' . esc_html($data['shortDisclaimer']) . '</p>';
    $markup .= '<aside class="twins-cost-spring-note"><h3>About spring-related repairs</h3><p>' . esc_html($data['springClarification']) . '</p></aside>';
    $markup .= '<aside class="twins-cost-full-disclaimer"><strong>Every Twins project is priced individually</strong><p>' . esc_html($data['fullDisclaimer']) . '</p></aside>';
    $markup .= '</div></section>';

    $markup .= '<section class="twins-overhaul-section twins-cost-factors"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($data['factorsEyebrow']) . '</p><h2>' . esc_html($data['factorsHeading']) . '</h2><p class="twins-cost-section-lede">' . esc_html($data['factorsLede']) . '</p><div class="twins-cost-factor-grid">';
    foreach ($data['factors'] as $index => $factor) {
        $markup .= '<article class="twins-overhaul-card"><span class="twins-cost-factor-number">' . esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) . '</span><h3>' . esc_html($factor['title']) . '</h3><p>' . esc_html($factor['copy']) . '</p></article>';
    }
    $markup .= '</div></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-cost-decision"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($data['decisionEyebrow']) . '</p><h2>' . esc_html($data['decisionHeading']) . '</h2><p class="twins-cost-section-lede">' . esc_html($data['decisionLede']) . '</p><div class="twins-cost-decision__grid">';
    foreach ($data['decisionCards'] as $index => $card) {
        $class = $index === 1 ? ' twins-cost-decision-card--replace' : '';
        $markup .= '<article class="twins-overhaul-card twins-cost-decision-card' . $class . '"><span class="twins-cost-decision-tag">' . esc_html($card['tag']) . '</span><h3>' . esc_html($card['title']) . '</h3><ul>';
        foreach ($card['items'] as $item) {
            $markup .= '<li>' . esc_html($item) . '</li>';
        }
        $markup .= '</ul></article>';
    }
    $markup .= '</div></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-cost-climate"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($data['climateEyebrow']) . '</p><h2>' . esc_html($data['climateHeading']) . '</h2><p class="twins-cost-section-lede">' . esc_html($data['climateLede']) . '</p><div class="twins-cost-climate-grid">';
    foreach ($data['climateCards'] as $index => $card) {
        $markup .= '<article class="twins-cost-climate-card"><strong>' . esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) . '</strong><h3>' . esc_html($card['title']) . '</h3><p>' . esc_html($card['copy']) . '</p></article>';
    }
    $markup .= '</div></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-cost-financing"><div class="twins-overhaul-shell twins-cost-financing__grid">';
    $markup .= '<div><p class="twins-overhaul-eyebrow">' . esc_html($data['financeEyebrow']) . '</p><h2>' . esc_html($data['financeHeading']) . '</h2><p>' . esc_html($data['financeCopy']) . '</p></div>';
    $markup .= '<div class="twins-cost-financing__action"><a class="twins-overhaul-button" href="#twins-cost-coverage">Ask About Financing</a><small>' . esc_html($data['financeDisclosure']) . '</small></div>';
    $markup .= '</div></section>';

    $markup .= '<section class="twins-overhaul-section twins-cost-process"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($data['processEyebrow']) . '</p><h2>' . esc_html($data['processHeading']) . '</h2><p class="twins-cost-section-lede">' . esc_html($data['processLede']) . '</p><ol>';
    foreach ($data['process'] as $step) {
        $markup .= '<li><h3>' . esc_html($step['title']) . '</h3><p>' . esc_html($step['copy']) . '</p></li>';
    }
    $markup .= '</ol></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-cost-faq"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($data['faqEyebrow']) . '</p><h2>' . esc_html($data['faqHeading']) . '</h2><div class="twins-cost-faq__layout"><aside class="twins-cost-faq__aside">';
    if ($data['faqAsideCount'] !== '') {
        $markup .= '<strong>' . esc_html($data['faqAsideCount']) . '</strong>';
    }
    $markup .= '<h3>' . esc_html($data['faqAsideHeading']) . '</h3><p>' . esc_html($data['faqAsideCopy']) . '</p></aside><div class="twins-cost-faq__list">';
    foreach ($data['faqs'] as $index => $faq) {
        $open = $index === 0 ? ' open' : '';
        $markup .= '<details' . $open . '><summary>' . esc_html($faq['question']) . '</summary><p>' . esc_html($faq['answer']) . '</p></details>';
    }
    $markup .= '</div></div></div></section>';

    $markup .= '<section class="twins-overhaul-section twins-cost-service-area" id="twins-cost-coverage"><div class="twins-overhaul-shell twins-cost-service-area__grid">';
    $markup .= '<div class="twins-cost-zip" data-twins-overhaul-zip>';
    $markup .= '<p class="twins-overhaul-eyebrow">' . esc_html($data['coverageEyebrow']) . '</p><h2>Do we serve your ZIP code?</h2>';
    $markup .= '<p>Enter your ZIP and we will point you toward the right Twins service area.</p>';
    $markup .= '<label for="twins-cost-zip-' . esc_attr($data['key']) . '">ZIP code</label>';
    $markup .= '<div class="twins-cost-zip__controls"><input id="twins-cost-zip-' . esc_attr($data['key']) . '" data-twins-zip-input inputmode="numeric" autocomplete="postal-code" maxlength="5" pattern="[0-9]{5}"><button type="button" data-twins-zip-route>Check My ZIP</button></div>';
    $markup .= '<p class="twins-cost-zip__status" data-twins-zip-status role="status" aria-live="polite">Enter a 5-digit ZIP code to review the matching local guide.</p>';
    $markup .= '<p>This private staging preview does not submit or store lead information.</p>';
    $markup .= '<address><strong>Twins Garage Doors</strong><span>' . esc_html($data['street']) . '</span><span>' . esc_html($data['addressLine']) . '</span><a href="tel:' . esc_attr($data['tel']) . '">' . esc_html($data['phone']) . '</a></address>';
    $markup .= '</div>';
    $markup .= '<figure class="twins-overhaul-fleet-proof"><picture><source srcset="' . esc_url(twins_overhaul_asset_url('truck-webp')) . '" type="image/webp"><img src="' . esc_url(twins_overhaul_asset_url('truck-png')) . '" width="1398" height="821" alt="Yellow-and-navy Twins Garage Doors service truck"></picture><figcaption>Real Twins service fleet</figcaption></figure>';
    $markup .= '</div></section>';

    $markup .= '</div>';
    $markup .= twins_overhaul_render_cost_schema($data);
    return $markup;
}

/**
 * Render fixed LocalBusiness and FAQPage data that mirrors the visible page.
 *
 * @param array $data Fixed market dataset.
 * @return string
 */
function twins_overhaul_render_cost_schema(array $data): string {
    $questions = array();
    foreach ($data['faqs'] as $faq) {
        $questions[] = array(
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text' => $faq['answer'],
            ),
        );
    }

    $areas = array();
    foreach ($data['areaServed'] as $area) {
        $areas[] = array('@type' => 'City', 'name' => $area);
    }

    $schema = array(
        '@context' => 'https://schema.org',
        '@graph' => array(
            array(
                '@type' => 'LocalBusiness',
                '@id' => $data['url'] . '#business',
                'name' => 'Twins Garage Doors',
                'url' => $data['url'],
                'telephone' => $data['tel'],
                'address' => array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => $data['street'],
                    'addressLocality' => $data['locality'],
                    'addressRegion' => $data['region'],
                    'postalCode' => $data['postalCode'],
                    'addressCountry' => 'US',
                ),
                'areaServed' => $areas,
            ),
            array(
                '@type' => 'FAQPage',
                'mainEntity' => $questions,
            ),
        ),
    );

    $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || $json === '') {
        twins_overhaul_refuse_route('cost-page schema encoding failed.');
    }
    return '<script type="application/ld+json">' . $json . '</script>';
}
