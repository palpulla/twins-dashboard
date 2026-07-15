<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');
define('WP_ENVIRONMENT_TYPE', 'staging');
define('TWINS_STAGING_SAFETY', true);
define('DISABLE_WP_CRON', true);
define('WP_CLI', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
define('OBJECT', 'OBJECT');
define('ARRAY_A', 'ARRAY_A');
$GLOBALS['wp_version'] = '7.0.1';

final class WP_Error
{
    private string $code;
    private string $message;

    public function __construct(string $code = '', string $message = '')
    {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

$GLOBALS['twins_staging_il_expected_core'] = [
    ['slug' => '', 'title' => 'Garage Door Service in Rockford, Illinois'],
    ['slug' => 'garage-door-services', 'title' => 'Garage Door Services'],
    ['slug' => 'garage-door-repair', 'title' => 'Garage Door Repair'],
    ['slug' => 'garage-door-installation', 'title' => 'Garage Door Installation'],
    ['slug' => 'garage-door-openers', 'title' => 'Garage Door Openers'],
    ['slug' => 'emergency-garage-services', 'title' => 'Emergency Garage Door Service'],
    ['slug' => 'locations', 'title' => 'Illinois Service Areas'],
    ['slug' => 'contact-us', 'title' => 'Contact Twins Garage Doors'],
    ['slug' => 'door-builder', 'title' => 'Design Your Garage Door'],
];
$GLOBALS['twins_staging_il_expected_cities'] = [
    'rockford',
    'loves-park',
    'machesney-park',
    'belvidere',
    'roscoe',
    'rockton',
    'cherry-valley',
    'poplar-grove',
    'south-beloit',
    'winnebago',
    'byron',
    'caledonia',
];
$GLOBALS['twins_staging_il_city_titles'] = [
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
];
$GLOBALS['twins_staging_il_expected_frontend_tables'] = [
    'wp8y_5_gf_draft_submissions',
    'wp8y_5_gf_entry',
    'wp8y_5_gf_entry_meta',
    'wp8y_5_gf_entry_notes',
    'wp8y_5_gf_form',
    'wp8y_5_gf_form_meta',
    'wp8y_5_gf_form_revisions',
    'wp8y_5_gf_form_view',
];

function twins_staging_il_harness_site_row(
    int $blogId,
    string $domain,
    string $path,
    int $public
): array {
    return [
        'blog_id' => $blogId,
        'site_id' => 1,
        'domain' => $domain,
        'path' => $path,
        'registered' => '2026-07-14 12:00:00',
        'last_updated' => '2026-07-14 12:00:00',
        'public' => $public,
        'archived' => 0,
        'mature' => 0,
        'spam' => 0,
        'deleted' => 0,
        'lang_id' => 0,
    ];
}

function twins_staging_il_harness_reset(): void
{
    $GLOBALS['wp_version'] = '7.0.1';
    $GLOBALS['twins_staging_il_state'] = [
        'multisite' => true,
        'networkHomeUrl' => 'https://danielj140.sg-host.com',
        'homeUrl' => 'https://danielj140.sg-host.com',
        'networkId' => 1,
        'mainSiteId' => 1,
        'currentBlogId' => 1,
        'blogStack' => [],
        'users' => [
            21 => ['ID' => 21, 'user_login' => 'chatgptprofile1stage'],
        ],
        'superAdminIds' => [21],
        'memberships' => [1 => [21], 3 => [21], 4 => [21]],
        'sites' => [
            1 => twins_staging_il_harness_site_row(1, 'danielj140.sg-host.com', '/', 0),
            3 => twins_staging_il_harness_site_row(3, 'danielj140.sg-host.com', '/ky/', 0),
            4 => twins_staging_il_harness_site_row(4, 'danielj140.sg-host.com', '/wi/', 0),
        ],
        'tables' => [
            'wp8y_posts', 'wp8y_options', 'wp8y_3_posts', 'wp8y_3_options',
            'wp8y_4_posts', 'wp8y_4_options',
        ],
        'posts' => [1 => [], 3 => [], 4 => []],
        'comments' => [1 => [], 3 => [], 4 => []],
        'postmetaRows' => [1 => [], 3 => [], 4 => []],
        'commentmetaRows' => [1 => [], 3 => [], 4 => []],
        'termsRows' => [1 => [], 3 => [], 4 => []],
        'termmetaRows' => [1 => [], 3 => [], 4 => []],
        'termTaxonomyRows' => [1 => [], 3 => [], 4 => []],
        'termRelationshipRows' => [1 => [], 3 => [], 4 => []],
        'linksRows' => [1 => [], 3 => [], 4 => []],
        'options' => [
            1 => ['home' => 'https://danielj140.sg-host.com', 'siteurl' => 'https://danielj140.sg-host.com', 'rewrite_rules' => 'main-rules-unchanged'],
            3 => ['rewrite_rules' => 'ky-rules-unchanged'],
            4 => ['rewrite_rules' => 'wi-rules-unchanged'],
        ],
        'usermeta' => [
            ['umeta_id' => 1020, 'user_id' => 21, 'meta_key' => 'primary_blog', 'meta_value' => '1'],
        ],
        'blogmeta' => [],
        'registrationLog' => [],
        'nextPostId' => 5000,
        'nextOptionId' => 100,
        'autoIncrement' => 5,
        'cachedAutoIncrement' => 4,
        'autoIncrementStep' => 1,
        'autoIncrementOffset' => 1,
        'defaultStorageEngine' => 'InnoDB',
        'coordinationStorage' => [
            'wp8y_blogs' => ['engine' => 'InnoDB', 'table_collation' => 'utf8mb4_unicode_ci'],
            'wp8y_sitemeta' => ['engine' => 'InnoDB', 'table_collation' => 'utf8mb4_unicode_ci'],
        ],
        'blogPrefixOverride' => null,
        'blogTableMapOverride' => null,
        'blogCount' => 3,
        'duplicateBlogCount' => false,
        'defaultPrivacyPolicyContent' => null,
        'initializedSites' => [1, 3, 4],
        'hookChecks' => [],
        'unsafeHooks' => [],
        'coreHooks' => ['wp_initialize_site' => 10],
        'registeredPostTypes' => [],
        'operations' => [],
        'mutations' => [],
        'controls' => [],
        'backupChecks' => 0,
        'filesystemResidue' => [],
        'frontendRuntimeEvidence' => [
            'valid' => false,
            'profile' => 'UNAVAILABLE',
            'mismatches' => ['frontend runtime evidence was not requested'],
        ],
        'filters' => [],
        'filterCalls' => [],
        'htaccessDigest' => 'fixed-htaccess-digest',
        'htaccessChecks' => 0,
        'lockAvailable' => true,
        'lockHeld' => false,
        'rowGuardAvailable' => true,
        'transactionOpen' => false,
        'dbInsertAttempts' => 0,
        'dbInsertFailureAt' => null,
        'postInsertAttempts' => 0,
        'postDriftAt' => [],
        'initializeFailure' => false,
        'initializeReturnError' => false,
        'initializeBaselineDrift' => false,
        'initializeStorageDrift' => false,
        'tableStorageOverrides' => [],
        'runtimeEvidenceValid' => true,
        'muPhpFiles' => ['twins-staging-safety.php'],
        'overhaulDigest' => '',
        'astraAllowed' => true,
        'installerOverrideAbsent' => true,
        'locale' => 'en_US',
        'dbReadFailureNeedle' => null,
        'targetTableNameReads' => 0,
        'targetTableDriftOnNameRead' => null,
        'optionReadQueries' => [],
        'optionNamesReturned' => [],
        'maxOptionValueBytesReturned' => 0,
        'rewriteEvidenceReads' => 0,
        'rewriteDriftOnEvidenceRead' => null,
        'cachedOptions' => [
            1 => ['rewrite_rules' => 'main-rules-unchanged'],
            3 => ['rewrite_rules' => 'ky-rules-unchanged'],
            4 => ['rewrite_rules' => 'wi-rules-unchanged'],
        ],
        'nonTargetRewriteEvidenceReads' => 0,
        'maxNonTargetRewriteValueBytesReturned' => 0,
        'nonTargetRewriteDriftOnFlush' => false,
        'nonTargetRewriteDriftApplied' => false,
    ];
    if (isset($GLOBALS['wpdb']) && $GLOBALS['wpdb'] instanceof Twins_Staging_IL_Harness_WPDB) {
        foreach ([
            'users' => 'wp8y_users',
            'usermeta' => 'wp8y_usermeta',
            'blogs' => 'wp8y_blogs',
            'blogmeta' => 'wp8y_blogmeta',
            'signups' => 'wp8y_signups',
            'site' => 'wp8y_site',
            'sitemeta' => 'wp8y_sitemeta',
            'registration_log' => 'wp8y_registration_log',
        ] as $property => $table) {
            $GLOBALS['wpdb']->{$property} = $table;
        }
        $GLOBALS['wpdb']->set_blog_id(1);
    }
}

function twins_staging_il_harness_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function twins_staging_il_harness_rows(array $rows, $output): array
{
    if ($output === ARRAY_A) {
        return array_map(static function ($row): array {
            return is_object($row) ? get_object_vars($row) : (array) $row;
        }, $rows);
    }
    return array_map(static function ($row): object {
        return is_object($row) ? clone $row : (object) $row;
    }, $rows);
}

final class Twins_Staging_IL_Harness_WPDB
{
    public string $base_prefix = 'wp8y_';
    public string $prefix = 'wp8y_';
    public string $blogs = 'wp8y_blogs';
    public string $blogmeta = 'wp8y_blogmeta';
    public string $users = 'wp8y_users';
    public string $usermeta = 'wp8y_usermeta';
    public string $registration_log = 'wp8y_registration_log';
    public string $signups = 'wp8y_signups';
    public string $site = 'wp8y_site';
    public string $sitemeta = 'wp8y_sitemeta';
    public string $posts = 'wp8y_posts';
    public string $postmeta = 'wp8y_postmeta';
    public string $options = 'wp8y_options';
    public string $last_error = '';
    public string $charset = 'utf8mb4';
    public string $collate = 'utf8mb4_unicode_520_ci';
    public int $insert_id = 0;
    public int $rows_affected = 0;

    public function set_blog_id(int $blogId): void
    {
        $this->prefix = $blogId === 1 ? 'wp8y_' : 'wp8y_' . $blogId . '_';
        $this->posts = $this->prefix . 'posts';
        $this->postmeta = $this->prefix . 'postmeta';
        $this->options = $this->prefix . 'options';
    }

    public function get_blog_prefix(int $blogId = 0): string
    {
        $blogId = $blogId === 0 ? get_current_blog_id() : $blogId;
        if ($blogId === 5 && is_string($GLOBALS['twins_staging_il_state']['blogPrefixOverride'])) {
            return $GLOBALS['twins_staging_il_state']['blogPrefixOverride'];
        }
        return $blogId === 1 ? 'wp8y_' : 'wp8y_' . $blogId . '_';
    }

    public function tables(string $scope = 'all', bool $prefix = true, int $blogId = 0): array
    {
        if ($scope !== 'blog' || !$prefix || $blogId !== 5) {
            return [];
        }
        $tables = [
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
        ];
        if (is_array($GLOBALS['twins_staging_il_state']['blogTableMapOverride'])) {
            $tables = array_replace($tables, $GLOBALS['twins_staging_il_state']['blogTableMapOverride']);
        }
        return $tables;
    }

    public function prepare(string $query, ...$arguments): string
    {
        if (count($arguments) === 1 && is_array($arguments[0])) {
            $arguments = $arguments[0];
        }
        foreach ($arguments as $argument) {
            $replacement = is_int($argument) || is_float($argument)
                ? (string) $argument
                : "'" . str_replace("'", "''", (string) $argument) . "'";
            $query = preg_replace('/%[dfs]/', $replacement, $query, 1) ?? $query;
        }
        return str_replace('%%', '%', $query);
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    public function get_var(string $query, int $columnOffset = 0, int $rowOffset = 0)
    {
        unset($columnOffset, $rowOffset);
        $state =& $GLOBALS['twins_staging_il_state'];
        if (is_string($state['dbReadFailureNeedle']) && stripos($query, $state['dbReadFailureNeedle']) !== false) {
            $this->last_error = 'stubbed database read failure';
            return null;
        }
        if (stripos($query, 'GET_LOCK') !== false) {
            $state['controls'][] = ['op' => 'get-lock', 'sql' => $query];
            $state['operations'][] = ['op' => 'get-lock'];
            if (!$state['lockAvailable'] || $state['lockHeld']) {
                return 0;
            }
            $state['lockHeld'] = true;
            return 1;
        }
        if (stripos($query, 'RELEASE_LOCK') !== false) {
            $state['controls'][] = ['op' => 'release-lock', 'sql' => $query];
            $state['operations'][] = ['op' => 'release-lock'];
            $wasHeld = $state['lockHeld'];
            $state['lockHeld'] = false;
            return $wasHeld ? 1 : 0;
        }
        if (stripos($query, '@@auto_increment_increment') !== false) {
            return $state['autoIncrementStep'];
        }
        if (stripos($query, '@@auto_increment_offset') !== false) {
            return $state['autoIncrementOffset'];
        }
        if (stripos($query, '@@default_storage_engine') !== false) {
            return $state['defaultStorageEngine'];
        }
        if (preg_match('/FROM\s+`?wp8y_sitemeta`?/i', $query) && stripos($query, 'blog_count') !== false) {
            return (string) $state['blogCount'];
        }
        if (preg_match('/SHOW\s+TABLES\s+LIKE\s+[\'\"]([^\'\"]+)/i', $query, $matches)) {
            $needle = str_replace(['\\_', '\\%'], ['_', '%'], $matches[1]);
            foreach ($state['tables'] as $table) {
                if ($needle === $table || (str_ends_with($needle, '%') && str_starts_with($table, rtrim($needle, '%')))) {
                    return $table;
                }
            }
            return null;
        }
        if (preg_match('/SELECT\s+COUNT\s*\(\s*\*\s*\)\s+FROM\s+`?(wp8y(?:_5)?_[a-z0-9_]+|wp8y_blogs)`?/i', $query, $matches)) {
            $table = $matches[1];
            if ($table === 'wp8y_blogs') {
                return count(array_filter($state['sites'], static function (array $row): bool {
                    return (int) $row['blog_id'] === 5 || ($row['domain'] === 'danielj140.sg-host.com' && $row['path'] === '/il/');
                }));
            }
            if ($table === 'wp8y_5_posts') {
                return count($state['posts'][5] ?? []);
            }
            if ($table === 'wp8y_usermeta') {
                return count(array_filter($state['usermeta'], static fn(array $row): bool => str_starts_with((string) ($row['meta_key'] ?? ''), 'wp8y_5_')));
            }
            return 0;
        }
        if (preg_match('/SELECT\s+option_value\s+FROM\s+`?wp8y_5_options`?[\s\S]*option_name\s*=\s*[\'\"]([^\'\"]+)/i', $query, $matches)) {
            return $state['options'][5][$matches[1]] ?? null;
        }
        $results = $this->get_results($query, ARRAY_A);
        if ($results === []) {
            return null;
        }
        $row = $results[0];
        return reset($row);
    }

    public function get_row(string $query, $output = OBJECT, int $rowOffset = 0)
    {
        $state =& $GLOBALS['twins_staging_il_state'];
        if (is_string($state['dbReadFailureNeedle']) && stripos($query, $state['dbReadFailureNeedle']) !== false) {
            $this->last_error = 'stubbed database read failure';
            return null;
        }
        if (stripos($query, 'SHOW TABLE STATUS') !== false) {
            $row = ['Name' => 'wp8y_blogs', 'Engine' => 'InnoDB', 'Collation' => 'utf8mb4_unicode_ci', 'Auto_increment' => $GLOBALS['twins_staging_il_state']['autoIncrement']];
            return $output === ARRAY_A ? $row : (object) $row;
        }
        if (stripos($query, 'SHOW CREATE TABLE') !== false && stripos($query, 'wp8y_blogs') !== false) {
            $row = [
                'Table' => 'wp8y_blogs',
                'Create Table' => 'CREATE TABLE `wp8y_blogs` (`blog_id` bigint unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (`blog_id`)) ENGINE=InnoDB AUTO_INCREMENT=' . $state['autoIncrement'] . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            ];
            return $output === ARRAY_A ? $row : (object) $row;
        }
        $results = $this->get_results($query, $output);
        return $results[$rowOffset] ?? null;
    }

    public function get_results(string $query, $output = OBJECT): array
    {
        $state =& $GLOBALS['twins_staging_il_state'];
        if (is_string($state['dbReadFailureNeedle']) && stripos($query, $state['dbReadFailureNeedle']) !== false) {
            $this->last_error = 'stubbed database read failure';
            return [];
        }
        $rows = [];
        if (stripos($query, 'FOR UPDATE NOWAIT') !== false) {
            $state['controls'][] = ['op' => 'row-guard', 'sql' => $query];
            $state['operations'][] = ['op' => 'row-guard'];
            if (!$state['transactionOpen'] || !$state['rowGuardAvailable']) {
                $this->last_error = 'stubbed NOWAIT conflict';
                return [];
            }
            $this->last_error = '';
        }
        if (preg_match('/\bFROM\s+`?wp8y_blogs`?/i', $query)) {
            $rows = array_values($state['sites']);
            if (stripos($query, 'WHERE') !== false && (preg_match('/blog_id\s*=\s*5\b/i', $query) || strpos($query, '/il/') !== false)) {
                $rows = array_values(array_filter($rows, static function (array $row): bool {
                    return (int) $row['blog_id'] === 5 || ($row['domain'] === 'danielj140.sg-host.com' && $row['path'] === '/il/');
                }));
            }
        } elseif (preg_match('/\bFROM\s+`?wp8y_5_posts`?/i', $query)) {
            $rows = array_values($state['posts'][5] ?? []);
        } elseif (preg_match('/\bFROM\s+`?wp8y_5_comments`?/i', $query)) {
            $rows = array_values($state['comments'][5] ?? []);
        } elseif (preg_match('/\bFROM\s+`?wp8y_5_postmeta`?/i', $query)) {
            $rows = array_values($state['postmetaRows'][5] ?? []);
        } elseif (preg_match('/\bFROM\s+`?wp8y_5_commentmeta`?/i', $query)) {
            $rows = array_values($state['commentmetaRows'][5] ?? []);
        } elseif (preg_match('/\bFROM\s+`?wp8y_5_terms`?/i', $query)) {
            $rows = array_values($state['termsRows'][5] ?? []);
        } elseif (preg_match('/\bFROM\s+`?wp8y_5_termmeta`?/i', $query)) {
            $rows = array_values($state['termmetaRows'][5] ?? []);
        } elseif (preg_match('/\bFROM\s+`?wp8y_5_term_taxonomy`?/i', $query)) {
            $rows = array_values($state['termTaxonomyRows'][5] ?? []);
        } elseif (preg_match('/\bFROM\s+`?wp8y_5_term_relationships`?/i', $query)) {
            $rows = array_values($state['termRelationshipRows'][5] ?? []);
        } elseif (preg_match('/\bFROM\s+`?wp8y_5_links`?/i', $query)) {
            $rows = array_values($state['linksRows'][5] ?? []);
        } elseif (preg_match('/\bFROM\s+`?(wp8y(?:_3|_4)?_options)`?/i', $query, $nonTargetOptionMatch)) {
            $tableBlogIds = ['wp8y_options' => 1, 'wp8y_3_options' => 3, 'wp8y_4_options' => 4];
            $table = (string) $nonTargetOptionMatch[1];
            $blogId = $tableBlogIds[$table] ?? 0;
            $isBoundedRewriteEvidence = $blogId > 0
                && stripos($query, 'OCTET_LENGTH(option_value)') !== false
                && preg_match(
                    '/LEFT\s*\(\s*CAST\s*\(\s*option_value\s+AS\s+BINARY\s*\)\s*,\s*(\d+)\s*\)/i',
                    $query,
                    $boundMatch
                ) === 1
                && preg_match('/option_name\s*=\s*[\'"]rewrite_rules[\'"]/i', $query) === 1;
            if ($isBoundedRewriteEvidence) {
                $state['nonTargetRewriteEvidenceReads']++;
                $rawValue = (string) ($state['options'][$blogId]['rewrite_rules'] ?? '');
                $boundedBytes = (int) $boundMatch[1];
                $returnedValue = substr($rawValue, 0, $boundedBytes);
                $state['maxNonTargetRewriteValueBytesReturned'] = max(
                    $state['maxNonTargetRewriteValueBytesReturned'],
                    strlen($returnedValue)
                );
                if (array_key_exists('rewrite_rules', $state['options'][$blogId] ?? [])) {
                    $rows[] = [
                        'option_id' => 1,
                        'option_name' => 'rewrite_rules',
                        'value_bytes' => strlen($rawValue),
                        'bounded_value' => $returnedValue,
                    ];
                }
            }
        } elseif (preg_match('/\bFROM\s+`?wp8y_5_options`?/i', $query)) {
            $state['optionReadQueries'][] = $query;
            $requestedNames = array_keys($state['options'][5] ?? []);
            $boundedBytes = null;
            $isBoundedEvidence = stripos($query, 'OCTET_LENGTH(option_value)') !== false
                && preg_match(
                    '/LEFT\s*\(\s*CAST\s*\(\s*option_value\s+AS\s+BINARY\s*\)\s*,\s*(\d+)\s*\)/i',
                    $query,
                    $boundMatch
                ) === 1;
            if ($isBoundedEvidence) {
                $boundedBytes = (int) $boundMatch[1];
                if (preg_match('/option_name\s*=\s*[\'\"]rewrite_rules[\'\"]/i', $query) === 1) {
                    $requestedNames = ['rewrite_rules'];
                    $state['rewriteEvidenceReads']++;
                    if ($state['rewriteDriftOnEvidenceRead'] === $state['rewriteEvidenceReads']) {
                        $current = (string) ($state['options'][5]['rewrite_rules'] ?? '');
                        if ($current !== '') {
                            $offset = min(100, strlen($current) - 1);
                            $replacement = $current[$offset] === 'x' ? 'y' : 'x';
                            $state['options'][5]['rewrite_rules'] = substr_replace($current, $replacement, $offset, 1);
                        }
                    }
                } elseif (preg_match('/option_name\s+IN\s*\((.*?)\)\s*ORDER\s+BY/is', $query, $inMatch) === 1) {
                    preg_match_all('/\'((?:\'\'|[^\'])*)\'/', $inMatch[1], $nameMatches);
                    $requestedNames = array_map(
                        static fn(string $name): string => str_replace("''", "'", $name),
                        $nameMatches[1] ?? []
                    );
                }
            }
            $optionId = 1;
            foreach ($requestedNames as $name) {
                if (!array_key_exists($name, $state['options'][5] ?? [])) {
                    continue;
                }
                $rawValue = (string) $state['options'][5][$name];
                $returnedValue = $boundedBytes === null
                    ? $rawValue
                    : substr($rawValue, 0, $boundedBytes);
                $state['optionNamesReturned'][] = $name;
                $state['maxOptionValueBytesReturned'] = max(
                    $state['maxOptionValueBytesReturned'],
                    strlen($returnedValue)
                );
                $rows[] = $isBoundedEvidence
                    ? [
                        'option_id' => $optionId++,
                        'option_name' => $name,
                        'value_bytes' => strlen($rawValue),
                        'bounded_value' => $returnedValue,
                    ]
                    : [
                        'option_id' => $optionId++,
                        'option_name' => $name,
                        'option_value' => $returnedValue,
                        'autoload' => 'yes',
                    ];
            }
        } elseif (preg_match('/\bFROM\s+`?wp8y_usermeta`?/i', $query)) {
            $rows = array_values($state['usermeta']);
            if (stripos($query, 'primary_blog') !== false) {
                $rows = array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['user_id'] ?? 0) === 21 && (string) ($row['meta_key'] ?? '') === 'primary_blog'));
            }
        } elseif (preg_match('/\bFROM\s+`?wp8y_blogmeta`?/i', $query)) {
            $rows = array_values($state['blogmeta']);
        } elseif (preg_match('/\bFROM\s+`?wp8y_registration_log`?/i', $query)) {
            $rows = array_values($state['registrationLog']);
        } elseif (preg_match('/\bFROM\s+`?wp8y_sitemeta`?/i', $query)) {
            $rows = [
                ['meta_key' => 'blog_count', 'meta_value' => (string) $state['blogCount']],
                ['meta_key' => 'first_page', 'meta_value' => ''],
                ['meta_key' => 'first_post', 'meta_value' => 'Welcome to %s. This is your first post. Edit or delete it, then start writing!'],
                ['meta_key' => 'WPLANG', 'meta_value' => ''],
            ];
            if ($state['duplicateBlogCount']) {
                $rows[] = ['meta_key' => 'blog_count', 'meta_value' => (string) $state['blogCount']];
            }
            if ($state['defaultPrivacyPolicyContent'] !== null) {
                $rows[] = ['meta_key' => 'default_privacy_policy_content', 'meta_value' => (string) $state['defaultPrivacyPolicyContent']];
            }
            usort($rows, static fn(array $left, array $right): int => strcmp($left['meta_key'], $right['meta_key']));
        } elseif (stripos($query, 'SHOW TABLES') !== false || stripos($query, 'information_schema.tables') !== false) {
            if (stripos($query, 'AUTO_INCREMENT') !== false && stripos($query, 'wp8y_blogs') !== false) {
                $rows[] = ['auto_increment' => $state['cachedAutoIncrement']];
            } elseif (stripos($query, 'wp8y_sitemeta') !== false && stripos($query, 'wp8y_blogs') !== false) {
                foreach ($state['coordinationStorage'] as $table => $facts) {
                    $rows[] = ['table_name' => $table, 'engine' => $facts['engine'], 'table_collation' => $facts['table_collation']];
                }
            } else {
                if (stripos($query, 'SELECT table_name FROM') !== false) {
                    $state['targetTableNameReads']++;
                    if ($state['targetTableDriftOnNameRead'] === $state['targetTableNameReads']) {
                        $state['tables'][] = 'wp8y_5_gf_concurrent_extra';
                    }
                }
                foreach ($state['tables'] as $table) {
                    if (str_starts_with($table, 'wp8y_5_')) {
                        $defaultCollation = str_starts_with($table, 'wp8y_5_gf_')
                            ? 'utf8mb4_unicode_520_ci'
                            : 'utf8mb4_unicode_ci';
                        $storage = $state['tableStorageOverrides'][$table] ?? [
                            'engine' => 'InnoDB',
                            'table_collation' => $defaultCollation,
                        ];
                        $rows[] = [
                            'table_name' => $table,
                            'engine' => $storage['engine'],
                            'table_collation' => $state['initializeStorageDrift'] && $table === 'wp8y_5_posts'
                                ? 'utf8mb4_unicode_520_ci'
                                : $storage['table_collation'],
                        ];
                    }
                }
            }
        }
        return twins_staging_il_harness_rows($rows, $output);
    }

    public function get_col(string $query, int $columnOffset = 0): array
    {
        $results = $this->get_results($query, ARRAY_A);
        $values = [];
        foreach ($results as $row) {
            $rowValues = array_values($row);
            if (array_key_exists($columnOffset, $rowValues)) {
                $values[] = $rowValues[$columnOffset];
            }
        }
        return $values;
    }

    public function query(string $query)
    {
        $state =& $GLOBALS['twins_staging_il_state'];
        if (stripos($query, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') !== false) {
            $state['controls'][] = ['op' => 'set-isolation', 'sql' => $query];
            $state['operations'][] = ['op' => 'set-isolation'];
            return 1;
        }
        if (stripos($query, 'START TRANSACTION') !== false) {
            $state['controls'][] = ['op' => 'start-transaction', 'sql' => $query];
            $state['operations'][] = ['op' => 'start-transaction'];
            if ($state['transactionOpen']) {
                $this->last_error = 'transaction already open';
                return false;
            }
            $state['transactionOpen'] = true;
            return 1;
        }
        if (preg_match('/^\s*COMMIT\s*$/i', $query)) {
            $state['controls'][] = ['op' => 'commit-transaction', 'sql' => $query];
            $state['operations'][] = ['op' => 'commit-transaction'];
            $wasOpen = $state['transactionOpen'];
            $state['transactionOpen'] = false;
            return $wasOpen ? 1 : false;
        }
        if (preg_match('/^\s*DELETE\s+FROM\s+`?(wp8y_5_[a-z0-9_]+)`?/i', $query, $matches)) {
            $table = $matches[1];
            $state['mutations'][] = ['op' => 'db-delete', 'table' => $table, 'sql' => $query];
            $state['operations'][] = ['op' => 'db-delete', 'table' => $table];
            if ($table === 'wp8y_5_posts') {
                $state['posts'][5] = [];
            }
            $this->rows_affected = 1;
            return 1;
        }
        $this->last_error = 'unsupported harness query';
        return false;
    }

    public function insert(string $table, array $data, $format = null)
    {
        unset($format);
        $state =& $GLOBALS['twins_staging_il_state'];
        $attempt = ++$state['dbInsertAttempts'];
        $state['mutations'][] = ['op' => 'db-insert', 'table' => $table, 'data' => $data, 'attempt' => $attempt];
        $state['operations'][] = ['op' => 'db-insert', 'table' => $table];
        if ($state['dbInsertFailureAt'] === $attempt) {
            $this->last_error = 'stubbed direct insert failure';
            $this->rows_affected = 0;
            return false;
        }
        if ($table === 'wp8y_blogs') {
            $blogId = (int) ($data['blog_id'] ?? 0);
            if ($blogId === 0 || isset($state['sites'][$blogId])) {
                $this->last_error = 'duplicate or missing fixed blog id';
                return false;
            }
            $state['sites'][$blogId] = array_merge(
                twins_staging_il_harness_site_row($blogId, (string) ($data['domain'] ?? ''), (string) ($data['path'] ?? ''), (int) ($data['public'] ?? 1)),
                $data
            );
            $state['autoIncrement'] = max($state['autoIncrement'], $blogId + 1);
            $this->insert_id = $blogId;
        } elseif ($table === 'wp8y_5_posts') {
            $postId = isset($data['ID']) ? (int) $data['ID'] : $state['nextPostId']++;
            $postAttempt = ++$state['postInsertAttempts'];
            $row = array_merge([
                'ID' => $postId,
                'post_author' => 21,
                'post_name' => '',
                'post_title' => '',
                'post_status' => 'draft',
                'post_type' => 'post',
                'post_parent' => 0,
            ], $data);
            foreach ($state['postDriftAt'][$postAttempt] ?? [] as $field => $value) {
                $row[$field] = $value;
            }
            $state['posts'][5][$postId] = $row;
            $this->insert_id = $postId;
        } elseif ($table === 'wp8y_5_options') {
            $name = (string) ($data['option_name'] ?? '');
            if ($name === '' || array_key_exists($name, $state['options'][5] ?? [])) {
                $this->last_error = 'duplicate or missing option name';
                return false;
            }
            $state['options'][5][$name] = $data['option_value'] ?? '';
            $this->insert_id = $state['nextOptionId']++;
        } elseif ($table === 'wp8y_usermeta') {
            $state['usermeta'][] = $data;
            $this->insert_id = count($state['usermeta']);
        } elseif ($table === 'wp8y_blogmeta') {
            $state['blogmeta'][] = $data;
            $this->insert_id = count($state['blogmeta']);
        } elseif ($table === 'wp8y_registration_log') {
            $state['registrationLog'][] = $data;
            $this->insert_id = count($state['registrationLog']);
        } else {
            $this->last_error = 'unsupported harness insert table';
            return false;
        }
        $this->last_error = '';
        $this->rows_affected = 1;
        return 1;
    }

    public function update(string $table, array $data, array $where, $format = null, $whereFormat = null)
    {
        unset($format, $whereFormat);
        $state =& $GLOBALS['twins_staging_il_state'];
        $state['mutations'][] = ['op' => 'db-update', 'table' => $table, 'data' => $data, 'where' => $where];
        $state['operations'][] = ['op' => 'db-update', 'table' => $table];
        if ($table === 'wp8y_5_options' && isset($where['option_name'])) {
            $name = (string) $where['option_name'];
            if (!array_key_exists($name, $state['options'][5] ?? [])) {
                $this->last_error = 'missing option';
                return false;
            }
            $state['options'][5][$name] = $data['option_value'] ?? $state['options'][5][$name];
            $this->rows_affected = 1;
            return 1;
        }
        if ($table === 'wp8y_usermeta' && isset($where['meta_key'])) {
            foreach ($state['usermeta'] as &$row) {
                if (($row['user_id'] ?? null) === ($where['user_id'] ?? null) && ($row['meta_key'] ?? null) === $where['meta_key']) {
                    $row = array_merge($row, $data);
                    $this->rows_affected = 1;
                    return 1;
                }
            }
            unset($row);
        }
        if ($table === 'wp8y_5_term_taxonomy') {
            foreach ($state['termTaxonomyRows'][5] as &$row) {
                $matches = true;
                foreach ($where as $field => $value) {
                    if ((string) ($row[$field] ?? '') !== (string) $value) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    $row = array_merge($row, $data);
                    $this->rows_affected = 1;
                    return 1;
                }
            }
            unset($row);
            $this->rows_affected = 0;
            return 0;
        }
        if ($table === 'wp8y_sitemeta'
            && (int) ($where['site_id'] ?? 0) === 1
            && (string) ($where['meta_key'] ?? '') === 'blog_count'
            && (string) ($where['meta_value'] ?? '') === (string) $state['blogCount']) {
            $state['blogCount'] = (int) ($data['meta_value'] ?? 0);
            $this->rows_affected = 1;
            return 1;
        }
        $this->last_error = 'unsupported harness update';
        return false;
    }

    public function delete(string $table, array $where, $whereFormat = null)
    {
        unset($whereFormat);
        $state =& $GLOBALS['twins_staging_il_state'];
        $state['mutations'][] = ['op' => 'db-delete', 'table' => $table, 'where' => $where];
        $state['operations'][] = ['op' => 'db-delete', 'table' => $table];
        $stateKeys = [
            'wp8y_5_posts' => 'posts',
            'wp8y_5_comments' => 'comments',
            'wp8y_5_postmeta' => 'postmetaRows',
            'wp8y_5_term_relationships' => 'termRelationshipRows',
        ];
        if (isset($stateKeys[$table])) {
            $stateKey = $stateKeys[$table];
            $removed = 0;
            foreach ($state[$stateKey][5] ?? [] as $index => $row) {
                $matches = true;
                foreach ($where as $field => $value) {
                    if ((string) ($row[$field] ?? '') !== (string) $value) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    unset($state[$stateKey][5][$index]);
                    $removed++;
                }
            }
            $state[$stateKey][5] = array_values($state[$stateKey][5] ?? []);
            $this->rows_affected = $removed;
            return $removed;
        }
        $this->last_error = 'unsupported harness delete';
        return false;
    }
}

$GLOBALS['wpdb'] = new Twins_Staging_IL_Harness_WPDB();

final class Twins_Staging_IL_Harness_Rewrite
{
    public function init(): void
    {
        $GLOBALS['twins_staging_il_state']['operations'][] = ['op' => 'rewrite-init', 'blogId' => get_current_blog_id()];
    }
}

$GLOBALS['wp_rewrite'] = new Twins_Staging_IL_Harness_Rewrite();
twins_staging_il_harness_reset();

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

function wp_die($message, $title = '', $arguments = []): void
{
    unset($title, $arguments);
    throw new RuntimeException((string) $message);
}

function is_multisite(): bool
{
    return (bool) $GLOBALS['twins_staging_il_state']['multisite'];
}

function home_url($path = ''): string
{
    $state = $GLOBALS['twins_staging_il_state'];
    $base = get_current_blog_id() === 5
        ? 'https://danielj140.sg-host.com/il'
        : rtrim($state['homeUrl'], '/');
    return $base . ($path === '' ? '' : '/' . ltrim((string) $path, '/'));
}

function network_home_url($path = ''): string
{
    $base = rtrim($GLOBALS['twins_staging_il_state']['networkHomeUrl'], '/');
    return $base . ($path === '' ? '' : '/' . ltrim((string) $path, '/'));
}

function site_url($path = ''): string
{
    return home_url($path);
}

function get_current_blog_id(): int
{
    return (int) $GLOBALS['twins_staging_il_state']['currentBlogId'];
}

function get_main_site_id($networkId = null): int
{
    unset($networkId);
    return (int) $GLOBALS['twins_staging_il_state']['mainSiteId'];
}

function get_current_network_id(): int
{
    return (int) $GLOBALS['twins_staging_il_state']['networkId'];
}

function get_network($networkId = null)
{
    $networkId = $networkId === null ? 1 : (int) $networkId;
    if ($networkId !== 1) {
        return null;
    }
    return (object) ['id' => 1, 'domain' => 'danielj140.sg-host.com', 'path' => '/', 'site_id' => 1];
}

function get_site($site = null)
{
    $blogId = is_object($site) && isset($site->blog_id)
        ? (int) $site->blog_id
        : ($site === null ? get_current_blog_id() : (int) $site);
    $row = $GLOBALS['twins_staging_il_state']['sites'][$blogId] ?? null;
    return is_array($row) ? (object) $row : null;
}

function get_sites($arguments = []): array
{
    $sites = [];
    foreach ($GLOBALS['twins_staging_il_state']['sites'] as $row) {
        if (isset($arguments['network_id']) && (int) $row['site_id'] !== (int) $arguments['network_id']) {
            continue;
        }
        if (isset($arguments['domain']) && $row['domain'] !== $arguments['domain']) {
            continue;
        }
        if (isset($arguments['path']) && $row['path'] !== $arguments['path']) {
            continue;
        }
        $sites[] = (object) $row;
    }
    return $sites;
}

function get_user_by($field, $value)
{
    foreach ($GLOBALS['twins_staging_il_state']['users'] as $row) {
        if (in_array((string) $field, ['id', 'ID'], true) && (int) $row['ID'] === (int) $value) {
            return (object) $row;
        }
        if ((string) $field === 'login' && $row['user_login'] === (string) $value) {
            return (object) $row;
        }
    }
    return false;
}

function get_userdata($userId)
{
    return get_user_by('id', $userId);
}

function get_current_user_id(): int
{
    return 21;
}

function is_super_admin($userId = false): bool
{
    $userId = $userId === false ? get_current_user_id() : (int) $userId;
    return in_array($userId, $GLOBALS['twins_staging_il_state']['superAdminIds'], true);
}

function get_super_admins(): array
{
    $logins = [];
    foreach ($GLOBALS['twins_staging_il_state']['superAdminIds'] as $userId) {
        $user = get_userdata($userId);
        if (is_object($user)) {
            $logins[] = (string) $user->user_login;
        }
    }
    return $logins;
}

function is_user_member_of_blog($userId = 0, $blogId = 0): bool
{
    $blogId = (int) ($blogId ?: get_current_blog_id());
    return in_array((int) $userId, $GLOBALS['twins_staging_il_state']['memberships'][$blogId] ?? [], true);
}

function has_action($hookName, $callback = false)
{
    unset($callback);
    $GLOBALS['twins_staging_il_state']['hookChecks'][] = (string) $hookName;
    if (in_array((string) $hookName, $GLOBALS['twins_staging_il_state']['unsafeHooks'], true)) {
        return 10;
    }
    return $GLOBALS['twins_staging_il_state']['coreHooks'][(string) $hookName] ?? false;
}

function add_filter($hookName, $callback, $priority = 10, $acceptedArguments = 1): bool
{
    $state =& $GLOBALS['twins_staging_il_state'];
    $state['filterCalls'][] = ['op' => 'add', 'hook' => (string) $hookName, 'callback' => $callback, 'priority' => (int) $priority, 'acceptedArguments' => (int) $acceptedArguments];
    $state['operations'][] = ['op' => 'add-hard-flush-filter'];
    $state['filters'][(string) $hookName][(int) $priority][] = $callback;
    return true;
}

function remove_filter($hookName, $callback, $priority = 10): bool
{
    $state =& $GLOBALS['twins_staging_il_state'];
    $state['filterCalls'][] = ['op' => 'remove', 'hook' => (string) $hookName, 'callback' => $callback, 'priority' => (int) $priority];
    $state['operations'][] = ['op' => 'remove-hard-flush-filter'];
    foreach ($state['filters'][(string) $hookName][(int) $priority] ?? [] as $index => $registered) {
        if ($registered === $callback) {
            unset($state['filters'][(string) $hookName][(int) $priority][$index]);
            return true;
        }
    }
    return false;
}

function wp_is_site_initialized($siteId): bool
{
    return in_array((int) $siteId, $GLOBALS['twins_staging_il_state']['initializedSites'], true);
}

function clean_blog_cache($blog): void
{
    $blogId = is_object($blog) && isset($blog->blog_id) ? (int) $blog->blog_id : (int) $blog;
    $GLOBALS['twins_staging_il_state']['operations'][] = ['op' => 'clean-blog-cache', 'blogId' => $blogId];
}

function wp_cache_delete($key, $group = ''): bool
{
    $GLOBALS['twins_staging_il_state']['operations'][] = ['op' => 'cache-delete', 'key' => (string) $key, 'group' => (string) $group, 'blogId' => get_current_blog_id()];
    return true;
}

function wp_initialize_site($siteId, array $arguments = [])
{
    $siteId = is_object($siteId) && isset($siteId->blog_id) ? (int) $siteId->blog_id : (int) $siteId;
    $state =& $GLOBALS['twins_staging_il_state'];
    $state['mutations'][] = ['op' => 'initialize-site', 'blogId' => $siteId, 'arguments' => $arguments, 'collation' => $GLOBALS['wpdb']->collate];
    $state['operations'][] = ['op' => 'initialize-site', 'blogId' => $siteId];
    if ($state['initializeFailure']) {
        throw new RuntimeException('stubbed site initialization failure');
    }
    if ($state['initializeReturnError']) {
        return new WP_Error('stubbed_initializer_error', 'stubbed initializer error result');
    }
    if ($siteId !== 5 || !isset($state['sites'][5]) || (int) $state['sites'][5]['public'] !== 0) {
        throw new RuntimeException('initialization began before fixed nonpublic site row existed');
    }
    foreach (['wp_initialize_site', 'wpmu_new_blog'] as $hookName) {
        if (!in_array($hookName, $state['hookChecks'], true) || in_array($hookName, $state['unsafeHooks'], true)) {
            throw new RuntimeException('initialization hook quarantine was not proven: ' . $hookName);
        }
    }
    foreach (['posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'links', 'options'] as $suffix) {
        $table = 'wp8y_5_' . $suffix;
        if (!in_array($table, $state['tables'], true)) {
            $state['tables'][] = $table;
        }
    }
    $state['options'][5] = [
        'home' => 'http://danielj140.sg-host.com/il',
        'siteurl' => 'http://danielj140.sg-host.com/il',
        'blogname' => 'Temporary',
        'blog_public' => 1,
        'show_on_front' => 'posts',
        'page_on_front' => 0,
        'cptui_post_types' => '',
        'rewrite_rules' => 'temporary-il-rules',
    ];
    $defaultDate = '2026-07-14 12:00:00';
    $state['posts'][5] = [
        1 => [
            'ID' => 1,
            'post_author' => 21,
            'post_name' => 'hello-world',
            'post_title' => 'Hello world!',
            'post_content' => 'Welcome to the staging network. This is your first post.',
            'post_excerpt' => '',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_parent' => 0,
            'comment_status' => 'open',
            'ping_status' => 'open',
            'post_password' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_content_filtered' => '',
            'menu_order' => 0,
            'post_mime_type' => '',
            'comment_count' => 1,
            'post_date' => $defaultDate,
            'post_date_gmt' => $defaultDate,
            'post_modified' => $defaultDate,
            'post_modified_gmt' => $defaultDate,
            'guid' => 'https://danielj140.sg-host.com/il/?p=1',
        ],
        2 => [
            'ID' => 2,
            'post_author' => 21,
            'post_name' => 'sample-page',
            'post_title' => 'Sample Page',
            'post_content' => 'This is an example page.',
            'post_excerpt' => '',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_parent' => 0,
            'comment_status' => 'closed',
            'ping_status' => 'open',
            'post_password' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_content_filtered' => '',
            'menu_order' => 0,
            'post_mime_type' => '',
            'comment_count' => 0,
            'post_date' => $defaultDate,
            'post_date_gmt' => $defaultDate,
            'post_modified' => $defaultDate,
            'post_modified_gmt' => $defaultDate,
            'guid' => 'https://danielj140.sg-host.com/il/?page_id=2',
        ],
    ];
    if ($state['initializeBaselineDrift']) {
        $state['posts'][5][99] = array_merge($state['posts'][5][2], ['ID' => 99, 'post_name' => 'unexpected']);
    }
    $state['comments'][5] = [[
        'comment_ID' => 1,
        'comment_post_ID' => 1,
        'comment_type' => 'comment',
    ]];
    $state['postmetaRows'][5] = [[
        'meta_id' => 1,
        'post_id' => 2,
        'meta_key' => '_wp_page_template',
        'meta_value' => 'default',
    ]];
    $state['commentmetaRows'][5] = [];
    $state['termsRows'][5] = [[
        'term_id' => 1,
        'name' => 'Uncategorized',
        'slug' => 'uncategorized',
        'term_group' => 0,
    ]];
    $state['termmetaRows'][5] = [];
    $state['termTaxonomyRows'][5] = [[
        'term_taxonomy_id' => 1,
        'term_id' => 1,
        'taxonomy' => 'category',
        'description' => '',
        'parent' => 0,
        'count' => 1,
    ]];
    $state['termRelationshipRows'][5] = [[
        'object_id' => 1,
        'term_taxonomy_id' => 1,
        'term_order' => 0,
    ]];
    $state['linksRows'][5] = [];
    $state['nextPostId'] = 3;
    $state['memberships'][5] = [21];
    $state['usermeta'][] = ['user_id' => 21, 'meta_key' => 'wp8y_5_capabilities', 'meta_value' => 'a:1:{s:13:"administrator";b:1;}'];
    $state['usermeta'][] = ['user_id' => 21, 'meta_key' => 'wp8y_5_user_level', 'meta_value' => '10'];
    $state['initializedSites'][] = 5;
    $priorBlogId = $state['currentBlogId'];
    $state['currentBlogId'] = 5;
    $GLOBALS['wpdb']->set_blog_id(5);
    flush_rewrite_rules(true);
    $state['currentBlogId'] = $priorBlogId;
    $GLOBALS['wpdb']->set_blog_id($priorBlogId);
    return true;
}

function switch_to_blog($blogId, $deprecated = null): bool
{
    unset($deprecated);
    $state =& $GLOBALS['twins_staging_il_state'];
    $state['blogStack'][] = $state['currentBlogId'];
    $state['currentBlogId'] = (int) $blogId;
    $state['operations'][] = ['op' => 'switch-blog', 'blogId' => (int) $blogId];
    $GLOBALS['wpdb']->set_blog_id((int) $blogId);
    return true;
}

function restore_current_blog(): bool
{
    $state =& $GLOBALS['twins_staging_il_state'];
    if ($state['blogStack'] === []) {
        return false;
    }
    $state['currentBlogId'] = (int) array_pop($state['blogStack']);
    $state['operations'][] = ['op' => 'restore-blog', 'blogId' => $state['currentBlogId']];
    $GLOBALS['wpdb']->set_blog_id($state['currentBlogId']);
    return true;
}

function register_post_type($postType, $arguments = []): object
{
    $GLOBALS['twins_staging_il_state']['registeredPostTypes'][get_current_blog_id()][(string) $postType] = (array) $arguments;
    $GLOBALS['twins_staging_il_state']['operations'][] = ['op' => 'register-post-type', 'blogId' => get_current_blog_id(), 'postType' => (string) $postType];
    return (object) ['name' => (string) $postType];
}

function unregister_post_type($postType)
{
    unset($GLOBALS['twins_staging_il_state']['registeredPostTypes'][get_current_blog_id()][(string) $postType]);
    $GLOBALS['twins_staging_il_state']['operations'][] = ['op' => 'unregister-post-type', 'blogId' => get_current_blog_id(), 'postType' => (string) $postType];
    return (object) ['name' => (string) $postType];
}

function flush_rewrite_rules($hard = true): void
{
    $state =& $GLOBALS['twins_staging_il_state'];
    $requestedHard = (bool) $hard;
    foreach ($state['filters']['flush_rewrite_rules_hard'] ?? [] as $callbacks) {
        foreach ($callbacks as $callback) {
            $hard = $callback((bool) $hard);
        }
    }
    $state['mutations'][] = ['op' => 'flush-rewrite-rules', 'blogId' => get_current_blog_id(), 'hard' => (bool) $hard, 'requestedHard' => $requestedHard];
    $state['operations'][] = ['op' => 'flush-rewrite-rules', 'blogId' => get_current_blog_id()];
    if (get_current_blog_id() === 5) {
        if ($state['nonTargetRewriteDriftOnFlush'] && !$state['nonTargetRewriteDriftApplied']) {
            $state['options'][3]['rewrite_rules'] = 'ky-rules-concurrent-raw-change';
            $state['nonTargetRewriteDriftApplied'] = true;
        }
        $hasLocation = isset($state['registeredPostTypes'][5]['location']);
        $state['options'][5]['rewrite_rules'] = $hasLocation
            ? serialize([
                'location/?$' => 'index.php?post_type=location',
                'location/(.+?)(?:/([0-9]+))?/?$' => 'index.php?location=$matches[1]&page=$matches[2]',
            ])
            : serialize(['broken/?$' => 'index.php?broken=1']);
    }
}

function get_option($option, $default = false)
{
    return $GLOBALS['twins_staging_il_state']['cachedOptions'][get_current_blog_id()][(string) $option] ?? $default;
}

function get_post($postId, $output = OBJECT, $filter = 'raw')
{
    unset($filter);
    $row = $GLOBALS['twins_staging_il_state']['posts'][get_current_blog_id()][(int) $postId] ?? null;
    if (!is_array($row)) {
        return null;
    }
    return $output === ARRAY_A ? $row : (object) $row;
}

function get_page_by_path($path, $output = OBJECT, $postType = 'page')
{
    $segments = explode('/', trim((string) $path, '/'));
    $slug = (string) end($segments);
    foreach ($GLOBALS['twins_staging_il_state']['posts'][get_current_blog_id()] ?? [] as $row) {
        if ($row['post_name'] === $slug && in_array($row['post_type'], (array) $postType, true)) {
            return $output === ARRAY_A ? $row : (object) $row;
        }
    }
    return null;
}

function get_posts($arguments = []): array
{
    $postTypes = isset($arguments['post_type']) ? (array) $arguments['post_type'] : ['post'];
    $rows = [];
    foreach ($GLOBALS['twins_staging_il_state']['posts'][get_current_blog_id()] ?? [] as $row) {
        if (in_array($row['post_type'], $postTypes, true)) {
            $rows[] = (object) $row;
        }
    }
    return $rows;
}

function sanitize_title($title): string
{
    $value = preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $title)) ?? '';
    return trim($value, '-');
}

function absint($value): int
{
    return abs((int) $value);
}

function trailingslashit($value): string
{
    return rtrim((string) $value, '/\\') . '/';
}

function untrailingslashit($value): string
{
    return rtrim((string) $value, '/\\');
}

function wp_json_encode($value, $flags = 0, $depth = 512)
{
    return json_encode($value, (int) $flags, (int) $depth);
}

function maybe_serialize($value): string
{
    if (is_array($value) || is_object($value)) {
        return serialize($value);
    }
    if (is_string($value)) {
        $decoded = @unserialize($value);
        if ($decoded !== false || $value === 'b:0;') {
            return serialize($value);
        }
    }
    return (string) $value;
}

function maybe_unserialize($value)
{
    if (!is_string($value)) {
        return $value;
    }
    $unserialized = @unserialize($value);
    return $unserialized === false && $value !== 'b:0;' ? $value : $unserialized;
}

function wp_parse_url($url, $component = -1)
{
    return parse_url((string) $url, (int) $component);
}

function get_locale(): string
{
    return (string) $GLOBALS['twins_staging_il_state']['locale'];
}

function twins_staging_il_backup_digest(): string
{
    $GLOBALS['twins_staging_il_state']['backupChecks']++;
    $GLOBALS['twins_staging_il_state']['operations'][] = ['op' => 'backup-gate'];
    return '836dd8850730d4772956e041877cebd23d791700800a0a94588ebf1a9e12f374';
}

function twins_staging_il_filesystem_residue(): array
{
    return $GLOBALS['twins_staging_il_state']['filesystemResidue'];
}

function twins_staging_il_frontend_runtime_evidence(): array
{
    return $GLOBALS['twins_staging_il_state']['frontendRuntimeEvidence'];
}

function twins_staging_il_htaccess_digest(): string
{
    $GLOBALS['twins_staging_il_state']['htaccessChecks']++;
    return $GLOBALS['twins_staging_il_state']['htaccessDigest'];
}

function twins_staging_il_runtime_evidence(): array
{
    return [
        'valid' => (bool) $GLOBALS['twins_staging_il_state']['runtimeEvidenceValid'],
        'cli' => true,
        'wpVersion' => (string) $GLOBALS['wp_version'],
        'phpMajor' => 8,
        'phpMinor' => 2,
        'databaseVersion' => '8.4.6-6',
        'safetyDigest' => '0aedbd14df0ce5276b8400e6b4180af7eca0072e5403ac5d4280d6a01f9c6cd2',
        'muPhpFiles' => (array) $GLOBALS['twins_staging_il_state']['muPhpFiles'],
        'overhaulDigest' => (string) $GLOBALS['twins_staging_il_state']['overhaulDigest'],
        'normalPluginOrThemeLoaded' => false,
        'astraAvailable' => true,
        'astraAllowed' => (bool) $GLOBALS['twins_staging_il_state']['astraAllowed'],
        'installerOverrideAbsent' => (bool) $GLOBALS['twins_staging_il_state']['installerOverrideAbsent'],
        'locale' => (string) $GLOBALS['twins_staging_il_state']['locale'],
        'dropins' => [],
    ];
}

function twins_staging_il_source_cpt_digests(): array
{
    return [
        1 => 'a03dea17f88de0d6b4b7f8f377f370d3479b422fc855c2f4b0efcccea350b94f',
        3 => 'a03dea17f88de0d6b4b7f8f377f370d3479b422fc855c2f4b0efcccea350b94f',
        4 => 'a03dea17f88de0d6b4b7f8f377f370d3479b422fc855c2f4b0efcccea350b94f',
    ];
}

foreach (['wpmu_create_blog', 'wp_insert_site', 'create_empty_blog', 'wp_insert_post', 'update_blog_status', 'update_option', 'update_blog_option', 'add_user_to_blog'] as $forbiddenFunction) {
    if (!function_exists($forbiddenFunction)) {
        eval('function ' . $forbiddenFunction . '(...$arguments) { unset($arguments); throw new RuntimeException("forbidden hook-driven API called: ' . $forbiddenFunction . '"); }');
    }
}

function twins_staging_il_harness_seed_exact(array $manifest): void
{
    twins_staging_il_harness_reset();
    $state =& $GLOBALS['twins_staging_il_state'];
    $state['sites'][5] = twins_staging_il_harness_site_row(5, 'danielj140.sg-host.com', '/il/', 0);
    $state['autoIncrement'] = 6;
    $state['blogCount'] = 4;
    $state['initializedSites'][] = 5;
    $state['memberships'][5] = [21];
    foreach (['posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'links', 'options'] as $suffix) {
        $state['tables'][] = 'wp8y_5_' . $suffix;
    }
    $homeId = 1;
    $state['nextPostId'] = 1;
    $state['posts'][5] = [];
    foreach ($GLOBALS['twins_staging_il_expected_core'] as $index => $page) {
        $postId = $state['nextPostId']++;
        $state['posts'][5][$postId] = [
            'ID' => $postId,
            'post_author' => 21,
            'post_name' => $page['slug'] === '' ? 'home' : $page['slug'],
            'post_title' => $page['title'],
            'post_content' => '',
            'post_excerpt' => '',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_parent' => 0,
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_content_filtered' => '',
            'guid' => 'https://danielj140.sg-host.com/il/?p=' . $postId,
            'menu_order' => 0,
            'post_mime_type' => '',
            'comment_count' => 0,
            'post_date' => '2026-07-14 12:00:00',
            'post_date_gmt' => '2026-07-14 12:00:00',
            'post_modified' => '2026-07-14 12:00:00',
            'post_modified_gmt' => '2026-07-14 12:00:00',
        ];
        if ($index === 0) {
            $homeId = $postId;
        }
    }
    foreach ($GLOBALS['twins_staging_il_expected_cities'] as $slug) {
        $postId = $state['nextPostId']++;
        $state['posts'][5][$postId] = [
            'ID' => $postId,
            'post_author' => 21,
            'post_name' => $slug,
            'post_title' => $GLOBALS['twins_staging_il_city_titles'][$slug],
            'post_content' => '',
            'post_excerpt' => '',
            'post_status' => 'publish',
            'post_type' => 'location',
            'post_parent' => 0,
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_content_filtered' => '',
            'guid' => 'https://danielj140.sg-host.com/il/?p=' . $postId,
            'menu_order' => 0,
            'post_mime_type' => '',
            'comment_count' => 0,
            'post_date' => '2026-07-14 12:00:00',
            'post_date_gmt' => '2026-07-14 12:00:00',
            'post_modified' => '2026-07-14 12:00:00',
            'post_modified_gmt' => '2026-07-14 12:00:00',
        ];
    }
    $state['comments'][5] = [];
    $state['postmetaRows'][5] = [];
    $state['commentmetaRows'][5] = [];
    $state['termsRows'][5] = [['term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized', 'term_group' => 0]];
    $state['termmetaRows'][5] = [];
    $state['termTaxonomyRows'][5] = [['term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 0]];
    $state['termRelationshipRows'][5] = [];
    $state['linksRows'][5] = [];
    $state['options'][5] = [
        'home' => 'https://danielj140.sg-host.com/il',
        'siteurl' => 'https://danielj140.sg-host.com/il',
        'blogname' => 'Garage Door Service in Rockford, Illinois',
        'blog_public' => 0,
        'show_on_front' => 'page',
        'page_on_front' => $homeId,
        'permalink_structure' => '/%postname%/',
        'template' => 'astra',
        'stylesheet' => 'astra',
        'active_plugins' => 'a:0:{}',
        'ping_sites' => '',
        'post_count' => '0',
        'twins_staging_il_phone' => '(815) 800-2025',
        'twins_staging_il_tel' => '+18158002025',
        'cptui_post_types' => $manifest['cptOptionRaw'],
        'rewrite_rules' => serialize([
            'location/?$' => 'index.php?post_type=location',
            'location/(.+?)(?:/([0-9]+))?/?$' => 'index.php?location=$matches[1]&page=$matches[2]',
        ]),
    ];
    $state['usermeta'] = [
        ['umeta_id' => 1020, 'user_id' => 21, 'meta_key' => 'primary_blog', 'meta_value' => '1'],
        ['user_id' => 21, 'meta_key' => 'wp8y_5_capabilities', 'meta_value' => 'a:1:{s:13:"administrator";b:1;}'],
        ['user_id' => 21, 'meta_key' => 'wp8y_5_user_level', 'meta_value' => '10'],
    ];
}

function twins_staging_il_harness_frontend_rewrite_rules(): string
{
    $encoded =
        'YToxMDk6e3M6MTE6Il53cC1qc29uLz8kIjtzOjIyOiJpbmRleC5waHA/cmVzdF9yb3V0ZT0vIjtzOjE0OiJed3AtanNvbi8oLiop' .
        'PyI7czozMzoiaW5kZXgucGhwP3Jlc3Rfcm91dGU9LyRtYXRjaGVzWzFdIjtzOjIxOiJeaW5kZXgucGhwL3dwLWpzb24vPyQiO3M6' .
        'MjI6ImluZGV4LnBocD9yZXN0X3JvdXRlPS8iO3M6MjQ6Il5pbmRleC5waHAvd3AtanNvbi8oLiopPyI7czozMzoiaW5kZXgucGhw' .
        'P3Jlc3Rfcm91dGU9LyRtYXRjaGVzWzFdIjtzOjE3OiJed3Atc2l0ZW1hcFwueG1sJCI7czoyMzoiaW5kZXgucGhwP3NpdGVtYXA9' .
        'aW5kZXgiO3M6MTc6Il53cC1zaXRlbWFwXC54c2wkIjtzOjM2OiJpbmRleC5waHA/c2l0ZW1hcC1zdHlsZXNoZWV0PXNpdGVtYXAi' .
        'O3M6MjM6Il53cC1zaXRlbWFwLWluZGV4XC54c2wkIjtzOjM0OiJpbmRleC5waHA/c2l0ZW1hcC1zdHlsZXNoZWV0PWluZGV4Ijtz' .
        'OjQ4OiJed3Atc2l0ZW1hcC0oW2Etel0rPyktKFthLXpcZF8tXSs/KS0oXGQrPylcLnhtbCQiO3M6NzU6ImluZGV4LnBocD9zaXRl' .
        'bWFwPSRtYXRjaGVzWzFdJnNpdGVtYXAtc3VidHlwZT0kbWF0Y2hlc1syXSZwYWdlZD0kbWF0Y2hlc1szXSI7czozNDoiXndwLXNp' .
        'dGVtYXAtKFthLXpdKz8pLShcZCs/KVwueG1sJCI7czo0NzoiaW5kZXgucGhwP3NpdGVtYXA9JG1hdGNoZXNbMV0mcGFnZWQ9JG1h' .
        'dGNoZXNbMl0iO3M6NDc6ImNhdGVnb3J5LyguKz8pL2ZlZWQvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjUyOiJpbmRl' .
        'eC5waHA/Y2F0ZWdvcnlfbmFtZT0kbWF0Y2hlc1sxXSZmZWVkPSRtYXRjaGVzWzJdIjtzOjQyOiJjYXRlZ29yeS8oLis/KS8oZmVl' .
        'ZHxyZGZ8cnNzfHJzczJ8YXRvbSkvPyQiO3M6NTI6ImluZGV4LnBocD9jYXRlZ29yeV9uYW1lPSRtYXRjaGVzWzFdJmZlZWQ9JG1h' .
        'dGNoZXNbMl0iO3M6MjM6ImNhdGVnb3J5LyguKz8pL2VtYmVkLz8kIjtzOjQ2OiJpbmRleC5waHA/Y2F0ZWdvcnlfbmFtZT0kbWF0' .
        'Y2hlc1sxXSZlbWJlZD10cnVlIjtzOjM1OiJjYXRlZ29yeS8oLis/KS9wYWdlLz8oWzAtOV17MSx9KS8/JCI7czo1MzoiaW5kZXgu' .
        'cGhwP2NhdGVnb3J5X25hbWU9JG1hdGNoZXNbMV0mcGFnZWQ9JG1hdGNoZXNbMl0iO3M6MTc6ImNhdGVnb3J5LyguKz8pLz8kIjtz' .
        'OjM1OiJpbmRleC5waHA/Y2F0ZWdvcnlfbmFtZT0kbWF0Y2hlc1sxXSI7czo0NDoidGFnLyhbXi9dKykvZmVlZC8oZmVlZHxyZGZ8' .
        'cnNzfHJzczJ8YXRvbSkvPyQiO3M6NDI6ImluZGV4LnBocD90YWc9JG1hdGNoZXNbMV0mZmVlZD0kbWF0Y2hlc1syXSI7czozOToi' .
        'dGFnLyhbXi9dKykvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjQyOiJpbmRleC5waHA/dGFnPSRtYXRjaGVzWzFdJmZl' .
        'ZWQ9JG1hdGNoZXNbMl0iO3M6MjA6InRhZy8oW14vXSspL2VtYmVkLz8kIjtzOjM2OiJpbmRleC5waHA/dGFnPSRtYXRjaGVzWzFd' .
        'JmVtYmVkPXRydWUiO3M6MzI6InRhZy8oW14vXSspL3BhZ2UvPyhbMC05XXsxLH0pLz8kIjtzOjQzOiJpbmRleC5waHA/dGFnPSRt' .
        'YXRjaGVzWzFdJnBhZ2VkPSRtYXRjaGVzWzJdIjtzOjE0OiJ0YWcvKFteL10rKS8/JCI7czoyNToiaW5kZXgucGhwP3RhZz0kbWF0' .
        'Y2hlc1sxXSI7czo0NToidHlwZS8oW14vXSspL2ZlZWQvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjUwOiJpbmRleC5w' .
        'aHA/cG9zdF9mb3JtYXQ9JG1hdGNoZXNbMV0mZmVlZD0kbWF0Y2hlc1syXSI7czo0MDoidHlwZS8oW14vXSspLyhmZWVkfHJkZnxy' .
        'c3N8cnNzMnxhdG9tKS8/JCI7czo1MDoiaW5kZXgucGhwP3Bvc3RfZm9ybWF0PSRtYXRjaGVzWzFdJmZlZWQ9JG1hdGNoZXNbMl0i' .
        'O3M6MjE6InR5cGUvKFteL10rKS9lbWJlZC8/JCI7czo0NDoiaW5kZXgucGhwP3Bvc3RfZm9ybWF0PSRtYXRjaGVzWzFdJmVtYmVk' .
        'PXRydWUiO3M6MzM6InR5cGUvKFteL10rKS9wYWdlLz8oWzAtOV17MSx9KS8/JCI7czo1MToiaW5kZXgucGhwP3Bvc3RfZm9ybWF0' .
        'PSRtYXRjaGVzWzFdJnBhZ2VkPSRtYXRjaGVzWzJdIjtzOjE1OiJ0eXBlLyhbXi9dKykvPyQiO3M6MzM6ImluZGV4LnBocD9wb3N0' .
        'X2Zvcm1hdD0kbWF0Y2hlc1sxXSI7czoxMToibG9jYXRpb24vPyQiO3M6Mjg6ImluZGV4LnBocD9wb3N0X3R5cGU9bG9jYXRpb24i' .
        'O3M6NDE6ImxvY2F0aW9uL2ZlZWQvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjQ1OiJpbmRleC5waHA/cG9zdF90eXBl' .
        'PWxvY2F0aW9uJmZlZWQ9JG1hdGNoZXNbMV0iO3M6MzY6ImxvY2F0aW9uLyhmZWVkfHJkZnxyc3N8cnNzMnxhdG9tKS8/JCI7czo0' .
        'NToiaW5kZXgucGhwP3Bvc3RfdHlwZT1sb2NhdGlvbiZmZWVkPSRtYXRjaGVzWzFdIjtzOjI4OiJsb2NhdGlvbi9wYWdlLyhbMC05' .
        'XXsxLH0pLz8kIjtzOjQ2OiJpbmRleC5waHA/cG9zdF90eXBlPWxvY2F0aW9uJnBhZ2VkPSRtYXRjaGVzWzFdIjtzOjM0OiJsb2Nh' .
        'dGlvbi8uKz8vYXR0YWNobWVudC8oW14vXSspLz8kIjtzOjMyOiJpbmRleC5waHA/YXR0YWNobWVudD0kbWF0Y2hlc1sxXSI7czo0' .
        'NDoibG9jYXRpb24vLis/L2F0dGFjaG1lbnQvKFteL10rKS90cmFja2JhY2svPyQiO3M6Mzc6ImluZGV4LnBocD9hdHRhY2htZW50' .
        'PSRtYXRjaGVzWzFdJnRiPTEiO3M6NjQ6ImxvY2F0aW9uLy4rPy9hdHRhY2htZW50LyhbXi9dKykvZmVlZC8oZmVlZHxyZGZ8cnNz' .
        'fHJzczJ8YXRvbSkvPyQiO3M6NDk6ImluZGV4LnBocD9hdHRhY2htZW50PSRtYXRjaGVzWzFdJmZlZWQ9JG1hdGNoZXNbMl0iO3M6' .
        'NTk6ImxvY2F0aW9uLy4rPy9hdHRhY2htZW50LyhbXi9dKykvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjQ5OiJpbmRl' .
        'eC5waHA/YXR0YWNobWVudD0kbWF0Y2hlc1sxXSZmZWVkPSRtYXRjaGVzWzJdIjtzOjU5OiJsb2NhdGlvbi8uKz8vYXR0YWNobWVu' .
        'dC8oW14vXSspL2NvbW1lbnQtcGFnZS0oWzAtOV17MSx9KS8/JCI7czo1MDoiaW5kZXgucGhwP2F0dGFjaG1lbnQ9JG1hdGNoZXNb' .
        'MV0mY3BhZ2U9JG1hdGNoZXNbMl0iO3M6NDA6ImxvY2F0aW9uLy4rPy9hdHRhY2htZW50LyhbXi9dKykvZW1iZWQvPyQiO3M6NDM6' .
        'ImluZGV4LnBocD9hdHRhY2htZW50PSRtYXRjaGVzWzFdJmVtYmVkPXRydWUiO3M6MjM6ImxvY2F0aW9uLyguKz8pL2VtYmVkLz8k' .
        'IjtzOjQxOiJpbmRleC5waHA/bG9jYXRpb249JG1hdGNoZXNbMV0mZW1iZWQ9dHJ1ZSI7czoyNzoibG9jYXRpb24vKC4rPykvdHJh' .
        'Y2tiYWNrLz8kIjtzOjM1OiJpbmRleC5waHA/bG9jYXRpb249JG1hdGNoZXNbMV0mdGI9MSI7czo0NzoibG9jYXRpb24vKC4rPykv' .
        'ZmVlZC8oZmVlZHxyZGZ8cnNzfHJzczJ8YXRvbSkvPyQiO3M6NDc6ImluZGV4LnBocD9sb2NhdGlvbj0kbWF0Y2hlc1sxXSZmZWVk' .
        'PSRtYXRjaGVzWzJdIjtzOjQyOiJsb2NhdGlvbi8oLis/KS8oZmVlZHxyZGZ8cnNzfHJzczJ8YXRvbSkvPyQiO3M6NDc6ImluZGV4' .
        'LnBocD9sb2NhdGlvbj0kbWF0Y2hlc1sxXSZmZWVkPSRtYXRjaGVzWzJdIjtzOjM1OiJsb2NhdGlvbi8oLis/KS9wYWdlLz8oWzAt' .
        'OV17MSx9KS8/JCI7czo0ODoiaW5kZXgucGhwP2xvY2F0aW9uPSRtYXRjaGVzWzFdJnBhZ2VkPSRtYXRjaGVzWzJdIjtzOjQyOiJs' .
        'b2NhdGlvbi8oLis/KS9jb21tZW50LXBhZ2UtKFswLTldezEsfSkvPyQiO3M6NDg6ImluZGV4LnBocD9sb2NhdGlvbj0kbWF0Y2hl' .
        'c1sxXSZjcGFnZT0kbWF0Y2hlc1syXSI7czozMToibG9jYXRpb24vKC4rPykoPzovKFswLTldKykpPy8/JCI7czo0NzoiaW5kZXgu' .
        'cGhwP2xvY2F0aW9uPSRtYXRjaGVzWzFdJnBhZ2U9JG1hdGNoZXNbMl0iO3M6NDg6Ii4qd3AtKGF0b218cmRmfHJzc3xyc3MyfGZl' .
        'ZWR8Y29tbWVudHNyc3MyKVwucGhwJCI7czoxODoiaW5kZXgucGhwP2ZlZWQ9b2xkIjtzOjIwOiIuKndwLWFwcFwucGhwKC8uKik/' .
        'JCI7czoxOToiaW5kZXgucGhwP2Vycm9yPTQwMyI7czoxODoiLip3cC1yZWdpc3Rlci5waHAkIjtzOjIzOiJpbmRleC5waHA/cmVn' .
        'aXN0ZXI9dHJ1ZSI7czozMjoiZmVlZC8oZmVlZHxyZGZ8cnNzfHJzczJ8YXRvbSkvPyQiO3M6Mjc6ImluZGV4LnBocD8mZmVlZD0k' .
        'bWF0Y2hlc1sxXSI7czoyNzoiKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjI3OiJpbmRleC5waHA/JmZlZWQ9JG1hdGNo' .
        'ZXNbMV0iO3M6ODoiZW1iZWQvPyQiO3M6MjE6ImluZGV4LnBocD8mZW1iZWQ9dHJ1ZSI7czoyMDoicGFnZS8/KFswLTldezEsfSkv' .
        'PyQiO3M6Mjg6ImluZGV4LnBocD8mcGFnZWQ9JG1hdGNoZXNbMV0iO3M6Mjc6ImNvbW1lbnQtcGFnZS0oWzAtOV17MSx9KS8/JCI7' .
        'czozODoiaW5kZXgucGhwPyZwYWdlX2lkPTEmY3BhZ2U9JG1hdGNoZXNbMV0iO3M6NDE6ImNvbW1lbnRzL2ZlZWQvKGZlZWR8cmRm' .
        'fHJzc3xyc3MyfGF0b20pLz8kIjtzOjQyOiJpbmRleC5waHA/JmZlZWQ9JG1hdGNoZXNbMV0md2l0aGNvbW1lbnRzPTEiO3M6MzY6' .
        'ImNvbW1lbnRzLyhmZWVkfHJkZnxyc3N8cnNzMnxhdG9tKS8/JCI7czo0MjoiaW5kZXgucGhwPyZmZWVkPSRtYXRjaGVzWzFdJndp' .
        'dGhjb21tZW50cz0xIjtzOjE3OiJjb21tZW50cy9lbWJlZC8/JCI7czoyMToiaW5kZXgucGhwPyZlbWJlZD10cnVlIjtzOjQ0OiJz' .
        'ZWFyY2gvKC4rKS9mZWVkLyhmZWVkfHJkZnxyc3N8cnNzMnxhdG9tKS8/JCI7czo0MDoiaW5kZXgucGhwP3M9JG1hdGNoZXNbMV0m' .
        'ZmVlZD0kbWF0Y2hlc1syXSI7czozOToic2VhcmNoLyguKykvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjQwOiJpbmRl' .
        'eC5waHA/cz0kbWF0Y2hlc1sxXSZmZWVkPSRtYXRjaGVzWzJdIjtzOjIwOiJzZWFyY2gvKC4rKS9lbWJlZC8/JCI7czozNDoiaW5k' .
        'ZXgucGhwP3M9JG1hdGNoZXNbMV0mZW1iZWQ9dHJ1ZSI7czozMjoic2VhcmNoLyguKykvcGFnZS8/KFswLTldezEsfSkvPyQiO3M6' .
        'NDE6ImluZGV4LnBocD9zPSRtYXRjaGVzWzFdJnBhZ2VkPSRtYXRjaGVzWzJdIjtzOjE0OiJzZWFyY2gvKC4rKS8/JCI7czoyMzoi' .
        'aW5kZXgucGhwP3M9JG1hdGNoZXNbMV0iO3M6NDc6ImF1dGhvci8oW14vXSspL2ZlZWQvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20p' .
        'Lz8kIjtzOjUwOiJpbmRleC5waHA/YXV0aG9yX25hbWU9JG1hdGNoZXNbMV0mZmVlZD0kbWF0Y2hlc1syXSI7czo0MjoiYXV0aG9y' .
        'LyhbXi9dKykvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjUwOiJpbmRleC5waHA/YXV0aG9yX25hbWU9JG1hdGNoZXNb' .
        'MV0mZmVlZD0kbWF0Y2hlc1syXSI7czoyMzoiYXV0aG9yLyhbXi9dKykvZW1iZWQvPyQiO3M6NDQ6ImluZGV4LnBocD9hdXRob3Jf' .
        'bmFtZT0kbWF0Y2hlc1sxXSZlbWJlZD10cnVlIjtzOjM1OiJhdXRob3IvKFteL10rKS9wYWdlLz8oWzAtOV17MSx9KS8/JCI7czo1' .
        'MToiaW5kZXgucGhwP2F1dGhvcl9uYW1lPSRtYXRjaGVzWzFdJnBhZ2VkPSRtYXRjaGVzWzJdIjtzOjE3OiJhdXRob3IvKFteL10r' .
        'KS8/JCI7czozMzoiaW5kZXgucGhwP2F1dGhvcl9uYW1lPSRtYXRjaGVzWzFdIjtzOjY5OiIoWzAtOV17NH0pLyhbMC05XXsxLDJ9' .
        'KS8oWzAtOV17MSwyfSkvZmVlZC8oZmVlZHxyZGZ8cnNzfHJzczJ8YXRvbSkvPyQiO3M6ODA6ImluZGV4LnBocD95ZWFyPSRtYXRj' .
        'aGVzWzFdJm1vbnRobnVtPSRtYXRjaGVzWzJdJmRheT0kbWF0Y2hlc1szXSZmZWVkPSRtYXRjaGVzWzRdIjtzOjY0OiIoWzAtOV17' .
        'NH0pLyhbMC05XXsxLDJ9KS8oWzAtOV17MSwyfSkvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjgwOiJpbmRleC5waHA/' .
        'eWVhcj0kbWF0Y2hlc1sxXSZtb250aG51bT0kbWF0Y2hlc1syXSZkYXk9JG1hdGNoZXNbM10mZmVlZD0kbWF0Y2hlc1s0XSI7czo0' .
        'NToiKFswLTldezR9KS8oWzAtOV17MSwyfSkvKFswLTldezEsMn0pL2VtYmVkLz8kIjtzOjc0OiJpbmRleC5waHA/eWVhcj0kbWF0' .
        'Y2hlc1sxXSZtb250aG51bT0kbWF0Y2hlc1syXSZkYXk9JG1hdGNoZXNbM10mZW1iZWQ9dHJ1ZSI7czo1NzoiKFswLTldezR9KS8o' .
        'WzAtOV17MSwyfSkvKFswLTldezEsMn0pL3BhZ2UvPyhbMC05XXsxLH0pLz8kIjtzOjgxOiJpbmRleC5waHA/eWVhcj0kbWF0Y2hl' .
        'c1sxXSZtb250aG51bT0kbWF0Y2hlc1syXSZkYXk9JG1hdGNoZXNbM10mcGFnZWQ9JG1hdGNoZXNbNF0iO3M6Mzk6IihbMC05XXs0' .
        'fSkvKFswLTldezEsMn0pLyhbMC05XXsxLDJ9KS8/JCI7czo2MzoiaW5kZXgucGhwP3llYXI9JG1hdGNoZXNbMV0mbW9udGhudW09' .
        'JG1hdGNoZXNbMl0mZGF5PSRtYXRjaGVzWzNdIjtzOjU2OiIoWzAtOV17NH0pLyhbMC05XXsxLDJ9KS9mZWVkLyhmZWVkfHJkZnxy' .
        'c3N8cnNzMnxhdG9tKS8/JCI7czo2NDoiaW5kZXgucGhwP3llYXI9JG1hdGNoZXNbMV0mbW9udGhudW09JG1hdGNoZXNbMl0mZmVl' .
        'ZD0kbWF0Y2hlc1szXSI7czo1MToiKFswLTldezR9KS8oWzAtOV17MSwyfSkvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtz' .
        'OjY0OiJpbmRleC5waHA/eWVhcj0kbWF0Y2hlc1sxXSZtb250aG51bT0kbWF0Y2hlc1syXSZmZWVkPSRtYXRjaGVzWzNdIjtzOjMy' .
        'OiIoWzAtOV17NH0pLyhbMC05XXsxLDJ9KS9lbWJlZC8/JCI7czo1ODoiaW5kZXgucGhwP3llYXI9JG1hdGNoZXNbMV0mbW9udGhu' .
        'dW09JG1hdGNoZXNbMl0mZW1iZWQ9dHJ1ZSI7czo0NDoiKFswLTldezR9KS8oWzAtOV17MSwyfSkvcGFnZS8/KFswLTldezEsfSkv' .
        'PyQiO3M6NjU6ImluZGV4LnBocD95ZWFyPSRtYXRjaGVzWzFdJm1vbnRobnVtPSRtYXRjaGVzWzJdJnBhZ2VkPSRtYXRjaGVzWzNd' .
        'IjtzOjI2OiIoWzAtOV17NH0pLyhbMC05XXsxLDJ9KS8/JCI7czo0NzoiaW5kZXgucGhwP3llYXI9JG1hdGNoZXNbMV0mbW9udGhu' .
        'dW09JG1hdGNoZXNbMl0iO3M6NDM6IihbMC05XXs0fSkvZmVlZC8oZmVlZHxyZGZ8cnNzfHJzczJ8YXRvbSkvPyQiO3M6NDM6Imlu' .
        'ZGV4LnBocD95ZWFyPSRtYXRjaGVzWzFdJmZlZWQ9JG1hdGNoZXNbMl0iO3M6Mzg6IihbMC05XXs0fSkvKGZlZWR8cmRmfHJzc3xy' .
        'c3MyfGF0b20pLz8kIjtzOjQzOiJpbmRleC5waHA/eWVhcj0kbWF0Y2hlc1sxXSZmZWVkPSRtYXRjaGVzWzJdIjtzOjE5OiIoWzAt' .
        'OV17NH0pL2VtYmVkLz8kIjtzOjM3OiJpbmRleC5waHA/eWVhcj0kbWF0Y2hlc1sxXSZlbWJlZD10cnVlIjtzOjMxOiIoWzAtOV17' .
        'NH0pL3BhZ2UvPyhbMC05XXsxLH0pLz8kIjtzOjQ0OiJpbmRleC5waHA/eWVhcj0kbWF0Y2hlc1sxXSZwYWdlZD0kbWF0Y2hlc1sy' .
        'XSI7czoxMzoiKFswLTldezR9KS8/JCI7czoyNjoiaW5kZXgucGhwP3llYXI9JG1hdGNoZXNbMV0iO3M6Mjc6Ii4/Lis/L2F0dGFj' .
        'aG1lbnQvKFteL10rKS8/JCI7czozMjoiaW5kZXgucGhwP2F0dGFjaG1lbnQ9JG1hdGNoZXNbMV0iO3M6Mzc6Ii4/Lis/L2F0dGFj' .
        'aG1lbnQvKFteL10rKS90cmFja2JhY2svPyQiO3M6Mzc6ImluZGV4LnBocD9hdHRhY2htZW50PSRtYXRjaGVzWzFdJnRiPTEiO3M6' .
        'NTc6Ii4/Lis/L2F0dGFjaG1lbnQvKFteL10rKS9mZWVkLyhmZWVkfHJkZnxyc3N8cnNzMnxhdG9tKS8/JCI7czo0OToiaW5kZXgu' .
        'cGhwP2F0dGFjaG1lbnQ9JG1hdGNoZXNbMV0mZmVlZD0kbWF0Y2hlc1syXSI7czo1MjoiLj8uKz8vYXR0YWNobWVudC8oW14vXSsp' .
        'LyhmZWVkfHJkZnxyc3N8cnNzMnxhdG9tKS8/JCI7czo0OToiaW5kZXgucGhwP2F0dGFjaG1lbnQ9JG1hdGNoZXNbMV0mZmVlZD0k' .
        'bWF0Y2hlc1syXSI7czo1MjoiLj8uKz8vYXR0YWNobWVudC8oW14vXSspL2NvbW1lbnQtcGFnZS0oWzAtOV17MSx9KS8/JCI7czo1' .
        'MDoiaW5kZXgucGhwP2F0dGFjaG1lbnQ9JG1hdGNoZXNbMV0mY3BhZ2U9JG1hdGNoZXNbMl0iO3M6MzM6Ii4/Lis/L2F0dGFjaG1l' .
        'bnQvKFteL10rKS9lbWJlZC8/JCI7czo0MzoiaW5kZXgucGhwP2F0dGFjaG1lbnQ9JG1hdGNoZXNbMV0mZW1iZWQ9dHJ1ZSI7czox' .
        'NjoiKC4/Lis/KS9lbWJlZC8/JCI7czo0MToiaW5kZXgucGhwP3BhZ2VuYW1lPSRtYXRjaGVzWzFdJmVtYmVkPXRydWUiO3M6MjA6' .
        'IiguPy4rPykvdHJhY2tiYWNrLz8kIjtzOjM1OiJpbmRleC5waHA/cGFnZW5hbWU9JG1hdGNoZXNbMV0mdGI9MSI7czo0MDoiKC4/' .
        'Lis/KS9mZWVkLyhmZWVkfHJkZnxyc3N8cnNzMnxhdG9tKS8/JCI7czo0NzoiaW5kZXgucGhwP3BhZ2VuYW1lPSRtYXRjaGVzWzFd' .
        'JmZlZWQ9JG1hdGNoZXNbMl0iO3M6MzU6IiguPy4rPykvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjQ3OiJpbmRleC5w' .
        'aHA/cGFnZW5hbWU9JG1hdGNoZXNbMV0mZmVlZD0kbWF0Y2hlc1syXSI7czoyODoiKC4/Lis/KS9wYWdlLz8oWzAtOV17MSx9KS8/' .
        'JCI7czo0ODoiaW5kZXgucGhwP3BhZ2VuYW1lPSRtYXRjaGVzWzFdJnBhZ2VkPSRtYXRjaGVzWzJdIjtzOjM1OiIoLj8uKz8pL2Nv' .
        'bW1lbnQtcGFnZS0oWzAtOV17MSx9KS8/JCI7czo0ODoiaW5kZXgucGhwP3BhZ2VuYW1lPSRtYXRjaGVzWzFdJmNwYWdlPSRtYXRj' .
        'aGVzWzJdIjtzOjI0OiIoLj8uKz8pKD86LyhbMC05XSspKT8vPyQiO3M6NDc6ImluZGV4LnBocD9wYWdlbmFtZT0kbWF0Y2hlc1sx' .
        'XSZwYWdlPSRtYXRjaGVzWzJdIjtzOjI3OiJbXi9dKy9hdHRhY2htZW50LyhbXi9dKykvPyQiO3M6MzI6ImluZGV4LnBocD9hdHRh' .
        'Y2htZW50PSRtYXRjaGVzWzFdIjtzOjM3OiJbXi9dKy9hdHRhY2htZW50LyhbXi9dKykvdHJhY2tiYWNrLz8kIjtzOjM3OiJpbmRl' .
        'eC5waHA/YXR0YWNobWVudD0kbWF0Y2hlc1sxXSZ0Yj0xIjtzOjU3OiJbXi9dKy9hdHRhY2htZW50LyhbXi9dKykvZmVlZC8oZmVl' .
        'ZHxyZGZ8cnNzfHJzczJ8YXRvbSkvPyQiO3M6NDk6ImluZGV4LnBocD9hdHRhY2htZW50PSRtYXRjaGVzWzFdJmZlZWQ9JG1hdGNo' .
        'ZXNbMl0iO3M6NTI6IlteL10rL2F0dGFjaG1lbnQvKFteL10rKS8oZmVlZHxyZGZ8cnNzfHJzczJ8YXRvbSkvPyQiO3M6NDk6Imlu' .
        'ZGV4LnBocD9hdHRhY2htZW50PSRtYXRjaGVzWzFdJmZlZWQ9JG1hdGNoZXNbMl0iO3M6NTI6IlteL10rL2F0dGFjaG1lbnQvKFte' .
        'L10rKS9jb21tZW50LXBhZ2UtKFswLTldezEsfSkvPyQiO3M6NTA6ImluZGV4LnBocD9hdHRhY2htZW50PSRtYXRjaGVzWzFdJmNw' .
        'YWdlPSRtYXRjaGVzWzJdIjtzOjMzOiJbXi9dKy9hdHRhY2htZW50LyhbXi9dKykvZW1iZWQvPyQiO3M6NDM6ImluZGV4LnBocD9h' .
        'dHRhY2htZW50PSRtYXRjaGVzWzFdJmVtYmVkPXRydWUiO3M6MTY6IihbXi9dKykvZW1iZWQvPyQiO3M6Mzc6ImluZGV4LnBocD9u' .
        'YW1lPSRtYXRjaGVzWzFdJmVtYmVkPXRydWUiO3M6MjA6IihbXi9dKykvdHJhY2tiYWNrLz8kIjtzOjMxOiJpbmRleC5waHA/bmFt' .
        'ZT0kbWF0Y2hlc1sxXSZ0Yj0xIjtzOjQwOiIoW14vXSspL2ZlZWQvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjQzOiJp' .
        'bmRleC5waHA/bmFtZT0kbWF0Y2hlc1sxXSZmZWVkPSRtYXRjaGVzWzJdIjtzOjM1OiIoW14vXSspLyhmZWVkfHJkZnxyc3N8cnNz' .
        'MnxhdG9tKS8/JCI7czo0MzoiaW5kZXgucGhwP25hbWU9JG1hdGNoZXNbMV0mZmVlZD0kbWF0Y2hlc1syXSI7czoyODoiKFteL10r' .
        'KS9wYWdlLz8oWzAtOV17MSx9KS8/JCI7czo0NDoiaW5kZXgucGhwP25hbWU9JG1hdGNoZXNbMV0mcGFnZWQ9JG1hdGNoZXNbMl0i' .
        'O3M6MzU6IihbXi9dKykvY29tbWVudC1wYWdlLShbMC05XXsxLH0pLz8kIjtzOjQ0OiJpbmRleC5waHA/bmFtZT0kbWF0Y2hlc1sx' .
        'XSZjcGFnZT0kbWF0Y2hlc1syXSI7czoyNDoiKFteL10rKSg/Oi8oWzAtOV0rKSk/Lz8kIjtzOjQzOiJpbmRleC5waHA/bmFtZT0k' .
        'bWF0Y2hlc1sxXSZwYWdlPSRtYXRjaGVzWzJdIjtzOjE2OiJbXi9dKy8oW14vXSspLz8kIjtzOjMyOiJpbmRleC5waHA/YXR0YWNo' .
        'bWVudD0kbWF0Y2hlc1sxXSI7czoyNjoiW14vXSsvKFteL10rKS90cmFja2JhY2svPyQiO3M6Mzc6ImluZGV4LnBocD9hdHRhY2ht' .
        'ZW50PSRtYXRjaGVzWzFdJnRiPTEiO3M6NDY6IlteL10rLyhbXi9dKykvZmVlZC8oZmVlZHxyZGZ8cnNzfHJzczJ8YXRvbSkvPyQi' .
        'O3M6NDk6ImluZGV4LnBocD9hdHRhY2htZW50PSRtYXRjaGVzWzFdJmZlZWQ9JG1hdGNoZXNbMl0iO3M6NDE6IlteL10rLyhbXi9d' .
        'KykvKGZlZWR8cmRmfHJzc3xyc3MyfGF0b20pLz8kIjtzOjQ5OiJpbmRleC5waHA/YXR0YWNobWVudD0kbWF0Y2hlc1sxXSZmZWVk' .
        'PSRtYXRjaGVzWzJdIjtzOjQxOiJbXi9dKy8oW14vXSspL2NvbW1lbnQtcGFnZS0oWzAtOV17MSx9KS8/JCI7czo1MDoiaW5kZXgu' .
        'cGhwP2F0dGFjaG1lbnQ9JG1hdGNoZXNbMV0mY3BhZ2U9JG1hdGNoZXNbMl0iO3M6MjI6IlteL10rLyhbXi9dKykvZW1iZWQvPyQi' .
        'O3M6NDM6ImluZGV4LnBocD9hdHRhY2htZW50PSRtYXRjaGVzWzFdJmVtYmVkPXRydWUiO30=';
    $decoded = base64_decode($encoded, true);
    twins_staging_il_harness_assert(is_string($decoded), 'frontend rewrite fixture is malformed');
    twins_staging_il_harness_assert(strlen($decoded) === 10103, 'frontend rewrite fixture length changed');
    twins_staging_il_harness_assert(
        hash('sha256', $decoded) === '901a1657f7de11dc41d6ad2ac2cd0e55bcc0314a47809e7c855effb72516845c',
        'frontend rewrite fixture digest changed'
    );
    return $decoded;
}

function twins_staging_il_harness_seed_frontend_initialized(array $manifest): void
{
    twins_staging_il_harness_seed_exact($manifest);
    $state =& $GLOBALS['twins_staging_il_state'];
    foreach ($GLOBALS['twins_staging_il_expected_frontend_tables'] as $table) {
        $state['tables'][] = $table;
    }
    $state['filesystemResidue'] = ['uploads/sites/5'];
    $state['options'][5]['rewrite_rules'] = twins_staging_il_harness_frontend_rewrite_rules();
    $state['frontendRuntimeEvidence'] = [
        'valid' => true,
        'profile' => 'FRONTEND_INITIALIZED',
        'mismatches' => [],
    ];
}

function twins_staging_il_harness_assert_safe_report(array $report, bool $mutated): void
{
    twins_staging_il_harness_assert(($report['productionWriteAuthority'] ?? null) === false, 'report granted production authority');
    twins_staging_il_harness_assert(($report['stagingMutation'] ?? null) === $mutated, 'mutation receipt is inaccurate');
}

function twins_staging_il_harness_expect_preflight_refusal(callable $callback, string $label): Throwable
{
    try {
        $result = $callback();
        if (is_array($result) && preg_match('/(?:FAILED|REFUSED)$/', (string) ($result['status'] ?? ''))) {
            return new RuntimeException((string) ($result['error'] ?? $result['status']));
        }
    } catch (Throwable $error) {
        return $error;
    }
    throw new RuntimeException($label . ' did not fail closed');
}

function twins_staging_il_harness_assert_write_failure(array $result, string $label): void
{
    twins_staging_il_harness_assert_safe_report($result, true);
    twins_staging_il_harness_assert(($result['status'] ?? null) === 'STAGING_IL_PROVISION_FAILED', $label . ' did not return a failed receipt');
    twins_staging_il_harness_assert(twins_staging_il_cli_exit_code($result) !== 0, $label . ' did not map to nonzero');
}

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "STAGING_IL_PROVISION_TOOL_MISSING\n");
    exit(2);
}

require $argv[1];

final class Twins_Staging_IL_Adversarial_Serialized_Value
{
    public function __wakeup(): void
    {
        $GLOBALS['twins_staging_il_adversarial_wakeup']++;
    }
}

$GLOBALS['twins_staging_il_adversarial_wakeup'] = 0;
$adversarialPluginValue = serialize(new Twins_Staging_IL_Adversarial_Serialized_Value());
twins_staging_il_harness_assert(
    twins_staging_il_decode_frontend_plugins($adversarialPluginValue) === null
        && $GLOBALS['twins_staging_il_adversarial_wakeup'] === 0,
    'unauthenticated network plugin bytes were deserialized'
);

$boundedFixture = sys_get_temp_dir() . '/twins-il-runtime-' . bin2hex(random_bytes(8));
if (!mkdir($boundedFixture, 0700)) {
    throw new RuntimeException('bounded runtime fixture could not be created');
}
try {
    file_put_contents($boundedFixture . '/a', 'abc');
    file_put_contents($boundedFixture . '/b', 'def');
    twins_staging_il_harness_assert(
        twins_staging_il_directory_is_exact($boundedFixture, ['a', 'b']),
        'bounded directory reader rejected the exact fixture'
    );
    file_put_contents($boundedFixture . '/c', 'excess');
    twins_staging_il_harness_assert(
        !twins_staging_il_directory_is_exact($boundedFixture, ['a', 'b']),
        'bounded directory reader accepted an excess entry'
    );
    $filePath = $boundedFixture . '/a';
    $fileStat = lstat($filePath);
    $fileExpected = [
        'mode' => ((int) $fileStat['mode']) & 0777,
        'uid' => (int) $fileStat['uid'],
        'gid' => (int) $fileStat['gid'],
        'size' => 3,
        'sha256' => hash('sha256', 'abc'),
    ];
    twins_staging_il_harness_assert(
        twins_staging_il_pinned_file_is_exact($filePath, $fileExpected),
        'bounded pinned-file reader rejected the exact fixture'
    );
    file_put_contents($filePath, 'abc-excess');
    twins_staging_il_harness_assert(
        !twins_staging_il_pinned_file_is_exact($filePath, $fileExpected),
        'bounded pinned-file reader accepted an oversized file'
    );
} finally {
    foreach (['a', 'b', 'c'] as $name) {
        @unlink($boundedFixture . '/' . $name);
    }
    @rmdir($boundedFixture);
}

foreach (['twins_staging_il_manifest' => 0, 'twins_staging_il_status' => 0, 'twins_staging_il_provision' => 1] as $functionName => $arity) {
    twins_staging_il_harness_assert(function_exists($functionName), $functionName . ' is missing');
    $reflection = new ReflectionFunction($functionName);
    twins_staging_il_harness_assert($reflection->getNumberOfParameters() === $arity && $reflection->getNumberOfRequiredParameters() === $arity, $functionName . ' accepts caller-selected input');
}
$dryRunParameter = (new ReflectionFunction('twins_staging_il_provision'))->getParameters()[0];
twins_staging_il_harness_assert($dryRunParameter->hasType() && (string) $dryRunParameter->getType() === 'bool', 'provision input is not only a strict dry-run boolean');

$manifest = twins_staging_il_manifest();
foreach ([
    'domain' => 'danielj140.sg-host.com',
    'path' => '/il/',
    'blogId' => 5,
    'networkId' => 1,
    'ownerUserId' => 21,
    'ownerLogin' => 'chatgptprofile1stage',
    'siteTitle' => 'Garage Door Service in Rockford, Illinois',
    'siteUrl' => 'https://danielj140.sg-host.com/il',
    'phone' => '(815) 800-2025',
    'tel' => '+18158002025',
    'public' => 0,
    'basePrefix' => 'wp8y_',
    'backupPath' => '/home/customer/staging-safety/before-full-overhaul-20260714.sql.gz',
    'backupSha256' => '836dd8850730d4772956e041877cebd23d791700800a0a94588ebf1a9e12f374',
    'cptOptionSha256' => 'a03dea17f88de0d6b4b7f8f377f370d3479b422fc855c2f4b0efcccea350b94f',
] as $key => $expected) {
    twins_staging_il_harness_assert(($manifest[$key] ?? null) === $expected, 'fixed manifest mismatch: ' . $key);
}
twins_staging_il_harness_assert(array_key_exists('address', $manifest) && $manifest['address'] === null, 'manifest invented an Illinois address');
twins_staging_il_harness_assert(is_string($manifest['cptOptionRaw'] ?? null) && hash('sha256', $manifest['cptOptionRaw']) === $manifest['cptOptionSha256'], 'fixed CPT option raw value/hash mismatch');
$manifestCore = array_map(static function (array $page): array {
    return ['slug' => $page['slug'] ?? null, 'title' => $page['title'] ?? null];
}, $manifest['core'] ?? []);
twins_staging_il_harness_assert($manifestCore === $GLOBALS['twins_staging_il_expected_core'], 'nine core pages are not exact');
twins_staging_il_harness_assert(($manifest['cities'] ?? null) === $GLOBALS['twins_staging_il_expected_cities'], 'twelve city slugs are not exact');
twins_staging_il_harness_assert(function_exists('twins_staging_il_cli_exit_code'), 'CLI exit mapping is missing');

foreach ([
    'not multisite' => static function (): void { $GLOBALS['twins_staging_il_state']['multisite'] = false; },
    'wrong staging host' => static function (): void { $GLOBALS['twins_staging_il_state']['networkHomeUrl'] = 'https://wrong.example'; $GLOBALS['twins_staging_il_state']['homeUrl'] = 'https://wrong.example'; },
    'wrong network' => static function (): void { $GLOBALS['twins_staging_il_state']['networkId'] = 2; },
    'wrong main blog' => static function (): void { $GLOBALS['twins_staging_il_state']['currentBlogId'] = 3; },
    'owner missing' => static function (): void { unset($GLOBALS['twins_staging_il_state']['users'][21]); },
    'owner login drift' => static function (): void { $GLOBALS['twins_staging_il_state']['users'][21]['user_login'] = 'wrong-owner'; },
    'owner not super admin' => static function (): void { $GLOBALS['twins_staging_il_state']['superAdminIds'] = []; },
    'unsafe initialization hook' => static function (): void { $GLOBALS['twins_staging_il_state']['unsafeHooks'] = ['wpmu_new_blog']; },
    'unsafe lower lifecycle hook' => static function (): void { $GLOBALS['twins_staging_il_state']['unsafeHooks'] = ['wp_initialize_site_args']; },
    'wrong WordPress version' => static function (): void { $GLOBALS['wp_version'] = '7.0.2'; },
    'invalid runtime evidence' => static function (): void { $GLOBALS['twins_staging_il_state']['runtimeEvidenceValid'] = false; },
    'Astra not network allowed' => static function (): void { $GLOBALS['twins_staging_il_state']['astraAllowed'] = false; },
    'sequence database read failure' => static function (): void { $GLOBALS['twins_staging_il_state']['dbReadFailureNeedle'] = 'information_schema.tables'; },
    'privacy default would create a third page' => static function (): void { $GLOBALS['twins_staging_il_state']['defaultPrivacyPolicyContent'] = 'Privacy Policy'; },
    'duplicate network site-count row' => static function (): void { $GLOBALS['twins_staging_il_state']['duplicateBlogCount'] = true; },
    'custom installer override present' => static function (): void { $GLOBALS['twins_staging_il_state']['installerOverrideAbsent'] = false; },
    'non-InnoDB default storage engine' => static function (): void { $GLOBALS['twins_staging_il_state']['defaultStorageEngine'] = 'MyISAM'; },
    'non-English initializer locale' => static function (): void { $GLOBALS['twins_staging_il_state']['locale'] = 'fr_FR'; },
    'nontransactional network options table' => static function (): void { $GLOBALS['twins_staging_il_state']['coordinationStorage']['wp8y_sitemeta']['engine'] = 'MyISAM'; },
] as $label => $mutate) {
    twins_staging_il_harness_reset();
    $mutate();
    twins_staging_il_harness_expect_preflight_refusal('twins_staging_il_status', $label);
    twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [], $label . ' wrote');
}

foreach (['users', 'usermeta', 'blogs', 'blogmeta', 'signups', 'site', 'sitemeta', 'registration_log'] as $property) {
    twins_staging_il_harness_reset();
    $GLOBALS['wpdb']->{$property} = 'external_' . $property;
    twins_staging_il_harness_expect_preflight_refusal('twins_staging_il_status', 'global table routing drift: ' . $property);
    twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [], 'global table routing drift wrote: ' . $property);
}

