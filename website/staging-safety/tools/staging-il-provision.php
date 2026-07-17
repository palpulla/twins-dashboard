<?php

/**
 * Fixed, fail-closed provisioner for the private Illinois staging subsite.
 *
 * This file is intentionally a WP-CLI eval-file tool. It grants no production
 * authority and accepts no caller-selected site identity or content manifest.
 */

if (!function_exists('twins_staging_il_backup_digest')) {
    function twins_staging_il_backup_digest(): string
    {
        $path = '/home/customer/staging-safety/before-full-overhaul-20260714.sql.gz';
        clearstatcache(true, $path);
        if (realpath($path) !== $path || !is_file($path) || is_link($path)) {
            throw new RuntimeException('STAGING_IL_REFUSED: fixed backup is not a regular file');
        }
        $before = lstat($path);
        if (!is_array($before)
            || (($before['mode'] ?? 0) & 0777) !== 0600
            || (int) ($before['size'] ?? -1) !== 39430462
            || !function_exists('posix_geteuid')
            || (int) ($before['uid'] ?? -1) !== (int) posix_geteuid()) {
            throw new RuntimeException('STAGING_IL_REFUSED: fixed backup metadata mismatch');
        }
        $digest = hash_file('sha256', $path);
        if (!is_string($digest)) {
            throw new RuntimeException('STAGING_IL_REFUSED: fixed backup could not be hashed');
        }
        clearstatcache(true, $path);
        $after = lstat($path);
        foreach (array('dev', 'ino', 'mode', 'nlink', 'uid', 'gid', 'size', 'mtime', 'ctime') as $field) {
            if (!is_array($after) || ($before[$field] ?? null) !== ($after[$field] ?? null)) {
                throw new RuntimeException('STAGING_IL_REFUSED: fixed backup changed while hashing');
            }
        }
        return $digest;
    }
}

if (!function_exists('twins_staging_il_filesystem_residue')) {
    function twins_staging_il_filesystem_residue(): array
    {
        $fixed = array(
            'uploads/sites/5' => WP_CONTENT_DIR . '/uploads/sites/5',
            'blogs.dir/5' => WP_CONTENT_DIR . '/blogs.dir/5',
        );
        $found = array();
        foreach ($fixed as $label => $path) {
            if (file_exists($path) || is_link($path)) {
                $found[] = $label;
            }
        }
        return $found;
    }
}

if (!function_exists('twins_staging_il_htaccess_digest')) {
    function twins_staging_il_htaccess_digest(): string
    {
        $path = ABSPATH . '.htaccess';
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('STAGING_IL_REFUSED: .htaccess is not a regular file');
        }
        $digest = hash_file('sha256', $path);
        if (!is_string($digest)) {
            throw new RuntimeException('STAGING_IL_REFUSED: .htaccess could not be hashed');
        }
        return $digest;
    }
}

if (!function_exists('twins_staging_il_runtime_evidence')) {
    function twins_staging_il_runtime_evidence(): array
    {
        global $wpdb, $wp_version;
        $muRoot = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
        $pluginRoot = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';
        $safetyPath = $muRoot . '/twins-staging-safety.php';
        $overhaulPath = $muRoot . '/twins-staging-overhaul.php';
        $muFiles = glob($muRoot . '/*.php');
        $muFiles = is_array($muFiles) ? array_values(array_map('basename', $muFiles)) : array();
        sort($muFiles, SORT_STRING);

        $dropins = array();
        foreach (array('advanced-cache.php', 'object-cache.php', 'db.php', 'sunrise.php') as $name) {
            $path = WP_CONTENT_DIR . '/' . $name;
            if (file_exists($path) || is_link($path)) {
                $dropins[] = $name;
            }
        }

        $normalLoaded = false;
        $pluginPrefix = rtrim(str_replace('\\', '/', $pluginRoot), '/') . '/';
        $themePrefix = rtrim(str_replace('\\', '/', WP_CONTENT_DIR . '/themes'), '/') . '/';
        foreach (get_included_files() as $file) {
            $normalized = str_replace('\\', '/', (string) $file);
            if (str_starts_with($normalized, $pluginPrefix) || str_starts_with($normalized, $themePrefix)) {
                $normalLoaded = true;
                break;
            }
        }

        $safetyDigest = (!is_link($safetyPath) && is_file($safetyPath))
            ? hash_file('sha256', $safetyPath)
            : false;
        $overhaulDigest = (!is_link($overhaulPath) && is_file($overhaulPath))
            ? hash_file('sha256', $overhaulPath)
            : false;
        $astraPath = WP_CONTENT_DIR . '/themes/astra/style.css';
        $astraTheme = function_exists('wp_get_theme') ? wp_get_theme('astra') : null;
        $installerOverridePath = WP_CONTENT_DIR . '/install.php';

        return array(
            'valid' => true,
            'cli' => defined('WP_CLI') && WP_CLI && class_exists('WP_CLI'),
            'wpVersion' => (string) $wp_version,
            'phpMajor' => PHP_MAJOR_VERSION,
            'phpMinor' => PHP_MINOR_VERSION,
            'databaseVersion' => is_object($wpdb) && method_exists($wpdb, 'db_version') ? (string) $wpdb->db_version() : '',
            'safetyDigest' => is_string($safetyDigest) ? $safetyDigest : '',
            'overhaulDigest' => is_string($overhaulDigest) ? $overhaulDigest : '',
            'muPhpFiles' => $muFiles,
            'normalPluginOrThemeLoaded' => $normalLoaded,
            'astraAvailable' => is_file($astraPath) && !is_link($astraPath),
            'astraAllowed' => is_object($astraTheme)
                && method_exists($astraTheme, 'is_allowed')
                && $astraTheme->is_allowed('network'),
            'installerOverrideAbsent' => !file_exists($installerOverridePath) && !is_link($installerOverridePath),
            'locale' => function_exists('get_locale') ? (string) get_locale() : '',
            'dropins' => $dropins,
        );
    }
}

/** @return array<int, string> */
function twins_staging_il_frontend_tables(): array
{
    return array(
        'wp8y_5_gf_draft_submissions',
        'wp8y_5_gf_entry',
        'wp8y_5_gf_entry_meta',
        'wp8y_5_gf_entry_notes',
        'wp8y_5_gf_form',
        'wp8y_5_gf_form_meta',
        'wp8y_5_gf_form_revisions',
        'wp8y_5_gf_form_view',
    );
}

/** @return array<string, array{bytes: int, sha256: string}> */
function twins_staging_il_frontend_table_manifest(): array
{
    return array(
        'wp8y_5_gf_draft_submissions' => array(
            'bytes' => 561,
            'sha256' => '463ce7dfa4f94e523bedc9b5e60a6e233245f0fec39d143a1c6b9517ad1ff7fd',
        ),
        'wp8y_5_gf_entry' => array(
            'bytes' => 1345,
            'sha256' => '85e0a6a1e8f54572f4a93cbba48e8a5015775f0c5e78456f869936ac69bb97be',
        ),
        'wp8y_5_gf_entry_meta' => array(
            'bytes' => 575,
            'sha256' => '9a1f0c55476384af14d9783262628ab1e344b104585fca07308653f46c2045e0',
        ),
        'wp8y_5_gf_entry_notes' => array(
            'bytes' => 622,
            'sha256' => '2081960476137fc4e48b036915c0a81d28da967c316bab89bf86505270f6be90',
        ),
        'wp8y_5_gf_form' => array(
            'bytes' => 401,
            'sha256' => '1b56016aaeb840f093a3d7b7ffa9a90e5008bcb3ffe1d168ea3fb0b4a6ca9cb7',
        ),
        'wp8y_5_gf_form_meta' => array(
            'bytes' => 413,
            'sha256' => '8aec6360abea414e878971581bf7d9597409cd91c8a60e14df07b0a485a8dc1e',
        ),
        'wp8y_5_gf_form_revisions' => array(
            'bytes' => 384,
            'sha256' => '42252d6f982503b05c9a77843a08390aea05384315f7c9d1bad62649f2b9766b',
        ),
        'wp8y_5_gf_form_view' => array(
            'bytes' => 433,
            'sha256' => '45457654e5caa46db96f68e7a1c8c074130fecd851201176d2eeb3e8e0b01d83',
        ),
    );
}

/** @return array<int, string> */
function twins_staging_il_frontend_plugin_keys(): array
{
    return array(
        'advanced-custom-fields/acf.php',
        'astra-addon/astra-addon.php',
        'custom-post-type-ui/custom-post-type-ui.php',
        'elementor-pro/elementor-pro.php',
        'elementor/elementor.php',
        'gravityforms/gravityforms.php',
        'pages-with-category-and-tag/pages-with-category-and-tag.php',
        'ultimate-elementor/ultimate-elementor.php',
    );
}

/** @return array<int, string>|null */
function twins_staging_il_decode_frontend_plugins(string $raw): ?array
{
    $expectedSha256 = 'bada7e256edf31eede68c952bcf2ec4902a80c4fd517252252c3211ebd9c956d';
    if (strlen($raw) !== 457 || !hash_equals($expectedSha256, hash('sha256', $raw))) {
        return null;
    }

    $decoded = maybe_unserialize($raw);
    if (!is_array($decoded)) {
        return null;
    }
    $keys = array_values(array_map('strval', array_keys($decoded)));
    sort($keys, SORT_STRING);
    return $keys === twins_staging_il_frontend_plugin_keys() ? $keys : null;
}

/** @return array<string, array<int, string>> */
function twins_staging_il_frontend_directory_manifest(): array
{
    return array(
        '' => array('2026', 'astra', 'astra-addon', 'elementor', 'uael_uploads'),
        '2026' => array('07'),
        '2026/07' => array(),
        'astra' => array('index.php'),
        'astra-addon' => array(
            'astra-addon-6a569d87738a81-12948230.css',
            'astra-addon-6a569d87748253-27510918.js',
            'index.php',
        ),
        'elementor' => array('design-system-sync'),
        'elementor/design-system-sync' => array(),
        'uael_uploads' => array('.htaccess'),
    );
}

