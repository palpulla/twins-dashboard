<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}

$editorialKinds = [
    'location' => [
        'kicker' => 'Local garage door service',
        'answer' => 'Twins Garage Doors repairs and installs garage doors, springs, openers, and cables in this area. Call the local number or request a quote for a clear diagnosis and practical options.',
    ],
    'trust' => [
        'kicker' => 'About Twins',
        'answer' => 'Twins Garage Doors is a local, licensed, and insured garage door company. The details below explain how we work. For anything specific to your home or property, call or request a quote and a real person will help.',
    ],
    'article' => [
        'kicker' => 'Garage door resource',
        'answer' => 'This guide comes from the Twins team. Use what applies to your door, and leave spring or cable work to trained professionals. For help with your specific situation, call Twins or request a quote.',
    ],
];
if (!isset($editorialKinds[$kind])) {
    throw new DomainException('Editorial kind is unavailable.');
}

$editorial = $editorialKinds[$kind];
$isLocation = $kind === 'location';
$isArticle = $kind === 'article';
$locationRecord = $isLocation && isset($locationContent) && is_array($locationContent)
    ? $locationContent
    : null;
$title = isset($context['title']) && is_string($context['title']) && trim($context['title']) !== ''
    ? trim($context['title'])
    : 'Twins Garage Doors';
$locationCity = $title;
$locationCityIsClean = $isLocation
    && str_word_count($title) <= 3
    && stripos($title, 'garage') === false;
if ($locationCityIsClean) {
    $title = 'Garage Door Service in ' . $locationCity;
    $editorial['answer'] = 'Twins Garage Doors repairs garage doors, openers, springs, cables, rollers, and tracks in ' . $locationCity . ' and the nearby area. We also install replacement garage doors and opener systems when repair is not the sound choice.';
}
$locationLabel = $locationRecord !== null ? $locationRecord['label'] : $locationCity;
if ($locationRecord !== null && $locationRecord['intro'] !== '') {
    $editorial['answer'] = $locationRecord['intro'];
}

$editorialFaqs = [];
if ($locationRecord !== null && $locationRecord['faq'] !== []) {
    foreach ($locationRecord['faq'] as $faq) {
        $editorialFaqs[] = ['question' => $faq['q'], 'answer' => $faq['a']];
    }
} elseif (isset($context['faqPage']['faqs']) && is_array($context['faqPage']['faqs'])) {
    $editorialFaqs = $context['faqPage']['faqs'];
}
if ($isLocation) {
    $locationSharedFaqs = [
        [
            'question' => 'Can you repair my garage door, or will it need to be replaced?',
            'answer' => 'Many doors can be repaired when the panels and main structure are in sound condition. Replacement may make more sense when damage is extensive, sections are failing, or the door no longer meets the home’s safety, insulation, or appearance needs. We inspect the system and explain both options when both are reasonable.',
        ],
        [
            'question' => 'Do you service garage door openers?',
            'answer' => 'Yes. We troubleshoot opener power, controls, safety sensors, travel settings, drive systems, and the connection between the opener and the door. We also check whether the door moves freely by hand, because a door problem can look like an opener failure.',
        ],
        [
            'question' => 'What should I do if a spring or cable breaks?',
            'answer' => 'Stop operating the door and keep people, pets, and vehicles clear of it. Springs and cables hold significant tension, and a damaged door can move unexpectedly. Call Twins so a trained technician can inspect the system and make the repair safely.',
        ],
    ];
    $editorialFaqs = array_slice(array_merge($editorialFaqs, $locationSharedFaqs), 0, 5);
}

