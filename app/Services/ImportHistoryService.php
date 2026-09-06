<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class ImportHistoryService
{
    public const STATUS_PREVIEWED = 'previewed';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_FAILED = 'failed';

    public function ensureTable(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS core_import_runs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            suite_key VARCHAR(80) NOT NULL,
            import_type VARCHAR(120) NOT NULL,
            source_file VARCHAR(255) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'previewed',
            preview_token VARCHAR(80) NULL,
            valid_rows INT NOT NULL DEFAULT 0,
            invalid_rows INT NOT NULL DEFAULT 0,
            skipped_rows INT NOT NULL DEFAULT 0,
            duplicate_rows INT NOT NULL DEFAULT 0,
            imported_rows INT NOT NULL DEFAULT 0,
            failed_rows INT NOT NULL DEFAULT 0,
            warning_text TEXT NULL,
            error_text TEXT NULL,
            summary_json LONGTEXT NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_import_preview_token (preview_token),
            KEY idx_import_suite_created (suite_key, created_at),
            KEY idx_import_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function startPreview(string $suiteKey, string $importType, string $sourceFile, string $previewToken, array $summary): int
    {
        $this->ensureTable();
        DB::query(
            'INSERT INTO core_import_runs (suite_key, import_type, source_file, status, preview_token, valid_rows, invalid_rows, skipped_rows, duplicate_rows, imported_rows, failed_rows, warning_text, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $suiteKey,
                $importType,
                $sourceFile,
                self::STATUS_PREVIEWED,
                $previewToken,
                (int)($summary['valid_rows'] ?? 0),
                (int)($summary['invalid_rows'] ?? 0),
                (int)($summary['skipped_rows'] ?? 0),
                (int)($summary['duplicate_rows'] ?? 0),
                0,
                0,
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
    public function completeImport(int $runId, array $summary): void
    {
        $this->ensureTable();
        DB::query(
            'UPDATE core_import_runs
             SET status=?, valid_rows=?, invalid_rows=?, skipped_rows=?, duplicate_rows=?, imported_rows=?, failed_rows=?, warning_text=?, error_text=NULL, summary_json=?
             WHERE id=?',
            [
                self::STATUS_IMPORTED,
                (int)($summary['valid_rows'] ?? 0),
                (int)($summary['invalid_rows'] ?? 0),
                (int)($summary['skipped_rows'] ?? 0),
                (int)($summary['duplicate_rows'] ?? 0),
                (int)($summary['imported_rows'] ?? 0),
                (int)($summary['failed_rows'] ?? 0),
                trim((string)($summary['warning_text'] ?? '')),
                json_encode($summary, JSON_UNESCAPED_SLASHES),
                $runId,
            ]
        );
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function failRun(?int $runId, string $suiteKey, string $importType, string $sourceFile, string $errorText, array $summary = []): void
    {
        $this->ensureTable();
        if ($runId !== null && $runId > 0) {
            DB::query(
                'UPDATE core_import_runs
                 SET status=?, error_text=?, summary_json=?, warning_text=?
                 WHERE id=?',
                [
                    self::STATUS_FAILED,
                    $errorText,
                    json_encode($summary, JSON_UNESCAPED_SLASHES),
                    trim((string)($summary['warning_text'] ?? '')),
                    $runId,
                ]
            );
            return;
        }

        DB::query(
            'INSERT INTO core_import_runs (suite_key, import_type, source_file, status, error_text, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?)',
            [
                $suiteKey,
                $importType,
                $sourceFile,
                self::STATUS_FAILED,
                $errorText,
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
        $sql = 'SELECT * FROM core_import_runs';
        $params = [];
        if ($suiteKey !== null && trim($suiteKey) !== '') {
            $sql .= ' WHERE suite_key=?';
            $params[] = trim($suiteKey);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit);

        $rows = DB::fetchAll($sql, $params);
        foreach ($rows as &$row) {
            $row['summary'] = $this->decodeMap((string)($row['summary_json'] ?? '{}'));
        }
        unset($row);
        return $rows;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function savePreviewPayload(string $previewToken, array $payload): string
    {
        $dir = APP_ROOT . '/storage/import_previews';
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
    public function loadPreviewPayload(string $previewToken): ?array
    {
        $path = APP_ROOT . '/storage/import_previews/' . $previewToken . '.json';
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    public function deletePreviewPayload(string $previewToken): void
    {
        $path = APP_ROOT . '/storage/import_previews/' . $previewToken . '.json';
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeMap(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
