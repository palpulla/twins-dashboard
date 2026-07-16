<?php
declare(strict_types=1);

$mode = $catalogView['mode'];
$isProduct = $mode === 'product';
$product = $isProduct ? $catalogView['product'] : null;
$collections = $isProduct ? [] : $catalogView['collections'];
$featured = $isProduct ? [] : $catalogView['featured'];
$builderPath = $catalogView['builderPath'];
$heading = $isProduct ? 'Clopay ' . $product['title'] : 'Clopay garage door collections';
$eyebrow = $isProduct ? 'Clopay manufacturer reference' : 'Frozen Clopay catalog';
$lead = $isProduct
    ? 'This private staging page shows the frozen product record and local manufacturer reference images available for this collection.'
    : 'This private staging page uses the fixed local catalog of 23 Clopay product records. Start with the three featured manufacturer references below or open the builder to compare the full frozen catalog.';
$heroImage = $isProduct ? $product['showcase'] : $featured[0]['product']['showcase'];
$factFamilies = [
    ['key' => 'designs', 'label' => 'Panels and designs'],
    ['key' => 'colors', 'label' => 'Colors'],
    ['key' => 'windows', 'label' => 'Windows'],
    ['key' => 'glass', 'label' => 'Glass'],
    ['key' => 'hardware', 'label' => 'Hardware'],
    ['key' => 'gallery', 'label' => 'Gallery images'],
];
$selectionFamilies = [
    ['key' => 'colors', 'label' => 'Colors', 'empty' => 'color'],
    ['key' => 'windows', 'label' => 'Windows', 'empty' => 'window'],
    ['key' => 'glass', 'label' => 'Glass', 'empty' => 'glass'],
    ['key' => 'hardware', 'label' => 'Hardware', 'empty' => 'hardware'],
];
$gallery = $isProduct ? array_slice($product['gallery'], 0, 3) : [];
$escape = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<main id="twins-overhaul-main" class="twins-brand-page twins-brand-catalog-page">
  <section class="twins-brand-catalog-hero" aria-labelledby="twins-catalog-title">
    <div class="twins-brand-catalog-hero__copy">
      <span class="twins-brand-kicker"><?= $escape($eyebrow) ?></span>
      <h1 id="twins-catalog-title"><?= $escape($heading) ?></h1>
      <p><?= $escape($lead) ?></p>
      <div class="twins-brand-catalog-actions">
        <a class="twins-brand-cta twins-brand-cta--quote" href="<?= $escape($builderPath) ?>">Design This Door</a>
        <a class="twins-brand-cta twins-brand-cta--book" href="<?= $escape($quotePath) ?>">Request a Quote</a>
      </div>
    </div>
    <figure class="twins-brand-catalog-hero__image">
      <img
        src="<?= $escape($heroImage['src']) ?>"
        width="<?= $escape((string) $heroImage['width']) ?>"
        height="<?= $escape((string) $heroImage['height']) ?>"
        alt="<?= $escape($heroImage['alt']) ?>"
        decoding="async"
        fetchpriority="high"
      >
    </figure>
  </section>

  <?php if (!$isProduct): ?>
    <section class="twins-brand-catalog-section" aria-labelledby="twins-catalog-featured">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Featured frozen records</span>
        <h2 id="twins-catalog-featured">Three Clopay manufacturer references</h2>
        <p>These fixed records are Modern Steel™, Gallery® Steel, and Classic™ Steel.</p>
      </div>
      <div class="twins-brand-catalog-grid">
        <?php foreach ($featured as $record): ?>
          <?php $featuredProduct = $record['product']; ?>
          <article class="twins-brand-catalog-card">
            <img
              src="<?= $escape($featuredProduct['showcase']['src']) ?>"
              width="<?= $escape((string) $featuredProduct['showcase']['width']) ?>"
              height="<?= $escape((string) $featuredProduct['showcase']['height']) ?>"
              alt="<?= $escape($featuredProduct['showcase']['alt']) ?>"
              loading="lazy"
              decoding="async"
            >
            <h3><?= $escape($featuredProduct['title']) ?></h3>
            <dl class="twins-brand-catalog-facts">
              <?php foreach ($factFamilies as $family): ?>
                <div><dt><?= $escape($family['label']) ?></dt><dd><?= $escape((string) count($featuredProduct[$family['key']])) ?></dd></div>
              <?php endforeach; ?>
            </dl>
            <a href="<?= $escape($record['path']) ?>">View manufacturer record</a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="twins-brand-catalog-section twins-brand-catalog-section--ordered" aria-labelledby="twins-catalog-all">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Complete frozen catalog</span>
        <h2 id="twins-catalog-all">All 23 frozen product records</h2>
        <p>The records below follow the fixed catalog order used by the builder.</p>
      </div>
      <div class="twins-brand-catalog-grid">
        <?php foreach ($collections as $record): ?>
          <?php $collectionProduct = $record['product']; ?>
          <article class="twins-brand-catalog-card">
            <img
              src="<?= $escape($collectionProduct['showcase']['src']) ?>"
              width="<?= $escape((string) $collectionProduct['showcase']['width']) ?>"
              height="<?= $escape((string) $collectionProduct['showcase']['height']) ?>"
              alt="<?= $escape($collectionProduct['showcase']['alt']) ?>"
              loading="lazy"
              decoding="async"
            >
            <h3><?= $escape($collectionProduct['title']) ?></h3>
            <a href="<?= $escape($record['path']) ?>">View manufacturer record</a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else: ?>
    <section class="twins-brand-catalog-section" aria-labelledby="twins-catalog-record">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Frozen product facts</span>
        <h2 id="twins-catalog-record">What the frozen record includes</h2>
        <p>Counts below come directly from the fixed local record for <?= $escape($product['title']) ?>.</p>
      </div>
      <dl class="twins-brand-catalog-facts twins-brand-catalog-facts--large">
        <?php foreach ($factFamilies as $family): ?>
          <div><dt><?= $escape($family['label']) ?></dt><dd><?= $escape((string) count($product[$family['key']])) ?></dd></div>
        <?php endforeach; ?>
      </dl>
    </section>

    <section class="twins-brand-catalog-section twins-brand-catalog-section--dark" aria-labelledby="twins-catalog-designs">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Construction and design labels</span>
        <h2 id="twins-catalog-designs">Panels and designs</h2>
        <p>These labels are copied from the frozen product record.</p>
      </div>
      <?php if ($product['designs'] !== []): ?>
        <ul class="twins-brand-catalog-labels">
          <?php foreach ($product['designs'] as $design): ?>
            <li><?= $escape($design['title']) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p>The frozen record does not list panel or design choices for this product.</p>
      <?php endif; ?>
    </section>

    <section class="twins-brand-catalog-section" aria-labelledby="twins-catalog-selections">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Frozen option samples</span>
        <h2 id="twins-catalog-selections">Selection references</h2>
        <p>The first three labels in each non-empty family are shown below. Open the builder to compare every frozen option.</p>
      </div>
      <div class="twins-brand-catalog-selection-grid">
        <?php foreach ($selectionFamilies as $family): ?>
          <article>
            <h3><?= $escape($family['label']) ?> <span><?= $escape((string) count($product[$family['key']])) ?></span></h3>
            <?php if ($product[$family['key']] !== []): ?>
              <ul>
                <?php foreach (array_slice($product[$family['key']], 0, 3) as $option): ?>
                  <li><?= $escape($option['title']) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p>No <?= $escape($family['empty']) ?> records are listed in this frozen product record.</p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($gallery !== []): ?>
      <section class="twins-brand-catalog-section twins-brand-catalog-section--gallery" aria-labelledby="twins-catalog-gallery">
        <div class="twins-brand-section-heading">
          <span class="twins-brand-kicker">Frozen local files</span>
          <h2 id="twins-catalog-gallery">Local manufacturer reference images</h2>
          <p>These local files are reference images from the frozen catalog. Twins confirms the final appearance before ordering.</p>
        </div>
        <div class="twins-brand-catalog-gallery">
          <?php foreach ($gallery as $photo): ?>
            <figure>
              <img
                src="<?= $escape($photo['image']['src']) ?>"
                width="<?= $escape((string) $photo['image']['width']) ?>"
                height="<?= $escape((string) $photo['image']['height']) ?>"
                alt="<?= $escape($photo['image']['alt']) ?>"
                loading="lazy"
                decoding="async"
              >
              <figcaption><?= $escape($photo['title']) ?></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="twins-brand-catalog-section twins-brand-catalog-section--closing" aria-labelledby="twins-catalog-builder">
      <div>
        <span class="twins-brand-kicker">Continue with Twins</span>
        <h2 id="twins-catalog-builder">Compare this product in the door builder</h2>
        <p>Open the private builder to compare the product and the selection references present in this frozen record.</p>
      </div>
      <div class="twins-brand-catalog-actions">
        <a class="twins-brand-cta twins-brand-cta--quote" href="<?= $escape($builderPath) ?>">Design This Door</a>
        <a class="twins-brand-cta twins-brand-cta--book" href="<?= $escape($quotePath) ?>">Request a Quote</a>
      </div>
    </section>
  <?php endif; ?>

  <p class="twins-brand-catalog-preview-notice">Private staging preview. This page does not contact Clopay, submit a quote request, or store project information.</p>
</main>
