<?php
declare(strict_types=1);

$collection = $experience->reviewCollection();
$reviewsRoute = $experience->route('reviews', $marketKey);
$isReviewsPage = ($context['classification'] ?? '') === 'reviews-brand';

if (($collection['status'] ?? null) === 'unavailable'):
?>
<section class="twins-brand-reviews" aria-labelledby="twins-brand-reviews-title">
  <div class="twins-brand-section-heading">
    <span class="twins-brand-kicker">Verified customer stories</span>
    <h2 id="twins-brand-reviews-title">What our customers say</h2>
    <p role="status">Reviews are temporarily unavailable.</p>
  </div>
  <a class="twins-brand-text-link" href="<?= htmlspecialchars($reviewsRoute, ENT_QUOTES, 'UTF-8') ?>">Read all reviews</a>
</section>
<?php
    return;
endif;

if (!isset($collection['records']) || !is_array($collection['records'])) {
    throw new DomainException('Verified review records are unavailable.');
}

$records = $collection['records'];
if (!$isReviewsPage) {
    $records = array_slice($records, 0, 9);
}

$externalReviewsUrl = null;
if ($environment === 'production' && ($collection['allowExternalSourceAction'] ?? null) === true) {
    if (!isset($collection['businessReviewsUrl']) || !is_string($collection['businessReviewsUrl']) || $collection['businessReviewsUrl'] === '') {
        throw new DomainException('Verified external review action is unavailable.');
    }
    $externalReviewsUrl = $collection['businessReviewsUrl'];
}

$renderReviewCard = static function (array $review, int $index, bool $listMode): void {
    $words = preg_split('/\s+/u', trim($review['text']));
    if ($words === false) {
        throw new DomainException('Verified review text is unavailable.');
    }
    $isLong = count($words) > 42;
    $excerpt = implode(' ', array_slice($words, 0, 42));
    $class = 'twins-brand-review-card' . ($listMode ? ' twins-brand-review-card--list' : '');
?>
  <article class="<?= $class ?>" data-review-stable-id="<?= htmlspecialchars($review['stableId'], ENT_QUOTES, 'UTF-8') ?>" data-review-index="<?= $index ?>">
    <div class="twins-brand-review-stars" aria-label="<?= (int) $review['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $review['rating']) ?></div>
    <?php if ($isLong): ?>
      <p class="twins-brand-review-excerpt"><?= htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8') ?>…</p>
      <details class="twins-brand-review-details">
        <summary>Read full review</summary>
        <blockquote><?= nl2br(htmlspecialchars($review['text'], ENT_QUOTES, 'UTF-8')) ?></blockquote>
      </details>
    <?php else: ?>
      <blockquote><?= nl2br(htmlspecialchars($review['text'], ENT_QUOTES, 'UTF-8')) ?></blockquote>
    <?php endif; ?>
    <footer>
      <strong><?= htmlspecialchars($review['author'], ENT_QUOTES, 'UTF-8') ?></strong>
      <time datetime="<?= htmlspecialchars($review['publishedDate'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($review['publishedDate'], ENT_QUOTES, 'UTF-8') ?></time>
    </footer>
  </article>
<?php
};
?>
<section class="twins-brand-reviews" aria-labelledby="twins-brand-reviews-title">
  <div class="twins-brand-section-heading">
    <span class="twins-brand-kicker">Verified customer stories</span>
    <h2 id="twins-brand-reviews-title">What our customers say</h2>
    <p class="twins-brand-google-attribution" aria-label="Verified Google reviews">Google reviews from real Twins customers</p>
  </div>
  <?php if ($isReviewsPage): ?>
    <div class="twins-brand-review-list">
      <?php foreach ($records as $index => $review) $renderReviewCard($review, (int) $index, true); ?>
    </div>
  <?php else: ?>
    <div class="twins-brand-review-slider" data-twins-review-slider data-review-mode="featured" data-review-count="<?= count($records) ?>" tabindex="0" aria-label="Featured customer reviews">
      <div class="twins-brand-review-track">
        <?php foreach ($records as $index => $review) $renderReviewCard($review, (int) $index, false); ?>
      </div>
      <button type="button" class="twins-brand-review-control" data-review-prev aria-label="Previous reviews">Previous</button>
      <output class="twins-brand-review-status" data-review-page-status aria-live="polite">1 of 1</output>
      <button type="button" class="twins-brand-review-control" data-review-next aria-label="Next reviews">Next</button>
    </div>
    <a class="twins-brand-text-link" href="<?= htmlspecialchars($reviewsRoute, ENT_QUOTES, 'UTF-8') ?>">Read all reviews</a>
  <?php endif; ?>
  <?php if ($externalReviewsUrl !== null): ?>
    <a class="twins-brand-text-link" href="<?= htmlspecialchars($externalReviewsUrl, ENT_QUOTES, 'UTF-8') ?>" rel="noopener noreferrer">See our reviews on Google</a>
  <?php endif; ?>
</section>
