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

if (!function_exists('twins_brand_hero_art')) {
    /**
     * THE SHARED HERO ART.
     *
     * The owner's report on 2026-08-26 was "no pic at top", filed against
     * /contact-us/ and a service page and explicitly meant for the class, not
     * the two examples. Eight of the sixteen page families opened on a navy
     * field with a headline in the left 45% and nothing at all in the right
     * half: contact, reviews, team, the three editorial kinds, blog, and the
     * five service pages with no part to draw.
     *
     * The two heroes he did not complain about are the reference. The home hero
     * is a garage-door panel with the two Twins standing in front of it; the
     * careers hero is a framed crew photograph. Both put a real object in the
     * right half. This function is the home hero's half of that language, made
     * reusable, so the artless families join the site instead of each inventing
     * something.
     *
     * Assets: the inline door panel below, and twin-left / twin-right, the two
     * owned brand mascots already used on the home hero and on every city page.
     * No new raster asset, no photograph outside its provenance allowedUse, no
     * remote request.
     *
     * Decorative only: aria-hidden, empty alt, pointer-events off in CSS. The
     * heroes it goes into carry their meaning entirely in the h1 and the lede.
     *
     * @param string $variant crew (both twins) | twin (one twin) | door (framed panel) | truck (owned fleet photo, team only)
     * @param object $experience Portable experience, for owned asset URLs.
     * @param string $idSuffix Unique per page when two hero arts could co-exist.
     */
    function twins_brand_hero_art(string $variant, $experience, string $idSuffix = ''): string
    {
        if (!in_array($variant, ['crew', 'twin', 'door', 'truck'], true)) {
            return '';
        }
        if (!is_object($experience) || !method_exists($experience, 'asset')) {
            return '';
        }
        $suffix = preg_replace('/[^a-z0-9-]/', '', strtolower($idSuffix));
        $gradientId = 'twins-hero-art-sky' . ($suffix === '' ? '' : '-' . $suffix);

        // The panel: the home hero's door, redrawn on a 4x3 grid so it still
        // reads as a door at the smaller widths these heroes give it.
        $panel = '<svg class="twins-brand-hero-art__door" viewBox="0 0 520 330" aria-hidden="true" focusable="false">'
            . '<defs><linearGradient id="' . $gradientId . '" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0" stop-color="#e9eef4"/><stop offset="1" stop-color="#ccd6e2"/>'
            . '</linearGradient></defs>'
            . '<rect x="0" y="0" width="520" height="312" rx="10" fill="url(#' . $gradientId . ')"/>'
            . '<rect x="11" y="11" width="498" height="290" rx="6" fill="#eff3f8"/>';
        // Rows and columns are laid out inside the 11..509 x 11..301 inner face
        // with an even 24px margin and even gaps, so no cell can overhang the
        // door body the way an eyeballed grid did on the first pass.
        foreach ([35, 130, 225] as $rowIndex => $rowY) {
            foreach ([35, 151, 267, 383] as $columnX) {
                $panel .= $rowIndex === 0
                    ? '<rect x="' . $columnX . '" y="' . $rowY . '" width="102" height="52" rx="4" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>'
                    : '<rect x="' . $columnX . '" y="' . $rowY . '" width="102" height="52" rx="4" fill="#f8fafc" stroke="#d3d9e0" stroke-width="2"/>';
            }
        }
        // The two courses the panel grid leaves between rows, so the door reads
        // as sections rather than as a table.
        $panel .= '<rect x="11" y="104" width="498" height="3" fill="rgba(3,18,43,.10)"/>'
            . '<rect x="11" y="199" width="498" height="3" fill="rgba(3,18,43,.10)"/>'
            . '<ellipse cx="260" cy="320" rx="240" ry="7" fill="rgba(3, 18, 43, .35)"/>'
            . '</svg>';

        if ($variant === 'door') {
            return '<div class="twins-brand-hero-art twins-brand-hero-art--door" aria-hidden="true">' . $panel . '</div>';
        }

        if ($variant === 'truck') {
            // The owned service-truck cutout, at the three widths
            // tools/build-owned-images.mjs generated for it. Its provenance
            // record allows home, careers and team; this variant is used on the
            // team hero only, which is inside that allowance.
            $src = static fn(string $key): string => htmlspecialchars($experience->asset($key), ENT_QUOTES, 'UTF-8');
            return '<div class="twins-brand-hero-art twins-brand-hero-art--truck" aria-hidden="true">'
                . $panel
                . '<img class="twins-brand-hero-art__truck"'
                . ' src="' . $src('truck-webp-880') . '"'
                . ' srcset="' . $src('truck-webp-320') . ' 320w, ' . $src('truck-webp-880') . ' 880w"'
                . ' sizes="(min-width: 1101px) 32vw, 360px"'
                . ' width="880" height="517" alt="" aria-hidden="true" loading="lazy" decoding="async">'
                . '</div>';
        }

        $twin = static function (string $key, string $side, int $width, int $height) use ($experience): string {
            return '<img class="twins-brand-hero-art__twin twins-brand-hero-art__twin--' . $side . '"'
                . ' src="' . htmlspecialchars($experience->asset($key), ENT_QUOTES, 'UTF-8') . '"'
                . ' width="' . $width . '" height="' . $height . '"'
                . ' alt="" aria-hidden="true" loading="lazy" decoding="async">';
        };

        $figures = $variant === 'crew'
            ? $twin('twin-left', 'left', 196, 534) . $twin('twin-right', 'right', 297, 538)
            : $twin('twin-right', 'right', 297, 538);

        return '<div class="twins-brand-hero-art twins-brand-hero-art--' . $variant . '" aria-hidden="true">'
            . $panel . $figures . '</div>';
    }
}
