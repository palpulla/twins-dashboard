<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}

$editorialKinds = [
    'location' => [
        'kicker' => 'Service-area guide',
        'answer' => 'Twins Garage Doors repairs and installs garage doors, springs, openers, and cables in this area. Call the local number for help today, or request a quote and the crew will follow up with straight answers and upfront options.',
    ],
    'trust' => [
        'kicker' => 'About Twins',
        'answer' => 'Twins Garage Doors is a local, licensed, and insured garage door company. The details below explain how we work. For anything specific to your home or property, call or request a quote and a real person will help.',
    ],
    'article' => [
        'kicker' => 'Garage door resource',
        'answer' => 'This guide comes from the Twins team. Use what applies to your door, and leave spring or cable work to trained professionals. For help with your specific situation, call Twins or request a quote.',
    ],
];
if (!isset($editorialKinds[$kind])) {
    throw new DomainException('Editorial kind is unavailable.');
}
$editorial = $editorialKinds[$kind];
$title = isset($context['title']) && is_string($context['title']) && trim($context['title']) !== ''
    ? trim($context['title'])
    : 'Twins Garage Doors';
if (
    $kind === 'location'
    && str_word_count($title) <= 3
    && stripos($title, 'garage') === false
) {
    $title = 'Garage Door Service in ' . $title;
}
$locationServiceLinks = $kind === 'location'
    ? [
        ['Garage Door Repair', 'repair'],
        ['Garage Door Installation', 'installation'],
        ['Spring Repair', 'spring-repair'],
        ['Opener Repair', 'opener-repair'],
        ['Emergency Service', 'emergency-service'],
        ['Customer Reviews', 'reviews'],
    ]
    : [];
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-editorial-page">
  <header class="twins-brand-editorial-hero" aria-labelledby="twins-brand-editorial-title">
    <span class="twins-brand-kicker"><?= htmlspecialchars($editorial['kicker'], ENT_QUOTES, 'UTF-8') ?></span>
    <h1 id="twins-brand-editorial-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
  </header>

  <section class="twins-brand-editorial-answer" aria-labelledby="twins-brand-editorial-answer-title">
    <div>
      <span class="twins-brand-kicker">Direct answer</span>
      <h2 id="twins-brand-editorial-answer-title">What to know first</h2>
      <p><?= htmlspecialchars($editorial['answer'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
  </section>

  <?php if ($locationServiceLinks !== []): ?>
    <section class="twins-brand-editorial-services" aria-labelledby="twins-brand-location-services-title">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">What we do here</span>
        <h2 id="twins-brand-location-services-title">Garage door help in this area</h2>
      </div>
      <nav class="twins-brand-location-links" aria-label="Local garage door services">
        <?php foreach ($locationServiceLinks as [$locationLabel, $locationRoute]): ?>
          <a href="<?= htmlspecialchars($experience->route($locationRoute, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($locationRoute, $marketKey, $locationLabel), ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </nav>
    </section>
  <?php endif; ?>

  <section class="twins-brand-editorial-body">
    <article class="twins-brand-editorial-content">
      <?= $content ?>
    </article>
  </section>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-editorial-final-title">
    <span class="twins-brand-kicker"><?= htmlspecialchars($market['label'], ENT_QUOTES, 'UTF-8') ?></span>
    <h2 id="twins-brand-editorial-final-title">Need a project-specific answer?</h2>
    <div class="twins-brand-final-actions">
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </section>
</main>
