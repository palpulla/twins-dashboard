<?php declare(strict_types=1); ?>
<section class="twins-brand-membership-scene" data-home-scene="membership" aria-labelledby="twins-brand-membership-title" data-home-reveal>
  <div>
    <span class="twins-brand-kicker">Plan ahead</span>
    <h2 id="twins-brand-membership-title">TwinShield Protection Plans</h2>
    <p>See the current plan intervals, what a visit covers, and how to join.</p>
    <?php
    // /protection-plans/ does not exist on production and would 404 at cutover.
    // /maintenance-plans/ is live there and is the closest real page, so point
    // at it until the TwinShield page is written. Swap back to
    // $homeRoutes['protection-plans'] once that page exists.
    ?>
    <a class="twins-brand-cta" href="<?= htmlspecialchars($homeRoutes['maintenance-plans'], ENT_QUOTES, 'UTF-8') ?>">See Plan Details</a>
  </div>
  <aside>
    <span>Financing options</span>
    <p>Review the current financing pathway without promises about approval, rates, or savings.</p>
    <a href="<?= htmlspecialchars($homeRoutes['financing'], ENT_QUOTES, 'UTF-8') ?>">View Financing</a>
  </aside>
</section>

<section class="twins-brand-closing-scene" data-home-scene="location-close" data-home-closing aria-labelledby="twins-brand-closing-title" data-home-reveal data-home-motion>
  <img class="twins-brand-closing-backdrop" src="<?= htmlspecialchars($experience->asset('truck-webp-880'), ENT_QUOTES, 'UTF-8') ?>" width="880" height="517" alt="" aria-hidden="true" loading="lazy" decoding="async">
  <div class="twins-brand-closing-faq">
    <span class="twins-brand-kicker">Helpful answers</span>
    <h2 id="twins-brand-closing-title">Know what happens next.</h2>
    <?php foreach ($homeFaqs as $faq): ?>
      <details><summary><?= htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8') ?></summary><p><?= htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8') ?></p></details>
    <?php endforeach; ?>
    <a href="<?= htmlspecialchars($homeRoutes['faqs'], ENT_QUOTES, 'UTF-8') ?>">Read All FAQs</a>
  </div>
  <div class="twins-brand-closing-area">
    <span class="twins-brand-kicker"><?= htmlspecialchars($market['label'], ENT_QUOTES, 'UTF-8') ?></span>
    <h2>Garage door service near you.</h2>
    <?php if ($homeAreaLinks !== []): ?>
      <ul>
        <?php foreach ($homeAreaLinks as [$label, $routeKey]): ?>
          <li><a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <details class="twins-brand-home-market-choice">
      <summary>Choose Your Service Area</summary>
      <div>
        <?php foreach ($experience->markets()->all($environment) as $availableKey => $availableMarket): ?>
          <?php if ($availableKey === 'main') continue; ?>
          <?php $availableRows = $availableMarket['metroLines'] ?? [['label' => $availableMarket['label']]]; ?>
          <?php foreach ($availableRows as $availableRow): ?>
            <a href="<?= htmlspecialchars($experience->route($availableKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($availableRow['label'], ENT_QUOTES, 'UTF-8') ?></a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </details>
  </div>
  <div class="twins-brand-closing-actions">
    <h2>Let’s get your garage door back to normal.</h2>
    <?php if (($booking['mode'] ?? null) === 'dialog'): ?>
      <button type="button" class="twins-brand-cta twins-brand-cta--book" data-twins-booking-open>Book Online</button>
    <?php else: ?>
      <a class="twins-brand-cta twins-brand-cta--book" href="<?= htmlspecialchars($booking['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Book Online</a>
    <?php endif; ?>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call Now</a>
  </div>
</section>
