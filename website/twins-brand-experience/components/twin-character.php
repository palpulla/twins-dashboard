<?php
declare(strict_types=1);

$characters = [
    'left' => ['asset' => 'twin-left', 'width' => 196, 'height' => 534],
    'right' => ['asset' => 'twin-right', 'width' => 297, 'height' => 538],
];
$allowedPairs = [
    ['right', 'services'],
    ['left', 'final-left'],
    ['right', 'final-right'],
];

if (
    !isset($character, $placement)
    || !is_string($character)
    || !is_string($placement)
    || !isset($characters[$character])
    || !in_array([$character, $placement], $allowedPairs, true)
) {
    return;
}

$selectedCharacter = $characters[$character];
$loading = 'lazy';
?>
<img src="<?= htmlspecialchars($experience->asset($selectedCharacter['asset']), ENT_QUOTES, 'UTF-8') ?>" width="<?= (int) $selectedCharacter['width'] ?>" height="<?= (int) $selectedCharacter['height'] ?>" class="twins-location-twin twins-location-twin--<?= htmlspecialchars($placement, ENT_QUOTES, 'UTF-8') ?> twins-location-twin--<?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true" loading="<?= $loading ?>" decoding="async">
