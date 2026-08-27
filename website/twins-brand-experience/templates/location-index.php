<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}

require dirname(__DIR__) . '/components/nav-data.php';
require_once dirname(__DIR__) . '/components/door-art.php';

$copyByMarket = [
    'main' => [
        'kicker' => 'Where we work',
        'title' => 'Garage door service areas',
        'intro' => 'Choose your state to find the local Twins Garage Doors team, phone number, services, and nearby communities.',
        'areasTitle' => 'Find your local Twins team',
    ],
    'wi' => [
        'kicker' => 'Wisconsin service areas',
        'title' => 'Garage door service across Wisconsin',
        'intro' => 'Twins Garage Doors serves homeowners across the Madison and Milwaukee areas. Choose your community for local repair, opener, and installation information.',
        'areasTitle' => 'Wisconsin communities we serve',
    ],
    'il-preview' => [
        'kicker' => 'Illinois service areas',
        'title' => 'Garage door service across northern Illinois',
        'intro' => 'Our staffed Rockford office serves Rockford and nearby northern Illinois communities. Choose your city for local repair, opener, and installation information.',
        'areasTitle' => 'Illinois communities we serve',
    ],
];
if (!isset($copyByMarket[$marketKey])) {
    throw new DomainException('Service-area index market is unavailable.');
}
$copy = $copyByMarket[$marketKey];
$cityLinks = array_values(array_filter(
    $marketCityLinks,
    static fn(array $item): bool => isset($item[1]) && is_string($item[1]) && strpos($item[1], 'city-') === 0
));

// The long tail lives here now, so this page has to earn the click. Every metro
// group in the header links to an anchor below, and the towns the header does
// NOT carry -- provisioned WP pages with no approved copy record -- are linked
// exactly once, from here, as "Nearby communities we also serve". That single
// link is what keeps 24 indexed pages from being orphaned; a sitewide header
// link would spend crawl budget on generic pages and tell Google they rank as
// peers of Madison.
//
// Deliberately NOT an A-Z rail: at this size an alphabet index costs 26 targets
// to save at most one screen of scanning, and alphabetical order inside each
// metro already does that job. The threshold where a letter rail earns its
// place is roughly 150+ entries.
$metroSections = [];
$featuredRouteKeys = [];
foreach ($twinsNavAreaMetros as $twinsNavMetro) {
    $metroTowns = [];
    foreach ($twinsNavMetro['towns'] as [$townLabel, $townRoute]) {
        $featuredRouteKeys[] = $townRoute;
        $metroTowns[] = [$townLabel, $townRoute];
    }
    // Anchor ids match $twinsNavMetroTree['hubAnchor'] exactly.
    $metroSections[] = [
        'id' => ltrim($twinsNavMetro['hubAnchor'], '#'),
        'label' => $twinsNavMetro['label'],
        'towns' => $metroTowns,
    ];
}
$nearbyLinks = array_values(array_filter(
    $cityLinks,
    static fn(array $item): bool => !in_array($item[1], $featuredRouteKeys, true)
));

