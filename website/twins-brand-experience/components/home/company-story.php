<?php declare(strict_types=1); ?>
<section class="twins-brand-company-story" data-home-scene="company-story" aria-labelledby="twins-brand-company-title" data-home-reveal>
  <div class="twins-brand-company-copy">
    <span class="twins-brand-kicker">Local people. Branded trucks. Real work.</span>
    <h2 id="twins-brand-company-title">A Madison family company.</h2>
    <p>Twins Garage Doors is a Madison family company, with techs your neighbors already know by name.</p>
    <a class="twins-brand-cta" href="<?= htmlspecialchars($homeRoutes['team'], ENT_QUOTES, 'UTF-8') ?>">Meet the Team</a>
    <ul class="twins-brand-company-proof" aria-label="Why homeowners call Twins">
      <li><strong><span data-twins-live-count><?= htmlspecialchars($reviewSummary['displayCount'], ENT_QUOTES, 'UTF-8') ?></span>+</strong><span>Google reviews</span></li>
      <li><strong data-twins-live-rating><?= htmlspecialchars(number_format($reviewSummary['ratingValue'], 1), ENT_QUOTES, 'UTF-8') ?></strong><span>star average on Google</span></li>
      <li><strong>Flat price</strong><span>approved before any work starts</span></li>
    </ul>
  </div>
  <div class="twins-brand-company-media">
    <?php
    $logicalKey = 'crew-fleet';
    $sizes = '(max-width: 768px) 100vw, 58vw';
    $class = 'twins-brand-company-fleet';
    $loading = 'lazy';
    require dirname(__DIR__) . '/picture.php';
    ?>
    <span class="twins-brand-work-order" aria-hidden="true">TWINS / LOCAL SERVICE / <?= htmlspecialchars(strtoupper($market['label']), ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <nav class="twins-brand-diagnostic-rail" aria-labelledby="twins-brand-diagnostic-title">
    <span id="twins-brand-diagnostic-title">What is your door doing?</span>
    <div class="twins-brand-diagnostic-viewport" role="region" aria-labelledby="twins-brand-diagnostic-title" tabindex="0">
      <div class="twins-brand-diagnostic-track">
        <?php foreach ($homeProblemPaths as $problem): ?>
          <a href="<?= htmlspecialchars($homeRoutes[$problem['route']], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($problem['label'], ENT_QUOTES, 'UTF-8') ?><span aria-hidden="true">→</span></a>
        <?php endforeach; ?>
      </div>
    </div>
  </nav>
</section>

<aside class="twins-brand-home-offer" aria-labelledby="twins-brand-offer-title" data-home-reveal>
  <span aria-hidden="true">$0</span>
  <div>
    <small>Current repair offer</small>
    <h2 id="twins-brand-offer-title">$0 Service Call With Repair</h2>
  </div>
  <div>
    <a href="<?= htmlspecialchars($homeRoutes['offers'], ENT_QUOTES, 'UTF-8') ?>">View Offer Details</a>
    <a href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
  </div>
</aside>
