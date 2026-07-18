<?php
declare(strict_types=1);

/**
 * Inline garage-door brand art.
 *
 * Every graphic is a fixed, dependency-free inline SVG in the Twins palette.
 * Nothing here fetches, submits, or references a remote resource.
 */

if (!function_exists('twins_brand_door_curtain_markup')) {
    /**
     * One four-section door curtain: a window row on top, raised panels below.
     */
    function twins_brand_door_curtain_markup(): string
    {
        $svg = '';
        $left = 26.0;
        $top = 26.0;
        $cellWidth = 40.0;
        $cellGap = 3.6;
        $rowHeight = 32.0;
        $rowGap = 3.4;

        for ($row = 0; $row < 4; $row++) {
            $y = $top + $row * ($rowHeight + $rowGap);
            for ($column = 0; $column < 4; $column++) {
                $x = $left + $column * ($cellWidth + $cellGap);
                if ($row === 0) {
                    $svg .= sprintf(
                        '<rect x="%.1F" y="%.1F" width="%.1F" height="%.1F" rx="2.5" class="twins-da-window-frame"/>'
                        . '<rect x="%.1F" y="%.1F" width="%.1F" height="%.1F" rx="1.5" class="twins-da-glass"/>'
                        . '<path d="M %.1F %.1F q 8 -5 16 0 t 16 0 v %.1F h -32 z" class="twins-da-glass-hi"/>',
                        $x,
                        $y,
                        $cellWidth,
                        $rowHeight,
                        $x + 3.4,
                        $y + 3.4,
                        $cellWidth - 6.8,
                        $rowHeight - 6.8,
                        $x + 3.7,
                        $y + $rowHeight - 12.0,
                        8.4,
                    );
                    continue;
                }
                $svg .= sprintf(
                    '<rect x="%.1F" y="%.1F" width="%.1F" height="%.1F" rx="2" class="twins-da-panel"/>'
                    . '<rect x="%.1F" y="%.1F" width="%.1F" height="%.1F" rx="1.4" class="twins-da-panel-inner"/>',
                    $x,
                    $y,
                    $cellWidth,
                    $rowHeight,
                    $x + 7.4,
                    $y + 6.4,
                    $cellWidth - 14.8,
                    $rowHeight - 12.8,
                );
            }
        }
        return $svg;
    }
}

