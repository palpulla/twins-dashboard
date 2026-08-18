<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
if (!isset($pageContent) || !is_array($pageContent)) {
    throw new DomainException('Service page content is unavailable.');
}
?>
<div id="twins-overhaul-main" class="twins-brand-page twins-brand-service-page">
  <?php require_once dirname(__DIR__) . '/components/service-hero-art.php'; ?>
  <?php $serviceHeroArt = twins_brand_service_hero_art($path, $experience); ?>
  <section class="twins-brand-service-hero<?= $serviceHeroArt !== '' ? ' twins-brand-service-hero--with-art' : '' ?>" aria-labelledby="twins-brand-service-title">
    <div>
      <span class="twins-brand-kicker">Garage door service guide</span>
      <h1 id="twins-brand-service-title"><?= htmlspecialchars($pageContent['h1'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p>Straight answers, safe work, and upfront options from the local Twins crew.</p>
    </div>
    <?= $serviceHeroArt ?>
    <div class="twins-brand-service-hero-actions">
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </section>

  <section class="twins-brand-direct-answer" aria-labelledby="twins-brand-direct-answer-title">
    <div>
      <span class="twins-brand-kicker">Direct answer</span>
      <h2 id="twins-brand-direct-answer-title">What to know first</h2>
      <p><?= htmlspecialchars($pageContent['directAnswer'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <aside class="twins-brand-service-safety" aria-labelledby="twins-brand-service-safety-title">
      <h2 id="twins-brand-service-safety-title">Safety first</h2>
      <p><?= htmlspecialchars($pageContent['safety'], ENT_QUOTES, 'UTF-8') ?></p>
    </aside>
  </section>

  <section class="twins-brand-service-needs" aria-labelledby="twins-brand-service-needs-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">When this path fits</span>
      <h2 id="twins-brand-service-needs-title">Start with the service concern</h2>
    </div>
    <ul class="twins-brand-service-grid">
      <?php foreach ($pageContent['needs'] as $need): ?>
        <li><?= htmlspecialchars($need, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="twins-brand-service-process" aria-labelledby="twins-brand-service-process-title">
    <div>
      <span class="twins-brand-kicker">A clear process</span>
      <h2 id="twins-brand-service-process-title">What happens next</h2>
      <ol class="twins-brand-service-steps">
        <?php foreach ($pageContent['process'] as $step): ?>
          <li><?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <aside class="twins-brand-service-prepare" aria-labelledby="twins-brand-service-prepare-title">
      <h2 id="twins-brand-service-prepare-title">What to prepare</h2>
      <ul>
        <?php foreach ($pageContent['prepare'] as $item): ?>
          <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </aside>
  </section>

  <?php $isMembership = $path === '/protection-plans/'; ?>
  <section class="twins-brand-service-options<?= $isMembership ? ' twins-brand-membership' : '' ?>" aria-labelledby="twins-brand-service-options-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker"><?= $isMembership ? 'Membership tiers' : 'Options and tradeoffs' ?></span>
      <h2 id="twins-brand-service-options-title"><?= $isMembership ? 'Choose your TwinShield plan' : 'Review the next-step paths' ?></h2>
    </div>
    <?php if ($isMembership): ?>
      <div class="twins-brand-membership-grid">
        <?php foreach ($pageContent['options'] as $planIndex => $plan): ?>
          <?php
            $planParts = explode(' — ', $plan['tradeoff'], 2);
            $planPrice = $planParts[0];
            $planBenefits = $planParts[1] ?? '';
            $planRecommended = $planIndex === 1;
            $planTier = str_replace('TwinShield ', '', explode(' - ', $plan['option'], 2)[0]);
          ?>
          <article class="twins-brand-membership-card<?= $planRecommended ? ' twins-brand-membership-card--featured' : '' ?>">
            <?php if ($planRecommended): ?><span class="twins-brand-membership-badge">Recommended</span><?php endif; ?>
            <h3><?= htmlspecialchars($plan['option'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="twins-brand-membership-price"><?= htmlspecialchars($planPrice, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="twins-brand-membership-benefits"><?= htmlspecialchars($planBenefits, ENT_QUOTES, 'UTF-8') ?></p>
            <a class="twins-brand-cta twins-brand-cta--quote twins-brand-membership-cta" href="<?= htmlspecialchars($experience->route('contact', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Ask about <?= htmlspecialchars($planTier, ENT_QUOTES, 'UTF-8') ?></a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="twins-brand-service-option-grid">
        <?php foreach ($pageContent['options'] as $option): ?>
          <article>
            <h3><?= htmlspecialchars($option['option'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p><?= htmlspecialchars($option['tradeoff'], ENT_QUOTES, 'UTF-8') ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <nav class="twins-brand-service-links" aria-label="Related garage door pages">
      <?php foreach ($pageContent['links'] as $link): ?>
        <a href="<?= htmlspecialchars($experience->route($link['route'], $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($link['route'], $marketKey, $link['label']), ENT_QUOTES, 'UTF-8') ?></a>
      <?php endforeach; ?>
    </nav>
  </section>

  <section class="twins-brand-service-area" aria-labelledby="twins-brand-service-area-title">
    <div>
      <span class="twins-brand-kicker">Your selected service area</span>
      <h2 id="twins-brand-service-area-title"><?= htmlspecialchars($market['label'], ENT_QUOTES, 'UTF-8') ?></h2>
      <p>This page uses the contact details and service routes for the market selected in the shared Twins experience.</p>
    </div>
    <div class="twins-brand-service-area-actions">
      <a class="twins-brand-service-phone" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
      <a href="<?= htmlspecialchars($experience->route('services', $marketKey), ENT_QUOTES, 'UTF-8') ?>">View garage door services</a>
    </div>
  </section>

  <section class="twins-brand-faq" aria-labelledby="twins-brand-service-faq-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">Frequently asked questions</span>
      <h2 id="twins-brand-service-faq-title">Answers before you choose a next step</h2>
    </div>
    <div class="twins-brand-faq-list">
      <?php foreach ($pageContent['faqs'] as $faq): ?>
        <details>
          <summary><?= htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8') ?></summary>
          <p><?= htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8') ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-service-final-title">
    <span class="twins-brand-kicker">Ready for a project-specific answer?</span>
    <h2 id="twins-brand-service-final-title">Use the contact path for your service area.</h2>
    <div class="twins-brand-final-actions">
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </section>
</div>
