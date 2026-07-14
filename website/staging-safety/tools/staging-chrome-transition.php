<?php

declare(strict_types=1);

/**
 * Fail-closed Elementor Theme Builder condition transition for private staging.
 */

function twins_staging_chrome_manifest(): array
{
    return [
        36   => ['title' => 'Header', 'type' => 'header', 'dataSha256' => 'f433dcb2b40578ee75394c486e7c13b987dc9f0cc20d9c83ab2d9c195996072d'],
        305  => ['title' => 'POP Menu Template', 'type' => 'section', 'dataSha256' => '4df9f5ae619f65b8eb4fdb674ee0fffa7b21d4f4ba3577509f1aa1d6b5360341'],
        7333 => ['title' => 'UNIT 1 DEP — POP MENU 305 twx2 — 2026-07-10', 'type' => 'section', 'dataSha256' => 'd00c1141386ddcb162200d0767741cd46901336a07e58b0fac2be3fe77605c8d'],
        7336 => ['title' => 'UNIT 1 CANARY — Header 36 twx2 — 2026-07-10', 'type' => 'header', 'dataSha256' => 'f158f14cc66da49e7621d0002da7536c38a34e5103abc54e2f83e155e9a743c0'],
        1409 => ['title' => 'Footer', 'type' => 'footer', 'dataSha256' => '4db2fe9f8f1fd6772a1b2908faafba3aa3a093a4b6a603ee9465cdb9263be296'],
        7344 => ['title' => 'UNIT 2 CANARY — Footer 1409 twx2 — 2026-07-10', 'type' => 'footer', 'dataSha256' => '4db2fe9f8f1fd6772a1b2908faafba3aa3a093a4b6a603ee9465cdb9263be296'],
        2163 => ['title' => 'Header Contact Us', 'type' => 'header', 'dataSha256' => '0928a31330c97748fa522910fe065bdc36e8f0b3b57c4aebf472e271dc19b7c7'],
        2179 => ['title' => 'Footer Contact Us', 'type' => 'footer', 'dataSha256' => 'a1b6a10f7aa12bbab7d138ca71cf6e30d73e8adf8f9f2b36985f459a8bdeef32'],
    ];
}

function twins_staging_chrome_condition_maps(): array
{
    $canary = [
        36 => ['include/general', 'exclude/singular/page/6065'],
        305 => [],
        7333 => [],
        7336 => ['include/singular/page/6065'],
        1409 => ['include/general', 'exclude/singular/page/6065'],
        7344 => ['include/singular/page/6065'],
        2163 => [],
        2179 => ['include/singular/page/2123'],
    ];
    $global = [
        36 => [],
        305 => [],
        7333 => [],
        7336 => ['include/general'],
        1409 => [],
        7344 => ['include/general'],
        2163 => [],
        2179 => ['include/singular/page/2123'],
    ];
    $original = [
        36 => ['include/general'],
        305 => [],
        7333 => [],
        7336 => [],
        1409 => ['include/general'],
        7344 => [],
        2163 => [],
        2179 => ['include/singular/page/2123'],
    ];

    return [
        'CANARY' => $canary,
        'GLOBAL' => $global,
        'ORIGINAL' => $original,
    ];
}

function twins_staging_chrome_target_conditions(string $mode): array
{
    $maps = twins_staging_chrome_condition_maps();
    $targets = [
        'promote' => $maps['GLOBAL'],
        'restore-canary' => $maps['CANARY'],
        'rollback' => $maps['ORIGINAL'],
    ];

    if (!isset($targets[$mode])) {
        throw new RuntimeException('TRANSITION_REFUSED: unsupported mode');
    }

    return $targets[$mode];
}

function twins_staging_chrome_classify(array $conditions): string
{
    foreach (twins_staging_chrome_condition_maps() as $state => $known_conditions) {
        if ($conditions === $known_conditions) {
            return $state;
        }
    }

    return 'UNKNOWN';
}

function twins_staging_chrome_assert_identity(): void
{
    if (!defined('WP_ENVIRONMENT_TYPE') || WP_ENVIRONMENT_TYPE !== 'staging') {
        throw new RuntimeException('TRANSITION_REFUSED: WP_ENVIRONMENT_TYPE must be staging');
    }
    if (!defined('TWINS_STAGING_SAFETY') || TWINS_STAGING_SAFETY !== true) {
        throw new RuntimeException('TRANSITION_REFUSED: TWINS_STAGING_SAFETY must be true');
    }
    if (rtrim(home_url(), '/') !== 'https://danielj140.sg-host.com') {
        throw new RuntimeException('TRANSITION_REFUSED: unexpected WordPress home URL');
    }
    if (get_current_blog_id() !== 1) {
        throw new RuntimeException('TRANSITION_REFUSED: unexpected WordPress blog');
    }
}