$locationServiceCards = $isLocation
    ? [
        [
            'title' => 'Garage Door Repair',
            'route' => 'repair',
            'art' => 'spring',
            'description' => 'We diagnose broken springs, damaged cables, worn rollers, track problems, noisy movement, uneven travel, and doors that will not open or close correctly.',
            'items' => ['Full door-system inspection', 'Repair options explained first', 'Balance and safety check'],
        ],
        [
            'title' => 'Garage door opener service',
            'route' => 'opener-repair',
            'art' => 'keypad',
            'description' => 'We troubleshoot motors, remotes, wall controls, safety sensors, travel settings, drive systems, and the connection between the opener and the door.',
            'items' => ['Sensor and control diagnosis', 'Drive and travel inspection', 'Opener replacement options'],
        ],
        [
            'title' => 'Garage door installation',
            'route' => 'installation',
            'art' => 'door',
            'description' => 'When a door is extensively damaged or no longer fits the home, we measure the opening and explain construction, insulation, window, and finish choices.',
            'items' => ['Opening measured before quoting', 'Door and track options explained', 'Complete operating-system setup'],
        ],
    ]
    : [];

$articleServiceLinks = $isArticle
    ? [
        ['Garage Door Repair', 'repair'],
        ['Garage Door Installation', 'installation'],
        ['Spring Repair', 'spring-repair'],
        ['Opener Repair', 'opener-repair'],
        ['Customer Reviews', 'reviews'],
    ]
    : [];
$articleHeroImage = $isArticle && isset($articleHero) && is_string($articleHero) ? $articleHero : '';
$editorialTitleId = $isLocation ? 'twins-location-title' : 'twins-brand-editorial-title';
$editorialTitleMarkup = '<h1 id="' . $editorialTitleId . '">' . ($isLocation
    ? '<span>Garage door service</span><span class="twins-location-title-accent">in ' . htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') . '</span>'
    : htmlspecialchars($title, ENT_QUOTES, 'UTF-8')) . '</h1>';

$napSummary = [];
$napRating = null;
$napCount = '';
$napAddress = '';
$locationNearbyLinks = [];
$locationPath = isset($context['path']) && is_string($context['path']) ? $context['path'] : '';
$normalizedEditorialPath = '/' . trim(preg_replace(
    '~^/(wi|ky|il)/~',
    '/',
    '/' . ltrim($locationPath, '/')
), '/') . '/';
$isFinancing = $kind === 'trust' && $normalizedEditorialPath === '/financing/';
$financingApplyHref = 'https://wisetack.us/#/ifbtqfh/prequalify';
if ($isFinancing) {
    $content = preg_replace('/\s*Click to Apply\s*/i', '', $content);
    if (!is_string($content)) {
        throw new DomainException('Financing content could not be prepared.');
    }
}
$locationNavMarketKey = $marketKey;
if ($isLocation) {
    $napSummaryFile = dirname(__DIR__) . '/config/review-summary.php';
    $napSummary = is_file($napSummaryFile) ? require $napSummaryFile : [];
    $napRating = isset($napSummary['ratingValue']) ? $napSummary['ratingValue'] : null;
    $napCount = isset($napSummary['displayCount']) ? (string) $napSummary['displayCount'] : '';

    $metroAddresses = [
        'madison' => '2921 Landmark Pl, Ste 206, Madison, WI 53713',
        'milwaukee' => '11220 W Burleigh St Ste 100, Wauwatosa, WI 53222',
        'rockford' => '5758 Elaine Dr, Rockford, IL 61108',
    ];
    $locationMetro = $locationRecord !== null ? $locationRecord['metro'] : '';
    $napAddress = isset($context['metroAddress']) && is_string($context['metroAddress']) && $context['metroAddress'] !== ''
        ? $context['metroAddress']
        : ($metroAddresses[$locationMetro] ?? (isset($market['address']) && is_string($market['address']) ? $market['address'] : ''));

    if (
        $locationMetro === 'rockford'
        || $marketKey === 'il-preview'
        || preg_match('~^/il/location/[a-z][a-z0-9-]{0,39}/$~D', $locationPath) === 1
    ) {
        $locationNavMarketKey = 'il-preview';
    } elseif (
        $marketKey === 'ky'
        || preg_match('~^/ky/location/[a-z][a-z0-9-]{0,39}/$~D', $locationPath) === 1
    ) {
        $locationNavMarketKey = 'ky';
    } else {
        $locationNavMarketKey = 'wi';
    }
    $nearbyByMetro = [
        'madison' => [
            ['Madison', 'city-madison'],
            ['Belleville', 'city-belleville'],
            ['Cottage Grove', 'city-cottage-grove'],
            ['Fitchburg', 'city-fitchburg'],
            ['Middleton', 'city-middleton'],
            ['Sun Prairie', 'city-sun-prairie'],
            ['Verona', 'city-verona'],
        ],
        'milwaukee' => [
            ['Milwaukee', 'city-milwaukee'],
            ['Wauwatosa', 'city-wauwatosa'],
            ['Waukesha', 'city-waukesha'],
            ['Brookfield', 'city-brookfield'],
            ['New Berlin', 'city-new-berlin'],
            ['Greenfield', 'city-greenfield'],
            ['Oak Creek', 'city-oak-creek'],
        ],
        'rockford' => [
            ['Rockford', 'city-rockford'],
            ['Loves Park', 'city-loves-park'],
            ['Machesney Park', 'city-machesney-park'],
            ['Belvidere', 'city-belvidere'],
            ['Roscoe', 'city-roscoe'],
            ['Rockton', 'city-rockton'],
            ['Cherry Valley', 'city-cherry-valley'],
        ],
    ];
    $locationNearbyLinks = array_slice(array_values(array_filter(
        $nearbyByMetro[$locationMetro] ?? [],
        static fn(array $item): bool => strpos($item[1], 'city-') === 0 && strcasecmp($item[0], $locationLabel) !== 0
    )), 0, 6);
    if ($locationRecord !== null && isset($locationRecord['neighborLinks'])) {
        $locationNearbyLinks = [];
        foreach ($locationRecord['neighborLinks'] as $locationNeighbor) {
            $locationNearbyLinks[] = [$locationNeighbor['label'], 'city-' . $locationNeighbor['slug']];
        }
    }
}
$locationMapEmbedUrl = $locationRecord !== null && isset($locationRecord['mapEmbedUrl'])
    ? $locationRecord['mapEmbedUrl']
    : '';