/** @return array<string, array{mode: int, uid: int, gid: int, size: int, sha256: string}> */
function twins_staging_il_frontend_file_manifest(): array
{
    return array(
        'astra-addon/astra-addon-6a569d87738a81-12948230.css' => array(
            'mode' => 0644,
            'uid' => 2820,
            'gid' => 2820,
            'size' => 14909,
            'sha256' => 'a0402be13410d4b6d0e58d56b48274beb495e468cd297848caf904cad48ac2c9',
        ),
        'astra-addon/astra-addon-6a569d87748253-27510918.js' => array(
            'mode' => 0644,
            'uid' => 2820,
            'gid' => 2820,
            'size' => 11563,
            'sha256' => '1073204d0ab9aa849f46c989e305104fbbd5aa11807a0f784584363be34e6978',
        ),
        'astra-addon/index.php' => array(
            'mode' => 0644,
            'uid' => 2820,
            'gid' => 2820,
            'size' => 0,
            'sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        ),
        'astra/index.php' => array(
            'mode' => 0644,
            'uid' => 2820,
            'gid' => 2820,
            'size' => 0,
            'sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        ),
        'uael_uploads/.htaccess' => array(
            'mode' => 0644,
            'uid' => 2820,
            'gid' => 2820,
            'size' => 13,
            'sha256' => '63d687eb50a82f2c475f5b4f6103506f5a4b80c86bc9ce04d8ba65d0e4de0f8d',
        ),
    );
}

function twins_staging_il_stable_stat(array $before, array $after): bool
{
    foreach (array('dev', 'ino', 'mode', 'nlink', 'uid', 'gid', 'size', 'mtime', 'ctime') as $field) {
        if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
            return false;
        }
    }
    return true;
}

/** @param array<int, string> $expectedChildren */
function twins_staging_il_directory_is_exact(string $path, array $expectedChildren): bool
{
    if (!array_is_list($expectedChildren) || count($expectedChildren) > 8) {
        return false;
    }
    foreach ($expectedChildren as $child) {
        if (!is_string($child)
            || $child === '.'
            || $child === '..'
            || str_contains($child, '/')
            || str_contains($child, "\0")) {
            return false;
        }
    }
    if (count(array_unique($expectedChildren)) !== count($expectedChildren)) {
        return false;
    }

    $handle = @opendir($path);
    if (!is_resource($handle)) {
        return false;
    }
    $children = array();
    try {
        while (($entry = @readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $children[] = $entry;
            if (count($children) > count($expectedChildren)) {
                return false;
            }
        }
    } finally {
        closedir($handle);
    }

    sort($children, SORT_STRING);
    sort($expectedChildren, SORT_STRING);
    return $children === $expectedChildren;
}

/** @param array{mode: int, uid: int, gid: int, size: int, sha256: string} $expected */
function twins_staging_il_pinned_file_is_exact(string $path, array $expected): bool
{
    $expectedSize = (int) ($expected['size'] ?? -1);
    $expectedDigest = (string) ($expected['sha256'] ?? '');
    if ($expectedSize < 0
        || $expectedSize > 65536
        || preg_match('/\A[0-9a-f]{64}\z/D', $expectedDigest) !== 1) {
        return false;
    }

    clearstatcache(true, $path);
    $pathBefore = @lstat($path);
    if (!is_array($pathBefore)
        || @realpath($path) !== $path
        || is_link($path)
        || (((int) ($pathBefore['mode'] ?? 0)) & 0170000) !== 0100000
        || (((int) $pathBefore['mode']) & 0777) !== (int) ($expected['mode'] ?? -1)
        || (int) ($pathBefore['uid'] ?? -1) !== (int) ($expected['uid'] ?? -2)
        || (int) ($pathBefore['gid'] ?? -1) !== (int) ($expected['gid'] ?? -2)
        || (int) ($pathBefore['size'] ?? -1) !== $expectedSize) {
        return false;
    }

    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        return false;
    }
    $descriptorBefore = @fstat($handle);
    if (!is_array($descriptorBefore)
        || (($descriptorBefore['dev'] ?? null) !== ($pathBefore['dev'] ?? null))
        || (($descriptorBefore['ino'] ?? null) !== ($pathBefore['ino'] ?? null))
        || (((int) ($descriptorBefore['mode'] ?? 0)) & 0170000) !== 0100000
        || (int) ($descriptorBefore['size'] ?? -1) !== $expectedSize) {
        fclose($handle);
        return false;
    }

    $hashContext = hash_init('sha256');
    $bytesRead = 0;
    $readFailed = false;
    while ($bytesRead <= $expectedSize) {
        $remaining = ($expectedSize + 1) - $bytesRead;
        if ($remaining <= 0) {
            break;
        }
        $chunk = @fread($handle, min(8192, $remaining));
        if (!is_string($chunk)) {
            $readFailed = true;
            break;
        }
        if ($chunk === '') {
            break;
        }
        $bytesRead += strlen($chunk);
        if ($bytesRead > $expectedSize) {
            break;
        }
        hash_update($hashContext, $chunk);
    }
    $digest = hash_final($hashContext);
    $descriptorAfter = @fstat($handle);
    fclose($handle);

    clearstatcache(true, $path);
    $pathAfter = @lstat($path);
    return !$readFailed
        && $bytesRead === $expectedSize
        && is_array($descriptorAfter)
        && is_array($pathAfter)
        && twins_staging_il_stable_stat($descriptorBefore, $descriptorAfter)
        && twins_staging_il_stable_stat($pathBefore, $pathAfter)
        && @realpath($path) === $path
        && !is_link($path)
        && hash_equals($expectedDigest, $digest);
}

if (!function_exists('twins_staging_il_frontend_runtime_evidence')) {
    /** @return array{valid: bool, profile: string, mismatches: array<int, string>} */
    function twins_staging_il_frontend_runtime_evidence(): array
    {
        global $wpdb;
        $mismatches = array();

        $pluginReads = array();
        for ($pass = 0; $pass < 2; $pass++) {
            $wpdb->last_error = '';
            $rows = $wpdb->get_results(
                "SELECT meta_id,
                        OCTET_LENGTH(meta_value) AS value_bytes,
                        LEFT(meta_value, 458) AS bounded_value
                 FROM `wp8y_sitemeta`
                 WHERE site_id = 1 AND meta_key = 'active_sitewide_plugins'
                 ORDER BY meta_id ASC",
                ARRAY_A
            );
            if (!is_array($rows) || count($rows) !== 1 || (string) $wpdb->last_error !== '') {
                $mismatches[] = 'network-active plugin evidence is unavailable or ambiguous';
                break;
            }
            $row = (array) $rows[0];
            $raw = (string) ($row['bounded_value'] ?? '');
            if ((int) ($row['value_bytes'] ?? -1) !== 457
                || twins_staging_il_decode_frontend_plugins($raw) === null) {
                $mismatches[] = 'network-active plugin evidence changed';
            }
            $pluginReads[] = array(
                'metaId' => (int) ($row['meta_id'] ?? 0),
                'bytes' => (int) ($row['value_bytes'] ?? -1),
                'raw' => $raw,
            );
        }
        if (count($pluginReads) === 2 && $pluginReads[0] !== $pluginReads[1]) {
            $mismatches[] = 'network-active plugin evidence changed while it was read';
        }

        foreach (twins_staging_il_frontend_table_manifest() as $table => $expected) {
            if (preg_match('/\Awp8y_5_gf_[a-z_]+\z/D', $table) !== 1) {
                $mismatches[] = 'internal frontend table manifest is malformed';
                continue;
            }
            $schemaReads = array();
            for ($pass = 0; $pass < 2; $pass++) {
                $wpdb->last_error = '';
                $row = $wpdb->get_row('SHOW CREATE TABLE `' . $table . '`', ARRAY_A);
                $createSql = is_array($row) ? (string) ($row['Create Table'] ?? '') : '';
                if (!is_array($row)
                    || (string) ($row['Table'] ?? '') !== $table
                    || (string) $wpdb->last_error !== ''
                    || strlen($createSql) !== $expected['bytes']
                    || hash('sha256', $createSql) !== $expected['sha256']) {
                    $mismatches[] = 'Gravity Forms schema digest changed: ' . $table;
                    break;
                }
                $schemaReads[] = $createSql;

                $wpdb->last_error = '';
                $hasRows = $wpdb->get_var('SELECT EXISTS(SELECT 1 FROM `' . $table . '` LIMIT 1)');
                if ((string) $wpdb->last_error !== '' || (string) $hasRows !== '0') {
                    $mismatches[] = 'Gravity Forms table is nonempty or unreadable: ' . $table;
                    break;
                }
            }
            if (count($schemaReads) === 2 && !hash_equals($schemaReads[0], $schemaReads[1])) {
                $mismatches[] = 'Gravity Forms schema changed while it was read: ' . $table;
            }
        }

        $root = WP_CONTENT_DIR . '/uploads/sites/5';
        $legacy = WP_CONTENT_DIR . '/blogs.dir/5';
        clearstatcache(true, $root);
        $rootBefore = @lstat($root);
        if (!is_array($rootBefore)
            || realpath($root) !== $root
            || is_link($root)
            || !is_dir($root)
            || (((int) ($rootBefore['mode'] ?? 0)) & 0170000) !== 0040000
            || (int) ($rootBefore['uid'] ?? -1) !== 2820
            || (int) ($rootBefore['gid'] ?? -1) !== 2820) {
            $mismatches[] = 'frontend filesystem root is not the pinned directory';
        }
        if (file_exists($legacy) || is_link($legacy)) {
            $mismatches[] = 'legacy uploads tree exists';
        }

        for ($pass = 0; $pass < 2; $pass++) {
            foreach (twins_staging_il_frontend_directory_manifest() as $relative => $expectedChildren) {
                $path = $relative === '' ? $root : $root . '/' . $relative;
                clearstatcache(true, $path);
                $before = @lstat($path);
                $childrenAreExact = is_array($before)
                    && !is_link($path)
                    && twins_staging_il_directory_is_exact($path, $expectedChildren);
                clearstatcache(true, $path);
                $after = @lstat($path);
                if (!is_array($before)
                    || !is_array($after)
                    || @realpath($path) !== $path
                    || is_link($path)
                    || (((int) ($before['mode'] ?? 0)) & 0170000) !== 0040000
                    || (int) ($before['uid'] ?? -1) !== 2820
                    || (int) ($before['gid'] ?? -1) !== 2820
                    || ($relative !== '' && ((((int) $before['mode']) & 0777) !== 0755 || (int) ($before['size'] ?? -1) !== 4096))
                    || !twins_staging_il_stable_stat($before, $after)
                    || !$childrenAreExact) {
                    $mismatches[] = 'frontend filesystem directory changed: ' . ($relative === '' ? '.' : $relative);
                }
            }
        }

        for ($pass = 0; $pass < 2; $pass++) {
            foreach (twins_staging_il_frontend_file_manifest() as $relative => $expected) {
                if (!twins_staging_il_pinned_file_is_exact($root . '/' . $relative, $expected)) {
                    $mismatches[] = 'frontend filesystem file changed: ' . $relative;
                }
            }
        }

        clearstatcache(true, $root);
        $rootAfter = @lstat($root);
        if (!is_array($rootBefore)
            || !is_array($rootAfter)
            || !twins_staging_il_stable_stat($rootBefore, $rootAfter)) {
            $mismatches[] = 'frontend filesystem evidence changed while it was read';
        }
        clearstatcache(true, $legacy);
        if (file_exists($legacy) || is_link($legacy)) {
            $mismatches[] = 'legacy uploads tree changed while it was read';
        }

        return array(
            'valid' => $mismatches === array(),
            'profile' => 'FRONTEND_INITIALIZED',
            'mismatches' => array_values(array_unique($mismatches)),
        );
    }
}

function twins_staging_il_manifest(): array
{
    $cptBase64 = 'YToxOntzOjg6ImxvY2F0aW9uIjthOjM0OntzOjQ6Im5hbWUiO3M6ODoibG9jYXRpb24iO3M6NToibGFiZWwiO3M6OToiTG9jYXRpb25zIjtzOjE0OiJzaW5ndWxhcl9sYWJlbCI7czo4OiJMb2NhdGlvbiI7czoxMToiZGVzY3JpcHRpb24iO3M6MDoiIjtzOjY6InB1YmxpYyI7czo0OiJ0cnVlIjtzOjE4OiJwdWJsaWNseV9xdWVyeWFibGUiO3M6NDoidHJ1ZSI7czo3OiJzaG93X3VpIjtzOjQ6InRydWUiO3M6MTc6InNob3dfaW5fbmF2X21lbnVzIjtzOjQ6InRydWUiO3M6MTY6ImRlbGV0ZV93aXRoX3VzZXIiO3M6NToiZmFsc2UiO3M6MTI6InNob3dfaW5fcmVzdCI7czo0OiJ0cnVlIjtzOjk6InJlc3RfYmFzZSI7czowOiIiO3M6MjE6InJlc3RfY29udHJvbGxlcl9jbGFzcyI7czowOiIiO3M6MTQ6InJlc3RfbmFtZXNwYWNlIjtzOjA6IiI7czoxMToiaGFzX2FyY2hpdmUiO3M6NDoidHJ1ZSI7czoxODoiaGFzX2FyY2hpdmVfc3RyaW5nIjtzOjA6IiI7czoxOToiZXhjbHVkZV9mcm9tX3NlYXJjaCI7czo1OiJmYWxzZSI7czoxNToiY2FwYWJpbGl0eV90eXBlIjtzOjQ6InBvc3QiO3M6MTI6ImhpZXJhcmNoaWNhbCI7czo0OiJ0cnVlIjtzOjEwOiJjYW5fZXhwb3J0IjtzOjU6ImZhbHNlIjtzOjc6InJld3JpdGUiO3M6NDoidHJ1ZSI7czoxMjoicmV3cml0ZV9zbHVnIjtzOjA6IiI7czoxNzoicmV3cml0ZV93aXRoZnJvbnQiO3M6NDoidHJ1ZSI7czo5OiJxdWVyeV92YXIiO3M6NDoidHJ1ZSI7czoxNDoicXVlcnlfdmFyX3NsdWciO3M6MDoiIjtzOjEzOiJtZW51X3Bvc2l0aW9uIjtzOjA6IiI7czoxMjoic2hvd19pbl9tZW51IjtzOjQ6InRydWUiO3M6MTk6InNob3dfaW5fbWVudV9zdHJpbmciO3M6MDoiIjtzOjk6Im1lbnVfaWNvbiI7czowOiIiO3M6MjA6InJlZ2lzdGVyX21ldGFfYm94X2NiIjtOO3M6ODoic3VwcG9ydHMiO2E6NDp7aTowO3M6NToidGl0bGUiO2k6MTtzOjY6ImVkaXRvciI7aToyO3M6OToidGh1bWJuYWlsIjtpOjM7czoxNToicGFnZS1hdHRyaWJ1dGVzIjt9czoxMDoidGF4b25vbWllcyI7YToyOntpOjA7czo4OiJjYXRlZ29yeSI7aToxO3M6ODoicG9zdF90YWciO31zOjY6ImxhYmVscyI7YToyOTp7czo5OiJtZW51X25hbWUiO3M6MDoiIjtzOjk6ImFsbF9pdGVtcyI7czowOiIiO3M6NzoiYWRkX25ldyI7czowOiIiO3M6MTI6ImFkZF9uZXdfaXRlbSI7czowOiIiO3M6OToiZWRpdF9pdGVtIjtzOjA6IiI7czo4OiJuZXdfaXRlbSI7czowOiIiO3M6OToidmlld19pdGVtIjtzOjA6IiI7czoxMDoidmlld19pdGVtcyI7czowOiIiO3M6MTI6InNlYXJjaF9pdGVtcyI7czowOiIiO3M6OToibm90X2ZvdW5kIjtzOjA6IiI7czoxODoibm90X2ZvdW5kX2luX3RyYXNoIjtzOjA6IiI7czoxNzoicGFyZW50X2l0ZW1fY29sb24iO3M6MDoiIjtzOjE0OiJmZWF0dXJlZF9pbWFnZSI7czowOiIiO3M6MTg6InNldF9mZWF0dXJlZF9pbWFnZSI7czowOiIiO3M6MjE6InJlbW92ZV9mZWF0dXJlZF9pbWFnZSI7czowOiIiO3M6MTg6InVzZV9mZWF0dXJlZF9pbWFnZSI7czowOiIiO3M6ODoiYXJjaGl2ZXMiO3M6MDoiIjtzOjE2OiJpbnNlcnRfaW50b19pdGVtIjtzOjA6IiI7czoyMToidXBsb2FkZWRfdG9fdGhpc19pdGVtIjtzOjA6IiI7czoxNzoiZmlsdGVyX2l0ZW1zX2xpc3QiO3M6MDoiIjtzOjIxOiJpdGVtc19saXN0X25hdmlnYXRpb24iO3M6MDoiIjtzOjEwOiJpdGVtc19saXN0IjtzOjA6IiI7czoxMDoiYXR0cmlidXRlcyI7czowOiIiO3M6MTQ6Im5hbWVfYWRtaW5fYmFyIjtzOjA6IiI7czoxNDoiaXRlbV9wdWJsaXNoZWQiO3M6MDoiIjtzOjI0OiJpdGVtX3B1Ymxpc2hlZF9wcml2YXRlbHkiO3M6MDoiIjtzOjIyOiJpdGVtX3JldmVydGVkX3RvX2RyYWZ0IjtzOjA6IiI7czoxNDoiaXRlbV9zY2hlZHVsZWQiO3M6MDoiIjtzOjEyOiJpdGVtX3VwZGF0ZWQiO3M6MDoiIjt9czoxNToiY3VzdG9tX3N1cHBvcnRzIjtzOjA6IiI7czoxNjoiZW50ZXJfdGl0bGVfaGVyZSI7czowOiIiO319';
    $cptRaw = base64_decode($cptBase64, true);
    if (!is_string($cptRaw)) {
        throw new RuntimeException('STAGING_IL_REFUSED: fixed CPT manifest is unavailable');
    }
    $decodedCpt = maybe_unserialize($cptRaw);
    if (hash('sha256', $cptRaw) !== 'a03dea17f88de0d6b4b7f8f377f370d3479b422fc855c2f4b0efcccea350b94f'
        || !is_array($decodedCpt)
        || array_keys($decodedCpt) !== array('location')
        || (string) ($decodedCpt['location']['public'] ?? '') !== 'true'
        || (string) ($decodedCpt['location']['rewrite'] ?? '') !== 'true') {
        throw new RuntimeException('STAGING_IL_REFUSED: fixed CPT manifest shape changed');
    }

    return array(
        'domain' => 'danielj140.sg-host.com',
        'path' => '/il/',
        'siteUrl' => 'https://danielj140.sg-host.com/il',
        'blogId' => 5,
        'networkId' => 1,
        'ownerUserId' => 21,
        'ownerLogin' => 'chatgptprofile1stage',
        'siteTitle' => 'Garage Door Service in Rockford, Illinois',
        'phone' => '(815) 800-2025',
        'tel' => '+18158002025',
        'address' => null,
        'public' => 0,
        'basePrefix' => 'wp8y_',
        'targetPrefix' => 'wp8y_5_',
        'sequenceBoundary' => 'AUTO_INCREMENT',
        'backupPath' => '/home/customer/staging-safety/before-full-overhaul-20260714.sql.gz',
        'backupSha256' => '836dd8850730d4772956e041877cebd23d791700800a0a94588ebf1a9e12f374',
        'cptOptionName' => 'cptui_post_types',
        'cptOptionRaw' => $cptRaw,
        'cptOptionSha256' => 'a03dea17f88de0d6b4b7f8f377f370d3479b422fc855c2f4b0efcccea350b94f',
        'core' => array(
            array('slug' => '', 'title' => 'Garage Door Service in Rockford, Illinois'),
            array('slug' => 'garage-door-services', 'title' => 'Garage Door Services'),
            array('slug' => 'garage-door-repair', 'title' => 'Garage Door Repair'),
            array('slug' => 'garage-door-installation', 'title' => 'Garage Door Installation'),
            array('slug' => 'garage-door-openers', 'title' => 'Garage Door Openers'),
            array('slug' => 'emergency-garage-services', 'title' => 'Emergency Garage Door Service'),
            array('slug' => 'locations', 'title' => 'Illinois Service Areas'),
            array('slug' => 'contact-us', 'title' => 'Contact Twins Garage Doors'),
            array('slug' => 'door-builder', 'title' => 'Design Your Garage Door'),
        ),
        'cities' => array(
            'rockford', 'loves-park', 'machesney-park', 'belvidere',
            'roscoe', 'rockton', 'cherry-valley', 'poplar-grove',
            'south-beloit', 'winnebago', 'byron', 'caledonia',
        ),
    );
}

if (!function_exists('twins_staging_il_source_cpt_digests')) {
    function twins_staging_il_source_cpt_digests(): array
    {
        global $wpdb;
        $tables = array(1 => 'wp8y_options', 3 => 'wp8y_3_options', 4 => 'wp8y_4_options');
        $digests = array();
        foreach ($tables as $blogId => $table) {
            $raw = $wpdb->get_var(
                "SELECT option_value FROM `{$table}` WHERE option_name = 'cptui_post_types' LIMIT 1"
            );
            $digests[$blogId] = is_string($raw) ? hash('sha256', $raw) : '';
        }
        return $digests;
    }
}

function twins_staging_il_city_titles(): array
{
    return array(
        'rockford' => 'Garage Door Service in Rockford, Illinois',
        'loves-park' => 'Garage Door Service in Loves Park, Illinois',
        'machesney-park' => 'Garage Door Service in Machesney Park, Illinois',
        'belvidere' => 'Garage Door Service in Belvidere, Illinois',
        'roscoe' => 'Garage Door Service in Roscoe, Illinois',
        'rockton' => 'Garage Door Service in Rockton, Illinois',
        'cherry-valley' => 'Garage Door Service in Cherry Valley, Illinois',
        'poplar-grove' => 'Garage Door Service in Poplar Grove, Illinois',
        'south-beloit' => 'Garage Door Service in South Beloit, Illinois',
        'winnebago' => 'Garage Door Service in Winnebago, Illinois',
        'byron' => 'Garage Door Service in Byron, Illinois',
        'caledonia' => 'Garage Door Service in Caledonia, Illinois',
    );
}

function twins_staging_il_expected_tables(): array
{
    return array(
        'wp8y_5_commentmeta',
        'wp8y_5_comments',
        'wp8y_5_links',
        'wp8y_5_options',
        'wp8y_5_postmeta',
        'wp8y_5_posts',
        'wp8y_5_term_relationships',
        'wp8y_5_term_taxonomy',
        'wp8y_5_termmeta',
        'wp8y_5_terms',
    );
}

function twins_staging_il_assert_table_routing(): void
{
    global $wpdb;
    $expectedGlobal = array(
        'users' => 'wp8y_users',
        'usermeta' => 'wp8y_usermeta',
        'blogs' => 'wp8y_blogs',
        'blogmeta' => 'wp8y_blogmeta',
        'signups' => 'wp8y_signups',
        'site' => 'wp8y_site',
        'sitemeta' => 'wp8y_sitemeta',
        'registration_log' => 'wp8y_registration_log',
    );
    $actualGlobal = array();
    foreach (array_keys($expectedGlobal) as $property) {
        $actualGlobal[$property] = is_object($wpdb) ? (string) ($wpdb->{$property} ?? '') : '';
    }
    $expectedBlog = array(
        'posts' => 'wp8y_5_posts',
        'comments' => 'wp8y_5_comments',
        'links' => 'wp8y_5_links',
        'options' => 'wp8y_5_options',
        'postmeta' => 'wp8y_5_postmeta',
        'terms' => 'wp8y_5_terms',
        'term_taxonomy' => 'wp8y_5_term_taxonomy',
        'term_relationships' => 'wp8y_5_term_relationships',
        'termmeta' => 'wp8y_5_termmeta',
        'commentmeta' => 'wp8y_5_commentmeta',
    );
    $actualBlog = is_object($wpdb) && method_exists($wpdb, 'tables')
        ? $wpdb->tables('blog', true, 5)
        : array();
    if ($actualGlobal !== $expectedGlobal
        || !is_object($wpdb)
        || !method_exists($wpdb, 'get_blog_prefix')
        || $wpdb->get_blog_prefix(5) !== 'wp8y_5_'
        || $actualBlog !== $expectedBlog) {
        throw new RuntimeException('STAGING_IL_REFUSED: WordPress table routing is not exact');
    }
}

function twins_staging_il_assert_owner_primary_blog(): void
{
    global $wpdb;
    $wpdb->last_error = '';
    $rawRows = $wpdb->get_results(
        "SELECT umeta_id, user_id, meta_key, meta_value
         FROM `wp8y_usermeta`
         WHERE user_id = 21 AND meta_key = 'primary_blog'
         ORDER BY umeta_id ASC",
        ARRAY_A
    );
    if (!is_array($rawRows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: owner primary-blog metadata read failed');
    }
    $rows = array_values(array_map(static function ($row): array {
        $row = (array) $row;
        return array(
            'user_id' => (int) ($row['user_id'] ?? 0),
            'meta_key' => (string) ($row['meta_key'] ?? ''),
            'meta_value' => (string) ($row['meta_value'] ?? ''),
        );
    }, $rawRows));
    if ($rows !== array(array('user_id' => 21, 'meta_key' => 'primary_blog', 'meta_value' => '1'))) {
        throw new RuntimeException('STAGING_IL_REFUSED: owner primary-blog metadata is not exact');
    }
}

function twins_staging_il_assert_identity(): void
{
    global $wpdb, $wp_version;
    $manifest = twins_staging_il_manifest();

    if (!defined('WP_CLI') || WP_CLI !== true
        || !defined('MULTISITE') || MULTISITE !== true
        || !defined('SUBDOMAIN_INSTALL') || SUBDOMAIN_INSTALL !== false) {
        throw new RuntimeException('STAGING_IL_REFUSED: fixed WP-CLI multisite runtime is required');
    }
    if (!defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'staging') {
        throw new RuntimeException('STAGING_IL_REFUSED: WP_ENVIRONMENT_TYPE must be staging');
    }
    if (!defined('TWINS_STAGING_SAFETY') || TWINS_STAGING_SAFETY !== true) {
        throw new RuntimeException('STAGING_IL_REFUSED: TWINS_STAGING_SAFETY must be true');
    }
    if (!defined('DISABLE_WP_CRON') || DISABLE_WP_CRON !== true) {
        throw new RuntimeException('STAGING_IL_REFUSED: WordPress cron must be disabled');
    }
    if (!is_multisite() || get_current_network_id() !== 1 || get_main_site_id(1) !== 1) {
        throw new RuntimeException('STAGING_IL_REFUSED: unexpected multisite identity');
    }
    if (get_current_blog_id() !== 1) {
        throw new RuntimeException('STAGING_IL_REFUSED: command must begin on the main staging blog');
    }
    $network = get_network(1);
    if (!is_object($network)
        || (string) ($network->domain ?? '') !== 'danielj140.sg-host.com'
        || (string) ($network->path ?? '') !== '/'
        || rtrim(home_url('/'), '/') !== 'https://danielj140.sg-host.com'
        || rtrim(site_url('/'), '/') !== 'https://danielj140.sg-host.com') {
        throw new RuntimeException('STAGING_IL_REFUSED: unexpected staging hostname');
    }
    if (!is_object($wpdb)
        || (string) $wpdb->base_prefix !== $manifest['basePrefix']
        || (string) ($wpdb->blogs ?? '') !== 'wp8y_blogs') {
        throw new RuntimeException('STAGING_IL_REFUSED: unexpected database prefix');
    }
    twins_staging_il_assert_table_routing();
    twins_staging_il_assert_owner_primary_blog();

    $runtime = twins_staging_il_runtime_evidence();
    $muPhpFiles = (array) ($runtime['muPhpFiles'] ?? array());
    $overhaulDigest = (string) ($runtime['overhaulDigest'] ?? '');
    $predeploymentMuSet = $muPhpFiles === array('twins-staging-safety.php')
        && $overhaulDigest === '';
    $deployedMuSet = $muPhpFiles === array('twins-staging-overhaul.php', 'twins-staging-safety.php')
        && $overhaulDigest === '20a3e8b8d88917f54173457f112562c6a31250f9385a3144d9771704d63a2e90';
    if (($runtime['valid'] ?? false) !== true
        || ($runtime['cli'] ?? false) !== true
        || (string) ($runtime['wpVersion'] ?? $wp_version) !== '7.0.1'
        || (int) ($runtime['phpMajor'] ?? PHP_MAJOR_VERSION) !== 8
        || (int) ($runtime['phpMinor'] ?? PHP_MINOR_VERSION) !== 2
        || !str_starts_with((string) ($runtime['databaseVersion'] ?? ''), '8.4.')
        || (string) ($runtime['safetyDigest'] ?? '') !== '65c65d28c502d5465b2e6419a48108781d8c554473290ec70d2d9997263226d2'
        || (!$predeploymentMuSet && !$deployedMuSet)
        || ($runtime['normalPluginOrThemeLoaded'] ?? true) !== false
        || ($runtime['astraAvailable'] ?? false) !== true
        || ($runtime['astraAllowed'] ?? false) !== true
        || ($runtime['installerOverrideAbsent'] ?? false) !== true
        || (string) ($runtime['locale'] ?? '') !== 'en_US'
        || (array) ($runtime['dropins'] ?? array()) !== array()) {
        throw new RuntimeException('STAGING_IL_REFUSED: reviewed staging runtime evidence changed');
    }

    $sourceDigests = twins_staging_il_source_cpt_digests();
    if ($sourceDigests !== array(
        1 => $manifest['cptOptionSha256'],
        3 => $manifest['cptOptionSha256'],
        4 => $manifest['cptOptionSha256'],
    )) {
        throw new RuntimeException('STAGING_IL_REFUSED: source location CPT evidence changed');
    }

    $owner = get_user_by('id', $manifest['ownerUserId']);
    if (!is_object($owner)
        || (int) ($owner->ID ?? 0) !== $manifest['ownerUserId']
        || (string) ($owner->user_login ?? '') !== $manifest['ownerLogin']
        || !is_super_admin($manifest['ownerUserId'])) {
        throw new RuntimeException('STAGING_IL_REFUSED: fixed staging owner is unavailable');
    }

    $initializeHook = has_action('wp_initialize_site', 'wp_initialize_site');
    if ($initializeHook !== 10 || has_action('wpmu_new_blog') !== false) {
        throw new RuntimeException('STAGING_IL_REFUSED: core initialization hook identity changed');
    }
    foreach (array(
        'wp_initialize_site_args',
        'populate_options',
        'populate_site_meta',
        'pre_wp_is_site_initialized',
        'can_add_user_to_blog',
        'add_user_to_blog',
        'clean_site_cache',
        'dbdelta_queries',
        'dbdelta_create_queries',
        'dbdelta_insert_queries',
        'flush_rewrite_rules_hard',
        'generate_rewrite_rules',
        'rewrite_rules_array',
    ) as $hookName) {
        if (has_action($hookName) !== false) {
            throw new RuntimeException('STAGING_IL_REFUSED: unsafe lifecycle hook is present: ' . $hookName);
        }
    }
    if (defined('ELEMENTOR_VERSION') || defined('ACF_VERSION')
        || class_exists('GFForms', false) || function_exists('cptui_register_my_cpts')) {
        throw new RuntimeException('STAGING_IL_REFUSED: ordinary plugins are loaded');
    }
}

function twins_staging_il_assert_apply_runtime(): void
{
    $runtime = twins_staging_il_runtime_evidence();
    if (
        (array) ($runtime['muPhpFiles'] ?? array()) !== array('twins-staging-safety.php')
        || (string) ($runtime['overhaulDigest'] ?? '') !== ''
    ) {
        throw new RuntimeException('STAGING_IL_REFUSED: apply requires the reviewed predeployment MU-plugin set');
    }
}

/** @return array<int, array<string, mixed>> */
function twins_staging_il_site_rows(): array
{
    global $wpdb;
    $manifest = twins_staging_il_manifest();
    $sql = $wpdb->prepare(
        "SELECT blog_id, site_id, domain, path, registered, last_updated, public, archived, mature, spam, deleted, lang_id
         FROM `wp8y_blogs`
         WHERE blog_id = %d OR (domain = %s AND path = %s)
         ORDER BY blog_id ASC",
        $manifest['blogId'],
        $manifest['domain'],
        $manifest['path']
    );
    $wpdb->last_error = '';
    $rows = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($rows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: site identity read failed');
    }
    return array_values(array_map(static fn($row): array => (array) $row, $rows));
}

/** @return array<int, int> */
function twins_staging_il_all_site_ids(): array
{
    global $wpdb;
    $wpdb->last_error = '';
    $rows = $wpdb->get_results('SELECT blog_id FROM `wp8y_blogs` ORDER BY blog_id ASC', ARRAY_A);
    if (!is_array($rows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: network site-list read failed');
    }
    $ids = array_values(array_map(static fn($row): int => (int) ((array) $row)['blog_id'], $rows));
    sort($ids, SORT_NUMERIC);
    return $ids;
}

/** @return array<int, string> */
function twins_staging_il_target_tables(): array
{
    global $wpdb;
    $manifest = twins_staging_il_manifest();
    $like = $wpdb->esc_like($manifest['targetPrefix']) . '%';
    $sql = $wpdb->prepare(
        'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE %s ORDER BY table_name ASC LIMIT 20',
        $like
    );
    $wpdb->last_error = '';
    $rawTables = $wpdb->get_col($sql);
    if (!is_array($rawTables) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: target table-list read failed');
    }
    $tables = array_values(array_map('strval', $rawTables));
    $tables = array_values(array_filter(
        $tables,
        static fn(string $table): bool => str_starts_with($table, $manifest['targetPrefix'])
    ));
    sort($tables, SORT_STRING);
    return $tables;
}

/** @return array<int, array<string, string>> */
function twins_staging_il_target_table_facts(): array
{
    global $wpdb;
    $wpdb->last_error = '';
    $rows = $wpdb->get_results(
        "SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_COLLATION AS table_collation
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name LIKE 'wp8y\\_5\\_%'
         ORDER BY table_name ASC
         LIMIT 20",
        ARRAY_A
    );
    if (!is_array($rows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: target table-storage read failed');
    }
    $facts = array_values(array_map(static function ($row): array {
        $row = (array) $row;
        return array(
            'table_name' => (string) ($row['table_name'] ?? ''),
            'engine' => (string) ($row['engine'] ?? ''),
            'table_collation' => (string) ($row['table_collation'] ?? ''),
        );
    }, $rows));
    usort($facts, static fn(array $left, array $right): int => strcmp($left['table_name'], $right['table_name']));
    return $facts;
}

/** @return array<int, array{table_name: string, engine: string, table_collation: string}> */
function twins_staging_il_coordination_table_facts(): array
{
    global $wpdb;
    $wpdb->last_error = '';
    $rawRows = $wpdb->get_results(
        "SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_COLLATION AS table_collation
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND TABLE_NAME IN ('wp8y_blogs', 'wp8y_sitemeta')
         ORDER BY TABLE_NAME ASC",
        ARRAY_A
    );
    if (!is_array($rawRows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: coordination table-storage read failed');
    }
    $facts = array_values(array_map(static function ($row): array {
        $row = (array) $row;
        return array(
            'table_name' => (string) ($row['table_name'] ?? ''),
            'engine' => (string) ($row['engine'] ?? ''),
            'table_collation' => (string) ($row['table_collation'] ?? ''),
        );
    }, $rawRows));
    usort($facts, static fn(array $left, array $right): int => strcmp($left['table_name'], $right['table_name']));
    $expected = array(
        array('table_name' => 'wp8y_blogs', 'engine' => 'InnoDB', 'table_collation' => 'utf8mb4_unicode_ci'),
        array('table_name' => 'wp8y_sitemeta', 'engine' => 'InnoDB', 'table_collation' => 'utf8mb4_unicode_ci'),
    );
    if ($facts !== $expected) {
        throw new RuntimeException('STAGING_IL_REFUSED: coordination table storage is not exact');
    }
    return $facts;
}

function twins_staging_il_blogs_auto_increment(): int
{
    global $wpdb;
    $wpdb->last_error = '';
    $rawRow = $wpdb->get_row('SHOW CREATE TABLE `wp8y_blogs`', ARRAY_A);
    if (!is_array($rawRow) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: site-sequence boundary read failed');
    }
    $createSql = (string) ($rawRow['Create Table'] ?? '');
    if ((string) ($rawRow['Table'] ?? '') !== 'wp8y_blogs'
        || preg_match('/\bAUTO_INCREMENT=([1-9][0-9]*)\b/', $createSql, $matches) !== 1) {
        throw new RuntimeException('STAGING_IL_REFUSED: site-sequence boundary is malformed');
    }
    return (int) $matches[1];
}

function twins_staging_il_assert_target_storage(): void
{
    $expected = array_map(static fn(string $table): array => array(
        'table_name' => $table,
        'engine' => 'InnoDB',
        'table_collation' => 'utf8mb4_unicode_ci',
    ), twins_staging_il_expected_tables());
    usort($expected, static fn(array $left, array $right): int => strcmp($left['table_name'], $right['table_name']));
    if (twins_staging_il_target_table_facts() !== $expected) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: initialized table storage is not exact');
    }
}

/** @return array{blogCountRaw: string, rows: array<int, array{meta_key: string, meta_value: string}>} */
function twins_staging_il_network_defaults(bool $lockRows = false): array
{
    global $wpdb;
    $sql = "SELECT meta_key, meta_value
            FROM `wp8y_sitemeta`
            WHERE site_id = 1
              AND meta_key IN ('blog_count', 'first_post', 'first_page', 'default_privacy_policy_content', 'WPLANG')
            ORDER BY meta_key ASC, meta_id ASC";
    if ($lockRows) {
        $sql .= ' FOR UPDATE NOWAIT';
    }
    $wpdb->last_error = '';
    $rawRows = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($rawRows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: network-default read failed or conflicted');
    }
    $rows = array_values(array_map(static function ($row): array {
        $row = (array) $row;
        return array(
            'meta_key' => (string) ($row['meta_key'] ?? ''),
            'meta_value' => (string) ($row['meta_value'] ?? ''),
        );
    }, $rawRows));
    usort($rows, static function (array $left, array $right): int {
        $caseInsensitive = strcasecmp($left['meta_key'], $right['meta_key']);
        return $caseInsensitive !== 0 ? $caseInsensitive : strcmp($left['meta_key'], $right['meta_key']);
    });
    $firstPost = 'Welcome to %s. This is your first post. Edit or delete it, then start writing!';
    $expectedThree = array(
        array('meta_key' => 'blog_count', 'meta_value' => '3'),
        array('meta_key' => 'first_page', 'meta_value' => ''),
        array('meta_key' => 'first_post', 'meta_value' => $firstPost),
        array('meta_key' => 'WPLANG', 'meta_value' => ''),
    );
    $expectedFour = $expectedThree;
    $expectedFour[0]['meta_value'] = '4';
    if ($rows !== $expectedThree && $rows !== $expectedFour) {
        throw new RuntimeException('STAGING_IL_REFUSED: network creation defaults are not exact');
    }
    return array('blogCountRaw' => $rows[0]['meta_value'], 'rows' => $rows);
}

/** @return array<string, mixed> */
function twins_staging_il_sequence_facts(): array
{
    global $wpdb;
    $coordinationFacts = twins_staging_il_coordination_table_facts();

    $wpdb->last_error = '';
    $increment = $wpdb->get_var('SELECT @@auto_increment_increment');
    if (!is_numeric($increment) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: site-sequence increment read failed');
    }

    $wpdb->last_error = '';
    $offset = $wpdb->get_var('SELECT @@auto_increment_offset');
    if (!is_numeric($offset) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: site-sequence offset read failed');
    }

    $wpdb->last_error = '';
    $defaultStorageEngine = $wpdb->get_var('SELECT @@default_storage_engine');
    if ((string) $defaultStorageEngine !== 'InnoDB' || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: default storage engine is not exact');
    }

    $networkDefaults = twins_staging_il_network_defaults(false);
    $networkBlogCount = $networkDefaults['blogCountRaw'];

    return array(
        'engine' => $coordinationFacts[0]['engine'],
        'collation' => $coordinationFacts[0]['table_collation'],
        'sitemetaEngine' => $coordinationFacts[1]['engine'],
        'sitemetaCollation' => $coordinationFacts[1]['table_collation'],
        'autoIncrement' => twins_staging_il_blogs_auto_increment(),
        'increment' => (int) $increment,
        'offset' => (int) $offset,
        'defaultStorageEngine' => (string) $defaultStorageEngine,
        'networkBlogCount' => (int) $networkBlogCount,
    );
}

/** @return array<string, array<int, array<string, mixed>>|array<int, string>> */
function twins_staging_il_residue(): array
{
    global $wpdb;
    $manifest = twins_staging_il_manifest();

    $wpdb->last_error = '';
    $usermetaRows = $wpdb->get_results(
        "SELECT user_id, meta_key, meta_value FROM `wp8y_usermeta` WHERE meta_key LIKE 'wp8y\\_5\\_%' ORDER BY user_id, meta_key",
        ARRAY_A
    );
    if (!is_array($usermetaRows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: target ownership-residue read failed');
    }
    $usermeta = array_values(array_filter(array_map(static fn($row): array => (array) $row, $usermetaRows), static function (array $row): bool {
        return str_starts_with((string) ($row['meta_key'] ?? ''), 'wp8y_5_');
    }));

    $wpdb->last_error = '';
    $blogmetaRows = $wpdb->get_results(
        'SELECT blog_id, meta_key, meta_value FROM `wp8y_blogmeta` WHERE blog_id = 5 ORDER BY meta_key',
        ARRAY_A
    );
    if (!is_array($blogmetaRows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: target blog-meta residue read failed');
    }
    $blogmeta = array_values(array_filter(array_map(static fn($row): array => (array) $row, $blogmetaRows), static function (array $row): bool {
        return (int) ($row['blog_id'] ?? 0) === 5;
    }));

    $wpdb->last_error = '';
    $registrationRows = $wpdb->get_results(
        'SELECT ID, email, IP, blog_id, date_registered FROM `wp8y_registration_log` WHERE blog_id = 5 ORDER BY ID',
        ARRAY_A
    );
    if (!is_array($registrationRows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_REFUSED: target registration residue read failed');
    }
    $registration = array_values(array_filter(array_map(static fn($row): array => (array) $row, $registrationRows), static function (array $row): bool {
        return (int) ($row['blog_id'] ?? 0) === 5;
    }));

    return array(
        'usermeta' => $usermeta,
        'blogmeta' => $blogmeta,
        'registrationLog' => $registration,
        'filesystem' => twins_staging_il_filesystem_residue(),
        'targetPrefix' => array($manifest['targetPrefix']),
    );
}

/** @return array<int, array<string, mixed>> */
function twins_staging_il_posts(): array
{
    global $wpdb;
    $wpdb->last_error = '';
    $rows = $wpdb->get_results(
        'SELECT ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status,
                comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified,
                post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type,
                post_mime_type, comment_count
         FROM `wp8y_5_posts` ORDER BY ID ASC',
        ARRAY_A
    );
    if (!is_array($rows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: target post read failed');
    }
    return array_values(array_map(static fn($row): array => (array) $row, $rows));
}

/** @return array<string, mixed> */
function twins_staging_il_options(): array
{
    global $wpdb;
    $allowedNames = array(
        'active_plugins',
        'blog_public',
        'blogdescription',
        'blogname',
        'cptui_post_types',
        'default_category',
        'default_role',
        'home',
        'page_for_posts',
        'page_on_front',
        'permalink_structure',
        'ping_sites',
        'post_count',
        'show_on_front',
        'siteurl',
        'stylesheet',
        'template',
        'twins_staging_il_address',
        'twins_staging_il_phone',
        'twins_staging_il_tel',
    );
    $allowedNameMap = array_fill_keys($allowedNames, true);
    $wpdb->last_error = '';
    $rows = $wpdb->get_results(
        "SELECT option_id,
                option_name,
                OCTET_LENGTH(option_value) AS value_bytes,
                LEFT(CAST(option_value AS BINARY), 10104) AS bounded_value
         FROM `wp8y_5_options`
         WHERE option_name IN (
             'active_plugins',
             'blog_public',
             'blogdescription',
             'blogname',
             'cptui_post_types',
             'default_category',
             'default_role',
             'home',
             'page_for_posts',
             'page_on_front',
             'permalink_structure',
             'ping_sites',
             'post_count',
             'show_on_front',
             'siteurl',
             'stylesheet',
             'template',
             'twins_staging_il_address',
             'twins_staging_il_phone',
             'twins_staging_il_tel'
         )
         ORDER BY option_name ASC, option_id ASC
         LIMIT 21",
        ARRAY_A
    );
    if (!is_array($rows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: target option read failed');
    }
    $options = array();
    foreach ($rows as $row) {
        $row = (array) $row;
        $name = (string) ($row['option_name'] ?? '');
        $valueBytes = (int) ($row['value_bytes'] ?? -1);
        $boundedValue = (string) ($row['bounded_value'] ?? '');
        $returnedBytes = strlen($boundedValue);
        if (!isset($allowedNameMap[$name])
            || array_key_exists($name, $options)
            || $valueBytes < 0
            || $returnedBytes > 10104
            || $returnedBytes !== min($valueBytes, 10104)) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: target option evidence is malformed');
        }
        $options[$name] = $boundedValue;
    }
    return $options;
}

/** @return array{rows: int, bytes: int, raw: string} */
function twins_staging_il_rewrite_evidence(): array
{
    global $wpdb;
    $wpdb->last_error = '';
    $rows = $wpdb->get_results(
        "SELECT option_id,
                option_name,
                OCTET_LENGTH(option_value) AS value_bytes,
                LEFT(CAST(option_value AS BINARY), 10104) AS bounded_value
         FROM `wp8y_5_options`
         WHERE option_name = 'rewrite_rules'
         ORDER BY option_id ASC
         LIMIT 2",
        ARRAY_A
    );
    if (!is_array($rows) || (string) $wpdb->last_error !== '') {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: rewrite evidence read failed');
    }
    if (count($rows) !== 1) {
        return array('rows' => count($rows), 'bytes' => -1, 'raw' => '');
    }

    $row = (array) $rows[0];
    $valueBytes = (int) ($row['value_bytes'] ?? -1);
    $boundedValue = (string) ($row['bounded_value'] ?? '');
    $returnedBytes = strlen($boundedValue);
    if ((string) ($row['option_name'] ?? '') !== 'rewrite_rules'
        || $valueBytes < 0
        || $returnedBytes > 10104
        || $returnedBytes !== min($valueBytes, 10104)) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: rewrite evidence is malformed');
    }

    return array('rows' => 1, 'bytes' => $valueBytes, 'raw' => $boundedValue);
}

/** @return array<int, array<string, mixed>> */
function twins_staging_il_expected_posts(): array
{
    $manifest = twins_staging_il_manifest();
    $expected = array();
    $nextId = 1;
    foreach ($manifest['core'] as $page) {
        $expected[] = array(
            'ID' => $nextId,
            'post_author' => $manifest['ownerUserId'],
            'post_name' => $page['slug'] === '' ? 'home' : $page['slug'],
            'post_title' => $page['title'],
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_parent' => 0,
            'post_content' => '',
            'post_excerpt' => '',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_content_filtered' => '',
            'guid' => $manifest['siteUrl'] . '/?p=' . $nextId,
            'menu_order' => 0,
            'post_mime_type' => '',
            'comment_count' => 0,
            'post_date' => '2026-07-14 12:00:00',
            'post_date_gmt' => '2026-07-14 12:00:00',
            'post_modified' => '2026-07-14 12:00:00',
            'post_modified_gmt' => '2026-07-14 12:00:00',
        );
        $nextId++;
    }
    foreach ($manifest['cities'] as $slug) {
        $expected[] = array(
            'ID' => $nextId,
            'post_author' => $manifest['ownerUserId'],
            'post_name' => $slug,
            'post_title' => twins_staging_il_city_titles()[$slug],
            'post_status' => 'publish',
            'post_type' => 'location',
            'post_parent' => 0,
            'post_content' => '',
            'post_excerpt' => '',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_content_filtered' => '',
            'guid' => $manifest['siteUrl'] . '/?p=' . $nextId,
            'menu_order' => 0,
            'post_mime_type' => '',
            'comment_count' => 0,
            'post_date' => '2026-07-14 12:00:00',
            'post_date_gmt' => '2026-07-14 12:00:00',
            'post_modified' => '2026-07-14 12:00:00',
            'post_modified_gmt' => '2026-07-14 12:00:00',
        );
        $nextId++;
    }
    return $expected;
}

/** @return array<string, mixed> */
function twins_staging_il_inspect(): array
{
    $manifest = twins_staging_il_manifest();
    twins_staging_il_assert_identity();

    $rows = twins_staging_il_site_rows();
    $siteIds = twins_staging_il_all_site_ids();
    $tables = twins_staging_il_target_tables();
    $tableFacts = twins_staging_il_target_table_facts();
    $sequence = twins_staging_il_sequence_facts();
    $residue = twins_staging_il_residue();
    $mismatches = array();

    $absenceSequenceIsClean = in_array($sequence['autoIncrement'], array(4, 5), true)
        && $sequence['increment'] === 1
        && $sequence['offset'] === 1
        && $sequence['networkBlogCount'] === 3
        && $sequence['engine'] === 'InnoDB'
        && $sequence['sitemetaEngine'] === 'InnoDB'
        && $sequence['sitemetaCollation'] === 'utf8mb4_unicode_ci'
        && $sequence['defaultStorageEngine'] === 'InnoDB'
        && $sequence['collation'] === 'utf8mb4_unicode_ci';
    $absenceIsClean = $rows === array()
        && $siteIds === array(1, 3, 4)
        && $tables === array()
        && $tableFacts === array()
        && $residue['usermeta'] === array()
        && $residue['blogmeta'] === array()
        && $residue['registrationLog'] === array()
        && $residue['filesystem'] === array()
        && $absenceSequenceIsClean;

    if ($absenceIsClean) {
        $absenceClosingRows = twins_staging_il_site_rows();
        $absenceClosingSiteIds = twins_staging_il_all_site_ids();
        $absenceClosingTables = twins_staging_il_target_tables();
        $absenceClosingTableFacts = twins_staging_il_target_table_facts();
        $absenceClosingSequence = twins_staging_il_sequence_facts();
        $absenceClosingResidue = twins_staging_il_residue();
        if ($absenceClosingRows !== $rows
            || $absenceClosingSiteIds !== $siteIds
            || $absenceClosingTables !== $tables
            || $absenceClosingTableFacts !== $tableFacts
            || $absenceClosingSequence !== $sequence
            || $absenceClosingResidue !== $residue) {
            return array(
                'status' => 'STAGING_IL_STATUS',
                'state' => 'DRIFT',
                'runtimeProfile' => 'INVALID',
                'productionWriteAuthority' => false,
                'stagingMutation' => false,
                'mismatches' => array('target table evidence changed while absence was read'),
                'manifest' => array('blogId' => 5, 'networkId' => 1, 'path' => '/il/', 'pages' => 9, 'locations' => 12),
                'sequence' => $absenceClosingSequence,
            );
        }
        return array(
            'status' => 'STAGING_IL_STATUS',
            'state' => 'ABSENT',
            'runtimeProfile' => 'ABSENT',
            'productionWriteAuthority' => false,
            'stagingMutation' => false,
            'mismatches' => array(),
            'manifest' => array('blogId' => 5, 'networkId' => 1, 'path' => '/il/', 'pages' => 9, 'locations' => 12),
            'sequence' => $sequence,
        );
    }

    if (count($rows) !== 1) {
        $mismatches[] = 'target site identity is absent, duplicated, or collides';
    } else {
        $site = $rows[0];
        foreach (array(
            'blog_id' => 5,
            'site_id' => 1,
            'domain' => 'danielj140.sg-host.com',
            'path' => '/il/',
            'public' => 0,
            'archived' => 0,
            'mature' => 0,
            'spam' => 0,
            'deleted' => 0,
            'lang_id' => 0,
        ) as $field => $expected) {
            $actual = in_array($field, array('domain', 'path'), true)
                ? (string) ($site[$field] ?? '')
                : (int) ($site[$field] ?? -1);
            if ($actual !== $expected) {
                $mismatches[] = 'target site field mismatch: ' . $field;
            }
        }
    }

    if ($siteIds !== array(1, 3, 4, 5)) {
        $mismatches[] = 'network site identifier set is not exact';
    }

    $expectedTables = twins_staging_il_expected_tables();
    sort($expectedTables, SORT_STRING);
    $frontendOnlyTables = twins_staging_il_frontend_tables();
    sort($frontendOnlyTables, SORT_STRING);
    $frontendExpectedTables = array_values(array_unique(array_merge($expectedTables, $frontendOnlyTables)));
    sort($frontendExpectedTables, SORT_STRING);
    $pristineProfile = $tables === $expectedTables && $residue['filesystem'] === array();
    $frontendProfile = $tables === $frontendExpectedTables
        && $residue['filesystem'] === array('uploads/sites/5');
    $runtimeProfile = $pristineProfile ? 'PRISTINE' : ($frontendProfile ? 'FRONTEND_INITIALIZED' : 'INVALID');

    if (!$pristineProfile && !$frontendProfile) {
        $mismatches[] = 'target table and filesystem profile is not exact';
    } else {
        $factNames = array();
        foreach ($tableFacts as $fact) {
            $factNames[] = $fact['table_name'];
            $expectedCollation = in_array($fact['table_name'], $frontendOnlyTables, true)
                ? 'utf8mb4_unicode_520_ci'
                : 'utf8mb4_unicode_ci';
            if ($fact['engine'] !== 'InnoDB' || $fact['table_collation'] !== $expectedCollation) {
                $mismatches[] = 'target table storage mismatch: ' . $fact['table_name'];
            }
        }
        $expectedFactNames = $frontendProfile ? $frontendExpectedTables : $expectedTables;
        if ($factNames !== $expectedFactNames) {
            $mismatches[] = 'target table storage evidence is incomplete';
        }
    }

    if ($sequence['autoIncrement'] !== 6 || $sequence['increment'] !== 1 || $sequence['offset'] !== 1 || $sequence['networkBlogCount'] !== 4
        || $sequence['engine'] !== 'InnoDB' || $sequence['defaultStorageEngine'] !== 'InnoDB'
        || $sequence['collation'] !== 'utf8mb4_unicode_ci'
        || $sequence['sitemetaEngine'] !== 'InnoDB'
        || $sequence['sitemetaCollation'] !== 'utf8mb4_unicode_ci') {
        $mismatches[] = 'site identifier sequence is not exact';
    }

    if ($residue['blogmeta'] !== array() || $residue['registrationLog'] !== array()) {
        $mismatches[] = 'unexpected target metadata residue exists';
    }

    $expectedUsermeta = array(
        array('user_id' => 21, 'meta_key' => 'wp8y_5_capabilities', 'meta_value' => 'a:1:{s:13:"administrator";b:1;}'),
        array('user_id' => 21, 'meta_key' => 'wp8y_5_user_level', 'meta_value' => '10'),
    );
    $actualUsermeta = array_map(static function (array $row): array {
        return array(
            'user_id' => (int) ($row['user_id'] ?? 0),
            'meta_key' => (string) ($row['meta_key'] ?? ''),
            'meta_value' => (string) ($row['meta_value'] ?? ''),
        );
    }, $residue['usermeta']);
    usort($actualUsermeta, static fn(array $left, array $right): int => strcmp($left['meta_key'], $right['meta_key']));
    if ($actualUsermeta !== $expectedUsermeta || !is_user_member_of_blog(21, 5)) {
        $mismatches[] = 'fixed owner membership is not exact';
    }

    if (!wp_is_site_initialized(5)) {
        $mismatches[] = 'target site is not initialized';
    }

    $actualPosts = twins_staging_il_posts();
    $expectedPosts = twins_staging_il_expected_posts();
    $normalizedPosts = array_map(static function (array $row): array {
        return array(
            'ID' => (int) ($row['ID'] ?? 0),
            'post_author' => (int) ($row['post_author'] ?? 0),
            'post_name' => (string) ($row['post_name'] ?? ''),
            'post_title' => (string) ($row['post_title'] ?? ''),
            'post_status' => (string) ($row['post_status'] ?? ''),
            'post_type' => (string) ($row['post_type'] ?? ''),
            'post_parent' => (int) ($row['post_parent'] ?? 0),
            'post_content' => (string) ($row['post_content'] ?? ''),
            'post_excerpt' => (string) ($row['post_excerpt'] ?? ''),
            'comment_status' => (string) ($row['comment_status'] ?? ''),
            'ping_status' => (string) ($row['ping_status'] ?? ''),
            'post_password' => (string) ($row['post_password'] ?? ''),
            'to_ping' => (string) ($row['to_ping'] ?? ''),
            'pinged' => (string) ($row['pinged'] ?? ''),
            'post_content_filtered' => (string) ($row['post_content_filtered'] ?? ''),
            'guid' => (string) ($row['guid'] ?? ''),
            'menu_order' => (int) ($row['menu_order'] ?? 0),
            'post_mime_type' => (string) ($row['post_mime_type'] ?? ''),
            'comment_count' => (int) ($row['comment_count'] ?? 0),
            'post_date' => (string) ($row['post_date'] ?? ''),
            'post_date_gmt' => (string) ($row['post_date_gmt'] ?? ''),
            'post_modified' => (string) ($row['post_modified'] ?? ''),
            'post_modified_gmt' => (string) ($row['post_modified_gmt'] ?? ''),
        );
    }, $actualPosts);
    if ($normalizedPosts !== $expectedPosts) {
        $mismatches[] = 'fixed page and location manifest is not exact';
    }
    if (array_diff($expectedTables, $tables) === array() && !twins_staging_il_final_content_is_exact()) {
        $mismatches[] = 'retained category or empty content tables are not exact';
    }

    $homeId = 0;
    foreach ($actualPosts as $post) {
        if ((string) ($post['post_name'] ?? '') === 'home' && (string) ($post['post_type'] ?? '') === 'page') {
            $homeId = (int) ($post['ID'] ?? 0);
            break;
        }
    }
    $options = twins_staging_il_options();
    $rewriteEvidence = twins_staging_il_rewrite_evidence();
    $expectedOptions = array(
        'home' => $manifest['siteUrl'],
        'siteurl' => $manifest['siteUrl'],
        'blogname' => $manifest['siteTitle'],
        'blog_public' => '0',
        'show_on_front' => 'page',
        'page_on_front' => (string) $homeId,
        'permalink_structure' => '/%postname%/',
        'template' => 'astra',
        'stylesheet' => 'astra',
        'active_plugins' => 'a:0:{}',
        'ping_sites' => '',
        'post_count' => '0',
        'twins_staging_il_phone' => $manifest['phone'],
        'twins_staging_il_tel' => $manifest['tel'],
    );
    foreach ($expectedOptions as $name => $expected) {
        if ((string) ($options[$name] ?? '') !== (string) $expected) {
            $mismatches[] = 'target option mismatch: ' . $name;
        }
    }
    if (array_key_exists('twins_staging_il_address', $options)) {
        $mismatches[] = 'Illinois address option must remain absent';
    }
    if (hash('sha256', (string) ($options[$manifest['cptOptionName']] ?? '')) !== $manifest['cptOptionSha256']) {
        $mismatches[] = 'fixed location CPT configuration is not exact';
    }
    $expectedRewriteRules = serialize(array(
        'location/?$' => 'index.php?post_type=location',
        'location/(.+?)(?:/([0-9]+))?/?$' => 'index.php?location=$matches[1]&page=$matches[2]',
    ));
    $actualRewriteRules = (string) ($rewriteEvidence['raw'] ?? '');
    $pristineRewriteRulesAreExact = $pristineProfile
        && (int) ($rewriteEvidence['rows'] ?? 0) === 1
        && (int) ($rewriteEvidence['bytes'] ?? -1) === strlen($expectedRewriteRules)
        && hash_equals($expectedRewriteRules, $actualRewriteRules);
    $frontendRewriteRulesAreExact = $frontendProfile
        && (int) ($rewriteEvidence['rows'] ?? 0) === 1
        && (int) ($rewriteEvidence['bytes'] ?? -1) === 10103
        && strlen($actualRewriteRules) === 10103
        && hash_equals(
            '901a1657f7de11dc41d6ad2ac2cd0e55bcc0314a47809e7c855effb72516845c',
            hash('sha256', $actualRewriteRules)
        );
    if (!$pristineRewriteRulesAreExact && !$frontendRewriteRulesAreExact) {
        $mismatches[] = 'location rewrite rules are unavailable';
    }

    if ($frontendProfile) {
        $frontendEvidence = twins_staging_il_frontend_runtime_evidence();
        $frontendMismatches = array_values(array_map('strval', (array) ($frontendEvidence['mismatches'] ?? array())));
        if (($frontendEvidence['valid'] ?? false) !== true
            || (string) ($frontendEvidence['profile'] ?? '') !== 'FRONTEND_INITIALIZED'
            || $frontendMismatches !== array()) {
            $runtimeProfile = 'INVALID';
            if ($frontendMismatches === array()) {
                $mismatches[] = 'frontend runtime evidence is invalid';
            } else {
                foreach ($frontendMismatches as $mismatch) {
                    $mismatches[] = $mismatch;
                }
            }
        }
    }

    $closingTables = twins_staging_il_target_tables();
    $closingTableFacts = twins_staging_il_target_table_facts();
    $closingResidue = twins_staging_il_residue();
    $closingRewriteEvidence = twins_staging_il_rewrite_evidence();
    if ($closingTables !== $tables
        || $closingTableFacts !== $tableFacts
        || $closingResidue !== $residue) {
        $mismatches[] = 'target table evidence changed while status was read';
    }
    if ($closingRewriteEvidence !== $rewriteEvidence) {
        $mismatches[] = 'target rewrite evidence changed while status was read';
    }

    return array(
        'status' => 'STAGING_IL_STATUS',
        'state' => $mismatches === array() ? 'EXACT' : 'DRIFT',
        'runtimeProfile' => $runtimeProfile,
        'productionWriteAuthority' => false,
        'stagingMutation' => false,
        'mismatches' => array_values(array_unique($mismatches)),
        'manifest' => array('blogId' => 5, 'networkId' => 1, 'path' => '/il/', 'pages' => 9, 'locations' => 12),
        'sequence' => $sequence,
    );
}

function twins_staging_il_status(): array
{
    return twins_staging_il_inspect();
}

function twins_staging_il_force_soft_flush($hard): bool
{
    unset($hard);
    return false;
}

function twins_staging_il_install_hard_flush_containment(bool &$installed): void
{
    if (!add_filter('flush_rewrite_rules_hard', 'twins_staging_il_force_soft_flush', PHP_INT_MAX, 1)) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: rewrite containment could not be installed');
    }
    $installed = true;
}

function twins_staging_il_remove_hard_flush_containment(bool &$installed): void
{
    if (!$installed) {
        return;
    }
    $removed = remove_filter('flush_rewrite_rules_hard', 'twins_staging_il_force_soft_flush', PHP_INT_MAX);
    $installed = false;
    if (!$removed) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: rewrite containment could not be removed');
    }
}

function twins_staging_il_assert_site_row(): void
{
    $rows = twins_staging_il_site_rows();
    if (count($rows) !== 1) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: fixed private site row was not readable');
    }
    $site = $rows[0];
    foreach (array(
        'blog_id' => 5,
        'site_id' => 1,
        'domain' => 'danielj140.sg-host.com',
        'path' => '/il/',
        'public' => 0,
        'archived' => 0,
        'mature' => 0,
        'spam' => 0,
        'deleted' => 0,
        'lang_id' => 0,
    ) as $field => $expected) {
        $actual = in_array($field, array('domain', 'path'), true)
            ? (string) ($site[$field] ?? '')
            : (int) ($site[$field] ?? -1);
        if ($actual !== $expected) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: private site row drifted at ' . $field);
        }
    }
}

/** @return array<string, array<int, array<string, mixed>>> */
function twins_staging_il_content_rows(): array
{
    global $wpdb;
    $queries = array(
        'comments' => 'SELECT comment_ID, comment_post_ID, comment_type FROM `wp8y_5_comments` ORDER BY comment_ID',
        'postmeta' => 'SELECT meta_id, post_id, meta_key, meta_value FROM `wp8y_5_postmeta` ORDER BY meta_id',
        'commentmeta' => 'SELECT meta_id, comment_id, meta_key, meta_value FROM `wp8y_5_commentmeta` ORDER BY meta_id',
        'terms' => 'SELECT term_id, name, slug, term_group FROM `wp8y_5_terms` ORDER BY term_id',
        'termmeta' => 'SELECT meta_id, term_id, meta_key, meta_value FROM `wp8y_5_termmeta` ORDER BY meta_id',
        'termTaxonomy' => 'SELECT term_taxonomy_id, term_id, taxonomy, description, parent, count FROM `wp8y_5_term_taxonomy` ORDER BY term_taxonomy_id',
        'termRelationships' => 'SELECT object_id, term_taxonomy_id, term_order FROM `wp8y_5_term_relationships` ORDER BY object_id, term_taxonomy_id',
        'links' => 'SELECT link_id FROM `wp8y_5_links` ORDER BY link_id',
    );
    $result = array();
    foreach ($queries as $name => $sql) {
        $wpdb->last_error = '';
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows) || (string) $wpdb->last_error !== '') {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: target content proof failed: ' . $name);
        }
        $result[$name] = array_values(array_map(static fn($row): array => (array) $row, $rows));
    }
    return $result;
}

function twins_staging_il_final_content_is_exact(): bool
{
    try {
        $content = twins_staging_il_content_rows();
    } catch (Throwable $error) {
        unset($error);
        return false;
    }
    $expected = array(
        'comments' => array(),
        'postmeta' => array(),
        'commentmeta' => array(),
        'terms' => array(array('term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized', 'term_group' => 0)),
        'termmeta' => array(),
        'termTaxonomy' => array(array('term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 0)),
        'termRelationships' => array(),
        'links' => array(),
    );
    foreach ($expected as $name => $expectedRows) {
        $normalized = array_map(static function (array $row) use ($expectedRows): array {
            $template = $expectedRows[0] ?? array();
            $picked = array();
            foreach ($template as $field => $value) {
                $picked[$field] = is_int($value) ? (int) ($row[$field] ?? -1) : (string) ($row[$field] ?? '');
            }
            return $picked;
        }, $content[$name]);
        if ($normalized !== $expectedRows) {
            return false;
        }
    }
    return true;
}

function twins_staging_il_assert_default_baseline(): void
{
    $posts = twins_staging_il_posts();
    $content = twins_staging_il_content_rows();
    if (count($posts) !== 2) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: default post baseline is not exact');
    }
    $postExpectations = array(
        array('ID' => 1, 'post_author' => 21, 'post_name' => 'hello-world', 'post_title' => 'Hello world!', 'post_status' => 'publish', 'post_type' => 'post', 'post_parent' => 0, 'comment_status' => 'open', 'comment_count' => 1, 'guid' => 'https://danielj140.sg-host.com/il/?p=1'),
        array('ID' => 2, 'post_author' => 21, 'post_name' => 'sample-page', 'post_title' => 'Sample Page', 'post_status' => 'publish', 'post_type' => 'page', 'post_parent' => 0, 'comment_status' => 'closed', 'comment_count' => 0, 'guid' => 'https://danielj140.sg-host.com/il/?page_id=2'),
    );
    foreach ($postExpectations as $index => $expected) {
        $actual = $posts[$index];
        foreach ($expected as $field => $value) {
            $normalized = in_array($field, array('ID', 'post_author', 'post_parent', 'comment_count'), true)
                ? (int) ($actual[$field] ?? -1)
                : (string) ($actual[$field] ?? '');
            if ($normalized !== $value) {
                throw new RuntimeException('STAGING_IL_PROVISION_FAILED: default post baseline drifted: ' . $field);
            }
        }
        if ((string) ($actual['post_content'] ?? '') === '') {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: default post content is unexpectedly empty');
        }
    }
    $expectedContent = array(
        'comments' => array(array('comment_ID' => 1, 'comment_post_ID' => 1, 'comment_type' => 'comment')),
        'postmeta' => array(array('meta_id' => 1, 'post_id' => 2, 'meta_key' => '_wp_page_template', 'meta_value' => 'default')),
        'commentmeta' => array(),
        'terms' => array(array('term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized', 'term_group' => 0)),
        'termmeta' => array(),
        'termTaxonomy' => array(array('term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 1)),
        'termRelationships' => array(array('object_id' => 1, 'term_taxonomy_id' => 1, 'term_order' => 0)),
        'links' => array(),
    );
    foreach ($expectedContent as $name => $expectedRows) {
        $normalized = array_map(static function (array $row) use ($expectedRows): array {
            $template = $expectedRows[0] ?? array();
            $picked = array();
            foreach ($template as $field => $value) {
                $picked[$field] = is_int($value) ? (int) ($row[$field] ?? -1) : (string) ($row[$field] ?? '');
            }
            return $picked;
        }, $content[$name]);
        if ($normalized !== $expectedRows) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: default content baseline drifted: ' . $name);
        }
    }
}

function twins_staging_il_require_one($result, string $label): void
{
    if ($result !== 1) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: exact default transition failed: ' . $label);
    }
}

function twins_staging_il_clear_defaults(): void
{
    global $wpdb;
    twins_staging_il_assert_default_baseline();
    twins_staging_il_require_one($wpdb->delete('wp8y_5_comments', array('comment_ID' => 1, 'comment_post_ID' => 1)), 'comment');
    twins_staging_il_require_one($wpdb->delete('wp8y_5_postmeta', array('meta_id' => 1, 'post_id' => 2, 'meta_key' => '_wp_page_template', 'meta_value' => 'default')), 'page template metadata');
    twins_staging_il_require_one($wpdb->delete('wp8y_5_term_relationships', array('object_id' => 1, 'term_taxonomy_id' => 1)), 'category relationship');
    twins_staging_il_require_one($wpdb->update(
        'wp8y_5_term_taxonomy',
        array('count' => 0),
        array('term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category', 'count' => 1)
    ), 'category count');
    twins_staging_il_require_one($wpdb->delete('wp8y_5_posts', array('ID' => 1, 'post_name' => 'hello-world', 'post_type' => 'post', 'post_status' => 'publish')), 'hello post');
    twins_staging_il_require_one($wpdb->delete('wp8y_5_posts', array('ID' => 2, 'post_name' => 'sample-page', 'post_type' => 'page', 'post_status' => 'publish')), 'sample page');

    $content = twins_staging_il_content_rows();
    $expectedRetained = array(
        'comments' => array(),
        'postmeta' => array(),
        'commentmeta' => array(),
        'terms' => array(array('term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized', 'term_group' => 0)),
        'termmeta' => array(),
        'termTaxonomy' => array(array('term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 0)),
        'termRelationships' => array(),
        'links' => array(),
    );
    if (twins_staging_il_posts() !== array()) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: default posts remain after exact transition');
    }
    foreach ($expectedRetained as $name => $expectedRows) {
        $normalized = array_map(static function (array $row) use ($expectedRows): array {
            $template = $expectedRows[0] ?? array();
            $picked = array();
            foreach ($template as $field => $value) {
                $picked[$field] = is_int($value) ? (int) ($row[$field] ?? -1) : (string) ($row[$field] ?? '');
            }
            return $picked;
        }, $content[$name]);
        if ($normalized !== $expectedRows) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: retained core baseline drifted: ' . $name);
        }
    }
}

/** @return array<string, mixed>|null */
function twins_staging_il_post_by_id(int $postId): ?array
{
    foreach (twins_staging_il_posts() as $post) {
        if ((int) ($post['ID'] ?? 0) === $postId) {
            return $post;
        }
    }
    return null;
}

function twins_staging_il_insert_post(array $expected): int
{
    global $wpdb;
    $data = array(
        'ID' => (int) $expected['ID'],
        'post_author' => (int) $expected['post_author'],
        'post_date' => (string) $expected['post_date'],
        'post_date_gmt' => (string) $expected['post_date_gmt'],
        'post_name' => (string) $expected['post_name'],
        'post_title' => (string) $expected['post_title'],
        'post_content' => '',
        'post_excerpt' => '',
        'post_status' => (string) $expected['post_status'],
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_password' => '',
        'to_ping' => '',
        'pinged' => '',
        'post_content_filtered' => '',
        'post_modified' => (string) $expected['post_modified'],
        'post_modified_gmt' => (string) $expected['post_modified_gmt'],
        'post_type' => (string) $expected['post_type'],
        'post_parent' => (int) $expected['post_parent'],
        'menu_order' => 0,
        'post_mime_type' => '',
        'comment_count' => 0,
        'guid' => (string) $expected['guid'],
    );
    $inserted = $wpdb->insert('wp8y_5_posts', $data);
    if ($inserted === false) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: fixed post insert failed: ' . (string) $wpdb->last_error);
    }
    $postId = (int) $wpdb->insert_id;
    $actual = twins_staging_il_post_by_id($postId);
    if (!is_array($actual)) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: fixed post read-back is missing');
    }
    foreach ($expected as $field => $expectedValue) {
        $actualValue = in_array($field, array('ID', 'post_author', 'post_parent', 'menu_order', 'comment_count'), true)
            ? (int) ($actual[$field] ?? -1)
            : (string) ($actual[$field] ?? '');
        if ($actualValue !== $expectedValue) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: fixed post read-back drifted at ' . $field);
        }
    }
    return $postId;
}

