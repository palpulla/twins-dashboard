<?php
declare(strict_types=1);

require __DIR__ . '/nav-data.php';

$nav = [
    'Repair Services' => $serviceItems,
    'Garage Doors' => $garageDoorItems,
    'Why Twins' => array_merge($aboutItems, $resourceItems),
    'Service Area' => $serviceAreas,
];
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
          <div class="twins-brand-nav-group">
            <button type="button" class="twins-brand-nav-trigger" aria-expanded="false"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></button>
            <div class="twins-brand-nav-panel<?= count($items) > 8 ? ' twins-brand-nav-panel--wide' : '' ?>">
              <?php foreach ($items as [$label, $routeKey]): ?>
                <a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($routeKey, $marketKey, $label), ENT_QUOTES, 'UTF-8') ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
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
              <?php if ($availableMarket['preview'] === true): ?><small>Private staging preview</small><?php endif; ?>
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
            <strong><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php foreach ($items as [$label, $routeKey]): ?>
              <a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($routeKey, $marketKey, $label), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
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
