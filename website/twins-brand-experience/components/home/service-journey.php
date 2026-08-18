<?php declare(strict_types=1); ?>
<section class="twins-brand-service-journey" data-home-scene="service-journey" data-home-service-journey data-home-motion aria-labelledby="twins-brand-journey-title" data-home-reveal>
  <header>
    <span class="twins-brand-kicker">What to expect when you call</span>
    <h2 id="twins-brand-journey-title">Three clear stops. No runaround.</h2>
  </header>
  <div class="twins-brand-journey-route" aria-hidden="true"></div>
  <ol>
    <li class="twins-brand-journey-step">
      <span>01</span><div><h3>Call or book</h3><p>Tell us what the door is doing and choose a time that works for you.</p></div>
    </li>
    <li class="twins-brand-journey-step">
      <?php
      $logicalKey = 'technician-at-work';
      $sizes = '(max-width: 768px) 100vw, 44vw';
      $class = 'twins-brand-journey-photo';
      $loading = 'lazy';
      require dirname(__DIR__) . '/picture.php';
      ?>
      <span>02</span><div><h3>Inspect and explain</h3><p>A local technician checks the system and explains what was found.</p></div>
    </li>
    <li class="twins-brand-journey-step">
      <span>03</span><div><h3>Choose the next step</h3><p>Review the exact price and available options before approving the work.</p></div>
    </li>
  </ol>
  <img class="twins-brand-journey-truck" src="<?= htmlspecialchars($experience->asset('truck-webp-320'), ENT_QUOTES, 'UTF-8') ?>" width="320" height="188" alt="" aria-hidden="true" loading="lazy" decoding="async">
</section>
