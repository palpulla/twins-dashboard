<?php
declare(strict_types=1);

final class TwinsPrivateStagingDeploy
{
    private const APPLICATION_IDENTITY = 'https://danielj140.sg-host.com/';
    private const ENVIRONMENT = 'staging';
    private const WEB_ROOT = '/home/customer/www/danielj140.sg-host.com/public_html';
    private const TRANSACTION_ROOT = '/home/customer/staging-safety/staging-remediation-r16-20260717';
    private const MAX_FILES = 4096;
    private const MAX_FILE_SIZE = 67108864;
    private const HOST_VERIFICATION_SCENARIOS = [
        'dry-run',
        'existing-core-capture',
        'existing-core-install',
        'prerequisite-drift',
        'expected-old-drift',
        'late-expected-old-drift',
        'second-deploy-conflict',
        'empty-candidate',
        'candidate-not-closed',
        'incoming-copy-failure',
        'incoming-copy-drift',
        'partial-activation-failure',
        'activation-deletion-drift',
        'activation-success-drift',
        'target-set-invalid',
        'core-boot-failure',
        'activation-failure',
        'activation-failure-drift',
        'rollback-existing-core',
        'rollback-drift',
        'non-regular-rejected',
    ];
    private const HOST_REVIEW_SCENARIOS = [
        'valid',
        'bad-record-hash',
        'bad-source-hash',
        'bad-record-count',
        'bad-business-url',
        'bad-source-record-url',
        'impossible-date',
        'stale',
        'short',
        'relative-date',
    ];
    private const HOST_BOOTSTRAP_SCENARIOS = [
        'missingEnvironment',
        'wrongEnvironment',
        'missingSafetyFlag',
        'falseSafetyFlag',
        'missingCronDisable',
        'falseCronDisable',
    ];
    private const HOST_RENDERER_SCENARIOS = [
        'routes',
        'asset-versions',
        'hooks',
        'blog-index',
        'campaign',
        'family-once',
        'path-contact-context',
        'service-brand-chrome',
        'catalog-brand-chrome',
        'home-brand',
        'team-brand',
        'careers-brand',
        'reviews-brand',
        'contact-brand',
        'elementor-theme-content',
        'elementor-document-content',
        'legacy-location-document',
        'ineligible',
        'article',
        'unknown-blog',
    ];

    /** @var string */
    private $root;
    /** @var string */
    private $transaction;
    /** @var string */
    private $manifestPath;
    /** @var callable */
    private $identityProbe;
    /** @var callable */
    private $activationProbe;
    /** @var callable */
    private $deployProbe;

    private function __construct(
        string $root,
        string $transaction,
        string $manifestPath,
        callable $identityProbe,
        callable $activationProbe,
        callable $deployProbe
    )
    {
        $this->root = rtrim($root, '/');
        $this->transaction = rtrim($transaction, '/');
        $this->manifestPath = $manifestPath;
        $this->identityProbe = $identityProbe;
        $this->activationProbe = $activationProbe;
        $this->deployProbe = $deployProbe;
    }

    public static function forProduction(): self
    {
        $identity = static function (): void {
            $loader = self::WEB_ROOT . '/wp-load.php';
            if (!is_file($loader) || is_link($loader)) {
                throw new RuntimeException('WORDPRESS_ROOT_INVALID');
            }
            require_once $loader;
            if (!function_exists('home_url') || home_url('/') !== self::APPLICATION_IDENTITY) {
                throw new RuntimeException('APPLICATION_IDENTITY_INVALID');
            }
            if (!defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== self::ENVIRONMENT) {
                throw new RuntimeException('ENVIRONMENT_INVALID');
            }
        };
        $activation = static function (): void {
            $program = '$root=' . var_export(self::WEB_ROOT, true) . ';'
                . '$_SERVER["HTTP_HOST"]="danielj140.sg-host.com";$_SERVER["REQUEST_URI"]="/";'
                . 'require $root."/wp-load.php";'
                . 'if (!defined("WP_ENVIRONMENT_TYPE") || WP_ENVIRONMENT_TYPE!=="staging" || home_url("/")!=="https://danielj140.sg-host.com/") exit(71);'
                . 'echo "TWINS_STAGE_ACTIVATION_OK";';
            self::runPhp([PHP_BINARY, '-r', $program], 'TWINS_STAGE_ACTIVATION_OK');
        };
        $deployProbe = static function (string $phase, string $target): void {
        };
        return new self(
            self::WEB_ROOT,
            self::TRANSACTION_ROOT,
            self::TRANSACTION_ROOT . '/verification/twins-brand-experience/manifests/staging-runtime.json',
            $identity,
            $activation,
            $deployProbe
        );
    }

    public function execute(string $operation): array
    {
        if (!in_array($operation, ['--dry-run', '--capture-expected-old', '--deploy', '--rollback'], true)) {
            throw new InvalidArgumentException('INVALID_OPERATION');
        }
        call_user_func($this->identityProbe);
        $manifest = $this->readManifest();
        $this->verifyPrerequisites($manifest);
        if ($operation === '--dry-run') {
            if ($this->transaction === self::TRANSACTION_ROOT) {
                $this->verifyHostTooling();
            }
            return $this->result('PRIVATE_STAGING_DRY_RUN_PASSED', $operation, $manifest);
        }
        if ($operation === '--capture-expected-old') {
            $this->captureExpectedOld($manifest);
            return $this->result('EXPECTED_OLD_CAPTURED', $operation, $manifest);
        }
        if ($operation === '--deploy') {
            $this->deploy($manifest);
            return $this->result('PRIVATE_STAGING_DEPLOYED', $operation, $manifest);
        }
        $this->rollback($manifest);
        return $this->result('PRIVATE_STAGING_ROLLED_BACK', $operation, $manifest);
    }

    private function result(string $status, string $operation, array $manifest): array
    {
        return [
            'status' => $status,
            'operation' => $operation,
            'applicationIdentity' => self::APPLICATION_IDENTITY,
            'environment' => self::ENVIRONMENT,
            'manifestSha256' => hash_file('sha256', $this->manifestPath),
            'deployPackageSha256' => $this->canonicalHash($manifest, 'deploy'),
            'prerequisiteSetSha256' => $this->canonicalHash($manifest, 'verify-prerequisite'),
            'writeAuthority' => false,
            'productionWriteAuthority' => false,
        ];
    }

