<?php
declare(strict_types=1);

$footerServiceAreas = [];
foreach ($experience->markets()->all($environment) as $key => $availableMarket) {
    if ($key === 'main') continue;
    $footerServiceAreas[] = [$availableMarket['label'], $key];
}

$footerServiceItems = [
    ['All Services', 'services'],
    ['Garage Door Repair', 'repair'],
    ['Garage Door Installation', 'installation'],
    ['Spring Repair', 'spring-repair'],
    ['Opener Repair', 'opener-repair'],
    ['Emergency Service', 'emergency-service'],
];
if ($marketKey === 'il-preview') {
    $footerServiceItems = array_values(array_filter(
        $footerServiceItems,
        static fn(array $item): bool => $item[1] !== 'spring-repair'
    ));
}

$footerGroups = [
    'Services' => $footerServiceItems,
    'Garage Doors' => [
        ['Garage Door Collections', 'garage-doors'],
        ['Classic Collection', 'classic-collection'],
        ['Modern Steel', 'modern-steel'],
        ['Gallery Steel', 'gallery-steel'],
        ['Design Your Door', 'door-builder'],
    ],
    'Service Areas' => $footerServiceAreas,
    'Resources' => [
        ['Reviews', 'reviews'],
        ['Wisconsin Garage Door Cost Guide', 'cost-guide'],
        ['Financing', 'financing'],
        ['Offers', 'offers'],
        ['Frequently Asked Questions', 'faqs'],
        ['Blog', 'blog'],
    ],
    'About' => [
        ['About Twins', 'about'],
        ['Our Team', 'team'],
        ['Careers', 'careers'],
        ['Contact Us', 'contact'],
    ],
];

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
?>
<footer class="twins-brand-footer">
  <div class="twins-brand-footer-intro">
    <a class="twins-brand-footer-logo" href="<?= htmlspecialchars($experience->route('home', $marketKey), ENT_QUOTES, 'UTF-8') ?>" aria-label="Twins Garage Doors home">
      <img src="<?= htmlspecialchars($experience->asset('logo'), ENT_QUOTES, 'UTF-8') ?>" width="711" height="325" alt="Twins Garage Doors">
    </a>
    <p>Local garage door service from a team that treats your home like our own.</p>
    <a class="twins-brand-phone" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
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
  <a href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
  <a href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
</div>
