<?php declare(strict_types=1); ?>
<section class="twins-brand-service-showcase" data-home-scene="service-showcase" aria-labelledby="twins-brand-services-title" data-home-reveal>
  <header>
    <span class="twins-brand-kicker">Services we offer</span>
    <h2 id="twins-brand-services-title">The right help for the door in front of you.</h2>
  </header>
  <div class="twins-brand-service-feature" data-home-service-showcase data-twins-service-tabs>
    <div class="twins-brand-service-tabs" data-service-tablist hidden>
      <?php foreach ($homeServices as $index => $service): ?>
        <button type="button" id="twins-service-tab-<?= htmlspecialchars($service['id'], ENT_QUOTES, 'UTF-8') ?>" class="twins-brand-service-tab" data-service-tab="<?= $index ?>">
          <span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span><?= htmlspecialchars($service['label'], ENT_QUOTES, 'UTF-8') ?>
        </button>
      <?php endforeach; ?>
    </div>
    <div class="twins-brand-service-stage">
      <?php foreach ($homeServices as $index => $service): ?>
        <article id="twins-service-panel-<?= htmlspecialchars($service['id'], ENT_QUOTES, 'UTF-8') ?>" class="twins-brand-service-panel" data-service-panel="<?= $index ?>">
          <?php
          $logicalKey = $service['picture'];
          $pictureFallbackLogicalKey = 'crew-fleet';
          $sizes = '(max-width: 768px) 100vw, 52vw';
          $class = 'twins-brand-service-image';
          $loading = 'lazy';
          require dirname(__DIR__) . '/picture.php';
          ?>
          <div class="twins-brand-service-copy">
            <span><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?> / 04</span>
            <h3><?= htmlspecialchars($service['heading'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars($service['copy'], ENT_QUOTES, 'UTF-8') ?></p>
            <a href="<?= htmlspecialchars($homeServiceRoutes[$service['route']], ENT_QUOTES, 'UTF-8') ?>">Explore <?= htmlspecialchars($service['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <a href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <footer class="twins-brand-service-controls" data-service-controls hidden>
      <button type="button" data-service-prev aria-label="Previous service">←</button>
      <span data-service-status>1 of 4</span>
      <button type="button" data-service-next aria-label="Next service">→</button>
    </footer>
  </div>
</section>