foreach ([
    'blog prefix drift' => static function (): void { $GLOBALS['twins_staging_il_state']['blogPrefixOverride'] = 'wrong_5_'; },
    'blog table map drift' => static function (): void { $GLOBALS['twins_staging_il_state']['blogTableMapOverride'] = ['posts' => 'external_posts']; },
    'owner primary blog absent' => static function (): void { $GLOBALS['twins_staging_il_state']['usermeta'] = []; },
    'owner primary blog malformed' => static function (): void { $GLOBALS['twins_staging_il_state']['usermeta'][0]['meta_value'] = '3'; },
    'owner primary blog duplicated' => static function (): void { $GLOBALS['twins_staging_il_state']['usermeta'][] = $GLOBALS['twins_staging_il_state']['usermeta'][0]; },
] as $label => $mutate) {
    twins_staging_il_harness_reset();
    $mutate();
    twins_staging_il_harness_expect_preflight_refusal('twins_staging_il_status', $label);
    twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [], $label . ' wrote');
}

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['targetTableDriftOnNameRead'] = 2;
$absenceRace = twins_staging_il_status();
twins_staging_il_harness_assert(
    ($absenceRace['state'] ?? null) === 'DRIFT'
        && str_contains(implode('; ', (array) ($absenceRace['mismatches'] ?? [])), 'changed while absence was read'),
    'a concurrent target-table addition escaped the ABSENT closing snapshot'
);
twins_staging_il_harness_assert(
    $GLOBALS['twins_staging_il_state']['mutations'] === []
        && $GLOBALS['twins_staging_il_state']['controls'] === [],
    'ABSENT closing race check wrote or locked'
);

