<?php
declare(strict_types=1);

if (!isset($quote['href']) || !is_string($quote['href']) || $quote['href'] === '') {
    throw new DomainException('Quote action is unavailable.');
}
if (!isset($pageContent) || !is_array($pageContent)) {
    throw new DomainException('Service page content is unavailable.');
}

$servicePath = isset($path) && is_string($path) ? $path : '';
$serviceNormalizedPath = '/' . trim(preg_replace(
    '~^/(wi|ky|il)/~',
    '/',
    '/' . ltrim($servicePath, '/')
), '/') . '/';
$serviceFacts = $pageContent['answerFacts'];
$servicePriceRange = $pageContent['priceRange'];
$serviceSafety = $pageContent['safety'];
$servicePlans = $pageContent['plans'];

// The TwinShield membership block. Only the paths registered in
// config/twinshield-program.php carry it; every other service page renders
// exactly as before. The component validates the program and throws on any
// drift, so a mistyped rate never reaches a customer.
require_once dirname(__DIR__) . '/components/twinshield-plan.php';
$twinshieldProgram = twins_brand_twinshield_program($serviceNormalizedPath);
$serviceGuarantee = 'Done Right, or We Make It Right.';
$serviceGuaranteeDetail = 'If something about our work is not right, we come back and fix it. No arguing, no fine print.';

// L10 L4 gate: an answer earns the gold-rule callout only when its own first
// sentence turns on a dollar figure. Mentioning a price later in the answer
// is not the same as the answer hinging on one, and the deck reserves the
// callout for the second case. Spec:
// docs/marketing/website-rebuild/l10-design-language.md
$serviceAnswerHingesOnFigure = static function (string $answer): bool {
    $first = $answer;
    if (preg_match('/^(.+?[.!?])(?:\s|$)/su', $answer, $matches) === 1) {
        $first = $matches[1];
    }
    return preg_match('/\$\s?\d/u', $first) === 1;
};

// Verbatim Google review quotes: the service-tagged pool from the city-page
// system, filtered to this record's tag first and rotated deterministically
// per path so pages differ without relabeling any quote.
$serviceReviewQuotes = [];
$serviceQuotesFile = dirname(__DIR__) . '/config/review-quotes.php';
$serviceQuotesPool = is_file($serviceQuotesFile) ? require $serviceQuotesFile : [];
if (is_array($serviceQuotesPool)) {
    $serviceQuotesPool = array_values(array_filter(
        $serviceQuotesPool,
        static fn($reviewQuote): bool => is_array($reviewQuote)
            && isset($reviewQuote['text'], $reviewQuote['author'], $reviewQuote['rating'], $reviewQuote['date'], $reviewQuote['service'])
            && is_string($reviewQuote['text']) && trim($reviewQuote['text']) !== ''
            && is_string($reviewQuote['author']) && trim($reviewQuote['author']) !== ''
            && is_int($reviewQuote['rating']) && $reviewQuote['rating'] >= 1 && $reviewQuote['rating'] <= 5
            && is_string($reviewQuote['date'])
            && is_string($reviewQuote['service'])
    ));
    $serviceTagged = array_values(array_filter(
        $serviceQuotesPool,
        static fn(array $reviewQuote): bool => $reviewQuote['service'] === $pageContent['reviewTag']
    ));
    $serviceUntagged = array_values(array_filter(
        $serviceQuotesPool,
        static fn(array $reviewQuote): bool => $reviewQuote['service'] !== $pageContent['reviewTag']
    ));
    $serviceOrderedPool = array_merge($serviceTagged, $serviceUntagged);
    if (count($serviceOrderedPool) >= 3) {
        if (count($serviceTagged) >= 3) {
            $serviceQuoteOffset = crc32($serviceNormalizedPath) % count($serviceTagged);
            for ($serviceQuoteIndex = 0; $serviceQuoteIndex < 3; $serviceQuoteIndex++) {
                $serviceReviewQuotes[] = $serviceTagged[($serviceQuoteOffset + $serviceQuoteIndex) % count($serviceTagged)];
            }
        } else {
            $serviceReviewQuotes = array_slice($serviceOrderedPool, 0, 3);
        }
    }
}

