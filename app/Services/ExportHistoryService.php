<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class ExportHistoryService
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function ensureTable(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS core_export_runs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            target_type VARCHAR(40) NOT NULL,
            target_key VARCHAR(120) NOT NULL,
            suite_key VARCHAR(80) NULL,
            export_type VARCHAR(80) NOT NULL,
            file_name VARCHAR(255) NULL,
            file_path VARCHAR(500) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'completed',
            warning_text TEXT NULL,
            error_text TEXT NULL,
            summary_json LONGTEXT NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_export_target (target_type, target_key),
            KEY idx_export_suite (suite_key, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function recordSuccess(string $targetType, string $targetKey, ?string $suiteKey, string $exportType, string $fileName, string $filePath, array $summary = [], string $warningText = ''): int
    {
        $this->ensureTable();
        DB::query(
            'INSERT INTO core_export_runs (target_type, target_key, suite_key, export_type, file_name, file_path, status, warning_text, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $targetType,
                $targetKey,
                $suiteKey,
                $exportType,
                $fileName,
                $filePath,
                self::STATUS_COMPLETED,
                $warningText,
                json_encode($summary, JSON_UNESCAPED_SLASHES),
                (string)(Auth::user()['email'] ?? 'system'),
            ]
        );
        return (int)DB::conn()->insert_id;
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function recordFailure(string $targetType, string $targetKey, ?string $suiteKey, string $exportType, string $errorText, array $summary = []): int
    {
        $this->ensureTable();
        DB::query(
            'INSERT INTO core_export_runs (target_type, target_key, suite_key, export_type, status, error_text, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $targetType,
                $targetKey,
                $suiteKey,
                $exportType,
                self::STATUS_FAILED,
                $errorText,
                json_encode($summary, JSON_UNESCAPED_SLASHES),
                (string)(Auth::user()['email'] ?? 'system'),
            ]
        );
        return (int)DB::conn()->insert_id;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function recentRuns(?string $suiteKey = null, ?string $targetType = null, int $limit = 30): array
    {
        $this->ensureTable();
        $sql = 'SELECT * FROM core_export_runs WHERE 1=1';
        $params = [];
        if ($suiteKey !== null && trim($suiteKey) !== '') {
            $sql .= ' AND suite_key=?';
            $params[] = trim($suiteKey);
        }
        if ($targetType !== null && trim($targetType) !== '') {
            $sql .= ' AND target_type=?';
            $params[] = trim($targetType);
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
    public function findById(int $id): ?array
    {
        $this->ensureTable();
        $row = DB::fetchOne('SELECT * FROM core_export_runs WHERE id=? LIMIT 1', [$id]);
        if (!is_array($row)) {
            return null;
        }
        $decoded = json_decode((string)($row['summary_json'] ?? '{}'), true);
        $row['summary'] = is_array($decoded) ? $decoded : [];
        return $row;
    }
}
