<?php

declare(strict_types=1);

namespace {
    $GLOBALS['twins_staging_chrome_posts'] = [];
    $GLOBALS['twins_staging_chrome_meta'] = [];
    $GLOBALS['twins_staging_chrome_save_calls'] = [];
    $GLOBALS['twins_staging_chrome_save_failures'] = [];
    $GLOBALS['twins_staging_chrome_save_drifts'] = [];
    $GLOBALS['twins_staging_chrome_snapshot_calls'] = 0;
    $GLOBALS['twins_staging_chrome_snapshot_failures'] = [];
    $GLOBALS['twins_staging_chrome_statuses'] = [];

    function home_url(): string
    {
        return 'https://danielj140.sg-host.com';
    }

    function get_current_blog_id(): int
    {
        return 1;
    }

    function get_post(int $document_id)
    {
        return $GLOBALS['twins_staging_chrome_posts'][$document_id] ?? null;
    }

    function get_post_meta(int $document_id, string $key, bool $single = false)
    {
        unset($single);
        return $GLOBALS['twins_staging_chrome_meta'][$document_id][$key] ?? '';
    }
}

namespace ElementorPro\Modules\ThemeBuilder {
    final class HarnessConditionsManager
    {
        public function save_conditions(int $document_id, array $conditions): void
        {
            $call_number = count($GLOBALS['twins_staging_chrome_save_calls']) + 1;
            $GLOBALS['twins_staging_chrome_save_calls'][] = [
                'documentId' => $document_id,
                'conditions' => $conditions,
            ];
            if (isset($GLOBALS['twins_staging_chrome_save_failures'][$call_number])) {
                throw new \RuntimeException($GLOBALS['twins_staging_chrome_save_failures'][$call_number]);
            }
            $GLOBALS['twins_staging_chrome_meta'][$document_id]['_elementor_conditions'] = array_map(
                static function (array $segments): string {
                    return implode('/', $segments);
                },
                $conditions
            );
            if (isset($GLOBALS['twins_staging_chrome_save_drifts'][$call_number])) {
                foreach ($GLOBALS['twins_staging_chrome_save_drifts'][$call_number] as $drift_id => $drift_conditions) {
                    $GLOBALS['twins_staging_chrome_meta'][$drift_id]['_elementor_conditions'] = $drift_conditions;
                }
            }
        }
    }

    final class Module
    {
        public static function instance(): self
        {
            return new self();
        }

        public function get_conditions_manager(): HarnessConditionsManager
        {
            return new HarnessConditionsManager();
        }
    }
}

namespace {
    function twins_staging_chrome_harness_assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function twins_staging_chrome_harness_seed(
        array $conditions,
        array $failures = [],
        array $drifts = [],
        array $snapshot_failures = [],
        array $statuses = []
    ): void
    {
        $GLOBALS['twins_staging_chrome_meta'] = [];
        foreach ($conditions as $document_id => $document_conditions) {
            $GLOBALS['twins_staging_chrome_meta'][$document_id]['_elementor_conditions'] = $document_conditions;
        }
        $GLOBALS['twins_staging_chrome_save_calls'] = [];
        $GLOBALS['twins_staging_chrome_save_failures'] = $failures;
        $GLOBALS['twins_staging_chrome_save_drifts'] = $drifts;
        $GLOBALS['twins_staging_chrome_snapshot_calls'] = 0;
        $GLOBALS['twins_staging_chrome_snapshot_failures'] = $snapshot_failures;
        $GLOBALS['twins_staging_chrome_statuses'] = array_fill_keys(array_keys($conditions), 'publish');
        foreach ($statuses as $document_id => $status) {
            $GLOBALS['twins_staging_chrome_statuses'][$document_id] = $status;
        }
    }

    function twins_staging_chrome_harness_snapshot(): array
    {
        $call_number = ++$GLOBALS['twins_staging_chrome_snapshot_calls'];
        if (isset($GLOBALS['twins_staging_chrome_snapshot_failures'][$call_number])) {
            throw new \RuntimeException($GLOBALS['twins_staging_chrome_snapshot_failures'][$call_number]);
        }
        $snapshot = [];
        foreach (array_keys(twins_staging_chrome_manifest()) as $document_id) {
            $snapshot[$document_id] = [
                'status' => $GLOBALS['twins_staging_chrome_statuses'][$document_id] ?? 'missing',
                'conditions' => twins_staging_chrome_normalize_conditions(
                    $GLOBALS['twins_staging_chrome_meta'][$document_id]['_elementor_conditions'] ?? []
                ),
            ];
        }
        return $snapshot;
    }

    function twins_staging_chrome_harness_conditions(): array
    {
        return twins_staging_chrome_snapshot_conditions(twins_staging_chrome_harness_snapshot());
    }

