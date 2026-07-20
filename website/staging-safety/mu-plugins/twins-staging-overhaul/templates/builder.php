<?php
/**
 * Network-closed garage-door builder for the private staging preview.
 */

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit;
}

/**
 * Validate one fixed local image record.
 *
 * @param mixed $image Candidate image record.
 * @return bool
 */
function twins_overhaul_builder_image_is_valid($image): bool {
    return is_array($image)
        && array_keys($image) === array('src', 'width', 'height', 'alt')
        && is_string($image['src'])
        && preg_match('~^/wp-content/mu-plugins/twins-staging-assets/clopay/[a-f0-9]{2}/[a-f0-9]{64}\.(?:webp|jpg)$~D', $image['src']) === 1
        && is_int($image['width'])
        && $image['width'] > 0
        && $image['width'] <= 8192
        && is_int($image['height'])
        && $image['height'] > 0
        && $image['height'] <= 8192
        && ($image['width'] * $image['height']) <= 40000000
        && is_string($image['alt'])
        && strlen($image['alt']) <= 128;
}

/**
 * Return the only approved frozen builder catalog.
 *
 * @return array
 */
function twins_overhaul_builder_catalog(): array {
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $path = dirname(__DIR__, 2) . '/twins-staging-assets/clopay-products.json';
    $stat = @lstat($path);
    if (!is_array($stat) || is_link($path) || !is_file($path)) {
        twins_overhaul_refuse_route('builder catalog is not a bounded regular file.');
    }
    $size = @filesize($path);
    if (!is_int($size) || $size < 2 || $size > 2097152) {
        twins_overhaul_refuse_route('builder catalog is outside the fixed byte boundary.');
    }
    $bytes = @file_get_contents($path);
    if (!is_string($bytes) || strlen($bytes) !== $size || preg_match('~(?:https?:|//www\.|clopaydoor\.com)~i', $bytes)) {
        twins_overhaul_refuse_route('builder catalog contains a remote or unreadable value.');
    }
    $hash = hash('sha256', $bytes);
    $expectedHash = 'ce960f1267327183719192d80d249f31c903a24e5fc6471992bed00dccda74f5';
    if (!hash_equals($expectedHash, $hash)) {
        twins_overhaul_refuse_route('builder catalog digest does not match the approved catalog.');
    }

    try {
        $decoded = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        twins_overhaul_refuse_route('builder catalog JSON is malformed.');
    }

    $order = array('330','320','30','29','240','26','170','340','12','16','290','370','250','380','11','27','291','8','10','25','9','13','23');
    if (
        !is_array($decoded)
        || array_keys($decoded) !== array('schemaVersion', 'productOrder', 'products')
        || ($decoded['schemaVersion'] ?? null) !== 1
        || ($decoded['productOrder'] ?? null) !== $order
        || !isset($decoded['products'])
        || !is_array($decoded['products'])
        || count($decoded['products']) !== count($order)
    ) {
        twins_overhaul_refuse_route('builder catalog top-level schema is noncanonical.');
    }

    $optionFields = array('designs', 'colors', 'windows', 'glass', 'hardware', 'gallery');
    foreach ($decoded['products'] as $index => $product) {
        if (
            !is_array($product)
            || ($product['id'] ?? null) !== $order[$index]
            || !is_string($product['title'] ?? null)
            || $product['title'] === ''
            || strlen($product['title']) > 128
            || !twins_overhaul_builder_image_is_valid($product['showcase'] ?? null)
        ) {
            twins_overhaul_refuse_route('builder product schema is noncanonical.');
        }
        foreach ($optionFields as $field) {
            if (!isset($product[$field]) || !is_array($product[$field])) {
                twins_overhaul_refuse_route('builder option family is missing.');
            }
            foreach ($product[$field] as $optionIndex => $option) {
                $prefix = $field === 'designs' ? 'design' : ($field === 'colors' ? 'color' : ($field === 'windows' ? 'window' : ($field === 'gallery' ? 'gallery' : $field)));
                if (
                    !is_array($option)
                    || ($option['id'] ?? null) !== $prefix . '-' . $optionIndex
                    || !is_string($option['title'] ?? null)
                    || $option['title'] === ''
                    || strlen($option['title']) > 128
                    || !twins_overhaul_builder_image_is_valid($option['image'] ?? null)
                ) {
                    twins_overhaul_refuse_route('builder option schema is noncanonical.');
                }
            }
        }
    }

    $catalog = $decoded;
    return $catalog;
}

