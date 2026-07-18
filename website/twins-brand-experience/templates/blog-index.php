<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
if (!isset($blogIndex) || !is_array($blogIndex)) {
    throw new DomainException('Blog index view is unavailable.');
}

$blogPosts = $blogIndex['posts'];
$blogPage = $blogIndex['page'];
$blogTotalPages = $blogIndex['totalPages'];
$blogBasePath = $blogIndex['basePath'];
$blogPagePath = static function (int $target) use ($blogBasePath): string {
    return $target <= 1 ? $blogBasePath : $blogBasePath . 'page/' . $target . '/';
};
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-blog-page">
  <header class="twins-brand-blog-hero" aria-labelledby="twins-brand-blog-title">
    <span class="twins-brand-kicker">Garage door resources</span>
    <h1 id="twins-brand-blog-title">Garage door answers from the Twins crew</h1>
    <p>Straight answers on springs, openers, panels, and new doors, written by the crew that repairs and installs them every day. Use what applies to your door, and leave spring or cable work to trained professionals.</p>
    <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
  </header>

  <?php if ($blogPosts === []): ?>
    <section class="twins-brand-blog-empty" aria-label="No guides available">
      <p>New guides from the crew are on the way. Check back soon.</p>
    </section>
  <?php else: ?>
    <section class="twins-brand-blog-grid-section" aria-label="Latest garage door guides">
      <div class="twins-brand-blog-grid">
        <?php foreach ($blogPosts as $blogPost): ?>
          <article class="twins-brand-blog-card">
            <?php if ($blogPost['thumbnail'] !== ''): ?>
              <a class="twins-brand-blog-card-media" href="<?= htmlspecialchars($blogPost['path'], ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-hidden="true">
                <img
                  src="<?= htmlspecialchars($blogPost['thumbnail'], ENT_QUOTES, 'UTF-8') ?>"
                  width="1200"
                  height="675"
                  alt=""
                  loading="lazy"
                  decoding="async"
                >
              </a>
            <?php endif; ?>
            <div class="twins-brand-blog-card-body">
              <?php if ($blogPost['date'] !== ''): ?>
                <span class="twins-brand-blog-card-date"><?= htmlspecialchars($blogPost['date'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
              <h2><a href="<?= htmlspecialchars($blogPost['path'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($blogPost['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
              <?php if ($blogPost['excerpt'] !== ''): ?>
                <p><?= htmlspecialchars($blogPost['excerpt'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
              <span class="twins-brand-blog-card-more" aria-hidden="true">Read the guide</span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($blogTotalPages > 1): ?>
      <nav class="twins-brand-blog-pagination" aria-label="Blog pages">
        <?php if ($blogPage > 1): ?>
          <a class="twins-brand-blog-page-link" href="<?= htmlspecialchars($blogPagePath($blogPage - 1), ENT_QUOTES, 'UTF-8') ?>">Newer guides</a>
        <?php endif; ?>
        <span class="twins-brand-blog-page-status">Page <?= htmlspecialchars((string) $blogPage, ENT_QUOTES, 'UTF-8') ?> of <?= htmlspecialchars((string) $blogTotalPages, ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($blogPage < $blogTotalPages): ?>
          <a class="twins-brand-blog-page-link" href="<?= htmlspecialchars($blogPagePath($blogPage + 1), ENT_QUOTES, 'UTF-8') ?>">Older guides</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

  <section class="twins-brand-final-cta" aria-labelledby="twins-brand-blog-final-title">
    <span class="twins-brand-kicker"><?= htmlspecialchars($market['label'], ENT_QUOTES, 'UTF-8') ?></span>
    <h2 id="twins-brand-blog-final-title">Rather have the crew take a look?</h2>
    <div class="twins-brand-final-actions">
      <a class="twins-brand-cta twins-brand-cta--quote" href="<?= htmlspecialchars($quote['href'], ENT_QUOTES, 'UTF-8') ?>">Request a Quote</a>
    </div>
  </section>
</main>
