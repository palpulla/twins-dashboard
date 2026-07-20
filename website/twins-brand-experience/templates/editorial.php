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
$isArticle = $kind === 'article';
$title = isset($context['title']) && is_string($context['title']) && trim($context['title']) !== ''
    ? trim($context['title'])
    : 'Twins Garage Doors';
$locationCity = $title;
$locationCityIsClean = $kind === 'location'
    && str_word_count($title) <= 3
    && stripos($title, 'garage') === false;
if ($locationCityIsClean) {
    $title = 'Garage Door Service in ' . $locationCity;
    // City-aware answer: only names the city when the title is a clean city
    // name, so pages with an odd title fall back to the generic wording.
    $editorial['answer'] = 'Twins Garage Doors repairs and installs garage doors, springs, openers, and cables in ' . $locationCity . ' and the nearby area. Call the local number for help today, or request a quote and the crew will follow up with straight answers and upfront options.';
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
$articleServiceLinks = $isArticle
    ? [
        ['Garage Door Repair', 'repair'],
        ['Garage Door Installation', 'installation'],
        ['Spring Repair', 'spring-repair'],
        ['Opener Repair', 'opener-repair'],
        ['Customer Reviews', 'reviews'],
    ]
    : [];
$articleHeroImage = $isArticle && isset($articleHero) && is_string($articleHero) ? $articleHero : '';
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-editorial-page<?= $isArticle ? ' twins-brand-article-page' : '' ?>">
  <?php if ($articleHeroImage !== ''): ?>
    <figure class="twins-brand-article-hero-media">
      <img src="<?= htmlspecialchars($articleHeroImage, ENT_QUOTES, 'UTF-8') ?>" width="1200" height="675" alt="" decoding="async" fetchpriority="high">
    </figure>
  <?php endif; ?>
  <header class="twins-brand-editorial-hero<?= $isArticle ? ' twins-brand-article-hero' : '' ?>" aria-labelledby="twins-brand-editorial-title">
    <span class="twins-brand-kicker"><?= htmlspecialchars($editorial['kicker'], ENT_QUOTES, 'UTF-8') ?></span>
    <h1 id="twins-brand-editorial-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
  </header>

  <?php if (!$isArticle): ?>
    <section class="twins-brand-editorial-answer" aria-labelledby="twins-brand-editorial-answer-title">
      <div>
        <span class="twins-brand-kicker">Direct answer</span>
        <h2 id="twins-brand-editorial-answer-title">What to know first</h2>
        <p><?= htmlspecialchars($editorial['answer'], ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
    </section>
  <?php endif; ?>

  <?php if ($kind === 'location' && isset($market['address']) && is_string($market['address']) && $market['address'] !== ''):
    $napSummaryFile = dirname(__DIR__) . '/config/review-summary.php';
    $napSummary = is_file($napSummaryFile) ? require $napSummaryFile : [];
    $napRating = isset($napSummary['ratingValue']) ? $napSummary['ratingValue'] : null;
    $napCount = isset($napSummary['displayCount']) ? (string) $napSummary['displayCount'] : '';
  ?>
    <section class="twins-brand-editorial-answer" aria-labelledby="twins-brand-nap-title">
      <div>
        <span class="twins-brand-kicker">Where we are</span>
        <h2 id="twins-brand-nap-title">Twins Garage Doors</h2>
        <p><?= htmlspecialchars($market['address'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($napRating !== null): ?>
          <p><span class="twins-brand-stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span> <?= htmlspecialchars((string) $napRating, ENT_QUOTES, 'UTF-8') ?> on Google<?= $napCount !== '' ? ' &middot; ' . htmlspecialchars($napCount, ENT_QUOTES, 'UTF-8') . ' reviews' : '' ?> &middot; Licensed and insured</p>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

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

  <?php if ($kind === 'location') require dirname(__DIR__) . '/components/service-areas-panel.php'; ?>

  <?php if (isset($context['faqPage']['faqs']) && is_array($context['faqPage']['faqs'])): ?>
    <section class="twins-brand-faq" aria-labelledby="twins-brand-faq-page-title">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Frequently asked questions</span>
        <h2 id="twins-brand-faq-page-title">Garage door questions, answered straight</h2>
      </div>
      <div class="twins-brand-faq-list">
        <?php foreach ($context['faqPage']['faqs'] as $faq): ?>
          <details>
            <summary><?= htmlspecialchars((string) $faq['question'], ENT_QUOTES, 'UTF-8') ?></summary>
            <p><?= htmlspecialchars((string) $faq['answer'], ENT_QUOTES, 'UTF-8') ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </section>
  <?php elseif ($kind !== 'location'): ?>
    <?php // Location pages ship the clean brand experience only (city answer, NAP,
          // services, service-area panel, map, Service schema). The preserved
          // legacy per-city essays carry typos, unverified claims, and duplicate
          // blocks (see production-cutover/page-signoff.md), so they are not
          // rendered; trust and article pages keep their preserved body. ?>
    <section class="twins-brand-editorial-body<?= $isArticle ? ' twins-brand-article-body' : '' ?>">
      <article class="twins-brand-editorial-content<?= $isArticle ? ' twins-brand-article-content' : '' ?>">
        <?= $content ?>
      </article>
    </section>
  <?php endif; ?>

  <?php if ($articleServiceLinks !== []): ?>
    <section class="twins-brand-editorial-services twins-brand-article-services" aria-labelledby="twins-brand-article-services-title">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Need hands-on help?</span>
        <h2 id="twins-brand-article-services-title">Services related to this guide</h2>
      </div>
      <nav class="twins-brand-location-links" aria-label="Related garage door services">
        <?php foreach ($articleServiceLinks as [$articleLabel, $articleRoute]): ?>
          <a href="<?= htmlspecialchars($experience->route($articleRoute, $marketKey), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($experience->contextualRouteLabel($articleRoute, $marketKey, $articleLabel), ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </nav>
    </section>
  <?php endif; ?>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-editorial-final-title">
    <?php require_once dirname(__DIR__) . '/components/door-art.php'; ?>
    <?= twins_brand_door_art('door-open', 'twins-brand-cta-art', 'editorial-final') ?>
    <span class="twins-brand-kicker"><?= htmlspecialchars($market['label'], ENT_QUOTES, 'UTF-8') ?></span>
    <h2 id="twins-brand-editorial-final-title">Need a project-specific answer?</h2>
    <div class="twins-brand-final-actions">
      <?php if ($isArticle): ?>
        <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
      <?php else: ?>
        <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call Twins</a>
      <?php endif; ?>
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </section>
</main>