twins_staging_il_harness_reset();
$absent = twins_staging_il_status();
twins_staging_il_harness_assert_safe_report($absent, false);
twins_staging_il_harness_assert(($absent['status'] ?? null) === 'STAGING_IL_STATUS' && ($absent['state'] ?? null) === 'ABSENT', 'clean absence was not classified ABSENT');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [] && $GLOBALS['twins_staging_il_state']['controls'] === [], 'status mutated or locked');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['backupChecks'] === 0, 'status touched the mutation-only backup gate');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['filterCalls'] === [] && $GLOBALS['twins_staging_il_state']['htaccessChecks'] === 0, 'status touched write-phase rewrite containment');
$dryRun = twins_staging_il_provision(true);
twins_staging_il_harness_assert_safe_report($dryRun, false);
twins_staging_il_harness_assert(($dryRun['status'] ?? null) === 'STAGING_IL_DRY_RUN' && ($dryRun['beforeState'] ?? null) === 'ABSENT' && ($dryRun['projectedState'] ?? null) === 'EXACT', 'dry-run projection is not fixed');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [] && $GLOBALS['twins_staging_il_state']['controls'] === [], 'dry-run mutated or locked');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['backupChecks'] === 0, 'dry-run touched the mutation-only backup gate');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['filterCalls'] === [] && $GLOBALS['twins_staging_il_state']['htaccessChecks'] === 0, 'dry-run touched write-phase rewrite containment');

