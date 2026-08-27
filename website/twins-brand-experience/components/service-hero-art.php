<?php
declare(strict_types=1);

/**
 * Decorative hero artwork for service pages: an animated rendition of the
 * part each page is about, or real product photography for the openers page.
 * Returns '' for pages without a matching part.
 *
 * THE TWO LABELS ON THE SPRING ART ARE NOT A CONTRAST DEFECT, and this note
 * exists because they have been reported as one. An automated pass read the
 * <text> elements' inherited CSS `color` -- white, because the hero band is
 * navy -- and reported "TWINS GARAGE" as white on gold at 1.39:1. SVG does not
 * paint with `color`. It paints with `fill`, and `color` on an <svg><text> is
 * inert unless something asks for `currentColor`; nothing here does.
 *
 * Measured 2026-08-27 from rendered pixels at 2x, with the glyphs hidden to
 * sample the ground under them, at four phases of the twinsSpringFlex
 * animation (-0s, -0.85s, -1.7s, -2.55s), identical at every phase:
 *
 *   "TWINS GARAGE"  fill rgb(28,36,48)   worst ground rgb(192,148,44)  5.59:1
 *   "218 X 2 X 32"  fill rgb(232,229,218) worst ground rgb(66,69,75)   7.62:1
 *
 * The worst ground is the coil pattern's dark stripe, not the flat gold, so
 * 5.59:1 is the floor rather than the average. Both clear the 4.5 body floor,
 * and the art carries no reading obligation anyway: the wrapper is
 * aria-hidden="true" and the svg is focusable="false", so the labels convey
 * nothing that is not also in the page copy. Decorative AND legible; there is
 * nothing here to restyle.
 */
