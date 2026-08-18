<?php
declare(strict_types=1);

require __DIR__ . '/nav-data.php';

$footerGroups = [
    'Priority Services' => array_slice($serviceItems, 0, 4),
    'Service Area' => $serviceAreasCompact,
    'Help & Company' => [
        $aboutItems[0],
        $aboutItems[1],
        $aboutItems[3],
        $resourceItems[0],
    ],
];

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
$footerAddress = isset($context['metroAddress']) && is_string($context['metroAddress']) && trim($context['metroAddress']) !== ''
    ? trim($context['metroAddress'])
    : (isset($market['address']) && is_string($market['address']) ? $market['address'] : '');
$footerPath = isset($context['path']) && is_string($context['path']) ? $context['path'] : '';
$isLocationFooter = isset($context['classification']) && $context['classification'] === 'location';
if (!isset($context['classification'])) {
    $isLocationFooter = preg_match('~^/(?:wi|il|ky)/location/[a-z][a-z0-9-]{0,39}/$~D', $footerPath) === 1;
}
$footerQuoteLabel = $isLocationFooter ? 'Get a Free Quote' : 'Request a Quote';
$footerBookingMode = $booking['mode'] ?? null;
$isHomeFooter = ($context['classification'] ?? '') === 'home-brand';
?>
<?php if (!$isHomeFooter): ?>
<section class="twins-brand-footer-cta" aria-labelledby="twins-brand-footer-cta-title">
  <h2 id="twins-brand-footer-cta-title">Need garage door help today?</h2>
  <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($footerQuoteLabel, ENT_QUOTES, 'UTF-8') ?></a>
</section>
<?php endif; ?>
<footer class="twins-brand-footer">
  <div class="twins-brand-footer-inner">
    <div class="twins-brand-footer-grid">
      <div class="twins-brand-footer-brand">
        <a class="twins-brand-footer-logo" href="<?= htmlspecialchars($experience->route('home', $marketKey), ENT_QUOTES, 'UTF-8') ?>" aria-label="Twins Garage Doors home">
          <img src="<?= htmlspecialchars($experience->asset('logo'), ENT_QUOTES, 'UTF-8') ?>" width="711" height="325" alt="Twins Garage Doors">
        </a>
        <p>Local crews, upfront pricing, and the exact cost before any work starts.</p>
        <a class="twins-brand-phone" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
        <address class="twins-brand-footer-nap">
          <span>Twins Garage Doors</span>
          <?php if ($footerAddress !== ''): ?><span><?= htmlspecialchars($footerAddress, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
          <a href="mailto:contact@twinsgaragedoors.com">contact@twinsgaragedoors.com</a>
          <span>Licensed and insured</span>
        </address>
      </div>
      <nav class="twins-brand-footer-nav" aria-label="Footer navigation">
        <?php foreach ($footerGroups as $group => $items): ?>
          <div class="twins-brand-footer-group">
            <h2><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></h2>
            <?php foreach ($items as [$label, $routeKey]): ?>
              <a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($routeKey, $marketKey, $label), ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </nav>
    </div>
    <p class="twins-brand-footer-legal">&copy; Twins Garage Doors. All rights reserved.</p>
  </div>
</footer>
<div class="twins-brand-mobile-actions" aria-label="Quick actions">
  <a href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call Now</a>
  <?php if ($footerBookingMode === 'dialog'): ?>
    <button type="button" data-twins-booking-open>Book Online</button>
  <?php else: ?>
    <a href="<?= htmlspecialchars($booking['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Book Online</a>
  <?php endif; ?>
</div>
