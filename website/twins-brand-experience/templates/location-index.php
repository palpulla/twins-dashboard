<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}

require dirname(__DIR__) . '/components/nav-data.php';

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
?>
<div id="twins-overhaul-main" class="twins-brand-page twins-brand-editorial-page twins-brand-location-index">
  <header class="twins-brand-editorial-hero" aria-labelledby="twins-brand-location-index-title">
    <span class="twins-brand-kicker"><?= htmlspecialchars($copy['kicker'], ENT_QUOTES, 'UTF-8') ?></span>
    <h1 id="twins-brand-location-index-title"><?= htmlspecialchars($copy['title'], ENT_QUOTES, 'UTF-8') ?></h1>
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
        <?php foreach ($experience->markets()->selectable($environment) as $availableMarketKey => $availableMarket): ?>
          <?php if ($availableMarketKey === 'main') continue; ?>
          <a class="twins-brand-market-card<?= $availableMarket['preview'] === true ? ' twins-brand-market-card--preview' : '' ?>" href="<?= htmlspecialchars($experience->route($availableMarketKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>">
            <strong><?= htmlspecialchars($availableMarket['label'], ENT_QUOTES, 'UTF-8') ?></strong>
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
      <nav class="twins-brand-location-links" aria-label="<?= htmlspecialchars($copy['areasTitle'], ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($cityLinks as [$cityLabel, $cityRoute]): ?>
          <a href="<?= htmlspecialchars($experience->route($cityRoute, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cityLabel, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </nav>
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
