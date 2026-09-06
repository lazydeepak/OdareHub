<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class SetupStateService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_CONFIGURED = 'configured';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_PARTIAL = 'partial';

    /**
     * @return array<int,string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RUNNING,
            self::STATUS_INSTALLED,
            self::STATUS_CONFIGURED,
            self::STATUS_VERIFIED,
            self::STATUS_FAILED,
            self::STATUS_ROLLED_BACK,
            self::STATUS_PARTIAL,
        ];
    }

    public function ensureTables(): void
    {
        $statusEnum = "'" . implode("','", self::statuses()) . "'";

        DB::query("CREATE TABLE IF NOT EXISTS core_setup_runs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            target_type VARCHAR(40) NOT NULL,
            target_key VARCHAR(190) NOT NULL,
            action_key VARCHAR(80) NOT NULL,
            status ENUM({$statusEnum}) NOT NULL DEFAULT 'pending',
            rollback_performed TINYINT(1) NOT NULL DEFAULT 0,
            warnings_json LONGTEXT NULL,
            error_text TEXT NULL,
            manual_attention_text TEXT NULL,
            resume_action VARCHAR(80) NULL,
            retry_action VARCHAR(80) NULL,
            repair_action VARCHAR(80) NULL,
            run_meta_json LONGTEXT NULL,
            started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at TIMESTAMP NULL DEFAULT NULL,
            created_by VARCHAR(190) NULL,
            INDEX idx_setup_runs_target (target_type, target_key),
            INDEX idx_setup_runs_started (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS core_setup_steps (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            run_id BIGINT NOT NULL,
            step_key VARCHAR(120) NOT NULL,
            step_label VARCHAR(190) NOT NULL,
            step_order INT NOT NULL DEFAULT 0,
            status ENUM({$statusEnum}) NOT NULL DEFAULT 'pending',
            rollback_performed TINYINT(1) NOT NULL DEFAULT 0,
            result_text TEXT NULL,
            error_text TEXT NULL,
            warnings_json LONGTEXT NULL,
            manual_attention_text TEXT NULL,
            step_meta_json LONGTEXT NULL,
            started_at TIMESTAMP NULL DEFAULT NULL,
            finished_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_setup_steps_run (run_id, step_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * @param array<int,array<string,mixed>> $steps
     * @param array<string,mixed> $meta
     */
    public function startRun(string $targetType, string $targetKey, string $actionKey, array $steps, array $meta = []): int
    {
        $this->ensureTables();
        $actor = (string)(Auth::user()['email'] ?? 'system');

        DB::query(
            'INSERT INTO core_setup_runs (target_type, target_key, action_key, status, run_meta_json, created_by, started_at) VALUES (?,?,?,?,?,?,NOW())',
            [
                $targetType,
                $targetKey,
                $actionKey,
                self::STATUS_RUNNING,
                $this->encode($meta),
                $actor,
            ]
        );

        $runId = (int)DB::conn()->insert_id;
        $order = 10;
        foreach ($steps as $step) {
            DB::query(
                'INSERT INTO core_setup_steps (run_id, step_key, step_label, step_order, status) VALUES (?,?,?,?,?)',
                [
                    $runId,
                    (string)($step['key'] ?? ''),
                    (string)($step['label'] ?? ($step['key'] ?? 'Step')),
                    $order,
                    self::STATUS_PENDING,
                ]
            );
            $order += 10;
        }

        return $runId;
    }

    public function startStep(int $runId, string $stepKey): void
    {
        DB::query(
            'UPDATE core_setup_steps SET status=?, started_at=NOW(), error_text=NULL, warnings_json=NULL, manual_attention_text=NULL WHERE run_id=? AND step_key=?',
            [self::STATUS_RUNNING, $runId, $stepKey]
        );
    }

    /**
     * @param array<int,string> $warnings
     * @param array<string,mixed> $meta
     */
    public function completeStep(int $runId, string $stepKey, string $status, string $resultText = '', array $warnings = [], bool $rollbackPerformed = false, string $manualAttention = '', array $meta = []): void
    {
        DB::query(
            'UPDATE core_setup_steps SET status=?, finished_at=NOW(), result_text=?, warnings_json=?, rollback_performed=?, manual_attention_text=?, step_meta_json=? WHERE run_id=? AND step_key=?',
            [
                $status,
                $resultText,
                $this->encode($warnings),
                $rollbackPerformed ? 1 : 0,
                $manualAttention,
                $this->encode($meta),
                $runId,
                $stepKey,
            ]
        );
    }

    /**
     * @param array<int,string> $warnings
     * @param array<string,mixed> $meta
     */
    public function failStep(int $runId, string $stepKey, string $errorText, array $warnings = [], bool $rollbackPerformed = false, string $manualAttention = '', array $meta = []): void
    {
        DB::query(
            'UPDATE core_setup_steps SET status=?, finished_at=NOW(), error_text=?, warnings_json=?, rollback_performed=?, manual_attention_text=?, step_meta_json=? WHERE run_id=? AND step_key=?',
            [
                self::STATUS_FAILED,
                $errorText,
                $this->encode($warnings),
                $rollbackPerformed ? 1 : 0,
                $manualAttention,
                $this->encode($meta),
                $runId,
                $stepKey,
            ]
        );
    }

    /**
     * @param array<int,string> $warnings
     * @param array<string,mixed> $meta
     */
    public function finalizeRun(int $runId, string $status, array $warnings = [], ?string $errorText = null, bool $rollbackPerformed = false, string $manualAttention = '', ?string $resumeAction = null, ?string $retryAction = null, ?string $repairAction = null, array $meta = []): void
    {
        DB::query(
            'UPDATE core_setup_runs SET status=?, finished_at=NOW(), warnings_json=?, error_text=?, rollback_performed=?, manual_attention_text=?, resume_action=?, retry_action=?, repair_action=?, run_meta_json=? WHERE id=?',
            [
                $status,
                $this->encode($warnings),
                $errorText,
                $rollbackPerformed ? 1 : 0,
                $manualAttention,
                $resumeAction,
                $retryAction,
                $repairAction,
                $this->encode($meta),
                $runId,
            ]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function latestRun(string $targetType, string $targetKey): ?array
    {
        $this->ensureTables();
        $run = DB::fetchOne(
            'SELECT * FROM core_setup_runs WHERE target_type=? AND target_key=? ORDER BY id DESC LIMIT 1',
            [$targetType, $targetKey]
        );
        if (!is_array($run)) {
            return null;
        }

        $run['warnings'] = $this->decodeList((string)($run['warnings_json'] ?? '[]'));
        $run['meta'] = $this->decodeMap((string)($run['run_meta_json'] ?? '{}'));
        $run['steps'] = $this->stepsForRun((int)($run['id'] ?? 0));
        $run['completed_steps'] = count(array_filter((array)$run['steps'], static fn(array $step): bool => in_array((string)($step['status'] ?? ''), [
            self::STATUS_INSTALLED,
            self::STATUS_CONFIGURED,
            self::STATUS_VERIFIED,
            self::STATUS_ROLLED_BACK,
        ], true)));
        $run['failed_step'] = $this->failedStepFromSteps((array)$run['steps']);
        return $run;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function recentRuns(int $limit = 50): array
    {
        $this->ensureTables();
        $rows = DB::fetchAll(
            'SELECT * FROM core_setup_runs ORDER BY started_at DESC, id DESC LIMIT ' . max(1, (int)$limit)
        );
        foreach ($rows as &$row) {
            $row['warnings'] = $this->decodeList((string)($row['warnings_json'] ?? '[]'));
            $row['meta'] = $this->decodeMap((string)($row['run_meta_json'] ?? '{}'));
            $row['steps'] = $this->stepsForRun((int)($row['id'] ?? 0));
            $row['failed_step'] = $this->failedStepFromSteps((array)$row['steps']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function stepsForRun(int $runId): array
    {
        if ($runId <= 0) {
            return [];
        }

        $steps = DB::fetchAll('SELECT * FROM core_setup_steps WHERE run_id=? ORDER BY step_order ASC, id ASC', [$runId]);
        foreach ($steps as &$step) {
            $step['warnings'] = $this->decodeList((string)($step['warnings_json'] ?? '[]'));
            $step['meta'] = $this->decodeMap((string)($step['step_meta_json'] ?? '{}'));
        }
        unset($step);

        return $steps;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function failedStepFromSteps(array $steps): ?array
    {
        foreach ($steps as $step) {
            if ((string)($step['status'] ?? '') === self::STATUS_FAILED) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $value
     */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * @return array<int,string>
     */
    private function decodeList(string $json): array
    {
        $value = json_decode($json, true);
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map('strval', $value));
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeMap(string $json): array
    {
        $value = json_decode($json, true);
        return is_array($value) ? $value : [];
    }
}
