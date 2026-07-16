<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}

$editorialKinds = [
    'location' => [
        'kicker' => 'Service-area guide',
        'answer' => 'Review the verified location information below, then use the selected regional phone or quote path for questions about a specific garage door project.',
    ],
    'trust' => [
        'kicker' => 'About Twins',
        'answer' => 'Review the verified published information below, then use the selected regional contact details when your question depends on a specific property or project.',
    ],
    'article' => [
        'kicker' => 'Garage door resource',
        'answer' => 'Start with the published resource below, keep the details that apply to your question, and use the selected regional contact path for project-specific guidance.',
    ],
];
if (!isset($editorialKinds[$kind])) {
    throw new DomainException('Editorial kind is unavailable.');
}
$editorial = $editorialKinds[$kind];
$title = isset($context['title']) && is_string($context['title']) && trim($context['title']) !== ''
    ? trim($context['title'])
    : 'Twins Garage Doors';
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-editorial-page">
  <header class="twins-brand-editorial-hero" aria-labelledby="twins-brand-editorial-title">
    <span class="twins-brand-kicker"><?= htmlspecialchars($editorial['kicker'], ENT_QUOTES, 'UTF-8') ?></span>
    <h1 id="twins-brand-editorial-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
  </header>

  <section class="twins-brand-editorial-answer" aria-labelledby="twins-brand-editorial-answer-title">
    <div>
      <span class="twins-brand-kicker">Direct answer</span>
      <h2 id="twins-brand-editorial-answer-title">How to use this page</h2>
      <p><?= htmlspecialchars($editorial['answer'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <a class="twins-brand-cta twins-brand-cta--call" href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>">Call <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a>
  </section>

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
