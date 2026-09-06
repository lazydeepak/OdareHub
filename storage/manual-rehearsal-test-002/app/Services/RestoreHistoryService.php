<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class RestoreHistoryService
{
    public const STATUS_PREVIEWED = 'previewed';
    public const STATUS_RESTORED = 'restored';
    public const STATUS_FAILED = 'failed';

    public function ensureTable(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS core_restore_runs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            target_type VARCHAR(40) NOT NULL,
            target_key VARCHAR(120) NOT NULL,
            suite_key VARCHAR(80) NULL,
            restore_type VARCHAR(80) NOT NULL,
            source_backup VARCHAR(255) NULL,
            preview_token VARCHAR(80) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'previewed',
            warning_text TEXT NULL,
            error_text TEXT NULL,
            rollback_attempted TINYINT(1) NOT NULL DEFAULT 0,
            summary_json LONGTEXT NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_restore_preview_token (preview_token),
            KEY idx_restore_suite (suite_key, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function startPreview(string $targetType, string $targetKey, ?string $suiteKey, string $restoreType, string $sourceBackup, string $previewToken, array $summary): int
    {
        $this->ensureTable();
        DB::query(
            'INSERT INTO core_restore_runs (target_type, target_key, suite_key, restore_type, source_backup, preview_token, status, warning_text, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $targetType,
                $targetKey,
                $suiteKey,
                $restoreType,
                $sourceBackup,
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
    public function completeRestore(int $runId, array $summary, bool $rollbackAttempted = false): void
    {
        $this->ensureTable();
        DB::query(
            'UPDATE core_restore_runs
             SET status=?, warning_text=?, error_text=NULL, rollback_attempted=?, summary_json=?
             WHERE id=?',
            [
                self::STATUS_RESTORED,
                trim((string)($summary['warning_text'] ?? '')),
                $rollbackAttempted ? 1 : 0,
                json_encode($summary, JSON_UNESCAPED_SLASHES),
                $runId,
            ]
        );
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function failRun(?int $runId, string $targetType, string $targetKey, ?string $suiteKey, string $restoreType, string $sourceBackup, string $errorText, array $summary = [], bool $rollbackAttempted = false): void
    {
        $this->ensureTable();
        if ($runId !== null && $runId > 0) {
            DB::query(
                'UPDATE core_restore_runs
                 SET status=?, warning_text=?, error_text=?, rollback_attempted=?, summary_json=?
                 WHERE id=?',
                [
                    self::STATUS_FAILED,
                    trim((string)($summary['warning_text'] ?? '')),
                    $errorText,
                    $rollbackAttempted ? 1 : 0,
                    json_encode($summary, JSON_UNESCAPED_SLASHES),
                    $runId,
                ]
            );
            return;
        }

        DB::query(
            'INSERT INTO core_restore_runs (target_type, target_key, suite_key, restore_type, source_backup, status, warning_text, error_text, rollback_attempted, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $targetType,
                $targetKey,
                $suiteKey,
                $restoreType,
                $sourceBackup,
                self::STATUS_FAILED,
                trim((string)($summary['warning_text'] ?? '')),
                $errorText,
                $rollbackAttempted ? 1 : 0,
                json_encode($summary, JSON_UNESCAPED_SLASHES),
                (string)(Auth::user()['email'] ?? 'system'),
            ]
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function recentRuns(?string $suiteKey = null, int $limit = 20): array
    {
        $this->ensureTable();
        $sql = 'SELECT * FROM core_restore_runs';
        $params = [];
        if ($suiteKey !== null && trim($suiteKey) !== '') {
            $sql .= ' WHERE suite_key=?';
            $params[] = trim($suiteKey);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit);
        $rows = DB::fetchAll($sql, $params);
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
    public function loadPreview(string $previewToken): ?array
    {
        $path = APP_ROOT . '/storage/restore_previews/' . $previewToken . '.json';
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function savePreview(string $previewToken, array $payload): string
    {
        $dir = APP_ROOT . '/storage/restore_previews';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . $previewToken . '.json';
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $path;
    }

    public function deletePreview(string $previewToken): void
    {
        $path = APP_ROOT . '/storage/restore_previews/' . $previewToken . '.json';
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
