<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

final class Twins_Overhaul_Brand_Asset_Refusal extends RuntimeException
{
    public int $response;

    public function __construct(string $message, int $response)
    {
        parent::__construct($message);
        $this->response = $response;
    }
}

function twins_overhaul_brand_asset_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function twins_overhaul_refuse_route(string $reason): void
{
    throw new Twins_Overhaul_Brand_Asset_Refusal($reason, 503);
}

function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1): bool
{
    unset($hook, $callback, $priority, $acceptedArgs);
    return true;
}

function add_filter($hook, $callback, $priority = 10, $acceptedArgs = 1): bool
{
    unset($hook, $callback, $priority, $acceptedArgs);
    return true;
}

function twins_overhaul_brand_asset_expect_refusal(callable $operation, string $label, string $message = ''): void
{
    $refusal = null;
    try {
        $operation();
    } catch (Twins_Overhaul_Brand_Asset_Refusal $exception) {
        $refusal = $exception;
    }
    twins_overhaul_brand_asset_assert(
        $refusal instanceof Twins_Overhaul_Brand_Asset_Refusal,
        $label . ' did not use the fixed refusal path'
    );
    twins_overhaul_brand_asset_assert($refusal->response === 503, $label . ' refusal did not use 503');
    if ($message !== '') {
        twins_overhaul_brand_asset_assert($refusal->getMessage() === $message, $label . ' refusal reason changed');
    }
}

function twins_overhaul_brand_asset_remove_tree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @chmod($path, 0600);
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        twins_overhaul_brand_asset_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "STAGING_OVERHAUL_RENDERERS_SOURCE_MISSING\n");
    exit(2);
}

$temporaryRoot = sys_get_temp_dir() . '/twins-overhaul-brand-assets-' . bin2hex(random_bytes(8));
$rendererDirectory = $temporaryRoot . '/staging-safety/mu-plugins/twins-staging-overhaul';
$assetRoot = $temporaryRoot . '/twins-brand-experience';
$cssPath = $assetRoot . '/assets/css/twins-brand.css';
$scriptPath = $assetRoot . '/assets/js/twins-brand.js';
$rendererPath = $rendererDirectory . '/renderers.php';

try {
    twins_overhaul_brand_asset_assert(@mkdir(dirname($cssPath), 0700, true), 'temporary CSS directory was not created');
    twins_overhaul_brand_asset_assert(@mkdir(dirname($scriptPath), 0700, true), 'temporary script directory was not created');
    twins_overhaul_brand_asset_assert(@mkdir($rendererDirectory, 0700, true), 'temporary renderer directory was not created');
    twins_overhaul_brand_asset_assert(copy($argv[1], $rendererPath), 'renderer source was not copied');

    $cssBytes = "body{color:#123456}\n";
    $scriptBytes = "window.TwinsBrandTest=true;\n";
    twins_overhaul_brand_asset_assert(file_put_contents($cssPath, $cssBytes) === strlen($cssBytes), 'temporary CSS was not written');
    twins_overhaul_brand_asset_assert(file_put_contents($scriptPath, $scriptBytes) === strlen($scriptBytes), 'temporary script was not written');

    require $rendererPath;

    twins_overhaul_brand_asset_assert(
        twins_overhaul_brand_asset_version('assets/css/twins-brand.css') === substr(hash('sha256', $cssBytes), 0, 16),
        'CSS version is not the independent SHA-256 prefix'
    );
    twins_overhaul_brand_asset_assert(
        twins_overhaul_brand_asset_version('assets/js/twins-brand.js') === substr(hash('sha256', $scriptBytes), 0, 16),
        'script version is not the independent SHA-256 prefix'
    );

    foreach (['', '../assets/css/twins-brand.css', '/assets/css/twins-brand.css', 'assets/css/twins-brand.css?x=1'] as $unsafePath) {
        twins_overhaul_brand_asset_expect_refusal(
            static fn(): string => twins_overhaul_brand_asset_version($unsafePath),
            'unsafe logical path ' . $unsafePath,
            'brand asset path is outside the fixed allowlist.'
        );
    }

    unlink($scriptPath);
    clearstatcache(true, $scriptPath);
    twins_overhaul_brand_asset_expect_refusal(
        static fn(): string => twins_overhaul_brand_asset_version('assets/js/twins-brand.js'),
        'missing fixed script',
        'brand asset is not a bounded regular file.'
    );

    file_put_contents($cssPath, '');
    clearstatcache(true, $cssPath);
    twins_overhaul_brand_asset_expect_refusal(
        static fn(): string => twins_overhaul_brand_asset_version('assets/css/twins-brand.css'),
        'empty fixed stylesheet',
        'brand asset size is outside the fixed boundary.'
    );

    $oversized = fopen($cssPath, 'c+b');
    twins_overhaul_brand_asset_assert(is_resource($oversized), 'oversized boundary fixture was not opened');
    twins_overhaul_brand_asset_assert(ftruncate($oversized, 2097153), 'oversized boundary fixture was not created');
    fclose($oversized);
    clearstatcache(true, $cssPath);
    twins_overhaul_brand_asset_expect_refusal(
        static fn(): string => twins_overhaul_brand_asset_version('assets/css/twins-brand.css'),
        'oversized fixed stylesheet',
        'brand asset size is outside the fixed boundary.'
    );

    unlink($cssPath);
    $outsidePath = $temporaryRoot . '/outside.css';
    file_put_contents($outsidePath, "body{}\n");
    twins_overhaul_brand_asset_assert(symlink($outsidePath, $cssPath), 'symlink boundary fixture was not created');
    clearstatcache(true, $cssPath);
    twins_overhaul_brand_asset_expect_refusal(
        static fn(): string => twins_overhaul_brand_asset_version('assets/css/twins-brand.css'),
        'symlinked fixed stylesheet',
        'brand asset is not a bounded regular file.'
    );

    unlink($cssPath);
    file_put_contents($cssPath, "body{display:block}\n");
    chmod($cssPath, 0000);
    clearstatcache(true, $cssPath);
    if (!is_readable($cssPath)) {
        twins_overhaul_brand_asset_expect_refusal(
            static fn(): string => twins_overhaul_brand_asset_version('assets/css/twins-brand.css'),
            'unreadable fixed stylesheet',
            'brand asset hash is unavailable.'
        );
    }
    chmod($cssPath, 0600);
} finally {
    twins_overhaul_brand_asset_remove_tree($temporaryRoot);
}

echo "STAGING_OVERHAUL_BRAND_ASSET_HARNESS_OK\n";