function twins_staging_il_set_option(string $name, $value): void
{
    global $wpdb;
    $serialized = (is_scalar($value) || $value === null)
        ? (string) $value
        : maybe_serialize($value);
    $options = twins_staging_il_options();
    if (array_key_exists($name, $options)) {
        $written = $wpdb->update(
            'wp8y_5_options',
            array('option_value' => $serialized),
            array('option_name' => $name)
        );
    } else {
        $written = $wpdb->insert(
            'wp8y_5_options',
            array('option_name' => $name, 'option_value' => $serialized, 'autoload' => 'yes')
        );
    }
    if ($written === false) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: fixed option write failed: ' . $name);
    }
    $readBack = twins_staging_il_options();
    if (!array_key_exists($name, $readBack) || (string) $readBack[$name] !== (string) $serialized) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: fixed option read-back drifted: ' . $name);
    }
}

/** @return array<int, string> */
function twins_staging_il_rewrite_hashes(): array
{
    global $wpdb;
    $tables = array(
        1 => 'wp8y_options',
        3 => 'wp8y_3_options',
        4 => 'wp8y_4_options',
    );
    $hashes = array();
    foreach ($tables as $blogId => $table) {
        $wpdb->last_error = '';
        $rows = $wpdb->get_results(
            "SELECT option_id,
                    option_name,
                    OCTET_LENGTH(option_value) AS value_bytes,
                    LEFT(CAST(option_value AS BINARY), 1048576) AS bounded_value
             FROM `{$table}`
             WHERE option_name = 'rewrite_rules'
             ORDER BY option_id ASC
             LIMIT 2",
            ARRAY_A
        );
        if (!is_array($rows) || (string) $wpdb->last_error !== '') {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: non-target rewrite evidence read failed');
        }
        if (count($rows) > 1) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: non-target rewrite evidence is ambiguous');
        }
        if ($rows === array()) {
            $hashes[$blogId] = hash('sha256', "ABSENT\0" . $table);
            continue;
        }

        $row = (array) $rows[0];
        $valueBytes = (int) ($row['value_bytes'] ?? -1);
        $boundedValue = (string) ($row['bounded_value'] ?? '');
        $returnedBytes = strlen($boundedValue);
        if ((string) ($row['option_name'] ?? '') !== 'rewrite_rules'
            || $valueBytes < 0
            || $returnedBytes > 1048576
            || $returnedBytes !== min($valueBytes, 1048576)) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: non-target rewrite evidence is malformed');
        }
        if ($valueBytes > 1048576) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: non-target rewrite evidence exceeds fixed byte limit');
        }
        $hashes[$blogId] = hash('sha256', "PRESENT\0" . $table . "\0" . $boundedValue);
    }
    return $hashes;
}

