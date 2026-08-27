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
    'daniel-portrait' => [
        'original' => 'daniel-portrait-original',
        'sources' => ['daniel-portrait-480w 480w', 'daniel-portrait-768w 768w', 'daniel-portrait-1066w 1066w'],
        'width' => 1254,
        'height' => 1673,
        'alt' => 'Twins Garage Doors co-founder and CEO Daniel Joseph',
    ],
    'charles-portrait' => [
        'original' => 'charles-portrait-original',
        'sources' => ['charles-portrait-480w 480w', 'charles-portrait-768w 768w', 'charles-portrait-1066w 1066w'],
        'width' => 1600,
        'height' => 1322,
        'alt' => 'Twins Garage Doors field operations manager Charles Rue',
    ],
    'maurice-portrait' => [
        'original' => 'maurice-portrait-original',
        'sources' => ['maurice-portrait-480w 480w', 'maurice-portrait-768w 768w', 'maurice-portrait-1066w 1066w'],
        'width' => 1448,
        'height' => 1086,
        'alt' => 'Twins Garage Doors senior technician Maurice Williams',
    ],
    'nicholas-portrait' => [
        'original' => 'nicholas-portrait-original',
        'sources' => ['nicholas-portrait-480w 480w', 'nicholas-portrait-768w 768w', 'nicholas-portrait-1066w 900w'],
        'width' => 900,
        'height' => 1600,
        'alt' => 'Twins Garage Doors technician Nicholas Roccaforte',
    ],
    'door-builder-before-after' => [
        'original' => 'door-builder-before-after',
        'sources' => [],
        'width' => 1080,
        'height' => 930,
        'alt' => 'Before and after view of a real Twins garage door installation',
    ],
];

$requestedFallbackKey = $pictureFallbackLogicalKey ?? null;
unset($pictureFallbackLogicalKey);

/* EAGER AND HIGH USED TO BE THE SAME DECISION, and they are not. A hero
   portrait wants both: load it now and claim the LCP slot. A panel that is
   simply the one the page opens on wants only the first half -- it must be in
   the picture when its section arrives, and it must not compete with the hero
   for the first bytes on the wire. `$fetchPriority` splits the pair. It is
   consumed like every other picture input so it cannot leak into the next
   require, and the only two values it accepts are the two the platform has. */
$requestedFetchPriority = $fetchPriority ?? null;
unset($fetchPriority);
if ($requestedFetchPriority !== null && !in_array($requestedFetchPriority, ['high', 'auto'], true)) {
    throw new DomainException('Picture fetch priority is outside the fixed boundary.');
}
$pictureLoadingAttributes = $loading === 'eager'
    ? ($requestedFetchPriority === 'auto' ? ' decoding="async"' : ' fetchpriority="high"')
    : ' decoding="async"';

$resolvePicture = static function (string $key) use ($experience, $pictures): array {
    if (!isset($pictures[$key])) {
        throw new DomainException('Unknown picture key.');
    }

    $picture = $pictures[$key];
    $srcset = [];
    foreach ($picture['sources'] as $source) {
        [$assetKey, $descriptor] = explode(' ', $source, 2);
        $srcset[] = $experience->asset($assetKey) . ' ' . $descriptor;
    }

    return [$picture, $srcset, $experience->asset($picture['original'])];
};

try {
    [$picture, $srcset, $resolvedSrc] = $resolvePicture($logicalKey);
} catch (\DomainException $error) {
    if (
        !is_string($requestedFallbackKey)
        || $requestedFallbackKey === ''
        || $requestedFallbackKey === $logicalKey
    ) {
        throw $error;
    }
    [$picture, $srcset, $resolvedSrc] = $resolvePicture($requestedFallbackKey);
}
?>
<picture>
  <?php if ($srcset !== []): ?>
    <source type="image/webp" srcset="<?= htmlspecialchars(implode(', ', $srcset), ENT_QUOTES, 'UTF-8') ?>" sizes="<?= htmlspecialchars($sizes, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <img src="<?= htmlspecialchars($resolvedSrc, ENT_QUOTES, 'UTF-8') ?>" width="<?= (int) $picture['width'] ?>" height="<?= (int) $picture['height'] ?>" alt="<?= htmlspecialchars($picture['alt'], ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>" loading="<?= htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') ?>"<?= $pictureLoadingAttributes ?>>
</picture>
