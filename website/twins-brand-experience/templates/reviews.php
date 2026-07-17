<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
$context['classification'] = 'reviews-brand';
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-reviews-page">
  <section class="twins-brand-page-hero" aria-labelledby="twins-brand-reviews-page-title">
    <span class="twins-brand-kicker">Verified customer reviews</span>
    <h1 id="twins-brand-reviews-page-title">Straight from Twins customers</h1>
    <p>Real Google reviews from Twins customers across our service areas.</p>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>

  <section class="twins-brand-reviews-collection" aria-label="Customer review collection">
    <?php require dirname(__DIR__) . '/components/review-slider.php'; ?>
  </section>

  <nav class="twins-brand-reviews-next" aria-label="Explore Twins">
    <a href="<?= htmlspecialchars($experience->route('services', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Explore Services</a>
    <a href="<?= htmlspecialchars($experience->route('team', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Meet Our Team</a>
    <a href="<?= htmlspecialchars($experience->route('contact', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Contact Twins</a>
  </nav>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-reviews-quote-title">
    <h2 id="twins-brand-reviews-quote-title">Ready for help with your garage door?</h2>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>
</main>