// "Find your local Twins team" on the main index offered two cards, WISCONSIN
// and ILLINOIS, for the same reason /contact-us/ did: it looped the market
// registry. A heading that says "team" has to list the teams, and there are
// three of them, two inside one market. Same source as the contact chooser and
// as the header menu: labels from $twinsNavMetroTree, everything else from the
// metroLines in config/markets.php.
//
// The card still points at the metro's market front door. City routes are
// market-scoped and the main index has none of them, which is the same
// degradation the metro tree already documents; the alternative is four route
// tables' worth of cross-market city keys for one link.
$indexTeams = [];
foreach ($experience->markets()->selectable($environment) as $indexMarketKey => $indexMarket) {
    if ($indexMarketKey === 'main') {
        continue;
    }
    $indexLines = $indexMarket['metroLines'] ?? [['key' => $indexMarketKey, 'label' => $indexMarket['label']]];
    foreach ($indexLines as $indexLine) {
        $indexLineKey = (string) ($indexLine['key'] ?? $indexMarketKey);
        $indexTeams[] = [
            'label' => $twinsNavMetroTree[$indexLineKey]['label'] ?? $indexLine['label'],
            'routeKey' => $indexMarketKey,
            'preview' => $indexMarket['preview'] === true,
        ];
    }
}
?>
<div id="twins-overhaul-main" class="twins-brand-page twins-brand-editorial-page twins-brand-location-index">
  <header class="twins-brand-editorial-hero" aria-labelledby="twins-brand-location-index-title">
    <span class="twins-brand-kicker"><?= htmlspecialchars($copy['kicker'], ENT_QUOTES, 'UTF-8') ?></span>
    <h1 id="twins-brand-location-index-title"><?= htmlspecialchars($copy['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <?= twins_brand_hero_art('door', $experience, 'location-index') ?>
  </header>

  <section class="twins-brand-editorial-answer" aria-labelledby="twins-brand-location-index-answer-title">
    <div>
      <span class="twins-brand-kicker">Local help, one clear starting point</span>
      <h2 id="twins-brand-location-index-answer-title">Choose the area closest to you</h2>
      <p><?= htmlspecialchars($copy['intro'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
  </section>

  <?php if ($marketKey === 'main'): ?>
    <section class="twins-brand-market-selector" aria-labelledby="twins-brand-location-index-areas-title">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Wisconsin and Illinois</span>
        <h2 id="twins-brand-location-index-areas-title"><?= htmlspecialchars($copy['areasTitle'], ENT_QUOTES, 'UTF-8') ?></h2>
      </div>
      <div class="twins-brand-market-grid">
        <?php foreach ($indexTeams as $indexTeam): ?>
          <a class="twins-brand-market-card<?= $indexTeam['preview'] ? ' twins-brand-market-card--preview' : '' ?>" href="<?= htmlspecialchars($experience->route($indexTeam['routeKey'], $marketKey), ENT_QUOTES, 'UTF-8') ?>">
            <strong><?= htmlspecialchars($indexTeam['label'], ENT_QUOTES, 'UTF-8') ?></strong>
            <span>View local service areas</span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else: ?>
    <section class="twins-brand-editorial-services" aria-labelledby="twins-brand-location-index-areas-title">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Where we work</span>
        <h2 id="twins-brand-location-index-areas-title"><?= htmlspecialchars($copy['areasTitle'], ENT_QUOTES, 'UTF-8') ?></h2>
      </div>
      <?php if (count($metroSections) > 1): ?>
        <nav class="twins-brand-location-jump" aria-label="Jump to a metro">
          <span>Jump to:</span>
          <?php foreach ($metroSections as $metroSection): ?>
            <a href="#<?= htmlspecialchars($metroSection['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($metroSection['label'], ENT_QUOTES, 'UTF-8') ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <?php foreach ($metroSections as $metroSection): ?>
        <?php $metroHeadingId = 'twins-brand-metro-' . $metroSection['id']; ?>
        <section class="twins-brand-location-metro" id="<?= htmlspecialchars($metroSection['id'], ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="<?= htmlspecialchars($metroHeadingId, ENT_QUOTES, 'UTF-8') ?>">
          <h3 id="<?= htmlspecialchars($metroHeadingId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($metroSection['label'], ENT_QUOTES, 'UTF-8') ?></h3>
          <?php $metroTownCount = count($metroSection['towns']); ?>
          <p class="twins-brand-location-metro-count"><?= htmlspecialchars((string) $metroTownCount, ENT_QUOTES, 'UTF-8') ?> <?= $metroTownCount === 1 ? 'community' : 'communities' ?> with a local page</p>
          <nav class="twins-brand-location-links" aria-label="<?= htmlspecialchars($metroSection['label'], ENT_QUOTES, 'UTF-8') ?> communities">
            <?php foreach ($metroSection['towns'] as [$cityLabel, $cityRoute]): ?>
              <a href="<?= htmlspecialchars($experience->route($cityRoute, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cityLabel, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </nav>
        </section>
      <?php endforeach; ?>
      <?php if ($nearbyLinks !== []): ?>
        <section class="twins-brand-location-nearby" aria-labelledby="twins-brand-location-index-nearby-title">
          <h3 id="twins-brand-location-index-nearby-title">Nearby communities we also serve</h3>
          <nav class="twins-brand-location-links twins-brand-location-links--secondary" aria-label="Nearby communities we also serve">
            <?php foreach ($nearbyLinks as [$cityLabel, $cityRoute]): ?>
              <a href="<?= htmlspecialchars($experience->route($cityRoute, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cityLabel, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </nav>
        </section>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="twins-brand-editorial-services" aria-labelledby="twins-brand-location-index-services-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">How Twins can help</span>
      <h2 id="twins-brand-location-index-services-title">Garage door services for your area</h2>
    </div>
    <nav class="twins-brand-location-links" aria-label="Garage door services">
      <?php foreach ($serviceItems as [$serviceLabel, $serviceRoute]): ?>
        <a href="<?= htmlspecialchars($experience->route($serviceRoute, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($serviceRoute, $marketKey, $serviceLabel), ENT_QUOTES, 'UTF-8') ?></a>
      <?php endforeach; ?>
    </nav>
  </section>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-location-index-final-title">
    <span class="twins-brand-kicker"><?= htmlspecialchars($market['label'], ENT_QUOTES, 'UTF-8') ?></span>
    <h2 id="twins-brand-location-index-final-title">Need help with your garage door?</h2>
    <p>Tell us what the door is doing. A real person will help you choose the right next step.</p>
    <div class="twins-brand-hero-actions">
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Get a Free Quote</a>
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
    </div>
  </section>
</div>
