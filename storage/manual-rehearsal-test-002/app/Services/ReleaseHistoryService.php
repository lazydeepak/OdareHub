<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class ReleaseHistoryService
{
    public const STATUS_PREVIEWED = 'previewed';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_FAILED = 'failed';

    public function ensureTable(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS core_release_runs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            package_name VARCHAR(255) NULL,
            package_path VARCHAR(500) NULL,
            scope_key VARCHAR(80) NOT NULL,
            release_version VARCHAR(80) NOT NULL,
            included_suites_json LONGTEXT NULL,
            included_modules_json LONGTEXT NULL,
            notes_text TEXT NULL,
            preview_token VARCHAR(80) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'previewed',
            warning_text TEXT NULL,
            error_text TEXT NULL,
            summary_json LONGTEXT NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_release_preview_token (preview_token),
            KEY idx_release_created (created_at),
            KEY idx_release_scope (scope_key, release_version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * @param array<string,mixed> $summary
     * @param array<int,string> $includedSuites
     * @param array<int,string> $includedModules
     */
    public function startPreview(string $scopeKey, string $releaseVersion, array $includedSuites, array $includedModules, string $notes, string $previewToken, array $summary): int
    {
        $this->ensureTable();
        DB::query(
            'INSERT INTO core_release_runs (scope_key, release_version, included_suites_json, included_modules_json, notes_text, preview_token, status, warning_text, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $scopeKey,
                $releaseVersion,
                json_encode(array_values($includedSuites), JSON_UNESCAPED_SLASHES),
                json_encode(array_values($includedModules), JSON_UNESCAPED_SLASHES),
                $notes,
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
    public function completeRelease(int $runId, string $packageName, string $packagePath, array $summary): void
    {
        $this->ensureTable();
        DB::query(
            'UPDATE core_release_runs
             SET package_name=?, package_path=?, status=?, warning_text=?, error_text=NULL, summary_json=?
             WHERE id=?',
            [
                $packageName,
                $packagePath,
                self::STATUS_GENERATED,
                trim((string)($summary['warning_text'] ?? '')),
                json_encode($summary, JSON_UNESCAPED_SLASHES),
                $runId,
            ]
        );
    }

    /**
     * @param array<string,mixed> $summary
     */
    public function failRun(?int $runId, string $scopeKey, string $releaseVersion, array $includedSuites, array $includedModules, string $notes, string $errorText, array $summary = []): void
    {
        $this->ensureTable();
        if ($runId !== null && $runId > 0) {
            DB::query(
                'UPDATE core_release_runs
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
            'INSERT INTO core_release_runs (scope_key, release_version, included_suites_json, included_modules_json, notes_text, status, warning_text, error_text, summary_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $scopeKey,
                $releaseVersion,
                json_encode(array_values($includedSuites), JSON_UNESCAPED_SLASHES),
                json_encode(array_values($includedModules), JSON_UNESCAPED_SLASHES),
                $notes,
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
        $rows = DB::fetchAll('SELECT * FROM core_release_runs ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit));
        foreach ($rows as &$row) {
            $row['included_suites'] = json_decode((string)($row['included_suites_json'] ?? '[]'), true) ?: [];
            $row['included_modules'] = json_decode((string)($row['included_modules_json'] ?? '[]'), true) ?: [];
            $row['summary'] = json_decode((string)($row['summary_json'] ?? '{}'), true) ?: [];
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
        $row = DB::fetchOne('SELECT * FROM core_release_runs WHERE id=? LIMIT 1', [$id]);
        if (!is_array($row)) {
            return null;
        }
        $row['included_suites'] = json_decode((string)($row['included_suites_json'] ?? '[]'), true) ?: [];
        $row['included_modules'] = json_decode((string)($row['included_modules_json'] ?? '[]'), true) ?: [];
        $row['summary'] = json_decode((string)($row['summary_json'] ?? '{}'), true) ?: [];
        return $row;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function savePreview(string $previewToken, array $payload): string
    {
        $dir = APP_ROOT . '/storage/release_previews';
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
        $path = APP_ROOT . '/storage/release_previews/' . $previewToken . '.json';
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    public function deletePreview(string $previewToken): void
    {
        $path = APP_ROOT . '/storage/release_previews/' . $previewToken . '.json';
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
