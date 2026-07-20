<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}

require_once dirname(__DIR__) . '/components/door-art.php';

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
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-contact-page">
  <section class="twins-brand-page-hero" aria-labelledby="twins-brand-contact-title">
    <span class="twins-brand-kicker">Contact Twins</span>
    <h1 id="twins-brand-contact-title">Request a Quote</h1>
    <p>Tell us what is going on and we will call you right back, or call the number for your service area.</p>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins at <?= htmlspecialchars($market['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?></a>
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
      <?php foreach ($experience->markets()->all($environment) as $availableMarketKey => $availableMarket): ?>
        <?php if ($availableMarketKey === 'main') continue; ?>
        <article>
          <h3><?= htmlspecialchars($availableMarket['label'], ENT_QUOTES, 'UTF-8') ?></h3>
          <a href="<?= htmlspecialchars($availableMarket['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($availableMarket['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?>
          </a>
          <?php if ($availableMarket['preview'] === true): ?><small>Private staging preview</small><?php endif; ?>
          <a href="<?= htmlspecialchars($experience->route($availableMarketKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>">View service area</a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-contact-call-title">
    <h2 id="twins-brand-contact-call-title">Prefer to talk?</h2>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>
</main>