function twins_staging_chrome_normalize_conditions($conditions): array
{
    if ($conditions === '' || $conditions === null || $conditions === false) {
        return [];
    }
    if (!is_array($conditions)) {
        throw new RuntimeException('TRANSITION_REFUSED: malformed Elementor conditions');
    }

    $normalized = [];
    foreach ($conditions as $condition) {
        if (!is_string($condition) || $condition === '') {
            throw new RuntimeException('TRANSITION_REFUSED: malformed Elementor condition');
        }
        $normalized[] = $condition;
    }

    return $normalized;
}

function twins_staging_chrome_read_conditions(int $document_id): array
{
    return twins_staging_chrome_normalize_conditions(
        get_post_meta($document_id, '_elementor_conditions', true)
    );
}

function twins_staging_chrome_snapshot(): array
{
    twins_staging_chrome_assert_identity();

    $snapshot = [];
    foreach (twins_staging_chrome_manifest() as $document_id => $expected) {
        $post = get_post($document_id);
        if (!is_object($post) || !isset($post->ID, $post->post_title, $post->post_status)) {
            throw new RuntimeException('TRANSITION_REFUSED: missing template ' . $document_id);
        }
        if ((int) $post->ID !== $document_id || (string) $post->post_title !== $expected['title']) {
            throw new RuntimeException('TRANSITION_REFUSED: template identity mismatch ' . $document_id);
        }

        $template_type = get_post_meta($document_id, '_elementor_template_type', true);
        if (!is_string($template_type) || $template_type !== $expected['type']) {
            throw new RuntimeException('TRANSITION_REFUSED: template type mismatch ' . $document_id);
        }

        $elementor_data = get_post_meta($document_id, '_elementor_data', true);
        if (!is_string($elementor_data)) {
            throw new RuntimeException('TRANSITION_REFUSED: template data is not a string ' . $document_id);
        }
        $data_sha256 = hash('sha256', $elementor_data);
        if ($data_sha256 !== $expected['dataSha256']) {
            throw new RuntimeException('TRANSITION_REFUSED: template fingerprint mismatch ' . $document_id);
        }

        $snapshot[$document_id] = [
            'title' => (string) $post->post_title,
            'status' => (string) $post->post_status,
            'type' => $template_type,
            'dataSha256' => $data_sha256,
            'conditions' => twins_staging_chrome_read_conditions($document_id),
        ];
    }

    return $snapshot;
}

function twins_staging_chrome_snapshot_conditions(array $snapshot): array
{
    $conditions = [];
    foreach (twins_staging_chrome_manifest() as $document_id => $expected) {
        unset($expected);
        if (!isset($snapshot[$document_id]['conditions']) || !is_array($snapshot[$document_id]['conditions'])) {
            throw new RuntimeException('TRANSITION_REFUSED: incomplete transition snapshot');
        }
        $conditions[$document_id] = $snapshot[$document_id]['conditions'];
    }

    return $conditions;
}

function twins_staging_chrome_write_orders(): array
{
    return [
        'promote' => [7336, 36, 7344, 1409],
        'restore-canary' => [36, 7336, 1409, 7344],
        'rollback' => [36, 7336, 1409, 7344],
        'compensate' => [36, 7336, 1409, 7344],
    ];
}

function twins_staging_chrome_base_report(string $mode, string $before_state, array $snapshot): array
{
    return [
        'productionWriteAuthority' => false,
        'stagingMutation' => false,
        'mode' => $mode,
        'beforeState' => $before_state,
        'afterState' => $before_state,
        'projectedState' => null,
        'changedDocumentIds' => [],
        'projectedDocumentIds' => [],
        'snapshot' => $snapshot,
        'status' => 'TRANSITION_STATUS',
    ];
}