foreach ([
    'target table residue' => static function (): void { $GLOBALS['twins_staging_il_state']['tables'][] = 'wp8y_5_options'; },
    'wrong next auto increment' => static function (): void { $GLOBALS['twins_staging_il_state']['autoIncrement'] = 6; },
    'target ownership residue' => static function (): void { $GLOBALS['twins_staging_il_state']['usermeta'][] = ['user_id' => 21, 'meta_key' => 'wp8y_5_capabilities', 'meta_value' => 'x']; },
    'target filesystem residue' => static function (): void { $GLOBALS['twins_staging_il_state']['filesystemResidue'][] = 'uploads/sites/5'; },
    'blog five collision' => static function (): void { $GLOBALS['twins_staging_il_state']['sites'][5] = twins_staging_il_harness_site_row(5, 'danielj140.sg-host.com', '/other/', 0); },
    'path collision' => static function (): void { $GLOBALS['twins_staging_il_state']['sites'][6] = twins_staging_il_harness_site_row(6, 'danielj140.sg-host.com', '/il/', 0); },
    'unexpected unrelated site' => static function (): void { $GLOBALS['twins_staging_il_state']['sites'][6] = twins_staging_il_harness_site_row(6, 'danielj140.sg-host.com', '/unexpected/', 0); },
] as $label => $contaminate) {
    twins_staging_il_harness_reset();
    $contaminate();
    $status = twins_staging_il_status();
    twins_staging_il_harness_assert(($status['state'] ?? null) === 'DRIFT' && ($status['mismatches'] ?? []) !== [], $label . ' did not fail closed as DRIFT');
    twins_staging_il_harness_expect_preflight_refusal(static function (): array { return twins_staging_il_provision(false); }, $label);
    twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [], $label . ' attempted repair');
}