/** @return array<string, mixed> */
function twins_staging_il_location_arguments(): array
{
    return array(
        'label' => 'Locations',
        'labels' => array('name' => 'Locations', 'singular_name' => 'Location'),
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'hierarchical' => true,
        'rewrite' => array('slug' => 'location', 'with_front' => true),
        'query_var' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'page-attributes'),
        'taxonomies' => array('category', 'post_tag'),
    );
}

function twins_staging_il_seed_fixed_content(): int
{
    $homeId = 0;
    foreach (twins_staging_il_expected_posts() as $index => $post) {
        $postId = twins_staging_il_insert_post($post);
        if ($index === 0) {
            $homeId = $postId;
        }
    }
    if ($homeId < 1) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: fixed homepage was not created');
    }
    return $homeId;
}

function twins_staging_il_write_options(int $homeId): void
{
    $manifest = twins_staging_il_manifest();
    foreach (array(
        'home' => $manifest['siteUrl'],
        'siteurl' => $manifest['siteUrl'],
        'blogname' => $manifest['siteTitle'],
        'blogdescription' => '',
        'blog_public' => 0,
        'show_on_front' => 'page',
        'page_on_front' => $homeId,
        'page_for_posts' => 0,
        'permalink_structure' => '/%postname%/',
        'default_role' => 'subscriber',
        'default_category' => 1,
        'template' => 'astra',
        'stylesheet' => 'astra',
        'active_plugins' => array(),
        'ping_sites' => '',
        'post_count' => 0,
        'twins_staging_il_phone' => $manifest['phone'],
        'twins_staging_il_tel' => $manifest['tel'],
        $manifest['cptOptionName'] => $manifest['cptOptionRaw'],
    ) as $name => $value) {
        twins_staging_il_set_option((string) $name, $value);
    }
}