function twins_brand_service_hero_art(string $path, $experience): string
{
    // Market pages carry their route prefix (/wi/..., /ky/..., /il/...);
    // the artwork is keyed by the market-agnostic service path.
    $path = preg_replace('~^/(?:wi|ky|il)/~', '/', $path);

    $svgOpen = '<div class="twins-brand-service-hero-art" aria-hidden="true">';
    $svgClose = '</div>';

    if ($path === '/garage-door-spring-repair/') {
        return $svgOpen . <<<'SVG'
<svg viewBox="0 0 300 380" focusable="false">
  <defs>
    <pattern id="twinsCoilGold" width="12" height="11" patternUnits="userSpaceOnUse">
      <rect width="12" height="11" fill="#ffc641"/>
      <rect y="8" width="12" height="3" fill="rgba(60,42,0,.32)"/>
      <rect y="0" width="12" height="1.4" fill="rgba(255,255,255,.35)"/>
    </pattern>
    <pattern id="twinsCoilBlack" width="12" height="11" patternUnits="userSpaceOnUse">
      <rect width="12" height="11" fill="#23272d"/>
      <rect y="8" width="12" height="3" fill="rgba(0,0,0,.55)"/>
      <rect y="0" width="12" height="1.4" fill="rgba(255,255,255,.14)"/>
    </pattern>
  </defs>
  <g class="twins-art-spring twins-art-spring--a">
    <rect x="52" y="30" width="18" height="14" rx="3" fill="#8a6d1d"/>
    <rect x="40" y="42" width="42" height="300" rx="9" fill="url(#twinsCoilGold)" stroke="#b98f16" stroke-width="2"/>
    <text x="61" y="192" transform="rotate(90 61 192)" text-anchor="middle" font-size="19" font-weight="800" letter-spacing="3" fill="#1c2430" font-family="inherit">TWINS GARAGE</text>
    <path d="M36 342 L86 342 L78 366 L44 366 Z" fill="#e0a911"/>
    <rect x="52" y="366" width="18" height="10" rx="2" fill="#8a6d1d"/>
  </g>
  <g class="twins-art-spring twins-art-spring--b">
    <rect x="196" y="70" width="14" height="12" rx="3" fill="#4a4f57"/>
    <rect x="186" y="80" width="34" height="252" rx="8" fill="url(#twinsCoilBlack)" stroke="#0e1013" stroke-width="2"/>
    <text x="203" y="206" transform="rotate(90 203 206)" text-anchor="middle" font-size="14" font-weight="700" letter-spacing="2" fill="#e8e5da" font-family="inherit">218 X 2 X 32</text>
    <path d="M182 332 L224 332 L218 352 L188 352 Z" fill="#3a3f46"/>
  </g>
  <ellipse cx="63" cy="378" rx="52" ry="5" fill="rgba(3,18,43,.4)"/>
  <ellipse cx="203" cy="357" rx="40" ry="4" fill="rgba(3,18,43,.4)"/>
</svg>
SVG . $svgClose;
    }

    if ($path === '/garage-door-repair/' || $path === '/garage-door-cable-repair/') {
        $frayed = $path === '/garage-door-cable-repair/'
            ? '<path class="twins-art-cable-loose" d="M208 150 q 14 40 -6 78 q -10 22 4 40" fill="none" stroke="#9aa7b6" stroke-width="3" stroke-linecap="round"/>'
            : '';
        return $svgOpen . <<<SVG
<svg viewBox="0 0 320 360" focusable="false">
  <rect x="20" y="24" width="280" height="16" rx="4" fill="#33507a"/>
  <g class="twins-art-drum">
    <circle cx="160" cy="110" r="72" fill="#c3ccd6" stroke="#7f8b99" stroke-width="4"/>
    <circle cx="160" cy="110" r="58" fill="none" stroke="#93a0af" stroke-width="3" stroke-dasharray="10 7"/>
    <circle cx="160" cy="110" r="42" fill="none" stroke="#93a0af" stroke-width="3" stroke-dasharray="8 6"/>
    <circle cx="160" cy="110" r="16" fill="#5d6a79"/>
    <rect x="150" y="46" width="20" height="8" rx="3" fill="#7f8b99"/>
    <rect x="150" y="166" width="20" height="8" rx="3" fill="#7f8b99"/>
    <rect x="96" y="100" width="8" height="20" rx="3" fill="#7f8b99"/>
    <rect x="216" y="100" width="8" height="20" rx="3" fill="#7f8b99"/>
  </g>
  <path class="twins-art-cable" d="M226 132 q 10 60 -4 118 q -6 34 6 74" fill="none" stroke="#dfe6ee" stroke-width="5" stroke-linecap="round" stroke-dasharray="14 9"/>
  {$frayed}
  <ellipse cx="160" cy="348" rx="120" ry="7" fill="rgba(3,18,43,.4)"/>
</svg>
SVG . $svgClose;
    }

    if ($path === '/garage-door-opener-repair/') {
        return $svgOpen . <<<'SVG'
<svg viewBox="0 0 360 300" focusable="false">
  <rect x="10" y="52" width="250" height="12" rx="4" fill="#8f9aa8"/>
  <path class="twins-art-chain" d="M14 58 H 256" fill="none" stroke="#dfe6ee" stroke-width="4" stroke-dasharray="10 8"/>
  <g class="twins-art-trolley">
    <rect x="40" y="42" width="46" height="32" rx="8" fill="#ffc641" stroke="#b98f16" stroke-width="2.5"/>
    <path d="M63 74 L 108 168" fill="none" stroke="#c3ccd6" stroke-width="7" stroke-linecap="round"/>
  </g>
  <g>
    <rect x="252" y="26" width="96" height="66" rx="14" fill="#0d2d54" stroke="#33507a" stroke-width="3"/>
    <rect x="266" y="42" width="42" height="30" rx="6" fill="#123a6b"/>
    <circle class="twins-art-opener-light" cx="330" cy="58" r="9" fill="#ffe9a8"/>
  </g>
  <rect x="60" y="168" width="240" height="112" rx="8" fill="#eef1f5" stroke="#d3d9e0" stroke-width="3"/>
  <line x1="60" y1="206" x2="300" y2="206" stroke="#d3d9e0" stroke-width="3"/>
  <line x1="60" y1="244" x2="300" y2="244" stroke="#d3d9e0" stroke-width="3"/>
  <ellipse cx="180" cy="292" rx="140" ry="7" fill="rgba(3,18,43,.4)"/>
</svg>
SVG . $svgClose;
    }

    if ($path === '/emergency-garage-services/') {
        return $svgOpen . <<<'SVG'
<svg viewBox="0 0 360 300" focusable="false">
  <defs>
    <pattern id="twinsCoilEmg" width="11" height="12" patternUnits="userSpaceOnUse">
      <rect width="11" height="12" fill="#ffc641"/>
      <rect x="8" width="3" height="12" fill="rgba(60,42,0,.32)"/>
      <rect x="0" width="1.4" height="12" fill="rgba(255,255,255,.35)"/>
    </pattern>
  </defs>
  <rect x="16" y="120" width="328" height="10" rx="4" fill="#33507a"/>
  <g class="twins-art-break twins-art-break--l">
    <rect x="26" y="100" width="140" height="38" rx="8" fill="url(#twinsCoilEmg)" stroke="#b98f16" stroke-width="2.5"/>
    <path d="M166 100 l 12 10 l -12 9 l 12 9 l -12 10" fill="none" stroke="#b98f16" stroke-width="3"/>
  </g>
  <g class="twins-art-break twins-art-break--r">
    <rect x="206" y="100" width="128" height="38" rx="8" fill="url(#twinsCoilEmg)" stroke="#b98f16" stroke-width="2.5"/>
    <path d="M206 100 l -12 10 l 12 9 l -12 9 l 12 10" fill="none" stroke="#b98f16" stroke-width="3"/>
  </g>
  <g class="twins-art-warn">
    <path d="M180 22 L 214 78 L 146 78 Z" fill="#ffc641" stroke="#0d2d54" stroke-width="4" stroke-linejoin="round"/>
    <rect x="176.5" y="40" width="7" height="20" rx="3" fill="#0d2d54"/>
    <circle cx="180" cy="68" r="4" fill="#0d2d54"/>
  </g>
  <text x="180" y="182" text-anchor="middle" font-size="17" font-weight="800" letter-spacing="2" fill="#9db4d4" font-family="inherit">BROKEN SPRING? STAY CLEAR.</text>
  <ellipse cx="180" cy="212" rx="150" ry="6" fill="rgba(3,18,43,.4)"/>
</svg>
SVG . $svgClose;
    }

    if ($path === '/garage-door-tune-up/') {
        return $svgOpen . <<<'SVG'
<svg viewBox="0 0 240 360" focusable="false">
  <rect x="52" y="16" width="14" height="328" rx="5" fill="#8f9aa8"/>
  <rect x="88" y="16" width="8" height="328" rx="4" fill="#5d6a79"/>
  <g class="twins-art-roller-glide">
    <rect x="66" y="164" width="58" height="10" rx="5" fill="#c3ccd6"/>
    <g class="twins-art-roller">
      <circle cx="59" cy="169" r="26" fill="#e8edf3" stroke="#93a0af" stroke-width="4"/>
      <circle cx="59" cy="169" r="9" fill="#5d6a79"/>
      <rect x="55.5" y="146" width="7" height="10" rx="3" fill="#93a0af"/>
      <rect x="55.5" y="182" width="7" height="10" rx="3" fill="#93a0af"/>
      <rect x="36" y="165.5" width="10" height="7" rx="3" fill="#93a0af"/>
      <rect x="72" y="165.5" width="10" height="7" rx="3" fill="#93a0af"/>
    </g>
    <rect x="124" y="150" width="76" height="38" rx="7" fill="#eef1f5" stroke="#d3d9e0" stroke-width="3"/>
  </g>
  <ellipse cx="110" cy="352" rx="86" ry="6" fill="rgba(3,18,43,.4)"/>
</svg>
SVG . $svgClose;
    }

    if ($path === '/garage-weatherstripping-repair/') {
        return $svgOpen . <<<'SVG'
<svg viewBox="0 0 340 260" focusable="false">
  <rect x="30" y="20" width="280" height="150" rx="8" fill="#eef1f5" stroke="#d3d9e0" stroke-width="3"/>
  <line x1="30" y1="70" x2="310" y2="70" stroke="#d3d9e0" stroke-width="3"/>
  <line x1="30" y1="120" x2="310" y2="120" stroke="#d3d9e0" stroke-width="3"/>
  <path class="twins-art-seal" d="M30 170 q 8 26 24 26 h 232 q 16 0 24 -26 Z" fill="#23272d"/>
  <rect x="10" y="216" width="320" height="10" rx="4" fill="#33507a"/>
  <g class="twins-art-drip">
    <path d="M60 190 q 5 10 0 16 q -6 -6 0 -16" fill="#9db4d4"/>
  </g>
  <ellipse cx="170" cy="248" rx="150" ry="6" fill="rgba(3,18,43,.4)"/>
</svg>
SVG . $svgClose;
    }

    if ($path === '/garage-door-openers/') {
        $img6690 = htmlspecialchars($experience->asset('opener-6690l'), ENT_QUOTES, 'UTF-8');
        $img98022 = htmlspecialchars($experience->asset('opener-98022'), ENT_QUOTES, 'UTF-8');
        return '<div class="twins-brand-service-hero-art twins-brand-service-hero-art--openers">'
            . '<figure class="twins-brand-opener-card">'
            . '<img src="' . $img6690 . '" width="800" height="800" alt="LiftMaster 6690L smart belt-drive garage door opener with remote, control panel, and keypad" loading="lazy" decoding="async">'
            . '<figcaption>LiftMaster 6690L + accessories</figcaption>'
            . '</figure>'
            . '<figure class="twins-brand-opener-card twins-brand-opener-card--wall">'
            . '<img src="' . $img98022 . '" width="420" height="420" alt="LiftMaster 98022 wall-mount garage door opener with accessories" loading="lazy" decoding="async">'
            . '<figcaption>LiftMaster 98022 wall mount</figcaption>'
            . '</figure>'
            . '</div>';
    }

    // --- The five service pages that had no part to draw --------------------
    // Owner report 2026-08-26, "no pic at top". Eight of the thirteen service
    // pages already opened on the part they are about; installation, the
    // services index, the two plan pages and property management opened on an
    // empty navy field. Each of these is the same fixed inline SVG in the same
    // palette as the eight above, drawn from the same door vocabulary, and each
    // shows the thing the page actually sells rather than a generic icon.

    if ($path === '/garage-door-installation/') {
        // A new section being set onto the two already hung.
        return $svgOpen . <<<'SVG'
<svg viewBox="0 0 320 340" focusable="false">
  <rect x="14" y="26" width="292" height="12" rx="4" fill="#33507a"/>
  <g class="twins-art-install-panel">
    <rect x="46" y="52" width="228" height="62" rx="6" fill="#f8fafc" stroke="#c3ccd6" stroke-width="3"/>
    <rect x="66" y="68" width="86" height="30" rx="3" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
    <rect x="168" y="68" width="86" height="30" rx="3" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
  </g>
  <rect x="46" y="130" width="228" height="62" rx="6" fill="#eef1f5" stroke="#d3d9e0" stroke-width="3"/>
  <rect x="66" y="146" width="86" height="30" rx="3" fill="#f8fafc" stroke="#d3d9e0" stroke-width="2"/>
  <rect x="168" y="146" width="86" height="30" rx="3" fill="#f8fafc" stroke="#d3d9e0" stroke-width="2"/>
  <rect x="46" y="200" width="228" height="62" rx="6" fill="#eef1f5" stroke="#d3d9e0" stroke-width="3"/>
  <rect x="66" y="216" width="86" height="30" rx="3" fill="#f8fafc" stroke="#d3d9e0" stroke-width="2"/>
  <rect x="168" y="216" width="86" height="30" rx="3" fill="#f8fafc" stroke="#d3d9e0" stroke-width="2"/>
  <rect x="30" y="52" width="12" height="216" rx="4" fill="#8f9aa8"/>
  <rect x="278" y="52" width="12" height="216" rx="4" fill="#8f9aa8"/>
  <rect x="24" y="268" width="272" height="10" rx="4" fill="#33507a"/>
  <ellipse cx="160" cy="330" rx="130" ry="6" fill="rgba(3,18,43,.4)"/>
</svg>
SVG . $svgClose;
    }

    if ($path === '/garage-door-services/') {
        // The wrench that covers all of them, over a door face.
        return $svgOpen . <<<'SVG'
<svg viewBox="0 0 320 330" focusable="false">
  <rect x="30" y="24" width="260" height="196" rx="8" fill="#eef1f5" stroke="#d3d9e0" stroke-width="3"/>
  <rect x="30" y="90" width="260" height="3" fill="#d3d9e0"/>
  <rect x="30" y="156" width="260" height="3" fill="#d3d9e0"/>
  <rect x="52" y="40" width="94" height="36" rx="3" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
  <rect x="174" y="40" width="94" height="36" rx="3" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
  <!-- The static rotation lives on an OUTER g, because the animated class
       carries a CSS transform and a CSS transform replaces the transform
       attribute on the same element rather than composing with it. The inline
       style is load-bearing too: the shared art rule sets `transform-box:
       fill-box` on every g in here, which re-resolves this rotation about the
       group's own bounding box on top of its explicit centre and slid the
       wrench off the bottom of the door. view-box with a 0 0 origin is the
       plain SVG behaviour this rotate() is written against. -->
  <g transform="rotate(-34 160 118)" style="transform-box: view-box; transform-origin: 0 0;">
    <g class="twins-art-wrench">
      <path d="M132 20 h 20 v 26 h 16 v -26 h 20 v 58 a 12 12 0 0 1 -12 12 h -32 a 12 12 0 0 1 -12 -12 z" fill="#ffc641" stroke="#b98f16" stroke-width="4" stroke-linejoin="round"/>
      <rect x="148" y="84" width="24" height="76" rx="10" fill="#ffc641" stroke="#b98f16" stroke-width="4"/>
      <circle cx="160" cy="186" r="30" fill="#ffc641" stroke="#b98f16" stroke-width="4"/>
      <circle cx="160" cy="186" r="14" fill="#eef1f5" stroke="#b98f16" stroke-width="4"/>
    </g>
  </g>
  <rect x="16" y="222" width="288" height="10" rx="4" fill="#33507a"/>
  <ellipse cx="160" cy="320" rx="132" ry="6" fill="rgba(3,18,43,.4)"/>
</svg>
SVG . $svgClose;
    }

    if ($path === '/maintenance-plans/' || $path === '/protection-plans/') {
        // The TwinShield badge: a shield over the door it covers.
        return $svgOpen . <<<'SVG'
<svg viewBox="0 0 300 340" focusable="false">
  <rect x="26" y="26" width="248" height="176" rx="8" fill="#eef1f5" stroke="#d3d9e0" stroke-width="3"/>
  <rect x="26" y="90" width="248" height="3" fill="#d3d9e0"/>
  <rect x="26" y="150" width="248" height="3" fill="#d3d9e0"/>
  <rect x="46" y="42" width="90" height="34" rx="3" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
  <rect x="164" y="42" width="90" height="34" rx="3" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
  <rect x="12" y="204" width="276" height="10" rx="4" fill="#33507a"/>
  <g class="twins-art-shield">
    <path d="M150 92 l 74 26 v 62 q 0 62 -74 96 q -74 -34 -74 -96 v -62 z" fill="#ffc641" stroke="#b98f16" stroke-width="5" stroke-linejoin="round"/>
    <path d="M116 194 l 24 26 l 46 -58" fill="none" stroke="#071c38" stroke-width="14" stroke-linecap="round" stroke-linejoin="round"/>
  </g>
  <ellipse cx="150" cy="330" rx="104" ry="6" fill="rgba(3,18,43,.4)"/>
</svg>
SVG . $svgClose;
    }

    if ($path === '/property-management-services/') {
        // A row of bays, which is what a property portfolio actually looks like.
        return $svgOpen . <<<'SVG'
<svg viewBox="0 0 360 300" focusable="false">
  <rect x="10" y="26" width="340" height="14" rx="5" fill="#33507a"/>
  <g class="twins-art-bay twins-art-bay--a">
    <rect x="22" y="52" width="96" height="176" rx="6" fill="#eef1f5" stroke="#d3d9e0" stroke-width="3"/>
    <rect x="36" y="66" width="68" height="26" rx="3" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
    <rect x="22" y="104" width="96" height="3" fill="#d3d9e0"/>
    <rect x="22" y="148" width="96" height="3" fill="#d3d9e0"/>
    <rect x="22" y="192" width="96" height="3" fill="#d3d9e0"/>
  </g>
  <g class="twins-art-bay twins-art-bay--b">
    <rect x="132" y="52" width="96" height="122" rx="6" fill="#f8fafc" stroke="#c3ccd6" stroke-width="3"/>
    <rect x="146" y="66" width="68" height="26" rx="3" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
    <rect x="132" y="104" width="96" height="3" fill="#d3d9e0"/>
    <rect x="132" y="148" width="96" height="3" fill="#d3d9e0"/>
    <rect x="132" y="176" width="96" height="52" fill="#0b2745"/>
    <rect x="146" y="196" width="68" height="6" rx="3" fill="rgba(255,198,65,.55)"/>
  </g>
  <g class="twins-art-bay twins-art-bay--c">
    <rect x="242" y="52" width="96" height="176" rx="6" fill="#eef1f5" stroke="#d3d9e0" stroke-width="3"/>
    <rect x="256" y="66" width="68" height="26" rx="3" fill="#c8dcee" stroke="#a9c3da" stroke-width="2"/>
    <rect x="242" y="104" width="96" height="3" fill="#d3d9e0"/>
    <rect x="242" y="148" width="96" height="3" fill="#d3d9e0"/>
    <rect x="242" y="192" width="96" height="3" fill="#d3d9e0"/>
  </g>
  <rect x="10" y="228" width="340" height="10" rx="4" fill="#33507a"/>
  <ellipse cx="180" cy="292" rx="160" ry="6" fill="rgba(3,18,43,.4)"/>
</svg>
SVG . $svgClose;
    }

    return '';
}