final class Twins_Staging_Chrome_Transition_Runtime
{
    public static function run(string $mode, bool $dry_run): array
    {
        $snapshot_reader = static function (): array {
            return twins_staging_chrome_snapshot();
        };
        $save_conditions = static function (int $document_id, array $conditions): void {
            $conditions_manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()
                ->get_conditions_manager();
            if (!is_object($conditions_manager) || !method_exists($conditions_manager, 'save_conditions')) {
                throw new RuntimeException('TRANSITION_REFUSED: Elementor conditions manager unavailable');
            }
            $conditions_manager->save_conditions($document_id, $conditions);
        };

        return self::execute($mode, $dry_run, $snapshot_reader, $save_conditions);
    }

    private static function execute(
        string $mode,
        bool $dry_run,
        callable $snapshot_reader,
        callable $save_conditions
    ): array {
        if (!in_array($mode, ['status', 'promote', 'restore-canary', 'rollback'], true)) {
            throw new RuntimeException('TRANSITION_REFUSED: unsupported mode');
        }

        $before_snapshot = $snapshot_reader();
        $before_conditions = twins_staging_chrome_snapshot_conditions($before_snapshot);
        $before_state = twins_staging_chrome_classify($before_conditions);
        $report = twins_staging_chrome_base_report($mode, $before_state, $before_snapshot);

        if ($mode === 'status') {
            return $report;
        }

        $expected_before_states = [
            'promote' => 'CANARY',
            'restore-canary' => 'GLOBAL',
            'rollback' => 'GLOBAL',
        ];
        if ($before_state !== $expected_before_states[$mode]) {
            throw new RuntimeException('TRANSITION_REFUSED: unexpected initial condition state');
        }

        $target = twins_staging_chrome_target_conditions($mode);
        $projected_state = twins_staging_chrome_classify($target);
        $projected_document_ids = twins_staging_chrome_write_orders()[$mode];

        if ($dry_run) {
            $report['projectedState'] = $projected_state;
            $report['projectedDocumentIds'] = $projected_document_ids;
            $report['status'] = 'TRANSITION_DRY_RUN';
            return $report;
        }

        $write = self::write_target($mode, false, $snapshot_reader, $save_conditions);
        $after = self::read_actual_state($snapshot_reader);
        if ($write['errors'] === [] && $after['state'] !== $projected_state) {
            $write['errors'][] = 'TRANSITION_WRITE_FAILED: final target state verification failed';
        }

        if ($write['errors'] === []) {
            $report['stagingMutation'] = true;
            $report['afterState'] = $after['state'];
            $report['changedDocumentIds'] = $projected_document_ids;
            $report['snapshot'] = $after['snapshot'];
            $report['status'] = 'TRANSITION_OK';
            return $report;
        }

        if ($mode === 'promote') {
            $compensation = self::write_target('compensate', true, $snapshot_reader, $save_conditions);
            $compensated = self::read_actual_state($snapshot_reader);
            $changed = self::actual_changed_document_ids($before_snapshot, $compensated['snapshot']);
            $compensation_failed = $compensation['errors'] !== [] || $compensated['state'] !== 'CANARY';

            $report['stagingMutation'] = true;
            $report['afterState'] = $compensated['state'];
            $report['changedDocumentIds'] = $changed === null ? $write['successfulIds'] : $changed;
            $report['snapshot'] = $compensated['snapshot'];
            $report['status'] = $compensation_failed
                ? 'TRANSITION_COMPENSATION_FAILED'
                : 'TRANSITION_COMPENSATED';
            $report['errors'] = $write['errors'];
            $report['compensationErrors'] = $compensation['errors'];
            if ($compensated['error'] !== null) {
                $report['compensationErrors'][] = $compensated['error'];
            }
            return $report;
        }

        $changed = self::actual_changed_document_ids($before_snapshot, $after['snapshot']);
        $report['stagingMutation'] = $write['attemptedIds'] !== [];
        $report['afterState'] = $after['state'];
        $report['changedDocumentIds'] = $changed === null ? $write['successfulIds'] : $changed;
        $report['snapshot'] = $after['snapshot'];
        $report['status'] = 'TRANSITION_FAILED';
        $report['errors'] = $write['errors'];
        if ($after['error'] !== null) {
            $report['errors'][] = $after['error'];
        }
        return $report;
    }

