<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/** Append-only storage for immutable deletion execution request provenance. */
final class StudioDeletionExecutionRequestStore
{
    /** @param array<string,mixed> $record @return array<string,mixed> */
    public static function append(array $record, ?string $root = null): array
    {
        $ownerKey = trim((string)($record['target']['owner_key'] ?? ''));
        $changeSetFingerprint = trim((string)($record['source']['change_set_fingerprint'] ?? ''));
        $dryRunFingerprint = trim((string)($record['source']['dry_run_fingerprint'] ?? ''));
        $requestId = trim((string)($record['request_id'] ?? ''));
        if ($ownerKey === '' || $changeSetFingerprint === '' || $dryRunFingerprint === '' || $requestId === '') {
            throw new \RuntimeException('Execution request storage requires owner, change-set fingerprint, dry-run fingerprint, and request id.');
        }

        $directory = self::basePath($root)
            . DIRECTORY_SEPARATOR . self::slug($ownerKey)
            . DIRECTORY_SEPARATOR . self::slug($changeSetFingerprint)
            . DIRECTORY_SEPARATOR . self::slug($dryRunFingerprint);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create deletion execution request directory.');
        }

        $path = $directory . DIRECTORY_SEPARATOR . self::slug($requestId) . '.json';
        $handle = @fopen($path, 'x+b');
        if ($handle === false) {
            throw new \RuntimeException('Execution request already exists or cannot be created.');
        }

        $encoded = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            fclose($handle);
            @unlink($path);
            throw new \RuntimeException('Unable to encode deletion execution request.');
        }
        $encoded .= "\n";

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock deletion execution request.');
            }
            $length = strlen($encoded);
            $written = 0;
            while ($written < $length) {
                $chunk = fwrite($handle, substr($encoded, $written));
                if ($chunk === false || $chunk === 0) {
                    throw new \RuntimeException('Unable to write complete deletion execution request.');
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
    public static function latest(string $ownerKey, string $changeSetFingerprint, string $dryRunFingerprint, ?string $root = null): ?array
    {
        $ownerKey = trim($ownerKey);
        $changeSetFingerprint = trim($changeSetFingerprint);
        $dryRunFingerprint = trim($dryRunFingerprint);
        if ($ownerKey === '' || $changeSetFingerprint === '' || $dryRunFingerprint === '') {
            return null;
        }

        $directory = self::basePath($root)
            . DIRECTORY_SEPARATOR . self::slug($ownerKey)
            . DIRECTORY_SEPARATOR . self::slug($changeSetFingerprint)
            . DIRECTORY_SEPARATOR . self::slug($dryRunFingerprint);
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
            glob($ownerDirectory . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.json') ?: [],
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
            $timeCompare = strcmp((string)($right['requested_at_utc'] ?? ''), (string)($left['requested_at_utc'] ?? ''));
            return $timeCompare !== 0
                ? $timeCompare
                : strcmp((string)($right['request_id'] ?? ''), (string)($left['request_id'] ?? ''));
        });
        return $records[0];
    }

    private static function basePath(?string $root): string
    {
        $repositoryRoot = $root !== null && trim($root) !== ''
            ? rtrim($root, DIRECTORY_SEPARATOR)
            : (defined('APP_ROOT') ? rtrim((string)APP_ROOT, DIRECTORY_SEPARATOR) : dirname(__DIR__, 3));
        return $repositoryRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'studio' . DIRECTORY_SEPARATOR . 'deletion-execution-requests';
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
