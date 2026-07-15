<?php
declare(strict_types=1);

final class TwinsPrivateStagingDeploy
{
    private const APPLICATION_IDENTITY = 'https://danielj140.sg-host.com/';
    private const ENVIRONMENT = 'staging';
    private const WEB_ROOT = '/home/customer/www/danielj140.sg-host.com/public_html';
    private const TRANSACTION_ROOT = '/home/customer/staging-safety/brand-wide-20260715';
    private const MAX_FILES = 4096;
    private const MAX_FILE_SIZE = 67108864;

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

    private function __construct(string $root, string $transaction, string $manifestPath, callable $identityProbe, callable $activationProbe)
    {
        $this->root = rtrim($root, '/');
        $this->transaction = rtrim($transaction, '/');
        $this->manifestPath = $manifestPath;
        $this->identityProbe = $identityProbe;
        $this->activationProbe = $activationProbe;
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
        return new self(
            self::WEB_ROOT,
            self::TRANSACTION_ROOT,
            self::TRANSACTION_ROOT . '/verification/twins-brand-experience/manifests/staging-runtime.json',
            $identity,
            $activation
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
        return array_values(array_filter($manifest['files'], static function (array $entry): bool {
            return $entry['role'] === 'deploy';
        }));
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
        $state = $this->stateRoot();
        if (file_exists($state) || is_link($state)) {
            throw new RuntimeException('EXPECTED_OLD_ALREADY_CAPTURED');
        }
        $core = $this->muRoot() . '/twins-brand-experience';
        if (file_exists($core) || is_link($core)) {
            throw new RuntimeException('UNEXPECTED_EXISTING_CORE');
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
        foreach ($this->targetNames($manifest) as $name) {
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
        foreach ($this->targetNames($manifest) as $name) {
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
        $incoming = [];
        foreach ($this->targetNames($manifest) as $name) {
            $path = $this->muRoot() . '/.' . $name . '.incoming';
            if (file_exists($path) || is_link($path)) {
                throw new RuntimeException('INCOMING_TARGET_EXISTS');
            }
            $this->copyTree($this->candidateRoot() . '/' . $name, $path, 0644);
            $incoming[$name] = $path;
        }
        $activated = false;
        try {
            foreach ($this->targetNames($manifest) as $name) {
                $target = $this->muRoot() . '/' . $name;
                if (file_exists($target) || is_link($target)) {
                    $this->removeTree($target);
                }
                if (!rename($incoming[$name], $target)) {
                    throw new RuntimeException('ACTIVATION_RENAME_FAILED');
                }
            }
            $activated = true;
            call_user_func($this->activationProbe);
        } catch (Throwable $error) {
            if ($activated || $this->targetsDifferFromSnapshot($snapshot)) {
                $this->restoreSnapshot($snapshot);
            }
            foreach ($incoming as $path) {
                if (file_exists($path) || is_link($path)) {
                    $this->removeTree($path);
                }
            }
            throw $error;
        }
    }

    private function rollback(array $manifest): void
    {
        $snapshot = $this->readExpectedOld($manifest);
        $this->restoreSnapshot($snapshot);
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

    private function targetsDifferFromSnapshot(array $snapshot): bool
    {
        try {
            $this->assertExpectedOldCurrent($snapshot);
            return false;
        } catch (Throwable $ignored) {
            return true;
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

    private static function runPhp(array $command, string $expected): void
    {
        $pipes = [];
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('PHP_SUBPROCESS_UNAVAILABLE');
        }
        $stdout = stream_get_contents($pipes[1], self::MAX_FILE_SIZE);
        $stderr = stream_get_contents($pipes[2], self::MAX_FILE_SIZE);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || strpos((string) $stdout, $expected) === false) {
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
            self::write($mu . '/twins-staging-overhaul/old.php', "<?php\n// expected old\n");
            self::write($mu . '/twins-staging-overhaul.php', "<?php\n// loader\n");
            self::write($mu . '/twins-staging-safety.php', "<?php\n// safety\n");
            self::write($mu . '/twins-staging-assets/a.txt', "asset\n");
            $candidate = $transaction . '/candidate/wp-content/mu-plugins';
            self::write($candidate . '/twins-brand-experience/bootstrap.php', "<?php\ndeclare(strict_types=1);\n");
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
            $activationFailure = $scenario === 'activation-failure';
            $deploy = self::instance($fixture, $activationFailure);
            if ($scenario === 'dry-run') {
                return $deploy->execute('--dry-run');
            }
            if ($scenario === 'prerequisite-drift') {
                file_put_contents($fixture['root'] . '/wp-content/mu-plugins/twins-staging-assets/a.txt', 'drift');
                return self::expectFailure(static function () use ($deploy): void { $deploy->execute('--dry-run'); }, 'PREREQUISITE_DRIFT_REJECTED');
            }
            if ($scenario === 'unexpected-core') {
                self::write($fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience/unexpected.php', '<?php');
                return self::expectFailure(static function () use ($deploy): void { $deploy->execute('--capture-expected-old'); }, 'UNEXPECTED_CORE_REJECTED');
            }
            if ($scenario === 'non-regular-rejected') {
                $asset = $fixture['root'] . '/wp-content/mu-plugins/twins-staging-assets/a.txt';
                unlink($asset);
                symlink('../twins-staging-safety.php', $asset);
                return self::expectFailure(static function () use ($deploy): void { $deploy->execute('--dry-run'); }, 'NON_REGULAR_REJECTED');
            }
            if ($scenario === 'core-boot-failure') {
                $bootstrap = $fixture['transaction'] . '/candidate/wp-content/mu-plugins/twins-brand-experience/bootstrap.php';
                file_put_contents($bootstrap, "<?php this is invalid !!!\n");
                self::refreshDeployHash($fixture, 'twins-brand-experience/bootstrap.php', $bootstrap);
                $deploy = self::instance($fixture, false);
                $deploy->execute('--capture-expected-old');
                $result = self::expectFailure(static function () use ($deploy): void { $deploy->execute('--deploy'); }, 'CORE_BOOT_FAILURE_REJECTED');
                if (file_exists($fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience')) {
                    throw new RuntimeException('Failed boot installed core.');
                }
                return $result;
            }
            $deploy->execute('--capture-expected-old');
            if ($scenario === 'activation-failure') {
                $result = self::expectFailure(static function () use ($deploy): void { $deploy->execute('--deploy'); }, 'ACTIVATION_FAILURE_ROLLED_BACK');
                if (file_exists($fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience') ||
                    strpos((string) file_get_contents($fixture['root'] . '/wp-content/mu-plugins/twins-staging-overhaul/old.php'), 'expected old') === false) {
                    throw new RuntimeException('Activation rollback failed.');
                }
                return $result;
            }
            $result = $deploy->execute('--deploy');
            if (!file_exists($fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience/bootstrap.php')) {
                throw new RuntimeException('Core was not installed.');
            }
            if ($scenario === 'absent-core-install') {
                return $result;
            }
            if ($scenario === 'rollback-absent-core') {
                $result = $deploy->execute('--rollback');
                if (file_exists($fixture['root'] . '/wp-content/mu-plugins/twins-brand-experience') ||
                    strpos((string) file_get_contents($fixture['root'] . '/wp-content/mu-plugins/twins-staging-overhaul/old.php'), 'expected old') === false) {
                    throw new RuntimeException('Absent core state was not restored.');
                }
                return $result;
            }
            throw new InvalidArgumentException('Unknown fixture scenario.');
        }

        public static function cleanup(array $fixture): void
        {
            self::remove($fixture['base']);
        }

        private static function instance(array $fixture, bool $activationFailure): TwinsPrivateStagingDeploy
        {
            $identity = static function (): void {};
            $activation = static function () use ($activationFailure): void {
                if ($activationFailure) throw new RuntimeException('Fixture activation failure.');
            };
            $reflection = new ReflectionClass(TwinsPrivateStagingDeploy::class);
            $constructor = $reflection->getConstructor();
            $constructor->setAccessible(true);
            $instance = $reflection->newInstanceWithoutConstructor();
            $constructor->invoke($instance, $fixture['root'], $fixture['transaction'], $fixture['manifest'], $identity, $activation);
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

        private static function expectFailure(callable $operation, string $status): array
        {
            try {
                $operation();
            } catch (Throwable $expected) {
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
