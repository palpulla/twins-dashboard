<?php
declare(strict_types=1);

require __DIR__ . '/nav-data.php';

// Three generic groups. Service Area is rendered explicitly after this loop
// (desktop) and after the drawer loop (mobile), so the other three panels keep
// today's exact markup: the owner complained about one menu, not four.
$nav = [
    'Repair Services' => $serviceItems,
    'Garage Doors' => $garageDoorItems,
    'Why Twins' => array_merge($aboutItems, $resourceItems),
];
$twinsNavGroupId = static fn(string $group): string =>
    'twins-brand-nav-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($group));
$twinsNavAreasId = 'twins-brand-nav-service-area';
$bookingMode = $booking['mode'] ?? null;
?>
<style id="twins-brand-critical-chrome">
body:has(.twins-brand-header) :where(
  #masthead,
  #colophon,
  header.elementor-location-header,
  [data-elementor-type="header"][data-elementor-id="7336"],
  footer.elementor-location-footer,
  #menuhopin.twx2-header
) { display: none !important; }
</style>
<header class="twins-brand-header" data-twins-header>
  <div class="twins-brand-header-shell">
    <div class="twins-brand-mainbar">
      <a class="twins-brand-logo" href="<?= htmlspecialchars($experience->route('home', $marketKey), ENT_QUOTES, 'UTF-8') ?>" aria-label="Twins Garage Doors home">
        <img src="<?= htmlspecialchars($experience->asset('logo'), ENT_QUOTES, 'UTF-8') ?>" width="711" height="325" alt="Twins Garage Doors">
      </a>
      <nav class="twins-brand-primary-nav" aria-label="Primary navigation">
        <?php foreach ($nav as $group => $items): ?>
          <?php $twinsNavPanelId = $twinsNavGroupId($group); ?>
          <div class="twins-brand-nav-group">
            <button type="button" class="twins-brand-nav-trigger" aria-expanded="false" aria-controls="<?= htmlspecialchars($twinsNavPanelId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></button>
            <div class="twins-brand-nav-panel<?= count($items) > 8 ? ' twins-brand-nav-panel--wide' : '' ?>" id="<?= htmlspecialchars($twinsNavPanelId, ENT_QUOTES, 'UTF-8') ?>">
              <?php foreach ($items as [$label, $routeKey]): ?>
                <a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($routeKey, $marketKey, $label), ENT_QUOTES, 'UTF-8') ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php
        // Service Area: a real two-level tree, metro -> town. The old whole-panel
        // "more than 8 items means two alphabetical columns" heuristic is what
        // produced the flat 51-row dump; this panel never uses it.
        //
        // <p id> + <ul aria-labelledby>, NOT <h2>/<h3>: a heading inside site
        // chrome injects "Madison Metro" into the heading outline of every page
        // on the site, where it competes with real page headings in H-key
        // navigation. aria-labelledby buys the whole win -- screen readers
        // announce "Madison Metro, list, 27 items" on entry -- at no outline
        // cost. On the hub page, where the groups genuinely are document
        // sections, they ARE real headings.
        //
        // No phone numbers anywhere in here. Per-metro phones are the market
        // disclosure's exclusive job: the disclosure answers "who serves me and
        // what is their number", this menu answers "where is my town".
        ?>
        <div class="twins-brand-nav-group twins-brand-nav-group--areas">
          <button type="button" class="twins-brand-nav-trigger" aria-expanded="false" aria-controls="<?= htmlspecialchars($twinsNavAreasId, ENT_QUOTES, 'UTF-8') ?>">Service Area</button>
          <div class="twins-brand-nav-panel twins-brand-nav-panel--areas<?= $twinsNavAreaMetros === [] ? ' twins-brand-nav-panel--cards-only' : '' ?>" id="<?= htmlspecialchars($twinsNavAreasId, ENT_QUOTES, 'UTF-8') ?>">
            <div class="twins-brand-area-columns">
              <?php foreach ($twinsNavAreaMetros as $twinsNavMetro): ?>
                <?php $twinsNavMetroId = 'twins-area-' . $twinsNavMetro['key']; ?>
                <div class="twins-brand-area-metro">
                  <p class="twins-brand-area-metro-title" id="<?= htmlspecialchars($twinsNavMetroId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($twinsNavMetro['label'], ENT_QUOTES, 'UTF-8') ?></p>
                  <ul class="twins-brand-area-towns<?= $twinsNavMetro['townCount'] > 10 ? ' twins-brand-area-towns--split' : '' ?>" aria-labelledby="<?= htmlspecialchars($twinsNavMetroId, ENT_QUOTES, 'UTF-8') ?>">
                    <?php foreach ($twinsNavMetro['towns'] as [$label, $routeKey]): ?>
                      <li><a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($routeKey, $marketKey, $label), ENT_QUOTES, 'UTF-8') ?></a></li>
                    <?php endforeach; ?>
                  </ul>
                  <a class="twins-brand-area-hub" href="<?= htmlspecialchars($experience->route('service-area', $marketKey) . $twinsNavMetro['hubAnchor'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($twinsNavMetro['hubLabel'], ENT_QUOTES, 'UTF-8') ?></a>
                </div>
              <?php endforeach; ?>
              <?php foreach ($twinsNavAreaMarkets as $twinsNavAreaMarket): ?>
                <?php $twinsNavMarketCardId = 'twins-area-market-' . $twinsNavAreaMarket['key']; ?>
                <div class="twins-brand-area-card">
                  <p class="twins-brand-area-metro-title" id="<?= htmlspecialchars($twinsNavMarketCardId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($twinsNavAreaMarket['label'], ENT_QUOTES, 'UTF-8') ?></p>
                  <p class="twins-brand-area-blurb"><?= htmlspecialchars($twinsNavAreaMarket['blurb'], ENT_QUOTES, 'UTF-8') ?></p>
                  <a class="twins-brand-area-jump" href="<?= htmlspecialchars($experience->route($twinsNavAreaMarket['key'], $marketKey), ENT_QUOTES, 'UTF-8') ?>">Visit the <?= htmlspecialchars($twinsNavAreaMarket['label'], ENT_QUOTES, 'UTF-8') ?> site</a>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($twinsNavAreaMetros === []): ?>
              <?php [$twinsNavAreaHubLabel, $twinsNavAreaHubRoute] = $twinsNavAreaHub; ?>
              <div class="twins-brand-area-foot">
                <a class="twins-brand-area-hub" href="<?= htmlspecialchars($experience->route($twinsNavAreaHubRoute, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($twinsNavAreaHubLabel, ENT_QUOTES, 'UTF-8') ?></a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </nav>
      <div class="twins-brand-header-actions">
        <a class="twins-brand-phone" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
        <?php if ($bookingMode === 'dialog'): ?>
          <button type="button" class="twins-brand-book-link" data-twins-booking-open>Book Garage Door Service</button>
        <?php else: ?>
          <a class="twins-brand-book-link" href="<?= htmlspecialchars($booking['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Book Garage Door Service</a>
        <?php endif; ?>
      </div>
      <div class="twins-brand-header-mobile-controls">
        <?php if ($bookingMode === 'dialog'): ?>
          <button type="button" class="twins-brand-mobile-book" data-twins-booking-open>Book</button>
        <?php else: ?>
          <a class="twins-brand-mobile-book" href="<?= htmlspecialchars($booking['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Book</a>
        <?php endif; ?>
        <button type="button" class="twins-brand-menu-trigger" aria-expanded="false" aria-controls="twins-brand-drawer">Menu</button>
      </div>
    </div>
  </div>
  <div class="twins-brand-market-strip">
    <details class="twins-brand-market-menu">
      <summary>Choose Your Service Area</summary>
      <div class="twins-brand-market-menu-panel">
        <?php // selectable(), never all(): a retired market must never be offered to a visitor. ?>
        <?php foreach ($experience->markets()->selectable($environment) as $availableKey => $availableMarket): ?>
          <?php if ($availableKey === 'main') continue; ?>
          <?php $availableRows = $availableMarket['metroLines'] ?? [['label' => $availableMarket['label'], 'phoneDisplay' => $availableMarket['phoneDisplay']]]; ?>
          <?php foreach ($availableRows as $availableRow): ?>
            <a href="<?= htmlspecialchars($experience->route($availableKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>">
              <strong><?= htmlspecialchars($availableRow['label'], ENT_QUOTES, 'UTF-8') ?></strong>
              <span><?= htmlspecialchars($availableRow['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </details>
    <span>Serving <?= htmlspecialchars($market['label'], ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <div id="twins-brand-drawer" class="twins-brand-drawer" hidden aria-hidden="true">
    <div class="twins-brand-drawer-panel" role="dialog" aria-modal="true" aria-label="Main menu">
      <button type="button" class="twins-brand-drawer-close" aria-label="Close menu">Close</button>
      <nav aria-label="Mobile navigation">
        <?php foreach ($nav as $group => $items): ?>
          <div class="twins-brand-drawer-group">
            <h2 class="twins-brand-drawer-group-title"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></h2>
            <?php foreach ($items as [$label, $routeKey]): ?>
              <a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($routeKey, $marketKey, $label), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <?php
        // Below 1200px the mega-menu does not exist, so this drawer IS the phone
        // and most-laptops experience. It used to emit 51 identical 44px rows
        // under one heading, about 2,250px of scroll for one group. One closed
        // <details> per metro collapses that to four rows. Every anchor is still
        // in the DOM on every page -- crawlers read the rendered DOM, so a closed
        // disclosure costs nothing in link equity while costing nothing in
        // scroll either. Ids are prefixed "drawer-" because the drawer and the
        // desktop panel both render on every page.
        ?>
        <div class="twins-brand-drawer-group twins-brand-drawer-group--areas">
          <h2 class="twins-brand-drawer-group-title">Service Area</h2>
          <?php foreach ($twinsNavAreaMetros as $twinsNavMetro): ?>
            <?php $twinsNavDrawerMetroId = 'drawer-metro-' . $twinsNavMetro['key']; ?>
            <details class="twins-brand-drawer-metro">
              <summary>
                <span class="twins-brand-drawer-metro-name" id="<?= htmlspecialchars($twinsNavDrawerMetroId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($twinsNavMetro['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="twins-brand-drawer-metro-count"><?= htmlspecialchars((string) $twinsNavMetro['townCount'], ENT_QUOTES, 'UTF-8') ?> <?= $twinsNavMetro['townCount'] === 1 ? 'town' : 'towns' ?></span>
              </summary>
              <div class="twins-brand-drawer-metro-body">
                <ul aria-labelledby="<?= htmlspecialchars($twinsNavDrawerMetroId, ENT_QUOTES, 'UTF-8') ?>">
                  <?php foreach ($twinsNavMetro['towns'] as [$label, $routeKey]): ?>
                    <li><a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($routeKey, $marketKey, $label), ENT_QUOTES, 'UTF-8') ?></a></li>
                  <?php endforeach; ?>
                </ul>
                <a class="twins-brand-drawer-hub" href="<?= htmlspecialchars($experience->route('service-area', $marketKey) . $twinsNavMetro['hubAnchor'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($twinsNavMetro['hubLabel'], ENT_QUOTES, 'UTF-8') ?></a>
              </div>
            </details>
          <?php endforeach; ?>
          <?php foreach ($twinsNavAreaMarkets as $twinsNavAreaMarket): ?>
            <a class="twins-brand-drawer-jump" href="<?= htmlspecialchars($experience->route($twinsNavAreaMarket['key'], $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($twinsNavAreaMarket['label'], ENT_QUOTES, 'UTF-8') ?></a>
          <?php endforeach; ?>
          <?php if ($twinsNavAreaMetros === []): ?>
            <?php [$twinsNavAreaHubLabel, $twinsNavAreaHubRoute] = $twinsNavAreaHub; ?>
            <a class="twins-brand-drawer-hub" href="<?= htmlspecialchars($experience->route($twinsNavAreaHubRoute, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($twinsNavAreaHubLabel, ENT_QUOTES, 'UTF-8') ?></a>
          <?php endif; ?>
        </div>
      </nav>
      <?php if ($bookingMode === 'dialog'): ?>
        <button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Garage Door Service</button>
      <?php else: ?>
        <a class="twins-brand-cta twins-brand-cta--book" href="<?= htmlspecialchars($booking['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Book Garage Door Service</a>
      <?php endif; ?>
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call Now</a>
    </div>
  </div>
  <?php if ($bookingMode === 'dialog') echo $booking['experienceHtml']; ?>
</header>
