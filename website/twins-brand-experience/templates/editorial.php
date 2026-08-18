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
        'madison' => '2921 Landmark Pl #206, Madison, WI 53713',
        'milwaukee' => '11220 W Burleigh St Ste 100, Wauwatosa, WI 53222',
        'rockford' => '5758 Elaine Dr Ste 110, Rockford, IL 61108',
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
        <?php if ($locationRecord !== null && $locationRecord['localNotes'] !== ''): ?>
          <p><?= htmlspecialchars($locationRecord['localNotes'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($napAddress !== ''): ?><p class="twins-location-address"><?= htmlspecialchars($napAddress, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <ul class="twins-location-proof-list">
          <li><strong>Complete system inspection</strong><span>Door, hardware, opener, controls, and safety equipment checked together.</span></li>
          <li><strong>Plain-language options</strong><span>Repair and replacement paths explained before work moves forward.</span></li>
          <li><strong>Respect for your home</strong><span>Licensed and insured service centered on safe, reliable operation.</span></li>
        </ul>
      </div>
    </section>
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
