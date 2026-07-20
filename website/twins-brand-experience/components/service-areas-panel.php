<?php
declare(strict_types=1);

/**
 * City links presented on an illustrated garage door.
 * Requires $experience, $marketKey, $environment in scope.
 * Renders nothing for markets without city links.
 */

require __DIR__ . '/nav-data.php';

$panelCityLinks = array_values(array_filter(
    $marketCityLinks,
    static fn(array $item): bool => strpos($item[1], 'city-') === 0
));
if ($panelCityLinks !== []):
?>
<section class="twins-brand-door-map" aria-labelledby="twins-brand-door-map-title">
  <div class="twins-brand-section-heading">
    <span class="twins-brand-kicker">Where we work</span>
    <h2 id="twins-brand-door-map-title">Our service areas</h2>
  </div>
  <div class="twins-brand-door-map-door">
    <ul class="twins-brand-door-map-grid">
      <?php foreach ($panelCityLinks as [$panelCityLabel, $panelCityRoute]): ?>
        <li>
          <a href="<?= htmlspecialchars($experience->route($panelCityRoute, $marketKey), ENT_QUOTES, 'UTF-8') ?>">
            <span class="twins-brand-door-map-pin" aria-hidden="true"></span>
            <?= htmlspecialchars($panelCityLabel, ENT_QUOTES, 'UTF-8') ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="twins-brand-door-map-handle" aria-hidden="true"></div>
  </div>
</section>
<?php endif; ?>