    private static function write_target(
        string $mode,
        bool $continue_after_error,
        callable $snapshot_reader,
        callable $save_conditions
    ): array {
        $target_mode = $mode === 'compensate' ? 'restore-canary' : $mode;
        $target = twins_staging_chrome_target_conditions($target_mode);
        $orders = twins_staging_chrome_write_orders();
        if (!isset($orders[$mode])) {
            throw new RuntimeException('TRANSITION_REFUSED: missing fixed write order');
        }

        $attempted_ids = [];
        $successful_ids = [];
        $errors = [];
        foreach ($orders[$mode] as $document_id) {
            $attempted_ids[] = $document_id;
            try {
                $pre_write_snapshot = $snapshot_reader();
                twins_staging_chrome_snapshot_conditions($pre_write_snapshot);

                $ordered_segments = [];
                foreach ($target[$document_id] as $condition) {
                    $ordered_segments[] = explode('/', $condition);
                }
                $save_conditions($document_id, $ordered_segments);

                $read_back_snapshot = $snapshot_reader();
                $read_back_conditions = twins_staging_chrome_snapshot_conditions($read_back_snapshot);
                if ($read_back_conditions[$document_id] !== $target[$document_id]) {
                    throw new RuntimeException(
                        'TRANSITION_WRITE_FAILED: condition read-back mismatch ' . $document_id
                    );
                }
                $successful_ids[] = $document_id;
            } catch (Throwable $error) {
                $errors[] = $document_id . ': ' . $error->getMessage();
                if (!$continue_after_error) {
                    break;
                }
            }
        }

        return [
            'attemptedIds' => $attempted_ids,
            'successfulIds' => $successful_ids,
            'errors' => $errors,
        ];
    }

    private static function read_actual_state(callable $snapshot_reader): array
    {
        try {
            $snapshot = $snapshot_reader();
            $conditions = twins_staging_chrome_snapshot_conditions($snapshot);
            return [
                'snapshot' => $snapshot,
                'state' => twins_staging_chrome_classify($conditions),
                'error' => null,
            ];
        } catch (Throwable $error) {
            return [
                'snapshot' => [],
                'state' => 'UNKNOWN',
                'error' => $error->getMessage(),
            ];
        }
    }

    private static function actual_changed_document_ids(array $before, array $after): ?array
    {
        if ($after === []) {
            return null;
        }
        try {
            $before_conditions = twins_staging_chrome_snapshot_conditions($before);
            $after_conditions = twins_staging_chrome_snapshot_conditions($after);
        } catch (Throwable $error) {
            unset($error);
            return null;
        }

        $changed = [];
        foreach (array_keys(twins_staging_chrome_manifest()) as $document_id) {
            if ($before_conditions[$document_id] !== $after_conditions[$document_id]) {
                $changed[] = $document_id;
            }
        }
        return $changed;
    }
}

function twins_staging_chrome_transition(string $mode, bool $dryRun): array
{
    return Twins_Staging_Chrome_Transition_Runtime::run($mode, $dryRun);
}

function twins_staging_chrome_cli_exit_code(array $result): int
{
    return in_array(
        $result['status'] ?? '',
        ['TRANSITION_STATUS', 'TRANSITION_DRY_RUN', 'TRANSITION_OK'],
        true
    ) ? 0 : 1;
}

function twins_staging_chrome_cli_error_report(string $mode, Throwable $error): array
{
    return [
        'productionWriteAuthority' => false,
        'stagingMutation' => false,
        'mode' => $mode,
        'beforeState' => null,
        'afterState' => null,
        'projectedState' => null,
        'changedDocumentIds' => [],
        'projectedDocumentIds' => [],
        'status' => 'TRANSITION_REFUSED',
        'error' => $error->getMessage(),
    ];
}

if (defined('WP_CLI') && WP_CLI === true) {
    $mode_raw = getenv('TWINS_STAGING_CHROME_MODE');
    $mode = $mode_raw === false || $mode_raw === '' ? 'status' : $mode_raw;
    $dry_run_raw = getenv('TWINS_STAGING_CHROME_DRY_RUN');
    $dry_run_raw = $dry_run_raw === false || $dry_run_raw === '' ? '0' : $dry_run_raw;

    try {
        if (!in_array($dry_run_raw, ['0', '1'], true)) {
            throw new RuntimeException('TRANSITION_REFUSED: dry-run flag must be exactly 0 or 1');
        }
        $result = twins_staging_chrome_transition($mode, $dry_run_raw === '1');
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $exit_code = twins_staging_chrome_cli_exit_code($result);
        if ($exit_code !== 0) {
            exit($exit_code);
        }
    } catch (Throwable $error) {
        echo json_encode(
            twins_staging_chrome_cli_error_report($mode, $error),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
        exit(1);
    }
}
