<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
if (!isset($pageContent) || !is_array($pageContent)) {
    throw new DomainException('Service page content is unavailable.');
}
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-service-page">
  <section class="twins-brand-service-hero" aria-labelledby="twins-brand-service-title">
    <div>
      <span class="twins-brand-kicker">Garage door service guide</span>
      <h1 id="twins-brand-service-title"><?= htmlspecialchars($pageContent['h1'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p>Start with a clear answer, review the safety guidance, and use the contact details for your selected service area.</p>
    </div>
    <div class="twins-brand-service-hero-actions">
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
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

  <section class="twins-brand-service-options" aria-labelledby="twins-brand-service-options-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">Options and tradeoffs</span>
      <h2 id="twins-brand-service-options-title">Review the next-step paths</h2>
    </div>
    <div class="twins-brand-service-option-grid">
      <?php foreach ($pageContent['options'] as $option): ?>
        <article>
          <h3><?= htmlspecialchars($option['option'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p><?= htmlspecialchars($option['tradeoff'], ENT_QUOTES, 'UTF-8') ?></p>
        </article>
      <?php endforeach; ?>
    </div>
    <nav class="twins-brand-service-links" aria-label="Related garage door pages">
      <?php foreach ($pageContent['links'] as $link): ?>
        <a href="<?= htmlspecialchars($experience->route($link['route'], $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></a>
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
      <a class="twins-brand-service-phone" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($market['phoneDisplay'], ENT_QUOTES, 'UTF-8') ?></a>
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
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($market['phoneHref'], ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </section>
</main>
