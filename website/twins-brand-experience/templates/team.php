<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-team-page">
  <section class="twins-brand-page-hero" aria-labelledby="twins-brand-team-title">
    <span class="twins-brand-kicker">Our Team</span>
    <h1 id="twins-brand-team-title">The people behind Twins Garage Doors</h1>
    <p>Meet the real crew and the values that guide how we work with customers and one another.</p>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>

  <section class="twins-brand-team-crew" aria-labelledby="twins-brand-crew-title">
    <div class="twins-brand-team-crew-image">
      <?php
      $logicalKey = 'crew-fleet';
      $sizes = '(max-width: 900px) 100vw, 62vw';
      $class = 'twins-brand-team-crew-photo';
      $loading = 'eager';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
    </div>
    <div class="twins-brand-team-crew-copy">
      <span class="twins-brand-kicker">Local people. Branded fleet.</span>
      <h2 id="twins-brand-crew-title">A crew focused on doing the work right</h2>
      <p>Clear communication, careful work, and follow-through shape the experience we want every customer to have.</p>
    </div>
  </section>

  <section class="twins-brand-team-portraits" aria-labelledby="twins-brand-people-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">People at Twins</span>
      <h2 id="twins-brand-people-title">Meet a familiar face</h2>
    </div>
    <article class="twins-brand-person-card">
      <?php
      $logicalKey = 'tal-portrait';
      $sizes = '(max-width: 700px) 100vw, 36vw';
      $class = 'twins-brand-tal-portrait';
      $loading = 'lazy';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
      <h3>Tal Joseph</h3>
    </article>
    <div class="twins-brand-team-work-photo">
      <?php
      $logicalKey = 'technician-at-work';
      $sizes = '(max-width: 700px) 100vw, 54vw';
      $class = 'twins-brand-technician-photo';
      $loading = 'lazy';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
    </div>
  </section>

  <section class="twins-brand-team-values" aria-labelledby="twins-brand-team-values-title">
    <span class="twins-brand-kicker">What matters here</span>
    <h2 id="twins-brand-team-values-title">Own the outcome. Treat people right. Keep learning.</h2>
    <p>We value clear communication, respect for the customer and crew, attention to detail, and a willingness to stay coachable.</p>
  </section>

  <section class="twins-brand-team-story" data-section="company-story" aria-labelledby="twins-brand-company-story-title">
    <span class="twins-brand-kicker">The Twins approach</span>
    <h2 id="twins-brand-company-story-title">Straight answers and careful work</h2>
    <p>Twins is built around a practical promise: listen, explain the available options clearly, respect the customer’s home, and follow through on the work.</p>
  </section>

  <section class="twins-brand-team-careers" aria-labelledby="twins-brand-team-careers-title">
    <span class="twins-brand-kicker">Join the crew</span>
    <h2 id="twins-brand-team-careers-title">See where your skills could contribute</h2>
    <a href="<?= htmlspecialchars($experience->route('careers', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Explore Careers</a>
  </section>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-team-quote-title">
    <h2 id="twins-brand-team-quote-title">Ready to work with Twins?</h2>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </section>
</main>