    function twins_staging_chrome_harness_call_ids(): array
    {
        return array_map(
            static function (array $call): int {
                return $call['documentId'];
            },
            $GLOBALS['twins_staging_chrome_save_calls']
        );
    }

    if ($argc !== 2 || !is_file($argv[1])) {
        fwrite(STDERR, "Usage: php staging-chrome-transition-harness.php /path/to/staging-chrome-transition.php\n");
        exit(2);
    }

    require $argv[1];

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
    $unknown = $canary;
    $unknown[7336] = [];

    twins_staging_chrome_harness_assert(twins_staging_chrome_classify($canary) === 'CANARY', 'canary classification failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_classify($global) === 'GLOBAL', 'global classification failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_classify($original) === 'ORIGINAL', 'original classification failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_classify($unknown) === 'UNKNOWN', 'unknown classification failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_target_conditions('promote') === $global, 'promote target failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_target_conditions('restore-canary') === $canary, 'restore-canary target failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_target_conditions('rollback') === $original, 'rollback target failed');

    $execute = new \ReflectionMethod('Twins_Staging_Chrome_Transition_Runtime', 'execute');
    $execute->setAccessible(true);
    $snapshot_reader = static function (): array {
        return twins_staging_chrome_harness_snapshot();
    };
    $save_conditions = static function (int $document_id, array $conditions): void {
        \ElementorPro\Modules\ThemeBuilder\Module::instance()
            ->get_conditions_manager()
            ->save_conditions($document_id, $conditions);
    };
    $run = static function (string $mode, bool $dry_run) use ($execute, $snapshot_reader, $save_conditions): array {
        return $execute->invoke(null, $mode, $dry_run, $snapshot_reader, $save_conditions);
    };

