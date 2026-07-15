<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-contact-page">
  <section class="twins-brand-page-hero" aria-labelledby="twins-brand-contact-title">
    <span class="twins-brand-kicker">Contact Twins</span>
    <h1 id="twins-brand-contact-title">Request a Quote</h1>
    <p>Tell the Twins team what is happening with your garage door, or call the number for your service area.</p>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins at <?= htmlspecialchars($market['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?></a>
  </section>

  <section class="twins-brand-contact-markets" aria-labelledby="twins-brand-contact-markets-title">
    <span class="twins-brand-kicker">Service areas</span>
    <h2 id="twins-brand-contact-markets-title">Choose your local Twins team</h2>
    <div class="twins-brand-contact-market-grid">
      <?php foreach ($experience->markets()->all($environment) as $availableMarketKey => $availableMarket): ?>
        <?php if ($availableMarketKey === 'main') continue; ?>
        <article>
          <h3><?= htmlspecialchars($availableMarket['label'], ENT_QUOTES, 'UTF-8') ?></h3>
          <?php if ($availableMarket['preview'] === true): ?>
            <p>Private staging preview</p>
          <?php else: ?>
            <a href="<?= htmlspecialchars($availableMarket['phoneHref'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($availableMarket['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?></a>
          <?php endif; ?>
          <a href="<?= htmlspecialchars($experience->route($availableMarketKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>">View service area</a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="twins-brand-contact-quote" aria-labelledby="twins-brand-contact-quote-title">
    <span class="twins-brand-kicker">A few helpful details</span>
    <h2 id="twins-brand-contact-quote-title">Request a Quote from Twins</h2>
    <?= $experience->quoteAdapter()->renderExperience($context) ?>
  </section>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-contact-call-title">
    <h2 id="twins-brand-contact-call-title">Prefer to talk?</h2>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>
</main>
