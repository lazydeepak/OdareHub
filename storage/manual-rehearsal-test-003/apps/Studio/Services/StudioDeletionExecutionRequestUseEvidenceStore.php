<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/** Read-only lookup for single-use claim or execution evidence bound to a request. */
final class StudioDeletionExecutionRequestUseEvidenceStore
{
    /** @return array<string,mixed>|null */
    public static function latest(string $requestId, ?string $root = null): ?array
    {
        $requestId = trim($requestId);
        if ($requestId === '') {
            return null;
        }
        $directory = self::basePath($root) . DIRECTORY_SEPARATOR . self::slug($requestId);
        if (!is_dir($directory)) {
            return null;
        }
        $records = [];
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $decoded = json_decode((string)file_get_contents($path), true);
            if (!is_array($decoded)) {
                continue;
            }
            $decoded['storage'] = [
                'storage_scope' => 'studio_provenance',
                'relative_path' => self::relativePath($path, $root),
                'immutable' => 'yes',
                'append_only' => 'yes',
            ];
            $records[] = $decoded;
        }
        if ($records === []) {
            return null;
        }
        usort($records, static function (array $left, array $right): int {
            $timeCompare = strcmp((string)($right['recorded_at_utc'] ?? ''), (string)($left['recorded_at_utc'] ?? ''));
            return $timeCompare !== 0
                ? $timeCompare
                : strcmp((string)($right['use_id'] ?? ''), (string)($left['use_id'] ?? ''));
        });
        return $records[0];
    }

    private static function basePath(?string $root): string
    {
        $repositoryRoot = $root !== null && trim($root) !== ''
            ? rtrim($root, DIRECTORY_SEPARATOR)
            : (defined('APP_ROOT') ? rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR) : dirname(__DIR__, 3));
        return $repositoryRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'studio' . DIRECTORY_SEPARATOR . 'deletion-execution-uses';
    }

    private static function relativePath(string $path, ?string $root): string
    {
        $repositoryRoot = $root !== null && trim($root) !== ''
            ? rtrim($root, DIRECTORY_SEPARATOR)
            : (defined('APP_ROOT') ? rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR) : dirname(__DIR__, 3));
        $prefix = $repositoryRoot . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $prefix)
            ? str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($prefix)))
            : str_replace(DIRECTORY_SEPARATOR, '/', $path);
    }

    private static function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '__', trim($value));
        $slug = trim((string)$slug, '._-');
        return $slug !== '' ? substr($slug, 0, 180) : 'unknown';
    }
}
