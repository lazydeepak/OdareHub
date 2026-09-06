<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class EnvironmentPortabilityHistoryService
{
    public const STATUS_CREATED = 'created';
    public const STATUS_PREVIEWED = 'previewed';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_FAILED = 'failed';

    public function ensureTable(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS core_environment_portability_runs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            operation_type VARCHAR(40) NOT NULL,
            scope_key VARCHAR(80) NOT NULL,
            source_environment VARCHAR(120) NULL,
            target_environment VARCHAR(120) NULL,
            snapshot_file_name VARCHAR(255) NULL,
            snapshot_file_path VARCHAR(500) NULL,
            preview_token VARCHAR(80) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'created',
            warning_text TEXT NULL,
            error_text TEXT NULL,
            summary_json LONGTEXT NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_env_preview_token (preview_token),
            KEY idx_env_runs_created (created_at),
            KEY idx_env_runs_operation (operation_type, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function recordSnapshotSuccess(string $scopeKey, string $sourceEnvironment, string $fileName, string $filePath, array $summary): int
    {
        $this->ensureTable();
        DB::query(
            'INSERT INTO core_environment_portability_runs (operation_type, scope_key, source_environment, snapshot_file_name, snapshot_file_path, status, warning_text, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                'snapshot_create',
                $scopeKey,
                $sourceEnvironment,
                $fileName,
                $filePath,
                self::STATUS_CREATED,
                trim((string)($summary['warning_text'] ?? '')),
                json_encode($summary, JSON_UNESCAPED_SLASHES),
                (string)(Auth::user()['email'] ?? 'system'),
            ]
        );
        return (int)DB::conn()->insert_id;
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function startClonePreview(string $scopeKey, string $sourceEnvironment, string $targetEnvironment, string $sourceFile, string $previewToken, array $summary): int
    {
        $this->ensureTable();
        DB::query(
            'INSERT INTO core_environment_portability_runs (operation_type, scope_key, source_environment, target_environment, snapshot_file_name, preview_token, status, warning_text, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                'clone_preview',
                $scopeKey,
                $sourceEnvironment,
                $targetEnvironment,
                $sourceFile,
                $previewToken,
                self::STATUS_PREVIEWED,
                trim((string)($summary['warning_text'] ?? '')),
                json_encode($summary, JSON_UNESCAPED_SLASHES),
                (string)(Auth::user()['email'] ?? 'system'),
            ]
        );
        return (int)DB::conn()->insert_id;
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function completeClone(int $runId, array $summary): void
    {
        $this->ensureTable();
        DB::query(
            'UPDATE core_environment_portability_runs
             SET status=?, warning_text=?, error_text=NULL, summary_json=?
             WHERE id=?',
            [
                self::STATUS_APPLIED,
                trim((string)($summary['warning_text'] ?? '')),
                json_encode($summary, JSON_UNESCAPED_SLASHES),
                $runId,
            ]
        );
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function failRun(?int $runId, string $operationType, string $scopeKey, ?string $sourceEnvironment, ?string $targetEnvironment, string $sourceFile, string $errorText, array $summary = []): void
    {
        $this->ensureTable();
        if ($runId !== null && $runId > 0) {
            DB::query(
                'UPDATE core_environment_portability_runs
                 SET status=?, warning_text=?, error_text=?, summary_json=?
                 WHERE id=?',
                [
                    self::STATUS_FAILED,
                    trim((string)($summary['warning_text'] ?? '')),
                    $errorText,
                    json_encode($summary, JSON_UNESCAPED_SLASHES),
                    $runId,
                ]
            );
            return;
        }

        DB::query(
            'INSERT INTO core_environment_portability_runs (operation_type, scope_key, source_environment, target_environment, snapshot_file_name, status, warning_text, error_text, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $operationType,
                $scopeKey,
                $sourceEnvironment,
                $targetEnvironment,
                $sourceFile,
                self::STATUS_FAILED,
                trim((string)($summary['warning_text'] ?? '')),
                $errorText,
                json_encode($summary, JSON_UNESCAPED_SLASHES),
                (string)(Auth::user()['email'] ?? 'system'),
            ]
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function recentRuns(int $limit = 30): array
    {
        $this->ensureTable();
        $rows = DB::fetchAll('SELECT * FROM core_environment_portability_runs ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit));
        foreach ($rows as &$row) {
            $decoded = json_decode((string)($row['summary_json'] ?? '{}'), true);
            $row['summary'] = is_array($decoded) ? $decoded : [];
        }
        unset($row);
        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $this->ensureTable();
        $row = DB::fetchOne('SELECT * FROM core_environment_portability_runs WHERE id=? LIMIT 1', [$id]);
        if (!is_array($row)) {
            return null;
        }
        $decoded = json_decode((string)($row['summary_json'] ?? '{}'), true);
        $row['summary'] = is_array($decoded) ? $decoded : [];
        return $row;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function savePreview(string $previewToken, array $payload): string
    {
        $dir = APP_ROOT . '/storage/environment_clone_previews';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . $previewToken . '.json';
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $path;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function loadPreview(string $previewToken): ?array
    {
        $path = APP_ROOT . '/storage/environment_clone_previews/' . $previewToken . '.json';
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    public function deletePreview(string $previewToken): void
    {
        $path = APP_ROOT . '/storage/environment_clone_previews/' . $previewToken . '.json';
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
