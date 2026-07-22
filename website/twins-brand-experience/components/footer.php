<?php
declare(strict_types=1);

require __DIR__ . '/nav-data.php';

$footerGroups = [
    'Services' => $serviceItems,
    'Garage Doors' => $garageDoorItems,
    'Service Areas' => $serviceAreasCompact,
    'Resources' => $resourceItems,
    'About' => $aboutItems,
];

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
$footerAddress = isset($context['metroAddress']) && is_string($context['metroAddress']) && trim($context['metroAddress']) !== ''
    ? trim($context['metroAddress'])
    : (isset($market['address']) && is_string($market['address']) ? $market['address'] : '');
$footerPath = isset($context['path']) && is_string($context['path']) ? $context['path'] : '';
$isLocationFooter = preg_match('~^/(?:wi|il)/location/[a-z][a-z0-9-]{0,39}/$~D', $footerPath) === 1;
$footerQuoteLabel = $isLocationFooter ? 'Get a Free Quote' : 'Request a Quote';
$mobileCallLabel = $isLocationFooter ? 'Call Now' : 'Call Twins';
?>
<?php require_once __DIR__ . '/door-art.php'; ?>
<footer class="twins-brand-footer">
  <div class="twins-brand-footer-intro">
    <?= twins_brand_door_art('door', 'twins-brand-footer-door', 'footer') ?>
    <a class="twins-brand-footer-logo" href="<?= htmlspecialchars($experience->route('home', $marketKey), ENT_QUOTES, 'UTF-8') ?>" aria-label="Twins Garage Doors home">
      <img src="<?= htmlspecialchars($experience->asset('logo'), ENT_QUOTES, 'UTF-8') ?>" width="711" height="325" alt="Twins Garage Doors">
    </a>
    <p>Local garage door service from a team that treats your home like our own.</p>
    <a class="twins-brand-phone" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
    <address class="twins-brand-footer-nap">
      <span>Twins Garage Doors</span>
      <?php if ($footerAddress !== ''): ?><span><?= htmlspecialchars($footerAddress, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
      <a href="mailto:contact@twinsgaragedoors.com">contact@twinsgaragedoors.com</a>
      <span>Licensed and insured</span>
    </address>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($footerQuoteLabel, ENT_QUOTES, 'UTF-8') ?></a>
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
  <p class="twins-brand-footer-legal">&copy; Twins Garage Doors. All rights reserved.</p>
</footer>
<div class="twins-brand-mobile-actions" aria-label="Quick actions">
  <a href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mobileCallLabel, ENT_QUOTES, 'UTF-8') ?></a>
  <a href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($footerQuoteLabel, ENT_QUOTES, 'UTF-8') ?></a>
</div>
