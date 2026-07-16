<?php
declare(strict_types=1);

$collection = $experience->reviewCollection();
$reviewsRoute = $experience->route('reviews', $marketKey);

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

$externalReviewsUrl = null;
if ($environment === 'production' && ($collection['allowExternalSourceAction'] ?? null) === true) {
    if (!isset($collection['businessReviewsUrl']) || !is_string($collection['businessReviewsUrl']) || $collection['businessReviewsUrl'] === '') {
        throw new DomainException('Verified external review action is unavailable.');
    }
    $externalReviewsUrl = $collection['businessReviewsUrl'];
}
?>
<section class="twins-brand-reviews" aria-labelledby="twins-brand-reviews-title">
  <div class="twins-brand-section-heading">
    <span class="twins-brand-kicker">Verified customer stories</span>
    <h2 id="twins-brand-reviews-title">What our customers say</h2>
    <p class="twins-brand-google-attribution" aria-label="Verified Google reviews">Google reviews from real Twins customers</p>
  </div>
  <div class="twins-brand-review-slider" data-twins-review-slider data-review-count="<?= count($collection['records']) ?>">
    <div class="twins-brand-review-track">
      <?php foreach ($collection['records'] as $index => $review): ?>
        <article class="twins-brand-review-card" data-review-stable-id="<?= htmlspecialchars($review['stableId'], ENT_QUOTES, 'UTF-8') ?>" data-review-index="<?= (int) $index ?>">
          <div class="twins-brand-review-stars" aria-label="<?= (int) $review['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $review['rating']) ?></div>
          <blockquote><?= nl2br(htmlspecialchars($review['text'], ENT_QUOTES, 'UTF-8')) ?></blockquote>
          <footer>
            <strong><?= htmlspecialchars($review['author'], ENT_QUOTES, 'UTF-8') ?></strong>
            <time datetime="<?= htmlspecialchars($review['publishedDate'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($review['publishedDate'], ENT_QUOTES, 'UTF-8') ?></time>
          </footer>
        </article>
      <?php endforeach; ?>
    </div>
    <button type="button" data-review-prev aria-label="Previous reviews">Previous</button>
    <div class="twins-brand-review-dots" role="group" aria-label="Choose review page"></div>
    <button type="button" data-review-next aria-label="Next reviews">Next</button>
  </div>
  <a class="twins-brand-text-link" href="<?= htmlspecialchars($reviewsRoute, ENT_QUOTES, 'UTF-8') ?>">Read all reviews</a>
  <?php if ($externalReviewsUrl !== null): ?>
    <a class="twins-brand-text-link" href="<?= htmlspecialchars($externalReviewsUrl, ENT_QUOTES, 'UTF-8') ?>" rel="noopener noreferrer">See our reviews on Google</a>
  <?php endif; ?>
</section>