// Structured data owned by this template: Service + BreadcrumbList + FAQPage
// (docs/marketing/website-rebuild/build/schema/service-page.jsonld), with the
// FAQPage mirroring exactly the questions rendered below.
$serviceMarketNap = [
    'main' => ['street' => '2921 Landmark Pl, Ste 206', 'locality' => 'Madison', 'region' => 'WI', 'postalCode' => '53713', 'state' => 'Wisconsin', 'city' => 'Madison'],
    'wi' => ['street' => '2921 Landmark Pl, Ste 206', 'locality' => 'Madison', 'region' => 'WI', 'postalCode' => '53713', 'state' => 'Wisconsin', 'city' => 'Madison'],
    'ky' => ['street' => '3651 Aarons Run Rd', 'locality' => 'Mt Sterling', 'region' => 'KY', 'postalCode' => '40353', 'state' => 'Kentucky', 'city' => 'Mt Sterling'],
    'il-preview' => ['street' => '5758 Elaine Dr', 'locality' => 'Rockford', 'region' => 'IL', 'postalCode' => '61108', 'state' => 'Illinois', 'city' => 'Rockford'],
];
$serviceJsonLd = '';
$serviceNap = $serviceMarketNap[$marketKey] ?? null;
if ($serviceNap !== null && $servicePath !== '') {
    $serviceAbsoluteUrl = static fn(string $urlPath): string => function_exists('home_url') ? (string) home_url($urlPath) : $urlPath;
    $servicePageUrl = $serviceAbsoluteUrl($servicePath);
    $serviceProvider = [
        '@type' => 'HomeAndConstructionBusiness',
        'name' => 'Twins Garage Doors',
        'telephone' => '+1' . preg_replace('/\D+/', '', $phone),
        'url' => $serviceAbsoluteUrl('/'),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $serviceNap['street'],
            'addressLocality' => $serviceNap['locality'],
            'addressRegion' => $serviceNap['region'],
            'postalCode' => $serviceNap['postalCode'],
            'addressCountry' => 'US',
        ],
    ];
    $serviceSummaryFile = dirname(__DIR__) . '/config/review-summary.php';
    $serviceSummary = is_file($serviceSummaryFile) ? require $serviceSummaryFile : [];
    if (
        is_array($serviceSummary)
        && isset($serviceSummary['ratingValue'], $serviceSummary['reviewCountFloor'])
        && (is_float($serviceSummary['ratingValue']) || is_int($serviceSummary['ratingValue']))
        && is_int($serviceSummary['reviewCountFloor'])
    ) {
        $serviceProvider['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $serviceSummary['ratingValue'],
            'reviewCount' => $serviceSummary['reviewCountFloor'],
            'bestRating' => 5,
        ];
    }
    $serviceSchemaService = [
        '@type' => 'Service',
        '@id' => $servicePageUrl . '#service',
        'name' => $pageContent['h1'],
        'serviceType' => $pageContent['h1'],
        'url' => $servicePageUrl,
        'description' => $pageContent['directAnswer'],
        'provider' => $serviceProvider,
        'areaServed' => [
            '@type' => 'City',
            'name' => $serviceNap['city'],
            'containedInPlace' => ['@type' => 'State', 'name' => $serviceNap['state']],
        ],
    ];
    if ($servicePriceRange !== null) {
        $serviceSchemaService['offers'] = [
            '@type' => 'Offer',
            'priceCurrency' => 'USD',
            'priceSpecification' => [
                '@type' => 'PriceSpecification',
                'minPrice' => $servicePriceRange['min'],
                'maxPrice' => $servicePriceRange['max'],
                'priceCurrency' => 'USD',
            ],
        ];
    }
    if ($twinshieldProgram !== null) {
        $serviceSchemaService['hasOfferCatalog'] = twins_brand_twinshield_offer_catalog($twinshieldProgram);
    }
    $serviceBreadcrumbItems = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $serviceAbsoluteUrl('/')],
    ];
    if ($serviceNormalizedPath === '/garage-door-services/') {
        $serviceBreadcrumbItems[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $pageContent['h1'], 'item' => $servicePageUrl];
    } else {
        $serviceBreadcrumbItems[] = ['@type' => 'ListItem', 'position' => 2, 'name' => 'Garage Door Services', 'item' => $serviceAbsoluteUrl($experience->route('services', $marketKey))];
        $serviceBreadcrumbItems[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $pageContent['h1'], 'item' => $servicePageUrl];
    }
    $serviceSchemaGraph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            $serviceSchemaService,
            [
                '@type' => 'BreadcrumbList',
                '@id' => $servicePageUrl . '#breadcrumb',
                'itemListElement' => $serviceBreadcrumbItems,
            ],
            [
                '@type' => 'FAQPage',
                '@id' => $servicePageUrl . '#faq',
                'mainEntity' => array_map(
                    static fn(array $faq): array => [
                        '@type' => 'Question',
                        'name' => (string) $faq['question'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $faq['answer']],
                    ],
                    $pageContent['faqs']
                ),
            ],
        ],
    ];
    $serviceJsonLdCandidate = json_encode(
        $serviceSchemaGraph,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
    );
    if (is_string($serviceJsonLdCandidate) && $serviceJsonLdCandidate !== '' && stripos($serviceJsonLdCandidate, '<') === false) {
        $serviceJsonLd = $serviceJsonLdCandidate;
    }
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
    <div class="twins-l10-head twins-l10-head--onDark twins-l10-head--span">
      <span class="twins-brand-kicker">Direct answer</span>
      <h2 id="twins-brand-direct-answer-title">What to know first</h2>
      <p><?= htmlspecialchars($pageContent['directAnswer'], ENT_QUOTES, 'UTF-8') ?></p>
      <p class="twins-brand-service-guarantee-line"><strong><?= htmlspecialchars($serviceGuarantee, ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars($serviceGuaranteeDetail, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php
      // L10 L2: cost / timing / when-to-call as the deck's card row, with the
      // first fact on the inverted primary card so one answer leads. The
      // strings, the order and the description-list semantics are unchanged.
      $serviceFactRow = [];
      if ($serviceFacts['cost'] !== null) {
        $serviceFactRow[] = ['Typical cost', $serviceFacts['cost']];
      }
      if ($serviceFacts['timing'] !== null) {
        $serviceFactRow[] = ['Timing', $serviceFacts['timing']];
      }
      $serviceFactRow[] = ['When to call', $serviceFacts['call']];
    ?>
    <aside class="twins-brand-service-facts twins-l10-stats-shell twins-l10-head--span" aria-labelledby="twins-brand-service-facts-title">
      <h2 id="twins-brand-service-facts-title">The short version</h2>
      <dl class="twins-l10-stats">
        <?php foreach ($serviceFactRow as $serviceFactIndex => $serviceFact): ?>
          <div class="twins-l10-stat<?= $serviceFactIndex === 0 ? ' twins-l10-stat--lead' : '' ?>">
            <dt class="twins-l10-stat__label"><?= htmlspecialchars($serviceFact[0], ENT_QUOTES, 'UTF-8') ?></dt>
            <dd class="twins-l10-stat__body"><?= htmlspecialchars($serviceFact[1], ENT_QUOTES, 'UTF-8') ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </aside>
  </section>

  <section class="twins-brand-service-signs" aria-labelledby="twins-brand-service-signs-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">Warning signs</span>
      <h2 id="twins-brand-service-signs-title">Signs this is your door</h2>
    </div>
    <ul class="twins-brand-service-grid">
      <?php foreach ($pageContent['warningSigns'] as $serviceSign): ?>
        <li><?= htmlspecialchars($serviceSign, ENT_QUOTES, 'UTF-8') ?></li>
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
    <aside class="twins-brand-service-guarantee" aria-labelledby="twins-brand-service-guarantee-title">
      <h2 id="twins-brand-service-guarantee-title"><?= htmlspecialchars($serviceGuarantee, ENT_QUOTES, 'UTF-8') ?></h2>
      <p><?= htmlspecialchars($serviceGuaranteeDetail, ENT_QUOTES, 'UTF-8') ?> You approve the exact price before any work starts, and the number you approve is the number you pay.</p>
    </aside>
  </section>

  <section class="twins-brand-service-cost" aria-labelledby="twins-brand-service-cost-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">Real prices from completed jobs</span>
      <h2 id="twins-brand-service-cost-title">What it costs</h2>
    </div>
    <?php if ($servicePriceRange !== null): ?>
      <p class="twins-brand-service-cost-range" aria-label="Typical price range">
        $<?= htmlspecialchars(number_format($servicePriceRange['min']), ENT_QUOTES, 'UTF-8') ?>
        <span>to</span>
        $<?= htmlspecialchars(number_format($servicePriceRange['max']), ENT_QUOTES, 'UTF-8') ?>
      </p>
    <?php endif; ?>
    <div class="twins-brand-service-cost-copy">
      <?php foreach ($pageContent['costParagraphs'] as $serviceCostParagraph): ?>
        <p><?= htmlspecialchars($serviceCostParagraph, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($serviceSafety !== null): ?>
    <section class="twins-brand-service-safety-section" aria-labelledby="twins-brand-service-safety-title">
      <div class="twins-brand-service-safety twins-l10-callout">
        <h2 id="twins-brand-service-safety-title" class="twins-l10-callout__title">Do not DIY this one. Seriously.</h2>
        <p><?= htmlspecialchars($serviceSafety, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($twinshieldProgram !== null): ?>
    <?= twins_brand_twinshield_section($twinshieldProgram, $experience->route('contact', $marketKey)) ?>
  <?php endif; ?>

  <?php if ($servicePlans !== null): ?>
    <section class="twins-brand-service-options twins-brand-membership" aria-labelledby="twins-brand-service-options-title">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Membership tiers</span>
        <h2 id="twins-brand-service-options-title">Choose your TwinShield plan</h2>
      </div>
      <div class="twins-brand-membership-grid">
        <?php foreach ($servicePlans as $planIndex => $plan): ?>
          <?php
            $planParts = explode(' - ', $plan['tradeoff'], 2);
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
    </section>
  <?php endif; ?>

  <?php if ($serviceReviewQuotes !== []): ?>
    <section class="twins-brand-service-reviews" aria-labelledby="twins-brand-service-reviews-title">
      <div class="twins-brand-section-heading">
        <span class="twins-brand-kicker">Verified customer stories</span>
        <h2 id="twins-brand-service-reviews-title">What Twins customers say</h2>
        <p class="twins-brand-google-attribution" aria-label="Verified Google reviews">Verbatim Google reviews from real Twins customers</p>
      </div>
      <div class="twins-location-review-grid">
        <?php foreach ($serviceReviewQuotes as $serviceQuoteRecord): ?>
          <article class="twins-location-review-card">
            <div class="twins-brand-review-stars" aria-label="<?= (int) $serviceQuoteRecord['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $serviceQuoteRecord['rating']) ?></div>
            <blockquote><?= htmlspecialchars($serviceQuoteRecord['text'], ENT_QUOTES, 'UTF-8') ?></blockquote>
            <footer>
              <strong><?= htmlspecialchars($serviceQuoteRecord['author'], ENT_QUOTES, 'UTF-8') ?></strong>
              <time datetime="<?= htmlspecialchars($serviceQuoteRecord['date'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($serviceQuoteRecord['date'], ENT_QUOTES, 'UTF-8') ?></time>
            </footer>
          </article>
        <?php endforeach; ?>
      </div>
      <a class="twins-brand-text-link" href="<?= htmlspecialchars($experience->route('reviews', $marketKey), ENT_QUOTES, 'UTF-8') ?>">Read all reviews</a>
    </section>
  <?php endif; ?>

  <section class="twins-brand-service-related" aria-labelledby="twins-brand-service-related-title">
    <div class="twins-brand-section-heading">
      <span class="twins-brand-kicker">Related services</span>
      <h2 id="twins-brand-service-related-title">While we are on the subject</h2>
    </div>
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
          <?php // L10 L4: only the answers whose first sentence turns on a figure. ?>
          <p<?= $serviceAnswerHingesOnFigure($faq['answer']) ? ' class="twins-l10-callout"' : '' ?>><?= htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8') ?></p>
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
  <?php if ($serviceJsonLd !== ''): ?>
    <script type="application/ld+json"><?= $serviceJsonLd ?></script>
  <?php endif; ?>
</div>
