<?php declare(strict_types=1); require_once dirname(__DIR__) . '/door-art.php'; ?>
<section class="twins-brand-membership-scene" data-home-scene="membership" aria-labelledby="twins-brand-membership-title" data-home-reveal>
  <div>
    <span class="twins-brand-kicker">Plan ahead</span>
    <h2 id="twins-brand-membership-title">TwinShield. The no-surprises plan.</h2>
    <p>Wisconsin is hard on garage doors. Springs snap on the coldest morning of the year, and openers quit the week you need them most. TwinShield is our maintenance membership: a trained tech inspects, lubricates, tightens, and tests your whole system on a schedule, so small problems stay small.</p>
    <?php
    // /maintenance-plans/ is the plan page. There is no other one to point at.
    //
    // There were two until 2026-08-27: /protection-plans/ carried a thin,
    // paraphrased `plans` list and this page carried the full TwinShield block
    // from components/twinshield-plan.php. The owner made /maintenance-plans/
    // canonical (the URL people search) and retired the duplicate, so
    // $homeRoutes no longer has a 'protection-plans' key to swap back to and
    // the retired path 302s here from every market. This CTA and the nav item
    // labelled "Protection Plan" both resolve through the 'maintenance-plans'
    // route key, which is the only plan route that exists.
    ?>
    <a class="twins-brand-cta" href="<?= htmlspecialchars($homeRoutes['maintenance-plans'], ENT_QUOTES, 'UTF-8') ?>">See Plan Details</a>
  </div>
  <aside>
    <?= twins_brand_door_art('roller', 'twins-brand-membership-roller') ?>
    <span>New door now. Payments monthly.</span>
    <p>A new insulated door is a real purchase, and you should not have to wait for the perfect month to make it. We offer financing through GoodLeap, with monthly payments available on new doors. Ask about it when you book, or when your tech quotes the job.</p>
    <a href="<?= htmlspecialchars($homeRoutes['financing'], ENT_QUOTES, 'UTF-8') ?>">View Financing</a>
  </aside>
</section>

<section class="twins-brand-closing-scene" data-home-scene="location-close" data-home-closing aria-labelledby="twins-brand-closing-title" data-home-reveal data-home-motion>
  <img class="twins-brand-closing-backdrop" src="<?= htmlspecialchars($experience->asset('truck-webp-880'), ENT_QUOTES, 'UTF-8') ?>" width="880" height="517" alt="" aria-hidden="true" loading="lazy" decoding="async">
  <div class="twins-brand-closing-faq">
    <span class="twins-brand-kicker">Cost questions first</span>
    <h2 id="twins-brand-closing-title">Quick answers.</h2>
    <?php foreach ($homeFaqs as $faq): ?>
      <details><summary><?= htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8') ?></summary><p><?= htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8') ?></p></details>
    <?php endforeach; ?>
    <a href="<?= htmlspecialchars($homeRoutes['faqs'], ENT_QUOTES, 'UTF-8') ?>">Read All FAQs</a>
    <?php if (isset($homeRoutes['cost-guide'])): ?>
      <a href="<?= htmlspecialchars($homeRoutes['cost-guide'], ENT_QUOTES, 'UTF-8') ?>">See Real Madison Prices</a>
    <?php endif; ?>
  </div>
  <div class="twins-brand-closing-area">
    <span class="twins-brand-kicker"><?= htmlspecialchars($market['label'], ENT_QUOTES, 'UTF-8') ?></span>
    <h2>Garage door service near you.</h2>
    <?php // Real <h3>s here: on a page, a metro name IS a document section. In site
          // chrome it is not, which is why the header menu uses <p id> + aria-labelledby. ?>
    <?php foreach ($homeAreaGroups as $homeAreaGroup): ?>
      <?php $homeAreaGroupId = 'twins-brand-closing-area-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($homeAreaGroup['label'])); ?>
      <h3 class="twins-brand-closing-area-metro" id="<?= htmlspecialchars($homeAreaGroupId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($homeAreaGroup['label'], ENT_QUOTES, 'UTF-8') ?></h3>
      <ul aria-labelledby="<?= htmlspecialchars($homeAreaGroupId, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($homeAreaGroup['towns'] as [$label, $routeKey]): ?>
          <li><a href="<?= htmlspecialchars($experience->route($routeKey, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
        <?php endforeach; ?>
      </ul>
      <a class="twins-brand-closing-area-hub" href="<?= htmlspecialchars($homeAreaGroup['hubHref'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($homeAreaGroup['hubLabel'], ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
    <details class="twins-brand-home-market-choice">
      <summary>Choose Your Service Area</summary>
      <div>
        <?php foreach ($experience->markets()->selectable($environment) as $availableKey => $availableMarket): ?>
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