function twins_staging_il_clear_target_option_cache(): void
{
    if (get_current_blog_id() !== 5) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: option-cache quarantine is outside Illinois');
    }
    foreach (array(
        'alloptions',
        'notoptions',
        'home',
        'siteurl',
        'blogname',
        'blog_public',
        'show_on_front',
        'page_on_front',
        'permalink_structure',
        'template',
        'stylesheet',
        'active_plugins',
        'ping_sites',
        'post_count',
        'cptui_post_types',
        'rewrite_rules',
    ) as $key) {
        wp_cache_delete($key, 'options');
    }
}

/** @return array<string, mixed> */
function twins_staging_il_refusal(string $error, string $beforeState = 'UNKNOWN'): array
{
    return array(
        'status' => 'STAGING_IL_REFUSED',
        'beforeState' => $beforeState,
        'productionWriteAuthority' => false,
        'stagingMutation' => false,
        'error' => $error,
    );
}

function twins_staging_il_finish_insert_guard(bool &$transactionOpen): void
{
    global $wpdb;
    if (!$transactionOpen) {
        return;
    }
    $committed = $wpdb->query('COMMIT');
    $transactionOpen = false;
    if ($committed === false) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: insertion guard could not be finalized');
    }
}

function twins_staging_il_insert_guarded_site(bool &$mutationStarted): void
{
    global $wpdb;
    $transactionOpen = false;
    $pendingError = null;
    $timestamp = gmdate('Y-m-d H:i:s');
    $siteRow = array(
        'blog_id' => 5,
        'site_id' => 1,
        'domain' => 'danielj140.sg-host.com',
        'path' => '/il/',
        'registered' => $timestamp,
        'last_updated' => $timestamp,
        'public' => 0,
        'archived' => 0,
        'mature' => 0,
        'spam' => 0,
        'deleted' => 0,
        'lang_id' => 0,
    );

    if ($wpdb->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
        || $wpdb->query('START TRANSACTION') === false) {
        throw new RuntimeException('STAGING_IL_REFUSED: insertion guard could not start');
    }
    $transactionOpen = true;

    try {
        $wpdb->last_error = '';
        $rows = $wpdb->get_results(
            'SELECT blog_id, site_id, domain, path, public, archived, mature, spam, deleted, lang_id
             FROM `wp8y_blogs` FORCE INDEX (PRIMARY)
             WHERE blog_id >= 1
             ORDER BY blog_id ASC
             FOR UPDATE NOWAIT',
            ARRAY_A
        );
        if ((string) $wpdb->last_error !== '' || !is_array($rows)) {
            throw new RuntimeException('STAGING_IL_REFUSED: nonblocking insertion guard conflicted');
        }
        $actualRows = array_map(static function ($row): array {
            $row = (array) $row;
            return array(
                'blog_id' => (int) ($row['blog_id'] ?? 0),
                'site_id' => (int) ($row['site_id'] ?? 0),
                'domain' => (string) ($row['domain'] ?? ''),
                'path' => (string) ($row['path'] ?? ''),
                'public' => (int) ($row['public'] ?? -1),
                'archived' => (int) ($row['archived'] ?? -1),
                'mature' => (int) ($row['mature'] ?? -1),
                'spam' => (int) ($row['spam'] ?? -1),
                'deleted' => (int) ($row['deleted'] ?? -1),
                'lang_id' => (int) ($row['lang_id'] ?? -1),
            );
        }, $rows);
        $expectedRows = array(
            array('blog_id' => 1, 'site_id' => 1, 'domain' => 'danielj140.sg-host.com', 'path' => '/', 'public' => 0, 'archived' => 0, 'mature' => 0, 'spam' => 0, 'deleted' => 0, 'lang_id' => 0),
            array('blog_id' => 3, 'site_id' => 1, 'domain' => 'danielj140.sg-host.com', 'path' => '/ky/', 'public' => 0, 'archived' => 0, 'mature' => 0, 'spam' => 0, 'deleted' => 0, 'lang_id' => 0),
            array('blog_id' => 4, 'site_id' => 1, 'domain' => 'danielj140.sg-host.com', 'path' => '/wi/', 'public' => 0, 'archived' => 0, 'mature' => 0, 'spam' => 0, 'deleted' => 0, 'lang_id' => 0),
        );
        $owner = get_user_by('id', 21);
        if ($actualRows !== $expectedRows
            || !is_object($owner)
            || (string) ($owner->user_login ?? '') !== 'chatgptprofile1stage'
            || !is_super_admin(21)) {
            throw new RuntimeException('STAGING_IL_REFUSED: locked insertion preconditions changed');
        }
        $networkDefaults = twins_staging_il_network_defaults(true);
        if ($networkDefaults['blogCountRaw'] !== '3') {
            throw new RuntimeException('STAGING_IL_REFUSED: locked network defaults changed');
        }

        $guardTables = twins_staging_il_target_tables();
        $guardTableFacts = twins_staging_il_target_table_facts();
        $guardResidue = twins_staging_il_residue();
        if ($guardTables !== array()
            || $guardTableFacts !== array()
            || $guardResidue['usermeta'] !== array()
            || $guardResidue['blogmeta'] !== array()
            || $guardResidue['registrationLog'] !== array()
            || $guardResidue['filesystem'] !== array()) {
            throw new RuntimeException('STAGING_IL_REFUSED: insertion residue changed');
        }

        $mutationStarted = true;
        $inserted = $wpdb->insert($wpdb->blogs, $siteRow);
        if ($inserted !== 1 || (int) $wpdb->rows_affected !== 1) {
            throw new RuntimeException('STAGING_IL_REFUSED: fixed private site row insert failed: ' . (string) $wpdb->last_error);
        }
        twins_staging_il_assert_site_row();
    } catch (Throwable $error) {
        $pendingError = $error;
    }

    try {
        twins_staging_il_finish_insert_guard($transactionOpen);
    } catch (Throwable $error) {
        if (!($pendingError instanceof Throwable)) {
            $pendingError = $error;
        }
    }
    if ($pendingError instanceof Throwable) {
        throw $pendingError;
    }
    clean_blog_cache(5);
}