    private function readManifest(): array
    {
        $this->assertRegular($this->manifestPath);
        $raw = file_get_contents($this->manifestPath);
        if ($raw === false || strlen($raw) > self::MAX_FILE_SIZE) {
            throw new RuntimeException('MANIFEST_READ_FAILED');
        }
        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || ($manifest['schemaVersion'] ?? null) !== 1 ||
            ($manifest['productionWriteAuthority'] ?? null) !== false ||
            ($manifest['applicationIdentity'] ?? null) !== self::APPLICATION_IDENTITY ||
            ($manifest['environment'] ?? null) !== self::ENVIRONMENT || !is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('MANIFEST_INVALID');
        }
        if (count($manifest['files']) < 1 || count($manifest['files']) > self::MAX_FILES) {
            throw new RuntimeException('MANIFEST_FILE_COUNT_INVALID');
        }
        $destinations = [];
        foreach ($manifest['files'] as $entry) {
            if (!is_array($entry) || !in_array($entry['role'] ?? null, ['deploy', 'verify-prerequisite'], true) ||
                !$this->safeRelative($entry['destination'] ?? '') ||
                !is_int($entry['size'] ?? null) || $entry['size'] < 0 || $entry['size'] > self::MAX_FILE_SIZE ||
                !is_string($entry['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'])) {
                throw new RuntimeException('MANIFEST_ENTRY_INVALID');
            }
            if (isset($destinations[$entry['destination']])) {
                throw new RuntimeException('MANIFEST_DESTINATION_DUPLICATE');
            }
            $destinations[$entry['destination']] = true;
        }
        return $manifest;
    }

    private function safeRelative(string $value): bool
    {
        if ($value === '' || $value[0] === '/' || strpos($value, '\\') !== false || strpos($value, "\0") !== false) {
            return false;
        }
        foreach (explode('/', $value) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }
        return true;
    }

    private function muRoot(): string
    {
        return $this->root . '/wp-content/mu-plugins';
    }

    private function candidateRoot(): string
    {
        return $this->transaction . '/candidate/wp-content/mu-plugins';
    }

    private function stateRoot(): string
    {
        return $this->transaction . '/state';
    }

    private function assertRegular(string $file): void
    {
        $stat = @lstat($file);
        if ($stat === false || is_link($file) || !is_file($file) || ($stat['size'] ?? -1) > self::MAX_FILE_SIZE) {
            throw new RuntimeException('NON_REGULAR_FILE_REJECTED');
        }
    }

    private function assertEntry(string $file, array $entry): void
    {
        $this->assertRegular($file);
        if (filesize($file) !== $entry['size'] || hash_file('sha256', $file) !== $entry['sha256']) {
            throw new RuntimeException('FILE_HASH_DRIFT');
        }
    }

    private function verifyPrerequisites(array $manifest): void
    {
        foreach ($manifest['files'] as $entry) {
            if ($entry['role'] === 'verify-prerequisite') {
                $this->assertEntry($this->muRoot() . '/' . $entry['destination'], $entry);
            }
        }
    }

    private function deployEntries(array $manifest): array
    {
        $entries = array_values(array_filter($manifest['files'], static function (array $entry): bool {
            return $entry['role'] === 'deploy';
        }));
        if (count($entries) < 1) {
            throw new RuntimeException('CANDIDATE_EMPTY');
        }
        return $entries;
    }

    private function verifyCandidate(array $manifest): void
    {
        $expected = [];
        foreach ($this->deployEntries($manifest) as $entry) {
            $expected[] = $entry['destination'];
            $this->assertEntry($this->candidateRoot() . '/' . $entry['destination'], $entry);
        }
        sort($expected, SORT_STRING);
        $actual = $this->listTree($this->candidateRoot());
        sort($actual, SORT_STRING);
        if ($expected !== $actual) {
            throw new RuntimeException('CANDIDATE_NOT_CLOSED');
        }
    }

    private function targetNames(array $manifest): array
    {
        $names = [];
        foreach ($this->deployEntries($manifest) as $entry) {
            $parts = explode('/', $entry['destination']);
            $names[$parts[0]] = true;
        }
        $names = array_keys($names);
        sort($names, SORT_STRING);
        if ($names !== ['twins-brand-experience', 'twins-staging-overhaul']) {
            throw new RuntimeException('DEPLOY_TARGET_SET_INVALID');
        }
        return $names;
    }

    private function captureExpectedOld(array $manifest): void
    {
        $targets = $this->targetNames($manifest);
        $state = $this->stateRoot();
        if (file_exists($state) || is_link($state)) {
            throw new RuntimeException('EXPECTED_OLD_ALREADY_CAPTURED');
        }
        if (!mkdir($state . '/archive', 0700, true)) {
            throw new RuntimeException('STATE_CREATE_FAILED');
        }
        $snapshot = [
            'schemaVersion' => 1,
            'manifestSha256' => hash_file('sha256', $this->manifestPath),
            'targets' => [],
            'writeAuthority' => false,
            'productionWriteAuthority' => false,
        ];
        foreach ($targets as $name) {
            $source = $this->muRoot() . '/' . $name;
            $present = file_exists($source) || is_link($source);
            if ($present) {
                if (!is_dir($source) || is_link($source)) {
                    throw new RuntimeException('TARGET_NOT_DIRECTORY');
                }
                $this->copyTree($source, $state . '/archive/' . $name, 0600);
            }
            $snapshot['targets'][$name] = [
                'present' => $present,
                'files' => $present ? $this->treeRecords($source) : [],
            ];
        }
        $bytes = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($bytes) || file_put_contents($state . '/expected-old.json', $bytes . "\n", LOCK_EX) === false) {
            throw new RuntimeException('EXPECTED_OLD_WRITE_FAILED');
        }
        chmod($state . '/expected-old.json', 0600);
    }

    private function readExpectedOld(array $manifest): array
    {
        $file = $this->stateRoot() . '/expected-old.json';
        $this->assertRegular($file);
        $snapshot = json_decode((string) file_get_contents($file), true);
        if (!is_array($snapshot) || ($snapshot['schemaVersion'] ?? null) !== 1 ||
            ($snapshot['manifestSha256'] ?? null) !== hash_file('sha256', $this->manifestPath) ||
            ($snapshot['writeAuthority'] ?? null) !== false || ($snapshot['productionWriteAuthority'] ?? null) !== false) {
            throw new RuntimeException('EXPECTED_OLD_INVALID');
        }
        $targets = $this->targetNames($manifest);
        $snapshotTargets = array_keys($snapshot['targets'] ?? []);
        sort($snapshotTargets, SORT_STRING);
        if ($snapshotTargets !== $targets) {
            throw new RuntimeException('EXPECTED_OLD_TARGET_SET_INVALID');
        }
        foreach ($targets as $name) {
            if (!isset($snapshot['targets'][$name]) || !is_bool($snapshot['targets'][$name]['present'] ?? null) ||
                !is_array($snapshot['targets'][$name]['files'] ?? null)) {
                throw new RuntimeException('EXPECTED_OLD_TARGET_INVALID');
            }
            if ($snapshot['targets'][$name]['present']) {
                $archive = $this->stateRoot() . '/archive/' . $name;
                if ($this->treeRecords($archive) !== $snapshot['targets'][$name]['files']) {
                    throw new RuntimeException('ROLLBACK_ARCHIVE_DRIFT');
                }
            }
        }
        return $snapshot;
    }

    private function assertExpectedOldCurrent(array $snapshot): void
    {
        foreach ($snapshot['targets'] as $name => $record) {
            $target = $this->muRoot() . '/' . $name;
            $present = file_exists($target) || is_link($target);
            if ($present !== $record['present']) {
                throw new RuntimeException('EXPECTED_OLD_CONFLICT');
            }
            if ($present && (!is_dir($target) || is_link($target) || $this->treeRecords($target) !== $record['files'])) {
                throw new RuntimeException('EXPECTED_OLD_CONFLICT');
            }
        }
    }

    private function deploy(array $manifest): void
    {
        $snapshot = $this->readExpectedOld($manifest);
        $this->assertExpectedOldCurrent($snapshot);
        $this->verifyCandidate($manifest);
        $this->verifyCandidatePhp();
        $this->recordDeployAttempt($manifest);
        $incoming = [];
        $backups = [];
        $targets = $this->targetNames($manifest);
        $activationProgress = array_fill_keys($targets, 'unprocessed');
        $mutationStarted = false;
        try {
            foreach ($targets as $name) {
                $path = $this->muRoot() . '/.' . $name . '.incoming';
                if (file_exists($path) || is_link($path)) {
                    throw new RuntimeException('INCOMING_TARGET_EXISTS');
                }
                $incoming[$name] = $path;
                $backup = $this->muRoot() . '/.' . $name . '.expected-old-backup';
                if (file_exists($backup) || is_link($backup)) {
                    throw new RuntimeException('ACTIVATION_BACKUP_EXISTS');
                }
                $backups[$name] = $backup;
            }
            foreach ($targets as $name) {
                call_user_func($this->deployProbe, 'before-copy', $name);
                $this->copyTree($this->candidateRoot() . '/' . $name, $incoming[$name], 0644);
                call_user_func($this->deployProbe, 'after-copy', $name);
                if ($this->treeRecords($incoming[$name]) !==
                    $this->treeRecords($this->candidateRoot() . '/' . $name)) {
                    throw new RuntimeException('INCOMING_COPY_DRIFT');
                }
            }
            call_user_func($this->deployProbe, 'before-second-cas', '');
            $this->assertExpectedOldCurrent($snapshot);
            $mutationStarted = true;
            foreach ($targets as $name) {
                $target = $this->muRoot() . '/' . $name;
                if (file_exists($target) || is_link($target)) {
                    if (!rename($target, $backups[$name])) {
                        throw new RuntimeException('ACTIVATION_BACKUP_FAILED');
                    }
                }
                $activationProgress[$name] = 'backed-up';
                call_user_func($this->deployProbe, 'after-backup', $name);
                if (!rename($incoming[$name], $target)) {
                    throw new RuntimeException('ACTIVATION_RENAME_FAILED');
                }
                $activationProgress[$name] = 'activated';
                call_user_func($this->deployProbe, 'after-activate', $name);
            }
            call_user_func($this->activationProbe);
            $this->assertCurrentMatchesCandidate($manifest);
        } catch (Throwable $error) {
            if ($mutationStarted) {
                try {
                    $this->assertActivationEnvelope($manifest, $snapshot, $backups, $activationProgress);
                    $this->restoreSnapshot($snapshot);
                    $this->cleanupBackups($backups);
                    $this->assertExpectedOldCurrent($snapshot);
                } catch (Throwable $compensationError) {
                    $this->cleanupIncoming($incoming);
                    throw $compensationError;
                }
            }
            $this->cleanupIncoming($incoming);
            throw $error;
        }
        $this->cleanupBackups($backups);
    }

    private function cleanupIncoming(array $incoming): void
    {
        foreach ($incoming as $path) {
            if (file_exists($path) || is_link($path)) {
                $this->removeTree($path);
            }
        }
    }

    private function cleanupBackups(array $backups): void
    {
        foreach ($backups as $path) {
            if (file_exists($path) || is_link($path)) {
                $this->removeTree($path);
            }
        }
    }

    private function rollback(array $manifest): void
    {
        $snapshot = $this->readExpectedOld($manifest);
        $this->verifyCandidate($manifest);
        $this->assertCurrentMatchesCandidate($manifest);
        $this->restoreSnapshot($snapshot);
    }

    private function assertCurrentMatchesCandidate(array $manifest): void
    {
        foreach ($this->targetNames($manifest) as $name) {
            $target = $this->muRoot() . '/' . $name;
            if (!is_dir($target) || is_link($target)) {
                throw new RuntimeException('ROLLBACK_CONFLICT');
            }
            try {
                $matches = $this->treeRecords($target) === $this->candidateTargetRecords($manifest, $name);
            } catch (Throwable $error) {
                throw new RuntimeException('ROLLBACK_CONFLICT');
            }
            if (!$matches) {
                throw new RuntimeException('ROLLBACK_CONFLICT');
            }
        }
    }

    private function assertActivationEnvelope(
        array $manifest,
        array $snapshot,
        array $backups,
        array $activationProgress
    ): void
    {
        foreach ($this->targetNames($manifest) as $name) {
            $record = $snapshot['targets'][$name];
            $target = $this->muRoot() . '/' . $name;
            $backup = $backups[$name] ?? '';
            $targetPresent = file_exists($target) || is_link($target);
            $backupPresent = $backup !== '' && (file_exists($backup) || is_link($backup));
            $phase = $activationProgress[$name] ?? '';
            if (!in_array($phase, ['unprocessed', 'backed-up', 'activated'], true)) {
                throw new RuntimeException('ROLLBACK_CONFLICT');
            }
            if ($phase === 'unprocessed') {
                if ($backupPresent || $targetPresent !== $record['present']) {
                    throw new RuntimeException('ROLLBACK_CONFLICT');
                }
                if ($targetPresent) {
                    try {
                        if (!is_dir($target) || is_link($target) ||
                            $this->treeRecords($target) !== $record['files']) {
                            throw new RuntimeException('ROLLBACK_CONFLICT');
                        }
                    } catch (Throwable $error) {
                        throw new RuntimeException('ROLLBACK_CONFLICT');
                    }
                }
                continue;
            }
            if ($backupPresent !== $record['present']) {
                throw new RuntimeException('ROLLBACK_CONFLICT');
            }
            if ($backupPresent) {
                try {
                    if (!is_dir($backup) || is_link($backup) ||
                        $this->treeRecords($backup) !== $record['files']) {
                        throw new RuntimeException('ROLLBACK_CONFLICT');
                    }
                } catch (Throwable $error) {
                    throw new RuntimeException('ROLLBACK_CONFLICT');
                }
            }
            if ($phase === 'backed-up') {
                if ($targetPresent) {
                    throw new RuntimeException('ROLLBACK_CONFLICT');
                }
                continue;
            }
            if (!$targetPresent) {
                throw new RuntimeException('ROLLBACK_CONFLICT');
            }
            try {
                if (!is_dir($target) || is_link($target) ||
                    $this->treeRecords($target) !== $this->candidateTargetRecords($manifest, $name)) {
                    throw new RuntimeException('ROLLBACK_CONFLICT');
                }
            } catch (Throwable $error) {
                throw new RuntimeException('ROLLBACK_CONFLICT');
            }
        }
    }

    private function candidateTargetRecords(array $manifest, string $name): array
    {
        $records = [];
        $prefix = $name . '/';
        foreach ($this->deployEntries($manifest) as $entry) {
            if (strpos($entry['destination'], $prefix) === 0) {
                $relative = substr($entry['destination'], strlen($prefix));
                if ($relative === '') {
                    throw new RuntimeException('MANIFEST_ENTRY_INVALID');
                }
                $records[$relative] = ['size' => $entry['size'], 'sha256' => $entry['sha256']];
            }
        }
        if ($records === []) {
            throw new RuntimeException('DEPLOY_TARGET_SET_INVALID');
        }
        ksort($records, SORT_STRING);
        return $records;
    }

    private function recordDeployAttempt(array $manifest): void
    {
        $file = $this->stateRoot() . '/deploy-attempt.json';
        if (file_exists($file) || is_link($file)) {
            throw new RuntimeException('DEPLOY_ATTEMPT_ALREADY_RECORDED');
        }
        $bytes = json_encode([
            'schemaVersion' => 1,
            'manifestSha256' => hash_file('sha256', $this->manifestPath),
            'deployPackageSha256' => $this->canonicalHash($manifest, 'deploy'),
            'writeAuthority' => false,
            'productionWriteAuthority' => false,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($bytes)) {
            throw new RuntimeException('DEPLOY_ATTEMPT_RECORD_FAILED');
        }
        $handle = @fopen($file, 'x');
        if (!is_resource($handle)) {
            if (file_exists($file) || is_link($file)) {
                throw new RuntimeException('DEPLOY_ATTEMPT_ALREADY_RECORDED');
            }
            throw new RuntimeException('DEPLOY_ATTEMPT_RECORD_FAILED');
        }
        $written = fwrite($handle, $bytes . "\n");
        $closed = fclose($handle);
        if ($written !== strlen($bytes) + 1 || !$closed || !chmod($file, 0600)) {
            throw new RuntimeException('DEPLOY_ATTEMPT_RECORD_FAILED');
        }
    }

    private function restoreSnapshot(array $snapshot): void
    {
        foreach ($snapshot['targets'] as $name => $record) {
            $target = $this->muRoot() . '/' . $name;
            if (file_exists($target) || is_link($target)) {
                $this->removeTree($target);
            }
            if ($record['present']) {
                $this->copyTree($this->stateRoot() . '/archive/' . $name, $target, 0644);
                if ($this->treeRecords($target) !== $record['files']) {
                    throw new RuntimeException('ROLLBACK_VERIFY_FAILED');
                }
            }
        }
    }

    private function verifyCandidatePhp(): void
    {
        foreach ($this->listTree($this->candidateRoot()) as $relative) {
            if (substr($relative, -4) === '.php') {
                self::runPhp([PHP_BINARY, '-l', $this->candidateRoot() . '/' . $relative], 'No syntax errors detected');
            }
        }
        $bootstrap = $this->candidateRoot() . '/twins-brand-experience/bootstrap.php';
        $program = 'require ' . var_export($bootstrap, true) . '; echo "TWINS_CORE_BOOT_OK";';
        self::runPhp([PHP_BINARY, '-r', $program], 'TWINS_CORE_BOOT_OK');
    }

    private function verifyHostTooling(): void
    {
        $verificationRoot = $this->transaction . '/verification';
        $brandRoot = $verificationRoot . '/twins-brand-experience';
        $stagingRoot = $verificationRoot . '/staging-safety';
        $loader = $stagingRoot . '/mu-plugins/twins-staging-overhaul.php';
        $safety = $stagingRoot . '/mu-plugins/twins-staging-safety.php';
        $package = $stagingRoot . '/mu-plugins/twins-staging-overhaul';
        $tool = $brandRoot . '/tools/private-staging-deploy.php';
        $harness = $brandRoot . '/tests/php/private-staging-deploy-harness.php';
        foreach ($this->listTree($verificationRoot) as $relative) {
            if (substr($relative, -4) === '.php') {
                self::runPhp([PHP_BINARY, '-l', $verificationRoot . '/' . $relative], 'No syntax errors detected');
            }
        }
        self::runPhpExact(
            [PHP_BINARY, $brandRoot . '/tests/php/portable-core-harness.php', $brandRoot . '/bootstrap.php'],
            'portable-core-ok'
        );
        self::runPhpExact(
            [PHP_BINARY, $brandRoot . '/tests/php/renderer-contract-harness.php', $brandRoot . '/bootstrap.php'],
            'renderer-contracts-ok'
        );
        foreach (self::HOST_REVIEW_SCENARIOS as $scenario) {
            self::runPhpExact([
                PHP_BINARY,
                $brandRoot . '/tests/php/review-codec-harness.php',
                $brandRoot . '/bootstrap.php',
                $brandRoot . '/tests/fixtures/reviews/' . $scenario . '.json',
                $scenario,
                '2026-07-15T00:00:00Z',
            ], $scenario === 'valid' ? 'review-codec-ok' : 'review-codec-rejected');
        }
        foreach (self::HOST_VERIFICATION_SCENARIOS as $scenario) {
            self::runPhpExact([PHP_BINARY, $harness, $tool, $scenario], $scenario . '-ok');
        }
        self::runPhpExact(
            [PHP_BINARY, $stagingRoot . '/tests/staging-overhaul-foundation-harness.php', $loader],
            'STAGING_OVERHAUL_FOUNDATION_HARNESS_OK'
        );
        self::runPhpExact(
            [PHP_BINARY, $stagingRoot . '/tests/staging-overhaul-harness.php', $loader],
            'STAGING_OVERHAUL_HARNESS_OK'
        );
        self::runPhpExact([
            PHP_BINARY,
            $stagingRoot . '/tests/staging-overhaul-builder-harness.php',
            $package,
            $brandRoot,
        ], 'STAGING_OVERHAUL_BUILDER_HARNESS_OK');
        self::runPhpExact(
            [PHP_BINARY, $stagingRoot . '/tests/staging-overhaul-cost-harness.php', $package],
            'STAGING_OVERHAUL_COST_HARNESS_OK'
        );
        foreach (self::HOST_BOOTSTRAP_SCENARIOS as $scenario) {
            self::verifyBootstrapReport(self::runPhpJson([
                PHP_BINARY,
                $stagingRoot . '/tests/staging-overhaul-bootstrap-harness.php',
                $loader,
                $scenario,
            ]), $scenario);
        }
        foreach (self::HOST_RENDERER_SCENARIOS as $scenario) {
            self::runPhpExact([
                PHP_BINARY,
                $stagingRoot . '/tests/staging-overhaul-renderers-harness.php',
                $loader,
                $scenario,
            ], 'STAGING_OVERHAUL_RENDERERS_HARNESS_OK:' . $scenario);
        }
        self::runPhpExact([
            PHP_BINARY,
            $stagingRoot . '/tests/staging-overhaul-brand-asset-harness.php',
            $package . '/renderers.php',
        ], 'STAGING_OVERHAUL_BRAND_ASSET_HARNESS_OK');
        self::runPhpExact([
            PHP_BINARY,
            $stagingRoot . '/tests/staging-brand-adapters-harness.php',
            $loader,
            $brandRoot,
        ], 'STAGING_BRAND_ADAPTERS_HARNESS_OK');
        self::runPhpExact(
            [PHP_BINARY, $stagingRoot . '/tests/staging-legacy-image-srcset-harness.php', $safety],
            'STAGING_LEGACY_IMAGE_SRCSET_HARNESS_OK'
        );
        self::runPhpExact([
            PHP_BINARY,
            $stagingRoot . '/tests/staging-il-provision-harness.php',
            $stagingRoot . '/tools/staging-il-provision.php',
        ], 'STAGING_IL_PROVISION_HARNESS_OK');
        self::runPhpExact([
            PHP_BINARY,
            $stagingRoot . '/tests/staging-chrome-transition-harness.php',
            $stagingRoot . '/tools/staging-chrome-transition.php',
        ], 'STAGING_CHROME_TRANSITION_HARNESS_OK');
        self::verifyWordPressReport(self::runPhpJson([
            PHP_BINARY,
            $stagingRoot . '/tests/wordpress-harness.php',
            $safety,
        ]));
    }

    private static function runPhp(array $command, string $expected): void
    {
        $stdout = self::runPhpProcess($command);
        if (strpos($stdout, $expected) === false) {
            throw new RuntimeException('PHP_BOOT_VERIFICATION_FAILED');
        }
    }

    private static function runPhpExact(array $command, string $expected): void
    {
        $stdout = self::runPhpProcess($command);
        if (trim($stdout) !== $expected) {
            throw new RuntimeException('PHP_BOOT_VERIFICATION_FAILED');
        }
    }

    private static function runPhpJson(array $command): array
    {
        $stdout = self::runPhpProcess($command);
        $report = json_decode(trim($stdout), true);
        if (!is_array($report) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('PHP_BOOT_VERIFICATION_FAILED');
        }
        return $report;
    }

    private static function runPhpProcess(array $command): string
    {
        $pipes = [];
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('PHP_SUBPROCESS_UNAVAILABLE');
        }
        $stdout = stream_get_contents($pipes[1], self::MAX_FILE_SIZE + 1);
        $stderr = stream_get_contents($pipes[2], self::MAX_FILE_SIZE + 1);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if (!is_string($stdout) || !is_string($stderr) ||
            strlen($stdout) > self::MAX_FILE_SIZE || strlen($stderr) > self::MAX_FILE_SIZE ||
            $status !== 0 || trim($stderr) !== '') {
            throw new RuntimeException('PHP_BOOT_VERIFICATION_FAILED');
        }
        return $stdout;
    }

    private static function verifyBootstrapReport(array $report, string $scenario): void
    {
        if ($report !== [
            'scenario' => $scenario,
            'status' => 'refused',
            'response' => 503,
            'implementationLoads' => 0,
            'hookRegistrations' => 0,
        ]) {
            throw new RuntimeException('PHP_BOOT_VERIFICATION_FAILED');
        }
    }

    private static function verifyWordPressReport(array $report): void
    {
        $expectedBoot = [
            'missingEnvironment' => 'refused',
            'wrongEnvironment' => 'refused',
            'missingSafetyFlag' => 'refused',
            'falseSafetyFlag' => 'refused',
            'missingCronDisable' => 'refused',
            'preexistingOrdinary' => 'refused',
            'preexistingNetwork' => 'refused',
            'preexistingBrainstrom' => 'refused',
            'configuredStaging' => 'booted',
        ];
        $expectedHttp = [
            'sameOriginGet' => 'twins_staging_http_blocked',
            'sameOriginPost' => 'twins_staging_http_blocked',
            'clopayGet' => 'twins_staging_http_blocked',
            'clopayHead' => 'twins_staging_http_blocked',
            'arbitraryExternal' => 'twins_staging_http_blocked',
        ];
        $expectedCsp = "default-src 'self'; base-uri 'self'; object-src 'none'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            . "style-src 'self' 'unsafe-inline'; img-src 'self' data: https://www.clopaydoor.com; font-src 'self'; "
            . "connect-src 'self'; media-src 'self'; frame-src 'self'; child-src 'self'; worker-src 'self'; "
            . "manifest-src 'self'; form-action 'self'; frame-ancestors 'self'; navigate-to 'self';";
        $reviewPlaceholder = $report['reviewPlaceholder'] ?? null;
        $integrationPlaceholder = $report['integrationPlaceholder'] ?? null;
        $shortcodeFailSafe = $report['shortcodeFailSafe'] ?? null;
        $brainstrom = $report['brainstrom'] ?? null;
        if (($report['boot'] ?? null) !== $expectedBoot ||
            ($report['mailShortCircuit'] ?? null) !== true ||
            ($report['http'] ?? null) !== $expectedHttp ||
            ($report['csp'] ?? null) !== $expectedCsp ||
            !is_string($reviewPlaceholder) ||
            strpos($reviewPlaceholder, 'Reviews are intentionally disabled on this private staging copy') === false ||
            preg_match('/https?:|<script|<iframe|<form|src=|href=|attacker|onload/i', $reviewPlaceholder) ||
            !is_string($integrationPlaceholder) ||
            strpos($integrationPlaceholder, 'Interactive product and door-builder integrations are intentionally disabled on this private staging copy') === false ||
            preg_match('/https?:|<script|<iframe|<form|src=|href=|attacker|onload/i', $integrationPlaceholder) ||
            ($report['quarantinedOptionUpdate'] ?? null) !== false ||
            ($report['quarantinedOptionAdded'] ?? null) !== ['elementor_connect_site_key'] ||
            ($report['quarantinedNetworkOptionAdded'] ?? null) !== [[7, 'elementor_connect_site_key']] ||
            ($report['quarantinedNetworkPreAdd'] ?? null) !== false ||
            ($report['ordinaryAddGuard'] ?? null) !== [
                'quarantinedSecret' => 'refused',
                'quarantinedEmpty' => 'allowed',
                'brainstromSecret' => 'refused',
                'brainstromSafe' => 'allowed',
                'ordinary' => 'allowed',
            ] ||
            ($report['legacyRedirects'] ?? null) !== [
                'madisonPage' => '/wi/garage-door-opener-in-madison-wi/',
                'madisonException' => '/wi/garage-door-services/',
                'wiMenu' => '/wi/location/madison/',
                'kyPagination' => '/ky/category/madison/',
                'ordinaryMissing' => null,
                'unsafeRelative' => null,
                'unsafeTraversal' => null,
            ] ||
            ($report['lateShortcodes'] ?? null) !== [
                'brb_collection' => 'twins_staging_safety_review_placeholder',
                'clopay_product' => 'twins_staging_safety_disabled_integration_placeholder',
                'clopay_collection_grid' => 'twins_staging_safety_disabled_integration_placeholder',
                'twins_door_builder' => 'twins_staging_safety_disabled_integration_placeholder',
            ] ||
            !is_array($shortcodeFailSafe) ||
            !is_string($shortcodeFailSafe['reviews'] ?? null) ||
            strpos($shortcodeFailSafe['reviews'], 'Reviews are intentionally disabled') === false ||
            !is_string($shortcodeFailSafe['clopay'] ?? null) ||
            strpos($shortcodeFailSafe['clopay'], 'Interactive product and door-builder integrations are intentionally disabled') === false ||
            preg_match('/attacker/i', $shortcodeFailSafe['reviews'] . $shortcodeFailSafe['clopay']) ||
            ($shortcodeFailSafe['ordinary'] ?? null) !== 'unchanged' ||
            !is_array($brainstrom) ||
            ($brainstrom['safeUpdate'] ?? null) !== ['astra-addon' => ['version' => '4.0.0', 'enabled' => true]] ||
            ($brainstrom['safeAdd'] ?? null) !== ['astra-addon' => ['version' => '4.0.0', 'enabled' => true]] ||
            ($brainstrom['licenseUpdate'] ?? null) !== ['astra-addon' => ['version' => '3.9.0']] ||
            ($brainstrom['purchaseAdd'] ?? null) !== [] ||
            ($brainstrom['tokenAdd'] ?? null) !== [] ||
            ($brainstrom['keyAdd'] ?? null) !== []) {
            throw new RuntimeException('PHP_BOOT_VERIFICATION_FAILED');
        }
    }

    private function listTree(string $base): array
    {
        if (!is_dir($base) || is_link($base)) {
            throw new RuntimeException('TREE_ROOT_INVALID');
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isLink() || !$item->isFile()) {
                throw new RuntimeException('NON_REGULAR_FILE_REJECTED');
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($base) + 1));
            if (!$this->safeRelative($relative)) {
                throw new RuntimeException('TREE_PATH_INVALID');
            }
            $files[] = $relative;
            if (count($files) > self::MAX_FILES) {
                throw new RuntimeException('TREE_FILE_COUNT_INVALID');
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function treeRecords(string $base): array
    {
        $records = [];
        foreach ($this->listTree($base) as $relative) {
            $file = $base . '/' . $relative;
            $this->assertRegular($file);
            $records[$relative] = ['size' => filesize($file), 'sha256' => hash_file('sha256', $file)];
        }
        return $records;
    }

    private function copyTree(string $source, string $destination, int $fileMode): void
    {
        if (!is_dir($source) || is_link($source)) {
            throw new RuntimeException('COPY_SOURCE_INVALID');
        }
        if (!mkdir($destination, 0700, true) && !is_dir($destination)) {
            throw new RuntimeException('COPY_DESTINATION_FAILED');
        }
        foreach ($this->listTree($source) as $relative) {
            $from = $source . '/' . $relative;
            $to = $destination . '/' . $relative;
            $parent = dirname($to);
            if (!is_dir($parent) && !mkdir($parent, 0700, true)) {
                throw new RuntimeException('COPY_PARENT_FAILED');
            }
            if (!copy($from, $to)) {
                throw new RuntimeException('COPY_FAILED');
            }
            chmod($to, $fileMode);
        }
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (!unlink($path)) {
                throw new RuntimeException('REMOVE_FAILED');
            }
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            throw new RuntimeException('REMOVE_SCAN_FAILED');
        }
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..') {
                $this->removeTree($path . '/' . $item);
            }
        }
        if (!rmdir($path)) {
            throw new RuntimeException('REMOVE_DIRECTORY_FAILED');
        }
    }

    private function canonicalHash(array $manifest, string $role): string
    {
        $entries = array_values(array_filter($manifest['files'], static function (array $entry) use ($role): bool {
            return $entry['role'] === $role;
        }));
        usort($entries, static function (array $a, array $b): int {
            return strcmp($a['destination'], $b['destination']);
        });
        $context = hash_init('sha256');
        foreach ($entries as $entry) {
            foreach ([$entry['destination'], (string) $entry['size'], $entry['sha256']] as $field) {
                hash_update($context, strlen($field) . ':' . $field);
            }
        }
        return hash_final($context);
    }
}