twins_staging_il_harness_seed_exact($manifest);
$exact = twins_staging_il_status();
twins_staging_il_harness_assert_safe_report($exact, false);
twins_staging_il_harness_assert(($exact['state'] ?? null) === 'EXACT', 'exact fixed site was not classified EXACT');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [] && get_current_blog_id() === 1, 'exact status mutated or changed context');
$GLOBALS['twins_staging_il_state']['muPhpFiles'] = ['twins-staging-overhaul.php', 'twins-staging-safety.php'];
$GLOBALS['twins_staging_il_state']['overhaulDigest'] = '20a3e8b8d88917f54173457f112562c6a31250f9385a3144d9771704d63a2e90';
$deployedExact = twins_staging_il_status();
twins_staging_il_harness_assert_safe_report($deployedExact, false);
twins_staging_il_harness_assert(($deployedExact['state'] ?? null) === 'EXACT', 'read-only status rejected the exact deployed overhaul MU-plugin set');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [] && $GLOBALS['twins_staging_il_state']['controls'] === [], 'deployed read-only status wrote or locked');
$exactDryRun = twins_staging_il_provision(true);
twins_staging_il_harness_assert_safe_report($exactDryRun, false);
twins_staging_il_harness_assert(($exactDryRun['status'] ?? null) === 'STAGING_IL_DRY_RUN' && ($exactDryRun['beforeState'] ?? null) === 'EXACT' && ($exactDryRun['projectedState'] ?? null) === 'EXACT', 'exact dry-run did not remain read-only and exact');
$exactRefusal = twins_staging_il_provision(false);
twins_staging_il_harness_assert_safe_report($exactRefusal, false);
twins_staging_il_harness_assert(($exactRefusal['status'] ?? null) === 'STAGING_IL_REFUSED', 'exact apply was not explicitly refused');
twins_staging_il_harness_assert(twins_staging_il_cli_exit_code($exactRefusal) !== 0, 'exact apply refusal did not map to nonzero');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [] && $GLOBALS['twins_staging_il_state']['controls'] === [], 'exact read-only paths wrote or locked');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['backupChecks'] === 0 && $GLOBALS['twins_staging_il_state']['filterCalls'] === [] && $GLOBALS['twins_staging_il_state']['htaccessChecks'] === 0, 'exact read-only paths entered the write phase');

