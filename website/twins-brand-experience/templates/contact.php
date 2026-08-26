<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}

require_once dirname(__DIR__) . '/components/door-art.php';
require dirname(__DIR__) . '/components/nav-data.php';

// THE CHOOSER LISTS TEAMS, NOT STATES.
//
// It used to loop the market registry, so a Milwaukee homeowner reading
// "Choose your local Twins team" was offered WISCONSIN and handed the Madison
// number. Twins has three staffed metros, each with its own address and its own
// line: Madison (608) 420-2377, Milwaukee/Wauwatosa (414) 800-9271 and
// Rockford (815) 800-2025. Two of the three live inside one market, so a
// market-level list can never show all three.
//
// The metro is already the unit this site navigates by: the header's service-
// area menu lists metro rows, the service-area index is sectioned by metro, and
// every city page takes its NAP from its metro. This block is the same tree,
// read for its phones.
//
// Numbers come only from config/markets.php 'metroLines'; labels come only from
// $twinsNavMetroTree. Nothing is written here.
$contactMetroLabels = [];
$contactMetroHubs = [];
foreach ($twinsNavMetroTree as $contactMetroKey => $contactMetroNode) {
    $contactMetroLabels[$contactMetroKey] = $contactMetroNode['label'];
    // City routes are market-scoped, so a metro's own hub page is only
    // reachable from inside its market. Elsewhere the card falls back to that
    // market's front door, which is what the header market menu already does.
    $contactMetroHubs[$contactMetroKey] = ($contactMetroNode['market'] === $marketKey && isset($contactMetroNode['towns'][0][1]))
        ? $contactMetroNode['towns'][0][1]
        : $contactMetroNode['market'];
}

$contactTeams = [];
foreach ($experience->markets()->selectable($environment) as $contactMarketKey => $contactMarket) {
    if ($contactMarketKey === 'main') {
        continue;
    }
    $contactLines = $contactMarket['metroLines'] ?? [[
        'key' => $contactMarketKey,
        'label' => $contactMarket['label'],
        'phoneDisplay' => $contactMarket['phoneDisplay'],
        'phoneHref' => $contactMarket['phoneHref'],
    ]];
    foreach ($contactLines as $contactLine) {
        $contactLineKey = (string) ($contactLine['key'] ?? $contactMarketKey);
        $contactTeams[] = [
            'label' => $contactMetroLabels[$contactLineKey] ?? $contactLine['label'],
            'phoneDisplay' => $contactLine['phoneDisplay'],
            'phoneHref' => $contactLine['phoneHref'],
            'routeKey' => $contactMetroHubs[$contactLineKey] ?? $contactMarketKey,
        ];
    }
}
if ($contactTeams === []) {
    throw new DomainException('Contact market chooser has no team to offer.');
}

$bookingMode = $booking['mode'] ?? null;
if ($environment === 'staging') {
    if ($bookingMode !== 'dialog') {
        throw new DomainException('Staging booking must stay an inert dialog.');
    }
} elseif ($environment === 'production') {
    if ($bookingMode !== 'external') {
        throw new DomainException('Production booking action is unavailable.');
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
}
?>
<div id="twins-overhaul-main" class="twins-brand-page twins-brand-contact-page">
  <section class="twins-brand-page-hero" aria-labelledby="twins-brand-contact-title">
    <span class="twins-brand-kicker">Contact Twins</span>
    <h1 id="twins-brand-contact-title">Request a Quote</h1>
    <p>Tell us what is going on and we will call you right back, or call the number for your service area.</p>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins at <?= htmlspecialchars($market['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?></a>
    <?= twins_brand_hero_art('crew', $experience, 'contact') ?>
  </section>

  <section class="twins-brand-contact-quote" aria-label="Request a call back">
    <?= $experience->quoteAdapter()->renderExperience($context) ?>
  </section>

  <section class="twins-brand-contact-booking" aria-labelledby="twins-brand-contact-booking-title">
    <div>
      <span class="twins-brand-kicker">Schedule online</span>
      <h2 id="twins-brand-contact-booking-title">Prefer to pick your own time?</h2>
      <p>Book your service appointment online and choose the arrival window that works for your day. You will get a confirmation right away.</p>
      <?php if ($bookingMode === 'dialog'): ?>
        <button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Online</button>
      <?php elseif ($bookingMode === 'external'): ?>
        <a class="twins-brand-cta twins-brand-cta--book" href="<?= htmlspecialchars($booking['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Book Online</a>
      <?php endif; ?>
    </div>
    <?= twins_brand_door_art('door-open', '', 'contact-booking') ?>
  </section>

  <section class="twins-brand-contact-markets" aria-labelledby="twins-brand-contact-markets-title">
    <span class="twins-brand-kicker">Service areas</span>
    <h2 id="twins-brand-contact-markets-title">Choose your local Twins team</h2>
    <div class="twins-brand-contact-market-grid">
      <?php foreach ($contactTeams as $contactTeam): ?>
        <article>
          <h3><?= htmlspecialchars($contactTeam['label'], ENT_QUOTES, 'UTF-8') ?></h3>
          <a href="<?= htmlspecialchars($contactTeam['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($contactTeam['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?>
          </a>
          <a href="<?= htmlspecialchars($experience->route($contactTeam['routeKey'], $marketKey), ENT_QUOTES, 'UTF-8') ?>">View service area</a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-contact-call-title">
    <h2 id="twins-brand-contact-call-title">Prefer to talk?</h2>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>
</div>