if (defined('TWINS_PRIVATE_STAGING_DEPLOY_TESTING') && TWINS_PRIVATE_STAGING_DEPLOY_TESTING) {
    final class TwinsPrivateStagingDeployHarness
    {
        public static function fixture(): array
        {
            $base = sys_get_temp_dir() . '/twins-private-stage-' . bin2hex(random_bytes(8));
            $root = $base . '/root';
            $transaction = $base . '/transaction';
            $mu = $root . '/wp-content/mu-plugins';
            self::write($mu . '/twins-brand-experience/bootstrap.php', "<?php\n// expected old core\n");
            self::write($mu . '/twins-brand-experience/nested/legacy.txt', "expected old nested bytes\n");
            self::write($mu . '/twins-staging-overhaul/old.php', "<?php\n// expected old\n");
            self::write($mu . '/twins-staging-overhaul.php', "<?php\n// loader\n");
            self::write($mu . '/twins-staging-safety.php', "<?php\n// safety\n");
            self::write($mu . '/twins-staging-assets/a.txt', "asset\n");
            $candidate = $transaction . '/candidate/wp-content/mu-plugins';
            self::write($candidate . '/twins-brand-experience/bootstrap.php', "<?php\ndeclare(strict_types=1);\n// candidate core\n");
            self::write($candidate . '/twins-staging-overhaul/old.php', "<?php\n// replacement\n");
            $manifestPath = $transaction . '/verification/twins-brand-experience/manifests/staging-runtime.json';
            $files = [
                self::entry('deploy', 'twins-brand-experience/bootstrap.php', $candidate . '/twins-brand-experience/bootstrap.php'),
                self::entry('deploy', 'twins-staging-overhaul/old.php', $candidate . '/twins-staging-overhaul/old.php'),
                self::entry('verify-prerequisite', 'twins-staging-assets/a.txt', $mu . '/twins-staging-assets/a.txt'),
                self::entry('verify-prerequisite', 'twins-staging-overhaul.php', $mu . '/twins-staging-overhaul.php'),
                self::entry('verify-prerequisite', 'twins-staging-safety.php', $mu . '/twins-staging-safety.php'),
            ];
            usort($files, static function (array $a, array $b): int { return strcmp($a['destination'], $b['destination']); });
            self::writeManifest($manifestPath, $files);
            return ['base' => $base, 'root' => $root, 'transaction' => $transaction, 'manifest' => $manifestPath];
        }

        public static function run(string $scenario, array $fixture): array
        {
            $activationFailure = in_array(
                $scenario,
                ['activation-failure', 'activation-failure-drift', 'activation-deletion-drift'],
                true
            );
            $deploy = self::instance($fixture, $activationFailure, $scenario);
            if ($scenario === 'dry-run') {
                return $deploy->execute('--dry-run');
            }
            if ($scenario === 'prerequisite-drift') {
                file_put_contents($fixture['root'] . '/wp-content/mu-plugins/twins-staging-assets/a.txt', 'drift');
                return self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--dry-run'); },
                    'PREREQUISITE_DRIFT_REJECTED',
                    'FILE_HASH_DRIFT'
                );
            }
            if ($scenario === 'empty-candidate') {
                self::filterDeployEntries($fixture, static function (array $entry): bool { return false; });
                $deploy = self::instance($fixture, false);
                return self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--capture-expected-old'); },
                    'EMPTY_CANDIDATE_REJECTED',
                    'CANDIDATE_EMPTY'
                );
            }
            if ($scenario === 'target-set-invalid') {
                self::filterDeployEntries($fixture, static function (array $entry): bool {
                    return strpos($entry['destination'], 'twins-staging-overhaul/') !== 0;
                });
                $deploy = self::instance($fixture, false);
                return self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--capture-expected-old'); },
                    'TARGET_SET_REJECTED',
                    'DEPLOY_TARGET_SET_INVALID'
                );
            }
            if ($scenario === 'non-regular-rejected') {
                $asset = $fixture['root'] . '/wp-content/mu-plugins/twins-staging-assets/a.txt';
                unlink($asset);
                symlink('../twins-staging-safety.php', $asset);
                return self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--dry-run'); },
                    'NON_REGULAR_REJECTED',
                    'NON_REGULAR_FILE_REJECTED'
                );
            }
            if ($scenario === 'existing-core-capture') {
                $mu = $fixture['root'] . '/wp-content/mu-plugins';
                $expectedCore = self::records($mu . '/twins-brand-experience');
                $expectedOverhaul = self::records($mu . '/twins-staging-overhaul');
                $result = $deploy->execute('--capture-expected-old');
                $snapshot = json_decode((string) file_get_contents($fixture['transaction'] . '/state/expected-old.json'), true);
                if (!is_array($snapshot) ||
                    ($snapshot['targets']['twins-brand-experience']['present'] ?? null) !== true ||
                    ($snapshot['targets']['twins-staging-overhaul']['present'] ?? null) !== true ||
                    ($snapshot['targets']['twins-brand-experience']['files'] ?? null) !== $expectedCore ||
                    ($snapshot['targets']['twins-staging-overhaul']['files'] ?? null) !== $expectedOverhaul ||
                    self::records($fixture['transaction'] . '/state/archive/twins-brand-experience') !== $expectedCore ||
                    self::records($fixture['transaction'] . '/state/archive/twins-staging-overhaul') !== $expectedOverhaul ||
                    file_get_contents($fixture['transaction'] . '/state/archive/twins-brand-experience/bootstrap.php') !==
                        file_get_contents($mu . '/twins-brand-experience/bootstrap.php') ||
                    file_get_contents($fixture['transaction'] . '/state/archive/twins-brand-experience/nested/legacy.txt') !==
                        file_get_contents($mu . '/twins-brand-experience/nested/legacy.txt')) {
                    throw new RuntimeException('Existing fixed targets were not captured exactly.');
                }
                return $result;
            }
            if ($scenario === 'expected-old-drift') {
                $deploy->execute('--capture-expected-old');
                file_put_contents($fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience/bootstrap.php', "<?php\n// external drift\n");
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'EXPECTED_OLD_DRIFT_REJECTED',
                    'EXPECTED_OLD_CONFLICT'
                );
                if (strpos((string) file_get_contents($fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience/bootstrap.php'), 'external drift') === false) {
                    throw new RuntimeException('Expected-old conflict changed the current target.');
                }
                return $result;
            }
            if ($scenario === 'late-expected-old-drift') {
                $deploy->execute('--capture-expected-old');
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'LATE_EXPECTED_OLD_DRIFT_REJECTED',
                    'EXPECTED_OLD_CONFLICT'
                );
                $current = $fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience/bootstrap.php';
                if (file_get_contents($current) !== "<?php\n// late external drift\n") {
                    throw new RuntimeException('Late drift was overwritten.');
                }
                self::assertNoIncoming($fixture);
                self::assertDeployAttemptRecorded($fixture);
                return $result;
            }
            if ($scenario === 'candidate-not-closed') {
                self::write($fixture['transaction'] . '/candidate/wp-content/mu-plugins/twins-brand-experience/unexpected.txt', "unexpected\n");
                $deploy->execute('--capture-expected-old');
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'OPEN_CANDIDATE_REJECTED',
                    'CANDIDATE_NOT_CLOSED'
                );
                self::assertOldTargets($fixture);
                return $result;
            }
            if ($scenario === 'incoming-copy-failure') {
                $deploy->execute('--capture-expected-old');
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'INCOMING_COPY_FAILURE_CLEANED',
                    'Fixture incoming copy failure.'
                );
                self::assertOldTargets($fixture);
                self::assertNoIncoming($fixture);
                self::assertDeployAttemptRecorded($fixture);
                return $result;
            }
            if ($scenario === 'incoming-copy-drift') {
                $deploy->execute('--capture-expected-old');
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'INCOMING_COPY_DRIFT_REJECTED',
                    'INCOMING_COPY_DRIFT'
                );
                self::assertOldTargets($fixture);
                self::assertNoIncoming($fixture);
                self::assertDeployAttemptRecorded($fixture);
                return $result;
            }
            if ($scenario === 'core-boot-failure') {
                $bootstrap = $fixture['transaction'] . '/candidate/wp-content/mu-plugins/twins-brand-experience/bootstrap.php';
                file_put_contents($bootstrap, "<?php this is invalid !!!\n");
                self::refreshDeployHash($fixture, 'twins-brand-experience/bootstrap.php', $bootstrap);
                $deploy = self::instance($fixture, false);
                $deploy->execute('--capture-expected-old');
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'CORE_BOOT_FAILURE_REJECTED',
                    'PHP_BOOT_VERIFICATION_FAILED'
                );
                self::assertOldTargets($fixture);
                return $result;
            }
            $deploy->execute('--capture-expected-old');
            if ($scenario === 'activation-success-drift') {
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'ACTIVATION_SUCCESS_DRIFT_REJECTED',
                    'ROLLBACK_CONFLICT'
                );
                $mu = $fixture['root'] . '/wp-content/mu-plugins';
                if (file_get_contents($mu . '/twins-brand-experience/bootstrap.php') !== "<?php\n// activation returned with successor drift\n" ||
                    file_get_contents($mu . '/twins-staging-overhaul/old.php') !== "<?php\n// replacement\n") {
                    throw new RuntimeException('Successful activation drift was masked.');
                }
                self::assertDeployAttemptRecorded($fixture);
                return $result;
            }
            if ($scenario === 'activation-deletion-drift') {
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'ACTIVATION_DELETION_DRIFT_REJECTED',
                    'ROLLBACK_CONFLICT'
                );
                $mu = $fixture['root'] . '/wp-content/mu-plugins';
                if (file_exists($mu . '/twins-brand-experience') ||
                    file_get_contents($mu . '/twins-staging-overhaul/old.php') !== "<?php\n// replacement\n") {
                    throw new RuntimeException('Activation deletion drift was masked.');
                }
                self::assertDeployAttemptRecorded($fixture);
                return $result;
            }
            if ($scenario === 'activation-failure-drift') {
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'ACTIVATION_DRIFT_ROLLBACK_REJECTED',
                    'ROLLBACK_CONFLICT'
                );
                $mu = $fixture['root'] . '/wp-content/mu-plugins';
                if (file_get_contents($mu . '/twins-brand-experience/bootstrap.php') !== "<?php\n// activation successor drift\n" ||
                    file_get_contents($mu . '/twins-staging-overhaul/old.php') !== "<?php\n// replacement\n") {
                    throw new RuntimeException('Activation drift conflict changed current targets.');
                }
                self::assertDeployAttemptRecorded($fixture);
                return $result;
            }
            if ($scenario === 'activation-failure') {
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'ACTIVATION_FAILURE_ROLLED_BACK',
                    'Fixture activation failure.'
                );
                self::assertOldTargets($fixture);
                $safeActivation = self::instance($fixture, false);
                self::expectFailure(
                    static function () use ($safeActivation): void { $safeActivation->execute('--deploy'); },
                    'ACTIVATION_RETRY_REJECTED',
                    'DEPLOY_ATTEMPT_ALREADY_RECORDED'
                );
                return $result;
            }
            if ($scenario === 'partial-activation-failure') {
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'PARTIAL_ACTIVATION_FAILURE_ROLLED_BACK',
                    'Fixture partial activation failure.'
                );
                self::assertOldTargets($fixture);
                self::assertNoIncoming($fixture);
                self::assertNoActivationBackups($fixture);
                self::assertDeployAttemptRecorded($fixture);
                return $result;
            }
            $result = $deploy->execute('--deploy');
            self::assertCandidateTargets($fixture);
            if ($scenario === 'existing-core-install') {
                return $result;
            }
            if ($scenario === 'second-deploy-conflict') {
                return self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--deploy'); },
                    'SECOND_DEPLOY_REJECTED',
                    'EXPECTED_OLD_CONFLICT'
                );
            }
            if ($scenario === 'rollback-existing-core') {
                $result = $deploy->execute('--rollback');
                self::assertOldTargets($fixture);
                return $result;
            }
            if ($scenario === 'rollback-drift') {
                $mu = $fixture['root'] . '/wp-content/mu-plugins';
                file_put_contents($mu . '/twins-brand-experience/bootstrap.php', "<?php\n// successor drift\n");
                $overhaulBefore = file_get_contents($mu . '/twins-staging-overhaul/old.php');
                $result = self::expectFailure(
                    static function () use ($deploy): void { $deploy->execute('--rollback'); },
                    'ROLLBACK_DRIFT_REJECTED',
                    'ROLLBACK_CONFLICT'
                );
                if (file_get_contents($mu . '/twins-brand-experience/bootstrap.php') !== "<?php\n// successor drift\n" ||
                    file_get_contents($mu . '/twins-staging-overhaul/old.php') !== $overhaulBefore) {
                    throw new RuntimeException('Rollback conflict changed current targets.');
                }
                return $result;
            }
            throw new InvalidArgumentException('Unknown fixture scenario.');
        }

        public static function cleanup(array $fixture): void
        {
            self::remove($fixture['base']);
        }

        private static function instance(array $fixture, bool $activationFailure, string $scenario = ''): TwinsPrivateStagingDeploy
        {
            $identity = static function (): void {};
            $activation = static function () use ($activationFailure, $fixture, $scenario): void {
                if ($scenario === 'activation-success-drift') {
                    file_put_contents(
                        $fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience/bootstrap.php',
                        "<?php\n// activation returned with successor drift\n"
                    );
                }
                if ($scenario === 'activation-deletion-drift') {
                    self::remove($fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience');
                }
                if ($scenario === 'activation-failure-drift') {
                    file_put_contents(
                        $fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience/bootstrap.php',
                        "<?php\n// activation successor drift\n"
                    );
                }
                if ($activationFailure) throw new RuntimeException('Fixture activation failure.');
            };
            $deployProbe = static function (string $phase, string $target) use ($fixture, $scenario): void {
                if ($scenario === 'late-expected-old-drift' && $phase === 'before-second-cas') {
                    file_put_contents(
                        $fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience/bootstrap.php',
                        "<?php\n// late external drift\n"
                    );
                }
                if ($scenario === 'incoming-copy-failure' && $phase === 'before-copy' && $target === 'twins-staging-overhaul') {
                    throw new RuntimeException('Fixture incoming copy failure.');
                }
                if ($scenario === 'incoming-copy-drift' && $phase === 'after-copy' && $target === 'twins-brand-experience') {
                    file_put_contents(
                        $fixture['root'] . '/wp-content/mu-plugins/.twins-brand-experience.incoming/bootstrap.php',
                        "<?php\n// copied bytes drifted\n"
                    );
                }
                if ($scenario === 'partial-activation-failure' && $phase === 'after-backup' &&
                    $target === 'twins-staging-overhaul') {
                    throw new RuntimeException('Fixture partial activation failure.');
                }
            };
            $reflection = new ReflectionClass(TwinsPrivateStagingDeploy::class);
            $constructor = $reflection->getConstructor();
            $constructor->setAccessible(true);
            $instance = $reflection->newInstanceWithoutConstructor();
            $constructor->invoke(
                $instance,
                $fixture['root'],
                $fixture['transaction'],
                $fixture['manifest'],
                $identity,
                $activation,
                $deployProbe
            );
            return $instance;
        }

        private static function entry(string $role, string $destination, string $file): array
        {
            return [
                'role' => $role,
                'source' => 'twins-brand-experience/fixture/' . str_replace('/', '-', $destination),
                'destination' => $destination,
                'size' => filesize($file),
                'sha256' => hash_file('sha256', $file),
            ];
        }

        private static function writeManifest(string $path, array $files): void
        {
            self::write($path, json_encode([
                'schemaVersion' => 1,
                'applicationIdentity' => 'https://danielj140.sg-host.com/',
                'environment' => 'staging',
                'productionWriteAuthority' => false,
                'files' => $files,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }

        private static function refreshDeployHash(array $fixture, string $destination, string $file): void
        {
            $manifest = json_decode((string) file_get_contents($fixture['manifest']), true);
            foreach ($manifest['files'] as &$entry) {
                if ($entry['destination'] === $destination) {
                    $entry['size'] = filesize($file);
                    $entry['sha256'] = hash_file('sha256', $file);
                }
            }
            unset($entry);
            self::writeManifest($fixture['manifest'], $manifest['files']);
        }

        private static function filterDeployEntries(array $fixture, callable $keep): void
        {
            $manifest = json_decode((string) file_get_contents($fixture['manifest']), true);
            $manifest['files'] = array_values(array_filter($manifest['files'], static function (array $entry) use ($keep): bool {
                return $entry['role'] !== 'deploy' || $keep($entry);
            }));
            self::writeManifest($fixture['manifest'], $manifest['files']);
        }

        private static function assertOldTargets(array $fixture): void
        {
            $mu = $fixture['root'] . '/wp-content/mu-plugins';
            if (strpos((string) file_get_contents($mu . '/twins-brand-experience/bootstrap.php'), 'expected old core') === false ||
                file_get_contents($mu . '/twins-brand-experience/nested/legacy.txt') !== "expected old nested bytes\n" ||
                strpos((string) file_get_contents($mu . '/twins-staging-overhaul/old.php'), 'expected old') === false) {
                throw new RuntimeException('Expected-old targets were not restored.');
            }
            self::assertNoActivationBackups($fixture);
        }

        private static function assertCandidateTargets(array $fixture): void
        {
            $mu = $fixture['root'] . '/wp-content/mu-plugins';
            if (strpos((string) file_get_contents($mu . '/twins-brand-experience/bootstrap.php'), 'candidate core') === false ||
                strpos((string) file_get_contents($mu . '/twins-staging-overhaul/old.php'), 'replacement') === false) {
                throw new RuntimeException('Candidate targets were not installed.');
            }
            self::assertNoActivationBackups($fixture);
        }

        private static function assertNoIncoming(array $fixture): void
        {
            $mu = $fixture['root'] . '/wp-content/mu-plugins';
            foreach (['twins-brand-experience', 'twins-staging-overhaul'] as $name) {
                if (file_exists($mu . '/.' . $name . '.incoming') || is_link($mu . '/.' . $name . '.incoming')) {
                    throw new RuntimeException('Partial incoming target remained.');
                }
            }
        }

        private static function assertNoActivationBackups(array $fixture): void
        {
            $mu = $fixture['root'] . '/wp-content/mu-plugins';
            foreach (['twins-brand-experience', 'twins-staging-overhaul'] as $name) {
                if (file_exists($mu . '/.' . $name . '.expected-old-backup') ||
                    is_link($mu . '/.' . $name . '.expected-old-backup')) {
                    throw new RuntimeException('Activation backup was not cleaned.');
                }
            }
        }

        private static function assertDeployAttemptRecorded(array $fixture): void
        {
            $marker = $fixture['transaction'] . '/state/deploy-attempt.json';
            if (!is_file($marker) || is_link($marker)) {
                throw new RuntimeException('Deploy attempt marker missing.');
            }
            $record = json_decode((string) file_get_contents($marker), true);
            if (!is_array($record) || ($record['writeAuthority'] ?? null) !== false ||
                ($record['productionWriteAuthority'] ?? null) !== false) {
                throw new RuntimeException('Deploy attempt marker invalid.');
            }
        }

        private static function records(string $base): array
        {
            $records = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $item) {
                if ($item->isLink() || !$item->isFile()) {
                    throw new RuntimeException('Fixture record tree invalid.');
                }
                $path = $item->getPathname();
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($base) + 1));
                $records[$relative] = ['size' => filesize($path), 'sha256' => hash_file('sha256', $path)];
            }
            ksort($records, SORT_STRING);
            return $records;
        }

        private static function expectFailure(callable $operation, string $status, string $expectedMessage): array
        {
            try {
                $operation();
            } catch (Throwable $expected) {
                if ($expected->getMessage() !== $expectedMessage) {
                    throw new RuntimeException('Unexpected failure: ' . $expected->getMessage());
                }
                return ['status' => $status, 'writeAuthority' => false, 'productionWriteAuthority' => false];
            }
            throw new RuntimeException('Expected operation to fail.');
        }

        private static function write(string $file, string $bytes): void
        {
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0700, true)) {
                throw new RuntimeException('Fixture directory failed.');
            }
            file_put_contents($file, $bytes);
        }

        private static function remove(string $path): void
        {
            if (is_link($path) || is_file($path)) { @unlink($path); return; }
            if (!is_dir($path)) return;
            foreach (scandir($path) ?: [] as $name) {
                if ($name !== '.' && $name !== '..') self::remove($path . '/' . $name);
            }
            @rmdir($path);
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ &&
    !(defined('TWINS_PRIVATE_STAGING_DEPLOY_TESTING') && TWINS_PRIVATE_STAGING_DEPLOY_TESTING)) {
    $operation = $argv[1] ?? '';
    try {
        $result = TwinsPrivateStagingDeploy::forProduction()->execute($operation);
        echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    } catch (Throwable $error) {
        echo json_encode([
            'status' => 'PRIVATE_STAGING_OPERATION_FAILED',
            'operation' => $operation,
            'writeAuthority' => false,
            'productionWriteAuthority' => false,
        ], JSON_UNESCAPED_SLASHES) . "\n";
        exit(1);
    }
}