    twins_staging_chrome_harness_seed($canary);
    $status = $run('status', false);
    twins_staging_chrome_harness_assert($status['afterState'] === 'CANARY', 'status actual state failed');
    twins_staging_chrome_harness_assert($status['projectedState'] === null, 'status projected state must be empty');
    twins_staging_chrome_harness_assert($status['stagingMutation'] === false, 'status mutated staging');
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_call_ids() === [], 'status called save_conditions');

    twins_staging_chrome_harness_seed($canary);
    $dry_run = $run('promote', true);
    twins_staging_chrome_harness_assert($dry_run['afterState'] === 'CANARY', 'dry-run replaced actual afterState');
    twins_staging_chrome_harness_assert($dry_run['projectedState'] === 'GLOBAL', 'dry-run projection failed');
    twins_staging_chrome_harness_assert($dry_run['changedDocumentIds'] === [], 'dry-run reported changed documents');
    twins_staging_chrome_harness_assert($dry_run['projectedDocumentIds'] === [7336, 36, 7344, 1409], 'dry-run projected order failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_call_ids() === [], 'dry-run called save_conditions');
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_conditions() === $canary, 'dry-run changed conditions');

    twins_staging_chrome_harness_seed($canary);
    $promoted = $run('promote', false);
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_call_ids() === [7336, 36, 7344, 1409], 'promotion order failed');
    twins_staging_chrome_harness_assert($GLOBALS['twins_staging_chrome_save_calls'][0]['conditions'] === [['include', 'general']], 'slash segment conversion failed');
    twins_staging_chrome_harness_assert($promoted['afterState'] === 'GLOBAL', 'promotion final state failed');
    twins_staging_chrome_harness_assert($promoted['changedDocumentIds'] === [7336, 36, 7344, 1409], 'promotion changed IDs failed');
    twins_staging_chrome_harness_assert($promoted['stagingMutation'] === true, 'promotion mutation receipt failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_conditions() === $global, 'promotion target failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_cli_exit_code($promoted) === 0, 'successful transition exit code failed');

    twins_staging_chrome_harness_seed($global, [], [], [], [7336 => 'draft']);
    $status_drift_refused = false;
    try {
        $run('status', false);
    } catch (\RuntimeException $error) {
        $status_drift_refused = strpos($error->getMessage(), 'template status mismatch 7336') !== false;
    }
    twins_staging_chrome_harness_assert($status_drift_refused, 'template status drift was not refused');
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_call_ids() === [], 'status drift attempted a write');

    twins_staging_chrome_harness_seed($global);
    $rolled_back = $run('rollback', false);
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_call_ids() === [36, 7336, 1409, 7344], 'rollback order failed');
    twins_staging_chrome_harness_assert($rolled_back['afterState'] === 'ORIGINAL', 'rollback final state failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_conditions() === $original, 'rollback target failed');

    twins_staging_chrome_harness_seed($canary, [2 => 'promotion write failed']);
    $compensated = $run('promote', false);
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_call_ids() === [7336, 36, 36, 7336, 1409, 7344], 'compensation order/one-pass failed');
    twins_staging_chrome_harness_assert($compensated['status'] === 'TRANSITION_COMPENSATED', 'compensated status failed');
    twins_staging_chrome_harness_assert($compensated['afterState'] === 'CANARY', 'compensated actual state failed');
    twins_staging_chrome_harness_assert($compensated['stagingMutation'] === true, 'compensated mutation receipt failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_conditions() === $canary, 'compensated target failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_cli_exit_code($compensated) === 1, 'compensated exit code failed');

    twins_staging_chrome_harness_seed($canary, [2 => 'promotion write failed', 4 => 'compensation write failed']);
    $compensation_failed = $run('promote', false);
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_call_ids() === [7336, 36, 36, 7336, 1409, 7344], 'failed compensation did not attempt every document once');
    twins_staging_chrome_harness_assert($compensation_failed['status'] === 'TRANSITION_COMPENSATION_FAILED', 'compensation failure status failed');
    twins_staging_chrome_harness_assert($compensation_failed['afterState'] === 'UNKNOWN', 'compensation failure actual state failed');
    twins_staging_chrome_harness_assert($compensation_failed['changedDocumentIds'] === [7336], 'compensation failure actual changed IDs failed');
    twins_staging_chrome_harness_assert(count($compensation_failed['compensationErrors']) === 1, 'compensation errors were not collected');
    twins_staging_chrome_harness_assert(twins_staging_chrome_cli_exit_code($compensation_failed) === 1, 'compensation failure exit code failed');

    twins_staging_chrome_harness_seed($global, [2 => 'rollback write failed']);
    $partial = $run('rollback', false);
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_call_ids() === [36, 7336, 7336, 36, 7344, 1409], 'failed rollback compensation order/one-pass failed');
    twins_staging_chrome_harness_assert($partial['status'] === 'TRANSITION_COMPENSATED', 'partial rollback was not compensated');
    twins_staging_chrome_harness_assert($partial['afterState'] === 'GLOBAL', 'partial rollback did not restore global state');
    twins_staging_chrome_harness_assert($partial['stagingMutation'] === true, 'partial failure hid mutation');
    twins_staging_chrome_harness_assert($partial['changedDocumentIds'] === [], 'partial rollback left changed IDs');
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_conditions() === $global, 'partial rollback compensation target failed');
    twins_staging_chrome_harness_assert(twins_staging_chrome_cli_exit_code($partial) === 1, 'partial failure exit code failed');

    twins_staging_chrome_harness_seed($global, [2 => 'restore-canary write failed']);
    $restore_partial = $run('restore-canary', false);
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_call_ids() === [36, 7336, 7336, 36, 7344, 1409], 'failed restore-canary compensation order/one-pass failed');
    twins_staging_chrome_harness_assert($restore_partial['status'] === 'TRANSITION_COMPENSATED', 'partial restore-canary was not compensated');
    twins_staging_chrome_harness_assert($restore_partial['afterState'] === 'GLOBAL', 'partial restore-canary did not restore global state');
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_conditions() === $global, 'partial restore-canary compensation target failed');

    twins_staging_chrome_harness_seed(
        $canary,
        [2 => 'promotion write failed'],
        [],
        [7 => 'compensation snapshot failed']
    );
    $compensation_preflight_failed = $run('promote', false);
    twins_staging_chrome_harness_assert(twins_staging_chrome_harness_call_ids() === [7336, 36], 'compensation preflight failure retried a write');
    twins_staging_chrome_harness_assert($compensation_preflight_failed['status'] === 'TRANSITION_COMPENSATION_FAILED', 'compensation preflight failure status failed');
    twins_staging_chrome_harness_assert($compensation_preflight_failed['stagingMutation'] === true, 'compensation preflight failure hid possible mutation');
    twins_staging_chrome_harness_assert($compensation_preflight_failed['afterState'] === 'UNKNOWN', 'compensation preflight failure was not indeterminate');
    twins_staging_chrome_harness_assert(count($compensation_preflight_failed['compensationErrors']) >= 1, 'compensation preflight error was not reported');

    twins_staging_chrome_harness_seed($canary, [], [
        1 => [1409 => []],
    ]);
    $drift_compensated = $run('promote', false);
    twins_staging_chrome_harness_assert(
        twins_staging_chrome_harness_call_ids() === [7336, 36, 7336, 1409, 7344],
        'condition drift was overwritten instead of forcing compensation'
    );
    twins_staging_chrome_harness_assert(
        $drift_compensated['status'] === 'TRANSITION_COMPENSATED',
        'condition drift did not fail closed through compensation'
    );
    twins_staging_chrome_harness_assert(
        twins_staging_chrome_harness_conditions() === $canary,
        'condition drift compensation did not restore the canary'
    );

    echo "STAGING_CHROME_TRANSITION_HARNESS_OK\n";
}