$locationMetroNap = [
    'madison' => ['street' => '2921 Landmark Pl, Ste 206', 'locality' => 'Madison', 'region' => 'WI', 'postalCode' => '53713'],
    'milwaukee' => ['street' => '11220 W Burleigh St Ste 100', 'locality' => 'Wauwatosa', 'region' => 'WI', 'postalCode' => '53222'],
    'rockford' => ['street' => '5758 Elaine Dr', 'locality' => 'Rockford', 'region' => 'IL', 'postalCode' => '61108'],
];
$locationNap = $locationRecord !== null ? ($locationMetroNap[$locationRecord['metro']] ?? null) : null;

// Verbatim Google review quotes: a service-tagged pool without city attribution,
// rotated deterministically per location path so pages differ without ever
// relabeling a quote as city-specific.
$locationReviewQuotes = [];
if ($locationRecord !== null) {
    $locationQuotesFile = dirname(__DIR__) . '/config/review-quotes.php';
    $locationQuotesPool = is_file($locationQuotesFile) ? require $locationQuotesFile : [];
    if (is_array($locationQuotesPool)) {
        $locationQuotesPool = array_values(array_filter(
            $locationQuotesPool,
            static fn($quote): bool => is_array($quote)
                && isset($quote['text'], $quote['author'], $quote['rating'], $quote['date'])
                && is_string($quote['text']) && trim($quote['text']) !== ''
                && is_string($quote['author']) && trim($quote['author']) !== ''
                && is_int($quote['rating']) && $quote['rating'] >= 1 && $quote['rating'] <= 5
                && is_string($quote['date'])
        ));
        $locationQuoteCount = count($locationQuotesPool);
        if ($locationQuoteCount >= 3) {
            $locationQuoteOffset = crc32($locationPath) % $locationQuoteCount;
            for ($locationQuoteIndex = 0; $locationQuoteIndex < 3; $locationQuoteIndex++) {
                $locationReviewQuotes[] = $locationQuotesPool[($locationQuoteOffset + $locationQuoteIndex) % $locationQuoteCount];
            }
        }
    }
}

