<?php declare(strict_types=1); ?>
<section class="twins-brand-service-journey" data-home-scene="service-journey" data-home-service-journey data-home-motion aria-labelledby="twins-brand-journey-title" data-home-reveal>
  <header>
    <span class="twins-brand-kicker">What to expect when you call</span>
    <h2 id="twins-brand-journey-title">Here is how it goes.</h2>
  </header>
  <div class="twins-brand-journey-route" aria-hidden="true"></div>
  <ol>
    <li class="twins-brand-journey-step">
      <span>01</span><div><h3>Book it</h3><p>Book online in about a minute, or call us. You pick an arrival window that works for your day.</p></div>
    </li>
    <li class="twins-brand-journey-step">
      <?php
      $logicalKey = 'technician-at-work';
      $sizes = '(max-width: 768px) 100vw, 44vw';
      $class = 'twins-brand-journey-photo';
      $loading = 'lazy';
      require dirname(__DIR__) . '/picture.php';
      ?>
      <span>02</span><div><h3>Meet your tech</h3><p>You get a text with your tech's name when he is on the way. He finds the problem, shows you what he found, and quotes a flat price. Nothing starts until you approve it.</p></div>
    </li>
    <li class="twins-brand-journey-step">
      <span>03</span><div><h3>Done right</h3><p>We fix it, run the door through full cycles to test it, and clean up after ourselves. "Done Right, or We Make It Right." If something about our work is not right, we come back and fix it.</p></div>
    </li>
  </ol>
  <img class="twins-brand-journey-truck" src="<?= htmlspecialchars($experience->asset('truck-webp-320'), ENT_QUOTES, 'UTF-8') ?>" width="320" height="188" alt="" aria-hidden="true" loading="lazy" decoding="async">
</section>