twins_staging_il_harness_seed_frontend_initialized($manifest);
$GLOBALS['twins_staging_il_state']['muPhpFiles'] = ['twins-staging-overhaul.php', 'twins-staging-safety.php'];
$GLOBALS['twins_staging_il_state']['overhaulDigest'] = '20a3e8b8d88917f54173457f112562c6a31250f9385a3144d9771704d63a2e90';
$frontendInitialized = twins_staging_il_status();
twins_staging_il_harness_assert_safe_report($frontendInitialized, false);
twins_staging_il_harness_assert(
    ($frontendInitialized['state'] ?? null) === 'EXACT'
        && ($frontendInitialized['runtimeProfile'] ?? null) === 'FRONTEND_INITIALIZED'
        && ($frontendInitialized['mismatches'] ?? null) === [],
    'the complete pinned frontend-initialized profile was not accepted as EXACT'
);
twins_staging_il_harness_assert(
    $GLOBALS['twins_staging_il_state']['mutations'] === []
        && $GLOBALS['twins_staging_il_state']['controls'] === [],
    'frontend-initialized status wrote or locked'
);

foreach ([
    'missing Gravity Forms table' => static function (): void {
        $missing = $GLOBALS['twins_staging_il_expected_frontend_tables'][0];
        $GLOBALS['twins_staging_il_state']['tables'] = array_values(array_filter(
            $GLOBALS['twins_staging_il_state']['tables'],
            static fn(string $table): bool => $table !== $missing
        ));
    },
    'extra target table' => static function (): void {
        $GLOBALS['twins_staging_il_state']['tables'][] = 'wp8y_5_gf_addon_feed';
    },
    'missing frontend filesystem tree' => static function (): void {
        $GLOBALS['twins_staging_il_state']['filesystemResidue'] = [];
    },
    'legacy uploads tree present' => static function (): void {
        $GLOBALS['twins_staging_il_state']['filesystemResidue'][] = 'blogs.dir/5';
    },
    'Gravity Forms table storage changed' => static function (): void {
        $table = $GLOBALS['twins_staging_il_expected_frontend_tables'][0];
        $GLOBALS['twins_staging_il_state']['tableStorageOverrides'][$table] = [
            'engine' => 'InnoDB',
            'table_collation' => 'utf8mb4_unicode_ci',
        ];
    },
    'frontend rewrite receipt changed' => static function (): void {
        $GLOBALS['twins_staging_il_state']['options'][5]['rewrite_rules'] .= 'x';
    },
    'frontend rewrite receipt changed without changing length' => static function (): void {
        $current = (string) $GLOBALS['twins_staging_il_state']['options'][5]['rewrite_rules'];
        $offset = 100;
        $replacement = $current[$offset] === 'x' ? 'y' : 'x';
        $changed = substr_replace($current, $replacement, $offset, 1);
        twins_staging_il_harness_assert(strlen($changed) === strlen($current), 'same-length rewrite tamper fixture changed size');
        twins_staging_il_harness_assert(hash('sha256', $changed) !== hash('sha256', $current), 'same-length rewrite tamper fixture kept its digest');
        $GLOBALS['twins_staging_il_state']['options'][5]['rewrite_rules'] = $changed;
    },
] as $label => $mutate) {
    twins_staging_il_harness_seed_frontend_initialized($manifest);
    $mutate();
    $profileDrift = twins_staging_il_status();
    twins_staging_il_harness_assert(
        ($profileDrift['state'] ?? null) === 'DRIFT'
            && ($profileDrift['mismatches'] ?? []) !== [],
        $label . ' was accepted as frontend-initialized EXACT'
    );
    twins_staging_il_harness_assert(
        $GLOBALS['twins_staging_il_state']['mutations'] === []
            && $GLOBALS['twins_staging_il_state']['controls'] === [],
        $label . ' wrote or locked'
    );
}

