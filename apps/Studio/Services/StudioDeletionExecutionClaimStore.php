<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

require_once __DIR__ . '/StudioDeletionExecutionRequestUseEvidenceStore.php';

/**
 * Atomic append-only storage for one immutable claim per execution request.
 * Claims live in the canonical single-use evidence namespace.
 */
final class StudioDeletionExecutionClaimStore
{
    /** @param array<string,mixed> $record @return array<string,mixed> */
    public static function claim(array $record, ?string $root = null): array
    {
        $requestId = trim((string)($record['source']['request_id'] ?? ''));
        $requestFingerprint = trim((string)($record['source']['request_fingerprint'] ?? ''));
        $claimId = trim((string)($record['claim_id'] ?? ''));
        if ($requestId === '' || $requestFingerprint === '' || $claimId === '') {
            throw new \RuntimeException('Execution claim storage requires request id, request fingerprint, and claim id.');
        }

        $directory = self::basePath($root) . DIRECTORY_SEPARATOR . self::slug($requestId);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create deletion execution claim directory.');
        }

        $path = $directory . DIRECTORY_SEPARATOR . 'claim.json';
        $handle = @fopen($path, 'x+b');
        if ($handle === false) {
            throw new \RuntimeException('Execution request is already claimed or claim storage cannot be created.');
        }

        $encoded = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            fclose($handle);
            @unlink($path);
            throw new \RuntimeException('Unable to encode deletion execution claim.');
        }
        $encoded .= "\n";

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock deletion execution claim.');
            }
            $length = strlen($encoded);
            $written = 0;
            while ($written < $length) {
                $chunk = fwrite($handle, substr($encoded, $written));
                if ($chunk === false || $chunk === 0) {
                    throw new \RuntimeException('Unable to write complete deletion execution claim.');
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
            'atomic_single_use' => 'yes',
        ];
    }

    /** @return array<string,mixed>|null */
    public static function latest(string $requestId, ?string $root = null): ?array
    {
        return StudioDeletionExecutionRequestUseEvidenceStore::latest($requestId, $root);
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