// Helpful resources: the metro cost guide plus the two evergreen guides linked
// from every city page. Rockford borrows the Madison guide until an Illinois
// cost page exists.
$locationResourceLinks = [];
if ($locationRecord !== null) {
    $locationCostGuides = [
        'madison' => ['How much does a garage door cost in Madison?', '/wi/garage-door-cost-in-madison-wi/'],
        'milwaukee' => ['How much does a garage door cost in Milwaukee?', '/wi/garage-door-cost-in-milwaukee-wi/'],
        'rockford' => ['How much does a garage door cost in Madison?', '/wi/garage-door-cost-in-madison-wi/'],
    ];
    $locationResourceLinks = [
        $locationCostGuides[$locationRecord['metro']] ?? $locationCostGuides['madison'],
        ['Is it cheaper to repair or replace a garage door?', '/is-it-cheaper-to-repair-or-replace-a-garage-door/'],
        ['Belt drive vs. chain drive garage door openers', '/belt-drive-vs-chain-drive-openers/'],
    ];
}
// Structured data for record-backed location pages: the schema stack from the
// approved city-page template (HomeAndConstructionBusiness + BreadcrumbList +
// FAQPage). The FAQPage mirrors only the FAQs rendered on this page.
$locationJsonLd = '';
if ($isLocation && $locationRecord !== null && isset($locationRecord['lat'], $locationRecord['lng']) && $locationNap !== null) {
    $locationStateName = $locationRecord['metro'] === 'rockford' ? 'Illinois' : 'Wisconsin';
    $locationStateAbbr = $locationRecord['metro'] === 'rockford' ? 'IL' : 'WI';
    $locationAbsoluteUrl = static fn(string $urlPath): string => function_exists('home_url') ? (string) home_url($urlPath) : $urlPath;
    $locationPageUrl = $locationAbsoluteUrl($locationPath);
    $locationSchemaBusiness = [
        '@type' => 'HomeAndConstructionBusiness',
        '@id' => $locationPageUrl . '#business',
        'name' => 'Twins Garage Doors',
        'url' => $locationPageUrl,
        'telephone' => '+1' . preg_replace('/\D+/', '', $phone),
        'priceRange' => '$$',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $locationNap['street'],
            'addressLocality' => $locationNap['locality'],
            'addressRegion' => $locationNap['region'],
            'postalCode' => $locationNap['postalCode'],
            'addressCountry' => 'US',
        ],
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => $locationRecord['lat'],
            'longitude' => $locationRecord['lng'],
        ],
        'areaServed' => [
            '@type' => 'City',
            'name' => $locationLabel,
            'containedInPlace' => ['@type' => 'State', 'name' => $locationStateName],
        ],
        'openingHoursSpecification' => [[
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'opens' => '00:00',
            'closes' => '23:59',
        ]],
        'hasOfferCatalog' => [
            '@type' => 'OfferCatalog',
            'name' => 'Garage door services in ' . $locationLabel . ', ' . $locationStateAbbr,
            'itemListElement' => array_map(
                static fn(string $serviceName): array => [
                    '@type' => 'Offer',
                    'itemOffered' => ['@type' => 'Service', 'name' => $serviceName],
                ],
                [
                    'Garage Door Repair',
                    'Garage Door Spring Repair',
                    'Garage Door Cable Repair',
                    'Garage Door Opener Repair',
                    'Garage Door Installation',
                    'Emergency Garage Door Repair',
                ]
            ),
        ],
    ];
    if ($napRating !== null && $napCount !== '') {
        $locationSchemaBusiness['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $napRating,
            'reviewCount' => (int) $napCount,
            'bestRating' => 5,
        ];
    }
    $locationSchemaGraph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            $locationSchemaBusiness,
            [
                '@type' => 'BreadcrumbList',
                '@id' => $locationPageUrl . '#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $locationAbsoluteUrl('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Locations', 'item' => $locationAbsoluteUrl($experience->route('service-area', $locationNavMarketKey))],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $locationStateAbbr, 'item' => $locationAbsoluteUrl($experience->route($locationStateAbbr === 'IL' ? 'il-preview' : 'wi', $locationNavMarketKey))],
                    ['@type' => 'ListItem', 'position' => 4, 'name' => $locationLabel, 'item' => $locationPageUrl],
                ],
            ],
            [
                '@type' => 'FAQPage',
                '@id' => $locationPageUrl . '#faq',
                'mainEntity' => array_map(
                    static fn(array $faq): array => [
                        '@type' => 'Question',
                        'name' => (string) $faq['question'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $faq['answer']],
                    ],
                    $editorialFaqs
                ),
            ],
        ],
    ];
    $locationJsonLdCandidate = json_encode(
        $locationSchemaGraph,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
    );
    if (is_string($locationJsonLdCandidate) && $locationJsonLdCandidate !== '' && stripos($locationJsonLdCandidate, '<') === false) {
        $locationJsonLd = $locationJsonLdCandidate;
    }
}
$locationNearbyKicker = $locationNavMarketKey === 'ky' ? 'Kentucky garage door services' : 'Nearby service areas';
$locationNearbyTitle = $locationNavMarketKey === 'ky'
    ? 'Explore repair, installation, and opener service'
    : 'Garage door help beyond ' . $locationLabel;