twins_staging_il_harness_seed_frontend_initialized($manifest);
$GLOBALS['twins_staging_il_state']['options'][5]['unrelated_megabyte_fixture'] = str_repeat('u', 1024 * 1024);
$hugeUnrelatedOption = twins_staging_il_status();
twins_staging_il_harness_assert(
    ($hugeUnrelatedOption['state'] ?? null) === 'EXACT'
        && !in_array('unrelated_megabyte_fixture', $GLOBALS['twins_staging_il_state']['optionNamesReturned'], true)
        && $GLOBALS['twins_staging_il_state']['maxOptionValueBytesReturned'] <= 10104,
    'status materialized a huge unrelated option value'
);
twins_staging_il_harness_assert(
    $GLOBALS['twins_staging_il_state']['mutations'] === []
        && $GLOBALS['twins_staging_il_state']['controls'] === [],
    'huge unrelated option check wrote or locked'
);

twins_staging_il_harness_seed_frontend_initialized($manifest);
$GLOBALS['twins_staging_il_state']['options'][5]['home'] = str_repeat('h', 1024 * 1024);
$hugeTargetOption = twins_staging_il_status();
twins_staging_il_harness_assert(
    ($hugeTargetOption['state'] ?? null) === 'DRIFT'
        && $GLOBALS['twins_staging_il_state']['maxOptionValueBytesReturned'] <= 10104,
    'status did not reject a huge target option through a bounded transfer'
);
twins_staging_il_harness_assert(
    $GLOBALS['twins_staging_il_state']['mutations'] === []
        && $GLOBALS['twins_staging_il_state']['controls'] === [],
    'huge target option check wrote or locked'
);

foreach ([
    'Gravity Forms table is nonempty',
    'Gravity Forms schema digest changed',
    'network-active plugin evidence changed',
    'frontend filesystem manifest changed',
    'frontend evidence changed while it was read',
] as $label) {
    twins_staging_il_harness_seed_frontend_initialized($manifest);
    $GLOBALS['twins_staging_il_state']['frontendRuntimeEvidence'] = [
        'valid' => false,
        'profile' => 'FRONTEND_INITIALIZED',
        'mismatches' => [$label],
    ];
    $evidenceDrift = twins_staging_il_status();
    twins_staging_il_harness_assert(
        ($evidenceDrift['state'] ?? null) === 'DRIFT'
            && str_contains(implode('; ', (array) ($evidenceDrift['mismatches'] ?? [])), $label),
        $label . ' was not reported as frontend runtime drift'
    );
    twins_staging_il_harness_assert(
        $GLOBALS['twins_staging_il_state']['mutations'] === []
            && $GLOBALS['twins_staging_il_state']['controls'] === [],
        $label . ' wrote or locked'
    );
}

foreach ([
    'false-valid empty frontend evidence' => [
        'valid' => false,
        'profile' => 'FRONTEND_INITIALIZED',
        'mismatches' => [],
    ],
    'wrong-profile empty frontend evidence' => [
        'valid' => true,
        'profile' => 'WRONG',
        'mismatches' => [],
    ],
] as $label => $invalidEvidence) {
    twins_staging_il_harness_seed_frontend_initialized($manifest);
    $GLOBALS['twins_staging_il_state']['frontendRuntimeEvidence'] = $invalidEvidence;
    $emptyEvidenceDrift = twins_staging_il_status();
    twins_staging_il_harness_assert(
        ($emptyEvidenceDrift['state'] ?? null) === 'DRIFT'
            && str_contains(implode('; ', (array) ($emptyEvidenceDrift['mismatches'] ?? [])), 'frontend runtime evidence is invalid'),
        $label . ' was accepted as EXACT'
    );
}

twins_staging_il_harness_seed_frontend_initialized($manifest);
$GLOBALS['twins_staging_il_state']['targetTableDriftOnNameRead'] = 2;
$closingRace = twins_staging_il_status();
twins_staging_il_harness_assert(
    ($closingRace['state'] ?? null) === 'DRIFT'
        && str_contains(implode('; ', (array) ($closingRace['mismatches'] ?? [])), 'changed while status was read'),
    'a concurrent target-table addition escaped the closing snapshot'
);
twins_staging_il_harness_assert(
    $GLOBALS['twins_staging_il_state']['mutations'] === []
        && $GLOBALS['twins_staging_il_state']['controls'] === [],
    'closing table-race check wrote or locked'
);

twins_staging_il_harness_seed_frontend_initialized($manifest);
$GLOBALS['twins_staging_il_state']['rewriteDriftOnEvidenceRead'] = 2;
$closingRewriteRace = twins_staging_il_status();
twins_staging_il_harness_assert(
    ($closingRewriteRace['state'] ?? null) === 'DRIFT'
        && str_contains(implode('; ', (array) ($closingRewriteRace['mismatches'] ?? [])), 'rewrite evidence changed while status was read'),
    'a concurrent rewrite update escaped the closing snapshot'
);
twins_staging_il_harness_assert(
    $GLOBALS['twins_staging_il_state']['rewriteEvidenceReads'] === 2
        && $GLOBALS['twins_staging_il_state']['mutations'] === []
        && $GLOBALS['twins_staging_il_state']['controls'] === [],
    'closing rewrite-race check wrote, locked, or skipped its bounded reread'
);

twins_staging_il_harness_seed_exact($manifest);

$GLOBALS['twins_staging_il_adversarial_wakeup'] = 0;
$GLOBALS['twins_staging_il_state']['options'][5]['rewrite_rules'] = serialize(new Twins_Staging_IL_Adversarial_Serialized_Value());
$rewriteObjectDrift = twins_staging_il_status();
twins_staging_il_harness_assert(
    ($rewriteObjectDrift['state'] ?? null) === 'DRIFT'
        && $GLOBALS['twins_staging_il_adversarial_wakeup'] === 0,
    'status deserialized unauthenticated rewrite-rule bytes'
);

twins_staging_il_harness_seed_exact($manifest);

$firstPostId = array_key_first($GLOBALS['twins_staging_il_state']['posts'][5]);
$GLOBALS['twins_staging_il_state']['posts'][5][$firstPostId]['post_status'] = 'draft';
$drift = twins_staging_il_status();
twins_staging_il_harness_assert(($drift['state'] ?? null) === 'DRIFT' && ($drift['mismatches'] ?? []) !== [], 'post drift was not reported');
twins_staging_il_harness_expect_preflight_refusal(static function (): array { return twins_staging_il_provision(true); }, 'drift dry-run');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [], 'drift dry-run attempted repair');

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['muPhpFiles'] = ['twins-staging-overhaul.php', 'twins-staging-safety.php'];
$GLOBALS['twins_staging_il_state']['overhaulDigest'] = '20a3e8b8d88917f54173457f112562c6a31250f9385a3144d9771704d63a2e90';
$deployedApplyRefusal = twins_staging_il_provision(false);
twins_staging_il_harness_assert_safe_report($deployedApplyRefusal, false);
twins_staging_il_harness_assert(($deployedApplyRefusal['status'] ?? null) === 'STAGING_IL_REFUSED', 'deployed overhaul runtime was allowed to enter apply');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [] && $GLOBALS['twins_staging_il_state']['controls'] === [], 'deployed apply refusal wrote or locked');

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['targetTableDriftOnNameRead'] = 5;
$guardRaceRefusal = twins_staging_il_provision(false);
twins_staging_il_harness_assert_safe_report($guardRaceRefusal, false);
twins_staging_il_harness_assert(
    ($guardRaceRefusal['status'] ?? null) === 'STAGING_IL_REFUSED'
        && str_contains((string) ($guardRaceRefusal['error'] ?? ''), 'insertion residue changed'),
    'the insertion guard accepted concurrent target residue'
);
twins_staging_il_harness_assert(
    $GLOBALS['twins_staging_il_state']['mutations'] === [],
    'the insertion-guard race performed a partial write'
);

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['nonTargetRewriteDriftOnFlush'] = true;
$cachedRewriteRace = twins_staging_il_provision(false);
twins_staging_il_harness_assert_safe_report($cachedRewriteRace, true);
twins_staging_il_harness_assert(
    ($cachedRewriteRace['status'] ?? null) === 'STAGING_IL_PROVISION_FAILED'
        && str_contains((string) ($cachedRewriteRace['error'] ?? ''), 'another staging site rewrite changed'),
    'a concurrent raw non-target rewrite change was masked by the WordPress option cache'
);
twins_staging_il_harness_assert(
    $GLOBALS['twins_staging_il_state']['nonTargetRewriteEvidenceReads'] === 6
        && $GLOBALS['twins_staging_il_state']['maxNonTargetRewriteValueBytesReturned'] < 1048576,
    'non-target rewrite preservation was not proven by six bounded authoritative reads'
);

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['options'][3]['rewrite_rules'] = str_repeat('x', 1048577);
$oversizedRewriteRefusal = twins_staging_il_provision(false);
twins_staging_il_harness_assert_safe_report($oversizedRewriteRefusal, false);
twins_staging_il_harness_assert(
    ($oversizedRewriteRefusal['status'] ?? null) === 'STAGING_IL_REFUSED'
        && str_contains((string) ($oversizedRewriteRefusal['error'] ?? ''), 'non-target rewrite evidence exceeds fixed byte limit'),
    'oversized non-target rewrite evidence did not fail closed before mutation'
);

