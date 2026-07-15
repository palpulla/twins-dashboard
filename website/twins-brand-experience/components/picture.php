<?php
declare(strict_types=1);

$pictures = [
    'crew-fleet' => [
        'original' => 'crew-fleet-original',
        'sources' => ['crew-fleet-768w 768w', 'crew-fleet-1280w 1280w', 'crew-fleet-1920w 1920w'],
        'width' => 2560,
        'height' => 1372,
        'alt' => 'The Twins Garage Doors crew with their branded service fleet',
    ],
    'tal-portrait' => [
        'original' => 'tal-portrait-original',
        'sources' => ['tal-portrait-480w 480w', 'tal-portrait-768w 768w', 'tal-portrait-1066w 1066w'],
        'width' => 1066,
        'height' => 1600,
        'alt' => 'Twins Garage Doors co-founder Tal Joseph',
    ],
    'technician-at-work' => [
        'original' => 'technician-original',
        'sources' => ['technician-480w 480w', 'technician-768w 768w', 'technician-924w 924w'],
        'width' => 924,
        'height' => 570,
        'alt' => 'A Twins Garage Doors technician working on a garage door',
    ],
    'door-builder-before-after' => [
        'original' => 'door-builder-before-after',
        'sources' => [],
        'width' => 1080,
        'height' => 930,
        'alt' => 'Before and after view of a real Twins garage door installation',
    ],
];

if (!isset($pictures[$logicalKey])) throw new DomainException('Unknown picture key.');

$picture = $pictures[$logicalKey];
$srcset = [];
foreach ($picture['sources'] as $source) {
    [$assetKey, $descriptor] = explode(' ', $source, 2);
    $srcset[] = $experience->asset($assetKey) . ' ' . $descriptor;
}
?>
<picture>
  <?php if ($srcset !== []): ?>
    <source type="image/webp" srcset="<?= htmlspecialchars(implode(', ', $srcset), ENT_QUOTES, 'UTF-8') ?>" sizes="<?= htmlspecialchars($sizes, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <img src="<?= htmlspecialchars($experience->asset($picture['original']), ENT_QUOTES, 'UTF-8') ?>" width="<?= (int) $picture['width'] ?>" height="<?= (int) $picture['height'] ?>" alt="<?= htmlspecialchars($picture['alt'], ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>" loading="<?= htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') ?>">
</picture>
