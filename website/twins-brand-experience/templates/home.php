<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-home">
  <section class="twins-brand-hero" data-section="brand-hero">
    <div class="twins-brand-hero-copy">
      <span class="twins-brand-offer-chip">$0 Service Call</span>
      <span class="twins-brand-kicker">Local garage door service across our communities</span>
      <h1>Garage Door <em>Repair</em> & Installation, Done Right <em>Today</em>.</h1>
      <p>Fast local service, straight answers, and upfront options from the Twins crew.</p>
      <div class="twins-brand-hero-actions">
        <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
        <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
      </div>
      <p class="twins-brand-hero-proof"><span class="twins-brand-stars" aria-hidden="true">★★★★★</span> 4.9 on Google from 675+ reviews · Licensed and insured</p>
    </div>
    <div class="twins-brand-hero-art" aria-label="The Twins Garage Doors team">
      <img class="twins-brand-truck twins-brand-truck--hero" src="<?= htmlspecialchars($experience->asset('truck-webp'), ENT_QUOTES, 'UTF-8') ?>" width="1398" height="821" alt="Twins Garage Doors branded service truck">
      <img class="twins-brand-twin twins-brand-twin--left" src="<?= htmlspecialchars($experience->asset('twin-left'), ENT_QUOTES, 'UTF-8') ?>" width="196" height="534" alt="Twins Garage Doors technician character">
      <img class="twins-brand-twin twins-brand-twin--right" src="<?= htmlspecialchars($experience->asset('twin-right'), ENT_QUOTES, 'UTF-8') ?>" width="297" height="538" alt="Twins Garage Doors technician character">
    </div>
  </section>

  <section class="twins-brand-mobile-proof" aria-label="Local Twins service team">
    <img class="twins-brand-truck twins-brand-truck--mobile-proof" src="<?= htmlspecialchars($experience->asset('truck-webp'), ENT_QUOTES, 'UTF-8') ?>" width="1398" height="821" alt="" aria-hidden="true">
    <p>Local crews. Branded trucks. Straight answers.</p>
  </section>

  <section class="twins-brand-trust-ribbon" data-section="trust-ribbon" aria-label="Service promises">
    <span>Same-day appointments</span>
    <span>Upfront pricing</span>
    <span>Most repairs done in one visit</span>
  </section>

  <section class="twins-brand-service-pathways" data-section="service-pathways" aria-labelledby="twins-brand-services-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">How we can help</span>
      <h2 id="twins-brand-services-title">Start with the service you need</h2>
      <p>Choose a pathway for repair, a new garage door, or opener help.</p>
    </div>
    <div class="twins-brand-card-grid">
      <article class="twins-brand-service-card">
        <h3>Garage Door Repair</h3>
        <p>Explore help for springs, cables, rollers, and doors that will not move correctly.</p>
        <a href="<?= htmlspecialchars($experience->route('repair', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Explore repair service</a>
      </article>
      <article class="twins-brand-service-card">
        <h3>Garage Door Installation</h3>
        <p>Compare a replacement door with guidance from the Twins team.</p>
        <a href="<?= htmlspecialchars($experience->route('installation', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Explore installation</a>
      </article>
      <article class="twins-brand-service-card">
        <h3>Garage Door Openers</h3>
        <p>Find help for opener troubleshooting, repair, and replacement options.</p>
        <a href="<?= htmlspecialchars($experience->route('opener-repair', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Explore opener service</a>
      </article>
    </div>
  </section>

  <section class="twins-brand-review-proof" data-section="review-slider">
    <?php require dirname(__DIR__) . '/components/review-slider.php'; ?>
  </section>

  <section class="twins-brand-team-story" data-section="team-story" aria-labelledby="twins-brand-team-story-title">
    <div class="twins-brand-team-story-copy">
      <span class="twins-brand-kicker">The crew behind the work</span>
      <h2 id="twins-brand-team-story-title">Meet the people behind Twins</h2>
      <p>Our real local crew brings clear communication, careful work, and respect for the homes we visit.</p>
      <a href="<?= htmlspecialchars($experience->route('team', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Meet Our Team</a>
    </div>
    <div class="twins-brand-team-story-images">
      <?php
      $logicalKey = 'crew-fleet';
      $sizes = '(max-width: 900px) 100vw, 58vw';
      $class = 'twins-brand-team-story-crew';
      $loading = 'lazy';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
      <?php
      $logicalKey = 'technician-at-work';
      $sizes = '(max-width: 900px) 100vw, 38vw';
      $class = 'twins-brand-team-story-technician';
      $loading = 'lazy';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
    </div>
  </section>

  <section class="twins-brand-door-builder" data-section="door-builder" aria-labelledby="twins-brand-door-builder-title">
    <div class="twins-brand-door-builder-art">
      <?php
      $logicalKey = 'door-builder-before-after';
      $sizes = '(max-width: 900px) 100vw, 50vw';
      $class = 'twins-brand-door-builder-preview';
      $loading = 'lazy';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
    </div>
    <div class="twins-brand-door-builder-copy">
      <span class="twins-brand-kicker">See the possibilities</span>
      <h2 id="twins-brand-door-builder-title">Picture a door that fits your home</h2>
      <p>Explore styles and finishes before you speak with the Twins team.</p>
      <a class="twins-brand-cta" href="<?= htmlspecialchars($experience->route('door-builder', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Design Your Door</a>
    </div>
  </section>

  <section class="twins-brand-market-selector" data-section="market-selector" aria-labelledby="twins-brand-markets-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">Choose your service area</span>
      <h2 id="twins-brand-markets-title">Find your local Twins team</h2>
    </div>
    <div class="twins-brand-market-grid">
      <?php foreach ($experience->markets()->all($environment) as $availableMarketKey => $availableMarket): ?>
        <?php if ($availableMarketKey === 'main') continue; ?>
        <a class="twins-brand-market-card<?= $availableMarket['preview'] === true ? ' twins-brand-market-card--preview' : '' ?>" href="<?= htmlspecialchars($experience->route($availableMarketKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>">
          <strong><?= htmlspecialchars($availableMarket['label'], ENT_QUOTES, 'UTF-8') ?></strong>
          <?php if ($availableMarket['preview'] === true): ?><span>Private staging preview</span><?php else: ?><span>View local service</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php require dirname(__DIR__) . '/components/service-areas-panel.php'; ?>

  <section class="twins-brand-careers" data-section="careers" aria-labelledby="twins-brand-careers-title">
    <div class="twins-brand-careers-image">
      <?php
      $logicalKey = 'technician-at-work';
      $sizes = '(max-width: 900px) 100vw, 48vw';
      $class = 'twins-brand-careers-photo';
      $loading = 'lazy';
      require dirname(__DIR__) . '/components/picture.php';
      ?>
    </div>
    <div class="twins-brand-careers-copy">
      <span class="twins-brand-kicker">Careers at Twins</span>
      <h2 id="twins-brand-careers-title">Do work you are proud to put your name on.</h2>
      <?php if ($environment === 'staging'): ?>
        <p>Explore ways to contribute, what matters to the crew, and the private application preview.</p>
      <?php else: ?>
        <p>Explore ways to contribute, what matters to the crew, and the application experience.</p>
      <?php endif; ?>
      <a href="<?= htmlspecialchars($experience->route('careers', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Explore Careers</a>
    </div>
  </section>

  <section class="twins-brand-final-cta" data-section="final-cta" aria-labelledby="twins-brand-final-cta-title">
    <span class="twins-brand-kicker">Ready when you are</span>
    <h2 id="twins-brand-final-cta-title">Let’s get your garage door moving.</h2>
    <div class="twins-brand-final-actions">
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </section>
</main>