twins_staging_il_harness_reset();
$rewriteHashesBefore = [
    1 => hash('sha256', $GLOBALS['twins_staging_il_state']['options'][1]['rewrite_rules']),
    3 => hash('sha256', $GLOBALS['twins_staging_il_state']['options'][3]['rewrite_rules']),
    4 => hash('sha256', $GLOBALS['twins_staging_il_state']['options'][4]['rewrite_rules']),
];
$htaccessDigestBefore = $GLOBALS['twins_staging_il_state']['htaccessDigest'];
$applied = twins_staging_il_provision(false);
twins_staging_il_harness_assert_safe_report($applied, true);
twins_staging_il_harness_assert(($applied['status'] ?? null) === 'STAGING_IL_PROVISION_OK' && ($applied['beforeState'] ?? null) === 'ABSENT' && ($applied['afterState'] ?? null) === 'EXACT', 'one-pass apply did not verify exact final state: ' . json_encode($applied));

$controls = $GLOBALS['twins_staging_il_state']['controls'];
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['backupChecks'] === 1, 'actual apply did not re-hash the fixed backup exactly once');
$operationNames = array_column($GLOBALS['twins_staging_il_state']['operations'], 'op');
$lockPosition = array_search('get-lock', $operationNames, true);
$backupPosition = array_search('backup-gate', $operationNames, true);
$firstWritePosition = array_search('db-insert', $operationNames, true);
twins_staging_il_harness_assert(is_int($lockPosition) && is_int($backupPosition) && is_int($firstWritePosition) && $lockPosition < $backupPosition, 'fixed backup gate was not before the write phase');
$preInsertOperations = array_slice($operationNames, $backupPosition + 1, $firstWritePosition - $backupPosition - 1);
twins_staging_il_harness_assert($preInsertOperations === ['set-isolation', 'start-transaction', 'row-guard', 'row-guard'], 'only the nonblocking site and network-default guards may occur between backup proof and first write');
twins_staging_il_harness_assert(count(array_filter($controls, static fn(array $row): bool => $row['op'] === 'get-lock')) === 1, 'advisory lock was retried');
twins_staging_il_harness_assert(count(array_filter($controls, static fn(array $row): bool => $row['op'] === 'release-lock')) === 1, 'advisory lock was not released exactly once');
twins_staging_il_harness_assert(stripos($controls[0]['sql'], 'GET_LOCK') !== false && preg_match('/,\s*0\s*\)/', $controls[0]['sql']) === 1, 'advisory lock was not nonblocking');

$mutations = $GLOBALS['twins_staging_il_state']['mutations'];
twins_staging_il_harness_assert(($mutations[0]['op'] ?? null) === 'db-insert' && ($mutations[0]['table'] ?? null) === 'wp8y_blogs', 'first write was not the fixed site row');
$siteInsert = $mutations[0]['data'];
foreach (['blog_id' => 5, 'site_id' => 1, 'domain' => 'danielj140.sg-host.com', 'path' => '/il/', 'public' => 0, 'archived' => 0, 'mature' => 0, 'spam' => 0, 'deleted' => 0, 'lang_id' => 0] as $field => $expected) {
    twins_staging_il_harness_assert(($siteInsert[$field] ?? null) === $expected, 'initial private site row mismatch: ' . $field);
}
$initializations = array_values(array_filter($mutations, static fn(array $row): bool => $row['op'] === 'initialize-site'));
twins_staging_il_harness_assert(count($initializations) === 1 && $initializations[0]['blogId'] === 5, 'core site initialization was not one fixed call');
twins_staging_il_harness_assert(($initializations[0]['arguments']['user_id'] ?? null) === 21, 'site initialization owner is not fixed');
twins_staging_il_harness_assert(($initializations[0]['collation'] ?? null) === 'utf8mb4_unicode_ci', 'new tables were not pinned to the existing staging collation');
twins_staging_il_harness_assert($GLOBALS['wpdb']->collate === 'utf8mb4_unicode_520_ci', 'runtime database collation was not restored after initialization');

$postInserts = array_values(array_filter($mutations, static fn(array $row): bool => $row['op'] === 'db-insert' && $row['table'] === 'wp8y_5_posts'));
twins_staging_il_harness_assert(count($postInserts) === 21, 'direct seed must contain exactly 21 posts');
$expectedPosts = [];
foreach ($GLOBALS['twins_staging_il_expected_core'] as $page) {
    $expectedPosts[] = ['ID' => count($expectedPosts) + 1, 'post_name' => $page['slug'] === '' ? 'home' : $page['slug'], 'post_title' => $page['title'], 'post_status' => 'publish', 'post_type' => 'page'];
}
foreach ($GLOBALS['twins_staging_il_expected_cities'] as $slug) {
    $expectedPosts[] = ['ID' => count($expectedPosts) + 1, 'post_name' => $slug, 'post_title' => $GLOBALS['twins_staging_il_city_titles'][$slug], 'post_status' => 'publish', 'post_type' => 'location'];
}
foreach ($postInserts as $index => $insert) {
    foreach ($expectedPosts[$index] as $field => $expected) {
        twins_staging_il_harness_assert(($insert['data'][$field] ?? null) === $expected, 'deterministic post mismatch at ' . $index . ': ' . $field);
    }
}
twins_staging_il_harness_assert(($GLOBALS['twins_staging_il_state']['options'][5]['home'] ?? null) === 'https://danielj140.sg-host.com/il', 'home inherited HTTP');
twins_staging_il_harness_assert(($GLOBALS['twins_staging_il_state']['options'][5]['siteurl'] ?? null) === 'https://danielj140.sg-host.com/il', 'siteurl inherited HTTP');
twins_staging_il_harness_assert((int) ($GLOBALS['twins_staging_il_state']['options'][5]['blog_public'] ?? 1) === 0, 'final blog option is public');
twins_staging_il_harness_assert(($GLOBALS['twins_staging_il_state']['options'][5]['show_on_front'] ?? null) === 'page', 'homepage mode mismatch');
twins_staging_il_harness_assert(($GLOBALS['twins_staging_il_state']['options'][5]['template'] ?? null) === 'astra' && ($GLOBALS['twins_staging_il_state']['options'][5]['stylesheet'] ?? null) === 'astra', 'fixed Astra theme options are missing');
twins_staging_il_harness_assert(($GLOBALS['twins_staging_il_state']['options'][5]['active_plugins'] ?? null) === 'a:0:{}', 'new site plugins were not kept inactive');
twins_staging_il_harness_assert(!array_key_exists('twins_staging_il_address', $GLOBALS['twins_staging_il_state']['options'][5]), 'an Illinois address option was invented');
$home = null;
foreach ($GLOBALS['twins_staging_il_state']['posts'][5] as $row) {
    if ($row['post_name'] === 'home' && $row['post_type'] === 'page') {
        $home = $row;
        break;
    }
}
twins_staging_il_harness_assert(is_array($home) && (int) ($GLOBALS['twins_staging_il_state']['options'][5]['page_on_front'] ?? 0) === (int) $home['ID'], 'page_on_front does not point to home');
twins_staging_il_harness_assert(hash('sha256', (string) ($GLOBALS['twins_staging_il_state']['options'][5]['cptui_post_types'] ?? '')) === $manifest['cptOptionSha256'], 'fixed CPT configuration was not installed');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['blogCount'] === 4, 'network site count was not advanced exactly once');
$countUpdates = array_values(array_filter($mutations, static fn(array $row): bool => $row['op'] === 'db-update' && $row['table'] === 'wp8y_sitemeta'));
twins_staging_il_harness_assert(count($countUpdates) === 1, 'network site-count transition was retried or omitted');

$flushes = array_values(array_filter($mutations, static fn(array $row): bool => $row['op'] === 'flush-rewrite-rules'));
twins_staging_il_harness_assert(count($flushes) === 2, 'apply did not contain exactly the implicit and final rewrite flushes');
twins_staging_il_harness_assert($flushes[0]['blogId'] === 5 && $flushes[0]['requestedHard'] === true && $flushes[0]['hard'] === false, 'implicit initialization flush was not downgraded to Illinois-only soft mode');
twins_staging_il_harness_assert($flushes[1]['blogId'] === 5 && $flushes[1]['requestedHard'] === false && $flushes[1]['hard'] === false, 'final rewrite flush was not Illinois-only soft mode');
twins_staging_il_harness_assert(count(array_filter($flushes, static fn(array $row): bool => $row['hard'] === true)) === 0, 'a hard rewrite flush escaped containment');
$filterCalls = $GLOBALS['twins_staging_il_state']['filterCalls'];
twins_staging_il_harness_assert(count($filterCalls) === 2, 'hard-flush filter was not installed and removed exactly once');
twins_staging_il_harness_assert($filterCalls[0]['op'] === 'add' && $filterCalls[0]['hook'] === 'flush_rewrite_rules_hard', 'hard-flush containment filter was not installed');
twins_staging_il_harness_assert($filterCalls[1]['op'] === 'remove' && $filterCalls[1]['hook'] === 'flush_rewrite_rules_hard', 'hard-flush containment filter was not removed');
twins_staging_il_harness_assert($filterCalls[0]['callback'] === $filterCalls[1]['callback'] && $filterCalls[0]['priority'] === $filterCalls[1]['priority'], 'hard-flush filter removal did not match its installation');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['htaccessChecks'] === 3 && $GLOBALS['twins_staging_il_state']['htaccessDigest'] === $htaccessDigestBefore, '.htaccess was not proven unchanged through the final soft flush');
twins_staging_il_harness_assert(!isset($GLOBALS['twins_staging_il_state']['registeredPostTypes'][5]['location']), 'temporary location CPT registration leaked after the flush');
$operationNames = array_column($GLOBALS['twins_staging_il_state']['operations'], 'op');
$addFilterPosition = array_search('add-hard-flush-filter', $operationNames, true);
$initializePosition = array_search('initialize-site', $operationNames, true);
$removeFilterPosition = array_search('remove-hard-flush-filter', $operationNames, true);
$registerPosition = array_search('register-post-type', $operationNames, true);
$unregisterPosition = array_search('unregister-post-type', $operationNames, true);
$flushPositions = array_keys($operationNames, 'flush-rewrite-rules', true);
twins_staging_il_harness_assert(is_int($addFilterPosition) && is_int($initializePosition) && is_int($removeFilterPosition) && $addFilterPosition < $initializePosition && $initializePosition < $removeFilterPosition, 'hard-flush filter did not wrap only site initialization');
twins_staging_il_harness_assert(count($flushPositions) === 2 && $addFilterPosition < $flushPositions[0] && $flushPositions[0] < $removeFilterPosition, 'implicit initialization flush escaped its containment window');
twins_staging_il_harness_assert(is_int($registerPosition) && $removeFilterPosition < $registerPosition && $registerPosition < $flushPositions[1], 'location CPT registration did not precede the final soft flush');
twins_staging_il_harness_assert(is_int($unregisterPosition) && $flushPositions[1] < $unregisterPosition, 'temporary location CPT registration was not removed after the final flush');
$rewriteInitBlogs = array_values(array_map(static fn(array $row): int => (int) $row['blogId'], array_filter($GLOBALS['twins_staging_il_state']['operations'], static fn(array $row): bool => $row['op'] === 'rewrite-init')));
twins_staging_il_harness_assert($rewriteInitBlogs === [5, 1], 'rewrite object was not initialized for Illinois and then restored to main');
foreach ($rewriteHashesBefore as $blogId => $hash) {
    twins_staging_il_harness_assert(hash('sha256', $GLOBALS['twins_staging_il_state']['options'][$blogId]['rewrite_rules']) === $hash, 'rewrite rules changed outside Illinois: ' . $blogId);
}
twins_staging_il_harness_assert(get_current_blog_id() === 1, 'apply did not restore the main blog');
twins_staging_il_harness_assert(($GLOBALS['twins_staging_il_state']['operations'][array_key_last($GLOBALS['twins_staging_il_state']['operations'])]['op'] ?? null) === 'release-lock', 'lock was not released after final verification');

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['lockAvailable'] = false;
twins_staging_il_harness_expect_preflight_refusal(static function (): array { return twins_staging_il_provision(false); }, 'busy advisory lock');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [], 'busy lock allowed a write');
twins_staging_il_harness_assert(count($GLOBALS['twins_staging_il_state']['controls']) === 1 && $GLOBALS['twins_staging_il_state']['controls'][0]['op'] === 'get-lock', 'busy lock was retried or released without ownership');

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['rowGuardAvailable'] = false;
$rowGuardConflict = twins_staging_il_provision(false);
twins_staging_il_harness_assert_safe_report($rowGuardConflict, false);
twins_staging_il_harness_assert(($rowGuardConflict['status'] ?? null) === 'STAGING_IL_REFUSED', 'NOWAIT row-guard conflict was not refused');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['mutations'] === [], 'NOWAIT row-guard conflict allowed a write');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['controls'], static fn(array $row): bool => $row['op'] === 'row-guard')) === 1, 'NOWAIT row guard was retried');
twins_staging_il_harness_assert($GLOBALS['twins_staging_il_state']['transactionOpen'] === false, 'NOWAIT conflict left the insertion transaction open');

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['dbInsertFailureAt'] = 1;
$indeterminateFirstWrite = twins_staging_il_provision(false);
twins_staging_il_harness_assert_write_failure($indeterminateFirstWrite, 'indeterminate first write');
twins_staging_il_harness_assert(count(array_filter(
    $GLOBALS['twins_staging_il_state']['mutations'],
    static fn(array $row): bool => $row['op'] === 'db-insert' && $row['table'] === 'wp8y_blogs'
)) === 1, 'indeterminate first write was retried');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['controls'], static fn(array $row): bool => $row['op'] === 'get-lock')) === 1, 'indeterminate first write retried lock acquisition');

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['initializeFailure'] = true;
$initializationFailure = twins_staging_il_provision(false);
twins_staging_il_harness_assert_write_failure($initializationFailure, 'initialization failure');
twins_staging_il_harness_assert(isset($GLOBALS['twins_staging_il_state']['sites'][5]) && (int) $GLOBALS['twins_staging_il_state']['sites'][5]['public'] === 0, 'failed initialization cleaned up or exposed the site row');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['mutations'], static fn(array $row): bool => $row['op'] === 'initialize-site')) === 1, 'initialization was retried');
twins_staging_il_harness_assert(array_column($GLOBALS['twins_staging_il_state']['filterCalls'], 'op') === ['add', 'remove'], 'failed initialization did not remove its hard-flush filter');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['controls'], static fn(array $row): bool => $row['op'] === 'get-lock')) === 1, 'failed initialization retried its lock');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['controls'], static fn(array $row): bool => $row['op'] === 'release-lock')) === 1, 'failed initialization did not release its lock');

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['initializeReturnError'] = true;
$initializerErrorResult = twins_staging_il_provision(false);
twins_staging_il_harness_assert_write_failure($initializerErrorResult, 'initializer WP_Error result');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['mutations'], static fn(array $row): bool => $row['op'] === 'db-delete')) === 0, 'initializer WP_Error allowed default cleanup');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['mutations'], static fn(array $row): bool => $row['op'] === 'db-insert' && $row['table'] === 'wp8y_5_posts')) === 0, 'initializer WP_Error allowed content seeding');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['controls'], static fn(array $row): bool => $row['op'] === 'get-lock')) === 1, 'initializer WP_Error retried lock acquisition');

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['initializeBaselineDrift'] = true;
$baselineDriftResult = twins_staging_il_provision(false);
twins_staging_il_harness_assert_write_failure($baselineDriftResult, 'unexpected initializer baseline');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['mutations'], static fn(array $row): bool => $row['op'] === 'db-delete')) === 0, 'unexpected baseline allowed a cleanup delete');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['mutations'], static fn(array $row): bool => $row['op'] === 'db-insert' && $row['table'] === 'wp8y_5_posts')) === 0, 'unexpected baseline allowed content seeding');

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['postDriftAt'][1] = ['post_title' => 'Unexpected title'];
$readbackFailure = twins_staging_il_provision(false);
twins_staging_il_harness_assert_write_failure($readbackFailure, 'post read-back mismatch');
$failedPostInserts = array_values(array_filter($GLOBALS['twins_staging_il_state']['mutations'], static fn(array $row): bool => $row['op'] === 'db-insert' && $row['table'] === 'wp8y_5_posts'));
twins_staging_il_harness_assert(count($failedPostInserts) === 1, 'post mismatch was not verified before the next write');
$failedFlushes = array_values(array_filter($GLOBALS['twins_staging_il_state']['mutations'], static fn(array $row): bool => $row['op'] === 'flush-rewrite-rules'));
twins_staging_il_harness_assert(count($failedFlushes) === 1 && $failedFlushes[0]['requestedHard'] === true && $failedFlushes[0]['hard'] === false, 'failed post verification performed anything beyond the contained implicit flush');
twins_staging_il_harness_assert(isset($GLOBALS['twins_staging_il_state']['sites'][5]), 'failed post verification automatically rolled back the site');
twins_staging_il_harness_assert(count(array_filter($GLOBALS['twins_staging_il_state']['controls'], static fn(array $row): bool => $row['op'] === 'get-lock')) === 1, 'post failure retried lock acquisition');

twins_staging_il_harness_reset();
$GLOBALS['twins_staging_il_state']['initializeStorageDrift'] = true;
$storageFailure = twins_staging_il_provision(false);
twins_staging_il_harness_assert_write_failure($storageFailure, 'initializer storage mismatch');
twins_staging_il_harness_assert(count(array_filter(
    $GLOBALS['twins_staging_il_state']['mutations'],
    static fn(array $row): bool => in_array($row['op'], ['db-delete', 'db-update'], true) || ($row['op'] === 'db-insert' && $row['table'] === 'wp8y_5_posts')
)) === 0, 'storage mismatch was detected only after destructive cleanup or content writes');

echo "STAGING_IL_PROVISION_HARNESS_OK\n";
