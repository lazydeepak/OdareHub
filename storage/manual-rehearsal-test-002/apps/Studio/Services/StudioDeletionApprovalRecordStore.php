<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * Append-only storage for immutable deletion approval records.
 * Records are Studio provenance artifacts and never mutate owner runtime files.
 */
final class StudioDeletionApprovalRecordStore
{
    /** @param array<string,mixed> $record @return array<string,mixed> */
    public static function append(array $record, ?string $root = null): array
    {
        $ownerKey = trim((string)($record['target']['owner_key'] ?? ''));
        $changeSetFingerprint = trim((string)($record['source']['change_set_fingerprint'] ?? ''));
        $recordId = trim((string)($record['record_id'] ?? ''));
        if ($ownerKey === '' || $changeSetFingerprint === '' || $recordId === '') {
            throw new \RuntimeException('Approval record storage requires owner, change-set fingerprint, and record id.');
        }

        $base = self::basePath($root);
        $directory = $base
            . DIRECTORY_SEPARATOR . self::slug($ownerKey)
            . DIRECTORY_SEPARATOR . self::slug($changeSetFingerprint);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create deletion approval record directory.');
        }

        $filename = self::slug($recordId) . '.json';
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        $handle = @fopen($path, 'x+b');
        if ($handle === false) {
            throw new \RuntimeException('Approval record already exists or cannot be created.');
        }

        $encoded = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            fclose($handle);
            @unlink($path);
            throw new \RuntimeException('Unable to encode deletion approval record.');
        }
        $encoded .= "\n";

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock deletion approval record.');
            }
            $length = strlen($encoded);
            $written = 0;
            while ($written < $length) {
                $chunk = fwrite($handle, substr($encoded, $written));
                if ($chunk === false || $chunk === 0) {
                    throw new \RuntimeException('Unable to write complete deletion approval record.');
                }
                $written += $chunk;
            }
            fflush($handle);
            flock($handle, LOCK_UN);
        } catch (\Throwable $exception) {
            fclose($handle);
            @unlink($path);
            throw $exception;
        }
        fclose($handle);

        return [
            'storage_scope' => 'studio_provenance',
            'relative_path' => self::relativePath($path, $root),
            'immutable' => 'yes',
            'append_only' => 'yes',
        ];
    }

    /** @return array<string,mixed>|null */
    public static function latest(string $ownerKey, string $changeSetFingerprint, ?string $root = null): ?array
    {
        $ownerKey = trim($ownerKey);
        $changeSetFingerprint = trim($changeSetFingerprint);
        if ($ownerKey === '' || $changeSetFingerprint === '') {
            return null;
        }

        $directory = self::basePath($root)
            . DIRECTORY_SEPARATOR . self::slug($ownerKey)
            . DIRECTORY_SEPARATOR . self::slug($changeSetFingerprint);
        if (!is_dir($directory)) {
            return null;
        }

        return self::latestFromPaths(glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [], $root);
    }

    /** @return array<string,mixed>|null */
    public static function latestForOwner(string $ownerKey, ?string $root = null): ?array
    {
        $ownerKey = trim($ownerKey);
        if ($ownerKey === '') {
            return null;
        }

        $ownerDirectory = self::basePath($root) . DIRECTORY_SEPARATOR . self::slug($ownerKey);
        if (!is_dir($ownerDirectory)) {
            return null;
        }

        return self::latestFromPaths(
            glob($ownerDirectory . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.json') ?: [],
            $root
        );
    }

    /** @param array<int,string> $paths @return array<string,mixed>|null */
    private static function latestFromPaths(array $paths, ?string $root): ?array
    {
        $records = [];
        foreach ($paths as $path) {
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
                : strcmp((string)($right['record_id'] ?? ''), (string)($left['record_id'] ?? ''));
        });
        return $records[0];
    }

    private static function basePath(?string $root): string
    {
        $repositoryRoot = $root !== null && trim($root) !== ''
            ? rtrim($root, DIRECTORY_SEPARATOR)
            : (defined('APP_ROOT') ? rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR) : dirname(__DIR__, 3));
        return $repositoryRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'studio' . DIRECTORY_SEPARATOR . 'deletion-approvals';
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