$locationNearbyActionRoute = $locationNavMarketKey === 'ky' ? 'services' : 'service-area';
$locationNearbyActionLabel = $locationNavMarketKey === 'ky' ? 'View all garage door services' : 'View all service areas';
require_once dirname(__DIR__) . '/components/door-art.php';
$twinCharacterComponent = dirname(__DIR__) . '/components/twin-character.php';
?>
<div id="twins-overhaul-main" class="twins-brand-page twins-brand-editorial-page<?= $isArticle ? ' twins-brand-article-page' : '' ?><?= $isLocation ? ' twins-location-page' : '' ?>">
  <?php if ($isLocation): ?>
    <header class="twins-location-hero" aria-labelledby="twins-location-title" data-location-reveal>
      <div class="twins-location-hero-copy">
        <span class="twins-brand-kicker">Garage door help in <?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <?= $editorialTitleMarkup ?>
        <p><?= htmlspecialchars($editorial['answer'], ENT_QUOTES, 'UTF-8') ?></p>
        <div class="twins-location-actions">
          <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Get a Free Quote</a>
          <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
        </div>
      </div>
      <figure class="twins-location-hero-media">
        <?php
        $logicalKey = 'technician-at-work';
        $sizes = '(max-width: 768px) 100vw, 42vw';
        $class = 'twins-location-hero-image';
        $loading = 'eager';
        require dirname(__DIR__) . '/components/picture.php';
        ?>
        <figcaption>Careful diagnosis. Clear options. Work centered on the complete door system.</figcaption>
      </figure>
    </header>

    <section class="twins-location-trust" role="list" aria-label="Why homeowners call Twins Garage Doors" data-location-reveal>
      <div role="listitem">
        <?php if ($napRating !== null): ?>
          <strong><span class="twins-brand-stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span> <span data-twins-live-rating><?= htmlspecialchars((string) $napRating, ENT_QUOTES, 'UTF-8') ?></span> on Google</strong>
          <span><?= $napCount !== '' ? '<span data-twins-live-count>' . htmlspecialchars($napCount, ENT_QUOTES, 'UTF-8') . '</span> customer reviews' : 'Verified customer reviews' ?></span>
        <?php else: ?>
          <strong>Customer-reviewed service</strong><span>Real feedback from Twins customers</span>
        <?php endif; ?>
      </div>
      <div role="listitem"><strong>Family owned</strong><span>Run by twin brothers, not a franchise</span></div>
      <div role="listitem"><strong>Licensed and insured</strong><span>Professional service for your home</span></div>
    </section>

    <section class="twins-location-services" aria-labelledby="twins-location-services-title" data-location-reveal>
      <?php
      $character = 'right';
      $placement = 'services';
      require $twinCharacterComponent;
      ?>
      <div class="twins-location-section-heading">
        <span class="twins-brand-kicker">How we can help</span>
        <h2 id="twins-location-services-title">The right service for the door you have.</h2>
        <p>Twins checks the complete system before recommending repair, opener work, or replacement.</p>
      </div>
      <div class="twins-location-service-grid">
        <?php foreach ($locationServiceCards as $serviceCard): ?>
          <article class="twins-location-service-card">
            <?= twins_brand_door_art($serviceCard['art'], 'twins-location-service-art', 'location-service-' . $serviceCard['art']) ?>
            <h3><?= htmlspecialchars($serviceCard['title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars($serviceCard['description'], ENT_QUOTES, 'UTF-8') ?></p>
            <a class="twins-location-service-link" href="<?= htmlspecialchars($experience->route($serviceCard['route'], $locationNavMarketKey), ENT_QUOTES, 'UTF-8') ?>">Explore <?= htmlspecialchars($serviceCard['title'], ENT_QUOTES, 'UTF-8') ?></a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="twins-location-local-proof" aria-labelledby="twins-location-local-proof-title" data-location-reveal>
      <figure class="twins-location-local-proof-media">
        <?php
        $logicalKey = 'door-builder-before-after';
        $sizes = '(max-width: 768px) 100vw, 42vw';
        $class = 'twins-location-local-proof-image';
        $loading = 'lazy';
        require dirname(__DIR__) . '/components/picture.php';
        ?>
      </figure>
      <div class="twins-location-local-proof-copy">
        <span class="twins-brand-kicker">Local garage door service</span>
        <h2 id="twins-location-local-proof-title">Practical help for <?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?> homeowners.</h2>
        <?php if ($locationRecord !== null): foreach ($locationRecord['localNotes'] as $locationNote): ?>
          <p><?= htmlspecialchars($locationNote, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endforeach; endif; ?>
        <?php if ($napAddress !== ''): ?><p class="twins-location-address"><?= htmlspecialchars($napAddress, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <ul class="twins-location-proof-list">
          <li><strong>Complete system inspection</strong><span>Door, hardware, opener, controls, and safety equipment checked together.</span></li>
          <li><strong>Plain-language options</strong><span>Repair and replacement paths explained before work moves forward.</span></li>
          <li><strong>Respect for your home</strong><span>Licensed and insured service centered on safe, reliable operation.</span></li>
        </ul>
      </div>
    </section>

    <?php if ($locationReviewQuotes !== []): ?>
      <section class="twins-location-reviews" aria-labelledby="twins-location-reviews-title" data-location-reveal>
        <div class="twins-brand-section-heading">
          <span class="twins-brand-kicker">Verified customer stories</span>
          <h2 id="twins-location-reviews-title">What Twins customers say</h2>
          <p class="twins-brand-google-attribution" aria-label="Verified Google reviews">Verbatim Google reviews from real Twins customers</p>
        </div>
        <div class="twins-location-review-grid">
          <?php foreach ($locationReviewQuotes as $locationQuote): ?>
            <article class="twins-location-review-card">
              <div class="twins-brand-review-stars" aria-label="<?= (int) $locationQuote['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $locationQuote['rating']) ?></div>
              <blockquote><?= htmlspecialchars($locationQuote['text'], ENT_QUOTES, 'UTF-8') ?></blockquote>
              <footer>
                <strong><?= htmlspecialchars($locationQuote['author'], ENT_QUOTES, 'UTF-8') ?></strong>
                <time datetime="<?= htmlspecialchars($locationQuote['date'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($locationQuote['date'], ENT_QUOTES, 'UTF-8') ?></time>
              </footer>
            </article>
          <?php endforeach; ?>
        </div>
        <a class="twins-brand-text-link" href="<?= htmlspecialchars($experience->route('reviews', $locationNavMarketKey), ENT_QUOTES, 'UTF-8') ?>">Read all reviews</a>
      </section>
    <?php endif; ?>

    <?php if ($locationMapEmbedUrl !== ''): ?>
      <section class="twins-location-map-section" aria-labelledby="twins-location-map-title" data-location-reveal>
        <div class="twins-brand-section-heading">
          <span class="twins-brand-kicker">Find us on the map</span>
          <h2 id="twins-location-map-title"><?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?> service area</h2>
        </div>
        <iframe class="twins-brand-location-map" title="Map of <?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?>" src="<?= htmlspecialchars($locationMapEmbedUrl, ENT_QUOTES, 'UTF-8') ?>" width="600" height="380" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <?php if ($napAddress !== ''): ?>
          <p class="twins-location-map-nap">
            <strong>Serving <?= htmlspecialchars($locationLabel, ENT_QUOTES, 'UTF-8') ?> from</strong>
            <span><?= htmlspecialchars($napAddress, ENT_QUOTES, 'UTF-8') ?></span>
            <a href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
          </p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($locationNearbyLinks !== []): ?>
      <section class="twins-location-neighbors" aria-labelledby="twins-location-neighbors-title" data-location-reveal>
        <div class="twins-brand-section-heading">
          <span class="twins-brand-kicker"><?= htmlspecialchars($locationNearbyKicker, ENT_QUOTES, 'UTF-8') ?></span>
          <h2 id="twins-location-neighbors-title"><?= htmlspecialchars($locationNearbyTitle, ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <nav class="twins-brand-location-links" aria-label="Nearby areas we serve">
          <?php foreach ($locationNearbyLinks as [$locationNearbyLabel, $locationNearbyRoute]): ?>
            <a href="<?= htmlspecialchars($experience->route($locationNearbyRoute, $locationNavMarketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($locationNearbyLabel, ENT_QUOTES, 'UTF-8') ?></a>
          <?php endforeach; ?>
        </nav>
        <a class="twins-brand-text-link" href="<?= htmlspecialchars($experience->route($locationNearbyActionRoute, $locationNavMarketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($locationNearbyActionLabel, ENT_QUOTES, 'UTF-8') ?></a>
      </section>
    <?php endif; ?>

    <?php if ($locationResourceLinks !== []): ?>
      <section class="twins-location-resources" aria-labelledby="twins-location-resources-title" data-location-reveal>
        <div class="twins-brand-section-heading">
          <span class="twins-brand-kicker">Helpful resources</span>
          <h2 id="twins-location-resources-title">Guides from the Twins team</h2>
        </div>
        <nav class="twins-brand-location-links twins-location-resource-links" aria-label="Helpful garage door guides">
          <?php foreach ($locationResourceLinks as [$locationResourceLabel, $locationResourceHref]): ?>
            <a href="<?= htmlspecialchars($locationResourceHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($locationResourceLabel, ENT_QUOTES, 'UTF-8') ?></a>
          <?php endforeach; ?>
        </nav>
      </section>
    <?php endif; ?>
    <?php if ($editorialFaqs !== []): ?>
      <section class="twins-brand-faq" aria-labelledby="twins-location-questions-title" data-location-reveal>
        <div class="twins-brand-section-heading">
          <span class="twins-brand-kicker">Frequently asked questions</span>
          <h2 id="twins-location-questions-title">Garage door questions, answered straight</h2>
        </div>
        <div class="twins-brand-faq-list">
          <?php foreach ($editorialFaqs as $faq): ?>
            <details><summary><?= htmlspecialchars((string) $faq['question'], ENT_QUOTES, 'UTF-8') ?></summary><p><?= htmlspecialchars((string) $faq['answer'], ENT_QUOTES, 'UTF-8') ?></p></details>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
    <?php if ($locationJsonLd !== ''): ?>
      <script type="application/ld+json"><?= $locationJsonLd ?></script>
    <?php endif; ?>
  <?php else: ?>
    <?php if ($articleHeroImage !== ''): ?>
      <figure class="twins-brand-article-hero-media">
        <img src="<?= htmlspecialchars($articleHeroImage, ENT_QUOTES, 'UTF-8') ?>" width="1200" height="675" alt="" aria-hidden="true" decoding="async" fetchpriority="high">
      </figure>
    <?php endif; ?>
    <header class="twins-brand-editorial-hero<?= $isArticle ? ' twins-brand-article-hero' : '' ?>" aria-labelledby="twins-brand-editorial-title">
      <span class="twins-brand-kicker"><?= htmlspecialchars($editorial['kicker'], ENT_QUOTES, 'UTF-8') ?></span>
      <?= $editorialTitleMarkup ?>
    </header>

    <?php if (!$isArticle): ?>
      <section class="twins-brand-editorial-answer" aria-labelledby="twins-brand-editorial-answer-title">
        <div>
          <span class="twins-brand-kicker">Direct answer</span>
          <h2 id="twins-brand-editorial-answer-title">What to know first</h2>
          <p><?= htmlspecialchars($editorial['answer'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
      </section>
    <?php endif; ?>

    <?php if ($isFinancing): ?>
      <section class="twins-brand-editorial-answer" aria-labelledby="twins-brand-financing-action-title">
        <div>
          <span class="twins-brand-kicker">Financing through Wisetack</span>
          <h2 id="twins-brand-financing-action-title">Review your financing options</h2>
          <p>The application opens on Wisetack in a new tab. Review the terms there before continuing.</p>
        </div>
        <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($financingApplyHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Apply with Wisetack</a>
      </section>
    <?php endif; ?>

    <section class="twins-brand-editorial-body<?= $isArticle ? ' twins-brand-article-body' : '' ?>">
      <article class="twins-brand-editorial-content<?= $isArticle ? ' twins-brand-article-content' : '' ?>">
        <?= $content ?>
      </article>
    </section>

    <?php if ($editorialFaqs !== []): ?>
      <section class="twins-brand-faq" aria-labelledby="twins-brand-faq-page-title">
        <div class="twins-brand-section-heading">
          <span class="twins-brand-kicker">Frequently asked questions</span>
          <h2 id="twins-brand-faq-page-title">Garage door questions, answered straight</h2>
        </div>
        <div class="twins-brand-faq-list">
          <?php foreach ($editorialFaqs as $faq): ?>
            <details><summary><?= htmlspecialchars((string) $faq['question'], ENT_QUOTES, 'UTF-8') ?></summary><p><?= htmlspecialchars((string) $faq['answer'], ENT_QUOTES, 'UTF-8') ?></p></details>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($articleServiceLinks !== []): ?>
      <section class="twins-brand-editorial-services twins-brand-article-services" aria-labelledby="twins-brand-article-services-title">
        <div class="twins-brand-section-heading">
          <span class="twins-brand-kicker">Need hands-on help?</span>
          <h2 id="twins-brand-article-services-title">Services related to this guide</h2>
        </div>
        <nav class="twins-brand-location-links" aria-label="Related garage door services">
          <?php foreach ($articleServiceLinks as [$articleLabel, $articleRoute]): ?>
            <a href="<?= htmlspecialchars($experience->route($articleRoute, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($articleRoute, $marketKey, $articleLabel), ENT_QUOTES, 'UTF-8') ?></a>
          <?php endforeach; ?>
        </nav>
      </section>
    <?php endif; ?>
  <?php endif; ?>

  <?php $finalCtaArtKind = $isLocation ? 'door' : 'door-open'; ?>
  <section class="twins-brand-final-cta<?= $isLocation ? ' twins-location-final-cta' : '' ?>" aria-labelledby="twins-brand-editorial-final-title"<?= $isLocation ? ' data-location-reveal' : '' ?>>
    <?php if ($isLocation): ?>
      <?php
      $character = 'left';
      $placement = 'final-left';
      require $twinCharacterComponent;
      $character = 'right';
      $placement = 'final-right';
      require $twinCharacterComponent;
      ?>
    <?php endif; ?>
    <?= twins_brand_door_art($finalCtaArtKind, 'twins-brand-cta-art', 'editorial-final') ?>
    <span class="twins-brand-kicker"><?= htmlspecialchars($isLocation ? $locationLabel : $market['label'], ENT_QUOTES, 'UTF-8') ?></span>
    <h2 id="twins-brand-editorial-final-title"><?= $isLocation ? 'Tell us what your garage door is doing.' : 'Need a project-specific answer?' ?></h2>
    <?php if ($isLocation): ?><p>Call Twins or request a quote. We will help you choose the right next step for the door, opener, or installation.</p><?php endif; ?>
    <div class="twins-brand-final-actions">
      <?php if ($isLocation): ?>
        <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Get a Free Quote</a>
        <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
      <?php else: ?>
        <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= $isArticle ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : 'Twins' ?></a>
        <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
      <?php endif; ?>
    </div>
  </section>
</div>
