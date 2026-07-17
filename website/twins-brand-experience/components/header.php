<?php
declare(strict_types=1);

require __DIR__ . '/nav-data.php';

$nav = [
    'Services' => $serviceItems,
    'Garage Doors' => $garageDoorItems,
    'Service Areas' => $serviceAreas,
    'Resources' => $resourceItems,
    'About' => $aboutItems,
];

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
$bookingMode = $booking['mode'] ?? null;
if ($environment === 'staging') {
    if ($bookingMode !== 'dialog') {
        throw new DomainException('Booking action mode does not match staging.');
    }
    if (!isset($booking['experienceHtml']) || !is_string($booking['experienceHtml'])) {
        throw new DomainException('Booking dialog is unavailable.');
    }
} elseif ($environment === 'production') {
    if ($bookingMode !== 'external') {
        throw new DomainException('Booking action mode does not match production.');
    }
    if (
        !isset($booking['href']) ||
        !is_string($booking['href']) ||
        $booking['href'] === '' ||
        ($booking['target'] ?? null) !== '_blank' ||
        ($booking['rel'] ?? null) !== 'noopener noreferrer'
    ) {
        throw new DomainException('External booking action is unavailable.');
    }
} else {
    throw new DomainException('Booking environment is invalid.');
}
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
  <div class="twins-brand-utility">
    <details class="twins-brand-market-menu">
      <summary>Choose your service area</summary>
      <div class="twins-brand-market-menu-panel">
        <?php foreach ($experience->markets()->all($environment) as $availableKey => $availableMarket): ?>
          <?php if ($availableKey === 'main') continue; ?>
          <a href="<?= htmlspecialchars($experience->route($availableKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>">
            <strong><?= htmlspecialchars($availableMarket['label'], ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars($availableMarket['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($availableMarket['preview'] === true): ?><small>Private staging preview</small><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </details>
    <a class="twins-brand-phone" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>
    </a>
  </div>
  <div class="twins-brand-fascia">
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
    <?php if ($bookingMode === 'dialog'): ?>
      <button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Online</button>
    <?php elseif ($bookingMode === 'external'): ?>
      <a class="twins-brand-cta twins-brand-cta--book" href="<?= htmlspecialchars($booking['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Book Online</a>
    <?php endif; ?>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    <button type="button" class="twins-brand-menu-trigger" aria-expanded="false" aria-controls="twins-brand-drawer">Menu</button>
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
        <button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Online</button>
      <?php elseif ($bookingMode === 'external'): ?>
        <a class="twins-brand-cta twins-brand-cta--book" href="<?= htmlspecialchars($booking['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Book Online</a>
      <?php endif; ?>
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </div>
  <?php if ($bookingMode === 'dialog') echo $booking['experienceHtml']; ?>
</header>