if (!function_exists('twins_brand_door_art')) {
    /**
     * Render one fixed brand graphic.
     *
     * @param string $kind door | door-open | spring | roller | keypad
     * @param string $class Extra CSS classes.
     * @param string $idSuffix Unique per page when the same animated kind repeats.
     */
    function twins_brand_door_art(string $kind, string $class = '', string $idSuffix = ''): string
    {
        $classAttribute = htmlspecialchars(trim('twins-brand-door-art twins-brand-door-art--' . $kind . ' ' . $class), ENT_QUOTES, 'UTF-8');
        $suffix = preg_replace('/[^a-z0-9-]/', '', strtolower($idSuffix));

        if ($kind === 'door') {
            return '<svg viewBox="0 0 220 190" class="' . $classAttribute . '" aria-hidden="true" focusable="false">'
                . '<rect x="2" y="2" width="216" height="186" rx="10" class="twins-da-gold"/>'
                . '<rect x="11" y="11" width="198" height="168" rx="6" class="twins-da-navy"/>'
                . '<rect x="20" y="20" width="180" height="150" rx="4" class="twins-da-face"/>'
                . twins_brand_door_curtain_markup()
                . '</svg>';
        }

        if ($kind === 'door-open') {
            $clipId = 'twins-door-clip' . ($suffix === '' ? '' : '-' . $suffix);
            return '<svg viewBox="0 0 220 190" class="' . $classAttribute . '" aria-hidden="true" focusable="false">'
                . '<defs><clipPath id="' . $clipId . '"><rect x="20" y="20" width="180" height="150" rx="4"/></clipPath></defs>'
                . '<rect x="2" y="2" width="216" height="186" rx="10" class="twins-da-gold"/>'
                . '<rect x="11" y="11" width="198" height="168" rx="6" class="twins-da-navy"/>'
                . '<rect x="20" y="20" width="180" height="150" rx="4" class="twins-da-interior"/>'
                . '<ellipse cx="110" cy="168" rx="74" ry="26" class="twins-da-glow"/>'
                . '<rect x="34" y="150" width="152" height="6" rx="3" class="twins-da-floor"/>'
                . '<g clip-path="url(#' . $clipId . ')"><g class="twins-da-curtain">'
                . '<rect x="20" y="20" width="180" height="150" rx="4" class="twins-da-face"/>'
                . twins_brand_door_curtain_markup()
                . '</g></g>'
                . '</svg>';
        }

        if ($kind === 'spring') {
            $coils = '';
            for ($index = 0; $index < 7; $index++) {
                $coils .= sprintf('<ellipse cx="%.1F" cy="60" rx="13" ry="24" class="twins-da-coil"/>', 42.0 + $index * 19.5);
            }
            return '<svg viewBox="0 0 220 120" class="' . $classAttribute . '" aria-hidden="true" focusable="false">'
                . '<rect x="8" y="55" width="204" height="10" rx="5" class="twins-da-shaft"/>'
                . $coils
                . '<circle cx="18" cy="60" r="11" class="twins-da-hub"/>'
                . '<circle cx="202" cy="60" r="11" class="twins-da-hub"/>'
                . '</svg>';
        }

        if ($kind === 'roller') {
            return '<svg viewBox="0 0 220 120" class="' . $classAttribute . '" aria-hidden="true" focusable="false">'
                . '<path d="M 30 12 h 22 v 96 h -22 z" class="twins-da-shaft"/>'
                . '<path d="M 30 12 h 22 v 10 h -12 v 86 h -10 z" class="twins-da-navy"/>'
                . '<rect x="62" y="52" width="66" height="10" rx="5" class="twins-da-shaft"/>'
                . '<circle cx="150" cy="57" r="34" class="twins-da-hub"/>'
                . '<circle cx="150" cy="57" r="22" class="twins-da-coil-fill"/>'
                . '<circle cx="150" cy="57" r="7" class="twins-da-navy"/>'
                . '</svg>';
        }

        if ($kind === 'keypad') {
            $buttons = '';
            for ($row = 0; $row < 3; $row++) {
                for ($column = 0; $column < 2; $column++) {
                    $buttons .= sprintf(
                        '<rect x="%.1F" y="%.1F" width="26" height="15" rx="4" class="twins-da-button"/>',
                        76.0 + $column * 42.0,
                        44.0 + $row * 23.0,
                    );
                }
            }
            return '<svg viewBox="0 0 220 160" class="' . $classAttribute . '" aria-hidden="true" focusable="false">'
                . '<rect x="58" y="8" width="104" height="144" rx="14" class="twins-da-navy"/>'
                . '<rect x="70" y="20" width="80" height="14" rx="5" class="twins-da-glow-strong"/>'
                . $buttons
                . '<rect x="76" y="113" width="68" height="15" rx="6" class="twins-da-gold"/>'
                . '</svg>';
        }

        return '';
    }
}

if (!function_exists('twins_brand_door_avatar')) {
    /**
     * Branded stand-in for a crew member without an approved photo yet.
     */
    function twins_brand_door_avatar(string $initials, string $class = ''): string
    {
        $safeInitials = htmlspecialchars(mb_substr(preg_replace('/[^A-Za-z]/', '', $initials) ?? '', 0, 2), ENT_QUOTES, 'UTF-8');
        $classAttribute = htmlspecialchars(trim('twins-brand-door-avatar ' . $class), ENT_QUOTES, 'UTF-8');
        return '<svg viewBox="0 0 220 264" class="' . $classAttribute . '" role="img" aria-hidden="true" focusable="false">'
            . '<rect x="2" y="2" width="216" height="260" rx="12" class="twins-da-navy"/>'
            . '<rect x="10" y="10" width="200" height="244" rx="8" class="twins-da-navy-soft"/>'
            . '<circle cx="110" cy="84" r="50" class="twins-da-ring"/>'
            . '<text x="110" y="102" text-anchor="middle" class="twins-da-initials">' . $safeInitials . '</text>'
            . '<g transform="translate(55 158) scale(0.5)">'
            . '<rect x="2" y="2" width="216" height="186" rx="10" class="twins-da-gold"/>'
            . '<rect x="11" y="11" width="198" height="168" rx="6" class="twins-da-navy"/>'
            . '<rect x="20" y="20" width="180" height="150" rx="4" class="twins-da-face"/>'
            . twins_brand_door_curtain_markup()
            . '</g>'
            . '</svg>';
    }
}