/**
 * Render the staging-only, same-document builder shell.
 *
 * @param array $context Internal request context.
 * @return string
 */
function twins_overhaul_render_builder(array $context): string {
    $region = twins_overhaul_resolve_context($context);
    $catalog = twins_overhaul_builder_catalog();
    $json = wp_json_encode(
        $catalog,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if (!is_string($json) || $json === '') {
        twins_overhaul_refuse_route('builder catalog cannot be embedded safely.');
    }

    $stages = array(
        'collection' => 'Collection',
        'design' => 'Design',
        'color' => 'Color',
        'windows' => 'Windows',
        'glass' => 'Glass',
        'hardware' => 'Hardware (optional)',
        'summary' => 'Summary',
        'contact-preview' => 'Contact Preview',
    );
    $markup = '<div id="twins-overhaul-main" class="twins-brand-page twins-overhaul-main twins-builder-page" tabindex="-1">';
    $markup .= '<section class="twins-builder-hero"><div class="twins-overhaul-shell"><p class="twins-overhaul-eyebrow">Official Clopay dealer</p><h1>Design your garage door with Twins</h1><p>Explore Clopay collections, pick the panel style, color, windows, and hardware you like, and share your design with the Twins team for a real quote.</p></div></section>';
    $markup .= '<section class="twins-overhaul-section twins-builder"><div class="twins-overhaul-shell">';
    $markup .= '<p class="twins-builder__truth"><strong>Manufacturer reference only.</strong> Images show product, panel, color, window, glass, hardware, or inspiration references. Your selected options are listed separately, and Twins will confirm the final appearance before ordering.</p>';
    $markup .= '<p class="twins-builder__truth" role="note">This private staging preview does not submit or store lead information. Selections remain in this page until you leave or reload.</p>';
    $markup .= '<div class="twins-builder__fallback" data-builder-fallback><h2>Builder steps</h2><ol>';
    foreach ($stages as $stage) {
        $markup .= '<li>' . esc_html($stage) . '</li>';
    }
    $markup .= '</ol><p>JavaScript adds the interactive preview. You can still call <a href="tel:' . esc_attr($region['tel']) . '">' . esc_html($region['phone']) . '</a> to review door choices.</p></div>';
    $markup .= '<div class="twins-builder__app" data-twins-overhaul-builder data-builder-region="' . esc_attr($region['key']) . '" data-builder-phone="' . esc_attr($region['phone']) . '" data-builder-tel="' . esc_attr($region['tel']) . '" hidden>';
    $markup .= '<nav class="twins-builder__progress" aria-label="Door builder progress"><ol>';
    foreach ($stages as $target => $stage) {
        $markup .= '<li><button type="button" data-builder-step-target="' . esc_attr($target) . '"' . ($target === 'collection' ? ' aria-current="step"' : '') . '>' . esc_html($stage) . '</button></li>';
    }
    $markup .= '</ol></nav><section class="twins-builder__stage" data-builder-stage aria-live="polite"></section>';
    $markup .= '<p class="twins-builder__status" data-builder-status role="status" aria-live="polite"></p>';
    $markup .= '<p class="twins-builder__copy-label">Use <strong>Copy Summary</strong> at the Summary step to copy only the choices shown on screen.</p>';
    $markup .= '<aside class="twins-builder__contact-preview" data-builder-contact-preview><h2>Contact Preview</h2><p>No information is collected here. Call Twins at <a href="tel:' . esc_attr($region['tel']) . '">' . esc_html($region['phone']) . '</a> when you are ready to discuss the saved summary.</p></aside>';
    $markup .= '</div><script type="application/json" data-twins-builder-catalog>' . $json . '</script>';
    $markup .= '</div></section></div>';
    return $markup;
}