function twins_staging_il_update_network_count(): void
{
    global $wpdb;
    $updated = $wpdb->update(
        'wp8y_sitemeta',
        array('meta_value' => '4'),
        array('site_id' => 1, 'meta_key' => 'blog_count', 'meta_value' => '3')
    );
    if ($updated !== 1 || (int) $wpdb->rows_affected !== 1) {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: network site-count transition failed');
    }
    wp_cache_delete('1:blog_count', 'site-options');
    $readBack = $wpdb->get_var("SELECT meta_value FROM `wp8y_sitemeta` WHERE site_id = 1 AND meta_key = 'blog_count' LIMIT 1");
    if ((string) $readBack !== '4') {
        throw new RuntimeException('STAGING_IL_PROVISION_FAILED: network site-count read-back failed');
    }
}

function twins_staging_il_provision(bool $dryRun): array
{
    global $wpdb, $wp_rewrite;
    $before = twins_staging_il_status();
    $beforeState = (string) ($before['state'] ?? 'UNKNOWN');

    if ($dryRun) {
        if (!in_array($beforeState, array('ABSENT', 'EXACT'), true)) {
            return twins_staging_il_refusal('STAGING_IL_REFUSED: dry-run found drift', $beforeState);
        }
        return array(
            'status' => 'STAGING_IL_DRY_RUN',
            'beforeState' => $beforeState,
            'projectedState' => 'EXACT',
            'productionWriteAuthority' => false,
            'stagingMutation' => false,
            'manifest' => array('blogId' => 5, 'networkId' => 1, 'path' => '/il/', 'pages' => 9, 'locations' => 12),
        );
    }

    if ($beforeState !== 'ABSENT') {
        return twins_staging_il_refusal('STAGING_IL_REFUSED: apply requires a clean ABSENT state', $beforeState);
    }

    $lockOwned = false;
    $mutationStarted = false;
    $hardFlushContained = false;
    $result = twins_staging_il_refusal('STAGING_IL_REFUSED: apply did not start', $beforeState);

    try {
        twins_staging_il_assert_apply_runtime();
        $lock = $wpdb->get_var("SELECT GET_LOCK('twins-staging-il-provision-v1', 0)");
        if ((int) $lock !== 1) {
            throw new RuntimeException('STAGING_IL_REFUSED: fixed advisory lock is busy');
        }
        $lockOwned = true;

        $locked = twins_staging_il_status();
        if (($locked['state'] ?? null) !== 'ABSENT') {
            throw new RuntimeException('STAGING_IL_REFUSED: state changed before the write phase');
        }

        $rewriteHashesBefore = twins_staging_il_rewrite_hashes();
        $manifest = twins_staging_il_manifest();
        $backupDigest = twins_staging_il_backup_digest();
        if (!hash_equals($manifest['backupSha256'], $backupDigest)) {
            throw new RuntimeException('STAGING_IL_REFUSED: fixed backup digest mismatch');
        }
        twins_staging_il_insert_guarded_site($mutationStarted);
        twins_staging_il_assert_site_row();

        $htaccessBefore = twins_staging_il_htaccess_digest();
        twins_staging_il_install_hard_flush_containment($hardFlushContained);
        $initializationError = null;
        $initializationResult = null;
        if ((string) ($wpdb->charset ?? '') !== 'utf8mb4') {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: database charset changed before initialization');
        }
        $originalCollation = (string) ($wpdb->collate ?? '');
        $wpdb->collate = 'utf8mb4_unicode_ci';
        try {
            $initializationResult = wp_initialize_site(5, array(
                'user_id' => 21,
                'title' => $manifest['siteTitle'],
                'options' => array(
                    'blog_public' => 0,
                    'home' => $manifest['siteUrl'],
                    'siteurl' => $manifest['siteUrl'],
                ),
            ));
        } catch (Throwable $error) {
            $initializationError = $error;
        } finally {
            $wpdb->collate = $originalCollation;
        }
        twins_staging_il_remove_hard_flush_containment($hardFlushContained);
        $htaccessAfter = twins_staging_il_htaccess_digest();
        if (!hash_equals($htaccessBefore, $htaccessAfter)) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: .htaccess changed during initialization');
        }
        if ($initializationError instanceof Throwable) {
            throw $initializationError;
        }
        if (is_wp_error($initializationResult) || $initializationResult !== true) {
            $message = is_wp_error($initializationResult)
                ? $initializationResult->get_error_code() . ': ' . $initializationResult->get_error_message()
                : 'unexpected initializer result';
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: ' . $message);
        }

        twins_staging_il_assert_site_row();
        if (!wp_is_site_initialized(5)) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: core did not initialize the fixed site');
        }
        $tables = twins_staging_il_target_tables();
        $expectedTables = twins_staging_il_expected_tables();
        sort($expectedTables, SORT_STRING);
        if ($tables !== $expectedTables) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: core table set is not exact');
        }
        twins_staging_il_assert_target_storage();

        twins_staging_il_clear_defaults();
        $homeId = twins_staging_il_seed_fixed_content();
        twins_staging_il_write_options($homeId);

        if (!is_object($wp_rewrite) || !method_exists($wp_rewrite, 'init') || !switch_to_blog(5)) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: target rewrite context failed');
        }
        $locationRegistered = false;
        $rewriteCleanupError = null;
        try {
            twins_staging_il_clear_target_option_cache();
            $wp_rewrite->init();
            $registered = register_post_type('location', twins_staging_il_location_arguments());
            if (is_wp_error($registered)) {
                throw new RuntimeException('STAGING_IL_PROVISION_FAILED: location post type registration failed');
            }
            $locationRegistered = true;
            flush_rewrite_rules(false);
        } finally {
            if ($locationRegistered) {
                $unregistered = unregister_post_type('location');
                if (is_wp_error($unregistered)) {
                    $rewriteCleanupError = new RuntimeException('STAGING_IL_PROVISION_FAILED: temporary location registration was not removed');
                }
            }
            if (!restore_current_blog()) {
                $rewriteCleanupError = new RuntimeException('STAGING_IL_PROVISION_FAILED: target rewrite context was not restored');
            }
            $wp_rewrite->init();
            if ($rewriteCleanupError instanceof Throwable) {
                throw $rewriteCleanupError;
            }
        }
        $htaccessFinal = twins_staging_il_htaccess_digest();
        if (!hash_equals($htaccessBefore, $htaccessFinal)) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: .htaccess changed during final soft rewrite');
        }

        if (twins_staging_il_rewrite_hashes() !== $rewriteHashesBefore) {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: another staging site rewrite changed');
        }
        twins_staging_il_update_network_count();
        $after = twins_staging_il_status();
        if (($after['state'] ?? null) !== 'EXACT') {
            throw new RuntimeException('STAGING_IL_PROVISION_FAILED: final exact-state proof failed: ' . implode('; ', (array) ($after['mismatches'] ?? array())));
        }
        $result = array(
            'status' => 'STAGING_IL_PROVISION_OK',
            'beforeState' => 'ABSENT',
            'afterState' => 'EXACT',
            'productionWriteAuthority' => false,
            'stagingMutation' => true,
            'manifest' => array('blogId' => 5, 'networkId' => 1, 'path' => '/il/', 'pages' => 9, 'locations' => 12),
        );
    } catch (Throwable $error) {
        $result = array(
            'status' => $mutationStarted ? 'STAGING_IL_PROVISION_FAILED' : 'STAGING_IL_REFUSED',
            'beforeState' => $beforeState,
            'productionWriteAuthority' => false,
            'stagingMutation' => $mutationStarted,
            'error' => $error->getMessage(),
        );
    } finally {
        if ($hardFlushContained) {
            try {
                twins_staging_il_remove_hard_flush_containment($hardFlushContained);
            } catch (Throwable $cleanupError) {
                $result['status'] = $mutationStarted ? 'STAGING_IL_PROVISION_FAILED' : 'STAGING_IL_REFUSED';
                $result['error'] = $cleanupError->getMessage();
            }
        }
        if (get_current_blog_id() !== 1) {
            if (!restore_current_blog()) {
                $result['status'] = $mutationStarted ? 'STAGING_IL_PROVISION_FAILED' : 'STAGING_IL_REFUSED';
                $result['error'] = 'STAGING_IL_PROVISION_FAILED: main staging context was not restored';
            }
        }
        if ($lockOwned) {
            $released = $wpdb->get_var("SELECT RELEASE_LOCK('twins-staging-il-provision-v1')");
            if ((int) $released !== 1) {
                $result['status'] = $mutationStarted ? 'STAGING_IL_PROVISION_FAILED' : 'STAGING_IL_REFUSED';
                $result['error'] = 'STAGING_IL_PROVISION_FAILED: advisory lock release was not confirmed';
            }
        }
    }

    return $result;
}

function twins_staging_il_cli_exit_code(array $result): int
{
    return in_array((string) ($result['status'] ?? ''), array(
        'STAGING_IL_STATUS',
        'STAGING_IL_DRY_RUN',
        'STAGING_IL_PROVISION_OK',
    ), true) ? 0 : 1;
}

if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
    $mode = (string) getenv('TWINS_STAGING_IL_MODE');
    $mode = $mode === '' ? 'status' : $mode;
    if ($mode === 'status') {
        $cliResult = twins_staging_il_status();
    } elseif ($mode === 'provision') {
        $dryValue = (string) getenv('TWINS_STAGING_IL_DRY_RUN');
        if (!in_array($dryValue, array('0', '1'), true)) {
            $cliResult = twins_staging_il_refusal('STAGING_IL_REFUSED: dry-run mode must be exactly 0 or 1');
        } else {
            $cliResult = twins_staging_il_provision($dryValue === '1');
        }
    } else {
        $cliResult = twins_staging_il_refusal('STAGING_IL_REFUSED: mode must be status or provision');
    }
    WP_CLI::line((string) wp_json_encode($cliResult, JSON_UNESCAPED_SLASHES));
    WP_CLI::halt(twins_staging_il_cli_exit_code($cliResult));
}
